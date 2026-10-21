<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class WC_Gateway_khipu_abstract extends WC_Payment_Gateway
{
    const PLUGIN_VERSION = '4.3.0';
    const API_VERSION = '3.0';

    /** Opción de WordPress compartida por las tres pasarelas (un solo panel). */
    const SETTINGS_OPTION = 'woocommerce_khipu_settings';

    public $notify_url;
    public $receiver_id;
    public $secret;
    public $api_key;
    public $after_payment_status;

    /**
     * Imagen de assets/img que se muestra junto al medio ('' = sin ícono).
     */
    const ICON_FILE = '';

    /**
     * Cada subclase define ID, METHOD_ID (medio de Khipu) y URL_FIELD (clave de
     * la URL del medio en la respuesta de POST /payments).
     */
    public function __construct()
    {
        $this->id = static::ID;
        $this->has_fields = false;
        $this->method_title = __('Khipu', 'woocommerce-khipu-gateway');
        $this->method_description = __('Acepte pagos con Khipu. Un solo panel para todos los medios de pago de su cuenta.', 'woocommerce-khipu-gateway');
        $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));

        $this->init_form_fields();
        $this->init_settings();
        $this->title = WC_Khipu_Methods::compose_title($this->get_option('title'), static::METHOD_ID);
        $this->description = $this->get_option('description');
        $this->receiver_id = $this->get_option('receiver_id');
        $this->secret = $this->get_option('secret');
        $this->api_key = $this->get_option('api_key');
        $this->icon = $this->get_payment_method_icon(static::METHOD_ID);
        $this->after_payment_status = $this->get_option('after_payment_status');

        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_ipn_response'));

        if (!$this->is_valid_for_use()) {
            $this->enabled = false;
        }
    }

    /**
     * Las tres pasarelas leen y guardan en la misma opción.
     */
    public function get_option_key()
    {
        return self::SETTINGS_OPTION;
    }

    /**
     * El embebido abre Khipu Web, que hoy solo cubre la transferencia
     * simplificada. Está disponible solo si la cuenta ofrece ese medio; que la
     * fila esté marcada o no en "Medios de pago a mostrar" no importa, porque el
     * modo solo afecta a esa fila.
     */
    public function embedded_available()
    {
        $ok = $this->is_configured()
            && self::methods_include_any($this->get_payment_methods(), array('SIMPLIFIED_TRANSFER'));
        return (bool) apply_filters('woocommerce_khipu_embedded_available', $ok);
    }

    /**
     * Sin transferencia simplificada en la cuenta el modo se fuerza a redirección,
     * sin importar lo guardado.
     */
    public function get_option($key, $empty_value = null)
    {
        if ('embedded' === $key && !$this->embedded_available()) {
            return 'no';
        }
        return parent::get_option($key, $empty_value);
    }

    /**
     * Si el embebido no está disponible, el interruptor "Modo de pago" se ve gris,
     * fijo en Redirección y con una advertencia.
     */
    public function get_form_fields()
    {
        $fields = parent::get_form_fields();
        if (isset($fields['embedded']) && !$this->embedded_available()) {
            $fields['embedded']['default'] = 'no';
            $fields['embedded']['custom_attributes']['disabled'] = 'disabled';
        }
        return $fields;
    }

    /**
     * Formulario único: lo muestra la pasarela anfitriona (transferencia normal).
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Habilitar/Deshabilitar', 'woocommerce-khipu-gateway'),
                'type' => 'checkbox',
                'label' => __('Habilita Khipu', 'woocommerce-khipu-gateway'),
                'description' => __('Activa o desactiva todos los medios de pago de Khipu en el checkout.', 'woocommerce-khipu-gateway'),
                'desc_tip' => true,
                'default' => 'yes'
            ),
            'receiver_id' => array(
                'title' => __('Id de cobrador', 'woocommerce-khipu-gateway'),
                'type' => 'text',
                'description' => __('Ingrese su Id de cobrador. Se obtiene en https://khipu.com/merchant/profile ', 'woocommerce-khipu-gateway'),
                'default' => '',
                'desc_tip' => true
            ),
            'secret' => array(
                'title' => __('Llave', 'woocommerce-khipu-gateway'),
                'type' => 'text',
                'description' => __('Ingrese su llave secreta. Se obtiene en https://khipu.com/merchant/profile ', 'woocommerce-khipu-gateway'),
                'default' => '',
                'desc_tip' => true
            ),
            'api_key' => array(
                'title' => __('Api Key', 'woocommerce-khipu-gateway'),
                'type' => 'text',
                'description' => __('Ingrese su Api Key. Se obtiene en https://khipu.com/merchant/profile ', 'woocommerce-khipu-gateway'),
                'default' => '',
                'desc_tip' => true
            ),
            'title' => array(
                'title' => __('Título', 'woocommerce-khipu-gateway'),
                'type' => 'text',
                'description' => __('Título que ve el comprador. Cada medio de pago se muestra como "Título - Medio".', 'woocommerce-khipu-gateway'),
                'default' => 'Khipu',
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Descripción', 'woocommerce-khipu-gateway'),
                'type' => 'textarea',
                'description' => __('Descripción que ve el comprador al elegir un medio de Khipu.', 'woocommerce-khipu-gateway'),
                'desc_tip' => true,
                'default' => 'Paga con tu banco'
            ),
            'embedded' => array(
                'title' => __('Modo de pago', 'woocommerce-khipu-gateway'),
                'type' => 'checkbox',
                'label' => ' ',
                'class' => 'khipu-toggle',
                'custom_attributes' => array('aria-label' => 'Modo de pago: a la izquierda Embebido, a la derecha Redirección'),
                'description' => __('Embebido solo es efectivo para el medio de pago transferencia simplificada.', 'woocommerce-khipu-gateway'),
                'desc_tip' => __('Embebido abre Khipu dentro de la tienda. Redirección lleva al comprador al sitio de Khipu.', 'woocommerce-khipu-gateway'),
                'default' => 'yes'
            ),
            'enabled_methods' => array(
                'title' => __('Medios de pago a mostrar', 'woocommerce-khipu-gateway'),
                'type' => 'khipu_methods',
                'default' => ''
            ),
            'after_payment_status' => array(
                'title' => __('Estado del pedido pagado.', 'woocommerce-khipu-gateway'),
                'description' => __('Seleccione estado con el que desea dejar sus órdenes luego de pagadas.', 'woocommerce-khipu-gateway'),
                'type' => 'select',
                'options' => array(
                    'wc-processing' => __('Procesando', 'woocommerce-khipu-gateway'),
                    'wc-completed' => __('Completado', 'woocommerce-khipu-gateway'),
                ),
                'default' => 'wc-processing',
                'desc_tip' => true
            ),
        );
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     */
    function is_valid_for_use()
    {
        return $this->is_configured()
            && $this->is_method_selected()
            && $this->is_payment_method_available();
    }

    function is_configured()
    {
        return $this->secret && $this->receiver_id && $this->api_key;
    }

    /**
     * El admin marcó este medio en el panel (sin selección guardada: todos).
     */
    function is_method_selected()
    {
        return WC_Khipu_Methods::is_selected(static::METHOD_ID, $this->get_option('enabled_methods'));
    }

    /**
     * La cuenta de cobro tiene este medio habilitado en Khipu.
     */
    function is_payment_method_available()
    {
        return self::methods_include_any(
            $this->get_payment_methods(),
            self::required_method_ids(get_woocommerce_currency(), static::METHOD_ID)
        );
    }

    /**
     * Process the payment and return the result: lleva a la página de pago del
     * pedido, donde receipt_page() crea el pago en Khipu. Sirve para el checkout
     * clásico y para Blocks. (Antes vivía en cada pasarela; ahora es compartido.)
     */
    function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
    }

    /**
     * Output for the order received page.
     */
    function receipt_page($order)
    {
        $this->render_receipt($order);
    }

    /**
     * Headers estándar para llamadas a la API de Khipu — compartido entre
     * este archivo y WC_Khipu_Refund_Service.
     *
     * @param string $api_key
     * @return array
     */
    public static function build_api_headers($api_key)
    {
        return [
            'x-api-key'    => $api_key,
            'Content-Type' => 'application/json',
            'User-Agent'   => 'khipu-api-php-client/' . self::API_VERSION . '|woocommerce-khipu/' . self::PLUGIN_VERSION,
        ];
    }

    function comm_error($exception = null)
    {
        if (!$exception) {
            $msg = __('Error de comunicación con khipu, por favor intente nuevamente más tarde.', 'woocommerce-khipu-gateway');
        } else {
            $msg = "<h2>Error de comunicación con khipu</h2><ul><li><strong>Código</strong>:" . $exception->getStatus() . "</li>";
            $msg .= "<li><strong>Mensaje</strong>: " . $exception->getMessage() . "</li>";
            if (method_exists($exception, 'getErrors')) {
                $msg .= "<li>Errores<ul>";
                foreach ($exception->getErrors() as $errorItem) {
                    $msg .= "<li><strong>" . $errorItem->getField() . "</strong>: " . $errorItem->getMessage() . "</li>";
                }
                $msg .= "</ul></li>";
            }
            $msg .= "</ul>";
        }
        return "<div class='woocommerce-error'>$msg</div>";
    }

    /**
     * Create the payment on khipu and try to start the app.
     */
    function create_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $item_names = array();

        if (sizeof($order->get_items()) > 0) {
            foreach ($order->get_items() as $item) {
                if ($item['qty']) {
                    $item_names[] = $item['qty'] . ' x ' . $item['name'];
                }
            }
        }

        $headers = self::build_api_headers($this->api_key);
        $payments_url = 'https://payment-api.khipu.com/v3/payments';
        $cartProductsKhipu = '';
        foreach ($item_names as $product) {
            $cartProductsKhipu .= "\n\r" . $product;
        }

        $data = [
            'subject'           => 'Orden ' . $order->get_order_number() . ' - ' . get_bloginfo('name'),
            'currency'          => $order->get_currency(),
            'amount'            => (float) number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', ''),
            'transaction_id'    => ltrim($order->get_order_number(), '#'),
            'custom'            => serialize([$order_id, $order->get_order_key()]),
            'body'              => 'Productos incluidos en la compra:' . $cartProductsKhipu,
            'return_url'        => $this->get_return_url($order),
            'cancel_url'        => $order->get_checkout_payment_url(),
            'notify_url'        => $this->notify_url,
            'notify_api_version'=> '3.0',
            'payer_email'       => $order->get_billing_email()
        ];

        $held_duration = get_option('woocommerce_hold_stock_minutes');

        if ($held_duration > 1 && 'yes' == get_option('woocommerce_manage_stock')) {
            $interval = new DateInterval('PT' . $held_duration . 'M');
            $timeout = new DateTime('now');
            $timeout->add($interval);
            $data['expires_date'] = $timeout->format(DateTime::ATOM);
        }

        $response = wp_remote_post($payments_url, [
            'headers' => $headers,
            'body'    => json_encode($data),
            'method'  => 'POST'
        ]);

        if (is_wp_error($response)) {
            // comm_error() espera una excepción (getStatus), no un string: se loguea el detalle.
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(sprintf('Khipu create payment: %s', $response->get_error_message()), array('source' => 'khipu'));
            }
            return $this->comm_error();
        }

        $response_body = wp_remote_retrieve_body($response);
        $createPaymentResponse = json_decode($response_body);

        if (empty($createPaymentResponse) || isset($createPaymentResponse->error)) {
            $error_message = json_encode(isset($createPaymentResponse->error) ? $createPaymentResponse->error : $response_body);
            return "<div class='woocommerce-error'>Error ab: No se pudo crear el pago. " . esc_html($error_message) . "</div>";
        }

        return $createPaymentResponse;
    }

    private function get_payment_response($payment_id)
    {
        $headers = self::build_api_headers($this->api_key);

        $payments_url = 'https://payment-api.khipu.com/v3/payments/' . $payment_id;

        $response = wp_remote_get($payments_url, [
            'headers' => $headers
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response));
    }

    /**
     * Check for Khipu IPN Response
     */
    function check_ipn_response()
    {
        @ob_clean();
        http_response_code(401);

        // Sin llave secreta configurada la firma se podría calcular con llave vacía.
        if ('' === (string) $this->secret) {
            http_response_code(401);
            exit('Not configured');
        }

        $raw_post = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_KHIPU_SIGNATURE'];
        if (empty($_SERVER['HTTP_X_KHIPU_SIGNATURE'])) {
            http_response_code(401);
            exit("Missing signature");
        }

        $signature_parts = explode(',', $signature,2);
        foreach ($signature_parts as $part) {
            [$key, $value] = explode('=', $part,2);
            if ($key === 't') {
                $t_value = $value;
            } elseif ($key === 's') {
                $s_value = $value;
            }
        }
        $to_hash = $t_value . '.' . $raw_post;
        $hash_bytes = hash_hmac('sha256', $to_hash, $this->secret, true);
        $hmac_base64 = base64_encode($hash_bytes);

        if ($hmac_base64 !== $s_value) {
            http_response_code(401);
            exit("Invalid signature");
        }

        $paymentResponse = json_decode($raw_post);
        if (!$paymentResponse) {
            http_response_code(401);
            exit("No payment response for payment ID");
        }

        $order = $this->get_order($paymentResponse);
        if (!$order) {
            http_response_code(401);
            exit("No order for payment response");
        }

        if (!in_array($order->get_payment_method(), array(
            WC_Gateway_khipu_regular_transfer::ID,
            WC_Gateway_khipu_simplified_transfer::ID,
            WC_Gateway_khipu_ligopay::ID,
        ), true)) {
            http_response_code(401);
            exit('Order is not a Khipu order');
        }

        if ($paymentResponse->amount != floatval(number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', ''))) {
            http_response_code(401);
            $expected = floatval( number_format( $order->get_total(), absint( get_option( 'woocommerce_price_num_decimals', 2 ) ), '.', '' ) );
            exit( 'Wrong amount. Expected: ' . esc_html( (string) $expected ) . ' got ' . esc_html( (string) $paymentResponse->amount ) );
        }

        if ($order) {
            http_response_code(200);
            if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                exit('Notification already processed');
            }
            /* translators: string contains placeholders. */
            $order->add_order_note(sprintf(__('Pago verificado con código único de verificación khipu %s', 'woocommerce-khipu-gateway'), $paymentResponse->payment_id));
            $order->payment_complete($paymentResponse->payment_id);
            $defined_status = $this->get_option('after_payment_status');
            if ($defined_status) {
                $order->update_status($defined_status);
            }
            exit('Notification processed');
        }
    }

    /**
     * get_khipu_order function.
     */
    private function get_order($paymentResponse)
    {
        $custom = isset($paymentResponse->custom) ? $paymentResponse->custom : null;
        // `custom` lo controla quien envía la petición: nunca se instancian objetos.
        if (is_string($custom) && is_serialized($custom)) {
            $custom = unserialize($custom, array('allowed_classes' => false));
            if (!is_array($custom) || 2 !== count($custom)) {
                return null;
            }
        }

        // Backwards comp for IPN requests
        if (is_numeric($custom)) {
            $order_id = (int)$custom;
            $order_key = $paymentResponse->transaction_id;
        } elseif (is_string($custom)) {
            $order_id = (int)str_replace($this->invoice_prefix, '', $custom);
            $order_key = $custom;
        } elseif (is_array($custom) && 2 === count($custom)) {
            list($order_id, $order_key) = array_values($custom);
        } else {
            return null;
        }

        $order = new WC_Order($order_id);

        if (!isset($order->id)) {
            $order_id = woocommerce_get_order_id_by_order_key($order_key);
            $order = new WC_Order($order_id);
        }

        return $order;
    }

    /**
     * Admin Panel Options
     */
    public function admin_options()
    {
        // Interruptor de dos extremos: izquierda Embebido (marcado), derecha Redirección.
        echo '<style>
            .woocommerce table.form-table input.khipu-toggle { -webkit-appearance: none; appearance: none; position: relative; box-sizing: border-box; width: 240px; height: 34px; margin: 0; padding: 0; border: 0; border-radius: 17px; background: #e2e2e2; box-shadow: none; vertical-align: middle; cursor: pointer; font: 600 13px/34px sans-serif; }
            .woocommerce table.form-table input.khipu-toggle::before { content: "Redirección"; position: absolute; top: 3px; left: 3px; width: 116px; height: 28px; margin: 0; border-radius: 14px; background: #8347AD; color: #fff; text-align: center; font: 600 13px/28px sans-serif; transform: translateX(118px); transition: transform .2s; }
            .woocommerce table.form-table input.khipu-toggle::after { content: "Embebido"; position: absolute; top: 0; left: 0; width: 120px; height: 34px; color: #666; text-align: center; font: 600 13px/34px sans-serif; }
            .woocommerce table.form-table input.khipu-toggle:checked::before { content: "Embebido"; transform: translateX(0); }
            .woocommerce table.form-table input.khipu-toggle:checked::after { content: "Redirección"; left: auto; right: 0; }
            .woocommerce table.form-table input.khipu-toggle:disabled { opacity: .55; cursor: not-allowed; }
            .woocommerce table.form-table input.khipu-toggle:focus { outline: 2px solid #3CB4E5; outline-offset: 2px; box-shadow: none; }
        </style>';
        parent::admin_options();
        // El sol y el peso chileno no usan los mismos decimales: una tienda que
        // pasa a PEN con los decimales de CLP (0) cobraría el monto redondeado.
        if ('PEN' === get_woocommerce_currency() && 0 === absint(get_option('woocommerce_price_num_decimals', 2))) {
            ?>
            <div class="inline notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Revisa los decimales de precio', 'woocommerce-khipu-gateway'); ?></strong>:
                    <?php esc_html_e('La tienda está en soles (PEN) con 0 decimales. Khipu cobraría el monto redondeado al entero. Configura 2 decimales en WooCommerce → Ajustes → General → Opciones de moneda.', 'woocommerce-khipu-gateway'); ?>
                </p>
            </div>
            <?php
        }

        // Sección "Billetera de reversas" al final: saldo + estado del pedido
        // reversado (o placeholder si aún no hay credenciales configuradas).
        echo WC_Khipu_Refund_Admin::get_wallet_section_html(
            $this->api_key,
            WC_Khipu_Refund_Admin::configured_after_refund_status($this->id)
        );
    }

    /**
     * El campo "Estado del pedido reversado" (after_refund_status) se renderiza
     * en la sección "Billetera de reversas" (fuera de la tabla estándar de
     * WooCommerce, ver WC_Khipu_Refund_Admin::get_wallet_section_html()), así que
     * WooCommerce no lo guarda solo: lo persistimos manualmente acá.
     */
    public function process_admin_options()
    {
        $saved = parent::process_admin_options();
        // El mismo request dibuja el panel de nuevo: se refrescan las credenciales del objeto.
        $this->init_settings();
        $this->receiver_id = $this->get_option('receiver_id');
        $this->secret = $this->get_option('secret');
        $this->api_key = $this->get_option('api_key');
        // La lista de medios se cachea 30 s sin distinguir credenciales: al
        // guardar el panel se descarta para que las casillas reflejen la cuenta nueva.
        delete_option('woocommerce_gateway_khipu_payment_methods');

        if (isset($_POST['khipu_after_refund_status'])) {
            $posted = wc_clean(wp_unslash($_POST['khipu_after_refund_status']));
            if (!in_array($posted, ['', 'wc-refunded'], true)) {
                $posted = 'wc-refunded';
            }
            $option_key = $this->get_option_key();
            $settings = get_option($option_key, []);
            if (!is_array($settings)) {
                $settings = [];
            }
            $settings['after_refund_status'] = $posted;
            update_option($option_key, $settings);
        }

        return $saved;
    }

    function get_payment_methods()
    {
        $response = get_option('woocommerce_gateway_khipu_payment_methods');
        if (!$this->receiver_id || !$this->api_key) {
            return null;
        }

        if (!$response || !$response['ts'] || (time() - (int)$response['ts']) > 30) {
            // Último resultado bueno: si Khipu falla un momento, el checkout no pierde sus medios.
            $last_good = (is_array($response) && !empty($response['methods'])) ? $response['methods'] : null;
            unset($response);
            $payments_url = 'https://payment-api.khipu.com/v3/merchants/' . $this->receiver_id . '/paymentMethods';

            // --- reemplazo de cURL por WP HTTP API ---
            $headers = self::build_api_headers($this->api_key);

            $http_response = wp_remote_get( $payments_url, array(
                'headers' => $headers,
                'timeout' => 20,
            ) );

            if ( is_wp_error( $http_response ) ) {
                if ( function_exists( 'wc_get_logger' ) ) {
                    wc_get_logger()->error(
                        sprintf( 'Khipu API error: %s', $http_response->get_error_message() ),
                        array( 'source' => 'khipu' )
                    );
                }
                $this->cache_payment_methods_failure($last_good);
                return $last_good;
            }

            $http_code = (int) wp_remote_retrieve_response_code( $http_response );
            if ( 200 === $http_code ) {
                $body_raw = wp_remote_retrieve_body( $http_response );
                $paymentsMethodsResponse = json_decode( $body_raw );
                $response = array();
                $response['methods'] = $paymentsMethodsResponse;
                $response['ts'] = time();
                update_option('woocommerce_gateway_khipu_payment_methods', $response);
            } else {
                if ( function_exists( 'wc_get_logger' ) ) {
                        wc_get_logger()->error(
                            sprintf( 'Error en la solicitud a Khipu API: HTTP code %d', $http_code ),
                            array( 'source' => 'khipu' )
                        );
                    }
                $this->cache_payment_methods_failure($last_good);
                return $last_good;
            }
        }
        return $response['methods'];
    }

    /**
     * Cachea 30 s el fallo de la consulta de medios (misma opción que el éxito)
     * para no repetir la llamada por cada pasarela ni en cada request. Conserva
     * el último resultado bueno, si lo hay.
     */
    private function cache_payment_methods_failure($last_good = null)
    {
        update_option('woocommerce_gateway_khipu_payment_methods', array('methods' => $last_good, 'ts' => time()));
    }

    function get_payment_method_icon($id)
    {
        // Ícono propio del plugin, igual en todos los países (LigoPay aún no tiene: '').
        return static::ICON_FILE ? plugins_url('assets/img/' . static::ICON_FILE, WC_KHIPU_PLUGIN_FILE) : '';
    }

    /**
     * URL del ícono de una pasarela para Blocks ('' si no tiene).
     */
    public static function blocks_icon($gateway_id)
    {
        $gateways = function_exists('WC') && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
        return isset($gateways[$gateway_id]) ? (string) $gateways[$gateway_id]->icon : '';
    }

    /**
     * Ids de medios de pago que la cuenta debe tener para ofrecer este gateway.
     *
     * Cada fila del checkout exige su propio medio, en cualquier moneda: en
     * Perú (PEN) la transferencia manual y LigoPay son filas distintas.
     *
     * @param string $currency   moneda de la tienda (no cambia el medio exigido)
     * @param string $default_id medio propio del gateway (ej. REGULAR_TRANSFER)
     * @return string[]
     */
    public static function required_method_ids($currency, $default_id)
    {
        return array($default_id);
    }

    /**
     * @param object|null $methods respuesta de /paymentMethods
     * @param string[]    $ids
     * @return bool
     */
    public static function methods_include_any($methods, array $ids)
    {
        if (!$methods || empty($methods->paymentMethods)) {
            return false;
        }
        foreach ($methods->paymentMethods as $paymentMethod) {
            if (in_array($paymentMethod->id, $ids, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * URL a la que se redirige al comprador tras crear el pago: la URL propia
     * del medio de este gateway (transfer_url, simplified_transfer_url,
     * ligopay_url), también en PEN. Sin ella no se improvisa otra.
     *
     * @param object|string $response respuesta de POST /payments
     * @param string        $currency moneda de la tienda (no cambia la URL)
     * @param string        $own_key  propiedad propia del gateway
     * @return string|null
     */
    public static function select_redirect_url($response, $currency, $own_key)
    {
        if (is_object($response) && !empty($response->$own_key)) {
            return (string) $response->$own_key;
        }
        return null;
    }

    /**
     * Ids de los medios que la cuenta de cobro tiene habilitados en Khipu.
     *
     * @return string[]
     */
    protected function account_method_ids()
    {
        return array_values(array_intersect(
            WC_Khipu_Methods::account_method_ids($this->get_payment_methods()),
            $this->known_method_ids()
        ));
    }

    /**
     * Medios que tienen pasarela en el plugin (solo esos tienen casilla).
     *
     * @return string[]
     */
    protected function known_method_ids()
    {
        return array(
            WC_Gateway_khipu_regular_transfer::METHOD_ID,
            WC_Gateway_khipu_simplified_transfer::METHOD_ID,
            WC_Gateway_khipu_ligopay::METHOD_ID,
        );
    }

    /**
     * Campo "Medios de pago a mostrar": una casilla por medio de la cuenta.
     */
    public function generate_khipu_methods_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $saved = $this->get_option($key);
        $ids = $this->is_configured() ? $this->account_method_ids() : array();

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><label><?php echo wp_kses_post($data['title']); ?> <?php echo wc_help_tip(__('Marca los medios de pago de tu cuenta que quieres ofrecer. Cada medio marcado aparece como una opción en el checkout.', 'woocommerce-khipu-gateway')); // phpcs:ignore WordPress.Security.EscapeOutput ?></label></th>
            <td class="forminp">
                <fieldset>
                    <?php if (!$this->is_configured()) : ?>
                        <p class="description"><?php esc_html_e('Guarda tus credenciales para ver los medios de pago disponibles en tu cuenta.', 'woocommerce-khipu-gateway'); ?></p>
                    <?php elseif (empty($ids)) : ?>
                        <p class="description"><?php esc_html_e('No se pudieron consultar los medios de pago de tu cuenta. Se conserva la selección guardada.', 'woocommerce-khipu-gateway'); ?></p>
                    <?php else : ?>
                        <input type="hidden" name="<?php echo esc_attr($field_key . '_present'); ?>" value="1" />
                        <?php foreach ($ids as $id) : ?>
                            <label style="display:block;margin-bottom:4px">
                                <input type="checkbox" name="<?php echo esc_attr($field_key); ?>[]" value="<?php echo esc_attr($id); ?>" <?php checked(WC_Khipu_Methods::is_selected($id, $saved)); ?> />
                                <?php echo esc_html(WC_Khipu_Methods::label($id)); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Guarda la selección. Si el campo no se mostró (sin credenciales o API
     * caída) se conserva lo guardado; mostrarlo y desmarcar todo guarda [].
     */
    public function validate_khipu_methods_field($key, $value)
    {
        $field_key = $this->get_field_key($key);
        if (empty($_POST[$field_key . '_present'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce valida el nonce antes de process_admin_options().
            return $this->get_option($key);
        }
        $ids = $this->account_method_ids();
        if (empty($ids)) {
            // La API no respondió justo al guardar: se conserva lo guardado en vez de borrar la selección.
            return $this->get_option($key);
        }
        return WC_Khipu_Methods::normalize_selection($value, $ids);
    }

    // ------------------------------------------------------------------
    // Pago embebido (KWS) y redirección
    // ------------------------------------------------------------------

    const KWS_SCRIPT_URL = 'https://js.khipu.com/v1/kws.js';
    const META_PAYMENT_ID = '_khipu_payment_id';
    const META_PAYMENT_URL = '_khipu_payment_url';
    const META_PAYMENT_TOTAL = '_khipu_payment_total';
    const META_PAYMENT_METHOD = '_khipu_payment_method';
    const META_PAYMENT_OWN_URL = '_khipu_payment_own_url';
    const AJAX_CANCEL_ACTION = 'khipu_embedded_cancel';

    /**
     * Pago embebido (casilla del panel, activa por defecto), solo para transferencia simplificada.
     */
    public function is_embedded()
    {
        return 'SIMPLIFIED_TRANSFER' === static::METHOD_ID && 'yes' === $this->get_option('embedded', 'yes');
    }

    /**
     * DELETE /v3/payments/{id}. Devuelve el código HTTP (0 si no hubo respuesta).
     */
    public function delete_payment($payment_id)
    {
        $response = wp_remote_request('https://payment-api.khipu.com/v3/payments/' . rawurlencode($payment_id), array(
            'method'  => 'DELETE',
            'headers' => self::build_api_headers($this->api_key),
            'timeout' => 20,
        ));
        if (is_wp_error($response)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(sprintf('Khipu delete payment: %s', $response->get_error_message()), array('source' => 'khipu'));
            }
            return 0;
        }
        return (int) wp_remote_retrieve_response_code($response);
    }

    /**
     * Decide qué hacer con un pago ya creado (GET /v3/payments/{id} devuelve
     * `status` y `expires_date` ISO 8601):
     *  - 'reuse'       sigue 'pending' y vigente, o no se pudo saber su estado
     *                  (red caída, respuesta rara): ante la duda no se toca;
     *  - 'new'         ya no se puede pagar (deleted/expired, vencido o 404);
     *  - 'in_progress' el comprador ya pagó o Khipu lo está verificando: no se
     *                  borra ni se crea otro, para no cobrar dos veces.
     */
    private function payment_state($payment_id)
    {
        $response = wp_remote_get('https://payment-api.khipu.com/v3/payments/' . rawurlencode($payment_id), array(
            'headers' => self::build_api_headers($this->api_key),
            'timeout' => 20,
        ));
        if (is_wp_error($response)) {
            return 'reuse';
        }
        if (404 === (int) wp_remote_retrieve_response_code($response)) {
            return 'new';
        }
        $payment = json_decode(wp_remote_retrieve_body($response));
        if (!is_object($payment) || empty($payment->status)) {
            return 'reuse';
        }
        if ('pending' === $payment->status) {
            if (!empty($payment->expires_date)) {
                $expires = strtotime((string) $payment->expires_date);
                if (false !== $expires && $expires <= time()) {
                    return 'new';
                }
            }
            return 'reuse';
        }
        if (in_array($payment->status, array('deleted', 'expired'), true)) {
            return 'new';
        }
        return 'in_progress';
    }

    /**
     * Crea el pago en Khipu UNA sola vez por pedido y medio (guarda payment_id
     * en meta). Si ya existe, el pedido sigue pendiente, el total no cambió y
     * el medio es el mismo, lo reutiliza. Si ya está pagado o en verificación no
     * hace nada (avisa al comprador). Si hay que crear uno nuevo (otro
     * medio u otro total), el anterior se borra en Khipu para que no quede
     * pagable junto al nuevo.
     *
     * @return array{payment_id:string,url:string,own_url:string}|string  string = HTML de error
     *         url = URL para el modal o el respaldo (la del medio, o la página general del pago);
     *         own_url = URL propia del medio ('' si Khipu no la entregó).
     */
    protected function get_or_create_payment($order)
    {
        $total = (string) $order->get_total();
        $pid   = $order->get_meta(self::META_PAYMENT_ID);
        $url   = $order->get_meta(self::META_PAYMENT_URL);
        $state = $pid ? $this->payment_state($pid) : 'new';

        if ('in_progress' === $state) {
            return "<div class='woocommerce-info'>" . esc_html__('Tu pago está en verificación. Te avisaremos cuando Khipu lo confirme; no es necesario que pagues de nuevo.', 'woocommerce-khipu-gateway') . '</div>';
        }

        if ($pid && $url && 'reuse' === $state
            && $order->has_status(array('pending', 'failed'))
            && (string) $order->get_meta(self::META_PAYMENT_TOTAL) === $total
            && static::METHOD_ID === $order->get_meta(self::META_PAYMENT_METHOD)
        ) {
            return array('payment_id' => $pid, 'url' => $url, 'own_url' => (string) $order->get_meta(self::META_PAYMENT_OWN_URL));
        }

        if ($pid && 'new' !== $state) {
            // El pago anterior sigue vivo en Khipu (otro medio u otro total). Mejor esfuerzo: si Khipu no acepta borrarlo, queda una nota.
            $code = $this->delete_payment($pid);
            if (!($code >= 200 && $code < 300) && 404 !== $code) {
                $order->add_order_note(sprintf('Khipu: no se pudo borrar el cobro anterior %s (HTTP %d) al crear uno nuevo.', $pid, $code));
            }
        }

        $response = $this->create_payment($order->get_id());
        if (!is_object($response)) {
            return $response ? (string) $response : "<div class='woocommerce-error'>Error: No se pudo crear el pago.</div>";
        }
        if (empty($response->payment_id)) {
            return "<div class='woocommerce-error'>Error: respuesta inesperada de Khipu al crear el pago.</div>";
        }
        // URL de respaldo si el modal no carga: la del medio, o la página general del pago.
        $own = (string) self::select_redirect_url($response, get_woocommerce_currency(), static::URL_FIELD);
        $url = $own;
        if ('' === $url && !empty($response->payment_url)) {
            $url = (string) $response->payment_url;
        }
        $order->update_meta_data(self::META_PAYMENT_ID, $response->payment_id);
        $order->update_meta_data(self::META_PAYMENT_URL, $url);
        $order->update_meta_data(self::META_PAYMENT_OWN_URL, $own);
        $order->update_meta_data(self::META_PAYMENT_TOTAL, $total);
        $order->update_meta_data(self::META_PAYMENT_METHOD, static::METHOD_ID);
        $order->save();
        return array('payment_id' => $response->payment_id, 'url' => $url, 'own_url' => $own);
    }

    /**
     * Página de pago del pedido (hook woocommerce_receipt_{id}). Sirve para el
     * checkout clásico y para el de Blocks: ambos terminan aquí vía process_payment().
     * En ambos modos el pago se crea una sola vez por pedido y medio.
     */
    protected function render_receipt($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            echo "<div class='woocommerce-error'>Error: pedido no encontrado.</div>";
            return;
        }
        $payment = $this->get_or_create_payment($order);
        if (!is_array($payment)) {
            echo $payment;
            return;
        }

        if (!$this->is_embedded()) {
            // Redirección: se manda al comprador a la URL de este medio.
            $url = $payment['own_url'];
            if ('' === $url) {
                echo "<div class='woocommerce-error'>" . esc_html(sprintf(
                    /* translators: %s: nombre del medio de pago */
                    __('Khipu no entregó la dirección de pago para %s. Intenta con otro medio de pago.', 'woocommerce-khipu-gateway'),
                    WC_Khipu_Methods::label(static::METHOD_ID)
                )) . '</div>';
                return;
            }
            if (!headers_sent()) {
                wp_redirect($url);
                exit;
            }
            // El tema ya imprimió salida: se redirige desde el navegador.
            echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Continuar con Khipu', 'woocommerce-khipu-gateway') . '</a></p>';
            echo '<script>window.location.href = ' . wp_json_encode(esc_url_raw($url)) . ';</script>';
            return;
        }

        $file = dirname(WC_KHIPU_PLUGIN_FILE) . '/assets/js/khipu-embedded.js';
        wp_enqueue_script(
            'khipu-embedded',
            plugins_url('assets/js/khipu-embedded.js', WC_KHIPU_PLUGIN_FILE),
            array(),
            file_exists($file) ? (string) filemtime($file) : self::PLUGIN_VERSION, // versionado: evita JS viejo en caché
            true
        );

        printf(
            '<div id="khipu-web-root" data-payment-id="%s" data-return-url="%s" data-fallback-url="%s" data-cart-url="%s" data-ajax-url="%s" data-cancel-action="%s" data-order-id="%d" data-order-key="%s" data-nonce="%s" data-kws-url="%s"></div>',
            esc_attr($payment['payment_id']),
            esc_url($this->get_return_url($order)),
            esc_url($payment['url']),
            esc_url(wc_get_cart_url()),
            esc_url(admin_url('admin-ajax.php')),
            esc_attr(self::AJAX_CANCEL_ACTION),
            (int) $order->get_id(),
            esc_attr($order->get_order_key()),
            esc_attr(wp_create_nonce(self::AJAX_CANCEL_ACTION . '_' . $order->get_id())),
            esc_url(self::KWS_SCRIPT_URL)
        );
        echo '<p class="khipu-embedded-msg">' . esc_html__('Abriendo Khipu para completar tu pago...', 'woocommerce-khipu-gateway')
            . ' <a href="' . esc_url($payment['url']) . '">' . esc_html__('Si no se abre, haz clic aquí.', 'woocommerce-khipu-gateway') . '</a></p>';
    }

    /**
     * AJAX (logueado o invitado): solo se llama cuando KWS devuelve ERROR o el
     * comprador abandona. Borra el cobro en Khipu, cancela el pedido y devuelve al carrito.
     */
    public static function ajax_cancel()
    {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce se verifica abajo.
        $key      = isset($_POST['order_key']) ? wc_clean(wp_unslash($_POST['order_key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce    = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if (!$order_id || !wp_verify_nonce($nonce, self::AJAX_CANCEL_ACTION . '_' . $order_id)) {
            wp_send_json_error(array('message' => 'invalid_nonce'), 403);
        }
        $order = wc_get_order($order_id);
        if (!$order || !hash_equals((string) $order->get_order_key(), (string) $key)) {
            wp_send_json_error(array('message' => 'invalid_order'), 403);
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = isset($gateways[$order->get_payment_method()]) ? $gateways[$order->get_payment_method()] : null;
        if (!($gateway instanceof WC_Gateway_khipu_abstract)) {
            wp_send_json_error(array('message' => 'invalid_gateway'), 400);
        }
        if (!$order->has_status(array('pending', 'failed'))) {
            // Ya pagado o procesado: no se toca; el comprador va a la página del pedido.
            wp_send_json_error(array('message' => 'order_not_open', 'redirect' => $gateway->get_return_url($order)));
        }

        $pid = $order->get_meta(self::META_PAYMENT_ID);
        if ($pid) {
            $code = $gateway->delete_payment($pid);
            if (!($code >= 200 && $code < 300) && 404 !== $code) {
                // Khipu no aceptó borrar (p. ej. ya pagado): no se cancela, el webhook decide.
                $order->add_order_note(sprintf('Khipu: no se pudo borrar el cobro %s (HTTP %d); pedido sin cancelar.', $pid, $code));
                wp_send_json_error(array('message' => 'delete_failed', 'redirect' => $gateway->get_return_url($order)));
            }
        }

        foreach (array(self::META_PAYMENT_ID, self::META_PAYMENT_URL, self::META_PAYMENT_OWN_URL, self::META_PAYMENT_TOTAL, self::META_PAYMENT_METHOD) as $meta) {
            $order->delete_meta_data($meta);
        }
        $order->save();
        $order->update_status('cancelled', __('Khipu: el cliente no completó el pago; cobro eliminado en Khipu.', 'woocommerce-khipu-gateway'));

        wp_send_json_success(array('redirect' => add_query_arg('khipu_cancelled', '1', wc_get_cart_url())));
    }

    /**
     * Aviso en el carrito tras cancelar (wc_add_notice no funciona dentro de admin-ajax).
     */
    public static function maybe_show_cancel_notice()
    {
        if (is_admin() || !WC()->session) {
            return;
        }
        if (isset($_GET['khipu_cancelled']) && function_exists('wc_add_notice') && !wc_has_notice(self::cancel_notice_text(), 'error')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wc_add_notice(self::cancel_notice_text(), 'error');
        }
    }

    private static function cancel_notice_text()
    {
        return __('El pago con Khipu no se completó y tu pedido fue cancelado. Puedes intentar de nuevo.', 'woocommerce-khipu-gateway');
    }

    function is_payment_method_available_in_khipu($id)
    {
        $methods = $this->get_payment_methods();
        if (!$methods) {
            return false;
        }
        foreach ($methods->paymentMethods as $paymentMethod) {
            if (strcmp($paymentMethod->id, $id) == 0) {
                return true;
            }
        }
    }
}
