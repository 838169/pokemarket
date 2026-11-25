<?php
declare(strict_types=1);
namespace TrainerService;

final class BattleInitiative
{
    public static function create(array $players, array $automatic = []): array
    {
        $ready = array_fill_keys($players, false);
        foreach ($automatic as $player) $ready[$player] = true;
        return ['listos' => $ready, 'orden' => [], 'automaticos' => $automatic, 'caras' => array_values($players)];
    }
    public static function confirm(array &$battle, string $player, ?\Closure $draw = null): void
    {
        if (!isset($battle['iniciativa'])) return;
        $initiative = &$battle['iniciativa'];
        if (!array_key_exists($player, $initiative['listos'])) throw new \DomainException('No participas en esta partida.');
        if ($initiative['orden']) return;
        foreach ($initiative['automaticos'] as $automatic) $initiative['listos'][$automatic] = true;
        $initiative['listos'][$player] = true;
        if (in_array(false, $initiative['listos'], true)) return;
        $order = array_keys($initiative['listos']);
        for ($i = count($order) - 1; $i > 0; $i--) {
            $j = $draw ? $draw($i) : random_int(0, $i);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }
        $initiative['orden'] = $order;
    }
    public static function requireReady(array $battle): void
    {
        if (isset($battle['iniciativa']) && !$battle['iniciativa']['orden']) {
            throw new \DomainException('Confirma la preparación y espera a todos los participantes antes de atacar.');
        }
    }
}
