USE pokemarket;
CREATE TABLE IF NOT EXISTS integrantes_sala (
    sala_id CHAR(24) NOT NULL,
    usuario_id INT NOT NULL,
    aceptado TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (sala_id, usuario_id),
    INDEX (usuario_id, aceptado),
    FOREIGN KEY (sala_id) REFERENCES salas_duo(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT IGNORE INTO integrantes_sala(sala_id,usuario_id,aceptado)
SELECT id,anfitrion,1 FROM salas_duo WHERE JSON_EXTRACT(datos,'$.configuracion') IS NULL;
INSERT IGNORE INTO integrantes_sala(sala_id,usuario_id,aceptado)
SELECT id,companero,CASE WHEN estado='invitada' THEN 0 ELSE 1 END FROM salas_duo WHERE JSON_EXTRACT(datos,'$.configuracion') IS NULL;
