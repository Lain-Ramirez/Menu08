<?php

declare(strict_types=1);

use Menu08\Nucleo\RedireccionHttps;

$base = 'https://adso.menu08.com';
afirmarIgual(null, RedireccionHttps::destino('http://localhost:8088', []), 'HTTP local permitido');
afirmarIgual(null, RedireccionHttps::destino('', []), 'Configuracion vacia sin redireccion');
afirmarIgual(null, RedireccionHttps::destino($base, ['HTTPS' => 'on']), 'HTTPS sin bucle');
afirmarIgual(null, RedireccionHttps::destino($base, ['HTTPS' => '1']), 'HTTPS numerico sin bucle');
afirmarIgual(null, RedireccionHttps::destino($base, ['HTTPS' => 'ON']), 'HTTPS mayusculas sin bucle');
afirmarIgual($base . '/', RedireccionHttps::destino($base, []), 'Raiz por defecto');
afirmarIgual($base . '/carta/truck-de-pruebas', RedireccionHttps::destino($base, ['REQUEST_URI' => '/carta/truck-de-pruebas']), 'Conserva ruta');
afirmarIgual($base . '/carta/truck-de-pruebas?busqueda=a%20b&x=1', RedireccionHttps::destino($base, ['REQUEST_URI' => '/carta/truck-de-pruebas?busqueda=a%20b&x=1']), 'Conserva consulta y codificacion');
afirmarIgual($base . '/panel', RedireccionHttps::destino($base, ['HTTP_HOST' => 'ajeno.invalid', 'REQUEST_URI' => '/panel']), 'Host no controla destino');
afirmarIgual($base . '/panel', RedireccionHttps::destino($base, ['HTTP_X_FORWARDED_PROTO' => 'https', 'REQUEST_URI' => '/panel']), 'Cabecera reenviada no evita redireccion');
afirmarIgual('https://localhost:8443/panel', RedireccionHttps::destino('https://localhost:8443/app', ['REQUEST_URI' => '/panel']), 'Conserva puerto configurado sin duplicar subruta');
afirmarIgual($base . '/', RedireccionHttps::destino($base, ['REQUEST_URI' => "//malicioso.invalid\r\nX-Falso: 1"]), 'Rechaza controles en ruta');
afirmarIgual($base . '/', RedireccionHttps::destino($base, ['REQUEST_URI' => 'http://ajeno.invalid/']), 'Rechaza destino absoluto');
