<?php
/**
 * Pagina "LU3G Tracking" (sotto il menu di LU3G Hub se c'è, altrimenti voce propria).
 *
 * Schede: Panoramica, Impostazioni, Form, Contatti, Consenso, GTM, Diagnostica.
 * Tutte le azioni passano da admin-ajax con nonce E permesso manage_options; i download da admin-post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Admin {

	const PAGE = 'lu3g-tracking';
	const CAP  = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 ); // dopo LU3G Hub (priorità 10)
		add_action( 'wp_ajax_lu3g_ct', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_lu3g_ct_gtm', array( __CLASS__, 'download_gtm' ) );
		add_action( 'admin_post_lu3g_ct_tpl', array( __CLASS__, 'download_tpl' ) );
		add_action( 'admin_post_lu3g_ct_tpl_consent', array( __CLASS__, 'download_tpl_consent' ) );
		add_action( 'admin_post_lu3g_ct_report', array( __CLASS__, 'download_report' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LU3G_CT_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		global $admin_page_hooks;
		if ( isset( $admin_page_hooks['lu3g-hub'] ) ) {
			$hook = add_submenu_page( 'lu3g-hub', 'LU3G Tracking', __( 'Tracking', 'lu3g-conversion-tracker' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		} else {
			$svg  = (string) file_get_contents( LU3G_CT_PATH . 'assets/lu3g-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$hook = add_menu_page( 'LU3G Tracking', 'LU3G Tracking', self::CAP, self::PAGE, array( __CLASS__, 'render' ), 'data:image/svg+xml;base64,' . base64_encode( $svg ), 82 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		add_action( 'load-' . $hook, array( __CLASS__, 'load' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Impostazioni', 'lu3g-conversion-tracker' ) . '</a>' );
		return $links;
	}

	public static function notice() {
		if ( ! current_user_can( self::CAP ) || LU3G_CT_Forms::has_elementor_pro() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'plugins', 'dashboard' ), true ) ) {
			echo '<div class="notice notice-info"><p><strong>LU3G Conversion Tracker</strong>: ' . esc_html__( 'Elementor Pro non è attivo, quindi i form non vengono tracciati. I click su WhatsApp, telefono ed email funzionano.', 'lu3g-conversion-tracker' ) . '</p></div>';
		}
	}

	public static function load() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter(
			'admin_body_class',
			function ( $c ) {
				return $c . ' lu3g-ct-page';
			}
		);
	}

	public static function assets() {
		wp_enqueue_style( 'lu3g-ct-admin', LU3G_CT_URL . 'assets/admin.css', array(), LU3G_CT_VERSION );
		wp_add_inline_style( 'lu3g-ct-admin', '@font-face{font-family:"LU3G Manrope";src:url(' . esc_url( LU3G_CT_URL . 'assets/fonts/manrope-latin-wght.woff2' ) . ') format("woff2");font-weight:200 800;font-display:swap}' );
		wp_enqueue_script( 'lu3g-ct-core', LU3G_CT_URL . 'assets/lu3g-ct-core.js', array(), LU3G_CT_VERSION, true );
		wp_enqueue_script( 'lu3g-ct-libphone', LU3G_CT_URL . 'assets/vendor/libphonenumber-js/libphonenumber-min.js', array(), '1.13.15', true );
		wp_enqueue_script( 'lu3g-ct-admin', LU3G_CT_URL . 'assets/admin.js', array( 'lu3g-ct-core', 'lu3g-ct-libphone' ), LU3G_CT_VERSION, true );
		wp_localize_script(
			'lu3g-ct-admin',
			'LU3G_CT',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'lu3g_ct' ),
				'home'  => home_url( '/' ),
			)
		);
	}

	/* ================================================================ pagina */

	public static function render() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'overview' => __( 'Panoramica', 'lu3g-conversion-tracker' ),
			'settings' => __( 'Impostazioni', 'lu3g-conversion-tracker' ),
			'forms'    => __( 'Form', 'lu3g-conversion-tracker' ),
			'contacts' => __( 'Contatti', 'lu3g-conversion-tracker' ),
			'consent'  => __( 'Consenso', 'lu3g-conversion-tracker' ),
			'gtm'      => __( 'GTM', 'lu3g-conversion-tracker' ),
			'diag'     => __( 'Diagnostica', 'lu3g-conversion-tracker' ),
		);
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'overview';
		$g    = LU3G_CT_Settings::get();
		$forms = LU3G_CT_Forms::all();
		$on    = 0;
		$errs  = 0;
		foreach ( $forms as $f ) {
			$e = LU3G_CT_Settings::effective( $f );
			$on += $e['cfg']['enabled'] && empty( $e['errors']['event'] ) ? 1 : 0;
			$errs += $e['errors'] ? 1 : 0;
		}
		$logo = (string) file_get_contents( LU3G_CT_PATH . 'assets/lu3g-logo.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		echo '<div class="wrap lu3g-ct"><h1 class="screen-reader-text">LU3G Tracking</h1>';
		echo '<header class="ct-hero"><div class="ct-hero__glow" aria-hidden="true"></div><div class="ct-hero__brand"><div class="ct-hero__logo">' . $logo . '</div><div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG del plugin
		echo '<p class="ct-hero__eyebrow">LU3G Conversion Tracker</p><h2 class="ct-hero__title">' . esc_html__( 'Conversioni per Google Tag Manager', 'lu3g-conversion-tracker' ) . '</h2>';
		echo '<p class="ct-hero__lead">' . esc_html__( 'Form di Elementor e click sui contatti nel dataLayer, con identificatori cifrati per le conversioni avanzate quando il consenso lo permette.', 'lu3g-conversion-tracker' ) . '</p></div></div>';
		echo '<dl class="ct-stats"><div><dt>' . esc_html__( 'Form censiti', 'lu3g-conversion-tracker' ) . '</dt><dd>' . count( $forms ) . '</dd></div><div><dt>' . esc_html__( 'Tracciati', 'lu3g-conversion-tracker' ) . '</dt><dd>' . (int) $on . '</dd></div><div><dt>' . esc_html__( 'Da sistemare', 'lu3g-conversion-tracker' ) . '</dt><dd>' . (int) $errs . '</dd></div></dl>';
		if ( $g['test_mode'] ) {
			echo '<p class="ct-hero__flag">' . esc_html__( 'Modalità di test attiva: tutti gli eventi hanno test_mode: true', 'lu3g-conversion-tracker' ) . '</p>';
		}
		echo '</header>';

		echo '<nav class="ct-tabs" aria-label="' . esc_attr__( 'Sezioni', 'lu3g-conversion-tracker' ) . '">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a href="%1$s" class="ct-tab%2$s"%3$s>%4$s</a>', esc_url( self::url( array( 'tab' => $k ) ) ), $k === $tab ? ' is-active' : '', $k === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav><div class="ct-body">';
		call_user_func( array( __CLASS__, 'tab_' . $tab ), $g, $forms );
		echo '</div><div class="ct-toasts" data-toasts aria-live="polite"></div><p class="ct-foot">LU3G Conversion Tracker ' . esc_html( LU3G_CT_VERSION ) . ' · libphonenumber-js 1.13.15</p></div>';
	}

	/* ---------------------------------------------------------------- componenti */

	private static function field( $name, $label, $value, $help = '', $attrs = '' ) {
		printf( '<label class="ct-field"><span>%1$s</span><input type="text" class="ct-input" name="%2$s" value="%3$s" spellcheck="false" %5$s><small data-err="%2$s"></small>%4$s</label>', esc_html( $label ), esc_attr( $name ), esc_attr( (string) $value ), $help ? '<small class="ct-help">' . esc_html( $help ) . '</small>' : '', $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs costanti
	}

	private static function toggle( $name, $label, $on, $help = '' ) {
		printf( '<label class="ct-toggle"><input type="checkbox" name="%1$s" value="1" %2$s><span class="ct-toggle__ui" aria-hidden="true"></span><span><strong>%3$s</strong>%4$s</span></label>', esc_attr( $name ), checked( $on, true, false ), esc_html( $label ), $help ? '<small>' . esc_html( $help ) . '</small>' : '' );
	}

	private static function savebar() {
		echo '<div class="ct-savebar"><button type="submit" class="ct-btn ct-btn--primary">' . esc_html__( 'Salva', 'lu3g-conversion-tracker' ) . '</button></div>';
	}

	private static function status( $state, $text ) {
		$labels = array(
			'ok'         => __( 'OK', 'lu3g-conversion-tracker' ),
			'error'      => __( 'Errore', 'lu3g-conversion-tracker' ),
			'consent'    => __( 'Bloccato dal consenso', 'lu3g-conversion-tracker' ),
			'unverified' => __( 'Non verificato', 'lu3g-conversion-tracker' ),
			'na'         => __( 'Non applicabile', 'lu3g-conversion-tracker' ),
		);
		return '<span class="ct-st ct-st--' . esc_attr( $state ) . '">' . esc_html( $labels[ $state ] ?? $state ) . '</span> ' . esc_html( $text );
	}

	/* ---------------------------------------------------------------- schede */

	private static function tab_overview( array $g, array $forms ) {
		$checks   = array();
		$checks[] = LU3G_CT_Forms::has_elementor_pro()
			? array( 'ok', sprintf( /* translators: %s: versione */ __( 'Elementor Pro %s attivo: adapter elementor_form (widget Form classico).', 'lu3g-conversion-tracker' ), ELEMENTOR_PRO_VERSION ) )
			: array( 'error', __( 'Elementor Pro non attivo: i form non vengono tracciati (i click sui contatti sì).', 'lu3g-conversion-tracker' ) );
		$atomic = count(
			array_filter(
				$forms,
				function ( $f ) {
					return 'atomic_form' === $f['adapter'];
				}
			)
		);
		if ( $atomic ) {
			/* translators: %d: numero */
			$checks[] = array( 'na', sprintf( __( '%d Atomic Form trovati: non ancora supportati in questa versione (non vengono tracciati).', 'lu3g-conversion-tracker' ), $atomic ) );
		}
		$checks[] = is_ssl() ? array( 'ok', __( 'Sito in HTTPS: Web Crypto disponibile per gli hash.', 'lu3g-conversion-tracker' ) ) : array( 'error', __( 'Sito senza HTTPS: i browser non danno Web Crypto, quindi gli identificatori vengono omessi (gli eventi partono lo stesso).', 'lu3g-conversion-tracker' ) );
		$names    = array(
			'consent_mode' => __( 'Consent Mode (comandi gtag consent nel dataLayer)', 'lu3g-conversion-tracker' ),
			'iubenda'      => 'Iubenda',
			'custom'       => __( 'adapter personalizzato', 'lu3g-conversion-tracker' ),
			'none'         => __( 'nessuno: identificatori mai inviati', 'lu3g-conversion-tracker' ),
		);
		$checks[] = array( 'none' === $g['consent'] ? 'consent' : 'unverified', sprintf( /* translators: %s: adapter */ __( 'Consenso: %s. Lo stato reale si vede nella sessione di verifica.', 'lu3g-conversion-tracker' ), $names[ $g['consent'] ] ) );
		$checks[] = '' !== $g['container_id'] ? array( 'unverified', sprintf( /* translators: %s: ID */ __( 'Container atteso %s: controllato nella sessione di verifica.', 'lu3g-conversion-tracker' ), $g['container_id'] ) ) : array( 'unverified', __( 'Nessun container atteso impostato (Impostazioni).', 'lu3g-conversion-tracker' ) );
		$missing  = LU3G_CT_GTM::missing();
		$checks[] = $missing ? array( 'error', __( 'Configurazione GTM incompleta: ', 'lu3g-conversion-tracker' ) . implode( ', ', $missing ) . '.' ) : array( 'ok', __( 'ID GA4, ID Ads ed etichette pronti per il JSON da importare.', 'lu3g-conversion-tracker' ) );
		$res      = LU3G_CT_Diagnostics::results();
		$checks[] = $res ? array( 'ok', sprintf( /* translators: %s: data */ __( 'Ultimo test: %s.', 'lu3g-conversion-tracker' ), wp_date( 'd/m/Y H:i', $res[0]['saved'] ) ) ) : array( 'unverified', __( 'Nessun test eseguito: Diagnostica → Avvia sessione di verifica.', 'lu3g-conversion-tracker' ) );

		echo '<div class="ct-card"><h3>' . esc_html__( 'Stato', 'lu3g-conversion-tracker' ) . '</h3><ul class="ct-checks">';
		foreach ( $checks as $c ) {
			echo '<li>' . self::status( $c[0], $c[1] ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito escapato
		}
		echo '</ul></div>';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Come funziona', 'lu3g-conversion-tracker' ) . '</h3><ol class="ct-steps">';
		echo '<li>' . esc_html__( 'Dopo un invio riuscito di un form di Elementor il plugin mette nel dataLayer l\'evento lead_submit (o quello scelto per il form). Click su WhatsApp, telefono ed email: contact_click.', 'lu3g-conversion-tracker' ) . '</li>';
		echo '<li>' . esc_html__( 'Se il consenso ad_user_data è concesso, aggiunge email e telefono normalizzati e cifrati (SHA-256) in lu3g_user_data, solo per Google Ads.', 'lu3g-conversion-tracker' ) . '</li>';
		echo '<li>' . esc_html__( 'GTM (scheda GTM: JSON da importare) manda gli eventi a GA4 e Google Ads. Il plugin non manda niente direttamente a Google: un push nel dataLayer non è una conferma di ricezione.', 'lu3g-conversion-tracker' ) . '</li>';
		echo '<li>' . esc_html__( 'Diagnostica: sessione di verifica nel sito, con esito di ogni passaggio.', 'lu3g-conversion-tracker' ) . '</li></ol></div>';
	}

	private static function tab_settings( array $g ) {
		echo '<form class="ct-form" data-save="settings" autocomplete="off">';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Form', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Valgono per tutti i form; ogni form può cambiarle nella sezione "Tracciamento LU3G" del widget o nella scheda Form.', 'lu3g-conversion-tracker' ) . '</p><div class="ct-grid">';
		self::toggle( 'forms_enabled', __( 'Tracking dei form attivo', 'lu3g-conversion-tracker' ), $g['forms_enabled'], __( 'Anche sui form nuovi, senza toccarli.', 'lu3g-conversion-tracker' ) );
		self::toggle( 'ec', __( 'Conversioni avanzate', 'lu3g-conversion-tracker' ), $g['ec'], __( 'Email e telefono cifrati, solo con il consenso ad_user_data.', 'lu3g-conversion-tracker' ) );
		self::field( 'event', __( 'Nome evento', 'lu3g-conversion-tracker' ), $g['event'], __( 'Predefinito lead_submit. Se lo cambi, aggiorna anche GTM.', 'lu3g-conversion-tracker' ) );
		self::field( 'conversion_key', __( 'Chiave conversione Ads', 'lu3g-conversion-tracker' ), $g['conversion_key'], __( 'Nome logico collegato in GTM a ID ed etichetta. Vuoto: nessuna conversione Ads.', 'lu3g-conversion-tracker' ) );
		self::field( 'value', __( 'Valore', 'lu3g-conversion-tracker' ), $g['value'], __( 'Vuoto: nessun valore (non inventarne uno). 0 è un valore. Virgola o punto.', 'lu3g-conversion-tracker' ) );
		self::field( 'currency', __( 'Valuta', 'lu3g-conversion-tracker' ), $g['currency'], __( 'ISO 4217, es. EUR.', 'lu3g-conversion-tracker' ) );
		self::field( 'country', __( 'Paese dei telefoni', 'lu3g-conversion-tracker' ), $g['country'], __( 'Per i numeri scritti senza prefisso. Es. IT.', 'lu3g-conversion-tracker' ) );
		echo '</div></div>';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Google Tag Manager', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Si usa il container già presente nel sito: il plugin non ne aggiunge un altro.', 'lu3g-conversion-tracker' ) . '</p><div class="ct-grid">';
		self::field( 'container_id', __( 'ID del container atteso', 'lu3g-conversion-tracker' ), $g['container_id'], __( 'GTM-XXXXXXX, solo per la diagnostica.', 'lu3g-conversion-tracker' ) );
		self::field( 'datalayer', __( 'Nome del dataLayer', 'lu3g-conversion-tracker' ), $g['datalayer'], __( 'Predefinito dataLayer.', 'lu3g-conversion-tracker' ) );
		echo '</div></div>';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Test', 'lu3g-conversion-tracker' ) . '</h3><div class="ct-grid">';
		self::toggle( 'test_mode', __( 'Modalità di test', 'lu3g-conversion-tracker' ), $g['test_mode'], __( 'test_mode: true su tutti gli eventi. Da sola non esclude niente: in GTM gli attivatori LU3G filtrano test_mode = false.', 'lu3g-conversion-tracker' ) );
		self::toggle( 'admins_test', __( 'Amministratori collegati come test', 'lu3g-conversion-tracker' ), $g['admins_test'], __( 'I tuoi invii da amministratore non contano come conversioni reali.', 'lu3g-conversion-tracker' ) );
		echo '</div></div>';
		self::savebar();
		echo '</form>';
	}

	private static function tab_forms( array $g, array $forms ) {
		echo '<div class="ct-card"><div class="ct-card__head"><h3>' . esc_html__( 'Form trovati', 'lu3g-conversion-tracker' ) . '</h3><button type="button" class="ct-btn" data-op="rescan">' . esc_html__( 'Riscansiona', 'lu3g-conversion-tracker' ) . '</button></div>';
		/* translators: %s: tempo */
		echo '<p class="ct-help">' . esc_html( sprintf( __( 'Pagine, template e popup di Elementor. Ultima scansione: %s fa; si aggiorna da sola quando salvi in Elementor.', 'lu3g-conversion-tracker' ), human_time_diff( max( 1, LU3G_CT_Forms::last_scan() ) ) ) ) . '</p>';
		if ( ! $forms ) {
			echo '<p class="ct-empty">' . esc_html__( 'Nessun form di Elementor trovato.', 'lu3g-conversion-tracker' ) . '</p></div>';
			return;
		}
		$o = array(
			'form'   => __( 'del form', 'lu3g-conversion-tracker' ),
			'global' => __( 'globale', 'lu3g-conversion-tracker' ),
			'auto'   => __( 'automatico', 'lu3g-conversion-tracker' ),
		);
		echo '<div class="ct-forms">';
		foreach ( $forms as $key => $f ) {
			$e   = LU3G_CT_Settings::effective( $f );
			$c   = $e['cfg'];
			$sup = 'supported' === $f['adapter_status'];
			$on  = $c['enabled'] && empty( $e['errors']['event'] );
			echo '<details class="ct-formrow"><summary><span class="ct-dot ' . ( $on ? 'is-on' : '' ) . '" aria-hidden="true"></span><span class="ct-formrow__name"><strong>' . esc_html( $f['name'] ) . '</strong><small>' . esc_html( $f['post_title'] . ' · ' . $key ) . '</small></span>';
			echo '<span class="ct-formrow__meta">' . ( $sup ? ( $on ? '<code>' . esc_html( $c['event'] ) . '</code>' : '<em>' . esc_html__( 'non tracciato', 'lu3g-conversion-tracker' ) . '</em>' ) : '<em>' . esc_html__( 'Atomic Form: non supportato', 'lu3g-conversion-tracker' ) . '</em>' ) . ( $e['errors'] ? ' <span class="ct-st ct-st--error">' . esc_html__( 'Da sistemare', 'lu3g-conversion-tracker' ) . '</span>' : '' ) . '</span></summary>';
			echo '<div class="ct-formrow__body">';
			if ( $e['errors'] ) {
				echo '<ul class="ct-errors">';
				foreach ( $e['errors'] as $msg ) {
					echo '<li>' . esc_html( $msg ) . '</li>';
				}
				echo '</ul>';
			}
			if ( ! $sup ) {
				echo '<p class="ct-help">' . esc_html__( 'Atomic Form usa un ciclo di invio diverso dal widget classico: verrà supportato con un adapter dedicato e provato. Per ora non genera eventi.', 'lu3g-conversion-tracker' ) . '</p></div></details>';
				continue;
			}
			// Configurazione effettiva con origine
			$rows = array(
				__( 'Tracking', 'lu3g-conversion-tracker' )         => array( $c['enabled'] ? __( 'attivo', 'lu3g-conversion-tracker' ) : __( 'disattivo', 'lu3g-conversion-tracker' ), $e['origin']['enabled'] ),
				__( 'Evento', 'lu3g-conversion-tracker' )           => array( $c['event'], $e['origin']['event'] ),
				__( 'form_name', 'lu3g-conversion-tracker' )        => array( $c['form_name'], $e['origin']['form_name'] ),
				__( 'Valore', 'lu3g-conversion-tracker' )           => array( null === $c['value'] ? __( 'nessuno', 'lu3g-conversion-tracker' ) : $c['value'] . ' ' . $c['currency'], $e['origin']['value'] ),
				__( 'Conversione Ads', 'lu3g-conversion-tracker' )  => array( '' !== $c['conversion_key'] ? $c['conversion_key'] : __( 'esclusa', 'lu3g-conversion-tracker' ), $e['origin']['conversion_key'] ),
				__( 'Conv. avanzate', 'lu3g-conversion-tracker' )   => array( $c['ec'] ? __( 'sì (col consenso)', 'lu3g-conversion-tracker' ) : __( 'no', 'lu3g-conversion-tracker' ), $e['origin']['ec'] ),
				__( 'Campo email', 'lu3g-conversion-tracker' )      => array( '' !== $c['email'] ? $c['email'] : '—', $e['origin']['email'] ),
				__( 'Campo telefono', 'lu3g-conversion-tracker' )   => array( '' !== $c['phone'] ? $c['phone'] : '—', $e['origin']['phone'] ),
				__( 'Paese telefono', 'lu3g-conversion-tracker' )   => array( $c['country'] . ( $c['country_field'] ? ' + ' . __( 'campo', 'lu3g-conversion-tracker' ) . ' ' . $c['country_field'] : '' ), $e['origin']['country'] ),
			);
			echo '<dl class="ct-eff">';
			foreach ( $rows as $label => $r ) {
				echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( (string) $r[0] ) . ' <small>' . esc_html( $o[ $r[1] ] ?? $r[1] ) . '</small></dd></div>';
			}
			echo '</dl>';
			$cand = $e['candidates'];
			/* translators: 1: campi email, 2: campi telefono */
			echo '<p class="ct-help">' . esc_html( sprintf( __( 'Candidati trovati — email: %1$s · telefono: %2$s', 'lu3g-conversion-tracker' ), $cand['email'] ? implode( ', ', $cand['email'] ) : '—', $cand['phone'] ? implode( ', ', $cand['phone'] ) : '—' ) ) . '</p>';

			// Modifica (scrive nel widget, come la sezione "Tracciamento LU3G" di Elementor)
			$s   = $f['ct'];
			$sel = function ( $name, array $opts ) use ( $s ) {
				$cur = $s[ $name ] ?? '';
				$h   = '<select name="' . esc_attr( $name ) . '">';
				foreach ( $opts as $v => $l ) {
					$h .= '<option value="' . esc_attr( $v ) . '"' . selected( $cur, $v, false ) . '>' . esc_html( $l ) . '</option>';
				}
				return $h . '</select>';
			};
			$fields = array( '' => __( 'Automatico', 'lu3g-conversion-tracker' ), '__none' => __( 'Non usare', 'lu3g-conversion-tracker' ) );
			foreach ( $f['fields'] as $fl ) {
				$fields[ $fl['id'] ] = $fl['label'] . ' (' . $fl['id'] . ', ' . $fl['type'] . ')';
			}
			$country_fields = array( '' => __( 'Nessuno', 'lu3g-conversion-tracker' ) ) + array_slice( $fields, 2, null, true );
			$inh            = __( 'Eredita', 'lu3g-conversion-tracker' );
			echo '<form class="ct-form ct-formedit" data-save="form" data-key="' . esc_attr( $key ) . '" autocomplete="off"><h4>' . esc_html__( 'Impostazioni di questo form', 'lu3g-conversion-tracker' ) . '</h4><div class="ct-grid ct-grid--3">';
			echo '<label class="ct-field"><span>' . esc_html__( 'Tracking del form', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_tracking', array( '' => $inh, 'on' => __( 'Attivo', 'lu3g-conversion-tracker' ), 'off' => __( 'Disattivo', 'lu3g-conversion-tracker' ) ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::field( 'lu3g_ct_event', __( 'Nome evento', 'lu3g-conversion-tracker' ), $s['lu3g_ct_event'] ?? '', '', 'placeholder="' . esc_attr( $g['event'] ) . '"' );
			self::field( 'lu3g_ct_label', __( 'Nome descrittivo', 'lu3g-conversion-tracker' ), $s['lu3g_ct_label'] ?? '', '', 'placeholder="' . esc_attr( LU3G_CT_Settings::label( $f['name'] ) ) . '"' );
			echo '<label class="ct-field"><span>' . esc_html__( 'Valore', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_value_mode', array( '' => $inh, 'fixed' => __( 'Importo specifico', 'lu3g-conversion-tracker' ), 'none' => __( 'Nessun valore', 'lu3g-conversion-tracker' ) ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::field( 'lu3g_ct_value', __( 'Importo', 'lu3g-conversion-tracker' ), $s['lu3g_ct_value'] ?? '', '', 'placeholder="25,50"' );
			self::field( 'lu3g_ct_currency', __( 'Valuta', 'lu3g-conversion-tracker' ), $s['lu3g_ct_currency'] ?? '', '', 'placeholder="' . esc_attr( $g['currency'] ) . '"' );
			echo '<label class="ct-field"><span>' . esc_html__( 'Campo email', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_email_field', $fields ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<label class="ct-field"><span>' . esc_html__( 'Campo telefono', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_phone_field', $fields ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<label class="ct-field"><span>' . esc_html__( 'Campo paese', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_country_field', $country_fields ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::field( 'lu3g_ct_country', __( 'Paese del telefono', 'lu3g-conversion-tracker' ), $s['lu3g_ct_country'] ?? '', '', 'placeholder="' . esc_attr( $g['country'] ) . '" maxlength="2"' );
			echo '<label class="ct-field"><span>' . esc_html__( 'Conversioni avanzate', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_ec', array( '' => $inh, 'on' => __( 'Abilitate (col consenso)', 'lu3g-conversion-tracker' ), 'off' => __( 'Disabilitate', 'lu3g-conversion-tracker' ) ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<label class="ct-field"><span>' . esc_html__( 'Destinazione Ads', 'lu3g-conversion-tracker' ) . '</span>' . $sel( 'lu3g_ct_ads', array( '' => $inh, 'key' => __( 'Chiave specifica', 'lu3g-conversion-tracker' ), 'off' => __( 'Esclusa', 'lu3g-conversion-tracker' ) ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::field( 'lu3g_ct_conversion_key', __( 'Chiave della conversione', 'lu3g-conversion-tracker' ), $s['lu3g_ct_conversion_key'] ?? '', '', 'placeholder="lead"' );
			echo '</div><div class="ct-inline"><button type="submit" class="ct-btn ct-btn--primary">' . esc_html__( 'Salva in Elementor', 'lu3g-conversion-tracker' ) . '</button><a class="ct-link" href="' . esc_url( LU3G_CT_Forms::edit_url( $f['post_id'] ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Apri in Elementor', 'lu3g-conversion-tracker' ) . ' ↗</a></div>';
			echo '<p class="ct-help">' . esc_html__( 'Il salvataggio scrive nelle impostazioni del widget (le stesse della sezione "Tracciamento LU3G" in Elementor). Se cambi nome evento o chiave, aggiorna anche GTM.', 'lu3g-conversion-tracker' ) . '</p></form>';
			echo '</div></details>';
		}
		echo '</div></div>';
	}

	private static function tab_contacts( array $g ) {
		echo '<form class="ct-form" data-save="settings" autocomplete="off">';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Click sui contatti', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Link tel:, mailto: e WhatsApp click-to-chat (wa.me, api.whatsapp.com/send, web.whatsapp.com/send, whatsapp://send) in tutto il sito, anche fuori da Elementor. Misura l\'intenzione di contatto, non una chiamata fatta o un messaggio inviato.', 'lu3g-conversion-tracker' ) . '</p><div class="ct-grid">';
		self::toggle( 'contacts', __( 'Traccia i click sui contatti', 'lu3g-conversion-tracker' ), $g['contacts'] );
		self::field( 'contact_event', __( 'Nome evento', 'lu3g-conversion-tracker' ), $g['contact_event'] );
		echo '</div></div>';
		$ch = array(
			'whatsapp' => 'WhatsApp',
			'phone'    => __( 'Telefono (tel:)', 'lu3g-conversion-tracker' ),
			'email'    => __( 'Email (mailto:)', 'lu3g-conversion-tracker' ),
		);
		echo '<div class="ct-card"><h3>' . esc_html__( 'Canali', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Ogni canale ha la sua chiave di conversione Ads (vuota = escluso da Ads) e il suo valore, separati dai form.', 'lu3g-conversion-tracker' ) . '</p>';
		foreach ( $ch as $k => $label ) {
			echo '<div class="ct-grid ct-grid--3 ct-channel">';
			self::toggle( 'ch_' . $k, $label, $g[ 'ch_' . $k ] );
			self::field( 'key_' . $k, __( 'Chiave conversione Ads', 'lu3g-conversion-tracker' ), $g[ 'key_' . $k ] );
			self::field( 'value_' . $k, __( 'Valore', 'lu3g-conversion-tracker' ), $g[ 'value_' . $k ], __( 'Vuoto: nessun valore.', 'lu3g-conversion-tracker' ) );
			echo '</div>';
		}
		echo '</div><div class="ct-card"><h3>' . esc_html__( 'Esclusioni e attributi', 'lu3g-conversion-tracker' ) . '</h3>';
		echo '<label class="ct-field ct-field--wide"><span>' . esc_html__( 'Escludi questi elementi (selettori CSS, uno per riga)', 'lu3g-conversion-tracker' ) . '</span><textarea class="ct-input" name="contact_exclude" rows="3" spellcheck="false" placeholder=".footer-credits&#10;#menu-legale">' . esc_textarea( $g['contact_exclude'] ) . '</textarea></label>';
		echo '<p class="ct-help">' . esc_html__( 'Facoltativi sul link o su un suo contenitore: data-lu3g-contact-id="commerciale" (contact_id), data-lu3g-placement="header" (placement; senza, si riconosce header/footer/popup di Elementor), data-lu3g-ct-ignore (non tracciare). I link funzionano anche senza attributi. Non si inviano numero, indirizzo, testo del messaggio né URL completo.', 'lu3g-conversion-tracker' ) . '</p></div>';
		self::savebar();
		echo '</form>';
	}

	private static function tab_consent( array $g ) {
		$opts = array(
			'consent_mode' => array( __( 'Consent Mode v2 (qualsiasi CMP)', 'lu3g-conversion-tracker' ), __( 'Legge lo stato scritto dal tag "LU3G - Ponte consenso" di GTM dopo un evento del CMP (per i CMP che lavorano dentro GTM, come il modello Iubenda), oppure i comandi gtag(\'consent\', \'update\', …) che il CMP mette nel dataLayer. Il solo stato predefinito vale come sconosciuto.', 'lu3g-conversion-tracker' ) ),
			'iubenda'      => array( 'Iubenda', __( 'Come sopra, più la presenza della Cookie Solution (_iub.cs.api). Con il modello Iubenda in GTM (googleConsentMode "template") lo stato arriva dal tag "LU3G - Ponte consenso" sugli eventi iubenda_*. L\'API JavaScript di Iubenda non espone lo stato per finalità, quindi si leggono i segnali che Iubenda passa a Google.', 'lu3g-conversion-tracker' ) ),
			'custom'       => array( __( 'Adapter personalizzato', 'lu3g-conversion-tracker' ), __( 'Per altri CMP: una funzione window.lu3gCTConsent che restituisce lo stato esplicito (vedi sotto).', 'lu3g-conversion-tracker' ) ),
			'none'         => array( __( 'Nessuno', 'lu3g-conversion-tracker' ), __( 'Senza CMP non c\'è consenso: gli identificatori non vengono mai inviati. Gli eventi sì.', 'lu3g-conversion-tracker' ) ),
		);
		echo '<form class="ct-form" data-save="settings" autocomplete="off"><div class="ct-card"><h3>' . esc_html__( 'Da dove leggere il consenso', 'lu3g-conversion-tracker' ) . '</h3>';
		echo '<p class="ct-help">' . esc_html__( 'Il plugin distingue analytics_storage, ad_storage, ad_user_data e ad_personalization. Gli identificatori cifrati per Ads partono solo con ad_user_data esplicitamente "granted", controllato all\'invio e di nuovo subito prima del push. Il plugin non concede consensi, non mostra banner e non tocca le callback del CMP. Gli eventi senza identificatori partono sempre: è GTM, con Consent Mode, a decidere cosa fanno i tag.', 'lu3g-conversion-tracker' ) . '</p>';
		foreach ( $opts as $v => $o ) {
			printf( '<label class="ct-option"><input type="radio" name="consent" value="%1$s" %2$s><span><strong>%3$s</strong><small>%4$s</small></span></label>', esc_attr( $v ), checked( $g['consent'], $v, false ), esc_html( $o[0] ), esc_html( $o[1] ) );
		}
		echo '<div class="ct-grid" style="margin-top:12px">';
		self::field( 'consent_events', __( 'Eventi del CMP in GTM (regex)', 'lu3g-conversion-tracker' ), $g['consent_events'], __( 'Attivatore del tag "LU3G - Ponte consenso" nel JSON. Iubenda: ^iubenda_. Dopo una modifica riscarica il JSON.', 'lu3g-conversion-tracker' ) );
		echo '</div></div>';
		self::savebar();
		echo '</form>';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Adapter personalizzato', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Da mettere nel sito (es. snippet WPCode) e adattare al CMP. ready deve essere true solo quando il CMP ha caricato la scelta dell\'utente.', 'lu3g-conversion-tracker' ) . '</p>';
		echo '<pre class="ct-code">window.lu3gCTConsent = function () {
  // Esempio: leggi lo stato dal tuo CMP.
  var s = window.MioCMP &amp;&amp; window.MioCMP.getConsent ? window.MioCMP.getConsent() : null;
  if (!s) { return { ready: false }; }
  return {
    ready: true,
    analytics_storage: s.statistiche ? \'granted\' : \'denied\',
    ad_storage: s.marketing ? \'granted\' : \'denied\',
    ad_user_data: s.marketing ? \'granted\' : \'denied\',
    ad_personalization: s.marketing ? \'granted\' : \'denied\'
  };
};</pre></div>';
	}

	private static function tab_gtm( array $g ) {
		$keys    = LU3G_CT_GTM::conversion_keys();
		$events  = LU3G_CT_GTM::form_events();
		$missing = LU3G_CT_GTM::missing();
		echo '<form class="ct-form" data-save="settings" autocomplete="off"><div class="ct-card"><h3>' . esc_html__( 'ID per il container da importare', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Servono solo per generare il JSON: il plugin non manda niente a Google. Salvare qui non aggiorna GTM: dopo ogni modifica a eventi, chiavi o etichette scarica di nuovo il JSON e importalo.', 'lu3g-conversion-tracker' ) . '</p><div class="ct-grid">';
		self::field( 'gtm_ga4_id', __( 'ID di misurazione GA4', 'lu3g-conversion-tracker' ), $g['gtm_ga4_id'], 'G-XXXXXXXXXX' );
		self::field( 'gtm_ads_id', __( 'ID conversione Google Ads', 'lu3g-conversion-tracker' ), $g['gtm_ads_id'], __( 'Solo cifre (AW-123456789 → 123456789).', 'lu3g-conversion-tracker' ) );
		echo '</div><h4>' . esc_html__( 'Etichette delle conversioni Ads, per chiave', 'lu3g-conversion-tracker' ) . '</h4><div class="ct-grid">';
		foreach ( $keys as $k ) {
			self::field( 'gtm_labels[' . $k . ']', $k, $g['gtm_labels'][ $k ] ?? '', __( 'Etichetta (Conversion Label). Vuota: questa chiave non va ad Ads.', 'lu3g-conversion-tracker' ) );
		}
		echo '</div></div>';
		self::savebar();
		echo '</form>';

		echo '<div class="ct-card"><h3>' . esc_html__( 'Scarica e importa', 'lu3g-conversion-tracker' ) . '</h3>';
		if ( $missing ) {
			echo '<p class="ct-warn">' . esc_html__( 'Mancano ancora: ', 'lu3g-conversion-tracker' ) . esc_html( implode( '; ', $missing ) ) . '. ' . esc_html__( 'Nel JSON restano come SOSTITUISCI.', 'lu3g-conversion-tracker' ) . '</p>';
		}
		/* translators: %s: elenco eventi */
		echo '<p class="ct-help">' . esc_html( sprintf( __( 'Eventi dei form inclusi: %s; più %s.', 'lu3g-conversion-tracker' ), implode( ', ', $events ), $g['contact_event'] ) ) . '</p>';
		echo '<div class="ct-inline"><a class="ct-btn ct-btn--primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lu3g_ct_gtm' ), 'lu3g_ct_dl' ) ) . '">' . esc_html__( 'Scarica il container (JSON)', 'lu3g-conversion-tracker' ) . '</a><a class="ct-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lu3g_ct_tpl' ), 'lu3g_ct_dl' ) ) . '">' . esc_html__( 'Modello del bridge (.tpl)', 'lu3g-conversion-tracker' ) . '</a><a class="ct-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lu3g_ct_tpl_consent' ), 'lu3g_ct_dl' ) ) . '">' . esc_html__( 'Modello del ponte consenso (.tpl)', 'lu3g-conversion-tracker' ) . '</a></div>';
		echo '<ol class="ct-steps">';
		$steps = array(
			__( 'GTM → Amministrazione → Importa container → scegli il file → area di lavoro nuova (consigliato) → opzione "Unisci" → "Rinomina i tag, gli attivatori e le variabili in conflitto". Tutti gli elementi iniziano con "LU3G -".', 'lu3g-conversion-tracker' ),
			__( 'Controlla le variabili "LU3G - GA4 Measurement ID", "LU3G - Ads Conversion ID" e la tabella "LU3G - Ads Conversion Label" (chiave → etichetta).', 'lu3g-conversion-tracker' ),
			__( 'Se il container ha già un Linker conversioni, elimina "LU3G - Linker conversioni". Se ha già un tag che ascolta gli invii dei form (es. Invio modulo automatico di GA4 o uno script personale), toglilo o escludi questi eventi: altrimenti i lead contano due volte.', 'lu3g-conversion-tracker' ),
			__( 'Non usare come principali nello stesso account Ads sia questa conversione diretta sia la stessa conversione importata da GA4: una delle due va come secondaria.', 'lu3g-conversion-tracker' ),
			__( 'In Google Ads: Obiettivi → Impostazioni → "Uso dei dati dei clienti" → attiva le conversioni avanzate (dal 2026 interruttore unico per web e lead). Nel Google tag: "Consenti le funzionalità dei dati forniti dagli utenti".', 'lu3g-conversion-tracker' ),
			__( 'Anteprima (Tag Assistant) → nel sito avvia una sessione di verifica (Diagnostica) → invia un form: in Tag Assistant compare lead_submit con test_mode true e i tag LU3G di produzione NON partono (corretto). Per provare i tag reali usa un form di prova con la modalità di test spenta e filtra il traffico in GA4.', 'lu3g-conversion-tracker' ),
			__( 'Pubblica il container solo dopo la verifica. Il plugin non pubblica niente da solo.', 'lu3g-conversion-tracker' ),
		);
		foreach ( $steps as $s ) {
			echo '<li>' . esc_html( $s ) . '</li>';
		}
		echo '</ol></div>';
		echo '<div class="ct-card"><h3>' . esc_html__( 'Cosa contiene', 'lu3g-conversion-tracker' ) . '</h3><ul class="ct-list">';
		$items = array(
			__( 'Variabili del dataLayer per lu3g.* (event_id, form_name, value, currency, conversion_key, test_mode, contact_type…).', 'lu3g-conversion-tracker' ),
			__( '"LU3G - user_data (hash)": JavaScript che restituisce solo sha256_email_address / sha256_phone_number validi, già calcolati (nessuna Promise); "LU3G - Dati forniti dall\'utente" in modalità Codice la usa.', 'lu3g-conversion-tracker' ),
			__( 'GA4: un tag per gli eventi dei form e uno per i click, solo metadati ordinari (nessun dato utente), page_location senza query string. Il nome evento resta lead_submit: mapparlo su generate_lead va fatto apposta, al posto di questo, non in aggiunta.', 'lu3g-conversion-tracker' ),
			__( 'Google Ads: un tag conversione per le chiavi con etichetta, valore e valuta, ID transazione = event_id (deduplica delle conversioni Ads con lo stesso ID; non vale per GA4), conversioni avanzate con i soli dati di questo evento.', 'lu3g-conversion-tracker' ),
			__( 'Attivatori di produzione con test_mode = false; un evento alternativo va ad Ads solo se ha una chiave con etichetta.', 'lu3g-conversion-tracker' ),
			__( 'Bridge diagnostico (modello personalizzato con addEventCallback): restituisce al plugin event_id, container e stato dei tag GA4/Ads, senza dati personali.', 'lu3g-conversion-tracker' ),
		);
		foreach ( $items as $i ) {
			echo '<li>' . esc_html( $i ) . '</li>';
		}
		echo '</ul></div>';
	}

	private static function tab_diag( array $g ) {
		$active = LU3G_CT_Diagnostics::session_active();
		echo '<div class="ct-card"><h3>' . esc_html__( 'Test locale', 'lu3g-conversion-tracker' ) . '</h3><p class="ct-help">' . esc_html__( 'Dati inventati, calcoli fatti qui nel pannello: nessun push nel dataLayer del sito, nessun invio di form.', 'lu3g-conversion-tracker' ) . '</p>';
		echo '<form class="ct-grid ct-grid--3" data-localtest autocomplete="off">';
		self::field( 't_email', __( 'Email di prova', 'lu3g-conversion-tracker' ), ' Nome.Cognome+Preventivo@gmail.com ' );
		self::field( 't_phone', __( 'Telefono di prova', 'lu3g-conversion-tracker' ), '0039 347 123 4567' );
		self::field( 't_country', __( 'Paese', 'lu3g-conversion-tracker' ), $g['country'] );
		self::field( 't_event', __( 'Nome evento', 'lu3g-conversion-tracker' ), $g['event'] );
		self::field( 't_value', __( 'Valore', 'lu3g-conversion-tracker' ), $g['value'] );
		self::field( 't_currency', __( 'Valuta', 'lu3g-conversion-tracker' ), $g['currency'] );
		echo '</form><div class="ct-inline"><button type="button" class="ct-btn ct-btn--primary" data-run-local>' . esc_html__( 'Calcola', 'lu3g-conversion-tracker' ) . '</button></div><pre class="ct-code" data-local-out hidden></pre></div>';

		echo '<div class="ct-card"><h3>' . esc_html__( 'Sessione di verifica nel sito', 'lu3g-conversion-tracker' ) . '</h3>';
		echo '<p class="ct-help">' . esc_html__( 'Per 30 minuti, solo per te: nelle pagine del sito compare un riquadro con form rilevati, consenso, GTM ed esito di ogni evento; gli eventi hanno test_mode: true. Attenzione: un invio reale esegue le azioni del form (email, webhook…). Usa un form di prova se puoi.', 'lu3g-conversion-tracker' ) . '</p>';
		if ( $active ) {
			/* translators: %s: ora */
			echo '<p class="ct-ok">' . esc_html( sprintf( __( 'Sessione attiva fino alle %s.', 'lu3g-conversion-tracker' ), wp_date( 'H:i', LU3G_CT_Diagnostics::session_until() ) ) ) . '</p>';
			echo '<div class="ct-inline"><a class="ct-btn ct-btn--primary" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Apri il sito', 'lu3g-conversion-tracker' ) . ' ↗</a><button type="button" class="ct-btn" data-op="diag_stop">' . esc_html__( 'Termina la sessione', 'lu3g-conversion-tracker' ) . '</button></div>';
		} else {
			echo '<div class="ct-inline"><button type="button" class="ct-btn ct-btn--primary" data-op="diag_start">' . esc_html__( 'Avvia sessione di verifica (30 minuti)', 'lu3g-conversion-tracker' ) . '</button></div>';
		}
		echo '</div>';

		$res = LU3G_CT_Diagnostics::results();
		echo '<div class="ct-card"><div class="ct-card__head"><h3>' . esc_html__( 'Ultimi esiti', 'lu3g-conversion-tracker' ) . '</h3><span class="ct-inline"><a class="ct-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lu3g_ct_report' ), 'lu3g_ct_dl' ) ) . '">' . esc_html__( 'Esporta report (JSON)', 'lu3g-conversion-tracker' ) . '</a>' . ( $res ? '<button type="button" class="ct-btn" data-op="diag_clear">' . esc_html__( 'Cancella', 'lu3g-conversion-tracker' ) . '</button>' : '' ) . '</span></div>';
		if ( ! $res ) {
			echo '<p class="ct-empty">' . esc_html__( 'Nessun esito: avvia una sessione e invia un form o clicca un contatto nel sito.', 'lu3g-conversion-tracker' ) . '</p></div>';
		} else {
			$names = array(
				'detected'     => __( 'Form rilevato', 'lu3g-conversion-tracker' ),
				'success'      => __( 'Invio riuscito', 'lu3g-conversion-tracker' ),
				'payload'      => __( 'Payload valido', 'lu3g-conversion-tracker' ),
				'ids'          => __( 'Identificatori', 'lu3g-conversion-tracker' ),
				'datalayer'    => __( 'DataLayer', 'lu3g-conversion-tracker' ),
				'gtm'          => __( 'GTM', 'lu3g-conversion-tracker' ),
				'tags'         => __( 'Tag', 'lu3g-conversion-tracker' ),
				'transmission' => __( 'Trasmissione', 'lu3g-conversion-tracker' ),
				'reception'    => __( 'Ricezione Google', 'lu3g-conversion-tracker' ),
				'attribution'  => __( 'Attribuzione Ads', 'lu3g-conversion-tracker' ),
			);
			foreach ( $res as $r ) {
				echo '<details class="ct-formrow"><summary><span class="ct-formrow__name"><strong>' . esc_html( $r['event'] ) . '</strong><small>' . esc_html( wp_date( 'd/m/Y H:i:s', $r['saved'] ) . ' · ' . $r['form_key'] . ' · ' . $r['page_path'] ) . '</small></span><span class="ct-formrow__meta"><code>' . esc_html( $r['event_id'] ) . '</code></span></summary><div class="ct-formrow__body"><ul class="ct-checks">';
				foreach ( $names as $k => $n ) {
					echo '<li><b>' . esc_html( $n ) . '</b> ' . self::status( $r['steps'][ $k ]['state'], $r['steps'][ $k ]['note'] ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito escapato
				}
				echo '</ul><pre class="ct-code">' . esc_html( wp_json_encode( array( 'event' => $r['event'], 'lu3g' => $r['payload'], 'lu3g_user_data' => $r['identifiers'] ? array_fill_keys( $r['identifiers'], '<hash non salvato>' ) : null ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></div></details>';
			}
			echo '</div>';
		}

		echo '<div class="ct-card"><h3>' . esc_html__( 'Verifiche fuori da WordPress', 'lu3g-conversion-tracker' ) . '</h3><ol class="ct-steps">';
		$steps = array(
			__( 'Tag Assistant (tagassistant.google.com): collega il sito, invia un form, controlla che l\'evento arrivi al container giusto e quali tag partono.', 'lu3g-conversion-tracker' ),
			__( 'GA4 → Amministrazione → DebugView: l\'evento con form_name ed event_id (il debug di Tag Assistant lo abilita).', 'lu3g-conversion-tracker' ),
			__( 'Google Ads → Obiettivi → Conversioni → la conversione → Diagnostica: stato dei tag e delle conversioni avanzate (servono alcuni giorni di dati).', 'lu3g-conversion-tracker' ),
			__( 'Questa pagina non può attestare l\'elaborazione nei server di Google né il matching degli hash: una richiesta partita non garantisce l\'attribuzione.', 'lu3g-conversion-tracker' ),
		);
		foreach ( $steps as $s ) {
			echo '<li>' . esc_html( $s ) . '</li>';
		}
		echo '</ol></div>';
	}

	/* ================================================================ azioni */

	public static function ajax() {
		check_ajax_referer( 'lu3g_ct', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Non hai i permessi per questa operazione.', 'lu3g-conversion-tracker' ) ), 403 );
		}
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$data = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validato campo per campo
		$data = is_array( $data ) ? $data : array();

		switch ( $op ) {
			case 'settings':
				$errors = LU3G_CT_Settings::save( $data );
				if ( $errors ) {
					wp_send_json_error(
						array(
							'message' => __( 'Alcuni campi non sono validi e non sono stati salvati.', 'lu3g-conversion-tracker' ),
							'errors'  => $errors,
						)
					);
				}
				wp_send_json_success( array( 'message' => __( 'Impostazioni salvate.', 'lu3g-conversion-tracker' ), 'reload' => true ) );
				break;

			case 'form':
				$key  = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
				$vals = array_intersect_key( $data, array_flip( LU3G_CT_Settings::form_keys() ) );
				// Validazione prima di scrivere in Elementor: un nome evento non valido non si salva.
				$errors = array();
				if ( '' !== trim( (string) ( $vals['lu3g_ct_event'] ?? '' ) ) ) {
					$err = LU3G_CT_Validate::event_name( trim( (string) $vals['lu3g_ct_event'] ) );
					if ( '' !== $err ) {
						$errors['lu3g_ct_event'] = $err;
					}
				}
				if ( 'fixed' === ( $vals['lu3g_ct_value_mode'] ?? '' ) ) {
					$r = LU3G_CT_Validate::value( $vals['lu3g_ct_value'] ?? '' );
					if ( ! $r['ok'] || null === $r['value'] ) {
						$errors['lu3g_ct_value'] = $r['ok'] ? __( 'Scrivi l\'importo (anche 0).', 'lu3g-conversion-tracker' ) : $r['error'];
					}
				}
				if ( '' !== trim( (string) ( $vals['lu3g_ct_currency'] ?? '' ) ) && ! LU3G_CT_Validate::currency( strtoupper( trim( (string) $vals['lu3g_ct_currency'] ) ) ) ) {
					$errors['lu3g_ct_currency'] = __( 'Valuta non valida (ISO 4217).', 'lu3g-conversion-tracker' );
				}
				if ( '' !== trim( (string) ( $vals['lu3g_ct_country'] ?? '' ) ) && ! LU3G_CT_Validate::country( strtoupper( trim( (string) $vals['lu3g_ct_country'] ) ) ) ) {
					$errors['lu3g_ct_country'] = __( 'Paese non valido (due lettere).', 'lu3g-conversion-tracker' );
				}
				if ( 'key' === ( $vals['lu3g_ct_ads'] ?? '' ) && '' === LU3G_CT_Validate::conversion_key( $vals['lu3g_ct_conversion_key'] ?? '' ) ) {
					$errors['lu3g_ct_conversion_key'] = __( 'Scrivi la chiave della conversione.', 'lu3g-conversion-tracker' );
				}
				if ( $errors ) {
					wp_send_json_error(
						array(
							'message' => __( 'Correggi i campi segnalati: niente è stato salvato.', 'lu3g-conversion-tracker' ),
							'errors'  => $errors,
						)
					);
				}
				foreach ( array( 'lu3g_ct_currency', 'lu3g_ct_country' ) as $k ) {
					if ( isset( $vals[ $k ] ) ) {
						$vals[ $k ] = strtoupper( trim( (string) $vals[ $k ] ) );
					}
				}
				if ( isset( $vals['lu3g_ct_conversion_key'] ) ) {
					$vals['lu3g_ct_conversion_key'] = LU3G_CT_Validate::conversion_key( $vals['lu3g_ct_conversion_key'] );
				}
				$r = LU3G_CT_Forms::write_settings( $key, $vals );
				if ( is_wp_error( $r ) ) {
					wp_send_json_error( array( 'message' => $r->get_error_message() ) );
				}
				wp_send_json_success( array( 'message' => __( 'Salvato in Elementor.', 'lu3g-conversion-tracker' ), 'reload' => true ) );
				break;

			case 'rescan':
				$c = LU3G_CT_Forms::scan();
				/* translators: %d: numero */
				wp_send_json_success( array( 'message' => sprintf( _n( 'Trovato %d form.', 'Trovati %d form.', count( $c['forms'] ), 'lu3g-conversion-tracker' ), count( $c['forms'] ) ), 'reload' => true ) );
				break;

			case 'diag_start':
				LU3G_CT_Diagnostics::start();
				wp_send_json_success( array( 'message' => __( 'Sessione di verifica avviata per 30 minuti.', 'lu3g-conversion-tracker' ), 'reload' => true ) );
				break;

			case 'diag_stop':
				LU3G_CT_Diagnostics::stop();
				wp_send_json_success( array( 'message' => __( 'Sessione terminata.', 'lu3g-conversion-tracker' ), 'reload' => true ) );
				break;

			case 'diag_clear':
				LU3G_CT_Diagnostics::clear();
				wp_send_json_success( array( 'message' => __( 'Esiti cancellati.', 'lu3g-conversion-tracker' ), 'reload' => true ) );
				break;
		}
		wp_send_json_error( array( 'message' => __( 'Azione sconosciuta.', 'lu3g-conversion-tracker' ) ) );
	}

	private static function guard_download() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per questa operazione.', 'lu3g-conversion-tracker' ), 403 );
		}
		check_admin_referer( 'lu3g_ct_dl' );
		nocache_headers();
	}

	public static function download_gtm() {
		self::guard_download();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lu3g-conversion-tracker-gtm-' . gmdate( 'Ymd' ) . '.json"' );
		echo wp_json_encode( LU3G_CT_GTM::container(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON da scaricare
		exit;
	}

	public static function download_tpl() {
		self::guard_download();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lu3g-ct-bridge.tpl"' );
		readfile( LU3G_CT_PATH . 'gtm/lu3g-ct-bridge.tpl' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public static function download_tpl_consent() {
		self::guard_download();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lu3g-ct-consent.tpl"' );
		readfile( LU3G_CT_PATH . 'gtm/lu3g-ct-consent.tpl' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public static function download_report() {
		self::guard_download();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lu3g-tracking-report-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( LU3G_CT_Diagnostics::report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON da scaricare
		exit;
	}
}
