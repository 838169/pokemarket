# Expedición salvaje

Explorar comparte las superficies azul noche de la Pokédex, con acentos coral para riesgo, amarillo para recursos, verde para resistencia y azul para orientación. El terreno usa agua azul y hierba más viva. La dificultad es propia de este juego; no se presenta como una reproducción del sistema de encuentros oficial.

## Reglas

- Los cinco antiguos avistamientos dejan de garantizar encuentros. La API publica `puntos: []`. Al rastrear hierba alta nueva puede aparecer un Pokémon caminando en el mapa, incluyendo rutas largas marcadas con el ratón. Acercarse a él abre la captura; no se abre un diálogo automático al encontrar un rastro.
- La región ocupa 48 × 40 casillas (1.920, cuatro veces su superficie original). Seis casillas de distancia Manhattan alrededor del entrenador revelan el terreno. El mapa y minimapa recuerdan lo explorado; una máscara de niebla con bordes difuminados cubre lo desconocido. Esto es una ayuda visual, no una barrera de seguridad: la generación de encuentros se controla en el servidor.
- Tiempo inicial: 60 segundos de movimiento. Cada casilla consume su duración real: 180 ms caminando o 105 ms corriendo. Detenerse, cambiar de sección y capturar no consumen tiempo. La animación usa el prefijo de ruta autorizado por el servidor.
- Misiones: preparar el equipo en el campamento da 20 segundos una vez al día; descubrir 40 casillas nuevas da otros 20; completar una batalla normal da 60, ganes o pierdas. La práctica no cuenta. Las recompensas se acumulan y cada logro se cobra una sola vez.
- Tras al menos seis pasos desde el encuentro anterior, cada tres casillas nuevas de hierba alta se realiza una tirada: 28% caminando y 10% corriendo. Las últimas 24 casillas recorridas no vuelven a sumar rastreo. Tras 12 casillas nuevas de hierba alta, caminar garantiza un avistamiento.
- Regresar al campamento es gratis incluso con el reloj en cero. El regreso no genera encuentros ni recarga automáticamente el reloj; allí puedes reclamar la misión diaria.
- Con Poké Ball estándar, las probabilidades aproximadas por encuentro de tres lanzamientos son 20%, 10%, 5% y 3%, de común a legendario, antes de aplicar ruido o protección por mala racha. Cada lanzamiento tiene al menos un 1% de probabilidad, y las Poké Balls especiales aplican su multiplicador sobre esa base. El tercer fallo provoca huida. Los encuentros duran tres minutos.
- La tienda ofrece seis tipos de Poké Ball, en paquetes de 3 o 6. Puedes comprar 15 unidades al día y llevar hasta 30. El cupo se renueva a medianoche en Colombia. Se consume una unidad por lanzamiento y se pueden conseguir cinco estampas al día.

## Progreso y seguridad

No se borran cuentas, monedas, Poké Balls, capturas, descubrimientos ni posiciones anteriores. Los estados anteriores, incluso con resistencia cero, reciben un minuto inicial. `tiempo_ms` y los contadores privados `misiones_cobradas` se guardan en el JSON existente. No requiere migración SQL ni tablas nuevas.

Todas las acciones mantienen el bloqueo de cartera y estado, la versión y la clave de idempotencia. El navegador no elige resistencia, tiradas, probabilidad, Pokémon ni coste. Las rutas respetan las colisiones del servidor. La niebla y el rastreo se guardan por usuario. Las batallas, las habilidades y la compra en catálogo mantienen su funcionamiento.

Pruebas: `tests/expedition-rules.php`, `tests/exploration-rules.php`, `tests/exploration-integration.php`, `tests/exploration-input.cjs` y `tests/exploration-world.cjs`.

## Pokémon en movimiento

Un avistamiento aparece a dos o tres casillas y recorre una trayectoria transitable a un máximo de dos casillas del origen, dentro de la visión inicial. Las especies tienen perfiles de paseante, corredor o saltarín. En hierba alta, el Pokémon se esconde durante una pausa de 3,2 segundos y el arbusto se agita; luego emerge y camina. El minimapa señala su posición visible. Movimiento reducido mantiene la pista estática. La ruta, la especie, la probabilidad de captura y el reloj se guardan en `avistamiento`. Recargar no reinicia el animal; después de tres minutos desaparece. Solo hay un avistamiento activo a la vez.

Se puede pulsar el Pokémon en el lienzo o «Acercarme» en el panel. Si está lejos, el entrenador se dirige a su posición actual; puede ser necesario seguirlo si continúa moviéndose. `POST /exploration/observe` exige el identificador del avistamiento y comprueba en servidor una distancia máxima de 2,25 casillas. Acercarse no consume Poké Balls ni monedas. La captura conserva sus reglas y solo consume una Poké Ball al lanzar.

