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

            // Top libros
            let html = '';
            (d.top_books || []).forEach(function (b, i) {
                html += `<tr>
                    <td>${i + 1}</td>
                    <td>${escHtml(b.title)}</td>
                    <td>${escHtml(b.author || '')}</td>
                    <td><strong>${escHtml(b.loan_count)}</strong></td>
                </tr>`;
            });
            $('#activity-top-books-body').html(html || `<tr><td colspan="4">${I18N.no_data || ''}</td></tr>`);

            // Top lectores
            let html2 = '';
            (d.top_readers || []).forEach(function (r, i) {
                html2 += `<tr>
                    <td>${i + 1}</td>
                    <td>${escHtml(r.display_name)}</td>
                    <td><strong>${escHtml(r.loan_count)}</strong></td>
                </tr>`;
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
            rows.forEach(function (r) {
                html += `<tr>
                    <td>${escHtml(r.dewey_class)}</td>
                    <td>${escHtml(r.title)}</td>
                    <td>${escHtml(r.author || '')}</td>
                    <td><strong>${escHtml(r.total_loans)}</strong></td>
                </tr>`;
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
                html += `<tr>
                    <td>${i + 1}</td>
                    <td>${escHtml(r.book_title)}</td>
                    <td>${escHtml(r.borrower)}</td>
                    <td>${escHtml(r.due_date)}</td>
                    <td><span style="color:#d63638;font-weight:700;">${escHtml(r.days_overdue)}</span></td>
                    <td>${escHtml(r.fine_amount)}</td>
                </tr>`;
            });
            $('#overdue-current-body').html(html || `<tr><td colspan="6">${I18N.no_data || ''}</td></tr>`);
            $('#overdue-current-table').show();

            // Multas cobradas
            let html2 = '';
            (d.paid_fines || []).forEach(function (r) {
                html2 += `<tr>
                    <td>${escHtml(r.book_title)}</td>
                    <td>${escHtml(r.borrower)}</td>
                    <td><strong>${escHtml(r.fine_amount)}</strong></td>
                    <td>${escHtml(r.return_date || '')}</td>
                </tr>`;
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
            (d.by_status || []).forEach(function (r) {
                html += `<tr>
                    <td>${escHtml(statusLabels[r.status] || r.status)}</td>
                    <td>${escHtml(r.cnt)}</td>
                    <td>${escHtml(r.total_copies)}</td>
                </tr>`;
            });
            $('#inventory-by-status-body').html(html || `<tr><td colspan="3">${I18N.no_data || ''}</td></tr>`);

            // Mayor rotación
            let html3 = '';
            (d.top_rotation || []).forEach(function (r, i) {
                html3 += `<tr>
                    <td>${i + 1}</td>
                    <td>${escHtml(r.title)}</td>
                    <td>${escHtml(r.dewey_number || '')}</td>
                    <td><strong>${escHtml(r.total_loans)}</strong></td>
                </tr>`;
            });
            $('#inventory-top-rotation-body').html(html3 || `<tr><td colspan="4">${I18N.no_data || ''}</td></tr>`);

            // Inactivos
            let html2 = '';
            (d.inactive || []).forEach(function (r) {
                html2 += `<tr>
                    <td>${escHtml(r.title)}</td>
                    <td>${escHtml(r.author || '')}</td>
                    <td>${escHtml(r.total_copies)}</td>
                </tr>`;
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
    });

})(jQuery);
