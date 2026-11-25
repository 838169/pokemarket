const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const events = {},
    navigations = [],
    tracks = [],
    volumes = [];
const animations = [];
const loading = { hidden: true };
const loadingMessage = {};
const retry = {};
const timers = new Map();
let nextTimer = 0;
const savedAudio = new Map([['pokeAudio-music', '40']]);
const slider = {
    value: '100',
    get valueAsNumber() {
        return Number(this.value);
    },
    dataset: { audioChannel: 'music' },
    previousElementSibling: {},
    addEventListener(name, fn) {
        this[name] = fn;
    },
    dispatchEvent(event) {
        this[event.type]();
    },
};
const master = { addEventListener() {} };
const saveAudio = {
    addEventListener(name, fn) {
        this[name] = fn;
    },
};
const settings = {
    querySelectorAll: () => [slider],
    addEventListener(name, fn) {
        this[name] = fn;
    },
    close() {
        this.open = false;
        this.closeListener();
    },
    showModal() {
        this.open = true;
    },
};
settings.addEventListener = (name, fn) => {
    settings[name + 'Listener'] = fn;
};
let musicGain;
const reducedMotion = {
    matches: false,
    addEventListener: (name, fn) => (events.motionChange = fn),
};
const location = {
    href: 'http://localhost/pokemarket/catalogo.php',
    origin: 'http://localhost',
    replace: (url) => navigations.push(url),
};
const player = {
    scene: (file) => tracks.push(file),
    toggle() {},
    isEnabled: () => true,
    setVolume: (v) => volumes.push(v),
    setGain: (value) => {
        musicGain = value;
    },
};
const frame = {
    src: '',
    contentWindow: { location: { href: 'about:blank', replace: (url) => navigations.push(url) } },
    contentDocument: { title: 'Explorar' },
    setAttribute() {},
    removeAttribute() {},
    addEventListener: (name, fn) => (events[name] = fn),
    animate() {
        const animation = {
            cancelled: false,
            cancel() {
                this.cancelled = true;
            },
        };
        animations.push(animation);
        return animation;
    },
};
const context = {
    window: {
        addEventListener: (name, fn) => (events[name] = fn),
        matchMedia: () => reducedMotion,
    },
    document: {
        getElementById: (id) =>
            ({
                gameView: frame,
                gameLoading: loading,
                gameLoadingMessage: loadingMessage,
                retryGameLoad: retry,
                gameSound: master,
                audioSettings: settings,
                saveAudioSettings: saveAudio,
            })[id],
    },
    localStorage: {
        getItem: (key) => savedAudio.get(key) ?? null,
        setItem: (key, value) => savedAudio.set(key, value),
    },
    Event,
    setTimeout(fn, delay) {
        timers.set(++nextTimer, { fn, delay });
        return nextTimer;
    },
    clearTimeout: (id) => timers.delete(id),
    location,
    URL,
    history: {
        pushState: (_, title, url) => navigations.push(url),
        replaceState: (_, title, url) => navigations.push(url),
    },
    queueMicrotask: (fn) => fn(),
    PokeAudioMixer: { create: () => player, button: () => () => {} },
};
vm.runInNewContext(fs.readFileSync('frontend/assets/js/game-session.js', 'utf8'), context);
const host = context.window.PokeGameAudio;
assert.equal(loading.hidden, true, 'Evita destellos en cargas rápidas');
[...timers.values()].find(({ delay }) => delay === 180).fn();
assert.equal(loading.hidden, false);
[...timers.values()].find(({ delay }) => delay === 12000).fn();
assert.equal(retry.hidden, false, 'Ofrece reintentar en una carga lenta');
assert.equal(musicGain, 0.4);
slider.value = '0';
slider.input();
assert.equal(musicGain, 0);
assert.equal(savedAudio.get('pokeAudio-music'), '40');
assert.equal(saveAudio.disabled, false);
saveAudio.click();
assert.equal(savedAudio.get('pokeAudio-music'), '0');
host.openSettings();
assert.equal(settings.open, true);
assert.ok(frame.src.includes('_contenido=1'));
const first = host.connect({ volume: 0.2 }),
    second = host.connect({ volume: 0.25 });
assert.notEqual(first, second, 'Each view receives a scoped handle to the shared player');
assert.equal(host.navigate('http://localhost/pokemarket/explorar.php'), true);
assert.equal(tracks.at(-1), 'assets/audio/home-meadow.mp3');
const beforeStale = tracks.length;
first.scene('stale-battle.mp3');
second.setVolume(0.99);
assert.equal(tracks.length, beforeStale, 'An unloaded view cannot change the soundtrack');
assert.notEqual(volumes.at(-1), 0.99, 'An old timer cannot alter the current volume');
host.navigate('http://localhost/pokemarket/batalla.php');
host.navigate('http://localhost/pokemarket/catalogo.php');
assert.equal(
    tracks.at(-1),
    'assets/audio/catalog-theme.ogg',
    'Leaving battle restores ambient music'
);
assert.equal(host.navigate('https://example.org/catalogo.php'), false);
assert.equal(host.navigate('http://localhost/other/catalogo.php'), false);
assert.equal(host.navigate('http://localhost/pokemarket/login.php'), false);
assert.ok(fs.readFileSync('.htaccess', 'utf8').includes('frame-ancestors'));
frame.contentWindow.location.href = 'http://localhost/pokemarket/catalogo.php?_contenido=1';
host.viewReady(frame.contentWindow);
assert.equal(animations.length, 1, 'Section entrance animates after load');
assert.equal(loading.hidden, true, 'Ready markup releases the loader before secondary media completes');
events.load();
assert.equal(animations.length, 1, 'The later load event does not restart the entrance');
reducedMotion.matches = true;
events.motionChange();
assert.equal(animations[0].cancelled, true, 'Reduced motion cancels active transitions');
events.load();
assert.equal(animations.length, 1, 'Reduced motion skips section animation');
reducedMotion.matches = false;
let transitions = 0,
    skipped = 0;
context.document.startViewTransition = (update) => {
    transitions++;
    const finished = update();
    return {
        finished,
        skipTransition() {
            skipped++;
        },
    };
};
host.navigate('http://localhost/pokemarket/pokeballs.php');
host.navigate('http://localhost/pokemarket/alimentos.php');
assert.equal(transitions, 2, 'Las tiendas participan en las transiciones nativas');
assert.equal(skipped, 1, 'La navegación rápida interrumpe la transición anterior');
frame.contentWindow.location.href = 'http://localhost/pokemarket/alimentos.php?_contenido=1';
frame.contentDocument = { title: 'Alimentos' };
events.load();
assert.equal(animations.length, 1, 'La transición nativa no se mezcla con otra entrada');
frame.contentWindow.pokeNavigationLocked = () => true;
host.navigate('http://localhost/pokemarket/batalla.php');
assert.equal(transitions, 2, 'Una acción activa conserva la vista actual');
frame.contentWindow.pokeNavigationLocked = () => false;
host.navigate('http://localhost/pokemarket/batalla.php');
assert.equal(transitions, 3, 'La navegación vuelve a estar disponible al terminar');
console.log('OK: persistent music bus across sections, local routes only and framing isolation');
