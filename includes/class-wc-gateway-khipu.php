<?php

class WC_Gateway_khipu extends WC_Gateway_khipu_abstract
{

    var $notify_url;

    /**
     * Constructor for the gateway.
     *
     */
    public function __construct()
    {
        $this->id = 'khipu';
        $this->has_fields = false;
        $this->method_title = __('khipu configuración básica', 'woocommerce-gateway-khipu');
        $this->method_description = sprintf( __( 'khipu permite aceptar pagos con transferencia, tarjetas de crédito y débito. <a href="%1$s" target="_blank">Crea un cuenta khipu</a> y <a href="%2$s" target="_blank">obten tus credenciales para WooCommerce</a>.', 'woocommerce-gateway-khipu' ), 'https://khipu.com/page/precios', 'https://khipu.com/merchant/profile' );


        // Load the settings and init variables.
        $this->init_form_fields();
        $this->init_settings();
        $this->title = __('khipu configuración básica', 'woocommerce-gateway-khipu');
        $this->receiver_id = $this->get_option('receiver_id');
        $this->secret = $this->get_option('secret');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options'));

        $this->enabled = false;
    }

    function is_valid_for_use()
    {
        return true;
    }


    /**
     * Admin Panel Options
     */
    public function admin_options()
    {
        parent::admin_options();
    }

    function process_admin_options() {
        parent::process_admin_options();
        $this->init_settings();
        $post_data = $this->get_post_data();
        update_option('woocommerce_gateway_khipu_receiver_id', $post_data['woocommerce_khipu_receiver_id']);
        update_option('woocommerce_gateway_khipu_secret', $post_data['woocommerce_khipu_secret']);
        update_option('woocommerce_gateway_khipu_payment_methods', '');
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'receiver_id' => array(
                'title' => __('Id de cobrador', 'woocommerce-gateway-khipu'),
                'type' => 'text',
                'description' => __('Ingrese su Id de cobrador. Se obtiene en https://khipu.com/merchant/profile',
                    'woocommerce-gateway-khipu'),
                'default' => '',
                'desc_tip' => true
            ),
            'secret' => array(
                'title' => __('Llave', 'woocommerce-gateway-khipu'),
                'type' => 'text',
                'description' => __('Ingrese su llave secreta. Se obtiene en https://khipu.com/merchant/profile',
                    'woocommerce-gateway-khipu'),
                'default' => '',
                'desc_tip' => true
            )
        );

    }

}
