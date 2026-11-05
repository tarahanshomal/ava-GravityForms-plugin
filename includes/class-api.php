<?php
namespace AvaGF;

defined( 'ABSPATH' ) || exit;

final class Api {
    public static function validate_text( $text ) {
        if ( ! is_string( $text ) || 1 !== preg_match( '//u', $text ) || '' === trim( $text ) ||
            preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text ) ) {
            return new \WP_Error( 'ava_text', 'متن پیامک معتبر نیست.' );
        }
        $length = preg_match_all( '/./us', $text, $unused );
        if ( $length > 1000 ) {
            return new \WP_Error( 'ava_text', 'متن نهایی پیامک باید حداکثر ۱۰۰۰ نویسه باشد.' );
        }
        return $text;
    }

    public static function send( $phone, $text, $idempotency_key, $settings = null ) {
        $phone = Phone::normalize( $phone );
        if ( is_wp_error( $phone ) ) {
            return $phone;
        }
        $text = self::validate_text( $text );
        if ( is_wp_error( $text ) ) {
            return $text;
        }
        if ( ! is_string( $idempotency_key ) || ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $idempotency_key ) ) {
            return new \WP_Error( 'ava_idempotency', 'شناسهٔ یکتای درخواست معتبر نیست.' );
        }
        $settings = null === $settings ? Settings::get() : $settings;
        $payload = array( 'phone' => $phone, 'text' => $text );
        if ( ! empty( $settings['sender'] ) ) {
            if ( ! preg_match( '/^[0-9]{3,20}$/D', (string) $settings['sender'] ) ) {
                return new \WP_Error( 'ava_sender', 'خط فرستنده معتبر نیست.' );
            }
            $payload['sender'] = $settings['sender'];
        }
        return self::request( 'POST', '/api/v1/sms', $payload, $idempotency_key, $settings );
    }

    public static function report( $id, $settings = null ) {
        if ( ! is_string( $id ) || ! preg_match( '/^[A-Za-z0-9_-]{1,100}$/D', $id ) ) {
            return new \WP_Error( 'ava_id', 'شناسهٔ پیامک معتبر نیست.' );
        }
        $result = self::request( 'GET', '/api/v1/sms/' . rawurlencode( $id ), null, '', $settings );
        if ( ! is_wp_error( $result ) && $id !== $result['id'] ) {
            return new \WP_Error( 'ava_report_id', 'شناسهٔ پاسخ گزارش با درخواست یکسان نیست.' );
        }
        return $result;
    }

    private static function request( $method, $path, $payload, $key, $settings ) {
        $settings = null === $settings ? Settings::get() : $settings;
        $url = Settings::valid_url( $settings['api_url'] ?? null );
        if ( ! $url || ! Settings::valid_key( $settings['api_key'] ?? null ) ) {
            return new \WP_Error( 'ava_configuration', 'نشانی یا کلید API آوا تنظیم نشده است.' );
        }
        $args = array(
            'method' => $method, 'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
            'reject_unsafe_urls' => true, 'limit_response_size' => 65536,
            'headers' => array( 'Authorization' => 'Bearer ' . $settings['api_key'], 'Accept' => 'application/json' ),
            'user-agent' => 'AvaGravityForms/' . AVA_GF_VERSION . '; WordPress',
        );
        if ( 'POST' === $method ) {
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $args['headers']['Idempotency-Key'] = $key;
            $args['body'] = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );
            $args['data_format'] = 'body';
        }
        $response = wp_safe_remote_request( $url . $path, $args );
        $post = 'POST' === $method;
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'ava_transport', 'پاسخ آوا دریافت نشد؛ وضعیت درخواست نامشخص است. تکرار خودکار انجام نمی‌شود.', array( 'uncertain' => $post ) );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( ! in_array( $code, array( 200, 202 ), true ) ) {
            $messages = array(
                400 => 'شماره، متن و خط فرستنده را بررسی کنید.',
                401 => 'کلید API نامعتبر یا منقضی است.',
                403 => 'مجوز کلید یا دسترسی سرویس کافی نیست.',
                404 => 'پیامک یا مسیر API پیدا نشد.',
                409 => 'سرویس آماده نیست یا شناسهٔ درخواست تعارض دارد.',
                422 => 'شماره، متن یا خط فرستنده معتبر نیست.',
                429 => 'سقف درخواست API پر شده است؛ درخواست خودکار تکرار نمی‌شود.',
            );
            return new \WP_Error( 'ava_http_' . $code, $messages[ $code ] ?? 'پاسخ آوا قابل تأیید نیست؛ گزارش پنل را بررسی کنید.', array(
                'status' => $code,
                'uncertain' => $post && ( $code < 200 || $code >= 500 || 408 === $code || ( $code >= 200 && $code < 400 ) ),
            ) );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $statuses = array( 'pending', 'sending', 'unknown', 'accepted', 'sent', 'delivered', 'undelivered', 'rejected', 'blocked', 'cancelled', 'simulated', 'expired' );
        if ( ! is_array( $body ) || ! is_string( $body['id'] ?? null ) ||
            ! preg_match( '/^[A-Za-z0-9_-]{1,100}$/D', $body['id'] ) || ! in_array( $body['status'] ?? null, $statuses, true ) ) {
            return new \WP_Error( 'ava_response', 'پاسخ آوا ساختار قابل تأیید ندارد؛ تکرار خودکار انجام نمی‌شود.', array( 'uncertain' => $post ) );
        }
        $result = array( 'id' => $body['id'], 'status' => $body['status'] );
        if ( isset( $body['requires_review'] ) && is_bool( $body['requires_review'] ) ) {
            $result['requires_review'] = $body['requires_review'];
        }
        foreach ( array( 'review_status', 'campaign_status' ) as $field ) {
            if ( isset( $body[ $field ] ) && is_string( $body[ $field ] ) && preg_match( '/^[a-z_]{1,40}$/D', $body[ $field ] ) ) {
                $result[ $field ] = $body[ $field ];
            }
        }
        return $result;
    }
}
