<?php
namespace AvaGF;

defined( 'ABSPATH' ) || exit;

final class Delivery {
    const PREFIX = 'ava_gf_delivery_';

    public static function process( $feed, $entry, $form ) {
        $settings = Settings::get();
        if ( 'yes' !== $settings['enabled'] || 'active' !== ( $entry['status'] ?? 'active' ) ) {
            return null;
        }
        $entry_id = absint( $entry['id'] ?? 0 );
        $feed_id = absint( $feed['id'] ?? 0 );
        $form_id = absint( $form['id'] ?? 0 );
        if ( ! $entry_id || ! $feed_id || ! $form_id || $form_id !== absint( $entry['form_id'] ?? 0 ) ||
            $form_id !== absint( $feed['form_id'] ?? 0 ) || ( isset( $feed['is_active'] ) && ! $feed['is_active'] ) ) {
            return new \WP_Error( 'ava_identity', 'شناسهٔ فرم، ورودی یا اعلان معتبر نیست.' );
        }
        $option = self::key( $entry_id, $feed_id );
        if ( false !== get_option( $option, false ) ) {
            return null;
        }
        if ( ! Settings::valid_url( $settings['api_url'] ) || ! Settings::valid_key( $settings['api_key'] ) ) {
            return new \WP_Error( 'ava_configuration', 'نشانی یا کلید API آوا تنظیم نشده است.' );
        }
        $meta = isset( $feed['meta'] ) && is_array( $feed['meta'] ) ? $feed['meta'] : array();
        $mode = $meta['recipientMode'] ?? '';
        if ( 'field' === $mode ) {
            $field_id = $meta['phoneField'] ?? '';
            if ( ! is_scalar( $field_id ) || ! preg_match( '/^[1-9][0-9]*(?:\.[0-9]+)?$/D', (string) $field_id ) ) {
                return new \WP_Error( 'ava_field', 'فیلد موبایل را انتخاب کنید.' );
            }
            // Do not allow an arbitrary entry property to become a recipient.
            $matched = false;
            foreach ( $form['fields'] ?? array() as $field ) {
                $id = is_object( $field ) ? $field->id : ( $field['id'] ?? 0 );
                $type = is_object( $field ) ? $field->type : ( $field['type'] ?? '' );
                if ( (string) $id === (string) $field_id && in_array( $type, array( 'phone', 'text' ), true ) ) {
                    $matched = true;
                    break;
                }
            }
            if ( ! $matched ) {
                return new \WP_Error( 'ava_field', 'فیلد موبایل انتخاب‌شده در فرم موجود نیست.' );
            }
            $raw_phones = array( $entry[ (string) $field_id ] ?? '' );
        } elseif ( 'fixed' === $mode && is_string( $meta['fixedPhones'] ?? null ) ) {
            $raw_phones = preg_split( '/[,;\s،؛]+/u', trim( $meta['fixedPhones'] ), -1, PREG_SPLIT_NO_EMPTY );
            if ( ! $raw_phones || count( $raw_phones ) > 5 ) {
                return new \WP_Error( 'ava_recipients', 'بین یک تا پنج شمارهٔ موبایل ثابت وارد کنید.' );
            }
        } else {
            return new \WP_Error( 'ava_recipients', 'روش انتخاب گیرنده معتبر نیست.' );
        }
        $phones = array();
        foreach ( $raw_phones as $raw_phone ) {
            $phone = Phone::normalize( $raw_phone );
            if ( is_wp_error( $phone ) ) {
                return $phone;
            }
            $phones[ $phone ] = $phone;
        }
        $template = $meta['message'] ?? '';
        if ( ! is_string( $template ) ) {
            return new \WP_Error( 'ava_text', 'متن پیامک معتبر نیست.' );
        }
        $text = \GFCommon::replace_variables( $template, $form, $entry, false, false, false, 'text' );
        $text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
        $text = Api::validate_text( $text );
        if ( is_wp_error( $text ) ) {
            return $text;
        }
        $record = array(
            'form_id' => $form_id,
            'connection' => self::connection( $settings ),
            'created_at' => gmdate( 'c' ),
            'results' => array(),
        );
        foreach ( $phones as $phone ) {
            $record['results'][] = array( 'phone' => Phone::mask( $phone ), 'status' => 'unknown' );
        }
        // An insert-only claim precedes HTTP. A crash leaves unknown outcomes.
        // add_option() is unsuitable here because it can update a duplicate row.
        $claimed = self::claim( $option, $record );
        if ( true !== $claimed ) {
            return $claimed;
        }
        foreach ( array_values( $phones ) as $index => $phone ) {
            $request_key = 'avagf_' . hash_hmac( 'sha256', $option . '|' . $phone . '|' . home_url( '/' ), wp_salt( 'auth' ) );
            $response = Api::send( $phone, $text, $request_key, $settings );
            if ( is_wp_error( $response ) ) {
                $data = $response->get_error_data();
                $record['results'][ $index ]['status'] = ! empty( $data['uncertain'] ) ? 'unknown' : 'rejected';
                $record['results'][ $index ]['detail'] = $response->get_error_message();
            } else {
                $record['results'][ $index ] = array_merge( $record['results'][ $index ], $response );
            }
            if ( ! self::update_result( $option, $index, $record['results'][ $index ] ) ) {
                // The durable guard remains in place even if the result cannot
                // be stored. Do not expose a confirmed state that was lost.
                $record['results'][ $index ] = array(
                    'phone' => Phone::mask( $phone ),
                    'status' => 'unknown',
                    'detail' => 'ذخیرهٔ نتیجهٔ درخواست ممکن نشد؛ گزارش پنل آوا را بررسی کنید. تکرار خودکار انجام نمی‌شود.',
                );
            }
        }
        return $record['results'];
    }

