<?php

use PHPUnit\Framework\TestCase;

class PluginFunctionsTest extends TestCase {
	/** @dataProvider anonymizedIpProvider */
	public function test_anonymizes_ip_addresses( $ip, $expected ) {
		$this->assertSame( $expected, futuri_cookies_anonymize_ip( $ip ) );
	}

	public function anonymizedIpProvider() {
		return array(
			'IPv4 final octet is masked' => array( '192.0.2.123', '192.0.2.0' ),
			'full IPv6 host part is masked' => array( '2001:0db8:85a3:0000:0000:8a2e:0370:7334', '2001:db8:85a3::' ),
			'compressed IPv6 host part is masked' => array( '2001:db8::1', '2001:db8::' ),
			'invalid address uses a safe fallback' => array( 'not-an-ip-address', '0.0.0.0' ),
			'non-string input uses a safe fallback' => array( null, '0.0.0.0' ),
		);
	}

	public function test_normalizes_valid_consent_in_a_stable_order() {
		$this->assertSame(
			'{"necessary":true,"functional":false,"analytics":true,"marketing":false}',
			futuri_cookies_normalize_consent( '{"marketing":false,"analytics":true,"functional":false,"necessary":true}' )
		);
	}

	/** @dataProvider invalidConsentProvider */
	public function test_rejects_invalid_consent( $consent ) {
		$this->assertFalse( futuri_cookies_normalize_consent( $consent ) );
	}

	public function invalidConsentProvider() {
		return array(
			'missing category' => array( '{"necessary":true,"functional":false,"analytics":true}' ),
			'non-boolean category' => array( '{"necessary":true,"functional":false,"analytics":true,"marketing":"false"}' ),
			'unknown category' => array( '{"necessary":true,"functional":false,"analytics":true,"marketing":false,"extra":true}' ),
			'invalid JSON' => array( '{not-json}' ),
			'non-string value' => array( array() ),
		);
	}

	public function test_blocks_an_external_script_and_preserves_other_attributes() {
		$html = '<script src="https://example.test/tracker.js" async data-site="123">window.tracker=true;</script>';

		$this->assertSame(
			'<script type="text/plain" data-cookiecategory="analytics" data-src="https://example.test/tracker.js" async data-site="123">window.tracker=true;</script>' . "\n",
			futuri_cookies_block_html( $html, 'analytics' )
		);
	}

	public function test_replaces_an_existing_script_type_and_blocks_every_script_tag() {
		$html = "<script type='module' src='one.js'></script><script TYPE=\"application/javascript\">two()</script>";
		$output = futuri_cookies_block_html( $html, 'marketing' );

		$this->assertSame( 2, substr_count( $output, 'type="text/plain" data-cookiecategory="marketing"' ) );
		$this->assertStringContainsString( "data-src='one.js'", $output );
		$this->assertStringNotContainsString( "type='module'", $output );
		$this->assertStringNotContainsString( 'TYPE="application/javascript"', $output );
	}

	public function test_wraps_plain_javascript_in_a_blocked_script_tag() {
		$this->assertSame(
			'<script type="text/plain" data-cookiecategory="functional">console.log("ready");</script>' . "\n",
			futuri_cookies_block_html( '  console.log("ready");  ', 'functional' )
		);
	}

	public function test_limits_consent_log_writes_per_anonymized_network() {
		$GLOBALS['futuri_cookies_test_transients'] = array();

		for ( $request = 0; $request < FUTURI_COOKIES_CONSENT_RATE_LIMIT; $request++ ) {
			$this->assertTrue( futuri_cookies_allow_consent_log( '192.0.2.0' ) );
		}

		$this->assertFalse( futuri_cookies_allow_consent_log( '192.0.2.0' ) );
	}
}
