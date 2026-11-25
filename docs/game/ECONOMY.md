# Economía y Hogar Pokémon

## Ciclo de juego

Cada cuenta nueva recibe 1.000 monedas y 3 cestas de bayas al terminar la bienvenida o al omitir la intro o la guía. El servidor acredita el regalo una sola vez por cuenta. La primera compra puede ser de cualquier categoría, siempre que alcance el saldo, y crea automáticamente «Mi primer equipo» con ese Pokémon. Primeros compañeros es una recomendación, no un requisito. Una especie solo puede comprarse una vez por usuario.

El catálogo contiene las 1.025 especies base de PokéAPI. No mezcla formas alternativas como compras independientes. Las categorías y filtros se aplican al índice completo antes de paginar. La ordenación predeterminada conserva Champions, Sword/Shield y después sprites estándar. La calidad visual no cambia el precio.

Categorías: primeros compañeros (selección de iniciales, Pikachu y Eevee); exploradores (menos de 400 puntos base); especialistas (400–519); élite (520 o más); legendarios/singulares (banderas oficiales de la especie). La prioridad de clasificación es rareza, inicial y estadísticas.

El precio suma una base de categoría, tramos de diez puntos estadísticos sobre el umbral de esa categoría y un único recargo por una habilidad funcional: 25 monedas en iniciales o 40 en otras categorías. Si no hay habilidad compatible, el recargo es cero. No depende de cuántas habilidades posibles tenga la especie; no se cobra por activación ni cambio. La fórmula está centralizada en `MarketCatalog.php`; el diálogo muestra el nombre de la habilidad, el desglose y el saldo posterior. Los compañeros existentes reciben la habilidad sin cobro retroactivo. Consulta [habilidades](../habilidades.md) para efectos, alternativas y persistencia.

## Batallas y cuidado

La sección **Explorar** ofrece una vía adicional para conseguir especies básicas con Poké Balls y encuentros con probabilidad visible. El tiempo de movimiento limita el recorrido. El catálogo mantiene la compra garantizada. Ver [reglas de exploración](EXPLORACION.md).

- Victoria: 180 monedas más 70 por cada miembro adicional del equipo participante.
- Derrota: 0 monedas.
- Práctica y abandono: sin monedas.
- Al terminar un combate real, cada participante consume 12 de energía y gana 2 de vínculo por victoria o 1 por derrota. Se aplica una sola vez junto al premio.
- Bayas: 40 monedas, +35 energía y +5 vínculo.
- Curry: 90 monedas, +75 energía y +10 vínculo.
- Energía y vínculo tienen tope 100. Con energía completa no se puede gastar comida.
- Energía de al menos 60 concede +5% a ataque/defensa físicos y especiales. Cada 20 de vínculo añade +1%, hasta un bonus total de +10%. Se calcula en servidor al iniciar el combate.
- No hay pérdida de energía por ausencia, muerte por hambre ni bloqueo de la batalla por energía baja. Estos límites evitan dejar a un usuario sin forma de obtener monedas.

El Hogar permite seleccionar un residente, alimentarlo y asignarlo a pradera, bosque o laguna. Cada escenario muestra hasta seis compañeros juntos, con espacios reservados para imagen y nombre. En pantallas pequeñas y en Laguna se distribuyen en dos filas de tres. Seleccionar al sexto conserva visibles los otros cinco. Las colecciones mayores se recorren en grupos de seis con Anterior/Siguiente; seleccionar un compañero de otro grupo abre ese grupo. Las pestañas y los controles quedan fuera del paisaje para no tapar residentes.

El combate utiliza estadísticas y movimientos que el servicio de Pokémon publica por HTTP a partir de su índice compilado. Se seleccionan hasta cuatro movimientos ofensivos de potencia fija 30–100 con precisión base de al menos 90, priorizando tipos propios y diversidad. Se respeta la distinción físico/especial. Es una simulación simplificada: no implementa PP ni todos los efectos secundarios de los juegos. Forcejeo sirve de respaldo si no hay movimientos elegibles. Los rivales se eligen cerca del potencial base medio del equipo.

## Persistencia y reinicio aplicado

`003_economia_hogar.sql` agrega carteras, historial de monedas, inventario y cuidado. Las operaciones monetarias bloquean la cartera con `FOR UPDATE` dentro de transacciones. Una clave única por bienvenida, compra y batalla evita duplicar esos movimientos. Los precios, premios y bonus se calculan en servidor; se ignora cualquier precio enviado desde el navegador. La antigua ruta POST `/collection` ahora también cobra. DELETE `/collection/:id` rechaza el borrado de compras.

