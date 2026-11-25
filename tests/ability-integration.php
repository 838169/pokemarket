<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\AbilityService;
use TrainerService\GameEconomy;
use TrainerService\MarketCatalog;
use TrainerService\ProgressionService;
$db = require dirname(__DIR__) . '/backend/trainer-service/config/database.php';
$uid = null;
$email = 'codex-ability-' . bin2hex(random_bytes(8)) . '@example.invalid';
function abilityCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function rejectAbility(callable $f): void
{
    try {
        $f();
    } catch (DomainException $e) {
        return;
    }
    throw new RuntimeException('Debe rechazar habilidad inválida');
}
try {
    $q = $db->prepare('INSERT INTO usuarios(usuario,nombre,email,password) VALUES(?,?,?,?)');
    $q->execute([
        bin2hex(random_bytes(8)),
        'Prueba de habilidades',
        $email,
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $uid = (int) $db->lastInsertId();
    $game = new GameEconomy($db);
    $game->completeWelcome($uid);
    $before = $game->wallet($uid)['monedas'];
    rejectAbility(fn() => $game->purchase($uid, 4, 'static'));
    abilityCheck($game->wallet($uid)['monedas'] === $before, 'Compra inválida no cobra');
    $purchase = $game->purchase($uid, 4, 'solar-power');
    abilityCheck(
        $purchase['pokemon']['habilidad_activa']['id'] === 'solar-power',
        'Habilidad elegida persiste en compra',
    );
    abilityCheck(
        $purchase['monedas'] === $before - MarketCatalog::get(4)['precio'],
        'El servidor cobra precio oficial una sola vez',
    );
    $service = new AbilityService($db);
    $profile = new ProgressionService($db);
    $actor = $profile->decorate($uid, MarketCatalog::get(4));
    $controller = (new ReflectionClass(
        TrainerService\ControladorBatalla::class,
    ))->newInstanceWithoutConstructor();
    $convert = new ReflectionMethod($controller, 'combatPokemon');
    $snapshot = $convert->invoke($controller, $actor);
    $balance = $game->wallet($uid)['monedas'];
    $service->equip($uid, 4, 'blaze');
    abilityCheck(
        $profile->decorate($uid, MarketCatalog::get(4))['habilidad_activa']['id'] === 'blaze',
        'Cambio persistente',
    );
    abilityCheck(
        $snapshot['habilidad_activa']['id'] === 'solar-power',
        'No altera el combate existente',
    );
    abilityCheck($game->wallet($uid)['monedas'] === $balance, 'Cambio gratuito');
    rejectAbility(fn() => $service->equip($uid, 25, 'static'));
    rejectAbility(fn() => $service->equip($uid, 4, 'static'));
    rejectAbility(fn() => $game->purchase($uid, 4, 'blaze'));
    abilityCheck($game->wallet($uid)['monedas'] === $balance, 'Compra duplicada no cobra');
    $db->prepare('DELETE FROM habilidades_pokemon WHERE usuario_id=?')->execute([$uid]);
    abilityCheck(
        $profile->decorate($uid, MarketCatalog::get(4))['habilidad_activa']['id'] === 'blaze',
        'Compañeros antiguos reciben habilidad por defecto gratis',
    );
    echo "OK: compra, persistencia, propiedad, saldo, duplicados y aislamiento del combate.\n";
} finally {
    if ($uid) {
        $db->prepare('DELETE FROM usuarios WHERE id=? AND email=?')->execute([$uid, $email]);
    }
}
