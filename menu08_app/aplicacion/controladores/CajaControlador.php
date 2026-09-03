<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\TurnoCaja;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\RutaNoEncontrada;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Validador;

/**
 * Modulo CAJA: apertura y cierre del turno.
 *
 * El turno es lo que agrupa las ventas de una jornada. Sin turno abierto no se
 * puede vender, porque la orden no tendria a que pertenecer ni entraria en
 * ningun cuadre.
 */
final class CajaControlador extends Controlador
{
    private const ROLES = ['food_truck', 'cajero'];

    /**
     * Pantalla de venta. La construccion real de la orden llega con su issue;
     * aqui se resuelve la puerta: sin turno abierto no se entra.
     */
    public function inicio(): void
    {
        $this->exigirRol(...self::ROLES);

        $turno = TurnoCaja::vigente($this->foodTruckActual());

        if ($turno === null) {
            Sesion::mensaje('No hay un turno abierto. Abra el turno para poder vender.', 'aviso');

            $this->redirigir('/caja/turno');
        }

        $this->vista('caja/inicio', [
            'usuario' => $this->usuario(),
            'turno'   => $turno,
            'resumen' => TurnoCaja::resumen((int) $turno['id']),
        ], 'Caja');
    }

    /**
     * Una sola pantalla para las dos caras del turno: si no hay turno abierto
     * muestra el formulario de apertura; si lo hay, el resumen y el cierre.
     */
    public function turno(): void
    {
        $this->exigirRol(...self::ROLES);

        $ft    = $this->foodTruckActual();
        $turno = TurnoCaja::vigente($ft);

        $this->vista('caja/turno', [
            'turno'   => $turno,
            'resumen' => $turno === null ? null : TurnoCaja::resumen((int) $turno['id']),
            'errores' => [],
        ], $turno === null ? 'Abrir turno' : 'Cerrar turno');
    }

    public function abrir(): void
    {
        $this->exigirRol(...self::ROLES);
        $this->verificarCsrf();

        $ft = $this->foodTruckActual();

        $v    = new Validador();
        $base = $v->precio('base_inicial', $_POST['base_inicial'] ?? '');

        if (!$v->correcto()) {
            $this->vista('caja/turno', [
                'turno'   => null,
                'resumen' => null,
                'errores' => $v->errores(),
            ], 'Abrir turno', 422);

            return;
        }

        $usuario = $this->usuario();
        $id      = TurnoCaja::abrir($ft, (int) $usuario['id'], (string) $base);

        if ($id === 0) {
            // Ya habia un turno vigente: no se crea otro y se avisa.
            $this->vista('caja/turno', [
                'turno'   => TurnoCaja::vigente($ft),
                'resumen' => null,
                'errores' => ['base_inicial' => 'Ya hay un turno abierto. Cierrelo antes de abrir otro.'],
            ], 'Abrir turno', 409);

            return;
        }

        Csrf::rotar();
        Sesion::mensaje('Turno abierto.', 'exito');

        $this->redirigir('/caja');
    }

    public function cerrar(): void
    {
        $this->exigirRol(...self::ROLES);
        $this->verificarCsrf();

        $ft    = $this->foodTruckActual();
        $turno = TurnoCaja::vigente($ft);

        if ($turno === null) {
            Sesion::mensaje('No hay ningun turno abierto que cerrar.', 'aviso');

            $this->redirigir('/caja/turno');
        }

        $v         = new Validador();
        $declarado = $v->precio('total_declarado', $_POST['total_declarado'] ?? '');

        if (!$v->correcto()) {
            $this->vista('caja/turno', [
                'turno'   => $turno,
                'resumen' => TurnoCaja::resumen((int) $turno['id']),
                'errores' => $v->errores(),
            ], 'Cerrar turno', 422);

            return;
        }

        if (!TurnoCaja::cerrar((int) $turno['id'], $ft, (string) $declarado)) {
            Sesion::mensaje('El turno ya no estaba abierto.', 'aviso');

            $this->redirigir('/caja/turno');
        }

        Csrf::rotar();
        Sesion::mensaje('Turno cerrado.', 'exito');

        $this->redirigir('/caja/turnos/' . $turno['id']);
    }

    public function historial(): void
    {
        $this->exigirRol(...self::ROLES);

        $this->vista('caja/turnos', [
            'turnos' => TurnoCaja::historial($this->foodTruckActual()),
        ], 'Turnos de caja');
    }

    /**
     * Resumen de un turno ya cerrado, consultable despues.
     */
    public function detalle(string $id): void
    {
        $this->exigirRol(...self::ROLES);

        $ft    = $this->foodTruckActual();
        $turno = TurnoCaja::porId((int) $id, $ft);

        if ($turno === null) {
            throw new RutaNoEncontrada(sprintf('Turno %s inexistente para este food truck.', $id));
        }

        $this->vista('caja/turno_detalle', [
            'turno'   => $turno,
            'resumen' => TurnoCaja::resumen((int) $turno['id']),
        ], 'Turno ' . $turno['id']);
    }
}
