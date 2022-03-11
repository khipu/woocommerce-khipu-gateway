<?php

abstract class WC_Gateway_khipu_abstract extends WC_Payment_Gateway
{


    function comm_error($exception = null)
    {
        if (!$exception) {
            $msg = __('Error de comunicación con khipu, por favor intente nuevamente más tarde.', 'woocommerce-gateway-khipu');
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
                    $item_names[] = $item['name'] . ' x ' . $item['qty'];
                }
            }
        }

        $configuration = new Khipu\Configuration();
        $configuration->setSecret($this->secret);
        $configuration->setReceiverId($this->receiver_id);
        $configuration->setPlatform('woocommerce-khipu', '3.1');
//        $configuration->setDebug(true);

        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);


        $options = array(
            'transaction_id' => ltrim($order->get_order_number(), '#')
        , 'custom' => serialize(array($order_id, $order->get_order_key()))
        , 'body' => implode(', ', $item_names)
        , 'return_url' => $this->get_return_url($order)
        , 'cancel_url' => $order->get_checkout_payment_url()
        , 'notify_url' => $this->notify_url
        , 'notify_api_version' => '1.3'
        , 'payer_email' => $order->get_billing_email()
        );

        $held_duration = get_option('woocommerce_hold_stock_minutes');

        if ($held_duration > 1 && 'yes' == get_option('woocommerce_manage_stock')) {
            $interval = new DateInterval('PT' . $held_duration . 'M');
            $timeout = new DateTime('now');
            $timeout->add($interval);
            $options['expires_date'] = $timeout;
        }

        try {
            $createPaymentResponse = $payments->paymentsPost(
                'Orden ' . $order->get_order_number() . ' - ' . get_bloginfo('name')
                , $order->get_currency()
                , number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', '')
                , $options
            );
        } catch (\Khipu\ApiException $e) {
            return $this->comm_error($e->getResponseObject());
        }
        return $createPaymentResponse;
    }


    private function get_payment_response($notification_token)
    {
        $configuration = new Khipu\Configuration();
        $configuration->setSecret($this->secret);
        $configuration->setReceiverId($this->receiver_id);
        $configuration->setPlatform('woocommerce-khipu', '3.1');

        $client = new Khipu\ApiClient($configuration);
        $payments = new Khipu\Client\PaymentsApi($client);
        try {
            $paymentsResponse = $payments->paymentsGet($notification_token);
        } catch (\Khipu\ApiException $e) {
            return null;
        }
        return $paymentsResponse;
    }

    /**
     * Check for Khipu IPN Response
     */
    function check_ipn_response()
    {
        @ob_clean();
        http_response_code(400);

        if (empty($_POST)) {
            exit("No POST data");
        }
        if (empty($_POST['api_version']) || $_POST['api_version'] != '1.3') {
            exit("Invalid API version");
        }
        if (empty($_POST['notification_token'])) {
            exit("Missing notification token");
        }

        $paymentResponse = $this->get_payment_response($_POST['notification_token']);
        if (!$paymentResponse) {
            exit("No payment response for token");
        }

        $order = $this->get_order($paymentResponse);
        if (!$order) {
            exit("No order for payment response");
        }

        if ($paymentResponse->getStatus() != 'done') {
            exit("Payment status not done");
        }

        if ($paymentResponse->getAmount() != floatval(number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', ''))) {
            exit("Wrong amount. Expected: " . floatval(number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', '')) . ' got ' . $paymentResponse->getAmount());
        }

        if ($order) {
            http_response_code(200);
            if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                exit('Notification already processed');
            }
            $order->add_order_note(sprintf(__('Pago verificado con código único de verificación khipu %s', 'woocommerce-gateway-khipu'), $paymentResponse->getPaymentId()));
            $order->payment_complete($paymentResponse->getPaymentId());
            $defined_status = get_option('woocommerce_gateway_khipu_after_payment_status');
            if ($defined_status) {
                $order->update_status($defined_status);
            }
            exit('Notification processed');
        }

    }

    /**
     * get_khipu_order function.
     */
    private function get_order(Khipu\Model\PaymentsResponse $paymentResponse)
    {
        $custom = maybe_unserialize($paymentResponse->getCustom());

        // Backwards comp for IPN requests
        if (is_numeric($custom)) {
            $order_id = (int)$custom;
            $order_key = $paymentResponse->getTransactionId();
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

        return $order;
    }

    /**
     * Admin Panel Options
     */
    public function admin_options()
    {
        if ($this->is_valid_for_use()) {
            parent::admin_options();
        } elseif (!$this->is_configured()) {
            echo '<h2>' . esc_html($this->get_method_title());
            wc_back_link(__('Return to payments', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            echo '</h2>';

            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e('khipu no está configurado aún', 'woocommerce-gateway-khipu'); ?></strong>: <?php echo wp_kses_post(wpautop($this->get_method_description())); ?>
                </p>
            </div>
            <?php
        } elseif (!$this->is_valid_currency()) {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e('Medio de pago desactivado', 'woocommerce-gateway-khipu'); ?></strong>: <?php esc_html_e('Este medio de pago no soporta la moneda de la tienda.', 'woocommerce-gateway-khipu'); ?>
                </p>
            </div>
            <?php
        } elseif (!$this->is_payment_method_available()) {
            echo '<h2>' . esc_html($this->get_method_title());
            wc_back_link(__('Return to payments', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            echo '</h2>';

            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e('Medio de pago desactivado', 'woocommerce-gateway-khipu'); ?></strong>: <?php echo esc_html_e('Debes activar este medio de pago en khipu.com', 'woocommerce-gateway-khipu'); ?>
                </p>
            </div>
            <?php
        }

    }

    function get_payment_methods()
    {
        $response = get_option('woocommerce_gateway_khipu_payment_methods');
        if (!$this->receiver_id || !$this->secret) {
            return null;
        }

        if (!$response || !$response['ts'] || (time() - (int)$response['ts']) > 30) {
            unset($response);
            $configuration = new Khipu\Configuration();
            $configuration->setSecret($this->secret);
            $configuration->setReceiverId($this->receiver_id);
            $configuration->setPlatform('woocommerce-khipu', '3.0');

            $client = new Khipu\ApiClient($configuration);
            $paymentMethodsApi = new Khipu\Client\PaymentMethodsApi($client);

            try {
                $paymentsMethodsResponse = $paymentMethodsApi->merchantsIdPaymentMethodsGet($this->receiver_id);
                $response['methods'] = $paymentsMethodsResponse;
            } catch (\Khipu\ApiException $e) {
            }
            $response['ts'] = time();
            update_option('woocommerce_gateway_khipu_payment_methods', $response);
        }
        return $response['methods'];
    }

    function get_payment_method_icon($id)
    {
        $methods = $this->get_payment_methods();
        if (!$methods) {
            return '';
        }
        foreach ($methods->getPaymentMethods() as $paymentMethod) {
            if (strcmp($paymentMethod->getId(), $id) == 0) {
                return $paymentMethod->getLogoUrl();
            }
        }
    }


    function is_payment_method_available_in_khipu($id)
    {
        $methods = $this->get_payment_methods();
        if (!$methods) {
            return false;
        }
        foreach ($methods->getPaymentMethods() as $paymentMethod) {
            if (strcmp($paymentMethod->getId(), $id) == 0) {
                return true;
            }
        }
    }


}
