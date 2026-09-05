<?php

declare(strict_types=1);

use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Vista;

/**
 * Plantilla comun. Ya no maqueta nada: encadena las tres piezas del marco
 * —cabecera, navegacion y pie— y coloca entre ellas el contenido de la vista.
 *
 * $contenido ya viene renderizado y escapado por su vista, por eso es lo unico
 * que se imprime sin volver a escapar.
 *
 * @var string $titulo
 * @var string $contenido
 */

/**
 * Icono de cada tipo de mensaje. md3.css advierte que la banda calida queda
 * densa, asi que un aviso nunca se distingue solo por el color: lleva tambien
 * icono y palabra. Los tres unicos tipos son los que emite Sesion::mensaje().
 */
$iconoAviso = static function (string $tipo): string {
    $trazos = [
        'exito' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'aviso' => '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 10v3.5"/><path d="M12 17h.01"/>',
        'error' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5"/><path d="M12 16h.01"/>',
    ];

    return sprintf(
        '<svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $trazos[$tipo]
    );
};

echo Vista::renderizar('plantillas/cabecera', ['titulo' => $titulo]);

// La navegacion es del panel: sin sesion no hay modulos que ofrecer.
if (Sesion::autenticado()) {
    echo Vista::renderizar('plantillas/navegacion');
}
?>
    <main class="contenido pila pila-5">
        <?php $mensajes = Sesion::sacarMensajes(); ?>

        <?php if ($mensajes !== []) : ?>
            <div class="pila pila-3 sin-impresion">
                <?php foreach ($mensajes as $mensaje) : ?>
                    <?php
                    // Un tipo desconocido caeria en una clase sin estilo: se trata
                    // como aviso, que es el valor por omision de Sesion::mensaje().
                    $tipo = in_array($mensaje['tipo'], ['exito', 'aviso', 'error'], true)
                        ? $mensaje['tipo']
                        : 'aviso';
                    ?>
                    <div class="aviso aviso-<?= Vista::e($tipo) ?>"
                         role="<?= $tipo === 'error' ? 'alert' : 'status' ?>">
                        <?= $iconoAviso($tipo) ?>
                        <p><?= Vista::e($mensaje['texto']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $contenido ?>
    </main>
<?= Vista::renderizar('plantillas/pie') ?>
