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

    if (typeof auraLibraryReservations === 'undefined') return;

    var cfg        = auraLibraryReservations;
    var currentPage = 1;
    var perPage     = 20;
    var totalPages  = 1;

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

        // Cerrar modales
        $(document).on('click', '.aura-lib-modal-close, .aura-lib-modal-overlay', function () {
            closeAllModals();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeAllModals();
        });
    }

    // ──────────────────────────────────────────────────────────────
    // CARGA DE RESERVAS
    // ──────────────────────────────────────────────────────────────
    function loadReservations() {
        showTableLoading();

        $.post(cfg.ajaxurl, {
            action   : 'aura_library_reservations_get_list',
            nonce    : cfg.nonce,
            page     : currentPage,
            per_page : perPage,
            search   : $('#aura-lib-res-search').val() || '',
            status   : $('#aura-lib-res-filter-status').val() || '',
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
        var tbody  = $('#aura-lib-res-tbody');

        if (!items.length) {
            tbody.html('<tr><td colspan="8" style="text-align:center;padding:20px;">' +
                escHtml(cfg.txt.no_results) + '</td></tr>');
            renderPagination(data);
            return;
        }

        var rows = '';
        $.each(items, function (i, res) {
            var statusBadge = renderStatusBadge(res.status);
            var queueBadge  = res.status === 'waiting'
                ? '<span class="aura-lib-badge aura-lib-badge-blue">#' + parseInt(res.queue_position, 10) + '</span>'
                : '—';

            var expStr = res.expires_at ? res.expires_at.substring(0, 10) : '—';
            var isExpiringSoon = false;
            if (res.expires_at && res.status === 'notified') {
                var diff = dateDiff(todayStr(), res.expires_at.substring(0, 10));
                if (diff >= 0 && diff <= 2) isExpiringSoon = true;
            }
            if (isExpiringSoon) {
                expStr = '<span class="aura-lib-badge aura-lib-badge-orange">' + expStr + '</span>';
            }

            var actions = '<div class="aura-lib-actions">';
            actions += '<button class="aura-lib-btn-action aura-lib-res-btn-detail" data-id="' +
                parseInt(res.id, 10) + '" title="Ver detalle">' +
                '<span class="dashicons dashicons-visibility"></span></button>';
            if (cfg.can_cancel && res.can_cancel) {
                actions += '<button class="aura-lib-btn-action aura-lib-res-btn-cancel aura-lib-btn-danger" data-id="' +
                    parseInt(res.id, 10) + '" title="Cancelar reserva">' +
                    '<span class="dashicons dashicons-no-alt"></span></button>';
            }
            actions += '</div>';

            rows += '<tr data-id="' + parseInt(res.id, 10) + '">';
            rows += '<td><small>#' + parseInt(res.id, 10) + '</small></td>';
            rows += '<td><strong>' + escHtml(res.book_title || '—') + '</strong>' +
                (res.dewey_number ? '<br><code style="font-size:11px;">' + escHtml(res.dewey_number) + '</code>' : '') + '</td>';
            rows += '<td>' + escHtml(res.user_name || '—') +
                '<br><small style="color:#666;">' + escHtml(res.user_email || '') + '</small></td>';
            rows += '<td style="text-align:center;">' + queueBadge + '</td>';
            rows += '<td>' + escHtml((res.reserved_at || '').substring(0, 10)) + '</td>';
            rows += '<td>' + expStr + '</td>';
            rows += '<td>' + statusBadge + '</td>';
            rows += '<td>' + actions + '</td>';
            rows += '</tr>';
        });

        tbody.html(rows);
        renderPagination(data);
    }

    function renderStatusBadge(status) {
        var classMap = {
            'waiting'  : 'aura-lib-badge-blue',
            'notified' : 'aura-lib-badge-green',
            'expired'  : 'aura-lib-badge-orange',
            'cancelled': 'aura-lib-badge-gray',
        };
        var cls   = classMap[status] || 'aura-lib-badge-gray';
        var label = (cfg.txt.status_labels && cfg.txt.status_labels[status]) || status;
        return '<span class="aura-lib-badge ' + cls + '">' + escHtml(label) + '</span>';
    }

    function renderPagination(data) {
        var total  = data.total || 0;
        totalPages = data.total_pages || 1;
        var pag    = $('#aura-lib-res-pagination');

        var html = '<span class="aura-lib-count">' + sprintf(cfg.txt.n_items, total) + '</span>';

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
        $('#aura-lib-res-detail-body').html('<span class="spinner is-active" style="float:none;"></span>');
        showModal('#aura-lib-res-detail-modal');

        $.post(cfg.ajaxurl, {
            action : 'aura_library_reservations_get_detail',
            nonce  : cfg.nonce,
            id     : id,
        }, function (res) {
            if (!res.success || !res.data) {
                $('#aura-lib-res-detail-body').html('<p style="color:#d63638;">' + escHtml(cfg.txt.error) + '</p>');
                return;
            }
            renderDetail(res.data.reservation);
        });
    }

    function renderDetail(r) {
        var statusBadge = renderStatusBadge(r.status);
        var html = '<table class="aura-lib-detail-table wp-list-table widefat">' +
            '<tr><th>Libro</th><td><strong>' + escHtml(r.book_title) + '</strong>' +
                (r.dewey_number ? ' <code>' + escHtml(r.dewey_number) + '</code>' : '') +
                '<br><small>' + escHtml(r.author || '') + '</small></td></tr>' +
            '<tr><th>Copias disponibles</th><td>' + parseInt(r.available_copies || 0, 10) +
                ' / ' + parseInt(r.total_copies || 0, 10) + '</td></tr>' +
            '<tr><th>Lector</th><td>' + escHtml(r.user_name) +
                '<br><small>' + escHtml(r.user_email) + '</small></td></tr>' +
            '<tr><th>Posición en cola</th><td>' +
                (r.status === 'waiting' ? '#' + parseInt(r.queue_position, 10) : '—') + '</td></tr>' +
            '<tr><th>Reservado el</th><td>' + escHtml((r.reserved_at || '').substring(0, 10)) + '</td></tr>' +
            (r.notified_at ? '<tr><th>Notificado el</th><td>' + escHtml(r.notified_at.substring(0, 10)) + '</td></tr>' : '') +
            (r.expires_at  ? '<tr><th>Expira el</th><td>'    + escHtml(r.expires_at.substring(0, 10))  + '</td></tr>' : '') +
            '<tr><th>Estado</th><td>' + statusBadge + '</td></tr>' +
            (r.notes ? '<tr><th>Notas</th><td>' + escHtml(r.notes) + '</td></tr>' : '') +
            '</table>';

        var btns = '<div class="aura-lib-modal-footer">';
        if (cfg.can_cancel && r.can_cancel) {
            btns += '<button type="button" class="button aura-lib-res-btn-cancel" data-id="' +
                parseInt(r.id, 10) + '">Cancelar reserva</button> ';
        }
        btns += '<button type="button" class="button aura-lib-modal-close">Cerrar</button>';
        btns += '</div>';

        $('#aura-lib-res-detail-body').html(html + btns);
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES MODAL
    // ──────────────────────────────────────────────────────────────
    function showModal(selector) {
        $(selector).fadeIn(200);
        $('body').addClass('aura-lib-modal-open');
    }
    function closeAllModals() {
        $('.aura-lib-modal').fadeOut(150);
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
            '<tr><td colspan="8" style="text-align:center;padding:20px;color:#d63638;">' +
            escHtml(msg) + '</td></tr>'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // UTILIDADES NOTIFICACIONES
    // ──────────────────────────────────────────────────────────────
    function showNotice(type, msg) {
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
