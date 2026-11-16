Base de datos de PokéMarket

[Importa schema.sql](schema.sql) en una base vacía de MySQL o MariaDB. El archivo crea las 20 tablas para jugar. Las cuentas y el progreso se generan al usar el juego.

## Qué guarda cada tabla

| Sección       | Tabla                  | Contenido                                                                  |
| ------------- | ---------------------- | -------------------------------------------------------------------------- |
| Cuentas       | `usuarios`             | Identidad del entrenador y configuración de acceso                         |
| Cuentas       | `codigos_cuenta`       | Códigos temporales de verificación y recuperación, almacenados como hashes |
| Colección     | `coleccion_pokemon`    | Pokémon que posee cada entrenador                                          |
| Colección     | `equipos`              | Equipos guardados de cada entrenador                                       |
| Colección     | `miembros_equipo`      | Pokémon de cada equipo y su posición                                       |
| Economía      | `carteras_usuario`     | Saldo de monedas, versión del progreso y regalo de bienvenida              |
| Economía      | `movimientos_monedas`  | Historial de compras, premios y otros cambios de saldo                     |
| Hogar         | `inventario_comida`    | Alimentos disponibles por entrenador                                       |
| Hogar         | `cuidado_pokemon`      | Energía, vínculo, hábitat y última comida                                  |
| Aventura      | `escenarios_usuario`   | Escenarios desbloqueados                                                   |
| Aventura      | `ofertas_apuesta`      | Ofertas temporales para apostar en batallas                                |
| Aventura      | `rondas_apuesta`       | Estado guardado de las batallas con apuesta                                |
| Exploración   | `progreso_exploracion` | Misiones, suministros y álbum de estampas                                  |
| Progresión    | `progreso_pokemon`     | Experiencia y movimientos de cada compañero                                |
| Progresión    | `experiencia_combate`  | Premios de experiencia registrados por combate                             |
| Progresión    | `habilidades_pokemon`  | Habilidad activa de cada compañero                                         |
| Multijugador  | `busquedas_duelo`      | Jugadores disponibles y su alineación                                      |
| Multijugador  | `duelos_jugadores`     | Invitaciones y estado de los duelos entre jugadores                        |
| Multijugador  | `amistades`            | Solicitudes y amistades aceptadas                                          |
| Mantenimiento | `migraciones_bd`       | Registro de cambios de datos aplicados por herramientas                    |

## Actualizar una base existente

Detén los servicios PHP y deja iniciado MySQL. Ejecuta `C:/xampp/php/php.exe tools/setup.php` desde la raíz con tu `.env` habitual. El instalador actualiza las tablas históricas y renombra las que cambiaron de nombre, conservando sus filas. Para instalaciones nuevas solo hace falta el esquema completo.
