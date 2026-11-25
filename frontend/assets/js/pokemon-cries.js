/* Selection cries share the game's mute state and never hold up a UI action. */
window.PokePokemonCry = (() => {
    if (window.parent && window.parent !== window && window.parent.PokePokemonCry)
        return window.parent.PokePokemonCry;
    const cache = new Map();
    let current = null,
        activeId = null,
        lastId = null,
        lastPlayed = 0,
        releaseMusic = null,
        generation = 0;
    const valid = (id) => Number.isInteger(Number(id)) && Number(id) >= 1 && Number(id) <= 1025;
    const enabled = () => Boolean(window.PokeGameAudio?.isEnabled());
    const sources = (id) => {
        const base = `assets/audio/cries/${window.PokeCryFiles?.[id] || `${id}.ogg`}`;
        return Number(id) === 25 ? ['assets/audio/cries/pikachu-voice.mp3', base] : [base];
    };
    function preload(id) {
        if (!valid(id)) return;
        const src = sources(Number(id))[0];
        if (cache.has(src)) return;
        const clip = new Audio(src);
        clip.preload = 'auto';
        clip.load();
        cache.set(src, clip);
        if (cache.size > 16) cache.delete(cache.keys().next().value);
    }
    function stop() {
        generation++;
        current?.pause();
        current = null;
        activeId = null;
        releaseMusic?.();
        releaseMusic = null;
    }
    function play(id) {
        if (
            !valid(id) ||
            !enabled() ||
            document.hidden ||
            window.PokeGameAudio?.gain?.('cries') === 0
        )
            return;
        id = Number(id);
        if ((current && activeId === id) || (lastId === id && Date.now() - lastPlayed < 800))
            return;
        stop();
        activeId = lastId = id;
        lastPlayed = Date.now();
        releaseMusic = window.PokeGameAudio?.duck?.(0.35) || null;
        const ticket = generation,
            files = sources(Number(id));
        function attempt(index) {
            if (ticket !== generation || !enabled() || document.hidden) return;
            if (index >= files.length) {
                stop();
                return;
            }
            const src = files[index];
            const clip = cache.get(src)?.cloneNode() || new Audio(src);
            current = clip;
            clip.volume = 0.5 * (window.PokeGameAudio?.gain?.('cries') ?? 1);
            let failed = false;
            const fallback = (error) => {
                if (failed) return;
                failed = true;
                clip.pause();
                if (ticket !== generation) return;
                if (current === clip) current = null;
                if (error?.name !== 'NotAllowedError') attempt(index + 1);
                else stop();
            };
            clip.addEventListener('error', fallback, { once: true });
            clip.addEventListener(
                'ended',
                () => {
                    if (current === clip) stop();
                },
                { once: true }
            );
            clip.play()
                .then(() => {
                    if (ticket !== generation || !enabled() || document.hidden) clip.pause();
                })
                .catch(fallback);
        }
        attempt(0);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
    });
    window.addEventListener('pagehide', stop);
    return {
        play,
        preload,
        stop,
        setGain(value) {
            if (value === 0) stop();
            else if (current) current.volume = 0.5 * value;
        },
    };
})();
