<?php
/**
 * futuri Cookies — administrace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------- Menu ---------------- */
add_action( 'admin_menu', 'futuri_cookies_menu' );
function futuri_cookies_menu() {
	add_menu_page(
		'futuri Cookies',
		'futuri Cookies',
		'manage_options',
		'futuri-cookies',
		'futuri_cookies_settings_page',
		'dashicons-shield-alt',
		81
	);
}

/* ---------------- Assety administrace ---------------- */
add_action( 'admin_enqueue_scripts', 'futuri_cookies_admin_assets' );
function futuri_cookies_admin_assets( $hook ) {
	if ( 'toplevel_page_futuri-cookies' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_style( 'futuri-cookies-admin', FUTURI_COOKIES_URL . 'assets/admin.css', array(), FUTURI_COOKIES_VERSION );
	wp_enqueue_script( 'futuri-cookies-admin', FUTURI_COOKIES_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), FUTURI_COOKIES_VERSION, true );
}

/* ---------------- Uložení (admin-post) ---------------- */
add_action( 'admin_post_futuri_cookies_save', 'futuri_cookies_save' );
function futuri_cookies_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nedostatečná oprávnění.' );
	}
	check_admin_referer( 'futuri_cookies_save', 'futuri_cookies_nonce' );

	$in  = wp_unslash( $_POST );
	$def = futuri_cookies_default_settings();
	$out = array();

	// Přepínače (checkboxy)
	foreach ( array( 'enabled', 'log_consents', 'consent_mode', 'float_button' ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}

	// Výběry
	$out['layout']   = ( isset( $in['layout'] ) && in_array( $in['layout'], array( 'box', 'bar' ), true ) ) ? $in['layout'] : 'box';
	$out['position'] = ( isset( $in['position'] ) && in_array( $in['position'], array( 'bottom-left', 'bottom-right', 'bottom-center' ), true ) ) ? $in['position'] : 'bottom-left';

	// Čísla
	$out['expiry'] = isset( $in['expiry'] ) ? max( 1, min( 3650, intval( $in['expiry'] ) ) ) : 365;
	$out['radius'] = isset( $in['radius'] ) ? max( 0, min( 40, intval( $in['radius'] ) ) ) : 16;

	// URL
	$out['privacy_url'] = isset( $in['privacy_url'] ) ? esc_url_raw( $in['privacy_url'] ) : '';

	// Textová pole
	$text_keys = array(
		'title', 'btn_accept', 'btn_reject', 'btn_settings', 'btn_save', 'privacy_label',
		'prefs_title', 'float_label',
		'cat_necessary_name', 'cat_functional_name', 'cat_analytics_name', 'cat_marketing_name',
	);
	foreach ( $text_keys as $k ) {
		$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $def[ $k ];
	}

	// Delší texty
	$area_keys = array(
		'message', 'prefs_intro',
		'cat_necessary_desc', 'cat_functional_desc', 'cat_analytics_desc', 'cat_marketing_desc',
	);
	foreach ( $area_keys as $k ) {
		$out[ $k ] = isset( $in[ $k ] ) ? sanitize_textarea_field( $in[ $k ] ) : $def[ $k ];
	}

	// Barvy
	$color_keys = array( 'color_bg', 'color_text', 'color_heading', 'color_primary', 'color_primary_text', 'color_accent', 'color_border' );
	foreach ( $color_keys as $k ) {
		$val = isset( $in[ $k ] ) ? sanitize_hex_color( $in[ $k ] ) : '';
		$out[ $k ] = $val ? $val : $def[ $k ];
	}

	// Skripty (repeater) — kód ukládáme syrově (nastavuje jen administrátor).
	$out['scripts'] = array();
	if ( isset( $in['scripts'] ) && is_array( $in['scripts'] ) ) {
		foreach ( $in['scripts'] as $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$code = isset( $row['code'] ) ? trim( $row['code'] ) : '';
			if ( '' === $name && '' === $code ) {
				continue;
			}
			$cat = ( isset( $row['category'] ) && in_array( $row['category'], array( 'functional', 'analytics', 'marketing' ), true ) ) ? $row['category'] : 'analytics';
			$pos = ( isset( $row['position'] ) && in_array( $row['position'], array( 'head', 'body' ), true ) ) ? $row['position'] : 'head';
			$out['scripts'][] = array(
				'name'     => $name,
				'category' => $cat,
				'position' => $pos,
				'code'     => $code,
			);
		}
	}

	update_option( FUTURI_COOKIES_OPT, $out );
	wp_safe_redirect( add_query_arg( array( 'page' => 'futuri-cookies', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
}

/* ---------------- Export záznamů souhlasů (CSV) ---------------- */
add_action( 'admin_post_futuri_cookies_export', 'futuri_cookies_export' );
function futuri_cookies_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nedostatečná oprávnění.' );
	}
	check_admin_referer( 'futuri_cookies_export' );

	global $wpdb;
	$table = $wpdb->prefix . 'futuri_consent_log';
	$rows  = $wpdb->get_results( "SELECT created_at, ip, url, consent, user_agent FROM {$table} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=futuri-souhlasy-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM pro Excel
	fputcsv( $out, array( 'Datum a čas', 'IP (anonymní)', 'URL', 'Souhlas', 'Prohlížeč' ) );
	if ( $rows ) {
		foreach ( $rows as $r ) {
			fputcsv( $out, array( $r['created_at'], $r['ip'], $r['url'], $r['consent'], $r['user_agent'] ) );
		}
	}
	fclose( $out );
	exit;
}

/* ---------------- Stránka nastavení ---------------- */
function futuri_cookies_settings_page() {
	$o = futuri_cookies_get();
	?>
	<div class="wrap fc-admin">
		<h1>futuri Cookies</h1>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Nastavení uloženo.</p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper fc-tabs">
			<a href="#fc-tab-general" class="nav-tab nav-tab-active">Obecné</a>
			<a href="#fc-tab-texts" class="nav-tab">Texty a kategorie</a>
			<a href="#fc-tab-design" class="nav-tab">Vzhled</a>
			<a href="#fc-tab-scripts" class="nav-tab">Skripty a služby</a>
			<a href="#fc-tab-log" class="nav-tab">Záznamy souhlasů</a>
		</h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="futuri_cookies_save">
			<?php wp_nonce_field( 'futuri_cookies_save', 'futuri_cookies_nonce' ); ?>

			<!-- OBECNÉ -->
			<div id="fc-tab-general" class="fc-tab-panel">
				<table class="form-table">
					<tr>
						<th>Zapnuto</th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $o['enabled'] ); ?>> Zobrazovat cookie lištu na webu</label></td>
					</tr>
					<tr>
						<th>Rozvržení</th>
						<td>
							<label><input type="radio" name="layout" value="box" <?php checked( $o['layout'], 'box' ); ?>> Kartička (box)</label>&nbsp;&nbsp;
							<label><input type="radio" name="layout" value="bar" <?php checked( $o['layout'], 'bar' ); ?>> Lišta přes šířku (bar)</label>
						</td>
					</tr>
					<tr>
						<th>Umístění</th>
						<td>
							<select name="position">
								<option value="bottom-left" <?php selected( $o['position'], 'bottom-left' ); ?>>Vlevo dole</option>
								<option value="bottom-right" <?php selected( $o['position'], 'bottom-right' ); ?>>Vpravo dole</option>
								<option value="bottom-center" <?php selected( $o['position'], 'bottom-center' ); ?>>Uprostřed dole</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Platnost souhlasu</th>
						<td><input type="number" name="expiry" value="<?php echo esc_attr( $o['expiry'] ); ?>" min="1" max="3650" class="small-text"> dní</td>
					</tr>
					<tr>
						<th>Odkaz na Zásady</th>
						<td>
							<input type="url" name="privacy_url" value="<?php echo esc_attr( $o['privacy_url'] ); ?>" class="regular-text" placeholder="https://futuri.cz/ochrana-osobnich-udaju/">
							<p class="description">Zobrazí se jako odkaz v liště i v panelu nastavení.</p>
						</td>
					</tr>
					<tr>
						<th>Google Consent Mode v2</th>
						<td>
							<label><input type="checkbox" name="consent_mode" value="1" <?php checked( $o['consent_mode'] ); ?>> Zapnout (doporučeno, pokud používáte Google Analytics / Ads)</label>
							<p class="description">Nastaví výchozí stav souhlasu na „denied“ dřív, než se načte Google tag, a po volbě ho automaticky aktualizuje. Váš Google tag pak stačí načíst běžným způsobem.</p>
						</td>
					</tr>
					<tr>
						<th>Záznamy souhlasů</th>
						<td><label><input type="checkbox" name="log_consents" value="1" <?php checked( $o['log_consents'] ); ?>> Ukládat záznamy souhlasů (s anonymizovanou IP) — viz záložka Záznamy</label></td>
					</tr>
					<tr>
						<th>Plovoucí tlačítko</th>
						<td>
							<label><input type="checkbox" name="float_button" value="1" <?php checked( $o['float_button'] ); ?>> Zobrazit malé tlačítko pro znovuotevření nastavení</label>
							<p><input type="text" name="float_label" value="<?php echo esc_attr( $o['float_label'] ); ?>" class="regular-text" placeholder="Cookies"></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- TEXTY -->
			<div id="fc-tab-texts" class="fc-tab-panel" style="display:none">
				<h3>Lišta</h3>
				<table class="form-table">
					<tr><th>Nadpis</th><td><input type="text" name="title" value="<?php echo esc_attr( $o['title'] ); ?>" class="large-text"></td></tr>
					<tr><th>Text</th><td><textarea name="message" rows="3" class="large-text"><?php echo esc_textarea( $o['message'] ); ?></textarea></td></tr>
					<tr><th>Tlačítko „Přijmout“</th><td><input type="text" name="btn_accept" value="<?php echo esc_attr( $o['btn_accept'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Tlačítko „Odmítnout“</th><td><input type="text" name="btn_reject" value="<?php echo esc_attr( $o['btn_reject'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Tlačítko „Nastavit“</th><td><input type="text" name="btn_settings" value="<?php echo esc_attr( $o['btn_settings'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Text odkazu na Zásady</th><td><input type="text" name="privacy_label" value="<?php echo esc_attr( $o['privacy_label'] ); ?>" class="regular-text"></td></tr>
				</table>

				<h3>Panel nastavení</h3>
				<table class="form-table">
					<tr><th>Nadpis panelu</th><td><input type="text" name="prefs_title" value="<?php echo esc_attr( $o['prefs_title'] ); ?>" class="large-text"></td></tr>
					<tr><th>Úvodní text</th><td><textarea name="prefs_intro" rows="2" class="large-text"><?php echo esc_textarea( $o['prefs_intro'] ); ?></textarea></td></tr>
					<tr><th>Tlačítko „Uložit volbu“</th><td><input type="text" name="btn_save" value="<?php echo esc_attr( $o['btn_save'] ); ?>" class="regular-text"></td></tr>
				</table>

				<h3>Kategorie</h3>
				<table class="form-table">
					<tr><th>Nezbytné — název</th><td><input type="text" name="cat_necessary_name" value="<?php echo esc_attr( $o['cat_necessary_name'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Nezbytné — popis</th><td><textarea name="cat_necessary_desc" rows="2" class="large-text"><?php echo esc_textarea( $o['cat_necessary_desc'] ); ?></textarea></td></tr>
					<tr><th>Preferenční — název</th><td><input type="text" name="cat_functional_name" value="<?php echo esc_attr( $o['cat_functional_name'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Preferenční — popis</th><td><textarea name="cat_functional_desc" rows="2" class="large-text"><?php echo esc_textarea( $o['cat_functional_desc'] ); ?></textarea></td></tr>
					<tr><th>Analytické — název</th><td><input type="text" name="cat_analytics_name" value="<?php echo esc_attr( $o['cat_analytics_name'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Analytické — popis</th><td><textarea name="cat_analytics_desc" rows="2" class="large-text"><?php echo esc_textarea( $o['cat_analytics_desc'] ); ?></textarea></td></tr>
					<tr><th>Marketingové — název</th><td><input type="text" name="cat_marketing_name" value="<?php echo esc_attr( $o['cat_marketing_name'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Marketingové — popis</th><td><textarea name="cat_marketing_desc" rows="2" class="large-text"><?php echo esc_textarea( $o['cat_marketing_desc'] ); ?></textarea></td></tr>
				</table>
			</div>

			<!-- VZHLED -->
			<div id="fc-tab-design" class="fc-tab-panel" style="display:none">
				<table class="form-table">
					<tr><th>Pozadí</th><td><input type="text" name="color_bg" value="<?php echo esc_attr( $o['color_bg'] ); ?>" class="fc-color"></td></tr>
					<tr><th>Text</th><td><input type="text" name="color_text" value="<?php echo esc_attr( $o['color_text'] ); ?>" class="fc-color"></td></tr>
					<tr><th>Nadpisy</th><td><input type="text" name="color_heading" value="<?php echo esc_attr( $o['color_heading'] ); ?>" class="fc-color"></td></tr>
					<tr><th>Tlačítka (pozadí)</th><td><input type="text" name="color_primary" value="<?php echo esc_attr( $o['color_primary'] ); ?>" class="fc-color"><p class="description">Platí pro Přijmout i Odmítnout — obě tlačítka jsou záměrně rovnocenná.</p></td></tr>
					<tr><th>Tlačítka (text)</th><td><input type="text" name="color_primary_text" value="<?php echo esc_attr( $o['color_primary_text'] ); ?>" class="fc-color"></td></tr>
					<tr><th>Akcent (odkazy, přepínače)</th><td><input type="text" name="color_accent" value="<?php echo esc_attr( $o['color_accent'] ); ?>" class="fc-color"></td></tr>
					<tr><th>Okraj</th><td><input type="text" name="color_border" value="<?php echo esc_attr( $o['color_border'] ); ?>" class="fc-color"></td></tr>
					<tr><th>Zaoblení rohů</th><td><input type="number" name="radius" value="<?php echo esc_attr( $o['radius'] ); ?>" min="0" max="40" class="small-text"> px</td></tr>
				</table>
				<p class="description">Tip: barvy jsou předvyplněné podle futuri brandu (emerald pro nadpisy/tlačítka, coral pro akcent, off-white pozadí).</p>
			</div>

			<!-- SKRIPTY -->
			<div id="fc-tab-scripts" class="fc-tab-panel" style="display:none">
				<p class="description" style="max-width:760px">
					Sem vložte kód sledovacích/reklamních služeb (např. Facebook Pixel, Sklik, Hotjar).
					Každou zde uloženou položku plugin automaticky označí jako blokovanou a aktivuje ji
					až po udělení souhlasu s příslušnou kategorií. Skripty vložené jiným pluginem nebo
					šablonou plugin automaticky neblokuje: jejich autor je musí označit
					<code>type="text/plain"</code> a <code>data-cookiecategory</code>; u externího
					skriptu musí použít <code>data-src</code> místo <code>src</code>.
					<strong>Google Analytics / Ads:</strong> Consent Mode v2 nastavuje stav souhlasu pro
					Google tagy, ale není univerzálním blokátorem externích skriptů.
				</p>
				<div id="fc-scripts">
					<?php
					$scripts = ! empty( $o['scripts'] ) ? $o['scripts'] : array();
					if ( empty( $scripts ) ) {
						$scripts = array( array( 'name' => '', 'category' => 'analytics', 'position' => 'head', 'code' => '' ) );
					}
					foreach ( $scripts as $i => $s ) :
						?>
						<div class="fc-script-row">
							<div class="fc-script-top">
								<input type="text" name="scripts[<?php echo $i; ?>][name]" value="<?php echo esc_attr( $s['name'] ); ?>" placeholder="Název (např. Facebook Pixel)" class="regular-text">
								<select name="scripts[<?php echo $i; ?>][category]">
									<option value="functional" <?php selected( $s['category'], 'functional' ); ?>>Preferenční</option>
									<option value="analytics" <?php selected( $s['category'], 'analytics' ); ?>>Analytické</option>
									<option value="marketing" <?php selected( $s['category'], 'marketing' ); ?>>Marketingové</option>
								</select>
								<select name="scripts[<?php echo $i; ?>][position]">
									<option value="head" <?php selected( $s['position'], 'head' ); ?>>&lt;head&gt;</option>
									<option value="body" <?php selected( $s['position'], 'body' ); ?>>&lt;body&gt; (patička)</option>
								</select>
								<button type="button" class="button fc-script-remove">Odebrat</button>
							</div>
							<textarea name="scripts[<?php echo $i; ?>][code]" rows="4" class="large-text code" placeholder="&lt;script&gt;...&lt;/script&gt; nebo čistý JS"><?php echo esc_textarea( $s['code'] ); ?></textarea>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-secondary" id="fc-script-add">+ Přidat službu</button>
			</div>

			<!-- ZÁZNAMY -->
			<div id="fc-tab-log" class="fc-tab-panel" style="display:none">
				<?php futuri_cookies_render_log(); ?>
			</div>

			<p class="submit"><button type="submit" class="button button-primary button-large">Uložit nastavení</button></p>
		</form>
	</div>
	<?php
}

/* ---------------- Výpis záznamů souhlasů ---------------- */
function futuri_cookies_render_log() {
	global $wpdb;
	$table = $wpdb->prefix . 'futuri_consent_log';
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	$rows  = $wpdb->get_results( "SELECT created_at, ip, url, consent FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A ); // phpcs:ignore

	echo '<p>Celkem záznamů: <strong>' . esc_html( $count ) . '</strong> &nbsp; ';
	$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=futuri_cookies_export' ), 'futuri_cookies_export' );
	echo '<a href="' . esc_url( $export_url ) . '" class="button">Exportovat vše do CSV</a></p>';

	if ( ! $rows ) {
		echo '<p class="description">Zatím žádné záznamy. Objeví se tady, jakmile návštěvníci začnou udělovat souhlasy.</p>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr><th>Datum a čas</th><th>IP (anonymní)</th><th>Souhlas</th><th>URL</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$c   = json_decode( $r['consent'], true );
		$tag = '';
		if ( is_array( $c ) ) {
			$on = array();
			foreach ( array( 'functional' => 'Pref.', 'analytics' => 'Anal.', 'marketing' => 'Mark.' ) as $k => $lbl ) {
				if ( ! empty( $c[ $k ] ) ) {
					$on[] = $lbl;
				}
			}
			$tag = $on ? implode( ', ', $on ) : 'jen nezbytné';
		}
		echo '<tr>';
		echo '<td>' . esc_html( $r['created_at'] ) . '</td>';
		echo '<td>' . esc_html( $r['ip'] ) . '</td>';
		echo '<td>' . esc_html( $tag ) . '</td>';
		echo '<td>' . esc_html( $r['url'] ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">Zobrazeno posledních 100 záznamů. Kompletní historie je v CSV exportu.</p>';
}
