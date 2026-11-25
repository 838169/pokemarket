<?php
declare(strict_types=1);

require dirname(__DIR__) . '/backend/pokemon-service/bootstrap.php';

use PokemonService\Controllers\PokemonController;
use PokemonService\Services\CacheService;
use PokemonService\Services\PokeApiClient;
use PokemonService\Services\PokemonMapper;
use PokemonService\Services\SpriteResolver;
use PokemonService\Services\SpriteCatalog;

$directory = sys_get_temp_dir() . '/pokemarket-detail-test-' . bin2hex(random_bytes(6));
$cache = new CacheService($directory, 900);
$base = 'https://example.invalid/api/v2';
$raw = [
    'id' => 7,
    'name' => 'squirtle',
    'height' => 5,
    'weight' => 90,
    'moves' => [['move' => ['name' => 'water-gun', 'url' => 'https://example.invalid/move/1']]],
    'species' => ['url' => $base . '/pokemon-species/7/'],
];
$cache->put('upstream:' . $base . '/pokemon/7', $raw);
$cache->put('upstream:' . $base . '/pokemon-species/7', [
    'flavor_text_entries' => [
        ['language' => ['name' => 'es'], 'flavor_text' => 'Descripción de prueba.'],
    ],
]);
$battle = ['id' => 7, 'movimientos' => [['nombre' => 'Water gun', 'potencia' => 40]]];
$cache->put('detail:7', $battle);
try {
    $client = new PokeApiClient($base, 1, $cache);
    if ($client->getPokemonMany([$base . '/pokemon/7/']) !== [$raw]) {
        throw new RuntimeException('El lote debe reutilizar la respuesta guardada sin conexión.');
    }
    $controller = new PokemonController(
        $client,
        new PokemonMapper(new SpriteResolver()),
        $cache,
        [],
    );
    $progress = [];
    $view = $controller->detail('7', false, static function (int $percent) use (&$progress): void {
        $progress[] = $percent;
    })['pokemon'];
    if ($progress !== [0, 10, 55, 85, 100]) {
        throw new RuntimeException('El progreso debe corresponder a las etapas completadas.');
    }
    if (
        $view['nombre'] !== 'Squirtle' ||
        $view['descripcion'] !== 'Descripción de prueba.' ||
        $view['movimientos'] !== []
    ) {
        throw new RuntimeException('La ficha debe usar datos guardados sin consultar movimientos.');
    }
    if ($controller->detail('7')['pokemon']['movimientos'] !== $battle['movimientos']) {
        throw new RuntimeException('La ficha no debe sustituir los datos de combate.');
    }
    if ($controller->detail('7')['pokemon']['sprite_animado_hd'] !== SpriteCatalog::source(7)) {
        throw new RuntimeException('La caché antigua debe actualizar sus medios sin red.');
    }
    $entries = [
        ['url' => 'https://example.invalid/pokemon/999999/'],
        ['url' => 'https://example.invalid/pokemon/1/'],
        ['url' => 'https://example.invalid/pokemon/3/'],
    ];
    $ordered = SpriteCatalog::order($entries);
    if (
        array_column($ordered, 'url') !== [
            $entries[2]['url'],
            $entries[1]['url'],
            $entries[0]['url'],
        ]
    ) {
        throw new RuntimeException('Orden esperado: Champions, Sword/Shield, sin HD.');
    }
    echo "OK: detalle sin red, sin descarga de movimientos y cache de combate preservada\n";
} finally {
    foreach (glob($directory . '/*.json') as $file) {
        unlink($file);
    }
    rmdir($directory);
}
