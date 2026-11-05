# Mandraina Forum

API RESTful + foro simple desarrollado como Actividad de Evaluación N°1 (AE1) —
Paradigmas y Lenguajes de Programación III, UCP (FAITA), 2026.

**Demo en producción:** https://mandrainaforum.onrender.com/index.html

## Dominio del problema

Foro comunitario donde usuarios registrados pueden publicar, editar y eliminar
sus propias publicaciones (posts), y ver las publicaciones de los demás.

## Entidades

- **Usuario** (`usuarios`): nickname, password (hasheada). No se guarda email,
  bio ni foto de perfil: el registro es anónimo.
- **Publicación** (`publicaciones`): título, contenido, autor (relación con Usuario),
  fecha de creación/actualización. Entidad principal del AE1.

## Administrador

Hay un único usuario administrador, que puede eliminar **cualquier**
publicación o respuesta (no solo las propias). Se define por variables de
entorno:

- `ADMIN_NICKNAME`: nickname del admin; por defecto **`DesAnima1`**. Queda
  reservado (sin distinguir mayúsculas): nadie puede registrarlo por el flujo
  del QR.
- `ADMIN_PASSWORD`: contraseña inicial (mínimo 10 caracteres).

Al arrancar el contenedor, `docker/start.sh` ejecuta `bin/crear_admin.php`, que
crea la cuenta si no existe (si ya existe no la modifica). Para cambiar la
contraseña: `php bin/crear_admin.php --reset` con el nuevo `ADMIN_PASSWORD`.

El permiso se valida en el servidor (`includes/Admin.php`, usado por
`PublicacionController::destroy` y `RespuestaController::destroy`); el panel
solo muestra el botón *Eliminar* en todas las tarjetas y una insignia ADMIN.
Cada borrado ajeno queda en el log (`[admin] ... eliminó la publicación #id`).

## Reacciones (mandarina y tomate)

Cada publicación tiene dos botones: 🍊 **mandarina** (like) y 🍅 **tomate**
(dislike), con su contador visible. Reglas, validadas en el servidor
(`controllers/ReaccionController.php`) y por la clave primaria
`(publicacion_id, user_id)` de la tabla `reacciones`:

- No se puede reaccionar a la publicación propia (403).
- Un usuario tiene una sola reacción por publicación: nunca las dos a la
  vez. Puede cambiarla de mandarina a tomate (o al revés) o quitarla tocando
  de nuevo la que eligió (`DELETE /api/v1/reacciones.php?publicacion_id=ID`,
  204). Repetir la misma por POST devuelve 409.
- Solo aplica a publicaciones, no a respuestas.

`GET /api/v1/reacciones.php` devuelve las reacciones del usuario (va aparte del
feed, que es igual para todos y se cachea en Redis) y
`POST /api/v1/reacciones.php` con `{publicacion_id, tipo}` reacciona o cambia
la reacción. Los
contadores (`likes`, `dislikes`) vienen en cada publicación del feed y se
actualizan en vivo. Las animaciones (mandarinas que suben, tomate que se
estrella contra la tarjeta) las ve solo quien hace clic, se eliminan al
terminar y se omiten con `prefers-reduced-motion`.

## Saltos de línea

El cuadro de contenido acepta Enter para bajar de renglón y las publicaciones
(y respuestas) lo muestran igual (`white-space: pre-wrap`). El servidor
(`includes/Texto.php`) conserva los saltos de línea, con un máximo de una
línea en blanco seguida entre párrafos; el título es siempre de una línea.

## Mandrainos activos

El cuadro de arriba a la derecha muestra cuántos usuarios tienen el panel
abierto y visible. No hay un sondeo extra: cada consulta de
`GET /api/v1/novedades.php` (cada ~3 s por pestaña visible) funciona como
"latido" y la respuesta trae `activos`. Se cuenta como activo a quien dio señal
en los últimos 20 s (`Presencia::VENTANA`); varias pestañas del mismo usuario
cuentan una vez y al cerrar sesión se sale del conteo enseguida. Con Redis es
un conjunto ordenado (`presencia`); sin Redis usa la tabla `presencia` de
PostgreSQL (`includes/Presencia.php`).

## Publicaciones duplicadas

