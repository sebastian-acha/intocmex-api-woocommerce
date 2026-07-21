<?php
/**
 * Real-time stock validator for the checkout flow.
 *
 * Implements Sección 5.2 step 4 of the IWS guide: before placing an order the
 * cart contents must be validated against IWS using GetProducts in real time.
 *
 * A short transient cache (default 60s) is used to absorb repeated requests
 * in the same session without exceeding the API call rate.
 *
 * @link       https://ventresslabs.com/
 * @since      1.1.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VentressLabs_Intcomex_Stock_Validator {

    /**
     * Caching window in seconds for batched stock lookups.
     *
     * @since 1.1.0
     * @var int
     */
    const CACHE_TTL = 60;

    /**
     * Transient key prefix for cached stock lookups.
     */
    const CACHE_PREFIX = 'vl_intcomex_stock_';

    /**
     * Option key for the validator settings.
     */
    const SETTINGS_KEY = 'ventresslabs_intcomex_stock_validator';

    /**
     * Logger instance.
     *
     * @since 1.1.0
     * @var VentressLabs_Intcomex_Logger
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct() {
        if ( class_exists( 'VentressLabs_Intcomex_Logger' ) ) {
            $this->logger = new VentressLabs_Intcomex_Logger();
        }
    }

    /**
     * Get the validator settings with defaults.
     *
     * @since 1.1.0
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'fail_open'      => 'no',
            'block_on_zero'  => 'yes',
            'cache_ttl'      => self::CACHE_TTL,
        );
        $settings = get_option( self::SETTINGS_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Validate the current cart against real-time IWS stock.
     *
     * Hooked into `woocommerce_check_cart_items`. Adds WooCommerce notices
     * (error) for items without enough stock and returns false to block
     * checkout when something is off.
     *
     * @since 1.1.0
     * @return bool
     */
    public function validate_cart() {
        // Endpoint toggle is the single source of truth (legacy 'enabled'
        // field was migrated in 2.0.0 and is no longer consulted).
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRODUCTS ) ) {
            return true;
        }
        $settings = $this->get_settings();
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return true;
        }

        $items = WC()->cart->get_cart();
        if ( empty( $items ) ) {
            return true;
        }

        $sku_qty = array();
        foreach ( $items as $item ) {
            $product = $item['data'] ?? null;
            if ( ! $product instanceof WC_Product ) {
                continue;
            }
            $sku = $product->get_sku();
            if ( '' === $sku ) {
                continue;
            }
            if ( ! isset( $sku_qty[ $sku ] ) ) {
                $sku_qty[ $sku ] = 0;
            }
            $sku_qty[ $sku ] += (float) $item['quantity'];
        }

        if ( empty( $sku_qty ) ) {
            return true;
        }

        $stock_map = $this->fetch_stock( array_keys( $sku_qty ), (int) $settings['cache_ttl'] );

        if ( is_wp_error( $stock_map ) ) {
            if ( 'yes' === $settings['fail_open'] ) {
                if ( $this->logger ) {
                    $this->logger->log_call( array(
                        'environment'   => 'stock-validator',
                        'method'        => 'GET (cached)',
                        'url'           => 'getproducts',
                        'request_body'  => array_keys( $sku_qty ),
                        'response_code' => 0,
                        'response_body' => null,
                        'wp_error'      => $stock_map->get_error_message(),
                        'elapsed_ms'    => 0,
                    ) );
                }
                wc_add_notice(
                    __( 'No se pudo validar el stock en Intcomex en este momento. Tu orden podrá ser revisada manualmente.', 'ventresslabs-intcomex' ),
                    'notice'
                );
                return true;
            }
            wc_add_notice(
                sprintf(
                    /* translators: %s is the error message. */
                    __( 'No se pudo validar el stock con Intcomex: %s', 'ventresslabs-intcomex' ),
                    $stock_map->get_error_message()
                ),
                'error'
            );
            return false;
        }

        $ok           = true;
        $block_on_zero = 'yes' === $settings['block_on_zero'];

        foreach ( $sku_qty as $sku => $qty ) {
            $stock = isset( $stock_map[ $sku ] ) ? (int) $stock_map[ $sku ] : null;

            if ( null === $stock ) {
                // SKU no encontrado en IWS: producto ya no está en catálogo.
                wc_add_notice(
                    sprintf(
                        /* translators: %s is the SKU. */
                        __( 'El producto con SKU %s ya no está disponible en Intcomex.', 'ventresslabs-intcomex' ),
                        '<code>' . esc_html( $sku ) . '</code>'
                    ),
                    'error'
                );
                $ok = false;
                continue;
            }

            if ( 0 === $stock && $block_on_zero ) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s is the SKU. */
                        __( 'El producto con SKU %s está agotado en Intcomex.', 'ventresslabs-intcomex' ),
                        '<code>' . esc_html( $sku ) . '</code>'
                    ),
                    'error'
                );
                $ok = false;
                continue;
            }

            if ( $stock > 0 && $qty > $stock ) {
                wc_add_notice(
                    sprintf(
                        /* translators: 1: SKU, 2: requested qty, 3: available stock. */
                        __( 'Stock insuficiente para %1$s. Solicitado: %2$d, disponible en Intcomex: %3$d.', 'ventresslabs-intcomex' ),
                        '<code>' . esc_html( $sku ) . '</code>',
                        $qty,
                        $stock
                    ),
                    'error'
                );
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Fetch real-time stock for a list of SKUs, using a short transient cache
     * to deduplicate calls within the same session.
     *
     * @since 1.1.0
     * @param array $skus     List of SKUs to look up.
     * @param int   $cache_ttl Cache TTL in seconds (0 disables cache).
     * @return array|WP_Error Map of SKU => stock quantity.
     */
    public function fetch_stock( array $skus, $cache_ttl = self::CACHE_TTL ) {
        $skus = array_values( array_filter( array_map( 'strval', $skus ) ) );
        if ( empty( $skus ) ) {
            return array();
        }

        $cache_key = self::CACHE_PREFIX . md5( implode( '|', $skus ) );
        $cached    = $cache_ttl > 0 ? get_transient( $cache_key ) : false;

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $client   = new VentressLabs_Intcomex_Api_Client();
        $response = $client->get_products( $skus, array(
            'includePriceData'      => 'false',
            'includeInventoryData' => 'true',
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $stock_map = array();
        if ( is_array( $response ) ) {
            foreach ( $response as $product ) {
                $sku = isset( $product['Sku'] ) ? $product['Sku'] : null;
                if ( ! $sku ) {
                    continue;
                }
                $stock_map[ $sku ] = isset( $product['InStock'] ) ? (int) $product['InStock'] : 0;
            }
        }

        if ( $cache_ttl > 0 ) {
            set_transient( $cache_key, $stock_map, $cache_ttl );
        }

        return $stock_map;
    }

    /**
     * Invalidate the cached stock lookup for a list of SKUs.
     *
     * @since 1.1.0
     * @param array $skus List of SKUs.
     */
    public function clear_cache( array $skus = array() ) {
        if ( empty( $skus ) ) {
            return;
        }
        $cache_key = self::CACHE_PREFIX . md5( implode( '|', array_map( 'strval', $skus ) ) );
        delete_transient( $cache_key );
    }
}
