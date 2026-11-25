const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { spawnSync } = require('node:child_process');
const php =
    process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const page = spawnSync(php, ['frontend/pages/catalogo.php'], { encoding: 'utf8' });
assert.equal(page.status, 0, page.stderr);
function element() {
    return {
        disabled: false,
        textContent: '',
        innerHTML: '',
        classList: { remove() {} },
        addEventListener() {},
        setAttribute() {},
        focus() {},
        querySelector() {
            return element();
        },
        querySelectorAll() {
            return [];
        },
        showModal() {
            this.open = true;
        },
        close() {
            this.open = false;
        },
    };
}
const nodes = new Map([...page.stdout.matchAll(/\bid="([^"]+)"/g)].map((m) => [m[1], element()]));
const affordable = {
    id: 274,
    nombre: 'Nuzleaf',
    categoria: 'basicos',
    precio: 600,
    poder_base: 340,
    precio_desglose: { categoria: 200, estadisticas: 320, habilidades: 80 },
};
const expensive = { ...affordable, id: 999, precio: 1200 };
const buys = [affordable, expensive].map((p) => ({ ...element(), dataset: { buy: String(p.id) } }));
const wallet = { monedas: 1000, pokemon_ids: [], necesita_inicial: true };
const messages = [];
const context = vm.createContext({
    window: {},
    PokeMarket: { imagenPokemon: (id) => `/pokemon-api/v1/sprites/${id}/home` },
    document: {
        getElementById: (id) => nodes.get(id) || null,
        querySelectorAll: () => buys,
        addEventListener() {},
    },
    PokeEconomy: {
        wallet,
        esc: String,
        number: String,
        typeNames: {},
        ready: new Promise(() => {}),
        toast: (message) => messages.push(message),
    },
});
let source = fs.readFileSync('frontend/assets/js/market.js', 'utf8');
source = source.replace(
    /\}\)\(\);\s*$/,
    `globalThis.marketFixture={buttons,openPurchase,buy,inventory,setDetail:(p)=>{detailPokemon=p;buttons();}};})();`
);
vm.runInContext(source, context);
const market = context.marketFixture;
market.inventory.set(274, affordable);
market.inventory.set(999, expensive);
market.buttons();
assert.equal(buys[0].disabled, false, 'A first non-starter costing 600 is purchasable with 1000');
assert.equal(buys[1].disabled, true, 'Insufficient funds still disable purchases');
market.setDetail(affordable);
assert.equal(
    nodes.get('modalColeccion').disabled,
    false,
    'The detail dialog also allows the first choice'
);
market.openPurchase(affordable, buys[0]);
assert.equal(nodes.get('purchaseDialog').open, true);
assert.match(
    nodes.get('purchaseContent').innerHTML,
    /400 monedas/,
    'Confirmation shows the remaining balance'
);
nodes.get('purchaseDialog').close();
wallet.monedas = 599;
market.buttons();
assert.equal(buys[0].disabled, true);
market.openPurchase(affordable, buys[0]);
assert.equal(
    nodes.get('purchaseDialog').open,
    false,
    'Insufficient funds do not open confirmation'
);
wallet.monedas = 1000;
wallet.pokemon_ids = [274];
market.buttons();
assert.equal(buys[0].disabled, true, 'Owned Pokémon remain blocked');
assert.equal(nodes.get('modalColeccion').disabled, true);
assert.equal(messages.length, 1);
console.log('OK: first choice, cards, details, confirmation, funds and duplicate ownership');
async function verifyMediaFlow() {
    let resolvePurchase,
        requests = 0,
        celebrations = 0;
    wallet.monedas = 1000;
    wallet.pokemon_ids = [];
    context.PokeEconomy.update = (next) => Object.assign(wallet, next);
    context.PokeEconomy.reveal = () => {};
    context.PokeEconomy.refresh = async () => wallet;
    context.window.PokePurchaseCelebration = {
        prepare() {},
        play() {
            celebrations++;
            return Promise.reject(new Error('broken video'));
        },
    };
    context.PokeMarket = {
        imagenPokemon: (id) => `/pokemon-api/v1/sprites/${id}/home`,
        trainer: () => {
            requests++;
            return new Promise((resolve) => {
                resolvePurchase = resolve;
            });
        },
    };
    market.openPurchase(affordable, buys[0]);
    const purchase = market.buy();
    await market.buy();
    assert.equal(requests, 1, 'A double click never posts two purchases');
    assert.equal(celebrations, 0, 'Do not celebrate before server confirmation');
    resolvePurchase({
        cartera: { ...wallet, monedas: 400, pokemon_ids: [274] },
        primer_companero: true,
    });
    await purchase;
    assert.equal(celebrations, 1);
    assert.match(nodes.get('purchaseContent').innerHTML, /ya tiene hogar/);
    assert.equal(
        nodes.get('purchaseError').textContent,
        '',
        'Media failure cannot invite a second charge'
    );
    wallet.pokemon_ids = [];
    wallet.monedas = 1000;
    context.PokeMarket.trainer = async () => {
        throw new Error('Compra rechazada');
    };
    market.openPurchase(affordable, buys[0]);
    await market.buy();
    assert.equal(celebrations, 1, 'Rejected purchase does not play celebration');
    assert.equal(nodes.get('purchaseError').textContent, 'Compra rechazada');
    console.log(
        'OK: confirmed-only celebration, no double charges, media failure isolation and rejected purchases'
    );
}
verifyMediaFlow().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
