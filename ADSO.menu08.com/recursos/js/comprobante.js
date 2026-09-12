/* comprobante.js — el dialogo de impresion del comprobante.
   Menu08 · carta, caja y produccion para food trucks.

   Solo lo carga /caja/comprobante/{id}. Hace dos cosas y nada mas: abrir el
   dialogo de impresion cuando la pagina termina de cargar y volver a abrirlo
   desde el boton de la barra.

   POR QUE AL 'load' Y NO ANTES. window.print() congela la pagina mientras el
   dialogo esta abierto: lanzado antes de que el navegador haya aplicado las
   hojas, la vista previa puede salir sin los estilos del papel. Esperar a que
   todo este pintado cuesta unos milisegundos y garantiza que lo que se ve en la
   vista previa es lo que sale del rollo.

   Y SE ABRE UNA SOLA VEZ. El evento 'load' no se repite al volver con el boton
   de atras —el navegador restaura la pagina de su cache sin recargarla—, asi
   que consultar un comprobante viejo no lanza una impresion que nadie pidio.

   Issue #19 · Fase 4 - Frontend */

'use strict';

(function () {
    var papel = document.querySelector('.comprobante');

    if (papel === null || typeof window.print !== 'function') {
        return;
    }

    var boton = document.querySelector('[data-comprobante-imprimir]');
    var abierto = false;

    function imprimir() {
        window.print();
    }

    /* El boton llega deshabilitado del servidor: sin este archivo no podria
       abrir nada. */
    if (boton !== null) {
        boton.disabled = false;
        boton.addEventListener('click', imprimir);
    }

    function abrirUnaVez() {
        if (abierto) {
            return;
        }

        abierto = true;

        /* Fuera del hilo de la carga: el dialogo bloquea, y bloquear dentro del
           propio evento deja al navegador a medio terminar su trabajo. */
        window.setTimeout(imprimir, 0);
    }

    if (document.readyState === 'complete') {
        abrirUnaVez();

        return;
    }

    window.addEventListener('load', abrirUnaVez);
}());
