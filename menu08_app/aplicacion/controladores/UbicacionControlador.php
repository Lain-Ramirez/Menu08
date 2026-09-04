<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use DateTimeImmutable;
use Menu08\Modelos\Ubicacion;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\RutaNoEncontrada;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Validador;

/**
 * Agenda de paradas del food truck: alta, listado, edicion y baja logica.
 *
 * El food_truck_id sale siempre de la sesion. Una parada de otro food truck no
 * existe para esta sesion: se responde 404, nunca 403, porque un 403
 * confirmaria que ese identificador existe.
 */
final class UbicacionControlador extends Controlador
{
    /**
     * Formatos que se aceptan en ?momento=. El primero es el que manda
     * <input type="datetime-local">; los demas, para escribirlo a mano o con
     * curl. La admiracion inicial deja a cero lo que el formato no indica.
     */
    private const FORMATOS_MOMENTO = [
        '!Y-m-d\TH:i',
        '!Y-m-d H:i',
        '!Y-m-d\TH:i:s',
        '!Y-m-d H:i:s',
    ];

    public function listado(): void
    {
        $this->exigirRol('food_truck');

        $ft = $this->foodTruckActual();

        $this->pantalla($ft, null, [], $this->momentoPedido());
    }

    public function editar(string $id): void
    {
        $this->exigirRol('food_truck');

        $ft     = $this->foodTruckActual();
        $parada = Ubicacion::porId((int) $id, $ft);

        // Un identificador de otro food truck no existe para esta sesion.
        if ($parada === null) {
            throw new RutaNoEncontrada(sprintf('Parada %s inexistente para este food truck.', $id));
        }

        $this->pantalla($ft, $parada, []);
    }

    public function guardar(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $ft = $this->foodTruckActual();
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && Ubicacion::porId($id, $ft) === null) {
            throw new RutaNoEncontrada('Parada inexistente para este food truck.');
        }

        $v = new Validador();
        $v->texto('nombre', $_POST['nombre'] ?? '', 120, true, 'El punto');
        $v->texto('referencia', $_POST['referencia'] ?? '', 200, false, 'La referencia');
        $v->diaSemana('dia_semana', $_POST['dia_semana'] ?? '', 'El dia');
        $v->hora('hora_inicio', $_POST['hora_inicio'] ?? '', 'La hora de inicio');
        $v->hora('hora_fin', $_POST['hora_fin'] ?? '', 'La hora de fin');
        $v->coordenada('latitud', $_POST['latitud'] ?? '', -90.0, 90.0, 'La latitud');
        $v->coordenada('longitud', $_POST['longitud'] ?? '', -180.0, 180.0, 'La longitud');

        // No se comprueba que hora_fin sea posterior a hora_inicio, y no es un
        // descuido: una parada de 18:00 a 01:00 es el caso normal de un truck
        // nocturno. Rechazarla seria el error clasico de este formulario.

        if (!$v->correcto()) {
            // Se rearma a mano, no con array_merge($_POST, ...), para que _token
            // no acabe impreso en un atributo value.
            $this->pantalla($ft, [
                'id'          => $id,
                'nombre'      => $_POST['nombre'] ?? '',
                'referencia'  => $_POST['referencia'] ?? '',
                'latitud'     => $_POST['latitud'] ?? '',
                'longitud'    => $_POST['longitud'] ?? '',
                'dia_semana'  => $_POST['dia_semana'] ?? '',
                'hora_inicio' => $_POST['hora_inicio'] ?? '',
                'hora_fin'    => $_POST['hora_fin'] ?? '',
            ], $v->errores(), null, 422);

            return;
        }

        // Todo lo que llega al modelo sale del validador, nunca de $_POST.
        $datos = [
            'nombre'      => $v->valor('nombre'),
            'referencia'  => $v->valor('referencia'),
            'latitud'     => $v->valor('latitud'),
            'longitud'    => $v->valor('longitud'),
            'dia_semana'  => $v->valor('dia_semana'),
            'hora_inicio' => $v->valor('hora_inicio'),
            'hora_fin'    => $v->valor('hora_fin'),
        ];

        if ($id > 0) {
            Ubicacion::actualizar($id, $ft, $datos);
            Sesion::mensaje('Parada actualizada.', 'exito');
        } else {
            Ubicacion::crear($ft, $datos);
            Sesion::mensaje('Parada creada.', 'exito');
        }

        Csrf::rotar();

        $this->redirigir('/panel/ubicaciones');
    }

    public function cambiarEstado(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $ft     = $this->foodTruckActual();
        $id     = (int) ($_POST['id'] ?? 0);
        $parada = Ubicacion::porId($id, $ft);

        if ($parada === null) {
            throw new RutaNoEncontrada('Parada inexistente para este food truck.');
        }

        $activa = (int) $parada['activa'] === 1;
        Ubicacion::cambiarEstado($id, $ft, !$activa);

        Csrf::rotar();
        Sesion::mensaje($activa ? 'Parada desactivada.' : 'Parada activada.', 'exito');

        $this->redirigir('/panel/ubicaciones');
    }

    /**
     * La pantalla de paradas, que es la misma para listar, editar y repintar un
     * formulario con errores.
     *
     * @param array<string, mixed>|null $edita
     * @param array<string, string>     $errores
     */
    private function pantalla(int $ft, ?array $edita, array $errores, ?string $momento = null, int $codigo = 200): void
    {
        $this->vista('panel/ubicaciones', [
            'ubicaciones' => Ubicacion::delFoodTruck($ft),
            'edita'       => $edita,
            'errores'     => $errores,
            'dias'        => Ubicacion::DIAS,
            'vigente'     => Ubicacion::vigente($ft, $momento),
            'momento'     => $momento,
        ], $edita === null ? 'Paradas' : 'Editar parada', $codigo);
    }

    /**
     * Instante con el que se pregunta cual es la parada vigente.
     *
     * Sin parametro manda el reloj del servidor. Con el se puede mirar otra hora,
     * que es como se comprueba la jornada que cruza la medianoche sin esperar a
     * las 00:30 ni tocar el reloj del hosting. Es de solo lectura y la consulta
     * sigue filtrando por el food truck de la sesion, asi que no abre nada.
     */
    private function momentoPedido(): ?string
    {
        $pedido = trim((string) ($_GET['momento'] ?? ''));

        if ($pedido === '') {
            return null;
        }

        foreach (self::FORMATOS_MOMENTO as $formato) {
            $momento = DateTimeImmutable::createFromFormat($formato, $pedido);

            if ($momento !== false) {
                return $momento->format('Y-m-d H:i:s');
            }
        }

        Sesion::mensaje(
            'El momento consultado no tiene un formato valido; se muestra la parada de ahora mismo.',
            'aviso'
        );

        return null;
    }
}
