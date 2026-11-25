const listaEquipos = document.getElementById('listaEquipos');
const detalleEquipo = document.getElementById('detalleEquipo');
const formulario = document.getElementById('formEquipo');
const nombreEquipo = document.getElementById('nombreEquipo');
const mensaje = document.getElementById('mensajeEquipos');
const selectorEquipo = document.getElementById('selectorEquipoPokemon');
const opcionesEquipo = document.getElementById('opcionesEquipoPokemon');
const estadoSelector = document.getElementById('estadoSelectorEquipo');
const editorNombre = document.getElementById('renombrarEquipo');
const formNombre = document.getElementById('formRenombrarEquipo');
const nuevoNombre = document.getElementById('nuevoNombreEquipo');
const estadoNombre = document.getElementById('estadoRenombrarEquipo');
const cancelarNombre = document.getElementById('cancelarRenombrarEquipo');
const memoriaPokemon = new Map();
let equipos = [];
let capacidad = null;
let coleccion = [];
let equipoActual = null;
let cargandoEquipo = 0;
let guardandoMiembro = false;
let equipoParaRenombrar = null;
cancelarNombre.onclick = () => editorNombre.close();
editorNombre.addEventListener('cancel', (event) => {
    if (cancelarNombre.disabled) event.preventDefault();
});
formNombre.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (cancelarNombre.disabled) return;
    const { id, nombre: anterior } = equipoParaRenombrar;
    const nombre = nuevoNombre.value.trim();
    if (!nombre) {
        estadoNombre.textContent = 'Escribe un nombre para tu equipo.';
        nuevoNombre.focus();
        return;
    }
    if (nombre === anterior) return editorNombre.close();
    const guardar = formNombre.querySelector('[type="submit"]');
    const restore = PokeMarket.esperarBoton(guardar, 'Guardando…');
    cancelarNombre.disabled = nuevoNombre.disabled = true;
    estadoNombre.textContent = 'Guardando…';
    try {
        await PokeMarket.trainer(`/teams/${id}`, { method: 'PATCH', body: { nombre } });
        const equipo = equipos.find((item) => Number(item.id) === Number(id));
        if (equipo) equipo.nombre = nombre;
        if (Number(equipoActual?.id) === Number(id)) {
            equipoActual.nombre = nombre;
            const titulo = detalleEquipo.querySelector('.team-name h2');
            if (titulo) titulo.textContent = nombre;
        }
        renderizarLista();
        editorNombre.close();
        mostrarMensaje('Nombre del equipo guardado.');
    } catch (error) {
        estadoNombre.textContent = error.message;
    } finally {
        restore();
        cancelarNombre.disabled = nuevoNombre.disabled = false;
    }
});
selectorEquipo.addEventListener('cancel', (event) => {
    if (guardandoMiembro) event.preventDefault();
});
selectorEquipo.querySelector('form').addEventListener('submit', (event) => {
    if (guardandoMiembro) event.preventDefault();
});

function mostrarMensaje(texto = '', error = false) {
    mensaje.textContent = texto;
    mensaje.classList.toggle('error', error);
}

function renderizarLista() {
    if (capacidad) {
        const lleno = equipos.length >= capacidad.limite;
        const boton = formulario.querySelector('button');
        boton.disabled = lleno;
        boton.dataset.gameLocked = String(lleno);
        nombreEquipo.disabled = lleno;
        document.getElementById('crearEquipoRequisito').textContent = !lleno
            ? ''
            : capacidad.nivel_siguiente
              ? `Llega al nivel ${capacidad.nivel_siguiente} de entrenador para desbloquear otro equipo.`
              : `Límite de ${capacidad.limite} equipos. Elimina uno para crear otro.`;
        document.getElementById('capacidadEquipos').textContent =
            `${equipos.length} / ${capacidad.limite} equipos`;
    }
    if (equipos.length === 0) {
        listaEquipos.innerHTML = '<div class="loading">Aún no has creado equipos.</div>';
        return;
    }
    listaEquipos.replaceChildren();
    equipos.forEach((equipo) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'equipo-item';
        boton.classList.toggle('activo', Number(equipoActual?.id) === Number(equipo.id));
        boton.setAttribute('aria-pressed', String(Number(equipoActual?.id) === Number(equipo.id)));
        boton.innerHTML = `<strong>${PokeMarket.escaparHTML(equipo.nombre)}</strong>
            <span>${Number(equipo.total_miembros)} / 6</span>`;
        boton.addEventListener('click', () => cargarEquipo(equipo.id));
        listaEquipos.appendChild(boton);
    });
}

