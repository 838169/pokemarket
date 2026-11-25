<?php require dirname(__DIR__) . '/config/game-shell.php'; ?>
<?php
require_once dirname(__DIR__, 2) . '/backend/trainer-service/bootstrap.php';
use TrainerService\AdventureRules;
?>

<!DOCTYPE html>

<html lang="es">

    <head>

        <meta charset="UTF-8" />

        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>Batalla | PokéMarket</title>

        <link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/base.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/catalogo.css" />

        <link rel="stylesheet" href="assets/css/pokedex.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/pokedex.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/batalla.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/batalla.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/battle-arena.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/battle-arena.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/battle-school.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/battle-school.css',
        ) ?>" />

        <link rel="stylesheet" href="assets/css/economy.css?v=<?= filemtime(
            dirname(__DIR__) . '/assets/css/economy.css',
        ) ?>" />

    <link rel="stylesheet" href="assets/css/adventure.css?v=<?= filemtime(
        dirname(__DIR__) . '/assets/css/adventure.css',
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



    <body class="catalog-page battle-page" data-protegida="true">

        <?php
        $activePage = 'batalla.php';
        require dirname(__DIR__) . '/partials/game-header.php';
        ?>



        <main class="batalla-container">

            <section class="batalla-heading" id="battleHeading">

                <div>

                    <h1 id="tituloArena">Tu aventura como entrenador.</h1>

                    <p id="subtituloArena">Aprende paso a paso o entra al combate con tu equipo.</p>

                </div>

                <div class="arena-toolbar">

                    <button

                        id="ayudaBatalla"

                        class="btn-secundario"

                        type="button"

                        aria-expanded="false"

                    >

                        <span aria-hidden="true">?</span> Guía de combate</button

                    >
                    <a id="equiposArena" class="btn-secundario arena-toolbar-accent" href="equipos.php">Mis equipos</a>
                    <button id="salirPractica" class="btn-secundario" type="button" hidden>Salir de práctica</button>
                    <button id="abrirAtaques" class="btn-secundario arena-toolbar-accent" type="button" aria-haspopup="dialog" hidden>Elegir ataque</button>
                    <button id="reintentarTurnoRival" class="btn-secundario" type="button" hidden>Reintentar turno rival</button>
                    <button id="abrirResultado" class="btn-secundario arena-toolbar-accent" type="button" aria-haspopup="dialog" aria-controls="resultadoMovil" hidden>Ver resultado</button>

                </div>

            </section>



            <section id="panelInicio" class="panel-inicio">

                <div class="lobby-intro">

                    <div>

                        <h2>Tu primera batalla, con una guía a tu lado.</h2>

                        <p>

                            Cinco pasos cortos: conoce a tu Pokémon, ataca, observa el daño, cambia

                            y consigue tu primera victoria.

                        </p>

                        <button id="practicaDirecta" class="btn-batalla" type="button">

                            Empezar el tutorial</button

                        ><span class="lobby-nota">Te indicaremos dónde pulsar en cada paso.</span>

                    </div>

                    <div class="lobby-emblema" aria-hidden="true">

                        <div class="brand-ball"></div>

                        <span>Escuela de entrenadores</span>

                    </div>

                </div>

                <h2 class="selector-titulo">Elige cómo quieres jugar</h2>

                <div class="modos-juego" role="group" aria-label="Modo de juego">

                    <button

                        class="modo-juego seleccionado"

                        data-modo="practica"

                        type="button"

                        aria-pressed="true"

                    >

                        <img class="mode-art" src="<?= AdventureRules::artwork(
                            'modo-practica.png',
                            'battle-meadow.jpg',
                        ) ?>" alt="" /><strong>Práctica guiada</strong

                        ><span>Aprende atacando y cambiando de Pokémon.</span

                        ><small>Sin equipo · A tu ritmo</small>

                    </button>

                    <button

                        class="modo-juego"

                        data-modo="rapido"

                        type="button"

                        aria-pressed="false"

                    >

                        <img class="mode-art" src="<?= AdventureRules::artwork(
                            'modo-rapido.png',
                            'battle-forest.jpg',
                        ) ?>" alt="" /><strong>Duelo rápido</strong

                        ><span>El primer Pokémon de tu equipo contra un rival.</span

                        ><small>1 contra 1 · Cada decisión cuenta</small>

                    </button>

                    <button

                        class="modo-juego"

                        data-modo="equipos"

                        type="button"

                        aria-pressed="false"

                    >

                        <img class="mode-art" src="<?= AdventureRules::artwork(
                            'modo-equipos.png',
                            'pokemon-home.png',
                        ) ?>" alt="" /><strong>Combate por equipos</strong

                        ><span>Combina tus Pokémon hasta vencer al rival.</span

                        ><small>Tu equipo completo · Más estrategia</small>

                    </button>

                    <button class="modo-juego" data-modo="apuesta" type="button" aria-pressed="false">

                        <img class="mode-art" src="<?= AdventureRules::artwork(
                            'modo-apuesta.png',
                            'battle-forest.jpg',
                        ) ?>" alt="" />

                        <strong>Duelo de apuestas</strong><span>Elige tu riesgo y enfréntate a un desafío mayor.</span><small>1 contra 1 · Solo monedas del juego</small>

                    </button>
                    <button class="modo-juego" data-modo="amigos" type="button" aria-pressed="false">
                        <img class="mode-art" src="<?= AdventureRules::artwork('modo-amigos.jpg', 'battle-forest.jpg') ?>" alt="">
                        <strong>Duelo con amigos</strong><span>Reta a un amigo en una sala privada.</span><small>Dos entrenadores · 1 a 6 Pokémon por lado</small>
                    </button>

                </div>

                <dialog id="confirmarEntrada" class="adventure-dialog battle-entry-dialog" aria-labelledby="tituloEntrada">
                <header class="battle-entry-heading"><div><p id="modoEntrada"></p><h2 id="tituloEntrada">Prepara tu combate</h2></div><form method="dialog"><button class="btn-secundario" aria-label="Cerrar confirmación" autofocus>×</button></form></header>
                <div class="battle-entry-body">
                <section id="wagerBoard" class="wager-board" hidden aria-labelledby="wagerTitle">

                    <h2 id="wagerTitle">Elige cuánto quieres arriesgar</h2>

                    <p>La entrada se descuenta al comenzar. Si ganas, recibes el doble de la apuesta (incluye tu entrada). Si pierdes o abandonas, pierdes la entrada. No hay recompensa adicional.</p>

                    <div id="wagerOffers" class="wager-offers" role="group" aria-label="Ofertas de apuestas"></div>

                    <div class="wager-meta"><span id="wagerExpiry" role="status"></span><button class="btn-secundario" type="button" id="refreshWagers">Actualizar ofertas</button></div>

                </section>

                <div id="selectorEquipos" hidden>
                    <div class="battle-entry-team">
                    <h2 class="selector-titulo">1. Elige tu equipo</h2>

                    <p id="notaEquipo" class="nota-equipo">

                        Se usará el primer Pokémon de tu equipo.

                    </p>

                    <div id="tarjetasEquipos" class="tarjetas-equipos"></div>

                    <p id="sinEquipos" hidden>

                        Prepara tu equipo en <a href="equipos.php">Mis equipos</a>. Mientras tanto,

                        puedes jugar la práctica guiada.

                    </p>

                    </div>
                    <section id="selectorPokemonInicial" hidden aria-labelledby="tituloPokemonInicial"><h3 id="tituloPokemonInicial">2. Elige quién empieza</h3><p id="notaPokemonInicial">Selecciona un equipo para ver a sus compañeros.</p><div id="opcionesPokemonInicial" class="battle-lead-options" role="group" aria-label="Pokémon inicial"></div></section>
                </div>
                </div>

                <footer class="battle-entry-footer">
                <div class="inicio-controles">

                    <button id="iniciarBatalla" class="btn-batalla" type="button">

                        Entrar a la práctica</button

                    ><span id="resumenModo">Aprende sin afectar tu Hogar.</span>

                </div>

                <p id="estadoEntrada" class="mensaje-batalla" role="status"></p>
                </footer>
                </dialog>
                <div id="batallaActiva" class="batalla-activa" hidden>

                    <p>Hay una batalla activa en tu sesión.</p>

                    <button id="continuarBatalla" class="btn-batalla" type="button">

                        Continuar batalla

                    </button>

                    <button id="abandonarDesdeInicio" class="btn-abandonar" type="button">

                        Terminar batalla guardada

                    </button>

                </div>

                <p id="mensajeBatalla" class="mensaje-batalla" aria-live="polite"></p>

            </section>



            <section id="panelCombate" class="panel-combate" hidden>

                <section

                    id="rutaTutorial"

                    class="ruta-tutorial"

                    aria-label="Pasos de la práctica"

                    hidden

                >

                    <div class="ruta-cabecera">

                        <strong>Tu primera batalla, paso a paso</strong

                        ><span id="rutaProgreso">Paso 1 de 5</span

                        ><button id="reiniciarGuia" type="button">Volver a empezar</button>

                    </div>

                    <ol id="pasosTutorial"></ol>

                </section>

                <div class="battle-layout">

                    <div class="battle-view">

                        <div class="arena-marcador">

                            <span id="etiquetaModo">Combate por equipos</span

                            ><span id="numeroTurno">Turno 1</span>

                            <div class="arena-opciones">

                                <label class="sonido-control"

                                    ><input id="animacionesBatalla" type="checkbox" />

                                    Animaciones</label

                                ><button id="sonidoBatalla" class="sound-toggle" type="button" aria-pressed="false" aria-label="Activar sonido" title="Activar sonido"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4Z"/><g class="sound-on"><path d="M15 8a6 6 0 0 1 0 8M18 5a10 10 0 0 1 0 14"/></g><path class="sound-off" d="m16 9 5 6m0-6-5 6"/></svg></button>

                            </div>

                        </div>

                        <div class="combate-campo" aria-label="Campo de batalla">

                            <article

                                id="ladoJugador"

                                class="lado-combate lado-jugador"

                                aria-label="Tu Pokémon"

                            ></article>

                            <article

                                id="ladoRival"

                                class="lado-combate lado-rival"

                                aria-label="Pokémon rival"

                            ></article>

                            <div class="arena-centro" aria-hidden="true">VS</div>

                            <span

                                id="efectividadTexto"

                                class="efecto-mensaje"

                                aria-live="polite"

                            ></span>

                        </div>

                        <div class="equipos-miniaturas">

                            <div>

                                <h3>Tu equipo <span>Selecciona para cambiar</span></h3>

                                <div id="miniaturasJugador" class="miniaturas"></div>

                            </div>

                            <div>

                                <h3>Equipo rival</h3>

                                <div id="miniaturasRival" class="miniaturas"></div>

                            </div>

                        </div>

                    </div>

                    <aside class="battle-command" aria-label="Controles de combate">

                        <div id="coachCabecera" class="coach-cabecera">

                            <span class="coach-ball" aria-hidden="true"></span>

                            <div>

                                <small>Escuela de entrenadores</small

                                ><strong>Vamos paso a paso</strong>

                            </div>

                        </div>

                        <div id="guiaContextual" class="guia-contextual" hidden>

                            <span id="guiaPaso" class="guia-paso"></span>

                            <h2 id="guiaTitulo"></h2>

                            <p id="guiaTexto"></p>

                            <p id="guiaObjetivo" class="guia-objetivo"></p>

                            <button id="guiaSiguiente" class="btn-batalla" type="button" hidden>

                                Continuar</button

                            ><button id="ocultarGuia" class="guia-ocultar" type="button">

                                Practicar sin guía

                            </button>

                        </div>

                        <div class="battle-actions">

                            <div class="comando-heading">

                                <span id="turnoTexto">Tu turno</span

                                ><span class="estado-led" aria-hidden="true"></span>

                            </div>

                            <div class="feedback-combate">

                                <div id="mensajeAccion" class="mensaje-accion" aria-live="polite">

                                    ¿Cuál será tu siguiente movimiento?

                                </div>

                                <div

                                    id="indicadorTurno"

                                    class="indicador-turno"

                                    aria-live="polite"

                                ></div>

                                <span

                                    id="estadoMusica"

                                    class="sr-only"

                                    aria-live="polite"

                                ></span>

                            </div>

                            <p id="ultimoMovimiento" class="ultimo-movimiento"></p>

                            <div id="resultadoEnPanel">
                                <div id="resultadoBatalla" class="resultado-batalla" hidden></div>
                                <button id="repetirBatalla" class="btn-batalla" type="button" hidden>Volver a jugar</button>
                                <a id="volverEquipos" class="btn-secundario" href="equipos.php" hidden>Mis equipos</a>
                            </div>

                            <button id="abandonarBatalla" class="btn-abandonar" type="button">

                                Salir del combate

                            </button>

                        </div>

                    </aside>

                </div>

            </section>

            <section id="teamDuels" class="duel-room" aria-labelledby="teamDuelsTitle" hidden>
                <div id="duelLobby">
                    <header class="duel-lobby-hero">
                        <button class="btn-secundario" type="button" data-duel="modos">‹ Modos de combate</button>
                        <h2 id="teamDuelsTitle" tabindex="-1">Combate por equipos</h2>
                        <span id="duelSearchClock" class="duel-clock" hidden></span>
                        <div class="duel-mobile-tools">
                            <button type="button" data-duel-panel="duelInvites" aria-controls="duelMobilePanel" aria-haspopup="dialog">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span>Invitaciones</span><b id="duelInviteCount" hidden></b>
                            </button>
                            <button type="button" data-duel-panel="duelPlayers" aria-controls="duelMobilePanel" aria-haspopup="dialog">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 19v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m2-14a3 3 0 0 1 0 6m4 8v-2a4 4 0 0 0-3-4"/></svg><span>Entrenadores</span><b id="duelPlayerCount" hidden></b>
                            </button>
                        </div>
                    </header>
                    <div class="duel-lobby-layout">
                        <form id="duelSearch" aria-labelledby="duelSetupTitle">
                            <div class="duel-team-field"><label id="duelSetupTitle" for="duelTeam">Tu alineación</label><select id="duelTeam" required></select></div>
                            <fieldset id="duelLineup"><legend>Pokémon para el combate</legend></fieldset>
                            <div class="duel-search-actions">
                                <button class="btn-batalla" type="submit" id="duelSearchSubmit">Buscar partida <span aria-hidden="true">→</span></button>
                                <button class="btn-secundario" type="button" id="duelStop" hidden>Dejar de buscar</button>
                            </div>
                        </form>
                        <div class="duel-lobby-grid">
                            <section aria-labelledby="duelInvitesTitle"><h3 id="duelInvitesTitle">Invitaciones</h3><div id="duelInvites" tabindex="0" role="group" aria-labelledby="duelInvitesTitle"></div></section>
                            <section aria-labelledby="duelPlayersTitle"><h3 id="duelPlayersTitle">Entrenadores disponibles</h3><div id="duelPlayers" tabindex="0" role="group" aria-labelledby="duelPlayersTitle"></div></section>
                        </div>
                        <section id="duelFriendList" aria-labelledby="duelFriendTitle" hidden>
                            <h3 id="duelFriendTitle">Elige a tu rival</h3>
                            <div id="duelFriends"></div>
                            <a href="amigos.php">Administrar amistades</a>
                        </section>
                    </div>
                </div>
                <div id="duelMatch" hidden></div>
                <p id="duelStatus" role="status"></p>
                <dialog id="duelMobilePanel" aria-labelledby="duelMobileTitle">
                    <header><h3 id="duelMobileTitle"></h3><form method="dialog"><button aria-label="Cerrar" autofocus>×</button></form></header>
                    <div id="duelMobileContent"></div>
                </dialog>
                <dialog id="duelChallengePanel" aria-labelledby="duelChallengeTitle">
                    <header><h3 id="duelChallengeTitle">Reto de combate</h3></header>
                    <div id="duelChallengeContent"></div>
                    <p id="duelChallengeError" role="status"></p>
                </dialog>
                <dialog id="duelWagerPanel" aria-labelledby="duelWagerTitle">
                    <header class="duel-wager-heading">
                        <span class="coin-icon" aria-hidden="true">P</span>
                        <h3 id="duelWagerTitle">Apuesta del duelo</h3>
                        <span id="duelWagerTime" class="duel-wager-time"></span>
                    </header>
                    <dl class="duel-wager-offers">
                        <div><dt>Tu propuesta</dt><dd id="duelWagerOwn"></dd></div>
                        <div><dt>Propuesta rival</dt><dd id="duelWagerRival"></dd></div>
                    </dl>
                    <p id="duelWagerProgress" class="duel-wager-progress" role="status" hidden></p>
                    <form id="duelWagerForm" novalidate>
                        <fieldset class="duel-wager-presets">
                            <legend>Monedas por entrenador</legend>
                            <?php foreach ([50, 100, 200] as $amount): ?>
                                <button type="button" data-duel="propuesta_rapida" data-value="<?= $amount ?>"><?= $amount ?></button>
                            <?php endforeach; ?>
                        </fieldset>
                        <div class="duel-wager-custom">
                            <label class="sr-only" for="duelWagerAmount">Otro monto</label>
                            <input id="duelWagerAmount" type="text" inputmode="numeric" maxlength="7" placeholder="Otro monto" autocomplete="off" aria-describedby="duelWagerError">
                            <button class="btn-batalla" type="submit">Proponer</button>
                        </div>
                        <button class="btn-secundario" type="button" id="duelWagerMatch" data-duel="igualar_apuesta" hidden></button>
                        <button class="btn-secundario" type="button" id="duelWagerKeep" data-duel="igualar_apuesta" hidden></button>
                        <button class="btn-secundario" type="button" data-duel="sin_apuesta">Jugar sin apuesta</button>
                    </form>
                    <p id="duelWagerError" role="status"></p>
                </dialog>
            </section>
            <dialog id="resultadoMovil" class="game-option-dialog" aria-labelledby="resultadoMovilTitulo">
                <header><h2 id="resultadoMovilTitulo">Resultado del combate</h2><form method="dialog"><button aria-label="Cerrar resultado">×</button></form></header>
                <div id="resultadoEnDialogo"></div>
            </dialog>
            <dialog id="battleFaceoff" aria-labelledby="battleFaceoffTitle">
                <h2 id="battleFaceoffTitle" class="sr-only">Cara a cara</h2>
                <div class="faceoff-lineup">
                    <div class="faceoff-player"><img id="faceoffPlayerImage" width="300" height="300" alt=""></div>
                    <b class="faceoff-vs">VS</b>
                    <div class="faceoff-rival"><img id="faceoffRivalImage" width="300" height="300" alt=""></div>
                </div>
                <p id="faceoffCount" role="status" aria-atomic="true"></p>
                <progress id="faceoffProgress" max="5" value="5" aria-label="Tiempo restante para empezar"></progress>
                <form method="dialog"><button type="submit" class="btn-secundario" autofocus>Empezar ahora</button></form>
            </dialog>
            <dialog id="confirmarSalida" class="adventure-dialog" aria-labelledby="salidaTitulo" aria-describedby="salidaConsecuencia">
                <span class="battle-exit-icon" aria-hidden="true">↪</span>
                <h2 id="salidaTitulo">¿Abandonar el combate?</h2>
                <p id="salidaConsecuencia"></p>
                <form method="dialog">
                    <button class="btn-secundario" value="stay" autofocus>Seguir jugando</button>
                    <button class="btn-batalla" value="leave" id="salidaAceptar">Abandonar</button>
                </form>
            </dialog>
            <dialog id="selectorAtaques" class="adventure-dialog battle-entry-dialog attack-picker" aria-labelledby="tituloAtaques">
                <header class="battle-entry-heading"><h2 id="tituloAtaques">Elige tu ataque</h2><form method="dialog"><button class="btn-secundario" aria-label="Cerrar ataques">×</button></form></header>
                <p id="ataqueContexto"></p>
                <div id="rivalAtaque" class="attack-rival" aria-label="Pokémon rival"></div>
                <p id="errorAtaque" role="status"></p>
                <div id="movimientos" class="movimientos"></div>
                <footer class="attack-picker-actions">
                    <button id="verReservas" type="button" class="btn-secundario" hidden>
                        <span aria-hidden="true">⇄</span> Cambiar Pokémon
                    </button>
                </footer>
            </dialog>

            <dialog id="manualBatalla" class="manual-batalla" aria-labelledby="manualTitulo">

                <button

                    id="cerrarManual"

                    class="manual-cerrar"

                    type="button"

                    aria-label="Cerrar ayuda"

                >

                    ×

                </button>

                <h2 id="manualTitulo">Tu primera batalla</h2>

                <ol class="manual-cards">
                    <li><span class="manual-example manual-types">Agua <b>×2 →</b> Fuego</span><strong>Ataca con ventaja</strong><span>Elige un movimiento en tu turno.</span></li>
                    <li><span class="manual-example manual-hp"><i aria-hidden="true"></i> 48 / 80 PS</span><strong>Vigila los PS</strong><span>Si el rival se queda sin Pokémon en pie, ganas.</span></li>
                    <li><span class="manual-example manual-switch" aria-hidden="true">⇄</span><strong>Cambia de Pokémon</strong><span>Elige una reserva. El cambio usa tu turno.</span></li>
                </ol>

                <button id="manualPractica" class="btn-batalla" type="button">

                    Probar la práctica guiada</button

                ><button id="manualVolver" class="btn-secundario" type="button">Volver</button>

            </dialog>

        </main>



        <script src="assets/js/wager-ui.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/wager-ui.js',
        ) ?>"></script>

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
        <script src="assets/js/battle-effects.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/battle-effects.js',
        ) ?>"></script>

        <script src="assets/js/battle-practice.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/battle-practice.js',
        ) ?>"></script>

        <script src="assets/js/battle-tutorial.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/battle-tutorial.js',
        ) ?>"></script>

        <script src="assets/js/battle-initiative.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/battle-initiative.js') ?>"></script>

        <script src="assets/js/battle-duos.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/battle-duos.js') ?>"></script>

        <script src="assets/js/batalla.js?v=<?= filemtime(
            dirname(__DIR__) . '/assets/js/batalla.js',
        ) ?>"></script>

    </body>

</html>

