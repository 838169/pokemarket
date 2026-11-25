const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const chromaContext = { window: {} };
vm.runInNewContext(fs.readFileSync('frontend/assets/js/purchase-chroma.js', 'utf8'), chromaContext);
const key = chromaContext.window.PokePurchaseChroma.apply;
const pixels = new Uint8ClampedArray([
    0, 255, 0, 255, 255, 255, 255, 255, 255, 0, 0, 255, 0, 0, 0, 255, 20, 230, 30, 255,
]);
key(pixels);
assert.equal(pixels[3], 0);
for (const index of [7, 11, 15])
    assert.equal(pixels[index], 255, 'Keep white flash, red ball and black band');
assert.equal(pixels[19], 0, 'Tolerate compressed green');
const realBackground = new Uint8ClampedArray([42, 143, 0, 255]);
key(realBackground);
assert.equal(realBackground[3], 0, 'Remove the darker green used in the actual Flow clip');
const edge = new Uint8ClampedArray([130, 190, 140, 255]);
key(edge);
assert.ok(edge[3] > 0 && edge[3] < 255);
assert.ok(edge[1] <= 160, 'Despill semi-transparent edges');

function fixture({ source = '', reduced = false, blocked = false, pending = false } = {}) {
    let now = 0,
        next = 0,
        dialog,
        video,
        restored = 0;
    const timers = new Map(),
        frames = new Map(),
        sounds = [];
    class Node {
        constructor() {
            this.events = {};
            this.dataset = {};
            this.style = {};
            this.children = {};
            this.open = false;
        }
        setAttribute() {}
        removeAttribute() {}
        addEventListener(name, fn) {
            (this.events[name] ||= new Set()).add(fn);
        }
        removeEventListener(name, fn) {
            this.events[name]?.delete(fn);
        }
        emit(name, event = {}) {
            for (const fn of this.events[name] || []) fn(event);
        }
        querySelector(selector) {
            if (!this.children[selector]) this.children[selector] = new Node();
            return this.children[selector];
        }
        showModal() {
            this.open = true;
        }
        close() {
            this.open = false;
            this.emit('close');
        }
        remove() {
            this.removed = true;
        }
        focus() {}
        load() {}
        pause() {
            this.paused = true;
        }
        play() {
            return blocked
                ? Promise.reject(new Error('blocked'))
                : pending
                  ? new Promise(() => {})
                  : Promise.resolve();
        }
        getContext() {
            return {
                drawImage() {},
                getImageData: () => ({ data: new Uint8ClampedArray(4) }),
                putImageData() {},
            };
        }
    }
    const document = new Node();
    document.body = {
        append: (el) => {
            dialog = el;
        },
    };
    document.currentScript = { dataset: { videoSrc: source } };
    document.createElement = (tag) => {
        const node = new Node();
        if (tag === 'video') {
            video = node;
            Object.assign(video, {
                videoWidth: 1920,
                videoHeight: 1080,
                readyState: 2,
                currentTime: 0,
            });
        }
        return node;
    };
    const motion = new Node();
    motion.matches = reduced;
    const window = new Node();
    window.matchMedia = () => motion;
    window.PokeMarket = { configurarImagen() {} };
    window.PokePurchaseChroma = chromaContext.window.PokePurchaseChroma;
    window.PokePurchaseAudio = {
        prepare() {},
        start: () => ({ play: (name) => sounds.push(name), stop: () => restored++ }),
    };
    vm.runInNewContext(fs.readFileSync('frontend/assets/js/purchase-celebration.js', 'utf8'), {
        window,
        document,
        performance: { now: () => now },
        setTimeout: (fn, delay) => {
            timers.set(++next, { fn, at: now + delay });
            return next;
        },
        clearTimeout: (id) => timers.delete(id),
        requestAnimationFrame: (fn) => {
            frames.set(++next, fn);
            return next;
        },
        cancelAnimationFrame: (id) => frames.delete(id),
    });
    return {
        api: window.PokePurchaseCelebration,
        sounds,
        document,
        window,
        motion,
        get dialog() {
            return dialog;
        },
        get video() {
            return video;
        },
        get restored() {
            return restored;
        },
        step(ms) {
            now += ms;
            for (const [id, timer] of [...timers])
                if (timer.at <= now) {
                    timers.delete(id);
                    timer.fn();
                }
            for (const [id, frame] of [...frames]) {
                frames.delete(id);
                frame(now);
            }
        },
    };
}
const flush = async () => {
    for (let i = 0; i < 4; i++) await Promise.resolve();
};
async function run() {
    const pokemon = { id: 25, nombre: 'Pikachu' };
    let f = fixture();
    let done = f.api.play(pokemon);
    assert.equal(f.dialog.dataset.mode, 'fallback');
    f.step(0);
    f.step(250);
    f.step(400);
    f.step(250);
    f.step(150);
    f.step(850);
    await done;
    assert.ok(f.sounds.includes('success'));
    assert.ok(f.dialog.removed);
    assert.equal(f.restored, 1);
    f = fixture({ source: 'capture.mp4' });
    done = f.api.play(pokemon);
    await flush();
    assert.equal(f.dialog.dataset.mode, 'video');
    assert.equal(f.video.muted, true);
    assert.equal(f.video.volume, 0);
    f.video.currentTime = 2.7;
    f.step(0);
    assert.ok(f.sounds.includes('lock'));
    f.video.emit('ended');
    await done;
    assert.equal(f.restored, 1);
    f = fixture({ source: 'bad.mp4', blocked: true });
    done = f.api.play(pokemon);
    await flush();
    assert.equal(f.dialog.dataset.mode, 'fallback', 'Video errors use an immediate fallback');
    f.dialog.querySelector('button').emit('click');
    await done;
    assert.equal(f.restored, 1);
    f = fixture({ source: 'slow.mp4', pending: true });
    done = f.api.play(pokemon);
    f.step(2200);
    assert.equal(f.dialog.dataset.mode, 'fallback', 'Loading deadline prevents a stuck purchase');
    f.document.hidden = true;
    f.document.emit('visibilitychange');
    await done;
    assert.equal(f.restored, 1);
    f = fixture({ reduced: true, source: 'capture.mp4' });
    done = f.api.play(pokemon);
    assert.equal(f.dialog.dataset.mode, 'reduced');
    assert.equal(f.video, undefined);
    f.step(800);
    await done;
    f = fixture();
    done = f.api.play(pokemon);
    f.dialog.emit('cancel', { preventDefault() {} });
    await done;
    assert.equal(f.restored, 1);
    console.log(
        'OK: chroma, mute video, playback cues, missing/blocked/slow video, skip, visibility and reduced motion'
    );
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
