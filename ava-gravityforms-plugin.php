<?php
/**
 * Plugin Name: Ava SMS for Gravity Forms
 * Plugin URI: https://github.com/tarahanshomal/ava-GravityForms-plugin
 * Description: اعلان پیامکی فرم‌ها از طریق پنل آوا، با گیرندهٔ دلخواه، برچسب‌های فرم و ارسال شرطی.
 * Version: 1.0.0
 * Author: طراحان شمال
 * Author URI: https://github.com/tarahanshomal
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ava-gravityforms-plugin
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;
define( 'AVA_GF_VERSION', '1.0.0' );
define( 'AVA_GF_FILE', __FILE__ );

foreach ( array( 'settings', 'phone', 'api', 'delivery', 'admin' ) as $ava_gf_class ) {
    require_once __DIR__ . '/includes/class-' . $ava_gf_class . '.php';
}
unset( $ava_gf_class );

add_action( 'gform_loaded', static function () {
    if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
        return;
    }
    \GFForms::include_feed_addon_framework();
    require_once __DIR__ . '/includes/class-addon.php';
    \GFAddOn::register( 'AvaGF\\AddOn' );
}, 5 );

\AvaGF\Admin::init();
add_action( 'gform_delete_entry', array( 'AvaGF\\Delivery', 'delete_entry' ) );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), static function ( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ava-gravityforms' ) ) . '">' . esc_html__( 'تنظیمات آوا', 'ava-gravityforms-plugin' ) . '</a>' );
    return $links;
} );
