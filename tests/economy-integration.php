<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
require dirname(__DIR__) . '/tests/support/http-cookies.php';
use TrainerService\GameEconomy;
use TrainerService\MarketCatalog;
$db = require dirname(__DIR__) . '/backend/trainer-service/config/database.php';
$email = 'codex-economy-' . bin2hex(random_bytes(6)) . '@example.invalid';
$uid = null;
$extraAccounts = [];
$cookies = '';
$checks = 0;
$csrf = '';
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function api(
    string $path,
    string $method = 'GET',
    ?array $body = null,
    string $contentType = 'application/json',
): array {
    global $cookies, $csrf;
    $ch = curl_init('http://localhost:8002/trainer-api/v1' . $path);
    $headers = ['Content-Type: ' . $contentType];
    if ($cookies) {
        $headers[] = 'Cookie: ' . $cookies;
    }
    if ($csrf) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($c, $h) {
            global $cookies;
            $cookies = integrationCookie($cookies, $h);
            return strlen($h);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($r === false) {
        throw new RuntimeException('API no disponible: ' . $error);
    }
    $json = json_decode($r, true);
    if (!is_array($json)) {
        throw new RuntimeException(
            'Respuesta JSON inválida en ' . $path . ': ' . substr($r, 0, 300),
        );
    }
    return [$code, $json];
}
try {
    [$code] = api('/home');
    check($code === 401, 'Hogar privado sin sesión');
    [$code, $health] = api('/health');
    check($code === 200 && $health['status'] === 'ready', 'Estado del servicio y base de datos');
    check(
        (bool) preg_match('/^[a-f0-9]{16}$/', $health['request_id']),
        'Identificador de solicitud trazable',
    );
    [$code] = api('/auth/register', 'POST', [
        'nombre' => ['invalido'],
        'email' => $email,
        'password' => 'Test-Password-123',
    ]);
    check($code === 400, 'Nombre de tipo incorrecto rechazado');
    [$code] = api(
        '/auth/login',
        'POST',
        ['email' => $email, 'password' => 'Test-Password-123'],
        'text/plain',
    );
    check($code === 415, 'Tipo de contenido validado');
    $handle = bin2hex(random_bytes(8));
    $signup = [
        'nombre' => 'Prueba de economía',
        'usuario' => $handle,
        'email' => $email,
        'password' => bin2hex(random_bytes(12)),
    ];
    [$code] = api('/auth/register', 'POST', $signup);
    check($code === 202, 'Solicita verificación para el registro');
    $findAccount = $db->prepare('SELECT id FROM usuarios WHERE email=?');
    $findAccount->execute([$email]);
    check($findAccount->fetchColumn() === false, 'Antes de verificar no existe la cuenta');
    $pendingRegistration = $cookies;
    $cookies = '';
    [$code] = api('/auth/register-code', 'POST', ['codigo' => '123456']);
    check($code === 403, 'Verificar registro requiere la sesión que inició el registro');
    [$code] = api('/auth/register', 'POST', $signup);
    check($code === 202, 'Otra sesión puede pedir código, sin reemplazar el desafío reciente');
    $registrationCode = $db->prepare('UPDATE codigos_registro SET codigo_hash=? WHERE email=?');
    $registrationCode->execute([password_hash('123456', PASSWORD_DEFAULT), $email]);
    [$code] = api('/auth/register-code', 'POST', ['codigo' => '123456']);
    check($code === 400, 'Otra sesión no puede utilizar el desafío reciente');
    $cookies = $pendingRegistration;
    [$code] = api('/auth/me');
    check($code === 401, 'El registro pendiente no autentica');
    [$code] = api('/auth/register-code', 'POST', ['codigo' => '000000']);
    check($code === 400, 'El registro rechaza un código incorrecto');
    $findAccount->execute([$email]);
    check($findAccount->fetchColumn() === false, 'El código incorrecto no crea la cuenta');
    [$code] = api('/auth/register-code', 'POST', ['codigo' => '123456']);
    check($code === 200, 'El código correcto completa el registro');
    $findAccount->execute([$email]);
    $uid = (int) $findAccount->fetchColumn();
    check($uid > 0, 'La cuenta se crea al verificar');
    [$code, $security] = api('/auth/security');
    check($code === 200 && $security['verificado'], 'El correo verificado habilita la sesión');
    [, $session] = api('/auth/me');
    $csrf = $session['csrf_token'];
    api('/auth/logout', 'POST');
    $cookies = $csrf = '';
    $db->prepare('UPDATE usuarios SET correo_verificado=NULL WHERE id=?')->execute([$uid]);
    [$code] = api('/auth/register', 'POST', $signup);
    check($code === 202, 'Permite retomar una cuenta anterior sin verificar con las credenciales correctas');
    $registrationCode->execute([password_hash('123456', PASSWORD_DEFAULT), $email]);
    [$code] = api('/auth/register-code', 'POST', ['codigo' => '123456']);
    check($code === 200, 'La cuenta anterior se verifica con el nuevo código');
    $findAccount->execute([$email]);
    check((int) $findAccount->fetchColumn() === $uid, 'El registro anterior conserva su identificador');
    [, $session] = api('/auth/me');
    $csrf = $session['csrf_token'];
    api('/auth/logout', 'POST');
    $cookies = $csrf = '';
    $db->prepare('UPDATE usuarios SET correo_verificado=NULL WHERE id=?')->execute([$uid]);
    [$code] = api('/auth/register', 'POST', [
        'nombre' => 'Prueba duplicada',
        'usuario' => strtoupper($handle),
        'email' => 'duplicate-' . $email,
        'password' => 'Test-Password-123',
    ]);
    check($code === 409, 'Nombre de usuario único sin distinguir mayúsculas');
    // Set a known test-only password, without reading real user credentials.
    $password = bin2hex(random_bytes(12));
    $q = $db->prepare('UPDATE usuarios SET password=? WHERE id=?');
    $q->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
    [$code, $r] = api('/auth/login', 'POST', ['email' => $email, 'password' => $password]);
    check($code === 200, 'Login de prueba');
    [$code, $security] = api('/auth/security');
    check($code === 200 && !$security['verificado'] && !$security['dos_pasos'], 'Estado inicial de seguridad');
    [$code] = api('/shop/purchase', 'POST', ['pokemon_id' => 7]);
    check($code === 403, 'Compra sin token CSRF rechazada');
    [, $session] = api('/auth/me');
    $csrf = $session['csrf_token'];
    $validCsrf = $csrf;
    $validCookies = $cookies;
    $cookies = integrationCookie($validCookies, 'Set-Cookie: POKEMARKET_AUTH=deleted;');
    [$code] = api('/auth/me');
    check($code === 401, 'Una sesión PHP sin JWT no autentica');
    $cookies = preg_replace('/(POKEMARKET_AUTH=[^.]+\.[^.]+\.)[A-Za-z0-9_-]/', '${1}!', $validCookies);
    [$code] = api('/auth/me');
    check($code === 401, 'Una firma JWT alterada no autentica');
    $cookies = $validCookies;
    $q = $db->prepare('INSERT INTO codigos_cuenta(usuario_id,finalidad,codigo_hash,emitido) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE codigo_hash=VALUES(codigo_hash), emitido=VALUES(emitido), intentos=0');
    $q->execute([$uid, 'verify', password_hash('123456', PASSWORD_DEFAULT), time() - 901]);
    [$code] = api('/auth/verify-email', 'POST', ['codigo' => '123456']);
    check($code === 400, 'Rechaza el código vencido');
    $db->prepare('UPDATE codigos_cuenta SET emitido=? WHERE usuario_id=? AND finalidad=?')->execute([time(), $uid, 'verify']);
    [$code] = api('/auth/verify-email', 'POST', ['codigo' => '123456']);
    check($code === 200, 'Verifica el correo con código vigente');
    [$code] = api('/auth/verify-email', 'POST', ['codigo' => '123456']);
    check($code === 400, 'Rechaza un código ya utilizado');
    $db->prepare('UPDATE usuarios SET correo_verificado=NULL WHERE id=?')->execute([$uid]);
    [$code] = api('/auth/reset-password', 'POST', ['password' => $password, 'confirmacion' => $password]);
    check($code === 403, 'Cambiar contraseña requiere validar el código primero');
    [$code, $profile] = api('/profile');
    check($code === 200 && $profile['perfil']['usuario'] === $handle, 'El perfil usa el nombre registrado');
    [$code] = api('/profile/username', 'POST', ['usuario' => $handle . 'a']);
    check($code === 409, 'Sin tarjeta no se permite cambiar usuario');
    $db->prepare('UPDATE carteras_usuario SET tarjetas_cambio_usuario=2 WHERE usuario_id=?')->execute([$uid]);
    [$code] = api('/profile/username', 'POST', ['usuario' => $handle]);
    check($code === 200, 'Mantener el mismo usuario no consume tarjeta');
    [, $profile] = api('/profile');
    check($profile['perfil']['tarjetas_cambio_usuario'] === 2, 'Conserva la tarjeta al mantener el nombre');
    [$code] = api('/profile/username', 'POST', ['usuario' => 'usuario con espacios']);
    check($code === 400, 'Nombre inválido no consume tarjeta');
    $conflictEmail = 'codex-name-conflict-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $conflictHandle = 'reserved_' . bin2hex(random_bytes(5));
    $db->prepare('INSERT INTO usuarios(usuario,nombre,email,password) VALUES(?,?,?,?)')->execute([$conflictHandle, 'Prueba de nombre ocupado', $conflictEmail, password_hash($password, PASSWORD_DEFAULT)]);
    $extraAccounts[] = [(int) $db->lastInsertId(), $conflictEmail];
    [$code] = api('/profile/username', 'POST', ['usuario' => $conflictHandle]);
    check($code === 409, 'Nombre ocupado conserva la tarjeta');
    [, $profile] = api('/profile');
    check($profile['perfil']['tarjetas_cambio_usuario'] === 2, 'El fallo por nombre ocupado no consume tarjetas');
    foreach (['a', 'b', 'c'] as $suffix) {
        [$code] = api('/profile/username', 'POST', ['usuario' => $handle . $suffix]);
        check($code === ($suffix === 'c' ? 409 : 200), 'Cada cambio consume una tarjeta');
    }
    [, $profile] = api('/profile');
    check($profile['perfil']['tarjetas_cambio_usuario'] === 0, 'No quedan tarjetas tras dos cambios');
    $db->prepare('UPDATE carteras_usuario SET tarjetas_cambio_usuario=1 WHERE usuario_id=?')->execute([$uid]);
    [$code] = api('/profile/username', 'POST', ['usuario' => $handle . 'c']);
    check($code === 200, 'Con una nueva tarjeta permite un tercer cambio sin cuota mensual');
    [, $profile] = api('/profile');
    check($profile['perfil']['tarjetas_cambio_usuario'] === 0, 'El tercer cambio consume su tarjeta');
    $csrf = 'token-invalido';
    [$code] = api('/shop/purchase', 'POST', ['pokemon_id' => 7]);
    check($code === 403, 'CSRF incorrecto rechazado');
    $csrf = $validCsrf;
    [$code, $r] = api('/wallet');
    check(
        $code === 200 && $r['cartera']['monedas'] === 0 && $r['cartera']['necesita_bienvenida'],
        'El regalo espera al final de la bienvenida',
    );
    [$code, $r] = api('/onboarding/complete', 'POST', []);
    check($code === 200 && $r['cartera']['monedas'] === 1000, 'Bienvenida de 1000');
    [, $againWelcome] = api('/onboarding/complete', 'POST', []);
    check($againWelcome['cartera']['monedas'] === 1000, 'Reclamar bienvenida no duplica monedas');
    check($r['cartera']['comida']['baya'] === 3, 'Tres bayas iniciales');
    [, $again] = api('/wallet');
    check($again['cartera']['monedas'] === 1000, 'La bienvenida no se duplica');
    // Regression: a new account can spend its gift on a non-starter.
    $freeEmail = 'codex-first-choice-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $q = $db->prepare('INSERT INTO usuarios(usuario,nombre,email,password) VALUES(?,?,?,?)');
    $q->execute([
        bin2hex(random_bytes(8)),
        'Prueba de primera compra',
        $freeEmail,
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);
    $freeUid = (int) $db->lastInsertId();
    $extraAccounts[] = [$freeUid, $freeEmail];
    $freeGame = new GameEconomy($db);
    $freeGame->completeWelcome($freeUid);
    $nonStarter = MarketCatalog::get(274);
    check(
        $nonStarter['categoria'] !== 'iniciales' && $nonStarter['precio'] === 560,
        'Nuzleaf cuesta 560, con un solo recargo de 40 por Clorofila',
    );
    $freePurchase = $freeGame->purchase($freeUid, 274);
    check(
        $freePurchase['primer_companero'] && $freePurchase['monedas'] === 440,
        'Primera compra libre: 1000 menos 560 deja 440',
    );
    check(
        $freeGame->wallet($freeUid)['pokemon_ids'] === [274],
        'Primera compra fuera de iniciales llega al Hogar',
    );
    $q = $db->prepare(
        'SELECT m.pokemon_id FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id WHERE e.usuario_id=? ORDER BY m.posicion',
    );
    $q->execute([$freeUid]);
    check(
        array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)) === [274],
        'La elección libre también crea el primer equipo',
    );
    [$code, $r] = api('/shop/catalog?limit=12');
    check($code === 200 && $r['total'] === 1025, 'Índice completo');
    [$code, $r] = api('/shop/catalog?categoria=legendarios');
    check(
        $r['total'] > 50 &&
            count(array_filter($r['pokemon'], fn($p) => $p['categoria'] !== 'legendarios')) === 0,
        'Filtro global de categoría',
    );
    [$code] = api('/shop/catalog?categoria=no-existe');
    check($code === 400, 'Categoría inválida rechazada');
    [$code] = api('/shop/catalog?q%5B%5D=x');
    check($code === 400, 'Búsqueda de tipo incorrecto rechazada');
    [$code] = api('/shop/catalog?offset=-1');
    check($code === 400, 'Paginación inválida rechazada');
    [$code, $r] = api('/shop/purchase', 'POST', ['pokemon_id' => 150, 'precio' => 1]);
    check($code === 409, 'La primera compra también exige saldo suficiente');
    [$code, $r] = api('/collection', 'POST', ['pokemon_id' => 7, 'precio' => 1]);
    $price = MarketCatalog::get(7)['precio'];
    check(
        $code === 200 && $r['cartera']['monedas'] === 1000 - $price,
        'Compra por precio del servidor en ruta heredada',
    );
    [$code, $r] = api('/shop/purchase', 'POST', ['pokemon_id' => 7]);
    check($code === 409, 'Compra duplicada rechazada');
    [, $r] = api('/teams');
    check(count($r['equipos']) === 1, 'Equipo inicial creado');
    check($r['capacidad']['limite'] === 1, 'Primer nivel tiene un equipo');
    $teamId = (int) $r['equipos'][0]['id'];
    [$code] = api("/teams/$teamId", 'PATCH', ['nombre' => '   ']);
    check($code === 400, 'No permite renombrar con un nombre vacío');
    [$code] = api("/teams/$teamId", 'PATCH', ['nombre' => 'Equipo Aurora']);
    check($code === 200, 'Puede renombrar su equipo con el cupo lleno');
    [, $renamed] = api("/teams/$teamId");
    check($renamed['equipo']['nombre'] === 'Equipo Aurora', 'El nombre se guarda');
    [$code] = api('/teams', 'POST', ['nombre' => 'Segundo equipo prematuro']);
    check($code === 409, 'La API aplica el límite de equipos');
    [$code, $profile] = api('/profile');
    check(
        $code === 200 && $profile['perfil']['puntos'] === 10 && $profile['perfil']['nivel'] === 1,
        'La colección suma puntos de entrenador',
    );
    check(
        !array_key_exists('email', $profile['top'][0]) &&
            !array_key_exists('nombre', $profile['top'][0]),
        'El top usa alias públicos',
    );
    [$code, $r] = api('/shop/purchase', 'POST', ['pokemon_id' => 150, 'precio' => 0]);
    check($code === 409, 'Saldo insuficiente');
    [, $r] = api('/wallet');
    $before = $r['cartera']['monedas'];
    check($before === 1000 - $price, 'Operaciones rechazadas no cobran monedas');
    [$code, $abilityHome] = api('/home/ability', 'POST', [
        'pokemon_id' => 7,
        'habilidad' => 'torrent',
    ]);
    check(
        $code === 200 && $abilityHome['pokemon'][0]['habilidad_activa']['id'] === 'torrent',
        'Elegir habilidad compatible por API',
    );
    check($abilityHome['cartera']['monedas'] === $before, 'Elegir habilidad no cobra');
    [$code] = api('/home/ability', 'POST', ['pokemon_id' => 25, 'habilidad' => 'static']);
    check($code === 409, 'No cambiar habilidad de un Pokémon ajeno');
    [$code] = api('/home/ability', 'POST', ['pokemon_id' => 7, 'habilidad' => 'blaze']);
    check($code === 409, 'No asignar habilidad de otra especie');
    [$code] = api('/home/ability', 'POST', ['pokemon_id' => 7, 'habilidad' => ['torrent']]);
    check($code === 400, 'Rechazar habilidad con tipo inválido');
    [$code, $r] = api('/home/feed', 'POST', ['pokemon_id' => 7, 'comida' => 'baya']);
    check($code === 409, 'No alimentar a energía completa');
    $game = new GameEconomy($db);
    $state = [
        'id' => 'test-' . bin2hex(random_bytes(8)),
        'estado' => 'finalizada',
        'resultado' => 'victoria',
        'equipo_jugador' => ['pokemon' => [['id' => 7]]],
    ];
    $game->settle($uid, $state);
    $game->settle($uid, $state);
    [$code, $r] = api('/home');
    check($r['cartera']['monedas'] === $before + 180, 'Premio de victoria exactamente una vez');
    check($r['pokemon'][0]['cuidado']['energia'] === 88, 'Energía consumida una sola vez');
    check($r['pokemon'][0]['nivel'] === 1, 'El Hogar muestra el nivel inicial real');
    $initialMove = $r['pokemon'][0]['progreso']['movimientos'][0]['nombre'];
    [$code, $training] = api('/home/moves', 'POST', [
        'pokemon_id' => 7,
        'movimientos' => [$initialMove],
    ]);
    check(
        $code === 200 && count($training['pokemon'][0]['progreso']['movimientos']) === 1,
        'Ataques equipados por API',
    );
    [$code] = api('/home/moves', 'POST', ['pokemon_id' => 7, 'movimientos' => ['hydro-pump']]);
    check($code === 409, 'No equipar ataques de niveles futuros');
    [$code] = api('/home/moves', 'POST', ['pokemon_id' => 25, 'movimientos' => ['thunder-shock']]);
    check($code === 409, 'No equipar ataques en un Pokémon ajeno');
    [$code, $r] = api('/home/feed', 'POST', ['pokemon_id' => 7, 'comida' => 'baya']);
    check(
        $code === 200 && $r['pokemon'][0]['cuidado']['energia'] === 100,
        'Comida recupera energía',
    );
    check($r['cartera']['comida']['baya'] === 2, 'Consume una comida');
    check($r['pokemon'][0]['cuidado']['vinculo'] === 7, 'Vínculo persistente');
    [$code, $r] = api('/home/food', 'POST', ['comida' => 'curry', 'cantidad' => 1]);
    check(
        $code === 200 &&
            $r['cartera']['monedas'] === $before + 180 - 90 &&
            $r['cartera']['comida']['curry'] === 1,
        'Compra de comida',
    );
    [$code] = api('/home/food', 'POST', ['comida' => 'baya', 'cantidad' => 1.5]);
    check($code === 409, 'Cantidad fraccionaria rechazada');
    [$code] = api('/home/habitat', 'PATCH', ['pokemon_id' => 7, 'habitat' => 'laguna']);
    check($code === 409, 'No asignar un escenario bloqueado');
    [$code] = api('/home/scenes', 'POST', ['escenario' => 'laguna', 'precio' => 0]);
    check($code === 409, 'Escenario sin saldo rechazado');
    [$code] = api('/home/feed', 'POST', ['pokemon_id' => 25, 'comida' => 'baya']);
    check($code === 409, 'No alimentar Pokémon ajeno');
    [$code] = api('/home/habitat', 'PATCH', ['pokemon_id' => 25, 'habitat' => 'bosque']);
    check($code === 409, 'No cambiar hábitat de Pokémon ajeno');
    [$code] = api('/collection/7', 'DELETE');
    check($code === 409, 'No borrar compras por ruta antigua');
    $p = $game->applyCare($uid, ['id' => 7, 'estadisticas' => ['ataque' => 100, 'defensa' => 100]]);
    check($p['estadisticas']['ataque'] === 105, 'Bonus aplicado en servidor');
    $loss = [
        'id' => 'test-loss-' . bin2hex(random_bytes(8)),
        'estado' => 'finalizada',
        'resultado' => 'derrota',
        'equipo_jugador' => ['pokemon' => [['id' => 7]]],
    ];
    $game->settle($uid, $loss);
    $game->settle($uid, $loss);
    [, $r] = api('/wallet');
    check($r['cartera']['monedas'] === $before + 180 - 90 && $loss['recompensa_monedas'] === 0, 'Derrota sin monedas');
    $config = require dirname(__DIR__) . '/backend/trainer-service/config/app.php';
    preg_match('/(?:^|; )POKEMARKET_SESSION=([^;]+)/', $cookies, $sessionCookie);
    session_id($sessionCookie[1]);
    (new \TrainerService\SessionManager($config))->start();
    $actor = [
        'id' => 7,
        'nombre' => 'Squirtle',
        'tipos' => ['water'],
        'estadisticas' => ['hp' => 100, 'ataque' => 100, 'defensa' => 100, 'velocidad' => 100],
        'movimientos' => [
            [
                'nombre' => 'water-gun',
                'nombre_mostrado' => 'Pistola Agua',
                'tipo' => 'water',
                'potencia' => 100,
            ],
        ],
        'sprite_animado' => null,
        'imagen_fallback' => null,
        'hp_actual' => 100,
        'hp_maximo' => 100,
        'derrotado' => false,
    ];
    $enemy = $actor;
    $enemy['id'] = 4;
    $enemy['nombre'] = 'Charmander';
    $enemy['tipos'] = ['fire'];
    $enemy['hp_actual'] = 1;
    $enemy['estadisticas']['velocidad'] = $actor['estadisticas']['velocidad'] + 100;
    $battleId = 'http-test-' . bin2hex(random_bytes(7));
    $_SESSION['batalla'] = [
        'id' => $battleId,
        'usuario_id' => $uid,
        'economia_revision' => GameEconomy::REVISION,
        'modo' => 'rapido',
        'estado' => 'en_curso',
        'equipo_jugador' => ['pokemon' => [$actor]],
        'equipo_rival' => ['pokemon' => [$enemy]],
        'activo_jugador' => 0,
        'activo_rival' => 0,
        'turno' => 1,
        'resultado' => null,
        'historial' => [],
    ];
    session_write_close();
    [$code, $r] = api('/battles/' . $battleId . '/turn', 'POST', ['movimiento' => 'water-gun']);
    check(($r['acciones'][0]['actor'] ?? null) === 'jugador', 'El jugador ataca primero aunque el rival sea más rápido');
    check(
        $code === 200 &&
            $r['estado_final']['resultado'] === 'victoria' &&
            $r['estado_final']['recompensa_monedas'] === 180,
        'Turno HTTP termina batalla y abona recompensa',
    );
    [$code] = api('/battles/' . $battleId . '/turn', 'POST', ['movimiento' => 'water-gun']);
    check($code === 409, 'No repetir turno finalizado');
    [, $r] = api('/wallet');
    check($r['cartera']['monedas'] === $before + 180 - 90 + 180, 'Saldo real tras turno HTTP');
    [, $teams] = api('/teams');
    $leadTeamId = (int) $teams['equipos'][0]['id'];
    [$invalidLeadCode] = api('/battles', 'POST', [
        'equipo_id' => $leadTeamId,
        'modo' => 'rapido',
        'pokemon_id' => 150,
    ]);
    check($invalidLeadCode === 400, 'Rechazar un Pokémon ajeno al equipo antes de comenzar');
    $db->prepare('INSERT INTO coleccion_pokemon(usuario_id,pokemon_id) VALUES(?,25)')->execute([$uid]);
    $db->prepare('INSERT INTO cuidado_pokemon(usuario_id,pokemon_id) VALUES(?,25)')->execute([
        $uid,
    ]);
    $db->prepare(
        'INSERT INTO miembros_equipo(equipo_id,posicion,pokemon_id) VALUES(?,2,25)',
    )->execute([$leadTeamId]);
    foreach (['rapido', 'equipos'] as $leadMode) {
        [$leadCode, $leadBattle] = api('/battles', 'POST', [
            'equipo_id' => $leadTeamId,
            'modo' => $leadMode,
            'pokemon_id' => 25,
        ]);
        check(
            $leadCode === 201 &&
                $leadBattle['batalla']['equipo_jugador']['pokemon'][0]['id'] === 25,
            'El compañero elegido inicia en ' . $leadMode,
        );
        check(
            count($leadBattle['batalla']['equipo_jugador']['pokemon']) ===
                ($leadMode === 'rapido' ? 1 : 2),
            'Selección respeta el tamaño del modo',
        );
        api('/battles/' . $leadBattle['batalla']['id'], 'DELETE');
    }
    $db->prepare('DELETE FROM miembros_equipo WHERE equipo_id=? AND pokemon_id=25')->execute([
        $leadTeamId,
    ]);
    $db->prepare('DELETE FROM cuidado_pokemon WHERE usuario_id=? AND pokemon_id=25')->execute([
        $uid,
    ]);
    $db->prepare('DELETE FROM coleccion_pokemon WHERE usuario_id=? AND pokemon_id=25')->execute([$uid]);
    [$code, $started] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'rapido',
    ]);
    check(
        $code === 201 && $started['batalla']['equipo_jugador']['pokemon'][0]['id'] === 7,
        'Inicio de batalla con compañero comprado y rival equilibrado: HTTP ' .
            $code .
            ' ' .
            ($started['message'] ?? ''),
    );
    check(
        $started['batalla']['equipo_jugador']['pokemon'][0]['altura'] === 0.5,
        'Batalla conserva altura oficial en metros',
    );
    check(
        $started['batalla']['equipo_jugador']['pokemon'][0]['bonus_cuidado'] === 5,
        'Inicio aplica el cuidado actual',
    );
    check($started['batalla']['iniciativa']['orden'] === [], 'Una batalla nueva espera confirmar el orden');
    [$code] = api('/battles/' . $started['batalla']['id'] . '/turn', 'POST', ['movimiento' => $started['batalla']['equipo_jugador']['pokemon'][0]['movimientos'][0]['nombre']]);
    check($code === 409, 'No atacar antes del sorteo');
    [$code, $draw] = api('/battles/' . $started['batalla']['id'] . '/initiative', 'POST', []);
    check($code === 200 && count($draw['batalla']['iniciativa']['orden']) === 2, 'Confirmar sortea los dos lados');
    [, $againDraw] = api('/battles/' . $started['batalla']['id'] . '/initiative', 'POST', []);
    check($draw['batalla']['iniciativa'] === $againDraw['batalla']['iniciativa'], 'Repetir confirmación conserva el sorteo');
    [$code] = api('/battles/' . $started['batalla']['id'], 'DELETE');
    check($code === 200, 'Abandono válido');
    [, $r] = api('/wallet');
    check(
        $r['cartera']['monedas'] === $before + 180 - 90 + 180,
        'Abandonar no entrega monedas',
    );
    // Controlled test funds only: scenarios, escrow, recovery and stale-save protection.
    $db->prepare('UPDATE carteras_usuario SET monedas=10000 WHERE usuario_id=?')->execute([$uid]);
    [, $r] = api('/home');
    check(
        count(array_filter($r['escenarios'], fn($x) => $x['desbloqueado'])) === 0,
        'Expansiones bloqueadas al comenzar',
    );
    [$code, $r] = api('/home/scenes', 'POST', ['escenario' => 'pradera', 'precio' => 1]);
    check(
        $code === 200 && $r['cartera']['monedas'] === 9500,
        'Precio de escenario calculado en servidor',
    );
    [$code] = api('/home/scenes', 'POST', ['escenario' => 'pradera']);
    check($code === 409, 'No volver a comprar un escenario');
    [$code, $r] = api('/home/habitat', 'PATCH', ['pokemon_id' => 7, 'habitat' => 'pradera']);
    check(
        $code === 200 && $r['pokemon'][0]['cuidado']['habitat'] === 'pradera',
        'Escenario comprado permite asignación',
    );
    [, $offers] = api('/battles/offers');
    [, $sameOffers] = api('/battles/offers');
    check(
        count($offers['ofertas']) === 4 && $offers['ofertas'] === $sameOffers['ofertas'],
        'Ofertas aleatorias estables hasta vencer',
    );
    $offer = $offers['ofertas'][0];
    $db->prepare('UPDATE ofertas_apuesta SET vence=? WHERE id=? AND usuario_id=?')->execute([
        time() - 1,
        $offer['id'],
        $uid,
    ]);
    [$code] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'apuesta',
        'oferta_id' => $offer['id'],
    ]);
    check($code === 409, 'Oferta vencida no cobra ni inicia duelo');
    $db->prepare('UPDATE ofertas_apuesta SET vence=? WHERE id=? AND usuario_id=?')->execute([
        $offer['vence'],
        $offer['id'],
        $uid,
    ]);
    $db->prepare('UPDATE carteras_usuario SET monedas=50 WHERE usuario_id=?')->execute([$uid]);
    [$code] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'apuesta',
        'oferta_id' => $offer['id'],
    ]);
    check(
        $code === 409 && $game->wallet($uid)['monedas'] === 50,
        'Apuesta sin saldo no genera deuda',
    );
    $db->prepare('UPDATE carteras_usuario SET monedas=9500 WHERE usuario_id=?')->execute([$uid]);
    try {
        (new \TrainerService\WagerService($db))->offer($uid + 1000000, $offer['id']);
        check(false, 'No aceptar oferta ajena');
    } catch (DomainException $e) {
        check(true, 'Oferta privada por usuario');
    }

    [$code] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'apuesta',
        'oferta_id' => 'inventada',
        'cantidad' => 1,
    ]);
    check($code === 409, 'No aceptar apuesta inventada');
    [$code, $wager] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'apuesta',
        'oferta_id' => $offer['id'],
        'cantidad' => 1,
    ]);
    check(
        $code === 201 && $wager['batalla']['apuesta']['cantidad'] === 100,
        'Entrada validada en servidor',
    );
    [, $r] = api('/wallet');
    check($r['cartera']['monedas'] === 9400, 'Apuesta debitada exactamente al comenzar');
    $wid = $wager['batalla']['id'];
    [$code] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'rapido',
    ]);
    check($code === 409, 'Una apuesta pendiente impide crear otro combate');
    [$code] = api('/auth/logout', 'POST');
    api('/auth/login', 'POST', ['email' => $email, 'password' => $password]);
    [, $session] = api('/auth/me');
    $csrf = $session['csrf_token'];
    [, $current] = api('/battles/current');
    check($current['batalla']['id'] === $wid, 'Apuesta recuperada después de cerrar sesión');
    [, $current] = api('/battles/' . $wid . '/initiative', 'POST', []);
    $service = new \TrainerService\WagerService($db);
    $durable = $service->find($uid, $wid);
    $durable['iniciativa']['orden'] = ['rival', 'jugador'];
    $durable['equipo_jugador']['pokemon'][0]['hp_actual'] = 10000;
    $durable['equipo_rival']['pokemon'][0]['hp_actual'] = 10000;
    $service->save($uid, $durable);
    [$code] = api('/battles/' . $wid . '/turn', 'POST', ['movimiento' => 'inventado']);
    check($code === 409, 'El rival debe actuar antes de que se pueda elegir un ataque');
    $rivalRequest = ['rival' => true, 'ronda' => $durable['turno']];
    [$code, $opening] = api('/battles/' . $wid . '/turn', 'POST', $rivalRequest);
    check($code === 200 && $opening['acciones'][0]['actor'] === 'rival', 'El rival abre su ronda sin pedir ataque al jugador');
    [, $repeated] = api('/battles/' . $wid . '/turn', 'POST', $rivalRequest);
    check($repeated['acciones'] === [] && $repeated['estado_final']['version_apuesta'] === $opening['estado_final']['version_apuesta'], 'Reintentar el turno rival no duplica el ataque');
    [$code] = api('/battles/' . $wid . '/turn', 'POST', ['movimiento' => 'inventado']);
    check($code === 400, 'Movimiento inválido no altera la apuesta');
    $move = $current['batalla']['equipo_jugador']['pokemon'][0]['movimientos'][0]['nombre'];
    [$code, $turn] = api('/battles/' . $wid . '/turn', 'POST', ['movimiento' => $move]);
    check(
        $code === 200 && $turn['estado_final']['version_apuesta'] === $opening['estado_final']['version_apuesta'] + 1 &&
            array_column(array_filter($turn['acciones'], fn($a) => $a['tipo'] === 'ataque'), 'actor') === ['jugador'],
        'Turno de apuesta persistido con versión',
    );
    // Finish a deterministic victory through the same persistent settlement used by the controller.
    $service = new \TrainerService\WagerService($db);
    $saved = $service->find($uid, $wid);
    $stale = $saved;
    $saved['estado'] = 'finalizada';
    $saved['resultado'] = 'victoria';
    $service->save($uid, $saved);
    check(
        $game->wallet($uid)['monedas'] === 9600,
        'Victoria devuelve entrada y premio sin recompensa ordinaria',
    );
    try {
        $service->save($uid, $stale);
        check(false, 'No aceptar versión antigua');
    } catch (DomainException $e) {
        check(true, 'Versión antigua no vuelve a pagar');
    }
    [$code, $second] = api('/battles', 'POST', [
        'equipo_id' => (int) $teams['equipos'][0]['id'],
        'modo' => 'apuesta',
        'oferta_id' => $offer['id'],
    ]);
    check($code === 201, 'Nuevo duelo después de terminar');
    [$code] = api('/battles/' . $second['batalla']['id'], 'DELETE');
    check(
        $code === 200 && $game->wallet($uid)['monedas'] === 9500,
        'Abandono consume entrada y no premia',
    );
    [$code] = api('/battles/' . $second['batalla']['id'], 'DELETE');
    check(
        $code === 409 && $game->wallet($uid)['monedas'] === 9500,
        'No volver a liquidar abandono',
    );
    foreach (['bellota', 'sopa', 'galleta', 'puff', 'pokecubo'] as $food) {
        $beforeFood = $game->wallet($uid)['monedas'];
        [$code, $purchasedFood] = api('/home/food', 'POST', [
            'comida' => $food,
            'cantidad' => 1,
            'precio' => 0,
        ]);
        check(
            $code === 200 &&
                $purchasedFood['cartera']['comida'][$food] === 1 &&
                $purchasedFood['cartera']['monedas'] ===
                    $beforeFood - GameEconomy::FOOD[$food]['precio'],
            'Alimento nuevo comprado con precio del servidor: ' . $food,
        );
        $db->prepare(
            'UPDATE cuidado_pokemon SET energia=20 WHERE usuario_id=? AND pokemon_id=7',
        )->execute([$uid]);
        [$code, $fed] = api('/home/feed', 'POST', ['pokemon_id' => 7, 'comida' => $food]);
        check(
            $code === 200 && $fed['cartera']['comida'][$food] === 0,
            'Alimento nuevo consumido una sola vez: ' . $food,
        );
    }
    $addSpecies = $db->prepare('INSERT IGNORE INTO coleccion_pokemon (usuario_id, pokemon_id) VALUES (?, ?)');
    foreach (range(1, 20) as $species) {
        $addSpecies->execute([$uid, $species]);
    }
    [$code] = api('/teams', 'POST', ['nombre' => 'Equipo de explorador']);
    check($code === 201, 'El nivel 3 desbloquea el segundo equipo');
    [$code] = api('/teams', 'POST', ['nombre' => 'Tercer equipo prematuro']);
    check($code === 409, 'El tercer equipo sigue bloqueado');
    foreach (range(21, 60) as $species) {
        $addSpecies->execute([$uid, $species]);
    }
    [$code] = api('/teams', 'POST', ['nombre' => 'Equipo de veterano']);
    check($code === 201, 'El nivel 7 desbloquea el tercer equipo');
    [$code] = api('/teams', 'POST', ['nombre' => 'Cuarto equipo']);
    check($code === 409, 'Tres equipos es el máximo');
    // A stale active snapshot in the PHP session must not hide the closed persistent round.
    [$code] = api('/auth/logout', 'POST');
    check($code === 200, 'Cierre de sesión con CSRF');
    [$code] = api('/wallet');
    check($code === 401, 'Sesión cerrada sin acceso a saldo');
    api('/auth/login', 'POST', ['email' => $email, 'password' => $password]);
    [, $duelSession] = api('/auth/me');
    $csrf = $duelSession['csrf_token'];
    [$code] = api('/duels', 'POST', ['accion' => 'buscar', 'equipo_id' => $leadTeamId, 'alineacion' => [7]]);
    check($code === 200, 'Publica una búsqueda de duelo');
    $firstSession = [$cookies, $csrf];
    $duelPassword = bin2hex(random_bytes(16));
    $db->prepare('UPDATE usuarios SET password=? WHERE id=?')->execute([password_hash($duelPassword, PASSWORD_DEFAULT), $freeUid]);
    $cookies = $csrf = '';
    api('/auth/login', 'POST', ['email' => $freeEmail, 'password' => $duelPassword]);
    [, $duelSession] = api('/auth/me');
    $csrf = $duelSession['csrf_token'];
    [, $rivalTeams] = api('/teams');
    $rivalTeam = (int) $rivalTeams['equipos'][0]['id'];
    [$code, $room] = api('/duels', 'POST', ['accion' => 'buscar', 'equipo_id' => $rivalTeam, 'alineacion' => [274]]);
    check($code === 200 && in_array($uid, array_column($room['jugadores'], 'id')), 'La sala muestra al otro entrenador');
    [$code, $room] = api('/duels', 'POST', ['accion' => 'invitar', 'rival' => $uid]);
    check($code === 200, 'Se envía invitación');
    $duelId = $room['duelos'][0]['id'];
    $secondSession = [$cookies, $csrf];
    [$cookies, $csrf] = $firstSession;
    [$code, $room] = api('/duels', 'POST', ['accion' => 'aceptar', 'id' => $duelId, 'equipo_id' => $leadTeamId]);
    check($code === 200 && $room['duelos'][0]['estado'] === 'activa', 'Aceptar inicia el duelo');
    [$code] = api('/duels', 'POST', ['accion' => 'proponer_apuesta', 'id' => $duelId, 'monto' => 0]);
    check($code === 200, 'Se puede continuar el duelo individual sin apuesta');
    [$code] = api('/duels', 'POST', ['accion' => 'atacar', 'id' => $duelId, 'movimiento' => 'tackle']);
    check($code === 409, 'No atacar antes de que confirmen ambos');
    [, $room] = api('/duels', 'POST', ['accion' => 'iniciativa', 'id' => $duelId]);
    check($room['duelos'][0]['partida']['iniciativa']['orden'] === [], 'La primera persona espera a la segunda');
    [$cookies, $csrf] = $secondSession;
    [, $room] = api('/duels', 'POST', ['accion' => 'iniciativa', 'id' => $duelId]);
    check(count($room['duelos'][0]['partida']['iniciativa']['orden']) === 2, 'La segunda confirmación revela el orden');
    $attackerSession = $room['duelos'][0]['partida']['turno'] ? $secondSession : $firstSession;
    $defenderSession = $room['duelos'][0]['partida']['turno'] ? $firstSession : $secondSession;
    [$cookies, $csrf] = $defenderSession;
    [$code] = api('/duels', 'POST', ['accion' => 'atacar', 'id' => $duelId, 'movimiento' => 'tackle']);
    check($code === 409, 'Quien perdió el sorteo espera el turno del otro');
    [$cookies, $csrf] = $attackerSession;
    [, $room] = api('/duels');
    $move = $room['duelos'][0]['partida']['equipo'][0]['movimientos'][0]['nombre'];
    [$code, $room] = api('/duels', 'POST', ['accion' => 'atacar', 'id' => $duelId, 'movimiento' => $move]);
    check($code === 200 && !$room['duelos'][0]['partida']['turno'], 'Un ataque cede el turno');
    [$cookies, $csrf] = $firstSession;
    api('/auth/logout', 'POST');
    $q = $db->prepare('INSERT INTO codigos_cuenta(usuario_id,finalidad,codigo_hash,emitido) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE codigo_hash=VALUES(codigo_hash), emitido=VALUES(emitido), intentos=0');
    $db->prepare('UPDATE usuarios SET correo_verificado=CURRENT_TIMESTAMP WHERE id=?')->execute([$uid]);
    $q->execute([$uid, 'login', password_hash('654321', PASSWORD_DEFAULT), time()]);
    [$code, $challenge] = api('/auth/login', 'POST', ['email' => $email, 'password' => $password]);
    check($code === 200 && $challenge['metodo'] === 'correo', 'Correo verificado exige un segundo paso');
    [$code] = api('/auth/me');
    check($code === 401, 'El desafío pendiente no autentica');
    [$code] = api('/auth/login-code', 'POST', ['codigo' => '654321']);
    check($code === 200, 'Código de correo completa el inicio');
    $q->execute([$uid, 'reset', password_hash('123456', PASSWORD_DEFAULT), time()]);
    api('/auth/reset-request', 'POST', ['email' => $email]);
    [$code] = api('/auth/reset-verify', 'POST', ['codigo' => '000000']);
    check($code === 400, 'Recuperación rechaza un código incorrecto');
    [$code] = api('/auth/reset-verify', 'POST', ['codigo' => '123456']);
    check($code === 200, 'Código válido habilita el cambio');
    $newPassword = 'Nueva-prueba-' . bin2hex(random_bytes(8));
    [$code] = api('/auth/reset-password', 'POST', ['password' => $newPassword, 'confirmacion' => $password]);
    check($code === 400, 'Rechaza contraseñas distintas');
    [$code] = api('/auth/reset-password', 'POST', ['password' => $newPassword, 'confirmacion' => $newPassword]);
    check($code === 200, 'Guarda las contraseñas coincidentes');
    [$code] = api('/auth/me');
    check($code === 401, 'El cambio invalida la sesión anterior');
    echo "OK: $checks verificaciones de API real, bienvenida, compras, saldo, comida, cuidado, hábitat y premios idempotentes.\n";
} finally {
    $db->prepare('DELETE FROM codigos_registro WHERE email=?')->execute([$email]);
    foreach (array_merge([[$uid, $email]], $extraAccounts) as [$cleanupUid, $cleanupEmail]) {
        if (!$cleanupUid) {
            continue;
        }
        $db->beginTransaction();
        $q = $db->prepare(
            'DELETE m FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id WHERE e.usuario_id=?',
        );
        $q->execute([$cleanupUid]);
        foreach (
            [
                'escenarios_usuario',
                'ofertas_apuesta',
                'rondas_apuesta',
                'equipos',
                'coleccion_pokemon',
                'cuidado_pokemon',
                'inventario_comida',
                'movimientos_monedas',
                'carteras_usuario',
            ]
            as $t
        ) {
            $q = $db->prepare('DELETE FROM ' . $t . ' WHERE usuario_id=?');
            $q->execute([$cleanupUid]);
        }
        $q = $db->prepare('DELETE FROM usuarios WHERE id=? AND email=?');
        $q->execute([$cleanupUid, $cleanupEmail]);
        $db->commit();
    }
}