**Causa:** el formulario no se bloqueaba mientras esperaba al servidor, así que
un segundo clic en *Publicar* (o un segundo Enter en el título) enviaba lo
mismo otra vez. **Prevención**, en tres capas:

1. El panel deshabilita el botón ("Enviando...") hasta recibir la respuesta.
2. El servidor toma un candado corto en Redis por usuario, para que dos envíos
   simultáneos no se pisen.
3. `Publicacion::crearSinDuplicar` inserta solo si el mismo usuario no publicó
   exactamente lo mismo en los últimos 60 s (todo en una sola sentencia SQL); si
   ya existía devuelve esa (200 en vez de 201), sin crear otra.

**Limpieza de las que ya existen** (solo administrador): botón *🧹 Duplicados*
en el panel (cuenta, pide confirmación y elimina), `GET`/`DELETE
/api/v1/duplicadas.php`, o por consola `php bin/limpiar_duplicados.php
--simular` (sin `--simular` borra de verdad). Es duplicada la publicación del
mismo usuario con igual título y contenido (sin distinguir mayúsculas ni
espacios de más) creada hasta 10 minutos después de la original; se conserva
siempre la más antigua. Al borrar una copia se borran también sus respuestas y
reacciones.

## Registro anónimo por QR

No hay formulario de contraseña en el registro: el usuario solo elige un
nickname y el sistema genera una contraseña al azar, que se entrega a
través de un QR de un solo uso.

1. `register.html` pide únicamente un nickname y llama a `register.php`.
2. `register.php` crea un registro **pendiente** (token random + hash de
   una password random), válido por 10 minutos (`Usuario::TTL_SEGUNDOS`).
   Se guarda en **Redis** con TTL nativo; el nickname queda reservado de
   forma atómica (`SET NX EX`). Todavía no existe la cuenta.
3. El navegador genera el QR localmente (librería `qrcode-generator`,
   sin backend externo) apuntando a `claim.php?token=...`.
4. Al abrir ese link (por ejemplo escaneándolo con el celular), `claim.php`
   recién ahí crea la fila real en `usuarios` y muestra la contraseña en
   texto plano **una única vez**. El registro pendiente se lee y borra en
   una sola operación atómica (`GETDEL`), sea cual sea el resultado.
5. Si pasan los 10 minutos sin reclamarlo, Redis lo expira solo y el
   nickname vuelve a estar disponible. La contraseña en texto plano vive
   únicamente en Redis y solo durante esa ventana: ya no se guarda en
   PostgreSQL.

> Si `REDIS_URL` no está definida, la app usa como respaldo la tabla
> `registros_pendientes` de PostgreSQL (comportamiento anterior).
6. `login.php` autentica por `nickname + password` (ya no por email).

Si tenías una base con el esquema viejo (con `email`, `bio`,
`profile_pic`), corré una vez `migrations/001_anonimizar_usuarios.sql`
contra tu `DATABASE_URL`. Los usuarios existentes conservan su nickname
y su password hasheada, así que van a poder loguearse igual.

**Nota de seguridad:** el QR en sí no "anonimiza" nada — cualquiera con
el link puede reclamarlo dentro de la ventana de 10 minutos. Lo que
garantiza el anonimato es que el sistema nunca pide ni guarda un dato
identificable (email, teléfono, foto): solo nickname + hash de password.

## Avatares de personaje

Después de loguearse, cualquier usuario puede subir una imagen en
`perfil.php` (pensada para fotos de personaje/ilustraciones, no fotos
reales — el registro sigue siendo 100% anónimo). Se guarda en la
columna `profile_pic` (BYTEA) de `usuarios` y se sirve a través de
`avatar.php?id={id}`, que valida el tipo de imagen y devuelve un ícono
genérico si el usuario no subió nada. Si tu base ya corrió la
migración 001 (que había sacado esa columna), corré también
`migrations/002_recuperar_profile_pic.sql`.

## Notificaciones

Cuando alguien responde a una publicación tuya, te llega un aviso: aparece una
campanita 🔔 en el encabezado con la cantidad de avisos sin leer (también en
el título de la pestaña). Al abrirla se ven los últimos 20; al hacer clic en
uno se abre la publicación con el hilo de respuestas y se resalta la respuesta
nueva. "Marcar todas como leídas" las limpia de una vez.

