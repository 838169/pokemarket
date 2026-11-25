<?php require dirname(__DIR__) . '/config/game-shell.php'; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Amigos · PokéMarket</title>
    <?php foreach (['base', 'catalogo', 'pokedex', 'economy', 'adventure', 'audio-controls', 'friends'] as $style): ?>
        <link rel="stylesheet" href="assets/css/<?= $style ?>.css?v=<?= filemtime(dirname(__DIR__) . "/assets/css/$style.css") ?>">
    <?php endforeach; ?>
    <?php foreach (['pokemon-cries-catalog', 'pokemon-cries'] as $script): ?>
        <script src="assets/js/<?= $script ?>.js?v=<?= filemtime(dirname(__DIR__) . "/assets/js/$script.js") ?>"></script>
    <?php endforeach; ?>
    <script src="assets/js/game-view.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/game-view.js') ?>"></script>
</head>
<body class="catalog-page" data-protegida="true">
    <?php $activePage = 'amigos.php'; require dirname(__DIR__) . '/partials/game-header.php'; ?>
    <main class="friends-page">
        <header class="friends-hero">
            <div class="friends-search">
                <h1>Amigos</h1>
                <p>Busca a un entrenador para agregarlo a tu lista.</p>
                <form id="friendSearch">
                    <label for="friendUsername">Usuario</label>
                    <div class="friends-search-fields"><input id="friendUsername" required maxlength="25" pattern="@[a-z0-9_\-]{1,24}" placeholder="@entrenador-4" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="friendSearchHint"><button type="submit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.2"/><path d="m15.2 15.2 4.6 4.6" stroke-linecap="round"/></svg>Buscar</button></div>
                    <small id="friendSearchHint">Incluye el @ y escribe el nombre completo.</small>
                </form>
                <div id="friendResults"></div>
            </div>
            <div class="friends-art" aria-hidden="true">
                <img class="friends-eevee" data-pokemon-artwork="133" width="215" height="215" alt="">
                <img class="friends-pikachu" data-pokemon-artwork="25" width="172" height="172" alt="">
            </div>
        </header>
        <p id="friendsStatus" role="status"></p>
        <div class="friends-layout">
            <section aria-labelledby="friendRequestsTitle">
                <h2 id="friendRequestsTitle"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6" stroke-linejoin="round"/></svg>Solicitudes</h2>
                <div id="friendRequests"><p>Cargando solicitudes…</p></div>
            </section>
            <section aria-labelledby="friendListTitle">
                <h2 id="friendListTitle"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 19v-1a6 6 0 0 1 12 0v1M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5" stroke-linecap="round"/></svg>Mis amigos</h2>
                <div id="friendList"><p>Cargando amigos…</p></div>
            </section>
        </div>
    </main>
    <?php foreach (['api', 'welcome', 'economy', 'friends'] as $script): ?>
        <script src="assets/js/<?= $script ?>.js?v=<?= filemtime(dirname(__DIR__) . "/assets/js/$script.js") ?>"></script>
    <?php endforeach; ?>
</body>
</html>
