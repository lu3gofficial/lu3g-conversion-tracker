<?php
/**
 * Prove dell'unione col container del sito (LU3G_CT_GTM_Merge). Da riga di comando: php tests/merge.test.php
 * Container del sito inventato (nessun dato reale); file LU3G = gtm/lu3g-conversion-tracker-container.json.
 */
define( 'LU3G_CT_TEST', true );
require __DIR__ . '/../includes/class-gtm-merge.php';

$fail = 0;
$n    = 0;
function ok( $cond, $msg ) {
	global $fail, $n;
	$n++;
	if ( ! $cond ) {
		$fail++;
		echo "✗ $msg\n";
	} else {
		echo "✓ $msg\n";
	}
}
function names( $list ) {
	return array_map( function ( $x ) { return $x['name']; }, $list );
}

$lu3g_raw = file_get_contents( __DIR__ . '/../gtm/lu3g-conversion-tracker-container.json' );
$lu3g     = json_decode( $lu3g_raw, true );
$lu3g_pub = $lu3g['containerVersion']['container']['publicId'];

$site = array(
	'exportFormatVersion' => 2,
	'containerVersion'    => array(
		'accountId'   => '111',
		'containerId' => '222',
		'container'   => array( 'accountId' => '111', 'containerId' => '222', 'name' => 'sito', 'publicId' => $lu3g_pub, 'usageContext' => array( 'WEB' ) ),
		'tag'         => array(
			array( 'accountId' => '111', 'containerId' => '222', 'tagId' => '5', 'name' => 'Banner cookie', 'type' => 'cvt_KFGZD', 'firingTriggerId' => array( '2147479572' ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'tagId' => '6', 'name' => 'Linker conversioni', 'type' => 'gclidw', 'firingTriggerId' => array( '2147479573' ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'tagId' => '7', 'name' => 'Vecchia conversione Ads', 'type' => 'awct', 'firingTriggerId' => array( '8' ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'tagId' => '9', 'name' => 'Vecchio pixel Meta', 'type' => 'html', 'parameter' => array( array( 'type' => 'TEMPLATE', 'key' => 'html', 'value' => "<script>fbq('init', '1');fbq('track','PageView');</script>" ) ), 'firingTriggerId' => array( '2147479553' ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'tagId' => '10', 'name' => 'Altro evento', 'type' => 'gaawe', 'firingTriggerId' => array( '11' ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'tagId' => '30', 'name' => 'LU3G - Tag tolto dal plugin', 'type' => 'html', 'firingTriggerId' => array( '31' ) ),
		),
		'trigger'     => array(
			array( 'accountId' => '111', 'containerId' => '222', 'triggerId' => '8', 'name' => 'lead', 'type' => 'CUSTOM_EVENT', 'customEventFilter' => array( array( 'type' => 'EQUALS', 'parameter' => array( array( 'type' => 'TEMPLATE', 'key' => 'arg0', 'value' => '{{_event}}' ), array( 'type' => 'TEMPLATE', 'key' => 'arg1', 'value' => 'lead_submit' ) ) ) ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'triggerId' => '11', 'name' => 'scroll', 'type' => 'CUSTOM_EVENT', 'customEventFilter' => array( array( 'type' => 'EQUALS', 'parameter' => array( array( 'type' => 'TEMPLATE', 'key' => 'arg0', 'value' => '{{_event}}' ), array( 'type' => 'TEMPLATE', 'key' => 'arg1', 'value' => 'scroll_90' ) ) ) ) ),
			array( 'accountId' => '111', 'containerId' => '222', 'triggerId' => '31', 'name' => 'LU3G - attivatore vecchio', 'type' => 'CUSTOM_EVENT' ),
		),
		'variable'    => array(
			array( 'accountId' => '111', 'containerId' => '222', 'variableId' => '12', 'name' => 'DLV - email', 'type' => 'v', 'formatValue' => new stdClass() ),
		),
		'builtInVariable' => array( array( 'accountId' => '111', 'containerId' => '222', 'type' => 'PAGE_URL', 'name' => 'Page URL' ) ),
	),
);
$site_raw = json_encode( $site );

// parse()
ok( ! LU3G_CT_GTM_Merge::parse( $lu3g_raw )['ok'], 'rifiuta il file del plugin al posto dell\'export' );
ok( ! LU3G_CT_GTM_Merge::parse( '{"a":1}' )['ok'], 'rifiuta un JSON qualsiasi' );
ok( ! LU3G_CT_GTM_Merge::parse( $site_raw, 'GTM-ALTRO' )['ok'], 'rifiuta un container con ID diverso dal plugin' );
$p = LU3G_CT_GTM_Merge::parse( $site_raw, strtolower( $lu3g_pub ) );
ok( $p['ok'], 'accetta l\'export del sito (ID senza distinzione di maiuscole)' );

$r  = LU3G_CT_GTM_Merge::merge( $p['data'], $lu3g, array( 'events' => array( 'lead_submit', 'contact_click' ) ) );
$cv = $r['container']['containerVersion'];
$tn = names( $cv['tag'] );

ok( in_array( 'Banner cookie', $tn, true ) && in_array( 'Linker conversioni', $tn, true ) && in_array( 'Altro evento', $tn, true ), 'tiene i tag del sito' );
ok( ! in_array( 'LU3G - Linker conversioni', $tn, true ), 'niente Linker LU3G se il sito ne ha già uno' );
ok( ! in_array( 'LU3G - Tag tolto dal plugin', $tn, true ), 'toglie i tag LU3G non più generati' );
ok( ! in_array( 'LU3G - attivatore vecchio', names( $cv['trigger'] ), true ), 'toglie gli attivatori LU3G non più usati' );
ok( in_array( 'Vecchia conversione Ads', $r['report']['paused'], true ), 'mette in pausa la vecchia conversione sullo stesso evento' );
ok( in_array( 'Vecchio pixel Meta', $r['report']['paused'], true ) === in_array( 'LU3G - Meta - Pixel base', names( $lu3g['containerVersion']['tag'] ), true ), 'pausa il vecchio pixel Meta solo se c\'è quello LU3G' );
ok( ! in_array( 'Altro evento', $r['report']['paused'], true ), 'non tocca i tag su altri eventi' );

$ids = array();
foreach ( array( 'tag' => 'tagId', 'trigger' => 'triggerId', 'variable' => 'variableId', 'customTemplate' => 'templateId' ) as $k => $ik ) {
	foreach ( $cv[ $k ] ?? array() as $x ) {
		$ids[] = $x[ $ik ];
	}
}
ok( count( $ids ) === count( array_unique( $ids ) ), 'nessun ID ripetuto' );
ok( min( array_map( 'intval', array_diff( $ids, array( '5', '6', '7', '9', '10', '8', '11', '12' ) ) ) ) > 31, 'ID nuovi dopo il più alto del container' );

$tr  = array_column( $cv['trigger'], 'triggerId' );
$tpl = array();
foreach ( $cv['customTemplate'] as $t ) {
	$tpl[] = 'cvt_222_' . $t['templateId'];
}
$ok_ref = true;
foreach ( $cv['tag'] as $t ) {
	if ( 0 !== strpos( $t['name'], 'LU3G - ' ) ) {
		continue;
	}
	foreach ( $t['firingTriggerId'] as $id ) {
		$ok_ref = $ok_ref && ( in_array( $id, $tr, true ) || (int) $id >= 2147479000 );
	}
	if ( 0 === strpos( $t['type'], 'cvt_' ) ) {
		$ok_ref = $ok_ref && in_array( $t['type'], $tpl, true );
	}
	$ok_ref = $ok_ref && '111' === $t['accountId'] && '222' === $t['containerId'];
}
ok( $ok_ref, 'attivatori, modelli e account dei tag LU3G riallineati' );

$json = json_encode( LU3G_CT_GTM_Merge::for_json( $r['container'] ) );
ok( false !== strpos( $json, '"formatValue":{}' ), 'gli oggetti vuoti restano {}' );
ok( in_array( 'EVENT', array_column( $cv['builtInVariable'], 'type' ), true ) && in_array( 'PAGE_URL', array_column( $cv['builtInVariable'], 'type' ), true ), 'variabili integrate unite' );

// Seconda volta sul risultato: niente da cambiare.
$p2 = LU3G_CT_GTM_Merge::parse( $json );
$r2 = LU3G_CT_GTM_Merge::merge( $p2['data'], $lu3g, array( 'events' => array( 'lead_submit', 'contact_click' ) ) );
ok( 0 === $r2['report']['counts']['added'] && 0 === $r2['report']['counts']['updated'] && 0 === $r2['report']['counts']['removed'] && 0 === $r2['report']['counts']['paused'], 'rifatta sul risultato non cambia niente' );

// Senza pausa automatica: solo avvisi.
$r3 = LU3G_CT_GTM_Merge::merge( $p['data'], $lu3g, array( 'events' => array( 'lead_submit' ), 'pause_duplicates' => false ) );
ok( ! $r3['report']['paused'] && $r3['report']['notes'], 'senza pausa automatica solo avvisi' );

echo "\n" . ( $n - $fail ) . "/$n prove superate\n";
exit( $fail ? 1 : 0 );
