<?php


if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * LigoPay (Perú). Sin formulario propio: ver WC_Gateway_khipu_abstract.
 */
class WC_Gateway_khipu_ligopay extends WC_Gateway_khipu_abstract
{
    const ID = 'khipuligopay';
    const METHOD_ID = 'LIGOPAY';
    const URL_FIELD = 'ligopay_url';
}
