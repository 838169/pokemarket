<?php
declare(strict_types=1);

$views = [
    403 => ['Acceso restringido', 'Esta ruta requiere permiso.'],
    404 => ['Ruta no encontrada', 'Esta página no aparece en el mapa.'],
    500 => ['Algo salió mal', 'Puedes volver al inicio e intentarlo de nuevo.'],
    503 => ['Servicio en pausa', 'Espera un momento y vuelve a intentarlo.'],
];
$status = $errorStatus ?? (int) ($_SERVER['REDIRECT_STATUS'] ?? (($_GET['status'] ?? '') === '503' ? 503 : 404));
if (!isset($views[$status])) $status = 404;
[$title, $message] = $views[$status];
$trainerOffline = $status === 503 && ($errorService ?? $_GET['service'] ?? '') === 'trainer';
if ($trainerOffline) {
    $title = 'Servicio de Entrenadores no disponible';
    $message = 'Comprueba que el servicio del puerto 8002 esté encendido y recarga la página.';
}
http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
$base = PHP_SAPI === 'cli-server' ? '/' : rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/', 3), '/\\') . '/';
$base = htmlspecialchars($base, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $status ?> · <?= $title ?> | PokéMarket</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/status.css?v=<?= filemtime(dirname(__DIR__) . '/assets/css/status.css') ?>">
</head>
<body class="game-error">
    <main class="route-status">
        <img class="route-ball" src="<?= $base ?>assets/img/balls/poke-ball.svg" width="76" height="76" alt="">
        <p class="route-code"><?= $status ?></p>
        <h1><?= $title ?></h1>
        <p><?= $message ?></p>
        <?php if ($trainerOffline): ?>
        <a class="route-action" href="<?= $base ?>login.php" target="_top">Volver al inicio de sesión</a>
        <?php else: ?>
        <a class="route-action" href="<?= $base ?>catalogo.php" target="_top">Volver al catálogo</a>
        <?php endif; ?>
    </main>
</body>
</html>
