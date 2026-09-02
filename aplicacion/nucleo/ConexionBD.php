<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Punto unico de acceso a MySQL. Devuelve siempre la misma instancia de PDO.
 *
 * La conexion se configura para lanzar excepciones, devolver arreglos asociativos
 * y NO emular sentencias preparadas: la emulacion interpola los valores del lado
 * de PHP, que es justo lo que se quiere evitar.
 */
final class ConexionBD
{
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
        } catch (PDOException $e) {
            // El mensaje original de PDO puede incluir el usuario y el servidor.
            // Se guarda en la bitacora, pero nunca se propaga hacia el navegador.
            Bitacora::registrar('Conexion a la base de datos fallida: ' . $e->getMessage());

            throw new RuntimeException('No fue posible conectar con la base de datos.');
        }

        return self::$pdo;
    }

    /**
     * Solo para las pruebas: descarta la conexion viva.
     */
    public static function reiniciar(): void
    {
        self::$pdo = null;
    }
}
