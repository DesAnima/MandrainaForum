-- migrations/001_anonimizar_usuarios.sql
--
-- Corré esto UNA VEZ si tu tabla `usuarios` ya existe con el esquema
-- viejo (email, bio, profile_pic). Los usuarios existentes conservan su
-- nickname y su contraseña hasheada, así que van a poder seguir
-- entrando con login por nickname sin tener que re-registrarse.
--
-- Cómo correrlo (ejemplo con psql usando tu DATABASE_URL de Neon):
--   psql "$DATABASE_URL" -f migrations/001_anonimizar_usuarios.sql

BEGIN;

-- Sacamos las restricciones/columnas que ya no se usan.
ALTER TABLE usuarios DROP CONSTRAINT IF EXISTS usuarios_email_key;
ALTER TABLE usuarios DROP COLUMN IF EXISTS email;
ALTER TABLE usuarios DROP COLUMN IF EXISTS bio;
ALTER TABLE usuarios DROP COLUMN IF EXISTS profile_pic;

-- Nos aseguramos que nickname sea la clave de login (única y obligatoria).
ALTER TABLE usuarios ALTER COLUMN nickname SET NOT NULL;
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'usuarios_nickname_key'
    ) THEN
        ALTER TABLE usuarios ADD CONSTRAINT usuarios_nickname_key UNIQUE (nickname);
    END IF;
END $$;

-- Columna de fecha de creación (nueva); si no existía, se agrega con
-- NOW() como valor por defecto para las filas existentes.
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW();

-- Tabla nueva para los registros pendientes (flujo de QR). Si ya existe
-- no pasa nada, Usuario.php también la crea con IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS registros_pendientes (
    id SERIAL PRIMARY KEY,
    token TEXT UNIQUE NOT NULL,
    nickname TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    password_plain TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT NOW(),
    expira_en TIMESTAMP NOT NULL
);

COMMIT;
