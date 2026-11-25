# pokemon-service

Servicio PHP independiente para catálogo, búsqueda, detalle y consulta por lote de Pokémon. PokéAPI se consume únicamente mediante `src/Services/PokeApiClient.php`; la caché JSON dura 900 segundos.

El servicio publica el catálogo del juego en `GET /pokemon-api/v1/game-catalog` a partir de su índice compilado de PokéAPI. Las imágenes y animaciones se solicitan en `/pokemon-api/v1/sprites/{id}/{vista}`; la ruta redirige a la fuente del recurso sin guardar la redirección. Estado en `GET /pokemon-api/v1/health`. Documentación general en [README](../../README.md).

## Ejecutar localmente

Desde esta carpeta, con XAMPP instalado:

```powershell
C:\xampp\php\php.exe -S localhost:8001 -t public public/router.php
```

## Endpoints

```text
GET http://localhost:8001/pokemon-api/v1/pokemon?limit=12&offset=0
GET http://localhost:8001/pokemon-api/v1/pokemon/search?q=pikachu
GET http://localhost:8001/pokemon-api/v1/pokemon/25
GET http://localhost:8001/pokemon-api/v1/pokemon/batch?ids=1,4,25
```

Pruebas rápidas en PowerShell:

```powershell
Invoke-RestMethod "http://localhost:8001/pokemon-api/v1/pokemon?limit=12&offset=0" | ConvertTo-Json -Depth 10
Invoke-RestMethod "http://localhost:8001/pokemon-api/v1/pokemon/search?q=pikachu" | ConvertTo-Json -Depth 10
Invoke-RestMethod "http://localhost:8001/pokemon-api/v1/pokemon/25" | ConvertTo-Json -Depth 10
Invoke-RestMethod "http://localhost:8001/pokemon-api/v1/pokemon/batch?ids=1,4,25" | ConvertTo-Json -Depth 10
```
