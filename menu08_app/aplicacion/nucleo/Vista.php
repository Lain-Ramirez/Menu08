<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use RuntimeException;
use Throwable;

/**
 * Renderiza las plantillas de aplicacion/vistas dentro de la plantilla comun.
 */
final class Vista
{
    /**
     * Renderiza una plantilla suelta y devuelve su contenido.
     *
     * @param array<string, mixed> $datos
     */
    public static function renderizar(string $plantilla, array $datos = []): string
    {
        $ruta = self::carpeta() . '/' . self::normalizar($plantilla) . '.php';

        if (!is_file($ruta)) {
            throw new RuntimeException(sprintf('No existe la vista %s.', $plantilla));
        }

        return self::incluir($ruta, $datos);
    }

    /**
     * Ejecuta la plantilla en un ambito propio.
     *
     * Las variables locales llevan el prefijo __vista para que ninguna clave de
     * los datos pueda chocar con ellas, y ademas se eliminan de los datos antes
     * de extraerlos. Sin esta precaucion, una vista que reciba una clave llamada
     * como una variable interna se queda con el valor interno y falla en
     * silencio: no hay error, simplemente muestra el dato equivocado.
     *
     * @param array<string, mixed> $__vista_datos
     */
    private static function incluir(string $__vista_ruta, array $__vista_datos): string
    {
        unset($__vista_datos['__vista_ruta'], $__vista_datos['__vista_datos']);

        extract($__vista_datos, EXTR_OVERWRITE);

        ob_start();

        try {
            require $__vista_ruta;
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Renderiza una plantilla y la envuelve en plantillas/base.
     *
     * @param array<string, mixed> $datos
     */
    public static function pagina(
        string $plantilla,
        array $datos = [],
        string $titulo = 'Menu08',
        string $marco = 'plantillas/base',
        array $hojas = []
    ): string {
        return self::renderizar($marco, [
            'titulo'    => $titulo,
            'hojas'     => $hojas,
            'contenido' => self::renderizar($plantilla, $datos),
        ]);
    }

    /**
     * Escapa texto para insertarlo en HTML. Se usa en TODA salida dinamica.
     */
    public static function e(mixed $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Construye una direccion absoluta a partir de url_base.
     */
    public static function url(string $ruta = '/'): string
    {
        $base = rtrim((string) Configuracion::obtener('url_base', ''), '/');

        return $base . '/' . ltrim($ruta, '/');
    }

    private static function carpeta(): string
    {
        return dirname(__DIR__) . '/vistas';
    }

    /**
     * Impide salir de aplicacion/vistas mediante nombres como ../../configuracion.
     */
    private static function normalizar(string $plantilla): string
    {
        $limpia = str_replace('\\', '/', $plantilla);
        $limpia = preg_replace('#[^a-zA-Z0-9_/-]#', '', $limpia) ?? '';

        return trim(str_replace('..', '', $limpia), '/');
    }
}
