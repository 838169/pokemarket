const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const nodes = new Map();
const get = (id) => {
    if (!nodes.has(id))
        nodes.set(id, {
            value: '3',
            dataset: {},
            hidden: false,
            open: false,
            textContent: '',
            innerHTML: '',
            classList: { add() {}, remove() {} },
            setAttribute() {},
            addEventListener(event, listener) {
                this[`on${event}`] = listener;
            },
            getAnimations() {
                return [];
            },
            showModal() {
                this.open = true;
            },
            close() {
                this.open = false;
            },
        });
    return nodes.get(id);
};
const posts = [];
const celebrations = [];
const exploreSource = fs.readFileSync('frontend/assets/js/explore.js', 'utf8');
const bagContext = {
    state: {
        tipos_bolas: {
            poke: { nombre: 'Poké Ball', icono: 'poke-ball.svg', factor: 1 },
            ultra: { nombre: 'Ultra Ball', icono: 'ultra-ball.svg', factor: 2 },
        },
        bolas: { poke: 4, ultra: 0 },
    },
    selectedBall: 'ultra',
    esc: String,
    $: get,
};
vm.createContext(bagContext);
vm.runInContext(
    exploreSource.slice(
        exploreSource.indexOf('    function renderBag()'),
        exploreSource.indexOf("    $('openBag').onclick")
    ) + '\nrenderBag();',
    bagContext
);
assert.match(get('bagBalls').innerHTML, /Poké Ball/);
assert.doesNotMatch(
    get('bagBalls').innerHTML,
    /Ultra Ball|×0/,
    'La mochila oculta tipos sin existencias'
);
assert.equal(bagContext.selectedBall, 'poke', 'Seleccionar un tipo disponible');
bagContext.state.bolas.poke = 0;
vm.runInContext('renderBag();', bagContext);
assert.match(get('bagBalls').innerHTML, /Aún no tienes Poké Balls/, 'Estado de mochila vacía');
let uncertain = false;
const memory = new Map();
const model = {
    cartera: { monedas: 1000 },
    exploracion: {
        version: 1,
        paquetes_hoy: 0,
        pokeballs: 0,
        capacidad_mochila: 30,
        bolas: { poke: 0, super: 0 },
        tipos_bolas: {
            poke: { nombre: 'Poké Ball', precio: 40, factor: 1, icono: 'poke-ball.svg' },
            super: { nombre: 'Super Ball', precio: 70, factor: 1.5, icono: 'super-ball.svg' },
        },
    },
};
model.exploracion.referencias_captura = [{id: 1, nombre: 'Común', bolas: {
    poke: {normal: 7.17}, super: {normal: 1.5}, malla: {normal: 7.17, agua_bicho: 25.1},
}}];
model.exploracion.tipos_bolas.malla = {nombre: 'Malla Ball', precio: 120, icono: 'net-ball.svg'};
vm.runInNewContext(fs.readFileSync('frontend/assets/js/store.js', 'utf8'), {
    document: { body: { dataset: { store: 'balls' } }, getElementById: get, addEventListener() {} },
    PokeEconomy: { ready: Promise.resolve(), esc: String, number: String, update() {}, celebrate: (...args) => celebrations.push(args) },
    PokeMarket: {
        iniciarSesion: async () => ({ id: 123 }),
        trainer: async (path, options) => {
            if (!options) return model;
            posts.push(structuredClone(options.body));
            if (uncertain) {
                uncertain = false;
                throw Error('Conexión interrumpida');
            }
            return { ...structuredClone(model), resultado: { mensaje: 'Compra confirmada' } };
        },
    },
    window: {},
    crypto: webcrypto,
    requestAnimationFrame: (fn) => fn(),
    setInterval() {},
    setTimeout: (fn) => {
        get('cooldown').finish = fn;
    },
    sessionStorage: {
        getItem: (key) => memory.get(key) || null,
        setItem: (key, value) => memory.set(key, value),
        removeItem: (key) => memory.delete(key),
    },
}, { filename: require('node:path').resolve('frontend/assets/js/store.js') });
const flush = () => new Promise((resolve) => setImmediate(resolve));
const click = (id) =>
    get('storeItems').onclick({
        target: { closest: () => ({ disabled: false, dataset: { storeBuy: id } }) },
    });
(async () => {
    await flush();
    assert.match(get('storeItems').innerHTML, /7%/);
    assert.match(get('storeItems').innerHTML, /25%/);
    assert.match(get('storeItems').innerHTML, /2%/);
    assert.match(get('storeItems').innerHTML, /Squirtle y Caterpie/);
    assert.doesNotMatch(get('storeItems').innerHTML, /7,17%|25,1%/);
    assert.equal(get('storeDailyLimit').textContent, '15 Poké Balls');
    assert.equal(get('storeBagLimit').textContent, '30 Poké Balls');
    model.exploracion.paquetes_hoy = 5;
    get('storePack').onchange();
    assert.match(get('storeItems').innerHTML, /data-game-locked="true" disabled>Cupo agotado hasta medianoche/);
    model.exploracion.paquetes_hoy = 0;
    get('storePack').onchange();
    click('poke');
    assert.equal(posts.length, 0, 'La vista previa no cobra');
    assert.equal(get('ballPurchase').open, true);
    assert.equal(get('ballPreview').dataset.ball, 'poke');
    assert.match(get('ballPurchaseDetail').textContent, /120/);
    get('cancelBallPurchase').onclick();
    assert.equal(posts.length, 0, 'Cancelar no compra');
    get('storePack').value = '6';
    click('super');
    assert.equal(get('ballPreview').dataset.ball, 'super');
    assert.match(get('ballPurchaseDetail').textContent, /420/);
    uncertain = true;
    get('confirmBallPurchase').onclick();
    get('confirmBallPurchase').onclick();
    await flush();
    assert.equal(posts.length, 1, 'Doble clic no duplica la petición');
    assert.equal(posts[0].cantidad, 6);
    assert.equal(posts[0].tipo, 'super');
    assert.equal(get('storeRetry').hidden, false);
    get('storeRetry').onclick();
    await flush();
    assert.deepEqual(posts[1], posts[0], 'El reintento conserva la clave de compra');
    assert.equal(memory.size, 0, 'Compra confirmada limpia la solicitud pendiente');
    assert.match(
        get('storeItems').innerHTML,
        /✓ Guardado/,
        'Confirmación visible antes de permitir otra compra'
    );
    get('confirmBallPurchase').onclick();
    await flush();
    assert.equal(posts.length, 2, 'La pausa posterior a la compra bloquea nuevos cobros');
    get('cooldown').finish();
    assert.equal(celebrations.length, 1, 'Solo celebra la compra confirmada');
    assert.equal(celebrations[0][1], 'Super Ball');
    assert.equal(get('ballPurchase').open, false, 'La confirmación termina tras la pausa');
    console.log(
        'OK: vista previa sin cobro, cancelación, confirmación, paquete elegido, doble clic y recuperación idempotente.'
    );
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
