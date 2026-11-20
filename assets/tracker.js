/**
 * LU3G Conversion Tracker: script nel sito.
 *
 * FORM (widget Form classico di Elementor Pro)
 * 1. All'invio (evento "submit" nativo, fase di cattura: prima che Elementor legga e poi svuoti il form) si crea un
 *    TENTATIVO: id casuale (diventa event_id) e, solo se le conversioni avanzate sono attive e il consenso
 *    ad_user_data è esplicitamente concesso, email e telefono del SOLO form inviato, normalizzati e cifrati (SHA-256).
 *    Il valore originale del form non viene toccato. Il submit non è una conversione.
 * 2. La conversione parte solo al successo restituito da Elementor al browser: evento jQuery "submit_success"
 *    (Elementor lo lancia con jQuery.trigger: un listener DOM nativo con lo stesso nome non lo sentirebbe).
 *    Errori di validazione, CAPTCHA, server o rete non lo lanciano, quindi non producono conversioni.
 * 3. Il successo prende il tentativo più recente di quel form (Elementor non permette due invii in parallelo dallo
 *    stesso form; i tentativi precedenti senza successo sono falliti) e lo chiude: un secondo callback dello stesso
 *    tentativo non trova più niente. Due invii riusciti = due tentativi = due eventi.
 * 4. Prima di pubblicare si ricontrolla il consenso. Se gli hash non sono pronti l'evento parte senza identificatori
 *    (nessun secondo evento più tardi). L'invio è sincrono dentro "submit_success": se Elementor poi cambia pagina
 *    (redirect), l'evento è già nel dataLayer e GTM lo ha già elaborato; nessuna attesa aggiunta.
 * 5. Isolamento: prima di ogni evento si azzerano le chiavi "lu3g" e "lu3g_user_data" del modello dati di GTM (che
 *    unisce i valori in modo ricorsivo); dopo l'evento (eventCallback, cioè quando i tag lo hanno letto) si azzera di
 *    nuovo "lu3g_user_data". Il resto del dataLayer non si tocca. Niente cookie, localStorage o log con dati.
 *
 * CONTATTI: click (anche da tastiera, su icone interne, rotellina) su tel:, mailto: e link WhatsApp click-to-chat.
 * Un evento per attivazione; il link funziona come sempre. Non si inviano numero, indirizzo, testo del messaggio
 * o URL completo.
 *
 * CONSENSO: adapter "consent_mode" (stato scritto dal ponte GTM "LU3G CT Consent" dopo un evento del CMP, oppure
 * comandi gtag('consent', ...) nel dataLayer con un "update" esplicito del CMP), "iubenda" (come il precedente, più la
 * presenza della Cookie Solution), "custom" (funzione window.lu3gCTConsent), "none" (identificatori mai inviati).
 * Uno stato assente o solo "default" = sconosciuto = niente identificatori.
 */
