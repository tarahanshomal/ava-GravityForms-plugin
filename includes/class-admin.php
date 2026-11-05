<?php
namespace AvaGF;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
        add_action( 'gform_entry_detail_sidebar_middle', array( __CLASS__, 'entry' ), 10, 2 );
        add_action( 'admin_post_ava_gf_refresh', array( __CLASS__, 'refresh' ) );
    }

    public static function menu() {
        add_options_page( 'Ava Gravity Forms', 'Ava Gravity Forms', 'manage_options', 'ava-gravityforms', array( __CLASS__, 'page' ) );
    }

    public static function register() {
        register_setting( 'ava_gf', Settings::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( 'AvaGF\\Settings', 'sanitize' ), 'show_in_rest' => false ) );
    }

    public static function notice() {
        if ( ! current_user_can( 'manage_options' ) || ( class_exists( 'GFForms' ) && version_compare( \GFForms::$version, '2.8', '>=' ) ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'افزونهٔ آوا به Gravity Forms نسخهٔ ۲.۸ یا جدیدتر نیاز دارد. ابتدا آن را نصب و فعال کنید.', 'ava-gravityforms-plugin' ) . '</p></div>';
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $s = Settings::get();
        echo '<div class="wrap"><h1>Ava Gravity Forms</h1><p>اتصال اعلان‌های گرویتی فرم به پنل آوا</p>';
        echo '<div class="notice notice-info inline"><p>در API فعلی آوا، درخواست پیامک ابتدا برای بررسی ثبت می‌شود. ارسال پس از تأیید و شروع در پنل آوا انجام می‌شود. این افزونه سرویس احراز موبایل یا رمز یک‌بارمصرف نیست.</p></div>';
        settings_errors( Settings::OPTION );
        echo '<form action="options.php" method="post">';
        settings_fields( 'ava_gf' );
        echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row">فعال‌سازی</th><td><label><input type="checkbox" name="ava_gf_settings[enabled]" value="yes" ' . checked( $s['enabled'], 'yes', false ) . '> ثبت اعلان پیامکی فرم‌ها در آوا فعال باشد</label></td></tr>';
        echo '<tr><th scope="row"><label for="ava-gf-url">نشانی پنل</label></th><td><input id="ava-gf-url" class="regular-text code" type="url" name="ava_gf_settings[api_url]" value="' . esc_attr( $s['api_url'] ) . '" required><p class="description">دامنهٔ HTTPS پنل آوا یا دامنهٔ نماینده، بدون مسیر.</p></td></tr>';
        echo '<tr><th scope="row"><label for="ava-gf-key">کلید API</label></th><td><input id="ava-gf-key" class="regular-text code" type="password" name="ava_gf_settings[api_key]" value="" autocomplete="new-password" ' . disabled( defined( 'AVA_GF_API_KEY' ), true, false ) . '><p class="description">';
        echo esc_html( defined( 'AVA_GF_API_KEY' ) ? 'کلید از ثابت AVA_GF_API_KEY در wp-config.php خوانده می‌شود.' : ( '' !== $s['api_key'] ? 'کلید ذخیره شده است؛ خالی بگذارید تا حفظ شود.' : 'کلیدی با دسترسی sms:send و sms:read از پنل آوا بسازید.' ) );
        echo '</p>';
        if ( ! defined( 'AVA_GF_API_KEY' ) ) {
            echo '<label><input type="checkbox" name="ava_gf_settings[delete_api_key]" value="yes"> حذف کلید ذخیره‌شده</label>';
        }
        echo '</td></tr><tr><th scope="row"><label for="ava-gf-sender">خط فرستنده</label></th><td><input id="ava-gf-sender" class="regular-text code" type="text" name="ava_gf_settings[sender]" value="' . esc_attr( $s['sender'] ) . '" pattern="[0-9]{3,20}"><p class="description">اختیاری؛ یکی از خطوط مجاز حساب. خالی برای خط پیش‌فرض.</p></td></tr></tbody></table>';
        submit_button();
        echo '</form><h2>اعلان هر فرم</h2><p>در بخش «فرم‌ها»، تنظیمات فرم موردنظر را باز کنید و در «Ava SMS» یک اعلان بسازید. گیرنده را از فیلد موبایل یا حداکثر پنج شمارهٔ ثابت انتخاب کنید و متن و شرط ارسال را تنظیم کنید.</p><p>نتیجه در یادداشت و جزئیات ورودی ثبت می‌شود. به‌روزرسانی گزارش فقط وضعیت را می‌خواند و پیامک دوباره نمی‌فرستد. پاسخ نامشخص خودکار تکرار نمی‌شود.</p><p><a href="https://github.com/tarahanshomal/ava-GravityForms-plugin#readme" target="_blank" rel="noopener noreferrer">راهنما و کد منبع</a></p></div>';
    }

    public static function entry( $form, $entry ) {
        if ( ! current_user_can( 'manage_options' ) || empty( $entry['id'] ) ) {
            return;
        }
        $records = Delivery::records( $entry['id'] );
        if ( ! $records ) {
            return;
        }
        echo '<div class="postbox"><h3 class="hndle"><span>Ava SMS</span></h3><div class="inside">';
        foreach ( $records as $name => $record ) {
            if ( (int) $record['form_id'] !== (int) $form['id'] ) {
                continue;
            }
            echo '<ul>';
            foreach ( $record['results'] as $result ) {
                echo '<li><bdi>' . esc_html( $result['phone'] ?? '' ) . '</bdi>: ' . esc_html( Delivery::status_label( $result ) );
                if ( ! empty( $result['id'] ) ) {
                    echo ' <code>' . esc_html( $result['id'] ) . '</code>';
                }
                if ( ! empty( $result['detail'] ) ) {
                    echo '<br>' . esc_html( $result['detail'] );
                }
                if ( ! empty( $result['report_error'] ) ) {
                    echo '<br>' . esc_html( $result['report_error'] );
                }
                echo '</li>';
            }
            echo '</ul>';
            $entry_id = absint( $entry['id'] );
            $feed_id = absint( substr( $name, strrpos( $name, '_' ) + 1 ) );
            $form_key = 'ava-gf-refresh-' . $entry_id . '-' . $feed_id;
            echo '<button type="submit" form="' . esc_attr( $form_key ) . '" class="button">به‌روزرسانی گزارش این اعلان</button>';
            // Associate with a footer form; GF wraps the entry page in a form.
            add_action( 'admin_footer', static function () use ( $entry_id, $feed_id, $form_key ) {
                echo '<form id="' . esc_attr( $form_key ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ava_gf_refresh"><input type="hidden" name="entry_id" value="' . esc_attr( $entry_id ) . '"><input type="hidden" name="feed_id" value="' . esc_attr( $feed_id ) . '">';
                wp_nonce_field( 'ava_gf_refresh_' . $entry_id . '_' . $feed_id );
                echo '</form>';
            } );
        }
        echo '<p>ثبت درخواست، به معنای ارسال یا تحویل نیست. بررسی و شروع پیامک در پنل آوا انجام می‌شود.</p></div></div>';
    }

    public static function refresh() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی کافی نیست.', '', array( 'response' => 403 ) );
        }
        $entry_id = isset( $_POST['entry_id'] ) && is_scalar( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
        $feed_id = isset( $_POST['feed_id'] ) && is_scalar( $_POST['feed_id'] ) ? absint( $_POST['feed_id'] ) : 0;
        check_admin_referer( 'ava_gf_refresh_' . $entry_id . '_' . $feed_id );
        if ( ! class_exists( 'GFAPI' ) ) {
            wp_die( 'Gravity Forms در دسترس نیست.' );
        }
        $entry = \GFAPI::get_entry( $entry_id );
        if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
            wp_die( 'ورودی پیدا نشد.', '', array( 'response' => 404 ) );
        }
        $settings = Settings::get();
        $name = Delivery::key( $entry_id, $feed_id );
        $saved = get_option( $name, false );
        foreach ( is_array( $saved ) ? array( $name => $saved ) : array() as $name => $record ) {
            if ( (int) $record['form_id'] !== (int) $entry['form_id'] || ! hash_equals( $record['connection'] ?? '', Delivery::connection( $settings ) ) ) {
                continue;
            }
            foreach ( $record['results'] as $index => $result ) {
                if ( empty( $result['id'] ) || $index >= 5 ) {
                    continue;
                }
                $response = Api::report( $result['id'], $settings );
                if ( is_wp_error( $response ) ) {
                    Delivery::update_result( $name, $index, array( 'report_error' => $response->get_error_message() ), $result['id'] );
                } else {
                    Delivery::update_result( $name, $index, array_merge( $response, array( 'report_error' => '' ) ), $result['id'] );
                }
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=gf_entries&view=entry&id=' . absint( $entry['form_id'] ) . '&lid=' . $entry_id ) );
        exit;
    }
}
