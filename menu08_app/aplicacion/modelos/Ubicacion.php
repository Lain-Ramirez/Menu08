<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;

/**
 * Agenda de paradas del food truck.
 *
 * Un food truck no tiene direccion fija: para en puntos distintos segun el dia.
 * Cada fila es una parada programada —punto, referencia, dia y franja horaria—
 * y con ellas la carta publica responde la pregunta "donde estan hoy".
 *
 * Toda consulta filtra por food_truck_id: una parada de otro food truck no
 * devuelve fila, y el controlador la trata como inexistente.
 */
final class Ubicacion
{
    /**
     * Los dias como los numera la tabla: 1 lunes ... 7 domingo.
     *
     * Coincide a proposito con WEEKDAY() + 1 de MySQL y con format('N') de PHP.
     * Ojo: DAYOFWEEK() numera 1 = domingo y WEEKDAY() a secas 0 = lunes; ninguna
     * de las dos sirve tal cual.
     *
     * Viven aqui, en un solo sitio, igual que Orden::TRANSICIONES. La vista los
     * recibe como dato y no los vuelve a escribir.
     */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    /**
     * Agenda completa para el panel: activas e inactivas.
     *
     * @return list<array<string, mixed>>
     */
    public static function delFoodTruck(int $foodTruckId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT * FROM ubicaciones
              WHERE food_truck_id = :ft
              ORDER BY dia_semana, hora_inicio, id'
        );
        $s->execute(['ft' => $foodTruckId]);

