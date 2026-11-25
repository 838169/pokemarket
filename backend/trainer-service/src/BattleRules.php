<?php
declare(strict_types=1);
namespace TrainerService;

/** Pure battle rules: independent of HTTP, sessions and the database. */
final class BattleRules
{
    public const TYPES = [
        'normal',
        'fire',
        'water',
        'electric',
        'grass',
        'ice',
        'fighting',
        'poison',
        'ground',
        'flying',
        'psychic',
        'bug',
        'rock',
        'ghost',
        'dragon',
        'dark',
        'steel',
        'fairy',
    ];

    /** Defensive weaknesses from types only; ability modifiers are applied separately during combat. */
    public static function weaknesses(array $defenderTypes): array
    {
        if ($defenderTypes === [] || array_diff($defenderTypes, self::TYPES) !== []) {
            throw new \InvalidArgumentException('Tipos de Pokémon no válidos.');
        }
        $weaknesses = [];
        foreach (self::TYPES as $type) {
            $multiplier = self::effectiveness($type, array_unique($defenderTypes));
            if ($multiplier > 1) {
                $weaknesses[] = ['tipo' => $type, 'multiplicador' => $multiplier];
            }
        }
        usort(
            $weaknesses,
            static fn(array $a, array $b): int => $b['multiplicador'] <=> $a['multiplicador'],
        );
        return $weaknesses;
    }

    public static function damage(
        array $atacante,
        array $defensor,
        array $movimiento,
        float $efectividad,
        int $roll = 100,
        array $battle = [],
    ): int {
        if ($efectividad === 0.0) {
            return 0;
        }

        $special = ($movimiento['clase'] ?? 'fisico') === 'especial';
        $ataque = max(
            1,
            (int) ($atacante['estadisticas'][$special ? 'ataque_especial' : 'ataque'] ??
                $atacante['estadisticas']['ataque']),
        );
        $defensa = max(
            1,
            (int) ($defensor['estadisticas'][$special ? 'defensa_especial' : 'defensa'] ??
                $defensor['estadisticas']['defensa']),
        );
        $ability = AbilityRules::active($atacante);
        $ataque = max(
            1,
            (int) floor(
                $ataque *
                    AbilityRules::stage(
                        $atacante['cambios'][$special ? 'ataque_especial' : 'ataque'] ?? 0,
                    ),
            ),
        );
        $defensa = max(
            1,
            (int) floor(
                $defensa *
                    AbilityRules::stage(
                        $defensor['cambios'][$special ? 'defensa_especial' : 'defensa'] ?? 0,
                    ),
            ),
        );
        $boostType =
            ['blaze' => 'fire', 'overgrow' => 'grass', 'torrent' => 'water', 'swarm' => 'bug'][
                $ability
            ] ?? null;
        if (
            $boostType === $movimiento['tipo'] &&
            ($atacante['hp_actual'] ?? PHP_INT_MAX) * 3 <= ($atacante['hp_maximo'] ?? 0)
        ) {
            $ataque = max(1, (int) floor($ataque * 1.5));
        }
        if ($special && $ability === 'solar-power' && ($battle['clima'] ?? '') === 'sol') {
            $ataque = max(1, (int) floor($ataque * 1.5));
        }
        $weather = 1.0;
        if (in_array($movimiento['tipo'], ['fire', 'water'], true)) {
            if (($battle['clima'] ?? '') === 'sol') {
                $weather = $movimiento['tipo'] === 'fire' ? 1.5 : 0.5;
            }
            if (($battle['clima'] ?? '') === 'lluvia') {
                $weather = $movimiento['tipo'] === 'water' ? 1.5 : 0.5;
            }
        }
        $level = max(1, min(100, (int) ($atacante['nivel'] ?? 50)));
        $base = floor(
            ((intdiv(2 * $level, 5) + 2) * (int) $movimiento['potencia'] * $ataque) /
                $defensa /
                50 +
                2,
        );
        $stab = in_array($movimiento['tipo'], $atacante['tipos'], true) ? 1.5 : 1.0;

        return max(1, (int) floor($base * $weather * $stab * $efectividad * ($roll / 100)));
    }

    public static function effectiveness(string $moveType, array $defenderTypes): float
    {
        $strong = [
            'normal' => [],
            'fire' => ['grass', 'ice', 'bug', 'steel'],
            'water' => ['fire', 'ground', 'rock'],
            'electric' => ['water', 'flying'],
            'grass' => ['water', 'ground', 'rock'],
            'ice' => ['grass', 'ground', 'flying', 'dragon'],
            'fighting' => ['normal', 'ice', 'rock', 'dark', 'steel'],
            'poison' => ['grass', 'fairy'],
            'ground' => ['fire', 'electric', 'poison', 'rock', 'steel'],
            'flying' => ['grass', 'fighting', 'bug'],
            'psychic' => ['fighting', 'poison'],
            'bug' => ['grass', 'psychic', 'dark'],
            'rock' => ['fire', 'ice', 'flying', 'bug'],
            'ghost' => ['psychic', 'ghost'],
            'dragon' => ['dragon'],
            'dark' => ['psychic', 'ghost'],
            'steel' => ['ice', 'rock', 'fairy'],
            'fairy' => ['fighting', 'dragon', 'dark'],
        ];
        $weak = [
            'normal' => ['rock', 'steel'],
            'fire' => ['fire', 'water', 'rock', 'dragon'],
            'water' => ['water', 'grass', 'dragon'],
            'electric' => ['electric', 'grass', 'dragon'],
            'grass' => ['fire', 'grass', 'poison', 'flying', 'bug', 'dragon', 'steel'],
            'ice' => ['fire', 'water', 'ice', 'steel'],
            'fighting' => ['poison', 'flying', 'psychic', 'bug', 'fairy'],
            'poison' => ['poison', 'ground', 'rock', 'ghost'],
            'ground' => ['grass', 'bug'],
            'flying' => ['electric', 'rock', 'steel'],
            'psychic' => ['psychic', 'steel'],
            'bug' => ['fire', 'fighting', 'poison', 'flying', 'ghost', 'steel', 'fairy'],
            'rock' => ['fighting', 'ground', 'steel'],
            'ghost' => ['dark'],
            'dragon' => ['steel'],
            'dark' => ['fighting', 'dark', 'fairy'],
            'steel' => ['fire', 'water', 'electric', 'steel'],
            'fairy' => ['fire', 'poison', 'steel'],
        ];
        $immune = [
            'normal' => ['ghost'],
            'fighting' => ['ghost'],
            'poison' => ['steel'],
            'ground' => ['flying'],
            'electric' => ['ground'],
            'psychic' => ['dark'],
            'ghost' => ['normal'],
            'dragon' => ['fairy'],
        ];
        $multiplier = 1.0;

        foreach ($defenderTypes as $type) {
            if (in_array($type, $immune[$moveType] ?? [], true)) {
                return 0.0;
            }
            if (in_array($type, $strong[$moveType] ?? [], true)) {
                $multiplier *= 2;
            } elseif (in_array($type, $weak[$moveType] ?? [], true)) {
                $multiplier *= 0.5;
            }
        }

        return $multiplier;
    }

    public static function nextIndex(array $pokemon, int $current): ?int
    {
        $total = count($pokemon);
        for ($step = 1; $step < $total; $step++) {
            $index = ($current + $step) % $total;
            if ($pokemon[$index]['hp_actual'] > 0) {
                return $index;
            }
        }
        return null;
    }
}
