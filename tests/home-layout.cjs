const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync('frontend/assets/js/pokemon-scale.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/pokemon-scale.js') });
vm.runInNewContext(fs.readFileSync('frontend/assets/js/home-layout.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/home-layout.js') });
const { plan, page } = context.window.PokeHomeLayout;
const overlap = (a, b) =>
    a.left < b.left + b.width &&
    a.left + a.width > b.left &&
    a.top < b.top + b.height &&
    a.top + a.height > b.top;
for (const scene of ['todos', 'pradera', 'bosque', 'laguna']) {
    for (const [width, height] of [
        [1790, 900],
        [1440, 724],
        [1000, 503],
        [999, 644],
        [640, 413],
        [599, 386],
        [390, 468],
        [300, 360],
        [274, 329],
        [390, 390],
        [274, 274],
    ]) {
        const layout = plan(scene, width, height);
        for (const slot of layout.slots) {
            assert.ok(
                slot.left >= 0 && slot.top >= 64,
                'Espacio dentro del paisaje, bajo el encabezado'
            );
            assert.ok(
                slot.left + slot.width <= width && slot.top + slot.height <= height,
                'Imagen y etiqueta dentro del paisaje'
            );
            assert.ok(slot.width >= 44 && slot.height >= 44, 'Área táctil suficiente');
        }
        layout.slots.forEach((a, i) =>
            layout.slots
                .slice(i + 1)
                .forEach((b) =>
                    assert.equal(overlap(a, b), false, `${scene} ${width}: espacios superpuestos`)
                )
        );
        if (scene === 'laguna' && width >= 600)
            layout.slots.forEach((slot) =>
                assert.ok(slot.left + slot.width <= width * 0.76, 'Residentes sobre la orilla')
            );
        const residents = Array.from({ length: 37 }, (_, i) => ({ id: i + 1 }));
        const seen = [];
        for (let index = 0; index < Math.ceil(residents.length / layout.capacity); index++) {
            const group = page(residents, index, layout.capacity);
            assert.ok(
                group.items.length <= layout.slots.length,
                'No reutilizar espacios en un grupo'
            );
            seen.push(...group.items.map((p) => p.id));
        }
        assert.deepEqual(
            seen,
            residents.map((p) => p.id),
            'Todos siguen accesibles, incluso con más de 12 compañeros'
        );
        assert.equal(
            page(residents, 999, layout.capacity).current,
            Math.ceil(37 / layout.capacity) - 1
        );
    }
}
assert.equal(page([], 10, 5).current, 0);
assert.equal(
    plan('todos', 1790, 900).capacity,
    6,
    'Los seis compañeros tienen lugares distintos sin cambiar de grupo'
);
console.log(
    'OK: cuatro paisajes, nueve tamaños, sin colisiones ni desbordamiento, paginación completa y orilla de laguna.'
);

for (const scene of ['todos', 'pradera', 'bosque', 'laguna']) {
    for (const [width, height] of [
        [1440, 724],
        [640, 413],
        [300, 450],
    ]) {
        const layout = plan(scene, width, height);
        assert.equal(layout.slots.length, 6);
        const six = Array.from({ length: 6 }, (_, i) => ({ id: i + 1 }));
        assert.deepEqual(
            Array.from(
                page(six, Math.floor(5 / layout.capacity), layout.capacity).items,
                (p) => p.id
            ),
            [1, 2, 3, 4, 5, 6],
            'Seleccionar el sexto conserva visibles a los otros cinco'
        );
        assert.equal(page(six, 0, layout.capacity).pages, 1);
    }
}

// Exercise actual rendered rectangles, not only the height curve, with every species.
const scale = context.window.PokePokemonScale;
const catalog = JSON.parse(fs.readFileSync('backend/pokemon-service/config/market_catalog.json', 'utf8'));
assert.equal(catalog.length, 1025);
const archeus = catalog.find((p) => p.id === 493);
const pika = catalog.find((p) => p.id === 25);
const alone = plan('todos', 1790, 900, [archeus]).slots[0];
assert.ok(
    alone.imageSize > 260 && alone.imageSize < 315,
    'Arceus gets a modest boost above the familiar size'
);
assert.equal(
    alone.left,
    plan('todos', 1790, 900, [archeus, pika]).slots[0].left,
    'El primer lugar es fijo al añadir compañeros'
);
const trio = plan('todos', 1790, 900, [archeus, pika, catalog[0]]).slots;
assert.ok(
    trio[0].imageSize > trio[1].imageSize && trio[0].imageSize <= trio[1].imageSize * 1.25,
    'Only a modest increase for taller species'
);
assert.equal(trio[1].imageSize, 1790 * 0.14, 'Pikachu retains the familiar size');
assert.equal(scale.factor(0.1), 1);
assert.equal(scale.factor(1.5), 1);
assert.ok(scale.factor(3.2) > 1 && scale.factor(100) <= 1.25);
const mixed = plan('todos', 1790, 900, [
    pika,
    archeus,
    catalog[320],
    catalog[889],
    catalog[0],
    catalog[94],
]).slots;
assert.equal(mixed[0].imageSize, trio[1].imageSize, 'Giants do not shrink their small neighbours');
assert.ok(
    Math.abs(trio[0].imageSize - alone.imageSize) < 1,
    'Adding companions does not change scale when there is room'
);
for (const bad of [undefined, null, -4, 0, Number.NaN, Number.POSITIVE_INFINITY, 'oops'])
    assert.equal(scale.factor(bad), scale.factor(1));
for (const [width, height] of [
    [1790, 900],
    [1440, 724],
    [1000, 503],
    [640, 413],
    [390, 585],
    [274, 411],
]) {
    for (const scene of ['todos', 'pradera', 'bosque', 'laguna']) {
        for (const pokemon of catalog) {
            for (const count of [1, 2, 3, 4, 5, 6]) {
                const people = [
                    pokemon,
                    pika,
                    catalog[94],
                    catalog[320],
                    catalog[889],
                    catalog[0],
                ].slice(0, count);
                const layout = plan(scene, width, height, people);
                assert.equal(layout.slots.length, count);
                for (const slot of layout.slots) {
                    assert.ok(
                        slot.left >= -0.00001 && slot.top >= 64 - 0.00001,
                        `${scene}: inside top/left`
                    );
                    assert.ok(
                        slot.left + slot.width <= width + 0.00001 &&
                            slot.top + slot.height <= height + 0.00001
                    );
                    assert.ok(slot.imageSize >= Math.min(48, slot.width), 'Visible silhouette');
                }
                layout.slots.forEach((a, i) =>
                    layout.slots
                        .slice(i + 1)
                        .forEach((b) =>
                            assert.equal(overlap(a, b), false, `${scene}: no intersections`)
                        )
                );
            }
        }
    }
}
console.log(
    'OK: all 1025 species, solo/trio/six residents, all habitats and sizes, realistic Arceus contrast, no clipping or overlap'
);