async function cargarDatos() {
    try {
        await PokeMarket.iniciarSesion();
        const [respuestaEquipos, respuestaColeccion] = await Promise.all([
            PokeMarket.trainer('/teams'),
            PokeMarket.trainer('/home'),
        ]);
        equipos = respuestaEquipos.equipos;
        capacidad = respuestaEquipos.capacidad;
        coleccion = respuestaColeccion.pokemon.map((pokemon) => ({ pokemon_id: pokemon.id }));
        respuestaColeccion.pokemon.forEach((pokemon) =>
            memoriaPokemon.set(Number(pokemon.id), pokemon)
        );
        renderizarLista();
        if (equipos.length > 0) await cargarEquipo(equipos[0].id);
    } catch (error) {
        mostrarMensaje(error.message, true);
        listaEquipos.innerHTML = '<div class="loading">No fue posible cargar los equipos.</div>';
    }
}

async function cargarEquipo(id) {
    const solicitud = ++cargandoEquipo;
    detalleEquipo.innerHTML = '<div class="loading">Cargando equipo...</div>';
    try {
        const resultado = await PokeMarket.trainer(`/teams/${id}`);
        if (solicitud !== cargandoEquipo) return;
        equipoActual = resultado.equipo;
        await PokeMarket.obtenerPokemonLote(
            equipoActual.miembros.map((miembro) => miembro.pokemon_id),
            memoriaPokemon
        );
        if (solicitud !== cargandoEquipo) return;
        actualizarEquipoLista();
        renderizarLista();
        renderizarEquipo();
    } catch (error) {
        if (solicitud !== cargandoEquipo) return;
        detalleEquipo.innerHTML = `<div class="empty-equipo"><p>${PokeMarket.escaparHTML(error.message)}</p></div>`;
    }
}

function actualizarEquipoLista() {
    const indice = equipos.findIndex((equipo) => Number(equipo.id) === Number(equipoActual.id));
    if (indice >= 0) equipos[indice].total_miembros = equipoActual.miembros.length;
}

function opcionesColeccion(posicion) {
    const usados = new Set(
        equipoActual.miembros
            .filter((miembro) => Number(miembro.posicion) !== posicion)
            .map((miembro) => Number(miembro.pokemon_id))
    );
    return coleccion
        .map((item) => memoriaPokemon.get(Number(item.pokemon_id)))
        .filter((pokemon) => pokemon && !usados.has(Number(pokemon.id)));
}

function elegirMiembro(posicion, actual) {
    document.getElementById('tituloSelectorEquipo').textContent =
        `Pokémon para el puesto ${posicion}`;
    estadoSelector.textContent = '';
    const candidatos = opcionesColeccion(posicion).filter((pokemon) => pokemon.id !== actual?.id);
    opcionesEquipo.replaceChildren(
        ...candidatos.map((pokemon) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'team-picker-card';
            button.innerHTML = `<img loading="lazy" src="${PokeMarket.imagenPokemon(Number(pokemon.id))}" width="72" height="72" alt=""><strong>${PokeMarket.escaparHTML(pokemon.nombre)}</strong><small>Nv. ${Number(pokemon.nivel || 1)}</small>`;
            button.onclick = () => guardarMiembro(posicion, Number(pokemon.id), button);
            return button;
        })
    );
    if (!candidatos.length)
        estadoSelector.textContent = 'No tienes otros Pokémon disponibles para este puesto.';
    selectorEquipo.showModal();
}

