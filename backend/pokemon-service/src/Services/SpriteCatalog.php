<?php
declare(strict_types=1);

namespace PokemonService\Services;

final class SpriteCatalog
{
    private static ?array $sources = null;

    public static function version(): string
    {
        $file = dirname(__DIR__, 2) . '/config/sprite_catalog.json';
        return is_file($file) ? substr(hash_file('sha256', $file), 0, 12) : 'none';
    }

    public static function source(int $id, bool $back = false): ?string
    {
        if (self::$sources === null) {
            $file = dirname(__DIR__, 2) . '/config/sprite_catalog.json';
            self::$sources = is_file($file)
                ? json_decode(file_get_contents($file), true)
                : ['pokemon' => [], 'back' => []];
        }
        $path = self::$sources[$back ? 'back' : 'pokemon'][$id] ?? null;
        $view = $back ? 'back/' : '';
        return $path === null
            ? null
            : "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/versions/{$path}/{$view}{$id}.webp";
    }

    public static function order(array $entries): array
    {
        $id = static function (array $entry): int {
            return (int) basename(rtrim($entry['url'], '/'));
        };
        $rank = static function (int $id): int {
            $source = self::source($id);
            return $source === null ? 2 : (str_contains($source, '/champions/') ? 0 : 1);
        };
        usort($entries, static function (array $a, array $b) use ($id, $rank): int {
            $aId = $id($a);
            $bId = $id($b);
            return $rank($aId) <=> $rank($bId) ?: $aId <=> $bId;
        });
        return $entries;
    }
}
