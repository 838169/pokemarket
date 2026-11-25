# Instalación y operación local

## Requisitos

- PHP ≥ 8.1 con `pdo_mysql`, `curl` y OpenSSL. XAMPP local es la configuración utilizada.
- MariaDB/MySQL con soporte InnoDB.
- Navegador actual. JavaScript debe estar habilitado.
- Node.js ≥ 18 y npm, solo para las herramientas de calidad.

## Configuración

Desde la raíz:

```powershell
Copy-Item .env.example .env
C:\xampp\php\php.exe tools/setup.php
```

`Copy-Item` deja el archivo `.env` en la raíz, junto al README y a `.env.example`. Solo se versiona la plantilla. Cada persona configura las credenciales de su propia instalación. Las variables de entorno del proceso tienen prioridad sobre el archivo. `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` controlan la conexión. La configuración de ejemplo utiliza el usuario local de XAMPP; no es una configuración de producción.

Para una base nueva, `tools/setup.php` instala todas las tablas de [schema.sql](../database/schema.sql). También puedes importar ese archivo en una base vacía con phpMyAdmin. Para una instalación existente, ejecuta `tools/setup.php` con los servicios PHP detenidos. El instalador renombra las tablas antiguas y conserva los datos. Consulta el [mapa de tablas](../database/README.md).

`APP_ORIGINS` enumera los orígenes del frontend autorizados para trainer-service. Incluye `http://localhost` y los orígenes locales del puerto 8000. En HTTPS, configura `SESSION_SECURE=true`.

Para una instalación nueva, genera la clave JWT con `C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"` y pega el resultado en `GATEWAY_JWT_KEY` dentro del `.env` de la raíz. Después de incorporar JWT a una instalación previa, vuelve a iniciar sesión.

### Continuar con Google

En Google Cloud Console, configura la pantalla de consentimiento de Google Auth Platform y crea un cliente OAuth de tipo **Aplicación web**. Si la aplicación está en estado de prueba, agrega tu cuenta de Google como usuario de prueba. Registra exactamente esta URI de redirección autorizada:

```text
http://localhost:8000/api/entrenadores/auth/google-callback
```

En el `.env` de la raíz, agrega el ID y el secreto del cliente:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI=http://localhost:8000/api/entrenadores/auth/google-callback
```

Abre `http://localhost:8000/login.php` y pulsa **Continuar con Google**. Si tu correo es nuevo en PokéMarket, elige un nombre de usuario. Si ya tienes una cuenta anterior verificada con ese correo, completa su código de acceso. Para habilitar Google en otros dispositivos, usa una URL HTTPS con dominio y registra su URI de retorno en Google Cloud.

## Arranque manual

Abre tres terminales desde la raíz del proyecto. Cada proceso permanece en primer plano y se detiene con Ctrl+C.

```powershell
# Terminal 1: frontend con rutas protegidas
C:\xampp\php\php.exe -S localhost:8000 -t frontend tools/frontend-router.php
```

```powershell
# Terminal 2: datos de Pokémon
C:\xampp\php\php.exe -S localhost:8001 -t backend/pokemon-service/public backend/pokemon-service/public/router.php
```

```powershell
# Terminal 3: cuentas y juego
C:\xampp\php\php.exe -S localhost:8002 -t backend/trainer-service/public backend/trainer-service/public/router.php
```

Alternativa en Windows: `./tools/start.ps1` inicia los tres procesos y conserva los puertos ocupados. `./tools/start.ps1 -Service trainer` ejecuta únicamente trainer-service en primer plano.

El navegador llama al gateway del puerto 8000. El gateway se conecta a los servicios en localhost:8001 y localhost:8002. Para probar desde el celular, inicia con `./tools/start.ps1 -Lan` y permite TCP 8000 para la subred local.

## Comprobar los servicios

```powershell
Invoke-RestMethod http://localhost:8000/api/pokemon/health
Invoke-RestMethod http://localhost:8000/api/entrenadores/health
```

