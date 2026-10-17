/**
 * Tags Management — Aura Business Suite
 * Fase 5, Item 5.2
 * CRUD Robusto, Mejorado y Estandarizado de Etiquetas Financieras
 */
/* global auraTagsConfig, jQuery */
(function ($) {
    'use strict';

    /* ============================================================
       Estado Global del Módulo
       ============================================================ */
    let allTags = [];             // Listado completo obtenido del servidor
    let filteredTags = [];        // Listado tras aplicar búsqueda y filtros de tab
    let selectedTags = new Set(); // Etiquetas seleccionadas con checkboxes
    let globalCurrency = auraTagsConfig.currency || 'USD';

    let currentPage = 1;
    let perPage = 25;
    let currentFilter = 'all';    // 'all', 'active', 'empty', 'surplus', 'deficit'
    let currentSort = 'count_desc';

    let pendingDeleteTag = null;
    let pendingMergeSource = null;

    /* ============================================================
       Inicialización
       ============================================================ */
    $(function () {
        // Mover modales al <body> para evitar stacking contexts / overflow de contenedores del tema
        $('.aura-modal-overlay').appendTo('body');
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
       Cargar Datos de Etiquetas desde Servidor
       ============================================================ */
    function loadAllTags(callback) {
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_get_all',
            nonce:  auraTagsConfig.nonce,
        }).done(function (res) {
            if (res.success) {
                allTags = res.data.tags || [];
                globalCurrency = res.data.currency || globalCurrency;
                updateKpis(allTags);
                updateTabCounts(allTags);
                applyFiltersAndRender();
                if (typeof callback === 'function') callback();
            } else {
                renderTableError(res.data && res.data.message);
            }
        }).fail(function () {
            renderTableError('Error de comunicación con el servidor.');
        });
    }

    /* ============================================================
       Actualización de KPIs y Estadísticas Contables
       ============================================================ */
    function updateKpis(tags) {
        if (!tags) return;
        const totalTags = tags.length;
        let totalUsages = 0;
        let totalVolume = 0;
        let totalBalance = 0;
        let activeCount = 0;
        let emptyCount = 0;

        tags.forEach(t => {
            const cnt = parseInt(t.count || 0, 10);
            totalUsages += cnt;
            totalVolume += parseFloat(t.total_amount || 0);
            totalBalance += parseFloat(t.net_balance || 0);
            if (cnt > 0) activeCount++;
            else emptyCount++;
        });

        $('#kpi-total-tags').text(new Intl.NumberFormat().format(totalTags));
        $('#kpi-total-usages').text(new Intl.NumberFormat().format(totalUsages));
        $('#kpi-total-volume').text(formatMoney(totalVolume, globalCurrency));
        
        const $balanceEl = $('#kpi-total-balance');
        $balanceEl.text((totalBalance >= 0 ? '+' : '') + formatMoney(totalBalance, globalCurrency));
        $balanceEl.css('color', totalBalance >= 0 ? '#059669' : '#dc2626');

        $('#kpi-tags-breakdown').text(`${activeCount} con movimientos · ${emptyCount} sin usar`);
        $('#kpi-balance-status').text(totalBalance >= 0 ? 'Superávit global consolidado' : 'Déficit global consolidado');
        $('#table-tags-counter').text(`${totalTags} etiquetas`);
    }

    /* ============================================================
       Actualización de Conteos en Tabs de Filtro
       ============================================================ */
    function updateTabCounts(tags) {
        if (!tags) return;
        let allCount = tags.length;
        let activeCount = 0;
        let emptyCount = 0;
        let surplusCount = 0;
        let deficitCount = 0;

        tags.forEach(t => {
            const cnt = parseInt(t.count || 0, 10);
            const bal = parseFloat(t.net_balance || 0);
            if (cnt > 0) {
                activeCount++;
                if (bal > 0) surplusCount++;
                else if (bal < 0) deficitCount++;
            } else {
                emptyCount++;
            }
        });

        $('#pill-count-all').text(allCount);
        $('#pill-count-active').text(activeCount);
        $('#pill-count-empty').text(emptyCount);
        $('#pill-count-surplus').text(surplusCount);
        $('#pill-count-deficit').text(deficitCount);
    }

    /* ============================================================
       Filtrado, Ordenamiento y Renderizado Dinámico
       ============================================================ */
    function applyFiltersAndRender() {
        const query = ($('#tags-table-filter').val() || '').toLowerCase().trim();

        // 1. Filtrar por Tab
        let list = allTags.filter(t => {
            const cnt = parseInt(t.count || 0, 10);
            const bal = parseFloat(t.net_balance || 0);

            if (currentFilter === 'active') return cnt > 0;
            if (currentFilter === 'empty') return cnt === 0;
            if (currentFilter === 'surplus') return cnt > 0 && bal > 0;
            if (currentFilter === 'deficit') return cnt > 0 && bal < 0;
            return true;
        });

        // 2. Filtrar por búsqueda de texto
        if (query) {
            list = list.filter(t => {
                const name = (t.name || '').toLowerCase();
                const desc = (t.description || '').toLowerCase();
                return name.includes(query) || desc.includes(query);
            });
        }

        // 3. Ordenar
        list.sort((a, b) => {
            const cntA = parseInt(a.count || 0, 10);
            const cntB = parseInt(b.count || 0, 10);
            const volA = parseFloat(a.total_amount || 0);
            const volB = parseFloat(b.total_amount || 0);
            const balA = parseFloat(a.net_balance || 0);
            const balB = parseFloat(b.net_balance || 0);
            const nameA = (a.name || '').toLowerCase();
            const nameB = (b.name || '').toLowerCase();
            const dateA = a.latest_date || '';
            const dateB = b.latest_date || '';

            switch (currentSort) {
                case 'count_asc':
                    return cntA - cntB || nameA.localeCompare(nameB);
                case 'name_asc':
                    return nameA.localeCompare(nameB);
                case 'name_desc':
                    return nameB.localeCompare(nameA);
                case 'volume_desc':
                    return volB - volA;
                case 'balance_desc':
                    return balB - balA;
                case 'balance_asc':
                    return balA - balB;
                case 'date_desc':
                    return dateB.localeCompare(dateA) || cntB - cntA;
                case 'count_desc':
                default:
                    return cntB - cntA || nameA.localeCompare(nameB);
            }
        });

        filteredTags = list;

        // 4. Calcular Paginación
        const totalItems = filteredTags.length;
        const totalPages = perPage === Infinity ? 1 : Math.ceil(totalItems / perPage) || 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }

        const startIndex = perPage === Infinity ? 0 : (currentPage - 1) * perPage;
        const endIndex = perPage === Infinity ? totalItems : Math.min(startIndex + perPage, totalItems);
        const pagedTags = filteredTags.slice(startIndex, endIndex);

        // 5. Renderizar
        renderTableRows(pagedTags, startIndex);
        renderPagination(totalItems, startIndex, endIndex, totalPages);
        updateBulkBar();
        initPortalTooltip();
    }

    /* ============================================================
       Renderizar Filas de la Tabla (Principal + Child Row)
       ============================================================ */
    function renderTableRows(tags, startIndex) {
        const $body = $('#tags-table-body');

        if (!tags || tags.length === 0) {
            $body.html(
                '<tr><td colspan="8" style="text-align:center; padding: 48px 20px;">' +
                '<div class="aura-empty-state-box" style="padding:0;">' +
                '<span class="dashicons dashicons-tag" style="font-size:38px;width:38px;height:38px;color:#94a3b8;margin-bottom:8px;"></span>' +
                '<h4 style="margin:0 0 6px 0; color:#334155; font-size:15px;">No se encontraron etiquetas</h4>' +
                '<p style="color:#64748b;margin:0;font-size:13px;">' +
                (allTags.length === 0 ? 'Aún no hay etiquetas registradas. ¡Haz clic en "+ Nueva Etiqueta" para crear la primera!' : 'No hay etiquetas que coincidan con los filtros o la búsqueda actual.') +
                '</p>' +
                '</div></td></tr>'
            );
            return;
        }

        const rows = [];

        tags.forEach(function (tag, i) {
            const globalIndex = startIndex + i;
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
            const color = tag.color || '#3b82f6';
            const description = escHtml(tag.description || '');
            const isChecked = selectedTags.has(tag.name) ? 'checked' : '';
            const rowId = 'tag-row-' + globalIndex;

            const searchUrl = auraTagsConfig.searchUrl ? `${auraTagsConfig.searchUrl}&search_tag=${encodeURIComponent(tag.name)}` : '#';

            // Tooltips
            const tagTipHtml = `<div class="aura-tip-card">` +
                `<div class="aura-tip-card-header">` +
                `<div class="aura-tip-avatar-large" style="background:${color}22;color:${color};font-size:22px;font-weight:900;">#</div>` +
                `<div class="aura-tip-info">` +
                `<div class="aura-tip-title">#${escaped}</div>` +
                `<div class="aura-tip-subtitle">${description ? description.substring(0, 45) : 'Taxonomía Transversal'}</div>` +
                `</div>` +
                `</div>` +
                `<div class="aura-tip-card-body">` +
                `<div class="aura-tip-row"><span>Movimientos:</span><strong>${count} transacciones</strong></div>` +
                `<div class="aura-tip-row"><span>🟢 Ingresos:</span><strong>+${formatMoney(incomeAmount, globalCurrency)} (${incomeCount})</strong></div>` +
                `<div class="aura-tip-row"><span>🔴 Egresos:</span><strong>-${formatMoney(expenseAmount, globalCurrency)} (${expenseCount})</strong></div>` +
                `<div class="aura-tip-row"><span>Balance Neto:</span><strong style="color:${netBalance >= 0 ? '#10b981' : '#ef4444'}">${netBalance >= 0 ? '+' : ''}${formatMoney(netBalance, globalCurrency)}</strong></div>` +
                `</div>` +
                `</div>`;

            // Fila Principal
            const parentRow = `<tr class="aura-parent-row" data-row-id="${rowId}" data-tag="${escaped}">
                <td style="text-align: center;">
                    <input type="checkbox" class="aura-checkbox tag-row-checkbox" data-tag="${escaped}" ${isChecked}>
                </td>
                <td style="text-align: center;">
                    <div class="aura-id-toggle-wrap">
                        <button type="button" class="aura-row-toggle" aria-expanded="false" title="Desplegar desglose contable">+</button>
                        <span class="aura-txn-id-pill">#${globalIndex + 1}</span>
                    </div>
                </td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <span class="aura-tag-chip" style="background:${color}18; color:${color}; border-color:${color}44;" data-tooltip="${escAttr(tagTipHtml)}">
                            <span class="dashicons dashicons-tag" style="font-size:12px;width:12px;height:12px;"></span>
                            #${escaped}
                        </span>
                        ${description ? `<small style="color:#64748b; font-size:11.5px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:260px;" title="${description}">${description}</small>` : ''}
                    </div>
                </td>
                <td style="text-align: center;">
                    ${count > 0 ? `
                        <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                            <span class="aura-badge aura-badge-blue" style="font-weight:700;font-size:11px;padding:2px 7px;">
                                ${count} ${count === 1 ? 'mov.' : 'movs.'}
                            </span>
                            <div style="font-size:10.5px;color:#64748b;display:flex;gap:6px;">
                                <span style="color:#059669;font-weight:600;">🟢 ${incomeCount}</span>
                                <span style="color:#dc2626;font-weight:600;">🔴 ${expenseCount}</span>
                            </div>
                        </div>
                    ` : `
                        <span class="aura-badge" style="background:#f1f5f9; color:#94a3b8; font-size:11px;">0 usos</span>
                    `}
                </td>
                <td style="text-align: right;">
                    <span class="aura-amt-total" style="font-weight:700;color:#0f172a;font-size:13px;">
                        ${formatMoney(totalAmount, globalCurrency)}
                    </span>
                </td>
                <td style="text-align: right;">
                    <span class="aura-amt-balance ${netBalance >= 0 ? 'is-positive' : 'is-negative'}" style="font-weight:800;font-size:13px; color:${netBalance >= 0 ? '#059669' : '#dc2626'};">
                        ${netBalance >= 0 ? '+' : ''}${formatMoney(netBalance, globalCurrency)}
                    </span>
                </td>
                <td style="text-align: center;" class="aura-col-tablet-hidden">
                    <span class="aura-date-badge">${latestDate ? latestDate : '<span style="color:#94a3b8;">—</span>'}</span>
                </td>
                <td style="text-align: right;">
                    <div class="aura-table-actions-cell" style="display:inline-flex;gap:4px;justify-content:flex-end;">
                        <button type="button" class="aura-btn-icon btn-view-txns" data-tag="${escaped}" title="Ver transacciones vinculadas" aria-label="Ver transacciones">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        ${auraTagsConfig.canEdit ? `
                            <button type="button" class="aura-btn-icon aura-btn-icon--edit btn-edit-tag" data-tag="${escaped}" title="Editar nombre, color y descripción" aria-label="Editar">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="aura-btn-icon btn-merge-single" data-tag="${escaped}" title="Fusionar con otra etiqueta" aria-label="Fusionar">
                                <span class="dashicons dashicons-randomize"></span>
                            </button>
                        ` : ''}
                        ${auraTagsConfig.canDelete ? `
                            <button type="button" class="aura-btn-icon aura-btn-icon--danger btn-delete-tag" data-tag="${escaped}" data-count="${count}" title="Eliminar etiqueta" aria-label="Eliminar">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>`;

            // Fila Hija Expandible
            const childRow = `<tr class="aura-child-row" data-child-for="${rowId}" style="display:none;">
                <td colspan="8">
                    <div class="aura-child-card">
                        <div class="aura-child-hero-bar">
                            <div class="aura-child-hero-left">
                                <span class="aura-type-pill" style="background:${color}22;color:${color};font-weight:900;">#</span>
                                <div>
                                    <h4 class="aura-child-hero-title">Etiqueta: #${escaped}</h4>
                                    <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                        ${description || 'Taxonomía contable sin descripción adicional registrada.'}
                                    </div>
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
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Volumen Acumulado:</span><span class="aura-child-meta-val">${formatMoney(totalAmount, globalCurrency)}</span></div>
                            </div>
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-calendar-alt"></span>Trazabilidad Temporal</h5>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Primer Registro:</span><span class="aura-child-meta-val">${firstDate || 'Sin movimientos'}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Último Registro:</span><span class="aura-child-meta-val">${latestDate || 'Sin movimientos'}</span></div>
                                <div class="aura-child-meta-item"><span class="aura-child-meta-label">Frecuencia Total:</span><span class="aura-child-meta-val">${count} transacciones</span></div>
                            </div>
                            <div class="aura-child-section">
                                <h5 class="aura-child-section-title"><span class="dashicons dashicons-admin-tools"></span>Operaciones Directas</h5>
                                <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px;">
                                    <button type="button" class="aura-btn aura-btn-secondary btn-view-txns" data-tag="${escaped}" style="font-size:12px;padding:6px 10px;justify-content:flex-start;">
                                        <span class="dashicons dashicons-visibility"></span><span>Ver Transacciones Asociadas</span>
                                    </button>
                                    <a href="${searchUrl}" target="_blank" class="aura-btn aura-btn-secondary" style="font-size:12px;padding:6px 10px;justify-content:flex-start;">
                                        <span class="dashicons dashicons-search"></span><span>Abrir en Búsqueda Avanzada</span>
                                    </a>
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

    /* ============================================================
       Renderizado de Paginación
       ============================================================ */
    function renderPagination(totalItems, startIndex, endIndex, totalPages) {
        const $info = $('#pagination-info-text');
        const $ctrls = $('#pagination-buttons');

        if (totalItems === 0) {
            $info.text('0 etiquetas');
            $ctrls.empty();
            return;
        }

        $info.text(`Mostrando ${startIndex + 1} - ${endIndex} de ${totalItems} etiquetas`);

        if (totalPages <= 1) {
            $ctrls.empty();
            return;
        }

        let btnsHtml = [];

        // Botón Anterior
        btnsHtml.push(`<button type="button" class="aura-page-btn ${currentPage === 1 ? 'is-disabled' : ''}" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>&laquo; Ant</button>`);

        // Botones Numéricos
        const maxButtons = 5;
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        if (startPage > 1) {
            btnsHtml.push(`<button type="button" class="aura-page-btn" data-page="1">1</button>`);
            if (startPage > 2) btnsHtml.push(`<span class="aura-page-dots">...</span>`);
        }

        for (let p = startPage; p <= endPage; p++) {
            btnsHtml.push(`<button type="button" class="aura-page-btn ${p === currentPage ? 'is-active' : ''}" data-page="${p}">${p}</button>`);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) btnsHtml.push(`<span class="aura-page-dots">...</span>`);
            btnsHtml.push(`<button type="button" class="aura-page-btn" data-page="${totalPages}">${totalPages}</button>`);
        }

        // Botón Siguiente
        btnsHtml.push(`<button type="button" class="aura-page-btn ${currentPage === totalPages ? 'is-disabled' : ''}" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>Sig &raquo;</button>`);

        $ctrls.html(btnsHtml.join(''));
    }

    /* ============================================================
       Barra de Acciones Masivas
       ============================================================ */
    function updateBulkBar() {
        const count = selectedTags.size;
        const $bar = $('#aura-tags-bulk-bar');
        if (count > 0) {
            $('#bulk-selected-count').text(count);
            $bar.fadeIn(150);
        } else {
            $bar.fadeOut(150);
        }

        // Sincronizar checkbox de select all
        const visibleCheckboxes = $('.tag-row-checkbox');
        const checkedVisible = $('.tag-row-checkbox:checked');
        const $selectAll = $('#tags-select-all');

        if (visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedVisible.length) {
            $selectAll.prop('checked', true).prop('indeterminate', false);
        } else if (checkedVisible.length > 0) {
            $selectAll.prop('checked', false).prop('indeterminate', true);
        } else {
            $selectAll.prop('checked', false).prop('indeterminate', false);
        }
    }

    /* ============================================================
       Nube de Etiquetas Interactiva
       ============================================================ */
    function loadCloud() {
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_cloud',
            nonce:  auraTagsConfig.nonce,
        }).done(function (res) {
            const $cloud = $('#aura-tag-cloud');
            if (!res.success || !res.data.tags || !res.data.tags.length) {
                $cloud.html(
                    '<div style="text-align:center;width:100%;color:#94a3b8;font-size:13px;padding:24px 0;">' +
                    '<span class="dashicons dashicons-tag" style="font-size:24px;width:24px;height:24px;display:block;margin:0 auto 6px auto;"></span>' +
                    'No hay etiquetas registradas todavía.' +
                    '</div>'
                );
                return;
            }

            const chips = res.data.tags.map(function (t) {
                const escaped = escHtml(t.name);
                const color = t.color || '#3b82f6';
                const tipHtml = `<div class="aura-tip-card">` +
                    `<div class="aura-tip-card-header">` +
                    `<div class="aura-tip-avatar-large" style="background:${color}22;color:${color};font-size:22px;font-weight:900;">#</div>` +
                    `<div class="aura-tip-info">` +
                    `<div class="aura-tip-title">#${escaped}</div>` +
                    `<div class="aura-tip-subtitle">${t.description ? escHtml(t.description) : 'Nube de Frecuencia'}</div>` +
                    `</div>` +
                    `</div>` +
                    `<div class="aura-tip-card-body">` +
                    `<div class="aura-tip-row"><span>Movimientos:</span><strong>${t.count} transacciones</strong></div>` +
                    `<div class="aura-tip-row"><span>Acción:</span><strong>Haz clic para filtrar la tabla</strong></div>` +
                    `</div>` +
                    `</div>`;

                return `<a class="aura-cloud-tag" href="#" style="font-size:${t.size}px; border-color:${color}44; color:${color};" data-tag="${escaped}" data-tooltip="${escAttr(tipHtml)}">` +
                    `#${escaped} <small style="opacity:0.75;font-size:0.75em;">(${t.count})</small></a>`;
            });

            $cloud.html(chips.join(''));
            initPortalTooltip();
        });
    }

    /* ============================================================
       Autocomplete para Renombrar y Fusionar
       ============================================================ */
    function initAutocomplete() {
        const tagInputs = ['#rename-old', '#merge-source', '#merge-target', '#modal-merge-target', '#bulk-merge-target'];
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
       Eventos de Interacción del Módulo
       ============================================================ */
    function bindEvents() {

        // ── Tabs de Filtro ───────────────────────────────────────
        $('.aura-filter-pill').on('click', function () {
            $('.aura-filter-pill').removeClass('is-active');
            $(this).addClass('is-active');
            currentFilter = $(this).data('filter');
            currentPage = 1;
            applyFiltersAndRender();
        });

        // ── Buscador en Tiempo Real ──────────────────────────────
        $('#tags-table-filter').on('input', function () {
            currentPage = 1;
            applyFiltersAndRender();
        });

        // ── Ordenamiento por Selector ────────────────────────────
        $('#tags-table-sort').on('change', function () {
            currentSort = $(this).val();
            currentPage = 1;
            applyFiltersAndRender();
        });

        // ── Items por Página ─────────────────────────────────────
        $('#tags-table-per-page').on('change', function () {
            const val = $(this).val();
            perPage = (val === 'all') ? Infinity : parseInt(val, 10);
            currentPage = 1;
            applyFiltersAndRender();
        });

        // ── Paginación Clics ─────────────────────────────────────
        $(document).on('click', '.aura-page-btn', function (e) {
            e.preventDefault();
            const p = parseInt($(this).data('page'), 10);
            if (!isNaN(p)) {
                currentPage = p;
                applyFiltersAndRender();
                $('html, body').animate({ scrollTop: $('#aura-tags-table').offset().top - 80 }, 200);
            }
        });

        // ── Toggle de Filas Expandibles (+ / -) ───────────────────
        $(document).on('click', '.aura-row-toggle', function (e) {
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

        // ── Selección Múltiple (Checkboxes) ───────────────────────
        $('#tags-select-all').on('change', function () {
            const checked = $(this).is(':checked');
            $('.tag-row-checkbox').each(function () {
                const tag = $(this).data('tag');
                $(this).prop('checked', checked);
                if (checked) {
                    selectedTags.add(tag);
                } else {
                    selectedTags.delete(tag);
                }
            });
            updateBulkBar();
        });

        $(document).on('change', '.tag-row-checkbox', function () {
            const tag = $(this).data('tag');
            if ($(this).is(':checked')) {
                selectedTags.add(tag);
            } else {
                selectedTags.delete(tag);
            }
            updateBulkBar();
        });

        $('#btn-bulk-deselect').on('click', function () {
            selectedTags.clear();
            $('.tag-row-checkbox').prop('checked', false);
            $('#tags-select-all').prop('checked', false).prop('indeterminate', false);
            updateBulkBar();
        });

        // ── Toggle Nube de Tags ───────────────────────────────────
        $('#btn-toggle-cloud').on('click', function () {
            $('#aura-tags-collapsible-panels').slideToggle(200);
        });

        $('#btn-cloud-reload').on('click', function () {
            loadCloud();
        });

        // ── Clic en chip de nube → filtra o carga en inputs ───────
        $(document).on('click', '.aura-cloud-tag', function (e) {
            e.preventDefault();
            const tag = $(this).data('tag');
            $('#tags-table-filter').val(tag).trigger('input');
            $('#rename-old').val(tag);
            $('#merge-source').val(tag);
        });

        // ── Helpers de Modales Robustos y Accesibles ─────────────
        function showModal(selector) {
            const $m = $(selector);
            $m.addClass('is-active');
            if ($m.length) {
                $m[0].style.setProperty('display', 'flex', 'important');
                $m[0].style.setProperty('z-index', '9999999', 'important');
                $m[0].style.setProperty('visibility', 'visible', 'important');
                $m[0].style.setProperty('pointer-events', 'auto', 'important');
                requestAnimationFrame(function () {
                    $m[0].style.setProperty('opacity', '1', 'important');
                });
            }
        }

        function hideModal(selector) {
            const $m = $(selector);
            if ($m.length) {
                $m[0].style.setProperty('opacity', '0', 'important');
                setTimeout(function () {
                    $m.removeClass('is-active');
                    $m[0].style.setProperty('display', 'none', 'important');
                }, 180);
            }
        }

        // Cierre universal con tecla Escape
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $('.aura-modal-overlay.is-active').each(function () {
                    hideModal(this);
                });
            }
        });

        // Cierre al hacer clic en el backdrop / overlay
        $(document).on('click', '.aura-modal-overlay', function (e) {
            if (e.target === this) {
                hideModal(this);
            }
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 1: CREAR ETIQUETA
        // ══════════════════════════════════════════════════════════
        $(document).on('click', '#btn-open-create-modal', function () {
            $('#create-tag-name').val('');
            $('#create-tag-desc').val('');
            setCreateColor('#3b82f6');
            $('#create-tag-preview-text').text('#etiqueta');
            showModal('#aura-create-tag-modal');
            setTimeout(function () {
                $('#create-tag-name').focus();
            }, 60);
        });

        $(document).on('click', '#btn-close-create-modal, #btn-cancel-create', function () {
            hideModal('#aura-create-tag-modal');
        });

        $('#create-color-swatches .aura-color-swatch').on('click', function () {
            setCreateColor($(this).data('color'));
        });

        $('#create-tag-color-custom').on('input change', function () {
            setCreateColor($(this).val());
        });

        function setCreateColor(col) {
            $('#create-tag-color').val(col);
            $('#create-tag-color-custom').val(col);
            $('#create-color-swatches .aura-color-swatch').removeClass('is-selected');
            $(`#create-color-swatches .aura-color-swatch[data-color="${col}"]`).addClass('is-selected');
            $('#create-tag-preview').css({
                'background': col + '22',
                'color': col,
                'border-color': col + '55',
                'box-shadow': '0 4px 14px ' + col + '25'
            });
        }

        $('#create-tag-name').on('input', function () {
            const val = $(this).val().trim().replace(/#/g, '');
            $('#create-tag-preview-text').text('#' + (val || 'etiqueta'));
        }).on('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $('#btn-submit-create-tag').click();
            }
        });

        $('#btn-submit-create-tag').on('click', function () {
            const name = $('#create-tag-name').val().trim();
            const color = $('#create-tag-color').val();
            const desc = $('#create-tag-desc').val().trim();

            if (!name) {
                return showNotice('Por favor ingresa un nombre para la etiqueta.', 'error');
            }

            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update aura-spin"></span> Guardando...');

            $.post(auraTagsConfig.ajaxUrl, {
                action:      'aura_tags_create',
                nonce:       auraTagsConfig.nonce,
                name:        name,
                color:       color,
                description: desc
            }).done(function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.createSuccess, 'success');
                    hideModal('#aura-create-tag-modal');
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(origHtml);
                showNotice('Error al crear la etiqueta.', 'error');
            });
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 2: EDITAR ETIQUETA
        // ══════════════════════════════════════════════════════════
        $(document).on('click', '.btn-edit-tag', function () {
            const tag = $(this).data('tag');
            const item = allTags.find(t => t.name.toLowerCase() === String(tag).toLowerCase()) || {};

            $('#edit-tag-id').val(item.id || 0);
            $('#edit-tag-old-name').val(item.name || tag);
            $('#edit-tag-name').val(item.name || tag);
            $('#edit-tag-desc').val(item.description || '');
            setEditColor(item.color || '#3b82f6');
            $('#edit-tag-preview-text').text('#' + (item.name || tag));

            showModal('#aura-edit-tag-modal');
            setTimeout(function () {
                $('#edit-tag-name').focus();
            }, 60);
        });

        $(document).on('click', '#btn-close-edit-modal, #btn-cancel-edit', function () {
            hideModal('#aura-edit-tag-modal');
        });

        $('#edit-color-swatches .aura-color-swatch').on('click', function () {
            setEditColor($(this).data('color'));
        });

        $('#edit-tag-color-custom').on('input change', function () {
            setEditColor($(this).val());
        });

        function setEditColor(col) {
            $('#edit-tag-color').val(col);
            $('#edit-tag-color-custom').val(col);
            $('#edit-color-swatches .aura-color-swatch').removeClass('is-selected');
            $(`#edit-color-swatches .aura-color-swatch[data-color="${col}"]`).addClass('is-selected');
            $('#edit-tag-preview').css({
                'background': col + '22',
                'color': col,
                'border-color': col + '55',
                'box-shadow': '0 4px 14px ' + col + '25'
            });
        }

        $('#edit-tag-name').on('input', function () {
            const val = $(this).val().trim().replace(/#/g, '');
            $('#edit-tag-preview-text').text('#' + (val || 'etiqueta'));
        }).on('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $('#btn-submit-edit-tag').click();
            }
        });

        $('#btn-submit-edit-tag').on('click', function () {
            const id = $('#edit-tag-id').val();
            const oldName = $('#edit-tag-old-name').val();
            const newName = $('#edit-tag-name').val().trim();
            const color = $('#edit-tag-color').val();
            const desc = $('#edit-tag-desc').val().trim();
            const syncTxns = $('#edit-tag-sync-txns').is(':checked') ? 1 : 0;

            if (!newName) {
                return showNotice('El nombre no puede estar vacío.', 'error');
            }

            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update aura-spin"></span> Guardando...');

            $.post(auraTagsConfig.ajaxUrl, {
                action:              'aura_tags_update',
                nonce:               auraTagsConfig.nonce,
                id:                  id,
                old_name:            oldName,
                new_name:            newName,
                color:               color,
                description:         desc,
                update_transactions: syncTxns
            }).done(function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.updateSuccess, 'success');
                    hideModal('#aura-edit-tag-modal');
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(origHtml);
                showNotice('Error al actualizar la etiqueta.', 'error');
            });
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 3: FUSIONAR ETIQUETA (INDIVIDUAL)
        // ══════════════════════════════════════════════════════════
        $(document).on('click', '.btn-merge-single', function () {
            pendingMergeSource = $(this).data('tag');
            $('#modal-merge-source').val('#' + pendingMergeSource);
            $('#modal-merge-target').val('').focus();
            showModal('#aura-merge-modal');
        });

        $(document).on('click', '#btn-close-merge-modal, #btn-cancel-merge', function () {
            pendingMergeSource = null;
            hideModal('#aura-merge-modal');
        });

        $('#modal-merge-target').on('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $('#btn-confirm-merge').click();
            }
        });

        $('#btn-confirm-merge').on('click', function () {
            const target = $('#modal-merge-target').val().trim().replace(/#/g, '');
            if (!pendingMergeSource || !target) {
                return showNotice('Por favor indica la etiqueta destino.', 'error');
            }
            if (pendingMergeSource.toLowerCase() === target.toLowerCase()) {
                return showNotice('La etiqueta origen y destino deben ser distintas.', 'error');
            }

            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update aura-spin"></span> Fusionando...');

            $.post(auraTagsConfig.ajaxUrl, {
                action: 'aura_tags_merge',
                nonce:  auraTagsConfig.nonce,
                source: pendingMergeSource,
                target: target
            }).done(function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.mergeSuccess, 'success');
                    hideModal('#aura-merge-modal');
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(origHtml);
                showNotice('Error al fusionar las etiquetas.', 'error');
            });
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 4: FUSIÓN MASIVA (BULK MERGE)
        // ══════════════════════════════════════════════════════════
        $('#btn-bulk-merge').on('click', function () {
            if (selectedTags.size === 0) return;

            const chips = Array.from(selectedTags).map(t => {
                return `<span class="aura-tag-chip" style="font-size:12px; font-weight:700;">#${escHtml(t)}</span>`;
            });

            $('#bulk-merge-chips-container').html(chips.join(''));
            $('#bulk-merge-target').val('').focus();
            showModal('#aura-bulk-merge-modal');
        });

        $(document).on('click', '#btn-close-bulk-merge-modal, #btn-cancel-bulk-merge', function () {
            hideModal('#aura-bulk-merge-modal');
        });

        $('#bulk-merge-target').on('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $('#btn-confirm-bulk-merge').click();
            }
        });

        $('#btn-confirm-bulk-merge').on('click', function () {
            const target = $('#bulk-merge-target').val().trim().replace(/#/g, '');
            if (!target) {
                return showNotice('Por favor indica la etiqueta destino.', 'error');
            }

            const sources = Array.from(selectedTags);
            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update aura-spin"></span> Fusionando...');

            $.post(auraTagsConfig.ajaxUrl, {
                action:  'aura_tags_bulk_merge',
                nonce:   auraTagsConfig.nonce,
                sources: sources,
                target:  target
            }).done(function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.bulkMergeSuccess, 'success');
                    hideModal('#aura-bulk-merge-modal');
                    selectedTags.clear();
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(origHtml);
                showNotice('Error en la fusión masiva.', 'error');
            });
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 5: ELIMINACIÓN INDIVIDUAL
        // ══════════════════════════════════════════════════════════
        $(document).on('click', '.btn-delete-tag', function () {
            pendingDeleteTag = $(this).data('tag');
            const count = parseInt($(this).data('count') || 0, 10);
            $('#delete-tag-name').text('#' + pendingDeleteTag);
            
            if (count > 0) {
                $('#delete-tag-impact-text').text(`Esta acción desvinculará esta etiqueta de las ${count} transacciones históricas donde se encuentra asignada y la eliminará del catálogo.`);
            } else {
                $('#delete-tag-impact-text').text('Esta etiqueta no tiene transacciones asociadas. Se eliminará del catálogo de taxonomías.');
            }

            showModal('#aura-delete-tag-modal');
        });

        $(document).on('click', '#btn-close-delete-modal, #cancel-delete-tag', function () {
            pendingDeleteTag = null;
            hideModal('#aura-delete-tag-modal');
        });

        $('#confirm-delete-tag').on('click', function () {
            if (!pendingDeleteTag) return;
            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update aura-spin"></span> Eliminando...');

            $.post(auraTagsConfig.ajaxUrl, {
                action: 'aura_tags_delete',
                nonce:  auraTagsConfig.nonce,
                name:   pendingDeleteTag
            }).done(function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.deleteSuccess, 'success');
                    hideModal('#aura-delete-tag-modal');
                    selectedTags.delete(pendingDeleteTag);
                    pendingDeleteTag = null;
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(origHtml);
                showNotice('Error al eliminar la etiqueta.', 'error');
            });
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 6: ELIMINACIÓN MASIVA (BULK DELETE)
        // ══════════════════════════════════════════════════════════
        $('#btn-bulk-delete').on('click', function () {
            if (selectedTags.size === 0) return;
            const count = selectedTags.size;
            $('#bulk-delete-count').text(count);

            const chips = Array.from(selectedTags).map(t => {
                return `<span class="aura-tag-chip" style="font-size:12px; font-weight:700; color:#dc2626; border-color:#fca5a5; background:#fee2e2;">#${escHtml(t)}</span>`;
            });

            $('#bulk-delete-chips-container').html(chips.join(''));
            showModal('#aura-bulk-delete-modal');
        });

        $(document).on('click', '#btn-close-bulk-delete-modal, #btn-cancel-bulk-delete', function () {
            hideModal('#aura-bulk-delete-modal');
        });

        $('#btn-confirm-bulk-delete').on('click', function () {
            const tags = Array.from(selectedTags);
            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update aura-spin"></span> Eliminando...');

            $.post(auraTagsConfig.ajaxUrl, {
                action: 'aura_tags_bulk_delete',
                nonce:  auraTagsConfig.nonce,
                names:  tags
            }).done(function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.bulkDeleteSuccess, 'success');
                    hideModal('#aura-bulk-delete-modal');
                    selectedTags.clear();
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false).html(origHtml);
                showNotice('Error al eliminar las etiquetas.', 'error');
            });
        });

        // ══════════════════════════════════════════════════════════
        // MODAL 7: VISOR RÁPIDO DE TRANSACCIONES VINCULADAS
        // ══════════════════════════════════════════════════════════
        $(document).on('click', '.btn-view-txns', function () {
            const tag = $(this).data('tag');
            openTransactionsModal(tag);
        });

        $(document).on('click', '#btn-close-txns-modal, #btn-done-txns-modal', function () {
            hideModal('#aura-tag-txns-modal');
        });

        function openTransactionsModal(tag) {
            $('#txns-modal-tag-name').text('#' + tag);
            $('#txns-modal-search-link').attr('href', `${auraTagsConfig.searchUrl}&search_tag=${encodeURIComponent(tag)}`);
            $('#txns-modal-tbody').html(
                '<tr><td colspan="7" style="text-align: center; padding: 40px 20px;">' +
                '<span class="spinner is-active" style="float:none; margin:0 auto 10px auto;"></span>' +
                '<p style="color:#64748b; margin:0; font-size:13px;">Cargando transacciones vinculadas...</p>' +
                '</td></tr>'
            );
            showModal('#aura-tag-txns-modal');

            $.post(auraTagsConfig.ajaxUrl, {
                action: 'aura_tags_get_transactions',
                nonce:  auraTagsConfig.nonce,
                tag:    tag
            }).done(function (res) {
                if (res.success) {
                    renderTransactionsList(res.data.transactions || [], res.data.currency || globalCurrency, tag);
                } else {
                    $('#txns-modal-tbody').html(
                        `<tr><td colspan="7" style="text-align:center; color:#ef4444; padding:30px;">${escHtml(res.data && res.data.message || 'Error al cargar transacciones.')}</td></tr>`
                    );
                }
            }).fail(function () {
                $('#txns-modal-tbody').html(
                    '<tr><td colspan="7" style="text-align:center; color:#ef4444; padding:30px;">Error de conexión con el servidor.</td></tr>'
                );
            });
        }

        function renderTransactionsList(txns, curr, tag) {
            const $body = $('#txns-modal-tbody');
            $('#txns-modal-counter').text(`${txns.length} transacciones vinculadas`);

            if (!txns || txns.length === 0) {
                $body.html(
                    '<tr><td colspan="7" style="text-align:center; padding: 40px 20px; color:#64748b;">' +
                    '<span class="dashicons dashicons-info" style="font-size:30px;width:30px;height:30px;display:block;margin:0 auto 6px auto;color:#94a3b8;"></span>' +
                    `No hay transacciones activas con la etiqueta #${escHtml(tag)}.` +
                    '</td></tr>'
                );
                return;
            }

            const rows = txns.map(t => {
                const isIncome = t.transaction_type === 'income';
                const typeBadge = isIncome
                    ? '<span class="aura-badge aura-badge-green" style="font-size:10.5px;">Ingreso</span>'
                    : '<span class="aura-badge aura-badge-red" style="font-size:10.5px;">Egreso</span>';
                const sign = isIncome ? '+' : '-';
                const amtColor = isIncome ? '#059669' : '#dc2626';

                return `<tr>
                    <td style="text-align:center; font-weight:700; color:#64748b; font-size:12px;">#${t.id}</td>
                    <td style="font-size:12px; white-space:nowrap; color:#64748b;">${escHtml(t.transaction_date)}</td>
                    <td style="font-size:13px; font-weight:600;">
                        ${escHtml(t.description)}
                        <div style="margin-top:2px;">${typeBadge}</div>
                    </td>
                    <td style="font-size:12px; color:#64748b;">${escHtml(t.recipient_payer)}</td>
                    <td style="font-size:12px; color:#64748b;">${escHtml(t.category_name)}</td>
                    <td style="text-align:right; font-weight:800; font-size:13px; color:${amtColor}; white-space:nowrap;">
                        ${sign}${formatMoney(t.amount, curr)}
                    </td>
                    <td style="text-align:center;">
                        <a href="${t.edit_url}" target="_blank" class="aura-btn-icon aura-btn-icon--edit" title="Editar transacción" style="display:inline-flex;">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                    </td>
                </tr>`;
            });

            $body.html(rows.join(''));
        }

        // ══════════════════════════════════════════════════════════
        // EXPORTAR A CSV
        // ══════════════════════════════════════════════════════════
        $('#btn-export-tags-csv').on('click', function () {
            exportTagsCSV();
        });

        function exportTagsCSV() {
            if (!allTags || allTags.length === 0) {
                return showNotice('No hay etiquetas disponibles para exportar.', 'error');
            }

            const headers = [
                'Etiqueta',
                'Color',
                'Descripcion',
                'Usos Totales',
                'Ingresos Cantidad',
                'Ingresos Monto',
                'Egresos Cantidad',
                'Egresos Monto',
                'Volumen Total',
                'Balance Neto',
                'Primer Registro',
                'Ultimo Registro'
            ];

            const rows = [headers];

            allTags.forEach(t => {
                rows.push([
                    t.name || '',
                    t.color || '',
                    t.description || '',
                    t.count || 0,
                    t.income_count || 0,
                    parseFloat(t.income_amount || 0).toFixed(2),
                    t.expense_count || 0,
                    parseFloat(t.expense_amount || 0).toFixed(2),
                    parseFloat(t.total_amount || 0).toFixed(2),
                    parseFloat(t.net_balance || 0).toFixed(2),
                    t.first_date || '',
                    t.latest_date || ''
                ]);
            });

            const csvContent = '\uFEFF' + rows.map(e => e.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\r\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            const dateStr = new Date().toISOString().slice(0, 10);

            link.setAttribute('href', url);
            link.setAttribute('download', `catalogo-etiquetas-${dateStr}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showNotice('Archivo CSV exportado exitosamente.', 'success');
        }

        // ══════════════════════════════════════════════════════════
        // HERRAMIENTAS RÁPIDAS SUPERIORES (Renombrar y Fusionar)
        // ══════════════════════════════════════════════════════════
        $('#btn-rename-tag').on('click', function () {
            const oldName = $('#rename-old').val().trim();
            const newName = $('#rename-new').val().trim();
            if (!oldName || !newName) return showNotice('Por favor completa ambos campos.', 'error');

            const $btn = $(this).prop('disabled', true);
            $.post(auraTagsConfig.ajaxUrl, {
                action:   'aura_tags_rename',
                nonce:    auraTagsConfig.nonce,
                old_name: oldName,
                new_name: newName,
            }).done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.renameSuccess, 'success');
                    $('#rename-old, #rename-new').val('');
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                showNotice('Error al renombrar etiqueta.', 'error');
            });
        });

        $('#btn-merge-tags').on('click', function () {
            const source = $('#merge-source').val().trim();
            const target = $('#merge-target').val().trim();
            if (!source || !target) return showNotice('Completa ambos campos para la fusión.', 'error');
            if (source.toLowerCase() === target.toLowerCase()) return showNotice('La etiqueta origen y destino deben ser distintas.', 'error');
            if (!confirm(auraTagsConfig.i18n.confirmMerge)) return;

            const $btn = $(this).prop('disabled', true);
            $.post(auraTagsConfig.ajaxUrl, {
                action: 'aura_tags_merge',
                nonce:  auraTagsConfig.nonce,
                source: source,
                target: target,
            }).done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    showNotice(res.data.message || auraTagsConfig.i18n.mergeSuccess, 'success');
                    $('#merge-source, #merge-target').val('');
                    loadAllTags();
                    loadCloud();
                } else {
                    showNotice(res.data && res.data.message, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                showNotice('Error al fusionar etiquetas.', 'error');
            });
        });

        // ── Cerrar modales con clic fuera o Escape ─────────────────
        $(document).on('click', '.aura-modal-overlay', function (e) {
            if ($(e.target).hasClass('aura-modal-overlay')) {
                hideModal(this);
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                hideModal('.aura-modal-overlay');
            }
        });
    }

    /* ============================================================
       Helpers y Notificaciones
       ============================================================ */
    function renderTableError(msg) {
        $('#tags-table-body').html(
            `<tr><td colspan="8" style="color:#ef4444;text-align:center;padding:36px 20px;">` +
            `<span class="dashicons dashicons-warning" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:6px;"></span>` +
            `<span style="font-size:14px; font-weight:600;">${escHtml(msg || 'Error al cargar las etiquetas.')}</span></td></tr>`
        );
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
                'padding': '12px 18px',
                'border-radius': '8px',
                'margin-bottom': '20px',
                'font-size': '13.5px',
                'font-weight': '600',
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px',
                'box-shadow': '0 4px 14px rgba(0,0,0,0.06)'
            })
            .html(`<span class="dashicons ${icon}" style="font-size:22px;width:22px;height:22px;"></span><span>${escHtml(msg || '')}</span>`)
            .fadeIn(150);

        setTimeout(function () { $('#aura-tags-notice').fadeOut(350); }, 5000);
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
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return String(str || '')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

})(jQuery);
