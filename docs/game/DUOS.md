# Combate por equipos con amigos

En Batalla, abre **Combate por equipos** o **Sala con amigos**. El anfitrión elige el formato e invita a amigos agregados.

| Personas por equipo | Total de Pokémon permitido | Pokémon por persona |
| --- | --- | --- |
| 2 | 2, 4 o 6 | 1, 2 o 3 |
| 3 | 3 o 6 | 1 o 2 |
| 4 | 4 | 1 |

La sala muestra las invitaciones pendientes y los Pokémon de cada participante. Cada integrante acepta y confirma el Pokémon inicial y las reservas desde sus propios equipos. Cuando todos completan su selección, el anfitrión busca un rival con el mismo número de personas y Pokémon por persona.

Todos confirman para revelar el orden. Los turnos alternan entre ambos equipos mientras corresponda según el sorteo y se omiten los entrenadores sin Pokémon disponibles. Cambiar voluntariamente consume el turno. Cuando un Pokémon cae, entra la primera reserva disponible automáticamente. Gana el equipo que conserva algún Pokémon.

Es un modo amistoso: no cobra apuestas ni entrega monedas o experiencia. Las selecciones se validan nuevamente al emparejar y las acciones se serializan en la base de datos. Cada ataque incluye la versión de la partida para impedir que un envío atrasado se aplique a una vuelta posterior. La sala vence tras una hora sin actividad.

La preparación también existe en la práctica, contra el sistema y en duelos individuales: los dos humanos confirman; contra el sistema confirma el jugador. El orden sorteado se conserva durante la partida y al recuperarla. Las partidas antiguas ya iniciadas conservan su comportamiento.

Para actualizar una base existente, aplica `database/migrations/016_salas_equipos.sql` después de la migración de dúos o ejecuta `php tools/setup.php`. La migración incorpora a los miembros de las salas anteriores.
