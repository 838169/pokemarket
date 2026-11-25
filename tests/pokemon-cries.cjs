const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto');
const manifest = JSON.parse(fs.readFileSync('frontend/assets/audio/cries/inventory.json', 'utf8'));
const clips = [],
    events = {};
let enabled = true;
class Audio {
    constructor(src) {
        this.src = src;
        this.events = {};
        this.paused = true;
        clips.push(this);
    }
    load() {}
    cloneNode() {
        return new Audio(this.src);
    }
    addEventListener(name, fn) {
        this.events[name] = fn;
    }
    play() {
        this.paused = false;
        return new Promise((resolve, reject) => {
            this.resolve = resolve;
            this.reject = reject;
        });
    }
    pause() {
        this.paused = true;
    }
}
const context = vm.createContext({
    Audio,
    document: { hidden: false, addEventListener: (name, fn) => (events[name] = fn) },
    window: {
        PokeGameAudio: { isEnabled: () => enabled },
        addEventListener: (name, fn) => (events[name] = fn),
    },
});
vm.runInContext(fs.readFileSync('frontend/assets/js/pokemon-cries-catalog.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/pokemon-cries-catalog.js') });
vm.runInContext(fs.readFileSync('frontend/assets/js/pokemon-cries.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/pokemon-cries.js') });
const cry = context.window.PokePokemonCry;
for (let id = 1; id <= 1025; id++) {
    const filename = context.window.PokeCryFiles[id] || `${id}.ogg`;
    const data = fs.readFileSync(`frontend/assets/audio/cries/${filename}`);
    assert.equal(crypto.createHash('sha256').update(data).digest('hex'), manifest.sha256[filename]);
    assert.ok(
        filename.endsWith('.ogg')
            ? data.subarray(0, 4).toString() === 'OggS'
            : data[0] === 255 || data.subarray(0, 3).toString() === 'ID3'
    );
}
async function run() {
    cry.play(25);
    const pika = clips.at(-1);
    assert.ok(pika.src.endsWith('pikachu-voice.mp3'));
    cry.play(7);
    const squirtle = clips.at(-1);
    assert.ok(pika.paused, 'Only the latest selection plays');
    pika.resolve();
    await Promise.resolve();
    assert.ok(pika.paused, 'An old pending play cannot restart');
    squirtle.resolve();
    await Promise.resolve();
    assert.ok(!squirtle.paused);
    const beforeSpam = clips.length;
    for (let i = 0; i < 10; i++) cry.play(7);
    assert.equal(clips.length, beforeSpam, 'Repeated clicks do not restart or stack the same cry');
    cry.stop();
    enabled = false;
    const before = clips.length;
    cry.play(25);
    cry.play('../25');
    cry.play(0);
    cry.play(999999);
    assert.equal(clips.length, before, 'Muted and invalid requests create no voice');
    enabled = true;
    cry.play(25);
    const unavailable = clips.at(-1);
    unavailable.events.error();
    assert.ok(unavailable.paused);
    assert.ok(
        clips.at(-1).src.endsWith('/25.mp3'),
        'Fall back to the standard cry if the vocal variant fails'
    );
    cry.play(1);
    const latest = clips.at(-1);
    context.document.hidden = true;
    events.visibilitychange();
    assert.ok(latest.paused);
    context.document.hidden = false;
    for (let id = 1; id <= 30; id++) cry.preload(id);
    events.pagehide();
    console.log(
        'OK: 1025 validated local cries, vocal Pikachu, mute, selection races, fallback and visibility'
    );
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
