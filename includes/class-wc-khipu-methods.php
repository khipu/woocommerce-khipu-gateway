<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Lógica pura de los medios de pago de Khipu (sin WordPress ni WooCommerce):
 * nombre legible de cada medio, título compuesto de la fila y selección del
 * admin (qué medios de la cuenta se ofrecen).
 */
class WC_Khipu_Methods
{
    private static $labels = array(
        'REGULAR_TRANSFER'    => 'Transferencia normal',
        'SIMPLIFIED_TRANSFER' => 'Transferencia simplificada',
        'LIGOPAY'             => 'LigoPay',
        'NAVE'                => 'Pago por QR',
    );

    /**
     * Nombre legible del medio. Un medio que el plugin no conoce se muestra
     * con su id legible, para no esconderlo si Khipu suma uno nuevo.
     */
    public static function label($method_id)
    {
        $method_id = (string) $method_id;
        if (isset(self::$labels[$method_id])) {
            return self::$labels[$method_id];
        }
        return ucfirst(strtolower(str_replace('_', ' ', $method_id)));
    }

    /**
     * Título de la fila del checkout: "{Título} - {Medio}".
     */
    public static function compose_title($title, $method_id)
    {
        $title = trim((string) $title);
        if ('' === $title) {
            $title = 'Khipu';
        }
        return $title . ' - ' . self::label($method_id);
    }

    /**
     * Si el admin marcó el medio. Un valor guardado que no es array significa
     * "nunca se guardó la selección": se ofrecen todos los medios de la cuenta.
     * Un array, aun vacío, es una selección explícita.
     *
     * @param string $method_id
     * @param mixed  $saved
     */
    public static function is_selected($method_id, $saved)
    {
        if (!is_array($saved)) {
            return true;
        }
        return in_array($method_id, $saved, true);
    }

    /**
     * Medios que la cuenta puede tener habilitados pero que no sirven para pagar
     * una compra: nunca se muestran. Sumar uno acá es todo lo que hace falta.
     */
    private static $excluded = array('DIRECT_DEBIT');

    /**
     * Ids de los medios de la cuenta, sin repetir, sin los excluidos y en el orden de
     * visualización (ver sort_for_display(): simplificada primero, normal al final), que
     * es el que usan las casillas del panel y la selección guardada. Un medio que el plugin no conoce se incluye igual (aparece
     * en el panel); si no tiene pasarela, no genera ninguna fila.
     *
     * @param object|null $methods respuesta de GET /merchants/{id}/paymentMethods
     * @return string[]
     */
    public static function account_method_ids($methods)
    {
        $ids = array();
        if (is_object($methods) && !empty($methods->paymentMethods) && is_array($methods->paymentMethods)) {
            foreach ($methods->paymentMethods as $method) {
                if (!is_object($method) || !isset($method->id) || !is_scalar($method->id)) {
                    continue;
                }
                $id = (string) $method->id;
                if ('' === $id || in_array($id, self::$excluded, true) || in_array($id, $ids, true)) {
                    continue;
                }
                $ids[] = $id;
            }
        }
        return self::sort_for_display($ids);
    }

    /**
     * Selección enviada por el formulario, limitada a los medios de la cuenta
     * y en el orden de la cuenta.
     *
     * @param mixed    $posted
     * @param string[] $account_ids
     * @return string[]
     */
    public static function normalize_selection($posted, array $account_ids)
    {
        if (!is_array($posted)) {
            return array();
        }
        $selected = array();
        foreach ($account_ids as $id) {
            if (in_array($id, $posted, true)) {
                $selected[] = $id;
            }
        }
        return $selected;
    }

    /**
     * Recorta un texto a un máximo de caracteres (contando multibyte) con elipsis.
     * Khipu rechaza el detalle del cobro (`body`) de más de 5120 caracteres.
     *
     * @param mixed $text
     * @param int   $max
     * @return string
     */
    public static function fit_text($text, $max)
    {
        if (!is_string($text)) {
            return '';
        }
        $max = (int) $max;
        if ($max <= 0) {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') <= $max ? $text : mb_substr($text, 0, $max - 1, 'UTF-8') . '…';
        }
        return strlen($text) <= $max ? $text : substr($text, 0, $max - 1) . '…';
    }

    /**
     * Si una URL es de Khipu: solo https y host khipu.com o un subdominio. Las URLs de
     * pago vienen de la API por TLS verificado, pero antes de redirigir al comprador se
     * comprueba igual (como hace el módulo de PrestaShop).
     *
     * @param mixed $url
     * @return bool
     */
    public static function is_khipu_url($url)
    {
        if (!is_string($url) || '' === $url) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if ('https' !== strtolower($parts['scheme'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        return 'khipu.com' === $host || '.khipu.com' === substr($host, -10);
    }

    /**
     * Orden en que se muestran los medios de Khipu: la transferencia simplificada
     * siempre primero y la transferencia normal siempre al final; el resto (LigoPay,
     * QR, uno nuevo) conserva el orden recibido. Sin duplicados.
     *
     * Va acá (sin WordPress) para poder probarlo solo; quien lo aplica al registro de
     * pasarelas es woocommerce_gateway_khipu_sort_rows().
     *
     * @param string[] $method_ids
     * @return string[]
     */
    public static function sort_for_display(array $method_ids)
    {
        $ids = array_values(array_unique(array_map('strval', $method_ids)));
        $first = array();
        $middle = array();
        $last = array();
        foreach ($ids as $id) {
            if ('SIMPLIFIED_TRANSFER' === $id) {
                $first[] = $id;
            } elseif ('REGULAR_TRANSFER' === $id) {
                $last[] = $id;
            } else {
                $middle[] = $id;
            }
        }
        return array_merge($first, $middle, $last);
    }
}
