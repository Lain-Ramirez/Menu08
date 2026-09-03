<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Nucleo\Controlador;

/**
 * Sistema de Visualizacion de Produccion.
 * El contenido real llega con los issues de su fase.
 */
final class SvpControlador extends Controlador
{
    public function inicio(): void
    {
        $this->exigirRol('food_truck', 'produccion');

        $this->vista('svp/inicio', ['usuario' => $this->usuario()], 'Sistema de Visualizacion de Produccion');
    }
}
