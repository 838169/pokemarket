<?php
declare(strict_types=1);
namespace TrainerService;

final class MarketCatalog
{
    public const STARTERS = [
        1,
        4,
        7,
        25,
        133,
        152,
        155,
        158,
        252,
        255,
        258,
        387,
        390,
        393,
        495,
        498,
        501,
        650,
        653,
        656,
        722,
        725,
        728,
        810,
        813,
        816,
        906,
        909,
        912,
    ];
    public const CATEGORIES = [
        'iniciales' => [
            'nombre' => 'Primeros compañeros',
            'descripcion' => 'Una buena primera elección.',
            'desde' => 300,
        ],
        'basicos' => [
            'nombre' => 'Exploradores',
            'descripcion' => 'Compañeros para empezar tu aventura.',
            'desde' => 200,
        ],
        'avanzados' => [
            'nombre' => 'Especialistas',
            'descripcion' => 'Estadísticas y habilidades para combinar.',
            'desde' => 900,
        ],
        'elite' => [
            'nombre' => 'Élite',
            'descripcion' => 'Gran potencial para tus próximos equipos.',
            'desde' => 2200,
        ],
        'legendarios' => [
            'nombre' => 'Legendarios y singulares',
            'descripcion' => 'Especies excepcionales para una gran meta.',
            'desde' => 5500,
        ],
    ];
    public static function all(): array
    {
        static $items;
        if ($items !== null) {
            return $items;
        }
        $rows = self::species();
        $items = [];
        foreach ($rows as $row) {
            $bst = array_sum($row['estadisticas']);
            $starter = in_array($row['id'], self::STARTERS, true);
            $category =
                $row['legendario'] || $row['mitico']
                    ? 'legendarios'
                    : ($starter
                        ? 'iniciales'
                        : ($bst < 400
                            ? 'basicos'
                            : ($bst < 520
                                ? 'avanzados'
                                : 'elite')));
            $base = self::CATEGORIES[$category]['desde'];
            $statPremium =
                (int) (ceil(
                    max(
                        0,
                        $bst -
                            ($category === 'basicos'
                                ? 180
                                : ($category === 'avanzados'
                                    ? 400
                                    : ($category === 'elite'
                                        ? 520
                                        : ($category === 'legendarios'
                                            ? 500
                                            : 300)))),
                    ) / 10,
                ) * ($category === 'legendarios' ? 80 : ($category === 'elite' ? 50 : 20)));
            $row = AbilityRules::decorate($row);
            $abilityPremium = !empty($row['habilidad_activa']['implementada'])
                ? ($starter
                    ? 25
                    : 40)
                : 0;
            $row['poder_base'] = $bst;
            $row['debilidades'] = BattleRules::weaknesses($row['tipos']);
            $row['nivel_compra'] = PokemonProgression::START_LEVEL;
            $row['ataques_iniciales'] = array_slice(
                PokemonProgression::available($row['id'], PokemonProgression::START_LEVEL),
                -4,
            );
            $row['categoria'] = $category;
            $row['precio'] = $base + $statPremium + $abilityPremium;
            $row['precio_desglose'] = [
                'categoria' => $base,
                'estadisticas' => $statPremium,
                'habilidades' => $abilityPremium,
            ];
            $row['calidad_animacion'] = $row['sprite_animado_hd'] ? 'alta' : 'estandar';
            $statNames = [
                'hp' => 'salud',
                'ataque' => 'ataque',
                'defensa' => 'defensa',
                'ataque_especial' => 'ataque especial',
                'defensa_especial' => 'defensa especial',
                'velocidad' => 'velocidad',
            ];
            $sorted = $row['estadisticas'];
            arsort($sorted);
            $strengths = [];
            foreach (array_slice($sorted, 0, 2, true) as $name => $value) {
                $strengths[] = $statNames[$name] . ' (' . $value . ')';
            }
            $row['descripcion'] =
                'Sus estadísticas más altas son ' .
                implode(' y ', $strengths) .
                '. Compara sus tipos y su potencial con los de tu equipo antes de elegir.';
            $items[$row['id']] = $row;
        }
        return $items;
    }
    private static function species(): array
    {
        // CLI rules tests use the same snapshot without starting HTTP servers.
        if (PHP_SAPI === 'cli') {
            require_once dirname(__DIR__, 2) . '/pokemon-service/bootstrap.php';
            return \PokemonService\Services\GameCatalog::species();
        }

        $env = require dirname(__DIR__, 3) . '/config/environment.php';
        $base = rtrim(($env['POKEMON_SERVICE_URL'] ?? '') ?: 'http://localhost:8001/pokemon-api/v1', '/');
        $context = stream_context_create(['http' => ['timeout' => 3, 'follow_location' => 0]]);
        $json = @file_get_contents($base . '/game-catalog', false, $context);
        $payload = $json === false ? null : json_decode($json, true);
        if (empty($payload['success']) || !is_array($payload['pokemon'] ?? null) || !$payload['pokemon']) {
            throw new PokemonCatalogUnavailable();
        }
        return $payload['pokemon'];
    }
    public static function get(int $id): array
    {
        $all = self::all();
        if (!isset($all[$id])) {
            throw new \DomainException('Este Pokémon no está disponible en la tienda.');
        }
        return $all[$id];
    }
}
