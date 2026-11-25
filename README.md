# PokéMarket

Juego web académico para explorar Pokémon, comprar compañeros con monedas de batalla y cuidarlos en un Hogar. Desarrollado con PHP, JavaScript y MySQL/MariaDB.

La navegación interna mantiene un reproductor de música compartido y un control de sonido visible entre secciones. La arquitectura está descrita en [música continua](docs/game/HOGAR_MUSICA.md).

**Explorar** añade una pradera isométrica con niebla, rastreo, tiempo ganado con misiones y un álbum de estampas con Poké Balls. Consulta las [reglas de expedición salvaje](docs/expedicion.md) y la [arquitectura de exploración](docs/game/EXPLORACION.md).

## Empieza aquí

1. Configura una base local MySQL/MariaDB o tu instancia de TiDB Cloud. Para una base local, inicia **MySQL** en XAMPP. Para usar `http://localhost/pokemarket/`, inicia también Apache.
2. En la raíz, junto a este README, copia `.env.example` a `.env` y configura tu conexión a MySQL. Cada persona crea su propio `.env`. Al repositorio se sube la plantilla `.env.example`.

    ```powershell
    Copy-Item .env.example .env
    ```

3. Instala/verifica el esquema, **sin borrar cuentas ni progreso**:

    ```powershell
    C:\xampp\php\php.exe tools/setup.php
    ```

    Como alternativa, crea una base vacía en phpMyAdmin, selecciónala e importa [database/schema.sql](database/schema.sql). Usa ese mismo nombre en `DB_NAME`. El SQL contiene las tablas completas y la primera cuenta se registra desde el juego. Si ya tienes una base de PokéMarket, usa el instalador para actualizarla.

4. Arranca los servicios desde la raíz del proyecto:

    ```powershell
    .\tools\start.ps1
    ```

5. Abre **http://localhost:8000**, registra tu cuenta y elige tu primer compañero. El regalo de bienvenida es de 1.000 monedas y 3 cestas de bayas, una sola vez.

Para jugar desde la misma red, inicia los servicios con `powershell -ExecutionPolicy Bypass -File tools/start.ps1 -Lan` y abre la dirección IPv4 que imprime el comando. Si el equipo cambia de red, consulta su nueva IPv4 con `ipconfig`. Permite TCP 8000 en el firewall para la subred local. El gateway conecta con los microservicios desde el servidor.

Para enviar códigos con Hostinger Email, crea un buzón en hPanel y configura `MAIL_FROM` con la dirección completa, `MAIL_SMTP_HOST=smtp.hostinger.com` y `MAIL_SMTP_PASSWORD` con la contraseña del buzón en el archivo privado `.env`. El envío usa SMTP cifrado por el puerto 465. Si dejas vacío `MAIL_SMTP_HOST`, PHP usará el transporte de `mail()` configurado en el servidor.

Para activar una app autenticadora, genera una clave con `php -r "echo bin2hex(random_bytes(32));"` y guárdala en `ACCOUNT_SECURITY_KEY`. Conserva esa clave para poder descifrar los autenticadores registrados. Ejecuta `php tools/setup.php` para aplicar la migración. En un entorno con usuarios reales, sirve la web y las API mediante HTTPS.

