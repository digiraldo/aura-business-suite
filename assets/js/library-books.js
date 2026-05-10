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

    if (typeof auraLibraryBooks === 'undefined') return;

    var cfg         = auraLibraryBooks;
    var currentPage = 1;
    var perPage     = 20;
    var totalPages  = 1;
    var _booksTable = null;

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
                    window.prompt('Copia el texto con Ctrl+C y pégalo en tu IA:', text);
                });
            } else {
                window.prompt('Copia el texto con Ctrl+C y pégalo en tu IA:', text);
            }
        });

        function showCopied($btn) {
            var origHtml = $btn.html();
            $btn.addClass('copied').html('<span class="dashicons dashicons-yes"></span> ¡Copiado!');
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

        // Cerrar modales
        $(document).on('click', '.aura-lib-modal-close, .aura-lib-modal-overlay', function () {
            closeAllModals();
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
                0:'000 — Informática y Generalidades',1:'100 — Filosofía y Psicología',
                2:'200 — Religión',3:'300 — Ciencias Sociales',4:'400 — Lengua y Lingüística',
                5:'500 — Ciencias Puras',6:'600 — Tecnología Aplicada',7:'700 — Artes y Recreación',
                8:'800 — Literatura',9:'900 — Historia y Geografía'
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
                available      : '✅ Los lectores podrán solicitarlo en préstamo.',
                unavailable    : '🔴 No aparecerá disponible para nuevos préstamos.',
                reference_only : '📖 Solo consulta en sala; no se puede prestar.',
                lost           : '⚠️ Marcado como perdido; no disponible.',
                withdrawn      : '🗄️ Retirado del catálogo activo.'
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
    }

    // ──────────────────────────────────────────────────────────────
    // CARGA DE LIBROS (AJAX)
    // ──────────────────────────────────────────────────────────────
    function loadBooks() {
        var tbody = $('#aura-lib-books-table tbody');
        tbody.html('<tr><td colspan="9" style="text-align:center;padding:20px;">' +
            '<span class="spinner is-active" style="float:none;"></span> ' +
            escHtml(cfg.txt.loading) + '</td></tr>');

        $.post(cfg.ajaxurl, {
            action    : 'aura_library_books_get_list',
            nonce     : cfg.nonce,
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

    function renderTable(data) {
        var items = data.items || [];
        totalPages = data.total_pages || 1;

        var tbody = $('#aura-lib-books-table tbody');
        if (!items.length) {
            tbody.html('<tr><td colspan="9" style="text-align:center;padding:20px;">' + escHtml(cfg.txt.no_results) + '</td></tr>');
            renderPagination(data);
            return;
        }

        var rows = '';
        $.each(items, function (i, b) {
            var coverHtml = b.cover_thumb_url
                ? '<div class="aura-img-preview"><img src="' + escAttr(b.cover_thumb_url) + '" class="aura-lib-cover-thumb aura-thumb"' +
                  ' alt="' + escAttr(b.title) + '">' +
                  (b.cover_full_url ? '<div class="aura-img-tooltip"><img src="' + escAttr(b.cover_full_url) + '" alt=""></div>' : '') +
                  '</div>'
                : '<span class="dashicons dashicons-format-image aura-lib-cover-placeholder-sm"></span>';

            var statusClass = {
                'available'      : 'aura-lib-badge-green',
                'unavailable'    : 'aura-lib-badge-red',
                'reference_only' : 'aura-lib-badge-blue',
                'lost'           : 'aura-lib-badge-orange',
                'withdrawn'      : 'aura-lib-badge-gray',
                'on_loan'        : 'aura-lib-badge-purple'
            }[b.display_status] || 'aura-lib-badge-gray';

            var copiesColor = {
                'badge-green'  : '#00a32a',
                'badge-yellow' : '#996800',
                'badge-red'    : '#d63638',
            }[b.copies_badge] || '#555';

            var actions = '<div class="aura-lib-actions">';
            actions += '<button class="aura-lib-btn-action aura-lib-btn-detail" data-id="' + parseInt(b.id, 10) + '" title="' + escAttr(cfg.txt.loading) + '"><span class="dashicons dashicons-visibility"></span></button>';
            if (b.can_edit) {
                actions += '<button class="aura-lib-btn-action aura-lib-btn-edit" data-id="' + parseInt(b.id, 10) + '" title="Editar"><span class="dashicons dashicons-edit"></span></button>';
            }
            if (b.can_delete) {
                actions += '<button class="aura-lib-btn-action aura-lib-btn-delete aura-lib-btn-danger" data-id="' + parseInt(b.id, 10) + '" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>';
            }
            // F4.4 — Botón Reservar cuando no hay copias disponibles
            if (cfg.can_loan && parseInt(b.available_copies, 10) === 0 && b.status !== 'reference_only') {
                actions += '<button class="aura-lib-btn-action aura-lib-btn-reserve" data-id="' + parseInt(b.id, 10) + '" data-title="' + escAttr(b.title) + '" title="Reservar"><span class="dashicons dashicons-calendar-alt"></span></button>';
            }
            actions += '</div>';

            // Orden: Portada(0), Título(1), Autor(2), Estado(3), Copias(4), Dewey(5), Categoría(6), Ubicación(7), Acciones(8)
            rows += '<tr data-id="' + parseInt(b.id, 10) + '">' +
                '<td class="aura-lib-col-cover">' + coverHtml + '</td>' +
                '<td class="aura-lib-col-title"><strong>' + escHtml(b.title) + '</strong>' +
                    (b.subtitle ? '<br><small>' + escHtml(b.subtitle) + '</small>' : '') + '</td>' +
                '<td>' + escHtml(b.author) + '</td>' +
                '<td><span class="aura-lib-badge ' + statusClass + '">' + escHtml(b.display_status_label) + '</span></td>' +
                '<td><span class="aura-lib-copies-cell" style="color:' + copiesColor + ';font-weight:600;" title="' +
                    escAttr(parseInt(b.available_copies, 10) + ' disponibles de ' + parseInt(b.total_copies, 10) + ' totales') + '">' +
                    parseInt(b.available_copies, 10) + ' / ' + parseInt(b.total_copies, 10) +
                    (parseInt(b.available_copies, 10) === 0 ? (function() { var lent = parseInt(b.total_copies, 10) - parseInt(b.available_copies, 10); return '<br><small style="font-weight:normal;font-size:10px;">' + escHtml(lent + (lent === 1 ? ' prestada' : ' prestadas')) + '</small>'; })() : '') +
                    '</span></td>' +
                '<td><code>' + escHtml(b.dewey_number || '—') + '</code></td>' +
                '<td>' + escHtml(b.category || '—') + '</td>' +
                '<td>' + escHtml(b.physical_location || '—') +
                    (b.shelf_code ? '<br><small>' + escHtml(b.shelf_code) + '</small>' : '') + '</td>' +
                '<td>' + actions + '</td>' +
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
            responsive  : true,
            paging      : false,
            searching   : false,
            info        : false,
            ordering    : false,
            autoWidth   : false,
            language    : { emptyTable: cfg.txt.no_results },
            columnDefs  : [
                // 0:Portada  1:Título  2:Autor  3:Estado  4:Copias  5:Dewey  6:Categoría  7:Ubicación  8:Acciones
                { responsivePriority: 1,     targets: [1, 3, 8] },
                { responsivePriority: 2,     targets: [0, 2, 4] },
                { responsivePriority: 10000, targets: [5, 6, 7] },
            ],
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
            html += '<button class="aura-lib-page-btn button" data-page="' + Math.max(1, currentPage - 1) + '"' +
                    (currentPage <= 1 ? ' disabled' : '') + '>‹</button>';

            var start = Math.max(1, currentPage - 2);
            var end   = Math.min(tPages, start + 4);
            for (var p = start; p <= end; p++) {
                html += '<button class="aura-lib-page-btn button' + (p === currentPage ? ' button-primary' : '') +
                        '" data-page="' + p + '">' + p + '</button>';
            }

            html += '<button class="aura-lib-page-btn button" data-page="' + Math.min(tPages, currentPage + 1) + '"' +
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
            // Cargar datos del libro vía AJAX
            $.post(cfg.ajaxurl, {
                action : 'aura_library_books_get_detail',
                nonce  : cfg.nonce,
                id     : bookId,
            }, function (res) {
                if (!res.success) { alert(res.data ? res.data.message : cfg.txt.error); return; }
                populateForm(res.data.book);
                showModal('#aura-lib-book-modal');
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
    }

    function resetForm() {
        var form = document.getElementById('aura-lib-book-form');
        if (form) form.reset();
        $('#aura-lib-book-id').val(0);
        $('#aura-lib-f-cover-id').val(0);
        $('#aura-lib-cover-preview')
            .html('<span class="dashicons dashicons-format-image aura-lib-cover-placeholder"></span>' +
                  '<div class="aura-lib-dz-hint"><strong>Arrastra una imagen aquí</strong><span>o</span></div>');
        $('#aura-lib-dropzone').removeClass('has-image dragover');
        $('#aura-lib-btn-remove-cover').hide();
        $('.aura-lib-isbn-error').hide();
        $('#aura-lib-isbn-icon').removeClass('valid invalid');
        $('#aura-lib-dewey-label').text('');
        $('.aura-lib-dewey-pill').removeClass('active');
        $('.aura-lib-char-counter').text('0 / 255');
        $('.aura-lib-field-icon').removeClass('valid invalid');
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
            showToast('El archivo debe ser una imagen.', 'error');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            setDropzoneCover(e.target.result, 0);
        };
        reader.readAsDataURL(file);
        // Note: we can only preview local; to upload properly, we still need WP Media
        showToast('Vista previa local. Usa "Seleccionar imagen" para cargar al servidor.', 'error');
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
            showToast('ISBN inválido. Revisa el campo antes de guardar.', 'error');
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
            showToast(cfg.txt.saved || 'Libro guardado correctamente.', 'success');
            setTimeout(function () { closeAllModals(); loadBooks(); }, 800);
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
            if (!res.success) { alert(res.data ? res.data.message : cfg.txt.error); return; }
            loadBooks();
        }).fail(function () {
            alert(cfg.txt.error);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL DETALLE
    // ──────────────────────────────────────────────────────────────
    function openDetailModal(id) {
        $('#aura-lib-detail-body').html('<span class="spinner is-active" style="float:none;padding:20px;display:block;text-align:center;"></span>');
        showModal('#aura-lib-detail-modal');

        $.post(cfg.ajaxurl, {
            action : 'aura_library_books_get_detail',
            nonce  : cfg.nonce,
            id     : id,
        }, function (res) {
            if (!res.success) {
                $('#aura-lib-detail-body').html('<p class="notice notice-error">' + escHtml(res.data ? res.data.message : cfg.txt.error) + '</p>');
                return;
            }
            $('#aura-lib-detail-title').text(escHtml(res.data.book.title));
            renderDetailBody(res.data);
        }).fail(function () {
            $('#aura-lib-detail-body').html('<p>' + escHtml(cfg.txt.error) + '</p>');
        });
    }

    function renderDetailBody(data) {
        var b       = data.book;
        var loans   = data.loans || [];

        var coverHtml = b.cover_full_url
            ? '<img src="' + escAttr(b.cover_full_url) + '" alt="" class="aura-lib-detail-cover">'
            : '<span class="dashicons dashicons-format-image" style="font-size:60px;color:#ccc;display:block;text-align:center;"></span>';

        var statusClass = {
            'available'      : 'aura-lib-badge-green',
            'unavailable'    : 'aura-lib-badge-red',
            'reference_only' : 'aura-lib-badge-blue',
            'lost'           : 'aura-lib-badge-orange',
            'withdrawn'      : 'aura-lib-badge-gray',
            'on_loan'        : 'aura-lib-badge-purple'
        }[b.display_status || b.status] || 'aura-lib-badge-gray';

        var html = '<div class="aura-lib-detail-wrap">';
        html += '<div class="aura-lib-detail-cover-col">' + coverHtml + '</div>';
        html += '<div class="aura-lib-detail-info-col">';
        html += '<table class="aura-lib-detail-table">';

        var fields = [
            ['Autor', b.author],
            ['Dewey', b.dewey_number || '—'],
            ['ISBN', b.isbn || '—'],
            ['Editorial', b.publisher || '—'],
            ['Año', b.year_published || '—'],
            ['Idioma', b.language],
            ['Categoría', b.category || '—'],
            ['Ubicación', (b.physical_location || '') + (b.shelf_code ? ' · ' + b.shelf_code : '') || '—'],
            ['Ejemplares', b.available_copies + ' / ' + b.total_copies + ' disponibles'],
        ];
        $.each(fields, function (i, f) {
            html += '<tr><th>' + escHtml(f[0]) + '</th><td>' + escHtml(String(f[1] || '')) + '</td></tr>';
        });
        html += '</table>';
        html += '<span class="aura-lib-badge ' + statusClass + '" style="margin-top:8px;display:inline-block;">' + escHtml(b.display_status_label || b.status_label) + '</span>';

        if (b.description) {
            html += '<p style="margin-top:12px;color:#555;">' + escHtml(b.description) + '</p>';
        }

        html += '</div></div>';

        // Historial de préstamos
        html += '<h3 style="margin-top:24px;">Historial de préstamos</h3>';
        if (!loans.length) {
            html += '<p style="color:#999;">Sin préstamos registrados.</p>';
        } else {
            html += '<table class="wp-list-table widefat striped" style="margin-top:8px;"><thead><tr>' +
                '<th>Lector</th><th>Préstamo</th><th>Vencimiento</th><th>Devolución</th><th>Estado</th>' +
                '</tr></thead><tbody>';
            $.each(loans, function (i, l) {
                var lStatusClass = {
                    'active'   : 'aura-lib-badge-blue',
                    'returned' : 'aura-lib-badge-green',
                    'overdue'  : 'aura-lib-badge-red',
                    'lost'     : 'aura-lib-badge-orange',
                    'extended' : 'aura-lib-badge-yellow',
                }[l.status] || 'aura-lib-badge-gray';

                var dueClass = l.is_overdue ? 'style="color:#d63638;font-weight:600;"' : '';
                html += '<tr>' +
                    '<td>' + escHtml(l.borrower) + '</td>' +
                    '<td>' + escHtml(l.loan_date) + '</td>' +
                    '<td ' + dueClass + '>' + escHtml(l.due_date) + '</td>' +
                    '<td>' + escHtml(l.return_date || '—') + '</td>' +
                    '<td><span class="aura-lib-badge ' + lStatusClass + '">' + escHtml(l.status_label) + '</span></td>' +
                '</tr>';
            });
            html += '</tbody></table>';
        }

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
            setDropzoneCover(thumbUrl, attachment.id);
        });

        mediaFrame.open();
    }

    function removeCover() {
        $('#aura-lib-f-cover-id').val(0);
        $('#aura-lib-cover-preview')
            .html('<span class="dashicons dashicons-format-image aura-lib-cover-placeholder"></span>' +
                  '<div class="aura-lib-dz-hint"><strong>Arrastra una imagen aquí</strong><span>o</span></div>');
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
    // UTILIDADES MODALES
    // ──────────────────────────────────────────────────────────────
    function showModal(selector) {
        $(selector).fadeIn(200);
        $('body').addClass('aura-lib-modal-open');
    }
    function closeAllModals() {
        $('.aura-lib-modal').fadeOut(150);
        $('body').removeClass('aura-lib-modal-open');
        $('.aura-lib-cover-tooltip').remove();
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES
    // ──────────────────────────────────────────────────────────────
    function showTableError(msg) {
        $('#aura-lib-books-table tbody').html(
            '<tr><td colspan="9" style="color:#d63638;text-align:center;padding:20px;">' + escHtml(msg) + '</td></tr>'
        );
    }
    function showSaveMsg(msg, type) {
        var $el = $('.aura-lib-save-msg');
        $el.text(msg).css('color', type === 'error' ? '#d63638' : '#00a32a').show();
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
                // Mostrar mensaje de éxito debajo del heading
                var $msg = $('<div class="notice notice-success is-dismissible"><p></p></div>');
                $msg.find('p').text(res.data.message || 'Reserva creada correctamente.');
                $('.wp-heading-inline').first().closest('.wrap').find('hr.wp-header-end').after($msg);
                setTimeout(function () { $msg.fadeOut(); }, 5000);
                loadBooks();
            } else {
                alert(res.data ? res.data.message : 'Error al crear la reserva.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Confirmar Reserva');
            alert('Error al procesar la solicitud.');
        });
    }

})(jQuery);
