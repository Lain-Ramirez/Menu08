# Plan de pruebas

Ordena el aseguramiento de la calidad de los tres módulos —CARTA, CAJA y el Sistema de
Visualización de Producción— y describe el ambiente en el que se ejecutan los casos. Lo usan las
dos personas del equipo, así que todo lo que aquí se fija —nomenclatura, plantillas, ambiente— es
lo que hace que dos registros hechos por separado se puedan leer juntos.

No es un listado de casos. Los casos viven en `docs/pruebas/casos/`, uno por módulo y con la
plantilla de [`plantilla-caso-de-prueba.md`](plantilla-caso-de-prueba.md); lo que falla se anota en
[`registro-de-defectos.md`](registro-de-defectos.md). Los tres primeros ejecutados son
[`casos/carta.md`](casos/carta.md), del issue #22, [`casos/caja.md`](casos/caja.md), del issue #23,
y [`casos/svp.md`](casos/svp.md), del issue #24.

---

## 1. Alcance por módulo

| Grupo | Qué entra | Qué queda fuera |
|---|---|---|
| **CP-CARTA** | Carta pública por slug, categorías y productos con baja lógica, agenda de paradas y parada vigente, código QR, panel de carta del rol `food_truck` | El diseño gráfico del truck: las fotos y los textos los pone su dueño |
| **CP-CAJA** | Apertura y cierre de turno, cuadre, venta y cobro con los tres medios de pago, comprobante imprimible de 80 mm, historial de turnos | La impresora física: se prueba la salida del navegador y el PDF, no el rollo térmico |
| **CP-SVP** | Tablero de producción, sondeo y refresco sin recarga, avance de estado, realce de demoras, servicios JSON `GET /svp/ordenes` y `POST /svp/orden/{id}/estado` | La pantalla pública de turnos de la ventanilla, que llega en su propio issue |
| **CP-SEG** | Puerta de cada módulo por rol, token CSRF en todo POST, aislamiento por `food_truck_id`, respuestas JSON de los servicios al negar el paso | Pruebas de carga y de penetración: fuera del alcance del proyecto formativo |

El módulo móvil tiene sus propias comprobaciones ya ejecutadas en
[`../pruebas-movil-servicios.md`](../pruebas-movil-servicios.md) y
[`../pruebas-unitarias-movil.md`](../pruebas-unitarias-movil.md); entra en CP-SEG solo por la
puerta de `POST /movil/ubicacion`.

## 2. Tipos de prueba

| Tipo | Cómo se ejecuta | Dónde queda |
|---|---|---|
| **Funcional de interfaz** | A mano, en navegador, siguiendo los pasos del caso | Caso con su evidencia en `evidencias/<módulo>/` |
| **De servicio** | Postman o `curl` contra las rutas JSON, con la colección de [`../../POSTMAN.md`](../../POSTMAN.md) | Caso con el cuerpo de la respuesta pegado en «resultado obtenido» |
| **Unitaria** | `php menu08_app/pruebas/ejecutar.php` | Salida del ejecutor; hoy **49 comprobaciones, 0 fallos** |
| **De presentación** | La misma pantalla a los tres anchos de referencia y en los dos temas | Captura por ancho |
| **De impresión** | Vista previa y «Guardar como PDF» del comprobante en los dos navegadores | El PDF, junto a la captura |

Las pruebas unitarias cubren hoy lo que no se puede comprobar desde la interfaz sin inventar
fechas: el validador de coordenadas, el de horas y la parada que cruza la medianoche. No
sustituyen a los casos funcionales, los complementan.

## 3. Nomenclatura

```
CP-CARTA-01   caso de prueba del módulo CARTA
CP-CAJA-07    caso de prueba del módulo CAJA
CP-SVP-03     caso de prueba del Sistema de Visualización de Producción
CP-SEG-02     caso de prueba transversal de acceso y seguridad
DEF-14        defecto registrado
```

Cuatro reglas, para que dos personas numerando por separado no choquen:

1. **Dos dígitos y numeración corrida** dentro de cada grupo, empezando en `01`.
2. **El identificador no se reutiliza nunca.** Un caso que se retira deja su número muerto: si
   alguien busca `CP-CAJA-05` en una evidencia vieja tiene que encontrar el mismo caso.
