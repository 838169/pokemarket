<?php
declare(strict_types=1);

// Minimal .env reader. Process environment variables take priority; no file is executed.
$values = [];
$file = getenv('POKEMARKET_ENV_FILE') ?: dirname(__DIR__) . '/.env';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $match)) {
            $values[$match[1]] = trim(trim($match[2]), "\"'");
        }
    }
}
foreach (
    array_unique(
        array_merge(array_keys($values), [
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD',
            'DB_SSL_CA',
            'APP_ORIGINS',
            'MAIL_FROM',
            'MAIL_SMTP_HOST',
            'MAIL_SMTP_PASSWORD',
            'ACCOUNT_SECURITY_KEY',
            'SESSION_SECURE',
            'SESSION_IDLE_SECONDS',
            'POKEAPI_URL',
            'POKEMON_SERVICE_URL',
            'TRAINER_SERVICE_URL',
            'GATEWAY_JWT_KEY',
            'GOOGLE_OAUTH_CLIENT_ID',
            'GOOGLE_OAUTH_CLIENT_SECRET',
            'GOOGLE_OAUTH_REDIRECT_URI',
            'PHP_BINARY',
        ]),
    )
    as $key
) {
    $processValue = getenv($key);
    if ($processValue !== false) {
        $values[$key] = $processValue;
    }
}
return $values;
