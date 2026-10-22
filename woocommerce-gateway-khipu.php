<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

define('WC_KHIPU_PLUGIN_FILE', __FILE__);

/**
 * Plugin Name: Khipu for WooCommerce
 * Plugin URI:  https://docs.khipu.com/payment-solutions/instant-payments/khipu-ecommerce#woocommerce
 * Description: Acepte pagos con transferencia procesadas por khipu
 * Version:     4.3.0
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
    require_once dirname( __FILE__ ) . '/includes/class-wc-khipu-methods.php';
    require_once dirname( __FILE__ ) . '/includes/abstract-wc-gateway-khipu.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-khipu-simplified-transfer.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-khipu-regular-transfer.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-khipu-ligopay.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-khipu-refund-service.php';
    require_once dirname( __FILE__ ) . '/includes/class-wc-khipu-refund-admin.php';

    WC_Khipu_Refund_Admin::init();

    // Pago embebido: cancelación (solo ERROR o abandono del SDK) y aviso en el carrito.
    add_action('wp_ajax_' . WC_Gateway_khipu_abstract::AJAX_CANCEL_ACTION, array('WC_Gateway_khipu_abstract', 'ajax_cancel'));
    add_action('wp_ajax_nopriv_' . WC_Gateway_khipu_abstract::AJAX_CANCEL_ACTION, array('WC_Gateway_khipu_abstract', 'ajax_cancel'));
    add_action('wp_loaded', array('WC_Gateway_khipu_abstract', 'maybe_show_cancel_notice'));

    function woocommerce_gateway_khipu_add_gateways($methods)
    {
        $methods[] = 'WC_Gateway_khipu_simplified_transfer';
        $methods[] = 'WC_Gateway_khipu_regular_transfer';
        $methods[] = 'WC_Gateway_khipu_ligopay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_gateway_khipu_add_gateways');

    /**
     * Un solo panel de Khipu: en Ajustes → Pagos solo se lista la pasarela
     * anfitriona. WooCommerce dispara esta acción justo antes de armar el
     * listado para que las extensiones lo ajusten. Solo afecta al admin: en el
     * checkout las tres pasarelas siguen registradas y con su propia imagen.
     */
    function woocommerce_gateway_khipu_hide_secondary_gateways()
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return;
        }
        // El arreglo `payment_gateways` está indexado por orden de la pasarela, no por id:
        // se recorre y se busca por ->id.
        $registry = WC()->payment_gateways();
        $secondary = array(WC_Gateway_khipu_simplified_transfer::ID, WC_Gateway_khipu_ligopay::ID);
        foreach ($registry->payment_gateways as $key => $gateway) {
            if (in_array($gateway->id, $secondary, true)) {
                unset($registry->payment_gateways[$key]);
            } elseif (WC_Gateway_khipu_regular_transfer::ID === $gateway->id) {
                // La fila que queda en el listado representa a todo Khipu: lleva el logo
                // de Khipu y no la imagen de "Transferencia normal" (que sigue siendo la
                // del checkout, porque este cambio solo vive en este request del admin).
                $gateway->icon = plugins_url('assets/img/khipu-logo.png', WC_KHIPU_PLUGIN_FILE);
            }
        }
    }

    add_action('woocommerce_admin_field_payment_gateways', 'woocommerce_gateway_khipu_hide_secondary_gateways');

    /**
     * Aviso tras actualizar desde una versión con dos paneles: las credenciales
     * antiguas no se copian, el comercio las vuelve a ingresar en el panel único.
     */
    function woocommerce_gateway_khipu_upgrade_notice()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        if (false === get_option('woocommerce_khipuregulartransfer_settings', false)
            && false === get_option('woocommerce_khipusimplifiedtransfer_settings', false)) {
            return;
        }
        $settings = get_option('woocommerce_khipu_settings', array());
        if (is_array($settings)) {
            foreach (array('api_key', 'secret', 'receiver_id') as $field) {
                if (!empty($settings[$field])) {
                    return;
                }
            }
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('Khipu se actualizó a un solo panel de configuración. Vuelve a ingresar tu Id de cobrador, llave y API Key en WooCommerce → Ajustes → Pagos → Khipu para que Khipu vuelva a aparecer en tu checkout.', 'woocommerce-khipu-gateway'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=khipuregulartransfer')); ?>"><?php esc_html_e('Ir a la configuración de Khipu', 'woocommerce-khipu-gateway'); ?></a>
            </p>
        </div>
        <?php
    }

    add_action('admin_notices', 'woocommerce_gateway_khipu_upgrade_notice');

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
        require_once __DIR__ . '/includes/class-wc-gateway-khipu-blocks-ligopay.php';
        $registry->register( new WC_Gateway_khipu_blocks_regular_transfer() );
        $registry->register( new WC_Gateway_khipu_blocks_simplified_transfer() );
        $registry->register( new WC_Gateway_khipu_blocks_ligopay() );

    }
);
