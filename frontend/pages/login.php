<?php
$env = require dirname(__DIR__, 2) . '/config/environment.php';
$scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$folder = rtrim(dirname(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/\\');
$googleAvailable = !empty($env['GOOGLE_OAUTH_CLIENT_ID']) && !empty($env['GOOGLE_OAUTH_CLIENT_SECRET']) &&
    ($env['GOOGLE_OAUTH_REDIRECT_URI'] ?? '') === $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $folder . '/api/entrenadores/auth/google-callback';
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>PokéMarket</title>

    <link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/base.css',
    ) ?>">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?= filemtime(dirname(__DIR__) . '/assets/css/auth.css') ?>">

</head>

<body>

<div class="auth-page">

    <main class="auth-layout">


        <section class="auth-hero">


            <div class="brand">

                <div class="brand-ball"></div>

                <div class="brand-name">

                    <span class="brand-poke">
                        Poké
                    </span><span class="brand-market">Market</span>

                </div>

            </div>


            <div class="hero-copy">

                <h1>
                    Tu colección
                    comienza <strong>aquí</strong>
                </h1>

                <p>
                    Explora diferentes Pokémon,
                    consulta sus características
                    y crea tu propia colección personal.
                </p>

            </div>


            <div class="preview-cards">


                <article class="preview-card">

                    <img
                        data-pokemon-artwork="1"
                        alt="Bulbasaur">

                    <div class="preview-card-info">

                        <strong>Bulbasaur</strong>

                        <span>
                            Grass · Poison
                        </span>

                    </div>

                </article>


                <article class="preview-card">

                    <img
                        data-pokemon-artwork="4"
                        alt="Charmander">

                    <div class="preview-card-info">

                        <strong>Charmander</strong>

                        <span>
                            Fire
                        </span>

                    </div>

                </article>


                <article class="preview-card">

                    <img
                        data-pokemon-artwork="25"
                        alt="Pikachu">

                    <div class="preview-card-info">

                        <strong>Pikachu</strong>

                        <span>
                            Electric
                        </span>

                    </div>

                </article>

            </div>


            <div class="hero-features">


                <div class="hero-feature">

                    <div class="feature-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true">

                            <circle
                                cx="11"
                                cy="11"
                                r="6">
                            </circle>

                            <path
                                d="M16 16l4 4">
                            </path>

                        </svg>

                    </div>

                    <div class="feature-content">

                        <strong>
                            Explora
                        </strong>

                        <span>
                            Encuentra diferentes Pokémon.
                        </span>

                    </div>

                </div>


                <div class="hero-feature">

                    <div class="feature-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true">

                            <circle
                                cx="12"
                                cy="12"
                                r="9">
                            </circle>

                            <path
                                d="M12 10v6">
                            </path>

                            <path
                                d="M12 7h.01">
                            </path>

                        </svg>

                    </div>

                    <div class="feature-content">

                        <strong>
                            Descubre
                        </strong>

                        <span>
                            Conoce sus características.
                        </span>

                    </div>

                </div>


                <div class="hero-feature">

                    <div class="feature-icon">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true">

                            <path
                                d="M6 4h12a2 2 0 0 1 2 2v14l-8-4-8 4V6a2 2 0 0 1 2-2z">
                            </path>

                        </svg>

                    </div>

                    <div class="feature-content">

                        <strong>
                            Colecciona
                        </strong>

                        <span>
                            Guarda tus Pokémon favoritos.
                        </span>

                    </div>

                </div>

            </div>

        </section>


        <section class="auth-panel">

            <div class="auth-card">


                <div class="auth-symbol"></div>


                <h2>
                    Iniciar sesión
                </h2>


                <p class="auth-description">

                    Bienvenido de nuevo.
                    Ingresa tus datos para continuar.

                </p>


                <div
                    id="mensaje"
                    role="status"
                    class="form-message">
                </div>


                <form id="formLogin">


                    <div class="form-group">

                        <label for="email">
                            Correo electrónico
                        </label>

                        <input
                            class="form-control"
                            type="email"
                            id="email"
                            autocomplete="email"
                            maxlength="150"
                            placeholder="ejemplo@correo.com"
                            required>

                    </div>


                    <div class="form-group">

                        <label for="password">
                            Contraseña
                        </label>

                        <input
                            class="form-control"
                            type="password"
                            id="password"
                            autocomplete="current-password"
                            maxlength="72"
                            placeholder="Ingresa tu contraseña"
                            required>

                    </div>


                    <button
                        class="btn-primary"
                        type="submit">

                        Iniciar sesión

                    </button>

                </form>
                <?php if ($googleAvailable): ?>
                    <a class="google-sign-in" href="api/entrenadores/auth/google">
                        <img src="assets/img/google-g.png" width="20" height="20" alt="">
                        <span>Continuar con Google</span>
                    </a>
                <?php endif; ?>
                <p class="auth-switch"><button type="button" id="openRecovery">¿Olvidaste tu contraseña?</button></p>
                <dialog id="googleSignupDialog" class="game-option-dialog verification-dialog" aria-labelledby="googleSignupTitle">
                    <header><h2 id="googleSignupTitle">Elige tu nombre de usuario</h2><form method="dialog"><button aria-label="Cerrar">×</button></form></header>
                    <form id="googleSignupForm">
                        <div class="form-group">
                            <label for="googleUsername">Nombre de usuario</label>
                            <input class="form-control" id="googleUsername" autocomplete="username" pattern="[A-Za-z0-9_]{3,24}" maxlength="24" required>
                        </div>
                        <p class="verification-description">Usa de 3 a 24 letras sin tilde, números o guiones bajos.</p>
                        <p id="googleSignupStatus" class="verification-status" role="status"></p>
                        <button class="verification-primary" type="submit">Crear cuenta</button>
                    </form>
                </dialog>
                <dialog id="accountLoginDialog" class="game-option-dialog verification-dialog" aria-labelledby="accountLoginTitle">
                    <header><h2 id="accountLoginTitle">Verifica tu identidad</h2><form method="dialog"><button aria-label="Cerrar">×</button></form></header>
                    <form id="accountLoginForm">
                        <p id="accountLoginHelp" class="verification-description" hidden></p>
                        <div class="form-group" id="recoveryEmailGroup"><label for="recoveryEmail">Correo</label><input class="form-control" type="email" maxlength="150" id="recoveryEmail" autocomplete="email"></div>
                        <div class="form-group" id="recoveryCodeGroup"><label for="accountCode" id="accountCodeLabel">Código</label><input class="form-control" id="accountCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" aria-errormessage="accountLoginStatus"></div>
                        <div class="form-group" id="recoveryPasswordGroup"><label for="newAccountPassword">Nueva contraseña</label><input class="form-control" type="password" id="newAccountPassword" autocomplete="new-password" minlength="8" maxlength="72"></div>
                        <div class="form-group" id="recoveryConfirmGroup"><label for="confirmAccountPassword">Repite la nueva contraseña</label><input class="form-control" type="password" id="confirmAccountPassword" autocomplete="new-password" minlength="8" maxlength="72"></div>
                        <p id="accountLoginStatus" class="verification-status" role="status"></p>
                        <button class="verification-primary" type="submit">Continuar</button>
                    </form>
                </dialog>


                <p class="auth-switch">

                    ¿Aún no tienes cuenta?

                    <a href="registro.php">
                        Crear cuenta
                    </a>

                </p>


            </div>

        </section>


    </main>

</div>


<script defer src="assets/js/verification-code.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/verification-code.js') ?>"></script>
<script src="assets/js/api.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/api.js') ?>"></script>
<script src="assets/js/login.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/login.js') ?>"></script>

</body>

</html>
