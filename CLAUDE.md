# Menu08 — guía para el asistente

Este archivo es lo que hay que saber para trabajar en el código sin tener que reconstruirlo
leyéndolo entero. El **qué es el proyecto** está en el [`README.md`](README.md); aquí está el
**cómo se trabaja dentro de él**.

Las convenciones de abajo no son preferencias de estilo: son decisiones ya tomadas y evaluadas
en un proyecto formativo. Cambiarlas por tu cuenta rompe la coherencia de la entrega.

---

## Las cuatro reglas que no se negocian

1. **El tablero de producción se llama SVP** — *Sistema de Visualización de Producción*, también
   **SV3**. Nunca uses la sigla genérica en inglés con que se conoce a este tipo de pantalla en
   cocina: no aparece en ningún artefacto del proyecto —código, comentarios, nombres de clase,
   documentación, commits, issues ni presentaciones— y así debe seguir. En rutas y clases va
   `svp` / `Svp`: `SvpControlador`, `/svp`, `vistas/svp/`.
2. **No hagas commits.** Deja los cambios en el árbol de trabajo y di qué tocaste. Los confirma el
   desarrollador, porque el repositorio firma con GPG y la firma tiene que ser suya. Un `git commit`
   tuyo sale firmado con su clave y a su nombre, aunque no añadas ningún *trailer*.
3. **Todo en español.** Carpetas, clases, métodos, variables, comentarios, mensajes de error,
   commits y documentación. Nunca `app/`, `src/`, `public/`, `config/`, `database/`, `storage/`.
   Dentro del código PHP se escribe **sin acentos** (`Produccion`, `antiguedad`); en el Markdown de
   `docs/` y del README, con acentos normales.
4. **El único entorno de este proyecto es `adso.menu08.com`.** No consultes ningún otro dominio.

## El proyecto en un párrafo

Plataforma de carta digital, venta y producción **para food trucks**, con tres módulos: **CARTA**
(carta por QR, agenda de paradas y panel de catálogo), **CAJA** (turno, orden, cobro y número) y
**SVP** (tablero interno y pantalla pública de turnos). El orden de construcción es
**CARTA → CAJA → SVP**, porque CARTA es el catálogo del que CAJA vende y cuyas órdenes muestra el
SVP. El food truck de demostración es **Festín Rodante**.

No es una plataforma para «negocios de alimentos» en general: nada de restaurantes con mesas,
cafeterías ni locales fijos en el lenguaje, el modelo, los datos de ejemplo ni las vistas. Un truck
no tiene local: tiene ventanilla, fila y un punto que cambia según el día. De ahí salen dos cosas
que verás por todas partes: la tabla `ubicaciones` en vez de una dirección, y la entrega **por
llamado de número**, no en mesa.

## Stack cerrado

PHP 8.3 con POO, **MVC propio escrito a mano**, PDO con sentencias preparadas, MySQL 8, HTML, CSS y
JavaScript sin bibliotecas, Apache con `mod_rewrite`. **Sin Composer, sin frameworks, sin paquetes
de terceros.** No propongas instalar nada: la restricción es del proyecto formativo. El SVP se
refresca **por sondeo con `fetch`**, no por WebSockets.

---

## El recorrido de una petición

```
.htaccess  ->  menu08_app/publico/index.php
                 1. autocarga por convención
                 2. ManejadorErrores::registrar()     desde aquí nada se pierde
                 3. Configuracion::cargar()
                 4. Sesion::iniciar()
                 5. require configuracion/rutas.php   registra todas las rutas
                 6. Enrutador::despachar()
                       -> XxxControlador::metodo()
                            -> Modelo (estático, PDO)
                            -> $this->vista(...) o $this->json(...)
```

La **autocarga solo conoce tres espacios de nombres**, mapeados a tres carpetas:

