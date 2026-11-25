const byId = (id) => document.getElementById(id);
const panelInicio = byId('panelInicio');
const panelCombate = byId('panelCombate');
const iniciarBatalla = byId('iniciarBatalla');
const confirmarEntrada = byId('confirmarEntrada');
const ladoJugador = byId('ladoJugador');
const ladoRival = byId('ladoRival');
const movimientos = byId('movimientos');
const selectorAtaques = byId('selectorAtaques');
const abrirAtaques = byId('abrirAtaques');
const caraACara = byId('battleFaceoff');
let batalla = null;
let procesando = false;
let modo = 'practica';
let equipoSeleccionado = null;
let pokemonInicial = null;
const miembrosEquipos = new Map();
let pokemonPropios = new Map();
let batallaGuardada = null;
let practiceKey = '';
window.pokeNavigationLocked = () => Boolean(batalla?.estado === 'en_curso' || procesando || duelActive || window.PokeDuos?.active);

function guardarPractica() {
    if (!practiceKey) return;
    try {
        if (batalla?.modo === 'practica' && batalla.estado === 'en_curso') {
            sessionStorage.setItem(practiceKey, JSON.stringify({ batalla, pasoPractica, guiaVisible }));
        } else sessionStorage.removeItem(practiceKey);
    } catch (_) {}
}
let guiaVisible = false;
let pasoPractica = 0;
let tutorialCompletado = false;
try {
    tutorialCompletado = localStorage.getItem('pokemarket.tutorial-completo') === 'true';
} catch (_) {}

const nombresTipos = {
    normal: 'Normal',
    fire: 'Fuego',
    water: 'Agua',
    grass: 'Planta',
    electric: 'Eléctrico',
    ice: 'Hielo',
    fighting: 'Lucha',
    poison: 'Veneno',
    ground: 'Tierra',
    flying: 'Volador',
    psychic: 'Psíquico',
    bug: 'Bicho',
    rock: 'Roca',
    ghost: 'Fantasma',
    dragon: 'Dragón',
    dark: 'Siniestro',
    steel: 'Acero',
    fairy: 'Hada',
};
const nombresModos = {
    practica: 'Práctica guiada',
    rapido: 'Duelo rápido',
    equipos: 'Combate por equipos',
    apuesta: 'Duelo de apuestas',
};
const iconosAtaque = {
    normal: '✦', fire: '♨', water: '💧', grass: '🍃', electric: 'ϟ', ice: '❄',
    fighting: '🥊', poison: '☠', ground: '⛰', flying: '🪽', psychic: '◎',
    bug: '🐞', rock: '◆', ghost: '👻', dragon: '🐉', dark: '☾', steel: '⬢', fairy: '✧',
};
const escapar = (valor) => PokeMarket.escaparHTML(valor);
const nombreMovimiento = (movimiento) =>
    movimiento.nombre_mostrado ||
    String(movimiento.nombre || '')
        .replace(/-/g, ' ')
        .replace(/^./, (letra) => letra.toUpperCase());
const esPractica = () => batalla?.modo === 'practica';
const guiaActiva = () => esPractica() && guiaVisible;
const turnoRival = () => batalla?.estado === 'en_curso' &&
    batalla.iniciativa?.orden[0] === 'rival' && !batalla.rival_actuo;
const accionPermitida = (kind, value) =>
    (!batalla?.iniciativa || batalla.iniciativa.orden.length > 0) &&
    !turnoRival() &&
    (!guiaActiva() || PokeTutorial.allows(pasoPractica, kind, value));
const esperar = (tiempo) =>
    new Promise((resolve) =>
        setTimeout(
            resolve,
            window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : tiempo
        )
    );
const pokemonActivo = (estado, lado) => estado[`equipo_${lado}`].pokemon[estado[`activo_${lado}`]];

function mostrarMensaje(texto, error = false) {
    const destino = byId(confirmarEntrada.open ? 'estadoEntrada' : 'mensajeBatalla');
    destino.textContent = texto;
    destino.classList.toggle('error', error);
}

function actualizarInicio() {
    const practica = modo === 'practica';
    byId('selectorEquipos').hidden = practica;
    byId('wagerBoard').hidden = modo !== 'apuesta';
    byId('notaEquipo').textContent =
        modo === 'rapido' || modo === 'apuesta'
            ? 'Elige el Pokémon de tu equipo que participará en este duelo.'
            : 'Elige quién comienza. Los demás podrán entrar durante el combate.';
    iniciarBatalla.textContent = procesando
        ? 'Preparando el combate…'
        : practica
          ? 'Entrar a la práctica'
          : modo === 'rapido'
            ? 'Comenzar duelo rápido'
            : 'Entrar con mi equipo';
    if (modo === 'apuesta' && !procesando) iniciarBatalla.textContent = 'Revisar apuesta';
    iniciarBatalla.disabled =
        (modo === 'apuesta' && !PokeWager.choice()) ||
        procesando ||
        (!practica && (!equipoSeleccionado || !pokemonInicial || Boolean(batallaGuardada)));
    iniciarBatalla.setAttribute('aria-busy', String(procesando));
    iniciarBatalla.dataset.gameLocked = String(iniciarBatalla.disabled && !procesando);
    byId('resumenModo').textContent = practica
        ? 'Aprende sin afectar tu colección.'
        : batallaGuardada
          ? 'Continúa o termina tu batalla guardada antes de iniciar otra.'
          : equipoSeleccionado
            ? 'Tu rival se prepara al comenzar. Puede tardar unos segundos.'
            : 'Elige un equipo con al menos un Pokémon.';
    if (!practica && !batallaGuardada && equipoSeleccionado && !pokemonInicial) {
        byId('resumenModo').textContent = 'Elige el Pokémon que abrirá el combate.';
    } else if (modo === 'apuesta' && equipoSeleccionado && pokemonInicial && !batallaGuardada && !PokeWager.choice()) {
        byId('resumenModo').textContent = 'Selecciona una apuesta para continuar.';
    }
    byId('batallaActiva').hidden = !batallaGuardada;
    document.querySelectorAll('[data-modo]').forEach((button) => {
        const selected = button.dataset.modo === modo;
        button.classList.toggle('seleccionado', selected);
        button.setAttribute('aria-pressed', String(selected));
        button.disabled = procesando;
    });
}

function seleccionarModo(value) {
    if (procesando) return;
    modo = value;
    renderizarEleccionInicial();
    actualizarInicio();
    if (modo === 'apuesta')
        PokeEconomy.ready
            .then(() => PokeWager.load())
            .catch((e) => mostrarMensaje(e.message, true));
}
function abrirConfirmacion(value) {
    if (window.PokeDuos?.active) { PokeDuos.open(); return; }
    if (procesando || confirmarEntrada.open) return;
    seleccionarModo(value);
    confirmarEntrada.dataset.mode = value;
    byId('modoEntrada').textContent = nombresModos[value];
    byId('estadoEntrada').textContent = '';
    confirmarEntrada.showModal();
    confirmarEntrada.querySelector('.battle-entry-body').scrollTop = 0;
}

function actualizarVida(container, pokemon) {
    const hp = Math.max(0, Number(pokemon.hp_actual));
    const max = Math.max(1, Number(pokemon.hp_maximo));
    const percent = Math.max(0, Math.min(100, (hp / max) * 100));
    const fill = container.querySelector('[data-hp-fill]');
    const bar = container.querySelector('[role="progressbar"]');
    fill.style.width = `${percent}%`;
    fill.style.background = percent <= 20 ? '#c76758' : percent <= 50 ? '#c79d35' : '#418a5e';
    bar.setAttribute('aria-valuenow', String(hp));
    bar.setAttribute('aria-valuemax', String(max));
    container.querySelector('[data-hp-texto]').textContent = `${hp} / ${max} PS`;
}

function renderizarLado(container, estado, lado) {
    const pokemon = pokemonActivo(estado, lado);
    if (container.dataset.pokemonId !== String(pokemon.id)) {
        container.dataset.pokemonId = String(pokemon.id);
        container.classList.remove('debilitado', 'entrando');
        container.innerHTML = `<div class="pokemon-campo" data-pokemon-id="${Number(pokemon.id)}">
            ${lado === 'jugador' ? '<div class="plataforma-propia" aria-hidden="true"></div>' : ''}
            <img data-sprite decoding="async" alt="${escapar(pokemon.nombre)}">
            <div class="pokemon-datos"><div class="battle-vitals"><h2>${escapar(pokemon.nombre)}</h2><span class="hp-texto" data-hp-texto></span></div>
            <div class="barra-hp" role="progressbar" aria-label="Salud de ${escapar(pokemon.nombre)}" aria-valuemin="0"><span data-hp-fill></span></div>
            <p class="battle-passive" data-passive></p></div></div>`;
        PokeMarket.configurarImagen(container.querySelector('[data-sprite]'), {
            ...pokemon,
            vista_espalda: lado === 'jugador',
        });
    }
    container
        .querySelector('[data-sprite]')
        .style.setProperty('--pokemon-size-scale', window.PokePokemonScale.factor(pokemon.altura));
    actualizarVida(container, pokemon);
    const passive = container.querySelector('[data-passive]');
    if (passive) {
        const ability = pokemon.habilidad_activa;
        passive.textContent = `${ability?.implementada ? ability.nombre : ''}${pokemon.estado_primario === 'paralisis' ? ' · Paralizado · Velocidad ×0,5' : ''}${pokemon.cambios?.ataque ? ` · Ataque ${pokemon.cambios.ataque}` : ''}`;
        passive.title = ability?.descripcion || '';
        passive.hidden = !passive.textContent;
    }
}

