/**
 * Library Dashboard JS — Fase 6
 * Carga KPIs via AJAX y renderiza 3 gráficos Chart.js + 3 listas rápidas.
 *
 * @package AuraBusinessSuite
 */
/* global jQuery, Chart, auraLibDash */
(function ($) {
    'use strict';

    const NONCE    = $('#aura-lib-dashboard').data('nonce') || '';
    const AJAX_URL = typeof ajaxurl !== 'undefined' ? ajaxurl : '';

    // ─── Helpers ────────────────────────────────────────────────

    function ajax(action, extraData) {
        return $.post(AJAX_URL, Object.assign({ action, nonce: NONCE }, extraData));
    }

    function hideSpinner(id) {
        $('#' + id).removeClass('is-active').hide();
    }

    // ─── KPIs ───────────────────────────────────────────────────

    function loadKpis() {
        ajax('aura_library_dashboard_kpis').done(function (res) {
            if (!res.success) return;
            const d = res.data;
            $('#kpi-total-books').text(number(d.total_books));
            $('#kpi-available').text(number(d.available_copies));
            $('#kpi-active-loans').text(number(d.active_loans));
            $('#kpi-overdue').text(number(d.overdue_loans));
            $('#kpi-reservations').text(number(d.pending_reservations));
            $('#kpi-fines').text(parseFloat(d.pending_fines).toFixed(2));

            // Resaltar si hay vencidos
            const $card = $('#kpi-overdue').closest('.aura-lib-kpi-card');
            if (parseInt(d.overdue_loans) > 0) {
                $card.addClass('aura-lib-kpi-card--alert');
            }
        });
    }

    // ─── Gráfico 1: Préstamos por mes (Bar) ────────────────────

    let loansChart = null;

    function loadLoansChart() {
        const spinner = 'aura-lib-loans-chart-spinner';
        ajax('aura_library_dashboard_loans_chart').done(function (res) {
            hideSpinner(spinner);
            if (!res.success) return;
            const { labels, data } = res.data;
            const ctx = document.getElementById('aura-lib-loans-chart');
            if (!ctx) return;
            if (loansChart) loansChart.destroy();
            loansChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: auraLibDash.i18n.loans || 'Préstamos',
                        data,
                        backgroundColor: 'rgba(34,113,177,0.7)',
                        borderColor: '#2271b1',
                        borderWidth: 1,
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    },
                },
            });
        });
    }

    // ─── Gráfico 2: Estado del catálogo (Donut) ────────────────

    let statusChart = null;

    function loadStatusChart() {
        const spinner = 'aura-lib-status-chart-spinner';
        ajax('aura_library_dashboard_status_chart').done(function (res) {
            hideSpinner(spinner);
            if (!res.success) return;
            const { labels, data, colors } = res.data;
            const ctx = document.getElementById('aura-lib-status-chart');
            if (!ctx) return;
            if (statusChart) statusChart.destroy();
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors, borderWidth: 2 }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                    cutout: '60%',
                },
            });
        });
    }

    // ─── Gráfico 3: Distribución Dewey (Bar horizontal) ────────

    let deweyChart = null;

    function loadDeweyChart() {
        const spinner = 'aura-lib-dewey-chart-spinner';
        ajax('aura_library_dashboard_dewey_chart').done(function (res) {
            hideSpinner(spinner);
            if (!res.success) return;
            const { labels, data } = res.data;
            const ctx = document.getElementById('aura-lib-dewey-chart');
            if (!ctx) return;
            if (deweyChart) deweyChart.destroy();
            deweyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: 'rgba(0,163,42,0.65)',
                        borderColor: '#00a32a',
                        borderWidth: 1,
                        borderRadius: 4,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        });
    }

    // ─── Lista 1: Préstamos vencidos ────────────────────────────

    function loadOverdueList() {
        const $container = $('#aura-lib-overdue-list');
        ajax('aura_library_dashboard_overdue_list').done(function (res) {
            hideSpinner('aura-lib-overdue-spinner');
            if (!res.success) { $container.html('<p>' + (auraLibDash.i18n.error || 'Error') + '</p>'); return; }
            const items = res.data;
            if (!items.length) {
                $container.html('<p class="aura-lib-empty">' + (auraLibDash.i18n.no_overdue || 'Sin préstamos vencidos.') + '</p>');
                return;
            }
            let html = '<ul class="aura-lib-quick-list">';
            items.forEach(function (item) {
                html += `<li class="aura-lib-quick-item aura-lib-quick-item--alert">
                    <span class="aura-lib-quick-icon dashicons dashicons-warning"></span>
                    <div class="aura-lib-quick-info">
                        <strong>${escHtml(item.book_title)}</strong>
                        <span>${escHtml(item.borrower)} — ${item.days_overdue} ${auraLibDash.i18n.days_late || 'días'}</span>
                    </div>
                    <div class="aura-lib-quick-badge aura-lib-badge--red">${item.days_overdue}d</div>
                </li>`;
            });
            html += '</ul>';
            $container.html(html);
        });
    }

    // ─── Lista 2: Top libros ────────────────────────────────────

    function loadTopBooks() {
        const $container = $('#aura-lib-top-books-list');
        ajax('aura_library_dashboard_top_books').done(function (res) {
            hideSpinner('aura-lib-top-books-spinner');
            if (!res.success) { $container.html('<p>' + (auraLibDash.i18n.error || 'Error') + '</p>'); return; }
            const items = res.data;
            if (!items.length) {
                $container.html('<p class="aura-lib-empty">' + (auraLibDash.i18n.no_loans || 'Sin préstamos registrados.') + '</p>');
                return;
            }
            let html = '<ul class="aura-lib-quick-list">';
            items.forEach(function (item, idx) {
                const cover = item.cover
                    ? `<img src="${item.cover}" alt="" class="aura-lib-thumb">`
                    : '<span class="aura-lib-thumb aura-lib-thumb--placeholder dashicons dashicons-book-alt"></span>';
                html += `<li class="aura-lib-quick-item">
                    ${cover}
                    <div class="aura-lib-quick-info">
                        <strong>${escHtml(item.title)}</strong>
                        <span>${escHtml(item.author || '')}</span>
                    </div>
                    <div class="aura-lib-quick-badge">${item.loan_count}</div>
                </li>`;
            });
            html += '</ul>';
            $container.html(html);
        });
    }

    // ─── Lista 3: Reservas pendientes ───────────────────────────

    function loadReservations() {
        const $container = $('#aura-lib-reservations-list');
        ajax('aura_library_dashboard_recent_res').done(function (res) {
            hideSpinner('aura-lib-reservations-spinner');
            if (!res.success) { $container.html('<p>' + (auraLibDash.i18n.error || 'Error') + '</p>'); return; }
            const items = res.data;
            if (!items.length) {
                $container.html('<p class="aura-lib-empty">' + (auraLibDash.i18n.no_reservations || 'Sin reservas pendientes.') + '</p>');
                return;
            }
            let html = '<ul class="aura-lib-quick-list">';
            items.forEach(function (item) {
                const statusClass = item.status === 'notified' ? 'aura-lib-badge--green' : 'aura-lib-badge--yellow';
                html += `<li class="aura-lib-quick-item">
                    <span class="aura-lib-quick-icon dashicons dashicons-calendar"></span>
                    <div class="aura-lib-quick-info">
                        <strong>${escHtml(item.book_title)}</strong>
                        <span>${escHtml(item.user_name)} — ${escHtml(item.reserved_at)}</span>
                    </div>
                    <div class="aura-lib-quick-badge ${statusClass}">${escHtml(item.status)}</div>
                </li>`;
            });
            html += '</ul>';
            $container.html(html);
        });
    }

    // ─── Utils ──────────────────────────────────────────────────

    function number(n) {
        return parseInt(n || 0).toLocaleString();
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─── Init ───────────────────────────────────────────────────

    $(function () {
        if (!$('#aura-lib-dashboard').length) return;

        loadKpis();
        loadLoansChart();
        loadStatusChart();
        loadDeweyChart();
        loadOverdueList();
        loadTopBooks();
        loadReservations();
    });

})(jQuery);
