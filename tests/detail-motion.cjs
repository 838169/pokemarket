const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = {},
    animations = [];
const reduced = { matches: false, addEventListener() {} };
function node() {
    return {
        dataset: {},
        style: {},
        isConnected: true,
        addEventListener(name, callback) {
            this[name] = callback;
        },
        closest: () => null,
        removeAttribute() {},
    };
}
const nodes = new Map();
for (const id of [
    'pokemonModal',
    'cerrarModal',
    'detalleImagen',
    'detalleNumero',
    'detalleNombre',
    'detalleTipos',
    'detalleDescripcion',
    'detalleAltura',
    'detallePeso',
    'detalleHabilidades',
    'detalleDebilidades',
])
    nodes.set(id, node());
const modal = nodes.get('pokemonModal');
const classes = new Set();
modal.classList = {
    add: (name) => classes.add(name),
    remove: (name) => classes.delete(name),
    contains: (name) => classes.has(name),
};
const card = {
    animate() {
        let finish;
        const animation = {
            finished: new Promise((resolve) => {
                finish = resolve;
            }),
            cancel() {
                this.cancelled = true;
                finish();
            },
            finish: () => finish(),
        };
        animations.push(animation);
        return animation;
    },
};
modal.querySelector = (selector) => (selector === '.pokemon-detail' ? card : node());
const body = { dataset: {}, style: {} };
const document = {
    body,
    getElementById: (id) => nodes.get(id) || null,
    createElement: () => ({
        set textContent(value) {
            this.innerHTML = String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
        },
    }),
    addEventListener: (name, fn) => {
        events[name] = fn;
    },
};
const context = {
    window: { matchMedia: () => reduced, PokeEconomy: { typeNames: { ground: 'Tierra' } } },
    document,
    localStorage: { getItem: () => null },
};
vm.runInNewContext(fs.readFileSync('frontend/assets/js/api.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/api.js') });
const api = context.window.PokeMarket;
const pokemon = { id: 25, nombre: 'Pikachu', tipos: [], habilidades: [], altura: 0.4, peso: 6 };
const flush = async () => {
    for (let i = 0; i < 5; i++) await Promise.resolve();
};
async function run() {
    api.mostrarDetalle({
        ...pokemon,
        tipos: ['electric'],
        debilidades: [{ tipo: 'ground', multiplicador: 2 }],
    });
    assert.equal(modal.dataset.pokemonType, 'electric');
    assert.match(nodes.get('detalleDebilidades').innerHTML, /Tierra/);
    assert.match(nodes.get('detalleDebilidades').innerHTML, /×2/);
    nodes.get('cerrarModal').click();
    nodes.get('cerrarModal').click();
    assert.equal(animations.length, 1, 'Repeated close requests share one exit');
    assert.ok(classes.has('open'), 'Card stays visible during exit');
    api.mostrarDetalle(pokemon);
    assert.equal(
        modal.dataset.pokemonType,
        'normal',
        'Opening another species resets its color theme'
    );
    assert.match(nodes.get('detalleDebilidades').innerHTML, /Información no disponible/);
    await flush();
    assert.ok(animations[0].cancelled);
    assert.ok(classes.has('open'), 'An interrupted exit cannot hide a reopened card');
    nodes.get('cerrarModal').click();
    animations[1].finish();
    await flush();
    assert.ok(!classes.has('open'));
    assert.equal(body.style.overflow, '');
    reduced.matches = true;
    api.mostrarDetalle(pokemon);
    events.keydown({ key: 'Escape' });
    assert.ok(!classes.has('open'), 'Reduced motion closes immediately with Escape');
    assert.equal(animations.length, 2);
    api.mostrarDetalle({ ...pokemon, debilidades: [] });
    assert.match(nodes.get('detalleDebilidades').innerHTML, /Sin debilidades por tipo/);
    api.mostrarDetalle({ ...pokemon, debilidades: [{ tipo: '<script>', multiplicador: 4 }] });
    assert.match(nodes.get('detalleDebilidades').innerHTML, /is-severe/);
    assert.ok(!nodes.get('detalleDebilidades').innerHTML.includes('<script>'));
    console.log('OK: card exit, repeated close, reopening race, Escape and reduced motion');
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
