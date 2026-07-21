<?php
/**
 * Provide a admin area view for the sync page
 *
 * @link       https://ventresslabs.com/
 * @since      1.0.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sync_service = new VentressLabs_Intcomex_Sync_Service();
$status       = $sync_service->get_sync_status();
$next_cron    = wp_next_scheduled( VentressLabs_Intcomex_Sync_Service::CRON_HOOK );

$ep_catalog   = VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_CATALOG );
$ep_price     = VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_PRICE_LIST );
$ep_inv       = VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_GET_INVENTORY );
$ep_extended  = VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_DOWNLOAD_EXTENDED );

function ventresslabs_format_at( $ts ) {
    if ( empty( $ts ) ) {
        return __( 'Nunca', 'ventresslabs-intcomex' );
    }
    return gmdate( 'Y-m-d H:i:s\Z', $ts ) . ' (' . human_time_diff( $ts, time() ) . ' atrás)';
}

function ventresslabs_render_status_row( $label, $row, $enabled ) {
    echo '<tr>';
    echo '<td><code>' . esc_html( $label ) . '</code> ';
    if ( ! $enabled ) {
        echo '<span class="dashicons dashicons-lock" style="color:#dc3232;" title="' . esc_attr__( 'Deshabilitado en Endpoints', 'ventresslabs-intcomex' ) . '"></span>';
    }
    echo '</td>';
    if ( $enabled ) {
        echo '<td>' . esc_html( $row['count'] ) . '</td>';
        echo '<td>' . esc_html( ventresslabs_format_at( $row['at'] ) ) . '</td>';
    } else {
        echo '<td colspan="2"><em>' . esc_html__( 'Deshabilitado en Endpoints', 'ventresslabs-intcomex' ) . '</em></td>';
    }
    echo '</tr>';
}
?>
<div class="wrap">
    <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

    <p>
        <?php _e( 'Sincroniza tus productos WooCommerce con el catálogo, lista de precios e inventario de Intcomex siguiendo el flujo de la Sección 5.1 de la guía IWS:', 'ventresslabs-intcomex' ); ?>
        <strong>GetCatalog → GetPriceList → GetInventory → WooCommerce</strong>.
    </p>

    <?php if ( ! $ep_catalog ) : ?>
        <div class="notice notice-error">
            <p><?php _e( '<strong>GetCatalog está deshabilitado.</strong> Actívalo en Intcomex → Endpoints para poder sincronizar.', 'ventresslabs-intcomex' ); ?></p>
        </div>
    <?php endif; ?>

    <h3><?php _e( 'Estado actual', 'ventresslabs-intcomex' ); ?></h3>
    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:260px;"><?php _e( 'API', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Productos cacheados', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Última sincronización', 'ventresslabs-intcomex' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php ventresslabs_render_status_row( 'GetCatalog', $status['catalog'], $ep_catalog ); ?>
            <?php ventresslabs_render_status_row( 'GetPriceList', $status['price_list'], $ep_price ); ?>
            <?php ventresslabs_render_status_row( 'GetInventory', $status['inventory'], $ep_inv ); ?>
            <?php ventresslabs_render_status_row( 'DownloadExtendedCatalog', $status['extended'], $ep_extended ); ?>
        </tbody>
    </table>

    <p>
        <?php _e( 'Próxima ejecución automática (wp-cron hourly):', 'ventresslabs-intcomex' ); ?>
        <strong><?php echo ( $ep_catalog && $next_cron ) ? esc_html( gmdate( 'Y-m-d H:i:s\Z', $next_cron ) ) : __( 'No programada', 'ventresslabs-intcomex' ); ?></strong>
        <?php if ( ! $ep_catalog ) : ?>
            <em>(<?php _e( 'cron pausado porque GetCatalog está deshabilitado', 'ventresslabs-intcomex' ); ?>)</em>
        <?php endif; ?>
    </p>
    <p>
        <?php _e( 'Próxima sincronización de imágenes (wp-cron monthly):', 'ventresslabs-intcomex' ); ?>
        <?php $next_ext = wp_next_scheduled( VentressLabs_Intcomex_Sync_Service::CRON_HOOK_EXTENDED ); ?>
        <strong><?php echo ( $ep_extended && $next_ext ) ? esc_html( gmdate( 'Y-m-d H:i:s\Z', $next_ext ) ) : __( 'No programada', 'ventresslabs-intcomex' ); ?></strong>
        <?php if ( ! $ep_extended ) : ?>
            <em>(<?php _e( 'cron pausado porque DownloadExtendedCatalog está deshabilitado', 'ventresslabs-intcomex' ); ?>)</em>
        <?php endif; ?>
    </p>

    <h3><?php _e( 'Sincronización manual', 'ventresslabs-intcomex' ); ?></h3>
    <p class="description">
        <?php _e( 'La guía IWS limita la sincronización a máximo 1 vez por hora. El botón respeta este límite automáticamente.', 'ventresslabs-intcomex' ); ?>
    </p>
    <button id="ventresslabs-sync-now" class="button button-primary" <?php disabled( ! $ep_catalog ); ?>>
        <?php _e( 'Sincronizar ahora', 'ventresslabs-intcomex' ); ?>
    </button>
    <?php if ( ! $ep_catalog ) : ?>
        <em>(<?php _e( 'requiere que GetCatalog esté habilitado', 'ventresslabs-intcomex' ); ?>)</em>
    <?php endif; ?>

    <h3><?php _e( 'Catálogo extendido (imágenes)', 'ventresslabs-intcomex' ); ?></h3>
    <p class="description">
        <?php _e( 'La guía IWS limita DownloadExtendedCatalog a máximo 1 vez por mes. El botón respeta este límite automáticamente (se puede forzar manualmente).', 'ventresslabs-intcomex' ); ?>
    </p>
    <button id="ventresslabs-sync-extended" class="button" <?php disabled( ! $ep_extended ); ?>>
        <?php _e( 'Descargar catálogo extendido', 'ventresslabs-intcomex' ); ?>
    </button>
    <label style="margin-left:10px;">
        <input type="checkbox" id="ventresslabs-sync-extended-force" <?php disabled( ! $ep_extended ); ?>> <?php _e( 'Forzar (ignorar límite mensual)', 'ventresslabs-intcomex' ); ?>
    </label>
    <?php if ( ! $ep_extended ) : ?>
        <em>(<?php _e( 'requiere que DownloadExtendedCatalog esté habilitado', 'ventresslabs-intcomex' ); ?>)</em>
    <?php endif; ?>

    <div id="sync-log" style="margin-top: 20px; border: 1px solid #ccc; padding: 10px; max-height: 400px; overflow-y: auto; background: #fafafa; display: none;">
        <p><strong><?php _e( 'Synchronization Log:', 'ventresslabs-intcomex' ); ?></strong></p>
        <div id="sync-log-content"></div>
    </div>
</div>
