# Seguridad aplicada y límites

Guía de referencia: [OWASP ASVS](https://asvs.owasp.org/). No se declara cumplimiento de todos sus requisitos ni un nivel de certificación.

## Controles implementados

- **Identidad y pertenencia:** los controladores obtienen el usuario de la sesión, no del cuerpo JSON. Las consultas a equipos, Hogar y saldo incluyen su identificador. El estado de batalla también incluye dueño y revisión de economía.
- **Contraseñas:** `password_hash` y `password_verify`. Registro con mínimo de ocho caracteres y entrada limitada a 72 bytes para evitar truncamiento del algoritmo usado. Las cuentas antiguas no se borran ni se obliga a cambiar sus claves durante la instalación.
- **Sesiones:** regeneración al entrar, uso exclusivo de cookie, modo estricto, HttpOnly y SameSite=Lax. Expiración configurable por inactividad. `SESSION_SECURE=true` debe usarse si se despliega con HTTPS.
- **CSRF:** token aleatorio asociado a sesión y comparación constante con `hash_equals`. Las operaciones autenticadas que cambian datos exigen `X-CSRF-Token`. El navegador obtiene el token desde `/auth/me`. Login y registro se mantienen públicos, con JSON y validación de origen.
- **Entradas:** texto con tipo y tamaño comprobados; cuerpo JSON de máximo 64 KiB; categorías, tipos y orden mediante listas permitidas; cantidades y paginación validadas en servidor.
- **Base de datos:** consultas preparadas con emulación desactivada. Las operaciones monetarias usan transacciones y bloqueo de cartera. No se confía en precio ni saldo enviados por cliente.
- **Salida:** el cliente escapa texto antes de insertarlo como HTML; las etiquetas que no necesitan HTML usan `textContent`.
- **Errores:** no se devuelven contraseñas ni mensajes SQL. `X-Request-ID` y `request_id` permiten identificar una respuesta. Los fallos inesperados de trainer se registran con servicio, tipo de excepción e identificador.
- **Superficie pública:** se retiró la antigua API que permitía operar fuera de la economía. `.htaccess` y el router local impiden descargar configuración, tablas, respaldos, pruebas y código interno. Los servicios solo deben publicarse desde su carpeta `public`.
- **Secretos y datos:** `.env`, los SQL privados, respaldos y resultados locales no se versionan. El esquema de entrega no contiene cuentas de personas reales.

## Lo que todavía debe hacerse antes de producción

Aplicar HTTPS y cabeceras según el despliegue final, cuentas MySQL con privilegios mínimos, límites de intentos de acceso, una política de recuperación de contraseñas y análisis de dependencias. Definir copias de seguridad externas, restauración ensayada, observabilidad centralizada y ensayos de carga. Una política CSP completa exige revisar las fuentes externas de sprites, estilos y las plantillas dinámicas existentes; no se afirma que esté implementada.

Los logs del servidor no deben publicarse. Los identificadores de solicitud son útiles para diagnóstico, pero no sustituyen monitoreo ni un registro de auditoría completo.
