<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use JsonException;

/**
 * Base de todos los controladores: respuestas HTML, JSON y redirecciones.
 */
abstract class Controlador
{
    /**
     * @param array<string, mixed> $datos
     */
    protected function vista(string $plantilla, array $datos = [], string $titulo = 'Menu08', int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: text/html; charset=utf-8');

        echo Vista::pagina($plantilla, $datos, $titulo);
    }

    /**
     * Respuesta JSON. La consume el Sistema de Visualizacion de Produccion.
     *
     * @param array<array-key, mixed> $datos
     *
     * @throws JsonException
     */
    protected function json(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function redirigir(string $ruta): never
    {
        header('Location: ' . Vista::url($ruta), true, 302);

        exit;
    }
}
