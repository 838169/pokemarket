# Fuentes del índice de juego

Datos oficiales de https://github.com/PokeAPI/pokeapi/tree/master/data/v2/csv.

Para reconstruir el índice, descarga en esta carpeta los CSV `pokemon`, `pokemon_stats`, `pokemon_species`, `abilities`, `ability_names`, `pokemon_abilities`, `pokemon_types`, `types`, `moves`, `move_names` y `pokemon_moves`, con extensión `.csv`. Después ejecuta desde la raíz:

```powershell
python tools/build_market_catalog.py
```

Los CSV crudos están excluidos de Git. La aplicación utiliza el JSON compilado de `backend/pokemon-service/config/market_catalog.json` y no necesita descargar estos archivos al jugar. El constructor debe ejecutarse con los once CSV completos de la misma revisión de PokéAPI.

La progresión utiliza además `experience.csv` y `version_groups.csv` de la misma fuente. Ejecuta `python tools/build_progression_catalog.py` para compilar curvas de experiencia y ataques por nivel. Las reglas y limitaciones están documentadas en `docs/progresion.md`.
