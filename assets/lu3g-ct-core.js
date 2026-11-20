/**
 * LU3G Conversion Tracker: funzioni pure (nessun accesso al DOM né al dataLayer).
 *
 * Usate da tracker.js nel sito, dal "Test locale" nel pannello e dai test automatici (tests/core.test.js, con Node).
 * - normalizeEmail: regole Google per le conversioni avanzate (developers.google.com/google-ads/api/docs/conversions/
 *   upload-online, "Prepare data"): spazi iniziali/finali tolti, minuscolo; solo per gmail.com e googlemail.com si
 *   tolgono i punti e il suffisso "+..." dalla parte prima della @. Gli altri domini restano come sono.
 * - normalizePhone: libphonenumber-js (assets/vendor), risultato E.164. Paese: prefisso esplicito del numero, poi i paesi
 *   passati in ordine (campo paese del form, paese del form, paese del sito). Numeri incompleti, multipli, con lettere o
 *   ambigui vengono scartati con un motivo.
 * - sha256Hex: SHA-256 di UTF-8, esadecimale minuscolo (Web Crypto). Senza Web Crypto restituisce null: niente ripieghi.
 * - validateEventName, parseValue, isCurrency: le stesse regole del PHP (includes/class-validate.php).
 * - classifyContactLink: tel:, mailto:, link WhatsApp click-to-chat (host e percorso, non la parola "whatsapp").
 */