- Se guardan en la tabla `notificaciones` (se crea sola; ver
  `migrations/007_notificaciones.sql`) desde `RespuestaController::store`.
- No hay aviso si respondés en tu propia publicación.
- Si el worker de moderación elimina la respuesta, el aviso desaparece con
  ella (`ON DELETE CASCADE`).
- El panel las recarga junto con el feed cuando el sondeo de novedades detecta
  un cambio (ver "Actualización en vivo").

## Menú del encabezado

Mi perfil, Duplicados (solo admin) y Cerrar sesión están dentro de un menú
desplegable al hacer clic en tu avatar y nombre. Se cierra con un clic afuera
o con Escape.

## Fechas y hora argentina

Las columnas de fecha son `TIMESTAMP` (sin zona horaria) y todo se guarda en
**UTC**: `config/db.php` fija `SET TIME ZONE 'UTC'` en cada conexión. El panel
lee esas fechas como UTC y las muestra siempre en hora de Argentina
(`America/Argentina/Buenos_Aires`, formato `dd/mm/aaaa hh:mm`), sin importar la
zona del navegador. Las notificaciones usan la misma conversión para el "hace
5 min". Si la base de datos estuviera configurada en otra zona, conviene
revisar con `SHOW timezone;`.

## Contenido sensible

`includes/FiltroContenido.php` detecta lenguaje vulgar (español e
inglés, incluyendo variantes con números/símbolos tipo "leet" y letras
repetidas) en el título y contenido de una publicación.
`PublicacionController` marca cada publicación con
`contenido_sensible: true/false`, y el feed (`panel.php`) oculta el
cuerpo de esas publicaciones detrás de un aviso con un botón para
mostrarlas. Para sumar o sacar palabras, se edita la lista `PALABRAS`
en ese archivo.

## Stack tecnológico

| Capa          | Tecnología                          |
|---------------|--------------------------------------|
| Lenguaje      | PHP 8.2                              |
| Servidor web  | Apache (`mod_rewrite` habilitado)    |
| Persistencia  | PostgreSQL (Neon, serverless)        |
| Caché / estado| Redis (cliente `predis/predis`)      |
| Mensajería    | RabbitMQ (`php-amqplib/php-amqplib`) |
| Contenedor    | Docker + docker-compose              |
| Dependencias  | Composer: `vlucas/phpdotenv`, `predis/predis`, `php-amqplib/php-amqplib` |

**Justificación:** PHP + Apache permite un despliegue simple en Render vía
Docker sin infraestructura adicional. PostgreSQL en Neon da persistencia real
en la nube sin mantener un servidor propio. El modelo de ejecución de PHP con
Apache (`mod_php`) es **síncrono y bloqueante por request**: cada petición HTTP
es atendida por un proceso/hilo de Apache que espera la respuesta de la base
de datos antes de continuar; la concurrencia se logra a nivel de Apache
sirviendo múltiples requests en paralelo, no dentro de un mismo request.

## Arquitectura en capas

```
├── config/         # Conexiones: db.php (PostgreSQL), redis.php, rabbitmq.php, env.php
├── models/         # Acceso a datos (models/Publicacion.php, models/Usuario.php)
├── controllers/     # Lógica HTTP y validaciones (controllers/PublicacionController.php)
├── api/v1/          # Rutas de la API REST (api/v1/publicaciones.php + .htaccess)
├── includes/        # Helpers: auth_check, session (sesión en Redis), Cache, Feed, TiempoReal,
│                    #   RateLimiter, Cola (productor RabbitMQ), FiltroContenido
├── worker/          # consumer.php: consumidor de RabbitMQ (proceso aparte de Apache)
├── migrations/      # SQL de migración (001, 002, 003)
├── docker-compose.yml  # web + worker + redis + rabbitmq + postgres local
├── panel.php        # Página principal (feed de posts, consume la API vía fetch)
├── index.html       # Login (nickname + password)
├── register.html    # Registro anónimo (pide nickname, muestra el QR)
├── claim.php        # Página de un solo uso: crea la cuenta y muestra la password
└── login.php / logout.php / register.php
```

## Endpoints de la API

Base: `/api/v1/publicaciones`

