# Fondo del Hogar listo para sustituir

Guarda la imagen final como **`frontend/assets/img/adventure/hogar-santuario.png`**. Recarga el Hogar: «Todo el Hogar» la detecta automáticamente. No cambia Pradera, Bosque o Laguna. Si no existe el archivo, se conserva el fondo actual.

Genera PNG horizontal **2:1**, idealmente 2560 × 1280. No incluyas Pokémon, entrenadores, nombres ni botones: el juego los coloca encima. Las seis plataformas y sus posiciones las dibuja el código, también en móvil. No las pintes dentro de la imagen, porque se desalinearían al adaptar el encuadre.

## Prompt para Flow / generador de imágenes

> Crea únicamente el fondo de un santuario para criaturas de un videojuego de aventuras. Ilustración 3D estilizada de calidad profesional, cámara ortográfica fija elevada 25 grados, vista frontal de tres cuartos, paisaje panorámico 2:1 a 2560 × 1280. Una pequeña casa acogedora con tejado coral y madera clara ocupa el tercio superior, rodeada por árboles frondosos en los extremos. Un estanque azul pequeño queda al fondo a la derecha; un sendero estrecho rodea el césped sin atravesar las áreas donde estarán los personajes. El 65% central e inferior debe ser un claro amplio, llano, continuo y limpio, con hierba corta verde salvia, ligeramente texturizada. Reserva seis zonas de suelo libres de objetos: tres aproximadamente en x=19%, 50%, 81% a y=46%, y otras tres en esas mismas columnas a y=83%. Son espacios para colocar personajes posteriormente: NO dibujar plataformas, círculos ni marcas. Mantén también libre el centro para que funcione un recorte vertical en móvil. Luz cálida suave de mañana desde arriba a la izquierda, sombras delicadas coherentes, vegetación verde menta, agua celeste, pequeños detalles coral y amarillo cálido. Formas redondeadas, materiales agradables y profundidad de un diorama de aventura; acabado pulido y limpio. Los elementos de primer plano quedan únicamente en los bordes, sin rocas, muebles, ramas o flores altas delante de las zonas reservadas. Sin personajes, sin criaturas, sin Pokémon, sin siluetas, sin interfaz, sin texto, sin logotipos, sin marcos, sin desenfoque, sin noche, sin niebla densa, sin realismo fotográfico. Imagen completa a sangre, sin bordes.

## Ajustes de los lugares

`frontend/assets/js/home-layout.js` controla seis posiciones fijas por grupo. `frontend/assets/css/home-layout.css` dibuja las plataformas. Cada compañero conserva su lugar al añadir otros y los grupos de más de seis siguen paginados. El fondo es decorativo: nunca determina colisiones o posiciones de juego.
