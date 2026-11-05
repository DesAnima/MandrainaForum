<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/Admin.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mandraina Forum</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="panel.css?v=<?= (int) @filemtime(__DIR__ . '/panel.css') ?>" />
</head>
<body class="panel-body">
    <header class="panel-header">
        <h1>Mandraina Forum</h1>
        <!-- Mascota: un cobayo que pasea por la barra y salta si lo tocás -->
        <div class="cobayo-pista" id="cobayo-pista">
            <button type="button" class="cobayo" id="cobayo" aria-label="Mandraina, la mascota del foro. Tocala para que salte" title="¡Mandraina!">
                <span class="cobayo-cuerpo" id="cobayo-cuerpo"></span>
                <span class="cobayo-globo" id="cobayo-globo" aria-hidden="true">¡wiik!</span>
            </button>
        </div>
        <div class="panel-header-user">
            <div class="activos-box" id="activos-box" role="status" aria-live="polite" title="Usuarios con el panel abierto en este momento">
                <span class="activos-label"><span class="activos-punto" aria-hidden="true"></span>Mandrainos activos</span>
                <strong class="activos-num" id="activos-num">–</strong>
            </div>
            <div class="dropdown notif-wrap" id="notif-wrap">
                <button type="button" class="notif-btn" id="notif-btn" aria-label="Notificaciones" aria-haspopup="true" aria-expanded="false" title="Notificaciones">
                    <span aria-hidden="true">🔔</span>
                    <span class="notif-badge" id="notif-badge" hidden>0</span>
                </button>
                <div class="dropdown-panel notif-panel" id="notif-panel" role="menu" hidden>
                    <div class="notif-head">
                        <strong>Notificaciones</strong>
                        <button type="button" class="btn-link" id="notif-leer-todas" hidden>Marcar todas como leídas</button>
                    </div>
                    <ul class="notif-list" id="notif-list"></ul>
                    <p class="notif-empty" id="notif-empty" hidden>Todavía no tenés notificaciones. Cuando alguien responda a tus publicaciones, te avisamos acá.</p>
                </div>
            </div>
            <div class="dropdown" id="user-menu">
                <button type="button" class="user-menu-btn" id="user-menu-btn" aria-haspopup="true" aria-expanded="false">
                    <img class="avatar" src="avatar.php?id=<?= (int) $_SESSION['user_id'] ?>" alt="Tu avatar" />
                    <span class="user-menu-nombre">Hola, <strong><?= htmlspecialchars($_SESSION['nickname'] ?? '') ?></strong><?php if (Admin::esAdmin()): ?> <span class="badge-admin">ADMIN</span><?php endif; ?></span>
                    <span class="user-menu-flecha" aria-hidden="true">▾</span>
                </button>
                <div class="dropdown-panel user-menu-panel" id="user-menu-panel" role="menu" hidden>
                    <a href="perfil.php" class="dropdown-item" role="menuitem">👤 Mi perfil</a>
                    <?php if (Admin::esAdmin()): ?>
                    <button type="button" id="btn-duplicados" class="dropdown-item" role="menuitem" title="Buscar y eliminar publicaciones duplicadas">🧹 Duplicados</button>
                    <?php endif; ?>
                    <a href="logout.php" class="dropdown-item" role="menuitem">🚪 Cerrar sesión</a>
                </div>
            </div>
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
                <textarea id="contenido" class="input-box" placeholder="¿Qué querés contar?" rows="6" maxlength="2000" required></textarea>
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
        <div id="feed-more" class="feed-more" aria-live="polite"></div>
    </main>

    <aside class="tags-bar tags-bar-general" aria-label="Tags generales">
        <h2>Tags generales</h2>
        <ul id="tags-generales-list" class="tags-list"></ul>
        <p id="tags-generales-empty" class="tags-empty" hidden>Todavía nadie usó tags.</p>
        <button type="button" class="btn-link tags-ver-todos" id="tags-ver-todos" hidden>Ver todos los tags</button>
    </aside>
    </div>

    <dialog id="tags-modal" class="tags-modal" aria-labelledby="tags-modal-titulo">
        <div class="tags-modal-head">
            <h2 id="tags-modal-titulo">Todos los tags</h2>
            <button type="button" class="btn-link" id="tags-modal-cerrar">Cerrar</button>
        </div>
        <ul id="tags-modal-list" class="tags-list"></ul>
        <p id="tags-modal-estado" class="tags-empty"></p>
    </dialog>

    <button type="button" id="btn-subir" class="btn-subir" aria-label="Volver arriba" title="Volver arriba" aria-hidden="true" tabindex="-1"><span class="btn-subir-flecha" aria-hidden="true">↑</span></button>

    <script>
        const API_URL = 'api/v1/publicaciones';
        const RESPUESTAS_URL = 'api/v1/respuestas.php';
        const TAGS_URL = 'api/v1/tags.php';
        const REACCIONES_URL = 'api/v1/reacciones.php';
        const DUPLICADAS_URL = 'api/v1/duplicadas.php';
        const NOVEDADES_URL = 'api/v1/novedades.php';
        const NOTIF_URL = 'api/v1/notificaciones.php';
        const POLL_MS = 3000; // cada cuánto se pregunta si hay novedades
        const PAGINA = 10;          // publicaciones que se piden por vez al bajar
        const MAX_REFRESCO = 100;   // tope de tarjetas que se refrescan en cada actualización en vivo
        const currentUserId = <?= (int) $_SESSION['user_id'] ?>;
        const esAdmin = <?= Admin::esAdmin() ? 'true' : 'false' ?>;

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
        let tagsGenerales = [];
        let totalTagsGenerales = 0;   // cuántos tags existen en total (puede ser más de los que se muestran)
        let tagsTodos = [];
        let seqTagsGenerales = 0;
        let seqTagsTodos = 0;
        const tagsGeneralesList = document.getElementById('tags-generales-list');
        const tagsGeneralesEmpty = document.getElementById('tags-generales-empty');
        const tagsVerTodos = document.getElementById('tags-ver-todos');
        const tagsModal = document.getElementById('tags-modal');
        const tagsModalList = document.getElementById('tags-modal-list');
        const tagsModalEstado = document.getElementById('tags-modal-estado');

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

        // Una fila de los tags generales (también en "ver todos"). Los fijados llevan
        // 📌 para todos; el admin además ve el botón para fijar o dejar de fijar.
        function htmlTagGeneral(t) {
            const marca = t.fijado
                ? '<span class="tag-pin-marca" title="Fijado por el administrador" aria-label="Fijado">📌</span>'
                : '';
            const alternar = esAdmin
                ? `<button type="button" class="tag-pin-btn ${t.fijado ? 'activo' : ''}" data-pin="${escapeHtml(t.tag)}" data-fijado="${t.fijado ? '1' : '0'}"
                           title="${t.fijado ? 'Dejar de fijar' : 'Fijar arriba'}" aria-label="${t.fijado ? 'Dejar de fijar' : 'Fijar'} el tag ${escapeHtml(t.tag)}">${t.fijado ? '✕' : '📌'}</button>`
                : '';
            return `
                <li class="tag-row">
                    <button type="button" class="tag-item ${tagActivo === t.tag ? 'active' : ''}" data-tag="${escapeHtml(t.tag)}">
                        <span>${marca}#${escapeHtml(t.tag)}</span>
                        <span class="tag-total">${Number(t.total)}</span>
                    </button>
                    ${alternar}
                </li>
            `;
        }

        function renderTagsGenerales() {
            tagsGeneralesList.innerHTML = tagsGenerales.map(htmlTagGeneral).join('');
            tagsGeneralesEmpty.hidden = tagsGenerales.length > 0;

            // Si hay más tags de los que se muestran, se ofrece verlos todos.
            const hayMasTags = totalTagsGenerales > tagsGenerales.length;
            tagsVerTodos.hidden = !hayMasTags;
            tagsVerTodos.textContent = `Ver todos los tags (${totalTagsGenerales})`;
        }

        async function cargarTagsGenerales() {
            try {
                const miSeq = ++seqTagsGenerales;
                const res = await fetch(`${TAGS_URL}?alcance=general`, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (miSeq !== seqTagsGenerales) return; // llegó otra respuesta más nueva
                tagsGenerales = Array.isArray(data.tags) ? data.tags : [];
                totalTagsGenerales = Number(data.total_tags) || tagsGenerales.length;
                renderTagsGenerales();
            } catch (err) {
                console.error('Error al cargar los tags generales:', err);
            }
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

        // ---- "Ver todos los tags" (ventana) ----
        function renderTagsTodos() {
            tagsModalList.innerHTML = tagsTodos.map(htmlTagGeneral).join('');
            tagsModalEstado.textContent = tagsTodos.length ? '' : 'Todavía nadie usó tags.';
        }

        async function cargarTagsTodos() {
            const miSeq = ++seqTagsTodos;
            try {
                const res = await fetch(`${TAGS_URL}?alcance=todos`, { cache: 'no-store' });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                if (miSeq !== seqTagsTodos) return;
                tagsTodos = Array.isArray(data.tags) ? data.tags : [];
                renderTagsTodos();
            } catch (err) {
                if (miSeq !== seqTagsTodos) return;
                console.error('Error al cargar todos los tags:', err);
                tagsModalEstado.textContent = 'No se pudieron cargar los tags.';
            }
        }

        function abrirModalTags() {
            if (!tagsModal.open) tagsModal.showModal();
            tagsModalEstado.textContent = tagsTodos.length ? '' : 'Cargando tags...';
            renderTagsTodos();
            cargarTagsTodos();
        }

        function cerrarModalTags() {
            if (tagsModal.open) tagsModal.close();
        }

        tagsVerTodos.addEventListener('click', abrirModalTags);
        document.getElementById('tags-modal-cerrar').addEventListener('click', cerrarModalTags);
        tagsModal.addEventListener('click', (e) => {
            if (e.target === tagsModal) cerrarModalTags(); // clic en el fondo oscuro
        });

        // Fijar / dejar de fijar un tag (solo admin; el servidor lo vuelve a verificar).
        async function alternarFijado(tag, fijar) {
            try {
                const res = fijar
                    ? await fetch(TAGS_URL, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tag }),
                    })
                    : await fetch(`${TAGS_URL}?tag=${encodeURIComponent(tag)}`, { method: 'DELETE' });

                if (!res.ok) {
                    let mensaje = null;
                    try { mensaje = (await res.json()).error; } catch (_) {}
                    alert(mensaje || 'No se pudo cambiar el tag fijado.');
                    return;
                }
                await Promise.all([cargarTagsGenerales(), tagsModal.open ? cargarTagsTodos() : null]);
            } catch (err) {
                console.error('Error al fijar el tag:', err);
                alert('Error de red al fijar el tag.');
            }
        }

        function clicTagGeneral(e) {
            const pin = e.target.closest('.tag-pin-btn');
            if (pin) {
                alternarFijado(pin.dataset.pin, pin.dataset.fijado !== '1');
                return;
            }
            const boton = e.target.closest('.tag-item');
            if (boton) {
                cerrarModalTags();
                seleccionarTag(boton.dataset.tag);
            }
        }

        function seleccionarTag(tag) {
            tagActivo = tag || null;
            feedFilter.hidden = tagActivo === null;
            feedFilterTag.textContent = tagActivo === null ? '' : `#${tagActivo}`;
            renderTagsBar();
            renderTagsGenerales();
            primeraCarga = true; // las tarjetas del nuevo filtro no son "novedades"
            // Otro filtro, otro feed: se descarta lo cargado y la paginación en curso.
            generacionFiltro++;
            feedEl.innerHTML = '';
            firmasPosts.clear();
            hayMas = false;
            errorMas = false;
            cargandoMas = false;
            actualizarPie();
            cargarPublicaciones();
        }

        tagsList.addEventListener('click', (e) => {
            const boton = e.target.closest('.tag-item');
            if (boton) seleccionarTag(boton.dataset.tag);
        });

        tagsGeneralesList.addEventListener('click', clicTagGeneral);
        tagsModalList.addEventListener('click', clicTagGeneral);

        document.getElementById('feed-filter-clear').addEventListener('click', () => seleccionarTag(null));

        function actualizarContadores() {
            tituloCount.textContent = `${tituloField.value.length} / ${tituloField.maxLength}`;
            contenidoCount.textContent = `${contenidoField.value.length} / ${contenidoField.maxLength}`;
        }

        tituloField.addEventListener('input', actualizarContadores);
        contenidoField.addEventListener('input', actualizarContadores);

        // ---- Fechas en hora argentina ----
        // El servidor guarda las fechas en UTC y las manda sin zona
        // ("2026-11-05 03:22:11.123456"). Si el navegador las leyera tal cual
        // las tomaría como hora local y mostraría 3 horas de más. Acá se leen
        // como UTC y se muestran siempre en hora de Argentina, esté donde esté
        // quien mira el panel.
        const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

        function leerFechaServidor(valor) {
            if (!valor) return null;
            let texto = String(valor).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1');
            if (!/(Z|[+-]\d{2}:?\d{2})$/.test(texto)) texto += 'Z'; // sin zona: es UTC
            const fecha = new Date(texto);
            return Number.isNaN(fecha.getTime()) ? null : fecha;
        }

        function formatearFecha(valor) {
            const fecha = leerFechaServidor(valor);
            if (!fecha) return '';
            return fecha.toLocaleString('es-AR', {
                timeZone: ZONA_HORARIA,
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', hour12: false,
            }).replace(',', '');
        }

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
        let misReacciones = {};        // id de publicación -> 'like' | 'dislike' (solo las mías)
        let primeraCarga = true;       // la primera carga no resalta tarjetas como nuevas
        let seqFeed = 0;
        let hayMas = false;           // quedan publicaciones más viejas sin cargar
        let cargandoMas = false;      // hay una página pidiéndose al bajar
        let errorMas = false;         // la última página falló: se ofrece reintentar
        let generacionFiltro = 0;     // cambia con cada filtro, para descartar respuestas viejas

        const pieEl = document.getElementById('feed-more');

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
                Number(p.likes) || 0, Number(p.dislikes) || 0, misReacciones[p.id] || null,
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

        function reconciliarFeed(data, hayMasServidor = false) {
            // Fuera cualquier mensaje de estado ("Cargando...", "Todavía no hay...").
            feedEl.querySelectorAll(':scope > :not(.post-card)').forEach((n) => n.remove());

            const actuales = new Map(
                [...feedEl.querySelectorAll(':scope > .post-card')].map((c) => [c.dataset.id, c])
            );

            // Publicaciones que ya no están (borradas por su autora/autor o por moderación).
            // La respuesta cubre solo las N más nuevas: las tarjetas más viejas que
            // la última recibida están fuera de rango, no borradas, y se conservan.
            const vigentes = new Set(data.map((p) => String(p.id)));
            const menorId = Number(data[data.length - 1].id);
            let fueraDeRango = false;
            actuales.forEach((card, id) => {
                if (vigentes.has(id)) return;
                if (hayMasServidor && Number(id) < menorId) {
                    fueraDeRango = true;
                    return;
                }
                card.remove();
                firmasPosts.delete(id);
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

            hayMas = hayMasServidor || fueraDeRango;
            primeraCarga = false;
        }

        function urlFeed(params) {
            const q = new URLSearchParams(params);
            if (tagActivo !== null) q.set('tag', tagActivo);
            return `${API_URL}?${q}`;
        }

        function idUltimaTarjeta() {
            const t = feedEl.querySelectorAll(':scope > .post-card');
            return t.length ? Number(t[t.length - 1].dataset.id) : null;
        }

        // Pie del feed: estado de la carga al bajar. Es también el "centinela"
        // que el observador vigila para pedir la página siguiente.
        const observadorPie = 'IntersectionObserver' in window
            ? new IntersectionObserver((entradas) => {
                if (entradas.some((e) => e.isIntersecting)) cargarMas();
            }, { rootMargin: '600px 0px' })
            : null;

        function actualizarPie() {
            if (!hayTarjetas()) {
                pieEl.innerHTML = '';
                return;
            }
            if (cargandoMas) {
                pieEl.innerHTML = '<p class="feed-more-text">Cargando más publicaciones...</p>';
            } else if (errorMas) {
                pieEl.innerHTML = '<button type="button" class="btn-link" id="feed-more-retry">No se pudieron cargar más. Reintentar</button>';
            } else if (hayMas) {
                pieEl.innerHTML = observadorPie
                    ? ''
                    : '<button type="button" class="btn-link" id="feed-more-retry">Ver más publicaciones</button>';
            } else {
                pieEl.innerHTML = '<p class="feed-more-text">No hay más publicaciones.</p>';
            }

            // Se vuelve a observar para que dispare de nuevo si el pie sigue a la
            // vista (pantalla alta o página corta) después de cargar.
            if (observadorPie) {
                observadorPie.unobserve(pieEl);
                if (hayMas && !cargandoMas && !errorMas) observadorPie.observe(pieEl);
            }
        }

        pieEl.addEventListener('click', (e) => {
            if (e.target.closest('#feed-more-retry')) cargarMas();
        });

        // Pide las publicaciones anteriores a la última tarjeta cargada (cursor
        // por id) y las agrega al final, sin tocar las que ya están.
        async function cargarMas(limite = PAGINA) {
            if (cargandoMas || !hayMas) return;
            const antes = idUltimaTarjeta();
            if (antes === null) return;

            const gen = generacionFiltro;
            cargandoMas = true;
            errorMas = false;
            actualizarPie();

            try {
                const res = await fetch(urlFeed({ antes, limite }), { cache: 'no-store' });
                const data = await res.json();
                if (gen !== generacionFiltro) return; // cambió el filtro mientras tanto
                if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);

                for (const post of data.publicaciones || []) {
                    const id = String(post.id);
                    if (feedEl.querySelector(`:scope > .post-card[data-id="${id}"]`)) continue;
                    feedEl.appendChild(htmlADom(renderPost(post)));
                    firmasPosts.set(id, firmaPost(post));
                }
                hayMas = data.hay_mas === true;
            } catch (err) {
                if (gen === generacionFiltro) {
                    errorMas = true;
                    console.error('Error al cargar más publicaciones:', err);
                }
            } finally {
                if (gen === generacionFiltro) {
                    cargandoMas = false;
                    actualizarPie();
                }
            }
        }

        // silencioso = actualización en segundo plano: sin "Cargando..." y sin
        // mostrar errores (si falla, se reintenta en el próximo ciclo).
        async function cargarPublicaciones({ silencioso = false } = {}) {
            const miSeq = ++seqFeed;
            if (!silencioso && !hayTarjetas()) mostrarEstado('Cargando publicaciones...');

            try {
                // Se refresca todo lo que ya está cargado (hasta MAX_REFRESCO) para que
                // ediciones, borrados y contadores se vean en vivo también más abajo.
                const cargadas = feedEl.querySelectorAll(':scope > .post-card').length;
                const url = urlFeed({ limite: Math.min(MAX_REFRESCO, Math.max(PAGINA, cargadas)) });
                // Mis reacciones van aparte: el feed es igual para todos (caché en Redis).
                const [res, resReacc] = await Promise.all([
                    fetch(url, { cache: 'no-store' }),
                    fetch(REACCIONES_URL, { cache: 'no-store' }).catch(() => null),
                ]);
                const data = await res.json();
                let reaccionesNuevas = null;
                if (resReacc && resReacc.ok) {
                    try { reaccionesNuevas = (await resReacc.json()).reacciones || {}; } catch (_) {}
                }

                if (miSeq !== seqFeed) return; // llegó otra respuesta más nueva
                if (reaccionesNuevas !== null) misReacciones = reaccionesNuevas;

                if (!res.ok) {
                    if (!silencioso && !hayTarjetas()) {
                        mostrarEstado(data.error || 'No se pudieron cargar las publicaciones.');
                    }
                    return;
                }

                const lista = Array.isArray(data.publicaciones) ? data.publicaciones : [];

                if (lista.length === 0) {
                    firmasPosts.clear();
                    hayMas = false;
                    actualizarPie();
                    mostrarEstado(tagActivo === null
                        ? 'Todavía no hay publicaciones. ¡Sé el primero!'
                        : 'Todavía no hay publicaciones con ese tag.');
                    return;
                }

                reconciliarFeed(lista, data.hay_mas === true);
                actualizarPie();
            } catch (err) {
                console.error('Error al cargar publicaciones:', err);
                if (!silencioso && !hayTarjetas()) mostrarEstado('Error de red al cargar publicaciones.');
            }
        }

        // Mandarina = like, tomate = dislike. Una sola reacción por usuario y
        // publicación, definitiva, y ninguna sobre la publicación propia.
        function renderReacciones(post, esPropia) {
            const mia = misReacciones[post.id] || null;
            // La reacción activa queda marcada: tocarla de nuevo la quita, y tocar
            // la otra la cambia. Sobre la publicación propia no se puede reaccionar.
            const boton = (tipo, emoji, nombre, cantidad) => {
                const activa = mia === tipo;
                const bloqueado = esPropia;
                const titulo = esPropia
                    ? 'No podés reaccionar a tu propia publicación'
                    : (activa ? 'Quitar mi reacción' : (mia !== null ? `Cambiar a ${nombre}` : nombre));
                return `
                <button type="button" class="react-btn react-${tipo} ${activa ? 'is-active' : ''}"
                        data-tipo="${tipo}" data-id="${post.id}" ${bloqueado ? 'disabled' : ''}
                        title="${titulo}" aria-label="${nombre}: ${cantidad}">
                    <span class="react-emoji" aria-hidden="true">${emoji}</span>
                    <span class="react-count">${cantidad}</span>
                </button>`;
            };

            return `
                <div class="post-reactions">
                    ${boton('like', '🍊', 'Mandarina (me gusta)', Number(post.likes) || 0)}
                    ${boton('dislike', '🍅', 'Tomate (no me gusta)', Number(post.dislikes) || 0)}
                </div>`;
        }

        // ---- Animaciones de reacción ----
        // Los elementos animados viven en <body> (position: fixed), no dentro
        // de la tarjeta: así la actualización en vivo, que rehace la tarjeta
        // cuando cambian los contadores, no los corta. Cada uno se crea, se
        // anima y se elimina al terminar. Los estilos esenciales van en línea
        // para que no dependan de que el CSS esté cargado o cacheado.
        const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function crearFx(emoji, x, y) {
            const el = document.createElement('span');
            el.className = 'fx-emoji';
            el.textContent = emoji;
            el.setAttribute('aria-hidden', 'true');
            el.style.cssText = `position:fixed;left:${x}px;top:${y}px;z-index:9999;font-size:2.2rem;line-height:1;pointer-events:none;`;
            document.body.appendChild(el);
            return el;
        }

        function animarMandarina(origen) {
            if (sinMovimiento()) {
                // Versión suave: sin desplazamiento, solo aparece y se desvanece.
                const el = crearFx('🍊', origen.x, origen.y - 30);
                el.animate([{ opacity: 0, transform: 'translate(-50%, -50%)' }, { opacity: 1, offset: 0.3, transform: 'translate(-50%, -50%)' }, { opacity: 0, transform: 'translate(-50%, -50%)' }], { duration: 900 }).onfinish = () => el.remove();
                return;
            }
            // Tres mandarinas que suben girando y se desvanecen.
            [-28, 0, 28].forEach((desvio, i) => {
                const el = crearFx('🍊', origen.x, origen.y);
                const anim = el.animate([
                    { transform: 'translate(-50%, -50%) scale(0.4) rotate(0deg)', opacity: 0 },
                    { transform: `translate(calc(-50% + ${desvio * 0.5}px), calc(-50% - 40px)) scale(1.4) rotate(${desvio * 4}deg)`, opacity: 1, offset: 0.25 },
                    { transform: `translate(calc(-50% + ${desvio}px), calc(-50% - 120px)) scale(1.1) rotate(${desvio * 8}deg)`, opacity: 0 },
                ], { duration: 1200, delay: i * 110, easing: 'ease-out', fill: 'both' });
                anim.onfinish = () => el.remove();
            });
        }

        function ponerMancha(destino) {
            const mancha = document.createElement('div');
            mancha.className = 'fx-splat';
            mancha.style.cssText = `position:fixed;left:${destino.x}px;top:${destino.y}px;z-index:9998;width:150px;height:120px;pointer-events:none;`
                + 'border-radius:48% 52% 45% 55% / 55% 45% 55% 45%;'
                + 'background:radial-gradient(circle at 30% 35%,#ff6b4a 0 14%,transparent 15%),radial-gradient(circle at 70% 60%,#d62a1a 0 16%,transparent 17%),radial-gradient(circle at 55% 25%,#ff6b4a 0 9%,transparent 10%),radial-gradient(circle at 50% 50%,#c4200f 0 55%,rgba(196,32,15,.6) 70%,transparent 72%);';
            document.body.appendChild(mancha);
            const anim = mancha.animate(sinMovimiento() ? [
                { opacity: 0.9, transform: 'translate(-50%, -50%) scale(1)' },
                { opacity: 0, transform: 'translate(-50%, -50%) scale(1)' },
            ] : [
                { transform: 'translate(-50%, -50%) scale(0.2)', opacity: 1 },
                { transform: 'translate(-50%, -50%) scale(1)', opacity: 0.95, offset: 0.25 },
                { transform: 'translate(-50%, -48%) scale(1.05)', opacity: 0.85, offset: 0.7 },
                { transform: 'translate(-50%, -30%) scale(1.1)', opacity: 0 },
            ], { duration: sinMovimiento() ? 900 : 1400, easing: 'ease-out', fill: 'both' });
            anim.onfinish = () => mancha.remove();
        }

        function animarTomate(origen, destino, idPost) {
            if (sinMovimiento()) {
                // Versión suave: sin vuelo; el tomate y la mancha aparecen y se desvanecen.
                ponerMancha(destino);
                const el = crearFx('🍅', destino.x, destino.y);
                el.animate([{ opacity: 1, transform: 'translate(-50%, -50%)' }, { opacity: 0, transform: 'translate(-50%, -50%)' }], { duration: 900 }).onfinish = () => el.remove();
                return;
            }

            const dx = destino.x - origen.x;
            const dy = destino.y - origen.y;
            const tomate = crearFx('🍅', origen.x, origen.y);

            const vuelo = tomate.animate([
                { transform: 'translate(-50%, -50%) rotate(0deg) scale(1)' },
                { transform: `translate(calc(-50% + ${dx * 0.5}px), calc(-50% + ${dy * 0.5 - 90}px)) rotate(360deg) scale(1.5)`, offset: 0.5 },
                { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) rotate(720deg) scale(1.2)` },
            ], { duration: 650, easing: 'ease-in', fill: 'forwards' });

            vuelo.onfinish = () => {
                tomate.remove();
                ponerMancha(destino);

                // Sacudón de la tarjeta (si sigue en pantalla).
                const viva = document.querySelector(`.post-card[data-id="${idPost}"]`);
                if (viva) {
                    viva.animate([
                        { transform: 'translateX(0)' }, { transform: 'translateX(-6px) rotate(-0.4deg)' },
                        { transform: 'translateX(6px) rotate(0.4deg)' }, { transform: 'translateX(-3px)' },
                        { transform: 'translateX(0)' },
                    ], { duration: 350 });
                }
            };
        }

        function animarReaccion(tipo, origen, card) {
            try {
                if (tipo === 'like') {
                    animarMandarina(origen);
                } else {
                    const r = card.getBoundingClientRect();
                    animarTomate(origen, { x: r.left + r.width / 2, y: r.top + r.height / 2 }, card.dataset.id);
                }
            } catch (err) {
                console.error('Error en la animación:', err); // la animación nunca debe romper la reacción
            }
        }

        function tarjetaViva(id) {
            return document.querySelector(`.post-card[data-id="${id}"]`);
        }

        // La animación y el cambio de contadores arrancan en el clic, sin
        // esperar al servidor (que puede tardar). Tocar la reacción activa la
        // quita; tocar la otra la cambia. Si el servidor rechaza el cambio, se
        // deshace todo y se avisa.
        async function reaccionar(btn) {
            const card = btn.closest('.post-card');
            const barra = card.querySelector('.post-reactions');
            const r = btn.getBoundingClientRect();
            const origen = { x: r.left + r.width / 2, y: r.top + r.height / 2 };
            const tipo = btn.dataset.tipo;
            const id = Number(btn.dataset.id);

            const htmlPrevio = barra.innerHTML;
            const reaccionPrevia = misReacciones[id] || null;
            const quitar = reaccionPrevia === tipo; // volvió a tocar la suya: se arrepiente

            // --- Cambio inmediato en pantalla ---
            const contador = (t) => card.querySelector(`.react-${t} .react-count`);
            if (reaccionPrevia) contador(reaccionPrevia).textContent = Math.max(0, Number(contador(reaccionPrevia).textContent) - 1);
            if (!quitar) contador(tipo).textContent = Number(contador(tipo).textContent) + 1;
            card.querySelectorAll('.react-btn').forEach((b) => {
                b.classList.toggle('is-active', !quitar && b.dataset.tipo === tipo);
                b.disabled = true; // sin doble clic mientras responde el servidor
            });
            if (quitar) delete misReacciones[id]; else misReacciones[id] = tipo;
            if (!quitar) animarReaccion(tipo, origen, card); // quitar no se anima

            // --- Confirmación del servidor ---
            let error = null;
            try {
                const res = quitar
                    ? await fetch(`${REACCIONES_URL}?publicacion_id=${id}`, { method: 'DELETE' })
                    : await fetch(REACCIONES_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ publicacion_id: id, tipo }),
                    });
                if (res.status !== (quitar ? 204 : 201)) {
                    const data = await res.json().catch(() => ({}));
                    error = data.error || 'No se pudo registrar la reacción.';
                }
            } catch (err) {
                console.error('Error al reaccionar:', err);
                error = 'Error de red al reaccionar.';
            }

            const viva = tarjetaViva(id);
            if (error !== null) {
                if (reaccionPrevia) misReacciones[id] = reaccionPrevia; else delete misReacciones[id];
                if (viva) viva.querySelector('.post-reactions').innerHTML = htmlPrevio;
                alert(error);
            } else if (viva) {
                viva.querySelectorAll('.react-btn').forEach((b) => { b.disabled = false; });
            }

            // Se sincronizan los contadores reales con el servidor.
            cargarPublicaciones({ silencioso: true });
        }

        function renderPost(post) {
            const esPropia = Number(post.user_id) === currentUserId;
            const sensible = post.contenido_sensible === true;
            const fecha = formatearFecha(post.fecha_creacion);

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
                    ${(esPropia || esAdmin) ? `
                        <div class="post-card-actions">
                            ${esPropia ? `<button class="btn-link edit-btn" data-id="${post.id}" data-titulo="${escapeHtml(post.titulo)}" data-contenido="${escapeHtml(post.contenido).replace(/\n/g, '&#10;')}" data-tags="${escapeHtml((post.tags || []).join(' '))}">Editar</button>` : ''}
                            <button class="btn-link delete-btn" data-id="${post.id}" data-autor="${escapeHtml(post.nickname)}" data-ajena="${esPropia ? '0' : '1'}">Eliminar</button>
                        </div>
                    ` : ''}
                    ${renderReacciones(post, esPropia)}
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
            const fecha = formatearFecha(r.fecha_creacion);

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
                    ${(esPropia || esAdmin) ? `<button type="button" class="btn-link delete-reply-btn" data-id="${r.id}" data-autor="${escapeHtml(r.nickname)}" data-ajena="${esPropia ? '0' : '1'}">Eliminar</button>` : ''}
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
                cargarTagsGenerales();
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

        let enviandoPost = false; // evita que un doble clic / doble Enter mande la publicación dos veces

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (enviandoPost) return;
            enviandoPost = true;

            const textoBoton = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';

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
                cargarTagsGenerales();
            } catch (err) {
                console.error('Error al guardar la publicación:', err);
                alert('Error de red al guardar la publicación.');
            } finally {
                enviandoPost = false;
                submitBtn.disabled = false;
                if (submitBtn.textContent === 'Enviando...') submitBtn.textContent = textoBoton;
            }
        });

        cancelBtn.addEventListener('click', resetForm);

        feedEl.addEventListener('click', async (e) => {
            const botonReaccion = e.target.closest('.react-btn');
            if (botonReaccion && !botonReaccion.disabled) {
                reaccionar(botonReaccion);
                return;
            }

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
                const msgR = e.target.dataset.ajena === '1'
                    ? `Como administrador, vas a eliminar la respuesta de ${e.target.dataset.autor}. ¿Continuar?`
                    : '¿Seguro que querés eliminar esta respuesta?';
                if (!confirm(msgR)) return;
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
                const msgP = e.target.dataset.ajena === '1'
                    ? `Como administrador, vas a eliminar la publicación de ${e.target.dataset.autor}. ¿Continuar?`
                    : '¿Seguro que querés eliminar esta publicación?';
                if (!confirm(msgP)) return;

                try {
                    const res = await fetch(`${API_URL}/${id}`, { method: 'DELETE' });

                    if (res.status !== 204) {
                        const data = await res.json();
                        alert(data.error || 'No se pudo eliminar la publicación.');
                        return;
                    }

                    cargarPublicaciones();
                    cargarMisTags();
                    cargarTagsGenerales();
                } catch (err) {
                    console.error('Error al eliminar la publicación:', err);
                    alert('Error de red al eliminar la publicación.');
                }
            }
        });


        // ---- Menús desplegables del header (usuario y notificaciones) ----
        const dropdowns = [
            { btn: document.getElementById('user-menu-btn'), panel: document.getElementById('user-menu-panel') },
            { btn: document.getElementById('notif-btn'), panel: document.getElementById('notif-panel') },
        ];

        function cerrarDropdowns(salvo = null) {
            dropdowns.forEach((d) => {
                if (d === salvo) return;
                d.panel.hidden = true;
                d.btn.setAttribute('aria-expanded', 'false');
            });
        }

        dropdowns.forEach((d) => {
            d.btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const abrir = d.panel.hidden;
                cerrarDropdowns(d);
                d.panel.hidden = !abrir;
                d.btn.setAttribute('aria-expanded', String(abrir));
            });
            d.panel.addEventListener('click', (e) => {
                e.stopPropagation();
                if (e.target.closest('.dropdown-item')) cerrarDropdowns();
            });
        });

        // Clic afuera o Escape: se cierran.
        document.addEventListener('click', () => cerrarDropdowns());
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') cerrarDropdowns();
        });

        // ---- Notificaciones: alguien respondió a una publicación tuya ----
        const notifBadge = document.getElementById('notif-badge');
        const notifList = document.getElementById('notif-list');
        const notifEmpty = document.getElementById('notif-empty');
        const notifLeerTodas = document.getElementById('notif-leer-todas');
        const tituloOriginal = document.title;
        let notifItems = [];
        let notifNoLeidas = 0;
        let notifVistas = null; // ids ya conocidos: la primera carga no "avisa"
        let seqNotif = 0;

        function fechaRelativa(valor) {
            const fecha = leerFechaServidor(valor);
            if (!fecha) return '';
            const seg = Math.max(0, Math.round((Date.now() - fecha.getTime()) / 1000));
            if (seg < 60) return 'recién';
            if (seg < 3600) return `hace ${Math.floor(seg / 60)} min`;
            if (seg < 86400) return `hace ${Math.floor(seg / 3600)} h`;
            return formatearFecha(valor).split(' ')[0];
        }

        function renderNotificaciones() {
            notifBadge.hidden = notifNoLeidas === 0;
            notifBadge.textContent = notifNoLeidas > 99 ? '99+' : String(notifNoLeidas);
            notifLeerTodas.hidden = notifNoLeidas === 0;
            notifEmpty.hidden = notifItems.length > 0;
            document.title = notifNoLeidas > 0 ? `(${notifNoLeidas}) ${tituloOriginal}` : tituloOriginal;

            notifList.innerHTML = notifItems.map((n) => `
                <li>
                    <button type="button" class="notif-item ${n.leida ? '' : 'no-leida'}" data-id="${Number(n.id)}" data-post="${Number(n.publicacion_id)}" data-resp="${Number(n.respuesta_id)}">
                        <span class="notif-texto"><strong>${escapeHtml(n.actor_nickname)}</strong> respondió en <em>${escapeHtml(n.publicacion_titulo)}</em></span>
                        <span class="notif-extracto">${escapeHtml(n.respuesta_extracto)}</span>
                        <span class="notif-fecha">${fechaRelativa(n.fecha_creacion)}</span>
                    </button>
                </li>
            `).join('');
        }

        async function cargarNotificaciones() {
            try {
                const miSeq = ++seqNotif;
                const res = await fetch(NOTIF_URL, { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (miSeq !== seqNotif) return; // llegó otra respuesta más nueva

                const ids = new Set(data.items.map((n) => Number(n.id)));
                const llegaron = notifVistas === null
                    ? []
                    : data.items.filter((n) => !n.leida && !notifVistas.has(Number(n.id)));
                notifVistas = ids;

                notifItems = data.items;
                notifNoLeidas = Number(data.no_leidas) || 0;
                renderNotificaciones();

                if (llegaron.length > 0) {
                    notifBadge.classList.remove('notif-pulso');
                    void notifBadge.offsetWidth; // reinicia la animación
                    notifBadge.classList.add('notif-pulso');
                }
            } catch (err) {
                console.error('Error al cargar las notificaciones:', err);
            }
        }

        async function marcarNotificaciones(id = null) {
            try {
                await fetch(NOTIF_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(id === null ? {} : { id }),
                });
            } catch (err) {
                console.error('Error al marcar notificaciones:', err);
            }
        }

        notifLeerTodas.addEventListener('click', async () => {
            notifItems = notifItems.map((n) => ({ ...n, leida: true }));
            notifNoLeidas = 0;
            renderNotificaciones();
            await marcarNotificaciones();
        });

        // Ir a la publicación: se limpia el filtro de tag si hace falta, se
        // abre el hilo de respuestas y se resalta la respuesta nueva.
        async function irAPublicacion(postId, respId) {
            if (tagActivo !== null) {
                seleccionarTag(null);
                await cargarPublicaciones({ silencioso: true });
            }

            const buscar = () => feedEl.querySelector(`:scope > .post-card[data-id="${postId}"]`);
            let card = buscar();

            // Si todavía no está cargada, se baja de a tandas hasta alcanzarla (los
            // ids bajan al ir hacia abajo: si ya pasamos su id, no existe más).
            for (let i = 0; i < 40 && !card && hayMas && (idUltimaTarjeta() ?? 0) > postId && !errorMas; i++) {
                if (cargandoMas) {
                    await new Promise((r) => setTimeout(r, 200));
                } else {
                    await cargarMas(50);
                }
                card = buscar();
            }

            if (!card) {
                alert('Esa publicación ya no está disponible.');
                return;
            }

            const seccion = card.querySelector('.replies');
            if (seccion.hidden) seccion.hidden = false;
            await cargarRespuestas(card, { silencioso: true });

            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const respuesta = card.querySelector(`.reply[data-id="${respId}"]`);
            const objetivo = respuesta || card;
            objetivo.classList.remove('post-nuevo');
            void objetivo.offsetWidth;
            objetivo.classList.add('post-nuevo');
        }

        notifList.addEventListener('click', async (e) => {
            const item = e.target.closest('.notif-item');
            if (!item) return;

            const id = Number(item.dataset.id);
            const n = notifItems.find((x) => Number(x.id) === id);
            if (n && !n.leida) {
                n.leida = true;
                notifNoLeidas = Math.max(0, notifNoLeidas - 1);
                renderNotificaciones();
                marcarNotificaciones(id);
            }

            cerrarDropdowns();
            await irAPublicacion(Number(item.dataset.post), Number(item.dataset.resp));
        });

        // ---- Tiempo real ----
        // Cada POLL_MS se pregunta por la "versión" del contenido (una
        // consulta mínima a Redis). Solo si cambió se vuelve a pedir el feed y
        // los tags. Con la pestaña en segundo plano no se consulta, y al
        // volver se consulta enseguida.
        let versionRT = null;
        let consultando = false;

        // "Mandrainos activos": llega junto con cada sondeo de novedades.
        const activosNum = document.getElementById('activos-num');
        function actualizarActivos(n) {
            if (!Number.isInteger(n)) return; // sin dato: se deja el último valor
            if (activosNum.textContent === String(n)) return;
            activosNum.textContent = String(n);
            activosNum.classList.remove('activos-cambio');
            void activosNum.offsetWidth; // reinicia la animación
            activosNum.classList.add('activos-cambio');
        }

        async function leerVersion() {
            const res = await fetch(NOVEDADES_URL, { cache: 'no-store' });
            if (res.status === 401) {
                window.location.href = 'index.html'; // la sesión venció
                return null;
            }
            if (!res.ok) return null;
            const data = await res.json();
            actualizarActivos(data.activos);
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
                    await Promise.all([cargarPublicaciones({ silencioso: true }), cargarMisTags(), cargarTagsGenerales(), tagsModal.open ? cargarTagsTodos() : null, cargarNotificaciones()]);
                }
            } catch (err) {
                // Sin red o servidor caído: se reintenta en el próximo ciclo.
            } finally {
                consultando = false;
            }
        }

        renderTagsBar();
        renderTagsGenerales();

        // ---- Admin: limpiar publicaciones duplicadas ----
        const btnDuplicados = document.getElementById('btn-duplicados');
        if (btnDuplicados) {
            btnDuplicados.addEventListener('click', async () => {
                btnDuplicados.disabled = true;
                try {
                    const res = await fetch(DUPLICADAS_URL, { cache: 'no-store' });
                    const info = await res.json();
                    if (!res.ok) { alert(info.error || 'No se pudo buscar duplicados.'); return; }

                    if (info.cantidad === 0) { alert('No hay publicaciones duplicadas. 👌'); return; }

                    const detalle = info.respuestas_afectadas > 0
                        ? ` (con ${info.respuestas_afectadas} respuestas, que también se borrarán)`
                        : '';
                    if (!confirm(`Se encontraron ${info.cantidad} publicaciones duplicadas${detalle}.\n\nSe conserva la más antigua de cada grupo. ¿Eliminar las copias?`)) return;

                    const del = await fetch(DUPLICADAS_URL, { method: 'DELETE' });
                    const r = await del.json();
                    if (!del.ok) { alert(r.error || 'No se pudieron eliminar.'); return; }

                    alert(`Listo: se eliminaron ${r.eliminadas} publicaciones duplicadas.`);
                    cargarPublicaciones();
                    cargarMisTags();
                    cargarTagsGenerales();
                } catch (err) {
                    console.error('Error al limpiar duplicados:', err);
                    alert('Error de red al limpiar duplicados.');
                } finally {
                    btnDuplicados.disabled = false;
                }
            });
        }

        // ---- Botón "volver arriba": aparece al bajar y te lleva al tope con animación ----
        // El desplazamiento se anima por JavaScript (con aceleración y frenado
        // suaves) para que se vea igual en todos los navegadores, aunque el
        // sistema tenga desactivado el desplazamiento suave.
        const btnSubir = document.getElementById('btn-subir');
        const UMBRAL_SUBIR = 400; // px de scroll a partir de los cuales aparece
        let subiendo = false;
        let cancelarSubida = null;

        function actualizarBtnSubir() {
            const visible = window.scrollY >= UMBRAL_SUBIR;
            btnSubir.classList.toggle('visible', visible);
            btnSubir.setAttribute('aria-hidden', visible ? 'false' : 'true');
            btnSubir.tabIndex = visible ? 0 : -1;
        }

        function subirAlTope() {
            if (subiendo) return;
            const inicio = window.scrollY;
            if (inicio <= 0) return;

            subiendo = true;
            btnSubir.classList.add('subiendo');

            // Más distancia, más tiempo (entre 0,6 y 1,2 s).
            const duracion = Math.min(1200, Math.max(600, inicio * 0.3));
            const t0 = performance.now();
            let cancelado = false;
            let frame = 0;

            const terminar = () => {
                cancelAnimationFrame(frame);
                ['wheel', 'touchstart', 'keydown'].forEach((ev) => window.removeEventListener(ev, cancelarSubida));
                cancelarSubida = null;
                subiendo = false;
                btnSubir.classList.remove('subiendo');
            };
            // Si la persona mueve la página mientras sube, se corta para no pelear con ella.
            cancelarSubida = () => { cancelado = true; terminar(); };
            ['wheel', 'touchstart', 'keydown'].forEach((ev) => window.addEventListener(ev, cancelarSubida, { passive: true }));

            const paso = (ahora) => {
                if (cancelado) return;
                const t = Math.min(1, (ahora - t0) / duracion);
                const suave = t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; // easeInOutCubic
                window.scrollTo({ top: Math.round(inicio * (1 - suave)), behavior: 'instant' });
                if (t < 1) frame = requestAnimationFrame(paso); else terminar();
            };
            frame = requestAnimationFrame(paso);
        }

        // ---- Barra superior: queda fija al bajar y se achica ----
        // Histéresis (entra pasados 80 px, sale antes de 20 px) para que no
        // parpadee en el límite. Al achicarse, la diferencia de altura se
        // devuelve como margen inferior: el alto total del flujo no cambia y
        // el contenido no da un salto.
        const barraSuperior = document.querySelector('.panel-header');
        const SCROLL_COMPACTAR = 80;
        const SCROLL_EXPANDIR = 20;

        function ponerCompacta(compacta) {
            const antes = barraSuperior.offsetHeight;
            barraSuperior.classList.toggle('compacto', compacta);
            barraSuperior.style.marginBottom = compacta
                ? `${Math.max(0, antes - barraSuperior.offsetHeight)}px`
                : '';
        }

        function actualizarBarraSuperior() {
            const compacta = barraSuperior.classList.contains('compacto');
            if (!compacta && window.scrollY > SCROLL_COMPACTAR) ponerCompacta(true);
            else if (compacta && window.scrollY < SCROLL_EXPANDIR) ponerCompacta(false);
        }

        window.addEventListener('scroll', actualizarBarraSuperior, { passive: true });
        let anchoVentana = window.innerWidth;
        window.addEventListener('resize', () => {
            // Solo si cambia el ancho (en el celular la barra del navegador dispara
            // resize al bajar y no cambia nada). Con otro ancho cambian las alturas:
            // se vuelve al estado normal y se recalcula.
            if (window.innerWidth === anchoVentana) return;
            anchoVentana = window.innerWidth;
            barraSuperior.classList.remove('compacto');
            barraSuperior.style.marginBottom = '';
            actualizarBarraSuperior();
        });
        actualizarBarraSuperior();

        // ---- Mascota: cobayo que pasea por la barra superior ----
        // Camina de un lado a otro, se detiene a ratos y salta al tocarlo
        // (clic, toque en el celular o al pasarle el mouse por encima).
        (function iniciarCobayo() {
            const pista = document.getElementById('cobayo-pista');
            const cobayo = document.getElementById('cobayo');
            const cuerpo = document.getElementById('cobayo-cuerpo');
            const globo = document.getElementById('cobayo-globo');
            if (!pista || !cobayo || !cuerpo) return;

            const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const VELOCIDAD = 38;            // px por segundo
            const MARGEN = 6;                // separación con los bordes de la barra
            let x = 40 + Math.random() * 200;
            let dir = Math.random() < 0.5 ? 1 : -1;
            let estado = 'caminar';          // caminar | quieto | saltando
            let hasta = performance.now() + 2000 + Math.random() * 3000;
            let ultimo = performance.now();
            let globoTimer = null;
            let fase = 0;

            function ancho() { return pista.clientWidth; }
            function limite() { return Math.max(MARGEN, ancho() - cobayo.offsetWidth - MARGEN); }

            function pintar() {
                cobayo.style.transform = `translateX(${x.toFixed(1)}px)`;
                cuerpo.style.setProperty('--dir', dir);
            }

            function decirAlgo(texto) {
                if (!globo) return;
                globo.textContent = texto;
                globo.classList.add('visible');
                clearTimeout(globoTimer);
                globoTimer = setTimeout(() => globo.classList.remove('visible'), 900);
            }

            function saltar() {
                if (estado === 'saltando') return;
                const previo = estado;
                estado = 'saltando';
                const alto = sinMovimiento ? 6 : 22;
                const anim = cuerpo.animate([
                    { transform: `scaleX(${dir}) translateY(0) scale(1, 1)`, offset: 0 },
                    { transform: `scaleX(${dir}) translateY(0) scale(1.12, 0.82)`, offset: 0.15 },
                    { transform: `scaleX(${dir}) translateY(-${alto}px) scale(0.92, 1.1)`, offset: 0.5 },
                    { transform: `scaleX(${dir}) translateY(0) scale(1.12, 0.82)`, offset: 0.85 },
                    { transform: `scaleX(${dir}) translateY(0) scale(1, 1)`, offset: 1 }
                ], { duration: 520, easing: 'ease-in-out' });
                decirAlgo(['¡wiik!', '¡wiik wiik!', '¡Mandraina!'][Math.floor(Math.random() * 3)]);
                anim.onfinish = anim.oncancel = () => {
                    estado = previo === 'saltando' ? 'quieto' : previo;
                    hasta = performance.now() + 600 + Math.random() * 1200;
                };
            }

            cobayo.addEventListener('click', saltar);
            // Pasar el mouse por encima también lo asusta (con pausa para que no se vuelva molesto).
            let ultimoHover = 0;
            cobayo.addEventListener('pointerenter', (e) => {
                if (e.pointerType !== 'mouse') return;
                const ahora = performance.now();
                if (ahora - ultimoHover < 1500) return;
                ultimoHover = ahora;
                saltar();
            });

            function paso(ahora) {
                const dt = Math.min(0.05, (ahora - ultimo) / 1000);
                ultimo = ahora;

                if (!sinMovimiento && estado !== 'saltando') {
                    if (estado === 'caminar') {
                        x += dir * VELOCIDAD * dt;
                        const max = limite();
                        if (x <= MARGEN) { x = MARGEN; dir = 1; }
                        else if (x >= max) { x = max; dir = -1; }
                        // Balanceo al caminar
                        fase += dt * 11;
                        cuerpo.style.transform = `scaleX(${dir}) translateY(${(-Math.abs(Math.sin(fase)) * 2.2).toFixed(2)}px) rotate(${(Math.sin(fase) * 5).toFixed(1)}deg)`;
                        if (ahora > hasta) {
                            estado = 'quieto';
                            hasta = ahora + 1200 + Math.random() * 2500;
                            cuerpo.style.transform = `scaleX(${dir})`;
                        }
                    } else if (ahora > hasta) {
                        estado = 'caminar';
                        if (Math.random() < 0.4) dir = -dir;
                        hasta = ahora + 3000 + Math.random() * 5000;
                    }
                } else if (estado !== 'saltando') {
                    cuerpo.style.transform = `scaleX(${dir})`;
                }

                x = Math.min(Math.max(MARGEN, x), limite());
                pintar();
                requestAnimationFrame(paso);
            }

            window.addEventListener('resize', () => { x = Math.min(x, limite()); pintar(); });
            pintar();
            requestAnimationFrame((t) => { ultimo = t; paso(t); });
        })();

        window.addEventListener('scroll', actualizarBtnSubir, { passive: true });
        btnSubir.addEventListener('click', subirAlTope);
        actualizarBtnSubir();

        // La versión se lee ANTES (en paralelo) de cargar el feed: si algo cambia
        // mientras carga, el primer sondeo lo detecta.
        leerVersion().then((v) => { if (versionRT === null) versionRT = v; }).catch(() => {});
        cargarPublicaciones();
        cargarMisTags();
        cargarTagsGenerales();
        cargarNotificaciones();

        setInterval(buscarNovedades, POLL_MS);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) buscarNovedades();
        });
    </script>
</body>
</html>
