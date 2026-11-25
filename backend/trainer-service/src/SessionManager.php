<?php

declare(strict_types=1);

namespace TrainerService;

use PokeMarket\JwtSession;

require_once dirname(__DIR__, 3) . '/config/JwtSession.php';

final class SessionManager
{
    public function __construct(private readonly array $config, private readonly ?\PDO $db = null) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($this->config['session_name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->config['session_path'],
            'secure' => $this->config['session_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function login(array $usuario): void
    {
        $this->start();
        session_regenerate_id(true);
        unset($_SESSION['batalla']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['sesion_version'] = (int) ($usuario['sesion_version'] ?? 0);
        unset(
            $_SESSION['registro_pendiente'],
            $_SESSION['google_signup'],
            $_SESSION['google_oauth'],
            $_SESSION['login_pendiente'],
            $_SESSION['totp_pendiente'],
            $_SESSION['recuperacion'],
        );
        if (PHP_SAPI !== 'cli') {
            $this->authCookie(JwtSession::issue((int) $usuario['id'], session_id()));
        }
    }

    public function user(): ?array
    {
        $this->start();

        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }
        if (PHP_SAPI !== 'cli' &&
            JwtSession::subject($_COOKIE[JwtSession::COOKIE] ?? null, session_id()) !== (string) $_SESSION['usuario_id']) {
            return null;
        }
        if ($this->db) {
            $q = $this->db->prepare('SELECT sesion_version FROM usuarios WHERE id = ?');
            $q->execute([$_SESSION['usuario_id']]);
            $version = $q->fetchColumn();
            if ($version === false || (int) $version !== ($_SESSION['sesion_version'] ?? 0)) {
                $this->logout();
                return null;
            }
        }
        $idle = $this->config['session_idle_seconds'] ?? 3600;
        if (
            isset($_SESSION['ultima_actividad']) &&
            time() - (int) $_SESSION['ultima_actividad'] > $idle
        ) {
            $this->logout();
            return null;
        }
        $_SESSION['ultima_actividad'] = time();

        return [
            'id' => (int) $_SESSION['usuario_id'],
            'nombre' => $_SESSION['usuario_nombre'],
            'email' => $_SESSION['usuario_email'],
        ];
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $this->config['session_path'],
                'secure' => $this->config['session_secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        if (PHP_SAPI !== 'cli') $this->authCookie('', time() - 42000);
    }

    private function authCookie(string $value, int $expires = 0): void
    {
        setcookie(JwtSession::COOKIE, $value, [
            'expires' => $expires,
            'path' => $this->config['session_path'],
            'secure' => $this->config['session_secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    public function csrfToken(): string
    {
        $this->start();
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    public function requireCsrf(): void
    {
        if ($this->user() === null) {
            JsonResponse::error('Usuario no autenticado', 401);
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($this->csrfToken(), $token)) {
            JsonResponse::error('La sesión de la página cambió. Recarga antes de continuar.', 403);
        }
    }
}
