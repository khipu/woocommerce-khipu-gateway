#!/usr/bin/env bash

cd ..
rm -rf woocommerce-khipu-gateway-release
mkdir woocommerce-khipu-gateway-release
cp -R woocommerce-khipu-gateway woocommerce-khipu-gateway-release
cd woocommerce-khipu-gateway-release
rm -rf \
    woocommerce-khipu-gateway/.git \
    woocommerce-khipu-gateway/.gitignore \
    woocommerce-khipu-gateway/.gitmodules \
    woocommerce-khipu-gateway/.idea \
    woocommerce-khipu-gateway/.DS_Store \
    woocommerce-khipu-gateway/composer.phar \
    woocommerce-khipu-gateway/package.sh \
    woocommerce-khipu-gateway/lib/lib-khipu/.git \
    woocommerce-khipu-gateway/lib/lib-khipu/.gitignore \
    woocommerce-khipu-gateway/lib/lib-khipu/.gitattributes
zip -r woocommerce-khipu-gateway.zip woocommerce-khipu-gateway
cd ../woocommerce-khipu-gateway
