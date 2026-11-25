window.PokeDuos = (() => {
    const trigger = document.querySelector('[data-modo="equipos"]');
    const esc = PokeEconomy.esc;
    const dialog = document.createElement('dialog');
    dialog.className = 'duo-room';
    dialog.setAttribute('aria-labelledby','duoTitle');
    dialog.innerHTML = '<header><div><p>Combate por equipos</p><h2 id="duoTitle">Sala con amigos</h2></div><button type="button" data-close aria-label="Volver a los modos de combate">‹ Modos de combate</button></header><p class="duo-status" role="status"></p><div class="duo-content"></div>';
    document.body.append(dialog);
    const content = dialog.querySelector('.duo-content'), status = dialog.querySelector('.duo-status');
    const totals = { 2: [2, 4, 6], 3: [3, 6], 4: [4] };
    const formats = { 2: 'Dúo', 3: 'Tridente', 4: 'Escuadrón' };
    let model, busy = false, snapshot = '';
    const own = () => String(model.usuario_id);
    const sprite = id => PokeMarket.imagenPokemon(Number(id));
    const silhouette = 'assets/img/adventure/pikachu-welcome.png';
    const avatar = (name, id) => `<span class="duo-avatar" data-tone="${Number(id) % 3}" aria-hidden="true">${esc(name.slice(0, 1).toUpperCase())}</span>`;
    async function refresh(body) {
        const requestBody = body ? {id:model?.sala?.id,...(model?.sala?.batalla?{version:model.sala.batalla.version}:{}),...body} : null;
        while (busy) {
            if (!body) return false;
            await new Promise(resolve=>setTimeout(resolve,60));
        }
        busy = true;
        if (body) status.textContent = 'Guardando…';
        try {
            const previous = model;
            model = await PokeMarket.trainer('/duos',body ? {method:'POST',body:requestBody} : {});
            if (previous?.sala?.batalla && !model.sala) PokeInitiative.close();
            const room = model.sala;
            const label = room?.estado === 'buscando' ? 'Combate por equipos, buscando rival'
                : room?.estado === 'activa' ? 'Combate por equipos, partida activa' : '';
            if (label) trigger.setAttribute('aria-label', label);
            else trigger.removeAttribute('aria-label');
            if (body || !previous) status.textContent = body?.accion === 'invitar' ? 'Invitaciones registradas.' : '';
            if (dialog.open && (snapshot !== JSON.stringify(room) || body)) render();
            return true;
        } catch (error) { status.textContent = error.message || 'No fue posible actualizar la sala.'; return false; }
        finally { busy = false; }
    }
    function inviteFields(form) {
        const people = Number(form.elements.personas.value);
        const options = totals[people];
        form.querySelector('[data-roster-options]').innerHTML = options.map((total, index) =>
            `<label><input type="radio" name="total_pokemon" value="${total}" ${index === 0 ? 'checked' : ''}>
                <span><strong>${total / people} por persona</strong><small>${total} Pokémon en total</small></span></label>`).join('');
        const checked = [...form.querySelectorAll('[name=amigo]:checked')];
        checked.slice(people - 1).forEach(input => { input.checked = false; });
        updateRosterPreview(form);
        updateInvites(form);
    }
    function updateRosterPreview(form) {
        const total = Number(form.querySelector('[name=total_pokemon]:checked').value);
        form.querySelector('[data-roster-preview]').innerHTML = Array.from({length: total}, () =>
            `<img class="duo-silhouette" src="${silhouette}" width="30" height="30" alt="">`).join('');
    }
    function updateInvites(form) {
        const needed = Number(form.elements.personas.value) - 1;
        const checked = form.querySelectorAll('[name=amigo]:checked').length;
        form.querySelector('[data-invite-count]').textContent = `${checked} de ${needed} seleccionados`;
        form.querySelectorAll('[name=amigo]').forEach(input => { input.disabled = !input.checked && checked === needed; });
        form.querySelector('[type=submit]').disabled = checked !== needed;
        form.querySelector('[data-invite-hint]').hidden = model.amigos.length >= needed;
    }
    function render() {
        const room = model.sala;
        dialog.querySelector('#duoTitle').textContent = room?.batalla ? 'Combate en equipo' : 'Sala con amigos';
        snapshot = JSON.stringify(room);
        if (!room) {
            content.innerHTML = `<form class="duo-setup" data-invite>
                <div class="duo-setup-grid">
                    <section>
                        <fieldset><legend>Formato del equipo</legend><div class="duo-formats">${Object.entries(formats).map(([count, name]) => `
                            <label><input type="radio" name="personas" value="${count}" ${count === '2' ? 'checked' : ''}>
                            <span class="duo-format-card"><span class="duo-people" aria-hidden="true">${'<i></i>'.repeat(Number(count))}</span><strong>${name}</strong><small>${count} personas</small></span></label>`).join('')}</div></fieldset>
                        <fieldset class="duo-roster-size"><legend>Pokémon por equipo</legend><div class="duo-roster-panel">
                            <div class="duo-roster-options" data-roster-options></div>
                            <div class="duo-roster-preview" data-roster-preview aria-hidden="true"></div>
                        </div><p class="duo-help">Cada integrante elige sus Pokémon en la sala.</p></fieldset>
                    </section>
                    <fieldset class="duo-invite-panel"><legend>Invita a tus amigos</legend><p data-invite-count aria-live="polite"></p>
                        <div class="duo-friends">${model.amigos.map(friend => `<label><input type="checkbox" name="amigo" value="${Number(friend.id)}"><span class="duo-friend-card">${avatar(friend.usuario, friend.id)}<strong>@${esc(friend.usuario)}</strong><span class="duo-invite-check" aria-hidden="true">✓</span></span></label>`).join('')}</div>
                        <p class="duo-help" data-invite-hint>Agrega más amigos para completar este formato.</p>
                    </fieldset>
                </div>
                <div class="duo-setup-actions"><a href="amigos.php" class="duo-friend-link">Mi lista de amigos</a><button type="submit" class="duo-primary">Crear sala e invitar</button></div>
            </form>`;
            inviteFields(content.querySelector('form'));
            return;
        }
        if (room.batalla) { renderBattle(room); return; }
        const data = room.datos;
        const config = data.configuracion;
        const accepted = room.integrantes.find(member => member.id === model.usuario_id)?.aceptado;
        const complete = room.integrantes.every(member => member.aceptado && data.selecciones[member.id]?.pokemones.length === config.por_persona);
        const memberList = room.integrantes.map(member => {
            const selected = data.selecciones[member.id]?.pokemones;
            const readiness = selected?.length ? 'Listo' : member.aceptado ? 'Eligiendo Pokémon' : 'Invitación pendiente';
            return `<li><div class="duo-member-heading">${avatar(member.usuario, member.id)}<div><strong>@${esc(member.usuario)}${member.id === model.usuario_id ? ' · Tú' : ''}</strong><span class="duo-member-state" data-ready="${Boolean(selected?.length)}">${readiness}</span></div></div>
                ${selected?.length ? `<ul class="duo-selection">${selected.map(p => `<li><img src="${sprite(p.pokemon_id)}" width="36" height="36" alt=""><span>${esc(p.nombre)}</span></li>`).join('')}</ul>`
                    : ''}</li>`;
        }).join('');
        let controls;
        if (!accepted) controls = '<button type="button" class="duo-primary" data-action="aceptar">Aceptar invitación</button>';
        else if (room.estado === 'buscando') controls = '<p class="duo-search" role="status">Buscando un equipo con el mismo reparto…</p><button type="button" class="duo-secondary" data-action="detener">Detener búsqueda</button>';
        else {
            const selected = data.selecciones[model.usuario_id]?.pokemones || [];
            controls = `<form class="duo-choice" data-choice><h3>Tu alineación</h3><div class="duo-choice-grid">${Array.from({length:config.por_persona}, (_, index) => `<label><img class="duo-choice-art${selected[index] ? '' : ' duo-silhouette'}" src="${selected[index] ? sprite(selected[index].pokemon_id) : silhouette}" width="48" height="48" alt=""><span>${index === 0 ? 'Pokémon inicial' : `Reserva ${index}`}<select name="pokemon" required><option value="">Elige desde tus equipos</option>${model.opciones.map(p => {
                const value = `${Number(p.equipo_id)}:${Number(p.pokemon_id)}`;
                return `<option value="${value}" ${selected[index] && value === `${selected[index].equipo_id}:${selected[index].pokemon_id}` ? 'selected' : ''}>${esc(p.equipo)} · ${esc(p.nombre)}</option>`;
            }).join('')}</select></span></label>`).join('')}</div><button type="submit" class="duo-secondary">Confirmar mi selección</button></form>`;
            if (room.anfitrion === model.usuario_id) controls += `<button type="button" class="duo-primary" data-action="buscar" ${complete ? '' : 'disabled'}>Buscar equipo rival</button>`;
            if (!complete) controls += '<p class="duo-help">La búsqueda empieza cuando todos acepten y elijan sus Pokémon.</p>';
        }
        content.innerHTML = `<div class="duo-room-summary"><img class="duo-silhouette" src="${silhouette}" alt="" width="42" height="42"><div><strong>${formats[config.personas]} · ${config.total} Pokémon</strong><small>${config.por_persona} por persona</small></div></div>
            <ul class="duo-members">${memberList}</ul>${controls}<button type="button" class="duo-quiet" data-action="salir">Salir de la sala</button>`;
    }
    function renderBattle(room) {
        const game = room.batalla, me = game.jugadores[own()];
        if (!me) return;
        const finished = room.estado === 'finalizada';
        const roster = player => player.pokemon_equipo || [player.pokemon];
        const reserves = roster(me).filter((p,index) => index !== (me.activo ?? 0) && p.hp_actual > 0);
        const config = game.configuracion || room.datos.configuracion;
        const myTurn = Number(game.turno)===model.usuario_id && !finished && game.iniciativa.orden.length>0;
        const team = number => Object.values(game.jugadores).filter(p=>p.equipo===number).map(p=>`<article class="duo-fighter ${Number(game.turno)===p.id&&!finished?'is-turn':''} ${p.pokemon.hp_actual<=0?'is-fainted':''}"><span>@${esc(p.usuario)}${p.id===model.usuario_id?' · Tú':''}</span><img src="${sprite(p.pokemon.id)}" width="110" height="110" alt="${esc(p.pokemon.nombre)}"><strong>${esc(p.pokemon.nombre)}</strong><progress max="${Number(p.pokemon.hp_maximo)}" value="${Number(p.pokemon.hp_actual)}" aria-label="Salud de ${esc(p.pokemon.nombre)}"></progress><small>${Number(p.pokemon.hp_actual)} / ${Number(p.pokemon.hp_maximo)} PS</small></article>`).join('');
        const enemies = Object.values(game.jugadores).filter(p=>p.equipo!==me.equipo&&p.pokemon.hp_actual>0);
        const title = finished ? game.ganador===-1?'Empate':game.ganador===me.equipo?'¡Tu equipo ganó!':'Victoria del equipo rival' : game.turno ? 'Turno de @'+game.jugadores[game.turno].usuario : 'Decidan el orden antes de combatir';
        content.innerHTML = `<h3 class="duo-turn-title">${esc(title)}</h3><p class="duo-help">Ronda ${game.ronda} · ${config.personas} contra ${config.personas} · ${config.total} Pokémon por lado</p><div class="duo-teams"><section><h4>Tu equipo</h4><div>${team(me.equipo)}</div></section><section><h4>Equipo rival</h4><div>${team(1-me.equipo)}</div></section></div>${game.iniciativa.orden.length?`<ol class="duo-order" aria-label="Orden de turnos">${game.iniciativa.orden.map(id=>`<li ${Number(game.turno)===Number(id)?'aria-current="step"':''}>@${esc(game.jugadores[id].usuario)}</li>`).join('')}</ol>`:''}${myTurn?`<label>Objetivo<select class="duo-target">${enemies.map(p=>`<option value="${p.id}">@${esc(p.usuario)} · ${esc(p.pokemon.nombre)}</option>`).join('')}</select></label><div class="duo-attacks">${(me.pokemon.movimientos||[]).map(m=>`<button class="duo-secondary" data-move="${esc(m.nombre)}"><strong>${esc(m.nombre_mostrado)}</strong><small>Potencia ${Number(m.potencia)}</small></button>`).join('')}</div>`:''}${myTurn && reserves.length ? `<div class="duo-attacks">${reserves.map(p => `<button type="button" class="duo-secondary" data-switch="${Number(p.id)}">Cambiar a ${esc(p.nombre)}</button>`).join('')}</div>` : ''}<p class="duo-history" role="status">${esc(game.historial.at(-1)||(game.iniciativa.orden.length?'Orden confirmado. Cada persona controla su propio Pokémon.':'Todos deben confirmar la preparación.'))}</p><button class="duo-quiet" data-action="salir" data-busy>${finished?'Cerrar partida':'Rendir a mi equipo'}</button>`;
        if (!finished) PokeInitiative.show({
            key:'duo-'+game.id,state:game.iniciativa,own:own(),system:false,
            players:Object.fromEntries(Object.values(game.jugadores).map(p=>[String(p.id),{name:'@'+p.usuario+(p.equipo===me.equipo?' · Tu equipo':' · Rival'),roster:roster(p).map(pokemon => pokemon.nombre).join(' · ')}])),
            ready:async()=>{if(!await refresh({accion:'iniciativa'}))throw new Error(status.textContent||'No se pudo confirmar. Intenta nuevamente.');},
            exit:async()=>{if(!confirm('Salir rinde a tu equipo. ¿Quieres salir?'))return false;await refresh({accion:'salir'});return model.sala?.estado==='finalizada';},
        });
        else PokeInitiative.close();
    }
    dialog.addEventListener('change', event => {
        if (event.target.name === 'personas') inviteFields(event.target.form);
        if (event.target.name === 'total_pokemon') updateRosterPreview(event.target.form);
        if (event.target.name === 'amigo') updateInvites(event.target.form);
        if (event.target.name === 'pokemon') {
            const id = event.target.value.split(':')[1];
            const preview = event.target.closest('label').querySelector('img');
            preview.src = id ? sprite(id) : silhouette;
            preview.classList.toggle('duo-silhouette', !id);
        }
    });
    dialog.addEventListener('submit', event => {
        event.preventDefault();
        const form = event.target;
        const submit = form.querySelector('button');
        if (submit?.disabled) return;
        if (form.matches('[data-invite]')) {
            const amigos = Array.from(form.querySelectorAll('[name=amigo]:checked'), input => Number(input.value));
            const restore = PokeMarket.esperarBoton(submit, 'Enviando invitaciones…');
            const total = form.querySelector('[name=total_pokemon]:checked').value;
            refresh({accion:'invitar', amigos, personas:Number(form.elements.personas.value), total_pokemon:Number(total)}).finally(() => {
                if (form.isConnected) { restore(); updateInvites(form); }
            });
        }
        if (form.matches('[data-choice]')) {
            const pokemones = Array.from(form.querySelectorAll('[name=pokemon]'), select => {
                const [equipo_id,pokemon_id] = select.value.split(':').map(Number);
                return {equipo_id,pokemon_id};
            });
            if (new Set(pokemones.map(p => p.pokemon_id)).size !== pokemones.length) { status.textContent = 'Selecciona Pokémon distintos.'; return; }
            const restore = PokeMarket.esperarBoton(submit, 'Guardando equipo…');
            refresh({accion:'elegir', pokemones}).finally(() => { if (submit.isConnected) restore(); });
        }
    });
    dialog.addEventListener('click',event=>{
        const close=event.target.closest('[data-close]');if(close){dialog.close();return;}
        const button=event.target.closest('[data-action],[data-move],[data-switch]');if(!button||button.disabled)return;
        if(button.dataset.action==='salir'&&model.sala?.estado==='activa'&&!confirm('Tu equipo perderá por rendición. ¿Quieres salir?'))return;
        const action = button.dataset.switch
            ? {accion:'cambiar',pokemon_id:Number(button.dataset.switch)}
            : button.dataset.move
                ? {accion:'atacar',movimiento:button.dataset.move,objetivo:Number(dialog.querySelector('.duo-target').value)}
                : {accion:button.dataset.action};
        const label = { aceptar: 'Aceptando…', buscar: 'Iniciando búsqueda…', detener: 'Deteniendo…', salir: 'Saliendo…', cambiar: 'Cambiando…', atacar: 'Atacando…' };
        const restore = PokeMarket.esperarBoton(button, label[action.accion] || 'Guardando…');
        refresh(action).finally(() => { if (button.isConnected) restore(); });
    });
    dialog.addEventListener('close', () => trigger.focus({preventScroll: true}));
    async function open() {
        if (!dialog.open) dialog.showModal();
        if (!model) {
            status.textContent = 'Cargando sala…';
            await refresh();
        }
        if (model) { status.textContent = ''; render(); }
    }
    PokeEconomy.ready.then(async()=>{ await refresh(); if(new URLSearchParams(location.search).get('sala')==='duos') open(); }).catch(()=>{});
    setInterval(()=>{if(!document.hidden)refresh();},3000);
    return { open, get active(){return model?.sala?.estado==='activa';} };
})();
