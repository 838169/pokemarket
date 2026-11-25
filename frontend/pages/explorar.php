<?php require dirname(__DIR__) . '/config/game-shell.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Explorar · Pradera del Alba | PokéMarket</title>
    <link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/base.css',
    ) ?>" />
    <link rel="stylesheet" href="assets/css/catalogo.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/catalogo.css',
    ) ?>" />
    <link rel="stylesheet" href="assets/css/economy.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/economy.css',
    ) ?>" />
    <link rel="stylesheet" href="assets/css/adventure.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/adventure.css',
    ) ?>" />
    <link rel="stylesheet" href="assets/css/explorar.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/explorar.css',
    ) ?>" />
<script src="assets/js/pokemon-cries-catalog.js?v=<?= filemtime(
    dirname(__DIR__) . '/assets/js/pokemon-cries-catalog.js',
) ?>"></script>
<script src="assets/js/pokemon-cries.js?v=<?= filemtime(
    dirname(__DIR__) . '/assets/js/pokemon-cries.js',
) ?>"></script>
<script src="assets/js/game-view.js?v=<?= filemtime(
    dirname(__DIR__) . '/assets/js/game-view.js',
) ?>"></script>
<script src="assets/js/ball-effects.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/ball-effects.js') ?>"></script>
<link rel="stylesheet" href="assets/css/audio-controls.css?v=<?= filemtime(
    dirname(__DIR__) . '/assets/css/audio-controls.css',
) ?>">
<link rel="stylesheet" href="assets/css/expedition.css?v=<?= filemtime(
    dirname(__DIR__) . '/assets/css/expedition.css',
) ?>">
<link rel="stylesheet" href="assets/css/ball-effects.css?v=<?= filemtime(dirname(__DIR__) . '/assets/css/ball-effects.css') ?>">
</head>
<body class="catalog-page home-page explore-page" data-protegida="true">
    <?php
    $activePage = 'explorar.php';
    require dirname(__DIR__) . '/partials/game-header.php';
    ?>
    <main class="explore-shell">
        <div class="exp-heading">
            <div><p class="exp-breadcrumb">Región del Alba / Expedición salvaje</p><h1>La pradera no lo pone fácil.</h1></div>
            <button type="button" id="exploreHelp" class="market-secondary">Cómo explorar</button>
        </div>
        <div class="exp-expedition-strip"><span><i aria-hidden="true"></i> EXPEDICIÓN SALVAJE</span><p>Cuatro zonas por descubrir. Camina, lee el terreno y cuida tus recursos.</p><strong>Explora · Rastrea · Regresa</strong></div><div class="exp-layout">
            <section class="exp-map-panel" aria-label="Pradera del Alba">
                <div class="exp-map-heading"><div><span class="exp-live-dot" aria-hidden="true"></span><strong>Pradera del Alba</strong><span class="exp-map-tag">Dificultad alta</span><span id="exploreCycle" class="exp-cycle" aria-label="Ciclo de luz de la región">Día · 8:00</span></div><button type="button" id="exploreMusic" aria-pressed="false" class="sound-toggle" aria-label="Activar sonido"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4Z"/><g class="sound-on"><path d="M15 8a6 6 0 0 1 0 8M18 5a10 10 0 0 1 0 14"/></g><path class="sound-off" d="m16 9 5 6m0-6-5 6"/></svg></button></div>
                <div class="exp-viewport" id="exploreViewport">
                    <canvas id="exploreCanvas" tabindex="0" role="img" aria-label="Mapa de la Pradera del Alba. Camina con WASD, flechas o la cruceta. Toca solo los Pokémon para interactuar."></canvas>
                    <div class="exp-map-loading" id="exploreLoading" role="status"><span class="exp-loader"></span><strong>Preparando tu expedición…</strong><p>Buscando caminos y señales de vida.</p></div>
                    <div class="exp-location"><span class="exp-compass" aria-hidden="true">N</span><div><strong id="exploreLocation">Campamento del Alba</strong><small id="explorePosition">Tu progreso se guarda al caminar</small></div></div>
                    <div class="exp-map-tools"><button type="button" id="zoomIn" aria-label="Acercar mapa">+</button><button type="button" id="zoomOut" aria-label="Alejar mapa">−</button><button type="button" id="overviewMap" aria-label="Ver toda la región">Mapa</button><button type="button" id="centerMap" aria-label="Centrar en mi entrenador">◎</button></div>
                    <button type="button" id="exploreRun" class="exp-run" aria-pressed="false">Correr <kbd>Shift</kbd></button>
                    <canvas id="exploreMinimap" class="exp-minimap" width="144" height="100" aria-label="Minimapa de la pradera y tu posición"></canvas>
                    <div class="exp-map-caption">Camina en silencio · Hierba nueva, nuevos rastros</div>
                    <div class="exp-dpad" aria-label="Controles de movimiento"><button type="button" data-direction="up" aria-label="Caminar al norte">↑</button><button type="button" data-direction="left" aria-label="Caminar al oeste">←</button><button type="button" data-direction="down" aria-label="Caminar al sur">↓</button><button type="button" data-direction="right" aria-label="Caminar al este">→</button></div>
                </div>
                <div class="exp-map-footer"><strong id="exploreSave" role="status" aria-label="Conectando" title="Estado del guardado">…</strong><span id="exploreWaiting" class="exp-waiting" role="status" hidden><span class="exp-loader" aria-hidden="true"></span>Cargando…</span></div>
            </section>
            <aside class="exp-sidebar"><div class="exp-quick-actions"><button type="button" id="openBag" aria-controls="exploreBag"><img src="assets/img/icons/backpack2.svg" width="25" height="25" alt=""><span>Mochila</span></button><button type="button" id="openMissions" aria-controls="exploreMissionDialog"><img src="assets/img/icons/clipboard-check.svg" width="25" height="25" alt=""><span>Misiones</span></button><button type="button" id="openAlbum" aria-label="Abrir mi cuaderno" aria-controls="exploreAlbum" aria-expanded="false"><img src="assets/img/icons/journal-bookmark.svg" width="25" height="25" alt=""><span>Cuaderno</span></button></div>
                
                <section class="exp-radar"><div class="exp-section-heading"><h2>Tu expedición</h2><span id="exploreVisited">0%</span></div><div id="exploreSites"></div><section id="exploreWildlife" class="exp-wildlife-card" hidden aria-label="Pokémon avistado"><span class="exp-wildlife-eyebrow">Movimiento entre la hierba</span><h3 id="wildlifeName"></h3><p id="wildlifeBehaviour"></p><button id="wildlifeApproach" type="button" class="market-primary">Acercarme</button></section><button type="button" id="exploreReturn" class="market-secondary">Volver al campamento</button><small class="exp-return-note">Regreso gratis, incluso en 0.</small></section>
                
                
            </aside>

        </div>
        <p id="exploreStatus" class="sr-only" role="status" aria-live="polite" hidden></p>
    </main>
    <dialog id="exploreBag" class="exp-utility-dialog" aria-labelledby="bagTitle"><div class="exp-utility-heading"><h2 id="bagTitle">Tu mochila</h2><button id="closeBag" type="button" aria-label="Cerrar mochila">×</button></div><section id="bagInventory" role="tabpanel" aria-labelledby="bagTitle"><p class="bag-total"><strong id="exploreBalls">0</strong> / 30 en la mochila</p><div id="bagBalls" class="ball-inventory"></div><a id="buyExploreBalls" href="pokeballs.php" class="market-primary">Comprar Poké Balls</a><p id="exploreDaily"></p></section><p id="bagStatus" role="status"></p></dialog>
