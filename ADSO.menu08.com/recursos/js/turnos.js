/* turnos.js — el sondeo y refresco automatico de la pantalla publica de turnos.
   Menu08 · carta, caja y produccion para food trucks.

   Solo lo carga /turnos/{slug}. Sin bibliotecas y sin paso de compilacion,
   igual que interfaz.js y svp.js.

   PRINCIPIOS DE DISENO:
   1. SE RECONCILIA, NO SE REDIBUJA. Cada sondeo actualiza las tarjetas existentes,
      agrega las nuevas y retira las que avanzaron a entregada. De esta forma no
      hay parpadeos en el televisor ni saltos bruscos.
   2. UN FALLO DE CONEXION NO BORRA LA PANTALLA. Si se cae la red o el servidor
      tarda en responder, la pantalla conserva los ultimos numeros conocidos y muestra
      el aviso de reconexion automatica.
   3. PANTALLA COMPLETA INTEGRADA. Permite alternar a pantalla completa con un clic
      o toque para exhibicion en kiosco o monitor de ventanilla.

   Issue #38 · Fase 5 - Pruebas */

'use strict';

(function () {
    /* Intervalo de sondeo regular: cada 4 segundos */
    var INTERVALO = 4000;

    /* Latido de interfaz para el indicador de tiempo transcurrido */
    var LATIDO = 1000;

    var raiz = document.querySelector('[data-turnos]');

    if (raiz === null || typeof window.fetch !== 'function') {
        return;
    }

    var RUTA_SERVICIO = raiz.getAttribute('data-turnos-servicio');

    if (!RUTA_SERVICIO) {
        return;
    }

    var ESTADOS = ['en_preparacion', 'lista'];

    var columnas = raiz.querySelector('[data-turnos-columnas]');
    var vacioCerrado = raiz.querySelector('[data-turnos-cerrado]');
    var aviso = raiz.querySelector('[data-turnos-aviso]');
    var avisoTexto = raiz.querySelector('[data-turnos-aviso-texto]');
    var latido = raiz.querySelector('[data-turnos-latido]');
    var latidoTexto = raiz.querySelector('[data-turnos-latido-texto]');
    var btnPantalla = raiz.querySelector('[data-turnos-btn-pantalla]');
    var btnTexto = raiz.querySelector('[data-turnos-btn-texto]');

    var ultimoSondeo = null;
    var sondeando = false;

    /* Copia local de las ordenes vigentes */
    var ordenesLocales = [];
    var turnoActual = null;

    /* -------------------------------------------------------- plantillas */

    function obtenerMolde(estado) {
        var tpl = raiz.querySelector('[data-turnos-plantilla="' + estado + '"]');

        if (tpl === null || !tpl.content || !tpl.content.firstElementChild) {
            return null;
        }

        return tpl.content.firstElementChild.cloneNode(true);
    }

    /* ---------------------------------------------------- reconciliacion */

    function reconciliarColumna(estado, ordenes) {
        var columna = raiz.querySelector('[data-turnos-columna="' + estado + '"]');

        if (columna === null) {
            return;
        }

        var lista = columna.querySelector('[data-turnos-lista="' + estado + '"]');
        var vacia = columna.querySelector('[data-turnos-vacia="' + estado + '"]');
        var cuenta = columna.querySelector('[data-turnos-cuenta="' + estado + '"]');

        if (lista === null) {
            return;
        }

        var vivas = {};
        var i;

        for (i = 0; i < ordenes.length; i += 1) {
            vivas[ordenes[i].numero] = true;
        }

        /* 1. Retirar las ordenes que ya no estan en este estado */
        var actuales = Array.prototype.slice.call(lista.children);

        for (i = 0; i < actuales.length; i += 1) {
            var num = actuales[i].getAttribute('data-turno-numero');

            if (!vivas[num]) {
                lista.removeChild(actuales[i]);
            }
        }

        /* 2. Insertar o reposicionar las ordenes vigentes */
        for (i = 0; i < ordenes.length; i += 1) {
            var o = ordenes[i];
            var nodo = lista.querySelector('[data-turno-numero="' + o.numero + '"]');

            if (nodo === null) {
                nodo = obtenerMolde(estado);

                if (nodo === null) {
                    continue;
                }

                nodo.setAttribute('data-turno-numero', o.numero);

                var spanNum = nodo.querySelector('.turno-numero');

                if (spanNum !== null) {
                    spanNum.textContent = o.numero;
                } else {
                    nodo.textContent = o.numero;
                }
            }

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

    function reconciliar(datos) {
        turnoActual = datos.turno;
        ordenesLocales = datos.ordenes || [];

        var hayTurno = turnoActual !== null && turnoActual !== undefined;

        if (vacioCerrado !== null) {
            vacioCerrado.hidden = hayTurno;
        }

        if (columnas !== null) {
            columnas.hidden = !hayTurno;
        }

        if (hayTurno) {
            for (var e = 0; e < ESTADOS.length; e += 1) {
                var est = ESTADOS[e];
                var filtradas = ordenesLocales.filter(function (ord) {
                    return ord.estado === est;
                });

                reconciliarColumna(est, filtradas);
            }
        }
    }

    /* ---------------------------------------------------- latido y reloj */

    function latir() {
        if (latidoTexto === null || ultimoSondeo === null) {
            return;
        }

        var segundos = Math.max(0, Math.round((Date.now() - ultimoSondeo) / 1000));

        latidoTexto.textContent = segundos < 2
            ? 'Actualizado ahora'
            : 'Actualizado hace ' + segundos + ' s';
    }

    /* --------------------------------------------------- avisos de red */

    function mostrarFallo(texto) {
        if (aviso !== null && avisoTexto !== null) {
            avisoTexto.textContent = texto;
            aviso.hidden = false;
        }

        if (latido !== null) {
            latido.classList.add('turnos-latido-caido');
        }

        if (latidoTexto !== null) {
            latidoTexto.textContent = 'Sin conexión';
        }
    }

    function ocultarFallo() {
        if (aviso !== null) {
            aviso.hidden = true;
        }

        if (latido !== null) {
            latido.classList.remove('turnos-latido-caido');
        }
    }

    /* ----------------------------------------------------------- sondeo */

    function sondear() {
        if (sondeando) {
            return;
        }

        sondeando = true;

        fetch(RUTA_SERVICIO, {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        }).then(function (respuesta) {
            if (!respuesta.ok) {
                throw new Error('Error de comunicación con el servidor (HTTP ' + respuesta.status + ').');
            }

            return respuesta.json();
        }).then(function (datos) {
            ultimoSondeo = Date.now();
            ocultarFallo();
            reconciliar(datos);
            latir();
        }).catch(function () {
            /* Las ordenes que estan en pantalla se conservan intactas:
               no se borra nada, solo se advierte que se esta reconectando. */
            mostrarFallo('Se perdió la conexión con el servidor. Reconectando automáticamente... Los turnos en pantalla son los últimos conocidos.');
        }).then(function () {
            sondeando = false;
        });
    }

    /* ------------------------------------------------ pantalla completa */

    function alternarPantallaCompleta() {
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            var el = document.documentElement;

            if (el.requestFullscreen) {
                el.requestFullscreen();
            } else if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }
    }

    function actualizarEstadoPantalla() {
        var esCompleta = Boolean(document.fullscreenElement || document.webkitFullscreenElement);

        if (btnTexto !== null) {
            btnTexto.textContent = esCompleta ? 'Salir de pantalla completa' : 'Pantalla completa';
        }

        if (btnPantalla !== null) {
            btnPantalla.setAttribute('aria-pressed', esCompleta ? 'true' : 'false');
        }
    }

    if (btnPantalla !== null) {
        btnPantalla.addEventListener('click', alternarPantallaCompleta);
    }

    document.addEventListener('fullscreenchange', actualizarEstadoPantalla);
    document.addEventListener('webkitfullscreenchange', actualizarEstadoPantalla);

    /* --------------------------------------------------------- arranque */

    ultimoSondeo = Date.now();
    latir();

    window.setInterval(latir, LATIDO);
    window.setInterval(sondear, INTERVALO);
}());
