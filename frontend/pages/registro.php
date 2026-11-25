<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
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
                    <span class="brand-poke">Poké</span><span class="brand-market">Market</span>
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

                        <span>Grass · Poison</span>

                    </div>

                </article>


                <article class="preview-card">

                    <img
                        data-pokemon-artwork="4"
                        alt="Charmander">

                    <div class="preview-card-info">

                        <strong>Charmander</strong>

                        <span>Fire</span>

                    </div>

                </article>


                <article class="preview-card">

                    <img
                        data-pokemon-artwork="25"
                        alt="Pikachu">

                    <div class="preview-card-info">

                        <strong>Pikachu</strong>

                        <span>Electric</span>

                    </div>

                </article>

            </div>


            <div class="hero-features">

                <div class="hero-feature">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="6"></circle>
                            <path d="M16 16l4 4"></path>
                        </svg>

                    </div>

                    <div class="feature-content">
                        <strong>Explora</strong>
                        <span>Encuentra diferentes Pokémon.</span>
                    </div>

                </div>


                <div class="hero-feature">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 10v6"></path>
                            <path d="M12 7h.01"></path>
                        </svg>

                    </div>

                    <div class="feature-content">
                        <strong>Descubre</strong>
                        <span>Conoce sus características.</span>
                    </div>

                </div>


                <div class="hero-feature">

                    <div class="feature-icon">

                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 4h12a2 2 0 0 1 2 2v14l-8-4-8 4V6a2 2 0 0 1 2-2z"></path>
                        </svg>

                    </div>

                    <div class="feature-content">
                        <strong>Colecciona</strong>
                        <span>Guarda tus Pokémon favoritos.</span>
                    </div>

                </div>

            </div>
        </section>



        <section class="auth-panel">

            <div class="auth-card">

                <div class="auth-symbol"></div>

                <h2 id="tituloRegistro">Crear cuenta</h2>

                <p class="auth-description" id="descripcionRegistro">

                    Únete a PokéMarket y comienza
                    a construir tu colección.

                </p>


                <div id="mensaje" class="form-message" role="status" aria-live="polite">
                </div>


                <form id="formRegistro">

                    <div class="form-group">

                        <label for="nombre">
                            Nombre
                        </label>

                        <input
                            class="form-control"
                            type="text"
                            id="nombre"
                            placeholder="Tu nombre y apellidos"
                            autocomplete="name"
                            maxlength="100"
                            required>

                    </div>


                    <div class="form-group">
                        <label for="usuario">Nombre de usuario</label>
                        <input class="form-control" type="text" id="usuario" autocomplete="username"
                               autocapitalize="none" spellcheck="false" minlength="3" maxlength="24"
                               pattern="[A-Za-z0-9_]{3,24}" placeholder="Ej. entrenador_alba"
                               title="De 3 a 24 letras sin tilde, números o guiones bajos" required>
                    </div>

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
                            autocomplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                            minlength="8"
                            maxlength="72"
                            required>

                    </div>


                    <button
                        class="btn-primary"
                        type="submit">

                        Enviar código

                    </button>

                </form>


                <form id="formVerificarRegistro" hidden>
                    <div class="form-group">
                        <label for="codigoRegistro">Código de verificación</label>
                        <input class="form-control" id="codigoRegistro" type="text"
                               inputmode="numeric" autocomplete="one-time-code"
                               pattern="[0-9]{6}" maxlength="6" aria-errormessage="mensaje" spellcheck="false" required>
                    </div>
                    <button class="btn-primary" type="submit">Verificar e ingresar</button>
                    <p class="verification-resend">
                        <button id="reenviarCodigoRegistro" type="button">Reenviar código</button>
                    </p>
                </form>

                <p class="auth-switch">

                    ¿Ya tienes cuenta?

                    <a href="login.php">
                        Iniciar sesión
                    </a>

                </p>


            </div>

        </section>

    </main>

</div>


<script defer src="assets/js/verification-code.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/verification-code.js') ?>"></script>
<script src="assets/js/api.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/api.js') ?>"></script>
<script src="assets/js/registro.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/registro.js') ?>"></script>

</body>

</html>
