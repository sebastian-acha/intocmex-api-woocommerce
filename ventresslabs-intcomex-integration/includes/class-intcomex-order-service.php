<?php
/**
 * Order service: maps WooCommerce orders to IWS PlaceOrder calls.
 *
 * Implements Sección 5.2 paso 5 of the IWS guide: when a customer completes
 * a purchase, the system must call PlaceOrder to register the order in
 * Intcomex. The resulting OrderNumber is persisted as order meta so the
 * link WC ↔ IWS is preserved for fulfillment and reporting.
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

class VentressLabs_Intcomex_Order_Service {

    /**
     * Settings option key.
     */
    const SETTINGS_KEY = 'ventresslabs_intcomex_order_settings';

    /**
     * WC order meta key for the IWS OrderNumber.
     */
    const META_IWS_ORDER_NUMBER = '_intcomex_iws_order_number';

    /**
     * WC order meta key for the last IWS error (if any).
     */
    const META_IWS_LAST_ERROR = '_intcomex_iws_last_error';

    /**
     * WC order meta key for the full IWS response (json-encoded).
     */
    const META_IWS_LAST_RESPONSE = '_intcomex_iws_last_response';

    /**
     * WC order meta key marking WC order that needs retry.
     */
    const META_IWS_PENDING_RETRY = '_intcomex_iws_pending_retry';

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
     * Get the order service settings with defaults.
     *
     * @since 1.1.0
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'send_customer_info'  => 'yes',
            'auto_fail_order'     => 'yes',
            'allow_retry'         => 'yes',
            'default_locale'      => 'es',
            'store_id'            => '',
            'tag'                 => '',
        );
        $settings = get_option( self::SETTINGS_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Hooked at woocommerce_payment_complete (or woocommerce_checkout_order_processed
     * for gateways that don't trigger the former). Calls IWS PlaceOrder with the
     * WC order data and persists the resulting OrderNumber.
     *
     * @since 1.1.0
     * @param int $order_id WooCommerce order ID.
     * @return array|WP_Error
     */
    public function handle_order_created( $order_id ) {
        // Endpoint toggle is the single source of truth (legacy 'enabled'
        // field was migrated in 2.0.0 and is no longer consulted).
        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_PLACE_ORDER ) ) {
            return new WP_Error( 'endpoint_disabled', __( 'PlaceOrder está deshabilitado en Settings → Endpoints.', 'ventresslabs-intcomex' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Orden WC no encontrada.', 'ventresslabs-intcomex' ) );
        }

        // Avoid duplicate PlaceOrder for the same WC order.
        $existing = $order->get_meta( self::META_IWS_ORDER_NUMBER, true );
        if ( ! empty( $existing ) ) {
            return new WP_Error(
                'already_placed',
                sprintf( __( 'La orden #%d ya tiene OrderNumber IWS: %s', 'ventresslabs-intcomex' ), $order_id, $existing )
            );
        }

        $settings   = $this->get_settings();
        $payload    = $this->build_payload( $order, $settings );
        $query_args = $this->build_query_args( $order, $settings );

        $client   = new VentressLabs_Intcomex_Api_Client();
        $response = $client->place_order( $payload, $query_args );

        if ( is_wp_error( $response ) ) {
            $this->handle_place_order_error( $order, $response, $settings );
            return $response;
        }

        $this->handle_place_order_success( $order, $response );
        return $response;
    }

    /**
     * Build the PlaceOrder request body from a WC order.
     *
     * @since 1.1.0
     * @param WC_Order $order    Order object.
     * @param array    $settings Service settings.
     * @return array
     */
    public function build_payload( $order, $settings = array() ) {
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        // Ensure defaults if caller didn't pass a full settings array.
        $settings = wp_parse_args( $settings, $this->get_settings() );

        $items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $sku     = $product ? $product->get_sku() : '';
            if ( empty( $sku ) ) {
                continue;
            }
            $items[] = array(
                'Sku'      => $sku,
                'Quantity'  => (string) (int) $item->get_quantity(),
            );
        }

        // Filter IWS-empty items (products that don't have SKU).
        $items = array_filter( $items );

        if ( empty( $items ) ) {
            return array();
        }

        // Simple list of items (minimum viable order per IWS spec).
        if ( 'yes' !== $settings['send_customer_info'] ) {
            return $items;
        }

        // Extended RequestOrder payload with customer/billing/shipping.
        $store_order = array(
            'StoreOrderNumber' => (string) $order->get_id(),
        );
        if ( ! empty( $settings['store_id'] ) ) {
            $store_order['StoreId'] = $settings['store_id'];
        }

        $customer = array(
            'FirstName' => $order->get_billing_first_name(),
            'LastName'  => $order->get_billing_last_name(),
            'Email'     => $order->get_billing_email(),
        );
        if ( $order->get_billing_phone() ) {
            $customer['Cellphone'] = $order->get_billing_phone();
        }
        $store_order['Customer'] = $customer;

        $store_order['Billing'] = $this->build_address_from_order( $order, 'billing' );
        $store_order['Shipping'] = $this->build_address_from_order( $order, 'shipping' );
        $store_order['OrderTotal'] = (float) $order->get_total();

        $payload = array(
            'StoreOrder' => $store_order,
            'Items'       => $items,
        );
        if ( 'yes' === $settings['send_customer_info'] ) {
            // Force taxes flag so IWS uses our prices (default behavior).
            $payload['TaxesIncludedInPrice'] = false;
        }
        return $payload;
    }

    /**
     * Build an IWS address block from a WC order's billing or shipping fields.
     *
     * @since  1.1.0
     * @access private
     * @param  WC_Order $order Order.
     * @param  string   $type  'billing' or 'shipping'.
     * @return array
     */
    private function build_address_from_order( $order, $type = 'billing' ) {
        $get = function( $key ) use ( $order, $type ) {
            $method = "get_{$type}_{$key}";
            if ( method_exists( $order, $method ) ) {
                return $order->$method();
            }
            return '';
        };

        $address = array(
            'FirstName'  => $get( 'first_name' ),
            'LastName'   => $get( 'last_name' ),
            'Email'      => 'billing' === $type ? $order->get_billing_email() : $order->get_billing_email(),
            'Address'    => trim( $get( 'address_1' ) . ' ' . $get( 'address_2' ) ),
            'City'       => $get( 'city' ),
            'State'      => $get( 'state' ),
            'CountryId'  => strtolower( $get( 'country' ) ),
        );
        $phone = 'billing' === $type ? $order->get_billing_phone() : $order->get_shipping_phone();
        if ( $phone ) {
            $address['Cellphone'] = $phone;
        }
        if ( $order->get_billing_postcode() ) {
            $address['ZipCode'] = $order->get_billing_postcode();
        }
        return $address;
    }

    /**
     * Build query args for PlaceOrder (locale, tag, customerOrderNumber).
     *
     * @since 1.1.0
     * @param WC_Order $order Order.
     * @param array    $settings Service settings.
     * @return array
     */
    public function build_query_args( $order, $settings = array() ) {
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $settings = wp_parse_args( $settings, $this->get_settings() );

        $args = array();
        if ( ! empty( $settings['default_locale'] ) ) {
            $args['locale'] = in_array( $settings['default_locale'], array( 'en', 'es' ), true ) ? $settings['default_locale'] : 'es';
        }
        if ( ! empty( $settings['tag'] ) ) {
            $args['tag'] = $settings['tag'];
        }
        $args['customerOrderNumber'] = (string) $order->get_id();
        return $args;
    }

    /**
     * Handle a successful PlaceOrder response: persist OrderNumber, add order note,
     * clear retry flag.
     *
     * @since 1.1.0
     * @param WC_Order $order Order.
     * @param array    $response IWS response.
     */
    private function handle_place_order_success( $order, $response ) {
        $order_number = isset( $response['OrderNumber'] ) ? (string) $response['OrderNumber'] : '';

        $order->update_meta_data( self::META_IWS_ORDER_NUMBER, $order_number );
        $order->update_meta_data( self::META_IWS_LAST_RESPONSE, wp_json_encode( $response ) );
        $order->update_meta_data( self::META_IWS_LAST_ERROR, '' );
        $order->delete_meta_data( self::META_IWS_PENDING_RETRY );
        $order->save();

        $note = sprintf( __( 'PlaceOrder IWS exitoso. OrderNumber: %s', 'ventresslabs-intcomex' ), $order_number );
        $order->add_order_note( $note );

        // If the response includes shipments with tracking, save them as note.
        if ( ! empty( $response['Shipments'] ) && is_array( $response['Shipments'] ) ) {
            foreach ( $response['Shipments'] as $shipment ) {
                if ( ! empty( $shipment['TrackingId'] ) ) {
                    $track = sprintf(
                        __( 'Tracking IWS: %s — %s', 'ventresslabs-intcomex' ),
                        $shipment['TrackingId'],
                        isset( $shipment['TrackingUrl'] ) ? $shipment['TrackingUrl'] : ''
                    );
                    $order->add_order_note( $track );
                }
            }
        }
    }

    /**
     * Handle a failed PlaceOrder response: WC order note, persist error,
     * mark order as failed if configured, set pending retry flag.
     *
     * @since 1.1.0
     * @param WC_Order  $order Order.
     * @param WP_Error  $error Error from the API client.
     * @param array     $settings Service settings.
     */
    private function handle_place_order_error( $order, $error, $settings ) {
        $message = $error->get_error_message();
        $code    = $error->get_error_code();
        $data    = $error->get_error_data();

        $order->update_meta_data( self::META_IWS_LAST_ERROR, wp_json_encode( array(
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ) ) );
        $order->update_meta_data( self::META_IWS_PENDING_RETRY, current_time( 'mysql' ) );
        $order->save();

        $note = sprintf(
            __( 'PlaceOrder IWS fallido [%s]: %s', 'ventresslabs-intcomex' ),
            $code,
            $message
        );
        if ( ! empty( $data['reference'] ) ) {
            $note .= ' (Reference: ' . $data['reference'] . ')';
        }
        $order->add_order_note( $note );

        if ( 'yes' === $settings['auto_fail_order'] ) {
            $order->update_status( 'failed', __( 'PlaceOrder IWS falló.', 'ventresslabs-intcomex' ) );
        }
    }

    /**
     * Get the IWS OrderNumber linked to a WC order (if any).
     *
     * @since 1.1.0
     * @param int $order_id WC order ID.
     * @return string
     */
    public function get_order_number_for_wc( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return '';
        }
        return (string) $order->get_meta( self::META_IWS_ORDER_NUMBER, true );
    }

    /**
     * Get all WC orders that have the pending-retry flag.
     *
     * @since 1.1.0
     * @return array Array of WC_Order objects.
     */
    public function get_orders_pending_retry() {
        $query = new WC_Order_Query( array(
            'limit'        => 100,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'meta_key'     => self::META_IWS_PENDING_RETRY,
            'meta_compare' => 'EXISTS',
            'status'       => array( 'wc-failed', 'wc-pending', 'wc-on-hold', 'wc-processing' ),
        ) );
        return $query->get_orders();
    }
}