| Método | Ruta                          | Descripción                          | Respuestas                     |
|--------|-------------------------------|---------------------------------------|---------------------------------|
| GET    | `/api/v1/publicaciones`       | Listar todas las publicaciones        | 200                              |
| GET    | `/api/v1/publicaciones/{id}`  | Obtener una publicación por id        | 200 / 404                       |
| POST   | `/api/v1/publicaciones`       | Crear una publicación (requiere sesión)| 201 / 400 / 401                |
| PUT    | `/api/v1/publicaciones/{id}`  | Editar una publicación propia         | 200 / 400 / 401 / 403 / 404     |
| DELETE | `/api/v1/publicaciones/{id}`  | Eliminar una publicación propia (o cualquiera, si sos admin) | 204 / 401 / 403 / 404           |

`GET /api/v1/novedades.php` devuelve la "versión" del contenido (ver *Actualización en vivo*). Además: `GET /api/v1/health` informa el estado de PostgreSQL, Redis y
RabbitMQ (200 si PostgreSQL responde, 503 si no). `POST /api/v1/publicaciones`
también puede responder `429` si el usuario publica más de 10 veces por minuto.
`GET /api/v1/publicaciones` agrega el header `X-Cache: HIT|MISS`.

Cualquier error no controlado devuelve `500` con un JSON estandarizado
(`{"error": "...", "detalle": "..."}`), capturado por un `set_exception_handler`
centralizado en `api/v1/publicaciones.php`.


### Respuestas a publicaciones

Base: `/api/v1/respuestas.php` (el panel usa esta URL directa, así que no
depende de reglas de `.htaccess`).

| Método | Ruta                                          | Descripción                                   | Códigos                         |
|--------|-----------------------------------------------|-----------------------------------------------|---------------------------------|
| GET    | `/api/v1/respuestas.php?publicacion_id={id}`  | Respuestas de una publicación (más viejas primero) | 200 / 400 / 404            |
| POST   | `/api/v1/respuestas.php`                      | Responder (`{publicacion_id, contenido}`, requiere sesión) | 201 / 400 / 401 / 404 / 429 |
| DELETE | `/api/v1/respuestas.php?id={id}`              | Eliminar una respuesta propia                 | 204 / 400 / 401 / 403 / 404     |

Las respuestas son de un solo nivel (se responde a la publicación, no a otra
respuesta), con un máximo de 500 caracteres y el mismo saneado de texto que
las publicaciones (`includes/Texto.php`). El listado de publicaciones agrega
`total_respuestas` a cada una. Cada alta o baja invalida la caché del feed y
publica `respuesta.creada` / `respuesta.eliminada` en RabbitMQ; el worker
registra en `moderacion_log` las respuestas con lenguaje vulgar. La tabla
`respuestas` se crea sola (ver también `migrations/004_respuestas.sql`).

### Tags

Cada publicación puede llevar hasta 5 tags (máximo 20 caracteres cada uno).
Se envían en el campo `tags` al crear (`POST`) o editar (`PUT`) como texto
(`"musica, #juegos"`) o como lista, y se normalizan en `includes/Tags.php`:
minúsculas, sin `#`, sin tildes, solo letras, números, `_` y `-`, y sin
repetidos. Un tag vulgar se rechaza con 400. En un `PUT`, si no se envía
`tags` quedan como están; si se envía (aunque sea vacío) se reemplazan.

| Método | Ruta                                  | Descripción                                                    |
|--------|---------------------------------------|----------------------------------------------------------------|
| GET    | `/api/v1/publicaciones?tag={tag}`     | Solo las publicaciones con ese tag (filtra sobre el feed cacheado) |
| GET    | `/api/v1/tags.php`                    | Tags en los que participa el usuario (publicó o respondió), con el total de publicaciones de cada uno. Requiere sesión |
| GET    | `/api/v1/tags.php?alcance=general`    | Tags más usados por todos los usuarios (top 15), con el total de publicaciones de cada uno. Requiere sesión |
| GET    | `/api/v1/notificaciones.php`          | Avisos de respuestas a tus publicaciones: `{no_leidas, items}`. Requiere sesión |
| POST   | `/api/v1/notificaciones.php`          | Marca avisos como leídos: sin body, todos; con `{"id": n}`, solo ese. Requiere sesión |

