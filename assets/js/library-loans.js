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
    var _loansMap   = {};

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

        // Expandir / Colapsar fila hija con información completa del préstamo (+ / -)
        $(document).on('click', '#aura-lib-loans-table .aura-lib-row-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $tr  = $btn.closest('tr');
            var id   = parseInt($btn.data('id'), 10);
            var loan = _loansMap[id];
            if (!loan) return;

            var isExpanded = $btn.hasClass('is-active');
            if (_loansTable && typeof _loansTable.row === 'function') {
                var dtRow = _loansTable.row($tr);
                if (dtRow.child.isShown()) {
                    dtRow.child.hide();
                    $tr.removeClass('shown is-expanded');
                    $btn.removeClass('is-active').attr('aria-expanded', 'false')
                        .html('<span class="dashicons dashicons-plus-alt2"></span>');
                } else {
                    dtRow.child(renderLoanChildCard(loan), 'aura-dt-child-row aura-child-row is-open').show();
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
                    $tr.after('<tr class="aura-child-row is-open"><td colspan="' + getColCount() + '">' + renderLoanChildCard(loan) + '</td></tr>');
                    $tr.addClass('shown is-expanded');
                    $btn.addClass('is-active').attr('aria-expanded', 'true')
                        .html('<span class="dashicons dashicons-minus"></span>');
                }
            }
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

        // Cerrar modales (protección 2 fases contra drag-selection + soporte modal apilado)
        var _loansBackdropDown = null;
        $(document).on('mousedown', '.aura-lib-modal', function (e) {
            _loansBackdropDown = (e.target === this) ? this : null;
        });
        $(document).on('click', '.aura-lib-modal-close', function () {
            closeAllModals();
        });
        $(document).on('click', '.aura-lib-bp-close', function () {
            closeBookPickerModal();
        });
        $(document).on('click', '.aura-lib-modal', function (e) {
            if (_loansBackdropDown === this && e.target === this) {
                if (this.id === 'aura-lib-book-picker-modal') {
                    closeBookPickerModal();
                } else {
                    closeAllModals();
                }
            }
            _loansBackdropDown = null;
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                if ($('#aura-lib-book-picker-modal').is(':visible')) {
                    closeBookPickerModal();
                } else {
                    closeAllModals();
                }
            }
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

        // Limpiar lector / usuario seleccionado
        $(document).on('click', '#aura-lib-loan-clear-user', function () {
            $('#loan_user_id').val('');
            $('#loan_user_input').val('');
            $('#aura-lib-loan-user-card').hide();
            $('#loan-reader-selector-wrap').show();
            $('#loan-user-search-row').show();
            $('#loan-user-error').hide();
            if (window.AuraContactSelector) {
                window.AuraContactSelector.clear('#loan-contact-selector');
            }
            if ($('#loan-contact-selector .aura-cs-search-input').length) {
                $('#loan-contact-selector .aura-cs-search-input').focus();
            } else {
                $('#loan_user_input').focus();
            }
        });

        // Botón: Abrir Explorador Visual del Catálogo de Libros
        $(document).on('click', '#aura-lib-btn-open-book-picker', function () {
            openBookPickerModal();
        });
        var _bpTimer = null;
        $(document).on('input', '#aura-lib-bp-search', function () {
            clearTimeout(_bpTimer);
            _bpTimer = setTimeout(function () { bpPage = 1; loadBookPicker(); }, 280);
        });
        $(document).on('change', '#aura-lib-bp-dewey, #aura-lib-bp-avail-only', function () {
            bpPage = 1;
            loadBookPicker();
        });
        $(document).on('click', '#aura-lib-bp-pagination .aura-lib-page-btn', function () {
            var p = parseInt($(this).data('page'), 10);
            if (p >= 1) { bpPage = p; loadBookPicker(); }
        });
        $(document).on('click', '.aura-lib-bp-select-btn', function () {
            var $card = $(this).closest('.aura-lib-bp-card');
            var bookData = $card.data('book');
            if (!bookData) return;
            selectBookFromPicker(bookData);
        });

        // Botón: Abrir Modal Global de Todos los Usuarios (AuraThirdPartySelector)
        $(document).on('click', '#aura-lib-btn-open-user-picker', function () {
            if (!window.AuraThirdPartySelector || typeof window.AuraThirdPartySelector.openExplorer !== 'function') {
                showNotice('error', 'El directorio de usuarios no está disponible en esta pantalla.');
                return;
            }
            window.AuraThirdPartySelector.openExplorer({
                defaultTab: 'employee',
                allowUsers: true,
                onSelect: function (item) {
                    if (!item) return;
                    var pickedUid = parseInt(item.wp_user_id || item.user_id || (item.is_wp_user ? item.id : 0), 10) || 0;
                    if (!pickedUid) {
                        showNotice('warning', 'Selecciona un usuario del sistema WordPress con cuenta activa.');
                        return;
                    }
                    if (window.AuraContactSelector) {
                        window.AuraContactSelector.clear('#loan-contact-selector');
                    }
                    $('#loan_user_id').val(pickedUid);
                    $('#loan_user_input').val(item.name || '');
                    showUserCard({
                        name: item.name || item.commercial_name || '—',
                        email: item.email || (item.login ? '@' + item.login : ''),
                        avatar_url: item.avatar_url || item.logo_url || '',
                        role_label: item.accounting_role_label || 'Usuario WP',
                    });
                    $('#loan-reader-selector-wrap').hide();
                    $('#loan-user-error').hide();
                }
            });
        });

        // Eventos del Selector Universal de Contactos
        $(document).on('aura:contact-selected', '#loan-contact-selector', function () {
            $('#aura-lib-loan-user-card').hide();
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
                                    label     : (b.title || '—') + (b.author ? ' — ' + b.author : ''),
                                    value     : b.title || '',
                                    id        : b.id,
                                    title     : b.title || '',
                                    author    : b.author || '',
                                    dewey     : b.dewey_number || '',
                                    cover_url : b.cover_thumb_url || '',
                                    avail     : avail,
                                    total     : total,
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
        if (item.cover_url) {
            $('#loan-book-card-cover').html('<img src="' + escAttr(item.cover_url) + '" alt="' + escAttr(item.title || '') + '" class="aura-lib-loan-card-thumb">');
        } else {
            $('#loan-book-card-cover').html('<span class="dashicons dashicons-book" style="font-size:22px;color:#9ca3af;"></span>');
        }
        $('#aura-lib-loan-book-card').show();
    }

    // ── Mostrar tarjeta de lector seleccionado ─────────────────────
    function showUserCard(item) {
        var name = item.name || '—';
        var initials = name.charAt(0).toUpperCase();
        if (item.avatar_url) {
            $('#loan-user-card-avatar').html('<img src="' + escAttr(item.avatar_url) + '" alt="' + escAttr(name) + '">');
        } else {
            $('#loan-user-card-avatar').text(initials);
        }
        $('#loan-user-card-name').text(name);
        $('#loan-user-card-email').text(item.email || '');
        if ($('#loan-user-card-badge').length) {
            $('#loan-user-card-badge').text(item.role_label || 'Usuario WP');
        }
        $('#aura-lib-loan-user-card').show();
    }

    // ── Explorador Visual de Libros (Modal Apilado) ────────────────
    var bpPage = 1;
    function openBookPickerModal() {
        bpPage = 1;
        $('#aura-lib-bp-search').val('');
        $('#aura-lib-bp-dewey').val('');
        $('#aura-lib-bp-avail-only').prop('checked', true);
        showModal('#aura-lib-book-picker-modal');
        $('#aura-lib-book-picker-modal').css('z-index', '1001500');
        loadBookPicker();
        setTimeout(function () { $('#aura-lib-bp-search').focus(); }, 100);
    }

    function closeBookPickerModal() {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.closeModal === 'function') {
            AuraUI.closeModal('aura-lib-book-picker-modal');
        } else {
            $('#aura-lib-book-picker-modal').removeClass('active is-active').hide();
        }
    }

    function selectBookFromPicker(book) {
        $('#loan_book_id').val(book.id);
        $('#loan_book_input').val(book.title || '');
        showBookCard({
            id        : book.id,
            title     : book.title || '',
            author    : book.author || '',
            dewey     : book.dewey_number || '',
            cover_url : book.cover_thumb_url || '',
            avail     : book.available_copies,
            total     : book.total_copies,
        });
        $('#loan-book-search-row').hide();
        $('#loan-book-error').hide();
        closeBookPickerModal();
    }

    function loadBookPicker() {
        var $grid = $('#aura-lib-bp-grid');
        $grid.html('<div class="aura-lib-bp-loading"><span class="spinner is-active" style="float:none;"></span> Cargando catálogo…</div>');
        $.post(cfg.ajaxurl, {
            action         : 'aura_library_books_get_list',
            nonce          : cfg.nonce,
            page           : bpPage,
            per_page       : 12,
            search         : $('#aura-lib-bp-search').val() || '',
            dewey_class    : $('#aura-lib-bp-dewey').val() || '',
            available_only : $('#aura-lib-bp-avail-only').is(':checked') ? 1 : 0,
            _t             : Date.now(),
        }, function (res) {
            if (!res.success || !res.data) {
                $grid.html('<div class="aura-lib-bp-empty">' + escHtml(cfg.txt.error) + '</div>');
                return;
            }
            var items = res.data.items || [];
            var total = res.data.total || 0;
            var totalBpPages = res.data.total_pages || 1;
            $('#aura-lib-bp-count').text(total + ' libros encontrados');

            if (!items.length) {
                $grid.html('<div class="aura-lib-bp-empty">📚 No se encontraron libros con los filtros actuales.</div>');
                $('#aura-lib-bp-pagination').empty();
                return;
            }

            $grid.empty();
            $.each(items, function (_, b) {
                var avail = parseInt(b.available_copies, 10) || 0;
                var totalCp = parseInt(b.total_copies, 10) || 0;
                var canPick = avail > 0 && b.status !== 'reference_only' && b.status !== 'lost' && b.status !== 'withdrawn';
                var fullCover = b.cover_full_url || b.cover_thumb_url;
                var coverImg = fullCover
                    ? '<img src="' + escAttr(b.cover_thumb_url || fullCover) + '" alt="' + escAttr(b.title || '') + '" class="aura-lib-bp-cover-img">'
                    : '<div class="aura-lib-bp-cover-placeholder"><span class="dashicons dashicons-book-alt"></span></div>';

                var availBadge = canPick
                    ? '<span class="badge badge-emerald">' + avail + ' / ' + totalCp + ' disp.</span>'
                    : '<span class="badge badge-rose">' + escHtml(b.status === 'reference_only' ? 'Solo consulta' : 'Sin stock') + '</span>';

                var $card = $('<div class="aura-lib-bp-card' + (canPick ? '' : ' is-unavailable') + '">' +
                    '<div class="aura-lib-bp-cover-wrap">' + coverImg + '</div>' +
                    '<div class="aura-lib-bp-body">' +
                    '<div class="aura-lib-bp-title" title="' + escAttr(b.title || '') + '">' + escHtml(b.title || '—') + '</div>' +
                    '<div class="aura-lib-bp-author">' + escHtml(b.author || 'Autor desconocido') + '</div>' +
                    '<div class="aura-lib-bp-meta">' +
                    (b.dewey_number ? '<span class="aura-lib-dewey-code">' + escHtml(b.dewey_number) + '</span>' : '') +
                    availBadge +
                    '</div>' +
                    '</div>' +
                    '<div class="aura-lib-bp-foot">' +
                    '<button type="button" class="btn btn-sm ' + (canPick ? 'btn-indigo' : 'btn-glass') + ' aura-lib-bp-select-btn"' +
                    (canPick ? '' : ' disabled') + ' data-tooltip="' + (canPick ? 'Seleccionar este libro para el préstamo' : 'Ejemplar no disponible para préstamo') + '">' +
                    '<span class="dashicons dashicons-yes"></span> ' + (canPick ? 'Seleccionar' : 'No disponible') +
                    '</button>' +
                    '</div>' +
                    '</div>');
                $card.data('book', b);
                $grid.append($card);
            });

            var pHtml = '';
            if (totalBpPages > 1) {
                pHtml += '<div class="aura-lib-page-btns">';
                pHtml += '<button type="button" class="aura-lib-page-btn btn btn-glass btn-sm" data-page="' + Math.max(1, bpPage - 1) + '"' + (bpPage <= 1 ? ' disabled' : '') + '>‹</button>';
                var st = Math.max(1, bpPage - 2);
                var en = Math.min(totalBpPages, st + 4);
                for (var p = st; p <= en; p++) {
                    pHtml += '<button type="button" class="aura-lib-page-btn btn btn-sm ' + (p === bpPage ? 'btn-indigo' : 'btn-glass') + '" data-page="' + p + '">' + p + '</button>';
                }
                pHtml += '<button type="button" class="aura-lib-page-btn btn btn-glass btn-sm" data-page="' + Math.min(totalBpPages, bpPage + 1) + '"' + (bpPage >= totalBpPages ? ' disabled' : '') + '>›</button>';
                pHtml += '</div>';
            }
            $('#aura-lib-bp-pagination').html(pHtml);
        }).fail(function () {
            $grid.html('<div class="aura-lib-bp-empty">' + escHtml(cfg.txt.error) + '</div>');
        });
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
        if (_loansTable) {
            try { _loansTable.destroy(); } catch (e) {}
            _loansTable = null;
        }
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
            _t       : Date.now(),
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

    function renderLoanChildCard(loan) {
        var fullCover = loan.cover_full_url || loan.cover_thumb_url;
        var fineAmt = parseFloat(loan.fine_amount) || 0;
        var coverBox = fullCover
            ? '<img src="' + escAttr(fullCover) + '" alt="' + escAttr(loan.book_title || '') + '" class="aura-child-book-cover">'
            : '<div class="aura-child-book-cover-empty"><span class="dashicons dashicons-book-alt"></span></div>';

        var btns = '<button type="button" class="btn btn-sm btn-indigo aura-lib-loans-btn-detail" data-id="' + parseInt(loan.id, 10) + '"><span class="dashicons dashicons-visibility"></span> Ver detalle</button>';
        if (cfg.can_return && loan.can_return) {
            btns += '<button type="button" class="btn btn-sm btn-emerald aura-lib-loans-btn-return" data-id="' + parseInt(loan.id, 10) + '"><span class="dashicons dashicons-yes-alt"></span> Devolver</button>';
        }
        if (cfg.can_extend && loan.can_extend) {
            btns += '<button type="button" class="btn btn-sm btn-glass aura-lib-loans-btn-extend" data-id="' + parseInt(loan.id, 10) + '"><span class="dashicons dashicons-clock"></span> Extender</button>';
        }

        return '<div class="aura-child-card aura-lib-child-card">' +
            '<div class="aura-lib-child-layout">' +
            '<div class="aura-lib-child-cover-col">' + coverBox + '</div>' +
            '<div class="aura-lib-child-content">' +
            '<div class="aura-child-hero-bar">' +
            '<div>' +
            '<h4 class="aura-child-hero-title">📖 ' + escHtml(loan.book_title || '—') + '</h4>' +
            '<div class="aura-lib-child-author">👤 Lector: ' + escHtml(loan.borrower_name || '—') + (loan.contact_category ? ' (' + escHtml(loan.contact_category) + ')' : '') + '</div>' +
            '</div>' +
            '<div>' + renderStatusBadge(loan.status) + '</div>' +
            '</div>' +
            '<div class="aura-child-meta-grid">' +
            '<div class="aura-child-meta-item"><span>Préstamo ID</span><strong>#' + parseInt(loan.id, 10) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Dewey</span><strong><code>' + escHtml(loan.dewey_number || '—') + '</code></strong></div>' +
            '<div class="aura-child-meta-item"><span>Fecha Préstamo</span><strong>' + escHtml(loan.loan_date || '—') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Devolución Prevista</span><strong>' + escHtml(loan.due_date || '—') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Fecha Devolución Real</span><strong>' + escHtml(loan.return_date || 'Pendiente') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>Extensiones</span><strong>' + parseInt(loan.extended_count, 10) + ' / ' + cfg.max_extensions + '</strong></div>' +
            (cfg.fines_enabled && cfg.can_view_fines ? '<div class="aura-child-meta-item"><span>Multa</span><strong>' + (fineAmt > 0 ? '$' + fineAmt.toFixed(2) : '$0.00') + '</strong></div>' : '') +
            '</div>' +
            (loan.notes ? '<div class="aura-child-synopsis"><strong>Observaciones:</strong> ' + escHtml(loan.notes) + '</div>' : '') +
            '<div class="aura-child-footer-actions">' + btns + '</div>' +
            '</div></div></div>';
    }

    function renderTable(data) {
        var items  = data.items || [];
        totalPages = data.total_pages || 1;
        var tbody  = $('#aura-lib-loans-tbody');
        _loansMap = {};

        if (!items.length) {
            var cols = getColCount();
            tbody.html('<tr><td colspan="' + cols + '" style="text-align:center;padding:20px;">' +
                escHtml(cfg.txt.no_results) + '</td></tr>');
            renderPagination(data);
            return;
        }

        var rows = '';
        $.each(items, function (i, loan) {
            _loansMap[parseInt(loan.id, 10)] = loan;
            var statusTip = loan.is_overdue
                ? 'Préstamo vencido: requiere seguimiento o devolución'
                : ('Estado actual: ' + (cfg.txt.status_labels[loan.status] || loan.status));
            var statusBadge = '<span class="aura-tip-badge-wrap" data-tooltip="' + escAttr(statusTip) + '">' +
                renderStatusBadge(loan.status) + '</span>';
            var dueBadge    = '<span class="aura-tip-badge-wrap" data-tooltip="Fecha pactada de devolución: ' + escAttr(loan.due_date || '—') + '">' +
                renderDueBadge(loan) + '</span>';
            var extBadge    = '<span class="badge badge-slate" data-tooltip="Extensiones usadas: ' +
                parseInt(loan.extended_count, 10) + ' de máximo ' + cfg.max_extensions + '">' +
                parseInt(loan.extended_count, 10) + ' / ' + cfg.max_extensions + '</span>';
            var fineCell    = '';
            if (cfg.fines_enabled && cfg.can_view_fines) {
                var fineAmt = parseFloat(loan.fine_amount) || 0;
                fineCell = fineAmt > 0
                    ? '<span class="badge badge-amber" data-tooltip="Multa pendiente o aplicada al préstamo">$' + fineAmt.toFixed(2) + '</span>'
                    : '<span class="aura-lib-sub-muted" data-tooltip="Sin multas registradas">—</span>';
            }

            var actions = '<div class="aura-row-actions">';
            actions += '<button type="button" class="aura-btn-action-icon aura-lib-loans-btn-detail" data-id="' +
                parseInt(loan.id, 10) + '" data-tooltip="Ver ficha y detalle completo del préstamo">' +
                '<span class="dashicons dashicons-visibility"></span></button>';
            if (cfg.can_return && loan.can_return) {
                actions += '<button type="button" class="aura-btn-action-icon aura-btn-action-success aura-lib-loans-btn-return" data-id="' +
                    parseInt(loan.id, 10) + '" data-tooltip="Registrar devolución del ejemplar">' +
                    '<span class="dashicons dashicons-yes-alt"></span></button>';
            }
            if (cfg.can_extend && loan.can_extend) {
                actions += '<button type="button" class="aura-btn-action-icon aura-btn-action-primary aura-lib-loans-btn-extend" data-id="' +
                    parseInt(loan.id, 10) + '" data-tooltip="Extender plazo de devolución">' +
                    '<span class="dashicons dashicons-clock"></span></button>';
            }
            if (cfg.can_edit && loan.can_edit) {
                actions += '<button type="button" class="aura-btn-action-icon aura-lib-loans-btn-edit" data-id="' +
                    parseInt(loan.id, 10) + '" data-tooltip="Editar fechas u observaciones">' +
                    '<span class="dashicons dashicons-edit"></span></button>';
            }
            if (cfg.can_delete && loan.can_cancel) {
                actions += '<button type="button" class="aura-btn-action-icon aura-btn-action-danger aura-lib-loans-btn-cancel" data-id="' +
                    parseInt(loan.id, 10) + '" data-tooltip="Cancelar préstamo y liberar copia">' +
                    '<span class="dashicons dashicons-no"></span></button>';
            }
            actions += '</div>';

            var fullCover = loan.cover_full_url || loan.cover_thumb_url;
            var coverHtml;
            if (loan.cover_thumb_url) {
                var coverTipHtml = '<div class="aura-floating-img-card aura-lib-floating-cover">' +
                    '<img src="' + escAttr(fullCover) + '" alt="' + escAttr(loan.book_title || '') + '" class="aura-lib-floating-cover-img">' +
                    '<div class="aura-img-caption">' + escHtml(loan.book_title || '') + '</div>' +
                    '</div>';
                coverHtml = '<div class="aura-table-thumb-preview aura-lib-loan-thumb-preview" data-aura-tooltip="' + encodeURIComponent(coverTipHtml) + '">' +
                    '<img src="' + escAttr(loan.cover_thumb_url) + '" class="aura-lib-cover-thumb" alt="' + escAttr(loan.book_title || '') + '">' +
                    '</div>';
            } else {
                coverHtml = '<span class="dashicons dashicons-format-image aura-lib-cover-placeholder-sm" data-tooltip="Sin imagen de portada"></span>';
            }

            var bookTipHtml = '<div class="aura-tip-card">' +
                '<div class="aura-tip-hdr">📖 ' + escHtml(loan.book_title || '—') + '</div>' +
                '<div class="aura-tip-row"><span class="aura-tip-lbl">Préstamo:</span><span class="aura-tip-val">#' + parseInt(loan.id, 10) + '</span></div>' +
                (loan.dewey_number ? '<div class="aura-tip-row"><span class="aura-tip-lbl">Dewey:</span><span class="aura-tip-val">' + escHtml(loan.dewey_number) + '</span></div>' : '') +
                '<div class="aura-tip-row"><span class="aura-tip-lbl">Devolución:</span><span class="aura-tip-val">' + escHtml(loan.due_date || '—') + '</span></div>' +
                '</div>';

            rows += '<tr class="aura-parent-row" data-id="' + parseInt(loan.id, 10) + '">';
            rows += '<td data-label="#" class="aura-lib-col-expand">' +
                '<div class="aura-id-toggle-wrap">' +
                '<button type="button" class="aura-lib-row-toggle" data-id="' + parseInt(loan.id, 10) + '" aria-expanded="false" data-tooltip="Desplegar información completa del préstamo"><span class="dashicons dashicons-plus-alt2"></span></button>' +
                '<span class="aura-txn-id-pill">#' + parseInt(loan.id, 10) + '</span>' +
                '</div></td>';
            rows += '<td data-label="Libro" class="aura-lib-col-book">' +
                '<div class="aura-lib-loan-book-cell">' +
                '<div class="aura-lib-loan-book-cover">' + coverHtml + '</div>' +
                '<div class="aura-lib-loan-book-info" data-aura-tooltip="' + encodeURIComponent(bookTipHtml) + '">' +
                '<strong>' + escHtml(loan.book_title || '—') + '</strong>' +
                (loan.dewey_number ? '<br><code style="font-size:11px;">' + escHtml(loan.dewey_number) + '</code>' : '') +
                '<div class="aura-lib-mobile-sub">👤 ' + escHtml(loan.borrower_name || '—') + ' · 📅 ' + escHtml(loan.due_date || '—') + '</div>' +
                '</div></div>' +
                '</td>';
            if (cfg.can_view_all) {
                var borrowerHtml = '<div class="aura-lib-borrower-cell" data-tooltip="Lector: ' + escAttr(loan.borrower_name || '—') + (loan.contact_category ? ' (' + escAttr(loan.contact_category) + ')' : '') + '">';
                if (loan.contact_avatar) {
                    borrowerHtml += '<div class="avatar avatar-sm avatar-zoomable" data-preview-title="' + escAttr(loan.borrower_name || '') + '"><img src="' + escAttr(loan.contact_avatar) + '" alt="' + escAttr(loan.borrower_name || '') + '"></div>';
                } else {
                    var bInitials = (loan.borrower_name || '?').charAt(0).toUpperCase();
                    borrowerHtml += '<span class="aura-lib-borrower-initials">' + escHtml(bInitials) + '</span>';
                }
                borrowerHtml += '<div><strong>' + escHtml(loan.borrower_name || '—') + '</strong>';
                if (loan.contact_category) {
                    borrowerHtml += '<br><small class="aura-lib-borrower-cat">' + escHtml(loan.contact_category) + '</small>';
                }
                borrowerHtml += '</div></div>';
                rows += '<td data-label="Lector" class="aura-lib-col-secondary">' + borrowerHtml + '</td>';
            }
            rows += '<td data-label="Fecha préstamo" class="aura-lib-col-secondary"><span data-tooltip="Entregado el ' + escAttr(loan.loan_date || '—') + '">' + escHtml(loan.loan_date || '—') + '</span></td>';
            rows += '<td data-label="Devol. prevista" class="aura-lib-col-secondary">' + dueBadge + '</td>';
            rows += '<td data-label="Estado" class="aura-lib-col-status">' + statusBadge + '</td>';
            rows += '<td data-label="Extensiones" class="aura-lib-col-secondary" style="text-align:center;">' + extBadge + '</td>';
            if (cfg.fines_enabled && cfg.can_view_fines) {
                rows += '<td data-label="Multa" class="aura-lib-col-secondary" style="text-align:right;">' + fineCell + '</td>';
            }
            rows += '<td data-label="Acciones" class="aura-lib-col-actions">' + actions + '</td>';
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
        _loansTable = $('#aura-lib-loans-table').DataTable({
            responsive  : false,
            paging      : false,
            searching   : false,
            info        : false,
            ordering    : false,
            autoWidth   : false,
            language    : { emptyTable: cfg.txt.no_results },
        });
    }

    function renderStatusBadge(status) {
        var classMap = {
            'active'    : 'badge-emerald',
            'overdue'   : 'badge-red',
            'extended'  : 'badge-blue',
            'returned'  : 'badge-slate',
            'lost'      : 'badge-amber',
            'cancelled' : 'badge-slate',
        };
        var cls   = classMap[status] || 'badge-slate';
        var label = (cfg.txt.status_labels && cfg.txt.status_labels[status]) || status;
        return '<span class="badge aura-lib-badge ' + cls + '">' + escHtml(label) + '</span>';
    }

    function renderDueBadge(loan) {
        if (!loan.due_date) return '—';
        var isOverdue = (loan.status === 'overdue') ||
            (['active', 'extended'].indexOf(loan.status) !== -1 && loan.due_date < todayStr());
        if (isOverdue) {
            return '<span class="badge badge-red">' + escHtml(loan.due_date) + '</span>';
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
            html += '<button type="button" class="aura-lib-page-btn btn btn-glass btn-sm" data-page="' + Math.max(1, currentPage - 1) + '"' +
                    (currentPage <= 1 ? ' disabled' : '') + '>‹</button>';
            var start = Math.max(1, currentPage - 2);
            var end   = Math.min(totalPages, start + 4);
            for (var p = start; p <= end; p++) {
                html += '<button type="button" class="aura-lib-page-btn btn btn-sm ' + (p === currentPage ? 'btn-indigo' : 'btn-glass') +
                        '" data-page="' + p + '">' + p + '</button>';
            }
            html += '<button type="button" class="aura-lib-page-btn btn btn-glass btn-sm" data-page="' + Math.min(totalPages, currentPage + 1) + '"' +
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
        $('#loan-reader-selector-wrap').show();
        $('#loan-user-search-row').show();
        $('#loan-user-error').hide();
        if (window.AuraContactSelector) {
            window.AuraContactSelector.clear('#loan-contact-selector');
        }

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

        var contactId = parseInt($('#loan-contact-selector-val').val(), 10) || 0;
        var userId    = parseInt($('#loan_user_id').val(), 10) || 0;
        if (cfg.can_view_all && !userId && !contactId) {
            $('#loan-user-error').show();
            if ($('#loan-contact-selector .aura-cs-search-input').length) {
                $('#loan-contact-selector .aura-cs-search-input').focus();
            } else {
                $('#loan_user_input').focus();
            }
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
            contact_id        : contactId,
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
        $('#aura-lib-return-info').empty();
        showModal('#aura-lib-return-modal', { async: true });

        // Cargar detalle del préstamo para mostrar info
        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-return-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-return-modal');
                }
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
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-return-modal');
            }
        }).fail(function () {
            $('#aura-lib-return-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-return-modal');
            }
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
        $('#aura-lib-extend-info').empty();
        showModal('#aura-lib-extend-modal', { async: true });

        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-extend-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-extend-modal');
                }
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
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-extend-modal');
            }
        }).fail(function () {
            $('#aura-lib-extend-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-extend-modal');
            }
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
        $('#aura-lib-edit-loan-info').empty();
        $('#aura-lib-edit-loan-error').hide();
        $('#edit_loan_date, #edit_due_date, #edit_notes').val('');

        showModal('#aura-lib-edit-loan-modal', { async: true });

        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-edit-loan-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-edit-loan-modal');
                }
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
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-edit-loan-modal');
            }
        }).fail(function () {
            $('#aura-lib-edit-loan-info').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-edit-loan-modal');
            }
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
        $('#aura-lib-loan-detail-body').empty();
        showModal('#aura-lib-loan-detail-modal', { async: true });

        $.post(cfg.ajaxurl, {
            action : 'aura_library_loans_get_detail',
            nonce  : cfg.nonce,
            loan_id: loanId,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-loan-detail-body').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-loan-detail-modal');
                }
                return;
            }
            renderDetail(res.data.loan);
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-loan-detail-modal');
            }
        }).fail(function () {
            $('#aura-lib-loan-detail-body').html('<p class="notice notice-error">' + escHtml(cfg.txt.error) + '</p>');
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-loan-detail-modal');
            }
        });
    }

    function renderDetail(loan) {
        var statusBadge = renderStatusBadge(loan.status);
        var fineRow = '';
        if (cfg.fines_enabled && cfg.can_view_fines && parseFloat(loan.fine_current || 0) > 0) {
            fineRow = '<tr><th>Multa acumulada</th><td>' +
                '<span class="badge badge-amber">$' +
                parseFloat(loan.fine_current).toFixed(2) + '</span></td></tr>';
        }

        var html = '<table class="aura-lib-detail-table aura-table">' +
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
        var btns = '<div class="aura-modal-footer aura-lib-modal-footer aura-lib-detail-actions">';
        if (cfg.can_return && loan.can_return) {
            btns += '<button type="button" class="btn btn-shimmer btn-emerald btn-lift aura-lib-loans-btn-return" data-id="' +
                parseInt(loan.id, 10) + '">Registrar devolución</button>';
        }
        if (cfg.can_extend && loan.can_extend) {
            btns += '<button type="button" class="btn btn-glass btn-lift aura-lib-loans-btn-extend" data-id="' +
                parseInt(loan.id, 10) + '">Extender</button>';
        }
        if (cfg.can_edit && loan.can_edit) {
            btns += '<button type="button" class="btn btn-indigo btn-lift aura-lib-loans-btn-edit" data-id="' +
                parseInt(loan.id, 10) + '">Editar</button>';
        }
        if (cfg.can_delete && loan.can_cancel) {
            btns += '<button type="button" class="btn btn-rose btn-lift aura-lib-loans-btn-cancel" data-id="' +
                parseInt(loan.id, 10) + '">Cancelar préstamo</button>';
        }
        btns += '<button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-loan-detail-modal">Cerrar</button>';
        btns += '</div>';

        $('#aura-lib-loan-detail-body').html(html + btns);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: MODALES (AuraUI + Skeleton Loader)
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
        $('.aura-lib-modal').each(function () {
            var id = this.id;
            if (id && typeof AuraUI !== 'undefined' && typeof AuraUI.closeModal === 'function') {
                AuraUI.closeModal(id);
            } else {
                $(this).removeClass('active is-active').hide();
            }
        });
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
            '<tr><td colspan="' + cols + '" class="aura-lib-table-error">' +
            escHtml(msg) + '</td></tr>'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES: NOTIFICACIONES
    // ──────────────────────────────────────────────────────────────
    function showNotice(type, msg) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
            AuraUI.showToast(escHtml(msg), type || 'success');
        }
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
