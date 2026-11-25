const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('frontend/assets/js/store.js', 'utf8');
let now = Date.parse('2026-11-17T04:59:58Z');
let refreshes = 0;
const timer = { textContent: '' };
const context = { window: {}, document: { body: { dataset: { store: 'balls' } }, getElementById: () => timer, addEventListener() {} }, PokeEconomy: {}, setInterval() {},
    Date: class extends Date { static now() { return now; } },
    refresh() { refreshes++; return Promise.resolve(); },
};
vm.runInNewContext(source.slice(0, source.indexOf('    window.pokeNavigationLocked')) + `
    function load() { return refresh(); }
    function status() {}
    window.clock = { sync: syncResetClock, tick: resetCountdown };
})();`, context);
(async () => {
    const state = { ahora: now / 1000 };
    context.window.clock.sync(state);
    assert.equal(timer.textContent, '00:00:02');
    now += 1000;
    context.window.clock.tick();
    assert.equal(timer.textContent, '00:00:01');
    context.window.clock.sync(state);
    assert.equal(timer.textContent, '00:00:01', 'Volver a pintar la tienda no reinicia el reloj');
    now += 1000;
    context.window.clock.tick();
    context.window.clock.tick();
    assert.equal(timer.textContent, '00:00:00');
    assert.equal(refreshes, 1, 'Renueva el estado una sola vez al llegar a cero');
    await new Promise(setImmediate);
    context.window.clock.sync({ ahora: now / 1000 });
    assert.equal(timer.textContent, '24:00:00');
    const server = Date.parse('2026-11-17T00:00:00Z');
    now = Date.parse('2026-11-20T14:35:00Z');
    context.window.clock.sync({ ahora: server / 1000 });
    assert.equal(timer.textContent, '05:00:00', 'Usa medianoche de Colombia y la hora del servidor');
    console.log('OK: cuenta regresiva, medianoche en Colombia, reloj del servidor y renovación única.');
})().catch(error => { console.error(error); process.exitCode = 1; });
