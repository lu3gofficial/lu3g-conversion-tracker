<?php
/**
 * Configurazione di Google Tag Manager da importare (Amministrazione → Importa container → Unisci).
 *
 * Per il container completo (export del sito + LU3G, sicuro anche con Sovrascrivi) vedi LU3G_CT_GTM_Merge.
 *
 * Generata con gli ID impostati nel pannello (GA4, ID conversione Ads, etichette per chiave di conversione) e con gli
 * eventi effettivi dei form. Contiene solo elementi con il prefisso "LU3G -": non tocca gli altri tag del container.
 * - Variabili del dataLayer per lu3g.* e lu3g_user_data; "LU3G - user_data (hash)" restituisce solo chiavi sha256_*
 *   valide (64 caratteri esadecimali) o undefined; variabile Dati forniti dall'utente in modalità Codice.
 * - Tag GA4 per i form e per i click (solo metadati ordinari: niente email, telefono o hash; page_location senza query).
 * - Un tag conversione Google Ads: ID e etichetta per chiave di conversione (tabella di ricerca), valore, valuta,
 *   ID transazione = event_id, conversioni avanzate con la variabile dei dati utente. I dati utente vanno solo a questo
 *   tag, non a un Google tag condiviso.
 * - Linker conversioni, bridge diagnostico (modello personalizzato gtm/lu3g-ct-bridge.tpl).
 * - Ponte del consenso (gtm/lu3g-ct-consent.tpl, 1.1.0): sugli eventi del CMP scrive nella pagina lo stato dei segnali
 *   di consenso, per i CMP che lavorano dentro GTM (es. Iubenda con googleConsentMode "template").
 * - Attivatori di produzione con test_mode = false: gli eventi di test non arrivano ai tag.
 * Formato: exportFormatVersion 2, come gli export di GTM. Va verificato in Anteprima prima di pubblicare.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_GTM {

	const ACCOUNT   = '6000000000';
	const CONTAINER = '100000000';
	const ALL_PAGES = '2147479553';

	/** Eventi dei form (globale + alternativi dei form attivi). */
	public static function form_events() {
		$g      = LU3G_CT_Settings::get();
		$events = array( $g['event'] );
		foreach ( LU3G_CT_Forms::all() as $f ) {
			$e = LU3G_CT_Settings::effective( $f );
			if ( $e['cfg']['enabled'] && empty( $e['errors']['event'] ) ) {
				$events[] = $e['cfg']['event'];
			}
		}
		return array_values( array_unique( array_filter( $events ) ) );
	}

	/** Chiavi di conversione in uso (form + canali contatto). */
	public static function conversion_keys() {
		$g    = LU3G_CT_Settings::get();
		$keys = array( $g['conversion_key'], $g['key_whatsapp'], $g['key_phone'], $g['key_email'] );
		foreach ( LU3G_CT_Settings::active_channels() as $c ) {
			$keys[] = $c['key'];
		}
		foreach ( LU3G_CT_Forms::all() as $f ) {
			$e      = LU3G_CT_Settings::effective( $f );
			$keys[] = $e['cfg']['conversion_key'];
		}
		return array_values( array_unique( array_filter( $keys ) ) );
	}

	public static function container() {
		$g        = LU3G_CT_Settings::get();
		$events   = self::form_events();
		$contact  = $g['contact_event'];
		$all      = array_values( array_unique( array_merge( $events, array( $contact ) ) ) );
		$labels   = $g['gtm_labels'];
		$with_lbl = array_values( array_intersect( self::conversion_keys(), array_keys( array_filter( $labels ) ) ) );
		if ( ! $with_lbl ) {
			// Nessuna etichetta ancora: tag Ads con tutte le chiavi ed etichette SOSTITUISCI_… da compilare dopo l'importazione.
			$with_lbl = self::conversion_keys();
		}
		$re       = function ( array $list ) {
			return '^(' . implode( '|', array_map( 'preg_quote', $list ) ) . ')$';
		};

		$ids = array(
			'v' => 0,
			't' => 0,
			'r' => 0,
		);
		$id  = function ( $k ) use ( &$ids ) {
			return (string) ( ++$ids[ $k ] );
		};

		$variables = array();
		$var       = function ( $name, $type, array $params ) use ( &$variables, $id ) {
			$variables[] = array(
				'accountId'   => self::ACCOUNT,
				'containerId' => self::CONTAINER,
				'variableId'  => $id( 'v' ),
				'name'        => $name,
				'type'        => $type,
				'parameter'   => $params,
			);
		};
		$dlv = function ( $name, $path ) use ( $var ) {
			$var(
				$name,
				'v',
				array(
					array( 'type' => 'INTEGER', 'key' => 'dataLayerVersion', 'value' => '2' ),
					array( 'type' => 'BOOLEAN', 'key' => 'setDefaultValue', 'value' => 'false' ),
					array( 'type' => 'TEMPLATE', 'key' => 'name', 'value' => $path ),
				)
			);
		};
		foreach ( array( 'schema_version', 'event_id', 'source', 'form_key', 'form_instance_id', 'form_name', 'page_id', 'page_path', 'value', 'currency', 'conversion_key', 'test_mode', 'contact_type', 'contact_id', 'placement' ) as $k ) {
			$dlv( 'LU3G - ' . $k, 'lu3g.' . $k );
		}
		$dlv( 'LU3G - lu3g_user_data', 'lu3g_user_data' );
		$var(
			'LU3G - user_data (hash)',
			'jsm',
			array(
				array(
					'type'  => 'TEMPLATE',
					'key'   => 'javascript',
					'value' => "function () {\n  // Solo chiavi sha256_* già calcolate dal plugin (64 caratteri esadecimali); altrimenti undefined.\n  var u = {{LU3G - lu3g_user_data}};\n  if (!u || typeof u !== 'object') { return undefined; }\n  var o = {};\n  if (/^[0-9a-f]{64}$/.test(u.sha256_email_address)) { o.sha256_email_address = u.sha256_email_address; }\n  if (/^[0-9a-f]{64}$/.test(u.sha256_phone_number)) { o.sha256_phone_number = u.sha256_phone_number; }\n  return (o.sha256_email_address || o.sha256_phone_number) ? o : undefined;\n}",
				),
			)
		);
		$var(
			'LU3G - Dati forniti dall\'utente',
			'awec',
			array(
				array( 'type' => 'TEMPLATE', 'key' => 'mode', 'value' => 'CODE' ),
				array( 'type' => 'TEMPLATE', 'key' => 'dataSource', 'value' => '{{LU3G - user_data (hash)}}' ),
			)
		);
		$var(
			'LU3G - page_location pulita',
			'jsm',
			array(
				array(
					'type'  => 'TEMPLATE',
					'key'   => 'javascript',
					'value' => "function () {\n  // Indirizzo senza query string né frammento (possono contenere dati compilati).\n  return location.origin + location.pathname;\n}",
				),
			)
		);
		$var( 'LU3G - GA4 Measurement ID', 'c', array( array( 'type' => 'TEMPLATE', 'key' => 'value', 'value' => '' !== $g['gtm_ga4_id'] ? $g['gtm_ga4_id'] : 'G-SOSTITUISCI' ) ) );
		$var( 'LU3G - Ads Conversion ID', 'c', array( array( 'type' => 'TEMPLATE', 'key' => 'value', 'value' => '' !== $g['gtm_ads_id'] ? $g['gtm_ads_id'] : 'SOSTITUISCI' ) ) );
		$map = array();
		foreach ( self::conversion_keys() as $k ) {
			$map[] = array(
				'type' => 'MAP',
				'map'  => array(
					array( 'type' => 'TEMPLATE', 'key' => 'key', 'value' => $k ),
					array( 'type' => 'TEMPLATE', 'key' => 'value', 'value' => $labels[ $k ] ?? 'SOSTITUISCI_ETICHETTA_' . strtoupper( $k ) ),
				),
			);
		}
		$var(
			'LU3G - Ads Conversion Label',
			'smm',
			array(
				array( 'type' => 'BOOLEAN', 'key' => 'setDefaultValue', 'value' => 'false' ),
				array( 'type' => 'TEMPLATE', 'key' => 'input', 'value' => '{{LU3G - conversion_key}}' ),
				array( 'type' => 'LIST', 'key' => 'map', 'list' => $map ),
			)
		);

		// Attivatori
		$triggers = array();
		$trig     = function ( $name, $event_regex, array $filters ) use ( &$triggers, $id ) {
			$t   = array(
				'accountId'         => self::ACCOUNT,
				'containerId'       => self::CONTAINER,
				'triggerId'         => $id( 'r' ),
				'name'              => $name,
				'type'              => 'CUSTOM_EVENT',
				'customEventFilter' => array(
					array(
						'type'      => 'MATCH_REGEX',
						'parameter' => array(
							array( 'type' => 'TEMPLATE', 'key' => 'arg0', 'value' => '{{_event}}' ),
							array( 'type' => 'TEMPLATE', 'key' => 'arg1', 'value' => $event_regex ),
						),
					),
				),
			);
			if ( $filters ) {
				$t['filter'] = $filters;
			}
			$triggers[] = $t;
			return $t['triggerId'];
		};
		$cond = function ( $type, $var_name, $value ) {
			return array(
				'type'      => $type,
				'parameter' => array(
					array( 'type' => 'TEMPLATE', 'key' => 'arg0', 'value' => '{{' . $var_name . '}}' ),
					array( 'type' => 'TEMPLATE', 'key' => 'arg1', 'value' => $value ),
				),
			);
		};
		$prod      = $cond( 'EQUALS', 'LU3G - test_mode', 'false' );
		$t_forms   = $trig( 'LU3G - eventi form (produzione)', $re( $events ), array( $prod ) );
		$t_contact = $trig( 'LU3G - ' . $contact . ' (produzione)', $re( array( $contact ) ), array( $prod ) );
		$t_ads     = $with_lbl ? $trig( 'LU3G - conversioni Google Ads (produzione)', $re( $all ), array( $prod, $cond( 'MATCH_REGEX', 'LU3G - conversion_key', $re( $with_lbl ) ) ) ) : null;
		$t_bridge  = $trig( 'LU3G - tutti gli eventi (bridge diagnostico)', $re( $all ), array() );
		$t_consent = $trig( 'LU3G - eventi del CMP (ponte consenso)', $g['consent_events'], array() );

		// Tag
		$tags = array();
		$tag  = function ( $name, $type, array $params, array $fire, $role = '', array $extra = array() ) use ( &$tags, $id ) {
			$t = array(
				'accountId'        => self::ACCOUNT,
				'containerId'      => self::CONTAINER,
				'tagId'            => $id( 't' ),
				'name'             => $name,
				'type'             => $type,
				'parameter'        => $params,
				'firingTriggerId'  => $fire,
				'tagFiringOption'  => 'ONCE_PER_EVENT',
				'consentSettings'  => array( 'consentStatus' => 'NOT_SET' ),
			);
			if ( '' !== $role ) {
				$t['monitoringMetadata']           = array(
					'type' => 'MAP',
					'map'  => array( array( 'type' => 'TEMPLATE', 'key' => 'lu3g_role', 'value' => $role ) ),
				);
				$t['monitoringMetadataTagNameKey'] = 'name';
			}
			$tags[] = array_merge( $t, $extra );
		};
		// Consenso richiesto per i tag che non gestiscono Consent Mode da soli (HTML personalizzato, es. Meta).
		$needs_ads_consent = array(
			'consentSettings' => array(
				'consentStatus' => 'NEEDED',
				'consentType'   => array(
					'type' => 'LIST',
					'list' => array( array( 'type' => 'TEMPLATE', 'value' => 'ad_storage' ) ),
				),
			),
		);
		$html = function ( $code ) {
			return array(
				array( 'type' => 'TEMPLATE', 'key' => 'html', 'value' => $code ),
				array( 'type' => 'BOOLEAN', 'key' => 'supportDocumentWrite', 'value' => 'false' ),
			);
		};
		$table = function ( array $pairs ) {
			$list = array();
			foreach ( $pairs as $p => $v ) {
				$list[] = array(
					'type' => 'MAP',
					'map'  => array(
						array( 'type' => 'TEMPLATE', 'key' => 'parameter', 'value' => $p ),
						array( 'type' => 'TEMPLATE', 'key' => 'parameterValue', 'value' => $v ),
					),
				);
			}
			return array( 'type' => 'LIST', 'key' => 'eventSettingsTable', 'list' => $list );
		};
		$tag(
			'LU3G - GA4 - eventi form',
			'gaawe',
			array(
				array( 'type' => 'TEMPLATE', 'key' => 'eventName', 'value' => '{{Event}}' ),
				array( 'type' => 'TEMPLATE', 'key' => 'measurementIdOverride', 'value' => '{{LU3G - GA4 Measurement ID}}' ),
				$table(
					array(
						'event_id'         => '{{LU3G - event_id}}',
						'form_name'        => '{{LU3G - form_name}}',
						'form_key'         => '{{LU3G - form_key}}',
						'value'            => '{{LU3G - value}}',
						'currency'         => '{{LU3G - currency}}',
						'conversion_key'   => '{{LU3G - conversion_key}}',
						'page_location'    => '{{LU3G - page_location pulita}}',
						'lu3g_schema'      => '{{LU3G - schema_version}}',
					)
				),
			),
			array( $t_forms ),
			'ga4'
		);
		$tag(
			'LU3G - GA4 - ' . $contact,
			'gaawe',
			array(
				array( 'type' => 'TEMPLATE', 'key' => 'eventName', 'value' => '{{Event}}' ),
				array( 'type' => 'TEMPLATE', 'key' => 'measurementIdOverride', 'value' => '{{LU3G - GA4 Measurement ID}}' ),
				$table(
					array(
						'event_id'      => '{{LU3G - event_id}}',
						'contact_type'  => '{{LU3G - contact_type}}',
						'contact_id'    => '{{LU3G - contact_id}}',
						'placement'     => '{{LU3G - placement}}',
						'page_location' => '{{LU3G - page_location pulita}}',
					)
				),
			),
			array( $t_contact ),
			'ga4'
		);
		if ( $t_ads ) {
			$tag(
				'LU3G - Google Ads - conversioni',
				'awct',
				array(
					array( 'type' => 'TEMPLATE', 'key' => 'conversionId', 'value' => '{{LU3G - Ads Conversion ID}}' ),
					array( 'type' => 'TEMPLATE', 'key' => 'conversionLabel', 'value' => '{{LU3G - Ads Conversion Label}}' ),
					array( 'type' => 'TEMPLATE', 'key' => 'conversionValue', 'value' => '{{LU3G - value}}' ),
					array( 'type' => 'TEMPLATE', 'key' => 'currencyCode', 'value' => '{{LU3G - currency}}' ),
					array( 'type' => 'TEMPLATE', 'key' => 'orderId', 'value' => '{{LU3G - event_id}}' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableEnhancedConversion', 'value' => 'true' ),
					array( 'type' => 'TEMPLATE', 'key' => 'cssProvidedEnhancedConversionValue', 'value' => '{{LU3G - Dati forniti dall\'utente}}' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableConversionLinker', 'value' => 'true' ),
					array( 'type' => 'TEMPLATE', 'key' => 'conversionCookiePrefix', 'value' => '_gcl' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableProductReporting', 'value' => 'false' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableShippingData', 'value' => 'false' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableNewCustomerReporting', 'value' => 'false' ),
					array( 'type' => 'BOOLEAN', 'key' => 'rdp', 'value' => 'false' ),
				),
				array( $t_ads ),
				'ads'
			);
		}
		// Meta: solo se c'è l'ID del pixel. Partono solo con il consenso marketing (ad_storage).
		if ( '' !== $g['meta_pixel_id'] ) {
			$setup = array();
			if ( $g['meta_base'] ) {
				$tag(
					'LU3G - Meta - Pixel base',
					'html',
					// Una volta per evento, con un controllo nella pagina che lo esegue una sola volta: con "una volta per
					// pagina" GTM considerava il tag già usato quando al caricamento era bloccato dal consenso, e non lo
					// riprovava più all'evento del banner (visto su un sito reale con il modello Iubenda).
					$html( "<script>\nif (!window.__lu3gMetaPixel) {\n  window.__lu3gMetaPixel = true;\n  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\n  fbq('init', '" . $g['meta_pixel_id'] . "');\n  fbq('track', 'PageView');\n}\n</script>" ),
					// Tutte le pagine + eventi del banner: se il consenso arriva dopo il caricamento, il pixel parte allora.
					array( self::ALL_PAGES, $t_consent ),
					'',
					$needs_ads_consent
				);
				$setup = array( 'setupTag' => array( array( 'tagName' => 'LU3G - Meta - Pixel base', 'stopOnSetupFailure' => false ) ) );
			}
			$tag(
				'LU3G - Meta - Lead',
				'html',
				$html( "<script>\nif (window.fbq) { fbq('track', 'Lead', {}, { eventID: '{{LU3G - event_id}}' }); }\n</script>" ),
				array( $t_forms ),
				'meta',
				array_merge( $needs_ads_consent, $setup )
			);
			$tag(
				'LU3G - Meta - Contact',
				'html',
				$html( "<script>\nif (window.fbq) { fbq('track', 'Contact', { content_category: '{{LU3G - contact_type}}' }, { eventID: '{{LU3G - event_id}}' }); }\n</script>" ),
				array( $t_contact ),
				'meta',
				array_merge( $needs_ads_consent, $setup )
			);
		}
		if ( ! $g['has_linker'] ) {
			$tag(
				'LU3G - Linker conversioni',
				'gclidw',
				array(
					array( 'type' => 'BOOLEAN', 'key' => 'enableCrossDomain', 'value' => 'false' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableUrlPassthrough', 'value' => 'false' ),
					array( 'type' => 'BOOLEAN', 'key' => 'enableCookieOverrides', 'value' => 'false' ),
				),
				array( self::ALL_PAGES )
			);
		}
		$tag( 'LU3G - Bridge diagnostico', 'cvt_' . self::CONTAINER . '_1', array(), array( $t_bridge ) );
		// Ponte del consenso: scrive nella pagina lo stato dei 4 segnali dopo ogni evento del CMP (es. modello Iubenda).
		$tag( 'LU3G - Ponte consenso', 'cvt_' . self::CONTAINER . '_2', array(), array( $t_consent ) );

		$tpl  = (string) file_get_contents( LU3G_CT_PATH . 'gtm/lu3g-ct-bridge.tpl' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del plugin
		$tpl2 = (string) file_get_contents( LU3G_CT_PATH . 'gtm/lu3g-ct-consent.tpl' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del plugin

		return array(
			'exportFormatVersion' => 2,
			'exportTime'          => gmdate( 'Y-m-d H:i:s' ),
			'containerVersion'    => array(
				'path'               => 'accounts/' . self::ACCOUNT . '/containers/' . self::CONTAINER . '/versions/0',
				'accountId'          => self::ACCOUNT,
				'containerId'        => self::CONTAINER,
				'containerVersionId' => '0',
				'container'          => array(
					'path'         => 'accounts/' . self::ACCOUNT . '/containers/' . self::CONTAINER,
					'accountId'    => self::ACCOUNT,
					'containerId'  => self::CONTAINER,
					// Il nome compare nella schermata di importazione di GTM: ricorda di usare Unisci.
					'name'         => 'SOLO LU3G - importa con UNISCI, non Sovrascrivi',
					'publicId'     => '' !== $g['container_id'] ? $g['container_id'] : 'GTM-XXXXXXX',
					'usageContext' => array( 'WEB' ),
				),
				'tag'                => $tags,
				'trigger'            => $triggers,
				'variable'           => $variables,
				'builtInVariable'    => array(
					array(
						'accountId'   => self::ACCOUNT,
						'containerId' => self::CONTAINER,
						'type'        => 'EVENT',
						'name'        => 'Event',
					),
				),
				'customTemplate'     => array(
					array(
						'accountId'    => self::ACCOUNT,
						'containerId'  => self::CONTAINER,
						'templateId'   => '1',
						'name'         => 'LU3G CT Bridge',
						'templateData' => $tpl,
					),
					array(
						'accountId'    => self::ACCOUNT,
						'containerId'  => self::CONTAINER,
						'templateId'   => '2',
						'name'         => 'LU3G CT Consent',
						'templateData' => $tpl2,
					),
				),
			),
		);
	}

	/** Elementi ancora da sostituire nel JSON (ID mancanti). */
	public static function missing() {
		$g   = LU3G_CT_Settings::get();
		$out = array();
		if ( '' === $g['gtm_ga4_id'] ) {
			$out[] = __( 'ID di misurazione GA4', 'lu3g-conversion-tracker' );
		}
		if ( '' === $g['gtm_ads_id'] ) {
			$out[] = __( 'ID conversione Google Ads', 'lu3g-conversion-tracker' );
		}
		foreach ( self::conversion_keys() as $k ) {
			if ( empty( $g['gtm_labels'][ $k ] ) ) {
				/* translators: %s: chiave di conversione */
				$out[] = sprintf( __( 'Etichetta Ads per "%s" (senza etichetta la chiave resta esclusa da Ads)', 'lu3g-conversion-tracker' ), $k );
			}
		}
		return $out;
	}
}