<dialog id="exploreMissionDialog" class="exp-utility-dialog" aria-labelledby="exploreMissionsTitle"><div class="exp-utility-heading"><h2 id="exploreMissionsTitle">Misiones de exploración</h2><button id="closeMissions" type="button" aria-label="Cerrar misiones">×</button></div><div id="exploreMissions"></div><p id="missionStatus" role="status" class="mission-status"></p></dialog>
<dialog id="exploreSuppliesNotice" class="exp-utility-dialog supplies-notice" aria-labelledby="suppliesNoticeTitle">
    <div class="supplies-ball-bundle" aria-hidden="true"><img src="assets/img/balls/quick-ball.svg" alt=""><img src="assets/img/balls/poke-ball.svg" alt=""><img src="assets/img/balls/super-ball.svg" alt=""></div>
    <p class="exp-gold">Antes de salir a la pradera</p><h2 id="suppliesNoticeTitle">A tu mochila le faltan Poké Balls</h2>
    <p>Los encuentros no esperan. Lleva algunas Poké Balls para capturar Pokémon y completar tu cuaderno.</p>
    <a href="pokeballs.php" class="market-primary">Ir a la tienda de Poké Balls</a>
    <button id="closeSuppliesNotice" type="button" class="market-secondary">Revisar mi cuaderno primero</button>
