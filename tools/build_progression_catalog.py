"""Compile official growth curves and level-up attacks; no runtime network calls."""
import csv
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
data = root / 'tools/economy-data'

def rows(name):
    with (data / f'{name}.csv').open(encoding='utf-8') as source:
        yield from csv.DictReader(source)

types = {int(r['id']): r['identifier'] for r in rows('types')}
names = {int(r['move_id']): r['name'] for r in rows('move_names') if r['local_language_id'] == '7'}
# Use the latest traditional main-series learnset in which each species appears.
# Legends and Champions have different learning/combat systems.
versions = {int(r['id']): r for r in rows('version_groups')
            if r['identifier'] not in ['legends-arceus', 'legends-za', 'mega-dimension', 'champions']}
moves = {}
for r in rows('moves'):
    if r['power'] and 1 <= int(r['power']) <= 100 and r['damage_class_id'] in ['2', '3']:
        # Only direct attacks supported by this game's current battle engine.
        if r['accuracy'] and int(r['accuracy']) < 90:
            continue
        mid = int(r['id'])
        moves[mid] = {'nombre': r['identifier'], 'nombre_mostrado': names.get(mid, r['identifier']),
                      'tipo': types[int(r['type_id'])], 'potencia': int(r['power']),
                      'clase': 'especial' if r['damage_class_id'] == '3' else 'fisico'}

learnsets = {}
for r in rows('pokemon_moves'):
    pid, version = int(r['pokemon_id']), int(r['version_group_id'])
    if pid > 1025 or version not in versions or r['pokemon_move_method_id'] != '1':
        continue
    rank = int(versions[version]['order'])
    previous = learnsets.get(pid)
    if not previous or rank > previous['rank']:
        previous = learnsets[pid] = {'rank': rank, 'version': versions[version]['identifier'], 'moves': {}}
    if rank != previous['rank'] or int(r['move_id']) not in moves:
        continue
    mid, level = int(r['move_id']), max(1, int(r['level']))
    previous['moves'][mid] = min(level, previous['moves'].get(mid, 100))

species = {int(r['id']): r for r in rows('pokemon_species')}
result = {}
for r in rows('pokemon'):
    pid = int(r['id'])
    if r['is_default'] != '1' or pid not in species:
        continue
    learned = learnsets.get(pid, {'version': '', 'moves': {}})
    entries = [{**moves[mid], 'nivel': level} for mid, level in learned['moves'].items()]
    entries.sort(key=lambda m: (m['nivel'], m['nombre']))
    result[pid] = {'crecimiento': int(species[pid]['growth_rate_id']),
                   'experiencia_base': int(r['base_experience'] or 0),
                   'version': learned['version'], 'aprendizaje': entries}

growth = {}
for r in rows('experience'):
    growth.setdefault(r['growth_rate_id'], {})[r['level']] = int(r['experience'])
target = root / 'backend/trainer-service/config/progression_catalog.json'
target.write_text(json.dumps({'curvas': growth, 'pokemon': result}, ensure_ascii=False,
                             separators=(',', ':')), encoding='utf-8')
print(f'{len(result)} especies; curvas oficiales y ataques por nivel compilados.')
