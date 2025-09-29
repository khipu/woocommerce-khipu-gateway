<?php

class WC_Gateway_khipu_blocks_simplified_transfer extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    // Debe coincidir EXACTO con $this->id de la opción de pago clásica
    protected $name = 'khipusimplifiedtransfer';

    public function initialize() {
        // Se cargan los ajustes de la opción de pago clásica
        $this->settings = get_option( 'woocommerce_khipusimplifiedtransfer_settings', [] );
    }

    public function is_active() {
        // Si la opción de pago está habilitada en WooCommerce, se activa en Blocks
        return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
    }

    public function get_payment_method_script_handles() {
        // Se registra el JS de Blocks (usa un handle único para este método)
        wp_register_script(
            'khipu-blocks-simplified',
            plugins_url( '../assets/js/khipu-blocks-simplified.js', __FILE__ ),
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element' ],
            '1.0.0',
            true
        );
        return [ 'khipu-blocks-simplified' ];
    }

    public function get_payment_method_script_handles_for_admin() {
        return $this->get_payment_method_script_handles();
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title', 'Khipu' ),
            'description' => $this->get_setting( 'description', '' ),
            'supports'    => [ 'products' ],
        ];
    }
}