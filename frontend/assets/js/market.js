(() => {
    const $ = (id) => document.getElementById(id),
        E = PokeEconomy,
        esc = E.esc;
    const grid = $('pokemonGrid'),
        purchase = $('purchaseDialog');
    let category = 'todos',
        offset = 0,
        total = 0,
        loading = false,
        request = 0,
        batch = null,
        selected = null,
        purchasing = false,
        detailRequest = 0,
        detailPokemon = null,
        detailTrigger = null;
    let categories = [],
        lastTrigger = null;
    window.pokeNavigationLocked = () => purchasing;
    const inventory = new Map();
    const labels = {
        iniciales: 'Primer compañero',
        basicos: 'Explorador',
        avanzados: 'Especialista',
        elite: 'Élite',
        legendarios: 'Legendario / singular',
    };
    const types = (p) => p.tipos.map((t) => `<span>${esc(E.typeNames[t] || t)}</span>`).join('');
    function buttons() {
        document.querySelectorAll('[data-buy]').forEach((b) => {
            const p = inventory.get(Number(b.dataset.buy));
            if (!p || !E.wallet) return;
            const owned = E.wallet.pokemon_ids.includes(p.id),
                poor = E.wallet.monedas < p.precio;
            b.disabled = owned || poor || purchasing;
            b.dataset.gameLocked = String(poor && !owned);
            b.textContent = owned ? 'En tu Hogar' : poor ? `Faltan ${E.number(p.precio - E.wallet.monedas)} monedas` : 'Comprar';
            b.title = poor
                ? `Necesitas ${E.number(p.precio - E.wallet.monedas)} monedas más. Termina batallas para conseguirlas.`
                : '';
        });
        if (detailPokemon) {
            const own = E.wallet?.pokemon_ids.includes(detailPokemon.id),
                poor = E.wallet.monedas < detailPokemon.precio;
            $('modalColeccion').disabled = !!own || poor || purchasing;
            $('modalColeccion').setAttribute('data-game-locked', String(poor && !own));
            $('modalColeccion').setAttribute('data-owned', String(Boolean(own)));
            $('modalColeccion').textContent = own
                ? 'Ya vive en tu Hogar'
                : poor
                  ? `Te faltan ${E.number(detailPokemon.precio - E.wallet.monedas)} monedas`
                  : `Comprar por ${E.number(detailPokemon.precio)} monedas`;
        }
    }
    function tabs() {
        const focusedCategory = document.activeElement?.dataset?.category;
        const options = [
            { id: 'todos', nombre: 'Todos', total: categories.reduce((s, c) => s + c.total, 0) },
            ...categories,
        ];
        const mascots = {
            todos: 25,
            iniciales: 1,
            basicos: 133,
            avanzados: 448,
            elite: 6,
            legendarios: 150,
        };
        $('categoryTabs').innerHTML = options
            .map(
                (c) =>
                    `<button class="category-tab" type="button" data-category="${c.id}" aria-pressed="${c.id === category}"><span class="category-art"><img src="${PokeMarket.imagenPokemon(mascots[c.id])}" width="100" height="100" alt=""></span><span class="category-caption"><strong>${esc(c.nombre)}</strong><small class="category-count">${E.number(c.total)} Pokémon</small></span></button>`
            )
            .join('');
        if (focusedCategory)
            $('categoryTabs')
                .querySelector(`[data-category="${focusedCategory}"]`)
                ?.focus({ preventScroll: true });
    }
    function card(p) {
        inventory.set(p.id, p);
        const el = document.createElement('article');
        el.className = `market-card tier-${p.categoria}`;
        el.innerHTML = `<div class="market-card-top"><span class="market-number">N.º ${String(p.id).padStart(3, '0')}</span><span class="market-tier">${esc(labels[p.categoria])}</span></div><h3>${esc(p.nombre)}</h3><div class="market-sprite"><img decoding="async" alt="${esc(p.nombre)}"><span class="market-sprite-progress" aria-hidden="true"><svg viewBox="0 0 64 64"><circle cx="32" cy="32" r="28"/><circle cx="32" cy="32" r="28" pathLength="100"/></svg><span>0%</span></span><span class="market-sprite-unavailable">Sin imagen</span></div><div class="market-card-body"><div class="market-types">${types(p)}</div><div class="power-row"><span>Potencial base</span><strong>${p.poder_base} <small>/ 720</small></strong></div><div class="power-track" aria-hidden="true"><span style="width:${Math.min(100, (p.poder_base / 720) * 100)}%"></span></div><p class="ability-line" title="${esc(p.habilidades.map((h) => h.nombre).join(', '))}">${esc(
            p.habilidades
                .slice(0, 2)
                .map((h) => h.nombre)
                .join(' · ')
        )}</p><div class="market-card-footer"><span class="market-price"><span class="coin-icon" aria-hidden="true">P</span>${E.number(p.precio)}</span><button class="market-primary" data-buy="${p.id}" type="button">Comprar</button></div><button class="market-detail" type="button">Ver detalles</button></div>`;
        const artwork = el.querySelector('.market-sprite');
        const greeting = document.createElement('button');
        greeting.className = artwork.className;
        greeting.type = 'button';
        greeting.dataset.imageState = 'loading';
        greeting.setAttribute('aria-label', `Tocar o arrastrar a ${p.nombre}`);
        greeting.replaceChildren(...artwork.childNodes);
        artwork.replaceWith(greeting);
        const reaction = document.createElement('span');
        reaction.className = 'pokemon-reaction';
        reaction.setAttribute('role', 'status');
        greeting.append(reaction);
        const react = () => {
            if (!PokeMarket.reaccionarImagen(greeting.querySelector('img'), p)) return;
            const owned = E.wallet?.pokemon_ids.includes(p.id);
            reaction.textContent = owned ? '¡Hola, compañero!' : '¡Eh, con cuidado!';
            greeting.dataset.reacting = 'true';
            setTimeout(() => {
                greeting.dataset.reacting = 'false';
                reaction.textContent = '';
            }, 1100);
        };
        bindPokemonDrag(greeting, react);
        const arrival = document.createElement('p');
        arrival.className = 'market-footnote';
        arrival.textContent = `Llega a Nv. ${p.nivel_compra || 1} · Crece contigo en el Hogar`;
        el.querySelector('.market-sprite').after(arrival);
        el.querySelector('[data-buy]').addEventListener('click', (event) =>
            openPurchase(p, event.currentTarget)
        );
        el.querySelector('.market-detail').addEventListener('click', (event) =>
            openDetail(p, event.currentTarget)
        );
        return el;
    }
    function loadArtwork(card, pokemon, signal) {
        const artwork = card.querySelector('.market-sprite');
        const meter = artwork.querySelector('.market-sprite-progress');
        const label = meter.querySelector('span');
        const progress = (value) => {
            meter.style.setProperty('--sprite-progress', value);
            label.textContent = `${value}%`;
        };
        return new Promise((resolve) => {
            PokeMarket.configurarImagen(artwork.querySelector('img'), pokemon, {
                signal,
                progreso: progress,
                terminar(loaded) {
                    if (loaded) progress(100);
                    artwork.dataset.imageState = loaded ? 'ready' : 'unavailable';
                    if (!loaded) artwork.setAttribute('aria-label', `Imagen no disponible de ${pokemon.nombre}`);
                    resolve();
                },
            });
        });
    }
    function bindPokemonDrag(stage, react) {
        const sprite = stage.querySelector('img');
        sprite.draggable = false;
        let returnTimer = null,
            returnAnimation = null,
            drag = null,
            x = 0,
            y = 0,
            suppressClick = false;
        stage.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0 || drag) return;
            clearTimeout(returnTimer);
            if (returnAnimation) {
                const position = getComputedStyle(sprite).translate.split(' ');
                x = Number.parseFloat(position[0]) || 0;
                y = Number.parseFloat(position[1]) || 0;
                returnAnimation.cancel();
                returnAnimation = null;
                sprite.style.translate = `${x}px ${y}px`;
            }
            sprite.style.rotate = '0deg';
            const box = stage.getBoundingClientRect(),
                image = sprite.getBoundingClientRect();
            drag = {
                id: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                x,
                y,
                limitX: Math.max(0, (box.width - image.width) / 2 - 8),
                limitY: Math.max(0, (box.height - image.height) / 2 - 8),
                moved: false,
            };
            stage.setPointerCapture(event.pointerId);
            stage.dataset.dragging = 'true';
        });
        stage.addEventListener('pointermove', (event) => {
            if (!drag || drag.id !== event.pointerId) return;
            const dx = event.clientX - drag.startX,
                dy = event.clientY - drag.startY;
            if (!drag.moved && Math.hypot(dx, dy) < 4) return;
            if (!drag.moved) {
                drag.moved = true;
                react();
            }
            x = Math.max(-drag.limitX, Math.min(drag.limitX, drag.x + dx));
            y = Math.max(-drag.limitY, Math.min(drag.limitY, drag.y + dy));
            sprite.style.translate = `${x}px ${y}px`;
            sprite.style.rotate = `${Math.max(-4, Math.min(4, dx * -0.025))}deg`;
        });
        const finish = (event) => {
            if (!drag || drag.id !== event.pointerId) return;
            suppressClick = drag.moved && event.type === 'pointerup';
            drag = null;
            stage.dataset.dragging = 'false';
            sprite.style.rotate = '0deg';
            if (x || y)
                returnTimer = setTimeout(() => {
                    const from = `${x}px ${y}px`;
                    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        const animation = sprite.animate(
                            [{ translate: from }, { translate: '0px 0px' }],
                            { duration: 650, easing: 'cubic-bezier(.22,1,.36,1)' }
                        );
                        returnAnimation = animation;
                        animation.finished
                            .then(() => {
                                if (returnAnimation === animation) returnAnimation = null;
                            })
                            .catch(() => {});
                    }
                    x = y = 0;
                    sprite.style.translate = '0px 0px';
                }, 1800);
        };
        stage.addEventListener('pointerup', finish);
        stage.addEventListener('pointercancel', finish);
        stage.addEventListener('lostpointercapture', finish);
        stage.onclick = () => {
            if (suppressClick) {
                suppressClick = false;
                return;
            }
            react();
        };
    }
    async function load(reset = false) {
        if (loading && !reset) return;
        const token = ++request;
        if (reset) batch?.abort();
        const controller = new AbortController();
        batch = controller;
        loading = true;
        grid.setAttribute('aria-busy', 'true');
        $('cargarMas').disabled = true;
        $('cargarMas').textContent = 'Cargando…';
        if (reset) {
            offset = 0;
            grid.innerHTML = '<p class="market-loading">Buscando a tu próximo compañero…</p>';
        }
        const query = new URLSearchParams({
            categoria: category,
            tipo: $('typeFilter').value,
            orden: $('sortFilter').value,
            q: $('buscarPokemon').value.trim(),
            offset: String(offset),
            limit: '12',
        });
        try {
            const data = await PokeMarket.trainer(`/shop/catalog?${query}`, { signal: controller.signal });
            if (token !== request) return;
            E.update(data.cartera);
            categories = data.categorias;
            total = data.total;
            tabs();
            $('welcomeGift').hidden = !E.wallet.necesita_inicial;
            if (reset) grid.replaceChildren();
            const images = data.pokemon.map((p, i) => {
                const element = card(p);
                grid.append(element);
                if (!matchMedia('(prefers-reduced-motion: reduce)').matches)
                    element.animate(
                        [
                            { opacity: 0, transform: 'translateY(14px)' },
                            { opacity: 1, transform: 'translateY(0)' },
                        ],
                        {
                            duration: 300,
                            delay: Math.min(i, 5) * 35,
                            easing: 'cubic-bezier(.2,.7,.3,1)',
                            fill: 'backwards',
                        }
                    );
                return loadArtwork(element, p, controller.signal);
            });
            offset += data.pokemon.length;
            if (!total)
                grid.innerHTML =
                    '<div class="market-empty"><h3>No encontramos compañeros con esos filtros.</h3><p>Prueba otro nombre, tipo o categoría.</p><button type="button" class="text-button" id="emptyReset">Ver todos los Pokémon</button></div>';
            $('emptyReset')?.addEventListener('click', resetFilters);
            $('resultsCount').textContent =
                `${E.number(total)} Pokémon ${category === 'todos' ? 'en el catálogo' : 'en esta categoría'}`;
            $('cargarMas').hidden = offset >= total;
            buttons();
            if (images.length) {
                $('cargarMas').textContent = 'Cargando imágenes…';
                await Promise.all(images);
            }
        } catch (err) {
            if (token !== request) return;
            if (reset) {
                grid.innerHTML = `<div class="market-empty"><h3>No pudimos cargar el catálogo.</h3><p>${esc(err.message)}</p><button id="retryCatalog" class="market-secondary" type="button">Reintentar</button></div>`;
                $('retryCatalog').onclick = () => load(true);
            } else E.toast(err.message, true);
        } finally {
            if (token === request) {
                loading = false;
                grid.setAttribute('aria-busy', 'false');
                $('cargarMas').disabled = false;
                $('cargarMas').textContent = 'Ver más Pokémon';
            }
        }
    }
    function openPurchase(p, trigger) {
        if (purchasing) return;
        if (E.wallet.pokemon_ids.includes(p.id)) return;
        if (E.wallet.monedas < p.precio) {
            E.toast('Te faltan monedas. Termina batallas para conseguir más.', true);
            return;
        }
        selected = p;
        try {
            window.PokePurchaseCelebration?.prepare();
        } catch (_) {}
        lastTrigger = trigger;
        $('purchaseError').textContent = '';
        purchase.setAttribute('data-state', 'confirm');
        purchase.setAttribute('data-pokemon-type', p.tipos?.[0] || 'normal');
        const ability = p.habilidad_activa;
        const moves = p.ataques_iniciales;
        $('purchaseContent').innerHTML = `
            <div class="purchase-preview">
                <div class="purchase-art">
                    <div class="detail-art-heading"><span class="detail-edition">Nuevo compañero</span><span class="detail-level">Nv. ${Number(p.nivel_compra || 1)}</span></div>
                    <span class="purchase-art-number" aria-hidden="true">${String(p.id).padStart(3, '0')}</span>
                    <img class="purchase-portrait" src="${PokeMarket.imagenPokemon(p.id)}" alt="${esc(p.nombre)}">
                </div>
                <div class="purchase-companion">
                    ${ability ? `<section class="purchase-ability"><span class="purchase-eyebrow">${ability.implementada ? 'Una habilidad activa' : 'Habilidad informativa · sin recargo'}</span><h3>${esc(ability.nombre)}${ability.oculta ? ' · Oculta' : ''}</h3><p>${esc(ability.descripcion)}</p><small>Puedes cambiar entre habilidades compatibles en el Hogar, gratis.</small></section>` : ''}
                    ${Array.isArray(moves) ? `<section class="purchase-first-moves"><div class="detail-arrival-heading"><span>Nv. ${Number(p.nivel_compra || 1)}</span><strong>Sus primeros ataques</strong></div>${moves.length ? `<div class="detail-starter-moves">${moves.map((m) => `<span>${esc(m.nombre_mostrado)}</span>`).join('')}</div>` : '<p>Aún no conoce ataques de daño. Puede ganar experiencia como reserva del equipo.</p>'}</section>` : ''}
                </div>
            </div>
            <div class="purchase-info">
                <span class="purchase-eyebrow">${esc(labels[p.categoria])} · N.º ${String(p.id).padStart(3, '0')}</span>
                <h2 id="purchaseTitle">Un hogar para <span>${esc(p.nombre)}.</span></h2>
                <p>${E.wallet.necesita_inicial ? 'Será tu primer compañero. También prepararemos tu primer equipo.' : 'Tu nuevo compañero te esperará en el Hogar Pokémon.'}</p>
                <div class="purchase-breakdown">
                    <div><span>${esc(labels[p.categoria])}</span><span>${E.number(p.precio_desglose.categoria)}</span></div>
                    <div><span>Estadísticas base (${p.poder_base})</span><span>+${E.number(p.precio_desglose.estadisticas)}</span></div>
                    <div><span>Habilidad activa${ability?.implementada ? `: ${esc(ability.nombre)}` : ' · sin recargo'}</span><span>+${E.number(p.precio_desglose.habilidades)}</span></div>
                    <div><span>Precio total</span><span>${E.number(p.precio)} monedas</span></div>
                </div>
                <p class="purchase-balance">Saldo después de comprar<strong>${E.number(E.wallet.monedas - p.precio)} monedas</strong></p>
                <p class="purchase-payment-note">Un único pago. La habilidad no cobra monedas al activarse.</p>
            </div>`;
        $('confirmPurchase').hidden = false;
        $('confirmPurchase').disabled = false;
        $('confirmPurchase').textContent = `Comprar por ${E.number(p.precio)} monedas`;
        $('cancelPurchase').textContent = 'Seguir mirando';
        purchase.showModal();
    }
    async function buy() {
        if (purchasing || !selected) return;
        try {
            window.PokePurchaseCelebration?.arm?.();
        } catch (_) {}
        purchasing = true;
        buttons();
        $('confirmPurchase').disabled = true;
        $('confirmPurchase').textContent = 'Preparando su llegada…';
        $('cancelPurchase').disabled = true;
        purchase.querySelector('.dialog-close').disabled = true;
        const p = selected;
        try {
            const response = await PokeMarket.trainer('/shop/purchase', {
                method: 'POST',
                body: { pokemon_id: p.id },
            });
            E.update(response.cartera);
            // Media must never turn an already committed purchase into a retry.
            try {
                await window.PokePurchaseCelebration?.play(p);
            } catch (_) {}
            purchase.setAttribute('data-state', 'success');
            $('purchaseContent').innerHTML =
                `<div class="purchase-success"><span class="success-seal" aria-hidden="true">✓</span><img class="purchase-portrait" src="${PokeMarket.imagenPokemon(p.id)}" alt="${esc(p.nombre)}"><h2 id="purchaseTitle">${esc(p.nombre)} ya tiene hogar.</h2><p>${response.primer_companero ? 'Tu primer equipo ya está preparado. Puedes visitar el Hogar o ir a tu primera batalla.' : 'Tu nuevo compañero te espera en el Hogar.'}</p><p>Te quedan <strong>${E.number(E.wallet.monedas)} monedas</strong>.</p><a href="coleccion.php" class="market-primary" style="margin-top:20px">Visitar mi Hogar Pokémon</a></div>`;
            $('welcomeGift').hidden = true;
            $('confirmPurchase').hidden = true;
            $('cancelPurchase').textContent = 'Seguir explorando';
            E.reveal($('purchaseContent'));
            E.toast(`${p.nombre} llegó a tu Hogar.`);
            $('purchaseContent').querySelector('a').focus();
        } catch (err) {
            $('purchaseError').textContent = err.message;
            $('confirmPurchase').disabled = false;
            $('confirmPurchase').textContent = 'Reintentar compra';
            try {
                await E.refresh();
            } catch (_) {}
        } finally {
            purchasing = false;
            $('cancelPurchase').disabled = false;
            purchase.querySelector('.dialog-close').disabled = false;
            buttons();
        }
    }
    async function openDetail(p, b) {
        if (b.disabled) return;
        window.PokePokemonCry?.play(p.id);
        const token = ++detailRequest;
        b.disabled = true;
        b.setAttribute('aria-busy', 'true');
        const progress = (n) => {
            b.textContent = `Cargando ${n}%`;
            b.style.setProperty('--carga', `${n}%`);
        };
        progress(0);
        try {
            // The local market index already contains this full card: no slow upstream request.
            progress(100);
            await new Promise((resolve) => setTimeout(resolve, 120));
            if (token !== detailRequest) return;
            detailPokemon = p;
            detailTrigger = b;
            PokeMarket.mostrarDetalle(p);
            const names = {
                hp: 'Salud',
                ataque: 'Ataque',
                defensa: 'Defensa',
                ataque_especial: 'Ataque especial',
                defensa_especial: 'Defensa especial',
                velocidad: 'Velocidad',
            };
            document.getElementById('detalleCombate').innerHTML = Object.entries(p.estadisticas)
                .map(
                    ([key, value]) =>
                        `<div class="detail-battle-stat" data-stat="${esc(key)}"><div class="detail-stat-label"><span>${esc(names[key])}</span><strong>${Number(value)}</strong></div><div class="detail-stat-track" aria-hidden="true"><i style="--stat-fill:${Math.min(100, Math.max(0, (Number(value) / 255) * 100))}%"></i></div></div>`
                )
                .join('');
            buttons();
            $('cerrarModal').focus();
        } catch (err) {
            if (token === detailRequest) E.toast(err.message, true);
        } finally {
            b.disabled = false;
            b.textContent = 'Ver detalles';
            b.removeAttribute('aria-busy');
            b.style.removeProperty('--carga');
        }
    }
    function resetFilters() {
        clearTimeout(searchTimer);
        category = 'todos';
        $('buscarPokemon').value = '';
        $('typeFilter').value = '';
        $('sortFilter').value = 'calidad';
        $('marketFilters').dispatchEvent(new Event('change', { bubbles: true }));
        void load(true);
    }
    $('confirmPurchase').addEventListener('click', buy);
    function closePurchase() {
        if (!purchasing) purchase.close();
    }
    purchase.querySelector('.dialog-close').onclick = closePurchase;
    $('cancelPurchase').onclick = closePurchase;
    purchase.addEventListener('cancel', (e) => {
        if (purchasing) e.preventDefault();
    });
    purchase.addEventListener('close', () => lastTrigger?.focus());
    $('modalColeccion').onclick = () => {
        if (!detailPokemon) return;
        document.getElementById('pokemonModal').classList.remove('open');
        document.body.style.overflow = '';
        openPurchase(detailPokemon, detailTrigger);
    };
    let searchTimer;
    $('buscarPokemon').addEventListener('input', () => {
        clearTimeout(searchTimer);
        request++;
        searchTimer = setTimeout(() => load(true), 250);
    });
    $('marketFilters').addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(searchTimer);
        void load(true);
    });
    $('typeFilter').onchange = () => load(true);
    $('categoryTabs').onclick = (event) => {
        const button = event.target.closest('[data-category]');
        if (!button || button.dataset.category === category) return;
        category = button.dataset.category;
        $('categoryTabs')
            .querySelectorAll('[data-category]')
            .forEach((option) => {
                option.setAttribute('aria-pressed', String(option === button));
            });
        void load(true);
    };
    $('sortFilter').onchange = () => load(true);
    $('resetFilters').onclick = resetFilters;
    $('cargarMas').onclick = () => load();
    Object.entries(E.typeNames)
        .sort((a, b) => a[1].localeCompare(b[1], 'es'))
        .forEach(([value, label]) => {
            const o = document.createElement('option');
            o.value = value;
            o.textContent = label;
            $('typeFilter').append(o);
        });
    E.ready
        .then(() => {
            category = 'todos';
            PokeMarket.trainer('/shop/catalog?q=133&limit=1')
                .then((data) => {
                    if (data.pokemon?.[0])
                        PokeMarket.configurarImagen($('marketHeroPokemon'), data.pokemon[0]);
                })
                .catch(() => {});
            return load(true);
        })
        .catch((err) => {
            grid.innerHTML = `<div class="market-empty"><h3>No pudimos preparar tu aventura.</h3><p>${esc(err.message)}</p><button class="market-secondary" type="button" id="retryStart">Reintentar</button></div>`;
            $('retryStart').onclick = () => location.reload();
        });
    // The legacy detail viewer is kept keyboard-accessible without changing its callers.
    const modal = $('pokemonModal');
    modal.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;
        const list = [...modal.querySelectorAll('button:not(:disabled),a[href],summary')];
        const first = list[0],
            last = list.at(-1);
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last?.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first?.focus();
        }
    });
    $('cerrarModal').addEventListener('click', () => detailTrigger?.focus());
})();
