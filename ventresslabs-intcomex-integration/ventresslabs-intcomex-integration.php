<?php
/**
 * Plugin Name:       VentressLabs Intcomex Integration
 * Plugin URI:        https://ventresslabs.com/
 * Description:       Integrates WooCommerce with the Intcomex API to synchronize products.
 * Version:           1.0.0
 * Author:            VentressLabs
 * Author URI:        https://ventresslabs.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ventresslabs-intcomex
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ventresslabs-intcomex.php';

/**
 * Schedule the hourly IWS sync event on activation (Sección 5.1 - máx 1/h).
 */
register_activation_hook( __FILE__, function () {
    if ( ! wp_next_scheduled( VentressLabs_Intcomex_Sync_Service::CRON_HOOK ) ) {
        wp_schedule_event( time() + 60, VentressLabs_Intcomex_Sync_Service::CRON_SCHEDULE, VentressLabs_Intcomex_Sync_Service::CRON_HOOK );
    }
    // Schedule the monthly DownloadExtendedCatalog (Sección 4 - máx 1/mes).
    if ( ! wp_next_scheduled( VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED ) ) {
        wp_schedule_event( time() + 120, VentressLabs_Intcomex_Sync_Service::CRON_SCHEDULE_EXTENDED, VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED );
    }
} );

/**
 * Clear the scheduled event and the manual sync throttle on deactivation.
 */
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( VentressLabs_Intcomex_Sync_Service::CRON_HOOK );
    wp_clear_scheduled_hook( VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED );
    delete_transient( VentressLabs_Intcomex_Sync_Service::THROTTLE_KEY );
    delete_transient( VentressLabs_Intcomex_Sync_Service::EXTENDED_THROTTLE_KEY );
} );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ventresslabs_intcomex_integration() {

    $plugin = new VentressLabs_Intcomex();
    $plugin->run();

}
run_ventresslabs_intcomex_integration();
