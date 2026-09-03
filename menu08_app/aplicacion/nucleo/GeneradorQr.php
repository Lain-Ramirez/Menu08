<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use InvalidArgumentException;

/**
 * Generador de codigos QR en PHP puro, sin gestor de dependencias ni GD.
 *
 * Cubre lo que necesita el prototipo: modo binario, nivel de correccion M y
 * versiones 1 a 10, suficiente para una direccion de hasta unos 200 caracteres.
 *
 * El nivel M recupera cerca del 15% de los modulos dañados, que es lo adecuado
 * para un adhesivo pegado en la ventanilla de un food truck: aguanta suciedad y
 * roces sin volverse ilegible.
 *
 * El PNG se arma a mano con pack() y gzcompress(), de modo que tampoco hace
 * falta la extension GD en el servidor.
 */
final class GeneradorQr
{
    /** version => [correccion por bloque, bloques g1, datos g1, bloques g2, datos g2] */
    private const BLOQUES_M = [
        1  => [10, 1, 16, 0, 0],
        2  => [16, 1, 28, 0, 0],
        3  => [26, 1, 44, 0, 0],
        4  => [18, 2, 32, 0, 0],
        5  => [24, 2, 43, 0, 0],
        6  => [16, 4, 27, 0, 0],
        7  => [18, 4, 31, 0, 0],
        8  => [22, 2, 38, 2, 39],
        9  => [22, 3, 36, 2, 37],
        10 => [26, 4, 43, 1, 44],
    ];

    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** @var list<int> */
    private static array $exp = [];

    /** @var list<int> */
    private static array $log = [];

    /**
     * Devuelve el contenido binario de un PNG con el codigo QR.
     */
    public static function png(string $texto, int $escala = 8, int $margen = 4): string
    {
        [$matriz] = self::matriz($texto);

        return self::armarPng($matriz, max(1, $escala), max(0, $margen));
    }

