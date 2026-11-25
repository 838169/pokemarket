import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';

for (const file of [
    'README.md',
    'docs/arquitectura.md',
    'docs/calidad.md',
    'docs/pruebas.md',
    'docs/presentacion.md',
    'database/schema.sql',
    'database/README.md',
    'backend/pokemon-service/public/index.php',
    'backend/trainer-service/public/index.php',
    '.env.example',
    '.htaccess',
    'package-lock.json',
])
    assert.ok(existsSync(file), `Falta ${file}`);
for (const file of [
    'api',
    'pokemarket_v2.sql',
    'config/database.php',
    'frontend/assets/js/catalogo.js',
    'frontend/assets/js/coleccion.js',
    'frontend/assets/css/coleccion.css',
    'backend/pokemon-service/prueba_curl.php',
])
    assert.ok(!existsSync(file), `Archivo obsoleto o privado en carpeta pública: ${file}`);
for (const page of [
    'login.php',
    'registro.php',
    'catalogo.php',
    'coleccion.php',
    'equipos.php',
    'batalla.php',
    'explorar.php',
]) {
    const html = readFileSync(`frontend/pages/${page}`, 'utf8');
    for (const match of html.matchAll(/(?:src|href)="(assets\/[^"?]+)(?:\?[^\"]*)?"/g))
        assert.ok(existsSync(`frontend/${match[1]}`), `${page}: recurso ausente ${match[1]}`);
}
const schema = readFileSync('database/schema.sql', 'utf8');
assert.ok(
    !/\bINSERT\s+(?:IGNORE\s+)?INTO\b/i.test(schema),
    'El esquema no debe contener datos de personas'
);
const index = JSON.parse(
    readFileSync('backend/pokemon-service/config/market_catalog.json', 'utf8')
);
assert.equal(index.length, 1025);
assert.equal(new Set(index.map((p) => p.id)).size, index.length);
for (const file of readdirSync('tests'))
    assert.ok(
        !/(?:preview|fixture.*\.php$)/.test(file),
        `Página de prueba temporal pendiente: ${file}`
    );
assert.ok(
    readFileSync('tools/migrate_economy.php', 'utf8').includes('--reset-progression'),
    'El reinicio debe ser explícito'
);
console.log(
    'OK: estructura documentada, recursos existentes, índice íntegro y archivos privados fuera de la entrega.'
);
