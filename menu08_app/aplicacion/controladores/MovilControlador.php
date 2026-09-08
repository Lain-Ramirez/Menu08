<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Ubicacion;
use Menu08\Modelos\Usuario;
use Menu08\Nucleo\Bitacora;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\DatosInvalidos;
use Menu08\Nucleo\ManejadorErrores;
use Menu08\Nucleo\Sesion;
use Menu08\Nucleo\Validador;

/**
 * Servicios JSON que consume la aplicacion movil.
 *
 * El APK no tiene navegador: no sabe rellenar un formulario, no sigue una
 * redireccion a una pantalla de ingreso y no puede leer el token de una
 * etiqueta <meta>. Por eso el recorrido del panel no le sirve y estos
 * servicios existen aparte, respondiendo siempre un objeto, tambien al fallar.
 *
 * Los POST llegan como application/x-www-form-urlencoded porque el nucleo lee
 * el token de $_POST['_token'] y $_POST solo se llena con ese tipo de cuerpo.
 * Enviandolo asi, ni Csrf ni Controlador tienen que cambiar.
 */
final class MovilControlador extends Controlador
{
    /**
     * Ingreso de la aplicacion movil.
     *
     * Devuelve tres cosas que el cliente necesita para todo lo demas: quien es
     * —con su rol y su food_truck_id—, la cookie de sesion, que viaja en la
     * cabecera, y el token contra falsificacion de peticiones que el servicio
     * de ubicacion va a exigir.
     *
     * Es el UNICO servicio /movil que no pasa por verificarCsrfApi(): el token
     * nace con la sesion y aqui todavia no hay ninguna, asi que exigirlo seria
     * pedirle al cliente algo que solo esta peticion puede darle.
     *
     * Tampoco se llama a Csrf::rotar() al terminar, igual que en los servicios
     * del SVP y al contrario que CajaControlador::vender(): la aplicacion
     * ingresa una vez y reporta su posicion muchas, y rotar el token la
     * obligaria a volver a ingresar entre un reporte y el siguiente.
     */
    public function ingresar(): void
    {
        // Desde aqui, hasta un fallo no previsto sale como objeto y no como
        // pagina de error. No sirve exigirRolApi(), que es lo que lo activa en
        // los demas servicios: aun no hay sesion cuyo rol se pueda exigir.
        ManejadorErrores::responderEnJson();

        $correo = trim((string) ($_POST['correo'] ?? ''));
        $clave  = (string) ($_POST['contrasena'] ?? '');

        // Se comprueba antes de consultar: un cuerpo vacio no llega a la tabla.
        if ($correo === '' || $clave === '') {
            $this->jsonError(
                'datos_incompletos',
                'Faltan el correo o la contraseña.',
                422
            );
        }

        $usuario = Usuario::porCorreo($correo);

        // Un solo desenlace para los tres motivos —correo inexistente, clave
        // equivocada y cuenta desactivada— porque distinguirlos delataria que
        // cuentas existen y cuales estan activas. Es la misma regla que aplica
        // AutenticacionControlador::ingresar() en el recorrido del navegador.
        if (!Usuario::claveCorrecta($usuario, $clave) || (int) $usuario['activo'] !== 1) {
            Bitacora::registrar(
                sprintf('Ingreso movil fallido para el correo "%s"', $correo),
                'AVISO'
            );

            $this->jsonError(
                'credenciales_invalidas',
                'Correo o contraseña incorrectos.',
                401
            );
        }

        Usuario::recifrarSiHaceFalta((int) $usuario['id'], (string) $usuario['contrasena'], $clave);
        Usuario::registrarIngreso((int) $usuario['id']);

        Sesion::autenticar($usuario);

        // La sesion guarda exactamente las cinco claves publicas del usuario,
        // asi que se copian de ahi y no de la fila: la contrasena cifrada que
        // devuelve Usuario::porCorreo() no puede colarse en la respuesta.
        $this->json([
            'usuario'    => Sesion::usuario(),
            'token_csrf' => Csrf::token(),
        ]);
    }

    /**
     * Reporte del punto donde esta parado el truck.
     *
     * El rol es el mismo que exige UbicacionControlador en sus cuatro
     * acciones: solo food_truck. Abrir aqui la puerta al cajero o a produccion
     * seria dar por el telefono un permiso que el panel no da.
     *
     * La regla de que se hace con el punto —actualizar la parada vigente o
     * registrar una nueva— no vive aqui: la guarda Ubicacion::asentarPunto(),
     * que es quien tiene la transaccion. Este metodo valida la entrada,
     * traduce la negativa del modelo a su codigo y elige entre 200 y 201.
     */
    public function ubicacion(): void
    {
        $this->exigirRolApi('food_truck');
        $this->verificarCsrfApi();

        $v = new Validador();

        $v->coordenada('latitud', $_POST['latitud'] ?? '', -90.0, 90.0, 'La latitud');
        $v->coordenada('longitud', $_POST['longitud'] ?? '', -180.0, 180.0, 'La longitud');

        // Aqui las dos son obligatorias, al reves que en el formulario del
        // panel: un reporte del GPS sin punto no es un reporte. Como
        // coordenada() acepta el vacio como NULL y no deja mensaje, el caso se
        // marca a mano para que la respuesta diga cual falto.
        foreach (['latitud' => 'La latitud', 'longitud' => 'La longitud'] as $campo => $etiqueta) {
            if ($v->valor($campo) === null && !isset($v->errores()[$campo])) {
                $v->error($campo, sprintf('%s es obligatoria.', $etiqueta));
            }
        }

        if (!$v->correcto()) {
            $this->jsonError(
                'coordenadas_invalidas',
                implode(' ', $v->errores()),
                422
            );
        }

        try {
            $asiento = Ubicacion::asentarPunto(
                $this->foodTruckActual(),
                (string) $v->valor('latitud'),
                (string) $v->valor('longitud')
            );
        } catch (DatosInvalidos $e) {
            $this->jsonError('food_truck_invalido', $e->getMessage(), 422);
        }

        // 201 solo cuando el reporte creo una parada: el cliente lo usa para
        // decir "se registro un punto nuevo" en vez de "se actualizo tu parada".
        $this->json($asiento, $asiento['creada'] ? 201 : 200);
    }
}
