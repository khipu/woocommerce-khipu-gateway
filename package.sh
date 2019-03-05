#!/usr/bin/env bash

cd ..
rm -rf woocommerce-khipu-gateway.zip
cp -R woocommerce-khipu-gateway woocommerce-khipu-gateway-release
rm -rf \
    woocommerce-khipu-gateway-release/.git \
    woocommerce-khipu-gateway-release/.gitignore \
    woocommerce-khipu-gateway-release/.gitmodules \
    woocommerce-khipu-gateway-release/.idea \
    woocommerce-khipu-gateway-release/.DS_Store \
    woocommerce-khipu-gateway-release/composer.phar \
    woocommerce-khipu-gateway-release/package.sh \
    woocommerce-khipu-gateway-release/lib/lib-khipu/.git \
    woocommerce-khipu-gateway-release/lib/lib-khipu/.gitignore \
    woocommerce-khipu-gateway-release/lib/lib-khipu/.gitattributes
zip -r woocommerce-khipu-gateway.zip woocommerce-khipu-gateway-release
rm -rf woocommerce-khipu-gateway-release
cd woocommerce-khipu-gateway
