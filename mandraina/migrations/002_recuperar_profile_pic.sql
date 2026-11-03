-- migrations/002_recuperar_profile_pic.sql
--
-- Si ya corriste 001_anonimizar_usuarios.sql (que sacaba profile_pic),
-- corré esto para volver a agregarla. Ahora se usa solo para avatares de
-- personaje elegidos por el usuario después de loguearse, no para fotos
-- personales en el registro (que sigue siendo anónimo: nickname + password).
--
-- Cómo correrlo: pegalo en el SQL Editor de Neon, o:
--   psql "$DATABASE_URL" -f migrations/002_recuperar_profile_pic.sql

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS profile_pic BYTEA;