El reinicio solicitado se ejecutó con `tools/migrate_economy.php`: se retiraron 29 entradas antiguas de colección y sus equipos. Se conservaron las cuentas y contraseñas. Antes se guardó el progreso en `.local-backups/progreso-antes-economia-20261023-220529.json`, excluido de Git y denegado por Apache mediante `.htaccess`. El respaldo no contiene la tabla de usuarios. La migración tiene una marca única: ejecutarla de nuevo no vuelve a vaciar el progreso. Las sesiones de combate anteriores a esta economía dejan de ser válidas; las sesiones de usuario se conservan.

## Datos y recursos

`tools/build_market_catalog.py` crea `backend/pokemon-service/config/market_catalog.json` a partir de los CSV oficiales de [PokéAPI](https://github.com/PokeAPI/pokeapi/tree/master/data/v2/csv): pokemon, pokemon_stats, pokemon_species, abilities, ability_names, pokemon_abilities, pokemon_types, types, moves, move_names y pokemon_moves. Descargados el 23 de octubre de 2026. Los CSV de entrada están excluidos de Git; se conserva el índice compilado y el constructor.

La idea de alimentar y compartir comida se inspira en el [Poké Campamento oficial](https://swordshield.pokemon.com/es-mx/gameplay/pokemon-camps/). Los efectos sobre energía, vínculo y estadísticas descritos aquí son reglas propias de PokéMarket.

`frontend/assets/img/pokemon-home.png` es una ilustración creada para este proyecto con la skill imagegen. Los Pokémon animados y la baya mantienen las fuentes de `MEDIA.md`; el icono de curry es un SVG propio.

## Dirección visual

Skills aplicadas: frontend-design, UI/UX Pro Max e imagegen. Dirección: tienda de compañeros de aventura con una moneda dorada legible y un hogar ilustrado vivo. La consulta UI/UX específica de gaming sugirió profundidad y recursos inmersivos; se adaptó a los sprites existentes sin introducir WebGL ni una estética de esports.

Tokens: fondo azul bosque #101f27, panel #192d35, oro #edcc77, verde #386c58, texto #edf2ef y secundario #a5b4b0. Outfit para títulos de personalidad amable y DM Sans para lectura y controles. El encabezado, las monedas y la navegación son compartidos por Catálogo, Hogar, Equipos y Batalla. La jerarquía es oferta inicial → categorías → filtros → Pokémon → precio y acción. La imagen tiene espacio estable y conserva gran tamaño. Los movimientos responden a acciones: apertura de compra, confirmación, saldo y cambio de hábitat. Se respeta movimiento reducido.

## Validación

`C:\xampp\php\php.exe tests/economy-integration.php` pasó 33 verificaciones contra MySQL y los microservicios reales, con una cuenta temporal eliminada al acabar. Incluye bienvenida única, ruta heredada de compra, precio manipulado ignorado, saldo insuficiente, duplicados, equipo inicial, alimentación, hábitat, bonus, premio idempotente, turno HTTP finalizado, inicio real del combate y ausencia de premio al abandonar.

También pasaron las regresiones de imágenes, progreso, práctica y tutorial existentes, y las revisiones de sintaxis PHP/JavaScript. Las pruebas visuales usan una página temporal con respuestas simuladas para no gastar monedas ni comprar en cuentas reales; esa página se elimina al terminar.

Revisión visual realizada a 1440 px, 1024 px y 390 px: compra inicial y sucesiva, confirmación y saldo, detalle con seis estadísticas, búsqueda, filtros y orden por precio, estado sin resultados, alimentación, compra de curry y cambio de hábitat. Sin errores de JavaScript en los recorridos revisados ni desbordamiento horizontal en móvil. Se guardaron capturas del catálogo, del jardín y del cuidado. Se restauró el tamaño del navegador y se retiró la página temporal.

## Apuestas y expansiones del Hogar

El Catálogo siempre muestra imágenes estáticas de alta calidad, incluidos los detalles. Las preferencias de animación siguen aplicándose en Hogar, equipos y batalla.

El duelo de apuestas es 1 contra 1. Ofrece cuatro entradas por usuario durante diez minutos: 100; 400/500/600; 1000/1250/1500; 2000/3000/5000 monedas. Los importes se eligen en el servidor; actualizar antes de vencer conserva las mismas ofertas. El rival se genera cerca del potencial del jugador y se aplica un factor creciente: Aprendiz 0,90; Entrenador 1,05; Veterano 1,20; Maestro 1,40. Este factor afecta estadísticas y salud; los tipos y decisiones siguen influyendo en el resultado.

La entrada se descuenta al comenzar. Victoria: retorno 2×entrada, ganancia neta +entrada. Derrota o abandono: retorno cero, pérdida neta −entrada. No se suma el premio ordinario. La entrada, el cierre y el cuidado se registran con transacciones y claves únicas. El duelo se guarda en MySQL; cerrar sesión o recargar no elimina la apuesta pendiente. Una versión de partida rechaza escrituras antiguas de otras pestañas. No hay dinero real.

Todo el Hogar es gratuito y muestra a todos los compañeros. Pradera cuesta 500, Bosque 1500 y Laguna 2500 monedas, con desbloqueo permanente por cuenta. Los nuevos compañeros pueden descansar en la pradera del panorama general; comprar la expansión habilita entrar a su escenario y asignar compañeros desde el panel de cuidado. No se compra dos veces un escenario. Ver [imágenes de la aventura](../../frontend/assets/img/adventure/README.md) y [música](HOGAR_MUSICA.md).

Migración aditiva: `004_aventura.sql`, incluida en `tools/setup.php`. No vacía colecciones, cuentas ni carteras.

## Espacios de los residentes

`frontend/assets/js/home-layout.js` contiene la geometría pura de los espacios del Hogar, Pradera, Bosque y Laguna. Calcula cajas completas para imagen y etiqueta según el tamaño real del paisaje, con separación suficiente para los sprites HD. El Hogar no vuelve a reutilizar una de cuatro posiciones para el quinto residente. `home.js` conserva los nodos existentes por Pokémon, aplica el plan de colocación y recalcula al cambiar el tamaño mediante ResizeObserver. El orden de incorporación se desempata por identificador de colección para conservar la posición al recargar.

Las posiciones cambian sin animar un desplazamiento que atraviese a otros residentes. Los nombres largos se truncan dentro del espacio y el botón conserva el nombre completo en su etiqueta accesible. La Laguna encuadra la orilla en móvil. No se modifican propiedad, monedas ni hábitats guardados.

Validación del 24 de octubre de 2026: 114/114 comprobaciones de la suite completa. `tests/home-layout.cjs` comprueba cuatro escenarios y nueve tamaños, cajas sin intersecciones ni desbordamiento, área táctil, orilla de Laguna y navegación completa con 37 residentes. La regresión adicional verifica seis espacios y selección del sexto conservando los otros cinco, en los cuatro escenarios y tres tamaños. La versión anterior se revisó en navegador; la revisión visual final de esta corrección no pudo ejecutarse porque el navegador local está deshabilitado en Work.

## Elección del Pokémon inicial

En Batalla, después de elegir equipo se muestran sus compañeros y se exige elegir quién comienza. Duelo rápido y apuesta usan solamente ese Pokémon. En combate por equipos se coloca al elegido al inicio y se conservan los demás para los cambios durante la batalla, sin alterar el orden guardado de Mis equipos. El servidor valida `pokemon_id` contra los miembros del equipo del usuario antes de iniciar o descontar una apuesta. Los clientes antiguos que omiten el campo conservan el primer miembro como valor predeterminado. La práctica guiada mantiene sus compañeros didácticos.

## Tamaño visual por especie

La altura proviene del campo `height` de [PokéAPI](https://pokeapi.co/docs/v2#pokemon): el constructor local la convierte de decímetros a metros como `altura`. Las 1.025 especies ya incluyen este dato, por lo que el Hogar no hace nuevas peticiones externas para calcular tamaños.

La distribución permite hasta tres columnas y dos filas. Las páginas con pocos compañeros aprovechan posiciones centradas, y siguen siendo visibles hasta seis por grupo.

A petición del usuario se conserva el tamaño familiar de las especies de hasta 1,5 m. Las especies más altas reciben un aumento gradual, de hasta 25%, limitado por su espacio individual. La presencia de un gigante no reduce a los demás compañeros. La base se calcula con los tamaños previos del paisaje (máximo 260 px antes del aumento), y en pantallas pequeñas prevalece el espacio disponible.

En batalla las especies pequeñas conservan su tamaño anterior. El aumento se limita adicionalmente a 20% en el lado rival y 12% en móvil para mantener los puntos de apoyo dentro del campo. Los datos de altura, las estadísticas y los resultados no cambian.

Las pruebas recorren las 1.025 especies y los cuatro escenarios con grupos de uno a seis, verificando que los pequeños no se reduzcan por la presencia de gigantes, que el aumento sea moderado y que no existan superposiciones ni recortes.
