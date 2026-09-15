/**
 * Dashboard Financiero — Fase 3, Item 3.1 & Mejoras UX/UI
 * Maneja gráficos Chart.js, drilldown interactivo de categorías/subcategorías,
 * selector línea/barra, presets de período y refresco AJAX.
 */
/* global jQuery, Chart, auraDashboard */

(function ($) {
    'use strict';

    // ─── Referencias DOM y Estado ─────────────────────────────────────────────
    var $periodBtns, $startDate, $endDate, $compareChk,
        lineChart, donutChart,
        lineChartType = 'line',
        rootDonutData = null,
        currentDrilldownCategory = null,
        refreshInProgress = false;

    // ─── Colores consistentes y Paleta ─────────────────────────────────────────
    var COLORS = {
        income:      { fill: 'rgba(16,185,129,.15)', border: '#10b981' },
        expense:     { fill: 'rgba(239,68,68,.15)',  border: '#ef4444' },
        prevIncome:  { fill: 'rgba(16,185,129,.06)', border: 'rgba(16,185,129,.4)' },
        prevExpense: { fill: 'rgba(239,68,68,.06)',  border: 'rgba(239,68,68,.4)' },
    };

    var PALETTE = [
        '#6366f1','#ec4899','#10b981','#f59e0b','#3b82f6',
        '#8b5cf6','#14b8a6','#f97316','#06b6d4','#e11d48',
        '#84cc16','#a855f7','#0ea5e9','#d97706','#475569'
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

        // ── Eventos periodo ──────────────────────────────────────────────────
        $periodBtns.on('click', function () {
            var preset = $(this).data('period');
            if (preset === 'custom') return;
            $periodBtns.removeClass('active');
            $(this).addClass('active');
            applyPreset(preset);
            savePref('period', preset);
            fetchDashboardData();
        });

        // Aplicar rango personalizado
        $('#dash-apply-custom').on('click', function () {
            if (!$startDate.val() || !$endDate.val()) {
                alert(auraDashboard.i18n ? auraDashboard.i18n.selectDates : 'Por favor seleccione las fechas');
                return;
            }
            $periodBtns.removeClass('active');
            $('[data-period="custom"]').addClass('active');
            savePref('period', 'custom');
            savePref('start', $startDate.val());
            savePref('end',   $endDate.val());
            syncBankBudgetYearFromRange();
            fetchDashboardData();
        });

        // Comparar período anterior
        $compareChk.on('change', function () {
            savePref('compare', $(this).is(':checked') ? '1' : '0');
            fetchDashboardData();
        });

        // Alternar Línea / Barras
        $('#btn-chart-type-line').on('click', function () {
            if (lineChartType === 'line') return;
            lineChartType = 'line';
            $(this).addClass('active').siblings().removeClass('active');
            if (lineChart) {
                lineChart.config.type = 'line';
                lineChart.update();
            }
        });

        $('#btn-chart-type-bar').on('click', function () {
            if (lineChartType === 'bar') return;
            lineChartType = 'bar';
            $(this).addClass('active').siblings().removeClass('active');
            if (lineChart) {
                lineChart.config.type = 'bar';
                lineChart.update();
            }
        });

        // Botón Volver de Drilldown en Gráfico Donut
        $('#aura-drilldown-back').on('click', function (e) {
            e.preventDefault();
            resetDonutToRoot();
        });

        // Exportar PNGs
        $('#export-line-png').on('click', function () { exportChart(lineChart, 'ingresos-egresos.png'); });
        $('#export-donut-png').on('click', function () { exportChart(donutChart, 'gastos-categorias.png'); });

        // Refrescar manual
        $('#dash-refresh').on('click', function () { fetchDashboardData(); });

        // Selector de año bancario
        $('#aura-bank-budget-year-select').on('change', function () {
            fetchDashboardData();
        });
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
        syncBankBudgetYearFromRange();
    }

    function syncBankBudgetYearFromRange() {
        var $select = $('#aura-bank-budget-year-select');
        var $det = $('#aura-bank-budget-year');
        if (!$select.length) return;

        var endVal = $endDate && $endDate.length ? $endDate.val() : '';
        var startVal = $startDate && $startDate.length ? $startDate.val() : '';
        var base = endVal || startVal;
        var detected = (base && /^\d{4}-\d{2}-\d{2}$/.test(base)) ? parseInt(base.slice(0, 4), 10) : (new Date()).getFullYear();

        if ($select.find('option[value="' + detected + '"]').length === 0) {
            $select.prepend('<option value="' + detected + '">' + detected + '</option>');
        }
        $select.val(String(detected));

        if ($det.length) {
            $det.text('Año detectado: ' + detected);
        }
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

        $('[data-period="' + period + '"]').addClass('active');

        if (period !== 'custom') {
            applyPreset(period);
        } else {
            var s = getPref('start', '');
            var e = getPref('end', '');
            if (s) $startDate.val(s);
            if (e) $endDate.val(e);
            syncBankBudgetYearFromRange();
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
                action:    'aura_get_dashboard_data',
                nonce:     auraDashboard.nonce,
                start:     $startDate.val(),
                end:       $endDate.val(),
                compare:   $compareChk.is(':checked') ? 1 : 0,
                bank_year: $('#aura-bank-budget-year-select').val() || '',
            },
            success: function (resp) {
                if (!resp.success) {
                    console.error('[Aura Dashboard] Error:', resp.data);
                    return;
                }
                var d = resp.data;
                renderKPIs(d.kpis);
                updateLineChart(d.chart_line);

                // Guardar datos raíz del Donut para permitir volver de drilldown
                rootDonutData = d.chart_donut;
                currentDrilldownCategory = null;
                $('#aura-drilldown-nav').hide();
                $('#aura-donut-main-title').html('<span class="dashicons dashicons-chart-pie"></span> Gastos por Categoría');
                $('#aura-drilldown-hint').html('<span class="dashicons dashicons-info"></span> Haz clic en un gasto para ver sus subcategorías');
                updateDonutChart(d.chart_donut);

                renderRecentTransactions(d.recent);
                renderAlerts(d.alerts);
                renderBankBudgetSummary(d.bank_budget);
                renderAccountsSummary(d.accounts_summary);
                renderAreaDistribution(d.area_distribution);
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
        if (!kpis) return;

        $.each(kpis, function (key, data) {
            var $card = $('.aura-kpi--' + key);
            if (!$card.length) return;

            $card.removeClass('is-loading');

            // Valor principal
            var $val = $card.find('.aura-kpi__value');
            if ($val.length) {
                $val.text(data.formatted);
            }

            // Tendencia vs período anterior
            var $trend = $card.find('.aura-kpi__trend');
            if ($trend.length) {
                if (data.pct_change !== null && data.pct_change !== undefined) {
                    var pct   = Math.abs(data.pct_change).toFixed(1) + '%';
                    var cls   = data.pct_change > 0 ? 'up' : (data.pct_change < 0 ? 'down' : 'neutral');
                    var icon  = data.pct_change > 0
                        ? '<span class="dashicons dashicons-arrow-up-alt2"></span>'
                        : (data.pct_change < 0 ? '<span class="dashicons dashicons-arrow-down-alt2"></span>' : '—');
                    $trend.show().removeClass('up down neutral').addClass(cls).html(icon + ' ' + pct);
                } else {
                    $trend.hide();
                }
            }

            // Clase especial balance y savings
            if (key === 'balance') {
                $card.removeClass('is-positive is-negative');
                if (data.raw > 0)       $card.addClass('is-positive');
                else if (data.raw < 0)  $card.addClass('is-negative');
            }
            if (key === 'savings_rate') {
                $card.removeClass('is-positive is-negative');
                if (data.raw > 0)       $card.addClass('is-positive');
                else if (data.raw < 0)  $card.addClass('is-negative');
            }
        });
    }

    // ─── Gráfico de Líneas / Barras ───────────────────────────────────────────
    function initLineChart() {
        var ctx = document.getElementById('aura-line-chart');
        if (!ctx) return;

        lineChart = new Chart(ctx, {
            type: lineChartType,
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11, family: 'Inter, system-ui, sans-serif' }, usePointStyle: true }
                    },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + formatMoney(ctx.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: { font: { size: 11, family: 'Inter, system-ui, sans-serif' } }
                    },
                    y: {
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: {
                            font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                            callback: function (v) { return '$' + Number(v).toLocaleString(); }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function updateLineChart(data) {
        if (!lineChart || !data) return;

        var incomeLabel = auraDashboard.i18n ? auraDashboard.i18n.income : 'Ingresos';
        var expenseLabel = auraDashboard.i18n ? auraDashboard.i18n.expense : 'Egresos';
        var prevIncomeLabel = auraDashboard.i18n ? auraDashboard.i18n.prevIncome : 'Ingresos ant.';
        var prevExpenseLabel = auraDashboard.i18n ? auraDashboard.i18n.prevExpense : 'Egresos ant.';

        var datasets = [
            {
                label: incomeLabel,
                data: data.income,
                borderColor: COLORS.income.border,
                backgroundColor: COLORS.income.fill,
                fill: true,
                tension: 0.3,
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                borderRadius: 4,
            },
            {
                label: expenseLabel,
                data: data.expense,
                borderColor: COLORS.expense.border,
                backgroundColor: COLORS.expense.fill,
                fill: true,
                tension: 0.3,
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                borderRadius: 4,
            }
        ];

        if (data.prev_income) {
            datasets.push({
                label: prevIncomeLabel,
                data: data.prev_income,
                borderColor: COLORS.prevIncome.border,
                backgroundColor: 'transparent',
                borderDash: [4, 3],
                tension: 0.3,
                borderWidth: 1.5,
                pointRadius: 2,
            });
        }
        if (data.prev_expense) {
            datasets.push({
                label: prevExpenseLabel,
                data: data.prev_expense,
                borderColor: COLORS.prevExpense.border,
                backgroundColor: 'transparent',
                borderDash: [4, 3],
                tension: 0.3,
                borderWidth: 1.5,
                pointRadius: 2,
            });
        }

        lineChart.data.labels   = data.labels;
        lineChart.data.datasets = datasets;
        lineChart.update();
    }

    // ─── Gráfico Donut con DRILLDOWN INTERACTIVO ──────────────────────────────
    function initDonutChart() {
        var ctx = document.getElementById('aura-donut-chart');
        if (!ctx) return;

        donutChart = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 600
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct   = total > 0 ? (ctx.parsed / total * 100).toFixed(1) : 0;
                                return ' ' + ctx.label + ': ' + formatMoney(ctx.parsed) + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                onHover: function (evt, elements) {
                    var target = evt.native ? evt.native.target : evt.target;
                    if (target) {
                        target.style.cursor = elements.length ? 'pointer' : 'default';
                    }
                },
                onClick: function (evt, elements) {
                    if (!elements.length) return;
                    var clickedIndex = elements[0].index;
                    
                    if (!currentDrilldownCategory) {
                        // Nivel Raíz: hacer drilldown a subcategorías de la categoría cliqueada
                        var catId = donutChart.data.catIds && donutChart.data.catIds[clickedIndex];
                        var catName = donutChart.data.labels && donutChart.data.labels[clickedIndex];
                        if (catId) {
                            drilldownCategory(catId, catName);
                        }
                    }
                }
            }
        });
    }

    function updateDonutChart(data) {
        if (!donutChart || !data || !data.labels || !data.labels.length) {
            if (donutChart) {
                donutChart.data.labels = [];
                donutChart.data.datasets = [];
                donutChart.update();
            }
            $('#aura-donut-legend').html('<div class="aura-empty-state"><p>No hay gastos registrados en este período.</p></div>');
            return;
        }

        var colors = data.labels.map(function (_, i) {
            var col = (data.colors && data.colors[i]) ? data.colors[i] : '';
            if (!col || col.toLowerCase() === '#607d8b' || col === '#607D8B') {
                return PALETTE[i % PALETTE.length];
            }
            return col;
        });

        donutChart.data.catIds  = data.cat_ids || [];
        donutChart.data.labels  = data.labels;
        donutChart.data.datasets = [{
            data: data.amounts,
            backgroundColor: colors,
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 6,
        }];
        donutChart.update();

        renderDonutLegend(data, colors);
    }

    function renderDonutLegend(data, colors) {
        var $legend = $('#aura-donut-legend').empty();
        var total   = data.amounts.reduce(function (a, b) { return a + b; }, 0);
        var subcatCounts = data.subcat_counts || [];

        data.labels.forEach(function (label, i) {
            var amt = data.amounts[i];
            var pct = total > 0 ? (amt / total * 100).toFixed(1) : 0;
            var isClickable = !currentDrilldownCategory;
            var subCount = subcatCounts[i] !== undefined ? subcatCounts[i] : 0;

            var $item = $('<div class="aura-donut-legend__item' + (isClickable ? ' is-clickable' : '') + '"></div>');
            $item.append('<span class="aura-donut-legend__color" style="background:' + colors[i] + '"></span>');
            
            // Contenedor de nombre y badge de subcategorías
            var $nameWrap = $('<div class="aura-donut-legend__name-wrap"></div>');
            var $name = $('<span class="aura-donut-legend__name" title="' + escHtml(label) + '">' + escHtml(label) + '</span>');
            $nameWrap.append($name);

            if (isClickable) {
                if (subCount > 0) {
                    $nameWrap.append('<span class="aura-subcat-badge" title="' + subCount + ' subcategorías registradas"><span class="dashicons dashicons-category"></span> ' + subCount + ' subcats</span>');
                } else {
                    $nameWrap.append('<span class="aura-subcat-badge aura-subcat-badge--none" title="Sin subcategorías directas">Directo</span>');
                }
            }

            $item.append($nameWrap);
            $item.append('<span class="aura-donut-legend__amt">' + formatMoney(amt) + '</span>');
            $item.append('<span class="aura-donut-legend__pct">' + pct + '%</span>');

            if (isClickable && data.cat_ids && data.cat_ids[i]) {
                $item.attr('title', 'Haz clic para explorar ' + label);
                $item.on('click', function () {
                    drilldownCategory(data.cat_ids[i], label);
                });
            }

            $legend.append($item);
        });
    }

    // ─── Lógica Drilldown ─────────────────────────────────────────────────────
    function drilldownCategory(catId, catName) {
        var $card = $('.aura-chart-card--drilldown');
        $card.addClass('is-loading-drilldown');

        $.ajax({
            url: auraDashboard.ajaxUrl,
            method: 'POST',
            data: {
                action:      'aura_get_category_drilldown',
                nonce:       auraDashboard.nonce,
                category_id: catId,
                start:       $startDate.val(),
                end:         $endDate.val(),
            },
            success: function (resp) {
                $card.removeClass('is-loading-drilldown');
                if (!resp.success) {
                    console.error('[Aura Dashboard] Error Drilldown:', resp.data);
                    return;
                }

                var d = resp.data;
                currentDrilldownCategory = { id: catId, name: catName };

                // Mostrar barra de navegación
                $('#aura-drilldown-nav').fadeIn(200);
                $('#aura-drilldown-current-name').text(catName);
                $('#aura-donut-main-title').html('<span class="dashicons dashicons-category"></span> Desglose: ' + escHtml(catName));

                var drilldownLabels = d.labels && d.labels.length ? d.labels : ['Sin desglose'];
                var drilldownAmounts = d.amounts && d.amounts.length ? d.amounts : [d.total || 0];

                // Asignar colores variados para las subcategorías (evitando gris uniforme)
                var drilldownColors = drilldownLabels.map(function (lbl, i) {
                    var col = (d.colors && d.colors[i]) ? d.colors[i] : '';
                    if (!col || col.toLowerCase() === '#607d8b' || col === '#607D8B') {
                        return PALETTE[i % PALETTE.length];
                    }
                    return col;
                });

                updateDonutChart({
                    labels: drilldownLabels,
                    amounts: drilldownAmounts,
                    colors: drilldownColors,
                    cat_ids: [],
                    subcat_counts: [],
                });
            },
            error: function () {
                $card.removeClass('is-loading-drilldown');
            }
        });
    }

    function resetDonutToRoot() {
        if (!rootDonutData) return;
        currentDrilldownCategory = null;
        $('#aura-drilldown-nav').fadeOut(200);
        $('#aura-donut-main-title').html('<span class="dashicons dashicons-chart-pie"></span> Gastos por Categoría');
        updateDonutChart(rootDonutData);
    }

    // ─── Cuentas Bancarias y Saldos ───────────────────────────────────────────
    function renderAccountsSummary(accounts) {
        var $grid = $('#aura-accounts-grid');
        if (!$grid.length) return;

        if (!accounts || !accounts.length) {
            $grid.html('<div class="aura-empty-state"><p>No hay cuentas bancarias configuradas.</p></div>');
            return;
        }

        $grid.empty();
        accounts.forEach(function (acc) {
            var icon = 'dashicons-money-alt';
            var typeLabel = 'Cuenta Bancaria';
            if (acc.account_type === 'bank_account') {
                icon = 'dashicons-bank';
                typeLabel = 'Cuenta Bancaria';
            } else if (acc.account_type === 'usd_cash') {
                icon = 'dashicons-vault';
                typeLabel = 'Caja Moneda Extranjera';
            } else if (acc.account_type === 'petty_cash') {
                icon = 'dashicons-money';
                typeLabel = 'Caja Chica';
            } else if (acc.account_type === 'contributions_fund') {
                icon = 'dashicons-heart';
                typeLabel = 'Fondo de Aportes';
            }

            var balClass = acc.balance < 0 ? 'is-negative' : (acc.balance > 0 ? 'is-positive' : 'is-zero');

            var cardHtml = 
                '<div class="aura-account-card ' + balClass + '">' +
                    '<div class="aura-account-card__top">' +
                        '<div class="aura-account-card__icon-wrap">' +
                            '<span class="dashicons ' + icon + '"></span>' +
                        '</div>' +
                        '<div class="aura-account-card__info">' +
                            '<h4 class="aura-account-card__name">' + escHtml(acc.name) + '</h4>' +
                            '<span class="aura-account-card__meta">' + escHtml(acc.institution || typeLabel) + '</span>' +
                        '</div>' +
                        '<span class="aura-currency-pill">' + escHtml(acc.currency) + '</span>' +
                    '</div>' +
                    '<div class="aura-account-card__balance">' +
                        '<span class="aura-account-card__bal-label">Saldo Disponible</span>' +
                        '<strong class="aura-account-card__amount">' + escHtml(acc.formatted) + '</strong>' +
                    '</div>' +
                    '<div class="aura-account-card__actions">' +
                        '<span class="aura-account-card__tx-count"><span class="dashicons dashicons-list-view"></span> ' + (acc.tx_count || 0) + ' transacciones</span>' +
                        '<a href="' + escHtml(auraDashboard.txListUrl || 'admin.php?page=aura-financial-transactions') + '&account_id=' + acc.id + '" class="aura-account-card__btn-link">Ver detalle →</a>' +
                    '</div>' +
                '</div>';

            $grid.append(cardHtml);
        });
    }

    // ─── Distribución por Área / Programa ─────────────────────────────────────
    function renderAreaDistribution(areas) {
        var $wrap = $('#aura-area-distribution-wrap');
        if (!$wrap.length) return;

        if (!areas || !areas.length) {
            $wrap.html('<div class="aura-empty-state"><p>No hay egresos registrados en este período.</p></div>');
            return;
        }

        $wrap.empty();
        var $list = $('<div class="aura-area-list"></div>');

        areas.forEach(function (ar, i) {
            var color = ar.color || PALETTE[i % PALETTE.length];
            var icon = ar.icon || 'dashicons-groups';
            var rowHtml = 
                '<div class="aura-area-item">' +
                    '<div class="aura-area-item__header">' +
                        '<span class="aura-area-item__name">' +
                            '<span class="dashicons ' + escHtml(icon) + '" style="color:' + color + '; margin-right:6px; font-size:17px; width:17px; height:17px;"></span>' +
                            '<strong>' + escHtml(ar.area_name) + '</strong>' +
                            (ar.tx_count ? '<span class="aura-area-item__badge">' + ar.tx_count + ' movs</span>' : '') +
                        '</span>' +
                        '<span class="aura-area-item__values">' +
                            '<strong class="aura-area-item__amount">' + escHtml(ar.formatted) + '</strong> ' +
                            '<span class="aura-area-item__pct">' + ar.percent + '%</span>' +
                        '</span>' +
                    '</div>' +
                    '<div class="aura-area-item__bar-track">' +
                        '<div class="aura-area-item__bar-fill" style="width:' + ar.percent + '%; background:' + color + '"></div>' +
                    '</div>' +
                '</div>';
            $list.append(rowHtml);
        });

        $wrap.append($list);
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
                    '<td><span class="aura-cat-badge">' + escHtml(tx.cat_name) + '</span></td>' +
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

    // ─── Presupuesto de Bancos ────────────────────────────────────────────────
    function renderBankBudgetSummary(payload) {
        var $tbody = $('#aura-bank-budget-table tbody');
        var $year = $('#aura-bank-budget-year');

        if (!payload || !payload.budget) {
            $('#bb-kpi-limit, #bb-kpi-spent, #bb-kpi-remaining, #bb-kpi-policy').text('—');
            if ($tbody.length) {
                $tbody.html('<tr><td colspan="6">Sin datos de presupuesto disponibles.</td></tr>');
            }
            return;
        }

        var budget = payload.budget || {};
        var months = Array.isArray(budget.months) ? budget.months : [];
        var policyTxt = (budget.policy || 'warn') === 'block' ? 'Bloquear excedentes' : 'Advertir';

        if ($year.length) {
            var detText = payload.detected_year ? ('Año detectado: ' + payload.detected_year) : 'Año detectado: —';
            $year.text(detText);
        }

        var $select = $('#aura-bank-budget-year-select');
        if ($select.length && payload.year) {
            if ($select.find('option[value="' + payload.year + '"]').length === 0) {
                $select.prepend('<option value="' + payload.year + '">' + payload.year + '</option>');
            }
            $select.val(String(payload.year));
        }

        // Llenar tarjetas KPI superiores
        $('#bb-kpi-limit').text(formatMoney(budget.annual_limit || 0));
        $('#bb-kpi-spent').text(formatMoney(budget.annual_spent || 0));
        
        var rem = budget.annual_remaining || 0;
        var $remKpi = $('#bb-kpi-remaining').text(formatMoney(rem));
        if (rem < 0) {
            $remKpi.addClass('is-negative').removeClass('is-positive');
        } else {
            $remKpi.addClass('is-positive').removeClass('is-negative');
        }

        $('#bb-kpi-policy').text(policyTxt);

        if (!$tbody.length) return;

        if (!months.length) {
            $tbody.html('<tr><td colspan="6">No hay distribución mensual configurada para este año.</td></tr>');
            return;
        }

        var rows = months.map(function (row) {
            var monthNum = parseInt(row.month_num, 10) || 1;
            var monthLabel = getMonthFullName(monthNum);
            var limit = parseFloat(row.limit || 0);
            var spent = parseFloat(row.spent || 0);
            var remaining = parseFloat(row.remaining || 0);

            var ratio = limit > 0 ? (spent / limit) * 100 : (spent > 0 ? 100 : 0);
            var ratioSafe = Math.max(0, ratio);
            var fill = Math.min(100, ratioSafe);

            var toneClass = 'is-green';
            var statusBadge = '<span class="aura-budget-chip aura-budget-chip--success"><span class="dashicons dashicons-yes-alt"></span> En rango</span>';

            if (remaining < 0) {
                toneClass = 'is-red';
                statusBadge = '<span class="aura-budget-chip aura-budget-chip--danger"><span class="dashicons dashicons-warning"></span> Sobregirado</span>';
            } else if (ratioSafe >= 70) {
                toneClass = 'is-orange';
                statusBadge = '<span class="aura-budget-chip aura-budget-chip--warning"><span class="dashicons dashicons-clock"></span> Alerta (>70%)</span>';
            }

            var percentText = (Math.round(ratioSafe * 10) / 10).toFixed(1).replace('.0', '') + '%';
            var remainingClass = remaining < 0 ? 'is-negative' : 'is-positive';

            var monthStart = payload.year + '-' + ('0' + monthNum).slice(-2) + '-01';
            var lastDay = new Date(payload.year, monthNum, 0).getDate();
            var monthEnd = payload.year + '-' + ('0' + monthNum).slice(-2) + '-' + ('0' + lastDay).slice(-2);

            return '<tr>' +
                '<td><strong>' + escHtml(monthLabel) + '</strong></td>' +
                '<td>' + escHtml(formatMoney(limit)) + '</td>' +
                '<td>' + escHtml(formatMoney(spent)) + '</td>' +
                '<td><span class="aura-bank-budget-remaining ' + remainingClass + '">' + escHtml(formatMoney(remaining)) + '</span></td>' +
                '<td>' +
                    '<div class="aura-bank-budget-progress ' + toneClass + '">' +
                        '<span class="aura-bank-budget-progress__track"><span class="aura-bank-budget-progress__bar" style="width:' + fill + '%"></span></span>' +
                        '<span class="aura-bank-budget-progress__label">' + escHtml(percentText) + '</span>' +
                    '</div>' +
                    '<div style="margin-top:4px;">' + statusBadge + '</div>' +
                '</td>' +
                '<td><a class="button button-small" href="' + escHtml(auraDashboard.txListUrl || 'admin.php?page=aura-financial-transactions') + '&start_date=' + monthStart + '&end_date=' + monthEnd + '">Ver movimientos</a></td>' +
            '</tr>';
        }).join('');

        $tbody.html(rows);
    }

    function getMonthFullName(monthNum) {
        var names = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        var idx = Math.min(12, Math.max(1, monthNum)) - 1;
        return names[idx];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────
    function formatMoney(val) {
        return '$' + parseFloat(val || 0).toLocaleString('es-CO', {
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
                        (auraDashboard.i18n ? auraDashboard.i18n.refreshing : 'Actualizando datos…') +
                    '</div>'
                );
            }
        } else {
            $r.remove();
        }
    }

})(jQuery);
