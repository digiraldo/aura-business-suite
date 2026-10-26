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

    // ── Helper universal para detección semántica de emojis ───────────
    function getCategoryEmoji(name, rawIcon) {
        var icon = (rawIcon || '').trim();
        if (icon && icon.indexOf('dashicons') === -1) {
            return icon;
        }
        var n = (name || '').toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        if (!n) return '📁';

        // Mapeo semántico exhaustivo de palabras clave contables y de negocio
        if (/piscina|alberca|natacion/.test(n)) return '🏊';
        if (/taller|reparac|manten|mecanic|correctiv|preventiv|herramient|ferreter|refaccion/.test(n)) return '🛠️';
        if (/limpiez|aseo|lavad|higien|saniti|conserj|jabon|desinfect/.test(n)) return '🧼';
        if (/quimic|cloro|tratamiento de agua/.test(n)) return '🧪';
        if (/agua|hidric|potable|acueducto/.test(n)) return '💧';
        if (/jardin|planta|paisaj|cesped|poda|arbol|vivero|verde/.test(n)) return '🍃';
        if (/nomin|sueld|salar|honorari|personal|rrhh|recursos humanos|emplead|gratific|liquidac|aguinald/.test(n)) return '👥';
        if (/combust|gasolin|diesel|carburant|gas lp|petroleo|tanque/.test(n)) return '⛽';
        if (/vehicul|auto|camionet|flot|carro|coche|motos|rodaje/.test(n)) return '🚗';
        if (/camion|flete|transp|carga|mudanz|acarreo|logist|envio|courier/.test(n)) return '🚚';
        if (/viaj|viatic|vuelo|bolet|hotel|hospedaj|pasaj|aerol/.test(n)) return '✈️';
        if (/segur|poliz|siniestr|asegura|cobertura|fianza/.test(n)) return '🛡️';
        if (/aliment|comid|restaur|almuerz|desayun|cena|catering|despens|refrigeri|snack/.test(n)) return '🍔';
        if (/cafe|bebid|cafeter|hidratac|refresco|bar/.test(n)) return '☕';
        if (/impuest|fiscal|sat|haciend|tribut|arancel|retenc|iva|isr/.test(n)) return '🏛️';
        if (/oficin|papel|utiles|suminist|toner|librer|impres|archiv/.test(n)) return '📎';
        if (/tecno|softw|comput|sistem|hosting|domini|nube|cloud|servidor|licenc|it|informatic|programac|app|web/.test(n)) return '💻';
        if (/telef|celular|movil|internet|comunicac|fibra|wifi/.test(n)) return '📱';
        if (/market|publi|pauta|redes|anunci|promo|brand|disen|campana/.test(n)) return '📢';
        if (/ventas|comision|comercial|client|cierre/.test(n)) return '🏷️';
        if (/compras|proveed|adquisic|insumo|mercader/.test(n)) return '🛒';
        if (/legal|abogad|juridic|notar|contrat|litigi|demand|ley/.test(n)) return '⚖️';
        if (/banco|financ|prestam|interes|credito|hipotec|amortiz|comision bancar/.test(n)) return '🏦';
        if (/alquiler|renta|inmuebl|arrend|local|predial|bodega|oficinas/.test(n)) return '🏢';
        if (/luz|electr|energia|cfe|alumbrad|planta electrica/.test(n)) return '⚡';
        if (/capacit|curso|entrenam|educac|taller formativ|certific/.test(n)) return '🎓';
        if (/salud|medic|farmac|botiquin|enferm|epp|seguridad ocupacional|clinica/.test(n)) return '🩺';
        if (/seguridad|vigilanc|alarma|camara|guardia|custodi/.test(n)) return '🚨';
        if (/evento|conferenc|congres|reunion|asamble/.test(n)) return '🎪';
        if (/capital|activo|inversion|equip|maquinar|infraestruct/.test(n)) return '🏗️';

        return '📁';
    }

    // ── Renderizar icono/emoji de categoría ───────────────────────────
    function catIcon(b) {
        var emoji = getCategoryEmoji(b.category_name, b.category_icon);
        return '<span class="aura-cat-emoji" style="font-size:16px;line-height:1;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;margin-right:6px;">' + emoji + '</span>';
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
            + '<th style="min-width:210px;">' + (auraBudgets.txt.h_category || 'Categoría / Área') + ' <span class="aura-help-icon" data-tooltip="Departamento o programa responsable y rubro contable de referencia con subcategorías"><span class="dashicons dashicons-editor-help"></span></span></th>'
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
                var hasSubs   = b.subcategories && b.subcategories.length > 0;

                // Tooltip enriquecido de vigencia
                var periodTip = '<div class="aura-tip-card">'
                    + '<div class="aura-tip-header"><span class="dashicons dashicons-calendar-alt"></span> Vigencia del Período</div>'
                    + '<div class="aura-tip-row"><span>Ciclo:</span> <strong>' + escHtml(periodLabel(b.period_type)) + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Fecha Inicio:</span> <strong>' + escHtml(fmtDate(b.start_date)) + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Fecha Fin:</span> <strong>' + escHtml(fmtDate(b.end_date)) + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Transacciones:</span> <strong>' + txCount + ' ' + (txCount === 1 ? 'transacción' : 'transacciones') + '</strong></div>'
                    + '<div class="aura-tip-row"><span>Proyección al Cierre:</span> <strong>' + fmt(b.projection) + '</strong></div>'
                    + '</div>';

                var catHtml = '';
                if (b.category_name) {
                    var catEmoji = getCategoryEmoji(b.category_name, b.category_icon);
                    catHtml = '<div class="aura-cat-cell-title">'
                        + '<span class="aura-cat-emoji" style="font-size:16px;line-height:1;margin-right:4px;">' + catEmoji + '</span>'
                        + '<strong>' + escHtml(b.category_name) + '</strong>';

                    if (hasSubs) {
                        catHtml += ' <button type="button" class="aura-subcat-toggle-btn" data-budget-id="' + b.id + '" aria-expanded="false" data-tooltip="Ver desglose de ' + b.subcategories.length + ' subcategorías vinculadas a este presupuesto">'
                            + '<span class="dashicons dashicons-arrow-right-alt2 aura-subcat-arrow"></span>'
                            + '<span class="aura-subcat-badge">' + b.subcategories.length + ' subcat.</span>'
                            + '</button>';
                    }
                    catHtml += '</div>';
                } else {
                    catHtml = '<div class="aura-cat-cell-title"><span class="dashicons dashicons-portfolio aura-cat-icon" style="color:' + escHtml(b.area_color || '#607d8b') + '"></span><span>Techo Global de Área</span></div>';
                }

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

                // Filas de subcategorías vinculadas (desplegables)
                if (hasSubs) {
                    b.subcategories.forEach(function (sub) {
                        var sEmoji    = getCategoryEmoji(sub.name, sub.icon);
                        var sPct      = parseFloat(sub.percentage || 0);
                        var sBarColor = sub.color || '#6366f1';
                        var sTx       = parseInt(sub.tx_count || 0);
                        var sTxTip    = sTx === 1 ? '1 transacción aprobada en ' + sub.name : sTx + ' transacciones aprobadas en ' + sub.name;
                        var sTxBadge  = '<a href="admin.php?page=aura-financial-transactions&filter_category=' + sub.id + '" target="_blank" rel="noopener noreferrer" class="aura-tx-counter-pill ' + (sTx > 0 ? 'has-txs' : 'no-txs') + '" data-tooltip="' + escAttr(sTxTip) + '"><span class="dashicons dashicons-tickets-alt"></span> ' + sTx + ' ' + (sTx === 1 ? 'tx' : 'txs') + '</a>';

                        html += '<tr class="aura-subcat-row aura-group-row" data-group-member="' + escHtml(key) + '" data-parent-budget="' + b.id + '" style="display:none;">'
                            + '<td class="aura-subcat-cell">'
                            + '  <div class="aura-subcat-cell-inner">'
                            + '    <span class="aura-tree-branch">↳</span>'
                            + '    <span class="aura-cat-emoji" style="font-size:14px;line-height:1;margin-right:4px;">' + sEmoji + '</span>'
                            + '    <span class="aura-subcat-name">' + escHtml(sub.name) + '</span>'
                            + '  </div>'
                            + '</td>'
                            + '<td><span class="aura-subcat-period-tag"><span class="dashicons dashicons-tag"></span> Subrubro</span></td>'
                            + '<td><span class="aura-subcat-parent-note" data-tooltip="Consolidado en el presupuesto del rubro principal">(Consolidado)</span></td>'
                            + '<td><div class="aura-exec-cell-wrap"><span class="aura-cell-executed">' + fmt(sub.executed) + '</span>' + sTxBadge + '</div></td>'
                            + '<td><span style="color:#94a3b8;font-size:12px;">—</span></td>'
                            + '<td style="text-align:center;"><strong style="color:' + sBarColor + '">' + fmtPct(sPct) + '</strong></td>'
                            + '<td><div class="aura-prog-bar aura-prog-bar--sub"><div class="aura-prog-fill" style="width:' + Math.min(sPct, 100) + '%;background:' + sBarColor + '"></div></div></td>'
                            + '<td class="aura-row-actions">'
                            + '  <a href="admin.php?page=aura-financial-transactions&filter_category=' + sub.id + '" target="_blank" class="button button-small aura-btn-icon" data-tooltip="Ver transacciones de ' + escAttr(sub.name) + '"><span class="dashicons dashicons-external"></span></a>'
                            + '</td>'
                            + '</tr>';
                    });
                }
            });
        });

        html += '</tbody></table></div>';
        $('#aura-budgets-list').html(html).show();
        $('#aura-budgets-empty').hide();

        // Toggle collapse/expand de grupos de área
        $(document).off('click.auraGroupToggle').on('click.auraGroupToggle', '.aura-group-toggle', function (e) {
            e.stopPropagation();
            var grp      = $(this).data('group');
            var $rows    = $('[data-group-member="' + grp + '"]');
            var expanded = $(this).attr('aria-expanded') === 'true';
            if (expanded) {
                $rows.hide();
                $(this).attr('aria-expanded', 'false').text(auraBudgets.txt.expand_area || '▼');
            } else {
                $rows.each(function () {
                    // Si es una fila de subcategoría y su padre está colapsado, no mostrarla
                    if ($(this).hasClass('aura-subcat-row')) {
                        var pId = $(this).data('parent-budget');
                        var isParentExp = $('.aura-subcat-toggle-btn[data-budget-id="' + pId + '"]').attr('aria-expanded') === 'true';
                        if (isParentExp) $(this).show();
                    } else {
                        $(this).show();
                    }
                });
                $(this).attr('aria-expanded', 'true').text(auraBudgets.txt.collapse_area || '▲');
            }
        });

        // Toggle collapse/expand de subcategorías dentro de un presupuesto
        $(document).off('click.auraSubcatToggle').on('click.auraSubcatToggle', '.aura-subcat-toggle-btn', function (e) {
            e.stopPropagation();
            var bId = $(this).data('budget-id');
            var $rows = $('tr.aura-subcat-row[data-parent-budget="' + bId + '"]');
            var isExp = $(this).attr('aria-expanded') === 'true';
            if (isExp) {
                $rows.hide();
                $(this).attr('aria-expanded', 'false');
            } else {
                $rows.show();
                $(this).attr('aria-expanded', 'true');
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

    $(document).on('click', '#aura-new-budget-btn, .aura-trigger-new-budget', function (e) {
        e.preventDefault();
        openBudgetModal(null);
    });

    // Cerrar modal presupuesto
    $(document).on('click', '#aura-budget-modal-close, #aura-budget-cancel', function (e) {
        e.preventDefault();
        closeBudgetModal();
    });

    // Cerrar modal detalle
    $(document).on('click', '#aura-detail-modal-close, #aura-detail-modal-close-btn', function (e) {
        e.preventDefault();
        closeDetailModal();
    });

    $(document).on('click', '#aura-budget-modal', function (e) {
        if ($(e.target).is('#aura-budget-modal')) {
            closeBudgetModal();
        }
    });

    $(document).on('click', '#aura-budget-detail-modal', function (e) {
        if ($(e.target).is('#aura-budget-detail-modal')) {
            closeDetailModal();
        }
    });

    // Cerrar con Escape (todos los modales)
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeBudgetModal();
            closeDetailModal();
            if (window.AuraCategoryBudgets && typeof window.AuraCategoryBudgets.closeModal === 'function') {
                window.AuraCategoryBudgets.closeModal();
            }
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

                var modalTitleEmoji = d.budget.category_name ? getCategoryEmoji(d.budget.category_name, d.budget.category_icon) : '🏢';
                var modalTitleText  = d.budget.category_name
                    ? (modalTitleEmoji + ' ' + d.budget.category_name + (d.budget.area_name ? ' — Área ' + d.budget.area_name : ''))
                    : (d.budget.area_name ? '🏢 ' + d.budget.area_name + ' (Techo Global del Área)' : (auraBudgets.txt.detail_title || 'Detalle del Presupuesto'));
                $('#aura-detail-title').text(modalTitleText);
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
                var catEmoji = getCategoryEmoji(t.category_name, t.category_icon);
                var dotColor = t.category_color ? escHtml(t.category_color) : '#607d8b';
                catCell = '<span style="display:inline-flex;align-items:center;gap:6px;">'
                    + '<span class="aura-cat-emoji" style="font-size:14px;line-height:1;">' + catEmoji + '</span>'
                    + '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + dotColor + ';flex-shrink:0;"></span>'
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
            var cEmoji = getCategoryEmoji(c.name, c.icon);
            barHtml += '<div class="aura-cat-bar-row">'
                + '<div class="aura-cat-bar-label" title="' + escHtml(c.name) + '"><span class="aura-cat-emoji" style="font-size:14px;line-height:1;margin-right:4px;">' + cEmoji + '</span>' + escHtml(c.name) + '</div>'
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
            var cEmoji   = getCategoryEmoji(c.name, c.icon);
            bodyHtml += '<tr>'
                + '<td><span class="aura-cat-emoji" style="font-size:14px;line-height:1;margin-right:6px;">' + cEmoji + '</span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:' + escHtml(color) + ';margin-right:6px;vertical-align:middle;"></span>'
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

    // ── Notificaciones flotantes estándar ──────────────────────────────
    function showNotice(msg, type) {
        type = type || 'success';
        var iconMap = {
            success: 'dashicons-yes-alt',
            error  : 'dashicons-dismiss',
            warning: 'dashicons-warning',
            info   : 'dashicons-info'
        };
        var icon = iconMap[type] || 'dashicons-info';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible" style="animation: auraFadeSlideIn 0.25s ease-out; margin: 12px 0; display:flex; align-items:center; gap:8px;">'
            + '<span class="dashicons ' + icon + '"></span>'
            + '<p style="margin: 0.5em 0;">' + escHtml(msg) + '</p>'
            + '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Descartar</span></button>'
            + '</div>');

        $('#aura-wp-notices-container').empty().append($notice);
        $notice.find('.notice-dismiss').on('click', function () {
            $notice.slideUp(200, function () { $(this).remove(); });
        });
        setTimeout(function () {
            $notice.fadeOut(400, function () { $(this).remove(); });
        }, 5000);
    }

    // ── Navegación Principal por Pestañas ───────────────────────────────
    function initMainTabs() {
        if ($('#tab-area-budgets').hasClass('active') || $('#aura-tab-btn-areas').hasClass('active')) {
            $('#aura-new-budget-btn').show();
        } else {
            $('#aura-new-budget-btn').hide();
        }

        $('.aura-main-tab-btn').on('click', function () {
            var targetTab = $(this).data('tab');
            $('.aura-main-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.aura-main-tab-panel').removeClass('active').hide();
            $('#' + targetTab).addClass('active').show();

            // Sincronizar visibilidad del botón "Nuevo Presupuesto por Área"
            if (targetTab === 'tab-area-budgets') {
                $('#aura-new-budget-btn').show();
            } else {
                $('#aura-new-budget-btn').hide();
            }

            // Sincronizar parámetro URL sin recargar
            try {
                var url = new URL(window.location.href);
                if (targetTab === 'tab-area-budgets') {
                    url.searchParams.set('tab', 'areas');
                } else {
                    url.searchParams.delete('tab');
                }
                window.history.replaceState({}, '', url.toString());
            } catch (e) {}

            // Si es la pestaña de categorías, redimensionar gráficos ApexCharts para evitar distorsión
            if (targetTab === 'tab-category-budgets') {
                if (AuraCategoryBudgets.trendChart) {
                    AuraCategoryBudgets.trendChart.render();
                }
                if (AuraCategoryBudgets.donutChart) {
                    AuraCategoryBudgets.donutChart.render();
                }
            }
        });

        // Activar pestaña según URL si viene especificado ?tab=areas
        try {
            var params = new URLSearchParams(window.location.search);
            if (params.get('tab') === 'areas') {
                $('#aura-tab-btn-areas').trigger('click');
            }
        } catch (e) {}
    }

    // ══════════════════════════════════════════════════════════════════════
    // CONTROLADOR: PRESUPUESTOS Y ESTADÍSTICAS POR CATEGORÍA & SUBCATEGORÍAS
    // ══════════════════════════════════════════════════════════════════════
    var AuraCategoryBudgets = {
        currentCatId : null,
        currentYear  : parseInt($('#aura-cat-budget-year').val(), 10) || new Date().getFullYear(),
        categories   : [],
        data         : null,
        trendChart   : null,
        donutChart   : null,
        treeExpanded : true,

        init: function (initialCatId) {
            var self = this;

            // Leer categoría inicial de URL si existe
            try {
                var urlParams = new URLSearchParams(window.location.search);
                var catParam  = urlParams.get('category_id');
                if (catParam) {
                    initialCatId = parseInt(catParam, 10);
                }
            } catch (e) {}

            // Cargar datos iniciales
            self.loadStats(initialCatId || 0, self.currentYear);

            // Listener de cambio de ejercicio fiscal
            $('#aura-cat-budget-year').on('change', function () {
                self.currentYear = parseInt($(this).val(), 10) || new Date().getFullYear();
                self.loadStats(self.currentCatId, self.currentYear);
            });

            // Listener de cambio de categoría principal
            $('#aura-cat-budget-category-select').on('change', function () {
                var catId = parseInt($(this).val(), 10) || 0;
                if (catId) {
                    self.currentCatId = catId;
                    self.loadStats(catId, self.currentYear);

                    // Sincronizar URL
                    try {
                        var u = new URL(window.location.href);
                        u.searchParams.set('category_id', catId);
                        window.history.replaceState({}, '', u.toString());
                    } catch (e) {}
                }
            });

            // Botón abrir modal de asignación
            $('#aura-cat-budget-open-modal-btn').on('click', function () {
                self.openModal();
            });

            // Botón exportar CSV
            $('#aura-cat-budget-export-btn').on('click', function () {
                self.exportCSV();
            });

            // Botón imprimir / PDF
            $('#aura-cat-budget-print-btn').on('click', function () {
                self.prepareAndPrint();
            });

            // Botón toggle colapsar / expandir todas las subcategorías
            $('#aura-cat-toggle-all-tree').on('click', function () {
                self.toggleAllTree();
            });

            // Clic en fila padre o botón expander para alternar subcategorías
            $(document).on('click', '.aura-row-parent, .aura-tree-expander-btn', function (e) {
                if ($(e.target).closest('a, button:not(.aura-tree-expander-btn)').length) {
                    return;
                }
                var $btn = $(this).closest('tr').find('.aura-tree-expander-btn');
                $btn.toggleClass('collapsed');
                var isCollapsed = $btn.hasClass('collapsed');
                $('.aura-row-child').toggleClass('is-hidden', isCollapsed);

                self.treeExpanded = !isCollapsed;
                var $toggleAll = $('#aura-cat-toggle-all-tree');
                if (self.treeExpanded) {
                    $toggleAll.html('<span class="dashicons dashicons-arrow-up-alt2"></span> <span>Colapsar Subcategorías</span>');
                } else {
                    $toggleAll.html('<span class="dashicons dashicons-arrow-down-alt2"></span> <span>Desplegar Subcategorías</span>');
                }
            });

            // Inicializar eventos del modal de presupuesto de categoría
            self.initModalEvents();
        },

        // Preparar página y disparar impresión (se ajusta a Carta / Oficio / Horizontal / Vertical)
        prepareAndPrint: function () {
            var self = this;
            if (!self.data) {
                window.print();
                return;
            }
            var fam = self.data.category || self.data.family || {};
            var ex  = self.data.execution || {};
            var b   = self.data.budget || {};

            $('#aura-print-fiscal-year').text(self.currentYear);
            $('#aura-print-category-name').text(fam.name || 'Categoría');

            var statusLabels = {
                ok: 'Consumo Saludable (< 70%)',
                warning: 'Alerta Preventiva (70 - 89%)',
                critical: 'Nivel Crítico (90 - 99%)',
                overrun: '🚨 Sobrepasado (> 100%)',
                nobudget: 'Sin Presupuesto Asignado'
            };
            var stText = (statusLabels[ex.status] || 'Sin Presupuesto');
            if (b.has_budget) {
                stText += ' — ' + fmtPct(ex.execution_pct) + ' ejecutado';
            }
            $('#aura-print-status-label').text(stText);

            // Asegurarse de que todas las subcategorías estén expandidas y visibles
            $('.aura-row-child').removeClass('is-hidden');
            $('.aura-tree-expander-btn').removeClass('collapsed');

            // Forzar renderizado y ajuste de ApexCharts antes del diálogo de impresión
            if (self.trendChart) self.trendChart.render();
            if (self.donutChart) self.donutChart.render();

            setTimeout(function () {
                window.print();
            }, 200);
        },

        // Cargar estadísticas completas desde el Backend
        loadStats: function (categoryId, fiscalYear) {
            var self = this;
            $('#aura-cat-budget-loading').show();
            $('#aura-cat-budget-content').css('opacity', '0.4');

            $.post(auraBudgets.ajaxurl, {
                action      : 'aura_get_category_budget_stats',
                nonce       : auraBudgets.nonce,
                category_id : categoryId || 0,
                fiscal_year : fiscalYear
            }).done(function (res) {
                $('#aura-cat-budget-loading').hide();
                $('#aura-cat-budget-content').css('opacity', '1');

                if (!res.success || !res.data) {
                    showNotice(res.data && res.data.message ? res.data.message : 'Error al cargar estadísticas de la categoría.', 'error');
                    return;
                }

                var d = res.data;
                self.categories   = d.categories || [];
                self.currentCatId = d.selected_category_id || 0;
                self.currentYear  = d.fiscal_year || fiscalYear;
                self.data         = d.stats || null;

                // Poblar select de categorías principales con emojis y optgroups
                self.populateCategorySelect(self.categories, self.currentCatId);

                // Renderizar vista si hay estadísticas
                if (self.data) {
                    self.renderKPIs(self.data);
                    self.renderStatusBanner(self.data);
                    self.renderTrendChart(self.data);
                    self.renderDonutChart(self.data);
                    self.renderTable(self.data);
                }
            }).fail(function () {
                $('#aura-cat-budget-loading').hide();
                $('#aura-cat-budget-content').css('opacity', '1');
                showNotice('Error de conexión con el servidor al cargar presupuestos.', 'error');
            });
        },

        // Poblar selector de categorías agrupado visualmente con emojis y semáforos
        populateCategorySelect: function (categories, selectedId) {
            var $sel = $('#aura-cat-budget-category-select');
            $sel.empty();

            if (!categories || !categories.length) {
                $sel.append('<option value="">No hay categorías de egreso registradas</option>');
                return;
            }

            var budgeted = [];
            var unbudgeted = [];

            categories.forEach(function (c) {
                if (c.has_budget) {
                    budgeted.push(c);
                } else {
                    unbudgeted.push(c);
                }
            });

            // Asignación inteligente de icono o emoji por palabras clave
            function getCatEmoji(c) {
                return getCategoryEmoji(c.name, c.icon);
            }

            // Grupo 1: Categorías con Presupuesto Asignado
            if (budgeted.length > 0) {
                var $grpBudgeted = $('<optgroup label="🟢 💰 CATEGORÍAS CON PRESUPUESTO ASIGNADO (' + budgeted.length + ')"></optgroup>');
                budgeted.forEach(function (c) {
                    var isSelected = (parseInt(c.id, 10) === parseInt(selectedId, 10)) ? ' selected' : '';
                    var bAmt = parseFloat(c.budget_amount || 0);
                    var pct = parseFloat(c.execution_pct || 0);
                    var icon = getCatEmoji(c);
                    var subs = c.subcategories_count > 0 ? ' (' + c.subcategories_count + ' subcat.)' : '';

                    var statusBadge = '🟢';
                    if (pct >= 100) {
                        statusBadge = '🚨 🔴 SOBREGIRO';
                    } else if (pct >= 70) {
                        statusBadge = '⚠️ 🟡 ALERTA';
                    } else {
                        statusBadge = '✅ 🟢 SALUDABLE';
                    }

                    var optText = statusBadge + ' | ' + icon + ' ' + c.name + subs + ' — Presupuesto: ' + fmt(bAmt) + ' (' + pct.toFixed(1) + '% consumido)';
                    $grpBudgeted.append('<option value="' + c.id + '"' + isSelected + '>' + escHtml(optText) + '</option>');
                });
                $sel.append($grpBudgeted);
            }

            // Grupo 2: Categorías sin Presupuesto Asignado
            if (unbudgeted.length > 0) {
                var $grpUnbudgeted = $('<optgroup label="⚪ 📋 CATEGORÍAS SIN PRESUPUESTO (' + unbudgeted.length + ')"></optgroup>');
                unbudgeted.forEach(function (c) {
                    var isSelected = (parseInt(c.id, 10) === parseInt(selectedId, 10)) ? ' selected' : '';
                    var spent = parseFloat(c.total_spent || 0);
                    var icon = getCatEmoji(c);
                    var subs = c.subcategories_count > 0 ? ' (' + c.subcategories_count + ' subcat.)' : '';
                    var spentInfo = spent > 0 ? ' (Gastado YTD: ' + fmt(spent) + ')' : ' (Sin movimientos)';

                    var optText = '⚪ ❌ SIN PRESUPUESTO | ' + icon + ' ' + c.name + subs + ' — ' + spentInfo;
                    $grpUnbudgeted.append('<option value="' + c.id + '"' + isSelected + '>' + escHtml(optText) + '</option>');
                });
                $sel.append($grpUnbudgeted);
            }

            if (selectedId) {
                $sel.val(selectedId);
            }
        },

        // Renderizar Tarjetas KPI Gerenciales
        renderKPIs: function (stats) {
            var b  = stats.budget || {};
            var ex = stats.execution || {};

            // 1. Presupuesto Autorizado
            var budgetAmt = parseFloat(b.budget_amount !== undefined ? b.budget_amount : (b.budget_annual || 0));
            var budgetMonthly = parseFloat(b.budget_monthly || 0);

            if (b.has_budget && budgetAmt > 0) {
                $('#kpi-cat-budget-amount').text(fmt(budgetAmt));
                $('#kpi-cat-budget-monthly').text(fmt(budgetMonthly) + ' / mes prom.');
                if (b.calculation_mode === 'percentage') {
                    $('#kpi-cat-budget-mode-badge').text(parseFloat(b.budget_percentage || 0).toFixed(1) + '% de la bolsa').show();
                } else {
                    $('#kpi-cat-budget-mode-badge').text('Monto Fijo').show();
                }
            } else {
                $('#kpi-cat-budget-amount').text('$0.00');
                $('#kpi-cat-budget-monthly').text('Sin límite configurado');
                $('#kpi-cat-budget-mode-badge').text('No Asignado');
            }

            // 2. Gasto Real Ejecutado
            var spent = parseFloat(ex.total_spent || 0);
            var txCount = parseInt(ex.tx_count || 0, 10);
            $('#kpi-cat-spent-amount').text(fmt(spent));
            $('#kpi-cat-spent-tx-count').text(txCount + ' ' + (txCount === 1 ? 'transacción aprobada' : 'transacciones aprobadas'));

            // 3. Saldo Remanente / Margen Libre
            var remaining = parseFloat(ex.remaining || 0);
            var overrun   = parseFloat(ex.overrun || 0);
            var $remCard  = $('#kpi-cat-remaining-card');

            if (b.has_budget) {
                if (overrun > 0) {
                    $('#kpi-cat-remaining-amount').html('<span style="color:#ef4444;">-' + fmt(overrun) + '</span>');
                    $('#kpi-cat-remaining-label').text('⚠️ Sobregiro presupuestario');
                    $remCard.removeClass('kpi-indigo').addClass('kpi-amber');
                } else {
                    $('#kpi-cat-remaining-amount').text(fmt(remaining));
                    $('#kpi-cat-remaining-label').text('Margen libre disponible para ejecutar');
                    $remCard.removeClass('kpi-amber').addClass('kpi-indigo');
                }
            } else {
                $('#kpi-cat-remaining-amount').text('N/A');
                $('#kpi-cat-remaining-label').text('Sin presupuesto que descontar');
            }

            // 4. % de Ejecución y Semáforo
            var execPct = parseFloat(ex.execution_pct || 0);
            var status  = ex.status || 'nobudget';
            var fam     = stats.category || stats.family || {};
            var famColor = fam.color || '';
            var $fill   = $('#kpi-cat-progress-fill');
            var $badge  = $('#kpi-cat-status-badge');

            $fill.removeClass('fill-warning fill-critical fill-overrun').css('background', '');
            $badge.removeClass('status-badge--ok status-badge--warning status-badge--critical status-badge--overrun status-badge--nobudget');

            if (!b.has_budget) {
                $('#kpi-cat-pct-value').text('—');
                $fill.css('width', '0%');
                $badge.addClass('status-badge--nobudget').text('Sin Presupuesto');
            } else {
                $('#kpi-cat-pct-value').text(execPct.toFixed(1) + '%');
                $fill.css('width', Math.min(100, execPct).toFixed(1) + '%');

                if (status === 'overrun') {
                    $fill.addClass('fill-overrun');
                    $badge.addClass('status-badge--overrun').text('Sobrepasado (>100%)');
                } else if (status === 'critical') {
                    $fill.addClass('fill-critical');
                    $badge.addClass('status-badge--critical').text('Crítico (90-99%)');
                } else if (status === 'warning') {
                    $fill.addClass('fill-warning');
                    $badge.addClass('status-badge--warning').text('Preventivo (70-89%)');
                } else {
                    $badge.addClass('status-badge--ok').text('Saludable (<70%)');
                    if (famColor) {
                        $fill.css('background', famColor);
                    }
                }
            }

            // 5. Proyección de Cierre
            var proj = parseFloat(ex.projection_year_end || 0);
            $('#kpi-cat-projection-amount').text(fmt(proj));
            if (ex.is_year_closed) {
                $('#kpi-cat-projection-title').text('Cierre Definitivo del Ejercicio');
                $('#kpi-cat-projection-desc').text('Año fiscal finalizado');
            } else {
                $('#kpi-cat-projection-title').text('Proyección al Cierre de Año');
                var diff = parseFloat(ex.diff_vs_projection || 0);
                if (b.has_budget && budgetAmt > 0) {
                    if (diff < 0) {
                        $('#kpi-cat-projection-desc').html('<span style="color:#ef4444;">Exceso proyectado de ' + fmt(Math.abs(diff)) + '</span>');
                    } else {
                        $('#kpi-cat-projection-desc').html('<span style="color:#059669;">Superávit proyectado de ' + fmt(diff) + '</span>');
                    }
                } else {
                    $('#kpi-cat-projection-desc').text('Ritmo anualizado estimado');
                }
            }
        },

        // Renderizar Banner Superior de Estado
        renderStatusBanner: function (stats) {
            var b   = stats.budget || {};
            var ex  = stats.execution || {};
            var fam = stats.category || stats.family || {};
            var budgetAmt = parseFloat(b.budget_amount !== undefined ? b.budget_amount : (b.budget_annual || 0));
            var $banner = $('#aura-cat-budget-status-banner');

            $banner.removeClass('status-ok status-warning status-critical status-overrun status-nobudget');

            if (!b.has_budget) {
                $banner.addClass('status-nobudget').html(
                    '<div><strong>ℹ️ Rubro sin presupuesto autorizado:</strong> Esta categoría no cuenta con un techo financiero definido para el ejercicio ' + stats.fiscal_year + '. Puedes asignarle un monto o porcentaje con el botón "Asignar / Editar Presupuesto".</div>'
                ).show();
                return;
            }

            var status = ex.status;
            var html = '';
            if (status === 'overrun') {
                $banner.addClass('status-overrun');
                html = '<div><strong>🚨 Presupuesto Sobrepasado en ' + escHtml(fam.name || 'Categoría') + ':</strong> Se ha superado el límite autorizado de ' + fmt(budgetAmt) + ' por un monto de <strong>' + fmt(ex.overrun) + '</strong> (' + fmtPct(ex.execution_pct) + ' ejecutado). Se recomienda autorizar ampliación o congelar egresos.</div>';
            } else if (status === 'critical') {
                $banner.addClass('status-critical');
                html = '<div><strong>⚠️ Nivel Crítico de Consumo:</strong> Se ha ejecutado el <strong>' + fmtPct(ex.execution_pct) + '</strong> del presupuesto. Queda un saldo remanente de tan solo <strong>' + fmt(ex.remaining) + '</strong> para el resto del ejercicio fiscal.</div>';
            } else if (status === 'warning') {
                $banner.addClass('status-warning');
                html = '<div><strong>⚡ Alerta Preventiva:</strong> Se ha alcanzado el <strong>' + fmtPct(ex.execution_pct) + '</strong> del techo presupuestario anual. Saldo restante: <strong>' + fmt(ex.remaining) + '</strong>.</div>';
            } else {
                $banner.addClass('status-ok');
                html = '<div><strong>✅ Ejecución Presupuestaria Saludable:</strong> El gasto acumulado representa el <strong>' + fmtPct(ex.execution_pct) + '</strong> del presupuesto aprobado (' + fmt(budgetAmt) + '). Cuentas con <strong>' + fmt(ex.remaining) + '</strong> de saldo libre.</div>';
            }

            $banner.html(html).show();
        },

        // Renderizar Gráfico de Curva de Evolución Mensual (ApexCharts)
        renderTrendChart: function (stats) {
            var self = this;
            var $el = document.getElementById('aura-cat-trend-chart');
            if (!$el) return;

            if (self.trendChart) {
                self.trendChart.destroy();
                self.trendChart = null;
            }
            $el.innerHTML = '';

            var trend = stats.monthly_trend || [];
            var isDark = isDarkModeActive();
            var fam = stats.category || stats.family || {};
            var famColor = fam.color || '#2563eb';

            var categories = trend.map(function (m) { return m.month_name || m.month; });
            var realSpent  = trend.map(function (m) { return parseFloat(m.spent !== undefined ? m.spent : (m.spent_actual || 0)); });
            var cumSpent   = trend.map(function (m) { return parseFloat(m.cumulative_spent || 0); });
            var cumBudget  = trend.map(function (m) { return parseFloat(m.cumulative_budget || 0); });

            var series = [
                {
                    name: 'Gasto Real Acumulado',
                    type: 'area',
                    data: cumSpent
                },
                {
                    name: 'Presupuesto Meta Acumulado',
                    type: 'line',
                    data: cumBudget
                },
                {
                    name: 'Gasto Mensual Efectivo',
                    type: 'column',
                    data: realSpent
                }
            ];

            var options = {
                chart: {
                    height: 330,
                    type: 'line',
                    stacked: false,
                    toolbar: { show: false },
                    background: 'transparent'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                colors: [famColor, '#10b981', famColor + '99'],
                stroke: {
                    width: [3, 2.5, 0],
                    curve: 'smooth',
                    dashArray: [0, 5, 0]
                },
                fill: {
                    type: ['gradient', 'solid', 'solid'],
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.05,
                        stops: [0, 95, 100]
                    }
                },
                plotOptions: {
                    bar: {
                        columnWidth: '35%',
                        borderRadius: 4
                    }
                },
                xaxis: {
                    categories: categories,
                    labels: { style: { fontSize: '11px', colors: isDark ? '#94a3b8' : '#64748b' } }
                },
                yaxis: {
                    labels: {
                        style: { colors: isDark ? '#94a3b8' : '#64748b' },
                        formatter: function (v) { return '$' + Math.round(v).toLocaleString('es'); }
                    }
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (v) {
                            return fmt(v);
                        }
                    }
                },
                legend: {
                    position: 'top',
                    labels: { colors: isDark ? '#f8fafc' : '#334155' }
                },
                series: series
            };

            self.trendChart = new ApexCharts($el, options);
            self.trendChart.render();
        },

        // Renderizar Gráfico Donut de Participación de Subcategorías
        renderDonutChart: function (stats) {
            var self = this;
            var $el = document.getElementById('aura-cat-donut-chart');
            if (!$el) return;

            if (self.donutChart) {
                self.donutChart.destroy();
                self.donutChart = null;
            }
            $el.innerHTML = '';

            var fam  = stats.category || stats.family || {};
            var subs = stats.subcategories || [];
            var isDark = isDarkModeActive();

            // Filtrar las que tienen gasto > 0
            var activeSubs = subs.filter(function (s) {
                return parseFloat(s.total_spent || 0) > 0;
            }).map(function (s) {
                return {
                    name: s.name,
                    total_spent: parseFloat(s.total_spent || 0),
                    color: s.color
                };
            });

            // Si hay gasto directo en la categoría padre, añadirlo como sector
            var directSpent = parseFloat(stats.execution ? stats.execution.direct_spent : 0);
            if (directSpent > 0) {
                activeSubs.unshift({
                    name: (fam.name || 'Categoría') + ' (Gasto Directo)',
                    total_spent: directSpent,
                    color: fam.color || '#2563eb'
                });
            }

            if (!activeSubs.length) {
                $el.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:280px;color:#94a3b8;text-align:center;">'
                    + '<span class="dashicons dashicons-chart-pie" style="font-size:42px;width:42px;height:42px;opacity:0.4;margin-bottom:8px;"></span>'
                    + '<p style="margin:0;font-size:13.5px;">No hay transacciones registradas en este ejercicio para generar el desglose.</p>'
                    + '</div>';
                return;
            }

            var series = activeSubs.map(function (s) { return parseFloat(s.total_spent); });
            var labels = activeSubs.map(function (s) { return s.name; });
            var colors = activeSubs.map(function (s, idx) {
                return s.color || ['#2563eb', '#10b981', '#6366f1', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4', '#14b8a6', '#f97316'][idx % 9];
            });

            var totalSpent = stats.execution ? parseFloat(stats.execution.total_spent || 0) : 0;

            var options = {
                chart: {
                    type: 'donut',
                    height: 330,
                    toolbar: { show: false },
                    background: 'transparent'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                series: series,
                labels: labels,
                colors: colors,
                legend: {
                    position: 'bottom',
                    fontSize: '12px',
                    labels: { colors: isDark ? '#f8fafc' : '#334155' }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total Gastado',
                                    color: isDark ? '#f8fafc' : '#0f172a',
                                    formatter: function () {
                                        return fmt(totalSpent);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                tooltip: {
                    y: {
                        formatter: function (v) {
                            var pct = totalSpent > 0 ? ((v / totalSpent) * 100).toFixed(1) + '%' : '0%';
                            return fmt(v) + ' (' + pct + ')';
                        }
                    }
                },
                stroke: {
                    colors: isDark ? ['#1e293b'] : ['#ffffff'],
                    width: 2
                }
            };

            self.donutChart = new ApexCharts($el, options);
            self.donutChart.render();
        },

        // Renderizar Tabla Árbol Desplegable de Categoría y Subcategorías
        renderTable: function (stats) {
            var fam  = stats.category || stats.family || {};
            var b    = stats.budget || {};
            var ex   = stats.execution || {};
            var subs = stats.subcategories || [];

            $('#aura-cat-table-family-name').text(fam.name || 'Categoría');
            $('#aura-cat-table-subs-count').text(subs.length + ' ' + (subs.length === 1 ? 'subcategoría' : 'subcategorías'));

            var $tbody = $('#aura-cat-budget-table-body');
            var $tfoot = $('#aura-cat-budget-table-foot');
            $tbody.empty();
            $tfoot.empty();

            var parentDirectTxs  = parseInt(ex.direct_tx || 0, 10);
            var parentTotalTxs   = parseInt(ex.tx_count || 0, 10);
            var parentTotalSpent = parseFloat(ex.total_spent || 0);
            var budgetAmt        = parseFloat(b.budget_amount !== undefined ? b.budget_amount : (b.budget_annual || 0));

            var parentTxUrl = 'admin.php?page=aura-financial-transactions&filter_category=' + fam.id;

            // Fila 1: Categoría Principal (Padre)
            var parentRow = '<tr class="aura-row-parent" data-id="' + fam.id + '">'
                + '<td>'
                + '  <div class="aura-cat-parent-title">'
                + '    <button type="button" class="aura-tree-expander-btn" title="Expandir o contraer subcategorías">'
                + '      <span class="dashicons dashicons-arrow-down-alt2"></span>'
                + '    </button>'
                + '    <span class="aura-cat-color-circle" style="background:' + escHtml(fam.color || '#2563eb') + ';width:12px;height:12px;border-radius:50%;display:inline-block;"></span>'
                + '    <span class="aura-cat-emoji" style="font-size:16px;line-height:1;margin-right:6px;">' + getCategoryEmoji(fam.name, fam.icon) + '</span>'
                + '    <strong style="font-size:14.5px;color:#0f172a;">' + escHtml(fam.name) + '</strong>'
                + '    <span class="aura-badge-mini" style="background:#e0e7ff;color:#3730a3;">Categoría Principal</span>'
                + '  </div>'
                + '</td>'
                + '<td style="text-align:center;">'
                + '  <a href="' + parentTxUrl + '" target="_blank" rel="noopener noreferrer" class="aura-tx-counter-pill has-txs" title="Ver transacciones consolidadas">'
                + '    <span class="dashicons dashicons-tickets-alt"></span> ' + parentTotalTxs + ' ' + (parentTotalTxs === 1 ? 'tx' : 'txs')
                + '  </a>'
                + '</td>'
                + '<td style="text-align:right;"><strong style="font-size:14.5px;">' + fmt(parentTotalSpent) + '</strong></td>'
                + '<td style="text-align:center;"><strong style="color:#2563eb;">100.0%</strong></td>'
                + '<td style="text-align:center;">'
                + (b.has_budget && budgetAmt > 0 ? '<strong>' + fmtPct(ex.execution_pct) + '</strong>' : '—')
                + '</td>'
                + '<td style="text-align:center;">'
                + '  <button type="button" class="button button-small" onclick="AuraCategoryBudgets.openModal();" title="Editar presupuesto">'
                + '    <span class="dashicons dashicons-edit"></span>'
                + '  </button>'
                + '</td>'
                + '</tr>';

            $tbody.append(parentRow);

            // Si hay transacciones imputadas directamente a la categoría padre
            if (parentDirectTxs > 0) {
                var directSpent = parseFloat(ex.direct_spent || 0);
                var directPctOfParent = parentTotalSpent > 0 ? (directSpent / parentTotalSpent) * 100 : 0;
                var directPctOfBudget = budgetAmt > 0 ? (directSpent / budgetAmt) * 100 : 0;

                var directRow = '<tr class="aura-row-child" style="background:#fcfcfd;">'
                    + '<td>'
                    + '  <div class="aura-subcat-title">'
                    + '    <span class="aura-subcat-color-dot" style="background:#94a3b8;"></span>'
                    + '    <span class="aura-cat-emoji" style="font-size:14px;line-height:1;margin-right:4px;">📁</span>'
                    + '    <em style="color:#64748b;">(Movimientos directos a la raíz)</em>'
                    + '  </div>'
                    + '</td>'
                    + '<td style="text-align:center;">'
                    + '  <a href="' + parentTxUrl + '" target="_blank" class="aura-tx-counter-pill has-txs">'
                    + '    <span class="dashicons dashicons-tickets-alt"></span> ' + parentDirectTxs + ' txs'
                    + '  </a>'
                    + '</td>'
                    + '<td style="text-align:right;">' + fmt(directSpent) + '</td>'
                    + '<td style="text-align:center;">'
                    + '  <div class="aura-table-pct-cell">'
                    + '    <span>' + directPctOfParent.toFixed(1) + '%</span>'
                    + '    <div class="aura-mini-bar-wrap"><div class="aura-mini-bar-fill" style="width:' + Math.min(100, directPctOfParent) + '%;background:#94a3b8;"></div></div>'
                    + '  </div>'
                    + '</td>'
                    + '<td style="text-align:center;">' + (budgetAmt > 0 ? directPctOfBudget.toFixed(1) + '%' : '—') + '</td>'
                    + '<td style="text-align:center;"><a href="' + parentTxUrl + '" target="_blank" class="button button-small" title="Ver movimientos directos"><span class="dashicons dashicons-external"></span></a></td>'
                    + '</tr>';

                $tbody.append(directRow);
            }

            // Filas Hijas: Subcategorías
            var $toggleTreeBtn = $('#aura-cat-toggle-all-tree');
            if (!subs.length && parentDirectTxs === 0) {
                $tbody.append('<tr class="aura-row-child"><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px;">No hay subcategorías ni transacciones registradas bajo este rubro.</td></tr>');
                $toggleTreeBtn.prop('disabled', true).css('opacity', '0.6').html('<span class="dashicons dashicons-minus"></span> <span>Sin Subcategorías</span>');
            } else {
                $toggleTreeBtn.prop('disabled', false).css('opacity', '1');
                if (self.treeExpanded) {
                    $toggleTreeBtn.html('<span class="dashicons dashicons-arrow-up-alt2"></span> <span>Colapsar Subcategorías</span>');
                } else {
                    $toggleTreeBtn.html('<span class="dashicons dashicons-arrow-down-alt2"></span> <span>Desplegar Subcategorías</span>');
                }
                subs.forEach(function (s) {
                    var sTx = parseInt(s.tx_count || 0, 10);
                    var sSpent = parseFloat(s.total_spent || 0);
                    var sPctParent = parseFloat(s.pct_of_category !== undefined ? s.pct_of_category : (s.pct_of_parent || 0));
                    var sPctBudget = parseFloat(s.pct_of_budget || 0);
                    var sTxUrl = 'admin.php?page=aura-financial-transactions&filter_category=' + s.id;
                    var sColor = s.color || '#6366f1';
                    var sEmoji = getCategoryEmoji(s.name, s.icon);

                    var childRow = '<tr class="aura-row-child" data-id="' + s.id + '">'
                        + '<td>'
                        + '  <div class="aura-subcat-title">'
                        + '    <span class="aura-subcat-color-dot" style="background:' + escHtml(sColor) + ';"></span>'
                        + '    <span class="aura-cat-emoji" style="font-size:14px;line-height:1;margin-right:4px;">' + sEmoji + '</span>'
                        + '    <span style="font-weight:600;color:#334155;">' + escHtml(s.name) + '</span>'
                        + '  </div>'
                        + '</td>'
                        + '<td style="text-align:center;">'
                        + (sTx > 0
                            ? '<a href="' + sTxUrl + '" target="_blank" rel="noopener noreferrer" class="aura-tx-counter-pill has-txs"><span class="dashicons dashicons-tickets-alt"></span> ' + sTx + ' ' + (sTx === 1 ? 'tx' : 'txs') + '</a>'
                            : '<span class="aura-tx-counter-pill no-txs">0 txs</span>')
                        + '</td>'
                        + '<td style="text-align:right;font-weight:600;">' + fmt(sSpent) + '</td>'
                        + '<td style="text-align:center;">'
                        + '  <div class="aura-table-pct-cell">'
                        + '    <span style="font-weight:700;color:#1e293b;">' + sPctParent.toFixed(1) + '%</span>'
                        + '    <div class="aura-mini-bar-wrap"><div class="aura-mini-bar-fill" style="width:' + Math.min(100, sPctParent) + '%;background:' + escHtml(sColor) + ';"></div></div>'
                        + '  </div>'
                        + '</td>'
                        + '<td style="text-align:center;">'
                        + (b.has_budget && budgetAmt > 0
                            ? '<span style="color:#64748b;font-weight:600;">' + sPctBudget.toFixed(1) + '%</span>'
                            : '—')
                        + '</td>'
                        + '<td style="text-align:center;">'
                        + '  <a href="' + sTxUrl + '" target="_blank" class="button button-small" title="Ver transacciones de ' + escAttr(s.name) + '">'
                        + '    <span class="dashicons dashicons-external"></span>'
                        + '  </a>'
                        + '</td>'
                        + '</tr>';

                    $tbody.append(childRow);
                });
            }

            // Pie de Tabla
            var footRow = '<tr>'
                + '<td><strong>TOTAL CONSOLIDADO (' + escHtml(fam.name || 'Categoría') + ')</strong></td>'
                + '<td style="text-align:center;"><strong class="aura-cat-tx-total-badge"><span class="dashicons dashicons-tickets-alt"></span> ' + parentTotalTxs + ' transacciones</strong></td>'
                + '<td style="text-align:right;"><strong style="font-size:15px;color:#0f172a;">' + fmt(parentTotalSpent) + '</strong></td>'
                + '<td style="text-align:center;"><strong>100.0%</strong></td>'
                + '<td style="text-align:center;"><strong>' + (b.has_budget && budgetAmt > 0 ? fmtPct(ex.execution_pct) : '—') + '</strong></td>'
                + '<td></td>'
                + '</tr>';
            $tfoot.append(footRow);
        },

        // Alternar colapso de todas las subcategorías
        toggleAllTree: function () {
            this.treeExpanded = !this.treeExpanded;
            var isExpanded = this.treeExpanded;
            $('.aura-tree-expander-btn').toggleClass('collapsed', !isExpanded);
            $('.aura-row-child').toggleClass('is-hidden', !isExpanded);

            var $btn = $('#aura-cat-toggle-all-tree');
            if (isExpanded) {
                $btn.html('<span class="dashicons dashicons-arrow-up-alt2"></span> <span>Colapsar Subcategorías</span>');
            } else {
                $btn.html('<span class="dashicons dashicons-arrow-down-alt2"></span> <span>Desplegar Subcategorías</span>');
            }
        },

        // ── Modal de Presupuesto de Categoría ──────────────────────────────
        initModalEvents: function () {
            var self = this;

            // Cambio de modo de cálculo (Monto Fijo vs Porcentaje)
            $('input[name="calculation_mode"], input[name="calc_mode"]').on('change', function () {
                var mode = $(this).val();
                if (mode === 'percentage') {
                    $('#aura-group-budget-amount').hide();
                    $('#aura-group-budget-percentage').show();
                    $('#aura-mode-pill-percentage').addClass('selected');
                    $('#aura-mode-pill-amount').removeClass('selected');
                } else {
                    $('#aura-group-budget-percentage').hide();
                    $('#aura-group-budget-amount').show();
                    $('#aura-mode-pill-amount').addClass('selected');
                    $('#aura-mode-pill-percentage').removeClass('selected');
                }
            });

            // Conteo mensual automático al escribir monto anual
            $('#aura-modal-budget-amount').on('input', function () {
                var amt = parseFloat($(this).val()) || 0;
                var perMonth = amt > 0 ? (amt / 12) : 0;
                $('#aura-modal-monthly-calc').text('Equivale a aprox. ' + fmt(perMonth) + ' al mes (distribuido parejo en 12 meses).');
            });

            // Toggle desglose mensual
            $('#aura-toggle-monthly-dist-btn').on('click', function () {
                $('#aura-monthly-dist-container').slideToggle(200);
            });

            // Distribuir Parejo (÷12)
            $('#aura-distribute-even-btn').on('click', function () {
                var total = parseFloat($('#aura-modal-budget-amount').val()) || 0;
                var even = total > 0 ? Math.round((total / 12) * 100) / 100 : 0;
                $('.aura-modal-month-input').val(even.toFixed(2));
            });

            // Cancelar y cerrar modal
            $('#aura-cat-modal-cancel-btn, #aura-cat-modal-close-btn, #aura-category-budget-modal .aura-modal-close').on('click', function () {
                self.closeModal();
            });

            // Clic en backdrop para cerrar
            $('#aura-category-budget-modal').on('click', function (e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // Guardar formulario
            $('#aura-cat-budget-form').on('submit', function (e) {
                e.preventDefault();
                self.saveBudget();
            });

            // Eliminar presupuesto
            $('#aura-cat-modal-delete-btn').on('click', function () {
                self.deleteBudget();
            });
        },

        // Abrir modal y prellenar datos
        openModal: function () {
            var self = this;
            var fam = self.data ? (self.data.category || self.data.family) : null;
            var b   = self.data ? self.data.budget : null;

            if (!fam) {
                alert('Selecciona primero una categoría.');
                return;
            }

            $('#aura-cat-modal-icon-badge').text(getCategoryEmoji(fam.name, fam.icon));
            $('#aura-cat-modal-cat-name, #aura-modal-cat-name-display').text(fam.name || 'Categoría');
            $('#aura-cat-modal-year-display, #aura-modal-fiscal-year-display').text(self.currentYear);
            $('#aura-modal-cat-id, #aura-modal-hidden-cat-id').val(fam.id);
            $('#aura-modal-cat-year, #aura-modal-hidden-fiscal-year').val(self.currentYear);

            var subCount = (fam.subcategories && Array.isArray(fam.subcategories)) ? fam.subcategories.length : 0;
            $('#aura-cat-modal-subs-info').text(subCount + ' ' + (subCount === 1 ? 'subcategoría vinculada' : 'subcategorías vinculadas'));

            // Prellenar campos
            if (b && b.has_budget) {
                $('#aura-modal-budget-id, #aura-modal-hidden-budget-id').val(b.budget_id || b.id || 0);
                $('#aura-cat-modal-title').html('<span class="dashicons dashicons-edit"></span> <span>Editar Presupuesto de Categoría</span>');
                $('#aura-cat-modal-delete-btn').show();

                var budgetAmt = parseFloat(b.budget_amount !== undefined ? b.budget_amount : (b.budget_annual || 0));

                if (b.calculation_mode === 'percentage') {
                    $('input[name="calculation_mode"][value="percentage"], input[name="calc_mode"][value="percentage"]').prop('checked', true).trigger('change');
                    $('#aura-modal-budget-pct').val(b.budget_percentage || '');
                    $('#aura-modal-budget-amount').val(budgetAmt || '');
                } else {
                    $('input[name="calculation_mode"][value="amount"], input[name="calc_mode"][value="amount"]').prop('checked', true).trigger('change');
                    $('#aura-modal-budget-amount').val(budgetAmt || '');
                    $('#aura-modal-budget-pct').val('');
                }

                $('#aura-modal-budget-amount').trigger('input');
                $('#aura-modal-alert-threshold').val(b.alert_threshold || 80);
                $('#aura-modal-alert-exceed-chk').prop('checked', b.alert_on_exceed !== false);

                // Llenar inputs mensuales si existen
                if (b.custom_monthly && typeof b.custom_monthly === 'object') {
                    $('.aura-modal-month-input').each(function () {
                        var m = $(this).data('month');
                        if (b.custom_monthly[m] !== undefined) {
                            $(this).val(parseFloat(b.custom_monthly[m]).toFixed(2));
                        } else {
                            $(this).val('');
                        }
                    });
                    $('#aura-monthly-dist-container').show();
                } else {
                    $('.aura-modal-month-input').val('');
                    $('#aura-monthly-dist-container').hide();
                }
            } else {
                $('#aura-modal-budget-id, #aura-modal-hidden-budget-id').val(0);
                $('#aura-cat-modal-title').html('<span class="dashicons dashicons-money-alt"></span> <span>Asignar Techo Presupuestario</span>');
                $('#aura-cat-modal-delete-btn').hide();
                $('input[name="calculation_mode"][value="amount"], input[name="calc_mode"][value="amount"]').prop('checked', true).trigger('change');
                $('#aura-modal-budget-amount').val('');
                $('#aura-modal-budget-pct').val('');
                $('#aura-modal-monthly-calc').text('Equivale a aprox. $0.00 al mes.');
                $('#aura-modal-alert-threshold').val(80);
                $('#aura-modal-alert-exceed-chk').prop('checked', true);
                $('.aura-modal-month-input').val('');
                $('#aura-monthly-dist-container').hide();
            }

            $('#aura-category-budget-modal').addClass('active').css({ 'display': 'flex', 'opacity': '1', 'visibility': 'visible' });
        },

        closeModal: function () {
            $('#aura-category-budget-modal').removeClass('active').css({ 'display': 'none', 'opacity': '0', 'visibility': 'hidden' });
        },

        // Guardar Presupuesto vía AJAX
        saveBudget: function () {
            var self = this;
            var $saveBtn = $('#aura-cat-modal-save-btn').prop('disabled', true);

            var catId    = parseInt($('#aura-modal-cat-id').val() || $('#aura-modal-hidden-cat-id').val(), 10);
            var year     = parseInt($('#aura-modal-cat-year').val() || $('#aura-modal-hidden-fiscal-year').val(), 10);
            var calcMode = $('input[name="calculation_mode"]:checked, input[name="calc_mode"]:checked').val() || 'amount';
            var amount   = parseFloat($('#aura-modal-budget-amount').val()) || 0;
            var pct      = parseFloat($('#aura-modal-budget-pct').val()) || null;
            var thresh   = parseInt($('#aura-modal-alert-threshold').val(), 10) || 80;
            var alertExc = $('#aura-modal-alert-exceed-chk').is(':checked') ? 1 : 0;

            // Recopilar distribución mensual si está visible
            var monthly = {};
            var hasMonthly = false;
            $('.aura-modal-month-input').each(function () {
                var m = $(this).data('month');
                var v = parseFloat($(this).val());
                if (!isNaN(v) && v >= 0) {
                    monthly[m] = v;
                    hasMonthly = true;
                }
            });

            var payload = {
                action            : 'aura_save_category_budget',
                nonce             : auraBudgets.nonce,
                category_id       : catId,
                fiscal_year       : year,
                calculation_mode  : calcMode,
                budget_amount     : amount,
                budget_percentage : pct,
                alert_threshold   : thresh,
                alert_on_exceed   : alertExc,
                monthly_distribution : hasMonthly ? monthly : ''
            };

            $.post(auraBudgets.ajaxurl, payload).done(function (res) {
                $saveBtn.prop('disabled', false);
                if (!res.success) {
                    alert(res.data && res.data.message ? res.data.message : 'Error al guardar el presupuesto.');
                    return;
                }
                self.closeModal();
                showNotice(res.data.message || 'Presupuesto guardado correctamente.', 'success');
                // Recargar estadísticas
                self.loadStats(catId, year);
            }).fail(function () {
                $saveBtn.prop('disabled', false);
                alert('Error de conexión al guardar el presupuesto.');
            });
        },

        // Eliminar Presupuesto
        deleteBudget: function () {
            var self = this;
            var fam = self.data ? (self.data.category || self.data.family) : null;
            var catName = fam ? fam.name : 'esta categoría';

            if (!confirm('¿Estás seguro de eliminar el presupuesto asignado para ' + catName + ' en el año ' + self.currentYear + '?')) {
                return;
            }

            var catId = parseInt($('#aura-modal-cat-id').val() || $('#aura-modal-hidden-cat-id').val(), 10);
            var year  = parseInt($('#aura-modal-cat-year').val() || $('#aura-modal-hidden-fiscal-year').val(), 10);
            var $delBtn = $('#aura-cat-modal-delete-btn').prop('disabled', true);

            $.post(auraBudgets.ajaxurl, {
                action      : 'aura_delete_category_budget',
                nonce       : auraBudgets.nonce,
                category_id : catId,
                fiscal_year : year
            }).done(function (res) {
                $delBtn.prop('disabled', false);
                if (!res.success) {
                    alert(res.data && res.data.message ? res.data.message : 'Error al eliminar el presupuesto.');
                    return;
                }
                self.closeModal();
                showNotice(res.data.message || 'Presupuesto eliminado.', 'warning');
                self.loadStats(catId, year);
            }).fail(function () {
                $delBtn.prop('disabled', false);
                alert('Error de conexión al eliminar el presupuesto.');
            });
        },

        // Exportar Resumen a CSV
        exportCSV: function () {
            var self = this;
            if (!self.data) {
                alert('No hay datos para exportar.');
                return;
            }

            var fam  = self.data.category || self.data.family || {};
            var b    = self.data.budget || {};
            var ex   = self.data.execution || {};
            var subs = self.data.subcategories || [];
            var budgetAmt = parseFloat(b.budget_amount !== undefined ? b.budget_amount : (b.budget_annual || 0));

            var rows = [];
            rows.push(['REPORTE DE EJECUCIÓN PRESUPUESTARIA POR CATEGORÍA']);
            rows.push(['Categoría Principal', fam.name]);
            rows.push(['Ejercicio Fiscal', self.currentYear]);
            rows.push(['Presupuesto Autorizado', b.has_budget ? budgetAmt : 0]);
            rows.push(['Gasto Real Ejecutado', ex.total_spent]);
            rows.push(['Saldo Remanente', ex.remaining]);
            rows.push(['% Ejecución', ex.execution_pct + '%']);
            rows.push(['Transacciones Totales', ex.tx_count]);
            rows.push([]);
            rows.push(['Rubro / Subcategoría', 'Transacciones', 'Gasto Real ($)', '% de la Categoría', '% del Presupuesto']);

            // Fila de la categoría
            rows.push([fam.name + ' (Total Consolidado)', ex.tx_count, ex.total_spent, '100%', ex.execution_pct + '%']);

            if (ex.direct_spent > 0) {
                var dPct = ex.total_spent > 0 ? ((ex.direct_spent / ex.total_spent) * 100).toFixed(1) + '%' : '0%';
                var dPctBud = budgetAmt > 0 ? ((ex.direct_spent / budgetAmt) * 100).toFixed(1) + '%' : '0%';
                rows.push(['-- Imputado directo a la categoría', ex.direct_tx, ex.direct_spent, dPct, dPctBud]);
            }

            subs.forEach(function (s) {
                var sPctParent = parseFloat(s.pct_of_category !== undefined ? s.pct_of_category : (s.pct_of_parent || 0));
                var sPctBudget = parseFloat(s.pct_of_budget || 0);
                rows.push([
                    '-- ' + s.name,
                    s.tx_count,
                    s.total_spent,
                    sPctParent + '%',
                    sPctBudget + '%'
                ]);
            });

            // Generar contenido CSV con BOM UTF-8
            var csvContent = '\uFEFF';
            rows.forEach(function (row) {
                var escaped = row.map(function (field) {
                    var str = String(field !== undefined && field !== null ? field : '');
                    if (str.indexOf(',') !== -1 || str.indexOf('"') !== -1 || str.indexOf('\n') !== -1) {
                        return '"' + str.replace(/"/g, '""') + '"';
                    }
                    return str;
                });
                csvContent += escaped.join(',') + '\r\n';
            });

            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var filename = 'presupuesto-' + (fam.slug || 'cat') + '-' + self.currentYear + '.csv';
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }
    };

    // ── Init ───────────────────────────────────────────────────────────
    initFloatingTooltips();
    initMainTabs();
    AuraCategoryBudgets.init();
    loadBudgets();

});

