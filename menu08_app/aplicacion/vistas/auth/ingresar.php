<?php

declare(strict_types=1);

use Menu08\Nucleo\Csrf;
use Menu08\Nucleo\Vista;

/**
 * @var string      $correo
 * @var string|null $error
 */
$error = $error ?? null;
?>
<h1>Ingresar</h1>

<?php if ($error !== null) : ?>
    <p class="aviso aviso-error" role="alert"><?= Vista::e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= Vista::e(Vista::url('/ingresar')) ?>" novalidate>
    <?= Csrf::campo() ?>

    <p>
        <label for="correo">Correo</label><br>
        <input type="email" id="correo" name="correo" value="<?= Vista::e($correo) ?>"
               required autocomplete="username" autofocus>
    </p>

    <p>
        <label for="contrasena">Contraseña</label><br>
        <input type="password" id="contrasena" name="contrasena"
               required autocomplete="current-password">
    </p>

    <p><button type="submit">Entrar</button></p>
</form>
