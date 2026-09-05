<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;

/**
 * Productos del catalogo. Alimenta la carta publica y, mas adelante, CAJA.
 * Toda consulta filtra por food_truck_id.
 */
final class Producto
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function delFoodTruck(int $foodTruckId, ?int $categoriaId = null): array
    {
        $sql = 'SELECT p.*, c.nombre AS categoria
                  FROM productos p
                  JOIN categorias c ON c.id = p.categoria_id
                 WHERE p.food_truck_id = :ft';

        $parametros = ['ft' => $foodTruckId];

        if ($categoriaId !== null) {
            $sql .= ' AND p.categoria_id = :cat';
            $parametros['cat'] = $categoriaId;
        }

        $sql .= ' ORDER BY c.orden, c.nombre, p.orden, p.nombre';

        $s = ConexionBD::obtener()->prepare($sql);
        $s->execute($parametros);

        return $s->fetchAll();
    }

    /**
     * Catalogo de la carta publica: solo categorias activas y productos
     * disponibles, agrupados por categoria.
     *
     * Un producto marcado como no disponible desaparece de aqui, pero su
     * registro sigue en la tabla y en las ordenes ya guardadas.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalogoPublico(int $foodTruckId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT c.id AS categoria_id, c.nombre AS categoria, c.orden AS categoria_orden,
                    p.id, p.nombre, p.descripcion, p.precio, p.foto
               FROM categorias c
               JOIN productos p ON p.categoria_id = c.id
              WHERE c.food_truck_id = :ft AND c.activo = 1 AND p.disponible = 1
              ORDER BY c.orden, c.nombre, p.orden, p.nombre'
        );
        $s->execute(['ft' => $foodTruckId]);

        return $s->fetchAll();
    }

    /**
     * Catalogo de la carta publica: categorias activas con TODOS sus productos,
     * disponibles o no, cada uno con su bandera.
     *
     * Se separa de catalogoPublico() a proposito. La carta muestra lo agotado
     * atenuado y con su etiqueta, porque el cliente que hace fila necesita
     * saber que hoy no hay chunchullo antes de llegar a la ventanilla. CAJA no:
     * ahi un agotado que se puede pulsar es una venta que no se puede entregar.
     * Son dos preguntas distintas, y por eso son dos consultas distintas.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalogoCarta(int $foodTruckId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT c.id AS categoria_id, c.nombre AS categoria, c.orden AS categoria_orden,
                    p.id, p.nombre, p.descripcion, p.precio, p.foto, p.disponible
               FROM categorias c
               JOIN productos p ON p.categoria_id = c.id
              WHERE c.food_truck_id = :ft AND c.activo = 1
              ORDER BY c.orden, c.nombre, p.disponible DESC, p.orden, p.nombre'
        );
        $s->execute(['ft' => $foodTruckId]);

        return $s->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(int $id, int $foodTruckId): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT * FROM productos WHERE id = :id AND food_truck_id = :ft LIMIT 1'
        );
        $s->execute(['id' => $id, 'ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    public static function nombreRepetido(int $categoriaId, string $nombre, int $excepto = 0): bool
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT 1 FROM productos WHERE categoria_id = :cat AND nombre = :nombre AND id <> :id LIMIT 1'
        );
        $s->execute(['cat' => $categoriaId, 'nombre' => $nombre, 'id' => $excepto]);

        return $s->fetch() !== false;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(int $foodTruckId, array $datos): int
    {
        $pdo = ConexionBD::obtener();
        $s   = $pdo->prepare(
            'INSERT INTO productos
                (food_truck_id, categoria_id, nombre, descripcion, precio, foto, disponible, orden)
             VALUES (:ft, :cat, :nombre, :descripcion, :precio, :foto, :disponible, :orden)'
        );

        $s->execute([
            'ft'          => $foodTruckId,
            'cat'         => $datos['categoria_id'],
            'nombre'      => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'precio'      => $datos['precio'],
            'foto'        => $datos['foto'],
            'disponible'  => $datos['disponible'],
            'orden'       => $datos['orden'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * La foto solo se reemplaza si llego una nueva: COALESCE conserva la
     * anterior cuando el formulario se envia sin archivo.
     *
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, int $foodTruckId, array $datos): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE productos
                SET categoria_id = :cat, nombre = :nombre, descripcion = :descripcion,
                    precio = :precio, disponible = :disponible, orden = :orden,
                    foto = COALESCE(:foto, foto)
              WHERE id = :id AND food_truck_id = :ft'
        );

        $s->execute([
            'cat'         => $datos['categoria_id'],
            'nombre'      => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'precio'      => $datos['precio'],
            'disponible'  => $datos['disponible'],
            'orden'       => $datos['orden'],
            'foto'        => $datos['foto'],
            'id'          => $id,
            'ft'          => $foodTruckId,
        ]);
    }

    /**
     * Baja logica. El producto no se borra: las ordenes ya registradas
     * conservan su copia historica del nombre y del precio.
     */
    public static function cambiarDisponibilidad(int $id, int $foodTruckId, bool $disponible): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE productos SET disponible = :d WHERE id = :id AND food_truck_id = :ft'
        );
        $s->execute(['d' => $disponible ? 1 : 0, 'id' => $id, 'ft' => $foodTruckId]);
    }

    /**
     * @return array{categorias: int, productos: int, disponibles: int}
     */
    public static function resumen(int $foodTruckId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT (SELECT COUNT(*) FROM categorias WHERE food_truck_id = :a) AS categorias,
                    (SELECT COUNT(*) FROM productos  WHERE food_truck_id = :b) AS productos,
                    (SELECT COUNT(*) FROM productos  WHERE food_truck_id = :c AND disponible = 1) AS disponibles'
        );
        $s->execute(['a' => $foodTruckId, 'b' => $foodTruckId, 'c' => $foodTruckId]);

        /** @var array{categorias: int, productos: int, disponibles: int} $fila */
        $fila = $s->fetch();

        return $fila;
    }
}
