<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Modelos\Producto;
use Menu08\Modelos\Ubicacion;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\GestorImagenes;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Validador;

/**
 * Panel del modulo CARTA: tablero y datos del food truck.
 */
final class PanelControlador extends Controlador
{
    public function inicio(): void
    {
        $this->exigirRol('plataforma', 'food_truck');

        $id = Sesion::foodTruckId();

        $this->vista('panel/inicio', [
            'usuario'   => $this->usuario(),
            'truck'     => $id === null ? null : FoodTruck::porId($id),
            'resumen'   => $id === null ? null : Producto::resumen($id),
            'paradas'   => $id === null ? null : Ubicacion::resumen($id),
        ], 'Panel');
    }

    public function formulario(): void
    {
        $this->exigirRol('food_truck');

        $id = $this->foodTruckActual();

        $this->vista('panel/food_truck', [
            'truck'   => FoodTruck::porId($id),
            'errores' => [],
        ], 'Datos del food truck');
    }

    public function guardar(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $id    = $this->foodTruckActual();
        $truck = FoodTruck::porId($id);

        $v = new Validador();
        $v->texto('nombre', $_POST['nombre'] ?? '', 120, true, 'El nombre');
        $v->texto('descripcion', $_POST['descripcion'] ?? '', 500, false, 'La descripcion');
        $v->texto('telefono', $_POST['telefono'] ?? '', 40, false, 'El telefono');
        $v->texto('whatsapp', $_POST['whatsapp'] ?? '', 40, false, 'El whatsapp');
        $v->texto('instagram', $_POST['instagram'] ?? '', 80, false, 'El instagram');
        $v->texto('ciudad', $_POST['ciudad'] ?? '', 80, false, 'La ciudad');

        $slugPedido = trim((string) ($_POST['slug'] ?? ''));
        $slug       = FoodTruck::slugificar($slugPedido !== '' ? $slugPedido : (string) ($_POST['nombre'] ?? ''));

        if ($slug === '') {
            $v->error('slug', 'El slug no puede quedar vacio.');
        } elseif (FoodTruck::slugRepetido($slug, $id)) {
            $v->error('slug', 'Ese slug ya lo usa otro food truck.');
        }

        $logo = GestorImagenes::guardar($_FILES['logo'] ?? null, $v, 'logo');

        if (!$v->correcto()) {
            GestorImagenes::borrar($logo);

            $this->vista('panel/food_truck', [
                'truck'   => array_merge((array) $truck, $_POST, ['slug' => $slug]),
                'errores' => $v->errores(),
            ], 'Datos del food truck', 422);

            return;
        }

        if ($logo !== null && $truck !== null) {
            GestorImagenes::borrar((string) $truck['logo']);
        }

        FoodTruck::actualizar($id, [
            'nombre'      => $v->valor('nombre'),
            'slug'        => $slug,
            'descripcion' => $v->valor('descripcion'),
            'telefono'    => $v->valor('telefono'),
            'whatsapp'    => $v->valor('whatsapp'),
            'instagram'   => $v->valor('instagram'),
            'ciudad'      => $v->valor('ciudad'),
            'logo'        => $logo,
        ]);

        Csrf::rotar();
        Sesion::mensaje('Datos del food truck actualizados.', 'exito');

        $this->redirigir('/panel');
    }
}
