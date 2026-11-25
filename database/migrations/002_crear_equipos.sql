CREATE TABLE equipos (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_equipos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
);

CREATE TABLE miembros_equipo (
    equipo_id INT NOT NULL,
    posicion TINYINT UNSIGNED NOT NULL,
    pokemon_id INT NOT NULL,
    fecha_agregado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (equipo_id, posicion),
    UNIQUE KEY uq_miembro_equipo_pokemon (equipo_id, pokemon_id),
    CONSTRAINT chk_miembro_equipo_posicion
        CHECK (posicion BETWEEN 1 AND 6),
    CONSTRAINT fk_miembros_equipo_equipo
        FOREIGN KEY (equipo_id)
        REFERENCES equipos(id)
        ON DELETE CASCADE
);
