<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\ExpeditionRules as R;
use TrainerService\ExplorationMap as M;
use TrainerService\ExplorationService as S;
function expeditionCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$old = ['posicion' => M::START, 'pokeballs' => 19, 'descubiertos' => [25], 'encuentro' => null];
foreach (['poke' => 1, 'super' => 1.5, 'ultra' => 2] as $ball => $factor) {
    expeditionCheck(S::ballFactor($ball, [], 0) === (float) $factor, 'Multiplicador estándar');
}
expeditionCheck(
    S::ballFactor('rapida', ['intentos' => 0], 0) === 5.0 &&
        S::ballFactor('rapida', ['intentos' => 1], 0) === 5.0 &&
        S::ballFactor('rapida', ['intentos' => 2], 0) === 5.0,
    'Veloz conserva la probabilidad en todos los lanzamientos',
);
expeditionCheck(
    S::ballFactor('ocaso', [], 479) === 1.0 &&
        S::ballFactor('ocaso', [], 480) === 3.0 &&
        S::ballFactor('ocaso', [], 720) === 1.0,
    'Ocaso usa el mismo ciclo día y noche que el mapa',
);
expeditionCheck(
    S::ballFactor('malla', ['pokemon' => ['tipos' => ['water']]], 0) === 3.5 &&
        S::ballFactor('malla', ['pokemon' => ['tipos' => ['bug', 'flying']]], 0) === 3.5 &&
        S::ballFactor('malla', ['pokemon' => ['tipos' => ['fire']]], 0) === 1.0,
    'Malla comprueba los tipos en servidor',
);
$s = R::normalize($old);
expeditionCheck(
    $s['pokeballs'] === 19 && $s['descubiertos'] === [25],
    'Migración conserva inventario y descubrimientos',
);
expeditionCheck(
    $s['tiempo_ms'] === 60000 && count($s['explorado']) < 480,
    'Inicio limitado por niebla',
);
expeditionCheck(M::describe()['puntos'] === [], 'No se revelan puntos garantizados');
$before = $s;
for ($i = 0; $i < 30; $i++) {
    R::journey($s, [M::START], false, fn() => 1);
}
expeditionCheck(
    $s['rastreo'] === 0 && $s['pasos_totales'] === 0,
    'Quedarse quieto no genera encuentros',
);
[$x, $y] = M::SITES[0];
$ready = R::normalize([
    'posicion' => [$x - 1, $y],
    'tiempo_ms' => 60000,
    'rastreo' => 2,
    'pasos_desde' => 8,
]);
$walk = $ready;
$r = R::journey($walk, [[$x - 1, $y], [$x, $y]], false, fn() => 10);
expeditionCheck(
    $r['avistamiento'] && $walk['tiempo_ms'] === 60000 - M::WALK_MS && $walk['rastreo'] === 0,
    'Caminar consume solo el tiempo autorizado',
);
$run = $ready;
$r = R::journey($run, [[$x - 1, $y], [$x, $y]], true, fn() => 11);
expeditionCheck(
    !$r['avistamiento'] && $run['tiempo_ms'] === 60000 - M::RUN_MS,
    'Correr conserva la misma unidad de tiempo',
);
$run = $ready;
$r = R::journey($run, [[$x - 1, $y], [$x, $y]], true, fn() => 4);
expeditionCheck($r['avistamiento'], 'Carrera todavía puede encontrar Pokémon');
$fresh = $ready;
$r = R::journey($fresh, [[$x - 1, $y], [$x, $y]], false, fn() => 29);
expeditionCheck(
    !$r['avistamiento'],
    'Encuentro no garantizado aunque se llegue a un antiguo punto',
);
$recent = $ready;
$recent['huellas_recientes'] = ["$x,$y"];
$r = R::journey($recent, [[$x - 1, $y], [$x, $y]], false, fn() => 1);
expeditionCheck(!$r['avistamiento'] && $recent['rastreo'] === 2, 'No farmear una casilla reciente');
$young = $ready;
$young['pasos_desde'] = 0;
$r = R::journey($young, [[$x - 1, $y], [$x, $y]], false, fn() => 1);
expeditionCheck(!$r['avistamiento'], 'Separación de seis pasos entre encuentros');
$pity = $ready;
$pity['rastreo'] = 11;
$r = R::journey($pity, [[$x - 1, $y], [$x, $y]], false, fn() => 100);
expeditionCheck($r['avistamiento'], 'Rastreo largo en silencio evita sequías ilimitadas');
$long = $ready;
$r = R::journey($long, [[$x - 1, $y], [$x, $y], [$x, $y + 1]], false, fn() => 1);
expeditionCheck(
    count($r['camino']) === 2 && $long['posicion'] === [$x, $y],
    'Encuentro interrumpe ruta en la casilla real',
);
$exhausted = R::normalize(['posicion' => [$x, $y], 'tiempo_ms' => 0]);
$r = R::journey($exhausted, M::path([$x, $y], M::START), false, fn() => 1);
expeditionCheck(
    $exhausted['posicion'] === M::START && $exhausted['tiempo_ms'] === 0 && !$r['avistamiento'],
    'Regreso seguro y gratuito incluso agotado',
);
$exhausted = R::normalize(['posicion' => [$x - 1, $y], 'tiempo_ms' => 0]);
$r = R::journey($exhausted, [[$x - 1, $y], [$x, $y]], false, fn() => 1);
expeditionCheck(
    count($r['camino']) === 1 && $exhausted['posicion'] === [$x - 1, $y],
    'No avanzar agotado lejos del campamento',
);
expeditionCheck(R::captureChance(300, false) > R::captureChance(300, true), 'Ruido reduce captura');
expeditionCheck(
    R::captureChance(180, false) > R::captureChance(390, false),
    'Especies de mayor potencial son más difíciles',
);
foreach (M::POOL as $id) {
    expeditionCheck(
        R::captureChance(M::species($id)['poder_base'], false) < 65,
        'Captura más exigente que antes',
    );
}
function rejectMission(callable $f): void
{
    try {
        $f();
    } catch (DomainException | InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException('Recompensa inválida aceptada');
}
$migrated = R::normalize(['posicion' => M::START, 'resistencia' => 0]);
expeditionCheck(
    $migrated['tiempo_ms'] === 60000,
    'Usuarios agotados reciben su primer minuto al migrar',
);
$idle = R::normalize($migrated);
expeditionCheck($idle['tiempo_ms'] === $migrated['tiempo_ms'], 'Leer el estado no consume tiempo');
$missions = R::normalize(['posicion' => M::START, 'tiempo_ms' => 0]);
expeditionCheck(
    R::claimMission($missions, 'campamento', 0, '2026-11-13') === 20 &&
        $missions['tiempo_ms'] === 20000,
    'Preparación diaria rescata una cuenta sin tiempo',
);
rejectMission(fn() => R::claimMission($missions, 'campamento', 0, '2026-11-13'));
rejectMission(fn() => R::claimMission($missions, 'batalla', 0, '2026-11-13'));
R::claimMission($missions, 'batalla', 1, '2026-11-13');
expeditionCheck($missions['tiempo_ms'] === 80000, 'Una batalla completa da un minuto');
rejectMission(fn() => R::claimMission($missions, 'batalla', 1, '2026-11-13'));
rejectMission(fn() => R::claimMission($missions, 'cartografia', 1, '2026-11-13'));
$missions['explorado'] = array_merge(
    $missions['explorado'],
    array_map(fn($i) => 'new' . $i, range(1, 40)),
);
R::claimMission($missions, 'cartografia', 1, '2026-11-13');
expeditionCheck($missions['tiempo_ms'] === 100000, 'Cartografiar recompensa veinte segundos');
rejectMission(fn() => R::claimMission($missions, 'cartografia', 1, '2026-11-13'));
rejectMission(fn() => R::claimMission($missions, 'inventada', 1, '2026-11-13'));
R::claimMission($missions, 'campamento', 1, '2026-11-14');
expeditionCheck($missions['tiempo_ms'] === 120000, 'La misión diaria se puede repetir otro día');
$backlog = R::normalize(['posicion' => M::START]);
$backlog['explorado'] = array_merge(
    $backlog['explorado'],
    array_map(fn($i) => 'backlog' . $i, range(1, 125)),
);
R::claimMission($backlog, 'cartografia', 8, '2026-11-13');
$afterClaim = $backlog['tiempo_ms'];
for ($click = 0; $click < 5; $click++) {
    rejectMission(fn() => R::claimMission($backlog, 'cartografia', 8, '2026-11-13'));
}
expeditionCheck(
    $backlog['tiempo_ms'] === $afterClaim && R::missions($backlog, 8, '2026-11-13')[1]['cobrada'],
    'Un tramo acumulado se cobra una vez y queda marcado',
);
$backlog['explorado'] = array_merge(
    $backlog['explorado'],
    array_map(fn($i) => 'fresh' . $i, range(1, 40)),
);
expeditionCheck(
    !R::missions($backlog, 8, '2026-11-13')[1]['cobrada'],
    'Solo cuarenta casillas nuevas habilitan otra misión',
);
R::claimMission($backlog, 'cartografia', 8, '2026-11-13');
R::claimMission($backlog, 'batalla', 8, '2026-11-13');
rejectMission(fn() => R::claimMission($backlog, 'batalla', 8, '2026-11-13'));
expeditionCheck(
    R::missions($backlog, 8, '2026-11-13')[2]['cobrada'],
    'Batallas históricas no permiten cobros consecutivos',
);
R::claimMission($backlog, 'batalla', 9, '2026-11-13');
$limited = $ready;
$limited['tiempo_ms'] = M::WALK_MS;
$journey = R::journey($limited, [[$x - 1, $y], [$x, $y], [$x, $y + 1]], false, fn() => 100);
expeditionCheck(
    count($journey['camino']) === 2 && $limited['tiempo_ms'] === 0,
    'Nunca se autoriza movimiento que exceda el reloj',
);
echo "OK: tiempo de movimiento, pausas, migración, regreso seguro y misiones sin recompensas duplicadas.\n";

expeditionCheck(R::captureChance(0, false, 4) === 1.0, 'Legendario tiene un mínimo real del 1%');
expeditionCheck(R::captureChance(0, true, 4) === 1.0, 'Correr tampoco reduce la captura por debajo del mínimo');
foreach (S::captureReferences() as $reference) {
    foreach ($reference['bolas'] as $ball => $rates) {
        expeditionCheck(min($rates) >= 1 && max($rates) <= 100, 'Porcentaje dentro del intervalo');
        $base = R::captureChance(0, false, $reference['id']);
        expeditionCheck($rates['normal'] === round(min(100, $base * S::ballFactor($ball, ['intentos' => 2], 0)), 2), 'La tienda coincide con la probabilidad real del tercer intento');
    }
    expeditionCheck($reference['bolas']['ocaso']['noche'] > $reference['bolas']['ocaso']['normal'], 'Ocaso distingue día y noche');
    expeditionCheck($reference['bolas']['malla']['agua_bicho'] > $reference['bolas']['malla']['normal'], 'Malla distingue tipos compatibles');
}
