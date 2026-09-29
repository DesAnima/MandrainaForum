<?php
require_once __DIR__ . '/includes/auth_check.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mandraina Forum</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="panel.css" />
</head>
<body class="panel-body">
    <header class="panel-header">
        <h1>Mandraina Forum</h1>
        <div class="panel-header-user">
            <span>Hola, <strong><?= htmlspecialchars($_SESSION['nickname'] ?? '') ?></strong></span>
            <a href="logout.php" class="btn btn-register btn-small">Cerrar sesión</a>
        </div>
    </header>

    <main class="panel-main">
        <section class="post-form-card">
            <h2 id="form-title">Nueva publicación</h2>
            <form id="post-form">
                <input type="hidden" id="post-id" value="" />
                <input type="text" id="titulo" class="input-box" placeholder="Título" required />
                <textarea id="contenido" class="input-box" placeholder="¿Qué querés contar?" rows="3" required></textarea>
                <div class="post-form-actions">
                    <button type="submit" class="btn btn-login" id="submit-btn">Publicar</button>
                    <button type="button" class="btn btn-register" id="cancel-edit-btn" hidden>Cancelar</button>
                </div>
            </form>
        </section>

        <section id="feed" class="feed">
            <p id="feed-status">Cargando publicaciones...</p>
        </section>
    </main>

    <script>
        const API_URL = 'api/v1/publicaciones.php';
        const currentUserId = <?= (int) $_SESSION['user_id'] ?>;

        const feedEl = document.getElementById('feed');
        const feedStatus = document.getElementById('feed-status');
        const form = document.getElementById('post-form');
        const idField = document.getElementById('post-id');
        const tituloField = document.getElementById('titulo');
        const contenidoField = document.getElementById('contenido');
        const submitBtn = document.getElementById('submit-btn');
        const cancelBtn = document.getElementById('cancel-edit-btn');
        const formTitle = document.getElementById('form-title');

        // Escapa también las comillas: se usa dentro de atributos (data-titulo, data-contenido).
        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        async function cargarPublicaciones() {
            feedStatus.textContent = 'Cargando publicaciones...';
            try {
                const res = await fetch(API_URL);
                const data = await res.json();

                if (!res.ok) {
                    feedStatus.textContent = data.error || 'No se pudieron cargar las publicaciones.';
                    return;
                }

                if (data.length === 0) {
                    feedEl.innerHTML = '<p id="feed-status">Todavía no hay publicaciones. ¡Sé el primero!</p>';
                    return;
                }

                feedEl.innerHTML = data.map(renderPost).join('');
            } catch (err) {
                console.error('Error al cargar publicaciones:', err);
                feedStatus.textContent = 'Error de red al cargar publicaciones.';
            }
        }

        function renderPost(post) {
            const esPropia = Number(post.user_id) === currentUserId;
            const sensible = post.contenido_sensible === true;
            const fecha = new Date(post.fecha_creacion).toLocaleString();

            return `
                <article class="post-card" data-id="${post.id}">
                    <header class="post-card-header">
                        <strong>${escapeHtml(post.nickname)}</strong>
                        <span class="post-date">${fecha}</span>
                    </header>
                    ${sensible ? `
                        <div class="sensible-box">
                            <p>⚠️ Esta publicación contiene lenguaje sensible.</p>
                            <button type="button" class="btn-link reveal-btn">Mostrar contenido</button>
                        </div>
                    ` : ''}
                    <div class="post-body" ${sensible ? 'hidden' : ''}>
                        <h3>${escapeHtml(post.titulo)}</h3>
                        <p>${escapeHtml(post.contenido)}</p>
                    </div>
                    ${esPropia ? `
                        <div class="post-card-actions">
                            <button class="btn-link edit-btn" data-id="${post.id}" data-titulo="${escapeHtml(post.titulo)}" data-contenido="${escapeHtml(post.contenido)}">Editar</button>
                            <button class="btn-link delete-btn" data-id="${post.id}">Eliminar</button>
                        </div>
                    ` : ''}
                </article>
            `;
        }

        function resetForm() {
            idField.value = '';
            tituloField.value = '';
            contenidoField.value = '';
            submitBtn.textContent = 'Publicar';
            formTitle.textContent = 'Nueva publicación';
            cancelBtn.hidden = true;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = idField.value;
            const payload = {
                titulo: tituloField.value.trim(),
                contenido: contenidoField.value.trim(),
            };

            const url = id ? `${API_URL}?id=${id}` : API_URL;
            const method = id ? 'PUT' : 'POST';

            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok) {
                    alert(data.error || 'Ocurrió un error al guardar la publicación.');
                    return;
                }

                resetForm();
                cargarPublicaciones();
            } catch (err) {
                alert('Error de red al guardar la publicación.');
            }
        });

        cancelBtn.addEventListener('click', resetForm);

        feedEl.addEventListener('click', async (e) => {
            if (e.target.classList.contains('reveal-btn')) {
                const body = e.target.closest('.post-card').querySelector('.post-body');
                body.hidden = !body.hidden;
                e.target.textContent = body.hidden ? 'Mostrar contenido' : 'Ocultar contenido';
                return;
            }

            if (e.target.classList.contains('edit-btn')) {
                idField.value = e.target.dataset.id;
                tituloField.value = e.target.dataset.titulo;
                contenidoField.value = e.target.dataset.contenido;
                submitBtn.textContent = 'Guardar cambios';
                formTitle.textContent = 'Editar publicación';
                cancelBtn.hidden = false;
                tituloField.focus();
            }

            if (e.target.classList.contains('delete-btn')) {
                const id = e.target.dataset.id;
                if (!confirm('¿Seguro que querés eliminar esta publicación?')) return;

                try {
                    const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });

                    if (res.status !== 204) {
                        const data = await res.json();
                        alert(data.error || 'No se pudo eliminar la publicación.');
                        return;
                    }

                    cargarPublicaciones();
                } catch (err) {
                    alert('Error de red al eliminar la publicación.');
                }
            }
        });

        cargarPublicaciones();
    </script>
</body>
</html>
