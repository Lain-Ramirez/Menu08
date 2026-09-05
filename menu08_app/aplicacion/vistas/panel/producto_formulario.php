<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Alta y edicion de un producto. Aqui vive la previsualizacion de la foto:
 * el campo declara data-vista-previa y productos.js pinta la imagen elegida
 * antes de enviar, ademas de avisar si el archivo no es una imagen admitida o
 * pasa de 2 MB.
 *
 * @var array<string, mixed>|null  $producto
 * @var list<array<string, mixed>> $categorias
 * @var array<string, string>      $errores
 */
$id = (int) ($producto['id'] ?? 0);

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
        <h1><?= $id > 0 ? 'Editar producto' : 'Nuevo producto' ?></h1>
        <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel/productos')) ?>">Volver al catalogo</a>
    </header>

    <?php if ($categorias === []) : ?>
        <div class="aviso aviso-aviso" role="status">
            <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"></path>
                <path d="M12 10v3.5"></path><path d="M12 17h.01"></path>
            </svg>
            <p>
                <strong>Atencion.</strong> Antes de crear un producto hace falta al menos una categoria.
                <a href="<?= Vista::e(Vista::url('/panel/categorias')) ?>">Crear una</a>.
            </p>
        </div>
    <?php else : ?>
        <form class="pila pila-5 panel-formulario" method="post" enctype="multipart/form-data"
              action="<?= Vista::e(Vista::url('/panel/productos')) ?>">
            <?= Csrf::campo() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <section class="tarjeta tarjeta-contorno panel-grupo">
                <h2 class="tarjeta-titulo">Que es</h2>

                <div class="rejilla rejilla-2">
                    <div class="campo<?= $clase('nombre') ?>">
                        <input class="campo-control" type="text" id="nombre" name="nombre" maxlength="120"
                               required placeholder=" " value="<?= Vista::e($producto['nombre'] ?? '') ?>"<?= $aria('nombre') ?>>
                        <label class="campo-etiqueta" for="nombre">Nombre <abbr class="campo-obligatorio" title="obligatorio">*</abbr></label>
                        <?= $apoyo('nombre', 'Como lo pide el cliente en la ventanilla.') ?>
                    </div>

                    <div class="campo campo-con-valor<?= $clase('categoria_id') ?>">
                        <select class="campo-control" id="categoria_id" name="categoria_id" required<?= $aria('categoria_id') ?>>
                            <?php foreach ($categorias as $c) : ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= (int) ($producto['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= Vista::e($c['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label class="campo-etiqueta" for="categoria_id">Categoria <abbr class="campo-obligatorio" title="obligatorio">*</abbr></label>
                        <?= $apoyo('categoria_id', 'El bloque de la carta donde aparece.') ?>
                    </div>
                </div>

                <div class="campo<?= $clase('descripcion') ?>">
                    <textarea class="campo-control campo-area" id="descripcion" name="descripcion" rows="3"
                              maxlength="400" placeholder=" "<?= $aria('descripcion') ?>><?= Vista::e($producto['descripcion'] ?? '') ?></textarea>
                    <label class="campo-etiqueta" for="descripcion">Descripcion</label>
                    <?= $apoyo('descripcion', 'Los ingredientes, en una linea.') ?>
                </div>
            </section>

            <section class="tarjeta tarjeta-contorno panel-grupo">
                <h2 class="tarjeta-titulo">Precio y orden</h2>

                <div class="rejilla rejilla-2">
                    <div class="campo<?= $clase('precio') ?>">
                        <input class="campo-control numerica" type="text" id="precio" name="precio"
                               inputmode="decimal" required placeholder=" "
                               value="<?= Vista::e($producto['precio'] ?? '') ?>"<?= $aria('precio') ?>>
                        <label class="campo-etiqueta" for="precio">Precio <abbr class="campo-obligatorio" title="obligatorio">*</abbr></label>
                        <?= $apoyo('precio', 'En pesos, sin puntos ni simbolo.') ?>
                    </div>

                    <div class="campo<?= $clase('orden') ?>">
                        <input class="campo-control numerica" type="number" id="orden" name="orden"
                               min="0" max="999" placeholder=" "
                               value="<?= (int) ($producto['orden'] ?? 0) ?>"<?= $aria('orden') ?>>
                        <label class="campo-etiqueta" for="orden">Orden dentro de la categoria</label>
                        <?= $apoyo('orden', 'Menor va antes.') ?>
                    </div>
                </div>

                <label class="panel-casilla" for="disponible">
                    <input type="checkbox" id="disponible" name="disponible" value="1"
                        <?= ($producto === null || (int) ($producto['disponible'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <span>
                        Disponible en la carta
                        <span class="texto-p texto-apagado">Sin marcar se retira de la carta publica y de CAJA.</span>
                    </span>
                </label>
            </section>

            <section class="tarjeta tarjeta-contorno panel-grupo">
                <h2 class="tarjeta-titulo">Foto</h2>

                <div class="panel-medio">
                    <?php if (!empty($producto['foto'])) : ?>
                        <img class="panel-foto" width="96" height="96" decoding="async" src="<?= Vista::e(Vista::url('/subidas/' . $producto['foto'])) ?>"
                             alt="Foto actual de <?= Vista::e($producto['nombre'] ?? 'el producto') ?>">
                    <?php endif; ?>

                    <div class="campo<?= $clase('foto') ?>">
                        <input class="campo-control campo-archivo" type="file" id="foto" name="foto"
                               accept="image/jpeg,image/png,image/webp"
                               data-vista-previa="foto-previa"<?= $aria('foto') ?>>
                        <label class="campo-etiqueta campo-etiqueta-fija" for="foto">
                            <?= empty($producto['foto']) ? 'Elegir foto' : 'Cambiar foto' ?>
                        </label>
                        <?= $apoyo('foto', 'JPG, PNG o WEBP, hasta 2 MB. Vacio: conserva la actual.') ?>
                    </div>

                    <img class="panel-foto panel-previa" id="foto-previa" width="96" height="96" alt="Vista previa de la foto elegida" hidden>
                </div>
            </section>

            <div class="fila">
                <button class="boton boton-relleno" type="submit">Guardar</button>
                <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel/productos')) ?>">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="<?= Vista::e(Vista::url('/recursos/js/productos.js')) ?>" defer></script>
