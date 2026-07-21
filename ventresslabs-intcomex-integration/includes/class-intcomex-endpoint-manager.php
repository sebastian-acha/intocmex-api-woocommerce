<?php
/**
 * Endpoint Manager: central feature-toggle system for IWS endpoints.
 *
 * Provides per-endpoint activation/deactivation, persisted in wp_options.
 * The GetCatalog endpoint is always enabled (mandatory per IWS guide
 * integration flow). All other endpoints can be toggled by the admin.
 *
 * Also exposes a WP-CLI command when WP-CLI is available.
 *
 * @link       https://ventresslabs.com/
 * @since      2.0.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VentressLabs_Intcomex_Endpoint_Manager {

    /**
     * Option key for the toggles.
     */
    const SETTINGS_KEY = 'ventresslabs_intcomex_endpoints';

    /**
     * Option key used as a flag to ensure migration only runs once.
     */
    const MIGRATED_FLAG = 'ventresslabs_intcomex_endpoints_migrated';

    /**
     * Endpoint IDs.
     */
    const EP_GET_CATALOG              = 'get_catalog';
    const EP_GET_PRICE_LIST           = 'get_price_list';
    const EP_GET_INVENTORY            = 'get_inventory';
    const EP_DOWNLOAD_EXTENDED        = 'download_extended_catalog';
    const EP_GET_PRODUCTS             = 'get_products';
    const EP_PLACE_ORDER              = 'place_order';

    /**
     * Catalog of endpoints: id => metadata.
     *
     * 'required' = true means the toggle cannot be disabled (UI locks it).
     * 'depends'  = list of endpoint IDs this one relies on. The Manager will
     *              warn (but not block) if the dependency is off.
     *
     * @return array
     */
    public static function catalog() {
        return array(
            self::EP_GET_CATALOG => array(
                'label'        => __( 'GetCatalog', 'ventresslabs-intcomex' ),
                'description'  => __( 'Catálogo de productos. Obligatorio, base para todos los demás.', 'ventresslabs-intcomex' ),
                'required'     => true,
                'depends'       => array(),
                'default'      => true,
            ),
            self::EP_GET_PRICE_LIST => array(
                'label'        => __( 'GetPriceList', 'ventresslabs-intcomex' ),
                'description'  => __( 'Lista de precios vigentes (sync periódica).', 'ventresslabs-intcomex' ),
                'required'     => false,
                'depends'       => array( self::EP_GET_CATALOG ),
                'default'      => true,
            ),
            self::EP_GET_INVENTORY => array(
                'label'        => __( 'GetInventory', 'ventresslabs-intcomex' ),
                'description'  => __( 'Inventario en tiempo casi real (sync periódica).', 'ventresslabs-intcomex' ),
                'required'     => false,
                'depends'       => array( self::EP_GET_CATALOG ),
                'default'      => true,
            ),
            self::EP_DOWNLOAD_EXTENDED => array(
                'label'        => __( 'DownloadExtendedCatalog', 'ventresslabs-intcomex' ),
                'description'  => __( 'Descarga imágenes y especificaciones (máx 1/mes).', 'ventresslabs-intcomex' ),
                'required'     => false,
                'depends'       => array( self::EP_GET_CATALOG ),
                'default'      => true,
            ),
            self::EP_GET_PRODUCTS => array(
                'label'        => __( 'GetProducts', 'ventresslabs-intcomex' ),
                'description'  => __( 'Validación de stock en checkout en tiempo real.', 'ventresslabs-intcomex' ),
                'required'     => false,
                'depends'       => array( self::EP_GET_CATALOG ),
                'default'      => true,
            ),
            self::EP_PLACE_ORDER => array(
                'label'        => __( 'PlaceOrder', 'ventresslabs-intcomex' ),
                'description'  => __( 'Envío de órdenes a IWS desde el checkout.', 'ventresslabs-intcomex' ),
                'required'     => false,
                'depends'       => array( self::EP_GET_PRODUCTS ),
                'default'      => false, // Opt-in for safety.
            ),
        );
    }

    /**
     * Get all toggles merged with defaults.
     *
     * @since 2.0.0
     * @return array Map: endpoint_id => array( 'enabled' => bool, ...metadata ).
     */
    public static function get_all() {
        $stored = get_option( self::SETTINGS_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        $catalog = self::catalog();
        $all     = array();
        foreach ( $catalog as $id => $meta ) {
            $enabled = isset( $stored[ $id ]['enabled'] )
                ? (bool) $stored[ $id ]['enabled']
                : (bool) $meta['default'];
            // Required endpoints are forced on regardless of stored value.
            if ( ! empty( $meta['required'] ) ) {
                $enabled = true;
            }
            $all[ $id ] = array_merge( $meta, array( 'enabled' => $enabled ) );
        }
        return $all;
    }

    /**
     * Is a specific endpoint enabled?
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint ID.
     * @return bool
     */
    public static function is_enabled( $endpoint ) {
        $all = self::get_all();
        return isset( $all[ $endpoint ] ) ? (bool) $all[ $endpoint ]['enabled'] : false;
    }

    /**
     * Enable an endpoint. Respects required endpoints (no-op for those).
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint ID.
     * @return bool True on success.
     */
    public static function enable( $endpoint ) {
        return self::set_enabled( $endpoint, true );
    }

    /**
     * Disable an endpoint. Refuses to disable required endpoints.
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint ID.
     * @return bool True on success, false if endpoint is required or unknown.
     */
    public static function disable( $endpoint ) {
        $catalog = self::catalog();
        if ( ! isset( $catalog[ $endpoint ] ) ) {
            return false;
        }
        if ( ! empty( $catalog[ $endpoint ]['required'] ) ) {
            return false;
        }
        return self::set_enabled( $endpoint, false );
    }

    /**
     * Set the enabled state for an endpoint.
     *
     * @since 2.0.0
     * @access private
     * @param string $endpoint Endpoint ID.
     * @param bool   $enabled  New state.
     * @return bool
     */
    private static function set_enabled( $endpoint, $enabled ) {
        $catalog = self::catalog();
        if ( ! isset( $catalog[ $endpoint ] ) ) {
            return false;
        }
        if ( ! empty( $catalog[ $endpoint ]['required'] ) ) {
            $enabled = true;
        }
        $stored = get_option( self::SETTINGS_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        if ( ! isset( $stored[ $endpoint ] ) || ! is_array( $stored[ $endpoint ] ) ) {
            $stored[ $endpoint ] = array();
        }
        $stored[ $endpoint ]['enabled'] = (bool) $enabled;
        $updated = update_option( self::SETTINGS_KEY, $stored, false );

        // Note: the `ventresslabs_intcomex_endpoint_toggled` action is fired
        // centrally by {@see on_option_updated()} hooked into
        // `update_option_<SETTINGS_KEY>`. No need to fire it manually here.

        return (bool) $updated;
    }

    /**
     * Sanitize the toggles array coming from the Settings form.
     *
     * @since 2.0.0
     * @param array $input Raw input.
     * @return array
     */
    public static function sanitize( $input ) {
        $catalog = self::catalog();
        if ( ! is_array( $input ) ) {
            $input = array();
        }
        $sanitized = array();
        foreach ( $catalog as $id => $meta ) {
            $enabled = isset( $input[ $id ]['enabled'] )
                ? (bool) $input[ $id ]['enabled']
                : ( isset( $input[ $id ] ) ? (bool) $input[ $id ] : false );
            // Required endpoints are always on.
            if ( ! empty( $meta['required'] ) ) {
                $enabled = true;
            }
            $sanitized[ $id ] = array( 'enabled' => $enabled );
        }
        return $sanitized;
    }

    /**
     * Hook callback for `update_option_ventresslabs_intcomex_endpoints`.
     *
     * Compares the previous stored state with the new one (already
     * sanitized via {@see sanitize()}) and fires the
     * `ventresslabs_intcomex_endpoint_toggled` action for each endpoint
     * whose `enabled` flag actually changed. This is what makes the admin
     * Settings form trigger cron rescheduling (and any listener attached
     * to the toggled action).
     *
     * Note: required endpoints can never change (sanitize() forces them
     * on), but we still surface any diff for completeness/clarity.
     *
     * @since 2.0.0
     * @param mixed $old_value Raw old option value.
     * @param mixed $new_value Raw new option value (already sanitized).
     */
    public static function on_option_updated( $old_value, $new_value ) {
        if ( ! is_array( $old_value ) ) {
            $old_value = array();
        }
        if ( ! is_array( $new_value ) ) {
            $new_value = array();
        }

        foreach ( self::catalog() as $id => $meta ) {
            $old_enabled = isset( $old_value[ $id ]['enabled'] )
                ? (bool) $old_value[ $id ]['enabled']
                : (bool) $meta['default'];
            $new_enabled = isset( $new_value[ $id ]['enabled'] )
                ? (bool) $new_value[ $id ]['enabled']
                : (bool) $meta['default'];

            // Required endpoints are always on, no diff to report.
            if ( ! empty( $meta['required'] ) ) {
                continue;
            }

            if ( $old_enabled !== $new_enabled ) {
                do_action( 'ventresslabs_intcomex_endpoint_toggled', $id, $new_enabled );
            }
        }
    }

    /**
     * Get metadata for one endpoint.
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint ID.
     * @return array|null
     */
    public static function get_metadata( $endpoint ) {
        $catalog = self::catalog();
        return isset( $catalog[ $endpoint ] ) ? $catalog[ $endpoint ] : null;
    }

    /**
     * Get the list of dependency warnings (dependencies that are off).
     *
     * @since 2.0.0
     * @param string $endpoint Endpoint ID.
     * @return array Endpoint IDs whose dependency is off.
     */
    public static function dependency_warnings( $endpoint ) {
        $catalog = self::catalog();
        if ( ! isset( $catalog[ $endpoint ] ) ) {
            return array();
        }
        $deps   = $catalog[ $endpoint ]['depends'] ?? array();
        $warns   = array();
        foreach ( $deps as $dep ) {
            if ( ! self::is_enabled( $dep ) ) {
                $warns[] = $dep;
            }
        }
        return $warns;
    }

    /**
     * Run the migration from <2.0.0 settings to endpoint toggles.
     * Idempotent via the MIGRATED_FLAG option.
     *
     * @since 2.0.0
     */
    public static function maybe_migrate() {
        if ( 'yes' === get_option( self::MIGRATED_FLAG, 'no' ) ) {
            return;
        }

        // Defaults from catalog.
        $toggles = array();
        foreach ( self::catalog() as $id => $meta ) {
            $toggles[ $id ] = array( 'enabled' => (bool) $meta['default'] );
        }

        // Stock validator legacy enabled flag.
        $stock_legacy = get_option( 'ventresslabs_intcomex_stock_validator', array() );
        if ( is_array( $stock_legacy ) && isset( $stock_legacy['enabled'] ) ) {
            $toggles[ self::EP_GET_PRODUCTS ]['enabled'] = ( 'yes' === $stock_legacy['enabled'] );
        }

        // Order service legacy enabled flag.
        $order_legacy = get_option( 'ventresslabs_intcomex_order_settings', array() );
        if ( is_array( $order_legacy ) && isset( $order_legacy['enabled'] ) ) {
            $toggles[ self::EP_PLACE_ORDER ]['enabled'] = ( 'yes' === $order_legacy['enabled'] );
        }

        update_option( self::SETTINGS_KEY, $toggles, false );
        update_option( self::MIGRATED_FLAG, 'yes', false );

        // The `ventresslabs_intcomex_endpoint_toggled` actions are fired
        // automatically by {@see on_option_updated()} when the option is
        // written above (it diffs old vs new for each endpoint).
    }
}

/**
 * WP-CLI command for endpoint management.
 *
 * Usage:
 *   wp intcomex endpoints list
 *   wp intcomex endpoints enable place_order
 *   wp intcomex endpoints disable download_extended_catalog
 *   wp intcomex endpoints status place_order
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'intcomex endpoints', 'VentressLabs_Intcomex_Endpoint_Manager_CLI' );
}

/**
 * WP-CLI command class.
 *
 * @since 2.0.0
 */
class VentressLabs_Intcomex_Endpoint_Manager_CLI {

    /**
     * List all endpoints and their states.
     *
     * @subcommand list
     */
    public function list( $args, $assoc_args ) {
        $all = VentressLabs_Intcomex_Endpoint_Manager::get_all();
        $rows = array();
        foreach ( $all as $id => $data ) {
            $rows[] = array(
                'endpoint'    => $id,
                'label'       => $data['label'],
                'enabled'     => $data['enabled'] ? 'yes' : 'no',
                'required'    => ! empty( $data['required'] ) ? 'yes' : 'no',
                'depends_on'  => implode( ', ', $data['depends'] ),
            );
        }
        WP_CLI\Utils\format_items( 'table', $rows, array( 'endpoint', 'label', 'enabled', 'required', 'depends_on' ) );
    }

    /**
     * Enable an endpoint.
     *
     * @subcommand enable
     * @synopsis <endpoint>
     */
    public function enable( $args ) {
        list( $endpoint ) = $args;
        $ok = VentressLabs_Intcomex_Endpoint_Manager::enable( $endpoint );
        if ( $ok ) {
            WP_CLI::success( "Endpoint '{$endpoint}' enabled." );
        } else {
            WP_CLI::warning( "Endpoint '{$endpoint}' could not be enabled (unknown ID)." );
        }
    }

    /**
     * Disable an endpoint.
     *
     * @subcommand disable
     * @synopsis <endpoint>
     */
    public function disable( $args ) {
        list( $endpoint ) = $args;
        $catalog = VentressLabs_Intcomex_Endpoint_Manager::catalog();
        if ( ! isset( $catalog[ $endpoint ] ) ) {
            WP_CLI::warning( "Endpoint '{$endpoint}' is unknown." );
            return;
        }
        if ( ! empty( $catalog[ $endpoint ]['required'] ) ) {
            WP_CLI::warning( "Endpoint '{$endpoint}' is required and cannot be disabled." );
            return;
        }
        $ok = VentressLabs_Intcomex_Endpoint_Manager::disable( $endpoint );
        if ( $ok ) {
            WP_CLI::success( "Endpoint '{$endpoint}' disabled." );
        } else {
            WP_CLI::warning( "Endpoint '{$endpoint}' could not be disabled." );
        }
    }

    /**
     * Show the status of a single endpoint.
     *
     * @subcommand status
     * @synopsis <endpoint>
     */
    public function status( $args ) {
        list( $endpoint ) = $args;
        $catalog = VentressLabs_Intcomex_Endpoint_Manager::catalog();
        if ( ! isset( $catalog[ $endpoint ] ) ) {
            WP_CLI::warning( "Endpoint '{$endpoint}' is unknown." );
            return;
        }
        $enabled = VentressLabs_Intcomex_Endpoint_Manager::is_enabled( $endpoint );
        WP_CLI::log( "Endpoint: {$endpoint}" );
        WP_CLI::log( "State:    " . ( $enabled ? 'enabled' : 'disabled' ) );
        WP_CLI::log( "Required: " . ( ! empty( $catalog[ $endpoint ]['required'] ) ? 'yes' : 'no' ) );
    }
}
