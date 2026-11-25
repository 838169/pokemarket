# trainer-service

Servicio PHP para autenticación, compras, Hogar, equipos y batallas. Usa una base de datos compatible con MySQL y consume por HTTP el catálogo de `pokemon-service`. Aplica las reglas de precios, progresión y combate sobre esos datos.

## Ejecutar

```powershell
C:\xampp\php\php.exe -S localhost:8002 -t public public/router.php
```

Instalación no destructiva desde la raíz: `php tools/setup.php`. Configuración en `.env` a partir de `.env.example`.

Estado: `GET /trainer-api/v1/health`. Las operaciones autenticadas que modifican datos necesitan cookie y `X-CSRF-Token`, obtenido de `GET /trainer-api/v1/auth/me`.

Más información: [arquitectura](../../docs/arquitectura.md), [instalación](../../docs/instalacion.md) y [pruebas](../../docs/pruebas.md).
