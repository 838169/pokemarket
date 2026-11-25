(() => {
    const $ = (id) => document.getElementById(id),
        E = PokeEconomy,
        esc = E.esc;

    let data = null,
        selectedId = null,
        habitat = 'todos',
        busy = false,
        scenePage = 0,
        homeSection = 'care';
    let placementKey, placements = {}, drag = null;

    let preparingHome = true;
    const artworkTasks = [];
    function loadingProgress(percent, message) {
        $('homeLoadingProgress').style.setProperty('--home-progress', percent);
        $('homeLoadingProgress').setAttribute('aria-valuenow', String(percent));
        $('homeLoadingPercent').textContent = `${percent}%`;
        $('homeLoadingMessage').textContent = message;
    }

    const typeTags = (p) => p.tipos.map((t) => `<span>${esc(E.typeNames[t] || t)}</span>`).join('');
    const careIcon = (name) =>
        `<img class="care-icon" src="assets/img/icons/${name}.svg" width="18" height="18" alt="">`;
    const foodImage = (key) => `assets/img/${data.alimentos[key].icono}`;

    const selected = () => data?.pokemon.find((p) => p.id === selectedId);

    function renderActors() {
        const actors = $('habitatActors');

        const existing = new Map(
            [...actors.children].map((el) => [Number(el.dataset.resident), el])
        );

        const list = data.pokemon.filter(
            (p) => habitat === 'todos' || p.cuidado.habitat === habitat
        );

        const box = $('homeScene').getBoundingClientRect();
        const page = PokeHomeLayout.page(list, scenePage, 6);
        const key = `${habitat}:${page.current}`;
        const saved = placements[key] || [];
        const slots = Array.from({ length: 6 }, (_, i) => page.items.find((p) => p.id === saved[i]));
        for (const pokemon of page.items) {
            if (!slots.includes(pokemon)) slots[slots.indexOf(undefined)] = pokemon;
        }
        const layout = PokeHomeLayout.plan(habitat, box.width, box.height, slots.map((p) => p || { altura: 1 }));
        const places = PokeHomeLayout.plan(habitat, box.width, box.height);
        $('habitatPlaces').innerHTML = places.slots
            .map(
                (slot, i) =>
                    `<span class="habitat-place ${slots[i] ? 'occupied' : ''}" data-slot="${i}" style="left:${slot.left + slot.width / 2}px;top:${slot.top + slot.height - 30}px;width:${Math.max(48, slot.width * 0.8)}px"></span>`
            )
            .join('');
        scenePage = page.current;
        const kept = new Set();
        slots.forEach((p, i) => {
            if (!p) return;
            const b = existing.get(p.id) || document.createElement('button');
            const slot = layout.slots[i];
            b.type = 'button';
            b.className = 'habitat-actor' + (p.id === selectedId ? ' selected' : '');
            b.dataset.resident = String(p.id);
            b.dataset.slot = String(i);
            kept.add(p.id);
            b.style.left = `${slot.left}px`;
            b.style.top = `${slot.top}px`;
            b.style.width = `${slot.width}px`;
            b.style.height = `${slot.height}px`;
            b.style.setProperty('--resident-size', `${slot.imageSize}px`);
            b.setAttribute('aria-label', `Cuidar a ${p.nombre}`);
            b.title = `${p.nombre} · ${window.PokePokemonScale.metres(p.altura).toLocaleString('es-CO')} m`;

            if (!existing.has(p.id)) {
                b.innerHTML = `<img alt="" draggable="false"><span>${esc(p.nombre)}</span>`;

                if (preparingHome) {
                    artworkTasks.push(new Promise((resolve) => {
                        PokeMarket.configurarImagen(b.querySelector('img'), p, {
                            progreso() {},
                            terminar: resolve,
                        });
                    }));
                } else PokeMarket.configurarImagen(b.querySelector('img'), p);

                b.onclick = (event) => {
                    if (event.detail && b.dataset.ignoreClick) return;
                    activityTrigger = b;
                    homeSection = 'care';
                    choose(p.id);
                };

                actors.append(b);
            }
            b.onpointerdown = (event) => {
                if (!event.isPrimary || event.button !== 0 || busy) return;
                delete b.dataset.ignoreClick;
                drag = { actor: b, pointer: event.pointerId, x: event.clientX, y: event.clientY, slot: i, key, slots };
                b.setPointerCapture(event.pointerId);
            };
        });

        existing.forEach((el, id) => {
            if (!kept.has(id)) el.remove();
        });

        $('homeCount').textContent =
            `${data.pokemon.length} ${data.pokemon.length === 1 ? 'compañero en casa' : 'compañeros en casa'}`;

        $('emptyHome').hidden = data.pokemon.length > 0;

        $('habitatCaption').textContent = list.length
            ? `${list.length} ${list.length === 1 ? 'compañero disfrutando' : 'compañeros disfrutando'} ${habitat === 'todos' ? 'del Hogar' : `de ${habitat === 'laguna' ? 'la laguna' : habitat === 'pradera' ? 'la pradera' : 'el bosque'}`}.`
            : 'Todavía no has asignado compañeros a este hábitat.';
        $('homePlacementInfo').textContent = list.length
            ? `Viendo ${page.offset + 1}–${page.offset + page.items.length} de ${list.length} compañeros`
            : 'Elige un compañero y asígnalo a este paisaje.';
        $('homePagination').hidden = page.pages <= 1;
        $('homePageLabel').textContent = `Grupo ${page.current + 1} de ${page.pages}`;
        $('homePrevious').disabled = page.current === 0;
        $('homeNext').disabled = page.current === page.pages - 1;

        document.querySelectorAll('[data-habitat]').forEach((b) => {
            const scene = data.escenarios.find((x) => x.id === b.dataset.habitat);
            if (scene) {
                b.dataset.locked = String(!scene.desbloqueado);
                b.dataset.gameLocked = String(!scene.desbloqueado);
                b.textContent = scene.desbloqueado
                    ? scene.nombre
                    : `${scene.nombre} · Desbloquear por ${E.number(scene.precio)} monedas`;
            }
            b.setAttribute('aria-pressed', String(b.dataset.habitat === habitat));
        });
    }
    const sceneActors = $('habitatActors');
    function dropTarget(event) {
        const under = document.elementFromPoint(event.clientX, event.clientY);
        const actor = under?.closest('.habitat-actor');
        if (actor && sceneActors.contains(actor)) return Number(actor.dataset.slot);
        return [...$('habitatPlaces').children].findIndex((place) => {
            const rect = place.getBoundingClientRect();
            return event.clientX >= rect.left - 8 && event.clientX <= rect.right + 8 &&
                event.clientY >= rect.top - 20 && event.clientY <= rect.bottom + 8;
        });
    }
    sceneActors.addEventListener('pointermove', (event) => {
        if (!drag || drag.pointer !== event.pointerId) return;
        const x = event.clientX - drag.x, y = event.clientY - drag.y;
        if (!drag.moving && Math.hypot(x, y) < 8) return;
        drag.moving = true;
        drag.actor.dataset.ignoreClick = 'true';
        drag.actor.classList.add('is-dragging');
        drag.actor.style.translate = `${x}px ${y}px`;
        const target = dropTarget(event);
        [...$('habitatPlaces').children].forEach((place, i) => place.classList.toggle('drop-target', i === target));
    });
    function finishDrag(event) {
        if (!drag || drag.pointer !== event.pointerId) return;
        const { actor, moving, slot, key, slots } = drag;
        drag = null;
        if (!moving) return;
        const target = event.type === 'pointerup' ? dropTarget(event) : -1;
        actor.style.translate = '';
        actor.classList.remove('is-dragging');
        if (target >= 0 && target !== slot && key === `${habitat}:${scenePage}` && actor.isConnected) {
            [slots[slot], slots[target]] = [slots[target], slots[slot]];
            placements[key] = slots.map((p) => p?.id ?? null);
            try { localStorage.setItem(placementKey, JSON.stringify(placements)); } catch (_) {}
        }
        renderActors();
    }
    for (const event of ['pointerup', 'pointercancel', 'lostpointercapture']) {
        sceneActors.addEventListener(event, finishDrag);
    }
    const picker = $('homePicker');
    const detail = $('homeCareSection');
    const carePicker = $('homeCarePicker');
    let careTool;
    carePicker.addEventListener('close', () => {
        $('carePanel').querySelector(`[data-care-tool="${careTool}"]`)?.focus({ preventScroll: true });
    });
    const habitats = $('homeHabitats');
    $('openHabitats').onclick = () => habitats.showModal();
    let activityTrigger;
    for (const dialog of [picker, detail, $('homeHistorySection')]) {
        dialog.addEventListener('close', () => {
            if (!picker.open && !detail.open && !$('homeHistorySection').open)
                activityTrigger?.focus({ preventScroll: true });
        });
    }
    function choose(id) {
        if (busy) return;
        window.PokePokemonCry?.play(id);
        selectedId = id;
        const list = data.pokemon.filter(
            (p) => habitat === 'todos' || p.cuidado.habitat === habitat
        );
        const index = list.findIndex((p) => p.id === id);
        const box = $('homeScene').getBoundingClientRect();
        if (index >= 0)
            scenePage = Math.floor(
                index / PokeHomeLayout.plan(habitat, box.width, box.height).capacity
            );
        picker.close();
        $('carePanel').dataset.mode = homeSection;
        $('homeDetailTitle').textContent = selected().nombre;
        $('homeCareStatus').textContent = '';
        renderActors();
        renderCare();
        detail.showModal();
        detail.scrollTop = 0;
    }
    function renderCompanions() {
        $('companionList').replaceChildren();
        if (!data.pokemon.length) {
            $('companionList').innerHTML =
                '<p class="market-footnote">Aquí aparecerán los Pokémon que compres en el Catálogo.</p>';
            return;
        }
        data.pokemon.forEach((p) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'companion-pill';
            b.setAttribute('aria-pressed', String(p.id === selectedId));
            b.innerHTML = `<img src="${PokeMarket.imagenPokemon(p.id)}" width="62" height="62" loading="lazy" alt=""><span>${esc(p.nombre)} <small>Nv. ${p.nivel || 1}</small></span>`;
            b.onclick = () => choose(p.id);
            $('companionList').append(b);
        });
    }
    function renderCare() {
        const p = selected(),
            panel = $('carePanel');
        panel.replaceChildren();
        if (!p) return;
        if (homeSection === 'train') {
            renderTraining(p, panel);
            return;
        }
        const c = p.cuidado;
        const meter = (title, value, cls) =>
            `<div class="care-meter ${cls}"><div><span>${careIcon(cls ? 'heart-fill' : 'lightning-charge-fill')}${title}</span><strong>${value} / 100</strong></div><div class="care-meter-track" role="progressbar" aria-label="${title} de ${esc(p.nombre)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${value}"><span style="width:${value}%"></span></div></div>`;
        panel.innerHTML = `<div class="care-profile"><img class="care-portrait" alt="${esc(p.nombre)}"><div><span class="care-category">Nv. ${p.nivel} / 10</span><h2>${esc(p.nombre)}</h2><div class="market-types">${typeTags(p)}</div>${meter('Energía', c.energia, '')}${meter('Vínculo', c.vinculo, 'vinculo')}<p class="care-bonus">+${c.bonus}% en ataque y defensa</p></div></div>
            <div class="care-tools">
                <button type="button" data-care-tool="food" aria-haspopup="dialog" aria-controls="homeCarePicker">${careIcon('basket')} Alimento</button>
                <button type="button" data-care-tool="habitat" aria-haspopup="dialog" aria-controls="homeCarePicker">${careIcon('journal-bookmark')} Hábitat</button>
            </div>`;
        PokeMarket.configurarImagen(panel.querySelector('.care-portrait'), p);
        panel.querySelectorAll('[data-care-tool]').forEach((button) => {
            button.onclick = () => {
                if (busy) return;
                careTool = button.dataset.careTool;
                $('homeCarePickerStatus').textContent = '';
                renderCarePicker();
                carePicker.showModal();
            };
        });
    }

    function renderCarePicker() {
        if (['moves', 'stats', 'passive'].includes(careTool)) {
            const titles = { moves: 'Ataques', stats: 'Estadísticas', passive: 'Habilidad pasiva' };
            carePicker.dataset.tool = careTool;
            $('homeCarePickerTitle').textContent = titles[careTool];
            const choices = $('homeCareChoices');
            choices.replaceChildren();
            renderTraining(selected(), choices, careTool);
            return;
        }
        const p = selected();
        const choices = $('homeCareChoices');
        const food = careTool === 'food';
        const full = p.cuidado.energia === 100;
        carePicker.dataset.tool = careTool;
        $('homeCarePickerTitle').textContent = food ? `Alimentar a ${p.nombre}` : `Hábitat de ${p.nombre}`;
        if (food) {
            const meals = Object.entries(data.alimentos).filter(([key]) => data.cartera.comida[key] > 0);
            choices.innerHTML = `<p class="care-picker-meta">Energía · ${p.cuidado.energia}/100</p>
                ${meals.length ? `<div class="care-choice-grid">${meals.map(([key, item]) =>
                    `<button type="button" class="care-choice" data-choice="${esc(key)}" ${full ? 'disabled' : ''}><img src="${esc(foodImage(key))}" alt="" width="80" height="80"><strong>${esc(item.nombre)}</strong><small>+${item.energia} energía · +${item.vinculo} vínculo</small><span>×${data.cartera.comida[key]} en la despensa</span></button>`
                ).join('')}</div>` : '<p>Tu despensa está vacía.</p>'}
                <a href="alimentos.php" class="market-secondary care-picker-store">Ir a la tienda</a>`;
        } else {
            choices.innerHTML = `<div class="care-choice-grid">${data.escenarios.map((scene) => {
                const current = scene.id === p.cuidado.habitat;
                return `<button type="button" class="care-choice care-landscape" data-choice="${esc(scene.id)}" ${current || !scene.desbloqueado ? 'disabled' : ''}>
                    <img src="${esc(scene.fondo)}" alt=""><strong>${esc(scene.nombre)}</strong>
                    <span class="${!scene.desbloqueado ? 'game-lock' : ''}">${current ? 'Hábitat actual' : scene.desbloqueado ? 'Mover aquí' : 'Bloqueado'}</span></button>`;
            }).join('')}</div>`;
        }
        choices.querySelectorAll('[data-choice]').forEach((button) => {
            button.onclick = () => act(
                food ? '/home/feed' : '/home/habitat',
                food ? { pokemon_id: p.id, comida: button.dataset.choice } : { pokemon_id: p.id, habitat: button.dataset.choice },
                food ? 'POST' : 'PATCH',
                food ? `${p.nombre} disfrutó de su comida.` : `Nuevo hábitat para ${p.nombre}.`,
                button
            );
        });
    }
    function renderTraining(p, panel, tool = null) {
        const progress = p.progreso;
        if (!progress) return;
        const section = document.createElement('section');
        section.className = 'home-training';
        section.style.setProperty('--pokemon-tone', PokeMarket.colorTipo(p.tipos[0]));
        const equipped = new Set(progress.movimientos.map((m) => m.nombre));
        const statNames = {
            hp: ['Salud', '♥', '#ff9eb0'],
            ataque: ['Ataque', '⚔', '#ffc08a'],
            defensa: ['Defensa', '⬡', '#94caff'],
            ataque_especial: ['At. especial', '✦', '#d8b3ff'],
            defensa_especial: ['Def. especial', '◇', '#91dfc3'],
            velocidad: ['Velocidad', 'ϟ', '#ffe08b'],
        };
        const icons = { normal: '✧', fire: '♨', water: '◒', grass: '❧', electric: 'ϟ', ice: '❄', fighting: '⚔', poison: '☣', ground: '▰', flying: '➶', psychic: '◉', bug: '❋', rock: '◆', ghost: '☾', dragon: '✦', dark: '◐', steel: '⬡', fairy: '✿' };
        const icon = (symbol) => `<span class="training-icon" aria-hidden="true">${symbol}</span>`;
        const typeIcon = (type) => icon(icons[type] || '✧');
        const maxLevel = progress.nivel >= progress.nivel_maximo;
        const remaining = Math.max(0, progress.experiencia_siguiente - progress.experiencia_nivel);
        const moves = (items, locked = false) => items.map((m) =>
            `<div class="training-move${locked ? ' is-locked' : ''}" style="--training-type:${PokeMarket.colorTipo(m.tipo)}">${typeIcon(m.tipo)}<div><strong>${esc(m.nombre_mostrado)}</strong><small>${esc(E.typeNames[m.tipo] || m.tipo)}${locked ? ` · <span class="game-lock">Nv. ${Number(m.nivel)}</span>` : ''}</small></div><div class="training-power"><b>${Number(m.potencia)}</b><small>Potencia</small></div></div>`
        ).join('');
        if (!tool) {
            const shortcut = (id, symbol, label, count = '') =>
                `<button type="button" data-care-tool="${id}" aria-haspopup="dialog" aria-controls="homeCarePicker">${icon(symbol)}<strong>${label}</strong>${count ? `<small>${count}</small>` : ''}</button>`;
        section.innerHTML = `<div class="training-progress"><div class="training-stage"><img class="training-portrait" alt="" draggable="false"></div><div class="training-identity"><div class="market-types">${typeTags(p)}</div><h3>${esc(p.nombre)}</h3><div class="training-heading"><strong>Nv. ${progress.nivel} <small>/ ${progress.nivel_maximo}</small></strong><span>${maxLevel ? 'Nivel máximo' : `Faltan ${E.number(remaining)} EXP`}</span></div>
            <div class="training-exp" role="progressbar" aria-label="Experiencia de ${esc(p.nombre)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress.porcentaje}"><span style="width:${progress.porcentaje}%"></span></div></div></div>
                <div class="training-shortcuts">
                    ${shortcut('moves', '⚔', 'Ataques', `${progress.movimientos.length}/4`)}
                    ${shortcut('stats', '▥', 'Estadísticas')}
                    ${p.habilidad_activa ? shortcut('passive', '◇', 'Pasiva') : ''}
                </div>`;
            panel.append(section);
            const portrait = section.querySelector('.training-portrait');
            PokeMarket.configurarImagen(portrait, p);
            portrait.alt = '';
            section.querySelectorAll('[data-care-tool]').forEach((button) => {
                button.onclick = () => {
                    if (busy) return;
                    careTool = button.dataset.careTool;
                    $('homeCarePickerStatus').textContent = '';
                    renderCarePicker();
                    carePicker.showModal();
                };
            });
            return;
        }
        const identity = document.createElement('div');
        identity.className = 'training-sheet-identity';
        identity.innerHTML = `<img width="48" height="48" alt="" aria-hidden="true"><div><strong>${esc(p.nombre)}</strong><span>Nv. ${progress.nivel} · ${p.tipos.map(t => esc(E.typeNames[t] || t)).join(' / ')}</span></div>`;
        panel.append(identity, section);
        PokeMarket.configurarImagen(identity.querySelector('img'), p);
        section.classList.add('training-sheet');
        if (tool === 'stats') {
            const highest = Math.max(1, ...Object.keys(statNames).map(key => Number(p.estadisticas[key])));
            section.innerHTML = `<p class="training-sheet-hint">Sus seis atributos actuales, comparados entre sí.</p><dl class="training-stat-list">${Object.entries(statNames).map(([key, [name]]) => `<div><dt>${name}</dt><dd><span class="training-stat-meter" aria-hidden="true"><i style="width:${Math.max(0, Number(p.estadisticas[key])) / highest * 100}%"></i></span><strong>${Number(p.estadisticas[key])}</strong></dd></div>`).join('')}</dl>`;
            return;
        }
        if (tool === 'moves') {
            section.innerHTML = `<div class="training-loadout"><p class="training-sheet-hint">Elige de 1 a 4 ataques para llevar al combate.</p>
            ${progress.disponibles.length ? `<form class="training-picker" data-move-form><fieldset><legend class="sr-only">Equipa de uno a cuatro ataques</legend><div class="training-list-heading" aria-hidden="true"><span>Movimiento</span><span>Potencia</span></div><div class="training-options">${progress.disponibles.map((m) => `<label style="--training-type:${PokeMarket.colorTipo(m.tipo)}"><input type="checkbox" name="movimiento" value="${esc(m.nombre)}" ${equipped.has(m.nombre) ? 'checked' : ''}><span class="training-option-name"><strong>${esc(m.nombre_mostrado)}</strong><small><i class="training-type-dot" aria-hidden="true"></i>${esc(E.typeNames[m.tipo] || m.tipo)}</small></span><span class="training-power"><b>${Number(m.potencia)}</b></span></label>`).join('')}</div></fieldset><div class="training-save"><p data-move-count role="status"></p><button class="market-primary" type="submit" ${busy ? 'disabled' : ''}>Guardar ataques</button></div></form>` : '<p>Aún no tiene ataques de daño.</p>'}
            ${progress.proximos.length ? `<details class="training-upcoming"><summary>Próximos ataques <span>${progress.proximos.length}</span></summary><div class="training-moves">${moves(progress.proximos, true)}</div></details>` : ''}</div>`;
        }
        if (tool === 'passive' && p.habilidad_activa) {
            const passive = document.createElement('section');
            passive.className = 'training-picker training-passive';
            const ability = p.habilidad_activa;
            const description = ability.implementada ? ability.descripcion : 'Sin efecto en el combate actual.';
            passive.innerHTML = `<p class="training-sheet-hint">${ability.implementada ? 'Se activa automáticamente durante el combate.' : 'Esta habilidad es informativa; no altera el combate actual.'}</p>` + (p.habilidades_compatibles?.length > 1
                ? `<form data-ability-form><fieldset><legend class="sr-only">Elegir habilidad pasiva</legend><div class="training-options">${p.habilidades_compatibles.map((a) => `<label class="passive-option"><input type="radio" name="habilidad" value="${esc(a.id)}" ${a.id === ability.id ? 'checked' : ''}><span><strong>${esc(a.nombre)}</strong><small>${esc(a.implementada === false ? 'Sin efecto en el combate actual.' : a.descripcion || 'Sin efecto en el combate actual.')}</small><em class="ability-selection-note">Seleccionada</em></span></label>`).join('')}</div></fieldset><div class="training-save"><span class="training-save-hint">Una habilidad activa</span><button type="submit" class="market-primary" ${busy ? 'disabled' : ''}>Guardar pasiva</button></div></form>`
                : `<div class="training-passive-title"><strong>${esc(ability.nombre)}</strong><span>${ability.implementada ? 'Equipada' : 'Informativa'}</span></div><p class="training-passive-description">${esc(description)}</p>`);
            section.append(passive);
            const abilityForm = passive.querySelector('[data-ability-form]');
            if (abilityForm) {
                abilityForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    act(
                        '/home/ability',
                        { pokemon_id: p.id, habilidad: abilityForm.elements.habilidad.value },
                        'POST',
                        `Habilidad de ${p.nombre} guardada para la próxima batalla.`,
                        abilityForm.querySelector('button')
                    );
                });
            }
        }
        const form = section.querySelector('[data-move-form]');
        if (!form) return;
        const options = [...form.querySelectorAll('input[name="movimiento"]')];
        const sync = () => {
            const count = options.filter((input) => input.checked).length;
            options.forEach((input) => {
                input.disabled = busy || (!input.checked && count >= 4);
            });
            form.querySelector('[data-move-count]').textContent =
                `${count} / 4 equipados`;
            form.querySelector('button').disabled = busy || count < 1 || count > 4;
        };
        form.addEventListener('change', sync);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const names = options.filter((input) => input.checked).map((input) => input.value);
            act(
                '/home/moves',
                { pokemon_id: p.id, movimientos: names },
                'POST',
                `Ataques de ${p.nombre} guardados.`,
                form.querySelector('button')
            );
        });
        sync();
    }
    function render() {
        E.update(data.cartera);
        setScene();
        if (!selected()) selectedId = data.pokemon[0]?.id ?? null;
        renderActors();
        if (picker.open) renderCompanions();
        if (detail.open) renderCare();
        $('walletHistory').innerHTML = data.cartera.historial
            .map(
                (h) =>
                    `<div class="wallet-history-row"><span>${esc(h.concepto)}</span><strong class="${Number(h.cantidad) < 0 ? 'debit' : ''}">${Number(h.cantidad) > 0 ? '+' : ''}${E.number(h.cantidad)} monedas</strong></div>`
            )
            .join('');
    }
    async function load() {
        data = await PokeMarket.trainer('/home');
        loadingProgress(40, 'Preparando el paisaje y tus compañeros…');
        render();
        const background = new Promise((resolve) => {
            const image = new Image();
            const timeout = setTimeout(() => finish(false), 12000);
            const finish = (loaded) => {
                clearTimeout(timeout);
                image.onload = image.onerror = null;
                resolve(loaded);
            };
            image.onload = () => finish(true);
            image.onerror = () => finish(false);
            image.src = data.escenarios.find(x => x.id === habitat)?.fondo ||
                $('homeScene').dataset.customBackground || 'assets/img/pokemon-home.png';
        });
        const tasks = [...artworkTasks, background];
        let completed = 0;
        const results = await Promise.all(tasks.map(task => task.then(loaded => {
            completed++;
            loadingProgress(Math.round(40 + 60 * completed / tasks.length),
                completed === tasks.length ? '¡Tu hogar está listo!' : 'Cargando el paisaje y las imágenes…');
            return loaded;
        })));
        preparingHome = false;
        artworkTasks.length = 0;
        $('homeScene').setAttribute('aria-busy', 'false');
        if (results.some(loaded => !loaded)) {
            $('homePlacementInfo').textContent += ' Algunas imágenes no están disponibles. Puedes volver a cargar la página para reintentarlo.';
        }
        setTimeout(() => { $('homeLoading').hidden = true; }, 250);
    }
    function openPicker() {
        $('homePickerTitle').textContent =
            homeSection === 'train' ? '¿A quién quieres entrenar?' : '¿A quién quieres cuidar?';
        if (data) renderCompanions();
        picker.showModal();
        picker.scrollTop = 0;
    }
    document.querySelectorAll('[data-home-section]').forEach((button) => {
        button.onclick = () => {
            activityTrigger = button;
            const section = button.dataset.homeSection;
            if (section === 'history') $('homeHistorySection').showModal();
            else {
                homeSection = section;
                openPicker();
            }
        };
    });
    $('changeCompanion').onclick = () => {
        if (busy) return;
        detail.close();
        openPicker();
    };
    async function act(path, body, method, message, button) {
        if (busy) return;
        busy = true;
        const status = carePicker.open ? $('homeCarePickerStatus') : $('homeCareStatus');
        const label = path.endsWith('/feed') ? 'Alimentando…' : 'Guardando…';
        status.textContent = label;
        const restore = PokeMarket.esperarBoton(button, label);
        try {
            data = await PokeMarket.trainer(path, { method, body });
            status.textContent = message;
        } catch (err) {
            status.textContent = err.message;
        } finally {
            restore();
            ((busy = false), (scenePage = 0));
            render();
            if (carePicker.open) {
                renderCarePicker();
                carePicker.querySelector('header button').focus({ preventScroll: true });
            } else if (detail.open) $('changeCompanion').focus({ preventScroll: true });
        }
    }
    function setScene() {
        const scene = data.escenarios.find((x) => x.id === habitat);

        $('homeScene').dataset.scene = habitat;

        $('homeScene').style.backgroundImage =
            `url("${scene?.fondo || $('homeScene').dataset.customBackground || 'assets/img/pokemon-home.png'}")`;

        PokeHomeMusic.scene(`assets/audio/${scene?.musica || 'home-meadow.mp3'}`);
    }

    async function selectScene(id) {
        if (!data || busy) return;

        const scene = data.escenarios.find((x) => x.id === id);

        if (scene && !scene.desbloqueado) {
            const dialog = document.createElement('dialog');

            dialog.className = 'adventure-dialog';
            dialog.setAttribute('aria-labelledby', 'sceneTitle');

            const enough = data.cartera.monedas >= scene.precio;

            dialog.innerHTML = `<img class="scene-purchase-art" src="${esc(scene.fondo)}" alt=""><h2 id="sceneTitle">${esc(scene.nombre)}</h2><p>Un nuevo lugar para tus compañeros, con su propio paisaje y música. El desbloqueo es permanente para tu cuenta.</p><div class="wager-terms"><div>Precio <strong>${E.number(scene.precio)} monedas</strong></div><div>Tu saldo <strong>${E.number(data.cartera.monedas)}</strong></div></div><p role="status" data-status>${enough ? 'Después de comprar podrás asignar compañeros a este lugar.' : 'Necesitas más monedas. Puedes ganarlas en batalla.'}</p><div class="welcome-actions"><button class="market-secondary" data-cancel type="button">Volver</button><button class="market-primary" data-buy type="button" ${enough ? '' : 'disabled'}>Desbloquear escenario</button></div>`;

            document.body.append(dialog);
            dialog.showModal();

            dialog.addEventListener('close', () => dialog.remove());

            dialog.querySelector('[data-cancel]').onclick = () => dialog.close();

            dialog.addEventListener('cancel', (e) => {
                if (busy) e.preventDefault();
            });

            dialog.querySelector('[data-buy]').onclick = async (e) => {
                if (busy) return;
                busy = true;
                e.target.disabled = true;
                e.target.textContent = 'Desbloqueando…';

                dialog.querySelector('[data-cancel]').disabled = true;

                try {
                    data = await PokeMarket.trainer('/home/scenes', {
                        method: 'POST',
                        body: { escenario: id },
                    });
                    habitat = id;
                    scenePage = 0;
                    dialog.close();
                    habitats.close();
                    E.celebrate('¡Hábitat desbloqueado!', scene.nombre, scene.fondo);
                } catch (err) {
                    dialog.querySelector('[data-status]').textContent = err.message;
                    e.target.disabled = false;
                    e.target.textContent = 'Reintentar';
                } finally {
                    ((busy = false), (scenePage = 0));
                    dialog.querySelector('[data-cancel]').disabled = false;
                    render();
                }
            };

            return;
        }

        habitat = id;
        habitats.close();
        scenePage = 0;
        setScene();
        renderActors();
        E.reveal($('habitatActors'));
    }

    document
        .querySelectorAll('[data-habitat]')
        .forEach((b) => (b.onclick = () => selectScene(b.dataset.habitat)));

    $('homePrevious').onclick = () => {
        scenePage--;
        renderActors();
    };
    $('homeNext').onclick = () => {
        scenePage++;
        renderActors();
    };
    const sceneResize = new ResizeObserver(() => {
        if (data) renderActors();
    });
    sceneResize.observe($('homeScene'));

    E.ready.then(async () => {
        loadingProgress(15, 'Cargando tu cuenta…');
        const user = await PokeMarket.iniciarSesion();
        loadingProgress(25, 'Buscando a tus compañeros…');
        placementKey = `pokemarket.hogar.posiciones.${user.id}`;
        try { placements = JSON.parse(localStorage.getItem(placementKey)) || {}; } catch (_) {}
        await load();
    }).catch((err) => {
        $('homeCount').textContent = 'No se pudo cargar';

        preparingHome = false;
        artworkTasks.length = 0;
        $('homeScene').setAttribute('aria-busy', 'false');
        $('homeLoadingProgress').hidden = true;
        $('homeLoadingMessage').textContent = err.message || 'No pudimos preparar tu hogar.';
        $('retryHome').hidden = false;

        $('retryHome').onclick = () => location.reload();
    });
})();
