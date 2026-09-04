<?php

declare(strict_types=1);

use Menu08\Nucleo\Vista;

/**
 * Muestrario del catalogo de componentes.
 *
 * Una sola pagina con las seis piezas de componentes.css, cada una con sus
 * variantes y con los estados de foco, error y deshabilitado, mas las tres
 * utilidades de interfaz.js en funcionamiento.
 *
 * No lleva ni un atributo style: si algo no se puede maquetar con las clases de
 * base.css y componentes.css, es que a las hojas les falta esa clase.
 *
 * Los estados de la orden no estan escritos aqui: llegan de Orden::TRANSICIONES
 * a traves del controlador, para que el catalogo no pueda desviarse del ciclo
 * de vida real.
 *
 * @var list<array<string, string>> $estados
 */

/** Iconos de trazo. Tabla fija: nada de lo que sale de aqui viene del usuario. */
$icono = static function (string $nombre, string $clase): string {
    $trazos = [
        'exito'       => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'aviso'       => '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 10v3.5"/><path d="M12 17h.01"/>',
        'error'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5"/><path d="M12 16h.01"/>',
        'pendiente'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'preparacion' => '<path d="M12 3c3 3.5 5 6 5 9a5 5 0 0 1-10 0c0-1.6.7-3 1.7-4.2.3 1.5 1.1 2.4 2.1 2.7C10.2 8.2 10.6 5.6 12 3Z"/>',
        'lista'       => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'entregada'   => '<path d="M17 6 7.5 16 3 11.5"/><path d="m21 8-7 7.5"/>',
        'demorada'    => '<path d="M10.3 4 2.5 17.5A1.8 1.8 0 0 0 4 20.2h16a1.8 1.8 0 0 0 1.5-2.7L13.7 4a2 2 0 0 0-3.4 0Z"/><path d="M12 10v3.5"/><path d="M12 17h.01"/>',
        'agotado'     => '<circle cx="12" cy="12" r="9"/><path d="m8.5 15.5 7-7"/>',
        'flecha'      => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'mas'         => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'menu'        => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
    ];

    return sprintf(
        '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        Vista::e($clase),
        $trazos[$nombre] ?? ''
    );
};

/** Secciones del indice: ancla => rotulo. */
$secciones = [
    'boton'    => 'Boton',
    'campo'    => 'Campo de formulario',
    'tarjeta'  => 'Tarjeta',
    'tabla'    => 'Tabla',
    'aviso'    => 'Aviso',
    'etiqueta' => 'Etiqueta de estado',
    'utilidades' => 'Utilidades de interfaz.js',
];

