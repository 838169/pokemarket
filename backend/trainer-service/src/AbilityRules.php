<?php
declare(strict_types=1);
namespace TrainerService;

/** Supported singles mechanics. An unimplemented ability never receives a surcharge. */
final class AbilityRules
{
    public const EFFECTS = [
        'blaze' => 'Con un tercio de PS o menos, potencia Fuego un 50%.',
        'overgrow' => 'Con un tercio de PS o menos, potencia Planta un 50%.',
        'torrent' => 'Con un tercio de PS o menos, potencia Agua un 50%.',
        'swarm' => 'Con un tercio de PS o menos, potencia Bicho un 50%.',
        'static' =>
            'Al recibir contacto, hay un 30% de probabilidad de paralizar al atacante. Los tipos Eléctrico son inmunes.',
        'intimidate' =>
            'Al entrar al combate, baja un nivel el Ataque del rival. Los cambios de estadísticas se reinician al retirarse.',
        'solar-power' =>
            'Con sol, aumenta el Ataque Especial un 50%, pero pierde 1/8 de sus PS máximos al final del turno. Combínala con Sequía.',
        'drought' => 'Al entrar, crea sol durante cinco turnos: Fuego ×1,5 y Agua ×0,5.',
        'drizzle' => 'Al entrar, crea lluvia durante cinco turnos: Agua ×1,5 y Fuego ×0,5.',
        'chlorophyll' => 'Duplica su Velocidad mientras hay sol.',
        'swift-swim' => 'Duplica su Velocidad mientras llueve.',
        'clear-body' => 'Impide que Intimidación reduzca sus estadísticas.',
        'white-smoke' => 'Impide que Intimidación reduzca sus estadísticas.',
        'hyper-cutter' => 'Impide que Intimidación reduzca su Ataque.',
        'inner-focus' =>
            'Impide la reducción de Ataque por Intimidación. El combate actual no simula retrocesos.',
        'oblivious' =>
            'Impide la reducción de Ataque por Intimidación. El combate actual no simula atracción ni provocación.',
        'own-tempo' =>
            'Impide la reducción de Ataque por Intimidación. El combate actual no simula confusión.',
        'scrappy' => 'Permite golpear a Fantasma con ataques Normal y Lucha e impide Intimidación.',
        'limber' => 'Impide quedar paralizado.',
    ];

