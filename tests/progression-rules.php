<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\PokemonProgression as P;
use TrainerService\MarketCatalog;
use TrainerService\BattleRules;
function verifyProgress(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
verifyProgress(P::threshold(25, 100) === 1000000, 'Pikachu: curva media oficial');
verifyProgress(P::threshold(1, 100) === 1059860, 'Bulbasaur: curva parabólica oficial');
$initial = P::profile(25, 0);
verifyProgress($initial['nivel'] === 1, 'Compra a nivel 1');
verifyProgress(
    !in_array('thunderbolt', array_column($initial['movimientos'], 'nombre'), true),
    'No se regalan ataques avanzados',
);
verifyProgress(P::level(25, P::threshold(25, 6) - 1) === 5, 'Umbral exacto antes de subir');
verifyProgress(P::level(25, P::threshold(25, 6)) === 6, 'Sube en el umbral oficial');
$max = P::profile(25, PHP_INT_MAX);
verifyProgress(
    $max['nivel'] === 10 &&
        $max['porcentaje'] === 100 &&
        $max['experiencia_siguiente'] === 0 &&
        $max['proximos'] === [],
    'Nivel 10 sin desbordamiento ni ataques fuera del límite',
);
$bulbasaur = MarketCatalog::get(1);
$route = P::milestones($bulbasaur);
verifyProgress(
    array_column($route, 'nivel') === range(1, 10),
    'La ficha muestra todos los niveles',
);
verifyProgress(
    $route[2]['ataques'] ===
        array_column(
            array_values(array_filter(P::available(1, 3), fn($move) => $move['nivel'] === 3)),
            'nombre_mostrado',
        ) && $route[2]['estadisticas'] === P::stats($bulbasaur, 3),
    'La ruta usa los ataques y estadísticas del juego para ese nivel',
);
verifyProgress(
    !in_array('vine-whip', array_column(P::available(1, 1), 'nombre'), true) &&
        in_array('vine-whip', array_column(P::available(1, 3), 'nombre'), true),
    'Bulbasaur aprende Látigo Cepa al nivel oficial, no al comprar',
);
verifyProgress(
    P::profile(25, PHP_INT_MAX, ['thunderbolt'])['movimientos'] !== [],
    'El límite nuevo conserva ataques básicos utilizables',
);
verifyProgress(P::stats($bulbasaur, 10)['hp'] === 30, 'PS calculados a nivel 10, IV 15 y EV 0');
verifyProgress(P::stats(MarketCatalog::get(292), 100)['hp'] === 1, 'Shedinja siempre tiene un PS');
verifyProgress(P::available(129, 5) === [], 'Magikarp no aprende ataques inventados');
verifyProgress(
    P::available(129, 15)[0]['nombre'] === 'tackle',
    'Magikarp aprende Placaje en su nivel oficial',
);
verifyProgress(
    P::reward(7, 10, 5, true) > P::reward(7, 10, 20, true),
    'El compañero pequeño recibe más EXP',
);
verifyProgress(
    P::reward(7, 10, 5, false) < P::reward(7, 10, 5, true),
    'Reservas reciben parte de EXP',
);
foreach (MarketCatalog::all() as $id => $pokemon) {
    $previousCost = 0;
    for ($level = 1; $level < P::MAX_LEVEL; $level++) {
        $cost = P::trainingThreshold($id, $level + 1) - P::trainingThreshold($id, $level);
        verifyProgress(
            $cost > $previousCost,
            'Cada salto cuesta más EXP que el anterior para las 1025 especies',
        );
        verifyProgress(
            P::level($id, P::trainingThreshold($id, $level + 1) - 1) === $level,
            'Antes del umbral no sube de nivel',
        );
        verifyProgress(
            P::level($id, P::trainingThreshold($id, $level + 1)) === $level + 1,
            'Al alcanzar el umbral sube exactamente un nivel',
        );
        $previousCost = $cost;
    }
    $last = 0;
    for ($level = 1; $level <= 100; $level++) {
        $xp = P::threshold($id, $level);
        verifyProgress($xp >= $last, 'Curva de experiencia creciente');
        $last = $xp;
    }
    $p = P::profile($id, 0);
    verifyProgress(count($p['movimientos']) <= 4, 'Máximo cuatro movimientos');
    foreach ($p['movimientos'] as $move) {
        verifyProgress($move['nivel'] <= 1, 'Solo ataques oficiales de nivel 1');
    }
    verifyProgress(
        BattleRules::weaknesses($pokemon['tipos']) ===
            BattleRules::weaknesses(P::atLevel($pokemon, 100)['tipos']),
        'El nivel no reduce las debilidades',
    );
}
echo "OK: 1025 especies, curvas oficiales, ataques por nivel, cuatro ranuras, estadísticas, EXP y tipos.\n";
