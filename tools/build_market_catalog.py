"""Build the local market index from official PokeAPI CSV exports (no user data)."""
import csv, json
from pathlib import Path
root = Path(__file__).resolve().parent.parent
data = root / 'tools/economy-data'
def rows(name):
    with (data / (name + '.csv')).open(encoding='utf-8') as f:
        return list(csv.DictReader(f))
species = {int(r['id']): r for r in rows('pokemon_species')}
names = {int(r['id']): r['identifier'].replace('-', ' ').capitalize() for r in rows('abilities')}
for r in rows('ability_names'):
    if r['local_language_id'] == '7': names[int(r['ability_id'])] = r['name']
types = {int(r['id']): r['identifier'] for r in rows('types')}
stats, abilities, ptypes = {}, {}, {}
for r in rows('pokemon_stats'):
    stats.setdefault(int(r['pokemon_id']), {})[int(r['stat_id'])] = int(r['base_stat'])
for r in rows('pokemon_abilities'):
    abilities.setdefault(int(r['pokemon_id']), []).append({'nombre':names[int(r['ability_id'])], 'oculta':r['is_hidden']=='1'})
for r in rows('pokemon_types'):
    ptypes.setdefault(int(r['pokemon_id']), []).append(types[int(r['type_id'])])
result = []
move_names = {}
for r in rows('move_names'):
    if r['local_language_id'] == '7': move_names[int(r['move_id'])] = r['name']
move_data = {}
for r in rows('moves'):
    if r['power'] and 30 <= int(r['power']) <= 100 and r['damage_class_id'] in ['2','3'] and (not r['accuracy'] or int(r['accuracy']) >= 90):
        mid=int(r['id'])
        move_data[mid]={'nombre':r['identifier'],'nombre_mostrado':move_names.get(mid,r['identifier']), 'tipo':types[int(r['type_id'])], 'potencia':int(r['power']), 'clase':'especial' if r['damage_class_id']=='3' else 'fisico'}
learnsets = {}
with (data/'pokemon_moves.csv').open(encoding='utf-8') as f:
    for r in csv.DictReader(f):
        pid,mid,version=int(r['pokemon_id']),int(r['move_id']),int(r['version_group_id'])
        if pid > 1025 or mid not in move_data: continue
        previous=learnsets.get(pid)
        if not previous or version > previous[0]: learnsets[pid]=(version,{mid})
        elif version == previous[0]: previous[1].add(mid)
for r in rows('pokemon'):
    pid = int(r['id'])
    if r['is_default'] != '1' or pid not in species: continue
    sp, st = species[pid], stats[pid]
    compatible=[move_data[mid] for mid in learnsets.get(pid,(0,set()))[1]]
    compatible.sort(key=lambda m:(-(1000 if m['tipo'] in ptypes[pid] else 0)-m['potencia'],m['nombre']))
    chosen=[]
    for move in compatible:
        if move['tipo'] not in [m['tipo'] for m in chosen]: chosen.append(move)
        if len(chosen)==4: break
    if len(chosen)<4:
        for move in compatible:
            if move not in chosen: chosen.append(move)
            if len(chosen)==4: break
    if not chosen: chosen=[{'nombre':'struggle','nombre_mostrado':'Forcejeo','tipo':'normal','potencia':50,'clase':'fisico'}]
    result.append({'id':pid,'nombre':sp['identifier'].capitalize(), 'tipos':ptypes[pid], 'movimientos':chosen,
        'estadisticas':dict(zip(['hp','ataque','defensa','ataque_especial','defensa_especial','velocidad'],[st[i] for i in range(1,7)])),
        'habilidades':abilities.get(pid,[]),'altura':int(r['height'])/10,'peso':int(r['weight'])/10,
        'legendario':sp['is_legendary']=='1','mitico':sp['is_mythical']=='1',
        'evolucion_previa':bool(sp['evolves_from_species_id'])})
target = root/'backend/pokemon-service/config/market_catalog.json'
target.write_text(json.dumps(result,ensure_ascii=False,separators=(',',':')),encoding='utf-8')
print(f'{len(result)} Pokémon indexados con seis estadísticas, tipos, rareza y habilidades.')
