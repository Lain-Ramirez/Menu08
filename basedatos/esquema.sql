-- ---------------------------------------------------------------------------
-- Menu08 - Esquema relacional del prototipo
--
-- Sostiene los tres modulos de la plataforma:
--   CARTA  menu digital publicado por slug y administrado desde el panel
--   CAJA   punto de venta con turnos, ordenes y medios de pago
--   SVP    Sistema de Visualizacion de Produccion, consume las ordenes de CAJA
--
-- Motor InnoDB y cotejamiento utf8mb4_unicode_ci en las ocho tablas.
-- Todos los montos se declaran como DECIMAL(10,2).
--
-- Uso:  mysql -u root -p < basedatos/esquema.sql
--   o:  SOURCE basedatos/esquema.sql;   desde el cliente de MySQL
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Politica de borrado
--
-- El prototipo no borra negocios, categorias ni productos de forma fisica:
-- usa baja logica con la columna `activo` (o `disponible` en productos).
-- Por eso todas las llaves foraneas que apuntan a `negocios` se declaran
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
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS estados_orden;
DROP TABLE IF EXISTS negocios;

-- ---------------------------------------------------------------------------
-- negocios - cada cuenta de la plataforma. El slug identifica la carta publica.
-- ---------------------------------------------------------------------------
CREATE TABLE negocios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(120)  NOT NULL,
  slug            VARCHAR(80)   NOT NULL COMMENT 'minusculas, guiones, sin acentos',
  descripcion     VARCHAR(500)      NULL,
  logo            VARCHAR(160)      NULL COMMENT 'nombre del archivo en almacenamiento/subidas',
  telefono        VARCHAR(40)       NULL,
  direccion       VARCHAR(200)      NULL,
  horario         VARCHAR(200)      NULL,
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_negocios_slug (slug),
  KEY ix_negocios_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- usuarios - acceso al panel. negocio_id es NULL solo para el rol plataforma.
--   plataforma  administra todos los negocios
--   negocio     administra la CARTA de su propio negocio
--   cajero      opera el modulo CAJA
--   produccion  opera el Sistema de Visualizacion de Produccion
-- ---------------------------------------------------------------------------
CREATE TABLE usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  negocio_id      INT UNSIGNED      NULL,
  nombre          VARCHAR(120)  NOT NULL,
  correo          VARCHAR(160)  NOT NULL,
  contrasena      VARCHAR(255)  NOT NULL COMMENT 'resultado de password_hash, nunca texto plano',
  rol             ENUM('plataforma','negocio','cajero','produccion') NOT NULL,
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  ultimo_ingreso  DATETIME          NULL,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo),
  KEY ix_usuarios_negocio (negocio_id),
  CONSTRAINT fk_usuarios_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- categorias - agrupan los productos dentro de la carta de un negocio.
-- ---------------------------------------------------------------------------
CREATE TABLE categorias (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  negocio_id      INT UNSIGNED NOT NULL,
  nombre          VARCHAR(90)   NOT NULL,
  orden           SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'orden de aparicion en la carta',
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_negocio_nombre (negocio_id, nombre),
  KEY ix_categorias_orden (negocio_id, orden),
  CONSTRAINT fk_categorias_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- productos - el catalogo. Alimenta la carta publica y el catalogo de CAJA.
--   disponible = 0 lo retira de la carta y de CAJA, pero conserva su historial
--   en las ordenes ya guardadas gracias a la copia en orden_items.
-- ---------------------------------------------------------------------------
CREATE TABLE productos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  negocio_id      INT UNSIGNED NOT NULL,
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
  KEY ix_productos_negocio (negocio_id),
  KEY ix_productos_disponible (negocio_id, disponible),
  CONSTRAINT fk_productos_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios (id) ON DELETE RESTRICT ON UPDATE CASCADE,
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
  negocio_id      INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NOT NULL COMMENT 'cajero que abrio el turno',
  base_inicial    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_ventas    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_declarado DECIMAL(10,2)     NULL COMMENT 'conteo fisico al cerrar',
  diferencia      DECIMAL(10,2)     NULL COMMENT 'total_declarado - (base_inicial + total_ventas)',
  estado          ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
  abierto_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cerrado_en      DATETIME          NULL,
  PRIMARY KEY (id),
  KEY ix_turnos_negocio_estado (negocio_id, estado),
  KEY ix_turnos_usuario (usuario_id),
  CONSTRAINT fk_turnos_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_turnos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- ordenes - la venta registrada en CAJA y mostrada en el SVP.
-- ---------------------------------------------------------------------------
CREATE TABLE ordenes (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  negocio_id             INT UNSIGNED NOT NULL,
  turno_id               INT UNSIGNED NOT NULL,
  estado_id              TINYINT UNSIGNED NOT NULL,
  numero                 VARCHAR(20)   NOT NULL COMMENT 'consecutivo visible, unico por negocio',
  total                  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  medio_pago             ENUM('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo',
  nota                   VARCHAR(300)      NULL,
  creado_en              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado_actualizado_en  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ordenes_negocio_numero (negocio_id, numero),
  KEY ix_ordenes_turno (turno_id),
  KEY ix_ordenes_tablero (negocio_id, estado_id, creado_en) COMMENT 'consulta del SVP',
  CONSTRAINT fk_ordenes_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios (id) ON DELETE RESTRICT ON UPDATE CASCADE,
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
