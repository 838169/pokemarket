const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let on = true,
    listener,
    restored = 0,
    stoppedCries = 0;
const tracks = [],
    events = {};
class Audio {
    constructor(src) {
        this.src = src;
        this.paused = true;
        tracks.push(this);
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
const host = {
    isEnabled: () => on,
    duck: (factor) => {
        assert.equal(factor, 0.2);
        return () => restored++;
    },
    subscribe: (fn) => {
        listener = fn;
        return () => {
            listener = null;
        };
    },
};
const document = {
    hidden: false,
    addEventListener: (name, fn) => {
        events[name] = fn;
    },
};
const context = {
    Audio,
    document,
    window: {
        parent: { PokeGameAudio: host, PokePokemonCry: { stop: () => stoppedCries++ } },
        addEventListener: (name, fn) => {
            events[name] = fn;
        },
    },
};
vm.runInNewContext(fs.readFileSync('frontend/assets/js/purchase-audio.js', 'utf8'), context);
const audio = context.window.PokePurchaseAudio;
audio.prepare();
assert.equal(tracks.length, 6);
for (const track of tracks) {
    assert.equal(fs.readFileSync('frontend/' + track.src).subarray(0, 4).toString(), 'OggS');
    assert.ok(track.paused, 'Preparing audio must not celebrate a pending purchase');
}
let session = audio.start();
session.play('receipt');
session.play('arrival');
session.play('energy');
session.play('lock');
assert.equal(tracks.filter((t) => !t.paused).length, 3, 'Bound overlap');
on = false;
listener(false);
assert.ok(
    tracks.every((t) => t.paused),
    'Global mute stops active effects immediately'
);
const count = tracks.length;
session.play('success');
assert.equal(tracks.length, count, 'Muted cues do not create more audio');
session.stop();
session.stop();
assert.equal(restored, 1, 'Restore music once');
on = true;
session = audio.start();
session.play('success');
document.hidden = true;
events.visibilitychange();
assert.equal(restored, 2);
assert.ok(tracks.every((t) => t.paused));
assert.equal(listener, null, 'Release parent subscription when the sequence ends');
assert.ok(stoppedCries >= 2);
assert.ok(tracks.every((t) => t.volume === undefined || t.volume <= 0.36));
async function verifyOriginalTrack() {
    const gain = { gain: { value: 1 }, connect() {}, disconnect() {} };
    const source = { connect: () => gain, disconnect() {} };
    context.window.AudioContext = class {
        state = 'running';
        resume() { return Promise.resolve(); }
        createGain() { return gain; }
        createMediaElementSource() { return source; }
    };
    const movie = new Audio('assets/video/compra-pokebola.mp4');
    document.hidden = false;
    audio.armMovie(movie);
    assert.equal(gain.gain.value, 0, 'La pista queda silenciada antes de la confirmación');
    const session = audio.start();
    assert.equal(await session.movie(movie), true);
    assert.equal(gain.gain.value, 1);
    on = false;
    listener(false);
    assert.equal(gain.gain.value, 0, 'La pista original respeta el mute del juego');
    session.stop();
    audio.releaseMovie(movie);
}
verifyOriginalTrack().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
console.log(
    'OK: local CC0 cues, no premature success, bounded overlap, shared mute and music restoration'
);
