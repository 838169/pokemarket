<?php

declare(strict_types=1);

use TrainerService\ControladorAuth;
use TrainerService\ControladorColeccion;
use TrainerService\ControladorEquipos;
use TrainerService\ControladorBatalla;
use TrainerService\JsonResponse;

return static function (
    ControladorAuth $auth,
    ControladorColeccion $coleccion,
    ControladorEquipos $equipos,
    ControladorBatalla $batallas,
    \TrainerService\ControladorEconomia $economia,
): never {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        JsonResponse::options();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
    if ($path === '/trainer-api/v1/duels' && in_array($method, ['GET', 'POST'], true)) {
        $batallas->duels($method === 'POST');
    }
    if ($path === '/trainer-api/v1/friends' && in_array($method, ['GET', 'POST'], true)) {
        if ($method === 'GET' && ($_GET['invitaciones'] ?? '') === '1') {
            $batallas->friendDuelInvites();
        }
        $batallas->friends($method === 'POST', $_GET['usuario'] ?? null);
    }
    if (in_array($method,['GET','POST'],true) && $path === '/trainer-api/v1/duos') {
        $batallas->duos($method === 'POST');
    }
    if ($method === 'GET' && $path === '/trainer-api/v1/battles/offers') {
        $batallas->offers();
    }
    if ($method === 'POST' && $path === '/trainer-api/v1/profile/username') {
        $auth->changeUsername();
    }
    if ($economia) {
        if ($method === 'GET' && $path === '/trainer-api/v1/profile') {
            $economia->trainerProfile();
        }
        if ($method === 'POST' && $path === '/trainer-api/v1/onboarding/complete') {
            $economia->welcome();
        }
        if ($method === 'POST' && $path === '/trainer-api/v1/home/scenes') {
            $economia->unlockScene();
        }
        if ($method === 'GET' && $path === '/trainer-api/v1/wallet') {
            $economia->wallet();
        }
        if ($method === 'GET' && $path === '/trainer-api/v1/shop/catalog') {
            $economia->catalog();
        }
        if (
            $method === 'POST' &&
            in_array($path, ['/trainer-api/v1/shop/purchase', '/trainer-api/v1/collection'], true)
        ) {
            $economia->purchase();
        }
        if ($method === 'GET' && $path === '/trainer-api/v1/home') {
            $economia->home();
        }
        if ($method === 'POST' && $path === '/trainer-api/v1/home/food') {
            $economia->buyFood();
        }
        if ($method === 'POST' && $path === '/trainer-api/v1/home/feed') {
            $economia->feed();
        }
        if ($method === 'POST' && $path === '/trainer-api/v1/home/ability') {
            $economia->ability();
        }
        if ($method === 'POST' && $path === '/trainer-api/v1/home/moves') {
            $economia->moves();
        }
        if ($method === 'PATCH' && $path === '/trainer-api/v1/home/habitat') {
            $economia->habitat();
        }
    }

    if ($method === 'GET' && $path === '/trainer-api/v1/auth/security') {
        $auth->security('security');
    }
    if ($method === 'GET' && in_array($path, ['/trainer-api/v1/auth/google', '/trainer-api/v1/auth/google-callback'], true)) {
        $auth->googleLogin(str_ends_with($path, '-callback'));
    }
    if ($method === 'POST' && preg_match('#^/trainer-api/v1/auth/(login-code|register-code|register-resend|reset-request|reset-verify|reset-password|verify-request|verify-email|totp-setup|totp-enable)$#', $path, $match)) {
        $auth->security($match[1]);
    }
    if ($method === 'POST' && $path === '/trainer-api/v1/auth/register') {
        $auth->register();
    }

    if ($method === 'POST' && $path === '/trainer-api/v1/auth/login') {
        $auth->login();
    }
    if ($method === 'POST' && $path === '/trainer-api/v1/auth/google-signup') {
        $auth->googleSignup();
    }

    if ($method === 'POST' && $path === '/trainer-api/v1/auth/logout') {
        $auth->logout();
    }

    if ($method === 'GET' && $path === '/trainer-api/v1/auth/me') {
        $auth->me();
    }

    if ($method === 'GET' && $path === '/trainer-api/v1/collection') {
        $coleccion->list();
    }

    if ($method === 'POST' && $path === '/trainer-api/v1/collection') {
        $coleccion->add();
    }

    if (
        $method === 'DELETE' &&
        preg_match('#^/trainer-api/v1/collection/([^/]+)$#', $path, $matches)
    ) {
        $coleccion->delete($matches[1]);
    }

    if ($method === 'POST' && $path === '/trainer-api/v1/battles') {
        $batallas->start();
    }

    if ($method === 'POST' && preg_match('#^/trainer-api/v1/battles/([^/]+)/initiative$#', $path, $matches)) {
        $batallas->initiative($matches[1]);
    }

    if ($method === 'GET' && $path === '/trainer-api/v1/battles/current') {
        $batallas->active();
    }

    if (
        $method === 'POST' &&
        preg_match('#^/trainer-api/v1/battles/([^/]+)/switch$#', $path, $matches)
    ) {
        $batallas->switchPokemon($matches[1]);
    }

    if (
        $method === 'POST' &&
        preg_match('#^/trainer-api/v1/battles/([^/]+)/turn$#', $path, $matches)
    ) {
        $batallas->turn($matches[1]);
    }

    if ($method === 'GET' && preg_match('#^/trainer-api/v1/battles/([^/]+)$#', $path, $matches)) {
        $batallas->current($matches[1]);
    }

    if (
        $method === 'DELETE' &&
        preg_match('#^/trainer-api/v1/battles/([^/]+)$#', $path, $matches)
    ) {
        $batallas->abandon($matches[1]);
    }

    if ($method === 'GET' && $path === '/trainer-api/v1/teams') {
        $equipos->list();
    }

    if ($method === 'POST' && $path === '/trainer-api/v1/teams') {
        $equipos->create();
    }

    if (
        $method === 'PUT' &&
        preg_match('#^/trainer-api/v1/teams/([^/]+)/slots/([^/]+)$#', $path, $matches)
    ) {
        $equipos->saveMember($matches[1], $matches[2]);
    }

    if (
        $method === 'DELETE' &&
        preg_match('#^/trainer-api/v1/teams/([^/]+)/slots/([^/]+)$#', $path, $matches)
    ) {
        $equipos->deleteMember($matches[1], $matches[2]);
    }

    if ($method === 'GET' && preg_match('#^/trainer-api/v1/teams/([^/]+)$#', $path, $matches)) {
        $equipos->detail($matches[1]);
    }

    if ($method === 'PATCH' && preg_match('#^/trainer-api/v1/teams/([^/]+)$#', $path, $matches)) {
        $equipos->update($matches[1]);
    }

    if ($method === 'DELETE' && preg_match('#^/trainer-api/v1/teams/([^/]+)$#', $path, $matches)) {
        $equipos->delete($matches[1]);
    }

    JsonResponse::error('Ruta no encontrada o método no permitido', 404);
};
