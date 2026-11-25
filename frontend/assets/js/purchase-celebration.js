window.PokePurchaseCelebration = (() => {
    const source = document.currentScript?.dataset.videoSrc || '';
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const movieCues = [
        [0, 'arrival'],
        [0.65, 'energy'],
        [2.7, 'lock'],
        [3.2, 'tick'],
        [3.8, 'tick'],
        [4.4, 'tick'],
        [5.2, 'success'],
    ];
    const fallbackCues = [
        [0, 'arrival'],
        [0.25, 'energy'],
        [0.65, 'lock'],
        [0.9, 'tick'],
        [1.05, 'success'],
    ];
    let active = null,
        prepared = null;
    function video() {
        const element = document.createElement('video');
        element.muted = element.defaultMuted = true;
        element.volume = 0;
        element.playsInline = true;
        element.preload = 'auto';
        element.src = source;
        element.load();
        return element;
    }
    function prepare() {
        window.PokePurchaseAudio?.prepare();
        if (source && !reduced.matches && !prepared) prepared = video();
    }
    function arm() {
        prepare();
        window.PokePurchaseAudio?.armMovie?.(prepared);
    }
    function play(pokemon) {
        active?.();
        if (document.hidden) return Promise.resolve();
        return new Promise((resolve) => {
            let dialog,
                media,
                sound,
                frame = 0,
                loadingTimer = 0,
                watchdog = 0;
            let finished = false,
                originalAudio = false,
                mode = '',
                started = 0,
                lastFrame = -Infinity,
                cueIndex = 0;
            function finish() {
                if (finished) return;
                finished = true;
                cancelAnimationFrame(frame);
                clearTimeout(loadingTimer);
                clearTimeout(watchdog);
                document.removeEventListener('visibilitychange', onHidden);
                window.removeEventListener('pagehide', finish);
                reduced.removeEventListener('change', onReduced);
                sound?.stop();
                if (media) {
                    media.pause();
                    window.PokePurchaseAudio?.releaseMovie?.(media);
                    media.removeAttribute('src');
                    media.load();
                }
                if (dialog?.open) dialog.close();
                dialog?.remove();
                if (active === finish) active = null;
                resolve();
            }
            function onHidden() {
                if (document.hidden) finish();
            }
            function onReduced() {
                if (reduced.matches) finish();
            }
            function fallback() {
                if (finished || mode === 'fallback') return;
                clearTimeout(loadingTimer);
                cancelAnimationFrame(frame);
                media?.pause();
                mode = 'fallback';
                cueIndex = 0;
                started = performance.now();
                dialog.dataset.mode = 'fallback';
                frame = requestAnimationFrame(tick);
            }
            function cues(time, list) {
                while (cueIndex < list.length && time >= list[cueIndex][0]) {
                    const [at, name] = list[cueIndex++];
                    if (time - at < 0.4) sound?.play(name);
                }
            }
            let canvas, context;
            function tick(now) {
                if (finished) return;
                try {
                    if (mode === 'fallback') {
                        const elapsed = (now - started) / 1000;
                        cues(elapsed, fallbackCues);
                        if (elapsed >= 1.9) return finish();
                    } else if (mode === 'video') {
                        if (!originalAudio) cues(media.currentTime, movieCues);
                        const progress = Math.max(0, Math.min(1, (media.currentTime - 0.65) / 2));
                        const companion = dialog.querySelector('img');
                        companion.style.top = `${55 - 16 * progress}%`;
                        companion.style.transform = `translate(-50%, -50%) scale(${1 - 0.95 * progress})`;
                        companion.style.opacity = String(1 - progress);
                        const ending = Math.max(0, Math.min(1, (media.currentTime - 7) / 0.7));
                        canvas.style.opacity = String(1 - ending);
                        canvas.style.transform = `scale(${1 - 0.85 * ending})`;
                        dialog.querySelector('.purchase-capture-flash').style.opacity = String(
                            0.85 * Math.max(0, 1 - Math.abs(media.currentTime - 5.2) / 0.08)
                        );
                        if (media.readyState >= 2 && now - lastFrame >= 1000 / 24) {
                            lastFrame = now;
                            context.drawImage(media, 0, 0, canvas.width, canvas.height);
                            const image = context.getImageData(0, 0, canvas.width, canvas.height);
                            window.PokePurchaseChroma.apply(image.data);
                            context.putImageData(image, 0, 0);
                        }
                    }
                    frame = requestAnimationFrame(tick);
                } catch (_) {
                    fallback();
                }
            }
            try {
                active = finish;
                sound = window.PokePurchaseAudio?.start();
                dialog = document.createElement('dialog');
                dialog.className = 'purchase-capture';
                dialog.setAttribute('aria-label', `Llegada de ${pokemon.nombre} a tu Hogar`);
                dialog.innerHTML = `<div class="purchase-capture-stage" aria-hidden="true"><canvas class="purchase-capture-video"></canvas><div class="purchase-capture-ball"><span></span></div><img class="purchase-capture-companion" alt=""></div><div class="purchase-capture-flash" aria-hidden="true"></div><div class="purchase-capture-caption"><span>Un nuevo compañero</span><h2></h2><p>Su lugar en tu Hogar ya está listo.</p></div><button class="market-secondary purchase-capture-skip" type="button">Continuar</button>`;
                dialog.querySelector('h2').textContent = pokemon.nombre;
                document.body.append(dialog);
                window.PokeMarket.configurarImagen(dialog.querySelector('img'), {
                    ...pokemon,
                    solo_imagen: true,
                });
                dialog.querySelector('button').addEventListener('click', finish);
                dialog.addEventListener('cancel', (event) => {
                    event.preventDefault();
                    finish();
                });
                dialog.addEventListener('close', finish);
                dialog.addEventListener('keydown', (event) => {
                    if (event.key === 'Tab') {
                        event.preventDefault();
                        dialog.querySelector('button').focus();
                    }
                });
                document.addEventListener('visibilitychange', onHidden);
                window.addEventListener('pagehide', finish);
                reduced.addEventListener('change', onReduced);
                dialog.showModal();
                dialog.querySelector('button').focus();
                watchdog = setTimeout(finish, 12000);
                if (reduced.matches) {
                    dialog.dataset.mode = 'reduced';
                    sound?.play('success');
                    loadingTimer = setTimeout(finish, 800);
                    return;
                }
                sound?.play('receipt');
                if (!source) {
                    fallback();
                    return;
                }
                mode = 'loading';
                dialog.dataset.mode = 'loading';
                media = prepared || video();
                prepared = null;
                canvas = dialog.querySelector('canvas');
                context = canvas.getContext('2d', { willReadFrequently: true });
                if (!context) {
                    fallback();
                    return;
                }
                media.addEventListener('error', fallback, { once: true });
                media.addEventListener('ended', finish, { once: true });
                loadingTimer = setTimeout(fallback, 2200);
                const startMovie = (hasAudio) => {
                    if (finished || mode === 'fallback') return;
                    originalAudio = hasAudio;
                    media.muted = !hasAudio;
                    media.currentTime = 0;
                    return media.play().then(() => {
                        if (finished || mode === 'fallback') {
                            media.pause();
                            return;
                        }
                        clearTimeout(loadingTimer);
                        const scale = Math.min(1, 960 / media.videoWidth, 540 / media.videoHeight);
                        canvas.width = Math.max(1, Math.round(media.videoWidth * scale));
                        canvas.height = Math.max(1, Math.round(media.videoHeight * scale));
                        mode = 'video';
                        dialog.dataset.mode = 'video';
                        tick(performance.now());
                    });
                };
                const audio = sound?.movie?.(media);
                if (audio) audio.then(startMovie).catch(fallback);
                else startMovie(false)?.catch(fallback);
            } catch (_) {
                finish();
            }
        });
    }
    window.addEventListener('pagehide', () => {
        active?.();
        if (prepared) {
            prepared.pause();
            window.PokePurchaseAudio?.releaseMovie?.(prepared);
            prepared.removeAttribute('src');
            prepared.load();
            prepared = null;
        }
    });
    return { prepare, arm, play };
})();
