<?php
/**
 * Simple regression tests for IP anonymization without requiring WordPress.
 *
 * Run with: php tests/anonymize-ip.test.php
 */

define( 'ABSPATH', __DIR__ . '/' );

set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return '';
}

function add_action() {}
function add_shortcode() {}
function register_activation_hook() {}

require_once dirname( __DIR__ ) . '/futuri-cookies.php';

function assert_same( $expected, $actual, $description ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$description}\nExpected: {$expected}\nActual:   {$actual}\n" );
		exit( 1 );
	}
}

assert_same( '192.0.2.0', futuri_cookies_anonymize_ip( '192.0.2.123' ), 'IPv4 masks its final octet' );
assert_same( '2001:db8:85a3::', futuri_cookies_anonymize_ip( '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ), 'full IPv6 masks its final 80 bits' );
assert_same( '2001:db8::', futuri_cookies_anonymize_ip( '2001:db8::1' ), 'compressed IPv6 masks its host part' );
assert_same( '::', futuri_cookies_anonymize_ip( '::1' ), 'IPv6 loopback masks its host part' );
assert_same( '0.0.0.0', futuri_cookies_anonymize_ip( 'not-an-ip-address' ), 'invalid input returns the fallback value' );

echo "IP anonymization tests passed.\n";
