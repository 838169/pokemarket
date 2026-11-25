<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
$root = dirname(__DIR__);
// Keep the established appearance where available, then fill gaps with other 512px renders.
$providers = [
    'generation-ix/champions',
    'generation-viii/sword-shield',
    'generation-ix/scarlet-violet',
    'generation-viii/brilliant-diamond-shining-pearl',
    'generation-viii/legends-arceus',
    'generation-ix/legends-za',
];
function githubInventory(string $endpoint): array
{
    $handle = curl_init('https://api.github.com/repos/PokeAPI/sprites/' . $endpoint);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_ENCODING => '',
        CURLOPT_PROXY => '',
        CURLOPT_USERAGENT => 'PokeMarket-SpriteCatalog',
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
    ]);
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === false || $status !== 200) {
        throw new RuntimeException("Inventario no disponible: $status $error");
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
$catalog = [];
$back = [];
$parents = [];
$counts = [];
foreach ($providers as $path) {
    [$generation, $game] = explode('/', $path);
    $parents[$generation] ??= githubInventory('contents/sprites/pokemon/versions/' . $generation);
    $folder = array_values(
        array_filter($parents[$generation], fn($entry) => $entry['name'] === $game),
    );
    if (!$folder) {
        throw new RuntimeException('Falta el proveedor ' . $path);
    }
    // Git Trees does not have the Contents endpoint's 1,000-file cap.
    $tree = githubInventory('git/trees/' . $folder[0]['sha'] . '?recursive=1');
    if ($tree['truncated'] ?? true) {
        throw new RuntimeException('Inventario incompleto: ' . $path);
    }
    $count = 0;
    foreach ($tree['tree'] as $entry) {
        if (preg_match('/^(\d+)\.webp$/D', $entry['path'], $m) && (int) $m[1] <= 1025) {
            $catalog[$m[1]] ??= $path;
            $count++;
        }
        if (preg_match('#^back/(\d+)\.webp$#D', $entry['path'], $m) && (int) $m[1] <= 1025) {
            $back[$m[1]] ??= $path;
        }
    }
    $counts[$path] = $count;
    echo "$path: $count especies base.\n";
}
if (count($catalog) < 800) {
    throw new RuntimeException('Inventario insuficiente; se conserva el anterior.');
}
ksort($catalog, SORT_NUMERIC);
ksort($back, SORT_NUMERIC);
$missing = array_values(array_diff(range(1, 1025), array_map('intval', array_keys($catalog))));
$payload = [
    'updated_at' => gmdate(DATE_ATOM),
    'repository' => 'https://github.com/PokeAPI/sprites',
    'providers' => $counts,
    'missing' => $missing,
    'pokemon' => (object) $catalog,
    'back' => (object) $back,
];
$file = $root . '/backend/pokemon-service/config/sprite_catalog.json';
$temp = $file . '.tmp';
if (
    file_put_contents(
        $temp,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    ) === false ||
    !rename($temp, $file)
) {
    throw new RuntimeException('No se pudo guardar el inventario.');
}
echo 'Cobertura HD: ' . count($catalog) . '/1025. Pendientes: ' . implode(', ', $missing) . "\n";
