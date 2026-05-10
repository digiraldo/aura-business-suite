/**
 * library-loans.js — Préstamos y Devoluciones (Fase 3)
 * Aura Business Suite
 *
 * Maneja: listado DataTables, modal nuevo préstamo (autocomplete libro+lector),
 * modal devolución (multa + finanzas), extender préstamo, detalle de préstamo.
 */
/* global auraLibraryLoans, jQuery */
(function ($) {
    'use strict';

    if (typeof auraLibraryLoans === 'undefined') return;

    var cfg         = auraLibraryLoans;
    var currentPage = 1;
    var perPage     = 20;
    var totalPages  = 1;
    var _loansTable = null;

    // ──────────────────────────────────────────────────────────────
    // INIT
    // ──────────────────────────────────────────────────────────────
    $(document).ready(function () {
        initDateDefaults();
        loadLoans();
        bindEvents();
        initAutocomplete();
    });

    function initDateDefaults() {
        var today = todayStr();
        var due   = addDays(today, cfg.loan_days || 14);
        $('#loan_loan_date').val(today);
        $('#loan_due_date').val(due);
        $('#return_date').val(today);
    }

    // ──────────────────────────────────────────────────────────────
    // EVENTS
    // ──────────────────────────────────────────────────────────────
    function bindEvents() {
        // Nuevo préstamo
        $(document).on('click', '#aura-lib-btn-new-loan', function () {
            openLoanModal();
        });

        // Filtros
        $(document).on('click', '#aura-lib-loans-filter-apply', function () {
            currentPage = 1;
            loadLoans();
        });
        $(document).on('click', '#aura-lib-loans-filter-clear', function () {
            $('#aura-lib-loans-search').val('');
            $('#aura-lib-loans-filter-status').val('');
            $('#aura-lib-loans-filter-from').val('');
            $('#aura-lib-loans-filter-to').val('');
            currentPage = 1;
            loadLoans();
        });
        $(document).on('keydown', '#aura-lib-loans-search', function (e) {
            if (e.key === 'Enter') { currentPage = 1; loadLoans(); }
        });

        // Paginación
        $(document).on('click', '#aura-lib-loans-pagination .aura-lib-page-btn', function () {
            var p = parseInt($(this).data('page'), 10);
            if (p >= 1 && p <= totalPages) { currentPage = p; loadLoans(); }
        });

        // Acciones de tabla
        $(document).on('click', '.aura-lib-loans-btn-return', function () {
            openReturnModal(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.aura-lib-loans-btn-extend', function () {
            openExtendModal(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.aura-lib-loans-btn-detail', function () {
            openDetailModal(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.aura-lib-loans-btn-edit', function () {
            openEditModal(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.aura-lib-loans-btn-cancel', function () {
            cancelLoan(parseInt($(this).data('id'), 10));
        });

        // Guardar edición de préstamo
        $(document).on('submit', '#aura-lib-edit-loan-form', function (e) {
            e.preventDefault();
            saveEditLoan();
        });
        $(document).on('click', '#aura-lib-edit-loan-save', function () {
            saveEditLoan();
        });

        // Modal de extensión — presets y cálculo de nueva fecha
        $(document).on('click', '.aura-lib-extend-preset', function () {
            $('#extend_days').val($(this).data('days'));
            updateExtendPreview();
        });
        $(document).on('input change', '#extend_days', function () {
            updateExtendPreview();
        });
        $(document).on('submit', '#aura-lib-extend-form', function (e) {
            e.preventDefault();
            saveExtend();
        });
        $(document).on('click', '#aura-lib-extend-save', function () {
            saveExtend();
        });

        // Cerrar modales
        $(document).on('click', '.aura-lib-modal-close, .aura-lib-modal-overlay', function () {
            closeAllModals();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeAllModals();
        });

        // Guardar nuevo préstamo (click en botón en lugar de form submit)
        $(document).on('click', '#aura-lib-loan-save', function () {
            saveLoan();
        });
        // También permitir Enter en el form (para accesibilidad)
        $(document).on('submit', '#aura-lib-loan-form', function (e) {
            e.preventDefault();
            saveLoan();
        });

        // Guardar devolución
        $(document).on('submit', '#aura-lib-return-form', function (e) {
            e.preventDefault();
            saveReturn();
        });

        // Recalcular multa al cambiar fecha de devolución real
        $(document).on('change', '#return_date', function () {
            recalculateFine();
        });

        // Sincronizar fecha devolución + actualizar chip de duración
        $(document).on('change', '#loan_loan_date', function () {
            var loanDate = $(this).val();
            if (loanDate) {
                $('#loan_due_date').val(addDays(loanDate, cfg.loan_days || 14));
            }
            updateDurationChip();
        });
        $(document).on('change', '#loan_due_date', function () {
            updateDurationChip();
        });

        // Limpiar libro seleccionado
        $(document).on('click', '#aura-lib-loan-clear-book', function () {
            $('#loan_book_id').val('');
            $('#loan_book_input').val('').focus();
            $('#aura-lib-loan-book-card').hide();
            $('#loan-book-search-row').show();
            $('#loan-book-error').hide();
        });

        // Limpiar lector seleccionado
        $(document).on('click', '#aura-lib-loan-clear-user', function () {
            $('#loan_user_id').val('');
            $('#loan_user_input').val('').focus();
            $('#aura-lib-loan-user-card').hide();
            $('#loan-user-search-row').show();
            $('#loan-user-error').hide();
        });

        // Toggle notas opcionales
        $(document).on('click', '#aura-lib-loan-notes-toggle', function () {
            var $wrap = $('#aura-lib-loan-notes-wrap');
            var $icon = $(this).find('.aura-lib-notes-icon');
            if ($wrap.is(':visible')) {
                $wrap.slideUp(150);
                $icon.removeClass('dashicons-minus').addClass('dashicons-plus-alt2');
            } else {
                $wrap.slideDown(150);
                $icon.removeClass('dashicons-plus-alt2').addClass('dashicons-minus');
                $('#loan_notes').focus();
            }
        });
    }

    // ──────────────────────────────────────────────────────────────
    // AUTOCOMPLETE
    // ──────────────────────────────────────────────────────────────
    function initAutocomplete() {
        // Autocomplete de libros disponibles
        if ($('#loan_book_input').length) {
            $('#loan_book_input').autocomplete({
                source: function (req, res) {
                    $.post(cfg.ajaxurl, {
                        action : 'aura_library_books_search',
                        nonce  : cfg.nonce,
                        q      : req.term,   // PHP expects 'q' not 'term'
                    }, function (data) {
                        if (data.success && data.data) {
                            res($.map(data.data, function (b) {
                                var avail = parseInt(b.available_copies, 10) || 0;
                                var total = parseInt(b.total_copies, 10) || 0;
                                return {
                                    label  : (b.title || '—') + (b.author ? ' — ' + b.author : ''),
                                    value  : b.title || '',
                                    id     : b.id,
                                    title  : b.title || '',
                                    author : b.author || '',
                                    dewey  : b.dewey_number || '',
                                    avail  : avail,
                                    total  : total,
                                };
                            }));
                        } else {
                            res([]);
                        }
                    });
                },
                minLength: 2,
                select: function (event, ui) {
                    event.preventDefault();
                    $('#loan_book_id').val(ui.item.id);
                    $('#loan_book_input').val(ui.item.title);
                    showBookCard(ui.item);
                    $('#loan-book-search-row').hide();
                    $('#loan-book-error').hide();
                },
                change: function () {
                    if (!$('#loan_book_id').val()) {
                        $('#loan_book_input').val('');
                    }
                },
            });
        }

        // Autocomplete de usuarios (lector)
        if ($('#loan_user_input').length) {
            $('#loan_user_input').autocomplete({
                source: function (req, res) {
                    $.post(cfg.ajaxurl, {
                        action : 'aura_library_loans_search_users',
                        nonce  : cfg.nonce,
                        q      : req.term,   // PHP expects 'q' not 'term'
                    }, function (data) {
                        if (data.success && data.data) {
                            res($.map(data.data, function (u) {
                                return {
                                    label : (u.name || u.display_name || '—') +
                                            ' (' + (u.email || u.user_email || '') + ')',
                                    value : u.name || u.display_name || '',
                                    id    : u.id || u.ID,
                                    name  : u.name || u.display_name || '',
                                    email : u.email || u.user_email || '',
                                };
                            }));
                        } else {
                            res([]);
                        }
                    });
                },
                minLength: 2,
                select: function (event, ui) {
                    event.preventDefault();
                    $('#loan_user_id').val(ui.item.id);
                    $('#loan_user_input').val(ui.item.name);
                    showUserCard(ui.item);
                    $('#loan-user-search-row').hide();
                    $('#loan-user-error').hide();
                },
                change: function () {
                    if (!$('#loan_user_id').val()) {
                        $('#loan_user_input').val('');
                    }
                },
            });
        }
    }

    // ── Mostrar tarjeta de libro seleccionado ──────────────────────
    function showBookCard(item) {
        var avail = parseInt(item.avail, 10) || 0;
        var total = parseInt(item.total, 10) || 0;
        $('#loan-book-card-title').text(item.title || '—');
        var sub = [];
        if (item.author) sub.push(item.author);
        if (item.dewey)  sub.push('Dewey: ' + item.dewey);
        $('#loan-book-card-sub').text(sub.join(' · '));

        var $avail = $('#loan-book-card-avail');
        if (avail <= 0) {
            $avail.text('Sin stock').removeClass('avail-ok avail-low').addClass('avail-none');
        } else if (avail === 1) {
            $avail.text('1 disp.').removeClass('avail-ok avail-none').addClass('avail-low');
        } else {
            $avail.text(avail + ' / ' + total + ' disp.').removeClass('avail-low avail-none').addClass('avail-ok');
        }
        $('#loan-book-card-cover').html('<span class="dashicons dashicons-book" style="font-size:22px;color:#9ca3af;"></span>');
        $('#aura-lib-loan-book-card').show();
    }

    // ── Mostrar tarjeta de lector seleccionado ─────────────────────
    function showUserCard(item) {
        var initials = (item.name || '?').charAt(0).toUpperCase();
        $('#loan-user-card-avatar').text(initials);
        $('#loan-user-card-name').text(item.name || '—');
        $('#loan-user-card-email').text(item.email || '');
        $('#aura-lib-loan-user-card').show();
    }

    // ── Actualizar chip de duración ────────────────────────────────
    function updateDurationChip() {
        var from = $('#loan_loan_date').val();
        var to   = $('#loan_due_date').val();
        var $chip = $('#loan-duration-chip');
        if (!from || !to) { $chip.hide(); return; }
        var days = dateDiff(from, to);
        $chip.removeClass('aura-lib-chip-warn aura-lib-chip-error');
        if (days > 0) {
            $chip.text(days + (days === 1 ? ' día' : ' días')).show();
        } else if (days === 0) {
            $chip.text('Mismo día').addClass('aura-lib-chip-warn').show();
        } else {
            $chip.text('Fecha inválida (' + days + 'd)').addClass('aura-lib-chip-error').show();
        }
    }

    // ──────────────────────────────────────────────────────────────
    // CARGAR LISTADO DE PRÉSTAMOS
    // ──────────────────────────────────────────────────────────────
    function loadLoans() {
        showTableLoading();

        $.post(cfg.ajaxurl, {
            action   : 'aura_library_loans_get_list',
            nonce    : cfg.nonce,
            page     : currentPage,
            per_page : perPage,
            search   : $('#aura-lib-loans-search').val() || '',
            status   : $('#aura-lib-loans-filter-status').val() || '',
            date_from: $('#aura-lib-loans-filter-from').val() || '',
            date_to  : $('#aura-lib-loans-filter-to').val() || '',
        }, function (res) {
            if (!res.success) {
                showTableError(res.data ? res.data.message : cfg.txt.error);
                return;
            }
            renderTable(res.data);
        }).fail(function () {
            showTableError(cfg.txt.error);
        });
    }

    function renderTable(data) {
        var items  = data.items || [];
        totalPages = data.total_pages || 1;
        var tbody  = $('#aura-lib-loans-tbody');

        if (!items.length) {
            var cols = getColCount();
            tbody.html('<tr><td colspan="' + cols + '" style="text-align:center;padding:20px;">' +
                escHtml(cfg.txt.no_results) + '</td></tr>');
            renderPagination(data);
            return;
        }

        var rows = '';
        $.each(items, function (i, loan) {
            var statusBadge = renderStatusBadge(loan.status);
            var dueBadge    = renderDueBadge(loan);
            var extBadge    = '<span class="aura-lib-badge aura-lib-badge-gray">' +
                parseInt(loan.extended_count, 10) + ' / ' + cfg.max_extensions + '</span>';
            var fineCell    = '';
            if (cfg.fines_enabled && cfg.can_view_fines) {
                var fineAmt = parseFloat(loan.fine_amount) || 0;
                fineCell = fineAmt > 0
                    ? '<span class="aura-lib-badge aura-lib-badge-orange">$' + fineAmt.toFixed(2) + '</span>'
                    : '—';
            }

            var actions = '<div class="aura-lib-actions">';
            actions += '<button class="aura-lib-btn-action aura-lib-loans-btn-detail" data-id="' +
                parseInt(loan.id, 10) + '" title="Ver detalle">' +
                '<span class="dashicons dashicons-visibility"></span></button>';
            if (cfg.can_return && loan.can_return) {
                actions += '<button class="aura-lib-btn-action aura-lib-loans-btn-return aura-lib-btn-success" data-id="' +
                    parseInt(loan.id, 10) + '" title="Registrar devolución">' +
                    '<span class="dashicons dashicons-yes-alt"></span></button>';
            }
            if (cfg.can_extend && loan.can_extend) {
                actions += '<button class="aura-lib-btn-action aura-lib-loans-btn-extend" data-id="' +
                    parseInt(loan.id, 10) + '" title="Extender préstamo">' +
                    '<span class="dashicons dashicons-clock"></span></button>';
            }
            if (cfg.can_edit && loan.can_edit) {
                actions += '<button class="aura-lib-btn-action aura-lib-loans-btn-edit" data-id="' +
                    parseInt(loan.id, 10) + '" title="Editar préstamo">' +
                    '<span class="dashicons dashicons-edit"></span></button>';
            }
            if (cfg.can_delete && loan.can_cancel) {
                actions += '<button class="aura-lib-btn-action aura-lib-loans-btn-cancel aura-lib-btn-danger" data-id="' +
                    parseInt(loan.id, 10) + '" title="Cancelar préstamo">' +
                    '<span class="dashicons dashicons-no"></span></button>';
            }
            actions += '</div>';

            var coverHtml = loan.cover_thumb_url
                ? '<div class="aura-img-preview">' +
                  '<img src="' + escAttr(loan.cover_thumb_url) + '" class="aura-lib-cover-thumb aura-thumb" alt="' + escAttr(loan.book_title || '') + '">' +
                  (loan.cover_full_url ? '<div class="aura-img-tooltip"><img src="' + escAttr(loan.cover_full_url) + '" alt=""></div>' : '') +
                  '</div>'
                : '<span class="dashicons dashicons-format-image aura-lib-cover-placeholder-sm"></span>';

            rows += '<tr data-id="' + parseInt(loan.id, 10) + '">';
            rows += '<td><small>#' + parseInt(loan.id, 10) + '</small></td>';
            rows += '<td class="aura-lib-col-book">' +
                '<div class="aura-lib-loan-book-cell">' +
                '<div class="aura-lib-loan-book-cover">' + coverHtml + '</div>' +
                '<div class="aura-lib-loan-book-info">' +
                '<strong>' + escHtml(loan.book_title || '—') + '</strong>' +
                (loan.dewey_number ? '<br><code style="font-size:11px;">' + escHtml(loan.dewey_number) + '</code>' : '') +
                '</div></div>' +
                '</td>';
            if (cfg.can_view_all) {
                rows += '<td>' + escHtml(loan.borrower_name || '—') + '</td>';
            }
            rows += '<td>' + escHtml(loan.loan_date || '—') + '</td>';
            rows += '<td>' + dueBadge + '</td>';
            rows += '<td>' + statusBadge + '</td>';
            rows += '<td style="text-align:center;">' + extBadge + '</td>';
            if (cfg.fines_enabled && cfg.can_view_fines) {
                rows += '<td style="text-align:right;">' + fineCell + '</td>';
            }
            rows += '<td>' + actions + '</td>';
            rows += '</tr>';
        });

        tbody.html(rows);
        renderPagination(data);
        initDataTable();
    }

    function initDataTable() {
        if (_loansTable) {
            try { _loansTable.destroy(); } catch (e) {}
            _loansTable = null;
        }
        // Las columnas varían según permisos del usuario:
        // BASE (sin Lector, sin Multa): #(0) Libro(1) F.Prést(2) F.Dev(3) Estado(4) Ext(5) Acc(6)
        // Con Lector:                  #(0) Libro(1) Lector(2) F.Prést(3) F.Dev(4) Estado(5) Ext(6) Acc(7)
        // Con Lector + Multa:          #(0) Libro(1) Lector(2) F.Prést(3) F.Dev(4) Estado(5) Ext(6) Multa(7) Acc(8)
        var totalCols = getColCount();
        var lastCol   = totalCols - 1;
        // Prioridades fijas por nombre lógico:
        var colDefs = [];
        // Siempre: # → baja prioridad, Libro → 1, Estado → 1, Acciones → 1
        colDefs.push({ responsivePriority: 10000, targets: [0] });           // #
        colDefs.push({ responsivePriority: 1,     targets: [1] });           // Libro
        colDefs.push({ responsivePriority: 1,     targets: [lastCol] });     // Acciones
        if (cfg.can_view_all && cfg.fines_enabled && cfg.can_view_fines) {
            // 9 cols: 0=#  1=Libro  2=Lector  3=F.Prést  4=F.Dev  5=Estado  6=Ext  7=Multa  8=Acc
            colDefs.push({ responsivePriority: 2,     targets: [2, 5] });    // Lector, Estado
            colDefs.push({ responsivePriority: 3,     targets: [4] });       // F.Dev
            colDefs.push({ responsivePriority: 10000, targets: [3, 6, 7] }); // F.Prést, Ext, Multa
        } else if (cfg.can_view_all) {
            // 8 cols: 0=#  1=Libro  2=Lector  3=F.Prést  4=F.Dev  5=Estado  6=Ext  7=Acc
            colDefs.push({ responsivePriority: 2,     targets: [2, 5] });    // Lector, Estado
            colDefs.push({ responsivePriority: 3,     targets: [4] });       // F.Dev
            colDefs.push({ responsivePriority: 10000, targets: [3, 6] });    // F.Prést, Ext
        } else if (cfg.fines_enabled && cfg.can_view_fines) {
            // 8 cols: 0=#  1=Libro  2=F.Prést  3=F.Dev  4=Estado  5=Ext  6=Multa  7=Acc
            colDefs.push({ responsivePriority: 2,     targets: [4] });       // Estado
            colDefs.push({ responsivePriority: 3,     targets: [3] });       // F.Dev
            colDefs.push({ responsivePriority: 10000, targets: [2, 5, 6] }); // F.Prést, Ext, Multa
        } else {
            // 7 cols: 0=#  1=Libro  2=F.Prést  3=F.Dev  4=Estado  5=Ext  6=Acc
            colDefs.push({ responsivePriority: 2,     targets: [4] });       // Estado
            colDefs.push({ responsivePriority: 3,     targets: [3] });       // F.Dev
            colDefs.push({ responsivePriority: 10000, targets: [2, 5] });    // F.Prést, Ext
        }
        _loansTable = $('#aura-lib-loans-table').DataTable({
            responsive  : true,
            paging      : false,
            searching   : false,
            info        : false,
            ordering    : false,
            autoWidth   : false,
            language    : { emptyTable: cfg.txt.no_results },
            columnDefs  : colDefs,
        });
    }

    function renderStatusBadge(status) {
        var classMap = {
            'active'    : 'aura-lib-badge-green',
            'overdue'   : 'aura-lib-badge-red',
            'extended'  : 'aura-lib-badge-blue',
            'returned'  : 'aura-lib-badge-gray',
            'lost'      : 'aura-lib-badge-orange',
            'cancelled' : 'aura-lib-badge-gray',
        };
        var cls   = classMap[status] || 'aura-lib-badge-gray';
        var label = (cfg.txt.status_labels && cfg.txt.status_labels[status]) || status;
        return '<span class="aura-lib-badge ' + cls + '">' + escHtml(label) + '</span>';
    }

    function renderDueBadge(loan) {
        if (!loan.due_date) return '—';
        var isOverdue = (loan.status === 'overdue') ||
            (['active', 'extended'].indexOf(loan.status) !== -1 && loan.due_date < todayStr());
        if (isOverdue) {
            return '<span class="aura-lib-badge aura-lib-badge-red">' + escHtml(loan.due_date) + '</span>';
        }
        return escHtml(loan.due_date);
    }

    function getColCount() {
        return 7 + (cfg.can_view_all ? 1 : 0) + (cfg.fines_enabled && cfg.can_view_fines ? 1 : 0);
    }

    function renderPagination(data) {
        var total  = data.total || 0;
        totalPages = data.total_pages || 1;
        var pag    = $('#aura-lib-loans-pagination');

        var html = '<span class="aura-lib-count">' +
            sprintf(cfg.txt.n_items, total) + '</span>';

        if (totalPages > 1) {
            html += '<div class="aura-lib-page-btns">';
            html += '<button class="aura-lib-page-btn button" data-page="' + Math.max(1, currentPage - 1) + '"' +
                    (currentPage <= 1 ? ' disabled' : '') + '>‹</button>';
            var start = Math.max(1, currentPage - 2);
            var end   = Math.min(totalPages, start + 4);
            for (var p = start; p <= end; p++) {
                html += '<button class="aura-lib-page-btn button' + (p === currentPage ? ' button-primary' : '') +
                        '" data-page="' + p + '">' + p + '</button>';
            }
            html += '<button class="aura-lib-page-btn button" data-page="' + Math.min(totalPages, currentPage + 1) + '"' +
                    (currentPage >= totalPages ? ' disabled' : '') + '>›</button>';
            html += '</div>';
        }

        pag.html(html);
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL: NUEVO PRÉSTAMO
    // ──────────────────────────────────────────────────────────────
    function openLoanModal() {
        document.getElementById('aura-lib-loan-form').reset();

        // Resetear libro
        $('#loan_book_id').val('');
        $('#loan_book_input').val('');
        $('#aura-lib-loan-book-card').hide();
        $('#loan-book-search-row').show();
        $('#loan-book-error').hide();

        // Resetear lector
        $('#loan_user_id').val('');
        $('#loan_user_input').val('');
        $('#aura-lib-loan-user-card').hide();
        $('#loan-user-search-row').show();
        $('#loan-user-error').hide();

        // Resetear notas
        $('#aura-lib-loan-notes-wrap').hide();
        $('#aura-lib-loan-notes-toggle .aura-lib-notes-icon')
            .removeClass('dashicons-minus').addClass('dashicons-plus-alt2');

        // Resetear error general
        $('#aura-lib-loan-modal-error').hide();

        // Resetear botón
        var $btn = $('#aura-lib-loan-save').prop('disabled', false);
        $btn.find('.aura-lib-btn-label').show();
        $btn.find('.aura-lib-btn-loading').hide();

        initDateDefaults();
        updateDurationChip();

        showModal('#aura-lib-loan-modal');
        setTimeout(function () { $('#loan_book_input').focus(); }, 100);
    }

    function saveLoan() {
        // Limpiar errores previos
        $('#loan-book-error, #loan-user-error, #aura-lib-loan-modal-error').hide();

        var bookId = parseInt($('#loan_book_id').val(), 10);
        if (!bookId) {
            $('#loan-book-error').show();
            $('#loan_book_input').focus();
            return;
        }

        var userId = parseInt($('#loan_user_id').val(), 10);
        if (cfg.can_view_all && !userId) {
            $('#loan-user-error').show();
            $('#loan_user_input').focus();
            return;
        }

        var loanDate = $('#loan_loan_date').val();
        var dueDate  = $('#loan_due_date').val();
        if (!loanDate || !dueDate) {
            $('#aura-lib-loan-modal-error').text('⚠ Las fechas son obligatorias.').show();
            return;
        }
        if (dateDiff(loanDate, dueDate) <= 0) {
            $('#aura-lib-loan-modal-error').text('⚠ La fecha límite debe ser posterior a la fecha de préstamo.').show();
            return;
        }

        var postData = {
            action            : 'aura_library_loans_create',
            nonce             : cfg.nonce,
            book_id           : bookId,
            borrower_user_id  : userId || 0,
            loan_date         : loanDate,
            due_date          : dueDate,
            notes             : $('#loan_notes').val(),
        };

        // Estado de carga en botón
        var $btn = $('#aura-lib-loan-save').prop('disabled', true);
        $btn.find('.aura-lib-btn-label').hide();
        $btn.find('.aura-lib-btn-loading').show();

        $.post(cfg.ajaxurl, postData, function (res) {
            $btn.prop('disabled', false);
            $btn.find('.aura-lib-btn-label').show();
            $btn.find('.aura-lib-btn-loading').hide();
            if (res.success) {
                closeAllModals();
                showNotice('success', cfg.txt.saved);
                loadLoans();
            } else {
                var msg = res.data ? res.data.message : cfg.txt.error;
                $('#aura-lib-loan-modal-error').text('⚠ ' + msg).show();
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $btn.find('.aura-lib-btn-label').show();
            $btn.find('.aura-lib-btn-loading').hide();
            $('#aura-lib-loan-modal-error').text('⚠ ' + cfg.txt.error).show();
        });
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL: DEVOLUCIÓN
    // ──────────────────────────────────────────────────────────────
    var currentLoan = null;

    function openReturnModal(loanId) {
        currentLoan = null;
        $('#return_loan_id').val(loanId);
        $('#return_date').val(todayStr());
        $('#return_condition').val('good');
        $('#return_notes').val('');
        $('#return_pay_fine').prop('checked', false);
        $('#return_to_finance').prop('checked', false);
        $('#aura-lib-fine-panel').hide();
        $('#aura-lib-return-info').html('<span class="spinner is-active" style="float:none;"></span>');
        showModal('#aura-lib-return-modal');

        // Cargar detalle del préstamo para mostrar info
        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-return-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                return;
            }
            var loan = res.data.loan;
            currentLoan = loan;
            var info = '<table class="aura-lib-detail-table">' +
                '<tr><th>Libro</th><td>' + escHtml(loan.book_title) + '</td></tr>' +
                '<tr><th>Lector</th><td>' + escHtml(loan.borrower_name) + '</td></tr>' +
                '<tr><th>Fecha préstamo</th><td>' + escHtml(loan.loan_date) + '</td></tr>' +
                '<tr><th>Devolución prevista</th><td>' + escHtml(loan.due_date) + '</td></tr>' +
                '</table>';
            $('#aura-lib-return-info').html(info);
            recalculateFine();
        });
    }

    function recalculateFine() {
        if (!currentLoan || !cfg.fines_enabled) return;
        var returnDate = $('#return_date').val();
        if (!returnDate || !currentLoan.due_date) return;

        var dueDate    = currentLoan.due_date;
        var overdue    = dateDiff(dueDate, returnDate); // days late
        var graceDays  = parseInt(currentLoan.grace_days || 1, 10);
        var perDay     = parseFloat(currentLoan.fine_per_day || 0);
        var maxFine    = parseFloat(currentLoan.fine_max || 0);

        if (overdue > 0 && overdue > graceDays && perDay > 0) {
            var fineAmt = (overdue - graceDays) * perDay;
            if (maxFine > 0) fineAmt = Math.min(fineAmt, maxFine);
            $('#return-overdue-days').text(overdue);
            $('#return-fine-amount').text('$' + fineAmt.toFixed(2));
            $('#aura-lib-fine-panel').show();
        } else {
            $('#aura-lib-fine-panel').hide();
        }
    }

    function saveReturn() {
        var loanId = parseInt($('#return_loan_id').val(), 10);
        if (!loanId) return;

        var data = {
            action         : 'aura_library_loans_return_book',
            nonce          : cfg.nonce,
            loan_id        : loanId,
            return_date    : $('#return_date').val(),
            return_condition: $('#return_condition').val(),
            return_notes   : $('#return_notes').val(),
            pay_fine       : $('#return_pay_fine').is(':checked') ? 1 : 0,
            to_finance     : $('#return_to_finance').is(':checked') ? 1 : 0,
        };

        var $btn = $('#aura-lib-return-save').prop('disabled', true).text('Guardando…');

        $.post(cfg.ajaxurl, data, function (res) {
            $btn.prop('disabled', false).text('Confirmar Devolución');
            if (res.success) {
                closeAllModals();
                showNotice('success', cfg.txt.returned);
                loadLoans();
            } else {
                alert(res.data ? res.data.message : cfg.txt.error);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Confirmar Devolución');
            alert(cfg.txt.error);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL: EXTENDER PRÉSTAMO
    // ──────────────────────────────────────────────────────────────
    var _extendCurrentDue = null;

    function openExtendModal(loanId) {
        _extendCurrentDue = null;
        $('#extend_loan_id').val(loanId);
        $('#extend_days').val(cfg.extension_days || 7);
        $('#extend-new-due').text('—');
        $('#aura-lib-extend-error').hide();
        $('#aura-lib-extend-info').html('<span class="spinner is-active" style="float:none;"></span>');
        showModal('#aura-lib-extend-modal');

        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-extend-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                return;
            }
            var loan = res.data.loan;
            _extendCurrentDue = loan.due_date || null;
            var extended = parseInt(loan.extended_count, 10) || 0;
            var info = '<table class="aura-lib-detail-table">' +
                '<tr><th>Libro</th><td>' + escHtml(loan.book_title || '—') + '</td></tr>' +
                '<tr><th>Lector</th><td>' + escHtml(loan.borrower_name || '—') + '</td></tr>' +
                '<tr><th>Fecha límite actual</th><td><strong>' + escHtml(loan.due_date || '—') + '</strong></td></tr>' +
                '<tr><th>Extensiones usadas</th><td>' + extended + ' / ' + cfg.max_extensions + '</td></tr>' +
                '</table>';
            $('#aura-lib-extend-info').html(info);
            updateExtendPreview();
        }).fail(function () {
            $('#aura-lib-extend-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
        });
    }

    function updateExtendPreview() {
        if (!_extendCurrentDue) { $('#extend-new-due').text('—'); return; }
        var days = parseInt($('#extend_days').val(), 10);
        if (isNaN(days) || days < 1 || days > 180) { $('#extend-new-due').text('—'); return; }
        var base = new Date(_extendCurrentDue + 'T00:00:00');
        base.setDate(base.getDate() + days);
        var y = base.getFullYear();
        var m = String(base.getMonth() + 1).padStart(2, '0');
        var d = String(base.getDate()).padStart(2, '0');
        $('#extend-new-due').text(y + '-' + m + '-' + d);
    }

    function saveExtend() {
        var loanId = parseInt($('#extend_loan_id').val(), 10);
        if (!loanId) return;
        var days = parseInt($('#extend_days').val(), 10);
        if (isNaN(days) || days < 1 || days > 180) {
            $('#aura-lib-extend-error').text('⚠ Ingresa un número de días válido (1–180).').show();
            return;
        }
        $('#aura-lib-extend-error').hide();
        var $btn = $('#aura-lib-extend-save').prop('disabled', true).text('Guardando…');

        $.post(cfg.ajaxurl, {
            action  : 'aura_library_loans_extend',
            nonce   : cfg.nonce,
            loan_id : loanId,
            days    : days,
        }, function (res) {
            $btn.prop('disabled', false).text('Confirmar Extensión');
            if (res.success) {
                closeAllModals();
                showNotice('success', cfg.txt.extended);
                loadLoans();
            } else {
                $('#aura-lib-extend-error').text('⚠ ' + (res.data ? res.data.message : cfg.txt.error)).show();
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Confirmar Extensión');
            $('#aura-lib-extend-error').text('⚠ ' + cfg.txt.error).show();
        });
    }

    // legacy stub — replaced by openExtendModal
    function extendLoan(loanId) {
        openExtendModal(loanId);
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL: EDITAR PRÉSTAMO
    // ──────────────────────────────────────────────────────────────
    function openEditModal(loanId) {
        $('#edit_loan_id').val(loanId);
        $('#aura-lib-edit-loan-info').html('<span class="spinner is-active" style="float:none;"></span>');
        $('#aura-lib-edit-loan-error').hide();
        $('#edit_loan_date, #edit_due_date, #edit_notes').val('');

        showModal('#aura-lib-edit-loan-modal');

        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-edit-loan-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                return;
            }
            var loan = res.data.loan;
            var info = '<table class="aura-lib-detail-table">' +
                '<tr><th>Libro</th><td>' + escHtml(loan.book_title || '—') + '</td></tr>' +
                '<tr><th>Lector</th><td>' + escHtml(loan.borrower_name || '—') + '</td></tr>' +
                '</table>';
            $('#aura-lib-edit-loan-info').html(info);
            $('#edit_loan_date').val(loan.loan_date || '');
            $('#edit_due_date').val(loan.due_date   || '');
            $('#edit_status').val(loan.status       || 'active');
            $('#edit_notes').val(loan.notes         || '');
        }).fail(function () {
            $('#aura-lib-edit-loan-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
        });
    }

    function saveEditLoan() {
        var loanId   = parseInt($('#edit_loan_id').val(), 10);
        var loanDate = $('#edit_loan_date').val();
        var dueDate  = $('#edit_due_date').val();

        if (!loanId) return;

        $('#aura-lib-edit-loan-error').hide();

        if (!loanDate || !dueDate) {
            $('#aura-lib-edit-loan-error').text('⚠ Las fechas son obligatorias.').show();
            return;
        }
        if (dueDate < loanDate) {
            $('#aura-lib-edit-loan-error').text('⚠ La fecha límite debe ser posterior a la fecha de préstamo.').show();
            return;
        }

        var $btn = $('#aura-lib-edit-loan-save').prop('disabled', true).text('Guardando…');

        $.post(cfg.ajaxurl, {
            action   : 'aura_library_loans_update',
            nonce    : cfg.nonce,
            loan_id  : loanId,
            loan_date: loanDate,
            due_date : dueDate,
            status   : $('#edit_status').val(),
            notes    : $('#edit_notes').val(),
        }, function (res) {
            $btn.prop('disabled', false).text('Guardar Cambios');
            if (res.success) {
                closeAllModals();
                showNotice('success', cfg.txt.updated || 'Préstamo actualizado.');
                loadLoans();
            } else {
                var msg = res.data ? res.data.message : cfg.txt.error;
                $('#aura-lib-edit-loan-error').text('⚠ ' + msg).show();
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Guardar Cambios');
            $('#aura-lib-edit-loan-error').text('⚠ ' + cfg.txt.error).show();
        });
    }

    // ──────────────────────────────────────────────────────────────
    // CANCELAR PRÉSTAMO
    // ──────────────────────────────────────────────────────────────
    function cancelLoan(loanId) {
        var msg = cfg.txt.confirm_cancel || '¿Cancelar este préstamo? Esta acción no se puede deshacer.';
        if (!confirm(msg)) return;

        $.post(cfg.ajaxurl, {
            action  : 'aura_library_loans_cancel',
            nonce   : cfg.nonce,
            loan_id : loanId,
        }, function (res) {
            if (res.success) {
                showNotice('success', cfg.txt.cancelled || 'Préstamo cancelado.');
                loadLoans();
            } else {
                alert(res.data ? res.data.message : cfg.txt.error);
            }
        }).fail(function () {
            alert(cfg.txt.error);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // MODAL: DETALLE
    // ──────────────────────────────────────────────────────────────
    function openDetailModal(loanId) {
        $('#aura-lib-loan-detail-body').html('<span class="spinner is-active" style="float:none;"></span>');
        showModal('#aura-lib-loan-detail-modal');

        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-loan-detail-body').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                return;
            }
            renderDetail(res.data.loan);
        });
    }

    function renderDetail(loan) {
        var statusBadge = renderStatusBadge(loan.status);
        var fineRow = '';
        if (cfg.fines_enabled && cfg.can_view_fines && parseFloat(loan.fine_current || 0) > 0) {
            fineRow = '<tr><th>Multa acumulada</th><td>' +
                '<span class="aura-lib-badge aura-lib-badge-orange">$' +
                parseFloat(loan.fine_current).toFixed(2) + '</span></td></tr>';
        }

        var html = '<table class="aura-lib-detail-table wp-list-table widefat">' +
            '<tr><th>Libro</th><td><strong>' + escHtml(loan.book_title) + '</strong>' +
                (loan.dewey_number ? ' <code>' + escHtml(loan.dewey_number) + '</code>' : '') + '</td></tr>' +
            '<tr><th>Lector</th><td>' + escHtml(loan.borrower_name) + '</td></tr>' +
            '<tr><th>Fecha préstamo</th><td>' + escHtml(loan.loan_date) + '</td></tr>' +
            '<tr><th>Devolución prevista</th><td>' + escHtml(loan.due_date) + '</td></tr>' +
            (loan.return_date ? '<tr><th>Devuelto el</th><td>' + escHtml(loan.return_date) + '</td></tr>' : '') +
            '<tr><th>Estado</th><td>' + statusBadge + '</td></tr>' +
            '<tr><th>Extensiones</th><td>' + parseInt(loan.extended_count, 10) + ' de ' + cfg.max_extensions + '</td></tr>' +
            fineRow +
            (loan.notes ? '<tr><th>Notas</th><td>' + escHtml(loan.notes) + '</td></tr>' : '') +
            (loan.return_notes ? '<tr><th>Notas devolución</th><td>' + escHtml(loan.return_notes) + '</td></tr>' : '') +
            (loan.return_condition ? '<tr><th>Condición</th><td>' +
                escHtml((cfg.txt.condition_labels && cfg.txt.condition_labels[loan.return_condition]) || loan.return_condition) +
                '</td></tr>' : '') +
            '</table>';

        // Botones de acción en detalle
        var btns = '<div class="aura-lib-modal-footer">';
        if (cfg.can_return && loan.can_return) {
            btns += '<button type="button" class="button button-primary aura-lib-loans-btn-return" data-id="' +
                parseInt(loan.id, 10) + '">Registrar devolución</button> ';
        }
        if (cfg.can_extend && loan.can_extend) {
            btns += '<button type="button" class="button aura-lib-loans-btn-extend" data-id="' +
                parseInt(loan.id, 10) + '">Extender</button> ';
        }
        if (cfg.can_edit && loan.can_edit) {
            btns += '<button type="button" class="button aura-lib-loans-btn-edit" data-id="' +
                parseInt(loan.id, 10) + '">Editar</button> ';
        }
        if (cfg.can_delete && loan.can_cancel) {
            btns += '<button type="button" class="button aura-lib-loans-btn-cancel" data-id="' +
                parseInt(loan.id, 10) + '" style="color:#d63638;">Cancelar préstamo</button> ';
        }
        btns += '<button type="button" class="button aura-lib-modal-close">Cerrar</button>';
        btns += '</div>';

        $('#aura-lib-loan-detail-body').html(html + btns);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: MODALES
    // ──────────────────────────────────────────────────────────────
    function showModal(selector) {
        $(selector).fadeIn(200);
        $('body').addClass('aura-lib-modal-open');
    }

    function closeAllModals() {
        $('.aura-lib-modal').fadeOut(150);
        $('body').removeClass('aura-lib-modal-open');
        currentLoan = null;
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: TABLA
    // ──────────────────────────────────────────────────────────────
    function showTableLoading() {
        var cols = getColCount();
        $('#aura-lib-loans-tbody').html(
            '<tr><td colspan="' + cols + '" style="text-align:center;padding:20px;">' +
            '<span class="spinner is-active" style="float:none;"></span> ' +
            escHtml(cfg.txt.loading) + '</td></tr>'
        );
    }

    function showTableError(msg) {
        var cols = getColCount();
        $('#aura-lib-loans-tbody').html(
            '<tr><td colspan="' + cols + '" style="text-align:center;padding:20px;color:#d63638;">' +
            escHtml(msg) + '</td></tr>'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: NOTIFICACIONES
    // ──────────────────────────────────────────────────────────────
    function showNotice(type, msg) {
        var $n = $('#aura-lib-loans-notice');
        $n.attr('class', 'notice notice-' + type + ' is-dismissible')
          .html('<p>' + escHtml(msg) + '</p>')
          .show();
        setTimeout(function () { $n.fadeOut(); }, 4000);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: FECHA
    // ──────────────────────────────────────────────────────────────
    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' +
               pad2(d.getMonth() + 1) + '-' +
               pad2(d.getDate());
    }

    function addDays(dateStr, days) {
        var d = new Date(dateStr);
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    /** Returns number of days returnDate is AFTER dueDate (negative if early). */
    function dateDiff(dueDate, returnDate) {
        var d1 = new Date(dueDate);
        var d2 = new Date(returnDate);
        return Math.floor((d2 - d1) / 86400000);
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: ESCAPE + STRING
    // ──────────────────────────────────────────────────────────────
    function escHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escAttr(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function sprintf(tpl, val) {
        return String(tpl).replace('%s', String(val));
    }

}(jQuery));
