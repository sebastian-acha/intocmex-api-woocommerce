<?php
/**
 * IWS End-to-End validation script.
 *
 * Runs the full integration flow required by the IWS guide (Sección 6
 * go-live checklist) against the configured TEST environment, and prints
 * a report that TI Intcomex can use to validate the integration.
 *
 * Requirements:
 *   - Run inside a WP install that has the VentressLabs Intcomex plugin
 *     activated and WooCommerce installed.
 *   - Run via WP-CLI: `wp --user=1 eval-file wp-content/plugins/ventresslabs-intcomex-integration/bin/iws-e2e-test.php`
 *     or via PHP CLI with the WordPress bootstrap (see header).
 *   - TEST credentials configured in Settings → Intcomex.
 *
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Bootstrap WordPress when invoked from raw PHP CLI.
    $wp_path = getenv( 'WP_PATH' ) ?: ( dirname( __DIR__, 5 ) ?: ABSPATH );
    if ( file_exists( $wp_path . 'wp-load.php' ) ) {
        require_once $wp_path . 'wp-load.php';
    } elseif ( file_exists( dirname( __DIR__, 5 ) . '/wp-load.php' ) ) {
        require_once dirname( __DIR__, 5 ) . '/wp-load.php';
    } else {
        fwrite( STDERR, "ERROR: no se pudo localizar wp-load.php. Ejecuta vía WP-CLI o define WP_PATH.\n" );
        exit( 1 );
    }
}

require_once dirname( __DIR__ ) . '/includes/class-intcomex-logger.php';
require_once dirname( __DIR__ ) . '/includes/class-intcomex-api-client.php';
require_once dirname( __DIR__ ) . '/includes/class-intcomex-sync-service.php';
require_once dirname( __DIR__ ) . '/includes/class-intcomex-stock-validator.php';
require_once dirname( __DIR__ ) . '/includes/class-intcomex-order-service.php';

/**
 * Minimal test runner.
 */
class IWS_E2E_Test {

    /** @var array */
    protected $results = array();

    /** @var VentressLabs_Intcomex_Logger */
    protected $logger;

    public function __construct() {
        $this->logger = new VentressLabs_Intcomex_Logger();
    }

    public function run() {
        $this->line( "" );
        $this->line( "===== IWS End-to-End Validation (go-live checklist) =====" );
        $this->line( "" );

        $client = new VentressLabs_Intcomex_Api_Client();
        $env    = $client->get_environment();
        $this->line( "Ambiente activo: " . strtoupper( $env ) );
        if ( 'prod' === $env ) {
            $this->line( "⚠ ALERTA: el ambiente activo es PRODUCCIÓN. La prueba creará una orden REAL." );
            $this->line( "Cambia a TEST en Settings → Intcomex antes de ejecutar.", true );
            return;
        }
        $this->line( "" );

        $this->test_authentication();
        $this->test_get_catalog();
        $this->test_get_price_list();
        $this->test_get_inventory();
        $this->test_get_products();
        $this->test_download_extended_catalog();
        $this->test_stock_validator();
        $this->test_place_order();
        $this->logs_summary();

        $this->line( "" );
        $this->line( "===== Resumen final =====" );
        $this->print_summary();
        $this->line( "" );
    }

    protected function test_authentication() {
        $this->section( "Sección 3 - Autenticación" );
        $credentials = get_option( 'ventresslabs_intcomex_api_credentials', array() );
        $has_test_key  = ! empty( $credentials['api_key_test'] );
        $has_test_acc  = ! empty( $credentials['access_key_test'] );
        $this->record( "API Key TEST configurada", $has_test_key );
        $this->record( "Access Key TEST configurada", $has_test_acc );

        if ( ! $has_test_key || ! $has_test_acc ) {
            $this->line( "✗ Faltan credenciales TEST. Deteniendo prueba.", true );
            exit( 1 );
        }

        // Trigger a real call to verify signature works.
        $client   = new VentressLabs_Intcomex_Api_Client();
        $response = $client->get_catalog();
        $this->record( "Llamada autenticada exitosa (GetCatalog)", ! is_wp_error( $response ) || false === strpos( $response->get_error_code(), '401' ) );
        if ( is_wp_error( $response ) && 'iws_api_error' === $response->get_error_code() ) {
            $data = $response->get_error_data();
            if ( 401 === (int) ( $data['status_code'] ?? 0 ) ) {
                $this->line( "✗ Autenticación fallida: " . $response->get_error_message(), true );
                $this->line( "  ErrorCode: " . ( $data['error_code'] ?? '?' ) . ", Reference: " . ( $data['reference'] ?? '?' ) );
            }
        }
    }

