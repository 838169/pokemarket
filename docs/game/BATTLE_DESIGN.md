# Arena de PokeMarket

La fantasía es dirigir a tu Pokémon desde el campo: observar al rival, elegir un ataque o un cambio, ver el impacto y decidir de nuevo.

La economía y el Hogar están conectados al combate real; véase `ECONOMY.md`. Preparar participantes y movimientos ya usa el índice oficial local. El cuidado modifica las estadísticas antes de empezar. El servidor entrega las monedas y consume energía una sola vez al finalizar; el resultado muestra el premio y un acceso al Hogar. La práctica sigue siendo local, sin premios ni consumo de energía.

## Dirección visual

Encuentro en un claro del bosque, con una pradera como alternativa seleccionable. Los fondos proceden del cliente de Pokémon Showdown. El Pokémon propio aparece grande y de espaldas en primer plano; el rival al fondo. Las fichas de vida recuerdan a los marcadores de los juegos, con fondo marfil y texto oscuro. No hay líneas de campo deportivo ni gradas.

Paleta: fondo #101b21, panel #1b2a2c, bosque #172a24, guía #f7f2db, texto oscuro #25392e y objetivo #edcc6a. Segoe UI/system para lectura y cifras tabulares. `battle-school.css` completa los estilos de la arena. En móvil la explicación aparece antes del campo y los ataques después; al completar una acción se muestra la nueva explicación.

## Modos

- Práctica guiada: simulación local con Squirtle y Pikachu contra Charmander. Enseña efectividad, cambio y victoria. Repetible y sin afectar una batalla real guardada.
- Duelo rápido: el primer miembro del equipo contra un rival. Se limita en el servidor a un miembro por lado.
- Equipos: todos los miembros del equipo elegido contra un rival del mismo tamaño.

La práctica tiene cinco pasos visibles: conocer a Squirtle, usar Pistola Agua, comprobar el daño, cambiar a Pikachu y terminar con Rayo. Las explicaciones tienen botones explícitos para continuar. Solo se habilita la acción del paso actual; las transiciones comprueban el daño y el cambio realmente ejecutados. `battle-tutorial.js` contiene esta política y se prueba de forma independiente. El usuario puede reiniciar o pasar a práctica libre. Cada acción bloquea temporalmente los controles, reproduce el ataque, actualiza vida y comunica el resultado. El historial es desplegable. Se incluyen teclado, foco visible, nombres accesibles y movimiento reducido.

Se retiraron los efectos sintetizados y los gritos automáticos. El control Música reproduce una pista de batalla ORAS del cliente Showdown, alojada localmente, en bucle y al 12% de volumen. Está apagada al entrar, se detiene al salir y muestra errores de reproducción. El jugador puede desactivar las animaciones. Los sprites se mantienen entre turnos para no reiniciar su carga. Fuentes exactas en `frontend/assets/audio/README.md`.

## Verificación

- `node tests/battle-practice.cjs`: práctica de principio a fin, efectividad, coste del cambio, victoria y rechazo de acciones inválidas.
- Pruebas de regresión de imágenes y progreso: `tests/media-ui.cjs` y `tests/detail-progress.cjs`.
- `node tests/battle-tutorial.cjs`: cinco pasos en orden, pausas explícitas, rechazo de acciones ajenas al paso y victoria.
- Navegador con API simulada: recorrido completo de cinco pasos hasta la victoria, HP del rival 125 → 71 → 0, reinicio y selección de escenario. Sin errores JavaScript durante ese recorrido. No se modificaron partidas reales.
- Revisión a 1440 × 1080 y 390 × 844: guía, campo, ataques, transición entre explicaciones y ausencia de desplazamiento horizontal. Se restauró el viewport después de las pruebas.
- Turno real del controlador PHP probado con una sesión temporal aislada y sin base de datos: debilitamiento, entrada de reemplazo y cancelación del ataque pendiente del Pokémon debilitado.
- Se verificó la sintaxis PHP y JavaScript. Las páginas temporales de prueba se retiraron. No se comenzó una partida en la cuenta real del usuario.

## Referencias

Skills locales: frontend-design y ui-ux-pro-max. Consulta del catálogo oficial con skill-installer: sin una skill específica de juegos en el catálogo consultado.

Referencias comunitarias de diseño consultadas, sin instalar ejecutables:

- https://github.com/fcsouza/agent-skills/blob/main/plugins/game-dev/design/ui-ux-game/SKILL.md
- https://github.com/fcsouza/agent-skills/blob/main/plugins/game-dev/design/game-design-fundamentals/SKILL.md
- https://gameaccessibilityguidelines.com/full-list/
- https://www.nngroup.com/articles/onboarding-tutorials/
