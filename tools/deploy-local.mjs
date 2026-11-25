import { readdirSync, statSync, mkdirSync, cpSync, copyFileSync, writeFileSync, renameSync, rmSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash, randomUUID } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';

const root = fileURLToPath(new URL('..', import.meta.url));
const working = join(root, '.local-backups', 'operations');
const releases = join(working, 'releases');
const inputs = ['frontend', 'backend', 'config', 'database', 'docs', 'tests', 'tools',
    'README.md', 'package.json', 'package-lock.json', '.env.example', '.htaccess'];

function sourceFiles(path) {
    const info = statSync(path);
    if (!info.isDirectory()) return [[path, info.size, info.mtimeMs]];
    return readdirSync(path, { withFileTypes: true }).flatMap((entry) =>
        ['storage', '__pycache__'].includes(entry.name) || entry.isSymbolicLink()
            ? [] : sourceFiles(join(path, entry.name)),
    );
}

function snapshot() {
    const files = inputs.flatMap((input) => sourceFiles(join(root, input)));
    const version = createHash('sha256').update(JSON.stringify(files)).digest('hex');
    return { files, version };
}

function publish(candidate) {
    const name = randomUUID();
    const folder = join(releases, name);
    if (dirname(folder) !== releases) throw new Error('Ruta de versión fuera del directorio de despliegue.');
    let published = false;
    try {
        mkdirSync(folder, { recursive: true });
        for (const [source] of candidate.files) {
            const target = join(folder, relative(root, source));
            mkdirSync(dirname(target), { recursive: true });
            copyFileSync(source, target);
        }
        if (candidate.version !== snapshot().version) return;
        console.log('CI · Ejecutando pruebas de la versión candidata.');
        const test = spawnSync(process.execPath, ['tools/quality.mjs'], {
            cwd: folder, stdio: 'inherit', windowsHide: true, timeout: 600000,
        });
        try {
            cpSync(join(folder, 'reports'), join(working, 'reports'), { recursive: true });
        } catch (error) {
            if (error.code !== 'ENOENT') throw error;
        }
        if (test.status !== 0 || test.error) {
            console.log('CI FAIL · Candidata rechazada. Se conserva la versión activa.');
            return;
        }
        if (candidate.version !== snapshot().version) return;
        const pending = join(working, 'release.next');
        writeFileSync(pending, name, 'ascii');
        renameSync(pending, join(working, 'release.txt'));
        published = true;
        console.log('CI PASS · Versión lista para desplegar: ' + name);
    } finally {
        if (!published) rmSync(folder, { recursive: true, force: true });
    }
}

let lastAttempt;
let observed;
while (true) {
    try {
        const candidate = snapshot();
        if (candidate.version === observed && candidate.version !== lastAttempt) {
            lastAttempt = candidate.version;
            publish(candidate);
        }
        observed = candidate.version;
    } catch (error) {
        console.error('CI ERROR · ' + error.message);
        observed = undefined;
    }
    await sleep(3000);
}
