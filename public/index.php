<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

// PHP built-in server (php -S): serve static files directly without going through Symfony
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false; // Let PHP handle the static file directly
    }
}

require_once dirname(__DIR__).'/vendor/autoload.php';

// Load .env files – won't override env vars already set by Railway / OS
if (class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
    (new \Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$env   = $_SERVER['APP_ENV']   ?? $_ENV['APP_ENV']   ?? 'prod';
$debug = filter_var(
    $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? ('prod' !== $env),
    FILTER_VALIDATE_BOOLEAN
);

$kernel   = new Kernel($env, $debug);
$request  = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
