const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('frontend/assets/js/home.js', 'utf8');
const progress = source.slice(source.indexOf('    function loadingProgress('), source.indexOf('    const typeTags'));
const load = source.slice(source.indexOf('    async function load()'), source.indexOf('    function openPicker()'));
async function checkLoad(count, failed = false) {
    const elements = new Map();
    const timers = [];
    const finishArtwork = [];
    let background;
    const get = id => {
        if (!elements.has(id)) elements.set(id, {
            textContent: '', hidden: false, dataset: {}, attributes: {},
            style: { setProperty() {} }, setAttribute(key, value) { this.attributes[key] = value; },
        });
        return elements.get(id);
    };
    const context = vm.createContext({
        $: get, data: null, preparingHome: true, artworkTasks: [], habitat: 'todos',
        PokeMarket: { trainer: async () => ({ escenarios: [] }) },
        Image: class { constructor() { background = this; } },
        setTimeout(callback, ms) { const timer = { callback, ms }; timers.push(timer); return timer; },
        clearTimeout(timer) { timer.cleared = true; },
    });
    context.render = () => {
        for (let i = 0; i < count; i++) context.artworkTasks.push(new Promise(resolve => finishArtwork.push(resolve)));
    };
    vm.runInContext(progress + load + '\nthis.loadHome = load;', context);
    const ready = context.loadHome();
    await new Promise(setImmediate);
    assert.equal(get('homeLoadingPercent').textContent, '40%');
    for (let i = 0; i < count; i++) {
        finishArtwork[i](!failed);
        await new Promise(setImmediate);
        assert.ok(Number(get('homeLoadingProgress').attributes['aria-valuenow']) < 100);
    }
    assert.equal(timers.some(timer => timer.ms === 250), false, 'No oculta la carga antes del fondo');
    if (failed) background.onerror(); else background.onload();
    await ready;
    assert.equal(get('homeLoadingPercent').textContent, '100%');
    assert.equal(get('homeScene').attributes['aria-busy'], 'false');
    assert.equal(context.preparingHome, false);
    assert.equal(context.artworkTasks.length, 0);
    timers.find(timer => timer.ms === 250).callback();
    assert.equal(get('homeLoading').hidden, true);
    assert.equal(get('homePlacementInfo').textContent.includes('no están disponibles'), failed);
}
(async () => {
    await checkLoad(2);
    await checkLoad(0);
    await checkLoad(2, true);
    console.log('OK: porcentaje por recursos completados, fondo pendiente, hogar vacío e imágenes fallidas.');
})().catch(error => { console.error(error); process.exitCode = 1; });
