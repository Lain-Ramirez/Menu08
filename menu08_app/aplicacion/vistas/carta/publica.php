<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Carta publica. Se consulta desde el telefono, haciendo fila en la ventanilla,
 * asi que el movil manda: una sola columna y texto grande.
 *
 * No lleva el marco del panel. La renderiza plantillas/publica.php, que abre el
 * documento sin barra de usuario ni navegacion.
 *
 * @var array<string, mixed>                          $truck
 * @var array<int, array{nombre: string, productos: list<array<string, mixed>>}> $porCategoria
 * @var list<array<string, mixed>>                    $agenda
 * @var array<string, mixed>|null                     $vigente
 * @var array<int, string>                            $dias
 */

/**
 * Reemplazo para el producto sin foto. Es un SVG de trazo y no un archivo de
 * imagen: pesa unos bytes, se recolorea con el tema porque usa currentColor y
 * no hay que mantener un JPG mas en subidas.
 */
$sinFoto = static fn (): string =>
    '<span class="carta-foto carta-foto-vacia" aria-hidden="true">'
    . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">'
    . '<path d="M4 19.5V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v13.5"/>'
    . '<path d="M4 17l4.5-4.5a2 2 0 0 1 2.8 0L16 17"/>'
    . '<path d="M14 15l1.5-1.5a2 2 0 0 1 2.8 0L20 15"/>'
    . '<circle cx="9" cy="9" r="1.2"/></svg></span>';
?>
<article class="carta">
    <header class="carta-cabecera">
        <?php if (!empty($truck['logo'])) : ?>
            <img src="<?= Vista::e(Vista::url('/subidas/' . $truck['logo'])) ?>"
                 alt="<?= Vista::e($truck['nombre']) ?>" class="carta-logo">
        <?php endif; ?>

        <h1><?= Vista::e($truck['nombre']) ?></h1>

        <?php if (!empty($truck['descripcion'])) : ?>
            <p class="carta-descripcion"><?= Vista::e($truck['descripcion']) ?></p>
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
                <p class="carta-vigente">
                    <strong>Ahora en <?= Vista::e($vigente['nombre']) ?></strong>
                    <?php if (!empty($vigente['referencia'])) : ?>
                        · <?= Vista::e($vigente['referencia']) ?>
                    <?php endif; ?>
                    · hasta las <?= Vista::e(substr((string) $vigente['hora_fin'], 0, 5)) ?>
                </p>
            <?php endif; ?>

            <ul class="carta-paradas">
                <?php foreach ($agenda as $u) : ?>
                    <li class="carta-parada">
                        <div class="carta-texto">
                            <h3><?= Vista::e($u['nombre']) ?></h3>
                            <?php if (!empty($u['referencia'])) : ?>
                                <p><?= Vista::e($u['referencia']) ?></p>
                            <?php endif; ?>
                        </div>

                        <span class="carta-horario numerica">
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
        <?php // Barra de categorias. Son anclas de verdad: sin JavaScript el
              // salto al bloque funciona igual, porque cada seccion lleva su id. ?>
        <nav class="carta-barra" aria-label="Categorias de la carta" data-carta-barra>
            <ul>
                <?php foreach ($porCategoria as $id => $grupo) : ?>
                    <li>
                        <a href="#categoria-<?= Vista::e((string) $id) ?>">
                            <?= Vista::e($grupo['nombre']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php foreach ($porCategoria as $id => $grupo) : ?>
            <section class="carta-categoria" id="categoria-<?= Vista::e((string) $id) ?>">
                <h2><?= Vista::e($grupo['nombre']) ?></h2>

                <ul class="carta-lista">
                    <?php foreach ($grupo['productos'] as $p) : ?>
                        <?php $hay = (int) $p['disponible'] === 1; ?>
                        <li class="carta-item<?= $hay ? '' : ' carta-item-agotado' ?>">
                            <?php if (!empty($p['foto'])) : ?>
                                <img src="<?= Vista::e(Vista::url('/subidas/' . $p['foto'])) ?>"
                                     alt="<?= Vista::e($p['nombre']) ?>" class="carta-foto" loading="lazy">
                            <?php else : ?>
                                <?= $sinFoto() ?>
                            <?php endif; ?>

                            <div class="carta-texto">
                                <?php // Nombre y precio en la misma linea, y la
                                      // descripcion debajo a todo el ancho: a
                                      // 360 px un precio en su propia columna
                                      // deja el nombre partido en tres renglones. ?>
                                <div class="carta-fila">
                                    <h3><?= Vista::e($p['nombre']) ?></h3>

                                    <span class="carta-precio cifra">
                                        $ <?= Vista::e(number_format((float) $p['precio'], 0, ',', '.')) ?>
                                    </span>
                                </div>

                                <?php if (!empty($p['descripcion'])) : ?>
                                    <p><?= Vista::e($p['descripcion']) ?></p>
                                <?php endif; ?>

                                <?php // El color no decide solo: el agotado se
                                      // distingue tambien por la palabra. ?>
                                <?php if (!$hay) : ?>
                                    <span class="etiqueta etiqueta-pendiente">No disponible</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</article>
