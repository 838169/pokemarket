"""Compile ability identifiers and move contact/priority from official PokeAPI CSVs."""
import csv
import json
from pathlib import Path

root = Path(__file__).resolve().parent.parent
data = root / 'tools/economy-data'
def rows(name):
    with (data / f'{name}.csv').open(encoding='utf-8-sig') as stream:
        return list(csv.DictReader(stream))

abilities = {r['id']: r['identifier'] for r in rows('abilities')}
names = {r['name']: abilities[r['ability_id']] for r in rows('ability_names') if r['local_language_id'] == '7'}
# PokeAPI move flag 1 = contact; physical damage alone does not imply contact.
contact = {r['move_id'] for r in rows('move_flag_map') if r['move_flag_id'] == '1'}
moves = {r['identifier']: {'contacto': r['id'] in contact, 'prioridad': int(r['priority'])} for r in rows('moves')}
target = root / 'backend/trainer-service/config/ability_catalog.json'
target.write_text(json.dumps({'habilidades': names, 'movimientos': moves}, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
print(f'{len(names)} habilidades y {len(moves)} movimientos compilados.')
