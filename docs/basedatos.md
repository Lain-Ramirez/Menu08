# Base de datos del prototipo

## Estructura

Ocho tablas en MySQL 8 con motor InnoDB y cotejamiento `utf8mb4_unicode_ci`.
Todos los montos se declaran como `DECIMAL(10,2)`.

| Tabla | Módulo | Para qué sirve |
|---|---|---|
| `negocios` | CARTA | Cuenta del negocio. El `slug` identifica su carta pública y es único. |
| `usuarios` | Todos | Acceso al panel. Roles: `plataforma`, `negocio`, `cajero`, `produccion`. |
| `categorias` | CARTA | Agrupan los productos dentro de la carta. |
| `productos` | CARTA | El catálogo. Alimenta la carta pública y el catálogo de CAJA. |
| `estados_orden` | SVP | Ciclo de vida de la orden: pendiente, en preparación, lista, entregada. |
| `turnos_caja` | CAJA | Apertura y cierre del turno del cajero. |
| `ordenes` | CAJA y SVP | La venta registrada en CAJA que el SVP muestra en su tablero. |
| `orden_items` | CAJA | Detalle de la orden con copia histórica del nombre y del precio. |

### Por qué `orden_items` copia el nombre y el precio

Si el negocio cambia el precio de un producto o lo marca como no disponible, las
órdenes ya registradas deben conservar los valores con los que se vendieron. Por eso
`orden_items` guarda `nombre_producto` y `precio_unitario` como copia histórica, y
`producto_id` queda solo como referencia informativa que puede volverse `NULL`.

## Reconstruir la base desde cero

Los dos scripts se ejecutan **en este orden**. El primero elimina y vuelve a crear
las ocho tablas, así que borra todo lo que hubiera.

```bash
mysql -u root -p < basedatos/esquema.sql
mysql -u root -p < basedatos/datos_iniciales.sql
```

Desde el cliente interactivo de MySQL:

```sql
SOURCE basedatos/esquema.sql;
SOURCE basedatos/datos_iniciales.sql;
```

En un hosting con cPanel, importar los dos archivos en ese mismo orden desde
phpMyAdmin, quitando previamente las líneas `CREATE DATABASE` y `USE` si el panel
ya creó la base con un nombre con prefijo.

## Comprobar que quedó bien

```sql
USE menu08;
SHOW TABLE STATUS;                        -- ocho filas, motor InnoDB, utf8mb4_unicode_ci
SELECT COUNT(*) FROM estados_orden;       -- 4
SELECT nombre, slug FROM negocios;        -- Sabor Criollo / sabor-criollo
SELECT nombre, precio, disponible FROM productos;   -- 4 productos, uno no disponible
```

La restricción de llave foránea se comprueba así: el siguiente `INSERT` debe ser
rechazado por la base porque la categoría no existe.

```sql
INSERT INTO productos (negocio_id, categoria_id, nombre, precio)
VALUES (1, 999, 'Producto invalido', 1000.00);
-- ERROR 1452: Cannot add or update a child row: a foreign key constraint fails
```

## Usuarios de demostración

| Correo | Rol | Módulo que opera |
|---|---|---|
| `plataforma@menu08.local` | plataforma | Administra todos los negocios |
| `negocio@menu08.local` | negocio | Panel de CARTA |
| `cajero@menu08.local` | cajero | CAJA |
| `produccion@menu08.local` | produccion | Sistema de Visualización de Producción |

Contraseña de todos: `Menu08*Demo2026`, cifrada con `password_hash`.

> Estas credenciales son solo para el entorno local. **Cambiarlas antes de publicar
> el prototipo en un servidor accesible desde internet.**

## Respaldo

```bash
mysqldump -u root -p --single-transaction --routines menu08 | gzip > menu08_respaldo.sql.gz
gunzip -c menu08_respaldo.sql.gz | mysql -u root -p menu08     # restauración
```
