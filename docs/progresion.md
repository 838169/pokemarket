# Progresión Pokémon

Cada compra llega a nivel 5. Los compañeros existentes se incorporan también en nivel 5 al consultar el Hogar; las cuentas, compras, monedas, equipos y cuidados se conservan. El nivel máximo es 100.

Las debilidades de la ficha se calculan con la misma tabla de 18 tipos que usa el daño en batalla. Se combinan ambos tipos (×2, ×4, cancelación de debilidad y resistencia, inmunidades). Subir de nivel no modifica los tipos ni sus multiplicadores. Las [habilidades compatibles](habilidades.md) ya se simulan; las restantes se identifican como informativas.

## Datos y reglas

- Fuente: [CSV públicos de PokéAPI](https://github.com/PokeAPI/pokeapi/tree/master/data/v2/csv). `tools/build_progression_catalog.py` compila `backend/trainer-service/config/progression_catalog.json`.
- Se usa el último aprendizaje por nivel disponible en un juego tradicional para cada especie. Se excluyen Legends y Champions, que tienen otros sistemas. No se mezclan movimientos de distintas versiones ni se conceden MT, crianza o tutor automáticamente. Los movimientos de nivel 0 de una especie ya evolucionada se consideran recordables desde nivel 1.
- Se conserva el subconjunto que puede representar el motor actual: ataques físicos/especiales con potencia fija de 1 a 100 y precisión de al menos 90 o sin valor numérico. No se pretende mostrar todos los movimientos de la especie. El juego aún no reproduce todos los estados ni efectos secundarios de movimientos, precisión, PP, objetos ni evolución. Sí incorpora parálisis y las habilidades descritas en [habilidades](habilidades.md).
- Cada especie usa su curva oficial de experiencia. Se calcula la EXP escalada de generación VII en adelante sin modificadores de intercambio, objetos o afecto. Cada Pokémon que haya atacado al rival recibe la cantidad completa; las reservas del equipo que siguen conscientes reciben la mitad. Las especies sin ataques compatibles pueden entrenarse como reservas, pero no encabezar un combate.
- Al comenzar, el rival usa el nivel medio del equipo del jugador y ataques disponibles a ese nivel. La práctica guiada conserva sus datos y no entrega EXP. La dificultad de apuestas y el bonus de cuidado son adaptaciones preexistentes del juego.
- Las estadísticas usan la fórmula tradicional con naturaleza neutra, IV 15 y EV 0. Shedinja conserva 1 PS. No hay variación aleatoria de IV ni entrenamiento de EV en esta versión. El daño utiliza el nivel real del atacante.
- Se equipan de uno a cuatro ataques aprendidos en el Hogar. Los nuevos llenan ranuras libres; si ya hay cuatro, el jugador decide cuáles cambiar. Recordar ataques aprendidos es gratuito. Los cambios del Hogar se aplican a la siguiente batalla; los nuevos ataques que caben en ranuras libres se incorporan al subir de nivel.

## Persistencia

`006_progresion.sql` añade tablas sin borrar información. `tools/setup.php` aplica el esquema de forma repetible. `progreso_pokemon` guarda EXP y ataques por usuario y especie. `experiencia_combate` impide premiar dos veces el mismo rival a un compañero. EXP, monedas y rondas de apuesta se guardan en la misma transacción existente, con bloqueos de filas. Las llamadas del cliente no pueden elegir nivel, EXP ni potencia.

Las batallas antiguas sin nivel continúan con su instantánea anterior y no generan eventos de EXP. Las nuevas usan estadísticas y aprendizaje por nivel.

Pruebas: `tests/progression-rules.php`, `tests/progression-integration.php`, `tests/battle-rules.php`, `tests/market-rules.php` y validaciones HTTP de ataques en `tests/economy-integration.php`.
