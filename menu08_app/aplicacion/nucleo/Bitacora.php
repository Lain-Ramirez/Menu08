<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

/**
 * Registro de errores en almacenamiento/bitacora, un archivo por dia.
 *
 * Esta clase la invoca el manejador de errores, incluso cuando la configuracion
 * todavia no se ha podido cargar. Por eso nunca lanza excepciones ni depende de
 * que Configuracion este inicializada: si algo falla al escribir, se rinde en
 * silencio en lugar de provocar un segundo error dentro del manejador del primero.
 */
final class Bitacora
{
    public static function registrar(string $mensaje, string $nivel = 'ERROR'): void
    {
        $carpeta = self::carpeta();

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            return;
        }

        $linea = sprintf(
            '[%s] %s: %s%s',
            date('Y-m-d H:i:s'),
            $nivel,
            str_replace(["\r", "\n"], ' ', $mensaje),
            PHP_EOL
        );

        @file_put_contents($carpeta . '/' . date('Y-m-d') . '.log', $linea, FILE_APPEND | LOCK_EX);
    }

    private static function carpeta(): string
    {
        $configurada = Configuracion::obtener('bitacora.ruta');

        if (is_string($configurada) && $configurada !== '') {
            return rtrim($configurada, '/');
        }

        return dirname(__DIR__, 2) . '/almacenamiento/bitacora';
    }
}
