<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\FoodTruck;
use Menu08\Nucleo\Configuracion;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\GeneradorQr;
use Menu08\Nucleo\RutaNoEncontrada;
use Menu08\Nucleo\Vista;
use RuntimeException;

/**
 * Codigo QR de la carta publica: se muestra en el panel y se descarga en PNG
 * para imprimirlo y pegarlo en la ventanilla del food truck.
 */
final class QrControlador extends Controlador
{
    public function mostrar(): void
    {
        $this->exigirRol('food_truck', 'plataforma');

        $truck = $this->truckDeLaSesion();

        $this->vista('panel/qr', [
            'truck'   => $truck,
            'destino' => self::direccionPublica($truck),
            'archivo' => self::asegurarArchivo($truck),
        ], 'Codigo QR');
    }

    /**
     * Entrega el PNG como descarga, con nombre legible para quien lo imprime.
     */
    public function descargar(): void
    {
        $this->exigirRol('food_truck', 'plataforma');

        $truck  = $this->truckDeLaSesion();
        $imagen = GeneradorQr::png(self::direccionPublica($truck), 10, 4);

        header('Content-Type: image/png');
        header(sprintf('Content-Disposition: attachment; filename="qr-%s.png"', $truck['slug']));
        header('Content-Length: ' . strlen($imagen));
        header('Cache-Control: no-store');

        echo $imagen;
    }

    /**
     * @return array<string, mixed>
     */
    private function truckDeLaSesion(): array
    {
        $truck = FoodTruck::porId($this->foodTruckActual());

        if ($truck === null) {
            throw new RutaNoEncontrada('El food truck de la sesion ya no existe.');
        }

        return $truck;
    }

    /**
     * @param array<string, mixed> $truck
     */
    private static function direccionPublica(array $truck): string
    {
        return Vista::url('/carta/' . $truck['slug']);
    }

    /**
     * Deja el PNG en la carpeta de subidas para que la pagina lo muestre como
     * un archivo estatico. Se regenera siempre: el slug pudo cambiar, y un QR
     * que apunta a una direccion vieja es papel impreso inservible.
     *
     * @param array<string, mixed> $truck
     */
    private static function asegurarArchivo(array $truck): string
    {
        $carpeta = rtrim((string) Configuracion::obtener('subidas.ruta', ''), '/');

        if ($carpeta === '' || (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta))) {
            throw new RuntimeException('La carpeta de subidas no existe y no se pudo crear.');
        }

        $nombre = 'qr-' . $truck['slug'] . '.png';

        file_put_contents($carpeta . '/' . $nombre, GeneradorQr::png(self::direccionPublica($truck), 8, 4));
        @chmod($carpeta . '/' . $nombre, 0644);

        return $nombre;
    }
}
