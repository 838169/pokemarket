<?php
declare(strict_types=1);
namespace TrainerService;

use PDO;

final class FriendshipEvents
{
    public function __construct(private PDO $db) {}

    public function requested(array $pair, int $sender, int $recipient): void
    {
        $this->db->prepare(
            "INSERT INTO eventos_amistad(usuario_menor,usuario_mayor,solicitante_id,destinatario_id,tipo)
             VALUES(?,?,?,?,'amistad_solicitada')",
        )->execute([...$pair, $sender, $recipient]);
    }

    public function deliverPending(): int
    {
        return $this->db->exec(
            "UPDATE eventos_amistad SET entregado_en=CURRENT_TIMESTAMP
             WHERE entregado_en IS NULL AND tipo='amistad_solicitada' ORDER BY id LIMIT 100",
        );
    }
}
