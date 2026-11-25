<?php

declare(strict_types=1);

use PokemonService\Controllers\PokemonController;
use PokemonService\Exceptions\HttpException;

return static function (PokemonController $controller): array {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        return [204, null];
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new HttpException(405, 'Método no permitido');
    }

    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');

    // Las rutas específicas deben mantenerse antes de la ruta dinámica por ID.
    if ($path === '/pokemon-api/v1/pokemon/search') {
        return [200, $controller->search($_GET)];
    }

    if ($path === '/pokemon-api/v1/pokemon/batch') {
        return [200, $controller->batch($_GET)];
    }

    if ($path === '/pokemon-api/v1/pokemon') {
        return [200, $controller->catalog($_GET)];
    }

    if (preg_match('#^/pokemon-api/v1/pokemon/([^/]+)$#', $path, $matches)) {
        return [200, $controller->detail($matches[1], ($_GET['view'] ?? '') !== 'card')];
    }

    throw new HttpException(404, 'Ruta no encontrada');
};
