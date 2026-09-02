<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Nucleo\ConexionBD;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\RutaNoEncontrada;

/**
 * Controlador de comprobacion del nucleo.
 *
 * Demuestra que el ciclo completo funciona: el front controller recibe la
 * peticion, el enrutador la resuelve, el controlador consulta la base con PDO
 * y la vista se renderiza dentro de la plantilla comun.
 *
 * Se retira cuando el panel real ocupe la ruta raiz.
 */
final class InicioControlador extends Controlador
{
    public function comprobacion(): void
    {
        $pdo = ConexionBD::obtener();

        $trucks = $pdo
            ->query('SELECT nombre, slug, ciudad, activo FROM food_trucks ORDER BY nombre')
            ->fetchAll();

        $estados = $pdo
            ->query('SELECT codigo, nombre FROM estados_orden ORDER BY orden')
            ->fetchAll();

        $this->vista(
            'inicio/comprobacion',
            ['trucks' => $trucks, 'estados' => $estados],
            'Comprobacion del nucleo'
        );
    }

    /**
     * Ruta con parametro. La consulta usa una sentencia preparada: el slug
     * llega de la direccion y nunca se concatena dentro del SQL.
     */
    public function porSlug(string $slug): void
    {
        $sentencia = ConexionBD::obtener()->prepare(
            'SELECT nombre, slug, ciudad, descripcion FROM food_trucks WHERE slug = :slug AND activo = 1'
        );

        $sentencia->execute(['slug' => $slug]);

        $truck = $sentencia->fetch();

        if ($truck === false) {
            throw new RutaNoEncontrada(sprintf('No hay un food truck activo con el slug %s.', $slug));
        }

        $this->vista('inicio/truck', ['truck' => $truck], (string) $truck['nombre']);
    }
}
