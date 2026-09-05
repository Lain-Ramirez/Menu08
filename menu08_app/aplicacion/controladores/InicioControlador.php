<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Modelos\Ubicacion;
use Menu08\Nucleo\ConexionBD;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\RutaNoEncontrada;
use Menu08\Nucleo\Sesion;

/**
 * Portada publica.
 *
 * Es lo primero que ve quien escribe la direccion, y quien llega asi casi
 * siempre es un cliente buscando que comer: por eso el bloque principal son los
 * food trucks, cada uno con su carta a un clic y con el punto donde esta ahora.
 * El acceso del personal va despues, que es la minoria de las visitas.
 */
final class InicioControlador extends Controlador
{
    public function portada(): void
    {
        $trucks = FoodTruck::publicos();

        // Donde para cada truck en este momento. Son pocas consultas —una por
        // truck activo— y es el dato que decide si el cliente se acerca o no,
        // asi que compensa: sin el, la portada no responde "donde estan hoy".
        foreach ($trucks as $i => $truck) {
            $trucks[$i]['vigente'] = Ubicacion::vigente((int) $truck['id']);
        }

        $this->vista(
            'inicio/portada',
            [
                'trucks'  => $trucks,
                'usuario' => Sesion::autenticado() ? $this->usuario() : null,
                'inicio'  => Sesion::autenticado() ? self::INICIO_POR_ROL[Sesion::rol()] ?? '/panel' : null,
            ],
            'Cartas de food trucks'
        );
    }

    /**
     * A donde lleva a cada rol su boton de "ir a mi zona". Es el mismo destino
     * al que redirige el ingreso, en AutenticacionControlador.
     */
    private const INICIO_POR_ROL = [
        'plataforma' => '/panel',
        'food_truck' => '/panel',
        'cajero'     => '/caja',
        'produccion' => '/svp',
    ];

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
