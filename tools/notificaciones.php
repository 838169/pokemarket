<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);
require $root . '/backend/trainer-service/bootstrap.php';
$once = in_array('--once', $argv, true);
$db = null;
if (!$once) echo "Procesando solicitudes de amistad e invitaciones de sala. Ctrl+C para pausar.\n";
do {
    try {
        $db ??= require $root . '/backend/trainer-service/config/database.php';
        $count = (new TrainerService\FriendshipEvents($db))->deliverPending()
            + (new TrainerService\SalaEvents($db))->deliverPending();
        if ($count || $once) echo "Eventos procesados: $count\n";
        if (!$once && $count < 100) sleep(2);
    } catch (Throwable $error) {
        fwrite(STDERR, "El proceso de notificaciones no pudo acceder a la cola de eventos.\n");
        if ($once) exit(1);
        $db = null;
        sleep(5);
    }
} while (!$once);
