<?php
// Copier en config.local.php ; les variables d'environnement ont priorité.
return [
    'RELIFE_MODE_DEV' => false,
    'RELIFE_REQUIRE_HTTPS' => false, // true sur un serveur public HTTPS
    'RELIFE_DB_HOST' => 'localhost',
    'RELIFE_DB_PORT' => 3306,
    'RELIFE_DB_NAME' => 'relife',
    'RELIFE_DB_USER' => 'root',
    'RELIFE_DB_PASS' => '', // secret local, jamais dans Git
    'RELIFE_SITE_URL' => 'http://localhost/Re-Life',
    'RELIFE_SESSION_IDLE' => 1800,
    'RELIFE_SESSION_MAX' => 28800,
];
