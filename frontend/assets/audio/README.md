# Recursos de la arena

Recursos descargados del cliente público de Pokémon Showdown el 23 de octubre de 2026:

| Archivo local | Fuente |
| --- | --- |
| `battle-theme.mp3` | https://play.pokemonshowdown.com/audio/oras-trainer.mp3 |
| `../img/battle-forest.jpg` | https://play.pokemonshowdown.com/sprites/gen6bgs/bg-forest.jpg |
| `../img/battle-meadow.jpg` | https://play.pokemonshowdown.com/sprites/gen6bgs/bg-meadow.jpg |

La pista es música de combate de Pokémon ORAS. Los fondos son escenarios de encuentro publicados por el cliente Showdown. No son recursos originales de PokeMarket. Se conservan localmente para evitar solicitudes externas durante la práctica. La música requiere que el usuario la active con el icono de sonido. Los efectos de ataque son muestras locales; no se reproducen gritos ni tonos sintetizados.

La música de los escenarios del Hogar usa pistas CC0 distintas, documentadas en [HOGAR_MUSICA.md](../../docs/game/HOGAR_MUSICA.md).


## Efectos de batalla y transiciones (24 de octubre de 2026)

Los archivos de `effects/` proceden de [Kenney RPG Audio](https://kenney.nl/assets/rpg-audio), [Impact Sounds](https://kenney.nl/assets/impact-sounds) y [Sci-fi Sounds](https://kenney.nl/assets/sci-fi-sounds), todos CC0. Se incluyen los tres archivos de licencia originales. No requieren créditos en la interfaz. Se seleccionaron 15 muestras, con 18 perfiles de timbre/velocidad según el tipo de ataque. Son efectos temáticos adaptados al juego, no grabaciones oficiales de los movimientos Pokémon.

`battle-meadow.mp3`: https://play.pokemonshowdown.com/audio/xy-trainer.mp3, pista de entrenador de Pokémon XY publicada por Showdown. Comparte las limitaciones de procedencia de la pista ORAS; no es un recurso CC0 ni original de PokéMarket.

`audio-mixer.js` coordina música del Hogar y batalla: fundido de 900 ms, preparación de la siguiente pista antes de retirar la anterior, bucle nativo y cancelación de reproducciones antiguas. La música conserva su posición al cambiar de ventana y al silenciar/reactivar. El bloqueo de reproducción automática no cambia la preferencia del usuario; se reintenta con una interacción. El reproductor reside en el documento exterior del juego y sobrevive al cambio de vistas internas. Al salir del juego o cerrar sesión se liberan las voces. Las reducciones temporales de volumen se pueden combinar y liberar sin reiniciar la pista ni sobrescribir el volumen de la escena.

Los efectos se precargan al activar sonido, admiten hasta cuatro voces simultáneas, reducen temporalmente el volumen de música y se silencian con el mismo botón. El movimiento reducido no elimina el audio. Al terminar una batalla se desvanece la música y se permite terminar el último efecto.

### Confirmación de compra

`purchase/` contiene seis efectos de [Kenney Interface Sounds](https://kenney.nl/assets/interface-sounds), CC0, con licencia e inventario de fuentes. `purchase-audio.js` comparte el mute del documento exterior y reduce la música al 20 % de su volumen actual durante la presentación. No se mezclan los audios del vídeo con estos efectos: el clip se reproduce siempre silenciado. La presentación solo se activa tras la respuesta de compra confirmada; un fallo de vídeo/audio no permite cobrar de nuevo. Consulta [la ruta y preparación del vídeo](../video/README.md).


## Gritos de selección Pokémon

Fuentes verificadas el 24 de octubre de 2026: [PokéAPI Cries](https://github.com/PokeAPI/cries) ofrece gritos de videojuegos de las generaciones 1–9, identificados por número de Pokémon. Se importaron las 1.025 especies base a `assets/audio/cries/` y se guardó el inventario con SHA-256 por archivo. Las especies/formas con números superiores a 1025 no forman parte de este catálogo. No son grabaciones del anime para todas las especies ni se afirma que todas digan su nombre.

Para Pikachu se prioriza la [variante Pikachu Starter publicada por Showdown](https://play.pokemonshowdown.com/audio/cries/pikachu-starter.mp3); si falla, se usa su grito estándar. Son recursos Pokémon de terceros, no archivos CC0 ni voces originales del proyecto. Las muestras de PokéAPI remiten a Showdown, Veekun y The Sounds Resource en su README.

`tools/sync_pokemon_cries.py` importa el archivo comprimido completo, comprueba cobertura y cabeceras antes de escribir, y genera `pokemon-cries-catalog.js`. Detectó tres MP3 publicados con extensión OGG (25, 808 y 809), que se guardan con la extensión real. Si el servidor de Showdown rechaza el cliente Python, se puede descargar la voz con `Invoke-WebRequest` y pasarla al importador mediante `--pikachu-voice`.

`pokemon-cries.js` se aloja en el documento exterior y las vistas comparten una sola voz. Suena al seleccionar compañero en Hogar, elegir el inicial de batalla, confirmar un cambio de batalla, elegir en Mis equipos o abrir detalles del Catálogo. No suena al renderizar listas ni al cargar la página. Respeta el mute global; se detiene al cambiar de selección, navegar, ocultar la pestaña o salir. La precarga tiene un límite de 16 muestras y no bloquea la selección. Un fallo de audio no altera compras, equipos ni batallas.

## Selección musical y aislamiento de vistas (13 de noviembre de 2026)

- `catalog-theme.ogg`: **Other Center**, Chris Murphy (zesona), [OpenGameArt](https://opengameart.org/content/other-center), CC0. El autor la describe como un bucle creado para un centro de criaturas en un juego de parodia. Se usa para Catálogo, Equipos y menú de Batalla.
- `battle-unova.mp3`: [Pokémon BW, entrenador](https://play.pokemonshowdown.com/audio/bw-trainer.mp3).
- `battle-johto.mp3`: [Pokémon HGSS Johto, entrenador](https://play.pokemonshowdown.com/audio/hgss-johto-trainer.mp3).

Las dos pistas Pokémon adicionales proceden del [índice público de Showdown](https://play.pokemonshowdown.com/audio/), con la misma procedencia y derechos de terceros de las pistas ORAS/XY existentes; no son CC0. La arena alterna dos opciones por paisaje al comenzar un combate. Terminar o abandonar vuelve al ambiente del menú; navegar a Hogar o Explorar selecciona su ambiente.

Una sola pista de música puede sonar. Cambiar de pista pausa la anterior antes de iniciar la nueva y recuerda su posición para regresar. Cada vista recibe un control ligado a su navegación: callbacks de una batalla descargada no pueden cambiar la música o el volumen de otra sección. Silenciar es inmediato y cambiar de ventana no reinicia la pista. Los gritos de selección usan una sola voz, ignoran clics repetidos del mismo Pokémon mientras suena y atenúan temporalmente la música.
