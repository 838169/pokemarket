# Vídeo de bienvenida de una compra

Coloca aquí el archivo **`compra-pokebola.mp4`** y vuelve a cargar el catálogo. Se detecta automáticamente; no hace falta modificar código ni base de datos.

El clip esperado dura **8 segundos**, en horizontal **16:9**, con fondo verde uniforme. La versión presente es **1920 × 1080 a 24 fps**. La sincronización está ajustada a ese vídeo: absorción entre 0,65 y 2,65 segundos, cierre a 2,7, movimientos a 3,2/3,8/4,4 y confirmación a 5,2 segundos. La aplicación reduce y desvanece la esfera entre 7 y 7,7 segundos para completar la llegada al Hogar aunque el vídeo conserve la esfera al terminar.

El juego quita el verde al reproducirlo mediante un canvas y añade la imagen del Pokémon comprado. El croma tolera tanto #00FF00 como el verde más oscuro que aparece en el clip de Flow y reduce los reflejos verdes de los bordes. Mantén los objetos, los brillos y las partículas en rojo, blanco, cian y dorado; cualquier elemento verde también se eliminaría. La pista original del vídeo sigue el control de efectos y el mute del juego. Si el navegador no permite iniciar esa pista, se usan los sonidos locales de compra. La imagen se ajusta completa a la zona de presentación para no recortar la Poké Ball en móviles.

La secuencia comienza únicamente tras una compra confirmada por el servidor. «Continuar», Escape, cambiar de pestaña o salir de la página cancelan la presentación sin deshacer la compra. Si falta el vídeo, falla su reproducción o tarda demasiado en cargar, se usa una animación breve. Con movimiento reducido se muestra una confirmación estática.

El procesado del croma se limita a 960 × 540 y 24 actualizaciones por segundo para controlar el coste de CPU. El archivo puede tener mayor resolución. El reproductor tiene un límite de espera y no impide llegar a la confirmación de compra.
