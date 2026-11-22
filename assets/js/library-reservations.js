/**
 * library-reservations.js — Reservas de Libros (Fase 4)
 * Aura Business Suite
 *
 * Maneja: listado de reservas DataTables, detalle, cancelación.
 * El botón "Reservar" desde el catálogo está en library-books.js.
 */
/* global auraLibraryReservations, jQuery */
(function ($) {
    'use strict';

    function __(s) { return (window.wp && wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__(s, 'aura-suite') : s; }

    if (typeof auraLibraryReservations === 'undefined') return;

    var cfg        = auraLibraryReservations;
    var currentPage = 1;
    var perPage     = 20;
    var totalPages  = 1;
    var _resTable   = null;
    var _resMap     = {};

    // ──────────────────────────────────────────────────────────────
    // INIT
    // ──────────────────────────────────────────────────────────────
    $(document).ready(function () {
        loadReservations();
        bindEvents();
    });

    // ──────────────────────────────────────────────────────────────
    // EVENTS
    // ──────────────────────────────────────────────────────────────
    function bindEvents() {
        $(document).on('click', '#aura-lib-res-filter-apply', function () {
            currentPage = 1;
            loadReservations();
        });
        $(document).on('click', '#aura-lib-res-filter-clear', function () {
            $('#aura-lib-res-search').val('');
            $('#aura-lib-res-filter-status').val('');
            currentPage = 1;
            loadReservations();
        });
        $(document).on('keydown', '#aura-lib-res-search', function (e) {
            if (e.key === 'Enter') { currentPage = 1; loadReservations(); }
        });

        // Paginación
        $(document).on('click', '#aura-lib-res-pagination .aura-lib-page-btn', function () {
            var p = parseInt($(this).data('page'), 10);
            if (p >= 1 && p <= totalPages) { currentPage = p; loadReservations(); }
        });

        // Acciones tabla
        $(document).on('click', '.aura-lib-res-btn-cancel', function () {
            cancelReservation(parseInt($(this).data('id'), 10));
        });
        $(document).on('click', '.aura-lib-res-btn-detail', function () {
            openDetailModal(parseInt($(this).data('id'), 10));
        });

        // Expandir / Colapsar fila hija con información completa de la reserva (+ / -)
        $(document).on('click', '#aura-lib-reservations-table .aura-lib-row-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $tr  = $btn.closest('tr');
            var id   = parseInt($btn.data('id'), 10);
            var res  = _resMap[id];
            if (!res) return;

            var isExpanded = $btn.hasClass('is-active');
            if (_resTable && typeof _resTable.row === 'function') {
                var dtRow = _resTable.row($tr);
                if (dtRow.child.isShown()) {
                    dtRow.child.hide();
                    $tr.removeClass('shown is-expanded');
                    $btn.removeClass('is-active').attr('aria-expanded', 'false')
                        .html('<span class="dashicons dashicons-plus-alt2"></span>');
                } else {
                    dtRow.child(renderResChildCard(res), 'aura-dt-child-row aura-child-row is-open').show();
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
                    $tr.after('<tr class="aura-child-row is-open"><td colspan="8">' + renderResChildCard(res) + '</td></tr>');
                    $tr.addClass('shown is-expanded');
                    $btn.addClass('is-active').attr('aria-expanded', 'true')
                        .html('<span class="dashicons dashicons-minus"></span>');
                }
            }
        });

        // Cerrar modales (protección 2 fases contra drag-selection en inputs)
        var _resBackdropDown = false;
        $(document).on('mousedown', '.aura-lib-modal', function (e) {
            _resBackdropDown = (e.target === this);
        });
        $(document).on('click', '.aura-lib-modal-close', function () {
            closeAllModals();
        });
        $(document).on('click', '.aura-lib-modal', function (e) {
            if (_resBackdropDown && e.target === this) {
                closeAllModals();
            }
            _resBackdropDown = false;
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeAllModals();
        });
    }

    // ──────────────────────────────────────────────────────────────
    // CARGA DE RESERVAS
    // ──────────────────────────────────────────────────────────────
    function loadReservations() {
        if (_resTable) {
            try { _resTable.destroy(); } catch (e) {}
            _resTable = null;
        }
        showTableLoading();

        $.post(cfg.ajaxurl, {
            action   : 'aura_library_reservations_get_list',
            nonce    : cfg.nonce,
            page     : currentPage,
            per_page : perPage,
            search   : $('#aura-lib-res-search').val() || '',
            status   : $('#aura-lib-res-filter-status').val() || '',
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

    function renderResChildCard(res) {
        var btns = '<button type="button" class="btn btn-sm btn-indigo aura-lib-res-btn-detail" data-id="' + parseInt(res.id, 10) + '"><span class="dashicons dashicons-visibility"></span> ' + escHtml(__('Ver detalle', 'aura-suite')) + '</button>';
        if (cfg.can_cancel && res.can_cancel) {
            btns += '<button type="button" class="btn btn-sm btn-rose aura-lib-res-btn-cancel" data-id="' + parseInt(res.id, 10) + '"><span class="dashicons dashicons-no-alt"></span> ' + escHtml(__('Cancelar reserva', 'aura-suite')) + '</button>';
        }
        return '<div class="aura-child-card aura-lib-child-card">' +
            '<div class="aura-child-hero-bar">' +
            '<div><h4 class="aura-child-hero-title">🔖 ' + escHtml(res.book_title || '—') + '</h4>' +
            '<div class="aura-lib-sub-muted">👤 ' + escHtml(res.user_name || '—') + (res.user_email ? ' · ' + escHtml(res.user_email) : '') + '</div></div>' +
            '<div>' + renderStatusBadge(res.status) + '</div>' +
            '</div>' +
            '<div class="aura-child-meta-grid">' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Reserva ID', 'aura-suite')) + '</span><strong>#' + parseInt(res.id, 10) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Dewey', 'aura-suite')) + '</span><strong><code>' + escHtml(res.dewey_number || '—') + '</code></strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Posición en Cola', 'aura-suite')) + '</span><strong>' + (res.status === 'waiting' ? '#' + parseInt(res.queue_position, 10) : '—') + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Reservado el', 'aura-suite')) + '</span><strong>' + escHtml((res.reserved_at || '—').substring(0, 10)) + '</strong></div>' +
            '<div class="aura-child-meta-item"><span>' + escHtml(__('Expira', 'aura-suite')) + '</span><strong>' + escHtml(res.expires_at ? res.expires_at.substring(0, 10) : '—') + '</strong></div>' +
            '</div>' +
            '<div class="aura-child-footer-actions">' + btns + '</div>' +
            '</div>';
    }

    function renderTable(data) {
        var items  = data.items || [];
        totalPages = data.total_pages || 1;
        var tbody  = $('#aura-lib-res-tbody');
        _resMap = {};

        if (!items.length) {
            tbody.html('<tr><td colspan="8" style="text-align:center;padding:20px;">' +
                escHtml(cfg.txt.no_results) + '</td></tr>');
            renderPagination(data);
            return;
        }

        var rows = '';
        $.each(items, function (i, res) {
            _resMap[parseInt(res.id, 10)] = res;
            var statusTip = res.status === 'waiting'
                ? __('En espera del próximo ejemplar disponible', 'aura-suite')
                : (res.status === 'notified'
                    ? __('Lector notificado: pendiente de recoger antes de expirar', 'aura-suite')
                    : (__('Estado:', 'aura-suite') + ' ' + ((cfg.txt.status_labels && cfg.txt.status_labels[res.status]) || res.status)));
            var statusBadge = '<span class="aura-tip-badge-wrap" data-tooltip="' + escHtml(statusTip) + '">' +
                renderStatusBadge(res.status) + '</span>';
            var queueBadge  = res.status === 'waiting'
                ? '<span class="badge badge-blue" data-tooltip="' + escHtml(__('Turno #', 'aura-suite')) + parseInt(res.queue_position, 10) + ' ' + escHtml(__('en la lista de espera', 'aura-suite')) + '">#' + parseInt(res.queue_position, 10) + '</span>'
                : '<span class="aura-lib-sub-muted" data-tooltip="' + escHtml(__('Reserva fuera de cola de espera', 'aura-suite')) + '">—</span>';

            var expRaw = res.expires_at ? res.expires_at.substring(0, 10) : '';
            var expStr = expRaw ? escHtml(expRaw) : '<span class="aura-lib-sub-muted">—</span>';
            var isExpiringSoon = false;
            if (expRaw && res.status === 'notified') {
                var diff = dateDiff(todayStr(), expRaw);
                if (diff >= 0 && diff <= 2) isExpiringSoon = true;
            }
            if (isExpiringSoon) {
                expStr = '<span class="badge badge-amber" data-tooltip="' + escHtml(__('¡Expira pronto! Quedan pocos días para retirar', 'aura-suite')) + '">' + escHtml(expRaw) + '</span>';
            } else if (expRaw) {
                expStr = '<span data-tooltip="' + escHtml(__('Fecha límite de recogida:', 'aura-suite')) + ' ' + escHtml(expRaw) + '">' + escHtml(expRaw) + '</span>';
            }

            var actions = '<div class="aura-row-actions">';
            actions += '<button type="button" class="aura-btn-action-icon aura-lib-res-btn-detail" data-id="' +
                parseInt(res.id, 10) + '" data-tooltip="' + escHtml(__('Ver ficha completa de la reserva', 'aura-suite')) + '">' +
                '<span class="dashicons dashicons-visibility"></span></button>';
            if (cfg.can_cancel && res.can_cancel) {
                actions += '<button type="button" class="aura-btn-action-icon aura-btn-action-danger aura-lib-res-btn-cancel" data-id="' +
                    parseInt(res.id, 10) + '" data-tooltip="' + escHtml(__('Cancelar reserva y liberar turno', 'aura-suite')) + '">' +
                    '<span class="dashicons dashicons-no-alt"></span></button>';
            }
            actions += '</div>';

            var bookTipHtml = '<div class="aura-tip-card">' +
                '<div class="aura-tip-card-header"><div class="aura-tip-info"><div class="aura-tip-title">📖 ' + escHtml(res.book_title || '—') + '</div></div></div>' +
                '<div class="aura-tip-card-body">' +
                (res.dewey_number ? '<div class="aura-tip-row"><span>' + escHtml(__('Dewey:', 'aura-suite')) + '</span><strong>' + escHtml(res.dewey_number) + '</strong></div>' : '') +
                '<div class="aura-tip-row"><span>' + escHtml(__('Lector:', 'aura-suite')) + '</span><strong>' + escHtml(res.user_name || '—') + '</strong></div>' +
                '<div class="aura-tip-row"><span>' + escHtml(__('Reserva:', 'aura-suite')) + '</span><strong>#' + parseInt(res.id, 10) + '</strong></div>' +
                '</div></div>';

            rows += '<tr class="aura-parent-row" data-id="' + parseInt(res.id, 10) + '">';
            rows += '<td data-label="#" class="aura-lib-col-expand">' +
                '<div class="aura-id-toggle-wrap">' +
                '<button type="button" class="aura-lib-row-toggle" data-id="' + parseInt(res.id, 10) + '" aria-expanded="false" data-tooltip="' + escHtml(__('Desplegar detalles de la reserva', 'aura-suite')) + '"><span class="dashicons dashicons-plus-alt2"></span></button>' +
                '<span class="aura-txn-id-pill">#' + parseInt(res.id, 10) + '</span>' +
                '</div></td>';
            rows += '<td data-label="' + escHtml(__('Libro', 'aura-suite')) + '" class="aura-lib-col-book" data-aura-tooltip="' + encodeURIComponent(bookTipHtml) + '"><strong>' + escHtml(res.book_title || '—') + '</strong>' +
                (res.dewey_number ? '<br><code style="font-size:11px;">' + escHtml(res.dewey_number) + '</code>' : '') +
                '<div class="aura-lib-mobile-sub">👤 ' + escHtml(res.user_name || '—') + (res.status === 'waiting' ? ' · ' + escHtml(__('Turno #', 'aura-suite')) + parseInt(res.queue_position, 10) : '') + '</div></td>';
            rows += '<td data-label="' + escHtml(__('Lector', 'aura-suite')) + '" class="aura-lib-col-secondary"><span data-tooltip="' + escHtml((res.user_name || '—') + (res.user_email ? ' (' + res.user_email + ')' : '')) + '">' + escHtml(res.user_name || '—') + '</span>' +
                '<br><small class="aura-lib-sub-muted">' + escHtml(res.user_email || '') + '</small></td>';
            rows += '<td data-label="' + escHtml(__('Posición', 'aura-suite')) + '" class="aura-lib-col-secondary" style="text-align:center;">' + queueBadge + '</td>';
            rows += '<td data-label="' + escHtml(__('Reservado', 'aura-suite')) + '" class="aura-lib-col-secondary">' + escHtml((res.reserved_at || '').substring(0, 10)) + '</td>';
            rows += '<td data-label="' + escHtml(__('Expira', 'aura-suite')) + '" class="aura-lib-col-secondary">' + expStr + '</td>';
            rows += '<td data-label="' + escHtml(__('Estado', 'aura-suite')) + '" class="aura-lib-col-status">' + statusBadge + '</td>';
            rows += '<td data-label="' + escHtml(__('Acciones', 'aura-suite')) + '" class="aura-lib-col-actions">' + actions + '</td>';
            rows += '</tr>';
        });

        tbody.html(rows);
        renderPagination(data);

        if ($.fn && $.fn.DataTable) {
            _resTable = $('#aura-lib-reservations-table').DataTable({
                responsive  : false,
                paging      : false,
                searching   : false,
                info        : false,
                ordering    : false,
                autoWidth   : false,
                language    : { emptyTable: cfg.txt.no_results },
            });
        }
    }

    function renderStatusBadge(status) {
        var classMap = {
            'waiting'  : 'badge-blue',
            'notified' : 'badge-emerald',
            'expired'  : 'badge-amber',
            'cancelled': 'badge-slate',
        };
        var cls   = classMap[status] || 'badge-slate';
        var label = (cfg.txt.status_labels && cfg.txt.status_labels[status]) || status;
        return '<span class="badge aura-lib-badge ' + cls + '">' + escHtml(label) + '</span>';
    }

    function renderPagination(data) {
        var total  = data.total || 0;
        totalPages = data.total_pages || 1;
        var pag    = $('#aura-lib-res-pagination');

        var html = '<span class="aura-lib-count">' + sprintf(cfg.txt.n_items, total) + '</span>';

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
    // CANCELAR
    // ──────────────────────────────────────────────────────────────
    function cancelReservation(id) {
        if (!confirm(cfg.txt.confirm_cancel)) return;

        $.post(cfg.ajaxurl, {
            action         : 'aura_library_reservations_cancel',
            nonce          : cfg.nonce,
            reservation_id : id,
        }, function (res) {
            if (res.success) {
                showNotice('success', cfg.txt.cancelled);
                loadReservations();
            } else {
                alert(res.data ? res.data.message : cfg.txt.error);
            }
        }).fail(function () {
            alert(cfg.txt.error);
        });
    }

    // ──────────────────────────────────────────────────────────────
    // DETALLE
    // ──────────────────────────────────────────────────────────────
    function openDetailModal(id) {
        $('#aura-lib-res-detail-body').empty();
        showModal('#aura-lib-res-detail-modal', { async: true });

        $.post(cfg.ajaxurl, {
            action : 'aura_library_reservations_get_detail',
            nonce  : cfg.nonce,
            id     : id,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-res-detail-body').html('<p class="aura-lib-table-error">' + escHtml(cfg.txt.error) + '</p>');
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-lib-res-detail-modal');
                }
                return;
            }
            renderDetail(res.data.reservation);
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-res-detail-modal');
            }
        }).fail(function () {
            $('#aura-lib-res-detail-body').html('<p class="aura-lib-table-error">' + escHtml(cfg.txt.error) + '</p>');
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-lib-res-detail-modal');
            }
        });
    }

    function renderDetail(r) {
        var statusBadge = renderStatusBadge(r.status);
        var html = '<table class="aura-lib-detail-table aura-table">' +
            '<tr><th>' + escHtml(__('Libro', 'aura-suite')) + '</th><td><strong>' + escHtml(r.book_title) + '</strong>' +
                (r.dewey_number ? ' <code>' + escHtml(r.dewey_number) + '</code>' : '') +
                '<br><small class="aura-lib-sub-muted">' + escHtml(r.author || '') + '</small></td></tr>' +
            '<tr><th>' + escHtml(__('Copias disponibles', 'aura-suite')) + '</th><td>' + parseInt(r.available_copies || 0, 10) +
                ' / ' + parseInt(r.total_copies || 0, 10) + '</td></tr>' +
            '<tr><th>' + escHtml(__('Lector', 'aura-suite')) + '</th><td>' + escHtml(r.user_name) +
                '<br><small class="aura-lib-sub-muted">' + escHtml(r.user_email) + '</small></td></tr>' +
            '<tr><th>' + escHtml(__('Posición en cola', 'aura-suite')) + '</th><td>' +
                (r.status === 'waiting' ? '#' + parseInt(r.queue_position, 10) : '—') + '</td></tr>' +
            '<tr><th>' + escHtml(__('Reservado el', 'aura-suite')) + '</th><td>' + escHtml((r.reserved_at || '').substring(0, 10)) + '</td></tr>' +
            (r.notified_at ? '<tr><th>' + escHtml(__('Notificado el', 'aura-suite')) + '</th><td>' + escHtml(r.notified_at.substring(0, 10)) + '</td></tr>' : '') +
            (r.expires_at  ? '<tr><th>' + escHtml(__('Expira el', 'aura-suite')) + '</th><td>'    + escHtml(r.expires_at.substring(0, 10))  + '</td></tr>' : '') +
            '<tr><th>' + escHtml(__('Estado', 'aura-suite')) + '</th><td>' + statusBadge + '</td></tr>' +
            (r.notes ? '<tr><th>' + escHtml(__('Notas', 'aura-suite')) + '</th><td>' + escHtml(r.notes) + '</td></tr>' : '') +
            '</table>';

        var btns = '<div class="aura-modal-footer aura-lib-modal-footer aura-lib-detail-actions">';
        if (cfg.can_cancel && r.can_cancel) {
            btns += '<button type="button" class="btn btn-rose btn-lift aura-lib-res-btn-cancel" data-id="' +
                parseInt(r.id, 10) + '">' + escHtml(__('Cancelar reserva', 'aura-suite')) + '</button>';
        }
        btns += '<button type="button" class="btn btn-glass btn-lift aura-modal-close aura-lib-modal-close" data-modal-close="aura-lib-res-detail-modal">' + escHtml(__('Cerrar', 'aura-suite')) + '</button>';
        btns += '</div>';

        $('#aura-lib-res-detail-body').html(html + btns);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES MODAL (AuraUI + Skeleton Loader)
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
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES TABLA
    // ──────────────────────────────────────────────────────────────
    function showTableLoading() {
        $('#aura-lib-res-tbody').html(
            '<tr><td colspan="8" style="text-align:center;padding:20px;">' +
            '<span class="spinner is-active" style="float:none;"></span> ' +
            escHtml(cfg.txt.loading) + '</td></tr>'
        );
    }
    function showTableError(msg) {
        $('#aura-lib-res-tbody').html(
            '<tr><td colspan="8" class="aura-lib-table-error">' +
            escHtml(msg) + '</td></tr>'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES NOTIFICACIONES
    // ──────────────────────────────────────────────────────────────
    function showNotice(type, msg) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
            AuraUI.showToast(escHtml(msg), type || 'success');
        }
        var $n = $('#aura-lib-res-notice');
        $n.attr('class', 'notice notice-' + type + ' is-dismissible')
          .html('<p>' + escHtml(msg) + '</p>')
          .show();
        setTimeout(function () { $n.fadeOut(); }, 4000);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES FECHA
    // ──────────────────────────────────────────────────────────────
    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }
    /** Days from dateA to dateB (positive = dateB is after dateA) */
    function dateDiff(dateA, dateB) {
        return Math.floor((new Date(dateB) - new Date(dateA)) / 86400000);
    }
    function pad2(n) { return n < 10 ? '0' + n : String(n); }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES ESCAPE
    // ──────────────────────────────────────────────────────────────
    function escHtml(str) {
        if (!str && str !== 0) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function sprintf(tpl, val) {
        return String(tpl).replace('%s', String(val));
    }

}(jQuery));
