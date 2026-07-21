<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://ventresslabs.com/
 * @since      1.0.0
 *
 * @package    VentressLabs_Intcomex
 * @subpackage VentressLabs_Intcomex/admin
 */
class VentressLabs_Intcomex_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name     The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'VentressLabs Intcomex Integration',
            'Intcomex',
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_setup_page' ),
            'dashicons-cloud',
            56
        );

        add_submenu_page(
            $this->plugin_name,
            'Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array( $this, 'display_plugin_setup_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            'Synchronization',
            'Synchronization',
            'manage_options',
            $this->plugin_name . '-sync',
            array( $this, 'display_sync_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            'Logs',
            'Logs',
            'manage_options',
            $this->plugin_name . '-logs',
            array( $this, 'display_logs_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            'Órdenes',
            'Órdenes',
            'manage_options',
            $this->plugin_name . '-orders',
            array( $this, 'display_orders_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            'Endpoints',
            'Endpoints',
            'manage_options',
            $this->plugin_name . '-endpoints',
            array( $this, 'display_endpoints_page' )
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        include_once( 'partials/ventresslabs-intcomex-admin-display.php' );
    }

    /**
     * Render the sync page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_sync_page() {
        include_once( 'partials/ventresslabs-intcomex-sync-display.php' );
    }

    /**
     * Render the logs page for this plugin.
     *
     * @since 1.1.0
     */
    public function display_logs_page() {
        include_once( 'partials/ventresslabs-intcomex-logs-display.php' );
    }

    /**
     * Render the orders page for this plugin.
     *
     * @since 1.1.0
     */
    public function display_orders_page() {
        include_once( 'partials/ventresslabs-intcomex-orders-display.php' );
    }

    /**
     * Render the endpoints page for this plugin.
     *
     * @since 2.0.0
     */
    public function display_endpoints_page() {
        include_once( 'partials/ventresslabs-intcomex-endpoints-display.php' );
    }

    /**
     * Register the settings for this plugin.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_api_credentials',
            array( $this, 'sanitize_credentials' )
        );

        add_settings_section(
            'ventresslabs_intcomex_api_section',
            __( 'Environment & API Credentials', 'ventresslabs-intcomex' ),
            array( $this, 'render_api_section_callback' ),
            'ventresslabs_intcomex_options'
        );

        add_settings_field(
            'ventresslabs_intcomex_environment',
            __( 'Active Environment', 'ventresslabs-intcomex' ),
            array( $this, 'render_environment_field_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_api_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_api_key_test',
            __( 'TEST API Key', 'ventresslabs-intcomex' ),
            array( $this, 'render_api_key_field_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_api_section',
            array( 'env' => 'test' )
        );

        add_settings_field(
            'ventresslabs_intcomex_access_key_test',
            __( 'TEST Access Key', 'ventresslabs-intcomex' ),
            array( $this, 'render_access_key_field_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_api_section',
            array( 'env' => 'test' )
        );

        add_settings_field(
            'ventresslabs_intcomex_api_key_prod',
            __( 'Production API Key', 'ventresslabs-intcomex' ),
            array( $this, 'render_api_key_field_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_api_section',
            array( 'env' => 'prod' )
        );

        add_settings_field(
            'ventresslabs_intcomex_access_key_prod',
            __( 'Production Access Key', 'ventresslabs-intcomex' ),
            array( $this, 'render_access_key_field_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_api_section',
            array( 'env' => 'prod' )
        );

        register_setting( 'ventresslabs_intcomex_options', 'ventresslabs_intcomex_selected_categories' );

        add_settings_section(
            'ventresslabs_intcomex_categories_section',
            __( 'Product Categories to Sync', 'ventresslabs-intcomex' ),
            array( $this, 'render_categories_section_callback' ),
            'ventresslabs_intcomex_options'
        );

        add_settings_field(
            'ventresslabs_intcomex_categories_list',
            __( 'Categories', 'ventresslabs-intcomex' ),
            array( $this, 'render_categories_field_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_categories_section'
        );

        // Stock Validator settings (Sección 5.2 paso 4 IWS guide).
        register_setting( 'ventresslabs_intcomex_options', 'ventresslabs_intcomex_stock_validator', array( $this, 'sanitize_stock_validator' ) );

        add_settings_section(
            'ventresslabs_intcomex_stock_validator_section',
            __( 'Validación de stock en tiempo real (checkout)', 'ventresslabs-intcomex' ),
            array( $this, 'render_stock_validator_section_callback' ),
            'ventresslabs_intcomex_options'
        );

        add_settings_field(
            'ventresslabs_intcomex_stock_validator_fail_open',
            __( 'Modo fail-open', 'ventresslabs-intcomex' ),
            array( $this, 'render_stock_validator_fail_open_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_stock_validator_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_stock_validator_block_on_zero',
            __( 'Bloquear si stock=0', 'ventresslabs-intcomex' ),
            array( $this, 'render_stock_validator_block_on_zero_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_stock_validator_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_stock_validator_cache_ttl',
            __( 'Caché (segundos)', 'ventresslabs-intcomex' ),
            array( $this, 'render_stock_validator_cache_ttl_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_stock_validator_section'
        );

        // PlaceOrder settings (Sección 5.2 paso 5 IWS guide).
        $this->register_order_settings();

        // Endpoint toggles (Sección 4 IWS guide), since 2.0.0.
        $this->register_endpoint_settings();
    }

    /**
     * Register endpoint toggles settings group (Sección 4 IWS guide).
     *
     * @since 2.0.0
     */
    private function register_endpoint_settings() {
        register_setting(
            'ventresslabs_intcomex_endpoints_group',
            'ventresslabs_intcomex_endpoints',
            array( 'VentressLabs_Intcomex_Endpoint_Manager', 'sanitize' )
        );

        add_settings_section(
            'ventresslabs_intcomex_endpoints_section',
            __( 'Activación de endpoints', 'ventresslabs-intcomex' ),
            array( $this, 'render_endpoints_section_callback' ),
            'ventresslabs_intcomex_endpoints'
        );

        add_settings_field(
            'ventresslabs_intcomex_endpoints_toggles',
            __( 'Endpoints', 'ventresslabs-intcomex' ),
            array( $this, 'render_endpoints_toggles_callback' ),
            'ventresslabs_intcomex_endpoints',
            'ventresslabs_intcomex_endpoints_section'
        );
    }

    public function render_endpoints_section_callback() {
        echo __( 'Habilita o deshabilita cada endpoint IWS. Cuando un endpoint está desactivado, ninguna llamada se realizará a la API correspondiente y los hooks asociados se saltan.', 'ventresslabs-intcomex' );
    }

    public function render_endpoints_toggles_callback() {
        $all = VentressLabs_Intcomex_Endpoint_Manager::get_all();
        ?>
        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th style="width:60px;"><?php _e( 'Activar', 'ventresslabs-intcomex' ); ?></th>
                    <th style="width:200px;"><?php _e( 'Endpoint', 'ventresslabs-intcomex' ); ?></th>
                    <th><?php _e( 'Descripción', 'ventresslabs-intcomex' ); ?></th>
                    <th style="width:140px;"><?php _e( 'Depende de', 'ventresslabs-intcomex' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $all as $id => $data ) : 
                    $required = ! empty( $data['required'] );
                ?>
                    <tr>
                        <td style="text-align:center;">
                            <?php if ( $required ) : ?>
                                <input type="hidden" name="ventresslabs_intcomex_endpoints[<?php echo esc_attr( $id ); ?>][enabled]" value="1" />
                            <?php endif; ?>
                            <label class="switch">
                                <input type="checkbox"
                                    name="ventresslabs_intcomex_endpoints[<?php echo esc_attr( $id ); ?>][enabled]"
                                    value="1"
                                    <?php checked( $data['enabled'], true ); ?>
                                    <?php disabled( $required, true ); ?>
                                />
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td>
                            <strong><code><?php echo esc_html( $data['label'] ); ?></code></strong>
                            <?php if ( $required ) : ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Obligatorio', 'ventresslabs-intcomex' ); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $data['description'] ); ?></td>
                        <td>
                            <?php 
                            if ( empty( $data['depends'] ) ) {
                                echo '—';
                            } else {
                                $dep_labels = array();
                                foreach ( $data['depends'] as $dep ) {
                                    $meta = VentressLabs_Intcomex_Endpoint_Manager::get_metadata( $dep );
                                    $dep_labels[] = '<code>' . ( $meta['label'] ?? $dep ) . '</code>';
                                }
                                echo implode( ', ', $dep_labels );
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <style>
            .switch { position: relative; display: inline-block; width: 36px; height: 18px; }
            .switch input { opacity: 0; width: 0; height: 0; }
            .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .2s; border-radius: 18px; }
            .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 2px; bottom: 2px; background-color: white; transition: .2s; border-radius: 50%; }
            input:checked + .slider { background-color: #46b450; }
            input:checked + .slider:before { transform: translateX(18px); }
            input:disabled + .slider { background-color: #46b450; opacity: 0.7; cursor: not-allowed; }
        </style>
        <?php
    }

    /**
     * Sanitize the stock validator settings.
     *
     * @since 1.1.0
     */
    public function sanitize_stock_validator( $input ) {
        $current = get_option( 'ventresslabs_intcomex_stock_validator', array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        return array_merge( $current, array(
            'fail_open'     => isset( $input['fail_open'] ) && 'yes' === $input['fail_open'] ? 'yes' : 'no',
            'block_on_zero' => isset( $input['block_on_zero'] ) && 'yes' === $input['block_on_zero'] ? 'yes' : 'no',
            'cache_ttl'     => isset( $input['cache_ttl'] ) ? max( 0, min( 3600, (int) $input['cache_ttl'] ) ) : 60,
        ) );
    }

    public function render_stock_validator_section_callback() {
        echo __( 'Valida el stock del carrito contra Intcomex en tiempo real usando GetProducts antes de permitir el pago (Sección 5.2 paso 4 de la guía IWS).', 'ventresslabs-intcomex' );
    }

    public function render_stock_validator_fail_open_callback() {
        $settings = get_option( 'ventresslabs_intcomex_stock_validator', array() );
        $value    = isset( $settings['fail_open'] ) ? $settings['fail_open'] : 'no';
        ?>
        <label><input type="checkbox" name="ventresslabs_intcomex_stock_validator[fail_open]" value="yes" <?php checked( $value, 'yes' ); ?>> <?php _e( 'Permitir checkout si la API falla (recomendado: NO)', 'ventresslabs-intcomex' ); ?></label>
        <?php
    }

    public function render_stock_validator_block_on_zero_callback() {
        $settings = get_option( 'ventresslabs_intcomex_stock_validator', array() );
        $value    = isset( $settings['block_on_zero'] ) ? $settings['block_on_zero'] : 'yes';
        ?>
        <label><input type="checkbox" name="ventresslabs_intcomex_stock_validator[block_on_zero]" value="yes" <?php checked( $value, 'yes' ); ?>> <?php _e( 'Bloquear checkout si el producto está agotado', 'ventresslabs-intcomex' ); ?></label>
        <?php
    }

    public function render_stock_validator_cache_ttl_callback() {
        $settings = get_option( 'ventresslabs_intcomex_stock_validator', array() );
        $value    = isset( $settings['cache_ttl'] ) ? $settings['cache_ttl'] : 60;
        ?>
        <input type="number" min="0" max="3600" name="ventresslabs_intcomex_stock_validator[cache_ttl]" value="<?php echo esc_attr( $value ); ?>" class="small-text"> <?php _e( 'segundos (0 deshabilita caché)', 'ventresslabs-intcomex' ); ?>
        <?php
    }

    /**
     * Register PlaceOrder settings (Sección 5.2 paso 5).
     *
     * @since 1.1.0
     */
    private function register_order_settings() {
        register_setting( 'ventresslabs_intcomex_options', 'ventresslabs_intcomex_order_settings', array( $this, 'sanitize_order_settings' ) );

        add_settings_section(
            'ventresslabs_intcomex_order_section',
            __( 'Envío de órdenes a IWS (PlaceOrder)', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_section_callback' ),
            'ventresslabs_intcomex_options'
        );

        add_settings_field(
            'ventresslabs_intcomex_order_send_customer_info',
            __( 'Enviar datos del cliente', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_send_customer_info_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_order_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_order_auto_fail',
            __( 'Marcar orden WC fallida si IWS falla', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_auto_fail_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_order_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_order_allow_retry',
            __( 'Permitir reintentos manuales', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_allow_retry_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_order_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_order_default_locale',
            __( 'Locale por defecto', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_default_locale_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_order_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_order_store_id',
            __( 'StoreId (opcional)', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_store_id_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_order_section'
        );

        add_settings_field(
            'ventresslabs_intcomex_order_tag',
            __( 'Tag (opcional)', 'ventresslabs-intcomex' ),
            array( $this, 'render_order_tag_callback' ),
            'ventresslabs_intcomex_options',
            'ventresslabs_intcomex_order_section'
        );
    }

    /**
     * Sanitize PlaceOrder settings.
     *
     * @since 1.1.0
     */
    public function sanitize_order_settings( $input ) {
        $current = get_option( 'ventresslabs_intcomex_order_settings', array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        return array_merge( $current, array(
            'send_customer_info' => isset( $input['send_customer_info'] ) && 'yes' === $input['send_customer_info'] ? 'yes' : 'no',
            'auto_fail_order'    => isset( $input['auto_fail_order'] ) && 'yes' === $input['auto_fail_order'] ? 'yes' : 'no',
            'allow_retry'        => isset( $input['allow_retry'] ) && 'yes' === $input['allow_retry'] ? 'yes' : 'no',
            'default_locale'     => isset( $input['default_locale'] ) && in_array( $input['default_locale'], array( 'en', 'es' ), true ) ? $input['default_locale'] : 'es',
            'store_id'           => isset( $input['store_id'] ) ? sanitize_text_field( $input['store_id'] ) : '',
            'tag'                => isset( $input['tag'] ) ? sanitize_text_field( $input['tag'] ) : '',
        ) );
    }

    public function render_order_section_callback() {
        echo __( 'Configura el comportamiento del PlaceOrder en IWS al completar una orden de WooCommerce (Sección 5.2 paso 5 de la guía IWS). Habilita o deshabilita PlaceOrder en Intcomex → Endpoints.', 'ventresslabs-intcomex' );
    }

    public function render_order_send_customer_info_callback() {
        $settings = get_option( 'ventresslabs_intcomex_order_settings', array() );
        $value    = isset( $settings['send_customer_info'] ) ? $settings['send_customer_info'] : 'yes';
        ?>
        <label><input type="checkbox" name="ventresslabs_intcomex_order_settings[send_customer_info]" value="yes" <?php checked( $value, 'yes' ); ?>> <?php _e( 'Incluir Customer, Billing y Shipping en el payload', 'ventresslabs-intcomex' ); ?></label>
        <?php
    }

    public function render_order_auto_fail_callback() {
        $settings = get_option( 'ventresslabs_intcomex_order_settings', array() );
        $value    = isset( $settings['auto_fail_order'] ) ? $settings['auto_fail_order'] : 'yes';
        ?>
        <label><input type="checkbox" name="ventresslabs_intcomex_order_settings[auto_fail_order]" value="yes" <?php checked( $value, 'yes' ); ?>> <?php _e( 'Marcar WC order como "Failed" si PlaceOrder falla', 'ventresslabs-intcomex' ); ?></label>
        <?php
    }

    public function render_order_allow_retry_callback() {
        $settings = get_option( 'ventresslabs_intcomex_order_settings', array() );
        $value    = isset( $settings['allow_retry'] ) ? $settings['allow_retry'] : 'yes';
        ?>
        <label><input type="checkbox" name="ventresslabs_intcomex_order_settings[allow_retry]" value="yes" <?php checked( $value, 'yes' ); ?>> <?php _e( 'Habilitar botón "Reintentar" en el listado de pedidos', 'ventresslabs-intcomex' ); ?></label>
        <?php
    }

    public function render_order_default_locale_callback() {
        $settings = get_option( 'ventresslabs_intcomex_order_settings', array() );
        $value    = isset( $settings['default_locale'] ) ? $settings['default_locale'] : 'es';
        ?>
        <select name="ventresslabs_intcomex_order_settings[default_locale]">
            <option value="es" <?php selected( $value, 'es' ); ?>>Español (es)</option>
            <option value="en" <?php selected( $value, 'en' ); ?>>English (en)</option>
        </select>
        <?php
    }

    public function render_order_store_id_callback() {
        $settings = get_option( 'ventresslabs_intcomex_order_settings', array() );
        $value    = isset( $settings['store_id'] ) ? $settings['store_id'] : '';
        ?>
        <input type="text" name="ventresslabs_intcomex_order_settings[store_id]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <?php
    }

    public function render_order_tag_callback() {
        $settings = get_option( 'ventresslabs_intcomex_order_settings', array() );
        $value    = isset( $settings['tag'] ) ? $settings['tag'] : '';
        ?>
        <input type="text" name="ventresslabs_intcomex_order_settings[tag]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <?php
    }

    /**
     * Sanitize the credentials option.
     *
     * Migrates the legacy format (api_key / access_key) to the new
     * per-environment format when an upgrade is detected.
     *
     * @since 1.1.0
     */
    public function sanitize_credentials( $input ) {
        $current = get_option( 'ventresslabs_intcomex_api_credentials', array() );
        if ( ! is_array( $current ) ) {
            $current = array();
        }
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        // Migration from legacy format (< 1.1.0).
        if ( ! empty( $input['api_key'] ) && empty( $input['api_key_test'] ) && empty( $input['api_key_prod'] ) ) {
            $input['api_key_test'] = $input['api_key'];
            unset( $input['api_key'] );
        }
        if ( ! empty( $input['access_key'] ) && empty( $input['access_key_test'] ) && empty( $input['access_key_prod'] ) ) {
            $input['access_key_test'] = $input['access_key'];
            unset( $input['access_key'] );
        }

        $sanitized = array_merge( $current, array(
            'environment'       => isset( $input['environment'] ) && 'prod' === $input['environment'] ? 'prod' : 'test',
            'api_key_test'      => isset( $input['api_key_test'] ) ? sanitize_text_field( $input['api_key_test'] ) : '',
            'access_key_test'   => isset( $input['access_key_test'] ) ? sanitize_text_field( $input['access_key_test'] ) : '',
            'api_key_prod'      => isset( $input['api_key_prod'] ) ? sanitize_text_field( $input['api_key_prod'] ) : '',
            'access_key_prod'   => isset( $input['access_key_prod'] ) ? sanitize_text_field( $input['access_key_prod'] ) : '',
        ) );

        return $sanitized;
    }

    public function render_api_section_callback() {
        echo __( 'Configure the environment and Intcomex API credentials. TEST must be used before Production (see IWS guide section 2).', 'ventresslabs-intcomex' );
    }

    public function render_categories_section_callback() {
        echo __( 'Select the product categories you want to synchronize with your WooCommerce store.', 'ventresslabs-intcomex' );
    }

    public function render_environment_field_callback() {
        $options       = get_option( 'ventresslabs_intcomex_api_credentials', array() );
        $environment   = isset( $options['environment'] ) ? $options['environment'] : 'test';
        $prod_selected = ( 'prod' === $environment ) ? 'checked' : '';
        $test_selected = ( 'prod' !== $environment ) ? 'checked' : '';
        ?>
        <label><input type="radio" name="ventresslabs_intcomex_api_credentials[environment]" value="test" <?php echo $test_selected; ?>> TEST (recomendado para integración)</label><br>
        <label><input type="radio" name="ventresslabs_intcomex_api_credentials[environment]" value="prod" <?php echo $prod_selected; ?>> Production</label>
        <p class="description"><?php _e( 'IWS requiere iniciar la integración en TEST antes de pasar a Producción.', 'ventresslabs-intcomex' ); ?></p>
        <?php
    }

    public function render_api_key_field_callback( $args = array() ) {
        $env     = isset( $args['env'] ) ? $args['env'] : 'test';
        $key     = 'test' === $env ? 'api_key_test' : 'api_key_prod';
        $options = get_option( 'ventresslabs_intcomex_api_credentials', array() );
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
        ?>
        <input type='text' name='ventresslabs_intcomex_api_credentials[<?php echo esc_attr( $key ); ?>]' value='<?php echo esc_attr( $value ); ?>' class='regular-text'>
        <?php
    }

    public function render_access_key_field_callback( $args = array() ) {
        $env     = isset( $args['env'] ) ? $args['env'] : 'test';
        $key     = 'test' === $env ? 'access_key_test' : 'access_key_prod';
        $options = get_option( 'ventresslabs_intcomex_api_credentials', array() );
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : '';
        ?>
        <input type='password' name='ventresslabs_intcomex_api_credentials[<?php echo esc_attr( $key ); ?>]' value='<?php echo esc_attr( $value ); ?>' class='regular-text'>
        <?php
    }

    public function enqueue_scripts($hook) {
        // Load on our plugin's pages, and on the WC orders list so the retry
        // button and IWS column work there too.
        $load = ( strpos($hook, 'ventresslabs-intcomex') !== false )
            || ( 'edit.php' === $hook && isset( $_GET['post_type'] ) && 'shop_order' === $_GET['post_type'] );

        if ( ! $load ) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/ventresslabs-intcomex-admin.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'vl_intcomex_admin',
            array(
                'ajax_url'              => admin_url( 'admin-ajax.php' ),
                'fetch_nonce'           => wp_create_nonce( 'ventresslabs_fetch_categories_nonce' ),
                'sync_nonce'            => wp_create_nonce( 'ventresslabs_sync_nonce' ),
                'clear_logs_nonce'      => wp_create_nonce( 'ventresslabs_clear_logs_nonce' ),
                'sync_extended_nonce'   => wp_create_nonce( 'ventresslabs_sync_extended_nonce' ),
                'retry_order_nonce'     => wp_create_nonce( 'ventresslabs_retry_order_nonce' ),
                'i18n_retrying'         => __( 'Reintentando…', 'ventresslabs-intcomex' ),
                'i18n_retry_confirm'    => __( '¿Reintentar PlaceOrder en IWS para este pedido?', 'ventresslabs-intcomex' ),
                'i18n_bulk_confirm'     => __( '¿Reintentar todas las órdenes pendientes?', 'ventresslabs-intcomex' ),
            )
        );
    }

    /**
     * Add the IWS column to the WC orders table.
     *
     * @since 1.1.0
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_orders_column_iws( $columns ) {
        $columns['intcomex_iws'] = __( 'IWS', 'ventresslabs-intcomex' );
        return $columns;
    }

    /**
     * Render the IWS column for a given order row.
     *
     * @since 1.1.0
     * @param string $column Column key.
     * @param int    $post_id Order post ID.
     */
    public function render_orders_column_iws( $column, $post_id ) {
        if ( 'intcomex_iws' !== $column ) {
            return;
        }
        $order = wc_get_order( $post_id );
        if ( ! $order ) {
            return;
        }
        $order_number = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER, true );
        $pending      = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_PENDING_RETRY, true );

        if ( ! empty( $order_number ) ) {
            echo '<strong style="color:#46b450;">#' . esc_html( $order_number ) . '</strong>';
        } elseif ( ! empty( $pending ) ) {
            if ( VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_PLACE_ORDER ) ) {
                echo '<span style="color:#dc3232;" title="' . esc_attr( $pending ) . '">' . __( 'Pendiente', 'ventresslabs-intcomex' ) . '</span>';
                echo ' <button type="button" class="button button-small ventresslabs-retry-order" data-order-id="' . esc_attr( $post_id ) . '">' . __( 'Reintentar', 'ventresslabs-intcomex' ) . '</button>';
            } else {
                echo '<span style="color:#dc3232;" title="' . esc_attr( $pending ) . '">' . __( 'Pendiente (PlaceOrder deshabilitado)', 'ventresslabs-intcomex' ) . '</span>';
            }
        } else {
            echo '—';
        }
    }

    /**
     * AJAX handler: manually retry PlaceOrder for a WC order.
     *
     * @since 1.1.0
     */
    public function ajax_retry_order() {
        check_ajax_referer( 'ventresslabs_retry_order_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'ventresslabs-intcomex' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'ID de orden no válido.', 'ventresslabs-intcomex' ) ) );
        }

        // Clear the OrderNumber meta so handle_order_created doesn't early-return,
        // but only if it's empty or the user is explicitly retrying (allowed).
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Orden WC no encontrada.', 'ventresslabs-intcomex' ) ) );
        }
        $existing = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER, true );
        if ( ! empty( $existing ) ) {
            wp_send_json_error( array(
                'message' => sprintf( __( 'La orden ya tiene OrderNumber IWS #%s.', 'ventresslabs-intcomex' ), $existing ),
            ) );
        }

        $order_service = new VentressLabs_Intcomex_Order_Service();
        $response      = $order_service->handle_order_created( $order_id );

        if ( is_wp_error( $response ) ) {
            $existing_check = wc_get_order( $order_id );
            $new_order_number = $existing_check ? $existing_check->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER, true ) : '';
            if ( ! empty( $new_order_number ) ) {
                // Error entre bambalinas pero finalmente quedó creada — exito.
                wp_send_json_success( array(
                    'message'      => sprintf( __( 'PlaceOrder exitoso: #%s.', 'ventresslabs-intcomex' ), $new_order_number ),
                    'order_number' => $new_order_number,
                ) );
            }
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $new_order_number = isset( $response['OrderNumber'] ) ? (string) $response['OrderNumber'] : '';
        wp_send_json_success( array(
            'message'      => sprintf( __( 'PlaceOrder exitoso: #%s.', 'ventresslabs-intcomex' ), $new_order_number ),
            'order_number' => $new_order_number,
        ) );
    }

    /**
     * AJAX handler: bulk retry of PlaceOrder for all pending WC orders.
     *
     * Iterates over orders flagged with META_IWS_PENDING_RETRY and calls
     * handle_order_created for each one. Returns a per-order summary.
     *
     * @since 1.1.0
     */
    public function ajax_retry_orders_bulk() {
        check_ajax_referer( 'ventresslabs_retry_order_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'ventresslabs-intcomex' ) ) );
        }

        @set_time_limit( 0 );

        $order_service = new VentressLabs_Intcomex_Order_Service();
        $pending       = $order_service->get_orders_pending_retry();

        if ( empty( $pending ) ) {
            wp_send_json_success( array(
                'message' => __( 'No hay órdenes pendientes de reintento.', 'ventresslabs-intcomex' ),
                'results' => array(),
            ) );
        }

        $results = array();
        foreach ( $pending as $order ) {
            $order_id = $order->get_id();
            $existing = $order->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER, true );
            if ( ! empty( $existing ) ) {
                $results[] = array(
                    'order_id'     => $order_id,
                    'status'       => 'skipped',
                    'order_number' => $existing,
                );
                continue;
            }

            $response = $order_service->handle_order_created( $order_id );

            if ( is_wp_error( $response ) ) {
                // Re-check meta: maybe PlaceOrder succeeded but service returned error from intermediary.
                $re_check = wc_get_order( $order_id );
                $new_number = $re_check ? $re_check->get_meta( VentressLabs_Intcomex_Order_Service::META_IWS_ORDER_NUMBER, true ) : '';
                if ( ! empty( $new_number ) ) {
                    $results[] = array(
                        'order_id'     => $order_id,
                        'status'       => 'success',
                        'order_number' => $new_number,
                    );
                } else {
                    $results[] = array(
                        'order_id' => $order_id,
                        'status'   => 'error',
                        'error'     => $response->get_error_message(),
                    );
                }
            } else {
                $new_number = isset( $response['OrderNumber'] ) ? (string) $response['OrderNumber'] : '';
                $results[] = array(
                    'order_id'     => $order_id,
                    'status'       => 'success',
                    'order_number' => $new_number,
                );
            }
        }

        $success = count( array_filter( $results, function( $r ) { return 'success' === $r['status']; } ) );
        $errors  = count( array_filter( $results, function( $r ) { return 'error' === $r['status']; } ) );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: 1: success count, 2: error count. */
                __( 'Reintentos procesados: %1$d exitosos, %2$d fallidos.', 'ventresslabs-intcomex' ),
                $success,
                $errors
            ),
            'results' => $results,
        ) );
    }

    public function ajax_sync_products() {
        check_ajax_referer( 'ventresslabs_sync_nonce', 'nonce' );

        if ( ! is_plugin_active('woocommerce/woocommerce.php') ) {
            wp_send_json_error(['message' => 'WooCommerce is not active.']);
        }

        if ( ! class_exists( 'VentressLabs_Intcomex_Sync_Service' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-intcomex-sync-service.php';
        }

        @set_time_limit(0);
        $sync_service = new VentressLabs_Intcomex_Sync_Service();
        $result       = $sync_service->run_full_sync();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'  => __( 'Sincronización completa (GetCatalog + GetPriceList + GetInventory + WooCommerce).', 'ventresslabs-intcomex' ),
            'results'  => $result,
        ) );
    }

    /**
     * Synchronize a single product from the IWS catalog into WooCommerce.
     *
     * Price and stock are now expected to come from GetPriceList and
     * GetInventory respectively (Sección 5.1 IWS guide). If a value is null,
     * the method falls back to the corresponding field in the catalog entry
     * for backwards compatibility, while logging a warning in meta.
     *
     * @since 1.0.0
     * @param array       $api_product Product entry from GetCatalog.
     * @param string|null $price       Unit price from GetPriceList (or null).
     * @param int|null    $stock       Stock from GetInventory (or null).
     * @return string
     */
    public function sync_single_product( $api_product, $price = null, $stock = null ) {
        $sku = $api_product['Sku'] ?? null;
        if ( ! $sku ) {
            return "Skipped: Product is missing an SKU.";
        }

        $product_id = wc_get_product_id_by_sku( $sku );
        $product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();

        $product->set_sku( $sku );
        $product->set_name( $api_product['Description'] ?? '' );

        // Price: prefer GetPriceList, fall back to GetCatalog entry.
        if ( null === $price ) {
            $price = $api_product['Price']['UnitPrice'] ?? 0;
        }
        $product->set_regular_price( $price );
        $product->set_price( $price );

        // Stock: prefer GetInventory, fall back to GetCatalog entry.
        if ( null === $stock ) {
            $stock = $api_product['InStock'] ?? 0;
        }
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $stock );
        $product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );

        $product->update_meta_data( '_intcomex_synced_at', current_time( 'mysql' ) );
        $product->update_meta_data( '_intcomex_sku', $sku );

        $product_id = $product->save();

        if ( isset( $api_product['Category'] ) ) {
            $this->assign_product_category( $product_id, $api_product['Category'] );
        }

        // Asociar imágenes del catálogo extendido si están disponibles.
        $this->assign_product_images( $product_id, $sku, $api_product['Description'] ?? '' );

        return "Processed SKU {$sku}: " . ( $product_id ? 'Updated/Created' : 'Failed' );
    }

    /**
     * Download and attach images from the extended catalog to a WC product.
     *
     * Uses media_sideload_image() for each URL. Idempotent: skips if the
     * product already has the same set of images for the current extended
     * catalog version (controlled by meta).
     *
     * @since 1.1.0
     * @param int    $product_id WooCommerce product ID.
     * @param string $sku        Product SKU to look up in the extended catalog.
     * @param string $title      Product title for the attachment caption.
     */
    private function assign_product_images( $product_id, $sku, $title = '' ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Solo sincronizar si la descarga del catálogo extendido ya se ejecutó.
        $sync_extended_at = (int) get_option( VentressLabs_Intcomex_Sync_Service::OPT_EXTENDED_AT, 0 );
        if ( empty( $sync_extended_at ) ) {
            return;
        }

        $sync_service = new VentressLabs_Intcomex_Sync_Service();
        $extended      = $sync_service->get_extended_for_sku( $sku );
        if ( empty( $extended ) || empty( $extended['images'] ) ) {
            return;
        }

        // Saltar si ya procesamos este set de imágenes para este producto.
        $last_set_hash    = md5( wp_json_encode( $extended['images'] ) );
        $attached_hash    = get_post_meta( $product_id, '_intcomex_images_hash', true );
        $last_extended_at = (int) get_post_meta( $product_id, '_intcomex_images_synced_with', true );
        if ( $attached_hash === $last_set_hash && $last_extended_at === $sync_extended_at ) {
            return;
        }

        // Adjuntar imágenes sin eliminar las existentes (pueden ser del usuario).
        $attachment_ids = array();
        foreach ( $extended['images'] as $url ) {
            $attachment_id = media_sideload_image( $url, $product_id, $title ? $title : $sku, 'id' );
            if ( ! is_wp_error( $attachment_id ) ) {
                $attachment_ids[] = $attachment_id;
            }
        }

        // Primera imagen → imagen destacada; todas → galería del producto.
        if ( ! empty( $attachment_ids ) ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                set_post_thumbnail( $product_id, $attachment_ids[0] );

                $gallery_ids = array_slice( $attachment_ids, 1 );
                if ( ! empty( $gallery_ids ) ) {
                    $product->set_gallery_image_ids( $gallery_ids );
                    $product->save();
                }
            }

            update_post_meta( $product_id, '_intcomex_images_hash', $last_set_hash );
            update_post_meta( $product_id, '_intcomex_images_synced_with', $sync_extended_at );
        }
    }

    private function assign_product_category($product_id, $api_category_node) {
        $category_names = [];
        $this->get_category_hierarchy_names($api_category_node, $category_names);
        
        $term_ids = [];
        $parent_id = 0;

        foreach ($category_names as $cat_name) {
            $term = get_term_by('name', $cat_name, 'product_cat');
            if (!$term) {
                $new_term = wp_insert_term($cat_name, 'product_cat', ['parent' => $parent_id]);
                if (!is_wp_error($new_term)) {
                    $term_id = $new_term['term_id'];
                }
            } else {
                $term_id = $term->term_id;
            }
            $term_ids[] = $term_id;
            $parent_id = $term_id; // Next category will be a child of this one
        }
        
        if (!empty($term_ids)) {
            wp_set_object_terms($product_id, $term_ids, 'product_cat');
        }
    }
    
    private function get_category_hierarchy_names($api_category_node, &$names) {
        if (!empty($api_category_node['Description'])) {
            $names[] = $api_category_node['Description'];
        }
        if (!empty($api_category_node['Subcategories'])) {
            foreach($api_category_node['Subcategories'] as $subcat) {
                $this->get_category_hierarchy_names($subcat, $names);
            }
        }
    }

    public function ajax_fetch_categories() {
        check_ajax_referer( 'ventresslabs_fetch_categories_nonce', 'nonce' );

        $api_client = new VentressLabs_Intcomex_Api_Client();
        $catalog = $api_client->get_catalog();

        if ( is_wp_error( $catalog ) ) {
            wp_send_json_error( array( 'message' => $catalog->get_error_message() ) );
        }

        if ( empty( $catalog ) || ! is_array( $catalog ) ) {
            wp_send_json_error( array( 'message' => 'No products found in the catalog or invalid format.' ) );
        }

        $categories = [];
        foreach ( $catalog as $product ) {
            if ( isset( $product['Category'] ) ) {
                $this->extract_categories_recursive( $product['Category'], $categories );
            }
        }

        set_transient( 'ventresslabs_intcomex_categories', $categories, DAY_IN_SECONDS );
        $saved_categories = get_option('ventresslabs_intcomex_selected_categories', []);

        wp_send_json_success( ['categories' => $categories, 'saved_categories' => $saved_categories] );
    }

    public function ajax_clear_logs() {
        check_ajax_referer( 'ventresslabs_clear_logs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'ventresslabs-intcomex' ) ) );
        }

        $logger = new VentressLabs_Intcomex_Logger();
        $logger->clear();
        wp_send_json_success( array( 'message' => __( 'Logs eliminados.', 'ventresslabs-intcomex' ) ) );
    }

    /**
     * Manual trigger for DownloadExtendedCatalog. Honors the monthly throttle
     * unless the request indicates force=1 (admin only).
     *
     * @since 1.1.0
     */
    public function ajax_sync_extended() {
        check_ajax_referer( 'ventresslabs_sync_extended_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'ventresslabs-intcomex' ) ) );
        }

        if ( ! VentressLabs_Intcomex_Endpoint_Manager::is_enabled( VentressLabs_Intcomex_Endpoint_Manager::EP_DOWNLOAD_EXTENDED ) ) {
            wp_send_json_error( array(
                'message' => __( 'DownloadExtendedCatalog está deshabilitado en Intcomex → Endpoints.', 'ventresslabs-intcomex' ),
            ) );
        }

        $force = isset( $_POST['force'] ) && '1' === $_POST['force'];
        $sync  = new VentressLabs_Intcomex_Sync_Service();
        $result = $sync->sync_extended_catalog( $force );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => sprintf( __( 'DownloadExtendedCatalog procesado: %d productos con imágenes.', 'ventresslabs-intcomex' ), (int) $result ),
            'count'   => (int) $result,
        ) );
    }

    private function extract_categories_recursive( $category_node, &$categories ) {
        if ( ! empty( $category_node['CategoryId'] ) && ! isset( $categories[ $category_node['CategoryId'] ] ) ) {
            $categories[ $category_node['CategoryId'] ] = $category_node['Description'];
        }

        if ( ! empty( $category_node['Subcategories'] ) && is_array( $category_node['Subcategories'] ) ) {
            foreach ( $category_node['Subcategories'] as $subcategory ) {
                $this->extract_categories_recursive( $subcategory, $categories );
            }
        }
    }

    public function render_categories_field_callback() {
        $categories = get_transient('ventresslabs_intcomex_categories');
        $saved_categories = get_option('ventresslabs_intcomex_selected_categories', []);
        ?>
        <button id="fetch-intcomex-categories" class="button"><?php _e( 'Fetch Categories from Intcomex', 'ventresslabs-intcomex' ); ?></button>
        <div id="intcomex-categories-list" style="margin-top: 10px; border: 1px solid #ccc; padding: 10px; max-height: 300px; overflow-y: auto;">
            <?php if ( ! empty( $categories ) ) : ?>
                <?php foreach ( $categories as $id => $name ) : ?>
                    <div>
                        <label>
                            <input type="checkbox" name="ventresslabs_intcomex_selected_categories[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $saved_categories ) ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>Please save your API credentials and click "Fetch Categories".</p>
            <?php endif; ?>
        </div>
        <p class="description">
            <?php _e('After fetching, select the categories you wish to sync and click "Save Settings".', 'ventresslabs-intcomex'); ?>
        </p>
        <?php
    }
}
