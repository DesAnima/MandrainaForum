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
            <img class="avatar" src="avatar.php?id=<?= (int) $_SESSION['user_id'] ?>" alt="Tu avatar" />
            <span>Hola, <strong><?= htmlspecialchars($_SESSION['nickname'] ?? '') ?></strong></span>
            <a href="perfil.php" class="btn btn-register btn-small">Mi perfil</a>
            <a href="logout.php" class="btn btn-register btn-small">Cerrar sesión</a>
        </div>
    </header>

    <div class="panel-layout">
    <aside class="tags-bar" aria-label="Tags">
        <h2>Mis tags</h2>
        <ul id="tags-list" class="tags-list"></ul>
        <p id="tags-empty" class="tags-empty" hidden>Todavía no participás en ningún tag. Publicá o respondé en una publicación con tags y van a aparecer acá.</p>
    </aside>

    <main class="panel-main">
        <section class="post-form-card">
            <h2 id="form-title">Nueva publicación</h2>
            <form id="post-form">
                <input type="hidden" id="post-id" value="" />
                <input type="text" id="titulo" class="input-box" placeholder="Título" maxlength="100" required />
                <small class="char-count" id="titulo-count">0 / 100</small>
                <textarea id="contenido" class="input-box" placeholder="¿Qué querés contar?" rows="3" maxlength="2000" required></textarea>
                <small class="char-count" id="contenido-count">0 / 2000</small>
                <input type="text" id="tags" class="input-box" placeholder="Tags (ej: musica, juegos)" maxlength="120" />
                <small class="form-hint">Hasta 5 tags separados por coma o espacio.</small>
                <div class="post-form-actions">
                    <button type="submit" class="btn btn-login" id="submit-btn">Publicar</button>
                    <button type="button" class="btn btn-register" id="cancel-edit-btn" hidden>Cancelar</button>
                </div>
            </form>
        </section>

        <div id="feed-filter" class="feed-filter" hidden>
            <span>Publicaciones con <strong id="feed-filter-tag"></strong></span>
            <button type="button" class="btn-link" id="feed-filter-clear">Ver todas</button>
        </div>

        <section id="feed" class="feed">
            <p id="feed-status">Cargando publicaciones...</p>
        </section>
    </main>
    </div>

    <script>
        const API_URL = 'api/v1/publicaciones';
        const RESPUESTAS_URL = 'api/v1/respuestas.php';
        const TAGS_URL = 'api/v1/tags.php';
        const NOVEDADES_URL = 'api/v1/novedades.php';
        const POLL_MS = 3000; // cada cuánto se pregunta si hay novedades
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
        const tituloCount = document.getElementById('titulo-count');
        const contenidoCount = document.getElementById('contenido-count');
        const tagsField = document.getElementById('tags');
        const tagsList = document.getElementById('tags-list');
        const tagsEmpty = document.getElementById('tags-empty');
        const feedFilter = document.getElementById('feed-filter');
        const feedFilterTag = document.getElementById('feed-filter-tag');

        let tagActivo = null;
        let seqTags = 0;
        let misTags = [];

        function renderTagsBar() {
            const todas = `
                <li><button type="button" class="tag-item ${tagActivo === null ? 'active' : ''}" data-tag="">Todas las publicaciones</button></li>
            `;
            const items = misTags.map((t) => `
                <li>
                    <button type="button" class="tag-item ${tagActivo === t.tag ? 'active' : ''}" data-tag="${escapeHtml(t.tag)}">
                        <span>#${escapeHtml(t.tag)}</span>
                        <span class="tag-total">${Number(t.total)}</span>
                    </button>
                </li>
            `).join('');

            tagsList.innerHTML = todas + items;
            tagsEmpty.hidden = misTags.length > 0;
        }

        async function cargarMisTags() {
            try {
                const miSeq = ++seqTags;
                const res = await fetch(TAGS_URL, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (miSeq !== seqTags) return; // llegó otra respuesta más nueva
                misTags = data;
                renderTagsBar();
            } catch (err) {
                console.error('Error al cargar los tags:', err);
            }
        }

        function seleccionarTag(tag) {
            tagActivo = tag || null;
            feedFilter.hidden = tagActivo === null;
            feedFilterTag.textContent = tagActivo === null ? '' : `#${tagActivo}`;
            renderTagsBar();
            primeraCarga = true; // las tarjetas del nuevo filtro no son "novedades"
            cargarPublicaciones();
        }

        tagsList.addEventListener('click', (e) => {
            const boton = e.target.closest('.tag-item');
            if (boton) seleccionarTag(boton.dataset.tag);
        });

        document.getElementById('feed-filter-clear').addEventListener('click', () => seleccionarTag(null));

        function actualizarContadores() {
            tituloCount.textContent = `${tituloField.value.length} / ${tituloField.maxLength}`;
            contenidoCount.textContent = `${contenidoField.value.length} / ${contenidoField.maxLength}`;
        }

        tituloField.addEventListener('input', actualizarContadores);
        contenidoField.addEventListener('input', actualizarContadores);

        // Escapa también las comillas: se usa dentro de atributos (data-titulo, data-contenido).
        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ---- Feed: se actualiza tarjeta por tarjeta, nunca se vuelve a dibujar todo ----
        // Así una actualización en vivo no te borra la respuesta que estás
        // escribiendo, no cierra los hilos abiertos ni vuelve a ocultar el
        // contenido sensible que ya revelaste.

        const firmasPosts = new Map(); // id -> "firma" de lo último que se dibujó
        let primeraCarga = true;       // la primera carga no resalta tarjetas como nuevas
        let seqFeed = 0;

        function mostrarEstado(texto) {
            feedEl.innerHTML = `<p id="feed-status">${escapeHtml(texto)}</p>`;
        }

        function hayTarjetas() {
            return feedEl.querySelector('.post-card') !== null;
        }

        function firmaPost(p) {
            return JSON.stringify([
                p.nickname, p.titulo, p.contenido, p.tags || [],
                p.contenido_sensible === true, Number(p.total_respuestas) || 0,
                p.fecha_actualizacion,
            ]);
        }

        function htmlADom(html) {
            const t = document.createElement('template');
            t.innerHTML = html.trim();
            return t.content.firstElementChild;
        }

        // Guarda lo que el usuario "tiene a medias" en una tarjeta...
        function capturarEstado(card) {
            const input = card.querySelector('.reply-input');
            const botonRevelar = card.querySelector('.reveal-btn');
            return {
                abierto: !card.querySelector('.replies').hidden,
                listaHtml: card.querySelector('.replies-list').innerHTML,
                borrador: input ? input.value : '',
                enfocado: !!input && document.activeElement === input,
                selIni: input ? input.selectionStart : 0,
                selFin: input ? input.selectionEnd : 0,
                cuerpoRevelado: !!botonRevelar && !card.querySelector('.post-body').hidden,
            };
        }

        // ...y se lo devuelve a la tarjeta nueva (ya insertada en el documento).
        function restaurarEstado(card, e) {
            if (e.cuerpoRevelado) {
                const cuerpo = card.querySelector('.post-body');
                const boton = card.querySelector('.reveal-btn');
                if (cuerpo && boton) {
                    cuerpo.hidden = false;
                    boton.textContent = 'Ocultar contenido';
                }
            }

            if (e.abierto) {
                card.querySelector('.replies').hidden = false;
                card.querySelector('.replies-list').innerHTML = e.listaHtml; // sin parpadeo
                cargarRespuestas(card, { silencioso: true });                // y se refresca
            }

            const input = card.querySelector('.reply-input');
            if (input && e.borrador) {
                input.value = e.borrador;
                card.querySelector('.reply-count').textContent = `${input.value.length} / ${input.maxLength}`;
                if (e.enfocado) {
                    input.focus();
                    input.setSelectionRange(e.selIni, e.selFin);
                }
            }
        }

        function reconciliarFeed(data) {
            // Fuera cualquier mensaje de estado ("Cargando...", "Todavía no hay...").
            feedEl.querySelectorAll(':scope > :not(.post-card)').forEach((n) => n.remove());

            const actuales = new Map(
                [...feedEl.querySelectorAll(':scope > .post-card')].map((c) => [c.dataset.id, c])
            );

            // Publicaciones que ya no están (borradas por su autora/autor o por moderación).
            const vigentes = new Set(data.map((p) => String(p.id)));
            actuales.forEach((card, id) => {
                if (!vigentes.has(id)) {
                    card.remove();
                    firmasPosts.delete(id);
                }
            });

            let cursor = feedEl.firstElementChild;
            for (const post of data) {
                const id = String(post.id);
                const firma = firmaPost(post);
                let card = actuales.get(id);
                let estado = null;

                if (!card) {
                    card = htmlADom(renderPost(post));
                    if (!primeraCarga) card.classList.add('post-nuevo');
                } else if (firmasPosts.get(id) !== firma) {
                    // Cambió (editada, nuevas respuestas, etc.): se rehace esa tarjeta.
                    estado = capturarEstado(card);
                    const nueva = htmlADom(renderPost(post));
                    if (cursor === card) cursor = card.nextElementSibling;
                    card.remove();
                    card = nueva;
                }
                firmasPosts.set(id, firma);

                if (card === cursor) {
                    cursor = cursor.nextElementSibling; // ya está en su lugar
                } else {
                    feedEl.insertBefore(card, cursor); // nueva, reemplazada o fuera de orden
                }

                if (estado) restaurarEstado(card, estado);
            }

            primeraCarga = false;
        }

        // silencioso = actualización en segundo plano: sin "Cargando..." y sin
        // mostrar errores (si falla, se reintenta en el próximo ciclo).
        async function cargarPublicaciones({ silencioso = false } = {}) {
            const miSeq = ++seqFeed;
            if (!silencioso && !hayTarjetas()) mostrarEstado('Cargando publicaciones...');

            try {
                const url = tagActivo === null ? API_URL : `${API_URL}?tag=${encodeURIComponent(tagActivo)}`;
                const res = await fetch(url, { cache: 'no-store' });
                const data = await res.json();

                if (miSeq !== seqFeed) return; // llegó otra respuesta más nueva

                if (!res.ok) {
                    if (!silencioso && !hayTarjetas()) {
                        mostrarEstado(data.error || 'No se pudieron cargar las publicaciones.');
                    }
                    return;
                }

                if (data.length === 0) {
                    firmasPosts.clear();
                    mostrarEstado(tagActivo === null
                        ? 'Todavía no hay publicaciones. ¡Sé el primero!'
                        : 'Todavía no hay publicaciones con ese tag.');
                    return;
                }

                reconciliarFeed(data);
            } catch (err) {
                console.error('Error al cargar publicaciones:', err);
                if (!silencioso && !hayTarjetas()) mostrarEstado('Error de red al cargar publicaciones.');
            }
        }

        function renderPost(post) {
            const esPropia = Number(post.user_id) === currentUserId;
            const sensible = post.contenido_sensible === true;
            const fecha = new Date(post.fecha_creacion).toLocaleString();

            return `
                <article class="post-card" data-id="${post.id}">
                    <header class="post-card-header">
                        <div class="post-author">
                            <img class="avatar avatar-sm" src="avatar.php?id=${Number(post.user_id)}" alt="" loading="lazy" />
                            <strong>${escapeHtml(post.nickname)}</strong>
                        </div>
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
                    ${(post.tags || []).length ? `
                        <div class="post-tags">
                            ${post.tags.map((t) => `<button type="button" class="tag-chip" data-tag="${escapeHtml(t)}">#${escapeHtml(t)}</button>`).join('')}
                        </div>
                    ` : ''}
                    ${esPropia ? `
                        <div class="post-card-actions">
                            <button class="btn-link edit-btn" data-id="${post.id}" data-titulo="${escapeHtml(post.titulo)}" data-contenido="${escapeHtml(post.contenido)}" data-tags="${escapeHtml((post.tags || []).join(' '))}">Editar</button>
                            <button class="btn-link delete-btn" data-id="${post.id}">Eliminar</button>
                        </div>
                    ` : ''}
                    <div class="post-replies-bar">
                        <button type="button" class="btn-link replies-toggle">💬 Respuestas (${Number(post.total_respuestas) || 0})</button>
                    </div>
                    <section class="replies" hidden>
                        <div class="replies-list"></div>
                        <form class="reply-form" data-id="${post.id}">
                            <textarea class="input-box reply-input" rows="2" maxlength="500" placeholder="Escribí una respuesta..." required></textarea>
                            <small class="char-count reply-count">0 / 500</small>
                            <button type="submit" class="btn btn-login btn-small">Responder</button>
                        </form>
                    </section>
                </article>
            `;
        }

        function renderRespuesta(r) {
            const esPropia = Number(r.user_id) === currentUserId;
            const sensible = r.contenido_sensible === true;
            const fecha = new Date(r.fecha_creacion).toLocaleString();

            return `
                <div class="reply" data-id="${r.id}">
                    <div class="reply-header">
                        <span class="post-author">
                            <img class="avatar avatar-sm" src="avatar.php?id=${Number(r.user_id)}" alt="" loading="lazy" />
                            <strong>${escapeHtml(r.nickname)}</strong>
                        </span>
                        <span class="post-date">${fecha}</span>
                    </div>
                    ${sensible ? `
                        <div class="sensible-box">
                            <p>⚠️ Esta respuesta contiene lenguaje sensible.</p>
                            <button type="button" class="btn-link reveal-reply-btn">Mostrar contenido</button>
                        </div>
                    ` : ''}
                    <p class="reply-body" ${sensible ? 'hidden' : ''}>${escapeHtml(r.contenido)}</p>
                    ${esPropia ? `<button type="button" class="btn-link delete-reply-btn" data-id="${r.id}">Eliminar</button>` : ''}
                </div>
            `;
        }

        async function cargarRespuestas(card, { silencioso = false } = {}) {
            const lista = card.querySelector('.replies-list');
            if (!silencioso) lista.textContent = 'Cargando respuestas...';

            try {
                const res = await fetch(`${RESPUESTAS_URL}?publicacion_id=${card.dataset.id}`, { cache: 'no-store' });
                const data = await res.json();

                if (!res.ok) {
                    if (!silencioso) lista.textContent = data.error || 'No se pudieron cargar las respuestas.';
                    return;
                }
                if (!card.isConnected) return; // la tarjeta se quitó mientras tanto

                // Respuestas con lenguaje sensible que el usuario ya había revelado.
                const reveladas = [...lista.querySelectorAll('.reply')]
                    .filter((r) => r.querySelector('.reveal-reply-btn') && !r.querySelector('.reply-body').hidden)
                    .map((r) => r.dataset.id);

                lista.innerHTML = data.length
                    ? data.map(renderRespuesta).join('')
                    : '<p class="replies-empty">Todavía no hay respuestas.</p>';

                reveladas.forEach((id) => {
                    const r = lista.querySelector(`.reply[data-id="${id}"]`);
                    const cuerpo = r && r.querySelector('.reply-body');
                    const boton = r && r.querySelector('.reveal-reply-btn');
                    if (cuerpo && boton) {
                        cuerpo.hidden = false;
                        boton.textContent = 'Ocultar contenido';
                    }
                });

                card.querySelector('.replies-toggle').textContent = `💬 Respuestas (${data.length})`;
            } catch (err) {
                console.error('Error al cargar respuestas:', err);
                if (!silencioso) lista.textContent = 'Error de red al cargar las respuestas.';
            }
        }

        feedEl.addEventListener('input', (e) => {
            if (!e.target.classList.contains('reply-input')) return;
            const contador = e.target.closest('.reply-form').querySelector('.reply-count');
            contador.textContent = `${e.target.value.length} / ${e.target.maxLength}`;
        });

        feedEl.addEventListener('submit', async (e) => {
            const form = e.target.closest('.reply-form');
            if (!form) return;
            e.preventDefault();

            const card = form.closest('.post-card');
            const input = form.querySelector('.reply-input');
            const boton = form.querySelector('button[type="submit"]');
            const payload = {
                publicacion_id: Number(form.dataset.id),
                contenido: input.value.trim(),
            };

            boton.disabled = true;
            try {
                const res = await fetch(RESPUESTAS_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok) {
                    alert(data.error || 'No se pudo guardar la respuesta.');
                    return;
                }

                input.value = '';
                form.querySelector('.reply-count').textContent = `0 / ${input.maxLength}`;
                cargarRespuestas(card);
                cargarMisTags();
            } catch (err) {
                console.error('Error al guardar la respuesta:', err);
                alert('Error de red al guardar la respuesta.');
            } finally {
                boton.disabled = false;
            }
        });

        function resetForm() {
            idField.value = '';
            tituloField.value = '';
            contenidoField.value = '';
            tagsField.value = '';
            actualizarContadores();
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
                tags: tagsField.value.trim(),
            };

            const url = id ? `${API_URL}/${id}` : API_URL;
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
                cargarMisTags();
            } catch (err) {
                console.error('Error al guardar la publicación:', err);
                alert('Error de red al guardar la publicación.');
            }
        });

        cancelBtn.addEventListener('click', resetForm);

        feedEl.addEventListener('click', async (e) => {
            if (e.target.classList.contains('tag-chip')) {
                seleccionarTag(e.target.dataset.tag);
                return;
            }

            if (e.target.classList.contains('replies-toggle')) {
                const card = e.target.closest('.post-card');
                const seccion = card.querySelector('.replies');
                seccion.hidden = !seccion.hidden;
                if (!seccion.hidden) cargarRespuestas(card);
                return;
            }

            if (e.target.classList.contains('reveal-reply-btn')) {
                const cuerpo = e.target.closest('.reply').querySelector('.reply-body');
                cuerpo.hidden = !cuerpo.hidden;
                e.target.textContent = cuerpo.hidden ? 'Mostrar contenido' : 'Ocultar contenido';
                return;
            }

            if (e.target.classList.contains('delete-reply-btn')) {
                if (!confirm('¿Seguro que querés eliminar esta respuesta?')) return;
                const card = e.target.closest('.post-card');

                try {
                    const res = await fetch(`${RESPUESTAS_URL}?id=${e.target.dataset.id}`, { method: 'DELETE' });

                    if (res.status !== 204) {
                        const data = await res.json();
                        alert(data.error || 'No se pudo eliminar la respuesta.');
                        return;
                    }

                    cargarRespuestas(card);
                } catch (err) {
                    console.error('Error al eliminar la respuesta:', err);
                    alert('Error de red al eliminar la respuesta.');
                }
                return;
            }

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
                tagsField.value = e.target.dataset.tags || '';
                actualizarContadores();
                submitBtn.textContent = 'Guardar cambios';
                formTitle.textContent = 'Editar publicación';
                cancelBtn.hidden = false;
                tituloField.focus();
            }

            if (e.target.classList.contains('delete-btn')) {
                const id = e.target.dataset.id;
                if (!confirm('¿Seguro que querés eliminar esta publicación?')) return;

                try {
                    const res = await fetch(`${API_URL}/${id}`, { method: 'DELETE' });

                    if (res.status !== 204) {
                        const data = await res.json();
                        alert(data.error || 'No se pudo eliminar la publicación.');
                        return;
                    }

                    cargarPublicaciones();
                    cargarMisTags();
                } catch (err) {
                    console.error('Error al eliminar la publicación:', err);
                    alert('Error de red al eliminar la publicación.');
                }
            }
        });

        // ---- Tiempo real ----
        // Cada POLL_MS se pregunta por la "versión" del contenido (una
        // consulta mínima a Redis). Solo si cambió se vuelve a pedir el feed y
        // los tags. Con la pestaña en segundo plano no se consulta, y al
        // volver se consulta enseguida.
        let versionRT = null;
        let consultando = false;

        async function leerVersion() {
            const res = await fetch(NOVEDADES_URL, { cache: 'no-store' });
            if (res.status === 401) {
                window.location.href = 'index.html'; // la sesión venció
                return null;
            }
            if (!res.ok) return null;
            const data = await res.json();
            return typeof data.version === 'string' ? data.version : null;
        }

        async function buscarNovedades() {
            if (document.hidden || consultando) return;
            consultando = true;
            try {
                const version = await leerVersion();
                if (version === null) return;

                if (versionRT === null) {
                    versionRT = version; // primera lectura: punto de partida
                } else if (version !== versionRT) {
                    versionRT = version;
                    await Promise.all([cargarPublicaciones({ silencioso: true }), cargarMisTags()]);
                }
            } catch (err) {
                // Sin red o servidor caído: se reintenta en el próximo ciclo.
            } finally {
                consultando = false;
            }
        }

        renderTagsBar();

        // La versión se lee ANTES (en paralelo) de cargar el feed: si algo cambia
        // mientras carga, el primer sondeo lo detecta.
        leerVersion().then((v) => { if (versionRT === null) versionRT = v; }).catch(() => {});
        cargarPublicaciones();
        cargarMisTags();

        setInterval(buscarNovedades, POLL_MS);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) buscarNovedades();
        });
    </script>
</body>
</html>
