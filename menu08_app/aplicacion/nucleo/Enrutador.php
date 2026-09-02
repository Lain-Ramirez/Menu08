<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use InvalidArgumentException;

/**
 * Enrutador propio. Registra rutas por metodo HTTP y patron, y extrae los
 * parametros nombrados de la direccion.
 *
 * Patrones admitidos:
 *   /carta/{slug}              cualquier segmento
 *   /svp/orden/{id:\d+}        segmento restringido por una expresion regular
 */
final class Enrutador
{
    /** @var array<string, list<array{patron: string, regex: string, accion: mixed}>> */
    private array $rutas = [];

    public function get(string $patron, callable|array $accion): void
    {
        $this->agregar('GET', $patron, $accion);
    }

    public function post(string $patron, callable|array $accion): void
    {
        $this->agregar('POST', $patron, $accion);
    }

    public function agregar(string $metodo, string $patron, callable|array $accion): void
    {
        $this->rutas[strtoupper($metodo)][] = [
            'patron' => $patron,
            'regex'  => $this->compilar($patron),
            'accion' => $accion,
        ];
    }

    /**
     * Busca la primera ruta que coincida y ejecuta su accion.
     * Si ninguna coincide lanza RutaNoEncontrada, que el manejador
     * de errores convierte en una respuesta 404.
     */
    public function despachar(string $metodo, string $ruta): void
    {
        $metodo = strtoupper($metodo);

        foreach ($this->rutas[$metodo] ?? [] as $registro) {
            if (preg_match($registro['regex'], $ruta, $coincidencias) === 1) {
                $parametros = array_filter(
                    $coincidencias,
                    static fn (int|string $clave): bool => is_string($clave),
                    ARRAY_FILTER_USE_KEY
                );

                $this->invocar($registro['accion'], $parametros);

                return;
            }
        }

        throw new RutaNoEncontrada(sprintf('No hay ruta registrada para %s %s', $metodo, $ruta));
    }

    /**
     * Convierte /carta/{slug} en una expresion regular con grupos nombrados.
     */
    private function compilar(string $patron): string
    {
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^{}]+))?\}#',
            static fn (array $c): string => '(?P<' . $c[1] . '>' . ($c[2] ?? '[^/]+') . ')',
            $patron
        );

        if ($regex === null) {
            throw new InvalidArgumentException(sprintf('Patron de ruta invalido: %s', $patron));
        }

        return '#^' . $regex . '$#u';
    }

    /**
     * Los parametros llegan con nombre, de modo que se pasan como argumentos
     * nombrados y el orden en que aparezcan en la direccion deja de importar.
     *
     * @param array<string, string> $parametros
     */
    private function invocar(callable|array $accion, array $parametros): void
    {
        if (is_array($accion) && !is_callable($accion)) {
            [$clase, $metodo] = $accion;
            $controlador = new $clase();
            $controlador->{$metodo}(...$parametros);

            return;
        }

        ($accion)(...$parametros);
    }
}
