-- ---------------------------------------------------------------------------
-- Menu08 - Datos de PRUEBA
--
-- Crea un segundo food truck completo, "Truck de Pruebas", pensado para
-- ejercitar la aplicacion desde fuera (Postman, curl) sin tocar los datos de
-- Festin Rodante, que es el food truck de demostracion del prototipo y cuyo
-- catalogo real lo define su dueno.
--
-- Este archivo NO forma parte del sembrado del prototipo: `datos_iniciales.sql`
-- se ejecuta siempre, este solo cuando se quiere un banco de pruebas.
--
-- Uso:  mysql -u usuario -p nombre_base < basedatos/datos_pruebas.sql
--
-- Es reejecutable: borra su propio food truck y lo vuelve a crear, sin tocar
-- ninguna fila de los demas. El borrado va en el orden inverso a las
-- dependencias porque las llaves hacia food_trucks son ON DELETE RESTRICT.
-- ---------------------------------------------------------------------------

SET @slug := 'truck-de-pruebas';
SET @ft   := (SELECT id FROM food_trucks WHERE slug = @slug);

DELETE oi FROM orden_items oi JOIN ordenes o ON o.id = oi.orden_id WHERE o.food_truck_id = @ft;
DELETE FROM ordenes      WHERE food_truck_id = @ft;
DELETE FROM turnos_caja  WHERE food_truck_id = @ft;
DELETE FROM productos    WHERE food_truck_id = @ft;
DELETE FROM categorias   WHERE food_truck_id = @ft;
DELETE FROM ubicaciones  WHERE food_truck_id = @ft;
DELETE FROM usuarios     WHERE food_truck_id = @ft;
DELETE FROM food_trucks  WHERE id = @ft;

-- ---------------------------------------------------------------------------
-- El food truck
-- ---------------------------------------------------------------------------
INSERT INTO food_trucks (nombre, slug, descripcion, telefono, whatsapp, instagram, ciudad, activo)
VALUES (
  'Truck de Pruebas',
  'truck-de-pruebas',
  'Food truck de banco de pruebas. Existe para ejercitar la aplicacion desde fuera sin tocar los datos de Festin Rodante.',
  '6017000000',
  '573000000000',
  'truckdepruebas',
  'Bogota',
  1
);

SET @ft := LAST_INSERT_ID();

-- ---------------------------------------------------------------------------
-- Usuarios: uno por rol operativo. Misma contrasena que los de demostracion,
-- `Menu08*Demo2026`. El hash es de password_hash, con su propia sal.
-- ---------------------------------------------------------------------------
INSERT INTO usuarios (food_truck_id, nombre, correo, contrasena, rol, activo) VALUES
  (@ft, 'Administrador de pruebas', 'pruebas.foodtruck@menu08.local',
   '$2y$10$p/d4s3qIbmYBmIPvh114i.NHUYUos/.NiFCAK4hnB.HqyFFlXQ0hS', 'food_truck', 1),
  (@ft, 'Cajero de pruebas',        'pruebas.cajero@menu08.local',
   '$2y$10$VHT13GrpH3WANNkk7AZf5.CUXnF0cZmFS8PmFkWq4N0nhIKR3LXk.', 'cajero',     1),
  (@ft, 'Produccion de pruebas',    'pruebas.produccion@menu08.local',
   '$2y$10$7GGxngUjsOWFyD.VFAKcnuElZGG7tUSVLEmngkQ/TnCR4J2Kg31k6', 'produccion', 1);

-- ---------------------------------------------------------------------------
-- Agenda de paradas. La tercera cruza la medianoche a proposito: `hora_fin`
-- menor que `hora_inicio` significa que la jornada cierra al dia siguiente,
-- que es el caso normal de un truck nocturno.
-- ---------------------------------------------------------------------------
INSERT INTO ubicaciones (food_truck_id, nombre, referencia, latitud, longitud, dia_semana, hora_inicio, hora_fin, activa) VALUES
  (@ft, 'Parque de Pruebas',   'costado norte, junto a la ciclorruta', 4.6767000, -74.0483000, 3, '11:00:00', '15:00:00', 1),
  (@ft, 'Plaza de Pruebas',    'frente a la biblioteca',               4.6510000, -74.0560000, 5, '12:00:00', '20:00:00', 1),
  (@ft, 'Zona Rosa de Pruebas','sobre la calle peatonal',              4.6650000, -74.0540000, 6, '18:00:00', '01:00:00', 1),
  (@ft, 'Parada desactivada',  'no debe aparecer en la carta publica', NULL, NULL,             1, '09:00:00', '13:00:00', 0);

-- ---------------------------------------------------------------------------
-- Catalogo
-- ---------------------------------------------------------------------------
INSERT INTO categorias (food_truck_id, nombre, orden, activo) VALUES
  (@ft, 'Entradas',  1, 1),
  (@ft, 'Fuertes',   2, 1),
  (@ft, 'Bebidas',   3, 1),
  (@ft, 'Postres',   4, 1),
  (@ft, 'Categoria desactivada', 5, 0);

SET @cat_entradas := (SELECT id FROM categorias WHERE food_truck_id = @ft AND nombre = 'Entradas');
SET @cat_fuertes  := (SELECT id FROM categorias WHERE food_truck_id = @ft AND nombre = 'Fuertes');
SET @cat_bebidas  := (SELECT id FROM categorias WHERE food_truck_id = @ft AND nombre = 'Bebidas');
SET @cat_postres  := (SELECT id FROM categorias WHERE food_truck_id = @ft AND nombre = 'Postres');

-- Precios variados a proposito, incluido uno con decimales distintos de cero y
-- otro gratuito, para comprobar el calculo del total en centavos.
INSERT INTO productos (food_truck_id, categoria_id, nombre, descripcion, precio, disponible, orden) VALUES
  (@ft, @cat_entradas, 'Papas de prueba',        'Porcion pequena',                    6500.00, 1, 1),
  (@ft, @cat_entradas, 'Empanada de prueba',     'Unidad',                             3200.00, 1, 2),
  (@ft, @cat_entradas, 'Entrada agotada',        'Sirve para probar disponible = 0',   5000.00, 0, 3),
  (@ft, @cat_fuertes,  'Hamburguesa de prueba',  'La grande',                         24900.00, 1, 1),
  (@ft, @cat_fuertes,  'Perro de prueba',        'Con todo',                          18750.50, 1, 2),
  (@ft, @cat_fuertes,  'Salchipapa de prueba',   'Para compartir',                    21000.00, 1, 3),
  (@ft, @cat_bebidas,  'Gaseosa de prueba',      'Lata de 330 ml',                     4500.00, 1, 1),
  (@ft, @cat_bebidas,  'Limonada de prueba',     'Vaso de 500 ml',                     7000.00, 1, 2),
  (@ft, @cat_bebidas,  'Vaso de agua',           'Cortesia, para probar precio cero',     0.00, 1, 3),
  (@ft, @cat_postres,  'Brownie de prueba',      'Con helado',                         9900.00, 1, 1),
  (@ft, @cat_postres,  'Producto con un nombre deliberadamente largo para probar el corte en pantalla',
                                                 'Comprueba el maquetado',            12345.67, 1, 2);

-- El turno se deja CERRADO a proposito: asi la coleccion de Postman puede
-- recorrer el flujo entero desde el principio, abriendo el turno ella misma.
