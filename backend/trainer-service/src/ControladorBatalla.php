<?php

declare(strict_types=1);

namespace TrainerService;

use PDO;

final class ControladorBatalla
{
    private function duelQuery(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->conexion->prepare($sql);
        $q->execute($args);
        return $q;
    }

    public function friends(bool $write, mixed $query): never
    {
        $uid = $this->usuarioId();
        if ($write) {
            $input = JsonResponse::body();
            $other = $this->positiveInteger($input['id'] ?? null, 'Entrenador');
            if ($uid === $other) JsonResponse::error('Elige a otro entrenador.', 400);
            $pair = [min($uid, $other), max($uid, $other)];
            $action = $input['accion'] ?? '';
            $delivered = ' AND EXISTS (SELECT 1 FROM eventos_amistad e
                WHERE e.usuario_menor=amistades.usuario_menor AND e.usuario_mayor=amistades.usuario_mayor
                AND e.entregado_en IS NOT NULL)';
            if ($action === 'solicitar') {
                $this->conexion->beginTransaction();
                try {
                    $changed = $this->duelQuery('INSERT IGNORE INTO amistades (usuario_menor,usuario_mayor,solicitante)
                        SELECT ?,?,? FROM usuarios WHERE id=?', [...$pair, $uid, $other])->rowCount();
                    if ($changed) (new FriendshipEvents($this->conexion))->requested($pair, $uid, $other);
                    $this->conexion->commit();
                } catch (\Throwable $error) {
                    if ($this->conexion->inTransaction()) $this->conexion->rollBack();
                    throw $error;
                }
            } elseif ($action === 'aceptar') {
                $changed = $this->duelQuery('UPDATE amistades SET aceptada=1 WHERE usuario_menor=? AND usuario_mayor=? AND solicitante=? AND aceptada=0' . $delivered, [...$pair, $other])->rowCount();
            } elseif ($action === 'rechazar') {
                $changed = $this->duelQuery('DELETE FROM amistades WHERE usuario_menor=? AND usuario_mayor=? AND solicitante=? AND aceptada=0' . $delivered, [...$pair, $other])->rowCount();
            } else {
                JsonResponse::error('Acción desconocida.', 400);
            }
            if (!$changed) JsonResponse::error('La solicitud ya cambió o no está disponible.', 409);
        }
        if ($query !== null && (!is_string($query) || !preg_match('/^@[a-z0-9_-]{1,24}$/D', $query))) {
            JsonResponse::error('Escribe el usuario completo, incluido el @.', 400);
        }
        $list = $this->duelQuery('SELECT u.id,u.usuario,a.aceptada,a.solicitante FROM amistades a
            JOIN usuarios u ON u.id=IF(a.usuario_menor=?,a.usuario_mayor,a.usuario_menor)
            LEFT JOIN eventos_amistad e ON e.usuario_menor=a.usuario_menor AND e.usuario_mayor=a.usuario_mayor
            WHERE ? IN (a.usuario_menor,a.usuario_mayor)
            AND (a.aceptada=1 OR a.solicitante=? OR e.entregado_en IS NOT NULL)
            ORDER BY u.usuario', [$uid, $uid, $uid])->fetchAll(PDO::FETCH_ASSOC);
        $found = $query === null ? [] : $this->duelQuery("SELECT id,usuario FROM usuarios
            WHERE id<>? AND usuario = ? AND NOT EXISTS
            (SELECT 1 FROM amistades a WHERE a.usuario_menor=LEAST(usuarios.id,?) AND a.usuario_mayor=GREATEST(usuarios.id,?))
            LIMIT 1", [$uid, substr($query, 1), $uid, $uid])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$friend) {
            $friend['recibida'] = (int) $friend['solicitante'] !== $uid;
            unset($friend['solicitante']);
        }
        unset($friend);
        JsonResponse::send(['success' => true, 'amigos' => $list, 'resultados' => $found]);
    }

    public function friendDuelInvites(): never
    {
        $uid = $this->usuarioId();
        $rows = $this->duelQuery("SELECT d.id,u.usuario FROM duelos_jugadores d JOIN usuarios u ON u.id=d.retador
            WHERE d.invitado=? AND d.estado='pendiente' AND d.vence>? AND JSON_EXTRACT(d.datos,'$.amigos')=true
            ORDER BY d.vence DESC LIMIT 1", [$uid, time()])->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            try {
                $rows = $this->duelQuery("SELECT s.id,u.usuario,'duo' tipo FROM notificaciones_sala n
                    JOIN eventos_sala e ON e.id=n.evento_id
                    JOIN salas_duo s ON s.id=e.sala_id
                    JOIN usuarios u ON u.id=s.anfitrion
                    JOIN integrantes_sala m ON m.sala_id=s.id AND m.usuario_id=e.destinatario_id
                    WHERE e.destinatario_id=? AND m.aceptado=0 AND s.estado IN('invitada','preparando')
                    AND s.vence>? ORDER BY n.entregada_en DESC,e.id DESC LIMIT 1",
                    [$uid,time()])->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $error) {
                if ((int) ($error->errorInfo[1] ?? 0) !== 1146) throw $error;
            }
        }
        if (!$rows) {
            $rows = $this->duelQuery("SELECT e.id,u.usuario,'amistad' tipo FROM eventos_amistad e
                JOIN usuarios u ON u.id=e.solicitante_id
                JOIN amistades a ON a.usuario_menor=e.usuario_menor AND a.usuario_mayor=e.usuario_mayor
                WHERE e.destinatario_id=? AND e.entregado_en IS NOT NULL AND a.aceptada=0
                ORDER BY e.id DESC LIMIT 1", [$uid])->fetchAll(PDO::FETCH_ASSOC);
        }
        JsonResponse::send(['success' => true, 'invitaciones' => $rows]);
    }

    public function duels(bool $write): never
    {
        $uid = $this->usuarioId();
        $now = time();
        if ($write) {
            $input = JsonResponse::body();
            $action = $input['accion'] ?? '';
            $this->conexion->beginTransaction();
            try {
                $this->updateDuel($uid, $now, $action, $input);
                $this->conexion->commit();
            } catch (\DomainException $error) {
                $this->conexion->rollBack();
                JsonResponse::error($error->getMessage(), 409);
            }
        }
        $search = $this->duelQuery('SELECT equipo_id, vence, alineacion FROM busquedas_duelo WHERE usuario_id=? AND vence>?', [$uid, $now])->fetch(PDO::FETCH_ASSOC);
        $count = count(json_decode($search['alineacion'] ?? '[]', true, 512, JSON_THROW_ON_ERROR));
        $players = $this->duelQuery(
            "SELECT b.usuario_id id, u.usuario, COALESCE(JSON_LENGTH(b.alineacion),
                (SELECT COUNT(*) FROM miembros_equipo m JOIN coleccion_pokemon c ON c.usuario_id=b.usuario_id AND c.pokemon_id=m.pokemon_id WHERE m.equipo_id=b.equipo_id)) cantidad,
             CASE WHEN EXISTS (SELECT 1 FROM duelos_jugadores d WHERE d.vence>? AND d.estado='activa'
                 AND b.usuario_id IN (d.retador,d.invitado)) THEN 'jugando'
             WHEN EXISTS (SELECT 1 FROM duelos_jugadores d WHERE d.vence>? AND d.estado='pendiente'
                 AND b.usuario_id IN (d.retador,d.invitado) AND JSON_EXTRACT(d.datos,'$.match')=true)
             THEN 'confirmando' WHEN b.alineacion IS NULL THEN 'disponible' ELSE 'buscando' END estado
             FROM busquedas_duelo b JOIN usuarios u ON u.id=b.usuario_id
             WHERE b.vence>? AND b.usuario_id<>? AND JSON_LENGTH(b.alineacion)=?
             ORDER BY u.usuario LIMIT 40",
            [$now, $now, $now, $uid, $count],
        )->fetchAll(PDO::FETCH_ASSOC);
        $duels = $this->duelQuery(
            "SELECT d.*, a.usuario nombre_retador, b.usuario nombre_invitado FROM duelos_jugadores d JOIN usuarios a ON a.id=d.retador JOIN usuarios b ON b.id=d.invitado
             WHERE (d.retador=? OR d.invitado=?) AND d.vence>?
             AND (d.estado<>'pendiente' OR JSON_EXTRACT(d.datos,'$.match')=true OR JSON_EXTRACT(d.datos,'$.invitacion_directa')=true)
             ORDER BY d.vence DESC LIMIT 20",
            [$uid, $uid, $now],
        )->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_fill_keys(array_merge(array_column($players, 'id'), array_column($duels, 'retador'), array_column($duels, 'invitado')), true);
        $ranks = $ids ? (new GameEconomy($this->conexion))->trainerProfile($uid, $ids)['solicitados'] : [];
        foreach ($players as &$player) {
            $player += array_intersect_key($ranks[$player['id']], ['nivel' => true, 'rango' => true]);
        }
        unset($player);
        foreach ($duels as &$duel) {
            $duel['recibida'] = (int) $duel['invitado'] === $uid;
            $duel['rival'] = $duel['recibida'] ? $duel['nombre_retador'] : $duel['nombre_invitado'];
            $duel['nivel_rival'] = $ranks[$duel['recibida'] ? $duel['retador'] : $duel['invitado']]['nivel'];
            $duel['partida'] = null;
            $game = json_decode($duel['datos'], true, 512, JSON_THROW_ON_ERROR);
            $duel['emparejado'] = $game['match'] ?? false;
            $duel['entre_amigos'] = $game['amigos'] ?? false;
            $duel['confirmado'] = isset($game['confirmados'][$uid]);
            $duel['cantidad'] = $game['cantidad'] ?? null;
            if (in_array($duel['estado'], ['activa', 'finalizada'], true)) {
                $side = $duel['recibida'] ? 'rival' : 'jugador';
                $other = $side === 'jugador' ? 'rival' : 'jugador';
                $wagerDeadline = $this->duelWagerDeadline($game, $now);
                $choosing = $now >= ($game['apuesta_hasta'] ?? 0);
                $duel['partida'] = [
                    'apuesta' => $duel['estado'] === 'activa' && $wagerDeadline > $now
                        ? ['segundos' => $wagerDeadline - $now, 'elegir' => $choosing,
                            'mi_eleccion' => $game['elecciones'][$uid] ?? null,
                            'votos' => array_count_values($game['elecciones'] ?? []),
                            'total_jugadores' => 2,
                            'mi_monto' => $game['propuestas'][$uid] ?? null,
                            'rival_monto' => $game['propuestas'][$duel['recibida'] ? $duel['retador'] : $duel['invitado']] ?? null]
                        : null,
                    'bote' => ($game['aporte'] ?? 0) * 2,
                    'rendicion' => $game['rendicion'] ?? false,
                    'equipo' => $game['equipo_' . $side]['pokemon'],
                    'rival' => array_map(static fn($p) => array_intersect_key($p, array_flip(['id', 'nombre', 'hp_actual', 'hp_maximo', 'tipos', 'sprite_animado_hd'])), $game['equipo_' . $other]['pokemon']),
                    'activo' => $game['activo_' . $side], 'activo_rival' => $game['activo_' . $other],
                    'iniciativa' => isset($game['iniciativa']) ? [
                        'listos' => ['jugador' => $game['iniciativa']['listos'][$side], 'rival' => $game['iniciativa']['listos'][$other]],
                        'orden' => array_map(fn($entry) => $entry === $side ? 'jugador' : 'rival', $game['iniciativa']['orden']),
                        'caras' => array_map(fn($entry) => $entry === $side ? 'jugador' : 'rival', $game['iniciativa']['caras'] ?? array_keys($game['iniciativa']['listos'])),
                    ] : null,
                    'turno' => (!isset($game['iniciativa']) || $game['iniciativa']['orden']) && $game['lado_turno'] === $side,
                    'ronda' => $game['turno'],
                    'accion' => isset($game['ultima_accion'])
                        ? array_merge($game['ultima_accion'], ['propia' => $game['ultima_accion']['lado'] === $side])
                        : null,
                    'resultado' => $game['resultado'] === null ? null : (($game['resultado'] === 'victoria') === ($side === 'jugador') ? 'Victoria' : 'Derrota'),
                    'mensaje' => $game['historial'] ? end($game['historial']) : '',
                ];
            }
            unset($duel['datos']);
        }
        unset($duel);
        JsonResponse::send(['success' => true, 'jugadores' => $players, 'duelos' => $duels,
            'buscando' => $count > 0,
            'inicio_busqueda' => $count ? (int) $search['vence'] - 120 : null, 'hora_servidor' => $now]);
    }

