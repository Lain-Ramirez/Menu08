# Menu08 — guía para el asistente

Léela entera antes de escribir una línea. Este proyecto tiene convenciones cerradas: no son
preferencias de estilo, son decisiones ya tomadas y evaluadas. Cambiarlas por tu cuenta rompe la
coherencia de una entrega formativa.

## Las cuatro reglas que no se negocian

1. **El tablero de producción se llama SVP** — *Sistema de Visualización de Producción*, también
   **SV3**. Nunca uses la sigla genérica en inglés con que se conoce a este tipo de pantalla en
   cocina: no aparece en ningún artefacto del proyecto —código, comentarios, nombres de clase,
   documentación, commits, issues ni presentaciones— y así debe seguir. En rutas y clases va
   `svp` / `Svp`: `SvpControlador`, `/svp`, `vistas/svp/`.
2. **No hagas commits.** Deja los cambios en el árbol de trabajo y di qué tocaste. Los confirma el
   desarrollador, porque el repositorio firma con GPG y la firma tiene que ser suya. Un `git commit`
   tuyo sale firmado con su clave y a su nombre.
3. **Todo en español.** Carpetas, clases, métodos, variables, comentarios, mensajes de error,
   commits y documentación. Nunca `app/`, `src/`, `public/`, `config/`, `database/`, `storage/`.
4. **El único entorno de este proyecto es `adso.menu08.com`.** No consultes ningún otro dominio.

## Qué es este repositorio

Prototipo de **Menu08**, una plataforma de carta digital, venta y producción **para food trucks**.
Proyecto formativo del Tecnólogo en Análisis y Desarrollo de Software del SENA, ficha 3235887.

No es una plataforma para «negocios de alimentos» en general: **nada de restaurantes con mesas,
cafeterías ni locales fijos** en el lenguaje, el modelo de datos, los datos de ejemplo ni las
vistas. Un food truck no tiene local ni dirección fija: tiene una ventanilla, una fila y un punto
que cambia según el día. De ahí salen dos decisiones de modelo que verás por todas partes:

- La tabla es `ubicaciones` (agenda de paradas por día y franja horaria), **no** una columna de
  dirección. Una jornada nocturna cruza la medianoche: si `hora_fin <= hora_inicio`, cierra al día
  siguiente.
- La entrega es **por llamado de número en la ventanilla**, no en mesa. El QR va pegado en la
  ventanilla, no en una mesa.

El primer food truck, y el de demostración, es **Festín Rodante**.

| Módulo | Quién lo usa | Qué resuelve |
|---|---|---|
| **CARTA** | Cliente y administrador del truck | Carta digital por QR, agenda de paradas, panel de catálogo |
| **CAJA** | Cajero | Turno, armado de la orden, cobro y número de turno |
| **SVP** | Producción y cliente | Tablero interno del truck y pantalla pública de turnos |

**Orden de construcción: CARTA → CAJA → SVP.** CARTA primero porque es el catálogo del que CAJA
vende y cuyas órdenes muestra el SVP.

## Stack cerrado

PHP 8.2 con POO, **MVC propio escrito a mano**, PDO con sentencias preparadas, MySQL 8, HTML, CSS y
JavaScript sin bibliotecas, Apache con `mod_rewrite`. **Sin Composer, sin frameworks, sin paquetes
de terceros.** No propongas instalar nada: la restricción es del proyecto formativo.

El SVP se refresca **por sondeo con `fetch`**, no por WebSockets.

## Estructura

El repositorio es un **espejo del servidor**: las dos carpetas de primer nivel se llaman igual que
en el hosting y se copian tal cual.

```
menu08_app/              privada, fuera de toda raíz web
  publico/index.php        front controller, única puerta de entrada
  aplicacion/
    nucleo/                Enrutador, ConexionBD, Controlador, Vista, Sesion, Csrf,
                           Validador, ManejadorErrores, Bitacora, GestorImagenes, GeneradorQr
    controladores/         XxxControlador.php
    modelos/               Xxx.php, métodos estáticos
    vistas/                plantillas/ auth/ panel/ carta/ caja/ svp/
  configuracion/           configuracion.php NUNCA se versiona; rutas.php es la tabla de rutas
  basedatos/               esquema.sql y datos_iniciales.sql
  almacenamiento/bitacora/ registro de errores

ADSO.menu08.com/         pública, raíz del sitio: index.php puente, .htaccess, recursos/, subidas/
docs/                    documentación
```

Nada sensible vive en la carpeta pública, y la protección es **estructural**: no depende de una
regla de `.htaccess` que alguien pueda desactivar.

## Invariantes de la aplicación

Están ya implementados y probados. Si tocas código cerca de ellos, no los debilites:

- **El `food_truck_id` sale siempre de la sesión, nunca de la petición.** Es el filtro de toda
  consulta. Nadie administra el catálogo de otro cambiando un número en la URL.
