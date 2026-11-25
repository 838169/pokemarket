/* The outer document survives navigation; no wallet or game state is stored here. */
(() => {
    const frame = document.getElementById('gameView');
    const loading = document.getElementById('gameLoading');
    const loadingMessage = document.getElementById('gameLoadingMessage');
    const retry = document.getElementById('retryGameLoad');
    let loadingDelay;
    let loadingTimeout;
    let readyDocument;
    function clearLoading() {
        if (!loading) return;
        clearTimeout(loadingDelay);
        clearTimeout(loadingTimeout);
        loading.hidden = true;
        frame.removeAttribute('aria-busy');
        frame.inert = false;
    }
    function beginLoading() {
        if (!loading) return;
        clearLoading();
        frame.setAttribute('aria-busy', 'true');
        frame.inert = true;
        loadingMessage.textContent = 'Cargando PokéMarket…';
        retry.hidden = true;
        readyDocument = null;
        loadingDelay = setTimeout(() => { loading.hidden = false; }, 180);
        loadingTimeout = setTimeout(() => {
            loadingMessage.textContent = 'La página está tardando en responder.';
            retry.hidden = false;
        }, 12000);
    }
    if (retry) retry.onclick = () => location.reload();
    const basePath = new URL('.', location.href).pathname;
    const pages = new Set([
        'catalogo.php',
        'coleccion.php',
        'equipos.php',
        'batalla.php',
        'explorar.php',
        'pokeballs.php',
        'alimentos.php',
        'amigos.php',
    ]);
    let listener = null,
        viewEpoch = 0;
    let entrance = null;
    let transition = null,
        expectedView = '';
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const master = document.getElementById('gameSound');
    const settings = document.getElementById('audioSettings');
    const gains = { music: 1, cries: 1, effects: 1 };
    const gainListeners = new Set();
    const gain = (channel) => gains[channel] ?? 1;
    let settingsTrigger;
    const music = PokeAudioMixer.create({
        onStatus(status) {
            master.checked = music.isEnabled();
            if (!music.isEnabled()) window.PokePokemonCry?.stop();
            try {
                listener?.(status);
            } catch (_) {
                listener = null;
            }
        },
    });
    window.PokeGameAudio = {
        connect(options = {}) {
            const epoch = viewEpoch;
            const ownsView = () => epoch === viewEpoch;
            listener = options.onStatus || null;
            if (options.volume) music.setVolume(options.volume);
            queueMicrotask(() => {
                try {
                    if (ownsView()) listener?.(music.isEnabled() ? 'playing' : 'paused');
                } catch (_) {}
            });
            return {
                ...music,
                scene: (file) => {
                    if (ownsView()) music.scene(file);
                },
                setVolume: (value) => {
                    if (ownsView()) music.setVolume(value);
                },
                toggle: (value) => {
                    if (ownsView()) music.toggle(value);
                },
                resume: () => {
                    if (ownsView()) music.resume();
                },
                stop: () => {
                    if (ownsView()) music.stop();
                },
                duck: (factor) => (ownsView() ? music.duck(factor) : () => {}),
            };
        },
        isEnabled: music.isEnabled,
        resume: music.resume,
        duck: music.duck,
        subscribe: music.subscribe,
        gain,
        subscribeGain(callback) {
            gainListeners.add(callback);
            return () => gainListeners.delete(callback);
        },
        openSettings(trigger) {
            settingsTrigger = trigger;
            if (!settings.open) settings.showModal();
        },
        viewReady(view) {
            if (view === frame.contentWindow) finishView();
        },
        navigate(path, push = true) {
            const requested = new URL(path, location.href);
            if (requested.origin !== location.origin) return false;
            const nextPage = [...pages].find((page) => requested.pathname === basePath + page);
            if (!nextPage) return false;
            const url = new URL(nextPage, new URL('.', location.href));
            url.search = requested.search;
            url.hash = requested.hash;
            url.searchParams.delete('_contenido');
            if (frame.contentWindow?.pokeNavigationLocked?.()) {
                const current = new URL(expectedView);
                current.searchParams.delete('_contenido');
                if (current.href !== location.href)
                    history.replaceState(null, '', current.pathname + current.search + current.hash);
                return true;
            }
            if (push) history.pushState(null, '', url.pathname + url.search + url.hash);
            listener = null;
            viewEpoch++;
            window.PokePokemonCry?.stop();
            music.scene(
                nextPage === 'coleccion.php' || nextPage === 'explorar.php'
                    ? 'assets/audio/home-meadow.mp3'
                    : 'assets/audio/catalog-theme.ogg'
            );
            music.setVolume(0.25);
            // The frame only needs the embedded view flag and the two supported battle rooms.
            const room = nextPage === 'batalla.php' ? requested.searchParams.get('sala') : null;
            const query = room === 'amigos' ? '?_contenido=1&sala=amigos' :
                room === 'duos' ? '?_contenido=1&sala=duos' : '?_contenido=1';
            const viewUrl = new URL(nextPage + query, new URL('.', location.href)).href;
            transition?.skipTransition();
            transition = null;
            expectedView = viewUrl;
            beginLoading();
            const epoch = viewEpoch;
            const navigateView = () => {
                if (epoch !== viewEpoch) return;
                try {
                    if (frame.contentWindow?.location.href !== 'about:blank') {
                        frame.contentWindow.location.replace(viewUrl);
                        return;
                    }
                } catch (_) {
                    // Safari may expose an inaccessible frame after a network error.
                }
                frame.src = viewUrl;
            };
            if (!reducedMotion?.matches && document.startViewTransition && viewEpoch > 1) {
                try {
                    const active = document.startViewTransition(navigateView);
                    transition = active;
                    active.finished
                        .catch(() => {})
                        .finally(() => {
                            if (transition === active) transition = null;
                        });
                } catch (_) {
                    navigateView();
                }
            } else navigateView();
            return true;
        },
    };
    const saveAudio = document.getElementById('saveAudioSettings');
    const sliders = [...settings.querySelectorAll('[data-audio-channel]')];
    let savedMaster = music.isEnabled();
    master.checked = savedMaster;
    const savedLevels = new Map();
    function updateSave() {
        saveAudio.disabled =
            master.checked === savedMaster &&
            sliders.every((slider) => slider.value === savedLevels.get(slider));
    }
    master.addEventListener('change', () => {
        music.toggle(master.checked, false);
        updateSave();
    });
    settings.addEventListener('close', () => {
        master.checked = savedMaster;
        music.toggle(savedMaster, false);
        for (const slider of sliders) {
            slider.value = savedLevels.get(slider);
            slider.dispatchEvent(new Event('input'));
        }
        frame.contentWindow?.focus?.();
        settingsTrigger?.focus();
    });
    saveAudio.addEventListener('click', () => {
        savedMaster = master.checked;
        try {
            localStorage.setItem('pokeSoundEnabled', String(savedMaster));
        } catch (_) {}
        for (const slider of sliders) {
            savedLevels.set(slider, slider.value);
            try {
                localStorage.setItem(`pokeAudio-${slider.dataset.audioChannel}`, slider.value);
            } catch (_) {}
        }
        settings.close();
    });
    for (const slider of sliders) {
        const channel = slider.dataset.audioChannel;
        try {
            const saved = localStorage.getItem(`pokeAudio-${channel}`);
            if (saved !== null) slider.value = saved;
        } catch (_) {}
        savedLevels.set(slider, slider.value);
        const apply = () => {
            gains[channel] = slider.valueAsNumber / 100;
            slider.previousElementSibling.value = `${slider.value}%`;
            if (channel === 'music') music.setGain(gain(channel));
            if (channel === 'cries') window.PokePokemonCry?.setGain(gain(channel));
            for (const callback of gainListeners) callback(channel, gain(channel));
        };
        apply();
        slider.addEventListener('input', () => {
            apply();
            updateSave();
        });
    }
    function finishView() {
        try {
            const url = new URL(frame.contentWindow.location.href);
            if (url.origin !== location.origin) return;
            if (['login.php', 'registro.php'].includes(url.pathname.split('/').pop())) {
                clearLoading();
                music.stop();
                location.replace(url.pathname);
                return;
            }
            if (url.href !== expectedView && url.pathname !== basePath + 'error.php') return;
            const view = frame.contentDocument;
            if (!view || view === readyDocument) return;
            readyDocument = view;
            clearLoading();
            document.title = view.title;
            entrance?.cancel();
            entrance = null;
            if (!transition && !reducedMotion?.matches && frame.animate)
                entrance = frame.animate(
                    [
                        { opacity: 0, transform: 'translateY(8px)' },
                        { opacity: 1, transform: 'translateY(0)' },
                    ],
                    {
                        duration: 440,
                        easing: 'cubic-bezier(.2,.8,.2,1)',
                    }
                );
        } catch (_) {}
    }
    frame.addEventListener('load', finishView);
    const onMotionChange = () => {
        if (reducedMotion.matches) {
            entrance?.cancel();
            transition?.skipTransition();
        }
    };
    if (reducedMotion?.addEventListener) reducedMotion.addEventListener('change', onMotionChange);
    else reducedMotion?.addListener?.(onMotionChange);
    window.addEventListener('popstate', () => window.PokeGameAudio.navigate(location.href, false));
    window.addEventListener?.('beforeunload', (event) => {
        if (!frame.contentWindow?.pokeNavigationLocked?.()) return;
        event.preventDefault();
        event.returnValue = '';
    });
    window.PokeGameAudio.navigate(location.href, false);
})();
