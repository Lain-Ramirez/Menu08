<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

use JsonException;

/**
 * Base de todos los controladores: respuestas HTML, JSON, redirecciones y
 * las comprobaciones de acceso que se repiten en cada zona privada.
 */
abstract class Controlador
{
    /**
     * @param array<string, mixed> $datos
     */
    protected function vista(string $plantilla, array $datos = [], string $titulo = 'Menu08', int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: text/html; charset=utf-8');

        echo Vista::pagina($plantilla, $datos, $titulo);
    }

    /**
     * Respuesta JSON. La consume el Sistema de Visualizacion de Produccion.
     *
     * @param array<array-key, mixed> $datos
     *
     * @throws JsonException
     */
    protected function json(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function redirigir(string $ruta): never
    {
        header('Location: ' . Vista::url($ruta), true, 302);

        exit;
    }

    /**
     * Respuesta de error en JSON. Se usa en los servicios que consume el
     * Sistema de Visualizacion de Produccion.
     *
     * @throws JsonException
     */
    protected function jsonError(string $error, string $mensaje, int $codigo): never
    {
        $this->json(['error' => $error, 'mensaje' => $mensaje], $codigo);

        exit;
    }

    /**
     * Puerta de los servicios JSON.
     *
     * A diferencia de exigirRol(), aqui no se redirige ni se pinta HTML: un
     * cliente que sondea espera siempre un objeto, tambien cuando le niegan
     * el paso. Sin sesion es 401; con sesion y rol equivocado, 403.
     */
    protected function exigirRolApi(string ...$roles): void
    {
        ManejadorErrores::responderEnJson();

        if (!Sesion::autenticado()) {
            $this->jsonError('no_autenticado', 'Debe iniciar sesion para consultar este servicio.', 401);
        }

        if (!in_array((string) Sesion::rol(), $roles, true)) {
            $this->jsonError(
                'rol_no_autorizado',
                sprintf('El rol "%s" no tiene acceso a este servicio.', (string) Sesion::rol()),
                403
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function usuario(): ?array
    {
        return Sesion::usuario();
    }

    /**
     * Identificador del food truck de la sesion.
     *
     * Es el filtro de toda consulta del panel: nunca se toma de la URL, para
     * que nadie pueda administrar el catalogo de otro cambiando un numero.
     */
    protected function foodTruckActual(): int
    {
        $id = Sesion::foodTruckId();

        if ($id === null) {
            throw new AccesoDenegado('Esta cuenta no esta asociada a ningun food truck.');
        }

        return $id;
    }

    /**
     * Zona privada: sin sesion no se entra. Se redirige al formulario en lugar
     * de responder 403, porque el visitante no ha fallado, solo no ha entrado.
     */
    protected function exigirSesion(): void
    {
        if (Sesion::autenticado()) {
            return;
        }

        Sesion::mensaje('Debe ingresar para ver esa pagina.', 'aviso');

        $this->redirigir('/ingresar');
    }

    /**
     * Exige sesion y ademas uno de los roles indicados. Un usuario autenticado
     * con el rol equivocado recibe 403: si aqui se redirigiera, se quedaria
     * dando vueltas entre su inicio y la pagina que no le corresponde.
     */
    protected function exigirRol(string ...$roles): void
    {
        $this->exigirSesion();

        if (in_array((string) Sesion::rol(), $roles, true)) {
            return;
        }

        throw new AccesoDenegado(sprintf(
            'El rol "%s" no tiene acceso; se requiere uno de: %s',
            (string) Sesion::rol(),
            implode(', ', $roles)
        ));
    }

    /**
     * Todo POST que modifique datos pasa por aqui. Un token ausente, vencido
     * o alterado corta la peticion antes de tocar la base de datos.
     */
    protected function verificarCsrf(): void
    {
        if (Csrf::valido(isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            return;
        }

        Bitacora::registrar(sprintf(
            'Token CSRF invalido en %s %s',
            (string) ($_SERVER['REQUEST_METHOD'] ?? '?'),
            (string) ($_SERVER['REQUEST_URI'] ?? '?')
        ), 'AVISO');

        throw new AccesoDenegado('El formulario expiro o no es valido. Vuelva a intentarlo.');
    }
}
