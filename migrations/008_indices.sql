-- migrations/008_indices.sql
--
-- Índices para acelerar el feed paginado y las consultas de tags, respuestas
-- y reacciones. La app también los crea sola (CREATE INDEX IF NOT EXISTS) la
-- primera vez que se usa; este archivo es para quien prefiera aplicarlos a mano.
--
--   psql "$DATABASE_URL" -f migrations/008_indices.sql
--
-- El orden del feed es por id descendente y el cursor es el id, así que lo
-- cubre la clave primaria de publicaciones: no hace falta otro índice para eso.

-- Publicaciones de un usuario (perfil, tags de participación).
CREATE INDEX IF NOT EXISTS idx_publicaciones_user
    ON publicaciones (user_id, id DESC);

-- Contar y listar las respuestas de una publicación.
CREATE INDEX IF NOT EXISTS idx_respuestas_publicacion
    ON respuestas (publicacion_id, fecha_creacion);

-- Respuestas de un usuario (tags en los que participa) y borrado en cascada.
CREATE INDEX IF NOT EXISTS idx_respuestas_user
    ON respuestas (user_id);

-- Contar likes y dislikes de cada publicación sin leer la tabla entera.
CREATE INDEX IF NOT EXISTS idx_reacciones_publicacion_tipo
    ON reacciones (publicacion_id, tipo);

-- Reacciones de un usuario (mis reacciones) y borrado en cascada.
CREATE INDEX IF NOT EXISTS idx_reacciones_user
    ON reacciones (user_id);

-- Filtrar por tag y contar publicaciones por tag. Reemplaza al índice simple
-- de la migración 005, que queda redundante.
CREATE INDEX IF NOT EXISTS idx_publicacion_tags_tag_pub
    ON publicacion_tags (tag, publicacion_id);
DROP INDEX IF EXISTS idx_publicacion_tags_tag;