function renderizarMiniaturas(container, team, active, side) {
    container.replaceChildren();
    team.forEach((pokemon, index) => {
        const button = document.createElement('button');
        const defeated = pokemon.derrotado || Number(pokemon.hp_actual) <= 0;
        button.type = 'button';
        button.className = 'pokemon-reserva';
        button.dataset.reservaId = pokemon.id;
        button.classList.toggle('activo', index === active);
        button.classList.toggle('derrotado', defeated);
        button.disabled =
            side !== 'jugador' ||
            index === active ||
            defeated ||
            procesando ||
            batalla.estado !== 'en_curso' ||
            !accionPermitida('switch', Number(pokemon.id));
        button.setAttribute(
            'aria-label',
            `${pokemon.nombre}: ${defeated ? 'debilitado' : `${pokemon.hp_actual} PS${index === active ? ', en combate' : ', cambiar a este Pokémon'}`}`
        );
        button.title = button.getAttribute('aria-label');
        const img = document.createElement('img');
        img.alt = '';
        button.appendChild(img);
        const state = document.createElement('span');
        state.className = 'estado-reserva';
        state.textContent = defeated ? '×' : index === active ? '●' : '';
        button.appendChild(state);
        button.addEventListener('click', () => cambiarPokemon(Number(pokemon.id)));
        container.appendChild(button);
        PokeMarket.configurarImagen(img, pokemon);
        img.alt = '';
    });
}

function renderizarMovimientos() {
    movimientos.replaceChildren();
    if (!batalla || batalla.estado !== 'en_curso') return;
    const pokemon = pokemonActivo(batalla, 'jugador');
    if (!(pokemon.movimientos || pokemon.movimientos_disponibles || []).length) {
        const notice = document.createElement('p');
        notice.textContent =
            'Este compañero todavía no conoce ataques de daño. Cambia a otro Pokémon o sal del combate para preparar tu equipo.';
        movimientos.appendChild(notice);
    }
    (pokemon.movimientos || pokemon.movimientos_disponibles || [])
        .slice(0, 4)
        .forEach((move, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'movimiento';
            button.disabled = procesando || !accionPermitida('turn', move.nombre);
            button.style.setProperty('--move-color', PokeBattleEffects.color(move.tipo));
            button.dataset.movimiento = move.nombre;
            const advantage =
                esPractica() &&
                move.tipo === 'water' &&
                pokemonActivo(batalla, 'rival').tipos.includes('fire');
            button.innerHTML = `<span class="move-icon" aria-hidden="true">${iconosAtaque[move.tipo] || '✦'}</span><span class="move-label"><strong>${escapar(nombreMovimiento(move))}</strong><small>${escapar(nombresTipos[move.tipo] || move.tipo)}</small></span><span class="move-power" aria-label="Potencia ${Number(move.potencia)}"><b>${Number(move.potencia)}</b><small>Potencia</small></span>${advantage ? '<span class="move-ventaja">Ventaja ×2</span>' : ''}`;
            button.title = `${index + 1}: ${nombreMovimiento(move)}`;
            button.addEventListener('click', () => ejecutarTurno(move.nombre));
            movimientos.appendChild(button);
        });
}

function actualizarSelectorAtaques() {
    const listo = batalla?.estado === 'en_curso' && !procesando && !turnoRival();
    abrirAtaques.hidden = !listo || !movimientos.querySelector('button:not(:disabled)');
    if (abrirAtaques.hidden && selectorAtaques.open) selectorAtaques.close();
}

function mostrarAtaques() {
    if (abrirAtaques.hidden || selectorAtaques.open || document.querySelector('dialog[open]'))
        return;
    byId('ataqueContexto').textContent = guiaActiva()
        ? PokeTutorial.steps[pasoPractica].text
        : `Turno ${batalla.turno} · ${pokemonActivo(batalla, 'jugador').nombre}`;
    const rival = pokemonActivo(batalla, 'rival');
    const preview = byId('rivalAtaque');
    preview.innerHTML = `<img width="60" height="60" alt=""><div><strong>${escapar(rival.nombre)}</strong><div class="attack-rival-types">${rival.tipos.map((tipo) => `<span style="--rival-type:${PokeBattleEffects.color(tipo)}">${escapar(nombresTipos[tipo] || tipo)}</span>`).join('')}</div></div><div class="attack-rival-health"><span>${Number(rival.hp_actual)} / ${Number(rival.hp_maximo)} PS</span><progress aria-label="Salud del rival" max="${Number(rival.hp_maximo)}" value="${Number(rival.hp_actual)}"></progress></div>`;
    const portrait = preview.querySelector('img');
    PokeMarket.configurarImagen(portrait, rival);
    portrait.alt = '';
    byId('verReservas').hidden = !byId('miniaturasJugador').querySelector('button:not(:disabled)');
    selectorAtaques.showModal();
}

function continuarTurno() {
    if (turnoRival()) void realizarAccion('rival');
    else mostrarAtaques();
}

function actualizarGuia() {
    const panel = byId('guiaContextual');
    panel.hidden = !guiaVisible || batalla?.estado !== 'en_curso';
    document
        .querySelectorAll('.guia-highlight')
        .forEach((element) => element.classList.remove('guia-highlight'));
    byId('rutaTutorial').hidden = !guiaActiva();
    byId('coachCabecera').hidden = !guiaActiva();
    byId('reiniciarGuia').disabled = procesando;
    byId('rutaProgreso').textContent =
        pasoPractica >= 5 ? '5 de 5 completados' : `Paso ${pasoPractica + 1} de 5`;
    byId('pasosTutorial').replaceChildren();
    PokeTutorial.steps.forEach((step, index) => {
        const item = document.createElement('li');
        const completed = index < pasoPractica;
        item.className = completed
            ? 'paso-completo'
            : index === pasoPractica
              ? 'paso-actual'
              : 'paso-pendiente';
        if (index === pasoPractica) item.setAttribute('aria-current', 'step');
        item.innerHTML = `<span class="paso-numero" aria-hidden="true">${completed ? '✓' : index + 1}</span><span>${escapar(step.label)}</span><small>${completed ? 'Hecho' : index === pasoPractica ? 'Ahora' : 'Después'}</small>`;
        byId('pasosTutorial').appendChild(item);
    });
    if (panel.hidden) return;
    const step = esPractica()
        ? PokeTutorial.steps[Math.min(pasoPractica, 4)]
        : {
              title: 'Tu siguiente decisión',
              text: 'Elige un ataque o pulsa otro Pokémon de tu equipo para cambiar. Cambiar consume tu acción y permite que el rival ataque.',
          };
    byId('guiaPaso').textContent = esPractica()
        ? `PASO ${Math.min(pasoPractica + 1, 5)} DE 5`
        : 'Ayuda de combate';
    byId('guiaTitulo').textContent = step.title;
    byId('guiaTexto').textContent =
        esPractica() && pasoPractica === 2
            ? `¡Funcionó! Charmander ha pasado de 125 a ${batalla.equipo_rival.pokemon[0].hp_actual} PS. Squirtle también ha recibido el contraataque. Mira las dos barras antes de continuar.`
            : step.text;
    byId('guiaObjetivo').textContent = procesando
        ? 'Mira cómo se resuelve el turno…'
        : step.action
          ? `Tu acción: ${step.action}`
          : esPractica()
            ? 'Lee la indicación y pulsa el botón de abajo.'
            : 'Elige un ataque disponible para continuar.';
    byId('guiaSiguiente').hidden = !step.next;
    byId('guiaSiguiente').disabled = procesando;
    byId('guiaSiguiente').textContent = step.next || 'Continuar';
    byId('ocultarGuia').textContent = esPractica() ? 'Practicar sin guía' : 'Ocultar ayuda';
    byId('ocultarGuia').disabled = procesando;
    if (guiaActiva() && !procesando) {
        if (pasoPractica === 0)
            ladoJugador.querySelector('.pokemon-datos')?.classList.add('guia-highlight');
        if (pasoPractica === 1)
            movimientos
                .querySelector('[data-movimiento="water-gun"]')
                ?.classList.add('guia-highlight');
        if (pasoPractica === 2)
            ladoRival.querySelector('.barra-hp')?.classList.add('guia-highlight');
        if (pasoPractica === 3)
            byId('miniaturasJugador')
                .querySelector('[data-reserva-id="25"]')
                ?.classList.add('guia-highlight');
        if (pasoPractica === 4)
            movimientos
                .querySelector('[data-movimiento="thunderbolt"]')
                ?.classList.add('guia-highlight');
    }
}

