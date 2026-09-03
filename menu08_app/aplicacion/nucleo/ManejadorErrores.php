<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use ErrorException;
use Throwable;

/**
 * Convierte avisos, excepciones y errores fatales en una respuesta controlada.
 *
 * En cualquier entorno el detalle va a la bitacora. Al visitante solo se le
 * muestra la pagina de error: nunca la traza, la consulta ni los datos de conexion.
 * En entorno de desarrollo el detalle SI se muestra en pantalla, para poder trabajar.
 */
final class ManejadorErrores
{
    public static function registrar(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        // log_errors se deja como lo tenga el servidor: es la red de seguridad
        // para los fallos anteriores a que este manejador quede registrado.

        set_error_handler([self::class, 'manejarError']);
        set_exception_handler([self::class, 'manejarExcepcion']);
        register_shutdown_function([self::class, 'manejarApagado']);
    }

    /**
     * Eleva los avisos de PHP a excepciones, para que no pasen inadvertidos.
     */
    public static function manejarError(int $tipo, string $mensaje, string $archivo = '', int $linea = 0): bool
    {
        if ((error_reporting() & $tipo) === 0) {
            return false;
        }

        throw new ErrorException($mensaje, 0, $tipo, $archivo, $linea);
    }

    public static function manejarExcepcion(Throwable $e): void
    {
        $codigo = match (true) {
            $e instanceof RutaNoEncontrada => 404,
            $e instanceof AccesoDenegado   => 403,
            $e instanceof DatosInvalidos   => 422,
            default                        => 500,
        };

        Bitacora::registrar(
            sprintf('%s: %s en %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
            $codigo === 500 ? 'ERROR' : 'AVISO'
        );

        self::responder($codigo, $e);
    }

    /**
     * Los errores fatales no pasan por set_exception_handler.
     */
    public static function manejarApagado(): void
    {
        $ultimo = error_get_last();

        if ($ultimo === null) {
            return;
        }

        $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        if (!in_array($ultimo['type'], $fatales, true)) {
            return;
        }

        Bitacora::registrar(
            sprintf('Error fatal: %s en %s:%d', $ultimo['message'], $ultimo['file'], $ultimo['line'])
        );

        self::responder(500, null);
    }

    private static function responder(int $codigo, ?Throwable $e): void
    {
        if (headers_sent()) {
            return;
        }

        // Se descarta cualquier salida a medio escribir para que la pagina de
        // error no quede incrustada dentro de una vista rota.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($codigo);
        header('Content-Type: text/html; charset=utf-8');

        $detalle = null;

        if ($e !== null && !Configuracion::esProduccion()) {
            $detalle = sprintf("%s: %s\n\n%s:%d\n\n%s", $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        }

        try {
            echo Vista::pagina('plantillas/error', ['codigo' => $codigo, 'detalle' => $detalle], 'Error ' . $codigo);
        } catch (Throwable) {
            // Si la propia vista de error falla, se responde en texto plano.
            echo '<!doctype html><meta charset="utf-8"><title>Error ' . $codigo . '</title>'
                . '<h1>Error ' . $codigo . '</h1><p>Ocurrio un problema al procesar la solicitud.</p>';
        }
    }
}