3. **El identificador no cambia aunque cambie el texto del caso.** Si el caso cambia tanto que ya
   no es el mismo, se retira y se abre otro con número nuevo.
4. **El defecto se referencia desde el caso que lo encontró**, y el caso desde el defecto. Sin ese
   par, un defecto corregido no se puede volver a comprobar.

Los nombres de archivo de evidencia llevan el identificador delante:
`CP-CAJA-07-cierre-con-faltante.png`.

## 4. Ambiente

### 4.1 El servidor

Las pruebas se ejecutan contra **`https://adso.menu08.com`**, que es el mismo software que se
entrega. Lo que hay debajo, comprobado contra el servidor y anotado en
[`../despliegue.md`](../despliegue.md):

| Pieza | Versión | Nota |
|---|---|---|
| PHP | **8.3.33** | No 8.2, aunque cPanel diga otra cosa |
| Servidor web | **LiteSpeed** | Lee los `.htaccess` de Apache: la reescritura de `publico/.htaccess` funciona igual que con `mod_rewrite`, y es lo que hace que las rutas sin archivo lleguen al front controller |
| MySQL | **8.0.46** | Compilación `cll-lve` de CloudLinux |
| Cotejamiento | `utf8mb4_unicode_ci` | En las nueve tablas y las 25 columnas de texto |

La configuración sale de `menu08_app/configuracion/configuracion.ejemplo.php` copiada a
`menu08_app/configuracion/configuracion.php`, que **no se versiona** porque lleva la contraseña de
MySQL. Los valores que cambian respecto de la plantilla están en la tabla de `despliegue.md`; para
pruebas importan tres: `entorno` en `produccion` —los errores van a la bitácora, no a la pantalla—,
`url_base` en `https://adso.menu08.com` y `sesion.solo_https` en `true`.

### 4.2 Una sola base, dos food trucks

**El ambiente de pruebas no vive en una base aparte.** El usuario del hosting no puede crear
bases —lo recoge `despliegue.md`, y por eso al importar hay que quitar las sentencias
`CREATE DATABASE` y `USE` de `esquema.sql` o importar desde phpMyAdmin con la base ya
seleccionada—. Vive en la misma base que la aplicación, **aislado por `food_truck_id`**, que es el
invariante del proyecto: toda consulta de panel, caja y producción filtra por el food truck de la
sesión, y ese identificador nunca se toma de la URL.

Eso convierte una limitación del hosting en la prueba más barata que tiene el proyecto: **si el
aislamiento falla, se ve**. Un producto del truck de pruebas asomando en la carta de Festín
Rodante es un defecto de severidad alta, no un detalle cosmético.

| | Food truck de demostración | Food truck de pruebas |
|---|---|---|
| Nombre | Festín Rodante | **Truck de Pruebas** |
| `slug` | `festin-rodante` | `truck-de-pruebas` |
| Carta pública | `/carta/festin-rodante` | `/carta/truck-de-pruebas` |
| Correos | `<rol>@menu08.local` | `pruebas.<rol>@menu08.local` |
| Lo carga | `datos_iniciales.sql` y `datos_festin_rodante.sql` | `datos_pruebas.sql` |
| Se puede borrar y rehacer | **No.** Su catálogo lo define su dueño | **Sí**, tantas veces como haga falta |

Regla de oro: **los casos se ejecutan siempre con los usuarios `pruebas.*`**. Festín Rodante solo
se toca para comprobar que *no* cambió.

### 4.3 Navegadores y resoluciones

| | Mínimo | Dónde importa |
|---|---|---|
| **Escritorio** | **1280 × 720** | Es el ancho al que el tablero del SVP reparte sus tres columnas y la pantalla de venta sus dos zonas |
| **Tableta** | **768** de ancho | Punto en el que el marco del panel colapsa la navegación |
| **Teléfono** | **360 × 640** | El ancho al que se abre la carta desde el QR, en la fila de la ventanilla |

| Navegador | Versión | Qué se ejecuta ahí |
|---|---|---|
| **Chrome** de escritorio | La estable del día | Todos los grupos, y la impresión del comprobante con «Guardar como PDF» |
| **Firefox** de escritorio | La estable del día | Todos los grupos, y la segunda comprobación de impresión |
| **Chrome** en Android | La estable del día | CP-CARTA a 360 px: la carta pública se abre desde el QR, en un teléfono |

