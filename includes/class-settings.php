<?php
namespace AvaGF;

defined( 'ABSPATH' ) || exit;

final class Settings {
    const OPTION = 'ava_gf_settings';

    public static function defaults() {
        return array( 'enabled' => 'no', 'api_url' => 'https://ava.tarpardaz.com', 'api_key' => '', 'sender' => '' );
    }

    public static function get() {
        $raw = get_option( self::OPTION, array() );
        $settings = array_merge( self::defaults(), is_array( $raw ) ? $raw : array() );
        if ( defined( 'AVA_GF_API_KEY' ) ) {
            $settings['api_key'] = (string) AVA_GF_API_KEY;
        }
        return $settings;
    }

    public static function valid_url( $url ) {
        if ( ! is_string( $url ) ) {
            return false;
        }
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) ||
            isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ||
            ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ||
            ( ! empty( $parts['path'] ) && '/' !== $parts['path'] ) ||
            ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/iD', $parts['host'] ) ) {
            return false;
        }
        return 'https://' . strtolower( $parts['host'] );
    }

    public static function valid_key( $key ) {
        return is_string( $key ) && preg_match( '/^ava_live_[A-Za-z0-9_-]{16,240}$/D', $key );
    }

    public static function sanitize( $input ) {
        $out = self::get();
        $input = is_array( $input ) ? $input : array();
        $out['enabled'] = isset( $input['enabled'] ) && 'yes' === $input['enabled'] ? 'yes' : 'no';
        $url = self::valid_url( $input['api_url'] ?? null );
        if ( $url ) {
            $out['api_url'] = $url;
        } else {
            self::error( 'url', 'نشانی آوا باید یک دامنهٔ HTTPS بدون مسیر باشد؛ نشانی قبلی حفظ شد.' );
        }
        if ( defined( 'AVA_GF_API_KEY' ) ) {
            $raw = get_option( self::OPTION, array() );
            $out['api_key'] = is_array( $raw ) ? ( $raw['api_key'] ?? '' ) : '';
        } elseif ( isset( $input['delete_api_key'] ) && 'yes' === $input['delete_api_key'] ) {
            $out['api_key'] = '';
        } elseif ( isset( $input['api_key'] ) && is_string( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
            $key = trim( $input['api_key'] );
            if ( self::valid_key( $key ) ) {
                $out['api_key'] = $key;
            } else {
                self::error( 'key', 'ساختار کلید API معتبر نیست؛ کلید قبلی حفظ شد.' );
            }
        }
        $sender = isset( $input['sender'] ) && is_string( $input['sender'] ) ? trim( $input['sender'] ) : '';
        if ( '' === $sender || preg_match( '/^[0-9]{3,20}$/D', $sender ) ) {
            $out['sender'] = $sender;
        } else {
            self::error( 'sender', 'خط فرستنده باید ۳ تا ۲۰ رقم باشد؛ مقدار قبلی حفظ شد.' );
        }
        return $out;
    }

    private static function error( $code, $message ) {
        add_settings_error( self::OPTION, 'ava_gf_' . $code, $message );
    }
}
