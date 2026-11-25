<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/trainer-service/bootstrap.php';
use TrainerService\DuoService;
$db=require dirname(__DIR__).'/backend/trainer-service/config/database.php';
$users=[];$teams=[];$checks=0;
function duoCheck(bool $ok,string $message):void { global $checks; if(!$ok)throw new RuntimeException($message);$checks++; }
function duoReject(callable $f,string $message):void {try{$f();}catch(DomainException){duoCheck(true,$message);return;}throw new RuntimeException($message);}
try {
    foreach([7,25,4,1,10] as $index=>$pokemon){
        $handle='duotest_'.bin2hex(random_bytes(5));
        $db->prepare('INSERT INTO usuarios(nombre,usuario,email,password) VALUES(?,?,?,?)')->execute(['Prueba dúo',$handle,$handle.'@example.invalid',password_hash(bin2hex(random_bytes(10)),PASSWORD_DEFAULT)]);
        $uid=(int)$db->lastInsertId();$users[]=$uid;
        $db->prepare('INSERT INTO coleccion_pokemon(usuario_id,pokemon_id) VALUES(?,?)')->execute([$uid,$pokemon]);
        $db->prepare('INSERT INTO equipos(usuario_id,nombre) VALUES(?,?)')->execute([$uid,'Equipo de prueba']);
        $team=(int)$db->lastInsertId();$teams[$uid]=[$team,$pokemon];
        $db->prepare('INSERT INTO miembros_equipo(equipo_id,posicion,pokemon_id) VALUES(?,1,?)')->execute([$team,$pokemon]);
    }
    foreach([[$users[0],$users[1]],[$users[2],$users[3]]] as $friends){sort($friends);$db->prepare('INSERT INTO amistades(usuario_menor,usuario_mayor,solicitante,aceptada) VALUES(?,?,?,1)')->execute([...$friends,$friends[0]]);}
    $roster=function($uid,$team,$lineup)use($db){
        $q=$db->prepare('SELECT m.pokemon_id FROM miembros_equipo m JOIN equipos e ON e.id=m.equipo_id WHERE e.usuario_id=? AND e.id=? AND m.pokemon_id=?');$q->execute([$uid,$team,$lineup[0]]);
        if(!$q->fetchColumn())throw new DomainException('Ese Pokémon no pertenece a tu equipo.');
        return [['id'=>$lineup[0],'nombre'=>'Pokémon '.$lineup[0],'hp_actual'=>30,'hp_maximo'=>30,'movimientos'=>[['nombre'=>'tackle','nombre_mostrado'=>'Placaje','potencia'=>40]]]];
    };
    $game=new DuoService($db,$roster,function($a,$d,$move){if($move!=='tackle')throw new DomainException('Ataque inválido.');$d['hp_actual']=max(0,$d['hp_actual']-10);return ['atacante'=>$a,'defensor'=>$d,'mensaje'=>'Placaje · 10 de daño'];});
    duoReject(fn()=>$game->act($users[0],['accion'=>'invitar','amigo'=>$users[4]]),'No invitar a personas fuera de la lista de amigos');
    $rooms=[];
    foreach([0,2] as $index){
        [$host,$guest]=[$users[$index],$users[$index+1]];
        $s=$game->act($host,['accion'=>'invitar','amigo'=>$guest]);$room=$s['sala']['id'];$rooms[$host]=$rooms[$guest]=$room;
        duoReject(fn()=>$game->act($host,['id'=>$room,'accion'=>'aceptar']),'El anfitrión no acepta su propia invitación');
        duoReject(fn()=>$game->act($users[4],['id'=>$room,'accion'=>'aceptar']),'Un ajeno no puede entrar a la sala');
        $game->act($guest,['id'=>$room,'accion'=>'aceptar']);
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
        $s=$game->act($uid,['id'=>$rooms[$uid],'accion'=>'atacar','version'=>$battle['version'],'objetivo'=>$targets[0]['id'],'movimiento'=>'tackle']);$battle=$s['sala']['batalla'];
        duoCheck(count($battle['historial'])>0,'Ataque queda registrado');
        if($s['sala']['estado']==='finalizada')break;
        duoCheck($battle['jugadores'][$battle['turno']]['pokemon']['hp_actual']>0,'Se omiten entrenadores debilitados');
    }
    duoCheck($s['sala']['estado']==='finalizada'&&$battle['ganador']!==null,'La partida termina al quedar un dúo');
    foreach(array_slice($users,0,4) as $uid)duoCheck($game->state($uid)['sala']['estado']==='finalizada','Todos ven el resultado final');
    echo "OK: $checks comprobaciones de amigos, selección, emparejamiento 2v2, confirmación, turnos y resultado.\n";
} finally {
    foreach($users as $uid)$db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$uid]);
}
