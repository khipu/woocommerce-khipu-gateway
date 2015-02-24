cd ..
rm -rf woocommerce-khipu-gateway woocommerce-khipu-gateway.zip
cp -R woocommerce-khipu woocommerce-khipu-gateway
rm -rf woocommerce-khipu-gateway/.git woocommerce-khipu-gateway/.gitignore woocommerce-khipu-gateway/.gitmodules woocommerce-khipu-gateway/.idea woocommerce-khipu-gateway/.DS_Store woocommerce-khipu-gateway/package.sh
zip -r woocommerce-khipu-gateway.zip woocommerce-khipu-gateway
rm -rf woocommerce-khipu-gateway
