# Explorar: Pradera del Alba

Primera región jugable de PokéMarket, con un mapa isométrico 2.5D original. No es 3D real ni un mundo multijugador sincronizado. El renderizador Canvas no requiere dependencias ni servicios de mapas; los microservicios PHP conservan las reglas y MySQL guarda el progreso por cuenta.

## Cómo jugar

Entra en **Explorar** desde la navegación. La primera vez aparece una guía de tres pasos, que también puede abrirse desde «Cómo explorar». Haz clic para caminar o enfoca el mapa y usa flechas/WASD. En móvil hay una cruceta. El panel de expedición muestra tiempo de movimiento, superficie descubierta y misiones. El botón de regreso al campamento es gratis incluso sin tiempo.

La hierba alta nueva puede generar encuentros de una de las 15 especies básicas que aún no viva en tu Hogar. No hay señales con encuentros garantizados. Correr consume su duración real, hace ruido y reduce la probabilidad de encuentro y captura. El mapa y el minimapa ocultan terreno desconocido. Consulta [expedición salvaje](../expedicion.md) para fórmulas, probabilidades, límites y recuperación.

- 3 Poké Balls cuestan 120 monedas. Se muestra el precio antes de comprarlas. No hay compra automática.
- Cada lanzamiento consume una Poké Ball. La probabilidad depende del potencial de la especie y del ruido; cada fallo resta 8 puntos hasta un mínimo del 10%. Tres fallos hacen huir al Pokémon.
- La exploración está limitada por el tiempo de movimiento. Puedes seguir capturando mientras tengas Poké Balls y un encuentro activo.
- Dejar libre al Pokémon no consume monedas ni Poké Balls.
- Los encuentros nuevos vencen a los tres minutos; el diálogo muestra los segundos restantes y se recupera al recargar mientras siga vigente.
- Las capturas pegan estampas, sin añadir Pokémon al Hogar. Completar categorías desbloquea los paquetes de premios de las hojas 21 y 22; Leyendas entrega a Rayquaza al Hogar.
- El catálogo conserva la compra garantizada; explorar supone gastar consumibles con un resultado incierto y un límite diario.

La música ambiental local del Hogar se reutiliza a volumen bajo, se activa de forma voluntaria y se pausa cuando la página no está visible. Las animaciones respetan movimiento reducido. Los diálogos usan `dialog` nativo, mantienen el foco y ofrecen controles con texto.

## Instalación y arquitectura

Ejecuta `C:\xampp\php\php.exe tools/setup.php` para aplicar la migración aditiva `005_exploracion.sql`. No elimina cuentas, monedas ni compras.

- `explorar.php`: página y controles semánticos.
- `frontend/assets/css/explorar.css`: composición adaptable y controles del mapa.
- `frontend/assets/css/expedition.css`: paleta compartida con la Pokédex, paneles de recursos y adaptación móvil.
- `ExpeditionRules.php`: tiempo, misiones, visión, rastreo, ruido y probabilidad de captura.
- `frontend/assets/js/explore-world.js`: proyección isométrica, dibujo del mapa, cámara y animación.
- `frontend/assets/js/explore.js`: interacción, tutorial, recuperación y peticiones.
- `ExplorationMap.php`: terreno y rutas autoritativas, compartidos con la vista mediante API.
- `ExplorationService.php`: estado persistente, suministros y capturas.
- `ControladorExploracion.php`: sesión y respuestas HTTP.

GET `/exploration` devuelve mapa, estado y cartera. POST `/exploration/{travel,supplies,capture,leave,tutorial,observe,mission,album}` exige sesión, CSRF, versión entera e identificador de solicitud. El servidor calcula las rutas hacia un destino mediante BFS. La caminata con teclado o cruceta adelanta la animación y agrupa hasta 12 pasos contiguos por solicitud. El servidor comprueba adyacencia, colisiones, tiempo, versión y encuentros antes de confirmar cada tramo. La cola tiene un límite y las acciones de captura, compras y recompensas esperan la confirmación. Si el servidor corta la ruta por un encuentro o rechaza el tramo, el mapa vuelve a la posición confirmada.

Las mutaciones bloquean primero la cartera y después el estado de exploración, dentro de una transacción. Las últimas 24 solicitudes conservan su resultado para reintentos idempotentes. Una versión evita sobrescribir progreso de otra pestaña. Una clave monetaria única protege además el cobro de suministros. El cliente guarda la solicitud pendiente en sessionStorage por usuario y la recupera tras un error o recarga.

## Validación

`npm test` comprueba terreno, accesibilidad de todos los avistamientos, colisiones, proyección reversible y controles existentes. `npm run test:integration` añade MySQL y API: gastos atómicos, repetición de solicitudes, versiones antiguas, saldo insuficiente, captura, fallo, huida, duplicados, límite diario y creación del primer equipo. Las pruebas usan cuentas sintéticas y eliminan exclusivamente sus propios datos.

La actualización de expedición salvaje pasa 151/151 comprobaciones de la suite completa. Incluye reglas puras y pruebas MySQL/API de resistencia, recuperación, recursos, capturas, versiones e idempotencia. La revisión visual de esta actualización no pudo ejecutarse porque el uso local del navegador estaba desactivado en Work. Las pruebas visuales históricas descritas más abajo corresponden al escenario anterior.

Dirección visual actual: Outfit y DM Sans, fondo azul noche #0b1428, paneles #16233b, coral #ff9b73, amarillo #ffe16a, verde #a2e5a2 y azul #84ceff. El mapa es la pieza principal; mochila y expedición quedan a un lado en escritorio y debajo en móvil. Las imágenes de especies proceden del repositorio público de sprites de PokéAPI.

