# Habilidades de combate

Cada compañero tiene una sola habilidad. Mar Llamas, Espesura, Torrente y Enjambre potencian su tipo un 50% con un tercio de PS o menos. Electricidad Estática aplica un 30% de parálisis al recibir contacto, incluso si su portador cae; los tipos Eléctrico y Flexibilidad la impiden. La parálisis reduce la Velocidad a la mitad y tiene un 25% de impedir actuar.

Intimidación reduce el Ataque un nivel al entrar; los niveles van de −6 a +6 y se reinician al retirar al Pokémon. Cuerpo Puro, Humo Blanco, Corte Fuerte, Foco Interno, Despiste, Ritmo Propio e Intrépido impiden esa reducción según las reglas modernas. Intrépido permite además golpear a Fantasma con Normal y Lucha. Los demás efectos que aún no representa el motor se indican en la descripción.

Sequía y Llovizna crean cinco turnos de sol o lluvia, incluyendo el turno de entrada. Sol potencia Fuego ×1,5 y reduce Agua ×0,5; lluvia hace lo contrario. Repetir la habilidad con el mismo clima no renueva el contador. Si ambos líderes cambian el clima, se resuelven del más rápido al más lento. Clorofila duplica Velocidad con sol; Nado Rápido con lluvia. Poder Solar aumenta Ataque Especial ×1,5 con sol y resta 1/8 de los PS máximos al final del turno, antes de terminar el clima. No crea sol por sí misma: puede combinarse con un compañero con Sequía.

El orden de ataque respeta la prioridad oficial del movimiento y después la Velocidad efectiva; los empates se deciden al azar. `ability_catalog.json` conserva contacto y prioridad del CSV oficial de PokéAPI, compilados con `tools/build_ability_catalog.py`. Un ataque físico no implica contacto. El motor conserva las limitaciones de [progresión](progresion.md): no representa todos los efectos de los movimientos ni todas las habilidades de la franquicia.

## Tienda y Hogar

El precio añade **25 monedas en iniciales o 40 en otras categorías por una habilidad funcional**, una sola vez al comprar. No se cobra por cantidad de alternativas, activación ni cambio. Si no hay habilidad compatible, el recargo es cero. Se prioriza la primera habilidad compatible de la especie; la ficha distingue habilidades informativas y compatibles, e identifica las ocultas.

En el Hogar se puede elegir gratis entre las alternativas compatibles propias de la especie; la elección de habilidad oculta es una comodidad de esta tienda, no una simulación de su método de obtención en los juegos originales. Las habilidades conservan sus efectos y exclusividad originales. Los Pokémon existentes reciben su habilidad por defecto sin cobro retroactivo.

`007_habilidades.sql` añade una tabla sin borrar información; `tools/setup.php` aplica la migración. La elección se guarda por usuario y especie; se comprueba propiedad y compatibilidad en el servidor. `POST /trainer-api/v1/home/ability` recibe `pokemon_id` y `habilidad` (identificador oficial). La compra permite indicar una alternativa compatible con el mismo recargo. Una batalla conserva su instantánea y no cambia si se elige otra habilidad en el Hogar. Las batallas anteriores a esta incorporación continúan sin habilidades.

Pruebas: `tests/ability-rules.php`, `tests/ability-integration.php` y suite HTTP de economía. Fuentes de reglas: [habilidades](https://pokemondb.net/ability), [Electricidad Estática](https://www.smogon.com/dex/sv/abilities/static/), [Poder Solar](https://pokemondb.net/ability/solar-power), [Intimidación](https://pokemondb.net/ability/intimidate) y [Sequía](https://pokemondb.net/ability/drought).