function renderizarBatalla(estado) {
    batalla = estado;
    renderizarLado(ladoJugador, estado, 'jugador');
    renderizarLado(ladoRival, estado, 'rival');
    renderizarMiniaturas(
        byId('miniaturasJugador'),
        estado.equipo_jugador.pokemon,
        estado.activo_jugador,
        'jugador'
    );
    renderizarMiniaturas(
        byId('miniaturasRival'),
        estado.equipo_rival.pokemon,
        estado.activo_rival,
        'rival'
    );
    renderizarMovimientos();
    byId('numeroTurno').textContent =
        `Turno ${Number(estado.turno || 1)}${estado.clima ? ` · ${estado.clima === 'sol' ? 'Sol' : 'Lluvia'} · ${estado.clima_turnos} turnos` : ''}`;
    byId('etiquetaModo').textContent = esPractica()
        ? guiaVisible
            ? 'Práctica paso a paso'
            : 'Práctica libre'
        : nombresModos[estado.modo || 'equipos'];
    byId('turnoTexto').textContent = procesando
        ? 'Resolviendo turno'
        : estado.estado === 'en_curso'
          ? estado.iniciativa && !estado.iniciativa.orden.length ? 'Preparando el orden' : turnoRival() ? 'Turno del rival' : 'Elige tu acción'
          : 'Combate terminado';
    const puedeCambiar =
        estado.equipo_jugador.pokemon.filter((p) => Number(p.hp_actual) > 0).length > 1;
    byId('indicadorTurno').textContent = procesando
        ? 'Espera a que termine la acción.'
        : estado.estado === 'en_curso'
          ? turnoRival() ? 'El rival elige su ataque.' : puedeCambiar
              ? 'Ataca o cambia de compañero.'
              : 'Elige un ataque para continuar.'
          : '';
    document.querySelector('.equipos-miniaturas h3 span').hidden = !puedeCambiar;
    byId('abandonarBatalla').disabled = procesando;
    byId('abandonarBatalla').textContent = esPractica()
        ? 'Volver a los modos de juego'
        : 'Salir del combate';
    const finished = estado.estado === 'finalizada';
    byId('resultadoBatalla').hidden = !finished;
    byId('repetirBatalla').hidden = !finished;
    byId('volverEquipos').hidden = !finished;
    if (finished) {
        PokeBattleEffects.finish();
        syncBattleSound(PokeBattleEffects.isSoundEnabled());
        const won = estado.resultado === 'victoria';
        byId('resultadoBatalla').innerHTML =
            `<span class="battle-result-icon" aria-hidden="true">${won ? '★' : '↻'}</span><h2>${won ? '¡Victoria!' : 'Derrota'}</h2>`;
        for (const progress of estado.progreso_ganado || []) {
            const line = document.createElement('p');
            line.className = 'battle-growth-reward';
            line.textContent = `${progress.nombre} · +${progress.experiencia} EXP${progress.nivel > progress.nivel_anterior ? ` · Nv. ${progress.nivel}` : ''}${progress.nuevos_movimientos.length ? ` · ${progress.nuevos_movimientos.map((m) => m.nombre_mostrado).join(', ')}` : ''}`;
            byId('resultadoBatalla').append(line);
        }
        if (estado.modo === 'apuesta') {
            const result = document.createElement('p');
            result.textContent =
                estado.resultado === 'victoria'
                    ? `Recibes ${PokeEconomy.number(estado.recompensa_monedas)} monedas: entrada devuelta + ${PokeEconomy.number(estado.apuesta.cantidad)} de ganancia.`
                    : `Perdiste ${PokeEconomy.number(estado.apuesta.cantidad)} monedas de entrada.`;
            byId('resultadoBatalla').append(result);
        }
        if (!esPractica() && estado.modo !== 'apuesta' && estado.recompensa_monedas) {
            const reward = document.createElement('strong');
            reward.className = 'battle-reward';
            reward.textContent = `+${PokeEconomy.number(estado.recompensa_monedas)} monedas`;
            byId('resultadoBatalla').append(reward);
            const home = document.createElement('a');
            home.href = 'coleccion.php';
            home.className = 'btn-secundario battle-home-link';
            home.textContent = 'Ir al Hogar';
            byId('resultadoBatalla').append(home);
        }
        byId('mensajeAccion').textContent = '';
        byId('abandonarBatalla').hidden = true;
        if (guiaActiva() && won && pasoPractica >= 5) {
            tutorialCompletado = true;
            try {
                localStorage.setItem('pokemarket.tutorial-completo', 'true');
            } catch (_) {}
        } else if (!esPractica()) {
            batallaGuardada = null;
        }
    } else byId('abandonarBatalla').hidden = false;
    actualizarGuia();
    actualizarSelectorAtaques();
    const popup = byId('resultadoMovil');
    const mobileResult = finished && window.matchMedia('(max-width: 780px)').matches;
    byId('abrirResultado').hidden = !mobileResult;
    const destination = mobileResult ? byId('resultadoEnDialogo') : byId('resultadoEnPanel');
    const result = byId('resultadoBatalla');
    if (result.parentElement !== destination) {
        destination.append(result, byId('repetirBatalla'), byId('volverEquipos'));
    }
    if (mobileResult && !popup.open) popup.showModal();
}

function presentarRival(estado) {
    for (const [side, label] of [['jugador', 'Player'], ['rival', 'Rival']]) {
        const pokemon = pokemonActivo(estado, side);
        const portrait = byId(`faceoff${label}Image`);
        PokeMarket.configurarImagen(portrait, pokemon);
        portrait.alt = `${side === 'jugador' ? 'Tu Pokémon' : 'Rival'} · ${pokemon.nombre}`;
    }
    caraACara.classList.toggle('faceoff-motion', PokeMarket.animacionesActivas());
    let segundos = 5;
    const count = byId('faceoffCount');
    const progress = byId('faceoffProgress');
    count.textContent = String(segundos);
    progress.value = segundos;
    caraACara.showModal();
    const timer = setInterval(() => {
        progress.value = --segundos;
        if (segundos > 0) count.textContent = String(segundos);
        else {
            clearInterval(timer);
            if (caraACara.open) caraACara.close();
        }
    }, 1000);
    caraACara.addEventListener('close', () => {
        clearInterval(timer);
        if (batalla !== estado || panelCombate.hidden) return;
        if (guiaActiva()) byId('guiaSiguiente').focus({ preventScroll: true });
        continuarTurno();
    }, { once: true });
}

function prepararIniciativa(estado) {
    if (!estado.iniciativa || estado.estado !== 'en_curso') return;
    return window.PokeInitiative?.show({
        key: estado.id, state: estado.iniciativa, own: 'jugador', system: true,
        players: {
            jugador: { name: 'Tu equipo', roster: estado.equipo_jugador.pokemon.map(p => p.nombre).join(' · ') },
            rival: { name: 'Entrenadora Alba', roster: estado.equipo_rival.pokemon.map(p => p.nombre).join(' · ') },
        },
        ready: async () => {
            if (estado.modo === 'practica') estado = PokePractice.confirmInitiative(estado);
            else estado = (await PokeMarket.trainer(`/battles/${estado.id}/initiative`, { method: 'POST', body: {} })).batalla;
            renderizarBatalla(estado); guardarPractica(); prepararIniciativa(estado);
        },
        enter: () => presentarRival(estado),
        exit: async () => { await abandonar(); return panelCombate.hidden; },
    });
}

function abrirCampo(estado, presentar = true, mostrarGuia = estado.modo === 'practica' || !tutorialCompletado) {
    byId('errorAtaque').textContent = '';
    byId('reintentarTurnoRival').hidden = true;
    const escenarios = {
        practica: ['pradera', 'bosque'],
        rapido: ['bosque', 'laguna'],
        equipos: ['pradera', 'laguna'],
        apuesta: ['ruinas', 'bosque'],
    };
    const opciones = escenarios[estado.modo] || escenarios.equipos;
    // El ID aleatorio mantiene el mismo escenario al retomar una batalla guardada.
    const indice =
        estado.modo === 'practica'
            ? Math.floor(Math.random() * opciones.length)
            : (Number.parseInt(String(estado.id).slice(-2), 16) || 0) % opciones.length;
    const escenario = opciones[indice];
    document.querySelector('.combate-campo').dataset.escenario = escenario;
    if (estado.estado === 'en_curso') PokeBattleEffects.setScene(escenario);
    byId('equiposArena').hidden = true;
    byId('salirPractica').hidden = estado.modo !== 'practica';
    ladoJugador.dataset.pokemonId = '';
    ladoRival.dataset.pokemonId = '';
    panelInicio.hidden = true;
    panelCombate.hidden = false;
    byId('tituloArena').textContent =
        estado.modo === 'practica' ? 'Tu primera batalla.' : 'Combate Pokémon';
    byId('subtituloArena').textContent =
        estado.modo === 'practica' ? 'Práctica sin recompensas ni gasto de energía.' : '';
    byId('efectividadTexto').textContent = '';
    byId('ultimoMovimiento').textContent = '';
    byId('mensajeAccion').textContent = `¿Qué hará ${pokemonActivo(estado, 'jugador').nombre}?`;
    guiaVisible = mostrarGuia;
    renderizarBatalla(estado);
    guardarPractica();
    byId('tituloArena').scrollIntoView({ block: 'start', behavior: 'smooth' });
    (guiaActiva() ? byId('guiaSiguiente') : abrirAtaques)?.focus({
        preventScroll: true,
    });
    if (prepararIniciativa(estado)) return;
    if (presentar && estado.estado === 'en_curso') presentarRival(estado);
    else queueMicrotask(() => {
        if (batalla === estado && !panelCombate.hidden) continuarTurno();
    });
}

