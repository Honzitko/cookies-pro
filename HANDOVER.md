# Handover: futuri Cookies — follow-up audit and release checklist

**Purpose:** This document hands the consent-plugin audit to a future implementer/reviewer. Use it after the seven fixes listed below have been completed. Do not mark the work complete merely because the UI looks correct: run the functional, compatibility, privacy, and legal checks in this document.

**Important:** This is an engineering compliance checklist, not legal advice. Czech/ePrivacy/GDPR requirements and regulator guidance can change. Before production release, have the deployed configuration, cookie inventory, privacy policy, and consent-log retention reviewed by Czech privacy counsel.

## Baseline and known issues

The original static audit found these issues:

1. **Consent Mode version mismatch (critical):** the early PHP head snippet restored any decodable consent cookie without checking its version, while frontend JavaScript treated an old version as invalid. An old consent could therefore grant Google storage before the visitor reconfirmed.
2. **IPv6 anonymisation (critical):** string splitting did not safely handle compressed IPv6 addresses and did not mask the number of bits claimed by the comment.
3. **Consent-log integrity (high):** anonymous AJAX logging had no rate limit and accepted unvalidated consent text. Logs could be polluted and should not be treated as strong evidence.
4. **Privacy-policy link (medium):** admin copy promised the link in both banner and preferences modal, but the modal omitted it.
5. **Centred floating button (medium):** `bottom-center` positioned the button at the right side.
6. **Modal accessibility (medium):** no focus trap or focus restoration.
7. **Documentation accuracy (high):** claims about blocking trackers were broader than the implementation: only managed or manually marked scripts are blocked.

## Required fixes and acceptance criteria

### 1. Consent Mode must reject obsolete consent

- Update the inline consent-mode code in `futuri-cookies.php` so it accepts a cookie only when it has the current `FUTURI_COOKIES_VERSION` and expected boolean categories.
- Missing, malformed, or old-version cookies must leave every optional Google storage value `denied`.
- Keep the browser-side version rule in `assets/banner.js` identical to the PHP rule.
- Verify that a new banner appears for an old cookie and no Google storage is granted until the visitor makes a new choice.

### 2. Anonymise IP addresses correctly

- Replace IPv6 string splitting with `inet_pton()`/`inet_ntop()` or equivalent binary-safe handling.
- Preserve only the intended network prefix, zero the host bits, and document the exact mask (for example `/24` for IPv4 and the selected IPv6 prefix).
- Handle compressed IPv6, full IPv6, IPv4, loopback, invalid input, and proxy-related deployment documentation.
- Do **not** trust `X-Forwarded-For` unless the site has an explicit trusted-proxy configuration.

### 3. Validate and rate-limit consent logging

- Retain nonce and capability protections already used for admin actions.
- For the public log endpoint, parse consent as JSON and normalise it to only the four known boolean keys.
- Reject invalid requests; do not store arbitrary strings as consent evidence.
- Add a server-side rate limit keyed from a privacy-preserving value (for example the already anonymised address plus a short time window) and return a JSON error on limit exhaustion.
- Check `$wpdb->insert()` failure and do not report a false success.
- Define and implement a retention/deletion policy for the log table; an export alone is not a retention policy.

### 4. Show the privacy-policy link in preferences

- Render the configured, escaped privacy-policy URL and label in the preferences modal as well as the banner.
- Render nothing when no URL is configured.
- Verify keyboard access and sensible focus order.

### 5. Centre the floating button

- Give `.fc-float.fc-pos-bottom-center` a true horizontal-centre rule.
- Confirm desktop and mobile rules do not overwrite the centring transform.

### 6. Make the preferences modal keyboard-accessible

- Store the element that opened the modal and restore focus to it on close.
- Trap Tab and Shift+Tab within the modal while it is open.
- Prevent page-background controls from being keyboard reachable while the modal is modal (prefer an `inert` strategy with a compatible fallback).
- Retain Escape, close-button, and backdrop-close behavior; make sure each restores scroll and focus.
- Check all focus indicators against custom theme CSS.

### 7. Correct documentation and admin copy

