cd ..
rm -rf woocommerce-khipu-gateway woocommerce-khipu-gateway.zip
cp -R woocommerce-khipu woocommerce-khipu-gateway
rm -rf woocommerce-khipu-gateway/.git woocommerce-khipu-gateway/.gitignore woocommerce-khipu-gateway/.gitmodules woocommerce-khipu-gateway/.idea woocommerce-khipu-gateway/.DS_Store woocommerce-khipu-gateway/package.sh woocommerce-khipu-gateway/lib/lib-khipu/.git woocommerce-khipu-gateway/lib/lib-khipu/.gitignore woocommerce-khipu-gateway/lib/lib-khipu/.gitattributes
zip -r woocommerce-khipu-gateway.zip woocommerce-khipu-gateway
rm -rf woocommerce-khipu-gateway
