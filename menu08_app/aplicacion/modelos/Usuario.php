<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;

/**
 * Acceso a la tabla usuarios. Todas las consultas van preparadas.
 */
final class Usuario
{
    /**
     * Hash de descarte con el que se compara cuando el correo no existe.
     * Sirve para que responder a un correo inexistente tarde lo mismo que
     * responder a uno real: sin esto, el tiempo de respuesta delataria que
     * cuentas estan registradas.
     */
    private const HASH_SEÑUELO = '$2y$10$usuarioInexistenteXXXXXO2mBLbLQZ8kBSBBxvzKPFVLuBLnHOfa';

    /**
     * @return array<string, mixed>|null
     */
    public static function porCorreo(string $correo): ?array
    {
        $sentencia = ConexionBD::obtener()->prepare(
            'SELECT id, food_truck_id, nombre, correo, contrasena, rol, activo
               FROM usuarios
              WHERE correo = :correo
              LIMIT 1'
        );

        $sentencia->execute(['correo' => $correo]);

        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Verifica la contraseña contra el hash almacenado.
     *
     * Cuando el usuario no existe se compara igualmente contra un hash de
     * descarte, de modo que el tiempo empleado no revele si el correo existe.
     */
    public static function claveCorrecta(?array $usuario, string $clave): bool
    {
        $hash = $usuario === null ? self::HASH_SEÑUELO : (string) $usuario['contrasena'];

        $coincide = password_verify($clave, $hash);

        return $usuario !== null && $coincide;
    }

    /**
     * Si el algoritmo por omision cambia, la contraseña se vuelve a cifrar
     * en el siguiente ingreso correcto, sin pedirle nada al usuario.
     */
    public static function recifrarSiHaceFalta(int $id, string $hash, string $clave): void
    {
        if (!password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            return;
        }

        $sentencia = ConexionBD::obtener()->prepare(
            'UPDATE usuarios SET contrasena = :hash WHERE id = :id'
        );

        $sentencia->execute(['hash' => password_hash($clave, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function registrarIngreso(int $id): void
    {
        $sentencia = ConexionBD::obtener()->prepare(
            'UPDATE usuarios SET ultimo_ingreso = NOW() WHERE id = :id'
        );

        $sentencia->execute(['id' => $id]);
    }
}
