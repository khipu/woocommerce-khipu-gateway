<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Plugin Name: WooCommerce khipubacs
 * Plugin URI: https://khipu.com
 * Description: khipu powered direct transfer payment gateway for woocommerce
 * Version: 2.2
 * Author: khipu
 * Author URI: https://khipu.com
 */

add_action('plugins_loaded', 'woocommerce_khipubacs_init', 0);


function woocommerce_khipubacs_showWooCommerceNeeded()
{
    woocommerce_khipubacs_showMessage("Debes instalar y activar el plugin WooCommerce. El plugin de khipu se deshabilitará hasta que esto este corregido.", true);
}

function woocommerce_khipubacs_orderReceivedHasSpaces()
{
    woocommerce_khipu_showMessage("El 'endpoint' de Pedido recibido tiene espacios, debe ser una palabra sin espacios, para corregirlo anda a WooCommerce->Ajustes->Finalizar compra y corrige el valor en el campo 'Pedido recibido'. El plugin de khipu se deshabilitará hasta que esto este corregido.", true);
}


function woocommerce_khipubacs_showMessage($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    }
    else {
        echo '<div id="message" class="updated fade">';
    }

    echo "<p><strong>$message</strong></p></div>";
}


function woocommerce_khipubacs_init()
{

    require_once "lib/lib-khipu/src/Khipu.php";

    $orderReceived = isset( WC()->query->query_vars[ 'order-received' ] ) ? WC()->query->query_vars[ 'order-received' ] : 'order-received';


    if (!class_exists('WC_Payment_Gateway')) {

        add_action('admin_notices', 'woocommerce_khipubacs_showWooCommerceNeeded');

    } else if (strpos($orderReceived, ' ') !== false){
        add_action('admin_notices', 'woocommerce_khipubacs_orderReceivedHasSpaces');
    } else {

        class WC_Gateway_khipubacs extends WC_Payment_Gateway
        {

            var $notify_url;

            /**
             * Constructor for the gateway.
             *
             */
            public function __construct()
            {
                $this->id = 'khipubacs';
                //$this->icon = plugins_url('images/buttons/50x25.png', __FILE__);
                $this->has_fields = false;
                $this->method_title = __('Trasferencia normal', 'woocommerce');
                $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));

                // Load the settings and init variables.
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->receiver_id = $this->get_option('receiver_id');
                $this->secret = $this->get_option('secret');

                // Actions
                add_action('valid-' . $this->id . '-ipn-request', array($this, 'successful_request'));
                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                // Payment listener/API hook
                add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_ipn_response'));

                if (!$this->is_valid_for_use()) {
                    $this->enabled = false;
                }
            }


            /**
             * Check if this gateway is enabled and available in the user's country
             */
            function is_valid_for_use()
            {
                if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')))) {
                    return false;
                }
                return true;
            }

            /**
             * Admin Panel Options
             */
            public function admin_options()
            {
                ?>
                <h3><?php _e('khipu', 'woocommerce'); ?></h3>
                <p><?php _e('khipu informa al pagador de la transferencia que debe realizar y luego concilia y notifica al comercio del pago realizado.', 'woocommerce'); ?></p>

                <?php if ($this->is_valid_for_use()) : ?>
                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table><!--/.form-table-->

            <?php else : ?>
                <div class="inline error">
                    <p>
                        <strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('khipu does not support your store currency.', 'woocommerce'); ?>
                    </p>
                </div>
            <?php
            endif;
            }


            /**
             * Initialise Gateway Settings Form Fields
             */
            function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable khipu', 'woocommerce'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('Transferencia normal', 'woocommerce'),
                        'desc_tip' => true
                    ),
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                        'default' => __('Debes ingresar el rut de la cuenta corriente o vista con'
                                        .'que pagarás, luego se te entregarán todos los datos'
                                        .'para que puedas realizar la transferencia desde el'
                                        .'portal web o móvil de tu banco. Debes tener cuidado, el'
                                        .'monto a transferir debe ser el que se te ha informado'
                                        .'que realices, así el pago se procesará con éxito.')
                    ),
                    'receiver_id' => array(
                        'title' => __('Id de cobrador', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Ingrese su Id de cobrador. Se obtiene en https://khipu.com/merchant/profile', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true
                    ),
                    'secret' => array(
                        'title' => __('Llave', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Ingrese su llave secreta. Se obtiene en https://khipu.com/merchant/profile', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true
                    )
                );

            }


            function comm_error()
            {
                $msg = __('Error de comunicación con khipu, por favor intente nuevamente más tarde.');
                return "<div class='woocommerce-error'>$msg</div>";
            }

            /**
             * Create the payment on khipu and try to start the app.
             */
            function generate_khipubacs_submit_button($order_id)
            {

                $order = new WC_Order($order_id);

                $Khipu = new Khipu();
                $Khipu->authenticate($this->receiver_id, $this->secret);
                $Khipu->setAgent('woocommerce-khipubacs-2.2;;'.site_url().';;'.bloginfo('name'));
                $create_page_service = $Khipu->loadService('CreatePaymentURL');

                $item_names = array();

                if (sizeof($order->get_items()) > 0) {
                    foreach ($order->get_items() as $item) {
                        if ($item['qty']) {
                            $item_names[] = $item['name'] . ' x ' . $item['qty'];
                        }
                    }
                }

                $create_page_service->setParameter('subject', 'Orden ' . $order->get_order_number() . ' - ' . get_bloginfo('name'));
                $create_page_service->setParameter('body', implode(', ', $item_names));
                $create_page_service->setParameter('amount', number_format($order->get_total(), 0, ',', ''));
                $create_page_service->setParameter('transaction_id', ltrim($order->get_order_number(), '#'));
                $create_page_service->setParameter('custom', serialize(array($order_id, $order->order_key)));
                $create_page_service->setParameter('payer_email', $order->billing_email);
                $create_page_service->setParameter('notify_url', $this->notify_url);
                $create_page_service->setParameter('bank_id', '');
                $create_page_service->setParameter('return_url', $this->get_return_url($order));

                $json_string = $create_page_service->createUrl();
                $response = json_decode($json_string);

                if (!$response) {
                    return $this->comm_error();
                }

                $manualUrl = 'manual-url';

                wp_redirect($response->$manualUrl);
                return;

            }

            /**
             * Process the payment and return the result
             */
            function process_payment($order_id){
                $order = new WC_Order($order_id);
                return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
            }

            /**
             * Output for the order received page.
             */
            function receipt_page($order)
            {
                echo $this->generate_khipubacs_submit_button($order);
            }

            /**
             * Get order from Khipu IPN
             **/
            function get_order_from_ipn()
            {

                $_POST = array_map('stripslashes', $_POST);

                $api_version = $_POST['api_version'];


                if($api_version == '1.2') {
                    return $this->get_order_from_ipn_1_2();
                } else if($api_version == '1.3') {
                    return $this->get_order_from_ipn_1_3();
                }
                return false;

            }

            /**
             * Get order from Khipu IPN API 1.2
             **/
            function get_order_from_ipn_1_2() {
                $Khipu = new Khipu();
                $Khipu->authenticate($this->receiver_id, $this->secret);
                $Khipu->setAgent('woocommerce-khipubacs-2.2;;'.site_url().';;'.bloginfo('name'));
                $service = $Khipu->loadService('VerifyPaymentNotification');
                $service->setDataFromPost();
                if ($_POST['receiver_id'] != $this->receiver_id) {
                    return false;
                }

                $verify = $service->verify();
                if($verify['response'] == 'VERIFIED'){
                    return $this->get_khipubacs_order($_POST['custom'], $_POST['transaction_id']);
                }
                return false;
            }

            /**
             * Get order from Khipu IPN API 1.3
             **/
            function get_order_from_ipn_1_3() {
                $Khipu = new Khipu();
                $Khipu->authenticate($this->receiver_id, $this->secret);
                $Khipu->setAgent('woocommerce-khipubacs-2.2;;'.site_url().';;'.bloginfo('name'));
                $service = $Khipu->loadService('GetPaymentNotification');
                $service->setDataFromPost();
                $response = json_decode($service->consult());
                if ($response->receiver_id != $this->receiver_id) {
                    return false;
                }
                $order = $this->get_khipubacs_order($response->custom, $response->transaction_id);

                if($order) {
                    return $order;
                }
                return false;

            }

            /**
             * Check for Khipu IPN Response
             */
            function check_ipn_response()
            {
                @ob_clean();

                if(empty($_POST) || empty($_POST['api_version'])){
                    wp_die("khipu notification validation invalid");
                }


                $order = $this->get_order_from_ipn();

                if($order) {
                    header('HTTP/1.1 200 OK');
                    do_action("valid-khipubacs-ipn-request", $order);
                    return;
                }

            }

            /**
             * Successful Payment
             */
            function successful_request($order)
            {
                if ($order->status == 'completed') {
                    exit;
                }
                $order->add_order_note(__('Pago con khipubacs verificado', 'woocommerce'));
                $order->payment_complete();
            }




            /**
             * get_khipu_order function.
             */
            function get_khipubacs_order($custom, $transaction_id)
            {
                $custom = maybe_unserialize($custom);

                // Backwards comp for IPN requests
                if (is_numeric($custom)) {
                    $order_id = (int)$custom;
                    $order_key = $transaction_id;
                } elseif (is_string($custom)) {
                    $order_id = (int)str_replace($this->invoice_prefix, '', $custom);
                    $order_key = $custom;
                } else {
                    list($order_id, $order_key) = $custom;
                }

                $order = new WC_Order($order_id);

                if (!isset($order->id)) {
                    $order_id = woocommerce_get_order_id_by_order_key($order_key);
                    $order = new WC_Order($order_id);
                }

                // Validate key
                if ($order->order_key !== $order_key) {
                    if ($this->debug == 'yes') {
                        $this->log->add('paypal', 'Error: Order Key does not match invoice.');
                    }
                    exit;
                }

                return $order;
            }

        }

        /**
         * Add the Gateway to WooCommerce
         **/
        function woocommerce_add_khipubacs_gateway($methods)
        {
            $methods[] = 'WC_Gateway_khipubacs';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'woocommerce_add_khipubacs_gateway');

        function woocommerce_khipubacs_add_clp_currency($currencies)
        {
            $currencies["CLP"] = __('Pesos Chilenos');
            return $currencies;
        }

        function woocommerce_khipubacs_add_clp_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'CLP':
                    $currency_symbol = '$';
                    break;
            }
            return $currency_symbol;
        }

        add_filter('woocommerce_currencies', 'woocommerce_khipubacs_add_clp_currency', 10, 1);
        add_filter('woocommerce_currency_symbol', 'woocommerce_khipubacs_add_clp_currency_symbol', 10, 2);

    }

}
