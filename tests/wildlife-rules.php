<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\WildlifeRules as W;
use TrainerService\ExplorationMap as M;
function wildCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
function wildReject(callable $f): void
{
    try {
        $f();
    } catch (DomainException $e) {
        return;
    }
    throw new RuntimeException('Interacción inválida aceptada');
}
$fixture = [
    'ruta' => [
        ['x' => 2, 'y' => 2, 'duracion_ms' => 1000],
        ['x' => 3, 'y' => 2, 'duracion_ms' => 500],
        ['x' => 2, 'y' => 2, 'duracion_ms' => 500],
    ],
    'inicio' => 100,
    'vence' => 280,
    'modo' => 'libre',
    'pokemon' => M::species(19),
    'probabilidad' => 32,
];
wildCheck(W::position($fixture, 100) === [2.0, 2.0], 'Comienza descansando en su origen');
wildCheck(W::position($fixture, 101.25) === [2.5, 2.0], 'Posición interpolada entre puntos');
wildCheck(
    W::position($fixture, 103.25) === W::position($fixture, 101.25),
    'Recarga no reinicia la trayectoria',
);
wildReject(fn() => W::encounter($fixture, [30, 30], 101));
wildReject(fn() => W::encounter($fixture, [2, 2], 281));
$encounter = W::encounter($fixture, [2, 2], 101);
wildCheck(
    $encounter['pokemon']['id'] === 19 &&
        $encounter['probabilidad'] === 32 &&
        $encounter['intentos'] === 0,
    'El servidor conserva especie, probabilidad y contador de captura',
);
foreach (M::POOL as $pid) {
    $animal = W::create(M::species($pid), [12, 13], 100, false);
    foreach ($animal['ruta'] as $i => $point) {
        wildCheck(
            abs($point['x'] - 12) + abs($point['y'] - 13) <= 5,
            'Animal permanece dentro del radio visible de aparición',
        );
        wildCheck(M::walkable($point['x'], $point['y']), 'Animales no atraviesan árboles ni agua');
        if ($i) {
            wildCheck(
                abs($point['x'] - $animal['ruta'][$i - 1]['x']) +
                    abs($point['y'] - $animal['ruta'][$i - 1]['y']) <=
                    1,
                'Trayectoria con pasos adyacentes',
            );
        }
    }
    if ($animal['escondite']) {
        wildCheck(
            M::grid()[$animal['escondite'][1]][$animal['escondite'][0]] === 5 &&
                $animal['ruta'][0]['duracion_ms'] === 3200,
            'Se esconde en hierba real y deja tiempo para notar las hojas',
        );
    }
    $original = W::position($animal, 102);
    $flee = W::flee($animal, [12, 13], 102);
    wildCheck(
        W::position($flee, 102) === $original,
        'Huir no teletransporta ni regresa desde el destino final',
    );
    wildCheck(
        $flee['modo'] === 'huyendo' && $flee['vence'] === 106 && $flee['repetir'] === false,
        'Huida breve sin bucle',
    );
    wildReject(fn() => W::encounter($flee, $original, 103));
    $end = end($flee['ruta']);
    wildCheck(
        hypot(
            ...array_map(fn($a, $b) => $a - $b, W::position($flee, 200), [$end['x'], $end['y']]),
        ) < 0.001,
        'Al terminar no vuelve hacia el jugador',
    );
}
wildCheck(
    W::profile(19)['nombre'] === 'corredor' &&
        W::profile(183)['nombre'] === 'saltarín' &&
        W::profile(10)['nombre'] === 'paseante',
    'Perfiles variados de locomoción',
);
echo "OK: trayectorias transitables, reloj estable, paseo, salto, carrera, aproximación y huida sin teletransporte.\n";
