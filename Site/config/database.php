<?php
return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('DB_NAME') ?: 'erpdb',
    'username' => getenv('DB_USER') ?: 'webuser',
    'password' => getenv('DB_PASS') ?: 'webuser',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];