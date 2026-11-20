<?php
/**
 * Impostazioni.
 *
 * Una sola fonte per ogni cosa:
 * - impostazioni globali: opzione "lu3g_ct_settings" (pagina LU3G Tracking);
 * - impostazioni del singolo form: nelle impostazioni del widget Form di Elementor (sezione "Tracciamento LU3G",
 *   chiavi lu3g_ct_*), salvate da Elementor nel documento. La pagina LU3G Tracking le legge e le scrive lì.
 * Precedenza: impostazione del form (se non è "Eredita") → impostazione globale. effective() restituisce anche da dove
 * viene ogni valore, per la diagnostica.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Settings {

	const OPTION = 'lu3g_ct_settings';

	public static function defaults() {
		return array(
			// Form
			'forms_enabled'   => true,
			'event'           => 'lead_submit',
			'value'           => '',          // vuoto = nessun valore
			'currency'        => 'EUR',
			'country'         => 'IT',
			'ec'              => true,        // conversioni avanzate (sempre subordinate al consenso)
			'conversion_key'  => 'lead',
			// Container
			'datalayer'       => 'dataLayer',
			'container_id'    => '',          // GTM-XXXX atteso (solo diagnostica)
			// Consenso
			'consent'         => 'consent_mode', // consent_mode | iubenda | custom | none
			// Test
			'test_mode'       => false,       // test_mode:true su tutti gli eventi
			'admins_test'     => true,        // amministratori collegati: test_mode:true
			// Contatti
			'contacts'        => true,
			'contact_event'   => 'contact_click',
			'contact_exclude' => '',          // selettori CSS esclusi
			'ch_whatsapp'     => true,
			'ch_phone'        => true,
			'ch_email'        => true,
			'key_whatsapp'    => 'contact_whatsapp',
			'key_phone'       => 'contact_phone',
			'key_email'       => 'contact_email',
			'value_whatsapp'  => '',
			'value_phone'     => '',
			'value_email'     => '',
			// Configurazione GTM da scaricare
			'gtm_ga4_id'      => '',
			'gtm_ads_id'      => '',
			'gtm_labels'      => array(),     // conversion_key => etichetta Ads
		);
	}

	public static function get() {
		$s = get_option( self::OPTION, array() );
		$s = array_merge( self::defaults(), is_array( $s ) ? array_intersect_key( $s, self::defaults() ) : array() );
		foreach ( array( 'forms_enabled', 'ec', 'test_mode', 'admins_test', 'contacts', 'ch_whatsapp', 'ch_phone', 'ch_email' ) as $k ) {
			$s[ $k ] = (bool) $s[ $k ];
		}
		$s['gtm_labels'] = is_array( $s['gtm_labels'] ) ? $s['gtm_labels'] : array();
		return $s;
	}

	/**
	 * Salva le impostazioni globali. Restituisce gli errori (campo => messaggio): i campi con errore non si salvano.
	 */
	public static function save( array $in ) {
		$cur    = self::get();
		$errors = array();
		foreach ( array( 'forms_enabled', 'ec', 'test_mode', 'admins_test', 'contacts', 'ch_whatsapp', 'ch_phone', 'ch_email' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$cur[ $k ] = filter_var( $in[ $k ], FILTER_VALIDATE_BOOLEAN );
			}
		}
		foreach ( array( 'event', 'contact_event' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$v   = trim( (string) $in[ $k ] );
				$err = LU3G_CT_Validate::event_name( $v );
				if ( '' !== $err ) {
					$errors[ $k ] = $err;
				} else {
					$cur[ $k ] = $v;
				}
			}
		}
		foreach ( array( 'value', 'value_whatsapp', 'value_phone', 'value_email' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$r = LU3G_CT_Validate::value( $in[ $k ] );
				if ( ! $r['ok'] ) {
					$errors[ $k ] = $r['error'];
				} else {
					$cur[ $k ] = null === $r['value'] ? '' : (string) $r['value'];
				}
			}
		}
		if ( array_key_exists( 'currency', $in ) ) {
			$c = strtoupper( trim( (string) $in['currency'] ) );
			if ( LU3G_CT_Validate::currency( $c ) ) {
				$cur['currency'] = $c;
			} else {
				$errors['currency'] = __( 'Valuta non valida: usa un codice ISO 4217 di tre lettere (es. EUR).', 'lu3g-conversion-tracker' );
			}
		}
		if ( array_key_exists( 'country', $in ) ) {
			$c = strtoupper( trim( (string) $in['country'] ) );
			if ( LU3G_CT_Validate::country( $c ) ) {
				$cur['country'] = $c;
			} else {
				$errors['country'] = __( 'Paese non valido: usa il codice di due lettere (es. IT).', 'lu3g-conversion-tracker' );
			}
		}
		foreach ( array( 'conversion_key', 'key_whatsapp', 'key_phone', 'key_email' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$cur[ $k ] = LU3G_CT_Validate::conversion_key( $in[ $k ] );
			}
		}
		if ( array_key_exists( 'datalayer', $in ) ) {
			$cur['datalayer'] = LU3G_CT_Validate::datalayer_name( $in['datalayer'] );
		}
		if ( array_key_exists( 'container_id', $in ) ) {
			$id = LU3G_CT_Validate::container_id( $in['container_id'] );
			if ( '' === $id && '' !== trim( (string) $in['container_id'] ) ) {
				$errors['container_id'] = __( 'ID del container non valido (formato GTM-XXXXXXX).', 'lu3g-conversion-tracker' );
			} else {
				$cur['container_id'] = $id;
			}
		}
		if ( array_key_exists( 'consent', $in ) ) {
			$cur['consent'] = in_array( $in['consent'], array( 'consent_mode', 'iubenda', 'custom', 'none' ), true ) ? $in['consent'] : 'consent_mode';
		}
		if ( array_key_exists( 'contact_exclude', $in ) ) {
			$cur['contact_exclude'] = substr( sanitize_textarea_field( (string) $in['contact_exclude'] ), 0, 1000 );
		}
		if ( array_key_exists( 'gtm_ga4_id', $in ) ) {
			$v                  = strtoupper( trim( (string) $in['gtm_ga4_id'] ) );
			$cur['gtm_ga4_id'] = preg_match( '/^G-[A-Z0-9]{4,15}$/', $v ) ? $v : '';
			if ( '' === $cur['gtm_ga4_id'] && '' !== $v ) {
				$errors['gtm_ga4_id'] = __( 'ID di misurazione GA4 non valido (formato G-XXXXXXXXXX).', 'lu3g-conversion-tracker' );
			}
		}
		if ( array_key_exists( 'gtm_ads_id', $in ) ) {
			$v                  = preg_replace( '/^AW-/i', '', trim( (string) $in['gtm_ads_id'] ) );
			$cur['gtm_ads_id'] = preg_match( '/^\d{6,15}$/', $v ) ? $v : '';
			if ( '' === $cur['gtm_ads_id'] && '' !== $v ) {
				$errors['gtm_ads_id'] = __( 'ID conversione Google Ads non valido (solo cifre, es. 123456789).', 'lu3g-conversion-tracker' );
			}
		}
		if ( array_key_exists( 'gtm_labels', $in ) && is_array( $in['gtm_labels'] ) ) {
			$labels = array();
			foreach ( $in['gtm_labels'] as $k => $v ) {
				$k = LU3G_CT_Validate::conversion_key( $k );
				$v = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v );
				if ( '' !== $k && '' !== $v ) {
					$labels[ $k ] = $v;
				}
			}
			$cur['gtm_labels'] = $labels;
		}
		update_option( self::OPTION, $cur, false );
		return $errors;
	}

	/* ------------------------------------------------------------------ impostazioni del form */

	/** Chiavi dei controlli nel widget Form (sezione "Tracciamento LU3G"). */
	public static function form_keys() {
		return array( 'lu3g_ct_tracking', 'lu3g_ct_event', 'lu3g_ct_value_mode', 'lu3g_ct_value', 'lu3g_ct_currency', 'lu3g_ct_label', 'lu3g_ct_email_field', 'lu3g_ct_phone_field', 'lu3g_ct_country_field', 'lu3g_ct_country', 'lu3g_ct_ec', 'lu3g_ct_ads', 'lu3g_ct_conversion_key' );
	}

	/**
	 * Configurazione effettiva di un form.
	 *
	 * @param array $form Voce del censimento (LU3G_CT_Forms): settings lu3g_ct_*, campi, nome.
	 * @return array [ 'cfg' => [...], 'origin' => [ chiave => form|global ], 'errors' => [], 'candidates' => [...] ]
	 */
	public static function effective( array $form ) {
		$g      = self::get();
		$s      = isset( $form['ct'] ) && is_array( $form['ct'] ) ? $form['ct'] : array();
		$v      = function ( $k ) use ( $s ) {
			return isset( $s[ $k ] ) ? (string) $s[ $k ] : '';
		};
		$origin = array();
		$errors = array();
		$cfg    = array();

		// Attivo
		$t = $v( 'lu3g_ct_tracking' );
		if ( in_array( $t, array( 'on', 'off' ), true ) ) {
			$cfg['enabled']    = 'on' === $t;
			$origin['enabled'] = 'form';
		} else {
			$cfg['enabled']    = $g['forms_enabled'];
			$origin['enabled'] = 'global';
		}
		if ( 'unsupported' === ( $form['adapter_status'] ?? '' ) ) {
			$cfg['enabled'] = false;
		}

		// Evento
		$e = trim( $v( 'lu3g_ct_event' ) );
		if ( '' !== $e ) {
			$cfg['event']    = $e;
			$origin['event'] = 'form';
		} else {
			$cfg['event']    = $g['event'];
			$origin['event'] = 'global';
		}
		$err = LU3G_CT_Validate::event_name( $cfg['event'] );
		if ( '' !== $err ) {
			$errors['event'] = $err;
		}

		// Valore e valuta
		$mode = $v( 'lu3g_ct_value_mode' );
		if ( 'none' === $mode ) {
			$cfg['value']    = null;
			$origin['value'] = 'form';
		} elseif ( 'fixed' === $mode ) {
			$r               = LU3G_CT_Validate::value( $v( 'lu3g_ct_value' ) );
			$origin['value'] = 'form';
			if ( ! $r['ok'] || null === $r['value'] ) {
				$errors['value'] = $r['ok'] ? __( 'Valore specifico scelto ma vuoto.', 'lu3g-conversion-tracker' ) : $r['error'];
				$cfg['value']    = null;
			} else {
				$cfg['value'] = $r['value'];
			}
		} else {
			$r               = LU3G_CT_Validate::value( $g['value'] );
			$cfg['value']    = $r['value'];
			$origin['value'] = 'global';
		}
		$cur = strtoupper( trim( $v( 'lu3g_ct_currency' ) ) );
		if ( '' !== $cur ) {
			$cfg['currency']    = $cur;
			$origin['currency'] = 'form';
			if ( ! LU3G_CT_Validate::currency( $cur ) ) {
				$errors['currency'] = __( 'Valuta non valida (codice ISO 4217, es. EUR).', 'lu3g-conversion-tracker' );
			}
		} else {
			$cfg['currency']    = $g['currency'];
			$origin['currency'] = 'global';
		}

		// Nome descrittivo: etichetta statica (mai valori compilati)
		$label            = trim( $v( 'lu3g_ct_label' ) );
		$cfg['form_name'] = '' !== $label ? self::label( $label ) : self::label( (string) ( $form['name'] ?? '' ) );
		$origin['form_name'] = '' !== $label ? 'form' : 'global';

		// Paese
		$c = strtoupper( trim( $v( 'lu3g_ct_country' ) ) );
		if ( '' !== $c && LU3G_CT_Validate::country( $c ) ) {
			$cfg['country']    = $c;
			$origin['country'] = 'form';
		} else {
			$cfg['country']    = $g['country'];
			$origin['country'] = 'global';
		}
		$cfg['country_field'] = self::field_id( $v( 'lu3g_ct_country_field' ) );

		// Conversioni avanzate
		$ec = $v( 'lu3g_ct_ec' );
		if ( in_array( $ec, array( 'on', 'off' ), true ) ) {
			$cfg['ec']    = 'on' === $ec;
			$origin['ec'] = 'form';
		} else {
			$cfg['ec']    = $g['ec'];
			$origin['ec'] = 'global';
		}

		// Destinazione Ads
		$ads = $v( 'lu3g_ct_ads' );
		if ( 'off' === $ads ) {
			$cfg['conversion_key']    = '';
			$origin['conversion_key'] = 'form';
		} elseif ( 'key' === $ads ) {
			$cfg['conversion_key']    = LU3G_CT_Validate::conversion_key( $v( 'lu3g_ct_conversion_key' ) );
			$origin['conversion_key'] = 'form';
			if ( '' === $cfg['conversion_key'] ) {
				$errors['conversion_key'] = __( 'Chiave della conversione Ads scelta ma vuota.', 'lu3g-conversion-tracker' );
			}
		} else {
			// Un evento alternativo non eredita la conversione del lead principale.
			$cfg['conversion_key']    = ( $cfg['event'] === $g['event'] ) ? $g['conversion_key'] : '';
			$origin['conversion_key'] = 'global';
		}

		// Campi email / telefono
		$fields            = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$cand              = self::candidates( $fields );
		$cfg['email']      = self::pick( $v( 'lu3g_ct_email_field' ), $cand['email'], $origin, 'email' );
		$cfg['phone']      = self::pick( $v( 'lu3g_ct_phone_field' ), $cand['phone'], $origin, 'phone' );
		if ( '' === $cfg['email'] && count( $cand['email'] ) > 1 && '' === $v( 'lu3g_ct_email_field' ) ) {
			$errors['email'] = __( 'Più campi email: scegli quale usare per le conversioni avanzate (l\'evento parte comunque).', 'lu3g-conversion-tracker' );
		}
		if ( '' === $cfg['phone'] && count( $cand['phone'] ) > 1 && '' === $v( 'lu3g_ct_phone_field' ) ) {
			$errors['phone'] = __( 'Più campi telefono: scegli quale usare per le conversioni avanzate (l\'evento parte comunque).', 'lu3g-conversion-tracker' );
		}

		return array(
			'cfg'        => $cfg,
			'origin'     => $origin,
			'errors'     => $errors,
			'candidates' => $cand,
		);
	}

	/** Etichetta statica: minuscole, numeri e trattino basso (es. "Richiesta preventivo" → richiesta_preventivo). */
	public static function label( $text ) {
		$t = remove_accents( strtolower( (string) $text ) );
		$t = trim( preg_replace( '/[^a-z0-9]+/', '_', $t ), '_' );
		return substr( '' !== $t ? $t : 'form', 0, 60 );
	}

	/**
	 * Campi candidati: email = tipo email o id/etichetta da email; telefono = tipo tel o id/etichetta da telefono.
	 * Mai campi di testo libero lungo, file, password, ecc.
	 */
	public static function candidates( array $fields ) {
		$out = array( 'email' => array(), 'phone' => array() );
		foreach ( $fields as $f ) {
			$type = (string) ( $f['type'] ?? 'text' );
			$id   = (string) ( $f['id'] ?? '' );
			$hint = strtolower( $id . ' ' . ( $f['label'] ?? '' ) );
			if ( in_array( $type, array( 'textarea', 'upload', 'password', 'html', 'hidden', 'step', 'honeypot', 'recaptcha', 'recaptcha_v3', 'acceptance', 'checkbox', 'radio', 'select', 'date', 'time', 'number' ), true ) ) {
				continue;
			}
			if ( 'email' === $type || ( 'text' === $type && preg_match( '/\be-?mail\b|posta/', $hint ) ) ) {
				$out['email'][] = $id;
			} elseif ( 'tel' === $type || ( 'text' === $type && preg_match( '/tel|phone|cell|mobile|whatsapp/', $hint ) ) ) {
				$out['phone'][] = $id;
			}
		}
		return $out;
	}

	/** ID di un campo di Elementor (lettere, numeri, trattino e trattino basso, maiuscole comprese). */
	public static function field_id( $id ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id ), 0, 64 );
	}

	/** Campo scelto: mappatura esplicita > unico candidato; "__none" = non usare; più candidati = nessuno. */
	private static function pick( $mapped, array $cand, array &$origin, $key ) {
		if ( '__none' === $mapped ) {
			$origin[ $key ] = 'form';
			return '';
		}
		if ( '' !== $mapped ) {
			$origin[ $key ] = 'form';
			return self::field_id( $mapped );
		}
		$origin[ $key ] = 'auto';
		return 1 === count( $cand ) ? $cand[0] : '';
	}
}
