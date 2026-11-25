<?php

declare(strict_types=1);

$env = require dirname(__DIR__, 3) . '/config/environment.php';
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int) ($env['DB_PORT'] ?? 3306);
$dbname = $env['DB_NAME'] ?? 'pokemarket';
$username = $env['DB_USER'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';
if (
    !preg_match('/^[a-zA-Z0-9_]+$/', $dbname) ||
    $port < 1 ||
    $port > 65535 ||
    str_contains($host, ';')
) {
    throw new RuntimeException('Configuración de base de datos inválida. Revisa .env.');
}

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
if (str_ends_with($host, '.tidbcloud.com')) {
    $options[PDO::ATTR_PERSISTENT] = true;
}
$sslCa = $env['DB_SSL_CA'] ?? '';
if ($sslCa !== '') {
    $certificate = is_file($sslCa) ? @fopen($sslCa, 'rb') : false;
    if ($certificate === false) {
        throw new RuntimeException('DB_SSL_CA debe indicar un archivo de certificado legible.');
    }
    fclose($certificate);
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    return new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        $options,
    );
} catch (PDOException $exception) {
    throw new RuntimeException(
        'No fue posible conectar con la base de datos. Revisa MySQL y .env.',
        0,
        $exception,
    );
}