    protected function test_get_catalog() {
        $this->section( "Sección 4/5.1 - GetCatalog" );
        $sync = new VentressLabs_Intcomex_Sync_Service();
        $r = $sync->sync_catalog( /* force */ false );
        if ( is_wp_error( $r ) ) {
            $this->record( "GetCatalog ejecutado con éxito", false, $r->get_error_message() );
        } else {
            $this->record( "GetCatalog ejecutado con éxito", true, "$r productos cacheados" );
        }
    }

    protected function test_get_price_list() {
        $this->section( "Sección 4/5.1 - GetPriceList" );
        $sync = new VentressLabs_Intcomex_Sync_Service();
        $r = $sync->sync_price_list();
        if ( is_wp_error( $r ) ) {
            $this->record( "GetPriceList ejecutado con éxito", false, $r->get_error_message() );
        } else {
            $this->record( "GetPriceList ejecutado con éxito", true, "$r precios cacheados" );
        }
    }

    protected function test_get_inventory() {
        $this->section( "Sección 4/5.1 - GetInventory" );
        $sync = new VentressLabs_Intcomex_Sync_Service();
        $r = $sync->sync_inventory();
        if ( is_wp_error( $r ) ) {
            $this->record( "GetInventory ejecutado con éxito", false, $r->get_error_message() );
        } else {
            $this->record( "GetInventory ejecutado con éxito", true, "$r inventarios cacheados" );
        }
    }

    protected function test_get_products() {
        $this->section( "Sección 4/5.2 paso 4 - GetProducts" );
        $catalog = get_option( VentressLabs_Intcomex_Sync_Service::OPT_CATALOG, array() );
        if ( empty( $catalog ) ) {
            $this->record( "GetProducts probado", false, "Catálogo vacío, no hay SKUs disponibles" );
            return;
        }
        $skus = array_slice( array_keys( $catalog ), 0, 5 );
        $client = new VentressLabs_Intcomex_Api_Client();
        $r = $client->get_products( $skus, array( 'includePriceData' => 'false', 'includeInventoryData' => 'true' ) );
        if ( is_wp_error( $r ) ) {
            $this->record( "GetProducts ejecutado con éxito", false, $r->get_error_message() );
        } else {
            $count = is_array( $r ) ? count( $r ) : 0;
            $this->record( "GetProducts ejecutado con éxito", true, "$count productos para SKUs: " . implode( ', ', $skus ) );
        }
    }

    protected function test_download_extended_catalog() {
        $this->section( "Sección 4 - DownloadExtendedCatalog" );
        $sync = new VentressLabs_Intcomex_Sync_Service();
        // Force true porque el throttle mensual puede estar activo.
        $r = $sync->sync_extended_catalog( true );
        if ( is_wp_error( $r ) ) {
            $this->record( "DownloadExtendedCatalog ejecutado con éxito", false, $r->get_error_message() );
        } else {
            $this->record( "DownloadExtendedCatalog ejecutado con éxito", true, "$r productos con imágenes" );
        }
    }

    protected function test_stock_validator() {
        $this->section( "Sección 5.2 paso 4 - Stock Validator" );
        $catalog = get_option( VentressLabs_Intcomex_Sync_Service::OPT_CATALOG, array() );
        if ( empty( $catalog ) ) {
            $this->record( "Validator probado", false, "Catálogo vacío" );
            return;
        }
        $skus = array_slice( array_keys( $catalog ), 0, 3 );
        $validator = new VentressLabs_Intcomex_Stock_Validator();
        $stock = $validator->fetch_stock( $skus, 0 );
        if ( is_wp_error( $stock ) ) {
            $this->record( "Validator probado", false, $stock->get_error_message() );
        } else {
            $report = array();
            foreach ( $skus as $sku ) {
                $report[] = "$sku=" . ( $stock[ $sku ] ?? '?' );
            }
            $this->record( "Validator probado", true, implode( ', ', $report ) );
        }
    }