- **Los precios se leen de `productos`**, dentro de la misma transacción que escribe la venta. Lo
  que el formulario diga sobre el importe se descarta.
- **El dinero se acumula en centavos con enteros** y solo se formatea al final. En coma flotante,
  una jornada de decenas de ventas descuadra la caja.
- **`orden_items` copia el nombre y el precio** del producto al momento de la venta. Cambiar un
  precio después no altera las órdenes ya registradas.
- **Todo POST que modifique datos pasa por CSRF.** El token se rota tras cada operación sensible,
  así que un formulario reenviado no vuelve a ejecutarse.
- **PDO sin emulación de preparadas.** Un marcador nombrado solo puede aparecer una vez por
  sentencia; si necesitas el mismo valor dos veces, usa dos nombres (`:ft`, `:ft2`).
- **Los servicios JSON responden JSON también al fallar.** `exigirRolApi()` da 401 sin sesión y 403
  con rol equivocado, y activa el modo JSON del manejador de errores. Un cliente que sondea no sabe
  leer una página HTML.

## Los issues no se rehacen

El trabajo se sigue en los issues del repositorio, agrupados por milestones de fase. **Cada issue
ya está redactado y tiene una estructura fija.** No los reescribas, no los reordenes y no crees
duplicados: complétalos.

```
> Orden de construcción / Depende de / Bloquea a
### Descripción
### Criterios de aceptación     (casillas)
### Tareas                      (casillas)
### Definición de hecho
### Evidencia SENA              (enlaces cruzados)
**Estimación** · **Fase**
```

Cuando trabajes un issue, **verifica criterio por criterio** y di con qué evidencia concreta
—código citado, salida real de un comando— lo das por cumplido. Un «cumple» sin evidencia no vale.

## Documentación

Cada funcionalidad terminada deja su rastro en `docs/`, y el patrón ya está establecido:

| Archivo | Para qué |
|---|---|
| `docs/nucleo.md`, `docs/basedatos.md`, `docs/despliegue.md` | Arquitectura, modelo de datos y despliegue |
| `docs/api-*.md` | Contrato de un servicio: ruta, método, parámetros, ejemplo de éxito y de error |
| `docs/pruebas-*.md` | Evidencia de pruebas: qué se comprobó, con qué resultado, y **qué quedó sin cubrir** |

Los `docs/pruebas-*.md` no son un adorno: son la evidencia de la entrega. Escríbelos con la salida
real, incluidos los intentos fallidos y su causa. Cierra siempre con una sección honesta de lo que
las pruebas **no** cubren.

## Commits

No los hagas tú (regla 2). Cuando el desarrollador confirme, el mensaje sigue este formato:

```
usuario@equipo 3septiembre10:50 - Corregir
```

Verbo en infinitivo: Implementar, Programar, Persistir, Exponer, Publicar, Corregir. **Sin
trailers de atribución de ningún tipo** — ni coautoría, ni enlaces de sesión, ni firmas de
asistente. En este proyecto firma el desarrollador, y solo él.

## Despliegue

El hosting **bloquea FTP, SFTP, SSH y cPanel** desde fuera de las direcciones autorizadas, y los
intentos fallidos hacen que se bloquee la dirección que insiste. **No lo intentes.** Lo único
alcanzable en remoto es HTTPS y MySQL en el 3306.

Los archivos se entregan en un **ZIP que sube y extrae el desarrollador** por el Administrador de
archivos de cPanel. Dos reglas:

- Se genera **en la raíz del repositorio** (`.gitignore` ya lleva `*.zip`), para tenerlo a mano.
- **Sin el envoltorio dentro.** `menu08_app.zip` contiene `aplicacion/ publico/ configuracion/
  basedatos/ almacenamiento/` en su primer nivel. Con el envoltorio, cPanel deja
  `menu08_app/menu08_app/` y hay que deshacerlo a mano.

Excluye siempre `configuracion/configuracion.php` y el contenido de `almacenamiento/bitacora/`:
extraer no debe pisar las credenciales del servidor ni borrar los registros.

## Cómo verificar tu trabajo

El prototipo está publicado en **https://adso.menu08.com** y se comprueba contra él por HTTPS. Los
usuarios de demostración están en `docs/basedatos.md`. El formulario de ingreso lleva `_token`, así
que para automatizar hay que pedirlo antes de cada POST.

Si bajas la bitácora del servidor para revisarla, **déjala fuera del repositorio**: lleva rutas
internas del hosting y los correos de quien intentó entrar. `.gitignore` cubre `*.log`.

Los datos de demostración: **no inventes catálogo.** Las categorías y productos de Festín Rodante
los define su dueño; el sembrado deja esos bloques marcados como PENDIENTE a propósito.

## Si perdiste el hilo

En este orden: `README.md` para el panorama y el estado de las fases; los issues abiertos del
milestone en curso; `docs/` para lo ya cerrado; y `git log --oneline` para lo último que se movió.
