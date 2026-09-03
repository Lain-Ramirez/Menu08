<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

/**
 * Acumula errores de validacion por campo, para que la vista pueda mostrar
 * cada mensaje junto al control que lo provoco.
 */
final class Validador
{
    /** @var array<string, string> */
    private array $errores = [];

    /** @var array<string, mixed> */
    private array $limpios = [];

    /**
     * Texto obligatorio, recortado y con longitud maxima.
     */
    public function texto(string $campo, mixed $valor, int $maximo, bool $obligatorio = true, string $etiqueta = 'El campo'): ?string
    {
        $v = trim((string) $valor);

        if ($v === '') {
            if ($obligatorio) {
                $this->errores[$campo] = sprintf('%s es obligatorio.', $etiqueta);

                return null;
            }

            // Campo opcional vacio: se guarda como NULL, no como cadena vacia.
            $this->limpios[$campo] = null;

            return null;
        }

        if (mb_strlen($v) > $maximo) {
            $this->errores[$campo] = sprintf('%s no puede pasar de %d caracteres.', $etiqueta, $maximo);

            return null;
        }

        return $this->limpios[$campo] = $v;
    }

    /**
     * Precio: numerico, no negativo y con dos decimales como maximo.
     *
     * Se acepta coma o punto como separador decimal, porque en Colombia se
     * escriben las dos formas, pero se guarda siempre con punto.
     */
    public function precio(string $campo, mixed $valor): ?string
    {
        $v = str_replace(',', '.', trim((string) $valor));

        if ($v === '') {
            $this->errores[$campo] = 'El precio es obligatorio.';

            return null;
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            $this->errores[$campo] = 'El precio debe ser un numero positivo con dos decimales como maximo.';

            return null;
        }

        if ((float) $v > 99999999.99) {
            $this->errores[$campo] = 'El precio excede el maximo admitido.';

            return null;
        }

        return $this->limpios[$campo] = number_format((float) $v, 2, '.', '');
    }

    /**
     * Entero dentro de un rango.
     */
    public function entero(string $campo, mixed $valor, int $minimo, int $maximo, string $etiqueta = 'El valor'): ?int
    {
        $v = trim((string) $valor);

        if ($v === '' || !preg_match('/^-?\d+$/', $v)) {
            $this->errores[$campo] = sprintf('%s debe ser un numero entero.', $etiqueta);

            return null;
        }

        $n = (int) $v;

        if ($n < $minimo || $n > $maximo) {
            $this->errores[$campo] = sprintf('%s debe estar entre %d y %d.', $etiqueta, $minimo, $maximo);

            return null;
        }

        return $this->limpios[$campo] = $n;
    }

    public function error(string $campo, string $mensaje): void
    {
        $this->errores[$campo] = $mensaje;
    }

    public function correcto(): bool
    {
        return $this->errores === [];
    }

    /**
     * @return array<string, string>
     */
    public function errores(): array
    {
        return $this->errores;
    }

    public function valor(string $campo): mixed
    {
        return $this->limpios[$campo] ?? null;
    }
}
