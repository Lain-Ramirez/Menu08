-- ---------------------------------------------------------------------------
-- Menu08 - Esquema relacional del prototipo
--
-- Plataforma de carta digital, venta y produccion para FOOD TRUCKS.
--
-- Sostiene los tres modulos:
--   CARTA  menu digital publicado por slug, con la agenda de puntos del truck
--   CAJA   punto de venta con turnos, ordenes y medios de pago
--   SVP    Sistema de Visualizacion de Produccion: tablero interno dentro del
--          truck y pantalla publica de turnos en la ventanilla
--
-- Motor InnoDB y cotejamiento utf8mb4_unicode_ci en las nueve tablas.
-- Todos los montos se declaran como DECIMAL(10,2).
--
-- Uso:  mysql -u root -p < basedatos/esquema.sql
--   o:  SOURCE basedatos/esquema.sql;   desde el cliente de MySQL
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Politica de borrado
--
-- El prototipo no borra food_trucks, categorias ni productos de forma fisica:
-- usa baja logica con la columna `activo` (o `disponible` en productos).
-- Por eso todas las llaves foraneas que apuntan a `food_trucks` se declaran
-- ON DELETE RESTRICT: evitan rutas de cascada cruzadas que dejarian el
-- borrado a medias entre las tablas de catalogo y las de ventas.
--
-- Las unicas cascadas son:
--   orden_items -> ordenes   al eliminar una orden se eliminan sus items
--   orden_items -> productos al eliminar un producto el item conserva la copia
--                            historica del nombre y el precio, y producto_id
--                            queda en NULL
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE DATABASE IF NOT EXISTS menu08
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE menu08;

-- Se eliminan en orden inverso a las dependencias para permitir recrear
-- el esquema completo sobre una base que ya tenga datos.
DROP TABLE IF EXISTS orden_items;
DROP TABLE IF EXISTS ordenes;
DROP TABLE IF EXISTS turnos_caja;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS ubicaciones;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS estados_orden;
DROP TABLE IF EXISTS food_trucks;

