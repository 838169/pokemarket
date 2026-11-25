-- Aggregate locked alongside the wallet. Additive: never resets existing progress.
CREATE TABLE IF NOT EXISTS exploracion_usuario (
 usuario_id INT NOT NULL PRIMARY KEY,
 datos LONGTEXT NOT NULL,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
