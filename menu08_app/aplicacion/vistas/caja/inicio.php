<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Marcador de posicion del modulo. Lo reemplazan los issues de su fase.
 *
 * @var array<string, mixed> $usuario
 */
?>
<h1>CAJA</h1>

<p>
    Zona privada. Entro con el rol <code><?= Vista::e($usuario['rol']) ?></code>,
    como <?= Vista::e($usuario['nombre']) ?>.
</p>

<p>El modulo se construye en su fase correspondiente.</p>
