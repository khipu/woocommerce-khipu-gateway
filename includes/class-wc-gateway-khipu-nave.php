<?php


if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pago por QR (Argentina, medio NAVE de Khipu). Siempre por redirección a nave_url;
 * sin ícono propio. Sin formulario propio: ver WC_Gateway_khipu_abstract.
 */
class WC_Gateway_khipu_nave extends WC_Gateway_khipu_abstract
{
    const ID = 'khipunave';
    const METHOD_ID = 'NAVE';
    const URL_FIELD = 'nave_url';
}
