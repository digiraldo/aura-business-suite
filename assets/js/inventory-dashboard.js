/**
 * Aura Business Suite — Inventory Dashboard JS
 * FASE 4: KPIs async, ApexCharts (donut + bar), calendario, lista crítica
 */
/* global jQuery, ApexCharts, auraInventoryDashboard */
(function ($) {
    'use strict';

    var InventoryDashboard = {

        cfg: null,
        statusChartInst: null,
        costChartInst:   null,
        currentPeriod:   'month',

        // ── Init ────────────────────────────────────────────
        init: function () {
            if (typeof auraInventoryDashboard === 'undefined') {
                return;
            }
            this.cfg = auraInventoryDashboard;

            this.loadStatusChart();
            this.loadCostChart(this.currentPeriod);
            this.loadCalendar();
            this.loadCriticalList();
            this.bindPeriodTabs();
        },

        // ── Period tab buttons ───────────────────────────────
        bindPeriodTabs: function () {
            var self = this;
            $(document).on('click', '.aura-dash-period-tabs button', function () {
                var period = $(this).data('period');
                if (period === self.currentPeriod) {
                    return;
                }
                self.currentPeriod = period;
                $(this).closest('.aura-dash-period-tabs').find('button').removeClass('active');
                $(this).addClass('active');
                self.loadCostChart(period);
            });
        },

        // ── AJAX helper ─────────────────────────────────────
        doAjax: function (action, data, callback) {
            $.post(this.cfg.ajaxurl, $.extend({ action: action, nonce: this.cfg.nonce }, data), callback);
        },

        // ── Status Chart (donut) ─────────────────────────────
        loadStatusChart: function () {
            var self   = this;
            var wrap   = $('#aura-dash-status-chart');
            var spinner = wrap.closest('.aura-dash-chart-card').find('.aura-dash-chart-spinner');
            spinner.show();

            self.doAjax('aura_inventory_dashboard_status_chart', {}, function (res) {
                spinner.hide();
                if (!res.success) {
                    wrap.html('<p class="aura-dash-empty">' + (res.data || 'Error') + '</p>');
                    return;
                }
                var d = res.data;
                if (!d.series || !d.series.length) {
                    wrap.html('<p class="aura-dash-empty">' + (self.cfg.txt.no_data||'Sin datos') + '</p>');
                    return;
                }

                if (self.statusChartInst) {
                    self.statusChartInst.destroy();
                }

                var options = {
                    chart:   { type: 'donut', height: 280, animations: { enabled: true } },
                    series:  d.series,
                    labels:  d.labels,
                    colors:  d.colors,
                    legend:  { position: 'bottom', fontSize: '12px' },
                    dataLabels: { enabled: false },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '65%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: self.cfg.txt.total || 'Total',
                                        fontSize: '14px',
                                        fontWeight: 600
                                    }
                                }
                            }
                        }
                    },
                    tooltip: {
                        y: { formatter: function (v) { return v + ' ' + (self.cfg.txt.units || 'equipos'); } }
                    },
                    responsive: [{
                        breakpoint: 480,
                        options: { chart: { height: 220 }, legend: { position: 'bottom' } }
                    }]
                };

                self.statusChartInst = new ApexCharts(wrap[0], options);
                self.statusChartInst.render();
            });
        },

        // ── Cost Chart (bars) ────────────────────────────────
        loadCostChart: function (period) {
            var self    = this;
            var wrap    = $('#aura-dash-cost-chart');
            var spinner = wrap.closest('.aura-dash-chart-card').find('.aura-dash-chart-spinner');
            spinner.show();

            self.doAjax('aura_inventory_dashboard_cost_chart', { period: period }, function (res) {
                spinner.hide();
                if (!res.success) {
                    wrap.html('<p class="aura-dash-empty">' + (res.data || 'Error') + '</p>');
                    return;
                }
                var d = res.data;
                if (!d.categories || !d.categories.length) {
                    wrap.html('<p class="aura-dash-empty">' + (self.cfg.txt.no_data || 'Sin datos') + '</p>');
                    return;
                }

                var currency = d.currency || self.cfg.currency || '';

                if (self.costChartInst) {
                    self.costChartInst.destroy();
                }

                var options = {
                    chart:  { type: 'bar', height: 280, toolbar: { show: false } },
                    series: [{
                        name: self.cfg.txt.cost_title || 'Costo total',
                        data: d.series
                    }],
                    xaxis:  {
                        categories: d.categories,
                        labels:     { style: { fontSize: '11px' } }
                    },
                    yaxis:  {
                        labels: {
                            formatter: function (v) {
                                return currency + ' ' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
                            }
                        }
                    },
                    colors: d.colors || ['#2271b1'],
                    dataLabels: {
                        enabled: true,
                        formatter: function (v) {
                            return currency + ' ' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
                        },
                        style: { fontSize: '11px' }
                    },
                    plotOptions: {
                        bar: { borderRadius: 4, distributed: true }
                    },
                    tooltip: {
                        y: {
                            formatter: function (v, opts) {
                                var cnt = d.counts && d.counts[opts.dataPointIndex] ? ' (' + d.counts[opts.dataPointIndex] + ' ' + (self.cfg.txt.events || 'registros') + ')' : '';
                                return currency + ' ' + Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + cnt;
                            }
                        }
                    },
                    legend: { show: false },
                    responsive: [{
                        breakpoint: 480,
                        options: { chart: { height: 200 } }
                    }]
                };

                self.costChartInst = new ApexCharts(wrap[0], options);
                self.costChartInst.render();
            });
        },

        // ── Calendar ──────────────────────────────────────────
        loadCalendar: function () {
            var self = this;
            var wrap  = $('#aura-dash-calendar-list');
            wrap.html('<p class="aura-dash-empty"><span class="aura-dash-chart-spinner"></span></p>');

            self.doAjax('aura_inventory_dashboard_calendar', {}, function (res) {
                if (!res.success) {
                    wrap.html('<p class="aura-dash-empty">' + (res.data || 'Error') + '</p>');
                    return;
                }
                var items = res.data;
                if (!items || !items.length) {
                    wrap.html('<p class="aura-dash-empty">' + (self.cfg.txt.cal_empty || 'Sin eventos próximos.') + '</p>');
                    return;
                }

                var html = '<ul class="aura-dash-calendar-list">';
                $.each(items, function (i, item) {
                    html += self.renderCalendarItem(item);
                });
                html += '</ul>';
                wrap.html(html);
            });
        },

        renderCalendarItem: function (item) {
            var t = this.cfg.txt || {};
            var levelLabels = {
                overdue: t.overdue || 'Vencido',
                urgent:  t.urgent  || 'Urgente',
                warning: t.warning || 'Próximo',
                ok:      t.ok      || 'Planificado'
            };
            var label     = levelLabels[item.level] || item.level;
            var actionHtml = item.maint_url
                ? '<span class="aura-dash-cal-action"><a href="' + item.maint_url + '">' + (t.register_maint || 'Registrar') + '</a></span>'
                : '';

            return '<li class="aura-dash-calendar-item ' + item.level + '">' +
                '<span class="aura-dash-cal-badge">' + label + '</span>' +
                '<span class="aura-dash-cal-name" title="' + $('<div>').text(item.name).html() + '">' + $('<div>').text(item.name).html() + '</span>' +
                '<span class="aura-dash-cal-date">' + item.next_maintenance_date + '</span>' +
                actionHtml +
                '</li>';
        },

        // ── Critical List ─────────────────────────────────────
        loadCriticalList: function () {
            var self = this;
            var wrap  = $('#aura-dash-critical');
            wrap.html('<p class="aura-dash-empty"><span class="aura-dash-chart-spinner"></span></p>');

            self.doAjax('aura_inventory_dashboard_critical_list', {}, function (res) {
                if (!res.success) {
                    wrap.html('<p class="aura-dash-empty">' + (res.data || 'Error') + '</p>');
                    return;
                }
                var d     = res.data;
                var empty = (!d.overdue_maint || !d.overdue_maint.length) &&
                            (!d.in_repair     || !d.in_repair.length)     &&
                            (!d.overdue_loans || !d.overdue_loans.length);

                if (empty) {
                    wrap.html('<p class="aura-dash-empty">' + (self.cfg.txt.no_critical || '✅ Sin equipos críticos.') + '</p>');
                    return;
                }

                var html = '<div class="aura-dash-critical-wrap">';

                if (d.overdue_maint && d.overdue_maint.length) {
                    html += self.renderCriticalSection(d.overdue_maint, 'overdue_maint');
                }
                if (d.in_repair && d.in_repair.length) {
                    html += self.renderCriticalSection(d.in_repair, 'in_repair');
                }
                if (d.overdue_loans && d.overdue_loans.length) {
                    html += self.renderCriticalSection(d.overdue_loans, 'overdue_loans');
                }

                html += '</div>';
                wrap.html(html);
            });
        },

        renderCriticalSection: function (items, type) {
            var self     = this;
            var t2 = this.cfg.txt || {};
            var titles   = {
                overdue_maint: t2.crit_overdue || 'Mantenimiento vencido',
                in_repair:     t2.crit_repair  || 'En reparación',
                overdue_loans: t2.crit_loan    || 'Préstamo vencido'
            };
            var badgeClass = (type === 'overdue_loans') ? 'orange' : '';
            var title  = titles[type] || type;
            var count  = items.length;

            var html = '<div class="aura-dash-critical-section">' +
                '<h4>' + title + ' <span class="aura-dash-critical-count ' + badgeClass + '">' + count + '</span></h4>' +
                '<ul class="aura-dash-critical-list-ul">';

            $.each(items, function (i, item) {
                var editUrl = (self.cfg.edit_url || '').replace('{id}', item.equipment_id || item.id || '');
                var sub     = '';
                if (item.days_overdue) {
                    sub = '<span class="aura-dash-sub">+' + item.days_overdue + 'd</span>';
                } else if (item.borrower) {
                    sub = '<span class="aura-dash-sub">' + $('<div>').text(item.borrower).html() + '</span>';
                }
                html += '<li>' +
                    '<a href="' + editUrl + '">' + $('<div>').text(item.name).html() + '</a>' +
                    sub +
                    '</li>';
            });

            html += '</ul></div>';
            return html;
        }
    };

    // Boot
    $(function () {
        InventoryDashboard.init();
    });

}(jQuery));
