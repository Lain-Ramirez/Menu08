/* turno.js — el teclado de la apertura y el cuadre vivo del cierre.
   Menu08 · carta, caja y produccion para food trucks.

   Solo lo carga /caja/turno. Sin bibliotecas y sin paso de compilacion, igual
   que interfaz.js y caja.js.

   TODO LO DE AQUI ES MEJORA PROGRESIVA. Los dos campos de la pantalla son
   <input> de verdad y los dos formularios se envian solos: sin este archivo se
   teclea con el teclado del dispositivo, la apertura y el cierre funcionan, y lo
   unico que falta es el teclado en pantalla y el adelanto de la diferencia. Por
   eso las teclas salen deshabilitadas del servidor y es este archivo el que las
   habilita: mientras no corra, no prometen nada que no puedan cumplir.

   EL DINERO SE CUENTA EN CENTAVOS, con enteros, igual que caja.js y que
   TurnoCaja::cerrar en el servidor. La diferencia que se ve aqui es informativa:
   la que queda guardada la calcula el servidor al cerrar.

   Issue #19 · Fase 4 - Frontend */

'use strict';

(function () {
    /* Nueve digitos son mil millones de pesos: mas alla no hay una base de caja,
       hay un dedo apoyado en la tecla. */
    var MAXIMO = 9;

    /* ------------------------------------------------------------- utiles */

    function soloDigitos(texto) {
        return String(texto).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    }

    /** Separador de miles a la colombiana, el mismo que number_format del
        servidor. Los centavos no se muestran: la caja se cuenta en billetes. */
    function agrupar(digitos) {
        return digitos.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function pesos(centavos) {
        return '$ ' + agrupar(String(Math.round(Math.abs(centavos) / 100)));
    }

    /** Agrupa lo que se escribe sin estorbar al que escribe. El cursor solo se
        empuja al final cuando ya estaba al final: corrigiendo una cifra por el
        medio, devolverlo al final obligaria a recorrerla otra vez. */
    function formatear(campo) {
        var alFinal = campo.selectionStart === campo.value.length;
        var digitos = soloDigitos(campo.value).slice(0, MAXIMO);

        campo.value = agrupar(digitos);

        if (alFinal && typeof campo.setSelectionRange === 'function') {
            campo.setSelectionRange(campo.value.length, campo.value.length);
        }
    }

    /** Un envio, no dos. El boton se apaga DESPUES del despacho del evento y
        solo si nadie lo detuvo: el dialogo de data-confirmar cancela el primer
        submit para preguntar, y apagarlo ahi dejaria el formulario muerto si el
        cajero responde que no. */
    function evitarDobleEnvio(formulario) {
        var boton = formulario.querySelector('[data-turno-enviar]');

        if (boton === null) {
            return;
        }

        formulario.addEventListener('submit', function (evento) {
            window.setTimeout(function () {
                if (evento.defaultPrevented) {
                    return;
                }

                boton.disabled = true;
            }, 0);
        });
    }

    /* ----------------------------------------------------------- apertura */

    function apertura() {
        var formulario = document.querySelector('[data-turno-apertura]');

        if (formulario === null) {
            return;
        }

        var campo = formulario.querySelector('[data-turno-base]');

        if (campo === null) {
            return;
        }

        var lectura = formulario.querySelector('[data-turno-lectura]');
        var error = formulario.querySelector('[data-turno-base-error]');
        var teclas = formulario.querySelectorAll('[data-turno-tecla]');
        var sugerencias = formulario.querySelectorAll('[data-turno-valor]');
        var i;

        /* El rechazo del servidor deja de senalar en cuanto se corrige: quien ya
           esta escribiendo no necesita que le sigan diciendo que estaba mal. */
        function limpiarRechazo() {
            if (lectura !== null) {
                lectura.classList.remove('turno-lectura-error');
            }

            if (error !== null) {
                error.hidden = true;
            }
        }

        function escribir(digitos) {
            campo.value = agrupar(digitos.slice(0, MAXIMO));
            limpiarRechazo();
        }

        /* El teclado NO devuelve el foco al campo: en una tableta eso levanta el
           teclado del sistema y tapa justo lo que se acaba de marcar, que es
           todo lo que este teclado existe para evitar. */
        function enlazarTecla(tecla) {
            tecla.disabled = false;

            tecla.addEventListener('click', function () {
                var valor = tecla.getAttribute('data-turno-tecla');
                var actual = soloDigitos(campo.value);

                if (valor === 'borrar') {
                    escribir(actual.slice(0, -1));

                    return;
                }

                var siguiente = soloDigitos(actual + valor);

                if (siguiente.length > MAXIMO) {
                    return;
                }

                escribir(siguiente);
            });
        }

        function enlazarSugerencia(boton) {
            boton.disabled = false;

            boton.addEventListener('click', function () {
                escribir(soloDigitos(boton.getAttribute('data-turno-valor')));
            });
        }

        for (i = 0; i < teclas.length; i += 1) {
            enlazarTecla(teclas[i]);
        }

        for (i = 0; i < sugerencias.length; i += 1) {
            enlazarSugerencia(sugerencias[i]);
        }

        campo.addEventListener('input', function () {
            formatear(campo);
            limpiarRechazo();
        });

        evitarDobleEnvio(formulario);
    }

    /* ------------------------------------------------------------- cierre */

    function cierre() {
        var formulario = document.querySelector('[data-turno-cierre]');

        if (formulario === null) {
            return;
        }

        var campo = formulario.querySelector('[data-turno-conteo]');
        var bloque = formulario.querySelector('[data-turno-cuadre]');
        var rotulo = formulario.querySelector('[data-turno-cuadre-rotulo]');
        var valor = formulario.querySelector('[data-turno-cuadre-valor]');
        var iconos = formulario.querySelectorAll('[data-turno-cuadre-icono]');

        if (campo === null || bloque === null || rotulo === null || valor === null) {
            return;
        }

        /* Lo que deberia haber en el cajon, en centavos: base inicial mas lo
           vendido. Lo calcula la vista con los mismos datos con los que el
           servidor calculara la diferencia definitiva. */
        var esperado = Number(formulario.getAttribute('data-turno-esperado'));

        if (!isFinite(esperado)) {
            esperado = 0;
        }

        /* El estado nunca se distingue solo por el color: cada uno cambia el
           fondo, el icono Y la palabra. */
        function estadoDelCuadre() {
            var digitos = soloDigitos(campo.value);

            if (digitos === '') {
                return { clave: 'sin-contar', rotulo: 'Diferencia', valor: '—' };
            }

            var diferencia = (Number(digitos) * 100) - esperado;

            if (diferencia === 0) {
                return { clave: 'cuadra', rotulo: 'La caja cuadra', valor: pesos(0) };
            }

            if (diferencia > 0) {
                return { clave: 'sobrante', rotulo: 'Sobrante', valor: '+' + pesos(diferencia) };
            }

            return { clave: 'faltante', rotulo: 'Faltante', valor: '-' + pesos(diferencia) };
        }

        function pintar() {
            var estado = estadoDelCuadre();
            var i;

            bloque.className = estado.clave === 'sin-contar'
                ? 'turno-diferencia'
                : 'turno-diferencia turno-diferencia-' + estado.clave;

            rotulo.textContent = estado.rotulo;
            valor.textContent = estado.valor;

            for (i = 0; i < iconos.length; i += 1) {
                iconos[i].hidden = iconos[i].getAttribute('data-turno-cuadre-icono') !== estado.clave;
            }
        }

        campo.addEventListener('input', function () {
            formatear(campo);
            pintar();
        });

        /* Al volver con el boton de atras el navegador restaura lo que habia
           escrito, y el bloque tiene que decir lo mismo que el campo. */
        pintar();

        evitarDobleEnvio(formulario);
    }

    apertura();
    cierre();
}());
