<?php

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

define('WC_KHIPU_PLUGIN_FILE', __FILE__);

/**
 * Plugin Name: Khipu for WooCommerce
 * Plugin URI:  https://docs.khipu.com/payment-solutions/instant-payments/khipu-ecommerce#woocommerce
 * Description: Acepte pagos con transferencia procesadas por khipu
 * Version:     4.3.1
 * Requires PHP: 7.4
 * WC requires at least: 8.5
 * WC tested up to: 10.2
 * Author:      Khipu SpA
 * Author URI:  https://khipu.com/
 * Text Domain: woocommerce-khipu-gateway
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Compatibilidad declarada con el almacenamiento de pedidos en tablas propias (HPOS)
 * y con el checkout de bloques. Todo el acceso a pedidos es por el CRUD de WooCommerce.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * La REST de pasarelas (GET /wc/v3/payment_gateways/{id}) devuelve los ajustes con sus
 * valores a cualquier usuario con manage_woocommerce. La llave secreta firma el webhook y
 * la API key opera la cuenta: no viajan por ahí. (Quien necesite leerlas, las ve en el panel.)
 */
add_filter('woocommerce_rest_prepare_payment_gateway', function ($response, $gateway) {
    if (!($gateway instanceof WC_Gateway_khipu_abstract) || !is_object($response) || !method_exists($response, 'get_data')) {
        return $response;
    }
    $data = $response->get_data();
    foreach (array('secret', 'api_key') as $field) {
        if (isset($data['settings'][$field]['value'])) {
            $data['settings'][$field]['value'] = '';
        }
    }
    $response->set_data($data);
    return $response;
}, 10, 2);

/**
 * La REST de opciones de wc-admin (GET /wc-admin/options) entrega opciones enteras a los
 * administradores: la de Khipu, con la llave y la API key, queda fuera.
 */
/**
 * Como la REST de pasarelas devuelve la llave y la API key vacías, un cliente que lea los
 * ajustes y los reenvíe (PUT) mandaría esos campos vacíos: se conservan los guardados.
 * Este filtro solo lo aplica el guardado por REST; el panel de Khipu sigue pudiendo
 * cambiarlos o vaciarlos.
 */
foreach (array('khipusimplifiedtransfer', 'khipuregulartransfer', 'khipuligopay', 'khipunave') as $woocommerce_gateway_khipu_rest_id) {
    add_filter('woocommerce_gateway_' . $woocommerce_gateway_khipu_rest_id . '_settings_values', function ($settings) {
        if (!is_array($settings)) {
            return $settings;
        }
        $stored = get_option('woocommerce_khipu_settings', array());
        foreach (array('secret', 'api_key') as $field) {
            if ((!isset($settings[$field]) || '' === (string) $settings[$field]) && is_array($stored) && !empty($stored[$field])) {
                $settings[$field] = $stored[$field];
            }
        }
        return $settings;
    });
}
unset($woocommerce_gateway_khipu_rest_id);

add_filter('woocommerce_rest_api_option_permissions', function ($permissions) {
    if (!is_array($permissions)) {
        $permissions = array();
    }
    $permissions['woocommerce_khipu_settings'] = false;
    return $permissions;
});

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
    require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-khipu-nave.php';
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
        $methods[] = 'WC_Gateway_khipu_nave';
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
        $secondary = array(WC_Gateway_khipu_simplified_transfer::ID, WC_Gateway_khipu_ligopay::ID, WC_Gateway_khipu_nave::ID);
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
     * Orden de las filas de Khipu: transferencia simplificada primero, transferencia
     * normal al final, el resto (QR, LigoPay) en medio, y las cuatro contiguas en la
     * posición de la pasarela anfitriona (transferencia normal), que es la única fila
     * que el comercio ve y puede arrastrar en Ajustes → Pagos.
     *
     * No basta con el orden de registro: WooCommerce ordena por la opción
     * `woocommerce_gateway_order` cuando el comercio alguna vez arrastró sus pasarelas, y
     * como las secundarias de Khipu están ocultas del listado del admin, no entran en esa
     * opción y caen al final (índice 999+), o conservan una posición vieja por encima de
     * la anfitriona. Se ordena sobre el registro porque de ahí leen el checkout clásico,
     * Blocks y el listado del admin.
     *
     * Cuidados: WooCommerce vuelve a llamar init() al guardar un panel SIN vaciar el
     * registro, así que se deduplica por id y se conservan las claves originales (si se
     * reindexara, el segundo init() dejaría pasarelas ajenas repetidas). Requiere la
     * acción `wc_payment_gateways_initialized` (WooCommerce 8.5+).
     *
     * @param WC_Payment_Gateways $registry
     */
    function woocommerce_gateway_khipu_sort_rows($registry)
    {
        if (!($registry instanceof WC_Payment_Gateways) || empty($registry->payment_gateways)) {
            return;
        }
        // Una pasarela por id, conservando la primera aparición y su clave.
        $seen = array();
        $gateways = array();
        foreach ($registry->payment_gateways as $key => $gateway) {
            if (!is_object($gateway) || empty($gateway->id) || isset($seen[$gateway->id])) {
                continue;
            }
            $seen[$gateway->id] = true;
            $gateways[$key] = $gateway;
        }

        $khipu = array();
        $anchor = null;
        $first = null;
        foreach ($gateways as $key => $gateway) {
            if (!($gateway instanceof WC_Gateway_khipu_abstract)) {
                continue;
            }
            $class = get_class($gateway);
            if (!defined($class . '::METHOD_ID')) {
                continue; // Una subclase ajena sin medio: se deja donde está.
            }
            $khipu[constant($class . '::METHOD_ID')] = $gateway;
            if (null === $first) {
                $first = $key;
            }
            if (WC_Gateway_khipu_regular_transfer::ID === $gateway->id) {
                $anchor = $key;
            }
        }
        if (null === $anchor) {
            $anchor = $first;
        }

        $ordered = array();
        if (count($khipu) >= 2) {
            $block = array();
            foreach (WC_Khipu_Methods::sort_for_display(array_keys($khipu)) as $method_id) {
                $block[] = $khipu[$method_id];
            }
            foreach ($gateways as $key => $gateway) {
                if ($gateway instanceof WC_Gateway_khipu_abstract && defined(get_class($gateway) . '::METHOD_ID')) {
                    if ($key === $anchor) {
                        $ordered = array_merge($ordered, $block);
                    }
                    continue;
                }
                $ordered[] = $gateway;
            }
        } else {
            $ordered = array_values($gateways);
        }

        // Mismas claves que tenía el registro, en orden ascendente: el segundo init() de
        // WooCommerce vuelve a escribir en esas mismas claves y no duplica nada.
        $keys = array_keys($gateways);
        sort($keys);
        $result = array();
        foreach ($ordered as $i => $gateway) {
            $result[$keys[$i]] = $gateway;
        }
        $registry->payment_gateways = $result;
    }

    add_action('wc_payment_gateways_initialized', 'woocommerce_gateway_khipu_sort_rows');

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
        require_once __DIR__ . '/includes/class-wc-gateway-khipu-blocks-nave.php';
        $registry->register( new WC_Gateway_khipu_blocks_regular_transfer() );
        $registry->register( new WC_Gateway_khipu_blocks_simplified_transfer() );
        $registry->register( new WC_Gateway_khipu_blocks_ligopay() );
        $registry->register( new WC_Gateway_khipu_blocks_nave() );

    }
);
