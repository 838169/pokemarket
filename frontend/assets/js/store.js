(() => {
    const $ = (id) => document.getElementById(id),
        E = PokeEconomy,
        esc = E.esc;
    const food = document.body.dataset.store === 'food';
    let model,
        busy = false,
        pending = null,
        pendingKey = '',
        otherPending = false,
        previewProduct = null,
        previewQuantity = 3,
        savedProduct = null,
        buyingProduct = null,
        purchaseCooldown = false,
        captureTier = 1;
    let resetAt = 0, serverOffset = 0, resetRefreshing = false, clockState = null, resetRetryAfter = 0;
    function syncResetClock(state) {
        if (food || clockState === state) return;
        clockState = state;
        const now = (state.ahora || Date.now() / 1000) * 1000;
        serverOffset = now - Date.now();
        const colombia = new Date(now - 5 * 3600000);
        resetAt = Date.UTC(colombia.getUTCFullYear(), colombia.getUTCMonth(), colombia.getUTCDate() + 1, 5);
        resetCountdown();
    }
    function resetCountdown() {
        if (food || !resetAt) return;
        const seconds = Math.max(0, Math.ceil((resetAt - Date.now() - serverOffset) / 1000));
        const parts = [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60];
        $('storeResetCountdown').textContent = parts.map(value => String(value).padStart(2, '0')).join(':');
        if (seconds === 0 && !resetRefreshing && !busy && !pending && Date.now() >= resetRetryAfter) {
            resetRefreshing = true;
            load().catch(error => { resetRetryAfter = Date.now() + 30000; status(error.message, true); }).finally(() => { resetRefreshing = false; });
        }
    }
    if (!food) {
        setInterval(resetCountdown, 1000);
        document.addEventListener('visibilitychange', resetCountdown);
    }
    window.pokeNavigationLocked = () => busy;
    const nonce = () =>
        [...crypto.getRandomValues(new Uint8Array(16))]
            .map((n) => n.toString(16).padStart(2, '0'))
            .join('');
    function status(message, error = false) {
        $('storeStatus').textContent = message;
        $('storeStatus').dataset.error = String(error);
    }
    function savePending() {
        try {
            if (pending) sessionStorage.setItem(pendingKey, JSON.stringify(pending));
            else sessionStorage.removeItem(pendingKey);
        } catch (_) {}
    }
    const percentText = value => Math.round(value);
    function metric(value, label, gain = false) {
        const known = Number.isFinite(value);
        const text = known ? `${gain ? '+' : ''}${percentText(value)}%` : '—';
        return `<div class="store-metric"><div class="store-metric-ring" style="--metric-percent:${known ? Math.max(0, Math.min(100, value)) : 0}" aria-hidden="true"><svg viewBox="0 0 72 72"><circle cx="36" cy="36" r="31"/><circle cx="36" cy="36" r="31" pathLength="100"/></svg><strong>${esc(text)}</strong></div><span><span class="sr-only">${esc(text)} </span>${esc(label)}</span></div>`;
    }
    function productBenefits(id, item) {
        if (food) return `<div class="store-metrics">${metric(Number(item.energia), 'Energía', true)}${metric(Number(item.vinculo), 'Vínculo', true)}</div>`;
        const rates = model.exploracion.referencias_captura?.find(reference => reference.id === captureTier)?.bolas[id];
        const normal = rates?.normal;
        if (id === 'ocaso') return `<div class="store-metrics">${metric(normal, 'De día')}${metric(rates?.noche, 'De noche')}</div><p class="store-metric-note">Según el día y la noche de la región.</p>`;
        if (id === 'malla') return `<div class="store-metrics">${metric(normal, 'Otros Pokémon')}${metric(rates?.agua_bicho, 'Agua o insectos')}</div><p class="store-metric-note">Ventaja con Pokémon acuáticos o insectos, como Squirtle y Caterpie. Puedes ver el tipo en su ficha.</p>`;
        return `<div class="store-metrics">${metric(normal, 'Captura')}</div><p class="store-metric-note">${id === 'rapida' ? 'La misma probabilidad en cada intento.' : 'Probabilidad por lanzamiento.'}</p>`;
    }
    function captureSummary(id) {
        const reference = model.exploracion.referencias_captura?.find(entry => entry.id === captureTier);
        const rates = reference?.bolas[id];
        if (!rates) return 'Consulta la probabilidad exacta durante el encuentro.';
        const value = percentText(rates.normal);
        const detail = id === 'ocaso' ? `${value}% de día · ${percentText(rates.noche)}% de noche`
            : id === 'malla' ? `${value}% otros Pokémon · ${percentText(rates.agua_bicho)}% Pokémon de agua o insectos, como Squirtle y Caterpie`
            : `${value}% en cada lanzamiento`;
        return `${detail}. Referencia: ${reference.nombre.toLowerCase()}, sin correr.`;
    }
    function render() {
        E.update(model.cartera);
        const quantity = food ? 1 : Number($('storePack').value);
        const state = model.exploracion;
        syncResetClock(state);
        const dailyLimit = food ? 0 : (state.limite_paquetes_diario ?? 5) * 3;
        const left = food ? 0 : Math.max(0, dailyLimit - state.paquetes_hoy * 3);
        if (!food) {
            $('storeDailyLimit').textContent = `${dailyLimit} Poké Balls`;
            $('storeBagLimit').textContent = `${state.capacidad_mochila} Poké Balls`;
        }
        $('storeInventory').textContent = food
            ? 'Tu despensa se comparte con el Hogar.'
            : `${state.pokeballs}/${state.capacidad_mochila} en mochila · ${left} disponibles hoy`;
        $('storeItems').innerHTML = Object.entries(food ? model.alimentos : state.tipos_bolas)
            .map(([id, item]) => {
                const price = food ? item.precio : item.precio * quantity;
                const count = food ? model.cartera.comida[id] || 0 : state.bolas[id] || 0;
                const image = food ? `assets/img/${item.icono}` : `assets/img/balls/${item.icono}`;
                const name = item.nombre;
                const blocked = !food && left < quantity
                    ? (left ? `Solo puedes comprar ${left} más hoy` : 'Cupo agotado hasta medianoche')
                    : !food && state.pokeballs + quantity > state.capacidad_mochila
                      ? `Necesitas ${quantity} espacios en la mochila`
                      : model.cartera.monedas < price
                        ? `Te faltan ${E.number(price - model.cartera.monedas)} monedas`
                        : '';
                const ready = !busy && !purchaseCooldown && !otherPending && !pending && !blocked;
                const label =
                    buyingProduct === id
                        ? 'Comprando…'
                        : savedProduct === id
                        ? '✓ Guardado'
                        : blocked || `Comprar ${quantity} · ${E.number(price)} monedas`;
                return `<article class="store-product" data-product="${esc(id)}"><span class="store-stock">×${count} disponibles</span><div class="store-product-art ${item.caliente ? 'food-hot' : food ? 'food-snack' : ''}"><img src="${esc(image)}" width="150" height="150" alt="${esc(name)}">${item.caliente ? '<span class="food-steam" aria-hidden="true"><i></i><i></i><i></i></span>' : ''}</div><h2>${esc(name)}</h2><div class="store-benefits">${productBenefits(id, item)}</div><button type="button" class="market-primary" data-store-buy="${esc(id)}" data-game-locked="${Boolean(blocked && savedProduct !== id)}" ${ready ? '' : 'disabled'}>${esc(label)}</button></article>`;
            })
            .join('');
        $('storeItems').setAttribute('aria-busy', String(busy));
        if ($('storePack')) $('storePack').disabled = busy || purchaseCooldown;
        $('confirmBallPurchase').disabled = busy || purchaseCooldown;
        $('confirmBallPurchase').textContent = busy
            ? 'Guardando…'
            : purchaseCooldown
              ? '✓ Guardado'
              : 'Confirmar compra';
        $('closeBallPurchase').disabled = $('cancelBallPurchase').disabled =
            busy || purchaseCooldown;
    }
    async function load() {
        model = await PokeMarket.trainer(food ? '/home' : '/exploration');
        render();
    }
    async function buy(id) {
        if (busy || purchaseCooldown || otherPending) return;
        busy = true;
        buyingProduct = id;
        if (!food && !pending) {
            pending = {
                action: 'supplies',
                body: {
                    tipo: id,
                    cantidad: Number($('storePack').value),
                    version: model.exploracion.version,
                    solicitud: nonce(),
                },
            };
            savePending();
        }
        render();
        status('Confirmando la compra…');
        $('storeRetry').hidden = true;
        try {
            const result = await PokeMarket.trainer(food ? '/home/food' : '/exploration/supplies', {
                method: 'POST',
                body: food ? { comida: id, cantidad: 1 } : pending.body,
            });
            if (!food) {
                pending = null;
                savePending();
                model = { ...model, ...result };
            } else model.cartera = result.cartera;
            savedProduct = id;
            purchaseCooldown = true;
            const item = food ? model.alimentos[id] : model.exploracion.tipos_bolas[id];
            const art = food ? `assets/img/${item.icono}` : `assets/img/balls/${item.icono}`;
            setTimeout(() => {
                purchaseCooldown = false;
                savedProduct = null;
                if ($('ballPurchase').open) $('ballPurchase').close();
                render();
                E.celebrate(food ? '¡Despensa surtida!' : '¡Poké Balls guardadas!', item.nombre, art);
            }, 1400);
            status(food ? 'Comida añadida a tu despensa.' : result.resultado.mensaje);
        } catch (error) {
            if ($('ballPurchase').open) $('ballPurchase').close();
            status(error.message, true);
            if (!food && error.status >= 400 && error.status < 500) {
                pending = null;
                savePending();
            }
            if (pending) {
                $('storeRetry').hidden = false;
                status(
                    'No se pudo confirmar. Reintenta la misma compra para evitar un cobro duplicado.',
                    true
                );
            } else await load().catch(() => {});
        } finally {
            buyingProduct = null;
            busy = false;
            render();
        }
    }
    $('storeItems').onclick = (event) => {
        const button = event.target.closest('[data-store-buy]');
        if (!button || button.disabled) return;
        if (food) void buy(button.dataset.storeBuy);
        else {
            previewProduct = button.dataset.storeBuy;
            previewQuantity = Number($('storePack').value);
            const item = model.exploracion.tipos_bolas[previewProduct];
            $('ballPurchaseTitle').textContent = `${previewQuantity} ${item.nombre}`;
            $('ballPurchaseDetail').textContent =
                `${E.number(item.precio * previewQuantity)} monedas · Quedan ${E.number(model.cartera.monedas - item.precio * previewQuantity)} después de comprar`;
            $('ballPurchaseRule').textContent =
                captureSummary(previewProduct);
            $('ballPreview').dataset.ball = previewProduct;
            $('ballPreviewArt').src = `assets/img/balls/${item.icono}`;
            $('ballPurchase').showModal();
            replayPreview();
        }
    };
    function replayPreview() {
        const preview = $('ballPreview');
        window.PokeBallEffects?.mount(preview);
        preview.getAnimations({ subtree: true }).forEach((animation) => animation.cancel());
        preview.classList.remove('throwing');
        requestAnimationFrame(() => requestAnimationFrame(() => preview.classList.add('throwing')));
    }
    $('replayBallPreview').onclick = replayPreview;
    $('closeBallPurchase').onclick = $('cancelBallPurchase').onclick = () =>
        $('ballPurchase').close();
    $('ballPurchase').addEventListener('cancel', (event) => {
        if (busy || purchaseCooldown) event.preventDefault();
    });
    $('confirmBallPurchase').onclick = () => {
        if (busy || purchaseCooldown || pending || !previewProduct || !$('ballPurchase').open)
            return;
        $('storePack').value = String(previewQuantity);
        void buy(previewProduct);
    };
    $('storeCaptureTier')?.addEventListener('change', event => {
        captureTier = Number(event.target.value);
        if (model) render();
    });
    $('storePack')?.addEventListener('change', () => {
        if (model) render();
    });
    $('storeRetry').onclick = () => {
        if (pending) void buy(pending.body.tipo || 'poke');
    };
    E.ready
        .then(async () => {
            const user = await PokeMarket.iniciarSesion();
            pendingKey = `pokemarket.exploration.pending.${user.id}`;
            if (!food)
                try {
                    const saved = JSON.parse(sessionStorage.getItem(pendingKey));
                    if (
                        saved?.action === 'supplies' &&
                        Number.isInteger(saved.body?.version) &&
                        /^[a-f0-9]{24,64}$/.test(saved.body?.solicitud)
                    )
                        pending = saved;
                    else otherPending = Boolean(saved);
                } catch (_) {}
            await load();
            if (otherPending)
                status(
                    'Vuelve a Explorar para terminar la acción pendiente antes de comprar.',
                    true
                );
            else if (pending) await buy(pending.body.tipo || 'poke');
            else status('');
        })
        .catch((error) => status(error.message, true));
})();
