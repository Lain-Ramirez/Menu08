# Base de datos del prototipo

Nueve tablas en MySQL 8, motor InnoDB, cotejamiento `utf8mb4_unicode_ci`.
Todos los montos se declaran como `DECIMAL(10,2)`.

| Tabla | Módulo | Para qué sirve |
|---|---|---|
| `food_trucks` | CARTA | Cada food truck registrado. El `slug` identifica su carta pública y es único. **No tiene dirección fija.** |
| `ubicaciones` | CARTA | Agenda de paradas del truck: dónde para, qué día y en qué horario. |
| `usuarios` | Todos | Acceso al panel. Roles: `plataforma`, `negocio`, `cajero`, `produccion`. |
| `categorias` | CARTA | Agrupan los productos dentro de la carta. |
| `productos` | CARTA | El catálogo. Alimenta la carta pública y el catálogo de CAJA. |
| `estados_orden` | SVP | Ciclo de vida de la orden: pendiente, en preparación, lista, entregada. |
| `turnos_caja` | CAJA | Apertura y cierre del turno del cajero. |
| `ordenes` | CAJA y SVP | La venta registrada en CAJA, con su número de turno, que el SVP muestra. |
| `orden_items` | CAJA | Detalle de la orden con copia histórica del nombre y del precio. |

## Dos decisiones de modelo propias de un food truck

### Por qué existe `ubicaciones` y no un campo de dirección

Un food truck se mueve: no tiene local. Cada fila de `ubicaciones` es una parada
programada —punto, referencia, día de la semana y franja horaria— y con ellas la carta
pública responde la pregunta *¿dónde están hoy?*.

**Jornadas que cruzan la medianoche.** Es el caso normal, no la excepción: un truck
nocturno abre a las 18:00 y cierra a la 01:00. Por eso el esquema **no** declara una
restricción `hora_fin > hora_inicio`, que invalidaría esas filas. La convención es:

> Si `hora_fin` es menor o igual que `hora_inicio`, la jornada termina al día siguiente.

Toda consulta de "parada vigente ahora" debe contemplar ese caso.

### Por qué `orden_items` copia el nombre y el precio

Si el truck cambia el precio de un producto o lo marca como no disponible, las órdenes
ya registradas deben conservar los valores con los que se vendieron. Por eso `orden_items`
guarda `nombre_producto` y `precio_unitario` como copia histórica, y `producto_id` queda
solo como referencia informativa que puede volverse `NULL`.

## Política de borrado

El prototipo **no borra** food trucks, categorías ni productos de forma física: usa baja
lógica con `activo` (o `disponible` en productos, `activa` en ubicaciones). Por eso todas
las llaves foráneas que apuntan a `food_trucks` se declaran `ON DELETE RESTRICT`: evitan
rutas de cascada cruzadas que dejarían el borrado a medias entre el catálogo y las ventas.

Las únicas cascadas son `orden_items → ordenes` (al eliminar una orden se eliminan sus
ítems) y `orden_items → productos` con `SET NULL` (el ítem conserva su copia histórica).

## Reconstruir la base desde cero

Los dos scripts se ejecutan **en este orden**. El primero elimina y vuelve a crear las
nueve tablas, así que borra todo lo que hubiera.

```bash
mysql -u root -p < basedatos/esquema.sql
mysql -u root -p < basedatos/datos_iniciales.sql
```

Desde el cliente interactivo de MySQL:

```sql
SOURCE basedatos/esquema.sql;
SOURCE basedatos/datos_iniciales.sql;
```

En un hosting con cPanel, importar los dos archivos en ese mismo orden desde phpMyAdmin,
quitando previamente las líneas `CREATE DATABASE` y `USE` si el panel ya creó la base con
un nombre con prefijo.

## Comprobar que quedó bien

```sql
USE menu08;
SHOW TABLE STATUS;                          -- nueve filas, InnoDB, utf8mb4_unicode_ci
SELECT COUNT(*) FROM estados_orden;         -- 4
SELECT nombre, slug FROM food_trucks;          -- Festin Rodante / festin-rodante
SELECT COUNT(*) FROM usuarios;              -- 4, uno por rol
```

La restricción de llave foránea se comprueba así: el siguiente `INSERT` debe ser rechazado
por la base porque la categoría no existe.

```sql
INSERT INTO productos (food_truck_id, categoria_id, nombre, precio)
VALUES (1, 999, 'Producto invalido', 1000.00);
-- ERROR 1452: Cannot add or update a child row: a foreign key constraint fails
```

## Lo que todavía no está sembrado

`basedatos/datos_iniciales.sql` trae dos bloques marcados como **PENDIENTE**:

1. **La agenda de paradas de Festín Rodante** — los puntos reales donde para el truck.
2. **La carta de Festín Rodante** — sus categorías y productos reales.

No se sembraron datos inventados a propósito: la carta de demostración tiene que ser la de
Festín Rodante y no un catálogo genérico. **Mientras esos bloques sigan vacíos, la carta
pública se ve sin productos y los módulos CAJA y SVP no se pueden recorrer de punta a punta.**

## Usuarios de demostración

| Correo | Rol | Qué opera |
|---|---|---|
| `plataforma@menu08.local` | plataforma | Administra todos los food trucks |
| `negocio@menu08.local` | negocio | Panel de CARTA: catálogo y paradas |
| `cajero@menu08.local` | cajero | CAJA |
| `produccion@menu08.local` | produccion | Sistema de Visualización de Producción |

Contraseña de todos: `Menu08*Demo2026`, cifrada con `password_hash`.

> Estas credenciales son solo para el entorno local. **Cambiarlas antes de publicar el
> prototipo en un servidor accesible desde internet.**

## Respaldo

```bash
mysqldump -u root -p --single-transaction --routines menu08 | gzip > menu08_respaldo.sql.gz
gunzip -c menu08_respaldo.sql.gz | mysql -u root -p menu08     # restauración
```
