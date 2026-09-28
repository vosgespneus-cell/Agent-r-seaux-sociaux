<?php
// Copy to /var/www/vosgespneus.com/home/vp_config.php and fill locally.
// Never place the filled file in htdocs or GitHub.
return [
    'webhook_secret' => 'REPLACE_WITH_RANDOM_SECRET_AT_LEAST_32_CHARACTERS',
    'db_dsn' => 'mysql:host=YOUR_LWS_DB_HOST;dbname=YOUR_DB_NAME;charset=utf8mb4',
    'db_user' => 'YOUR_DB_USER',
    'db_password' => 'YOUR_DB_PASSWORD',
];
