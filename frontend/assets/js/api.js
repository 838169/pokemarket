window.PokeMarket = (() => {
    const urls = {
        pokemon: 'api/pokemon',
        trainer: 'api/entrenadores',
    };
    let sesionActual = null;
    let csrfToken = null;
    let cerrandoSesion = false;
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
    let animaciones = !movimientoReducido.matches;
    try {
        animaciones = animaciones && localStorage.getItem('pokemarket.animaciones') !== 'false';
    } catch (_) {}
    const datosImagenes = new WeakMap();
    function cambiarAnimaciones(activas) {
        animaciones = Boolean(activas) && !movimientoReducido.matches;
        try {
            localStorage.setItem('pokemarket.animaciones', String(animaciones));
        } catch (_) {}
        document.querySelectorAll('img[data-pokemon-imagen]').forEach((imagen) => {
            const carga = datosImagenes.get(imagen);
            if (carga) configurarImagen(imagen, carga.datos, carga.seguimiento);
        });
        return animaciones;
    }
    const actualizarMovimiento = () => cambiarAnimaciones(!movimientoReducido.matches);
    if (movimientoReducido.addEventListener) movimientoReducido.addEventListener('change', actualizarMovimiento);
    else movimientoReducido.addListener?.(actualizarMovimiento);

    async function solicitar(servicio, ruta, opciones = {}) {
        const { body, ...resto } = opciones;
        const headers = body === undefined ? {} : { 'Content-Type': 'application/json' };
        const mutation =
            servicio === 'trainer' &&
            !['GET', 'HEAD', 'OPTIONS'].includes((resto.method || 'GET').toUpperCase());
        if (mutation && !['/auth/login', '/auth/register', '/auth/google-signup', '/auth/login-code', '/auth/register-code', '/auth/register-resend', '/auth/reset-request', '/auth/reset-verify', '/auth/reset-password'].includes(ruta)) {
            if (!csrfToken) {
                const session = await solicitar('trainer', '/auth/me');
                csrfToken = session.csrf_token;
            }
            headers['X-CSRF-Token'] = csrfToken;
        }
        let respuesta;
        try {
            respuesta = await fetch(urls[servicio] + ruta, {
                ...resto,
                credentials: servicio === 'trainer' ? 'include' : 'omit',
                headers,
                body: body === undefined ? undefined : JSON.stringify(body),
            });
        } catch (error) {
            if (error.name === 'AbortError') throw error;
            if (servicio === 'trainer' && document.body.dataset.protegida === 'true') {
                window.location.replace('error.php?status=503&service=trainer');
            }
            throw Object.assign(new Error('No fue posible conectar con el servicio. Intenta nuevamente.'), {
                code: 'SERVICE_OFFLINE',
            });
        }

        if (respuesta.status === 401 && servicio === 'trainer') {
            sesionActual = undefined;
            if (document.body.dataset.protegida === 'true') {
                window.location.replace('login.php');
            }
        }

        let datos;
        try {
            datos = await respuesta.json();
        } catch {
            throw new Error('El servicio devolvió una respuesta inválida.');
        }
        if (!respuesta.ok || !datos.success) {
            const error = new Error(datos.message || 'No fue posible completar la solicitud.');
            error.status = respuesta.status;
            error.data = datos;
            throw error;
        }
        if (datos.csrf_token) csrfToken = datos.csrf_token;
        if (ruta === '/auth/logout') csrfToken = null;
        return datos;
    }

    const pokemon = (ruta, opciones) => solicitar('pokemon', ruta, opciones);

    function esperarBoton(boton, texto) {
        const contenido = [...boton.childNodes];
        const deshabilitado = boton.disabled;
        boton.disabled = true;
        boton.setAttribute('aria-busy', 'true');
        boton.textContent = texto;
        return () => {
            boton.replaceChildren(...contenido);
            boton.removeAttribute('aria-busy');
            boton.disabled = deshabilitado;
        };
    }

    async function detalleConProgreso(id, progreso) {
        let respuesta;
        try {
            respuesta = await fetch(`${urls.pokemon}/pokemon/${Number(id)}?view=card&progress=1`);
        } catch (_) {
            throw new Error(
                'No fue posible conectar con el servicio de Pokémon. Intenta nuevamente.'
            );
        }
        if (!respuesta.ok || !respuesta.body)
            throw new Error('No fue posible cargar los detalles. Intenta nuevamente.');
        const reader = respuesta.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let resultado = null;
        const procesar = (linea) => {
            if (!linea.trim()) return;
            const evento = JSON.parse(linea);
            if (evento.event === 'error') throw new Error(evento.message);
            if (evento.event === 'progress')
                progreso(Math.max(0, Math.min(100, Number(evento.percent))));
            if (evento.event === 'result') resultado = evento.data;
        };
        try {
            while (true) {
                const { value, done } = await reader.read();
                buffer += decoder.decode(value, { stream: !done });
                let fin;
                while ((fin = buffer.indexOf('\n')) !== -1) {
                    procesar(buffer.slice(0, fin));
                    buffer = buffer.slice(fin + 1);
                }
                if (done) break;
            }
            procesar(buffer);
            if (!resultado?.success || !resultado.pokemon)
                throw new Error('La carga quedó incompleta. Intenta nuevamente.');
            return resultado;
        } finally {
            await reader.cancel().catch(() => {});
            reader.releaseLock();
        }
    }
    const trainer = (ruta, opciones) => solicitar('trainer', ruta, opciones);
    const friendDuelNotice = document.getElementById('friendDuelNotice');
    if (friendDuelNotice) {
        const checkFriendDuel = async () => {
            if (document.hidden || friendDuelNotice.dataset.pending) return;
            friendDuelNotice.dataset.pending = 'true';
            try {
                const { invitaciones } = await trainer('/friends?invitaciones=1');
                const invite = invitaciones[0];
                friendDuelNotice.hidden = !invite;
                if (invite) {
                    const friendship = invite.tipo === 'amistad';
                    friendDuelNotice.textContent = friendship ? `@${invite.usuario} te envió una solicitud de amistad · Ver solicitud →`
                        : invite.tipo === 'duo' ? `@${invite.usuario} te invitó a su equipo · Ver invitación →` : `@${invite.usuario} te reta a un duelo · Ver invitación →`;
                    friendDuelNotice.href = friendship ? 'amigos.php#friendRequests'
                        : invite.tipo === 'duo' ? 'batalla.php?sala=duos' : 'batalla.php?sala=amigos';
                }
            } catch (_) {
                friendDuelNotice.hidden = true;
            } finally {
                delete friendDuelNotice.dataset.pending;
            }
        };
        setInterval(checkFriendDuel, 2500);
    }

    async function iniciarSesion() {
        if (sesionActual === null) {
            sesionActual = trainer('/auth/me').catch((error) => {
                sesionActual = null;
                throw error;
            });
        }
        const { usuario } = await sesionActual;
        cargarUsuario(usuario);
        return usuario;
    }

    function cargarUsuario(usuario) {
        const nombre = document.getElementById('usuarioNombre');
        const avatar = document.getElementById('usuarioAvatar');
        const email = document.getElementById('usuarioEmail');
        if (nombre) nombre.textContent = usuario.nombre || '';
        if (email) email.textContent = usuario.email || '';
        if (avatar) avatar.textContent = Array.from(usuario.nombre || '')[0]?.toUpperCase() || '';
    }

    const salir = document.getElementById('cerrarSesion');
    salir?.addEventListener('click', async (event) => {
        event.preventDefault();
        if (cerrandoSesion) return;
        cerrandoSesion = true;
        try {
            await trainer('/auth/logout', { method: 'POST' });
            sesionActual = null;
            window.location.replace('login.php');
        } catch (error) {
            alert(error.message);
        } finally {
            cerrandoSesion = false;
        }
    });

    async function obtenerPokemonLote(ids, memoria = new Map()) {
        const faltantes = [...new Set(ids.map(Number))].filter((id) => !memoria.has(id));
        for (let i = 0; i < faltantes.length; i += 20) {
            const lote = faltantes.slice(i, i + 20);
            const resultado = await pokemon(`/pokemon/batch?ids=${lote.join(',')}`);
            resultado.pokemon.forEach((item) => memoria.set(Number(item.id), item));
        }
        return memoria;
    }

    const imagenPokemon = (id, vista = 'home') => `${urls.pokemon}/sprites/${Number(id)}/${vista}`;
    document.querySelectorAll?.('img[data-pokemon-artwork]').forEach((imagen) => {
        imagen.src = imagenPokemon(imagen.dataset.pokemonArtwork, 'artwork');
    });

    function configurarImagen(imagen, datos, seguimiento) {
        const id = Number(datos.id);
        const tieneId = Number.isInteger(id) && id > 0;
        datosImagenes.get(imagen)?.limpiar?.();
        const carga = { datos, seguimiento };
        datosImagenes.set(imagen, carga);
        imagen.dataset.pokemonImagen = String(id);
        const animar =
            animaciones && !datos.solo_imagen && !imagen.closest('.miniaturas, .miniaturas-equipo');
        imagen.dataset.vistaSprite = datos.vista_espalda && !animar ? 'frente-jugador' : '';
        // Only use verified HD media; HOME remains visible while it downloads.
        const hd = datos.vista_espalda ? datos.sprite_animado_hd_espalda : datos.sprite_animado_hd;
        const animadas = tieneId && animar && hd
            ? [imagenPokemon(id, datos.vista_espalda ? 'back' : 'animated')] : [];
        const fuentes = tieneId ? [imagenPokemon(id), imagenPokemon(id, 'artwork')] : [];
        let indice = 0;
        let fuenteActiva = fuentes[0] || '';
        const solicitudImagen = String(Number(imagen.dataset.solicitudImagen || 0) + 1);
        imagen.dataset.solicitudImagen = solicitudImagen;
        imagen.alt = datos.nombre;
        imagen.style.imageRendering = 'auto';
        let plazo;
        const limpiar = () => {
            clearTimeout(plazo);
            seguimiento?.signal?.removeEventListener('abort', abortar);
        };
        const terminar = (exito) => {
            if (!carga.seguimiento) return;
            limpiar();
            carga.seguimiento = null;
            seguimiento.terminar(exito);
        };
        const abortar = () => {
            imagen.onload = imagen.onerror = null;
            imagen.removeAttribute('src');
            terminar(false);
        };
        if (seguimiento) {
            carga.limpiar = limpiar;
            if (seguimiento.signal?.aborted) return abortar();
            seguimiento.signal?.addEventListener('abort', abortar, { once: true });
        }
        const iniciarFuente = () => {
            if (carga.seguimiento) {
                clearTimeout(plazo);
                seguimiento.progreso(Math.round((indice / fuentes.length) * 90));
                plazo = setTimeout(siguienteFuente, 4500);
            }
            imagen.src = fuenteActiva;
        };
        // Never stretch a low-resolution GIF. Start sharp, then reveal the HD animation.
        imagen.onload = () => {
            imagen.onload = null;
            terminar(true);
            let siguiente = 0;
            const cargarAnimacion = () => {
                if (
                    imagen.dataset.solicitudImagen !== solicitudImagen ||
                    !imagen.isConnected ||
                    siguiente >= animadas.length
                )
                    return;
                const animacion = new Image();
                animacion.decoding = 'async';
                animacion.onload = () => {
                    if (imagen.dataset.solicitudImagen !== solicitudImagen || !imagen.isConnected)
                        return;
                    fuenteActiva = animacion.src;
                    imagen.src = fuenteActiva;
                };
                animacion.onerror = cargarAnimacion;
                animacion.src = animadas[siguiente++];
            };
            cargarAnimacion();
        };
        const siguienteFuente = () => {
            indice += 1;
            if (indice < fuentes.length) {
                fuenteActiva = fuentes[indice];
                iniciarFuente();
            } else {
                imagen.onload = imagen.onerror = null;
                imagen.removeAttribute('src');
                terminar(false);
            }
        };
        imagen.onerror = () => {
            if (imagen.currentSrc === fuenteActiva || imagen.src === fuenteActiva ||
                imagen.getAttribute?.('src') === fuenteActiva) siguienteFuente();
        };
        if (fuentes.length) iniciarFuente();
        else {
            imagen.removeAttribute('src');
            terminar(false);
        }
    }

    function escaparHTML(valor) {
        const elemento = document.createElement('span');
        elemento.textContent = String(valor ?? '');
        return elemento.innerHTML;
    }

    function colorTipo(tipo) {
        const colores = {
            fire: 'rgba(239, 68, 68, .65)',
            water: 'rgba(59, 130, 246, .65)',
            electric: 'rgba(250, 204, 21, .65)',
            grass: 'rgba(34, 197, 94, .60)',
            poison: 'rgba(168, 85, 247, .60)',
            psychic: 'rgba(236, 72, 153, .60)',
            ice: 'rgba(34, 211, 238, .60)',
            dragon: 'rgba(99, 102, 241, .60)',
            dark: 'rgba(71, 85, 105, .60)',
            fairy: 'rgba(244, 114, 182, .60)',
            normal: 'rgba(148, 163, 184, .50)',
        };
        return colores[tipo] ?? 'rgba(255,255,255,.10)';
    }

    const modal = document.getElementById('pokemonModal');
    let cierreDetalle = null;
    function ocultarDetalle() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    function cerrarDetalle() {
        if (cierreDetalle || !modal.classList.contains('open')) return;
        const tarjeta = modal.querySelector('.pokemon-detail');
        if (movimientoReducido.matches || !tarjeta?.animate) {
            ocultarDetalle();
            return;
        }
        const cierre = tarjeta.animate(
            [
                { opacity: 1, transform: 'perspective(1200px) rotateY(0) scale(1)' },
                { opacity: 0, transform: 'perspective(1200px) rotateY(8deg) scale(.96)' },
            ],
            { duration: 160, easing: 'ease-in', fill: 'forwards' }
        );
        cierreDetalle = cierre;
        cierre.finished
            .catch(() => {})
            .then(() => {
                if (cierreDetalle !== cierre) return;
                ocultarDetalle();
                cierreDetalle = null;
                cierre.cancel();
            });
    }
    if (modal) {
        document.getElementById('cerrarModal').addEventListener('click', cerrarDetalle);
        modal.querySelector('.modal-backdrop').addEventListener('click', cerrarDetalle);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('open')) cerrarDetalle();
        });
    }

    function mostrarDetalle(datos) {
        const cierre = cierreDetalle;
        cierreDetalle = null;
        cierre?.cancel();
        const tiposConocidos = [
            'normal',
            'fire',
            'water',
            'grass',
            'electric',
            'ice',
            'fighting',
            'poison',
            'ground',
            'flying',
            'psychic',
            'bug',
            'rock',
            'ghost',
            'dragon',
            'dark',
            'steel',
            'fairy',
        ];
        const tipoVisual = (tipo) => (tiposConocidos.includes(tipo) ? tipo : 'normal');
        modal.dataset.pokemonType = tiposConocidos.includes(datos.tipos[0])
            ? datos.tipos[0]
            : 'normal';
        const numeroArte = document.getElementById('detalleNumeroArte');
        if (numeroArte) numeroArte.textContent = String(datos.id).padStart(3, '0');
        const nivel = document.getElementById('detalleNivel');
        if (nivel) nivel.textContent = `Nv. ${datos.nivel_compra || 1}`;
        const categoria = document.getElementById('detalleCategoria');
        if (categoria)
            categoria.textContent =
                {
                    iniciales: 'Primer compañero',
                    basicos: 'Explorador',
                    avanzados: 'Especialista',
                    elite: 'Élite',
                    legendarios: 'Legendario / singular',
                }[datos.categoria] || 'Ficha de especie';
        configurarImagen(document.getElementById('detalleImagen'), datos);
        document.getElementById('detalleNumero').textContent =
            `N.º ${String(datos.id).padStart(3, '0')}`;
        document.getElementById('detalleNombre').textContent = datos.nombre;
        document.getElementById('detalleTipos').innerHTML = datos.tipos
            .map(
                (tipo) =>
                    `<span class="type-badge" data-type="${tipoVisual(tipo)}">${escaparHTML(window.PokeEconomy?.typeNames?.[tipo] || tipo)}</span>`
            )
            .join('');
        const labels = {
            hp: 'Salud',
            ataque: 'Ataque',
            defensa: 'Defensa',
            ataque_especial: 'Ataque especial',
            defensa_especial: 'Defensa especial',
            velocidad: 'Velocidad',
        };
        const strengths = document.getElementById('detalleFortalezas');
        if (strengths) {
            strengths.innerHTML = Object.entries(datos.estadisticas || {})
                .filter(([key]) => Object.hasOwn(labels, key))
                .sort((a, b) => Number(b[1]) - Number(a[1]))
                .slice(0, 2)
                .map(([key, value]) => `<li data-stat="${key}"><span>${labels[key]}</span><strong>${Number(value)}</strong></li>`)
                .join('');
        }
        const description = document.getElementById('detalleDescripcion');
        if (description) description.textContent = datos.descripcion || 'Información no disponible.';
        document.getElementById('detalleAltura').textContent = `${datos.altura} m`;
        document.getElementById('detallePeso').textContent = `${datos.peso} kg`;
        const inicio = document.getElementById('detalleInicio');
        if (inicio) {
            const ataques = datos.ataques_iniciales;
            inicio.innerHTML = Array.isArray(datos.niveles)
                ? '<h3>Ruta de entrenamiento</h3><div class="detail-level-grid" role="group" aria-label="Ver recompensas por nivel"></div><div class="detail-level-summary" aria-live="polite"></div>'
                : Array.isArray(ataques)
                  ? `<div class="detail-arrival-heading"><span>Nv. ${escaparHTML(datos.nivel_compra || 1)}</span><strong>Sus primeros ataques</strong></div>${ataques.length ? `<div class="detail-starter-moves">${ataques.map((m) => `<span>${escaparHTML(m.nombre_mostrado || m.nombre)}</span>`).join('')}</div>` : '<p>Aún no conoce ataques de daño; puede ganar experiencia como reserva del equipo.</p>'}`
                  : '';
            if (Array.isArray(datos.niveles)) {
                const grid = inicio.querySelector('.detail-level-grid');
                const summary = inicio.querySelector('.detail-level-summary');
                const buttons = datos.niveles.map((step, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = step.nivel;
                    button.setAttribute(
                        'aria-label',
                        `Nivel ${step.nivel}${step.ataques.length ? ', nuevos ataques' : ''}`
                    );
                    if (step.ataques.length) button.classList.add('has-moves');
                    button.onclick = () => showLevel(index);
                    grid.append(button);
                    return button;
                });
                function showLevel(index) {
                    const step = datos.niveles[index];
                    const previous = datos.niveles[index - 1];
                    buttons.forEach((button, i) =>
                        button.setAttribute('aria-pressed', String(index === i))
                    );
                    const stats = Object.entries(step.estadisticas)
                        .map(([key, value]) => {
                            const increase = previous
                                ? Number(value) - Number(previous.estadisticas[key])
                                : 0;
                            return `<div data-stat="${escaparHTML(key)}"><dt>${escaparHTML(key === 'hp' ? 'PS' : labels[key] || key)}</dt><dd>${Number(value)}${increase > 0 ? ` <small>+${increase}</small>` : ''}</dd></div>`;
                        })
                        .join('');
                    summary.innerHTML = `<div class="detail-arrival-heading"><span>Nv. ${Number(step.nivel)}</span><strong>${index === 0 ? 'Estadísticas iniciales' : 'Estadísticas al subir'}</strong></div><dl>${stats}</dl>${step.ataques.length ? `<strong class="detail-move-title">${index === 0 ? 'Ataques iniciales' : 'Nuevos ataques'}</strong><div class="detail-starter-moves">${step.ataques.map((move) => `<span>${escaparHTML(move)}</span>`).join('')}</div>` : '<p>Sin ataques de daño nuevos.</p>'}`;
                }
                if (buttons.length) showLevel(0);
            }
        }
        const debilidades = document.getElementById('detalleDebilidades');
        if (debilidades) {
            const tipos = window.PokeEconomy?.typeNames || {};
            debilidades.innerHTML = Array.isArray(datos.debilidades)
                ? datos.debilidades.length
                    ? datos.debilidades
                          .map(
                              ({ tipo, multiplicador }) =>
                                  `<li class="detail-weakness${multiplicador === 4 ? ' is-severe' : ''}" data-type="${tipoVisual(tipo)}"><span>${escaparHTML(tipos[tipo] || tipo)}</span><strong aria-label="Daño multiplicado por ${escaparHTML(multiplicador)}">×${escaparHTML(multiplicador)}</strong></li>`
                          )
                          .join('')
                    : '<li class="detail-weakness-empty">Sin debilidades por tipo.</li>'
                : '<li class="detail-weakness-empty">Información no disponible.</li>';
        }
        document.getElementById('detalleHabilidades').innerHTML = datos.habilidades
            .map(
                (habilidad) => `
            <div class="ability-item" title="${escaparHTML(habilidad.descripcion || '')}">
                <strong>${escaparHTML(habilidad.nombre)}</strong>
                <span>${habilidad.oculta ? 'Oculta' : 'Principal'}${habilidad.implementada ? ' · En combate' : ' · Informativa'}</span><small>${escaparHTML(habilidad.descripcion || '')}</small>
            </div>`
            )
            .join('');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    if (document.body.dataset.protegida === 'true') {
        iniciarSesion().catch(() => {});
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) trainer('/auth/me').catch(() => {});
        });
    }

    const reactions = new WeakMap();
    function reaccionarImagen(imagen, pokemon) {
        const previous = reactions.get(imagen);
        if (previous && Date.now() - previous.at < 1000) return;
        previous?.animation?.cancel();
        const animation = !movimientoReducido.matches
            ? imagen.animate(
                  [
                      { transform: 'translateX(0) rotate(0)' },
                      { transform: 'translateX(-7px) rotate(-4deg)', offset: 0.25 },
                      { transform: 'translateX(6px) rotate(3deg)', offset: 0.55 },
                      { transform: 'translateX(0) rotate(0)' },
                  ],
                  { duration: 380, easing: 'ease-out' }
              )
            : null;
        reactions.set(imagen, { at: Date.now(), animation });
        window.PokePokemonCry?.play(pokemon.id);
        return true;
    }
    return {
        pokemon,
        detalleConProgreso,
        trainer,
        esperarBoton,
        iniciarSesion,
        obtenerPokemonLote,
        imagenPokemon,
        configurarImagen,
        reaccionarImagen,
        cambiarAnimaciones,
        animacionesActivas: () => animaciones,
        escaparHTML,
        colorTipo,
        mostrarDetalle,
    };
})();