Cada pantalla se comprueba además **en claro y en oscuro**, añadiendo la clase `o` al elemento
`<html>` desde las herramientas del navegador. Ninguna hoja del proyecto escribe un color literal:
todos salen de los 31 tokens de `md3.css`, y el modo oscuro no tiene una sola regla propia, así que
lo que se comprueba es justamente que ninguna vista se saliera del contrato.

### 4.4 Quién ejecuta cada grupo

| Grupo | Rol | Usuario | Ruta de entrada |
|---|---|---|---|
| CP-CARTA, parte pública | **sin sesión** | — | `/carta/truck-de-pruebas` |
| CP-CARTA, panel | `food_truck` | `pruebas.foodtruck@menu08.local` | `/panel` |
| CP-CAJA | `cajero` | `pruebas.cajero@menu08.local` | `/caja` |
| CP-SVP | `produccion` | `pruebas.produccion@menu08.local` | `/svp` |
| CP-SEG | los cuatro roles y **sin sesión** | los `pruebas.*` y el de plataforma | la ruta que se esté probando |

La contraseña de todos los usuarios de prueba es la misma que la de los de demostración,
`Menu08*Demo2026`, y está anotada en `datos_iniciales.sql`. Es una contraseña de prototipo
formativo: **se cambia antes de que el sitio deje de serlo**.

## 5. Datos de prueba

`menu08_app/basedatos/datos_pruebas.sql` deja el banco listo. Comprobado importándolo sobre el
esquema ya cargado —`esquema.sql` y después `datos_iniciales.sql`— en un MySQL 8 limpio y vacío,
fuera del servidor. La comprobación se hizo contra **MySQL 8.4** y el hosting corre **8.0.46**: el
archivo no usa sintaxis propia de ninguna de las dos versiones —`SET`, `DELETE` e `INSERT`
corrientes—, pero la importación en el servidor conviene mirarla la primera vez.

| Pieza | Cuántas | Para qué |
|---|---|---|
| Food truck | 1 | `Truck de Pruebas`, activo |
| Usuarios | 3 | Uno por rol de operación: `food_truck`, `cajero`, `produccion` |
| Categorías | 5 | Una **desactivada**, para comprobar que no asoma en la carta |
| Productos | 11 | Uno **no disponible**, uno de precio `0.00`, uno de `18750.50` y uno de nombre deliberadamente largo |
| Ubicaciones | 4 | Una **desactivada** y otra que **cruza la medianoche** (`18:00` → `01:00`) |
| Turnos y órdenes | 0 | El turno se deja cerrado a propósito: los casos de CAJA abren el suyo |

Los datos raros no son adorno. El producto gratuito y el de `18750.50` comprueban el cálculo del
total en centavos; el nombre largo, el corte en pantalla a 360 px; la parada nocturna, la única
regla de fechas del proyecto que no se puede probar a ojo.

Si al ejecutar un caso falta un dato, **se amplía este archivo y se anota en el caso**, en lugar de
crear la fila a mano por la interfaz: un banco que solo existe en la cabeza de quien lo montó no
se puede volver a levantar.

## 6. Restauración del ambiente entre ciclos

`datos_pruebas.sql` **es reejecutable**: borra su propio food truck con todo lo que cuelga de él
y lo vuelve a crear, en el orden inverso a las dependencias porque las llaves hacia `food_trucks`
son `ON DELETE RESTRICT`.

```bash
mysql -u USUARIO -p NOMBRE_BASE < menu08_app/basedatos/datos_pruebas.sql
```

Desde phpMyAdmin, que es la vía normal en este hosting: pestaña **Importar**, con la base ya
seleccionada, y subir el archivo tal cual.

| Lo que borra | Lo que **no** toca |
|---|---|
| El food truck de pruebas y sus usuarios, categorías, productos y paradas | Festín Rodante y todas sus filas |
| Sus turnos de caja, sus órdenes y los renglones de esas órdenes | Los estados de orden de `estados_orden` |

