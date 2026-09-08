/* caja.js — la orden en construccion de la pantalla de venta.
   Menu08 · carta, caja y produccion para food trucks.

   Arma la orden en el navegador: agregar desde el catalogo, subir y bajar
   cantidades, quitar renglones, recalcular el total y cobrar. Nada de esto
   recarga la pagina; el unico envio al servidor es el del cobro.

   Sin bibliotecas y sin paso de compilacion, igual que interfaz.js, del que
   toma prestada la forma de dibujar iconos y de no meter nunca texto como HTML.

   UNA SOLA FUENTE DE VERDAD. Cada ficha del catalogo lleva su campo oculto
   cantidad[ID], y es ese campo el que viaja en el envio: este archivo lo
   mantiene al dia mientras se arma la orden, en vez de inyectar campos al
   enviar. Por eso una orden que el servidor rechaza vuelve con las cantidades
   ya escritas y se recompone sola, sin que el cajero la rearme.

   EL DINERO SE CUENTA EN CENTAVOS, con enteros, igual que Orden::registrar en
   el servidor. Sumar importes en coma flotante arrastra errores de redondeo que
   en una jornada de decenas de ventas descuadran la caja por unos pesos. Lo que
   se calcula aqui es informativo de todos modos: el precio que vale es el que
   lee el servidor de la base dentro de la transaccion que escribe la venta.

   Issue #18 · Fase 4 - Frontend */

'use strict';

