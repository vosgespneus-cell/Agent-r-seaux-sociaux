#!/bin/sh
# LWS host cron namespace, matching the existing PHP 8.3 phase wrappers.
# Sending is also gated by the private allowlist, test marker and enabled flag.
set -eu
umask 077
task_base=/var/www/vosgespneus.com/home/vp_pneus_review
PHP_INI_SCAN_DIR=/usr/base/opt/php8.3/etc/conf.d \
  /usr/base/opt/php8.3/bin/php \
  -c /usr/base/opt/php8.3/etc/php.ini \
  -d extension_dir=/usr/base/opt/php8.3/lib/php/extensions/no-debug-non-zts-20230831 \
  "$task_base/review_intake_tests.php" --run-tests \
  > "$task_base/intake-test.last.log" 2> "$task_base/intake-test.last.err"
if [ -f "$task_base/bridge-tests.json" ]; then
  PHP_INI_SCAN_DIR=/usr/base/opt/php8.3/etc/conf.d \
    /usr/base/opt/php8.3/bin/php \
    -c /usr/base/opt/php8.3/etc/php.ini \
    -d extension_dir=/usr/base/opt/php8.3/lib/php/extensions/no-debug-non-zts-20230831 \
    "$task_base/shopify_bridge.php" --sync-tests \
    > "$task_base/shopify-bridge.last.log" 2> "$task_base/shopify-bridge.last.err"
fi
