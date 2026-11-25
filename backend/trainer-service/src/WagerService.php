<?php
declare(strict_types=1);
namespace TrainerService;
use PDO;
use DomainException;

/** Persistent escrow and optimistic versioning protect balances across sessions/tabs. */
final class WagerService
{
    public function __construct(private PDO $db) {}
    private function query(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
        return $q;
    }
    private function atomic(callable $action): mixed
    {
        $this->db->beginTransaction();
        try {
            $r = $action();
            $this->db->commit();
            return $r;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    public function offers(int $uid): array
    {
        (new GameEconomy($this->db))->ensure($uid);
        return $this->atomic(function () use ($uid) {
            $this->query('SELECT usuario_id FROM carteras_usuario WHERE usuario_id=? FOR UPDATE', [$uid]);
            $rows = $this->query(
                'SELECT * FROM ofertas_apuesta WHERE usuario_id=? AND vence>? ORDER BY cantidad',
                [$uid, time()],
            )->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                $this->query('DELETE FROM ofertas_apuesta WHERE usuario_id=?', [$uid]);
                $amounts = [
                    100,
                    [400, 500, 600][random_int(0, 2)],
                    [1000, 1250, 1500][random_int(0, 2)],
                    [2000, 3000, 5000][random_int(0, 2)],
                ];
                $expires = time() + 600;
                foreach ($amounts as $amount) {
                    $id = bin2hex(random_bytes(12));
                    $this->query(
                        'INSERT INTO ofertas_apuesta(id,usuario_id,cantidad,vence) VALUES(?,?,?,?)',
                        [$id, $uid, $amount, $expires],
                    );
                    $rows[] = ['id' => $id, 'cantidad' => $amount, 'vence' => $expires];
                }
            }
            return array_map(
                fn($r) => [
                    'id' => $r['id'],
                    'cantidad' => (int) $r['cantidad'],
                    'vence' => (int) $r['vence'],
                    'dificultad' => AdventureRules::difficulty((int) $r['cantidad'])['nombre'],
                    'retorno' => (int) $r['cantidad'] * 2,
                ],
                $rows,
            );
        });
    }
    public function offer(int $uid, string $id): array
    {
        $r = $this->query('SELECT * FROM ofertas_apuesta WHERE id=? AND usuario_id=? AND vence>?', [
            $id,
            $uid,
            time(),
        ])->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            throw new DomainException(
                'Esta oferta venció. Actualiza las ofertas antes de apostar.',
            );
        }
        return $r;
    }
    public function active(int $uid): ?array
    {
        $json = $this->query(
            "SELECT partida FROM rondas_apuesta WHERE usuario_id=? AND estado='en_curso' ORDER BY fecha DESC LIMIT 1",
            [$uid],
        )->fetchColumn();
        return $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : null;
    }
    public function find(int $uid, string $id): ?array
    {
        $json = $this->query('SELECT partida FROM rondas_apuesta WHERE usuario_id=? AND id=?', [
            $uid,
            $id,
        ])->fetchColumn();
        return $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : null;
    }
    public function reserve(int $uid, string $offerId, array &$battle): void
    {
        $this->atomic(function () use ($uid, $offerId, &$battle) {
            $balance = (int) $this->query(
                'SELECT monedas FROM carteras_usuario WHERE usuario_id=? FOR UPDATE',
                [$uid],
            )->fetchColumn();
            if ($this->active($uid)) {
                throw new DomainException('Continúa o termina tu apuesta pendiente.');
            }
            $offer = $this->offer($uid, $offerId);
            $stake = (int) $offer['cantidad'];
            if ($balance < $stake) {
                throw new DomainException('No tienes monedas suficientes para esta apuesta.');
            }
            $battle['apuesta'] = [
                'cantidad' => $stake,
                'retorno' => $stake * 2,
                'dificultad' => AdventureRules::difficulty($stake)['nombre'],
            ];
            $battle['version_apuesta'] = 1;
            $this->query('UPDATE carteras_usuario SET monedas=monedas-? WHERE usuario_id=?', [
                $stake,
                $uid,
            ]);
            $this->query(
                'INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)',
                [
                    $uid,
                    'apuesta-' . $battle['id'],
                    'Entrada al duelo de apuestas',
                    -$stake,
                    $balance - $stake,
                ],
            );
            $this->query(
                'INSERT INTO rondas_apuesta(id,usuario_id,cantidad,partida) VALUES(?,?,?,?)',
                [$battle['id'], $uid, $stake, json_encode($battle, JSON_THROW_ON_ERROR)],
            );
        });
    }
    public function save(int $uid, array &$battle): void
    {
        $this->atomic(function () use ($uid, &$battle) {
            $balance = (int) $this->query(
                'SELECT monedas FROM carteras_usuario WHERE usuario_id=? FOR UPDATE',
                [$uid],
            )->fetchColumn();
            $row = $this->query(
                'SELECT * FROM rondas_apuesta WHERE usuario_id=? AND id=? FOR UPDATE',
                [$uid, $battle['id']],
            )->fetch(PDO::FETCH_ASSOC);
            if (
                !$row ||
                $row['estado'] !== 'en_curso' ||
                (int) $row['version'] !== ($battle['version_apuesta'] ?? 0)
            ) {
                throw new DomainException(
                    'La batalla cambió en otra pestaña. Recarga para continuar.',
                );
            }
            (new ProgressionService($this->db))->award($uid, $battle);
            if ($battle['estado'] !== 'en_curso') {
                $stake = (int) $row['cantidad'];
                $reward = AdventureRules::payout($stake, $battle['resultado']);
                $battle['recompensa_monedas'] = $reward;
                $battle['balance_apuesta'] = $reward - $stake;
                $this->query('UPDATE carteras_usuario SET monedas=monedas+? WHERE usuario_id=?', [
                    $reward,
                    $uid,
                ]);
                $this->query(
                    'INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)',
                    [
                        $uid,
                        'cierre-apuesta-' . $battle['id'],
                        $reward
                            ? 'Apuesta ganada: devolución y premio'
                            : 'Apuesta perdida: entrada consumida',
                        $reward,
                        $balance + $reward,
                    ],
                );
                foreach ($battle['equipo_jugador']['pokemon'] as $p) {
                    $this->query(
                        'UPDATE cuidado_pokemon SET energia=GREATEST(0,energia-12),vinculo=LEAST(100,vinculo+?) WHERE usuario_id=? AND pokemon_id=?',
                        [$reward ? 2 : 1, $uid, $p['id']],
                    );
                }
            }
            $battle['version_apuesta']++;
            $this->query(
                'UPDATE rondas_apuesta SET estado=?,version=?,partida=? WHERE id=? AND usuario_id=?',
                [
                    $battle['estado'],
                    $battle['version_apuesta'],
                    json_encode($battle, JSON_THROW_ON_ERROR),
                    $battle['id'],
                    $uid,
                ],
            );
        });
    }
}
