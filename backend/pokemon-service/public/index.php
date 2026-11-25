<?php

declare(strict_types=1);

use PokemonService\Controllers\PokemonController;
use PokemonService\Exceptions\HttpException;
use PokemonService\Http\JsonResponse;
use PokemonService\Http\DetailStream;
use PokemonService\Services\CacheService;
use PokemonService\Services\GameCatalog;
use PokemonService\Services\PokeApiClient;
use PokemonService\Services\PokemonMapper;
use PokemonService\Services\SpriteResolver;

$config = require dirname(__DIR__) . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('X-Request-ID: ' . bin2hex(random_bytes(8)));
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/') === '/pokemon-api/v1/health'
) {
    JsonResponse::send(['success' => true, 'service' => 'pokemon', 'status' => 'ready']);
}

try {
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/pokemon-api/v1/game-catalog') {
        header('Cache-Control: no-store');
        JsonResponse::send(['success' => true, 'pokemon' => GameCatalog::species()]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
        preg_match('#^/pokemon-api/v1/sprites/([1-9][0-9]{0,4})/(home|artwork|animated|back)$#', $path, $match)) {
        $sprite = GameCatalog::sprite((int) $match[1], $match[2]);
        header('Cache-Control: no-store');
        if ($sprite === null) JsonResponse::send(['success' => false, 'message' => 'Imagen no disponible'], 404);
        header('Location: ' . $sprite, true, 302);
        exit();
    }

    $controller = new PokemonController(
        new PokeApiClient(
            $config['pokeapi_base_url'],
            $config['http_timeout'],
            new CacheService($config['cache_path'], $config['cache_ttl']),
        ),
        new PokemonMapper(new SpriteResolver()),
        new CacheService($config['cache_path'], $config['cache_ttl']),
        $config,
    );

    if (
        $_SERVER['REQUEST_METHOD'] === 'GET' &&
        ($_GET['progress'] ?? '') === '1' &&
        ($_GET['view'] ?? '') === 'card' &&
        preg_match('#^/pokemon-api/v1/pokemon/([1-9][0-9]*)$#', $path, $match)
    ) {
        DetailStream::send($controller, $match[1]);
    }

    $router = require dirname(__DIR__) . '/src/Routes/api.php';
    [$statusCode, $payload] = $router($controller);

    if ($statusCode === 204) {
        http_response_code(204);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        exit();
    }

    JsonResponse::send($payload, $statusCode);
} catch (HttpException $exception) {
    JsonResponse::send(
        array_merge(
            [
                'success' => false,
                'message' => $exception->getMessage(),
            ],
            $exception->context(),
        ),
        $exception->statusCode(),
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(
        [
            'success' => false,
            'message' => 'Error interno del pokemon-service',
        ],
        500,
    );
}
