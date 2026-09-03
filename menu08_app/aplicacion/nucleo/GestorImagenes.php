<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use RuntimeException;

/**
 * Subida de imagenes de producto y de logotipo.
 *
 * El tipo se determina leyendo el CONTENIDO del archivo, nunca su extension ni
 * el encabezado que envia el navegador: los dos los controla quien sube. El
 * archivo se renombra con un nombre aleatorio, de modo que subir "foto.php.jpg"
 * no deja nada ejecutable ni predecible en la carpeta publica.
 */
final class GestorImagenes
{
    private const TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Devuelve el nombre del archivo guardado, o null si no se envio ninguno.
     *
     * @param array<string, mixed>|null $archivo entrada de $_FILES
     */
    public static function guardar(?array $archivo, Validador $validador, string $campo = 'foto'): ?string
    {
        if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $validador->error($campo, match ((int) $archivo['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido.',
                UPLOAD_ERR_PARTIAL                        => 'La imagen se subio a medias. Intente de nuevo.',
                default                                   => 'No fue posible subir la imagen.',
            });

            return null;
        }

        $ruta = (string) $archivo['tmp_name'];

        if (!is_uploaded_file($ruta)) {
            $validador->error($campo, 'El archivo recibido no es una subida valida.');

            return null;
        }

        $maximo = (int) Configuracion::obtener('subidas.tamano_maximo', 2 * 1024 * 1024);

        if ((int) $archivo['size'] > $maximo) {
            $validador->error($campo, sprintf('La imagen no puede pasar de %d MB.', (int) round($maximo / 1048576)));

            return null;
        }

        $tipo = self::tipoReal($ruta);

        if ($tipo === null || !isset(self::TIPOS[$tipo])) {
            $validador->error($campo, 'Solo se admiten imagenes JPG, PNG o WEBP.');

            return null;
        }

        $carpeta = rtrim((string) Configuracion::obtener('subidas.ruta', ''), '/');

        if ($carpeta === '' || (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta))) {
            throw new RuntimeException('La carpeta de subidas no existe y no se pudo crear.');
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . self::TIPOS[$tipo];

        if (!move_uploaded_file($ruta, $carpeta . '/' . $nombre)) {
            throw new RuntimeException('No fue posible guardar la imagen subida.');
        }

        @chmod($carpeta . '/' . $nombre, 0644);

        return $nombre;
    }

    /**
     * Borra una imagen anterior. Solo acepta nombres simples, para que un valor
     * manipulado no pueda apuntar fuera de la carpeta de subidas.
     */
    public static function borrar(?string $nombre): void
    {
        if ($nombre === null || $nombre === '' || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $nombre)) {
            return;
        }

        $carpeta = rtrim((string) Configuracion::obtener('subidas.ruta', ''), '/');
        $ruta    = $carpeta . '/' . $nombre;

        if ($carpeta !== '' && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /**
     * Tipo determinado por el contenido. Se prefiere fileinfo; si la extension
     * no esta disponible en el servidor se recurre a getimagesize, que tambien
     * lee las cabeceras del archivo y no la extension.
     */
    private static function tipoReal(string $ruta): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $tipo = finfo_file($finfo, $ruta);
                finfo_close($finfo);

                if (is_string($tipo) && $tipo !== '') {
                    return $tipo;
                }
            }
        }

        $info = @getimagesize($ruta);

        return is_array($info) && isset($info['mime']) ? (string) $info['mime'] : null;
    }
}
