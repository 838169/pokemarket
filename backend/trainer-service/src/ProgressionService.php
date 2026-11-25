<?php
declare(strict_types=1);
namespace TrainerService;
use PDO;

final class ProgressionService
{
    public function __construct(private PDO $db) {}
    private function query(string $sql, array $args): \PDOStatement
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
        return $q;
    }
    public function profile(int $uid, int $pid, bool $lock = false): array
    {
        if (
            !$this->query('SELECT pokemon_id FROM coleccion_pokemon WHERE usuario_id=? AND pokemon_id=?', [
                $uid,
                $pid,
            ])->fetchColumn()
        ) {
            throw new \DomainException('Este Pokémon no pertenece a tu Hogar.');
        }
        $initial = PokemonProgression::profile(
            $pid,
            PokemonProgression::threshold($pid, PokemonProgression::START_LEVEL),
        );
        $this->query(
            'INSERT IGNORE INTO progreso_pokemon(usuario_id,pokemon_id,experiencia,movimientos) VALUES(?,?,?,?)',
            [
                $uid,
                $pid,
                $initial['experiencia'],
                json_encode(array_column($initial['movimientos'], 'nombre'), JSON_THROW_ON_ERROR),
            ],
        );
        $row = $this->query(
            'SELECT experiencia,movimientos FROM progreso_pokemon WHERE usuario_id=? AND pokemon_id=?' .
                ($lock ? ' FOR UPDATE' : ''),
            [$uid, $pid],
        )->fetch(PDO::FETCH_ASSOC);
        return PokemonProgression::profile(
            $pid,
            (int) $row['experiencia'],
            json_decode($row['movimientos'], true, 512, JSON_THROW_ON_ERROR),
        );
    }
    public function decorate(int $uid, array $pokemon): array
    {
        $p = $this->profile($uid, $pokemon['id']);
        $pokemon['progreso'] = $p;
        $pokemon['nivel'] = $p['nivel'];
        $pokemon['estadisticas_base'] = $pokemon['estadisticas'];
        $pokemon['estadisticas'] = PokemonProgression::stats($pokemon, $p['nivel']);
        $pokemon['movimientos'] = $p['movimientos'];
        return (new AbilityService($this->db))->decorate($uid, $pokemon);
    }
    public function equip(int $uid, int $pid, array $names): void
    {
        if (
            count($names) < 1 ||
            count($names) > 4 ||
            count(array_filter($names, 'is_string')) !== count($names) ||
            count(array_unique($names)) !== count($names)
        ) {
            throw new \DomainException('Elige entre uno y cuatro ataques distintos.');
        }
        $this->db->beginTransaction();
        try {
            $p = $this->profile($uid, $pid, true);
            if (array_diff($names, array_column($p['disponibles'], 'nombre')) !== []) {
                throw new \DomainException('Todavía no has aprendido alguno de esos ataques.');
            }
            $this->query(
                'UPDATE progreso_pokemon SET movimientos=? WHERE usuario_id=? AND pokemon_id=?',
                [json_encode(array_values($names), JSON_THROW_ON_ERROR), $uid, $pid],
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    /** Called inside the battle's existing transaction; ledger prevents duplicate EXP. */
    public function award(int $uid, array &$battle): void
    {
        foreach ($battle['eventos_experiencia'] ?? [] as $event) {
            foreach ($event['receptores'] as $recipient) {
                $pid = (int) $recipient['id'];
                $before = $this->profile($uid, $pid, true);
                $amount =
                    $before['nivel'] >= PokemonProgression::MAX_LEVEL
                        ? 0
                        : PokemonProgression::reward(
                            $event['id'],
                            $event['nivel'],
                            $before['nivel'],
                            $recipient['participo'],
                        );
                $insert = $this->query(
                    'INSERT IGNORE INTO experiencia_combate(usuario_id,batalla_id,rival_indice,pokemon_id,cantidad) VALUES(?,?,?,?,?)',
                    [$uid, $battle['id'], $event['indice'], $pid, $amount],
                );
                if (!$insert->rowCount()) {
                    continue;
                }
                $after = PokemonProgression::profile(
                    $pid,
                    $before['experiencia'] + $amount,
                    array_column($before['movimientos'], 'nombre'),
                );
                $new = array_values(
                    array_filter(
                        $after['disponibles'],
                        static fn(array $m): bool => $m['nivel'] > $before['nivel'],
                    ),
                );
                $equipped = array_column($after['movimientos'], 'nombre');
                foreach ($new as $move) {
                    if (count($equipped) < 4 && !in_array($move['nombre'], $equipped, true)) {
                        $equipped[] = $move['nombre'];
                    }
                }
                $this->query(
                    'UPDATE progreso_pokemon SET experiencia=?,movimientos=? WHERE usuario_id=? AND pokemon_id=?',
                    [
                        $after['experiencia'],
                        json_encode($equipped, JSON_THROW_ON_ERROR),
                        $uid,
                        $pid,
                    ],
                );
                $battle['progreso_ganado'][] = [
                    'pokemon_id' => $pid,
                    'nombre' => MarketCatalog::get($pid)['nombre'],
                    'experiencia' => $after['experiencia'] - $before['experiencia'],
                    'nivel_anterior' => $before['nivel'],
                    'nivel' => $after['nivel'],
                    'nuevos_movimientos' => $new,
                ];
                foreach ($battle['equipo_jugador']['pokemon'] as &$actor) {
                    if (
                        $actor['id'] !== $pid ||
                        !isset($actor['nivel']) ||
                        $after['nivel'] <= $before['nivel']
                    ) {
                        continue;
                    }
                    $updated = $this->decorate($uid, MarketCatalog::get($pid));
                    $oldMax = $actor['hp_maximo'];
                    $actor['nivel'] = $updated['nivel'];
                    $actor['estadisticas'] = $updated['estadisticas'];
                    foreach (
                        ['ataque', 'defensa', 'ataque_especial', 'defensa_especial']
                        as $stat
                    ) {
                        $actor['estadisticas'][$stat] = (int) round(
                            $actor['estadisticas'][$stat] *
                                (1 + ($actor['bonus_cuidado'] ?? 0) / 100),
                        );
                    }
                    $actor['hp_maximo'] = $actor['estadisticas']['hp'];
                    if ($actor['hp_actual'] > 0) {
                        $actor['hp_actual'] += $actor['hp_maximo'] - $oldMax;
                    }
                    foreach ($new as $move) {
                        if (
                            count($actor['movimientos']) < 4 &&
                            !in_array(
                                $move['nombre'],
                                array_column($actor['movimientos'], 'nombre'),
                                true,
                            )
                        ) {
                            $actor['movimientos'][] = $move;
                        }
                    }
                    $actor['movimientos_disponibles'] = $actor['movimientos'];
                }
                unset($actor);
            }
        }
    }
}
