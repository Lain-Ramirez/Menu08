<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var array<string, mixed> $truck
 * @var string               $destino
 * @var string               $archivo
 */
?>
<h1>Codigo QR de la carta</h1>

<p>
    Apunta a <a href="<?= Vista::e($destino) ?>"><?= Vista::e($destino) ?></a>.
    Imprimalo y peguelo en la ventanilla del food truck.
</p>

<p>
    <img src="<?= Vista::e(Vista::url('/subidas/' . $archivo)) ?>?v=<?= Vista::e((string) time()) ?>"
         alt="Codigo QR de la carta de <?= Vista::e($truck['nombre']) ?>" width="280" height="280">
</p>

<p>
    <a href="<?= Vista::e(Vista::url('/panel/qr/descargar')) ?>">Descargar el PNG</a> ·
    <a href="<?= Vista::e(Vista::url('/panel')) ?>">Volver al panel</a>
</p>

<p class="aviso aviso-aviso">
    Si cambia el slug del food truck, el codigo cambia y los adhesivos ya impresos
    dejan de servir. Verifique la direccion antes de mandar a imprimir.
</p>
