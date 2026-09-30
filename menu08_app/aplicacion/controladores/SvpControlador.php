<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Modelos\Orden;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\DatosInvalidos;
use Menu08\Nucleo\ManejadorErrores;
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

    /**
     * El tablero.
     *
     * El primer pintado lo hace el servidor con las mismas ordenes que devuelve
     * el sondeo, para que la pantalla de la pared no pase por un hueco en blanco
     * mientras llega la primera respuesta —ni se quede en blanco para siempre si
     * nunca llega—. Desde ahi manda svp.js.
     */
    public function inicio(): void
    {
        $this->exigirRol(...self::ROLES);

        $datos = Orden::enCurso($this->foodTruckActual(), self::MINUTOS_DEMORA);

        $this->vista('svp/tablero', [
            'turno'   => $datos['turno'],
            'ordenes' => $datos['ordenes'],
            'ahora'   => $datos['ahora'],
            'demora'  => self::MINUTOS_DEMORA,
        ], 'Sistema de Visualizacion de Produccion', 200, ['svp.css'], ['svp.js']);
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
            // El reloj del servidor viaja en cada respuesta: es contra el que el
            // tablero descuenta el desfase de su propio reloj antes de contar.
            'ahora'           => $datos['ahora'],
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

    /**
     * Pantalla publica de turnos para la ventanilla del food truck.
     *
     * Es la vista que el cliente mira desde la fila para saber si su pedido ya esta
     * listo. No exige sesion. Muestra unicamente dos columnas: «En preparacion» y «Listos»,
     * con tipografia de gran tamano para leerse a tres metros de distancia.
     */
    public function turnos(string $slug): void
    {
        $truck = FoodTruck::porSlug($slug);

        if ($truck === null) {
            throw new RutaNoEncontrada(sprintf('No hay un food truck activo con el slug "%s".', $slug));
        }

        $datos = Orden::turnosPublicos((int) $truck['id']);

        $this->vistaPublica(
            'svp/turnos',
            [
                'foodTruck' => $truck,
                'turno'     => $datos['turno'],
                'ordenes'   => $datos['ordenes'],
                'ahora'     => $datos['ahora'],
            ],
            sprintf('Turnos · %s', (string) $truck['nombre']),
            ['turnos.css'],
            200,
            ['turnos.js']
        );
    }

    /**
     * Servicio JSON publico de turnos para el sondeo de la pantalla de ventanilla.
     *
     * Hermano de /svp/ordenes, pero sin sesion y devolviendo unicamente numero de orden
     * y estado. No expone productos, cantidades, totales, medios de pago ni notas.
     */
    public function turnosOrdenes(string $slug): void
    {
        ManejadorErrores::responderEnJson();

        $truck = FoodTruck::porSlug($slug);

        if ($truck === null) {
            $this->jsonError('food_truck_no_encontrado', sprintf('No hay un food truck activo con el slug "%s".', $slug), 404);
        }

        $datos = Orden::turnosPublicos((int) $truck['id']);

        $this->json([
            'turno'   => $datos['turno'],
            'ahora'   => $datos['ahora'],
            'total'   => $datos['total'],
            'ordenes' => $datos['ordenes'],
        ]);
    }
}
