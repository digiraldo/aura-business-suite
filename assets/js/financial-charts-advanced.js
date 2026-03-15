/**
 * Análisis Visual – Fase 3, Item 3.3
 * Cliente JS para los 5 tabs de gráficos interactivos con ApexCharts
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
    /* AJAX HELPER                                                    */
    /* ============================================================ */

    function ajaxPost(action, data) {
        return $.ajax({
            url:    auraAnalytics.ajaxurl,
            method: 'POST',
            data:   Object.assign({ action, nonce: auraAnalytics.nonce }, data),
        });
    }

    function formatCurrency(val) {
        const n = parseFloat(val) || 0;
        return auraAnalytics.currency_symbol + ' ' + n.toLocaleString('es', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    /* ============================================================ */
    /* GESTIÓN DE TABS                                                */
    /* ============================================================ */

    function switchTab(tabId) {
        State.activeTab = tabId;

        $('.nav-tab').removeClass('nav-tab-active');
        $('[data-tab="' + tabId + '"].nav-tab').addClass('nav-tab-active');

        $('.aura-tab-content').removeClass('active').hide();
        $('#tab-' + tabId).addClass('active').show();

        if (!State.loaded[tabId]) {
            loadTabData(tabId);
        }
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

        const fsChart = new ApexCharts(innerDiv, instance.opts);
        fsChart.render();

        $overlay.show();
        State.charts[chartId + '-fs'] = fsChart;
    }

    /* ============================================================ */
    /* TAB 1 – TENDENCIAS                                             */
    /* ============================================================ */

    function loadTrends() {
        const $container = $('#chart-trends');
        $container.html('<div class="aura-loading">' + auraAnalytics.txt.loading + '</div>');

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
            $container.html('<p class="error">' + auraAnalytics.txt.error + '</p>');
        });
    }

    function renderTrends(data) {
        const $el = document.getElementById('chart-trends');
        if (!$el) return;

        // Combinar datos reales + proyección
        const projStart = data.labels.length;
        const projLabels = [];
        for (let i = 0; i < (data.projection?.length || 0); i++) {
            projLabels.push(auraAnalytics.txt.projection + ' +' + (i + 1));
        }

        const allLabels  = [...data.labels, ...projLabels];
        const incPadded  = [...data.income,  ...new Array(projLabels.length).fill(null)];
        const expPadded  = [...data.expense, ...new Array(projLabels.length).fill(null)];
        const balPadded  = [...data.balance, ...new Array(projLabels.length).fill(null)];
        const projPadded = [...new Array(data.labels.length).fill(null), ...(data.projection || [])];

        const opts = {
            chart: {
                type:      'line',
                height:    420,
                zoom:      { enabled: true, type: 'x' },
                toolbar:   { show: true },
                animations:{ enabled: true },
            },
            series: [
                { name: auraAnalytics.txt.income,     data: incPadded,  color: '#10b981' },
                { name: auraAnalytics.txt.expense,    data: expPadded,  color: '#ef4444' },
                { name: auraAnalytics.txt.balance,    data: balPadded,  color: '#3b82f6' },
                { name: auraAnalytics.txt.projection, data: projPadded, color: '#a78bfa', dashArray: 6 },
            ],
            xaxis: { categories: allLabels },
            yaxis: {
                labels: {
                    formatter: val => auraAnalytics.currency_symbol + ' ' + Number(val).toLocaleString('es'),
                },
            },
            tooltip: {
                shared: true,
                y: { formatter: val => formatCurrency(val) },
                custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                    const label = w.globals.categoryLabels[dataPointIndex] || allLabels[dataPointIndex];
                    const cnt   = data.counts?.[dataPointIndex] ?? 0;
                    let html = '<div class="aura-apex-tooltip"><strong>' + label + '</strong>';
                    w.globals.seriesNames.forEach((name, i) => {
                        const val = series[i][dataPointIndex];
                        if (val !== null) {
                            html += '<div class="tt-row"><span class="tt-name">' + name + '</span>'
                                + '<span class="tt-val">' + formatCurrency(val) + '</span></div>';
                        }
                    });
                    if (cnt) {
                        html += '<div class="tt-row"><span class="tt-name">' + auraAnalytics.txt.transactions + '</span>'
                            + '<span class="tt-val">' + cnt + '</span></div>';
                    }
                    html += '<a href="' + auraAnalytics.transactions_url + '" class="tt-link">' + auraAnalytics.txt.see_detail + '</a>';
                    html += '</div>';
                    return html;
                },
            },
            stroke:   { curve: 'smooth', width: [2, 2, 2, 1] },
            markers:  { size: 4 },
            annotations: parseAnnotationsForApex(data.annotations),
            noData:   { text: auraAnalytics.txt.no_data },
        };

        $($el).empty();
        const chart = new ApexCharts($el, opts);
        chart.opts = opts;
        chart.render();
        State.charts['chart-trends'] = chart;
    }

    /* ============================================================ */
    /* TAB 2 – DISTRIBUCIÓN                                           */
    /* ============================================================ */

    function loadCategories() {
        const $container = $('#chart-categories');
        $container.html('<div class="aura-loading">' + auraAnalytics.txt.loading + '</div>');

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
            renderCategories(res.data.categories);
            State.loaded['categories'] = true;
        });
    }

    function renderCategories(cats) {
        const $el = document.getElementById('chart-categories');
        if (!$el || !cats.length) {
            $($el).html('<p class="aura-no-data">' + auraAnalytics.txt.no_data + '</p>');
            return;
        }

        const labels  = cats.map(c => c.name);
        const incomes  = cats.map(c => parseFloat(c.income));
        const expenses = cats.map(c => parseFloat(c.expense));
        const colors   = cats.map(c => c.color || '#3b82f6');

        const series = State.catType === 'income'
            ? [{ name: auraAnalytics.txt.income,  data: incomes,  color: '#10b981' }]
            : State.catType === 'expense'
            ? [{ name: auraAnalytics.txt.expense, data: expenses, color: '#ef4444' }]
            : [
                { name: auraAnalytics.txt.income,  data: incomes,  color: '#10b981' },
                { name: auraAnalytics.txt.expense, data: expenses, color: '#ef4444' },
              ];

        const opts = {
            chart:   { type: 'bar', height: Math.max(300, cats.length * 40 + 100) },
            plotOptions: { bar: { horizontal: true, dataLabels: { position: 'top' } } },
            series,
            xaxis: {
                categories: labels,
                labels: { formatter: val => auraAnalytics.currency_symbol + ' ' + Number(val).toLocaleString('es') },
            },
            tooltip: { y: { formatter: val => formatCurrency(val) } },
            noData: { text: auraAnalytics.txt.no_data },
        };

        $($el).empty();
        const chart = new ApexCharts($el, opts);
        chart.opts = opts;
        chart.render();
        State.charts['chart-categories'] = chart;
    }

    /* ============================================================ */
    /* TAB 3 – COMPARACIONES                                          */
    /* ============================================================ */

    function loadComparison() {
        if (State.loaded['comparison']) return; // sólo se carga al presionar "Comparar"
    }

    function runComparison() {
        const $container = $('#chart-comparison');
        $container.html('<div class="aura-loading">' + auraAnalytics.txt.loading + '</div>');

        ajaxPost('aura_analytics_comparison', {
            a_start: $('#cmp-a-start').val(),
            a_end:   $('#cmp-a-end').val(),
            b_start: $('#cmp-b-start').val(),
            b_end:   $('#cmp-b-end').val(),
        }).done(function (res) {
            if (!res.success) {
                $container.html('<p class="error">' + (res.data?.message || 'Error') + '</p>');
                return;
            }
            renderComparison(res.data);
        });
    }

    function renderComparison(data) {
        const cats = data.categories;
        const labels = cats.map(c => c.category);

        const opts = {
            chart:  { type: 'bar', height: Math.max(300, labels.length * 35 + 100) },
            plotOptions: { bar: { horizontal: true, grouped: true } },
            series: [
                {
                    name: 'Período A (ingreso-egreso)',
                    data: cats.map(c => parseFloat(c.a_income) - parseFloat(c.a_expense)),
                    color: '#3b82f6',
                },
                {
                    name: 'Período B (ingreso-egreso)',
                    data: cats.map(c => parseFloat(c.b_income) - parseFloat(c.b_expense)),
                    color: '#f59e0b',
                },
            ],
            xaxis: {
                categories: labels,
                labels: { formatter: val => auraAnalytics.currency_symbol + ' ' + Number(val).toLocaleString('es') },
            },
            tooltip: { y: { formatter: val => formatCurrency(val) } },
            noData: { text: auraAnalytics.txt.no_data },
        };

        const $el = document.getElementById('chart-comparison');
        $($el).empty();
        const chart = new ApexCharts($el, opts);
        chart.opts = opts;
        chart.render();
        State.charts['chart-comparison'] = chart;

        // Tabla de diferencias
        const $tbody = $('#cmp-table-body').empty();
        cats.forEach(function (c) {
            const isMax = Math.abs(c.abs_diff) === Math.max(...cats.map(x => Math.abs(x.abs_diff)));
            const pct   = c.pct_diff !== null ? c.pct_diff + '%' : '—';
            const dir   = c.abs_diff > 0 ? '▲' : c.abs_diff < 0 ? '▼' : '—';
            const cls   = c.abs_diff > 0 ? 'positive' : c.abs_diff < 0 ? 'negative' : '';
            $tbody.append(
                '<tr' + (isMax ? ' class="highlight-max"' : '') + '>' +
                '<td>' + escHtml(c.category) + '</td>' +
                '<td>' + formatCurrency(parseFloat(c.a_income) - parseFloat(c.a_expense)) + '</td>' +
                '<td>' + formatCurrency(parseFloat(c.b_income) - parseFloat(c.b_expense)) + '</td>' +
                '<td class="' + cls + '">' + dir + ' ' + formatCurrency(c.abs_diff) + '</td>' +
                '<td class="' + cls + '">' + pct + '</td>' +
                '</tr>'
            );
        });
    }

    /* ============================================================ */
    /* TAB 4 – PATRONES                                               */
    /* ============================================================ */

    function loadPatterns() {
        [
            '#chart-heatmap',
            '#chart-scatter',
        ].forEach(sel => $(sel).html('<div class="aura-loading">' + auraAnalytics.txt.loading + '</div>'));

        ajaxPost('aura_analytics_patterns', {
            start_date: State.startDate,
            end_date:   State.endDate,
        }).done(function (res) {
            if (!res.success) return;
            renderHeatmap(res.data);
            renderScatter(res.data);
            renderOutliers(res.data.outliers);
            State.loaded['patterns'] = true;
        });
    }

    function renderHeatmap(data) {
        // ApexCharts heatmap: eje x = días de la semana, eje y = semanas
        const days   = data.dow_labels;
        const counts = data.heatmap;   // array 7
        const amounts= data.heatmap_amount;

        const $el = document.getElementById('chart-heatmap');
        $($el).empty();

        const opts = {
            chart:  { type: 'bar', height: 200 },
            series: [
                { name: auraAnalytics.txt.transactions, data: counts },
            ],
            xaxis:  { categories: days },
            yaxis:  { title: { text: auraAnalytics.txt.transactions } },
            colors: ['#3b82f6'],
            tooltip: {
                custom: function ({ series, dataPointIndex }) {
                    const cnt = series[0][dataPointIndex];
                    const amt = amounts[dataPointIndex];
                    return '<div class="aura-apex-tooltip">'
                        + '<strong>' + days[dataPointIndex] + '</strong>'
                        + '<div>' + auraAnalytics.txt.transactions + ': ' + cnt + '</div>'
                        + '<div>Total: ' + formatCurrency(amt) + '</div>'
                        + '</div>';
                },
            },
            plotOptions: { bar: { borderRadius: 4 } },
            noData: { text: auraAnalytics.txt.no_data },
        };

        const chart = new ApexCharts($el, opts);
        chart.opts = opts;
        chart.render();
        State.charts['chart-heatmap'] = chart;
    }

    function renderScatter(data) {
        const scatter = data.scatter;
        const $el = document.getElementById('chart-scatter');
        if (!scatter || !scatter.length) {
            $($el).html('<p class="aura-no-data">' + auraAnalytics.txt.no_data + '</p>');
            return;
        }
        $($el).empty();

        const series = scatter.map(c => ({
            name: c.cat,
            data: [[parseFloat(c.freq), parseFloat(c.avg_amount)]],
        }));

        const opts = {
            chart:  { type: 'scatter', height: 220, zoom: { enabled: true } },
            series,
            xaxis:  { title: { text: 'Frecuencia' }, type: 'numeric' },
            yaxis:  {
                title: { text: auraAnalytics.txt.avg_amount },
                labels: { formatter: val => formatCurrency(val) },
            },
            tooltip: {
                custom: function ({ seriesIndex, dataPointIndex, w }) {
                    const c  = scatter[seriesIndex];
                    return '<div class="aura-apex-tooltip">'
                        + '<strong>' + escHtml(c.cat) + '</strong>'
                        + '<div>Frecuencia: ' + c.freq + '</div>'
                        + '<div>Prom: ' + formatCurrency(c.avg_amount) + '</div>'
                        + '<div>Total: ' + formatCurrency(c.total) + '</div>'
                        + '</div>';
                },
            },
            noData: { text: auraAnalytics.txt.no_data },
        };

        const chart = new ApexCharts($el, opts);
        chart.opts = opts;
        chart.render();
        State.charts['chart-scatter'] = chart;
    }

    function renderOutliers(outliers) {
        const $tbody = $('#outliers-body').empty();
        if (!outliers || !outliers.length) {
            $tbody.html('<tr><td colspan="5" class="aura-empty-row">' + auraAnalytics.txt.no_outliers + '</td></tr>');
            return;
        }
        outliers.forEach(function (o) {
            const typeLabel = o.transaction_type === 'income' ? auraAnalytics.txt.income : auraAnalytics.txt.expense;
            $tbody.append(
                '<tr>' +
                '<td>' + escHtml(o.transaction_date) + '</td>' +
                '<td>' + escHtml(o.description || '—') + '</td>' +
                '<td>' + escHtml(o.cat || '—') + '</td>' +
                '<td>' + typeLabel + '</td>' +
                '<td>' + formatCurrency(o.amount) + '</td>' +
                '</tr>'
            );
        });
    }

    /* ============================================================ */
    /* TAB 5 – PRESUPUESTO                                            */
    /* ============================================================ */

    function loadBudget() {
        State.budgetYear  = parseInt($('#budget-year').val())  || new Date().getFullYear();
        State.budgetMonth = parseInt($('#budget-month').val()) || new Date().getMonth() + 1;

        const $container = $('#chart-budget');
        $container.html('<div class="aura-loading">' + auraAnalytics.txt.loading + '</div>');

        ajaxPost('aura_analytics_budget', {
            year:  State.budgetYear,
            month: State.budgetMonth,
        }).done(function (res) {
            if (!res.success) {
                $container.html('<p class="error">' + (res.data?.message || 'Error') + '</p>');
                return;
            }
            renderBudget(res.data);
            State.loaded['budget'] = true;
        });
    }

    function renderBudget(data) {
        const items  = data.items;
        const $el    = document.getElementById('chart-budget');
        const labels = items.map(i => i.name);
        const budgets = items.map(i => parseFloat(i.budget));
        const actuals = items.map(i => parseFloat(i.actual));
        const colors  = items.map(i => i.over ? '#ef4444' : '#10b981');

        $($el).empty();

        const opts = {
            chart: { type: 'bar', height: Math.max(300, labels.length * 40 + 100) },
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: false,
                },
            },
            series: [
                { name: auraAnalytics.txt.budget, data: budgets, color: '#cbd5e1' },
                { name: auraAnalytics.txt.actual, data: actuals.map((v, i) => ({ x: labels[i], y: v, fillColor: colors[i] })) },
            ],
            xaxis: {
                categories: labels,
                labels: { formatter: val => auraAnalytics.currency_symbol + ' ' + Number(val).toLocaleString('es') },
            },
            tooltip: {
                y: { formatter: val => formatCurrency(val) },
            },
            noData: { text: auraAnalytics.txt.no_data },
        };

        const chart = new ApexCharts($el, opts);
        chart.opts = opts;
        chart.render();
        State.charts['chart-budget'] = chart;

        // Tabla
        const $tbody = $('#budget-table-body').empty();
        if (!items.length) {
            $tbody.html('<tr><td colspan="6" class="aura-empty-row">' + auraAnalytics.txt.no_data + '</td></tr>');
            return;
        }
        items.forEach(function (item) {
            const pct  = item.pct !== null ? item.pct + '%' : '—';
            const cls  = item.over ? 'over-budget' : '';
            const icon = item.over ? '🔴' : '🟢';
            $tbody.append(
                '<tr class="' + cls + '">' +
                '<td>' + escHtml(item.name) + '</td>' +
                '<td>' + formatCurrency(item.budget) + '</td>' +
                '<td>' + formatCurrency(item.actual) + '</td>' +
                '<td>' + pct + '</td>' +
                '<td>' + formatCurrency(item.projection) + '</td>' +
                '<td>' + icon + ' ' + (item.over ? auraAnalytics.txt.over_budget : auraAnalytics.txt.on_track) + '</td>' +
                '</tr>'
            );
        });
    }

    /* ============================================================ */
    /* EDICIÓN DE PRESUPUESTO                                         */
    /* ============================================================ */

    function openBudgetModal() {
        const $modal = $('#aura-budget-modal');
        const $items = $('#budget-form-items');
        $items.html('<div class="aura-loading">' + auraAnalytics.txt.loading + '</div>');
        $modal.show();

        ajaxPost('aura_analytics_categories', {
            start_date: State.startDate,
            end_date:   State.endDate,
            type: 'both',
            sort: 'alpha',
            limit: 20,
        }).done(function (res) {
            if (!res.success) {
                $items.html('<p class="error">Error al cargar categorías</p>');
                return;
            }
            const cats = res.data.categories;
            let html = '<table class="widefat striped"><thead><tr>'
                + '<th>Categoría</th><th>Presupuesto mensual</th></tr></thead><tbody>';
            cats.forEach(c => {
                html += '<tr>'
                    + '<td>' + escHtml(c.name) + '</td>'
                    + '<td><input type="number" class="budget-input" data-id="' + c.id + '" '
                    + 'data-name="' + escAttr(c.name) + '" step="0.01" min="0" '
                    + 'placeholder="0.00" value=""></td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            $items.html(html);

            // Cargar valores actuales
            ajaxPost('aura_analytics_budget', {
                year: State.budgetYear, month: State.budgetMonth,
            }).done(function (br) {
                if (!br.success) return;
                br.data.items.forEach(item => {
                    $('[data-id="' + item.id + '"].budget-input').val(item.budget || '');
                });
            });
        });
    }

    function saveBudgets() {
        const budgets = {};
        $('.budget-input').each(function () {
            const id  = $(this).data('id');
            const val = parseFloat($(this).val()) || 0;
            const nm  = $(this).data('name');
            if (id) {
                budgets[id] = { amount: val, name: nm };
            }
        });

        ajaxPost('aura_analytics_budget_save', {
            year:    State.budgetYear,
            month:   State.budgetMonth,
            budgets: JSON.stringify(budgets),
        }).done(function (res) {
            if (res.success) {
                closeModals();
                State.loaded['budget'] = false;
                loadBudget();
                showNotice(auraAnalytics.txt.saved, 'success');
            } else {
                showNotice(res.data?.message || 'Error', 'error');
            }
        });
    }

    /* ============================================================ */
    /* ANOTACIONES                                                    */
    /* ============================================================ */

    function openAnnotationModal(tab) {
        $('#ann-tab').val(tab);
        $('#ann-date').val(State.startDate);
        $('#ann-note').val('');
        $('#aura-annotation-modal').show();
    }

    function saveAnnotation() {
        ajaxPost('aura_analytics_annotation_save', {
            tab:  $('#ann-tab').val(),
            date: $('#ann-date').val(),
            note: $('#ann-note').val(),
        }).done(function (res) {
            if (res.success) {
                closeModals();
                State.loaded[State.activeTab] = false;
                loadTabData(State.activeTab);
                showNotice(auraAnalytics.txt.annotation_saved, 'success');
            } else {
                showNotice(res.data?.message || 'Error', 'error');
            }
        });
    }

    function renderAnnotationsList(tab, annotations) {
        const $list = $('#' + tab + '-annotations').empty();
        if (!annotations || !annotations.length) {
            return;
        }
        let html = '<div class="ann-list-header">' + auraAnalytics.txt.annotations + '</div><ul>';
        annotations.forEach(a => {
            html += '<li data-id="' + a.id + '">'
                + '<span class="ann-date">' + a.annotation_date + '</span> '
                + '<span class="ann-note">' + escHtml(a.note) + '</span>'
                + '<button class="ann-delete-btn" data-id="' + a.id + '">✕</button>'
                + '</li>';
        });
        html += '</ul>';
        $list.html(html);
    }

    /* ============================================================ */
    /* UTILIDADES                                                     */
    /* ============================================================ */

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str);
    }

    function closeModals() {
        $('.aura-modal').hide();
    }

    function showNotice(msg, type) {
        const cls = type === 'success' ? 'notice-success' : 'notice-error';
        const $n  = $('<div class="notice ' + cls + ' is-dismissible"><p>' + escHtml(msg) + '</p></div>');
        $('.aura-analytics-wrap h1').after($n);
        setTimeout(() => $n.fadeOut(() => $n.remove()), 3000);
    }

    /* ============================================================ */
    /* EVENTOS                                                        */
    /* ============================================================ */

    function bindEvents() {
        // Cambio de tab
        $(document).on('click', '.nav-tab[data-tab]', function (e) {
            e.preventDefault();
            switchTab($(this).data('tab'));
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
            $('#aura-filter-end').val(new Date().toISOString().substring(0, 10));
            State.startDate = year + '-01-01';
            State.endDate   = new Date().toISOString().substring(0, 10);
            State.loaded    = {};
            loadTabData(State.activeTab);
        });

        // Granularidad trends
        $(document).on('click', '#trends-granularity .aura-btn-toggle', function () {
            $('#trends-granularity .aura-btn-toggle').removeClass('active');
            $(this).addClass('active');
            State.trendsGran = $(this).data('gran');
            State.loaded['trends'] = false;
            loadTrends();
        });

        // Tipo y ordenación de categorías
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
            State.catLimit = parseInt($(this).val()) || 10;
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
            if (!confirm(auraAnalytics.txt.confirm_delete)) return;
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
            $('#aura-fullscreen-overlay').hide();
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
            if (e.key === 'Escape') closeModals();
        });
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

        // Mostrar solo el primer tab activo
        $('.aura-tab-content').hide();
        $('#tab-trends').show();

        // Cargar el tab inicial
        loadTabData('trends');
    });

})(jQuery);
