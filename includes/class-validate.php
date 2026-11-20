<?php
/**
 * Validazioni condivise (le stesse regole di assets/lu3g-ct-core.js).
 *
 * - Nomi evento: regole GA4 (massimo 40 caratteri, inizia con una lettera, solo lettere/numeri/trattino basso, niente
 *   nomi o prefissi riservati). Un nome non valido viene segnalato e NON corretto: l'evento di quel form non parte
 *   finché non lo si sistema, perché un nome cambiato in silenzio scollegherebbe i tag di GTM.
 * - Valori: numeri finiti non negativi, zero compreso; virgola italiana accettata; vuoto = nessun valore.
 * - Valute: codici ISO 4217 attivi.
 * - Paesi: ISO 3166-1 alpha-2.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Validate {

	const RESERVED_EVENTS   = array( 'ad_activeview', 'ad_click', 'ad_exposure', 'ad_impression', 'ad_query', 'ad_reward', 'adunit_exposure', 'app_background', 'app_clear_data', 'app_exception', 'app_install', 'app_remove', 'app_store_refund', 'app_store_subscription_cancel', 'app_store_subscription_convert', 'app_store_subscription_renew', 'app_uninstall', 'app_update', 'app_upgrade', 'dynamic_link_app_open', 'dynamic_link_app_update', 'dynamic_link_first_open', 'error', 'firebase_campaign', 'firebase_in_app_message_action', 'firebase_in_app_message_dismiss', 'firebase_in_app_message_impression', 'first_open', 'first_visit', 'in_app_purchase', 'notification_dismiss', 'notification_foreground', 'notification_open', 'notification_receive', 'notification_send', 'os_update', 'screen_view', 'session_start', 'session_start_with_rollout', 'user_engagement' );
	const RESERVED_PREFIXES = array( '_', 'firebase_', 'ga_', 'google_', 'gtag.', 'gtm.' );
	const CURRENCIES        = 'AED AFN ALL AMD ANG AOA ARS AUD AWG AZN BAM BBD BDT BGN BHD BIF BMD BND BOB BRL BSD BTN BWP BYN BZD CAD CDF CHF CLP CNY COP CRC CUP CVE CZK DJF DKK DOP DZD EGP ERN ETB EUR FJD FKP GBP GEL GHS GIP GMD GNF GTQ GYD HKD HNL HTG HUF IDR ILS INR IQD IRR ISK JMD JOD JPY KES KGS KHR KMF KPW KRW KWD KYD KZT LAK LBP LKR LRD LSL LYD MAD MDL MGA MKD MMK MNT MOP MRU MUR MVR MWK MXN MYR MZN NAD NGN NIO NOK NPR NZD OMR PAB PEN PGK PHP PKR PLN PYG QAR RON RSD RUB RWF SAR SBD SCR SDG SEK SGD SHP SLE SOS SRD SSP STN SVC SYP SZL THB TJS TMT TND TOP TRY TTD TWD TZS UAH UGX USD UYU UZS VES VND VUV WST XAF XCD XCG XOF XPF YER ZAR ZMW ZWG';

	/** @return string '' se valido, altrimenti il motivo in italiano. */
	public static function event_name( $name ) {
		$name = (string) $name;
		if ( '' === $name ) {
			return __( 'Il nome dell\'evento è vuoto.', 'lu3g-conversion-tracker' );
		}
		if ( strlen( $name ) > 40 ) {
			return __( 'Il nome dell\'evento supera i 40 caratteri.', 'lu3g-conversion-tracker' );
		}
		foreach ( self::RESERVED_PREFIXES as $p ) {
			if ( 0 === strpos( strtolower( $name ), $p ) ) {
				/* translators: %s: prefisso */
				return sprintf( __( 'Il nome dell\'evento inizia con un prefisso riservato (%s).', 'lu3g-conversion-tracker' ), $p );
			}
		}
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $name ) ) {
			return __( 'Il nome dell\'evento deve iniziare con una lettera e contenere solo lettere, numeri e trattino basso.', 'lu3g-conversion-tracker' );
		}
		if ( in_array( strtolower( $name ), self::RESERVED_EVENTS, true ) ) {
			return __( 'Il nome dell\'evento è riservato da Google Analytics 4.', 'lu3g-conversion-tracker' );
		}
		return '';
	}

	/**
	 * @return array [ 'ok' => bool, 'value' => float|null, 'error' => string ]  value null = nessun valore.
	 */
	public static function value( $raw ) {
		$s = preg_replace( '/[\s\x{00a0}€]/u', '', (string) $raw );
		if ( '' === $s ) {
			return array( 'ok' => true, 'value' => null, 'error' => '' );
		}
		if ( preg_match( '/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s ) ) {
			$s = str_replace( ',', '.', str_replace( '.', '', $s ) );
		} elseif ( preg_match( '/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s ) ) {
			$s = str_replace( ',', '', $s );
		} else {
			$s = preg_replace( '/,/', '.', $s, 1 );
		}
		if ( ! preg_match( '/^\d+(\.\d+)?$/', $s ) ) {
			return array( 'ok' => false, 'value' => null, 'error' => __( 'Il valore deve essere un numero non negativo (es. 25 o 25,50).', 'lu3g-conversion-tracker' ) );
		}
		$n = (float) $s;
		if ( ! is_finite( $n ) || $n < 0 ) {
			return array( 'ok' => false, 'value' => null, 'error' => __( 'Il valore deve essere un numero non negativo.', 'lu3g-conversion-tracker' ) );
		}
		return array( 'ok' => true, 'value' => round( $n, 2 ), 'error' => '' );
	}

	public static function currency( $code ) {
		return in_array( (string) $code, explode( ' ', self::CURRENCIES ), true );
	}

	public static function country( $code ) {
		return (bool) preg_match( '/^[A-Z]{2}$/', (string) $code );
	}

	/** Chiave logica della conversione (es. lead, contact_whatsapp): minuscole, numeri, trattino basso. */
	public static function conversion_key( $key ) {
		return substr( preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $key ) ), 0, 40 );
	}

	/** ID di un container GTM (GTM-XXXXXXX) oppure ''. */
	public static function container_id( $id ) {
		$id = strtoupper( trim( (string) $id ) );
		return preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $id ) ? $id : '';
	}

	/** Nome del dataLayer: identificatore JavaScript semplice. */
	public static function datalayer_name( $name ) {
		$name = trim( (string) $name );
		return preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]{0,63}$/', $name ) ? $name : 'dataLayer';
	}
}
