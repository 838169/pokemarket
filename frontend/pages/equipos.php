<?php require dirname(__DIR__) . '/config/game-shell.php'; ?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Mis equipos | PokéMarket</title>
        <link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/base.css',
        ) ?>" />
        <link rel="stylesheet" href="assets/css/catalogo.css" />
        <link rel="stylesheet" href="assets/css/equipos.css?v=<?= filemtime(dirname(__DIR__) . '/assets/css/equipos.css') ?>" />
        <link rel="stylesheet" href="assets/css/pokedex.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/pokedex.css',
        ) ?>" />
        <link rel="stylesheet" href="assets/css/economy.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/economy.css',
        ) ?>" />
    <link rel="stylesheet" href="assets/css/adventure.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/adventure.css',
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

    <body class="catalog-page" data-protegida="true">
        <?php
        $activePage = 'equipos.php';
        require dirname(__DIR__) . '/partials/game-header.php';
        ?>

        <main class="equipos-container">
            <section class="equipos-heading">
                <div>
                    <h1>Mis equipos</h1>
                    <p>Crea equipos y organiza hasta seis Pokémon en cada uno.</p>
                </div>
                <div class="equipos-actions">
                    <form id="formEquipo" class="crear-equipo">
                        <input
                            id="nombreEquipo"
                            type="text"
                            maxlength="100"
                            placeholder="Nombre del equipo"
                            required
                        />
                        <button type="submit" aria-describedby="crearEquipoRequisito" disabled>Crear equipo</button>
                        <small id="crearEquipoRequisito" class="team-create-requirement" role="status"></small>
                    </form>
                    <button id="botonBatallaEquipo" class="btn-batalla" type="button">
                        Batalla
                    </button>
                </div>
            </section>

            <div id="mensajeEquipos" class="equipos-mensaje" aria-live="polite"></div>
            <p id="capacidadEquipos" class="team-limit" aria-live="polite"></p>

            <section class="equipos-layout">
                <aside id="listaEquipos" class="lista-equipos">
                    <div class="loading">Cargando equipos...</div>
                </aside>

                <section id="detalleEquipo" class="detalle-equipo">
                    <div class="empty-equipo">
                        <h2>Selecciona un equipo</h2>
                        <p>Crea o selecciona un equipo para organizar tu Hogar.</p>
                    </div>
                </section>
            </section>
        </main>
        <dialog id="selectorEquipoPokemon" class="game-option-dialog" aria-labelledby="tituloSelectorEquipo">
            <header><h2 id="tituloSelectorEquipo">Elige un Pokémon</h2><form method="dialog"><button aria-label="Cerrar selector" autofocus>×</button></form></header>
            <div id="opcionesEquipoPokemon" class="team-picker-grid"></div>
            <p id="estadoSelectorEquipo" role="status"></p>
        </dialog>

        <dialog id="renombrarEquipo" class="game-option-dialog" aria-labelledby="tituloRenombrarEquipo">
            <header><h2 id="tituloRenombrarEquipo">Renombrar equipo</h2></header>
            <form id="formRenombrarEquipo" class="profile-handle-form">
                <label for="nuevoNombreEquipo">Nombre del equipo</label>
                <input id="nuevoNombreEquipo" maxlength="100" required autofocus>
                <p id="estadoRenombrarEquipo" role="status"></p>
                <div class="profile-handle-actions">
                    <button type="button" id="cancelarRenombrarEquipo">Cancelar</button>
                    <button type="submit">Guardar</button>
                </div>
            </form>
        </dialog>

        <div id="pokemonModal" class="pokemon-modal">
            <div class="modal-backdrop"></div>
            <section class="pokemon-detail">
                <button id="cerrarModal" class="modal-close" type="button">×</button>
                <div class="detail-image-area"><img id="detalleImagen" src="" alt="" draggable="false" /></div>
                <div class="detail-info">
                    <span id="detalleNumero" class="detail-number"></span>
                    <h2 id="detalleNombre"></h2>
                    <div id="detalleTipos" class="detail-types"></div>
                    <p id="detalleDescripcion" class="detail-description"></p>
                    <div class="detail-stats">
                        <div class="detail-stat">
                            <span>Altura</span><strong id="detalleAltura"></strong>
                        </div>
                        <div class="detail-stat">
                            <span>Peso</span><strong id="detallePeso"></strong>
                        </div>
                    </div>
                    <h3>Habilidades</h3>
                    <div id="detalleHabilidades" class="detail-abilities"></div>
                </div>
            </section>
        </div>

        <script src="assets/js/api.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/api.js') ?>"></script>
        <script src="assets/js/welcome.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/welcome.js',
        ) ?>"></script>
        <script src="assets/js/economy.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/economy.js',
        ) ?>"></script>
        <script src="assets/js/equipos.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/equipos.js',
        ) ?>"></script>
        <script>
            PokeMarket.trainer("/battles/current")
                .then((respuesta) => {
                    const boton = document.getElementById("botonBatallaEquipo");
                    boton.textContent = respuesta.batalla ? "Continuar batalla" : "Batalla";
                })
                .catch(() => {});
            document.getElementById("botonBatallaEquipo").addEventListener("click", () => {
                window.location.href = "batalla.php";
            });
        </script>
    </body>
</html>
