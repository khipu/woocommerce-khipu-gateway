<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Plugin Name: WooCommerce khipu Gateway
 * Plugin URI: https://khipu.com/page/woocommerce
 * Description: Acepte pagos con transferencia procesadas por khipu
 * Version: 4.0.2
 * Author: khipu
 * Author URI: https://khipu.com
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

    echo "<p><strong>$message</strong></p></div>";
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

    function woocommerce_gateway_khipu_add_gateways($methods)
    {
        $methods[] = 'WC_Gateway_khipu_simplified_transfer';
        $methods[] = 'WC_Gateway_khipu_regular_transfer';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_gateway_khipu_add_gateways');

    add_filter('woocommerce_currencies', 'woocommerce_gateway_khipu_add_currencies' );

    function woocommerce_gateway_khipu_add_currencies( $currencies ) {
        $currencies['CLP'] = __( 'Peso Chileno', 'woocommerce' );
        $currencies['ARS'] = __( 'Peso Argentino', 'woocommerce' );
        $currencies['EUR'] = __( 'Euro', 'woocommerce' );
        $currencies['PEN'] = __( 'Sol Peruano', 'woocommerce' );
        $currencies['MXN'] = __( 'Peso Mexicano', 'woocommerce' );
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

