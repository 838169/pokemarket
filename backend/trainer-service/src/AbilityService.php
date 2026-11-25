<?php
declare(strict_types=1);
namespace TrainerService;
use PDO;

final class AbilityService
{
    public function __construct(private PDO $db) {}
    public function decorate(int $uid, array $pokemon): array
    {
        $q = $this->db->prepare(
            'SELECT habilidad FROM habilidades_pokemon WHERE usuario_id=? AND pokemon_id=?',
        );
        $q->execute([$uid, $pokemon['id']]);
        $id = $q->fetchColumn();
        foreach ($pokemon['habilidades_compatibles'] as $ability) {
            if ($ability['id'] === $id) {
                $pokemon['habilidad_activa'] = $ability;
            }
        }
        return $pokemon;
    }
    public static function choice(array $pokemon, string $id): array
    {
        foreach ($pokemon['habilidades_compatibles'] as $ability) {
            if ($ability['id'] === $id) {
                return $ability;
            }
        }
        throw new \DomainException('Elige una habilidad compatible de esta especie.');
    }
    public function equip(int $uid, int $pid, string $id): void
    {
        self::choice(MarketCatalog::get($pid), $id);
        // One atomic ownership-checked write. Existing battle snapshots stay unchanged.
        $q = $this->db->prepare(
            'INSERT INTO habilidades_pokemon(usuario_id,pokemon_id,habilidad) SELECT usuario_id,pokemon_id,? FROM coleccion_pokemon WHERE usuario_id=? AND pokemon_id=? ON DUPLICATE KEY UPDATE habilidad=VALUES(habilidad)',
        );
        $q->execute([$id, $uid, $pid]);
        $q = $this->db->prepare(
            'SELECT pokemon_id FROM coleccion_pokemon WHERE usuario_id=? AND pokemon_id=?',
        );
        $q->execute([$uid, $pid]);
        if (!$q->fetchColumn()) {
            throw new \DomainException('Este Pokémon no pertenece a tu Hogar.');
        }
    }
}
