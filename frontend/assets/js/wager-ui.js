window.PokeWager = (() => {
    let offers = [],
        selected = null,
        expires = 0,
        offset = 0,
        loading = false,
        onChange = () => {};
    const $ = (id) => document.getElementById(id);
    const now = () => Date.now() / 1000 + offset;
    function choice() {
        return selected && expires > now() && PokeEconomy.wallet?.monedas >= selected.cantidad
            ? selected
            : null;
    }
    function render() {
        if (!$('wagerOffers')) return;
        $('wagerOffers').innerHTML = offers
            .map(
                (o) =>
                    `<button class="wager-offer" type="button" data-offer="${o.id}" aria-pressed="${o.id === selected?.id}" ${expires <= now() || PokeEconomy.wallet?.monedas < o.cantidad ? 'disabled' : ''}><span>${PokeEconomy.esc(o.dificultad)}</span><strong>${PokeEconomy.number(o.cantidad)}</strong><small>monedas de entrada</small><small>Si ganas recibes ${PokeEconomy.number(o.retorno)}</small><small>${PokeEconomy.wallet?.monedas < o.cantidad ? 'Saldo insuficiente' : `Ganancia neta: +${PokeEconomy.number(o.cantidad)}`}</small></button>`
            )
            .join('');
        $('wagerOffers')
            .querySelectorAll('[data-offer]')
            .forEach(
                (b) =>
                    (b.onclick = () => {
                        selected = offers.find((o) => o.id === b.dataset.offer);
                        render();
                        onChange();
                    })
            );
        tick();
    }
    function tick() {
        const seconds = Math.max(0, Math.ceil(expires - now()));
        $('wagerExpiry').textContent = loading
            ? 'Buscando ofertas…'
            : !offers.length
              ? 'No se pudieron cargar las ofertas.'
              : seconds
                ? `Ofertas disponibles durante ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
                : 'Las ofertas vencieron. Actualiza para ver nuevos desafíos.';
        if (!seconds && selected) {
            selected = null;
            render();
            onChange();
        }
    }
    async function load() {
        if (loading) return;
        loading = true;
        selected = null;
        onChange();
        $('refreshWagers').disabled = true;
        tick();
        try {
            const r = await PokeMarket.trainer('/battles/offers');
            offset = r.hora_servidor - Date.now() / 1000;
            offers = r.ofertas;
            expires = offers[0]?.vence || 0;
        } catch (e) {
            offers = [];
            PokeEconomy.toast(e.message, true);
        } finally {
            loading = false;
            $('refreshWagers').disabled = false;
            render();
            onChange();
        }
    }
    function confirm(offer) {
        if (!offer) return Promise.resolve(false);
        return new Promise((resolve) => {
            const dialog = document.createElement('dialog');
            dialog.className = 'adventure-dialog';
            dialog.setAttribute('aria-labelledby', 'wagerConfirmTitle');
            dialog.innerHTML = `<h2 id="wagerConfirmTitle">Tu apuesta, antes de entrar.</h2><p>Duelo 1 contra 1 con el Pokémon que elegiste. Dificultad: ${PokeEconomy.esc(offer.dificultad)}. Una apuesta mayor fortalece al rival.</p><div class="wager-terms"><div>Entrada <strong>${PokeEconomy.number(offer.cantidad)} monedas</strong></div><div>Si ganas recibes <strong>${PokeEconomy.number(offer.retorno)}</strong></div><div>Ganancia neta al ganar <strong>+${PokeEconomy.number(offer.cantidad)}</strong></div><div>Si pierdes o abandonas <strong>−${PokeEconomy.number(offer.cantidad)}</strong></div></div><p>La entrada se descuenta al confirmar. Cerrar la página conserva tu duelo pendiente.</p><div class="welcome-actions"><button type="button" class="market-secondary" data-cancel>Volver</button><button type="button" class="market-primary" data-confirm>Confirmar apuesta</button></div>`;
            document.body.append(dialog);
            dialog.showModal();
            let accepted = false;
            dialog.querySelector('[data-cancel]').onclick = () => dialog.close();
            dialog.querySelector('[data-confirm]').onclick = () => {
                accepted = true;
                dialog.close();
            };
            dialog.addEventListener(
                'close',
                () => {
                    dialog.remove();
                    resolve(accepted);
                },
                { once: true }
            );
            dialog.querySelector('[data-cancel]').focus();
        });
    }
    function init(callback) {
        onChange = callback;
        $('refreshWagers').onclick = load;
        setInterval(tick, 1000);
        document.addEventListener('walletchange', () => {
            render();
            onChange();
        });
    }
    return { init, load, choice, confirm };
})();
