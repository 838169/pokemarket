ALTER TABLE usuarios ADD COLUMN google_sub VARBINARY(255) NULL;
ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_google_sub (google_sub);
