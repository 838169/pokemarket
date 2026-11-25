<?php
declare(strict_types=1);
namespace TrainerService;

/** Shared authoritative terrain; the browser receives this exact grid. */
final class ExplorationMap
{
    public const START = [5, 12];
    public const SITES = [[7, 9], [10, 6], [12, 13], [18, 10], [19, 5]];
    public const INTRO_POOL = [10, 13, 16, 19, 21, 29, 32, 43, 46, 48, 69, 161, 165, 183, 187];
    public const POOL = [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        10,
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18,
        19,
        20,
        21,
        22,
        23,
        24,
        25,
        26,
        27,
        28,
        29,
        30,
        31,
        32,
        33,
        34,
        35,
        36,
        37,
        38,
        39,
        40,
        41,
        42,
        43,
        44,
        45,
        46,
        47,
        48,
        49,
        50,
        51,
        52,
        53,
        54,
        55,
        56,
        57,
        58,
        59,
        60,
        61,
        62,
        63,
        64,
        65,
        66,
        67,
        68,
        69,
        70,
        71,
        72,
        73,
        74,
        75,
        76,
        77,
        78,
        79,
        80,
        81,
        82,
        83,
        84,
        94,
        95,
        123,
        130,
        131,
        143,
        144,
        145,
        146,
        149,
        150,
        151,
        161,
        165,
        183,
        187,
    ];
    public const WIDTH = 48;
    public const HEIGHT = 40;
    public const WALK_MS = 180;
    public const RUN_MS = 105;