function renderizarEquipo() {
    const miembros = new Map(
        equipoActual.miembros.map((miembro) => [Number(miembro.posicion), miembro])
    );
    detalleEquipo.replaceChildren();
    const cabecera = document.createElement('div');
    cabecera.className = 'cabecera-equipo';
    cabecera.innerHTML = `<div><div class="team-name profile-handle"><h2>${PokeMarket.escaparHTML(equipoActual.nombre)}</h2>
        <button type="button" aria-label="Editar nombre del equipo" aria-haspopup="dialog"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 5 5 5M4 20l5-1L20 8l-4-4L5 15z"/></svg></button></div>
        <p>${equipoActual.miembros.length} de 6 puestos ocupados</p>
        <progress class="team-capacity" aria-label="Puestos ocupados" value="${equipoActual.miembros.length}" max="6"></progress></div>`;
    cabecera.querySelector('button').onclick = () => {
        equipoParaRenombrar = equipoActual;
        nuevoNombre.value = equipoActual.nombre;
        estadoNombre.textContent = '';
        editorNombre.showModal();
        nuevoNombre.select();
    };
    const eliminar = document.createElement('button');
    eliminar.type = 'button';
    eliminar.className = 'btn-eliminar-equipo';
    eliminar.textContent = 'Eliminar equipo';
    eliminar.addEventListener('click', () => eliminarEquipo(eliminar));
    cabecera.appendChild(eliminar);
    detalleEquipo.appendChild(cabecera);

    const slots = document.createElement('div');
    slots.className = 'equipo-slots';
    for (let posicion = 1; posicion <= 6; posicion += 1) {
        const miembro = miembros.get(posicion);
        slots.appendChild(crearSlot(posicion, miembro));
    }
    detalleEquipo.appendChild(slots);
}

function crearSlot(posicion, miembro) {
    const slot = document.createElement('article');
    slot.className = 'equipo-slot';
    slot.innerHTML = `<span class="slot-numero">${String(posicion).padStart(2, '0')}</span>`;
    const pokemon = miembro ? memoriaPokemon.get(Number(miembro.pokemon_id)) : null;
    slot.classList.toggle('slot-ocupado', Boolean(pokemon));
    const contenido = document.createElement('div');
    contenido.className = pokemon ? 'slot-pokemon' : 'slot-vacio';
    if (pokemon) {
        const imagen = document.createElement('img');
        imagen.loading = 'lazy';
        PokeMarket.configurarImagen(imagen, pokemon);
        const verDetalle = document.createElement('button');
        verDetalle.type = 'button';
        verDetalle.textContent = pokemon.nombre;
        verDetalle.addEventListener('click', () => PokeMarket.mostrarDetalle(pokemon));
        const nivel = document.createElement('small');
        nivel.textContent = `Nv. ${Number(pokemon.nivel || 1)}`;
        contenido.append(imagen, verDetalle, nivel);
        slot.appendChild(contenido);
    }

    const selector = document.createElement('button');
    selector.type = 'button';
    selector.className = 'slot-selector';
    selector.dataset.puesto = posicion;
    selector.innerHTML = pokemon ? 'Cambiar' : '<span aria-hidden="true">+</span>Agregar Pokémon';
    selector.setAttribute(
        'aria-label',
        `${pokemon ? 'Cambiar' : 'Agregar'} Pokémon en el puesto ${posicion}`
    );
    selector.onclick = () => elegirMiembro(posicion, pokemon);
    slot.appendChild(selector);

    if (pokemon) {
        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'btn-quitar-miembro';
        quitar.textContent = '×';
        quitar.setAttribute('aria-label', `Quitar a ${pokemon.nombre}`);
        quitar.addEventListener('click', () => quitarMiembro(posicion, quitar));
        slot.appendChild(quitar);
    }
    return slot;
}

