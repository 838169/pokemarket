-- PokéMarket. Selecciona una base de datos vacía antes de importar.
-- Para actualizar una instalación anterior, ejecuta php tools/setup.php.
SET NAMES utf8mb4;

-- Cuentas, colección y equipos
CREATE TABLE IF NOT EXISTS usuarios (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    usuario_cambios_mes CHAR(7) NULL,
    usuario_cambios TINYINT UNSIGNED NOT NULL DEFAULT 0,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    google_sub VARBINARY(255) NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    correo_verificado TIMESTAMP NULL,
    totp_secreto VARCHAR(128) NULL,
    totp_paso BIGINT NOT NULL DEFAULT 0,
    totp_ventana INT UNSIGNED NOT NULL DEFAULT 0,
    totp_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sesion_version INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_usuarios_google_sub (google_sub),
    UNIQUE KEY uq_usuarios_usuario (usuario)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS coleccion_pokemon (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    pokemon_nombre VARCHAR(100) NULL,
    fecha_agregado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_pokemon (usuario_id, pokemon_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS equipos (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS miembros_equipo (
    equipo_id INT NOT NULL,
    posicion TINYINT UNSIGNED NOT NULL,
    pokemon_id INT NOT NULL,
    fecha_agregado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (equipo_id, posicion),
    UNIQUE KEY uq_miembro_equipo_pokemon (equipo_id, pokemon_id),
    CHECK (posicion BETWEEN 1 AND 6),
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS codigos_cuenta (
    usuario_id INT NOT NULL,
    finalidad VARCHAR(12) NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    emitido INT UNSIGNED NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (usuario_id, finalidad),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS codigos_registro (
    email VARCHAR(150) NOT NULL PRIMARY KEY,
    desafio CHAR(64) NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    emitido INT UNSIGNED NOT NULL,
    intentos TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Economía y cuidado de Pokémon
CREATE TABLE IF NOT EXISTS migraciones_bd (
    version VARCHAR(80) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS carteras_usuario (
    usuario_id INT PRIMARY KEY,
    monedas INT UNSIGNED NOT NULL DEFAULT 0,
    tarjetas_cambio_usuario INT UNSIGNED NOT NULL DEFAULT 0,
    revision INT NOT NULL DEFAULT 1,
    bienvenida TINYINT NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS movimientos_monedas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    clave VARCHAR(100) NOT NULL,
    concepto VARCHAR(160) NOT NULL,
    cantidad INT NOT NULL,
    saldo INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_movimiento (usuario_id, clave),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inventario_comida (
    usuario_id INT NOT NULL,
    comida VARCHAR(30) NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (usuario_id, comida),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cuidado_pokemon (
    usuario_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    energia INT NOT NULL DEFAULT 100,
    vinculo INT NOT NULL DEFAULT 0,
    habitat VARCHAR(30) NOT NULL DEFAULT 'pradera',
    ultima_comida TIMESTAMP NULL,
    PRIMARY KEY (usuario_id, pokemon_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Aventuras y exploración
CREATE TABLE IF NOT EXISTS escenarios_usuario (
    usuario_id INT NOT NULL,
    escenario VARCHAR(20) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(usuario_id, escenario),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS ofertas_apuesta (
    id CHAR(24) PRIMARY KEY,
    usuario_id INT NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    vence BIGINT NOT NULL,
    INDEX(usuario_id, vence),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS rondas_apuesta (
    id CHAR(16) PRIMARY KEY,
    usuario_id INT NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'en_curso',
    version INT NOT NULL DEFAULT 1,
    partida LONGTEXT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(usuario_id, estado),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS progreso_exploracion (
    usuario_id INT NOT NULL PRIMARY KEY,
    datos LONGTEXT NOT NULL,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Experiencia y habilidades
CREATE TABLE IF NOT EXISTS progreso_pokemon (
    usuario_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    experiencia INT UNSIGNED NOT NULL,
    movimientos TEXT NOT NULL,
    PRIMARY KEY (usuario_id, pokemon_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS experiencia_combate (
    usuario_id INT NOT NULL,
    batalla_id VARCHAR(64) NOT NULL,
    rival_indice INT NOT NULL,
    pokemon_id INT NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, batalla_id, rival_indice, pokemon_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS habilidades_pokemon (
    usuario_id INT NOT NULL,
    pokemon_id INT NOT NULL,
    habilidad VARCHAR(80) NOT NULL,
    PRIMARY KEY (usuario_id, pokemon_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Duelos y amistades
CREATE TABLE IF NOT EXISTS busquedas_duelo (
    usuario_id INT PRIMARY KEY,
    equipo_id INT NULL,
    vence INT UNSIGNED NOT NULL,
    alineacion TEXT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS duelos_jugadores (
    id CHAR(32) PRIMARY KEY,
    retador INT NOT NULL,
    invitado INT NOT NULL,
    estado VARCHAR(12) NOT NULL,
    vence INT UNSIGNED NOT NULL,
    datos LONGTEXT NOT NULL,
    INDEX (retador, estado),
    INDEX (invitado, estado),
    FOREIGN KEY (retador) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (invitado) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS amistades (
    usuario_menor INT NOT NULL,
    usuario_mayor INT NOT NULL,
    solicitante INT NOT NULL,
    aceptada TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (usuario_menor, usuario_mayor),
    INDEX (usuario_mayor),
    FOREIGN KEY (usuario_menor) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_mayor) REFERENCES usuarios(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dúos de amigos: dos entrenadores por lado
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

-- Miembros de salas de 2 a 4 entrenadores
CREATE TABLE IF NOT EXISTS integrantes_sala (
    sala_id CHAR(24) NOT NULL,
    usuario_id INT NOT NULL,
    aceptado TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (sala_id, usuario_id),
    INDEX (usuario_id, aceptado),
    FOREIGN KEY (sala_id) REFERENCES salas_duo(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Invitaciones: evento persistente y notificación entregada.
CREATE TABLE IF NOT EXISTS eventos_sala (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sala_id CHAR(24) NOT NULL,
    destinatario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    procesado_en TIMESTAMP NULL,
    UNIQUE KEY (sala_id, destinatario_id, tipo),
    INDEX (procesado_en, id),
    FOREIGN KEY (sala_id, destinatario_id) REFERENCES integrantes_sala(sala_id, usuario_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS notificaciones_sala (
    evento_id BIGINT UNSIGNED PRIMARY KEY,
    entregada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos_sala(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
