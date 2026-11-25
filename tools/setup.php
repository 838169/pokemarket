<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);
$env = require $root . '/config/environment.php';
$name = $env['DB_NAME'] ?? 'pokemarket';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
    throw new RuntimeException('DB_NAME inválido.');
}
try {
    $db = require $root . '/backend/trainer-service/config/database.php';
} catch (RuntimeException $e) {
    if (($env['DB_SSL_CA'] ?? '') !== '') {
        throw $e;
    }
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = (int) ($env['DB_PORT'] ?? 3306);
    if (str_contains($host, ';') || $port < 1 || $port > 65535) {
        throw new RuntimeException('Host o puerto inválido.');
    }
    $db = new PDO(
        "mysql:host=$host;port=$port;charset=utf8mb4",
        $env['DB_USER'] ?? 'root',
        $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $db->exec(
        'CREATE DATABASE IF NOT EXISTS `' .
            $name .
            '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    );
    $db = require $root . '/backend/trainer-service/config/database.php';
}
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$legacyNames = [
    'coleccion' => 'coleccion_pokemon',
    'carteras' => 'carteras_usuario',
    'duelo_busqueda' => 'busquedas_duelo',
    'duelos_equipos' => 'duelos_jugadores',
    'game_migrations' => 'migraciones_bd',
    'amigos' => 'amistades',
    'exploracion_usuario' => 'progreso_exploracion',
];
$renames = [];
foreach ($legacyNames as $old => $new) {
    if (!in_array($old, $tables, true)) {
        continue;
    }
    if (in_array($new, $tables, true)) {
        throw new RuntimeException(
            "Existen las tablas $old y $new. Revisa la base antes de migrar.",
        );
    }
    $renames[] = "`$old` TO `$new`";
}

if (in_array('usuarios', $tables, true)) {
    $columns = $db->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('usuario', $columns, true) || !in_array('usuario_cambios', $columns, true)) {
        $db->exec(file_get_contents($root . '/database/migrations/008_usuarios.sql'));
    }
    if (!in_array('sesion_version', $columns, true)) {
        $db->exec(file_get_contents($root . '/database/migrations/009_seguridad.sql'));
    }
    [$addGoogleColumn, $addGoogleIndex] = explode(';', file_get_contents($root . '/database/migrations/019_cuentas_google.sql'));
    if (!in_array('google_sub', $columns, true)) {
        $db->exec($addGoogleColumn);
    }
    if (!$db->query("SHOW INDEX FROM usuarios WHERE Key_name = 'uq_usuarios_google_sub'")->fetch()) {
        $db->exec(trim($addGoogleIndex));
    }
}
if (in_array('duelo_busqueda', $tables, true)) {
    $db->exec(file_get_contents($root . '/database/migrations/011_duelo_emparejamiento.sql'));
    $db->exec(file_get_contents($root . '/database/migrations/013_duelo_individual.sql'));
}
if ($renames) {
    $db->exec('RENAME TABLE ' . implode(', ', $renames));
}

$db->exec(file_get_contents($root . '/database/schema.sql'));
$walletColumns = $db->query('SHOW COLUMNS FROM carteras_usuario')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('tarjetas_cambio_usuario', $walletColumns, true)) {
    $db->exec('ALTER TABLE carteras_usuario ADD COLUMN tarjetas_cambio_usuario INT UNSIGNED NOT NULL DEFAULT 0');
}
$db->exec("INSERT IGNORE INTO integrantes_sala(sala_id,usuario_id,aceptado)
    SELECT id,anfitrion,1 FROM salas_duo WHERE JSON_EXTRACT(datos,'$.configuracion') IS NULL");
$db->exec("INSERT IGNORE INTO integrantes_sala(sala_id,usuario_id,aceptado)
    SELECT id,companero,CASE WHEN estado='invitada' THEN 0 ELSE 1 END
    FROM salas_duo WHERE JSON_EXTRACT(datos,'$.configuracion') IS NULL");
$db->exec(file_get_contents($root . '/database/migrations/017_eventos_sala.sql'));
$db->exec(file_get_contents($root . '/database/migrations/018_eventos_amistad.sql'));
echo "Instalación verificada. Esquema disponible; las cuentas y el progreso se conservaron.\n";
