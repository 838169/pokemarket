# Música del Hogar

Las pistas están incluidas localmente: no requieren claves, registro, anuncios ni peticiones a un proveedor al cambiar de escenario. La música empieza al pulsar el icono de sonido y se pausa al ocultar la pestaña. Al cambiar de escenario, la pista anterior se desvanece mientras entra la siguiente.

| Escenario               | Archivo                        | Obra y autor                           | Fuente y licencia verificada                                            |
| ----------------------- | ------------------------------ | -------------------------------------- | ----------------------------------------------------------------------- |
| Todo el Hogar y Pradera | `frontend/assets/audio/home-meadow.mp3` | Campsite Theme — FazeDevWater          | [OpenGameArt, CC0](https://opengameart.org/content/campsite-theme)      |
| Bosque                  | `frontend/assets/audio/home-forest.mp3` | Forest Ambience — TinyWorlds           | [OpenGameArt, CC0](https://opengameart.org/content/forest-ambience)     |
| Laguna                  | `frontend/assets/audio/home-lagoon.ogg` | Underwater Theme II — Cleyton Kauffman | [OpenGameArt, CC0](https://opengameart.org/content/underwater-theme-ii) |

Verificación: 23 de octubre de 2026. El archivo readme del paquete de Underwater Theme II también indica Creative Commons Zero. Crédito del autor: Music by Cleyton Kauffman, https://soundcloud.com/cleytonkauffman. Se conserva este crédito aunque CC0 no exige atribución.

Se investigó la [API de Freesound](https://freesound.org/docs/api/authentication.html): requiere autenticación y cada recurso tiene su propia licencia. Para estos escenarios fijos se eligieron pistas verificadas y alojadas en el proyecto, evitando exponer una clave en JavaScript o añadir dependencia de red.

Estas pistas son independientes de la música de combate preexistente; sus fuentes están documentadas en `frontend/assets/audio/README.md`.

Actualización del 24 de octubre de 2026: el control visible es un botón circular en la esquina inferior derecha del paisaje, con icono, etiqueta accesible y estado `aria-pressed`. Se retiran los créditos, el rango de volumen y los mensajes permanentes de la interfaz. Las fuentes y licencias se conservan en esta documentación y `frontend/assets/audio/README.md`. El volumen base es 25%; los cambios de escenario y el final de pista usan un fundido cruzado de 900 ms. Al cambiar rápidamente se ignoran reproducciones antiguas; ocultar la pestaña libera las voces y volver recupera la música si estaba activa.

## Música continua entre secciones

`frontend/config/game-shell.php` mantiene un documento exterior con un solo reproductor e icono de sonido. Catálogo, Hogar, Equipos, Batalla y Explorar se muestran como vistas del mismo origen; los enlaces internos cambian la vista y la URL, sin reemplazar el reproductor. Las rutas, sesiones PHP, autorizaciones y cálculos del servidor conservan sus controles. El icono global permanece en la esquina inferior derecha. Atrás/adelante actualiza la vista, y cerrar sesión devuelve a la página de acceso y detiene el audio.

Los encabezados permiten enmarcado únicamente desde el mismo origen (`SAMEORIGIN` y CSP `frame-ancestors 'self'`). No se permite navegación de vistas a dominios externos ni rutas fuera del directorio del juego. Los archivos PHP siguen mostrando su contenido al ejecutarse desde CLI para las verificaciones existentes. Recargar completamente la página reinicia el documento exterior; la continuidad se aplica a la navegación interna entre secciones.

Validación: 122/122 comprobaciones de la suite con integración API/MySQL, incluyendo elección del segundo miembro del equipo como inicial, rechazo de miembros ajenos, movimiento sin foco exacto y reutilización del reproductor. Se verificaron por HTTP las cinco rutas exteriores y sus vistas, con los encabezados de aislamiento. La revisión visual en navegador no pudo ejecutarse porque el navegador local está deshabilitado en Work.
