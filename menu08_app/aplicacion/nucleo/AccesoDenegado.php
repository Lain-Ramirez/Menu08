<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use RuntimeException;

/**
 * El visitante esta autenticado pero su rol no alcanza para lo que pidio,
 * o un formulario llego sin token valido. El manejador la traduce a 403.
 */
final class AccesoDenegado extends RuntimeException
{
}
