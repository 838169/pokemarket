<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
require dirname(__DIR__) . '/tests/support/http-cookies.php';
use TrainerService\ExplorationService;
use TrainerService\ExplorationMap;
use TrainerService\GameEconomy;
$db = require dirname(__DIR__) . '/backend/trainer-service/config/database.php';
$uid = null;
$email = 'codex-exploration-' . bin2hex(random_bytes(8)) . '@example.invalid';
$checks = 0;
function verifyExp(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function rejectsExp(callable $f, string $message): void
{
    try {
        $f();
    } catch (DomainException | InvalidArgumentException $e) {
        verifyExp(true, $message);
        return;
    }
    throw new RuntimeException($message);
}
function payloadExp(array $s, array $extra = []): array
{
    return $extra + ['version' => $s['version'], 'solicitud' => bin2hex(random_bytes(16))];
}
function editExp(PDO $db, int $uid, callable $edit): array
{
    // Test-only account fixture; never modifies another user's state.
    $q = $db->prepare('SELECT datos FROM progreso_exploracion WHERE usuario_id=?');
    $q->execute([$uid]);
    $s = json_decode($q->fetchColumn(), true);
    $previousBalls = $s['pokeballs'];
    $previousTypes = $s['bolas'] ?? null;
    $edit($s);
    if (
        isset($s['bolas']) &&
        $s['pokeballs'] !== $previousBalls &&
        $s['bolas'] === $previousTypes
    ) {
        $s['bolas'] = ['poke' => $s['pokeballs'], 'super' => 0, 'ultra' => 0];
    }
    $q = $db->prepare('UPDATE progreso_exploracion SET datos=? WHERE usuario_id=?');
    $q->execute([json_encode($s), $uid]);
    return $s;
}
function approachExp(ExplorationService $game, PDO $db, int $uid): array
{
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
        $s['posicion'] = array_map(
            'intval',
            array_map(
                'round',
                TrainerService\WildlifeRules::position($s['avistamiento'], microtime(true)),
            ),
        );
    });
    return $game->act(
        $uid,
        'observe',
        payloadExp($s, ['avistamiento' => $s['avistamiento']['id']]),
    )['exploracion'];
}
function visitExp(ExplorationService $game, PDO $db, int $uid, array $s, int $i): array
{
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
        $s['encuentro'] = null;
        $s['enfriamientos'] = [];
        $s['tiempo_ms'] = 60000;
        $s['pasos_desde'] = 8;
        $s['rastreo'] = 2;
        $s['huellas_recientes'] = [];
    });
    [$x, $y] = ExplorationMap::SITES[$i];
    $s = editExp($db, $uid, function (&$s) use ($x, $y) {
        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
            if (ExplorationMap::walkable($x + $dx, $y + $dy)) {
                $s['posicion'] = [$x + $dx, $y + $dy];
                break;
            }
        }
    });
    $r = $game->act($uid, 'travel', payloadExp($s, ['x' => $x, 'y' => $y]));
    verifyExp(
        $r['resultado']['tipo'] === 'avistamiento',
        'Pokémon móvil por rastreo y tirada del servidor',
    );
    return approachExp($game, $db, $uid);
}
try {
    $q = $db->prepare('INSERT INTO usuarios(usuario,nombre,email,password) VALUES(?,?,?,?)');
    $password = bin2hex(random_bytes(16));
    $q->execute([bin2hex(random_bytes(8)), 'Prueba de exploración', $email, password_hash($password, PASSWORD_DEFAULT)]);
    $uid = (int) $db->lastInsertId();
    $game = new ExplorationService($db, fn() => 1, fn() => 1); // Server-only deterministic test rolls.
    $economy = new GameEconomy($db);
    $s = $game->state($uid)['exploracion'];
    verifyExp(
        $s['posicion'] === ExplorationMap::START && $s['pokeballs'] === 0,
        'Inicio sin inventario regalado',
    );
    verifyExp(
        $s['tiempo_ms'] === 60000 && count($s['misiones']) === 3,
        'Primer minuto y misiones disponibles',
    );
    $campRequest = payloadExp($s, ['mision' => 'campamento', 'segundos' => 99999]);
    $camp = $game->act($uid, 'mission', $campRequest);
    $s = $camp['exploracion'];
    verifyExp($s['tiempo_ms'] === 80000, 'Misión diaria concede solo veinte segundos autorizados');
    $repeat = $game->act($uid, 'mission', $campRequest);
    verifyExp($repeat['exploracion']['tiempo_ms'] === 80000, 'Reintentar misión no duplica tiempo');
    rejectsExp(
        fn() => $game->act($uid, 'mission', payloadExp($s, ['mision' => 'campamento'])),
        'No cobrar la preparación dos veces el mismo día',
    );
    rejectsExp(
        fn() => $game->act($uid, 'mission', payloadExp($s, ['mision' => 'batalla'])),
        'Sin batalla real no hay recompensa',
    );
    rejectsExp(
        fn() => $game->act($uid, 'mission', payloadExp($s, ['mision' => 'inventada'])),
        'No aceptar misiones inventadas',
    );
    rejectsExp(
        fn() => $game->act($uid, 'supplies', payloadExp($s)),
        'Sin saldo no se compran suministros',
    );
    verifyExp(
        $game->state($uid)['exploracion']['pokeballs'] === 0,
        'Compra rechazada no entrega inventario',
    );
    verifyExp(
        $game->state($uid)['mapa']['puntos'] === [],
        'API no revela coordenadas garantizadas',
    );
    $economy->completeWelcome($uid);
    $request = payloadExp($s);
    $r = $game->act($uid, 'supplies', $request);
    $s = $r['exploracion'];
    verifyExp(
        $r['cartera']['monedas'] === 880 && $s['pokeballs'] === 3,
        'Cobro correcto de 120 por 3',
    );
    $r = $game->act($uid, 'supplies', $request);
    verifyExp(
        $r['cartera']['monedas'] === 880 && $r['exploracion']['pokeballs'] === 3,
        'Reintento no cobra ni entrega dos veces',
    );
    rejectsExp(
        fn() => $game->act($uid, 'supplies', payloadExp(['version' => 1])),
        'Versión antigua rechazada',
    );
    rejectsExp(
        fn() => $game->act($uid, 'travel', payloadExp($s, ['x' => 0, 'y' => 0])),
        'Agua bloqueada en servidor',
    );
    rejectsExp(
        fn() => $game->act($uid, 'travel', payloadExp($s, ['x' => '7', 'y' => 9])),
        'Coordenadas con tipo incorrecto rechazadas',
    );
    $r = $game->act($uid, 'tutorial', payloadExp($s));
    $s = $r['exploracion'];
    verifyExp($s['tutorial'], 'Guía persistida');
    $walkBefore = $s['tiempo_ms'];
    $r = $game->act(
        $uid,
        'travel',
        payloadExp($s, ['x' => 6, 'y' => 12, 'resistencia' => 9999, 'probabilidad' => 100]),
    );
    $s = $r['exploracion'];
    verifyExp(
        $s['tiempo_ms'] === $walkBefore - ExplorationMap::WALK_MS && !$s['encuentro'],
        'Servidor calcula resistencia e ignora probabilidades del cliente',
    );
    $previousArrival = $s['listo_en'];
    rejectsExp(
        fn() => $game->act($uid, 'travel', payloadExp($s, [
            'x' => 7, 'y' => 12, 'compacto' => true, 'camino' => [[9, 12], [7, 12]],
        ])),
        'El movimiento agrupado rechaza saltos entre casillas',
    );
    $stream = $game->act($uid, 'travel', payloadExp($s, [
        'x' => 7, 'y' => 12, 'compacto' => true, 'camino' => [[7, 12]],
    ]));
    verifyExp(
        !isset($stream['cartera']) && !isset($stream['exploracion']['album']) &&
            isset($stream['exploracion']['misiones']),
        'El viaje compacto conserva misiones y evita leer cartera y álbum',
    );
    $s = $stream['exploracion'];
    verifyExp(
        $s['listo_en'] >= $previousArrival + ExplorationMap::WALK_MS / 1000 - 0.001,
        'Preparar el siguiente tramo conserva todo el tiempo de viaje',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
        $s['tiempo_ms'] = 0;
    });
    rejectsExp(
        fn() => $game->act($uid, 'travel', payloadExp($s, ['x' => 8, 'y' => 12])),
        'Agotado no avanza fuera del campamento',
    );
    $r = $game->act($uid, 'travel', payloadExp($s, ['x' => 5, 'y' => 12]));
    verifyExp(
        $r['exploracion']['tiempo_ms'] === 0 && !$r['exploracion']['encuentro'],
        'Regreso seguro no exige ni regala tiempo',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
    });
    [$x, $y] = ExplorationMap::SITES[0];
    $s = editExp($db, $uid, function (&$s) use ($x, $y) {
        $s['posicion'] = [$x - 1, $y];
        $s['tiempo_ms'] = 60000;
        $s['pasos_desde'] = 8;
        $s['rastreo'] = 2;
        $s['huellas_recientes'] = [];
    });
    $r = $game->act($uid, 'travel', payloadExp($s, ['x' => $x, 'y' => $y]));
    $s = $r['exploracion'];
    verifyExp(
        $r['resultado']['tipo'] === 'avistamiento' && count($s['descubiertos']) === 1,
        'Descubrimiento guardado',
    );
    verifyExp(
        $game->state($uid)['exploracion']['avistamiento']['id'] === $s['avistamiento']['id'],
        'Encuentro recuperable tras recarga',
    );
    rejectsExp(
        fn() => $game->act(
            $uid,
            'observe',
            payloadExp($s, ['avistamiento' => $s['avistamiento']['id']]),
        ),
        'No interactuar antes de llegar',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
    });
    $s = editExp($db, $uid, function (&$s) {
        $s['posicion'] = [42, 28];
    });
    rejectsExp(
        fn() => $game->act(
            $uid,
            'observe',
            payloadExp($s, ['avistamiento' => $s['avistamiento']['id']]),
        ),
        'No interactuar con un Pokémon remoto',
    );
    rejectsExp(
        fn() => $game->act($uid, 'observe', payloadExp($s, ['avistamiento' => 'inventado'])),
        'No falsificar el avistamiento',
    );
    $ballsBefore = $s['pokeballs'];
    $s = approachExp($game, $db, $uid);
    verifyExp(
        $s['avistamiento'] === null && $s['encuentro'] && $s['pokeballs'] === $ballsBefore,
        'Acercarse abre un encuentro sin consumir Poké Balls',
    );
    rejectsExp(
        fn() => $game->act($uid, 'capture', payloadExp($s, ['encuentro' => 'inventado'])),
        'No aceptar encuentro falsificado',
    );
    $capture = payloadExp($s, ['encuentro' => $s['encuentro']['id']]);
    $pid = $s['encuentro']['pokemon']['id'];
    $r = $game->act($uid, 'capture', $capture);
    $s = $r['exploracion'];
    verifyExp(
        $r['resultado']['tipo'] === 'captura' && $s['pokeballs'] === 2 && $s['capturas_hoy'] === 1,
        'Captura consume una Poké Ball',
    );
    verifyExp(
        in_array($pid, $s['estampas'], true) &&
            !in_array($pid, $r['cartera']['pokemon_ids'], true) &&
            $r['cartera']['monedas'] === 880,
        'Captura pega una estampa sin añadir Pokémon al Hogar ni cobrar monedas',
    );
    $q = $db->prepare(
        'SELECT COUNT(*) FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id WHERE e.usuario_id=? AND m.pokemon_id=?',
    );
    $q->execute([$uid, $pid]);
    verifyExp((int) $q->fetchColumn() === 0, 'Una estampa no crea equipo');
    $r = $game->act($uid, 'capture', $capture);
    verifyExp(
        $r['exploracion']['pokeballs'] === 2 && $r['exploracion']['capturas_hoy'] === 1,
        'Captura idempotente',
    );
    $q = $db->prepare('SELECT COUNT(*) FROM cuidado_pokemon WHERE usuario_id=? AND pokemon_id=?');
    $q->execute([$uid, $pid]);
    verifyExp((int) $q->fetchColumn() === 0, 'Una estampa no crea cuidado ni propiedad');
    $s = visitExp($game, $db, $uid, $s, 1);
    verifyExp(abs($s['encuentro']['vence'] - $s['encuentro']['inicio'] - 60) < 0.001, 'El encuentro dura un minuto');
    verifyExp($s['encuentro']['pokemon']['id'] !== $pid, 'No generar especies ya adquiridas');
    $failed = new ExplorationService($db, fn() => 100);
    $s = editExp($db, $uid, function (&$s) {
        $s['pokeballs'] = 3;
    });
    for ($i = 0; $i < 3; $i++) {
        $oddsBefore = $s['encuentro']['probabilidad'];
        $r = $failed->act($uid, 'capture', payloadExp($s, ['encuentro' => $s['encuentro']['id']]));
        $s = $r['exploracion'];
        verifyExp(
            $r['resultado']['tipo'] === ($i < 2 ? 'fallo' : 'huida'),
            'Fallo y huida controlados',
        );
        if ($i < 2) {
            verifyExp(
                $s['encuentro']['probabilidad'] === $oddsBefore,
                'Los intentos conservan la probabilidad de su rareza',
            );
        }
    }
    verifyExp(
        $s['pokeballs'] === 0 && !$s['encuentro'],
        'Tres fallos consumen tres bolas y cierran encuentro',
    );
    $s = visitExp($game, $db, $uid, $s, 2);
    rejectsExp(
        fn() => $game->act($uid, 'capture', payloadExp($s, ['encuentro' => $s['encuentro']['id']])),
        'Sin Poké Balls no se captura',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['pokeballs'] = 3;
        $s['capturas_hoy'] = 5;
    });
    $r = $game->act($uid, 'leave', payloadExp($s, ['encuentro' => $s['encuentro']['id']]));
    $s = $r['exploracion'];
    verifyExp(!$s['encuentro'] && $s['pokeballs'] === 3, 'Retirada gratuita');
    $s = visitExp($game, $db, $uid, $s, 2);
    $r = $game->act($uid, 'capture', payloadExp($s, ['encuentro' => $s['encuentro']['id']]));
    $s = $r['exploracion'];
    verifyExp($r['resultado']['tipo'] === 'captura' && $s['capturas_hoy'] === 6, 'Permite una sexta captura el mismo día');
    $r = $game->act(
        $uid,
        'travel',
        payloadExp($s, ['x' => ExplorationMap::SITES[2][0], 'y' => ExplorationMap::SITES[2][1]]),
    );
    verifyExp(!$r['exploracion']['encuentro'], 'Cooldown impide generar otro encuentro inmediato');
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
        $s['avistamiento'] = TrainerService\WildlifeRules::create(
            ExplorationMap::species(19),
            [12, 13],
            microtime(true),
            false,
        );
        $s['posicion'] = array_map(
            'intval',
            array_map(
                'round',
                TrainerService\WildlifeRules::position($s['avistamiento'], microtime(true)),
            ),
        );
        $s['tiempo_ms'] = 60000;
    });
    $destination = null;
    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
        if (ExplorationMap::walkable($s['posicion'][0] + $dx, $s['posicion'][1] + $dy)) {
            $destination = [$s['posicion'][0] + $dx, $s['posicion'][1] + $dy];
            break;
        }
    }
    $r = $game->act(
        $uid,
        'travel',
        payloadExp($s, ['x' => $destination[0], 'y' => $destination[1], 'correr' => true]),
    );
    verifyExp(
        $r['resultado']['tipo'] === 'espantado' &&
            $r['exploracion']['avistamiento']['modo'] === 'huyendo',
        'Correr cerca provoca una huida visible en servidor',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
    });
    rejectsExp(
        fn() => $game->act(
            $uid,
            'observe',
            payloadExp($s, ['avistamiento' => $s['avistamiento']['id']]),
        ),
        'No capturar al Pokémon que ya está huyendo',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['avistamiento']['vence'] = time() - 1;
    });
    verifyExp(
        $game->state($uid)['exploracion']['avistamiento'] === null,
        'Avistamiento vencido no vuelve a aparecer al recargar',
    );
    // Real HTTP checks use only this synthetic account and an in-memory cookie jar.
    $cookie = '';
    $csrf = '';
    $http = function (string $path, string $method = 'GET', ?array $body = null) use (
        &$cookie,
        &$csrf,
    ): array {
        $ch = curl_init('http://localhost:8002/trainer-api/v1' . $path);
        $headers = ['Content-Type: application/json'];
        if ($cookie) {
            $headers[] = 'Cookie: ' . $cookie;
        }
        if ($csrf) {
            $headers[] = 'X-CSRF-Token: ' . $csrf;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$cookie) {
                $cookie = integrationCookie($cookie, $h);
                return strlen($h);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException(curl_error($ch));
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
    };
    [$code] = $http('/exploration');
    verifyExp($code === 401, 'Explorar requiere sesión');
    [$code] = $http('/auth/login', 'POST', ['email' => $email, 'password' => $password]);
    verifyExp($code === 200, 'Login de cuenta de prueba');
    [$code] = $http('/exploration/supplies', 'POST', []);
    verifyExp($code === 403, 'Mutaciones requieren CSRF');
    [, $session] = $http('/auth/me');
    $csrf = $session['csrf_token'];
    [$code, $r] = $http('/exploration');
    verifyExp(
        $code === 200 && $r['mapa']['nombre'] === 'Pradera del Alba',
        'Mapa disponible por API',
    );
    [$code] = $http(
        '/exploration/travel',
        'POST',
        payloadExp($r['exploracion'], ['x' => -1, 'y' => 0]),
    );
    verifyExp($code === 400, 'API rechaza coordenadas inválidas');
    [$code, $r] = $http('/exploration/supplies', 'POST', payloadExp($r['exploracion']));
    verifyExp($code === 200 && $r['cartera']['monedas'] === 760, 'Compra real por API');
    $stateForMission = $r['exploracion'];
    $db->prepare(
        'INSERT INTO movimientos_monedas(usuario_id,clave,concepto,cantidad,saldo) VALUES(?,?,?,?,?)',
    )->execute([$uid, 'batalla-test-mision', 'Batalla completada', 0, 760]);
    $missionRequest = payloadExp($stateForMission, ['mision' => 'batalla', 'segundos' => 9999]);
    [$code, $rewarded] = $http('/exploration/mission', 'POST', $missionRequest);
    verifyExp(
        $code === 200 &&
            $rewarded['exploracion']['tiempo_ms'] === $stateForMission['tiempo_ms'] + 60000,
        'API verifica batalla y suma un minuto',
    );
    [$code, $replayedMission] = $http('/exploration/mission', 'POST', $missionRequest);
    verifyExp(
        $code === 200 &&
            $replayedMission['exploracion']['tiempo_ms'] === $rewarded['exploracion']['tiempo_ms'],
        'Reintento HTTP no duplica recompensa',
    );
    [$code] = $http(
        '/exploration/mission',
        'POST',
        payloadExp($replayedMission['exploracion'], ['mision' => 'batalla']),
    );
    verifyExp($code === 409, 'La misma batalla no concede más tiempo');
    $s = editExp($db, $uid, function (&$s) {
        $s['listo_en'] = 0;
        $s['encuentro'] = null;
        $s['avistamiento'] = TrainerService\WildlifeRules::create(
            ExplorationMap::species(10),
            [12, 13],
            microtime(true),
            false,
        );
        $s['posicion'] = array_map(
            'intval',
            array_map(
                'round',
                TrainerService\WildlifeRules::position($s['avistamiento'], microtime(true)),
            ),
        );
    });
    [, $r] = $http('/exploration');
    $observeRequest = payloadExp($r['exploracion'], [
        'avistamiento' => $r['exploracion']['avistamiento']['id'],
    ]);
    [$code, $observed] = $http('/exploration/observe', 'POST', $observeRequest);
    verifyExp(
        $code === 200 &&
            $observed['exploracion']['encuentro'] &&
            !$observed['exploracion']['avistamiento'],
        'API de aproximación abre el encuentro real',
    );
    [$code, $replayed] = $http('/exploration/observe', 'POST', $observeRequest);
    verifyExp(
        $code === 200 &&
            $replayed['exploracion']['encuentro']['id'] ===
                $observed['exploracion']['encuentro']['id'],
        'Reintentar aproximación no duplica encuentros',
    );
    [$code, $r] = $http(
        '/exploration/leave',
        'POST',
        payloadExp($replayed['exploracion'], [
            'encuentro' => $replayed['exploracion']['encuentro']['id'],
        ]),
    );
    verifyExp(
        $code === 200 && $r['cartera']['monedas'] === 760,
        'Interacción y retirada no cobran monedas',
    );
    $s = $r['exploracion'];
    rejectsExp(
        fn() => $game->act(
            $uid,
            'travel',
            payloadExp($s, ['x' => 5, 'y' => 12, 'correr' => 'yes']),
        ),
        'No se acepta una velocidad manipulada',
    );
    $r = $game->act($uid, 'travel', payloadExp($s, ['x' => 5, 'y' => 12, 'correr' => true]));
    verifyExp(
        $r['resultado']['paso_ms'] === ExplorationMap::RUN_MS &&
            ExplorationMap::RUN_MS < ExplorationMap::WALK_MS,
        'El servidor autoriza el ritmo de carrera',
    );
    verifyExp(
        $r['exploracion']['listo_en'] > microtime(true),
        'Correr conserva la validación de tiempo de llegada',
    );
    $categories = ExplorationMap::albumCategories();
    $s = editExp($db, $uid, function (&$s) {
        $s['encuentro'] = null;
        $s['listo_en'] = 0;
        $s['estampas'] = [];
        $s['recompensas_categorias'] = [];
        $s['bolas'] = ['poke' => 0, 'super' => 0, 'ultra' => 0];
        $s['pokeballs'] = 0;
    });
    verifyExp(count(array_unique(array_merge(...array_column($categories, 'especies')))) === 100, 'Las categorías cubren las cien estampas');
    rejectsExp(fn() => $game->act($uid, 'album', payloadExp($s, ['categoria' => 1])), 'No reclamar categoría incompleta');
    rejectsExp(fn() => $game->act($uid, 'album', payloadExp($s, ['categoria' => '1'])), 'Rechaza categoría no entera');
    rejectsExp(fn() => $game->act($uid, 'album', payloadExp($s, ['hoja' => 0, 'pokemon_id' => 150])), 'El formato antiguo no entrega compañeros por hoja');
    foreach ($categories as $category) {
        $s = editExp($db, $uid, function (&$s) use ($category) {
            $s['estampas'] = array_values(array_unique(array_merge($s['estampas'], $category['especies'])));
            $s['bolas'] = ['poke' => 30, 'super' => 0, 'ultra' => 0];
            $s['pokeballs'] = 30;
        });
        $before = $game->state($uid)['cartera'];
        if (array_sum($category['bolas'])) {
            rejectsExp(fn() => $game->act($uid, 'album', payloadExp($s, ['categoria' => $category['id']])), 'Mochila llena conserva el premio pendiente');
            verifyExp($game->state($uid)['cartera']['monedas'] === $before['monedas'], 'Un premio bloqueado no entrega monedas parciales');
        }
        $s = editExp($db, $uid, function (&$s) { $s['bolas']['poke'] = $s['pokeballs'] = 0; });
        $claim = payloadExp($s, ['categoria' => $category['id']]);
        $reward = $game->act($uid, 'album', $claim);
        verifyExp($reward['cartera']['monedas'] === $before['monedas'] + $category['monedas'], 'Monedas de categoría correctas');
        verifyExp($reward['cartera']['tarjetas_cambio_usuario'] === $before['tarjetas_cambio_usuario'] + $category['tarjetas'], 'Tarjetas de categoría correctas');
        verifyExp($reward['exploracion']['categorias_album'][$category['id'] - 1]['reclamada'], 'Categoría marcada como reclamada');
        foreach ($category['bolas'] as $type => $quantity) verifyExp($reward['exploracion']['bolas'][$type] === $quantity, 'Bolas del premio disponibles');
        foreach ($category['comida'] as $type => $quantity) verifyExp($reward['cartera']['comida'][$type] === $before['comida'][$type] + $quantity, 'Alimento del premio disponible');
        $replay = $game->act($uid, 'album', $claim);
        verifyExp($replay['cartera']['monedas'] === $reward['cartera']['monedas'], 'Reintento idempotente no duplica monedas');
        verifyExp($replay['cartera']['tarjetas_cambio_usuario'] === $reward['cartera']['tarjetas_cambio_usuario'], 'Reintento no duplica tarjetas');
        rejectsExp(fn() => $game->act($uid, 'album', payloadExp($replay['exploracion'], ['categoria' => $category['id']])), 'No reclamar categoría dos veces');
    }
    verifyExp(in_array(384, $reward['cartera']['pokemon_ids'], true), 'Leyendas entrega a Rayquaza');
    $progression = new TrainerService\ProgressionService($db);
    verifyExp($progression->profile($uid, 384)['nivel'] === 2, 'Rayquaza empieza a nivel dos');
    $higherExperience = TrainerService\PokemonProgression::threshold(384, 5);
    $db->prepare('UPDATE progreso_pokemon SET experiencia=? WHERE usuario_id=? AND pokemon_id=384')->execute([$higherExperience, $uid]);
    $s = editExp($db, $uid, function (&$s) { $s['recompensas_categorias'] = [1, 2, 3]; });
    $db->prepare("DELETE FROM movimientos_monedas WHERE usuario_id=? AND clave='cuaderno-categoria-4'")->execute([$uid]);
    $ownedReward = $game->act($uid, 'album', payloadExp($s, ['categoria' => 4]));
    verifyExp(in_array(384, $ownedReward['cartera']['pokemon_ids'], true), 'Tener a Rayquaza no bloquea el premio de Leyendas');
    verifyExp($progression->profile($uid, 384)['experiencia'] === $higherExperience, 'El premio conserva el progreso de un Rayquaza de nivel superior');
    $q = $db->prepare('SELECT COUNT(*) FROM cuidado_pokemon WHERE usuario_id=? AND pokemon_id=384');
    $q->execute([$uid]);
    verifyExp((int) $q->fetchColumn() === 1, 'No duplica el cuidado de un legendario existente');
    $s = editExp($db, $uid, function (&$s) { $s['estampas'] = []; });
    $db->prepare('UPDATE carteras_usuario SET monedas=10000 WHERE usuario_id=?')->execute([$uid]);
    $s = editExp($db, $uid, function (&$s) {
        $s['encuentro'] = null;
        $s['listo_en'] = 0;
        $s['pokeballs'] = 0;
        $s['bolas'] = ['poke' => 0, 'super' => 0, 'ultra' => 0];
        $s['paquetes_hoy'] = 0;
    });
    rejectsExp(
        fn() => $game->act($uid, 'supplies', payloadExp($s, ['tipo' => 'master', 'cantidad' => 3])),
        'No inventar tipos de Poké Ball',
    );
    rejectsExp(
        fn() => $game->act($uid, 'supplies', payloadExp($s, ['tipo' => 'poke', 'cantidad' => 4])),
        'Solo paquetes de tres o seis',
    );
    $purchase = payloadExp($s, ['tipo' => 'super', 'cantidad' => 6, 'precio' => 1]);
    $r = $game->act($uid, 'supplies', $purchase);
    $s = $r['exploracion'];
    verifyExp(
        $s['bolas']['super'] === 6 && $s['pokeballs'] === 6 && $r['cartera']['monedas'] === 9580,
        'El servidor cobra seis Super Balls al precio real',
    );
    $repeat = $game->act($uid, 'supplies', $purchase);
    verifyExp(
        $repeat['exploracion']['pokeballs'] === 6 && $repeat['cartera']['monedas'] === 9580,
        'Reintentar paquete de seis no duplica compra',
    );
    $r = $game->act($uid, 'supplies', payloadExp($s, ['tipo' => 'ultra', 'cantidad' => 6]));
    $s = $r['exploracion'];
    $r = $game->act($uid, 'supplies', payloadExp($s, ['tipo' => 'poke', 'cantidad' => 3]));
    $s = $r['exploracion'];
    verifyExp(
        $s['pokeballs'] === 15 && $s['paquetes_hoy'] === 5 && $r['cartera']['monedas'] === 8800,
        'El cupo diario combina los tres tipos y ambos paquetes',
    );
    rejectsExp(
        fn() => $game->act($uid, 'supplies', payloadExp($s)),
        'No superar quince Poké Balls diarias',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['dia'] = '2000-01-01';
    });
    $s = $game->state($uid)['exploracion'];
    verifyExp(
        $s['paquetes_hoy'] === 0 && $s['pokeballs'] === 15,
        'Al cambiar de día vuelve el cupo sin perder inventario',
    );
    $s = editExp($db, $uid, function (&$s) {
        $s['pokeballs'] = 30;
        $s['bolas'] = ['poke' => 18, 'super' => 6, 'ultra' => 6];
        $s['paquetes_hoy'] = 0;
    });
    rejectsExp(
        fn() => $game->act($uid, 'supplies', payloadExp($s)),
        'La capacidad de treinta se valida en servidor',
    );
    $newId = array_values(
        array_filter(
            ExplorationMap::POOL,
            fn($id) => ExplorationMap::rarityLevel($id) === 1 &&
                !in_array($id, $s['estampas'], true),
        ),
    )[0];
    $s = editExp($db, $uid, function (&$s) use ($newId) {
        $s['capturas_hoy'] = 0;
        $s['encuentro'] = [
            'id' => bin2hex(random_bytes(12)),
            'pokemon' => ExplorationMap::species($newId),
            'probabilidad' => 7.17,
            'intentos' => 0,
            'vence' => time() + 180,
        ];
    });
    $fixedRoll = new ExplorationService($db, fn() => 8);
    $r = $fixedRoll->act(
        $uid,
        'capture',
        payloadExp($s, ['encuentro' => $s['encuentro']['id'], 'bola' => 'poke', 'factor' => 999]),
    );
    verifyExp(
        $r['resultado']['tipo'] === 'fallo' && $r['exploracion']['bolas']['poke'] === 17,
        'El cliente no puede inventar efectividad',
    );
    $s = $r['exploracion'];
    $r = $fixedRoll->act(
        $uid,
        'capture',
        payloadExp($s, ['encuentro' => $s['encuentro']['id'], 'bola' => 'super']),
    );
    verifyExp(
        $r['resultado']['tipo'] === 'captura' && $r['exploracion']['bolas']['super'] === 5,
        'Super Ball mejora la captura y consume solo su propio inventario',
    );
    $s = $r['exploracion'];
    $newId = array_values(
        array_filter(
            ExplorationMap::POOL,
            fn($id) => ExplorationMap::rarityLevel($id) === 1 &&
                !in_array($id, $s['estampas'], true),
        ),
    )[0];
    $s = editExp($db, $uid, function (&$s) use ($newId) {
        $s['listo_en'] = 0;
        $s['rachas_captura'][1] = 9;
        $s['avistamiento'] = TrainerService\WildlifeRules::create(
            ExplorationMap::species($newId),
            $s['posicion'],
            microtime(true),
            false,
        );
        $s['posicion'] = array_map(
            fn($n) => (int) round($n),
            TrainerService\WildlifeRules::position($s['avistamiento'], microtime(true)),
        );
    });
    $s = $game->act($uid, 'observe', payloadExp($s, ['avistamiento' => $s['avistamiento']['id']]))[
        'exploracion'
    ];
    verifyExp(
        $s['encuentro']['probabilidad'] === 100 && $s['encuentro']['proteccion'],
        'La mala racha activa una captura garantizada',
    );
    $neverLucky = new ExplorationService($db, fn() => 100);
    $r = $neverLucky->act(
        $uid,
        'capture',
        payloadExp($s, ['encuentro' => $s['encuentro']['id'], 'bola' => 'poke']),
    );
    verifyExp(
        $r['resultado']['tipo'] === 'captura',
        'La protección funciona incluso con la peor tirada',
    );
    echo "OK: $checks verificaciones de exploración, álbum, compras, límites, rareza, protección y API.\n";
} finally {
    if ($uid) {
        $db->beginTransaction();
        $q = $db->prepare(
            'DELETE m FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id WHERE e.usuario_id=?',
        );
        $q->execute([$uid]);
        $q = $db->prepare('DELETE FROM usuarios WHERE id=? AND email=?');
        $q->execute([$uid, $email]);
        $db->commit();
    }
}
