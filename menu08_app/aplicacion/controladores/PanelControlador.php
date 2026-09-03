<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Nucleo\Controlador;

/**
 * Panel de administracion del modulo CARTA.
 * El contenido real llega con los issues de la fase de backend y de frontend.
 */
final class PanelControlador extends Controlador
{
    public function inicio(): void
    {
        $this->exigirRol('plataforma', 'food_truck');

        $this->vista('panel/inicio', ['usuario' => $this->usuario()], 'Panel');
    }
}