-- ---------------------------------------------------------------------------
-- food_trucks - cada food truck registrado en la plataforma.
-- El slug identifica su carta publica. No lleva direccion fija: un food truck
-- se mueve, y sus puntos de operacion se modelan en la tabla `ubicaciones`.
-- ---------------------------------------------------------------------------
CREATE TABLE food_trucks (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(120)  NOT NULL,
  slug            VARCHAR(80)   NOT NULL COMMENT 'minusculas, guiones, sin acentos',
  descripcion     VARCHAR(500)      NULL,
  logo            VARCHAR(160)      NULL COMMENT 'nombre del archivo en almacenamiento/subidas',
  telefono        VARCHAR(40)       NULL,
  whatsapp        VARCHAR(40)       NULL,
  instagram       VARCHAR(80)       NULL,
  ciudad          VARCHAR(80)       NULL COMMENT 'ciudad donde opera el food truck',
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_food_trucks_slug (slug),
  KEY ix_food_trucks_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ubicaciones - agenda de puntos donde para el food truck.
--
-- Un food truck no tiene direccion fija: opera en puntos distintos segun el dia.
-- Cada fila es una parada programada. La carta publica responde con esto la
-- pregunta "donde estamos hoy".
--
-- Jornadas que cruzan la medianoche: es lo habitual en un food truck nocturno
-- (por ejemplo de 18:00 a 01:00). Cuando `hora_fin` es menor o igual que
-- `hora_inicio` se entiende que la jornada termina al dia siguiente. Por eso
-- NO se declara una restriccion hora_fin > hora_inicio: invalidaria el caso normal.
-- ---------------------------------------------------------------------------
CREATE TABLE ubicaciones (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_truck_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(120)  NOT NULL COMMENT 'como lo conoce la gente: Parque de la 93',
  referencia      VARCHAR(200)      NULL COMMENT 'costado norte, frente al centro comercial',
  latitud         DECIMAL(10,7)     NULL,
  longitud        DECIMAL(10,7)     NULL,
  dia_semana      TINYINT UNSIGNED NOT NULL COMMENT '1 lunes ... 7 domingo',
  hora_inicio     TIME          NOT NULL,
  hora_fin        TIME          NOT NULL COMMENT 'si es <= hora_inicio, la jornada cierra al dia siguiente',
  activa          TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ubicaciones_agenda (food_truck_id, dia_semana, activa),
  CONSTRAINT fk_ubicaciones_food_truck FOREIGN KEY (food_truck_id)
    REFERENCES food_trucks (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_ubicaciones_dia CHECK (dia_semana BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- usuarios - acceso al panel. food_truck_id es NULL solo para el rol plataforma.
--   plataforma  administra todos los food_trucks
--   food_truck  administra la CARTA de su propio food truck
--   cajero      opera el modulo CAJA
--   produccion  opera el Sistema de Visualizacion de Produccion
-- ---------------------------------------------------------------------------
CREATE TABLE usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_truck_id      INT UNSIGNED      NULL,
  nombre          VARCHAR(120)  NOT NULL,
  correo          VARCHAR(160)  NOT NULL,
  contrasena      VARCHAR(255)  NOT NULL COMMENT 'resultado de password_hash, nunca texto plano',
  rol             ENUM('plataforma','food_truck','cajero','produccion') NOT NULL,
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  ultimo_ingreso  DATETIME          NULL,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY ix_usuarios_food_truck (food_truck_id),
  CONSTRAINT fk_usuarios_food_truck FOREIGN KEY (food_truck_id)
    REFERENCES food_trucks (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- categorias - agrupan los productos dentro de la carta de un food truck.
-- ---------------------------------------------------------------------------
CREATE TABLE categorias (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_truck_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(90)   NOT NULL,
  orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'orden de aparicion en la carta',
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_food_truck_nombre (food_truck_id, nombre),
  KEY ix_categorias_orden (food_truck_id, orden),
  CONSTRAINT fk_categorias_food_truck FOREIGN KEY (food_truck_id)
    REFERENCES food_trucks (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- productos - el catalogo. Alimenta la carta publica y el catalogo de CAJA.
--   disponible = 0 lo retira de la carta y de CAJA, pero conserva su historial
--   en las ordenes ya guardadas gracias a la copia en orden_items.
-- ---------------------------------------------------------------------------
CREATE TABLE productos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_truck_id      INT UNSIGNED NOT NULL,
  categoria_id    INT UNSIGNED NOT NULL,
  nombre          VARCHAR(120)  NOT NULL,
  descripcion     VARCHAR(400)      NULL,
  precio          DECIMAL(10,2) NOT NULL,
  foto            VARCHAR(160)      NULL COMMENT 'nombre del archivo en almacenamiento/subidas',
  disponible      TINYINT(1)    NOT NULL DEFAULT 1,
  orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_categoria_nombre (categoria_id, nombre),
  KEY ix_productos_food_truck (food_truck_id),
  KEY ix_productos_disponible (food_truck_id, disponible),
  CONSTRAINT fk_productos_food_truck FOREIGN KEY (food_truck_id)
    REFERENCES food_trucks (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id)
    REFERENCES categorias (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_productos_precio CHECK (precio >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- estados_orden - ciclo de vida de la orden dentro del SVP.
-- ---------------------------------------------------------------------------
CREATE TABLE estados_orden (
  id       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo   VARCHAR(30)  NOT NULL COMMENT 'identificador estable usado por el codigo',
  nombre   VARCHAR(40)  NOT NULL COMMENT 'texto mostrado en pantalla',
  orden    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_estados_orden_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- turnos_caja - apertura y cierre del turno. Toda orden pertenece a un turno.
-- ---------------------------------------------------------------------------
CREATE TABLE turnos_caja (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_truck_id      INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL COMMENT 'cajero que abrio el turno',
  base_inicial    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_ventas    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_declarado DECIMAL(10,2)     NULL COMMENT 'conteo fisico al cerrar',
  diferencia      DECIMAL(10,2)     NULL COMMENT 'total_declarado - (base_inicial + total_ventas)',
  estado          ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  abierto_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cerrado_en      DATETIME          NULL,
  PRIMARY KEY (id),
  KEY ix_turnos_food_truck_estado (food_truck_id, estado),
  KEY ix_turnos_usuario (usuario_id),
  CONSTRAINT fk_turnos_food_truck FOREIGN KEY (food_truck_id)
    REFERENCES food_trucks (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_turnos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ordenes - la venta registrada en CAJA y mostrada en el SVP.
-- ---------------------------------------------------------------------------
CREATE TABLE ordenes (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_truck_id             INT UNSIGNED NOT NULL,
  turno_id               INT UNSIGNED NOT NULL,
  estado_id              TINYINT UNSIGNED NOT NULL,
  numero                 VARCHAR(20)   NOT NULL COMMENT 'consecutivo visible, unico por food truck',
  total                  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  medio_pago             ENUM('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo',
  nota                   VARCHAR(300)      NULL,
  creado_en              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado_actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ordenes_food_truck_numero (food_truck_id, numero),
  KEY ix_ordenes_turno (turno_id),
  KEY ix_ordenes_tablero (food_truck_id, estado_id, creado_en) COMMENT 'consulta del SVP',
  CONSTRAINT fk_ordenes_food_truck FOREIGN KEY (food_truck_id)
    REFERENCES food_trucks (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ordenes_turno FOREIGN KEY (turno_id)
    REFERENCES turnos_caja (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ordenes_estado FOREIGN KEY (estado_id)
    REFERENCES estados_orden (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT ck_ordenes_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- orden_items - detalle de la orden. Copia el nombre y el precio del producto
-- al momento de la venta, para que un cambio posterior en el catalogo no altere
-- las ordenes ya registradas.
-- ---------------------------------------------------------------------------
CREATE TABLE orden_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id         INT UNSIGNED NOT NULL,
  producto_id      INT UNSIGNED     NULL COMMENT 'referencia informativa, puede quedar en NULL',
  nombre_producto  VARCHAR(120)  NOT NULL COMMENT 'copia historica del nombre',
  precio_unitario  DECIMAL(10,2) NOT NULL COMMENT 'copia historica del precio',
  cantidad         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  subtotal         DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY ix_orden_items_orden (orden_id),
  KEY ix_orden_items_producto (producto_id),
  CONSTRAINT fk_orden_items_orden FOREIGN KEY (orden_id)
    REFERENCES ordenes (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_orden_items_producto FOREIGN KEY (producto_id)
    REFERENCES productos (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT ck_orden_items_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_orden_items_subtotal CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
