# Guía para presentar el proyecto

## Antes de clase

1. Lee el `README.md`. Arranca MySQL y los servicios.
2. Ejecuta formato, pruebas offline e integración. Abre `reports/quality-report.md` y conserva la fecha de esa ejecución.
3. Comprueba que los dos endpoints `/health` respondan.
4. Utiliza una cuenta de demostración creada por ti. No muestres cuentas reales, SQL privados, `.env`, cookies ni tokens.
5. Comprueba Internet si vas a mostrar sprites externos. Comprar y preparar combates usan datos locales; las imágenes siguen teniendo proveedores externos.

## Recorrido sugerido de 8–10 minutos

| Momento   | Demostración                                                              | Idea de calidad                             |
| --------- | ------------------------------------------------------------------------- | ------------------------------------------- |
| 1 minuto  | Explicar el ciclo catálogo → compra → batalla → cuidado                   | Requisitos y alcance                        |
| 1 minuto  | Mostrar el árbol de carpetas y el diagrama de arquitectura                | Responsabilidad única y separación de capas |
| 2 minutos | Comprar el primer Pokémon, abrir Hogar y enseñar el equipo automático     | Flujo funcional y consistencia              |
| 1 minuto  | Enseñar precio, saldo y rechazo de compra sin monedas                     | Validación y reglas en servidor             |
| 2 minutos | Ejecutar `npm test` o abrir el informe ya generado con fecha visible      | Evidencia reproducible y regresión          |
| 1 minuto  | Mostrar la suite de integración: regalo único, CSRF y premio no duplicado | Integridad, seguridad y pruebas negativas   |
| 1 minuto  | Mostrar `.env.example`, esquema limpio y pendientes                       | Configuración, privacidad y mejora continua |

No necesitas mostrar cientos de líneas. Abre un ejemplo pequeño de `BattleRules.php`, su test y el resultado correspondiente. Esto demuestra que una regla puede probarse sin depender de la interfaz o de MySQL.

## Preguntas que puedes responder

**¿Qué cambió además de la apariencia?** Se eliminaron rutas antiguas que evitaban el cobro, se separaron responsabilidades, se externalizó la configuración y se añadieron validaciones, CSRF, control de sesión, instalación segura e informes automáticos.

**¿Cómo sabes que no cobra dos veces?** Precio y débito se calculan en una transacción con bloqueo. Los movimientos de compra y batalla tienen claves únicas. La integración intenta repetir operaciones y verifica el saldo.

**¿Qué ocurre cuando algo falla?** La operación monetaria se revierte. La API devuelve un error controlado con un identificador. La interfaz informa el problema y permite recuperarse; las pruebas cubren varios rechazos e interrupciones.

**¿Qué se prueba sin Internet?** Reglas del mercado y batalla, práctica, tutorial, manejo de imágenes, progreso, validación, CSRF del cliente, sesión y caché con datos preparados.

**¿Qué significa el número de comprobaciones del informe?** Es lo que se ejecutó en esa revisión, incluyendo sintaxis, suites y estructura. No equivale a porcentaje de líneas cubiertas.

**¿Cumple toda ISO 25010?** No se declara certificación. Se usa el modelo como referencia para ordenar objetivos y evidencias. Los requisitos y los límites están en el plan de calidad.

**¿Qué falta para publicarlo?** Endurecer el despliegue, limitar intentos, completar auditoría de accesibilidad, pruebas de carga, derechos de recursos y persistencia robusta de partidas.

## Orden recomendado para abrir archivos

`README.md` → `docs/arquitectura.md` → `docs/calidad.md` → `backend/trainer-service/src/BattleRules.php` → `tests/battle-rules.php` → `reports/quality-report.md`.

El profesor puede ejecutar los mismos comandos. Esa reproducibilidad es parte de la entrega.
