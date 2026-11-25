const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const clips = [],
    events = {},
    volumes = [];
let enabled = false;
const mixer = {
    scene() {},
    toggle(v) {
        enabled = v;
    },
    isEnabled: () => enabled,
    setVolume: (v) => volumes.push(v),
};
class Audio {
    constructor(src) {
        this.src = src;
        this.paused = true;
        clips.push(this);
    }
    load() {}
    cloneNode() {
        return new Audio(this.src);
    }
    addEventListener() {}
    play() {
        this.paused = false;
        return Promise.resolve();
    }
    pause() {
        this.paused = true;
    }
}
const doc = { hidden: false, addEventListener: (name, fn) => (events[name] = fn) };
const context = vm.createContext({
    Audio,
    document: doc,
    PokeAudioMixer: { create: () => mixer },
    window: {
        matchMedia: () => ({ matches: true }),
        addEventListener: (name, fn) => (events[name] = fn),
    },
    setTimeout: () => 1,
    clearTimeout() {},
});
vm.runInContext(fs.readFileSync('frontend/assets/js/battle-effects.js', 'utf8'), context);
const effects = context.window.PokeBattleEffects;
async function run() {
    await effects.attack(null, null, 'fire');
    assert.equal(clips.length, 0, 'Silent until enabled');
    effects.setSound(true);
    const distinct = new Set();
    for (const type of [
        'normal',
        'fire',
        'water',
        'grass',
        'electric',
        'ice',
        'psychic',
        'ghost',
        'poison',
        'dragon',
        'fairy',
        'fighting',
        'ground',
        'rock',
        'steel',
        'bug',
        'flying',
        'dark',
    ]) {
        await effects.attack(null, null, type);
        const clip = clips.at(-1);
        assert.ok(fs.existsSync('frontend/' + clip.src), type + ': local effect exists');
        assert.equal(fs.readFileSync('frontend/' + clip.src).subarray(0, 4).toString(), 'OggS');
        assert.equal(clip.paused, false, 'Effects remain enabled with reduced motion');
        assert.ok(clip.volume <= 0.3);
        distinct.add(clip.src + ':' + clip.playbackRate);
        assert.ok(clips.filter((c) => !c.paused).length <= 4, 'Bounded overlapping effects');
    }
    assert.equal(distinct.size, 18, 'Each attack type has a distinct sound profile');
    assert.ok(volumes.includes(0.065), 'Music ducks under the attack');
    effects.setSound(false);
    assert.ok(
        clips.every((c) => c.paused),
        'Mute includes all effects'
    );
    effects.setSound(true);
    await effects.attack(null, null, 'water');
    doc.hidden = true;
    events.visibilitychange();
    assert.ok(clips.every((c) => c.paused));
    console.log(
        'OK: 18 attack profiles, local OGG, reduced motion, bounded overlap, ducking and mute'
    );
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
