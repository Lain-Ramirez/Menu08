<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;
use PDO;
use Throwable;

/**
 * Turnos de CAJA. Un food truck solo puede tener un turno abierto a la vez:
 * el turno es lo que agrupa las ventas de una jornada y da sentido al cuadre.
 */
final class TurnoCaja
{
    /**
     * Turno abierto del food truck, si lo hay.
     *
     * @return array<string, mixed>|null
     */
    public static function vigente(int $foodTruckId): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            "SELECT t.*, u.nombre AS cajero
               FROM turnos_caja t
               JOIN usuarios u ON u.id = t.usuario_id
              WHERE t.food_truck_id = :ft AND t.estado = 'abierto'
              ORDER BY t.id DESC
              LIMIT 1"
        );
        $s->execute(['ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(int $id, int $foodTruckId): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT t.*, u.nombre AS cajero
               FROM turnos_caja t
               JOIN usuarios u ON u.id = t.usuario_id
              WHERE t.id = :id AND t.food_truck_id = :ft
              LIMIT 1'
        );
        $s->execute(['id' => $id, 'ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Abre un turno. Devuelve su identificador, o 0 si ya habia uno vigente.
     *
     * La comprobacion y la insercion van dentro de una transaccion con bloqueo
     * de fila: sin eso, dos cajeros pulsando «abrir» a la vez crearian dos
     * turnos y las ventas quedarian repartidas entre ambos.
     */
    public static function abrir(int $foodTruckId, int $usuarioId, string $base): int
    {
        $pdo = ConexionBD::obtener();
        $pdo->beginTransaction();

        try {
            $s = $pdo->prepare(
                "SELECT id FROM turnos_caja
                  WHERE food_truck_id = :ft AND estado = 'abierto'
                  FOR UPDATE"
            );
            $s->execute(['ft' => $foodTruckId]);

            if ($s->fetch() !== false) {
                $pdo->rollBack();

                return 0;
            }

            $i = $pdo->prepare(
                "INSERT INTO turnos_caja (food_truck_id, usuario_id, base_inicial, total_ventas, estado)
                 VALUES (:ft, :usuario, :base, 0.00, 'abierto')"
            );
            $i->execute(['ft' => $foodTruckId, 'usuario' => $usuarioId, 'base' => $base]);

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Resumen del turno: total vendido, cantidad de ordenes, unidades
     * despachadas y el desglose por medio de pago.
     *
     * @return array{total: string, ordenes: int, unidades: int, medios: list<array<string, mixed>>}
     */
    public static function resumen(int $turnoId): array
    {
        $pdo = ConexionBD::obtener();

        $s = $pdo->prepare(
            'SELECT COUNT(*) AS ordenes, COALESCE(SUM(total), 0) AS total
               FROM ordenes WHERE turno_id = :t'
        );
        $s->execute(['t' => $turnoId]);
        /** @var array{ordenes: int, total: string} $general */
        $general = $s->fetch();

        $u = $pdo->prepare(
            'SELECT COALESCE(SUM(i.cantidad), 0) AS unidades
               FROM orden_items i
               JOIN ordenes o ON o.id = i.orden_id
              WHERE o.turno_id = :t'
        );
        $u->execute(['t' => $turnoId]);
        /** @var array{unidades: int} $unidades */
        $unidades = $u->fetch();

        $m = $pdo->prepare(
            'SELECT medio_pago, COUNT(*) AS ordenes, COALESCE(SUM(total), 0) AS total
               FROM ordenes
              WHERE turno_id = :t
              GROUP BY medio_pago
              ORDER BY medio_pago'
        );
        $m->execute(['t' => $turnoId]);

        return [
            'total'    => (string) $general['total'],
            'ordenes'  => (int) $general['ordenes'],
            'unidades' => (int) $unidades['unidades'],
            'medios'   => $m->fetchAll(),
        ];
    }

    /**
     * Cierra el turno guardando lo vendido, lo declarado y la diferencia.
     *
     * El total vendido se recalcula desde `ordenes` en el momento del cierre,
     * no se arrastra de un contador: asi el cuadre refleja lo que hay en la
     * base y no lo que alguien fue sumando por el camino.
     */
    public static function cerrar(int $id, int $foodTruckId, string $totalDeclarado): bool
    {
        $pdo = ConexionBD::obtener();

        $s = $pdo->prepare(
            "UPDATE turnos_caja t
                SET t.total_ventas    = (SELECT COALESCE(SUM(o.total), 0) FROM ordenes o WHERE o.turno_id = t.id),
                    t.total_declarado = :declarado,
                    t.diferencia      = :declarado2 - (t.base_inicial +
                                          (SELECT COALESCE(SUM(o.total), 0) FROM ordenes o WHERE o.turno_id = t.id)),
                    t.estado          = 'cerrado',
                    t.cerrado_en      = NOW()
              WHERE t.id = :id AND t.food_truck_id = :ft AND t.estado = 'abierto'"
        );

        $s->execute([
            'declarado'  => $totalDeclarado,
            'declarado2' => $totalDeclarado,
            'id'         => $id,
            'ft'         => $foodTruckId,
        ]);

        return $s->rowCount() === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function historial(int $foodTruckId, int $limite = 20): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT t.*, u.nombre AS cajero,
                    (SELECT COUNT(*) FROM ordenes o WHERE o.turno_id = t.id) AS ordenes
               FROM turnos_caja t
               JOIN usuarios u ON u.id = t.usuario_id
              WHERE t.food_truck_id = :ft
              ORDER BY t.id DESC
              LIMIT :limite'
        );
        $s->bindValue('ft', $foodTruckId, PDO::PARAM_INT);
        $s->bindValue('limite', $limite, PDO::PARAM_INT);
        $s->execute();

        return $s->fetchAll();
    }
}
