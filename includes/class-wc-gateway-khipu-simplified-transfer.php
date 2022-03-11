<?php


class WC_Gateway_khipu_simplified_transfer extends WC_Gateway_khipu_abstract
{

    var $notify_url;

    /**
     * Constructor for the gateway.
     *
     */
    public function __construct()
    {
        $this->id = 'khipusimplifiedtransfer';

        $this->has_fields = false;
        $this->method_title = __('khipu transferencia simplificada', 'woocommerce-gateway-khipu');
        $this->method_description = sprintf(__('Las configuraciones generales de khipu se hacen <a href="%s">aquí</a>.', 'woocommerce-gateway-khipu'), admin_url('admin.php?page=wc-settings&tab=checkout&section=khipu'));

        $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));

        // Load the settings and init variables.
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->receiver_id = get_option('woocommerce_gateway_khipu_receiver_id');
        $this->secret = get_option('woocommerce_gateway_khipu_secret');
        $this->icon = $this->get_payment_method_icon('SIMPLIFIED_TRANSFER');
        $this->after_payment_status = get_option('woocommerce_gateway_khipu_after_payment_status');

        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options'));

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
        if (!$this->is_configured()){
            return false;
        }
        if (!$this->is_payment_method_available()) {
            return false;
        }
        if (!$this->is_valid_currency()){
            return false;
        }
        return true;
    }

    function is_payment_method_available()
    {
        return $this->is_payment_method_available_in_khipu('SIMPLIFIED_TRANSFER');
    }

    function is_valid_currency() {
        if (in_array(get_woocommerce_currency(),
            apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')))
        ) {
            return true;
        }
    }

    function is_configured() {
        return $this->secret && $this->receiver_id;
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
                'label' => sprintf(__('Habilita %s', 'woocommerce-gateway-khipu'), $this->method_title),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.',
                    'woocommerce'),
                'default' => __('khipu - Transferencia simplificada', 'woocommerce-gateway-khipu'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.',
                    'woocommerce'),
                'default' => __('Paga con cualquier Banco chileno usando la App Khipu.', 'woocommerce-gateway-khipu')
            ),
        );

    }


    /**
     * Process the payment and return the result
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
        $response = $this->create_payment($order);
        if (method_exists($response, 'getSimplifiedTransferUrl')) {
            wp_redirect($response->getSimplifiedTransferUrl());
        } else {
            echo $response;
        }
    }



}

