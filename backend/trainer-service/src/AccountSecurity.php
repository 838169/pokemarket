<?php
declare(strict_types=1);
namespace TrainerService;

use PDO;

final class AccountSecurity
{
    public function __construct(private readonly PDO $db) {}

    public function sendCode(array $user, string $purpose): void
    {
        $recent = $this->db->prepare('SELECT 1 FROM codigos_cuenta WHERE usuario_id=? AND finalidad=? AND emitido>?');
        $recent->execute([$user['id'], $purpose, time() - 60]);
        if ($recent->fetchColumn()) return;
        $code = sprintf('%06d', random_int(0, 999999));
        $now = time();
        $query = $this->db->prepare(
            'INSERT INTO codigos_cuenta(usuario_id, finalidad, codigo_hash, emitido)
             VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE
             codigo_hash=IF(emitido<=VALUES(emitido)-60, VALUES(codigo_hash), codigo_hash),
             intentos=IF(emitido<=VALUES(emitido)-60, 0, intentos),
             emitido=IF(emitido<=VALUES(emitido)-60, VALUES(emitido), emitido)',
        );
        $query->execute([$user['id'], $purpose, password_hash($code, PASSWORD_DEFAULT), $now]);
        if (!$query->rowCount()) return;
        try {
            self::deliverCode($user['email'], $purpose, $code);
        } catch (\RuntimeException $error) {
            $this->db->prepare('DELETE FROM codigos_cuenta WHERE usuario_id=? AND finalidad=? AND emitido=?')
                ->execute([$user['id'], $purpose, $now]);
            throw $error;
        }
    }

    public static function deliverCode(string $email, string $purpose, string $code): void
    {
        $env = require dirname(__DIR__, 3) . '/config/environment.php';
        $from = $env['MAIL_FROM'] ?? '';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Configura el remitente de correo.');
        }
        $smtp = $env['MAIL_SMTP_HOST'] ?? '';
        $password = $env['MAIL_SMTP_PASSWORD'] ?? '';
        if ($smtp !== '' && (!preg_match('/\A[a-z0-9.-]+\z/i', $smtp) || $password === '')) {
            throw new \RuntimeException('Completa la configuración SMTP.');
        }
        [$subject, $body, $headers] = AccountCodeEmail::render($purpose, $code);
        $headers['From'] = "PokeMarket <$from>";
        $sent = $smtp === '' ? @mail($email, $subject, $body, $headers)
            : self::smtp($smtp, $from, $password, $email, $subject, $body, $headers);
        if (!$sent) throw new \RuntimeException('No se pudo enviar el correo.');
    }

    private static function smtp(string $host, string $from, string $password, string $to, string $subject, string $body, array $headers): bool
    {
        $payload = 'Date: ' . gmdate('D, d M Y H:i:s +0000') . "\r\nTo: <$to>\r\nSubject: $subject\r\n";
        foreach ($headers as $name => $value) $payload .= "$name: $value\r\n";
        $payload .= "\r\n$body";
        $position = 0;
        $curl = curl_init("smtps://$host:465");
        curl_setopt_array($curl, [
            CURLOPT_USERNAME => $from,
            CURLOPT_PASSWORD => $password,
            CURLOPT_MAIL_FROM => "<$from>",
            CURLOPT_MAIL_RCPT => ["<$to>"],
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILESIZE => strlen($payload),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_READFUNCTION => static function ($handle, $stream, int $length) use ($payload, &$position): string {
                $part = substr($payload, $position, $length);
                $position += strlen($part);
                return $part;
            },
        ]);
        return curl_exec($curl) !== false;
    }

    public function consume(int $id, string $purpose, string $code, ?callable $apply = null): bool
    {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM codigos_cuenta WHERE usuario_id=? AND finalidad=? FOR UPDATE');
            $q->execute([$id, $purpose]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            $valid = $row && $row['intentos'] < 5 && time() - (int) $row['emitido'] < 900;
            $match = $valid && password_verify($code, $row['codigo_hash']);
            if ($match) {
                if ($apply) $apply();
                $q = $this->db->prepare("UPDATE codigos_cuenta SET intentos=5 WHERE usuario_id=? AND finalidad=?");
                $q->execute([$id, $purpose]);
            } elseif ($valid) {
                $q = $this->db->prepare('UPDATE codigos_cuenta SET intentos=intentos+1 WHERE usuario_id=? AND finalidad=?');
                $q->execute([$id, $purpose]);
            }
            $this->db->commit();
            return $match;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public static function secret(): string
    {
        $bits = '';
        foreach (str_split(random_bytes(20)) as $byte) $bits .= sprintf('%08b', ord($byte));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        return implode('', array_map(static fn ($part) => $alphabet[bindec($part)], str_split($bits, 5)));
    }

    public static function totpStep(string $secret, string $code, ?int $now = null): ?int
    {
        if (!preg_match('/\A[0-9]{6}\z/', $code)) return null;
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $char) $bits .= sprintf('%05b', strpos($alphabet, $char));
        $key = implode('', array_map(static fn ($part) => chr(bindec($part)), str_split($bits, 8)));
        $step = intdiv($now ?? time(), 30);
        for ($n = $step - 1; $n <= $step + 1; $n++) {
            $mac = hash_hmac('sha1', pack('N2', 0, $n), $key, true);
            $value = unpack('N', substr($mac, ord($mac[19]) & 15, 4))[1] & 0x7fffffff;
            if (hash_equals(sprintf('%06d', $value % 1000000), $code)) return $n;
        }
        return null;
    }

    public static function protect(string $value, bool $decrypt = false): string
    {
        $env = require dirname(__DIR__, 3) . '/config/environment.php';
        $hex = $env['ACCOUNT_SECURITY_KEY'] ?? '';
        if (!preg_match('/\A[0-9a-fA-F]{64}\z/', $hex)) {
            throw new \RuntimeException('Configura la clave de seguridad de la cuenta.');
        }
        $key = hex2bin($hex);
        if ($decrypt) {
            $data = base64_decode($value, true);
            $result = $data && strlen($data) > 28
                ? openssl_decrypt(substr($data, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16))
                : false;
        } else {
            $iv = random_bytes(12);
            $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            $result = $cipher === false ? false : base64_encode($iv . $tag . $cipher);
        }
        if ($result === false) throw new \RuntimeException('No se pudo procesar la clave de seguridad.');
        return $result;
    }
}
