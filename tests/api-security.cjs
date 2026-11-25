const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const requests = [];
const context = vm.createContext({
    window: { matchMedia: () => ({ matches: false, addEventListener() {} }) },
    document: { body: { dataset: {} }, getElementById: () => null },
    localStorage: { getItem: () => null },
    fetch: async (url, options) => {
        requests.push({ url, options });
        return {
            ok: true,
            status: 200,
            json: async () =>
                url.endsWith('/auth/me')
                    ? { success: true, csrf_token: 'token-de-prueba', usuario: { nombre: 'Test' } }
                    : { success: true },
        };
    },
});
vm.runInContext(fs.readFileSync('frontend/assets/js/api.js', 'utf8'), context);
(async () => {
    await context.window.PokeMarket.trainer('/shop/purchase', {
        method: 'POST',
        body: { pokemon_id: 7 },
    });
    assert.equal(requests.length, 2);
    assert.ok(requests[0].url.endsWith('/auth/me'));
    assert.equal(requests[1].options.headers['X-CSRF-Token'], 'token-de-prueba');
    await context.window.PokeMarket.trainer('/home/food', {
        method: 'POST',
        body: { comida: 'baya', cantidad: 1 },
    });
    assert.equal(requests.length, 3);
    await context.window.PokeMarket.trainer('/auth/login', {
        method: 'POST',
        body: { email: 'test@example.invalid', password: 'not-real' },
    });
    assert.equal(requests.at(-1).options.headers['X-CSRF-Token'], undefined);
    for (const route of ['/auth/register-code', '/auth/register-resend']) {
        await context.window.PokeMarket.trainer(route, { method: 'POST', body: {} });
        assert.ok(requests.at(-1).url.endsWith(route));
        assert.equal(requests.at(-1).options.headers['X-CSRF-Token'], undefined);
    }
    assert.equal(requests.length, 6);
    console.log(
        'OK: operaciones autenticadas llevan CSRF, token reutilizado y acceso público independiente.'
    );
})().catch((e) => {
    console.error(e);
    process.exitCode = 1;
});