| Espacio | Carpeta |
|---|---|
| `Menu08\Nucleo\` | `aplicacion/nucleo/` |
| `Menu08\Controladores\` | `aplicacion/controladores/` |
| `Menu08\Modelos\` | `aplicacion/modelos/` |

**Las vistas no tienen espacio de nombres.** Son archivos PHP que `Vista` incluye con las variables
extraídas, así que dentro de una vista las claves del arreglo son variables sueltas.

## El núcleo, clase por clase

Todo en `aplicacion/nucleo/`. No añadas dependencias nuevas: casi siempre ya existe la pieza.

| Clase | Para qué | Lo que vas a usar |
|---|---|---|
| `Enrutador` | Registra y resuelve rutas | `get()`, `post()`, patrones `{id:\d+}` |
| `Controlador` | Base de todos los controladores | ver la tabla siguiente |
| `Vista` | Renderiza plantillas | `Vista::e()` escapa, `Vista::url()` construye enlaces |
| `Sesion` | Sesión endurecida | `usuario()`, `rol()`, `foodTruckId()`, `mensaje()` |
| `Csrf` | Token contra falsificación | `campo()` en formularios, `token()`, `rotar()` |
| `Validador` | Validación de formularios | `texto()`, `precio()`, `entero()`, `diaSemana()`, `hora()`, `coordenada()`, `error()`, `correcto()`, `errores()`, `valor()` |
| `ConexionBD` | PDO único | `ConexionBD::obtener()` |
| `Configuracion` | Lee `configuracion.php` | `obtener('base_datos.servidor')`, `esProduccion()` |
| `ManejadorErrores` | Convierte todo fallo en respuesta | `responderEnJson()` |
| `Bitacora` | Registro de errores | `registrar($mensaje, 'AVISO')` |
| `GestorImagenes` | Subidas de fotos | `guardar($_FILES['foto'], $v)`, `borrar()` |
| `GeneradorQr` | Códigos QR sin biblioteca | `png($texto)` |

### Lo que `Controlador` te da

| Método | Qué hace |
|---|---|
| `vista($plantilla, $datos, $titulo, $codigo)` | Responde HTML |
| `json($datos, $codigo)` | Responde JSON |
| `redirigir($ruta)` | 302. Es `never`: no escribas `return` después |
| `jsonError($error, $mensaje, $codigo)` | Error en JSON. También `never` |
| `exigirSesion()` | Sin sesión, redirige al ingreso |
| `exigirRol(...$roles)` | Sesión y rol, o `AccesoDenegado` |
| `exigirRolApi(...$roles)` | Igual, pero en JSON: 401 sin sesión, 403 con rol equivocado |
| `verificarCsrf()` | Para formularios. Lanza `AccesoDenegado` |
| `verificarCsrfApi()` | Para servicios JSON. Responde 403 con motivo |
| `usuario()` | El usuario de la sesión |
| `foodTruckActual()` | **El filtro de toda consulta.** Nunca de la URL |

## Los errores se lanzan, no se maquetan

No construyas respuestas de error a mano en un controlador de páginas: lanza la excepción y el
manejador hace el resto, incluida la bitácora.

| Excepción | Código | Cuándo |
|---|---|---|
| `RutaNoEncontrada` | **404** | No existe, **o es de otro food truck** |
| `AccesoDenegado` | **403** | Rol equivocado, token inválido |
| `DatosInvalidos` | **422** | El formulario o la petición no valen |
| cualquier otra | **500** | Fallo no previsto; el detalle va a la bitácora, nunca al visitante |

En **servicios JSON** es al revés: atrapa la excepción en el controlador y tradúcela con
`jsonError()`, para que el cuerpo lleve el motivo y no un `fallo_interno` genérico. Así lo hace
`SvpControlador::estado()`.

## Añadir una funcionalidad, paso a paso

**1. La ruta**, en `configuracion/rutas.php`. Los parámetros llegan **por nombre y como cadena**:

```php
$enrutador->get('/panel/categorias/{id:\\d+}', [CategoriaControlador::class, 'editar']);
$enrutador->post('/svp/orden/{id:\\d+}/estado', [SvpControlador::class, 'estado']);
```

**2. El controlador.** Acceso primero, CSRF después, y el `food_truck_id` siempre de la sesión:

```php
public function editar(string $id): void          // llega como string
{
    $this->exigirRol('food_truck');

    $ft        = $this->foodTruckActual();
    $categoria = Categoria::porId((int) $id, $ft);

    // Un identificador de otro food truck no existe para esta sesion.
    if ($categoria === null) {
        throw new RutaNoEncontrada(sprintf('Categoria %s inexistente para este food truck.', $id));
    }

    $this->vista('panel/categorias', ['categorias' => ..., 'edita' => $categoria], 'Editar categoria');
}
```

**3. El modelo.** Métodos **estáticos**, PDO preparado, y el `food_truck_id` en el `WHERE`:

```php
public static function porId(int $id, int $foodTruckId): ?array
{
    $s = ConexionBD::obtener()->prepare(
        'SELECT * FROM categorias WHERE id = :id AND food_truck_id = :ft LIMIT 1'
    );
    $s->execute(['id' => $id, 'ft' => $foodTruckId]);

    return $s->fetch() ?: null;
}
```

Lo que escriba más de una tabla va dentro de `beginTransaction()` con `rollBack()` en el `catch`, y
lo que dependa del estado actual se bloquea con `SELECT … FOR UPDATE`. Hay dos ejemplos completos
en `Orden::registrar()` y `Orden::avanzar()`.

**4. La vista**, en `aplicacion/vistas/<modulo>/`. **Todo lo que salga se escapa**, sin excepción:

```php
<h2><?= Vista::e($categoria['nombre']) ?></h2>

