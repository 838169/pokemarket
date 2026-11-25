/* Original type-based effects; battle results remain authoritative on the server. */
window.PokeBattleEffects = (() => {
    let audioEnabled = false,
        audioStatus = null,
        restoreTimer = null;
    const active = new Set(),
        cache = new Map();
    const host = window.parent?.PokeGameAudio || window.PokeGameAudio;
    const unsubscribeGain = host?.subscribeGain?.((channel, gain) => {
        if (channel === 'effects') for (const clip of active) clip.volume = 0.3 * gain;
    });
    const music = PokeAudioMixer.create({
        volume: 0.12,
        onStatus: (status) => {
            audioEnabled = music.isEnabled();
            if (!audioEnabled) silenceEffects();
            else prepare();
            if (audioStatus) audioStatus(status);
        },
    });
    const presets = {
        normal: ['impactPunch_medium_000', 1],
        fighting: ['impactPunch_medium_000', 0.8],
        fire: ['thrusterFire_000', 1.15],
        water: ['slime_000', 0.9],
        grass: ['footstep_grass_000', 1.2],
        electric: ['laserLarge_000', 1.3],
        ice: ['impactGlass_light_000', 1.15],
        psychic: ['forceField_000', 1.15],
        ghost: ['forceField_000', 0.7],
        poison: ['slime_000', 0.7],
        dragon: ['explosionCrunch_000', 0.85],
        fairy: ['laserSmall_000', 1.4],
        ground: ['impactMining_000', 0.8],
        rock: ['impactMining_000', 1.1],
        steel: ['impactMetal_medium_000', 1],
        bug: ['knifeSlice', 1.3],
        flying: ['cloth1', 1.2],
        dark: ['chop', 0.85],
    };
    function prepare() {
        for (const [file] of Object.values(presets))
            if (!cache.has(file)) {
                const clip = new Audio(`assets/audio/effects/${file}.ogg`);
                clip.preload = 'auto';
                clip.load();
                cache.set(file, clip);
            }
    }
    function silenceEffects(restoreVolume = true) {
        clearTimeout(restoreTimer);
        if (restoreVolume) music.setVolume(0.12);
        for (const clip of active) clip.pause();
        active.clear();
    }
    function setSound(enabled) {
        audioEnabled = Boolean(enabled);
        if (audioEnabled) prepare();
        else silenceEffects();
        music.toggle(audioEnabled);
    }
    function sound(type) {
        const gain = host?.gain?.('effects') ?? 1;
        if (!music.isEnabled() || document.hidden || gain === 0) return;
        prepare();
        const [file, rate] = presets[type] || presets.normal;
        const clip = cache.get(file)?.cloneNode() || new Audio(`assets/audio/effects/${file}.ogg`);
        clip.volume = 0.3 * gain;
        clip.playbackRate = rate;
        if (active.size >= 4) {
            const oldest = active.values().next().value;
            oldest.pause();
            active.delete(oldest);
        }
        active.add(clip);
        const release = () => active.delete(clip);
        clip.addEventListener('ended', release, { once: true });
        clip.addEventListener('error', release, { once: true });
        clip.play().catch(release);
        music.setVolume(0.065);
        clearTimeout(restoreTimer);
        restoreTimer = setTimeout(() => music.setVolume(0.12), 700);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) silenceEffects();
    });
    window.addEventListener('pagehide', () => {
        unsubscribeGain?.();
        silenceEffects(false);
    });
    music.scene('assets/audio/catalog-theme.ogg');
    const colors = {
        fire: '#ff9548',
        water: '#65caff',
        grass: '#86da76',
        electric: '#ffe477',
        ice: '#b3efff',
        psychic: '#f69adb',
        ghost: '#b0a0ed',
        poison: '#c999e7',
        dragon: '#899bff',
        fairy: '#ffb6d8',
        fighting: '#e9a780',
        ground: '#d7b083',
        rock: '#cbbb8c',
        steel: '#c0d6e3',
        bug: '#bbd87a',
        flying: '#b6d9f5',
        dark: '#a29baa',
        normal: '#e0e7ef',
    };
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    async function attack(attacker, defender, type) {
        sound(type);
        if (reduced()) return;
        const field = attacker.closest('.combate-campo');
        const origin = attacker.querySelector('[data-sprite]');
        const target = defender.querySelector('[data-sprite]');
        if (!field || !origin || !target || !origin.animate) return;
        const box = field.getBoundingClientRect();
        const from = origin.getBoundingClientRect();
        const to = target.getBoundingClientRect();
        const x = from.left + from.width / 2 - box.left;
        const y = from.top + from.height / 2 - box.top;
        const dx = to.left + to.width / 2 - box.left - x;
        const dy = to.top + to.height / 2 - box.top - y;
        const color = colors[type] || colors.normal;
        const translate = (fraction) => `translate(${dx * fraction}px, ${dy * fraction}px)`;
        const effects = [];
        const animations = [];
        try {
            animations.push(
                origin.animate(
                    [
                        { transform: translate(0) },
                        { transform: `${translate(-0.06)} scale(.96)`, offset: 0.19 },
                        { transform: `${translate(0.24)} scale(1.08)`, offset: 0.48 },
                        { transform: translate(0) },
                    ],
                    { duration: 560, easing: 'ease-in-out' }
                )
            );
            for (let i = 0; i < 5; i++) {
                const particle = document.createElement('span');
                particle.className = 'attack-particle';
                particle.dataset.kind = type;
                particle.setAttribute('aria-hidden', 'true');
                particle.style.setProperty('--attack-color', color);
                particle.style.left = `${x - 13}px`;
                particle.style.top = `${y - 13}px`;
                field.appendChild(particle);
                effects.push(particle);
                const spread = (i - 2) * 13;
                animations.push(
                    particle.animate(
                        [
                            { transform: 'translate(0, 0) scale(.3)', opacity: 0 },
                            {
                                transform: `translate(${dx * 0.4}px, ${dy * 0.4 + spread}px) scale(1)`,
                                opacity: 1,
                                offset: 0.45,
                            },
                            { transform: `translate(${dx}px, ${dy}px) scale(.85)`, opacity: 0.9 },
                        ],
                        { duration: 270, delay: 145 + i * 18, easing: 'ease-in', fill: 'backwards' }
                    )
                );
            }
            if (target.animate)
                animations.push(
                    target.animate(
                        [
                            { transform: translate(0), opacity: 1 },
                            {
                                transform: `${translate(0.12)} rotate(${Math.sign(dx) * 8}deg)`,
                                opacity: 0.62,
                                offset: 0.24,
                            },
                            { transform: translate(-0.03), opacity: 1, offset: 0.65 },
                            { transform: translate(0), opacity: 1 },
                        ],
                        { duration: 280, delay: 400, easing: 'ease-out' }
                    )
                );
            const impact = document.createElement('span');
            impact.className = 'battle-impact-ring';
            impact.setAttribute('aria-hidden', 'true');
            impact.style.setProperty('--attack-color', color);
            impact.style.left = `${x + dx}px`;
            impact.style.top = `${y + dy}px`;
            field.appendChild(impact);
            effects.push(impact);
            animations.push(
                impact.animate(
                    [
                        { transform: 'translate(-50%, -50%) scale(.5)', opacity: 0.95 },
                        { transform: 'translate(-50%, -50%) scale(1.65)', opacity: 0 },
                    ],
                    { duration: 260, delay: 400, easing: 'ease-out', fill: 'backwards' }
                )
            );
            await Promise.all(animations.map((animation) => animation.finished.catch(() => {})));
        } finally {
            effects.forEach((element) => element.remove());
            animations.forEach((animation) => animation.cancel());
        }
    }
    return {
        attack,
        color: (type) => colors[type] || colors.normal,
        setSound,
        finish() {
            silenceEffects(false);
            music.scene('assets/audio/catalog-theme.ogg');
            music.setVolume(0.25);
            audioEnabled = music.isEnabled();
        },
        isSoundEnabled: () => audioEnabled,
        setScene(scene) {
            const tracks =
                scene === 'pradera'
                    ? ['battle-meadow.mp3', 'battle-unova.mp3']
                    : ['battle-theme.mp3', 'battle-johto.mp3'];
            music.setVolume(0.12);
            music.scene(`assets/audio/${tracks[Math.floor(Math.random() * tracks.length)]}`);
        },
        onAudioStatus: (callback) => {
            audioStatus = callback;
        },
    };
})();