    /**
     * Atomically create a durable delivery record without changing an old one.
     *
     * @return true|null|\WP_Error True for the owner, null for a duplicate.
     */
    public static function claim( $option, $record ) {
        global $wpdb;
        if ( ! is_string( $option ) || ! preg_match( '/^ava_gf_delivery_[1-9][0-9]*_[1-9][0-9]*$/D', $option ) || ! is_array( $record ) ) {
            return new \WP_Error( 'ava_storage', 'شناسهٔ ثبت درخواست معتبر نیست؛ هیچ پیامکی ثبت نشد.' );
        }
        $inserted = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
            $option,
            maybe_serialize( $record ),
            'no'
        ) );
        if ( 1 !== $inserted && 0 !== $inserted ) {
            return new \WP_Error( 'ava_storage', 'ثبت امن درخواست در پایگاه داده ممکن نشد؛ هیچ پیامکی ثبت نشد.' );
        }
        // A competing request can have populated a negative option cache.
        wp_cache_delete( $option, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
        return 1 === $inserted ? true : null;
    }

    /**
     * Merge one recipient's result with a compare-and-swap database update.
     * This preserves other recipients when sending and reporting overlap.
     */
    public static function update_result( $option, $index, $values, $expected_id = null ) {
        global $wpdb;
        if ( ! is_string( $option ) || ! preg_match( '/^ava_gf_delivery_[1-9][0-9]*_[1-9][0-9]*$/D', $option ) ||
            ! is_int( $index ) || $index < 0 || ! is_array( $values ) ||
            ( null !== $expected_id && ! is_string( $expected_id ) ) ) {
            return false;
        }
        for ( $attempt = 0; $attempt < 5; $attempt++ ) {
            // Read the current serialized value directly, bypassing stale
            // persistent option caches. Ledger values contain arrays only.
            $previous = $wpdb->get_var( $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option
            ) );
            if ( ! is_string( $previous ) ) {
                return false;
            }
            $record = @unserialize( $previous, array( 'allowed_classes' => false ) );
            if ( ! is_array( $record ) || ! isset( $record['results'][ $index ] ) || ! is_array( $record['results'][ $index ] ) ) {
                return false;
            }
            if ( null !== $expected_id && ( $record['results'][ $index ]['id'] ?? null ) !== $expected_id ) {
                return false;
            }
            $record['results'][ $index ] = array_merge( $record['results'][ $index ], $values );
            $replacement = maybe_serialize( $record );
            if ( $replacement === $previous ) {
                wp_cache_delete( $option, 'options' );
                wp_cache_delete( 'notoptions', 'options' );
                return true;
            }
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                $replacement,
                $option,
                $previous
            ) );
            if ( 1 === $updated ) {
                wp_cache_delete( $option, 'options' );
                wp_cache_delete( 'notoptions', 'options' );
                return true;
            }
            if ( 0 !== $updated ) {
                return false;
            }
        }
        return false;
    }

    public static function connection( $settings ) {
        return hash( 'sha256', ( $settings['api_url'] ?? '' ) . '|' . ( $settings['api_key'] ?? '' ) );
    }

    public static function key( $entry_id, $feed_id ) {
        return self::PREFIX . absint( $entry_id ) . '_' . absint( $feed_id );
    }

    public static function records( $entry_id ) {
        global $wpdb;
        $like = $wpdb->esc_like( self::PREFIX . absint( $entry_id ) . '_' ) . '%';
        $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 100", $like ) );
        $records = array();
        foreach ( $names as $name ) {
            $record = get_option( $name );
            if ( is_array( $record ) && isset( $record['results'] ) ) {
                $records[ $name ] = $record;
            }
        }
        return $records;
    }

    public static function delete_entry( $entry_id ) {
        foreach ( self::records( $entry_id ) as $name => $record ) {
            delete_option( $name );
        }
    }

    public static function status_label( $result ) {
        $labels = array(
            'pending' => 'در انتظار پردازش', 'sending' => 'در حال ارسال', 'unknown' => 'نامشخص؛ تکرار خودکار متوقف است',
            'accepted' => 'پذیرفته‌شده توسط سرویس', 'sent' => 'ارسال‌شده', 'delivered' => 'تحویل گزارش‌شده توسط اپراتور',
            'undelivered' => 'تحویل‌نشده', 'rejected' => 'ردشده', 'blocked' => 'مسدودشده', 'cancelled' => 'لغوشده',
            'simulated' => 'شبیه‌سازی‌شده', 'expired' => 'منقضی‌شده',
        );
        $status = $result['status'] ?? '';
        $campaign = $result['campaign_status'] ?? '';
        $review = $result['review_status'] ?? '';
        if ( in_array( $status, array( 'delivered', 'undelivered', 'rejected', 'blocked', 'cancelled', 'simulated', 'expired' ), true ) ) {
            return $labels[ $status ];
        }
        if ( in_array( $campaign, array( 'cancelled', 'expired' ), true ) ) {
            return $labels[ $campaign ];
        }
        if ( 'rejected' === $review ) {
            return 'ردشده در بررسی محتوا در پنل آوا';
        }
        if ( 'approved' === $review && 'draft' === $campaign ) {
            return 'تأییدشده؛ در انتظار شروع در پنل آوا';
        }
        if ( ! empty( $result['requires_review'] ) || 'pending' === $review ) {
            return 'در انتظار بررسی و شروع در پنل آوا';
        }
        if ( 'pending' === $status && 'paused' === $campaign ) {
            return 'متوقف‌شده در پنل آوا';
        }
        if ( 'pending' === $status && 'scheduled' === $campaign ) {
            return 'زمان‌بندی‌شده در پنل آوا';
        }
        return $labels[ $status ] ?? 'نامشخص';
    }
}
