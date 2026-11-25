<?php
declare(strict_types=1);
namespace TrainerService;

/** A sighting has a server-owned looping route; rendering and interaction use the same clock. */
final class WildlifeRules
{
    public static function profile(int $id): array
    {
        if (in_array($id, [19, 21, 161], true)) {
            return ['nombre' => 'corredor', 'paso_ms' => 550, 'pausa_ms' => 750];
        }
        if (in_array($id, [16, 183, 187], true)) {
            return ['nombre' => 'saltarín', 'paso_ms' => 850, 'pausa_ms' => 1250];
        }
        return ['nombre' => 'paseante', 'paso_ms' => 1250, 'pausa_ms' => 1900];
    }
    public static function create(array $pokemon, array $trainer, float $now, bool $running): array
    {
        $candidates = [];
        for ($dy = -3; $dy <= 3; $dy++) {
            for ($dx = -3; $dx <= 3; $dx++) {
                $distance = abs($dx) + abs($dy);
                $point = [$trainer[0] + $dx, $trainer[1] + $dy];
                if ($distance < 2 || $distance > 3 || !ExplorationMap::walkable(...$point)) {
                    continue;
                }
                try {
                    $path = ExplorationMap::path($trainer, $point);
                } catch (\DomainException) {
                    continue;
                }
                if (count($path) <= 7) {
                    $candidates[] = $point;
                }
            }
        }
        $grid = ExplorationMap::grid();
        $grass = array_values(array_filter($candidates, fn($p) => $grid[$p[1]][$p[0]] === 5));
        $candidates = $grass ?: $candidates;
        $origin = $candidates ? $candidates[random_int(0, count($candidates) - 1)] : $trainer;
        $hiding = $grid[$origin[1]][$origin[0]] === 5;
        $profile = self::profile($pokemon['id']);
        $route = [$origin];
        $current = $origin;
        // A short wandering loop always retraces walkable cells, never cuts water or trees.
        for ($i = 0; $i < 4; $i++) {
            $options = [];
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $next = [$current[0] + $dx, $current[1] + $dy];
                if (
                    ExplorationMap::walkable(...$next) &&
                    abs($next[0] - $origin[0]) + abs($next[1] - $origin[1]) <= 2
                ) {
                    $options[] = $next;
                }
            }
            if (!$options) {
                break;
            }
            $current = $options[random_int(0, count($options) - 1)];
            $route[] = $current;
        }
        $route = array_merge($route, array_reverse(array_slice($route, 0, -1)));
        $frames = [
            [
                'x' => $route[0][0],
                'y' => $route[0][1],
                'duracion_ms' => $hiding ? 3200 : $profile['pausa_ms'],
            ],
        ];
        foreach (array_slice($route, 1) as $point) {
            $frames[] = ['x' => $point[0], 'y' => $point[1], 'duracion_ms' => $profile['paso_ms']];
        }
        return [
            'id' => bin2hex(random_bytes(12)),
            'pokemon' => $pokemon,
            'ruta' => $frames,
            'inicio' => $now,
            'vence' => (int) $now + 180,
            'perfil' => $profile['nombre'],
            'escondite' => $hiding ? $origin : null,
            'modo' => 'libre',
            'probabilidad' => ExpeditionRules::captureChance(
                $pokemon['poder_base'],
                $running,
                $pokemon['rareza_nivel'] ?? 1,
            ),
        ];
    }
    public static function position(array $sighting, float $now): array
    {
        $route = $sighting['ruta'];
        $duration = array_sum(array_column($route, 'duracion_ms'));
        $elapsed = max(0, ($now - $sighting['inicio']) * 1000);
        $loop = $sighting['repetir'] ?? true;
        $phase = $loop
            ? fmod($elapsed, max(1, $duration))
            : min($elapsed, max(0, $duration - 0.001));
        $previous = $loop ? end($route) : $route[0];
        foreach ($route as $frame) {
            if ($phase < $frame['duracion_ms']) {
                $t = $phase / $frame['duracion_ms'];
                return [
                    $previous['x'] + ($frame['x'] - $previous['x']) * $t,
                    $previous['y'] + ($frame['y'] - $previous['y']) * $t,
                ];
            }
            $phase -= $frame['duracion_ms'];
            $previous = $frame;
        }
        return [$route[0]['x'], $route[0]['y']];
    }
    public static function near(
        array $sighting,
        array $trainer,
        float $now,
        float $radius = 2.25,
    ): bool {
        [$x, $y] = self::position($sighting, $now);
        return hypot($trainer[0] - $x, $trainer[1] - $y) <= $radius;
    }
    public static function flee(array $sighting, array $trainer, float $now): array
    {
        $origin = self::position($sighting, $now);
        [$x, $y] = array_map('intval', array_map('round', $origin));
        $route = [['x' => $origin[0], 'y' => $origin[1], 'duracion_ms' => 150]];
        for ($i = 0; $i < 5; $i++) {
            $choices = [];
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                if (ExplorationMap::walkable($x + $dx, $y + $dy)) {
                    $choices[] = [$x + $dx, $y + $dy];
                }
            }
            usort(
                $choices,
                fn($a, $b) => hypot($b[0] - $trainer[0], $b[1] - $trainer[1]) <=>
                    hypot($a[0] - $trainer[0], $a[1] - $trainer[1]),
            );
            if (!$choices) {
                break;
            }
            [$x, $y] = $choices[0];
            $route[] = ['x' => $x, 'y' => $y, 'duracion_ms' => 250];
        }
        // A fleeing route holds at its last point instead of visibly looping back.
        $route[] = ['x' => $x, 'y' => $y, 'duracion_ms' => 5000];
        $sighting['ruta'] = $route;
        $sighting['inicio'] = $now;
        $sighting['repetir'] = false;
        $sighting['modo'] = 'huyendo';
        $sighting['perfil'] = 'corredor';
        $sighting['vence'] = (int) $now + 4;
        return $sighting;
    }
    public static function encounter(array $sighting, array $trainer, float $now): array
    {
        if ($sighting['vence'] < $now || $sighting['modo'] === 'huyendo') {
            throw new \DomainException('Ese Pokémon se ha alejado. Sigue explorando.');
        }
        if (!self::near($sighting, $trainer, $now)) {
            throw new \DomainException(
                'Acércate un poco más al Pokémon, caminando y sin hacer ruido.',
            );
        }
        return [
            'id' => bin2hex(random_bytes(12)),
            'pokemon' => $sighting['pokemon'],
            'probabilidad' => $sighting['probabilidad'],
            'intentos' => 0,
            'inicio' => $now,
            'vence' => $now + 60,
            'max_intentos' => 3,
        ];
    }
}
