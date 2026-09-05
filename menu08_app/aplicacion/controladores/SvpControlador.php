<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Orden;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\DatosInvalidos;
use Menu08\Nucleo\RutaNoEncontrada;

/**
 * Sistema de Visualizacion de Produccion.
 *
 * El tablero es una pantalla dentro del truck que se refresca sola por sondeo,
 * asi que el servicio de consulta tiene que ser barato: dos sentencias
 * preparadas por respuesta y nada de HTML en los errores.
 */
final class SvpControlador extends Controlador
{
    /** Quien entra al SVP. Publica: la lee plantillas/navegacion.php. */
    public const ROLES = ['food_truck', 'produccion'];

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

    /**
     * Avanza una orden al siguiente estado de su ciclo de vida.
     *
     * La regla de que transicion vale no vive aqui: la guarda el modelo, en
     * Orden::TRANSICIONES. Este metodo solo traduce sus dos negativas al
     * codigo HTTP que les corresponde —404 si la orden no es de este truck,
     * 422 si el movimiento no esta permitido— y se asegura de que ninguna de
     * las dos salga en HTML.
     */
    public function estado(string $id): void
    {
        $this->exigirRolApi(...self::ROLES);
        $this->verificarCsrfApi();

        $destino = trim((string) ($_POST['estado'] ?? ''));

        try {
            $orden = Orden::avanzar((int) $id, $this->foodTruckActual(), $destino);
        } catch (RutaNoEncontrada $e) {
            $this->jsonError('orden_no_encontrada', $e->getMessage(), 404);
        } catch (DatosInvalidos $e) {
            $this->jsonError('transicion_invalida', $e->getMessage(), 422);
        }

        $this->json(['orden' => $orden]);
    }
}