( function () {
	'use strict';

	var C = window.lu3gCTConfig;
	var K = window.LU3GCTCore;
	if ( ! C || ! K || window.__lu3gCTOn ) {
		return;
	}
	window.__lu3gCTOn = true;

	var DL = C.dl || 'dataLayer';
	var CONSENT_KEYS = [ 'analytics_storage', 'ad_storage', 'ad_user_data', 'ad_personalization' ];

	function dl() {
		window[ DL ] = window[ DL ] || [];
		return window[ DL ];
	}

	/* ================================================================ consenso */

	function readConsentCommands() {
		var st = {};
		var updated = false;
		var a = window[ DL ] || [];
		for ( var i = 0; i < a.length; i++ ) {
			var e = a[ i ];
			if ( e && typeof e === 'object' && e[ 0 ] === 'consent' && ( e[ 1 ] === 'default' || e[ 1 ] === 'update' ) && e[ 2 ] && typeof e[ 2 ] === 'object' ) {
				for ( var j = 0; j < CONSENT_KEYS.length; j++ ) {
					var v = e[ 2 ][ CONSENT_KEYS[ j ] ];
					if ( v === 'granted' || v === 'denied' ) {
						st[ CONSENT_KEYS[ j ] ] = v;
					}
				}
				if ( e[ 1 ] === 'update' ) {
					updated = true;
				}
			}
		}
		return { st: st, updated: updated };
	}

	/** @return {{ready: boolean, source: string, reason: string, analytics_storage, ad_storage, ad_user_data, ad_personalization}} */
	function consentState() {
		var out = { ready: false, source: C.consent, reason: '' };
		var a = C.consent;
		if ( a === 'none' ) {
			out.reason = 'nessun CMP configurato: identificatori disattivati';
			return out;
		}
		if ( a === 'custom' ) {
			var r = null;
			try {
				r = typeof window.lu3gCTConsent === 'function' ? window.lu3gCTConsent() : null;
			} catch ( e ) {
				r = null;
			}
			if ( ! r || r.ready !== true ) {
				out.reason = 'adapter personalizzato assente o non pronto';
				return out;
			}
			CONSENT_KEYS.forEach( function ( k ) {
				if ( r[ k ] === 'granted' || r[ k ] === 'denied' ) {
					out[ k ] = r[ k ];
				}
			} );
			out.ready = true;
			return out;
		}
		if ( a === 'iubenda' && ! ( window._iub && window._iub.cs && window._iub.cs.api ) ) {
			out.reason = 'Iubenda Cookie Solution non caricata';
			return out;
		}
		// 1) Ponte GTM: lo scrive il modello "LU3G CT Consent" solo dopo un evento del CMP (es. modello Iubenda in GTM).
		var b = window.lu3gCTConsentGTM;
		if ( b && typeof b === 'object' && b.source === 'gtm' ) {
			CONSENT_KEYS.forEach( function ( k ) {
				if ( b[ k ] === 'granted' || b[ k ] === 'denied' ) {
					out[ k ] = b[ k ];
				}
			} );
			if ( out.ad_user_data ) {
				out.ready = true;
				out.via = 'ponte GTM' + ( b.event ? ' (' + String( b.event ).slice( 0, 40 ) + ')' : '' );
				return out;
			}
		}
		// 2) Comandi gtag('consent', ...) nel dataLayer.
		var c = readConsentCommands();
		CONSENT_KEYS.forEach( function ( k ) {
			if ( c.st[ k ] ) {
				out[ k ] = c.st[ k ];
			}
		} );
		if ( ! c.updated ) {
			out.reason = 'nessuna scelta espressa dal CMP (solo stato predefinito o nessuno; se il CMP lavora dentro GTM serve il tag "LU3G - Ponte consenso")';
			return out;
		}
		out.ready = true;
		out.via = 'comandi consent nel dataLayer';
		return out;
	}

	/** Identificatori per Ads: solo con ad_user_data esplicitamente "granted". */
	function idsAllowed() {
		var s = consentState();
		return s.ready && s.ad_user_data === 'granted';
	}

	/* ================================================================ libreria dei numeri (solo dove ci sono form) */

	var libState = window.libphonenumber ? 'ready' : 'idle';
	var observer = null;

	function loadLib() {
		if ( libState !== 'idle' ) {
			return;
		}
		if ( window.libphonenumber ) {
			libState = 'ready';
			return;
		}
		libState = 'loading';
		var s = document.createElement( 'script' );
		s.src = C.lib;
		s.async = true;
		s.onload = function () {
			libState = window.libphonenumber ? 'ready' : 'error';
		};
		s.onerror = function () {
			libState = 'error';
		};
		( document.head || document.documentElement ).appendChild( s );
		if ( observer ) {
			observer.disconnect();
			observer = null;
		}
	}

	function lookForForms() {
		if ( document.querySelector( 'form.elementor-form' ) ) {
			loadLib();
			diagForms();
			return true;
		}
		return false;
	}

	/* ================================================================ form */

	function isForm( el ) {
		return !! ( el && el.tagName === 'FORM' && el.classList && el.classList.contains( 'elementor-form' ) );
	}

	function hidden( form, name ) {
		var el = form.querySelector( 'input[name="' + name + '"]' );
		return el ? String( el.value || '' ) : '';
	}

	function label( text ) {
		var t = String( text || '' ).toLowerCase();
		if ( t.normalize ) {
			t = t.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}
		t = t.replace( /[^a-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' );
		return ( t || 'form' ).slice( 0, 60 );
	}

	/** Configurazione del form: censito (documento:widget), poi per solo widget (widget globali), poi globale. */
	function formInfo( form ) {
		var post = hidden( form, 'post_id' );
		var fid = hidden( form, 'form_id' );
		var key = post + ':' + fid;
		var cfg = C.forms && C.forms[ key ];
		var source = 'censito';
		if ( ! cfg && C.byEl && C.byEl[ fid ] ) {
			key = C.byEl[ fid ];
			cfg = C.forms[ key ];
			source = 'censito (widget globale)';
		}
		if ( ! cfg ) {
			var d = C.defaults || {};
			cfg = { on: d.on, e: d.e, n: label( form.getAttribute( 'name' ) ), v: d.v, c: d.c, k: d.k, ec: d.ec, m: '', p: '', cf: '', co: d.co, auto: true };
			source = 'non censito: impostazioni globali';
		}
		if ( ! form.__lu3gInst ) {
			form.__lu3gInst = K.uuid();
		}
		return { key: key, cfg: cfg, source: source, instance: form.__lu3gInst };
	}

	function field( form, id ) {
		return id ? form.querySelector( '[name="form_fields[' + id + ']"]' ) : null;
	}

	/** Campo email/telefono del form che ha generato il tentativo: mappato, altrimenti riconosciuto solo se unico. */
	function pickField( form, cfg, kind ) {
		var mapped = kind === 'email' ? cfg.m : cfg.p;
		if ( mapped ) {
			return { el: field( form, mapped ), how: 'mappato' };
		}
		if ( ! cfg.auto ) {
			return { el: null, how: 'nessun campo unico' };
		}
		// Form non censito: si riconosce dal tipo del campo, solo se ce n'è uno.
		var list = form.querySelectorAll( 'input[type="' + ( kind === 'email' ? 'email' : 'tel' ) + '"][name^="form_fields["]' );
		return list.length === 1 ? { el: list[ 0 ], how: 'automatico' } : { el: null, how: list.length > 1 ? 'più campi candidati' : 'nessun campo' };
	}

	function countryFrom( form, cfg ) {
		var out = [];
		var f = field( form, cfg.cf );
		if ( f && f.value ) {
			var v = String( f.value ).trim();
			if ( /^[A-Za-z]{2}$/.test( v ) ) {
				out.push( v.toUpperCase() );
			} else if ( /^\+?\d{1,4}$/.test( v ) && window.libphonenumber && window.libphonenumber.getCountries ) {
				var code = v.replace( '+', '' );
				var lib = window.libphonenumber;
				var match = lib.getCountries().filter( function ( c ) {
					return String( lib.getCountryCallingCode( c ) ) === code;
				} );
				if ( match.length === 1 ) {
					out.push( match[ 0 ] );
				}
			}
		}
		out.push( cfg.co || ( C.defaults && C.defaults.co ) || 'IT' );
		return out;
	}

	var attempts = [];
	var MAX_AGE = 120000;

	function prune() {
		var now = Date.now();
		attempts = attempts.filter( function ( a ) {
			if ( now - a.created > MAX_AGE ) {
				a.user = null;
				return false;
			}
			return true;
		} );
	}

	/** Toglie i tentativi ancora aperti di un form (falliti: errore, CAPTCHA, rete) e i loro dati. */
	function dropPending( form ) {
		attempts = attempts.filter( function ( x ) {
			if ( x.form === form && ! x.done ) {
				x.user = null;
				return false;
			}
			return true;
		} );
	}

	/** Fotografia al momento dell'invio. */
	function capture( form ) {
		prune();
		dropPending( form ); // un nuovo invio: i tentativi precedenti di questo form senza successo sono falliti
		form.__lu3gState = 'pending';
		var info = formInfo( form );
		var a = { id: K.uuid(), form: form, info: info, created: Date.now(), user: null, userReady: false, ids: { state: '', reason: '' }, done: false };
		if ( ! info.cfg.on ) {
			a.excluded = true;
		} else if ( ! info.cfg.ec ) {
			a.ids = { state: 'na', reason: 'conversioni avanzate disattivate' };
		} else if ( ! idsAllowed() ) {
			a.ids = { state: 'consent', reason: consentState().reason || 'consenso ad_user_data non concesso' };
		} else if ( ! K.hasSubtle() ) {
			a.ids = { state: 'error', reason: 'Web Crypto non disponibile (serve HTTPS): identificatori omessi' };
		} else {
			var ef = pickField( form, info.cfg, 'email' );
			var pf = pickField( form, info.cfg, 'phone' );
			var em = ef.el ? K.normalizeEmail( String( ef.el.value || '' ) ) : { value: null, reason: ef.how };
			var ph = pf.el ? K.normalizePhone( String( pf.el.value || '' ), countryFrom( form, info.cfg ), window.libphonenumber ) : { value: null, reason: pf.how };
			a.ids = { state: 'pending', reason: '', email: em.value ? 'ok' : em.reason, phone: ph.value ? 'ok' : ph.reason };
			var jobs = [ em.value ? K.sha256Hex( em.value ) : Promise.resolve( null ), ph.value ? K.sha256Hex( ph.value ) : Promise.resolve( null ) ];
			em = null;
			ph = null;
			Promise.all( jobs ).then( function ( h ) {
				if ( a.done ) {
					return; // troppo tardi: l'evento è già partito senza identificatori
				}
				var u = {};
				if ( h[ 0 ] ) {
					u.sha256_email_address = h[ 0 ];
				}
				if ( h[ 1 ] ) {
					u.sha256_phone_number = h[ 1 ];
				}
				a.user = ( u.sha256_email_address || u.sha256_phone_number ) ? u : null;
				a.userReady = true;
				a.ids.state = a.user ? 'ok' : 'none';
			} );
		}
		attempts.push( a );
		return a;
	}

	document.addEventListener( 'submit', function ( e ) {
		if ( isForm( e.target ) ) {
			try {
				capture( e.target );
			} catch ( err ) {
				// Mai bloccare l'invio del visitatore.
			}
		}
	}, true );

	function takeAttempt( form ) {
		var mine = attempts.filter( function ( a ) {
			return a.form === form && ! a.done;
		} );
		if ( ! mine.length ) {
			return null;
		}
		var a = mine[ mine.length - 1 ];
		attempts = attempts.filter( function ( x ) {
			if ( x.form === form ) {
				x.done = true;
				if ( x !== a ) {
					x.user = null;
				}
				return false;
			}
			return true;
		} );
		return a;
	}

	function pushEvent( event, lu3g, user, onDone ) {
		var q = dl();
		q.push( { lu3g: null, lu3g_user_data: null } );
		q.push( {
			event: event,
			lu3g: lu3g,
			lu3g_user_data: user,
			eventCallback: function () {
				dl().push( { lu3g_user_data: null } );
				if ( onDone ) {
					onDone();
				}
			},
			eventTimeout: 2000
		} );
	}

	function success( form ) {
		var a = takeAttempt( form );
		if ( ! a && form.__lu3gState === 'done' ) {
			return; // secondo callback dello stesso tentativo: già pubblicato
		}
		form.__lu3gState = 'done';
		if ( ! a ) {
			// Successo senza fotografia (invio partito senza evento "submit"): evento base, senza identificatori.
			a = { id: K.uuid(), info: formInfo( form ), created: Date.now(), user: null, userReady: false, ids: { state: 'error', reason: 'fotografia dell\'invio mancante' } };
			if ( ! a.info.cfg.on ) {
				a.excluded = true;
			}
		}
		a.done = true;
		var cfg = a.info.cfg;
		var report = diagStart( 'form', a );
		if ( a.excluded ) {
			diagStep( report, 'payload', 'na', cfg.err ? 'nome evento non valido: evento non inviato' : ( cfg.unsup ? 'adapter non supportato' : 'tracking disattivato per questo form' ) );
			diagFinish( report );
			return;
		}
		var lu3g = {
			schema_version: K.SCHEMA_VERSION,
			event_id: a.id,
			source: 'elementor_pro',
			form_key: a.info.key,
			form_instance_id: a.info.instance,
			form_name: cfg.n,
			page_id: C.pageId || undefined,
			page_path: K.cleanPath( location.pathname ),
			test_mode: !! C.test
		};
		if ( typeof cfg.v === 'number' && isFinite( cfg.v ) && cfg.v >= 0 ) {
			lu3g.value = cfg.v;
			lu3g.currency = cfg.c || 'EUR';
		}
		if ( cfg.k ) {
			lu3g.conversion_key = cfg.k;
		}
		if ( ! lu3g.page_id ) {
			delete lu3g.page_id;
		}
		var user = null;
		if ( a.userReady && a.user ) {
			if ( idsAllowed() ) {
				user = a.user;
			} else {
				a.ids = { state: 'consent', reason: 'consenso revocato prima dell\'invio' };
			}
		} else if ( a.ids.state === 'pending' ) {
			a.ids = { state: 'error', reason: 'hash non pronti in tempo: evento senza identificatori' };
		}
		a.user = null;
		diagPayload( report, cfg.e, lu3g, user, a.ids );
		pushEvent( cfg.e, lu3g, user, function () {
			diagStep( report, 'gtm_cb', 'ok', 'eventCallback ricevuto' );
		} );
		user = null;
		diagStep( report, 'datalayer', 'ok', 'push eseguito, event_id ' + a.id );
		diagFinish( report );
	}

	/* -------- "submit_success" di Elementor (evento jQuery); jQuery può arrivare dopo (es. WP Rocket) */

	var bound = false;
	function bindJq() {
		var $ = window.jQuery;
		if ( bound || ! $ || ! $.fn || ! $.fn.on ) {
			return bound;
		}
		bound = true;
		// Invio non riuscito (se Elementor lo segnala): si buttano subito i dati del tentativo.
		$( document ).on( 'error submit_error', 'form.elementor-form', function () {
			dropPending( this );
		} );
		$( document ).on( 'submit_success', function ( e ) {
			var t = e.target;
			var form = isForm( t ) ? t : ( t && t.querySelector ? t.querySelector( 'form.elementor-form' ) : null );
			if ( form ) {
				try {
					success( form );
				} catch ( err ) {
					// Mai bloccare il flusso di Elementor (messaggio, redirect).
				}
			}
		} );
		return true;
	}

	function start() {
		bindJq();
		if ( ! lookForForms() && typeof MutationObserver === 'function' && document.body ) {
			// Popup e contenuti caricati dopo: appena compare un form si carica la libreria dei numeri.
			var pending = false;
			observer = new MutationObserver( function () {
				if ( pending ) {
					return;
				}
				pending = true;
				setTimeout( function () {
					pending = false;
					lookForForms();
				}, 300 );
			} );
			observer.observe( document.body, { childList: true, subtree: true } );
		}
		document.addEventListener( 'focusin', function ( e ) {
			if ( e.target && e.target.closest && e.target.closest( 'form.elementor-form' ) ) {
				loadLib();
			}
		} );
	}


	/* ================================================================ click sui contatti */

	function placementOf( a ) {
		var attr = a.closest( '[data-lu3g-placement]' );
		if ( attr ) {
			return label( attr.getAttribute( 'data-lu3g-placement' ) ).slice( 0, 40 );
		}
		var loc = a.closest( '[data-elementor-type]' );
		var type = loc ? loc.getAttribute( 'data-elementor-type' ) : '';
		if ( type === 'header' || type === 'footer' || type === 'popup' ) {
			return type;
		}
		if ( a.closest( 'header' ) ) {
			return 'header';
		}
		if ( a.closest( 'footer' ) ) {
			return 'footer';
		}
		return 'content';
	}

	function excluded( a ) {
		if ( a.closest( '[data-lu3g-ct-ignore]' ) ) {
			return true;
		}
		var sel = String( ( C.contacts && C.contacts.exclude ) || '' ).split( /[\n,]+/ ).map( function ( s ) {
			return s.trim();
		} ).filter( Boolean );
		for ( var i = 0; i < sel.length; i++ ) {
			try {
				if ( a.closest( sel[ i ] ) ) {
					return true;
				}
			} catch ( e ) {
				// selettore non valido: ignorato
			}
		}
		return false;
	}

	function onContact( e ) {
		if ( ! C.contacts || ! C.contacts.on || e.__lu3gCT ) {
			return;
		}
		if ( e.type === 'auxclick' && e.button !== 1 ) {
			return;
		}
		e.__lu3gCT = true;
		var a = e.target && e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( ! a || excluded( a ) ) {
			return;
		}
		var kind = K.classifyContactLink( a.getAttribute( 'href' ) );
		if ( ! kind ) {
			return;
		}
		var ch = C.contacts.ch[ kind.type ];
		if ( ! ch || ! ch.on ) {
			return;
		}
		var lu3g = {
			schema_version: K.SCHEMA_VERSION,
			event_id: K.uuid(),
			source: 'link',
			contact_type: kind.type,
			placement: placementOf( a ),
			page_id: C.pageId || undefined,
			page_path: K.cleanPath( location.pathname ),
			test_mode: !! C.test
		};
		var idEl = a.closest( '[data-lu3g-contact-id]' );
		if ( idEl ) {
			lu3g.contact_id = label( idEl.getAttribute( 'data-lu3g-contact-id' ) ).slice( 0, 40 );
		}
		if ( ch.k ) {
			lu3g.conversion_key = ch.k;
		}
		if ( typeof ch.v === 'number' && isFinite( ch.v ) && ch.v >= 0 ) {
			lu3g.value = ch.v;
			lu3g.currency = C.contacts.c || 'EUR';
		}
		if ( ! lu3g.page_id ) {
			delete lu3g.page_id;
		}
		var report = diagStart( 'contact', { id: lu3g.event_id, info: { key: kind.type, source: 'link' } } );
		diagPayload( report, C.contacts.e, lu3g, null, { state: 'na', reason: 'i click non hanno identificatori' } );
		pushEvent( C.contacts.e, lu3g, null, function () {
			diagStep( report, 'gtm_cb', 'ok', 'eventCallback ricevuto' );
		} );
		diagStep( report, 'datalayer', 'ok', 'push eseguito, event_id ' + lu3g.event_id );
		diagFinish( report );
	}

	document.addEventListener( 'click', onContact, true );
	document.addEventListener( 'auxclick', onContact, true );

	/* ================================================================ diagnostica (solo in una sessione di verifica) */

	var D = C.diag || null;
	var reports = {};
	var box = null;

	function esc( s ) {
		return String( s === undefined || s === null ? '' : s ).replace( /[&<>"]/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
		} );
	}

	var LABELS = { ok: 'OK', error: 'Errore', consent: 'Bloccato dal consenso', unverified: 'Non verificato', na: 'Non applicabile' };

	function gtmPresence() {
		var q = window[ DL ] || [];
		var loaded = false;
		for ( var i = 0; i < q.length; i++ ) {
			if ( q[ i ] && q[ i ].event === 'gtm.js' ) {
				loaded = true;
				break;
			}
		}
		var ids = [];
		document.querySelectorAll( 'script[src*="gtm.js?id="]' ).forEach( function ( s ) {
			var m = /[?&]id=(GTM-[A-Z0-9]+)/.exec( s.src );
			if ( m && ids.indexOf( m[ 1 ] ) === -1 ) {
				ids.push( m[ 1 ] );
			}
		} );
		var st = 'unverified';
		var note = loaded ? 'evento gtm.js presente' : 'nessun evento gtm.js nel dataLayer';
		if ( C.gtm && ids.length ) {
			st = ids.indexOf( C.gtm ) !== -1 ? 'ok' : 'error';
			note = 'container nella pagina: ' + ids.join( ', ' ) + ( st === 'error' ? ' (atteso ' + C.gtm + ')' : '' );
		} else if ( ids.length ) {
			note += '; container nella pagina: ' + ids.join( ', ' );
		} else if ( loaded ) {
			note += '; ID non leggibile (caricamento personalizzato o server-side)';
		}
		return { state: st, note: note };
	}

	function diagForms() {
		if ( ! D ) {
			return;
		}
		ensureBox();
		var list = [];
		document.querySelectorAll( 'form.elementor-form' ).forEach( function ( f ) {
			var i = formInfo( f );
			list.push( { key: i.key, name: i.cfg.n, event: i.cfg.e, on: !! i.cfg.on, source: i.source, email: i.cfg.m || ( i.cfg.auto ? 'auto' : '—' ), phone: i.cfg.p || ( i.cfg.auto ? 'auto' : '—' ), err: i.cfg.err === 'event' ? 'nome evento non valido' : ( i.cfg.unsup ? 'adapter non supportato' : '' ) } );
		} );
		box.querySelector( '[data-forms]' ).innerHTML = list.length ? list.map( function ( f ) {
			return '<li><b>' + esc( f.name ) + '</b> <code>' + esc( f.key ) + '</code><br>' + ( f.on ? 'evento <code>' + esc( f.event ) + '</code>' : '<span class="x">non tracciato' + ( f.err ? ': ' + esc( f.err ) : '' ) + '</span>' ) + ' · email ' + esc( f.email ) + ' · tel ' + esc( f.phone ) + '<br><small>' + esc( f.source ) + ' · adapter elementor_form</small></li>';
		} ).join( '' ) : '<li>Nessun form di Elementor in questa pagina.</li>';
	}

	function ensureBox() {
		if ( box || ! D || ! document.body ) {
			return;
		}
		var css = '#lu3g-ct-diag{position:fixed;left:12px;bottom:12px;z-index:2147483646;width:min(380px,calc(100vw - 24px));max-height:70vh;overflow:auto;background:#09090b;color:#e4e4e7;font:12px/1.45 -apple-system,system-ui,sans-serif;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.35);padding:12px 14px}#lu3g-ct-diag h4{margin:0 0 6px;font-size:13px;color:#fff}#lu3g-ct-diag ul{margin:4px 0 8px;padding-left:16px}#lu3g-ct-diag code{background:#27272a;padding:0 4px;border-radius:4px}#lu3g-ct-diag .x{color:#fca5a5}#lu3g-ct-diag .s-ok{color:#86efac}#lu3g-ct-diag .s-error{color:#fca5a5}#lu3g-ct-diag .s-consent{color:#fcd34d}#lu3g-ct-diag .s-unverified,#lu3g-ct-diag .s-na{color:#a1a1aa}#lu3g-ct-diag button{float:right;background:none;border:0;color:#a1a1aa;cursor:pointer;font-size:14px}';
		var st = document.createElement( 'style' );
		st.textContent = css;
		document.head.appendChild( st );
		box = document.createElement( 'div' );
		box.id = 'lu3g-ct-diag';
		box.setAttribute( 'data-lu3g-ct-ignore', '' );
		box.setAttribute( 'role', 'region' );
		box.setAttribute( 'aria-label', 'Verifica LU3G Tracking' );
		var g = gtmPresence();
		var cs = consentState();
		box.innerHTML = '<button type="button" aria-label="Chiudi" data-close>×</button><h4>LU3G Tracking · sessione di verifica</h4>' +
			'<div>Gli eventi di questa sessione hanno <code>test_mode: true</code>.</div>' +
			'<div>GTM: <span class="s-' + g.state + '">' + LABELS[ g.state ] + '</span> <small>' + esc( g.note ) + '</small></div>' +
			'<div>Consenso (' + esc( C.consent ) + ( cs.via ? ', ' + esc( cs.via ) : '' ) + '): ' + ( cs.ready ? 'ad_user_data <b>' + esc( cs.ad_user_data || '—' ) + '</b>, ad_storage ' + esc( cs.ad_storage || '—' ) + ', analytics_storage ' + esc( cs.analytics_storage || '—' ) + ', ad_personalization ' + esc( cs.ad_personalization || '—' ) : '<span class="s-consent">sconosciuto</span> <small>' + esc( cs.reason ) + '</small>' ) + '</div>' +
			'<div>Libreria telefoni: <span data-lib>' + esc( libState ) + '</span> · Web Crypto: ' + ( K.hasSubtle() ? 'sì' : '<span class="x">no (serve HTTPS)</span>' ) + '</div>' +
			'<h4 style="margin-top:8px">Form nella pagina</h4><ul data-forms></ul><h4>Eventi</h4><ul data-events><li>Invia un form o clicca un contatto.</li></ul>';
		box.querySelector( '[data-close]' ).addEventListener( 'click', function () {
			box.style.display = 'none';
		} );
		document.body.appendChild( box );
	}

	function diagStart( kind, a ) {
		if ( ! D ) {
			return null;
		}
		var r = {
			kind: kind,
			event_id: a.id,
			form_key: a.info ? a.info.key : '',
			source: a.info ? a.info.source : '',
			time: new Date().toISOString(),
			steps: {
				detected: { state: 'ok', note: kind === 'form' ? 'adapter elementor_form' : 'link di contatto' },
				success: { state: kind === 'form' ? 'ok' : 'na', note: kind === 'form' ? 'submit_success di Elementor per questo tentativo' : '' },
				payload: { state: 'unverified', note: '' },
				ids: { state: 'na', note: '' },
				datalayer: { state: 'unverified', note: '' },
				gtm: { state: 'unverified', note: 'bridge non configurato o nessuna risposta: dataLayer verificato; elaborazione GTM non verificata' },
				tags: { state: 'unverified', note: '' },
				transmission: { state: 'unverified', note: 'controllare con Tag Assistant / pannello Rete' },
				reception: { state: 'unverified', note: 'controllare GA4 DebugView e la diagnostica di Google Ads' },
				attribution: { state: 'unverified', note: 'si vede nei report di Google Ads, non da qui' }
			}
		};
		reports[ a.id ] = r;
		return r;
	}

	function diagStep( r, step, state, note ) {
		if ( ! r ) {
			return;
		}
		if ( step === 'gtm_cb' ) {
			// L'eventCallback arriva anche per timeout: da solo non prova l'esecuzione dei tag.
			if ( r.steps.gtm.state !== 'ok' ) {
				r.steps.gtm.note = 'eventCallback ricevuto (può essere anche per timeout); per la prova serve il bridge';
			}
		} else {
			r.steps[ step ] = { state: state, note: note || '' };
		}
		render( r );
	}

	function diagPayload( r, event, lu3g, user, ids ) {
		if ( ! r ) {
			return;
		}
		var problems = [];
		if ( ! K.validateEventName( event ).ok ) {
			problems.push( 'nome evento' );
		}
		if ( lu3g.value !== undefined && ! K.isCurrency( lu3g.currency ) ) {
			problems.push( 'valuta' );
		}
		if ( user ) {
			Object.keys( user ).forEach( function ( k ) {
				if ( ! /^[0-9a-f]{64}$/.test( user[ k ] ) ) {
					problems.push( k );
				}
			} );
		}
		r.event = event;
		r.payload = JSON.parse( JSON.stringify( lu3g ) );
		r.identifiers = user ? Object.keys( user ) : [];
		r.steps.payload = { state: problems.length ? 'error' : 'ok', note: problems.length ? 'non conforme: ' + problems.join( ', ' ) : 'schema ' + lu3g.schema_version + ', evento ' + event + ( lu3g.value !== undefined ? ', valore ' + lu3g.value + ' ' + lu3g.currency : ', senza valore' ) };
		var map = { ok: 'ok', none: 'error', consent: 'consent', na: 'na', error: 'error' };
		var st = user ? 'ok' : ( map[ ids.state ] || 'error' );
		var note = user ? 'inviati: ' + r.identifiers.join( ', ' ) : ( ids.reason || '' );
		if ( ids.email !== undefined ) {
			note += ( note ? ' · ' : '' ) + 'email: ' + ids.email + ' · telefono: ' + ids.phone;
		}
		r.steps.ids = { state: st, note: note };
	}

	function diagFinish( r ) {
		if ( ! r ) {
			return;
		}
		render( r );
		// Senza risposta del bridge entro 6 secondi, si salva così com'è.
		setTimeout( function () {
			if ( ! r.bridged ) {
				send( r );
			}
		}, 6000 );
	}

	function render( r ) {
		ensureBox();
		if ( ! box ) {
			return;
		}
		var lib = box.querySelector( '[data-lib]' );
		if ( lib ) {
			lib.textContent = libState;
		}
		var ul = box.querySelector( '[data-events]' );
		var li = ul.querySelector( '[data-id="' + r.event_id + '"]' );
		if ( ! li ) {
			if ( ul.children.length === 1 && ! ul.children[ 0 ].hasAttribute( 'data-id' ) ) {
				ul.innerHTML = '';
			}
			li = document.createElement( 'li' );
			li.setAttribute( 'data-id', r.event_id );
			ul.insertBefore( li, ul.firstChild );
		}
		var names = { detected: 'Form rilevato', success: 'Invio riuscito', payload: 'Payload valido', ids: 'Identificatori', datalayer: 'DataLayer', gtm: 'GTM', tags: 'Tag', transmission: 'Trasmissione', reception: 'Ricezione Google', attribution: 'Attribuzione Ads' };
		li.innerHTML = '<b>' + esc( r.event || r.kind ) + '</b> <small>' + esc( r.form_key ) + '</small><br>' + Object.keys( names ).map( function ( k ) {
			var s = r.steps[ k ];
			return names[ k ] + ': <span class="s-' + s.state + '">' + LABELS[ s.state ] + '</span>' + ( s.note ? ' <small>' + esc( s.note ) + '</small>' : '' );
		} ).join( '<br>' );
	}

	/** Salva il risultato (senza dati personali). Si può richiamare: il server aggiorna per event_id. */
	function send( r ) {
		if ( ! D ) {
			return;
		}
		var body = new FormData();
		body.append( 'action', 'lu3g_ct_diag_record' );
		body.append( 'nonce', D.nonce );
		body.append( 'report', JSON.stringify( { kind: r.kind, event: r.event, event_id: r.event_id, form_key: r.form_key, source: r.source, time: r.time, page_path: K.cleanPath( location.pathname ), payload: r.payload, identifiers: r.identifiers, steps: r.steps, gtm_container: r.gtm_container || '' } ) );
		try {
			fetch( D.ajax, { method: 'POST', credentials: 'same-origin', body: body } );
		} catch ( e ) {
			// solo diagnostica
		}
	}

	if ( D ) {
		/**
		 * Bridge diagnostico: lo chiama il modello di tag "LU3G CT Bridge" importato in GTM (addEventCallback), con
		 * event_id, ID del container e stato dei singoli tag. Nessun dato personale.
		 */
		window.lu3gCTBridge = function ( p ) {
			if ( ! p || typeof p !== 'object' ) {
				return;
			}
			var r = reports[ String( p.event_id || '' ) ];
			if ( ! r ) {
				return;
			}
			r.bridged = true;
			r.gtm_container = String( p.container_id || '' ).slice( 0, 20 );
			var expected = C.gtm && r.gtm_container && r.gtm_container !== C.gtm;
			r.steps.gtm = { state: expected ? 'error' : 'ok', note: 'evento elaborato dal container ' + r.gtm_container + ( expected ? ' (atteso ' + C.gtm + ')' : '' ) };
			var tags = Array.isArray( p.tags ) ? p.tags : [];
			var relevant = tags.filter( function ( t ) {
				return t && ( t.role === 'ga4' || t.role === 'ads' );
			} );
			if ( relevant.length ) {
				var bad = relevant.filter( function ( t ) {
					return t.status !== 'success';
				} );
				r.steps.tags = { state: bad.length ? 'error' : 'ok', note: relevant.map( function ( t ) {
					return ( t.name || t.role ) + ': ' + t.status;
				} ).join( ' · ' ) };
			} else {
				r.steps.tags = { state: 'unverified', note: tags.length ? 'nessun tag con ruolo ga4/ads nei metadati' : 'nessun tag eseguito per questo evento' };
			}
			render( r );
			send( r );
		};
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', ensureBox );
		} else {
			ensureBox();
		}
	}

	/* ================================================================ avvio */

	if ( ! bindJq() ) {
		var tries = 0;
		var timer = setInterval( function () {
			tries++;
			if ( bindJq() || tries > 240 ) {
				clearInterval( timer );
			}
		}, 500 );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	/** Stato tecnico per il debug (nessun dato personale). */
	window.LU3GCT = {
		version: C.version,
		state: function () {
			return { lib: libState, consent: consentState(), pending: attempts.length, jquery: bound, gtm: gtmPresence() };
		}
	};
}() );
