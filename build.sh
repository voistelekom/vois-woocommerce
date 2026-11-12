#!/bin/sh
# Builds build/vois-woocommerce.zip (only runtime files).
set -e
cd "$(dirname "$0")"
rm -rf build && mkdir -p build/vois-woocommerce
cp -R vois-woocommerce.php readme.txt LICENSE src assets build/vois-woocommerce/
cd build && zip -qr vois-woocommerce.zip vois-woocommerce && echo "build/vois-woocommerce.zip"
