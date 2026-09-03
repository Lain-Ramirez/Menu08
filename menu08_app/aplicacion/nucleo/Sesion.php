<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

/**
 * Manejo de la sesion del panel: apertura endurecida, datos del usuario,
 * caducidad por inactividad, cierre y mensajes de un solo uso.
 *
 * La cookie se marca HttpOnly para que JavaScript no pueda leerla, SameSite=Lax
 * para que no viaje en peticiones desde otros sitios, y Secure cuando el sitio
 * se sirve por HTTPS.
 */
final class Sesion
{
    private const CLAVE_USUARIO   = 'usuario';
    private const CLAVE_ACTIVIDAD = 'ultima_actividad';
    private const CLAVE_MENSAJES  = 'mensajes';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $vida = max(5, (int) Configuracion::obtener('sesion.vida_minutos', 120)) * 60;

        session_name((string) Configuracion::obtener('sesion.nombre', 'menu08_sesion'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (bool) Configuracion::obtener('sesion.solo_https', false),
        ]);

        session_start();

        self::caducarPorInactividad($vida);
    }

    /**
     * Deja constancia del usuario recien autenticado.
     *
     * Se renueva el identificador de sesion para que el que pudiera conocer un
     * tercero antes del ingreso quede inservible: es la defensa contra la
     * fijacion de sesion.
     *
     * @param array<string, mixed> $usuario
     */
    public static function autenticar(array $usuario): void
    {
        session_regenerate_id(true);

        $_SESSION[self::CLAVE_USUARIO] = [
            'id'            => (int) $usuario['id'],
            'nombre'        => (string) $usuario['nombre'],
            'correo'        => (string) $usuario['correo'],
            'rol'           => (string) $usuario['rol'],
            'food_truck_id' => $usuario['food_truck_id'] === null ? null : (int) $usuario['food_truck_id'],
        ];

        $_SESSION[self::CLAVE_ACTIVIDAD] = time();
    }

    public static function autenticado(): bool
    {
        return isset($_SESSION[self::CLAVE_USUARIO]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function usuario(): ?array
    {
        return $_SESSION[self::CLAVE_USUARIO] ?? null;
    }

    public static function rol(): ?string
    {
        $usuario = self::usuario();

        return $usuario === null ? null : (string) $usuario['rol'];
    }

    /**
     * Identificador del food truck al que pertenece el usuario.
     * Es NULL para el rol de plataforma, que no esta atado a ninguno.
     */
    public static function foodTruckId(): ?int
    {
        $usuario = self::usuario();

        return $usuario === null ? null : $usuario['food_truck_id'];
    }

    public static function cerrar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        // Sin esto la cookie sobrevive al cierre y el navegador la sigue enviando.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Mensaje de un solo uso: se muestra en la siguiente pagina y se descarta.
     */
    public static function mensaje(string $texto, string $tipo = 'aviso'): void
    {
        $_SESSION[self::CLAVE_MENSAJES][] = ['tipo' => $tipo, 'texto' => $texto];
    }

    /**
     * @return list<array{tipo: string, texto: string}>
     */
    public static function sacarMensajes(): array
    {
        $mensajes = $_SESSION[self::CLAVE_MENSAJES] ?? [];
        unset($_SESSION[self::CLAVE_MENSAJES]);

        return $mensajes;
    }

    private static function caducarPorInactividad(int $vida): void
    {
        if (!isset($_SESSION[self::CLAVE_ACTIVIDAD])) {
            $_SESSION[self::CLAVE_ACTIVIDAD] = time();

            return;
        }

        if (time() - (int) $_SESSION[self::CLAVE_ACTIVIDAD] > $vida) {
            self::cerrar();
            session_start();
            self::mensaje('La sesion caduco por inactividad. Ingrese de nuevo.', 'aviso');
        }

        $_SESSION[self::CLAVE_ACTIVIDAD] = time();
    }
}
