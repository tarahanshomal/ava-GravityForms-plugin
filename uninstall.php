<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function ava_gf_uninstall_site() {
    global $wpdb;
    delete_option( 'ava_gf_settings' );
    // Remove only this plugin's delivery guards. Gravity Forms owns its entries/notes.
    do {
        $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500", $wpdb->esc_like( 'ava_gf_delivery_' ) . '%' ) );
        foreach ( $names as $name ) {
            delete_option( $name );
        }
    } while ( count( $names ) === 500 );
}

if ( is_multisite() ) {
    foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $ava_gf_site_id ) {
        switch_to_blog( $ava_gf_site_id );
        ava_gf_uninstall_site();
        restore_current_blog();
    }
} else {
    ava_gf_uninstall_site();
}
