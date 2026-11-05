-- migrations/006_reacciones.sql
--
-- Likes (mandarina) y dislikes (tomate) de las publicaciones. La API también
-- crea la tabla sola (CREATE TABLE IF NOT EXISTS); este archivo es para quien
-- prefiera aplicarla a mano.
--
--   psql "$DATABASE_URL" -f migrations/006_reacciones.sql
--
-- La clave primaria deja una sola fila por usuario y publicación: no puede
-- haber like y dislike a la vez, pero la fila se puede cambiar de uno a otro.

CREATE TABLE IF NOT EXISTS reacciones (
    publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    tipo TEXT NOT NULL CHECK (tipo IN ('like', 'dislike')),
    fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW(),
    PRIMARY KEY (publicacion_id, user_id)
);
