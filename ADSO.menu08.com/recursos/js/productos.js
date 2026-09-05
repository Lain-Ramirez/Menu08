/* productos.js — previsualizacion de la foto del producto.
   Menu08 · carta, caja y produccion para food trucks.

   Muestra la imagen elegida antes de enviar el formulario y avisa cuando el
   archivo no es una imagen admitida o pasa del tamaño permitido.

   Los limites son los MISMOS que aplica GestorImagenes en el servidor
   —image/jpeg, image/png, image/webp y 2 MB—. Esta comprobacion no sustituye a
   la del servidor, que es la que manda: aqui solo evita que el usuario suba
   dos megas para que se los rechacen despues.

   Sin JavaScript el campo de archivo funciona igual; lo unico que se pierde es
   ver la foto antes de enviarla.

   Issue #16 · Fase 4 - Frontend */

'use strict';

(function () {
    /* Espejo de GestorImagenes::TIPOS y de subidas.tamano_maximo. */
    var TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
    var MAXIMO = 2 * 1024 * 1024;

    function texto(bytes) {
        return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
    }

    function iniciar(campo) {
        if (campo.dataset.fotoEnlazada === '1') {
            return;
        }

        campo.dataset.fotoEnlazada = '1';

        var vista = document.getElementById(campo.getAttribute('data-vista-previa'));
        var apoyo = document.getElementById(campo.getAttribute('aria-describedby'));
        var apoyoOriginal = apoyo === null ? '' : apoyo.textContent;
        var lector = null;

        function marcar(mensaje) {
            var envoltura = campo.closest('.campo');

            if (envoltura !== null) {
                envoltura.classList.toggle('campo-error', mensaje !== '');
            }

            campo.setAttribute('aria-invalid', mensaje !== '' ? 'true' : 'false');

            if (apoyo !== null) {
                apoyo.textContent = mensaje !== '' ? mensaje : apoyoOriginal;
            }
        }

        function limpiar() {
            if (vista === null) {
                return;
            }

            vista.hidden = true;
            vista.removeAttribute('src');
        }

        campo.addEventListener('change', function () {
            /* Una lectura anterior en curso se cancela: si no, dos elecciones
               seguidas pueden pintar la imagen vieja sobre la nueva. */
            if (lector !== null) {
                lector.abort();
                lector = null;
            }

            var archivo = campo.files && campo.files[0];

            if (!archivo) {
                marcar('');
                limpiar();

                return;
            }

            if (TIPOS.indexOf(archivo.type) === -1) {
                marcar('Ese archivo no es una imagen JPG, PNG o WEBP.');
                limpiar();
                campo.value = '';

                return;
            }

            if (archivo.size > MAXIMO) {
                marcar('La imagen pesa ' + texto(archivo.size) + ' y el maximo es ' + texto(MAXIMO) + '.');
                limpiar();
                campo.value = '';

                return;
            }

            marcar('');

            if (vista === null || typeof FileReader === 'undefined') {
                return;
            }

            lector = new FileReader();

            lector.addEventListener('load', function (e) {
                vista.src = e.target.result;
                vista.hidden = false;
                lector = null;
            });

            lector.addEventListener('error', function () {
                /* No se pudo leer, pero el archivo puede ser valido: el servidor
                   decide. Solo se retira la vista previa. */
                limpiar();
                lector = null;
            });

            lector.readAsDataURL(archivo);
        });
    }

    function enlazar(raiz) {
        var campos = (raiz || document).querySelectorAll('[data-vista-previa]');
        var i;

        for (i = 0; i < campos.length; i += 1) {
            iniciar(campos[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { enlazar(); });
    } else {
        enlazar();
    }
}());
