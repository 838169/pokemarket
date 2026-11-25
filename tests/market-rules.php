<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\MarketCatalog;
use TrainerService\GameEconomy;
function assertRule(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$all = MarketCatalog::all();
assertRule(count($all) === 1025, 'Catálogo de especies completo');
foreach ($all as $id => $p) {
    assertRule(is_numeric($p['altura']) && $p['altura'] > 0, 'Altura disponible en metros');
    assertRule(
        $id === $p['id'] && count($p['estadisticas']) === 6,
        'Identidad y seis estadísticas',
    );
    assertRule(array_sum($p['precio_desglose']) === $p['precio'], 'Desglose coincide con precio');
    assertRule($p['precio'] > 0, 'Todos tienen precio positivo');
    $weaknesses = array_column($p['debilidades'], 'multiplicador', 'tipo');
    foreach (TrainerService\BattleRules::TYPES as $attackType) {
        $multiplier = TrainerService\BattleRules::effectiveness($attackType, $p['tipos']);
        assertRule(
            ($weaknesses[$attackType] ?? 1) === ($multiplier > 1 ? $multiplier : 1),
            'Las debilidades de la ficha coinciden con el daño en batalla',
        );
    }
    assertRule(
        count($p['movimientos']) >= 1 && count($p['movimientos']) <= 4,
        'Todos pueden combatir',
    );
    if ($p['categoria'] === 'iniciales') {
        assertRule($p['precio'] <= GameEconomy::GIFT, 'Regalo suficiente para cualquier inicial');
    }
    if ($p['legendario'] || $p['mitico']) {
        assertRule($p['categoria'] === 'legendarios', 'Rareza oficial tiene prioridad');
    }
}
assertRule(
    GameEconomy::bonus(['energia' => 59, 'vinculo' => 100]) === 5,
    'Vínculo sin bonus de energía',
);
assertRule(GameEconomy::bonus(['energia' => 60, 'vinculo' => 100]) === 10, 'Bonus máximo acotado');
assertRule(
    TrainerService\BattleRules::nextIndex([['hp_actual' => 100], ['hp_actual' => 0]], 1) === 0,
    'Una reserva anterior evita una derrota falsa',
);
assertRule(
    TrainerService\BattleRules::nextIndex([['hp_actual' => 0], ['hp_actual' => 0]], 1) === null,
    'Sin reservas se termina la batalla',
);
echo "OK: 1025 precios y movimientos, categorías, iniciales asequibles, bonus y reservas de combate.\n";
