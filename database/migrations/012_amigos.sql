CREATE TABLE IF NOT EXISTS amigos (
 usuario_menor INT NOT NULL, usuario_mayor INT NOT NULL, solicitante INT NOT NULL,
 aceptada TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY (usuario_menor, usuario_mayor), INDEX (usuario_mayor),
 FOREIGN KEY (usuario_menor) REFERENCES usuarios(id) ON DELETE CASCADE,
 FOREIGN KEY (usuario_mayor) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
