CREATE TABLE IF NOT EXISTS progreso_pokemon (
 usuario_id INT NOT NULL, pokemon_id INT NOT NULL,
 experiencia INT UNSIGNED NOT NULL, movimientos TEXT NOT NULL,
 PRIMARY KEY (usuario_id,pokemon_id),
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS experiencia_combate (
 usuario_id INT NOT NULL, batalla_id VARCHAR(64) NOT NULL, rival_indice INT NOT NULL,
 pokemon_id INT NOT NULL, cantidad INT UNSIGNED NOT NULL,
 PRIMARY KEY (usuario_id,batalla_id,rival_indice,pokemon_id),
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
