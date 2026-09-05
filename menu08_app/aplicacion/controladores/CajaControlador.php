<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Modelos\Orden;
use Menu08\Modelos\Producto;
use Menu08\Modelos\TurnoCaja;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\DatosInvalidos;
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
    /** Quien entra a CAJA. Publica: plantillas/navegacion.php la lee para
        no repetir el mapa de permisos en la vista. */
    public const ROLES = ['food_truck', 'cajero'];

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

        $this->pantallaVenta($this->foodTruckActual(), $turno);
    }

    /**
     * Registra la venta. Los precios los pone el servidor: lo que venga del
     * navegador sobre importes se ignora.
     */
    public function vender(): void
    {
        $this->exigirRol(...self::ROLES);
        $this->verificarCsrf();

        $ft    = $this->foodTruckActual();
        $turno = TurnoCaja::vigente($ft);

        if ($turno === null) {
            Sesion::mensaje('No hay un turno abierto. Abra el turno para poder vender.', 'aviso');

            $this->redirigir('/caja/turno');
        }

        $lineas = [];

        foreach ((array) ($_POST['cantidad'] ?? []) as $id => $cantidad) {
            $cantidad = trim((string) $cantidad);

            if ($cantidad === '' || !preg_match('/^\d+$/', $cantidad)) {
                continue;
            }

            if ((int) $cantidad > 0) {
                $lineas[(int) $id] = (int) $cantidad;
            }
        }

        try {
            $orden = Orden::registrar(
                $ft,
                (int) $turno['id'],
                $lineas,
                (string) ($_POST['medio_pago'] ?? ''),
                trim((string) ($_POST['nota'] ?? '')) ?: null
            );
        } catch (DatosInvalidos $e) {
            $this->pantallaVenta($ft, $turno, $e->getMessage(), 422);

            return;
        }

        // Se descarta el token: reenviar el formulario con F5 no duplica la venta.
        Csrf::rotar();
        Sesion::mensaje(sprintf('Orden %s registrada.', $orden['numero']), 'exito');

        $this->redirigir('/caja/comprobante/' . $orden['id']);
    }

    /**
     * Comprobante imprimible de una orden ya registrada.
     */
    public function comprobante(string $id): void
    {
        $this->exigirRol(...self::ROLES);

        $ft    = $this->foodTruckActual();
        $orden = Orden::porId((int) $id, $ft);

        if ($orden === null) {
            throw new RutaNoEncontrada(sprintf('Orden %s inexistente para este food truck.', $id));
        }

        $this->vista('caja/comprobante', [
            'orden' => $orden,
            'items' => Orden::items((int) $orden['id']),
            'truck' => FoodTruck::porId($ft),
        ], 'Orden ' . $orden['numero']);
    }

    /**
     * @param array<string, mixed> $turno
     */
    private function pantallaVenta(int $foodTruckId, array $turno, ?string $error = null, int $codigo = 200): void
    {
        $this->vista('caja/inicio', [
            'usuario'   => $this->usuario(),
            'turno'     => $turno,
            'resumen'   => TurnoCaja::resumen((int) $turno['id']),
            'catalogo'  => Producto::catalogoPublico($foodTruckId),
            'ordenes'   => Orden::delTurno((int) $turno['id']),
            'error'     => $error,
        ], 'Caja', $codigo);
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
            // Ya habia un turno vigente: no se crea otro. El aviso va aparte de
            // los errores de campo, porque la vista pasa a mostrar la rama de
            // cierre y alli no existe el campo de la base inicial.
            $vigente = TurnoCaja::vigente($ft);

            $this->vista('caja/turno', [
                'turno'   => $vigente,
                'resumen' => $vigente === null ? null : TurnoCaja::resumen((int) $vigente['id']),
                'errores' => [],
                'aviso'   => 'Ya hay un turno abierto. Cierrelo antes de abrir otro.',
            ], 'Turno de caja', 409);

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
