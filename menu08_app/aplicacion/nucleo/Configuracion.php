<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use RuntimeException;

/**
 * Lee configuracion/configuracion.php una sola vez y expone sus valores por
 * clave separada con puntos, por ejemplo: Configuracion::obtener('base_datos.servidor').
 *
 * El archivo real esta fuera del control de versiones. La plantilla versionada
 * es configuracion/configuracion.ejemplo.php.
 */
final class Configuracion
{
    /** @var array<string, mixed>|null */
    private static ?array $valores = null;

    public static function cargar(string $archivo): void
    {
        if (!is_file($archivo)) {
            throw new RuntimeException(
                'Falta el archivo de configuracion. Copie configuracion/configuracion.ejemplo.php '
                . 'como configuracion/configuracion.php y complete los valores.'
            );
        }

        $valores = require $archivo;

        if (!is_array($valores)) {
            throw new RuntimeException('configuracion/configuracion.php debe devolver un arreglo.');
        }

        self::$valores = $valores;
    }

    public static function cargada(): bool
    {
        return self::$valores !== null;
    }

    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        if (self::$valores === null) {
            return $porDefecto;
        }

        $actual = self::$valores;

        foreach (explode('.', $clave) as $parte) {
            if (!is_array($actual) || !array_key_exists($parte, $actual)) {
                return $porDefecto;
            }
            $actual = $actual[$parte];
        }

        return $actual;
    }

    public static function esProduccion(): bool
    {
        return self::obtener('entorno', 'produccion') === 'produccion';
    }
}
