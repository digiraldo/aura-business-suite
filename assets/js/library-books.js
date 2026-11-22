/**
 * library-books.js — Catálogo de Libros (Fase 2)
 * Aura Business Suite
 *
 * Maneja: listado DataTables, modales crear/editar/detalle,
 * Media Uploader para portada, validación ISBN, filtros.
 */
/* global auraLibraryBooks, jQuery */
(function ($) {
    'use strict';

    function __(s) { return (window.wp && wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__(s, 'aura-suite') : s; }

    if (typeof auraLibraryBooks === 'undefined') return;

    var cfg         = auraLibraryBooks;
    var currentPage = 1;
    var perPage     = 20;
    var totalPages  = 1;
    var _booksTable = null;
    var _booksMap   = {};

    // ──────────────────────────────────────────────────────────────
    // INIT
    // ──────────────────────────────────────────────────────────────
    $(document).ready(function () {
        loadBooks();
        bindEvents();
    });

    // ──────────────────────────────────────────────────────────────
    // EVENTS
    // ──────────────────────────────────────────────────────────────
    function bindEvents() {
        // Nuevo libro
        $(document).on('click', '#aura-lib-btn-new-book', function () {
            openBookModal(0);
        });

        // Copiar prompt IA
        $(document).on('click', '#aura-lib-copy-prompt-btn', function () {
            var $btn = $(this);
            var text = document.getElementById('aura-lib-ai-prompt-text').value;
            var copied = false;

            // 1. Intentar execCommand de forma síncrona (conserva el user-gesture context)
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;top:50%;left:50%;width:2px;height:2px;opacity:0.01;border:none;padding:0;margin:0;';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try { copied = document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);

            if (copied) {
                showCopied($btn);
                return;
            }

            // 2. Fallback: Clipboard API async
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    showCopied($btn);
                }).catch(function () {
                    window.prompt(__('Copia el texto con Ctrl+C y pégalo en tu IA:', 'aura-suite'), text);
                });
            } else {
                window.prompt(__('Copia el texto con Ctrl+C y pégalo en tu IA:', 'aura-suite'), text);
            }
        });

        function showCopied($btn) {
            var origHtml = $btn.html();
            $btn.addClass('copied').html('<span class="dashicons dashicons-yes"></span> ' + escHtml(__('¡Copiado!', 'aura-suite')));
            setTimeout(function () {
                $btn.removeClass('copied').html(origHtml);
            }, 2500);
        }

        // Filtros
        $(document).on('click', '#aura-lib-filter-apply', function () {
            currentPage = 1;
            loadBooks();
        });
        $(document).on('click', '#aura-lib-filter-clear', function () {
            $('#aura-lib-search').val('');
            $('#aura-lib-filter-dewey, #aura-lib-filter-category, #aura-lib-filter-status, #aura-lib-filter-area').val('');
            currentPage = 1;
            loadBooks();
        });
        $(document).on('keydown', '#aura-lib-search', function (e) {
            if (e.key === 'Enter') { currentPage = 1; loadBooks(); }
        });

        // Paginación
        $(document).on('click', '#aura-lib-pagination .aura-lib-page-btn', function () {
            var p = parseInt($(this).data('page'), 10);
            if (p >= 1 && p <= totalPages) { currentPage = p; loadBooks(); }
        });

        // Acciones de tabla (delegadas)
        $(document).on('click', '.aura-lib-btn-detail', function () {
            openDetailModal($(this).data('id'));
        });
        $(document).on('click', '.aura-lib-btn-edit', function () {
            openBookModal($(this).data('id'));
        });
        $(document).on('click', '.aura-lib-btn-delete', function () {
            deleteBook($(this).data('id'));
        });

        // F4.4 — Botón Reservar
        $(document).on('click', '.aura-lib-btn-reserve', function () {
            openReserveModal(parseInt($(this).data('id'), 10), $(this).data('title'));
        });
        $(document).on('submit', '#aura-lib-reserve-form', function (e) {
            e.preventDefault();
            saveReservation();
        });

        // Cerrar modales (protección anti-cierre accidental al arrastrar selección + detener escáneres)
        var _libBackdropDown = false;
        $(document).on('mousedown', '.aura-lib-modal', function (e) {
            _libBackdropDown = (e.target === this);
        });
        $(document).on('click', '.aura-lib-modal-close', function () {
            closeAllModals();
        });
        $(document).on('click', '.aura-lib-modal', function (e) {
            if (_libBackdropDown && e.target === this) {
                closeAllModals();
            }
            _libBackdropDown = false;
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeAllModals();
        });

        // Guardar formulario
        $(document).on('submit', '#aura-lib-book-form', function (e) {
            e.preventDefault();
            saveBook();
        });

        // ── Wizard: navegación Next / Back ──────────────────────────
        $(document).on('click', '.aura-lib-btn-next', function () {
            var fromStep = parseInt($(this).data('from'), 10);
            if (validateWizardStep(fromStep)) {
                goToWizardStep(fromStep + 1);
            }
        });
        $(document).on('click', '.aura-lib-btn-back', function () {
            var toStep = parseInt($(this).data('to'), 10);
            goToWizardStep(toStep);
        });

        // ── Validación en tiempo real ────────────────────────────────
        $(document).on('input', '#aura-lib-f-title', function () {
            updateCharCounter($(this), '#aura-lib-f-title', 255);
            validateRequired($(this));
        });
        $(document).on('input', '#aura-lib-f-author', function () {
            validateRequired($(this));
        });
        $(document).on('input blur', '#aura-lib-f-isbn', function () {
            validateIsbnField($(this).val());
        });
        $(document).on('input', '#aura-lib-f-description', function () {
            var len = $(this).val().length;
            var $c  = $('#aura-lib-desc-counter');
            $c.text(len + ' / 2000');
            $c.toggleClass('warn', len > 1600).toggleClass('over', len > 2000);
        });

        // ── Dewey pills ──────────────────────────────────────────────
        $(document).on('click', '.aura-lib-dewey-pill', function () {
            var prefix = $(this).data('prefix');
            var title  = $(this).attr('title');
            $('.aura-lib-dewey-pill').removeClass('active');
            $(this).addClass('active');
            var current = $.trim($('#aura-lib-f-dewey').val());
            if (!current) {
                $('#aura-lib-f-dewey').val(prefix + '');
            }
            $('#aura-lib-dewey-label').text(title || '');
        });
        $(document).on('input', '#aura-lib-f-dewey', function () {
            var v      = $.trim($(this).val());
            var num    = parseFloat(v);
            var labels = {
                0: __('000 — Informática y Generalidades', 'aura-suite'), 1: __('100 — Filosofía y Psicología', 'aura-suite'),
                2: __('200 — Religión', 'aura-suite'), 3: __('300 — Ciencias Sociales', 'aura-suite'), 4: __('400 — Lengua y Lingüística', 'aura-suite'),
                5: __('500 — Ciencias Puras', 'aura-suite'), 6: __('600 — Tecnología Aplicada', 'aura-suite'), 7: __('700 — Artes y Recreación', 'aura-suite'),
                8: __('800 — Literatura', 'aura-suite'), 9: __('900 — Historia y Geografía', 'aura-suite')
            };
            if (!isNaN(num) && num >= 0 && num < 1000) {
                var cls = Math.floor(num / 100);
                var lbl = labels[cls] || '';
                $('#aura-lib-dewey-label').text(lbl);
                $('.aura-lib-dewey-pill').removeClass('active');
                $('.aura-lib-dewey-pill[data-prefix="' + (cls * 100) + '"]').addClass('active');
            } else {
                $('#aura-lib-dewey-label').text('');
                $('.aura-lib-dewey-pill').removeClass('active');
            }
        });

        // ── Stepper de ejemplares ─────────────────────────────────
        $(document).on('click', '#aura-lib-copies-minus', function () {
            var $i = $('#aura-lib-f-copies');
            var v  = Math.max(1, parseInt($i.val(), 10) - 1);
            $i.val(v);
        });
        $(document).on('click', '#aura-lib-copies-plus', function () {
            var $i = $('#aura-lib-f-copies');
            var v  = Math.min(9999, parseInt($i.val(), 10) + 1);
            $i.val(v);
        });

        // ── Status hint ──────────────────────────────────────────
        $(document).on('change', '#aura-lib-f-status', function () {
            var hints = {
                available      : '✅ ' + __('Los lectores podrán solicitarlo en préstamo.', 'aura-suite'),
                unavailable    : '🔴 ' + __('No aparecerá disponible para nuevos préstamos.', 'aura-suite'),
                reference_only : '📖 ' + __('Solo consulta en sala; no se puede prestar.', 'aura-suite'),
                lost           : '⚠️ ' + __('Marcado como perdido; no disponible.', 'aura-suite'),
                withdrawn      : '🗄️ ' + __('Retirado del catálogo activo.', 'aura-suite')
            };
            $('#aura-lib-status-hint').text(hints[$(this).val()] || '');
        });

        // ── Tag chips input ──────────────────────────────────────
        $(document).on('keydown input', '#aura-lib-tags-text', function (e) {
            if (e.type === 'keydown' && (e.key === 'Enter' || e.key === ',')) {
                e.preventDefault();
                addTag($.trim($(this).val().replace(/,/g, '')));
                $(this).val('');
            }
        });
        $(document).on('click', '#aura-lib-tags-container', function () {
            $('#aura-lib-tags-text').focus();
        });
        $(document).on('click', '.aura-lib-tag-remove', function () {
            $(this).closest('.aura-lib-tag-chip').remove();
            syncTagsHidden();
        });

        // ── Dropzone drag & drop ──────────────────────────────────
        $(document).on('dragover dragenter', '#aura-lib-dropzone', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        $(document).on('dragleave drop', '#aura-lib-dropzone', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            if (e.type === 'drop') {
                var files = e.originalEvent.dataTransfer.files;
                if (files && files.length) handleDroppedFile(files[0]);
            }
        });

        // Media uploader (portada)
        $(document).on('click', '#aura-lib-btn-select-cover', function (e) {
            e.preventDefault();
            openMediaUploader();
        });
        $(document).on('click', '#aura-lib-btn-remove-cover', function () {
            removeCover();
        });

        // Validación ISBN en tiempo real (legacy handler kept)
        $(document).on('blur', '#aura-lib-f-isbn', function () {
            validateIsbnField($(this).val());
        });

        // Búsqueda inteligente por ISBN (barra superior e inline en Paso 1)
        $(document).on('click', '#aura-lib-btn-lookup-isbn', function () {
            lookupIsbn($('#aura-lib-quick-isbn').val());
        });
        $(document).on('click', '#aura-lib-btn-lookup-isbn-inline', function () {
            lookupIsbn($('#aura-lib-f-isbn').val());
        });
        $(document).on('keydown', '#aura-lib-quick-isbn', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupIsbn($(this).val());
            }
        });

        // Escáner de código de barras por cámara
        $(document).on('click', '#aura-lib-btn-scan-barcode', function () {
            startBarcodeScanner();
        });
        $(document).on('click', '#aura-lib-btn-close-scanner', function () {
            stopBarcodeScanner();
        });
        $(document).on('change', '#aura-lib-camera-select', function () {
            var camId = $(this).val();
            if (camId) switchScannerCamera(camId);
        });

        // Escáner remoto desde celular (QR)
        $(document).on('click', '#aura-lib-btn-remote-scan', function () {
            startRemoteScan();
        });
        $(document).on('click', '#aura-lib-btn-close-remote-qr', function () {
            stopRemoteScan();
        });

        // Expandir / Colapsar fila hija con información completa del libro (+ / -)
        $(document).on('click', '#aura-lib-books-table .aura-lib-row-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $tr  = $btn.closest('tr');
            var id   = parseInt($btn.data('id'), 10);
            var book = _booksMap[id];
            if (!book) return;

            var isExpanded = $btn.hasClass('is-active');
            if (_booksTable && typeof _booksTable.row === 'function') {
                var dtRow = _booksTable.row($tr);
                if (dtRow.child.isShown()) {
                    dtRow.child.hide();
                    $tr.removeClass('shown is-expanded');
                    $btn.removeClass('is-active').attr('aria-expanded', 'false')
                        .html('<span class="dashicons dashicons-plus-alt2"></span>');
                } else {
                    dtRow.child(renderBookChildCard(book), 'aura-dt-child-row aura-child-row is-open').show();
                    $tr.addClass('shown is-expanded');
                    $btn.addClass('is-active').attr('aria-expanded', 'true')
                        .html('<span class="dashicons dashicons-minus"></span>');
                }
            } else {
                var $next = $tr.next('tr.aura-child-row');
                if (isExpanded && $next.length) {
                    $next.remove();
                    $tr.removeClass('shown is-expanded');
                    $btn.removeClass('is-active').attr('aria-expanded', 'false')
                        .html('<span class="dashicons dashicons-plus-alt2"></span>');
                } else {
                    if ($next.length) $next.remove();
                    $tr.after('<tr class="aura-child-row is-open"><td colspan="10">' + renderBookChildCard(book) + '</td></tr>');
                    $tr.addClass('shown is-expanded');
                    $btn.addClass('is-active').attr('aria-expanded', 'true')
                        .html('<span class="dashicons dashicons-minus"></span>');
                }
            }
        });

        // Indicador visual para campos manuales de Clasificación
        $(document).on('input change', '.aura-lib-manual-input', function () {
            syncManualFieldState($(this));
        });
    }

    // ──────────────────────────────────────────────────────────────
    // CARGA DE LIBROS (AJAX)
    // ──────────────────────────────────────────────────────────────
    function loadBooks() {
        if (_booksTable) {
            try { _booksTable.destroy(); } catch (e) {}
            _booksTable = null;
        }
        var tbody = $('#aura-lib-books-table tbody');
        tbody.html('<tr><td colspan="10" style="text-align:center;padding:24px;">' +
            '<span class="spinner is-active" style="float:none;"></span> ' +
            escHtml(cfg.txt.loading) + '</td></tr>');

        $.post(cfg.ajaxurl, {
            action    : 'aura_library_books_get_list',
            nonce     : cfg.nonce,
            _t        : Date.now(),
            page      : currentPage,
            per_page  : perPage,
            search    : $('#aura-lib-search').val(),
            dewey     : ($('#aura-lib-filter-dewey').val() || ''),
            category  : ($('#aura-lib-filter-category').val() || ''),
            status    : ($('#aura-lib-filter-status').val() || ''),
            area_id   : ($('#aura-lib-filter-area').val() || 0),
        }, function (res) {
            if (!res.success) { showTableError(res.data ? res.data.message : cfg.txt.error); return; }
            renderTable(res.data);
        }).fail(function () {
            showTableError(cfg.txt.error);
        });
    }

    function buildCoverHoverCardAttr(imgUrl, title, subtitle) {
        if (!imgUrl) return '';
        var html = '<div class="aura-floating-img-card aura-lib-floating-cover">' +
            '<img src="' + escAttr(imgUrl) + '" alt="' + escAttr(title || '') + '" class="aura-lib-floating-cover-img">' +
            '<span class="aura-floating-img-title">' + escHtml(title || '') + '</span>' +
            (subtitle ? '<span class="aura-floating-img-sub">' + escHtml(subtitle) + '</span>' : '') +
            '</div>';
        return ' data-aura-tooltip="' + escAttr(encodeURIComponent(html)) + '"';
    }

    function buildBookRichTipAttr(b) {
        var meta = '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header"><strong>📖 ' + escHtml(b.title) + '</strong></div>' +
            '<div class="aura-tip-card-body">' +
            (b.subtitle ? '<div class="aura-tip-row"><span>' + escHtml(__('Subtítulo:', 'aura-suite')) + '</span> <strong>' + escHtml(b.subtitle) + '</strong></div>' : '') +
            '<div class="aura-tip-row"><span>' + escHtml(__('Autor:', 'aura-suite')) + '</span> <strong>' + escHtml(b.author || '—') + '</strong></div>' +
            (b.isbn ? '<div class="aura-tip-row"><span>' + escHtml(__('ISBN:', 'aura-suite')) + '</span> <code>' + escHtml(b.isbn) + '</code></div>' : '') +
            (b.publisher ? '<div class="aura-tip-row"><span>' + escHtml(__('Editorial:', 'aura-suite')) + '</span> <span>' + escHtml(b.publisher) + (b.year_published ? ' (' + escHtml(b.year_published) + ')' : '') + '</span></div>' : '') +
            '<div class="aura-tip-row"><span>' + escHtml(__('Idioma:', 'aura-suite')) + '</span> <span>' + escHtml(b.language || __('Español', 'aura-suite')) + '</span></div>' +
            '</div></div>';
        return ' data-aura-tooltip="' + escAttr(encodeURIComponent(meta)) + '"';
    }

    function renderBookChildCard(b) {
        var availCount = parseInt(b.available_copies, 10) || 0;
        var totalCount = parseInt(b.total_copies, 10) || 0;
        var pct        = totalCount > 0 ? Math.round((availCount / totalCount) * 100) : 0;
        var barClass   = availCount === 0 ? 'micro-progress-fill--rose' : (availCount < totalCount && pct <= 35 ? 'micro-progress-fill--amber' : 'micro-progress-fill--emerald');
        var fullImg    = b.cover_full_url || b.cover_thumb_url;
        var cleanDesc  = b.description ? String(b.description).replace(/\\(["'])/g, '$1') : '';

        var kwHtml = '';
        if (b.keywords) {
            var kws = String(b.keywords).split(',').map(function (s) { return $.trim(s); }).filter(Boolean);
            if (kws.length) {
                kwHtml = '<div class="aura-child-tags">' +
                    kws.map(function (k) { return '<span class="badge badge-indigo aura-tooltip-trigger" data-tooltip="' + escAttr(__('Palabra clave:', 'aura-suite') + ' ' + k) + '">' + escHtml(k) + '</span>'; }).join('') +
                    '</div>';
            }
        }

        var coverBox = fullImg
            ? '<img src="' + escAttr(fullImg) + '" alt="' + escAttr(b.title) + '" class="aura-child-book-cover">'
            : '<div class="aura-child-book-cover-empty"><span class="dashicons dashicons-book-alt"></span></div>';

        return '<div class="aura-child-card aura-lib-child-card">' +
            '<div class="aura-lib-child-layout">' +
            '<div class="aura-lib-child-cover-col">' + coverBox + '</div>' +
            '<div class="aura-lib-child-content">' +
            '<div class="aura-child-hero-bar">' +
            '<div>' +
            '<h4 class="aura-child-hero-title">' + escHtml(b.title || '—') + '</h4>' +
            (b.subtitle ? '<div class="aura-lib-sub-muted">' + escHtml(b.subtitle) + '</div>' : '') +
            '<div class="aura-lib-child-author">✍️ ' + escHtml(b.author || '—') + '</div>' +
            '</div>' +
            '<div class="aura-lib-child-stock aura-tooltip-trigger" data-tooltip="' + escAttr(availCount + ' ' + __('de', 'aura-suite') + ' ' + totalCount + ' ' + __('ejemplares disponibles', 'aura-suite') + ' (' + pct + '%)') + '">' +
            '<div class="aura-lib-child-stock-text"><strong>' + availCount + ' / ' + totalCount + '</strong> <small>' + escHtml(__('ejemplares disponibles', 'aura-suite')) + '</small></div>' +
            '<div class="micro-progress-bar aura-lib-child-progress"><div class="micro-progress-fill ' + barClass + '" style="width:' + pct + '%;"></div></div>' +
            '</div>' +
            '</div>' +
            '<div class="aura-child-meta-grid">' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Estado', 'aura-suite')) + '</span><strong>' + escHtml(b.display_status_label || b.status_label || '—') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Dewey', 'aura-suite')) + '</span><strong><code>' + escHtml(b.dewey_number || '—') + '</code></strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('ISBN', 'aura-suite')) + '</span><strong><code>' + escHtml(b.isbn || '—') + '</code></strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Categoría', 'aura-suite')) + '</span><strong>' + escHtml(b.category || '—') + (b.subcategory ? ' / ' + escHtml(b.subcategory) : '') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Editorial / Año', 'aura-suite')) + '</span><strong>' + escHtml(b.publisher || '—') + (b.year_published ? ' (' + escHtml(b.year_published) + ')' : '') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Edición / Páginas', 'aura-suite')) + '</span><strong>' + escHtml(b.edition || '—') + (b.pages ? ' · ' + escHtml(b.pages) + ' ' + escHtml(__('pp.', 'aura-suite')) : '') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Idioma', 'aura-suite')) + '</span><strong>' + escHtml(b.language || __('Español', 'aura-suite')) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Ubicación', 'aura-suite')) + '</span><strong>' + escHtml(b.physical_location || '—') + (b.shelf_code ? ' · ' + escHtml(__('Est.', 'aura-suite')) + ' ' + escHtml(b.shelf_code) : '') + '</strong></div>' +
            '</div>' +
            (cleanDesc ? '<div class="aura-child-synopsis"><strong>' + escHtml(__('Sinopsis:', 'aura-suite')) + '</strong> ' + escHtml(cleanDesc) + '</div>' : '') +
            kwHtml +
            '<div class="aura-child-footer-actions">' +
            '<button type="button" class="btn btn-sm btn-indigo btn-lift aura-lib-btn-detail aura-tooltip-trigger" data-id="' + parseInt(b.id, 10) + '" data-tooltip="' + escAttr(__('Abrir ficha técnica 360° e historial del libro', 'aura-suite')) + '"><span class="dashicons dashicons-visibility"></span> ' + escHtml(__('Ver Ficha 360°', 'aura-suite')) + '</button>' +
            (b.can_edit ? '<button type="button" class="btn btn-sm btn-glass btn-lift aura-lib-btn-edit aura-tooltip-trigger" data-id="' + parseInt(b.id, 10) + '" data-tooltip="' + escAttr(__('Editar título, ejemplares, clasificación o portada', 'aura-suite')) + '"><span class="dashicons dashicons-edit"></span> ' + escHtml(__('Editar', 'aura-suite')) + '</button>' : '') +
            '</div>' +
            '</div></div></div>';
    }

    function renderTable(data) {
        var items = data.items || [];
        totalPages = data.total_pages || 1;
        _booksMap = {};

        var tbody = $('#aura-lib-books-table tbody');
        if (!items.length) {
            tbody.html('<tr><td colspan="10" style="text-align:center;padding:24px;">' + escHtml(cfg.txt.no_results) + '</td></tr>');
            renderPagination(data);
            return;
        }

        var rows = '';
        $.each(items, function (i, b) {
            _booksMap[parseInt(b.id, 10)] = b;
            var fullImg = b.cover_full_url || b.cover_thumb_url;
            var coverHtml = b.cover_thumb_url
                ? '<div class="aura-table-thumb-preview aura-lib-thumb-preview aura-tooltip-trigger"' +
                  buildCoverHoverCardAttr(fullImg, b.title, b.author || '') + '>' +
                  '<img src="' + escAttr(b.cover_thumb_url) + '" class="aura-lib-cover-thumb" alt="' + escAttr(b.title) + '">' +
                  '</div>'
                : '<span class="dashicons dashicons-format-image aura-lib-cover-placeholder-sm aura-tooltip-trigger" data-tooltip="' + escAttr(__('Sin imagen de portada', 'aura-suite')) + '"></span>';

            var statusClass = {
                'available'      : 'badge-emerald',
                'unavailable'    : 'badge-red',
                'reference_only' : 'badge-blue',
                'lost'           : 'badge-amber',
                'withdrawn'      : 'badge-slate',
                'on_loan'        : 'badge-violet'
            }[b.display_status] || 'badge-slate';

            var statusTip = {
                'available'      : __('Disponible para préstamo a lectores', 'aura-suite'),
                'unavailable'    : __('Sin ejemplares disponibles en este momento', 'aura-suite'),
                'reference_only' : __('Solo lectura en sala; no habilitado para préstamo externo', 'aura-suite'),
                'lost'           : __('Reportado como perdido en inventario', 'aura-suite'),
                'withdrawn'      : __('Retirado del catálogo activo', 'aura-suite'),
                'on_loan'        : __('Todos los ejemplares se encuentran prestados actualmente', 'aura-suite')
            }[b.display_status] || b.display_status_label;

            var copiesClass = {
                'badge-green'  : 'aura-lib-copies-green',
                'badge-yellow' : 'aura-lib-copies-amber',
                'badge-red'    : 'aura-lib-copies-red',
            }[b.copies_badge] || 'aura-lib-copies-muted';

            var availCount = parseInt(b.available_copies, 10) || 0;
            var totalCount = parseInt(b.total_copies, 10) || 0;
            var lentCount  = Math.max(0, totalCount - availCount);
            var copiesTip  = availCount + ' ' + __('disponibles de', 'aura-suite') + ' ' + totalCount + ' ' + __('totales', 'aura-suite') + (lentCount > 0 ? ' (' + lentCount + ' ' + __('en préstamo', 'aura-suite') + ')' : '');

            var actions = '<div class="aura-lib-actions">';
            actions += '<button type="button" class="aura-lib-btn-action aura-lib-btn-detail aura-tooltip-trigger" data-id="' + parseInt(b.id, 10) + '" data-tooltip="' + escAttr(__('Ver ficha completa del libro (360°)', 'aura-suite')) + '"><span class="dashicons dashicons-visibility"></span></button>';
            if (b.can_edit) {
                actions += '<button type="button" class="aura-lib-btn-action aura-lib-btn-edit aura-tooltip-trigger" data-id="' + parseInt(b.id, 10) + '" data-tooltip="' + escAttr(__('Editar datos y portada del libro', 'aura-suite')) + '"><span class="dashicons dashicons-edit"></span></button>';
            }
            if (b.can_delete) {
                actions += '<button type="button" class="aura-lib-btn-action aura-lib-btn-delete aura-lib-btn-danger aura-tooltip-trigger" data-id="' + parseInt(b.id, 10) + '" data-tooltip="' + escAttr(__('Eliminar libro del catálogo', 'aura-suite')) + '"><span class="dashicons dashicons-trash"></span></button>';
            }
            // F4.4 — Botón Reservar cuando no hay copias disponibles
            if (cfg.can_loan && availCount === 0 && b.status !== 'reference_only') {
                actions += '<button type="button" class="aura-lib-btn-action aura-lib-btn-reserve aura-tooltip-trigger" data-id="' + parseInt(b.id, 10) + '" data-title="' + escAttr(b.title) + '" data-tooltip="' + escAttr(__('Reservar libro para lista de espera', 'aura-suite')) + '"><span class="dashicons dashicons-calendar-alt"></span></button>';
            }
            actions += '</div>';

            var locTip = (b.physical_location || __('Sin sala asignada', 'aura-suite')) + (b.shelf_code ? ' · ' + __('Estante', 'aura-suite') + ' ' + b.shelf_code : '');

            // Orden: Expand(0), Portada(1), Título(2), Autor(3), Estado(4), Copias(5), Dewey(6), Categoría(7), Ubicación(8), Acciones(9)
            rows += '<tr class="aura-parent-row table-row-hover-lift" data-id="' + parseInt(b.id, 10) + '">' +
                '<td class="aura-lib-col-expand" data-label="' + escAttr(__('Detalle', 'aura-suite')) + '">' +
                    '<button type="button" class="aura-lib-row-toggle" data-id="' + parseInt(b.id, 10) + '" aria-expanded="false" data-tooltip="' + escAttr(__('Desplegar información completa del libro', 'aura-suite')) + '"><span class="dashicons dashicons-plus-alt2"></span></button>' +
                '</td>' +
                '<td class="aura-lib-col-cover" data-label="' + escAttr(__('Portada', 'aura-suite')) + '">' + coverHtml + '</td>' +
                '<td class="aura-lib-col-title" data-label="' + escAttr(__('Título', 'aura-suite')) + '"><div class="aura-tooltip-trigger"' + buildBookRichTipAttr(b) + '><strong>' + escHtml(b.title) + '</strong>' +
                    (b.subtitle ? '<br><small class="aura-lib-sub-muted">' + escHtml(b.subtitle) + '</small>' : '') +
                    '<div class="aura-lib-mobile-sub">' + escHtml(b.author || '—') + (b.dewey_number ? ' · Dewey ' + escHtml(b.dewey_number) : '') + '</div>' +
                    '</div></td>' +
                '<td class="aura-lib-col-secondary" data-label="' + escAttr(__('Autor', 'aura-suite')) + '">' + escHtml(b.author) + '</td>' +
                '<td class="aura-lib-col-status" data-label="' + escAttr(__('Estado', 'aura-suite')) + '"><span class="badge aura-lib-badge ' + statusClass + ' aura-tooltip-trigger" data-tooltip="' + escAttr(statusTip) + '">' + escHtml(b.display_status_label) + '</span></td>' +
                '<td class="aura-lib-col-secondary" data-label="' + escAttr(__('Copias', 'aura-suite')) + '"><span class="aura-lib-copies-cell ' + copiesClass + ' aura-tooltip-trigger" data-tooltip="' + escAttr(copiesTip) + '">' +
                    availCount + ' / ' + totalCount +
                    (availCount === 0 && lentCount > 0 ? '<br><small style="font-weight:normal;font-size:10px;">' + escHtml(lentCount + ' ' + (lentCount === 1 ? __('prestada', 'aura-suite') : __('prestadas', 'aura-suite'))) + '</small>' : '') +
                    '</span></td>' +
                '<td class="aura-lib-col-secondary" data-label="' + escAttr(__('Dewey', 'aura-suite')) + '"><code class="aura-tooltip-trigger" data-tooltip="' + escAttr(b.dewey_number ? __('Clasificación Dewey:', 'aura-suite') + ' ' + b.dewey_number : __('Sin código Dewey asignado', 'aura-suite')) + '">' + escHtml(b.dewey_number || '—') + '</code></td>' +
                '<td class="aura-lib-col-secondary" data-label="' + escAttr(__('Categoría', 'aura-suite')) + '">' + escHtml(b.category || '—') + '</td>' +
                '<td class="aura-lib-col-secondary" data-label="' + escAttr(__('Ubicación', 'aura-suite')) + '"><span class="aura-tooltip-trigger" data-tooltip="' + escAttr(locTip) + '">' + escHtml(b.physical_location || '—') +
                    (b.shelf_code ? '<br><small class="aura-lib-sub-muted">' + escHtml(b.shelf_code) + '</small>' : '') + '</span></td>' +
                '<td class="aura-lib-col-actions" data-label="' + escAttr(__('Acciones', 'aura-suite')) + '">' + actions + '</td>' +
            '</tr>';
        });
        tbody.html(rows);

        bindCoverTooltips();
        renderPagination(data);
        initDataTable();
    }

    function initDataTable() {
        if (_booksTable) {
            try { _booksTable.destroy(); } catch (e) {}
            _booksTable = null;
        }
        _booksTable = $('#aura-lib-books-table').DataTable({
            responsive  : false,
            paging      : false,
            searching   : false,
            info        : false,
            ordering    : false,
            autoWidth   : false,
            language    : { emptyTable: cfg.txt.no_results },
        });
    }

    function renderPagination(data) {
        var total  = data.total    || 0;
        var tPages = data.total_pages || 1;
        totalPages = tPages;

        var html = '<div class="aura-lib-pagination">';
        html += '<span class="aura-lib-count">' + total + ' ' + escHtml(cfg.txt.n_items.replace('%s', '')) + '</span>';

        if (tPages > 1) {
            html += '<div class="aura-lib-page-btns">';
            html += '<button type="button" class="aura-lib-page-btn btn btn-glass btn-sm" data-page="' + Math.max(1, currentPage - 1) + '"' +
                    (currentPage <= 1 ? ' disabled' : '') + '>‹</button>';

            var start = Math.max(1, currentPage - 2);
            var end   = Math.min(tPages, start + 4);
            for (var p = start; p <= end; p++) {
                html += '<button type="button" class="aura-lib-page-btn btn btn-sm ' + (p === currentPage ? 'btn-indigo' : 'btn-glass') +
                        '" data-page="' + p + '">' + p + '</button>';
            }

            html += '<button type="button" class="aura-lib-page-btn btn btn-glass btn-sm" data-page="' + Math.min(tPages, currentPage + 1) + '"' +
                    (currentPage >= tPages ? ' disabled' : '') + '>›</button>';
            html += '</div>';
        }
        html += '</div>';

        var paginationEl = $('#aura-lib-pagination');
        if (!paginationEl.length) {
            $('#aura-lib-books-table').after('<div id="aura-lib-pagination"></div>');
            paginationEl = $('#aura-lib-pagination');
        }
        paginationEl.html(html);
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL CREAR / EDITAR
    // ──────────────────────────────────────────────────────────────
    function openBookModal(bookId) {
        resetForm();
        var title = bookId ? cfg.txt.edit_title : cfg.txt.new_title;
        $('#aura-lib-modal-title').text(title);

        if (bookId) {
            showModal('#aura-lib-book-modal', { async: true });
            // Cargar datos del libro vía AJAX
            $.post(cfg.ajaxurl, {
                action : 'aura_library_books_get_detail',
                nonce  : cfg.nonce,
                id     : bookId,
            }, function (res) {
                if (!res.success) {
                    closeAllModals();
                    showToast(res.data ? res.data.message : cfg.txt.error, 'error');
                    return;
                }
                populateForm(res.data.book);
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-book-modal');
                }
            }).fail(function () {
                closeAllModals();
                showToast(cfg.txt.error, 'error');
            });
        } else {
            showModal('#aura-lib-book-modal');
        }
    }

    function populateForm(b) {
        $('#aura-lib-book-id').val(b.id);
        $('#aura-lib-f-title').val(b.title || '');
        $('#aura-lib-f-subtitle').val(b.subtitle || '');
        $('#aura-lib-f-author').val(b.author || '');
        $('#aura-lib-f-dewey').val(b.dewey_number || '');
        $('#aura-lib-f-isbn').val(b.isbn || '');
        $('#aura-lib-f-publisher').val(b.publisher || '');
        $('#aura-lib-f-year').val(b.year_published || '');
        $('#aura-lib-f-edition').val(b.edition || '');
        $('#aura-lib-f-language').val(b.language || 'Español');
        $('#aura-lib-f-pages').val(b.pages || '');
        $('#aura-lib-f-category').val(b.category || '');
        $('#aura-lib-f-subcategory').val(b.subcategory || '');
        $('#aura-lib-f-location').val(b.physical_location || '');
        $('#aura-lib-f-shelf').val(b.shelf_code || '');
        $('#aura-lib-f-copies').val(b.total_copies || 1);
        $('#aura-lib-f-status').val(b.status || 'available').trigger('change');
        $('#aura-lib-f-description').val(b.description || '').trigger('input');
        $('#aura-lib-f-area').val(b.area_id || 0);

        // Tags
        loadTagsFromString(b.keywords || '');

        // Dewey label
        if (b.dewey_number) {
            $('#aura-lib-f-dewey').trigger('input');
        }

        // Char counter for title
        updateCharCounter($('#aura-lib-f-title'), '#aura-lib-f-title', 255);

        // Portada
        if (b.cover_image_id && b.cover_thumb_url) {
            setDropzoneCover(b.cover_thumb_url, b.cover_image_id);
        }

        $('.aura-lib-manual-input').each(function () { syncManualFieldState($(this)); });
    }

    function syncManualFieldState($el) {
        var val = $.trim(String($el.val() || ''));
        var filled = $el.is('select') ? (val !== '' && val !== '0') : (val !== '');
        $el.closest('.aura-lib-manual-highlight').toggleClass('is-filled', filled);
    }

    function resetForm() {
        var form = document.getElementById('aura-lib-book-form');
        if (form) form.reset();
        $('#aura-lib-book-id').val(0);
        $('#aura-lib-f-cover-id').val(0);
        $('#aura-lib-f-cover-url').val('');
        $('#aura-lib-quick-isbn').val('');
        stopBarcodeScanner();
        stopRemoteScan();
        $('#aura-lib-cover-preview')
            .html('<span class="dashicons dashicons-format-image aura-lib-cover-placeholder"></span>' +
                  '<div class="aura-lib-dz-hint"><strong>' + escHtml(__('Arrastra una imagen aquí', 'aura-suite')) + '</strong><span>' + escHtml(__('o', 'aura-suite')) + '</span></div>');
        $('#aura-lib-dropzone').removeClass('has-image dragover');
        $('#aura-lib-btn-remove-cover').hide();
        $('.aura-lib-isbn-error').hide();
        $('#aura-lib-isbn-icon').removeClass('valid invalid');
        $('#aura-lib-dewey-label').text('');
        $('.aura-lib-dewey-pill').removeClass('active');
        $('.aura-lib-char-counter').text('0 / 255');
        $('.aura-lib-field-icon').removeClass('valid invalid');
        $('.aura-lib-manual-highlight').removeClass('is-filled');
        // Clear tag chips
        $('#aura-lib-tags-container .aura-lib-tag-chip').remove();
        $('#aura-lib-f-keywords').val('');
        $('#aura-lib-f-status').trigger('change');
        // Reset wizard to step 1 — directly without goToWizardStep to avoid
        // crashes when modal parent is hidden (no :visible panels found)
        $('.aura-lib-wstep-panel').hide();
        $('#aura-lib-wstep-1').show();
        $('.aura-lib-wizard-step').removeClass('active done');
        $('.aura-lib-wizard-step[data-step="1"]').addClass('active');
        $('.aura-lib-wizard-connector').removeClass('done');
        // Re-enable save button
        setSaveLoading(false);
        $('.aura-lib-save-msg').hide();
    }

    // ──────────────────────────────────────────────────────────────
    // WIZARD NAVIGATION
    // ──────────────────────────────────────────────────────────────
    function goToWizardStep(step, silent) {
        $('.aura-lib-wstep-panel').hide();
        $('#aura-lib-wstep-' + step).show();

        // Update progress indicators
        $('.aura-lib-wizard-step').each(function () {
            var s = parseInt($(this).data('step'), 10);
            $(this).removeClass('active done');
            if (s === step) $(this).addClass('active');
            else if (s < step) $(this).addClass('done');
        });

        // Update connectors
        $('.aura-lib-wizard-connector').each(function (i) {
            $(this).toggleClass('done', (i + 1) < step);
        });

        if (!silent) {
            // Scroll modal body to top
            $('.aura-lib-modal-body').scrollTop(0);
        }

        // Autofocus first field
        setTimeout(function () {
            var $first = $('#aura-lib-wstep-' + step).find('input:not([type=hidden]), select, textarea').first();
            if ($first.length) $first.focus();
        }, 100);
    }

    function validateWizardStep(step) {
        var valid = true;
        if (step === 1) {
            // Validate title
            var $title = $('#aura-lib-f-title');
            if (!$.trim($title.val())) {
                markFieldInvalid($title);
                valid = false;
            } else {
                markFieldValid($title);
            }
            // Validate author
            var $author = $('#aura-lib-f-author');
            if (!$.trim($author.val())) {
                markFieldInvalid($author);
                valid = false;
            } else {
                markFieldValid($author);
            }
            // Validate ISBN if entered
            var isbn = $.trim($('#aura-lib-f-isbn').val());
            if (isbn && !validateIsbn(isbn)) {
                $('.aura-lib-isbn-error').show();
                valid = false;
            }
            if (!valid) {
                shakePanel('#aura-lib-wstep-1');
            }
        }
        return valid;
    }

    function markFieldInvalid($el) {
        $el.closest('.aura-lib-input-wrap').addClass('aura-lib-input-invalid').removeClass('aura-lib-input-valid');
        $el.closest('.aura-lib-input-wrap').find('.aura-lib-field-icon').removeClass('valid').addClass('invalid');
    }
    function markFieldValid($el) {
        $el.closest('.aura-lib-input-wrap').addClass('aura-lib-input-valid').removeClass('aura-lib-input-invalid');
        $el.closest('.aura-lib-input-wrap').find('.aura-lib-field-icon').removeClass('invalid').addClass('valid');
    }
    function validateRequired($el) {
        if ($.trim($el.val())) markFieldValid($el);
        else markFieldInvalid($el);
    }

    function shakePanel(selector) {
        var $p = $(selector);
        $p.css('animation', 'none');
        setTimeout(function () {
            $p.css('animation', 'auraShake .35s ease');
            setTimeout(function () { $p.css('animation', ''); }, 400);
        }, 10);
    }

    function updateCharCounter($input, selector, max) {
        var len  = $input.val().length;
        var $c   = $input.closest('.aura-lib-form-row').find('.aura-lib-char-counter');
        $c.text(len + ' / ' + max);
        $c.toggleClass('warn', len > max * 0.8).toggleClass('over', len > max);
    }

    // ──────────────────────────────────────────────────────────────
    // TAG CHIPS (keywords)
    // ──────────────────────────────────────────────────────────────
    function addTag(text) {
        if (!text || text.length < 1) return;
        // No duplicates
        var existing = $('#aura-lib-f-keywords').val().split(',').map(function (t) { return t.trim().toLowerCase(); });
        if (existing.indexOf(text.toLowerCase()) !== -1) return;

        var $chip = $('<span class="aura-lib-tag-chip"></span>')
            .text(text)
            .append('<button type="button" class="aura-lib-tag-remove" aria-label="Quitar">×</button>');
        $('#aura-lib-tags-text').before($chip);
        syncTagsHidden();
    }

    function syncTagsHidden() {
        var tags = [];
        $('#aura-lib-tags-container .aura-lib-tag-chip').each(function () {
            tags.push($.trim($(this).clone().children().remove().end().text()));
        });
        $('#aura-lib-f-keywords').val(tags.join(','));
    }

    function loadTagsFromString(str) {
        $('#aura-lib-tags-container .aura-lib-tag-chip').remove();
        if (!str) return;
        str.split(',').forEach(function (t) { addTag($.trim(t)); });
    }

    // ──────────────────────────────────────────────────────────────
    // DROPPED FILE (drag & drop de imagen)
    // ──────────────────────────────────────────────────────────────
    function handleDroppedFile(file) {
        if (!file.type.startsWith('image/')) {
            showToast(__('El archivo debe ser una imagen.', 'aura-suite'), 'error');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            setDropzoneCover(e.target.result, 0);
        };
        reader.readAsDataURL(file);
        // Note: we can only preview local; to upload properly, we still need WP Media
        showToast(__('Vista previa local. Usa "Seleccionar imagen" para cargar al servidor.', 'aura-suite'), 'error');
    }

    // ──────────────────────────────────────────────────────────────
    // SAVE BUTTON STATE
    // ──────────────────────────────────────────────────────────────
    function setSaveLoading(loading) {
        var $btn = $('#aura-lib-btn-save');
        if (loading) {
            $btn.prop('disabled', true).addClass('saving');
            $btn.find('.aura-lib-btn-label').hide();
            $btn.find('.aura-lib-btn-loading').show();
        } else {
            $btn.prop('disabled', false).removeClass('saving');
            $btn.find('.aura-lib-btn-label').show();
            $btn.find('.aura-lib-btn-loading').hide();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // TOAST NOTIFICATION
    // ──────────────────────────────────────────────────────────────
    function showToast(msg, type) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
            AuraUI.showToast(escHtml(msg), type || 'success');
            return;
        }
        var icon = type === 'success' ? '✅' : '❌';
        var $t = $('<div class="aura-lib-toast aura-lib-toast-' + type + '">' + icon + ' ' + escHtml(msg) + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $(this).remove(); }); }, 3500);
    }

    // ──────────────────────────────────────────────────────────────
    // COVER HELPERS
    // ──────────────────────────────────────────────────────────────
    function setDropzoneCover(src, attachmentId) {
        $('#aura-lib-f-cover-id').val(attachmentId);
        $('#aura-lib-cover-preview').html('<img src="' + escAttr(src) + '" alt="">');
        $('#aura-lib-dropzone').addClass('has-image');
        $('#aura-lib-btn-remove-cover').show();
    }

    // ──────────────────────────────────────────────────────────────
    // GUARDAR LIBRO
    // ──────────────────────────────────────────────────────────────
    function saveBook() {
        var isbn = $.trim($('#aura-lib-f-isbn').val());
        if (isbn && !validateIsbn(isbn)) {
            $('.aura-lib-isbn-error').show();
            goToWizardStep(1);
            showToast(__('ISBN inválido. Revisa el campo antes de guardar.', 'aura-suite'), 'error');
            return;
        }
        $('.aura-lib-isbn-error').hide();

        setSaveLoading(true);
        $('.aura-lib-save-msg').hide();

        var formData = $('#aura-lib-book-form').serializeArray();
        var postData = { action: 'aura_library_books_save', nonce: cfg.nonce };
        $.each(formData, function (i, field) { postData[field.name] = field.value; });

        $.post(cfg.ajaxurl, postData, function (res) {
            setSaveLoading(false);
            if (!res.success) {
                var msg = res.data ? res.data.message : cfg.txt.error;
                showToast(msg, 'error');
                showSaveMsg(msg, 'error');
                return;
            }
            showToast(cfg.txt.saved || __('Libro guardado correctamente.', 'aura-suite'), 'success');
            closeAllModals();
            setTimeout(function () { window.location.reload(); }, 450);
        }).fail(function () {
            setSaveLoading(false);
            showToast(cfg.txt.error, 'error');
        });
    }

    // ──────────────────────────────────────────────────────────────
    // ELIMINAR LIBRO
    // ──────────────────────────────────────────────────────────────
    function deleteBook(id) {
        if (!confirm(cfg.txt.confirm_delete)) return;

        $.post(cfg.ajaxurl, {
            action : 'aura_library_books_delete',
            nonce  : cfg.nonce,
            id     : id,
        }, function (res) {
            if (!res.success) {
                showToast(res.data ? res.data.message : cfg.txt.error, 'error');
                return;
            }
            showToast(cfg.txt.deleted || __('Libro eliminado correctamente.', 'aura-suite'), 'success');
            setTimeout(function () { window.location.reload(); }, 450);
        }).fail(function () {
            showToast(cfg.txt.error, 'error');
        });
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL DETALLE
    // ──────────────────────────────────────────────────────────────
    function openDetailModal(id) {
        $('#aura-lib-detail-body').empty();
        $('#aura-lib-detail-edit-btn').hide().attr('data-id', 0);
        showModal('#aura-lib-detail-modal', { async: true });

        $.post(cfg.ajaxurl, {
            action : 'aura_library_books_get_detail',
            nonce  : cfg.nonce,
            _t     : Date.now(),
            id     : id,
        }, function (res) {
            if (!res.success) {
                $('#aura-lib-detail-body').html('<p class="notice notice-error">' + escHtml(res.data ? res.data.message : cfg.txt.error) + '</p>');
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-detail-modal');
                }
                return;
            }
            $('#aura-lib-detail-title').text(res.data.book.title || '');
            if (cfg.can_edit) {
                $('#aura-lib-detail-edit-btn').attr('data-id', res.data.book.id).show();
            }
            renderDetailBody(res.data);
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-detail-modal');
            }
        }).fail(function () {
            $('#aura-lib-detail-body').html('<p>' + escHtml(cfg.txt.error) + '</p>');
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-detail-modal');
            }
        });
    }

    $(document).on('click', '#aura-lib-detail-edit-btn', function () {
        var bookId = parseInt($(this).attr('data-id'), 10);
        if (!bookId) return;
        closeAllModals();
        setTimeout(function () { openBookModal(bookId); }, 120);
    });

    function renderDetailBody(data) {
        var b       = data.book;
        var loans   = data.loans || [];
        var fullUrl = b.cover_full_url || b.cover_thumb_url;

        var availCount = parseInt(b.available_copies, 10) || 0;
        var totalCount = Math.max(1, parseInt(b.total_copies, 10) || 1);
        var availPct   = Math.min(100, Math.round((availCount / totalCount) * 100));

        var coverHtml = fullUrl
            ? '<div class="aura-lib-detail-cover-card aura-tooltip-trigger"' + buildCoverHoverCardAttr(fullUrl, b.title, b.author || '') + '>' +
              '<img src="' + escAttr(fullUrl) + '" alt="' + escAttr(b.title) + '" class="aura-lib-detail-cover">' +
              '<span class="aura-lib-detail-cover-zoom-hint"><span class="dashicons dashicons-search"></span> ' + escHtml(__('Ampliar', 'aura-suite')) + '</span>' +
              '</div>'
            : '<div class="aura-lib-detail-cover-placeholder-box"><span class="dashicons dashicons-book-alt"></span><small>' + escHtml(__('Sin portada', 'aura-suite')) + '</small></div>';

        var statusClass = {
            'available'      : 'badge-emerald',
            'unavailable'    : 'badge-red',
            'reference_only' : 'badge-blue',
            'lost'           : 'badge-amber',
            'withdrawn'      : 'badge-slate',
            'on_loan'        : 'badge-violet'
        }[b.display_status || b.status] || 'badge-slate';

        var html = '<div class="aura-lib-detail-hero">';
        html += '<div class="aura-lib-detail-cover-col">' + coverHtml +
            '<div class="aura-lib-detail-avail-box">' +
                '<div class="aura-lib-detail-avail-top">' +
                    '<span class="badge aura-lib-badge ' + statusClass + '">' + escHtml(b.display_status_label || b.status_label) + '</span>' +
                    '<strong>' + availCount + ' / ' + totalCount + '</strong>' +
                '</div>' +
                '<div class="micro-progress-bar"><span class="micro-progress-fill" style="width:' + availPct + '%;"></span></div>' +
                '<span class="aura-lib-sub-muted">' + availCount + ' ' + escHtml(__('ejemplares disponibles', 'aura-suite')) + '</span>' +
            '</div>' +
        '</div>';

        html += '<div class="aura-lib-detail-info-col">';
        html += '<div class="aura-lib-detail-title-block">' +
            '<h3>' + escHtml(b.title || '') + '</h3>' +
            (b.subtitle ? '<p class="aura-lib-detail-subtitle">' + escHtml(b.subtitle) + '</p>' : '') +
            '<div class="aura-lib-detail-author-chip"><span class="dashicons dashicons-admin-users"></span> ' + escHtml(b.author || __('Autor no especificado', 'aura-suite')) + '</div>' +
        '</div>';

        var specs = [
            { icon: '📚', label: __('Dewey', 'aura-suite'),      val: b.dewey_number || '—' },
            { icon: '🔢', label: __('ISBN', 'aura-suite'),       val: b.isbn || '—', isCode: true },
            { icon: '🏛️', label: __('Editorial', 'aura-suite'),  val: b.publisher || '—' },
            { icon: '📅', label: __('Año / Ed.', 'aura-suite'),  val: (b.year_published || '—') + (b.edition ? ' · ' + b.edition : '') },
            { icon: '🌐', label: __('Idioma', 'aura-suite'),     val: b.language || __('Español', 'aura-suite') },
            { icon: '📄', label: __('Páginas', 'aura-suite'),    val: b.pages ? b.pages + ' ' + __('págs.', 'aura-suite') : '—' },
            { icon: '🏷️', label: __('Categoría', 'aura-suite'),  val: (b.category || '—') + (b.subcategory ? ' / ' + b.subcategory : '') },
            { icon: '📍', label: __('Ubicación', 'aura-suite'),  val: (b.physical_location || '—') + (b.shelf_code ? ' · ' + __('Estante', 'aura-suite') + ' ' + b.shelf_code : '') },
        ];

        html += '<div class="aura-lib-detail-specs-grid">';
        $.each(specs, function (i, sp) {
            html += '<div class="aura-lib-detail-spec-item aura-tooltip-trigger" data-tooltip="' + escAttr(sp.label + ': ' + sp.val) + '">' +
                '<span class="aura-lib-spec-label">' + sp.icon + ' ' + escHtml(sp.label) + '</span>' +
                '<strong class="aura-lib-spec-val">' + (sp.isCode && sp.val !== '—' ? '<code>' + escHtml(sp.val) + '</code>' : escHtml(String(sp.val))) + '</strong>' +
                '</div>';
        });
        html += '</div>';

        if (b.keywords) {
            html += '<div class="aura-lib-detail-keywords">';
            b.keywords.split(',').forEach(function (kw) {
                var cleanKw = $.trim(kw);
                if (cleanKw) {
                    html += '<span class="badge badge-indigo">#' + escHtml(cleanKw) + '</span>';
                }
            });
            html += '</div>';
        }

        if (b.description) {
            html += '<div class="aura-lib-detail-synopsis"><h4>📝 ' + escHtml(__('Sinopsis', 'aura-suite')) + '</h4><p>' + escHtml(b.description) + '</p></div>';
        }

        html += '</div></div>';

        // Historial de préstamos
        html += '<div class="aura-lib-detail-history-card">';
        html += '<h3 class="aura-lib-detail-history-title"><span class="dashicons dashicons-backup"></span> ' + escHtml(__('Historial de préstamos', 'aura-suite')) + ' (' + loans.length + ')</h3>';
        if (!loans.length) {
            html += '<p class="aura-lib-detail-empty">' + escHtml(__('Sin préstamos registrados para este libro.', 'aura-suite')) + '</p>';
        } else {
            html += '<div class="aura-dt-wrapper aura-table-responsive-wrap" style="margin-top:10px;"><table class="aura-table aura-lib-responsive-table widefat"><thead><tr>' +
                '<th>' + escHtml(__('Lector', 'aura-suite')) + '</th><th>' + escHtml(__('Préstamo', 'aura-suite')) + '</th><th>' + escHtml(__('Vencimiento', 'aura-suite')) + '</th><th>' + escHtml(__('Devolución', 'aura-suite')) + '</th><th>' + escHtml(__('Estado', 'aura-suite')) + '</th>' +
                '</tr></thead><tbody>';
            $.each(loans, function (i, l) {
                var lStatusClass = {
                    'active'   : 'badge-blue',
                    'returned' : 'badge-emerald',
                    'overdue'  : 'badge-red',
                    'lost'     : 'badge-amber',
                    'extended' : 'badge-violet',
                }[l.status] || 'badge-slate';

                var dueClass = l.is_overdue ? 'aura-lib-due-overdue' : '';
                html += '<tr class="table-row-hover-lift">' +
                    '<td data-label="' + escAttr(__('Lector', 'aura-suite')) + '"><strong>' + escHtml(l.borrower) + '</strong></td>' +
                    '<td data-label="' + escAttr(__('Préstamo', 'aura-suite')) + '">' + escHtml(l.loan_date) + '</td>' +
                    '<td data-label="' + escAttr(__('Vencimiento', 'aura-suite')) + '" class="' + dueClass + '">' + escHtml(l.due_date) + '</td>' +
                    '<td data-label="' + escAttr(__('Devolución', 'aura-suite')) + '">' + escHtml(l.return_date || '—') + '</td>' +
                    '<td data-label="' + escAttr(__('Estado', 'aura-suite')) + '"><span class="badge aura-lib-badge ' + lStatusClass + '">' + escHtml(l.status_label) + '</span></td>' +
                '</tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';

        $('#aura-lib-detail-body').html(html);
    }

    // ──────────────────────────────────────────────────────────────
    // MEDIA UPLOADER (PORTADA)
    // ──────────────────────────────────────────────────────────────
    var mediaFrame = null;
    function openMediaUploader() {
        if (mediaFrame) { mediaFrame.open(); return; }

        mediaFrame = wp.media({
            title  : 'Seleccionar portada del libro',
            button : { text: 'Usar esta imagen' },
            multiple: false,
            library : { type: 'image' },
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
                ? attachment.sizes.thumbnail.url
                : attachment.url;
            $('#aura-lib-f-cover-url').val('');
            setDropzoneCover(thumbUrl, attachment.id);
        });

        mediaFrame.open();
    }

    function removeCover() {
        $('#aura-lib-f-cover-id').val(0);
        $('#aura-lib-f-cover-url').val('');
        $('#aura-lib-cover-preview')
            .html('<span class="dashicons dashicons-format-image aura-lib-cover-placeholder"></span>' +
                  '<div class="aura-lib-dz-hint"><strong>' + escHtml(__('Arrastra una imagen aquí', 'aura-suite')) + '</strong><span>' + escHtml(__('o', 'aura-suite')) + '</span></div>');
        $('#aura-lib-dropzone').removeClass('has-image');
        $('#aura-lib-btn-remove-cover').hide();
    }

    // ──────────────────────────────────────────────────────────────
    // TOOLTIP DE PORTADA
    // ──────────────────────────────────────────────────────────────
    function bindCoverTooltips() {
        $(document).off('mouseenter.coverthumb').on('mouseenter.coverthumb', '.aura-lib-cover-thumb[data-full]', function () {
            var fullUrl = $(this).data('full');
            if (!fullUrl) return;
            var $tip = $('<div class="aura-lib-cover-tooltip"><img src="' + escAttr(fullUrl) + '" alt=""></div>');
            $('body').append($tip);
            var off = $(this).offset();
            $tip.css({ top: off.top - $tip.outerHeight() - 10, left: off.left + $(this).outerWidth() + 8 }).fadeIn(150);
        }).off('mouseleave.coverthumb').on('mouseleave.coverthumb', '.aura-lib-cover-thumb[data-full]', function () {
            $('.aura-lib-cover-tooltip').remove();
        });
    }

    // ──────────────────────────────────────────────────────────────
    // VALIDACIÓN ISBN (client-side)
    // ──────────────────────────────────────────────────────────────
    function validateIsbn(isbn) {
        var clean = isbn.replace(/[^0-9X]/gi, '');
        if (clean.length === 10) {
            var sum = 0;
            for (var i = 0; i < 9; i++) sum += (10 - i) * parseInt(clean[i], 10);
            var last = clean[9].toUpperCase() === 'X' ? 10 : parseInt(clean[9], 10);
            sum += last;
            return sum % 11 === 0;
        }
        if (clean.length === 13) {
            var s = 0;
            for (var j = 0; j < 12; j++) s += parseInt(clean[j], 10) * (j % 2 === 0 ? 1 : 3);
            var check = (10 - (s % 10)) % 10;
            return check === parseInt(clean[12], 10);
        }
        return false;
    }

    function validateIsbnField(val) {
        var v = $.trim(val);
        if (v && !validateIsbn(v)) {
            $('.aura-lib-isbn-error').show();
        } else {
            $('.aura-lib-isbn-error').hide();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES MODALES (AuraUI + Skeleton Loader)
    // ──────────────────────────────────────────────────────────────
    function showModal(selector, options) {
        var modalId = String(selector || '').replace(/^#/, '');
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.openModal === 'function') {
            AuraUI.openModal(modalId, options || {});
        } else {
            $(selector).css('display', 'flex').addClass('active is-active');
        }
        $('body').addClass('aura-lib-modal-open');
    }
    function closeAllModals() {
        stopBarcodeScanner();
        stopRemoteScan();
        $('.aura-lib-modal').each(function () {
            var id = this.id;
            if (id && typeof AuraUI !== 'undefined' && typeof AuraUI.closeModal === 'function') {
                AuraUI.closeModal(id);
            } else {
                $(this).removeClass('active is-active').hide();
            }
        });
        $('body').removeClass('aura-lib-modal-open');
        $('.aura-lib-cover-tooltip').remove();
    }

    // ──────────────────────────────────────────────────────────────
    // BÚSQUEDA INTELIGENTE POR ISBN & ESCÁNER DE CÓDIGO DE BARRAS
    // ──────────────────────────────────────────────────────────────
    var _html5QrScanner = null;
    var _scannerActive  = false;

    function lookupIsbn(rawInput) {
        var raw   = $.trim(String(rawInput || ''));
        var clean = raw.replace(/[^0-9X]/gi, '').toUpperCase();
        var isIsbn = (clean.length === 10 || clean.length === 13) && validateIsbn(clean);

        if (!isIsbn && raw.length < 3) {
            showToast(__('Ingresa un ISBN válido o al menos 3 letras del Título y Autor.', 'aura-suite'), 'error');
            return;
        }

        var $btnMain   = $('#aura-lib-btn-lookup-isbn');
        var $btnInline = $('#aura-lib-btn-lookup-isbn-inline');
        $btnMain.prop('disabled', true).addClass('loading');
        $btnInline.prop('disabled', true).addClass('loading');

        var payload = isIsbn
            ? { action: 'aura_library_books_lookup_isbn', nonce: cfg.nonce, isbn: clean }
            : { action: 'aura_library_books_search_external', nonce: cfg.nonce, query: raw };

        $.post(cfg.ajaxurl, payload, function (res) {
            $btnMain.prop('disabled', false).removeClass('loading');
            $btnInline.prop('disabled', false).removeClass('loading');

            if (!res.success || !res.data) {
                showToast((res.data && res.data.message) ? res.data.message : __('No se encontraron datos para esa búsqueda.', 'aura-suite'), 'error');
                return;
            }

            applyBookMetadata(res.data, !isIsbn);
        }).fail(function () {
            $btnMain.prop('disabled', false).removeClass('loading');
            $btnInline.prop('disabled', false).removeClass('loading');
            showToast(__('Error de conexión al consultar las bases de datos de libros.', 'aura-suite'), 'error');
        });
    }

    function applyBookMetadata(d, preserveExistingIsbn) {
        var currentIsbn = $.trim($('#aura-lib-f-isbn').val());
        if (d.isbn && !(preserveExistingIsbn && currentIsbn)) {
            $('#aura-lib-quick-isbn').val(d.isbn);
            $('#aura-lib-f-isbn').val(d.isbn).trigger('input');
        }
        if (d.title)          $('#aura-lib-f-title').val(d.title).trigger('input');
        if (d.subtitle)       $('#aura-lib-f-subtitle').val(d.subtitle);
        if (d.author)         $('#aura-lib-f-author').val(d.author).trigger('input');
        if (d.publisher)      $('#aura-lib-f-publisher').val(d.publisher);
        if (d.dewey_number)   $('#aura-lib-f-dewey').val(d.dewey_number).trigger('input');
        if (d.year_published) $('#aura-lib-f-year').val(d.year_published);
        if (d.pages)          $('#aura-lib-f-pages').val(d.pages);
        if (d.language)       $('#aura-lib-f-language').val(d.language);
        if (d.category)       $('#aura-lib-f-category').val(d.category);
        if (d.subcategory)    $('#aura-lib-f-subcategory').val(d.subcategory);
        if (d.description)    $('#aura-lib-f-description').val(d.description).trigger('input');
        if (d.keywords)       loadTagsFromString(d.keywords);

        if (d.cover_url) {
            $('#aura-lib-f-cover-url').val(d.cover_url);
            setDropzoneCover(d.cover_url, 0);
        }

        updateCharCounter($('#aura-lib-f-title'), '#aura-lib-f-title', 255);

        var srcTxt = (d.sources && d.sources.length) ? ' (' + d.sources.join(' + ') + ')' : '';
        if (d.language && d.language !== 'Español') {
            showToast(__('Cargado en', 'aura-suite') + ' ' + d.language + srcTxt + '. ' + __('Si buscas la edición en español, escribe el Título y Autor arriba y pulsa Buscar.', 'aura-suite'), 'success');
        } else {
            showToast(__('Ficha, clasificación y portada cargadas', 'aura-suite') + srcTxt + '.', 'success');
        }
    }

    function getScannerConfig() {
        return {
            fps: 10,
            qrbox: function (viewfinderWidth, viewfinderHeight) {
                var w = Math.min(320, Math.floor(viewfinderWidth * 0.85));
                var h = Math.min(160, Math.floor(viewfinderHeight * 0.55));
                return { width: Math.max(180, w), height: Math.max(100, h) };
            },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.CODE_128,
            ],
        };
    }

    function populateCameraSelect() {
        if (typeof Html5Qrcode === 'undefined') return;
        Html5Qrcode.getCameras().then(function (devices) {
            var $selWrap = $('#aura-lib-camera-select-wrap');
            var $sel     = $('#aura-lib-camera-select').empty();
            if (devices && devices.length > 1) {
                $.each(devices, function (idx, cam) {
                    var label = cam.label || (__('Cámara', 'aura-suite') + ' ' + (idx + 1));
                    $sel.append('<option value="' + escAttr(cam.id) + '">' + escHtml(label) + '</option>');
                });
                $selWrap.show();
            } else {
                $selWrap.hide();
            }
        }).catch(function () {});
    }

    function launchScannerWithTarget(cameraTarget, allowDeviceFallback) {
        var $status = $('#aura-lib-scanner-status');
        if (!_html5QrScanner) {
            _html5QrScanner = new Html5Qrcode('aura-lib-qr-reader');
        }
        _scannerActive = true;

        _html5QrScanner.start(
            cameraTarget,
            getScannerConfig(),
            function (decodedText) {
                var clean = String(decodedText || '').replace(/[^0-9X]/gi, '');
                if (clean.length === 10 || clean.length === 13) {
                    playScanBeep();
                    if (navigator.vibrate) { try { navigator.vibrate(100); } catch (e) {} }
                    stopBarcodeScanner();
                    $('#aura-lib-quick-isbn').val(clean);
                    lookupIsbn(clean);
                }
            },
            function () {
                // Frame without barcode — silent
            }
        ).then(function () {
            $status.text(__('Encuadra el código de barras ISBN dentro del recuadro.', 'aura-suite'));
            populateCameraSelect();
        }).catch(function (err) {
            if (allowDeviceFallback) {
                Html5Qrcode.getCameras().then(function (devices) {
                    if (devices && devices.length > 0) {
                        var preferred = devices[0].id;
                        $.each(devices, function (_, cam) {
                            var lbl = (cam.label || '').toLowerCase();
                            if (lbl.indexOf('back') !== -1 || lbl.indexOf('rear') !== -1 || lbl.indexOf('environment') !== -1 ||
                                lbl.indexOf('iphone') !== -1 || lbl.indexOf('galaxy') !== -1 || lbl.indexOf('samsung') !== -1 ||
                                lbl.indexOf('phone link') !== -1 || lbl.indexOf('enlace') !== -1) {
                                preferred = cam.id;
                                return false;
                            }
                        });
                        launchScannerWithTarget(preferred, false);
                        return;
                    }
                    _scannerActive = false;
                    $status.text(__('No se detectó ninguna cámara disponible.', 'aura-suite'));
                    showToast(__('No se pudo acceder a la cámara. Revisa los permisos del navegador.', 'aura-suite'), 'error');
                }).catch(function () {
                    _scannerActive = false;
                    $status.text(__('No se pudo abrir la cámara:', 'aura-suite') + ' ' + (err && err.message ? err.message : err));
                    showToast(__('No se pudo acceder a la cámara. Revisa los permisos del navegador.', 'aura-suite'), 'error');
                });
                return;
            }
            _scannerActive = false;
            $status.text(__('No se pudo abrir la cámara:', 'aura-suite') + ' ' + (err && err.message ? err.message : err));
            showToast(__('No se pudo acceder a la cámara. Revisa los permisos o usa "Cámara del celular (QR)".', 'aura-suite'), 'error');
        });
    }

    function loadHtml5QrcodeScript(callback) {
        if (typeof Html5Qrcode !== 'undefined') {
            callback();
            return;
        }
        $.getScript('https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js')
            .done(function () {
                callback();
            })
            .fail(function () {
                $('#aura-lib-scanner-status').text(__('No se pudo descargar el lector de cámara.', 'aura-suite'));
                showToast(__('No se pudo cargar el módulo de cámara. Verifica tu conexión.', 'aura-suite'), 'error');
            });
    }

    function startBarcodeScanner() {
        var $panel  = $('#aura-lib-scanner-panel');
        var $status = $('#aura-lib-scanner-status');

        if (_scannerActive) {
            stopBarcodeScanner();
            return;
        }

        stopRemoteScan();

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            $panel.slideDown(180);
            $status.text(__('El navegador bloqueó el acceso a la cámara (requiere HTTPS y permiso de cámara).', 'aura-suite'));
            showToast(__('Tu navegador o conexión (HTTP sin HTTPS) no permite abrir la cámara.', 'aura-suite'), 'error');
            return;
        }

        $panel.slideDown(180, function () {
            try { $panel[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
        });
        $status.text(__('Iniciando cámara…', 'aura-suite'));

        if (typeof Html5Qrcode === 'undefined') {
            $status.text(__('Cargando módulo de cámara…', 'aura-suite'));
            loadHtml5QrcodeScript(function () {
                launchScannerWithTarget({ facingMode: 'environment' }, true);
            });
            return;
        }

        launchScannerWithTarget({ facingMode: 'environment' }, true);
    }

    function switchScannerCamera(camId) {
        if (!_html5QrScanner || !_scannerActive) return;
        $('#aura-lib-scanner-status').text(__('Cambiando de cámara…', 'aura-suite'));
        _html5QrScanner.stop().then(function () {
            launchScannerWithTarget(camId, []);
        }).catch(function () {
            launchScannerWithTarget(camId, []);
        });
    }

    function stopBarcodeScanner() {
        var $panel = $('#aura-lib-scanner-panel');
        if (_html5QrScanner && _scannerActive) {
            _scannerActive = false;
            _html5QrScanner.stop().then(function () {
                try { _html5QrScanner.clear(); } catch (e) {}
                _html5QrScanner = null;
            }).catch(function () {
                _html5QrScanner = null;
            });
        } else {
            _scannerActive = false;
            _html5QrScanner = null;
        }
        if ($panel.length && $panel.is(':visible')) {
            $panel.slideUp(150);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // ESCÁNER REMOTO DESDE CELULAR VÍA QR (Samsung Galaxy / iPhone)
    // ──────────────────────────────────────────────────────────────
    var _remoteScanTimer = null;
    var _remoteScanToken = '';

    function startRemoteScan() {
        var $panel = $('#aura-lib-remote-qr-panel');
        if ($panel.is(':visible')) {
            stopRemoteScan();
            return;
        }

        stopBarcodeScanner();
        $('#aura-lib-remote-qr-box').html('<span class="spinner is-active" style="float:none;"></span>');
        $('#aura-lib-remote-qr-url').val('');
        $('#aura-lib-remote-qr-status').html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Generando enlace QR…');
        $panel.slideDown(180);

        $.post(cfg.ajaxurl, {
            action : 'aura_library_books_create_remote_scan',
            nonce  : cfg.nonce,
        }, function (res) {
            if (!res.success || !res.data) {
                showToast(__('No se pudo crear la sesión de escaneo remoto.', 'aura-suite'), 'error');
                stopRemoteScan();
                return;
            }

            _remoteScanToken = res.data.token;
            var scanUrl = res.data.scan_url;
            $('#aura-lib-remote-qr-url').val(scanUrl);

            var $qrBox = $('#aura-lib-remote-qr-box').empty();
            if (typeof QRCode !== 'undefined') {
                new QRCode($qrBox[0], {
                    text: scanUrl,
                    width: 160,
                    height: 160,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                });
            } else {
                $qrBox.html('<a href="' + escAttr(scanUrl) + '" target="_blank" class="button button-primary">' + escHtml(__('Abrir enlace en celular', 'aura-suite')) + '</a>');
            }

            var httpsNote = res.data.is_https
                ? ''
                : '<br><small style="color:#b45309;">⚠️ ' + escHtml(__('Nota: En entornos locales HTTP el navegador móvil puede bloquear la cámara; en HTTPS funcionará directo.', 'aura-suite')) + '</small>';

            $('#aura-lib-remote-qr-status').html(
                '<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> ' + escHtml(__('Esperando escaneo desde tu celular…', 'aura-suite')) + httpsNote
            );

            if (_remoteScanTimer) clearInterval(_remoteScanTimer);
            _remoteScanTimer = setInterval(pollRemoteScan, 1500);
        }).fail(function () {
            showToast(__('Error al conectar con el servidor para generar el QR.', 'aura-suite'), 'error');
            stopRemoteScan();
        });
    }

    function pollRemoteScan() {
        if (!_remoteScanToken) return;
        $.post(cfg.ajaxurl, {
            action : 'aura_library_books_poll_remote_scan',
            nonce  : cfg.nonce,
            token  : _remoteScanToken,
        }, function (res) {
            if (!res.success || !res.data) return;
            if (res.data.status === 'expired') {
                stopRemoteScan();
                showToast(__('La sesión QR expiró. Vuelve a abrirla si deseas escanear.', 'aura-suite'), 'error');
                return;
            }
            if (res.data.status === 'scanned' && res.data.isbn) {
                playScanBeep();
                stopRemoteScan();
                $('#aura-lib-quick-isbn').val(res.data.isbn);
                lookupIsbn(res.data.isbn);
            }
        });
    }

    function stopRemoteScan() {
        if (_remoteScanTimer) {
            clearInterval(_remoteScanTimer);
            _remoteScanTimer = null;
        }
        _remoteScanToken = '';
        var $panel = $('#aura-lib-remote-qr-panel');
        if ($panel.length && $panel.is(':visible')) {
            $panel.slideUp(150);
        }
    }

    function playScanBeep() {
        try {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            var ctx  = new AudioCtx();
            var osc  = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1046.5, ctx.currentTime);
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.14);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
        } catch (e) {}
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES
    // ──────────────────────────────────────────────────────────────
    function showTableError(msg) {
        $('#aura-lib-books-table tbody').html(
            '<tr><td colspan="10" class="aura-lib-table-error">' + escHtml(msg) + '</td></tr>'
        );
    }
    function showSaveMsg(msg, type) {
        var $el = $('.aura-lib-save-msg');
        $el.text(msg).toggleClass('is-error', type === 'error').toggleClass('is-success', type !== 'error').show();
    }
    function escHtml(str) {
        return $('<div>').text(String(str || '')).html();
    }
    function escAttr(str) {
        return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ──────────────────────────────────────────────────────────────
    // F4.4 — RESERVAR LIBRO (Fase 4)
    // ──────────────────────────────────────────────────────────────
    function openReserveModal(bookId, bookTitle) {
        if (!bookId) return;
        $('#aura-lib-reserve-book-id').val(bookId);
        $('#aura-lib-reserve-book-title').text(bookTitle || '');
        $('#aura-lib-reserve-notes').val('');
        showModal('#aura-lib-reserve-modal');
    }

    function saveReservation() {
        var bookId = parseInt($('#aura-lib-reserve-book-id').val(), 10);
        if (!bookId) return;

        var $btn = $('#aura-lib-reserve-save').prop('disabled', true).text('Reservando…');
        $.post(cfg.ajaxurl, {
            action  : 'aura_library_reservations_create',
            nonce   : cfg.nonce,
            book_id : bookId,
            notes   : $('#aura-lib-reserve-notes').val(),
        }, function (res) {
            $btn.prop('disabled', false).text('Confirmar Reserva');
            if (res.success) {
                closeAllModals();
                showToast(res.data.message || __('Reserva creada correctamente.', 'aura-suite'), 'success');
                loadBooks();
            } else {
                showToast(res.data ? res.data.message : __('Error al crear la reserva.', 'aura-suite'), 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Confirmar Reserva');
            showToast(__('Error al procesar la solicitud.', 'aura-suite'), 'error');
        });
    }

})(jQuery);