function volverInicio() {
    byId('reintentarTurnoRival').hidden = true;
    if (byId('resultadoMovil').open) byId('resultadoMovil').close();
    byId('abrirResultado').hidden = true;
    if (selectorAtaques.open) selectorAtaques.close();
    abrirAtaques.hidden = true;
    batalla = null;
    guardarPractica();
    panelCombate.hidden = true;
    panelInicio.hidden = false;
    byId('equiposArena').hidden = false;
    byId('salirPractica').hidden = true;
    byId('tituloArena').textContent = 'Tu aventura como entrenador.';
    byId('subtituloArena').textContent = 'Aprende paso a paso o entra al combate con tu equipo.';
    PokeBattleEffects.finish();
    syncBattleSound(PokeBattleEffects.isSoundEnabled());
    actualizarInicio();
    document.querySelector('[data-modo][aria-pressed="true"]')?.focus({ preventScroll: true });
}

async function iniciarPractica() {
    if (procesando) return;
    procesando = true;
    try {
        await PokeMarket.pokemon('/health');
    } catch (error) {
        mostrarMensaje(error.message, true);
        return;
    } finally {
        procesando = false;
    }
    pasoPractica = 0;
    abrirCampo(PokePractice.create());
}

async function reproducirAcciones(actions, final) {
    const visual = structuredClone(batalla);
    for (const action of actions || []) {
        if (action.tipo === 'ataque') {
            const defenderSide = action.actor === 'jugador' ? 'rival' : 'jugador';
            const attacker = action.actor === 'jugador' ? ladoJugador : ladoRival;
            const defender = defenderSide === 'jugador' ? ladoJugador : ladoRival;
            byId('turnoTexto').textContent =
                action.actor === 'jugador' ? 'Tu Pokémon ataca' : 'El rival ataca';
            byId('mensajeAccion').textContent =
                `${action.pokemon_nombre} usó ${nombreMovimiento(action.movimiento)}.`;
            byId('ultimoMovimiento').textContent =
                `Último ataque: ${nombreMovimiento(action.movimiento)}`;
            await PokeBattleEffects.attack(attacker, defender, action.movimiento.tipo);
            const target = visual[`equipo_${defenderSide}`].pokemon.find(
                (p) => Number(p.id) === Number(action.objetivo_id)
            );
            if (target) {
                target.hp_actual = action.hp_nuevo;
                actualizarVida(defender, target);
            }
            if (action.danio > 0) {
                const damage = document.createElement('span');
                damage.className = 'dano-flotante';
                damage.textContent = `−${action.danio}`;
                defender.appendChild(damage);
                damage.addEventListener('animationend', () => damage.remove(), { once: true });
            }
            byId('efectividadTexto').textContent =
                action.mensaje && Number(action.efectividad) !== 1 ? action.mensaje : '';
            byId('mensajeAccion').textContent = action.mensaje || `${action.danio} puntos de daño.`;
            await esperar(260);
        } else if (action.tipo === 'habilidad') {
            byId('mensajeAccion').textContent = action.mensaje;
            byId('turnoTexto').textContent = 'Habilidad y estado';
            if (action.pokemon_id && Number.isFinite(action.hp_nuevo)) {
                const target = visual[`equipo_${action.lado}`].pokemon.find(
                    (p) => Number(p.id) === Number(action.pokemon_id)
                );
                if (target) {
                    target.hp_actual = action.hp_nuevo;
                    actualizarVida(action.lado === 'jugador' ? ladoJugador : ladoRival, target);
                }
            }
            await esperar(360);
        } else if (action.tipo === 'debilitamiento') {
            const side = action.lado === 'jugador' ? ladoJugador : ladoRival;
            side.classList.add('debilitado');
            byId('mensajeAccion').textContent = action.mensaje;
            await esperar(320);
        } else if (action.tipo === 'entrada') {
            const sideName = action.lado;
            const team = visual[`equipo_${sideName}`].pokemon;
            const next = team.findIndex((p) => Number(p.id) === Number(action.pokemon_id));
            if (next >= 0) visual[`activo_${sideName}`] = next;
            const side = sideName === 'jugador' ? ladoJugador : ladoRival;
            renderizarLado(side, visual, sideName);
            side.classList.add('entrando');
            byId('mensajeAccion').textContent = `${action.pokemon_nombre} entra al combate.`;
            await esperar(380);
            side.classList.remove('entrando');
        } else if (action.tipo === 'salida') {
            byId('mensajeAccion').textContent = action.mensaje;
            await esperar(220);
        }
    }
    batalla = final;
}

async function realizarAccion(kind, value) {
    if (!batalla || procesando || caraACara.open || batalla.estado !== 'en_curso') return;
    const rival = kind === 'rival';
    if (rival ? !turnoRival() : !accionPermitida(kind, value)) {
        actualizarGuia();
        return;
    }
    const estadoAnterior = batalla;
    byId('errorAtaque').textContent = '';
    byId('reintentarTurnoRival').hidden = true;
    procesando = true;
    renderizarBatalla(batalla);
    byId('mensajeAccion').textContent =
        rival ? 'El rival prepara su ataque…' : kind === 'switch' ? 'Preparando el cambio…' : 'Preparando el ataque…';
    let estadoConfirmado = null;
    try {
        const response = esPractica()
            ? rival
                ? PokePractice.rivalTurn(batalla)
                : kind === 'switch'
                ? PokePractice.switchPokemon(batalla, value)
                : PokePractice.turn(batalla, value)
            : await PokeMarket.trainer(`/battles/${batalla.id}/${rival ? 'turn' : kind}`, {
                  method: 'POST',
                  body: rival ? { rival: true, ronda: batalla.turno } : kind === 'switch' ? { pokemon_id: value } : { movimiento: value },
              });
        estadoConfirmado = response.estado_final;
        if (kind === 'switch') window.PokePokemonCry?.play(value);
        await reproducirAcciones(response.acciones, response.estado_final);
        if (!rival && guiaActiva())
            pasoPractica = PokeTutorial.advance(
                pasoPractica,
                kind,
                value,
                estadoAnterior,
                response.estado_final
            );
    } catch (error) {
        byId('mensajeAccion').textContent = error.message;
        byId('errorAtaque').textContent = error.message;
        if (rival && !estadoConfirmado) byId('reintentarTurnoRival').hidden = false;
    } finally {
        procesando = false;
        if (estadoConfirmado) batalla = estadoConfirmado;
        if (batalla) renderizarBatalla(batalla);
        guardarPractica();
        if (batalla?.estado === 'finalizada' && !esPractica())
            PokeEconomy.refresh().catch(() => {});
        if (
            abrirAtaques.hidden &&
            guiaActiva() &&
            batalla?.estado === 'en_curso' &&
            window.matchMedia('(max-width: 780px)').matches
        ) {
            byId('guiaContextual').scrollIntoView({ block: 'start', behavior: 'instant' });
        }
        if (estadoConfirmado) continuarTurno();
        else mostrarAtaques();
    }
}
const ejecutarTurno = (move) => realizarAccion('turn', move);
const cambiarPokemon = (id) => realizarAccion('switch', id);

function seleccionarEquipo(id) {
    if (procesando) return;
    equipoSeleccionado = Number(id);
    pokemonInicial = null;
    renderizarEleccionInicial();
    document.querySelectorAll('.tarjeta-equipo').forEach((button) => {
        const selected = Number(button.dataset.id) === equipoSeleccionado;
        button.classList.toggle('seleccionada', selected);
        button.setAttribute('aria-pressed', String(selected));
    });
    actualizarInicio();
}

function renderizarEleccionInicial() {
    const members = miembrosEquipos.get(equipoSeleccionado) || [];
    byId('selectorPokemonInicial').hidden = modo === 'practica' || !equipoSeleccionado;
    byId('notaPokemonInicial').textContent = members.length
        ? 'Elige tu compañero inicial.'
        : 'Cargando los compañeros del equipo…';
    const container = byId('opcionesPokemonInicial');
    container.replaceChildren();
    members.forEach((member) => {
        const id = Number(member.pokemon_id);
        const pokemon = pokemonPropios.get(id) || { id, nombre: `Pokémon #${id}` };
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'battle-lead';
        button.setAttribute('aria-pressed', String(pokemonInicial === id));
        button.innerHTML = `<img alt="" width="72" height="72"><strong>${escapar(pokemon.nombre)}</strong><small>${pokemonInicial === id ? 'Elegido para comenzar' : 'Elegir compañero'}</small>`;
        PokeMarket.configurarImagen(button.querySelector('img'), pokemon);
        button.addEventListener('pointerenter', () => window.PokePokemonCry?.preload(id));
        button.addEventListener('focus', () => window.PokePokemonCry?.preload(id));
        button.onclick = () => {
            if (procesando) return;
            window.PokePokemonCry?.play(id);
            pokemonInicial = id;
            renderizarEleccionInicial();
            actualizarInicio();
        };
        container.appendChild(button);
    });
}

