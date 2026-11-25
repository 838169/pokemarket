-- Ejecutar una sola vez sobre la base existente. Conserva cuentas y progreso.
USE pokemarket;
ALTER TABLE carteras_usuario
    ADD COLUMN tarjetas_cambio_usuario INT UNSIGNED NOT NULL DEFAULT 0;
