<?php
declare(strict_types=1);
namespace TrainerService;

use PDO;
use DomainException;

final class ExplorationService
{
    public const PACK_PRICE = 120;
    public const DAILY_PACK_LIMIT = 5;
    public const BALL_CAPACITY = 30;
    public const BALL_TYPES = [
        'poke' => [
            'nombre' => 'Poké Ball',
            'precio' => 40,
            'factor' => 1,
            'icono' => 'poke-ball.svg',
        ],
        'super' => [
            'nombre' => 'Super Ball',
            'precio' => 70,
            'factor' => 1.5,
            'icono' => 'super-ball.svg',
        ],
        'ultra' => [
            'nombre' => 'Ultra Ball',
            'precio' => 110,
            'factor' => 2,
            'icono' => 'ultra-ball.svg',
        ],
        'rapida' => [
            'nombre' => 'Veloz Ball',
            'precio' => 140,
            'factor' => 5,
            'icono' => 'quick-ball.svg',
            'descripcion' => 'Mantiene la misma probabilidad en todos los lanzamientos.',
        ],
        'ocaso' => [
            'nombre' => 'Ocaso Ball',
            'precio' => 120,
            'factor' => 1,
            'icono' => 'dusk-ball.svg',
            'descripcion' => 'Mayor probabilidad durante la noche de la región.',
        ],
        'malla' => [
            'nombre' => 'Malla Ball',
            'precio' => 120,
            'factor' => 1,
            'icono' => 'net-ball.svg',
            'descripcion' => 'Mayor probabilidad frente a tipos Agua o Bicho.',
        ],
    ];
    public function __construct(
        private PDO $db,
        private ?\Closure $captureRoll = null,
        private ?\Closure $encounterRoll = null,
    ) {}
    private function query(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
        return $q;
    }
    private function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format(
            'Y-m-d',
        );
    }
    private function ensure(int $uid): void
    {
        (new GameEconomy($this->db))->ensure($uid);
        $initial = [
            'version' => 1,
            'posicion' => ExplorationMap::START,
            'pokeballs' => 0,
            'descubiertos' => [],
            'visitados' => [],
            'enfriamientos' => [],
            'encuentro' => null,
            'diario' => [],
            'dia' => $this->today(),
            'capturas_hoy' => 0,
            'listo_en' => 0,
            'tutorial' => false,
            'solicitudes' => [],
        ];
        $this->query('INSERT IGNORE INTO progreso_exploracion(usuario_id,datos) VALUES(?,?)', [
            $uid,
            json_encode($initial, JSON_THROW_ON_ERROR),
        ]);
    }
    private function read(int $uid, bool $lock = false): array
    {
        return json_decode(
            $this->query(
                'SELECT datos FROM progreso_exploracion WHERE usuario_id=?' .
                    ($lock ? ' FOR UPDATE' : ''),
                [$uid],
            )->fetchColumn(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
    private function normalize(array $s): array
    {
        if ($s['dia'] !== $this->today()) {
            $s['dia'] = $this->today();
            $s['capturas_hoy'] = 0;
            $s['paquetes_hoy'] = 0;
        }
        if ($s['encuentro']) {
            $s['encuentro']['inicio'] ??= $s['encuentro']['vence'] - 180;
            $s['encuentro']['vence'] = min($s['encuentro']['vence'], $s['encuentro']['inicio'] + 60);
            if ($s['encuentro']['vence'] <= microtime(true)) $s['encuentro'] = null;
        }
        $s += ['avistamiento' => null, 'paquetes_hoy' => 0];
        $s += ['bolas' => ['poke' => $s['pokeballs'], 'super' => 0, 'ultra' => 0]];
        $s['bolas'] += array_fill_keys(array_keys(self::BALL_TYPES), 0);
        $s['pokeballs'] = array_sum($s['bolas']);
        if ($s['avistamiento'] && $s['avistamiento']['vence'] < microtime(true)) {
            $s['avistamiento'] = null;
        }
        foreach (['encuentro', 'avistamiento'] as $key) {
            if (isset($s[$key]['probabilidad'])) {
                $s[$key]['probabilidad'] = max(ExpeditionRules::MIN_CAPTURE_PERCENT, $s[$key]['probabilidad']);
            }
        }
        return ExpeditionRules::normalize($s);
    }
    private function battleCount(int $uid): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM movimientos_monedas WHERE usuario_id=? AND clave LIKE 'batalla-%'",
            [$uid],
        )->fetchColumn();
    }
    private function publicState(array $s, int $uid, bool $includeAlbum = true): array
    {
        if ($s['encuentro']) {
            $s['encuentro']['factores_bolas'] = array_map(
                fn($type) => self::ballFactor($type, $s['encuentro'], time()),
                array_combine(array_keys(self::BALL_TYPES), array_keys(self::BALL_TYPES)),
            );
        }
        $s['misiones'] = ExpeditionRules::missions($s, $this->battleCount($uid), $this->today());
        if ($includeAlbum) {
            $s['referencias_captura'] = self::captureReferences();
            $owned = array_map(
                'intval',
                $this->query('SELECT pokemon_id FROM coleccion_pokemon WHERE usuario_id=?', [$uid])->fetchAll(
                    PDO::FETCH_COLUMN,
                ),
            );
            $s['album'] = array_map(function ($sheet) use ($s, $owned) {
                $ids = array_column($sheet['especies'], 'id');
                return $sheet + [
                    'pegadas' => count(array_intersect($ids, $s['estampas'])),
                    'completa' => array_diff($ids, $s['estampas']) === [],
                    'reclamada' => in_array($sheet['id'], $s['recompensas_album'], true),
                    'opciones' => array_values(array_diff($ids, $owned)),
                ];
            }, ExplorationMap::albumSheets());
            $s['categorias_album'] = array_map(function ($category) use ($s) {
                $count = count(array_intersect($category['especies'], $s['estampas']));
                return $category + [
                    'pegadas' => $count,
                    'total' => count($category['especies']),
                    'completa' => $count === count($category['especies']),
                    'reclamada' => in_array($category['id'], $s['recompensas_categorias'], true),
                ];
            }, ExplorationMap::albumCategories());
        }
        unset($s['solicitudes']);
        unset($s['misiones_cobradas']);
        unset($s['rachas_captura']);
        return $s + [
            'precio_pack' => self::PACK_PRICE,
            'cantidad_pack' => 3,
            'limite_paquetes_diario' => self::DAILY_PACK_LIMIT,
            'capacidad_mochila' => self::BALL_CAPACITY,
            'tipos_bolas' => self::BALL_TYPES,
            'ahora' => time(),
            'ahora_preciso' => microtime(true),
            'modo' => 'expedicion',
            'tiempo_inicial_ms' => ExpeditionRules::INITIAL_TIME_MS,
        ];
    }
    public function state(int $uid): array
    {
        $this->ensure($uid);
        return [
            'exploracion' => $this->publicState($this->normalize($this->read($uid)), $uid),
            'mapa' => ExplorationMap::describe(),
            'cartera' => (new GameEconomy($this->db))->wallet($uid),
        ];
    }
    /** All writes use a wallet-first lock, a state version and an idempotency key. */
    public function act(int $uid, string $action, array $data): array
    {
        $key = $data['solicitud'] ?? null;
        if (!is_string($key) || !preg_match('/^[a-f0-9]{24,64}$/D', $key)) {
            throw new \InvalidArgumentException('Identificador de solicitud inválido.');
        }
        if (!isset($data['version']) || !is_int($data['version'])) {
            throw new \InvalidArgumentException('Versión inválida.');
        }
        $compact = $action === 'travel' && ($data['compacto'] ?? false) === true;
        if ($compact) {
            $exists = $this->query('SELECT 1 FROM progreso_exploracion WHERE usuario_id=?', [$uid])->fetchColumn();
            if (!$exists) $this->ensure($uid);
        } else {
            $this->ensure($uid);
        }
        $this->db->beginTransaction();
        try {
            $balance = $compact ? 0 : (int) $this->query(
                'SELECT monedas FROM carteras_usuario WHERE usuario_id=? FOR UPDATE',
                [$uid],
            )->fetchColumn();
            $s = $this->normalize($this->read($uid, true));
            if (isset($s['solicitudes'][$key])) {
                $result = $s['solicitudes'][$key];
            } else {
                if ($s['version'] !== $data['version']) {
                    throw new DomainException(
                        'Tu expedición cambió en otra pestaña. Hemos actualizado tu progreso.',
                    );
                }
                if (
                    $s['listo_en'] > microtime(true) &&
                    !(
                        $action === 'travel' &&
                        !$s['encuentro'] &&
                        $s['listo_en'] - microtime(true) <= (isset($data['camino']) ? 1.5 : 0.25)
                    )
                ) {
                    throw new DomainException(
                        'Tu entrenador todavía está llegando. Espera un momento.',
                    );
                }
                $result = $this->apply($uid, $s, $action, $data, $balance);
                $s['version']++;
                $s['solicitudes'][$key] = $result;
                $s['solicitudes'] = array_slice($s['solicitudes'], -24, null, true);
                $this->query('UPDATE progreso_exploracion SET datos=? WHERE usuario_id=?', [
                    json_encode($s, JSON_THROW_ON_ERROR),
                    $uid,
                ]);
            }
            $this->db->commit();
            return [
                'exploracion' => $this->publicState($s, $uid, !$compact),
                'resultado' => $result,
            ] + ($compact ? [] : ['cartera' => (new GameEconomy($this->db))->wallet($uid)]);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
    private function apply(int $uid, array &$s, string $action, array $d, int $balance): array
    {
        if ($action === 'mission') {
            if (!is_string($d['mision'] ?? null)) {
                throw new \InvalidArgumentException('Selecciona una misión válida.');
            }
            if ($s['encuentro']) {
                throw new DomainException('Termina el encuentro antes de reclamar la misión.');
            }
            $seconds = ExpeditionRules::claimMission(
                $s,
                $d['mision'],
                $this->battleCount($uid),
                $this->today(),
            );
            return [
                'tipo' => 'mision',
                'segundos' => $seconds,
                'mensaje' => 'Misión completada: ganaste ' . $seconds . ' segundos de exploración.',
            ];
        }
        if ($action === 'album') {
            if ($s['encuentro']) {
                throw new DomainException('Termina el encuentro antes de reclamar tu recompensa.');
            }
            $category = null;
            foreach (ExplorationMap::albumCategories() as $candidate) {
                if ($candidate['id'] === ($d['categoria'] ?? null)) $category = $candidate;
            }
            if (!$category) throw new \InvalidArgumentException('Selecciona una categoría válida.');
            if (array_diff($category['especies'], $s['estampas'])) {
                throw new DomainException('Completa todas las estampas de esta categoría.');
            }
            if (in_array($category['id'], $s['recompensas_categorias'], true)) {
                throw new DomainException('Ya recibiste el premio de esta categoría.');
            }
            $balls = array_sum($category['bolas']);
            if ($s['pokeballs'] + $balls > self::BALL_CAPACITY) {
                throw new DomainException('Necesitas ' . $balls . ' espacios libres en la mochila para este premio. Puedes reclamarlo después.');
            }
            $coins = $category['monedas'];
            $this->query('INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)', [
                $uid, 'cuaderno-categoria-' . $category['id'], 'Cuaderno del Alba · ' . $category['nombre'], $coins, $balance + $coins,
            ]);
            $this->query('UPDATE carteras_usuario SET monedas=monedas+?, tarjetas_cambio_usuario=tarjetas_cambio_usuario+? WHERE usuario_id=?', [$coins, $category['tarjetas'], $uid]);
            foreach ($category['bolas'] as $type => $quantity) $s['bolas'][$type] += $quantity;
            $s['pokeballs'] = array_sum($s['bolas']);
            foreach ($category['comida'] as $food => $quantity) {
                $this->query('INSERT INTO inventario_comida(usuario_id,comida,cantidad) VALUES(?,?,?) ON DUPLICATE KEY UPDATE cantidad=cantidad+VALUES(cantidad)', [$uid, $food, $quantity]);
            }
            if (isset($category['pokemon'])) {
                $pid = $category['pokemon']['id'];
                $owned = $this->query('SELECT 1 FROM coleccion_pokemon WHERE usuario_id=? AND pokemon_id=?', [$uid, $pid])->fetchColumn();
                if (!$owned) $this->grantPokemon($uid, $pid);
                $experience = PokemonProgression::threshold($pid, $category['pokemon']['nivel']);
                $profile = PokemonProgression::profile($pid, $experience);
                $this->query(
                    'INSERT INTO progreso_pokemon(usuario_id,pokemon_id,experiencia,movimientos) VALUES(?,?,?,?)
                    ON DUPLICATE KEY UPDATE experiencia=GREATEST(experiencia,VALUES(experiencia))',
                    [$uid, $pid, $experience, json_encode(array_column($profile['movimientos'], 'nombre'), JSON_THROW_ON_ERROR)],
                );
            }
            $s['recompensas_categorias'][] = $category['id'];
            return ['tipo' => 'recompensa_album', 'categoria' => $category['id'],
                'mensaje' => '¡Categoría ' . $category['nombre'] . ' completada! Tu premio ya está en tu inventario.'
            ];
        }
        if ($action === 'tutorial') {
            $s['tutorial'] = true;
            return ['tipo' => 'tutorial'];
        }
        if ($action === 'supplies') {
            $type = $d['tipo'] ?? 'poke';
            $quantity = $d['cantidad'] ?? 3;
            if (
                !is_string($type) ||
                !isset(self::BALL_TYPES[$type]) ||
                !in_array($quantity, [3, 6], true)
            ) {
                throw new \InvalidArgumentException(
                    'Selecciona un tipo de Poké Ball y un paquete de 3 o 6.',
                );
            }
            $price = self::BALL_TYPES[$type]['precio'] * $quantity;
            if ($s['paquetes_hoy'] + $quantity / 3 > self::DAILY_PACK_LIMIT) {
                throw new DomainException(
                    'Compraste tus 15 Poké Balls de hoy. El cupo vuelve a medianoche, hora de Colombia.',
                );
            }
            if ($s['pokeballs'] + $quantity > self::BALL_CAPACITY) {
                throw new DomainException(
                    'La mochila admite 30 Poké Balls. Usa algunas antes de comprar más.',
                );
            }
            if ($balance < $price) {
                throw new DomainException('No tienes monedas suficientes para ese paquete.');
            }
            $this->query(
                'INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)',
                [
                    $uid,
                    'exp-' . $d['solicitud'],
                    'Exploración: ' . $quantity . ' ' . self::BALL_TYPES[$type]['nombre'],
                    -$price,
                    $balance - $price,
                ],
            );
            $this->query('UPDATE carteras_usuario SET monedas=monedas-? WHERE usuario_id=?', [
                $price,
                $uid,
            ]);
            $s['bolas'][$type] += $quantity;
            $s['pokeballs'] = array_sum($s['bolas']);
            $s['paquetes_hoy'] += intdiv($quantity, 3);
            return [
                'tipo' => 'compra',
                'mensaje' =>
                    'Guardaste ' .
                    $quantity .
                    ' ' .
                    self::BALL_TYPES[$type]['nombre'] .
                    ' en tu mochila.',
            ];
        }
        if ($action === 'observe') {
            $sighting = $s['avistamiento'];
            if ($s['encuentro']) {
                throw new DomainException('Termina el encuentro actual primero.');
            }
            if (
                !$sighting ||
                !is_string($d['avistamiento'] ?? null) ||
                !hash_equals($sighting['id'], $d['avistamiento'])
            ) {
                throw new DomainException('Ese avistamiento ya no está disponible.');
            }
            $s['encuentro'] = WildlifeRules::encounter($sighting, $s['posicion'], microtime(true));
            if (
                ExpeditionRules::captureProtected(
                    $s,
                    $s['encuentro']['pokemon']['rareza_nivel'] ?? 1,
                )
            ) {
                $s['encuentro']['probabilidad'] = 100;
                $s['encuentro']['proteccion'] = true;
            }
            $s['avistamiento'] = null;
            return [
                'tipo' => 'encuentro',
                'mensaje' => 'Te acercaste con cuidado. Puedes intentar capturarlo.',
            ];
        }
        if ($action === 'travel') {
            if ($s['encuentro']) {
                throw new DomainException('Termina el encuentro antes de seguir caminando.');
            }
            if (
                !isset($d['x'], $d['y']) ||
                !is_int($d['x']) ||
                !is_int($d['y']) ||
                $d['x'] < 0 ||
                $d['x'] >= ExplorationMap::WIDTH ||
                $d['y'] < 0 ||
                $d['y'] >= ExplorationMap::HEIGHT
            ) {
                throw new \InvalidArgumentException('Destino inválido.');
            }
            if (isset($d['camino'])) {
                if (!is_array($d['camino']) || !array_is_list($d['camino']) ||
                    count($d['camino']) < 1 || count($d['camino']) > 12) {
                    throw new \InvalidArgumentException('Tramo de movimiento inválido.');
                }
                $path = [$s['posicion']];
                foreach ($d['camino'] as $point) {
                    $last = $path[array_key_last($path)];
                    if (!is_array($point) || !array_is_list($point) || count($point) !== 2 ||
                        !is_int($point[0]) || !is_int($point[1]) ||
                        abs($point[0] - $last[0]) + abs($point[1] - $last[1]) !== 1 ||
                        !ExplorationMap::walkable($point[0], $point[1])) {
                        throw new \InvalidArgumentException('El tramo debe seguir casillas contiguas transitables.');
                    }
                    $path[] = $point;
                }
                if ($point !== [$d['x'], $d['y']]) {
                    throw new \InvalidArgumentException('El tramo no coincide con el destino.');
                }
            } else {
                $path = ExplorationMap::path($s['posicion'], [$d['x'], $d['y']]);
            }
            if (isset($d['correr']) && !is_bool($d['correr'])) {
                throw new \InvalidArgumentException('Modo de movimiento inválido.');
            }
            $stepMs = !empty($d['correr']) ? ExplorationMap::RUN_MS : ExplorationMap::WALK_MS;
            $running = !empty($d['correr']);
            $journey = ExpeditionRules::journey(
                $s,
                $path,
                $running,
                fn() => $this->encounterRoll ? ($this->encounterRoll)() : random_int(1, 100),
            );
            $path = $journey['camino'];
            if (count($path) === 1 && [$d['x'], $d['y']] !== $s['posicion']) {
                throw new DomainException(
                    'No te queda tiempo de exploración. Reclama una misión o regresa gratis al campamento.',
                );
            }
            $s['listo_en'] =
                max(microtime(true), (float) $s['listo_en']) +
                (max(0, count($path) - 1) * $stepMs) / 1000;
            $result = ['tipo' => 'camino', 'camino' => $path, 'paso_ms' => $stepMs];
            if (
                $running &&
                !empty($s['avistamiento']) &&
                $s['avistamiento']['modo'] !== 'huyendo'
            ) {
                foreach ($path as $i => $point) {
                    if (
                        WildlifeRules::near(
                            $s['avistamiento'],
                            $point,
                            microtime(true) + ($i * $stepMs) / 1000,
                            3.5,
                        )
                    ) {
                        $s['avistamiento'] = WildlifeRules::flee(
                            $s['avistamiento'],
                            $point,
                            microtime(true),
                        );
                        $result['mensaje'] =
                            '¡Tus pasos lo han asustado! El Pokémon se aleja corriendo.';
                        $result['tipo'] = 'espantado';
                        break;
                    }
                }
            }
            if ($journey['regreso']) {
                $result['mensaje'] =
                    'Llegaste al campamento sin gastar tiempo. Revisa las misiones para preparar otra salida.';
                return $result;
            }
            if ($journey['avistamiento']) {
                $available = ExplorationMap::encounterCandidates(
                    $s['estampas'],
                    $this->encounterRoll ? ($this->encounterRoll)() : random_int(1, 1000),
                );
                if ($available) {
                    $pid = $available[random_int(0, count($available) - 1)];
                    $pokemon = ExplorationMap::species($pid);
                    $s['avistamiento'] = WildlifeRules::create(
                        $pokemon,
                        $s['posicion'],
                        microtime(true),
                        $running,
                    );
                    if (!in_array($pid, $s['descubiertos'], true)) {
                        $s['descubiertos'][] = $pid;
                    }
                    $result['tipo'] = 'avistamiento';
                    $result['mensaje'] = $running
                        ? '¡Un Pokémon se mueve entre la hierba! Deja de correr y acércate con cuidado.'
                        : '¡Un Pokémon está paseando cerca! Síguelo y acércate sin hacer ruido.';
                } else {
                    $result['mensaje'] = '¡Álbum completo! Revisa las recompensas de tus hojas.';
                }
            } elseif ($s['tiempo_ms'] <= 10000) {
                $result['mensaje'] =
                    'Te quedan pocos segundos. Reclama una misión o regresa al campamento.';
            }
            return $result;
        }
        $encounter = $s['encuentro'];
        if (
            !$encounter ||
            !is_string($d['encuentro'] ?? null) ||
            !hash_equals($encounter['id'], $d['encuentro'])
        ) {
            throw new DomainException(
                'Este encuentro terminó. Sigue explorando para encontrar otro.',
            );
        }
        if ($action === 'leave') {
            $s['encuentro'] = null;
            return ['tipo' => 'retirada', 'mensaje' => 'El Pokémon volvió a la pradera.'];
        }
        if ($action !== 'capture') {
            throw new \InvalidArgumentException('Acción inválida.');
        }
        if ($s['pokeballs'] < 1) {
            throw new DomainException(
                'No tienes Poké Balls. Compra un paquete o deja libre al Pokémon.',
            );
        }
        $pid = $encounter['pokemon']['id'];
        if (in_array($pid, $s['estampas'], true)) {
            throw new DomainException('Ya tienes esta estampa. Déjalo libre para buscar otra.');
        }
        $ball = $d['bola'] ?? 'poke';
        if (!is_string($ball) || !isset(self::BALL_TYPES[$ball])) {
            throw new \InvalidArgumentException('Poké Ball no válida.');
        }
        if (($s['bolas'][$ball] ?? 0) < 1) {
            throw new DomainException('No tienes ese tipo de Poké Ball.');
        }
        $s['bolas'][$ball]--;
        $s['pokeballs'] = array_sum($s['bolas']);
        $s['encuentro']['intentos']++;
        $tier = $encounter['pokemon']['rareza_nivel'] ?? 1;
        $roll = $this->captureRoll ? ($this->captureRoll)() : random_int(1, 10000) / 100;
        $chance = round(min(
            100,
            $encounter['probabilidad'] * self::ballFactor($ball, $encounter, time()),
        ));
        if ($roll > $chance) {
            $fled = $s['encuentro']['intentos'] >= 3;
            if ($fled) {
                $s['encuentro'] = null;
                $s['rachas_captura'][$tier]++;
            }
            return [
                'tipo' => $fled ? 'huida' : 'fallo',
                'mensaje' => $fled
                    ? 'El Pokémon escapó a la hierba. Puedes buscar otro.'
                    : 'Se ha liberado. Puedes volver a intentarlo; cada lanzamiento consume una Poké Ball.',
            ];
        }
        $s['estampas'][] = $pid;
        $s['rachas_captura'][$tier] = 0;
        $s['capturas_hoy']++;
        $s['encuentro'] = null;
        array_unshift($s['diario'], ['pokemon' => $encounter['pokemon'], 'fecha' => time()]);
        $s['diario'] = array_slice($s['diario'], 0, 12);
        return [
            'tipo' => 'captura',
            'pokemon' => $encounter['pokemon'],
            'mensaje' => '¡Estampa de ' . $encounter['pokemon']['nombre'] . ' pegada en tu álbum!',
        ];
    }
    public static function captureReferences(): array
    {
        $references = [];
        foreach ([1 => 'Común', 2 => 'Escaso', 3 => 'Raro', 4 => 'Legendario'] as $tier => $name) {
            $base = ExpeditionRules::captureChance(0, false, $tier);
            $balls = [];
            foreach (array_keys(self::BALL_TYPES) as $ball) {
                $generic = ['intentos' => 0, 'pokemon' => ['tipos' => []]];
                $water = ['intentos' => 0, 'pokemon' => ['tipos' => ['water']]];
                $balls[$ball] = [
                    'normal' => round(min(100, $base * self::ballFactor($ball, $generic, 0)), 2),
                    'noche' => round(min(100, $base * self::ballFactor($ball, $generic, 480)), 2),
                    'agua_bicho' => round(min(100, $base * self::ballFactor($ball, $water, 0)), 2),
                ];
            }
            $references[] = ['id' => $tier, 'nombre' => $name, 'bolas' => $balls];
        }
        return $references;
    }
    public static function ballFactor(string $ball, array $encounter, int $now): float
    {
        return match ($ball) {
            'ocaso' => $now % 720 >= 480 ? 3 : 1,
            'malla' => array_intersect($encounter['pokemon']['tipos'] ?? [], ['water', 'bug'])
                ? 3.5
                : 1,
            default => self::BALL_TYPES[$ball]['factor'] ?? 1,
        };
    }
    private function grantPokemon(int $uid, int $pid): void
    {
        $empty = !$this->query('SELECT 1 FROM coleccion_pokemon WHERE usuario_id=? LIMIT 1', [
            $uid,
        ])->fetchColumn();
        $this->query('INSERT INTO coleccion_pokemon(usuario_id,pokemon_id) VALUES(?,?)', [$uid, $pid]);
        $this->query('INSERT INTO cuidado_pokemon(usuario_id,pokemon_id) VALUES(?,?)', [
            $uid,
            $pid,
        ]);
        if ($empty) {
            $this->query("INSERT INTO equipos(usuario_id,nombre) VALUES(?,'Mi primer equipo')", [
                $uid,
            ]);
            $team = (int) $this->db->lastInsertId();
            $this->query(
                'INSERT INTO miembros_equipo(equipo_id,posicion,pokemon_id) VALUES(?,1,?)',
                [$team, $pid],
            );
        }
    }
}
