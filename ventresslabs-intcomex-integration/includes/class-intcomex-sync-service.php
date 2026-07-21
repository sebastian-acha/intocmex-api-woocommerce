<?php
/**
 * Orchestration service for the periodic sync of catalog, prices and inventory.
 *
 * Implements the recommended flow from the IWS guide (Sección 5.1):
 *   1. GetCatalog     -> structure + categories
 *   2. GetPriceList   -> prices
 *   3. GetInventory   -> stock
 *
 * Data is persisted in wp_options (with timestamps) so that product creation
 * can read price/stock from the right source instead of GetCatalog.
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

class VentressLabs_Intcomex_Sync_Service {

    /**
     * Option keys for cached data (each one paired with a *_at timestamp).
     */
    const OPT_CATALOG             = 'ventresslabs_intcomex_catalog';
    const OPT_CATALOG_AT          = 'ventresslabs_intcomex_catalog_at';
    const OPT_PRICE_LIST          = 'ventresslabs_intcomex_price_list';
    const OPT_PRICE_LIST_AT       = 'ventresslabs_intcomex_price_list_at';
    const OPT_INVENTORY           = 'ventresslabs_intcomex_inventory';
    const OPT_INVENTORY_AT        = 'ventresslabs_intcomex_inventory_at';
    const OPT_EXTENDED            = 'ventresslabs_intcomex_extended';
    const OPT_EXTENDED_AT         = 'ventresslabs_intcomex_extended_at';
    const OPT_EXTENDED_FILE       = 'ventresslabs_intcomex_extended_file';

    /**
     * Minimum number of seconds between manual sync requests, to honor the
     * "máximo 1 vez por hora" limit from Sección 4 and 5.1 of the IWS guide.
     */
    const SYNC_THROTTLE_SECS = 3600;

    /**
     * Minimum number of seconds between DownloadExtendedCatalog calls,
     * to honor the "máximo 1 vez por mes" limit from Sección 4.
     */
    const EXTENDED_THROTTLE_SECS = 2592000; // 30 days.

    /**
     * Transient key for the manual sync throttle.
     */
    const THROTTLE_KEY = 'ventresslabs_intcomex_sync_lock';

    /**
     * Transient key for the DownloadExtendedCatalog throttle.
     */
    const EXTENDED_THROTTLE_KEY = 'ventresslabs_intcomex_extended_lock';

    /**
     * Cron hook name.
     */
    const CRON_HOOK = 'ventresslabs_intcomex_cron_sync';

    /**
     * Cron hook for the monthly DownloadExtendedCatalog.
     */
    const CRON_HOOK_EXTENDED = 'ventresslabs_intcomex_cron_extended';

    /**
     * Cron schedule name (hourly).
     */
    const CRON_SCHEDULE = 'hourly';

    /**
     * Cron schedule name for the extended catalog (monthly).
     */
    const CRON_SCHEDULE_EXTENDED = 'monthly';

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
     * Run a full sync in the recommended order:
     *   1. GetCatalog     (required)
     *   2. GetPriceList   (if endpoint enabled)
     *   3. GetInventory   (if endpoint enabled)
     *
     * Then update WooCommerce products for the selected categories.
     *
     * @since 1.1.0
     * @param bool $force Skip the manual throttle check.
     * @return array|WP_Error
     */
    public function run_full_sync( $force = false ) {
        if ( ! $force && ! $this->acquire_throttle() ) {
            $remaining = $this->throttle_remaining();
            return new WP_Error(
                'sync_throttled',
                sprintf(
                    /* translators: %d is minutes remaining. */
                    __( 'Sincronización limitada por la guía IWS (máx. 1/h). Intenta nuevamente en ~%d min.', 'ventresslabs-intcomex' ),
                    max( 1, (int) ceil( $remaining / 60 ) )
                )
            );
        }

        $results = array(
            'catalog'    => null,
            'price_list' => null,
            'inventory'  => null,
            'products'   => array(),
        );

        // 1. GetCatalog (always required).
        $catalog = $this->sync_catalog();
        if ( is_wp_error( $catalog ) ) {
            return $catalog;
        }
        $results['catalog'] = $catalog;

        // 2. GetPriceList (conditional).
        if ( VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRICE_LIST ) ) {
            $price_list = $this->sync_price_list();
            if ( is_wp_error( $price_list ) ) {
                return $price_list;
            }
            $results['price_list'] = $price_list;
        } else {
            $results['price_list'] = 'disabled';
        }

        // 3. GetInventory (conditional).
        if ( VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_INVENTORY ) ) {
            $inventory = $this->sync_inventory();
            if ( is_wp_error( $inventory ) ) {
                return $inventory;
            }
            $results['inventory'] = $inventory;
        } else {
            $results['inventory'] = 'disabled';
        }

        // 4. Update WooCommerce products.
        $results['products'] = $this->update_products();

        return $results;
    }

    /**
     * Sync the catalog (GetCatalog) and persist a SKU-indexed version
     * along with the last-updated timestamp.
     *
     * @since 1.1.0
     * @return array|WP_Error
     */
    public function sync_catalog() {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_CATALOG ) ) {
            return new WP_Error(
                'endpoint_disabled',
                __( 'GetCatalog está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' )
            );
        }

        $client  = new VentressLabs_Intcomex_Api_Client();
        $catalog = $client->get_catalog();

        if ( is_wp_error( $catalog ) ) {
            return $catalog;
        }
        if ( ! is_array( $catalog ) ) {
            return new WP_Error( 'catalog_empty', __( 'GetCatalog returned no products.', 'ventresslabs-intcomex' ) );
        }

        // Index by SKU for fast lookup.
        $indexed = array();
        foreach ( $catalog as $product ) {
            $sku = isset( $product['Sku'] ) ? $product['Sku'] : null;
            if ( $sku ) {
                $indexed[ $sku ] = $product;
            }
        }

        update_option( self::OPT_CATALOG, $indexed, false );
        update_option( self::OPT_CATALOG_AT, time(), false );

        return count( $indexed );
    }

    /**
     * Sync the price list (GetPriceList) and persist a SKU-indexed version
     * along with the last-updated timestamp.
     *
     * @since 1.1.0
     * @param bool|null $in_stock Optional filter for the query.
     * @return array|WP_Error
     */
    public function sync_price_list( $in_stock = null ) {
        $client   = new VentressLabs_Intcomex_Api_Client();
        $response = $client->get_price_list( $in_stock );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( ! is_array( $response ) ) {
            return new WP_Error( 'price_list_empty', __( 'GetPriceList returned no data.', 'ventresslabs-intcomex' ) );
        }

        $indexed = array();
        foreach ( $response as $entry ) {
            $sku = isset( $entry['Sku'] ) ? $entry['Sku'] : null;
            if ( $sku ) {
                $indexed[ $sku ] = $entry;
            }
        }

        update_option( self::OPT_PRICE_LIST, $indexed, false );
        update_option( self::OPT_PRICE_LIST_AT, time(), false );

        return count( $indexed );
    }

    /**
     * Sync the inventory (GetInventory) and persist a SKU-indexed version
     * along with the last-updated timestamp.
     *
     * @since 1.1.0
     * @param array $skus Optional SKU filter. Empty = full inventory.
     * @return array|WP_Error
     */
    public function sync_inventory( $skus = array() ) {
        $client   = new VentressLabs_Intcomex_Api_Client();
        $response = $client->get_inventory( $skus );

        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( ! is_array( $response ) ) {
            return new WP_Error( 'inventory_empty', __( 'GetInventory returned no data.', 'ventresslabs-intcomex' ) );
        }

        $indexed = array();
        foreach ( $response as $entry ) {
            $sku = isset( $entry['Sku'] ) ? $entry['Sku'] : null;
            if ( $sku ) {
                $indexed[ $sku ] = $entry;
            }
        }

        update_option( self::OPT_INVENTORY, $indexed, false );
        update_option( self::OPT_INVENTORY_AT, time(), false );

        return count( $indexed );
    }

    /**
     * Update WooCommerce products for the selected categories using the
     * cached catalog, price list and inventory data.
     *
     * @since 1.1.0
     * @return array
     */
    public function update_products() {
        $catalog            = get_option( self::OPT_CATALOG, array() );
        $prices             = get_option( self::OPT_PRICE_LIST, array() );
        $inventory          = get_option( self::OPT_INVENTORY, array() );
        $selected_categories = get_option( 'ventresslabs_intcomex_selected_categories', array() );

        if ( empty( $catalog ) ) {
            return array( 'skipped' => __( 'No hay catálogo sincronizado.', 'ventresslabs-intcomex' ) );
        }

        $results = array();
        $matching = array_filter( $catalog, function( $product ) use ( $selected_categories ) {
            return isset( $product['Category']['CategoryId'] )
                && ( empty( $selected_categories ) || in_array( $product['Category']['CategoryId'], $selected_categories, true ) );
        } );

        if ( empty( $matching ) ) {
            return array( 'skipped' => __( 'No se encontraron productos en las categorías seleccionadas.', 'ventresslabs-intcomex' ) );
        }

        $admin = new VentressLabs_Intcomex_Admin( 'ventresslabs-intcomex', '1.1.0' );
        foreach ( $matching as $sku => $api_product ) {
            $price = isset( $prices[ $sku ]['Price']['UnitPrice'] ) ? $prices[ $sku ]['Price']['UnitPrice'] : null;
            $stock = isset( $inventory[ $sku ] ) ? $this->extract_stock( $inventory[ $sku ] ) : null;

            $results[] = $admin->sync_single_product( $api_product, $price, $stock );
        }

        return $results;
    }

    /**
     * Extract the stock quantity from a GetInventory entry. Handles both a
     * scalar `InStock` field and a more structured shape, falling back to 0
     * so products never appear as in stock without confirmation.
     *
     * @since 1.1.0
     * @access private
     */
    private function extract_stock( $inv_entry ) {
        if ( ! is_array( $inv_entry ) ) {
            return 0;
        }
        if ( isset( $inv_entry['InStock'] ) ) {
            return (int) $inv_entry['InStock'];
        }
        if ( isset( $inv_entry['Quantity'] ) ) {
            return (int) $inv_entry['Quantity'];
        }
        if ( isset( $inv_entry['Stock'] ) ) {
            return (int) $inv_entry['Stock'];
        }
        return 0;
    }

    /**
     * Acquire the manual sync throttle. Returns false if a sync happened
     * less than SYNC_THROTTLE_SECS ago.
     *
     * @since 1.1.0
     * @return bool
     */
    public function acquire_throttle() {
        $lock = get_transient( self::THROTTLE_KEY );
        if ( false !== $lock ) {
            return false;
        }
        set_transient( self::THROTTLE_KEY, time(), self::SYNC_THROTTLE_SECS );
        return true;
    }

    /**
     * Seconds remaining until the throttle clears.
     *
     * @since 1.1.0
     * @return int
     */
    public function throttle_remaining() {
        $remaining = get_transient( self::THROTTLE_KEY );
        if ( false === $remaining ) {
            return 0;
        }
        // Transient stored as time(); calculate remaining via timeout mechanism.
        $timeout = self::SYNC_THROTTLE_SECS;
        return max( 0, $timeout - ( time() - (int) $remaining ) );
    }

    /**
     * Get the last sync timestamps for UI display.
     *
     * @since 1.1.0
     * @return array
     */
    public function get_sync_status() {
        return array(
            'catalog'    => array(
                'count' => count( (array) get_option( self::OPT_CATALOG, array() ) ),
                'at'    => (int) get_option( self::OPT_CATALOG_AT, 0 ),
            ),
            'price_list' => array(
                'count' => count( (array) get_option( self::OPT_PRICE_LIST, array() ) ),
                'at'    => (int) get_option( self::OPT_PRICE_LIST_AT, 0 ),
            ),
            'inventory' => array(
                'count' => count( (array) get_option( self::OPT_INVENTORY, array() ) ),
                'at'    => (int) get_option( self::OPT_INVENTORY_AT, 0 ),
            ),
            'extended' => array(
                'count' => count( (array) get_option( self::OPT_EXTENDED, array() ) ),
                'at'    => (int) get_option( self::OPT_EXTENDED_AT, 0 ),
            ),
        );
    }

    /**
     * Sync the extended catalog (DownloadExtendedCatalog).
     *
     * The endpoint returns a binary JSON file with detailed product content
     * including image URLs. The file is parsed and persisted as a SKU-indexed
     * map of image URLs, along with the last-updated timestamp. Honors the
     * "máximo 1 vez por mes" limit from Sección 4 of the IWS guide.
     *
     * @since 1.1.0
     * @param bool   $force  Skip the monthly throttle.
     * @param string $locale 'en' or 'es'.
     * @return array|WP_Error
     */
    public function sync_extended_catalog( $force = false, $locale = 'es' ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_DOWNLOAD_EXTENDED ) ) {
            return new WP_Error(
                'endpoint_disabled',
                __( 'DownloadExtendedCatalog está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' )
            );
        }

        if ( ! $force && ! $this->acquire_extended_throttle() ) {
            $remaining = $this->extended_throttle_remaining();
            return new WP_Error(
                'extended_throttled',
                sprintf(
                    /* translators: %d is hours remaining. */
                    __( 'DownloadExtendedCatalog limitado por la guía IWS (máx. 1/mes). Intenta en ~%d horas.', 'ventresslabs-intcomex' ),
                    max( 1, (int) ceil( $remaining / 3600 ) )
                )
            );
        }

        $client = new VentressLabs_Intcomex_Api_Client();
        $file   = $client->download_extended_catalog( $locale );

        if ( is_wp_error( $file ) ) {
            return $file;
        }
        if ( ! file_exists( $file ) ) {
            return new WP_Error( 'extended_file_missing', __( 'Archivo descargado no encontrado.', 'ventresslabs-intcomex' ) );
        }

        $contents = file_get_contents( $file );
        if ( false === $contents ) {
            @unlink( $file );
            return new WP_Error( 'extended_unreadable', __( 'No se pudo leer el archivo descargado.', 'ventresslabs-intcomex' ) );
        }
        @unlink( $file );

        $decoded = json_decode( $contents, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'extended_invalid', __( 'El archivo descargado no contiene JSON válido.', 'ventresslabs-intcomex' ) );
        }

        // Index by SKU. Each entry stores image URLs and any product specs.
        $indexed = array();
        foreach ( $decoded as $entry ) {
            $sku = isset( $entry['Sku'] ) ? $entry['Sku'] : ( isset( $entry['SKU'] ) ? $entry['SKU'] : null );
            if ( ! $sku ) {
                continue;
            }
            $images = $this->extract_image_urls( $entry );
            if ( empty( $images ) ) {
                continue;
            }
            $indexed[ $sku ] = array(
                'images'      => $images,
                'title'       => $entry['Title'] ?? $entry['Name'] ?? '',
                'description' => $entry['Description'] ?? $entry['LongDescription'] ?? '',
                'specs'       => $entry['Specs'] ?? $entry['Specifications'] ?? array(),
            );
        }

        update_option( self::OPT_EXTENDED, $indexed, false );
        update_option( self::OPT_EXTENDED_AT, time(), false );

        return count( $indexed );
    }

    /**
     * Extract a normalized list of image URLs from an extended-catalog entry.
     * Handles the common shapes (Images array, Image single string, ImageUrls).
     *
     * @since  1.1.0
     * @access private
     * @param  array $entry Extended catalog product entry.
     * @return array
     */
    private function extract_image_urls( $entry ) {
        $images = array();

        // Common key: "Images" -> array of objects or strings.
        if ( isset( $entry['Images'] ) && is_array( $entry['Images'] ) ) {
            foreach ( $entry['Images'] as $img ) {
                $url = is_array( $img ) ? ( $img['Url'] ?? $img['URL'] ?? '' ) : (string) $img;
                if ( $url ) {
                    $images[] = $url;
                }
            }
        }
        // Single image.
        if ( empty( $images ) && isset( $entry['Image'] ) ) {
            $url = is_array( $entry['Image'] ) ? ( $entry['Image']['Url'] ?? $entry['Image']['URL'] ?? '' ) : (string) $entry['Image'];
            if ( $url ) {
                $images[] = $url;
            }
        }
        // "ImageUrls" key.
        if ( empty( $images ) && isset( $entry['ImageUrls'] ) && is_array( $entry['ImageUrls'] ) ) {
            foreach ( $entry['ImageUrls'] as $url ) {
                if ( is_string( $url ) ) {
                    $images[] = $url;
                }
            }
        }

        return array_unique( $images );
    }

    /**
     * Acquire the DownloadExtendedCatalog monthly throttle.
     *
     * @since 1.1.0
     * @return bool
     */
    public function acquire_extended_throttle() {
        $lock = get_transient( self::EXTENDED_THROTTLE_KEY );
        if ( false !== $lock ) {
            return false;
        }
        set_transient( self::EXTENDED_THROTTLE_KEY, time(), self::EXTENDED_THROTTLE_SECS );
        return true;
    }

    /**
     * Seconds remaining until the extended throttle clears.
     *
     * @since 1.1.0
     * @return int
     */
    public function extended_throttle_remaining() {
        $lock = get_transient( self::EXTENDED_THROTTLE_KEY );
        if ( false === $lock ) {
            return 0;
        }
        return max( 0, self::EXTENDED_THROTTLE_SECS - ( time() - (int) $lock ) );
    }

    /**
     * Get the extended catalog entry for a SKU (image URLs + specs).
     *
     * @since 1.1.0
     * @param string $sku Product SKU.
     * @return array|null
     */
    public function get_extended_for_sku( $sku ) {
        $all = get_option( self::OPT_EXTENDED, array() );
        return isset( $all[ $sku ] ) ? $all[ $sku ] : null;
    }
}
