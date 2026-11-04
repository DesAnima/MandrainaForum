-- migrations/005_tags.sql
--
-- Tags de las publicaciones. La API también crea la tabla sola
-- (CREATE TABLE IF NOT EXISTS) la primera vez que se usa; este archivo es
-- para quien prefiera aplicarla a mano.
--
--   psql "$DATABASE_URL" -f migrations/005_tags.sql

CREATE TABLE IF NOT EXISTS publicacion_tags (
    publicacion_id INTEGER NOT NULL REFERENCES publicaciones(id) ON DELETE CASCADE,
    tag TEXT NOT NULL,
    PRIMARY KEY (publicacion_id, tag)
);

-- Opcional: acelera buscar publicaciones por tag.
CREATE INDEX IF NOT EXISTS idx_publicacion_tags_tag ON publicacion_tags (tag);
