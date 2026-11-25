<?php

declare(strict_types=1);

namespace PokemonService\Services;

final class CacheService
{
    public function __construct(private readonly string $cachePath, private readonly int $ttl)
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0775, true);
        }
    }

    public function get(string $key): ?array
    {
        $file = $this->fileFor($key);

        if (!is_file($file) || time() - filemtime($file) >= $this->ttl) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    public function put(string $key, array $data): void
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        $file = $this->fileFor($key);
        $temporaryFile = $file . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($temporaryFile, $encoded, LOCK_EX) !== false) {
            rename($temporaryFile, $file);
        }
    }

    private function fileFor(string $key): string
    {
        return $this->cachePath . '/' . hash('sha256', $key) . '.json';
    }
}
