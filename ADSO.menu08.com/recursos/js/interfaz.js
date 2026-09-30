/* interfaz.js — utilidades compartidas de interfaz.
   Menu08 · carta, caja y produccion para food trucks.

   Tres utilidades que consumen CARTA, CAJA y el SVP:

     Interfaz.aviso(texto, tipo, ms)   aviso temporal que se retira solo
     Interfaz.confirmar(opciones)      dialogo de confirmacion, devuelve Promise
     Interfaz.menu(boton, panel)       alternado de menu
     Interfaz.barraCategorias(barra)   resalta la categoria que se esta leyendo

   Y dos respuestas que se enganchan solas, sin que la vista escriba nada: el
   boton que envio un formulario se marca ocupado hasta que llega la pagina
   siguiente, y data-ver-clave muestra u oculta la contrasena de un campo.

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
            window.setTimeout(function () { retirarAviso(nodo); }, duracion);
        }

        return nodo;
    }

    /* Retira el aviso con su salida: la clase dispara la animacion de
       componentes.css y el nodo se quita cuando termina. Si desaparece de golpe,
       los avisos de debajo saltan a ocupar su sitio y el ojo se va al salto.

       El temporizador es el que manda, no el evento animationend: con
       prefers-reduced-motion la animacion dura una centesima y el evento puede
       no llegar nunca, y un aviso que no se va es peor que uno que se va sin
       animar. */
    function retirarAviso(nodo) {
        if (nodo.parentNode === null) {
            return;
        }

        nodo.classList.add('aviso-temporal-sale');

        window.setTimeout(function () {
            if (nodo.parentNode !== null) {
                nodo.parentNode.removeChild(nodo);
            }
        }, 200);
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
     * @param {string} [consulta] Consulta de medios en la que el alternado manda.
     *                            Fuera de ella el panel queda visible y el boton
     *                            no pinta nada. Sin consulta, manda siempre.
     * @returns {Object|null} { abrir, cerrar, alternar } o null si falta alguno.
     */
    function menu(boton, panel, consulta) {
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

        /* Sin consulta de medios el alternado manda siempre. Con ella, solo
           dentro. matchMedia siempre existe en los navegadores que soportan las
           hojas de este proyecto. */
        var medios = consulta ? window.matchMedia(consulta) : null;

        function alternadoManda() {
            return medios === null || medios.matches;
        }

        function fijar(abierto) {
            boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            panel.hidden = !abierto;
        }

        /* Fuera de la consulta el panel se muestra y se le quita hidden. Esto no
           es cosmetica: hidden lo saca del arbol de accesibilidad, asi que una
           navegacion "oculta" pero visible en pantalla ancha desapareceria para
           un lector de pantalla. */
        function sincronizar() {
            if (alternadoManda()) {
                fijar(false);
            } else {
                panel.hidden = false;
                boton.removeAttribute('aria-expanded');
            }
        }

        /* El panel arranca cerrado solo cuando hay JavaScript y solo donde el
           alternado manda. Sin JavaScript se queda como lo dejo el servidor,
           desplegado y utilizable. */
        sincronizar();

        if (medios !== null) {
            /* addEventListener y no addListener: el segundo esta obsoleto. Al
               cruzar el punto de quiebre hay que rehacer el estado, o el panel
               se queda cerrado en ancho o abierto en estrecho. */
            if (typeof medios.addEventListener === 'function') {
                medios.addEventListener('change', sincronizar);
            }
        }

        if (!boton.hasAttribute('aria-controls') && panel.id !== '') {
            boton.setAttribute('aria-controls', panel.id);
        }

        boton.addEventListener('click', function () {
            if (!alternadoManda()) {
                return;
            }

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
         <button data-alterna="nav" data-alterna-desde="(max-width: 767px)">
         <form data-confirmar="Cerrar el turno?">    pregunta antes de enviar
         <a data-confirmar="..." data-confirmar-peligro>
         <button data-aviso="Copiado" data-aviso-tipo="exito">

       En data-confirmar-* el texto llega escapado por Vista::e() y se inserta
       con textContent, nunca como HTML. */
    /* Barra de categorias de la carta publica.

       El salto al bloque NO lo hace esta funcion: son anclas de verdad y el
       navegador ya las resuelve, con o sin JavaScript. Lo que aporta aqui es
       decir cual se esta leyendo, que es lo que el ancla sola no puede.

       Con IntersectionObserver y no escuchando 'scroll': el observador avisa
       solo cuando una seccion entra o sale, en vez de correr en cada pixel de
       desplazamiento. En un telefono de gama baja, que es donde se abre esta
       carta, esa diferencia se nota.

       El margen superior del observador descuenta la altura de la barra pegada,
       para que la categoria se marque cuando su titulo asoma bajo ella y no
       cuando ya se paso de largo. */
    function barraCategorias(barra) {
        if (!barra || typeof IntersectionObserver !== 'function') {
            return null;
        }

        var enlaces = barra.querySelectorAll('a[href^="#"]');
        var porId = {};
        var secciones = [];
        var i;

        for (i = 0; i < enlaces.length; i += 1) {
            var id = decodeURIComponent(enlaces[i].getAttribute('href').slice(1));
            var seccion = document.getElementById(id);

            if (seccion !== null) {
                porId[id] = enlaces[i];
                secciones.push(seccion);
            }
        }

        if (secciones.length === 0) {
            return null;
        }

        function marcar(id) {
            for (var clave in porId) {
                if (Object.prototype.hasOwnProperty.call(porId, clave)) {
                    if (clave === id) {
                        porId[clave].setAttribute('aria-current', 'true');
                    } else {
                        porId[clave].removeAttribute('aria-current');
                    }
                }
            }
        }

        var visibles = {};

        var observador = new IntersectionObserver(function (entradas) {
            var j;

            for (j = 0; j < entradas.length; j += 1) {
                visibles[entradas[j].target.id] = entradas[j].isIntersecting;
            }

            /* Puede haber varias secciones a la vista a la vez. Manda la
               primera en el orden del documento, que es la que el lector tiene
               arriba: tomar la ultima haria saltar la marca hacia adelante. */
            for (j = 0; j < secciones.length; j += 1) {
                if (visibles[secciones[j].id]) {
                    marcar(secciones[j].id);

                    return;
                }
            }
        }, { rootMargin: '-96px 0px -60% 0px', threshold: 0 });

        for (i = 0; i < secciones.length; i += 1) {
            observador.observe(secciones[i]);
        }

        return { detener: function () { observador.disconnect(); } };
    }

    function iniciar(raiz) {
        var ambito = raiz || document;
        var i;

        var botones = ambito.querySelectorAll('[data-alterna]');

        for (i = 0; i < botones.length; i += 1) {
            menu(
                botones[i],
                document.getElementById(botones[i].getAttribute('data-alterna')),
                botones[i].getAttribute('data-alterna-desde') || undefined
            );
        }

        var confirmables = ambito.querySelectorAll('[data-confirmar]');

        for (i = 0; i < confirmables.length; i += 1) {
            enlazarConfirmacion(confirmables[i]);
        }

        var avisables = ambito.querySelectorAll('[data-aviso]');

        for (i = 0; i < avisables.length; i += 1) {
            enlazarAviso(avisables[i]);
        }

        var barras = ambito.querySelectorAll('[data-carta-barra]');

        for (i = 0; i < barras.length; i += 1) {
            barraCategorias(barras[i]);
        }

        var claves = ambito.querySelectorAll('[data-ver-clave]');

        for (i = 0; i < claves.length; i += 1) {
            enlazarClave(claves[i]);
        }

        var temas = ambito.querySelectorAll('[data-tema]');

        for (i = 0; i < temas.length; i += 1) {
            enlazarTema(temas[i]);
        }

        var filtros = ambito.querySelectorAll('[data-filtro]');

        for (i = 0; i < filtros.length; i += 1) {
            filtro(filtros[i]);
        }

        var cerrables = ambito.querySelectorAll('[data-aviso-cerrar]');

        for (i = 0; i < cerrables.length; i += 1) {
            enlazarCierre(cerrables[i]);
        }
    }

    /* ------------------------------------------------- tema claro y oscuro */

    /* md3.css trae la paleta Brasa en claro (:root) y en oscuro (html.o). El
       claro es el de siempre y el que sale por omision; el oscuro se elige con
       este boton y se recuerda en el navegador. La clase la pone al cargar un
       guion en linea del <head>, antes de pintar; aqui solo se atiende el clic.

       Todos los botones de la pagina se mantienen de acuerdo entre si. */
    var CLAVE_TEMA = 'menu08-tema';

    function temaOscuro() {
        return document.documentElement.classList.contains('o');
    }

    function reflejarTema() {
        var botones = document.querySelectorAll('[data-tema]');
        var oscuro = temaOscuro();
        var i;

        for (i = 0; i < botones.length; i += 1) {
            botones[i].setAttribute('aria-pressed', oscuro ? 'true' : 'false');
            botones[i].setAttribute('aria-label', oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
            botones[i].title = oscuro ? 'Tema claro' : 'Tema oscuro';
        }

        /* El color de la barra del navegador en el telefono acompana al tema. */
        var meta = document.querySelector('meta[name="theme-color"]');

        if (meta !== null) {
            meta.setAttribute('content', oscuro ? '#180f0a' : '#fff8f4');
        }
    }

    function enlazarTema(boton) {
        if (boton.dataset.temaEnlazado === '1') {
            return;
        }

        boton.dataset.temaEnlazado = '1';
        boton.hidden = false;

        boton.addEventListener('click', function () {
            var oscuro = !temaOscuro();

            document.documentElement.classList.toggle('o', oscuro);

            /* En modo privado el almacenamiento puede no estar: el tema cambia
               igual, solo que no se recuerda. */
            try {
                window.localStorage.setItem(CLAVE_TEMA, oscuro ? 'o' : 'c');
            } catch (e) { /* sin almacenamiento */ }

            reflejarTema();
        });

        reflejarTema();
    }

    /* -------------------------------------------------------------- filtro */

    /** Sin tildes y en minusculas: quien busca «limon» encuentra «Limón». */
    function normalizar(texto) {
        var s = String(texto || '').toLowerCase();

        return typeof s.normalize === 'function'
            ? s.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            : s;
    }

    /* Filtra una lista YA pintada por el servidor, sin ninguna consulta:

         <section data-filtro>
           <div data-filtro-controles hidden>
             <input data-filtro-texto>
             <button data-filtro-marca="" aria-pressed="true">Todos</button>
             <button data-filtro-marca="abierto" aria-pressed="false">Abiertos</button>
           </div>
           <li data-filtro-item data-filtro-busca="..." data-filtro-marcas="abierto">
           <p data-filtro-vacio hidden>Nada coincide.</p>
         </section>

       Los controles salen ocultos y se muestran aqui: sin JavaScript la lista
       se ve entera, que es lo que habia antes. Con un solo elemento tampoco se
       muestran: no hay nada que filtrar. */
    function filtro(raiz) {
        var controles = raiz.querySelector('[data-filtro-controles]');
        var items = raiz.querySelectorAll('[data-filtro-item]');

        if (controles === null || items.length < 2 || raiz.dataset.filtroEnlazado === '1') {
            return;
        }

        raiz.dataset.filtroEnlazado = '1';

        var campo = raiz.querySelector('[data-filtro-texto]');
        var chips = raiz.querySelectorAll('[data-filtro-marca]');
        var vacio = raiz.querySelector('[data-filtro-vacio]');
        var marca = '';
        var fichas = [];
        var i;

        for (i = 0; i < items.length; i += 1) {
            fichas.push({
                nodo: items[i],
                texto: normalizar(items[i].getAttribute('data-filtro-busca') || items[i].textContent),
                marcas: ' ' + (items[i].getAttribute('data-filtro-marcas') || '') + ' '
            });
        }

        function aplicar() {
            var consulta = campo === null ? '' : normalizar(campo.value).trim();
            var visibles = 0;
            var j;

            for (j = 0; j < fichas.length; j += 1) {
                var coincide = (consulta === '' || fichas[j].texto.indexOf(consulta) !== -1)
                    && (marca === '' || fichas[j].marcas.indexOf(' ' + marca + ' ') !== -1);

                fichas[j].nodo.hidden = !coincide;

                if (coincide) {
                    visibles += 1;
                }
            }

            if (vacio !== null) {
                vacio.hidden = visibles > 0;
            }
        }

        function elegir(chip) {
            var j;

            marca = chip.getAttribute('data-filtro-marca') || '';

            for (j = 0; j < chips.length; j += 1) {
                chips[j].setAttribute('aria-pressed', chips[j] === chip ? 'true' : 'false');
            }

            aplicar();
        }

        for (i = 0; i < chips.length; i += 1) {
            (function (chip) {
                chip.addEventListener('click', function () { elegir(chip); });
            }(chips[i]));
        }

        if (campo !== null) {
            campo.addEventListener('input', aplicar);

            campo.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && campo.value !== '') {
                    e.preventDefault();
                    campo.value = '';
                    aplicar();
                }
            });
        }

        controles.hidden = false;
        aplicar();
    }

    /* ------------------------------------------------- cierre de un aviso */

    /* Los mensajes de Sesion::mensaje() se quedan en la pagina hasta que se
       recarga. Una vez leidos estorban: el boton los retira, y el de exito se
       va solo a los ocho segundos —«Producto guardado» no necesita respuesta—.
       Los de aviso y error se quedan hasta que alguien los cierre. */
    function enlazarCierre(boton) {
        var caja = boton.closest ? boton.closest('.aviso') : null;

        if (caja === null || boton.dataset.cierreEnlazado === '1') {
            return;
        }

        boton.dataset.cierreEnlazado = '1';
        boton.hidden = false;

        function retirar() {
            if (caja.parentNode === null) {
                return;
            }

            caja.classList.add('aviso-temporal-sale');

            window.setTimeout(function () {
                if (caja.parentNode !== null) {
                    caja.parentNode.removeChild(caja);
                }
            }, 200);
        }

        boton.addEventListener('click', retirar);

        if (caja.getAttribute('data-aviso-sesion') === 'exito') {
            window.setTimeout(retirar, 8000);
        }
    }

    /* ------------------------------------------------- barra de progreso */

    /* Entre el clic y la pagina siguiente no pasa nada a la vista. Una barra
       fina arriba dice que el clic entro y que algo viene: avanza sola hasta
       cerca del final y la pagina nueva la sustituye.

       Solo para lo que de verdad cambia de pagina: un enlace del mismo sitio,
       en la misma pestana, sin tecla modificadora y que no sea un ancla ni una
       descarga. Se retira en 'pageshow' —al volver atras el navegador restaura
       la pagina tal como quedo— y, por si el clic no llego a navegar, sola a
       los ocho segundos. */
    var barraProgreso = null;

    function mostrarProgreso() {
        if (barraProgreso === null) {
            barraProgreso = document.createElement('div');
            barraProgreso.className = 'progreso sin-impresion';
            barraProgreso.setAttribute('aria-hidden', 'true');
        }

        if (barraProgreso.parentNode === null) {
            document.body.appendChild(barraProgreso);
            window.setTimeout(ocultarProgreso, 8000);
        }
    }

    function ocultarProgreso() {
        if (barraProgreso !== null && barraProgreso.parentNode !== null) {
            barraProgreso.parentNode.removeChild(barraProgreso);
        }
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        var enlace = e.target.closest ? e.target.closest('a[href]') : null;

        if (enlace === null || enlace.target === '_blank' || enlace.hasAttribute('download')
            || enlace.hasAttribute('data-sin-progreso')) {
            return;
        }

        var destino = enlace.getAttribute('href') || '';

        /* Un ancla no cambia de pagina, y un enlace a otro sitio tampoco deja
           esta pagina a la vista el tiempo suficiente para verla. */
        if (destino.charAt(0) === '#' || enlace.origin !== window.location.origin
            || (enlace.pathname === window.location.pathname && enlace.search === window.location.search && enlace.hash !== '')) {
            return;
        }

        /* Despues del despacho: si otro escuchador detuvo el clic —el visor de
           fotos de la carta, el dialogo de data-confirmar—, no hay navegacion. */
        window.setTimeout(function () {
            if (!e.defaultPrevented) {
                mostrarProgreso();
            }
        }, 0);
    });

    window.addEventListener('pageshow', ocultarProgreso);

    /* ------------------------------------------------- ver la contrasena */

    /* <button data-ver-clave="id-del-campo" hidden>

       El boton sale oculto del servidor y se muestra aqui: sin JavaScript no
       puede cambiar el tipo del campo, y un boton que no responde es peor que
       uno que no esta. El estado viaja en aria-pressed, que es lo que lee el
       lector de pantalla y lo que usa la hoja para cambiar de icono. */
    function enlazarClave(boton) {
        var campo = document.getElementById(boton.getAttribute('data-ver-clave'));

        if (campo === null || boton.dataset.claveEnlazada === '1') {
            return;
        }

        boton.dataset.claveEnlazada = '1';
        boton.hidden = false;

        boton.addEventListener('click', function () {
            var visible = campo.type === 'password';

            campo.type = visible ? 'text' : 'password';
            boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
            boton.setAttribute('aria-label', visible ? 'Ocultar la contrasena' : 'Mostrar la contrasena');

            /* El foco vuelve al campo: quien pulso el ojo estaba escribiendo. */
            campo.focus();
        });

        /* La contrasena nunca viaja ni se queda a la vista: al enviar, el campo
           vuelve a su tipo, y asi el navegador tampoco la guarda como texto. */
        if (campo.form !== null) {
            campo.form.addEventListener('submit', function () {
                campo.type = 'password';
                boton.setAttribute('aria-pressed', 'false');
            });
        }
    }

    /* ------------------------------------------------ boton ocupado al enviar */

    /* Entre la pulsacion y la pagina siguiente hay un hueco en el que no pasa
       nada a la vista, y en ese hueco es donde se pulsa dos veces. El boton que
       envio el formulario se marca ocupado: gira, y deja de atender al puntero.

       TRES CUIDADOS.

       1. Se marca DESPUES del despacho del evento y solo si nadie lo detuvo. El
          dialogo de data-confirmar y validacion.js cancelan el submit para
          preguntar o para senalar un campo; marcar ahi dejaria el boton girando
          sobre un formulario que no salio. Es el mismo criterio de turno.js.

       2. NO se deshabilita. Un boton deshabilitado no viaja con el formulario.

       3. Se limpia al volver. Con el boton de atras el navegador puede
          restaurar la pagina tal como quedo —con el boton girando—, y por eso
          'pageshow' lo apaga. Y se apaga solo a los ocho segundos, por si el
          envio no llego a cambiar de pagina. */
    var CLASE_OCUPADO = 'boton-ocupado';

    function liberarBotones() {
        var ocupados = document.querySelectorAll('.' + CLASE_OCUPADO);
        var i;

        for (i = 0; i < ocupados.length; i += 1) {
            ocupados[i].classList.remove(CLASE_OCUPADO);
            ocupados[i].removeAttribute('aria-busy');
        }
    }

    document.addEventListener('submit', function (e) {
        var formulario = e.target;

        if (!formulario || formulario.tagName !== 'FORM') {
            return;
        }

        /* El boton que disparo el envio. Si el navegador no lo dice —o el envio
           fue con Enter desde un campo—, el primero de tipo submit. */
        var boton = e.submitter || formulario.querySelector('button[type="submit"]');

        if (!boton || !boton.classList || !boton.classList.contains('boton')) {
            return;
        }

        window.setTimeout(function () {
            if (e.defaultPrevented) {
                return;
            }

            boton.classList.add(CLASE_OCUPADO);
            boton.setAttribute('aria-busy', 'true');
            mostrarProgreso();

            window.setTimeout(liberarBotones, 8000);
        }, 0);
    });

    window.addEventListener('pageshow', liberarBotones);

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
        barraCategorias: barraCategorias,
        iniciar: iniciar
    };
}());
