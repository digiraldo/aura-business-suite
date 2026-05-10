/**
 * Vehicle Dashboard — Fase 5: Dashboard y KPIs
 *
 * Carga KPIs y gráficas vía REST API + Chart.js.
 * Se actualiza al cambiar el período o el área seleccionada.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */
(function ($) {
    'use strict';

    var cfg   = window.auraVehiclesConfig || {};
    var API   = (cfg.apiBase || '/wp-json/aura/v1/') + 'vehicles/';

    // ── Estado ──────────────────────────────────────────────────────
    var state = { period: '30d', areaId: 0 };

    // Instancias de Chart.js activas  { id: Chart }
    var charts = {};

    // Etiquetas de período
    var periodLabels = {
        '7d':   'últimos 7 días',
        '30d':  'últimos 30 días',
        '90d':  'últimos 90 días',
        'year': 'año actual',
    };

    // ── INIT ─────────────────────────────────────────────────────────
    function init() {
        if ( ! document.getElementById('aura-veh-dashboard-page') ) {
            return;
        }

        // Botones de período
        $(document).on('click', '.aura-dash-period-btn', function () {
            $('.aura-dash-period-btn').removeClass('is-active');
            $(this).addClass('is-active');
            state.period = $(this).data('period') || '30d';
            updatePeriodLabel();
            loadAll();
        });

        // Selector de área
        $(document).on('change', '#aura-dash-area-filter', function () {
            state.areaId = parseInt($(this).val(), 10) || 0;
            loadAll();
        });

        // Botón actualizar
        $(document).on('click', '#aura-dash-refresh', function () {
            loadAll();
        });

        loadAll();
    }

    // ── ACTUALIZAR ETIQUETA DE PERÍODO ───────────────────────────────
    function updatePeriodLabel() {
        var $label = $('#aura-dash-period-label');
        var label  = periodLabels[state.period] || state.period;
        $label.text('Actividad — ' + label);
    }

    // ── CARGA PARALELA DE TODOS LOS DATOS ───────────────────────────
    function loadAll() {
        setLoading(true);

        var base = { period: state.period, area_id: state.areaId };
        var hdr  = { 'X-WP-Nonce': cfg.nonce || '' };

        function req(extra) {
            return $.ajax({
                url:     API + 'stats' + (extra.type ? '/chart' : ''),
                data:    $.extend({}, base, extra),
                headers: hdr,
                method:  'GET',
            });
        }

        $.when(
            req({}),                                         // KPIs
            req({ type: 'fleet-status'      }),              // doughnut flota
            req({ type: 'km-by-vehicle'     }),              // bar KM
            req({ type: 'usage-by-area'     }),              // bar h áreas
            req({ type: 'monthly-activity'  }),              // line diaria
            req({ type: 'cost-vs-income'    })               // bar agrupado
        ).done(function (kpiR, fleetR, kmR, areaR, activityR, costR) {
            renderKPIs(kpiR[0]);
            renderFleetStatus(fleetR[0].data);
            renderKmByVehicle(kmR[0].data);
            renderUsageByArea(areaR[0].data);
            renderMonthlyActivity(activityR[0].data);
            renderCostVsIncome(costR[0].data);
            showLastUpdate();
        }).fail(function (jqXHR) {
            var msg = 'Error al cargar los datos del dashboard.';
            if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
                msg = jqXHR.responseJSON.message;
            }
            showNotice(msg, 'error');
        }).always(function () {
            setLoading(false);
        });
    }

    // ── RENDIR KPIs ──────────────────────────────────────────────────
    function renderKPIs(data) {
        if (!data) { return; }

        var sc = data.status_counts || {};

        $('#kpi-available').text(sc.available   || 0);
        $('#kpi-rented').text(sc.rented          || 0);
        $('#kpi-maintenance').text(sc.maintenance || 0);
        $('#kpi-unavailable').text(sc.unavailable || 0);
        $('#kpi-active-trips').text(data.active_trips   || 0);
        $('#kpi-trips-today').text(data.trips_today      || 0);
        $('#kpi-km-total').text(formatNumber(data.km_total || 0) + ' km');
        $('#kpi-income').text(formatMoney(data.income_total || 0));
        $('#kpi-costs').text(formatMoney(data.costs_total  || 0));

        updatePeriodLabel();
    }

    // ── UTILS DE CHARTS ──────────────────────────────────────────────

    /** Destruye el chart previo si existe */
    function destroyChart(id) {
        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }
    }

    /**
     * Devuelve el elemento <canvas> con ID dado.
     * Si el wrapper tiene un estado vacío, lo limpia y restaura el canvas.
     */
    function getCanvas(id) {
        var el = document.getElementById(id);
        if (!el) {
            // El canvas pudo haber sido eliminado por showEmpty(); lo restaura
            var wrapper = document.getElementById('wrap-' + id);
            if (wrapper) {
                $(wrapper).html('<canvas id="' + id + '"></canvas>');
                el = document.getElementById(id);
            }
        }
        return el;
    }

    /** Muestra un mensaje de "sin datos" en el wrapper del canvas */
    function showEmpty(wrapperId) {
        var wrapper = document.getElementById(wrapperId);
        if (wrapper) {
            $(wrapper).html(
                '<div class="aura-dash-chart-empty">' +
                '<span class="dashicons dashicons-chart-bar"></span>' +
                '<p>Sin datos para este período.</p>' +
                '</div>'
            );
        }
    }

    // ── GRÁFICA 1: Fleet Status (doughnut) ────────────────────────
    function renderFleetStatus(data) {
        destroyChart('fleet-status');
        if (!data || !data.labels || !data.labels.length) {
            showEmpty('wrap-fleet-status');
            return;
        }
        var ctx = getCanvas('chart-fleet-status');
        if (!ctx) { return; }

        charts['fleet-status'] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data:            data.values,
                    backgroundColor: data.colors,
                    borderWidth:     2,
                    borderColor:     '#fff',
                    hoverOffset:     6,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                cutout:              '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels:   { padding: 14, font: { size: 12 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct   = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
                                return ' ' + ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    // ── GRÁFICA 2: KM por vehículo (bar vertical) ─────────────────
    function renderKmByVehicle(data) {
        destroyChart('km-by-vehicle');
        if (!data || !data.labels || !data.labels.length) {
            showEmpty('wrap-km-by-vehicle');
            return;
        }
        var ctx = getCanvas('chart-km-by-vehicle');
        if (!ctx) { return; }

        charts['km-by-vehicle'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label:           'KM recorridos',
                    data:            data.values,
                    backgroundColor: '#2271b1',
                    borderRadius:    4,
                    borderSkipped:   false,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 40,
                            font:        { size: 10 },
                            callback:    function (val, i) {
                                var lbl = this.getLabelForValue(i);
                                return lbl.length > 18 ? lbl.substring(0, 18) + '…' : lbl;
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) { return formatNumber(v) + ' km'; },
                        },
                    },
                },
            },
        });
    }

    // ── GRÁFICA 3: Uso por área (bar horizontal) ──────────────────
    function renderUsageByArea(data) {
        destroyChart('usage-by-area');
        if (!data || !data.labels || !data.labels.length) {
            showEmpty('wrap-usage-by-area');
            return;
        }
        var ctx = getCanvas('chart-usage-by-area');
        if (!ctx) { return; }

        // Generar colores base
        var palette = [
            '#2271b1','#00ba88','#f0b849','#d63638','#7c3aed',
            '#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6',
            '#06b6d4','#22c55e',
        ];
        var bgColors = data.labels.map(function (_, i) {
            return palette[i % palette.length];
        });

        charts['usage-by-area'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label:           'Salidas',
                    data:            data.values,
                    backgroundColor: bgColors,
                    borderRadius:    4,
                    borderSkipped:   false,
                }],
            },
            options: {
                indexAxis:           'y',
                responsive:          true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks:       { stepSize: 1, precision: 0 },
                    },
                    y: {
                        ticks: {
                            font: { size: 11 },
                            callback: function (val, i) {
                                var lbl = this.getLabelForValue(i);
                                return lbl.length > 22 ? lbl.substring(0, 22) + '…' : lbl;
                            },
                        },
                    },
                },
            },
        });
    }

    // ── GRÁFICA 4: Actividad diaria (line) ────────────────────────
    function renderMonthlyActivity(data) {
        destroyChart('monthly-activity');
        if (!data || !data.labels || !data.labels.length) {
            showEmpty('wrap-monthly-activity');
            return;
        }
        var ctx = getCanvas('chart-monthly-activity');
        if (!ctx) { return; }

        charts['monthly-activity'] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label:           'Salidas',
                    data:            data.values,
                    borderColor:     '#2271b1',
                    backgroundColor: 'rgba(34,113,177,.1)',
                    pointBackgroundColor: '#2271b1',
                    pointRadius:     4,
                    pointHoverRadius: 6,
                    tension:         0.35,
                    fill:            true,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            font:        { size: 10 },
                            maxTicksLimit: 15,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks:       { stepSize: 1, precision: 0 },
                    },
                },
            },
        });
    }

    // ── GRÁFICA 5: Costos vs Ingresos (bar agrupado) ──────────────
    function renderCostVsIncome(data) {
        destroyChart('cost-vs-income');
        if (!data || !data.labels || !data.labels.length) {
            showEmpty('wrap-cost-vs-income');
            return;
        }
        var ctx = getCanvas('chart-cost-vs-income');
        if (!ctx) { return; }

        charts['cost-vs-income'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label:           'Ingresos',
                        data:            data.income,
                        backgroundColor: '#00ba88',
                        borderRadius:    4,
                        borderSkipped:   false,
                    },
                    {
                        label:           'Costos',
                        data:            data.costs,
                        backgroundColor: '#f0b849',
                        borderRadius:    4,
                        borderSkipped:   false,
                    },
                ],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + formatMoney(ctx.raw);
                            },
                        },
                    },
                },
                scales: {
                    x: { ticks: { font: { size: 11 } } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) { return formatMoney(v); },
                        },
                    },
                },
            },
        });
    }

    // ── UI HELPERS ───────────────────────────────────────────────────

    function setLoading(on) {
        $('#aura-dash-loader').toggle(on);
        $('#aura-dash-refresh').prop('disabled', on);
    }

    function showNotice(msg, type) {
        var $el = $('#aura-dash-notice');
        $el.text(msg)
           .removeClass('is-success is-error')
           .addClass(type === 'error' ? 'is-error' : 'is-success')
           .show();
        setTimeout(function () { $el.fadeOut(); }, 5000);
    }

    function showLastUpdate() {
        var now = new Date();
        var hh  = String(now.getHours()).padStart(2, '0');
        var mm  = String(now.getMinutes()).padStart(2, '0');
        var ss  = String(now.getSeconds()).padStart(2, '0');
        $('#aura-dash-last-update').text('Actualizado: ' + hh + ':' + mm + ':' + ss).show();
    }

    function formatNumber(n) {
        return parseInt(n, 10).toLocaleString('es-CO');
    }

    function formatMoney(n) {
        var num = parseFloat(n) || 0;
        return num.toLocaleString('es-CO', {
            style:                 'decimal',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
    }

    // ── ARRANQUE ─────────────────────────────────────────────────────
    $(document).ready(init);

})(jQuery);
