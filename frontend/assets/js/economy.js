window.PokeEconomy = (() => {
    let wallet = null;
    const esc = (value) => PokeMarket.escaparHTML(value);
    const number = (value) => new Intl.NumberFormat('es-CO').format(Number(value));
    const walletTrigger = document.getElementById('openGameWallet');
    if (walletTrigger) {
        walletTrigger.onclick = () => document.getElementById('gameWallet').showModal();
    }
    const typeNames = {
        normal: 'Normal',
        fire: 'Fuego',
        water: 'Agua',
        grass: 'Planta',
        electric: 'Eléctrico',
        ice: 'Hielo',
        fighting: 'Lucha',
        poison: 'Veneno',
        ground: 'Tierra',
        flying: 'Volador',
        psychic: 'Psíquico',
        bug: 'Bicho',
        rock: 'Roca',
        ghost: 'Fantasma',
        dragon: 'Dragón',
        dark: 'Siniestro',
        steel: 'Acero',
        fairy: 'Hada',
    };
    function update(next) {
        const previous = wallet;
        wallet = next;
        if (walletTrigger) {
            walletTrigger.dataset.digits = String(String(wallet.monedas).length);
        }
        document.querySelectorAll('[data-wallet]').forEach((el) => {
            el.textContent = number(wallet.monedas);
            if (
                previous &&
                previous.monedas !== wallet.monedas &&
                !matchMedia('(prefers-reduced-motion: reduce)').matches
            )
                el.animate(
                    [
                        { transform: 'scale(1)' },
                        { transform: 'scale(1.18)', color: '#f3cf70' },
                        { transform: 'scale(1)' },
                    ],
                    { duration: 420 }
                );
        });
        document.dispatchEvent(new CustomEvent('walletchange', { detail: wallet }));
        return wallet;
    }
    async function refresh() {
        const data = await PokeMarket.trainer('/wallet');
        return update(data.cartera);
    }
    function toast(message, error = false) {
        let el = document.getElementById('gameToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'gameToast';
            el.className = 'game-toast';
            el.setAttribute('role', 'status');
            document.body.append(el);
        }
        el.textContent = message;
        el.classList.toggle('toast-error', error);
        el.classList.add('visible');
        clearTimeout(el.hideTimer);
        el.hideTimer = setTimeout(() => el.classList.remove('visible'), 6000);
    }
    function reveal(element) {
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches)
            element.animate(
                [
                    { opacity: 0.4, transform: 'scale(.97)' },
                    { opacity: 1, transform: 'scale(1)' },
                ],
                { duration: 260, easing: 'ease-out' }
            );
    }
    function celebrate(title, name, image) {
        const dialog = document.createElement('dialog');
        dialog.className = 'game-purchase-celebration';
        dialog.setAttribute('aria-labelledby', 'purchaseCelebrationTitle');
        dialog.innerHTML = `<div class="game-purchase-art"><img src="${esc(image)}" alt=""><span aria-hidden="true">✓</span></div><h2 id="purchaseCelebrationTitle">${esc(title)}</h2><p>${esc(name)}</p><form method="dialog"><button class="market-primary" autofocus>Continuar</button></form>`;
        document.body.append(dialog);
        dialog.addEventListener('close', () => dialog.remove(), { once: true });
        dialog.showModal();
    }
    const ready = PokeMarket.iniciarSesion()
        .then(refresh)
        .catch((error) => {
            if (error.status === 503)
                window.location.replace('error.php?status=503&service=trainer');
            throw error;
        })
        .then((w) => PokeWelcome.run(w, update));
    ready.catch(() => {});
    return {
        ready,
        refresh,
        update,
        toast,
        reveal,
        celebrate,
        esc,
        number,
        typeNames,
        get wallet() {
            return wallet;
        },
    };
})();
