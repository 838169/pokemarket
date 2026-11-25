<?php $activePage = $activePage ?? ''; ?>
<a id="friendDuelNotice" class="friend-duel-notice" href="batalla.php?sala=amigos" hidden></a>
<header class="catalog-header game-header">
    <a class="brand" href="catalogo.php" aria-label="PokéMarket, catálogo"
        ><span class="brand-ball"></span
        ><span class="brand-name"
            ><span class="brand-poke">Poké</span><span class="brand-market">Market</span></span
        ></a
    >
    <button class="game-menu-toggle" type="button" aria-controls="gameNavigation" aria-expanded="false" aria-label="Abrir menú"><span></span><span></span><span></span></button>
    <nav id="gameNavigation" class="catalog-nav" aria-label="Navegación principal">
        <?php foreach (
            [
                'catalogo.php' => 'Catálogo',
                'explorar.php' => 'Explorar',
                'pokeballs.php' => 'Poké Balls',
                'alimentos.php' => 'Alimentos',
                'coleccion.php' => 'Hogar Pokémon',
                'equipos.php' => 'Mis equipos',
                'batalla.php' => 'Batalla',
                'amigos.php' => 'Amigos',
            ]
            as $url => $label
        ): ?>
        <a href="<?= $url ?>" <?= $activePage === $url
    ? 'class="active" aria-current="page"'
    : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
        <button class="game-settings-link" type="button" data-game-settings aria-haspopup="dialog">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="M10 3h4l.7 2.7 2 .9 2.5-.9 2 3.5-1.8 2v2l1.8 2-2 3.5-2.5-.9-2 .9L14 21h-4l-.7-2.3-2-.9-2.5.9-2-3.5 1.8-2v-2l-1.8-2 2-3.5 2.5.9 2-.9z"/><circle cx="12" cy="12" r="3"/></svg>
            Configuración
        </button>
        <a id="cerrarSesion" href="login.php" class="logout-link">Salir</a>
    </nav>
    <div class="catalog-user">
        <button id="openGameWallet" type="button" class="wallet-pill" aria-label="Ver saldo de monedas" aria-haspopup="dialog" aria-controls="gameWallet"
            ><span class="coin-icon" aria-hidden="true">P</span><span data-wallet>…</span
            ><span class="wallet-name">monedas</span></button
        >
        <button id="openGameProfile" class="game-profile-button" type="button" aria-label="Abrir perfil" aria-haspopup="dialog">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 21v-2a5 5 0 0 1 5-5h5a5 5 0 0 1 5 5v2"/></svg>
        </button>
    </div>
</header>
<dialog id="gameWallet" class="game-option-dialog" aria-labelledby="gameWalletTitle">
    <header><h2 id="gameWalletTitle">Tu saldo</h2><form method="dialog"><button aria-label="Cerrar saldo" autofocus>×</button></form></header>
    <p class="game-profile-wallet"><span class="coin-icon" aria-hidden="true"></span><strong data-wallet>…</strong> monedas</p>
