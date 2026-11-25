/* Local teaching battle; never writes to trainer-service. */
window.PokePractice = (() => {
    const move = (nombre, nombre_mostrado, tipo, potencia) => ({
        nombre,
        nombre_mostrado,
        tipo,
        potencia,
    });
    function pokemon(id, nombre, tipo, moves, hp = 120) {
        return {
            id,
            nombre,
            altura: { 7: 0.5, 25: 0.4, 4: 0.6 }[id] || 1,
            tipos: [tipo],
            movimientos: moves,
            hp_actual: hp,
            hp_maximo: hp,
            estadisticas: { hp, ataque: 48, defensa: 50, velocidad: id === 25 ? 90 : 55 },
            derrotado: false,
            sprite_animado_hd: true,
            sprite_animado_hd_espalda: true,
        };
    }
    function create() {
        return {
            id: 'practica',
            modo: 'practica',
            estado: 'en_curso',
            turno: 1,
            iniciativa: { listos: { jugador: false, rival: true }, orden: [] },
            resultado: null,
            activo_jugador: 0,
            activo_rival: 0,
            historial: [],
            equipo_jugador: {
                nombre: 'Equipo de práctica',
                pokemon: [
                    pokemon(7, 'Squirtle', 'water', [
                        move('water-gun', 'Pistola Agua', 'water', 40),
                        move('tackle', 'Placaje', 'normal', 35),
                    ]),
                    pokemon(25, 'Pikachu', 'electric', [
                        move('thunderbolt', 'Rayo', 'electric', 65),
                        move('quick-attack', 'Ataque Rápido', 'normal', 40),
                    ]),
                ],
            },
            equipo_rival: {
                pokemon: [
                    pokemon(
                        4,
                        'Charmander',
                        'fire',
                        [move('scratch', 'Arañazo', 'normal', 25)],
                        125
                    ),
                ],
            },
        };
    }
    function active(state, side) {
        return state[`equipo_${side}`].pokemon[state[`activo_${side}`]];
    }
    function hit(state, side, movement, actions) {
        const targetSide = side === 'jugador' ? 'rival' : 'jugador';
        const attacker = active(state, side);
        const target = active(state, targetSide);
        const effectiveness = movement.tipo === 'water' && target.tipos.includes('fire') ? 2 : 1;
        const base = Math.floor(
            (22 * movement.potencia * attacker.estadisticas.ataque) /
                target.estadisticas.defensa /
                50 +
                2
        );
        const damage = Math.floor(
            base * (attacker.tipos.includes(movement.tipo) ? 1.5 : 1) * effectiveness
        );
        const previous = target.hp_actual;
        target.hp_actual = Math.max(0, previous - damage);
        const message =
            effectiveness === 2
                ? '¡Es súper efectivo! Agua supera a Fuego.'
                : `${damage} puntos de daño.`;
        actions.push({
            tipo: 'ataque',
            actor: side,
            pokemon_id: attacker.id,
            pokemon_nombre: attacker.nombre,
            objetivo_id: target.id,
            movimiento: movement,
            danio: damage,
            hp_anterior: previous,
            hp_nuevo: target.hp_actual,
            hp_maximo: target.hp_maximo,
            efectividad: effectiveness,
            mensaje: message,
        });
        state.historial.push(`${attacker.nombre} usó ${movement.nombre_mostrado}. ${message}`);
        if (target.hp_actual === 0) {
            target.derrotado = true;
            actions.push({
                tipo: 'debilitamiento',
                lado: targetSide,
                pokemon_id: target.id,
                mensaje: `${target.nombre} quedó debilitado.`,
            });
            const next = state[`equipo_${targetSide}`].pokemon.findIndex((p) => p.hp_actual > 0);
            if (next === -1) {
                state.estado = 'finalizada';
                state.resultado = targetSide === 'rival' ? 'victoria' : 'derrota';
            } else {
                state[`activo_${targetSide}`] = next;
                const incoming = active(state, targetSide);
                actions.push({
                    tipo: 'entrada',
                    lado: targetSide,
                    pokemon_id: incoming.id,
                    pokemon_nombre: incoming.nombre,
                });
            }
        }
    }
    function confirmInitiative(original, first = Math.random() < .5 ? 'jugador' : 'rival') {
        const state = structuredClone(original);
        if (state.iniciativa?.orden.length) return state;
        state.iniciativa = { listos: { jugador: true, rival: true }, orden: first === 'jugador' ? ['jugador', 'rival'] : ['rival', 'jugador'] };
        return state;
    }
    function requireInitiative(state) {
        if (state.iniciativa && !state.iniciativa.orden.length) throw new Error('Primero confirma el orden de turnos.');
    }
    function rivalPending(state) {
        return state?.estado === 'en_curso' &&
            state.iniciativa?.orden[0] === 'rival' && !state.rival_actuo;
    }
    function rivalTurn(original) {
        if (!rivalPending(original)) throw new Error('Ya puedes elegir tu acción.');
        const state = structuredClone(original);
        const actions = [];
        hit(state, 'rival', active(state, 'rival').movimientos[0], actions);
        state.rival_actuo = true;
        return { acciones: actions, estado_final: state };
    }
    function closeRound(state, actions) {
        if (state.estado === 'en_curso' && !state.rival_actuo) {
            hit(state, 'rival', active(state, 'rival').movimientos[0], actions);
        }
        if (state.estado === 'en_curso') {
            state.turno++;
            delete state.rival_actuo;
        }
    }
    function turn(original, name) {
        if (original.estado !== 'en_curso')
            throw new Error('La práctica ya terminó. Puedes volver a jugar.');
        requireInitiative(original);
        if (rivalPending(original)) throw new Error('Es el turno de tu rival.');
        const state = structuredClone(original);
        const movement = active(state, 'jugador').movimientos.find((m) => m.nombre === name);
        if (!movement) throw new Error('Elige uno de los ataques disponibles.');
        const actions = [];
        const rival = state.activo_rival;
        hit(state, 'jugador', movement, actions);
        if (state.activo_rival !== rival) state.rival_actuo = true;
        closeRound(state, actions);
        return { acciones: actions, estado_final: state };
    }
    function switchPokemon(original, id) {
        requireInitiative(original);
        if (rivalPending(original)) throw new Error('Es el turno de tu rival.');
        if (original.estado !== 'en_curso') throw new Error('La práctica ya terminó.');
        const state = structuredClone(original);
        const next = state.equipo_jugador.pokemon.findIndex((p) => p.id === id && p.hp_actual > 0);
        if (next < 0 || next === state.activo_jugador)
            throw new Error('Elige otro Pokémon disponible.');
        state.activo_jugador = next;
        const incoming = active(state, 'jugador');
        const actions = [
            {
                tipo: 'entrada',
                lado: 'jugador',
                pokemon_id: incoming.id,
                pokemon_nombre: incoming.nombre,
            },
        ];
        state.historial.push(`${incoming.nombre} entró al combate. Cambiar consume la acción.`);
        closeRound(state, actions);
        return { acciones: actions, estado_final: state };
    }
    return { create, turn, switchPokemon, confirmInitiative, rivalTurn };
})();
