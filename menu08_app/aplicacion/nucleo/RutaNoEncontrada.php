<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use RuntimeException;

/**
 * El enrutador no halló ninguna ruta que coincida, o el recurso pedido no existe.
 * El manejador de errores la traduce a una respuesta 404.
 */
final class RutaNoEncontrada extends RuntimeException
{
}
