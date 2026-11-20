<?php
/**
 * Script nel sito.
 *
 * Carica assets/lu3g-ct-core.js + assets/tracker.js con la configurazione (impostazioni globali e configurazione
 * effettiva di ogni form censito). La libreria dei numeri (assets/vendor/libphonenumber-js) la carica lo script solo
 * nelle pagine dove c'è un form.
 *
 * Non si carica: nell'editor e nell'anteprima di Elementor, nel Customizer, nei feed, nell'area amministrativa.
 * WP Rocket: gli script vengono esclusi dal "ritarda JavaScript", altrimenti la raccolta partirebbe solo alla prima
 * interazione (un click su WhatsApp come primo gesto andrebbe perso).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'rocket_exclusions' ) );
		add_filter( 'rocket_exclude_js', array( __CLASS__, 'rocket_exclusions' ) );
	}

	public static function rocket_exclusions( $list ) {
		$list   = is_array( $list ) ? $list : array();
		$list[] = 'lu3g-conversion-tracker/assets/';
		$list[] = 'lu3gCTConfig';
		return $list;
	}

	/** Pagine escluse dal tracciamento. */
	public static function skip() {
		if ( is_admin() || is_feed() || is_customize_preview() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola lettura del parametro dell'anteprima
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) && is_preview() ) {
			return true;
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return true;
		}
		return (bool) apply_filters( 'lu3g_ct_skip', false );
	}

	public static function enqueue() {
		if ( self::skip() ) {
			return;
		}
		wp_enqueue_script( 'lu3g-ct-core', LU3G_CT_URL . 'assets/lu3g-ct-core.js', array(), LU3G_CT_VERSION, true );
		wp_enqueue_script( 'lu3g-ct-tracker', LU3G_CT_URL . 'assets/tracker.js', array( 'lu3g-ct-core' ), LU3G_CT_VERSION, true );
		wp_add_inline_script( 'lu3g-ct-core', 'window.lu3gCTConfig=' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/** Configurazione per lo script. Nessun dato personale: solo impostazioni e chiavi dei form. */
	public static function config() {
		$g     = LU3G_CT_Settings::get();
		$forms = array();
		$by_el = array();
		$dupe  = array();
		foreach ( LU3G_CT_Forms::all() as $key => $f ) {
			$eff = LU3G_CT_Settings::effective( $f );
			$c   = $eff['cfg'];
			$row = array(
				'on' => $c['enabled'] && ! isset( $eff['errors']['event'] ),
				'e'  => $c['event'],
				'n'  => $c['form_name'],
				'v'  => $c['value'],
				'c'  => $c['currency'],
				'k'  => $c['conversion_key'],
				'ec' => $c['ec'],
				'm'  => $c['email'],
				'p'  => $c['phone'],
				'cf' => $c['country_field'],
				'co' => $c['country'],
			);
			if ( isset( $eff['errors']['event'] ) ) {
				$row['err'] = 'event';
			}
			if ( 'supported' !== $f['adapter_status'] ) {
				$row['on']    = false;
				$row['unsup'] = 1;
			}
			$forms[ $key ] = $row;
			// Ripiego per i widget globali: stesso widget, documento diverso. Solo se l'id del widget è unico.
			if ( isset( $by_el[ $f['element_id'] ] ) ) {
				$dupe[ $f['element_id'] ] = true;
			}
			$by_el[ $f['element_id'] ] = $key;
		}
		foreach ( array_keys( $dupe ) as $id ) {
			unset( $by_el[ $id ] );
		}

		$gv     = LU3G_CT_Validate::value( $g['value'] );
		$admin  = is_user_logged_in() && current_user_can( 'manage_options' );
		$diag   = LU3G_CT_Diagnostics::session_active();
		$values = array();
		foreach ( array( 'whatsapp', 'phone', 'email' ) as $ch ) {
			$r               = LU3G_CT_Validate::value( $g[ 'value_' . $ch ] );
			$values[ $ch ] = $r['value'];
		}

		$cfg = array(
			'version'  => LU3G_CT_VERSION,
			'dl'       => $g['datalayer'],
			'gtm'      => $g['container_id'],
			'test'     => $g['test_mode'] || ( $admin && $g['admins_test'] ) || $diag,
			'consent'  => $g['consent'],
			'pageId'   => (int) get_queried_object_id(),
			'lib'      => LU3G_CT_URL . 'assets/vendor/libphonenumber-js/libphonenumber-min.js?ver=1.13.15',
			'defaults' => array(
				'on' => $g['forms_enabled'] && '' === LU3G_CT_Validate::event_name( $g['event'] ),
				'e'  => $g['event'],
				'v'  => $gv['value'],
				'c'  => $g['currency'],
				'k'  => $g['conversion_key'],
				'ec' => $g['ec'],
				'co' => $g['country'],
			),
			'forms'    => $forms,
			'byEl'     => $by_el,
			'contacts' => array(
				'on'      => $g['contacts'],
				'e'       => '' === LU3G_CT_Validate::event_name( $g['contact_event'] ) ? $g['contact_event'] : 'contact_click',
				'exclude' => $g['contact_exclude'],
				'ch'      => array(
					'whatsapp' => array( 'on' => $g['ch_whatsapp'], 'k' => $g['key_whatsapp'], 'v' => $values['whatsapp'] ),
					'phone'    => array( 'on' => $g['ch_phone'], 'k' => $g['key_phone'], 'v' => $values['phone'] ),
					'email'    => array( 'on' => $g['ch_email'], 'k' => $g['key_email'], 'v' => $values['email'] ),
				),
				'c'       => $g['currency'],
			),
		);
		if ( $diag ) {
			$cfg['diag'] = array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'lu3g_ct_diag' ),
				'until' => LU3G_CT_Diagnostics::session_until(),
			);
		}
		return $cfg;
	}
}
