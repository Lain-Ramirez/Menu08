<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;

/**
 * Categorias de la carta. Toda consulta filtra por food_truck_id: un
 * identificador de otro food truck simplemente no devuelve fila.
 */
final class Categoria
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function delFoodTruck(int $foodTruckId, bool $soloActivas = false): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS productos
                  FROM categorias c
                 WHERE c.food_truck_id = :ft';

        if ($soloActivas) {
            $sql .= ' AND c.activo = 1';
        }

        $sql .= ' ORDER BY c.orden, c.nombre';

        $s = ConexionBD::obtener()->prepare($sql);
        $s->execute(['ft' => $foodTruckId]);

        return $s->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(int $id, int $foodTruckId): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT * FROM categorias WHERE id = :id AND food_truck_id = :ft LIMIT 1'
        );
        $s->execute(['id' => $id, 'ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    public static function nombreRepetido(int $foodTruckId, string $nombre, int $excepto = 0): bool
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT 1 FROM categorias WHERE food_truck_id = :ft AND nombre = :nombre AND id <> :id LIMIT 1'
        );
        $s->execute(['ft' => $foodTruckId, 'nombre' => $nombre, 'id' => $excepto]);

        return $s->fetch() !== false;
    }

    public static function crear(int $foodTruckId, string $nombre, int $orden): int
    {
        $pdo = ConexionBD::obtener();
        $s   = $pdo->prepare(
            'INSERT INTO categorias (food_truck_id, nombre, orden, activo) VALUES (:ft, :nombre, :orden, 1)'
        );
        $s->execute(['ft' => $foodTruckId, 'nombre' => $nombre, 'orden' => $orden]);

        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(int $id, int $foodTruckId, string $nombre, int $orden): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE categorias SET nombre = :nombre, orden = :orden
              WHERE id = :id AND food_truck_id = :ft'
        );
        $s->execute(['nombre' => $nombre, 'orden' => $orden, 'id' => $id, 'ft' => $foodTruckId]);
    }

    /**
     * Baja logica: la categoria se conserva para no romper los productos ni el
     * historial de ventas, pero deja de aparecer en la carta.
     */
    public static function cambiarEstado(int $id, int $foodTruckId, bool $activo): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE categorias SET activo = :activo WHERE id = :id AND food_truck_id = :ft'
        );
        $s->execute(['activo' => $activo ? 1 : 0, 'id' => $id, 'ft' => $foodTruckId]);
    }
}
