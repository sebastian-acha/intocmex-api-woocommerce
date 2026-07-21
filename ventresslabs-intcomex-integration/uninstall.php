<?php
/**
 * Uninstall handler for VentressLabs Intcomex Integration.
 *
 * Fired by WordPress when the plugin is removed (deleted) from the
 * Plugins admin screen. Removes all plugin-related options, transients
 * and scheduled cron events. Does NOT delete WooCommerce products or
 * order meta that were created during normal operation — those are
 * considered user data and are preserved.
 *
 * @link       https://ventresslabs.com/
 * @since      2.0.0
 *
 * @package    VentressLabs_Intcomex
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Cron events.
$cron_hooks = array(
    'ventresslabs_intcomex_cron_sync',
    'ventresslabs_intcomex_cron_extended',
);
foreach ( $cron_hooks as $hook ) {
    wp_clear_scheduled_hook( $hook );
}

// Options.
$option_keys = array(
    // Endpoint toggles (Sección 4 + 5.2).
    'ventresslabs_intcomex_endpoints',
    'ventresslabs_intcomex_endpoints_migrated',

    // API credentials + categories.
    'ventresslabs_intcomex_api_credentials',
    'ventresslabs_intcomex_selected_categories',

    // Stock validator + PlaceOrder settings (legacy sources for toggles).
    'ventresslabs_intcomex_stock_validator',
    'ventresslabs_intcomex_order_settings',

    // Sync service cached data (OPT_* constants).
    'ventresslabs_intcomex_catalog',
    'ventresslabs_intcomex_catalog_at',
    'ventresslabs_intcomex_price_list',
    'ventresslabs_intcomex_price_list_at',
    'ventresslabs_intcomex_inventory',
    'ventresslabs_intcomex_inventory_at',
    'ventresslabs_intcomex_extended',
    'ventresslabs_intcomex_extended_at',
    'ventresslabs_intcomex_extended_file',

    // Logger.
    'ventresslabs_intcomex_logs',
);
foreach ( $option_keys as $key ) {
    delete_option( $key );
}

// Transients.
$transient_keys = array(
    'ventresslabs_intcomex_sync_lock',
    'ventresslabs_intcomex_extended_lock',
    'ventresslabs_intcomex_categories',
);
foreach ( $transient_keys as $key ) {
    delete_transient( $key );
}

// Stock validator SKU-keyed transients use a dynamic prefix; we can't
// enumerate them without DB access, so we delete via SQL on the options
// table (transients are stored as _transient_* options).
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_vl_intcomex_stock_%'
        OR option_name LIKE '_transient_timeout_vl_intcomex_stock_%'"
);
