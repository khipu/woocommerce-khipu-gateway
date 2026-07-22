<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Plugin Name: Khipu for WooCommerce
 * Plugin URI:  https://docs.khipu.com/payment-solutions/instant-payments/khipu-ecommerce#woocommerce
 * Description: Acepte pagos con transferencia procesadas por khipu
 * Version:     4.2.0
 * Author:      Khipu SpA
 * Author URI:  https://khipu.com/
 * Text Domain: woocommerce-khipu-gateway
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

add_action('plugins_loaded', 'woocommerce_gateway_khipu_init', 0);

function woocommerce_gateway_khipu_showWooCommerceNeeded()
{
    woocommerce_gateway_khipu_showMessage("Debes instalar y activar el plugin WooCommerce. El plugin de khipu se deshabilitará hasta que esto este corregido.",
        true);
}

function woocommerce_gateway_khipu_orderReceivedHasSpaces()
{
    woocommerce_gateway_khipu_showMessage("El 'endpoint' de Pedido recibido tiene espacios, debe ser una palabra sin espacios, para corregirlo anda a WooCommerce->Ajustes->Finalizar compra y corrige el valor en el campo 'Pedido recibido'. El plugin de khipu se deshabilitará hasta que esto este corregido.",
        true);
}

function woocommerce_gateway_khipu_showMessage($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    } else {
        echo '<div id="message" class="updated fade">';
    }

    printf('<div class="notice notice-error"><p><strong>%s</strong></p></div>', esc_html( $message ));
}

function woocommerce_gateway_khipu_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'woocommerce_gateway_khipu_showWooCommerceNeeded');
        return;
    }

    $orderReceived =
        isset(WC()->query->query_vars['order-received']) ? WC()->query->query_vars['order-received'] : 'order-received';

    if (strpos($orderReceived, ' ') !== false) {
        add_action('admin_notices', 'woocommerce_gateway_khipu_orderReceivedHasSpaces');
        return;
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    require_once dirname( __FILE__ ) . '/includes/abstract-wc-gateway-khipu.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-khipu-simplified-transfer.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-khipu-regular-transfer.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-khipu-refund-service.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-khipu-refund-admin.php';

    WC_Khipu_Refund_Admin::init();

    function woocommerce_gateway_khipu_add_gateways($methods)
    {
        $methods[] = 'WC_Gateway_khipu_simplified_transfer';
        $methods[] = 'WC_Gateway_khipu_regular_transfer';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_gateway_khipu_add_gateways');

    add_filter('woocommerce_currencies', 'woocommerce_gateway_khipu_add_currencies' );

    function woocommerce_gateway_khipu_add_currencies( $currencies ) {
        $currencies['CLP'] = __('Peso Chileno', 'woocommerce-khipu-gateway');
        $currencies['ARS'] = __('Peso Argentino', 'woocommerce-khipu-gateway');
        $currencies['EUR'] = __('Euro', 'woocommerce-khipu-gateway');
        $currencies['PEN'] = __('Sol Peruano', 'woocommerce-khipu-gateway');
        $currencies['MXN'] = __('Peso Mexicano', 'woocommerce-khipu-gateway');
        return $currencies;
    }

    add_filter('woocommerce_currency_symbol', 'woocommerce_gateway_khipu_add_currencies_symbol', 10, 2);

    function woocommerce_gateway_khipu_add_currencies_symbol( $currency_symbol, $currency ) {
        switch( $currency ) {
            case 'CLP': $currency_symbol = '$'; break;
            case 'ARS': $currency_symbol = '$'; break; // Peso Argentino
            case 'EUR': $currency_symbol = '€'; break; // Euro
            case 'PEN': $currency_symbol = 'S/'; break; // Sol Peruano
            case 'MXN': $currency_symbol = '$'; break; // Peso Mexicano
        }
        return $currency_symbol;
    }
}

add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
        require_once __DIR__ . '/includes/class-wc-gateway-khipu-blocks-simplified-transfer.php';
        require_once __DIR__ . '/includes/class-wc-gateway-khipu-blocks-regular-transfer.php';
        $registry->register( new WC_Gateway_khipu_blocks_regular_transfer() );
        $registry->register( new WC_Gateway_khipu_blocks_simplified_transfer() );

    }
);
