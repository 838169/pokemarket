<?php

declare(strict_types=1);

namespace PokemonService\Services;

use PokemonService\Exceptions\HttpException;

final class PokeApiClient
{
    private array $moveCache = [];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly CacheService $cache,
    ) {}

    public function getPokemonPage(int $limit, int $offset): array
    {
        return $this->getJson($this->baseUrl . "/pokemon?limit={$limit}&offset={$offset}");
    }

    public function getPokemonPageByQuality(int $limit, int $offset): array
    {
        $index = $this->getPokemonPage(10000, 0);
        if (!isset($index['results']) || !is_array($index['results'])) {
            throw new HttpException(502, 'PokéAPI devolvió un catálogo inválido');
        }
        $index['results'] = array_slice(SpriteCatalog::order($index['results']), $offset, $limit);
        return $index;
    }

    public function getPokemon(string|int $identifier, bool $withBattleMoves = true): array
    {
        $pokemon = $this->getJson(
            $this->baseUrl . '/pokemon/' . rawurlencode((string) $identifier),
        );
        if (!$withBattleMoves) {
            return $pokemon;
        }
        $pokemon['battle_moves'] = $this->getBattleMoves(
            $pokemon['moves'] ?? [],
            array_map(
                static fn(array $entry): string => (string) ($entry['type']['name'] ?? ''),
                $pokemon['types'] ?? [],
            ),
        );

        return $pokemon;
    }

    public function getSpecies(string $url): array
    {
        return $this->getJson($url);
    }

    /** @param list<string> $urls */
    public function getPokemonMany(array $urls): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $index => $url) {
            $cached = $this->cache->get('upstream:' . rtrim($url, '/'));
            if ($cached !== null) {
                $results[$index] = $cached;
                continue;
            }
            $handle = $this->createHandle($url);
            curl_multi_add_handle($multi, $handle);
            $handles[$index] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $transferResults = [];
        while (($info = curl_multi_info_read($multi)) !== false) {
            $transferResults[spl_object_id($info['handle'])] = $info['result'];
        }

        $retry = [];

        try {
            foreach ($handles as $index => $handle) {
                $body = curl_multi_getcontent($handle);
                $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $error = curl_error($handle);

                $transferResult = $transferResults[spl_object_id($handle)] ?? CURLE_RECV_ERROR;

                if ($transferResult !== CURLE_OK) {
                    $retry[$index] = $urls[$index];
                    continue;
                }

                if ($body === false || $statusCode !== 200) {
                    throw $this->requestException($statusCode, $error);
                }

                $decoded = json_decode($body, true);

                if (!is_array($decoded)) {
                    throw new HttpException(502, 'PokéAPI devolvió una respuesta inválida');
                }

                $results[$index] = $decoded;
                $this->cache->put('upstream:' . rtrim($urls[$index], '/'), $decoded);
            }
        } finally {
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
        }
        // Retry interrupted transfers individually; successful responses are already cached.
        foreach ($retry as $index => $url) {
            $results[$index] = $this->getJson($url);
        }
        ksort($results);

        return array_values($results);
    }

    private function getJson(string $url): array
    {
        $cacheKey = 'upstream:' . rtrim($url, '/');
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $handle = $this->createHandle($url);
        $body = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $statusCode !== 200) {
            throw $this->requestException($statusCode, $error);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new HttpException(502, 'PokéAPI devolvió una respuesta inválida');
        }

        $this->cache->put($cacheKey, $decoded);
        return $decoded;
    }

    private function createHandle(string $url): \CurlHandle
    {
        $handle = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            // Negotiate compression and decode responses before parsing JSON.
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROXY => '',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        return $handle;
    }

    private function getBattleMoves(mixed $moves, array $pokemonTypes): array
    {
        if (!is_array($moves)) {
            return [];
        }

        $entries = [];
        $urls = [];
        $seenUrls = [];

        foreach ($moves as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $url = $entry['move']['url'] ?? null;

            if (!is_string($url) || $url === '') {
                continue;
            }

            $entries[] = [$url, (string) ($entry['move']['name'] ?? '')];

            if (!isset($seenUrls[$url])) {
                $seenUrls[$url] = true;
                $urls[] = $url;
            }
        }

        if ($urls === []) {
            return [];
        }

        $missingUrls = [];

        foreach ($urls as $url) {
            if (array_key_exists($url, $this->moveCache)) {
                continue;
            }

            $cached = $this->cache->get('move:' . $url);

            if ($cached !== null) {
                $this->moveCache[$url] = $cached;
                continue;
            }

            $missingUrls[] = $url;
        }

        if ($missingUrls !== []) {
            try {
                $details = $this->getPokemonMany($missingUrls);
            } catch (HttpException) {
                return [];
            }

            foreach ($missingUrls as $index => $url) {
                $move = $details[$index] ?? [];
                $normalized = [
                    'nombre' => (string) ($move['name'] ?? ''),
                    'tipo' => (string) ($move['type']['name'] ?? ''),
                    'potencia' =>
                        isset($move['power']) && is_numeric($move['power'])
                            ? (int) $move['power']
                            : null,
                ];
                $this->moveCache[$url] = $normalized;
                $this->cache->put('move:' . $url, $normalized);
            }
        }

        $typeMoves = [];
        $otherMoves = [];
        $seen = [];

        foreach ($entries as [$url, $fallbackName]) {
            $move = $this->moveCache[$url] ?? [];

            $type = $move['tipo'] ?? ($move['type']['name'] ?? null);
            $power = $move['potencia'] ?? ($move['power'] ?? null);

            if (!is_string($type) || !is_numeric($power) || (int) $power <= 0) {
                continue;
            }

            $name = (string) ($move['nombre'] ?? ($move['name'] ?? $fallbackName));

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $normalized = [
                'nombre' => $name,
                'tipo' => $type,
                'potencia' => (int) $power,
            ];

            if (in_array($type, $pokemonTypes, true)) {
                $typeMoves[] = $normalized;
            } else {
                $otherMoves[] = $normalized;
            }
        }

        return array_slice(array_merge($typeMoves, $otherMoves), 0, 4);
    }

    private function requestException(int $statusCode, string $curlError): HttpException
    {
        if ($statusCode === 404) {
            return new HttpException(404, 'Pokémon no encontrado');
        }

        $message =
            $curlError !== ''
                ? 'No fue posible conectar con PokéAPI'
                : 'PokéAPI no está disponible temporalmente';

        return new HttpException(502, $message);
    }
}
