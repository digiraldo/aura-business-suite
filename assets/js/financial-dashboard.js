/**
 * Dashboard Financiero — Fase 3, Item 3.1
 * Maneja gráficos Chart.js, presets de período y refresco AJAX.
 */
/* global jQuery, Chart, auraDashboard */

(function ($) {
    'use strict';

    // ─── Referencias DOM ─────────────────────────────────────────────────────
    var $periodBtns, $startDate, $endDate, $compareChk,
        lineChart, donutChart,
        refreshInProgress = false;

    // ─── Colores consistentes ─────────────────────────────────────────────────
    var COLORS = {
        income:  { fill: 'rgba(16,185,129,.15)',  border: '#10b981' },
        expense: { fill: 'rgba(239,68,68,.15)',    border: '#ef4444' },
        prevIncome:  { fill: 'rgba(16,185,129,.06)',  border: 'rgba(16,185,129,.4)' },
        prevExpense: { fill: 'rgba(239,68,68,.06)',    border: 'rgba(239,68,68,.4)' },
    };

    var PALETTE = [
        '#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6',
        '#06b6d4','#ec4899','#84cc16','#f97316','#14b8a6'
    ];

    // ─── Inicialización ───────────────────────────────────────────────────────
    $(document).ready(function () {
        $periodBtns = $('.period-btn');
        $startDate  = $('#dash-start');
        $endDate    = $('#dash-end');
        $compareChk = $('#dash-compare');

        // Restaurar preferencias guardadas
        loadStoredPreferences();

        // Construir gráficos vacíos
        initLineChart();
        initDonutChart();

        // Cargar datos iniciales
        fetchDashboardData();

        // ── Eventos periode ──────────────────────────────────────────────────
        $periodBtns.on('click', function () {
            var preset = $(this).data('period');
            if (preset === 'custom') return; // Lo maneja el botón Apply
            $periodBtns.removeClass('active');
            $(this).addClass('active');
            applyPreset(preset);
            savePref('period', preset);
            fetchDashboardData();
        });

        // Aplicar rango personalizado
        $('#dash-apply-custom').on('click', function () {
            if (!$startDate.val() || !$endDate.val()) {
                alert(auraDashboard.i18n.selectDates);
                return;
            }
            $periodBtns.removeClass('active');
            $('[data-period="custom"]').addClass('active');
            savePref('period', 'custom');
            savePref('start', $startDate.val());
            savePref('end',   $endDate.val());
            fetchDashboardData();
        });

        // Comparar período anterior
        $compareChk.on('change', function () {
            savePref('compare', $(this).is(':checked') ? '1' : '0');
            fetchDashboardData();
        });

        // Exportar gráfico de líneas
        $('#export-line-png').on('click', function () { exportChart(lineChart, 'ingresos-egresos.png'); });
        $('#export-donut-png').on('click', function () { exportChart(donutChart, 'categorias.png'); });

        // Refrescar manual
        $('#dash-refresh').on('click', function () { fetchDashboardData(); });
    });

    // ─── Presets de período ───────────────────────────────────────────────────
    function applyPreset(preset) {
        var today = new Date();
        var s, e;

        switch (preset) {
            case 'today':
                s = e = fmtDate(today);
                break;
            case 'week':
                var day = today.getDay();
                var mon = new Date(today); mon.setDate(today.getDate() - (day === 0 ? 6 : day - 1));
                s = fmtDate(mon);
                e = fmtDate(today);
                break;
            case 'month':
                s = fmtDate(new Date(today.getFullYear(), today.getMonth(), 1));
                e = fmtDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
                break;
            case 'quarter':
                var q = Math.floor(today.getMonth() / 3);
                s = fmtDate(new Date(today.getFullYear(), q * 3, 1));
                e = fmtDate(new Date(today.getFullYear(), q * 3 + 3, 0));
                break;
            case 'year':
                s = fmtDate(new Date(today.getFullYear(), 0, 1));
                e = fmtDate(new Date(today.getFullYear(), 11, 31));
                break;
            default:
                return;
        }
        $startDate.val(s);
        $endDate.val(e);
    }

    function fmtDate(d) {
        var mm = ('0' + (d.getMonth() + 1)).slice(-2);
        var dd = ('0' + d.getDate()).slice(-2);
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    // ─── LocalStorage preferences ─────────────────────────────────────────────
    function savePref(key, val) {
        try { localStorage.setItem('aura_dash_' + key, val); } catch (e) {}
    }

    function getPref(key, def) {
        try { return localStorage.getItem('aura_dash_' + key) || def; } catch (e) { return def; }
    }

    function loadStoredPreferences() {
        var period  = getPref('period', 'month');
        var compare = getPref('compare', '0');

        // Activar botón correcto
        $('[data-period="' + period + '"]').addClass('active');

        // Aplicar periodo (para que se llenen las fechas antes del fetch)
        if (period !== 'custom') {
            applyPreset(period);
        } else {
            var s = getPref('start', '');
            var e = getPref('end', '');
            if (s) $startDate.val(s);
            if (e) $endDate.val(e);
        }

        $compareChk.prop('checked', compare === '1');
    }

    // ─── AJAX: obtener datos del dashboard ────────────────────────────────────
    function fetchDashboardData() {
        if (refreshInProgress) return;
        refreshInProgress = true;
        showRefreshing(true);

        $.ajax({
            url: auraDashboard.ajaxUrl,
            method: 'POST',
            data: {
                action:  'aura_get_dashboard_data',
                nonce:   auraDashboard.nonce,
                start:   $startDate.val(),
                end:     $endDate.val(),
                compare: $compareChk.is(':checked') ? 1 : 0,
            },
            success: function (resp) {
                if (!resp.success) {
                    console.error('[Aura Dashboard] Error:', resp.data);
                    return;
                }
                var d = resp.data;
                renderKPIs(d.kpis);
                updateLineChart(d.chart_line);
                updateDonutChart(d.chart_donut);
                renderRecentTransactions(d.recent);
                renderAlerts(d.alerts);
            },
            error: function (xhr, status, err) {
                console.error('[Aura Dashboard] AJAX error:', err);
            },
            complete: function () {
                refreshInProgress = false;
                showRefreshing(false);
            }
        });
    }

    // ─── KPIs ─────────────────────────────────────────────────────────────────
    function renderKPIs(kpis) {
        $.each(kpis, function (key, data) {
            var $card = $('.aura-kpi--' + key);
            if (!$card.length) return;

            $card.removeClass('is-loading');

            // Valor principal
            $card.find('.aura-kpi__value').text(data.formatted);

            // Tendencia vs período anterior
            var $trend = $card.find('.aura-kpi__trend');
            if (data.pct_change !== null) {
                var pct   = Math.abs(data.pct_change).toFixed(1) + '%';
                var cls   = data.pct_change > 0 ? 'up' : (data.pct_change < 0 ? 'down' : 'neutral');
                var icon  = data.pct_change > 0
                    ? '<span class="dashicons dashicons-arrow-up-alt2"></span>'
                    : (data.pct_change < 0 ? '<span class="dashicons dashicons-arrow-down-alt2"></span>' : '—');
                $trend.show().removeClass('up down neutral').addClass(cls).html(icon + ' ' + pct);
            } else {
                $trend.hide();
            }

            // Clase especial balance
            if (key === 'balance') {
                $card.removeClass('is-positive is-negative');
                if (data.raw > 0)       $card.addClass('is-positive');
                else if (data.raw < 0)  $card.addClass('is-negative');
            }
        });
    }

    // ─── Gráfico de líneas ────────────────────────────────────────────────────
    function initLineChart() {
        var ctx = document.getElementById('aura-line-chart');
        if (!ctx) return;

        lineChart = new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2.4,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + formatMoney(ctx.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: '#f3f4f6' },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        grid: { color: '#f3f4f6' },
                        ticks: {
                            font: { size: 11 },
                            callback: function (v) { return '$' + v.toLocaleString(); }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function updateLineChart(data) {
        if (!lineChart || !data) return;

        var datasets = [
            {
                label: auraDashboard.i18n.income,
                data: data.income,
                borderColor: COLORS.income.border,
                backgroundColor: COLORS.income.fill,
                fill: true,
                tension: .3,
                borderWidth: 2,
                pointRadius: 3,
            },
            {
                label: auraDashboard.i18n.expense,
                data: data.expense,
                borderColor: COLORS.expense.border,
                backgroundColor: COLORS.expense.fill,
                fill: true,
                tension: .3,
                borderWidth: 2,
                pointRadius: 3,
            }
        ];

        // Agregar líneas de período anterior si existen
        if (data.prev_income) {
            datasets.push({
                label: auraDashboard.i18n.prevIncome,
                data: data.prev_income,
                borderColor: COLORS.prevIncome.border,
                backgroundColor: 'transparent',
                borderDash: [4, 3],
                tension: .3,
                borderWidth: 1.5,
                pointRadius: 2,
            });
        }
        if (data.prev_expense) {
            datasets.push({
                label: auraDashboard.i18n.prevExpense,
                data: data.prev_expense,
                borderColor: COLORS.prevExpense.border,
                backgroundColor: 'transparent',
                borderDash: [4, 3],
                tension: .3,
                borderWidth: 1.5,
                pointRadius: 2,
            });
        }

        lineChart.data.labels   = data.labels;
        lineChart.data.datasets = datasets;
        lineChart.update();
    }

    // ─── Gráfico de dona ──────────────────────────────────────────────────────
    function initDonutChart() {
        var ctx = document.getElementById('aura-donut-chart');
        if (!ctx) return;

        donutChart = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct   = total > 0 ? (ctx.parsed / total * 100).toFixed(1) : 0;
                                return ' ' + ctx.label + ': ' + formatMoney(ctx.parsed) + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                onClick: function (evt, elements) {
                    if (!elements.length) return;
                    var catId = donutChart.data.catIds && donutChart.data.catIds[elements[0].index];
                    if (catId) {
                        window.location.href = auraDashboard.txListUrl +
                            '&category_id=' + catId +
                            '&start_date=' + $startDate.val() +
                            '&end_date='   + $endDate.val();
                    }
                }
            }
        });
    }

    function updateDonutChart(data) {
        if (!donutChart || !data || !data.labels.length) return;

        var colors = data.labels.map(function (_, i) {
            return data.colors && data.colors[i] ? data.colors[i] : PALETTE[i % PALETTE.length];
        });

        donutChart.data.catIds  = data.cat_ids || [];
        donutChart.data.labels  = data.labels;
        donutChart.data.datasets = [{
            data: data.amounts,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#fff',
            hoverBorderColor: '#fff',
        }];
        donutChart.update();

        // Leyenda personalizada
        renderDonutLegend(data, colors);
    }

    function renderDonutLegend(data, colors) {
        var $legend = $('#aura-donut-legend').empty();
        var total   = data.amounts.reduce(function (a, b) { return a + b; }, 0);

        data.labels.forEach(function (label, i) {
            var pct = total > 0 ? (data.amounts[i] / total * 100).toFixed(1) : 0;
            $legend.append(
                '<div class="aura-donut-legend__item">' +
                    '<span class="aura-donut-legend__color" style="background:' + colors[i] + '"></span>' +
                    '<span class="aura-donut-legend__name">' + $('<span>').text(label).html() + '</span>' +
                    '<span class="aura-donut-legend__pct">' + pct + '%</span>' +
                '</div>'
            );
        });
    }

    // ─── Transacciones recientes ──────────────────────────────────────────────
    function renderRecentTransactions(items) {
        var $tbody = $('#aura-recent-tbody').empty();
        var $empty = $('#aura-recent-empty');

        if (!items || !items.length) {
            $empty.show();
            return;
        }
        $empty.hide();

        items.forEach(function (tx) {
            var typeIcon = tx.type === 'income'
                ? '<span class="tx-type-icon income dashicons dashicons-arrow-up-alt"></span>'
                : '<span class="tx-type-icon expense dashicons dashicons-arrow-down-alt"></span>';

            var amountClass = tx.type === 'income' ? 'amount-income' : 'amount-expense';
            var amountSign  = tx.type === 'income' ? '+' : '-';

            $tbody.append(
                '<tr>' +
                    '<td>' + escHtml(tx.date) + '</td>' +
                    '<td>' + typeIcon + '</td>' +
                    '<td>' + escHtml(tx.cat_name) + '</td>' +
                    '<td class="col-desc">' +
                        '<a href="' + escHtml(tx.edit_url) + '">' + escHtml(tx.description) + '</a>' +
                    '</td>' +
                    '<td class="col-amount ' + amountClass + '">' + amountSign + escHtml(tx.formatted) + '</td>' +
                    '<td><span class="status-pill ' + escHtml(tx.status) + '">' + escHtml(tx.status_label) + '</span></td>' +
                '</tr>'
            );
        });
    }

    // ─── Alertas ──────────────────────────────────────────────────────────────
    function renderAlerts(alerts) {
        var $list  = $('#aura-alerts-list').empty();
        var $empty = $('#aura-alerts-empty');

        if (!alerts || !alerts.length) {
            $empty.show();
            return;
        }
        $empty.hide();

        var iconMap = {
            danger:  'dashicons-warning',
            warning: 'dashicons-clock',
            info:    'dashicons-info-outline',
            success: 'dashicons-yes-alt',
        };

        alerts.forEach(function (a) {
            var icon = iconMap[a.type] || 'dashicons-info-outline';
            $list.append(
                '<div class="aura-alert aura-alert--' + escHtml(a.type) + '">' +
                    '<span class="dashicons ' + icon + '"></span>' +
                    '<div class="aura-alert__text">' + a.message + '</div>' +
                '</div>'
            );
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────
    function formatMoney(val) {
        return '$' + parseFloat(val || 0).toLocaleString('es', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escHtml(str) {
        return $('<span>').text(str || '').html();
    }

    function exportChart(chart, filename) {
        if (!chart) return;
        var a  = document.createElement('a');
        a.href = chart.toBase64Image();
        a.download = filename;
        a.click();
    }

    function showRefreshing(show) {
        var $r = $('#aura-dashboard-refreshing');
        if (show) {
            if (!$r.length) {
                $('body').append(
                    '<div id="aura-dashboard-refreshing" class="aura-dashboard-refreshing">' +
                        '<span class="aura-spinner"></span>' +
                        auraDashboard.i18n.refreshing +
                    '</div>'
                );
            }
        } else {
            $r.remove();
        }
    }

})(jQuery);
