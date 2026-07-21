<?php
/**
 * Minimal WordPress compatibility layer for tests of pure plugin functions.
 *
 * The production plugin still runs inside WordPress. These stubs deliberately
 * cover only functions needed while loading the plugin and testing its
 * side-effect-free helpers.
 */

define( 'ABSPATH', __DIR__ . '/' );

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return '';
}

function add_action() {}
function add_shortcode() {}
function register_activation_hook() {}

function esc_attr( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function get_transient( $key ) {
	return isset( $GLOBALS['futuri_cookies_test_transients'][ $key ] ) ? $GLOBALS['futuri_cookies_test_transients'][ $key ] : false;
}

function set_transient( $key, $value ) {
	$GLOBALS['futuri_cookies_test_transients'][ $key ] = $value;
	return true;
}

require_once dirname( __DIR__ ) . '/futuri-cookies.php';
