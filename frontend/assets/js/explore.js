(() => {
    const $ = (id) => document.getElementById(id);
    const E = PokeEconomy,
        esc = E.esc;
    const guide = $('exploreGuide'),
        album = $('exploreAlbum'),
        encounter = $('exploreEncounter'),
        canvas = $('exploreCanvas');
    const siteNames = [
        'Claro de las flores',
        'Sendero de los brotes',
        'Hierba del campamento',
        'Ribera del puente',
        'Rincón de las mariposas',
    ];
    const steps = [
        [
            'Tu primera expedición',
            'Camina con las flechas o WASD. En el móvil usa la cruceta del mapa. Pulsar el terreno no desplaza al entrenador; toca un Pokémon cuando lo encuentres para interactuar.',
        ],
        [
            'Aprende a rastrear',
            'Ya no hay señales que garanticen Pokémon. Recorre hierba alta nueva y descubre la región entre la niebla. Caminar en silencio favorece los encuentros; correr hace ruido. Repetir las mismas casillas no cuenta como un nuevo rastro. Cuando aparezca un Pokémon caminando, pulsa sobre él o en «Acercarme»; correr cerca puede espantarlo.',
        ],
        [
            'Prepara tu mochila',
            'Empiezas con un minuto de movimiento. El tiempo se pausa al detenerte y durante las capturas. Las misiones dan 20 segundos o un minuto más. Siempre puedes regresar gratis al campamento. Mira los arbustos que se agitan: puede haber un Pokémon escondido.',
        ],
    ];
    let state,
        map,
        world,
        busy = false,
        pending = null,
        storageKey = '',
        guideIndex = 0,
        albumPage = 0,
        albumFlipping = false,
        selectedBall = 'poke',
        queuedDestination = null,
        running = false,
        shiftHeld = false,
        heldDirection = null,
        visualJourney = Promise.resolve(),
        visualEndsAt = 0,
        movementReadyAt = 0,
        preview = null,
        walkingQueue = [],
        walkingMode = null,
        walkingPosition = null,
        walkingTimer = null,
        nextWalkingStep = 0,
        followingWildlife = null;
    let captureAnimating = false;
    window.pokeNavigationLocked = () =>
        Boolean(captureAnimating || state?.encuentro || pending || walkingQueue.length);

    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    encounter.classList.add('capture-effect');
    $('encounterImage').classList.add('effect-target');
    $('captureBallArt').parentElement.classList.add('effect-ball');
    window.PokeBallEffects?.mount(encounter.querySelector('.exp-encounter-scene'));
    const image = (id) =>
        PokeMarket.imagenPokemon(Number(id));
    let statusTimer;
    const message = (text) => {
        const status = $('exploreStatus');
        clearTimeout(statusTimer);
        status.textContent = text;
        status.hidden = !text;
        statusTimer = setTimeout(() => {
            status.hidden = true;
        }, 4500);
    };
    const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    function stored(value) {
        try {
            if (value) sessionStorage.setItem(storageKey, JSON.stringify(value));
            else sessionStorage.removeItem(storageKey);
        } catch (_) {}
    }
    function restore() {
        try {
            const d = JSON.parse(sessionStorage.getItem(storageKey));
            if (
                d &&
                [
                    'travel',
                    'capture',
                    'leave',
                    'supplies',
                    'tutorial',
                    'observe',
                    'mission',
                    'album',
                ].includes(d.action) &&
                /^[a-f0-9]{32}$/.test(d.body?.solicitud)
            )
                pending = d;
        } catch (_) {}
    }
    function controls() {
        document.querySelectorAll('[data-mission]').forEach((button) => {
            button.disabled =
                busy ||
                Date.now() < visualEndsAt ||
                Boolean(state?.encuentro) ||
                button.dataset.ready !== 'true';
        });

        document.querySelectorAll('[data-ball-buy]').forEach((button) => {
            button.disabled = busy || button.dataset.ready !== 'true';
        });
        document
            .querySelectorAll('.exp-site')
            .forEach((b) => (b.disabled = busy || Boolean(state?.encuentro)));
        document
            .querySelectorAll('[data-direction]')
            .forEach((b) => (b.disabled = Boolean(state?.encuentro)));
        const capture = $('capturePokemon');
        const requirement = state?.encuentro
            ? state.estampas?.includes(state.encuentro.pokemon.id)
                ? 'Ya tienes esta estampa'
                : (state.bolas?.[selectedBall] ?? state.pokeballs) < 1
                    ? 'Necesitas una Poké Ball'
                    : ''
            : '';
        capture.disabled = busy || captureAnimating || !state?.encuentro || Boolean(requirement);
        capture.dataset.gameLocked = String(Boolean(requirement));
        if (state?.encuentro && !capture.hidden) capture.textContent = requirement || 'Lanzar Poké Ball';
        $('leaveEncounter').disabled = busy;

        $('guideNext').disabled = busy;
        $('guideClose').disabled = busy;
    }
    function radar() {
        if (!state) return;
        const cycle = world?.environment?.();
        if (cycle) {
            $('exploreCycle').textContent =
                `${cycle.label} · ${Math.floor(cycle.remaining / 60)}:${String(cycle.remaining % 60).padStart(2, '0')}`;
            $('exploreCycle').setAttribute('data-phase', cycle.label);
        }
        if (map.modo === 'expedicion') {
            const remaining = Math.max(0, Math.ceil((state.tiempo_ms ?? 60000) / 1000));
            const clock = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
            const known = state.explorado?.length || 0;
            $('exploreVisited').textContent =
                `${Math.round((known / (map.terreno.length * map.terreno[0].length)) * 100)}%`;
            $('exploreSites').innerHTML =
                `<div class="exp-field-card"><span>Tiempo al caminar</span><strong class="exp-time ${remaining <= 10 ? 'low' : ''}">${clock}</strong></div><div class="exp-tracking"><strong>${remaining <= 0 ? 'Reclama una misión' : running || shiftHeld ? 'Pasos ruidosos' : state.avistamiento ? 'Hay movimiento cerca' : 'Busca hierba nueva'}</strong></div>`;
            $('exploreReturn').disabled =
                busy ||
                Date.now() < visualEndsAt ||
                Boolean(state.encuentro) ||
                (state.posicion[0] === map.campamento[0] &&
                    state.posicion[1] === map.campamento[1]);
            return;
        }
        $('exploreSites').innerHTML = map.puntos
            .map((point, i) => {
                const remaining = Math.max(
                    0,
                    Math.ceil((state.enfriamientos[i] || 0) - Date.now() / 1000)
                );
                return `<button type="button" class="exp-site" data-site="${i}" ${busy || state.encuentro ? 'disabled' : ''}><span class="exp-site-marker" aria-hidden="true"></span><span>${siteNames[i]}</span><small>${remaining ? `Vuelve en ${remaining}s` : 'Ir a buscar'}</small></button>`;
            })
            .join('');
    }
    function render(movementOnly = false) {
        $('exploreBalls').textContent = state.pokeballs;
        $('exploreDaily').textContent =
            `${(state.paquetes_hoy || 0) * 3}/${state.limite_paquetes_diario * 3} Poké Balls compradas hoy`;
        $('exploreVisited').textContent = `${state.visitados.length} / ${map.puntos.length}`;
        if (!movementOnly) {
            renderAlbum();
            renderBag();
        }
        const near = map.puntos.findIndex(
            (p) => p[0] === state.posicion[0] && p[1] === state.posicion[1]
        );
        $('exploreLocation').textContent = near >= 0 ? siteNames[near] : 'Pradera del Alba';
        $('explorePosition').textContent =
            `Posición ${state.posicion[0]}, ${state.posicion[1]} · Progreso guardado`;
        $('exploreSave').textContent = '✓';
        $('exploreSave').setAttribute('aria-label', 'Progreso guardado');
        $('exploreSave').title = 'Progreso guardado';
        world.sites = state.enfriamientos;
        world.explored = new Set(state.explorado || []);
        world.setWildlife?.(state.avistamiento);
        world.onWildlife = approachWildlife;
        $('exploreMissions').innerHTML = (state.misiones || [])
            .map((mission) => {
                const ready = !mission.cobrada && mission.progreso >= mission.meta;
                const name =
                    {
                        campamento: 'Preparar equipo',
                        cartografia: 'Descubrir terreno',
                        batalla: 'Completar una batalla',
                    }[mission.id] || mission.nombre;
                return `<article class="exp-mission ${mission.cobrada ? 'is-claimed' : ''}" title="${esc(mission.detalle)}"><div><h3>${esc(name)}</h3><small>${mission.cobrada ? 'Recompensa recibida' : `${mission.progreso} / ${mission.meta}`}</small></div><button class="market-secondary" type="button" data-mission="${esc(mission.id)}" data-ready="${ready}" data-game-locked="${!ready && !mission.cobrada}" aria-label="${mission.cobrada ? 'Misión reclamada' : ready ? `Reclamar ${mission.segundos} segundos` : `Completa la misión para reclamar ${mission.segundos} segundos`}" ${ready ? '' : 'disabled'}>${mission.cobrada ? '<span class="mission-check" aria-hidden="true">✓</span>' : `+${mission.segundos}s`}</button>${mission.id === 'batalla' && !ready && !mission.cobrada ? '<a href="batalla.php">Ir a combatir</a>' : ''}</article>`;
            })
            .join('');
        $('exploreWildlife').hidden = !state.avistamiento;
        if (state.avistamiento) {
            const sighting = state.avistamiento;
            $('wildlifeName').textContent = sighting.pokemon.nombre;
            $('wildlifeBehaviour').textContent =
                sighting.modo === 'huyendo'
                    ? '¡Se aleja corriendo!'
                    : sighting.perfil === 'corredor'
                      ? 'Rápido y asustadizo'
                      : sighting.perfil === 'saltarín'
                        ? 'Se mueve dando saltos'
                        : 'Paseando entre la hierba';
            $('wildlifeApproach').disabled =
                busy ||
                Date.now() < visualEndsAt ||
                Boolean(state.encuentro) ||
                sighting.modo === 'huyendo';
        }
        radar();
        wildlifeClue();
        controls();
    }
    function wildlifeClue() {
        if (!state?.avistamiento) return;
        const hidden = world.isWildlifeHidden?.();
        $('wildlifeName').textContent = hidden
            ? 'Algo en el arbusto…'
            : state.avistamiento.pokemon.nombre;
        $('wildlifeBehaviour').textContent = hidden
            ? 'Las hojas se están moviendo. Acércate en silencio.'
            : state.avistamiento.modo === 'huyendo'
              ? '¡Se aleja corriendo!'
              : 'Está recorriendo la zona. Puedes seguirlo.';
    }
    function categoryPrize(category) {
        const food = { baya: ['Cestas de bayas', 'food-berries.svg'], curry: ['Curry casero', 'food-curry.svg'], galleta: ['Pokochos de bayas', 'food-poffin.svg'] };
        const item = (name, quantity, icon) => `<span class="album-prize-item"><img src="assets/img/${icon}" width="38" height="38" alt=""><span><b>×${quantity}</b><small>${esc(name)}</small></span></span>`;
        const rewards = [item('Monedas', category.monedas, 'currency-pokedollar.svg')];
        for (const [type, quantity] of Object.entries(category.bolas))
            rewards.push(item(state.tipos_bolas[type].nombre, quantity, `balls/${state.tipos_bolas[type].icono}`));
        for (const [type, quantity] of Object.entries(category.comida))
            rewards.push(item(food[type][0], quantity, food[type][1]));
        if (category.tarjetas) rewards.push(`<div class="username-ticket"><span class="ticket-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 5 5 5M4 20l5-1L20 8l-4-4L5 15z"/><path d="M13 20h7"/></svg></span><span><b>Cambio de usuario</b><small>Tarjeta · 1 uso</small></span><span class="ticket-stub">×${category.tarjetas}</span></div>`);
        if (category.pokemon) rewards.push(`<div class="album-legend-prize"><img src="${image(category.pokemon.id)}" width="112" height="112" alt="${esc(category.pokemon.nombre)}"><span><small>Compañero legendario · Nv. ${Number(category.pokemon.nivel)}</small><b>${esc(category.pokemon.nombre)}</b><small>Si ya lo tienes, conserva su progreso y queda al menos en nivel ${Number(category.pokemon.nivel)}.</small></span></div>`);
        return `<article class="album-category-prize ${category.reclamada ? 'claimed' : ''}"><header><h4>${esc(category.nombre)}</h4><span>${category.reclamada ? '✓ Recibido' : category.completa ? '✓ Completada' : `${category.pegadas} / ${category.total} estampas`}</span></header><progress max="${category.total}" value="${category.pegadas}" aria-label="Estampas de categoría ${esc(category.nombre)}"></progress><div class="album-prize-bundle">${rewards.join('')}</div><button type="button" data-category-prize="${category.id}" class="album-claim" ${busy || state.encuentro || !category.completa || category.reclamada ? 'disabled' : ''}>${category.reclamada ? 'Premio en tu inventario' : category.completa ? 'Reclamar mi premio' : 'Completa esta categoría'}</button></article>`;
    }
    const compactAlbum = matchMedia('(max-width: 650px), (max-width: 950px) and (max-height: 540px)');
    let compactRewards = compactAlbum.matches;
    const rewardStart = () => state.album.length;
    const albumLength = () => rewardStart() + Math.ceil((state.categorias_album?.length || 4) / (compactRewards ? 1 : 2));

    function renderAlbum() {
        if (!state.album?.length) return;
        const total = albumLength();
        albumPage = Math.max(0, Math.min(albumPage, total - 1));
        const rewardsPage = albumPage >= rewardStart();
        const singleLeaf = rewardsPage && compactRewards;
        album.classList.toggle('album-single-leaf', singleLeaf);
        $('albumRightPage').hidden = singleLeaf;
        $('albumSpread').classList.toggle('album-rewards-spread', rewardsPage);
        $('exploreDiscovered').textContent = `${state.estampas.length} / ${map.especies.length}`;
        $('albumPageLabel').textContent = `${albumPage + 1} / ${total}`;
        $('albumPrevious').disabled = albumFlipping || albumPage === 0;
        $('albumNext').disabled = albumFlipping || albumPage === total - 1;
        $('albumLeftNumber').textContent = String(singleLeaf ? albumPage + 1 : albumPage * 2 + 1).padStart(2, '0');
        $('albumRightNumber').textContent = String(albumPage * 2 + 2).padStart(2, '0');
        $('albumReward').hidden = true;
        const heading = album.querySelector('.album-page-heading h3');
        if (rewardsPage) {
            const categories = state.categorias_album || [];
            const rewardOffset = (albumPage - rewardStart()) * (singleLeaf ? 1 : 2);
            $('albumTitle').textContent = singleLeaf ? 'Premios del cuaderno' : rewardOffset ? 'Retos de leyenda' : 'Primeros logros';
            $('albumDifficulty').textContent = `${categories.filter(c => c.completa).length} / ${categories.length} categorías completadas`;
            $('albumStickersLeft').innerHTML = categories.slice(rewardOffset, rewardOffset + 1).map(categoryPrize).join('');
            $('exploreSpecies').innerHTML = singleLeaf ? '' : categories.slice(rewardOffset + 1, rewardOffset + 2).map(categoryPrize).join('');
            if (heading) heading.textContent = rewardOffset ? 'El gran encuentro' : 'Tu próxima aventura';
            $('exploreJournal').textContent = 'Cada premio se reclama una vez. Las Poké Balls necesitan espacio libre en la mochila.';
            $('exploreJournal').classList.remove('game-lock');
            return;
        }
        const sheet = state.album[albumPage];
        $('albumTitle').textContent = sheet.nombre;
        if (heading) heading.textContent = 'Estampas de la ruta';
        $('albumDifficulty').textContent = `${'●'.repeat(sheet.dificultad)}${'○'.repeat(4 - sheet.dificultad)} · ${sheet.pegadas} / 5`;
        const stickers = sheet.especies.map((p) => {
            const captured = state.estampas.includes(p.id), seen = state.descubiertos.includes(p.id);
            return `<figure class="album-sticker ${captured ? 'pasted' : seen ? 'seen' : 'missing'}"><img src="${image(p.id)}" alt="${captured || seen ? esc(p.nombre) : 'Estampa por descubrir'}" width="80" height="80" loading="lazy"><figcaption>${captured || seen ? esc(p.nombre) : '???'}<small>${captured ? 'Pegada' : seen ? 'Avistado' : 'Por descubrir'}</small></figcaption></figure>`;
        });
        $('albumStickersLeft').innerHTML = stickers.slice(0, 2).join('');
        $('exploreSpecies').innerHTML = stickers.slice(2).join('');
        $('exploreJournal').textContent = sheet.completa ? '¡Hoja completa! Revisa el avance de tus categorías en Premios.' : 'Cada estampa acerca el premio de su categoría. Abre Premios para verlos.';
        $('exploreJournal').classList.toggle('game-lock', !sheet.completa);
    }
    function renderBag() {
        if (!state?.tipos_bolas) return;
        const types = Object.entries(state.tipos_bolas).filter(([id]) => state.bolas[id] > 0);
        $('bagBalls').innerHTML =
            types
                .map(
                    ([id, type]) =>
                        `<article class="ball-stock" data-ball="${id}"><div class="ball-stock-art"><img src="assets/img/balls/${esc(type.icono)}" width="88" height="88" alt=""></div><span><strong>${esc(type.nombre)}</strong><small>${esc(type.descripcion || `Captura ×${type.factor}`)}</small></span><b aria-label="${state.bolas[id]} disponibles">×${state.bolas[id]}</b></article>`
                )
                .join('') ||
            '<div class="bag-empty"><img src="assets/img/icons/backpack2.svg" width="48" height="48" alt=""><strong>Tu próxima aventura empieza aquí</strong><span>Aún no tienes Poké Balls.</span></div>';
        if (!(state.bolas[selectedBall] > 0))
            selectedBall = types.find(([id]) => state.bolas[id] > 0)?.[0] || 'poke';
        $('captureBallType').innerHTML = types
            .map(
                ([id, type]) =>
                    `<option value="${id}" ${state.bolas[id] ? '' : 'disabled'}>${esc(type.nombre)} ×${state.bolas[id] || 0}</option>`
            )
            .join('');
        $('captureBallType').value = selectedBall;
    }
    $('openBag').onclick = () => {
        stopHeld();
        renderBag();
        $('exploreBag').showModal();
    };
    $('closeBag').onclick = () => $('exploreBag').close();
    $('openMissions').onclick = () => {
        stopHeld();
        $('exploreMissionDialog').showModal();
    };
    $('closeMissions').onclick = () => $('exploreMissionDialog').close();
    $('captureBallType').onchange = (event) => {
        selectedBall = event.target.value;
        encounterOdds();
        controls();
    };
    async function turnAlbum(direction) {
        if (
            albumFlipping ||
            albumPage + direction < 0 ||
            albumPage + direction >= albumLength()
        )
            return;
        if (reduced.matches || (compactRewards && Math.max(albumPage, albumPage + direction) >= rewardStart())) {
            albumPage += direction;
            renderAlbum();
            return;
        }
        albumFlipping = true;
        $('albumPrevious').disabled = $('albumNext').disabled = true;
        const page = $(direction > 0 ? 'albumRightPage' : 'albumLeftPage');
        const width = page.getBoundingClientRect().width;
        const leaf = document.createElement('div');
        leaf.inert = true;
        leaf.setAttribute('aria-hidden', 'true');
        leaf.className = `album-turn-leaf ${direction > 0 ? 'forward' : 'backward'}`;
        let hinge = leaf;
        const bends = [];
        for (let i = 0; i < 8; i++) {
            const segment = document.createElement('div');
            segment.className = 'album-leaf-segment';
            segment.style.width = `${width / 8}px`;
            segment.style[direction > 0 ? 'left' : 'right'] = i === 0 ? '0' : '100%';
            segment.style.transformOrigin = direction > 0 ? 'left center' : 'right center';
            const window = document.createElement('div');
            window.className = 'album-leaf-window';
            const ink = page.cloneNode(true);
            ink.removeAttribute('id');
            ink.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'));
            ink.classList.add('album-leaf-copy');
            ink.style.width = `${width}px`;
            ink.style.left = `${(-(direction > 0 ? i : 7 - i) * width) / 8}px`;
            window.append(ink);
            segment.append(window);
            hinge.append(segment);
            hinge = segment;
            bends.push(segment);
        }
        $('albumSpread').append(leaf);
        const animation = leaf.animate(
            [
                { transform: 'rotateY(0deg)' },
                {
                    transform: `rotateY(${-direction * 75}deg) rotateZ(${-direction * 0.4}deg)`,
                    offset: 0.5,
                },
                { transform: `rotateY(${-direction * 175}deg)` },
            ],
            { duration: 900, easing: 'cubic-bezier(.42,0,.23,1)', fill: 'forwards' }
        );
        const curling = bends.map((segment, i) =>
            segment.animate(
                [
                    { transform: 'rotateY(0deg)' },
                    { transform: `rotateY(${direction * (i === 0 ? 5 : 11)}deg)`, offset: 0.45 },
                    { transform: 'rotateY(0deg)' },
                ],
                { duration: 900, easing: 'ease-in-out', fill: 'forwards' }
            )
        );
        await delay(470);
        albumPage += direction;
        renderAlbum();
        await Promise.all([animation, ...curling].map((item) => item.finished.catch(() => {})));
        leaf.remove();
        albumFlipping = false;
        renderAlbum();
    }
    compactAlbum.addEventListener?.('change', (event) => {
        if (event.matches === compactRewards) return;
        if (state?.album?.length && albumPage >= rewardStart()) {
            const start = rewardStart();
            const index = albumPage - start;
            albumPage = start + (event.matches ? index * 2 : Math.floor(index / 2));
        }
        compactRewards = event.matches;
        renderAlbum();
    });
    $('albumPrevious').onclick = () => turnAlbum(-1);
    $('albumNext').onclick = () => turnAlbum(1);
    $('openAlbum').onclick = () => {
        stopHeld();
        renderAlbum();
        album.showModal();
        $('openAlbum').setAttribute('aria-expanded', 'true');
    };
    $('closeAlbum').onclick = () => album.close();
    album.addEventListener('close', () => {
        $('openAlbum').setAttribute('aria-expanded', 'false');
        $('openAlbum').focus({ preventScroll: true });
    });
    $('albumRewardsShortcut').onclick = () => {
        if (albumFlipping) return;
        if (!state?.album?.length) return;
        albumPage = rewardStart();
        renderAlbum();
    };
    album.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-category-prize]');
        if (!button || button.disabled || busy || Date.now() < visualEndsAt) return;
        const restore = PokeMarket.esperarBoton(button, 'Reclamando…');
        try {
            const result = await mutate('album', { categoria: Number(button.dataset.categoryPrize) });
            renderAlbum();
            if (result) E.toast(result.mensaje);
        } finally {
            if (button.isConnected) restore();
        }
    });
    function needsSupplies() {
        if (!state || state.pokeballs > 0 || state.encuentro) return false;
        const notice = $('exploreSuppliesNotice');
        if (!notice) return false;
        stopHeld();
        if (!notice.open) notice.showModal();
        return true;
    }
    $('closeSuppliesNotice').onclick = () => {
        $('exploreSuppliesNotice').close();
        $('openAlbum').click();
    };
    async function load() {
        const data = await PokeMarket.trainer('/exploration');
        clearTimeout(walkingTimer);
        walkingQueue = [];
        walkingPosition = null;
        nextWalkingStep = 0;
        state = data.exploracion;
        map = data.mapa;
        E.update(data.cartera);
        if (!world)
            world = new PokeExploreWorld.World(
                canvas,
                { ...map, explorado: state.explorado },
                state.posicion,
                travel
            );
        else world.setPosition(state.posicion);
        world.clockOffset =
            (state.ahora_preciso || state.ahora || Date.now() / 1000) * 1000 - Date.now();
        render();
        return data;
    }
    function errorOverlay(error, retry) {
        stopHeld();
        queuedDestination = null;
        if (encounter.open) encounter.close();
        if (guide.open) guide.close();
        const overlay = $('exploreLoading');
        overlay.hidden = false;
        overlay.replaceChildren();
        const title = document.createElement('strong');
        title.textContent = 'La expedición está en pausa';
        const text = document.createElement('p');
        text.textContent = error.message;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'market-primary';
        button.textContent = 'Reintentar conexión';
        button.onclick = async () => {
            button.disabled = true;
            try {
                await retry();
            } catch (e) {
                errorOverlay(e, retry);
            }
        };
        overlay.append(title, text, button);
        message(
            'No se pudo confirmar la operación. Reintentar recupera la misma solicitud sin cobrar dos veces.'
        );
    }
    async function execute() {
        const command = pending;
        let movementOnly = false;
        busy = true;
        world.busy = true;
        controls();
        if (command.action !== 'travel') $('exploreSave').textContent = 'Guardando…';
        const loading = $(encounter.open ? 'encounterWaiting' : 'exploreWaiting');
        const waitTimer = setTimeout(() => { loading.hidden = false; }, 1000);
        try {
            const [data] = await Promise.all([
                PokeMarket.trainer(`/exploration/${command.action}`, {
                    method: 'POST',
                    body: command.body,
                }),
                delay(command.action === 'capture' && !reduced.matches ? 950 : 0),
            ]);
            let route = data.resultado.camino;
            const connected =
                route?.length &&
                state.posicion[0] === route[0][0] &&
                state.posicion[1] === route[0][1];
            if (preview) {
                if (connected && route[1]?.every((value, index) => value === preview[index])) {
                    route = route.slice(1);
                } else {
                    world.setPosition(state.posicion);
                    visualEndsAt = 0;
                }
                preview = null;
            }
            if (command.body.camino) {
                const expected = command.body.camino.at(-1);
                const arrived = data.exploracion.posicion.every((value, index) => value === expected[index]);
                if (!arrived || data.exploracion.encuentro || data.resultado.tipo === 'avistamiento') {
                    walkingQueue = [];
                    walkingPosition = null;
                    nextWalkingStep = visualEndsAt = movementReadyAt = 0;
                    world.setPosition(data.exploracion.posicion);
                    stopHeld();
                }
            } else if (connected) {
                const stepMs = data.resultado.paso_ms || map.paso_ms || 180;
                const duration = (route.length - 1) * stepMs;
                const start = Math.max(Date.now(), visualEndsAt);
                visualEndsAt = start + duration;
                const serverDelay = (data.exploracion.listo_en - data.exploracion.ahora_preciso) * 1000;
                const serverArrival = Number.isFinite(serverDelay) ? Date.now() + serverDelay : 0;
                movementReadyAt = Math.max(visualEndsAt, serverArrival) - 220;
                if (route.length > 1) visualJourney = world.travel(route, stepMs);
            } else {
                world.setPosition(data.exploracion.posicion);
            }
            state = { ...state, ...data.exploracion };
            if (!command.body.camino) walkingPosition = null;
            world.clockOffset =
                (state.ahora_preciso || state.ahora || Date.now() / 1000) * 1000 - Date.now();
            movementOnly = command.action === 'travel' && !data.cartera;
            if (data.cartera) E.update(data.cartera);
            pending = null;
            stored(null);
            $('exploreLoading').hidden = true;
            if (data.resultado.mensaje) message(data.resultado.mensaje);
            return data.resultado;
        } catch (error) {
            if (command.body.camino) {
                walkingQueue = [];
                walkingPosition = null;
                nextWalkingStep = visualEndsAt = movementReadyAt = 0;
                world.setPosition(state.posicion);
                stopHeld();
            }
            if (preview) {
                preview = null;
                visualEndsAt = movementReadyAt = 0;
                world.setPosition(state.posicion);
            }
            if (error.status) {
                pending = null;
                stored(null);
                visualEndsAt = movementReadyAt = 0;
                await load();
                afterAction(null);
                message(error.message);
                E.toast(error.message, true);
            } else {
                errorOverlay(error, async () => {
                    const result = await execute();
                    if (result) afterAction(result);
                });
            }
            return null;
        } finally {
            clearTimeout(waitTimer);
            loading.hidden = true;
            busy = Boolean(pending);
            world.busy = busy;
            render(movementOnly);
            if (!pending && walkingQueue.length) scheduleWalking();
        }
    }
    async function mutate(action, extra = {}) {
        if (busy || pending || !state) return null;
        if (walkingQueue.length && !extra.camino) {
            scheduleWalking();
            return null;
        }
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        pending = {
            action,
            body: {
                ...extra,
                version: state.version,
                solicitud: [...bytes].map((v) => v.toString(16).padStart(2, '0')).join(''),
            },
        };
        stored(pending);
        return execute();
    }
    function scheduleWalking() {
        clearTimeout(walkingTimer);
        walkingTimer = setTimeout(async () => {
            if (busy || pending || !walkingQueue.length) return;
            const camino = walkingQueue.splice(0, 12);
            const [x, y] = camino.at(-1);
            const result = await mutate('travel', { x, y, correr: walkingMode, compacto: true, camino });
            if (result) afterAction(result);
        }, 120);
    }
    function walkBuffered(name) {
        if (needsSupplies()) return;
        if (!state || state.encuentro || followingWildlife || guide.open || encounter.open || document.hidden ||
            pending && !pending.body.camino || walkingQueue.length >= 12 ||
            Date.now() < nextWalkingStep) return;
        const mode = running || shiftHeld;
        if (walkingMode !== null && mode !== walkingMode && (pending || walkingQueue.length)) return;
        const offsets = { up: [0, -1], down: [0, 1], left: [-1, 0], right: [1, 0] };
        const from = walkingPosition || state.posicion;
        const delta = offsets[name];
        const to = from.map((value, index) => value + delta[index]);
        const stepMs = mode ? map.correr_ms || 105 : map.paso_ms || 180;
        if (!PokeExploreWorld.walkable(map.terreno, ...to) ||
            state.tiempo_ms < (walkingQueue.length + (pending?.body.camino?.length || 0) + 1) * stepMs) return;
        walkingMode = mode;
        walkingPosition = to;
        walkingQueue.push(to);
        nextWalkingStep = Date.now() + stepMs;
        visualEndsAt = nextWalkingStep;
        visualJourney = world.previewStep(from, to, stepMs);
        if (!pending && !busy && walkingQueue.length === 1) scheduleWalking();
    }
    function afterAction(result) {
        if (result?.tipo === 'avistamiento' && state.avistamiento)
            window.PokePokemonCry?.play(state.avistamiento.pokemon.id);
        if (result?.tipo === 'captura') showCaught(result.pokemon);
        else if (state.encuentro) showEncounter();
        else if (encounter.open) encounter.close();
        if (result?.tipo === 'tutorial' && guide.open) guide.close();
    }
    async function travel(x, y, firstStep) {
        const camp = map?.campamento || map?.inicio;
        if (!(camp && x === camp[0] && y === camp[1]) && needsSupplies()) return;
        if (!state || state.encuentro || guide.open || encounter.open) return;
        if (walkingQueue.length) {
            stopHeld();
            scheduleWalking();
            return;
        }
        if (!busy && walkingPosition) walkingPosition = null;
        if (busy || Date.now() < (firstStep ? movementReadyAt : visualEndsAt)) {
            if (pending?.action === 'travel' || Date.now() < visualEndsAt) {
                queuedDestination = [x, y];
                world.queued = queuedDestination;
                message('Nuevo destino marcado. Continuarás al terminar este tramo.');
            }
            return;
        }
        if (!PokeExploreWorld.walkable(map.terreno, x, y)) {
            message('El agua y los árboles no se pueden atravesar. Elige un camino.');
            return;
        }
        const correr = !followingWildlife && (running || shiftHeld);
        const stepMs = correr ? map.correr_ms || 105 : map.paso_ms || 180;
        if (firstStep && !reduced.matches && Date.now() >= visualEndsAt &&
            state.tiempo_ms >= stepMs && world.previewStep) {
            preview = firstStep;
            visualEndsAt = Date.now() + stepMs;
            visualJourney = world.previewStep(state.posicion, firstStep, stepMs);
        }
        const result = await mutate('travel', { x, y, correr, compacto: true });
        const animation = visualJourney;
        if (state.encuentro || result?.tipo === 'avistamiento') stopHeld();
        await animation;
        if (animation !== visualJourney) return;
        if (result) afterAction(result);
        const next = queuedDestination;
        queuedDestination = null;
        world.queued = null;
        if (state.encuentro || !result) stopHeld();
        else if (next && !followingWildlife) travel(...next);
    }
    async function approachWildlife(id = state?.avistamiento?.id) {
        const available = () => id && state?.avistamiento?.id === id &&
            state.avistamiento.modo !== 'huyendo' && !state.encuentro && $('exploreLoading').hidden;
        if (followingWildlife || !available() || needsSupplies()) return;
        stopHeld();
        queuedDestination = null;
        world.queued = null;
        followingWildlife = id;
        $('wildlifeApproach').textContent = 'Acercándote…';
        try {
            while (available() && (busy || walkingQueue.length || Date.now() < visualEndsAt)) {
                await delay(60);
            }
            if (!available()) return;
            const point = world.wildlifePosition?.();
            if (!point) return;
            if (Math.hypot(state.posicion[0] - point[0], state.posicion[1] - point[1]) > 2.25) {
                await travel(Math.round(point[0]), Math.round(point[1]));
            }
            if (!available()) return;
            const result = await mutate('observe', { avistamiento: id });
            if (result) afterAction(result);
        } finally {
            followingWildlife = null;
            $('wildlifeApproach').textContent = 'Acercarme';
        }
    }
    $('exploreMissions').onclick = async (event) => {
        const button = event.target.closest('[data-mission]');
        if (
            !button ||
            button.disabled ||
            button.dataset.ready !== 'true' ||
            busy ||
            Date.now() < visualEndsAt
        )
            return;
        const result = await mutate('mission', { mision: button.dataset.mission });
        if (result) $('missionStatus').textContent = `✓ +${result.segundos || 0}s recibidos`;
    };
    $('wildlifeApproach').onclick = () => approachWildlife();
    function showEncounter() {
        const e = state.encuentro;
        if (!e) return;
        encounter.classList.remove('caught', 'throwing', 'escaped');
        $('captureBallType').parentElement.hidden = false;
        $('encounterTitle').textContent = e.pokemon.nombre;
        $('encounterTypes').textContent = e.pokemon.tipos
            .map((t) => E.typeNames[t] || t)
            .join(' · ');
        PokeMarket.configurarImagen(
            $('encounterImage'),
            map.especies.find((p) => p.id === e.pokemon.id) || e.pokemon
        );
        $('encounterEyebrow').textContent = 'Una señal entre la hierba';
        $('encounterLabel').textContent = e.pokemon.rareza || 'Encuentro salvaje';
        $('encounterMessage').textContent = e.proteccion
            ? 'Después de tu mala racha, este Pokémon se deja capturar. ¡Aprovecha la oportunidad!'
            : state.estampas?.includes(e.pokemon.id)
              ? 'Ya tienes su estampa. Déjalo libre para buscar otra.'
              : state.pokeballs
                  ? 'Muévete con cuidado. Tienes tres intentos; cada fallo lo pondrá más alerta.'
                  : 'Necesitas una Poké Ball para intentar la captura.';
        $('encounterOdds').hidden = false;
        encounterOdds();
        $('capturePokemon').hidden = false;
        $('capturePokemon').textContent = 'Lanzar Poké Ball';
        $('leaveEncounter').textContent = 'Dejarlo libre';
        $('encounterHome').hidden = true;
        controls();
        if (!encounter.open) encounter.showModal();
    }
    function encounterOdds() {
        const e = state?.encuentro;
        if (!e) return;
        const seconds = Math.max(0, Math.ceil(e.vence - (Date.now() + world.clockOffset) / 1000));
        const clock = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        const chance = Math.min(
            100,
            e.probabilidad *
                (e.factores_bolas?.[selectedBall] || state.tipos_bolas?.[selectedBall]?.factor || 1)
        );
        $('encounterOdds').textContent =
            `${Math.round(chance)}% por lanzamiento · ${3 - e.intentos} intentos · ${clock}`;
        return seconds;
    }
    function showCaught(pokemon) {
        encounter.classList.remove('throwing');
        encounter.classList.add('caught');
        $('encounterTitle').textContent = `¡Capturaste a ${pokemon.nombre}!`;
        $('encounterEyebrow').textContent = 'Una nueva estampa';
        $('encounterLabel').textContent = 'Estampa conseguida';
        PokeMarket.configurarImagen(
            $('encounterImage'),
            map.especies.find((p) => p.id === pokemon.id) || pokemon
        );
        $('encounterTypes').textContent = pokemon.tipos.map((t) => E.typeNames[t] || t).join(' · ');
        $('encounterMessage').textContent =
            '¡Estampa pegada! Completa su categoría y reclama el premio en el cuaderno.';
        $('capturePokemon').hidden = true;
        $('captureBallType').parentElement.hidden = true;
        $('encounterOdds').hidden = true;
        $('leaveEncounter').textContent = 'Seguir explorando';
        $('leaveEncounter').disabled = false;
        $('encounterHome').hidden = true;
        if (!encounter.open) encounter.showModal();
        $('leaveEncounter').focus();
    }
    $('capturePokemon').onclick = async () => {
        if (busy || captureAnimating || !state.encuentro) return;
        captureAnimating = true;
        encounter.classList.add('throwing');
        encounter.dataset.ball = selectedBall;
        $('captureBallArt').src =
            `assets/img/balls/${state.tipos_bolas?.[selectedBall]?.icono || 'poke-ball.svg'}`;
        $('encounterMessage').textContent = 'Lanzando Poké Ball…';
        const [result] = await Promise.all([
            mutate('capture', { encuentro: state.encuentro.id, bola: selectedBall }),
            delay(reduced.matches ? 0 : 1250),
        ]);
        encounter.classList.remove('throwing');
        captureAnimating = false;
        controls();
        if (result?.tipo === 'captura') showCaught(result.pokemon);
        else if (state.encuentro) {
            showEncounter();
            encounter.classList.add('escaped');
            if (result?.mensaje) $('encounterMessage').textContent = result.mensaje;
        } else if (result) {
            encounter.close();
            E.toast(result.mensaje);
        }
    };
    async function leave() {
        if (busy) return;
        if (!state.encuentro) {
            encounter.close();
            canvas.focus({ preventScroll: true });
            return;
        }
        const result = await mutate('leave', { encuentro: state.encuentro.id });
        if (result) {
            encounter.close();
            canvas.focus({ preventScroll: true });
        }
    }
    encounter.addEventListener('cancel', (event) => {
        event.preventDefault();
        leave();
    });
    $('leaveEncounter').onclick = leave;
    $('exploreReturn').onclick = () => travel(...(map.campamento || map.inicio));

    $('exploreSites').onclick = (event) => {
        const b = event.target.closest('[data-site]');
        if (b) travel(...map.puntos[Number(b.dataset.site)]);
    };
    function direction(name, steps = 2) {
        if (world?.previewStep && state?.tiempo_ms !== undefined) {
            walkBuffered(name);
            return;
        }
        if (
            !state ||
            busy ||
            Date.now() < movementReadyAt ||
            queuedDestination ||
            guide.open ||
            encounter.open
        )
            return;
        const offsets = { up: [0, -1], down: [0, 1], left: [-1, 0], right: [1, 0] },
            d = offsets[name];
        let [x, y] = state.posicion;
        let firstStep;
        for (let i = 0; i < steps; i++) {
            if (!PokeExploreWorld.walkable(map.terreno, x + d[0], y + d[1])) break;
            x += d[0];
            y += d[1];
            firstStep ??= [x, y];
            if (map.puntos.some((p) => p[0] === x && p[1] === y)) break;
        }
        if (firstStep) travel(x, y, firstStep);
        else
            message(
                'Hay un obstáculo en esa dirección. Prueba otra dirección con las teclas o la cruceta.'
            );
    }
    function stopHeld() {
        heldDirection = null;
        shiftHeld = false;
    }
    function runLabel() {
        $('exploreRun').setAttribute('aria-pressed', String(running || shiftHeld));
        $('exploreRun').innerHTML =
            `${running || shiftHeld ? 'Corriendo' : 'Correr'} <kbd>Shift</kbd>`;
    }
    $('exploreRun').onclick = () => {
        running = !running;
        runLabel();
        radar();
        canvas.focus({ preventScroll: true });
    };
    $('exploreViewport').addEventListener('contextmenu', (event) => {
        if (event.target.closest('.exp-dpad, #exploreRun')) event.preventDefault();
    });
    document.querySelectorAll('[data-direction]').forEach((b) => {
        b.onpointerdown = (event) => {
            if (event.button !== 0) return;
            event.preventDefault();
            canvas.focus({ preventScroll: true });
            heldDirection = b.dataset.direction;
            direction(heldDirection);
        };
        b.onclick = (event) => {
            if (event.detail === 0) direction(b.dataset.direction);
        };
    });
    document.addEventListener('pointerup', () => {
        heldDirection = null;
    });
    document.addEventListener('pointercancel', () => {
        heldDirection = null;
    });
    setInterval(() => {
        if (heldDirection && !document.hidden) direction(heldDirection, 3);
    }, 16);
    document.addEventListener('keydown', (event) => {
        if (
            event.altKey ||
            event.ctrlKey ||
            event.metaKey ||
            guide.open ||
            encounter.open ||
            document.querySelector('dialog[open]')
        )
            return;
        const target = event.target;
        if (target?.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target?.tagName || ''))
            return;
        if (event.key === 'Shift') {
            shiftHeld = true;
            runLabel();
            return;
        }
        const names = {
            ArrowUp: 'up',
            w: 'up',
            W: 'up',
            ArrowDown: 'down',
            s: 'down',
            S: 'down',
            ArrowLeft: 'left',
            a: 'left',
            A: 'left',
            ArrowRight: 'right',
            d: 'right',
            D: 'right',
        };
        if (names[event.key]) {
            event.preventDefault();
            if (heldDirection !== names[event.key]) {
                heldDirection = names[event.key];
                direction(heldDirection);
            }
        }
    });
    document.addEventListener('keyup', (event) => {
        if (event.key === 'Shift') {
            shiftHeld = false;
            runLabel();
        } else if (
            ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'w', 'a', 's', 'd'].includes(
                event.key.toLowerCase().startsWith('arrow') ? event.key : event.key.toLowerCase()
            )
        )
            heldDirection = null;
    });
    window.addEventListener('blur', () => {
        stopHeld();
        runLabel();
    });
    $('zoomIn').onclick = () => world?.changeZoom(0.15);
    $('zoomOut').onclick = () => world?.changeZoom(-0.15);
    $('centerMap').onclick = () => {
        world?.center();
        canvas.focus({ preventScroll: true });
    };
    $('overviewMap').onclick = () => {
        world?.overview();
        canvas.focus({ preventScroll: true });
    };
    function renderGuide() {
        $('guideStep').textContent = `Paso ${guideIndex + 1} de 3`;
        $('guideTitle').textContent = steps[guideIndex][0];
        $('guideText').textContent = steps[guideIndex][1];
        $('guidePrevious').hidden = guideIndex === 0;
        $('guideNext').textContent = guideIndex === 2 ? 'Empezar a explorar' : 'Continuar';
        $('guideDots').innerHTML = steps
            .map((_, i) => `<span class="${i <= guideIndex ? 'done' : ''}"></span>`)
            .join('');
    }
    function openGuide() {
        if (busy || Date.now() < visualEndsAt || encounter.open || !state) return;
        guideIndex = 0;
        renderGuide();
        guide.showModal();
    }
    async function closeGuide() {
        if (busy) return;
        if (!state.tutorial) {
            const result = await mutate('tutorial');
            if (!result) return;
        }
        guide.close();
        if (!needsSupplies()) canvas.focus({ preventScroll: true });
    }
    $('guideNext').onclick = () => {
        if (guideIndex < 2) {
            guideIndex++;
            renderGuide();
        } else closeGuide();
    };
    $('guidePrevious').onclick = () => {
        guideIndex = Math.max(0, guideIndex - 1);
        renderGuide();
    };
    $('guideClose').onclick = closeGuide;
    $('exploreHelp').onclick = openGuide;
    guide.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeGuide();
    });
    const music = PokeAudioMixer.create({ volume: 0.2 });
    music.scene('assets/audio/home-meadow.mp3');
    PokeAudioMixer.button('exploreMusic', (enabled) => music.toggle(enabled));
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopHeld();
            runLabel();
        }
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && !busy) load().catch((e) => E.toast(e.message, true));
    });
    setInterval(() => {
        if (!document.hidden) {
            radar();
            controls();
            wildlifeClue();
            if (state?.avistamiento && !busy && Date.now() >= visualEndsAt) {
                $('wildlifeApproach').disabled = state.avistamiento.modo === 'huyendo';
                if (state.avistamiento.vence <= Date.now() / 1000)
                    load().catch((e) => E.toast(e.message, true));
            }
            if (encounter.open && state?.encuentro && !busy) {
                if (encounterOdds() === 0) {
                    encounter.close();
                    load()
                        .then(() => {
                            afterAction(null);
                            message(
                                'Perdiste el rastro. Vuelve a explorar para encontrar otro Pokémon.'
                            );
                        })
                        .catch((e) => E.toast(e.message, true));
                }
            }
        }
    }, 1000);
    async function boot() {
        try {
            await E.ready.catch(async () => {
                await PokeMarket.iniciarSesion();
                await E.refresh();
                await PokeWelcome.run(E.wallet, E.update);
            });
            const user = await PokeMarket.iniciarSesion();
            storageKey = `pokemarket.exploration.pending.${user.id}`;
            await load();
            restore();
            $('exploreLoading').hidden = true;
            if (pending) {
                const result = await execute();
                if (result) afterAction(result);
            } else if (state.encuentro) showEncounter();
            else if (!needsSupplies() && !state.tutorial) openGuide();
            if (!guide.open && !encounter.open && !$('exploreSuppliesNotice')?.open) canvas.focus({ preventScroll: true });
            message('Camina con WASD, flechas o la cruceta. Toca los Pokémon para interactuar.');
        } catch (e) {
            errorOverlay(e, boot);
        }
    }
    boot();
})();