Pokémon informa disponibilidad del proceso sin consultar Internet. Trainer verifica acceso a la tabla de carteras. Un estado correcto no demuestra que todos los proveedores externos estén disponibles.

Abre http://localhost:8000. Apache en XAMPP también permite http://localhost/pokemarket/; la raíz contiene reglas `.htaccess` para impedir descargas de configuración, respaldos, pruebas y código interno. El servidor PHP del frontend aplica su propia lista de rutas permitidas y no depende de Apache.

## Problemas frecuentes

| Síntoma                                           | Acción                                                                                                         |
| ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Error de conexión a MySQL                         | Inicia MySQL y revisa `.env`. El servidor no muestra contraseñas en el error.                                  |
| Tabla inexistente                                 | Ejecuta `tools/setup.php`. No uses el SQL privado antiguo.                                                     |
| Puerto ocupado                                    | Detén la terminal correspondiente o comprueba que sea el servicio esperado. El script no mata procesos ajenos. |
| Error 403 al comprar después de una actualización | Recarga la página o inicia sesión para renovar el token CSRF.                                                  |
| Error 401                                         | La sesión terminó, se cerró o expiró por inactividad. Vuelve a entrar.                                         |
| Imagen externa ausente                            | Se intentan fuentes alternativas; revisar Internet. La compra y el cálculo de batalla usan datos locales.      |
| Formato incorrecto                                | Ejecuta `npm run format` y después `npm test`.                                                                 |

## Datos y recuperación

Haz un respaldo con las herramientas de MySQL antes de cambios manuales de esquema. Los respaldos locales se mantienen fuera de Git y de los directorios publicados. Nunca publiques el volcado que incluya cuentas reales.

`tools/migrate_economy.php --reset-progression` corresponde al reinicio histórico solicitado durante el desarrollo. Requiere un indicador explícito, guarda respaldo y usa una marca para no repetirlo. **No forma parte de la instalación ni de los comandos de calidad y no debe usarse para preparar la exposición.** Esta revisión del proyecto no requiere volver a reiniciar usuarios.
## Demostración de eventos asíncronos

Con los tres componentes en consolas separadas, deja detenido el proceso de notificaciones y envía una solicitud desde Amigos. La solicitud queda pendiente y el destinatario recibe el aviso cuando arranca el consumidor. Inícialo en otra ventana de PowerShell:

```powershell
& "C:\xampp\htdocs\pokemarket\tools\start.ps1" -Service notifications
```

El destinatario puede aceptar o rechazar la solicitud. Tras rechazarla, el remitente puede buscarlo y enviarla de nuevo. Pulsa Ctrl+C en la ventana del consumidor para volver a pausarlo. El arranque general con `tools/start.ps1` inicia también el consumidor.

## Operación y CI/CD local

Detén los procesos anteriores y ejecuta `./tools/start.ps1 -Lan -Continuous` desde la raíz. La primera publicación espera el resultado de las pruebas. El modo continuo detecta cambios guardados, copia una candidata, ejecuta la misma suite de `npm test` y publica esa copia solo si pasa. El supervisor arranca la versión aprobada y vuelve a la anterior si la web o un proceso no arranca. Las pruebas se ejecutan fuera de la versión activa. La configuración privada se lee de la raíz en ejecución. Las migraciones de TiDB se aplican por separado.

Para ver el estado y los registros, usa otra ventana de PowerShell desde la raíz:

```powershell
Get-Content .local-backups/operations/health.txt
Get-Content .local-backups/operations/operations.log -Wait
Get-Content .local-backups/operations/ci.log -Tail 12
```

`health.txt` registra HTTP y latencia a través del gateway. `operations.log` registra arranques, caídas, cambios de salud y despliegues. Esta carpeta contiene además el informe de pruebas y registros separados por proceso. `reports/quality-report.md` dentro de esa carpeta corresponde al último intento. Para demostrar la recuperación, detén el PID de Pokémon que imprime el supervisor y comprueba que vuelve con otro PID. Para demostrar el rechazo de una candidata, introduce y corrige un error de sintaxis en una copia de prueba del proyecto mientras corre el modo continuo.
