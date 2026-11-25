<?php require dirname(__DIR__) . '/config/game-shell.php'; ?>
<!DOCTYPE html>

<html lang="es">

    <head>

        <meta charset="UTF-8" />

        <meta name="viewport" content="width=device-width,initial-scale=1" />

        <title>Hogar Pokémon | PokéMarket</title>

        <link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/base.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/catalogo.css" />

        <link rel="stylesheet" href="assets/css/pokedex.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/pokedex.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/economy.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/economy.css',
        ) ?>" />

    <link rel="stylesheet" href="assets/css/adventure.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/adventure.css',
    ) ?>" />

        <link rel="stylesheet" href="assets/css/home-layout.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/home-layout.css',
        ) ?>" />
    <link rel="stylesheet" href="assets/css/audio-controls.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/audio-controls.css',
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
</head>

    <body class="catalog-page home-page" data-protegida="true">

        <?php
        $activePage = 'coleccion.php';

        require dirname(__DIR__) . '/partials/game-header.php';
        ?>

        <main class="market-container">

            <section class="home-heading">

                <div>

                    <h1>Un lugar al que volver.</h1>

                    <p>

                        Este es tu Hogar Pokémon. Aquí crecen tus compañeros y tus próximas

                        aventuras.

                    </p>

                </div>

                <a class="market-secondary" href="catalogo.php">Encontrar un compañero</a>

            </section>

            <section id="homeScene" class="home-scene" aria-busy="true" aria-label="Tu Hogar Pokémon" data-custom-background="<?= is_file(
                dirname(__DIR__) . '/assets/img/adventure/hogar-santuario.png',
            )
                ? 'assets/img/adventure/hogar-santuario.png'
                : '' ?>">

                <div class="scene-topline">

                    <span class="home-sign">Hogar Pokémon</span

                    ><span id="homeCount" class="scene-count">Preparando tu hogar…</span>

                </div>

                <div id="homeLoading" class="home-loading">
                    <div class="home-loading-card">
                        <div id="homeLoadingProgress" class="home-loading-progress" role="progressbar" aria-label="Preparando tu Hogar Pokémon" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="28"/><circle cx="32" cy="32" r="28" pathLength="100"/></svg>
                            <strong id="homeLoadingPercent" aria-hidden="true">0%</strong>
                        </div>
                        <p id="homeLoadingMessage" role="status">Preparando tu hogar…</p>
                        <button id="retryHome" type="button" class="market-secondary" hidden>Reintentar</button>
                    </div>
                </div>

                <div id="habitatPlaces" class="habitat-places" aria-hidden="true"></div>
                <div id="habitatActors" class="habitat-actors"></div>
