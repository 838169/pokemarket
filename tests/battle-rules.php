<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\BattleRules;
function rule(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
rule(BattleRules::effectiveness('water', ['fire']) === 2.0, 'Ventaja de Agua');
rule(BattleRules::effectiveness('electric', ['ground']) === 0.0, 'Inmunidad de Tierra');
rule(BattleRules::effectiveness('ice', ['dragon', 'flying']) === 4.0, 'Doble debilidad');
rule(BattleRules::effectiveness('fire', ['water', 'dragon']) === 0.25, 'Doble resistencia');
rule(
    BattleRules::weaknesses(['electric']) === [['tipo' => 'ground', 'multiplicador' => 2.0]],
    'Pikachu conserva únicamente su debilidad a Tierra',
);
$charizard = array_column(BattleRules::weaknesses(['fire', 'flying']), 'multiplicador', 'tipo');
rule($charizard['rock'] === 4.0, 'Charizard recibe Roca por cuatro');
rule(!isset($charizard['ground']), 'La inmunidad Volador cancela Tierra');
rule(!isset($charizard['ice']), 'La resistencia Fuego cancela la debilidad Hielo');
rule(
    BattleRules::weaknesses(['bug', 'steel']) === [['tipo' => 'fire', 'multiplicador' => 4.0]],
    'Scizor tiene una única doble debilidad',
);
rule(
    BattleRules::weaknesses(['electric', 'electric']) === BattleRules::weaknesses(['electric']),
    'No duplicar un tipo en el cálculo',
);
$actor = ['tipos' => ['normal'], 'estadisticas' => ['ataque' => 100, 'ataque_especial' => 200]];
$target = ['estadisticas' => ['defensa' => 100, 'defensa_especial' => 100]];
$physical = ['potencia' => 40, 'tipo' => 'normal', 'clase' => 'fisico'];
$special = $physical;
$special['clase'] = 'especial';
$damage = BattleRules::damage($actor, $target, $physical, 1.0);
rule($damage > 0, 'Daño positivo');
rule(BattleRules::damage($actor, $target, $physical, 0.0) === 0, 'Inmunidad sin daño');
rule(
    BattleRules::damage($actor, $target, $special, 1.0) > $damage,
    'Ataque especial utiliza su estadística',
);
rule(
    BattleRules::damage($actor, $target, $physical, 1.0, 85) < $damage,
    'Variación de daño acotada',
);
rule(
    BattleRules::nextIndex([['hp_actual' => 100], ['hp_actual' => 0]], 1) === 0,
    'Reserva viva antes del activo',
);
rule(BattleRules::nextIndex([['hp_actual' => 0]], 0) === null, 'Sin reservas');
echo "OK: efectividad, inmunidades, daño físico/especial, variación y sustituciones.\n";
