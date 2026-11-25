const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { spawnSync } = require('node:child_process');

// Use the real PHP output: missing template elements must not be invented by the test.
const php =
    process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const rendered = spawnSync(php, ['frontend/pages/batalla.php'], { encoding: 'utf8' });
assert.equal(rendered.status, 0, rendered.stderr);
const source = fs.readFileSync('frontend/assets/js/batalla.js', 'utf8');
const ids = [...rendered.stdout.matchAll(/\bid="([^"]+)"/g)].map((m) => m[1]);
assert.equal(new Set(ids).size, ids.length, 'IDs must be unique');
for (const [, id] of source.matchAll(/byId\('([^']+)'\)/g)) {
    assert.ok(ids.includes(id), `The battle template is missing #${id}`);
}
function element(dataset = {}) {
    return {
        dataset,
        hidden: false,
        disabled: false,
        textContent: '',
        listeners: {},
        attributes: {},
        children: [],
        replaceChildren() {
            this.children = [];
        },
        appendChild(child) {
            this.children.push(child);
        },
        querySelector() {
            return { addEventListener() {}, focus() {} };
        },
        querySelectorAll() { return []; },
        focus() {},
        scrollIntoView() {},
        showModal() {
            this.open = true;
        },
        close() {
            this.open = false;
        },
        classList: { toggle() {} },
        addEventListener(name, fn) {
            this.listeners[name] = fn;
        },
        setAttribute(name, value) {
            this.attributes[name] = value;
        },
    };
}
const elements = new Map(ids.map((id) => [id, element()]));
const modes = [...rendered.stdout.matchAll(/data-modo="([^"]+)"/g)].map((m) =>
    element({ modo: m[1] })
);
const requests = [];
let tick;
let offer = null,
    opened = null,
    confirmation = false;
