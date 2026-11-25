window.PokeInitiative = (() => {
    const dialog = document.createElement('dialog');
    dialog.className = 'battle-initiative';
    dialog.setAttribute('aria-labelledby', 'initiativeTitle');
    dialog.innerHTML = '<p class="initiative-kicker">Preparación del combate</p><h2 id="initiativeTitle">¿Quién empieza?</h2><p class="initiative-help"></p><div class="initiative-tray" aria-hidden="true"><span class="initiative-die"></span></div><ol class="initiative-players"></ol><p class="initiative-status" role="status"></p><button type="button" class="initiative-confirm">Lanzar el dado</button><button type="button" class="initiative-exit">Salir de la preparación</button>';
    document.body.append(dialog);
    const get = selector => dialog.querySelector(selector);
    const die = get('.initiative-die');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current, sending = false, rolledKey, roll;
    const readSeen = key => { try { return sessionStorage.getItem('pokemarket.iniciativa.' + key) === 'vista'; } catch (_) { return false; } };
    const markSeen = key => { try { sessionStorage.setItem('pokemarket.iniciativa.' + key, 'vista'); } catch (_) {} };
    dialog.addEventListener('cancel', event => event.preventDefault());
    function show(config) {
        if (!config.state) return false;
        current = config;
        const hasOrder = config.state.orden.length > 0;
        if (!hasOrder) {
            try { sessionStorage.removeItem('pokemarket.iniciativa.' + config.key); } catch (_) {}
            if (rolledKey === config.key) {
                roll?.cancel();
                roll = null;
                rolledKey = null;
            }
        }
        if (hasOrder && readSeen(config.key)) { if (dialog.open) dialog.close(); return false; }
        if (config.key !== rolledKey) {
            roll?.cancel();
            roll = null;
        }
        if (hasOrder && rolledKey !== config.key) {
            rolledKey = config.key;
            if (!reducedMotion.matches && die.animate) {
                const animation = die.animate([
                    { transform: 'translate(-34px, 0) rotate(-26deg) rotateX(0deg)' },
                    { transform: 'translate(20px, -15px) rotate(320deg) rotateX(540deg)', offset: 0.55 },
                    { transform: 'translate(0, 0) rotate(720deg) rotateX(1080deg)' },
                ], { duration: 1500, easing: 'cubic-bezier(0.23, 1, 0.32, 1)' });
                roll = animation;
                animation.onfinish = () => {
                    if (roll !== animation) return;
                    roll = null;
                    if (dialog.open && current?.key === config.key) show(current);
                };
            }
        }
        const rolling = Boolean(roll);
        const revealed = hasOrder && !rolling;
        const faces = (config.state.caras || Object.keys(config.state.listos)).map(String);
        const face = revealed ? faces.indexOf(String(config.state.orden[0])) + 1 : 0;
        die.dataset.sides = faces.length;
        die.textContent = face || (revealed ? '✓' : '?');
        get('#initiativeTitle').textContent = revealed ? 'Orden de turnos' : rolling ? 'Rodando el dado…' : '¿Quién empieza?';
        get('.initiative-help').textContent = revealed
            ? 'Se mantiene este orden durante el combate. Los relevos conservan el turno de su equipo.'
            : config.system ? 'Cada número corresponde a un participante. Lanza el dado para saber quién empieza.' : 'Cada participante tiene un número. El dado se lanza cuando todos confirmen.';
        const ids = revealed ? config.state.orden : faces;
        get('.initiative-players').replaceChildren(...ids.map((id, index) => {
            const player = config.players[id];
            const item = document.createElement('li');
            const position = document.createElement('span');
            position.className = 'initiative-position';
            position.textContent = revealed ? String(index + 1).padStart(2, '0') : String(index + 1);
            const identity = document.createElement('div');
            const name = document.createElement('strong'); name.textContent = player.name;
            const roster = document.createElement('small'); roster.textContent = player.roster;
            identity.append(name, roster);
            const status = document.createElement('span'); status.className = 'initiative-player-status';
            status.textContent = revealed ? index === 0 ? 'Abre el combate' : 'Después' : config.state.listos[id] ? 'Listo' : 'Pendiente';
            item.append(position, identity, status);
            return item;
        }));
        const ownReady = config.state.listos[config.own];
        const winner = config.system && ids[0] === config.own ? 'Tú empiezas.' : `Empieza ${config.players[ids[0]].name}.`;
        get('.initiative-status').textContent = revealed ? (face ? `Salió ${face}. ${winner}` : winner) : rolling ? 'Sorteando el orden de turnos…' : ownReady ? 'Ya confirmaste. Esperando a los demás…' : 'Pulsa cuando estés preparado.';
        const confirm = get('.initiative-confirm');
        confirm.disabled = sending || rolling || (!revealed && ownReady);
        confirm.textContent = rolling ? 'Sorteando…' : sending ? 'Confirmando…' : revealed ? 'Entrar al combate' : ownReady ? 'Esperando participantes…' : 'Lanzar el dado';
        get('.initiative-exit').disabled = sending;
        if (!dialog.open) dialog.showModal();
        return true;
    }
    get('.initiative-confirm').onclick = async () => {
        if (sending || roll || !current) return;
        const config = current;
        if (config.state.orden.length) {
            markSeen(config.key); dialog.close(); config.enter?.(); return;
        }
        sending = true; show(config);
        let errorMessage;
        try { await config.ready(); }
        catch (error) { errorMessage = error.message || 'No se pudo confirmar. Intenta nuevamente.'; }
        finally { sending = false; if (current) show(current); }
        if (errorMessage) get('.initiative-status').textContent = errorMessage;
    };
    get('.initiative-exit').onclick = async () => {
        if (sending || !current) return;
        if (await current.exit?.()) dialog.close();
    };
    return { show, close() { if (dialog.open) dialog.close(); } };
})();
