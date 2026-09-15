/**
 * Budgets JS – Fase 5, Item 5.1
 * Requiere: jQuery, ApexCharts
 */
jQuery(function ($) {
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
        var val = parseFloat(n || 0);
        var sign = val < 0 ? '-' : '';
        return sign + '$' + Math.abs(val).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtPct(n) {
        return parseFloat(n || 0).toFixed(1) + '%';
    }

    function fmtDate(dStr) {
        if (!dStr) return '—';
        var parts = dStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }
        return dStr;
    }

    function escAttr(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
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

    // Detectar Modo Oscuro
    function isDarkModeActive() {
        return document.documentElement.classList.contains('wp-dark-mode-active') ||
               document.body.classList.contains('wp-dark-mode-active') ||
               document.body.classList.contains('aura-dark-mode') ||
               document.documentElement.getAttribute('data-wp-dark-mode-scheme') === 'dark' ||
               document.body.getAttribute('data-theme') === 'dark';
    }

    // ── Barra resumen / Tarjetas KPI Modernas ─────────────────────────
    function renderSummaryBar(budgets) {
        var total_budget = 0, total_exec = 0, total_avail = 0, overrun = 0, critical = 0, ok = 0, total_txs = 0;
        budgets.forEach(function (b) {
            var bAmt = parseFloat(b.budget_amount || 0);
            var eAmt = parseFloat(b.executed || 0);
            total_budget += bAmt;
            total_exec   += eAmt;
            total_avail  += Math.max(0, bAmt - eAmt);
            total_txs    += parseInt(b.tx_count || 0);
            if (b.status === 'overrun')  overrun++;
            else if (b.status === 'critical' || b.status === 'warning') critical++;
            else ok++;
        });

        var txSubtext = total_txs + ' ' + (total_txs === 1 ? 'transacción del período' : 'transacciones del período');

        var html = '<div class="aura-kpi-grid">'
            + statCard(fmt(total_budget), auraBudgets.txt.total_budget || 'Total Presupuestado', '#2563eb', 'dashicons-chart-pie', 'Límite monetario total autorizado en todos los presupuestos activos.', 'Presupuesto general aprobado')
            + statCard(fmt(total_exec),   auraBudgets.txt.total_executed || 'Total Ejecutado',     '#10b981', 'dashicons-money-alt', 'Gasto acumulado y computado en ' + total_txs + ' transacciones aprobadas del período actual.', txSubtext)
            + statCard(fmt(total_avail),  auraBudgets.txt.available || 'Saldo Disponible',       '#0284c7', 'dashicons-vault', 'Saldo remanente total disponible para ejecutar sin exceder techos.', 'Remanente consolidado')
            + statCard(String(budgets.length), auraBudgets.txt.total_budgets || 'Presupuestos',    '#6366f1', 'dashicons-portfolio', 'Cantidad total de techos presupuestarios configurados.', 'Techos de gasto vigentes')
            + statCard(String(critical),  auraBudgets.txt.critical_count || 'En Alerta',          '#f59e0b', 'dashicons-flag', 'Presupuestos que han consumido entre el 70% y el 99% de su límite.', 'Consumo preventivo 70-99%')
            + statCard(String(overrun),   auraBudgets.txt.overrun_count || 'Sobrepasados',       '#ef4444', 'dashicons-warning', 'Presupuestos cuyo gasto ha superado el 100% de su límite asignado.', 'Exceso crítico > 100%')
            + '</div>';

        $('#aura-budget-summary-bar').html(html);
    }

    function statCard(val, label, color, icon, tooltip, subtext) {
        return '<div class="aura-stat-card" style="--card-accent:' + color + '">'
            + '<div class="stat-icon" style="background:' + color + '15; color:' + color + '; border:1px solid ' + color + '30;">'
            + '  <span class="dashicons ' + (icon || 'dashicons-chart-pie') + '"></span>'
            + '</div>'
            + '<div class="stat-info">'
            + '  <div class="stat-label-wrap">'
            + '    <span class="stat-label">' + escHtml(label) + '</span>'
            + '    <span class="aura-help-icon" data-tooltip="' + escAttr(tooltip || '') + '"><span class="dashicons dashicons-editor-help"></span></span>'
            + '  </div>'
            + '  <div class="stat-value" style="color:' + color + '">' + val + '</div>'
            + '  <div class="stat-sub">' + escHtml(subtext || '') + '</div>'
            + '</div>'
            + '</div>';
    }

    // ── Renderizar icono de categoría ────────────────────────────────
    function catIcon(b) {
        var rawIcon = (b.category_icon || '📁').trim();
        var color   = b.category_color || '#607d8b';
        if (rawIcon.indexOf('dashicons-') === 0 || rawIcon.indexOf('dashicons') !== -1) {
            return '<span class="dashicons ' + rawIcon + ' aura-cat-icon" style="color:' + color + '"></span>';
        }
        return '<span class="aura-cat-emoji" style="font-size:16px;line-height:1;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;margin-right:4px;">' + rawIcon + '</span>';
    }

    // ── Renderizar tabla de presupuestos ─────────────────────────────
    function renderBudgetsList(budgets) {
        var filterPeriod = $('#aura-filter-period').val();
        var filterStatus = $('#aura-filter-status').val();
        var filterArea   = $('#aura-filter-area').val();
        var searchVal    = ($('#aura-filter-search').val() || '').toLowerCase().trim();

        var filtered = budgets.filter(function (b) {
            if (filterPeriod && b.period_type !== filterPeriod) return false;
            if (filterStatus && b.status    !== filterStatus)   return false;
            if (filterArea   && String(b.area_id) !== filterArea) return false;
            if (searchVal) {
                var aName = (b.area_name || '').toLowerCase();
                var cName = (b.category_name || '').toLowerCase();
                var notes = (b.notes || '').toLowerCase();
                if (aName.indexOf(searchVal) === -1 && cName.indexOf(searchVal) === -1 && notes.indexOf(searchVal) === -1) {
                    return false;
                }
            }
            return true;
        });

        if (!filtered.length) {
            var emptyHtml = '<div class="aura-empty-state" style="text-align:center;padding:48px 24px;border-radius:12px;margin:0;">'
                + '<span class="dashicons dashicons-yes-alt" style="font-size:48px;width:48px;height:48px;color:#10b981;margin-bottom:12px;display:inline-block;"></span>'
                + '<h3 style="margin:0 0 8px;font-size:18px;font-weight:700;">¡Todo al día!</h3>'
                + '<p style="margin:0;font-size:14px;color:#64748b;">' + (auraBudgets.txt.no_active_budgets || 'No hay presupuestos registrados para los filtros seleccionados.') + '</p>'
                + '</div>';
            $('#aura-budgets-list').html(emptyHtml).show();
            $('#aura-budgets-empty').hide();
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

        var html = '<div class="aura-dt-wrapper">'
            + '<table class="widefat aura-budgets-table">'
            + '<thead><tr>'
            + '<th style="min-width:210px;">' + (auraBudgets.txt.h_category || 'Categoría / Área') + ' <span class="aura-help-icon" data-tooltip="Departamento o programa responsable y rubro contable de referencia"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th>' + (auraBudgets.txt.h_period || 'Período & Vigencia') + ' <span class="aura-help-icon" data-tooltip="Ciclo temporal y fechas exactas de vigencia del presupuesto"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th>' + (auraBudgets.txt.h_budget || 'Presupuesto') + ' <span class="aura-help-icon" data-tooltip="Límite máximo monetario asignado y autorizado"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th>' + (auraBudgets.txt.h_executed || 'Ejecutado') + ' <span class="aura-help-icon" data-tooltip="Gasto acumulado en transacciones aprobadas durante el ciclo"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th>' + (auraBudgets.txt.h_available || 'Disponible / Exceso') + ' <span class="aura-help-icon" data-tooltip="Saldo remanente disponible o déficit excedido"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th style="text-align:center;">' + (auraBudgets.txt.h_pct || '%') + ' <span class="aura-help-icon" data-tooltip="Porcentaje consumido del presupuesto asignado"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th style="min-width:140px;">' + (auraBudgets.txt.h_progress || 'Progreso') + ' <span class="aura-help-icon" data-tooltip="Indicador visual del nivel de consumo presupuestario"><span class="dashicons dashicons-editor-help"></span></span></th>'
            + '<th style="text-align:right;">' + (auraBudgets.txt.h_actions || 'Acciones') + '</th>'
            + '</tr></thead><tbody>';

        groupOrder.forEach(function (key) {
            var g = groups[key];

            // Calcular subtotales del grupo
            var subtotalBudget = 0, subtotalExecuted = 0, subtotalAvailable = 0, subtotalOverrun = 0, subtotalTxCount = 0;
            g.items.forEach(function (b) {
                subtotalBudget    += parseFloat(b.budget_amount || 0);
                subtotalExecuted  += parseFloat(b.executed      || 0);
                subtotalAvailable += Math.max(0, parseFloat(b.available || 0));
                subtotalOverrun   += Math.max(0, parseFloat(b.overrun   || 0));
                subtotalTxCount   += parseInt(b.tx_count || 0);
            });
            var subtotalPct      = subtotalBudget > 0 ? Math.round((subtotalExecuted / subtotalBudget) * 100) : 0;
            var subtotalBarColor = subtotalPct > 100 ? '#ef4444' : (subtotalPct >= 90 ? '#f97316' : (subtotalPct >= 70 ? '#f59e0b' : '#10b981'));

            // Badge de área en la cabecera del grupo
            var areaLabel;
            if (g.area_name) {
                var aColor = g.area_color || '#607d8b';
                areaLabel = '<span class="aura-area-title-wrap">'
                    + '<span class="aura-area-color-swatch" style="background:' + escHtml(aColor) + ';"></span>'
                    + '<strong>' + escHtml(g.area_name) + '</strong>'
                    + ' <span class="aura-area-count-badge">' + g.items.length + ' ' + (g.items.length === 1 ? 'presupuesto' : 'presupuestos') + '</span>'
                    + '</span>';
            } else {
                areaLabel = '<em style="color:#94a3b8;">' + (auraBudgets.txt.no_area || 'General') + '</em>';
            }

            // Fila cabecera del grupo (colapsable)
            html += '<tr class="aura-area-group-header" data-group-header="' + escHtml(key) + '">'
                + '<td colspan="2" class="aura-area-header-cell">'
                + '<button type="button" class="aura-group-toggle" data-group="' + escHtml(key) + '" aria-expanded="true" data-tooltip="Colapsar / Expandir presupuestos de esta área">'
                + (auraBudgets.txt.collapse_area || '▲') + '</button>'
                + areaLabel
                + '</td>'
                + '<td><strong class="aura-subtotal-val">' + fmt(subtotalBudget) + '</strong></td>'
                + '<td><div class="aura-exec-cell-wrap"><span class="aura-subtotal-val">' + fmt(subtotalExecuted) + '</span>'
                + '<span class="aura-tx-counter-pill aura-tx-counter-pill--subtotal" data-tooltip="Total de transacciones registradas del área en este período"><span class="dashicons dashicons-tickets-alt"></span> ' + subtotalTxCount + ' ' + (subtotalTxCount === 1 ? 'tx' : 'txs') + '</span></div></td>'
                + '<td>' + (subtotalOverrun > 0 ? '<span class="aura-overrun-badge">-' + fmt(subtotalOverrun) + '</span>' : '<span class="aura-avail-badge">' + fmt(subtotalAvailable) + '</span>') + '</td>'
                + '<td style="text-align:center;"><strong style="color:' + subtotalBarColor + '">' + fmtPct(subtotalPct) + '</strong></td>'
                + '<td><div class="aura-prog-bar"><div class="aura-prog-fill" style="width:' + Math.min(subtotalPct, 100) + '%;background:' + subtotalBarColor + '">' + (subtotalPct > 100 ? '<div class="aura-overrun-stripe"></div>' : '') + '</div></div></td>'
                + '<td style="text-align:right;"><span class="aura-subtotal-tag">' + (auraBudgets.txt.area_subtotal || 'Subtotal Área') + '</span></td>'
                + '</tr>';

            // Filas individuales del grupo
            g.items.forEach(function (b) {
                var pct       = parseFloat(b.percentage || 0);
                var barColor  = pct > 100 ? '#ef4444' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#f59e0b' : '#10b981'));
                var statusCls = 'aura-status-' + b.status;
                var txCount   = parseInt(b.tx_count || 0);
                var txTip     = txCount === 1 ? '1 transacción aprobada registrada en este período' : txCount + ' transacciones aprobadas registradas en este período';
                var txBadge   = '<span class="aura-tx-counter-pill ' + (txCount > 0 ? 'has-txs' : 'no-txs') + '" data-tooltip="' + escAttr(txTip) + '"><span class="dashicons dashicons-tickets-alt"></span> ' + txCount + ' ' + (txCount === 1 ? 'tx' : 'txs') + '</span>';

                // Tooltip enriquecido de vigencia
                var periodTip = '<div class="aura-tip-card">'
                    + '<div class="aura-tip-header"><span class="dashicons dashicons-calendar-alt"></span> Vigencia del Período</div>'
                    + '<div class="aura-tip-row"><span>Ciclo:</span> <strong>' + escHtml(periodLabel(b.period_type)) + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Fecha Inicio:</span> <strong>' + escHtml(fmtDate(b.start_date)) + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Fecha Fin:</span> <strong>' + escHtml(fmtDate(b.end_date)) + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Transacciones:</span> <strong>' + txCount + ' ' + (txCount === 1 ? 'transacción' : 'transacciones') + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Proyección al Cierre:</span> <strong>' + fmt(b.projection) + '</strong></div>'
                    + '</div>';

                var catHtml = b.category_name
                    ? '<div class="aura-cat-cell-title">' + catIcon(b) + '<span>' + escHtml(b.category_name) + '</span></div>'
                    : '<div class="aura-cat-cell-title"><span class="dashicons dashicons-portfolio aura-cat-icon" style="color:' + escHtml(b.area_color || '#607d8b') + '"></span><span>Techo Global de Área</span></div>';

                var areaSubHtml = b.area_name
                    ? '<div class="aura-cat-cell-sub"><span class="aura-area-dot" style="background:' + escHtml(b.area_color || '#607d8b') + '"></span>' + escHtml(b.area_name) + '</div>'
                    : '';

                var availCell = parseFloat(b.overrun) > 0
                    ? '<span class="aura-overrun-badge" data-tooltip="⚠️ Presupuesto excedido en ' + fmt(b.overrun) + '">-' + fmt(b.overrun) + '</span>'
                    : '<span class="aura-avail-badge" data-tooltip="Saldo remanente disponible">' + fmt(b.available) + '</span>';

                html += '<tr class="' + statusCls + ' aura-group-row" data-group-member="' + escHtml(key) + '" data-id="' + b.id + '">'
                    + '<td>' + catHtml + areaSubHtml + '</td>'
                    + '<td><span class="aura-period-badge" data-tooltip="' + escAttr(periodTip) + '"><span class="dashicons dashicons-calendar-alt"></span> ' + escHtml(periodLabel(b.period_type)) + '</span></td>'
                    + '<td><strong class="aura-cell-budget">' + fmt(b.budget_amount) + '</strong></td>'
                    + '<td><div class="aura-exec-cell-wrap"><span class="aura-cell-executed">' + fmt(b.executed) + '</span>' + txBadge + '</div></td>'
                    + '<td>' + availCell + '</td>'
                    + '<td style="text-align:center;"><strong style="color:' + barColor + '">' + fmtPct(pct) + '</strong></td>'
                    + '<td><div class="aura-prog-bar"><div class="aura-prog-fill" style="width:' + Math.min(pct, 100) + '%;background:' + barColor + '">' + (pct > 100 ? '<div class="aura-overrun-stripe"></div>' : '') + '</div></div></td>'
                    + '<td class="aura-row-actions">'
                    + '<button type="button" class="button button-small aura-btn-icon aura-detail-btn" data-id="' + b.id + '" data-tooltip="👁️ Ver análisis detallado, gráfico donut y transacciones"><span class="dashicons dashicons-visibility"></span></button> '
                    + '<button type="button" class="button button-small aura-btn-icon aura-edit-btn" data-id="' + b.id + '" data-tooltip="✏️ Editar presupuesto, montos y alertas"><span class="dashicons dashicons-edit"></span></button> '
                    + '<button type="button" class="button button-small aura-btn-icon aura-btn-icon--danger aura-delete-btn" data-id="' + b.id + '" data-tooltip="🗑️ Eliminar este presupuesto"><span class="dashicons dashicons-trash"></span></button>'
                    + '</td></tr>';
            });
        });

        html += '</tbody></table></div>';
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

    // ── Exportar Presupuestos a Excel / CSV ────────────────────────────
    function exportBudgetsToCSV() {
        var filterPeriod = $('#aura-filter-period').val();
        var filterStatus = $('#aura-filter-status').val();
        var filterArea   = $('#aura-filter-area').val();
        var searchVal    = ($('#aura-filter-search').val() || '').toLowerCase().trim();

        var list = (state.budgets || []).filter(function (b) {
            if (filterPeriod && b.period_type !== filterPeriod) return false;
            if (filterStatus && b.status    !== filterStatus)   return false;
            if (filterArea   && String(b.area_id) !== filterArea) return false;
            if (searchVal) {
                var aName = (b.area_name || '').toLowerCase();
                var cName = (b.category_name || '').toLowerCase();
                var notes = (b.notes || '').toLowerCase();
                if (aName.indexOf(searchVal) === -1 && cName.indexOf(searchVal) === -1 && notes.indexOf(searchVal) === -1) {
                    return false;
                }
            }
            return true;
        });

        if (!list.length) {
            alert('No hay presupuestos para exportar con los filtros seleccionados.');
            return;
        }

        var headers = [
            'Área / Programa',
            'Categoría de Referencia',
            'Tipo de Período',
            'Fecha Inicio',
            'Fecha Fin',
            'Presupuesto Asignado',
            'Monto Ejecutado',
            'N° Transacciones Período',
            'Saldo Disponible',
            'Monto Excedido',
            '% Consumido',
            'Proyección al Cierre',
            'Estado'
        ];

        var rows = [headers.join(';')];

        list.forEach(function (b) {
            var catTitle = b.category_name ? b.category_name : 'Techo Global de Área';
            var pLabel = periodLabel(b.period_type);
            var statusLabel = b.status === 'overrun' ? 'Sobrepasado' : (b.status === 'critical' ? 'Crítico' : (b.status === 'warning' ? 'Advertencia' : 'En buen estado'));

            var row = [
                '"' + (b.area_name || 'General').replace(/"/g, '""') + '"',
                '"' + catTitle.replace(/"/g, '""') + '"',
                '"' + pLabel.replace(/"/g, '""') + '"',
                '"' + (b.start_date || '') + '"',
                '"' + (b.end_date || '') + '"',
                parseFloat(b.budget_amount || 0).toFixed(2),
                parseFloat(b.executed || 0).toFixed(2),
                parseInt(b.tx_count || 0),
                parseFloat(b.available || 0).toFixed(2),
                parseFloat(b.overrun || 0).toFixed(2),
                parseFloat(b.percentage || 0).toFixed(1) + '%',
                parseFloat(b.projection || 0).toFixed(2),
                '"' + statusLabel + '"'
            ];
            rows.push(row.join(';'));
        });

        var csvContent = '\uFEFF' + rows.join('\r\n');
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var now = new Date().toISOString().slice(0, 10);
        link.href = URL.createObjectURL(blob);
        link.download = 'presupuestos-por-area-' + now + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showNotice(auraBudgets.txt.export_success || 'Reporte de presupuestos exportado correctamente en formato compatible con Excel.', 'success');
    }

    // ── Filtros y Búsqueda en Tiempo Real ──────────────────────────────
    $('#aura-filter-period, #aura-filter-status, #aura-filter-area').on('change', function () {
        renderBudgetsList(state.budgets);
    });

    $('#aura-filter-search').on('input', function () {
        renderBudgetsList(state.budgets);
    });

    $('#aura-export-budgets-btn').on('click', function (e) {
        e.preventDefault();
        exportBudgetsToCSV();
    });

    $('#aura-filters-clear').on('click', function () {
        $('#aura-filter-period, #aura-filter-status, #aura-filter-search').val('');
        var $areaFilter = $('#aura-filter-area');
        if (!$areaFilter.prop('disabled')) $areaFilter.val('');
        renderBudgetsList(state.budgets);
    });

    // ── Abrir / Cerrar modal crear y editar ────────────────────────────
    function openBudgetModal(id) {
        state.editingId = id;
        var $form  = $('#aura-budget-form');
        var $modal = $('#aura-budget-modal');

        if ($form.length) {
            $form[0].reset();
        }
        $('#budget_id').val(0);
        $('#aura-budget-modal-title').text(id ? (auraBudgets.txt.edit_title || 'Editar Presupuesto') : (auraBudgets.txt.new_title || 'Nuevo Presupuesto'));
        $('#aura-budget-form-error').hide();

        // Defaults para fecha (mes actual)
        if (!id) {
            var now      = new Date();
            var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            var lastDay  = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            $('#budget_start_date').val(firstDay.toISOString().slice(0, 10));
            $('#budget_end_date').val(lastDay.toISOString().slice(0, 10));
            $('input[name="period_type"][value="monthly"]').prop('checked', true).closest('.aura-radio-pill').addClass('selected').siblings().removeClass('selected');
            $('#notify_extra_toggle').prop('checked', false);
            $('#notify_emails').val('').hide();
        }

        if (id) {
            var b = state.budgets.find(function (x) { return parseInt(x.id) === parseInt(id); });
            if (b) {
                $('#budget_id').val(b.id);
                $('#budget_category_id').val(b.category_id || '');
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
                } else {
                    $('#notify_extra_toggle').prop('checked', false);
                    $('#notify_emails').val('').hide();
                }
            }
        }

        $modal.addClass('active').css({ 'display': 'flex', 'opacity': '1', 'visibility': 'visible' });
        setTimeout(function() {
            $('#budget_area_id, #budget_category_id').first().focus();
        }, 50);
    }

    function closeBudgetModal() {
        $('#aura-budget-modal').removeClass('active').css({ 'display': 'none', 'opacity': '0', 'visibility': 'hidden' });
    }

    function closeDetailModal() {
        $('#aura-budget-detail-modal').removeClass('active').css({ 'display': 'none', 'opacity': '0', 'visibility': 'hidden' });
    }

    $(document).on('click', '#aura-new-budget-btn', function (e) {
        e.preventDefault();
        openBudgetModal(null);
    });

    // Cerrar modal presupuesto
    $(document).on('click', '#aura-budget-modal-close, #aura-budget-cancel', function (e) {
        e.preventDefault();
        closeBudgetModal();
    });

    $(document).on('click', '#aura-budget-modal', function (e) {
        if ($(e.target).is('#aura-budget-modal')) {
            closeBudgetModal();
        }
    });

    // Cerrar con Escape
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeBudgetModal();
            closeDetailModal();
        }
    });

    // Toggle emails adicionales
    $(document).on('change', '#notify_extra_toggle', function () {
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
            closeBudgetModal();
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
        $('#aura-tab-tx-badge').hide().text('0');
        $('#aura-tab-cat-badge').hide().text('0');
        $('#aura-budget-detail-modal').addClass('active').css({ 'display': 'flex', 'opacity': '1', 'visibility': 'visible' });
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

                var txCount = (d.transactions || []).length;
                $('#aura-tab-tx-badge').text(txCount).show();
                if (d.by_category && d.by_category.length) {
                    $('#aura-tab-cat-badge').text(d.by_category.length).show();
                }

                $('#aura-detail-title').text(d.budget.category_name || (d.budget.area_name ? d.budget.area_name + ' (Techo Global)' : auraBudgets.txt.detail_title));
            });
    }

    function renderDetailStats(b) {
        var pct     = parseFloat(b.percentage);
        var color   = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
        var projPct = b.budget_amount > 0 ? Math.round((b.projection / b.budget_amount) * 100) : 0;
        var txCount = parseInt(b.tx_count || 0);

        var html = '<div class="aura-ds-grid">'
            + ds(auraBudgets.txt.budget_total,  fmt(b.budget_amount),  '#50575e')
            + ds(auraBudgets.txt.executed,       fmt(b.executed) + ' (' + fmtPct(pct) + ')', color)
            + ds('Transacciones del Período',    '<span class="aura-ds-tx-val"><span class="dashicons dashicons-tickets-alt"></span> ' + txCount + ' ' + (txCount === 1 ? 'tx' : 'txs') + '</span>', '#8b5cf6')
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
        var catColor = b.category_color || '#2563eb';
        var isDark = isDarkModeActive();

        var series = overr > 0
            ? [budget, overr]
            : [exec, avail];

        var labels = overr > 0
            ? [auraBudgets.txt.executed, auraBudgets.txt.overrun]
            : [auraBudgets.txt.executed, auraBudgets.txt.available];

        var colors = overr > 0
            ? [catColor, '#ef4444']
            : [catColor, isDark ? '#334155' : '#e2e8f0'];

        state.donutChart = new ApexCharts(document.getElementById('aura-budget-donut-chart'), {
            chart  : { type: 'donut', height: 220, toolbar: { show: false }, background: 'transparent' },
            theme  : { mode: isDark ? 'dark' : 'light' },
            series : series,
            labels : labels,
            colors : colors,
            legend : { position: 'bottom', fontSize: '12px', labels: { colors: isDark ? '#f8fafc' : '#334155' } },
            plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: fmtPct(b.percentage), color: isDark ? '#f8fafc' : '#0f172a', formatter: function () { return fmtPct(b.percentage); } } } } } },
            dataLabels: { enabled: false },
            tooltip    : { y: { formatter: function (v) { return fmt(v); } } },
            stroke     : { colors: isDark ? ['#1e293b'] : ['#ffffff'], width: 2 }
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
        var isDark = isDarkModeActive();

        state.histChart = new ApexCharts(document.getElementById('aura-budget-history-chart'), {
            chart  : { type: 'line', height: 200, toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
            theme  : { mode: isDark ? 'dark' : 'light' },
            series : [
                { name: auraBudgets.txt.budget_total, data: history.map(function (h) { return parseFloat(h.budget); }), color: '#2563eb' },
                { name: auraBudgets.txt.executed,     data: history.map(function (h) { return parseFloat(h.executed); }), color: '#ef4444' },
            ],
            xaxis  : { categories: cats, labels: { style: { fontSize: '11px', colors: isDark ? '#94a3b8' : '#64748b' } } },
            yaxis  : { labels: { style: { colors: isDark ? '#94a3b8' : '#64748b' }, formatter: function (v) { return '$' + Math.round(v).toLocaleString('es'); } } },
            stroke : { width: [2.5, 2.5], curve: 'smooth' },
            markers: { size: 4 },
            legend : { position: 'bottom', labels: { colors: isDark ? '#f8fafc' : '#334155' } },
            tooltip: { y: { formatter: function (v) { return fmt(v); } } },
            grid   : { borderColor: isDark ? '#334155' : '#e2e8f0' }
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

        var txLabel = txs.length === 1 ? '1 transacción del período' : txs.length + ' transacciones del período';
        html += '<tr class="aura-tx-total"><td colspan="3"><strong>' + (auraBudgets.txt.total || 'Total') + ' (' + txLabel + ')</strong></td><td colspan="2"><strong>' + fmt(total) + '</strong></td></tr>';
        $('#aura-detail-tx-body').html(html);
    }

    // Cerrar modal detalle
    $(document).on('click', '#aura-detail-modal-close', function (e) {
        e.preventDefault();
        closeDetailModal();
    });

    $(document).on('click', '#aura-budget-detail-modal', function (e) {
        if ($(e.target).is('#aura-budget-detail-modal')) {
            closeDetailModal();
        }
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
            closeDetailModal();
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
        var totalTxs = cats.reduce(function (sum, c) { return sum + (parseInt(c.tx_count) || 0); }, 0);

        cats.forEach(function (c) {
            var color    = c.color || '#607d8b';
            var pct      = parseFloat(c.pct) || 0;
            var barColor = pct > 50 ? '#d63638' : (pct > 25 ? '#dba617' : '#00a32a');
            var cTx      = parseInt(c.tx_count || 0);
            bodyHtml += '<tr>'
                + '<td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:' + escHtml(color) + ';margin-right:6px;vertical-align:middle;"></span>'
                + escHtml(c.name) + '</td>'
                + '<td style="text-align:center;"><span class="aura-tx-counter-pill has-txs"><span class="dashicons dashicons-tickets-alt"></span> ' + cTx + ' ' + (cTx === 1 ? 'tx' : 'txs') + '</span></td>'
                + '<td><strong>' + fmt(c.total_amount) + '</strong></td>'
                + '<td style="color:' + barColor + ';font-weight:600;">' + pct.toFixed(1) + '%</td>'
                + '<td><div style="background:#e5e7eb;border-radius:3px;height:10px;min-width:80px;">'
                + '<div style="background:' + escHtml(color) + ';border-radius:3px;height:10px;width:' + Math.min(pct, 100).toFixed(1) + '%"></div>'
                + '</div></td>'
                + '</tr>';
        });
        $('#aura-cat-breakdown-body').html(bodyHtml);

        // Pie de tabla
        var totColor    = pctUsed > 100 ? '#d63638' : '#2271b1';
        var txFootLabel = totalTxs + ' ' + (totalTxs === 1 ? 'transacción' : 'transacciones');
        var footHtml  = '<tr class="aura-tx-total">'
            + '<td><strong>' + (auraBudgets.txt.total || 'Total') + '</strong></td>'
            + '<td style="text-align:center;"><strong class="aura-cat-tx-total-badge"><span class="dashicons dashicons-tickets-alt"></span> ' + txFootLabel + '</strong></td>'
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

    // ── Motor Global Flotante de Tooltips (#aura-global-tooltip) ───────
    function initFloatingTooltips() {
        var $tip = $('#aura-global-tooltip');
        if (!$tip.length) {
            $tip = $('<div id="aura-global-tooltip" class="aura-global-tooltip">'
                + '<div class="aura-global-tooltip-inner"></div>'
                + '<div class="aura-global-tooltip-arrow"></div>'
                + '</div>');
            $('body').append($tip);
        }

        var $inner = $tip.find('.aura-global-tooltip-inner');
        var hideTimer = null;
        var currentEl = null;

        $(document).on('mouseenter focus', '[data-tooltip], .aura-help-icon', function (e) {
            if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
            var el = this;
            currentEl = el;
            var html = $(el).attr('data-tooltip') || $(el).attr('title') || $(el).attr('data-original-title') || '';
            if (!html || !html.trim()) return;

            // Prevenir tooltip nativo del navegador
            if ($(el).attr('title')) {
                $(el).attr('data-original-title', $(el).attr('title')).removeAttr('title');
            }

            if (html.indexOf('<div') !== -1 || html.indexOf('aura-tip-card') !== -1 || html.indexOf('<span') !== -1) {
                $tip.addClass('has-card-content');
            } else {
                $tip.removeClass('has-card-content');
            }

            $inner.html(html);
            $tip.addClass('is-visible');

            positionTooltip(el, $tip[0]);
        });

        $(document).on('mouseleave blur', '[data-tooltip], .aura-help-icon', function (e) {
            var rel = e.relatedTarget;
            if (rel && ($tip[0] === rel || $tip[0].contains(rel))) {
                return;
            }
            hideTimer = setTimeout(function () {
                $tip.removeClass('is-visible');
                currentEl = null;
            }, 100);
        });

        $tip.on('mouseleave', function (e) {
            var rel = e.relatedTarget;
            if (currentEl && (currentEl === rel || currentEl.contains(rel))) {
                return;
            }
            $tip.removeClass('is-visible');
            currentEl = null;
        });

        function positionTooltip(el, tipEl) {
            var rect = el.getBoundingClientRect();
            var tipRect = tipEl.getBoundingClientRect();
            var gap = 8;
            var top = rect.top - tipRect.height - gap;
            var left = rect.left + (rect.width / 2) - (tipRect.width / 2);
            var isBelow = false;

            if (top < 10) {
                top = rect.bottom + gap;
                isBelow = true;
            }
            if (left < 10) {
                left = 10;
            } else if (left + tipRect.width > window.innerWidth - 10) {
                left = window.innerWidth - tipRect.width - 10;
            }

            tipEl.style.top = Math.round(top) + 'px';
            tipEl.style.left = Math.round(left) + 'px';
            if (isBelow) {
                tipEl.classList.add('is-below');
            } else {
                tipEl.classList.remove('is-below');
            }
        }
    }

    // ── Init ───────────────────────────────────────────────────────────
    initFloatingTooltips();
    loadBudgets();

});
