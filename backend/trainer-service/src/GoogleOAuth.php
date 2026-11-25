<?php
declare(strict_types=1);

namespace TrainerService;

use RuntimeException;

final class GoogleOAuth
{
    public function __construct(private readonly array $env) {}

    public function start(): string
    {
        unset($_SESSION['google_signup']);
        $state = bin2hex(random_bytes(32));
        $verifier = bin2hex(random_bytes(32));
        $_SESSION['google_oauth'] = [$state, $verifier, time() + 600];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->env['GOOGLE_OAUTH_CLIENT_ID'],
            'redirect_uri' => $this->env['GOOGLE_OAUTH_REDIRECT_URI'],
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verifiedProfile(mixed $state, mixed $code): ?array
    {
        $pending = $_SESSION['google_oauth'] ?? null;
        if (!$pending || $pending[2] <= time() || !is_string($state) || !hash_equals($pending[0], $state)) {
            return null;
        }
        unset($_SESSION['google_oauth']);
        if (!is_string($code) || $code === '' || strlen($code) > 2048) return null;
        session_write_close();
        $tokens = $this->request('https://oauth2.googleapis.com/token', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $this->env['GOOGLE_OAUTH_CLIENT_ID'],
                'client_secret' => $this->env['GOOGLE_OAUTH_CLIENT_SECRET'],
                'redirect_uri' => $this->env['GOOGLE_OAUTH_REDIRECT_URI'],
                'grant_type' => 'authorization_code',
                'code_verifier' => $pending[1],
            ]),
        ]);
        $accessToken = $tokens['access_token'] ?? '';
        if (!is_string($accessToken) || !preg_match('/\A[A-Za-z0-9._~+\/=-]+\z/', $accessToken)) {
            throw new RuntimeException('Google no entregó un token de acceso válido.');
        }
        $profile = $this->request('https://openidconnect.googleapis.com/v1/userinfo', [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
        $email = $profile['email'] ?? '';
        $subject = $profile['sub'] ?? '';
        $name = $profile['name'] ?? '';
        if (($profile['email_verified'] ?? false) !== true || !is_string($email) ||
            strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !is_string($subject) || !preg_match('/\A[\x21-\x7e]{1,255}\z/', $subject)) {
            return null;
        }
        return [
            'sub' => $subject,
            'email' => strtolower($email),
            'nombre' => is_string($name) ? mb_substr(trim($name), 0, 100) : '',
        ];
    }

    private function request(string $url, array $options): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, $options + [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $result = is_string($response) ? json_decode($response, true) : null;
        if ($status !== 200 || !is_array($result)) throw new RuntimeException('Google no completó la autorización.');
        return $result;
    }
}
