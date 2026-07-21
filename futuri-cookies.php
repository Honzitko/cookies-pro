<?php
/**
 * Plugin Name:       futuri Cookies
 * Plugin URI:        https://futuri.cz
 * Description:        Plně samostatná, GDPR-friendly cookie lišta pro futuri.cz. Granulární souhlasy, blokování skriptů před souhlasem, Google Consent Mode v2, záznamy souhlasů a kompletní vizuální přizpůsobení — vše zdarma a pod vaší kontrolou.
 * Version:           1.0.0
 * Author:            futuri
 * Text Domain:       futuri-cookies
 * License:           GPL-2.0-or-later
 *
 * Pozn.: Tento plugin nenačítá žádné externí zdroje (žádné Google Fonts z CDN,
 * žádná externí CSS/JS), takže sám o sobě neodesílá data návštěvníka nikam ven.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUTURI_COOKIES_VERSION', '1.0.0' );
define( 'FUTURI_COOKIES_PATH', plugin_dir_path( __FILE__ ) );
define( 'FUTURI_COOKIES_URL', plugin_dir_url( __FILE__ ) );
define( 'FUTURI_COOKIES_OPT', 'futuri_cookies_settings' );
define( 'FUTURI_COOKIES_COOKIE', 'futuri_cookie_consent' );

require_once FUTURI_COOKIES_PATH . 'includes/settings.php';

/**
 * Výchozí nastavení + brandové defaulty a české texty.
 */
function futuri_cookies_default_settings() {
	return array(
		// --- Chování ---
		'enabled'         => 1,
		'layout'          => 'box',            // box | bar
		'position'        => 'bottom-left',    // bottom-left | bottom-right | bottom-center
		'expiry'          => 365,              // platnost souhlasu ve dnech
		'privacy_url'     => '',
		'log_consents'    => 1,
		'consent_mode'    => 1,                // Google Consent Mode v2
		'float_button'    => 1,
		'float_label'     => 'Cookies',

		// --- Texty lišty ---
		'title'           => 'Cookies u futuri',
		'message'         => 'Používáme cookies, abychom vám mohli ukázat ty správné informace o kroužcích a lépe porozumět tomu, jak web funguje. Nezbytné cookies jsou vždy aktivní, u ostatních rozhodujete vy.',
		'btn_accept'      => 'Přijmout vše',
		'btn_reject'      => 'Odmítnout vše',
		'btn_settings'    => 'Nastavit',
		'btn_save'        => 'Uložit volbu',
		'privacy_label'   => 'Zásady ochrany osobních údajů',

		// --- Panel nastavení (2. vrstva) ---
		'prefs_title'     => 'Nastavení cookies',
		'prefs_intro'     => 'Vyberte si, které cookies nám dovolíte používat. Své rozhodnutí můžete kdykoliv později změnit v patičce webu.',

		// --- Kategorie ---
		'cat_necessary_name'  => 'Nezbytné',
		'cat_necessary_desc'  => 'Zajišťují základní fungování webu a jeho zabezpečení. Bez nich by web nefungoval správně.',
		'cat_functional_name' => 'Preferenční',
		'cat_functional_desc' => 'Pamatují si vaše nastavení a preference, například naposledy prohlíženou sekci (edu / action).',
		'cat_analytics_name'  => 'Analytické',
		'cat_analytics_desc'  => 'Pomáhají nám pochopit, jak návštěvníci web používají, abychom ho mohli postupně vylepšovat.',
		'cat_marketing_name'  => 'Marketingové',
		'cat_marketing_desc'  => 'Používají se k zobrazování relevantní reklamy a měření její účinnosti na dalších webech a sociálních sítích.',

		// --- Barvy (futuri brand) ---
		'color_bg'            => '#F7F5F0',
		'color_text'          => '#2A2A2A',
		'color_heading'       => '#1D6B4A',
		'color_primary'       => '#1D6B4A', // tlačítka Přijmout / Odmítnout / Uložit (rovnocenná)
		'color_primary_text'  => '#FFFFFF',
		'color_accent'        => '#C4450A', // odkazy + zapnuté přepínače
		'color_border'        => '#E0DCD3',
		'radius'              => 16,

		// --- Skripty / služby ---
		// pole položek: array( 'name' => '', 'category' => 'analytics', 'position' => 'head', 'code' => '' )
		'scripts'         => array(),
	);
}

