<?php
declare(strict_types=1);
namespace PokemonService\Services;

final class GameCatalog
{
    public static function species(): array
    {
        $rows = json_decode(
            file_get_contents(dirname(__DIR__, 2) . '/config/market_catalog.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        foreach ($rows as &$pokemon) {
            $pokemon['sprite_animado_hd'] = SpriteCatalog::source($pokemon['id']);
            $pokemon['sprite_animado_hd_espalda'] = SpriteCatalog::source($pokemon['id'], true);
        }
        unset($pokemon);
        return $rows;
    }

    public static function sprite(int $id, string $view): ?string
    {
        if ($view === 'animated' || $view === 'back') {
            return SpriteCatalog::source($id, $view === 'back');
        }
        $folder = $view === 'artwork' ? 'official-artwork' : 'home';
        return "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/{$folder}/{$id}.png";
    }
}
