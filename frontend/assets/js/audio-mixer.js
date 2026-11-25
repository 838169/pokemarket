/* One music bus per page: prepare the next voice before fading out the current one. */
window.PokeAudioMixer = (() => {
    function create({ volume = 0.25, onStatus = () => {} } = {}) {
        if (window.parent && window.parent !== window && window.parent.PokeGameAudio)
            return window.parent.PokeGameAudio.connect({ volume, onStatus });
        const positions = new Map(),
            voices = new Set(),
            fades = new Map(),
            ducks = new Map(),
            listeners = new Set();
        let saved = null;
        try {
            saved = window.localStorage?.getItem('pokeSoundEnabled');
        } catch (_) {}
        let source = '',
            enabled = saved !== 'false',
            current = null,
            pending = null,
            generation = 0,
            gain = 1;
        function status(value) {
            onStatus(value);
            for (const listener of listeners) {
                try {
                    listener(enabled, value);
                } catch (_) {
                    listeners.delete(listener);
                }
            }
        }
        const targetVolume = () => volume * gain * Math.min(1, ...ducks.values());
        const musicMuted = () => !enabled || [...ducks.values()].includes(0);
        function updateVolume() {
            for (const voice of voices) voice.muted = musicMuted();
            if (current && voices.has(current)) fade(current, targetVolume());
        }
        function fade(voice, target, done = () => {}) {
            clearInterval(fades.get(voice));
            const from = voice.volume,
                started = Date.now();
            const timer = setInterval(() => {
                const t = Math.min(1, (Date.now() - started) / 900);
                voice.volume = t === 1 ? target : from + (target - from) * (t * t * (3 - 2 * t));
                if (t === 1) {
                    clearInterval(timer);
                    fades.delete(voice);
                    done();
                }
            }, 30);
            fades.set(voice, timer);
        }
        function release(voice) {
            if (voice === current && Number.isFinite(voice.currentTime))
                positions.set(source, voice.currentTime);
            clearInterval(fades.get(voice));
            fades.delete(voice);
            voice.pause();
            voices.delete(voice);
        }
        async function start() {
            if (!enabled || !source || pending) return;
            if (current && current.src.endsWith(source) && !current.paused) return;
            const ticket = ++generation,
                next = new Audio(source);
            pending = next;
            voices.add(next);
            next.muted = musicMuted();
            next.volume = 0;
            next.preload = 'auto';
            status('loading');
            next.loop = true;
            const position = positions.get(source) || 0;
            const seek = () => {
                try {
                    next.currentTime = position;
                } catch (_) {}
            };
            seek();
            next.addEventListener('loadedmetadata', seek, { once: true });
            next.addEventListener('error', () => {
                release(next);
                if (current === next) current = null;
                if (ticket === generation) {
                    status('error');
                }
            });
            try {
                await next.play();
                if (ticket !== generation || !enabled) {
                    release(next);
                    return;
                }
                current = next;
                fade(next, targetVolume());
                for (const old of voices) if (old !== next) release(old);
                status('playing');
            } catch (error) {
                release(next);
                if (ticket === generation) {
                    status(error.name === 'NotAllowedError' ? 'blocked' : 'error');
                }
            } finally {
                if (pending === next) pending = null;
            }
        }
        function scene(file) {
            if (source === file) return;
            generation++;
            for (const voice of voices) release(voice);
            current = null;
            pending = null;
            source = file;
            if (enabled) start();
        }
        function toggle(value, persist = true) {
            if (enabled === Boolean(value)) {
                if (enabled) start();
                return;
            }
            enabled = Boolean(value);
            try {
                if (persist) window.localStorage?.setItem('pokeSoundEnabled', String(enabled));
            } catch (_) {}
            if (!enabled && pending) {
                generation++;
                release(pending);
                pending = null;
            }
            if (current && !current.paused) {
                for (const voice of voices) voice.muted = musicMuted();
                status(enabled ? 'playing' : 'paused');
                if (enabled) start();
                return;
            }
            generation++;
            if (enabled) start();
            else {
                for (const voice of voices) fade(voice, 0, () => release(voice));
                current = null;
                status('paused');
            }
        }
        function suspend() {
            generation++;
            for (const voice of voices) release(voice);
            pending = null;
        }
        // Focus changes must never replace or rewind the active track.
        document.addEventListener('pointerdown', () => start());
        document.addEventListener('keydown', () => start());
        window.addEventListener('pagehide', suspend);
        window.addEventListener('pageshow', (event) => {
            if (event.persisted && enabled) {
                if (current) {
                    voices.add(current);
                    current.play().catch(() => status('blocked'));
                } else start();
            }
        });
        return {
            scene,
            toggle,
            resume: start,
            stop: suspend,
            isEnabled: () => enabled,
            subscribe(listener) {
                listeners.add(listener);
                return () => listeners.delete(listener);
            },
            duck(factor = 0.2) {
                const token = {};
                ducks.set(token, Math.max(0, Math.min(1, Number(factor) || 0)));
                updateVolume();
                return () => {
                    ducks.delete(token);
                    updateVolume();
                };
            },
            setVolume(value) {
                volume = Math.max(0, Math.min(1, Number(value) || 0));
                updateVolume();
            },
            setGain(value) {
                gain = value;
                updateVolume();
            },
        };
    }
    function button(id, change) {
        const element = document.getElementById(id);
        function sync(enabled) {
            element.setAttribute('aria-pressed', String(enabled));
            const label = enabled ? 'Silenciar sonido' : 'Activar sonido';
            element.setAttribute('aria-label', label);
            element.title = label;
        }
        element.addEventListener('click', () => {
            const enabled = element.getAttribute('aria-pressed') !== 'true';
            sync(enabled);
            change(enabled);
        });
        let enabled = true;
        try {
            enabled = window.localStorage?.getItem('pokeSoundEnabled') !== 'false';
        } catch (_) {}
        sync(window.parent?.PokeGameAudio?.isEnabled() ?? enabled);
        return sync;
    }
    return { create, button };
})();
