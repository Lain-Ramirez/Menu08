/* svp.js — el sondeo, el cronometro y el avance de estado del tablero.
   Menu08 · carta, caja y produccion para food trucks.

   Solo lo carga /svp. Sin bibliotecas y sin paso de compilacion, igual que
   interfaz.js y caja.js.

   CUATRO DECISIONES DE FONDO.

   1. EL MARCADO NO SE ESCRIBE AQUI. La tarjeta la escribe la vista una sola vez
      y la emite ademas vacia dentro de un <template> por estado; este archivo
      clona esa plantilla y rellena texto. Asi no hay dos versiones de la misma
      tarjeta que puedan separarse, y ningun dato entra nunca como HTML.

   2. SE RECONCILIA, NO SE REDIBUJA. Cada sondeo actualiza las tarjetas que ya
      estan, mueve las que cambiaron de columna y quita las que salieron. Volcar
      el tablero entero en cada ciclo perderia la posicion de desplazamiento
      —que es justo lo que el criterio prohibe perder—, haria parpadear la
      pantalla cada pocos segundos y cortaria el cronometro.

   3. EL CRONOMETRO ES DEL NAVEGADOR. Corre cada segundo con lo que ya tiene, sin
      pedir nada: el sondeo solo trae ordenes nuevas y cambios de estado. Y se
      cuenta contra el reloj DEL SERVIDOR: cada respuesta trae su `ahora`, se
      calcula el desfase con el reloj del dispositivo y se descuenta. Una tableta
      mal puesta en hora contaria minutos que no son, y el realce de demora
      saltaria cuando no toca o no saltaria nunca.

   4. UN FALLO DE RED NO BORRA EL TABLERO. Se avisa arriba, las ordenes se quedan
      en pantalla y el ciclo siguiente vuelve a intentarlo. En una cocina, una
      pantalla vacia se lee como «no hay nada que hacer», que es la mentira mas
      cara que puede contar este tablero.

   Issue #20 · Fase 4 - Frontend */

'use strict';

