<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://ventresslabs.com/
 * @since      1.0.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/includes
 * @author     VentressLabs <contact@ventresslabs.com>
 */
class VentressLabs_Intcomex {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      VentressLabs_Intcomex_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'VENTRESSLABS_INTCOMEX_VERSION' ) ) {
            $this->version = VENTRESSLABS_INTCOMEX_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'ventresslabs-intcomex';

        $this->load_dependencies();
        $this->loader = new VentressLabs_Intcomex_Loader();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_endpoint_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - VentressLabs_Intcomex_Loader. Orchestrates the hooks of the plugin.
     * - VentressLabs_Intcomex_Admin. Defines all hooks for the admin area.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ventresslabs-intcomex-loader.php';

        /**
         * Endpoint Manager: feature toggles per endpoint (Sección 4 + 5.2).
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-endpoint-manager.php';

        /**
         * Logger for IWS API calls (Sección 6 de la guía - go-live).
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-logger.php';

        /**
         * The API client.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-api-client.php';

        /**
         * Sync orchestration service (Sección 5.1 de la guía).
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-sync-service.php';

        /**
         * Real-time stock validator for the checkout flow (Sección 5.2 paso 4).
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-stock-validator.php';

        /**
         * Order service: PlaceOrder from WC checkout (Sección 5.2 paso 5).
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-order-service.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ventresslabs-intcomex-admin.php';
    }

    /**
     * Define all hooks that are registered throughout the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new VentressLabs_Intcomex_Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        $this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_ajax_ventresslabs_fetch_categories', $plugin_admin, 'ajax_fetch_categories' );
        $this->loader->add_action( 'wp_ajax_ventresslabs_sync_products', $plugin_admin, 'ajax_sync_products' );
        $this->loader->add_action( 'wp_ajax_ventresslabs_clear_logs', $plugin_admin, 'ajax_clear_logs' );
        $this->loader->add_action( 'wp_ajax_ventresslabs_sync_extended', $plugin_admin, 'ajax_sync_extended' );
        $this->loader->add_action( 'wp_ajax_ventresslabs_retry_order', $plugin_admin, 'ajax_retry_order' );
        $this->loader->add_action( 'wp_ajax_ventresslabs_retry_orders_bulk', $plugin_admin, 'ajax_retry_orders_bulk' );

        // UI: columna IWS en listado de pedidos WC.
        $this->loader->add_filter( 'manage_edit-shop_order_columns', $plugin_admin, 'add_orders_column_iws' );
        $this->loader->add_action( 'manage_shop_order_posts_custom_column', $plugin_admin, 'render_orders_column_iws', 10, 2 );

        // Cron: periodic sync (Sección 5.1 - máx. 1 vez por hora).
        $sync_service = new VentressLabs_Intcomex_Sync_Service();
        $this->loader->add_action( VentressLabs_Intcomex_Sync_Service::CRON_HOOK, $sync_service, 'run_full_sync' );

        // Cron: monthly DownloadExtendedCatalog (Sección 4 - máx. 1 vez por mes).
        $this->loader->add_action( VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED, $sync_service, 'sync_extended_catalog' );
    }

    /**
     * Define public-facing hooks for the storefront / checkout flow.
     *
     * @since 1.1.0
     * @access private
     */
    private function define_public_hooks() {
        $validator = new VentressLabs_Intcomex_Stock_Validator();

        // Sección 5.2 paso 4: validar stock en tiempo real antes de procesar pago.
        $this->loader->add_action( 'woocommerce_check_cart_items', $validator, 'validate_cart' );
        $this->loader->add_action( 'woocommerce_after_checkout_validation', $validator, 'validate_cart' );

        // Sección 5.2 paso 5: PlaceOrder en IWS cuando el pago se completa.
        $order_service = new VentressLabs_Intcomex_Order_Service();
        $this->loader->add_action( 'woocommerce_payment_complete', $order_service, 'handle_order_created' );
        // Fallback para gateways que no disparan woocommerce_payment_complete (COD, cheque, etc.)
        $this->loader->add_action( 'woocommerce_checkout_order_processed', $order_service, 'handle_order_created', 20 );
    }

    /**
     * Define hooks related to endpoint toggles: migration on init, cron
     * rescheduling when an endpoint is toggled, and options sanitization.
     *
     * @since 2.0.0
     * @access private
     */
    private function define_endpoint_hooks() {
        // Run legacy -> 2.0.0 migration as early as possible (idempotent).
        $this->loader->add_action( 'init', $this, 'maybe_migrate_endpoints', 1 );

        // Re-schedule crons when an endpoint is toggled.
        $this->loader->add_action( 'ventresslabs_intcomex_endpoint_toggled', $this, 'reschedule_crons', 10, 2 );

        // Fire endpoint_toggled for each diff when the admin Settings form
        // is saved (covers the UI path; programmatic set_enabled() already
        // fires the action on its own).
        $this->loader->add_action(
            'update_option_' . VentressLabs_Intcomex_Endpoint_Manager::SETTINGS_KEY,
            'VentressLabs_Intcomex_Endpoint_Manager',
            'on_option_updated',
            10,
            2
        );
    }

    /**
     * Run the endpoint toggles migration (idempotent).
     *
     * @since 2.0.0
     */
    public function maybe_migrate_endpoints() {
        VentressLabs_Intcomex_Endpoint_Manager::maybe_migrate();
    }

    /**
     * Reschedule crons based on the new state of endpoint toggles.
     *
     * - GetCatalog toggles the hourly sync cron.
     * - DownloadExtendedCatalog toggles the monthly extended catalog cron.
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint ID that changed.
     * @param bool   $enabled  New state.
     */
    public function reschedule_crons( $endpoint, $enabled ) {
        switch ( $endpoint ) {
            case VentressLabs_Intcomex_Endpoint_Manager::EP_GET_CATALOG:
                if ( $enabled ) {
                    if ( ! wp_next_scheduled( VentressLabs_Intcomex_Sync_Service::CRON_HOOK ) ) {
                        wp_schedule_event( time() + 60, VentressLabs_Intcomex_Sync_Service::CRON_SCHEDULE, VentressLabs_Intcomex_Sync_Service::CRON_HOOK );
                    }
                } else {
                    wp_clear_scheduled_hook( VentressLabs_Intcomex_Sync_Service::CRON_HOOK );
                }
                break;
            case VentressLabs_Intcomex_Endpoint_Manager::EP_DOWNLOAD_EXTENDED:
                if ( $enabled ) {
                    if ( ! wp_next_scheduled( VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED ) ) {
                        wp_schedule_event( time() + 120, VentressLabs_Intcomex_Sync_Service::CRON_SCHEDULE_EXTENDED, VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED );
                    }
                } else {
                    wp_clear_scheduled_hook( VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED );
                }
                break;
            case VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRODUCTS:
                // The stock validator is request-time, not cron driven. When
                // toggled off we cannot delete SKU-keyed transients (we don't
                // know which ones were set), but on re-enable that's fine
                // because they expire in CACHE_TTL seconds anyway. Nothing to do.
                break;
            case VentressLabs_Intcomex_Endpoint_Manager::EP_PLACE_ORDER:
            case VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRICE_LIST:
            case VentressLabs_Intcomex_Endpoint_Manager::EP_GET_INVENTORY:
                // These are sub-steps of run_full_sync or are request-time.
                // No dedicated cron to (re)schedule. The hourly cron already
                // honors each toggle internally.
                break;
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    VentressLabs_Intcomex_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
