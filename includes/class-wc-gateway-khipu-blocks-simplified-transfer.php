<?php

class WC_Gateway_khipu_blocks_simplified_transfer extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    // Debe coincidir EXACTO con $this->id de la opción de pago clásica
    protected $name = 'khipusimplifiedtransfer';

    public function initialize() {
        // Se cargan los ajustes de la opción de pago clásica
        $this->settings = get_option( WC_Gateway_khipu_abstract::SETTINGS_OPTION, [] );
    }

    public function is_active() {
        // Activa solo si la pasarela clásica quedó disponible (medio marcado,
        // presente en la cuenta y moneda soportada).
        $gateways = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        return isset( $gateways[ WC_Gateway_khipu_simplified_transfer::ID ] ) && 'yes' === $gateways[ WC_Gateway_khipu_simplified_transfer::ID ]->enabled;
    }

    public function get_payment_method_script_handles() {
        // Se registra el JS de Blocks (usa un handle único para este método)
        wp_register_script(
            'khipu-blocks-simplified',
            plugins_url( '../assets/js/khipu-blocks-simplified.js', __FILE__ ),
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element' ],
            WC_Gateway_khipu_abstract::PLUGIN_VERSION,
            true
        );
        return [ 'khipu-blocks-simplified' ];
    }

    public function get_payment_method_script_handles_for_admin() {
        return $this->get_payment_method_script_handles();
    }

    public function get_payment_method_data() {
        return [
            'title'       => WC_Khipu_Methods::compose_title( $this->get_setting( 'title', 'Khipu' ), WC_Gateway_khipu_simplified_transfer::METHOD_ID ),
            'description' => $this->get_setting( 'description', 'Paga con tu banco' ),
            'icon'        => WC_Gateway_khipu_abstract::blocks_icon( WC_Gateway_khipu_simplified_transfer::ID ),
            'supports'    => [ 'products' ],
        ];
    }
}