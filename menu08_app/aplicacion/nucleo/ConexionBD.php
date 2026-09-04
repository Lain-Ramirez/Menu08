<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Punto unico de acceso a MySQL. Devuelve siempre la misma instancia de PDO.
 *
 * La conexion se configura para lanzar excepciones, devolver arreglos asociativos
 * y NO emular sentencias preparadas: la emulacion interpola los valores del lado
 * de PHP, que es justo lo que se quiere evitar.
 */
final class ConexionBD
{
    /** Colombia, que es donde opera el prototipo. No tiene horario de verano. */
    private const ZONA_POR_DEFECTO = 'America/Bogota';

    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function obtener(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $servidor = (string) Configuracion::obtener('base_datos.servidor', 'localhost');
        $puerto   = (int) Configuracion::obtener('base_datos.puerto', 3306);
        $nombre   = (string) Configuracion::obtener('base_datos.nombre', '');
        $usuario  = (string) Configuracion::obtener('base_datos.usuario', '');
        $clave    = (string) Configuracion::obtener('base_datos.contrasena', '');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $servidor, $puerto, $nombre);

        try {
            self::$pdo = new PDO($dsn, $usuario, $clave, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // Sin esto, NOW() responde con el reloj del sistema del servidor, que
            // en un hosting compartido suele estar en UTC. Mientras solo se
            // comparan marcas de la propia base entre si, el desfase se cancela y
            // no se nota; en cuanto se compara el reloj con una hora escrita a
            // mano —la franja de una parada— se nota entero: a las 00:30 de
            // Bogota, un servidor en UTC cree que son las 05:30.
            //
            // Va en su propio try: si el hosting no dejara ejecutarlo, la
            // aplicacion sigue funcionando con el reloj del servidor y el aviso
            // queda en la bitacora. Un fallo aqui no justifica tumbar el sitio.
            try {
                self::$pdo->prepare('SET time_zone = ?')->execute([self::desplazamiento()]);
            } catch (PDOException $e) {
                Bitacora::registrar('No fue posible fijar la zona horaria de MySQL: ' . $e->getMessage(), 'AVISO');
            }
        } catch (PDOException $e) {
            // El mensaje original de PDO puede incluir el usuario y el servidor.
            // Se guarda en la bitacora, pero nunca se propaga hacia el navegador.
            Bitacora::registrar('Conexion a la base de datos fallida: ' . $e->getMessage());

            throw new RuntimeException('No fue posible conectar con la base de datos.');
        }

        return self::$pdo;
    }

    /**
     * Desplazamiento horario de la aplicacion, con la forma que espera MySQL.
     *
     * Se envia el desplazamiento (-05:00) y no el nombre de la zona porque las
     * tablas de zonas horarias de MySQL no siempre estan cargadas en un hosting
     * compartido, y sin ellas 'America/Bogota' falla. Se calcula con PHP en cada
     * conexion, asi que una zona con horario de verano tambien saldria bien.
     */
    private static function desplazamiento(): string
    {
        $zona = (string) Configuracion::obtener('zona_horaria', self::ZONA_POR_DEFECTO);

        try {
            return (new DateTimeImmutable('now', new DateTimeZone($zona)))->format('P');
        } catch (Throwable $e) {
            Bitacora::registrar(
                sprintf('Zona horaria "%s" desconocida; se usa %s.', $zona, self::ZONA_POR_DEFECTO),
                'AVISO'
            );

            return (new DateTimeImmutable('now', new DateTimeZone(self::ZONA_POR_DEFECTO)))->format('P');
        }
    }

    /**
     * Solo para las pruebas: descarta la conexion viva.
     */
    public static function reiniciar(): void
    {
        self::$pdo = null;
    }
}
