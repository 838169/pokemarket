<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit();
}

$allowedClient = getenv('POKEMARKET_LAN_CLIENT');
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
if ((!(require dirname(__DIR__) . '/config/lan-access.php') ||
    ($allowedClient !== false && ($_SERVER['REMOTE_ADDR'] ?? '') !== $allowedClient)) &&
    !in_array($requestPath, ['/assets/css/status.css', '/assets/img/balls/poke-ball.svg'], true)) {
    $errorStatus = 403;
    require dirname(__DIR__) . '/frontend/pages/error.php';
    return;
}
$root = dirname(__DIR__) . '/frontend';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$pages = [
    '/',
    '/index.php',
    '/login.php',
    '/registro.php',
    '/catalogo.php',
    '/coleccion.php',
    '/equipos.php',
    '/batalla.php',
    '/explorar.php',
    '/pokeballs.php',
    '/alimentos.php',
    '/amigos.php',
];
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");
header('Referrer-Policy: strict-origin-when-cross-origin');
if (str_starts_with($requestPath, '/api/')) {
    require $root . '/gateway.php';
    return;
}
if ($path === '/error.php') {
    $errorStatus = ($_GET['status'] ?? '') === '503' ? 503 : 404;
    require $root . '/pages/error.php';
    return;
}
if (in_array($path, $pages, true)) {
    ob_start();
    try {
        require $root . '/pages' . ($path === '/' ? '/index.php' : $path);
        ob_end_flush();
    } catch (Throwable $error) {
        ob_end_clean();
        error_log((string) $error);
        $errorStatus = 500;
        require $root . '/pages/error.php';
    }
    return;
}
$file = realpath($root . $path);
$assets = realpath($root . '/assets') . DIRECTORY_SEPARATOR;
if (
    $file &&
    str_starts_with($file, $assets) &&
    is_file($file) &&
    in_array(
        strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'mp3', 'ogg', 'mp4'],
        true,
    )
) {
    return false;
}
$errorStatus = 404;
require $root . '/pages/error.php';
