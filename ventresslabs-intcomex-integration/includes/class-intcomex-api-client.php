<?php
/**
 * The API client for interacting with the Intcomex API (IWS).
 *
 * Implements all endpoints required by the IWS guide for physical products:
 *   - GetCatalog
 *   - GetPriceList
 *   - GetInventory
 *   - GetProducts / GetProduct
 *   - DownloadExtendedCatalog
 *   - PlaceOrder
 *
 * Authentication uses the documented SHA-256 signature scheme built from
 * apiKey + accessKey + UTC timestamp.
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

class VentressLabs_Intcomex_Api_Client {

    /**
     * Base URLs per environment.
     *
     * @since 1.1.0
     * @var array
     */
    private $base_urls = array(
        'test' => 'https://intcomex-test.apigee.net/v1/',
        'prod' => 'https://intcomex-prod.apigee.net/v1/',
    );

    /**
     * Active environment: 'test' or 'prod'.
     *
     * @since 1.1.0
     * @var string
     */
    private $environment;

    /**
     * Base URL currently in use.
     *
     * @since 1.0.0
     * @var string
     */
    private $api_url;

    /**
     * The API Key (public).
     *
     * @since 1.0.0
     * @var string
     */
    private $api_key;

    /**
     * The Access Key (private, used to sign).
     *
     * @since 1.0.0
     * @var string
     */
    private $access_key;

    /**
     * Logger instance.
     *
     * @since 1.1.0
     * @var VentressLabs_Intcomex_Logger
     */
    private $logger;

    /**
     * HTTP timeout in seconds.
     *
     * @since 1.1.0
     * @var int
     */
    private $timeout = 30;

    /**
     * Initialize the client loading credentials for the active environment.
     *
     * @since 1.1.0
     */
    public function __construct() {
        $credentials = get_option( 'ventresslabs_intcomex_api_credentials', array() );
        $this->environment = isset( $credentials['environment'] ) ? $credentials['environment'] : 'test';

        if ( ! isset( $this->base_urls[ $this->environment ] ) ) {
            $this->environment = 'test';
        }

        $key           = 'test' === $this->environment ? 'api_key_test' : 'api_key_prod';
        $access        = 'test' === $this->environment ? 'access_key_test' : 'access_key_prod';
        $this->api_key = isset( $credentials[ $key ] ) ? $credentials[ $key ] : '';
        $this->access_key = isset( $credentials[ $access ] ) ? $credentials[ $access ] : '';
        $this->api_url = $this->base_urls[ $this->environment ];

        if ( class_exists( 'VentressLabs_Intcomex_Logger' ) ) {
            $this->logger = new VentressLabs_Intcomex_Logger();
        }
    }

    /**
     * Get the active environment.
     *
     * @since 1.1.0
     * @return string 'test' or 'prod'.
     */
    public function get_environment() {
        return $this->environment;
    }

    /**
     * Generate the authorization header for API requests following the
     * IWS signature scheme: signature = sha256( apiKey,accessKey,utcTimestamp ).
     *
     * @since  1.1.0
     * @access private
     * @return array Authorization headers, empty if credentials are missing.
     */
    private function get_auth_header() {
        if ( empty( $this->api_key ) || empty( $this->access_key ) ) {
            return array();
        }

        $utc_timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );
        $signing_string = sprintf( '%s,%s,%s', $this->api_key, $this->access_key, $utc_timestamp );
        $signature      = hash( 'sha256', $signing_string );

        $token = sprintf(
            'apiKey=%s&utcTimeStamp=%s&signature=%s',
            $this->api_key,
            $utc_timestamp,
            $signature
        );

        return array(
            'Authorization' => 'Bearer ' . $token,
            'Accept'         => 'application/json',
        );
    }

    /**
     * Validate that credentials are configured for the active environment.
     *
     * @since 1.1.0
     * @return true|WP_Error
     */
    private function validate_credentials() {
        if ( empty( $this->api_key ) || empty( $this->access_key ) ) {
            return new WP_Error(
                'missing_credentials',
                sprintf(
                    /* translators: %s is the environment name. */
                    __( 'Missing API Key or Access Key for the %s environment.', 'ventresslabs-intcomex' ),
                    strtoupper( $this->environment )
                )
            );
        }
        return true;
    }

    /**
     * Perform an HTTP request to the IWS API and standardize the response.
     * On a 401 "InvalidTimeStamp" (ErrorCode 12) the request is retried once
     * with a fresh timestamp to mitigate client clock skew.
     *
     * @since  1.1.0
     * @access private
     * @param  string $endpoint Endpoint path (relative to the base URL).
     * @param  array  $args     wp_remote_* args.
     * @param  string $method   'GET' or 'POST'.
     * @return array|WP_Error    Decoded body on success.
     */
    private function request( $endpoint, $args = array(), $method = 'GET' ) {
        $credentials_check = $this->validate_credentials();
        if ( is_wp_error( $credentials_check ) ) {
            return $credentials_check;
        }

        $url = $this->api_url . $endpoint;

        for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
            $headers = $this->get_auth_header();
            if ( empty( $headers ) ) {
                return new WP_Error( 'missing_credentials', __( 'API credentials are not configured.', 'ventresslabs-intcomex' ) );
            }

            $args = wp_parse_args( $args, array(
                'headers' => array(),
                'timeout' => $this->timeout,
                'method'  => $method,
            ) );
            $args['headers'] = array_merge( $args['headers'], $headers );

            $start  = microtime( true );
            $raw_response = ( 'POST' === $method )
                ? wp_remote_post( $url, $args )
                : wp_remote_get( $url, $args );
            $elapsed = round( ( microtime( true ) - $start ) * 1000, 2 );

            if ( is_wp_error( $raw_response ) ) {
                $this->log( $method, $url, $args['body'] ?? null, null, null, $raw_response, $elapsed );
                return $raw_response;
            }

            $code = wp_remote_retrieve_response_code( $raw_response );
            $body = wp_remote_retrieve_body( $raw_response );

            // Retry once if timestamp rejected.
            if ( 401 === (int) $code && $attempt < 2 ) {
                $decoded = json_decode( $body, true );
                if ( isset( $decoded['ErrorCode'] ) && 12 === (int) $decoded['ErrorCode'] ) {
                    continue;
                }
            }

            $this->log( $method, $url, $args['body'] ?? null, $code, $body, null, $elapsed );
            break;
        }

        if ( 200 !== (int) $code ) {
            return $this->build_error( $code, $body );
        }

        return json_decode( $body, true );
    }

    /**
     * Build a WP_Error from an IWS error response body.
     *
     * @since  1.1.0
     * @access private
     * @param  int    $code HTTP status code.
     * @param  string $body Raw response body.
     * @return WP_Error
     */
    private function build_error( $code, $body ) {
        $decoded = json_decode( $body, true );
        $message = isset( $decoded['Message'] ) ? $decoded['Message'] : __( 'Unknown IWS API error.', 'ventresslabs-intcomex' );
        $data    = array(
            'status_code' => $code,
        );
        if ( isset( $decoded['ErrorCode'] ) ) {
            $data['error_code'] = (int) $decoded['ErrorCode'];
        }
        if ( isset( $decoded['Reference'] ) ) {
            $data['reference'] = $decoded['Reference'];
        }
        return new WP_Error( 'iws_api_error', $message, $data );
    }

    /**
     * Log an API call if a logger is available.
     *
     * @since  1.1.0
     * @access private
     */
    private function log( $method, $url, $request_body, $response_code, $response_body, $wp_error, $elapsed_ms ) {
        if ( $this->logger ) {
            $this->logger->log_call( array(
                'environment'   => $this->environment,
                'method'        => $method,
                'url'           => $url,
                'request_body'  => $request_body,
                'response_code'  => $response_code,
                'response_body' => $response_body,
                'wp_error'      => $wp_error ? $wp_error->get_error_message() : null,
                'elapsed_ms'    => $elapsed_ms,
            ) );
        }
    }

    /**
     * Build a query string for array-style query params (?sku=a&sku=b).
     *
     * @since  1.1.0
     * @access private
     * @param  array $params Key-value parameters.
     * @return string
     */
    private function build_query( $params ) {
        $parts = array();
        foreach ( $params as $key => $value ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $v ) {
                    $parts[] = $key . '=' . rawurlencode( $v );
                }
            } else {
                $parts[] = $key . '=' . rawurlencode( $value );
            }
        }
        return empty( $parts ) ? '' : '?' . implode( '&', $parts );
    }

    /**
     * GetCatalog: full product catalog.
     *
     * @since  1.1.0
     * @return array|WP_Error
     */
    public function get_catalog() {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_CATALOG ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'GetCatalog está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        return $this->request( 'getcatalog' );
    }

    /**
     * GetPriceList: prices for the whole catalog.
     *
     * @since  1.1.0
     * @param  bool|null $in_stock Optional filter (true=in stock, false=out of stock).
     * @return array|WP_Error
     */
    public function get_price_list( $in_stock = null ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRICE_LIST ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'GetPriceList está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        $params = array();
        if ( null !== $in_stock ) {
            $params['inStock'] = $in_stock ? 'true' : 'false';
        }
        return $this->request( 'getpricelist' . $this->build_query( $params ) );
    }

    /**
     * GetInventory: real-time inventory for one or many SKUs.
     *
     * @since  1.1.0
     * @param  array|int $skus Optional SKU filter.
     * @return array|WP_Error
     */
    public function get_inventory( $skus = array() ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_INVENTORY ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'GetInventory está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        $params = array();
        if ( ! empty( $skus ) ) {
            $params['sku'] = (array) $skus;
        }
        return $this->request( 'getinventory' . $this->build_query( $params ) );
    }

    /**
     * GetProducts: real-time info for many SKUs.
     *
     * The IWS spec requires a comma-separated 'skusList' query param and
     * limits each request to 100 SKUs. This method chunks accordingly.
     *
     * @since  1.1.0
     * @param  array $skus List of SKUs.
     * @param  array $args Optional query args (locale, includePriceData, etc).
     * @return array|WP_Error
     */
    public function get_products( array $skus, array $args = array() ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRODUCTS ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'GetProducts está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        if ( empty( $skus ) ) {
            return new WP_Error( 'missing_skus', __( 'No SKUs provided to GetProducts.', 'ventresslabs-intcomex' ) );
        }

        $skus     = array_values( array_filter( array_map( 'strval', $skus ) ) );
        $chunks   = array_chunk( $skus, 100 );
        $combined = array();

        foreach ( $chunks as $chunk ) {
            $params = wp_parse_args( $args, array(
                'skusList'            => implode( ',', $chunk ),
                'includeInventoryData' => 'true',
            ) );
            $response = $this->request( 'getproducts' . $this->build_query( $params ) );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            if ( is_array( $response ) ) {
                foreach ( $response as $product ) {
                    $combined[] = $product;
                }
            }
        }

        return $combined;
    }

    /**
     * GetProduct: real-time info for a single SKU.
     *
     * @since  1.1.0
     * @param  string $sku Product SKU.
     * @param  array  $args Optional query args.
     * @return array|WP_Error
     */
    public function get_product( $sku, array $args = array() ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRODUCTS ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'GetProduct está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        if ( empty( $sku ) ) {
            return new WP_Error( 'missing_sku', __( 'No SKU provided to GetProduct.', 'ventresslabs-intcomex' ) );
        }
        $params = wp_parse_args( $args, array(
            'skusList'              => $sku,
            'includeInventoryData' => 'true',
        ) );
        return $this->request( 'getproduct' . $this->build_query( $params ) );
    }

    /**
     * DownloadExtendedCatalog: URLs to product images.
     *
     * The IWS spec returns this as a binary file (application/octet-stream),
     * not as JSON. This method streams the response to a temporary file and
     * returns the absolute path. The caller is responsible for moving/parsing
     * the file and removing it when done.
     *
     * @since  1.1.0
     * @param  string $locale 'en' or 'es'.
     * @return string|WP_Error Absolute path to the downloaded file.
     */
    public function download_extended_catalog( $locale = 'es' ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_DOWNLOAD_EXTENDED ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'DownloadExtendedCatalog está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        $params = array(
            'format' => 'json',
            'locale' => in_array( $locale, array( 'en', 'es' ), true ) ? $locale : 'es',
        );
        return $this->download_binary( 'downloadextendedcatalog' . $this->build_query( $params ) );
    }

    /**
     * Download a binary endpoint to a temporary file on disk.
     *
     * @since  1.1.0
     * @access private
     * @param  string $endpoint Endpoint path (with query string).
     * @return string|WP_Error  Absolute path to the downloaded file.
     */
    private function download_binary( $endpoint ) {
        $credentials_check = $this->validate_credentials();
        if ( is_wp_error( $credentials_check ) ) {
            return $credentials_check;
        }

        $url = $this->api_url . $endpoint;

        for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
            $headers = $this->get_auth_header();
            if ( empty( $headers ) ) {
                return new WP_Error( 'missing_credentials', __( 'API credentials are not configured.', 'ventresslabs-intcomex' ) );
            }

            $args = array(
                'headers'  => array_merge( array( 'Accept' => 'application/octet-stream' ), $headers ),
                'timeout'  => 600, // Binary files may be large.
                'method'   => 'GET',
                'stream'   => true,
                'filename' => '', // set below.
            );

            $tf = tmpfile();
            if ( false === $tf ) {
                return new WP_Error( 'tmpfile_failed', __( 'No se pudo crear archivo temporal.', 'ventresslabs-intcomex' ) );
            }
            $path = stream_get_meta_data( $tf )['uri'];
            $args['filename'] = $path;

            $start = microtime( true );
            $raw_response = wp_remote_get( $url, $args );
            $elapsed = round( ( microtime( true ) - $start ) * 1000, 2 );

            if ( is_wp_error( $raw_response ) ) {
                $this->log( 'GET', $url, null, null, null, $raw_response, $elapsed );
                fclose( $tf );
                return $raw_response;
            }

            $code = 200; // for binary, status code retrieved below.
            $code = wp_remote_retrieve_response_code( $raw_response );

            // Retry once if timestamp rejected.
            if ( 401 === (int) $code && $attempt < 2 ) {
                // Read body for error inspection.
                $error_body = file_exists( $path ) ? file_get_contents( $path ) : '';
                $decoded    = json_decode( $error_body, true );
                if ( isset( $decoded['ErrorCode'] ) && 12 === (int) $decoded['ErrorCode'] ) {
                    fclose( $tf );
                    continue;
                }
            }

            $this->log( 'GET', $url, null, $code, '[binary file ' . filesize( $path ) . ' bytes]', null, $elapsed );

            if ( 200 !== (int) $code ) {
                $error_body = file_exists( $path ) ? file_get_contents( $path ) : '';
                $error      = $this->build_error( $code, $error_body );
                fclose( $tf );
                return $error;
            }

            // Move temporary file to a path we can return; tmpfile is auto-removed
            // when $tf goes out of scope, so we copy its contents to a stable path.
            $dest = trailingslashit( sys_get_temp_dir() ) . 'intcomex_extended_catalog_' . wp_generate_password( 8, false ) . '.json';
            $contents = file_get_contents( $path );
            if ( false === $contents || false === file_put_contents( $dest, $contents ) ) {
                fclose( $tf );
                return new WP_Error( 'save_failed', __( 'No se pudo persistir el archivo descargado.', 'ventresslabs-intcomex' ) );
            }
            fclose( $tf );
            return $dest;
        }

        return new WP_Error( 'download_failed', __( 'DownloadExtendedCatalog falló tras reintentos.', 'ventresslabs-intcomex' ) );
    }

    /**
     * PlaceOrder: create an order in IWS.
     *
     * The body can be a simple list of items `[{Sku, Quantity}, ...]` or a
     * full RequestOrder object with StoreOrder / Customer / Billing / Shipping
     * / Shipments / Items.
     *
     * @since  1.1.0
     * @param  array  $payload            Order body as defined by the IWS spec.
     * @param  array  $query_args Optional query args: locale, tag,
     *                                    customerOrderNumber, locationId, carrierId.
     * @return array|WP_Error
     */
    public function place_order( array $payload, array $query_args = array() ) {
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_PLACE_ORDER ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'PlaceOrder está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }
        if ( empty( $payload ) ) {
            return new WP_Error( 'empty_order', __( 'PlaceOrder payload is empty.', 'ventresslabs-intcomex' ) );
        }

        $endpoint = 'placeorder';
        if ( ! empty( $query_args ) ) {
            $endpoint .= $this->build_query( $query_args );
        }

        $args = array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
        );

        return $this->request( $endpoint, $args, 'POST' );
    }
}
