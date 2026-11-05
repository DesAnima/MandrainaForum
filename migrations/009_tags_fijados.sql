-- migrations/009_tags_fijados.sql
--
-- Tags que el administrador deja fijados arriba en "Tags generales". La app
-- también crea la tabla sola (CREATE TABLE IF NOT EXISTS); este archivo es para
-- quien prefiera aplicarla a mano.
--
--   psql "$DATABASE_URL" -f migrations/009_tags_fijados.sql
--
-- No lleva clave foránea a publicacion_tags a propósito: un tag fijado sigue
-- apareciendo (con 0 publicaciones) aunque se borren todas las que lo usaban,
-- hasta que el administrador lo quite.

CREATE TABLE IF NOT EXISTS tags_fijados (
    tag TEXT PRIMARY KEY,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
);
