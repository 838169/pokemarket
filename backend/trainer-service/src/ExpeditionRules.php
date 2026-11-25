<?php
declare(strict_types=1);
namespace TrainerService;

/** Server-owned expedition resources and encounter checks; no client-supplied odds. */
final class ExpeditionRules
{
    public const MIN_CAPTURE_PERCENT = 1.0;
    public const INITIAL_TIME_MS = 60000;
    public const SIGHT = 6;
    public static function normalize(array $state): array
    {
        $state += [
            'tiempo_ms' => self::INITIAL_TIME_MS,
            'explorado' => [],
            'rastreo' => 0,
            'pasos_desde' => 0,
            'huellas_recientes' => [],
            'pasos_totales' => 0,
            'estampas' => array_values(
                array_unique(array_column(array_column($state['diario'] ?? [], 'pokemon'), 'id')),
            ),
            'recompensas_album' => [],
            'recompensas_categorias' => [],
            'rachas_captura' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
        ];
        self::reveal($state, $state['posicion']);
        $state += [
            'misiones_cobradas' => [
                'campamento_fecha' => '',
                'cartografia_inicio' => count($state['explorado']),
                'cartografia' => 0,
                'batallas' => 0,
            ],
        ];
        unset($state['resistencia']);
        return $state;
    }
    public static function missions(array $state, int $battles, string $today): array
    {
        $claimed = $state['misiones_cobradas'];
        $newTerrain = max(
            0,
            count($state['explorado']) -
                $claimed['cartografia_inicio'] -
                $claimed['cartografia'] * 40,
        );
        return [
            [
                'id' => 'campamento',
                'nombre' => 'Revisa el equipo en el campamento',
                'detalle' => 'Una vez al día. Puedes regresar aunque no tengas tiempo.',
                'progreso' => $state['posicion'] === ExplorationMap::START ? 1 : 0,
                'meta' => 1,
                'segundos' => 20,
                'cobrada' => $claimed['campamento_fecha'] === $today,
            ],
            [
                'id' => 'cartografia',
                'nombre' => 'Descubre 40 casillas nuevas',
                'detalle' => 'Cada tramo nuevo de la región cuenta.',
                'progreso' => min(40, $newTerrain),
                'meta' => 40,
                'segundos' => 20,
                'cobrada' => $claimed['cartografia'] > 0 && $newTerrain < 40,
            ],
            [
                'id' => 'batalla',
                'nombre' => 'Completa una batalla',
                'detalle' => 'Ganes o pierdas; la práctica no cuenta.',
                'progreso' => $battles > $claimed['batallas'] ? 1 : 0,
                'meta' => 1,
                'segundos' => 60,
                'cobrada' => $claimed['batallas'] > 0 && $battles <= $claimed['batallas'],
            ],
        ];
    }
    public static function claimMission(array &$state, string $id, int $battles, string $today): int
    {
        foreach (self::missions($state, $battles, $today) as $mission) {
            if ($mission['id'] !== $id) {
                continue;
            }
            if ($mission['cobrada'] || $mission['progreso'] < $mission['meta']) {
                throw new \DomainException('Todavía no puedes reclamar esta misión.');
            }
            if ($id === 'campamento') {
                $state['misiones_cobradas']['campamento_fecha'] = $today;
            } elseif ($id === 'cartografia') {
                // Claim the current milestone once, including previously accumulated terrain.
                $state['misiones_cobradas']['cartografia_inicio'] = count($state['explorado']) - 40;
                $state['misiones_cobradas']['cartografia'] = 1;
            } else {
                $state['misiones_cobradas']['batallas'] = $battles;
            }
            $state['tiempo_ms'] += $mission['segundos'] * 1000;
            return $mission['segundos'];
        }
        throw new \InvalidArgumentException('Misión no válida.');
    }
    public static function reveal(array &$state, array $point): void
    {
        $known = array_fill_keys($state['explorado'], true);
        for ($dy = -self::SIGHT; $dy <= self::SIGHT; $dy++) {
            for ($dx = -self::SIGHT; $dx <= self::SIGHT; $dx++) {
                [$x, $y] = [$point[0] + $dx, $point[1] + $dy];
                if (
                    $x >= 0 &&
                    $x < ExplorationMap::WIDTH &&
                    $y >= 0 &&
                    $y < ExplorationMap::HEIGHT &&
                    abs($dx) + abs($dy) <= self::SIGHT
                ) {
                    $known["$x,$y"] = true;
                }
            }
        }
        $state['explorado'] = array_keys($known);
    }
    /** Returns the actually travelled prefix; encounters can interrupt long click routes. */
    public static function journey(array &$state, array $path, bool $running, callable $roll): array
    {
        $returning = end($path) === ExplorationMap::START;
        $route = [$path[0]];
        $found = false;
        $grid = ExplorationMap::grid();
        foreach (array_slice($path, 1) as $point) {
            $cost = $running ? ExplorationMap::RUN_MS : ExplorationMap::WALK_MS;
            if (!$returning && $state['tiempo_ms'] < $cost) {
                break;
            }
            if (!$returning) {
                $state['tiempo_ms'] -= $cost;
            }
            $state['posicion'] = $point;
            $state['pasos_totales']++;
            $state['pasos_desde']++;
            $route[] = $point;
            self::reveal($state, $point);
            $key = implode(',', $point);
            $fresh = !in_array($key, $state['huellas_recientes'], true);
            $state['huellas_recientes'][] = $key;
            $state['huellas_recientes'] = array_slice($state['huellas_recientes'], -24);
            if (
                $returning ||
                !empty($state['avistamiento']) ||
                !$fresh ||
                $grid[$point[1]][$point[0]] !== 5
            ) {
                continue;
            }
            $state['rastreo']++;
            if ($state['pasos_desde'] < 6 || $state['rastreo'] % 3 !== 0) {
                continue;
            }
            $chance = $running ? 10 : 28;
            // A long quiet search eventually pays off; circling the same cells does not.
            if ($roll() <= $chance || (!$running && $state['rastreo'] >= 12)) {
                $found = true;
                $state['rastreo'] = 0;
                $state['pasos_desde'] = 0;
                break;
            }
        }
        return ['camino' => $route, 'avistamiento' => $found, 'regreso' => $returning];
    }
    public static function captureChance(int $power, bool $running, ?int $tier = null): float
    {
        $tier ??= $power >= 480 ? 3 : ($power >= 350 ? 2 : 1);
        $target = [1 => 0.2, 2 => 0.1, 3 => 0.05, 4 => 0.01][$tier];
        if ($running) {
            $target *= 0.75;
        }
        return max(self::MIN_CAPTURE_PERCENT, round((1 - pow(1 - $target, 1 / 3)) * 100, 2));
    }
    public static function captureProtected(array $state, int $tier): bool
    {
        return $state['rachas_captura'][$tier] >= [1 => 10, 2 => 20, 3 => 40, 4 => 100][$tier] - 1;
    }
}
