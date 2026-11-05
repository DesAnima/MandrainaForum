-- migrations/007_notificaciones.sql
--
-- Avisos para el autor de una publicación cuando alguien le responde. La API
-- también crea la tabla sola (CREATE TABLE IF NOT EXISTS) la primera vez que
-- se usa; este archivo es para quien prefiera aplicarla a mano.
--
--   psql "$DATABASE_URL" -f migrations/007_notificaciones.sql

CREATE TABLE IF NOT EXISTS notificaciones (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    actor_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
    respuesta_id INTEGER NOT NULL REFERENCES respuestas(id) ON DELETE CASCADE,
    leida BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notificaciones_usuario
    ON notificaciones (user_id, leida, fecha_creacion DESC);
