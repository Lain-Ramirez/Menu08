<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * @var list<array<string, mixed>> $trucks
 * @var list<array<string, mixed>> $estados
 */
?>
<h1>El nucleo responde</h1>

<p>
    Esta pagina llego por <code>publico/index.php</code>, la resolvio el enrutador,
    la sirvio un controlador y los datos de abajo salieron de MySQL por PDO.
</p>

<h2>Food trucks registrados</h2>

<?php if ($trucks === []) : ?>
    <p>No hay food trucks en la base. Ejecute <code>basedatos/datos_iniciales.sql</code>.</p>
<?php else : ?>
    <table>
        <thead>
            <tr><th>Nombre</th><th>Slug</th><th>Ciudad</th><th>Estado</th></tr>
        </thead>
        <tbody>
        <?php foreach ($trucks as $truck) : ?>
            <tr>
                <td><?= Vista::e($truck['nombre']) ?></td>
                <td>
                    <a href="<?= Vista::e(Vista::url('/comprobacion/' . $truck['slug'])) ?>">
                        <?= Vista::e($truck['slug']) ?>
                    </a>
                </td>
                <td><?= Vista::e($truck['ciudad'] ?? '—') ?></td>
                <td><?= ((int) $truck['activo'] === 1) ? 'activo' : 'inactivo' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Estados de una orden</h2>

<p>
    <?php foreach ($estados as $estado) : ?>
        <code><?= Vista::e($estado['codigo']) ?></code>
    <?php endforeach; ?>
</p>

<p>
    Para comprobar la pagina de error, visite una direccion que no exista:
    <a href="<?= Vista::e(Vista::url('/no-existe')) ?>">/no-existe</a>.
</p>
