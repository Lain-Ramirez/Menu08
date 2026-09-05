<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Categoria;
use Menu08\Modelos\Producto;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\GestorImagenes;
use Menu08\Nucleo\RutaNoEncontrada;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Validador;

/**
 * Productos del catalogo, con carga de foto.
 */
final class ProductoControlador extends Controlador
{
    public function listado(): void
    {
        $this->exigirRol('food_truck');

        $ft = $this->foodTruckActual();

        // Filtro por categoria. Llega como ?categoria=<id>; cualquier otra cosa
        // —vacio, texto, cero— se trata como "todas". No hace falta comprobar
        // que la categoria sea de este food truck: delFoodTruck() ya filtra por
        // food_truck_id, asi que un id ajeno devuelve una lista vacia y nunca
        // productos de otro negocio.
        $categoriaId = (int) ($_GET['categoria'] ?? 0);
        $categoriaId = $categoriaId > 0 ? $categoriaId : null;

        $this->vista('panel/productos', [
            'productos'  => Producto::delFoodTruck($ft, $categoriaId),
            'categorias' => Categoria::delFoodTruck($ft),
            'filtro'     => $categoriaId,
        ], 'Productos');
    }

    public function nuevo(): void
    {
        $this->exigirRol('food_truck');

        $ft = $this->foodTruckActual();

        $this->formularioCon($ft, null, []);
    }

    public function editar(string $id): void
    {
        $this->exigirRol('food_truck');

        $ft       = $this->foodTruckActual();
        $producto = Producto::porId((int) $id, $ft);

        if ($producto === null) {
            throw new RutaNoEncontrada(sprintf('Producto %s inexistente para este food truck.', $id));
        }

        $this->formularioCon($ft, $producto, []);
    }

    public function guardar(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $ft       = $this->foodTruckActual();
        $id       = (int) ($_POST['id'] ?? 0);
        $anterior = $id > 0 ? Producto::porId($id, $ft) : null;

        if ($id > 0 && $anterior === null) {
            throw new RutaNoEncontrada('Producto inexistente para este food truck.');
        }

        $v           = new Validador();
        $nombre      = $v->texto('nombre', $_POST['nombre'] ?? '', 120, true, 'El nombre');
        $descripcion = $v->texto('descripcion', $_POST['descripcion'] ?? '', 400, false, 'La descripcion');
        $precio      = $v->precio('precio', $_POST['precio'] ?? '');
        $orden       = $v->entero('orden', $_POST['orden'] ?? '0', 0, 999, 'El orden');
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);

        // La categoria tiene que ser del mismo food truck.
        if (Categoria::porId($categoriaId, $ft) === null) {
            $v->error('categoria_id', 'Seleccione una categoria valida.');
        }

        if ($nombre !== null && $categoriaId > 0 && Producto::nombreRepetido($categoriaId, $nombre, $id)) {
            $v->error('nombre', 'Ya existe un producto con ese nombre en la categoria.');
        }

        $foto = GestorImagenes::guardar($_FILES['foto'] ?? null, $v, 'foto');

        if (!$v->correcto()) {
            // Si la imagen se guardo pero otro campo fallo, no se deja huerfana.
            GestorImagenes::borrar($foto);

            $this->formularioCon($ft, array_merge((array) $anterior, $_POST, ['id' => $id]), $v->errores(), 422);

            return;
        }

        $datos = [
            'categoria_id' => $categoriaId,
            'nombre'       => $nombre,
            'descripcion'  => $descripcion,
            'precio'       => $precio,
            'foto'         => $foto,
            'disponible'   => isset($_POST['disponible']) ? 1 : 0,
            'orden'        => $orden,
        ];

        if ($id > 0) {
            if ($foto !== null && $anterior !== null) {
                GestorImagenes::borrar((string) $anterior['foto']);
            }

            Producto::actualizar($id, $ft, $datos);
            Sesion::mensaje('Producto actualizado.', 'exito');
        } else {
            Producto::crear($ft, $datos);
            Sesion::mensaje('Producto creado.', 'exito');
        }

        Csrf::rotar();

        $this->redirigir('/panel/productos');
    }

    public function cambiarDisponibilidad(): void
    {
        $this->exigirRol('food_truck');
        $this->verificarCsrf();

        $ft       = $this->foodTruckActual();
        $id       = (int) ($_POST['id'] ?? 0);
        $producto = Producto::porId($id, $ft);

        if ($producto === null) {
            throw new RutaNoEncontrada('Producto inexistente para este food truck.');
        }

        $disponible = (int) $producto['disponible'] === 1;
        Producto::cambiarDisponibilidad($id, $ft, !$disponible);

        Csrf::rotar();
        Sesion::mensaje($disponible ? 'Producto marcado como no disponible.' : 'Producto disponible.', 'exito');

        $this->redirigir('/panel/productos');
    }

    /**
     * @param array<string, mixed>|null $producto
     * @param array<string, string>     $errores
     */
    private function formularioCon(int $foodTruckId, ?array $producto, array $errores, int $codigo = 200): void
    {
        $this->vista('panel/producto_formulario', [
            'producto'   => $producto,
            'categorias' => Categoria::delFoodTruck($foodTruckId),
            'errores'    => $errores,
        ], $producto === null ? 'Nuevo producto' : 'Editar producto', $codigo);
    }
}