    /**
     * @return array{0: list<list<int>>, 1: int} matriz de 0 y 1, y la version usada
     */
    public static function matriz(string $texto): array
    {
        self::prepararCampo();

        $version = self::elegirVersion(strlen($texto));
        $lado    = $version * 4 + 17;

        $codigos = self::entrelazar(self::codificarDatos($texto, $version), $version);

        $bits = [];
        foreach ($codigos as $c) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($c >> $i) & 1;
            }
        }

        $mejorPena   = null;
        $mejorMatriz = null;

        for ($mascara = 0; $mascara < 8; $mascara++) {
            $m         = self::matrizBase($version);
            $reservado = self::colocar($m, $bits, $version);

            for ($r = 0; $r < $lado; $r++) {
                for ($c = 0; $c < $lado; $c++) {
                    if (!$reservado[$r][$c] && self::mascara($mascara, $r, $c)) {
                        $m[$r][$c] ^= 1;
                    }
                }
            }

            self::escribirFormato($m, $mascara, $lado);

            if ($version >= 7) {
                self::escribirVersion($m, $version, $lado);
            }

            $pena = self::penalizacion($m, $lado);

            if ($mejorPena === null || $pena < $mejorPena) {
                $mejorPena   = $pena;
                $mejorMatriz = $m;
            }
        }

        /** @var list<list<int>> $mejorMatriz */
        return [$mejorMatriz, $version];
    }

    // --- Campo de Galois GF(256) -------------------------------------------

    private static function prepararCampo(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x   = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;

            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        self::$exp = $exp;
        self::$log = $log;
    }

    private static function multiplicar(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /**
     * @return list<int>
     */
    private static function generador(int $n): array
    {
        $g = [1];

        for ($i = 0; $i < $n; $i++) {
            $nuevo = array_fill(0, count($g) + 1, 0);

            foreach ($g as $j => $c) {
                $nuevo[$j]     ^= $c;
                $nuevo[$j + 1] ^= self::multiplicar($c, self::$exp[$i]);
            }

            $g = $nuevo;
        }

        return $g;
    }

    /**
     * Codigos de correccion Reed-Solomon.
     *
     * @param list<int> $datos
     *
     * @return list<int>
     */
    private static function correccion(array $datos, int $n): array
    {
        $g   = self::generador($n);
        $res = array_merge($datos, array_fill(0, $n, 0));

        foreach ($datos as $i => $_) {
            $factor = $res[$i];

            if ($factor !== 0) {
                foreach ($g as $j => $c) {
                    $res[$i + $j] ^= self::multiplicar($c, $factor);
                }
            }
        }

        return array_values(array_slice($res, count($datos)));
    }

    // --- Datos --------------------------------------------------------------

    private static function capacidad(int $version): int
    {
        [, $g1, $d1, $g2, $d2] = self::BLOQUES_M[$version];

        return $g1 * $d1 + $g2 * $d2;
    }

    private static function elegirVersion(int $bytes): int
    {
        for ($v = 1; $v <= 10; $v++) {
            $bitsCuenta = $v <= 9 ? 8 : 16;

            if (self::capacidad($v) * 8 >= 4 + $bitsCuenta + $bytes * 8) {
                return $v;
            }
        }

        throw new InvalidArgumentException('El texto es demasiado largo para un codigo QR de version 10.');
    }

    /**
     * @return list<int>
     */
    private static function codificarDatos(string $texto, int $version): array
    {
        $bitsCuenta = $version <= 9 ? 8 : 16;
        $bits       = [];

        $push = static function (int $valor, int $ancho) use (&$bits): void {
            for ($i = $ancho - 1; $i >= 0; $i--) {
                $bits[] = ($valor >> $i) & 1;
            }
        };

        $push(0b0100, 4);                 // modo binario
        $push(strlen($texto), $bitsCuenta);

        for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
            $push(ord($texto[$i]), 8);
        }

        $total = self::capacidad($version) * 8;

        for ($i = 0, $n = min(4, $total - count($bits)); $i < $n; $i++) {
            $bits[] = 0;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codigos = [];

        for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
            $byte = 0;

            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }

            $codigos[] = $byte;
        }

        $relleno = [0xEC, 0x11];
        $k       = 0;

        while (count($codigos) < self::capacidad($version)) {
            $codigos[] = $relleno[$k % 2];
            $k++;
        }

        return $codigos;
    }

    /**
     * @param list<int> $codigos
     *
     * @return list<int>
     */
    private static function entrelazar(array $codigos, int $version): array
    {
        [$ec, $g1, $d1, $g2, $d2] = self::BLOQUES_M[$version];

        $bloques = [];
        $p       = 0;

        for ($i = 0; $i < $g1; $i++) {
            $bloques[] = array_slice($codigos, $p, $d1);
            $p += $d1;
        }

        for ($i = 0; $i < $g2; $i++) {
            $bloques[] = array_slice($codigos, $p, $d2);
            $p += $d2;
        }

        $correcciones = [];

        foreach ($bloques as $b) {
            $correcciones[] = self::correccion($b, $ec);
        }

        $largoMaximo = 0;

        foreach ($bloques as $b) {
            $largoMaximo = max($largoMaximo, count($b));
        }

        $salida = [];

        for ($i = 0; $i < $largoMaximo; $i++) {
            foreach ($bloques as $b) {
                if ($i < count($b)) {
                    $salida[] = $b[$i];
                }
            }
        }

        for ($i = 0; $i < $ec; $i++) {
            foreach ($correcciones as $e) {
                $salida[] = $e[$i];
            }
        }

        return $salida;
    }

    // --- Matriz -------------------------------------------------------------

    /**
     * @return list<list<int|null>>
     */
    private static function matrizBase(int $version): array
    {
        $t = $version * 4 + 17;
        $m = array_fill(0, $t, array_fill(0, $t, null));

        foreach ([[0, 0], [0, $t - 7], [$t - 7, 0]] as [$fr, $fc]) {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $r = $fr + $i;
                    $c = $fc + $j;

                    if ($r < 0 || $r >= $t || $c < 0 || $c >= $t) {
                        continue;
                    }

                    $borde  = ($i === -1 || $i === 7 || $j === -1 || $j === 7);
                    $dentro = ($i >= 0 && $i <= 6 && $j >= 0 && $j <= 6);
                    $anillo = $dentro && ($i === 0 || $i === 6 || $j === 0 || $j === 6);
                    $centro = $dentro && ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);

                    $m[$r][$c] = (!$borde && ($anillo || $centro)) ? 1 : 0;
                }
            }
        }

        for ($i = 8; $i < $t - 8; $i++) {
            $m[6][$i] = $i % 2 === 0 ? 1 : 0;
            $m[$i][6] = $i % 2 === 0 ? 1 : 0;
        }

        foreach (self::ALINEACION[$version] as $r) {
            foreach (self::ALINEACION[$version] as $c) {
                if (($r < 9 && $c < 9) || ($r < 9 && $c > $t - 10) || ($r > $t - 10 && $c < 9)) {
                    continue;
                }

                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $m[$r + $i][$c + $j] = max(abs($i), abs($j)) === 1 ? 0 : 1;
                    }
                }
            }
        }

        $m[$t - 8][8] = 1;

        for ($i = 0; $i < 9; $i++) {
            if ($m[8][$i] === null) {
                $m[8][$i] = 0;
            }
            if ($m[$i][8] === null) {
                $m[$i][8] = 0;
            }
        }

        for ($i = 0; $i < 8; $i++) {
            if ($m[8][$t - 1 - $i] === null) {
                $m[8][$t - 1 - $i] = 0;
            }
            if ($m[$t - 1 - $i][8] === null) {
                $m[$t - 1 - $i][8] = 0;
            }
        }

        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $m[$t - 11 + $j][$i] = 0;
                    $m[$i][$t - 11 + $j] = 0;
                }
            }
        }

        return $m;
    }

    /**
     * Coloca los bits en zigzag desde la esquina inferior derecha.
     *
     * @param list<list<int|null>> $m
     * @param list<int>            $bits
     *
     * @return list<list<bool>> mapa de modulos reservados por los patrones fijos
     */
    private static function colocar(array &$m, array $bits, int $version): array
    {
        $t         = $version * 4 + 17;
        $reservado = [];

        for ($r = 0; $r < $t; $r++) {
            for ($c = 0; $c < $t; $c++) {
                $reservado[$r][$c] = $m[$r][$c] !== null;
            }
        }

        $i      = 0;
        $arriba = true;
        $col    = $t - 1;

        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }

            for ($k = 0; $k < $t; $k++) {
                $r = $arriba ? $t - 1 - $k : $k;

                foreach ([$col, $col - 1] as $c) {
                    if (!$reservado[$r][$c]) {
                        $m[$r][$c] = $i < count($bits) ? $bits[$i] : 0;
                        $i++;
                    }
                }
            }

            $arriba = !$arriba;
            $col -= 2;
        }

        return $reservado;
    }

    private static function mascara(int $n, int $r, int $c): bool
    {
        return match ($n) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
            6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
            default => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
        };
    }

    /**
     * @param list<list<int>> $m
     */
    private static function escribirFormato(array &$m, int $mascara, int $t): void
    {
        $datos = (0b00 << 3) | $mascara;   // nivel M
        $v     = $datos << 10;

        for ($i = 4; $i >= 0; $i--) {
            if ($v & (1 << ($i + 10))) {
                $v ^= 0b10100110111 << $i;
            }
        }

        $f = (($datos << 10) | $v) ^ 0b101010000010010;

        // Copia 1: bits 0-5 en la columna 8, el codo, y 9-14 en la fila 8.
        for ($i = 0; $i < 15; $i++) {
            $b = ($f >> $i) & 1;

            if ($i < 6) {
                $m[$i][8] = $b;
            } elseif ($i === 6) {
                $m[7][8] = $b;
            } elseif ($i === 7) {
                $m[8][8] = $b;
            } elseif ($i === 8) {
                $m[8][7] = $b;
            } else {
                $m[8][14 - $i] = $b;
            }
        }

        // Copia 2: bits 0-7 en la fila 8 por la derecha, 8-14 en la columna 8.
        for ($i = 0; $i < 15; $i++) {
            $b = ($f >> $i) & 1;

            if ($i < 8) {
                $m[8][$t - 1 - $i] = $b;
            } else {
                $m[$t - 15 + $i][8] = $b;
            }
        }

        $m[$t - 8][8] = 1;   // modulo oscuro, siempre 1
    }

    /**
     * @param list<list<int>> $m
     */
    private static function escribirVersion(array &$m, int $version, int $t): void
    {
        $d = $version << 12;

        for ($i = 5; $i >= 0; $i--) {
            if ($d & (1 << ($i + 12))) {
                $d ^= 0b1111100100101 << $i;
            }
        }

        $info = ($version << 12) | $d;

        for ($i = 0; $i < 18; $i++) {
            $b = ($info >> $i) & 1;
            $a = $t - 11 + $i % 3;
            $c = intdiv($i, 3);

            $m[$c][$a] = $b;
            $m[$a][$c] = $b;
        }
    }

    /**
     * @param list<list<int>> $m
     */
    private static function penalizacion(array $m, int $t): int
    {
        $lineas = [];

        for ($r = 0; $r < $t; $r++) {
            $lineas[] = $m[$r];
        }

        for ($c = 0; $c < $t; $c++) {
            $col = [];

            for ($r = 0; $r < $t; $r++) {
                $col[] = $m[$r][$c];
            }

            $lineas[] = $col;
        }

        $p = 0;

        foreach ($lineas as $linea) {
            $racha = 1;

            for ($i = 1; $i < $t; $i++) {
                if ($linea[$i] === $linea[$i - 1]) {
                    $racha++;
                } else {
                    if ($racha >= 5) {
                        $p += 3 + ($racha - 5);
                    }
                    $racha = 1;
                }
            }

            if ($racha >= 5) {
                $p += 3 + ($racha - 5);
            }
        }

        for ($r = 0; $r < $t - 1; $r++) {
            for ($c = 0; $c < $t - 1; $c++) {
                if ($m[$r][$c] === $m[$r][$c + 1] && $m[$r][$c] === $m[$r + 1][$c] && $m[$r][$c] === $m[$r + 1][$c + 1]) {
                    $p += 3;
                }
            }
        }

        $patron  = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $patron2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];

        foreach ($lineas as $linea) {
            for ($i = 0; $i <= $t - 11; $i++) {
                $trozo = array_slice($linea, $i, 11);

                if ($trozo === $patron || $trozo === $patron2) {
                    $p += 40;
                }
            }
        }

        $oscuros = 0;

        foreach ($m as $fila) {
            $oscuros += array_sum($fila);
        }

        $proporcion = intdiv($oscuros * 100, $t * $t);
        $p += 10 * intdiv(abs($proporcion - 50), 5);

        return $p;
    }

    // --- PNG sin GD ---------------------------------------------------------

    /**
     * @param list<list<int>> $m
     */
    private static function armarPng(array $m, int $escala, int $margen): string
    {
        $t    = count($m);
        $lado = ($t + $margen * 2) * $escala;
        $crudo = '';

        for ($y = 0; $y < $lado; $y++) {
            $crudo .= "\x00";                        // filtro None
            $my = intdiv($y, $escala) - $margen;

            for ($x = 0; $x < $lado; $x++) {
                $mx     = intdiv($x, $escala) - $margen;
                $oscuro = $mx >= 0 && $mx < $t && $my >= 0 && $my < $t && $m[$my][$mx] === 1;
                $crudo .= $oscuro ? "\x00\x00\x00" : "\xff\xff\xff";
            }
        }

        $trozo = static function (string $tipo, string $datos): string {
            return pack('N', strlen($datos)) . $tipo . $datos . pack('N', crc32($tipo . $datos));
        };

        return "\x89PNG\r\n\x1a\n"
            . $trozo('IHDR', pack('NNCCCCC', $lado, $lado, 8, 2, 0, 0, 0))
            . $trozo('IDAT', gzcompress($crudo, 9))
            . $trozo('IEND', '');
    }
}
