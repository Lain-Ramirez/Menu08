/* validacion.js — comprobacion de formularios en el navegador.
   Menu08 · carta, caja y produccion para food trucks.

   Modulo generico, no de una pantalla: lo engancha cualquier formulario que
   lleve data-validar. Sin bibliotecas y sin paso de compilacion, igual que
   interfaz.js.

   NO SUSTITUYE AL SERVIDOR, Y ESO NO ES UNA FORMALIDAD. Quien manda es
   Validador, en PHP: es lo unico que ve una peticion hecha con curl. Esto
   adelanta el mismo rechazo unos segundos antes, para que el formulario no
   tenga que viajar entero, volver y repintarse para decir que falta el punto.
   Si este archivo no carga, el formulario se envia y el servidor contesta
   exactamente lo mismo.

   LOS MENSAJES NO ESTAN AQUI. Cada campo trae el suyo en un atributo, escrito
   en la vista al lado del control. Asi el mensaje del navegador y el del
   servidor se leen juntos al maquetar —y se nota enseguida si dicen cosas
   distintas—, y este archivo no lleva ni una palabra en castellano que pueda
   quedarse desfasada.

     <form data-validar novalidate>
       <div class="campo">
         <input id="punto" required
                data-validar-requerido="El punto es obligatorio."
                maxlength="120"
                data-validar-largo="El punto no puede pasar de 120 caracteres."
                data-validar-patron="^..."
                data-validar-formato="La hora debe tener el formato HH:MM.">
       </div>
       <span class="error-campo" data-validar-error="punto" hidden></span>

   El mensaje se pinta en el <span> que declara data-validar-error con el id del
   control, que es el MISMO nodo que usa el servidor cuando rechaza: no hay dos
   sitios donde pueda salir un error del mismo campo.

   Issue #37 · Fase 4 - Frontend */

'use strict';

(function () {
    /* ------------------------------------------------------------- utiles */

    function envoltura(control) {
        return control.closest ? control.closest('.campo') : null;
    }

    function nodoError(formulario, control) {
        return formulario.querySelector('[data-validar-error="' + control.id + '"]');
    }

    /** El primer reproche que merece el valor actual, o cadena vacia.

        El orden importa: vacio primero, porque un campo vacio no tiene formato
        que comprobar, y decirle a alguien que «no cumple el formato» cuando lo
        que pasa es que no escribio nada es la peor de las dos respuestas. */
    function reproche(control) {
        var valor = (control.value || '').trim();
        var requerido = control.getAttribute('data-validar-requerido');
        var largo = control.getAttribute('data-validar-largo');
        var patron = control.getAttribute('data-validar-patron');
        var formato = control.getAttribute('data-validar-formato');
        var maximo = Number(control.getAttribute('maxlength'));

        if (valor === '') {
            /* Un <input type="time"> con algo a medio escribir devuelve valor
               vacio y marca badInput: para el navegador no hay valor, pero para
               quien lo escribio si lo hay. Merece el mensaje de formato, no el
               de obligatorio. */
            if (formato && control.validity && control.validity.badInput) {
                return formato;
            }

            return requerido || '';
        }

        if (largo && isFinite(maximo) && maximo > 0 && valor.length > maximo) {
            return largo;
        }

        if (patron && formato && !(new RegExp(patron)).test(valor)) {
            return formato;
        }

        return '';
    }

    function marcar(formulario, control, mensaje) {
        var caja = envoltura(control);
        var error = nodoError(formulario, control);

        if (caja !== null) {
            caja.classList.toggle('campo-error', mensaje !== '');
        }

        control.setAttribute('aria-invalid', mensaje !== '' ? 'true' : 'false');

        if (error !== null) {
            error.textContent = mensaje;
            error.hidden = mensaje === '';
        }
    }

    /* ------------------------------------------------------------ enganche */

    function enlazar(formulario) {
        if (formulario.dataset.validarEnlazado === '1') {
            return;
        }

        formulario.dataset.validarEnlazado = '1';

        function controles() {
            return formulario.querySelectorAll(
                '[data-validar-requerido], [data-validar-patron], [data-validar-largo]'
            );
        }

        /* Mientras se corrige, el reproche se retira en cuanto deja de ser
           cierto; pero no se estrena uno nuevo tecleando. Marcar «obligatorio»
           en la primera letra que alguien borra es pelearse con quien escribe. */
        function alEscribir(control) {
            return function () {
                if (control.getAttribute('aria-invalid') !== 'true') {
                    return;
                }

                if (reproche(control) === '') {
                    marcar(formulario, control, '');
                }
            };
        }

        var lista = controles();
        var i;

        for (i = 0; i < lista.length; i += 1) {
            lista[i].addEventListener('input', alEscribir(lista[i]));
            lista[i].addEventListener('change', alEscribir(lista[i]));
        }

        formulario.addEventListener('submit', function (evento) {
            var campos = controles();
            var primero = null;
            var j;

            for (j = 0; j < campos.length; j += 1) {
                var mensaje = reproche(campos[j]);

                marcar(formulario, campos[j], mensaje);

                if (mensaje !== '' && primero === null) {
                    primero = campos[j];
                }
            }

            if (primero === null) {
                return;
            }

            evento.preventDefault();

            /* Al primero que falla, no al ultimo: es donde hay que volver a
               escribir, y arrastrar la pantalla hasta el se hace solo. */
            primero.focus();

            if (typeof primero.scrollIntoView === 'function') {
                primero.scrollIntoView({ block: 'center' });
            }
        });
    }

    function iniciar(ambito) {
        var formularios = (ambito || document).querySelectorAll('[data-validar]');
        var i;

        for (i = 0; i < formularios.length; i += 1) {
            enlazar(formularios[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { iniciar(); });
    } else {
        iniciar();
    }
}());
