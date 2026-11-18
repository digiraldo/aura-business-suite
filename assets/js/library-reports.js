/**
 * Library Reports JS — Fase 6
 * Gestión de pestañas, carga de tablas AJAX y exportaciones CSV/PDF.
 *
 * @package AuraBusinessSuite
 */
/* global jQuery, auraLibReports */
(function ($) {
    'use strict';

    const NONCE    = (typeof auraLibReports !== 'undefined') ? auraLibReports.nonce : '';
    const AJAX_URL = (typeof auraLibReports !== 'undefined') ? auraLibReports.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    const I18N     = (typeof auraLibReports !== 'undefined') ? auraLibReports.i18n : {};

    function ajax(action, extraData) {
        return $.post(AJAX_URL, Object.assign({ action, nonce: NONCE }, extraData));
    }

    function spinner(id, show) {
        const $s = $('#' + id);
        show ? $s.addClass('is-active') : $s.removeClass('is-active');
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─── Tabs ───────────────────────────────────────────────────

    function initTabs() {
        $(document).on('click', '#aura-lib-report-tabs .nav-tab', function (e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            $('#aura-lib-report-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.aura-lib-tab-panel').hide();
            $('#tab-' + tab).show();
        });
    }

    // ─── Tab 1: Actividad General ───────────────────────────────

    function loadActivity() {
        const period = $('#activity-period').val() || 'month';
        spinner('activity-spinner', true);
        ajax('aura_library_report_activity', { period }).done(function (res) {
            spinner('activity-spinner', false);
            if (!res.success) return;
            const d = res.data;

            // Resumen
            $('#activity-total').text(d.loans_in_period || 0);
            $('#activity-on-time').text(d.on_time || 0);
            $('#activity-late').text(d.late || 0);
            $('#activity-summary').show();

            function repToggleBtn(childId) {
                return '<button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="' + escHtml(childId) + '" aria-expanded="false" data-tooltip="Desplegar detalles completos de la fila"><span class="dashicons dashicons-plus-alt2"></span></button>';
            }

            // Top libros
            let html = '';
            (d.top_books || []).forEach(function (b, i) {
                const cid = 'rep-top-book-' + i;
                html += `<tr class="aura-parent-row">
                    <td data-label="#" class="aura-lib-col-expand"><div class="aura-id-toggle-wrap">${repToggleBtn(cid)}<span>${i + 1}</span></div></td>
                    <td data-label="Título" class="aura-lib-col-main" data-tooltip="${escHtml(b.title)}${b.author ? ' — ' + escHtml(b.author) : ''}"><strong>${escHtml(b.title)}</strong><div class="aura-lib-mobile-sub">${escHtml(b.author || '—')}</div></td>
                    <td data-label="Autor" class="aura-lib-col-secondary">${escHtml(b.author || '—')}</td>
                    <td data-label="Préstamos" class="aura-lib-col-stat"><strong data-tooltip="${escHtml(b.loan_count)} préstamos en el período">${escHtml(b.loan_count)}</strong></td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="4"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Posición</span><strong>#${i + 1}</strong></div>
                    <div class="aura-child-meta-item"><span>Título</span><strong>${escHtml(b.title)}</strong></div>
                    <div class="aura-child-meta-item"><span>Autor</span><strong>${escHtml(b.author || '—')}</strong></div>
                    <div class="aura-child-meta-item"><span>Préstamos</span><strong>${escHtml(b.loan_count)}</strong></div>
                </div></div></td></tr>`;
            });
            $('#activity-top-books-body').html(html || `<tr><td colspan="4">${I18N.no_data || ''}</td></tr>`);

            // Top lectores
            let html2 = '';
            (d.top_readers || []).forEach(function (r, i) {
                const cid = 'rep-top-reader-' + i;
                html2 += `<tr class="aura-parent-row">
                    <td data-label="#" class="aura-lib-col-expand"><div class="aura-id-toggle-wrap">${repToggleBtn(cid)}<span>${i + 1}</span></div></td>
                    <td data-label="Lector" class="aura-lib-col-main" data-tooltip="Lector activo: ${escHtml(r.display_name)}"><strong>${escHtml(r.display_name)}</strong></td>
                    <td data-label="Préstamos" class="aura-lib-col-stat"><strong data-tooltip="${escHtml(r.loan_count)} préstamos realizados">${escHtml(r.loan_count)}</strong></td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="3"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Posición</span><strong>#${i + 1}</strong></div>
                    <div class="aura-child-meta-item"><span>Lector</span><strong>${escHtml(r.display_name)}</strong></div>
                    <div class="aura-child-meta-item"><span>Préstamos</span><strong>${escHtml(r.loan_count)}</strong></div>
                </div></div></td></tr>`;
            });
            $('#activity-top-readers-body').html(html2 || `<tr><td colspan="3">${I18N.no_data || ''}</td></tr>`);

            $('#activity-tables').show();
        });
    }

    // ─── Tab 2: Dewey ───────────────────────────────────────────

    function loadDewey() {
        spinner('dewey-spinner', true);
        ajax('aura_library_report_dewey').done(function (res) {
            spinner('dewey-spinner', false);
            if (!res.success) return;
            const rows = res.data.rows || [];
            let html   = '';
            rows.forEach(function (r, i) {
                const cid = 'rep-dewey-' + i;
                html += `<tr class="aura-parent-row">
                    <td data-label="Dewey" class="aura-lib-col-expand"><div class="aura-id-toggle-wrap"><button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="${cid}" aria-expanded="false" data-tooltip="Desplegar ficha del registro"><span class="dashicons dashicons-plus-alt2"></span></button><span class="badge badge-indigo" data-tooltip="Clase Dewey ${escHtml(r.dewey_class)}">${escHtml(r.dewey_class)}</span></div></td>
                    <td data-label="Título" class="aura-lib-col-main"><strong>${escHtml(r.title)}</strong><div class="aura-lib-mobile-sub">${escHtml(r.author || '—')}</div></td>
                    <td data-label="Autor" class="aura-lib-col-secondary">${escHtml(r.author || '—')}</td>
                    <td data-label="Préstamos" class="aura-lib-col-stat"><strong>${escHtml(r.total_loans)}</strong></td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="4"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Clase Dewey</span><strong>${escHtml(r.dewey_class)}</strong></div>
                    <div class="aura-child-meta-item"><span>Título</span><strong>${escHtml(r.title)}</strong></div>
                    <div class="aura-child-meta-item"><span>Autor</span><strong>${escHtml(r.author || '—')}</strong></div>
                    <div class="aura-child-meta-item"><span>Préstamos</span><strong>${escHtml(r.total_loans)}</strong></div>
                </div></div></td></tr>`;
            });
            $('#dewey-body').html(html || `<tr><td colspan="4">${I18N.no_data || ''}</td></tr>`);
            $('#dewey-table').show();
        });
    }

    // ─── Tab 3: Morosidad ───────────────────────────────────────

    function loadOverdue() {
        spinner('overdue-spinner', true);
        ajax('aura_library_report_overdue').done(function (res) {
            spinner('overdue-spinner', false);
            if (!res.success) return;
            const d = res.data;

            // Resumen
            $('#overdue-count').text((d.current_overdue || []).length);
            $('#overdue-collected').text(parseFloat(d.total_collected || 0).toFixed(2));
            $('#overdue-summary').show();

            // Vencidos actuales
            let html = '';
            (d.current_overdue || []).forEach(function (r, i) {
                const cid = 'rep-ov-curr-' + i;
                html += `<tr class="aura-parent-row">
                    <td data-label="#" class="aura-lib-col-expand"><div class="aura-id-toggle-wrap"><button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="${cid}" aria-expanded="false" data-tooltip="Desplegar detalles de morosidad"><span class="dashicons dashicons-plus-alt2"></span></button><span>${i + 1}</span></div></td>
                    <td data-label="Libro" class="aura-lib-col-main"><strong>${escHtml(r.book_title)}</strong><div class="aura-lib-mobile-sub">👤 ${escHtml(r.borrower)} · ${escHtml(r.due_date)}</div></td>
                    <td data-label="Lector" class="aura-lib-col-secondary">${escHtml(r.borrower)}</td>
                    <td data-label="Fec. Venc." class="aura-lib-col-secondary" data-tooltip="Debió devolverse el ${escHtml(r.due_date)}">${escHtml(r.due_date)}</td>
                    <td data-label="Días" class="aura-lib-col-stat"><span class="badge badge-red" data-tooltip="${escHtml(r.days_overdue)} días en mora">${escHtml(r.days_overdue)}</span></td>
                    <td data-label="Multa" class="aura-lib-col-secondary" data-tooltip="Multa calculada al día de hoy">${escHtml(r.fine_amount)}</td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="6"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Libro</span><strong>${escHtml(r.book_title)}</strong></div>
                    <div class="aura-child-meta-item"><span>Lector</span><strong>${escHtml(r.borrower)}</strong></div>
                    <div class="aura-child-meta-item"><span>Vencimiento</span><strong>${escHtml(r.due_date)}</strong></div>
                    <div class="aura-child-meta-item"><span>Días de Atraso</span><strong>${escHtml(r.days_overdue)}</strong></div>
                    <div class="aura-child-meta-item"><span>Multa</span><strong>${escHtml(r.fine_amount)}</strong></div>
                </div></div></td></tr>`;
            });
            $('#overdue-current-body').html(html || `<tr><td colspan="6">${I18N.no_data || ''}</td></tr>`);
            $('#overdue-current-table').show();

            // Multas cobradas
            let html2 = '';
            (d.paid_fines || []).forEach(function (r, i) {
                const cid = 'rep-ov-paid-' + i;
                html2 += `<tr class="aura-parent-row">
                    <td data-label="Libro" class="aura-lib-col-main"><div class="aura-id-toggle-wrap"><button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="${cid}" aria-expanded="false" data-tooltip="Desplegar detalles del cobro"><span class="dashicons dashicons-plus-alt2"></span></button><strong>${escHtml(r.book_title)}</strong></div><div class="aura-lib-mobile-sub">👤 ${escHtml(r.borrower)}</div></td>
                    <td data-label="Lector" class="aura-lib-col-secondary">${escHtml(r.borrower)}</td>
                    <td data-label="Monto" class="aura-lib-col-stat"><strong data-tooltip="Monto pagado por retraso/daño">${escHtml(r.fine_amount)}</strong></td>
                    <td data-label="Fecha Devolución" class="aura-lib-col-secondary">${escHtml(r.return_date || '—')}</td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="4"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Libro</span><strong>${escHtml(r.book_title)}</strong></div>
                    <div class="aura-child-meta-item"><span>Lector</span><strong>${escHtml(r.borrower)}</strong></div>
                    <div class="aura-child-meta-item"><span>Monto Cobrado</span><strong>${escHtml(r.fine_amount)}</strong></div>
                    <div class="aura-child-meta-item"><span>Fecha Devolución</span><strong>${escHtml(r.return_date || '—')}</strong></div>
                </div></div></td></tr>`;
            });
            $('#overdue-paid-body').html(html2 || `<tr><td colspan="4">${I18N.no_data || ''}</td></tr>`);
            $('#overdue-paid-table').show();
        });
    }

    // ─── Tab 4: Inventario ──────────────────────────────────────

    const statusLabels = {
        available:      'Disponible',
        loaned:         'Prestado',
        reserved:       'Reservado',
        maintenance:    'Mantenimiento',
        reference_only: 'Solo Consulta',
        inactive:       'Inactivo',
    };

    function loadInventory() {
        const months = $('#inventory-months').val() || 6;
        spinner('inventory-spinner', true);
        ajax('aura_library_report_inventory', { inactive_months: months }).done(function (res) {
            spinner('inventory-spinner', false);
            if (!res.success) return;
            const d = res.data;

            // Por estado
            let html = '';
            (d.by_status || []).forEach(function (r, i) {
                const cid = 'rep-inv-st-' + i;
                html += `<tr class="aura-parent-row">
                    <td data-label="Estado" class="aura-lib-col-main"><div class="aura-id-toggle-wrap"><button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="${cid}" aria-expanded="false" data-tooltip="Desplegar resumen de estado"><span class="dashicons dashicons-plus-alt2"></span></button><span class="badge badge-slate">${escHtml(statusLabels[r.status] || r.status)}</span></div></td>
                    <td data-label="Títulos" class="aura-lib-col-secondary">${escHtml(r.cnt)}</td>
                    <td data-label="Ejemplares" class="aura-lib-col-stat">${escHtml(r.total_copies)}</td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="3"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Estado</span><strong>${escHtml(statusLabels[r.status] || r.status)}</strong></div>
                    <div class="aura-child-meta-item"><span>Títulos Distintos</span><strong>${escHtml(r.cnt)}</strong></div>
                    <div class="aura-child-meta-item"><span>Total Ejemplares</span><strong>${escHtml(r.total_copies)}</strong></div>
                </div></div></td></tr>`;
            });
            $('#inventory-by-status-body').html(html || `<tr><td colspan="3">${I18N.no_data || ''}</td></tr>`);

            // Mayor rotación
            let html3 = '';
            (d.top_rotation || []).forEach(function (r, i) {
                const cid = 'rep-inv-rot-' + i;
                html3 += `<tr class="aura-parent-row">
                    <td data-label="#" class="aura-lib-col-expand"><div class="aura-id-toggle-wrap"><button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="${cid}" aria-expanded="false" data-tooltip="Desplegar detalles de rotación"><span class="dashicons dashicons-plus-alt2"></span></button><span>${i + 1}</span></div></td>
                    <td data-label="Título" class="aura-lib-col-main"><strong>${escHtml(r.title)}</strong></td>
                    <td data-label="Dewey" class="aura-lib-col-secondary"><code>${escHtml(r.dewey_number || '—')}</code></td>
                    <td data-label="Préstamos" class="aura-lib-col-stat"><strong>${escHtml(r.total_loans)}</strong></td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="4"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Ranking</span><strong>#${i + 1}</strong></div>
                    <div class="aura-child-meta-item"><span>Título</span><strong>${escHtml(r.title)}</strong></div>
                    <div class="aura-child-meta-item"><span>Dewey</span><strong>${escHtml(r.dewey_number || '—')}</strong></div>
                    <div class="aura-child-meta-item"><span>Préstamos</span><strong>${escHtml(r.total_loans)}</strong></div>
                </div></div></td></tr>`;
            });
            $('#inventory-top-rotation-body').html(html3 || `<tr><td colspan="4">${I18N.no_data || ''}</td></tr>`);

            // Inactivos
            let html2 = '';
            (d.inactive || []).forEach(function (r, i) {
                const cid = 'rep-inv-inac-' + i;
                html2 += `<tr class="aura-parent-row">
                    <td data-label="Título" class="aura-lib-col-main" data-tooltip="Sin circulación en los últimos ${escHtml(months)} meses"><div class="aura-id-toggle-wrap"><button type="button" class="aura-lib-row-toggle aura-report-row-toggle" data-target="${cid}" aria-expanded="false" data-tooltip="Desplegar ficha inactiva"><span class="dashicons dashicons-plus-alt2"></span></button><strong>${escHtml(r.title)}</strong></div><div class="aura-lib-mobile-sub">${escHtml(r.author || '—')}</div></td>
                    <td data-label="Autor" class="aura-lib-col-secondary">${escHtml(r.author || '—')}</td>
                    <td data-label="Ejs." class="aura-lib-col-stat">${escHtml(r.total_copies)}</td>
                </tr>
                <tr class="aura-child-row" id="${cid}"><td colspan="3"><div class="aura-lib-child-card"><div class="aura-child-meta-grid">
                    <div class="aura-child-meta-item"><span>Título</span><strong>${escHtml(r.title)}</strong></div>
                    <div class="aura-child-meta-item"><span>Autor</span><strong>${escHtml(r.author || '—')}</strong></div>
                    <div class="aura-child-meta-item"><span>Ejemplares</span><strong>${escHtml(r.total_copies)}</strong></div>
                    <div class="aura-child-meta-item"><span>Inactividad</span><strong>>${escHtml(months)} meses sin préstamos</strong></div>
                </div></div></td></tr>`;
            });
            $('#inventory-inactive-body').html(html2 || `<tr><td colspan="3">${I18N.no_data || ''}</td></tr>`);

            $('#inventory-tables').show();
        });
    }

    // ─── Exportación CSV/PDF ────────────────────────────────────

    function handleExport() {
        $(document).on('click', '.aura-lib-export', function () {
            const reportType = $(this).data('type');
            const format     = $(this).data('format');
            const period     = $('#activity-period').val() || 'month';

            const $form = $('<form>', {
                method: 'POST',
                action: AJAX_URL,
            });

            const fields = {
                action:      'aura_library_export_' + format,
                nonce:       NONCE,
                report_type: reportType,
                period,
            };

            Object.entries(fields).forEach(([k, v]) => {
                $('<input>', { type: 'hidden', name: k, value: v }).appendTo($form);
            });

            $form.appendTo('body').submit().remove();
        });
    }

    // ─── Init ───────────────────────────────────────────────────

    $(function () {
        if (!$('#aura-lib-reports').length) return;

        initTabs();
        handleExport();

        // Load Activity on first render
        loadActivity();

        // Botones de carga
        $(document).on('click', '#activity-load',  loadActivity);
        $(document).on('click', '#dewey-load',     loadDewey);
        $(document).on('click', '#overdue-load',   loadOverdue);
        $(document).on('click', '#inventory-load', loadInventory);

        // Expandir / Colapsar filas en reportes (+ / -)
        $(document).on('click', '.aura-report-row-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const targetId = $btn.data('target');
            const $tr = $btn.closest('tr.aura-parent-row');
            const $ch = $('#' + targetId);
            if ($ch.hasClass('is-open')) {
                $ch.removeClass('is-open');
                $tr.removeClass('is-expanded');
                $btn.removeClass('is-active').attr('aria-expanded', 'false')
                    .html('<span class="dashicons dashicons-plus-alt2"></span>');
            } else {
                $ch.addClass('is-open');
                $tr.addClass('is-expanded');
                $btn.addClass('is-active').attr('aria-expanded', 'true')
                    .html('<span class="dashicons dashicons-minus"></span>');
            }
        });
    });

})(jQuery);