    private static function data(): array
    {
        static $data = null;
        return $data ??= json_decode(
            file_get_contents(dirname(__DIR__) . '/config/ability_catalog.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
    public static function decorate(array $pokemon): array
    {
        foreach ($pokemon['habilidades'] as &$ability) {
            $ability['id'] = self::data()['habilidades'][$ability['nombre']] ?? '';
            $ability['implementada'] = isset(self::EFFECTS[$ability['id']]);
            $ability['descripcion'] =
                self::EFFECTS[$ability['id']] ??
                'Efecto aún no simulado. Sin recargo por esta habilidad.';
        }
        unset($ability);
        $supported = array_values(
            array_filter($pokemon['habilidades'], fn($a) => $a['implementada']),
        );
        $pokemon['habilidades_compatibles'] = $supported;
        $pokemon['habilidad_activa'] = $supported[0] ?? ($pokemon['habilidades'][0] ?? null);
        return $pokemon;
    }
    public static function active(array $pokemon): string
    {
        $id = $pokemon['habilidad_activa']['id'] ?? '';
        return isset(self::EFFECTS[$id]) ? $id : '';
    }
    public static function move(array $move): array
    {
        return array_merge(
            $move,
            self::data()['movimientos'][$move['nombre']] ?? ['contacto' => false, 'prioridad' => 0],
        );
    }
    public static function stage(int $stage): float
    {
        $stage = max(-6, min(6, $stage));
        return $stage >= 0 ? (2 + $stage) / 2 : 2 / (2 - $stage);
    }
    public static function speed(array $pokemon, array $battle): int
    {
        $speed =
            $pokemon['estadisticas']['velocidad'] *
            self::stage($pokemon['cambios']['velocidad'] ?? 0);
        if (($pokemon['estado_primario'] ?? '') === 'paralisis') {
            $speed *= 0.5;
        }
        $ability = self::active($pokemon);
        if (
            ($ability === 'chlorophyll' && ($battle['clima'] ?? '') === 'sol') ||
            ($ability === 'swift-swim' && ($battle['clima'] ?? '') === 'lluvia')
        ) {
            $speed *= 2;
        }
        return max(1, (int) floor($speed));
    }
    public static function immobilized(array $pokemon, int $roll): bool
    {
        return ($pokemon['estado_primario'] ?? '') === 'paralisis' && $roll <= 25;
    }
    public static function playerFirst(
        array $player,
        array $playerMove,
        array $rival,
        array $rivalMove,
        array $battle,
        int $tieRoll,
    ): bool {
        $priority =
            (self::move($playerMove)['prioridad'] ?? 0) <=>
            (self::move($rivalMove)['prioridad'] ?? 0);
        if ($priority !== 0) {
            return $priority > 0;
        }
        $speed = self::speed($player, $battle) <=> self::speed($rival, $battle);
        return $speed !== 0 ? $speed > 0 : $tieRoll === 1;
    }
    public static function enter(array &$battle, string $side): array
    {
        $p = &$battle['equipo_' . $side]['pokemon'][$battle['activo_' . $side]];
        $p['cambios'] ??= [];
        $other = $side === 'jugador' ? 'rival' : 'jugador';
        $target = &$battle['equipo_' . $other]['pokemon'][$battle['activo_' . $other]];
        $ability = self::active($p);
        $message = null;
        if ($ability === 'intimidate' && $target['hp_actual'] > 0) {
            if (
                in_array(
                    self::active($target),
                    [
                        'clear-body',
                        'white-smoke',
                        'hyper-cutter',
                        'inner-focus',
                        'oblivious',
                        'own-tempo',
                        'scrappy',
                    ],
                    true,
                )
            ) {
                $message =
                    $target['nombre'] .
                    ' resiste Intimidación gracias a ' .
                    $target['habilidad_activa']['nombre'] .
                    '.';
            } else {
                $before = $target['cambios']['ataque'] ?? 0;
                $target['cambios']['ataque'] = max(-6, $before - 1);
                $message =
                    $p['nombre'] .
                    ': Intimidación. ' .
                    $target['nombre'] .
                    ($before > -6
                        ? ' pierde un nivel de Ataque.'
                        : ' ya tiene el Ataque al mínimo.');
            }
        } elseif (in_array($ability, ['drought', 'drizzle'], true)) {
            $weather = $ability === 'drought' ? 'sol' : 'lluvia';
            // Re-entering with the same weather does not renew its duration.
            if (($battle['clima'] ?? '') !== $weather) {
                $battle['clima'] = $weather;
                $battle['clima_turnos'] = 5;
                $message =
                    $p['nombre'] .
                    ': ' .
                    $p['habilidad_activa']['nombre'] .
                    '. ' .
                    ($weather === 'sol' ? '¡Sale el sol!' : '¡Empieza a llover!') .
                    ' Cinco turnos.';
            }
        }
        return $message ? [self::event($battle, $side, $message)] : [];
    }
    public static function event(
        array &$battle,
        string $side,
        string $message,
        ?array $pokemon = null,
    ): array {
        $battle['historial'][] = $message;
        $battle['historial'] = array_slice($battle['historial'], -50);
        $event = ['tipo' => 'habilidad', 'lado' => $side, 'mensaje' => $message];
        if ($pokemon) {
            $event += ['pokemon_id' => $pokemon['id'], 'hp_nuevo' => $pokemon['hp_actual']];
        }
        return $event;
    }
    public static function contact(
        array &$battle,
        string $side,
        array &$attacker,
        array $defender,
        array $move,
        int $damage,
        int $roll,
    ): array {
        if (
            $damage > 0 &&
            $attacker['hp_actual'] > 0 &&
            !empty(self::move($move)['contacto']) &&
            self::active($defender) === 'static' &&
            $roll <= 30 &&
            empty($attacker['estado_primario']) &&
            !in_array('electric', $attacker['tipos'], true) &&
            self::active($attacker) !== 'limber'
        ) {
            $attacker['estado_primario'] = 'paralisis';
            return [
                self::event(
                    $battle,
                    $side,
                    $defender['nombre'] .
                        ': Electricidad Estática. ¡' .
                        $attacker['nombre'] .
                        ' queda paralizado!',
                ),
            ];
        }
        return [];
    }
    /** Resolve all residual HP before replacing fainted combatants. */
    public static function endTurn(array &$battle): array
    {
        $events = [];
        if (($battle['clima'] ?? '') === 'sol') {
            foreach (['jugador', 'rival'] as $side) {
                $p = &$battle['equipo_' . $side]['pokemon'][$battle['activo_' . $side]];
                if ($p['hp_actual'] > 0 && self::active($p) === 'solar-power') {
                    $p['hp_actual'] = max(0, $p['hp_actual'] - max(1, intdiv($p['hp_maximo'], 8)));
                    $events[] = self::event(
                        $battle,
                        $side,
                        $p['nombre'] . ' pierde PS por Poder Solar.',
                        $p,
                    );
                }
                unset($p);
            }
        }
        if (($battle['clima_turnos'] ?? 0) > 0 && --$battle['clima_turnos'] === 0) {
            $battle['clima'] = null;
            $events[] = self::event($battle, 'jugador', 'El clima vuelve a la normalidad.');
        }
        return $events;
    }
}
