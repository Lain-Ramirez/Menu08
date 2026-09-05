<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Portada publica.
 *
 * Quien escribe la direccion casi siempre es un cliente con hambre, no personal
 * del truck: por eso lo primero y mas grande son las cartas, y el acceso queda
 * al final. Cada tarjeta entera es el enlace a la carta —no solo el nombre—,
 * para que en el telefono el blanco pulsable sea grande.
 *
 * @var list<array<string, mixed>> $trucks
 * @var array<string, mixed>|null  $usuario
 * @var string|null                $inicio   ruta del modulo del usuario en sesion
 */
$hm = static fn (mixed $h): string => substr((string) $h, 0, 5);
?>
<div class="pila pila-7">

    <header class="portada-encabezado">
        <h1 class="portada-titulo">Que hay para comer hoy</h1>
        <p class="portada-entrada">
            Menu08 reune las cartas de los food trucks de la ciudad. Abra una para ver que venden,
            a que precio y donde estan parados en este momento.
        </p>
    </header>

    <?php if ($usuario !== null && $inicio !== null) : ?>
        <?php // Quien ya entro no viene a mirar la carta: viene a trabajar. ?>
        <div class="aviso aviso-exito portada-sesion" role="status">
            <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
            </svg>
            <p>
                Entro como <strong><?= Vista::e($usuario['nombre']) ?></strong>.
            </p>
            <a class="boton boton-relleno" href="<?= Vista::e(Vista::url($inicio)) ?>">Ir a mi zona</a>
        </div>
    <?php endif; ?>

    <section class="pila pila-4" aria-labelledby="titulo-trucks">
        <h2 id="titulo-trucks" class="solo-lectores">Food trucks</h2>

        <?php if ($trucks === []) : ?>
            <div class="aviso aviso-aviso" role="status">
                <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"></path>
                    <path d="M12 10v3.5"></path><path d="M12 17h.01"></path>
                </svg>
                <p>
                    <strong>Atencion.</strong> Todavia no hay ningun food truck publicado.
                    Vuelva en un momento.
                </p>
            </div>
        <?php else : ?>
            <ul class="portada-lista">
                <?php foreach ($trucks as $t) : ?>
                    <li>
                        <?php // El enlace envuelve la tarjeta entera: en el telefono, el blanco
                              // pulsable es toda la ficha y no solo el nombre. ?>
                        <a class="tarjeta tarjeta-elevada portada-truck"
                           href="<?= Vista::e(Vista::url('/carta/' . $t['slug'])) ?>">
                            <?php if (!empty($t['logo'])) : ?>
                                <img class="portada-logo" width="64" height="64" decoding="async"
                                     src="<?= Vista::e(Vista::url('/subidas/' . $t['logo'])) ?>"
                                     alt="Logotipo de <?= Vista::e($t['nombre']) ?>">
                            <?php else : ?>
                                <span class="portada-logo portada-logo-vacio" aria-hidden="true">
                                    <?= Vista::e(mb_substr((string) $t['nombre'], 0, 1)) ?>
                                </span>
                            <?php endif; ?>

                            <span class="portada-truck-texto">
                                <span class="portada-truck-nombre"><?= Vista::e($t['nombre']) ?></span>

                                <?php if (!empty($t['descripcion'])) : ?>
                                    <span class="portada-truck-descripcion"><?= Vista::e($t['descripcion']) ?></span>
                                <?php endif; ?>

                                <?php // El dato que decide si el cliente se acerca. Si no hay
                                      // parada vigente se dice, en vez de callar. ?>
                                <?php if ($t['vigente'] !== null) : ?>
                                    <span class="etiqueta etiqueta-lista portada-parada">
                                        <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z"></path>
                                            <circle cx="12" cy="10" r="2.5"></circle>
                                        </svg>
                                        Ahora en <?= Vista::e($t['vigente']['nombre']) ?>
                                        · hasta las <?= Vista::e($hm($t['vigente']['hora_fin'])) ?>
                                    </span>
                                <?php else : ?>
                                    <span class="etiqueta etiqueta-entregada portada-parada">
                                        <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3.5 2"></path>
                                        </svg>
                                        Cerrado ahora · vea su agenda
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($t['ciudad'])) : ?>
                                    <span class="portada-truck-ciudad"><?= Vista::e($t['ciudad']) ?></span>
                                <?php endif; ?>
                            </span>

                            <span class="portada-truck-ir" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path>
                                </svg>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($usuario === null) : ?>
        <?php // El personal es la minoria de las visitas, asi que va al final y
              // sin competir con las cartas. ?>
        <section class="portada-personal">
            <div class="portada-personal-texto">
                <h2 class="portada-personal-titulo">Trabaja en un food truck</h2>
                <p class="texto-apagado texto-m">
                    Entre para administrar su carta, atender la caja o ver el tablero de produccion.
                </p>
            </div>
            <a class="boton boton-contorno" href="<?= Vista::e(Vista::url('/ingresar')) ?>">Ingresar</a>
        </section>
    <?php endif; ?>
</div>
