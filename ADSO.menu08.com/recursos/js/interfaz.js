/* interfaz.js — utilidades compartidas de interfaz.
   Menu08 · carta, caja y produccion para food trucks.

   Tres utilidades que consumen CARTA, CAJA y el SVP:

     Interfaz.aviso(texto, tipo, ms)   aviso temporal que se retira solo
     Interfaz.confirmar(opciones)      dialogo de confirmacion, devuelve Promise
     Interfaz.menu(boton, panel)       alternado de menu

   Sin bibliotecas y sin paso de compilacion: se carga con <script defer> desde
   la plantilla comun y publica un unico objeto global.

   Todo es mejora progresiva. Sin JavaScript un formulario con data-confirmar se
   envia igual que siempre y el menu queda desplegado: nada de lo que hay aqui
   es requisito para operar la caja.

   Los mensajes de un solo uso de Sesion::mensaje() los pinta la plantilla en el
   servidor y se quedan en la pagina. El aviso temporal es otra cosa: la
   respuesta a algo que acaba de hacer el usuario, sin recargar.

   Issue #14 · Fase 4 - Frontend */

'use strict';

var Interfaz = (function () {
    /* Iconos. Trazo de 2 px sobre reticula de 24, un solo estilo. Nunca emoji:
       no se recolorean con el tema y se dibujan distinto en cada sistema. */
    var ICONOS = {
        exito: '<circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.5 2.5 2.5 4.5-5"></path>',
        aviso: '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"></path><path d="M12 10v3.5"></path><path d="M12 17h.01"></path>',
        error: '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5.5"></path><path d="M12 16h.01"></path>'
    };

    var TIPOS = ['exito', 'aviso', 'error'];
    var MAXIMO_AVISOS = 3;
    var FOCALIZABLES = 'a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])';

    var region = null;

    function svg(tipo, clase) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

        s.setAttribute('class', clase);
        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('fill', 'none');
        s.setAttribute('stroke', 'currentColor');
        s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round');
        s.setAttribute('stroke-linejoin', 'round');
        s.setAttribute('aria-hidden', 'true');
        s.innerHTML = ICONOS[tipo];

        return s;
    }

    /* ------------------------------------------------------- aviso temporal */

    function regionAvisos() {
        if (region !== null && document.body.contains(region)) {
            return region;
        }

        region = document.createElement('div');
        region.className = 'avisos-region sin-impresion';
        /* polite y no assertive: un aviso de exito no debe cortar lo que el
           lector de pantalla este diciendo. El de error se marca aparte. */
        region.setAttribute('aria-live', 'polite');
        document.body.appendChild(region);

        return region;
    }

    /**
     * Muestra un aviso que se retira solo.
     *
     * @param {string} texto  Lo que se lee. Se inserta como texto, nunca como HTML.
     * @param {string} [tipo] exito | aviso | error. Por defecto aviso.
     * @param {number} [ms]   Milisegundos en pantalla. 0 lo deja fijo.
     * @returns {HTMLElement} El aviso, por si hay que retirarlo antes.
     */
    function aviso(texto, tipo, ms) {
        var clase = TIPOS.indexOf(tipo) === -1 ? 'aviso' : tipo;
        var duracion = typeof ms === 'number' ? ms : 4000;
        var caja = regionAvisos();

        /* Sin tope, una racha de errores tapa la pantalla entera. */
        while (caja.children.length >= MAXIMO_AVISOS) {
            caja.removeChild(caja.firstElementChild);
        }

        var nodo = document.createElement('div');
        nodo.className = 'aviso-temporal';

        if (clase === 'error') {
            nodo.setAttribute('role', 'alert');
        }

        nodo.appendChild(svg(clase, 'aviso-temporal-icono'));

        var span = document.createElement('span');
        span.textContent = String(texto);
        nodo.appendChild(span);

        caja.appendChild(nodo);

        if (duracion > 0) {
            window.setTimeout(function () {
                if (nodo.parentNode !== null) {
                    nodo.parentNode.removeChild(nodo);
                }
            }, duracion);
        }

        return nodo;
    }

    /* ------------------------------------------------ confirmacion de accion */

    /**
     * Pregunta antes de lo irreversible.
     *
     * @param {Object} opciones
     * @param {string} opciones.texto      La pregunta. Obligatoria.
     * @param {string} [opciones.titulo]
     * @param {string} [opciones.aceptar]
     * @param {string} [opciones.cancelar]
     * @param {boolean} [opciones.peligro] Pinta el boton de aceptar en rojo.
     * @returns {Promise<boolean>}
     */
    function confirmar(opciones) {
        var o = opciones || {};

        return new Promise(function (resolver) {
            var devolverFoco = document.activeElement;

            var velo = document.createElement('div');
            velo.className = 'dialogo-velo sin-impresion';

            var caja = document.createElement('div');
            caja.className = 'dialogo';
            caja.setAttribute('role', 'dialog');
            caja.setAttribute('aria-modal', 'true');

            var titulo = document.createElement('h2');
            titulo.className = 'dialogo-titulo';
            titulo.textContent = o.titulo || 'Confirmar';
            /* Sin id fijo: dos dialogos a la vez repetirian el identificador. */
            caja.appendChild(titulo);
            caja.setAttribute('aria-label', titulo.textContent);

            var texto = document.createElement('p');
            texto.className = 'dialogo-texto';
            texto.textContent = o.texto || '';
            caja.appendChild(texto);

            var acciones = document.createElement('div');
            acciones.className = 'dialogo-acciones';

            var cancelar = document.createElement('button');
            cancelar.type = 'button';
            cancelar.className = 'boton boton-texto';
            cancelar.textContent = o.cancelar || 'Cancelar';

            var aceptar = document.createElement('button');
            aceptar.type = 'button';
            aceptar.className = 'boton ' + (o.peligro ? 'boton-peligro' : 'boton-relleno');
            aceptar.textContent = o.aceptar || 'Aceptar';

            acciones.appendChild(cancelar);
            acciones.appendChild(aceptar);
            caja.appendChild(acciones);
            velo.appendChild(caja);

            function cerrar(respuesta) {
                document.removeEventListener('keydown', enTecla, true);

                if (velo.parentNode !== null) {
                    velo.parentNode.removeChild(velo);
                }

                /* El foco vuelve a donde estaba: si no, salta al principio del
                   documento y quien navega con teclado pierde el sitio. */
                if (devolverFoco !== null && typeof devolverFoco.focus === 'function') {
                    devolverFoco.focus();
                }

                resolver(respuesta);
            }

            function enTecla(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    cerrar(false);

                    return;
                }

                if (e.key !== 'Tab') {
                    return;
                }

                /* Encierro del foco: mientras el dialogo este abierto, el
                   tabulador no puede salirse a la pagina de detras. */
                var focalizables = caja.querySelectorAll(FOCALIZABLES);

                if (focalizables.length === 0) {
                    return;
                }

                var primero = focalizables[0];
                var ultimo = focalizables[focalizables.length - 1];

                /* Un clic sobre el texto del dialogo deja el foco en <body>, y
                   entonces no casa ni con el primero ni con el ultimo: sin esta
                   guarda el tabulador se escaparia a la pagina de detras. */
                if (!caja.contains(document.activeElement)) {
                    e.preventDefault();
                    (e.shiftKey ? ultimo : primero).focus();

                    return;
                }

                if (e.shiftKey && document.activeElement === primero) {
                    e.preventDefault();
                    ultimo.focus();
                } else if (!e.shiftKey && document.activeElement === ultimo) {
                    e.preventDefault();
                    primero.focus();
                }
            }

            cancelar.addEventListener('click', function () { cerrar(false); });
            aceptar.addEventListener('click', function () { cerrar(true); });

            velo.addEventListener('click', function (e) {
                if (e.target === velo) {
                    cerrar(false);
                }
            });

            document.addEventListener('keydown', enTecla, true);
            document.body.appendChild(velo);

            /* En una accion destructiva el foco arranca en Cancelar: un Enter
               de mas no debe cerrar el turno. */
            (o.peligro ? cancelar : aceptar).focus();
        });
    }

    /* ---------------------------------------------------- alternado de menu */

    /**
     * Enlaza un boton con el panel que abre y cierra.
     *
     * @param {HTMLElement} boton
     * @param {HTMLElement} panel
     * @returns {Object|null} { abrir, cerrar, alternar } o null si falta alguno.
     */
    function menu(boton, panel) {
        if (!boton || !panel) {
            return null;
        }

        /* iniciar() es publica y acepta una raiz, para volver a enganchar un
           trozo de DOM repintado. Sin esta guarda, la segunda pasada dejaria
           dos escuchadores en el mismo boton y cada clic alternaria dos veces:
           el panel no volveria a abrirse. */
        if (boton.dataset.menuEnlazado === '1') {
            return null;
        }

        boton.dataset.menuEnlazado = '1';

        function fijar(abierto) {
            boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            panel.hidden = !abierto;
        }

        /* El panel arranca cerrado solo cuando hay JavaScript. Sin el se queda
           como lo dejo el servidor, desplegado y utilizable. */
        fijar(false);

        if (!boton.hasAttribute('aria-controls') && panel.id !== '') {
            boton.setAttribute('aria-controls', panel.id);
        }

        boton.addEventListener('click', function () {
            fijar(boton.getAttribute('aria-expanded') !== 'true');
        });

        /* Escape cierra y devuelve el foco al boton. */
        panel.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                fijar(false);
                boton.focus();
            }
        });

        return {
            abrir: function () { fijar(true); },
            cerrar: function () { fijar(false); },
            alternar: function () { fijar(boton.getAttribute('aria-expanded') !== 'true'); }
        };
    }

    /* ------------------------------------------------------------- arranque */

    /* Enganche por atributos, para que una vista no necesite escribir JavaScript:

         <button data-alterna="menu-panel">          alterna #menu-panel
         <form data-confirmar="Cerrar el turno?">    pregunta antes de enviar
         <a data-confirmar="..." data-confirmar-peligro>
         <button data-aviso="Copiado" data-aviso-tipo="exito">

       En data-confirmar-* el texto llega escapado por Vista::e() y se inserta
       con textContent, nunca como HTML. */
    function iniciar(raiz) {
        var ambito = raiz || document;
        var i;

        var botones = ambito.querySelectorAll('[data-alterna]');

        for (i = 0; i < botones.length; i += 1) {
            menu(botones[i], document.getElementById(botones[i].getAttribute('data-alterna')));
        }

        var confirmables = ambito.querySelectorAll('[data-confirmar]');

        for (i = 0; i < confirmables.length; i += 1) {
            enlazarConfirmacion(confirmables[i]);
        }

        var avisables = ambito.querySelectorAll('[data-aviso]');

        for (i = 0; i < avisables.length; i += 1) {
            enlazarAviso(avisables[i]);
        }
    }

    function enlazarAviso(elemento) {
        if (elemento.dataset.avisoEnlazado === '1') {
            return;
        }

        elemento.dataset.avisoEnlazado = '1';

        elemento.addEventListener('click', function () {
            aviso(elemento.getAttribute('data-aviso'), elemento.getAttribute('data-aviso-tipo'));
        });
    }

    function enlazarConfirmacion(elemento) {
        if (elemento.dataset.confirmarEnlazado === '1') {
            return;
        }

        elemento.dataset.confirmarEnlazado = '1';

        var esFormulario = elemento.tagName === 'FORM';
        var evento = esFormulario ? 'submit' : 'click';

        /* La bandera vive en el cierre, no en el dataset: nadie de fuera puede
           dejarla puesta y no ensucia el marcado. */
        var confirmado = false;

        elemento.addEventListener(evento, function (e) {
            if (confirmado) {
                confirmado = false;

                return;
            }

            e.preventDefault();

            confirmar({
                titulo: elemento.getAttribute('data-confirmar-titulo') || 'Confirmar',
                texto: elemento.getAttribute('data-confirmar'),
                aceptar: elemento.getAttribute('data-confirmar-aceptar') || 'Aceptar',
                cancelar: elemento.getAttribute('data-confirmar-cancelar') || 'Cancelar',
                peligro: elemento.hasAttribute('data-confirmar-peligro')
            }).then(function (aceptado) {
                if (!aceptado) {
                    return;
                }

                confirmado = true;

                if (esFormulario) {
                    /* requestSubmit y no submit(): submit() se salta la
                       validacion del navegador y el evento, asi que un
                       formulario invalido se enviaria igual. */
                    if (typeof elemento.requestSubmit === 'function') {
                        elemento.requestSubmit();
                    } else if (typeof elemento.reportValidity !== 'function' || elemento.reportValidity()) {
                        /* Respaldo para navegadores sin requestSubmit: se
                           valida a mano antes, para no perder la comprobacion
                           que submit() se salta. */
                        elemento.submit();
                    }
                } else {
                    elemento.click();
                }

                /* Se limpia SIEMPRE. Si la validacion nativa impidio el envio,
                   el evento submit no llego a dispararse y la bandera se
                   quedaria puesta: el siguiente intento se enviaria sin
                   preguntar, que es justo lo que el dialogo existe para evitar. */
                confirmado = false;
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { iniciar(); });
    } else {
        iniciar();
    }

    return {
        aviso: aviso,
        confirmar: confirmar,
        menu: menu,
        iniciar: iniciar
    };
}());