function renderizarEquipos(teams) {
    const container = byId('tarjetasEquipos');
    container.replaceChildren();
    byId('sinEquipos').hidden = teams.some((team) => Number(team.total_miembros) > 0);
    teams.forEach((team) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'tarjeta-equipo';
        button.dataset.id = team.id;
        button.disabled = Number(team.total_miembros) === 0;
        button.setAttribute('aria-pressed', 'false');
        button.innerHTML = `<strong>${escapar(team.nombre)}</strong><span>${Number(team.total_miembros)} Pokémon</span><div class="miniaturas-equipo"></div>`;
        button.addEventListener('click', () => seleccionarEquipo(team.id));
        container.appendChild(button);
        PokeMarket.trainer(`/teams/${team.id}`)
            .then((detail) => {
                miembrosEquipos.set(Number(team.id), detail.equipo.miembros);
                if (Number(team.id) === equipoSeleccionado) {
                    renderizarEleccionInicial();
                    actualizarInicio();
                }
                detail.equipo.miembros.forEach((member) => {
                    const img = document.createElement('img');
                    button.querySelector('.miniaturas-equipo').appendChild(img);
                    PokeMarket.configurarImagen(img, {
                        id: Number(member.pokemon_id),
                        nombre: `Pokémon #${member.pokemon_id}`,
                    });
                });
            })
            .catch(() => {});
    });
}

async function cargarEquipos() {
    try {
        await PokeEconomy.ready;
        const user = await PokeMarket.iniciarSesion();
        practiceKey = `pokemarket.practice.${user.id}`;
        const [teams, active, home, online] = await Promise.all([
            PokeMarket.trainer('/teams'),
            PokeMarket.trainer('/battles/current'),
            PokeMarket.trainer('/home'),
            PokeMarket.trainer('/duels').catch(() => ({ duelos: [] })),
        ]);
        pokemonPropios = new Map(home.pokemon.map((p) => [Number(p.id), p]));
        renderizarEquipos(teams.equipos);
        batallaGuardada = active.batalla;
        actualizarInicio();
        if (batallaGuardada) {
            abrirCampo(batallaGuardada, false);
            return;
        }
        if (online.duelos.some((duel) => duel.estado === 'activa')) {
            void abrirDuelos(online.duelos.some((duel) => duel.estado === 'activa' && duel.entre_amigos));
            return;
        }
        if (new URLSearchParams(location.search).get('sala') === 'amigos') {
            void abrirDuelos(true);
            return;
        }
        let practice;
        try {
            practice = JSON.parse(sessionStorage.getItem(practiceKey));
        } catch (_) {}
        if (practice?.batalla?.modo === 'practica' && practice.batalla.estado === 'en_curso') {
            await PokeMarket.pokemon('/health');
            pasoPractica = practice.pasoPractica;
            abrirCampo(practice.batalla, false, practice.guiaVisible);
        }
    } catch (error) {
        mostrarMensaje(error.message, true);
    }
}

async function comenzarBatalla() {
    if (procesando) return;
    if (modo === 'practica') {
        confirmarEntrada.close();
        return iniciarPractica();
    }
    if (!equipoSeleccionado || batallaGuardada || !pokemonInicial) return;
    const offer = modo === 'apuesta' ? PokeWager.choice() : null;
    const startingMode = modo;
    if (modo === 'apuesta') {
        if (!offer) return;
        procesando = true;
        actualizarInicio();
        const accepted = await PokeWager.confirm(offer);
        procesando = false;
        actualizarInicio();
        if (!accepted) return;
    }
    procesando = true;
    actualizarInicio();
    mostrarMensaje('Preparando a los Pokémon y buscando rival…');
    try {
        const response = await PokeMarket.trainer('/battles', {
            method: 'POST',
            body: {
                equipo_id: equipoSeleccionado,
                ...(pokemonInicial ? { pokemon_id: pokemonInicial } : {}),
                modo: startingMode,
                ...(offer ? { oferta_id: offer.id } : {}),
            },
        });
        batallaGuardada = response.batalla;
        PokeEconomy.refresh().catch(() => {});
        procesando = false;
        confirmarEntrada.close();
        abrirCampo(response.batalla);
        mostrarMensaje('');
    } catch (error) {
        mostrarMensaje(error.message, true);
    } finally {
        procesando = false;
        actualizarInicio();
    }
}

async function continuar() {
    if (!batallaGuardada || procesando) return;
    procesando = true;
    byId('continuarBatalla').disabled = true;
    byId('continuarBatalla').textContent = 'Cargando combate…';
    try {
        const response = await PokeMarket.trainer(`/battles/${batallaGuardada.id}`);
        procesando = false;
        abrirCampo(response.batalla, false);
    } catch (error) {
        mostrarMensaje(error.message, true);
    } finally {
        procesando = false;
        byId('continuarBatalla').disabled = false;
        byId('continuarBatalla').textContent = 'Continuar batalla';
    }
}

function confirmarSalida(estado) {
    const dialog = byId('confirmarSalida');
    const practice = estado.modo === 'practica';
    byId('salidaTitulo').textContent = practice ? '¿Salir de la práctica?' : '¿Abandonar el combate?';
    byId('salidaAceptar').textContent = practice ? 'Salir de práctica' : 'Abandonar';
    byId('salidaConsecuencia').textContent = practice
        ? 'La próxima práctica empezará desde el inicio.'
        : estado.modo === 'apuesta'
          ? `Perderás las ${PokeEconomy.number(estado.apuesta.cantidad)} monedas de entrada.`
          : 'El combate terminará sin recompensas.';
    dialog.returnValue = '';
    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'leave'), { once: true });
        dialog.showModal();
    });
}

async function abandonar() {
    if (!batalla || procesando) return;
    if (!(await confirmarSalida(batalla))) return;
    if (esPractica()) return volverInicio();
    procesando = true;
    byId('abandonarBatalla').disabled = true;
    try {
        await PokeMarket.trainer(`/battles/${batalla.id}`, { method: 'DELETE' });
        batallaGuardada = null;
        procesando = false;
        volverInicio();
    } catch (error) {
        byId('mensajeAccion').textContent = error.message;
    } finally {
        procesando = false;
        byId('abandonarBatalla').disabled = false;
    }
}

const duelRoom = byId('teamDuels');
const duelMobilePanel = byId('duelMobilePanel');
const duelWagerPanel = byId('duelWagerPanel');
duelWagerPanel.addEventListener('cancel', (event) => event.preventDefault());
byId('duelWagerForm').addEventListener('submit', (event) => {
    event.preventDefault();
    const raw = byId('duelWagerAmount').value.trim();
    const monto = Number(raw);
    if (!/^\d+$/.test(raw) || monto < 1 || monto > 1000000) {
        byId('duelWagerError').textContent = 'Elige un monto entre 1 y 1.000.000.';
        return;
    }
    void requestDuels({ accion: 'proponer_apuesta', id: duelWagerPanel.dataset.id, monto });
});
byId('duelChallengePanel').addEventListener('cancel', (event) => event.preventDefault());
let mobileDuelList, mobileDuelOrigin;
duelMobilePanel.addEventListener('close', () => {
    mobileDuelOrigin?.append(mobileDuelList);
    duelRoom.insertBefore(byId('duelStatus'), duelMobilePanel);
    mobileDuelList = mobileDuelOrigin = null;
});
let duelPoll, duelBusy = false, duelSending = false, duelQueued = null, duelSnapshot = '';
let friendDuelMode = false;
let duelActive = false, duelAction = 0, duelIntro = 0, duelSearchStarted = null;
const dismissedDuels = new Set();

function duelSprite(pokemon) {
    const fallback = PokeMarket.imagenPokemon(pokemon.id);
    const source = pokemon.sprite_animado_hd ? PokeMarket.imagenPokemon(pokemon.id, 'animated') : fallback;
    return `<img src="${escapar(source)}" data-fallback="${fallback}" alt="" draggable="false">`;
}