(function () {
    /* Cada cuanto se vuelve a pedir la lista. Este es el unico sitio donde se
       cambia el ritmo del tablero. */
    var INTERVALO = 5000;

    /* Cada cuanto avanza el cronometro de las tarjetas. */
    var LATIDO = 1000;

    var raiz = document.querySelector('[data-svp]');

    if (raiz === null || typeof window.fetch !== 'function') {
        return;
    }

    var RUTA_ORDENES = raiz.getAttribute('data-svp-ordenes');
    var RUTA_AVANCE = raiz.getAttribute('data-svp-avance-base');
    /* El umbral llega pintado por el servidor y cada respuesta del sondeo lo
       vuelve a traer: si se cambia en SvpControlador, los tableros que ya estan
       abiertos se enteran en el ciclo siguiente en vez de quedarse con el de
       cuando se cargaron. */
    var segundosDemora = (Number(raiz.getAttribute('data-svp-demora')) || 10) * 60;

    var ESTADOS = ['pendiente', 'en_preparacion', 'lista'];

    var columnas = raiz.querySelector('[data-svp-columnas]');
    var plantillaLinea = raiz.querySelector('[data-svp-plantilla-linea]');
    var aviso = raiz.querySelector('[data-svp-aviso]');
    var avisoTexto = raiz.querySelector('[data-svp-aviso-texto]');
    var latido = raiz.querySelector('[data-svp-latido]');
    var latidoTexto = raiz.querySelector('[data-svp-latido-texto]');
    var total = raiz.querySelector('[data-svp-total]');
    var totalRotulo = raiz.querySelector('[data-svp-total-rotulo]');
    var vacioCerrado = raiz.querySelector('[data-svp-vacio="cerrado"]');
    var vacioAlDia = raiz.querySelector('[data-svp-vacio="al-dia"]');
    var demoraRotulo = raiz.querySelector('[data-svp-demora-rotulo]');

    /* Lo ultimo que dijo el servidor. Es lo que se vuelve a pintar cuando una
       orden avanza, sin esperar al sondeo siguiente. */
    var tablero = { turno: null, ordenes: [] };

    /* Reloj local menos reloj del servidor, en milisegundos. */
    var desfase = 0;

    var ultimoSondeo = null;
    var sondeando = false;

    /* ------------------------------------------------------------- tiempo */

    /** Convierte "2026-09-11 19:42:11" en Date.

        A mano y no con new Date(cadena): ese formato no esta en la norma y
        Safari lo ha rechazado historicamente, asi que el tablero se quedaria sin
        cronometro justo en la tableta mas probable de una ventanilla. */
    function fecha(marca) {
        var p = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/.exec(String(marca || ''));

        if (p === null) {
            return null;
        }

        return new Date(Number(p[1]), Number(p[2]) - 1, Number(p[3]), Number(p[4]), Number(p[5]), Number(p[6]));
    }

    /** Segundos desde que CAJA registro la orden, contra el reloj del servidor. */
    function transcurrido(marca) {
        var inicio = fecha(marca);

        if (inicio === null) {
            return 0;
        }

        return Math.max(0, Math.floor(((new Date()).getTime() - desfase - inicio.getTime()) / 1000));
    }

    /** MM:SS, y H:MM:SS cuando pasa de la hora. Es el mismo formato que escribe
        $cronometro en la vista: si cambia uno, cambia el otro. */
    function cronometro(segundos) {
        var horas = Math.floor(segundos / 3600);
        var minutos = Math.floor((segundos % 3600) / 60);
        var resto = segundos % 60;

        function dos(n) {
            return n < 10 ? '0' + n : String(n);
        }

        return horas > 0
            ? horas + ':' + dos(minutos) + ':' + dos(resto)
            : dos(minutos) + ':' + dos(resto);
    }

    function hora(marca) {
        var d = fecha(marca);

        function dos(n) {
            return n < 10 ? '0' + n : String(n);
        }

        return d === null ? '--:--' : dos(d.getHours()) + ':' + dos(d.getMinutes());
    }

    /* -------------------------------------------------------- las tarjetas */

    function plantilla(estado) {
        var molde = raiz.querySelector('[data-svp-plantilla="' + estado + '"]');

        return molde === null ? null : molde.content.firstElementChild.cloneNode(true);
    }

    /** Rellena una tarjeta con los datos de una orden. El cronometro no se toca
        aqui: lo lleva tic(), que corre cada segundo para todas. */
    function pintar(nodo, orden) {
        nodo.setAttribute('data-svp-orden', String(orden.id));
        nodo.setAttribute('data-svp-estado', orden.estado);
        nodo.setAttribute('data-svp-creado', orden.creado_en || '');

        nodo.querySelector('[data-svp-numero]').textContent = orden.numero;
        nodo.querySelector('[data-svp-recibida]').textContent = hora(orden.creado_en);

        var lineas = nodo.querySelector('[data-svp-lineas]');
        var items = orden.items || [];
        var i;

        /* Los renglones no cambian en la vida de una orden, asi que solo se
           escriben cuando la tarjeta es nueva o llegan distintos. */
        if (lineas.children.length !== items.length) {
            lineas.textContent = '';

            for (i = 0; i < items.length; i += 1) {
                var linea = plantillaLinea.content.firstElementChild.cloneNode(true);

                linea.querySelector('.svp-cantidad').textContent = String(items[i].cantidad);
                linea.querySelector('.svp-producto').textContent = items[i].nombre;
                lineas.appendChild(linea);
            }
        }

        var nota = nodo.querySelector('[data-svp-nota]');

        nota.hidden = !orden.nota;
        nodo.querySelector('[data-svp-nota-texto]').textContent = orden.nota || '';

        /* La plantilla trae el boton deshabilitado, como lo emite la vista: es
           este archivo el que lo habilita, y solo si no hay un envio en vuelo
           para esa misma orden. */
        var boton = nodo.querySelector('[data-svp-avance]');

        if (boton !== null) {
            boton.disabled = nodo.getAttribute('data-svp-enviando') === '1';
        }

        marcarDemora(nodo, orden.demorada === true);
    }

    /** El realce. Nunca es solo color: la tarjeta cambia de fondo y de filete, el
        cronometro engorda y la etiqueta pone la palabra. */
    function marcarDemora(nodo, demorada) {
        var etiqueta = nodo.querySelector('[data-svp-demora]');

        if (demorada) {
            nodo.classList.add('svp-tarjeta-demorada');
        } else {
            nodo.classList.remove('svp-tarjeta-demorada');
        }

        if (etiqueta !== null) {
            etiqueta.hidden = !demorada;
        }
    }

    /* ------------------------------------------------------ reconciliacion */

    function reconciliar() {
        var e;
        var enCurso = tablero.ordenes || [];

        for (e = 0; e < ESTADOS.length; e += 1) {
            reconciliarColumna(ESTADOS[e], enCurso.filter(function (o) {
                return o.estado === ESTADOS[e];
            }));
        }

        var hayTurno = tablero.turno !== null && tablero.turno !== undefined;

        if (columnas !== null) {
            columnas.hidden = !hayTurno || enCurso.length === 0;
        }

        if (vacioCerrado !== null) {
            vacioCerrado.hidden = hayTurno;
        }

        if (vacioAlDia !== null) {
            vacioAlDia.hidden = !hayTurno || enCurso.length > 0;
        }

        if (total !== null) {
            total.textContent = String(enCurso.length);
        }

        if (totalRotulo !== null) {
            totalRotulo.textContent = enCurso.length === 1 ? 'orden en curso' : 'órdenes en curso';
        }

        tic();
    }

    function reconciliarColumna(estado, ordenes) {
        var columna = raiz.querySelector('[data-svp-columna="' + estado + '"]');

        if (columna === null) {
            return;
        }

        var lista = columna.querySelector('[data-svp-lista]');
        var vacia = columna.querySelector('[data-svp-columna-vacia]');
        var cuenta = columna.querySelector('[data-svp-cuenta]');
        var vivas = {};
        var i;

        for (i = 0; i < ordenes.length; i += 1) {
            vivas[ordenes[i].id] = true;
        }

        /* Primero se van las que ya no pertenecen a esta columna: entregadas, o
           movidas a la siguiente. */
        var actuales = Array.prototype.slice.call(lista.children);

        for (i = 0; i < actuales.length; i += 1) {
            if (vivas[actuales[i].getAttribute('data-svp-orden')] !== true) {
                lista.removeChild(actuales[i]);
            }
        }

        /* Y despues se colocan en su sitio, en el orden que manda el servidor:
           lo mas antiguo primero, que es lo mas urgente. */
        for (i = 0; i < ordenes.length; i += 1) {
            var orden = ordenes[i];
            var nodo = lista.querySelector('[data-svp-orden="' + orden.id + '"]');

            if (nodo === null) {
                nodo = plantilla(orden.estado);

                if (nodo === null) {
                    continue;
                }
            }

            pintar(nodo, orden);

            if (lista.children[i] !== nodo) {
                lista.insertBefore(nodo, lista.children[i] || null);
            }
        }

        if (cuenta !== null) {
            cuenta.textContent = String(ordenes.length);
        }

        if (vacia !== null) {
            vacia.hidden = ordenes.length > 0;
        }
    }

    /* --------------------------------------------------------- el segundero */

    /** Reescribe los cronometros y enciende el realce de las que acaban de pasar
        el umbral, sin esperar al sondeo siguiente. */
    function tic() {
        var tarjetas = raiz.querySelectorAll('[data-svp-tarjeta]');
        var i;

        for (i = 0; i < tarjetas.length; i += 1) {
            var nodo = tarjetas[i];
            var marca = nodo.getAttribute('data-svp-creado');

            if (!marca) {
                continue;
            }

            var segundos = transcurrido(marca);

            nodo.querySelector('[data-svp-reloj]').textContent = cronometro(segundos);

            /* El tiempo solo va hacia delante: lo que ya estaba demorado no
               vuelve a estar a tiempo porque el sondeo diga otra cosa. */
            if (segundos >= segundosDemora) {
                marcarDemora(nodo, true);
            }
        }

        latir();
    }

    /** Cuanto hace del ultimo sondeo correcto. Es lo que distingue una cocina al
        dia de una pantalla congelada. */
    function latir() {
        if (latidoTexto === null || ultimoSondeo === null) {
            return;
        }

        var segundos = Math.max(0, Math.round(((new Date()).getTime() - ultimoSondeo) / 1000));

        latidoTexto.textContent = segundos < 2
            ? 'Actualizado ahora'
            : 'Actualizado hace ' + segundos + ' s';
    }

    /** Aviso temporal, el de interfaz.js. Si por lo que sea no esta cargado, el
        tablero sigue funcionando sin el en lugar de romperse a medio avance. */
    function avisar(texto, tipo) {
        if (typeof Interfaz !== 'undefined' && typeof Interfaz.aviso === 'function') {
            Interfaz.aviso(texto, tipo);
        }
    }

    /* -------------------------------------------------------- avisos de red */

    function fallar(texto) {
        if (aviso !== null && avisoTexto !== null) {
            avisoTexto.textContent = texto;
            aviso.hidden = false;
        }

        if (latido !== null) {
            latido.classList.add('svp-latido-caido');
        }
    }

    function recuperar() {
        if (aviso !== null) {
            aviso.hidden = true;
        }

        if (latido !== null) {
            latido.classList.remove('svp-latido-caido');
        }
    }

    /** Error con un mensaje pensado para leerse desde la pared.

        La marca importa: cuando fetch rechaza por su cuenta trae cosas como
        «Failed to fetch», que no le dicen nada a quien esta en la plancha. Solo
        se muestran los mensajes escritos aqui; el resto se traduce a uno que si
        explica que hacer. */
    function errorLegible(texto) {
        var error = new Error(texto);

        error.paraLeer = true;

        return error;
    }

    /** Traduce la negativa del servicio a algo que se pueda leer desde la pared.
        Los servicios del SVP responden JSON tambien al fallar, asi que aqui no
        hay que adivinar: 401 y 403 son la sesion, el resto es el servidor. */
    function motivo(estado) {
        if (estado === 401 || estado === 403) {
            return 'La sesión del tablero caducó. Vuelva a entrar para que las órdenes sigan llegando.';
        }

        return 'No se pudo consultar las órdenes (error ' + estado + '). Se reintenta solo en unos segundos.';
    }

    /* ------------------------------------------------------------- sondeo */

    function sondear() {
        if (sondeando) {
            return;
        }

        sondeando = true;

        fetch(RUTA_ORDENES, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (respuesta) {
            if (!respuesta.ok) {
                throw errorLegible(motivo(respuesta.status));
            }

            return respuesta.json();
        }).then(function (datos) {
            var servidor = fecha(datos.ahora);

            if (servidor !== null) {
                desfase = (new Date()).getTime() - servidor.getTime();
            }

            var umbral = Number(datos.minutos_demora);

            if (isFinite(umbral) && umbral > 0) {
                segundosDemora = umbral * 60;

                if (demoraRotulo !== null) {
                    demoraRotulo.textContent = String(umbral);
                }
            }

            tablero = { turno: datos.turno, ordenes: datos.ordenes || [] };
            ultimoSondeo = (new Date()).getTime();

            recuperar();
            reconciliar();
        }).catch(function (error) {
            /* Las ordenes que ya estan en pantalla se quedan: son viejas, pero
               son las ultimas que se supieron. */
            fallar(error && error.paraLeer
                ? error.message
                : 'Se perdió la conexión con el servidor. Las órdenes en pantalla son las últimas que se supieron.');
        }).then(function () {
            sondeando = false;
        });
    }

    /* ------------------------------------------------- avance de una orden */

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta === null ? '' : meta.getAttribute('content');
    }

    function avanzar(boton) {
        var tarjeta = boton.closest('[data-svp-tarjeta]');

        if (tarjeta === null) {
            return;
        }

        var id = tarjeta.getAttribute('data-svp-orden');
        var destino = boton.getAttribute('data-svp-avance');

        /* Mientras el envio viaja, el boton no admite otro: dos pulsaciones
           seguidas intentarian avanzar dos casillas de una vez. El servidor
           tambien lo frena —la orden ya movida devuelve 422—, pero el tablero no
           tiene por que llegar a preguntarlo. */
        boton.disabled = true;
        tarjeta.setAttribute('data-svp-enviando', '1');

        fetch(RUTA_AVANCE + encodeURIComponent(id) + '/estado', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json'
            },
            body: 'estado=' + encodeURIComponent(destino) + '&_token=' + encodeURIComponent(token())
        }).then(function (respuesta) {
            if (respuesta.status === 422) {
                /* Alguien la movio desde el otro tablero mientras tanto. No es un
                   error que haya que arreglar: se vuelve a preguntar y la
                   pantalla se pone al dia sola. */
                avisar('Esa orden ya la movieron desde otro sitio. Tablero actualizado.', 'aviso');
                sondear();

                return null;
            }

            if (!respuesta.ok) {
                throw errorLegible(respuesta.status === 401 || respuesta.status === 403
                    ? 'La sesión caducó: vuelva a entrar para mover órdenes.'
                    : 'No se pudo mover la orden (error ' + respuesta.status + ').');
            }

            return respuesta.json();
        }).then(function (datos) {
            tarjeta.removeAttribute('data-svp-enviando');

            if (datos === null) {
                return;
            }

            /* La tarjeta se mueve con lo que ya se sabe, sin esperar al sondeo:
               en la plancha, el medio segundo de espera se nota. */
            aplicarAvance(Number(id), destino);

            /* Y aun asi se vuelve a preguntar, porque entre medias pudieron
               entrar ordenes nuevas desde CAJA. */
            sondear();
        }).catch(function (error) {
            tarjeta.removeAttribute('data-svp-enviando');
            boton.disabled = false;
            avisar(error && error.paraLeer
                ? error.message
                : 'No se pudo mover la orden: se perdió la conexión. Vuelva a intentarlo.', 'error');
        });
    }

    /** Mueve la orden en la copia local y repinta. Si el destino es "entregada"
        sale del tablero: ya no esta en curso. */
    function aplicarAvance(id, destino) {
        var ordenes = [];
        var i;

        for (i = 0; i < tablero.ordenes.length; i += 1) {
            var orden = tablero.ordenes[i];

            if (orden.id !== id) {
                ordenes.push(orden);

                continue;
            }

            if (destino === 'entregada') {
                continue;
            }

            orden.estado = destino;
            ordenes.push(orden);
        }

        tablero.ordenes = ordenes;
        reconciliar();
    }

    /* ---------------------------------------------------------- arranque */

    /* Un solo oyente para todo el tablero: las tarjetas van y vienen con cada
       sondeo, y engancharlas una a una dejaria oyentes colgando. */
    raiz.addEventListener('click', function (evento) {
        var boton = evento.target.closest ? evento.target.closest('[data-svp-avance]') : null;

        if (boton !== null && !boton.disabled) {
            avanzar(boton);
        }
    });

    /* Los botones llegan deshabilitados del servidor: sin este archivo no pueden
       mover nada. */
    function habilitar() {
        var botones = raiz.querySelectorAll('[data-svp-avance]');
        var i;

        for (i = 0; i < botones.length; i += 1) {
            botones[i].disabled = false;
        }
    }

    /* El primer pintado ya lo hizo el servidor: de el salen el reloj y las
       ordenes con las que arranca el cronometro, para que la pantalla no espere
       al primer sondeo para contar. */
    var arranque = fecha(raiz.getAttribute('data-svp-ahora'));

    if (arranque !== null) {
        desfase = (new Date()).getTime() - arranque.getTime();
        ultimoSondeo = (new Date()).getTime();
    }

    habilitar();
    tic();

    window.setInterval(tic, LATIDO);
    window.setInterval(sondear, INTERVALO);
    sondear();
}());
