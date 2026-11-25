CREATE TABLE IF NOT EXISTS habilidades_pokemon (
 usuario_id INT NOT NULL, pokemon_id INT NOT NULL, habilidad VARCHAR(80) NOT NULL,
 PRIMARY KEY (usuario_id,pokemon_id),
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
