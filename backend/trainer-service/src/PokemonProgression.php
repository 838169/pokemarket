<?php
declare(strict_types=1);
namespace TrainerService;

/** Official growth tables and neutral-nature stats, with fixed IV 15 and EV 0. */
final class PokemonProgression
{
    public const START_LEVEL = 1;
    public const MAX_LEVEL = 10;

    private static function data(): array
    {
        static $data;
        return $data ??= json_decode(
            file_get_contents(dirname(__DIR__) . '/config/progression_catalog.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
    public static function species(int $id): array
    {
        return self::data()['pokemon'][$id] ?? throw new \DomainException('Especie no disponible.');
    }
    public static function threshold(int $id, int $level): int
    {
        $growth = self::species($id)['crecimiento'];
        return self::data()['curvas'][$growth][max(1, min(100, $level))];
    }
    public static function level(int $id, int $experience): int
    {
        $level = 1;
        while (
            $level < self::MAX_LEVEL &&
            $experience >= self::trainingThreshold($id, $level + 1)
        ) {
            $level++;
        }
        return $level;
    }
    public static function trainingThreshold(int $id, int $level): int
    {
        $total = 0;
        $previous = 0;
        for ($next = 2; $next <= max(1, min(self::MAX_LEVEL, $level)); $next++) {
            $cost = max(
                $previous + 1,
                self::threshold($id, $next) - self::threshold($id, $next - 1),
            );
            $total += $cost;
            $previous = $cost;
        }
        return $total;
    }
    public static function available(int $id, int $level): array
    {
        return array_values(
            array_filter(
                self::species($id)['aprendizaje'],
                static fn(array $m): bool => $m['nivel'] <= $level,
            ),
        );
    }
    public static function stats(array $pokemon, int $level): array
    {
        $out = [];
        foreach ($pokemon['estadisticas'] as $key => $base) {
            $value = intdiv((2 * $base + 15) * $level, 100);
            $out[$key] =
                $key === 'hp' ? ($pokemon['id'] === 292 ? 1 : $value + $level + 10) : $value + 5;
        }
        return $out;
    }
    public static function profile(int $id, int $experience, ?array $equipped = null): array
    {
        $experience = min(
            self::trainingThreshold($id, self::MAX_LEVEL),
            max(self::trainingThreshold($id, self::START_LEVEL), $experience),
        );
        $level = self::level($id, $experience);
        $available = self::available($id, $level);
        if ($equipped === null) {
            $equipped = array_column(array_slice($available, -4), 'nombre');
        }
        $known = array_column($available, null, 'nombre');
        $selected = [];
        foreach (array_unique($equipped) as $name) {
            if (isset($known[$name]) && count($selected) < 4) {
                $selected[] = $known[$name];
            }
        }
        if (!$selected && $available) {
            $selected = array_slice($available, -4);
        }
        $floor = self::trainingThreshold($id, $level);
        $next = $level < self::MAX_LEVEL ? self::trainingThreshold($id, $level + 1) : $floor;
        return [
            'nivel' => $level,
            'nivel_maximo' => self::MAX_LEVEL,
            'experiencia' => $experience,
            'experiencia_nivel' => $experience - $floor,
            'experiencia_siguiente' => $next - $floor,
            'porcentaje' =>
                $level === self::MAX_LEVEL
                    ? 100
                    : (int) floor((100 * ($experience - $floor)) / max(1, $next - $floor)),
            'movimientos' => $selected,
            'disponibles' => $available,
            'proximos' => array_slice(
                array_values(
                    array_filter(
                        self::species($id)['aprendizaje'],
                        static fn(array $m): bool => $m['nivel'] > $level &&
                            $m['nivel'] <= self::MAX_LEVEL,
                    ),
                ),
                0,
                3,
            ),
            'version' => self::species($id)['version'],
        ];
    }
    public static function atLevel(array $pokemon, int $level): array
    {
        $level = max(self::START_LEVEL, min(self::MAX_LEVEL, $level));
        $pokemon['nivel'] = $level;
        $pokemon['estadisticas_base'] = $pokemon['estadisticas'];
        $pokemon['estadisticas'] = self::stats($pokemon, $level);
        $pokemon['movimientos'] = array_slice(self::available($pokemon['id'], $level), -4);
        return $pokemon;
    }
    public static function milestones(array $pokemon): array
    {
        $id = $pokemon['id'];
        $moves = [];
        foreach (self::available($id, self::MAX_LEVEL) as $move) {
            $moves[max(self::START_LEVEL, $move['nivel'])][] = $move['nombre_mostrado'];
        }
        $levels = [];
        for ($level = self::START_LEVEL; $level <= self::MAX_LEVEL; $level++) {
            $levels[] = [
                'nivel' => $level,
                'estadisticas' => self::stats($pokemon, $level),
                'ataques' => $moves[$level] ?? [],
            ];
        }
        return $levels;
    }
    /** Generation VII+ scaled EXP, no held-item/trade/affection bonuses. */
    public static function reward(
        int $enemyId,
        int $enemyLevel,
        int $ownLevel,
        bool $participated,
    ): int {
        $base = self::species($enemyId)['experiencia_base'];
        $scaled =
            (($base * $enemyLevel) / 5) *
                pow((2 * $enemyLevel + 10) / ($enemyLevel + $ownLevel + 10), 2.5) +
            1;
        return max(1, (int) floor($scaled * ($participated ? 1 : 0.5)));
    }
}