</dialog>
<dialog id="exploreAlbum" class="album-dialog" aria-label="Cuaderno del Alba"><section class="exp-discovery field-album" aria-label="Cuaderno de explorador Pokémon"><div class="album-cover"><span class="album-cover-title">Cuaderno del Alba</span><button id="albumRewardsShortcut" type="button" class="album-prizes-link">Premios</button><span id="exploreDiscovered">0 / 100</span><button id="closeAlbum" type="button" class="album-close" aria-label="Cerrar cuaderno">×</button></div><div id="albumSpread" class="album-spread"><section id="albumLeftPage" class="album-page album-left"><div class="album-insignia" aria-hidden="true"><svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="31"/><circle cx="40" cy="40" r="25"/><path d="m40 8 8 24 24 8-24 8-8 24-8-24-24-8 24-8Z"/><path d="m40 20 5 15-5 5-5-5Z" fill="currentColor"/></svg></div><p class="album-edition">Diario de un explorador</p><h2 id="albumTitle">Primeros rastros</h2><span id="albumDifficulty"></span><div id="albumStickersLeft" class="album-stickers"></div><p id="exploreJournal" class="album-note" role="status">Completa las categorías y reclama sus premios en el cuaderno.</p><span id="albumLeftNumber" class="album-page-number">01</span></section><section id="albumRightPage" class="album-page album-right"><div class="album-page-heading"><h3>Estampas de la ruta</h3><span class="album-reward-label">Premios por categoría</span></div><div id="exploreSpecies" class="exp-species album-stickers"></div><div id="albumReward" hidden><label for="albumChoice">Elige tu compañero</label><select id="albumChoice"></select><button id="claimAlbum" type="button" class="market-primary">Llevar al Hogar · gratis</button></div><span id="albumRightNumber" class="album-page-number">02</span></section></div><nav class="album-pagination" aria-label="Hojas del cuaderno"><button id="albumPrevious" type="button" aria-label="Hoja anterior">←</button><span id="albumPageLabel" aria-live="polite">1 / 3</span><button id="albumNext" type="button" aria-label="Hoja siguiente">→</button></nav></section></dialog>
<dialog id="exploreGuide" class="exp-dialog exp-guide" aria-labelledby="guideTitle"><div class="exp-guide-art" aria-hidden="true"><span class="exp-trail-line"></span><span class="exp-ball-icon"></span></div><div class="exp-dialog-copy"><p id="guideStep" class="exp-gold">Paso 1 de 3</p><h2 id="guideTitle">Tu primera expedición</h2><p id="guideText"></p><div class="exp-guide-dots" id="guideDots" aria-hidden="true"></div><div class="exp-dialog-actions"><button type="button" id="guidePrevious" class="market-secondary">Anterior</button><button type="button" id="guideNext" class="market-primary">Continuar</button></div><button type="button" id="guideClose" class="exp-quiet">Explorar por mi cuenta</button></div></dialog>
    <dialog id="exploreEncounter" class="exp-dialog exp-encounter" aria-labelledby="encounterTitle"><div class="exp-encounter-scene"><div class="exp-wild-ring"></div><img id="encounterImage" alt="" width="260" height="260" /><span id="encounterLabel">Encuentro salvaje</span><div class="exp-capture-ball" aria-hidden="true"><img id="captureBallArt" src="assets/img/balls/poke-ball.svg" width="64" height="64" alt=""></div></div><div class="exp-dialog-copy"><p class="exp-gold" id="encounterEyebrow">Una señal entre la hierba</p><h2 id="encounterTitle">Un nuevo encuentro</h2><p id="encounterTypes"></p><p id="encounterMessage" role="status"></p><span id="encounterWaiting" class="exp-waiting" role="status" hidden><span class="exp-loader" aria-hidden="true"></span>Cargando encuentro…</span><label class="capture-ball-picker" for="captureBallType">Tu Poké Ball<select id="captureBallType"></select></label><div id="encounterOdds" class="exp-odds"></div><div class="exp-dialog-actions"><button type="button" id="capturePokemon" class="market-primary">Lanzar Poké Ball</button><button type="button" id="leaveEncounter" class="market-secondary">Dejarlo libre</button></div><a href="coleccion.php" id="encounterHome" hidden>Ver en mi Hogar</a></div></dialog>
    <script src="assets/js/api.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/api.js') ?>"></script>
    <script src="assets/js/welcome.js?v=<?= filemtime(
        dirname(__DIR__) . '/assets/js/welcome.js',
    ) ?>"></script>
    <script src="assets/js/economy.js?v=<?= filemtime(
        dirname(__DIR__) . '/assets/js/economy.js',
    ) ?>"></script>
    <script src="assets/js/audio-mixer.js?v=<?= filemtime(
        dirname(__DIR__) . '/assets/js/audio-mixer.js',
    ) ?>"></script>
    <script src="assets/js/wildlife-sprites.js?v=<?= filemtime(
        dirname(__DIR__) . '/assets/js/wildlife-sprites.js',
    ) ?>"></script>
    <script src="assets/js/explore-world.js?v=<?= filemtime(
        dirname(__DIR__) . '/assets/js/explore-world.js',
    ) ?>"></script>
    <script src="assets/js/explore.js?v=<?= filemtime(
        dirname(__DIR__) . '/assets/js/explore.js',
    ) ?>"></script>
</body>
</html>









