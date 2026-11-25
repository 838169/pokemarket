<?php
declare(strict_types=1);
namespace TrainerService;

use PDO;

final class SalaEvents
{
    public function __construct(private PDO $db) {}

    public function invite(string $room, array $friends): void
    {
        $insert = $this->db->prepare(
            "INSERT INTO eventos_sala(sala_id,destinatario_id,tipo) VALUES(?,?,'invitacion_creada')",
        );
        foreach ($friends as $friend) {
            $insert->execute([$room, $friend]);
        }
    }

    public function deliverPending(): int
    {
        $this->db->beginTransaction();
        try {
            $events = $this->db->query(
                "SELECT id FROM eventos_sala
                 WHERE procesado_en IS NULL AND tipo='invitacion_creada'
                 ORDER BY id LIMIT 100 FOR UPDATE",
            )->fetchAll(PDO::FETCH_COLUMN);
            if ($events) {
                $placeholders = implode(',', array_fill(0, count($events), '?'));
                $insert = $this->db->prepare(
                    "INSERT INTO notificaciones_sala(evento_id)
                     SELECT e.id FROM eventos_sala e
                     JOIN salas_duo s ON s.id=e.sala_id
                     JOIN integrantes_sala m ON m.sala_id=e.sala_id AND m.usuario_id=e.destinatario_id
                     WHERE e.id IN ($placeholders) AND m.aceptado=0
                     AND s.estado IN ('invitada','preparando') AND s.vence>?
                     ON DUPLICATE KEY UPDATE evento_id=VALUES(evento_id)",
                );
                $insert->execute([...$events, time()]);
                $this->db->prepare(
                    "UPDATE eventos_sala SET procesado_en=CURRENT_TIMESTAMP WHERE id IN ($placeholders)",
                )->execute($events);
            }
            $this->db->commit();
            return count($events);
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
