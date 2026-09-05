<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Modelos\Producto;
use Menu08\Modelos\Ubicacion;
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

        // catalogoCarta y no catalogoPublico: la carta si muestra lo agotado,
        // atenuado y con su etiqueta. El de CAJA lo deja fuera.
        $catalogo = Producto::catalogoCarta((int) $truck['id']);

        // Se agrupa por categoria conservando el orden que trae la consulta.
        // Se guarda el id ademas del nombre porque la barra de categorias
        // enlaza a cada bloque con un ancla: dos categorias podrian llamarse
        // parecido, pero el id es unico y no cambia si se renombra una.
        $porCategoria = [];

        foreach ($catalogo as $fila) {
            $id = (int) $fila['categoria_id'];

            $porCategoria[$id] ??= ['nombre' => (string) $fila['categoria'], 'productos' => []];
            $porCategoria[$id]['productos'][] = $fila;
        }

        // La agenda de paradas responde la pregunta que el cliente hace en la
        // fila: donde esta el truck. agendaPublica() deja fuera las paradas
        // desactivadas.
        // vistaPublica y no vista: esta pantalla la abre el cliente desde el
        // codigo QR, sin sesion. No lleva el marco del panel.
        $this->vistaPublica(
            'carta/publica',
            [
                'truck'        => $truck,
                'porCategoria' => $porCategoria,
                'agenda'       => Ubicacion::agendaPublica((int) $truck['id']),
                'vigente'      => Ubicacion::vigente((int) $truck['id']),
                'dias'         => Ubicacion::DIAS,
            ],
            sprintf('%s · carta', $truck['nombre']),
            ['carta.css']
        );
    }
}
