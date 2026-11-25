const assert = require('node:assert/strict'),
    fs = require('node:fs'),
    vm = require('node:vm');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync('frontend/assets/js/explore-world.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/explore-world.js') });
const { wildlifeMotion, World } = context.window.PokeExploreWorld;
const bush = {
    inicio: 100,
    modo: 'libre',
    escondite: [2, 2],
    ruta: [
        { x: 2, y: 2, duracion_ms: 3200 },
        { x: 3, y: 2, duracion_ms: 500 },
        { x: 2, y: 2, duracion_ms: 500 },
    ],
};
assert.equal(
    wildlifeMotion(bush, 101).hidden,
    true,
    'El arbusto sustituye al Pokémon mientras se esconde'
);
assert.equal(
    wildlifeMotion(bush, 103.4).hidden,
    false,
    'Sale del arbusto para caminar por el terreno'
);
assert.equal(
    wildlifeMotion({ ...bush, modo: 'huyendo' }, 101).hidden,
    false,
    'Un Pokémon huyendo no se oculta mágicamente'
);
vm.runInNewContext(fs.readFileSync('frontend/assets/js/wildlife-sprites.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/wildlife-sprites.js') });
const { wildlifeFrame, wildlifeDirection } = context.window.PokeExploreWorld;
assert.equal(
    wildlifeFrame({ durations: [60, 30] }, 1250),
    1,
    'Respeta la duración de cada fotograma'
);
assert.equal(
    wildlifeFrame({ durations: [60, 30] }, 1250, true),
    0,
    'Movimiento reducido fija el fotograma'
);
assert.equal(wildlifeDirection(1, 0), 7, 'Dirección sureste isométrica');
assert.equal(wildlifeDirection(0, 1), 1, 'Dirección suroeste isométrica');
assert.equal(Object.keys(context.window.PokeWildlifeSprites).length, 100);
for (const [id, animations] of Object.entries(context.window.PokeWildlifeSprites)) {
    assert.ok(fs.existsSync(`frontend/assets/img/wildlife/${id}/credits.txt`), 'Créditos por especie');
    for (const meta of Object.values(animations)) {
        const png = fs.readFileSync('frontend/' + meta.src);
        assert.equal(png.subarray(1, 4).toString(), 'PNG');
        assert.equal(png.readUInt32BE(16) % meta.width, 0);
        assert.equal(png.readUInt32BE(20) / meta.height, meta.directions);
        assert.ok([1, 8].includes(meta.directions));
        assert.ok(
            meta.durations.length <= png.readUInt32BE(16) / meta.width &&
                meta.durations.every((d) => d > 0),
            'Fotogramas válidos'
        );
    }
}
const sighting = {
    id: 'wild',
    pokemon: { id: 19 },
    inicio: 100,
    vence: 280,
    modo: 'libre',
    ruta: [
        { x: 2, y: 2, duracion_ms: 1000 },
        { x: 3, y: 2, duracion_ms: 500 },
        { x: 2, y: 2, duracion_ms: 500 },
    ],
};
let p = wildlifeMotion(sighting, 100);
assert.equal(p.x, 2);
assert.equal(p.moving, false);
p = wildlifeMotion(sighting, 101.25);
assert.equal(p.x, 2.5);
assert.equal(p.moving, true);
assert.equal(
    wildlifeMotion(sighting, 103.25).x,
    2.5,
    'Reloj sincronizado sin reinicio al recargar'
);
const fleeing = {
    ...sighting,
    repetir: false,
    ruta: [
        { x: 2.5, y: 2, duracion_ms: 150 },
        { x: 4, y: 2, duracion_ms: 500 },
        { x: 4, y: 2, duracion_ms: 5000 },
    ],
};
assert.equal(wildlifeMotion(fleeing, 100).x, 2.5, 'Al huir mantiene la posición visible');
assert.equal(wildlifeMotion(fleeing, 200).x, 4, 'No hace un bucle de regreso');
const world = Object.create(World.prototype);
Object.assign(world, {
    wildlife: sighting,
    clockOffset: 101250 - Date.now(),
    canvas: { getBoundingClientRect: () => ({ left: 0, top: 0 }) },
    camera: { x: 0, y: 0 },
    zoom: 1,
    width: 800,
    height: 500,
    visibility: () => 2,
});
const { project } = context.window.PokeExploreWorld;
const point = project(2.5, 2);
assert.equal(
    world.wildlifeAt(400 + point.x, 270 + point.y - 30)?.id,
    'wild',
    'Se puede pulsar el Pokémon en movimiento'
);
world.visibility = () => 0;
assert.equal(
    world.wildlifeAt(400 + point.x, 270 + point.y - 30),
    null,
    'La niebla no revela animales ocultos'
);
world.visibility = () => 2;
world.wildlife = { ...sighting, modo: 'huyendo' };
assert.equal(
    world.wildlifeAt(400 + point.x, 270 + point.y - 30),
    null,
    'Un animal que huye no abre capturas falsas'
);
console.log(
    'OK: reloj compartido, movimiento continuo, huida, interacción y visibilidad de fauna.'
);