- State that the plugin automatically blocks scripts managed through its own script UI.
- State that scripts output by themes or other plugins must be explicitly integrated (`type="text/plain"`, `data-cookiecategory`, and `data-src` for external scripts) or blocked at their source.
- State that Google Consent Mode conveys Google consent state; it is not a universal network/script blocker.
- Ensure claims in `readme.txt`, WordPress admin copy, and public-facing policy language match the actual deployed configuration.

## Functional verification checklist

Run these tests in a disposable WordPress environment with `WP_DEBUG`, `WP_DEBUG_LOG`, and browser developer tools enabled.

### Core user journeys

- [ ] First visit without a cookie: banner is visible, modal is hidden, optional scripts are absent/inert, Consent Mode is `denied`.
- [ ] **Accept all:** cookie contains current version and all categories; managed functional/analytics/marketing scripts run once; Consent Mode grants matching storage values; log has one valid normalised record when logging is enabled.
- [ ] **Reject all:** cookie contains current version with all optional categories false; no optional managed script runs; Consent Mode remains denied.
- [ ] **Save granular choice:** test all eight combinations of the three optional categories; only matching managed scripts activate and Google mappings are correct.
- [ ] Return visit with a current cookie: no banner; selected scripts activate once; no duplicate consent log is created.
- [ ] Return visit with old, missing-version, malformed, URL-encoded malformed, and expired cookies: banner returns; optional scripts stay blocked; Consent Mode stays denied before reconfirmation.
- [ ] `futuriCookies.open()`, `.accept()`, `.reject()`, `.getConsent()`, and `.reset()` work from the browser console.
- [ ] The shortcode opens the modal and returns focus to its link after close.
- [ ] Plugin disabled: no frontend markup, consent-mode script, frontend CSS, or frontend JavaScript is emitted.

### Script blocking

- [ ] Test an inline script and an external `src` script inserted via the plugin UI for each category and both supported positions.
- [ ] Test a manual theme/plugin integration using `type="text/plain"`, `data-cookiecategory`, and `data-src`.
- [ ] Verify scripts do not execute before consent, execute only once after the matching consent, and do not execute for rejected categories.
- [ ] Test common attributes (`async`, `defer`, `nonce`, `integrity`, `crossorigin`, `referrerpolicy`) and module scripts if support is claimed. Do not silently convert a `type="module"` script into a classic script.
- [ ] Use the browser Network tab to confirm no non-essential vendor request begins before consent. Test GTM, GA4, Ads, Meta Pixel, Sklik, Hotjar, embedded videos/maps, and any site-specific integrations actually deployed.

### Admin and logs

- [ ] Administrators can save all fields and invalid layout/position/category/color values fall back safely.
- [ ] Non-administrators cannot save settings or export logs.
- [ ] Export is valid UTF-8 CSV and Excel-compatible if that remains a requirement.
- [ ] Consent logging off produces no database records.
- [ ] Valid logging produces normalised consent JSON and the expected anonymised address.
- [ ] Invalid consent JSON, malformed URL, a bad nonce, and a rate-limited request produce safe errors and do not create records.
- [ ] Test both full and compressed IPv6; manually check database results are syntactically valid and mask host bits as documented.

### Accessibility and visual checks

- [ ] Keyboard-only: complete accept, reject, granular save, open, and close flows.
- [ ] Tab/Shift+Tab stays in the open modal; Escape closes it; focus returns to the opener.
- [ ] Screen reader: dialog name, button labels, checkbox/switch labels, and link are announced properly.
- [ ] Test 320px-wide mobile viewport, desktop, zoom at 200%, and narrow landscape view.
- [ ] Test `bottom-left`, `bottom-right`, and `bottom-center` for banner and floating button.
- [ ] Test `prefers-reduced-motion: reduce`, high contrast/forced colors, and a theme with aggressive global button styles.

## Compatibility matrix

At minimum, manually test the production WordPress/PHP support policy plus:

| Layer | Required checks |
| --- | --- |
| WordPress | Current supported WordPress release and the plugin's declared minimum (currently WordPress 5.5+) |
| PHP | The host's oldest supported PHP version and current production PHP; test PHP 8.x warnings with `WP_DEBUG` |
| Browsers | Current Chrome/Edge, Firefox, Safari desktop, Safari iOS, Chrome Android |
| JavaScript | `fetch`, `URLSearchParams`, `NodeList.forEach`, `dataset`, modal focus logic; add fallbacks or raise/document browser baseline if needed |
| Themes/plugins | Default block theme, classic theme, caching/minification plugin, security/CSP plugin, GTM/GA integration, and any production page builder |
| Caching | Anonymous full-page cache, CDN cache, JS defer/delay optimization, and nonce behavior for cached pages |

