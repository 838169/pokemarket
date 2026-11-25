<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/trainer-service/bootstrap.php';
use TrainerService\AbilityRules as A;
use TrainerService\BattleRules as B;
use TrainerService\MarketCatalog as M;
use TrainerService\AbilityService;

$checks = 0;
function verifyAbility(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function fighter(string $ability, array $types = ['normal']): array
{
    return [
        'id' => 1,
        'nombre' => 'Prueba',
        'nivel' => 50,
        'habilidad_activa' => ['id' => $ability, 'nombre' => $ability],
        'tipos' => $types,
        'estadisticas' => [
            'ataque' => 100,
            'defensa' => 100,
            'ataque_especial' => 100,
            'defensa_especial' => 100,
            'velocidad' => 100,
        ],
        'hp_actual' => 160,
        'hp_maximo' => 160,
        'movimientos' => [],
        'cambios' => [],
    ];
}
function battlefield(array $a, array $b): array
{
    return [
        'estado' => 'en_curso',
        'equipo_jugador' => ['pokemon' => [$a]],
        'equipo_rival' => ['pokemon' => [$b]],
        'activo_jugador' => 0,
        'activo_rival' => 0,
        'historial' => [],
    ];
}
$normal = fighter('');
verifyAbility(!A::immobilized($normal, 1), 'Sin parálisis siempre puede actuar');
$paralyzed = $normal;
$paralyzed['estado_primario'] = 'paralisis';
verifyAbility(
    A::immobilized($paralyzed, 25) && !A::immobilized($paralyzed, 26),
    '25% de perder acción por parálisis',
);
verifyAbility(
    A::playerFirst(
        $paralyzed,
        ['nombre' => 'quick-attack'],
        $normal,
        ['nombre' => 'tackle'],
        [],
        0,
    ),
    'Prioridad gana a Velocidad, incluso paralizado',
);
verifyAbility(
    !A::playerFirst($paralyzed, ['nombre' => 'tackle'], $normal, ['nombre' => 'tackle'], [], 1),
    'Parálisis modifica el orden',
);
verifyAbility(
    A::playerFirst($normal, ['nombre' => 'tackle'], $normal, ['nombre' => 'tackle'], [], 1) &&
        !A::playerFirst($normal, ['nombre' => 'tackle'], $normal, ['nombre' => 'tackle'], [], 0),
    'Empates de Velocidad al azar',
);
$move = ['nombre' => 'ember', 'tipo' => 'fire', 'potencia' => 40, 'clase' => 'especial'];
foreach (
    ['blaze' => 'fire', 'overgrow' => 'grass', 'torrent' => 'water', 'swarm' => 'bug']
    as $id => $type
) {
    $p = fighter($id);
    $p['hp_maximo'] = 150;
    $m = $move;
    $m['tipo'] = $type;
    $baseline = B::damage($p, $normal, $m, 1);
    $p['hp_actual'] = 50;
    verifyAbility(B::damage($p, $normal, $m, 1) > $baseline, "$id funciona al tercio exacto");
    $p['hp_actual'] = 51;
    verifyAbility(
        B::damage($p, $normal, $m, 1) === $baseline,
        "$id no se activa por encima del tercio",
    );
    $p['hp_actual'] = 1;
    $m['tipo'] = 'ice';
    verifyAbility(
        B::damage($p, $normal, $m, 1) === B::damage($normal, $normal, $m, 1),
        "$id respeta el tipo del movimiento",
    );
}
$battle = battlefield(fighter('intimidate'), fighter('intimidate'));
A::enter($battle, 'jugador');
A::enter($battle, 'rival');
verifyAbility(
    $battle['equipo_jugador']['pokemon'][0]['cambios']['ataque'] === -1 &&
        $battle['equipo_rival']['pokemon'][0]['cambios']['ataque'] === -1,
    'Dos Intimidaciones se conservan al iniciar',
);
$physical = ['nombre' => 'tackle', 'tipo' => 'normal', 'potencia' => 40, 'clase' => 'fisico'];
$weakened = $battle['equipo_jugador']['pokemon'][0];
verifyAbility(
    B::damage($weakened, $normal, $physical, 1) < B::damage($normal, $normal, $physical, 1),
    'Intimidación reduce daño físico',
);
verifyAbility(
    B::damage($weakened, $normal, $move, 1) === B::damage($normal, $normal, $move, 1),
    'Intimidación no reduce daño especial',
);
for ($i = 0; $i < 10; $i++) {
    A::enter($battle, 'jugador');
}
verifyAbility(
    $battle['equipo_rival']['pokemon'][0]['cambios']['ataque'] === -6,
    'Límite de seis niveles',
);
foreach (
    [
        'clear-body',
        'white-smoke',
        'hyper-cutter',
        'inner-focus',
        'oblivious',
        'own-tempo',
        'scrappy',
    ]
    as $immune
) {
    $b = battlefield(fighter('intimidate'), fighter($immune));
    A::enter($b, 'jugador');
    verifyAbility(
        empty($b['equipo_rival']['pokemon'][0]['cambios']),
        "$immune impide Intimidación",
    );
}
$static = fighter('static', ['electric']);
verifyAbility(
    A::move($physical)['contacto'] && !A::move(['nombre' => 'earthquake'])['contacto'],
    'Datos oficiales de contacto; Terremoto es físico sin contacto',
);
verifyAbility(
    A::move(['nombre' => 'quick-attack'])['prioridad'] === 1,
    'Prioridad oficial de Ataque Rápido',
);
$b = battlefield($normal, $static);
$attacker = $normal;
verifyAbility(
    count(A::contact($b, 'jugador', $attacker, $static, $physical, 10, 30)) === 1 &&
        $attacker['estado_primario'] === 'paralisis',
    'Estática activa en el límite del 30%',
);
verifyAbility(A::speed($attacker, []) === 50, 'Parálisis divide Velocidad entre dos');
verifyAbility(
    A::contact($b, 'jugador', $attacker, $static, $physical, 10, 1) === [],
    'No duplica un estado primario',
);
foreach ([fighter('limber'), fighter('', ['electric'])] as $immune) {
    verifyAbility(
        A::contact($b, 'jugador', $immune, $static, $physical, 10, 1) === [],
        'Inmunidad a parálisis',
    );
}
$attacker = $normal;
verifyAbility(
    A::contact($b, 'jugador', $attacker, $static, $physical, 10, 31) === [],
    'Estática no supera 30%',
);
verifyAbility(
    A::contact($b, 'jugador', $attacker, $static, ['nombre' => 'earthquake'], 10, 1) === [],
    'Estática no se activa sin contacto',
);
verifyAbility(
    A::contact($b, 'jugador', $attacker, $static, $physical, 0, 1) === [],
    'Estática no se activa en una inmunidad',
);
$static['hp_actual'] = 0;
verifyAbility(
    count(A::contact($b, 'jugador', $attacker, $static, $physical, 10, 1)) === 1,
    'Estática puede activarse aunque el portador caiga',
);
$solar = fighter('solar-power');
verifyAbility(
    B::damage($solar, $normal, $move, 1) === B::damage($normal, $normal, $move, 1),
    'Poder Solar inactivo sin sol',
);
$sun = ['clima' => 'sol'];
verifyAbility(
    B::damage($solar, $normal, $move, 1, 100, $sun) >
        B::damage($normal, $normal, $move, 1, 100, $sun),
    'Poder Solar aumenta Ataque Especial',
);
verifyAbility(
    B::damage($solar, $normal, $physical, 1, 100, $sun) ===
        B::damage($normal, $normal, $physical, 1, 100, $sun),
    'Poder Solar no aumenta ataques físicos',
);
verifyAbility(
    B::damage($normal, $normal, $move, 1, 100, $sun) > B::damage($normal, $normal, $move, 1),
    'Sol potencia Fuego',
);
$water = $move;
$water['tipo'] = 'water';
verifyAbility(
    B::damage($normal, $normal, $water, 1, 100, $sun) < B::damage($normal, $normal, $water, 1),
    'Sol reduce Agua',
);
$b = battlefield(fighter('drought'), $solar);
A::enter($b, 'jugador');
verifyAbility(
    $b['clima'] === 'sol' && $b['clima_turnos'] === 5,
    'Sequía establece cinco turnos de sol',
);
A::endTurn($b);
A::enter($b, 'jugador');
verifyAbility(
    $b['clima_turnos'] === 4 && $b['equipo_rival']['pokemon'][0]['hp_actual'] === 140,
    'Poder Solar resta 1/8; repetir Sequía no renueva el clima',
);
for ($i = 0; $i < 4; $i++) {
    A::endTurn($b);
}
verifyAbility(
    $b['clima'] === null && $b['equipo_rival']['pokemon'][0]['hp_actual'] === 60,
    'Quinto turno aplica daño antes de acabar el sol',
);
$b['equipo_jugador']['pokemon'][0] = fighter('drizzle');
A::enter($b, 'jugador');
verifyAbility($b['clima'] === 'lluvia', 'Llovizna establece lluvia');
verifyAbility(
    A::speed(fighter('chlorophyll'), $sun) === 200 && A::speed(fighter('swift-swim'), $b) === 200,
    'Clorofila y Nado Rápido',
);
foreach (M::all() as $p) {
    verifyAbility(
        $p['precio_desglose']['habilidades'] ===
            (!empty($p['habilidad_activa']['implementada'])
                ? ($p['categoria'] === 'iniciales'
                    ? 25
                    : 40)
                : 0),
        'Precio por una habilidad funcional, independiente de cantidad',
    );
}
verifyAbility(M::get(6)['habilidad_activa']['id'] === 'blaze', 'Charizard empieza con Mar Llamas');
verifyAbility(
    AbilityService::choice(M::get(6), 'solar-power')['oculta'],
    'Poder Solar es una alternativa oculta válida',
);
try {
    AbilityService::choice(M::get(25), 'blaze');
    throw new RuntimeException('No rechaza habilidad ajena');
} catch (DomainException $expected) {
}
echo "OK: $checks comprobaciones de habilidades, contacto oficial, inmunidades, clima y precios.\n";
