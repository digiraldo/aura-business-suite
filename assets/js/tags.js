/**
 * Tags Management — Aura Business Suite
 * Fase 5, Item 5.2
 * Optimizado según directrices de prompt-maestro.md
 */
/* global auraTagsConfig, jQuery */
(function ($) {
    'use strict';

    /* ============================================================
       Estado global
       ============================================================ */
    let allTags = [];       // [{name, count, income_count, expense_count, income_amount, expense_amount, total_amount, net_balance, first_date, latest_date}]
    let globalCurrency = 'USD';
    let pendingDeleteTag = null;

    /* ============================================================
       Init
       ============================================================ */
    $(function () {
        initPortalTooltip();
        loadAllTags();
        loadCloud();
        bindEvents();
        initAutocomplete();
    });

    /* ============================================================
       Portal Global de Tooltips (Zero Clipping)
       ============================================================ */
    function initPortalTooltip() {
        if (typeof AuraUI !== 'undefined' && AuraUI.initTooltips) {
            AuraUI.initTooltips();
        }
    }

    /* ============================================================
       Cargar tabla de tags
       ============================================================ */
    function loadAllTags() {
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_get_all',
            nonce:  auraTagsConfig.nonce,
        }).done(function (res) {
            if (res.success) {
                allTags = res.data.tags || [];
                globalCurrency = res.data.currency || 'USD';
                updateKpis(allTags);
                renderTable(allTags);
            } else {
                renderTableError(res.data && res.data.message);
            }
        }).fail(function () {
            renderTableError('Error de conexión con el servidor.');
        });
    }

    /* ============================================================
       Actualizar KPIs en tiempo real
       ============================================================ */
    function updateKpis(tags) {
        if (!tags) return;
        const totalTags = tags.length;
        const totalUsages = tags.reduce((acc, curr) => acc + parseInt(curr.count || 0, 10), 0);

        $('#kpi-total-tags').text(new Intl.NumberFormat().format(totalTags));
        $('#kpi-total-usages').text(new Intl.NumberFormat().format(totalUsages));
        $('#table-tags-counter').text(totalTags + ' etiquetas');
    }

    /* ============================================================
       Renderizar tabla de etiquetas (7 Columnas + Child Rows)
       ============================================================ */
    function renderTable(tags) {
        const $body = $('#tags-table-body');

        if (!tags || tags.length === 0) {
            $body.html('<tr><td colspan="7" style="text-align:center; padding: 36px 20px;">' +
                '<div class="aura-empty-state-box" style="padding:0;">' +
                '<span class="dashicons dashicons-tag" style="font-size:36px;width:36px;height:36px;color:#94a3b8;margin-bottom:8px;"></span>' +
                '<p style="color:#64748b;margin:0;font-size:13.5px;">' + auraTagsConfig.i18n.noResults + '</p>' +
                '</div></td></tr>');
            return;
        }

        const rows = [];

        tags.forEach(function (tag, index) {
            const escaped = escHtml(tag.name);
            const count = parseInt(tag.count || 0, 10);
            const incomeCount = parseInt(tag.income_count || 0, 10);
            const expenseCount = parseInt(tag.expense_count || 0, 10);
            const incomeAmount = parseFloat(tag.income_amount || 0);
            const expenseAmount = parseFloat(tag.expense_amount || 0);
            const totalAmount = parseFloat(tag.total_amount || 0);
            const netBalance = parseFloat(tag.net_balance || 0);
            const firstDate = escHtml(tag.first_date || '');
            const latestDate = escHtml(tag.latest_date || '');
            const rowId = 'tag-row-' + index;

            const searchUrl = auraTagsConfig.searchUrl ? `${auraTagsConfig.searchUrl}&search_tag=${encodeURIComponent(tag.name)}` : '#';

            // Porcentajes de transacciones
            const incomePct = count > 0 ? Math.round((incomeCount / count) * 100) : 0;
            const expensePct = count > 0 ? Math.round((expenseCount / count) * 100) : 0;

            // 1. Tooltip HTML: Posición / ID
            const idTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(37,99,235,0.2);color:#3b82f6;"><span class="dashicons dashicons-tag"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Posición #${index + 1}</div>` +
                `<div class="aura-tip-subtitle">Registro en Catálogo</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Etiqueta:</span><strong>#${escaped}</strong></div>` +
                `<div class="aura-tip-row"><span>Acción:</span><strong style="color:#2563eb;">Haz clic en [+] para abrir el desglose contable</strong></div>` +
                `</div>` +
                `</div>`;

            // 2. Tooltip HTML: Chip Etiqueta
            const tagTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(59,130,246,0.2);color:#3b82f6;font-size:24px;font-weight:900;">#</div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">#${escaped}</div>` +
                `<div class="aura-tip-subtitle">Taxonomía Transversal</div>` +
                `<div class="aura-tip-badges"><span class="aura-tip-badge aura-tip-badge--company">🏷️ Tag</span></div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>📊 Movimientos:</span><strong>${count} transacciones</strong></div>` +
                `<div class="aura-tip-row"><span>🟢 Ingresos:</span><strong>+${formatMoney(incomeAmount, globalCurrency)} (${incomeCount})</strong></div>` +
                `<div class="aura-tip-row"><span>🔴 Egresos:</span><strong>-${formatMoney(expenseAmount, globalCurrency)} (${expenseCount})</strong></div>` +
                `<div class="aura-tip-row"><span>⚖️ Balance Neto:</span><strong style="color:${netBalance >= 0 ? '#10b981' : '#ef4444'}">${netBalance >= 0 ? '+' : ''}${formatMoney(netBalance, globalCurrency)}</strong></div>` +
                `</div>` +
                `</div>`;

            // 3. Tooltip HTML: Usos / Movimientos
            const usageTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(16,185,129,0.2);color:#10b981;"><span class="dashicons dashicons-networking"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Desglose de Movimientos</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Total Movimientos:</span><strong>${count} transacciones</strong></div>` +
                `<div class="aura-tip-row"><span>🟢 Ingresos:</span><strong>${incomeCount} movs. (${incomePct}%)</strong></div>` +
                `<div class="aura-tip-row"><span>🔴 Egresos:</span><strong>${expenseCount} movs. (${expensePct}%)</strong></div>` +
                `</div>` +
                `</div>`;

            // 4. Tooltip HTML: Volumen Total
            const volumeTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(99,102,241,0.2);color:#6366f1;"><span class="dashicons dashicons-money-alt"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Volumen Financiero Total</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Suma Acumulada:</span><strong>${formatMoney(totalAmount, globalCurrency)}</strong></div>` +
                `<div class="aura-tip-row"><span>Ingresos (+):</span><strong style="color:#059669;">+${formatMoney(incomeAmount, globalCurrency)}</strong></div>` +
                `<div class="aura-tip-row"><span>Egresos (-):</span><strong style="color:#dc2626;">-${formatMoney(expenseAmount, globalCurrency)}</strong></div>` +
                `</div>` +
                `</div>`;

            // 5. Tooltip HTML: Balance Neto
            const balanceTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:${netBalance >= 0 ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)'};color:${netBalance >= 0 ? '#10b981' : '#ef4444'};"><span class="dashicons dashicons-chart-pie"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Balance Neto Resultante</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Balance Neto:</span><strong style="color:${netBalance >= 0 ? '#059669' : '#dc2626'};font-size:13.5px;">${netBalance >= 0 ? '+' : ''}${formatMoney(netBalance, globalCurrency)}</strong></div>` +
                `<div class="aura-tip-row"><span>Diagnóstico:</span><strong>${netBalance >= 0 ? 'Flujo neto a favor (Superávit)' : 'Flujo neto con saldo deudor (Déficit)'}</strong></div>` +
                `</div>` +
                `</div>`;

            // 6. Tooltip HTML: Último Uso
            const dateTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(148,163,184,0.2);color:#64748b;"><span class="dashicons dashicons-calendar-alt"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Trazabilidad Temporal</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Primer Registro:</span><strong>${firstDate || 'Sin registro'}</strong></div>` +
                `<div class="aura-tip-row"><span>Último Registro:</span><strong>${latestDate || 'Sin registro'}</strong></div>` +
                `</div>` +
                `</div>`;

            // 7. Tooltips HTML para Botones de Acción
            const editTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(37,99,235,0.2);color:#2563eb;"><span class="dashicons dashicons-edit"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Renombrar Etiqueta</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Acción:</span><strong>Carga el tag en el panel de renombrado</strong></div>` +
                `<div class="aura-tip-row"><span>Alcance:</span><strong>Actualiza todas las transacciones asociadas</strong></div>` +
                `</div>` +
                `</div>`;

            const searchTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(16,185,129,0.2);color:#059669;"><span class="dashicons dashicons-search"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Explorar Transacciones</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Filtro:</span><strong>Ver movimientos vinculados a #${escaped}</strong></div>` +
                `<div class="aura-tip-row"><span>Destino:</span><strong>Búsqueda Avanzada de Finanzas</strong></div>` +
                `</div>` +
                `</div>`;

            const deleteTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:rgba(220,38,38,0.2);color:#dc2626;"><span class="dashicons dashicons-trash"></span></div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">Eliminar Etiqueta</div>` +
                `<div class="aura-tip-subtitle">#${escaped}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span style="color:#ef4444;">⚠️ Advertencia:</span><strong style="color:#ef4444;">Desvinculará este tag de todas las transacciones</strong></div>` +
                `<div class="aura-tip-row"><span>Confirmación:</span><strong>Abre modal de eliminación segura</strong></div>` +
                `</div>` +
                `</div>`;

            // 1. Fila Principal
            const parentRow = `<tr class="aura-parent-row" data-row-id="${rowId}" data-tag="${escaped}">
                <td style="text-align: center;">
                    <div class="aura-id-toggle-wrap">
                        <button type="button" class="aura-row-toggle" aria-expanded="false" data-tooltip="${escAttr(idTipHtml)}">+</button>
                        <span class="aura-txn-id-pill" data-tooltip="${escAttr(idTipHtml)}">#${index + 1}</span>
                    </div>
                </td>
                <td>
                    <span class="aura-tag-chip" data-tag="${escaped}" data-tooltip="${escAttr(tagTipHtml)}">
                        <span class="dashicons dashicons-tag" style="font-size:12px;width:12px;height:12px;"></span>
                        #${escaped}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:help;" data-tooltip="${escAttr(usageTipHtml)}">
                        <span class="aura-badge aura-badge-blue" style="font-weight:700;font-size:11.5px;padding:3px 8px;">
                            ${count} ${count === 1 ? 'mov.' : 'movs.'}
                        </span>
                        <div style="font-size:10.5px;color:#64748b;display:flex;gap:6px;">
                            <span style="color:#059669;font-weight:600;">🟢 ${incomeCount}</span>
                            <span style="color:#dc2626;font-weight:600;">🔴 ${expenseCount}</span>
                        </div>
                    </div>
                </td>
                <td style="text-align: right;">
                    <span class="aura-amt-total" style="font-weight:800;color:#0f172a;font-size:13px;cursor:help;" data-tooltip="${escAttr(volumeTipHtml)}">
                        ${formatMoney(totalAmount, globalCurrency)}
                    </span>
                </td>
                <td style="text-align: right;">
                    <span class="aura-amt-balance ${netBalance >= 0 ? 'is-positive' : 'is-negative'}" style="font-weight:800;font-size:13px;cursor:help;" data-tooltip="${escAttr(balanceTipHtml)}">
                        ${netBalance >= 0 ? '+' : ''}${formatMoney(netBalance, globalCurrency)}
                    </span>
                </td>
                <td style="text-align: center;" class="aura-col-tablet-hidden">
                    <span class="aura-date-badge" style="cursor:help;" data-tooltip="${escAttr(dateTipHtml)}">${latestDate ? latestDate : '—'}</span>
                </td>
                <td style="text-align: right;">
                    <div class="aura-table-actions-cell" style="display:inline-flex;gap:6px;justify-content:flex-end;">
                        <button type="button" class="aura-btn-icon aura-btn-icon--edit btn-rename-quick" data-tag="${escaped}" data-tooltip="${escAttr(editTipHtml)}" aria-label="Renombrar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <a href="${searchUrl}" class="aura-btn-icon aura-btn-icon--search btn-search-tag" data-tooltip="${escAttr(searchTipHtml)}" aria-label="Ver movimientos">
                            <span class="dashicons dashicons-search"></span>
                        </a>
                        <button type="button" class="aura-btn-icon aura-btn-icon--danger btn-delete-tag" data-tag="${escaped}" data-tooltip="${escAttr(deleteTipHtml)}" aria-label="Eliminar">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </td>
            </tr>`;

            // 2. Fila Hija Expandible (Universal Child Row)
            const childRow = `<tr class="aura-child-row" data-child-for="${rowId}">
                <td colspan="7">
                    <div class="aura-child-card">
                        <div class="aura-child-hero-bar">
                            <div class="aura-child-hero-left">
                                <span class="aura-type-pill" style="background:#eff6ff;color:#2563eb;font-weight:900;">#</span>
                                <div>
                                    <h4 class="aura-child-hero-title">Etiqueta: #${escaped}</h4>
                                    <div style="font-size:11.5px;color:#64748b;margin-top:2px;">Taxonomía y Desglose Financiero Integral</div>
                                </div>
                            </div>
                            <div class="aura-child-hero-right">
                                <span class="aura-badge ${netBalance >= 0 ? 'aura-badge-green' : 'aura-badge-red'}" style="font-size:12.5px;font-weight:700;padding:4px 10px;">
                                    Balance Neto: ${netBalance >= 0 ? '+' : ''}${formatMoney(netBalance, globalCurrency)}
                                </span>
                            </div>
                        </div>
                        <div class="aura-child-sections-grid">
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-chart-pie"></span>Flujo Contable</h5>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Total Ingresos:</span><span class="aura-child-meta-val" style="color:#059669;">+${formatMoney(incomeAmount, globalCurrency)} (${incomeCount} mov.)</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Total Egresos:</span><span class="aura-child-meta-val" style="color:#dc2626;">-${formatMoney(expenseAmount, globalCurrency)} (${expenseCount} mov.)</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Volumen Total:</span><span class="aura-child-meta-val">${formatMoney(totalAmount, globalCurrency)}</span></div>
                            </div>
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-calendar-alt"></span>Trazabilidad Temporal</h5>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Primer Registro:</span><span class="aura-child-meta-val">${firstDate || '—'}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Último Registro:</span><span class="aura-child-meta-val">${latestDate || '—'}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Frecuencia Total:</span><span class="aura-child-meta-val">${count} transacciones</span></div>
                            </div>
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-admin-tools"></span>Acciones Rápidas</h5>
                                <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
                                    <a href="${searchUrl}" class="aura-btn aura-btn-secondary" style="font-size:12px;padding:6px 10px;justify-content:flex-start;">
                                        <span class="dashicons dashicons-search"></span><span>Ver en Búsqueda Avanzada</span>
                                    </a>
                                    <button type="button" class="aura-btn aura-btn-secondary btn-rename-quick" data-tag="${escaped}" style="font-size:12px;padding:6px 10px;justify-content:flex-start;">
                                        <span class="dashicons dashicons-edit"></span><span>Cargar en Panel de Renombrar</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>`;

            rows.push(parentRow);
            rows.push(childRow);
        });

        $body.html(rows.join(''));
    }

    function renderTableError(msg) {
        $('#tags-table-body').html(
            `<tr><td colspan="7" style="color:#ef4444;text-align:center;padding:24px;">` +
            `<span class="dashicons dashicons-warning" style="font-size:24px;width:24px;height:24px;vertical-align:middle;margin-right:6px;"></span>` +
            `${escHtml(msg || 'Error al cargar las etiquetas')}</td></tr>`
        );
    }

    /* ============================================================
       Nube de tags interactiva
       ============================================================ */
    function loadCloud() {
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_cloud',
            nonce:  auraTagsConfig.nonce,
        }).done(function (res) {
            const $cloud = $('#aura-tag-cloud');
            if (!res.success || !res.data.tags.length) {
                $cloud.html('<div style="text-align:center;width:100%;color:#94a3b8;font-size:13px;padding:20px 0;">' +
                    '<span class="dashicons dashicons-tag" style="font-size:24px;width:24px;height:24px;display:block;margin:0 auto 6px auto;"></span>' +
                    'No hay etiquetas registradas todavía.' +
                    '</div>');
                return;
            }

            const chips = res.data.tags.map(function (t) {
                const escaped = escHtml(t.name);
                const tipHtml = `<div class="aura-tip-card">` +
                    `<div class="aura-tip-card-header">` +
                    `<div class="aura-tip-avatar-large" style="background:rgba(59,130,246,0.2);color:#3b82f6;font-size:24px;font-weight:900;">#</div>` +
                    `<div class="aura-tip-info">` +
                    `<div class="aura-tip-title">#${escaped}</div>` +
                    `<div class="aura-tip-subtitle">Nube de Frecuencia</div>` +
                    `</div>` +
                    `</div>` +
                    `<div class="aura-tip-card-body">` +
                    `<div class="aura-tip-row"><span>📊 Frecuencia:</span><strong>${t.count} transacciones</strong></div>` +
                    `<div class="aura-tip-row"><span>⚡ Acción:</span><strong>Clic para cargar en formulario</strong></div>` +
                    `</div>` +
                    `</div>`;

                return `<a class="aura-cloud-tag" href="#" style="font-size:${t.size}px;" data-tag="${escaped}" data-tooltip="${escAttr(tipHtml)}">` +
                    `#${escaped} <small style="opacity:0.75;font-size:0.75em;">(${t.count})</small></a>`;
            });

            $cloud.html(chips.join(''));
        });
    }

    /* ============================================================
       Autocomplete en inputs de operaciones
       ============================================================ */
    function initAutocomplete() {
        const tagInputs = ['#rename-old', '#merge-source', '#merge-target'];
        tagInputs.forEach(function (sel) {
            $(sel).autocomplete({
                source: function (req, response) {
                    $.post(auraTagsConfig.ajaxUrl, {
                        action: 'aura_tags_autocomplete',
                        nonce:  auraTagsConfig.nonce,
                        term:   req.term,
                    }).done(function (res) {
                        response(res.success ? res.data : []);
                    });
                },
                minLength: 1,
            });
        });
    }

    /* ============================================================
       Eventos de Interacción
       ============================================================ */
    function bindEvents() {

        /* Toggle de Filas Expandibles (Universal Child Rows) */
        $(document).on('click', '.aura-row-toggle', function(e) {
            e.stopPropagation();
            const $btn = $(this);
            const $parentRow = $btn.closest('tr.aura-parent-row');
            const rowId = $parentRow.data('row-id');
            const $childRow = $('tr.aura-child-row[data-child-for="' + rowId + '"]');

            if ($childRow.is(':visible')) {
                $childRow.hide();
                $btn.text('+').attr('aria-expanded', 'false').removeClass('is-active');
                $parentRow.removeClass('is-expanded');
            } else {
                $childRow.show();
                $btn.text('−').attr('aria-expanded', 'true').addClass('is-active');
                $parentRow.addClass('is-expanded');
            }
        });

        /* Renombrar desde botón principal */
        $('#btn-rename-tag').on('click', function () {
            const oldName = $('#rename-old').val().trim();
            const newName = $('#rename-new').val().trim();
            if (!oldName || !newName) return showNotice('Por favor completa ambos campos (etiqueta actual y nuevo nombre).', 'error');
            doRename(oldName, newName);
        });

        /* Renombrar rápido desde tabla */
        $(document).on('click', '.btn-rename-quick', function (e) {
            e.preventDefault();
            const tag = $(this).data('tag');
            $('#rename-old').val(tag);
            $('#rename-new').val('').focus();
            $('html, body').animate({ scrollTop: $('#rename-old').offset().top - 80 }, 300);
        });

        /* Fusionar */
        $('#btn-merge-tags').on('click', function () {
            const source = $('#merge-source').val().trim();
            const target = $('#merge-target').val().trim();
            if (!source || !target) return showNotice('Por favor completa ambos campos para la fusión.', 'error');
            if (source === target) return showNotice('La etiqueta origen y destino deben ser distintas.', 'error');
            if (!confirm(auraTagsConfig.i18n.confirmMerge)) return;
            doMerge(source, target);
        });

        /* Eliminar → abrir modal universal */
        $(document).on('click', '.btn-delete-tag', function (e) {
            e.preventDefault();
            pendingDeleteTag = $(this).data('tag');
            $('#delete-tag-name').text('#' + pendingDeleteTag);
            $('#aura-delete-tag-modal').css('display', 'flex').hide().fadeIn(200);
        });

        $('#confirm-delete-tag').on('click', function () {
            if (!pendingDeleteTag) return;
            doDelete(pendingDeleteTag);
            closeDeleteModal();
        });

        $('#cancel-delete-tag, #btn-close-delete-modal').on('click', function () {
            closeDeleteModal();
        });

        // Cerrar modal al hacer clic en el backdrop exterior
        $(document).on('click', '#aura-delete-tag-modal', function (e) {
            if ($(e.target).is('#aura-delete-tag-modal')) {
                closeDeleteModal();
            }
        });

        // Cerrar modal con tecla Escape
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#aura-delete-tag-modal').is(':visible')) {
                closeDeleteModal();
            }
        });

        function closeDeleteModal() {
            pendingDeleteTag = null;
            $('#aura-delete-tag-modal').fadeOut(150);
        }

        /* Clic en chip de nube → cargar en rename-old y merge-source */
        $(document).on('click', '.aura-cloud-tag', function (e) {
            e.preventDefault();
            const tag = $(this).data('tag');
            $('#rename-old').val(tag);
            $('#merge-source').val(tag);
            $('html, body').animate({ scrollTop: $('#rename-old').offset().top - 80 }, 250);
        });

        /* Filtro en tiempo real de la tabla */
        $('#tags-table-filter').on('input', function () {
            const q = $(this).val().toLowerCase().trim();
            const filtered = allTags.filter(t => t.name.toLowerCase().includes(q));
            renderTable(filtered);
        });
    }

    /* ============================================================
       AJAX: Renombrar
       ============================================================ */
    function doRename(oldName, newName) {
        setLoading(true);
        $.post(auraTagsConfig.ajaxUrl, {
            action:   'aura_tags_rename',
            nonce:    auraTagsConfig.nonce,
            old_name: oldName,
            new_name: newName,
        }).done(function (res) {
            setLoading(false);
            if (res.success) {
                showNotice(res.data.message || auraTagsConfig.i18n.renameSuccess, 'success');
                reload();
            } else {
                showNotice(res.data && res.data.message, 'error');
            }
        }).fail(function () {
            setLoading(false);
            showNotice('Error de conexión con el servidor.', 'error');
        });
    }

    /* ============================================================
       AJAX: Fusionar
       ============================================================ */
    function doMerge(source, target) {
        setLoading(true);
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_merge',
            nonce:  auraTagsConfig.nonce,
            source: source,
            target: target,
        }).done(function (res) {
            setLoading(false);
            if (res.success) {
                showNotice(res.data.message || auraTagsConfig.i18n.mergeSuccess, 'success');
                reload();
            } else {
                showNotice(res.data && res.data.message, 'error');
            }
        }).fail(function () {
            setLoading(false);
            showNotice('Error de conexión con el servidor.', 'error');
        });
    }

    /* ============================================================
       AJAX: Eliminar
       ============================================================ */
    function doDelete(tag) {
        setLoading(true);
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_delete',
            nonce:  auraTagsConfig.nonce,
            name:   tag,
        }).done(function (res) {
            setLoading(false);
            if (res.success) {
                showNotice(res.data.message || auraTagsConfig.i18n.deleteSuccess, 'success');
                reload();
            } else {
                showNotice(res.data && res.data.message, 'error');
            }
        }).fail(function () {
            setLoading(false);
            showNotice('Error de conexión con el servidor.', 'error');
        });
    }

    /* ============================================================
       Utils
       ============================================================ */
    function reload() {
        $('#rename-old, #rename-new, #merge-source, #merge-target').val('');
        loadAllTags();
        loadCloud();
    }

    function showNotice(msg, type) {
        const isErr = (type === 'error');
        const bg = isErr ? '#fef2f2' : '#ecfdf5';
        const border = isErr ? '#ef4444' : '#10b981';
        const color = isErr ? '#991b1b' : '#065f46';
        const icon = isErr ? 'dashicons-warning' : 'dashicons-yes-alt';

        $('#aura-tags-notice')
            .css({
                'background': bg,
                'border-left': '4px solid ' + border,
                'color': color,
                'padding': '12px 16px',
                'border-radius': '6px',
                'margin-bottom': '20px',
                'font-size': '13.5px',
                'font-weight': '600',
                'display': 'flex',
                'align-items': 'center',
                'gap': '8px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.05)'
            })
            .html(`<span class="dashicons ${icon}" style="font-size:20px;width:20px;height:20px;"></span><span>${escHtml(msg || '')}</span>`)
            .fadeIn(200);

        setTimeout(function () { $('#aura-tags-notice').fadeOut(400); }, 5000);
    }

    function setLoading(on) {
        $('#btn-rename-tag, #btn-merge-tags, #confirm-delete-tag').prop('disabled', on);
    }

    function formatMoney(amount, currency) {
        const num = parseFloat(amount || 0);
        const curr = currency || 'USD';
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: curr,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(num);
        } catch (e) {
            return '$' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return String(str)
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

})(jQuery);
