# Plan de calidad y trazabilidad

## Objetivo y alcance

Que otra persona pueda instalar, comprender, verificar y mantener el proyecto sin depender de explicaciones del autor. La calidad se demuestra mediante requisitos, controles concretos y resultados de pruebas; no solo con la apariencia de las pantallas.

Se toma como referencia conceptual el [modelo de calidad de producto ISO/IEC 25010:2023](https://www.iso.org/standard/78176.html) y como guía de controles web [OWASP ASVS 5.0](https://asvs.owasp.org/). Esta selección **no es una evaluación completa ni una certificación**. La tabla siguiente agrupa necesidades del proyecto; no reproduce íntegramente la norma.

## Requisitos y evidencia

| ID     | Requisito de calidad o funcional | Criterio de aceptación                                                      | Evidencia                                                        |
| ------ | -------------------------------- | --------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| RF-01  | Autenticación y privacidad       | Un visitante no puede consultar Hogar ni saldo                              | Integración HTTP: respuestas 401                                 |
| RF-02  | Compra correcta                  | Precio calculado en servidor, saldo suficiente y especie única              | Precio manipulado, compra duplicada y saldo insuficiente         |
| RF-03  | Bienvenida única                 | Regalo de 1000 y tres bayas no se repite                                    | Relectura de cartera                                             |
| RF-04  | Compra inicial utilizable        | Primera compra crea un equipo con el compañero                              | Integración de compra y arranque real                            |
| RF-05  | Recompensas íntegras             | Victoria y derrota pagan una vez; abandono no paga                          | Liquidación repetida y turno HTTP real                           |
| RF-06  | Cuidado consistente              | Comida consume inventario, recupera energía y respeta propiedad             | Alimentación, límites y hábitat                                  |
| RNF-01 | Mantenibilidad                   | Sin rutas antiguas activas ni recursos locales rotos                        | `check-project.mjs`, formato y revisión de estructura            |
| RNF-02 | Capacidad de prueba              | Reglas principales ejecutables sin DB ni Internet                           | Suites offline de catálogo, batalla, tutorial, imágenes y sesión |
| RNF-03 | Seguridad de operaciones         | Mutaciones autenticadas exigen token CSRF y origen permitido                | Pruebas HTTP y cliente JavaScript                                |
| RNF-04 | Entradas controladas             | Arrays, tamaños y parámetros de catálogo inválidos son rechazados           | Validación pura y pruebas de API                                 |
| RNF-05 | Integridad de datos              | Error de negocio no cobra ni deja una compra parcial                        | Transacciones y comprobación de saldo tras rechazos              |
| RNF-06 | Fiabilidad y diagnóstico         | Fallo HTTP controlado incluye identificador, sin SQL ni claves              | `JsonResponse`, manejador de excepciones y health                |
| RNF-07 | Portabilidad local               | Instalación repetible sin importar usuarios reales                          | Esquema limpio, `.env.example` y `setup.php` sin reinicio        |
| RNF-08 | Interacción accesible            | Foco visible, acciones con nombre, móvil sin desborde y movimiento reducido | Revisión visual/manual y pruebas de imágenes                     |
| RNF-09 | Eficiencia                       | Catálogo y preparación de combate no requieren peticiones a PokéAPI         | Índice local y pruebas de inicio real                            |
| RNF-10 | Automatización                   | Un fallo devuelve código distinto de cero y deja informe                    | `npm test` y `test:integration`                                   |

## Puerta de calidad para cada entrega

1. `npm ci --ignore-scripts` termina correctamente.
2. `npm run format:check` no encuentra cambios pendientes.
3. `npm test` termina sin fallos.
4. Con servicios y MySQL: `npm run test:integration` termina sin fallos.
5. La lista manual de `pruebas.md` se revisa cuando una modificación afecta las pantallas.
6. No se incluyen cuentas reales, respaldos, `.env`, cachés ni páginas temporales de prueba.

Los informes registran fecha UTC, modo, resultado de cada comprobación y tiempo observado. **Un conteo de comprobaciones no es un porcentaje de cobertura**. Los tiempos del equipo local no se convierten en un compromiso de rendimiento de producción.

## Gestión de defectos

Para registrar un defecto incluye: pantalla o endpoint, pasos para reproducir, resultado esperado, resultado real, gravedad, identificador de solicitud si existe y evidencia sin credenciales. Añade una prueba de regresión cuando el defecto afecte una regla de negocio.

Gravedad alta: pérdida de saldo, acceso entre cuentas o imposibilidad de comprar/combatir. Media: filtro incorrecto, guía bloqueada o datos inconsistentes sin pérdida. Baja: texto, alineación o presentación secundaria.

## Riesgos y pendientes conocidos

| Riesgo o límite                 | Tratamiento actual / pendiente                                                                                |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Imágenes y audio de terceros    | Respaldo visual; documentar procedencia. Pendiente revisar derechos antes de distribución comercial.          |
| Sesión local de batalla         | Adecuada para la exposición. Pendiente persistir combates en DB para tolerar pérdida de sesión.               |
| Acceso público en Internet      | Pendiente endurecer HTTPS, limitación de intentos de acceso y configuración de despliegue.                    |
| Carga concurrente a gran escala | Bloqueos y restricciones protegen el saldo; pendiente ensayo de carga y fallos de infraestructura.            |
| Accesibilidad completa          | Foco y movimiento reducido presentes; pendiente auditoría formal con lector de pantalla y personas usuarias.  |
| Compatibilidad                  | Configuración CI y pruebas locales; pendiente validar navegadores y sistemas ajenos a los realmente probados. |
| Esquemas históricos             | Instalación no destructiva; pendiente migración versionada completa si cambian tablas preexistentes.          |
| Juego oficial completo          | No implementa todos los estados, PP y efectos especiales. Se declara en la interfaz/documentación.            |

Los pendientes permiten defender el alcance con honestidad: el proyecto es una aplicación académica con controles verificables, no un servicio de producción certificado.

## Requisitos de la ampliación de aventuras

| ID     | Requisito               | Aceptación y evidencia                                                                                   |
| ------ | ----------------------- | -------------------------------------------------------------------------------------------------------- |
| RF-07  | Catálogo estático       | No carga GIF/WebP animado aunque la preferencia global esté activa; `media-ui.cjs`.                      |
| RF-08  | Bienvenida guiada       | Cuatro pasos opcionales. Regalo al terminar u omitir, con abono único en la API.                     |
| RF-09  | Expansiones comprables  | Precio del servidor, saldo suficiente, bloqueo y propiedad permanente; API real.                         |
| RF-10  | Apuestas íntegras       | Oferta vigente y propia, débito de entrada, pago de victoria una vez, abandono sin devolución; API real. |
| RF-11  | Continuidad de apuestas | Logout/login recupera el duelo y una versión antigua no sobrescribe ni vuelve a pagar; API real.         |
| RF-12  | Música controlable      | Activación voluntaria, volumen, detención al cambiar de escena y al ocultar pestaña; `home-music.cjs`.   |
| RNF-07 | Recursos sustituibles   | Siete prompts y rutas estables, fondos de respaldo y música local con licencia documentada.              |

Pendiente de revisión humana: composición visual en navegador, equilibrio de dificultad tras sesiones de juego y calidad artística de las imágenes que se generen en Gemini. Las pruebas automáticas no certifican esas cualidades.
