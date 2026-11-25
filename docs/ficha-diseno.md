# Ficha Pokémon: dirección visual

Referencias revisadas: [Frontend Design](https://github.com/anthropics/skills/tree/main/skills/frontend-design) y [UI/UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill). Ambas skills ya estaban disponibles localmente; se adaptan sus criterios, sin añadir dependencias.

La ficha se trata como una tarjeta de Pokédex, con el Pokémon como protagonista. El cartel de la izquierda recibe el color de su tipo principal; la información ocupa un panel azul noche a la derecha, en continuidad con el fondo del juego. La Poké Ball y el número de especie forman parte del lenguaje de Pokémon. Las habilidades y los ataques iniciales quedan debajo de la ilustración.

- Tinta azul `#17243f` sobre la ilustración, panel azul noche `#16233b`, texto `#edf3ff` y lectura secundaria `#abbad2`. Las estadísticas usan superficies `#1d2d49` y colores luminosos.
- Eléctrico: amarillo `#ffe16a`; Fuego: coral `#ff9b73`; Agua: azul `#84ceff`; Planta: verde `#a2e5a2`.
- Outfit para nombre y números destacados; DM Sans para lectura. Se aprovechan las familias que el proyecto ya carga.
- Las seis estadísticas tienen colores propios y barras proporcionales sobre 255. Se mantienen sus valores exactos y se identifican como estadísticas base.
- Tipos y debilidades usan la misma paleta y conservan nombres y multiplicadores; el significado no depende solo del color.
- Un Pokémon comprado muestra un estado verde legible en el botón. La compra activa usa el color de su tipo con texto azul de tinta.
- El giro de apertura existente se conserva. No se añaden animaciones continuas. Se respeta la reducción de movimiento.
- En escritorio se busca una ficha completa dentro de la ventana; en móvil se apilan las columnas y se conserva el desplazamiento necesario para acceder a toda la información.
