const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let response;
const context = vm.createContext({
    window: { matchMedia: () => ({ matches: false, addEventListener() {} }) },
    document: { body: { dataset: {} }, getElementById: () => null },
    localStorage: { getItem: () => null },
    TextDecoder,
    fetch: async () => response,
});
vm.runInContext(fs.readFileSync('frontend/assets/js/api.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/api.js') });
function stream(events) {
    const bytes = new TextEncoder().encode(events.map(JSON.stringify).join('\n') + '\n');
    // Split both JSON records and multibyte characters across transport chunks.
    let offset = 0;
    return new Response(
        new ReadableStream({
            pull(controller) {
                if (offset >= bytes.length) return controller.close();
                controller.enqueue(bytes.slice(offset, (offset += 7)));
            },
        })
    );
}
(async () => {
    response = stream([
        ...[0, 10, 55, 85, 100].map((percent) => ({ event: 'progress', percent })),
        { event: 'result', data: { success: true, pokemon: { id: 669, nombre: 'Flabébé' } } },
    ]);
    const progress = [];
    const result = await context.window.PokeMarket.detalleConProgreso(669, (n) => progress.push(n));
    assert.deepEqual(progress, [0, 10, 55, 85, 100]);
    assert.equal(result.pokemon.nombre, 'Flabébé');
    response = stream([
        { event: 'progress', percent: 10 },
        { event: 'error', message: 'Sin conexión' },
    ]);
    await assert.rejects(
        () => context.window.PokeMarket.detalleConProgreso(1, () => {}),
        /Sin conexión/
    );
    response = stream([{ event: 'progress', percent: 55 }]);
    await assert.rejects(
        () => context.window.PokeMarket.detalleConProgreso(1, () => {}),
        /incompleta/
    );
    console.log(
        'OK: progreso por etapas, JSON fragmentado, UTF-8, errores y conexión interrumpida'
    );
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
