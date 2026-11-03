-- migrations/003_moderacion_log.sql
--
-- Tabla donde el worker de RabbitMQ registra las publicaciones que el
-- filtro de contenido marcó como sensibles. El worker también la crea solo
-- (CREATE TABLE IF NOT EXISTS) al arrancar; este archivo es para quien
-- prefiera aplicarla a mano.
--
--   psql "$DATABASE_URL" -f migrations/003_moderacion_log.sql

CREATE TABLE IF NOT EXISTS moderacion_log (
    id SERIAL PRIMARY KEY,
    publicacion_id INTEGER NOT NULL,
    user_id INTEGER,
    evento TEXT NOT NULL,
    motivo TEXT NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Los registros pendientes del QR ahora viven en Redis. Esta tabla queda
-- solo como respaldo cuando Redis no está configurado; si ya no la vas a
-- usar nunca podés borrarla:
--   DROP TABLE IF EXISTS registros_pendientes;
