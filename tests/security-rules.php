<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\Input;
function rejects(callable $action): void
{
    try {
        $action();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Entrada inválida aceptada');
}
rejects(fn() => Input::text(['x'], 'Nombre', 100));
rejects(fn() => Input::text(str_repeat('a', 101), 'Nombre', 100));
rejects(fn() => Input::text("x\0y", 'Nombre', 100));
rejects(fn() => Input::text(str_repeat('a', 73), 'Contraseña', 72, false));
if (Input::text("  José O'Neil  ", 'Nombre', 100) !== "José O'Neil") {
    throw new RuntimeException('Texto válido alterado');
}
$config = require dirname(__DIR__) . '/backend/trainer-service/config/app.php';
$session = new TrainerService\SessionManager($config);
$session->start();
$first = $session->csrfToken();
if (strlen($first) !== 64 || $first !== $session->csrfToken()) {
    throw new RuntimeException('CSRF no estable');
}
$session->login(['id' => 999999, 'nombre' => 'Prueba aislada', 'email' => 'test@example.invalid']);
if ($session->csrfToken() === $first) {
    throw new RuntimeException('Token no rotado al iniciar sesión');
}
$_SESSION['ultima_actividad'] = time() - $config['session_idle_seconds'] - 1;
if ($session->user() !== null) {
    throw new RuntimeException('Sesión inactiva no expirada');
}
echo "OK: validación de tipos, límites, Unicode, token CSRF rotado y expiración de sesión.\n";
