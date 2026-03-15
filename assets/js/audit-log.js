/**
 * Audit Log — Aura Business Suite
 * Fase 5, Item 5.3
 */
/* global auraAuditConfig, jQuery */
(function ($) {
    'use strict';

    const cfg = auraAuditConfig;
    let currentPage = 1;
    let totalPages  = 1;

    /* ============================================================
       Init
       ============================================================ */
    $(function () {
        loadLogs();
        bindEvents();
    });

    /* ============================================================
       Cargar logs con filtros
       ============================================================ */
    function loadLogs() {
        showState('loading');

        $.post(cfg.ajaxUrl, buildParams()).done(function (res) {
            if (!res.success || !res.data.logs.length) {
                showState('empty');
                return;
            }
            const d = res.data;
            totalPages = d.pages;
            renderLogs(d.logs);
            $('#audit-total-count').text(d.total);
            $('#audit-stats-bar').show();
            renderPagination(d.page, d.pages);
            showState('results');
        }).fail(function () {
            showState('empty');
        });
    }

    /* ============================================================
       Construir parámetros POST
       ============================================================ */
    function buildParams(page) {
        return {
            action:           'aura_audit_get_logs',
            nonce:            cfg.nonce,
            page:             page || currentPage,
            filter_user:      $('#f-user').val()      || '',
            filter_action:    $('#f-action').val()    || '',
            filter_entity:    $('#f-entity').val()    || '',
            filter_date_from: $('#f-date-from').val() || '',
            filter_date_to:   $('#f-date-to').val()   || '',
            filter_ip:        $('#f-ip').val()        || '',
        };
    }

    /* ============================================================
       Renderizar tabla de logs
       ============================================================ */
    function renderLogs(logs) {
        const $body = $('#audit-log-body');
        $body.empty();

        logs.forEach(function (log) {
            const actionLabel = cfg.actionLabels[log.action] || log.action;
            const actionBadge = buildActionBadge(log.action, actionLabel);
            const entityLabel = cfg.i18n.entityLabels[log.entity_type] || log.entity_type;
            const diffHtml    = buildDiffButton(log);
            const dateStr     = formatDate(log.created_at);

            const $tr = $(`<tr class="audit-row" data-id="${log.id}">
                <td>
                    <span class="audit-date">${escHtml(dateStr)}</span>
                </td>
                <td>${escHtml(log.user_name || '—')}</td>
                <td>${actionBadge}</td>
                <td style="text-align:center;">
                    <span class="audit-entity-badge audit-entity-${escHtml(log.entity_type)}">${escHtml(entityLabel)}</span>
                </td>
                <td style="text-align:center;">
                    ${log.entity_id ? `<code>${log.entity_id}</code>` : '—'}
                </td>
                <td style="text-align:center;">
                    <code class="audit-ip">${escHtml(log.ip_address || '—')}</code>
                </td>
                <td>${diffHtml}</td>
            </tr>`);

            // Fila expandible con diff y user-agent
            const $detail = buildDetailRow(log);
            $body.append($tr).append($detail);
        });
    }

    /* ============================================================
       Badge de acción (color según tipo)
       ============================================================ */
    function buildActionBadge(action, label) {
        let cls = 'audit-action-neutral';
        if (action.includes('created'))   cls = 'audit-action-create';
        if (action.includes('updated'))   cls = 'audit-action-update';
        if (action.includes('deleted'))   cls = 'audit-action-delete';
        if (action.includes('restored'))  cls = 'audit-action-restore';
        if (action.includes('approved'))  cls = 'audit-action-approve';
        if (action.includes('rejected'))  cls = 'audit-action-reject';
        if (action.includes('exceed'))    cls = 'audit-action-alert';
        if (action.includes('import') || action.includes('export')) cls = 'audit-action-io';
        return `<span class="audit-action-badge ${cls}">${escHtml(label)}</span>`;
    }

    /* ============================================================
       Botón "Ver cambios"
       ============================================================ */
    function buildDiffButton(log) {
        if (!log.old_value && !log.new_value) {
            return `<span style="color:#aaa">${cfg.i18n.noChanges}</span>`;
        }
        return `<button class="button button-small btn-show-diff" data-id="${log.id}">
                    <span class="dashicons dashicons-visibility" style="line-height:26px;"></span>
                    ${cfg.i18n.diff}
                </button>`;
    }

    /* ============================================================
       Fila de detalle (colapsable)
       ============================================================ */
    function buildDetailRow(log) {
        let diffHtml = '';

        if (log.old_value || log.new_value) {
            diffHtml = '<table class="audit-diff-table">';
            diffHtml += '<tr><th>Campo</th><th>Antes</th><th>Después</th></tr>';

            const allKeys = new Set([
                ...Object.keys(log.old_value || {}),
                ...Object.keys(log.new_value || {}),
            ]);

            allKeys.forEach(function (key) {
                const fieldLabel = cfg.i18n.fields[key] || key;
                const oldVal = log.old_value && log.old_value[key] !== undefined
                    ? String(log.old_value[key]) : '—';
                const newVal = log.new_value && log.new_value[key] !== undefined
                    ? String(log.new_value[key]) : '—';
                const changed = oldVal !== newVal;
                diffHtml += `<tr${changed ? ' class="diff-changed"' : ''}>
                    <td><strong>${escHtml(fieldLabel)}</strong></td>
                    <td class="diff-old">${escHtml(oldVal)}</td>
                    <td class="diff-new">${escHtml(newVal)}</td>
                </tr>`;
            });
            diffHtml += '</table>';
        }

        const uaHtml = log.user_agent
            ? `<p class="audit-user-agent"><strong>User Agent:</strong> ${escHtml(log.user_agent)}</p>`
            : '';

        return $(`<tr class="audit-detail-row" id="detail-${log.id}" style="display:none;">
            <td colspan="7">
                <div class="audit-detail-inner">
                    ${diffHtml}
                    ${uaHtml}
                </div>
            </td>
        </tr>`);
    }

    /* ============================================================
       Paginación
       ============================================================ */
    function renderPagination(page, pages) {
        const $pag = $('#audit-pagination');
        if (pages <= 1) { $pag.hide(); return; }

        let html = cfg.i18n.page + ' ';
        const start = Math.max(1, page - 3);
        const end   = Math.min(pages, page + 3);

        if (start > 1) html += `<button class="page-btn" data-page="1">1</button>…`;
        for (let i = start; i <= end; i++) {
            html += `<button class="page-btn${i === page ? ' active' : ''}" data-page="${i}">${i}</button>`;
        }
        if (end < pages) html += `…<button class="page-btn" data-page="${pages}">${pages}</button>`;
        html += ` ${cfg.i18n.of} ${pages}`;
        $pag.html(html).show();
    }

    /* ============================================================
       Eventos
       ============================================================ */
    function bindEvents() {

        /* Aplicar filtros */
        $('#btn-apply-audit-filters').on('click', function () {
            currentPage = 1;
            loadLogs();
        });

        /* Limpiar filtros */
        $('#btn-clear-audit-filters').on('click', function () {
            $('#f-user, #f-action, #f-entity').val('');
            $('#f-date-from, #f-date-to, #f-ip').val('');
            currentPage = 1;
            loadLogs();
        });

        /* Filtro al pulsar Enter en campo IP */
        $('#f-ip').on('keydown', function (e) {
            if (e.key === 'Enter') { currentPage = 1; loadLogs(); }
        });

        /* Paginación */
        $(document).on('click', '.page-btn', function () {
            const p = parseInt($(this).data('page'));
            if (p !== currentPage) { currentPage = p; loadLogs(); }
        });

        /* Expandir/colapsar detalle */
        $(document).on('click', '.btn-show-diff', function () {
            const id     = $(this).data('id');
            const $detail = $('#detail-' + id);
            $detail.toggle();
            $(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
        });

        /* Exportar CSV */
        $('#btn-export-audit').on('click', function () {
            $(this).prop('disabled', true).text('Exportando…');
            $.post(cfg.ajaxUrl, {
                action: 'aura_audit_export_csv',
                nonce:  cfg.nonce,
            }).done(function (res) {
                if (res.success) {
                    downloadBase64Csv(res.data.content, res.data.filename);
                    showToast(cfg.i18n.exportOk, 'success');
                }
            }).always(function () {
                $('#btn-export-audit').prop('disabled', false).html(
                    '<span class="dashicons dashicons-download"></span> Exportar todos (CSV)'
                );
            });
        });

        /* Purgar logs */
        $('#btn-purge-logs').on('click', function () {
            const days = parseInt($('#purge-days').val()) || 365;
            if (!confirm(cfg.i18n.confirmPurge)) return;
            $.post(cfg.ajaxUrl, {
                action: 'aura_audit_purge',
                nonce:  cfg.nonce,
                days:   days,
            }).done(function (res) {
                if (res.success) {
                    showToast(res.data.message, 'success');
                    currentPage = 1;
                    loadLogs();
                }
            });
        });
    }

    /* ============================================================
       Estado del panel
       ============================================================ */
    function showState(state) {
        $('#audit-loading, #audit-empty, #audit-log-table, #audit-stats-bar').hide();
        if (state === 'loading')  $('#audit-loading').show();
        if (state === 'empty')    $('#audit-empty').show();
        if (state === 'results')  $('#audit-log-table, #audit-stats-bar').show();
    }

    /* ============================================================
       Utils
       ============================================================ */
    function formatDate(dt) {
        if (!dt) return '—';
        const d = new Date(dt.replace(' ', 'T'));
        return d.toLocaleDateString('es-MX') + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }

    function downloadBase64Csv(b64, filename) {
        const byteStr = atob(b64);
        const buffer  = new Uint8Array(byteStr.length);
        for (let i = 0; i < byteStr.length; i++) buffer[i] = byteStr.charCodeAt(i);
        const blob = new Blob([buffer], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = filename; a.click();
        URL.revokeObjectURL(url);
    }

    function showToast(msg, type) {
        const color = type === 'success' ? '#1e7e34' : '#c00';
        const $t = $('<div>').css({
            position: 'fixed', bottom: '24px', right: '24px',
            background: color, color: '#fff', padding: '12px 20px',
            borderRadius: '6px', zIndex: 99999, fontSize: '14px',
            boxShadow: '0 4px 12px rgba(0,0,0,.2)',
        }).text(msg);
        $('body').append($t);
        setTimeout(() => $t.fadeOut(300, () => $t.remove()), 4000);
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
