<?php
declare(strict_types=1);
namespace TrainerService;
use PDO;
use DomainException;

final class DuoService
{
    public function __construct(private PDO $db, private \Closure $roster, private \Closure $attack) {}
    private function query(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->db->prepare($sql); $q->execute($args); return $q;
    }
    private function row(int $uid, ?string $id = null): array|false
    {
        return $this->query("SELECT s.*,m.aceptado FROM salas_duo s JOIN integrantes_sala m ON m.sala_id=s.id WHERE m.usuario_id=? AND s.vence>? " .
            ($id ? 'AND s.id=? ' : "AND s.estado<>'cerrada' ") .
            "ORDER BY CASE WHEN s.estado IN('invitada','preparando','buscando','activa') THEN 0 ELSE 1 END,s.creado DESC,s.id DESC LIMIT 1", $id ? [$uid,time(),$id] : [$uid,time()])->fetch(PDO::FETCH_ASSOC);
    }
    private function members(string $id): array
    {
        $rows = $this->query('SELECT u.id,u.usuario,m.aceptado FROM integrantes_sala m JOIN usuarios u ON u.id=m.usuario_id WHERE m.sala_id=? ORDER BY u.id',[$id])->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($m)=>['id'=>(int)$m['id'],'usuario'=>$m['usuario'],'aceptado'=>(bool)$m['aceptado']],$rows);
    }
    private function config(array $data): array
    {
        return $data['configuracion'] ?? TeamRoomRules::configuration(2,2);
    }
    private function selection(array $selection): array
    {
        return $selection['pokemones'] ?? [['equipo_id'=>$selection['equipo_id'],'pokemon_id'=>$selection['pokemon_id'],'nombre'=>$selection['nombre'] ?? 'Pokémon']];
    }
    private function selectedPokemon(int $uid, int $team, int $pokemon): array
    {
        $valid = $this->query('SELECT 1 FROM equipos e JOIN miembros_equipo m ON m.equipo_id=e.id JOIN coleccion_pokemon c ON c.usuario_id=e.usuario_id AND c.pokemon_id=m.pokemon_id WHERE e.usuario_id=? AND e.id=? AND m.pokemon_id=?',[$uid,$team,$pokemon])->fetchColumn();
        if (!$valid) throw new DomainException('El equipo elegido cambió. Detén la búsqueda y elige Pokémon de tus equipos.');
        return ($this->roster)($uid,$team,[$pokemon]);
    }
    private function save(string $id, array $data, string $status): void
    {
        $this->query('UPDATE salas_duo SET datos=?,estado=?,vence=? WHERE id=?',[json_encode($data,JSON_THROW_ON_ERROR),$status,time()+3600,$id]);
    }
    private function preparedPlayers(array $data, array $members, int $team): array
    {
        $config=$this->config($data);
        if (count($members)!==$config['personas'] || count($data['selecciones'])!==$config['personas'] || in_array(false,array_column($members,'aceptado'),true)) throw new DomainException('Todos deben aceptar y completar su selección antes de buscar.');
        $players=[];
        foreach($members as $member){
            $uid=$member['id']; $selection=$data['selecciones'][$uid]??null;
            if(!$selection)throw new DomainException('Falta la selección de un integrante.');
            $items=$this->selection($selection);
            if(count($items)!==$config['por_persona'] || count(array_unique(array_column($items,'pokemon_id')))!==count($items))throw new DomainException('Todos deben llevar la misma cantidad de Pokémon distintos.');
            $roster=[];
            foreach($items as $item)$roster[]=$this->selectedPokemon($uid,(int)$item['equipo_id'],(int)$item['pokemon_id'])[0];
            $players[(string)$uid]=['id'=>$uid,'usuario'=>$member['usuario'],'equipo'=>$team,'pokemon'=>$roster[0],'pokemon_equipo'=>$roster,'activo'=>0];
        }
        return $players;
    }
    private function updateActive(array &$player,array $pokemon): void
    {
        $roster=$player['pokemon_equipo']??[$player['pokemon']]; $active=$player['activo']??0;
        $roster[$active]=$pokemon;
        if($pokemon['hp_actual']<=0)foreach($roster as $index=>$candidate)if($candidate['hp_actual']>0){$active=$index;break;}
        $player['pokemon_equipo']=$roster; $player['activo']=$active; $player['pokemon']=$roster[$active];
    }
    private function finishAction(array &$game,string &$status,string $root): void
    {
        $game['version']++; $game['historial']=array_slice($game['historial'],-15);
        $alive=array_filter($game['jugadores'],fn($p)=>$p['pokemon']['hp_actual']>0);
        $teams=array_unique(array_column($alive,'equipo'));
        if(count($teams)<2){
            $game['ganador']=$teams?(int)reset($teams):-1;$status='finalizada';
            $this->query("UPDATE salas_duo SET estado='finalizada' WHERE batalla_id=?",[$root]);return;
        }
        $order=$game['iniciativa']['orden'];$index=array_search($game['turno'],$order,true);
        for($n=1;$n<=count($order);$n++){
            $next=($index+$n)%count($order);
            if($game['jugadores'][$order[$next]]['pokemon']['hp_actual']>0){if($next<=$index)$game['ronda']++;$game['turno']=$order[$next];break;}
        }
    }
    public function state(int $uid): array
    {
        $row=$this->row($uid);$room=null;
        if($row){
            $data=json_decode($row['datos'],true,512,JSON_THROW_ON_ERROR);
            if(!in_array($uid,$data['ocultos']??[],true)){
                $data['configuracion']=$this->config($data);
                unset($data['batalla'], $data['ocultos']);
                foreach($data['selecciones'] as &$selection)$selection['pokemones']=$this->selection($selection);
                unset($selection);
                $room=['id'=>$row['id'],'anfitrion'=>(int)$row['anfitrion'],'companero'=>(int)$row['companero'],'estado'=>$row['estado'],'datos'=>$data,'integrantes'=>$this->members($row['id'])];
                if($row['batalla_id']){
                    $battle=$row['batalla_id']===$row['id']?$row:$this->query('SELECT datos,estado FROM salas_duo WHERE id=?',[$row['batalla_id']])->fetch(PDO::FETCH_ASSOC);
                    if($battle){
                        $game=json_decode($battle['datos'],true,512,JSON_THROW_ON_ERROR);$room['estado']=$battle['estado'];$room['batalla']=$game['batalla'];
                        foreach($room['batalla']['jugadores'] as &$player)if($player['id']!==$uid){
                            unset($player['pokemon']['movimientos']);
                            if(isset($player['pokemon_equipo']))foreach($player['pokemon_equipo'] as &$pokemon)unset($pokemon['movimientos']);
                            unset($pokemon);
                        }
                        unset($player);
                    }
                }
            }
        }
        if($room&&isset($room['batalla']))return ['success'=>true,'usuario_id'=>$uid,'sala'=>$room,'amigos'=>[],'opciones'=>[]];
        $friends=$this->query('SELECT u.id,u.usuario FROM amistades a JOIN usuarios u ON u.id=IF(a.usuario_menor=?,a.usuario_mayor,a.usuario_menor) WHERE ? IN(a.usuario_menor,a.usuario_mayor) AND a.aceptada=1 ORDER BY u.usuario',[$uid,$uid])->fetchAll(PDO::FETCH_ASSOC);
        $choices=$this->query('SELECT e.id equipo_id,e.nombre equipo,m.pokemon_id FROM equipos e JOIN miembros_equipo m ON m.equipo_id=e.id JOIN coleccion_pokemon c ON c.usuario_id=e.usuario_id AND c.pokemon_id=m.pokemon_id WHERE e.usuario_id=? ORDER BY e.nombre,m.posicion',[$uid])->fetchAll(PDO::FETCH_ASSOC);
        foreach($choices as &$choice)$choice['nombre']=MarketCatalog::get((int)$choice['pokemon_id'])['nombre'];unset($choice);
        return ['success'=>true,'usuario_id'=>$uid,'sala'=>$room,'amigos'=>$friends,'opciones'=>$choices];
    }
    public function act(int $uid,array $input): array
    {
        $action=$input['accion']??'';$this->db->beginTransaction();
        try{
            if($action==='invitar'){
                $people=$input['personas']??2;$config=TeamRoomRules::configuration($people,$input['total_pokemon']??$people);
                $friends=$input['amigos']??[$input['amigo']??null];
                if(!is_array($friends)||!array_is_list($friends)||count($friends)!==$people-1||count(array_unique($friends,SORT_REGULAR))!==count($friends))throw new DomainException('Invita exactamente a los amigos que faltan para completar el grupo.');
                foreach($friends as $friend)if(!is_int($friend)||$friend<=0||$friend===$uid)throw new DomainException('Elige amigos diferentes para la sala.');
                $ids=[$uid,...$friends];sort($ids);$marks=implode(',',array_fill(0,count($ids),'?'));
                $this->query("SELECT id FROM usuarios WHERE id IN($marks) ORDER BY id FOR UPDATE",$ids)->fetchAll();
                foreach($friends as $friend)if(!$this->query('SELECT 1 FROM amistades WHERE usuario_menor=? AND usuario_mayor=? AND aceptada=1',[min($uid,$friend),max($uid,$friend)])->fetchColumn())throw new DomainException('Solo puedes invitar a amigos agregados.');
                if($this->query("SELECT 1 FROM salas_duo s JOIN integrantes_sala m ON m.sala_id=s.id WHERE s.estado IN('invitada','preparando','buscando','activa') AND s.vence>? AND m.usuario_id IN($marks) LIMIT 1",[time(),...$ids])->fetchColumn())throw new DomainException('Uno de los integrantes ya está preparando o jugando otra sala.');
                if($this->query("SELECT 1 FROM duelos_jugadores WHERE estado='activa' AND vence>? AND (retador IN($marks) OR invitado IN($marks)) LIMIT 1",[time(),...$ids,...$ids])->fetchColumn())throw new DomainException('Uno de los integrantes está en un duelo individual.');
                $names=$this->query("SELECT id,usuario FROM usuarios WHERE id IN($marks)",$ids)->fetchAll(PDO::FETCH_KEY_PAIR);
                $data=['aceptada'=>false,'selecciones'=>[],'nombres'=>$names,'configuracion'=>$config];$id=bin2hex(random_bytes(12));
                $this->query("INSERT INTO salas_duo(id,anfitrion,companero,estado,datos,vence) VALUES(?,?,?,'invitada',?,?)",[$id,$uid,$friends[0],json_encode($data,JSON_THROW_ON_ERROR),time()+900]);
                foreach($ids as $member)$this->query('INSERT INTO integrantes_sala(sala_id,usuario_id,aceptado) VALUES(?,?,?)',[$id,$member,$member===$uid?1:0]);
                (new SalaEvents($this->db))->invite($id, $friends);
            }else{
                if($action==='buscar')$this->query("SELECT id FROM salas_duo WHERE estado IN('invitada','preparando','buscando') AND vence>? ORDER BY id FOR UPDATE",[time()])->fetchAll();
                $row=$this->row($uid,is_string($input['id']??null)?$input['id']:null);
                if(!$row)throw new DomainException('La sala ya no está disponible.');
                if(!in_array($action,['aceptar','salir'],true)&&!(bool)$row['aceptado'])throw new DomainException('Primero acepta la invitación a esta sala.');
                $root=$row['batalla_id']?:$row['id'];$locked=$this->query('SELECT * FROM salas_duo WHERE id=? FOR UPDATE',[$root])->fetch(PDO::FETCH_ASSOC);
                if(!$locked||($root===$row['id']&&$locked['batalla_id']&&$locked['batalla_id']!==$root))throw new DomainException('La sala cambió. Actualiza e inténtalo nuevamente.');
                $data=json_decode($locked['datos'],true,512,JSON_THROW_ON_ERROR);$status=$locked['estado'];$config=$this->config($data);
                if($action==='salir'){
                    if($status==='activa'){
                        $own=$data['batalla']['jugadores'][(string)$uid]??null;
                        if(!$own)throw new DomainException('No participas en esta partida.');
                        $data['batalla']['ganador']=1-$own['equipo'];$data['batalla']['historial'][]='@'.$own['usuario'].' se retiró. Su equipo se rinde.';$status='finalizada';
                        $this->query("UPDATE salas_duo SET estado='finalizada' WHERE batalla_id=?",[$root]);
                    }elseif($status==='finalizada'){
                        $ownData=json_decode($row['datos'],true,512,JSON_THROW_ON_ERROR);$ownData['ocultos'][]=$uid;
                        if($root!==$row['id'])$this->save($row['id'],$ownData,'finalizada');else$data['ocultos'][]=$uid;
                    }else$status='cerrada';
                }elseif($action==='aceptar'&&in_array($status,['invitada','preparando'],true)){
                    if((int)$row['anfitrion']===$uid)throw new DomainException('No puedes aceptar tu propia invitación.');
                    if(!$this->query('SELECT 1 FROM amistades WHERE usuario_menor=? AND usuario_mayor=? AND aceptada=1',[min($uid,(int)$row['anfitrion']),max($uid,(int)$row['anfitrion'])])->fetchColumn())throw new DomainException('La invitación debe venir de una persona de tu lista de amigos.');
                    $this->query('UPDATE integrantes_sala SET aceptado=1 WHERE sala_id=? AND usuario_id=?',[$row['id'],$uid]);
                    $members=$this->members($row['id']);$data['aceptada']=!in_array(false,array_column($members,'aceptado'),true);if($data['aceptada'])$status='preparando';
                }elseif($action==='elegir'&&in_array($status,['invitada','preparando'],true)){
                    $items=$input['pokemones']??[['equipo_id'=>$input['equipo_id']??null,'pokemon_id'=>$input['pokemon_id']??null]];
                    if(!is_array($items)||!array_is_list($items)||count($items)!==$config['por_persona'])throw new DomainException('Debes elegir exactamente '.$config['por_persona'].' Pokémon para que todos jueguen por igual.');
                    $selected=[];$seen=[];
                    foreach($items as $item){
                        if(!is_array($item)||!is_int($item['equipo_id']??null)||!is_int($item['pokemon_id']??null)||$item['equipo_id']<=0||$item['pokemon_id']<=0||isset($seen[$item['pokemon_id']]))throw new DomainException('Elige Pokémon distintos de tus propios equipos.');
                        $pokemon=$this->selectedPokemon($uid,$item['equipo_id'],$item['pokemon_id'])[0];$seen[$item['pokemon_id']]=true;
                        $selected[]=['equipo_id'=>$item['equipo_id'],'pokemon_id'=>$item['pokemon_id'],'nombre'=>$pokemon['nombre']];
                    }
                    $data['selecciones'][$uid]=$selected[0]+['pokemones'=>$selected];
                }elseif($action==='buscar'&&in_array($status,['preparando','buscando'],true)){
                    if((int)$row['anfitrion']!==$uid)throw new DomainException('La búsqueda la inicia el anfitrión.');
                    $ownPlayers=$this->preparedPlayers($data,$this->members($root),0);$status='buscando';
                    $candidates=$this->query("SELECT * FROM salas_duo WHERE estado='buscando' AND vence>? AND id<>? AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.configuracion.personas')) AS UNSIGNED),2)=? AND COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(datos,'$.configuracion.por_persona')) AS UNSIGNED),1)=? ORDER BY creado,id FOR UPDATE",[time(),$root,$config['personas'],$config['por_persona']])->fetchAll(PDO::FETCH_ASSOC);
                    foreach($candidates as $candidate){
                        $other=json_decode($candidate['datos'],true,512,JSON_THROW_ON_ERROR);
                        try{$otherPlayers=$this->preparedPlayers($other,$this->members($candidate['id']),1);}catch(DomainException){$other['selecciones']=[];$this->save($candidate['id'],$other,'preparando');continue;}
                        $players=$ownPlayers+$otherPlayers;
                        if(count($players)!==$config['personas']*2)throw new DomainException('Una persona no puede aparecer en los dos equipos.');
                        $data['batalla']=['id'=>$root,'configuracion'=>$config,'jugadores'=>$players,'iniciativa'=>BattleInitiative::create(array_keys($players)),'turno'=>null,'ronda'=>1,'version'=>1,'ganador'=>null,'historial'=>[]];
                        $this->query("UPDATE salas_duo SET batalla_id=?,estado='activa',vence=? WHERE id IN(?,?)",[$root,time()+3600,$root,$candidate['id']]);$status='activa';break;
                    }
                }elseif($action==='detener'&&$status==='buscando')$status='preparando';
                elseif($action==='iniciativa'&&$status==='activa'){
                    $game=&$data['batalla'];BattleInitiative::confirm($game,(string)$uid);
                    if($game['iniciativa']['orden']&&$game['turno']===null){
                        $draw=$game['iniciativa']['orden'];$firstTeam=$game['jugadores'][$draw[0]]['equipo'];
                        $a=array_values(array_filter($draw,fn($id)=>$game['jugadores'][$id]['equipo']===$firstTeam));$b=array_values(array_filter($draw,fn($id)=>$game['jugadores'][$id]['equipo']!==$firstTeam));
                        $order=[];foreach($a as $index=>$id){$order[]=$id;$order[]=$b[$index];}$game['iniciativa']['orden']=$order;$game['turno']=$order[0];
                    }unset($game);
                }elseif(in_array($action,['atacar','cambiar'],true)&&$status==='activa'){
                    $game=&$data['batalla'];BattleInitiative::requireReady($game);
                    if(($input['version']??null)!==$game['version'])throw new DomainException('La partida cambió. Revisa el turno actual antes de actuar.');
                    if((int)$game['turno']!==$uid)throw new DomainException('Es el turno de otro entrenador.');
                    $own=&$game['jugadores'][(string)$uid];
                    if($action==='cambiar'){
                        $roster=$own['pokemon_equipo']??[$own['pokemon']];$target=$input['pokemon_id']??null;$index=array_search($target,array_column($roster,'id'),true);
                        if($index===false||$index===($own['activo']??0)||$roster[$index]['hp_actual']<=0)throw new DomainException('Elige otro Pokémon disponible de tu selección.');
                        $own['activo']=$index;$own['pokemon']=$roster[$index];$game['historial'][]='@'.$own['usuario'].' cambió a '.$own['pokemon']['nombre'].'.';
                    }else{
                        $target=filter_var($input['objetivo']??null,FILTER_VALIDATE_INT);
                        $enemy=$game['jugadores'][$target]??null;
                        if(!$enemy||$own['equipo']===$enemy['equipo']||$enemy['pokemon']['hp_actual']<=0)throw new DomainException('Elige un oponente que siga en combate.');
                        $enemy=&$game['jugadores'][$target];
                        $result=($this->attack)($own['pokemon'],$enemy['pokemon'],$input['movimiento']??'');
                        $this->updateActive($own,$result['atacante']);$this->updateActive($enemy,$result['defensor']);
                        $game['historial'][]='@'.$own['usuario'].' → @'.$enemy['usuario'].': '.$result['mensaje'];unset($enemy);
                    }
                    $this->finishAction($game,$status,$root);unset($game,$own);
                }else throw new DomainException('Esa acción no está disponible en esta fase de la sala.');
                $this->save($root,$data,$status);
            }
            $this->db->commit();
        }catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        return $this->state($uid);
    }
}