Los puertos deben estar libres antes del arranque. Comprueba los [endpoints de estado](docs/instalacion.md#comprobar-los-servicios) si había otros servidores abiertos. Ctrl+C detiene solo los procesos que haya creado el script.

Si prefieres abrir cada servicio en una terminal o PowerShell no permite ejecutar scripts, consulta los [comandos manuales](docs/instalacion.md#arranque-manual).

## Compartir la instalación

Copia `.env.example` como `.env` en la raíz del proyecto, junto a este README. Las credenciales de base de datos, correo y Google se configuran en ese archivo. La copia del repositorio incluye el esquema SQL y las migraciones, además del código y los recursos del juego.

Si usas TiDB Cloud, descarga el certificado CA desde la consola de TiDB y coloca su ruta absoluta en `DB_SSL_CA`. Autoriza la IP pública del computador que ejecuta Entrenadores. En modo local, el ejemplo usa XAMPP sin TLS.

Para la demostración de Google, registra `http://localhost:8000/api/entrenadores/auth/google-callback` como URI autorizada del cliente OAuth. La configuración de Google y las claves JWT están explicadas en [instalación](docs/instalacion.md).

Los informes de la exposición están en `output/pdf/` y `output/word/`.

## Comprobar la calidad

Requisitos: PHP 8.1 o superior con `pdo_mysql` y `curl`, Node.js 18 o superior y npm. MySQL es necesario para instalar y para las pruebas de integración; las pruebas offline no lo utilizan.

```powershell
npm ci --ignore-scripts
npm run format:check
npm test
```

Con MySQL y los microservicios iniciados:

```powershell
npm run test:integration
```

El informe se genera en `reports/quality-report.md` y `reports/quality-report.json`. El comando devuelve error si falla una comprobación. Las pruebas de integración crean cuentas temporales y eliminan **solo sus propios datos** al terminar.

Las herramientas npm son de desarrollo; **no son necesarias para jugar**. El índice de Pokémon ya está incluido y no requiere regenerarse para ejecutar el proyecto.

## Dónde está cada cosa

```text
PokéMarket/
├── frontend/
│   ├── pages/             Pantallas del juego, acceso y registro
│   ├── partials/          Encabezado, tienda y componentes compartidos
│   ├── assets/            CSS, JavaScript, imágenes, audio y video
│   └── config/            Configuración de la interfaz
├── backend/
│   ├── pokemon-service/   Consultas a PokéAPI, imágenes y caché
│   └── trainer-service/   Cuentas, colección, economía, exploración y duelos
├── database/
│   ├── schema.sql         SQL completo para una base nueva
│   └── migrations/       Cambios históricos para instalaciones previas
├── config/               Entorno y acceso local compartidos
├── docs/                 Documentación técnica y de exposición
├── tests/                Pruebas automáticas
├── tools/                Instalación, arranque y control de calidad
└── .env.example          Plantilla de configuración local
```

El código histórico sin uso se retiró. El SQL antiguo y los respaldos privados se conservan localmente en `.local-backups/`, fuera de la entrega y bloqueados para acceso web. No se necesitan para una instalación nueva.

El [mapa de la base de datos](database/README.md) explica qué guarda cada tabla y cómo actualizar una instalación existente.

## Documentación para la materia

| Documento                                            | Qué explica                                         |
| ---------------------------------------------------- | --------------------------------------------------- |
| [Guía de exposición](docs/presentacion.md)           | Recorrido sugerido y demostraciones                 |
| [Plan de calidad y requisitos](docs/calidad.md)      | Criterios medibles, trazabilidad y pendientes       |
| [Arquitectura](docs/arquitectura.md)                 | Componentes, responsabilidades, flujo y datos       |
| [Instalación](docs/instalacion.md)                   | Configuración, puertos y solución de problemas      |
| [Pruebas](docs/pruebas.md)                           | Qué se comprueba y cómo interpretar el informe      |
| [Seguridad](docs/seguridad.md)                       | Controles aplicados y límites del entorno académico |
| [Reglas de economía y cuidado](docs/game/ECONOMY.md) | Precios, monedas, comida y bonus                    |

## Alcance

El proyecto implementa un combate por turnos simplificado. No reproduce todos los efectos de habilidades, PP y estados de los juegos oficiales. Los sprites y la música pueden depender de recursos de terceros; sus fuentes están en [recursos visuales](docs/game/MEDIA.md) y [audio](frontend/assets/audio/README.md).

Se aplican criterios de calidad y controles de seguridad con pruebas concretas. **No se declara certificación ISO, auditoría completa de OWASP ni cobertura de pruebas del 100%.** Consulta los pendientes antes de plantear una publicación en Internet.
