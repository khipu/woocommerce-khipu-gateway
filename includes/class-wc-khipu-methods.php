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
     * @param object|null $methods respuesta de GET /merchants/{id}/paymentMethods
     * @return string[]
     */
    public static function account_method_ids($methods)
    {
        $ids = array();
        if (is_object($methods) && !empty($methods->paymentMethods)) {
            foreach ($methods->paymentMethods as $method) {
                if (isset($method->id)) {
                    $ids[] = (string) $method->id;
                }
            }
        }
        return $ids;
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
}