( function ( root, factory ) {
	var api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	} else {
		root.LU3GCTCore = api;
	}
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	var SCHEMA_VERSION = '1.0';

	/* ------------------------------------------------------------------ email */

	var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	function normalizeEmail( raw ) {
		if ( typeof raw !== 'string' ) {
			return { value: null, reason: 'vuota' };
		}
		var v = raw.replace( /^[\s ]+|[\s ]+$/g, '' ).toLowerCase();
		if ( v === '' ) {
			return { value: null, reason: 'vuota' };
		}
		if ( ! EMAIL_RE.test( v ) || v.split( '@' ).length !== 2 ) {
			return { value: null, reason: 'formato non valido' };
		}
		var at = v.lastIndexOf( '@' );
		var local = v.slice( 0, at );
		var domain = v.slice( at + 1 );
		if ( domain === 'gmail.com' || domain === 'googlemail.com' ) {
			var plus = local.indexOf( '+' );
			if ( plus !== -1 ) {
				local = local.slice( 0, plus );
			}
			local = local.replace( /\./g, '' );
			if ( local === '' ) {
				return { value: null, reason: 'formato non valido' };
			}
		}
		return { value: local + '@' + domain, reason: '' };
	}

	/* ------------------------------------------------------------------ telefono */

	var EXT_RE = /\s*(?:ext\.?|est\.?|int\.?|interno|x|#)\s*(\d{1,6})\s*$/i;

	/**
	 * @param {string} raw         Valore del campo.
	 * @param {Array}  countries   Paesi ISO 3166-1 alpha-2 in ordine di priorità (i non validi si saltano).
	 * @param {object} lib         libphonenumber-js (window.libphonenumber).
	 * @return {{value: string|null, ext: string, country: string, reason: string}}
	 */
	function normalizePhone( raw, countries, lib ) {
		var out = { value: null, ext: '', country: '', reason: '' };
		if ( typeof raw !== 'string' || raw.replace( /\s/g, '' ) === '' ) {
			out.reason = 'vuoto';
			return out;
		}
		if ( ! lib || typeof lib.parsePhoneNumberFromString !== 'function' ) {
			out.reason = 'libreria dei numeri non caricata';
			return out;
		}
		var s = raw.trim();
		var m = s.match( EXT_RE );
		if ( m ) {
			out.ext = m[ 1 ];
			s = s.slice( 0, m.index );
		}
		// Più numeri nello stesso campo: non si sceglie a caso.
		var parts = s.split( /\s*(?:[,;\/|]|\s(?:e|o|oppure|or|and)\s)\s*/i ).filter( function ( p ) {
			return p.replace( /\D/g, '' ).length >= 6;
		} );
		if ( parts.length > 1 ) {
			out.reason = 'più numeri nello stesso campo';
			return out;
		}
		if ( /[a-z]/i.test( s ) ) {
			out.reason = 'contiene lettere';
			return out;
		}
		if ( ! /^[\s\d+().\-\/]+$/.test( s ) ) {
			out.reason = 'caratteri non ammessi';
			return out;
		}
		var def = '';
		for ( var i = 0; i < ( countries || [] ).length; i++ ) {
			var c = String( countries[ i ] || '' ).toUpperCase();
			if ( /^[A-Z]{2}$/.test( c ) && ( ! lib.isSupportedCountry || lib.isSupportedCountry( c ) ) ) {
				def = c;
				break;
			}
		}
		var explicit = /^\s*\+/.test( s );
		var p;
		try {
			p = def ? lib.parsePhoneNumberFromString( s, def ) : lib.parsePhoneNumberFromString( s );
		} catch ( e ) {
			p = null;
		}
		if ( ! p ) {
			out.reason = explicit || def ? 'numero non riconosciuto' : 'manca il paese';
			return out;
		}
		if ( ! p.isValid() ) {
			out.reason = 'numero incompleto o non valido';
			return out;
		}
		// Cifre iniziali uguali al prefisso del paese, senza "+" né "00": se anche la lettura nazionale è valida, è ambiguo.
		var digits = s.replace( /\D/g, '' );
		if ( ! explicit && def && lib.getCountryCallingCode ) {
			var cc = String( lib.getCountryCallingCode( def ) );
			var strippedAsCc = digits.indexOf( cc ) === 0 && p.nationalNumber === digits.slice( cc.length ) && p.countryCallingCode === cc;
			if ( strippedAsCc && digits.indexOf( '00' ) !== 0 ) {
				var national = null;
				try {
					national = lib.parsePhoneNumberFromString( '+' + cc + digits );
				} catch ( e2 ) {
					national = null;
				}
				if ( national && national.isValid() ) {
					out.reason = 'ambiguo: le prime cifre potrebbero essere il prefisso ' + cc;
					return out;
				}
			}
		}
		out.value = p.number;
		out.country = p.country || '';
		return out;
	}

	/* ------------------------------------------------------------------ hash */

	function hasSubtle() {
		var c = typeof crypto !== 'undefined' ? crypto : null;
		return !! ( c && c.subtle && typeof c.subtle.digest === 'function' && typeof TextEncoder !== 'undefined' );
	}

	/** SHA-256 in esadecimale minuscolo (64 caratteri), oppure null se Web Crypto non c'è o fallisce. */
	function sha256Hex( text ) {
		if ( ! hasSubtle() || typeof text !== 'string' || text === '' ) {
			return Promise.resolve( null );
		}
		return crypto.subtle.digest( 'SHA-256', new TextEncoder().encode( text ) ).then( function ( buf ) {
			var b = new Uint8Array( buf );
			var hex = '';
			for ( var i = 0; i < b.length; i++ ) {
				hex += ( b[ i ] < 16 ? '0' : '' ) + b[ i ].toString( 16 );
			}
			return hex;
		}, function () {
			return null;
		} );
	}

	/* ------------------------------------------------------------------ validazioni (uguali al PHP) */

	var RESERVED_EVENTS = [ 'ad_activeview', 'ad_click', 'ad_exposure', 'ad_impression', 'ad_query', 'ad_reward', 'adunit_exposure', 'app_background', 'app_clear_data', 'app_exception', 'app_install', 'app_remove', 'app_store_refund', 'app_store_subscription_cancel', 'app_store_subscription_convert', 'app_store_subscription_renew', 'app_uninstall', 'app_update', 'app_upgrade', 'dynamic_link_app_open', 'dynamic_link_app_update', 'dynamic_link_first_open', 'error', 'firebase_campaign', 'firebase_in_app_message_action', 'firebase_in_app_message_dismiss', 'firebase_in_app_message_impression', 'first_open', 'first_visit', 'in_app_purchase', 'notification_dismiss', 'notification_foreground', 'notification_open', 'notification_receive', 'notification_send', 'os_update', 'screen_view', 'session_start', 'session_start_with_rollout', 'user_engagement' ];
	var RESERVED_PREFIXES = [ '_', 'firebase_', 'ga_', 'google_', 'gtag.', 'gtm.' ];

	function validateEventName( name ) {
		name = String( name === undefined || name === null ? '' : name );
		if ( name === '' ) {
			return { ok: false, error: 'Il nome dell\'evento è vuoto.' };
		}
		if ( name.length > 40 ) {
			return { ok: false, error: 'Il nome dell\'evento supera i 40 caratteri.' };
		}
		for ( var i = 0; i < RESERVED_PREFIXES.length; i++ ) {
			if ( name.toLowerCase().indexOf( RESERVED_PREFIXES[ i ] ) === 0 ) {
				return { ok: false, error: 'Il nome dell\'evento inizia con un prefisso riservato (' + RESERVED_PREFIXES[ i ] + ').' };
			}
		}
		if ( ! /^[A-Za-z][A-Za-z0-9_]*$/.test( name ) ) {
			return { ok: false, error: 'Il nome dell\'evento deve iniziare con una lettera e contenere solo lettere, numeri e trattino basso.' };
		}
		if ( RESERVED_EVENTS.indexOf( name.toLowerCase() ) !== -1 ) {
			return { ok: false, error: 'Il nome dell\'evento è riservato da Google Analytics 4.' };
		}
		return { ok: true, error: '' };
	}

	/**
	 * Valore della conversione: "" = nessun valore; "0" = zero; virgola o punto decimale ("25,50", "1.234,56").
	 * @return {{ok: boolean, value: number|null, error: string}}
	 */
	function parseValue( raw ) {
		if ( raw === null || raw === undefined ) {
			return { ok: true, value: null, error: '' };
		}
		if ( typeof raw === 'number' ) {
			return isFinite( raw ) && raw >= 0 ? { ok: true, value: raw, error: '' } : { ok: false, value: null, error: 'Il valore deve essere un numero non negativo.' };
		}
		var s = String( raw ).replace( /[\s €]/g, '' );
		if ( s === '' ) {
			return { ok: true, value: null, error: '' };
		}
		if ( /^\d{1,3}(\.\d{3})+(,\d+)?$/.test( s ) ) {
			s = s.replace( /\./g, '' ).replace( ',', '.' );
		} else if ( /^\d{1,3}(,\d{3})+(\.\d+)?$/.test( s ) ) {
			s = s.replace( /,/g, '' );
		} else {
			s = s.replace( ',', '.' );
		}
		if ( ! /^\d+(\.\d+)?$/.test( s ) ) {
			return { ok: false, value: null, error: 'Il valore deve essere un numero non negativo (es. 25 o 25,50).' };
		}
		var n = Number( s );
		if ( ! isFinite( n ) || n < 0 ) {
			return { ok: false, value: null, error: 'Il valore deve essere un numero non negativo.' };
		}
		return { ok: true, value: Math.round( n * 100 ) / 100, error: '' };
	}

	var CURRENCIES = 'AED AFN ALL AMD ANG AOA ARS AUD AWG AZN BAM BBD BDT BGN BHD BIF BMD BND BOB BRL BSD BTN BWP BYN BZD CAD CDF CHF CLP CNY COP CRC CUP CVE CZK DJF DKK DOP DZD EGP ERN ETB EUR FJD FKP GBP GEL GHS GIP GMD GNF GTQ GYD HKD HNL HTG HUF IDR ILS INR IQD IRR ISK JMD JOD JPY KES KGS KHR KMF KPW KRW KWD KYD KZT LAK LBP LKR LRD LSL LYD MAD MDL MGA MKD MMK MNT MOP MRU MUR MVR MWK MXN MYR MZN NAD NGN NIO NOK NPR NZD OMR PAB PEN PGK PHP PKR PLN PYG QAR RON RSD RUB RWF SAR SBD SCR SDG SEK SGD SHP SLE SOS SRD SSP STN SVC SYP SZL THB TJS TMT TND TOP TRY TTD TWD TZS UAH UGX USD UYU UZS VES VND VUV WST XAF XCD XCG XOF XPF YER ZAR ZMW ZWG'.split( ' ' );

	function isCurrency( code ) {
		return CURRENCIES.indexOf( String( code || '' ) ) !== -1;
	}

	/* ------------------------------------------------------------------ link di contatto */

	/**
	 * @return {{type: string}|null} type: phone | email | whatsapp
	 */
	function classifyContactLink( href ) {
		if ( typeof href !== 'string' ) {
			return null;
		}
		var h = href.trim();
		var lower = h.toLowerCase();
		if ( lower.indexOf( 'tel:' ) === 0 ) {
			return { type: 'phone' };
		}
		if ( lower.indexOf( 'mailto:' ) === 0 ) {
			return { type: 'email' };
		}
		if ( lower.indexOf( 'whatsapp://send' ) === 0 ) {
			return { type: 'whatsapp' };
		}
		var u;
		try {
			u = new URL( h, typeof location !== 'undefined' ? location.href : 'https://example.invalid/' );
		} catch ( e ) {
			return null;
		}
		if ( u.protocol !== 'https:' && u.protocol !== 'http:' ) {
			return null;
		}
		var host = u.hostname.toLowerCase().replace( /^www\./, '' );
		var path = u.pathname.replace( /\/+$/, '' );
		if ( host === 'wa.me' && /^\/(\+?\d{6,15}|message\/[A-Za-z0-9]+)?$/.test( path ) ) {
			return { type: 'whatsapp' };
		}
		if ( ( host === 'api.whatsapp.com' || host === 'web.whatsapp.com' ) && path === '/send' ) {
			return { type: 'whatsapp' };
		}
		return null;
	}

	/* ------------------------------------------------------------------ percorso pulito */

	/** Solo il percorso, senza query string né frammento (possono contenere dati compilati). */
	function cleanPath( pathname ) {
		var p = String( pathname || '/' ).split( '?' )[ 0 ].split( '#' )[ 0 ];
		return p === '' ? '/' : p;
	}

	function uuid() {
		if ( typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function' ) {
			return crypto.randomUUID();
		}
		var b = new Uint8Array( 16 );
		if ( typeof crypto !== 'undefined' && crypto.getRandomValues ) {
			crypto.getRandomValues( b );
		} else {
			for ( var i = 0; i < 16; i++ ) {
				b[ i ] = Math.floor( Math.random() * 256 );
			}
		}
		b[ 6 ] = ( b[ 6 ] & 0x0f ) | 0x40;
		b[ 8 ] = ( b[ 8 ] & 0x3f ) | 0x80;
		var h = [];
		for ( var j = 0; j < 16; j++ ) {
			h.push( ( b[ j ] + 0x100 ).toString( 16 ).slice( 1 ) );
		}
		return h.slice( 0, 4 ).join( '' ) + '-' + h.slice( 4, 6 ).join( '' ) + '-' + h.slice( 6, 8 ).join( '' ) + '-' + h.slice( 8, 10 ).join( '' ) + '-' + h.slice( 10 ).join( '' );
	}

	return {
		SCHEMA_VERSION: SCHEMA_VERSION,
		normalizeEmail: normalizeEmail,
		normalizePhone: normalizePhone,
		sha256Hex: sha256Hex,
		hasSubtle: hasSubtle,
		validateEventName: validateEventName,
		parseValue: parseValue,
		isCurrency: isCurrency,
		classifyContactLink: classifyContactLink,
		cleanPath: cleanPath,
		uuid: uuid
	};
} ) );