Pay particular attention to public AJAX nonce behavior on fully cached pages and CSP behavior for the inline Consent Mode script. A strict CSP may require a nonce/hash strategy; do not disable CSP merely to make the plugin work.

## Czech legal and privacy review checklist

**This is a release-gate checklist, not a legal opinion. Confirm the current text of law and regulator guidance with Czech counsel immediately before release.**

### Primary legal sources to verify at release time

- [ ] **Act No. 127/2005 Coll., on Electronic Communications, section 89:** verify the current wording and exceptions for storing/accessing information in terminal equipment. Use the current collection of laws/e-Sbírka text, not a stale summary.
- [ ] **GDPR:** verify Articles 4(11), 5, 6, 7, 12–14, 25, 30, 32, and where applicable 35. In particular review consent conditions, transparency, data minimisation, retention, accountability, security, and DPIA triggers.
- [ ] **Czech Data Protection Authority (ÚOOÚ):** review current official cookie/tracking guidance and enforcement materials.
- [ ] **EDPB guidance:** review current guidance on consent and valid consent mechanics, especially freely given, specific, informed, unambiguous, withdrawable consent, and cookie-wall/dark-pattern considerations.
- [ ] Record the exact source URLs, document dates, reviewer name, and conclusions in the release record.

### Practical Czech/EU compliance checks

- [ ] Build a current **cookie and tracker inventory** from browser network/storage inspection. Include first-party and third-party cookies, localStorage, pixels, SDKs, embedded media/maps, fingerprinting, and server-side tracking.
- [ ] Classify each technology and document why it is strictly necessary or which optional consent category it requires. The plugin's four categories are UI labels, not a legal classification by themselves.
- [ ] Before consent, block every non-essential storage/access and vendor request identified in the inventory. This must include technologies injected by themes, GTM, other plugins, CDNs, embeds, and tag managers.
- [ ] Do not pre-tick optional categories. Rejecting must be as easy as accepting, and granular choices must be possible without disadvantage.
- [ ] Provide clear first-layer information and an easily accessible second layer covering purposes, categories, recipients/vendors, retention, links to privacy information, and how to withdraw/change consent.
- [ ] Ensure withdrawal is as easy as granting and works at all times through the floating button/shortcode or equivalent persistent link.
- [ ] Define whether Google Consent Mode's cookieless pings are used and obtain counsel's review of their configuration and legal basis. Do not describe Consent Mode as proof that all tracking is blocked.
- [ ] Review the consent-log fields (`IP`, URL, user agent, timestamp, consent) as personal-data processing. Set a documented purpose, retention period, access control, deletion process, and lawful-basis/accountability rationale.
- [ ] Do not treat client-submitted log records as conclusive legal proof without controls against spoofing, tampering, and automated submission.
- [ ] Review privacy notice content under GDPR Articles 12–14. It must describe the controller, purposes, legal bases, recipients/transfers, retention, rights, complaint route, and relevant contact information.
- [ ] Assess international transfers and vendor terms for every third-party service, especially Google, Meta, and session-replay/analytics vendors.
- [ ] Assess children-facing pages and any processing involving children separately; do not rely on the banner alone to meet child-data obligations.
- [ ] Have Czech counsel sign off on the deployed site configuration—not merely this plugin source code—because legal compliance depends on all scripts and processing on the site.

## Commands and evidence to collect

Run from the repository root before review:

```bash
php -l futuri-cookies.php
php -l includes/settings.php
node --check assets/banner.js
node --check assets/admin.js
git diff --check
git status --short --branch
```

Add and run a documented automated test command when the test suite is introduced. Save test output, browser screenshots/video for the consent journeys, a HAR/network export for pre-consent and post-consent states, the cookie inventory, and counsel's release decision with the release record.

## Completion decision

Mark the fixes complete only when every relevant acceptance criterion and checklist item is evidenced. Any failed pre-consent network request from a non-essential vendor, old-cookie Consent Mode grant, invalid IP masking result, public-log abuse path, or unresolved counsel finding is a release blocker.
