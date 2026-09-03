<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Categoria;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\RutaNoEncontrada;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Validador;

/**
 * Categorias de la carta. Alta, listado, edicion y baja logica.
 */
final class CategoriaControlador extends Controlador
{
    public function listado(): void
    {
        $this->exigirRol('food_truck');

        $ft = $this->foodTruckActual();

        $this->vista('panel/categorias', [
            'categorias' => Categoria::delFoodTruck($ft),
            'edita'      => null,
            'errores'    => [],
        ], 'Categorias');
    }

    public function editar(string $id): void
    {
        $this->exigirRol('food_truck');

        $ft        = $this->foodTruckActual();
        $categoria = Categoria::porId((int) $id, $ft);

        // Un identificador de otro food truck no existe para esta sesion.
        if ($categoria === null) {
            throw new RutaNoEncontrada(sprintf('Categoria %s inexistente para este food truck.', $id));
        }

        $this->vista('panel/categorias', [
            'categorias' => Categoria::delFoodTruck($ft),
            'edita'      => $categoria,
            'errores'    => [],
        ], 'Editar categoria');
    }

    public function guardar(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $ft = $this->foodTruckActual();
        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && Categoria::porId($id, $ft) === null) {
            throw new RutaNoEncontrada('Categoria inexistente para este food truck.');
        }

        $v      = new Validador();
        $nombre = $v->texto('nombre', $_POST['nombre'] ?? '', 90, true, 'El nombre');
        $orden  = $v->entero('orden', $_POST['orden'] ?? '0', 0, 999, 'El orden');

        if ($nombre !== null && Categoria::nombreRepetido($ft, $nombre, $id)) {
            $v->error('nombre', 'Ya existe una categoria con ese nombre.');
        }

        if (!$v->correcto()) {
            $this->vista('panel/categorias', [
                'categorias' => Categoria::delFoodTruck($ft),
                'edita'      => ['id' => $id, 'nombre' => $_POST['nombre'] ?? '', 'orden' => $_POST['orden'] ?? 0],
                'errores'    => $v->errores(),
            ], 'Categorias', 422);

            return;
        }

        if ($id > 0) {
            Categoria::actualizar($id, $ft, (string) $nombre, (int) $orden);
            Sesion::mensaje('Categoria actualizada.', 'exito');
        } else {
            Categoria::crear($ft, (string) $nombre, (int) $orden);
            Sesion::mensaje('Categoria creada.', 'exito');
        }

        Csrf::rotar();

        $this->redirigir('/panel/categorias');
    }

    public function cambiarEstado(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $ft        = $this->foodTruckActual();
        $id        = (int) ($_POST['id'] ?? 0);
        $categoria = Categoria::porId($id, $ft);

        if ($categoria === null) {
            throw new RutaNoEncontrada('Categoria inexistente para este food truck.');
        }

        $activa = (int) $categoria['activo'] === 1;
        Categoria::cambiarEstado($id, $ft, !$activa);

        Csrf::rotar();
        Sesion::mensaje($activa ? 'Categoria desactivada.' : 'Categoria activada.', 'exito');

        $this->redirigir('/panel/categorias');
    }
}
