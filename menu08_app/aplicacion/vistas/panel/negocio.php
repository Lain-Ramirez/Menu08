<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Datos del negocio. Alimenta la cabecera de la carta publica.
 *
 * NO hay campo de direccion, y no es un olvido: un food truck no tiene local
 * fijo. Los puntos donde para se administran en /panel/ubicaciones, que es la
 * agenda semanal. Aqui van los datos que no cambian de un dia para otro.
 *
 * Los campos son exactamente los que valida PanelControlador::guardar().
 *
 * @var array<string, mixed>  $truck
 * @var array<string, string> $errores
 */

/** Marca el campo en error y devuelve su mensaje bajo el control. */
$apoyo = static function (string $campo, string $ayuda = '') use ($errores): string {
    $hay = isset($errores[$campo]);
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

/** Clases y atributos del campo segun tenga error o no. */
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
        <h1>Datos del negocio</h1>
        <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel')) ?>">Volver al panel</a>
    </header>

    <form class="pila pila-5 panel-formulario" method="post" enctype="multipart/form-data"
          action="<?= Vista::e(Vista::url('/panel/food-truck')) ?>">
        <?= Csrf::campo() ?>

        <section class="tarjeta tarjeta-contorno panel-grupo">
            <h2 class="tarjeta-titulo">Identidad</h2>

            <div class="rejilla rejilla-2">
                <div class="campo<?= $clase('nombre') ?>">
                    <input class="campo-control" type="text" id="nombre" name="nombre" maxlength="120"
                           required placeholder=" " value="<?= Vista::e($truck['nombre'] ?? '') ?>"<?= $aria('nombre', 'Como se llama el food truck.') ?>>
                    <label class="campo-etiqueta" for="nombre">Nombre <abbr class="campo-obligatorio" title="obligatorio">*</abbr></label>
                    <?= $apoyo('nombre', 'Como se llama el food truck.') ?>
                </div>

                <div class="campo<?= $clase('slug') ?>">
                    <input class="campo-control" type="text" id="slug" name="slug" maxlength="80"
                           placeholder=" " value="<?= Vista::e($truck['slug'] ?? '') ?>"<?= $aria('slug', 'Minusculas y guiones. Vacio: se genera del nombre.') ?>>
                    <label class="campo-etiqueta" for="slug">Enlace de la carta</label>
                    <?= $apoyo('slug', 'Minusculas y guiones. Vacio: se genera del nombre.') ?>
                </div>
            </div>

            <div class="campo<?= $clase('descripcion') ?>">
                <textarea class="campo-control campo-area" id="descripcion" name="descripcion" rows="3"
                          maxlength="500" placeholder=" "<?= $aria('descripcion', 'Una linea sobre la comida. Sale en la carta.') ?>><?= Vista::e($truck['descripcion'] ?? '') ?></textarea>
                <label class="campo-etiqueta" for="descripcion">Descripcion</label>
                <?= $apoyo('descripcion', 'Una linea sobre la comida. Sale en la carta.') ?>
            </div>
        </section>

        <section class="tarjeta tarjeta-contorno panel-grupo">
            <h2 class="tarjeta-titulo">Contacto</h2>
            <p class="tarjeta-texto">
                Sale en la carta publica. La direccion no va aqui: los puntos donde para el truck se
                programan en <a href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">la agenda de paradas</a>.
            </p>

            <div class="rejilla rejilla-2">
                <div class="campo<?= $clase('ciudad') ?>">
                    <input class="campo-control" type="text" id="ciudad" name="ciudad" maxlength="80"
                           placeholder=" " value="<?= Vista::e($truck['ciudad'] ?? '') ?>"<?= $aria('ciudad') ?>>
                    <label class="campo-etiqueta" for="ciudad">Ciudad</label>
                    <?= $apoyo('ciudad') ?>
                </div>

                <div class="campo<?= $clase('telefono') ?>">
                    <input class="campo-control" type="tel" id="telefono" name="telefono" maxlength="40"
                           placeholder=" " value="<?= Vista::e($truck['telefono'] ?? '') ?>"<?= $aria('telefono') ?>>
                    <label class="campo-etiqueta" for="telefono">Telefono</label>
                    <?= $apoyo('telefono') ?>
                </div>

                <div class="campo<?= $clase('whatsapp') ?>">
                    <input class="campo-control" type="tel" id="whatsapp" name="whatsapp" maxlength="40"
                           placeholder=" " value="<?= Vista::e($truck['whatsapp'] ?? '') ?>"<?= $aria('whatsapp', 'Se convierte en un enlace directo al chat.') ?>>
                    <label class="campo-etiqueta" for="whatsapp">WhatsApp</label>
                    <?= $apoyo('whatsapp', 'Se convierte en un enlace directo al chat.') ?>
                </div>

                <div class="campo<?= $clase('instagram') ?>">
                    <input class="campo-control" type="text" id="instagram" name="instagram" maxlength="80"
                           placeholder=" " value="<?= Vista::e($truck['instagram'] ?? '') ?>"<?= $aria('instagram') ?>>
                    <label class="campo-etiqueta" for="instagram">Instagram</label>
                    <?= $apoyo('instagram') ?>
                </div>
            </div>
        </section>

        <section class="tarjeta tarjeta-contorno panel-grupo">
            <h2 class="tarjeta-titulo">Logotipo</h2>

            <div class="panel-medio">
                <?php if (!empty($truck['logo'])) : ?>
                    <img class="panel-logo" width="96" height="96" decoding="async" src="<?= Vista::e(Vista::url('/subidas/' . $truck['logo'])) ?>"
                         alt="Logotipo actual de <?= Vista::e($truck['nombre'] ?? 'el negocio') ?>">
                <?php endif; ?>

                <div class="campo<?= $clase('logo') ?>">
                    <input class="campo-control campo-archivo" type="file" id="logo" name="logo"
                           accept="image/jpeg,image/png,image/webp"
                           data-vista-previa="logo-previa"<?= $aria('logo', 'JPG, PNG o WEBP, hasta 2 MB. Vacio: conserva el actual.') ?>>
                    <label class="campo-etiqueta campo-etiqueta-fija" for="logo">Cambiar logotipo</label>
                    <?= $apoyo('logo', 'JPG, PNG o WEBP, hasta 2 MB. Vacio: conserva el actual.') ?>
                </div>

                <img class="panel-logo panel-previa" id="logo-previa" width="96" height="96" alt="Vista previa del logotipo elegido" hidden>
            </div>
        </section>

        <div class="fila">
            <button class="boton boton-relleno" type="submit">Guardar</button>
            <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel')) ?>">Cancelar</a>
        </div>
    </form>
</div>

<script src="<?= Vista::e(Vista::url('/recursos/js/productos.js')) ?>" defer></script>
