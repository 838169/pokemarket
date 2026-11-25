const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const context = vm.createContext({ window: {}, structuredClone });
vm.runInContext(fs.readFileSync('frontend/assets/js/battle-practice.js', 'utf8'), context, { filename: require('node:path').resolve('frontend/assets/js/battle-practice.js') });
const game = context.window.PokePractice;
assert.throws(() => game.turn(game.create(), 'water-gun'), /confirma/);
const rivalFirst = game.confirmInitiative(game.create(), 'rival');
assert.throws(() => game.turn(rivalFirst, 'water-gun'), /turno de tu rival/);
assert.throws(() => game.switchPokemon(rivalFirst, 25), /turno de tu rival/);
const opening = game.rivalTurn(rivalFirst);
assert.equal(opening.acciones[0].actor, 'rival');
assert.equal(opening.estado_final.turno, rivalFirst.turno);
const reply = game.turn(opening.estado_final, 'water-gun');
assert.deepEqual(Array.from(reply.acciones.filter(a => a.tipo === 'ataque'), a => a.actor), ['jugador']);
assert.throws(() => game.turn(reply.estado_final, 'water-gun'), /turno de tu rival/);
const initial = game.confirmInitiative(game.create(), 'jugador');
const copy = structuredClone(initial);
assert.throws(() => game.turn(initial, 'not-a-move'));
assert.throws(() => game.switchPokemon(initial, 7));
assert.throws(() => game.switchPokemon(initial, 999));
const water = game.turn(initial, 'water-gun');
const normal = game.turn(initial, 'tackle');
assert.ok(water.acciones[0].danio > normal.acciones[0].danio);
assert.equal(water.acciones[0].efectividad, 2);
assert.equal(
    JSON.stringify(initial),
    JSON.stringify(copy),
    'La simulación no modifica el estado original'
);
const changed = game.switchPokemon(water.estado_final, 25);
assert.equal(changed.estado_final.activo_jugador, 1);
assert.equal(changed.estado_final.turno, water.estado_final.turno + 1);
assert.ok(
    changed.estado_final.equipo_jugador.pokemon[1].hp_actual < 120,
    'Cambiar permite el contraataque'
);
let state = changed.estado_final;
let turns = 0;
while (state.estado === 'en_curso' && turns++ < 30) {
    const pokemon = state.equipo_jugador.pokemon[state.activo_jugador];
    const result = game.turn(state, pokemon.movimientos[0].nombre);
    if (result.estado_final.resultado === 'victoria') {
        assert.equal(
            result.acciones.filter((a) => a.tipo === 'ataque' && a.actor === 'rival').length,
            0,
            'El rival debilitado no contraataca'
        );
    }
    state = result.estado_final;
}
assert.equal(state.resultado, 'victoria');
assert.equal(state.equipo_rival.pokemon[0].hp_actual, 0);
assert.throws(() => game.turn(state, 'thunderbolt'));
assert.throws(() => game.switchPokemon(state, 7));
console.log(
    'OK: práctica completa, ventaja de tipo, cambio con coste, victoria y bloqueo de acciones inválidas'
);
