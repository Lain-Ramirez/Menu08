<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;
use Menu08\Nucleo\DatosInvalidos;
use PDO;
use Throwable;

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
     *
     * Van con tilde, a diferencia del resto del codigo: no son identificadores,
     * son las palabras que lee el cliente en la carta publica —«Miércoles de
     * 11:00 a 15:00»— y ahi un «Miercoles» se lee como una errata del negocio.
     * No viajan en ningun contrato JSON, asi que la tilde no rompe nada.
     */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
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
     * La proxima parada que abre, contando desde este momento o desde el que se
     * pida.
     *
     * Es la respuesta cuando vigente() devuelve null: la carta publica no puede
     * quedarse en blanco justo con la pregunta que el cliente vino a hacer. Si
     * el truck no esta parado ahora, lo util es decirle cuando vuelve.
     *
     * COMO SE CALCULA. La agenda es semanal y se repite, asi que "la proxima"
     * no es un ORDEN por dia y hora: el domingo a las 23:00 la proxima parada es
     * la del lunes, que ordenando por dia_semana quedaria la primera de todas y
     * ordenando por fecha no existe todavia. Se convierte cada parada en la
     * FECHA en la que abre:
     *
     *   1. Cuantos dias faltan hasta su dia de la semana, entre 0 y 6, con el
     *      modulo 7. El 0 es hoy.
     *   2. Esa fecha con su hora de apertura.
     *   3. Si ya paso —una parada de hoy que abria a las 11:00 cuando son las
     *      14:00— se le suma una semana, que es cuando vuelve a abrir.
     *
     * Y se ordena por esa fecha. El envolvimiento del domingo al lunes sale
     * solo, sin ningun caso especial, que es la misma idea que sostiene la
     * tercera rama de vigente().
     *
     * La fila devuelta trae una columna de mas, `abre_en`, con esa fecha ya
     * calculada: la vista necesita saber si es hoy, manana o el viernes, y eso
     * no se puede deducir de dia_semana sin volver a hacer esta cuenta.
     *
     * @param string|null $momento 'AAAA-MM-DD HH:MM:SS'; null es el reloj del servidor
     *
     * @return array<string, mixed>|null
     */
    public static function proxima(int $foodTruckId, ?string $momento = null): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT p.id, p.food_truck_id, p.nombre, p.referencia, p.latitud, p.longitud,
                    p.dia_semana, p.hora_inicio, p.hora_fin, p.activa,
                    IF(p.base > p.m, p.base, p.base + INTERVAL 7 DAY) AS abre_en
               FROM (
                     SELECT u.*,
                            ahora.m AS m,
                            TIMESTAMP(
                              DATE(ahora.m) + INTERVAL ((7 + u.dia_semana - (WEEKDAY(ahora.m) + 1)) % 7) DAY,
                              u.hora_inicio
                            ) AS base
                       FROM ubicaciones u
                       CROSS JOIN (SELECT COALESCE(CAST(:momento AS DATETIME), NOW()) AS m) AS ahora
                      WHERE u.food_truck_id = :ft AND u.activa = 1
                    ) AS p
              ORDER BY abre_en, p.hora_inicio, p.id
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
     * Las nueve columnas que viajan al cliente movil.
     *
     * Se enumeran en vez de usar SELECT *: food_truck_id, creado_en y
     * actualizado_en no le dicen nada al telefono, y el contrato de la
     * respuesta tiene que ser el mismo en las dos ramas de asentarPunto().
     */
    private const COLUMNAS_CONTRATO =
        'id, nombre, referencia, latitud, longitud, dia_semana, hora_inicio, hora_fin, activa';

    /**
     * Asienta en la agenda el punto que reporta la aplicacion movil.
     *
     * Dos ramas y una sola regla: si en este instante hay una parada vigente,
     * el punto es SUYO y solo se le corrigen las coordenadas; si no la hay
     * —el truck paro fuera de su horario programado— el reporte no se tira, se
     * registra como parada nueva que el dueño podra renombrar o desactivar
     * despues desde /panel/ubicaciones.
     *
     * Elegir entre una rama y otra depende de lo que la tabla tenga en ese
     * momento, asi que todo va en una transaccion que empieza bloqueando la
     * fila del food truck, igual que Orden::registrar() serializa la
     * numeracion. Sin ese bloqueo, dos telefonos reportando a la vez fuera de
     * horario crearian cada uno su parada.
     *
     * @param string      $latitud  ya validada y normalizada por Validador::coordenada()
     * @param string      $longitud igual
     * @param string|null $momento  'AAAA-MM-DD HH:MM:SS'; null es el reloj del servidor
     *
     * @return array{parada: array<string, mixed>, creada: bool}
     *
     * @throws DatosInvalidos si el food truck no existe
     */
    public static function asentarPunto(
        int $foodTruckId,
        string $latitud,
        string $longitud,
        ?string $momento = null
    ): array {
        // Un solo instante para la consulta y para los campos de la parada
        // nueva. Leyendo el reloj dos veces, un reporte lanzado en el ultimo
        // segundo de un dia podria buscar en un dia y escribir en el siguiente.
        $momento ??= date('Y-m-d H:i:s');

        $pdo = ConexionBD::obtener();
        $pdo->beginTransaction();

        try {
            $bloqueo = $pdo->prepare('SELECT id FROM food_trucks WHERE id = :ft FOR UPDATE');
            $bloqueo->execute(['ft' => $foodTruckId]);

            if ($bloqueo->fetch() === false) {
                throw new DatosInvalidos('El food truck no existe.');
            }

            $vigente = self::vigenteBloqueada($pdo, $foodTruckId, $momento);

            if ($vigente !== null) {
                // Solo el punto. El nombre, la referencia, el dia y las horas
                // los puso el dueño al programar la parada y el reporte no
                // viene a reescribirlos: por eso no se usa actualizar(), que
                // pisa los siete campos del formulario del panel.
                $u = $pdo->prepare(
                    'UPDATE ubicaciones
                        SET latitud = :lat, longitud = :lon
                      WHERE id = :id AND food_truck_id = :ft'
                );
                $u->execute([
                    'lat' => $latitud,
                    'lon' => $longitud,
                    'id'  => (int) $vigente['id'],
                    'ft'  => $foodTruckId,
                ]);

                $id     = (int) $vigente['id'];
                $creada = false;
            } else {
                // hora_fin igual a hora_inicio deja esta parada vigente desde
                // el mismo segundo y durante 24 horas, por la segunda rama de
                // vigente(). Asi el reporte siguiente cae en la rama de arriba
                // y actualiza esta fila, en vez de sembrar una parada por cada
                // pulsacion del boton.
                $hora = substr($momento, 11, 5) . ':00';

                $id = self::crear($foodTruckId, [
                    'nombre'      => sprintf('Punto reportado %s', substr($momento, 0, 16)),
                    'referencia'  => 'Registrado desde la aplicacion movil',
                    'latitud'     => $latitud,
                    'longitud'    => $longitud,
                    'dia_semana'  => (int) date('N', (int) strtotime($momento)),
                    'hora_inicio' => $hora,
                    'hora_fin'    => $hora,
                ]);

                $creada = true;
            }

            // Se relee dentro de la transaccion para devolver lo que quedo
            // escrito de verdad, con el formato de DECIMAL(10,7), y no lo que
            // creemos haber escrito.
            $p = $pdo->prepare(
                'SELECT ' . self::COLUMNAS_CONTRATO . '
                   FROM ubicaciones
                  WHERE id = :id AND food_truck_id = :ft
                  LIMIT 1'
            );
            $p->execute(['id' => $id, 'ft' => $foodTruckId]);

            /** @var array<string, mixed> $parada */
            $parada = $p->fetch();

            $pdo->commit();

            return ['parada' => $parada, 'creada' => $creada];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * La misma parada que devuelve vigente(), pero con la fila bloqueada.
     *
     * Se repite la condicion en vez de reutilizar vigente() por dos motivos que
     * no se pueden esquivar:
     *
     * - Hace falta FOR UPDATE, y vigente() resuelve el instante en la tabla
     *   derivada `ahora`, que lo impide.
     * - Sin esa tabla derivada el instante hay que repetirlo siete veces, y
     *   PDO va sin emulacion de preparadas: un marcador nombrado solo puede
     *   aparecer una vez por sentencia. De ahi :momento1 ... :momento7.
     *
     * Las tres ramas y el envolvimiento del domingo al lunes con
     * `- INTERVAL 1 DAY` son los de vigente(): si una cambia, la otra tambien.
     *
     * @return array<string, mixed>|null
     */
    private static function vigenteBloqueada(PDO $pdo, int $foodTruckId, string $momento): ?array
    {
        $s = $pdo->prepare(
            'SELECT ' . self::COLUMNAS_CONTRATO . '
               FROM ubicaciones
              WHERE food_truck_id = :ft
                AND activa = 1
                AND (
                     -- Jornada normal: 11:00 -> 15:00 del mismo dia.
                     (hora_fin > hora_inicio
                      AND dia_semana = WEEKDAY(CAST(:momento1 AS DATETIME)) + 1
                      AND TIME(CAST(:momento2 AS DATETIME)) >= hora_inicio
                      AND TIME(CAST(:momento3 AS DATETIME)) <  hora_fin)

                     -- Jornada nocturna antes de medianoche: 18:00 -> 01:00 a las 23:00.
                  OR (hora_fin <= hora_inicio
                      AND dia_semana = WEEKDAY(CAST(:momento4 AS DATETIME)) + 1
                      AND TIME(CAST(:momento5 AS DATETIME)) >= hora_inicio)

                     -- La misma jornada pasada la medianoche: a las 00:30 sigue
                     -- abierta, pero la parada esta declarada en el dia anterior.
                  OR (hora_fin <= hora_inicio
                      AND dia_semana = WEEKDAY(CAST(:momento6 AS DATETIME) - INTERVAL 1 DAY) + 1
                      AND TIME(CAST(:momento7 AS DATETIME)) <  hora_fin)
                )
              ORDER BY hora_inicio, id
              LIMIT 1
              FOR UPDATE'
        );

        $s->execute([
            'ft'       => $foodTruckId,
            'momento1' => $momento,
            'momento2' => $momento,
            'momento3' => $momento,
            'momento4' => $momento,
            'momento5' => $momento,
            'momento6' => $momento,
            'momento7' => $momento,
        ]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
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
