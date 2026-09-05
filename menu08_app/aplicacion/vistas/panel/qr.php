<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Codigo QR de la carta. Es una pantalla del panel, no del cliente: la abre el
 * dueno con sesion para ver el codigo, comprobar a donde apunta y descargarlo
 * para mandarlo a imprimir.
 *
 * La carta que hay detras del codigo es carta/publica.php, que si es publica.
 *
 * @var array<string, mixed> $truck
 * @var string               $destino direccion publica que codifica el QR
 * @var string               $archivo nombre del PNG en subidas
 */
?>
<h1>Codigo QR de la carta</h1>

<div class="tarjeta tarjeta-elevada pila pila-4">
    <?php // El ?v= evita que el navegador siga mostrando el codigo anterior
          // desde su cache cuando el slug cambia y el PNG se regenera. ?>
    <img src="<?= Vista::e(Vista::url('/subidas/' . $archivo)) ?>?v=<?= Vista::e((string) time()) ?>"
         alt="Codigo QR de la carta de <?= Vista::e($truck['nombre']) ?>"
         width="280" height="280">

    <p class="tarjeta-texto">
        Apunta a <a href="<?= Vista::e($destino) ?>"><?= Vista::e($destino) ?></a>
    </p>

    <div class="tarjeta-pie">
        <a class="boton boton-relleno" href="<?= Vista::e(Vista::url('/panel/qr/descargar')) ?>">
            <svg class="boton-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            Descargar el PNG
        </a>

        <a class="boton boton-contorno" href="<?= Vista::e($destino) ?>">Ver la carta</a>
        <a class="boton boton-texto" href="<?= Vista::e(Vista::url('/panel')) ?>">Volver al panel</a>
    </div>
</div>

<?php // El aviso lleva icono ademas de color: md3.css advierte que la banda
      // calida de esta paleta es densa y el color solo no basta para distinguir
      // un estado de otro. ?>
<div class="aviso aviso-aviso" role="status">
    <svg class="aviso-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/>
        <path d="M12 10v3.5"/><path d="M12 17h.01"/>
    </svg>
    <p>
        Si cambia el slug del food truck, el codigo cambia y los adhesivos ya
        impresos dejan de servir. Verifique la direccion antes de mandar a imprimir.
    </p>
</div>
