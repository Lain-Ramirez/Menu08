-- ---------------------------------------------------------------------------
-- Menu08 - Datos iniciales del prototipo
--
-- Carga el catalogo minimo para que CARTA, CAJA y el Sistema de Visualizacion
-- de Produccion arranquen con informacion utilizable.
--
-- Ejecutar SIEMPRE despues de basedatos/esquema.sql:
--   mysql -u root -p < basedatos/datos_iniciales.sql
--
-- ATENCION: las contraseñas de este archivo son de demostracion y deben
-- cambiarse antes de publicar el prototipo en un servidor accesible.
-- Clave de todos los usuarios de demostracion: Menu08*Demo2026
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
USE menu08;

-- Estados por los que avanza una orden en el Sistema de Visualizacion de Produccion
INSERT INTO estados_orden (id, codigo, nombre, orden) VALUES
  (1, 'pendiente',      'Pendiente',      1),
  (2, 'en_preparacion', 'En preparacion', 2),
  (3, 'lista',          'Lista',          3),
  (4, 'entregada',      'Entregada',      4);

-- Negocio de demostracion. Su carta publica queda en /carta/sabor-criollo
INSERT INTO negocios (id, nombre, slug, descripcion, telefono, direccion, horario, activo) VALUES
  (1, 'Sabor Criollo', 'sabor-criollo',
   'Cocina casera y comidas rapidas. Negocio de demostracion del prototipo.',
   '3000000000', 'Calle 8 # 8-08', 'Lunes a sabado de 11:00 a 21:00', 1);

-- Usuarios: uno por rol. Las contraseñas estan cifradas con password_hash.
INSERT INTO usuarios (negocio_id, nombre, correo, contrasena, rol, activo) VALUES
  (NULL, 'Administrador de plataforma', 'plataforma@menu08.local', '$2y$10$GWHtLk1a0QlVFqNuikDm3uYk7x0KpoBKpOve2YduR6j.4YDTp9Yg6', 'plataforma', 1),
  (1,    'Administrador del negocio',   'negocio@menu08.local',    '$2y$10$Lgx.L6YLbhw0JOb/iQtTaOFHK3s0tw4Iskz.rojQiVQP.2tonWGa2',    'negocio',    1),
  (1,    'Cajero de demostracion',      'cajero@menu08.local',     '$2y$10$pgBUkyZRweLHteBkfzeqje7NTAgi1SYmPleN4sz0AShiRKZb4YgfC',     'cajero',     1),
  (1,    'Produccion de demostracion',  'produccion@menu08.local', '$2y$10$bsqqqW.FPjA6CTd8Hq238e2N0fVu5md8TF.QNFiSACBFxDxkQVaMu', 'produccion', 1);

-- Categorias de la carta
INSERT INTO categorias (id, negocio_id, nombre, orden, activo) VALUES
  (1, 1, 'Almuerzos',  1, 1),
  (2, 1, 'Bebidas',    2, 1);

-- Productos del catalogo. Alimentan la carta publica y el catalogo de CAJA.
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, disponible, orden) VALUES
  (1, 1, 'Bandeja paisa',    'Frijoles, arroz, carne molida, chicharron, huevo y arepa.', 28000.00, 1, 1),
  (1, 1, 'Sancocho de gallina', 'Servido con arroz, aguacate y limon.',                   24000.00, 1, 2),
  (1, 2, 'Limonada natural', 'Vaso de 12 onzas.',                                          6000.00, 1, 1),
  (1, 2, 'Jugo de mora',     'Preparado en agua o en leche.',                              7000.00, 0, 2);
