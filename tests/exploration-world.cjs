const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {}, requestAnimationFrame: () => 1 };
vm.runInNewContext(fs.readFileSync('frontend/assets/js/explore-world.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/explore-world.js') });
const { project, unproject, walkable } = context.window.PokeExploreWorld;
const World = context.window.PokeExploreWorld.World;
const { environmentAt } = context.window.PokeExploreWorld;
assert.equal(environmentAt(100).label, 'Día');
assert.equal(environmentAt(100).darkness, 0);
assert.equal(environmentAt(480).label, 'Noche');
assert.equal(environmentAt(480).darkness, 1);
assert.equal(environmentAt(460).label, 'Atardecer');
assert.ok(environmentAt(460).darkness > 0 && environmentAt(460).darkness < 1);
assert.equal(environmentAt(710).label, 'Amanecer');
assert.ok(environmentAt(710).darkness > 0 && environmentAt(710).darkness < 1);
assert.equal(environmentAt(720).darkness, 0);
assert.deepEqual(
    JSON.parse(JSON.stringify(environmentAt(100))),
    JSON.parse(JSON.stringify(environmentAt(820))),
    'El reloj no depende de recargar ni del número de fotogramas'
);

const fogged = Object.create(World.prototype);
Object.assign(fogged, {
    map: { modo: 'expedicion', vision: 3 },
    actor: [5, 12],
    explored: new Set(['18,10']),
});
assert.equal(fogged.visibility(5, 12), 2, 'Visión local del entrenador');
assert.equal(fogged.visibility(18, 10), 1, 'El mapa recuerda terreno recorrido');
assert.equal(fogged.visibility(22, 16), 0, 'El minimapa y la región ocultan terreno desconocido');
const animated = Object.create(World.prototype);
let arrived = false;
Object.assign(animated, {
    active: true,
    actor: [0, 0],
    path: [[1, 0]],
    last: 0,
    stepMs: 55,
    camera: project(0, 0),
    reduced: { matches: false },
    draw: () => {},
    arrival: () => {
        arrived = true;
    },
});
animated.tick(27.5);
assert.equal(animated.actor[0], 0.5, 'El entrenador interpola su posición');
animated.reduced.matches = true;
animated.tick(55);
assert.equal(arrived, true, 'Cambiar movimiento reducido durante un viaje no lo congela');
// One long frame crosses two cells without dropping its leftover time.
Object.assign(animated, {
    actor: [0, 0],
    path: [
        [1, 0],
        [2, 0],
        [3, 0],
    ],
    segment: null,
    last: 0,
    stepMs: 32,
    reduced: { matches: false },
    arrival: () => {},
});
animated.tick(80);
assert.equal(
    animated.actor[0],
    2.5,
    'Correr conserva tiempo entre casillas y es independiente de FPS'
);
const hit = Object.create(World.prototype);
Object.assign(hit, {
    canvas: { getBoundingClientRect: () => ({ left: 0, top: 0 }) },
    width: 800,
    height: 500,
    zoom: 1,
    camera: project(0, 0),
    map: { puntos: [[7, 9]] },
    sites: [],
});
const site = project(7, 9);
assert.deepEqual(
    Array.from(hit.markerAt(400 + site.x, 270 + site.y - 33)),
    [7, 9],
    'Clic en el icono flotante dirige al avistamiento correcto'
);
for (let y = 0; y < 40; y++)
    for (let x = 0; x < 48; x++) {
        const p = project(x, y),
            tile = unproject(p.x, p.y);
        assert.equal(tile.x, x);
        assert.equal(tile.y, y);
    }
assert.equal(walkable([[0, 1, 2, 3, 4, 5]], -1, 0), false);
assert.equal(walkable([[0, 1, 2, 3, 4, 5]], 3, 0), false);
assert.equal(walkable([[0, 1, 2, 3, 4, 5]], 0, 0), false);
for (const x of [1, 2, 4, 5]) assert.equal(walkable([[0, 1, 2, 3, 4, 5]], x, 0), true);
const html = fs.readFileSync('frontend/pages/explorar.php', 'utf8');
const js = fs.readFileSync('frontend/assets/js/explore.js', 'utf8');
for (const [, id] of js.matchAll(/\$\('([a-zA-Z][a-zA-Z0-9]+)'\)/g))
    assert.ok(html.includes(`id="${id}"`), `Falta control ${id}`);
assert.ok(html.includes('aria-live="polite"'));
assert.ok(html.includes('data-protegida="true"'));
console.log(
    'OK: proyección reversible en las 1920 celdas, colisiones, controles existentes y estado accesible.'
);
async function verifyContinuousRoutes() {
    const stream = Object.create(World.prototype);
    Object.assign(stream, {
        map: { paso_ms: 180 },
        active: true,
        actor: [0, 0],
        path: [],
        pathDurations: [],
        routeEnds: [],
        scheduledSteps: 0,
        completedSteps: 0,
        camera: project(0, 0),
        last: 0,
        reduced: { matches: false },
        draw() {},
    });
    const first = stream.travel(
        [
            [0, 0],
            [1, 0],
            [2, 0],
        ],
        180
    );
    stream.tick(90);
    stream.tick(180);
    stream.tick(270);
    assert.equal(stream.actor[0], 1.5);
    const next = stream.travel(
        [
            [2, 0],
            [3, 0],
            [4, 0],
        ],
        180
    );
    stream.tick(360);
    stream.tick(450);
    assert.equal(
        stream.actor[0],
        2.5,
        'La ruta preparada continúa sin parar ni volver a la casilla anterior'
    );
    stream.tick(540);
    stream.tick(630);
    stream.tick(720);
    await Promise.all([first, next]);
    assert.equal(stream.actor[0], 4);
    assert.equal(stream.routeEnds.length, 0, 'Cada ruta autorizada resuelve su propio final');
    console.log('OK: tramos encadenados sin pausas, saltos ni retrocesos de posición.');
}
verifyContinuousRoutes().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
