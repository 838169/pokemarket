<?php

declare(strict_types=1);

namespace PokemonService\Services;

final class SpriteResolver
{
    public function resolve(array $pokemon): array
    {
        $sprites = $pokemon['sprites'] ?? [];
        $officialArtwork =
            $sprites['other']['official-artwork']['front_default'] ??
            ($sprites['front_default'] ?? null);
        $fallback = $sprites['front_default'] ?? $officialArtwork;
        $id = (int) ($pokemon['id'] ?? 0);

        return [
            'sprite_animado_hd' => SpriteCatalog::source($id),
            'sprite_animado_hd_espalda' => SpriteCatalog::source($id, true),
            'calidad_animacion' => SpriteCatalog::source($id) !== null ? 'alta' : 'estandar',
            'imagen_home' => $sprites['other']['home']['front_default'] ?? null,
            'imagen_oficial' => $officialArtwork,
            'sprite_animado' =>
                $id > 0
                    ? "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/showdown/{$id}.gif"
                    : null,
            'imagen_fallback' => $fallback,
        ];
    }
}
