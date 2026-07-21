#!/usr/bin/env node
'use strict';

/*
 * Spouští přesně validaci z inline Consent Mode skriptu ve futuri-cookies.php
 * v minimálním browser-like prostředí. Bez externích závislostí:
 * node tests/consent-mode-cookie-validation.test.js
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const plugin = fs.readFileSync('futuri-cookies.php', 'utf8');
const match = plugin.match(/(\(function\(\)\{try\{[\s\S]*?\}\)\(\);)/);
assert.ok(match, 'Inline Consent Mode cookie-restoration script must exist.');

const inlineScript = match[1]
	.replace(/<\?php echo esc_js\( FUTURI_COOKIES_COOKIE \); \?>/g, 'futuri_cookie_consent')
	.replace(/<\?php echo wp_json_encode\( FUTURI_COOKIES_VERSION \); \?>/g, '"1.0.0"');

function updatesFor(cookie) {
	const updates = [];
	vm.runInNewContext(inlineScript, {
		document: { cookie: cookie || '' },
		decodeURIComponent,
		JSON,
		Array,
		gtag: (...args) => updates.push(args)
	});
	return updates;
}

function consentCookie(value) {
	return 'futuri_cookie_consent=' + encodeURIComponent(JSON.stringify(value));
}

const valid = updatesFor(consentCookie({ v: '1.0.0', analytics: true, marketing: true, functional: true }));
assert.equal(valid.length, 1, 'A current valid cookie must restore Consent Mode.');
assert.equal(valid[0][2].analytics_storage, 'granted');
assert.equal(valid[0][2].ad_storage, 'granted');
assert.equal(valid[0][2].functionality_storage, 'granted');

assert.equal(updatesFor(consentCookie({ analytics: true, marketing: true, functional: true })).length, 0,
	'A cookie without a version must not restore old consent.');
assert.equal(updatesFor(consentCookie({ v: '0.9.0', analytics: true, marketing: true, functional: true })).length, 0,
	'A cookie from an older version must not restore old consent.');
assert.equal(updatesFor('futuri_cookie_consent=%7Bnot-json').length, 0,
	'Invalid JSON must be ignored without throwing.');

console.log('Consent Mode cookie-version validation scenarios passed.');
