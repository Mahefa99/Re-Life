<?php
// Configuration versionnée ; secrets dans l'environnement ou config.local.php.
$local = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];
function configuration(string $name, $default = null) {
    global $local;
    $value = getenv($name);
    return $value !== false ? $value : ($local[$name] ?? $default);
}
define('MODE_DEV', filter_var(configuration('REWEAR_MODE_DEV', false), FILTER_VALIDATE_BOOL));
define('REQUIRE_HTTPS', filter_var(configuration('REWEAR_REQUIRE_HTTPS', false), FILTER_VALIDATE_BOOL));
define('SESSION_IDLE_SECONDS', max(60, (int)configuration('REWEAR_SESSION_IDLE', 1800)));
define('SESSION_MAX_SECONDS', max(300, (int)configuration('REWEAR_SESSION_MAX', 28800)));
define('DB_HOST', configuration('REWEAR_DB_HOST', 'localhost'));
define('DB_PORT', (int)configuration('REWEAR_DB_PORT', 3306));
define('DB_NAME', configuration('REWEAR_DB_NAME', 'rewear'));
define('DB_USER', configuration('REWEAR_DB_USER', 'root'));
define('DB_PASS', configuration('REWEAR_DB_PASS', ''));
define('SITE_URL', rtrim(configuration('REWEAR_SITE_URL', 'http://localhost/wear-share'), '/'));
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
ini_set('display_errors', MODE_DEV ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(static function (Throwable $exception) {
    error_log((string)$exception);
    http_response_code(500);
    echo 'Une erreur est survenue. Veuillez réessayer plus tard.';
});
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store');
    if (REQUIRE_HTTPS && !$https) {
        // Ni Host ni X-Forwarded-Proto fournis par le client ne sont utilisés.
        http_response_code(403);
        exit('Une connexion HTTPS est requise.');
    }
    if (REQUIRE_HTTPS && $https) header('Strict-Transport-Security: max-age=31536000');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('rewear_session');
$cookiePath = rtrim(parse_url(SITE_URL, PHP_URL_PATH) ?: '/', '/') . '/';
session_set_cookie_params(['lifetime' => 0, 'httponly' => true,
    'secure' => $https || REQUIRE_HTTPS, 'samesite' => 'Lax', 'path' => $cookiePath]);
if (!session_start()) { http_response_code(503); exit('La session est indisponible. Veuillez réessayer plus tard.'); }
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    exit('Le service est momentanément indisponible. Veuillez réessayer plus tard.');
}
