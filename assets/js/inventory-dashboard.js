/**
 * Aura Business Suite — Inventory Dashboard JS
 * FASE 4 + Design System: ApexCharts reactivo a Modo Claro/Oscuro, calendario, lista crítica y tooltips.
 */
/* global jQuery, ApexCharts, auraInventoryDashboard */
(function ($) {
    'use strict';

    function __( s, domain ) {
        return ( window.wp && wp.i18n && typeof wp.i18n.__ === 'function' ) ? wp.i18n.__( s, domain || 'aura-suite' ) : s;
    }
    function sprintf( fmt ) {
        if ( window.wp && wp.i18n && typeof wp.i18n.sprintf === 'function' ) {
            return wp.i18n.sprintf.apply( wp.i18n, arguments );
        }
        var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
        return String( fmt ).replace( /%[sd]/g, function () { return args[ i++ ]; } );
    }

    var InventoryDashboard = {

        cfg: null,
        statusChartInst: null,
        costChartInst:   null,
        statusChartData: null,
        costChartData:   null,
        currentPeriod:   'month',

        isDarkMode: function () {
            var html = document.documentElement;
            var body = document.body;
            return (
                (html && (
                    html.getAttribute('data-theme') === 'dark' ||
                    html.getAttribute('data-wp-dark-mode-scheme') === 'dark' ||
                    html.classList.contains('wp-dark-mode-active') ||
                    html.classList.contains('aura-dark-mode')
                )) ||
                (body && (
                    body.getAttribute('data-theme') === 'dark' ||
                    body.classList.contains('aura-dark-mode') ||
                    body.classList.contains('wp-dark-mode-active') ||
                    body.classList.contains('dark-mode') ||
                    body.classList.contains('admin-color-midnight') ||
                    body.classList.contains('admin-color-coffee')
                ))
            );
        },

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
            this.observeThemeChanges();
        },

        observeThemeChanges: function () {
            var self = this;
            if (typeof MutationObserver === 'undefined') return;
            var lastDark = self.isDarkMode();
            var checkTheme = function () {
                var nowDark = self.isDarkMode();
                if (nowDark !== lastDark) {
                    lastDark = nowDark;
                    self.refreshChartTheme();
                }
            };
            var obs = new MutationObserver(checkTheme);
            if (document.documentElement) {
                obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-wp-dark-mode-scheme'] });
            }
            if (document.body) {
                obs.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-wp-dark-mode-scheme'] });
            }
        },

        refreshChartTheme: function () {
            var dark = this.isDarkMode();
            var txtColor = dark ? '#e2e8f0' : '#334155';
            var gridColor = dark ? 'rgba(255,255,255,0.08)' : 'rgba(148,163,184,0.22)';
            var themeOpt = {
                theme: { mode: dark ? 'dark' : 'light' },
                chart: { foreColor: txtColor, background: 'transparent' }
            };
            if (this.statusChartInst) {
                this.statusChartInst.updateOptions($.extend(true, {}, themeOpt, {
                    legend: { labels: { colors: txtColor } }
                }), false, true);
            }
            if (this.costChartInst) {
                this.costChartInst.updateOptions($.extend(true, {}, themeOpt, {
                    grid: { borderColor: gridColor },
                    xaxis: { labels: { style: { colors: txtColor } } },
                    yaxis: { labels: { style: { colors: txtColor } } }
                }), false, true);
            }
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
                $(this).closest('.aura-dash-period-tabs').find('button')
                    .removeClass('active btn-indigo')
                    .addClass('btn-glass');
                $(this).removeClass('btn-glass').addClass('active btn-indigo');
                self.loadCostChart(period);
            });
        },

        // ── AJAX helper ─────────────────────────────────────
        doAjax: function (action, data, callback) {
            $.post(this.cfg.ajaxurl, $.extend({ action: action, nonce: this.cfg.nonce, _t: Date.now() }, data), callback);
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
                    wrap.html('<p class="aura-dash-empty">' + (res.data || __('Error', 'aura-suite')) + '</p>');
                    return;
                }
                var d = res.data;
                if (!d.series || !d.series.length) {
                    wrap.html('<p class="aura-dash-empty">' + (self.cfg.txt.no_data || __('Sin datos', 'aura-suite')) + '</p>');
                    return;
                }

                if (self.statusChartInst) {
                    self.statusChartInst.destroy();
                }

                var dark = self.isDarkMode();
                var txtColor = dark ? '#e2e8f0' : '#334155';
                var options = {
                    chart:   { type: 'donut', height: 280, animations: { enabled: true }, background: 'transparent', foreColor: txtColor },
                    theme:   { mode: dark ? 'dark' : 'light' },
                    series:  d.series,
                    labels:  d.labels,
                    colors:  d.colors,
                    legend:  { position: 'bottom', fontSize: '12px', labels: { colors: txtColor } },
                    dataLabels: { enabled: false },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '65%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: self.cfg.txt.total || __('Total', 'aura-suite'),
                                        color: txtColor,
                                        fontSize: '14px',
                                        fontWeight: 600
                                    }
                                }
                            }
                        }
                    },
                    tooltip: {
                        y: { formatter: function (v) { return v + ' ' + (self.cfg.txt.units || __('equipos', 'aura-suite')); } }
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
                    wrap.html('<p class="aura-dash-empty">' + (res.data || __('Error', 'aura-suite')) + '</p>');
                    return;
                }
                var d = res.data;
                if (!d.categories || !d.categories.length) {
                    wrap.html('<p class="aura-dash-empty">' + (self.cfg.txt.no_data || __('Sin datos', 'aura-suite')) + '</p>');
                    return;
                }

                var currency = d.currency || self.cfg.currency || '';

                if (self.costChartInst) {
                    self.costChartInst.destroy();
                }

                var dark = self.isDarkMode();
                var txtColor = dark ? '#e2e8f0' : '#334155';
                var gridColor = dark ? 'rgba(255,255,255,0.08)' : 'rgba(148,163,184,0.22)';
                var options = {
                    chart:  { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent', foreColor: txtColor },
                    theme:  { mode: dark ? 'dark' : 'light' },
                    grid:   { borderColor: gridColor },
                    series: [{
                        name: self.cfg.txt.cost_title || __('Costo total', 'aura-suite'),
                        data: d.series
                    }],
                    xaxis:  {
                        categories: d.categories,
                        labels:     { style: { fontSize: '11px', colors: txtColor } }
                    },
                    yaxis:  {
                        labels: {
                            style: { colors: txtColor },
                            formatter: function (v) {
                                return currency + ' ' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
                            }
                        }
                    },
                    colors: d.colors || ['#4f46e5'],
                    dataLabels: {
                        enabled: true,
                        formatter: function (v) {
                            return currency + ' ' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
                        },
                        style: { fontSize: '11px' }
                    },
                    plotOptions: {
                        bar: { borderRadius: 6, distributed: true }
                    },
                    tooltip: {
                        y: {
                            formatter: function (v, opts) {
                                var cnt = d.counts && d.counts[opts.dataPointIndex] ? ' (' + d.counts[opts.dataPointIndex] + ' ' + (self.cfg.txt.events || __('registros', 'aura-suite')) + ')' : '';
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
            $('#aura-dash-cal-spinner').show();

            self.doAjax('aura_inventory_dashboard_calendar', {}, function (res) {
                $('#aura-dash-cal-spinner').hide();
                if (!res.success) {
                    wrap.html('<p class="aura-dash-empty">' + (res.data || __('Error', 'aura-suite')) + '</p>');
                    return;
                }
                var items = res.data;
                if (!items || !items.length) {
                    wrap.html('<div class="empty-card"><span class="empty-icon">📅</span><p>' + (self.cfg.txt.cal_empty || __('Sin eventos próximos.', 'aura-suite')) + '</p></div>');
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
                overdue: t.overdue || __('Vencido', 'aura-suite'),
                urgent:  t.urgent  || __('Urgente', 'aura-suite'),
                warning: t.warning || __('Próximo', 'aura-suite'),
                ok:      t.ok      || __('Planificado', 'aura-suite')
            };
            var badgeMap = {
                overdue: 'badge-red',
                urgent:  'badge-amber',
                warning: 'badge-yellow',
                ok:      'badge-emerald'
            };
            var label  = levelLabels[item.level] || item.level;
            var bClass = badgeMap[item.level] || 'badge-slate';
            var escapedName = $('<div>').text(item.name || '').html();
            var actionHtml = item.maint_url
                ? '<a href="' + item.maint_url + '" class="btn btn-glass btn-sm btn-lift" data-tooltip="' + __('Ir a registrar mantenimiento', 'aura-suite') + '">' + (t.register_maint || __('Registrar', 'aura-suite')) + '</a>'
                : '';

            return '<li class="aura-dash-calendar-item ' + item.level + '">' +
                '<span class="badge ' + bClass + '" data-tooltip="' + sprintf(__('Prioridad: %s', 'aura-suite'), label) + '">' + label + '</span>' +
                '<span class="aura-dash-cal-name" data-tooltip="' + escapedName + '">' + escapedName + '</span>' +
                '<span class="aura-dash-cal-date">📅 ' + (item.next_maintenance_date || '—') + '</span>' +
                actionHtml +
                '</li>';
        },

        // ── Critical List ─────────────────────────────────────
        loadCriticalList: function () {
            var self = this;
            var wrap  = $('#aura-dash-critical');
            $('#aura-dash-crit-spinner').show();

            self.doAjax('aura_inventory_dashboard_critical_list', {}, function (res) {
                $('#aura-dash-crit-spinner').hide();
                if (!res.success) {
                    wrap.html('<p class="aura-dash-empty">' + (res.data || __('Error', 'aura-suite')) + '</p>');
                    return;
                }
                var d     = res.data;
                var empty = (!d.overdue_maint || !d.overdue_maint.length) &&
                            (!d.in_repair     || !d.in_repair.length)     &&
                            (!d.overdue_loans || !d.overdue_loans.length);

                if (empty) {
                    wrap.html('<div class="empty-card"><span class="empty-icon">✅</span><p>' + (self.cfg.txt.no_critical || __('Sin equipos críticos actualmente.', 'aura-suite')) + '</p></div>');
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
                overdue_maint: t2.crit_overdue || __('Mantenimiento vencido', 'aura-suite'),
                in_repair:     t2.crit_repair  || __('En reparación', 'aura-suite'),
                overdue_loans: t2.crit_loan    || __('Préstamo vencido', 'aura-suite')
            };
            var badgeCls = (type === 'overdue_loans') ? 'badge-amber' : 'badge-red';
            var title    = titles[type] || type;
            var count    = items.length;

            var html = '<div class="aura-dash-critical-section">' +
                '<h4>' + title + ' <span class="badge ' + badgeCls + '">' + count + '</span></h4>' +
                '<ul class="aura-dash-critical-list-ul">';

            $.each(items, function (i, item) {
                var editUrl = (self.cfg.edit_url || '').replace('{id}', item.equipment_id || item.id || '');
                var escapedName = $('<div>').text(item.name || '').html();
                var sub     = '';
                if (item.days_overdue) {
                    sub = '<span class="badge badge-red">+' + item.days_overdue + 'd</span>';
                } else if (item.borrower) {
                    sub = '<span class="aura-dash-sub">👤 ' + $('<div>').text(item.borrower).html() + '</span>';
                }
                html += '<li>' +
                    '<a href="' + editUrl + '" data-tooltip="' + sprintf(__('Ver o editar equipo: %s', 'aura-suite'), escapedName) + '">' + escapedName + '</a>' +
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
