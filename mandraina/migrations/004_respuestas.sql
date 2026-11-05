-- migrations/004_respuestas.sql
--
-- Respuestas a publicaciones. La API también crea la tabla sola
-- (CREATE TABLE IF NOT EXISTS) la primera vez que se usa; este archivo es
-- para quien prefiera aplicarla a mano.
--
--   psql "$DATABASE_URL" -f migrations/004_respuestas.sql

CREATE TABLE IF NOT EXISTS respuestas (
    id SERIAL PRIMARY KEY,
    publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    contenido TEXT NOT NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Opcional: acelera el listado de respuestas de una publicación.
CREATE INDEX IF NOT EXISTS idx_respuestas_publicacion
    ON respuestas (publicacion_id, fecha_creacion);
