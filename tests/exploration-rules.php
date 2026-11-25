<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\ExplorationMap;
function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$grid = ExplorationMap::grid();
expect(count($grid) === 40 && count($grid[0]) === 48, 'Región cuatro veces mayor');
expect(
    !ExplorationMap::walkable(0, 0) &&
        !ExplorationMap::walkable(-1, 10) &&
        !ExplorationMap::walkable(48, 2),
    'Bordes no transitables',
);
foreach ([[27, 9], [42, 9], [42, 28], [10, 28]] as $destination) {
    $route = ExplorationMap::path(ExplorationMap::START, $destination);
    expect(end($route) === $destination, 'Las nuevas regiones conectan con el campamento');
}
foreach (ExplorationMap::SITES as $site) {
    $path = ExplorationMap::path(ExplorationMap::START, $site);
    expect(
        $path[0] === ExplorationMap::START && end($path) === $site,
        'Todos los avistamientos son accesibles',
    );
    foreach ($path as $i => [$x, $y]) {
        expect(ExplorationMap::walkable($x, $y), 'El camino no cruza árboles ni agua');
        if ($i) {
            expect(
                abs($x - $path[$i - 1][0]) + abs($y - $path[$i - 1][1]) === 1,
                'Pasos adyacentes',
            );
        }
    }
}
expect(
    ExplorationMap::path(ExplorationMap::START, ExplorationMap::START) === [ExplorationMap::START],
    'Destino actual',
);
try {
    ExplorationMap::path(ExplorationMap::START, [0, 0]);
    throw new RuntimeException('Destino imposible aceptado');
} catch (DomainException) {
}
foreach (ExplorationMap::describe()['especies'] as $species) {
    expect(
        in_array($species['rareza_nivel'], [1, 2, 3, 4], true),
        'Cada especie tiene rareza de encuentro',
    );
    expect(!isset($species['precio']), 'El mapa no controla precios de catálogo');
}
expect(
    count(ExplorationMap::POOL) === 100 && count(array_unique(ExplorationMap::POOL)) === 100,
    'Cien especies sin duplicados',
);
$sheets = ExplorationMap::albumSheets();
expect(count($sheets) === 20, 'Veinte hojas de cinco estampas');
$ids = array_merge(...array_map(fn($sheet) => array_column($sheet['especies'], 'id'), $sheets));
sort($ids);
$pool = ExplorationMap::POOL;
sort($pool);
expect($ids === $pool, 'El álbum contiene exactamente las cien especies');
foreach (
    [1 => 1, 700 => 1, 701 => 2, 930 => 2, 931 => 3, 990 => 3, 991 => 4, 1000 => 4]
    as $roll => $tier
) {
    foreach (ExplorationMap::encounterCandidates([], $roll) as $id) {
        expect(
            ExplorationMap::rarityLevel($id) === $tier,
            'Las fronteras de rareza mantienen sus probabilidades',
        );
    };
}
echo "OK: mapa autoritativo, rutas, cien especies, veinte hojas y rareza ponderada.\n";
