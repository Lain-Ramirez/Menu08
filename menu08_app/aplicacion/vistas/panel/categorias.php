<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Categorias de la carta: el listado y el formulario de alta y edicion en la
 * misma pantalla, porque son pocas y se crean de una sentada.
 *
 * $edita a null significa alta; con valor, edicion de esa categoria.
 *
 * @var list<array<string, mixed>> $categorias
 * @var array<string, mixed>|null  $edita
 * @var array<string, string>      $errores
 */
$edicion = $edita !== null;

$apoyo = static function (string $campo, string $ayuda = '') use ($errores): string {
    $hay   = isset($errores[$campo]);
    $texto = $hay ? $errores[$campo] : $ayuda;

    if ($texto === '') {
        return '';
    }

    return sprintf(
        '<span class="campo-apoyo" id="%s-apoyo">%s%s</span>',
        Vista::e($campo),
        $hay
            ? '<svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
              . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
              . '<circle cx="12" cy="12" r="10"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>'
            : '',
        Vista::e($texto)
    );
};

$clase = static fn (string $c): string => isset($errores[$c]) ? ' campo-error' : '';

/**
 * Atributos del control. El aria-describedby solo se emite cuando de verdad
 * habra un <span> de apoyo que describir: si se pusiera siempre, los campos sin
 * error ni ayuda apuntarian a un id inexistente y el lector de pantalla
 * buscaria una descripcion que no esta. La condicion es la misma que usa
 * $apoyo() para decidir si emite algo.
 */
$aria = static function (string $c, string $ayuda = '') use ($errores): string {
    $hay = isset($errores[$c]);

    if (!$hay && $ayuda === '') {
        return '';
    }

    return sprintf(
        '%s aria-describedby="%s-apoyo"',
        $hay ? ' aria-invalid="true"' : '',
        Vista::e($c)
    );
};
?>
<div class="pila pila-5">

    <header class="fila fila-entre">
        <h1>Categorias</h1>
        <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel')) ?>">Volver al panel</a>
    </header>

    <section class="tarjeta tarjeta-contorno panel-grupo panel-formulario" id="formulario">
        <h2 class="tarjeta-titulo"><?= $edicion ? 'Editar categoria' : 'Nueva categoria' ?></h2>

        <form class="pila pila-4" method="post" action="<?= Vista::e(Vista::url('/panel/categorias')) ?>">
            <?= Csrf::campo() ?>
            <input type="hidden" name="id" value="<?= (int) ($edita['id'] ?? 0) ?>">

            <div class="panel-formulario-linea">
                <div class="campo campo-sobre-contenedor<?= $clase('nombre') ?>">
                    <input class="campo-control" type="text" id="nombre" name="nombre" maxlength="90"
                           required placeholder=" " value="<?= Vista::e($edita['nombre'] ?? '') ?>"<?= $aria('nombre') ?>>
                    <label class="campo-etiqueta" for="nombre">Nombre <abbr class="campo-obligatorio" title="obligatorio">*</abbr></label>
                    <?= $apoyo('nombre', 'Como aparece el bloque en la carta.') ?>
                </div>

                <div class="campo campo-sobre-contenedor panel-campo-corto<?= $clase('orden') ?>">
                    <input class="campo-control" type="number" id="orden" name="orden" min="0" max="999"
                           placeholder=" " value="<?= (int) ($edita['orden'] ?? 0) ?>"<?= $aria('orden') ?>>
                    <label class="campo-etiqueta" for="orden">Orden</label>
                    <?= $apoyo('orden', 'Menor va antes.') ?>
                </div>
            </div>

            <div class="fila fila-2">
                <button class="boton boton-relleno" type="submit"><?= $edicion ? 'Guardar' : 'Crear' ?></button>
                <?php if ($edicion) : ?>
                    <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($categorias === []) : ?>
        <div class="aviso aviso-aviso" role="status">
            <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"></path>
                <path d="M12 10v3.5"></path><path d="M12 17h.01"></path>
            </svg>
            <p>
                <strong>Atencion.</strong> Todavia no hay categorias. La carta publica se vera vacia
                hasta que cree la primera.
            </p>
        </div>
    <?php else : ?>
        <div class="tabla-envoltura">
            <table class="tabla tabla-apilable">
                <caption class="solo-lectores">Categorias de la carta</caption>
                <thead>
                    <tr>
                        <th scope="col" class="cifra columna-minima">Orden</th>
                        <th scope="col">Nombre</th>
                        <th scope="col" class="cifra columna-minima">Productos</th>
                        <th scope="col" class="columna-minima">Estado</th>
                        <th scope="col" class="columna-minima"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categorias as $c) : ?>
                    <?php $activa = (int) $c['activo'] === 1; ?>
                    <tr<?= $activa ? '' : ' class="fila-inerte"' ?>>
                        <td data-etiqueta="Orden" class="cifra columna-minima"><?= (int) $c['orden'] ?></td>
                        <td data-etiqueta="Nombre"><?= Vista::e($c['nombre']) ?></td>
                        <td data-etiqueta="Productos" class="cifra columna-minima"><?= (int) $c['productos'] ?></td>
                        <td data-etiqueta="Estado" class="columna-minima">
                            <?php if ($activa) : ?>
                                <span class="etiqueta etiqueta-lista">
                                    <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
                                    </svg>
                                    Activa
                                </span>
                            <?php else : ?>
                                <span class="etiqueta etiqueta-pendiente">
                                    <svg class="etiqueta-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9"></circle><path d="m8.5 15.5 7-7"></path>
                                    </svg>
                                    Inactiva
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-etiqueta="Acciones" class="columna-minima">
                            <div class="tabla-acciones">
                                <a class="boton boton-contorno"
                                   href="<?= Vista::e(Vista::url('/panel/categorias/' . $c['id'])) ?>#formulario">Editar</a>

                                <?php // Desactivar una categoria retira sus productos de la carta
                                      // publica y de CAJA: por eso se pregunta antes. ?>
                                <form method="post" action="<?= Vista::e(Vista::url('/panel/categorias/estado')) ?>"
                                      <?php if ($activa) : ?>
                                      data-confirmar="Al desactivar &quot;<?= Vista::e($c['nombre']) ?>&quot; sus <?= (int) $c['productos'] ?> productos dejan de verse en la carta publica y en CAJA."
                                      data-confirmar-titulo="Desactivar la categoria"
                                      data-confirmar-aceptar="Desactivar"
                                      data-confirmar-peligro
                                      <?php endif; ?>>
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="boton boton-texto" type="submit"><?= $activa ? 'Desactivar' : 'Activar' ?></button>
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
