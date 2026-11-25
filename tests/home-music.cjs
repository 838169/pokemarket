const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const tracks = [],
    events = {},
    timers = new Map();
let now = 0,
    nextTimer = 0;
const button = {
    attrs: {},
    addEventListener() {},
    setAttribute(k, v) {
        this.attrs[k] = v;
    },
};
const document = {
    hidden: false,
    getElementById: (id) => (id === 'homeMusic' ? button : { textContent: '' }),
    addEventListener: (name, fn) => (events[name] = fn),
};
class Audio {
    constructor(src) {
        this.src = src;
        this.paused = true;
        this.events = {};
        tracks.push(this);
    }
    addEventListener(name, fn) {
        this.events[name] = fn;
    }
    play() {
        this.paused = false;
        return new Promise((resolve, reject) => {
            this.ready = resolve;
            this.reject = reject;
        });
    }
    pause() {
        this.paused = true;
    }
}
const context = vm.createContext({
    document,
    window: { addEventListener: (name, fn) => (events[name] = fn) },
    Audio,
    Date: { now: () => now },
    setInterval: (fn) => {
        timers.set(++nextTimer, fn);
        return nextTimer;
    },
    clearInterval: (id) => timers.delete(id),
});
vm.runInContext(fs.readFileSync('frontend/assets/js/audio-mixer.js', 'utf8'), context);
context.PokeAudioMixer = context.window.PokeAudioMixer;
vm.runInContext(fs.readFileSync('frontend/assets/js/home-music.js', 'utf8'), context);
const music = context.window.PokeHomeMusic;
const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
};
const step = (ms) => {
    now += ms;
    [...timers.values()].forEach((fn) => fn());
};
async function run() {
    music.scene('meadow.mp3');
    assert.equal(tracks.length, 1, 'Music starts enabled');
    music.toggle(true);
    tracks[0].ready();
    await flush();
    step(900);
    assert.equal(tracks[0].volume, 0.25);
    const releaseA = music.duck(0.2);
    step(900);
    assert.equal(tracks[0].volume, 0.05, 'Purchase reduces music without pausing');
    const releaseB = music.duck(0.1);
    step(900);
    releaseA();
    step(900);
    assert.equal(tracks[0].volume, 0.025, 'Releasing one effect preserves another duck');
    music.setVolume(0.4);
    step(900);
    assert.ok(Math.abs(tracks[0].volume - 0.04) < 0.00001);
    releaseB();
    step(900);
    assert.equal(tracks[0].volume, 0.4, 'Restore the latest scene volume');
    music.setVolume(0.25);
    step(900);
    music.scene('forest.mp3');
    assert.equal(
        tracks[0].paused,
        true,
        'Stop the previous section before preparing its replacement'
    );
    assert.equal(tracks[1].volume, 0);
    tracks[1].ready();
    await flush();
    step(450);
    assert.equal(tracks[0].paused, true, 'No two audible music tracks overlap');
    assert.ok(tracks[1].volume > 0 && tracks[1].volume < 0.25);
    step(450);
    assert.equal(tracks[0].paused, true);
    assert.equal(tracks[1].volume, 0.25);
    music.scene('lagoon.ogg');
    music.scene('meadow.mp3');
    tracks[2].ready();
    await flush();
    assert.equal(tracks[2].paused, true, 'Stale scene cannot start after fast switching');
    tracks[3].ready();
    await flush();
    step(900);
    assert.equal(tracks[1].paused, true);
    assert.equal(tracks[3].loop, true, 'Native loop reuses the track');
    tracks[3].currentTime = 42;
    music.toggle(false);
    assert.equal(tracks[3].muted, true, 'Mute is immediate');
    music.toggle(true);
    assert.equal(tracks.length, 4, 'Unmute does not restart');
    assert.equal(tracks[3].currentTime, 42);
    document.hidden = true;
    events.visibilitychange?.();
    document.hidden = false;
    events.visibilitychange?.();
    assert.equal(tracks.length, 4, 'Focus does not restart music');
    assert.equal(tracks[3].paused, false);
    assert.equal(tracks[3].currentTime, 42);
    music.scene('blocked.mp3');
    tracks[4].reject(Object.assign(new Error('blocked'), { name: 'NotAllowedError' }));
    await flush();
    assert.equal(music.isEnabled(), true, 'Autoplay block preserves preference');
    events.pointerdown();
    tracks[5].ready();
    await flush();
    step(900);
    assert.equal(tracks[5].paused, false);
    music.scene('pending.mp3');
    music.toggle(false);
    tracks[6].ready();
    await flush();
    assert.equal(tracks[6].paused, true, 'Mute cancels pending audio');
    events.keydown();
    assert.equal(tracks.length, 7, 'Interaction respects mute');
    console.log(
        'OK: crossfades, ready-before-switch, looping, rapid switching, mute races, visibility and blocked playback'
    );
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
