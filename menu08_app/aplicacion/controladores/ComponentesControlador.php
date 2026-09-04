<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Orden;
use Menu08\Nucleo\Controlador;

/**
 * Muestrario del catalogo de componentes.
 *
 * Publico y sin sesion a proposito: no consulta la base ni muestra un solo dato
 * del negocio, y hay que poder abrirlo a 360, 768 y 1280 px sin entrar primero.
 *
 * Los estados de la orden no se escriben en la vista: salen de
 * Orden::TRANSICIONES, que es donde vive el ciclo de vida. Asi el catalogo no
 * puede quedarse describiendo estados que el modelo ya no tiene.
 */
final class ComponentesControlador extends Controlador
{
    /** Como se pinta cada codigo del ciclo. La clase es la de componentes.css. */
    private const PRESENTACION = [
        'pendiente'      => ['clase' => 'pendiente',   'rotulo' => 'Pendiente'],
        'en_preparacion' => ['clase' => 'preparacion', 'rotulo' => 'En preparacion'],
        'lista'          => ['clase' => 'lista',       'rotulo' => 'Lista'],
        'entregada'      => ['clase' => 'entregada',   'rotulo' => 'Entregada'],
    ];

    public function muestrario(): void
    {
        $this->vista(
            'plantillas/componentes',
            ['estados' => $this->estados()],
            'Catalogo de componentes'
        );
    }

    /**
     * Recorre el ciclo de la orden de principio a fin.
     *
     * TRANSICIONES es un mapa de estado actual a estado siguiente, asi que las
     * claves dan todos los estados menos el ultimo, y el ultimo es el destino
     * de la ultima transicion.
     *
     * @return list<array<string, string>>
     */
    private function estados(): array
    {
        $transiciones = Orden::TRANSICIONES;
        $codigos      = array_keys($transiciones);
        $codigos[]    = (string) end($transiciones);

        $estados = [];

        foreach ($codigos as $codigo) {
            // Un estado sin presentacion definida se muestra con su codigo
            // crudo y el estilo neutro, en vez de desaparecer del catalogo.
            $estados[] = self::PRESENTACION[$codigo]
                ?? ['clase' => 'pendiente', 'rotulo' => $codigo];
        }

        return $estados;
    }
}