function drawDuelArena(current) {
    const match = byId('duelMatch'), p = current.partida;
    const own = p.equipo[p.activo], rival = p.rival[p.activo_rival];
    const entering = match.hidden || match.dataset.id !== current.id;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches || !PokeMarket.animacionesActivas();
    const intro = entering && !p.resultado && !p.accion && !reduced;
    const action = !entering && p.accion?.numero > duelAction ? p.accion : null;
    const fighter = (pokemon, side) => `<article class="duel-fighter duel-${side}">
        <div class="duel-vitals"><strong>${escapar(pokemon.nombre)}</strong><span>${Number(pokemon.hp_actual)} / ${Number(pokemon.hp_maximo)} PS</span>
        <progress aria-label="Salud de ${escapar(pokemon.nombre)}" max="${Number(pokemon.hp_maximo)}" value="${Number(pokemon.hp_actual)}"></progress>
        <small>${(pokemon.tipos || []).map((type) => escapar(nombresTipos[type] || type)).join(' · ')}</small></div>
        <div class="duel-sprite"><div data-sprite>${duelSprite(pokemon)}</div></div></article>`;
    const roster = (team, active, selectable) => team.map((pokemon, index) => {
        const label = escapar(pokemon.nombre);
        const status = index === active ? 'actual' : (pokemon.hp_actual <= 0 ? 'fainted' : '');
        const content = `${duelSprite(pokemon)}<span>${label}</span>`;
        return selectable && !status
            ? `<button type="button" class="duel-member" data-duel="cambiar" data-value="${Number(pokemon.id)}" aria-label="Cambiar a ${label}">${content}</button>`
            : `<div class="duel-member ${status}">${content}</div>`;
    }).join('');
    const ownCanPlay = p.turno && !p.resultado;
    const status = p.resultado || (p.turno ? 'Tu turno' : 'Turno del rival');
    const wagerResult = p.resultado === 'Derrota' ? 'Apuesta perdida'
        : p.resultado === 'Victoria' ? (p.rendicion ? 'Aporte devuelto' : 'Premio') : 'Bote';
    const wagerAmount = p.resultado === 'Derrota' || p.rendicion ? p.bote / 2 : p.bote;
    const kind = current.entre_amigos ? 'Duelo con amigos' : p.equipo.length === 1 && p.rival.length === 1 ? 'Duelo individual' : 'Combate por equipos';
    const effect = action ? ` data-action="${action.tipo === 'atacar' ? 'attack' : 'switch'}" data-side="${action.propia ? 'own' : 'rival'}"` : '';
    match.dataset.id = current.id;
    duelAction = p.accion?.numero || 0;
    match.hidden = false;
    match.innerHTML = `<header class="duel-score">
        <div><small>${kind}</small><h2 tabindex="-1" id="duelArenaTitle">Tu equipo <span>vs.</span> @${escapar(current.rival)}</h2></div>
        <span>Ronda ${Number(p.ronda) || 1} · Rival Nv. ${Number(current.nivel_rival)}</span></header>
        <div class="duel-stage combate-campo${current.entre_amigos ? ' duel-friend-stage' : ''}"${effect}>
        ${fighter(own, 'own')}${fighter(rival, 'rival')}
        ${action?.tipo === 'atacar' ? `<span class="duel-impact" aria-hidden="true">${action.danio > 0 ? '−' + Number(action.danio) : '0'}</span>` : ''}
        ${intro ? `<div class="duel-faceoff" aria-hidden="true">
            <div>${duelSprite(own)}<strong>${escapar(own.nombre)}</strong></div><b>VS</b>
            <div>${duelSprite(rival)}<strong>@${escapar(current.rival)}</strong></div>
            <span class="duel-countdown"></span></div>` : ''}</div>
        <div class="duel-console">
            <div class="duel-turn" aria-live="polite"><strong>${status}</strong><span>${escapar(p.mensaje)}</span>
            ${p.bote ? `<span>${wagerResult} · ${wagerAmount} monedas</span>` : ''}</div>
            ${p.resultado ? '<button class="btn-batalla" data-duel="volver">Volver a la sala</button>' : `<div class="duel-moves">${ownCanPlay ? own.movimientos.map((m) =>
                `<button class="duel-move" data-type="${escapar(m.tipo)}" data-duel="atacar" data-value="${escapar(m.nombre)}"><span aria-hidden="true">${iconosAtaque[m.tipo] || '✦'}</span><strong>${escapar(nombreMovimiento(m))}</strong><small>${escapar(nombresTipos[m.tipo] || m.tipo)} · ${Number(m.potencia)}</small></button>`).join('') : ''}</div>`}
            <div class="duel-rosters"><section><h3>Tu equipo <small>${p.equipo.length}</small></h3><div>${roster(p.equipo, p.activo, ownCanPlay)}</div></section>
            <section><h3>Equipo rival <small>${p.rival.length}</small></h3><div>${roster(p.rival, p.activo_rival, false)}</div></section></div>
            ${p.resultado ? '' : '<button class="duel-forfeit" data-duel="rendirse">Rendirse</button>'}
        </div>`;
    match.querySelectorAll('img[data-fallback]').forEach((img) => {
        const fallback = () => { if (img.src !== img.dataset.fallback) img.src = img.dataset.fallback; };
        img.addEventListener('error', fallback, { once: true });
        if (img.complete && !img.naturalWidth) fallback();
    });
    if (entering) {
        match.querySelector('#duelArenaTitle').focus();
        duelIntro = intro ? performance.now() + 5000 : 0;
        if (!p.resultado) PokeBattleEffects.setScene('pradera');
    }
    if (action?.tipo === 'atacar') {
        const ownFighter = match.querySelector('.duel-own'), rivalFighter = match.querySelector('.duel-rival');
        const fighters = action.propia ? [ownFighter, rivalFighter] : [rivalFighter, ownFighter];
        duelIntro = reduced ? 0 : performance.now() + 680;
        const animation = reduced ? Promise.resolve() : PokeBattleEffects.attack(...fighters, action.elemento);
        animation.finally(() => {
            if (p.resultado && match.dataset.id === current.id && !duelActive) PokeBattleEffects.finish();
        });
    } else if (p.resultado) {
        PokeBattleEffects.finish();
    }
}