    public static function grid(): array
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }
        $grid = [];
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $water =
                    $x === 0 || $y === 0 || $x === 23 || $y === 19 || $x + $y < 5 || $x + $y > 37;
                $river = $x === 15 + (int) floor(sin($y / 3));
                $tile = $water || $river ? 0 : 1;
                if (
                    !$water &&
                    (($y === 12 && $x <= 12) ||
                        ($x === 10 && $y >= 5 && $y <= 12) ||
                        ($y === 9 && $x >= 10 && $x <= 20))
                ) {
                    $tile = $river ? 4 : 2;
                }
                if ($tile === 1 && ($x * 17 + $y * 31) % 19 < 3) {
                    $tile = 3;
                }
                if ($tile === 1 && ($x * 7 + $y * 11) % 13 < 4) {
                    $tile = 5;
                }
                foreach (self::SITES as [$sx, $sy]) {
                    if (abs($x - $sx) + abs($y - $sy) <= 1) {
                        $tile = 5;
                    }
                }
                if ($x >= 24 || $y >= 20) {
                    $edge =
                        $x === 0 ||
                        $y === 0 ||
                        $x === self::WIDTH - 1 ||
                        $y === self::HEIGHT - 1 ||
                        $x + $y > 79;
                    $riverEast = abs($x - (31 + (int) floor(sin($y / 4) * 2))) <= 0;
                    $lake = ($x - 38) ** 2 / 30 + ($y - 20) ** 2 / 14 < 1;
                    $tile = $edge || $riverEast || $lake ? 0 : 1;
                    if ($tile === 1 && ($x * 17 + $y * 31) % 23 < 5) {
                        $tile = 3;
                    }
                    if ($tile === 1 && ($x * 7 + $y * 11) % 13 < 5) {
                        $tile = 5;
                    }
                    $road =
                        ($y === 9 && $x >= 24 && $x <= 43) ||
                        ($y === 28 && $x >= 10 && $x <= 43) ||
                        ($x === 42 && $y >= 9 && $y <= 34) ||
                        ($x === 10 && $y >= 20 && $y <= 28);
                    if ($road && !$edge) {
                        $tile = $tile === 0 ? 4 : 2;
                    }
                }
                // Two connections extend the old island without relocating existing trainers.
                if (($y === 9 && $x >= 20 && $x <= 25) || ($x === 10 && $y >= 12 && $y <= 22)) {
                    $tile = $tile === 0 ? 4 : 2;
                }
                if ([$x, $y] === self::START) {
                    $tile = 2;
                }
                $grid[$y][$x] = $tile;
            }
        }
        return $cached = $grid;
    }

    public static function walkable(int $x, int $y): bool
    {
        return isset(self::grid()[$y][$x]) && !in_array(self::grid()[$y][$x], [0, 3], true);
    }

    public static function path(array $from, array $to): array
    {
        if (!self::walkable($to[0], $to[1])) {
            throw new \DomainException('Elige un camino o una zona de hierba.');
        }
        $grid = self::grid();
        $queue = [$from];
        $key = fn($p) => $p[0] . ',' . $p[1];
        $parents = [$key($from) => null];
        for ($i = 0; $i < count($queue); $i++) {
            [$x, $y] = $queue[$i];
            if ([$x, $y] === $to) {
                $path = [];
                $current = $to;
                while ($current !== null) {
                    $path[] = $current;
                    $current = $parents[$key($current)];
                }
                return array_reverse($path);
            }
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $next = [$x + $dx, $y + $dy];
                $nextKey = $key($next);
                if (
                    !array_key_exists($nextKey, $parents) &&
                    isset($grid[$next[1]][$next[0]]) &&
                    !in_array($grid[$next[1]][$next[0]], [0, 3], true)
                ) {
                    $parents[$nextKey] = [$x, $y];
                    $queue[] = $next;
                }
            }
        }
        throw new \DomainException('No hay un camino hasta ese lugar.');
    }

    public static function describe(): array
    {
        return [
            'nombre' => 'Pradera del Alba',
            'terreno' => self::grid(),
            'inicio' => self::START,
            'puntos' => [], // Wildlife has no guaranteed coordinates.
            'modo' => 'expedicion',
            'campamento' => self::START,
            'vision' => ExpeditionRules::SIGHT,
            'ciclo' => [
                'dia_segundos' => 480,
                'noche_segundos' => 240,
                'transicion_segundos' => 35,
            ],
            'regiones' => [
                ['nombre' => 'Refugio del Alba', 'x' => 5, 'y' => 12],
                ['nombre' => 'Bosque de Bruma', 'x' => 27, 'y' => 8],
                ['nombre' => 'Lago Espejo', 'x' => 38, 'y' => 20],
                ['nombre' => 'Sendero del Sur', 'x' => 22, 'y' => 28],
            ],
            'paso_ms' => self::WALK_MS,
            'correr_ms' => self::RUN_MS,
            'especies' => array_map(fn($id) => self::species($id), self::POOL),
        ];
    }

    public static function albumSheets(): array
    {
        $species = array_map(fn($id) => self::species($id), self::INTRO_POOL);
        usort(
            $species,
            fn($a, $b) => $a['poder_base'] <=> $b['poder_base'] ?: $a['id'] <=> $b['id'],
        );
        $names = ['Primeros rastros', 'Exploradores del Alba', 'Retos de la pradera'];
        $extra = array_map(
            fn($id) => self::species($id),
            array_values(array_diff(self::POOL, self::INTRO_POOL)),
        );
        usort(
            $extra,
            fn($a, $b) => $a['rareza_nivel'] <=> $b['rareza_nivel'] ?:
            ($a['poder_base'] <=> $b['poder_base'] ?:
            $a['id'] <=> $b['id']),
        );
        $species = array_merge($species, $extra);
        return array_map(function ($index) use ($species, $names) {
            $page = array_slice($species, $index * 5, 5);
            $difficulty = max(array_column($page, 'rareza_nivel'));
            $titles = [
                1 => 'Pradera tranquila',
                2 => 'Senderos ocultos',
                3 => 'Huellas difíciles',
                4 => 'Leyendas del Alba',
            ];
            return [
                'id' => $index,
                'nombre' => $names[$index] ?? $titles[$difficulty] . ' ' . ($index + 1),
                'dificultad' => $difficulty,
                'especies' => $page,
            ];
        }, range(0, 19));
    }
    public static function albumCategories(): array
    {
        $rewards = [
            1 => ['nombre' => 'Básica', 'monedas' => 500, 'tarjetas' => 1, 'bolas' => [], 'comida' => []],
            2 => ['nombre' => 'Media', 'monedas' => 600, 'tarjetas' => 0, 'bolas' => ['poke' => 2, 'rapida' => 1], 'comida' => ['baya' => 3]],
            3 => ['nombre' => 'Avanzada', 'monedas' => 800, 'tarjetas' => 1, 'bolas' => ['super' => 2, 'ultra' => 1], 'comida' => ['curry' => 2, 'galleta' => 3]],
            4 => [
                'nombre' => 'Leyendas', 'monedas' => 1000, 'tarjetas' => 0, 'bolas' => [],
                'comida' => ['baya' => 2, 'curry' => 2, 'galleta' => 2],
                'pokemon' => ['nivel' => 2] + MarketCatalog::get(384),
            ],
        ];
        foreach ($rewards as $tier => &$category) {
            $category['id'] = $tier;
            $category['especies'] = array_values(array_filter(self::POOL, fn($id) => self::rarityLevel($id) === $tier));
        }
        unset($category);
        return array_values($rewards);
    }
    public static function rarityLevel(int $id): int
    {
        if (in_array($id, [144, 145, 146, 150, 151], true)) {
            return 4;
        }
        $power = MarketCatalog::get($id)['poder_base'];
        if ($power >= 480 || in_array($id, [83, 94, 95, 123, 130, 131, 143, 149], true)) {
            return 3;
        }
        return $power >= 350 || in_array($id, [1, 4, 7, 25], true) ? 2 : 1;
    }
    public static function encounterCandidates(array $stamps, int $roll): array
    {
        $tier = $roll <= 700 ? 1 : ($roll <= 930 ? 2 : ($roll <= 990 ? 3 : 4));
        $pool = array_values(array_filter(self::POOL, fn($id) => self::rarityLevel($id) === $tier));
        $fresh = array_values(array_diff($pool, $stamps));
        return $fresh ?: $pool;
    }
    public static function species(int $id): array
    {
        $p = MarketCatalog::get($id);
        $tier = self::rarityLevel($id);
        return [
            'rareza_nivel' => $tier,
            'rareza' => [1 => 'Común', 2 => 'Escaso', 3 => 'Raro', 4 => 'Legendario'][$tier],
        ] +
            array_intersect_key(
                $p,
                array_flip([
                    'id',
                    'nombre',
                    'tipos',
                    'poder_base',
                    'sprite_animado_hd',
                    'sprite_animado_hd_espalda',
                ]),
            );
    }
}
