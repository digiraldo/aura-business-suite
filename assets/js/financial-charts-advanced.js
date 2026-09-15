/**
 * Análisis Visual – Fase 3, Item 3.3
 * Cliente JS para los 5 tabs de gráficos interactivos con ApexCharts
 * Soporte Dark Mode Dinámico, Navegación por Hash, Labels en Barras, Fullscreen e Impresión
 *
 * @package AuraBusinessSuite
 */
/* global auraAnalytics, ApexCharts */

(function ($) {
    'use strict';

    /* ============================================================ */
    /* ESTADO GLOBAL                                                  */
    /* ============================================================ */

    const State = {
        activeTab:   'trends',
        startDate:   document.getElementById('aura-filter-start')?.value || '',
        endDate:     document.getElementById('aura-filter-end')?.value   || '',
        trendsGran:  'month',
        catType:     'both',
        catSort:     'amount',
        catLimit:    10,
        budgetYear:  new Date().getFullYear(),
        budgetMonth: new Date().getMonth() + 1,
        loaded:      {},       // qué tabs ya cargaron datos
        charts:      {},       // instancias ApexCharts por ID
    };

    /* ============================================================ */
    /* HELPER: DETECCIÓN DE MODO OSCURO                               */
    /* ============================================================ */

    function isDarkMode() {
        return $('html').attr('data-wp-dark-mode-scheme') === 'dark' ||
               $('html').hasClass('wp-dark-mode-active') ||
               $('body').hasClass('wp-dark-mode-active') ||
               $('body').hasClass('aura-dark-mode') ||
               $('body').attr('data-theme') === 'dark' ||
               $('[data-theme="dark"]').length > 0;
    }

    function getApexThemeDefaults() {
        const dark = isDarkMode();
        return {
            theme: {
                mode: dark ? 'dark' : 'light',
            },
            chart: {
                background: 'transparent',
                foreColor: dark ? '#94a3b8' : '#64748b',
                fontFamily: 'inherit',
            },
            grid: {
                borderColor: dark ? '#334155' : '#e2e8f0',
                strokeDashArray: 3,
            },
        };
    }

    /* ============================================================ */
    /* AJAX HELPER                                                    */
    /* ============================================================ */

    function ajaxPost(action, data) {
        return $.ajax({
            url:    auraAnalytics?.ajaxurl || '/wp-admin/admin-ajax.php',
            method: 'POST',
            data:   Object.assign({ action, nonce: auraAnalytics?.nonce || '' }, data),
        });
    }

    function formatCurrency(val) {
        const n = parseFloat(val) || 0;
        const sym = auraAnalytics?.currency_symbol || '$';
        return sym + ' ' + n.toLocaleString('es', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    /* ============================================================ */
    /* GESTIÓN DE TABS Y NAVEGACIÓN                                   */
    /* ============================================================ */

    function switchTab(tabId) {
        if (!tabId) return;
        State.activeTab = tabId;

        // Actualizar URL hash sin recargar
        if (window.location.hash !== '#tab-' + tabId) {
            if (history.pushState) {
                history.pushState(null, null, '#tab-' + tabId);
            } else {
                location.hash = '#tab-' + tabId;
            }
        }

        // Actualizar clases de pestañas
        $('.nav-tab').removeClass('nav-tab-active');
        $('[data-tab="' + tabId + '"].nav-tab').addClass('nav-tab-active');

        // Mostrar tab seleccionado
        $('.aura-tab-content').removeClass('active').hide();
        $('#tab-' + tabId).addClass('active').show();

        // Cargar datos
        setTimeout(function () {
            if (!State.loaded[tabId]) {
                loadTabData(tabId);
            } else {
                window.dispatchEvent(new Event('resize'));
            }
        }, 50);
    }

    function loadTabData(tabId) {
        switch (tabId) {
            case 'trends':     loadTrends();     break;
            case 'categories': loadCategories(); break;
            case 'comparison': loadComparison(); break;
            case 'patterns':   loadPatterns();   break;
            case 'budget':     loadBudget();     break;
        }
    }

    /* ============================================================ */
    /* HELPERS – APEXCHARTS                                           */
    /* ============================================================ */

    function destroyChart(chartId) {
        if (State.charts[chartId]) {
            try {
                State.charts[chartId].destroy();
            } catch (e) {}
            delete State.charts[chartId];
        }
    }

    function parseAnnotationsForApex(annotations) {
        if (!annotations || !annotations.length) return { xaxis: [] };
        return {
            xaxis: annotations.map(a => ({
                x: a.annotation_date,
                borderColor: '#f59e0b',
                label: {
                    text:  a.note,
                    style: { color: '#fff', background: '#f59e0b' },
                },
            })),
        };
    }

    function fullscreenChart(chartId) {
        const instance = State.charts[chartId];
        if (!instance) return;

        const $overlay = $('#aura-fullscreen-overlay');
        const $container = $('#aura-fullscreen-chart');
        $container.empty();

        const innerDiv = document.createElement('div');
        innerDiv.id = chartId + '-fs';
        $container.append(innerDiv);

        const fsOpts = Object.assign({}, instance.opts, {
            chart: Object.assign({}, instance.opts.chart, {
                height: '100%',
                animations: { enabled: false },
            }),
        });

        $overlay.css('display', 'flex').hide().fadeIn(200, function () {
            const fsChart = new ApexCharts(innerDiv, fsOpts);
            fsChart.render();
            State.charts[chartId + '-fs'] = fsChart;
        });
    }

    /* ============================================================ */
    /* TAB 1 – TENDENCIAS                                             */
    /* ============================================================ */

    function loadTrends() {
        const $container = $('#chart-trends');
        $container.html('<div class="aura-loading">' + (auraAnalytics?.txt?.loading || 'Cargando…') + '</div>');

        State.startDate = $('#aura-filter-start').val() || State.startDate;
        State.endDate   = $('#aura-filter-end').val()   || State.endDate;

        ajaxPost('aura_analytics_trends', {
            start_date:  State.startDate,
            end_date:    State.endDate,
            granularity: State.trendsGran,
        }).done(function (res) {
            if (!res.success) {
                $container.html('<p class="error">' + (res.data?.message || 'Error') + '</p>');
                return;
            }
            renderTrends(res.data);
            renderAnnotationsList('trends', res.data.annotations);
            State.loaded['trends'] = true;
        }).fail(function () {
            $container.html('<p class="error">' + (auraAnalytics?.txt?.error || 'Error al cargar datos.') + '</p>');
        });
    }

    function renderTrends(data) {
        const $el = document.getElementById('chart-trends');
        if (!$el) return;

        destroyChart('chart-trends');

        const projLabels = [];
        for (let i = 0; i < (data.projection?.length || 0); i++) {
            projLabels.push((auraAnalytics?.txt?.projection || 'Proyección') + ' +' + (i + 1));
        }

        const allLabels  = [...(data.labels || []), ...projLabels];
        const incPadded  = [...(data.income || []),  ...new Array(projLabels.length).fill(null)];
        const expPadded  = [...(data.expense || []), ...new Array(projLabels.length).fill(null)];
        const balPadded  = [...(data.balance || []), ...new Array(projLabels.length).fill(null)];
        const projPadded = [...new Array((data.labels || []).length).fill(null), ...(data.projection || [])];

        const themeDefs = getApexThemeDefaults();

        const opts = {
            theme: themeDefs.theme,
            grid: themeDefs.grid,
            chart: Object.assign({
                type:      'line',
                height:    420,
                zoom:      { enabled: true, type: 'x' },
                toolbar:   { show: true },
                animations:{ enabled: true },
            }, themeDefs.chart),
            series: [
                { name: (auraAnalytics?.txt?.income || 'Ingresos'),     data: incPadded,  color: '#10b981' },
                { name: (auraAnalytics?.txt?.expense || 'Egresos'),    data: expPadded,  color: '#ef4444' },
                { name: (auraAnalytics?.txt?.balance || 'Balance'),    data: balPadded,  color: '#3b82f6' },
                { name: (auraAnalytics?.txt?.projection || 'Proyección'), data: projPadded, color: '#8b5cf6', dashArray: 6 },
            ],
            xaxis: { categories: allLabels },
            yaxis: {
                labels: {
                    formatter: val => (auraAnalytics?.currency_symbol || '$') + ' ' + Number(val).toLocaleString('es'),
                },
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: val => formatCurrency(val) },
                custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                    const label = w.globals.categoryLabels[dataPointIndex] || allLabels[dataPointIndex];
                    const cnt   = data.counts?.[dataPointIndex] ?? 0;
                    let html = '<div class="aura-apex-tooltip"><strong>' + label + '</strong>';
                    w.globals.seriesNames.forEach((name, i) => {
                        const val = series[i][dataPointIndex];
                        if (val !== null && val !== undefined) {
                            html += '<div class="tt-row"><span class="tt-name">' + name + '</span>'
                                + '<span class="tt-val">' + formatCurrency(val) + '</span></div>';
                        }
                    });
                    if (cnt) {
                        html += '<div class="tt-row"><span class="tt-name">' + (auraAnalytics?.txt?.transactions || 'Transacciones') + '</span>'
                            + '<span class="tt-val">' + cnt + '</span></div>';
                    }
                    html += '</div>';
                    return html;
                },
            },
            stroke:   { curve: 'smooth', width: [2.5, 2.5, 2.5, 1.5] },
            markers:  { size: 4 },
            annotations: parseAnnotationsForApex(data.annotations),
            noData:   { text: (auraAnalytics?.txt?.no_data || 'Sin datos') },
        };

        setTimeout(function () {
            $($el).empty();
            const chart = new ApexCharts($el, opts);
            chart.opts = opts;
            chart.render();
            State.charts['chart-trends'] = chart;
        }, 50);
    }

    /* ============================================================ */
    /* TAB 2 – DISTRIBUCIÓN                                           */
    /* ============================================================ */

    function loadCategories() {
        const $container = $('#chart-categories');
        $container.html('<div class="aura-loading">' + (auraAnalytics?.txt?.loading || 'Cargando…') + '</div>');

        State.startDate = $('#aura-filter-start').val() || State.startDate;
        State.endDate   = $('#aura-filter-end').val()   || State.endDate;

        ajaxPost('aura_analytics_categories', {
            start_date: State.startDate,
            end_date:   State.endDate,
            type:       State.catType,
            sort:       State.catSort,
            limit:      State.catLimit,
        }).done(function (res) {
            if (!res.success) {
                $container.html('<p class="error">' + (res.data?.message || 'Error') + '</p>');
                return;
            }
            renderCategories(res.data.categories || []);
            State.loaded['categories'] = true;
        }).fail(function() {
            $container.html('<p class="error">' + (auraAnalytics?.txt?.error || 'Error al cargar datos.') + '</p>');
        });
    }

    function renderCategories(cats) {
        const $el = document.getElementById('chart-categories');
        if (!$el || !cats.length) {
            $($el).html('<p class="aura-no-data">' + (auraAnalytics?.txt?.no_data || 'Sin datos para el período seleccionado.') + '</p>');
            return;
        }

        destroyChart('chart-categories');

        const labels   = cats.map(c => c.name);
        const incomes  = cats.map(c => parseFloat(c.income) || 0);
        const expenses = cats.map(c => parseFloat(c.expense) || 0);

        let series = [];
        let colors = [];

        if (State.catType === 'income') {
            series = [{ name: (auraAnalytics?.txt?.income || 'Ingresos'), data: incomes }];
            colors = ['#10b981'];
        } else if (State.catType === 'expense') {
            series = [{ name: (auraAnalytics?.txt?.expense || 'Egresos'), data: expenses }];
            colors = ['#ef4444'];
        } else {
            series = [
                { name: (auraAnalytics?.txt?.income || 'Ingresos'),  data: incomes },
                { name: (auraAnalytics?.txt?.expense || 'Egresos'), data: expenses },
            ];
            colors = ['#10b981', '#ef4444'];
        }

        const themeDefs = getApexThemeDefaults();
        const isDark = isDarkMode();

        const opts = {
            theme: themeDefs.theme,
            grid: themeDefs.grid,
            colors: colors,
            chart: Object.assign({
                type:     'bar',
                height:   Math.max(420, labels.length * 44),
                stacked:  false,
                toolbar:  { show: true },
            }, themeDefs.chart),
            plotOptions: {
                bar: {
                    horizontal:   true,
                    borderRadius: 4,
                    dataLabels:   { position: 'top' },
                },
            },
            dataLabels: {
                enabled: true,
                offsetX: 30,
                style: {
                    fontSize: '11px',
                    fontWeight: 700,
                    colors: [isDark ? '#f8fafc' : '#1e293b'],
                },
                formatter: function (val) {
                    return val > 0 ? formatCurrency(val) : '0';
                },
            },
            series,
            xaxis: {
                categories: labels,
                labels: {
                    formatter: val => (auraAnalytics?.currency_symbol || '$') + ' ' + Number(val).toLocaleString('es'),
                },
            },
            tooltip: {
                shared: false,
                intersect: false,
                y: { formatter: val => formatCurrency(val) },
            },
            noData: { text: (auraAnalytics?.txt?.no_data || 'Sin datos') },
        };

        setTimeout(function () {
            $($el).empty();
            const chart = new ApexCharts($el, opts);
            chart.opts = opts;
            chart.render();
            State.charts['chart-categories'] = chart;
        }, 50);
    }

    /* ============================================================ */
    /* TAB 3 – COMPARACIONES                                          */
    /* ============================================================ */

    function loadComparison() {
        runComparison();
    }

    function runComparison() {
        const aStart = $('#cmp-a-start').val();
        const aEnd   = $('#cmp-a-end').val();
        const bStart = $('#cmp-b-start').val();
        const bEnd   = $('#cmp-b-end').val();

        const $chart = $('#chart-comparison');
        $chart.html('<div class="aura-loading">' + (auraAnalytics?.txt?.loading || 'Cargando…') + '</div>');

        ajaxPost('aura_analytics_comparison', {
            a_start: aStart, a_end: aEnd,
            b_start: bStart, b_end: bEnd,
        }).done(function (res) {
            if (!res.success) {
                $chart.html('<p class="error">' + (res.data?.message || 'Error') + '</p>');
                return;
            }
            renderComparisonChart(res.data);
            renderComparisonTable(res.data.rows || res.data.categories || []);
            State.loaded['comparison'] = true;
        }).fail(function() {
            $chart.html('<p class="error">' + (auraAnalytics?.txt?.error || 'Error al cargar datos.') + '</p>');
        });
    }

    function renderComparisonChart(data) {
        const $el = document.getElementById('chart-comparison');
        if (!$el) return;

        destroyChart('chart-comparison');

        const rows = data.rows || data.categories || [];
        if (!rows.length) {
            $($el).html('<p class="aura-no-data">' + (auraAnalytics?.txt?.no_data || 'Sin datos para el período seleccionado.') + '</p>');
            return;
        }

        const categories = rows.map(r => r.category_name || r.category);
        const seriesA    = rows.map(r => parseFloat(r.amount_a !== undefined ? r.amount_a : (r.a_income - r.a_expense)));
        const seriesB    = rows.map(r => parseFloat(r.amount_b !== undefined ? r.amount_b : (r.b_income - r.b_expense)));

        const themeDefs = getApexThemeDefaults();

        const opts = {
            theme: themeDefs.theme,
            grid: themeDefs.grid,
            chart: Object.assign({
                type:    'bar',
                height:  Math.max(380, categories.length * 36),
                toolbar: { show: true },
            }, themeDefs.chart),
            plotOptions: {
                bar: {
                    horizontal:   true,
                    borderRadius: 4,
                    dataLabels:   { position: 'top' },
                },
            },
            series: [
                { name: 'Período Base (A)', data: seriesA, color: '#3b82f6' },
                { name: 'Período Comparativo (B)', data: seriesB, color: '#f59e0b' },
            ],
            xaxis: {
                categories,
                labels: { formatter: val => formatCurrency(val) },
            },
            tooltip: {
                shared: false,
                intersect: false,
                y: { formatter: val => formatCurrency(val) },
            },
            noData: { text: (auraAnalytics?.txt?.no_data || 'Sin datos') },
        };

        setTimeout(function () {
            $($el).empty();
            const chart = new ApexCharts($el, opts);
            chart.opts = opts;
            chart.render();
            State.charts['chart-comparison'] = chart;
        }, 50);
    }

    function renderComparisonTable(rows) {
        const $tbody = $('#cmp-table-body');
        $tbody.empty();

        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="5" class="aura-empty-row">' + (auraAnalytics?.txt?.no_data || 'Sin datos') + '</td></tr>');
            return;
        }

        rows.forEach(r => {
            const diff    = parseFloat(r.diff_abs !== undefined ? r.diff_abs : r.abs_diff);
            const pct     = r.diff_pct !== null && r.diff_pct !== undefined ? parseFloat(r.diff_pct) : (r.pct_diff !== null && r.pct_diff !== undefined ? parseFloat(r.pct_diff) : null);
            const cls     = diff > 0 ? 'positive' : (diff < 0 ? 'negative' : '');
            const pctText = pct !== null ? (pct > 0 ? '+' : '') + pct + '%' : '—';
            const sign    = diff > 0 ? '+' : '';
            const catName = r.category_name || r.category || '—';

            $tbody.append(
                '<tr>' +
                '<td><strong>' + $('<div>').text(catName).html() + '</strong></td>' +
                '<td>' + formatCurrency(r.amount_a !== undefined ? r.amount_a : (r.a_income - r.a_expense)) + '</td>' +
                '<td>' + formatCurrency(r.amount_b !== undefined ? r.amount_b : (r.b_income - r.b_expense)) + '</td>' +
                '<td class="' + cls + '">' + sign + formatCurrency(diff) + '</td>' +
                '<td class="' + cls + '">' + pctText + '</td>' +
                '</tr>'
            );
        });
    }

    /* ============================================================ */
    /* TAB 4 – PATRONES                                               */
    /* ============================================================ */

    function loadPatterns() {
        State.startDate = $('#aura-filter-start').val() || State.startDate;
        State.endDate   = $('#aura-filter-end').val()   || State.endDate;

        ajaxPost('aura_analytics_patterns', {
            start_date: State.startDate,
            end_date:   State.endDate,
        }).done(function (res) {
            if (!res.success) return;
            renderHeatmap(res.data.heatmap || []);
            renderScatter(res.data.scatter || []);
            renderOutliers(res.data.outliers || []);
            State.loaded['patterns'] = true;
        });
    }

    function renderHeatmap(heatmapData) {
        const $el = document.getElementById('chart-heatmap');
        if (!$el) return;

        destroyChart('chart-heatmap');

        const days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        const weeks = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4+'];

        const series = days.map((dayName, d) => ({
            name: dayName,
            data: (heatmapData[d] || [0, 0, 0, 0]).map((val, w) => ({
                x: weeks[w],
                y: val || 0,
            })),
        }));

        const themeDefs = getApexThemeDefaults();

        const opts = {
            theme: themeDefs.theme,
            grid: themeDefs.grid,
            chart: Object.assign({
                type:    'heatmap',
                height:  260,
                toolbar: { show: false },
            }, themeDefs.chart),
            dataLabels: { enabled: true, style: { fontSize: '11px', colors: ['#ffffff'] } },
            colors: ['#2563eb'],
            series,
            xaxis: {
                labels: { style: { fontSize: '11px' } },
            },
            tooltip: {
                shared: false,
                intersect: false,
                y: { formatter: val => val + ' transacciones' },
            },
            noData: { text: (auraAnalytics?.txt?.no_data || 'Sin datos') },
        };

        setTimeout(function () {
            $($el).empty();
            const chart = new ApexCharts($el, opts);
            chart.opts = opts;
            chart.render();
            State.charts['chart-heatmap'] = chart;
        }, 50);
    }

    function renderScatter(scatterData) {
        const $el = document.getElementById('chart-scatter');
        if (!$el) return;

        destroyChart('chart-scatter');

        if (!scatterData || !scatterData.length) {
            $($el).html('<p class="aura-no-data">' + (auraAnalytics?.txt?.no_data || 'Sin datos') + '</p>');
            return;
        }

        const series = scatterData.map(item => ({
            name: item.name,
            data: [[item.count, item.avg_amount]],
        }));

        const themeDefs = getApexThemeDefaults();

        const opts = {
            theme: themeDefs.theme,
            grid: themeDefs.grid,
            chart: Object.assign({
                type:    'scatter',
                height:  260,
                toolbar: { show: false },
            }, themeDefs.chart),
            series,
            xaxis: {
                title: { text: 'Frecuencia (N° transacciones)' },
                tickAmount: 5,
            },
            yaxis: {
                title: { text: (auraAnalytics?.txt?.avg_amount || 'Monto promedio') },
                labels: { formatter: val => formatCurrency(val) },
            },
            tooltip: {
                shared: false,
                intersect: false,
                custom: function ({ seriesIndex }) {
                    const item = scatterData[seriesIndex];
                    if (!item) return '';
                    return '<div class="aura-apex-tooltip">'
                        + '<strong>' + $('<div>').text(item.name).html() + '</strong>'
                        + '<div class="tt-row"><span class="tt-name">Frecuencia:</span><span class="tt-val">' + item.count + '</span></div>'
                        + '<div class="tt-row"><span class="tt-name">Promedio:</span><span class="tt-val">' + formatCurrency(item.avg_amount) + '</span></div>'
                        + '<div class="tt-row"><span class="tt-name">Total:</span><span class="tt-val">' + formatCurrency(item.total) + '</span></div>'
                        + '</div>';
                },
            },
            noData: { text: (auraAnalytics?.txt?.no_data || 'Sin datos') },
        };

        setTimeout(function () {
            $($el).empty();
            const chart = new ApexCharts($el, opts);
            chart.opts = opts;
            chart.render();
            State.charts['chart-scatter'] = chart;
        }, 50);
    }

    function renderOutliers(outliers) {
        const $tbody = $('#outliers-body');
        $tbody.empty();

        if (!outliers || !outliers.length) {
            $tbody.html('<tr><td colspan="5" class="aura-empty-row">' + (auraAnalytics?.txt?.no_outliers || 'No se detectaron transacciones atípicas.') + '</td></tr>');
            return;
        }

        outliers.forEach(t => {
            const isInc = t.transaction_type === 'income';
            $tbody.append(
                '<tr>' +
                '<td>' + t.transaction_date + '</td>' +
                '<td><strong>' + $('<div>').text(t.description).html() + '</strong></td>' +
                '<td>' + (t.category_name || '—') + '</td>' +
                '<td><span class="transaction-type ' + (isInc ? 'type-income' : 'type-expense') + '">'
                    + (isInc ? (auraAnalytics?.txt?.income || 'Ingresos') : (auraAnalytics?.txt?.expense || 'Egresos')) + '</span></td>' +
                '<td><strong class="' + (isInc ? 'amount-income' : 'amount-expense') + '">'
                    + formatCurrency(t.amount) + '</strong></td>' +
                '</tr>'
            );
        });
    }

    /* ============================================================ */
    /* TAB 5 – PRESUPUESTO                                            */
    /* ============================================================ */

    function loadBudget() {
        const year  = parseInt($('#budget-year').val(), 10)  || new Date().getFullYear();
        const month = parseInt($('#budget-month').val(), 10) || (new Date().getMonth() + 1);

        const $chart = $('#chart-budget');
        $chart.html('<div class="aura-loading">' + (auraAnalytics?.txt?.loading || 'Cargando…') + '</div>');

        ajaxPost('aura_analytics_budget', { year, month }).done(function (res) {
            if (!res.success) {
                $chart.html('<p class="error">' + (res.data?.message || 'Error') + '</p>');
                return;
            }
            renderBudgetChart(res.data.items || []);
            renderBudgetTable(res.data.items || []);
            State.loaded['budget'] = true;
        }).fail(function() {
            $chart.html('<p class="error">' + (auraAnalytics?.txt?.error || 'Error al cargar datos.') + '</p>');
        });
    }

    function renderBudgetChart(items) {
        const $el = document.getElementById('chart-budget');
        if (!$el || !items.length) {
            $($el).html('<p class="aura-no-data">' + (auraAnalytics?.txt?.no_data || 'Sin datos para el período seleccionado.') + '</p>');
            return;
        }

        destroyChart('chart-budget');

        const categories = items.map(i => i.name);
        const budgets    = items.map(i => parseFloat(i.budget) || 0);
        const actuals    = items.map(i => parseFloat(i.actual) || 0);

        const themeDefs = getApexThemeDefaults();

        const opts = {
            theme: themeDefs.theme,
            grid: themeDefs.grid,
            chart: Object.assign({
                type:    'bar',
                height:  Math.max(380, categories.length * 36),
                toolbar: { show: true },
            }, themeDefs.chart),
            plotOptions: {
                bar: {
                    horizontal:   true,
                    borderRadius: 4,
                    dataLabels:   { position: 'top' },
                },
            },
            series: [
                { name: (auraAnalytics?.txt?.budget || 'Presupuesto Asignado'), data: budgets, color: '#94a3b8' },
                { name: (auraAnalytics?.txt?.actual || 'Monto Ejecutado'), data: actuals, color: '#ef4444' },
            ],
            xaxis: {
                categories,
                labels: { formatter: val => formatCurrency(val) },
            },
            tooltip: {
                shared: false,
                intersect: false,
                y: { formatter: val => formatCurrency(val) },
            },
            noData: { text: (auraAnalytics?.txt?.no_data || 'Sin datos') },
        };

        setTimeout(function () {
            $($el).empty();
            const chart = new ApexCharts($el, opts);
            chart.opts = opts;
            chart.render();
            State.charts['chart-budget'] = chart;
        }, 50);
    }

    function renderBudgetTable(items) {
        const $tbody = $('#budget-table-body');
        $tbody.empty();

        if (!items || !items.length) {
            $tbody.html('<tr><td colspan="6" class="aura-empty-row">' + (auraAnalytics?.txt?.no_data || 'Sin datos') + '</td></tr>');
            return;
        }

        items.forEach(item => {
            const pct     = item.pct !== null ? item.pct + '%' : '—';
            const rowCls  = item.over ? 'over-budget' : '';
            const status  = item.over
                ? '<span style="color:#dc2626;font-weight:700;">⚠️ ' + (auraAnalytics?.txt?.over_budget || 'Excedido') + '</span>'
                : (item.pct > 80 ? '<span style="color:#f59e0b;font-weight:700;">⚡ Alto</span>' : '<span style="color:#10b981;font-weight:700;">✓ Normal</span>');

            $tbody.append(
                '<tr class="' + rowCls + '">' +
                '<td><strong>' + $('<div>').text(item.name).html() + '</strong></td>' +
                '<td>' + formatCurrency(item.budget) + '</td>' +
                '<td>' + formatCurrency(item.actual) + '</td>' +
                '<td>' + pct + '</td>' +
                '<td>' + formatCurrency(item.projection) + '</td>' +
                '<td>' + status + '</td>' +
                '</tr>'
            );
        });
    }

    /* ============================================================ */
    /* MODALES                                                        */
    /* ============================================================ */

    function openAnnotationModal(tab) {
        $('#ann-tab').val(tab);
        $('#ann-date').val(new Date().toISOString().slice(0, 10));
        $('#ann-note').val('');
        $('#aura-annotation-modal').removeClass('aura-modal-hidden').fadeIn(200);
    }

    function saveAnnotation() {
        const tab  = $('#ann-tab').val();
        const date = $('#ann-date').val();
        const note = $('#ann-note').val();

        ajaxPost('aura_analytics_annotation_save', { tab, date, note })
            .done(function (res) {
                if (res.success) {
                    closeModals();
                    State.loaded[tab] = false;
                    loadTabData(tab);
                } else {
                    alert(res.data?.message || 'Error');
                }
            });
    }

    function renderAnnotationsList(tab, annotations) {
        const $wrap = $('#' + tab + '-annotations');
        $wrap.empty();
        if (!annotations || !annotations.length) return;

        let html = '<div class="ann-list-header" style="font-weight:700;margin:10px 0 6px;color:#475569;">Anotaciones Registradas:</div><ul style="list-style:none;margin:0;padding:0;">';
        annotations.forEach(a => {
            html += '<li style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#fef3c7;border-left:3px solid #f59e0b;margin-bottom:4px;border-radius:4px;">'
                + '<span class="ann-date" style="font-weight:700;color:#92400e;">' + a.annotation_date + ':</span>'
                + '<span class="ann-note" style="flex:1;color:#1e293b;">' + $('<div>').text(a.note).html() + '</span>'
                + '<button type="button" class="ann-delete-btn" data-id="' + a.id + '" title="Eliminar" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;">&times;</button>'
                + '</li>';
        });
        html += '</ul>';
        $wrap.html(html);
    }

    function openBudgetModal() {
        const year  = $('#budget-year').val();
        const month = $('#budget-month').val();
        const $items = $('#budget-form-items');
        $items.html('<div class="aura-spinner">' + (auraAnalytics?.txt?.loading || 'Cargando…') + '</div>');
        $('#aura-budget-modal').removeClass('aura-modal-hidden').fadeIn(200);

        ajaxPost('aura_analytics_budget', { year, month }).done(function (res) {
            if (!res.success) return;
            $items.empty();
            res.data.items.forEach(item => {
                $items.append(
                    '<div class="budget-form-row" style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e2e8f0;">' +
                    '<label style="font-weight:600;font-size:13px;">' + $('<div>').text(item.name).html() + '</label>' +
                    '<div style="display:flex;align-items:center;gap:4px;">' +
                    '<span>' + (auraAnalytics?.currency_symbol || '$') + '</span>' +
                    '<input type="number" class="budget-input" min="0" step="100" ' +
                    'data-id="' + item.id + '" data-name="' + $('<div>').text(item.name).html() + '" ' +
                    'value="' + item.budget + '" style="width:140px;height:32px;text-align:right;border-radius:4px;border:1px solid #cbd5e1;padding:4px 8px;">' +
                    '</div></div>'
                );
            });
        });
    }

    function saveBudgets() {
        const year    = $('#budget-year').val();
        const month   = $('#budget-month').val();
        const budgets = {};

        $('#budget-form-items .budget-input').each(function () {
            const id = $(this).data('id');
            budgets[id] = {
                amount: parseFloat($(this).val()) || 0,
                name:   $(this).data('name'),
            };
        });

        ajaxPost('aura_analytics_budget_save', {
            year,
            month,
            budgets: JSON.stringify(budgets),
        }).done(function (res) {
            if (res.success) {
                closeModals();
                State.loaded['budget'] = false;
                loadBudget();
            } else {
                alert(res.data?.message || 'Error');
            }
        });
    }

    function closeModals() {
        $('.aura-modal').fadeOut(200, function() {
            $(this).addClass('aura-modal-hidden');
        });
    }

    /* ============================================================ */
    /* CHIPS DE FECHAS RÁPIDAS                                        */
    /* ============================================================ */

    function setupQuickDateChips() {
        $('.aura-chip-btn').on('click', function (e) {
            e.preventDefault();
            $('.aura-chip-btn').removeClass('active');
            $(this).addClass('active');

            const range = $(this).data('range');
            const now = new Date();
            let start, end;

            const pad = n => String(n).padStart(2, '0');
            const formatDate = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

            if (range === 'this_month') {
                start = new Date(now.getFullYear(), now.getMonth(), 1);
                end   = now;
            } else if (range === 'this_quarter') {
                const currentQuarterMonth = Math.floor(now.getMonth() / 3) * 3;
                start = new Date(now.getFullYear(), currentQuarterMonth, 1);
                end   = now;
            } else if (range === 'this_year') {
                start = new Date(now.getFullYear(), 0, 1);
                end   = now;
            } else if (range === 'last_year') {
                start = new Date(now.getFullYear() - 1, 0, 1);
                end   = new Date(now.getFullYear() - 1, 11, 31);
            }

            if (start && end) {
                $('#aura-filter-start').val(formatDate(start));
                $('#aura-filter-end').val(formatDate(end));
                State.startDate = formatDate(start);
                State.endDate   = formatDate(end);
                State.loaded    = {};
                loadTabData(State.activeTab);
            }
        });
    }

    /* ============================================================ */
    /* EVENTOS                                                        */
    /* ============================================================ */

    function bindEvents() {
        // Tabs: Evento delegado universal
        $(document).on('click', '#aura-analytics-tabs .nav-tab', function (e) {
            e.preventDefault();
            const $tab = $(this).closest('.nav-tab');
            const tabId = $tab.attr('data-tab') || $tab.data('tab');
            if (tabId) {
                switchTab(tabId);
            }
        });

        // Filtros globales
        $('#aura-apply-filters').on('click', function () {
            State.startDate = $('#aura-filter-start').val();
            State.endDate   = $('#aura-filter-end').val();
            State.loaded    = {};
            loadTabData(State.activeTab);
        });

        $('#aura-reset-filters').on('click', function () {
            const year = new Date().getFullYear();
            $('#aura-filter-start').val(year + '-01-01');
            $('#aura-filter-end').val(new Date().toISOString().slice(0, 10));
            State.startDate = year + '-01-01';
            State.endDate   = new Date().toISOString().slice(0, 10);
            State.loaded    = {};
            loadTabData(State.activeTab);
        });

        // Botón Imprimir / PDF
        $('#aura-print-analytics-btn').on('click', function(e) {
            e.preventDefault();
            window.print();
        });

        // Granularidad tendencias
        $(document).on('click', '#trends-granularity .aura-btn-toggle', function () {
            $('#trends-granularity .aura-btn-toggle').removeClass('active');
            $(this).addClass('active');
            State.trendsGran = $(this).data('gran');
            State.loaded['trends'] = false;
            loadTrends();
        });

        // Categorías: filtros
        $(document).on('click', '#cat-type .aura-btn-toggle', function () {
            $('#cat-type .aura-btn-toggle').removeClass('active');
            $(this).addClass('active');
            State.catType = $(this).data('val');
            State.loaded['categories'] = false;
            loadCategories();
        });

        $(document).on('click', '#cat-sort .aura-btn-toggle', function () {
            $('#cat-sort .aura-btn-toggle').removeClass('active');
            $(this).addClass('active');
            State.catSort = $(this).data('val');
            State.loaded['categories'] = false;
            loadCategories();
        });

        $('#cat-limit').on('change', function () {
            State.catLimit = parseInt($(this).val(), 10) || 10;
            State.loaded['categories'] = false;
            loadCategories();
        });

        // Comparaciones
        $('#cmp-apply').on('click', runComparison);

        // Presupuesto
        $('#budget-load').on('click', function () {
            State.loaded['budget'] = false;
            loadBudget();
        });

        $('#budget-edit').on('click', openBudgetModal);
        $('#budget-save').on('click', saveBudgets);

        // Anotaciones
        $(document).on('click', '.aura-add-annotation', function () {
            openAnnotationModal($(this).data('tab'));
        });

        $('#aura-annotation-form').on('submit', function (e) {
            e.preventDefault();
            saveAnnotation();
        });

        $(document).on('click', '.ann-delete-btn', function () {
            const annId = $(this).data('id');
            if (!confirm('¿Deseas eliminar esta anotación?')) return;
            ajaxPost('aura_analytics_annotation_delete', { annotation_id: annId })
                .done(function (res) {
                    if (res.success) {
                        State.loaded[State.activeTab] = false;
                        loadTabData(State.activeTab);
                    }
                });
        });

        // Fullscreen
        $(document).on('click', '.aura-fullscreen-btn', function () {
            fullscreenChart($(this).data('chart'));
        });

        $('#aura-exit-fullscreen').on('click', function () {
            $('#aura-fullscreen-overlay').fadeOut(200);
            const $fs = $('#aura-fullscreen-chart');
            const chartKey = $fs.find('[id]').first().attr('id');
            if (chartKey && State.charts[chartKey]) {
                State.charts[chartKey].destroy();
                delete State.charts[chartKey];
            }
            $fs.empty();
        });

        // Cerrar modales
        $(document).on('click', '.aura-modal-close, .aura-modal-overlay', closeModals);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModals();
                if ($('#aura-fullscreen-overlay').is(':visible')) {
                    $('#aura-exit-fullscreen').trigger('click');
                }
            }
        });

        setupQuickDateChips();
    }

    /* ============================================================ */
    /* INIT                                                           */
    /* ============================================================ */

    $(function () {
        if (typeof ApexCharts === 'undefined') {
            console.error('[Aura Analytics] ApexCharts no está disponible.');
            return;
        }

        bindEvents();

        // Obtener tab inicial de la URL
        let initialTab = 'trends';
        const hash = window.location.hash.replace('#tab-', '').replace('#', '');
        if (hash && ['trends', 'categories', 'comparison', 'patterns', 'budget'].includes(hash)) {
            initialTab = hash;
        }

        switchTab(initialTab);

        // Escuchar navegación atrás/adelante del navegador
        $(window).on('hashchange', function () {
            const currentHash = window.location.hash.replace('#tab-', '').replace('#', '');
            if (currentHash && currentHash !== State.activeTab && ['trends', 'categories', 'comparison', 'patterns', 'budget'].includes(currentHash)) {
                switchTab(currentHash);
            }
        });
    });

})(jQuery);
