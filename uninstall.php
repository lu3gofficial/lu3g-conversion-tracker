<?php
/**
 * Eliminazione del plugin: impostazioni, censimento dei form, esiti della diagnostica e sessioni di verifica.
 * Le impostazioni lu3g_ct_* salvate nei widget di Elementor restano nei documenti (sono innocue senza il plugin).
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'lu3g_ct_settings' );
delete_option( 'lu3g_ct_forms' );
delete_option( 'lu3g_ct_diag_results' );
delete_metadata( 'user', 0, 'lu3g_ct_diag_until', '', true );
delete_site_transient( 'lu3g_conversion_tracker_release' );
