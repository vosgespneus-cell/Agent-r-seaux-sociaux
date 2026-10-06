#!/bin/sh
PHP_INI_SCAN_DIR=/usr/base/opt/php8.3/etc/conf.d /usr/base/opt/php8.3/bin/php -c /usr/base/opt/php8.3/etc/php.ini -d extension_dir=/usr/base/opt/php8.3/lib/php/extensions/no-debug-non-zts-20230831 /var/www/vosgespneus.com/home/vp_run.php ai >>/var/www/vosgespneus.com/home/vp_ai.log 2>>/var/www/vosgespneus.com/home/vp_ai.err
