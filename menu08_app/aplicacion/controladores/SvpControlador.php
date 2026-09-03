<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Orden;
use Menu08\Nucleo\Controlador;

/**
 * Sistema de Visualizacion de Produccion.
 *
 * El tablero es una pantalla dentro del truck que se refresca sola por sondeo,
 * asi que el servicio de consulta tiene que ser barato: dos sentencias
 * preparadas por respuesta y nada de HTML en los errores.
 */
final class SvpControlador extends Controlador
{
    private const ROLES = ['food_truck', 'produccion'];

    /** Minutos a partir de los cuales una orden se marca como demorada. */
    private const MINUTOS_DEMORA = 10;

    public function inicio(): void
    {
        $this->exigirRol(...self::ROLES);

        $this->vista('svp/inicio', ['usuario' => $this->usuario()], 'Sistema de Visualizacion de Produccion');
    }

    /**
     * Ordenes en curso del turno vigente, en JSON, para el sondeo del tablero.
     */
    public function ordenes(): void
    {
        $this->exigirRolApi(...self::ROLES);

        $datos = Orden::enCurso($this->foodTruckActual(), self::MINUTOS_DEMORA);

        $this->json([
            'turno'           => $datos['turno'],
            'minutos_demora'  => self::MINUTOS_DEMORA,
            'total'           => count($datos['ordenes']),
            'ordenes'         => $datos['ordenes'],
        ]);
    }
}
