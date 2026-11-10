<?php

class WC_Gateway_khipu_blocks_nave extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    // Debe coincidir EXACTO con $this->id de la opción de pago clásica
    protected $name = 'khipunave';

    public function initialize() {
        // Ajustes compartidos del panel único de Khipu
        $this->settings = get_option( WC_Gateway_khipu_abstract::SETTINGS_OPTION, [] );
    }

    public function is_active() {
        // Activa solo si la pasarela clásica quedó disponible (medio marcado,
        // presente en la cuenta y moneda soportada).
        $gateways = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        return isset( $gateways[ WC_Gateway_khipu_nave::ID ] ) && $gateways[ WC_Gateway_khipu_nave::ID ]->is_available();
    }

    public function get_payment_method_script_handles() {
        $file = dirname( __DIR__ ) . '/assets/js/khipu-blocks-nave.js';
        wp_register_script(
            'khipu-blocks-nave',
            plugins_url( '../assets/js/khipu-blocks-nave.js', __FILE__ ),
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element' ],
            file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0',
            true
        );
        return [ 'khipu-blocks-nave' ];
    }

    public function get_payment_method_script_handles_for_admin() {
        return $this->get_payment_method_script_handles();
    }

    public function get_payment_method_data() {
        return [
            'title'       => WC_Khipu_Methods::compose_title( $this->get_setting( 'title', 'Khipu' ), WC_Gateway_khipu_nave::METHOD_ID ),
            'description' => $this->get_setting( 'description', 'Paga con tu banco' ),
            'icon'        => WC_Gateway_khipu_abstract::blocks_icon( WC_Gateway_khipu_nave::ID ),
            'supports'    => [ 'products' ],
        ];
    }
}
