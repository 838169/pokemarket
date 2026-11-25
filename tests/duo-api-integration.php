<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/trainer-service/bootstrap.php';
require dirname(__DIR__).'/tests/support/http-cookies.php';
use TrainerService\DuoService;
$db=require dirname(__DIR__).'/backend/trainer-service/config/database.php';
$users=[];$teams=[];$checks=0;$sessions=[];$emails=[];$cookies='';$csrf='';$testPassword='Duo-'.bin2hex(random_bytes(10));
function duoCheck(bool $ok,string $message):void { global $checks; if(!$ok)throw new RuntimeException($message);$checks++; }
function duoReject(callable $f,string $message):void {try{$f();}catch(DomainException){duoCheck(true,$message);return;}throw new RuntimeException($message);}
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
    foreach([7,25,4,1,10] as $index=>$pokemon){
        $handle='duotest_'.bin2hex(random_bytes(5));
        $db->prepare('INSERT INTO usuarios(nombre,usuario,email,password) VALUES(?,?,?,?)')->execute(['Prueba dúo',$handle,$handle.'@example.invalid',password_hash($testPassword,PASSWORD_DEFAULT)]);
        $uid=(int)$db->lastInsertId();$users[]=$uid;$emails[$uid]=$handle.'@example.invalid';
        $db->prepare('INSERT INTO coleccion_pokemon(usuario_id,pokemon_id) VALUES(?,?)')->execute([$uid,$pokemon]);
        $db->prepare('INSERT INTO equipos(usuario_id,nombre) VALUES(?,?)')->execute([$uid,'Equipo de prueba']);
        $team=(int)$db->lastInsertId();$teams[$uid]=[$team,$pokemon];
        $db->prepare('INSERT INTO miembros_equipo(equipo_id,posicion,pokemon_id) VALUES(?,1,?)')->execute([$team,$pokemon]);
    }
    $db->prepare('INSERT INTO amistades(usuario_menor,usuario_mayor,solicitante,aceptada) VALUES(?,?,?,1)')
        ->execute([$users[2],$users[3],$users[2]]);
    $roster=function($uid,$team,$lineup)use($db){
        $q=$db->prepare('SELECT m.pokemon_id FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id WHERE e.usuario_id=? AND e.id=? AND m.pokemon_id=?');$q->execute([$uid,$team,$lineup[0]]);
        if(!$q->fetchColumn())throw new DomainException('Ese Pokémon no pertenece a tu equipo.');
        return [['id'=>$lineup[0],'nombre'=>'Pokémon '.$lineup[0],'hp_actual'=>30,'hp_maximo'=>30,'movimientos'=>[['nombre'=>'tackle','nombre_mostrado'=>'Placaje','potencia'=>40]]]];
    };
    foreach($users as $uid){
        $cookies='';$csrf='';
        [$code]=api('/auth/login','POST',['email'=>$emails[$uid],'password'=>$testPassword]);
        duoCheck($code===200,'Inicio de sesión para cada participante');
        [, $session]=api('/auth/me');$csrf=$session['csrf_token'];$sessions[$uid]=[$cookies,$csrf];
    }
    [$sender,$recipient] = [$users[0],$users[1]];
    $friendEvents = new TrainerService\FriendshipEvents($db);
    foreach (['rechazar','aceptar'] as $reply) {
        [$cookies,$csrf] = $sessions[$sender];
        [$code,$sent] = api('/friends','POST',['accion'=>'solicitar','id'=>$recipient]);
        duoCheck($code===200 && count($sent['amigos'])===1,'El remitente ve una única solicitud pendiente');
        [$code] = api('/friends','POST',['accion'=>'solicitar','id'=>$recipient]);
        duoCheck($code===409,'Una solicitud pendiente evita duplicados');
        [$cookies,$csrf] = $sessions[$recipient];
        [, $inbox] = api('/friends');
        [, $notice] = api('/friends?invitaciones=1');
        duoCheck($inbox['amigos']===[] && $notice['invitaciones']===[],'Sin consumidor el destinatario no ve la solicitud');
        [$code] = api('/friends','POST',['accion'=>$reply,'id'=>$sender]);
        duoCheck($code===409,'Solo se puede responder a la solicitud entregada');
        duoCheck($friendEvents->deliverPending()===1 && $friendEvents->deliverPending()===0,'El consumidor entrega el evento una sola vez');
        [, $notice] = api('/friends?invitaciones=1');
        duoCheck(($notice['invitaciones'][0]['tipo']??null)==='amistad','La solicitud entregada genera un aviso');
        [$code,$answered] = api('/friends','POST',['accion'=>$reply,'id'=>$sender]);
        duoCheck($code===200 && count($answered['amigos'])===($reply==='aceptar'?1:0),'Se puede rechazar, reenviar y aceptar');
        [, $notice] = api('/friends?invitaciones=1');
        duoCheck($notice['invitaciones']===[],'Responder limpia el aviso pendiente');
    }
    $game=new class {
        public function state(int $uid):array {return $this->request($uid);}
        public function act(int $uid,array $input):array {return $this->request($uid,$input);}
        private function request(int $uid,?array $input=null):array {
            global $cookies,$csrf,$sessions;
            [$cookies,$csrf]=$sessions[$uid];
            [$code,$response]=api('/duos',$input===null?'GET':'POST',$input);
            $sessions[$uid]=[$cookies,$csrf];
            if($code>=400)throw new DomainException($response['message']??'Solicitud rechazada');
            return $response;
        }
    };
    duoReject(fn()=>$game->act($users[0],['accion'=>'invitar','amigo'=>$users[4]]),'No invitar a personas fuera de la lista de amigos');
    $rooms=[];
    foreach([0,2] as $index){
        [$host,$guest]=[$users[$index],$users[$index+1]];
        $s=$game->act($host,['accion'=>'invitar','amigo'=>$guest]);$room=$s['sala']['id'];$rooms[$host]=$rooms[$guest]=$room;
        [$cookies,$csrf]=$sessions[$guest];
        [, $notice]=api('/friends?invitaciones=1');
        duoCheck($notice['invitaciones']===[],'Sin consumidor la invitación se guarda pero el aviso queda pendiente');
        $pending = $db->prepare("SELECT COUNT(*) FROM eventos_sala WHERE sala_id=? AND destinatario_id=? AND tipo='invitacion_creada' AND procesado_en IS NULL");
        $pending->execute([$room,$guest]);
        duoCheck((int) $pending->fetchColumn()===1,'La transacción registra un evento pendiente para el invitado');
        $worker = proc_open(
            [PHP_BINARY, dirname(__DIR__).'/tools/notificaciones.php', '--once'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($worker)) throw new RuntimeException('No inició el consumidor de notificaciones.');
        $workerOutput = stream_get_contents($pipes[1]);
        $workerError = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        duoCheck(proc_close($worker)===0 && preg_match('/Eventos procesados: [1-9]/',$workerOutput)===1,
            'El consumidor independiente procesa el evento pendiente. '.$workerError);
        (new TrainerService\SalaEvents($db))->deliverPending();
        $count = $db->prepare('SELECT COUNT(*) FROM notificaciones_sala n JOIN eventos_sala e ON e.id=n.evento_id WHERE e.sala_id=? AND e.destinatario_id=?');
        $count->execute([$room,$guest]);
        duoCheck((int) $count->fetchColumn()===1,'Volver a ejecutar el consumidor conserva un solo aviso');
        [, $notice]=api('/friends?invitaciones=1');
        duoCheck(($notice['invitaciones'][0]['tipo']??null)==='duo','La invitación aparece en el aviso global de amigos');
        duoReject(fn()=>$game->act($host,['id'=>$room,'accion'=>'aceptar']),'El anfitrión no acepta su propia invitación');
        duoReject(fn()=>$game->act($users[4],['id'=>$room,'accion'=>'aceptar']),'Un ajeno no puede entrar a la sala');
        $game->act($guest,['id'=>$room,'accion'=>'aceptar']);
        [$cookies,$csrf]=$sessions[$guest];
        [, $notice]=api('/friends?invitaciones=1');
        duoCheck($notice['invitaciones']===[],'Aceptar la invitación oculta el aviso');
        duoReject(fn()=>$game->act($host,['id'=>$room,'accion'=>'buscar']),'No buscar hasta que ambos elijan');
        duoReject(fn()=>$game->act($host,['id'=>$room,'accion'=>'elegir','equipo_id'=>$teams[$guest][0],'pokemon_id'=>$teams[$guest][1]]),'No elegir el Pokémon de otra persona');
        foreach([$host,$guest] as $uid)$game->act($uid,['id'=>$room,'accion'=>'elegir','equipo_id'=>$teams[$uid][0],'pokemon_id'=>$teams[$uid][1]]);
        $s=$game->act($host,['id'=>$room,'accion'=>'buscar']);
        duoCheck($s['sala']['estado']===($index===0?'buscando':'activa'),'Empareja solo dos dúos completos');
    }
    foreach(array_slice($users,0,4) as $uid){$s=$game->state($uid);duoCheck($s['sala']['estado']==='activa','Los cuatro ven la misma partida activa');duoCheck(count($s['sala']['batalla']['jugadores'])===4,'Cuatro entrenadores diferentes');}
    foreach(array_slice($users,0,3) as $uid){$s=$game->act($uid,['id'=>$rooms[$uid],'accion'=>'iniciativa']);duoCheck($s['sala']['batalla']['iniciativa']['orden']===[],'No revela el orden hasta que confirmen los cuatro');}
    duoReject(fn()=>$game->act($users[0],['id'=>$rooms[$users[0]],'accion'=>'atacar','objetivo'=>$users[2],'movimiento'=>'tackle']),'No atacar sin preparación completa');
    $uid=$users[3];$s=$game->act($uid,['id'=>$rooms[$uid],'accion'=>'iniciativa']);$battle=$s['sala']['batalla'];$order=$battle['iniciativa']['orden'];
    duoCheck(count(array_unique($order))===4,'Sorteo sin participantes duplicados');
    foreach(array_slice($users,0,4) as $uid)duoCheck($game->state($uid)['sala']['batalla']['iniciativa']['orden']===$order,'Orden compartido entre las cuatro sesiones');
    foreach([0,1,2] as $index)duoCheck($battle['jugadores'][$order[$index]]['equipo']!==$battle['jugadores'][$order[$index+1]]['equipo'],'Los dúos alternan turnos');
    $game->act($users[0],['id'=>$rooms[$users[0]],'accion'=>'iniciativa']);
    duoCheck($game->state($users[0])['sala']['batalla']['iniciativa']['orden']===$order,'Repetir confirmación no vuelve a sortear');
    $first=(int)$order[0];$wrong=(int)$order[1];
    duoReject(fn()=>$game->act($wrong,['id'=>$rooms[$wrong],'accion'=>'atacar','version'=>$battle['version'],'objetivo'=>$first,'movimiento'=>'tackle']),'No atacar fuera de turno');
    $friendly=array_values(array_filter($battle['jugadores'],fn($p)=>$p['equipo']===$battle['jugadores'][$first]['equipo']&&$p['id']!==$first))[0]['id'];
    duoReject(fn()=>$game->act($first,['id'=>$rooms[$first],'accion'=>'atacar','version'=>$battle['version'],'objetivo'=>$friendly,'movimiento'=>'tackle']),'No atacar al compañero de dúo');
    for($n=0;$n<40;$n++){
        $uid=(int)$battle['turno'];$team=$battle['jugadores'][$uid]['equipo'];
        $targets=array_values(array_filter($battle['jugadores'],fn($p)=>$p['equipo']!==$team&&$p['pokemon']['hp_actual']>0));
        $view=$game->state($uid)['sala']['batalla'];
        $move=$view['jugadores'][$uid]['pokemon']['movimientos'][0]['nombre'];
        $s=$game->act($uid,['id'=>$rooms[$uid],'accion'=>'atacar','version'=>$battle['version'],'objetivo'=>$targets[0]['id'],'movimiento'=>$move]);$battle=$s['sala']['batalla'];
        duoCheck(count($battle['historial'])>0,'Ataque queda registrado');
        if($s['sala']['estado']==='finalizada')break;
        duoCheck($battle['jugadores'][$battle['turno']]['pokemon']['hp_actual']>0,'Se omiten entrenadores debilitados');
    }
    duoCheck($s['sala']['estado']==='finalizada'&&$battle['ganador']!==null,'La partida termina al quedar un dúo');
    foreach(array_slice($users,0,4) as $uid)duoCheck($game->state($uid)['sala']['estado']==='finalizada','Todos ven el resultado final');
    echo "OK: $checks comprobaciones por HTTP de cuatro sesiones, ataques reales, emparejamiento y turnos compartidos.\n";
} finally {
    foreach($users as $uid)$db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$uid]);
}
