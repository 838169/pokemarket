<?php

declare(strict_types=1);

namespace PokemonService\Services;

final class PokemonMapper
{
    public function __construct(private readonly SpriteResolver $spriteResolver) {}

    public function map(array $pokemon, ?array $species = null): array
    {
        $types = array_map(
            static fn(array $entry): string => $entry['type']['name'],
            $pokemon['types'] ?? [],
        );

        $abilities = array_map(
            static fn(array $entry): array => [
                'nombre' => ucfirst(str_replace('-', ' ', $entry['ability']['name'])),
                'oculta' => (bool) $entry['is_hidden'],
            ],
            $pokemon['abilities'] ?? [],
        );

        $stats = [];

        foreach ($pokemon['stats'] ?? [] as $entry) {
            $name = $entry['stat']['name'] ?? null;

            if (in_array($name, ['hp', 'attack', 'defense', 'speed'], true)) {
                $stats[$name] = (int) ($entry['base_stat'] ?? 0);
            }
        }

        $battleMoves = array_map(
            static fn(array $move): array => [
                'nombre' => ucfirst(str_replace('-', ' ', (string) ($move['nombre'] ?? ''))),
                'tipo' => (string) ($move['tipo'] ?? ''),
                'potencia' => (int) ($move['potencia'] ?? 0),
            ],
            array_slice($pokemon['battle_moves'] ?? [], 0, 4),
        );

        return array_merge(
            [
                'id' => (int) $pokemon['id'],
                'nombre' => ucfirst($pokemon['name']),
                'tipos' => $types,
                'estadisticas' => [
                    'hp' => $stats['hp'] ?? 0,
                    'ataque' => $stats['attack'] ?? 0,
                    'defensa' => $stats['defense'] ?? 0,
                    'velocidad' => $stats['speed'] ?? 0,
                ],
                'movimientos' => $battleMoves,
                'altura' => ((float) ($pokemon['height'] ?? 0)) / 10,
                'peso' => ((float) ($pokemon['weight'] ?? 0)) / 10,
                'habilidades' => $abilities,
                'descripcion' => $this->spanishDescription($species),
            ],
            $this->spriteResolver->resolve($pokemon),
        );
    }

    private function spanishDescription(?array $species): ?string
    {
        foreach ($species['flavor_text_entries'] ?? [] as $entry) {
            if (($entry['language']['name'] ?? null) === 'es') {
                return str_replace(["\n", "\f"], ' ', $entry['flavor_text']);
            }
        }

        return null;
    }
}
