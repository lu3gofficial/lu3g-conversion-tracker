<?php
/**
 * Plugin Name:       LU3G Conversion Tracker
 * Plugin URI:        https://github.com/lu3gofficial/lu3g-conversion-tracker
 * Description:       Conversioni dei form di Elementor Pro (lead_submit) e click su WhatsApp, telefono ed email nel dataLayer di Google Tag Manager, con identificatori normalizzati e cifrati (SHA-256) per le conversioni avanzate di Google Ads, rispetto del consenso e diagnostica.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LU3G Agenzia Web
 * Author URI:        https://lu3g.it
 * License:           GPL-2.0-or-later
 * Text Domain:       lu3g-conversion-tracker
 * Domain Path:       /languages
 * Update URI:        https://github.com/lu3gofficial/lu3g-conversion-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LU3G_CT_VERSION', '1.2.1' );
define( 'LU3G_CT_FILE', __FILE__ );
define( 'LU3G_CT_URL', plugin_dir_url( __FILE__ ) );
define( 'LU3G_CT_PATH', plugin_dir_path( __FILE__ ) );

// Aggiornamenti da GitHub: sempre attivi, anche senza Elementor.
require_once LU3G_CT_PATH . 'includes/class-updater.php';
LU3G_CT_Updater::init( __FILE__ );

/**
 * Avvio.
 * - Impostazioni, click sui contatti e pannello funzionano sempre.
 * - L'integrazione con i form (sezione nel widget, censimento) solo con Elementor Pro: senza, un avviso e nient'altro.
 */
function lu3g_ct_boot() {
	load_plugin_textdomain( 'lu3g-conversion-tracker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	require_once LU3G_CT_PATH . 'includes/class-validate.php';
	require_once LU3G_CT_PATH . 'includes/class-settings.php';
	require_once LU3G_CT_PATH . 'includes/class-forms.php';
	require_once LU3G_CT_PATH . 'includes/class-frontend.php';
	require_once LU3G_CT_PATH . 'includes/class-diagnostics.php';
	require_once LU3G_CT_PATH . 'includes/class-gtm.php';

	LU3G_CT_Forms::init();
	LU3G_CT_Frontend::init();
	LU3G_CT_Diagnostics::init();

	if ( LU3G_CT_Forms::has_elementor_pro() ) {
		require_once LU3G_CT_PATH . 'includes/class-elementor.php';
		LU3G_CT_Elementor::init();
	}

	if ( is_admin() ) {
		require_once LU3G_CT_PATH . 'includes/class-admin.php';
		LU3G_CT_Admin::init();
	}
}
add_action( 'plugins_loaded', 'lu3g_ct_boot', 20 );

register_activation_hook(
	__FILE__,
	function () {
		// Il censimento dei form si rifà alla prima apertura del pannello (Elementor qui potrebbe non essere caricato).
		delete_option( 'lu3g_ct_forms' );
	}
);
