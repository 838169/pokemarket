<?php

declare(strict_types=1);

$env = require dirname(__DIR__, 3) . '/config/environment.php';
return [
    'cache_ttl' => 900,
    'cache_path' => dirname(__DIR__) . '/storage/cache',
    'pokeapi_base_url' => $env['POKEAPI_URL'] ?? 'https://pokeapi.co/api/v2',
    'http_timeout' => 15,
    'max_catalog_limit' => 20,
    'default_catalog_limit' => 12,
    'max_batch_ids' => 20,
    'cors_origin' => '*',
];
