<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\GameEconomy;
use TrainerService\ProgressionService;
use TrainerService\PokemonProgression as P;
use TrainerService\MarketCatalog;
$db = require dirname(__DIR__) . '/backend/trainer-service/config/database.php';
$uid = null;
$email = 'codex-progress-' . bin2hex(random_bytes(8)) . '@example.invalid';
function checkProgress(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function rejectProgress(callable $f): void
{
    try {
        $f();
    } catch (DomainException $e) {
        return;
    }
    throw new RuntimeException('Debe rechazar la selección inválida');
}
try {
    $q = $db->prepare('INSERT INTO usuarios(usuario,nombre,email,password) VALUES(?,?,?,?)');
    $q->execute([
        bin2hex(random_bytes(8)),
        'Entrenador de prueba',
        $email,
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $uid = (int) $db->lastInsertId();
    $game = new GameEconomy($db);
    $game->completeWelcome($uid);
    $bought = $game->purchase($uid, 25);
    checkProgress($bought['pokemon']['nivel'] === 1, 'Compra inicial a nivel uno');
    $service = new ProgressionService($db);
    $before = $service->profile($uid, 25);
    rejectProgress(fn() => $service->equip($uid, 25, ['thunderbolt']));
    rejectProgress(fn() => $service->equip($uid, 7, ['tackle']));
    $name = $before['movimientos'][0]['nombre'];
    rejectProgress(fn() => $service->equip($uid, 25, [$name, $name]));
    $service->equip($uid, 25, [$name]);
    checkProgress(count($service->profile($uid, 25)['movimientos']) === 1, 'Selección persistente');
    $game->purchase($uid, 129);
    $actor = $service->decorate($uid, MarketCatalog::get(25));
    $actor['hp_actual'] = $actor['hp_maximo'] = $actor['estadisticas']['hp'];
    $reserve = $service->decorate($uid, MarketCatalog::get(129));
    $reserve['hp_actual'] = $reserve['hp_maximo'] = $reserve['estadisticas']['hp'];
    $enemy = P::atLevel(MarketCatalog::get(7), 100);
    $enemy['hp_actual'] = 1;
    $enemy['hp_maximo'] = $enemy['estadisticas']['hp'];
    $battle = [
        'id' => 'progress-' . bin2hex(random_bytes(8)),
        'estado' => 'en_curso',
        'equipo_jugador' => ['pokemon' => [$actor, $reserve, ['id' => 1, 'hp_actual' => 0]]],
        'equipo_rival' => ['pokemon' => [$enemy]],
        'activo_jugador' => 0,
        'activo_rival' => 0,
        'historial' => [],
    ];
    $controller = (new ReflectionClass(
        TrainerService\ControladorBatalla::class,
    ))->newInstanceWithoutConstructor();
    $attack = new ReflectionMethod($controller, 'attack');
    $attack->invokeArgs($controller, [&$battle, 'jugador', $actor['movimientos'][0]]);
    checkProgress(
        count($battle['eventos_experiencia'][0]['receptores']) === 2,
        'El KO premia al activo y a la reserva consciente',
    );
    checkProgress(
        $battle['eventos_experiencia'][0]['receptores'][1]['participo'] === false,
        'La reserva obtiene la parte reducida',
    );
    $game->settle($uid, $battle);
    $after = $service->profile($uid, 25);
    checkProgress($after['nivel'] > 1, 'Gana niveles al derrotar rivales');
    checkProgress(
        $battle['equipo_jugador']['pokemon'][0]['nivel'] === $after['nivel'],
        'El nivel se actualiza dentro del combate',
    );
    checkProgress(
        $battle['equipo_jugador']['pokemon'][0]['hp_maximo'] > $actor['hp_maximo'],
        'Los PS crecen dentro del combate',
    );
    checkProgress(
        $service->profile($uid, 129)['experiencia'] > P::threshold(129, 1),
        'Magikarp progresa como reserva',
    );
    checkProgress(
        $battle['progreso_ganado'][0]['nuevos_movimientos'] === [],
        'Pikachu no recibe ataques inventados antes del nivel diez',
    );
    $game->settle($uid, $battle);
    checkProgress(
        $service->profile($uid, 25)['experiencia'] === $after['experiencia'],
        'Repetir un turno no duplica EXP',
    );
    $recovered = $battle;
    unset($recovered['progreso_ganado']);
    $game->settle($uid, $recovered);
    checkProgress(
        $service->profile($uid, 25)['experiencia'] === $after['experiencia'],
        'Recuperar batalla no duplica EXP',
    );
    $home = $game->home($uid)['pokemon'][0];
    checkProgress($home['nivel'] === $after['nivel'], 'El Hogar recupera el nivel guardado');
    checkProgress(
        $home['estadisticas']['hp'] > $bought['pokemon']['estadisticas']['hp'],
        'Las estadísticas mejoran',
    );
    checkProgress(
        $home['debilidades'] === $bought['pokemon']['debilidades'],
        'Las debilidades permanecen',
    );
    $available = array_column($after['disponibles'], 'nombre');
    $service->equip($uid, 25, array_slice($available, -4));
    checkProgress(
        count($service->profile($uid, 25)['movimientos']) <= 4,
        'Aprendizaje respeta las cuatro ranuras',
    );
    $q = $db->prepare(
        'UPDATE progreso_pokemon SET experiencia=? WHERE usuario_id=? AND pokemon_id=25',
    );
    $q->execute([P::threshold(25, 100), $uid]);
    $battle['id'] .= '-cap';
    $game->settle($uid, $battle);
    checkProgress($service->profile($uid, 25)['nivel'] === 10, 'Nivel máximo persistente');
    echo "OK: compra, persistencia, niveles, EXP una sola vez, ataques ilegales/ajenos, aprendizaje y nivel 10.\n";
} finally {
    if ($uid) {
        $q = $db->prepare('DELETE FROM usuarios WHERE id=? AND email=?');
        $q->execute([$uid, $email]);
    }
}
