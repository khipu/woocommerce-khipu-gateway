<?php

abstract class WC_Gateway_khipu_abstract extends WC_Payment_Gateway
{
    const PLUGIN_VERSION = '4.0.2';
    const API_VERSION = '3.0';

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
                    $item_names[] = $item['qty'] . ' x ' . $item['name'];
                }
            }
        }

        $headers = [
            'x-api-key' => $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'khipu-api-php-client/' . self::API_VERSION . '|woocommerce-khipu/' . self::PLUGIN_VERSION
        ];
        $payments_url = 'https://payment-api.khipu.com/v3/payments';
        $cartProductsKhipu = '';
        foreach ($item_names as $product) {
            $cartProductsKhipu .= "\n\r" . $product;
        }

        $data = [
            'subject' => 'Orden ' . $order->get_order_number() . ' - ' . get_bloginfo('name'),
            'currency' => $order->get_currency(),
            'amount' => (float) number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', ''),
            'transaction_id' => ltrim($order->get_order_number(), '#'),
            'custom' => serialize([$order_id, $order->get_order_key()]),
            'body' => 'Productos incluidos en la compra:' . $cartProductsKhipu,
            'return_url' => $this->get_return_url($order),
            'cancel_url' => $order->get_checkout_payment_url(),
            'notify_url' => $this->notify_url,
            'notify_api_version' => '3.0',
            'payer_email' => $order->get_billing_email()
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
            'body' => json_encode($data),
            'method' => 'POST'
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return $this->comm_error($error_message);
        }

        $response_body = wp_remote_retrieve_body($response);
        $createPaymentResponse = json_decode($response_body);

        if (empty($createPaymentResponse) || isset($createPaymentResponse->error)) {
            $error_message = json_encode($createPaymentResponse->error);
            return "<div class='woocommerce-error'>Error ab: No se pudo crear el pago. " . $error_message . "</div>";
        }

        return $createPaymentResponse;
    }

    private function get_payment_response($payment_id)
    {
        $headers = [
            'x-api-key' => $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'khipu-api-php-client/' . self::API_VERSION . '|woocommerce-khipu/' . self::PLUGIN_VERSION
        ];

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

        if ($paymentResponse->amount != floatval(number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', ''))) {
            http_response_code(401);
            exit("Wrong amount. Expected: " . floatval(number_format($order->get_total(), absint(get_option('woocommerce_price_num_decimals', 2)), '.', '')) . ' got ' . $paymentResponse->amount);
        }

        if ($order) {
            http_response_code(200);
            if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                exit('Notification already processed');
            }
            $order->add_order_note(sprintf(__('Pago verificado con código único de verificación khipu %s', 'woocommerce-gateway-khipu'), $paymentResponse->payment_id));
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
        $custom = maybe_unserialize($paymentResponse->custom);

        // Backwards comp for IPN requests
        if (is_numeric($custom)) {
            $order_id = (int)$custom;
            $order_key = $paymentResponse->transaction_id;
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
        parent::admin_options();
        if (!$this->is_valid_currency()) {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e('Medio de pago desactivado', 'woocommerce-gateway-khipu'); ?></strong>: <?php esc_html_e('Este medio de pago no soporta la moneda de la tienda.', 'woocommerce-gateway-khipu'); ?>
                </p>
            </div>
            <?php
        }
    }

    function get_payment_methods()
    {
        $response = get_option('woocommerce_gateway_khipu_payment_methods');
        if (!$this->receiver_id || !$this->api_key) {
            return null;
        }

        if (!$response || !$response['ts'] || (time() - (int)$response['ts']) > 30) {
            unset($response);
            $payments_url = 'https://payment-api.khipu.com/v3/merchants/' . $this->receiver_id . '/paymentMethods';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $payments_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-api-key: ' . $this->api_key,
                'User-Agent: khipu-api-php-client/' . self::API_VERSION . '|woocommerce-khipu/' . self::PLUGIN_VERSION,
                'Content-Type: application/json'
            ]);

            $curl_response = curl_exec($ch);

            if (curl_errno($ch)) {
                error_log('cURL error: ' . curl_error($ch));
                curl_close($ch);
                return null;
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $paymentsMethodsResponse = json_decode($curl_response);
                $response['methods'] = $paymentsMethodsResponse;
                $response['ts'] = time();
                update_option('woocommerce_gateway_khipu_payment_methods', $response);
            } else {
                error_log('Error en la solicitud a Khipu API: HTTP code ' . $http_code);
                return null;
            }
        }
        return $response['methods'];
    }


    function get_payment_method_icon($id)
    {
        $methods = $this->get_payment_methods();
        if (!$methods) {
            return '';
        }
        foreach ($methods->paymentMethods as $paymentMethod) {
            if (strcmp($paymentMethod->id, $id) == 0) {
                return $paymentMethod->logo_url;
            }
        }
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
