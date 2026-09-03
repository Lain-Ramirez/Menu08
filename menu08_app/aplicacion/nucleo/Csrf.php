<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use Random\RandomException;

/**
 * Token contra falsificacion de peticiones en sitios cruzados.
 *
 * Cada sesion tiene un token con fecha de vencimiento. Todo formulario que
 * modifique datos debe incluirlo, y el servidor lo compara con hash_equals,
 * que tarda lo mismo acierte o falle y por tanto no filtra informacion.
 */
final class Csrf
{
    private const CLAVE      = 'csrf';
    private const VIDA_MINUTOS = 120;

    /**
     * @throws RandomException
     */
    public static function token(): string
    {
        $actual = $_SESSION[self::CLAVE] ?? null;

        if (is_array($actual) && $actual['vence'] > time()) {
            return (string) $actual['valor'];
        }

        $valor = bin2hex(random_bytes(32));

        $_SESSION[self::CLAVE] = [
            'valor' => $valor,
            'vence' => time() + self::VIDA_MINUTOS * 60,
        ];

        return $valor;
    }

    /**
     * Campo oculto listo para insertar dentro de un <form>.
     *
     * @throws RandomException
     */
    public static function campo(): string
    {
        return sprintf('<input type="hidden" name="_token" value="%s">', Vista::e(self::token()));
    }

    /**
     * Un token ausente, vencido o alterado se rechaza por igual.
     */
    public static function valido(?string $recibido): bool
    {
        $guardado = $_SESSION[self::CLAVE] ?? null;

        if (!is_array($guardado) || $recibido === null || $recibido === '') {
            return false;
        }

        if ($guardado['vence'] <= time()) {
            unset($_SESSION[self::CLAVE]);

            return false;
        }

        return hash_equals((string) $guardado['valor'], $recibido);
    }

    /**
     * Se descarta el token tras una operacion sensible, para que un envio
     * repetido del mismo formulario no vuelva a ejecutarse.
     */
    public static function rotar(): void
    {
        unset($_SESSION[self::CLAVE]);
    }
}
