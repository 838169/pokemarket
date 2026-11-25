# Entrenador prerenderizado

`frontend/assets/img/trainer-adventure-3d.png` es una hoja original generada con la herramienta integrada de imágenes el 13 de noviembre de 2026. Tiene fondo transparente, cuatro fotogramas y ocho direcciones, en orden S, SO, O, NO, N, NE, E, SE. El renderizador calcula cada celda a partir de las dimensiones reales. Es un personaje prerenderizado de aspecto 3D dentro del mapa Canvas, no un modelo 3D interactivo.

Prompt utilizado: hoja de sprites transparente, cuatro columnas y ocho filas, mismo joven entrenador de aventuras en cada celda, gorra coral con panel blanco, chaqueta azul verdosa sobre camiseta crema, pantalón azul noche, mochila beige y botas; 3D estilizado con cámara ortográfica elevada 30 grados y luz superior izquierda. Una dirección por fila en orden S, SO, O, NO, N, NE, E, SE y cuatro fases consecutivas de caminata por columna. Escala, cámara y punto de apoyo constantes; sin personajes existentes, texto, etiquetas, rejilla, suelo ni escenario.

La caminata sigue la distancia recorrida. Movimiento reducido mantiene un fotograma estable. Mientras carga o si falla el recurso, permanece el entrenador dibujado anterior.

## Reacciones de los Pokémon

Se revisaron [PokeAPI/sprites](https://github.com/PokeAPI/sprites) y [SpriteCollab](https://github.com/PMDCollab/SpriteCollab). El catálogo HD de PokeAPI publica animaciones y variantes, pero no un conjunto documentado de gestos de enfado HD para las 1.025 especies. SpriteCollab tiene acciones como Attack/Hurt e ilustraciones de expresión, con otra estética y resolución.

Para conservar la calidad actual, tocar un Pokémon del catálogo muestra un sobresalto y una respuesta breve sobre el mismo sprite HD. Es una reacción de interfaz, no un nuevo gesto facial oficial. Los clics repetidos no apilan ni reinician el grito. En el mapa se señala la molestia; correr cerca sigue provocando la huida validada por el servidor.