(function () {
    var ICONOS = {
        menos: '<path d="M5 12h14"></path>',
        mas: '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        basura: '<path d="M4 7h16"></path><path d="M10 7V5h4v2"></path><path d="m6 7 1 13h10l1-13"></path>'
    };

    /** Punto en el que la pantalla pasa de una columna a dos. El mismo valor
        esta en caja.css; si se cambia uno, el otro tambien. */
    var DOS_ZONAS = '(min-width: 1024px)';

    var raiz = document.querySelector('[data-venta]');

    if (raiz === null) {
        return;
    }

    /* ------------------------------------------------------------- utiles */

    function svg(trazos, clase) {
        var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

        s.setAttribute('viewBox', '0 0 24 24');
        s.setAttribute('fill', 'none');
        s.setAttribute('stroke', 'currentColor');
        s.setAttribute('stroke-width', '2');
        s.setAttribute('stroke-linecap', 'round');
        s.setAttribute('stroke-linejoin', 'round');
        s.setAttribute('aria-hidden', 'true');
        s.innerHTML = trazos;

        if (clase) {
            s.setAttribute('class', clase);
        }

        return s;
    }

    /** Separador de miles a la colombiana, el mismo que number_format del
        servidor. Los centavos no se muestran: ningun precio de la carta los
        usa y una columna de totales con ",00" repetido se lee peor. */
    function pesos(centavos) {
        var enteros = String(Math.round(centavos / 100));

        return '$ ' + enteros.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /** Minusculas y sin tildes, para que "limon" encuentre "limón" y al reves.
        Los nombres de la base no llevan tilde, pero quien busca si las teclea. */
    function normalizar(texto) {
        var plano = String(texto).toLowerCase();

        if (typeof plano.normalize === 'function') {
            plano = plano.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        return plano;
    }

    /** Solo digitos. Devuelve 0 con la cadena vacia. */
    function aEntero(texto) {
        var limpio = String(texto).replace(/\D/g, '');

        return limpio === '' ? 0 : parseInt(limpio, 10);
    }

    /* ------------------------------------------------------------- piezas */

    var forma = raiz.tagName === 'FORM' ? raiz : raiz.querySelector('form');
    var busqueda = raiz.querySelector('[data-venta-busqueda]');
    var chips = raiz.querySelectorAll('[data-venta-categoria]');
    var sinResultados = raiz.querySelector('[data-venta-vacio]');
    var zonas = raiz.querySelector('[data-venta-zonas]');
    var lista = raiz.querySelector('[data-venta-lineas]');
    var ordenVacia = raiz.querySelector('[data-venta-orden-vacia]');
    var salidaUnidades = raiz.querySelector('[data-venta-unidades]');
    var salidaTotal = raiz.querySelector('[data-venta-total]');
    var botonCobrar = raiz.querySelector('[data-venta-cobrar]');
    var botonVaciar = raiz.querySelector('[data-venta-vaciar]');
    var anuncio = raiz.querySelector('[data-venta-anuncio]');

    var dialogo = raiz.querySelector('[data-venta-dialogo]');
    var dialogoTotal = raiz.querySelector('[data-venta-dialogo-total]');
    var dialogoUnidades = raiz.querySelector('[data-venta-dialogo-unidades]');
    var medios = raiz.querySelectorAll('[data-venta-medio]');
    var bloqueEfectivo = raiz.querySelector('[data-venta-efectivo]');
    var campoRecibido = raiz.querySelector('[data-venta-recibido]');
    var envolturaRecibido = raiz.querySelector('[data-venta-recibido-campo]');
    var errorRecibido = raiz.querySelector('[data-venta-recibido-error]');
    var sugerencias = raiz.querySelector('[data-venta-sugerencias]');
    var salidaCambio = raiz.querySelector('[data-venta-cambio]');
    var bloqueCambio = raiz.querySelector('[data-venta-cambio-bloque]');
    var botonConfirmar = raiz.querySelector('[data-venta-confirmar]');
    var botonCancelar = raiz.querySelector('[data-venta-cancelar]');

    /** Hay turno abierto. Sin el no se arma nada: lo decide el servidor y aqui
        solo se obedece, porque el turno puede cerrarse desde otra pestaña. */
    var hayTurno = raiz.getAttribute('data-turno') === 'abierto';

    /* ------------------------------------------------------- el catalogo */

    /** Fichas del catalogo, con su texto ya normalizado para buscar sin
        rehacerlo en cada pulsacion de tecla. */
    var fichas = [];

    (function leerCatalogo() {
        var nodos = raiz.querySelectorAll('[data-venta-producto]');
        var i;

        for (i = 0; i < nodos.length; i += 1) {
            var nodo = nodos[i];
            var boton = nodo.querySelector('[data-venta-agregar]');
            var campo = nodo.querySelector('[data-venta-cantidad]');

            if (boton === null || campo === null) {
                continue;
            }

            fichas.push({
                nodo: nodo,
                boton: boton,
                campo: campo,
                categoria: nodo.getAttribute('data-categoria') || '',
                texto: normalizar(boton.getAttribute('data-nombre') || ''),
                producto: {
                    id: boton.getAttribute('data-venta-agregar'),
                    nombre: boton.getAttribute('data-nombre') || '',
                    precio: aEntero(boton.getAttribute('data-precio'))
                }
            });
        }
    }());

    /* ------------------------------------------------------------ la orden */

    /** id -> { producto, cantidad, nodo, numero, subtotal, menos, campo } */
    var renglones = {};

    /** Ids en el orden en que se fueron agregando, que es como se leen. */
    var secuencia = [];

    function totales() {
        var unidades = 0;
        var centavos = 0;
        var i;

        for (i = 0; i < secuencia.length; i += 1) {
            var r = renglones[secuencia[i]];
            unidades += r.cantidad;
            centavos += r.producto.precio * r.cantidad;
        }

        return { unidades: unidades, centavos: centavos };
    }

    /** Dibuja el renglon. Se construye con createElement y textContent: el
        nombre del producto lo escribe el administrador del truck y nunca se
        inserta como HTML. */
    function crearRenglon(producto) {
        var li = document.createElement('li');
        li.className = 'venta-linea';

        var texto = document.createElement('span');
        texto.className = 'venta-linea-texto';

        var nombre = document.createElement('span');
        nombre.className = 'venta-linea-nombre';
        nombre.textContent = producto.nombre;
        nombre.title = producto.nombre;

        var unitario = document.createElement('span');
        unitario.className = 'venta-linea-unitario';
        unitario.textContent = pesos(producto.precio) + ' c/u';

        texto.appendChild(nombre);
        texto.appendChild(unitario);

        var cantidad = document.createElement('span');
        cantidad.className = 'venta-linea-cantidad';

        var menos = document.createElement('button');
        menos.type = 'button';
        menos.className = 'boton-simbolo';

        var numero = document.createElement('span');
        numero.className = 'venta-linea-numero';

        var mas = document.createElement('button');
        mas.type = 'button';
        mas.className = 'boton-simbolo';
        mas.appendChild(svg(ICONOS.mas));
        mas.setAttribute('aria-label', 'Una unidad mas de ' + producto.nombre);
        mas.title = 'Una mas';

        cantidad.appendChild(menos);
        cantidad.appendChild(numero);
        cantidad.appendChild(mas);

        var subtotal = document.createElement('span');
        subtotal.className = 'venta-linea-subtotal';

        li.appendChild(texto);
        li.appendChild(cantidad);
        li.appendChild(subtotal);

        menos.addEventListener('click', function () { cambiar(producto.id, -1); });
        mas.addEventListener('click', function () { cambiar(producto.id, 1); });

        return { nodo: li, numero: numero, subtotal: subtotal, menos: menos, mas: mas };
    }

    /** Pinta la cantidad, el subtotal y la cara del boton de restar.

        A una unidad, restar es quitar: el boton pasa a papelera y lo dice en su
        rotulo. Asi el renglon se elimina sin gastar un cuarto boton en una
        columna que ya va justa de ancho. */
    function refrescarRenglon(r) {
        r.numero.textContent = String(r.cantidad);
        r.subtotal.textContent = pesos(r.producto.precio * r.cantidad);

        var quita = r.cantidad <= 1;

        r.menos.innerHTML = '';
        r.menos.appendChild(svg(quita ? ICONOS.basura : ICONOS.menos));
        r.menos.setAttribute(
            'aria-label',
            quita
                ? 'Quitar ' + r.producto.nombre + ' de la orden'
                : 'Una unidad menos de ' + r.producto.nombre
        );
        r.menos.title = quita ? 'Quitar de la orden' : 'Una menos';
    }

    /** Vuelca la cantidad al campo del catalogo, que es lo que se envia. */
    function sincronizarCampo(id, cantidad) {
        var i;

        for (i = 0; i < fichas.length; i += 1) {
            if (fichas[i].producto.id === id) {
                fichas[i].campo.value = String(cantidad);

                return;
            }
        }
    }

    function agregar(id) {
        if (!hayTurno) {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(renglones, id)) {
            cambiar(id, 1);

            return;
        }

        var ficha = null;
        var i;

        for (i = 0; i < fichas.length; i += 1) {
            if (fichas[i].producto.id === id) {
                ficha = fichas[i];
                break;
            }
        }

        if (ficha === null) {
            return;
        }

        var piezas = crearRenglon(ficha.producto);

        renglones[id] = {
            producto: ficha.producto,
            cantidad: 1,
            nodo: piezas.nodo,
            numero: piezas.numero,
            subtotal: piezas.subtotal,
            menos: piezas.menos,
            mas: piezas.mas
        };

        secuencia.push(id);
        lista.appendChild(piezas.nodo);
        refrescarRenglon(renglones[id]);
        sincronizarCampo(id, 1);
        actualizar();
    }

    function cambiar(id, delta) {
        if (!Object.prototype.hasOwnProperty.call(renglones, id)) {
            return;
        }

        var r = renglones[id];
        var nueva = r.cantidad + delta;

        if (nueva <= 0) {
            quitar(id);

            return;
        }

        /* El tope es el mismo que hace cumplir Orden::registrar. Sin el, el
           servidor rechazaria la venta entera despues de armarla. */
        if (nueva > 99) {
            nueva = 99;
        }

        r.cantidad = nueva;
        refrescarRenglon(r);
        sincronizarCampo(id, nueva);
        actualizar();
    }

    function quitar(id) {
        if (!Object.prototype.hasOwnProperty.call(renglones, id)) {
            return;
        }

        var r = renglones[id];
        var posicion = secuencia.indexOf(id);
        var siguiente = null;

        /* Al quitar un renglon el foco se queda en un boton que ya no existe y
           salta al principio del documento. Se pasa al renglon que ocupa su
           sitio, o al de arriba si era el ultimo. */
        if (posicion !== -1) {
            var vecino = secuencia[posicion + 1] || secuencia[posicion - 1] || null;

            if (vecino !== null && Object.prototype.hasOwnProperty.call(renglones, vecino)) {
                siguiente = renglones[vecino].mas;
            }

            secuencia.splice(posicion, 1);
        }

        if (r.nodo.parentNode !== null) {
            r.nodo.parentNode.removeChild(r.nodo);
        }

        delete renglones[id];
        sincronizarCampo(id, 0);
        actualizar();

        if (siguiente !== null) {
            siguiente.focus();
        } else if (busqueda !== null) {
            /* Era el ultimo renglon: cobrar acaba de deshabilitarse y no puede
               recibir el foco. Se devuelve al buscador, que es de donde sale la
               siguiente accion natural. */
            busqueda.focus();
        }
    }

    function vaciar() {
        var ids = secuencia.slice();
        var i;

        for (i = 0; i < ids.length; i += 1) {
            var r = renglones[ids[i]];

            if (r.nodo.parentNode !== null) {
                r.nodo.parentNode.removeChild(r.nodo);
            }

            delete renglones[ids[i]];
            sincronizarCampo(ids[i], 0);
        }

        secuencia = [];
        actualizar();
    }

    /** Un solo sitio donde se refresca todo lo que depende de la orden. */
    function actualizar() {
        var t = totales();
        var vacia = secuencia.length === 0;

        if (salidaUnidades !== null) {
            salidaUnidades.textContent = String(t.unidades);
        }

        if (salidaTotal !== null) {
            salidaTotal.textContent = pesos(t.centavos);
        }

        if (ordenVacia !== null) {
            ordenVacia.hidden = !vacia;
        }

        if (botonVaciar !== null) {
            botonVaciar.disabled = vacia;
        }

        /* Criterio del issue: sin renglones o sin turno abierto no se cobra. */
        if (botonCobrar !== null) {
            botonCobrar.disabled = vacia || !hayTurno;
        }

        /* showModal() deja el catalogo inerte, asi que en un navegador con
           <dialog> la orden no puede cambiar con el cobro abierto. Por el camino
           de respaldo si puede, y entonces el dialogo tiene que seguirla en vez
           de quedarse con un total que ya no es. */
        if (cobroAbierto()) {
            pintarTotalesDelCobro();
            evaluarCobro();
        }

        if (anuncio !== null) {
            anuncio.textContent = vacia
                ? 'La orden esta vacia.'
                : 'Orden: ' + t.unidades + (t.unidades === 1 ? ' articulo, total ' : ' articulos, total ')
                    + pesos(t.centavos) + '.';
        }
    }

    /* ------------------------------------------------- filtro y busqueda ==

       Los dos se aplican sobre las fichas YA renderizadas por el servidor: no
       hay ninguna consulta de por medio, que es lo que hace que responda al
       instante en la ventanilla. */

    var categoriaActiva = '';

    function filtrar() {
        var texto = busqueda === null ? '' : normalizar(busqueda.value.trim());
        var visibles = 0;
        var i;

        for (i = 0; i < fichas.length; i += 1) {
            var f = fichas[i];
            var pasaCategoria = categoriaActiva === '' || f.categoria === categoriaActiva;
            var pasaTexto = texto === '' || f.texto.indexOf(texto) !== -1;
            var visible = pasaCategoria && pasaTexto;

            f.nodo.hidden = !visible;

            if (visible) {
                visibles += 1;
            }
        }

        if (sinResultados !== null) {
            sinResultados.hidden = visibles > 0;
        }
    }

    function elegirCategoria(valor) {
        categoriaActiva = valor;

        var i;

        for (i = 0; i < chips.length; i += 1) {
            var activo = chips[i].getAttribute('data-venta-categoria') === valor;
            chips[i].setAttribute('aria-pressed', activo ? 'true' : 'false');
        }

        filtrar();
    }

    /* ---------------------------------------------------- dialogo de cobro */

    function medioElegido() {
        var i;

        for (i = 0; i < medios.length; i += 1) {
            if (medios[i].checked) {
                return medios[i].value;
            }
        }

        return '';
    }

    /** Recalcula el cambio y decide si se puede confirmar.

        Con efectivo, un valor recibido menor que el total bloquea el cobro: es
        el criterio del issue. Con tarjeta o transferencia no hay nada que
        comprobar, porque no hay cambio que devolver. */
    function evaluarCobro() {
        var t = totales();
        var efectivo = medioElegido() === 'efectivo';
        var recibido = campoRecibido === null ? 0 : aEntero(campoRecibido.value) * 100;
        var falta = efectivo && recibido < t.centavos;

        if (bloqueEfectivo !== null) {
            bloqueEfectivo.hidden = !efectivo;
        }

        if (salidaCambio !== null) {
            salidaCambio.textContent = (!efectivo || falta) ? '—' : pesos(recibido - t.centavos);
        }

        if (bloqueCambio !== null) {
            bloqueCambio.classList.toggle('venta-cambio-sin', !efectivo || falta);
        }

        /* Sin nada tecleado todavia no hay error que mostrar: se avisa cuando
           el cajero ya escribio algo y no alcanza. */
        var escribio = campoRecibido !== null && aEntero(campoRecibido.value) > 0;

        if (envolturaRecibido !== null) {
            envolturaRecibido.classList.toggle('campo-error', falta && escribio);
        }

        if (errorRecibido !== null) {
            errorRecibido.textContent = falta && escribio
                ? 'Faltan ' + pesos(t.centavos - recibido) + ' para cubrir el total.'
                : '';
            errorRecibido.hidden = !(falta && escribio);
        }

        if (campoRecibido !== null) {
            campoRecibido.setAttribute('aria-invalid', falta && escribio ? 'true' : 'false');
        }

        if (botonConfirmar !== null) {
            botonConfirmar.disabled = falta || t.unidades === 0 || !hayTurno;
        }
    }

    function pintarTotalesDelCobro() {
        var t = totales();

        if (dialogoTotal !== null) {
            dialogoTotal.textContent = pesos(t.centavos);
        }

        if (dialogoUnidades !== null) {
            dialogoUnidades.textContent = t.unidades === 1
                ? '1 articulo'
                : t.unidades + ' articulos';
        }
    }

    /* Valores frecuentes de "recibido".

       En la ventanilla se paga casi siempre con un billete redondo, asi que un
       toque cubre el caso normal y el teclado queda para la excepcion. Ademas
       evita tener que enfocar el campo al abrir: al hacerlo por codigo el
       navegador no aplica :focus-visible, no sale el anillo dorado, y lo unico
       que se ve es el borde de --primary, que en esta paleta es rojo. Un campo
       vacio con el borde rojo se lee como un error que no ha ocurrido.

       Se ofrecen el importe exacto, la subida al siguiente diez mil y los
       billetes que superan el total. Nunca mas de cuatro. */
    function pintarSugerencias(centavos) {
        if (sugerencias === null) {
            return;
        }

        while (sugerencias.firstChild !== null) {
            sugerencias.removeChild(sugerencias.firstChild);
        }

        var total = Math.ceil(centavos / 100);
        var valores = [total];
        var redondo = Math.ceil(total / 10000) * 10000;
        var billetes = [10000, 20000, 50000, 100000, 200000];
        var i;

        if (redondo > total) {
            valores.push(redondo);
        }

        for (i = 0; i < billetes.length; i += 1) {
            if (billetes[i] > total && valores.indexOf(billetes[i]) === -1) {
                valores.push(billetes[i]);
            }
        }

        valores = valores.slice(0, 4);

        for (i = 0; i < valores.length; i += 1) {
            (function (valor, exacto) {
                var boton = document.createElement('button');

                boton.type = 'button';
                boton.className = 'boton boton-tonal venta-sugerencia';
                boton.textContent = exacto ? 'Exacto' : pesos(valor * 100);
                boton.setAttribute('aria-label', exacto
                    ? 'Recibido exacto, ' + pesos(valor * 100)
                    : 'Recibido ' + pesos(valor * 100));

                boton.addEventListener('click', function () {
                    if (campoRecibido !== null) {
                        campoRecibido.value = String(valor);
                    }

                    evaluarCobro();
                });

                sugerencias.appendChild(boton);
            }(valores[i], i === 0));
        }
    }

    function abrirCobro() {
        if (dialogo === null || secuencia.length === 0 || !hayTurno || cobroAbierto()) {
            return;
        }

        pintarTotalesDelCobro();
        pintarSugerencias(totales().centavos);

        /* El servidor ya deja marcado efectivo, que es tambien el valor por
           omision de la columna medio_pago. Esto solo cubre que la vista cambie
           y no venga ninguno marcado: sin medio de pago no hay venta. */
        if (medioElegido() === '' && medios.length > 0) {
            medios[0].checked = true;
        }

        /* Se abre en limpio: un importe de un intento anterior que se cancelo
           induciria a confirmar un cambio que no corresponde a esta orden. */
        if (campoRecibido !== null) {
            campoRecibido.value = '';
        }

        evaluarCobro();

        if (typeof dialogo.showModal === 'function') {
            dialogo.showModal();
        } else {
            dialogo.setAttribute('open', 'open');
        }
    }

    /** Abierto se pregunta por el atributo y no por la propiedad .open:
        showModal() lo pone igual, y asi la respuesta es la misma tanto por el
        camino nativo como por el de respaldo. */
    function cobroAbierto() {
        return dialogo !== null && dialogo.hasAttribute('open');
    }

    function cerrarCobro() {
        if (dialogo === null) {
            return;
        }

        if (typeof dialogo.close === 'function') {
            dialogo.close();
        } else {
            dialogo.removeAttribute('open');
        }

        /* Cerrado el dialogo, confirmar vuelve a estar deshabilitado: deja de
           ser el boton por omision del formulario y ningun Enter suelto de la
           pantalla puede dispararlo. */
        if (botonConfirmar !== null) {
            botonConfirmar.disabled = true;
        }
    }

    /* -------------------------------------------- tope de la orden ========

       El catalogo fluye y la columna de la orden va pegada. Para que el boton
       de cobro entre en la primera pantalla aunque la orden lleve quince
       renglones, la columna no puede pasar de lo que va desde donde nace hasta
       el borde de abajo de la ventana. Eso se mide.

       Se mide .venta-zonas y no la propia columna: la columna es sticky y, con
       la pagina desplazada, su rectangulo devuelve la posicion pegada y no la
       natural. La reticula no se mueve, asi que su posicion en el documento
       —rectangulo mas desplazamiento— es la misma siempre. */

    var HOLGURA_INFERIOR = 24;

    function medirTope() {
        raiz.style.removeProperty('--venta-tope');

        if (zonas === null || typeof window.matchMedia !== 'function'
                || !window.matchMedia(DOS_ZONAS).matches) {
            return;
        }

        var rectangulo = zonas.getBoundingClientRect();
        var desdeArriba = rectangulo.top + (window.pageYOffset || 0);
        var tope = window.innerHeight - desdeArriba - HOLGURA_INFERIOR;

        /* Por debajo de esto la columna no cabe ni con la cabecera y el resumen,
           y limitarla solo la dejaria ilegible: mas vale que crezca. */
        if (tope > 260) {
            raiz.style.setProperty('--venta-tope', Math.floor(tope) + 'px');
        }
    }

    /* ------------------------------------------------------------ arranque */

    var i;

    /* Mejora progresiva: el servidor emite las fichas deshabilitadas porque sin
       JavaScript no pueden hacer nada. Se habilitan aqui, y a la vez se ocultan
       los campos de cantidad, que pasan a llevarse solos. */
    for (i = 0; i < fichas.length; i += 1) {
        (function (ficha) {
            ficha.boton.disabled = !hayTurno;
            ficha.boton.addEventListener('click', function () {
                agregar(ficha.producto.id);
            });
        }(fichas[i]));
    }

    /* Una orden que vuelve del servidor con error trae las cantidades ya
       escritas en los campos: se recompone en el mismo orden del catalogo. */
    for (i = 0; i < fichas.length; i += 1) {
        var previa = aEntero(fichas[i].campo.value);

        if (previa > 0) {
            agregar(fichas[i].producto.id);

            if (previa > 1) {
                cambiar(fichas[i].producto.id, previa - 1);
            }
        }
    }

    if (busqueda !== null) {
        busqueda.addEventListener('input', filtrar);

        /* Escape limpia la busqueda y devuelve el catalogo entero. En un
           type=search algunos navegadores ya lo hacen; aqui se hace en todos. */
        busqueda.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && busqueda.value !== '') {
                e.preventDefault();
                busqueda.value = '';
                filtrar();
            }
        });
    }

    for (i = 0; i < chips.length; i += 1) {
        (function (chip) {
            chip.addEventListener('click', function () {
                elegirCategoria(chip.getAttribute('data-venta-categoria') || '');
            });
        }(chips[i]));
    }

    if (botonVaciar !== null) {
        botonVaciar.addEventListener('click', vaciar);
    }

    if (botonCobrar !== null) {
        botonCobrar.addEventListener('click', abrirCobro);
    }

    if (botonCancelar !== null) {
        botonCancelar.addEventListener('click', cerrarCobro);
    }

    if (dialogo !== null) {
        /* Escape y el cierre nativo no pasan por cerrarCobro(), asi que el
           mismo apagado del boton se engancha tambien al evento. */
        dialogo.addEventListener('close', function () {
            if (botonConfirmar !== null) {
                botonConfirmar.disabled = true;
            }
        });
    }

    for (i = 0; i < medios.length; i += 1) {
        medios[i].addEventListener('change', evaluarCobro);
    }

    if (campoRecibido !== null) {
        campoRecibido.addEventListener('input', function () {
            /* Se limpia solo cuando hay algo que limpiar: reescribir el valor
               en cada pulsacion manda el cursor al final del campo. */
            var limpio = campoRecibido.value.replace(/\D/g, '');

            if (limpio !== campoRecibido.value) {
                campoRecibido.value = limpio;
            }

            evaluarCobro();
        });
    }

    if (forma !== null) {
        forma.addEventListener('submit', function (e) {
            /* Envio implicito. Toda la pantalla es un solo formulario, asi que
               un Enter en el buscador dispararia el boton por omision —el de
               confirmar— y la venta saldria sin pasar por el dialogo. La unica
               salida valida es con el dialogo abierto. */
            if (!cobroAbierto()) {
                e.preventDefault();

                return;
            }

            /* Doble envio. El servidor ya lo corta rotando el token CSRF, pero
               un segundo POST en camino deja al cajero mirando una pantalla que
               no responde. */
            if (botonConfirmar !== null) {
                botonConfirmar.disabled = true;
            }
        });
    }

    var esperaMedida = null;

    window.addEventListener('resize', function () {
        if (esperaMedida !== null) {
            window.clearTimeout(esperaMedida);
        }

        esperaMedida = window.setTimeout(function () {
            esperaMedida = null;
            medirTope();
        }, 120);
    });

    medirTope();
    filtrar();
    actualizar();
}());
