const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

async function main() {
    let clock = 10000,
        holdResponse = null;
    const elements = new Map(),
        events = {},
        timers = [],
        journeys = [],
        requests = [];
    function element(id) {
        if (!elements.has(id))
            elements.set(id, {
                id,
                hidden: false,
                open: false,
                dataset: {},
                classList: { add() {}, remove() {} },
                parentElement: { classList: { add() {} } },
                setAttribute() {},
                addEventListener() {},
                matches: () => false,
                focus() {},
                replaceChildren() {},
                append() {},
                showModal() {
                    this.open = true;
                },
                close() {
                    this.open = false;
                },
            });
        return elements.get(id);
    }
    let server = {
        version: 1,
        posicion: [1, 1],
        pokeballs: 3,
        descubiertos: [],
        visitados: [],
        enfriamientos: {},
        encuentro: null,
        diario: [],
        capturas_hoy: 0,
        limite_diario: 5,
        tutorial: true,
        tiempo_ms: 60000,
    };
    const map = {
        terreno: Array.from({ length: 4 }, () => Array(9).fill(1)),
        puntos: [[7, 1]],
        especies: [],
        paso_ms: 180,
        correr_ms: 105,
    };
    const wallet = { monedas: 1000 };
    const context = vm.createContext({
        Date: class extends Date {
            static now() {
                return clock;
            }
        },
        window: { addEventListener() {} },
        document: {
            hidden: false,
            getElementById: element,
            querySelectorAll: () => [],
            querySelector: () => null,
            addEventListener: (name, fn) => {
                events[name] = fn;
            },
            createElement: () => element('created'),
        },
        PokeAudioMixer: { create: () => ({ scene() {}, toggle() {} }), button() {} },
        PokeEconomy: {
            ready: Promise.resolve(),
            wallet,
            esc: String,
            typeNames: {},
            update() {},
            toast() {},
        },
        PokeMarket: {
            iniciarSesion: async () => ({ id: 777 }),
            trainer: async (path, options) => {
                if (!options)
                    return { exploracion: structuredClone(server), mapa: map, cartera: wallet };
                const d = options.body;
                if (holdResponse) await holdResponse;
                requests.push({ ...d });
                assert.equal(
                    d.version,
                    server.version,
                    'Las peticiones deben usar la última versión confirmada'
                );
                const start = [...server.posicion];
                const route = [start];
                let [rx, ry] = start;
                while (rx !== d.x || ry !== d.y) {
                    if (rx !== d.x) rx += Math.sign(d.x - rx);
                    else ry += Math.sign(d.y - ry);
                    route.push([rx, ry]);
                }
                server = { ...server, version: server.version + 1, posicion: [d.x, d.y] };
                return {
                    exploracion: structuredClone(server),
                    cartera: wallet,
                    resultado: {
                        tipo: 'camino',
                        camino: route,
                        paso_ms: d.correr ? 105 : 180,
                    },
                };
            },
        },
        matchMedia: () => ({ matches: false }),
        crypto: webcrypto,
        sessionStorage: { getItem: () => null, setItem() {}, removeItem() {} },
        setTimeout: (fn) => {
            fn();
            return 1;
        },
        clearTimeout() {},
        setInterval: (fn, ms) => {
            timers.push({ fn, ms });
            return timers.length;
        },
        PokeExploreWorld: {
            walkable: (g, x, y) => g[y]?.[x] === 1,
            World: class {
                constructor(canvas, map, pos, onMove) {
                    this.actor = pos;
                    this.onMove = onMove;
                    context.world = this;
                }
                setPosition(pos) {
                    this.actor = pos;
                }
                travel(route, speed) {
                    this.speed = speed;
                    return new Promise((resolve) =>
                        journeys.push(() => {
                            clock += (route.length - 1) * speed + 1;
                            resolve();
                        })
                    );
                }
            },
        },
    });
    vm.runInContext(fs.readFileSync('frontend/assets/js/explore.js', 'utf8'), context);
    const flush = () => new Promise((resolve) => setImmediate(resolve));
    const key = (name, type = 'keydown') => events[type]({ key: name, preventDefault() {} });
    const repeat = () => timers.find((t) => t.ms === 16).fn();
    await flush();
    key('ArrowRight');
    await flush();
    assert.equal(requests.length, 1);
    assert.equal(
        requests[0].x,
        3,
        'El teclado mueve al entrenador incluso sin foco exacto en el canvas'
    );
    repeat();
    assert.equal(requests.length, 1, 'Mantener la tecla no solapa peticiones');
    clock += 230;
    repeat();
    await flush();
    assert.equal(
        requests.length,
        2,
        'El siguiente tramo se prepara antes de terminar la animación actual'
    );
    assert.equal(
        journeys.length,
        2,
        'La autorización nueva queda enlazada sin reiniciar la ruta visible'
    );
    key('ArrowRight', 'keyup');
    journeys.shift()();
    await flush();
    repeat();
    await flush();
    assert.equal(
        requests[1].x,
        6,
        'Mantener la tecla continúa por tramos encadenados de tres celdas'
    );
    journeys.shift()();
    await flush();
    repeat();
    await flush();
    assert.equal(requests.length, 2, 'Soltar la tecla detiene el avance continuo');
    element('exploreRun').onclick();
    key('ArrowRight');
    await flush();
    assert.equal(requests[2].correr, true, 'Correr se envía al servidor');
    assert.equal(context.world.speed, 105, 'El renderizador utiliza la velocidad autorizada');
    key('ArrowRight', 'keyup');
    context.world.onMove(7, 2);
    await flush();
    assert.equal(
        requests.length,
        3,
        'Marcar otro destino durante un tramo no lo interrumpe ni duplica'
    );
    journeys.shift()();
    await flush();
    assert.equal(requests.length, 4);
    assert.equal(requests[3].x, 7);
    assert.equal(requests[3].y, 2, 'El destino en cola se ejecuta tras guardar el tramo anterior');
    journeys.shift()();
    await flush();
    assert.equal(element('exploreSave').textContent, '✓');
    let confirm;
    holdResponse = new Promise((resolve) => { confirm = resolve; });
    const previews = [];
    context.world.previewStep = (from, to, speed) => {
        previews.push({ from, to, speed });
        return Promise.resolve();
    };
    key('ArrowUp');
    await flush();
    assert.equal(previews.length, 1, 'El primer paso se dibuja antes de recibir la respuesta');
    assert.equal(previews[0].to.join(','), '7,1');
    assert.equal(element('exploreSave').textContent, '✓', 'Caminar no parpadea entre guardados');
    clock += 110;
    repeat();
    await flush();
    assert.equal(previews.length, 2, 'La caminata continúa mientras TiDB confirma el tramo anterior');
    assert.equal(previews[1].to.join(','), '7,0');
    confirm();
    key('ArrowUp', 'keyup');
    await flush();
    assert.equal(requests.at(-1).compacto, true);
    assert.equal(requests.at(-1).camino[0].join(','), '7,0', 'El siguiente lote conserva los pasos recorridos');
    assert.equal(journeys.length, 0, 'La confirmación no repite la animación del primer paso');
    console.log(
        'OK: avance continuo, liberación de teclas, peticiones serializadas, correr y cambio de destino en ruta.'
    );
}
main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
