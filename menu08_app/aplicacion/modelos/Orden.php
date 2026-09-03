<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;
use Menu08\Nucleo\DatosInvalidos;
use PDO;
use Throwable;

/**
 * Ordenes de CAJA.
 *
 * Regla de oro: los precios NUNCA vienen del navegador. Se leen de `productos`
 * dentro de la misma transaccion que escribe la venta, y con ellos se calculan
 * los subtotales y el total. Lo que el formulario diga sobre el importe es
 * informativo y se descarta.
 */
final class Orden
{
    private const MEDIOS = ['efectivo', 'tarjeta', 'transferencia'];

    /**
     * Registra la venta completa: cabecera e items, todo o nada.
     *
     * @param array<int, int> $lineas producto_id => cantidad
     *
     * @return array{id: int, numero: string, total: string, items: list<array<string, mixed>>}
     */
    public static function registrar(
        int $foodTruckId,
        int $turnoId,
        array $lineas,
        string $medioPago,
        ?string $nota = null
    ): array {
        if (!in_array($medioPago, self::MEDIOS, true)) {
            throw new DatosInvalidos('El medio de pago no es valido.');
        }

        $lineas = array_filter($lineas, static fn (int $c): bool => $c > 0);

        if ($lineas === []) {
            throw new DatosInvalidos('La orden no tiene ningun producto.');
        }

        foreach ($lineas as $cantidad) {
            if ($cantidad > 99) {
                throw new DatosInvalidos('La cantidad maxima por producto es 99.');
            }
        }

        $pdo = ConexionBD::obtener();
        $pdo->beginTransaction();

        try {
            // Bloquear el food truck serializa la numeracion: dos ventas
            // simultaneas no pueden obtener el mismo consecutivo.
            $bloqueo = $pdo->prepare('SELECT id FROM food_trucks WHERE id = :ft FOR UPDATE');
            $bloqueo->execute(['ft' => $foodTruckId]);

            if ($bloqueo->fetch() === false) {
                throw new DatosInvalidos('El food truck no existe.');
            }

            // El turno tiene que seguir abierto en este instante, no cuando se
            // cargo la pantalla: entre una cosa y otra pudieron cerrarlo.
            $t = $pdo->prepare(
                "SELECT id FROM turnos_caja
                  WHERE id = :t AND food_truck_id = :ft AND estado = 'abierto'
                  FOR UPDATE"
            );
            $t->execute(['t' => $turnoId, 'ft' => $foodTruckId]);

            if ($t->fetch() === false) {
                throw new DatosInvalidos('El turno ya no esta abierto. No se puede registrar la venta.');
            }

            // Precios y nombres vigentes, solo de productos de este food truck
            // y disponibles ahora mismo.
            $marcas = implode(',', array_fill(0, count($lineas), '?'));
            $p      = $pdo->prepare(
                "SELECT id, nombre, precio
                   FROM productos
                  WHERE food_truck_id = ? AND disponible = 1 AND id IN ($marcas)"
            );
            $p->execute(array_merge([$foodTruckId], array_keys($lineas)));

            $productos = [];

            foreach ($p->fetchAll() as $fila) {
                $productos[(int) $fila['id']] = $fila;
            }

            foreach (array_keys($lineas) as $id) {
                if (!isset($productos[$id])) {
                    throw new DatosInvalidos(sprintf(
                        'Un producto de la orden ya no esta disponible (identificador %d). Actualice la pantalla.',
                        $id
                    ));
                }
            }

            // El dinero se suma en centavos, con enteros. Acumular importes en
            // coma flotante arrastra errores de redondeo que en una jornada de
            // decenas de ventas terminan descuadrando la caja por unos pesos.
            $items         = [];
            $totalCentavos = 0;

            foreach ($lineas as $id => $cantidad) {
                $precio           = (string) $productos[$id]['precio'];
                $precioCentavos   = (int) round((float) $precio * 100);
                $subtotalCentavos = $precioCentavos * $cantidad;
                $totalCentavos   += $subtotalCentavos;

                $items[] = [
                    'producto_id'     => $id,
                    'nombre_producto' => (string) $productos[$id]['nombre'],
                    'precio_unitario' => $precio,
                    'cantidad'        => $cantidad,
                    'subtotal'        => number_format($subtotalCentavos / 100, 2, '.', ''),
                ];
            }

            $total = number_format($totalCentavos / 100, 2, '.', '');

            // Consecutivo dentro del turno. Se antepone el turno para que el
            // numero siga siendo unico dentro del food truck de un turno a otro.
            $c = $pdo->prepare('SELECT COUNT(*) AS n FROM ordenes WHERE turno_id = :t');
            $c->execute(['t' => $turnoId]);
            /** @var array{n: int} $conteo */
            $conteo = $c->fetch();
            $numero = sprintf('T%d-%03d', $turnoId, (int) $conteo['n'] + 1);

            $o = $pdo->prepare(
                'INSERT INTO ordenes (food_truck_id, turno_id, estado_id, numero, total, medio_pago, nota)
                 VALUES (:ft, :turno, :estado, :numero, :total, :medio, :nota)'
            );
            $o->execute([
                'ft'     => $foodTruckId,
                'turno'  => $turnoId,
                'estado' => self::idEstado('pendiente'),
                'numero' => $numero,
                'total'  => $total,
                'medio'  => $medioPago,
                'nota'   => $nota,
            ]);

            $ordenId = (int) $pdo->lastInsertId();

            $i = $pdo->prepare(
                'INSERT INTO orden_items
                    (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal)
                 VALUES (:orden, :producto, :nombre, :precio, :cantidad, :subtotal)'
            );

            foreach ($items as $item) {
                $i->execute([
                    'orden'    => $ordenId,
                    'producto' => $item['producto_id'],
                    'nombre'   => $item['nombre_producto'],
                    'precio'   => $item['precio_unitario'],
                    'cantidad' => $item['cantidad'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            $pdo->commit();

            return ['id' => $ordenId, 'numero' => $numero, 'total' => $total, 'items' => $items];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(int $id, int $foodTruckId): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT o.*, e.nombre AS estado, e.codigo AS estado_codigo, t.id AS turno
               FROM ordenes o
               JOIN estados_orden e ON e.id = o.estado_id
               JOIN turnos_caja t   ON t.id = o.turno_id
              WHERE o.id = :id AND o.food_truck_id = :ft
              LIMIT 1'
        );
        $s->execute(['id' => $id, 'ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function items(int $ordenId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT * FROM orden_items WHERE orden_id = :o ORDER BY id'
        );
        $s->execute(['o' => $ordenId]);

        return $s->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function delTurno(int $turnoId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT o.id, o.numero, o.total, o.medio_pago, o.creado_en, e.nombre AS estado
               FROM ordenes o
               JOIN estados_orden e ON e.id = o.estado_id
              WHERE o.turno_id = :t
              ORDER BY o.id DESC'
        );
        $s->execute(['t' => $turnoId]);

        return $s->fetchAll();
    }

    private static function idEstado(string $codigo): int
    {
        $s = ConexionBD::obtener()->prepare('SELECT id FROM estados_orden WHERE codigo = :c LIMIT 1');
        $s->execute(['c' => $codigo]);

        $fila = $s->fetch();

        if ($fila === false) {
            throw new DatosInvalidos(sprintf('Falta el estado de orden "%s" en la base.', $codigo));
        }

        return (int) $fila['id'];
    }
}