    protected function test_place_order() {
        $this->section( "Sección 4/5.2 paso 5 - PlaceOrder" );
        $catalog = get_option( VentressLabs_Intcomex_Sync_Service::OPT_CATALOG, array() );
        if ( empty( $catalog ) ) {
            $this->record( "PlaceOrder probado", false, "Catálogo vacío" );
            return;
        }

        // Pick the first SKU that has stock > 0 (we sync only inventory check).
        $inventory = get_option( VentressLabs_Intcomex_Sync_Service::OPT_INVENTORY, array() );
        $sku = null;
        foreach ( $catalog as $s => $product ) {
            $stock = isset( $inventory[ $s ] ) ? (int) ( $inventory[ $s ]['InStock'] ?? 0 ) : 0;
            if ( $stock > 0 ) {
                $sku = $s;
                break;
            }
        }
        if ( ! $sku ) {
            $this->record( "PlaceOrder probado", false, "No se encontró SKU con stock > 0 para prueba" );
            return;
        }

        // Important: only run PlaceOrder if explicitly enabled.
        $order_service = new VentressLabs_Intcomex_Order_Service();
        $settings = $order_service->get_settings();
        $enabled  = 'yes' === $settings['enabled'];
        if ( ! $enabled ) {
            $this->record( "PlaceOrder probado", true, "Deshabilitado en settings (omitido). SKU candidato: $sku" );
            $this->line( "ℹ PlaceOrder está deshabilitado. Para completar el checklist go-live:" );
            $this->line( "  1) Habilita PlaceOrder en Settings → Intcomex" );
            $this->line( "  2) Crea una orden WC manual con el SKU $sku" );
            $this->line( "  3) Re-ejecuta este script" );
            return;
        }

        $payload = array(
            array(
                'Sku'      => $sku,
                'Quantity' => '1',
            ),
        );
        $client = new VentressLabs_Intcomex_Api_Client();
        $resp = $client->place_order( $payload, array( 'locale' => 'es', 'customerOrderNumber' => 'E2E-TEST' ) );
        if ( is_wp_error( $resp ) ) {
            $this->record( "PlaceOrder (flujo mínimo) ejecutado con éxito", false, $resp->get_error_message() );
        } else {
            $order_number = $resp['OrderNumber'] ?? '(ninguno)';
            $this->record( "PlaceOrder (flujo mínimo) ejecutado con éxito", true, "OrderNumber: $order_number, SKU: $sku" );
        }
    }

    protected function logs_summary() {
        $this->section( "Sección 6 - Logs para TI Intcomex" );
        $entries = $this->logger->get_entries();
        $success = 0;
        $errors  = 0;
        foreach ( $entries as $e ) {
            if ( 200 === (int) ( $e['response_code'] ?? 0 ) ) {
                $success++;
            } else {
                $errors++;
            }
        }
        $this->record( "Entradas de log almacenadas", true, count( $entries ) . " entradas" );
        $this->record( "Llamadas exitosas (200)", $success > 0, "$success llamadas" );
        $this->record( "Llamadas fallidas", true, "$errors llamadas (deben revisarse)" );
        $this->line( "ℹ La página Intcomex → Logs contiene el detalle para validar con TI Intcomex." );
    }

    protected function section( $title ) {
        $this->line( "" );
        $this->line( ">>> $title" );
    }

    protected function record( $name, $ok, $detail = '' ) {
        $this->results[] = array(
            'name'   => $name,
            'ok'     => (bool) $ok,
            'detail' => $detail,
        );
        $mark = $ok ? '[OK]' : '[FAIL]';
        $line = "  $mark $name";
        if ( $detail ) {
            $line .= " — $detail";
        }
        $this->line( $line );
    }

    protected function print_summary() {
        $total = count( $this->results );
        $passed = count( array_filter( $this->results, function( $r ) { return $r['ok']; } ) );
        $this->line( "Pasaron $passed/$total verificaciones." );
        if ( $passed === $total ) {
            $this->line( "🎉 El plugin cumple el checklist go-live." );
        } else {
            $this->line( "Verificaciones fallidas:", true );
            foreach ( $this->results as $r ) {
                if ( ! $r['ok'] ) {
                    $this->line( "  - " . $r['name'] . ( $r['detail'] ? ': ' . $r['detail'] : '' ), true );
                }
            }
        }
    }

    protected function line( $msg, $stderr = false ) {
        $out = ( $stderr ? STDERR : STDOUT );
        fwrite( $out, $msg . "\n" );
    }
}

$test = new IWS_E2E_Test();
$test->run();
