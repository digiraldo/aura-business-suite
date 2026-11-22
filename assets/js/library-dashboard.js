/**
 * Library Dashboard JS — Fase 6
 * Carga KPIs via AJAX y renderiza 3 gráficos Chart.js + 3 listas rápidas.
 *
 * @package AuraBusinessSuite
 */
/* global jQuery, Chart, auraLibDash */
    function __(s) { return (window.wp && wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__(s, 'aura-suite') : s; }
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

    function isDarkMode() {
        const root = document.documentElement;
        const body = document.body;
        return root.getAttribute('data-theme') === 'dark'
            || root.getAttribute('data-wp-dark-mode-scheme') === 'dark'
            || root.classList.contains('aura-dark-mode')
            || root.classList.contains('wp-dark-mode-active')
            || (body && (
                body.getAttribute('data-theme') === 'dark'
                || body.getAttribute('data-wp-dark-mode-scheme') === 'dark'
                || body.classList.contains('aura-dark-mode')
                || body.classList.contains('wp-dark-mode-active')
                || body.classList.contains('dark-mode')
            ));
    }

    function getChartPalette() {
        const dark = isDarkMode();
        return {
            tickColor:  dark ? '#a8a8c8' : '#475569',
            gridColor:  dark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(15, 23, 42, 0.08)',
            legendColor: dark ? '#f0efff' : '#334155',
            donutBorder: dark ? '#16152b' : '#ffffff',
        };
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
            $('#kpi-fines').text('$' + parseFloat(d.pending_fines || 0).toFixed(2));

            // Resaltar si hay vencidos
            const $card = $('#kpi-overdue').closest('.aura-stat-card, .aura-lib-kpi-card');
            if (parseInt(d.overdue_loans, 10) > 0) {
                $card.addClass('aura-lib-kpi-card--alert');
            } else {
                $card.removeClass('aura-lib-kpi-card--alert');
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
            const pal = getChartPalette();
            if (loansChart) loansChart.destroy();
            loansChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: auraLibDash.i18n.loans || __('Préstamos', 'aura-suite'),
                        data,
                        backgroundColor: 'rgba(79, 70, 229, 0.72)',
                        borderColor: '#4f46e5',
                        borderWidth: 1,
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            ticks: { color: pal.tickColor },
                            grid: { color: pal.gridColor },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: pal.tickColor },
                            grid: { color: pal.gridColor },
                        },
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
            const pal = getChartPalette();
            if (statusChart) statusChart.destroy();
            statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors, borderColor: pal.donutBorder, borderWidth: 2 }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, color: pal.legendColor, font: { size: 11 } } },
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
            const pal = getChartPalette();
            if (deweyChart) deweyChart.destroy();
            deweyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: '#10b981',
                        borderWidth: 1,
                        borderRadius: 6,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: pal.tickColor },
                            grid: { color: pal.gridColor },
                        },
                        y: {
                            ticks: { color: pal.tickColor },
                            grid: { color: pal.gridColor },
                        },
                    },
                },
            });
        });
    }

    function refreshChartTheme() {
        const pal = getChartPalette();
        if (loansChart && loansChart.options && loansChart.options.scales) {
            loansChart.options.scales.x.ticks.color = pal.tickColor;
            loansChart.options.scales.x.grid.color  = pal.gridColor;
            loansChart.options.scales.y.ticks.color = pal.tickColor;
            loansChart.options.scales.y.grid.color  = pal.gridColor;
            loansChart.update('none');
        }
        if (statusChart && statusChart.options) {
            if (statusChart.data && statusChart.data.datasets && statusChart.data.datasets[0]) {
                statusChart.data.datasets[0].borderColor = pal.donutBorder;
            }
            if (statusChart.options.plugins && statusChart.options.plugins.legend && statusChart.options.plugins.legend.labels) {
                statusChart.options.plugins.legend.labels.color = pal.legendColor;
            }
            statusChart.update('none');
        }
        if (deweyChart && deweyChart.options && deweyChart.options.scales) {
            deweyChart.options.scales.x.ticks.color = pal.tickColor;
            deweyChart.options.scales.x.grid.color  = pal.gridColor;
            deweyChart.options.scales.y.ticks.color = pal.tickColor;
            deweyChart.options.scales.y.grid.color  = pal.gridColor;
            deweyChart.update('none');
        }
    }

    // ─── Lista 1: Préstamos vencidos ────────────────────────────

    function loadOverdueList() {
        const $container = $('#aura-lib-overdue-list');
        ajax('aura_library_dashboard_overdue_list').done(function (res) {
            hideSpinner('aura-lib-overdue-spinner');
            if (!res.success) { $container.html('<p>' + (auraLibDash.i18n.error || __('Error', 'aura-suite')) + '</p>'); return; }
            const items = res.data;
            if (!items.length) {
                $container.html('<p class="aura-lib-empty">' + (auraLibDash.i18n.no_overdue || __('Sin préstamos vencidos.', 'aura-suite')) + '</p>');
                return;
            }
            let html = '<ul class="aura-lib-quick-list">';
            items.forEach(function (item) {
                html += `<li class="aura-lib-quick-item aura-lib-quick-item--alert">
                    <span class="aura-lib-quick-icon dashicons dashicons-warning"></span>
                    <div class="aura-lib-quick-info">
                        <strong>${escHtml(item.book_title)}</strong>
                        <span>${escHtml(item.borrower)} — ${item.days_overdue} ${auraLibDash.i18n.days_late || __('días', 'aura-suite')}</span>
                    </div>
                    <span class="badge badge-red aura-lib-quick-badge">${item.days_overdue}d</span>
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
            if (!res.success) { $container.html('<p>' + (auraLibDash.i18n.error || __('Error', 'aura-suite')) + '</p>'); return; }
            const items = res.data;
            if (!items.length) {
                $container.html('<p class="aura-lib-empty">' + (auraLibDash.i18n.no_loans || __('Sin préstamos registrados.', 'aura-suite')) + '</p>');
                return;
            }
            let html = '<ul class="aura-lib-quick-list">';
            items.forEach(function (item) {
                const cover = item.cover
                    ? `<span class="aura-img-preview-trigger aura-lib-thumb-wrap" data-preview-title="${escHtml(item.title)}"><img src="${escHtml(item.cover)}" alt="${escHtml(item.title)}" class="aura-lib-thumb"></span>`
                    : '<span class="aura-lib-thumb aura-lib-thumb--placeholder dashicons dashicons-book-alt"></span>';
                html += `<li class="aura-lib-quick-item">
                    ${cover}
                    <div class="aura-lib-quick-info">
                        <strong>${escHtml(item.title)}</strong>
                        <span>${escHtml(item.author || '')}</span>
                    </div>
                    <span class="badge badge-indigo aura-lib-quick-badge">${item.loan_count}</span>
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
            if (!res.success) { $container.html('<p>' + (auraLibDash.i18n.error || __('Error', 'aura-suite')) + '</p>'); return; }
            const items = res.data;
            if (!items.length) {
                $container.html('<p class="aura-lib-empty">' + (auraLibDash.i18n.no_reservations || __('Sin reservas pendientes.', 'aura-suite')) + '</p>');
                return;
            }
            let html = '<ul class="aura-lib-quick-list">';
            items.forEach(function (item) {
                const badgeClass = item.status === 'notified' ? 'badge-emerald' : 'badge-amber';
                const statusTxt  = item.status === 'notified' ? __('Notificado', 'aura-suite') : (item.status === 'waiting' ? __('En espera', 'aura-suite') : item.status);
                html += `<li class="aura-lib-quick-item">
                    <span class="aura-lib-quick-icon dashicons dashicons-calendar"></span>
                    <div class="aura-lib-quick-info">
                        <strong>${escHtml(item.book_title)}</strong>
                        <span>${escHtml(item.user_name)} — ${escHtml(item.reserved_at)}</span>
                    </div>
                    <span class="badge ${badgeClass} aura-lib-quick-badge">${escHtml(statusTxt)}</span>
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

        if (window.MutationObserver) {
            const themeObserver = new MutationObserver(refreshChartTheme);
            themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-wp-dark-mode-scheme'] });
            if (document.body) {
                themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-wp-dark-mode-scheme'] });
            }
        }
    });

})(jQuery);
