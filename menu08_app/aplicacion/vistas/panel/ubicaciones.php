<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * Agenda de paradas del food truck.
 *
 * @var list<array<string, mixed>> $ubicaciones
 * @var array<string, mixed>|null  $edita
 * @var array<string, string>      $errores
 * @var array<int, string>         $dias
 * @var array<string, mixed>|null  $vigente
 * @var string|null                $momento
 */
$e = static fn (string $c): string => isset($errores[$c])
    ? '<span class="error-campo">' . Vista::e($errores[$c]) . '</span>' : '';

// La base devuelve TIME como 18:00:00 y <input type="time"> quiere 18:00.
$hm = static fn (mixed $hora): string => substr((string) $hora, 0, 5);

// hora_fin igual o anterior a hora_inicio significa que la jornada cierra al
// dia siguiente. Es el caso normal de un truck nocturno, y conviene decirlo.
$cruza = static fn (array $u): bool => (string) $u['hora_fin'] <= (string) $u['hora_inicio'];
?>
<h1>Paradas</h1>

<p>
    Un food truck no tiene direccion: para en puntos distintos segun el dia. Cada
    parada es un punto con su dia y su franja horaria, y con ellas la carta
    publica responde donde esta el truck.
</p>

<section class="panel-vigente">
    <h2>Donde estamos</h2>

    <?php if ($vigente === null) : ?>
        <p>No hay ninguna parada vigente<?= $momento === null ? ' ahora mismo' : ' en ese momento' ?>.</p>
    <?php else : ?>
        <p>
            <strong><?= Vista::e($vigente['nombre']) ?></strong>
            <?php if (!empty($vigente['referencia'])) : ?>
                · <?= Vista::e($vigente['referencia']) ?>
            <?php endif; ?>
            <br>
            <?= Vista::e($dias[(int) $vigente['dia_semana']] ?? '') ?>
            de <?= Vista::e($hm($vigente['hora_inicio'])) ?>
            a <?= Vista::e($hm($vigente['hora_fin'])) ?>
            <?php if ($cruza($vigente)) : ?>
                (cierra al dia siguiente)
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <form method="get" action="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">
        <label for="momento">Consultar otro momento</label>
        <input type="datetime-local" id="momento" name="momento"
               value="<?= Vista::e($momento === null ? '' : str_replace(' ', 'T', substr($momento, 0, 16))) ?>">
        <button type="submit">Consultar</button>
        <?php if ($momento !== null) : ?>
            <a href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">Volver a ahora</a>
        <?php endif; ?>
    </form>
</section>

<form method="post" action="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>" novalidate>
    <?= Csrf::campo() ?>
    <input type="hidden" name="id" value="<?= (int) ($edita['id'] ?? 0) ?>">

    <p>
        <label for="nombre"><?= $edita === null ? 'Nueva parada' : 'Editar parada' ?></label><br>
        <input type="text" id="nombre" name="nombre" maxlength="120" required
               placeholder="Parque de la 93"
               value="<?= Vista::e($edita['nombre'] ?? '') ?>"><br><?= $e('nombre') ?>
    </p>

    <p>
        <label for="referencia">Referencia (opcional)</label><br>
        <input type="text" id="referencia" name="referencia" maxlength="200"
               placeholder="costado norte, frente al centro comercial"
               value="<?= Vista::e($edita['referencia'] ?? '') ?>"><br><?= $e('referencia') ?>
    </p>

    <p>
        <label for="dia_semana">Dia</label><br>
        <select id="dia_semana" name="dia_semana" required>
            <option value="">Elija un dia</option>
            <?php foreach ($dias as $numero => $nombreDia) : ?>
                <option value="<?= (int) $numero ?>"
                    <?= (int) ($edita['dia_semana'] ?? 0) === (int) $numero ? ' selected' : '' ?>>
                    <?= Vista::e($nombreDia) ?>
                </option>
            <?php endforeach; ?>
        </select><br><?= $e('dia_semana') ?>
    </p>

    <p>
        <label for="hora_inicio">Abre</label><br>
        <input type="time" id="hora_inicio" name="hora_inicio" required
               value="<?= Vista::e($hm($edita['hora_inicio'] ?? '')) ?>"><br><?= $e('hora_inicio') ?>
    </p>

    <p>
        <label for="hora_fin">Cierra</label><br>
        <input type="time" id="hora_fin" name="hora_fin" required
               value="<?= Vista::e($hm($edita['hora_fin'] ?? '')) ?>"><br><?= $e('hora_fin') ?>
        <br><small>Una hora de cierre igual o anterior a la de apertura significa que la jornada
        cierra al dia siguiente: de 18:00 a 01:00 es correcto.</small>
    </p>

    <p>
        <label for="latitud">Latitud (opcional)</label><br>
        <input type="text" id="latitud" name="latitud" inputmode="decimal" maxlength="12"
               value="<?= Vista::e($edita['latitud'] ?? '') ?>"><br><?= $e('latitud') ?>
    </p>

    <p>
        <label for="longitud">Longitud (opcional)</label><br>
        <input type="text" id="longitud" name="longitud" inputmode="decimal" maxlength="12"
               value="<?= Vista::e($edita['longitud'] ?? '') ?>"><br><?= $e('longitud') ?>
    </p>

    <p>
        <button type="submit"><?= $edita === null ? 'Crear' : 'Guardar' ?></button>
        <?php if ($edita !== null) : ?>
            <a href="<?= Vista::e(Vista::url('/panel/ubicaciones')) ?>">Cancelar</a>
        <?php endif; ?>
    </p>
</form>

<?php if ($ubicaciones === []) : ?>
    <p>Todavia no hay paradas. La carta publica no podra decir donde para el truck.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>Dia</th><th>Punto</th><th>Referencia</th><th>Horario</th>
                <th>Coordenadas</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ubicaciones as $u) : ?>
            <tr>
                <td><?= Vista::e($dias[(int) $u['dia_semana']] ?? '') ?></td>
                <td><?= Vista::e($u['nombre']) ?></td>
                <td><?= Vista::e($u['referencia'] ?? '') ?></td>
                <td>
                    <?= Vista::e($hm($u['hora_inicio'])) ?> a <?= Vista::e($hm($u['hora_fin'])) ?>
                    <?php if ($cruza($u)) : ?>
                        <br><small>cierra al dia siguiente</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['latitud'] !== null && $u['longitud'] !== null) : ?>
                        <?= Vista::e($u['latitud']) ?>, <?= Vista::e($u['longitud']) ?>
                    <?php endif; ?>
                </td>
                <td><?= ((int) $u['activa'] === 1) ? 'activa' : 'inactiva' ?></td>
                <td>
                    <a href="<?= Vista::e(Vista::url('/panel/ubicaciones/' . $u['id'])) ?>">Editar</a>
                    <form method="post" action="<?= Vista::e(Vista::url('/panel/ubicaciones/estado')) ?>"
                          style="display:inline">
                        <?= Csrf::campo() ?>
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit"><?= ((int) $u['activa'] === 1) ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