## Histórico: refinamiento de movimiento y escenario anterior

El ritmo por celda pasa de 100 ms a 55 ms al caminar y 32 ms al correr. «Correr» permite activarlo en escritorio o móvil; Shift lo activa temporalmente con el mapa enfocado. Mantener una dirección continúa por tramos de hasta tres celdas, deteniéndose en avistamientos y obstáculos. Soltar la tecla o el control táctil, cambiar de pestaña o perder el foco detiene nuevas órdenes; el tramo ya autorizado se completa y guarda. Un clic durante un recorrido permite marcar el siguiente destino.

El servidor autoriza y devuelve el ritmo, conserva su validación de tiempo de llegada y mantiene la misma versión y transacciones. El renderizador conserva el tiempo sobrante entre segmentos, por lo que no pierde un fotograma en cada casilla. Las nuevas pruebas de entrada cubren continuidad, fin al soltar, cola de destino y ausencia de peticiones solapadas.

El mapa mantiene terreno y colisiones para conservar las posiciones existentes, pero renueva su presentación: suelo continuo con textura, costas, vegetación de distintas alturas, flores, rocas, mariposas, puente con barandas y entrenador con orientación y animación de marcha. Un minimapa muestra posición y avistamientos. El suelo se dibuja una vez en una superficie interna y los árboles reutilizan un atlas de 14 variantes: no se recalculan sus gradientes en cada fotograma. Las imágenes HD de los encuentros usan el mismo cargador del Hogar y de batalla.

En la prueba visual de escritorio, el recorrido campamento → Ribera del puente en modo correr tardó unos 0,75 segundos entre el clic y el diálogo de encuentro, en el equipo local de prueba; no es una garantía para otros dispositivos o redes.

La suite final del refinamiento pasó 111/111 comprobaciones, incluidas 40 de exploración con MySQL/API. Los controles direccionales táctiles tienen un mínimo de 44 × 44 px. Las pruebas visuales de los controles nuevos y el minimapa se realizaron en escritorio y móvil.

## Revisión de movimiento y presentación

El teclado responde desde la vista de exploración sin exigir foco exacto en el canvas. Campos de texto, selectores y diálogos abiertos conservan sus teclas. Un clic y los controles táctiles enfocan el mapa; soltar la tecla, perder foco o salir de la pestaña detiene la marcha continua. Los destinos continúan autorizándose y guardándose en servidor antes de recorrer la ruta.

Se añade vista completa de la región, arrastre de cámara, entrenador más grande y señalado como Tú, variación de terreno y dos referencias visuales (refugio y ruinas) sobre celdas ya bloqueadas. No se alteran los límites de captura, terrenos transitables ni encuentros guardados. El botón Centrar recupera el seguimiento del personaje; caminar también recupera la cámara. El audio se conecta al reproductor compartido del juego.

## Actualización: álbum y misiones (13 de noviembre de 2026)

La exploración empieza con 60 segundos de movimiento y pausa el consumo al detenerse. Preparar equipo en el campamento da 20 segundos diarios; cartografiar 40 casillas nuevas da 20; cada batalla normal terminada da 60. Regresar al campamento es gratis y no recarga automáticamente el reloj.

El álbum tiene veinte hojas de cinco especies y dos hojas finales de premios por categoría. Avistar descubre la silueta; capturar pega una estampa. Las capturas históricas del diario aportan sus estampas y se conservan los compañeros adquiridos anteriormente. Los premios se validan en el servidor y solo se entregan una vez por categoría.

El personaje usa una hoja original prerenderizada en 3D. Véase [entrenador y reacciones](ENTRENADOR_3D.md). Los avistamientos cercanos alternan paseo con pausas ocultos en un arbusto que se agita. El cuaderno permite pasar páginas con movimiento reducido respetado.

## Premios del cuaderno

Las veinte hojas de estampas se conservan. Las hojas 21 y 22 reúnen cuatro categorías por rareza; cada captura aporta su estampa a la categoría correspondiente. Ya no se entregan compañeros por cada hoja. Los compañeros recibidos anteriormente se conservan.

| Categoría | Premio único |
| --- | --- |
| Básica | 500 monedas y 1 tarjeta de cambio de usuario |
| Media | 600 monedas, 2 Poké Balls, 1 Veloz Ball y 3 cestas de bayas |
| Avanzada | 800 monedas, 2 Super Balls, 1 Ultra Ball, 2 currys, 3 pokochos y 1 tarjeta |
| Leyendas | 1000 monedas y Rayquaza a nivel 1 |

Se requieren todas las estampas de esa categoría, obtenidas al capturar. Los avistamientos y las compras no cuentan. Los premios se validan y entregan juntos en el servidor; las solicitudes repetidas no duplican objetos ni monedas. Si la mochila no tiene espacio, el premio sigue pendiente. Si Rayquaza ya está en el Hogar, se conservan su nivel y cuidado y se entregan las monedas.

El cambio de usuario consume una tarjeta únicamente al guardar un nombre diferente y disponible. No hay renovación mensual. Las tarjetas aparecen en el perfil y se ganan en las categorías Básica y Avanzada.

Para una base existente ejecutar una vez `database/migrations/014_recompensas_cuaderno.sql`, o ejecutar `php tools/setup.php`, que detecta la columna antes de añadirla. No elimina cuentas ni progreso.
