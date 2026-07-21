<?php
/**
 * Orders admin page: WC orders linked to IWS PlaceOrder results.
 *
 * Provides filters by IWS status (placed, pending retry, no order),
 * bulk retry for pending orders, and a detailed view of the last
 * PlaceOrder response/error per order.
 *
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$order_service   = new VentressLabs_Intcomex_Order_Service();
$settings        = $order_service->get_settings();
$allow_retry     = 'yes' === $settings['allow_retry']
    && VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_PLACE_ORDER );

$filter = isset( $_GET['iws_filter'] ) ? sanitize_key( $_GET['iws_filter'] ) : 'all';
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page = 25;

$query_args = array(
    'limit'    => $per_page,
    'paged'    => $paged,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'status'   => array( 'wc-failed', 'wc-pending', 'wc-on-hold', 'wc-processing', 'wc-completed' ),
);

if ( 'pending' === $filter ) {
    $query_args['meta_key']     = VentressLabs_Intcomex_Order_Service::META_IWS_PENDING_RETRY;
    $query_args['meta_compare'] = 'EXISTS';
} elseif ( 'placed' === $filter ) {
    $query_args['meta_key']     = VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER;
    $query_args['meta_compare'] = 'EXISTS';
} elseif ( 'missing' === $filter ) {
    // Orders without OrderNumber and without pending retry flag.
    $query_args['meta_query'] = array(
        'relation' => 'AND',
        array(
            'key'     => VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER,
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => VentressLabs_Intcomex_Order_Service::META_IWS_PENDING_RETRY,
            'compare' => 'NOT EXISTS',
        ),
    );
}

$query  = new WC_Order_Query( $query_args );
$orders = $query->get_orders();
$total  = $query->get_total();
$pages  = max( 1, (int) ceil( $total / $per_page ) );

$admin_url = admin_url( 'admin.php?page=ventresslabs-intcomex-orders' );
?>
<div class="wrap">
    <h2>Intcomex IWS — Órdenes</h2>

    <p><?php _e( 'Listado de pedidos de WooCommerce y su estado en Intcomex (PlaceOrder).', 'ventresslabs-intcomex' ); ?></p>

    <?php if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_PLACE_ORDER ) ) : ?>
        <div class="notice notice-warning">
            <p><?php _e( 'El endpoint <strong>PlaceOrder</strong> está deshabilitado en Intcomex → Endpoints. Los reintentos están ocultos y las nuevas órdenes no se enviarán a IWS hasta que se reactive.', 'ventresslabs-intcomex' ); ?></p>
        </div>
    <?php endif; ?>

    <ul class="subsubsub">
        <li><a href="<?php echo esc_url( add_query_arg( 'iws_filter', 'all', $admin_url ) ); ?>" class="<?php echo 'all' === $filter ? 'current' : ''; ?>"><?php _e( 'Todas', 'ventresslabs-intcomex' ); ?></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'iws_filter', 'placed', $admin_url ) ); ?>" class="<?php echo 'placed' === $filter ? 'current' : ''; ?>"><?php _e( 'Con orden IWS', 'ventresslabs-intcomex' ); ?></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'iws_filter', 'pending', $admin_url ) ); ?>" class="<?php echo 'pending' === $filter ? 'current' : ''; ?>"><?php _e( 'Pendientes de reintento', 'ventresslabs-intcomex' ); ?></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'iws_filter', 'missing', $admin_url ) ); ?>" class="<?php echo 'missing' === $filter ? 'current' : ''; ?>"><?php _e( 'Sin orden IWS', 'ventresslabs-intcomex' ); ?></a></li>
    </ul>

    <?php if ( $allow_retry && 'pending' === $filter && ! empty( $orders ) ) : ?>
        <div style="margin: 10px 0;">
            <button type="button" class="button button-primary" id="ventresslabs-retry-bulk"><?php _e( 'Reintentar todas las pendientes', 'ventresslabs-intcomex' ); ?></button>
            <span id="ventresslabs-retry-bulk-status" style="margin-left:10px;"></span>
        </div>
    <?php endif; ?>

    <table class="widefat striped" id="intcomex-orders-table">
        <thead>
            <tr>
                <th style="width:80px;"><?php _e( 'Pedido WC', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:160px;"><?php _e( 'Fecha', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:120px;"><?php _e( 'Estado WC', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:120px;"><?php _e( 'Total', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:120px;"><?php _e( 'OrderNumber IWS', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Estado IWS', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Último error', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:140px;"><?php _e( 'Acciones', 'ventresslabs-intcomex' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $orders ) ) : ?>
                <tr><td colspan="8"><?php _e( 'No hay pedidos en este filtro.', 'ventresslabs-intcomex' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $orders as $order ) :
                    $order_id   = $order->get_id();
                    $iws_number = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER, true );
                    $pending    = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_PENDING_RETRY, true );
                    $last_err   = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_LAST_ERROR, true );
                    $err_decoded = $last_err ? json_decode( $last_err, true ) : null;
                ?>
                    <tr data-order-id="<?php echo esc_attr( $order_id ); ?>">
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $order_id ); ?></a></td>
                        <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i' ) : '' ); ?></td>
                        <td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
                        <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                        <td>
                            <?php if ( ! empty( $iws_number ) ) : ?>
                                <strong style="color:#46b450;">#<?php echo esc_html( $iws_number ); ?></strong>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $iws_number ) ) : ?>
                                <span style="color:#46b450;"><?php _e( 'Creada', 'ventresslabs-intcomex' ); ?></span>
                            <?php elseif ( ! empty( $pending ) ) : ?>
                                <span style="color:#dc3232;"><?php _e( 'Pendiente', 'ventresslabs-intcomex' ); ?></span>
                            <?php else : ?>
                                <span style="color:#999;"><?php _e( 'No enviada', 'ventresslabs-intcomex' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $err_decoded ) ) : ?>
                                <code style="word-break:break-all; max-width:300px; display:inline-block; white-space:pre-wrap;" title="<?php echo esc_attr( $err_decoded['data']['reference'] ?? '' ); ?>">
                                    [<?php echo esc_html( $err_decoded['code'] ?? '?' ); ?>] <?php echo esc_html( $err_decoded['message'] ?? '' ); ?>
                                </code>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $allow_retry && empty( $iws_number ) ) : ?>
                                <button type="button" class="button button-small ventresslabs-retry-order" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php _e( 'Reintentar', 'ventresslabs-intcomex' ); ?></button>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $pages > 1 ) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $pages,
                    'current'   => $paged,
                ) );
                ?>
            </div>
        </div>
    <?php endif; ?>

    <p class="description">
        <?php _e( 'Puedes configurar el comportamiento de PlaceOrder en Intcomex → Settings.', 'ventresslabs-intcomex' ); ?>
    </p>
</div>
