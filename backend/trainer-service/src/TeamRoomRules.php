<?php
declare(strict_types=1);
namespace TrainerService;
final class TeamRoomRules
{
    public static function totals(int $people): array
    {
        if ($people < 2 || $people > 4) throw new \DomainException('Elige un grupo de 2, 3 o 4 personas.');
        return range($people, intdiv(6, $people) * $people, $people);
    }
    public static function configuration(mixed $people, mixed $total): array
    {
        if (!is_int($people) || !is_int($total) || !in_array($total, self::totals($people), true)) {
            throw new \DomainException('El reparto debe ser igual para todos y no puede superar 6 Pokémon por equipo.');
        }
        return ['personas'=>$people,'total'=>$total,'por_persona'=>intdiv($total,$people)];
    }
}
