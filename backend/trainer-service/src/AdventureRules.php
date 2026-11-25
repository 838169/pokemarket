<?php
declare(strict_types=1);
namespace TrainerService;

final class AdventureRules
{
    public const SCENES = [
        'pradera' => [
            'nombre' => 'Pradera del alba',
            'precio' => 500,
            'imagen' => 'pradera.png',
            'fallback' => 'battle-meadow.jpg',
            'musica' => 'home-meadow.mp3',
        ],
        'bosque' => [
            'nombre' => 'Bosque susurrante',
            'precio' => 1500,
            'imagen' => 'bosque.png',
            'fallback' => 'battle-forest.jpg',
            'musica' => 'home-forest.mp3',
        ],
        'laguna' => [
            'nombre' => 'Laguna de cristal',
            'precio' => 2500,
            'imagen' => 'laguna.png',
            'fallback' => 'pokemon-home.png',
            'musica' => 'home-lagoon.ogg',
        ],
    ];
    public static function difficulty(int $stake): array
    {
        return $stake < 500
            ? ['nombre' => 'Aprendiz', 'factor' => 0.9]
            : ($stake < 1000
                ? ['nombre' => 'Entrenador', 'factor' => 1.05]
                : ($stake < 2000
                    ? ['nombre' => 'Veterano', 'factor' => 1.2]
                    : ['nombre' => 'Maestro', 'factor' => 1.4]));
    }
    public static function payout(int $stake, string $result): int
    {
        return $result === 'victoria' ? $stake * 2 : 0;
    }
    public static function artwork(string $file, string $fallback): string
    {
        $path = 'assets/img/adventure/' . $file;
        return is_file(dirname(__DIR__, 3) . '/frontend/' . $path) ? $path : 'assets/img/' . $fallback;
    }
}
