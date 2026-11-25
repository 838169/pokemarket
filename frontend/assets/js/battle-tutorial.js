/* Guided practice advances only when the requested action has actually happened. */
window.PokeTutorial = (() => {
    const steps = [
        {
            label: 'Conoce',
            title: 'Conoce a tu compañero',
            text: 'Este es Squirtle, tu Pokémon de Agua. Su barra verde muestra sus puntos de salud (PS). Si llegan a cero, no podrá seguir luchando.',
            next: 'Entendido, vamos a atacar',
        },
        {
            label: 'Ataca',
            title: 'Pulsa Pistola Agua',
            text: 'Charmander es de Fuego. El Agua tiene ventaja: este ataque hará el doble de daño. Pulsa el botón iluminado Pistola Agua.',
            action: 'Pistola Agua',
        },
        {
            label: 'Observa',
            title: 'Comprueba el daño',
            text: 'La barra del rival ha bajado. El rival también ha respondido: cada turno permite una acción a cada entrenador.',
            next: 'Ya lo veo, quiero cambiar',
        },
        {
            label: 'Cambia',
            title: 'Ahora elige a Pikachu',
            text: 'Pulsa la miniatura iluminada de Pikachu, debajo del escenario, en Tu equipo. Cambiar consume tu acción y permite que el rival ataque.',
            action: 'Pikachu, en Tu equipo',
        },
        {
            label: 'Gana',
            title: 'Termina con Rayo',
            text: 'Pikachu ya está en combate. Pulsa Rayo y repítelo cuando vuelva tu turno. Ganas cuando los PS del rival llegan a cero.',
            action: 'Rayo',
        },
    ];
    function allows(step, kind, value) {
        if (step === 1) return kind === 'turn' && value === 'water-gun';
        if (step === 3) return kind === 'switch' && Number(value) === 25;
        if (step === 4) return kind === 'turn' && value === 'thunderbolt';
        return false;
    }
    function acknowledge(step) {
        return step === 0 ? 1 : step === 2 ? 3 : step;
    }
    function advance(step, kind, value, before, after) {
        if (!allows(step, kind, value)) return step;
        if (
            step === 1 &&
            before.equipo_rival.pokemon[0].hp_actual > after.equipo_rival.pokemon[0].hp_actual
        )
            return 2;
        if (step === 3 && after.equipo_jugador.pokemon[after.activo_jugador].id === 25) return 4;
        if (step === 4 && after.estado === 'finalizada' && after.resultado === 'victoria') return 5;
        return step;
    }
    return { steps, allows, acknowledge, advance };
})();
