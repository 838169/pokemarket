<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/pokemon-service/bootstrap.php';
use PokemonService\Services\SpriteCatalog;
$inventory = json_decode(
    file_get_contents(dirname(__DIR__) . '/backend/pokemon-service/config/sprite_catalog.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
foreach (range(1, 1025) as $id) {
    if (!SpriteCatalog::source($id) || !str_ends_with(SpriteCatalog::source($id), "/$id.webp")) {
        throw new RuntimeException("Falta animación HD de $id");
    }
    if (
        !SpriteCatalog::source($id, true) ||
        !str_contains(SpriteCatalog::source($id, true), "/back/$id.webp")
    ) {
        throw new RuntimeException("Falta espalda HD de $id");
    }
}
if (count($inventory['providers']) !== 6 || $inventory['missing'] !== []) {
    throw new RuntimeException('Inventario incompleto');
}
if (
    !str_contains(SpriteCatalog::source(21), 'brilliant-diamond-shining-pearl') ||
    !str_contains(SpriteCatalog::source(48), 'scarlet-violet')
) {
    throw new RuntimeException('Regresión de Spearow o Venonat');
}
echo "OK: 1025 especies con fuente frontal y posterior HD, seis proveedores y cobertura de Spearow/Venonat.\n";
