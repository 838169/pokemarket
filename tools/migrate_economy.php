<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
if (!in_array('--reset-progression', $argv, true)) {
    fwrite(
        STDERR,
        "Este comando es destructivo. Para instalar sin borrar datos usa tools/setup.php.\nReinicio explícito: php tools/migrate_economy.php --reset-progression\n",
    );
    exit(64);
}
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
$db = require dirname(__DIR__) . '/backend/trainer-service/config/database.php';
$key = '003-economia-hogar-reset-v1';
$q = $db->prepare('SELECT version FROM migraciones_bd WHERE version=?');
$q->execute([$key]);
if ($q->fetchColumn()) {
    echo "Economía ya migrada; no se repite el reinicio.\n";
    exit();
}
$backup = [];
foreach (
    [
        'coleccion_pokemon',
        'miembros_equipo',
        'equipos',
        'cuidado_pokemon',
        'carteras_usuario',
        'inventario_comida',
        'movimientos_monedas',
    ]
    as $table
) {
    $backup[$table] = $db->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
}
$folder = dirname(__DIR__) . '/.local-backups';
if (!is_dir($folder)) {
    mkdir($folder, 0700, true);
}
file_put_contents($folder . '/.htaccess', "Require all denied\nDeny from all\n");
$path = $folder . '/progreso-antes-economia-' . date('Ymd-His') . '.json';
if (
    file_put_contents(
        $path,
        json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ) === false
) {
    throw new RuntimeException('No se pudo guardar el respaldo.');
}
$db->beginTransaction();
try {
    foreach (
        [
            'miembros_equipo',
            'equipos',
            'coleccion_pokemon',
            'cuidado_pokemon',
            'inventario_comida',
            'movimientos_monedas',
            'carteras_usuario',
        ]
        as $table
    ) {
        $db->exec('DELETE FROM ' . $table);
    }
    $q = $db->prepare('INSERT INTO migraciones_bd(version) VALUES(?)');
    $q->execute([$key]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
echo 'Reinicio aplicado: ' .
    count($backup['coleccion_pokemon']) .
    " compras antiguas y equipos retirados; cuentas y contraseñas conservadas.\n";
echo 'Respaldo local de progreso: ' . $path . "\n";
echo "Cada cuenta recibirá 1000 monedas y 3 cestas de bayas una sola vez al entrar.\n";
