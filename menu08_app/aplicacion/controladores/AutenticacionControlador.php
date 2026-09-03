<?php

declare(strict_types=1);

namespace Menu08\Controladores;

use Menu08\Modelos\Usuario;
use Menu08\Nucleo\Bitacora;
use Menu08\Nucleo\Controlador;
use Menu08\Nucleo\Sesion;

/**
 * Ingreso y salida del panel.
 */
final class AutenticacionControlador extends Controlador
{
    /**
     * Destino de cada rol despues de ingresar.
     */
    private const INICIO_POR_ROL = [
        'plataforma' => '/panel',
        'food_truck' => '/panel',
        'cajero'     => '/caja',
        'produccion' => '/svp',
    ];

    public function formulario(): void
    {
        if (Sesion::autenticado()) {
            $this->redirigir(self::INICIO_POR_ROL[Sesion::rol()] ?? '/panel');
        }

        $this->vista('auth/ingresar', ['correo' => ''], 'Ingresar');
    }

    public function ingresar(): void
    {
        $this->verificarCsrf();

        $correo = trim((string) ($_POST['correo'] ?? ''));
        $clave  = (string) ($_POST['contrasena'] ?? '');

        $usuario = $correo === '' ? null : Usuario::porCorreo($correo);

        // El mensaje es el mismo para correo inexistente, contraseña equivocada
        // y cuenta desactivada: decir cual de los tres fue delataria que cuentas
        // existen y cuales estan activas.
        if (!Usuario::claveCorrecta($usuario, $clave) || (int) $usuario['activo'] !== 1) {
            Bitacora::registrar(sprintf('Ingreso fallido para el correo "%s"', $correo), 'AVISO');

            $this->vista(
                'auth/ingresar',
                ['correo' => $correo, 'error' => 'Correo o contraseña incorrectos.'],
                'Ingresar',
                401
            );

            return;
        }

        Usuario::recifrarSiHaceFalta((int) $usuario['id'], (string) $usuario['contrasena'], $clave);
        Usuario::registrarIngreso((int) $usuario['id']);

        Sesion::autenticar($usuario);
        Sesion::mensaje(sprintf('Bienvenido, %s.', $usuario['nombre']), 'exito');

        $this->redirigir(self::INICIO_POR_ROL[$usuario['rol']] ?? '/panel');
    }

    public function salir(): void
    {
        Sesion::cerrar();
        Sesion::iniciar();
        Sesion::mensaje('Sesion cerrada.', 'exito');

        $this->redirigir('/ingresar');
    }
}
