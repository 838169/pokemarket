const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const loaders = [];
const images = [];
const media = { matches: false, addEventListener() {} };
const context = vm.createContext({
    window: { matchMedia: () => media },
    localStorage: { getItem: () => null, setItem() {} },
    document: { body: { dataset: {} }, getElementById: () => null, querySelectorAll: () => images },
    setTimeout,
    clearTimeout,
    Image: class {
        constructor() {
            loaders.push(this);
        }
    },
});
vm.runInContext(fs.readFileSync('frontend/assets/js/api.js', 'utf8'), context);
const api = context.window.PokeMarket;
const image = {
    dataset: {},
    style: {},
    isConnected: true,
    closest: () => null,
    removeAttribute(name) {
        delete this[name];
    },
};
images.push(image);
const pokemon = {
    id: 48,
    nombre: 'Venonat',
    sprite_animado: 'https://example.org/48.gif',
    sprite_animado_hd:
        'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/versions/generation-ix/scarlet-violet/48.webp',
    sprite_animado_hd_espalda:
        'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/versions/generation-ix/scarlet-violet/back/48.webp',
};
api.configurarImagen(image, pokemon);
assert.match(
    image.src,
    /sprites\/48\/home$/,
    'Primero se muestra una imagen nítida, nunca el GIF pequeño'
);
image.onload();
assert.equal(loaders.at(-1).src, api.imagenPokemon(48, 'animated'));
loaders.at(-1).onload();
assert.equal(image.src, api.imagenPokemon(48, 'animated'));

// Toggling animation off invalidates outstanding HD downloads.
api.configurarImagen(image, pokemon);
image.onload();
const stale = loaders.at(-1);
api.cambiarAnimaciones(false);
stale.onload();
assert.match(image.src, /sprites\/48\/home$/);
api.cambiarAnimaciones(true);
image.onload();
loaders.at(-1).onerror();
assert.match(image.src, /sprites\/48\/home$/, 'Un fallo de HD conserva nitidez');
api.configurarImagen(image, { ...pokemon, vista_espalda: true });
image.onload();
assert.equal(loaders.at(-1).src, api.imagenPokemon(48, 'back'));
loaders.at(-1).onload();
assert.equal(image.src, api.imagenPokemon(48, 'back'));
// A response without verified animation must not guess endpoints or enlarge pixel art.
api.configurarImagen(image, { id: 99999, nombre: 'Sin HD' });
const unknown = loaders.length;
image.onload();
assert.equal(loaders.length, unknown);
// System reduced motion takes precedence over animation preferences.
media.matches = true;
assert.equal(api.cambiarAnimaciones(true), false);
const before = loaders.length;
image.onload();
assert.equal(loaders.length, before);
console.log('OK: progressive images, source fallback, stale loads and reduced motion');

// Catalog shares the verified animation path and its static fallback.
media.matches = false;
api.cambiarAnimaciones(true);
const catalogImage = {
    ...image,
    dataset: {},
    style: {},
    closest: (selector) => (selector.includes('.market-page') ? {} : null),
};
api.configurarImagen(catalogImage, pokemon);
assert.match(catalogImage.src, /sprites\/48\/home$/);
const catalogLoaders = loaders.length;
catalogImage.onload();
assert.equal(loaders.length, catalogLoaders + 1);
assert.equal(loaders.at(-1).src, api.imagenPokemon(48, 'animated'));
loaders.at(-1).onload();
assert.equal(catalogImage.src, api.imagenPokemon(48, 'animated'));
api.configurarImagen(image, { ...pokemon, solo_imagen: true });
assert.match(image.src, /sprites\/48\/home$/);
const detachedLoaders = loaders.length;
image.onload();
assert.equal(loaders.length, detachedLoaders, 'Catalog cards stay static before DOM insertion');
api.cambiarAnimaciones(true);
image.onload();
assert.equal(loaders.length, detachedLoaders, 'Animation preferences cannot animate catalog cards');
// Home receives the original Pokémon data and can still use the verified animation.
api.configurarImagen(image, pokemon);
image.onload();
assert.equal(loaders.at(-1).src, api.imagenPokemon(48, 'animated'));
loaders.at(-1).onload();
assert.equal(image.src, api.imagenPokemon(48, 'animated'), 'Purchased companions animate at home');
console.log('OK: animated catalog and home, static fallback and persistent preferences');

const steps = [],
    results = [];
const watched = { ...image, dataset: {} };
const controller = new AbortController();
api.configurarImagen(watched, { ...pokemon, solo_imagen: true }, {
    signal: controller.signal,
    progreso: (value) => steps.push(value),
    terminar: (loaded) => results.push(loaded),
});
assert.deepEqual(steps, [0]);
watched.onerror();
assert.ok(steps[1] > 0 && steps[1] < 100, 'El progreso avanza al cambiar de fuente');
watched.onload();
assert.deepEqual(results, [true], 'El lote se da por terminado al aparecer la imagen');
api.configurarImagen(watched, pokemon, {
    signal: controller.signal,
    progreso() {},
    terminar: (loaded) => results.push(loaded),
});
controller.abort();
assert.deepEqual(results, [true, false], 'Cambiar los filtros libera la carga anterior');
assert.equal(watched.src, undefined);
api.configurarImagen(watched, pokemon, {
    progreso() {},
    terminar: (loaded) => results.push(loaded),
});
for (let attempt = 0; watched.onerror && attempt < 6; attempt++) watched.onerror();
assert.deepEqual(results, [true, false, false], 'Agotar las fuentes libera el lote sin imagen');
