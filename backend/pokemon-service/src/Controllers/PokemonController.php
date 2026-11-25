<?php

declare(strict_types=1);

namespace PokemonService\Controllers;

use PokemonService\Exceptions\HttpException;
use PokemonService\Services\CacheService;
use PokemonService\Services\PokeApiClient;
use PokemonService\Services\SpriteCatalog;
use PokemonService\Services\PokemonMapper;

final class PokemonController
{
    public function __construct(
        private readonly PokeApiClient $pokeApi,
        private readonly PokemonMapper $mapper,
        private readonly CacheService $cache,
        private readonly array $config,
    ) {}

    public function catalog(array $query): array
    {
        $limit = $this->positiveInteger(
            $query['limit'] ?? $this->config['default_catalog_limit'],
            'limit',
        );
        $offset = $this->nonNegativeInteger($query['offset'] ?? 0, 'offset');

        if ($limit > $this->config['max_catalog_limit']) {
            throw new HttpException(
                400,
                'El parámetro limit no puede superar ' . $this->config['max_catalog_limit'],
            );
        }

        $order = $query['order'] ?? 'quality';
        if (!in_array($order, ['quality', 'pokedex'], true)) {
            throw new HttpException(400, 'Orden de catálogo no válido');
        }
        $version = SpriteCatalog::version();
        $cacheKey = "catalog:v3:{$version}:{$order}:{$limit}:{$offset}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $page =
            $order === 'quality'
                ? $this->pokeApi->getPokemonPageByQuality($limit, $offset)
                : $this->pokeApi->getPokemonPage($limit, $offset);

        if (!isset($page['results']) || !is_array($page['results'])) {
            throw new HttpException(502, 'PokéAPI devolvió un catálogo inválido');
        }

        $details = $this->pokeApi->getPokemonMany(array_column($page['results'], 'url'));
        $pokemon = array_map(fn(array $item): array => $this->mapper->map($item), $details);

        foreach ($pokemon as $item) {
            if (
                is_array($item['estadisticas'] ?? null) &&
                is_array($item['tipos'] ?? null) &&
                is_array($item['movimientos'] ?? null) &&
                $item['movimientos'] !== []
            ) {
                $this->cache->put('detail:' . $item['id'], $item);
            }
        }

        $response = [
            'success' => true,
            'total' => (int) ($page['count'] ?? 0),
            'order' => $order,
            'pokemon' => $pokemon,
        ];

        $this->cache->put($cacheKey, $response);

        return $response;
    }

    public function search(array $query): array
    {
        $term = strtolower(trim((string) ($query['q'] ?? '')));

        if ($term === '' || !preg_match('/^[a-z0-9-]+$/', $term)) {
            throw new HttpException(
                400,
                'El parámetro q debe contener un nombre o número de Pokémon válido',
            );
        }

        return [
            'success' => true,
            'pokemon' => $this->pokemonByIdentifier($term),
        ];
    }

    public function detail(
        string $pokemonId,
        bool $withBattleMoves = true,
        ?callable $progress = null,
    ): array {
        $id = $this->positiveInteger($pokemonId, 'pokemonId');
        $progress && $progress(0);

        return [
            'success' => true,
            'pokemon' => $this->pokemonByIdentifier($id, $withBattleMoves, $progress),
        ];
    }

    public function batch(array $query): array
    {
        $rawIds = trim((string) ($query['ids'] ?? ''));

        if ($rawIds === '') {
            throw new HttpException(400, 'El parámetro ids es obligatorio');
        }

        $parts = explode(',', $rawIds);

        if (count($parts) > $this->config['max_batch_ids']) {
            throw new HttpException(
                400,
                'La consulta por lote admite máximo ' . $this->config['max_batch_ids'] . ' IDs',
            );
        }

        $ids = [];

        foreach ($parts as $part) {
            $id = $this->positiveInteger(trim($part), 'ids');

            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            throw new HttpException(400, 'El parámetro ids no contiene IDs válidos');
        }

        $pokemon = array_map(fn(int $id): array => $this->pokemonByIdentifier($id), $ids);

        return [
            'success' => true,
            'total' => count($pokemon),
            'pokemon' => $pokemon,
        ];
    }

    private function pokemonByIdentifier(
        string|int $identifier,
        bool $withBattleMoves = true,
        ?callable $progress = null,
    ): array {
        $prefix = $withBattleMoves ? 'detail:' : 'detail-view:';
        $cacheKey = $prefix . strtolower((string) $identifier);
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $progress && $progress(100);
            $cached['sprite_animado_hd'] = SpriteCatalog::source((int) $cached['id']);
            $cached['sprite_animado_hd_espalda'] = SpriteCatalog::source((int) $cached['id'], true);
            $cached['calidad_animacion'] = $cached['sprite_animado_hd'] ? 'alta' : 'estandar';
            return $cached;
        }

        $progress && $progress(10);
        $pokemon = $this->pokeApi->getPokemon($identifier, $withBattleMoves);
        $progress && $progress(55);
        $species = null;

        if (isset($pokemon['species']['url']) && is_string($pokemon['species']['url'])) {
            try {
                $species = $this->pokeApi->getSpecies($pokemon['species']['url']);
            } catch (HttpException) {
                // An optional description must not prevent viewing the Pokémon.
            }
        }

        $progress && $progress(85);
        $normalized = $this->mapper->map($pokemon, $species);
        $this->cache->put($cacheKey, $normalized);
        $this->cache->put($prefix . $normalized['id'], $normalized);
        $progress && $progress(100);

        return $normalized;
    }

    private function positiveInteger(mixed $value, string $parameter): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            throw new HttpException(400, "El parámetro {$parameter} debe ser un entero positivo");
        }

        return (int) $value;
    }

    private function nonNegativeInteger(mixed $value, string $parameter): int
    {
        if (!is_scalar($value) || !preg_match('/^[0-9]+$/', (string) $value)) {
            throw new HttpException(
                400,
                "El parámetro {$parameter} debe ser un entero igual o mayor que cero",
            );
        }

        return (int) $value;
    }
}
