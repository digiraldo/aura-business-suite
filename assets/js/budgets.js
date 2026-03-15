/**
 * Budgets JS – Fase 5, Item 5.1
 * Requiere: jQuery, ApexCharts
 */
/* global auraBudgets, ApexCharts, $ */
(function ($) {
    'use strict';

    var state = {
        budgets    : [],
        editingId  : null,
        detailId   : null,
        donutChart : null,
        histChart  : null,
    };

    // ── Formatear moneda ──────────────────────────────────────────────
    function fmt(n) {
        return '$' + parseFloat(n || 0).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtPct(n) {
        return parseFloat(n || 0).toFixed(1) + '%';
    }

    // ── Cargar y renderizar lista ─────────────────────────────────────
    function loadBudgets() {
        $('#aura-budgets-loading').show();
        $('#aura-budgets-list').hide();
        $('#aura-budgets-empty').hide();

        $.post(auraBudgets.ajaxurl, {
            action: 'aura_get_budgets',
            nonce : auraBudgets.nonce,
        }).done(function (res) {
            $('#aura-budgets-loading').hide();
            if (!res.success) return;

            state.budgets = res.data.budgets || [];
            renderSummaryBar(state.budgets);
            renderBudgetsList(state.budgets);
        }).fail(function () {
            $('#aura-budgets-loading').hide();
        });
    }

    // ── Barra resumen ─────────────────────────────────────────────────
    function renderSummaryBar(budgets) {
        var total_budget   = 0, total_exec = 0, overrun = 0, critical = 0, ok = 0;
        budgets.forEach(function (b) {
            total_budget += parseFloat(b.budget_amount);
            total_exec   += parseFloat(b.executed);
            if (b.status === 'overrun')  overrun++;
            else if (b.status === 'critical' || b.status === 'warning') critical++;
            else ok++;
        });

        var html = '<div class="aura-summary-cards">'
            + summaryCard(fmt(total_budget), auraBudgets.txt.total_budget,   '#2271b1')
            + summaryCard(fmt(total_exec),   auraBudgets.txt.total_executed,  '#00a32a')
            + summaryCard(String(budgets.length), auraBudgets.txt.total_budgets, '#50575e')
            + summaryCard(String(overrun),   auraBudgets.txt.overrun_count,   '#d63638')
            + summaryCard(String(critical),  auraBudgets.txt.critical_count,  '#dba617')
            + summaryCard(String(ok),        auraBudgets.txt.ok_count,        '#00a32a')
            + '</div>';

        $('#aura-budget-summary-bar').html(html);
    }

    function summaryCard(val, label, color) {
        return '<div class="aura-summary-card" style="border-top:3px solid' + color + '">'
            + '<span class="aura-summary-val" style="color:' + color + '">' + val + '</span>'
            + '<span class="aura-summary-label">' + label + '</span>'
            + '</div>';
    }

    // ── Renderizar icono de categoría ────────────────────────────────
    function catIcon(b) {
        var icon  = b.category_icon  || 'dashicons-category';
        var color = b.category_color || '#607d8b';
        return '<span class="dashicons ' + icon + ' aura-cat-icon" style="color:' + color + '"></span>';
    }

    // ── Renderizar tabla ──────────────────────────────────────────────
    function renderBudgetsList(budgets) {
        var filterPeriod = $('#aura-filter-period').val();
        var filterStatus = $('#aura-filter-status').val();
        var filterArea   = $('#aura-filter-area').val();

        var filtered = budgets.filter(function (b) {
            if (filterPeriod && b.period_type !== filterPeriod) return false;
            if (filterStatus && b.status    !== filterStatus)   return false;
            if (filterArea   && String(b.area_id) !== filterArea) return false;
            return true;
        });

        if (!filtered.length) {
            $('#aura-budgets-list').hide();
            $('#aura-budgets-empty').show();
            return;
        }

        // Agrupar por área
        var groups = {};
        var groupOrder = [];
        filtered.forEach(function (b) {
            var key = b.area_id ? String(b.area_id) : '__none__';
            if (!groups[key]) {
                groups[key] = { area_id: b.area_id, area_name: b.area_name, area_color: b.area_color, items: [] };
                groupOrder.push(key);
            }
            groups[key].items.push(b);
        });

        var html = '<table class="widefat aura-budgets-table">'
            + '<thead><tr>'
            + '<th>' + auraBudgets.txt.h_category + '</th>'
            + '<th>' + auraBudgets.txt.h_period + '</th>'
            + '<th>' + auraBudgets.txt.h_budget + '</th>'
            + '<th>' + auraBudgets.txt.h_executed + '</th>'
            + '<th>' + auraBudgets.txt.h_available + '</th>'
            + '<th>' + auraBudgets.txt.h_pct + '</th>'
            + '<th>' + auraBudgets.txt.h_progress + '</th>'
            + '<th>' + auraBudgets.txt.h_actions + '</th>'
            + '</tr></thead><tbody>';

        groupOrder.forEach(function (key) {
            var g = groups[key];

            // Calcular subtotales del grupo
            var subtotalBudget = 0, subtotalExecuted = 0, subtotalAvailable = 0, subtotalOverrun = 0;
            g.items.forEach(function (b) {
                subtotalBudget    += parseFloat(b.budget_amount || 0);
                subtotalExecuted  += parseFloat(b.executed      || 0);
                subtotalAvailable += Math.max(0, parseFloat(b.available || 0));
                subtotalOverrun   += Math.max(0, parseFloat(b.overrun   || 0));
            });
            var subtotalPct      = subtotalBudget > 0 ? Math.round((subtotalExecuted / subtotalBudget) * 100) : 0;
            var subtotalBarColor = subtotalPct > 100 ? '#d63638' : (subtotalPct >= 90 ? '#f97316' : (subtotalPct >= 70 ? '#dba617' : '#00a32a'));

            // Badge de área en la cabecera del grupo
            var areaLabel;
            if (g.area_name) {
                var aColor = g.area_color || '#607d8b';
                areaLabel = '<span style="display:inline-flex;align-items:center;gap:6px;">'
                    + '<span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:' + escHtml(aColor) + ';flex-shrink:0;"></span>'
                    + '<strong>' + escHtml(g.area_name) + '</strong>'
                    + ' <em style="color:#72777c;font-size:12px;font-weight:400;">(' + g.items.length + ')</em>'
                    + '</span>';
            } else {
                areaLabel = '<em style="color:#aaa">' + (auraBudgets.txt.no_area || 'General') + '</em>';
            }

            // Fila cabecera del grupo (colapsable)
            html += '<tr class="aura-area-group-header" style="background:#f0f6fc;cursor:pointer;">'
                + '<td colspan="2" style="padding:8px 10px;">'
                + '<button type="button" class="aura-group-toggle button-link" data-group="' + escHtml(key) + '" aria-expanded="true" style="margin-right:6px;font-size:14px;border:none;background:none;cursor:pointer;">'
                + (auraBudgets.txt.collapse_area || '▲') + '</button>'
                + areaLabel
                + '</td>'
                + '<td style="padding:8px 10px;"><strong>' + fmt(subtotalBudget) + '</strong></td>'
                + '<td style="padding:8px 10px;">' + fmt(subtotalExecuted) + '</td>'
                + '<td style="padding:8px 10px;">' + (subtotalOverrun > 0 ? '<span class="aura-overrun-badge">-' + fmt(subtotalOverrun) + '</span>' : fmt(subtotalAvailable)) + '</td>'
                + '<td style="padding:8px 10px;"><strong style="color:' + subtotalBarColor + '">' + fmtPct(subtotalPct) + '</strong></td>'
                + '<td style="padding:8px 10px;"><div class="aura-prog-bar"><div class="aura-prog-fill" style="width:' + Math.min(subtotalPct, 100) + '%;background:' + subtotalBarColor + '"></div></div></td>'
                + '<td style="padding:8px 10px;color:#72777c;font-size:12px;">' + (auraBudgets.txt.area_subtotal || 'Subtotal') + '</td>'
                + '</tr>';

            // Filas individuales del grupo
            g.items.forEach(function (b) {
                var pct       = parseFloat(b.percentage);
                var barColor  = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
                var statusCls = 'aura-status-' + b.status;

                html += '<tr class="' + statusCls + ' aura-group-row" data-group-member="' + escHtml(key) + '" data-id="' + b.id + '">'
                    + '<td>' + catIcon(b) + ' ' + escHtml(b.category_name || '—') + '</td>'
                    + '<td><span class="aura-period-badge">' + periodLabel(b.period_type) + '</span></td>'
                    + '<td><strong>' + fmt(b.budget_amount) + '</strong></td>'
                    + '<td>' + fmt(b.executed) + '</td>'
                    + '<td>' + (parseFloat(b.overrun) > 0 ? '<span class="aura-overrun-badge">-' + fmt(b.overrun) + '</span>' : fmt(b.available)) + '</td>'
                    + '<td><strong style="color:' + barColor + '">' + fmtPct(pct) + '</strong></td>'
                    + '<td><div class="aura-prog-bar"><div class="aura-prog-fill" style="width:' + Math.min(pct, 100) + '%;background:' + barColor + '">' + (pct > 100 ? '<div class="aura-overrun-stripe"></div>' : '') + '</div></div></td>'
                    + '<td class="aura-row-actions">'
                    + '<button class="button button-small aura-detail-btn" data-id="' + b.id + '" title="' + auraBudgets.txt.detail + '"><span class="dashicons dashicons-visibility"></span></button> '
                    + '<button class="button button-small aura-edit-btn" data-id="' + b.id + '" title="' + auraBudgets.txt.edit + '"><span class="dashicons dashicons-edit"></span></button> '
                    + '<button class="button button-small aura-delete-btn" data-id="' + b.id + '" title="' + auraBudgets.txt.delete + '" style="color:#d63638"><span class="dashicons dashicons-trash"></span></button>'
                    + '</td></tr>';
            });
        });

        html += '</tbody></table>';
        $('#aura-budgets-list').html(html).show();
        $('#aura-budgets-empty').hide();

        // Toggle collapse/expand de grupos
        $(document).off('click.auraGroupToggle').on('click.auraGroupToggle', '.aura-group-toggle', function (e) {
            e.stopPropagation();
            var grp      = $(this).data('group');
            var $rows    = $('[data-group-member="' + grp + '"]');
            var expanded = $(this).attr('aria-expanded') === 'true';
            if (expanded) {
                $rows.hide();
                $(this).attr('aria-expanded', 'false').text(auraBudgets.txt.expand_area || '▼');
            } else {
                $rows.show();
                $(this).attr('aria-expanded', 'true').text(auraBudgets.txt.collapse_area || '▲');
            }
        });
    }

    function periodLabel(type) {
        var map = { monthly: auraBudgets.txt.monthly, quarterly: auraBudgets.txt.quarterly, semestral: auraBudgets.txt.semestral, yearly: auraBudgets.txt.yearly };
        return map[type] || type;
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    // ── Filtros ────────────────────────────────────────────────────────
    $('#aura-filter-period, #aura-filter-status, #aura-filter-area').on('change', function () {
        renderBudgetsList(state.budgets);
    });

    $('#aura-filters-clear').on('click', function () {
        $('#aura-filter-period, #aura-filter-status').val('');
        var $areaFilter = $('#aura-filter-area');
        if (!$areaFilter.prop('disabled')) $areaFilter.val('');
        renderBudgetsList(state.budgets);
    });

    // ── Abrir modal crear ──────────────────────────────────────────────
    $('#aura-new-budget-btn').on('click', function () {
        openBudgetModal(null);
    });

    function openBudgetModal(id) {
        state.editingId = id;
        var $form  = $('#aura-budget-form');
        var $modal = $('#aura-budget-modal');

        $form[0].reset();
        $('#budget_id').val(0);
        $('#aura-budget-modal-title').text(id ? auraBudgets.txt.edit_title : auraBudgets.txt.new_title);
        $('#aura-budget-form-error').hide();

        // Defaults para fecha (mes actual)
        if (!id) {
            var now      = new Date();
            var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            var lastDay  = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            $('#budget_start_date').val(firstDay.toISOString().slice(0, 10));
            $('#budget_end_date').val(lastDay.toISOString().slice(0, 10));
        }

        if (id) {
            var b = state.budgets.find(function (x) { return parseInt(x.id) === parseInt(id); });
            if (b) {
                $('#budget_id').val(b.id);
                $('#budget_category_id').val(b.category_id);
                // Área / Programa (Fase 8.1)
                if ($('#budget_area_id').length) {
                    $('#budget_area_id').val(b.area_id || '');
                }
                $('#budget_amount').val(b.budget_amount);
                $('input[name="period_type"][value="' + b.period_type + '"]').prop('checked', true).closest('.aura-radio-pill').addClass('selected').siblings().removeClass('selected');
                $('#budget_start_date').val(b.start_date);
                $('#budget_end_date').val(b.end_date);
                $('#alert_threshold').val(b.alert_threshold || 80);
                $('#alert_on_exceed').prop('checked', parseInt(b.alert_on_exceed) === 1);
                $('#notify_creator').prop('checked', parseInt(b.notify_creator) === 1);
                $('#notify_admins').prop('checked', parseInt(b.notify_admins) === 1);
                if (b.notify_emails) {
                    $('#notify_extra_toggle').prop('checked', true);
                    $('#notify_emails').val(b.notify_emails).show();
                }
            }
        }

        $modal.show();
        $('#budget_category_id').focus();
    }

    // Cerrar modal
    $('#aura-budget-modal-close, #aura-budget-cancel').on('click', function () {
        $('#aura-budget-modal').hide();
    });

    $(document).on('click', '.aura-modal-overlay', function (e) {
        if ($(e.target).is('.aura-modal-overlay')) $(this).hide();
    });

    // Toggle emails adicionales
    $('#notify_extra_toggle').on('change', function () {
        $('#notify_emails').toggle($(this).is(':checked'));
    });

    // Radios de período
    $(document).on('change', 'input[name="period_type"]', function () {
        $('input[name="period_type"]').closest('.aura-radio-pill').removeClass('selected');
        $(this).closest('.aura-radio-pill').addClass('selected');
        autoSetEndDate();
    });

    function autoSetEndDate() {
        var start = $('#budget_start_date').val();
        if (!start) return;
        var d    = new Date(start + 'T00:00:00');
        var type = $('input[name="period_type"]:checked').val();
        if (type === 'monthly') {
            d.setMonth(d.getMonth() + 1);
            d.setDate(0);
        } else if (type === 'quarterly') {
            d.setMonth(d.getMonth() + 3);
            d.setDate(0);
        } else if (type === 'semestral') {
            d.setMonth(d.getMonth() + 6);
            d.setDate(0);
        } else {
            d.setFullYear(d.getFullYear() + 1);
            d.setDate(d.getDate() - 1);
        }
        $('#budget_end_date').val(d.toISOString().slice(0, 10));
    }

    $('#budget_start_date').on('change', autoSetEndDate);

    // ── Guardar presupuesto ────────────────────────────────────────────
    $('#aura-budget-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#aura-budget-save').prop('disabled', true);
        $('#aura-budget-form-error').hide();

        var data     = $(this).serializeArray().reduce(function (acc, f) { acc[f.name] = f.value; return acc; }, {});
        data.action  = 'aura_save_budget';
        data.nonce   = auraBudgets.nonce;
        data.alert_on_exceed = $('#alert_on_exceed').is(':checked') ? 1 : 0;
        data.notify_creator  = $('#notify_creator').is(':checked')  ? 1 : 0;
        data.notify_admins   = $('#notify_admins').is(':checked')   ? 1 : 0;

        $.post(auraBudgets.ajaxurl, data).done(function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                $('#aura-budget-form-error').text(res.data.message || auraBudgets.txt.error_generic).show();
                return;
            }
            $('#aura-budget-modal').hide();
            loadBudgets();
            showNotice(res.data.action === 'created' ? auraBudgets.txt.created : auraBudgets.txt.updated, 'success');
        }).fail(function () {
            $btn.prop('disabled', false);
            $('#aura-budget-form-error').text(auraBudgets.txt.error_generic).show();
        });
    });

    // ── Acciones en tabla ──────────────────────────────────────────────
    $(document).on('click', '.aura-detail-btn', function () {
        openDetail($(this).data('id'));
    });

    $(document).on('click', '.aura-edit-btn', function () {
        openBudgetModal($(this).data('id'));
    });

    $(document).on('click', '.aura-delete-btn', function () {
        var id = $(this).data('id');
        if (!confirm(auraBudgets.txt.confirm_delete)) return;

        $.post(auraBudgets.ajaxurl, { action: 'aura_delete_budget', nonce: auraBudgets.nonce, id: id })
            .done(function (res) {
                if (!res.success) { alert(res.data.message || auraBudgets.txt.error_generic); return; }
                loadBudgets();
                showNotice(auraBudgets.txt.deleted, 'warning');
            });
    });

    // ── Modal detalle ──────────────────────────────────────────────────
    function openDetail(id) {
        state.detailId = id;
        $('#aura-detail-tx-body').html('<tr><td colspan="5" class="aura-loading">' + auraBudgets.txt.loading + '</td></tr>');
        $('#aura-detail-stats').html('');
        $('#aura-budget-detail-modal').show();
        resetDetailTabs();
        // Limpiar caché para este ID para forzar recarga si reabre
        delete breakdownLoaded[id];

        // Destruir gráficos previos
        if (state.donutChart) { state.donutChart.destroy(); state.donutChart = null; }
        if (state.histChart)  { state.histChart.destroy();  state.histChart  = null; }
        document.getElementById('aura-budget-donut-chart').innerHTML = '';
        document.getElementById('aura-budget-history-chart').innerHTML = '';

        $.post(auraBudgets.ajaxurl, { action: 'aura_get_budget_detail', nonce: auraBudgets.nonce, id: id })
            .done(function (res) {
                if (!res.success) return;
                var d = res.data;
                renderDetailStats(d.budget);
                renderDonutChart(d.budget);
                renderHistoryChart(d.history);
                renderTransactions(d.transactions);
                $('#aura-detail-title').text(d.budget.category_name || auraBudgets.txt.detail_title);
            });
    }

    function renderDetailStats(b) {
        var pct     = parseFloat(b.percentage);
        var color   = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
        var projPct = b.budget_amount > 0 ? Math.round((b.projection / b.budget_amount) * 100) : 0;

        var html = '<div class="aura-ds-grid">'
            + ds(auraBudgets.txt.budget_total,  fmt(b.budget_amount),  '#50575e')
            + ds(auraBudgets.txt.executed,       fmt(b.executed) + ' (' + fmtPct(pct) + ')', color)
            + ds(auraBudgets.txt.available,      parseFloat(b.overrun) > 0 ? '<span style="color:#d63638">-' + fmt(b.overrun) + '</span>' : fmt(b.available), '#00a32a')
            + ds(auraBudgets.txt.projection,     fmt(b.projection) + ' (' + projPct + '%)', projPct > 100 ? '#d63638' : '#2271b1')
            + '</div>';

        $('#aura-detail-stats').html(html);
    }

    function ds(label, val, color) {
        return '<div class="aura-ds"><span class="aura-ds-val" style="color:' + color + '">' + val + '</span><span class="aura-ds-lbl">' + label + '</span></div>';
    }

    // ── Gráfico donut ──────────────────────────────────────────────────
    function renderDonutChart(b) {
        var exec   = parseFloat(b.executed);
        var budget = parseFloat(b.budget_amount);
        var avail  = Math.max(0, budget - exec);
        var overr  = Math.max(0, exec - budget);
        var catColor = b.category_color || '#2271b1';

        var series = overr > 0
            ? [budget, overr]
            : [exec, avail];

        var labels = overr > 0
            ? [auraBudgets.txt.executed, auraBudgets.txt.overrun]
            : [auraBudgets.txt.executed, auraBudgets.txt.available];

        var colors = overr > 0
            ? [catColor, '#d63638']
            : [catColor, '#e5e7eb'];

        state.donutChart = new ApexCharts(document.getElementById('aura-budget-donut-chart'), {
            chart  : { type: 'donut', height: 220, toolbar: { show: false } },
            series : series,
            labels : labels,
            colors : colors,
            legend : { position: 'bottom', fontSize: '12px' },
            plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: fmtPct(b.percentage), formatter: function () { return fmtPct(b.percentage); } } } } } },
            dataLabels: { enabled: false },
            tooltip    : { y: { formatter: function (v) { return fmt(v); } } },
        });
        state.donutChart.render();
    }

    // ── Gráfico histórico ──────────────────────────────────────────────
    function renderHistoryChart(history) {
        if (!history || !history.length) {
            $('#aura-budget-history-chart').html('<p class="description">' + auraBudgets.txt.no_history + '</p>');
            return;
        }

        var cats = history.map(function (h) { return h.period; });
        state.histChart = new ApexCharts(document.getElementById('aura-budget-history-chart'), {
            chart  : { type: 'line', height: 200, toolbar: { show: false }, zoom: { enabled: false } },
            series : [
                { name: auraBudgets.txt.budget_total, data: history.map(function (h) { return parseFloat(h.budget); }), color: '#2271b1' },
                { name: auraBudgets.txt.executed,     data: history.map(function (h) { return parseFloat(h.executed); }), color: '#d63638' },
            ],
            xaxis  : { categories: cats, labels: { style: { fontSize: '11px' } } },
            yaxis  : { labels: { formatter: function (v) { return '$' + Math.round(v).toLocaleString('es'); } } },
            stroke : { width: [2, 2], dashArray: [0, 0] },
            markers: { size: 4 },
            legend : { position: 'bottom' },
            tooltip: { y: { formatter: function (v) { return fmt(v); } } },
        });
        state.histChart.render();
    }

    // ── Transacciones del período ──────────────────────────────────────
    function renderTransactions(txs) {
        if (!txs || !txs.length) {
            $('#aura-detail-tx-body').html('<tr><td colspan="5" class="description">' + auraBudgets.txt.no_transactions + '</td></tr>');
            return;
        }

        var html = '';
        var total = 0;
        txs.forEach(function (t) {
            total += parseFloat(t.amount);
            // Columna categoría: badge con punto de color
            var catCell;
            if (t.category_name) {
                var dotColor = t.category_color ? escHtml(t.category_color) : '#607d8b';
                catCell = '<span style="display:inline-flex;align-items:center;gap:5px;">'
                    + '<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:' + dotColor + ';flex-shrink:0;"></span>'
                    + escHtml(t.category_name)
                    + '</span>';
            } else {
                catCell = '<em style="color:#aaa">—</em>';
            }
            html += '<tr>'
                + '<td>' + escHtml(t.transaction_date) + '</td>'
                + '<td>' + escHtml(t.description) + '</td>'
                + '<td>' + catCell + '</td>'
                + '<td><strong>' + fmt(t.amount) + '</strong></td>'
                + '<td><span class="aura-tx-status aura-status-' + t.status + '">' + escHtml(t.status) + '</span></td>'
                + '</tr>';
        });

        html += '<tr class="aura-tx-total"><td colspan="3"><strong>' + auraBudgets.txt.total + '</strong></td><td colspan="2"><strong>' + fmt(total) + '</strong></td></tr>';
        $('#aura-detail-tx-body').html(html);
    }

    // Cerrar modal detalle
    $('#aura-detail-modal-close').on('click', function () {
        $('#aura-budget-detail-modal').hide();
    });

    // ── Ajustar presupuesto ────────────────────────────────────────────
    $('#aura-apply-adjust').on('click', function () {
        var val = parseFloat($('#adj_value').val());
        if (isNaN(val) || val === 0) { alert(auraBudgets.txt.adjust_invalid); return; }

        $.post(auraBudgets.ajaxurl, {
            action   : 'aura_adjust_budget',
            nonce    : auraBudgets.nonce,
            id       : state.detailId,
            adj_type : $('#adj_type').val(),
            adj_value: val,
        }).done(function (res) {
            if (!res.success) { alert(res.data.message || auraBudgets.txt.error_generic); return; }
            $('#aura-budget-detail-modal').hide();
            loadBudgets();
            showNotice(auraBudgets.txt.adjusted + ': ' + fmt(res.data.new_amount), 'success');
        });
    });

    // ── Notificación inline ────────────────────────────────────────────
    function showNotice(msg, type) {
        var $n = $('<div class="notice notice-' + (type === 'success' ? 'success' : 'warning') + ' is-dismissible"><p>' + escHtml(msg) + '</p></div>');
        $('.aura-budgets-header').after($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
    }

    // ── Widget en dashboard financiero ─────────────────────────────────
    if ($('#aura-budget-widget-body').length) {
        $.post(auraBudgets.ajaxurl, { action: 'aura_budget_widget_data', nonce: auraBudgets.nonce })
            .done(function (res) {
                if (!res.success || !res.data.budgets.length) {
                    $('#aura-budget-widget-body').html('<p class="description">' + auraBudgets.txt.no_active_budgets + '</p>');
                    return;
                }
                var html = '';
                res.data.budgets.forEach(function (b) {
                    var pct       = parseFloat(b.percentage);
                    var barColor  = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
                    var areaColor = b.area_color || '#6b7280';
                    var areaName  = b.area_name  || 'Sin área';
                    var catName   = b.category_name || '';
                    var areaLabel = '<span class="aura-area-dot" style="background:' + areaColor + '"></span>'
                                  + '<strong>' + escHtml(areaName) + '</strong>';
                    var catLabel  = catName
                                  ? '<span class="aura-widget-cat-sub">' + escHtml(catName) + '</span>'
                                  : '';
                    html += '<div class="aura-widget-budget-row">'
                          + '<div class="aura-widget-budget-meta">' + areaLabel + catLabel
                          + '<span class="aura-widget-pct" style="color:' + barColor + '">' + fmtPct(pct) + '</span></div>'
                          + '<div class="aura-prog-bar"><div class="aura-prog-fill" style="width:' + Math.min(pct, 100) + '%;background:' + barColor + '"></div></div>'
                          + '<div class="aura-widget-budget-amounts"><small>' + fmt(b.executed) + ' / ' + fmt(b.budget_amount) + '</small></div>'
                          + '</div>';
                });
                $('#aura-budget-widget-body').html(html);
            });
    }

    // ── Tabs en modal detalle ──────────────────────────────────────────
    $(document).on('click', '.aura-tab-btn', function () {
        var $btn   = $(this);
        var target = $btn.data('tab');

        // Cambiar estado activo de los botones
        $btn.closest('.aura-tab-nav').find('.aura-tab-btn')
            .removeClass('aura-tab-active')
            .attr('aria-selected', 'false');
        $btn.addClass('aura-tab-active').attr('aria-selected', 'true');

        // Mostrar/ocultar paneles
        $btn.closest('.aura-detail-tabs').find('.aura-tab-panel').hide();
        $('#' + target).show();

        // Cargar análisis cuando corresponde
        if (target === 'aura-tab-analysis' && state.detailId) {
            loadCategoryBreakdown(state.detailId);
        }
    });

    // Resetear tabs al volver a abrir el detalle
    function resetDetailTabs() {
        $('.aura-tab-btn').removeClass('aura-tab-active').attr('aria-selected', 'false');
        $('.aura-tab-btn[data-tab="aura-tab-transactions"]').addClass('aura-tab-active').attr('aria-selected', 'true');
        $('#aura-tab-transactions').show();
        $('#aura-tab-analysis').hide();
        $('#aura-cat-breakdown-content').hide();
        $('#aura-cat-breakdown-loading').show();
        $('#aura-cat-breakdown-empty').hide();
        $('#aura-cat-breakdown-alert').hide();
    }

    // ── Cargar desglose por categoría ──────────────────────────────────
    var breakdownLoaded = {};   // cache para evitar peticiones duplicadas

    function loadCategoryBreakdown(budgetId) {
        // Si ya está cargado para este presupuesto, no recargar
        if (breakdownLoaded[budgetId]) return;

        $('#aura-cat-breakdown-loading').show();
        $('#aura-cat-breakdown-content').hide();
        $('#aura-cat-breakdown-empty').hide();

        $.post(auraBudgets.ajaxurl, {
            action    : 'aura_budget_category_breakdown',
            nonce     : auraBudgets.nonce,
            budget_id : budgetId,
        })
        .done(function (res) {
            $('#aura-cat-breakdown-loading').hide();
            if (!res.success) return;
            var d = res.data;

            if (!d.categories || !d.categories.length) {
                $('#aura-cat-breakdown-empty').show();
                return;
            }

            renderCategoryBreakdown(d);
            breakdownLoaded[budgetId] = true;
        })
        .fail(function () {
            $('#aura-cat-breakdown-loading').hide();
        });
    }

    function renderCategoryBreakdown(data) {
        var cats    = data.categories;
        var total   = parseFloat(data.total_amount) || 0;
        var budget  = parseFloat(data.budget_amount) || 0;
        var pctUsed = parseFloat(data.pct_used) || 0;

        // ── Gráfico de barras CSS ────────────────────────────────────
        var barHtml = '<div class="aura-cat-bars">';
        cats.forEach(function (c) {
            var pct    = parseFloat(c.pct) || 0;
            var color  = c.color || '#607d8b';
            var amount = parseFloat(c.total_amount) || 0;
            barHtml += '<div class="aura-cat-bar-row">'
                + '<div class="aura-cat-bar-label" title="' + escHtml(c.name) + '">' + escHtml(c.name) + '</div>'
                + '<div class="aura-cat-bar-track">'
                + '  <div class="aura-cat-bar-fill" style="width:' + Math.min(pct, 100) + '%;background:' + escHtml(color) + '">'
                + '    <span class="aura-cat-bar-val">$' + fmt(amount) + ' (' + pct.toFixed(1) + '%)</span>'
                + '  </div>'
                + '</div>'
                + '</div>';
        });
        barHtml += '</div>';
        $('#aura-cat-bar-chart').html(barHtml);

        // ── Tabla ──────────────────────────────────────────────────
        var bodyHtml = '';
        cats.forEach(function (c) {
            var color  = c.color || '#607d8b';
            var pct    = parseFloat(c.pct) || 0;
            var barColor = pct > 50 ? '#d63638' : (pct > 25 ? '#dba617' : '#00a32a');
            bodyHtml += '<tr>'
                + '<td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' + escHtml(color) + ';margin-right:6px;vertical-align:middle;"></span>'
                + escHtml(c.name) + '</td>'
                + '<td style="text-align:center;">' + parseInt(c.tx_count) + '</td>'
                + '<td><strong>' + fmt(c.total_amount) + '</strong></td>'
                + '<td style="color:' + barColor + ';font-weight:600;">' + pct.toFixed(1) + '%</td>'
                + '<td><div style="background:#e5e7eb;border-radius:3px;height:10px;min-width:80px;">'
                + '<div style="background:' + escHtml(color) + ';border-radius:3px;height:10px;width:' + Math.min(pct, 100).toFixed(1) + '%"></div>'
                + '</div></td>'
                + '</tr>';
        });
        $('#aura-cat-breakdown-body').html(bodyHtml);

        // Pie de tabla
        var totColor  = pctUsed > 100 ? '#d63638' : '#2271b1';
        var footHtml  = '<tr class="aura-tx-total">'
            + '<td colspan="2"><strong>' + (auraBudgets.txt.total || 'Total') + '</strong></td>'
            + '<td><strong>' + fmt(total) + '</strong></td>'
            + '<td><strong style="color:' + totColor + '">' + fmtPct(pctUsed) + '</strong> del presupuesto (' + fmt(budget) + ')</td>'
            + '<td></td>'
            + '</tr>';
        $('#aura-cat-breakdown-foot').html(footHtml);

        // ── Alerta contextual ──────────────────────────────────────
        var $alert = $('#aura-cat-breakdown-alert');
        $alert.hide();
        if (cats.length && pctUsed >= 90) {
            var top = cats[0];
            var msg = '⚠️ El presupuesto se concentra en <strong>' + escHtml(top.name) + '</strong>'
                + ' que representa el <strong>' + parseFloat(top.pct).toFixed(1) + '%</strong> del total ejecutado.';
            $alert.html('<div style="background:#fff8e7;border:1px solid #dba617;border-radius:6px;padding:10px 14px;font-size:13px;color:#614200;">' + msg + '</div>').show();
        }

        $('#aura-cat-breakdown-content').show();
    }

    // ── Init ───────────────────────────────────────────────────────────
    loadBudgets();

}(jQuery));
