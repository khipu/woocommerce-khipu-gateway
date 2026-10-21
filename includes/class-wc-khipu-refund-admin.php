<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UI de administración para reversar dinero de un pedido pagado con Khipu.
 */
class WC_Khipu_Refund_Admin
{
    const ELIGIBLE_GATEWAYS = [
        WC_Gateway_khipu_simplified_transfer::ID,
        WC_Gateway_khipu_regular_transfer::ID,
        WC_Gateway_khipu_ligopay::ID,
    ];

    public static function init()
    {
        add_filter('woocommerce_admin_order_actions', [__CLASS__, 'add_order_action'], 10, 2);
        add_action('admin_post_khipu_refund', [__CLASS__, 'handle_request']);
        add_action('admin_menu', [__CLASS__, 'register_hidden_page']);
        add_action('admin_notices', [__CLASS__, 'show_admin_notice']);
        add_action('admin_init', [__CLASS__, 'maybe_process_post']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_css']);
    }

    /**
     * Da un ícono (dashicon "undo") al botón de acción "Reversa Khipu" en la
     * lista de pedidos. WooCommerce renderiza estas acciones como botones
     * solo-ícono; sin esto el botón sale vacío (el texto no cabe en la columna
     * angosta y no hay ícono). Se inyecta en todo el admin porque el selector
     * solo afecta a una clase que únicamente existe en la lista de pedidos —
     * así evitamos depender del nombre exacto del hook de esa pantalla (que
     * varía entre HPOS y legacy).
     */
    public static function enqueue_admin_css()
    {
        // El botón solo existe en la lista de pedidos. Como el ícono va embebido
        // (data URI, ~50KB) y no queremos inflar todas las pantallas del admin,
        // solo inyectamos el CSS ahí. Si la pantalla es DESCONOCIDA (get_current_screen
        // nulo), lo inyectamos igual (comportamiento seguro).
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !in_array($screen->id, ['woocommerce_page_wc-orders', 'edit-shop_order'], true)) {
            return;
        }

        // Botón SIN borde ni caja (solo el ícono), chico, con el texto oculto
        // (WooCommerce renderiza estas acciones como botones solo-ícono). CSS
        // embebido en 'common' porque el plugin es un symlink y Apache no sirve
        // estáticos a través de symlinks.
        $base = 'a.wc-action-button.khipu_refund{'
            . 'border:0!important;background-color:transparent!important;box-shadow:none!important;'
            . 'width:32px!important;min-width:32px!important;height:32px!important;padding:0!important;'
            . 'font-size:0!important;color:transparent!important;overflow:hidden!important}';

        // Ícono: PNG del repo (assets/img/refund.png, 80x80 @2x), embebido inline
        // como data URI (no se puede servir por URL a través del symlink). Se
        // muestra chico (16px) — nítido por venir a doble resolución. Si el PNG no
        // está disponible, cae al ícono Unicode ↺.
        $png_path = dirname(dirname(__FILE__)) . '/assets/img/refund.png';
        $png = is_readable($png_path) ? file_get_contents($png_path) : '';

        if ($png) {
            $data = 'data:image/png;base64,' . base64_encode($png);
            $css = $base . 'a.wc-action-button.khipu_refund{background-image:url("' . $data . '")!important;background-repeat:no-repeat!important;background-position:center!important;background-size:30px 30px!important}';
        } else {
            $css = $base . 'a.wc-action-button.khipu_refund::after{content:"\21BA"!important;display:block;color:#4F6BEA;font-size:28px!important;line-height:32px;text-align:center;font-weight:700}';
        }

        wp_add_inline_style('common', $css);
    }

