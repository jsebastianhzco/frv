<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Options.php';
require_once __DIR__ . '/HvacRepository.php';
require_once __DIR__ . '/Support.php';
set_exception_handler(function (Throwable $error): void {
    error_log('FRV application failure: ' . get_class($error) . ' code ' . $error->getCode());
    http_response_code(503);
    $title = 'Temporarily unavailable';
    $message = 'We could not load or save this information. Please try again shortly.';
    require __DIR__ . '/../views/error.php';
});
$configPath = __DIR__ . '/../config/database.php';
if (!is_file($configPath)) throw new RuntimeException('Configuration missing');
$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'America/Chicago');
$repository = new HvacRepository(Database::connect($config));
