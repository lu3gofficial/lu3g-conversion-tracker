<?php
/**
 * Diagnostica.
 *
 * Sessione di verifica: la avvia un amministratore dal pannello, dura 30 minuti, vale solo per quell'utente collegato
 * (meta dell'utente, nessun parametro pubblico riutilizzabile). Durante la sessione, nel sito:
 * - gli eventi hanno test_mode:true (in GTM vanno filtrati dai tag di produzione);
 * - un riquadro mostra form rilevati, consenso, GTM ed esito di ogni evento;
 * - gli esiti (senza dati compilati né hash) tornano qui: ultimi 20, cancellati dopo 7 giorni.
 * Il bridge GTM (gtm/lu3g-ct-bridge.tpl) restituisce event_id, container e stato dei tag.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Diagnostics {

	const META    = 'lu3g_ct_diag_until';
	const RESULTS = 'lu3g_ct_diag_results';
	const MINUTES = 30;

	public static function init() {
		add_action( 'wp_ajax_lu3g_ct_diag_record', array( __CLASS__, 'record' ) );
	}

	public static function session_until() {
		return is_user_logged_in() ? (int) get_user_meta( get_current_user_id(), self::META, true ) : 0;
	}

	public static function session_active() {
		return is_user_logged_in() && current_user_can( 'manage_options' ) && self::session_until() > time();
	}

	public static function start() {
		update_user_meta( get_current_user_id(), self::META, time() + self::MINUTES * MINUTE_IN_SECONDS );
	}

	public static function stop() {
		delete_user_meta( get_current_user_id(), self::META );
	}

	/** Risultati salvati, i più recenti per primi (scaduti tolti). */
	public static function results() {
		$r = get_option( self::RESULTS, array() );
		$r = is_array( $r ) ? $r : array();
		$r = array_filter(
			$r,
			function ( $x ) {
				return isset( $x['saved'] ) && $x['saved'] > time() - 7 * DAY_IN_SECONDS;
			}
		);
		return array_values( $r );
	}

	public static function clear() {
		delete_option( self::RESULTS );
	}

	/** AJAX dal sito: esito di un evento della sessione. Autorizzazione reale: utente, permesso, sessione, nonce. */
	public static function record() {
		check_ajax_referer( 'lu3g_ct_diag', 'nonce' );
		if ( ! self::session_active() ) {
			wp_send_json_error( array( 'message' => 'sessione non attiva' ), 403 );
		}
		$raw = isset( $_POST['report'] ) ? json_decode( wp_unslash( $_POST['report'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ripulito sotto, campo per campo
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => 'dati non validi' ), 400 );
		}
		$row = self::clean( $raw );
		$all = array_filter(
			self::results(),
			function ( $x ) use ( $row ) {
				return $x['event_id'] !== $row['event_id'];
			}
		);
		array_unshift( $all, $row );
		update_option( self::RESULTS, array_slice( array_values( $all ), 0, 20 ), false );
		wp_send_json_success();
	}

	/** Solo campi previsti, testi corti; il payload passa da una lista consentita (nessun dato compilato, nessun hash). */
	private static function clean( array $r ) {
		$states  = array( 'ok', 'error', 'consent', 'unverified', 'na' );
		$steps   = array();
		$allowed = array( 'detected', 'success', 'payload', 'ids', 'datalayer', 'gtm', 'tags', 'transmission', 'reception', 'attribution' );
		foreach ( $allowed as $k ) {
			$s           = isset( $r['steps'][ $k ] ) && is_array( $r['steps'][ $k ] ) ? $r['steps'][ $k ] : array();
			$steps[ $k ] = array(
				'state' => in_array( $s['state'] ?? '', $states, true ) ? $s['state'] : 'unverified',
				'note'  => mb_substr( sanitize_text_field( (string) ( $s['note'] ?? '' ) ), 0, 300 ),
			);
		}
		$payload = array();
		$keys    = array( 'schema_version', 'event_id', 'source', 'form_key', 'form_instance_id', 'form_name', 'page_id', 'page_path', 'value', 'currency', 'conversion_key', 'test_mode', 'contact_type', 'contact_id', 'placement' );
		foreach ( $keys as $k ) {
			if ( isset( $r['payload'][ $k ] ) && is_scalar( $r['payload'][ $k ] ) ) {
				$payload[ $k ] = is_bool( $r['payload'][ $k ] ) || is_numeric( $r['payload'][ $k ] ) ? $r['payload'][ $k ] : mb_substr( sanitize_text_field( (string) $r['payload'][ $k ] ), 0, 120 );
			}
		}
		$ids = array_values( array_intersect( (array) ( $r['identifiers'] ?? array() ), array( 'sha256_email_address', 'sha256_phone_number' ) ) );
		return array(
			'saved'         => time(),
			'kind'          => 'contact' === ( $r['kind'] ?? '' ) ? 'contact' : 'form',
			'event'         => mb_substr( sanitize_text_field( (string) ( $r['event'] ?? '' ) ), 0, 40 ),
			'event_id'      => mb_substr( preg_replace( '/[^a-f0-9\-]/', '', (string) ( $r['event_id'] ?? '' ) ), 0, 36 ),
			'form_key'      => mb_substr( sanitize_text_field( (string) ( $r['form_key'] ?? '' ) ), 0, 80 ),
			'source'        => mb_substr( sanitize_text_field( (string) ( $r['source'] ?? '' ) ), 0, 80 ),
			'page_path'     => mb_substr( sanitize_text_field( (string) ( $r['page_path'] ?? '' ) ), 0, 200 ),
			'gtm_container' => mb_substr( sanitize_text_field( (string) ( $r['gtm_container'] ?? '' ) ), 0, 20 ),
			'payload'       => $payload,
			'identifiers'   => $ids,
			'steps'         => $steps,
		);
	}

	/** Report esportabile: configurazione ed esiti, senza dati compilati né hash. */
	public static function report() {
		$g = LU3G_CT_Settings::get();
		unset( $g['gtm_labels'] );
		$forms = array();
		foreach ( LU3G_CT_Forms::all() as $key => $f ) {
			$e             = LU3G_CT_Settings::effective( $f );
			$forms[ $key ] = array(
				'nome'      => $f['name'],
				'pagina'    => $f['post_title'],
				'adapter'   => $f['adapter'] . ' (' . $f['adapter_status'] . ')',
				'effettiva' => $e['cfg'],
				'origine'   => $e['origin'],
				'errori'    => $e['errors'],
			);
		}
		return array(
			'generato'       => gmdate( 'c' ),
			'plugin'         => LU3G_CT_VERSION,
			'wordpress'      => get_bloginfo( 'version' ),
			'elementor'      => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
			'elementor_pro'  => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
			'libphonenumber' => '1.13.15',
			'impostazioni'   => $g,
			'form'           => $forms,
			'esiti'          => self::results(),
		);
	}
}
