CREATE TABLE IF NOT EXISTS eventos_amistad (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_menor INT NOT NULL,
    usuario_mayor INT NOT NULL,
    solicitante_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entregado_en TIMESTAMP NULL,
    UNIQUE KEY (usuario_menor, usuario_mayor),
    INDEX (entregado_en, id),
    INDEX (destinatario_id, entregado_en),
    FOREIGN KEY (usuario_menor, usuario_mayor) REFERENCES amistades(usuario_menor, usuario_mayor) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO eventos_amistad(usuario_menor,usuario_mayor,solicitante_id,destinatario_id,tipo)
SELECT usuario_menor,usuario_mayor,solicitante,IF(solicitante=usuario_menor,usuario_mayor,usuario_menor),'amistad_solicitada'
FROM amistades WHERE aceptada=0;
