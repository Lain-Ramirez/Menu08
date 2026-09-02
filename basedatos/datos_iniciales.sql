-- ---------------------------------------------------------------------------
-- Menu08 - Datos iniciales del prototipo
--
-- Carga lo minimo estructural para arrancar: los estados por los que avanza una
-- orden, el food truck de demostracion y un usuario por cada rol.
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

-- ---------------------------------------------------------------------------
-- Estados por los que avanza una orden.
-- Los codigos son estables: el codigo PHP y la pantalla publica de turnos los
-- usan como identificador, mientras que `nombre` es lo que se muestra.
-- ---------------------------------------------------------------------------
INSERT INTO estados_orden (id, codigo, nombre, orden) VALUES
  (1, 'pendiente',      'Pendiente',      1),
  (2, 'en_preparacion', 'En preparacion', 2),
  (3, 'lista',          'Lista',          3),
  (4, 'entregada',      'Entregada',      4);

-- ---------------------------------------------------------------------------
-- Festin Rodante - primer food truck de la plataforma.
-- Su carta publica queda en /carta/festin-rodante
--
-- Los datos de contacto, la ciudad y la descripcion se completan desde el panel:
-- aqui solo se crea el registro para poder iniciar sesion y administrarlo.
-- ---------------------------------------------------------------------------
INSERT INTO food_trucks (id, nombre, slug, descripcion, activo) VALUES
  (1, 'Festin Rodante', 'festin-rodante', NULL, 1);

-- ---------------------------------------------------------------------------
-- Usuarios: uno por rol. Las contraseñas estan cifradas con password_hash.
-- ---------------------------------------------------------------------------
INSERT INTO usuarios (food_truck_id, nombre, correo, contrasena, rol, activo) VALUES
  (NULL, 'Administrador de plataforma', 'plataforma@menu08.local', '$2y$10$SZ8i1WAdtsks1fsxCo38fOHbl47Lpf2khZK93oKhZUncz3TTcxe72', 'plataforma', 1),
  (1,    'Administrador del food truck','food truck@menu08.local',    '$2y$10$p/d4s3qIbmYBmIPvh114i.NHUYUos/.NiFCAK4hnB.HqyFFlXQ0hS',    'food truck',    1),
  (1,    'Cajero de demostracion',      'cajero@menu08.local',     '$2y$10$VHT13GrpH3WANNkk7AZf5.CUXnF0cZmFS8PmFkWq4N0nhIKR3LXk.',     'cajero',     1),
  (1,    'Produccion de demostracion',  'produccion@menu08.local', '$2y$10$7GGxngUjsOWFyD.VFAKcnuElZGG7tUSVLEmngkQ/TnCR4J2Kg31k6', 'produccion', 1);

-- ===========================================================================
-- PENDIENTE - Agenda de puntos de Festin Rodante
--
-- Un food truck para en sitios distintos segun el dia. Cada fila es una parada.
-- Recordar que una jornada nocturna cruza la medianoche: si `hora_fin` es menor
-- o igual que `hora_inicio`, se entiende que cierra al dia siguiente.
--
-- INSERT INTO ubicaciones
--   (food_truck_id, nombre, referencia, dia_semana, hora_inicio, hora_fin, activa)
-- VALUES
--   (1, '<punto>', '<referencia>', <1..7>, '18:00:00', '23:00:00', 1);
-- ===========================================================================

-- ===========================================================================
-- PENDIENTE - Carta de Festin Rodante
--
-- Las categorias y los productos reales los entrega el equipo del food truck.
-- No se siembran datos inventados: la carta de demostracion debe ser la de
-- Festin Rodante, no un catalogo generico.
--
-- INSERT INTO categorias (food_truck_id, nombre, orden, activo) VALUES
--   (1, '<categoria>', 1, 1);
--
-- INSERT INTO productos
--   (food_truck_id, categoria_id, nombre, descripcion, precio, disponible, orden)
-- VALUES
--   (1, 1, '<producto>', '<descripcion>', 0.00, 1, 1);
--
-- Mientras este bloque siga vacio, la carta publica se ve sin productos y los
-- modulos CAJA y SVP no se pueden recorrer de punta a punta.
-- ===========================================================================
