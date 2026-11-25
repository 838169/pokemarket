-- Additive migration: existing accounts and purchases are preserved.
CREATE TABLE IF NOT EXISTS escenarios_usuario (
 usuario_id INT NOT NULL, escenario VARCHAR(20) NOT NULL, fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(usuario_id,escenario), FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ofertas_apuesta (
 id CHAR(24) PRIMARY KEY, usuario_id INT NOT NULL, cantidad INT UNSIGNED NOT NULL,
 vence BIGINT NOT NULL, INDEX(usuario_id,vence),
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rondas_apuesta (
 id CHAR(16) PRIMARY KEY, usuario_id INT NOT NULL, cantidad INT UNSIGNED NOT NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'en_curso', version INT NOT NULL DEFAULT 1,
 partida LONGTEXT NOT NULL, fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(usuario_id,estado), FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
