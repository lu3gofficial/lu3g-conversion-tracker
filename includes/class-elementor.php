<?php
/**
 * Sezione "Tracciamento LU3G" nel widget Form di Elementor Pro (scheda Contenuto, dopo "Opzioni aggiuntive").
 *
 * I valori si salvano con il widget (chiavi lu3g_ct_*): sono la sola fonte delle impostazioni del singolo form.
 * Ogni controllo ha "Eredita" (vuoto) come predefinito, così un form nuovo funziona con le impostazioni globali senza
 * toccare niente. Gli ID dei controlli hanno il prefisso lu3g_ct_ per non scontrarsi con quelli di Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LU3G_CT_Elementor {

	public static function init() {
		add_action( 'elementor/element/form/section_form_options/after_section_end', array( __CLASS__, 'controls' ), 10, 2 );
	}

	/**
	 * @param \Elementor\Widget_Base $widget Widget Form.
	 */
	public static function controls( $widget, $args = array() ) {
		$g = LU3G_CT_Settings::get();
		$widget->start_controls_section(
			'lu3g_ct_section',
			array(
				'label' => __( 'Tracciamento LU3G', 'lu3g-conversion-tracker' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$widget->add_control(
			'lu3g_ct_intro',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Dopo un invio riuscito il form manda un evento nel dataLayer di Google Tag Manager. Lascia "Eredita" per usare le impostazioni di LU3G Tracking.', 'lu3g-conversion-tracker' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$inherit = __( 'Eredita', 'lu3g-conversion-tracker' );

		$widget->add_control(
			'lu3g_ct_tracking',
			array(
				'label'   => __( 'Traccia questo form', 'lu3g-conversion-tracker' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''    => $inherit . ' (' . ( $g['forms_enabled'] ? __( 'sì', 'lu3g-conversion-tracker' ) : __( 'no', 'lu3g-conversion-tracker' ) ) . ')',
					'on'  => __( 'Sì', 'lu3g-conversion-tracker' ),
					'off' => __( 'No', 'lu3g-conversion-tracker' ),
				),
			)
		);

		$widget->add_control(
			'lu3g_ct_event',
			array(
				'label'       => __( 'Nome evento', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => $g['event'],
				'description' => __( 'Vuoto: quello globale. Es. newsletter_signup, request_quote: parte solo questo evento. Lettere, numeri e trattino basso, massimo 40.', 'lu3g-conversion-tracker' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_more',
			array(
				'label'        => __( 'Opzioni avanzate', 'lu3g-conversion-tracker' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'separator'    => 'before',
				'description'  => __( 'Campi email/telefono, valore, Google Ads. Di solito non servono.', 'lu3g-conversion-tracker' ),
			)
		);

		$widget->add_control(
			'lu3g_ct_label',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'       => __( 'Nome descrittivo', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'richiesta_preventivo',
				'description' => __( 'Etichetta fissa inviata come form_name (mai i dati compilati). Vuoto: il nome del modulo.', 'lu3g-conversion-tracker' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_value_mode',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'     => __( 'Valore', 'lu3g-conversion-tracker' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'separator' => 'before',
				'options'   => array(
					''      => $inherit,
					'fixed' => __( 'Importo specifico', 'lu3g-conversion-tracker' ),
					'none'  => __( 'Nessun valore', 'lu3g-conversion-tracker' ),
				),
			)
		);

		$widget->add_control(
			'lu3g_ct_value',
			array(
				'label'       => __( 'Importo', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '25,50',
				'description' => __( 'Numero non negativo, anche 0; virgola o punto.', 'lu3g-conversion-tracker' ),
				'condition'   => array( 'lu3g_ct_more' => 'yes', 'lu3g_ct_value_mode' => 'fixed' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_currency',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'       => __( 'Valuta', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => $g['currency'],
				'description' => __( 'Codice ISO 4217 (EUR, USD, CHF…). Vuoto: quella globale.', 'lu3g-conversion-tracker' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_email_field',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'       => __( 'Campo email (ID)', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'automatico', 'lu3g-conversion-tracker' ),
				'description' => __( 'ID del campo (scheda Avanzate del campo). Vuoto: riconosciuto dal tipo. __none: non usare.', 'lu3g-conversion-tracker' ),
				'separator'   => 'before',
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_phone_field',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'       => __( 'Campo telefono (ID)', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'automatico', 'lu3g-conversion-tracker' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_country_field',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'       => __( 'Campo paese (ID)', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Facoltativo: un campo con il paese (IT, CH… o +39) usato per i numeri scritti senza prefisso.', 'lu3g-conversion-tracker' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_country',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'       => __( 'Paese del telefono', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => $g['country'],
				'description' => __( 'Codice di due lettere. Vuoto: quello del sito.', 'lu3g-conversion-tracker' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->add_control(
			'lu3g_ct_ec',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'     => __( 'Conversioni avanzate', 'lu3g-conversion-tracker' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'separator' => 'before',
				'options'   => array(
					''    => $inherit,
					'on'  => __( 'Abilitate (se c\'è il consenso)', 'lu3g-conversion-tracker' ),
					'off' => __( 'Disabilitate', 'lu3g-conversion-tracker' ),
				),
			)
		);

		$widget->add_control(
			'lu3g_ct_ads',
			array(
				'condition' => array( 'lu3g_ct_more' => 'yes' ),
				'label'   => __( 'Destinazione Google Ads', 'lu3g-conversion-tracker' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''    => $inherit,
					'key' => __( 'Chiave specifica', 'lu3g-conversion-tracker' ),
					'off' => __( 'Esclusa da Google Ads', 'lu3g-conversion-tracker' ),
				),
			)
		);

		$widget->add_control(
			'lu3g_ct_conversion_key',
			array(
				'label'       => __( 'Chiave della conversione', 'lu3g-conversion-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'lead',
				'description' => __( 'Nome logico (es. lead, preventivo) collegato in GTM a ID ed etichetta della conversione Ads.', 'lu3g-conversion-tracker' ),
				'condition'   => array( 'lu3g_ct_more' => 'yes', 'lu3g_ct_ads' => 'key' ),
				'ai'          => array( 'active' => false ),
			)
		);

		$widget->end_controls_section();
	}
}