async function guardarMiembro(posicion, pokemonId, control) {
    if (!pokemonId || !equipoActual || guardandoMiembro) return;
    guardandoMiembro = true;
    estadoSelector.textContent = 'Guardando…';
    const restore = control && PokeMarket.esperarBoton(control, 'Agregando…');
    const botones = [...opcionesEquipo.querySelectorAll('button')];
    botones.forEach((button) => {
        button.disabled = true;
    });
    try {
        await PokeMarket.trainer(`/teams/${equipoActual.id}/slots/${posicion}`, {
            method: 'PUT',
            body: { pokemon_id: pokemonId },
        });
        const existente = equipoActual.miembros.findIndex(
            (miembro) => Number(miembro.posicion) === posicion
        );
        const miembro = { posicion, pokemon_id: pokemonId };
        if (existente >= 0) equipoActual.miembros[existente] = miembro;
        else equipoActual.miembros.push(miembro);
        actualizarEquipoLista();
        selectorEquipo.close();
        renderizarLista();
        renderizarEquipo();
        detalleEquipo.querySelector(`[data-puesto="${posicion}"]`)?.focus({ preventScroll: true });
        window.PokePokemonCry?.play(pokemonId);
        mostrarMensaje('Pokémon agregado al equipo.');
    } catch (error) {
        estadoSelector.textContent = error.message;
    } finally {
        guardandoMiembro = false;
        restore?.();
        botones.forEach((button) => {
            button.disabled = false;
        });
    }
}

async function quitarMiembro(posicion, boton) {
    const restore = PokeMarket.esperarBoton(boton, 'Quitando…');
    try {
        await PokeMarket.trainer(`/teams/${equipoActual.id}/slots/${posicion}`, {
            method: 'DELETE',
        });
        equipoActual.miembros = equipoActual.miembros.filter(
            (miembro) => Number(miembro.posicion) !== posicion
        );
        actualizarEquipoLista();
        renderizarLista();
        renderizarEquipo();
        mostrarMensaje('Pokémon eliminado del equipo.');
    } catch (error) {
        mostrarMensaje(error.message, true);
    } finally {
        restore();
    }
}

async function eliminarEquipo(boton) {
    if (!window.confirm(`¿Eliminar el equipo ${equipoActual.nombre}?`)) return;
    const restore = PokeMarket.esperarBoton(boton, 'Eliminando…');
    try {
        await PokeMarket.trainer(`/teams/${equipoActual.id}`, { method: 'DELETE' });
        equipos = equipos.filter((equipo) => Number(equipo.id) !== Number(equipoActual.id));
        equipoActual = null;
        renderizarLista();
        detalleEquipo.innerHTML =
            '<div class="empty-equipo"><h2>Equipo eliminado</h2><p>Crea o selecciona otro equipo.</p></div>';
        mostrarMensaje('Equipo eliminado correctamente.');
    } catch (error) {
        mostrarMensaje(error.message, true);
    } finally {
        restore();
    }
}

formulario.addEventListener('submit', async (event) => {
    event.preventDefault();
    const boton = formulario.querySelector('button');
    if (boton.disabled) return;
    const restore = PokeMarket.esperarBoton(boton, 'Creando equipo…');
    mostrarMensaje();
    try {
        const resultado = await PokeMarket.trainer('/teams', {
            method: 'POST',
            body: { nombre: nombreEquipo.value.trim() },
        });
        equipos.unshift({ ...resultado.equipo, total_miembros: 0 });
        nombreEquipo.value = '';
        renderizarLista();
        await cargarEquipo(resultado.equipo.id);
        mostrarMensaje('Equipo creado correctamente.');
    } catch (error) {
        mostrarMensaje(error.message, true);
    } finally {
        restore();
        boton.disabled = Boolean(capacidad && equipos.length >= capacidad.limite);
    }
});

void cargarDatos();