Cada publicación del feed incluye `tags` (lista). La tabla `publicacion_tags`
se crea sola (ver también `migrations/005_tags.sql`). Como cambió la forma de
los datos, la clave de caché del feed pasó a `feed:v2`.

## Redis

Es opcional: sin `REDIS_URL` la app funciona igual, solo que sin estas mejoras.
Todo el código tolera que Redis no esté (si se cae, la app sigue andando).

| Uso | Detalle |
|-----|---------|
| **Sesiones** | `includes/session.php` guarda las sesiones PHP en Redis (TTL `SESSION_TTL`, 2 h por defecto). Sobreviven a redeploys y se comparten entre instancias. Cookie `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS y `use_strict_mode`. |
| **Caché del feed** | `GET /api/v1/publicaciones` se cachea 60 s (`includes/Feed.php`). Se invalida en cada alta/edición/baja y el worker la recalienta. Un HIT no toca PostgreSQL. |
| **Rate limiting** | `includes/RateLimiter.php`: login 10 intentos/5 min por IP y 5/5 min por nickname; registro 5/10 min por IP; publicar 10/min por usuario. |
| **Registros del QR** | TTL nativo de 10 min y reserva atómica del nickname (ver arriba). |

Todas las claves llevan el prefijo `mandraina:`.

## RabbitMQ

Es opcional: sin `AMQP_URL` la API funciona igual pero no publica eventos.

Cuando se crea, edita o elimina una publicación, la API publica un evento
(`publicacion.creada | .editada | .eliminada`) en el exchange topic
`mandraina.eventos`, que enruta a la cola durable `mandraina.publicaciones`.
El proceso `worker/consumer.php` lo consume y:

1. Modera por cantidad de insultos (`FiltroContenido::contarInsultos`): con 1 a 3 el contenido sigue visible con el cartel de precaución y se registra en `moderacion_log`; con 4 o más (`UMBRAL_ELIMINACION`) la publicación o respuesta se elimina y también queda registrada.
2. Recalienta la caché del feed en Redis.

Si un mensaje falla se reintenta una vez; después va a la cola
`mandraina.publicaciones.dlq` (se puede inspeccionar en el panel de RabbitMQ).
Publicar es "best effort": si RabbitMQ está caído se escribe en el log y la
API responde igual, porque los eventos solo disparan tareas secundarias.

## Actualización en vivo

El panel se actualiza solo cuando alguien publica, edita, borra o responde (y
cuando el worker elimina contenido por moderación), sin recargar la página.

**Cómo funciona.** Cada cambio guarda en Redis un valor nuevo
(`includes/TiempoReal.php`, llamado desde `Feed::invalidar()` y desde el
worker). El panel pregunta cada 3 segundos por ese valor a
`/api/v1/novedades.php` (un solo `GET` a Redis, sin tocar PostgreSQL) y recién
cuando cambió vuelve a pedir el feed y los tags. Con la pestaña en segundo
plano no consulta, y al volver lo hace al instante. Si Redis no está
configurado, `novedades.php` calcula una huella desde PostgreSQL y todo sigue
funcionando.

**No molesta mientras usás la página.** El feed se actualiza tarjeta por
tarjeta: solo se rehacen las que cambiaron. Se conserva la respuesta que estás
escribiendo (con el foco), los hilos abiertos y el contenido sensible que ya
revelaste. Las publicaciones que llegan en vivo brillan un instante.

**Por qué sondeo y no SSE/WebSockets.** Con Apache + PHP cada conexión abierta
ocuparía un proceso mientras dure, y los proxies (Render incluido) suelen
cortarlas o bufferizarlas. El sondeo es una petición mínima, no retiene
procesos y funciona igual en cualquier hosting. La demora máxima es
`POLL_MS` (3 s, en `panel.php`).

## Variables de entorno

Ver `.env.example`. En desarrollo local, copiá ese archivo como `.env`
(nunca se commitea, está en `.gitignore`) con tu `DATABASE_URL` de Neon.
En Render, la misma variable se configura desde el dashboard del servicio
(pestaña *Environment*).

## Cómo correrlo localmente (Docker Compose)

```bash
cp .env.example .env        # opcional: sin .env se usan valores por defecto
docker compose up --build
```

Levanta cinco servicios: `web` (Apache + PHP), `worker` (consumidor de
RabbitMQ), `redis`, `rabbitmq` y `db` (PostgreSQL local).

- App: http://localhost:8080
- Estado de las dependencias: http://localhost:8080/api/v1/health
- Panel de RabbitMQ: http://localhost:15672 (usuario/clave `mandraina` / `mandraina`)

Si preferís seguir con Neon, poné tu `DATABASE_URL` en el `.env`; el
servicio `db` simplemente no se usa. Para ver el worker trabajando:
`docker compose logs -f worker`.

### Probar que Redis y RabbitMQ funcionan

1. `curl -i http://localhost:8080/api/v1/publicaciones` dos veces: el primero
   devuelve `X-Cache: MISS` y el segundo `X-Cache: HIT`.