const context = vm.createContext({
    document: {
        createElement: () => element(),
        getElementById: (id) => elements.get(id) || null,
        querySelectorAll: (selector) => (selector === '[data-modo]' ? modes : []),
        addEventListener() {},
    },
    window: { matchMedia: () => ({ matches: false }) },
    setInterval: (callback) => ((tick = callback), 1),
    clearInterval() {},
    localStorage: { getItem: () => null },
    PokeEconomy: { ready: new Promise(() => {}), refresh: async () => {} },
    PokeMarket: {
        escaparHTML: (value) => value,
        animacionesActivas: () => true,
        configurarImagen() {},
        imagenPokemon: (id) => `/pokemon-api/v1/sprites/${id}/home`,
        trainer: async (path, options) => {
            if (path === '/friends') return { amigos: [] };
            requests.push({ path, options });
            return { batalla: { id: 'test-lobby', modo: options.body.modo } };
        },
    },
    PokeAudioMixer: { button: () => () => {} },
    PokeBattleEffects: { onAudioStatus() {}, color: () => '#ff9977', finish() {}, setScene() {} },
    PokeWager: { init() {}, choice: () => offer, confirm: async () => confirmation },
});
vm.runInContext(source, context);
vm.runInContext('globalThis.originalRequestDuels = requestDuels;', context);
vm.runInContext(fs.readFileSync('frontend/assets/js/battle-tutorial.js', 'utf8'), context);
context.PokeTutorial = context.window.PokeTutorial;
vm.runInContext('abrirCampo = (state) => { globalThis.opened = state; };', context);
async function run() {
    const attackDialog = elements.get('selectorAtaques');
    const attackTrigger = elements.get('abrirAtaques');
    context.document.querySelector = () => null;
    vm.runInContext(
        `
        batalla = { modo: 'practica', estado: 'en_curso', turno: 1, activo_rival: 0,
            equipo_rival: { pokemon: [{ nombre: 'Charmander', tipos: ['fire'], hp_actual: 17, hp_maximo: 125 }] } };
        guiaVisible = true;
        pasoPractica = 0;
    `,
        context
    );
    elements.get('movimientos').querySelector = () => null;
    vm.runInContext('actualizarSelectorAtaques(); mostrarAtaques();', context);
    assert.equal(
        attackTrigger.hidden,
        true,
        'Keep the introduction visible before attacks are allowed'
    );
    assert.notEqual(attackDialog.open, true);
    elements.get('movimientos').querySelector = () => ({});
    vm.runInContext(`
        pasoPractica = 1;
        batalla.iniciativa = { orden: ['rival', 'jugador'] };
        actualizarSelectorAtaques();
        mostrarAtaques();
    `, context);
    assert.equal(attackTrigger.hidden, true, 'El selector espera el ataque rival');
    assert.notEqual(attackDialog.open, true);
    vm.runInContext('batalla.rival_actuo = true; actualizarSelectorAtaques(); mostrarAtaques();', context);
    assert.equal(attackDialog.open, true, 'Open automatically when the tutorial allows an attack');
    assert.match(elements.get('rivalAtaque').innerHTML, /Charmander.*Fuego.*17 \/ 125 PS/);
    vm.runInContext('procesando = true; actualizarSelectorAtaques();', context);
    assert.equal(attackDialog.open, false, 'Close while the attack resolves');
    vm.runInContext('procesando = false; batalla = null;', context);
    vm.runInContext(`
        batalla = {
            equipo_jugador: { pokemon: [{ nombre: 'Pikachu' }] }, activo_jugador: 0,
            equipo_rival: { pokemon: [{ nombre: 'Paras' }] }, activo_rival: 0,
        };
        presentarRival(batalla);
    `, context);
    assert.equal(elements.get('faceoffRivalImage').alt, 'Rival · Paras');
    assert.equal(elements.get('faceoffCount').textContent, '5');
    for (let i = 0; i < 4; i++) tick();
    assert.equal(elements.get('battleFaceoff').open, true);
    assert.equal(elements.get('faceoffCount').textContent, '1');
    tick();
    assert.equal(elements.get('battleFaceoff').open, false);
    vm.runInContext('batalla = null;', context);
    assert.equal(elements.get('selectorEquipos').hidden, true);
    assert.equal(elements.get('wagerBoard').hidden, true);
    for (const mode of modes) {
        if (mode.dataset.modo === 'amigos') {
            elements.get('teamDuels').hidden = true;
            mode.listeners.click();
            assert.equal(elements.get('teamDuelsTitle').textContent, 'Duelo con amigos');
            assert.equal(elements.get('duelFriendList').hidden, false);
            assert.equal(elements.get('duelSearchSubmit').hidden, true);
            assert.match(rendered.stdout, /assets\/img\/adventure\/modo-amigos\.jpg/);
            requests.pop();
            continue;
        }
        if (mode.dataset.modo === 'equipos') {
            assert.match(rendered.stdout, /<section id="teamDuels"/);
            elements.get('teamDuels').hidden = true;
            vm.runInContext('requestDuels = async () => {};', context);
            mode.listeners.click();
            assert.equal(elements.get('teamDuels').hidden, false);
            assert.equal(elements.get('panelInicio').hidden, true);
            vm.runInContext(`
                alineacionDuelo = () => [25];
                const availableTrainer = { id: 10, usuario: 'juan', nivel: 1, rango: 'Casual', cantidad: 1, estado: 'buscando' };
                drawDuels({ jugadores: [availableTrainer], duelos: [], buscando: false });
            `, context);
            assert.match(elements.get('duelPlayers').innerHTML, /Pulsa Buscar partida/);
            assert.doesNotMatch(elements.get('duelPlayers').innerHTML, /@juan|data-duel="invitar"/);
            vm.runInContext('drawDuels({ jugadores: [availableTrainer], duelos: [], buscando: 7 });', context);
            assert.match(elements.get('duelPlayers').innerHTML, /@juan/);
            assert.match(elements.get('duelPlayers').innerHTML, /data-duel="invitar"/);
            vm.runInContext(`drawDuels({ jugadores: [], buscando: 7, duelos: [{
                id: 'challenge', estado: 'pendiente', recibida: true, rival: 'juan', emparejado: true, vence: 123
            }] });`, context);
            assert.equal(elements.get('duelChallengePanel').open, true);
            assert.match(elements.get('duelChallengeContent').innerHTML, /@juan/);
            assert.doesNotMatch(elements.get('duelInvites').innerHTML, /@juan/);
            context.performance = { now: () => 0 };
            vm.runInContext(`
                const fighter = { id: 25, nombre: 'Pikachu', hp_actual: 20, hp_maximo: 20, movimientos: [] };
                const duel = { id: 'duel', rival: 'ash', nivel_rival: 4, partida: {
                    equipo: [fighter], rival: [fighter], activo: 0, activo_rival: 0, turno: true
                }};
                byId('duelMatch').hidden = true;
                drawDuelArena(duel);
            `, context);
            assert.match(elements.get('duelMatch').innerHTML, /Duelo individual/);
            assert.match(elements.get('duelMatch').innerHTML, /duel-faceoff/);
            vm.runInContext(`duel.partida.equipo.push({ ...fighter, id: 133, nombre: 'Eevee' }); drawDuelArena(duel);`, context);
            assert.match(elements.get('duelMatch').innerHTML, /Combate por equipos/);
            assert.match(elements.get('duelMatch').innerHTML, /Cambiar a Eevee/);
            requests.pop();
            continue;
        }
        mode.listeners.click();
        assert.equal(elements.get('confirmarEntrada').open, true);
        assert.equal(requests.length, 0, 'Selecting a mode never starts a battle');
        assert.equal(elements.get('selectorEquipos').hidden, mode.dataset.modo === 'practica');
        assert.equal(elements.get('wagerBoard').hidden, mode.dataset.modo !== 'apuesta');
        elements.get('confirmarEntrada').close();
    }
    vm.runInContext(
        "equipoSeleccionado = 7; miembrosEquipos.set(7, [{pokemon_id:7},{pokemon_id:25}]); pokemonPropios.set(25,{id:25,nombre:'Pikachu'}); seleccionarModo('rapido');",
        context
    );
    assert.equal(elements.get('iniciarBatalla').disabled, true, 'Choose a lead before starting');
    assert.equal(elements.get('opcionesPokemonInicial').children.length, 2);
    elements.get('opcionesPokemonInicial').children[1].onclick();
    assert.equal(
        elements.get('opcionesPokemonInicial').children[1].attributes['aria-pressed'],
        'true'
    );
    assert.equal(elements.get('iniciarBatalla').disabled, false);
    await vm.runInContext('comenzarBatalla()', context);
    assert.equal(requests[0].path, '/battles');
    assert.equal(requests[0].options.body.modo, 'rapido');
    assert.equal(requests[0].options.body.pokemon_id, 25, 'Send the chosen second Pokémon');
    assert.equal(context.opened.modo, 'rapido');
    vm.runInContext("batallaGuardada = null; seleccionarModo('apuesta');", context);
    assert.equal(elements.get('iniciarBatalla').disabled, true, 'A wager needs an offer');
    offer = { id: 'test-offer', cantidad: 100 };
    vm.runInContext('actualizarInicio()', context);
    assert.equal(elements.get('iniciarBatalla').disabled, false);
    await vm.runInContext('comenzarBatalla()', context);
    assert.equal(requests.length, 1, 'Cancel must not start or debit a wager');
    confirmation = true;
    await vm.runInContext('comenzarBatalla()', context);
    assert.equal(requests[1].options.body.modo, 'apuesta');
    assert.equal(requests[1].options.body.oferta_id, 'test-offer');
    assert.equal(context.opened.modo, 'apuesta');
    await new Promise((resolve) => setImmediate(resolve));
    let releasePoll;
    const duelRequests = [];
    context.PokeMarket.trainer = async (path, options) => {
        duelRequests.push(options?.body?.accion || 'poll');
        if (!options?.body) await new Promise((resolve) => { releasePoll = resolve; });
        return { jugadores: [], duelos: [], buscando: false, hora_servidor: 0 };
    };
    vm.runInContext('requestDuels = originalRequestDuels; drawDuels = () => {}; duelRoom.hidden = false;', context);
    const poll = vm.runInContext('requestDuels()', context);
    await vm.runInContext("requestDuels({ accion: 'atacar' })", context);
    await vm.runInContext("requestDuels({ accion: 'cambiar' })", context);
    assert.deepEqual(duelRequests, ['poll'], 'El sondeo y una acción no se solapan');
    releasePoll();
    await poll;
    await Promise.resolve();
    assert.deepEqual(duelRequests, ['poll', 'atacar'], 'La primera acción sobrevive al sondeo');
    vm.runInContext('duelRoom.hidden = false; panelInicio.hidden = true; latestDuelLobby = { buscando: false };', context);
    const pendingPoll = vm.runInContext('requestDuels()', context);
    elements.get('teamDuels').listeners.click({ target: { closest(selector) {
        return selector === '[data-duel]' ? { dataset: { duel: 'modos' } } : null;
    } } });
    assert.equal(elements.get('teamDuels').hidden, true, 'Modos de combate funciona mientras hay un sondeo pendiente');
    assert.equal(elements.get('panelInicio').hidden, false, 'Vuelve a mostrar los modos');
    releasePoll(); await pendingPoll;
    assert.equal(elements.get('teamDuels').hidden, true, 'La respuesta atrasada no reabre la sala');
    console.log('OK: rendered template, mode selection, quick duel and confirmed wagers');
}
run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
