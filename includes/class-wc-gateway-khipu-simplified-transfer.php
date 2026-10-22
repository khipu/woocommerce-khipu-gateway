<?php


if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Transferencia simplificada. Sin formulario propio: ver WC_Gateway_khipu_abstract.
 */
class WC_Gateway_khipu_simplified_transfer extends WC_Gateway_khipu_abstract
{
    const ID = 'khipusimplifiedtransfer';
    const METHOD_ID = 'SIMPLIFIED_TRANSFER';
    const ICON_FILE = 'khipu-transferencia-simplificada.png';
    const URL_FIELD = 'simplified_transfer_url';
}
