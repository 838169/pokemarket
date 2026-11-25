<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/trainer-service/bootstrap.php';
use TrainerService\BattleInitiative as I;
function initiativeCheck(bool $ok): void { if (!$ok) throw new RuntimeException('Falló la preparación de turnos.'); }
$game = ['iniciativa'=>I::create(['jugador','rival'])];
I::confirm($game,'jugador'); initiativeCheck($game['iniciativa']['orden']===[]);
try { I::requireReady($game); throw new RuntimeException('Permitió atacar antes de confirmar.'); } catch (DomainException) {}
I::confirm($game,'rival',fn($max)=>0); I::requireReady($game);
initiativeCheck($game['iniciativa']['orden']===['rival','jugador']);
$copy=$game; I::confirm($game,'jugador'); initiativeCheck($copy===$game);
$group=['iniciativa'=>I::create(['1','2','3','4'])];
foreach (['1','2','3'] as $id) { I::confirm($group,$id); initiativeCheck($group['iniciativa']['orden']===[]); }
I::confirm($group,'4'); initiativeCheck(count(array_unique($group['iniciativa']['orden']))===4);
foreach (['jugador', 'rival'] as $index => $first) {
    $ai = ['iniciativa' => I::create(['jugador', 'rival'], ['rival'])];
    I::confirm($ai, 'jugador', fn($max) => 1 - $index);
    initiativeCheck($ai['iniciativa']['caras'][$index] === $first);
    initiativeCheck($ai['iniciativa']['orden'][0] === $first);
}
$three = ['iniciativa' => I::create(['ana', 'leo', 'luz'])];
I::confirm($three, 'ana');
I::confirm($three, 'leo');
initiativeCheck($three['iniciativa']['orden'] === []);
I::confirm($three, 'luz', fn($max) => $max === 2 ? 0 : 1);
initiativeCheck($three['iniciativa']['caras'][2] === $three['iniciativa']['orden'][0]);
$invalid=['iniciativa'=>I::create(['1','2'])];
try { I::confirm($invalid,'3'); throw new RuntimeException('Aceptó un ajeno.'); } catch (DomainException) {}
$sizes = [2 => [2, 4, 6], 3 => [3, 6], 4 => [4]];
foreach ($sizes as $people => $totals) {
    initiativeCheck(\TrainerService\TeamRoomRules::totals($people) === $totals);
    foreach ($totals as $total) {
        $rules = \TrainerService\TeamRoomRules::configuration($people, $total);
        initiativeCheck($rules['por_persona'] * $people === $total);
    }
}
foreach ([[4, 6], [4, 8], [3, 4], [2, 3], [5, 5]] as [$people, $total]) {
    try {
        \TrainerService\TeamRoomRules::configuration($people, $total);
        throw new RuntimeException('Permitió un reparto inválido.');
    } catch (DomainException) {}
}
echo "OK: todos confirman, un solo sorteo, cuatro participantes y rival automático.\n";
