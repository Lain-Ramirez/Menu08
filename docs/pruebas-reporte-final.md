# Reporte final de pruebas — Fase 5

Consolida las diez rondas de prueba de la Fase 5, ejecutadas contra **https://adso.menu08.com**
entre el 3 y el 12 de septiembre de 2026, con `curl` y sesiones reales de cada rol. Cierra el issue
#25: agrega la ronda de seguridad a lo ya probado en CARTA, CAJA y el Sistema de Visualización de
Producción, consolida los defectos encontrados en toda la fase y confirma cuáles quedaron
corregidos y reverificados.

No usa la nomenclatura `docs/pruebas/registro-de-defectos.md` que describían los issues de esta
fase: esa carpeta nunca se creó en las rondas anteriores, que documentaron cada defecto en su
propio archivo `docs/pruebas-*.md`. Este reporte mantiene esa convención y le agrega los
identificadores `DEF-xx` que pedía el issue, a modo de índice.

## Resumen por módulo

| Módulo | Documento | Comprobaciones | Fallos | Defectos |
|---|---|---|---|---|
| CARTA — autenticación, roles y CSRF | [`pruebas-autenticacion.md`](pruebas-autenticacion.md) | 19 | 0 | — |
| CARTA — carta pública y código QR | [`pruebas-carta-qr.md`](pruebas-carta-qr.md) | 26 | 0 | DEF-01, DEF-02 |
| CARTA — panel de catálogo | [`pruebas-panel-carta.md`](pruebas-panel-carta.md) | 20 | 0 | DEF-03 |
| CARTA — agenda de paradas | [`pruebas-agenda-paradas.md`](pruebas-agenda-paradas.md) | 55 | 0 | — |
| CAJA — turno | [`pruebas-caja-turno.md`](pruebas-caja-turno.md) | 9 | 0 | DEF-04 |
| CAJA — registro de órdenes | [`pruebas-caja-orden.md`](pruebas-caja-orden.md) | 14 | 0 | — |
| SVP — cambio de estado (integración CAJA → SVP) | [`pruebas-svp-estado.md`](pruebas-svp-estado.md) | 27 | 0 | DEF-05 |
| SVP — servicio JSON de órdenes en curso | [`pruebas-svp-ordenes.md`](pruebas-svp-ordenes.md) | 37 | 0 | DEF-06 |
| Transversal — método HEAD del enrutador | [`pruebas-enrutador-head.md`](pruebas-enrutador-head.md) | 30 | 0 | — |
| **Seguridad** (issue #25) | [`pruebas-seguridad.md`](pruebas-seguridad.md) | 27 | 0 | — |

**Total: 264 comprobaciones en las diez rondas de esta fase, 0 fallos abiertos.**

Fuera del alcance de CARTA/CAJA/SVP, el submódulo móvil tiene su propia evidencia en
[`pruebas-movil-servicios.md`](pruebas-movil-servicios.md) (31 peticiones, 0 fallos) y
[`pruebas-unitarias-movil.md`](pruebas-unitarias-movil.md) (49 comprobaciones, 0 fallos); no se
duplica aquí porque pertenece a un issue y una evidencia SENA distintos.

## Registro de defectos

| Id | Defecto | Severidad | Módulo | Estado |
|---|---|---|---|---|
| DEF-01 | El generador de código QR escribía los bits de información de formato con fila y columna intercambiadas; el símbolo se veía bien pero ningún lector lo decodificaba | Alta | CARTA | **Corregido y reverificado** — [`pruebas-carta-qr.md`](pruebas-carta-qr.md#1-la-información-de-formato-del-qr-transpuesta) |
| DEF-02 | `Vista::renderizar()` usaba `extract($datos, EXTR_SKIP)` con una variable local `$archivo`; una vista que pasara una clave `archivo` recibía la ruta interna de la plantilla en vez de su propio dato, y esa ruta absoluta del servidor quedaba expuesta en el HTML servido | **Alta (seguridad — divulgación de información)** | CARTA | **Corregido y reverificado** — [`pruebas-carta-qr.md`](pruebas-carta-qr.md#2-colisión-de-variables-en-el-renderizador-de-vistas) |
| DEF-03 | El validador de precio interpretaba `12.500` como `12,5` en vez de doce mil quinientos, la forma en que cualquier dueño de food truck en Colombia escribe sus precios | Media | CARTA | **Corregido y reverificado** — [`pruebas-panel-carta.md`](pruebas-panel-carta.md#el-defecto-que-encontraron-estas-pruebas) |
| DEF-04 | Al rechazar la apertura de un segundo turno, la vista se repintaba en la rama de «cerrar turno» y el aviso de error —registrado contra un campo que ahí no existe— no se mostraba en ninguna parte | Media | CAJA | **Corregido y reverificado** — [`pruebas-caja-turno.md`](pruebas-caja-turno.md#defecto-encontrado-y-corregido) |
| DEF-05 | El rol `produccion` no tenía de dónde generar un testigo CSRF propio; dependía por accidente del que arrastraba la sesión desde el ingreso, y ese testigo vence a los 120 minutos, dejando el tablero sin forma de avanzar órdenes hasta reingresar | **Alta (seguridad — dependencia frágil del CSRF)** | SVP | **Corregido y reverificado** — [`pruebas-svp-estado.md`](pruebas-svp-estado.md#defecto-encontrado-y-corregido) |
| DEF-06 | El servicio `GET /svp/ordenes` no distinguía «turno abierto sin órdenes pendientes» de «no hay ningún turno abierto»: los dos casos devolvían `turno: null` | Media | SVP | **Corregido y reverificado** — [`pruebas-svp-ordenes.md`](pruebas-svp-ordenes.md#defecto-encontrado-y-corregido) |

**Los seis defectos de la fase están cerrados.** Ninguno quedó pendiente de reejecutar: cada uno se
corrigió y se volvió a probar contra el servidor desplegado dentro de su propio documento, así que
la tarea de «reejecutar los casos fallidos» del issue #25 no encontró nada abierto que repetir.

La ronda de seguridad (issue #25) no abrió ningún defecto nuevo (`DEF-07` en adelante no existe),
pero sí revisó con lupa dos de los seis ya cerrados por su relevancia de seguridad: DEF-02
(divulgación de una ruta interna del servidor) y DEF-05 (una ruta autenticada que se quedaba sin
defensa CSRF utilizable). Los dos siguen corregidos, confirmado sobre el código y sobre el servidor
en esta ronda.

## Lo que esta fase no cubre

Reunido de los «no cubre» de cada documento, para no repetir el detalle:

- **No hay límite de intentos de ingreso** (fuerza bruta sobre `/ingresar`) —
  [`pruebas-autenticacion.md`](pruebas-autenticacion.md#lo-que-no-cubre-esta-prueba).
- **Ningún módulo se probó desde un teléfono real**; todo se midió con `curl` y con la emulación de
  Chrome sin interfaz —
  [`pruebas-carta-qr.md`](pruebas-carta-qr.md#lo-que-estas-pruebas-no-cubren).
- **Dos pantallas del SVP avanzando la misma orden a la vez** se razonó pero no se disparó de
  verdad en paralelo — [`pruebas-svp-estado.md`](pruebas-svp-estado.md#lo-que-estas-pruebas-no-cubren-todavía).
- **Qué debe ver producción cuando el cajero cierra el turno con órdenes en curso** sigue siendo
  una decisión de producto pendiente, no un defecto —
  [`pruebas-svp-ordenes.md`](pruebas-svp-ordenes.md#lo-que-estas-pruebas-no-cubren-todavía).
- **La subida de un archivo ejecutable disfrazado de imagen** no se repitió en vivo en la ronda de
  seguridad por una restricción del entorno de pruebas —
  [`pruebas-seguridad.md`](pruebas-seguridad.md#lo-que-estas-pruebas-no-cubren); se apoya en la
  prueba ya hecha en `pruebas-panel-carta.md` más una auditoría de código.
- **XSS solo se probó sobre `nombre` y `descripcion` de producto**, no sobre el resto de campos de
  texto libre del catálogo —
  [`pruebas-seguridad.md`](pruebas-seguridad.md#lo-que-estas-pruebas-no-cubren).
- **Inyección SQL bajo concurrencia** no se probó; el alcance fue por sentencia individual.

## Residuos de las pruebas que quedan en el entorno de demostración

Ningún residuo altera un resultado ya reportado, pero conviene que quien siga trabajando en
`adso.menu08.com` los conozca:

- **Producto id 35**, categoría «Para picar», nombre `<script>alert(1)</script>`, marcado no
  disponible — de la prueba de XSS en `pruebas-seguridad.md`.
- **Orden `T5-017`** (comprobante `/caja/comprobante/45`), $ 14.900, con la nota «PRUEBA SEGURIDAD
  CP-SEG - descartar» — de la prueba de inyección SQL sobre el armado de la orden.

Ninguno de los dos se puede borrar desde la aplicación: los productos solo se dan de baja lógica y
las órdenes no tienen ruta de borrado, por diseño (`orden_items` copia nombre y precio, y una
orden ya registrada no debe desaparecer). Si se quieren quitar del todo, es una operación directa
en la base.
