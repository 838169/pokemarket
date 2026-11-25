const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = vm.createContext({ window: {}, structuredClone });
for (const file of ['battle-practice', 'battle-tutorial'])
    vm.runInContext(fs.readFileSync(`frontend/assets/js/${file}.js`, 'utf8'), context, { filename: require('node:path').resolve(`frontend/assets/js/${file}.js`) });
const game = context.window.PokePractice;
const guide = context.window.PokeTutorial;
let state = game.confirmInitiative(game.create(), 'jugador');
let step = 0;
assert.equal(
    guide.allows(step, 'switch', 25),
    false,
    'No permite saltar al cambio antes de aprender a atacar'
);
assert.equal(guide.allows(step, 'turn', 'water-gun'), false);
step = guide.acknowledge(step);
assert.equal(step, 1);
assert.equal(guide.allows(step, 'turn', 'tackle'), false);
assert.equal(guide.allows(step, 'switch', 25), false);
assert.equal(
    guide.advance(step, 'turn', 'water-gun', state, state),
    1,
    'No avanza si no hubo daño'
);
const first = game.turn(state, 'water-gun').estado_final;
step = guide.advance(step, 'turn', 'water-gun', state, first);
assert.equal(step, 2);
assert.equal(first.equipo_rival.pokemon[0].hp_actual, 71);
state = first;
assert.equal(guide.allows(step, 'turn', 'water-gun'), false, 'Pausa para observar las barras');
assert.equal(guide.allows(step, 'switch', 25), false);
step = guide.acknowledge(step);
assert.equal(step, 3);
assert.equal(guide.acknowledge(step), 3, 'Leer no sustituye completar la acción');
assert.equal(guide.allows(step, 'turn', 'water-gun'), false);
assert.equal(guide.allows(step, 'switch', 25), true);
const changed = game.switchPokemon(state, 25).estado_final;
step = guide.advance(step, 'switch', 25, state, changed);
assert.equal(step, 4);
state = changed;
assert.equal(guide.allows(step, 'switch', 7), false, 'Impide alternar cambios sin atacar al rival');
assert.equal(guide.allows(step, 'turn', 'thunderbolt'), true);
let count = 0;
while (state.estado === 'en_curso' && count++ < 5) {
    const after = game.turn(state, 'thunderbolt').estado_final;
    step = guide.advance(step, 'turn', 'thunderbolt', state, after);
    state = after;
}
assert.equal(step, 5);
assert.equal(state.resultado, 'victoria');
assert.equal(state.equipo_rival.pokemon[0].hp_actual, 0);
assert.equal(count, 2);
console.log(
    'OK: cinco pasos en orden, pausas explícitas, daño visible, cambio único y victoria guiada'
);
