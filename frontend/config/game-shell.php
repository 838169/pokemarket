<?php
declare(strict_types=1);
if (PHP_SAPI === 'cli') {
    return;
}
header('Cache-Control: no-store');
require_once dirname(__DIR__, 2) . '/config/JwtSession.php';
$shellEnv = require dirname(__DIR__, 2) . '/config/environment.php';
$trainerConfig = require dirname(__DIR__, 2) . '/backend/trainer-service/config/app.php';
$sessionName = $trainerConfig['session_name'];
$sessionId = $_COOKIE[$sessionName] ?? '';
$jwt = $_COOKIE[\PokeMarket\JwtSession::COOKIE] ?? '';
$hasSession = \PokeMarket\JwtSession::subject($jwt, $sessionId) !== null;
$trainerPath = $hasSession ? '/auth/me' : '/health';
$trainerContext = stream_context_create(['http' => [
    'timeout' => 3,
    'follow_location' => 0,
    'ignore_errors' => true,
    'header' => $hasSession ? "Cookie: {$sessionName}={$sessionId}; POKEMARKET_AUTH={$jwt}\r\n" : '',
]]);
$trainerResponse = @file_get_contents(
    rtrim(($shellEnv['TRAINER_SERVICE_URL'] ?? '') ?: 'http://localhost:8002/trainer-api/v1', '/') . $trainerPath,
    false,
    $trainerContext,
);
$trainerData = $trainerResponse === false ? null : json_decode($trainerResponse, true);
$trainerStatus = (int) (explode(' ', $http_response_header[0] ?? '')[1] ?? 0);
$trainerHealthy = $trainerStatus === 200 && ($trainerData['success'] ?? false) === true;
if (($hasSession && $trainerStatus === 401) ||
    (!$hasSession && $trainerHealthy && ($trainerData['service'] ?? null) === 'trainer')) {
    header('Location: login.php', true, 302);
    exit();
}
if (!$hasSession || !$trainerHealthy || empty($trainerData['usuario']['id'])) {
    $errorStatus = 503;
    $errorService = 'trainer';
    require dirname(__DIR__) . '/pages/error.php';
    exit();
}
if (($_GET['_contenido'] ?? '') === '1') {
    return;
}
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");
?><!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PokéMarket</title>
<link rel="stylesheet" href="assets/css/audio-controls.css?v=<?= filemtime(
    __DIR__ . '/../assets/css/audio-controls.css',
) ?>">
<link rel="stylesheet" href="assets/css/status.css?v=<?= filemtime(__DIR__ . '/../assets/css/status.css') ?>">
<style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#0b1428}#gameView{display:block;width:100%;height:100dvh;border:0}::view-transition-old(root){animation:section-out 220ms ease-in-out both}::view-transition-new(root){animation:section-in 500ms cubic-bezier(.16,1,.3,1) 60ms both}@keyframes section-out{to{opacity:0;transform:translateY(-3px)}}@keyframes section-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}@media(prefers-reduced-motion:reduce){::view-transition-old(root),::view-transition-new(root){animation:none}}</style></head><body>
<div id="gameLoading" class="game-loading" hidden><div class="route-status">
    <img class="route-ball" src="assets/img/balls/poke-ball.svg" width="76" height="76" alt="">
    <p id="gameLoadingMessage" role="status">Cargando PokéMarket…</p>
    <button id="retryGameLoad" type="button" class="route-action" hidden>Reintentar</button>
</div></div>
<iframe id="gameView" title="PokéMarket" allow="autoplay; fullscreen"></iframe>
<dialog id="audioSettings" class="audio-settings" aria-labelledby="audioSettingsTitle">
    <form method="dialog"><button type="submit" aria-label="Cerrar configuración" autofocus>×</button></form>
    <h2 id="audioSettingsTitle">Configuración</h2>
    <label class="audio-master"><input id="gameSound" type="checkbox"> Sonido general</label>
    <?php foreach (
        ['music' => 'Música', 'cries' => 'Voces Pokémon', 'effects' => 'Efectos de sonido']
        as $key => $label
    ): ?>
        <div class="audio-channel"><label for="audio-<?= $key ?>"><?= $label ?></label><output for="audio-<?= $key ?>">100%</output><input type="range" min="0" max="100" value="100" id="audio-<?= $key ?>" data-audio-channel="<?= $key ?>"></div>
    <?php endforeach; ?>
    <button id="saveAudioSettings" class="audio-save" type="button" disabled>Guardar cambios</button>
</dialog>
<script src="assets/js/audio-mixer.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/audio-mixer.js',
) ?>"></script>
<script src="assets/js/pokemon-cries-catalog.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/pokemon-cries-catalog.js',
) ?>"></script>
<script src="assets/js/pokemon-cries.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/pokemon-cries.js',
) ?>"></script>
<script src="assets/js/game-session.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/game-session.js',
) ?>"></script>
</body></html><?php exit();
