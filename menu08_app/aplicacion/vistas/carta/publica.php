<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Carta publica. Se consulta desde el telefono, haciendo fila en la ventanilla,
 * asi que el movil manda: una sola columna y texto grande.
 *
 * @var array<string, mixed>                    $truck
 * @var array<string, list<array<string,mixed>>> $porCategoria
 * @var list<array<string, mixed>>               $agenda
 * @var array<string, mixed>|null                $vigente
 * @var array<int, string>                       $dias
 */
?>
<article class="carta">
    <header class="carta-cabecera">
        <?php if (!empty($truck['logo'])) : ?>
            <img src="<?= Vista::e(Vista::url('/subidas/' . $truck['logo'])) ?>"
                 alt="<?= Vista::e($truck['nombre']) ?>" class="carta-logo">
        <?php endif; ?>

        <h1><?= Vista::e($truck['nombre']) ?></h1>

        <?php if (!empty($truck['descripcion'])) : ?>
            <p><?= Vista::e($truck['descripcion']) ?></p>
        <?php endif; ?>

        <p class="carta-contacto">
            <?php if (!empty($truck['ciudad'])) : ?><?= Vista::e($truck['ciudad']) ?><?php endif; ?>
            <?php if (!empty($truck['whatsapp'])) : ?>
                · <a href="https://wa.me/<?= Vista::e(preg_replace('/\D/', '', (string) $truck['whatsapp'])) ?>">WhatsApp</a>
            <?php elseif (!empty($truck['telefono'])) : ?>
                · <?= Vista::e($truck['telefono']) ?>
            <?php endif; ?>
            <?php if (!empty($truck['instagram'])) : ?>
                · <?= Vista::e($truck['instagram']) ?>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($agenda !== []) : ?>
        <section class="carta-agenda">
            <h2>Donde estamos</h2>

            <?php if ($vigente !== null) : ?>
                <p>
                    <strong>Ahora en <?= Vista::e($vigente['nombre']) ?></strong>
                    <?php if (!empty($vigente['referencia'])) : ?>
                        · <?= Vista::e($vigente['referencia']) ?>
                    <?php endif; ?>
                    · hasta las <?= Vista::e(substr((string) $vigente['hora_fin'], 0, 5)) ?>
                </p>
            <?php endif; ?>

            <ul class="carta-lista">
                <?php foreach ($agenda as $u) : ?>
                    <li class="carta-item">
                        <div class="carta-texto">
                            <h3><?= Vista::e($u['nombre']) ?></h3>
                            <?php if (!empty($u['referencia'])) : ?>
                                <p><?= Vista::e($u['referencia']) ?></p>
                            <?php endif; ?>
                        </div>

                        <span class="carta-precio">
                            <?= Vista::e($dias[(int) $u['dia_semana']] ?? '') ?>
                            <?= Vista::e(substr((string) $u['hora_inicio'], 0, 5)) ?>
                            a <?= Vista::e(substr((string) $u['hora_fin'], 0, 5)) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($porCategoria === []) : ?>
        <p class="aviso aviso-aviso">La carta se esta preparando. Vuelva en un momento.</p>
    <?php else : ?>
        <?php foreach ($porCategoria as $categoria => $productos) : ?>
            <section class="carta-categoria">
                <h2><?= Vista::e($categoria) ?></h2>

                <ul class="carta-lista">
                    <?php foreach ($productos as $p) : ?>
                        <li class="carta-item">
                            <?php if (!empty($p['foto'])) : ?>
                                <img src="<?= Vista::e(Vista::url('/subidas/' . $p['foto'])) ?>"
                                     alt="<?= Vista::e($p['nombre']) ?>" class="carta-foto" loading="lazy">
                            <?php endif; ?>

                            <div class="carta-texto">
                                <h3><?= Vista::e($p['nombre']) ?></h3>
                                <?php if (!empty($p['descripcion'])) : ?>
                                    <p><?= Vista::e($p['descripcion']) ?></p>
                                <?php endif; ?>
                            </div>

                            <span class="carta-precio">
                                $ <?= Vista::e(number_format((float) $p['precio'], 0, ',', '.')) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</article>
