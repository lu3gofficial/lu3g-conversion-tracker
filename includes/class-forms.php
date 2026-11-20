<?php
/**
 * Censimento dei form.
 *
 * Legge _elementor_data di pagine, template, popup (ogni tipo di contenuto non nel cestino) e trova:
 * - il widget Form classico di Elementor Pro (widgetType "form"): adapter "elementor_form", supportato;
 * - Atomic Form (elemento "e-form", Elementor Pro 4.x): riconosciuto ma NON ancora supportato in questa versione
 *   (usa un ciclo di invio diverso; serve un adapter suo, provato). Lo si mostra nel pannello come tale.
 *
 * Chiave del form = "id documento:id widget" (il documento è il template per header, popup, widget globali): è la
 * definizione del form, distinta dalla pagina visitata e dalla singola istanza mostrata (quella la crea lo script).
 * Il nome del form non è una chiave: due form possono chiamarsi uguale.
 *
 * Il censimento resta in un'opzione e si aggiorna quando Elementor salva un documento (solo quel documento), quando
 * un contenuto va nel cestino, e con "Riscansiona" nel pannello. Nessuna scansione durante le visite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Forms {

	const OPTION = 'lu3g_ct_forms';

	public static function init() {
		add_action( 'elementor/document/after_save', array( __CLASS__, 'on_document_save' ), 20, 1 );
		add_action( 'wp_trash_post', array( __CLASS__, 'forget_post' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'forget_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'scan_post' ) );
	}

	public static function has_elementor_pro() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/** Form censiti; alla prima richiesta (o se l'opzione manca) si scansiona tutto, una volta. */
	public static function all() {
		$c = get_option( self::OPTION );
		if ( ! is_array( $c ) || ! isset( $c['forms'] ) ) {
			$c = self::scan();
		}
		return $c['forms'];
	}

	public static function last_scan() {
		$c = get_option( self::OPTION );
		return is_array( $c ) && isset( $c['time'] ) ? (int) $c['time'] : 0;
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/** Scansione completa (pannello, attivazione). */
	public static function scan() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- scansione amministrativa, non durante le visite
		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_type, p.post_status, m.meta_value
			FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = '_elementor_data'
			AND ( m.meta_value LIKE '%\"widgetType\":\"form\"%' OR m.meta_value LIKE '%\"e-form\"%' )
			AND p.post_type NOT IN ('revision','nav_menu_item')
			AND p.post_status NOT IN ('trash','auto-draft','inherit')",
			ARRAY_A
		);
		$forms = array();
		foreach ( (array) $rows as $row ) {
			foreach ( self::from_row( $row ) as $key => $f ) {
				$forms[ $key ] = $f;
			}
		}
		return self::store( $forms );
	}

	/** Riscansiona un solo documento. */
	public static function scan_post( $post_id ) {
		$post_id = (int) $post_id;
		$c       = get_option( self::OPTION );
		if ( ! is_array( $c ) || ! isset( $c['forms'] ) ) {
			return; // non ancora censito: lo farà la prima scansione completa
		}
		$forms = array_filter(
			$c['forms'],
			function ( $f ) use ( $post_id ) {
				return (int) $f['post_id'] !== $post_id;
			}
		);
		$p = get_post( $post_id );
		if ( $p && ! in_array( $p->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) && 'revision' !== $p->post_type ) {
			$row = array(
				'ID'          => $p->ID,
				'post_title'  => $p->post_title,
				'post_type'   => $p->post_type,
				'post_status' => $p->post_status,
				'meta_value'  => (string) get_post_meta( $p->ID, '_elementor_data', true ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			);
			foreach ( self::from_row( $row ) as $key => $f ) {
				$forms[ $key ] = $f;
			}
		}
		self::store( $forms );
	}

	public static function on_document_save( $document ) {
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			self::scan_post( $document->get_main_id() );
		}
	}

	public static function forget_post( $post_id ) {
		$c = get_option( self::OPTION );
		if ( is_array( $c ) && isset( $c['forms'] ) ) {
			$post_id = (int) $post_id;
			self::store(
				array_filter(
					$c['forms'],
					function ( $f ) use ( $post_id ) {
						return (int) $f['post_id'] !== $post_id;
					}
				)
			);
		}
	}

	private static function store( array $forms ) {
		uasort(
			$forms,
			function ( $a, $b ) {
				return strcasecmp( $a['post_title'] . $a['name'], $b['post_title'] . $b['name'] );
			}
		);
		$c = array(
			'time'  => time(),
			'forms' => $forms,
		);
		update_option( self::OPTION, $c, false );
		return $c;
	}

	private static function from_row( array $row ) {
		$data = json_decode( (string) $row['meta_value'], true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$found = array();
		self::walk( $data, $found );
		$out = array();
		foreach ( $found as $el ) {
			$key         = (int) $row['ID'] . ':' . $el['id'];
			$out[ $key ] = self::describe( $el, $row, $key );
		}
		return $out;
	}

	private static function walk( array $elements, array &$found ) {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) || ! isset( $el['id'] ) ) {
				continue;
			}
			$wt = (string) ( $el['widgetType'] ?? '' );
			$et = (string) ( $el['elType'] ?? '' );
			if ( ( 'widget' === $et && 'form' === $wt ) || 'e-form' === $et || 'e-form' === $wt ) {
				$found[] = $el;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk( $el['elements'], $found );
			}
		}
	}

	private static function describe( array $el, array $row, $key ) {
		$s      = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
		$atomic = 'form' !== ( $el['widgetType'] ?? '' );
		$fields = array();
		if ( ! $atomic ) {
			foreach ( (array) ( $s['form_fields'] ?? array() ) as $i => $f ) {
				$id       = (string) ( $f['custom_id'] ?? ( 'field_' . $i ) );
				$fields[] = array(
					'id'    => $id,
					'type'  => (string) ( $f['field_type'] ?? 'text' ),
					'label' => wp_strip_all_tags( (string) ( $f['field_label'] ?? $id ) ),
				);
			}
		}
		$ct = array();
		foreach ( LU3G_CT_Settings::form_keys() as $k ) {
			if ( isset( $s[ $k ] ) && '' !== $s[ $k ] ) {
				$ct[ $k ] = is_scalar( $s[ $k ] ) ? (string) $s[ $k ] : '';
			}
		}
		$name = $atomic ? (string) ( $s['form-name']['value'] ?? ( $s['form_name'] ?? 'Atomic Form' ) ) : (string) ( $s['form_name'] ?? 'New Form' );
		return array(
			'key'            => $key,
			'post_id'        => (int) $row['ID'],
			'element_id'     => (string) $el['id'],
			'name'           => wp_strip_all_tags( is_scalar( $name ) ? $name : 'Atomic Form' ),
			'post_title'     => '' !== (string) $row['post_title'] ? (string) $row['post_title'] : '#' . $row['ID'],
			'post_type'      => (string) $row['post_type'],
			'status'         => (string) $row['post_status'],
			'adapter'        => $atomic ? 'atomic_form' : 'elementor_form',
			'adapter_status' => $atomic ? 'unsupported' : 'supported',
			'fields'         => $fields,
			'ct'             => $ct,
		);
	}

	/**
	 * Scrive impostazioni lu3g_ct_* nel widget, passando da Elementor (come l'editor: CSS e cache rigenerati).
	 *
	 * @return true|WP_Error
	 */
	public static function write_settings( $key, array $values ) {
		$form = self::get( $key );
		if ( ! $form || 'elementor_form' !== $form['adapter'] ) {
			return new WP_Error( 'lu3g_ct_form', __( 'Form non trovato: riscansiona.', 'lu3g-conversion-tracker' ) );
		}
		$raw  = get_post_meta( $form['post_id'], '_elementor_data', true );
		$data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'lu3g_ct_data', __( 'Dati di Elementor illeggibili.', 'lu3g-conversion-tracker' ) );
		}
		$allowed = array_flip( LU3G_CT_Settings::form_keys() );
		$found   = self::patch(
			$data,
			$form['element_id'],
			function ( array &$el ) use ( $values, $allowed ) {
				foreach ( $values as $k => $v ) {
					if ( ! isset( $allowed[ $k ] ) ) {
						continue;
					}
					$v = sanitize_text_field( (string) $v );
					if ( '' === $v ) {
						unset( $el['settings'][ $k ] );
					} else {
						$el['settings'][ $k ] = $v;
					}
				}
			}
		);
		if ( ! $found ) {
			return new WP_Error( 'lu3g_ct_missing', __( 'Il form non c\'è più nella pagina: riscansiona.', 'lu3g-conversion-tracker' ) );
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$doc = \Elementor\Plugin::$instance->documents->get( $form['post_id'], false );
			if ( $doc ) {
				if ( false === $doc->save( array( 'elements' => $data ) ) ) {
					return new WP_Error( 'lu3g_ct_save', __( 'Elementor non ha salvato la pagina (permessi?).', 'lu3g-conversion-tracker' ) );
				}
				self::scan_post( $form['post_id'] );
				return true;
			}
		}
		update_metadata( 'post', $form['post_id'], '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		delete_post_meta( $form['post_id'], '_elementor_element_cache' );
		self::scan_post( $form['post_id'] );
		return true;
	}

	private static function patch( array &$elements, $id, callable $cb ) {
		foreach ( $elements as &$el ) {
			if ( is_array( $el ) && ( $el['id'] ?? '' ) === $id && 'form' === ( $el['widgetType'] ?? '' ) ) {
				if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
					$el['settings'] = array();
				}
				$cb( $el );
				return true;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) && self::patch( $el['elements'], $id, $cb ) ) {
				return true;
			}
		}
		return false;
	}

	public static function edit_url( $post_id ) {
		return add_query_arg(
			array(
				'post'   => (int) $post_id,
				'action' => 'elementor',
			),
			admin_url( 'post.php' )
		);
	}
}
