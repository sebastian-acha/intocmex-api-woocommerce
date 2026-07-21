<?php
/**
 * Endpoints management page: feature toggles per IWS endpoint.
 *
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$all      = VentressLabs_Intcomex_Endpoint_Manager::get_all();
$warnings = array();
foreach ( $all as $id => $data ) {
    $w = VentressLabs_Intcomex_Endpoint_Manager::dependency_warnings( $id );
    if ( ! empty( $w ) && ! empty( $data['enabled'] ) ) {
        $dep_labels = array();
        foreach ( $w as $dep_id ) {
            $dep_meta = VentressLabs_Intcomex_Endpoint_Manager::get_metadata( $dep_id );
            $dep_labels[] = '<code>' . ( isset( $dep_meta['label'] ) ? $dep_meta['label'] : $dep_id ) . '</code>';
        }
        $warnings[] = $data['label'] . ' ' . sprintf(
            __( 'depende de %s (actualmente deshabilitado).', 'ventresslabs-intcomex' ),
            implode( ', ', $dep_labels )
        );
    }
}
?>
<div class="wrap">
    <h2>Intcomex IWS — Endpoints</h2>

    <p><?php _e( 'Activa o desactiva cada endpoint de Intcomex. Los endpoints desactivados no harán llamadas a la API. La guía IWS requiere que <strong>GetCatalog</strong> esté siempre activo (se mantiene obligatorio).', 'ventresslabs-intcomex' ); ?></p>

    <?php if ( ! empty( $warnings ) ) : ?>
        <div class="notice notice-warning">
            <?php foreach ( $warnings as $w ) : ?>
                <p>⚠ <?php echo $w; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'ventresslabs_intcomex_endpoints_group' );
        do_settings_sections( 'ventresslabs_intcomex_endpoints' );
        submit_button( __( 'Guardar endpoints', 'ventresslabs-intcomex' ) );
        ?>
    </form>
</div>
