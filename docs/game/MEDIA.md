# Imágenes y animaciones de PokeMarket

La interfaz utiliza PokéAPI para los datos y su repositorio de sprites para los recursos visuales. No depende de una API externa de combate.

El Catálogo de compra y el Hogar usan ahora el índice oficial local descrito en `ECONOMY.md`. Sus fichas se abren con las seis estadísticas sin una descarga adicional. La preparación de batallas y la selección de equipos también usan esos datos locales. El escenario `frontend/assets/img/pokemon-home.png` fue creado para este proyecto con imagegen; el icono de curry es un SVG propio.

- Primero muestra HOME a 512 × 512 y después revela la animación WebP HD verificada. Nunca amplía un GIF pixelado mientras descarga ni cuando falla la red. Si no puede descargar la animación, conserva la imagen nítida.
- El inventario combina seis conjuntos de PokéAPI: Champions, Sword/Shield, Scarlet/Violet, Brilliant Diamond/Shining Pearl, Legends: Arceus y Legends: Z-A. La auditoría del 24 de octubre de 2026 encontró fuentes frontales y posteriores para las 1.025 especies base. Esto no implica cobertura de todas las formas alternativas. Se utiliza la fuente verificada de cada especie, sin sondear rutas inexistentes.
- Se descargaron y decodificaron muestras reales: Spearow (#21, BDSP) 512 × 512, 24 fotogramas; Venonat (#48, Scarlet/Violet) 512 × 512, 60 fotogramas; Pecharunt (#1025, Scarlet/Violet) 512 × 512, 50 fotogramas. La cobertura total se verifica contra los inventarios del proveedor; no se descargan todas las animaciones al entrar. El aspecto y la iluminación pueden variar entre juegos.
- El control «Animaciones Pokémon» guarda la preferencia local. Movimiento reducido usa imágenes estáticas. Las miniaturas de equipos también son estáticas para limitar descargas.
- Los efectos de ataques son propios, generados con Web Animations API: partículas por tipo, avance, impacto y actualización de vida. No son animaciones oficiales específicas de cada movimiento. Los resultados de combate los sigue calculando trainer-service.
- El catálogo abre detalles con `?view=card`: omite la descarga de movimientos de combate y usa una caché independiente. Las consultas de trainer-service conservan los movimientos. Los datos recibidos de PokéAPI también se guardan para reutilizarlos; un fallo en la descripción opcional no bloquea la ficha.
- El orden predeterminado del catálogo prioriza Champions y después las demás animaciones HD por número de Pokédex, a partir del inventario `backend/pokemon-service/config/sprite_catalog.json`. Se aplica al catálogo completo antes de paginar. `order=pokedex` permite consultar el orden numérico. El inventario incluye vistas de espalda verificadas. Los detalles antiguos en caché y las batallas recuperadas actualizan sus referencias de medios sin recalcular combate ni pedir datos externos. El inventario se renueva con `C:\xampp\php\php.exe tools/update_sprite_catalog.php`; su versión invalida automáticamente el orden guardado en caché. El constructor consulta Git Trees por juego para evitar el límite de 1.000 archivos de GitHub Contents; exige una respuesta completa y solo reemplaza el inventario al terminar todos los proveedores.
- Con `view=card&progress=1`, el servicio transmite NDJSON: 0% al iniciar, 10% al consultar los datos, 55% al recibir el Pokémon, 85% al completar la descripción opcional y 100% al preparar y guardar la ficha. Son etapas completadas, no una estimación de segundos restantes. Si la ficha está guardada, pasa directamente de 0% a 100%.
- PokéAPI ofrece gritos de juego a través de `cries.latest` y `cries.legacy`, alojados en https://github.com/PokeAPI/cries. Su repositorio declara cobertura de generaciones 1 a 9.
- La arena usa fondos locales de bosque y pradera de Pokémon Showdown. Se eliminaron los pitidos sintetizados y los gritos automáticos; «Música» activa una pista de batalla opcional, al 12% de volumen y apagada al entrar. Las fuentes de los recursos figuran en `frontend/assets/audio/README.md`. Véase `BATTLE_DESIGN.md` para los modos y la guía de cinco pasos.
- `frontend/assets/css/pokedex.css` centraliza el acabado visual compartido. UI/UX Pro Max y frontend-design guiaron el diseño; Framer y 21st.dev se usaron como referencias, sin migración de plataforma ni componentes React instalados.

Fuentes consultadas:

- https://github.com/PokeAPI/sprites
- https://github.com/smogon/pokemon-showdown-client/blob/master/play.pokemonshowdown.com/src/battle-animations-moves.ts
- https://www.framer.com/
- https://21st.dev/

Verificación local: `node tests/media-ui.cjs` y `node --check` sobre los scripts modificados. El catálogo y los efectos de batalla se revisaron en navegador con respuestas simuladas, sin alterar cuentas ni partidas reales.

## Intro integrada de bienvenida

`frontend/assets/img/adventure/Intro.mp4` es el archivo aportado por el usuario. La intro se presenta a toda la ventana, sin controles nativos, línea de tiempo, adelanto ni menú contextual. La página ofrece sonido, pausa y salto; al terminar se puede repetir o continuar con la guía.

Antes de iniciar se descarga el archivo completo con `fetch` y se crea una URL Blob local. Esto evita depender de la red durante la reproducción; la decodificación sigue dependiendo del navegador y del dispositivo. Mientras se prepara se muestra una pantalla de carga. Repetir reutiliza el mismo archivo en memoria. Saltar cancela la descarga pendiente y salir libera la URL; los errores y el bloqueo de autoplay permiten continuar con la bienvenida. La intro no reclama monedas.

`tests/welcome-media.cjs` verifica la descarga completa antes del inicio, ausencia de controles de adelanto, repetición sin otra descarga, rechazo de autoplay, salto y liberación de recursos.

La intro intenta comenzar con audio habilitado y ofrece «Silenciar». Si el navegador bloquea autoplay con sonido, presenta «Comenzar con sonido»; no cambia silenciosamente a reproducción muda. Al ocultar la pestaña pausa temporalmente y al volver reanuda desde el mismo punto, conservando el sonido elegido. Una pausa manual o una intro terminada no se reanudan automáticamente. Los eventos `pagehide`/`pageshow` permiten recuperar la reproducción cuando el navegador restaura la página desde su caché de navegación; se retiran los listeners al cerrar la bienvenida.

La prueba de medios incluye sonido inicial, silenciar, regreso a la pestaña, pausa manual, restauración de página y descarga que termina en segundo plano.

## Escenas animadas del recorrido

Los pasos 2, 3 y 4 muestran `bienvenida-companero.mp4`, `bienvenida-cuidados.mp4` y `bienvenida-batalla.mp4`, aportados por el usuario en `frontend/assets/img/adventure/`. Se reproducen silenciados y en bucle en el panel lateral, sin controles de adelanto. El recorte central adapta los videos verticales al panel y conserva una imagen de respaldo de alta resolución.

La primera escena se precarga durante el paso 1. Al reproducir una escena se prepara la siguiente. Cada archivo se descarga completo antes de empezar y se reutiliza al volver con «Anterior». Los videos se detienen al cambiar de paso; ocultar la pestaña pausa y volver reanuda, respetando las pausas manuales. Movimiento reducido conserva el respaldo estático. Las escenas no muestran botones para pausar o animar; mantienen la pausa y reanudación automática al cambiar de pestaña. Cerrar el recorrido cancela descargas y libera todas las URL Blob. La prueba `welcome-media.cjs` cubre las tres escenas, navegación, caché, reproducción y entrega del regalo al final.

## Celebración del regalo

Tras confirmar `onboarding/complete`, se muestra una tarjeta horizontal compacta: Pikachu a la izquierda y felicitación, 1.000 monedas, tres cestas de bayas y botón de elección a la derecha. Se eliminó la columna alta de medallón, título largo y mensajes repetidos. En móvil se apila con una ilustración más baja; en pantallas de poca altura se priorizan el regalo y el botón. Se mantiene desplazamiento como respaldo de accesibilidad para ventanas extremadamente pequeñas, sin ocultar contenido.

La imagen `frontend/assets/img/adventure/pikachu-welcome.png` procede de [PokeAPI/sprites, HOME #25](https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/home/25.png) y se sirve localmente. El fondo usa la pradera aportada por el usuario. La entrada de Pikachu y el confeti son breves y respetan movimiento reducido. La celebración se ejecuta después de confirmar el regalo; un error mantiene la opción de reintentar.

Revisión en navegador con datos simulados, sin modificar cuentas: 1280×720, 1024×600, 360×640, 320×568 y 896×414. En los cinco tamaños la tarjeta no tiene desbordamiento horizontal ni vertical y el botón completo permanece dentro de la ventana. La consola de la vista de prueba no mostró errores. `welcome-media.cjs` sigue verificando confirmación del regalo, fallo, reintento y movimiento reducido.

## Fuentes contrastadas para ampliar animaciones

Se consultaron el [repositorio de PokéAPI](https://github.com/PokeAPI/sprites), su [Sprite Finder](https://pokeapi.github.io/sprites/), [Pokémon Paraíso](https://www.pkparaiso.com/xy/sprites_pokemon.php) y [PokemonPokedex](https://www.pokemonpokedex.com/pokemon-gifs). La documentación y el inventario de PokéAPI permitieron cubrir todas las especies base sin depender de scraping ni introducir una API adicional. Los derechos y créditos de los recursos corresponden a sus titulares y al repositorio de origen.

Pruebas específicas: `tests/sprite-inventory.php` verifica las 1.025 fuentes frontales/posteriores y los dos casos de la captura del usuario; `tests/media-ui.cjs` comprueba imagen inicial nítida, carga HD, fallo de red sin degradación, cambios de preferencia, espalda y catálogo estático. Revisión en navegador: Spearow y Venonat cargan WebP de 512 × 512 en el Hogar con sus fuentes nuevas.

Verificación final del refinamiento: 111/111 comprobaciones de la suite completa, incluidas 40 verificaciones de exploración con MySQL/API. Se verificaron el movimiento con controles táctiles, el minimapa y la ausencia de desbordamiento horizontal a 390×844 y 320×568, además de las fuentes HD reales en el Hogar.
