ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS usuario VARCHAR(24) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
    ADD COLUMN IF NOT EXISTS usuario_cambios_mes CHAR(7) NULL,
    ADD COLUMN IF NOT EXISTS usuario_cambios TINYINT UNSIGNED NOT NULL DEFAULT 0;
UPDATE usuarios SET usuario = CONCAT('entrenador-', id) WHERE usuario IS NULL;
ALTER TABLE usuarios
    MODIFY usuario VARCHAR(24) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uq_usuarios_usuario (usuario);
