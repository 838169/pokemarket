<?php
declare(strict_types=1);

namespace PokeMarket;

final class JwtSession
{
    public const COOKIE = 'POKEMARKET_AUTH';
    private const HEADER = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
    private const LIFETIME = 28800;

    private static function key(): string
    {
        static $key = null;
        if ($key !== null) return $key;
        $env = require __DIR__ . '/environment.php';
        $hex = $env['GATEWAY_JWT_KEY'] ?? '';
        if (!preg_match('/\A[a-fA-F0-9]{64}\z/', $hex)) {
            throw new \RuntimeException('Configura GATEWAY_JWT_KEY con 32 bytes aleatorios en .env.');
        }
        return $key = hex2bin($hex);
    }

    private static function encode(string $content): string
    {
        return rtrim(strtr(base64_encode($content), '+/', '-_'), '=');
    }

    public static function issue(int $userId, string $sessionId): string
    {
        $now = time();
        $claims = self::encode(json_encode([
            'iss' => 'pokemarket-trainer',
            'aud' => 'pokemarket-api',
            'sub' => (string) $userId,
            'sid' => hash('sha256', $sessionId),
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
        ], JSON_THROW_ON_ERROR));
        $message = self::HEADER . '.' . $claims;
        return $message . '.' . self::encode(hash_hmac('sha256', $message, self::key(), true));
    }

    public static function subject(mixed $token, mixed $sessionId): ?string
    {
        if (!is_string($token) || strlen($token) > 2048 ||
            !is_string($sessionId) || !preg_match('/\A[a-zA-Z0-9,-]{1,128}\z/', $sessionId)) return null;
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::HEADER ||
            !preg_match('/\A[A-Za-z0-9_-]+\z/', $parts[1])) return null;
        $signature = self::encode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], self::key(), true));
        if (!hash_equals($signature, $parts[2])) return null;
        $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $decoded === false ? null : json_decode($decoded, true);
        $subject = $claims['sub'] ?? null;
        $issued = $claims['iat'] ?? null;
        $expires = $claims['exp'] ?? null;
        $now = time();
        if (($claims['iss'] ?? null) !== 'pokemarket-trainer' ||
            ($claims['aud'] ?? null) !== 'pokemarket-api' ||
            !is_string($subject) || !preg_match('/\A[1-9][0-9]*\z/', $subject) ||
            !is_int($issued) || !is_int($expires) || $issued > $now ||
            $expires <= $now || $expires !== $issued + self::LIFETIME ||
            ($claims['sid'] ?? null) !== hash('sha256', $sessionId)) {
            return null;
        }
        return $subject;
    }
}
