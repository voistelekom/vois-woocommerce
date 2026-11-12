# End-to-end test (WordPress Playground)

Real WordPress + WooCommerce. `mock-vois.php` answers every `api.vois.com.tr` call locally, so no real SMS is sent and no real call is made.

```sh
npx -y @wp-playground/cli@3.1.59 server --port=9400 --php=8.3 --blueprint=tests/e2e/blueprint.json \
  --mount=$PWD:/wordpress/wp-content/plugins/vois-woocommerce \
  --mount=$PWD/tests/e2e/mock-vois.php:/wordpress/wp-content/mu-plugins/mock-vois.php \
  --mount=$PWD/tests/e2e/run.php:/wordpress/vois-e2e.php
curl http://127.0.0.1:9400/vois-e2e.php              # classic order storage
curl "http://127.0.0.1:9400/vois-e2e.php?hpos=on"    # switch to HPOS (deletes playground orders), then run again
```

Playground's PHP 7.4 build crashes inside libxml while the blueprint runs, so the E2E suite runs on PHP 8.3. PHP 7.4 syntax compatibility is checked with `vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 7.4-`.
