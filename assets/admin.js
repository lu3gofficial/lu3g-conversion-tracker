/**
 * LU3G Tracking — pannello.
 * Salvataggi (impostazioni globali, impostazioni del form scritte in Elementor), azioni (riscansione, sessione di
 * verifica), errori accanto ai campi, test locale con le stesse funzioni dello script del sito (lu3g-ct-core.js).
 */
( function () {
	'use strict';

	var cfg = window.LU3G_CT;
	var page = document.querySelector( '.lu3g-ct' );
	if ( ! cfg || ! page ) {
		return;
	}
	var K = window.LU3GCTCore;
	var toasts = page.querySelector( '[data-toasts]' );

	function call( op, data, extra ) {
		var body = new FormData();
		body.append( 'action', 'lu3g_ct' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'op', op );
		body.append( 'data', JSON.stringify( data || {} ) );
		Object.keys( extra || {} ).forEach( function ( k ) {
			body.append( k, extra[ k ] );
		} );
		return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json().catch( function () {
				throw new Error( 'Risposta non valida dal server.' );
			} );
		} ).then( function ( j ) {
			if ( ! j.success ) {
				var e = new Error( ( j.data && j.data.message ) || 'Errore.' );
				e.errors = ( j.data && j.data.errors ) || {};
				throw e;
			}
			return j.data || {};
		} );
	}

	function toast( text, bad ) {
		if ( ! toasts || ! text ) {
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'ct-toast' + ( bad ? ' is-error' : '' );
		el.setAttribute( 'role', bad ? 'alert' : 'status' );
		el.textContent = text;
		toasts.appendChild( el );
		setTimeout( function () {
			el.classList.add( 'is-out' );
			setTimeout( function () {
				el.remove();
			}, 300 );
		}, bad ? 7000 : 3500 );
	}

	/** name="a[b][c]" → obj.a.b.c = value (anche a più livelli, es. channels[2][label]). */
	function setPath( obj, name, value ) {
		var parts = name.replace( /\]/g, '' ).split( '[' );
		var cur = obj;
		for ( var i = 0; i < parts.length - 1; i++ ) {
			cur[ parts[ i ] ] = cur[ parts[ i ] ] && typeof cur[ parts[ i ] ] === 'object' ? cur[ parts[ i ] ] : {};
			cur = cur[ parts[ i ] ];
		}
		cur[ parts[ parts.length - 1 ] ] = value;
	}

	function collect( form ) {
		var out = {};
		form.querySelectorAll( 'input[name], select[name], textarea[name]' ).forEach( function ( el ) {
			if ( el.disabled ) {
				return;
			}
			if ( el.type === 'radio' && ! el.checked ) {
				return;
			}
			setPath( out, el.name, el.type === 'checkbox' ? el.checked : el.value );
		} );
		return out;
	}

	function showErrors( form, errors ) {
		form.querySelectorAll( '[data-err]' ).forEach( function ( s ) {
			s.textContent = '';
			s.className = '';
		} );
		Object.keys( errors || {} ).forEach( function ( k ) {
			var s = form.querySelector( '[data-err="' + k + '"]' );
			if ( s ) {
				s.textContent = errors[ k ];
				s.className = 'ct-fielderr';
			}
		} );
	}

	page.querySelectorAll( 'form[data-save]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( '[type="submit"]' );
			if ( btn ) {
				btn.disabled = true;
			}
			var kind = form.getAttribute( 'data-save' );
			var extra = kind === 'form' ? { key: form.getAttribute( 'data-key' ) } : {};
			var data = collect( form );
			if ( kind === 'settings' && form.querySelector( '.ct-chlist' ) ) {
				// Lista dei canali sempre inviata (anche vuota), in ordine.
				var list = [];
				Object.keys( data.channels || {} ).sort( function ( x, y ) {
					return x - y;
				} ).forEach( function ( k ) {
					list.push( data.channels[ k ] );
				} );
				data.channels = list;
			}
			call( kind, data, extra ).then( function ( d ) {
				showErrors( form, {} );
				toast( d.message );
				if ( d.reload ) {
					setTimeout( function () {
						location.reload();
					}, 700 );
				}
			} ).catch( function ( err ) {
				showErrors( form, err.errors );
				toast( err.message, true );
			} ).then( function () {
				if ( btn ) {
					btn.disabled = false;
				}
			} );
		} );
	} );

	/* ------------------------------------------------ canali personalizzati: aggiungi / togli righe */

	var chList = page.querySelector( '.ct-chlist' );
	var chTpl = page.querySelector( '[data-ch-template]' );
	function renumber() {
		chList.querySelectorAll( '[data-ch-row]' ).forEach( function ( row, i ) {
			row.querySelectorAll( '[name^="channels["]' ).forEach( function ( el ) {
				el.name = el.name.replace( /^channels\[[^\]]*\]/, 'channels[' + i + ']' );
			} );
			row.querySelectorAll( '[data-err^="channels["]' ).forEach( function ( el ) {
				el.setAttribute( 'data-err', el.getAttribute( 'data-err' ).replace( /^channels\[[^\]]*\]/, 'channels[' + i + ']' ) );
			} );
		} );
	}
	if ( chList && chTpl ) {
		var addBtn = page.querySelector( '[data-ch-add]' );
		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () {
				chList.appendChild( chTpl.content.cloneNode( true ) );
				renumber();
				var rows = chList.querySelectorAll( '[data-ch-row]' );
				var input = rows[ rows.length - 1 ].querySelector( 'input[type="text"]' );
				if ( input ) {
					input.focus();
				}
			} );
		}
		chList.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '[data-ch-remove]' );
			if ( rm ) {
				rm.closest( '[data-ch-row]' ).remove();
				renumber();
			}
		} );
		renumber();
	}

	page.querySelectorAll( '[data-op]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			call( btn.getAttribute( 'data-op' ) ).then( function ( d ) {
				toast( d.message );
				if ( d.reload ) {
					setTimeout( function () {
						location.reload();
					}, 700 );
				}
			} ).catch( function ( err ) {
				toast( err.message, true );
				btn.disabled = false;
			} );
		} );
	} );

	/* ------------------------------------------------ test locale: stesse funzioni del sito, nessun push */

	var run = page.querySelector( '[data-run-local]' );
	var out = page.querySelector( '[data-local-out]' );
	var lt = page.querySelector( '[data-localtest]' );
	if ( run && out && lt && K ) {
		run.addEventListener( 'click', function () {
			var v = collect( lt );
			var em = K.normalizeEmail( v.t_email );
			var ph = K.normalizePhone( v.t_phone, [ v.t_country ], window.libphonenumber );
			var ev = K.validateEventName( v.t_event );
			var val = K.parseValue( v.t_value );
			var cur = String( v.t_currency || '' ).toUpperCase();
			Promise.all( [ em.value ? K.sha256Hex( em.value ) : null, ph.value ? K.sha256Hex( ph.value ) : null ] ).then( function ( h ) {
				var lu3g = { schema_version: K.SCHEMA_VERSION, event_id: K.uuid(), source: 'elementor_pro', form_key: 'documento:widget', form_name: 'test_locale', page_path: '/esempio/', test_mode: true };
				if ( val.ok && val.value !== null ) {
					lu3g.value = val.value;
					lu3g.currency = cur;
				}
				var user = {};
				if ( h[ 0 ] ) {
					user.sha256_email_address = h[ 0 ];
				}
				if ( h[ 1 ] ) {
					user.sha256_phone_number = h[ 1 ];
				}
				var lines = [
					'Email normalizzata: ' + ( em.value || '— (' + em.reason + ')' ),
					'Telefono E.164:     ' + ( ph.value || '— (' + ph.reason + ')' ) + ( ph.ext ? '  interno ' + ph.ext + ' (escluso dal matching)' : '' ),
					'Nome evento:        ' + ( ev.ok ? 'OK' : 'Errore: ' + ev.error ),
					'Valore:             ' + ( val.ok ? ( val.value === null ? 'nessun valore' : val.value ) : 'Errore: ' + val.error ),
					'Valuta:             ' + ( lu3g.value === undefined ? 'non necessaria' : ( K.isCurrency( cur ) ? 'OK' : 'Errore: codice ISO 4217 non valido' ) ),
					'Web Crypto:         ' + ( K.hasSubtle() ? 'disponibile' : 'NON disponibile (serve HTTPS): identificatori omessi' ),
					'',
					'Evento (simulato, NON inviato al dataLayer):',
					JSON.stringify( { event: ev.ok ? v.t_event : '(non valido)', lu3g: lu3g, lu3g_user_data: Object.keys( user ).length ? user : null }, null, 2 )
				];
				out.textContent = lines.join( '\n' );
				out.hidden = false;
			} );
		} );
	}
}() );
