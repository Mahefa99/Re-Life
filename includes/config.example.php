<?php
// Copier en config.local.php ; les variables d'environnement ont priorité.
return [
    'REWEAR_MODE_DEV' => false,
    'REWEAR_REQUIRE_HTTPS' => false, // true sur un serveur public HTTPS
    'REWEAR_DB_HOST' => 'localhost',
    'REWEAR_DB_PORT' => 3306,
    'REWEAR_DB_NAME' => 'rewear',
    'REWEAR_DB_USER' => 'rewear_app',
    'REWEAR_DB_PASS' => '', // secret local, jamais dans Git
    'REWEAR_SITE_URL' => 'http://localhost/wear-share',
    'REWEAR_SESSION_IDLE' => 1800,
    'REWEAR_SESSION_MAX' => 28800,
];
