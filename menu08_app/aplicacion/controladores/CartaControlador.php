<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Modelos\Producto;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\RutaNoEncontrada;

/**
 * Carta publica. Es la pantalla que abre el cliente al leer el codigo QR
 * pegado en la ventanilla, haciendo fila. No exige sesion.
 */
final class CartaControlador extends Controlador
{
    public function publica(string $slug): void
    {
        // porSlug ya filtra por activo = 1: un food truck inactivo no existe
        // para el publico, igual que uno cuyo slug no esta registrado.
        $truck = FoodTruck::porSlug($slug);

        if ($truck === null) {
            throw new RutaNoEncontrada(sprintf('No hay un food truck activo con el slug "%s".', $slug));
        }

        $catalogo = Producto::catalogoPublico((int) $truck['id']);

        // Se agrupa por categoria conservando el orden que trae la consulta.
        $porCategoria = [];

        foreach ($catalogo as $fila) {
            $porCategoria[$fila['categoria']][] = $fila;
        }

        $this->vista('carta/publica', [
            'truck'        => $truck,
            'porCategoria' => $porCategoria,
        ], (string) $truck['nombre']);
    }
}
