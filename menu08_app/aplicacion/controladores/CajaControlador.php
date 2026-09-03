<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Nucleo\Controlador;

/**
 * Modulo CAJA. El contenido real llega con los issues de su fase.
 */
final class CajaControlador extends Controlador
{
    public function inicio(): void
    {
        $this->exigirRol('food_truck', 'cajero');

        $this->vista('caja/inicio', ['usuario' => $this->usuario()], 'Caja');
    }
}
