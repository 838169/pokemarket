<?php
require dirname(__DIR__) . '/config/game-shell.php';
$foodStore = $storeKind === 'food';
$activePage = $foodStore ? 'alimentos.php' : 'pokeballs.php';
$title = $foodStore ? 'Alimentos' : 'Poké Balls';
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $title ?> | PokéMarket</title>
<?php foreach (
    ['base', 'catalogo', 'economy', 'store', 'ball-effects', 'audio-controls']
    as $style
): ?>
<link rel="stylesheet" href="assets/css/<?= $style ?>.css?v=<?= filemtime(
    dirname(__DIR__) . '/assets/css/' . $style . '.css',
) ?>">
<?php endforeach; ?>
<script src="assets/js/game-view.js?v=<?= filemtime(
    dirname(__DIR__) . '/assets/js/game-view.js',
) ?>"></script>
</head><body class="catalog-page store-page" data-protegida="true" data-store="<?= $storeKind ?>">
<?php require __DIR__ . '/game-header.php'; ?>
<main class="market-container store-shell">
<header class="store-heading"><div><h1><?= $title ?></h1><p><?= $foodStore
    ? 'Provisiones para recuperar energía y fortalecer el vínculo.'
    : 'Prepara tu mochila para la próxima expedición.' ?></p></div><a class="market-secondary" href="<?= $foodStore
    ? 'coleccion.php'
    : 'explorar.php' ?>"><?= $foodStore ? 'Volver al Hogar' : 'Volver a explorar' ?></a></header>
<div class="store-toolbar"><p id="storeInventory"></p><?php if (
    !$foodStore
): ?><label for="storePack">Tamaño del paquete<select id="storePack"><option value="3">3 Poké Balls</option><option value="6">6 Poké Balls</option></select></label><?php endif; ?></div>
<?php if (!$foodStore): ?>
<section class="store-capture-reference" aria-label="Referencia de probabilidad de captura">
    <label for="storeCaptureTier">Ver captura para <select id="storeCaptureTier"><option value="1">Común</option><option value="2">Escaso</option><option value="3">Raro</option><option value="4">Legendario</option></select></label>
    <p>Porcentajes aproximados por lanzamiento, sin correr. La probabilidad exacta aparece durante el encuentro.</p>
</section>
<?php else: ?>
<p class="store-food-reference">Cada porción recupera energía y mejora el vínculo, hasta completar el 100%.</p>
<?php endif; ?>
<div id="storeItems" class="store-products" aria-busy="true"></div>
<script src="assets/js/ball-effects.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/ball-effects.js') ?>"></script>
<dialog id="ballPurchase" class="ball-purchase" aria-labelledby="ballPurchaseTitle">
    <div class="ball-purchase-heading"><h2 id="ballPurchaseTitle">¿Preparamos tu mochila?</h2><button id="closeBallPurchase" type="button" aria-label="Cancelar compra">×</button></div>
    <div id="ballPreview" class="ball-preview capture-effect" aria-label="Demostración de captura">
        <img class="effect-target" data-pokemon-artwork="25" width="130" height="130" alt="Pikachu">
        <span class="effect-ball"><img id="ballPreviewArt" width="64" height="64" alt=""></span>
    </div>
    <button id="replayBallPreview" class="preview-replay" type="button">↻ Ver efecto</button>
    <p id="ballPurchaseDetail"></p><p id="ballPurchaseRule"></p>
    <div class="ball-purchase-actions"><button id="cancelBallPurchase" type="button" class="market-secondary">Seguir mirando</button><button id="confirmBallPurchase" type="button" class="market-primary">Confirmar compra</button></div>
</dialog>
<p id="storeStatus" role="status">Preparando la tienda…</p><button id="storeRetry" type="button" class="market-secondary" hidden>Reintentar la misma compra</button>
<?php if (
    !$foodStore
): ?>
<footer class="store-limits" aria-label="Límites de la tienda">
    <dl>
        <div><dt class="game-lock">Cupo diario</dt><dd id="storeDailyLimit">—</dd></div>
        <div><dt class="game-lock">Capacidad de mochila</dt><dd id="storeBagLimit">—</dd></div>
        <div><dt>Renovación del cupo</dt><dd><span id="storeResetCountdown" role="timer" aria-live="off">--:--:--</span> <small>Hora de Colombia</small></dd></div>
    </dl>
    <p>La rareza del Pokémon también influye en la captura.</p>
</footer>
<?php endif; ?>
</main>
<?php foreach (['api', 'welcome', 'economy', 'store'] as $script): ?>
<script src="assets/js/<?= $script ?>.js?v=<?= filemtime(
    dirname(__DIR__) . '/assets/js/' . $script . '.js',
) ?>"></script>
<?php endforeach; ?>
</body></html>
