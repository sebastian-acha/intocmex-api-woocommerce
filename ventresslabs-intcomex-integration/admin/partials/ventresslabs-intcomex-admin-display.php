<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://ventresslabs.com/
 * @since      1.0.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/admin/partials
 */
?>

<?php
$credentials = get_option( 'ventresslabs_intcomex_api_credentials', array() );
$environment  = isset( $credentials['environment'] ) ? $credentials['environment'] : 'test';
?>
<div class="wrap">
    <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

    <?php if ( 'test' === $environment ) : ?>
        <div class="notice notice-info">
            <p><?php _e( '<strong>Ambiente TEST activo.</strong> Las llamadas a IWS se harán al endpoint de prueba. No proceses órdenes reales en este ambiente (Sección 2 de la guía IWS).', 'ventresslabs-intcomex' ); ?></p>
        </div>
    <?php else : ?>
        <div class="notice notice-warning">
            <p><?php _e( '<strong>Ambiente PRODUCCIÓN activo.</strong> Asegúrate de haber completado el checklist de go-live (Sección 6 de la guía IWS) antes de sincronizar.', 'ventresslabs-intcomex' ); ?></p>
        </div>
    <?php endif; ?>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'ventresslabs_intcomex_options' );
        do_settings_sections( 'ventresslabs_intcomex_options' );
        submit_button( 'Save Settings' );
        ?>
    </form>
</div>
