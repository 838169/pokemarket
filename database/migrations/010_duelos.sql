CREATE TABLE IF NOT EXISTS duelo_busqueda (
 usuario_id INT PRIMARY KEY, equipo_id INT NOT NULL, vence INT UNSIGNED NOT NULL,
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
 FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS duelos_equipos (
 id CHAR(32) PRIMARY KEY, retador INT NOT NULL, invitado INT NOT NULL,
 estado VARCHAR(12) NOT NULL, vence INT UNSIGNED NOT NULL, datos LONGTEXT NOT NULL,
 INDEX (retador, estado), INDEX (invitado, estado),
 FOREIGN KEY (retador) REFERENCES usuarios(id) ON DELETE CASCADE,
 FOREIGN KEY (invitado) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
