<?php

declare(strict_types=1);

/**
 * Menu08 - Ejecutor de pruebas unitarias.
 *
 *   php menu08_app/pruebas/ejecutar.php
 *
 * Corre en frio: no lee la configuracion local, no abre sesion y no consulta la
 * base de datos. Por eso arranca en un clon recien bajado, sin MySQL instalado
 * y sin haber copiado la plantilla de configuracion.
 *
 * Aqui solo se prueba lo que es funcion pura: dado un valor de entrada, la
 * misma salida siempre. Lo que dependa del estado de las tablas se comprueba
 * contra el sitio publicado y queda en docs/pruebas-*.md, que es otra cosa.
 *
 * Sin gestor de dependencias y sin marco de pruebas: tres funciones bastan, y
 * asi el ejecutor se lee entero en un minuto.
 */

$raiz = dirname(__DIR__);

/**
 * La misma autocarga por convencion del front controller, recortada a lo
 * imprescindible: aqui no hay peticion que despachar.
 */
spl_autoload_register(static function (string $clase) use ($raiz): void {
    $prefijo = 'Menu08\\';

    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $carpetas = [
        'Nucleo'        => 'nucleo',
        'Controladores' => 'controladores',
        'Modelos'       => 'modelos',
    ];

    $partes  = explode('\\', substr($clase, strlen($prefijo)));
    $espacio = array_shift($partes);

    if (!isset($carpetas[$espacio]) || $partes === []) {
        return;
    }

    $archivo = $raiz . '/aplicacion/' . $carpetas[$espacio] . '/' . implode('/', $partes) . '.php';

    if (is_file($archivo)) {
        require $archivo;
    }
});

$comprobaciones = 0;
$fallos         = [];

/**
 * Comprobacion de una condicion suelta.
 */
function afirmar(bool $condicion, string $titulo): void
{
    global $comprobaciones, $fallos;

    ++$comprobaciones;

    if (!$condicion) {
        $fallos[] = sprintf('  FALLA  %s', $titulo);
    }
}

/**
 * Comprobacion de igualdad ESTRICTA, con ===.
 *
 * Los dos valores se imprimen con var_export porque el tipo es justo lo que se
 * quiere fijar: sin el, null, la cadena vacia y el entero 7 frente a la cadena
 * '7' saldrian iguales por pantalla y el fallo no diria nada.
 */
function afirmarIgual(mixed $esperado, mixed $obtenido, string $titulo): void
{
    global $comprobaciones, $fallos;

    ++$comprobaciones;

    if ($esperado !== $obtenido) {
        $fallos[] = sprintf(
            "  FALLA  %s\n           esperaba %s\n           obtuvo   %s",
            $titulo,
            var_export($esperado, true),
            var_export($obtenido, true)
        );
    }
}

/**
 * Cierra la ejecucion. Devuelve 1 si algo fallo, para que el ejecutor sirva de
 * comprobacion de un solo tiron.
 */
function resumen(): never
{
    global $comprobaciones, $fallos;

    if ($fallos !== []) {
        echo implode("\n", $fallos), "\n\n";
    }

    printf("%d comprobaciones, %d fallos\n", $comprobaciones, count($fallos));

    exit($fallos === [] ? 0 : 1);
}

$casos = glob($raiz . '/pruebas/casos/*.php');

if ($casos === false || $casos === []) {
    echo "No hay ningun caso en pruebas/casos/\n";

    exit(1);
}

foreach ($casos as $caso) {
    printf("· %s\n", basename($caso));

    require $caso;
}

echo "\n";

resumen();
