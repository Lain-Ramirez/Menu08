/* carta.js — las mejoras de la carta publica.
   Menu08 · carta, caja y produccion para food trucks.

   Solo la carga /carta/{slug}, a traves del parametro $guiones.

   TRES COSAS, Y LAS TRES SON MEJORA PROGRESIVA. La carta se lee entera sin este
   archivo: las categorias son anclas, la foto es un enlace a la imagen y la
   descripcion sale completa. Lo que se pone encima:

     - El buscador. Filtra las fichas YA pintadas por el servidor: no hay ninguna
       consulta de por medio, asi que responde en cada tecla y no gasta datos
       del telefono del que esta en la fila.
     - El visor de fotos. La foto se abre en grande sin salir de la carta.
     - «Leer mas». La descripcion del negocio se recorta a tres renglones, y el
       boton solo aparece si de verdad no cabe.

   Las tres piezas salen del servidor con hidden o sin recortar, y es este
   archivo el que las enciende: sin el no prometen nada que no hagan.

   Issue #17 · Fase 4 - Frontend */

'use strict';

(function () {
    var raiz = document.querySelector('[data-carta]');

    if (raiz === null) {
        return;
    }

    /* ------------------------------------------------------------ leer mas */

    (function leerMas() {
        var texto = raiz.querySelector('[data-carta-descripcion]');
        var boton = raiz.querySelector('[data-carta-leer-mas]');

        if (texto === null || boton === null) {
            return;
        }

        texto.classList.add('carta-descripcion-corta');

        /* Si con el recorte puesto el texto cabe entero, el boton sobra: no se
           ofrece «leer mas» de algo que ya se esta leyendo completo. */
        if (texto.scrollHeight <= texto.clientHeight + 1) {
            texto.classList.remove('carta-descripcion-corta');

            return;
        }

        boton.hidden = false;

        boton.addEventListener('click', function () {
            var abierto = boton.getAttribute('aria-expanded') === 'true';

            texto.classList.toggle('carta-descripcion-corta', abierto);
            boton.setAttribute('aria-expanded', abierto ? 'false' : 'true');
            boton.textContent = abierto ? 'Leer más' : 'Leer menos';
        });
    }());

    /* ------------------------------------------------------------ buscador */

    /** Sin tildes y en minusculas: quien busca «limon» encuentra «Limón». */
    function normalizar(texto) {
        var s = String(texto || '').toLowerCase();

        return typeof s.normalize === 'function'
            ? s.normalize('NFD').replace(/[̀-ͯ]/g, '')
            : s;
    }

    (function buscador() {
        var caja = raiz.querySelector('[data-carta-buscador]');
        var campo = raiz.querySelector('[data-carta-busqueda]');
        var sinResultados = raiz.querySelector('[data-carta-sin-resultados]');
        var categorias = raiz.querySelectorAll('[data-carta-categoria]');

        if (caja === null || campo === null || categorias.length === 0) {
            return;
        }

        /* El texto de cada ficha se normaliza una vez, no en cada tecla. */
        var bloques = [];
        var i;

        for (i = 0; i < categorias.length; i += 1) {
            var nodos = categorias[i].querySelectorAll('[data-carta-producto]');
            var fichas = [];
            var j;

            for (j = 0; j < nodos.length; j += 1) {
                fichas.push({
                    nodo: nodos[j],
                    texto: normalizar(
                        (nodos[j].getAttribute('data-nombre') || '') + ' '
                        + (nodos[j].getAttribute('data-descripcion') || '')
                    )
                });
            }

            bloques.push({ nodo: categorias[i], fichas: fichas });
        }

        function filtrar() {
            var consulta = normalizar(campo.value).trim();
            var visibles = 0;
            var b;
            var f;

            for (b = 0; b < bloques.length; b += 1) {
                var enBloque = 0;

                for (f = 0; f < bloques[b].fichas.length; f += 1) {
                    var ficha = bloques[b].fichas[f];
                    var coincide = consulta === '' || ficha.texto.indexOf(consulta) !== -1;

                    ficha.nodo.hidden = !coincide;

                    if (coincide) {
                        enBloque += 1;
                    }
                }

                /* Un titulo de categoria sin ninguna ficha debajo es ruido. */
                bloques[b].nodo.hidden = enBloque === 0;
                visibles += enBloque;
            }

            if (sinResultados !== null) {
                sinResultados.hidden = visibles > 0;
            }
        }

        caja.hidden = false;
        campo.addEventListener('input', filtrar);

        /* Escape limpia la busqueda y devuelve la carta entera. */
        campo.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && campo.value !== '') {
                e.preventDefault();
                campo.value = '';
                filtrar();
            }
        });

        /* El navegador puede restaurar el texto del campo al volver atras. */
        filtrar();
    }());

    /* ------------------------------------------------------- visor de foto */

    (function visor() {
        var dialogo = raiz.querySelector('[data-carta-visor]');

        /* Sin <dialog> la foto sigue siendo un enlace a la imagen. */
        if (dialogo === null || typeof dialogo.showModal !== 'function') {
            return;
        }

        var foto = dialogo.querySelector('[data-carta-visor-foto]');
        var nombre = dialogo.querySelector('[data-carta-visor-nombre]');
        var precio = dialogo.querySelector('[data-carta-visor-precio]');
        var descripcion = dialogo.querySelector('[data-carta-visor-descripcion]');

        raiz.addEventListener('click', function (e) {
            var enlace = e.target.closest ? e.target.closest('[data-carta-ver]') : null;

            if (enlace === null) {
                return;
            }

            var ficha = enlace.closest('[data-carta-producto]');

            if (ficha === null) {
                return;
            }

            e.preventDefault();

            /* Todo con textContent y con atributos: lo que escribe el dueno del
               truck nunca se inserta como HTML. */
            foto.src = enlace.getAttribute('href');
            foto.alt = ficha.getAttribute('data-nombre') || '';
            nombre.textContent = ficha.getAttribute('data-nombre') || '';
            precio.textContent = ficha.getAttribute('data-precio') || '';
            descripcion.textContent = ficha.getAttribute('data-descripcion') || '';

            dialogo.showModal();
        });

        /* Un toque fuera de la caja cierra: el velo es el propio <dialog>, asi
           que el clic le llega a el y no a lo de dentro. */
        dialogo.addEventListener('click', function (e) {
            if (e.target === dialogo) {
                dialogo.close();
            }
        });
    }());
}());