        return $s->fetchAll();
    }

    /**
     * La agenda que consume la carta publica. Una parada desactivada no sale de
     * aqui: es la mitad de la baja logica, y la otra mitad esta en vigente().
     *
     * @return list<array<string, mixed>>
     */
    public static function agendaPublica(int $foodTruckId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT * FROM ubicaciones
              WHERE food_truck_id = :ft AND activa = 1
              ORDER BY dia_semana, hora_inicio, id'
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
            'SELECT * FROM ubicaciones WHERE id = :id AND food_truck_id = :ft LIMIT 1'
        );
        $s->execute(['id' => $id, 'ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * La parada vigente en este momento, o en el instante que se pida.
     *
     * Aqui esta el caso que define la tabla: una jornada de food truck nocturno
     * cruza la medianoche. Cuando `hora_fin` es menor o igual que `hora_inicio`
     * se entiende que la jornada cierra al dia siguiente, asi que a las 00:30 la
     * parada que sigue abierta esta declarada en el dia ANTERIOR. De ahi las
     * tres ramas de la condicion.
     *
     * Dos detalles que no son adorno:
     *
     * - El instante se calcula UNA vez, en la tabla derivada `ahora`. PDO va sin
     *   emulacion de preparadas, y ahi un marcador nombrado solo puede aparecer
     *   una vez por sentencia; la condicion necesita el momento cinco veces.
     *
     * - El dia anterior sale de `- INTERVAL 1 DAY`, no de restarle uno al numero
     *   del dia. Asi el envolvimiento del domingo al lunes lo resuelve la
     *   aritmetica de fechas de MySQL: una parada del domingo de 20:00 a 02:00
     *   sigue vigente el lunes a la 01:00, sin ningun caso especial.
     *
     * El cierre es exclusivo: a las 15:00:00 en punto, una parada de 11:00 a
     * 15:00 ya cerro. Si las dos horas son iguales, la jornada dura 24 horas.
     *
     * @param string|null $momento 'AAAA-MM-DD HH:MM:SS'; null es el reloj del servidor
     *
     * @return array<string, mixed>|null
     */
    public static function vigente(int $foodTruckId, ?string $momento = null): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT u.*
               FROM ubicaciones u
               CROSS JOIN (SELECT COALESCE(CAST(:momento AS DATETIME), NOW()) AS m) AS ahora
              WHERE u.food_truck_id = :ft
                AND u.activa = 1
                AND (
                     -- Jornada normal: 11:00 -> 15:00 del mismo dia.
                     (u.hora_fin > u.hora_inicio
                      AND u.dia_semana = WEEKDAY(ahora.m) + 1
                      AND TIME(ahora.m) >= u.hora_inicio
                      AND TIME(ahora.m) <  u.hora_fin)

                     -- Jornada nocturna antes de medianoche: 18:00 -> 01:00 a las 23:00.
                  OR (u.hora_fin <= u.hora_inicio
                      AND u.dia_semana = WEEKDAY(ahora.m) + 1
                      AND TIME(ahora.m) >= u.hora_inicio)

                     -- La misma jornada ya pasada la medianoche: a las 00:30 sigue
                     -- abierta, pero la parada esta declarada en el dia anterior.
                  OR (u.hora_fin <= u.hora_inicio
                      AND u.dia_semana = WEEKDAY(ahora.m - INTERVAL 1 DAY) + 1
                      AND TIME(ahora.m) <  u.hora_fin)
                )
              ORDER BY u.hora_inicio, u.id
              LIMIT 1'
        );
        $s->execute(['momento' => $momento, 'ft' => $foodTruckId]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Una jornada cruza la medianoche cuando cierra a la misma hora a la que
     * abre, o antes. Lo usan la vista del panel y la carta para avisarlo.
     */
    public static function cruzaMedianoche(string $horaInicio, string $horaFin): bool
    {
        return $horaFin <= $horaInicio;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(int $foodTruckId, array $datos): int
    {
        $pdo = ConexionBD::obtener();
        $s   = $pdo->prepare(
            'INSERT INTO ubicaciones
                 (food_truck_id, nombre, referencia, latitud, longitud,
                  dia_semana, hora_inicio, hora_fin, activa)
             VALUES (:ft, :nombre, :referencia, :latitud, :longitud,
                     :dia, :inicio, :fin, 1)'
        );

        $s->execute([
            'ft'         => $foodTruckId,
            'nombre'     => $datos['nombre'],
            'referencia' => $datos['referencia'],
            'latitud'    => $datos['latitud'],
            'longitud'   => $datos['longitud'],
            'dia'        => $datos['dia_semana'],
            'inicio'     => $datos['hora_inicio'],
            'fin'        => $datos['hora_fin'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * La edicion no toca `activa`: se activa y se desactiva por su propia
     * accion, igual que en categorias y productos.
     *
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, int $foodTruckId, array $datos): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE ubicaciones
                SET nombre = :nombre, referencia = :referencia,
                    latitud = :latitud, longitud = :longitud,
                    dia_semana = :dia, hora_inicio = :inicio, hora_fin = :fin
              WHERE id = :id AND food_truck_id = :ft'
        );

        $s->execute([
            'nombre'     => $datos['nombre'],
            'referencia' => $datos['referencia'],
            'latitud'    => $datos['latitud'],
            'longitud'   => $datos['longitud'],
            'dia'        => $datos['dia_semana'],
            'inicio'     => $datos['hora_inicio'],
            'fin'        => $datos['hora_fin'],
            'id'         => $id,
            'ft'         => $foodTruckId,
        ]);
    }

    /**
     * Baja logica: la parada se conserva —el truck vuelve a ese punto la semana
     * que viene— pero deja de aparecer en la carta y de darse por vigente.
     */
    public static function cambiarEstado(int $id, int $foodTruckId, bool $activa): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE ubicaciones SET activa = :activa WHERE id = :id AND food_truck_id = :ft'
        );
        $s->execute(['activa' => $activa ? 1 : 0, 'id' => $id, 'ft' => $foodTruckId]);
    }

    /**
     * Contador para el tablero del panel.
     *
     * Dos marcadores distintos para el mismo valor: sin emulacion de preparadas
     * un nombre no puede repetirse en la sentencia.
     *
     * @return array<string, int>
     */
    public static function resumen(int $foodTruckId): array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT COUNT(*) AS paradas,
                    SUM(CASE WHEN activa = 1 THEN 1 ELSE 0 END) AS activas
               FROM ubicaciones
              WHERE food_truck_id = :ft'
        );
        $s->execute(['ft' => $foodTruckId]);

        $fila = $s->fetch();

        return [
            'paradas' => (int) ($fila['paradas'] ?? 0),
            'activas' => (int) ($fila['activas'] ?? 0),
        ];
    }
}
