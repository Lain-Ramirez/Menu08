<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Catalogo del food truck. Alimenta la carta publica y el catalogo de CAJA.
 *
 * El filtro por categoria viaja en la URL como ?categoria=<id> y no como
 * formulario: asi la vista filtrada se puede compartir, marcar y recargar, y el
 * boton de volver del navegador hace lo que el usuario espera.
 *
 * @var list<array<string, mixed>> $productos
 * @var list<array<string, mixed>> $categorias
 * @var int|null                   $filtro
 */
$peso = static fn (mixed $n): string => '$ ' . number_format((float) $n, 0, ',', '.');
?>
<div class="pila pila-5">

    <header class="fila fila-entre">
        <h1>Productos</h1>
        <div class="fila fila-2">
            <a class="boton boton-relleno" href="<?= Vista::e(Vista::url('/panel/productos/nuevo')) ?>">
                <svg class="boton-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14"></path><path d="M5 12h14"></path>
                </svg>
                Nuevo producto
            </a>
            <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel')) ?>">Volver al panel</a>
        </div>
    </header>

    <?php if ($categorias !== []) : ?>
        <nav class="panel-filtro" aria-label="Filtrar por categoria">
            <span class="muestrario-rotulo" id="filtro-rotulo">Categoria</span>

            <ul class="fila fila-2 panel-filtro-lista" aria-labelledby="filtro-rotulo">
                <li>
                    <a class="etiqueta panel-filtro-opcion"
                       href="<?= Vista::e(Vista::url('/panel/productos')) ?>"
                       <?= $filtro === null ? 'aria-current="page"' : '' ?>>Todas</a>
                </li>

                <?php foreach ($categorias as $c) : ?>
                    <?php $activa = $filtro === (int) $c['id']; ?>
                    <li>
                        <a class="etiqueta panel-filtro-opcion"
                           href="<?= Vista::e(Vista::url('/panel/productos?categoria=' . (int) $c['id'])) ?>"
                           <?= $activa ? 'aria-current="page"' : '' ?>><?= Vista::e($c['nombre']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <?php if ($productos === []) : ?>
        <div class="aviso aviso-aviso" role="status">
            <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"></path>
                <path d="M12 10v3.5"></path><path d="M12 17h.01"></path>
            </svg>
            <p>
                <?php if ($filtro !== null) : ?>
                    <strong>Atencion.</strong> Esa categoria no tiene productos todavia.
                    <a href="<?= Vista::e(Vista::url('/panel/productos')) ?>">Ver todas</a>.
                <?php else : ?>
                    <strong>Atencion.</strong> Todavia no hay productos. La carta publica y CAJA se
                    veran vacias hasta que cree el primero.
                <?php endif; ?>
            </p>
        </div>
    <?php else : ?>
        <div class="tabla-envoltura">
            <table class="tabla tabla-apilable">
                <caption class="solo-lectores">
                    Productos del catalogo<?= $filtro === null ? '' : ', filtrados por categoria' ?>
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="columna-minima"><span class="solo-lectores">Foto</span></th>
                        <th scope="col">Producto</th>
                        <th scope="col">Categoria</th>
                        <th scope="col" class="cifra columna-minima">Precio</th>
                        <th scope="col" class="columna-minima">Disponibilidad</th>
                        <th scope="col" class="columna-minima"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p) : ?>
                    <?php $disponible = (int) $p['disponible'] === 1; ?>
                    <tr<?= $disponible ? '' : ' class="fila-inerte"' ?>>
                        <td data-etiqueta="Foto" class="columna-minima">
                            <?php if (!empty($p['foto'])) : ?>
                                <img class="panel-miniatura" width="56" height="56" loading="lazy" decoding="async"
                                     src="<?= Vista::e(Vista::url('/subidas/' . $p['foto'])) ?>"
                                     alt="Foto de <?= Vista::e($p['nombre']) ?>">
                            <?php else : ?>
                                <span class="panel-miniatura panel-miniatura-vacia" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                        <circle cx="8.5" cy="9.5" r="1.5"></circle>
                                        <path d="m4 17 5-5 4 4 2-2 5 5"></path>
                                    </svg>
                                </span>
                                <span class="solo-lectores">Sin foto</span>
                            <?php endif; ?>
                        </td>
                        <td data-etiqueta="Producto"><?= Vista::e($p['nombre']) ?></td>
                        <td data-etiqueta="Categoria"><?= Vista::e($p['categoria']) ?></td>
                        <td data-etiqueta="Precio" class="cifra columna-minima"><?= Vista::e($peso($p['precio'])) ?></td>
                        <td data-etiqueta="Disponibilidad" class="columna-minima">
                            <?php if ($disponible) : ?>
                                <span class="etiqueta etiqueta-lista">
                                    <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
                                    </svg>
                                    Disponible
                                </span>
                            <?php else : ?>
                                <span class="etiqueta etiqueta-pendiente">
                                    <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"></circle><path d="m8.5 15.5 7-7"></path>
                                    </svg>
                                    Agotado
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-etiqueta="Acciones" class="columna-minima">
                            <div class="tabla-acciones">
                                <a class="boton boton-contorno"
                                   href="<?= Vista::e(Vista::url('/panel/productos/' . $p['id'])) ?>">Editar</a>

                                <form method="post" action="<?= Vista::e(Vista::url('/panel/productos/disponibilidad')) ?>">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="boton boton-texto" type="submit"><?= $disponible ? 'Agotar' : 'Reponer' ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
