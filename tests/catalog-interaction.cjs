const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('frontend/assets/js/market.js', 'utf8');
const start = source.indexOf('    function bindPokemonDrag(');
const end = source.indexOf('    async function load(', start);
const timers = new Map();
let timerId = 0;
const context = {
    setTimeout(fn, ms) {
        timers.set(++timerId, { fn, ms });
        return timerId;
    },
    clearTimeout(id) {
        timers.delete(id);
    },
    matchMedia: () => ({ matches: false }),
    getComputedStyle: (element) => ({ translate: element.style.translate || '0px 0px' }),
};
vm.runInNewContext(`${source.slice(start, end)}; this.bind = bindPokemonDrag;`, context);
const handlers = {};
let recentAnimation = null;
const sprite = {
    style: {},
    getBoundingClientRect: () => ({ width: 200, height: 210 }),
    animate(frames, options) {
        recentAnimation = {
            frames,
            options,
            finished: new Promise(() => {}),
            cancel() {
                this.cancelled = true;
            },
        };
        return recentAnimation;
    },
};
const stage = {
    dataset: {},
    querySelector: () => sprite,
    getBoundingClientRect: () => ({ width: 400, height: 260 }),
    setPointerCapture() {},
    addEventListener: (name, fn) => {
        handlers[name] = fn;
    },
};
let reactions = 0;
context.bind(stage, () => reactions++);
const pointer = (type, x, y, pointerType = 'mouse') =>
    handlers[type]({ type, pointerId: 1, button: 0, pointerType, clientX: x, clientY: y });
pointer('pointerdown', 100, 100, 'touch');
pointer('pointermove', 100, 160, 'touch');
assert.equal(sprite.style.translate, undefined, 'Deslizar con el dedo no arrastra al Pokémon');
assert.equal(stage.dataset.dragging, undefined, 'El gesto táctil queda libre para desplazar la página');
pointer('pointerdown', 100, 100);
pointer('pointermove', 1000, 1000);
assert.equal(sprite.style.translate, '92px 17px', 'No sale por el borde derecho ni inferior');
pointer('pointermove', -1000, -1000);
assert.equal(sprite.style.translate, '-92px -17px', 'No sale por el borde izquierdo ni superior');
assert.equal(reactions, 1, 'Una reacción por arrastre');
pointer('pointerup', -1000, -1000);
stage.onclick();
assert.equal(reactions, 1, 'Soltar después del arrastre no dispara otro toque');
stage.onclick();
assert.equal(reactions, 2, 'El toque y el teclado siguen funcionando');
pointer('pointerdown', 100, 100);
pointer('pointermove', 120, 120);
pointer('pointercancel', 120, 120);
stage.onclick();
assert.equal(reactions, 4, 'Cancelar el gesto no se come la siguiente interacción');
assert.equal(stage.dataset.dragging, 'false');
assert.equal(sprite.draggable, false, 'No aparece el arrastre nativo de la imagen');
const scheduled = [...timers.values()].at(-1);
assert.equal(scheduled.ms, 1800, 'Espera antes de volver solo al centro');
scheduled.fn();
assert.equal(sprite.style.translate, '0px 0px');
assert.equal(recentAnimation.options.duration, 650, 'Regresa al centro suavemente');
pointer('pointerdown', 100, 100);
assert.equal(recentAnimation.cancelled, true, 'Agarrarlo de nuevo interrumpe el regreso');
console.log(
    'OK: arrastre limitado a la tarjeta, toque, cancelación y una sola reacción por gesto.'
);