<button id="homeMusic" class="sound-toggle" type="button" aria-pressed="false" aria-label="Activar sonido" title="Activar sonido"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4Z"/><g class="sound-on"><path d="M15 8a6 6 0 0 1 0 8M18 5a10 10 0 0 1 0 14"/></g><path class="sound-off" d="m16 9 5 6m0-6-5 6"/></svg></button>
<span id="homeMusicStatus" class="sr-only" role="status"></span>

                <div id="emptyHome" class="empty-home" hidden>

                    <h2>Tu aventura aún está por llegar.</h2>

                    <p>

                        Tus primeras 1.000 monedas te esperan. Elige a tu primer compañero y dale

                        vida a este lugar.

                    </p>

                    <a href="catalogo.php" class="market-primary">Elegir mi primer Pokémon</a>

                </div>

            </section>

            <div class="home-scene-toolbar">
                <p id="homePlacementInfo" role="status" aria-live="polite"></p>
                <div id="homePagination" class="home-pagination" role="group" aria-label="Grupos de compañeros" hidden>
                    <button type="button" id="homePrevious" class="market-secondary" aria-label="Ver grupo anterior">Anterior</button>
                    <span id="homePageLabel"></span>
                    <button type="button" id="homeNext" class="market-secondary" aria-label="Ver siguiente grupo">Siguiente</button>
                </div>
            </div>

            <div class="home-actions home-shortcuts" role="group" aria-label="Actividades del Hogar">
                <button id="openHabitats" type="button" aria-haspopup="dialog"><img src="assets/img/icons/journal-bookmark.svg" width="22" height="22" alt="">Hábitats</button>
                <button type="button" aria-haspopup="dialog" data-home-section="care"><img src="assets/img/icons/heart-fill.svg" width="22" height="22" alt="">Cuidar</button>
                <button type="button" aria-haspopup="dialog" data-home-section="train"><img src="assets/img/icons/bar-chart-steps.svg" width="22" height="22" alt="">Entrenar</button>
                <button type="button" aria-haspopup="dialog" data-home-section="history"><img src="assets/img/icons/clock-history.svg" width="22" height="22" alt="">Historial</button>
            </div>
            <dialog id="homeHabitats" class="home-dialog" aria-labelledby="homeHabitatsTitle">
                    <header><h2 id="homeHabitatsTitle">Hábitats</h2><form method="dialog"><button aria-label="Cerrar hábitats">×</button></form></header>
                    <p id="habitatCaption"></p>

                    <div id="habitatTabs" role="group" aria-label="Ver hábitat">

                        <button type="button" data-habitat="todos" aria-pressed="true">

                            Todo el Hogar</button

                        ><button type="button" data-habitat="pradera">Pradera</button

                        ><button type="button" data-habitat="bosque">Bosque</button

                        ><button type="button" data-habitat="laguna">Laguna</button>

                    </div>

            </dialog>
            <dialog id="homePicker" class="home-dialog" aria-labelledby="homePickerTitle">
                <header><h2 id="homePickerTitle">¿A quién quieres cuidar?</h2><form method="dialog"><button aria-label="Cerrar selector">×</button></form></header>
                <div id="companionList" class="companion-list"><p>Cargando compañeros…</p></div>
            </dialog>
            <dialog id="homeCareSection" class="home-dialog home-detail" aria-labelledby="homeDetailTitle">
                <header><button id="changeCompanion" type="button">‹ Cambiar Pokémon</button><form method="dialog"><button aria-label="Cerrar ficha">×</button></form></header>
                <h2 id="homeDetailTitle" class="sr-only">Ficha Pokémon</h2>
                <section id="carePanel" class="care-panel"></section>
                <p id="homeCareStatus" role="status"></p>
            </dialog>
            <dialog id="homeCarePicker" class="home-dialog care-picker" aria-labelledby="homeCarePickerTitle">
                <header><h2 id="homeCarePickerTitle">Cuidar Pokémon</h2><form method="dialog"><button aria-label="Volver a la ficha" autofocus>×</button></form></header>
                <p id="homeCarePickerStatus" role="status"></p>
                <div id="homeCareChoices"></div>
            </dialog>
            <dialog id="homeHistorySection" class="home-dialog" aria-labelledby="homeHistoryTitle">
                <header><h2 id="homeHistoryTitle">Historial de monedas</h2><form method="dialog"><button aria-label="Cerrar historial">×</button></form></header>
                <div id="walletHistory"><p>Cargando movimientos…</p></div>
            </dialog>

        </main>

        <script src="assets/js/api.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/api.js') ?>"></script>

        <script src="assets/js/welcome.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/welcome.js',
        ) ?>"></script>

        <script src="assets/js/economy.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/economy.js',
        ) ?>"></script>

        <script src="assets/js/pokemon-scale.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/pokemon-scale.js',
        ) ?>"></script>
        <script src="assets/js/audio-mixer.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/audio-mixer.js',
        ) ?>"></script>
        <script src="assets/js/home-music.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/home-music.js',
        ) ?>"></script>

        <script src="assets/js/home-layout.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/home-layout.js',
        ) ?>"></script>

        <script src="assets/js/home.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/home.js') ?>"></script>

    </body>

</html>