Comprobado de punta a punta: con un turno abierto, una orden y sus renglones creados sobre el
truck de pruebas, la reimportación deja el banco en `3 usuarios / 5 categorías / 11 productos /
0 turnos / 0 órdenes / 0 renglones` y Festín Rodante exactamente igual que antes.

**Cuándo restaurar:** al empezar un ciclo completo, y cada vez que un caso deje el banco en un
estado del que otro caso no pueda partir —un turno abierto que estorba, un producto desactivado a
mitad de la tanda—. No hace falta entre casos que solo leen.

## 7. Criterios de entrada

No se empieza un ciclo hasta que se cumplen los cinco:

1. La versión que se va a probar está **desplegada** en `adso.menu08.com` y responde: portada,
   una ruta con parámetro y un 404 con su vista propia.
2. `php menu08_app/pruebas/ejecutar.php` pasa **sin fallos**.
3. El banco de pruebas está **recién restaurado**.
4. Los casos del ciclo están **escritos y numerados** antes de ejecutarlos. Un caso improvisado
   sobre la marcha no se puede repetir, y un resultado que no se puede repetir no es evidencia.
5. Los defectos **abiertos de severidad alta** del ciclo anterior están corregidos o
   explícitamente aplazados por el equipo.

## 8. Criterios de salida

Un ciclo se da por terminado cuando:

1. **Todos** los casos del alcance están ejecutados, con resultado obtenido escrito y estado
   asignado. Un caso sin ejecutar se marca `Bloqueado` con su motivo; no se deja en blanco.
2. **Ningún defecto abierto de severidad alta.** Un defecto alto sin corregir cierra el ciclo en
   falso.
3. Los defectos de severidad media y baja que queden abiertos están **registrados con responsable**
   y aceptados por el equipo.
4. Cada caso que falló tiene su **defecto enlazado**, y cada defecto corregido tiene su caso
   **reejecutado** con la evidencia nueva.
5. Las evidencias están **en su carpeta** y nombradas con el identificador del caso.

## 9. Dónde van las evidencias

```
docs/pruebas/evidencias/
├── carta/        CP-CARTA-xx
├── caja/         CP-CAJA-xx
├── svp/          CP-SVP-xx
└── seguridad/    CP-SEG-xx
```

Capturas en PNG, respuestas de servicio pegadas como texto dentro del caso, y el comprobante de
CAJA también en PDF. Lo que se captura es la pantalla entera, con la barra de direcciones a la
vista: una captura recortada no demuestra contra qué servidor se ejecutó.

## 10. Lo que ya está probado

Estas comprobaciones se ejecutaron mientras se construían los módulos y se conservan como
antecedente. No sustituyen a los casos de este plan —no siguen su plantilla ni su nomenclatura—,
pero dicen qué está ya cubierto y con qué resultado:

| Documento | Qué cubre |
|---|---|
| [`../pruebas-autenticacion.md`](../pruebas-autenticacion.md) | Ingreso, salida y puerta de los roles |
| [`../pruebas-panel-carta.md`](../pruebas-panel-carta.md) | Panel de categorías y productos |
| [`../pruebas-carta-qr.md`](../pruebas-carta-qr.md) | Carta pública y código QR |
| [`../pruebas-agenda-paradas.md`](../pruebas-agenda-paradas.md) | Agenda de paradas y parada vigente |
| [`../pruebas-caja-turno.md`](../pruebas-caja-turno.md) | Apertura, cierre y cuadre del turno |
| [`../pruebas-caja-orden.md`](../pruebas-caja-orden.md) | Registro de la venta y sus renglones |
| [`../pruebas-svp-ordenes.md`](../pruebas-svp-ordenes.md) | Servicio de órdenes en curso |
| [`../pruebas-svp-estado.md`](../pruebas-svp-estado.md) | Avance de estado y sus negativas |
| [`../pruebas-enrutador-head.md`](../pruebas-enrutador-head.md) | Enrutador y peticiones `HEAD` |
| [`../pruebas-movil-servicios.md`](../pruebas-movil-servicios.md) | Servicios del módulo móvil |
| [`../pruebas-unitarias-movil.md`](../pruebas-unitarias-movil.md) | Las pruebas unitarias del ejecutor |

---

**Issue #21 · Fase 5 - Pruebas**
