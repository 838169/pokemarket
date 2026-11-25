import { existsSync, readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve, join, extname, relative } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('..', import.meta.url)));
process.chdir(root);
const integration = process.argv.includes('--integration');
const php =
    process.env.PHP_BINARY ||
    (process.platform === 'win32' && existsSync('C:/xampp/php/php.exe')
        ? 'C:/xampp/php/php.exe'
        : 'php');
const excluded = new Set([
    '.git',
    'node_modules',
    '.local-backups',
    'storage',
    'reports',
    'economy-data',
    '__pycache__',
]);
function files(folder) {
    return readdirSync(folder, { withFileTypes: true }).flatMap((entry) => {
        if (excluded.has(entry.name)) return [];
        const p = join(folder, entry.name);
        return entry.isDirectory() ? files(p) : [p];
    });
}
const results = [];
function run(name, command, args) {
    const started = performance.now();
    const result = spawnSync(command, args, {
        cwd: root,
        encoding: 'utf8',
        timeout: integration ? 180000 : 60000,
        windowsHide: true,
    });
    const ok = result.status === 0 && !result.error;
    results.push({
        name,
        ok,
        duration_ms: Math.round(performance.now() - started),
        exit_code: result.status,
        details: (result.error?.message || result.stdout + result.stderr).trim().slice(0, 8000),
    });
    console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`);
    if (!ok) console.log(results.at(-1).details);
    return ok;
}
const source = files(root);
const phpFiles = source.filter((p) => extname(p) === '.php');
const jsFiles = source.filter((p) => ['.js', '.cjs', '.mjs'].includes(extname(p)));
const syntax = phpFiles.every((p) => run(`PHP: ${relative(root, p)}`, php, ['-l', p]));
const jsSyntax = jsFiles.every((p) =>
    run(`JS: ${relative(root, p)}`, process.execPath, ['--check', p])
);
if (syntax && jsSyntax) {
    for (const test of [
        'media-ui.cjs',
        'catalog-interaction.cjs',
        'market-purchase.cjs',
        'purchase-audio.cjs',
        'purchase-media.cjs',
        'exploration-world.cjs',
        'exploration-render.cjs',
        'wildlife-motion.cjs',
        'exploration-input.cjs',
        'store.cjs',
        'store-reset.cjs',
        'home-music.cjs',
        'battle-audio.cjs',
        'pokemon-cries.cjs',
        'game-session.cjs',
        'home-layout.cjs',
        'home-loading.cjs',
        'welcome-media.cjs',
        'detail-progress.cjs',
        'detail-motion.cjs',
        'battle-practice.cjs',
        'battle-tutorial.cjs',
        'battle-lobby.cjs',
        'api-security.cjs',
    ])
        run(`Prueba: ${test}`, process.execPath, [`tests/${test}`]);
    for (const test of [
        'market-rules.php',
        'sprite-inventory.php',
        'adventure-rules.php',
        'exploration-rules.php',
        'expedition-rules.php',
        'wildlife-rules.php',
        'battle-rules.php',
        'initiative-rules.php',
        'ability-rules.php',
        'progression-rules.php',
        'detail-view.php',
        'security-rules.php',
    ])
        run(`Prueba: ${test}`, php, [`tests/${test}`]);
    run('Estructura, recursos y datos públicos', process.execPath, ['tools/check-project.mjs']);
    if (integration) {
        run('API + MySQL: economía y seguridad', php, ['tests/economy-integration.php']);
        run('MySQL + API: exploración y capturas', php, ['tests/exploration-integration.php']);
        run('MySQL: salas de dúos y turnos compartidos', php, ['tests/duo-integration.php']);
        run('MySQL + API: cuatro sesiones en dúo', php, ['tests/duo-api-integration.php']);
        run('MySQL: experiencia y aprendizaje', php, ['tests/progression-integration.php']);
        run('MySQL: habilidades y compras', php, ['tests/ability-integration.php']);
    }
}
const report = {
    generated_at: new Date().toISOString(),
    mode: integration ? 'integration' : 'offline',
    environment: { node: process.version, platform: process.platform },
    summary: {
        checks: results.length,
        passed: results.filter((r) => r.ok).length,
        failed: results.filter((r) => !r.ok).length,
    },
    results,
};
mkdirSync(join(root, 'reports'), { recursive: true });
writeFileSync(join(root, 'reports/quality-report.json'), JSON.stringify(report, null, 2) + '\n');
writeFileSync(
    join(root, 'reports/quality-report.md'),
    `# Resultado de calidad\n\nFecha UTC: ${report.generated_at}\n\nModo: ${report.mode}. Comprobaciones: ${report.summary.checks}. Correctas: ${report.summary.passed}. Fallidas: ${report.summary.failed}.\n\n| Comprobación | Estado | Tiempo (ms) |\n| --- | --- | --- |\n${results.map((r) => `| ${r.name.replaceAll('|', '/')} | ${r.ok ? 'PASS' : 'FAIL'} | ${r.duration_ms} |`).join('\n')}\n\nEste informe cuenta comprobaciones ejecutadas; no representa cobertura porcentual ni certificación ISO/OWASP.\n`
);
console.log(
    `\n${report.summary.passed}/${report.summary.checks} comprobaciones correctas. Informe: reports/quality-report.md`
);
process.exitCode = report.summary.failed ? 1 : 0;
