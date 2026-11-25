-- Invitaciones: evento persistente y notificación entregada.
CREATE TABLE IF NOT EXISTS eventos_sala (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sala_id CHAR(24) NOT NULL,
    destinatario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    procesado_en TIMESTAMP NULL,
    UNIQUE KEY (sala_id, destinatario_id, tipo),
    INDEX (procesado_en, id),
    FOREIGN KEY (sala_id, destinatario_id) REFERENCES integrantes_sala(sala_id, usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS notificaciones_sala (
    evento_id BIGINT UNSIGNED PRIMARY KEY,
    entregada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos_sala(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO eventos_sala(sala_id,destinatario_id,tipo)
SELECT s.id,m.usuario_id,'invitacion_creada'
FROM salas_duo s
JOIN integrantes_sala m ON m.sala_id=s.id
WHERE m.aceptado=0 AND s.estado IN ('invitada','preparando') AND s.vence>UNIX_TIMESTAMP();
