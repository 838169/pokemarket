# Pruebas y evidencias

## Ejecutar

```powershell
npm ci --ignore-scripts
npm run format:check
npm test
```

`npm test` no abre MySQL ni consume PokéAPI. Revisa sintaxis de PHP/JavaScript, estructura y recursos, y ejecuta las suites offline. Las pruebas de caché crean y retiran un directorio temporal propio. Las de sesión usan una sesión aislada, sin cuenta real.

Para comprobar las operaciones reales:

```powershell
# Con MySQL, esquema y servicios ya disponibles:
npm run test:integration
```

La integración crea cuentas con correo `@example.invalid`, utiliza únicamente sus identificadores y elimina sus datos en `finally`. No usa credenciales de personas reales. La prueba prepara un estado de combate en su propia sesión para comprobar el turno final y también inicia un combate real con el compañero comprado. No declara que ese estado preparado sea una partida jugada manualmente de principio a fin.

## Qué comprueba cada suite

| Archivo en `tests/`       | Objetivo                                                                                                                                 |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `market-rules.php`        | Las 1025 especies tienen precios coherentes, categoría, seis estadísticas y movimientos; cualquier inicial puede comprarse con el regalo |
| `battle-rules.php`        | Inmunidad, ventaja, doble resistencia/debilidad, daño físico/especial, variación y reservas                                              |
| `battle-practice.cjs`     | Práctica completa, daño y coste de cambios, victoria y rechazo de acciones inválidas                                                     |
| `battle-tutorial.cjs`     | Cinco pasos ordenados; una acción ajena al paso no permite saltarlo                                                                      |
| `media-ui.cjs`            | Mejora de imagen, fuentes de respaldo, cargas obsoletas y movimiento reducido                                                            |
| `detail-progress.cjs`     | Progreso por etapas, JSON fragmentado, Unicode, errores e interrupción                                                                   |
| `detail-view.php`         | Lectura desde caché sin red y conservación independiente de datos de combate                                                             |
| `security-rules.php`      | Tipos y tamaños de texto, caracteres de control, token y expiración de sesión                                                            |
| `api-security.cjs`        | El cliente obtiene y envía CSRF en mutaciones, sin bloquear registro/login                                                               |
| `economy-integration.php` | Autenticación, contratos HTTP, saldo, compra, comida, hábitat, bonus y premios sobre MySQL real                                          |

`tools/check-project.mjs` detecta recursos de páginas que no existan, falta de documentos, archivos retirados que reaparezcan, datos de usuarios dentro del esquema y páginas temporales de prueba olvidadas.

## Informes

- `reports/quality-report.md`: resumen legible para la exposición.
- `reports/quality-report.json`: mismo resultado con datos estructurados.
- La salida y el código de retorno permiten ejecutar la misma puerta en CI.
- Un resultado offline no demuestra que MySQL esté disponible. Un resultado de integración presupone los servicios locales y registra sus comprobaciones reales.
- Los informes no incluyen contraseñas, cookies ni tokens. No sustituyen una medición formal de cobertura.

## Lista manual para cambios visuales

Registrar fecha, navegador, ancho y resultado. No marcar una fila como aprobada solo porque exista un test automático diferente.

| Recorrido                          | Resultado esperado                                                                              |
| ---------------------------------- | ----------------------------------------------------------------------------------------------- |
| Registro → login → catálogo        | Entrada clara al regalo y a los primeros compañeros                                             |
| Confirmar compra                   | Precio y saldo posterior visibles; botón bloqueado durante la petición                          |
| Abrir Hogar                        | El compañero aparece, se puede seleccionar y se muestra su energía                              |
| Alimentar tras batalla             | Inventario baja una unidad, energía se recupera y vínculo aumenta                               |
| Cambiar hábitat                    | La elección persiste después de recargar                                                        |
| Filtrar y buscar sin coincidencias | Resultado claro, sin quedarse en «Cargando» y con recuperación                                  |
| Tutorial                           | Instrucción y objetivo de cada paso visibles; progreso real hasta victoria                      |
| Teclado                            | Navegación sin ratón y foco visible; diálogo de compra no deja escapar el foco                  |
| Móvil                              | Sin desplazamiento horizontal de la página; las categorías pueden desplazarse en su propia fila |
| Movimiento reducido                | Se respetan las preferencias del sistema                                                        |
| Cerrar sesión                      | El saldo y el Hogar dejan de ser accesibles                                                     |

Las revisiones visuales realizadas durante el desarrollo se describen en `docs/game/ECONOMY.md`. Esta entrega de calidad mantiene esa presentación; cualquier nueva ejecución manual debe registrar su propia evidencia.

## Ampliación: bienvenida, escenarios y apuestas

La integración crea una cuenta temporal y verifica: regalo al finalizar, repetición sin doble abono; bloqueo de escenarios, precio ignorando manipulaciones del cliente, compra repetida y asignación; ofertas estables hasta vencer; rechazo de oferta inventada; débito de entrada; un solo duelo pendiente; recuperación tras logout/login; turnos persistidos y rechazo de versiones antiguas; victoria y abandono sin premio ordinario. Solo se modifican fondos de la cuenta temporal de prueba. El bloque `finally` elimina sus datos.

`adventure-rules.php` comprueba dificultad creciente, retornos y disponibilidad de fondos/música. `home-music.cjs` comprueba audio voluntario, cambio de pista, volumen y pausa en pestañas ocultas. `media-ui.cjs` comprueba que el Catálogo sigue estático aunque las animaciones globales estén activadas.

Revisión visual manual pendiente si el navegador local está deshabilitado en Work: comprobar bienvenida en móvil y escritorio, compra de escenarios, controles de música, cuatro tarjetas de modos y confirmación de apuesta. No se considera equivalente una prueba de sintaxis a una revisión visual.