</dialog>
<dialog id="gameProfile" class="game-option-dialog game-profile" aria-labelledby="gameProfileTitle" data-profile-view="profile">
    <header><h2 id="gameProfileTitle">Tu perfil</h2><form method="dialog"><button aria-label="Cerrar perfil" autofocus>×</button></form></header>
    <div class="profile-switch" role="group" aria-label="Vista del perfil">
        <button type="button" data-profile-tab="profile" aria-pressed="true">Perfil</button>
        <button type="button" data-profile-tab="ranking" aria-pressed="false">Top global</button>
    </div>
    <div class="profile-body">
        <p id="trainerProfileStatus" role="status"></p>
        <div class="profile-overview">
            <section id="trainerProgress" class="trainer-progress" aria-label="Progreso de entrenador" hidden>
                <div class="trainer-rank"><span id="trainerLevel"></span><strong id="trainerRank"></strong></div>
                <label for="trainerLevelProgress" id="trainerLevelCaption">Progreso de nivel</label>
                <progress id="trainerLevelProgress" max="100" value="0"></progress>
                <p id="trainerScore"></p>
            </section>
            <div class="game-profile-identity"><span id="usuarioAvatar" class="user-avatar" aria-hidden="true"></span><dl><div><dt>Nombres y apellidos</dt><dd id="usuarioNombre">Entrenador Pokémon</dd></div><div><dt>Usuario</dt><dd class="profile-handle"><span id="trainerHandle">…</span><button id="editTrainerHandle" type="button" aria-label="Editar nombre de usuario" aria-controls="trainerHandleForm" aria-expanded="false" hidden><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 5 5M4 20l5-1L20 8l-4-4L5 15z"/></svg></button></dd></div><div><dt>Correo</dt><dd id="usuarioEmail"></dd></div></dl></div>
            <form id="trainerHandleForm" class="profile-handle-form" hidden>
                <label for="trainerHandleInput">Nuevo nombre de usuario</label>
                <div class="profile-handle-actions">
                    <input id="trainerHandleInput" autocomplete="username" autocapitalize="none"
                           spellcheck="false" pattern="[A-Za-z0-9_]{3,24}" minlength="3" maxlength="24"
                           aria-describedby="trainerHandleHint trainerHandleQuota" required>
                    <button id="saveTrainerHandle" type="submit">Guardar</button>
                </div>
                <p id="trainerHandleHint">De 3 a 24 letras sin tilde, números o guiones bajos.</p>
                <div class="username-ticket profile-username-ticket"><span class="ticket-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 5 5 5M4 20l5-1L20 8l-4-4L5 15z"/><path d="M13 20h7"/></svg></span><span><b>Cambio de usuario</b><small id="trainerHandleQuota" tabindex="-1"></small></span></div>
                <p id="trainerHandleFeedback" role="status"></p>
            </form>
            <section id="accountSecurity" aria-labelledby="accountSecurityTitle">
                <div class="account-security-heading"><h3 id="accountSecurityTitle">Seguridad</h3><span id="accountTwoStepState" role="status"></span></div>
                <div class="account-security-options">
                    <button type="button" id="openVerifyEmail" aria-haspopup="dialog" aria-controls="accountSecurityDialog" aria-disabled="true">
                        <span class="security-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
                        <span><strong>Correo</strong><small id="accountSecurityState">Consultando…</small></span>
                    </button>
                    <button type="button" id="openSetupTotp" aria-haspopup="dialog" aria-controls="accountSecurityDialog" aria-disabled="true">
                        <span class="security-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><circle cx="12" cy="11" r="2"/><path d="M12 13v3"/></svg></span>
                        <span><strong>Autenticador</strong><small id="accountTotpState">Consultando…</small></span>
                    </button>
                </div>
            </section>
            <p class="game-profile-wallet"><span class="coin-icon" aria-hidden="true">P</span> <strong data-wallet>…</strong> monedas</p>
        </div>
        <section class="profile-ranking" aria-labelledby="trainerTopTitle">
            <div class="profile-ranking-heading"><h3 id="trainerTopTitle">Top global</h3><span id="trainerPosition"></span></div>
            <ol id="trainerLeaderboard" tabindex="0" aria-labelledby="trainerTopTitle"></ol>
        </section>
    </div>
</dialog>
<dialog id="accountSecurityDialog" class="game-option-dialog verification-dialog" aria-labelledby="accountSecurityDialogTitle">
    <header><h2 id="accountSecurityDialogTitle">Seguridad de la cuenta</h2><form method="dialog"><button aria-label="Volver al perfil" autofocus>×</button></form></header>
    <div class="account-security-step">
                <form id="verifyAccountEmail" hidden>
                    <div id="verifyEmailFields" hidden>
                        <label for="verifyEmailCode">Código del correo</label>
                        <input id="verifyEmailCode" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" aria-errormessage="accountSecurityFeedback" required disabled>
                        <button class="verification-primary" type="submit">Verificar correo</button>
                    </div>
                    <button type="button" id="sendAccountCode">Enviar código al correo</button>
                </form>
                <form id="setupAccountTotp" hidden>
                    <label for="accountTotpPassword">Contraseña actual</label>
                    <div class="profile-handle-actions"><input id="accountTotpPassword" type="password" autocomplete="current-password" maxlength="72" required><button type="submit">Continuar</button></div>
                </form>
                <form id="enableAccountTotp" hidden>
                    <p>Agrega esta clave en tu app como código basado en tiempo.</p>
                    <code id="accountTotpSecret"></code>
                    <label for="accountTotpCode">Código del autenticador</label>
                    <input id="accountTotpCode" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" aria-errormessage="accountSecurityFeedback" required>
                    <button class="verification-primary" type="submit">Activar autenticador</button>
                </form>
                <p id="accountSecurityFeedback" class="verification-status" role="status"></p>
    </div>
</dialog>
<script defer src="assets/js/verification-code.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/verification-code.js') ?>"></script>
