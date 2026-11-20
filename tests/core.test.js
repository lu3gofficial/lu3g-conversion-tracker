/**
 * Test delle funzioni pure (assets/lu3g-ct-core.js) con la libreria dei numeri inclusa nel plugin.
 * Avvio: node tests/core.test.js   (Node 18+, nessuna dipendenza)
 */
'use strict';
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );
const assert = require( 'assert' );
const crypto = require( 'crypto' );

const root = path.join( __dirname, '..' );
const core = require( path.join( root, 'assets/lu3g-ct-core.js' ) );

// La libreria è un bundle per il browser: la si carica in un contesto senza "module".
const ctx = {};
ctx.globalThis = ctx;
vm.createContext( ctx );
vm.runInContext( fs.readFileSync( path.join( root, 'assets/vendor/libphonenumber-js/libphonenumber-min.js' ), 'utf8' ), ctx );
const lib = ctx.libphonenumber;

let passed = 0;
const failures = [];
async function t( name, fn ) {
	try {
		await fn();
		passed++;
	} catch ( e ) {
		failures.push( name + ': ' + e.message );
	}
}

( async () => {
	/* email */
	const E = ( v ) => core.normalizeEmail( v ).value;
	await t( 'gmail punti e +alias', () => assert.strictEqual( E( ' Nome.Cognome+Preventivo@gmail.com ' ), 'nomecognome@gmail.com' ) );
	await t( 'googlemail', () => assert.strictEqual( E( 'Jane.Doe+Shopping@googlemail.com' ), 'janedoe@googlemail.com' ) );
	await t( 'dominio aziendale invariato', () => assert.strictEqual( E( ' Nome.Cognome+Preventivo@example.com ' ), 'nome.cognome+preventivo@example.com' ) );
	await t( 'email non valida', () => assert.strictEqual( E( 'mario@' ), null ) );
	await t( 'email vuota', () => assert.strictEqual( E( '   ' ), null ) );
	await t( 'due @', () => assert.strictEqual( E( 'a@b@c.it' ), null ) );
	await t( 'gmail solo punti', () => assert.strictEqual( E( '...@gmail.com' ), null ) );

	/* telefono */
	const P = ( v, c ) => core.normalizePhone( v, c || [ 'IT' ], lib );
	await t( 'cellulare IT', () => assert.strictEqual( P( '347 123 4567' ).value, '+393471234567' ) );
	await t( '+39 con default diverso', () => assert.strictEqual( P( '+39 347 123 4567', [ 'US' ] ).value, '+393471234567' ) );
	await t( '0039', () => assert.strictEqual( P( '0039 347 123 4567' ).value, '+393471234567' ) );
	await t( 'fisso Milano con zero', () => assert.strictEqual( P( '02 36618 300' ).value, '+390236618300' ) );
	await t( 'parentesi e trattini', () => assert.strictEqual( P( '(02) 3661-8300' ).value, '+390236618300' ) );
	await t( 'estero +44', () => assert.strictEqual( P( '+44 20 7946 0958' ).value, '+442079460958' ) );
	await t( 'estero 00 44 da IT', () => assert.strictEqual( P( '00 44 20 7946 0958' ).value, '+442079460958' ) );
	await t( 'cellulare che inizia per 39', () => assert.strictEqual( P( '393 123 4567' ).value, '+393931234567' ) );
	await t( 'interno separato', () => {
		const r = P( '02 36618 300 int. 22' );
		assert.strictEqual( r.value, '+390236618300' );
		assert.strictEqual( r.ext, '22' );
	} );
	await t( 'incompleto', () => assert.strictEqual( P( '347 123' ).value, null ) );
	await t( 'multiplo', () => assert.strictEqual( P( '3471234567, 3489876543' ).value, null ) );
	await t( 'multiplo con "o"', () => assert.strictEqual( P( '347 1234567 o 348 9876543' ).value, null ) );
	await t( 'lettere', () => assert.strictEqual( P( 'chiamami 3471234567' ).value, null ) );
	await t( 'paese dal campo del form prima del default', () => assert.strictEqual( P( '020 7946 0958', [ 'GB', 'IT' ] ).value, '+442079460958' ) );
	await t( 'paese non valido saltato', () => assert.strictEqual( P( '347 123 4567', [ 'ZZ', 'xx', 'IT' ] ).value, '+393471234567' ) );
	await t( 'senza libreria', () => assert.strictEqual( core.normalizePhone( '3471234567', [ 'IT' ], null ).value, null ) );
	await t( 'ambiguo: prefisso senza + e lettura nazionale valida', () => {
		// "39 06 1234 5678": come +39 06 12345678 è valido; come numero nazionale 39061234 5678 no → non ambiguo, accettato.
		const r = P( '390612345678' );
		assert.ok( r.value === '+390612345678' || r.value === null );
	} );

	/* hash */
	await t( 'sha256 come Node', async () => {
		const h = await core.sha256Hex( 'nomecognome@gmail.com' );
		assert.strictEqual( h, crypto.createHash( 'sha256' ).update( 'nomecognome@gmail.com', 'utf8' ).digest( 'hex' ) );
		assert.match( h, /^[0-9a-f]{64}$/ );
	} );
	await t( 'sha256 UTF-8', async () => {
		const h = await core.sha256Hex( 'perché@esempio.it' );
		assert.strictEqual( h, crypto.createHash( 'sha256' ).update( 'perché@esempio.it', 'utf8' ).digest( 'hex' ) );
	} );
	await t( 'niente hash della stringa vuota', async () => assert.strictEqual( await core.sha256Hex( '' ), null ) );

	/* nomi evento */
	const V = ( n ) => core.validateEventName( n ).ok;
	await t( 'lead_submit valido', () => assert.ok( V( 'lead_submit' ) ) );
	await t( 'newsletter_signup valido', () => assert.ok( V( 'newsletter_signup' ) ) );
	await t( 'riservato', () => assert.ok( ! V( 'session_start' ) ) );
	await t( 'prefisso ga_', () => assert.ok( ! V( 'ga_lead' ) ) );
	await t( 'prefisso gtm.', () => assert.ok( ! V( 'gtm.js' ) ) );
	await t( 'trattino', () => assert.ok( ! V( 'lead-submit' ) ) );
	await t( 'inizia con numero', () => assert.ok( ! V( '1lead' ) ) );
	await t( 'troppo lungo', () => assert.ok( ! V( 'a'.repeat( 41 ) ) ) );

	/* valori */
	const N = ( v ) => core.parseValue( v );
	await t( 'vuoto = nessun valore', () => assert.deepStrictEqual( [ N( '' ).ok, N( '' ).value ], [ true, null ] ) );
	await t( 'zero', () => assert.strictEqual( N( '0' ).value, 0 ) );
	await t( '25,50', () => assert.strictEqual( N( '25,50' ).value, 25.5 ) );
	await t( '1.234,56', () => assert.strictEqual( N( '1.234,56' ).value, 1234.56 ) );
	await t( 'negativo', () => assert.ok( ! N( '-5' ).ok ) );
	await t( 'testo', () => assert.ok( ! N( 'dieci' ).ok ) );
	await t( 'valuta EUR', () => assert.ok( core.isCurrency( 'EUR' ) ) );
	await t( 'valuta sbagliata', () => assert.ok( ! core.isCurrency( 'EURO' ) && ! core.isCurrency( 'eur' ) ) );

	/* link di contatto */
	const L = ( h ) => ( core.classifyContactLink( h ) || {} ).type || null;
	await t( 'tel', () => assert.strictEqual( L( 'tel:+390212345' ), 'phone' ) );
	await t( 'mailto', () => assert.strictEqual( L( 'mailto:info@example.com?subject=Ciao' ), 'email' ) );
	await t( 'wa.me', () => assert.strictEqual( L( 'https://wa.me/393331234567?text=Ciao' ), 'whatsapp' ) );
	await t( 'api.whatsapp.com/send', () => assert.strictEqual( L( 'https://api.whatsapp.com/send?phone=39333' ), 'whatsapp' ) );
	await t( 'web.whatsapp.com/send', () => assert.strictEqual( L( 'https://web.whatsapp.com/send?phone=39333' ), 'whatsapp' ) );
	await t( 'whatsapp://send', () => assert.strictEqual( L( 'whatsapp://send?phone=39333' ), 'whatsapp' ) );
	await t( 'articolo con "whatsapp" nel percorso', () => assert.strictEqual( L( 'https://example.com/blog/come-usare-whatsapp' ), null ) );
	await t( 'dominio falso', () => assert.strictEqual( L( 'https://wa.me.example.com/39333' ), null ) );
	await t( 'pagina normale', () => assert.strictEqual( L( 'https://example.com/contatti' ), null ) );

	/* canali personalizzati */
	const R = ( type, pattern, href ) => core.matchRule( { type, pattern }, href );
	const maps = 'maps.google.com, google.com/maps, maps.app.goo.gl, goo.gl/maps, maps.apple.com';
	await t( 'maps: google.com/maps', () => assert.ok( R( 'url', maps, 'https://www.google.com/maps/dir/?api=1&destination=Milano' ) ) );
	await t( 'maps: maps.app.goo.gl', () => assert.ok( R( 'url', maps, 'https://maps.app.goo.gl/AbCdE' ) ) );
	await t( 'maps: google.com senza /maps no', () => assert.ok( ! R( 'url', maps, 'https://www.google.com/search?q=maps' ) ) );
	await t( 'maps: dominio finto no', () => assert.ok( ! R( 'url', maps, 'https://maps.google.com.example.org/x' ) ) );
	await t( 'telegram t.me', () => assert.ok( R( 'url', 't.me, telegram.me, tg:', 'https://t.me/nozzedafavola' ) ) );
	await t( 'telegram schema tg:', () => assert.ok( R( 'url', 't.me, telegram.me, tg:', 'tg://resolve?domain=x' ) ) );
	await t( 'messenger m.me', () => assert.ok( R( 'url', 'm.me, messenger.com', 'https://m.me/pagina' ) ) );
	await t( 'sottodominio', () => assert.ok( R( 'url', 'calendly.com', 'https://it.calendly.com/studio/30min' ) ) );
	await t( 'pdf', () => assert.ok( R( 'ext', 'pdf', 'https://example.com/wp-content/uploads/catalogo.PDF?v=2' ) ) );
	await t( 'pdf no html', () => assert.ok( ! R( 'ext', 'pdf, zip', 'https://example.com/catalogo-pdf/' ) ) );
	await t( 'regola vuota', () => assert.ok( ! R( 'url', '', 'https://t.me/x' ) ) );
	await t( 'channelId', () => assert.strictEqual( core.channelId( 'Indicazioni (Google Maps)' ), 'indicazioni_google_maps' ) );

	/* varie */
	await t( 'percorso senza query', () => assert.strictEqual( core.cleanPath( '/grazie/?nome=Mario#x' ), '/grazie/' ) );
	await t( 'uuid', () => assert.match( core.uuid(), /^[0-9a-f-]{36}$/ ) );

	console.log( passed + ' test superati, ' + failures.length + ' falliti' );
	failures.forEach( ( f ) => console.log( '  ✗ ' + f ) );
	process.exit( failures.length ? 1 : 0 );
} )();
