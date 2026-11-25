<?php

declare(strict_types=1);

namespace TrainerService;

use PDO;

use DomainException;

final class GameEconomy
{
    public const REVISION = 1;

    public const GIFT = 1000;

    public const FOOD = [
        'baya' => [
            'nombre' => 'Cesta de bayas Aranja',
            'icono' => 'food-berries.svg',

            'precio' => 40,

            'energia' => 35,

            'vinculo' => 5,

            'descripcion' => 'Una merienda ligera para recuperar energía.',
        ],

        'curry' => [
            'nombre' => 'Curry casero',
            'icono' => 'food-curry.svg',
            'caliente' => true,

            'precio' => 90,

            'energia' => 75,

            'vinculo' => 10,

            'descripcion' => 'Una comida completa antes de volver a combatir.',
        ],
        'bellota' => [
            'nombre' => 'Pokéhabas de Alola',
            'icono' => 'food-beans.svg',
            'precio' => 25,
            'energia' => 20,
            'vinculo' => 3,
            'descripcion' => 'Pokéhabas de colores del Cayo Recreo.',
        ],
        'sopa' => [
            'nombre' => 'Sopa del campamento',
            'icono' => 'food-soup.svg',
            'caliente' => true,
            'precio' => 65,
            'energia' => 50,
            'vinculo' => 8,
            'descripcion' => 'Un cuenco caliente para recuperar fuerzas.',
        ],
        'galleta' => [
            'nombre' => 'Pokochos de bayas',
            'icono' => 'food-poffin.svg',
            'precio' => 50,
            'energia' => 30,
            'vinculo' => 9,
            'descripcion' => 'Panecillos de bayas al estilo de Sinnoh.',
        ],
        'puff' => [
            'nombre' => 'Pokélito de fresa',
            'icono' => 'food-puff.svg',
            'precio' => 80,
            'energia' => 45,
            'vinculo' => 14,
            'descripcion' => 'Un premio dulce para fortalecer el vínculo.',
        ],
        'pokecubo' => [
            'nombre' => 'Pokécubos de Hoenn',
            'icono' => 'food-pokecubes.svg',
            'precio' => 55,
            'energia' => 25,
            'vinculo' => 12,
            'descripcion' => 'Dulces de bayas preparados en cubos.',
        ],
    ];

    public function __construct(private PDO $db) {}

    private function run(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->db->prepare($sql);

        $q->execute($args);

        return $q;
    }

    public function ensure(int $uid): void
    {
        $this->run('INSERT IGNORE INTO carteras_usuario (usuario_id) VALUES (?)', [$uid]);
    }

    public function completeWelcome(int $uid): array
    {
        $this->ensure($uid);

        $this->transaction(function () use ($uid) {
            $balance = $this->lockWallet($uid);

            $done = $this->run('SELECT bienvenida FROM carteras_usuario WHERE usuario_id=?', [
                $uid,
            ])->fetchColumn();

            if (!(int) $done) {
                $this->movement(
                    $uid,
                    self::GIFT,
                    'bienvenida-v1',
                    'Regalo de bienvenida',
                    $balance,
                );

                $this->run('UPDATE carteras_usuario SET bienvenida=1 WHERE usuario_id=?', [$uid]);

                $this->run(
                    "INSERT INTO inventario_comida(usuario_id,comida,cantidad) VALUES (?,'baya',3) ON DUPLICATE KEY UPDATE cantidad=cantidad+3",
                    [$uid],
                );
            }
        });

        return $this->wallet($uid);
    }