    /**
     * Registra una página de administración oculta (sin entrada de menú) que
     * sirve la pantalla de confirmación de reversa con el chrome estándar
     * de wp-admin (header, menú, CSS), a diferencia de admin-post.php.
     */
    public static function register_hidden_page()
    {
        add_submenu_page(
            null, // no parent slug => hidden from any menu, but still a real registered admin page
            __('Reversa Khipu', 'woocommerce-khipu-gateway'),
            '',
            'manage_woocommerce',
            'khipu-refund',
            [__CLASS__, 'handle_request']
        );
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    public static function is_order_eligible($order)
    {
        if (!$order) {
            return false;
        }

        if (!in_array($order->get_payment_method(), self::ELIGIBLE_GATEWAYS, true)) {
            return false;
        }

        if ('' === $order->get_transaction_id()) {
            return false;
        }

        $remaining = $order->get_meta('_khipu_refund_remaining');
        if ('' !== $remaining && (float) $remaining <= 0) {
            return false;
        }

        return true;
    }

    /**
     * @param array    $actions
     * @param WC_Order $order
     * @return array
     */
    public static function add_order_action($actions, $order)
    {
        if (!self::is_order_eligible($order)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?page=khipu-refund&order_id=' . $order->get_id()),
            'khipu_refund_' . $order->get_id()
        );

        $actions['khipu_refund'] = [
            'url'    => $url,
            // El "name" es el tooltip (title/aria-label) del botón en la lista.
            'name'   => __('Reversa Khipu', 'woocommerce-khipu-gateway'),
            'action' => 'khipu_refund',
        ];

        return $actions;
    }

    /**
     * Intercepta el POST de confirmación de reversa en admin_init — antes de
     * que wp-admin emita cualquier output — para que wp_safe_redirect() en
     * redirect_with_notice() no falle por "headers already sent" (admin.php
     * siempre requiere admin-header.php antes de invocar el callback de
     * add_submenu_page(), que es donde antes se procesaba el POST).
     */
    public static function maybe_process_post()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }
        if (!isset($_POST['page']) || 'khipu-refund' !== $_POST['page']) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permiso para hacer esto.', 'woocommerce-khipu-gateway'));
        }

        $order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        check_admin_referer('khipu_refund_' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order || !self::is_order_eligible($order)) {
            wp_die(__('Pedido inválido para reversa Khipu.', 'woocommerce-khipu-gateway'));
        }

        self::process_refund($order); // termina con redirect_with_notice() -> wp_safe_redirect() + exit
    }

    public static function handle_request()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('No tienes permiso para hacer esto.', 'woocommerce-khipu-gateway'));
        }

        $order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
        check_admin_referer('khipu_refund_' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order || !self::is_order_eligible($order)) {
            wp_die(__('Pedido inválido para reversa Khipu.', 'woocommerce-khipu-gateway'));
        }

        self::render_form($order);
    }

    /**
     * @param WC_Order $order
     * @return string api key compartida de la tienda (una sola cuenta de cobro; el pedido no la cambia), o '' si no hay
     */
    public static function get_api_key_for_order($order)
    {
        $settings = get_option(WC_Gateway_khipu_abstract::SETTINGS_OPTION, []);
        return isset($settings['api_key']) ? $settings['api_key'] : '';
    }

    /**
     * @param WC_Order $order
     * @return WC_Khipu_Refund_Service
     */
    public static function get_service_for_order($order)
    {
        return new WC_Khipu_Refund_Service(self::get_api_key_for_order($order));
    }

    /**
     * Refleja una reversa de Khipu en WooCommerce según el ajuste
     * `after_refund_status` de la pasarela.
     *
     * - **"— No cambiar —"** (`''`): no hace nada; la reversa queda solo en la
     *   meta `_khipu_refund_remaining` y en la nota del pedido (comportamiento
     *   previo).
     * - **"Reembolsado"** (`wc-refunded`, por defecto): registra la reversa como
     *   un **reembolso nativo de WooCommerce** (`wc_create_refund` con
     *   `refund_payment => false`) por el monto reversado en esta operación —
     *   así los reembolsos **parciales y totales** se reflejan en la columna
     *   Total (`total - reembolsado`) y en los reportes. Cuando el pago queda
     *   sin saldo reversable, además pasa el pedido a estado "Reembolsado".
     *
     * **Sin riesgo de doble reembolso:** `refund_payment => false` crea solo el
     * asiento contable, NO llama a ninguna pasarela ni mueve plata (la plata ya
     * volvió vía el `POST /v3/refunds` de Khipu). Además el gateway Khipu no
     * declara `supports('refunds')` ni tiene `process_refund($order_id,...)`, así
     * que el sistema de reembolsos nativo de WooCommerce nunca dispara Khipu.
     *
     * @param WC_Order     $order
     * @param string|float $refunded_amount Monto reversado en esta operación (0 si no aplica, ej. auto-sanado).
     * @param string|float $remaining       Saldo reversable restante del pago.
     */
    private static function reflect_refund_in_woocommerce($order, $refunded_amount, $remaining)
    {
        $status = self::configured_after_refund_status($order->get_payment_method());

        if ('wc-refunded' !== $status) {
            return; // "— No cambiar —": solo meta + nota (comportamiento previo).
        }

        // Reembolso nativo (solo contable) por el monto reversado en esta operación.
        $amount = (float) $refunded_amount;
        if ($amount > 0) {
            $refund = wc_create_refund(array(
                'order_id'       => $order->get_id(),
                'amount'         => $amount,
                'reason'         => __('Reversa Khipu', 'woocommerce-khipu-gateway'),
                'refund_payment' => false,
            ));
            if (is_wp_error($refund)) {
                $order->add_order_note(sprintf(
                    /* translators: %s: mensaje de error */
                    __('No se pudo registrar el reembolso nativo de WooCommerce: %s', 'woocommerce-khipu-gateway'),
                    $refund->get_error_message()
                ));
            }
        }

        // Sin saldo reversable -> marcar el pedido como "Reembolsado". Si ya
        // reflejamos todo el monto arriba, wc_order_fully_refunded (disparado por
        // este cambio de estado) no duplicará el reembolso (su max_refund sería 0).
        if ((float) $remaining <= 0) {
            $order = wc_get_order($order->get_id()); // recargar tras wc_create_refund
            if ($order && 'refunded' !== $order->get_status()) {
                $order->update_status('wc-refunded', __('Pago completamente reversado con Khipu (sin saldo por reversar).', 'woocommerce-khipu-gateway'));
            }
        }
    }

    /**
     * Decimales según la moneda (Khipu devuelve montos con 4 decimales fijos).
     * CLP/COP no usan decimales; CLF usa 4; el resto, 2.
     *
     * @param string $currency
     * @return int
     */
    private static function currency_decimals($currency)
    {
        if (in_array($currency, ['CLP', 'COP'], true)) {
            return 0;
        }
        if ('CLF' === $currency) {
            return 4;
        }
        return 2;
    }

    /**
     * Formatea un monto de Khipu para mostrar: prefijo "$" y decimales según
     * la moneda (ej. "300.0000" + "CLP" -> "$300").
     *
     * @param string|float $amount
     * @param string       $currency
     * @return string
     */
    private static function format_money($amount, $currency)
    {
        return '$' . number_format((float) $amount, self::currency_decimals($currency));
    }

    /**
     * Saldo reembolsable de ESTE pedido, sin llamar a Khipu.
     *
     * Khipu no expone un GET para consultar el `remaining` de un pago (solo lo
     * devuelve en la respuesta de `POST /v3/refunds`, ver PREGUNTAS-API-KHIPU.md).
     * Por eso mantenemos un snapshot local: al reversar guardamos el `remaining`
     * que devuelve Khipu en la meta `_khipu_refund_remaining`. Si aún no hubo
     * ninguna reversa por el plugin, el saldo reembolsable es el total del pedido
     * (un pago conciliado se puede reversar por completo).
     *
     * @param WC_Order $order
     * @return float
     */
    public static function get_order_remaining($order)
    {
        $meta = $order->get_meta('_khipu_refund_remaining');
        return ('' !== $meta) ? (float) $meta : (float) $order->get_total();
    }

    /**
     * Consulta el saldo de la billetera (objeto) o el WP_Error de Khipu, con
     * cache de 30s por api_key (solo cachea el éxito).
     *
     * @param string $api_key
     * @return object|WP_Error
     */
    public static function fetch_wallet_balance($api_key)
    {
        $cache_key = 'khipu_wallet_balance_' . md5($api_key);
        $balance = get_transient($cache_key);

        if (false === $balance) {
            $service = new WC_Khipu_Refund_Service($api_key);
            $balance = $service->get_wallet_balance();
            if (!is_wp_error($balance)) {
                set_transient($cache_key, $balance, 30);
            }
        }

        return $balance;
    }

    /**
     * ¿El error de Khipu indica que la cuenta NO tiene habilitado el flag de
     * billetera de reversas? Khipu lo devuelve como un 400 con el mensaje
     * "La cuenta de cobro con ID … no está habilitada para hacer reversas"
     * (verificado 2026-07-22). Es la señal limpia para gatear la UI de refund.
     *
     * @param object|WP_Error $balance resultado de fetch_wallet_balance()
     * @return bool
     */
    public static function is_refund_wallet_disabled($balance)
    {
        return is_wp_error($balance)
            && false !== stripos($balance->get_error_message(), 'no está habilitada para hacer reversas');
    }

    /**
     * HTML del saldo a partir de un resultado ya consultado (evita re-llamar).
     *
     * @param object|WP_Error $balance
     * @return string
     */
    public static function format_wallet_balance_html($balance)
    {
        if (is_wp_error($balance)) {
            if (self::is_refund_wallet_disabled($balance)) {
                return '<p>' . esc_html__('La billetera de reversas no está habilitada en tu cuenta Khipu.', 'woocommerce-khipu-gateway') . '</p>';
            }
            return '<p>' . esc_html__('No se pudo consultar el saldo de la billetera de reversas.', 'woocommerce-khipu-gateway') . '</p>';
        }

        return '<p>' . sprintf(
            wp_kses(
                __('Saldo billetera de reversas: %1$s %2$s — <a href="%3$s" target="_blank" rel="noopener noreferrer">Recargar</a>', 'woocommerce-khipu-gateway'),
                ['a' => ['href' => [], 'target' => [], 'rel' => []]]
            ),
            esc_html(self::format_money($balance->balance, $balance->currency)),
            esc_html($balance->currency),
            esc_url($balance->add_funds_url)
        ) . '</p>';
    }

    public static function get_wallet_balance_html($api_key)
    {
        if (!$api_key) {
            return '';
        }
        return self::format_wallet_balance_html(self::fetch_wallet_balance($api_key));
    }

    /**
     * Valor configurado de `after_refund_status` para una pasarela, con el
     * default del producto ('wc-refunded') cuando el ajuste no está guardado.
     * Se guarda/renderiza fuera de la tabla estándar de campos (ver
     * get_wallet_section_html() y WC_Gateway_khipu_abstract::process_admin_options()),
     * por eso se lee del option crudo y no vía $this->get_option().
     *
     * @param string $payment_method_id Ya no se usa (el ajuste es compartido por las tres pasarelas); se conserva por compatibilidad.
     * @return string '' (No cambiar) | 'wc-refunded' (Reembolsado).
     */
    public static function configured_after_refund_status($payment_method_id)
    {
        // Los ajustes son compartidos por las tres pasarelas; $payment_method_id se
        // conserva en la firma por compatibilidad.
        $settings = get_option(WC_Gateway_khipu_abstract::SETTINGS_OPTION, []);
        return (is_array($settings) && array_key_exists('after_refund_status', $settings))
            ? $settings['after_refund_status']
            : 'wc-refunded';
    }

    /**
     * Sección "Billetera de reversas" que se muestra al final de los Ajustes de
     * la pasarela. Agrupa todo lo relativo a reversas:
     * - Sin `api_key` configurado (ej. recién instalado): solo un placeholder,
     *   porque no se puede consultar el saldo sin credenciales.
     * - Con `api_key`: el saldo de la billetera + el selector "Estado del pedido
     *   reversado" (que se guarda en WC_Gateway_khipu_abstract::process_admin_options()).
     *
     * @param string $api_key
     * @param string $current_after_refund_status
     * @return string HTML escapado, listo para echo.
     */
    public static function get_wallet_section_html($api_key, $current_after_refund_status)
    {
        $html = '<h3 class="wc-settings-sub-title">' . esc_html__('Billetera de reversas', 'woocommerce-khipu-gateway') . '</h3>';

        if (!$api_key) {
            return $html . '<p class="description">' . esc_html__('Complete los datos para poder visualizar la billetera.', 'woocommerce-khipu-gateway') . '</p>';
        }

        $balance = self::fetch_wallet_balance($api_key);
        $html .= self::format_wallet_balance_html($balance);

        // El selector "Estado del pedido reversado" solo aplica si la billetera
        // está habilitada (si no, no se puede reversar). Si no lo está, mostramos
        // solo el mensaje, sin el selector.
        if (self::is_refund_wallet_disabled($balance)) {
            return $html;
        }

        $options = [
            ''            => __('— No cambiar —', 'woocommerce-khipu-gateway'),
            'wc-refunded' => __('Reembolsado', 'woocommerce-khipu-gateway'),
        ];
        $select = '<select name="khipu_after_refund_status" id="khipu_after_refund_status">';
        foreach ($options as $value => $label) {
            $select .= '<option value="' . esc_attr($value) . '"' . selected($current_after_refund_status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        $select .= '</select>';

        $html .= '<table class="form-table"><tbody><tr valign="top">'
            . '<th scope="row" class="titledesc"><label for="khipu_after_refund_status">' . esc_html__('Estado del pedido reversado', 'woocommerce-khipu-gateway') . ' '
            . wc_help_tip(__('Qué hacer con el pedido al reversar su pago con Khipu. "Reembolsado": registra el reembolso en WooCommerce (parcial o total) y, al agotar el saldo, marca el pedido como reembolsado (solo asiento contable, no vuelve a llamar a Khipu). "— No cambiar —": solo deja nota interna y actualiza el saldo.', 'woocommerce-khipu-gateway'))
            . '</label></th>'
            . '<td class="forminp">' . $select
            . '</td></tr></tbody></table>';

        return $html;
    }

    /**
     * @param WC_Order $order
     * @return string add_funds_url si se pudo consultar el saldo, o '' si no.
     */
    public static function get_recharge_url_for_order($order)
    {
        $service = self::get_service_for_order($order);
        $balance = $service->get_wallet_balance();
        if (is_wp_error($balance) || empty($balance->add_funds_url)) {
            return '';
        }
        return $balance->add_funds_url;
    }

    /**
     * @param string      $type              'full' o 'partial'
     * @param string|null $amount            Monto pedido (string, puede venir de $_POST)
     * @param float       $order_total       Total del pedido
     * @param float|null  $remaining_balance Saldo aún reversable según Khipu (null si nunca se ha reversado antes)
     * @return true|string true si es válido, o el mensaje de error
     */
    public static function validate_refund_amount($type, $amount, $order_total, $remaining_balance = null)
    {
        if ('partial' !== $type) {
            return true;
        }

        if (null === $amount || '' === $amount || (float) $amount <= 0) {
            return __('Monto inválido.', 'woocommerce-khipu-gateway');
        }

        $max_allowed = (null !== $remaining_balance) ? (float) $remaining_balance : (float) $order_total;

        if ((float) $amount > $max_allowed) {
            return __('El monto no puede superar el saldo restante por reversar de este pedido.', 'woocommerce-khipu-gateway');
        }

        return true;
    }

    /**
     * @param WC_Order $order
     */
    public static function process_refund($order)
    {
        $type = (isset($_POST['refund_type']) && 'partial' === $_POST['refund_type']) ? 'partial' : 'full';
        $amount = isset($_POST['amount']) ? wc_format_decimal(wp_unslash($_POST['amount'])) : null;

        $remaining_meta = $order->get_meta('_khipu_refund_remaining');
        $remaining_balance = ('' !== $remaining_meta) ? (float) $remaining_meta : null;

        $validation = self::validate_refund_amount($type, $amount, $order->get_total(), $remaining_balance);
        if (true !== $validation) {
            self::redirect_with_notice($order->get_id(), 'error', $validation);
            return;
        }

        $service = self::get_service_for_order($order);
        $result = $service->refund($order->get_transaction_id(), $type, $amount);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $order->add_order_note(sprintf(
                /* translators: %s: mensaje de error de Khipu */
                __('Reversa Khipu falló: %s', 'woocommerce-khipu-gateway'),
                $error_message
            ));

            // Auto-corrección del botón: si Khipu indica que el pago "no es
            // reembolsable" (pago agotado, o no elegible por cualquier motivo —
            // ej. reversado por otro medio, >180 días, no conciliado), marcamos
            // el pedido sin saldo reversable para que el botón desaparezca.
            // OJO: solo ante "no es reembolsable"; NUNCA ante "excede el saldo
            // reembolsable" a secas, porque ese error puede darse cuando todavía
            // queda saldo (el admin solo pidió de más) — ver PENDIENTES.md.
            // Es una heurística sobre el texto de Khipu (frágil si lo reformulan),
            // pero semánticamente correcta: si Khipu no deja reembolsar, ocultar
            // el botón es lo apropiado igual.
            // Ante una reversa FALLIDA NO mutamos nada (ni meta, ni estado, ni
            // reembolso nativo). En particular, NO ocultamos el botón: el error
            // "no es reembolsable" es AMBIGUO y puede deberse a una causa
            // RECUPERABLE (flag de billetera de reversas aún no habilitado, pago
            // no conciliado todavía, etc.). Si ocultáramos el botón, al activarse
            // el flag el admin ya no podría reversar el pedido. El botón solo se
            // oculta cuando una reversa EXITOSA reporta remaining=0 (señal confiable).
            //
            // Mensaje al admin: el admin solo llega acá con el botón visible
            // (creíamos que había saldo), así que un "no es reembolsable" es
            // contradictorio. La causa MÁS común es que la billetera de reversas
            // no esté habilitada, así que por ahora mostramos ese mensaje
            // accionable en vez del texto crudo. (Técnicamente el mismo error
            // también aparece si el pago fue reversado por fuera, pero es un caso
            // raro; se puede revisar la nota interna con el texto crudo de Khipu.)
            $notice = (false !== stripos($error_message, 'no es reembolsable'))
                ? __('La billetera de reversas no está habilitada en tu cuenta Khipu.', 'woocommerce-khipu-gateway')
                : $error_message;
            self::redirect_with_notice($order->get_id(), 'error', $notice);
            return;
        }

        if (isset($result->remaining)) {
            $order->update_meta_data('_khipu_refund_remaining', $result->remaining);
            $order->save();
            // Refleja la reversa en WooCommerce según el ajuste (reembolso nativo
            // + estado "Reembolsado" al agotar el saldo, o nada si "No cambiar").
            self::reflect_refund_in_woocommerce($order, $result->refunded_amount, $result->remaining);
        }

        if (!empty($result->message)) {
            $order->add_order_note(sprintf(
                /* translators: 1: mensaje de Khipu, 2: monto reversado, 3: moneda, 4: saldo restante */
                __('Reversa Khipu solicitada, queda pendiente: %1$s (reversado en esta operación: %2$s %3$s, restante por reversar: %4$s)', 'woocommerce-khipu-gateway'),
                $result->message,
                $result->refunded_amount,
                $result->currency,
                $result->remaining
            ));
            // "Pendiente" es el estado NORMAL y exitoso de toda reversa: se
            // concreta en el lote diario de Khipu (aparece incluso con saldo de
            // sobra). Por eso el aviso va en tono de éxito, no de advertencia.
            // Solo ofrecemos "Recargar" si el saldo de la billetera realmente
            // podría no alcanzar para concretar lo recién reversado (heurística:
            // saldo actual vs. monto reversado en esta operación; no conocemos el
            // total de reversas del día que pondera el lote de Khipu).
            $balance_info = $service->get_wallet_balance();
            $balance = (!is_wp_error($balance_info) && isset($balance_info->balance)) ? (float) $balance_info->balance : null;
            $balance_ok = (null !== $balance && $balance >= (float) $result->refunded_amount);

            if ($balance_ok) {
                self::redirect_with_notice(
                    $order->get_id(),
                    'pending',
                    __('Reversa solicitada correctamente. Quedó en proceso y se concretará en el próximo ciclo de reversas de Khipu.', 'woocommerce-khipu-gateway')
                );
            } else {
                self::redirect_with_notice(
                    $order->get_id(),
                    'pending',
                    __('Reversa solicitada: quedó en proceso, pero el saldo de la billetera de reversas podría no alcanzar para concretarla. Recárgala para asegurar que se complete en el ciclo de Khipu.', 'woocommerce-khipu-gateway'),
                    self::get_recharge_url_for_order($order)
                );
            }
            return;
        }

        $order->add_order_note(sprintf(
            /* translators: 1: monto reversado, 2: moneda, 3: total reversado acumulado, 4: saldo restante */
            __('Reversa Khipu concretada: %1$s %2$s (total reversado del pedido: %3$s, restante por reversar: %4$s)', 'woocommerce-khipu-gateway'),
            $result->refunded_amount,
            $result->currency,
            $result->total_refunded,
            $result->remaining
        ));
        self::redirect_with_notice($order->get_id(), 'success', __('Reversa realizada con éxito.', 'woocommerce-khipu-gateway'));
    }

    /**
     * @param int    $order_id
     * @param string $status       'success'|'pending'|'error'
     * @param string $message
     * @param string $recharge_url URL de recarga de billetera (opcional, solo relevante para 'pending')
     */
    public static function redirect_with_notice($order_id, $status, $message, $recharge_url = '')
    {
        // El aviso se guarda en un transient por-usuario (se muestra UNA vez y se
        // borra), en vez de en query args de la URL — así no reaparece con texto
        // viejo en cada refresh de la página.
        set_transient('khipu_refund_notice_' . get_current_user_id(), [
            'status'       => $status,
            'message'      => $message,
            'recharge_url' => $recharge_url,
        ], 60);

        wp_safe_redirect(self::get_orders_list_url());
        exit;
    }

    /**
     * URL de la lista de pedidos, respetando HPOS vs almacenamiento legacy.
     *
     * @return string
     */
    public static function get_orders_list_url()
    {
        if (
            class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            return admin_url('admin.php?page=wc-orders');
        }
        return admin_url('edit.php?post_type=shop_order');
    }

    /**
     * @param WC_Order $order
     */
    public static function render_form($order)
    {
        $remaining = self::get_order_remaining($order);
        $currency  = $order->get_currency();
        $balance   = self::fetch_wallet_balance(self::get_api_key_for_order($order));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Reversa Khipu', 'woocommerce-khipu-gateway') . '</h1>';
        echo '<p>' . sprintf(
            /* translators: 1: número de pedido, 2: total formateado */
            esc_html__('Pedido #%1$s — Total: %2$s', 'woocommerce-khipu-gateway'),
            esc_html($order->get_order_number()),
            esc_html(self::format_money($order->get_total(), $currency))
        ) . '</p>';

        // Gate: si la cuenta Khipu no tiene habilitada la billetera de reversas,
        // no mostramos el formulario (Khipu rechazaría la reversa igual). La señal
        // es el error específico de Khipu — ver is_refund_wallet_disabled().
        if (self::is_refund_wallet_disabled($balance)) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('La billetera de reversas no está habilitada en tu cuenta Khipu. No es posible hacer reversas hasta habilitarla.', 'woocommerce-khipu-gateway') . '</p></div>';
            echo '<p><a href="' . esc_url(self::get_orders_list_url()) . '" class="button">' . esc_html__('Volver a Pedidos', 'woocommerce-khipu-gateway') . '</a></p>';
            echo '</div>';
            return;
        }

        echo '<p><strong>' . sprintf(
            /* translators: %s: saldo reembolsable del pedido, formateado */
            esc_html__('Saldo reembolsable de este pedido: %s', 'woocommerce-khipu-gateway'),
            esc_html(self::format_money($remaining, $currency))
        ) . '</strong></p>';

        echo self::format_wallet_balance_html($balance);

        // Aviso "ya tiene una reversa" SOLO si hubo una reversa EXITOSA previa
        // por el plugin. Esa señal es que `_khipu_refund_remaining` está seteada
        // (solo se escribe en la rama de éxito de process_refund). NO escaneamos
        // notas: los intentos FALLIDOS dejan notas "Reversa Khipu falló: …" que
        // también contienen "Reversa Khipu" y daban un falso positivo tras
        // reintentar (ej. cuenta sin el flag de billetera activo).
        if ('' !== $order->get_meta('_khipu_refund_remaining')) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('⚠️ Este pedido ya tiene una reversa Khipu registrada anteriormente — revisa las notas del pedido antes de continuar.', 'woocommerce-khipu-gateway') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=khipu-refund')) . '" id="khipu-refund-form">';
        wp_nonce_field('khipu_refund_' . $order->get_id());
        echo '<input type="hidden" name="page" value="khipu-refund">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr($order->get_id()) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr valign="top"><th scope="row" class="titledesc">' . esc_html__('Tipo de reversa', 'woocommerce-khipu-gateway') . '</th><td class="forminp">'
            . '<label><input type="radio" name="refund_type" value="full" checked> ' . esc_html__('Total', 'woocommerce-khipu-gateway') . '</label> &nbsp;&nbsp; '
            . '<label><input type="radio" name="refund_type" value="partial"> ' . esc_html__('Parcial', 'woocommerce-khipu-gateway') . '</label>'
            . '</td></tr>';
        echo '<tr valign="top" id="khipu-amount-row"><th scope="row" class="titledesc"><label for="khipu-amount">' . esc_html__('Monto a reversar', 'woocommerce-khipu-gateway') . '</label></th><td class="forminp">'
            . '<input type="text" inputmode="decimal" autocomplete="off" name="amount" id="khipu-amount" class="regular-text">'
            . '<p class="description">' . sprintf(
                /* translators: %s: monto máximo reembolsable, formateado */
                esc_html__('Máximo: %s', 'woocommerce-khipu-gateway'),
                esc_html(self::format_money($remaining, $currency))
            ) . '</p>'
            . '<p id="khipu-amount-error" class="description" style="display:none;color:#b32d2e;"></p>'
            . '</td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit">'
            . '<button type="submit" class="button button-primary" id="khipu-refund-submit">' . esc_html__('Confirmar reversa', 'woocommerce-khipu-gateway') . '</button> '
            . '<a href="' . esc_url(self::get_orders_list_url()) . '" class="button">' . esc_html__('Cancelar', 'woocommerce-khipu-gateway') . '</a>'
            . '</p>';
        echo '</form>';

        // JS progresivo (sin JS: el campo Monto queda visible y la validación la
        // hace el servidor — el formulario sigue funcionando).
        // 1) Muestra "Monto" solo en Parcial (default: Total).
        // 2) Sanea el monto: separador decimal '.' (lo que espera Khipu); la coma
        //    se convierte a punto, un solo punto, y antepone '0' si arranca con '.'.
        // 3) Valida al enviar (Parcial: monto > 0 y <= saldo) y bloquea doble envío.
        $max_js = (float) $remaining;
        echo "<script>(function(){"
            . "var form=document.getElementById('khipu-refund-form');"
            . "var row=document.getElementById('khipu-amount-row');"
            . "var amt=document.getElementById('khipu-amount');"
            . "var err=document.getElementById('khipu-amount-error');"
            . "var btn=document.getElementById('khipu-refund-submit');"
            . "var max=" . $max_js . ";"
            . "function isPartial(){var p=document.querySelector('input[name=\"refund_type\"][value=\"partial\"]');return p&&p.checked;}"
            . "function toggle(){row.style.display=isPartial()?'':'none';err.style.display='none';}"
            . "var radios=document.querySelectorAll('input[name=\"refund_type\"]');"
            . "for(var i=0;i<radios.length;i++){radios[i].addEventListener('change',toggle);}"
            . "toggle();"
            . "amt.addEventListener('input',function(){"
            .   "var v=this.value.replace(/,/g,'.').replace(/[^0-9.]/g,'');"
            .   "var i=v.indexOf('.');"
            .   "if(i>-1){v=v.slice(0,i)+'.'+v.slice(i+1).replace(/\\./g,'');if(i===0){v='0'+v;}}"
            .   "this.value=v;err.style.display='none';"
            . "});"
            . "form.addEventListener('submit',function(e){"
            .   "if(isPartial()){"
            .     "var val=parseFloat(amt.value);"
            .     "if(isNaN(val)||val<=0){e.preventDefault();err.textContent=" . wp_json_encode(__('Ingresa un monto válido.', 'woocommerce-khipu-gateway')) . ";err.style.display='';amt.focus();return;}"
            .     "if(val>max){e.preventDefault();err.textContent=" . wp_json_encode(__('El monto supera el saldo reembolsable.', 'woocommerce-khipu-gateway')) . ";err.style.display='';amt.focus();return;}"
            .   "}"
            .   "btn.disabled=true;btn.textContent=" . wp_json_encode(__('Procesando…', 'woocommerce-khipu-gateway')) . ";"
            . "});"
            . "})();</script>";

        echo '</div>';
    }

    public static function show_admin_notice()
    {
        $key = 'khipu_refund_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (false === $notice) {
            return;
        }
        delete_transient($key); // se muestra una sola vez

        $status = isset($notice['status']) ? $notice['status'] : 'error';
        $message = isset($notice['message']) ? $notice['message'] : '';
        $recharge_url = isset($notice['recharge_url']) ? $notice['recharge_url'] : '';

        if ('success' === $status) {
            $class = 'notice-success';
        } elseif ('pending' === $status) {
            // Pendiente = estado normal y exitoso (verde). Solo lo mostramos como
            // advertencia (amarillo) cuando hay link de recarga, es decir cuando
            // el saldo de la billetera podría no alcanzar para concretar la reversa.
            $class = $recharge_url ? 'notice-warning' : 'notice-success';
        } else {
            $class = 'notice-error';
        }

        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message);
        if ($recharge_url) {
            echo ' <a href="' . esc_url($recharge_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Recargar', 'woocommerce-khipu-gateway') . '</a>';
        }
        echo '</p></div>';
    }
}
