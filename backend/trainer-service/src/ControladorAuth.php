<?php

declare(strict_types=1);

namespace TrainerService;

use PDO;
use PDOException;

final class ControladorAuth
{
    public function __construct(
        private readonly PDO $conexion,
        private readonly SessionManager $sesion,
    ) {}

    private function username(array $datos): string
    {
        $usuario = strtolower(Input::text($datos['usuario'] ?? '', 'Usuario', 24));
        if (!preg_match('/\A[a-z0-9_]{3,24}\z/', $usuario)) {
            JsonResponse::error('El usuario debe tener de 3 a 24 letras sin tilde, números o guiones bajos', 400);
        }
        return $usuario;
    }

    public function changeUsername(): never
    {
        $id = $this->sesion->user()['id'];
        $usuario = $this->username(JsonResponse::body());
        (new GameEconomy($this->conexion))->ensure($id);
        $this->conexion->beginTransaction();
        try {
            $q = $this->conexion->prepare('SELECT tarjetas_cambio_usuario FROM carteras_usuario WHERE usuario_id=? FOR UPDATE');
            $q->execute([$id]);
            $cards = (int) $q->fetchColumn();
            $q = $this->conexion->prepare('SELECT usuario FROM usuarios WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $same = $q->fetchColumn() === $usuario;
            if (!$same && !$cards) {
                $this->conexion->rollBack();
                JsonResponse::error('Necesitas una tarjeta de cambio de usuario. Gánala en el Cuaderno del Alba.', 409);
            }
            if (!$same) {
                $q = $this->conexion->prepare('UPDATE usuarios SET usuario=? WHERE id=?');
                $q->execute([$usuario, $id]);
                $q = $this->conexion->prepare('UPDATE carteras_usuario SET tarjetas_cambio_usuario=tarjetas_cambio_usuario-1 WHERE usuario_id=? AND tarjetas_cambio_usuario>0');
                $q->execute([$id]);
            }
            $this->conexion->commit();
        } catch (\Throwable $error) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                JsonResponse::error('Ese nombre de usuario ya está en uso. Conservas tu tarjeta.', 409);
            }
            throw $error;
        }
        JsonResponse::send(['success' => true]);
    }

    public function register(): never
    {
        $datos = JsonResponse::body();
        $nombre = Input::text($datos['nombre'] ?? '', 'Nombre', 100);
        $usuario = $this->username($datos);
        $email = strtolower(Input::text($datos['email'] ?? '', 'Correo', 150));
        $password = Input::text($datos['password'] ?? '', 'Contraseña', 72, false);

        if ($nombre === '' || $email === '' || $password === '') {
            JsonResponse::error('Todos los campos son obligatorios', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            JsonResponse::error('El correo no es válido', 400);
        }

        if (preg_match_all('/./us', $password) < 8) {
            JsonResponse::error('La contraseña debe tener mínimo 8 caracteres', 400);
        }

        $existing = $this->account('email', $email);
        if ($existing && ($existing['correo_verificado'] || $existing['usuario'] !== $usuario
            || !password_verify($password, $existing['password']))) {
            JsonResponse::error('Ya existe una cuenta con ese correo. Inicia sesión.', 409);
        }
        if (!$existing && $this->account('usuario', $usuario)) {
            JsonResponse::error('Ese nombre de usuario ya está en uso.', 409);
        }

        $this->sesion->start();
        session_regenerate_id(true);
        $previous = $_SESSION['registro_pendiente'] ?? [];
        if (($previous['email'] ?? null) !== $email || $previous['usuario'] !== $usuario
            || !password_verify($password, $previous['password'])) {
            $previous = [];
        }
        $_SESSION['registro_pendiente'] = [
            'id' => $existing['id'] ?? null,
            'sesion_version' => (int) ($existing['sesion_version'] ?? 0),
            'nombre' => $existing['nombre'] ?? $nombre,
            'usuario' => $usuario,
            'email' => $email,
            'password' => $previous['password'] ?? password_hash($password, PASSWORD_DEFAULT),
            'desafio' => $previous['desafio'] ?? bin2hex(random_bytes(32)),
            'enviado' => $previous['enviado'] ?? false,
        ];
        JsonResponse::send(['success' => true, 'codigo_enviado' => $this->sendRegistrationCode()], 202);
    }

    private function sendRegistrationCode(): bool
    {
        $pending = &$_SESSION['registro_pendiente'];
        $code = sprintf('%06d', random_int(0, 999999));
        $now = time();
        $query = $this->conexion->prepare(
            'INSERT INTO codigos_registro (email, desafio, codigo_hash, emitido)
             VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE
             desafio=IF(emitido<=VALUES(emitido)-60, VALUES(desafio), desafio),
             codigo_hash=IF(emitido<=VALUES(emitido)-60, VALUES(codigo_hash), codigo_hash),
             intentos=IF(emitido<=VALUES(emitido)-60, 0, intentos),
             emitido=IF(emitido<=VALUES(emitido)-60, VALUES(emitido), emitido)',
        );
        $query->execute([$pending['email'], $pending['desafio'], password_hash($code, PASSWORD_DEFAULT), $now]);
        if ($query->rowCount()) {
            $pending['enviado'] = false;
            try {
                AccountSecurity::deliverCode($pending['email'], 'verify', $code);
                $pending['enviado'] = true;
            } catch (\RuntimeException $error) {
                JsonResponse::log('account_mail', $error);
            }
        }
        return $pending['enviado'];
    }

    private function finalizeRegistration(array $pending, string $code): never
    {
        $this->conexion->beginTransaction();
        try {
            $query = $this->conexion->prepare(
                'SELECT codigo_hash, emitido, intentos FROM codigos_registro WHERE email=? AND desafio=? FOR UPDATE',
            );
            $query->execute([$pending['email'], $pending['desafio']]);
            $challenge = $query->fetch(PDO::FETCH_ASSOC);
            if (!$challenge || time() - (int) $challenge['emitido'] >= 900 || (int) $challenge['intentos'] >= 5) {
                $this->conexion->rollBack();
                JsonResponse::error('Código vencido o sin intentos. Solicita otro.', 400);
            }
            if (!password_verify($code, $challenge['codigo_hash'])) {
                $query = $this->conexion->prepare('UPDATE codigos_registro SET intentos=intentos+1 WHERE email=?');
                $query->execute([$pending['email']]);
                $this->conexion->commit();
                JsonResponse::error('Código incorrecto.', 400);
            }
            if ($pending['id'] === null) {
                $query = $this->conexion->prepare(
                    'INSERT INTO usuarios (nombre, usuario, email, password, correo_verificado)
                     VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)',
                );
                $query->execute([$pending['nombre'], $pending['usuario'], $pending['email'], $pending['password']]);
                $pending['id'] = (int) $this->conexion->lastInsertId();
            } else {
                $query = $this->conexion->prepare(
                    'UPDATE usuarios SET correo_verificado=CURRENT_TIMESTAMP
                     WHERE id=? AND usuario=? AND sesion_version=? AND correo_verificado IS NULL',
                );
                $query->execute([$pending['id'], $pending['usuario'], $pending['sesion_version']]);
                if (!$query->rowCount()) {
                    $this->conexion->rollBack();
                    JsonResponse::error('La cuenta cambió. Inicia sesión.', 409);
                }
            }
            $this->conexion->prepare('DELETE FROM codigos_registro WHERE email=?')->execute([$pending['email']]);
            $this->conexion->commit();
        } catch (\Throwable $error) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
                JsonResponse::error('El usuario o correo ya está en uso. Inicia sesión o usa otros datos.', 409);
            }
            throw $error;
        }
        $this->sesion->login($pending);
        JsonResponse::send(['success' => true]);
    }

    public function login(): never
    {
        $datos = JsonResponse::body();
        $email = strtolower(Input::text($datos['email'] ?? '', 'Correo', 150));
        $password = Input::text($datos['password'] ?? '', 'Contraseña', 72, false);

        if ($email === '' || $password === '') {
            JsonResponse::error('Correo y contraseña son obligatorios', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            JsonResponse::error('El correo no es válido', 400);
        }

        try {
            $consulta = $this->conexion->prepare(
                'SELECT * FROM usuarios WHERE email = :email LIMIT 1',
            );
            $consulta->execute([':email' => $email]);
            $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || !password_verify($password, $usuario['password'])) {
                JsonResponse::error('Correo o contraseña incorrectos', 401);
            }

            $method = $usuario['totp_secreto'] ? 'autenticador' : ($usuario['correo_verificado'] ? 'correo' : null);
            if ($method !== null) {
                try {
                    $this->beginLoginChallenge($usuario, $method);
                } catch (\RuntimeException $error) {
                    JsonResponse::log('account_mail', $error);
                    JsonResponse::error('No se pudo enviar el código de acceso. Intenta más tarde.', 503);
                }
                JsonResponse::send(['success' => true, 'requiere_codigo' => true, 'metodo' => $method]);
            }
            $this->sesion->login($usuario);
            JsonResponse::send([
                'success' => true,
                'message' => 'Inicio de sesión correcto',
                'usuario' => [
                    'id' => (int) $usuario['id'],
                    'nombre' => $usuario['nombre'],
                ],
            ]);
        } catch (PDOException) {
            JsonResponse::error('Error interno del servidor', 500);
        }
    }

    private function beginLoginChallenge(array $user, string $method): void
    {
        $this->sesion->start();
        session_regenerate_id(true);
        $_SESSION = [];
        if ($method === 'correo') {
            (new AccountSecurity($this->conexion))->sendCode($user, 'login');
        }
        $_SESSION['login_pendiente'] = [(int) $user['id'], time() + ($method === 'correo' ? 900 : 300), 0, (int) $user['sesion_version']];
    }

    public function googleLogin(bool $callback): never
    {
        $env = require dirname(__DIR__, 3) . '/config/environment.php';
        if (empty($env['GOOGLE_OAUTH_CLIENT_ID']) || empty($env['GOOGLE_OAUTH_CLIENT_SECRET']) || empty($env['GOOGLE_OAUTH_REDIRECT_URI'])) {
            JsonResponse::error('El acceso con Google no está configurado.', 404);
        }
        $this->sesion->start();
        $google = new GoogleOAuth($env);
        $result = 'error';
        if (!$callback) {
            header('Location: ' . $google->start(), true, 302);
            exit();
        }
        try {
            $profile = $google->verifiedProfile($_GET['state'] ?? null, $_GET['code'] ?? null);
            if ($profile !== null) {
                $user = $this->account('google_sub', $profile['sub']);
                if ($user && !$user['totp_secreto']) {
                    $this->sesion->login($user);
                    header('Location: ../../../catalogo.php', true, 302);
                    exit();
                }
                $user = $user ?: $this->account('email', $profile['email']);
                if ($user && $user['correo_verificado']) {
                    $method = $user['totp_secreto'] ? 'autenticador' : 'correo';
                    $this->beginLoginChallenge($user, $method);
                    $result = $method;
                } elseif ($user) {
                    $result = 'cuenta';
                } else {
                    $this->sesion->start();
                    session_regenerate_id(true);
                    $_SESSION = ['google_signup' => $profile + ['expires' => time() + 600]];
                    $result = 'registro';
                }
            }
        } catch (\RuntimeException $error) {
            JsonResponse::log('google_oauth', $error);
        }
        header('Location: ../../../login.php?google=' . $result, true, 302);
        exit();
    }

    public function googleSignup(): never
    {
        $this->sesion->start();
        $profile = $_SESSION['google_signup'] ?? null;
        if (!$profile || $profile['expires'] <= time()) {
            JsonResponse::error('Vuelve a usar Continuar con Google para elegir tu usuario.', 403);
        }
        $username = $this->username(JsonResponse::body());
        $name = $profile['nombre'] !== '' ? $profile['nombre'] : $username;
        try {
            $query = $this->conexion->prepare(
                'INSERT INTO usuarios (nombre, usuario, email, password, google_sub, correo_verificado)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
            );
            $query->execute([$name, $username, $profile['email'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $profile['sub']]);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) !== 1062) throw $error;
            if ($this->account('google_sub', $profile['sub']) || $this->account('email', $profile['email'])) {
                unset($_SESSION['google_signup']);
                JsonResponse::error('Ya existe una cuenta con ese correo. Inicia sesión de nuevo.', 409);
            }
            JsonResponse::error('Ese nombre de usuario ya está en uso. Elige otro.', 409);
        }
        unset($_SESSION['google_signup']);
        $this->sesion->login([
            'id' => (int) $this->conexion->lastInsertId(),
            'nombre' => $name,
            'email' => $profile['email'],
        ]);
        JsonResponse::send(['success' => true], 201);
    }

    public function security(string $action): never
    {
        $service = new AccountSecurity($this->conexion);
        $this->sesion->start();
        $data = $action === 'security' ? [] : JsonResponse::body();
        $code = Input::text($data['codigo'] ?? '', 'Código', 6);
        if ($action === 'login-code') {
            $pending = $_SESSION['login_pendiente'] ?? null;
            if (!$pending || $pending[1] <= time() || $pending[2] >= 5) {
                JsonResponse::error('Vuelve a iniciar sesión para pedir otro intento.', 403);
            }
            $_SESSION['login_pendiente'][2]++;
            $user = $this->account('id', $pending[0]);
            if (!$user || (int) $user['sesion_version'] !== $pending[3]) {
                JsonResponse::error('Vuelve a iniciar sesión.', 403);
            }
            if (!$user['totp_secreto']) {
                if (!$user['correo_verificado'] || !$service->consume($pending[0], 'login', $code)) {
                    JsonResponse::error('Código incorrecto, vencido o ya utilizado.', 400);
                }
                $this->sesion->login($user);
                JsonResponse::send(['success' => true]);
            }
            $window = intdiv(time(), 900);
            $limit = $this->conexion->prepare(
                'UPDATE usuarios SET totp_intentos=IF(totp_ventana=?, totp_intentos+1, 1), totp_ventana=?
                 WHERE id=? AND (totp_ventana<>? OR totp_intentos<10)',
            );
            $limit->execute([$window, $window, $user['id'], $window]);
            if (!$limit->rowCount()) JsonResponse::error('Demasiados intentos. Intenta más tarde.', 429);
            if (!$this->acceptTotp($user, $code)) {
                JsonResponse::error('Código incorrecto o ya utilizado.', 400);
            }
            $this->conexion->prepare('UPDATE usuarios SET totp_intentos=0 WHERE id=?')->execute([$user['id']]);
            $this->sesion->login($user);
            JsonResponse::send(['success' => true]);
        }
        if ($action === 'reset-request') {
            unset($_SESSION['recuperacion']);
            $email = strtolower(Input::text($data['email'] ?? '', 'Correo', 150));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) JsonResponse::error('Correo inválido.', 400);
            $user = $this->account('email', $email);
            if ($user) {
                try {
                    $service->sendCode($user, 'reset');
                } catch (\RuntimeException $error) {
                    JsonResponse::log('account_mail', $error);
                }
            }
            $_SESSION['recuperacion'] = ['email' => $email];
            JsonResponse::send(['success' => true, 'message' => 'Si existe una cuenta, recibirás un código por correo.']);
        }
        if ($action === 'reset-verify') {
            $email = $_SESSION['recuperacion']['email'] ?? null;
            $user = $email ? $this->account('email', $email) : false;
            if (!$user || !$service->consume((int) $user['id'], 'reset', $code)) {
                JsonResponse::error('Código incorrecto, vencido o ya utilizado.', 400);
            }
            session_regenerate_id(true);
            $_SESSION['recuperacion'] = ['id' => $user['id'], 'version' => $user['sesion_version'], 'vence' => time() + 600];
            JsonResponse::send(['success' => true]);
        }
        if ($action === 'reset-password') {
            $grant = $_SESSION['recuperacion'] ?? [];
            if (($grant['vence'] ?? 0) <= time()) JsonResponse::error('Solicita y valida un código nuevo.', 403);
            $password = Input::text($data['password'] ?? '', 'Contraseña', 72, false);
            $confirmation = Input::text($data['confirmacion'] ?? '', 'Confirmación', 72, false);
            if (preg_match_all('/./us', $password) < 8) JsonResponse::error('Usa al menos 8 caracteres.', 400);
            if ($password !== $confirmation) JsonResponse::error('Las contraseñas deben coincidir.', 400);
            $q = $this->conexion->prepare('UPDATE usuarios SET password=?, sesion_version=sesion_version+1 WHERE id=? AND sesion_version=?');
            $q->execute([password_hash($password, PASSWORD_DEFAULT), $grant['id'], $grant['version']]);
            unset($_SESSION['recuperacion']);
            if (!$q->rowCount()) JsonResponse::error('Solicita y valida un código nuevo.', 403);
            JsonResponse::send(['success' => true, 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
        }

        if ($action === 'register-code' || $action === 'register-resend') {
            $pending = $_SESSION['registro_pendiente'] ?? null;
            if (!$pending) JsonResponse::error('Vuelve a abrir el formulario de registro.', 403);
            if ($action === 'register-code') $this->finalizeRegistration($pending, $code);
            if (!$this->sendRegistrationCode()) {
                JsonResponse::error('No se pudo enviar el código. Intenta de nuevo en un minuto.', 503);
            }
            JsonResponse::send(['success' => true, 'message' => 'Revisa tu correo. El código vence en 15 minutos.']);
        }
        $session = $this->sesion->user();
        if (!$session) JsonResponse::error('Inicia sesión.', 401);
        $id = $session['id'];
        $user = $this->account('id', $id);
        if ($action === 'security') {
            JsonResponse::send(['success' => true, 'verificado' => (bool) $user['correo_verificado'], 'dos_pasos' => (bool) $user['totp_secreto']]);
        }
        if ($action === 'verify-request') {
            try {
                if (!$user['correo_verificado']) $service->sendCode($user, 'verify');
            } catch (\RuntimeException $error) {
                JsonResponse::log('account_mail', $error);
                JsonResponse::error('El envío de correo no está disponible.', 503);
            }
            JsonResponse::send(['success' => true, 'message' => 'Revisa tu correo. El código vence en 15 minutos.']);
        }
        if ($action === 'verify-email') {
            if (!$service->consume($id, 'verify', $code, function () use ($id, $user) {
                $query = $this->conexion->prepare(
                    'UPDATE usuarios SET correo_verificado=CURRENT_TIMESTAMP WHERE id=? AND sesion_version=?',
                );
                $query->execute([$id, $user['sesion_version']]);
                if (!$query->rowCount()) {
                    throw new \DomainException('La cuenta cambió durante la verificación.');
                }
            })) JsonResponse::error('Código incorrecto, vencido o ya utilizado.', 400);
            JsonResponse::send(['success' => true]);
        }
        if (!$user['correo_verificado'] || $user['totp_secreto']) JsonResponse::error('Verifica tu correo antes de activar un autenticador.', 409);
        if ($action === 'totp-setup') {
            $password = Input::text($data['password'] ?? '', 'Contraseña', 72, false);
            if (!password_verify($password, $user['password'])) JsonResponse::error('Contraseña incorrecta.', 400);
            $secret = AccountSecurity::secret();
            AccountSecurity::protect($secret);
            $_SESSION['totp_pendiente'] = [$secret, time() + 900, 0];
            JsonResponse::send(['success' => true, 'clave' => $secret]);
        }
        $pending = $_SESSION['totp_pendiente'] ?? null;
        if (!$pending || $pending[1] <= time() || $pending[2] >= 5) JsonResponse::error('Genera una clave nueva.', 400);
        $_SESSION['totp_pendiente'][2]++;
        $step = AccountSecurity::totpStep($pending[0], $code);
        if ($step === null) JsonResponse::error('Código incorrecto.', 400);
        $q = $this->conexion->prepare('UPDATE usuarios SET totp_secreto=?, totp_paso=?, sesion_version=sesion_version+1 WHERE id=? AND totp_secreto IS NULL AND sesion_version=?');
        $q->execute([AccountSecurity::protect($pending[0]), $step, $id, $user['sesion_version']]);
        if (!$q->rowCount()) JsonResponse::error('Vuelve a iniciar sesión.', 409);
        $_SESSION['sesion_version'] = (int) $user['sesion_version'] + 1;
        unset($_SESSION['totp_pendiente']);
        JsonResponse::send(['success' => true]);
    }

    private function account(string $field, int|string $value): array|false
    {
        $query = $this->conexion->prepare("SELECT * FROM usuarios WHERE $field=?");
        $query->execute([$value]);
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    private function acceptTotp(array $user, string $code): bool
    {
        if (!$user['totp_secreto']) return false;
        $step = AccountSecurity::totpStep(AccountSecurity::protect($user['totp_secreto'], true), $code);
        if ($step === null) return false;
        $q = $this->conexion->prepare('UPDATE usuarios SET totp_paso=? WHERE id=? AND totp_paso<? AND sesion_version=?');
        $q->execute([$step, $user['id'], $step, $user['sesion_version']]);
        return $q->rowCount() === 1;
    }

    public function logout(): never
    {
        $this->sesion->logout();
        JsonResponse::send([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    public function me(): never
    {
        $usuario = $this->sesion->user();

        if ($usuario === null) {
            JsonResponse::error('Usuario no autenticado', 401);
        }

        JsonResponse::send([
            'success' => true,
            'usuario' => $usuario,
            'csrf_token' => $this->sesion->csrfToken(),
        ]);
    }
}
