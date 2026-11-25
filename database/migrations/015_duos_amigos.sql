USE pokemarket;
CREATE TABLE IF NOT EXISTS salas_duo (
    id CHAR(24) PRIMARY KEY,
    anfitrion INT NOT NULL,
    companero INT NOT NULL,
    estado VARCHAR(20) NOT NULL,
    batalla_id CHAR(24) NULL,
    datos LONGTEXT NOT NULL,
    vence INT UNSIGNED NOT NULL,
    creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (estado, vence),
    INDEX (anfitrion, vence),
    INDEX (companero, vence),
    FOREIGN KEY (anfitrion) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (companero) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