2. Creá una publicación con groserías desde el panel y mirá
   `docker compose logs worker`: debería aparecer la línea de moderación.
3. Probá el rate limiting: 6 logins fallidos seguidos con el mismo nickname
   te llevan a `index.html?e=limite`.

### Sin Docker

Hace falta PHP 8.1+ con `pdo_pgsql` y `sockets`, Composer, y servicios de
PostgreSQL, Redis y RabbitMQ accesibles (locales o en la nube). Completá el
`.env`, corré `composer install` y servilo con Apache/nginx. El worker se
corre con `php worker/consumer.php` y tiene que quedar siempre activo
(systemd, supervisor, o un *background worker* en la plataforma de hosting).

## Despliegue online gratuito (demo)

Combinación pensada para una demo sin costo: la web en Render (Docker),
PostgreSQL en Neon, Redis en Upstash y RabbitMQ en CloudAMQP. Los tres
servicios gestionados se conectan por TLS, así que las URLs usan
`rediss://` y `amqps://`.

1. **Redis (Upstash):** crear una base y copiar la URL con TLS
   (`rediss://default:CLAVE@HOST:6379`) en `REDIS_URL`.
2. **RabbitMQ (CloudAMQP, plan Little Lemur):** crear la instancia y copiar
   la AMQP URL (`amqps://usuario:clave@host/vhost`) en `AMQP_URL`.
3. **Render:** en el servicio web, pestaña *Environment*, definir
   `DATABASE_URL`, `REDIS_URL`, `AMQP_URL`, `TRUST_PROXY=1`,
   `RUN_WORKER=1` y `APP_ENV=production`.
4. Hacer *Manual Deploy* y verificar `https://TU-SERVICIO.onrender.com/api/v1/health`:
   tiene que mostrar `ok` en `postgres`, `redis` y `rabbitmq`.

Render gratis no ofrece background workers, por eso `docker/start.sh`
lanza el consumidor de RabbitMQ dentro del mismo contenedor que Apache
cuando `RUN_WORKER=1`. Si el servicio se duerme por inactividad, el worker
se detiene con él; los eventos quedan en la cola durable de CloudAMQP y se
procesan cuando el servicio vuelve a despertar. Para una versión sin
dormirse hace falta una instancia paga.

## Diseño responsive

Todas las páginas se adaptan a celular, tablet y escritorio con CSS puro
(`styles.css` para login, registro y claim; `panel.css` para panel y perfil).
Puntos de corte: 1024 px (el login pasa a una columna), 760 px (el panel pasa
a una columna y la barra de tags se vuelve una fila deslizable), 600 px y
480 px (la cabecera se apila y los campos ocupan todo el ancho). Además:
`box-sizing: border-box` global, campos con 16 px para que iOS no haga zoom
al enfocar, `100dvh` para la barra del navegador móvil, zonas de toque más
grandes en pantallas táctiles y respeto por `prefers-reduced-motion`.

## Roadmap (AE2 / TIFAS)

- Notificaciones en vivo por usuario (por ejemplo, "respondieron tu publicación").

- Despliegue completo sin Docker (web + worker + Redis + RabbitMQ gestionados).
- Panel simple para revisar `moderacion_log`.
- Migrar hashing y validaciones a un middleware reutilizable para otras entidades.
- Agregar tests automatizados de los endpoints.
- Sumar paginación al listado de publicaciones.
- Perfil público de usuario con sus publicaciones.
