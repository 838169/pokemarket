<?php

declare(strict_types=1);

use TrainerService\ControladorAuth;
use TrainerService\ControladorColeccion;
use TrainerService\ControladorEquipos;
use TrainerService\JsonResponse;
use TrainerService\SessionManager;

$config = require dirname(__DIR__) . '/bootstrap.php';
if (PHP_SAPI === 'cli-server' && getenv('POKEMARKET_LAN') === '1') {
    $address = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
        preg_match('/^(10\.|192\.168\.|172\.(?:1[6-9]|2\d|3[01])\.)/', $address)) {
        $config['allowed_origins'][] = "http://$address:8000";
    }
}
JsonResponse::configure($config['allowed_origins']);
set_exception_handler(static function (Throwable $error): never {
    if ($error instanceof \TrainerService\PokemonCatalogUnavailable) {
        JsonResponse::error($error->getMessage(), 503);
    }
    if ($error instanceof InvalidArgumentException) {
        JsonResponse::error($error->getMessage(), 400);
    }
    JsonResponse::log('unhandled_exception', $error);
    JsonResponse::error('El servicio no está disponible. Revisa MySQL o inténtalo de nuevo.', 503);
});
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::options();
}
$conexion = require dirname(__DIR__) . '/config/database.php';
$sesion = new SessionManager($config, $conexion);
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'], true)) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !in_array($origin, $config['allowed_origins'], true)) {
        JsonResponse::error('Origen no permitido.', 403);
    }
    if (!in_array($path, array_map(static fn ($route) => '/trainer-api/v1/auth/' . $route,
        ['login', 'register', 'google-signup', 'login-code', 'register-code', 'register-resend', 'reset-request', 'reset-verify', 'reset-password']), true)) {
        $sesion->requireCsrf();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/trainer-api/v1/exploration') {
    (new \TrainerService\ControladorExploracion(
        new \TrainerService\ExplorationService($conexion),
        $sesion,
    ))->handle('state');
}
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    preg_match(
        '#^/trainer-api/v1/exploration/(travel|supplies|capture|leave|tutorial|observe|mission|album)$#',
        $path,
        $match,
    )
) {
    (new \TrainerService\ControladorExploracion(
        new \TrainerService\ExplorationService($conexion),
        $sesion,
    ))->handle($match[1]);
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === '/trainer-api/v1/health') {
    $conexion->query('SELECT 1 FROM carteras_usuario LIMIT 1');
    JsonResponse::send([
        'success' => true,
        'service' => 'trainer',
        'status' => 'ready',
        'database' => true,
    ]);
}

$rutas = require dirname(__DIR__) . '/src/rutas.php';
$rutas(
    new ControladorAuth($conexion, $sesion),
    new ControladorColeccion($conexion, $sesion),
    new ControladorEquipos($conexion, $sesion),
    new \TrainerService\ControladorBatalla($conexion, $sesion),
    new \TrainerService\ControladorEconomia(new \TrainerService\GameEconomy($conexion), $sesion),
);
