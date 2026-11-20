<?php
/**
 * Container COMPLETO per GTM (1.3.0): l'export del container del sito + gli elementi LU3G aggiornati.
 *
 * Perché: il file "solo LU3G" va importato con Unisci. Se in GTM si sceglie "Sovrascrivi", GTM sostituisce l'intero
 * container con il file e cancella tutto il resto (banner dei cookie, Google tag base, linker…). Successo davvero su
 * un sito. Il file completo contiene tutto il container, quindi resta corretto anche con "Sovrascrivi".
 *
 * Regole dell'unione (per nome, come fa GTM):
 * - Gli elementi del sito che non sono LU3G restano identici (stesso ID, cartella, stato di pausa).
 * - Elementi LU3G ("LU3G - …", modelli "LU3G CT …") già presenti: contenuto aggiornato, ID e cartella mantenuti,
 *   stato di pausa mantenuto (se un tag LU3G è stato messo in pausa a mano, resta in pausa).
 * - Elementi LU3G nuovi: ID nuovi dopo il più alto del container (contatore unico per tutti i tipi, come GTM).
 * - Elementi LU3G non più generati dal plugin: tag tolti; attivatori, variabili e modelli tolti solo se nessun altro
 *   elemento li usa.
 * - Attivatori dei tag LU3G e tipi dei modelli (cvt_<container>_<modello>) riallineati agli ID finali.
 * - Linker LU3G non aggiunto se il sito ha già un Linker conversioni attivo.
 * - Facoltativo: mette in pausa i vecchi tag del sito che partono sugli stessi eventi LU3G (contano doppio) e i vecchi
 *   pixel base Meta attivi quando il file contiene il pixel base LU3G.
 *
 * Classe senza funzioni di WordPress (tranne __ se presente), così si prova da riga di comando.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'LU3G_CT_TEST' ) ) {
	exit;
}

final class LU3G_CT_GTM_Merge {

	const ITEM_PREFIX = 'LU3G - ';
	const TPL_PREFIX  = 'LU3G CT ';
	const LU3G_ACCOUNT = '6000000000';

	private static function t( $s ) {
		return function_exists( '__' ) ? __( $s, 'lu3g-conversion-tracker' ) : $s; // phpcs:ignore WordPress.WP.I18n
	}

	public static function is_lu3g( $name, $kind = 'item' ) {
		return 0 === strpos( (string) $name, 'customTemplate' === $kind ? self::TPL_PREFIX : self::ITEM_PREFIX );
	}

	/**
	 * Controlla che il testo sia l'export di un container web di GTM (e non il file del plugin).
	 *
	 * @return array{ok:bool,error?:string,data?:array}
	 */
	public static function parse( $raw, $public_id = '' ) {
		$raw = is_string( $raw ) ? preg_replace( '/^\xEF\xBB\xBF/', '', $raw ) : '';
		$o   = json_decode( (string) $raw );
		$d   = is_object( $o ) ? self::to_array( $o ) : null;
		if ( ! is_array( $d ) || empty( $d['containerVersion'] ) || ! is_array( $d['containerVersion'] ) ) {
			return array( 'ok' => false, 'error' => self::t( 'Il file non è un export di Google Tag Manager (manca "containerVersion"). In GTM: Amministrazione → Esporta container, oppure Versioni → la versione live → Esporta.' ) );
		}
		if ( 2 !== (int) ( $d['exportFormatVersion'] ?? 0 ) ) {
			return array( 'ok' => false, 'error' => self::t( 'Formato di export non riconosciuto (serve exportFormatVersion 2).' ) );
		}
		$c = $d['containerVersion']['container'] ?? array();
		if ( self::LU3G_ACCOUNT === (string) ( $d['containerVersion']['accountId'] ?? '' ) || in_array( ( $c['name'] ?? '' ), array( 'LU3G Conversion Tracker', 'SOLO LU3G - importa con UNISCI, non Sovrascrivi' ), true ) ) {
			return array( 'ok' => false, 'error' => self::t( 'Questo è il file generato dal plugin, non l\'export del container del sito. Esporta il container da GTM e carica quello.' ) );
		}
		if ( isset( $c['usageContext'] ) && ! in_array( 'WEB', (array) $c['usageContext'], true ) ) {
			return array( 'ok' => false, 'error' => self::t( 'Il container non è di tipo Web.' ) );
		}
		$pub = (string) ( $c['publicId'] ?? '' );
		if ( '' !== $public_id && '' !== $pub && strtoupper( $pub ) !== strtoupper( $public_id ) ) {
			/* translators: 1: ID del file, 2: ID nel plugin */
			return array( 'ok' => false, 'error' => sprintf( self::t( 'Il file è del container %1$s, ma nel plugin è impostato %2$s. Carica l\'export del container giusto.' ), $pub, $public_id ) );
		}
		return array( 'ok' => true, 'data' => $d );
	}

	const EMPTY_OBJECT = '__lu3g_empty_object__';

	/** Oggetti → array, ma un oggetto vuoto ({} nell'export, es. formatValue) resta riconoscibile. */
	private static function to_array( $v ) {
		if ( is_object( $v ) ) {
			$a = get_object_vars( $v );
			if ( ! $a ) {
				return array( self::EMPTY_OBJECT => true );
			}
			return array_map( array( __CLASS__, 'to_array' ), $a );
		}
		return is_array( $v ) ? array_map( array( __CLASS__, 'to_array' ), $v ) : $v;
	}

	/** Per json_encode: gli oggetti vuoti tornano {} (GTM li vuole così, non []). */
	public static function for_json( $v ) {
		if ( is_array( $v ) ) {
			if ( array( self::EMPTY_OBJECT => true ) === $v ) {
				return new stdClass();
			}
			return array_map( array( __CLASS__, 'for_json' ), $v );
		}
		return $v;
	}

	/** Contenuto confrontabile: senza ID, impronte, account, container, cartella e pausa. */
	private static function norm( array $x ) {
		foreach ( array( 'accountId', 'containerId', 'tagId', 'triggerId', 'variableId', 'templateId', 'fingerprint', 'parentFolderId', 'path', 'tagManagerUrl', 'paused', 'workspaceId' ) as $k ) {
			unset( $x[ $k ] );
		}
		// GTM riformatta il codice dei modelli (spazi, a capo): conta solo il contenuto.
		if ( isset( $x['templateData'] ) ) {
			$x['templateData'] = preg_replace( '/\s+/', '', (string) $x['templateData'] );
		}
		// stopOnSetupFailure / stopTeardownOnFailure false = valore predefinito, GTM a volte non lo esporta.
		foreach ( array( 'setupTag', 'teardownTag' ) as $k ) {
			if ( isset( $x[ $k ] ) && is_array( $x[ $k ] ) ) {
				foreach ( $x[ $k ] as $i => $st ) {
					foreach ( array( 'stopOnSetupFailure', 'stopTeardownOnFailure' ) as $f ) {
						if ( isset( $st[ $f ] ) && ! $st[ $f ] ) {
							unset( $x[ $k ][ $i ][ $f ] );
						}
					}
				}
			}
		}
		$sort = function ( $v ) use ( &$sort ) {
			if ( ! is_array( $v ) ) {
				return is_bool( $v ) ? ( $v ? 'true' : 'false' ) : (string) $v;
			}
			$assoc = array_keys( $v ) !== range( 0, count( $v ) - 1 );
			$out   = array();
			foreach ( $v as $k => $vv ) {
				$out[ $k ] = $sort( $vv );
			}
			if ( $assoc ) {
				ksort( $out );
			} elseif ( isset( $out[0] ) && is_array( $out[0] ) && isset( $out[0]['key'] ) ) {
				usort(
					$out,
					function ( $a, $b ) {
						return strcmp( (string) ( $a['key'] ?? '' ), (string) ( $b['key'] ?? '' ) );
					}
				);
			}
			return $out;
		};
		$x = $sort( $x );
		// Lista vuota di parametri = nessun parametro.
		if ( isset( $x['parameter'] ) && array() === $x['parameter'] ) {
			unset( $x['parameter'] );
		}
		return json_encode( $x );
	}

	/** L'attivatore (evento personalizzato) scatta su almeno uno degli eventi? */
	private static function trigger_hits( array $tr, array $events ) {
		if ( 'CUSTOM_EVENT' !== ( $tr['type'] ?? '' ) || empty( $tr['customEventFilter'] ) ) {
			return false;
		}
		foreach ( $tr['customEventFilter'] as $f ) {
			$arg1 = '';
			foreach ( (array) ( $f['parameter'] ?? array() ) as $p ) {
				if ( 'arg1' === ( $p['key'] ?? '' ) ) {
					$arg1 = (string) ( $p['value'] ?? '' );
				}
			}
			$neg = false;
			foreach ( (array) ( $f['parameter'] ?? array() ) as $p ) {
				if ( 'negate' === ( $p['key'] ?? '' ) && 'true' === (string) ( $p['value'] ?? '' ) ) {
					$neg = true;
				}
			}
			if ( $neg ) {
				continue;
			}
			$ci = false;
			foreach ( (array) ( $f['parameter'] ?? array() ) as $p ) {
				if ( 'ignore_case' === ( $p['key'] ?? '' ) && 'true' === (string) ( $p['value'] ?? '' ) ) {
					$ci = true;
				}
			}
			foreach ( $events as $ev ) {
				switch ( $f['type'] ?? '' ) {
					case 'EQUALS':
						$hit = $ci ? 0 === strcasecmp( $ev, $arg1 ) : $ev === $arg1;
						break;
					case 'CONTAINS':
						$hit = '' !== $arg1 && false !== ( $ci ? stripos( $ev, $arg1 ) : strpos( $ev, $arg1 ) );
						break;
					case 'STARTS_WITH':
						$hit = '' !== $arg1 && 0 === ( $ci ? stripos( $ev, $arg1 ) : strpos( $ev, $arg1 ) );
						break;
					case 'MATCH_REGEX':
						$hit = '' !== $arg1 && 1 === @preg_match( '~' . str_replace( '~', '\~', $arg1 ) . '~' . ( $ci ? 'i' : '' ), $ev ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
						break;
					default:
						$hit = false;
				}
				if ( $hit ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Unisce.
	 *
	 * @param array $site  Export del container del sito (già controllato con parse()).
	 * @param array $lu3g  File generato dal plugin (LU3G_CT_GTM::container()).
	 * @param array $opts  events (eventi LU3G), pause_duplicates (bool).
	 * @return array{container:array,report:array}
	 */
	public static function merge( array $site, array $lu3g, array $opts = array() ) {
		$opts   = array_merge( array( 'events' => array(), 'pause_duplicates' => true ), $opts );
		$cv     = $site['containerVersion'];
		$gen    = $lu3g['containerVersion'];
		$acc    = (string) ( $cv['accountId'] ?? $cv['container']['accountId'] ?? '' );
		$cid    = (string) ( $cv['containerId'] ?? $cv['container']['containerId'] ?? '' );
		$report = array(
			'added'   => array(),
			'updated' => array(),
			'same'    => array(),
			'removed' => array(),
			'kept'    => array(),
			'paused'  => array(),
			'notes'   => array(),
		);
		foreach ( array( 'tag', 'trigger', 'variable', 'customTemplate', 'folder', 'builtInVariable' ) as $k ) {
			$cv[ $k ] = isset( $cv[ $k ] ) && is_array( $cv[ $k ] ) ? array_values( $cv[ $k ] ) : array();
		}

		// Contatore unico per tutti i tipi.
		$max = 0;
		foreach ( array( 'tag' => 'tagId', 'trigger' => 'triggerId', 'variable' => 'variableId', 'customTemplate' => 'templateId', 'folder' => 'folderId' ) as $k => $idk ) {
			foreach ( $cv[ $k ] as $x ) {
				$n = (int) ( $x[ $idk ] ?? 0 );
				if ( $n < 2147479000 ) {
					$max = max( $max, $n );
				}
			}
		}
		$next = function () use ( &$max ) {
			return (string) ( ++$max );
		};

		$labels = array(
			'tag'            => self::t( 'tag' ),
			'trigger'        => self::t( 'attivatore' ),
			'variable'       => self::t( 'variabile' ),
			'customTemplate' => self::t( 'modello' ),
		);

		// Linker già presente nel sito (attivo, non LU3G): niente Linker LU3G.
		$site_linker = false;
		foreach ( $cv['tag'] as $t ) {
			if ( 'gclidw' === ( $t['type'] ?? '' ) && empty( $t['paused'] ) && ! self::is_lu3g( $t['name'] ?? '' ) ) {
				$site_linker = true;
			}
		}
		$gen_tags = array();
		foreach ( (array) ( $gen['tag'] ?? array() ) as $t ) {
			if ( $site_linker && 'gclidw' === ( $t['type'] ?? '' ) ) {
				$report['notes'][] = self::t( 'Il container ha già un Linker conversioni attivo: il Linker LU3G non viene aggiunto.' );
				continue;
			}
			$gen_tags[] = $t;
		}
		$gen['tag'] = $gen_tags;

		// Mappe ID: [tipo][id nel file del plugin] => id finale.
		$map = array(
			'trigger'        => array(),
			'customTemplate' => array(),
		);
		$idk = array(
			'tag'            => 'tagId',
			'trigger'        => 'triggerId',
			'variable'       => 'variableId',
			'customTemplate' => 'templateId',
		);

		// Ordine: modelli e attivatori prima dei tag (servono le mappe).
		foreach ( array( 'customTemplate', 'trigger', 'variable', 'tag' ) as $kind ) {
			$by_name = array();
			foreach ( $cv[ $kind ] as $i => $x ) {
				$by_name[ (string) ( $x['name'] ?? '' ) ] = $i;
			}
			foreach ( (array) ( $gen[ $kind ] ?? array() ) as $x ) {
				$name = (string) $x['name'];
				$gid  = (string) ( $x[ $idk[ $kind ] ] ?? '' );
				if ( 'tag' === $kind ) {
					foreach ( array( 'firingTriggerId', 'blockingTriggerId' ) as $fk ) {
						if ( isset( $x[ $fk ] ) ) {
							$x[ $fk ] = array_values(
								array_map(
									function ( $id ) use ( $map ) {
										return $map['trigger'][ (string) $id ] ?? (string) $id;
									},
									(array) $x[ $fk ]
								)
							);
						}
					}
					if ( preg_match( '/^cvt_[^_]+_(\d+)$/', (string) ( $x['type'] ?? '' ), $m ) && isset( $map['customTemplate'][ $m[1] ] ) ) {
						$x['type'] = 'cvt_' . $cid . '_' . $map['customTemplate'][ $m[1] ];
					}
				}
				$x['accountId']   = $acc;
				$x['containerId'] = $cid;
				if ( isset( $by_name[ $name ] ) ) {
					$old              = $cv[ $kind ][ $by_name[ $name ] ];
					$x[ $idk[ $kind ] ] = (string) $old[ $idk[ $kind ] ];
					if ( isset( $old['parentFolderId'] ) ) {
						$x['parentFolderId'] = $old['parentFolderId'];
					}
					if ( 'tag' === $kind && ! empty( $old['paused'] ) ) {
						$x['paused'] = true;
						/* translators: %s: nome del tag */
						$report['notes'][] = sprintf( self::t( '"%s" era in pausa nel container e resta in pausa.' ), $name );
					}
					if ( 'customTemplate' === $kind && isset( $old['galleryReference'] ) ) {
						$x['galleryReference'] = $old['galleryReference'];
					}
					$same = self::norm( $old ) === self::norm( $x );
					if ( $same ) {
						// Già uguale: resta esattamente com'è nel container (formattazione di GTM, impronta).
						$x = $old;
					} else {
						unset( $x['fingerprint'] );
					}
					$cv[ $kind ][ $by_name[ $name ] ] = $x;
					$report[ $same ? 'same' : 'updated' ][] = $labels[ $kind ] . ': ' . $name;
				} else {
					$x[ $idk[ $kind ] ] = $next();
					unset( $x['fingerprint'] );
					$cv[ $kind ][]      = $x;
					$by_name[ $name ]   = count( $cv[ $kind ] ) - 1;
					$report['added'][]  = $labels[ $kind ] . ': ' . $name;
				}
				if ( isset( $map[ $kind ] ) ) {
					$map[ $kind ][ $gid ] = (string) $x[ $idk[ $kind ] ];
				}
			}
		}

		// Elementi LU3G non più generati.
		$gen_names = array();
		foreach ( array( 'customTemplate', 'trigger', 'variable', 'tag' ) as $kind ) {
			$gen_names[ $kind ] = array_map(
				function ( $x ) {
					return (string) $x['name'];
				},
				(array) ( $gen[ $kind ] ?? array() )
			);
		}
		$stale = function ( $x, $kind ) use ( $gen_names ) {
			return self::is_lu3g( $x['name'] ?? '', $kind ) && ! in_array( (string) $x['name'], $gen_names[ $kind ], true );
		};
		$cv['tag'] = array_values(
			array_filter(
				$cv['tag'],
				function ( $x ) use ( $stale, &$report ) {
					if ( $stale( $x, 'tag' ) ) {
						$report['removed'][] = self::t( 'tag' ) . ': ' . $x['name'];
						return false;
					}
					return true;
				}
			)
		);
		$used_tr = array();
		foreach ( $cv['tag'] as $t ) {
			foreach ( array_merge( (array) ( $t['firingTriggerId'] ?? array() ), (array) ( $t['blockingTriggerId'] ?? array() ) ) as $id ) {
				$used_tr[ (string) $id ] = true;
			}
		}
		$cv['trigger'] = array_values(
			array_filter(
				$cv['trigger'],
				function ( $x ) use ( $stale, $used_tr, &$report ) {
					if ( $stale( $x, 'trigger' ) && empty( $used_tr[ (string) $x['triggerId'] ] ) ) {
						$report['removed'][] = self::t( 'attivatore' ) . ': ' . $x['name'];
						return false;
					}
					return true;
				}
			)
		);
		$blob_wo = function ( $skip_kind, $skip_name ) use ( &$cv ) {
			$s = '';
			foreach ( array( 'tag', 'trigger', 'variable' ) as $k ) {
				foreach ( $cv[ $k ] as $x ) {
					if ( $k === $skip_kind && $x['name'] === $skip_name ) {
						continue;
					}
					$s .= json_encode( $x );
				}
			}
			return $s;
		};
		$cv['variable'] = array_values(
			array_filter(
				$cv['variable'],
				function ( $x ) use ( $stale, $blob_wo, &$report ) {
					$ref = trim( (string) json_encode( '{{' . $x['name'] . '}}' ), '"' );
					if ( $stale( $x, 'variable' ) && false === strpos( $blob_wo( 'variable', $x['name'] ), $ref ) ) {
						$report['removed'][] = self::t( 'variabile' ) . ': ' . $x['name'];
						return false;
					}
					return true;
				}
			)
		);
		$used_types = array();
		foreach ( $cv['tag'] as $t ) {
			$used_types[ (string) ( $t['type'] ?? '' ) ] = true;
		}
		$cv['customTemplate'] = array_values(
			array_filter(
				$cv['customTemplate'],
				function ( $x ) use ( $stale, $used_types, $cid, &$report ) {
					if ( $stale( $x, 'customTemplate' ) && empty( $used_types[ 'cvt_' . $cid . '_' . $x['templateId'] ] ) ) {
						$report['removed'][] = self::t( 'modello' ) . ': ' . $x['name'];
						return false;
					}
					return true;
				}
			)
		);

		// Variabili integrate: unione per tipo.
		$have = array();
		foreach ( $cv['builtInVariable'] as $b ) {
			$have[ (string) ( $b['type'] ?? '' ) ] = true;
		}
		foreach ( (array) ( $gen['builtInVariable'] ?? array() ) as $b ) {
			if ( empty( $have[ (string) $b['type'] ] ) ) {
				$b['accountId']            = $acc;
				$b['containerId']          = $cid;
				$cv['builtInVariable'][]   = $b;
				$report['added'][]         = self::t( 'variabile integrata' ) . ': ' . $b['name'];
			}
		}

		// Vecchi tag che contano doppio.
		$events   = array_values( array_filter( array_map( 'strval', (array) $opts['events'] ) ) );
		$tr_by_id = array();
		foreach ( $cv['trigger'] as $tr ) {
			$tr_by_id[ (string) $tr['triggerId'] ] = $tr;
		}
		$lu3g_meta_base = in_array( 'LU3G - Meta - Pixel base', $gen_names['tag'], true );
		$dups           = array();
		foreach ( $cv['tag'] as $i => $t ) {
			if ( self::is_lu3g( $t['name'] ?? '' ) || ! empty( $t['paused'] ) ) {
				continue;
			}
			$type = (string) ( $t['type'] ?? '' );
			$html = '';
			foreach ( (array) ( $t['parameter'] ?? array() ) as $p ) {
				if ( 'html' === ( $p['key'] ?? '' ) ) {
					$html = (string) ( $p['value'] ?? '' );
				}
			}
			$conv = in_array( $type, array( 'gaawe', 'awct', 'sp', 'flc', 'fls' ), true ) || ( 'html' === $type && false !== strpos( $html, 'fbq(' ) && false === strpos( $html, "'init'" ) && false === strpos( $html, '"init"' ) );
			$hits = false;
			foreach ( (array) ( $t['firingTriggerId'] ?? array() ) as $id ) {
				if ( isset( $tr_by_id[ (string) $id ] ) && self::trigger_hits( $tr_by_id[ (string) $id ], $events ) ) {
					$hits = true;
				}
			}
			$meta_base = $lu3g_meta_base && 'html' === $type && preg_match( '/fbq\(\s*[\'"]init[\'"]/', $html );
			if ( ( $conv && $hits ) || $meta_base ) {
				$dups[] = $i;
			}
		}
		foreach ( $dups as $i ) {
			$name = (string) $cv['tag'][ $i ]['name'];
			if ( $opts['pause_duplicates'] ) {
				$cv['tag'][ $i ]['paused'] = true;
				unset( $cv['tag'][ $i ]['fingerprint'] );
				$report['paused'][] = $name;
			} else {
				/* translators: %s: nome del tag */
				$report['notes'][] = sprintf( self::t( 'Attenzione: "%s" è attivo e parte sugli stessi eventi dei tag LU3G (conta doppio). Mettilo in pausa.' ), $name );
			}
		}

		foreach ( array( 'tag', 'trigger', 'variable', 'customTemplate' ) as $k ) {
			foreach ( $cv[ $k ] as $x ) {
				if ( ! self::is_lu3g( $x['name'] ?? '', $k ) ) {
					$report['kept'][] = $labels[ $k ] . ': ' . $x['name'] . ( 'tag' === $k && ! empty( $x['paused'] ) ? ' ' . self::t( '(in pausa)' ) : '' );
				}
			}
		}
		foreach ( array( 'folder' ) as $k ) {
			if ( ! $cv[ $k ] ) {
				unset( $cv[ $k ] );
			}
		}

		$cv['name']        = 'LU3G Conversion Tracker – container completo';
		$cv['description'] = sprintf( 'Generato da LU3G Conversion Tracker: container del sito + elementi LU3G. Aggiunti %d, aggiornati %d, tolti %d, messi in pausa %d.', count( $report['added'] ), count( $report['updated'] ), count( $report['removed'] ), count( $report['paused'] ) );
		$site['containerVersion'] = $cv;
		$site['exportTime']       = gmdate( 'Y-m-d H:i:s' );
		$report['counts']         = array(
			'added'   => count( $report['added'] ),
			'updated' => count( $report['updated'] ),
			'same'    => count( $report['same'] ),
			'removed' => count( $report['removed'] ),
			'kept'    => count( $report['kept'] ),
			'paused'  => count( $report['paused'] ),
		);
		$report['notes'] = array_values( array_unique( $report['notes'] ) );
		return array(
			'container' => $site,
			'report'    => $report,
		);
	}
}