/**
 * Vrátí nastavení (sloučené s defaulty, aby chybějící klíče nerozbily plugin).
 */
function futuri_cookies_get( $key = null ) {
	$opt = wp_parse_args( get_option( FUTURI_COOKIES_OPT, array() ), futuri_cookies_default_settings() );
	if ( null === $key ) {
		return $opt;
	}
	return isset( $opt[ $key ] ) ? $opt[ $key ] : null;
}

/* ------------------------------------------------------------------------- *
 *  Aktivace: vytvoření tabulky pro záznamy souhlasů + výchozí nastavení
 * ------------------------------------------------------------------------- */
register_activation_hook( __FILE__, 'futuri_cookies_activate' );
function futuri_cookies_activate() {
	futuri_cookies_create_log_table();
	if ( false === get_option( FUTURI_COOKIES_OPT ) ) {
		add_option( FUTURI_COOKIES_OPT, futuri_cookies_default_settings() );
	}
}

function futuri_cookies_create_log_table() {
	global $wpdb;
	$table   = $wpdb->prefix . 'futuri_consent_log';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at DATETIME NOT NULL,
		ip VARCHAR(64) NOT NULL DEFAULT '',
		url VARCHAR(255) NOT NULL DEFAULT '',
		consent TEXT NOT NULL,
		user_agent VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY created_at (created_at)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/* ------------------------------------------------------------------------- *
 *  Anonymizace IP (poslední oktet u IPv4 / posledních 80 bitů u IPv6)
 * ------------------------------------------------------------------------- */
function futuri_cookies_anonymize_ip( $ip ) {
	if ( false !== strpos( $ip, ':' ) ) {
		$parts = explode( ':', $ip );
		return implode( ':', array_slice( $parts, 0, 4 ) ) . '::';
	}
	$parts = explode( '.', $ip );
	if ( 4 === count( $parts ) ) {
		$parts[3] = '0';
		return implode( '.', $parts );
	}
	return '0.0.0.0';
}

/* ------------------------------------------------------------------------- *
 *  Blokování skriptů: transformace <script> na text/plain + data-kategorie
 * ------------------------------------------------------------------------- */
function futuri_cookies_block_html( $html, $category ) {
	$html = trim( $html );
	if ( '' === $html ) {
		return '';
	}

	// Pokud vložený kód neobsahuje <script>, jde o čistý JS -> zabalíme ho.
	if ( false === stripos( $html, '<script' ) ) {
		return '<script type="text/plain" data-cookiecategory="' . esc_attr( $category ) . '">' . $html . '</script>' . "\n";
	}

	// Jinak přepíšeme každý <script ...> tag: odebereme type, src -> data-src, doplníme kategorii.
	$html = preg_replace_callback(
		'/<script\b([^>]*)>/i',
		function ( $m ) use ( $category ) {
			$attrs = $m[1];
			$attrs = preg_replace( '/\stype\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $attrs );
			$attrs = preg_replace( '/\ssrc\s*=/i', ' data-src=', $attrs );
			return '<script type="text/plain" data-cookiecategory="' . esc_attr( $category ) . '"' . $attrs . '>';
		},
		$html
	);

	return $html . "\n";
}

/**
 * Vypíše spravované skripty pro danou pozici (head/body) — zablokované.
 */
function futuri_cookies_output_scripts( $position ) {
	$scripts = futuri_cookies_get( 'scripts' );
	if ( empty( $scripts ) || ! is_array( $scripts ) ) {
		return;
	}
	foreach ( $scripts as $s ) {
		$pos = isset( $s['position'] ) ? $s['position'] : 'head';
		if ( $pos !== $position ) {
			continue;
		}
		$cat  = isset( $s['category'] ) ? $s['category'] : 'analytics';
		$code = isset( $s['code'] ) ? $s['code'] : '';
		echo "\n<!-- futuri Cookies: " . esc_html( isset( $s['name'] ) ? $s['name'] : '' ) . " ({$cat}) -->\n";
		echo futuri_cookies_block_html( $code, $cat ); // phpcs:ignore WordPress.Security.EscapeOutput -- skript záměrně vypisujeme jako kód
	}
}

/* ------------------------------------------------------------------------- *
 *  <head>: Google Consent Mode v2 (default denied) + okamžité obnovení
 *          uloženého souhlasu + zablokované skripty pro head
 * ------------------------------------------------------------------------- */
add_action( 'wp_head', 'futuri_cookies_head', 1 );
function futuri_cookies_head() {
	if ( ! futuri_cookies_get( 'enabled' ) ) {
		return;
	}

	if ( futuri_cookies_get( 'consent_mode' ) ) {
		?>
<script data-futuri="consent-mode">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{
  'ad_storage':'denied','ad_user_data':'denied','ad_personalization':'denied',
  'analytics_storage':'denied','functionality_storage':'denied',
  'personalization_storage':'denied','security_storage':'granted','wait_for_update':500
});
(function(){try{
  var m=document.cookie.match(/(?:^|;\s*)<?php echo esc_js( FUTURI_COOKIES_COOKIE ); ?>=([^;]+)/);
  if(!m)return;
  var c=JSON.parse(decodeURIComponent(m[1]));
  var expectedVersion=<?php echo wp_json_encode( FUTURI_COOKIES_VERSION ); ?>;
  // Stejně jako banner.js ignorujeme souhlas uložený starší verzí pluginu.
  if(!c||typeof c!=='object'||Array.isArray(c)||c.v!==expectedVersion)return;
  gtag('consent','update',{
    'analytics_storage':c.analytics?'granted':'denied',
    'ad_storage':c.marketing?'granted':'denied',
    'ad_user_data':c.marketing?'granted':'denied',
    'ad_personalization':c.marketing?'granted':'denied',
    'functionality_storage':c.functional?'granted':'denied',
    'personalization_storage':c.functional?'granted':'denied'
  });
}catch(e){}})();
</script>
		<?php
	}

	futuri_cookies_output_scripts( 'head' );
}

/* ------------------------------------------------------------------------- *
 *  Frontend assety (CSS/JS) + lokalizace nastavení
 * ------------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'futuri_cookies_assets' );
function futuri_cookies_assets() {
	if ( ! futuri_cookies_get( 'enabled' ) ) {
		return;
	}

	wp_enqueue_style( 'futuri-cookies', FUTURI_COOKIES_URL . 'assets/banner.css', array(), FUTURI_COOKIES_VERSION );

	// Dynamické CSS proměnné z nastavení barev.
	$css = ':root{'
		. '--fc-bg:' . futuri_cookies_get( 'color_bg' ) . ';'
		. '--fc-text:' . futuri_cookies_get( 'color_text' ) . ';'
		. '--fc-heading:' . futuri_cookies_get( 'color_heading' ) . ';'
		. '--fc-primary:' . futuri_cookies_get( 'color_primary' ) . ';'
		. '--fc-primary-text:' . futuri_cookies_get( 'color_primary_text' ) . ';'
		. '--fc-accent:' . futuri_cookies_get( 'color_accent' ) . ';'
		. '--fc-border:' . futuri_cookies_get( 'color_border' ) . ';'
		. '--fc-radius:' . intval( futuri_cookies_get( 'radius' ) ) . 'px;'
		. '}';
	wp_add_inline_style( 'futuri-cookies', $css );

	wp_enqueue_script( 'futuri-cookies', FUTURI_COOKIES_URL . 'assets/banner.js', array(), FUTURI_COOKIES_VERSION, true );
	wp_localize_script(
		'futuri-cookies',
		'FUTURI_COOKIES',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'futuri_cookies_log' ),
			'cookieName'  => FUTURI_COOKIES_COOKIE,
			'expiry'      => intval( futuri_cookies_get( 'expiry' ) ),
			'version'     => FUTURI_COOKIES_VERSION,
			'consentMode' => (bool) futuri_cookies_get( 'consent_mode' ),
			'logConsents' => (bool) futuri_cookies_get( 'log_consents' ),
		)
	);
}

/* ------------------------------------------------------------------------- *
 *  <footer>: HTML lišty + panelu + plovoucí tlačítko + zablokované body skripty
 * ------------------------------------------------------------------------- */
add_action( 'wp_footer', 'futuri_cookies_footer', 100 );
function futuri_cookies_footer() {
	if ( ! futuri_cookies_get( 'enabled' ) ) {
		return;
	}

	futuri_cookies_output_scripts( 'body' );

	$o        = futuri_cookies_get();
	$privacy  = esc_url( $o['privacy_url'] );
	$layout   = esc_attr( $o['layout'] );
	$position = esc_attr( $o['position'] );

	// Sprout ikona (dvoulístek) — drobný brandový akcent v hlavičce lišty.
	$sprout = '<svg class="fc-sprout" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">'
		. '<path d="M12 22V11" stroke="var(--fc-heading)" stroke-width="2" stroke-linecap="round"/>'
		. '<path d="M12 12C12 8 9 5 4 5c0 4 3 7 8 7Z" fill="var(--fc-heading)"/>'
		. '<path d="M12 12c0-4 3-7 8-7 0 4-3 7-8 7Z" fill="var(--fc-accent)"/>'
		. '</svg>';
	?>
	<div id="fc-banner" class="fc-banner fc-layout-<?php echo $layout; ?> fc-pos-<?php echo $position; ?>" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Nastavení cookies', 'futuri-cookies' ); ?>" hidden>
		<div class="fc-inner">
			<div class="fc-head"><?php echo $sprout; // phpcs:ignore WordPress.Security.EscapeOutput ?><h2 class="fc-title"><?php echo esc_html( $o['title'] ); ?></h2></div>
			<p class="fc-message"><?php echo esc_html( $o['message'] ); ?>
				<?php if ( $privacy ) : ?>
					<a class="fc-link" href="<?php echo $privacy; ?>"><?php echo esc_html( $o['privacy_label'] ); ?></a>
				<?php endif; ?>
			</p>
			<div class="fc-actions">
				<button type="button" class="fc-btn fc-btn-ghost" data-fc="settings"><?php echo esc_html( $o['btn_settings'] ); ?></button>
				<button type="button" class="fc-btn fc-btn-solid" data-fc="reject"><?php echo esc_html( $o['btn_reject'] ); ?></button>
				<button type="button" class="fc-btn fc-btn-solid" data-fc="accept"><?php echo esc_html( $o['btn_accept'] ); ?></button>
			</div>
		</div>
	</div>

	<div id="fc-prefs" class="fc-prefs" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $o['prefs_title'] ); ?>" hidden>
		<div class="fc-prefs-backdrop" data-fc="close-prefs"></div>
		<div class="fc-prefs-panel">
			<button type="button" class="fc-prefs-close" data-fc="close-prefs" aria-label="Zavřít">&times;</button>
			<h2 class="fc-prefs-title"><?php echo esc_html( $o['prefs_title'] ); ?></h2>
			<p class="fc-prefs-intro"><?php echo esc_html( $o['prefs_intro'] ); ?></p>

			<div class="fc-cat">
				<div class="fc-cat-head">
					<span class="fc-cat-name"><?php echo esc_html( $o['cat_necessary_name'] ); ?></span>
					<span class="fc-cat-always"><?php esc_html_e( 'Vždy aktivní', 'futuri-cookies' ); ?></span>
				</div>
				<p class="fc-cat-desc"><?php echo esc_html( $o['cat_necessary_desc'] ); ?></p>
			</div>

			<?php
			$cats = array(
				'functional' => array( $o['cat_functional_name'], $o['cat_functional_desc'] ),
				'analytics'  => array( $o['cat_analytics_name'], $o['cat_analytics_desc'] ),
				'marketing'  => array( $o['cat_marketing_name'], $o['cat_marketing_desc'] ),
			);
			foreach ( $cats as $key => $c ) :
				?>
				<div class="fc-cat">
					<div class="fc-cat-head">
						<span class="fc-cat-name"><?php echo esc_html( $c[0] ); ?></span>
						<label class="fc-switch">
							<input type="checkbox" data-cat="<?php echo esc_attr( $key ); ?>">
							<span class="fc-slider"></span>
						</label>
					</div>
					<p class="fc-cat-desc"><?php echo esc_html( $c[1] ); ?></p>
				</div>
			<?php endforeach; ?>

			<div class="fc-prefs-actions">
				<button type="button" class="fc-btn fc-btn-ghost" data-fc="reject"><?php echo esc_html( $o['btn_reject'] ); ?></button>
				<button type="button" class="fc-btn fc-btn-solid" data-fc="save"><?php echo esc_html( $o['btn_save'] ); ?></button>
				<button type="button" class="fc-btn fc-btn-solid" data-fc="accept"><?php echo esc_html( $o['btn_accept'] ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( $o['float_button'] ) : ?>
		<button type="button" id="fc-float" class="fc-float fc-pos-<?php echo $position; ?>" aria-label="<?php echo esc_attr( $o['prefs_title'] ); ?>" hidden>
			<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="10" r="1.3" fill="currentColor"/><circle cx="14" cy="9" r="1.1" fill="currentColor"/><circle cx="15" cy="14" r="1.3" fill="currentColor"/><circle cx="10" cy="15" r="1.1" fill="currentColor"/></svg>
			<span><?php echo esc_html( $o['float_label'] ); ?></span>
		</button>
	<?php endif; ?>
	<?php
}

/* ------------------------------------------------------------------------- *
 *  Shortcode pro odkaz "Nastavení cookies" do patičky/menu
 *  Použití: [futuri_cookie_settings text="Nastavení cookies"]
 * ------------------------------------------------------------------------- */
add_shortcode( 'futuri_cookie_settings', 'futuri_cookies_shortcode' );
function futuri_cookies_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'text' => futuri_cookies_get( 'prefs_title' ) ), $atts );
	return '<a href="#" class="fc-open-settings" onclick="if(window.futuriCookies){window.futuriCookies.open();}return false;">' . esc_html( $atts['text'] ) . '</a>';
}

/* ------------------------------------------------------------------------- *
 *  AJAX: záznam souhlasu (přihlášení i nepřihlášení uživatelé)
 * ------------------------------------------------------------------------- */
add_action( 'wp_ajax_futuri_log_consent', 'futuri_cookies_log_consent' );
add_action( 'wp_ajax_nopriv_futuri_log_consent', 'futuri_cookies_log_consent' );
function futuri_cookies_log_consent() {
	check_ajax_referer( 'futuri_cookies_log', 'nonce' );

	if ( ! futuri_cookies_get( 'log_consents' ) ) {
		wp_send_json_success();
	}

	global $wpdb;
	$table = $wpdb->prefix . 'futuri_consent_log';

	$consent = isset( $_POST['consent'] ) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '';
	$url     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? futuri_cookies_anonymize_ip( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
	$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';

	$wpdb->insert(
		$table,
		array(
			'created_at' => current_time( 'mysql' ),
			'ip'         => $ip,
			'url'        => substr( $url, 0, 255 ),
			'consent'    => $consent,
			'user_agent' => $ua,
		),
		array( '%s', '%s', '%s', '%s', '%s' )
	);

	wp_send_json_success();
}