    public function wallet(int $uid): array
    {
        $this->ensure($uid);

        $w = $this->run('SELECT monedas,revision,bienvenida,tarjetas_cambio_usuario FROM carteras_usuario WHERE usuario_id=?', [
            $uid,
        ])->fetch(PDO::FETCH_ASSOC);

        $owned = $this->run('SELECT pokemon_id FROM coleccion_pokemon WHERE usuario_id=?', [
            $uid,
        ])->fetchAll(PDO::FETCH_COLUMN);

        $food = array_fill_keys(array_keys(self::FOOD), 0);

        foreach (
            $this->run('SELECT comida,cantidad FROM inventario_comida WHERE usuario_id=?', [
                $uid,
            ])->fetchAll(PDO::FETCH_ASSOC)
            as $r
        ) {
            $food[$r['comida']] = (int) $r['cantidad'];
        }

        return [
            'monedas' => (int) $w['monedas'],
            'tarjetas_cambio_usuario' => (int) $w['tarjetas_cambio_usuario'],

            'regalo_inicial' => self::GIFT,

            'necesita_bienvenida' => !(bool) $w['bienvenida'],

            'necesita_inicial' => $owned === [],

            'pokemon_ids' => array_map('intval', $owned),

            'comida' => $food,

            'historial' => $this->run(
                'SELECT concepto,cantidad,saldo,fecha FROM movimientos_monedas WHERE usuario_id=? AND cantidad<>0 ORDER BY id DESC LIMIT 12',

                [$uid],
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function lockWallet(int $uid): int
    {
        return (int) $this->run('SELECT monedas FROM carteras_usuario WHERE usuario_id=? FOR UPDATE', [
            $uid,
        ])->fetchColumn();
    }

    private function movement(
        int $uid,

        int $amount,

        string $key,

        string $concept,

        int $balance,
    ): void {
        if ($balance + $amount < 0) {
            throw new DomainException(
                'No tienes suficientes monedas. Gana más terminando batallas.',
            );
        }

        $this->run(
            'INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)',

            [$uid, $key, $concept, $amount, $balance + $amount],
        );

        $this->run('UPDATE carteras_usuario SET monedas=monedas+? WHERE usuario_id=?', [$amount, $uid]);
    }

    private function transaction(callable $f): mixed
    {
        $this->db->beginTransaction();

        try {
            $r = $f();

            $this->db->commit();

            return $r;
        } catch (\Throwable $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    public function purchase(int $uid, int $pid, ?string $ability = null): array
    {
        $p = MarketCatalog::get($pid);
        if ($ability !== null) {
            $p['habilidad_activa'] = AbilityService::choice($p, $ability);
        }

        $this->ensure($uid);

        return $this->transaction(function () use ($uid, $pid, $p) {
            $balance = $this->lockWallet($uid);

            $owned = $this->run('SELECT pokemon_id FROM coleccion_pokemon WHERE usuario_id=?', [
                $uid,
            ])->fetchAll(PDO::FETCH_COLUMN);

            if (in_array($pid, array_map('intval', $owned), true)) {
                throw new DomainException('Este Pokémon ya vive en tu Hogar.');
            }

            $this->movement(
                $uid,

                -$p['precio'],

                'pokemon-' . $pid,

                'Compra de ' . $p['nombre'],

                $balance,
            );

            $this->run('INSERT INTO coleccion_pokemon(usuario_id,pokemon_id) VALUES(?,?)', [$uid, $pid]);
            if (!empty($p['habilidad_activa']['implementada'])) {
                $this->run(
                    'INSERT INTO habilidades_pokemon(usuario_id,pokemon_id,habilidad) VALUES(?,?,?)',
                    [$uid, $pid, $p['habilidad_activa']['id']],
                );
            }

            $this->run('INSERT INTO cuidado_pokemon(usuario_id,pokemon_id) VALUES(?,?)', [
                $uid,

                $pid,
            ]);

            if ($owned === []) {
                $this->run("INSERT INTO equipos(usuario_id,nombre) VALUES(?,'Mi primer equipo')", [
                    $uid,
                ]);

                $team = (int) $this->db->lastInsertId();

                $this->run(
                    'INSERT INTO miembros_equipo(equipo_id,posicion,pokemon_id) VALUES(?,1,?)',

                    [$team, $pid],
                );
            }

            return [
                'pokemon' => (new ProgressionService($this->db))->decorate($uid, $p),

                'monedas' => $balance - $p['precio'],

                'primer_companero' => $owned === [],
            ];
        });
    }

    public function buyFood(int $uid, string $food, int $qty): void
    {
        if (!isset(self::FOOD[$food]) || $qty < 1 || $qty > 20) {
            throw new DomainException('Elige una comida y entre 1 y 20 unidades.');
        }

        $this->ensure($uid);

        $this->transaction(function () use ($uid, $food, $qty) {
            $balance = $this->lockWallet($uid);

            $f = self::FOOD[$food];

            $this->movement(
                $uid,

                -$f['precio'] * $qty,

                'comida-' . bin2hex(random_bytes(12)),

                $qty . ' × ' . $f['nombre'],

                $balance,
            );

            $this->run(
                'INSERT INTO inventario_comida(usuario_id,comida,cantidad) VALUES(?,?,?) ON DUPLICATE KEY UPDATE cantidad=cantidad+VALUES(cantidad)',

                [$uid, $food, $qty],
            );
        });
    }

    public static function bonus(array $care): int
    {
        return ((int) $care['energia'] >= 60 ? 5 : 0) + min(5, intdiv((int) $care['vinculo'], 20));
    }

    public function trainerProfile(int $uid, array $include = []): array
    {
        $scores = [];
        foreach (
            $this->run(
                'SELECT u.id, u.usuario, c.pokemon_id, p.experiencia
            FROM usuarios u
            LEFT JOIN coleccion_pokemon c ON c.usuario_id = u.id
            LEFT JOIN progreso_pokemon p ON p.usuario_id = c.usuario_id AND p.pokemon_id = c.pokemon_id',
            )->fetchAll(PDO::FETCH_ASSOC)
            as $row
        ) {
            $id = (int) $row['id'];
            $scores[$id] ??= ['usuario' => $row['usuario'], 'especies' => 0, 'niveles_ganados' => 0, 'puntos' => 0];
            if ($row['pokemon_id'] === null) {
                continue;
            }
            $points = self::trainerPokemonPoints(
                (int) $row['pokemon_id'],
                (int) $row['experiencia'],
            );
            $scores[$id]['especies']++;
            $scores[$id]['niveles_ganados'] += intdiv($points - 10, 3);
            $scores[$id]['puntos'] += $points;
        }
        uksort(
            $scores,
            static fn($a, $b) => $scores[$b]['puntos'] <=> $scores[$a]['puntos'] ?: $a <=> $b,
        );
        $top = [];
        $profile = [];
        $requested = [];
        $position = 0;
        $rank = 0;
        $previous = null;
        foreach ($scores as $id => $score) {
            $position++;
            if ($score['puntos'] !== $previous) {
                $rank = $position;
            }
            $previous = $score['puntos'];
            $level = 1 + intdiv($score['puntos'], 100);
            $entry = $score + [
                'puesto' => $rank,
                'nivel' => $level,
                'rango' => match (true) {
                    $level >= 20 => 'Leyenda',
                    $level >= 12 => 'Élite',
                    $level >= 7 => 'Veterano',
                    $level >= 3 => 'Explorador',
                    default => 'Casual',
                },
            ];
            if ($position <= 10) {
                $top[] = $entry;
            }
            if (isset($include[$id])) $requested[$id] = $entry;
            if ($id === $uid) {
                $profile = $entry;
            }
        }
        $cards = $this->run('SELECT tarjetas_cambio_usuario FROM carteras_usuario WHERE usuario_id=?', [$uid])->fetchColumn();
        $profile['tarjetas_cambio_usuario'] = (int) $cards;
        return ['perfil' => $profile, 'top' => $top] + ($include ? ['solicitados' => $requested] : []);
    }

    public function teamCapacity(int $uid): array
    {
        $points = 0;
        $rows = $this->run(
            'SELECT c.pokemon_id, p.experiencia FROM coleccion_pokemon c
            LEFT JOIN progreso_pokemon p ON p.usuario_id = c.usuario_id AND p.pokemon_id = c.pokemon_id
            WHERE c.usuario_id = ?',
            [$uid],
        );
        foreach ($rows as $row) {
            $points += self::trainerPokemonPoints(
                (int) $row['pokemon_id'],
                (int) $row['experiencia'],
            );
        }
        $level = 1 + intdiv($points, 100);
        return [
            'limite' => $level >= 7 ? 3 : ($level >= 3 ? 2 : 1),
            'nivel_siguiente' => $level >= 7 ? null : ($level >= 3 ? 7 : 3),
        ];
    }

    private static function trainerPokemonPoints(int $pokemon, int $experience): int
    {
        return 10 + 3 * (PokemonProgression::level($pokemon, $experience) - 1);
    }

    public function home(int $uid): array
    {
        $wallet = $this->wallet($uid);

        $items = [];

        $rows = $this->run(
            'SELECT c.pokemon_id,cp.energia,cp.vinculo,cp.habitat FROM coleccion_pokemon c JOIN cuidado_pokemon cp ON cp.usuario_id=c.usuario_id AND cp.pokemon_id=c.pokemon_id WHERE c.usuario_id=? ORDER BY c.fecha_agregado,c.id',

            [$uid],
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $p = (new ProgressionService($this->db))->decorate(
                $uid,
                MarketCatalog::get((int) $r['pokemon_id']),
            );

            $r['energia'] = (int) $r['energia'];

            $r['vinculo'] = (int) $r['vinculo'];

            $p['cuidado'] = $r + ['bonus' => self::bonus($r)];

            $items[] = $p;
        }

        return [
            'cartera' => $wallet,
            'pokemon' => $items,
            'alimentos' => self::FOOD,
            'escenarios' => $this->scenes($uid),
        ];
    }

    public function scenes(int $uid): array
    {
        $owned = $this->run('SELECT escenario FROM escenarios_usuario WHERE usuario_id=?', [
            $uid,
        ])->fetchAll(PDO::FETCH_COLUMN);

        $out = [];

        foreach (AdventureRules::SCENES as $id => $scene) {
            $out[] = $scene + [
                'id' => $id,
                'desbloqueado' => in_array($id, $owned, true),
                'fondo' => AdventureRules::artwork($scene['imagen'], $scene['fallback']),
            ];
        }

        return $out;
    }

    public function unlockScene(int $uid, string $id): array
    {
        $scene = AdventureRules::SCENES[$id] ?? null;

        if (!$scene) {
            throw new DomainException('Escenario no válido.');
        }

        $this->ensure($uid);

        $this->transaction(function () use ($uid, $id, $scene) {
            $balance = $this->lockWallet($uid);

            if (
                $this->run(
                    'SELECT escenario FROM escenarios_usuario WHERE usuario_id=? AND escenario=?',
                    [$uid, $id],
                )->fetchColumn()
            ) {
                throw new DomainException('Este escenario ya está desbloqueado.');
            }

            $this->movement(
                $uid,
                -$scene['precio'],
                'escenario-' . $id,
                'Desbloqueo de ' . $scene['nombre'],
                $balance,
            );

            $this->run('INSERT INTO escenarios_usuario(usuario_id,escenario) VALUES(?,?)', [
                $uid,
                $id,
            ]);
        });

        return $this->home($uid);
    }

    public function feed(int $uid, int $pid, string $food): void
    {
        if (!isset(self::FOOD[$food])) {
            throw new DomainException('Selecciona una comida válida.');
        }

        $this->ensure($uid);

        $this->transaction(function () use ($uid, $pid, $food) {
            $this->lockWallet($uid);

            $care = $this->run(
                'SELECT * FROM cuidado_pokemon WHERE usuario_id=? AND pokemon_id=? FOR UPDATE',

                [$uid, $pid],
            )->fetch(PDO::FETCH_ASSOC);

            if (!$care) {
                throw new DomainException('Este Pokémon no pertenece a tu Hogar.');
            }

            if ((int) $care['energia'] >= 100) {
                throw new DomainException(
                    'Ya tiene toda su energía. Dale comida después de una batalla.',
                );
            }

            $q = $this->run(
                'UPDATE inventario_comida SET cantidad=cantidad-1 WHERE usuario_id=? AND comida=? AND cantidad>0',

                [$uid, $food],
            );

            if (!$q->rowCount()) {
                throw new DomainException(
                    'No tienes esta comida. Puedes comprarla en la despensa.',
                );
            }

            $f = self::FOOD[$food];

            $this->run(
                'UPDATE cuidado_pokemon SET energia=LEAST(100,energia+?),vinculo=LEAST(100,vinculo+?),ultima_comida=NOW() WHERE usuario_id=? AND pokemon_id=?',

                [$f['energia'], $f['vinculo'], $uid, $pid],
            );
        });
    }

    public function habitat(int $uid, int $pid, string $habitat): void
    {
        if (!in_array($habitat, ['pradera', 'bosque', 'laguna'], true)) {
            throw new DomainException('Elige un hábitat válido.');
        }

        if (
            !$this->run(
                'SELECT pokemon_id FROM cuidado_pokemon WHERE usuario_id=? AND pokemon_id=?',

                [$uid, $pid],
            )->fetchColumn()
        ) {
            throw new DomainException('Este Pokémon no pertenece a tu Hogar.');
        }

        if (
            !$this->run(
                'SELECT escenario FROM escenarios_usuario WHERE usuario_id=? AND escenario=?',
                [$uid, $habitat],
            )->fetchColumn()
        ) {
            throw new DomainException('Desbloquea primero este escenario en el Hogar.');
        }

        $this->run('UPDATE cuidado_pokemon SET habitat=? WHERE usuario_id=? AND pokemon_id=?', [
            $habitat,

            $uid,

            $pid,
        ]);
    }

    public function applyCare(int $uid, array $p): array
    {
        $care = $this->run(
            'SELECT energia,vinculo FROM cuidado_pokemon WHERE usuario_id=? AND pokemon_id=?',

            [$uid, $p['id']],
        )->fetch(PDO::FETCH_ASSOC);

        if (!$care) {
            throw new DomainException('Tu equipo contiene un Pokémon que no has comprado.');
        }

        $bonus = self::bonus($care);

        $p['bonus_cuidado'] = $bonus;

        foreach (['ataque', 'defensa', 'ataque_especial', 'defensa_especial'] as $stat) {
            if (isset($p['estadisticas'][$stat])) {
                $p['estadisticas'][$stat] = (int) round(
                    $p['estadisticas'][$stat] * (1 + $bonus / 100),
                );
            }
        }

        return $p;
    }

    public function equipAbility(int $uid, int $pid, string $id): void
    {
        (new AbilityService($this->db))->equip($uid, $pid, $id);
    }

    public function equipMoves(int $uid, int $pid, array $names): void
    {
        (new ProgressionService($this->db))->equip($uid, $pid, $names);
    }
    public function settle(int $uid, array &$battle): void
    {
        $finished =
            ($battle['estado'] ?? '') === 'finalizada' &&
            in_array($battle['resultado'], ['victoria', 'derrota'], true);
        if (!$finished && empty($battle['eventos_experiencia'])) {
            return;
        }

        $this->ensure($uid);

        $reward = $finished && $battle['resultado'] === 'victoria'
            ? 180 + 70 * (count($battle['equipo_jugador']['pokemon']) - 1)
            : 0;

        $this->transaction(function () use ($uid, &$battle, $reward, $finished) {
            $balance = $this->lockWallet($uid);
            (new ProgressionService($this->db))->award($uid, $battle);
            if (!$finished) {
                return;
            }

            $key = 'batalla-' . $battle['id'];

            if (
                !$this->run('SELECT id FROM movimientos_monedas WHERE usuario_id=? AND clave=?', [
                    $uid,

                    $key,
                ])->fetchColumn()
            ) {
                $this->movement(
                    $uid,

                    $reward,

                    $key,

                    $battle['resultado'] === 'victoria'
                        ? 'Victoria en batalla'
                        : 'Batalla completada',

                    $balance,
                );

                foreach ($battle['equipo_jugador']['pokemon'] as $p) {
                    $this->run(
                        'UPDATE cuidado_pokemon SET energia=GREATEST(0,energia-12),vinculo=LEAST(100,vinculo+?) WHERE usuario_id=? AND pokemon_id=?',

                        [$battle['resultado'] === 'victoria' ? 2 : 1, $uid, $p['id']],
                    );
                }
            }

            $battle['recompensa_monedas'] = $reward;
        });
    }
}
