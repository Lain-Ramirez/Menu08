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
 * @var array<string, mixed>|null                     $vigente  parada abierta ahora
 * @var array<string, mixed>|null                     $proxima  la siguiente que abre, si no hay vigente
 * @var array<int, string>                            $dias
 */

/** La base devuelve TIME como 18:00:00 y en la carta solo interesa 18:00. */
$hm = static fn (mixed $hora): string => substr((string) $hora, 0, 5);

/**
 * Cuando abre la proxima parada, dicho como lo diria alguien: hoy, manana o el
 * dia por su nombre. La fecha la calcula el modelo en `abre_en`; aqui solo se
 * traduce a palabras, que es lo unico que el cliente en la fila necesita.
 */
$cuando = static function (string $abreEn) use ($dias): string {
    $marca = strtotime($abreEn);

    if ($marca === false) {
        return '';
    }

    $faltan = (int) ((strtotime(date('Y-m-d', $marca)) - strtotime(date('Y-m-d'))) / 86400);

    if ($faltan <= 0) {
        return 'hoy';
    }

    if ($faltan === 1) {
        return 'mañana';
    }

    return 'el ' . mb_strtolower($dias[(int) date('N', $marca)] ?? '');
};

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

    <?php // ------------------------------------------ donde estamos hoy --
          // Es la pregunta que trae al cliente a esta pantalla: la abre haciendo
          // fila, desde el telefono, para saber si el truck esta donde cree.
          // Por eso encabeza la carta, antes de los productos, y por eso nunca
          // se queda vacia: sin parada abierta, dice cuando vuelve. ?>
    <?php if ($vigente !== null || $proxima !== null) : ?>
        <section class="carta-agenda" aria-labelledby="carta-donde">
            <h2 id="carta-donde">Dónde estamos</h2>

            <?php $destacada = $vigente ?? $proxima; ?>

            <div class="carta-ahora<?= $vigente === null ? ' carta-ahora-cerrado' : '' ?>">
                <?php if ($vigente !== null) : ?>
                    <span class="etiqueta etiqueta-lista">Abierto ahora</span>
                <?php else : ?>
                    <span class="etiqueta etiqueta-pendiente">Cerrado ahora</span>
                <?php endif; ?>

                <p class="carta-ahora-punto"><?= Vista::e($destacada['nombre']) ?></p>

                <?php if (!empty($destacada['referencia'])) : ?>
                    <p class="carta-ahora-referencia"><?= Vista::e($destacada['referencia']) ?></p>
                <?php endif; ?>

                <?php // El titular del horario no repite el dia cuando esta abierto: una
                      // jornada nocturna empezo AYER, y decir «hoy de 18:00 a 01:00» a las
                      // 00:30 seria mentira. Lo que importa a esa hora es hasta cuando. ?>
                <p class="carta-ahora-horario numerica">
                    <?php if ($vigente !== null) : ?>
                        Hasta las <strong><?= Vista::e($hm($vigente['hora_fin'])) ?></strong>
                    <?php else : ?>
                        Abre <?= Vista::e($cuando((string) $proxima['abre_en'])) ?>
                        a las <strong><?= Vista::e($hm($proxima['hora_inicio'])) ?></strong>
                    <?php endif; ?>
                </p>

                <p class="carta-ahora-franja numerica">
                    <?= Vista::e($dias[(int) $destacada['dia_semana']] ?? '') ?>
                    de <?= Vista::e($hm($destacada['hora_inicio'])) ?>
                    a <?= Vista::e($hm($destacada['hora_fin'])) ?>
                    <?php if ($hm($destacada['hora_fin']) <= $hm($destacada['hora_inicio'])) : ?>
                        · cierra al día siguiente
                    <?php endif; ?>
                </p>
            </div>

            <?php // El resto de la semana, resumido: dia, punto y franja. Sin la parada
                  // que ya va arriba, que se repetiria dos veces en la misma pantalla. ?>
            <?php $resto = array_values(array_filter(
                $agenda,
                static fn (array $u): bool => (int) $u['id'] !== (int) $destacada['id']
            )); ?>

            <?php if ($resto !== []) : ?>
                <h3 class="carta-agenda-titulo">El resto de la semana</h3>

                <ul class="carta-paradas">
                    <?php foreach ($resto as $u) : ?>
                        <li class="carta-parada">
                            <span class="carta-parada-dia"><?= Vista::e(mb_substr($dias[(int) $u['dia_semana']] ?? '', 0, 3)) ?></span>
                            <span class="carta-parada-punto"><?= Vista::e($u['nombre']) ?></span>
                            <span class="carta-parada-horario numerica">
                                <?= Vista::e($hm($u['hora_inicio'])) ?>–<?= Vista::e($hm($u['hora_fin'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
