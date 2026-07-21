/* futuri Cookies — frontend logika (bez závislostí) */
(function () {
	'use strict';

	var CFG = window.FUTURI_COOKIES || {};
	var COOKIE = CFG.cookieName || 'futuri_cookie_consent';
	var CATS = ['functional', 'analytics', 'marketing'];

	var banner = document.getElementById('fc-banner');
	var prefs = document.getElementById('fc-prefs');
	var floatBtn = document.getElementById('fc-float');
	var prefsOpener = null;
	var previousDocumentOverflow = '';
	var inertBackground = [];
	var focusableSelector = 'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, object, embed, [contenteditable], [tabindex]:not([tabindex="-1"])';

	/* ---------------- Cookie helpers ---------------- */
	function readConsent() {
		var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE + '=([^;]+)'));
		if (!m) return null;
		try { return JSON.parse(decodeURIComponent(m[1])); } catch (e) { return null; }
	}

	function writeConsent(state) {
		state.necessary = true;
		state.ts = Date.now();
		state.v = CFG.version || '1';
		var days = CFG.expiry || 365;
		var d = new Date();
		d.setTime(d.getTime() + days * 864e5);
		var secure = location.protocol === 'https:' ? ';Secure' : '';
		document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(state)) +
			';expires=' + d.toUTCString() + ';path=/;SameSite=Lax' + secure;
	}

	function isValidConsent(state) {
		return !!state && typeof state === 'object' && !Array.isArray(state) &&
			state.v === (CFG.version || '1') && state.necessary === true &&
			typeof state.functional === 'boolean' && typeof state.analytics === 'boolean' &&
			typeof state.marketing === 'boolean';
	}

	/* ---------------- Google Consent Mode v2 ---------------- */
	function updateConsentMode(state) {
		if (!CFG.consentMode || typeof window.gtag !== 'function') return;
		window.gtag('consent', 'update', {
			analytics_storage: state.analytics ? 'granted' : 'denied',
			ad_storage: state.marketing ? 'granted' : 'denied',
			ad_user_data: state.marketing ? 'granted' : 'denied',
			ad_personalization: state.marketing ? 'granted' : 'denied',
			functionality_storage: state.functional ? 'granted' : 'denied',
			personalization_storage: state.functional ? 'granted' : 'denied'
		});
	}

	/* ---------------- Aktivace zablokovaných skriptů ---------------- */
	function activateScripts(state) {
		var granted = [];
		CATS.forEach(function (c) { if (state[c]) granted.push(c); });

		var blocked = document.querySelectorAll('script[type="text/plain"][data-cookiecategory]');
		blocked.forEach(function (el) {
			if (el.dataset.fcActivated) return;
			var cat = el.getAttribute('data-cookiecategory');
			if (granted.indexOf(cat) === -1) return;

			var s = document.createElement('script');
			for (var i = 0; i < el.attributes.length; i++) {
				var a = el.attributes[i];
				if (a.name === 'type' || a.name === 'data-cookiecategory') continue;
				if (a.name === 'data-src') { s.src = a.value; continue; }
				s.setAttribute(a.name, a.value);
			}
			if (el.textContent && el.textContent.trim()) s.textContent = el.textContent;
			el.parentNode.insertBefore(s, el.nextSibling);
			el.dataset.fcActivated = '1';
		});
	}

	/* ---------------- Aplikace stavu + log ---------------- */
	function applyConsent(state, doLog) {
		updateConsentMode(state);
		activateScripts(state);
		if (doLog && CFG.logConsents && CFG.ajaxUrl) {
			logConsent(state);
		}
	}

	function logConsent(state) {
		try {
			var body = new URLSearchParams();
			body.append('action', 'futuri_log_consent');
			body.append('nonce', CFG.nonce || '');
			body.append('consent', JSON.stringify(state));
			body.append('url', location.href);
			fetch(CFG.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
				credentials: 'same-origin',
				keepalive: true
			});
		} catch (e) { /* logování je best-effort */ }
	}

	/* ---------------- UI ---------------- */
	function showBanner() { if (banner) banner.hidden = false; if (floatBtn) floatBtn.hidden = true; }
	function hideBanner() { if (banner) banner.hidden = true; if (floatBtn) floatBtn.hidden = false; }

	function getPrefsFocusableElements() {
		if (!prefs) return [];
		return Array.prototype.filter.call(prefs.querySelectorAll(focusableSelector), function (el) {
			return !el.hidden && el.getAttribute('aria-hidden') !== 'true';
		});
	}

	function setBackgroundInert(isInert) {
		if (!prefs || !('inert' in HTMLElement.prototype)) return;

		if (isInert) {
			inertBackground = [];
			Array.prototype.forEach.call(document.body.children, function (el) {
				if (el === prefs || el.contains(prefs)) return;
				inertBackground.push({ element: el, wasInert: el.inert });
				el.inert = true;
			});
			return;
		}

		inertBackground.forEach(function (item) {
			item.element.inert = item.wasInert;
		});
		inertBackground = [];
	}

	function openPrefs() {
		if (!prefs) return;
		if (!prefs.hidden) return;
		prefsOpener = document.activeElement;
		var saved = readConsent() || {};
		CATS.forEach(function (c) {
			var input = prefs.querySelector('input[data-cat="' + c + '"]');
			if (input) input.checked = !!saved[c];
		});
		prefs.hidden = false;
		setBackgroundInert(true);
		previousDocumentOverflow = document.documentElement.style.overflow;
		document.documentElement.style.overflow = 'hidden';
		var firstToggle = prefs.querySelector('input[data-cat]');
		if (firstToggle) firstToggle.focus();
		else prefs.focus();
	}

	function closePrefs() {
		if (!prefs || prefs.hidden) return;
		prefs.hidden = true;
		setBackgroundInert(false);
		document.documentElement.style.overflow = previousDocumentOverflow;
		previousDocumentOverflow = '';
		if (prefsOpener && prefsOpener.isConnected && typeof prefsOpener.focus === 'function') {
			prefsOpener.focus({ preventScroll: true });
		}
		prefsOpener = null;
	}

	function decide(type) {
		var state = { necessary: true, functional: false, analytics: false, marketing: false };

		if (type === 'accept') {
			CATS.forEach(function (c) { state[c] = true; });
		} else if (type === 'reject') {
			CATS.forEach(function (c) { state[c] = false; });
		} else if (type === 'save') {
			CATS.forEach(function (c) {
				var input = prefs.querySelector('input[data-cat="' + c + '"]');
				state[c] = input ? input.checked : false;
			});
		}

		writeConsent(state);
		applyConsent(state, true);
		closePrefs();
		hideBanner();
	}

	/* ---------------- Události ---------------- */
	function bind(root) {
		if (!root) return;
		root.querySelectorAll('[data-fc]').forEach(function (el) {
			el.addEventListener('click', function () {
				var action = el.getAttribute('data-fc');
				if (action === 'settings') { openPrefs(); }
				else if (action === 'close-prefs') { closePrefs(); }
				else { decide(action); }
			});
		});
	}

	document.addEventListener('keydown', function (e) {
		if (!prefs || prefs.hidden) return;

		if (e.key === 'Escape') {
			closePrefs();
			return;
		}

		if (e.key !== 'Tab') return;

		var focusable = getPrefsFocusableElements();
		if (!focusable.length) {
			e.preventDefault();
			prefs.focus();
			return;
		}

		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		var active = document.activeElement;
		if (active === first && e.shiftKey) {
			e.preventDefault();
			last.focus();
		} else if (active === last && !e.shiftKey) {
			e.preventDefault();
			first.focus();
		} else if (!prefs.contains(active)) {
			e.preventDefault();
			(e.shiftKey ? last : first).focus();
		}
	});

	/* ---------------- Veřejné API ---------------- */
	window.futuriCookies = {
		open: openPrefs,
		accept: function () { decide('accept'); },
		reject: function () { decide('reject'); },
		reset: function () {
			document.cookie = COOKIE + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
			location.reload();
		},
		getConsent: readConsent
	};

	/* ---------------- Init ---------------- */
	function init() {
		bind(banner);
		bind(prefs);
		if (floatBtn) {
			floatBtn.addEventListener('click', openPrefs);
		}

		var saved = readConsent();
		var validVersion = isValidConsent(saved);

		if (validVersion) {
			// Vrátivší se návštěvník — aplikujeme uložený souhlas, lištu neukazujeme.
			applyConsent(saved, false);
			hideBanner();
		} else {
			// Nový návštěvník (nebo změna verze kategorií) — ukážeme lištu.
			showBanner();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