function abrirPanelDuelo(listId) {
    if (duelMobilePanel.open || mobileDuelList) return;
    mobileDuelList = byId(listId);
    mobileDuelOrigin = mobileDuelList.parentElement;
    byId('duelMobileTitle').textContent = mobileDuelOrigin.querySelector('h3').textContent;
    byId('duelMobileContent').append(mobileDuelList, byId('duelStatus'));
    duelMobilePanel.showModal();
}
function actualizarRelojDuelo() {
    const clock = byId('duelSearchClock');
    clock.hidden = duelSearchStarted === null;
    if (clock.hidden) return;
    const seconds = Math.min(120, Math.max(0, Math.floor((Date.now() - duelSearchStarted) / 1000)));
    clock.textContent = `Buscando · ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
let shownDuelMatch = '';
let latestDuelLobby = null;
function drawDuels(data) {
    latestDuelLobby = data;
    const current = data.duelos.find((d) => d.estado === 'activa') ||
        data.duelos.find((d) => d.estado === 'finalizada' && d.id === byId('duelMatch').dataset.id && !dismissedDuels.has(d.id));
    const lobby = byId('duelLobby'), match = byId('duelMatch');
    lobby.hidden = Boolean(current);
    duelActive = current?.estado === 'activa';
    byId('duelStop').hidden = friendDuelMode || !data.buscando;
    const wager = current?.partida?.apuesta;
    if (!wager && duelWagerPanel.open) duelWagerPanel.close();
    if (current) {
        if (duelMobilePanel.open) duelMobilePanel.close();
        if (byId('duelChallengePanel').open) byId('duelChallengePanel').close();
        const key = JSON.stringify(current);
        if (wager) {
            match.hidden = true;
            match.dataset.id = current.id;
            duelWagerPanel.dataset.id = current.id;
            const time = byId('duelWagerTime');
            time.textContent = `${wager.segundos} s`;
            time.dataset.urgent = String(wager.segundos <= 3);
            const format = (amount) => amount === null ? 'Pendiente' : PokeEconomy.number(amount);
            byId('duelWagerOwn').textContent = format(wager.mi_monto);
            byId('duelWagerRival').textContent = format(wager.rival_monto);
            duelWagerPanel.querySelectorAll('[data-duel="propuesta_rapida"]').forEach((button) => {
                button.setAttribute('aria-pressed', String(Number(button.dataset.value) === wager.mi_monto));
            });
            const matchAmount = byId('duelWagerMatch');
            byId('duelWagerTitle').textContent = wager.elegir ? 'Elijan un monto' : 'Apuesta del duelo';
            duelWagerPanel.dataset.choosing = String(Boolean(wager.elegir));
            matchAmount.hidden = !wager.rival_monto || (!wager.elegir && wager.rival_monto === wager.mi_monto);
            matchAmount.dataset.value = String(wager.rival_monto || 0);
            matchAmount.textContent = `Aceptar ${PokeEconomy.number(wager.rival_monto)} monedas`;
            const keep = byId('duelWagerKeep');
            keep.hidden = !wager.elegir;
            keep.dataset.value = String(wager.mi_monto || 0);
            keep.textContent = `Elegir ${PokeEconomy.number(wager.mi_monto)} monedas`;
            const progress = byId('duelWagerProgress');
            progress.hidden = !wager.elegir;
            if (wager.elegir) {
                const count = Object.values(wager.votos).reduce((total, votes) => total + votes, 0);
                const label = `${count} de ${wager.total_jugadores} jugadores votaron`;
                if (progress.textContent !== label) progress.textContent = label;
            }
            for (const button of [matchAmount, keep]) {
                const amount = Number(button.dataset.value);
                button.setAttribute('aria-pressed', String(wager.mi_eleccion === amount));
                if (wager.elegir) {
                    const votes = wager.votos[amount] ?? 0;
                    button.textContent = `${PokeEconomy.number(amount)} monedas · ${votes}/${wager.total_jugadores} votos`;
                    button.style.setProperty('--vote-fill', `${votes * 100 / wager.total_jugadores}%`);
                }
            }
            if (!duelWagerPanel.open) {
                byId('duelWagerAmount').value = '';
                byId('duelWagerError').textContent = '';
                duelWagerPanel.showModal();
            }
            return;
        }
        if (current.estado === 'activa' && current.partida.iniciativa) window.PokeInitiative?.show({
            key: 'duelo-' + current.id, state: current.partida.iniciativa, own: 'jugador', system: false,
            players: {
                jugador: { name: 'Tu equipo', roster: current.partida.equipo.map(p => p.nombre).join(' · ') },
                rival: { name: '@' + current.rival, roster: current.partida.rival.map(p => p.nombre).join(' · ') },
            },
            ready: () => requestDuels({ accion: 'iniciativa', id: current.id }),
            exit: async () => {
                if (!confirm('Salir cuenta como rendición. ¿Quieres salir de esta partida?')) return false;
                await requestDuels({ accion: 'rendirse', id: current.id }); return true;
            },
        });
        if (match.hidden || match.dataset.snapshot !== key) {
            drawDuelArena(current);
            if (current.partida.bote && !current.partida.accion) PokeEconomy.refresh().catch(() => {});
            else if (current.partida.bote && current.partida.resultado) PokeEconomy.refresh().catch(() => {});
            match.dataset.snapshot = key;
        }
        return;
    }
    window.PokeInitiative?.close();
    if (!match.hidden) {
        match.hidden = true;
        PokeBattleEffects.finish();
    }
    const ball = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h6m6 0h6"/><circle cx="12" cy="12" r="3"/></svg>';
    const rank = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 15 7-7 7 7M5 21l7-7 7 7"/></svg>';
    const estados = { disponible: 'Disponible', buscando: 'Buscando partida', jugando: 'En combate', confirmando: 'Confirmando partida' };
    const pendingRivals = new Set(data.duelos.filter((d) => d.estado === 'pendiente').map((d) => d.rival));
    const players = data.buscando ? data.jugadores.filter((p) => p.estado === 'buscando' && !pendingRivals.has(p.usuario)) : [];
    byId('duelPlayers').innerHTML = players.map((p) => `<article class="duel-trainer">
        <strong>@${escapar(p.usuario)}</strong><div class="duel-trainer-meta">
        <span>${rank} Nv. ${Number(p.nivel)} · ${escapar(p.rango)}</span>
        ${p.cantidad ? `<span>${ball} ${Number(p.cantidad)} Pokémon</span>` : ''}</div>
        <div class="duel-trainer-state" data-state="${escapar(p.estado)}">${estados[p.estado] || ''}</div>
        <button class="btn-secundario" data-duel="invitar" data-value="${Number(p.id)}">Invitar</button></article>`).join('') || (data.buscando ? '<p>Sin entrenadores compatibles por ahora.</p>' : '<p>Pulsa Buscar partida para ver entrenadores compatibles.</p>');
    const invites = data.duelos.filter((d) => d.estado === 'pendiente' && Boolean(d.entre_amigos) === friendDuelMode);
    const challengeCard = (d) => `<article class="duel-trainer duel-challenge">
        <strong>@${escapar(d.rival)}</strong><div class="duel-trainer-meta"><span>${rank} Nv. ${Number(d.nivel_rival)}</span>
        ${d.cantidad ? `<span>${ball} ${Number(d.cantidad)} por lado</span>` : ''}</div>
        <small>${d.confirmado ? 'Esperando al rival' : d.recibida ? 'Te invita a un duelo' : 'Esperando respuesta'}
        <span data-duel-expiry="${Number(d.vence)}"></span></small>
        ${d.recibida || d.emparejado ? `<div class="duel-actions"><button class="btn-batalla" data-duel="aceptar" data-id="${d.id}" ${d.confirmado ? 'disabled' : ''}>${d.confirmado ? 'Confirmado' : 'Aceptar'}</button>
            <button class="btn-secundario" data-duel="rechazar" data-id="${d.id}">${d.confirmado ? 'Cancelar reto' : 'Rechazar'}</button></div>` : ''}</article>`;
    const listedInvites = invites.filter((d) => !d.emparejado);
    byId('duelInvites').innerHTML = listedInvites.map(challengeCard).join('') || '<p>Sin invitaciones pendientes.</p>';
    const matchInvite = invites.find((d) => d.emparejado);
    const challenge = byId('duelChallengePanel');
    if (matchInvite) {
        byId('duelChallengeContent').innerHTML = challengeCard(matchInvite);
        if (matchInvite.id !== shownDuelMatch) {
            shownDuelMatch = matchInvite.id;
            byId('duelChallengeError').textContent = '';
            if (!challenge.open) challenge.showModal();
        }
    } else if (challenge.open) {
        challenge.close();
    }
    const badges = [
        [byId('duelInviteCount'), listedInvites.filter((duel) => duel.recibida).length],
        [byId('duelPlayerCount'), players.length],
    ];
    badges.forEach(([badge, count]) => { badge.textContent = count; badge.hidden = count === 0; });
}
function actualizarVencimientosDuelo(serverTime) {
    duelRoom.querySelectorAll('[data-duel-expiry]').forEach((label) => {
        const remaining = Math.max(0, Number(label.dataset.duelExpiry) - serverTime);
        label.textContent = ' · ' + remaining + ' s';
        label.closest('article').hidden = remaining === 0;
    });
}
async function requestDuels(body) {
    if (duelRoom.hidden) return;
    if (duelBusy) {
        if (body && !duelSending && !duelQueued) duelQueued = body;
        return;
    }
    duelBusy = true;
    duelSending = Boolean(body);
    if (body) byId('duelStatus').textContent = 'Procesando…';
    try {
        const data = await PokeMarket.trainer('/duels', body ? { method: 'POST', body } : {});
        if (duelRoom.hidden) return;
        if (body?.accion === 'rendirse') {
            dismissedDuels.add(body.id);
            PokeEconomy.refresh().catch(() => {});
        }
        duelSearchStarted = friendDuelMode || data.inicio_busqueda == null ? null : Date.now() - (data.hora_servidor - data.inicio_busqueda) * 1000;
        actualizarRelojDuelo();
        const snapshot = JSON.stringify([data.jugadores, data.duelos, data.buscando, [...dismissedDuels]]);
        if (snapshot !== duelSnapshot) {
            drawDuels(data);
            duelSnapshot = snapshot;
        }
        actualizarVencimientosDuelo(data.hora_servidor);
        if (body) {
            byId('duelStatus').textContent = '';
            byId('duelChallengeError').textContent = '';
        }
    } catch (error) {
        byId(duelWagerPanel.open ? 'duelWagerError' : byId('duelChallengePanel').open ? 'duelChallengeError' : 'duelStatus').textContent = error.message;
    } finally {
        duelBusy = duelSending = false;
        const next = duelQueued;
        duelQueued = null;
        if (next) void requestDuels(next);
    }
}
async function abrirDuelos(friends = false) {
    if (window.PokeDuos?.active) { PokeDuos.open(); return; }
    if (!duelRoom.hidden) return;
    friendDuelMode = friends;
    latestDuelLobby = null;
    byId('duelLobby').classList.toggle('duel-friend-mode', friends);
    byId('teamDuelsTitle').textContent = friends ? 'Duelo con amigos' : 'Combate por equipos';
    byId('duelFriendList').hidden = !friends;
    byId('duelSearchSubmit').hidden = friends;
    byId('duelStop').hidden = friends;
    if (friends) {
        byId('duelFriends').textContent = 'Cargando amistades…';
        PokeMarket.trainer('/friends').then(({ amigos }) => {
            byId('duelFriends').innerHTML = amigos.filter((friend) => Number(friend.aceptada)).map((friend) =>
                `<article class="duel-trainer"><strong>@${escapar(friend.usuario)}</strong><button class="btn-secundario" type="button" data-duel="invitar_amigo" data-value="${Number(friend.id)}">Invitar al duelo</button></article>`
            ).join('') || '<p>Todavía no tienes amigos agregados.</p>';
        }).catch((error) => { byId('duelFriends').textContent = error.message; });
    }
    duelSnapshot = '';
    byId('battleHeading').hidden = true;
    panelInicio.hidden = true;
    duelRoom.hidden = false;
    duelRoom.classList.toggle('duel-motion', PokeMarket.animacionesActivas());
    byId('teamDuelsTitle').focus();
    void requestDuels();
    duelPoll = setInterval(() => { if (!document.hidden) { actualizarRelojDuelo(); void requestDuels(); } }, 1000);
    try {
        const { equipos } = await PokeMarket.trainer('/teams');
        if (duelRoom.hidden) return;
        const teams = equipos.filter((e) => Number(e.total_miembros) > 0).map((e) => `<option value="${Number(e.id)}">${escapar(e.nombre)}</option>`).join('');
        byId('duelTeam').innerHTML = teams + (friends ? '' : '<option value="0">Individual · Un Pokémon del Hogar</option>') || '<option value="">Crea un equipo con Pokémon</option>';
        await cargarAlineacionDuelo();
    } catch (error) { byId('duelStatus').textContent = error.message; }
}
async function cargarAlineacionDuelo() {
    const team = Number(byId('duelTeam').value);
    const lineup = byId('duelLineup');
    lineup.dataset.team = '';
    lineup.querySelectorAll('label').forEach((label) => label.remove());
    if (!byId('duelTeam').value) return;
    try {
        const members = team ? (await PokeMarket.trainer(`/teams/${team}`)).equipo.miembros
            : (await PokeMarket.trainer('/home')).pokemon.map((pokemon) => ({ pokemon_id: pokemon.id, nombre: pokemon.nombre }));
        if (Number(byId('duelTeam').value) !== team || duelRoom.hidden) return;
        lineup.dataset.team = String(team);
        lineup.insertAdjacentHTML('beforeend', members.map((member, index) => {
            const pokemon = pokemonPropios.get(Number(member.pokemon_id));
            const name = pokemon?.nombre || member.nombre || `Pokémon #${Number(member.pokemon_id)}`;
            return `<label><input type="${team ? 'checkbox' : 'radio'}" name="duelPokemon" value="${Number(member.pokemon_id)}" ${team || index === 0 ? 'checked' : ''}>
                ${duelSprite({ id: member.pokemon_id })}<span>${escapar(name)}</span></label>`;
        }).join(''));
        if (latestDuelLobby) drawDuels(latestDuelLobby);
    } catch (error) { byId('duelStatus').textContent = error.message; }
}
function alineacionDuelo() {
    const lineup = byId('duelLineup');
    return Number(lineup.dataset.team) === Number(byId('duelTeam').value)
        ? Array.from(lineup.querySelectorAll('input:checked'), (input) => Number(input.value))
        : [];
}
byId('duelTeam').addEventListener('change', cargarAlineacionDuelo);
byId('duelLineup').addEventListener('change', () => {
    if (latestDuelLobby) drawDuels(latestDuelLobby);
});
byId('duelSearch').addEventListener('submit', (event) => {
    event.preventDefault();
    if (friendDuelMode) return;
    if (!alineacionDuelo().length) {
        byId('duelStatus').textContent = 'Elige al menos un Pokémon para combatir.';
        return;
    }
    void requestDuels({ accion: 'buscar', equipo_id: Number(byId('duelTeam').value), alineacion: alineacionDuelo() });
});
byId('duelStop').addEventListener('click', () => requestDuels({ accion: 'salir' }));
duelRoom.addEventListener('click', (event) => {
    const panelButton = event.target.closest('[data-duel-panel]');
    if (panelButton) {
        abrirPanelDuelo(panelButton.dataset.duelPanel);
        return;
    }
    const button = event.target.closest('[data-duel]');
    if (!button) return;
    const accion = button.dataset.duel, value = button.dataset.value;
    const id = button.dataset.id || byId('duelMatch').dataset.id;
    if (accion === 'modos') {
        clearInterval(duelPoll);
        duelQueued = null;
        duelRoom.hidden = true;
        for (const panel of [duelMobilePanel, duelWagerPanel, byId('duelChallengePanel')]) if (panel.open) panel.close();
        byId('battleHeading').hidden = false;
        panelInicio.hidden = false;
        if (latestDuelLobby?.buscando) {
            PokeMarket.trainer('/duels', { method: 'POST', body: { accion: 'salir' } })
                .catch(error => mostrarMensaje(error.message, true));
        }
        document.querySelector('[data-modo="equipos"]')?.focus({ preventScroll: true });
        byId('battleHeading').scrollIntoView({ block: 'start', behavior: 'auto' });
        return;
    }
    if (accion === 'volver') {
        dismissedDuels.add(id);
        if (latestDuelLobby) drawDuels(latestDuelLobby);
        return;
    }
    if (['sin_apuesta', 'igualar_apuesta', 'propuesta_rapida'].includes(accion)) {
        void requestDuels({ accion: 'proponer_apuesta', id: duelWagerPanel.dataset.id, monto: accion === 'sin_apuesta' ? 0 : Number(value) });
        return;
    }
    if ((accion === 'atacar' || accion === 'cambiar') && performance.now() < duelIntro) return;
    const equipo_id = Number(byId('duelTeam').value);
    const alineacion = alineacionDuelo();
    if (accion === 'invitar' && !latestDuelLobby?.buscando) return;
    if ((['invitar', 'invitar_amigo'].includes(accion) || (accion === 'aceptar' && (friendDuelMode || duelSearchStarted === null))) && !alineacion.length) {
        byId('duelStatus').textContent = 'Elige qué Pokémon quieres llevar.';
        return;
    }
    void requestDuels({ accion, id, equipo_id, alineacion, rival: Number(value), movimiento: value, pokemon_id: Number(value) });
});

