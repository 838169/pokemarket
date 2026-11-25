/* Purchase-only cues share the persistent game's mute state and music bus. */
window.PokePurchaseAudio = (() => {
    const host = window.parent?.PokeGameAudio || window.PokeGameAudio;
    const clips = new Map();
    const volumes = {
        receipt: 0.2,
        arrival: 0.28,
        energy: 0.2,
        lock: 0.32,
        tick: 0.2,
        success: 0.36,
    };
    let current = null;
    let context;
    const movieAudio = new WeakMap();
    function enabled() {
        if (host) return host.isEnabled();
        try {
            return localStorage.getItem('pokeSoundEnabled') !== 'false';
        } catch (_) {
            return true;
        }
    }
    function prepare() {
        for (const name of Object.keys(volumes)) {
            if (clips.has(name)) continue;
            const clip = new Audio(`assets/audio/purchase/${name}.ogg`);
            clip.preload = 'auto';
            clip.load();
            clips.set(name, clip);
        }
    }
    function armMovie(media) {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!media || !AudioContext || movieAudio.has(media)) return;
        context ??= new AudioContext();
        const volume = context.createGain();
        volume.gain.value = 0;
        const source = context.createMediaElementSource(media);
        source.connect(volume).connect(context.destination);
        media.muted = false;
        media.volume = 1;
        const ready = Promise.all([context.resume(), media.play()]).then(
            () => {
                media.pause();
                return context.state === 'running';
            },
            () => {
                media.pause();
                return false;
            },
        );
        movieAudio.set(media, { volume, source, ready });
    }
    function releaseMovie(media) {
        const track = movieAudio.get(media);
        if (!track) return;
        track.source.disconnect();
        track.volume.disconnect();
        movieAudio.delete(media);
    }
    function start() {
        current?.stop();
        prepare();
        window.parent?.PokePokemonCry?.stop();
        window.PokePokemonCry?.stop();
        const voices = new Set();
        const baseVolume = new WeakMap();
        let movie;
        function updateMovie() {
            if (movie) movie.volume.gain.value = enabled() && !document.hidden ? (host?.gain?.('effects') ?? 1) : 0;
        }
        const unsubscribeGain = host?.subscribeGain?.((channel, gain) => {
            if (channel === 'effects') {
                for (const voice of voices) voice.volume = baseVolume.get(voice) * gain;
                updateMovie();
            }
        });
        const releaseMusic = host?.duck?.(0.2) || (() => {});
        let stopped = false;
        function silence() {
            for (const voice of voices) voice.pause();
            voices.clear();
        }
        const unsubscribe =
            host?.subscribe?.((on) => {
                if (!on) silence();
                updateMovie();
            }) || (() => {});
        const session = {
            movie(media) {
                const track = movieAudio.get(media);
                return track?.ready.then((ready) => {
                    if (!ready || stopped || !movieAudio.has(media)) return false;
                    movie = track;
                    updateMovie();
                    return true;
                });
            },
            play(name) {
                const gain = host?.gain?.('effects') ?? 1;
                if (stopped || document.hidden || !enabled() || !clips.has(name) || gain === 0)
                    return;
                const voice = clips.get(name).cloneNode();
                baseVolume.set(voice, volumes[name]);
                voice.volume = volumes[name] * gain;
                if (voices.size >= 3) {
                    const old = voices.values().next().value;
                    old.pause();
                    voices.delete(old);
                }
                voices.add(voice);
                const release = () => voices.delete(voice);
                voice.addEventListener('ended', release, { once: true });
                voice.addEventListener('error', release, { once: true });
                voice
                    .play()
                    .then(() => {
                        if (stopped || document.hidden || !enabled() || !voices.has(voice))
                            voice.pause();
                    })
                    .catch(release);
            },
            stop() {
                if (stopped) return;
                stopped = true;
                silence();
                if (movie) movie.volume.gain.value = 0;
                unsubscribe();
                unsubscribeGain?.();
                releaseMusic();
                if (current === session) current = null;
            },
        };
        current = session;
        return session;
    }
    window.addEventListener('pagehide', () => current?.stop());
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) current?.stop();
    });
    return { prepare, start, armMovie, releaseMovie };
})();
