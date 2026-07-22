<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Cliente de los endpoints de reversas de la API de Khipu v3 (Refund payment, Get refund wallet balance).
 * No conoce WordPress ni WooCommerce más allá de wp_remote/WP_Error - solo habla HTTP con Khipu.
 */
class WC_Khipu_Refund_Service
{
    /** @var string */
    private $api_key;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    private function headers()
    {
        return WC_Gateway_khipu_abstract::build_api_headers($this->api_key);
    }

    /**
     * GET /v3/refund-wallet/balance
     *
     * @return object|WP_Error
     */
    public function get_wallet_balance()
    {
        $response = wp_remote_get('https://payment-api.khipu.com/v3/refund-wallet/balance', [
            'headers' => $this->headers(),
            'timeout' => 20,
        ]);

        return $this->parse_response($response);
    }

    /**
     * POST /v3/refunds
     *
     * @param string      $payment_id
     * @param string      $type   'full' o 'partial'
     * @param string|null $amount Requerido si $type === 'partial'; debe omitirse si 'full'.
     * @return object|WP_Error
     */
    public function refund($payment_id, $type, $amount = null)
    {
        $data = [
            'type'       => $type,
            'payment_id' => $payment_id,
        ];

        if ('partial' === $type) {
            $data['amount'] = (string) $amount;
        }

        $response = wp_remote_post('https://payment-api.khipu.com/v3/refunds', [
            'headers' => $this->headers(),
            'body'    => json_encode($data),
            'timeout' => 20,
        ]);

        return $this->parse_response($response);
    }

    /**
     * @param array|WP_Error $response resultado de wp_remote_get/wp_remote_post
     * @return object|WP_Error
     */
    private function parse_response($response)
    {
        if (is_wp_error($response)) {
            return new WP_Error('khipu_comm_error', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        if (200 === $code) {
            if (!is_object($body)) {
                return new WP_Error('khipu_invalid_response', __('Respuesta inválida de Khipu (cuerpo vacío o mal formado).', 'woocommerce-khipu-gateway'));
            }
            return $body;
        }

        return $this->error_from_body($body, $code);
    }

    /**
     * Traduce el body de error de Khipu (formato verificado: {"status","message","errors":[{"field","message"}]})
     * a un WP_Error con un mensaje legible.
     *
     * @param object|null $body
     * @param int         $code
     * @return WP_Error
     */
    private function error_from_body($body, $code)
    {
        $message = ($body && isset($body->message)) ? $body->message : ('Error HTTP ' . $code);

        if ($body && !empty($body->errors) && is_array($body->errors)) {
            $parts = [];
            foreach ($body->errors as $error_item) {
                $parts[] = isset($error_item->message) ? $error_item->message : json_encode($error_item);
            }
            $message = implode(' ', $parts);
        }

        return new WP_Error('khipu_api_error', $message, ['status' => $code]);
    }
}
