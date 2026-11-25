<?php require dirname(__DIR__) . '/config/game-shell.php'; ?>
<!DOCTYPE html>

<html lang="es">

    <head>

        <meta charset="UTF-8" />

        <meta name="viewport" content="width=device-width,initial-scale=1" />

        <title>Catálogo | PokéMarket</title>

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

    <link rel="stylesheet" href="assets/css/pokemon-detail.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/pokemon-detail.css',
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

    <body class="catalog-page market-page" data-protegida="true">

        <?php
        $activePage = 'catalogo.php';

        require dirname(__DIR__) . '/partials/game-header.php';
        ?>

        <main class="market-container">

            <section class="market-hero">

                <div class="market-hero-copy">

                    <h1>Un compañero para<br />cada aventura.</h1>

                    <p>

                        Encuentra tu Pokémon, conoce su potencial y dale un hogar. Tu próximo equipo

                        empieza aquí.

                    </p>

                    <a href="batalla.php" class="hero-battle-link"

                        >Gana monedas en batalla <span aria-hidden="true">↗</span></a

                    >

                </div>

                <div class="market-hero-art" aria-hidden="true">

                    <span class="hero-orbit"></span

                    ><img

                        id="marketHeroPokemon"

                        alt=""

                    /><span class="hero-stamp">Tu aventura<br />toma forma.</span>

                </div>

            </section>

            <section id="welcomeGift" class="welcome-gift" hidden>

                <span class="gift-mark" aria-hidden="true">P</span>

                <div>

                    <h2>Tu primer compañero te espera.</h2>

                    <p>

                        Puedes elegir cualquier Pokémon que alcance tu saldo.
                        Primeros compañeros es una recomendación; tú decides con quién empezar.

                    </p>

                    <ol class="welcome-steps">

                        <li class="current">Elige un Pokémon</li>

                        <li>Compra con tu regalo</li>

                        <li>Conoce su Hogar</li>

                    </ol>

                </div>

            </section>

            <section class="shop-section" aria-labelledby="shopHeading">

                <div class="section-heading">

                    <div>

                        <h2 id="shopHeading">Pokédex</h2>

                    </div>

                    <span class="market-footnote">Nivel 1 → 10</span>

                </div>

                <div id="categoryTabs" class="category-tabs" role="group" aria-label="Categorías de Pokémon"></div>
                <form id="marketFilters" class="market-filters">

                    <label class="search-field"

                        ><span class="search-label">Buscar Pokémon</span

                        ><svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg><input

                            id="buscarPokemon"

                            type="search"

                            placeholder="Busca un Pokémon por nombre o número"

                            maxlength="80"

                        /><button id="botonBuscar" type="submit" class="market-secondary">

                            Buscar

                        </button></label

                    ><label

                        >Tipo<select id="typeFilter">

                            <option value="">Todos los tipos</option>

                        </select></label

                    ><label

                        >Ordenar<select id="sortFilter">

                            <option value="calidad">Mejor animación primero</option>

                            <option value="precio">Menor precio</option>

                            <option value="poder">Mayor potencial</option>

                        </select></label

                    ><button id="resetFilters" class="text-button" type="button">

                        Limpiar filtros

                    </button>

                </form>

                <div class="results-line">

                    <p id="resultsCount" aria-live="polite">Preparando el catálogo…</p>

                </div>

                <div id="pokemonGrid" class="market-grid" aria-busy="true"></div>

                <div class="load-more">

                    <button id="cargarMas" class="market-secondary" type="button" disabled>

                        Ver más Pokémon

                    </button>

                </div>

            </section>

        </main>

        <?php require dirname(__DIR__) . '/partials/pokemon-detail.php'; ?>

        <dialog id="purchaseDialog" class="game-dialog" aria-labelledby="purchaseTitle">

            <button class="dialog-close" type="button" aria-label="Cerrar compra">×</button>

            <div id="purchaseContent"></div>

            <p id="purchaseError" class="purchase-error" role="alert"></p>

            <div class="dialog-actions">

                <button id="confirmPurchase" class="market-primary" type="button">

                    Confirmar compra</button

                ><button id="cancelPurchase" class="market-secondary" type="button">

                    Seguir mirando

                </button>

            </div>

        </dialog>

        <link rel="stylesheet" href="assets/css/purchase-capture.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/purchase-capture.css',
        ) ?>" />
        <script src="assets/js/api.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/api.js') ?>"></script>

        <script src="assets/js/welcome.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/welcome.js',
        ) ?>"></script>

        <script src="assets/js/economy.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/economy.js',
        ) ?>"></script>

        <script src="assets/js/purchase-audio.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/purchase-audio.js',
        ) ?>"></script>
        <script src="assets/js/purchase-chroma.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/purchase-chroma.js',
        ) ?>"></script>
        <script src="assets/js/purchase-celebration.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/purchase-celebration.js',
        ) ?>" data-video-src="<?= is_file(dirname(__DIR__) . '/assets/video/compra-pokebola.mp4')
    ? htmlspecialchars(
        'assets/video/compra-pokebola.mp4?v=' .
            filemtime(dirname(__DIR__) . '/assets/video/compra-pokebola.mp4'),
        ENT_QUOTES,
        'UTF-8',
    )
    : '' ?>"></script>
        <script src="assets/js/market.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/market.js',
        ) ?>"></script>

    </body>

</html>




