<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Pie del marco y cierre del documento. Lo abre plantillas/cabecera.php.
 */
?>
    <footer class="pie sin-impresion">
        <div class="contenedor-ancho">
            <span>
                <a class="pie-marca" href="<?= Vista::e(Vista::url('/')) ?>"><span aria-hidden="true">🍴</span> Menu08</a>
                · carta, caja y producción para food trucks
            </span>
            <span>Prototipo del proyecto formativo · SENA ADSO ficha 3235887</span>
        </div>
    </footer>
</body>
</html>
