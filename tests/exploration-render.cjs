const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const listeners = new Map();
let fogImages = 0;
function context2d() {
    return new Proxy(
        {},
        {
            get(target, key) {
                if (key in target) return target[key];
                if (key === 'createLinearGradient' || key === 'createRadialGradient')
                    return () => ({ addColorStop() {} });
                if (key === 'measureText') return (value) => ({ width: value.length * 6 });
                if (key === 'drawImage')
                    return () => {
                        fogImages++;
                    };
                return () => {};
            },
            set(target, key, value) {
                target[key] = value;
                return true;
            },
        }
    );
}
function canvas() {
    const ctx = context2d();
    return {
        width: 0,
        height: 0,
        style: {},
        getContext: () => ctx,
        getBoundingClientRect: () => ({ width: 1000, height: 600, left: 0, top: 0 }),
        addEventListener() {},
        focus() {},
        removeEventListener() {},
    };
}
const ctx = vm.createContext({
    window: {},
    Image: class {
        complete = true;
        naturalWidth = 64;
    },
    innerWidth: 1200,
    devicePixelRatio: 1,
    performance: { now: () => 0 },
    requestAnimationFrame: () => 1,
    cancelAnimationFrame() {},
    ResizeObserver: class {
        observe() {}
        disconnect() {}
    },
    matchMedia: () => ({ matches: false }),
    document: {
        hidden: false,
        createElement: canvas,
        getElementById: () => null,
        addEventListener: (name, fn) => listeners.set(name, fn),
        removeEventListener() {},
    },
});
vm.runInContext(fs.readFileSync('frontend/assets/js/explore-world.js', 'utf8'), ctx, { filename: require('node:path').resolve('frontend/assets/js/explore-world.js') });
vm.runInContext(fs.readFileSync('frontend/assets/js/wildlife-sprites.js', 'utf8'), ctx, { filename: require('node:path').resolve('frontend/assets/js/wildlife-sprites.js') });
const World = ctx.window.PokeExploreWorld.World;
const world = new World(
    canvas(),
    {
        terreno: [
            [1, 1, 1],
            [1, 5, 1],
            [1, 1, 1],
        ],
        puntos: [],
        modo: 'expedicion',
        campamento: [1, 1],
        explorado: ['1,1'],
        vision: 6,
    },
    [1, 1],
    () => {}
);
assert.equal(
    world.visibility(1, 1),
    2,
    'El callback de pestaña no sustituye el método de visibilidad'
);
assert.equal(typeof listeners.get('visibilitychange'), 'function');
assert.ok(
    world.fogLayer && world.groundBounds,
    'Se construyen el terreno completo y la niebla suave'
);
assert.ok(
    world.fogLayer.width <= world.groundBounds.w / 2 + 1,
    'Máscara de niebla de media resolución para controlar memoria'
);
const cached = world.fogLayer;
world.draw(16);
assert.equal(world.fogLayer, cached, 'Se reutiliza la textura mientras no cambia lo explorado');
assert.ok(fogImages > 0, 'El renderizador utiliza las capas precalculadas');
world.setWildlife({
    id: 'field',
    pokemon: { id: 19, nombre: 'Rattata' },
    inicio: Date.now() / 1000,
    vence: Date.now() / 1000 + 180,
    perfil: 'corredor',
    modo: 'libre',
    ruta: [
        { x: 1, y: 1, duracion_ms: 1000 },
        { x: 2, y: 1, duracion_ms: 500 },
        { x: 1, y: 1, duracion_ms: 500 },
    ],
});
world.draw(32);
assert.equal(world.wildlifeSprites.size, 1, 'Carga una imagen por especie');
assert.ok(
    world.wildlifeSprites.get(19).sheets.walk && world.wildlifeSprites.get(19).sheets.idle,
    'Animaciones locales Walk e Idle'
);
assert.equal(
    world.wildlifeSprites.get(19).fallback,
    null,
    'La animación local no depende de descargas externas'
);
const sprite = world.wildlifeSprites.get(19);
world.setWildlife({ ...world.wildlife });
assert.equal(
    world.wildlifeSprites.get(19),
    sprite,
    'Actualizar el estado no reinicia la imagen ni su movimiento'
);
assert.ok(world.wildlifePosition(), 'Se dibuja y se puede localizar el Pokémon del campo');
let terrainMoves = 0;
world.onMove = () => terrainMoves++;
world.wildlifeAt = () => null;
world.click({ clientX: 50, clientY: 50 });
assert.equal(terrainMoves, 0, 'Pulsar terreno no ordena movimiento');
let touched = null;
world.wildlifeAt = () => ({ id: 'field' });
world.onWildlife = (id) => {
    touched = id;
};
world.click({ clientX: 50, clientY: 50 });
assert.equal(touched, 'field', 'Pulsar un Pokémon conserva la interacción');
world.setWildlife({ ...world.wildlife, escondite: [1, 1] });
assert.equal(world.isWildlifeHidden(), true, 'La pausa en la hierba oculta al Pokémon');
world.draw(48);
world.reduced = { matches: true };
world.draw(64);
assert.ok(world.wildlifePosition(), 'El arbusto conserva su posición con movimiento reducido');
console.log('OK: constructor real, visibilidad, eventos, luz, niebla y texturas en caché.');
