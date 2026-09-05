<?php

declare(strict_types=1);

namespace Menu08\Modelos;

use Menu08\Nucleo\ConexionBD;

/**
 * Datos del food truck. Todas las consultas van preparadas.
 */
final class FoodTruck
{
    /**
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $s = ConexionBD::obtener()->prepare('SELECT * FROM food_trucks WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porSlug(string $slug): ?array
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT * FROM food_trucks WHERE slug = :slug AND activo = 1 LIMIT 1'
        );
        $s->execute(['slug' => $slug]);

        $fila = $s->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Los food trucks que se muestran al publico en la portada.
     *
     * Solo los activos: uno dado de baja no debe aparecer, ni siquiera con la
     * carta vacia. Se piden las columnas que la portada pinta, no SELECT *, para
     * no arrastrar datos de contacto que ahi no se usan.
     *
     * @return list<array<string, mixed>>
     */
    public static function publicos(): array
    {
        return ConexionBD::obtener()
            ->query(
                'SELECT id, nombre, slug, descripcion, ciudad, logo
                   FROM food_trucks
                  WHERE activo = 1
                  ORDER BY nombre'
            )
            ->fetchAll();
    }

    public static function slugRepetido(string $slug, int $excepto): bool
    {
        $s = ConexionBD::obtener()->prepare(
            'SELECT 1 FROM food_trucks WHERE slug = :slug AND id <> :id LIMIT 1'
        );
        $s->execute(['slug' => $slug, 'id' => $excepto]);

        return $s->fetch() !== false;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $s = ConexionBD::obtener()->prepare(
            'UPDATE food_trucks
                SET nombre = :nombre, slug = :slug, descripcion = :descripcion,
                    telefono = :telefono, whatsapp = :whatsapp, instagram = :instagram,
                    ciudad = :ciudad, logo = COALESCE(:logo, logo)
              WHERE id = :id'
        );

        $s->execute([
            'nombre'      => $datos['nombre'],
            'slug'        => $datos['slug'],
            'descripcion' => $datos['descripcion'],
            'telefono'    => $datos['telefono'],
            'whatsapp'    => $datos['whatsapp'],
            'instagram'   => $datos['instagram'],
            'ciudad'      => $datos['ciudad'],
            'logo'        => $datos['logo'],
            'id'          => $id,
        ]);
    }

    /**
     * Convierte un nombre en un slug: minusculas, sin acentos, con guiones.
     */
    public static function slugificar(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');

        // iconv no siempre esta disponible en hosting compartido, y cuando
        // falla devuelve false. Por eso el reemplazo manual va primero: asi el
        // slug sale bien aunque la extension no exista.
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);

        if (function_exists('iconv')) {
            $traducido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);

            if (is_string($traducido) && $traducido !== '') {
                $t = $traducido;
            }
        }

        $t = preg_replace('/[^a-z0-9]+/', '-', strtolower($t)) ?? '';

        return trim($t, '-');
    }
}
