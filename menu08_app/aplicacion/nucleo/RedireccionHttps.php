<?php

declare(strict_types=1);

namespace Menu08\Nucleo;

/** Redirige al origen HTTPS configurado antes de abrir una sesion. */
final class RedireccionHttps
{
    /** @param array<string, mixed> $servidor */
    public static function destino(string $urlBase, array $servidor): ?string
    {
        $base = parse_url($urlBase);

        // Las instalaciones locales con url_base HTTP siguen funcionando.
        if (!is_array($base) || ($base['scheme'] ?? '') !== 'https' || empty($base['host'])) {
            return null;
        }

        $https = strtolower((string) ($servidor['HTTPS'] ?? ''));

        if ($https === 'on' || $https === '1') {
            return null;
        }

        // El destino sale de configuracion, nunca de Host ni de cabeceras
        // reenviadas que un visitante pudiera falsificar.
        $origen = 'https://' . $base['host'];

        if (isset($base['port'])) {
            $origen .= ':' . $base['port'];
        }

        $ruta = (string) ($servidor['REQUEST_URI'] ?? '/');

        if (!str_starts_with($ruta, '/') || preg_match('/[\x00-\x20\x7f]/', $ruta)) {
            $ruta = '/';
        }

        return $origen . $ruta;
    }
}
