# Núcleo de la aplicación

PHP 8.2 con MVC construido a mano. Sin Composer, sin marcos de trabajo, sin dependencias externas.

## Ciclo de una petición

```
navegador
  → publico/.htaccess          reescribe todo lo que no sea un archivo real
  → publico/index.php          front controller: autocarga, errores, configuración
  → configuracion/rutas.php    tabla de rutas
  → Enrutador                  resuelve método + patrón, extrae los parámetros
  → Controlador                orquesta
  → Modelo → ConexionBD        PDO con sentencias preparadas
  → Vista                      plantilla + plantillas/base
  → navegador
```

## Puesta en marcha

```bash
cp configuracion/configuracion.ejemplo.php configuracion/configuracion.php
```

Editar el archivo copiado con las credenciales reales y `url_base`. **Nunca se versiona**:
está en `.gitignore`. La plantilla `configuracion.ejemplo.php` sí, y debe mantenerse con
las mismas claves cuando se agregue una opción nueva.

`url_base` es la dirección pública desde la que se sirve la aplicación, y es la que se
codifica dentro del código QR de la carta. Si apunta a otro sitio, los QR ya impresos
dejan de servir.

## Piezas

| Clase | Responsabilidad |
|---|---|
| `Configuracion` | Lee `configuracion.php` una vez. Acceso por clave con puntos: `base_datos.servidor` |
| `Enrutador` | Registra rutas por método y patrón, extrae parámetros nombrados |
| `Controlador` | Base de los controladores: respuesta HTML, JSON y redirección |
| `Vista` | Renderiza plantillas dentro de `plantillas/base`, escapa la salida, arma direcciones |
| `ConexionBD` | Instancia única de PDO |
| `Bitacora` | Escribe en `almacenamiento/bitacora`, un archivo por día |
| `ManejadorErrores` | Traduce avisos, excepciones y fatales a una respuesta controlada |
| `RutaNoEncontrada` | Excepción que el manejador convierte en 404 |

## Agregar una ruta

En `configuracion/rutas.php`:

```php
$enrutador->get('/carta/{slug}', [CartaControlador::class, 'publica']);
$enrutador->get('/svp/orden/{id:\d+}', [SvpControlador::class, 'detalle']);
$enrutador->post('/panel/productos', [ProductoControlador::class, 'guardar']);
```

`{slug}` acepta cualquier segmento sin barras. `{id:\d+}` lo restringe con una expresión
regular. Los parámetros llegan al método como **argumentos nombrados**, así que la firma
debe usar el mismo nombre que el patrón:

```php
public function publica(string $slug): void
```

## Manejo de errores

Todo fallo queda en `almacenamiento/bitacora/AAAA-MM-DD.log` con fecha, nivel y mensaje.
Lo que ve el visitante depende de `entorno`:

- `desarrollo` — la página de error muestra además la clase, el mensaje, el archivo,
  la línea y la traza.
- `produccion` — solo el mensaje genérico. Nunca la traza, la consulta ni los datos de conexión.

El mensaje original de una conexión fallida de PDO puede incluir el usuario y el servidor,
así que `ConexionBD` lo guarda en la bitácora y propaga uno genérico.

Los avisos de PHP se elevan a excepciones a propósito: un aviso silencioso en una plantilla
suele ser un dato que no llegó, y es mejor que falle de forma visible en desarrollo.

## Comprobar que el núcleo responde

Con la base cargada y la configuración copiada:

```bash
php -S localhost:8000 -t publico publico/index.php
```

El último argumento es el front controller. **Sin él, el servidor integrado no resuelve
las rutas** y solo sirve archivos existentes.

| Dirección | Qué demuestra |
|---|---|
| `/` | El ciclo completo, con datos leídos de MySQL |
| `/comprobacion/festin-rodante` | Ruta con parámetro y sentencia preparada |
| `/no-existe` | Respuesta 404 con la vista de error propia |

`InicioControlador` y sus dos vistas son andamiaje de comprobación: se retiran cuando el
panel real ocupe la ruta raíz.

## Lo que este núcleo todavía no hace

Sesiones, inicio de sesión, control de acceso por rol y token contra falsificación de
peticiones llegan con el issue #7. `Controlador` no tiene aún ninguna comprobación de
permisos: cualquier ruta que se registre hoy es pública.