<form method="post" action="<?= Vista::e(Vista::url('/panel/categorias/guardar')) ?>">
    <?= Csrf::campo() ?>
    ...
</form>
```

**5. La documentación.** Si es un servicio, su contrato en `docs/api-*.md`. Cuando lo pruebes, la
evidencia en `docs/pruebas-*.md`.

## Servicios JSON

Los consume el tablero del SVP por sondeo, así que **nunca pueden responder HTML**: un cliente que
espera un objeto y recibe `<!doctype html>` falla con un error de sintaxis que no dice nada.

```php
public function ordenes(): void
{
    $this->exigirRolApi('food_truck', 'produccion');   // 401 o 403, siempre en JSON
    $this->json([...]);
}

public function estado(string $id): void
{
    $this->exigirRolApi('food_truck', 'produccion');
    $this->verificarCsrfApi();                          // 403 con motivo, no pagina de error

    try {
        $orden = Orden::avanzar((int) $id, $this->foodTruckActual(), $destino);
    } catch (RutaNoEncontrada $e) {
        $this->jsonError('orden_no_encontrada', $e->getMessage(), 404);
    } catch (DatosInvalidos $e) {
        $this->jsonError('transicion_invalida', $e->getMessage(), 422);
    }

    $this->json(['orden' => $orden]);
}
```

`exigirRolApi()` activa además el modo JSON del manejador de errores, para que hasta un 500
inesperado salga como objeto. El contrato vivo está en [`docs/api-svp.md`](docs/api-svp.md).

**Desde JavaScript**, el token se lee de la plantilla común, que lo publica en zona privada:

```js
const token = document.querySelector('meta[name="csrf-token"]').content
```

---

## Invariantes

Están implementados y probados. Si tocas código cerca de ellos, no los debilites.

- **El `food_truck_id` sale siempre de la sesión, nunca de la petición.** Es el filtro de toda
  consulta: nadie administra el catálogo de otro cambiando un número en la URL.
- **Un identificador de otro food truck se trata como inexistente**, con 404 y no con 403. Un 403
  confirmaría que existe.
- **Los precios se leen de `productos`**, dentro de la misma transacción que escribe la venta. Lo
  que el formulario diga sobre el importe se descarta.
- **El dinero se acumula en centavos con enteros** y solo se formatea al final. En coma flotante,
  una jornada de decenas de ventas descuadra la caja por unos pesos.
- **`orden_items` copia el nombre y el precio** del producto al vender: cambiar un precio después no
  altera las órdenes ya registradas.
- **Todo POST que modifique datos pasa por CSRF.**
- **`ordenes` guarda una marca de tiempo por estado** (`creado_en`, `en_preparacion_en`, `lista_en`,
  `entregada_en`) en vez de una que se vaya pisando, para que «¿cuánto tardó esta orden?» siga
  teniendo respuesta después de entregarla.
- **El ciclo de vida de la orden vive en un solo sitio**, `Orden::TRANSICIONES`, y solo avanza:
  `pendiente → en_preparacion → lista → entregada`.

## Trampas que cuestan tiempo

- **PDO va sin emulación de preparadas.** Un marcador nombrado **solo puede aparecer una vez** por
  sentencia. Si necesitas el mismo valor dos veces, usa dos nombres: `:ft` y `:ft2`.
- **`Csrf::rotar()` invalida el token** tras una operación sensible, para que reenviar el formulario
  no la repita. `CajaControlador::vender()` lo hace: si automatizas varias ventas seguidas, hay que
  releer el token entre una y otra. Los servicios del SVP **no** rotan, a propósito.
- **El token nace en el formulario de ingreso** y sobrevive al `session_regenerate_id()` del login.
  Por eso una sesión recién abierta ya trae uno, aunque la página que estés viendo no tenga formularios.
- **`redirigir()` y `jsonError()` están declarados `never`.** No escribas código detrás.
- **Los parámetros de ruta llegan como cadena**, aunque el patrón sea `\d+`. Convierte con `(int)`.
- **Las vistas se escapan siempre con `Vista::e()`.** Lo único que la plantilla base imprime sin
  escapar es `$contenido`, que ya viene renderizado por su vista.
- **Los enlaces se construyen con `Vista::url()`**, que respeta `url_base`. Una ruta a pelo se rompe
  al instalar la aplicación en una subcarpeta.
- **`configuracion/configuracion.php` no está en el repositorio** y nunca debe estarlo. La plantilla
  versionada es `configuracion.ejemplo.php`.

---

## Los issues no se rehacen

El trabajo se sigue en los issues, agrupados por milestones de fase. **Cada uno ya está redactado,
con una estructura fija.** No los reescribas, no los reordenes y no crees duplicados: complétalos.

```
> Orden de construcción / Depende de / Bloquea a
### Descripción
### Criterios de aceptación     (casillas)
### Tareas                      (casillas)
### Definición de hecho
### Evidencia SENA              (enlaces cruzados)
**Estimación** · **Fase**
```

Cuando trabajes uno, **verifica criterio por criterio** y di con qué evidencia concreta —código
citado, salida real de un comando— lo das por cumplido. Un «cumple» sin evidencia no vale. Si un
criterio no cabe en el esquema o pisa el alcance de otro issue, **dilo antes de codificar**: ha
pasado ya y resolverlo a tiempo ahorra rehacer trabajo.

## Documentación

| Archivo | Para qué |
|---|---|
| `docs/nucleo.md`, `docs/basedatos.md`, `docs/despliegue.md` | Arquitectura, modelo de datos y despliegue |
| `docs/api-*.md` | Contrato de un servicio: ruta, método, parámetros, ejemplos de éxito y de error |
| `docs/pruebas-*.md` | Evidencia: qué se comprobó, con qué resultado y **qué quedó sin cubrir** |
| [`POSTMAN.md`](POSTMAN.md) | Guía para probar **todas** las rutas desde fuera, escrita para quien llega nuevo |
| `postman/Menu08.postman_collection.json` | La misma guía, ejecutable: se importa en Postman y se corre |

**`POSTMAN.md` y la colección se actualizan en el mismo cambio que la ruta.** Si añades, cambias o
quitas algo en `configuracion/rutas.php`, los dos tienen que reflejarlo: método, rol, campos exactos
del cuerpo y códigos de respuesta. Los nombres de campo se comprueban leyendo el controlador, no el
formulario. El banco de pruebas que usan está en `basedatos/datos_pruebas.sql`.

Los `docs/pruebas-*.md` son la evidencia de la entrega, no un adorno. Escríbelos con la salida real,
incluidos los intentos fallidos y su causa, y cierra siempre con una sección honesta de lo que las
pruebas **no** cubren.

## Commits

No los hagas tú (regla 2). Cuando el desarrollador confirme, el mensaje sigue este formato:

```
usuario@equipo 3septiembre10:50 - Corregir
```

Verbo en infinitivo: Implementar, Programar, Persistir, Exponer, Publicar, Corregir. **Sin trailers
de atribución de ningún tipo** — ni coautoría, ni enlaces de sesión, ni firmas de asistente.

## Despliegue y comprobación

El repositorio es un **espejo del servidor**: `menu08_app/` (privada, fuera de toda raíz web) y
`ADSO.menu08.com/` (pública) se llaman igual que en el hosting. Detalle en
[`docs/despliegue.md`](docs/despliegue.md).

El hosting **bloquea FTP, SFTP, SSH y cPanel** desde fuera de las direcciones autorizadas, y los
intentos fallidos hacen que bloqueen la dirección que insiste. **No lo intentes.** Los archivos se
entregan en un ZIP que sube y extrae el desarrollador desde el Administrador de archivos. Dos
reglas: se genera **en la raíz del repositorio** (`.gitignore` ya lleva `*.zip`) y **sin el
envoltorio dentro** —`menu08_app.zip` contiene `aplicacion/ publico/ …` en su primer nivel, porque
con el envoltorio cPanel deja `menu08_app/menu08_app/`—. Excluye siempre
`configuracion/configuracion.php` y el contenido de `almacenamiento/bitacora/`.

Se comprueba contra el sitio publicado, por HTTPS. Para automatizar el ingreso hay que **pedir el
`_token` antes de cada POST**, porque el formulario lo lleva en un campo oculto:

```bash
TOK=$(curl -s -c j.txt https://adso.menu08.com/ingresar | grep -oE 'name="_token" value="[a-f0-9]+"' | grep -oE '[a-f0-9]{32,}')
curl -s -b j.txt -c j.txt -d "correo=…" -d "contrasena=…" -d "_token=$TOK" https://adso.menu08.com/ingresar
```

Los usuarios de demostración están en [`docs/basedatos.md`](docs/basedatos.md). Si bajas la bitácora
del servidor para revisarla, **déjala fuera del repositorio**: lleva rutas internas del hosting y
los correos de quien intentó entrar. `.gitignore` cubre `*.log`.

**No inventes catálogo.** Las categorías y productos de Festín Rodante los define su dueño; el
sembrado deja esos bloques marcados como PENDIENTE a propósito.

## Si perdiste el hilo

En este orden: `README.md` para el panorama y el estado de las fases; los issues abiertos del
milestone en curso; `docs/` para lo ya cerrado; y `git log --oneline` para lo último que se movió.
