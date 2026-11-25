<?php

declare(strict_types=1);

$env = require dirname(__DIR__, 3) . '/config/environment.php';
return [
    'allowed_origins' => array_values(
        array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    $env['APP_ORIGINS'] ?? '',
                ),
            ),
        ),
    ),
    'session_name' => 'POKEMARKET_SESSION',
    'session_path' => '/',
    'session_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        filter_var($env['SESSION_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'session_idle_seconds' => max(60, (int) ($env['SESSION_IDLE_SECONDS'] ?? 3600)),
];
