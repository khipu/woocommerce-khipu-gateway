<?php


if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Transferencia normal. Es la anfitriona del panel único de Khipu: el
 * formulario y las opciones viven en WC_Gateway_khipu_abstract.
 */
class WC_Gateway_khipu_regular_transfer extends WC_Gateway_khipu_abstract
{
    const ID = 'khipuregulartransfer';
    const METHOD_ID = 'REGULAR_TRANSFER';
    const ICON_FILE = 'khipu-transferencia-normal.png';
    const URL_FIELD = 'transfer_url';
}
