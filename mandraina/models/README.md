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

## Registro anónimo por QR

No hay formulario de contraseña en el registro: el usuario solo elige un
nickname y el sistema genera una contraseña al azar, que se entrega a
través de un QR de un solo uso.

1. `register.html` pide únicamente un nickname y llama a `register.php`.
2. `register.php` crea un registro **pendiente** en `registros_pendientes`
   (token random + hash de una password random), válido por 10 minutos
   (`Usuario::TTL_SEGUNDOS`). Todavía no existe la cuenta.
3. El navegador genera el QR localmente (librería `qrcode-generator`,
   sin backend externo) apuntando a `claim.php?token=...`.
4. Al abrir ese link (por ejemplo escaneándolo con el celular), `claim.php`
   recién ahí crea la fila real en `usuarios` y muestra la contraseña en
   texto plano **una única vez**. El registro pendiente se borra al
   instante, sea cual sea el resultado.
5. Si pasan los 10 minutos sin reclamarlo, `registros_pendientes` se
   purga solo en la siguiente request (a `register.php` o `claim.php`) y
   el nickname vuelve a estar disponible.
6. `login.php` autentica por `nickname + password` (ya no por email).

Si tenías una base con el esquema viejo (con `email`, `bio`,
`profile_pic`), corré una vez `migrations/001_anonimizar_usuarios.sql`
contra tu `DATABASE_URL`. Los usuarios existentes conservan su nickname
y su password hasheada, así que van a poder loguearse igual.

**Nota de seguridad:** el QR en sí no "anonimiza" nada — cualquiera con
el link puede reclamarlo dentro de la ventana de 10 minutos. Lo que
garantiza el anonimato es que el sistema nunca pide ni guarda un dato
identificable (email, teléfono, foto): solo nickname + hash de password.

## Stack tecnológico

| Capa          | Tecnología                          |
|---------------|--------------------------------------|
| Lenguaje      | PHP 8.2                              |
| Servidor web  | Apache (`mod_rewrite` habilitado)    |
| Persistencia  | PostgreSQL (Neon, serverless)        |
| Contenedor    | Docker                               |
| Dependencias  | Composer + `vlucas/phpdotenv`        |

**Justificación:** PHP + Apache permite un despliegue simple en Render vía
Docker sin infraestructura adicional. PostgreSQL en Neon da persistencia real
en la nube sin mantener un servidor propio. El modelo de ejecución de PHP con
Apache (`mod_php`) es **síncrono y bloqueante por request**: cada petición HTTP
es atendida por un proceso/hilo de Apache que espera la respuesta de la base
de datos antes de continuar; la concurrencia se logra a nivel de Apache
sirviendo múltiples requests en paralelo, no dentro de un mismo request.

## Arquitectura en capas

```
├── config/         # Conexión a la base de datos (config/db.php)
├── models/         # Acceso a datos (models/Publicacion.php, models/Usuario.php)
├── controllers/     # Lógica HTTP y validaciones (controllers/PublicacionController.php)
├── api/v1/          # Rutas de la API REST (api/v1/publicaciones.php + .htaccess)
├── includes/        # Helpers compartidos (auth_check.php)
├── migrations/      # SQL de migración (001_anonimizar_usuarios.sql)
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
| DELETE | `/api/v1/publicaciones/{id}`  | Eliminar una publicación propia       | 204 / 401 / 403 / 404           |

Cualquier error no controlado devuelve `500` con un JSON estandarizado
(`{"error": "...", "detalle": "..."}`), capturado por un `set_exception_handler`
centralizado en `api/v1/publicaciones.php`.

## Variables de entorno

Ver `.env.example`. En desarrollo local, copiá ese archivo como `.env`
(nunca se commitea, está en `.gitignore`) con tu `DATABASE_URL` de Neon.
En Render, la misma variable se configura desde el dashboard del servicio
(pestaña *Environment*).

## Cómo correrlo localmente (Docker)

```bash
docker build -t mandraina-forum .

docker run -p 8080:80 \
  -e DATABASE_URL="postgresql://usuario:password@host.neon.tech/db?sslmode=require" \
  mandraina-forum
```

Para editar código en vivo sin reconstruir la imagen, montá tu carpeta como volumen:

```bash
docker run -p 8080:80 \
  -e DATABASE_URL="postgresql://usuario:password@host.neon.tech/db?sslmode=require" \
  -v "$(pwd):/var/www/html" \
  mandraina-forum
```

Abrí `http://localhost:8080`.

## Roadmap (AE2 / TIFAS)

- Migrar hashing y validaciones a un middleware reutilizable para otras entidades.
- Agregar tests automatizados de los endpoints.
- Sumar paginación al listado de publicaciones.
- Perfil público de usuario con sus publicaciones.
