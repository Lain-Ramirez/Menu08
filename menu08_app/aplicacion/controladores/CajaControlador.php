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
     * Pantalla de venta: catalogo, orden en construccion y cobro.
     *
     * La puerta se resuelve aqui: sin turno abierto no se entra, porque la
     * orden no tendria a que pertenecer ni entraria en ningun cuadre.
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

        try {
            $orden = Orden::registrar(
                $ft,
                (int) $turno['id'],
                self::cantidadesEnviadas(),
                self::texto('medio_pago'),
                self::texto('nota') ?: null
            );
        } catch (DatosInvalidos $e) {
            // El turno se vuelve a leer en vez de reutilizar el de arriba: entre
            // que se cargo la pantalla y llego este envio pudieron cerrarlo
            // desde otra sesion, y la pantalla tiene que decir la verdad sobre
            // el estado del turno en lugar de repetir el que ya caduco.
            $this->pantallaVenta($ft, TurnoCaja::vigente($ft), $e->getMessage(), 422);

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

        // La hoja y el guion del comprobante solo viajan aqui: el formato de 80 mm
        // y el dialogo de impresion no le sirven a ninguna otra pantalla.
        $this->vista('caja/comprobante', [
            'orden' => $orden,
            'items' => Orden::items((int) $orden['id']),
            'truck' => FoodTruck::porId($ft),
        ], 'Orden ' . $orden['numero'], 200, ['comprobante.css'], ['comprobante.js']);
    }

    /**
     * Pinta la pantalla de venta.
     *
     * El turno llega como puede venir y no como deberia: null es un estado real
     * —lo devuelve TurnoCaja::vigente() cuando lo cerraron mientras la pantalla
     * estaba abierta— y la vista lo dibuja deshabilitado en lugar de romperse.
     *
     * @param array<string, mixed>|null $turno
     */
    private function pantallaVenta(int $foodTruckId, ?array $turno, ?string $error = null, int $codigo = 200): void
    {
        $this->vista('caja/venta', [
            'turno'     => $turno,
            'resumen'   => $turno === null ? null : TurnoCaja::resumen((int) $turno['id']),
            'catalogo'  => Producto::catalogoPublico($foodTruckId),
            'ordenes'   => $turno === null ? [] : Orden::delTurno((int) $turno['id']),
            'error'     => $error,
            // Tras un rechazo, lo que el cajero ya habia armado vuelve a la
            // pantalla: caja.js lee estas cantidades de los campos y recompone
            // la orden sola. En un GET no hay nada que devolver.
            'seleccion' => self::cantidadesEnviadas(),
            'medioPago' => self::texto('medio_pago'),
            'nota'      => self::texto('nota'),
        ], 'Caja', $codigo, ['caja.css'], ['caja.js']);
    }

    /**
     * Cantidades del formulario, ya saneadas: producto_id => cantidad.
     *
     * Lo usan el registro de la venta y el repintado tras un rechazo, para que
     * los dos entiendan por orden exactamente lo mismo.
     *
     * Lo que no sea un entero positivo se descarta en silencio, la clave
     * incluida: el navegador envia un campo por producto del catalogo y casi
     * todos valen cero. El tope de 99 por producto lo hace cumplir
     * Orden::registrar, que es donde estan los precios.
     *
     * @return array<int, int>
     */
    private static function cantidadesEnviadas(): array
    {
        $lineas = [];

        foreach ((array) ($_POST['cantidad'] ?? []) as $id => $cantidad) {
            // Un valor anidado —cantidad[7][] — llegaria como arreglo, y
            // convertirlo a texto avisa por la bitacora y devuelve "Array".
            if (!is_scalar($cantidad) || !preg_match('/^\d+$/', (string) $id)) {
                continue;
            }

            $cantidad = trim((string) $cantidad);

            if ($cantidad === '' || !preg_match('/^\d+$/', $cantidad)) {
                continue;
            }

            if ((int) $cantidad > 0) {
                $lineas[(int) $id] = (int) $cantidad;
            }
        }

        return $lineas;
    }

    /**
     * Valor de texto del formulario, sin espacios sobrantes.
     *
     * Cualquier cosa que no sea una cadena —un arreglo enviado a proposito para
     * provocar un aviso— se trata como ausente.
     */
    private static function texto(string $clave): string
    {
        $valor = $_POST[$clave] ?? '';

        return is_string($valor) ? trim($valor) : '';
    }

    /**
     * Una sola pantalla para las dos caras del turno: si no hay turno abierto
     * muestra el formulario de apertura; si lo hay, el resumen y el cierre.
     */
    public function turno(): void
    {
        $this->exigirRol(...self::ROLES);

        $this->pantallaTurno(TurnoCaja::vigente($this->foodTruckActual()));
    }

    /**
     * Pinta la pantalla del turno, la misma para las dos caras.
     *
     * Las cuatro salidas que llevan a esta pantalla —entrar, abrir mal, abrir
     * con uno ya abierto y cerrar mal— tienen que enlazar la misma hoja y el
     * mismo guion y devolver los mismos datos a la vista. Repetirlo cuatro veces
     * era la forma segura de que algun dia solo tres siguieran igual.
     *
     * El turno llega como puede venir y no como deberia: null es un estado real
     * y la vista lo dibuja como la apertura, que es lo que toca cuando no hay
     * turno abierto.
     *
     * @param array<string, mixed>|null $turno
     * @param array<string, string>     $errores
     */
    private function pantallaTurno(
        ?array $turno,
        array $errores = [],
        ?string $aviso = null,
        int $codigo = 200
    ): void {
        $usuario = $this->usuario();

        $this->vista('caja/turno', [
            'turno'   => $turno,
            'resumen' => $turno === null ? null : TurnoCaja::resumen((int) $turno['id']),
            'errores' => $errores,
            'aviso'   => $aviso,
            'cajero'  => (string) ($usuario['nombre'] ?? ''),
            // Lo que el cajero ya habia marcado vuelve a la pantalla tras un
            // rechazo, para que no tenga que contar el cajon otra vez. En un GET
            // no hay nada que devolver.
            'base'      => self::texto('base_inicial'),
            'declarado' => self::texto('total_declarado'),
        ], $turno === null ? 'Abrir turno' : 'Cerrar turno', $codigo, ['turno.css'], ['turno.js']);
    }

    public function abrir(): void
    {
        $this->exigirRol(...self::ROLES);
        $this->verificarCsrf();

        $ft = $this->foodTruckActual();

        $v    = new Validador();
        $base = $v->precio('base_inicial', $_POST['base_inicial'] ?? '');

        if (!$v->correcto()) {
            $this->pantallaTurno(null, $v->errores(), null, 422);

            return;
        }

        $usuario = $this->usuario();
        $id      = TurnoCaja::abrir($ft, (int) $usuario['id'], (string) $base);

        if ($id === 0) {
            // Ya habia un turno vigente: no se crea otro. El aviso va aparte de
            // los errores de campo, porque la vista pasa a mostrar la rama de
            // cierre y alli no existe el campo de la base inicial.
            $this->pantallaTurno(
                TurnoCaja::vigente($ft),
                [],
                'Ya hay un turno abierto. Cierrelo antes de abrir otro.',
                409
            );

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
            $this->pantallaTurno($turno, $v->errores(), null, 422);

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
