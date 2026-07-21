<?php
/**
 * Logs view for the plugin (Sección 6 IWS - validación de logs TEST).
 *
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$logger  = new VentressLabs_Intcomex_Logger();
$entries = $logger->get_recent( 200 );
$nonce   = wp_create_nonce( 'ventresslabs_clear_logs_nonce' );
?>
<div class="wrap">
    <h2>Intcomex IWS — Logs</h2>
    <p><?php _e( 'Las últimas 200 llamadas a la API de Intcomex se listan a continuación. Estos logs permiten a TI Intcomex validar las pruebas en el ambiente TEST (Sección 6 de la guía).', 'ventresslabs-intcomex' ); ?></p>

    <p>
        <button type="button" class="button" id="ventresslabs-clear-logs" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <?php _e( 'Borrar logs', 'ventresslabs-intcomex' ); ?>
        </button>
    </p>

    <table class="widefat striped" id="intcomex-logs-table">
        <thead>
            <tr>
                <th style="width:160px;"><?php _e( 'Timestamp (UTC)', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:60px;"><?php _e( 'Ambiente', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:60px;"><?php _e( 'Method', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Endpoint', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:60px;"><?php _e( 'Status', 'ventresslabs-intcomex' ); ?></th>
                <th style="width:80px;"><?php _e( 'Elapsed', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Request', 'ventresslabs-intcomex' ); ?></th>
                <th><?php _e( 'Response / Error', 'ventresslabs-intcomex' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $entries ) ) : ?>
                <tr><td colspan="8"><?php _e( 'Aún no se registran llamadas.', 'ventresslabs-intcomex' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $entries as $entry ) : ?>
                    <?php
                    $status = isset( $entry['response_code'] ) ? $entry['response_code'] : '—';
                    $status_class = ( 200 === (int) $status ) ? 'status-ok' : 'status-err';
                    $ctx  = isset( $entry['wp_error'] ) ? $entry['wp_error'] : ( isset( $entry['response_body'] ) ? $entry['response_body'] : '' );
                    $req  = isset( $entry['request_body'] ) ? $entry['request_body'] : '';
                    ?>
                    <tr class="<?php echo esc_attr( $status_class ); ?>">
                        <td><code><?php echo esc_html( isset( $entry['timestamp_utc'] ) ? $entry['timestamp_utc'] : '' ); ?></code></td>
                        <td><?php echo esc_html( strtoupper( isset( $entry['environment'] ) ? $entry['environment'] : '' ) ); ?></td>
                        <td><?php echo esc_html( isset( $entry['method'] ) ? $entry['method'] : '' ); ?></td>
                        <td><code style="word-break:break-all;"><?php echo esc_html( isset( $entry['url'] ) ? $entry['url'] : '' ); ?></code></td>
                        <td><?php echo esc_html( $status ); ?></td>
                        <td><?php echo esc_html( isset( $entry['elapsed_ms'] ) ? $entry['elapsed_ms'] . ' ms' : '' ); ?></td>
                        <td><code style="word-break:break-all; max-width:300px; display:inline-block; white-space:pre-wrap;"><?php echo esc_html( $req ); ?></code></td>
                        <td><code style="word-break:break-all; max-width:400px; display:inline-block; white-space:pre-wrap;"><?php echo esc_html( $ctx ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <style>
        .status-ok td:first-child { border-left: 4px solid #46b450; }
        .status-err td:first-child { border-left: 4px solid #dc3232; }
    </style>
</div>