Correr a 3,5 casillas o menos de su trayectoria lo asusta. El Pokémon huye con movimiento rápido durante unos segundos, sin teletransportarse ni volver al jugador al terminar la animación. No se puede capturar mientras huye. El paso del entrenador sigue gastando su resistencia habitual; el movimiento de los Pokémon no la consume.

Las 15 especies usan hojas locales **Walk/Idle de SpriteCollab**, con fotogramas, ocho direcciones y duraciones originales. Los corredores aceleran su ciclo de caminar. Las sombras, la perspectiva y el orden de profundidad se integran en el mismo renderizador del terreno; la niebla oculta animales fuera de la visión. Movimiento reducido fija el fotograma y elimina el balanceo adicional. Si un recurso local no carga, se usa la imagen de respaldo.

Fuentes, créditos por especie y condiciones: [créditos de animación](../frontend/assets/img/wildlife/CREDITS.md), [SpriteCollab](https://github.com/PMDCollab/SpriteCollab) y [formato de sprites](https://wiki.pmdo.pmdcollab.org/Tutorial%3APMD_Sprite_Format). `tools/import_wildlife_sprites.py` conserva los archivos originales sin modificación y genera `frontend/assets/js/wildlife-sprites.js`. La política de contribuciones del repositorio es para proyectos no comerciales con atribución; los sprites originales identificados como CHUNSOFT conservan sus derechos originales.

Pruebas adicionales: `tests/wildlife-rules.php`, `tests/wildlife-motion.cjs`, el renderizador real en `tests/exploration-render.cjs` y aproximación/huida/idempotencia por API en `tests/exploration-integration.php`.

## Movimiento, región y ciclo solar

El campamento conserva sus coordenadas y se conecta con el Bosque de Bruma, el Lago Espejo y el Sendero del Sur. Las zonas nuevas incluyen costa, bosque, lago, caminos y cruces de río. El mapa general y el minimapa adaptan su escala a toda la región.

Caminar usa 180 ms por casilla y correr 105 ms. La posición visible se interpola cada fotograma; la zancada sigue la distancia recorrida. El teclado y la cruceta solicitan tramos de dos casillas y pueden preparar el siguiente 150 ms antes de terminar el visible. El servidor permite hasta 250 ms de anticipación, pero acumula el tiempo completo de cada tramo y sigue validando colisiones, resistencia y encuentros. Las rutas autorizadas se encolan sin reiniciar la posición ni hacer una pausa entre ellas. La reducción de movimiento conserva el tiempo de recorrido.

Cada ciclo tiene **8 minutos de día y 4 de noche**, con transiciones de 35 segundos al atardecer y al amanecer. Se calcula con la hora del servidor y no se reinicia al recargar ni al cambiar de ventana. La configuración está en `ExplorationMap::describe()['ciclo']`. El mapa presenta agua clara y vegetación luminosa durante el día, luz azul moderada de noche y un resplandor cálido en el campamento. La niebla de exploración es independiente de la iluminación; se añade una bruma tenue que se mueve lentamente y se detiene con movimiento reducido.

El suelo se precalcula una vez; los árboles reutilizan un atlas. La niebla reutiliza una textura de media resolución y solo se reconstruye si cambia el terreno explorado. El renderizador descarta objetos fuera de cámara. Estas decisiones siguen las [recomendaciones de optimización de Canvas de MDN](https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API/Tutorial/Optimizing_canvas). `tests/exploration-render.cjs` comprueba el constructor real del renderizador y evita que un callback de pestaña vuelva a sustituir el método de visibilidad.

## Actualización: álbum y misiones (13 de noviembre de 2026)

La exploración empieza con 60 segundos de movimiento y pausa el consumo al detenerse. Preparar equipo en el campamento da 20 segundos diarios; cartografiar 40 casillas nuevas da 20; cada batalla normal terminada da 60. Regresar al campamento es gratis y no recarga automáticamente el reloj.

El álbum tiene veinte hojas de cinco especies y conserva las tres hojas originales. Avistar revela la especie y capturar pega una estampa. Las hojas 21 y 22 reúnen premios únicos por categoría: monedas, tarjetas de cambio de usuario, Poké Balls y alimentos. La categoría Leyendas entrega a Rayquaza al Hogar a nivel 1; si ya vive allí, conserva su progreso. Las capturas históricas del diario aportan sus estampas.

El personaje usa una hoja original prerenderizada en 3D. Véase [entrenador y reacciones](game/ENTRENADOR_3D.md). Los avistamientos cercanos alternan paseo con pausas ocultos en un arbusto que se agita. El cuaderno permite pasar páginas con movimiento reducido respetado.