document
    .querySelectorAll('[data-modo]')
    .forEach((button) =>
        button.addEventListener('click', () => {
            if (button.dataset.modo === 'equipos') {
                if (window.PokeDuos) PokeDuos.open();
                else void abrirDuelos();
            }
            else if (button.dataset.modo === 'amigos') void abrirDuelos(true);
            else abrirConfirmacion(button.dataset.modo);
        })
    );
iniciarBatalla.addEventListener('click', comenzarBatalla);
byId('practicaDirecta').addEventListener('click', () => abrirConfirmacion('practica'));
confirmarEntrada.addEventListener('cancel', (event) => {
    if (procesando) event.preventDefault();
});
confirmarEntrada.querySelector('form').addEventListener('submit', (event) => {
    if (procesando) event.preventDefault();
});
byId('repetirBatalla').addEventListener('click', volverInicio);
byId('abrirResultado').addEventListener('click', () => byId('resultadoMovil').showModal());
byId('abandonarBatalla').addEventListener('click', abandonar);
byId('continuarBatalla').addEventListener('click', continuar);
byId('abandonarDesdeInicio').addEventListener('click', async () => {
    if (
        !batallaGuardada ||
        procesando ||
        !(await confirmarSalida(batallaGuardada))
    )
        return;
    procesando = true;
    byId('abandonarDesdeInicio').disabled = true;
    try {
        await PokeMarket.trainer(`/battles/${batallaGuardada.id}`, { method: 'DELETE' });
        batallaGuardada = null;
    } catch (error) {
        mostrarMensaje(error.message, true);
    } finally {
        procesando = false;
        byId('abandonarDesdeInicio').disabled = false;
        actualizarInicio();
    }
});
const syncBattleSound = PokeAudioMixer.button('sonidoBatalla', (enabled) => {
    PokeBattleEffects.setSound(enabled);
});
PokeBattleEffects.onAudioStatus((status) => {
    byId('estadoMusica').textContent =
        status === 'loading'
            ? 'Preparando la música…'
            : status === 'error'
              ? 'No se pudo reproducir la música. Puedes volver a intentarlo.'
              : '';
    syncBattleSound(PokeBattleEffects.isSoundEnabled());
});
byId('salirPractica').addEventListener('click', abandonar);
byId('animacionesBatalla').checked = PokeMarket.animacionesActivas();
byId('animacionesBatalla').addEventListener('change', (event) => {
    event.target.checked = PokeMarket.cambiarAnimaciones(event.target.checked);
});
const manual = byId('manualBatalla');
byId('ayudaBatalla').addEventListener('click', () => {
    manual.showModal();
    byId('ayudaBatalla').setAttribute('aria-expanded', 'true');
});
const cerrarManual = () => manual.close();
byId('cerrarManual').addEventListener('click', cerrarManual);
byId('manualVolver').addEventListener('click', cerrarManual);
manual.addEventListener('close', () => byId('ayudaBatalla').setAttribute('aria-expanded', 'false'));
byId('manualPractica').addEventListener('click', () => {
    cerrarManual();
    void iniciarPractica();
});
byId('ocultarGuia').addEventListener('click', () => {
    if (procesando) return;
    guiaVisible = false;
    if (esPractica()) {
        byId('tituloArena').textContent = 'Práctica libre';
        byId('subtituloArena').textContent =
            'Prueba los ataques y los cambios a tu ritmo. Puedes reiniciar el tutorial desde Cómo jugar.';
    }
    renderizarBatalla(batalla);
    guardarPractica();
    mostrarAtaques();
});
byId('guiaSiguiente').addEventListener('click', () => {
    if (!guiaActiva() || procesando) return;
    pasoPractica = PokeTutorial.acknowledge(pasoPractica);
    renderizarBatalla(batalla);
    guardarPractica();
    mostrarAtaques();
    const target =
        pasoPractica === 3
            ? byId('miniaturasJugador').querySelector('button:not(:disabled)')
            : movimientos.querySelector('button:not(:disabled)');
    target?.focus({ preventScroll: !window.matchMedia('(max-width: 780px)').matches });
});
byId('reiniciarGuia').addEventListener('click', iniciarPractica);
abrirAtaques.addEventListener('click', mostrarAtaques);
byId('reintentarTurnoRival').addEventListener('click', continuarTurno);
byId('verReservas').addEventListener('click', () => {
    selectorAtaques.close();
    byId('miniaturasJugador').querySelector('button:not(:disabled)')?.focus();
});
document.addEventListener('keydown', (event) => {
    if (
        (document.querySelector('dialog[open]') && !selectorAtaques.open) ||
        manual.open ||
        event.repeat ||
        event.ctrlKey ||
        event.altKey ||
        event.metaKey ||
        /INPUT|TEXTAREA|SELECT/.test(event.target.tagName)
    )
        return;
    if (!panelCombate.hidden && /^[1-4]$/.test(event.key)) {
        const button = movimientos.querySelectorAll('button')[Number(event.key) - 1];
        if (button && !button.disabled) {
            event.preventDefault();
            button.click();
        }
    }
});
PokeWager.init(actualizarInicio);
actualizarInicio();
void cargarEquipos();
