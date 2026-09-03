<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use RuntimeException;

/**
 * La peticion es legitima pero los datos no permiten completar la operacion:
 * un turno que ya se cerro, un producto que se retiro del catalogo, una linea
 * sin cantidad. No es un fallo del programa, es una situacion prevista.
 *
 * El manejador la traduce a 422, y los controladores que pueden mostrar el
 * formulario de nuevo la capturan para explicar el motivo junto a los datos.
 */
final class DatosInvalidos extends RuntimeException
{
}