    private function updateDuel(int $uid, int $now, string $action, array $input): void
    {
        if ($action === 'salir') {
            $this->duelQuery("DELETE FROM duelos_jugadores WHERE estado='pendiente' AND ? IN (retador,invitado) AND JSON_EXTRACT(datos,'$.match')=true", [$uid]);
            $this->duelQuery('DELETE FROM busquedas_duelo WHERE usuario_id=?', [$uid]);
            return;
        }
        if (in_array($action, ['buscar', 'disponible'], true)) {
            $team = ($input['equipo_id'] ?? null) === 0 && $action === 'buscar' ? 0 : $this->positiveInteger($input['equipo_id'] ?? null, 'equipo_id');
            if ($team !== 0) $this->team($team, $uid);
            if ($action === 'disponible') {
                $this->duelQuery('INSERT INTO busquedas_duelo (usuario_id,equipo_id,vence,alineacion) VALUES (?,?,?,NULL)
                    ON DUPLICATE KEY UPDATE equipo_id=IF(alineacion IS NULL,VALUES(equipo_id),equipo_id), vence=IF(alineacion IS NULL,VALUES(vence),vence)', [$uid, $team, $now + 120]);
                return;
            }
            $roster = $this->duelRoster($uid, $team, $input['alineacion'] ?? []);
            $this->duelQuery('INSERT INTO busquedas_duelo (usuario_id,equipo_id,vence,alineacion) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE equipo_id=VALUES(equipo_id), vence=VALUES(vence), alineacion=VALUES(alineacion)',
                [$uid, $team ?: null, $now + 120, json_encode(array_column($roster, 'id'), JSON_THROW_ON_ERROR)]);
            return;
        }
        if (in_array($action, ['invitar', 'invitar_amigo'], true)) {
            $other = $this->positiveInteger($input['rival'] ?? null, 'rival');
            if ($other === $uid) throw new \DomainException('Elige a otro jugador.');
            $friends = $action === 'invitar_amigo';
            $this->duelQuery('SELECT id FROM usuarios WHERE id IN (?,?) ORDER BY id FOR UPDATE', [$uid, $other])->fetchAll();
            $search = $this->duelQuery('SELECT usuario_id,equipo_id,alineacion FROM busquedas_duelo
                WHERE usuario_id IN (?,?) AND vence>? ORDER BY usuario_id FOR UPDATE', [$uid, $other, $now])->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
            $searching = $search[$uid] ?? null;
            $target = $search[$other] ?? null;
            if (!$friends && (!isset($searching['alineacion']) || !isset($target['alineacion']))) {
                throw new \DomainException('Ambos entrenadores deben estar buscando partida.');
            }
            if ($friends && !$this->duelQuery('SELECT aceptada FROM amistades WHERE usuario_menor=? AND usuario_mayor=? AND aceptada=1',
                [min($uid, $other), max($uid, $other)])->fetchColumn()) {
                throw new \DomainException('Solo puedes crear esta sala con un amigo agregado.');
            }
            if (($friends && isset($target['alineacion'])) ||
                $this->duelQuery("SELECT id FROM duelos_jugadores WHERE estado='activa' AND vence>? AND (retador IN (?,?) OR invitado IN (?,?))", [$now, $uid, $other, $uid, $other])->fetchColumn()) {
                throw new \DomainException('Ese entrenador no está disponible para recibir invitaciones.');
            }
            if ($this->duelQuery("SELECT id FROM duelos_jugadores WHERE estado='pendiente' AND vence>?
                AND (JSON_EXTRACT(datos,'$.match')=true OR JSON_EXTRACT(datos,'$.invitacion_directa')=true)
                AND ((retador=? AND invitado=?) OR (retador=? AND invitado=?))", [$now, $uid, $other, $other, $uid])->fetchColumn()) return;
            $lineup = $friends ? ($input['alineacion'] ?? null) : json_decode($searching['alineacion'], true, 512, JSON_THROW_ON_ERROR);
            $team = $friends ? $this->positiveInteger($input['equipo_id'] ?? null, 'equipo_id') : (int) $searching['equipo_id'];
            $roster = $this->duelRoster($uid, $team, $lineup);
            $guestLineup = $friends ? null : json_decode($target['alineacion'], true, 512, JSON_THROW_ON_ERROR);
            if (!$friends && count($roster) !== count($guestLineup)) {
                throw new \DomainException('El otro entrenador tiene una alineación de distinto tamaño.');
            }
            $game = ['equipo' => $team, 'alineacion' => array_column($roster, 'id'), 'cantidad' => count($roster), 'amigos' => $friends, 'invitacion_directa' => true];
            if (!$friends) {
                $game += ['match' => true, 'confirmados' => [$uid => true],
                    'equipo_invitado' => (int) $target['equipo_id'], 'alineacion_invitado' => $guestLineup];
            }
            $this->duelQuery("INSERT INTO duelos_jugadores VALUES (?,?,?,'pendiente',?,?)", [bin2hex(random_bytes(16)), $uid, $other, $now + 15, json_encode($game, JSON_THROW_ON_ERROR)]);
            return;
        }
        $id = Input::text($input['id'] ?? '', 'Duelo', 32);
        $duel = $this->duelQuery('SELECT * FROM duelos_jugadores WHERE id=? AND (retador=? OR invitado=?) AND vence>? FOR UPDATE', [$id, $uid, $uid, $now])->fetch(PDO::FETCH_ASSOC);
        if (!$duel) throw new \DomainException('El duelo ya no está disponible.');
        $game = json_decode($duel['datos'], true, 512, JSON_THROW_ON_ERROR);
        $state = $duel['estado'];
        if ($state === 'pendiente' && !($game['match'] ?? false) && !($game['invitacion_directa'] ?? false)) {
            throw new \DomainException('Esa invitación ya no está disponible.');
        }
        $matched = $game['match'] ?? false;
        $canAnswer = $matched || (int) $duel['invitado'] === $uid;
        if ($state === 'pendiente' && $canAnswer && $action === 'rechazar') {
            $this->duelQuery('DELETE FROM duelos_jugadores WHERE id=?', [$id]);
            return;
        }
        if ($state === 'pendiente' && $canAnswer && $action === 'aceptar') {
            $retador = (int) $duel['retador'];
            $invitado = (int) $duel['invitado'];
            if ($matched) {
                $game['confirmados'][$uid] = true;
                if (count($game['confirmados']) < 2) {
                    $this->duelQuery('UPDATE duelos_jugadores SET datos=? WHERE id=?', [json_encode($game, JSON_THROW_ON_ERROR), $id]);
                    return;
                }
            } elseif ($this->duelQuery('SELECT usuario_id FROM busquedas_duelo WHERE usuario_id=? AND vence>? AND alineacion IS NOT NULL', [$uid, $now])->fetchColumn()) {
                throw new \DomainException('Detén la búsqueda para aceptar una invitación directa.');
            }
            $this->duelQuery('SELECT id FROM usuarios WHERE id IN (?,?) ORDER BY id FOR UPDATE', [$retador, $invitado])->fetchAll();
            if ($this->duelQuery("SELECT id FROM duelos_jugadores WHERE estado='activa' AND vence>? AND (retador IN (?,?) OR invitado IN (?,?))", [$now, $retador, $invitado, $retador, $invitado])->fetchColumn()) throw new \DomainException('Uno de los jugadores ya está en duelo.');
            $own = $this->duelRoster($retador, $game['equipo'], $game['alineacion'] ?? null);
            $guest = $this->duelRoster($invitado, $matched ? $game['equipo_invitado'] : $this->positiveInteger($input['equipo_id'] ?? null, 'equipo_id'),
                $matched ? $game['alineacion_invitado'] : ($input['alineacion'] ?? null));
            if (count($own) !== count($guest)) throw new \DomainException('Ambos equipos deben llevar la misma cantidad de Pokémon.');
            $game = [
                'amigos' => $game['amigos'] ?? false,
                'estado' => 'en_curso', 'resultado' => null, 'historial' => [], 'turno' => 1,
                'iniciativa' => BattleInitiative::create(['jugador', 'rival']),
                'equipo_jugador' => ['pokemon' => $own],
                'equipo_rival' => ['pokemon' => $guest],
                'activo_jugador' => 0, 'activo_rival' => 0, 'lado_turno' => 'jugador',
            ];
            AbilityRules::enter($game, 'jugador');
            AbilityRules::enter($game, 'rival');
            if ($matched && count($own) === 1) $game['apuesta_hasta'] = time() + 15;
            $state = 'activa';
            $this->duelQuery('UPDATE busquedas_duelo SET alineacion=NULL, vence=? WHERE usuario_id IN (?,?)', [$now + 900, $retador, $invitado]);
        } elseif ($state === 'activa' && $action === 'iniciativa') {
            if ($this->duelWagerDeadline($game, $now) > $now) throw new \DomainException('Termina primero el acuerdo de apuesta.');
            $side = (int) $duel['retador'] === $uid ? 'jugador' : 'rival';
            BattleInitiative::confirm($game, $side);
            if (!empty($game['iniciativa']['orden']) && empty($game['iniciativa_aplicada'])) {
                $game['lado_turno'] = $game['iniciativa']['orden'][0];
                $game['iniciativa_aplicada'] = true;
            }
        } elseif ($state === 'activa' && $action === 'proponer_apuesta') {
            $now = time();
            if ($this->duelWagerDeadline($game, $now) <= $now) {
                throw new \DomainException('El plazo de la apuesta terminó.');
            }
            $amount = filter_var($input['monto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
            if ($amount === false) throw new \DomainException('Escribe un monto entero entre 0 y 1.000.000.');
            $choosing = $now >= $game['apuesta_hasta'];
            if ($choosing && $amount !== 0 && !in_array($amount, $game['propuestas'], true)) {
                throw new \DomainException('Elige uno de los dos montos propuestos.');
            }
            $players = [(int) $duel['retador'], (int) $duel['invitado']];
            sort($players);
            $balances = $this->duelQuery('SELECT usuario_id,monedas FROM carteras_usuario WHERE usuario_id IN (?,?) ORDER BY usuario_id FOR UPDATE', $players)->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($amount > ($balances[$uid] ?? 0)) throw new \DomainException('Tu saldo es insuficiente.');
            $votes = $choosing ? 'elecciones' : 'propuestas';
            $game[$votes][$uid] = $amount;
            $other = $players[0] === $uid ? $players[1] : $players[0];
            if ($amount === 0 || ($game[$votes][$other] ?? null) === $amount) {
                $game['apuesta_cerrada'] = true;
                if ($amount > 0 && $amount <= ($balances[$other] ?? 0)) {
                    foreach ($players as $player) {
                        $this->duelWallet($player, -$amount, 'duelo-entrada-' . $id, 'Apuesta en duelo individual', (int) $balances[$player]);
                    }
                    $game['aporte'] = $amount;
                } elseif ($amount > 0) {
                    $this->addHistory($game, 'Duelo sin apuesta por saldo insuficiente.');
                }
            }
        } elseif ($state === 'activa' && in_array($action, ['atacar', 'cambiar', 'rendirse'], true)) {
            if ($action !== 'rendirse' && $this->duelWagerDeadline($game, $now) > $now) {
                throw new \DomainException('Espera a que termine el acuerdo de apuesta.');
            }
            $side = (int) $duel['retador'] === $uid ? 'jugador' : 'rival';
            $other = $side === 'jugador' ? 'rival' : 'jugador';
            if ($action === 'rendirse') {
                $game['estado'] = 'finalizada';
                $game['resultado'] = $side === 'jugador' ? 'derrota' : 'victoria';
                $game['rendicion'] = true;
                $this->addHistory($game, 'Un entrenador se rindió.');
            } else {
                BattleInitiative::requireReady($game);
                if ($game['lado_turno'] !== $side) throw new \DomainException('Es el turno de tu rival.');
                $active = $game['activo_' . $side];
                if ($action === 'atacar') {
                    $move = $this->findMove($game['equipo_' . $side]['pokemon'][$active], Input::text($input['movimiento'] ?? '', 'Ataque', 80));
                    if (!$move) throw new \DomainException('Ese ataque no está disponible.');
                    $actions = [$this->attack($game, $side, $move)];
                    $damage = $actions[0]['danio'];
                    $this->appendFaintAndEntry($game, $actions, $other);
                } else {
                    $target = $this->positiveInteger($input['pokemon_id'] ?? null, 'pokemon_id');
                    $index = array_search($target, array_column($game['equipo_' . $side]['pokemon'], 'id'), true);
                    if ($index === false || $index === $active || $game['equipo_' . $side]['pokemon'][$index]['hp_actual'] <= 0) throw new \DomainException('Elige una reserva disponible.');
                    $game['equipo_' . $side]['pokemon'][$active]['cambios'] = [];
                    $game['activo_' . $side] = $index;
                    AbilityRules::enter($game, $side);
                    $this->addHistory($game, $game['equipo_' . $side]['pokemon'][$index]['nombre'] . ' entró al duelo.');
                }
                $game['ultima_accion'] = [
                    'numero' => ($game['ultima_accion']['numero'] ?? 0) + 1,
                    'lado' => $side,
                    'tipo' => $action,
                    'danio' => $damage ?? 0,
                    'elemento' => $move['tipo'] ?? '',
                ];
                $game['lado_turno'] = $other;
                if ($side === ($game['iniciativa']['orden'][1] ?? 'rival') && $game['estado'] === 'en_curso') {
                    $actions = [];
                    $this->endTurn($game, $actions);
                    $game['turno']++;
                }
            }
            if ($game['estado'] === 'finalizada') {
                $state = 'finalizada';
                if (!empty($game['aporte'])) {
                    $winner = (int) $duel[$game['resultado'] === 'victoria' ? 'retador' : 'invitado'];
                    $balance = (int) $this->duelQuery('SELECT monedas FROM carteras_usuario WHERE usuario_id=? FOR UPDATE', [$winner])->fetchColumn();
                    if ($action === 'rendirse') {
                        $this->duelWallet($winner, $game['aporte'], 'duelo-devolucion-' . $id, 'Devolución de apuesta por rendición del rival', $balance);
                    } else {
                        $this->duelWallet($winner, 2 * $game['aporte'], 'duelo-premio-' . $id, 'Premio del duelo individual', $balance);
                    }
                }
            }
        } else throw new \DomainException('Esa acción no está disponible.');
        $this->duelQuery('UPDATE duelos_jugadores SET estado=?,vence=?,datos=? WHERE id=?',
            [$state, $state === 'activa' && !empty($game['aporte']) ? 4294967295 : $now + ($state === 'activa' ? 900 : 120), json_encode($game, JSON_THROW_ON_ERROR), $id]);
    }

    private function duelWagerDeadline(array $game, int $now): int
    {
        if (!empty($game['apuesta_cerrada'])) return 0;
        $deadline = $game['apuesta_hasta'] ?? 0;
        $offers = array_values($game['propuestas'] ?? []);
        if ($deadline && count($offers) === 2 && min($offers) > 0 && $offers[0] !== $offers[1]) {
            return $deadline + ($now >= $deadline ? 10 : 0);
        }
        return $deadline;
    }

    private function duelWallet(int $uid, int $delta, string $key, string $label, int $balance): void
    {
        $this->duelQuery('UPDATE carteras_usuario SET monedas=? WHERE usuario_id=?', [$balance + $delta, $uid]);
        $this->duelQuery('INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)',
            [$uid, $key, $label, $delta, $balance + $delta]);
    }

    private function duelRoster(int $uid, int $team, mixed $lineup = null): array
    {
        if ($team === 0) {
            if (!is_array($lineup) || count($lineup) !== 1) throw new \DomainException('Elige un Pokémon para el duelo individual.');
            $pokemon = $this->positiveInteger(reset($lineup), 'Pokémon');
            $members = $this->duelQuery('SELECT pokemon_id FROM coleccion_pokemon WHERE usuario_id=? AND pokemon_id=?', [$uid, $pokemon])->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $this->team($team, $uid);
            $members = $this->members($team);
        }
        if (!$members) throw new \DomainException('El equipo necesita al menos un Pokémon.');
        $progress = new ProgressionService($this->conexion);
        $roster = array_values(array_filter(
            array_map(fn ($member) => $this->combatPokemon($progress->decorate($uid, MarketCatalog::get((int) $member['pokemon_id']))), $members),
            static fn ($pokemon) => $pokemon['movimientos'] !== [],
        ));
        if (!$roster) throw new \DomainException('Tu equipo necesita un Pokémon con ataques de daño.');
        if ($lineup !== null) {
            if (!is_array($lineup) || count($lineup) < 1 || count($lineup) > 6) {
                throw new \DomainException('Elige entre uno y seis Pokémon.');
            }
            $available = array_column($roster, null, 'id');
            $selected = [];
            foreach ($lineup as $id) {
                $id = $this->positiveInteger($id, 'Pokémon');
                if (!isset($available[$id]) || isset($selected[$id])) {
                    throw new \DomainException('Elige Pokémon distintos de tu equipo con ataques disponibles.');
                }
                $selected[$id] = $available[$id];
            }
            return array_values($selected);
        }
        return $roster;
    }
    public function __construct(
        private readonly PDO $conexion,

        private readonly SessionManager $sesion,
    ) {}

    public function start(): never
    {
        $usuarioId = $this->usuarioId();

        $this->sesion->start();

        $actual = $this->activeBattle($usuarioId);

        if (
            is_array($actual) &&
            ($actual['economia_revision'] ?? 0) === GameEconomy::REVISION &&
            ($actual['estado'] ?? null) === 'en_curso'
        ) {
            JsonResponse::error('Ya existe una batalla activa', 409);
        }

        $datos = JsonResponse::body();

        $modo = $datos['modo'] ?? 'equipos';

        if (!in_array($modo, ['equipos', 'rapido', 'apuesta'], true)) {
            JsonResponse::error('Selecciona un modo de batalla válido', 400);
        }

        $offer = null;

        if ($modo === 'apuesta') {
            try {
                $offer = (new WagerService($this->conexion))->offer(
                    $usuarioId,
                    Input::text($datos['oferta_id'] ?? '', 'Oferta', 24),
                );
            } catch (\InvalidArgumentException $e) {
                JsonResponse::error($e->getMessage(), 400);
            } catch (\DomainException $e) {
                JsonResponse::error($e->getMessage(), 409);
            }
        }

        $equipoId = $this->positiveInteger($datos['equipo_id'] ?? null, 'equipo_id');

        $equipo = $this->team($equipoId, $usuarioId);

        $miembros = $this->members($equipoId);

        // The chosen lead must belong to this user's selected team, even in a 1v1 duel.
        if (isset($datos['pokemon_id'])) {
            $lead = $this->positiveInteger($datos['pokemon_id'], 'pokemon_id');
            $index = array_search(
                $lead,
                array_map(static fn($m): int => (int) $m['pokemon_id'], $miembros),
                true,
            );
            if ($index === false) {
                JsonResponse::error('Elige un Pokémon que pertenezca a este equipo.', 400);
            }
            $first = $miembros[$index];
            array_splice($miembros, $index, 1);
            array_unshift($miembros, $first);
        }
        if (in_array($modo, ['rapido', 'apuesta'], true)) {
            $miembros = array_slice($miembros, 0, 1);
        }

        if ($miembros === []) {
            JsonResponse::error('El equipo debe tener al menos un Pokémon', 409);
        }

        $jugador = [];

        $jugadorIds = array_map(
            static fn(array $miembro): int => (int) $miembro['pokemon_id'],

            $miembros,
        );

        $game = new GameEconomy($this->conexion);

        foreach ($jugadorIds as $pokemonId) {
            try {
                $jugador[] = $game->applyCare(
                    $usuarioId,

                    $this->combatPokemon(
                        (new ProgressionService($this->conexion))->decorate(
                            $usuarioId,
                            MarketCatalog::get($pokemonId),
                        ),
                    ),
                );
            } catch (\DomainException $e) {
                JsonResponse::error($e->getMessage(), 409);
            }
        }

        $rival = [];
        if ($jugador[0]['movimientos'] === []) {
            JsonResponse::error(
                'Este compañero aún no conoce ataques de daño. Elige otro para comenzar y llévalo de reserva para ganar experiencia.',
                409,
            );
        }
        $rivalLevel = max(
            PokemonProgression::START_LEVEL,
            min(
                PokemonProgression::MAX_LEVEL,
                (int) round(array_sum(array_column($jugador, 'nivel')) / count($jugador)),
            ),
        );

        foreach (
            array_slice(
                $this->rivalIds(count($jugador), $jugadorIds, $rivalLevel),
                0,
                count($jugador),
            )
            as $pokemonId
        ) {
            $rival[] = $this->combatPokemon(
                PokemonProgression::atLevel(MarketCatalog::get($pokemonId), $rivalLevel),
            );
        }

        if (count($rival) < count($jugador)) {
            JsonResponse::error('No se pudo preparar el equipo rival.', 503);
        }

        if ($offer) {
            $factor = AdventureRules::difficulty((int) $offer['cantidad'])['factor'];

            foreach ($rival as &$opponent) {
                foreach ($opponent['estadisticas'] as &$stat) {
                    $stat = max(1, (int) round($stat * $factor));
                }

                unset($stat);

                $opponent['hp_actual'] = $opponent['hp_maximo'] = (int) round(
                    $opponent['hp_maximo'] * $factor,
                );
            }

            unset($opponent);
        }

        $id = bin2hex(random_bytes(8));

        $prepared = [
            'id' => $id,

            'usuario_id' => $usuarioId,

            'economia_revision' => GameEconomy::REVISION,

            'modo' => $modo,
            'iniciativa' => BattleInitiative::create(['jugador', 'rival'], ['rival']),

            'estado' => 'en_curso',

            'equipo_jugador' => [
                'id' => $equipo['id'],

                'nombre' => $equipo['nombre'],

                'pokemon' => $jugador,
            ],

            'equipo_rival' => [
                'pokemon' => $rival,
            ],

            'activo_jugador' => 0,

            'activo_rival' => 0,

            'turno' => 1,

            'resultado' => null,

            'historial' => [],
        ];

        // Entry effects run from faster to slower; the slower weather setter wins.
        $entryOrder =
            AbilityRules::speed($prepared['equipo_jugador']['pokemon'][0], []) >=
            AbilityRules::speed($prepared['equipo_rival']['pokemon'][0], [])
                ? ['jugador', 'rival']
                : ['rival', 'jugador'];
        foreach ($entryOrder as $side) {
            AbilityRules::enter($prepared, $side);
        }

        if ($offer) {
            try {
                (new WagerService($this->conexion))->reserve($usuarioId, $offer['id'], $prepared);
            } catch (\DomainException $e) {
                JsonResponse::error($e->getMessage(), 409);
            }
        }

        $_SESSION['batalla'] = $prepared;

        JsonResponse::send(
            [
                'success' => true,

                'batalla' => $this->state($_SESSION['batalla']),
            ],

            201,
        );
    }

    public function current(string $battleId): never
    {
        $batalla = $this->battle($battleId);

        JsonResponse::send([
            'success' => true,

            'batalla' => $this->state($batalla),
        ]);
    }

    public function active(): never
    {
        $this->usuarioId();

        $this->sesion->start();

        $batalla = $this->activeBattle($this->usuarioId());

        JsonResponse::send([
            'success' => true,

            'batalla' =>
                is_array($batalla) &&
                ($batalla['economia_revision'] ?? 0) === GameEconomy::REVISION &&
                ($batalla['usuario_id'] ?? 0) === $this->usuarioId() &&
                ($batalla['estado'] ?? null) === 'en_curso'
                    ? $this->state($batalla)
                    : null,
        ]);
    }

    public function duos(bool $write): never
    {
        $uid = $this->usuarioId();
        $service = new DuoService($this->conexion,
            fn($user,$team,$lineup) => $this->duelRoster($user,$team,$lineup),
            function ($attacker,$defender,$name) {
                if (!is_string($name)) throw new \DomainException('Elige un ataque válido.');
                $move = $this->findMove($attacker,$name);
                if (!$move) throw new \DomainException('Ese ataque no está disponible.');
                $pair = ['equipo_jugador'=>['pokemon'=>[$attacker]],'equipo_rival'=>['pokemon'=>[$defender]],'activo_jugador'=>0,'activo_rival'=>0,'estado'=>'en_curso','historial'=>[]];
                $action = $this->attack($pair,'jugador',$move);
                return ['atacante'=>$pair['equipo_jugador']['pokemon'][0],'defensor'=>$pair['equipo_rival']['pokemon'][0],
                    'mensaje'=>($action['tipo'] ?? '') === 'habilidad' ? $action['mensaje'] : $attacker['nombre'].' usó '.($move['nombre_mostrado'] ?? $move['nombre']).' · '.($action['danio'] ?? 0).' de daño.'];
            });
        try { JsonResponse::send($write ? $service->act($uid,JsonResponse::body()) : $service->state($uid)); }
        catch (\DomainException $error) { JsonResponse::error($error->getMessage(),409); }
    }

    public function initiative(string $battleId): never
    {
        $battle = $this->battle($battleId);
        if ($battle['estado'] !== 'en_curso') JsonResponse::error('La batalla ya finalizó.', 409);
        if (empty($battle['iniciativa']['orden'])) {
            BattleInitiative::confirm($battle, 'jugador');
            $this->saveBattle($battle);
        }
        JsonResponse::send(['success' => true, 'batalla' => $this->state($battle)]);
    }

    public function turn(string $battleId): never
    {
        $this->sesion->start();

        $batalla = $this->battle($battleId);
        try { BattleInitiative::requireReady($batalla); }
        catch (\DomainException $error) { JsonResponse::error($error->getMessage(), 409); }


        $body = JsonResponse::body();
        $rivalFirst = ($batalla['iniciativa']['orden'][0] ?? null) === 'rival';
        if (($body['rival'] ?? false) === true) {
            if (!$rivalFirst) JsonResponse::error('Es tu turno.', 409);
            if (($body['ronda'] ?? null) !== $batalla['turno'] || !empty($batalla['rival_actuo'])) {
                $this->soloResponse($batalla, []);
            }
            if ($batalla['estado'] !== 'en_curso') JsonResponse::error('La batalla ya finalizó', 409);
            $acciones = [];
            $this->soloRivalAttack($batalla, $acciones);
            $batalla['rival_actuo'] = true;
            $this->saveBattle($batalla);
            $this->soloResponse($batalla, $acciones);
        }
        if ($batalla['estado'] !== 'en_curso') JsonResponse::error('La batalla ya finalizó', 409);
        if ($rivalFirst && empty($batalla['rival_actuo'])) {
            JsonResponse::error('Es el turno de tu rival.', 409);
        }

        $jugador = $batalla['equipo_jugador']['pokemon'][$batalla['activo_jugador']];
        $movimiento = $this->findMove($jugador, (string) ($body['movimiento'] ?? ''));
        if ($movimiento === null) {
            JsonResponse::error('El movimiento no está disponible para el Pokémon activo', 400);
        }

        $acciones = [];
        $activoRival = $batalla['activo_rival'];
        $this->soloAttack($batalla, $acciones, 'jugador', $movimiento);
        $this->finishSoloRound($batalla, $acciones, $activoRival === $batalla['activo_rival']);
    }

    private function soloAttack(array &$batalla, array &$acciones, string $lado, array $movimiento): void
    {
        $action = $this->attack($batalla, $lado, $movimiento);
        $acciones[] = $action;
        array_push($acciones, ...$action['efectos'] ?? []);
        $this->appendFaintAndEntry($batalla, $acciones, $lado === 'jugador' ? 'rival' : 'jugador');
    }

    private function soloRivalAttack(array &$batalla, array &$acciones): void
    {
        $rival = $batalla['equipo_rival']['pokemon'][$batalla['activo_rival']];
        $movimientos = $rival['movimientos'] ?? [];
        if ($movimientos === []) {
            JsonResponse::error('El Pokémon rival no tiene movimientos disponibles', 502);
        }
        $this->soloAttack($batalla, $acciones, 'rival', $movimientos[array_rand($movimientos)]);
    }

    private function finishSoloRound(array &$batalla, array &$acciones, bool $rivalPuedeResponder = true): never
    {
        if ($rivalPuedeResponder && $batalla['estado'] === 'en_curso' && ($batalla['iniciativa']['orden'][0] ?? null) !== 'rival') {
            $this->soloRivalAttack($batalla, $acciones);
        }
        if ($batalla['estado'] === 'en_curso') {
            $this->endTurn($batalla, $acciones);
            $batalla['turno']++;
            unset($batalla['rival_actuo']);
        }
        $this->saveBattle($batalla);
        $this->soloResponse($batalla, $acciones);
    }

    private function soloResponse(array $batalla, array $acciones): never
    {
        JsonResponse::send([
            'success' => true,
            'acciones' => $acciones,
            'estado_final' => $this->state($batalla),
            'historial' => $batalla['historial'],
        ]);
    }

    public function switchPokemon(string $battleId): never
    {
        $this->sesion->start();

        $batalla = $this->battle($battleId);
        try { BattleInitiative::requireReady($batalla); }
        catch (\DomainException $error) { JsonResponse::error($error->getMessage(), 409); }


        if ($batalla['estado'] !== 'en_curso') {
            JsonResponse::error('La batalla ya finalizó', 409);
        }

        if (($batalla['iniciativa']['orden'][0] ?? null) === 'rival' && empty($batalla['rival_actuo'])) {
            JsonResponse::error('Es el turno de tu rival.', 409);
        }

        $pokemonId = $this->positiveInteger(
            JsonResponse::body()['pokemon_id'] ?? null,

            'pokemon_id',
        );

        $pokemon = &$batalla['equipo_jugador']['pokemon'];

        $actual = (int) $batalla['activo_jugador'];

        $siguiente = null;

        foreach ($pokemon as $indice => $item) {
            if ((int) $item['id'] === $pokemonId) {
                $siguiente = $indice;

                break;
            }
        }

        if ($siguiente === null) {
            JsonResponse::error('El Pokémon no pertenece a tu equipo', 400);
        }

        if ($siguiente === $actual) {
            JsonResponse::error('Ese Pokémon ya está activo', 400);
        }

        if ($pokemon[$siguiente]['derrotado'] || $pokemon[$siguiente]['hp_actual'] <= 0) {
            JsonResponse::error('No puedes cambiar a un Pokémon debilitado', 400);
        }

        $acciones = [
            [
                'tipo' => 'salida',

                'lado' => 'jugador',

                'pokemon_id' => $pokemon[$actual]['id'],

                'pokemon_nombre' => $pokemon[$actual]['nombre'],

                'mensaje' => $pokemon[$actual]['nombre'] . ' vuelve a su Poké Ball.',
            ],
        ];

        $batalla['equipo_jugador']['pokemon'][$actual]['cambios'] = [];
        $batalla['activo_jugador'] = $siguiente;

        $nuevo = $pokemon[$siguiente];

        $acciones[] = $this->entryAction('jugador', $nuevo);

        $this->addHistory($batalla, $nuevo['nombre'] . ' entró al combate.');
        array_push($acciones, ...AbilityRules::enter($batalla, 'jugador'));

        $this->finishSoloRound($batalla, $acciones);
    }

    public function abandon(string $battleId): never
    {
        $this->sesion->start();

        $batalla = $this->battle($battleId);

        if ($batalla['estado'] !== 'en_curso') {
            JsonResponse::error('La batalla ya finalizó', 409);
        }

        $batalla['estado'] = 'abandonada';

        $batalla['resultado'] = 'abandono';

        $this->saveBattle($batalla);

        JsonResponse::send([
            'success' => true,

            'batalla' => $this->state($batalla),
        ]);
    }

    private function activeBattle(int $uid): ?array
    {
        $persistent = (new WagerService($this->conexion))->active($uid);
        if ($persistent) {
            return $persistent;
        }
        $session = $_SESSION['batalla'] ?? null;
        // Closed persistent rounds never fall back to an older session snapshot.
        return ($session['modo'] ?? '') === 'apuesta' ? null : $session;
    }
    public function offers(): never
    {
        JsonResponse::send([
            'success' => true,
            'ofertas' => (new WagerService($this->conexion))->offers($this->usuarioId()),
            'hora_servidor' => time(),
        ]);
    }

    private function saveBattle(array &$battle): void
    {
        try {
            if (($battle['modo'] ?? '') === 'apuesta') {
                (new WagerService($this->conexion))->save($this->usuarioId(), $battle);
            } else {
                (new GameEconomy($this->conexion))->settle($this->usuarioId(), $battle);
            }

            $_SESSION['batalla'] = $battle;
        } catch (\DomainException $e) {
            JsonResponse::error($e->getMessage(), 409);
        }
    }

    private function attack(array &$batalla, string $lado, array $movimiento): array
    {
        if ($lado === 'jugador') {
            $atacante = &$batalla['equipo_jugador']['pokemon'][$batalla['activo_jugador']];
        } else {
            $atacante = &$batalla['equipo_rival']['pokemon'][$batalla['activo_rival']];
        }

        $defensorLado = $lado === 'jugador' ? 'rival' : 'jugador';

        if ($defensorLado === 'jugador') {
            $defensor = &$batalla['equipo_jugador']['pokemon'][$batalla['activo_jugador']];
        } else {
            $defensor = &$batalla['equipo_rival']['pokemon'][$batalla['activo_rival']];
        }

        if (AbilityRules::immobilized($atacante, random_int(1, 100))) {
            return AbilityRules::event(
                $batalla,
                $lado,
                $atacante['nombre'] . ' está paralizado y no puede moverse.',
            );
        }
        $types = $defensor['tipos'];
        if (
            AbilityRules::active($atacante) === 'scrappy' &&
            in_array($movimiento['tipo'], ['normal', 'fighting'], true)
        ) {
            $types = array_values(array_diff($types, ['ghost']));
        }
        $efectividad = BattleRules::effectiveness($movimiento['tipo'], $types);

        $hpAnterior = $defensor['hp_actual'];

        $danio = BattleRules::damage(
            $atacante,

            $defensor,

            $movimiento,

            $efectividad,

            random_int(85, 100),
            $batalla,
        );

        $defensor['hp_actual'] = max(0, $defensor['hp_actual'] - $danio);

        $debilitado = $defensor['hp_actual'] === 0;
        if ($lado === 'jugador') {
            $defensor['participantes'][$atacante['id']] = true;
            if ($debilitado && isset($atacante['nivel'])) {
                $recipients = [];
                foreach ($batalla['equipo_jugador']['pokemon'] as $member) {
                    if ($member['hp_actual'] > 0) {
                        $recipients[] = [
                            'id' => $member['id'],
                            'participo' => isset($defensor['participantes'][$member['id']]),
                        ];
                    }
                }
                $batalla['eventos_experiencia'][] = [
                    'id' => $defensor['id'],
                    'nivel' => $defensor['nivel'],
                    'indice' => $batalla['activo_rival'],
                    'receptores' => $recipients,
                ];
            }
        }

        $mensaje =
            $efectividad > 1
                ? '¡Es súper efectivo!'
                : ($efectividad > 0 && $efectividad < 1
                    ? 'No es muy efectivo...'
                    : ($efectividad === 0
                        ? 'No afecta al objetivo.'
                        : null));

        $this->addHistory(
            $batalla,

            $atacante['nombre'] .
                ' usó ' .
                ($movimiento['nombre_mostrado'] ?? $movimiento['nombre']) .
                '.',
        );

        $this->addHistory($batalla, $danio . ' puntos de daño.');
        $effects = [];
        $ability = AbilityRules::active($atacante);
        $boostType =
            ['blaze' => 'fire', 'overgrow' => 'grass', 'torrent' => 'water', 'swarm' => 'bug'][
                $ability
            ] ?? null;
        if (
            $danio > 0 &&
            (($boostType === $movimiento['tipo'] &&
                $atacante['hp_actual'] * 3 <= $atacante['hp_maximo']) ||
                ($ability === 'solar-power' &&
                    ($batalla['clima'] ?? '') === 'sol' &&
                    ($movimiento['clase'] ?? '') === 'especial'))
        ) {
            $effects[] = AbilityRules::event(
                $batalla,
                $lado,
                $atacante['nombre'] .
                    ': ' .
                    $atacante['habilidad_activa']['nombre'] .
                    ' potencia su ataque.',
            );
        }
        array_push(
            $effects,
            ...AbilityRules::contact(
                $batalla,
                $lado,
                $atacante,
                $defensor,
                $movimiento,
                $danio,
                random_int(1, 100),
            ),
        );

        return [
            'tipo' => 'ataque',
            'efectos' => $effects,

            'actor' => $lado,

            'pokemon_id' => $atacante['id'],

            'pokemon_nombre' => $atacante['nombre'],

            'movimiento' => $movimiento,

            'objetivo_id' => $defensor['id'],

            'danio' => $danio,

            'hp_anterior' => $hpAnterior,

            'hp_nuevo' => $defensor['hp_actual'],

            'hp_maximo' => $defensor['hp_maximo'],

            'efectividad' => $efectividad,

            'mensaje' => $mensaje,

            'debilitado' => $debilitado,
        ];
    }

    private function appendFaintAndEntry(array &$batalla, array &$acciones, string $lado): void
    {
        if ($lado === 'jugador') {
            $equipo = &$batalla['equipo_jugador']['pokemon'];
        } else {
            $equipo = &$batalla['equipo_rival']['pokemon'];
        }

        $indice =
            $lado === 'jugador' ? (int) $batalla['activo_jugador'] : (int) $batalla['activo_rival'];

        if ($equipo[$indice]['hp_actual'] > 0) {
            return;
        }

        if (!empty($equipo[$indice]['derrotado'])) {
            return;
        }
        if ($lado === 'rival' && isset($equipo[$indice]['nivel'])) {
            $recorded = array_column($batalla['eventos_experiencia'] ?? [], 'indice');
            if (!in_array($indice, $recorded, true)) {
                $recipients = [];
                foreach ($batalla['equipo_jugador']['pokemon'] as $member) {
                    if ($member['hp_actual'] > 0) {
                        $recipients[] = [
                            'id' => $member['id'],
                            'participo' => isset($equipo[$indice]['participantes'][$member['id']]),
                        ];
                    }
                }
                $batalla['eventos_experiencia'][] = [
                    'id' => $equipo[$indice]['id'],
                    'nivel' => $equipo[$indice]['nivel'],
                    'indice' => $indice,
                    'receptores' => $recipients,
                ];
            }
        }
        $equipo[$indice]['derrotado'] = true;
        $equipo[$indice]['cambios'] = [];

        $this->addHistory($batalla, $equipo[$indice]['nombre'] . ' quedó debilitado.');

        $acciones[] = [
            'tipo' => 'debilitamiento',

            'lado' => $lado,

            'pokemon_id' => $equipo[$indice]['id'],

            'mensaje' => $equipo[$indice]['nombre'] . ' quedó debilitado.',
        ];

        $siguiente = BattleRules::nextIndex($equipo, $indice);

        if ($siguiente === null) {
            $batalla['estado'] = 'finalizada';

            $batalla['resultado'] = $lado === 'jugador' ? 'derrota' : 'victoria';

            return;
        }

        if ($lado === 'jugador') {
            $batalla['activo_jugador'] = $siguiente;
        } else {
            $batalla['activo_rival'] = $siguiente;
        }

        $nuevo = $equipo[$siguiente];

        $acciones[] = $this->entryAction($lado, $nuevo);

        $this->addHistory($batalla, $nuevo['nombre'] . ' entró al combate.');
        array_push($acciones, ...AbilityRules::enter($batalla, $lado));
    }

    private function endTurn(array &$battle, array &$actions): void
    {
        array_push($actions, ...AbilityRules::endTurn($battle));
        // Resolve both losses before sending any replacements onto the field.
        $playerDown =
            $battle['equipo_jugador']['pokemon'][$battle['activo_jugador']]['hp_actual'] <= 0;
        $rivalDown = $battle['equipo_rival']['pokemon'][$battle['activo_rival']]['hp_actual'] <= 0;
        $playerOut =
            $playerDown &&
            BattleRules::nextIndex(
                $battle['equipo_jugador']['pokemon'],
                $battle['activo_jugador'],
            ) === null;
        $rivalOut =
            $rivalDown &&
            BattleRules::nextIndex($battle['equipo_rival']['pokemon'], $battle['activo_rival']) ===
                null;
        if ($rivalDown) {
            $this->appendFaintAndEntry($battle, $actions, 'rival');
        }
        if ($playerDown) {
            $this->appendFaintAndEntry($battle, $actions, 'jugador');
        }
        // The existing economy supports win/loss; simultaneous last-Pokémon KO is a loss.
        if ($playerOut && $rivalOut) {
            $battle['resultado'] = 'derrota';
        }
    }

    private function combatPokemon(array $pokemon): array
    {
        $stats = $pokemon['estadisticas'] ?? [];

        $movimientos = array_map(
            fn(array $movimiento): array => $this->movement($movimiento),

            array_slice(
                $pokemon['movimientos'] ?? ($pokemon['movimientos_disponibles'] ?? []),

                0,

                4,
            ),
        );

        $hp = isset($pokemon['nivel']) ? (int) $stats['hp'] : (int) ($stats['hp'] ?? 0) + 50;

        return [
            'id' => (int) $pokemon['id'],
            'nivel' => $pokemon['nivel'] ?? 50,

            'nombre' => $pokemon['nombre'],

            'tipos' => $pokemon['tipos'],
            'altura' => (float) ($pokemon['altura'] ?? 1),

            'estadisticas' => $stats,
            'habilidad_activa' => $pokemon['habilidad_activa'] ?? null,
            'estado_primario' => null,
            'cambios' => [],

            'movimientos' => $movimientos,

            'movimientos_disponibles' => $movimientos,

            'sprite_animado' => $pokemon['sprite_animado'] ?? null,

            'sprite_animado_hd' => $pokemon['sprite_animado_hd'] ?? null,
            'sprite_animado_hd_espalda' => $pokemon['sprite_animado_hd_espalda'] ?? null,

            'imagen_fallback' => $pokemon['imagen_fallback'] ?? null,

            'hp_actual' => $hp,

            'hp_maximo' => $hp,

            'derrotado' => false,
        ];
    }

    private function state(array $batalla): array
    {
        // Refresh visuals in recovered battles without changing combat or wager state.
        foreach (['equipo_jugador', 'equipo_rival'] as $team) {
            foreach ($batalla[$team]['pokemon'] as &$pokemon) {
                $media = MarketCatalog::get((int) $pokemon['id']);
                $pokemon['altura'] = (float) $media['altura'];
                $pokemon['sprite_animado_hd'] = $media['sprite_animado_hd'];
                $pokemon['sprite_animado_hd_espalda'] = $media['sprite_animado_hd_espalda'];
            }
            unset($pokemon);
        }
        return $batalla;
    }

    private function battle(string $id): array
    {
        $uid = $this->usuarioId();

        $this->sesion->start();

        $batalla =
            (new WagerService($this->conexion))->find($uid, $id) ?? ($_SESSION['batalla'] ?? null);

        if (
            !is_array($batalla) ||
            ($batalla['usuario_id'] ?? 0) !== $uid ||
            ($batalla['economia_revision'] ?? 0) !== GameEconomy::REVISION ||
            !hash_equals((string) ($batalla['id'] ?? ''), $id)
        ) {
            JsonResponse::error('Batalla no encontrada', 404);
        }

        return $batalla;
    }

    private function usuarioId(): int
    {
        $usuario = $this->sesion->user();

        if ($usuario === null) {
            JsonResponse::error('Usuario no autenticado', 401);
        }

        return $usuario['id'];
    }

    private function team(int $id, int $usuarioId): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT id, nombre FROM equipos WHERE id = :id AND usuario_id = :usuario_id',
        );

        $consulta->execute([':id' => $id, ':usuario_id' => $usuarioId]);

        $equipo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$equipo) {
            JsonResponse::error('Equipo no encontrado', 404);
        }

        return $equipo;
    }

    private function members(int $teamId): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT m.posicion, m.pokemon_id FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id JOIN coleccion_pokemon c ON c.usuario_id=e.usuario_id AND c.pokemon_id=m.pokemon_id WHERE m.equipo_id = :equipo_id ORDER BY m.posicion',
        );

        $consulta->execute([':equipo_id' => $teamId]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    private function rivalIds(
        int $count,
        array $playerIds,
        int $level = PokemonProgression::START_LEVEL,
    ): array {
        $all = MarketCatalog::all();
        $all = array_filter(
            $all,
            static fn(array $p): bool => PokemonProgression::available($p['id'], $level) !== [],
        );

        $average =
            array_sum(array_map(fn($id) => $all[$id]['poder_base'] ?? 300, $playerIds)) /
            max(1, count($playerIds));

        $poolControlado = array_keys(
            array_filter($all, fn($p) => abs($p['poder_base'] - $average) <= 65 && $p['id'] <= 151),
        );

        if (count($poolControlado) < $count + 2) {
            $poolControlado = array_keys(
                array_filter($all, fn($p) => abs($p['poder_base'] - $average) <= 100),
            );
        }

        $pool = array_values(array_diff($poolControlado, $playerIds));

        shuffle($pool);

        return $pool;
    }

    private function findMove(array $pokemon, string $name): ?array
    {
        $target = $this->moveName($name);

        foreach ($pokemon['movimientos'] as $move) {
            if ($this->moveName((string) $move['nombre']) === $target) {
                return $move;
            }
        }

        return null;
    }

    private function movement(array $movement): array
    {
        $name = strtolower(str_replace(' ', '-', trim((string) ($movement['nombre'] ?? ''))));

        $translations = [
            'thunder-punch' => 'Puño Trueno',

            'thunder-shock' => 'Impactrueno',

            'thunderbolt' => 'Rayo',

            'thunder' => 'Trueno',

            'fire-punch' => 'Puño Fuego',

            'water-gun' => 'Pistola Agua',

            'vine-whip' => 'Látigo Cepa',

            'razor-leaf' => 'Hoja Afilada',

            'solar-beam' => 'Rayo Solar',

            'mega-punch' => 'Megapuño',

            'scratch' => 'Arañazo',

            'tackle' => 'Placaje',

            'ember' => 'Ascuas',

            'flamethrower' => 'Lanzallamas',

            'fly' => 'Vuelo',

            'wing-attack' => 'Ataque Ala',

            'headbutt' => 'Golpe Cabeza',

            'cut' => 'Corte',

            'bind' => 'Atadura',

            'mega-drain' => 'Megaagotar',

            'pay-day' => 'Día de Pago',

            'slam' => 'Atizar',
        ];

        $movement['nombre'] = $name;

        $movement['nombre_mostrado'] =
            $movement['nombre_mostrado'] ??
            ($translations[$name] ?? ucfirst(str_replace('-', ' ', $name)));

        return AbilityRules::move($movement);
    }

    private function entryAction(string $lado, array $pokemon): array
    {
        return [
            'tipo' => 'entrada',

            'lado' => $lado,

            'pokemon_id' => $pokemon['id'],

            'pokemon_nombre' => $pokemon['nombre'],

            'hp_actual' => $pokemon['hp_actual'],

            'hp_maximo' => $pokemon['hp_maximo'],

            'sprite_animado' => $pokemon['sprite_animado'],

            'imagen_fallback' => $pokemon['imagen_fallback'],
        ];
    }

    private function moveName(string $name): string
    {
        return strtolower(str_replace(' ', '-', trim($name)));
    }

    private function addHistory(array &$batalla, string $texto): void
    {
        $batalla['historial'][] = $texto;

        $batalla['historial'] = array_slice($batalla['historial'], -50);
    }

    private function positiveInteger(mixed $value, string $name): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            JsonResponse::error("{$name} debe ser un entero positivo", 400);
        }

        return (int) $value;
    }
}
