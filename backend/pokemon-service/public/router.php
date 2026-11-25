<?php

declare(strict_types=1);

$allowedClient = getenv('POKEMARKET_LAN_CLIENT');
if (!(require dirname(__DIR__, 3) . '/config/lan-access.php') ||
    ($allowedClient !== false && ($_SERVER['REMOTE_ADDR'] ?? '') !== $allowedClient)) {
    http_response_code(403);
    exit();
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
