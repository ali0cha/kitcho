<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('SRC', __DIR__);

$configFile = ROOT . '/config.php';
if (is_file($configFile)) {
    $GLOBALS['config'] = require $configFile;
} elseif (getenv('DB_HOST') !== false && getenv('DB_HOST') !== '') {
    // Déploiement par conteneur : configuration par variables d'environnement.
    $bool = fn(string $k, bool $d): bool => ($v = getenv($k)) === false ? $d : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    $GLOBALS['config'] = [
        'db' => [
            'host'    => getenv('DB_HOST'),
            'name'    => getenv('DB_NAME') ?: 'menu',
            'user'    => getenv('DB_USER') ?: 'menu',
            'pass'    => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ],
        'app' => [
            'name'               => getenv('APP_NAME') ?: 'Kitcho',
            'allow_registration' => $bool('ALLOW_REGISTRATION', true),
            'debug'              => $bool('APP_DEBUG', false),
            'timezone'           => getenv('TZ') ?: 'Europe/Brussels',
        ],
    ];
} else {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "config.php introuvable. Lancez install.php dans le navigateur, copiez config.sample.php ou définissez DB_HOST/DB_NAME/DB_USER/DB_PASS.\n");
        exit(1);
    }
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/install.php');
    exit;
}
date_default_timezone_set($GLOBALS['config']['app']['timezone'] ?? 'Europe/Brussels');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', !empty($GLOBALS['config']['app']['debug']) ? '1' : '0');

require SRC . '/helpers.php';
require SRC . '/db.php';
require SRC . '/dates.php';
require SRC . '/auth.php';
require SRC . '/ingredients.php';
require SRC . '/planner.php';
require SRC . '/ics.php';
require SRC . '/schema.php';
require SRC . '/steps.php';
require SRC . '/recipe_import.php';

if (!defined('SKIP_SCHEMA_ENSURE')) {
    schema_ensure();
}

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    $detail = !empty($GLOBALS['config']['app']['debug']) ? (string) $e : null;
    render('error', ['code' => 500, 'message' => 'Une erreur est survenue.', 'detail' => $detail]);
    exit;
});

if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('menu_sid');
    // Téléphone en magasin : on reste connecté 30 jours (cookie persistant + côté serveur).
    $sessionLifetime = 30 * 86400;
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => base_path() . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Le manifeste, le service worker et le flux ICS n'ont pas besoin de session (et ne doivent pas poser de cookie).
    if (!preg_match('#/(sw\.js|manifest\.webmanifest|version|cal/[0-9a-f]{40}\.ics)$#', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
        session_start();
    }
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