/** Ordenes de ejemplo para la tabla. Datos de muestra, no salen de la base. */
$ordenes = [
    ['numero' => '044', 'total' => '$ 12.000', 'medio' => 'Efectivo',      'estado' => 'pendiente',   'rotulo' => 'Pendiente',      'hora' => '12:21'],
    ['numero' => '043', 'total' => '$ 32.000', 'medio' => 'Tarjeta',       'estado' => 'preparacion', 'rotulo' => 'En preparacion', 'hora' => '12:18'],
    ['numero' => '042', 'total' => '$ 38.000', 'medio' => 'Transferencia', 'estado' => 'lista',       'rotulo' => 'Lista',          'hora' => '12:11'],
    ['numero' => '041', 'total' => '$ 24.000', 'medio' => 'Efectivo',      'estado' => 'entregada',   'rotulo' => 'Entregada',      'hora' => '12:04'],
];
?>
<div class="pila pila-7">

    <header class="pila pila-2">
        <span class="muestrario-rotulo">MENU08 · CATALOGO DE COMPONENTES</span>
        <h1>Seis piezas que arman CARTA, CAJA y el SVP</h1>
        <p class="texto-apagado texto-m">
            Boton, campo, tarjeta, tabla, aviso y etiqueta de estado, cada uno con sus variantes y con los
            estados de foco, error y deshabilitado. Todo el color sale de los 31 tokens de
            <code>md3.css</code>: no hay ni un valor escrito a mano en las hojas.
        </p>
        <nav class="muestrario-indice" aria-label="Secciones del muestrario">
            <?php foreach ($secciones as $ancla => $rotulo) : ?>
                <a class="boton boton-texto" href="#<?= Vista::e($ancla) ?>"><?= Vista::e($rotulo) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- ============================================================= boton -->
    <section class="pila pila-5 muestrario-seccion" id="boton" aria-labelledby="t-boton">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-boton">Boton</h2>
            <p class="muestrario-pie">
                40 px de alto y radio completo. La capa de estado es un <code>::after</code> de
                <code>currentColor</code>, asi que una sola regla sirve a las cinco variantes y a los dos modos.
            </p>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Variantes</span>
            <div class="fila">
                <button type="button" class="boton boton-relleno">Registrar orden</button>
                <button type="button" class="boton boton-tonal">Abrir turno</button>
                <button type="button" class="boton boton-contorno">Cancelar</button>
                <button type="button" class="boton boton-texto">Ver comprobante</button>
                <button type="button" class="boton boton-peligro">Cerrar turno</button>
                <button type="button" class="boton boton-relleno">
                    <?= $icono('mas', 'boton-icono') ?>
                    Nuevo producto
                </button>
            </div>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Estados</span>
            <div class="fila fila-arriba">
                <div class="pila pila-2">
                    <button type="button" class="boton boton-relleno">Reposo</button>
                    <span class="muestrario-pie">en reposo</span>
                </div>
                <div class="pila pila-2">
                    <button type="button" class="boton boton-relleno muestra-encima">Puntero encima</button>
                    <span class="muestrario-pie">:hover · capa al 8 %</span>
                </div>
                <div class="pila pila-2">
                    <button type="button" class="boton boton-relleno muestra-foco">Con foco</button>
                    <span class="muestrario-pie">:focus-visible · anillo dorado</span>
                </div>
                <div class="pila pila-2">
                    <button type="button" class="boton boton-relleno" disabled>Deshabilitado</button>
                    <span class="muestrario-pie">disabled · 12 % de fondo, 38 % de texto</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================= campo -->
    <section class="pila pila-5 muestrario-seccion" id="campo" aria-labelledby="t-campo">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-campo">Campo de formulario</h2>
            <p class="muestrario-pie">
                Campo con contorno de MD3: 56 px de alto y rotulo que sube a la muesca del borde. Al enfocar,
                el borde pasa a 2 px y el relleno baja a 15 px para que la altura no salte. El rotulo sube con
                <code>:placeholder-shown</code>, sin una linea de JavaScript, y por eso todo control lleva
                <code>placeholder=" "</code>.
            </p>
        </div>

        <div class="rejilla rejilla-3">
            <div class="campo campo-con-valor">
                <input class="campo-control" type="text" id="m-nombre" name="m-nombre"
                       value="Arepa de huevo" placeholder=" " autocomplete="off">
                <label class="campo-etiqueta" for="m-nombre">Nombre del producto</label>
                <span class="campo-apoyo">Como aparece en la carta.</span>
            </div>

            <div class="campo campo-con-valor">
                <select class="campo-control" id="m-medio" name="m-medio">
                    <option>Efectivo</option>
                    <option>Tarjeta</option>
                    <option>Transferencia</option>
                </select>
                <label class="campo-etiqueta" for="m-medio">Medio de pago</label>
                <span class="campo-apoyo">El rotulo va siempre arriba: un select nunca esta vacio.</span>
            </div>

            <div class="campo">
                <textarea class="campo-control campo-area" id="m-nota" name="m-nota"
                          placeholder=" " maxlength="300"></textarea>
                <label class="campo-etiqueta" for="m-nota">Nota para produccion</label>
                <span class="campo-apoyo">Maximo 300 caracteres.</span>
            </div>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Estados</span>
            <div class="rejilla rejilla-4">
                <div class="campo">
                    <input class="campo-control" type="text" id="m-vacio" name="m-vacio" placeholder=" ">
                    <label class="campo-etiqueta" for="m-vacio">Vacio</label>
                    <span class="campo-apoyo">Reposo · el rotulo ocupa el campo</span>
                </div>

                <div class="campo campo-foco">
                    <input class="campo-control" type="text" id="m-foco" name="m-foco"
                           value="Empanada de carne" placeholder=" ">
                    <label class="campo-etiqueta" for="m-foco">Con foco</label>
                    <span class="campo-apoyo">Borde de 2 px en --primary</span>
                </div>

                <div class="campo campo-error campo-con-valor">
                    <input class="campo-control" type="text" id="m-precio" name="m-precio"
                           value="-500" placeholder=" " aria-invalid="true" aria-describedby="m-precio-apoyo">
                    <label class="campo-etiqueta" for="m-precio">Precio</label>
                    <span class="campo-apoyo" id="m-precio-apoyo">
                        <?= $icono('error', 'aviso-icono etiqueta-icono') ?>
                        El precio debe ser mayor que cero.
                    </span>
                </div>

                <div class="campo campo-inerte campo-con-valor">
                    <input class="campo-control" type="text" id="m-slug" name="m-slug"
                           value="festin-rodante" placeholder=" " disabled>
                    <label class="campo-etiqueta" for="m-slug">Enlace de la carta</label>
                    <span class="campo-apoyo">disabled · lo fija la plataforma</span>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================================================== tarjeta -->
    <section class="pila pila-5 muestrario-seccion" id="tarjeta" aria-labelledby="t-tarjeta">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-tarjeta">Tarjeta</h2>
            <p class="muestrario-pie">
                Radio de 12 px. La rellena resuelve la jerarquia con un contenedor mas alto en vez de con
                sombra, que es lo que MD3 prefiere cuando ya hay varias capas apiladas.
            </p>
        </div>

        <div class="rejilla rejilla-3">
            <article class="tarjeta tarjeta-elevada">
                <h3 class="tarjeta-titulo">Turno abierto</h3>
                <p class="tarjeta-texto">Abierto a las 11:30 por Ana Ruiz · 12 ordenes · vendido $ 486.000</p>
                <div class="tarjeta-pie">
                    <button type="button" class="boton boton-texto">Ver detalle</button>
                    <button type="button" class="boton boton-texto">Cerrar</button>
                </div>
            </article>

            <article class="tarjeta tarjeta-rellena">
                <h3 class="tarjeta-titulo">Parada vigente</h3>
                <p class="tarjeta-texto">Parque de la 93 · costado norte · hasta las 21:00</p>
                <div class="tarjeta-pie">
                    <button type="button" class="boton boton-texto">Ver agenda</button>
                </div>
            </article>

            <article class="tarjeta tarjeta-contorno">
                <h3 class="tarjeta-titulo">Codigo QR</h3>
                <p class="tarjeta-texto">Apunta a /carta/festin-rodante · version 4</p>
                <div class="tarjeta-pie">
                    <button type="button" class="boton boton-texto">Descargar PNG</button>
                </div>
            </article>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Estados</span>
            <div class="rejilla rejilla-3">
                <a class="tarjeta tarjeta-elevada tarjeta-enlace" href="#tarjeta">
                    <h3 class="tarjeta-titulo">Pulsable, con foco</h3>
                    <p class="tarjeta-texto">
                        Tabule hasta aqui para ver el anillo. La tarjeta entera es el enlace, asi que
                        cumple el area de toque.
                    </p>
                </a>

                <article class="tarjeta tarjeta-contorno tarjeta-error">
                    <h3 class="tarjeta-titulo">Turno descuadrado</h3>
                    <p class="tarjeta-texto">Faltan $ 4.000 entre lo vendido y lo contado en caja.</p>
                </article>

                <article class="tarjeta tarjeta-contorno tarjeta-inerte">
                    <h3 class="tarjeta-titulo">Parada desactivada</h3>
                    <p class="tarjeta-texto">Plaza de mercado · jueves de 8:00 a 14:00</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============================================================= tabla -->
    <section class="pila pila-5 muestrario-seccion" id="tabla" aria-labelledby="t-tabla">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-tabla">Tabla</h2>
            <p class="muestrario-pie">
                Ordenes del turno, como las lista CAJA. El importe va a la derecha y con
                <code>tabular-nums</code>: en una columna de dinero, cifras de ancho variable se leen torcidas.
                Por debajo de 480 px cada fila pasa a ficha; el marcado no cambia, solo la ventana.
            </p>
        </div>

        <div class="tabla-envoltura">
            <table class="tabla tabla-apilable">
                <caption class="solo-lectores">Ordenes del turno de ejemplo</caption>
                <thead>
                    <tr>
                        <th scope="col">Numero</th>
                        <th scope="col" class="cifra">Total</th>
                        <th scope="col">Medio</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="cifra">Hora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $o) : ?>
                        <tr>
                            <td data-etiqueta="Numero"><?= Vista::e($o['numero']) ?></td>
                            <td data-etiqueta="Total" class="cifra"><?= Vista::e($o['total']) ?></td>
                            <td data-etiqueta="Medio"><?= Vista::e($o['medio']) ?></td>
                            <td data-etiqueta="Estado">
                                <span class="etiqueta etiqueta-<?= Vista::e($o['estado']) ?>">
                                    <?= $icono($o['estado'], 'etiqueta-icono') ?>
                                    <?= Vista::e($o['rotulo']) ?>
                                </span>
                            </td>
                            <td data-etiqueta="Hora" class="cifra"><?= Vista::e($o['hora']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Estados de la fila</span>
            <div class="tabla-envoltura">
                <table class="tabla tabla-apilable">
                    <caption class="solo-lectores">Estados que puede tomar una fila</caption>
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col" class="cifra">Precio</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr tabindex="0">
                            <td data-etiqueta="Producto">Arepa de huevo</td>
                            <td data-etiqueta="Precio" class="cifra">$ 8.000</td>
                            <td data-etiqueta="Estado">
                                <span class="etiqueta etiqueta-lista">
                                    <?= $icono('lista', 'etiqueta-icono') ?>
                                    Disponible
                                </span>
                            </td>
                        </tr>
                        <tr class="fila-error">
                            <td data-etiqueta="Producto">Orden 039</td>
                            <td data-etiqueta="Precio" class="cifra">$ 16.000</td>
                            <td data-etiqueta="Estado">
                                <span class="etiqueta etiqueta-demorada">
                                    <?= $icono('demorada', 'etiqueta-icono') ?>
                                    Demorada · 21 min
                                </span>
                            </td>
                        </tr>
                        <tr class="fila-inerte">
                            <td data-etiqueta="Producto">Empanada de carne</td>
                            <td data-etiqueta="Precio" class="cifra">$ 4.000</td>
                            <td data-etiqueta="Estado">
                                <span class="etiqueta etiqueta-pendiente">
                                    <?= $icono('agotado', 'etiqueta-icono') ?>
                                    Agotado
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="muestrario-pie">
                La primera fila es recorrible con teclado: tabule hasta ella. El anillo va por dentro,
                porque en el borde exterior lo recortaria la envoltura que desplaza.
            </p>
        </div>
    </section>

    <!-- ============================================================= aviso -->
    <section class="pila pila-5 muestrario-seccion" id="aviso" aria-labelledby="t-aviso">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-aviso">Aviso</h2>
            <p class="muestrario-pie">
                Los tres tipos que emite <code>Sesion::mensaje()</code>. <code>md3.css</code> advierte que
                <code>--primary-container</code> y <code>--warning-container</code> quedan densos en la banda
                calida, asi que cada aviso se distingue por tres canales a la vez: color, icono y palabra
                inicial. Quien no separe naranja de rojo sigue leyendo cual es cual.
            </p>
        </div>

        <div class="pila pila-3">
            <div class="aviso aviso-exito" role="status">
                <?= $icono('exito', 'aviso-icono') ?>
                <p><strong>Hecho.</strong> Producto guardado.</p>
            </div>

            <div class="aviso aviso-aviso" role="status">
                <?= $icono('aviso', 'aviso-icono') ?>
                <p><strong>Atencion.</strong> La sesion caduco por inactividad. Ingrese de nuevo.</p>
            </div>

            <div class="aviso aviso-error" role="alert">
                <?= $icono('error', 'aviso-icono') ?>
                <p><strong>Error.</strong> No se pudo registrar la orden: el turno esta cerrado.</p>
            </div>

            <p class="aviso aviso-aviso">
                Sin icono, con el texto suelto dentro del propio aviso y un
                <a href="#aviso">enlace</a> en medio. Es como lo emiten las vistas que todavia no se
                han remaquetado, y tiene que seguir leyendose en una sola linea.
            </p>
        </div>
    </section>

    <!-- ========================================================== etiqueta -->
    <section class="pila pila-5 muestrario-seccion" id="etiqueta" aria-labelledby="t-etiqueta">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-etiqueta">Etiqueta de estado</h2>
            <p class="muestrario-pie">
                El ciclo de <code>Orden::TRANSICIONES</code>, que solo avanza, mas la demora que marca el SVP
                cuando <code>minutos &gt;= minutos_demora</code>. Entregada se apaga a puro contorno: ya salio
                por la ventanilla y no debe competir por la mirada en el tablero.
            </p>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Ciclo de vida de la orden</span>
            <div class="fila fila-2">
                <?php foreach ($estados as $i => $estado) : ?>
                    <?php if ($i > 0) : ?>
                        <?= $icono('flecha', 'etiqueta-icono texto-apagado') ?>
                    <?php endif; ?>
                    <span class="etiqueta etiqueta-<?= Vista::e($estado['clase']) ?>">
                        <?= $icono($estado['clase'], 'etiqueta-icono') ?>
                        <?= Vista::e($estado['rotulo']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pila pila-3">
            <span class="muestrario-rotulo">Fuera del ciclo</span>
            <div class="fila fila-2">
                <span class="etiqueta etiqueta-demorada">
                    <?= $icono('demorada', 'etiqueta-icono') ?>
                    Demorada · 14 min
                </span>
                <span class="etiqueta etiqueta-lista">
                    <?= $icono('lista', 'etiqueta-icono') ?>
                    Disponible
                </span>
                <span class="etiqueta etiqueta-pendiente">
                    <?= $icono('agotado', 'etiqueta-icono') ?>
                    Agotado
                </span>
                <span class="muestrario-pie">Activa e inactiva, en categorias y paradas, reutilizan este ultimo par.</span>
            </div>
        </div>
    </section>

    <!-- ======================================================== utilidades -->
    <section class="pila pila-5 muestrario-seccion" id="utilidades" aria-labelledby="t-utilidades">
        <div class="muestrario-cabecera pila pila-1">
            <h2 id="t-utilidades">Utilidades de interfaz.js</h2>
            <p class="muestrario-pie">
                Las tres funcionan en esta pagina. Todo es mejora progresiva: sin JavaScript el formulario de
                abajo se envia igual y el menu se queda desplegado.
            </p>
        </div>

        <div class="rejilla rejilla-3">
            <div class="muestrario-escenario">
                <code class="texto-p">Interfaz.aviso(texto, tipo)</code>
                <p class="muestrario-pie">
                    Aparece abajo y se retira solo a los 4 s. Se apilan como maximo tres.
                </p>
                <div class="fila fila-2">
                    <button type="button" class="boton boton-tonal" data-aviso-tipo="exito"
                            data-aviso="Orden 043 registrada &middot; $ 32.000">Exito</button>
                    <button type="button" class="boton boton-tonal" data-aviso-tipo="aviso"
                            data-aviso="El turno lleva 6 h abierto.">Aviso</button>
                    <button type="button" class="boton boton-tonal" data-aviso-tipo="error"
                            data-aviso="No se pudo guardar el producto.">Error</button>
                </div>
            </div>

            <div class="muestrario-escenario">
                <code class="texto-p">Interfaz.confirmar(...)</code>
                <p class="muestrario-pie">
                    Se antepone a lo irreversible. En una accion destructiva el foco arranca en Cancelar: un
                    Enter de mas no debe cerrar el turno.
                </p>
                <form method="get" action="<?= Vista::e(Vista::url('/componentes')) ?>"
                      data-confirmar="Se cierra el turno #3 con 12 ordenes por $ 486.000. Un turno cerrado no se puede reabrir."
                      data-confirmar-titulo="Cerrar el turno"
                      data-confirmar-aceptar="Cerrar turno"
                      data-confirmar-peligro>
                    <button type="submit" class="boton boton-peligro">Cerrar turno</button>
                </form>
            </div>

            <div class="muestrario-escenario">
                <code class="texto-p">Interfaz.menu(boton, panel)</code>
                <p class="muestrario-pie">
                    Alterna <code>aria-expanded</code> y <code>hidden</code>. El marco del panel lo maqueta el
                    issue #15; aqui solo se ejercita el mecanismo.
                </p>
                <div class="fila fila-2">
                    <button type="button" class="boton-simbolo" data-alterna="m-menu" aria-label="Alternar menu">
                        <?= $icono('menu', 'boton-icono') ?>
                    </button>
                    <span class="muestrario-pie">Abre y cierra la lista</span>
                </div>
                <nav class="menu-desplegable" id="m-menu" aria-label="Menu de ejemplo">
                    <a href="#boton">Panel</a>
                    <a href="#tabla">Caja</a>
                    <a href="#etiqueta">SVP</a>
                </nav>
            </div>
        </div>
    </section>

</div>
