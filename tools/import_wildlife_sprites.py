"""Import unmodified overworld sprite sheets and their source credits from SpriteCollab."""
import concurrent.futures
import json
import re
import struct
import urllib.request
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BASE = 'https://raw.githubusercontent.com/PMDCollab/SpriteCollab/master'
pool_source = (ROOT / 'backend/trainer-service/src/ExplorationMap.php').read_text(encoding='utf-8-sig')
POOL = [int(value) for value in re.findall(r'\d+', re.search(r'public const POOL = \[(.*?)\];', pool_source, re.S).group(1))]
TARGET = ROOT / 'frontend/assets/img/wildlife'
TARGET.mkdir(parents=True, exist_ok=True)

def fetch(url):
    with urllib.request.urlopen(url, timeout=45) as response:
        return response.read()

def species(pid):
    prefix = f'{BASE}/sprite/{pid:04d}'
    xml = fetch(f'{prefix}/AnimData.xml')
    credits = fetch(f'{prefix}/credits.txt')
    folder = TARGET / str(pid)
    folder.mkdir(exist_ok=True)
    (folder / 'credits.txt').write_bytes(credits)
    (folder / 'AnimData.xml').write_bytes(xml)
    result = {}
    anims = {a.findtext('Name'): a for a in ET.fromstring(xml).find('Anims')}
    for name in ['Walk', 'Idle']:
        anim = anims[name]
        source_name = anim.findtext('CopyOf') or name
        anim = anims[source_name]
        width, height = int(anim.findtext('FrameWidth')), int(anim.findtext('FrameHeight'))
        durations = [int(d.text) for d in anim.find('Durations')]
        png = fetch(f'{prefix}/{source_name}-Anim.png')
        if png[:8] != b'\x89PNG\r\n\x1a\n':
            raise ValueError(f'Invalid sprite: {pid}/{name}')
        sheet_width, sheet_height = struct.unpack('>II', png[16:24])
        if sheet_width % width or sheet_height % height or sheet_width // width < len(durations):
            raise ValueError(f'Frame dimensions mismatch: {pid}/{name}')
        (folder / f'{name}.png').write_bytes(png)
        result[name.lower()] = {'src': f'assets/img/wildlife/{pid}/{name}.png', 'width': width, 'height': height, 'durations': durations, 'directions': sheet_height // height}
    return pid, result

policy = fetch(f'{BASE}/README.md')
(TARGET / 'SOURCE-POLICY.md').write_bytes(policy)
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    catalog = dict(pool.map(species, POOL))
(ROOT / 'frontend/assets/js/wildlife-sprites.js').write_text('/* Unmodified SpriteCollab sheets; see assets/img/wildlife/CREDITS.md. */\nwindow.PokeWildlifeSprites = ' + json.dumps(catalog, ensure_ascii=False, separators=(',', ':')) + ';\n', encoding='utf-8')
lines = ['# Créditos de animaciones de campo', '', 'Fuente: [PMDCollab / SpriteCollab](https://github.com/PMDCollab/SpriteCollab). Se conservan sin modificar las hojas originales Walk/Idle, sus metadatos y los registros de crédito por especie.', '', 'La política del repositorio permite reutilizar contribuciones con atribución para proyectos no comerciales ([CC BY-NC 4.0](https://creativecommons.org/licenses/by-nc/4.0/)). Los sprites oficiales identificados como CHUNSOFT conservan sus derechos originales; no se presentan como recursos CC0 ni como obras propias. Consulta SOURCE-POLICY.md y cada credits.txt para la procedencia exacta.', '', '| Especie | Créditos originales |', '| --- | --- |']
for pid in POOL:
    records = (TARGET / str(pid) / 'credits.txt').read_text(encoding='utf-8').splitlines()
    authors = sorted({r.split('\t')[1] for r in records if len(r.split('\t')) > 1})
    lines.append(f'| {pid:03d} | ' + ', '.join(authors) + f' ([registro]({pid}/credits.txt)) |')
(TARGET / 'CREDITS.md').write_text('\n'.join(lines) + '\n', encoding='utf-8')
print(f'{len(catalog)} especies: Walk/Idle, metadatos, créditos y política de la fuente.')
