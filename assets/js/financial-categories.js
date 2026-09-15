/* global auraCategories, jQuery */
jQuery(document).ready(function($) {
    'use strict';

    let rawCategories = [];
    let currentFilter = 'active';
    let collapsedParents = new Set();
    let draggedItemId = null;
    let toastTimeout = null;

    const ajaxUrl = (typeof auraCategories !== 'undefined') ? auraCategories.ajaxUrl : ajaxurl;
    const nonce   = (typeof auraCategories !== 'undefined') ? auraCategories.nonce : '';

    // Mostrar Toast flotante
    function showToast(message, isError = false) {
        const $toast = $('#aura-category-toast');
        const $msg = $('#aura-toast-msg');
        const $icon = $('#aura-toast-icon');

        if (toastTimeout) clearTimeout(toastTimeout);

        $toast.removeClass('aura-toast-success aura-toast-error');
        $toast.addClass(isError ? 'aura-toast-error' : 'aura-toast-success');
        $icon.text(isError ? '❌' : '✓');
        $msg.text(message);

        $toast.addClass('show');
        toastTimeout = setTimeout(() => {
            $toast.removeClass('show');
        }, 3500);
    }

    // Cargar categorías del servidor
    function loadCategories() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_get_categories',
                nonce: nonce,
                orderby: 'display_order',
                order: 'ASC'
            },
            success: function(response) {
                if (response.success) {
                    rawCategories = response.data.categories || [];
                    // Normalizar parent_id a entero (0 = raíz) para consistencia
                    rawCategories.forEach(c => { c.parent_id = parseInt(c.parent_id, 10) || 0; });
                    rawCategories.sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));
                    updateCounts();
                    renderTree();
                    populateParentSelect();
                } else {
                    showToast(response.data?.message || 'Error al cargar categorías', true);
                }
            },
            error: function(xhr, status, error) {
                showToast('Error de conexión: ' + error, true);
            }
        });
    }

    // Helper para verificar si una categoría es CapEx
    function isCatCapex(c) {
        if (!c) return false;
        return c.is_capex === true || c.is_capex === 1 || c.is_capex === '1' || c.is_capex === 'true' || c.type === 'capital';
    }

    // Actualizar contadores numéricos
    function updateCounts() {
        const all = rawCategories.length;
        const active = rawCategories.filter(c => !!c.is_active).length;
        const archived = rawCategories.filter(c => !c.is_active).length;
        const income = rawCategories.filter(c => !!c.is_active && (c.type === 'income' || c.type === 'both')).length;
        const expense = rawCategories.filter(c => !!c.is_active && (c.type === 'expense' || c.type === 'both') && !isCatCapex(c)).length;
        const capital = rawCategories.filter(c => !!c.is_active && isCatCapex(c)).length;
        
        // Categorías o subcategorías activas sin ninguna transacción
        const unused = rawCategories.filter(c => {
            if (!c.is_active) return false;
            const direct = parseInt(c.transaction_count || 0, 10);
            const children = rawCategories.filter(ch => ch.parent_id == c.id);
            const childTxs = children.reduce((sum, ch) => sum + parseInt(ch.transaction_count || 0, 10), 0);
            return (direct + childTxs) === 0;
        }).length;

        const totalParents = rawCategories.filter(c => !c.parent_id).length;
        const totalSubs = all - totalParents;

        $('#count-all').text(all);
        $('#count-active').text(active);
        $('#count-archived').text(archived);
        $('#count-income').text(income);
        $('#count-expense').text(expense);
        $('#count-capital').text(capital);
        $('#count-unused').text(unused);

        $('#hero-count-total').text(all);
        $('#hero-count-parents').text(totalParents);
        $('#hero-count-subs').text(totalSubs);
    }

    // Renderizar Ícono o Emoji universalmente
    function renderCategoryIcon(icon, color = '#4f46e5', size = 20) {
        icon = (icon || '').trim();
        if (!icon) {
            icon = '📁';
        }
        if (icon.startsWith('dashicons-') || icon.includes('dashicons')) {
            return `<span class="dashicons ${escapeHtml(icon)}" style="color: ${color}; font-size: ${size}px; width: ${size}px; height: ${size}px; line-height: 1; vertical-align: middle;"></span>`;
        }
        return `<span class="aura-cat-emoji" style="font-size: ${size}px; line-height: 1; vertical-align: middle; display: inline-flex; align-items: center; justify-content: center; width: ${size}px; height: ${size}px;" aria-hidden="true">${escapeHtml(icon)}</span>`;
    }

    // Actualizar vista previa del ícono y estado de color en el modal
    function updateModalIconPreview() {
        const icon = ($('#category-icon').val() || '📁').trim();
        const color = ($('#category-color').val() || '#5d5fef').toLowerCase();
        $('#category-icon-preview').html(renderCategoryIcon(icon, color, 22));

        // Resaltar botón de ícono o emoji activo
        $('.aura-emoji-btn, .aura-dashicon-btn').removeClass('is-active');
        $(`.aura-emoji-btn[data-icon="${icon}"], .aura-dashicon-btn[data-icon="${icon}"]`).addClass('is-active');

        // Resaltar preset de color activo
        $('.aura-color-preset').removeClass('is-active').filter(function() {
            return ($(this).attr('data-color') || '').toLowerCase() === color;
        }).addClass('is-active');
    }

    // Construir Tarjeta Flotante Enriquecida para Categoría (.aura-tip-card)
    function buildCategoryTipCard(cat, isParent, subCount, directTxs, totalTxs, txUrl) {
        const isCapex = isCatCapex(cat);
        const flowLabel = cat.type === 'income' ? '📈 Ingreso' : (cat.type === 'expense' ? '📉 Egreso' : (cat.type === 'capital' ? '🏗️ CapEx' : '🔄 Ambos Flujos'));
        const natureLabel = isCapex ? '🏗️ CapEx (Gasto de Capital)' : '💼 OpEx (Gasto Operativo)';
        const statusLabel = cat.is_active ? '✅ Activa' : '⏸️ Archivada';
        const desc = (cat.description || '').trim();

        return `
            <div class="aura-tip-card">
                <div class="aura-tip-card-header">
                    <div class="aura-tip-avatar-large" style="background:${escapeHtml(cat.color || '#2563eb')}25; border-color:${escapeHtml(cat.color || '#2563eb')}50;">
                        ${renderCategoryIcon(cat.icon, cat.color || '#2563eb', 26)}
                    </div>
                    <div class="aura-tip-info">
                        <div class="aura-tip-title">${escapeHtml(cat.name)}</div>
                        <div class="aura-tip-subtitle">slug: ${escapeHtml(cat.slug || cat.name.toLowerCase().replace(/\\s+/g, '-'))}</div>
                        <div class="aura-tip-badges">
                            <span class="aura-tip-badge aura-tip-badge--type">${flowLabel}</span>
                            <span class="aura-tip-badge ${isCapex ? 'aura-tip-badge--capex' : 'aura-tip-badge--opex'}">${natureLabel}</span>
                            <span class="aura-tip-badge ${cat.is_active ? 'aura-tip-badge--active' : 'aura-tip-badge--inactive'}">${statusLabel}</span>
                        </div>
                    </div>
                </div>
                <div class="aura-tip-card-body">
                    ${desc ? `<div class="aura-tip-row"><span>📝 Descripción:</span> <strong>${escapeHtml(desc)}</strong></div>` : ''}
                    <div class="aura-tip-row"><span>📂 Jerarquía:</span> <strong>${isParent ? 'Categoría Principal (Raíz)' : 'Subcategoría dependiente'}</strong></div>
                    ${isParent ? `<div class="aura-tip-row"><span>🌿 Subcategorías:</span> <strong>${subCount} ramas registradas</strong></div>` : ''}
                    <div class="aura-tip-row"><span>📊 Movimientos:</span> <strong>${totalTxs} transacciones asociadas</strong></div>
                </div>
                <div class="aura-tip-card-footer">
                    <a href="${txUrl}" class="aura-tip-btn-link" target="_blank" rel="noopener noreferrer">📊 Ver Transacciones ↗</a>
                </div>
            </div>
        `;
    }

    // Construir Tarjeta Flotante Enriquecida para Transacciones
    function buildTxTipCard(catName, isParent, directTxs, subTxs, totalTxs, txUrl) {
        return `
            <div class="aura-tip-card" style="min-width: 280px;">
                <div class="aura-tip-card-header">
                    <div class="aura-tip-avatar-large" style="font-size:24px;background:rgba(16,185,129,0.15);border-color:rgba(16,185,129,0.3);">
                        📊
                    </div>
                    <div class="aura-tip-info">
                        <div class="aura-tip-title">Registro Contable</div>
                        <div class="aura-tip-subtitle">${escapeHtml(catName)}</div>
                    </div>
                </div>
                <div class="aura-tip-card-body">
                    <div class="aura-tip-row"><span>📌 Directas:</span> <strong>${directTxs} movimientos</strong></div>
                    ${isParent && subTxs > 0 ? `<div class="aura-tip-row"><span>🌿 En Subcategorías:</span> <strong>${subTxs} movimientos</strong></div>` : ''}
                    <div class="aura-tip-row" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 4px; margin-top: 2px;">
                        <span>📊 Total Acumulado:</span> <strong>${totalTxs} transacciones</strong>
                    </div>
                </div>
                <div class="aura-tip-card-footer">
                    <a href="${txUrl}" class="aura-tip-btn-link" target="_blank" rel="noopener noreferrer">🔍 Explorar en Detalle ↗</a>
                </div>
            </div>
        `;
    }

    // Renderizar Árbol Jerárquico Visual
    function renderTree() {
        const searchTerm = ($('#cat-search-input').val() || '').toLowerCase().trim();
        let filtered = rawCategories;

        // Filtro por pestaña
        if (currentFilter === 'active') {
            filtered = rawCategories.filter(c => !!c.is_active);
        } else if (currentFilter === 'income') {
            filtered = rawCategories.filter(c => !!c.is_active && (c.type === 'income' || c.type === 'both'));
        } else if (currentFilter === 'expense') {
            filtered = rawCategories.filter(c => !!c.is_active && (c.type === 'expense' || c.type === 'both') && !isCatCapex(c));
        } else if (currentFilter === 'capital') {
            const capexIds = new Set(rawCategories.filter(c => !!c.is_active && isCatCapex(c)).map(c => c.id));
            filtered = rawCategories.filter(c => {
                if (!c.is_active) return false;
                if (isCatCapex(c)) return true;
                const hasCapexChildren = rawCategories.some(child => child.parent_id == c.id && !!child.is_active && isCatCapex(child));
                if (hasCapexChildren) return true;
                if (c.parent_id && capexIds.has(c.parent_id)) return true;
                return false;
            });
        } else if (currentFilter === 'unused') {
            filtered = rawCategories.filter(c => {
                if (!c.is_active) return false;
                const direct = parseInt(c.transaction_count || 0, 10);
                const children = rawCategories.filter(ch => ch.parent_id == c.id);
                const childTxs = children.reduce((sum, ch) => sum + parseInt(ch.transaction_count || 0, 10), 0);
                return (direct + childTxs) === 0;
            });
        } else if (currentFilter === 'archived') {
            filtered = rawCategories.filter(c => !c.is_active);
        } else if (currentFilter === 'all') {
            filtered = rawCategories;
        }

        // Filtro por búsqueda
        if (searchTerm !== '') {
            filtered = filtered.filter(c => 
                (c.name || '').toLowerCase().includes(searchTerm) ||
                (c.slug || '').toLowerCase().includes(searchTerm) ||
                (c.description || '').toLowerCase().includes(searchTerm)
            );
        }

        if (filtered.length === 0) {
            let emptyMsg = 'No se encontraron categorías que coincidan.';
            if (currentFilter === 'archived') {
                emptyMsg = 'No tienes categorías desactivadas o archivadas.';
            } else if (currentFilter === 'active') {
                emptyMsg = 'No se encontraron categorías activas.';
            }
            $('#categories-tree-container').html(`
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <span class="dashicons dashicons-search" style="font-size: 36px; height: 36px; width: 36px; color: #94a3b8;"></span>
                    <p style="margin: 8px 0 0 0;">${emptyMsg}</p>
                </div>
            `);
            return;
        }

        const filteredIds = new Set(filtered.map(c => c.id));
        const parentItems = filtered.filter(c => !c.parent_id || !filteredIds.has(c.parent_id));
        parentItems.sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));

        const childItems = filtered.filter(c => c.parent_id && filteredIds.has(c.parent_id));
        childItems.sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));

        let html = '<div class="aura-tree-list" id="aura-tree-draggable-root">';

        parentItems.forEach(parent => {
            const children = childItems.filter(c => c.parent_id == parent.id);
            children.sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));

            const isCollapsed = collapsedParents.has(parent.id);
            const isParentActive = !!parent.is_active;
            const parentArchivedClass = isParentActive ? '' : 'aura-tree-item--archived';
            const badgeBg = parent.type === 'income' ? '#dcfce7; color: #15803d;' : (parent.type === 'expense' ? '#fee2e2; color: #b91c1c;' : (parent.type === 'capital' ? '#ffedd5; color: #c2410c;' : '#dbeafe; color: #1e40af;'));
            const typeLabel = parent.type === 'income' ? 'Ingreso' : (parent.type === 'expense' ? 'Egreso' : (parent.type === 'capital' ? 'CapEx' : 'Ambos'));
            const capexBadge = isCatCapex(parent) ? '<span style="background: #ffedd5; color: #c2410c; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; margin-left: 4px;" data-tooltip="<strong>Naturaleza CapEx:</strong> Categoría clasificada como Gasto de Capital (inversiones de largo plazo).">🏗️ CapEx</span>' : '<span style="background: rgba(239, 68, 68, 0.1); color: #dc2626; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; margin-left: 4px;" data-tooltip="<strong>Naturaleza OpEx:</strong> Gasto Operativo habitual del negocio.">💼 OpEx</span>';
            const statusBadge = isParentActive ? '' : '<span class="aura-status-badge aura-status-badge--archived" style="background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;" data-tooltip="Categoría archivada fuera de operación">⏸️ Archivada</span>';
            const subCount = children.length;

            // Cálculos de transacciones
            const parentDirectTxs = parseInt(parent.transaction_count || 0, 10);
            const allDirectChildren = rawCategories.filter(c => c.parent_id == parent.id);
            const childrenTxs = allDirectChildren.reduce((sum, c) => sum + parseInt(c.transaction_count || 0, 10), 0);
            const totalParentTxs = parentDirectTxs + childrenTxs;
            const parentTxUrl = `admin.php?page=aura-financial-transactions&filter_category=${parent.id}`;

            let txBadgeHtml = '';
            if (totalParentTxs === 0) {
                txBadgeHtml = `<span class="aura-tx-badge-link aura-tx-badge-link--zero" data-tooltip="<strong>0 Transacciones:</strong> No hay movimientos financieros asociados. Seguro para editar, fusionar o eliminar sin afectar saldos."><span class="dashicons dashicons-yes" style="font-size:12px;width:12px;height:12px;line-height:1;"></span> 0 txs</span>`;
            } else {
                const txCard = buildTxTipCard(parent.name, true, parentDirectTxs, childrenTxs, totalParentTxs, parentTxUrl);
                txBadgeHtml = `<a href="${parentTxUrl}" target="_blank" rel="noopener noreferrer" class="aura-tx-badge-link" data-aura-tooltip="${encodeURIComponent(txCard)}">
                    <span class="aura-tx-icon">📊</span>
                    <span class="aura-tx-num">${totalParentTxs}</span>
                    <span class="aura-tx-label">tx${totalParentTxs === 1 ? '' : 's'}</span>
                    <span class="dashicons dashicons-external aura-tx-ext"></span>
                </a>`;
            }

            const statusToggleBtn = isParentActive
                ? `<button type="button" class="button button-small btn-toggle-status" data-id="${parent.id}" data-active="1" data-tooltip="Desactivar y archivar esta categoría (se ocultará en registros futuros)" style="color: #64748b;"><span class="dashicons dashicons-controls-pause" style="margin-top: 2px;"></span></button>`
                : `<button type="button" class="button button-small btn-toggle-status" data-id="${parent.id}" data-active="0" data-tooltip="Reactivar categoría para permitir nuevos movimientos contables" style="color: #059669; font-weight: 600;"><span class="dashicons dashicons-controls-play" style="margin-top: 2px;"></span> Activar</button>`;

            const parentDesc = (parent.description || '').trim();
            const parentCardHtml = buildCategoryTipCard(parent, true, subCount, parentDirectTxs, totalParentTxs, parentTxUrl);

            html += `
                <div class="aura-tree-item aura-tree-parent-item ${parentArchivedClass}" 
                     id="tree-item-${parent.id}" 
                     data-id="${parent.id}" 
                     data-is-parent="true" 
                     draggable="false">
                    
                    <div class="aura-tree-parent-header">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1; flex-wrap: wrap;">
                            <span class="aura-drag-handle" title="Arrastrar para reordenar arriba/abajo o anidar">⠿</span>
                            
                            ${subCount > 0 ? `
                                <button type="button" class="aura-tree-expander ${isCollapsed ? 'is-collapsed' : ''}" 
                                        data-id="${parent.id}" 
                                        data-has-children="true"
                                        data-tooltip="${isCollapsed ? 'Expandir subcategorías' : 'Colapsar subcategorías'}">
                                    <span class="dashicons ${isCollapsed ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2'}"></span>
                                </button>
                            ` : `
                                <span class="aura-tree-no-children" data-tooltip="Sin subcategorías">
                                    <span class="dashicons dashicons-minus"></span>
                                </span>
                            `}
                            
                            <span class="aura-cat-color-circle" style="background: ${escapeHtml(parent.color || '#5D5FEF')};" data-tooltip="Color asignado: ${escapeHtml(parent.color || '#5D5FEF')}"></span>
                            ${renderCategoryIcon(parent.icon, parent.color || '#5D5FEF', 20)}
                            
                            <strong class="aura-cat-name-trigger" style="font-size: 1rem; color: #0f172a; cursor: pointer;" data-aura-tooltip="${encodeURIComponent(parentCardHtml)}">
                                ${escapeHtml(parent.name)}
                                ${parentDesc ? `<span style="font-size: 11px; opacity: 0.5; margin-left: 4px; font-weight: normal;">ℹ️</span>` : ''}
                            </strong>
                            <span style="background: ${badgeBg}; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px;">${typeLabel}</span>
                            ${capexBadge}
                            ${statusBadge}
                            
                            ${subCount > 0 ? `<span style="background: rgba(37,99,235,0.1); color: #2563eb; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px;" data-tooltip="Esta categoría tiene ${subCount} subcategorías dependientes">${subCount} subcat.</span>` : ''}
                            ${txBadgeHtml}
                        </div>

                        <div style="display: flex; gap: 6px; align-items: center;">
                            <button type="button" class="button button-small btn-add-sub" data-parent="${parent.id}" data-name="${escapeHtml(parent.name)}" data-tooltip="Agregar una nueva subcategoría dentro de '${escapeHtml(parent.name)}'">
                                ➕ Subcategoría
                            </button>
                            <button type="button" class="button button-small btn-merge-cat" data-id="${parent.id}" data-name="${escapeHtml(parent.name)}" data-tooltip="Fusionar y reasignar transacciones hacia otra categoría">
                                <span class="dashicons dashicons-randomize" style="margin-top: 2px; color: #2563eb;"></span>
                            </button>
                            ${statusToggleBtn}
                            <button type="button" class="button button-small btn-edit-cat" data-id="${parent.id}" data-tooltip="Editar propiedades de la categoría">
                                <span class="dashicons dashicons-edit" style="margin-top: 2px;"></span>
                            </button>
                            <button type="button" class="button button-small btn-delete-cat" data-id="${parent.id}" data-name="${escapeHtml(parent.name)}" data-tooltip="Eliminar categoría" style="color: #dc2626;">
                                <span class="dashicons dashicons-trash" style="margin-top: 2px;"></span>
                            </button>
                        </div>
                    </div>
            `;

            // Subcategorías
            if (children.length > 0) {
                html += `
                    <div class="aura-tree-children-container ${isCollapsed ? 'is-hidden' : ''}" id="children-container-${parent.id}">
                `;

                children.forEach(child => {
                    const isChildActive = !!child.is_active;
                    const childArchivedClass = isChildActive ? '' : 'aura-tree-child--archived';
                    const cBadgeBg = child.type === 'income' ? '#dcfce7; color: #15803d;' : (child.type === 'expense' ? '#fee2e2; color: #b91c1c;' : '#dbeafe; color: #1e40af;');
                    const cTypeLabel = child.type === 'income' ? 'Ingreso' : (child.type === 'expense' ? 'Egreso' : 'Ambos');
                    const cCapexBadge = child.is_capex ? '<span style="background: #ffedd5; color: #c2410c; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 999px; margin-left: 4px;" data-tooltip="Gasto de Capital">CapEx</span>' : '';
                    const childStatusBadge = isChildActive ? '' : '<span class="aura-status-badge aura-status-badge--archived" style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:2px 6px;border-radius:999px;" data-tooltip="Subcategoría archivada">⏸️ Archivada</span>';
                    const childTxs = parseInt(child.transaction_count || 0, 10);
                    const childTxUrl = `admin.php?page=aura-financial-transactions&filter_category=${child.id}`;

                    let cTxBadgeHtml = '';
                    if (childTxs === 0) {
                        cTxBadgeHtml = `<span class="aura-tx-badge-link aura-tx-badge-link--zero" data-tooltip="<strong>0 Transacciones:</strong> Subcategoría limpia sin movimientos."><span class="dashicons dashicons-yes" style="font-size:11px;width:11px;height:11px;line-height:1;"></span> 0 txs</span>`;
                    } else {
                        const cTxCard = buildTxTipCard(child.name, false, childTxs, 0, childTxs, childTxUrl);
                        cTxBadgeHtml = `<a href="${childTxUrl}" target="_blank" rel="noopener noreferrer" class="aura-tx-badge-link" data-aura-tooltip="${encodeURIComponent(cTxCard)}">
                            <span class="aura-tx-icon">📊</span>
                            <span class="aura-tx-num">${childTxs}</span>
                            <span class="aura-tx-label">tx${childTxs === 1 ? '' : 's'}</span>
                            <span class="dashicons dashicons-external aura-tx-ext"></span>
                        </a>`;
                    }

                    const childStatusToggleBtn = isChildActive
                        ? `<button type="button" class="button button-small btn-toggle-status" data-id="${child.id}" data-active="1" data-tooltip="Desactivar y archivar esta subcategoría" style="color: #64748b;"><span class="dashicons dashicons-controls-pause" style="margin-top: 2px;"></span></button>`
                        : `<button type="button" class="button button-small btn-toggle-status" data-id="${child.id}" data-active="0" data-tooltip="Reactivar subcategoría" style="color: #059669; font-weight: 600;"><span class="dashicons dashicons-controls-play" style="margin-top: 2px;"></span> Activar</button>`;

                    const childDesc = (child.description || '').trim();
                    const childCardHtml = buildCategoryTipCard(child, false, 0, childTxs, childTxs, childTxUrl);

                    html += `
                        <div class="aura-tree-item aura-tree-child-item ${childArchivedClass}" 
                             id="tree-item-${child.id}" 
                             data-id="${child.id}" 
                             data-parent-id="${parent.id}"
                             data-is-parent="false" 
                             draggable="false">
                            
                            <div style="display: flex; align-items: center; gap: 8px; flex: 1; flex-wrap: wrap;">
                                <span class="aura-drag-handle" title="Arrastrar para mover o anidar">⠿</span>
                                <span class="aura-tree-connector">└──</span>
                                <span class="aura-cat-color-circle aura-cat-color-circle--child" style="background: ${escapeHtml(child.color || '#5D5FEF')};" data-tooltip="Color: ${escapeHtml(child.color || '#5D5FEF')}"></span>
                                ${renderCategoryIcon(child.icon, child.color || '#5D5FEF', 16)}
                                <span class="aura-cat-name-trigger" style="font-size: 0.92rem; font-weight: 600; color: #1e293b; cursor: pointer;" data-aura-tooltip="${encodeURIComponent(childCardHtml)}">
                                    ${escapeHtml(child.name)}
                                    ${childDesc ? `<span style="font-size: 10px; opacity: 0.5; margin-left: 3px; font-weight: normal;">ℹ️</span>` : ''}
                                </span>
                                <span style="background: ${cBadgeBg}; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 999px;">${cTypeLabel}</span>
                                ${cCapexBadge}
                                ${childStatusBadge}
                                ${cTxBadgeHtml}
                            </div>

                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                <button type="button" class="btn-unparent-cat" data-id="${child.id}" data-name="${escapeHtml(child.name)}" data-tooltip="Convertir en Categoría Principal independiente (sacar de '${escapeHtml(parent.name)}')">
                                    ⬅ Sacar a Principal
                                </button>
                                <button type="button" class="button button-small btn-merge-cat" data-id="${child.id}" data-name="${escapeHtml(child.name)}" data-tooltip="Fusionar y reasignar transacciones">
                                    <span class="dashicons dashicons-randomize" style="margin-top: 2px; color: #2563eb;"></span>
                                </button>
                                ${childStatusToggleBtn}
                                <button type="button" class="button button-small btn-edit-cat" data-id="${child.id}" data-tooltip="Editar subcategoría">
                                    <span class="dashicons dashicons-edit" style="margin-top: 2px;"></span>
                                </button>
                                <button type="button" class="button button-small btn-delete-cat" data-id="${child.id}" data-name="${escapeHtml(child.name)}" data-tooltip="Eliminar subcategoría" style="color: #dc2626;">
                                    <span class="dashicons dashicons-trash" style="margin-top: 2px;"></span>
                                </button>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
            }

            html += '</div>';
        });

        // Huérfanas
        const orphanChildren = childItems.filter(c => !parentItems.some(p => p.id == c.parent_id));
        if (orphanChildren.length > 0) {
            orphanChildren.forEach(orphan => {
                const isOrphanActive = !!orphan.is_active;
                const orphanArchivedClass = isOrphanActive ? '' : 'aura-tree-item--archived';
                const orphanStatusBadge = isOrphanActive ? '' : '<span class="aura-status-badge aura-status-badge--archived" style="background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:2px 6px;border-radius:999px;" data-tooltip="Desactivada">⏸️ Inactiva</span>';
                const orphanTxs = parseInt(orphan.transaction_count || 0, 10);
                const orphanTxUrl = `admin.php?page=aura-financial-transactions&filter_category=${orphan.id}`;
                
                let orphanTxBadge = '';
                if (orphanTxs === 0) {
                    orphanTxBadge = `<span class="aura-tx-badge-link aura-tx-badge-link--zero" data-tooltip="0 transacciones"><span class="dashicons dashicons-yes" style="font-size:11px;width:11px;height:11px;line-height:1;"></span> 0 txs</span>`;
                } else {
                    const oTxCard = buildTxTipCard(orphan.name, false, orphanTxs, 0, orphanTxs, orphanTxUrl);
                    orphanTxBadge = `<a href="${orphanTxUrl}" target="_blank" rel="noopener noreferrer" class="aura-tx-badge-link" data-aura-tooltip="${encodeURIComponent(oTxCard)}">
                        <span class="aura-tx-icon">📊</span>
                        <span class="aura-tx-num">${orphanTxs}</span>
                        <span class="aura-tx-label">tx${orphanTxs === 1 ? '' : 's'}</span>
                        <span class="dashicons dashicons-external aura-tx-ext"></span>
                    </a>`;
                }

                const orphanStatusToggleBtn = isOrphanActive
                    ? `<button type="button" class="button button-small btn-toggle-status" data-id="${orphan.id}" data-active="1" data-tooltip="Desactivar / Archivar" style="color: #64748b;"><span class="dashicons dashicons-controls-pause" style="margin-top: 2px;"></span></button>`
                    : `<button type="button" class="button button-small btn-toggle-status" data-id="${orphan.id}" data-active="0" data-tooltip="Reactivar" style="color: #059669; font-weight: 600;"><span class="dashicons dashicons-controls-play" style="margin-top: 2px;"></span> Activar</button>`;

                const orphanDesc = (orphan.description || '').trim();
                const orphanCardHtml = buildCategoryTipCard(orphan, false, 0, orphanTxs, orphanTxs, orphanTxUrl);

                html += `
                    <div class="aura-tree-item aura-tree-parent-item ${orphanArchivedClass}" id="tree-item-${orphan.id}" data-id="${orphan.id}" data-is-parent="false" draggable="false">
                        <div class="aura-tree-parent-header">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span class="aura-drag-handle" title="Arrastrar para colocar como principal o anidar">⠿</span>
                                <span class="aura-tree-no-children" data-tooltip="Sin subcategorías">
                                    <span class="dashicons dashicons-minus"></span>
                                </span>
                                <span class="aura-cat-color-circle aura-cat-color-circle--child" style="background: ${escapeHtml(orphan.color || '#5D5FEF')};" data-tooltip="Color: ${escapeHtml(orphan.color || '#5D5FEF')}"></span>
                                ${renderCategoryIcon(orphan.icon, orphan.color || '#5D5FEF', 18)}
                                <strong class="aura-cat-name-trigger" style="cursor: pointer;" data-aura-tooltip="${encodeURIComponent(orphanCardHtml)}">
                                    ${escapeHtml(orphan.name)}
                                    ${orphanDesc ? `<span style="font-size: 10px; opacity: 0.5; margin-left: 3px; font-weight: normal;">ℹ️</span>` : ''}
                                </strong>
                                <span style="background:#fef3c7;color:#92400e;font-size:10px;padding:2px 6px;border-radius:999px;" data-tooltip="Esta categoría quedó huérfana de padre">Sin Padre</span>
                                ${orphanStatusBadge}
                                ${orphanTxBadge}
                            </div>
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <button type="button" class="btn-unparent-cat" data-id="${orphan.id}" data-name="${escapeHtml(orphan.name)}" data-tooltip="Convertir formalmente en Categoría Principal">
                                    ⬅ Sacar a Principal
                                </button>
                                <button type="button" class="button button-small btn-merge-cat" data-id="${orphan.id}" data-name="${escapeHtml(orphan.name)}" data-tooltip="Fusionar / Reasignar">
                                    <span class="dashicons dashicons-randomize" style="margin-top: 2px; color: #2563eb;"></span>
                                </button>
                                ${orphanStatusToggleBtn}
                                <button type="button" class="button button-small btn-edit-cat" data-id="${orphan.id}" data-tooltip="Editar">
                                    <span class="dashicons dashicons-edit" style="margin-top: 2px;"></span>
                                </button>
                                <button type="button" class="button button-small btn-delete-cat" data-id="${orphan.id}" data-tooltip="Eliminar" style="color:#dc2626;">
                                    <span class="dashicons dashicons-trash" style="margin-top: 2px;"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        html += '</div>';

        $('#categories-tree-container').html(html);

        initDragAndDrop();
    }

    // Llenar selector de categoría padre en el modal
    function populateParentSelect(selectedParentId = 0, excludeId = 0) {
        let html = '<option value="0">--- Ninguna (Categoría Padre / Raíz) ---</option>';
        
        function renderOptions(parentId, depth) {
            const children = rawCategories
                .filter(c => (c.parent_id || 0) == parentId && c.id != excludeId)
                .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));
            
            children.forEach(c => {
                if (excludeId && isDescendant(excludeId, c.id)) return;
                
                const isSelected = (c.id == selectedParentId) ? ' selected' : '';
                const indent = '&nbsp;&nbsp;&nbsp;&nbsp;'.repeat(depth);
                html += `<option value="${c.id}"${isSelected}>${indent}${escapeHtml(c.name)}</option>`;
                
                renderOptions(c.id, depth + 1);
            });
        }
        
        renderOptions(0, 0);
        $('#category-parent-id').html(html);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Verificar si candidateChildId es descendiente de potentialAncestorId
    function isDescendant(potentialAncestorId, candidateChildId) {
        if (!candidateChildId || !potentialAncestorId) return false;
        let curr = rawCategories.find(c => c.id == candidateChildId);
        while (curr && curr.parent_id) {
            if (curr.parent_id == potentialAncestorId) return true;
            curr = rawCategories.find(c => c.id == curr.parent_id);
        }
        return false;
    }

    /* ==========================================================================
       DRAG AND DROP PROFESIONAL CON POINTER EVENTS Y AUTO-SCROLL FLUIDO
       ========================================================================== */

    let pointerDragActive = false;
    let pointerStartX = 0;
    let pointerStartY = 0;
    let pointerDraggedId = null;
    let $pointerDraggedItem = null;
    let $pointerGhost = null;
    let pointerAutoScrollRaf = null;
    let pointerScrollSpeed = 0;
    let lastDropTarget = null;
    let lastDropPos = null;

    function startPointerAutoScroll() {
        stopPointerAutoScroll();
        function step() {
            if (pointerDragActive && pointerScrollSpeed !== 0) {
                window.scrollBy(0, pointerScrollSpeed);
            }
            if (pointerDragActive) {
                pointerAutoScrollRaf = requestAnimationFrame(step);
            }
        }
        pointerAutoScrollRaf = requestAnimationFrame(step);
    }

    function stopPointerAutoScroll() {
        if (pointerAutoScrollRaf) {
            cancelAnimationFrame(pointerAutoScrollRaf);
            pointerAutoScrollRaf = null;
        }
        pointerScrollSpeed = 0;
    }

    function cleanupPointerDrag() {
        pointerDragActive = false;
        pointerStartX = 0;
        pointerStartY = 0;
        pointerDraggedId = null;
        lastDropTarget = null;
        lastDropPos = null;
        stopPointerAutoScroll();

        if ($pointerGhost && $pointerGhost.length) {
            $pointerGhost.remove();
            $pointerGhost = null;
        }

        $('body').removeClass('aura-is-dragging');
        $('.aura-tree-item').removeClass('is-dragging drop-before drop-after drop-inside');
        $('#aura-root-dropzone-top, #aura-root-dropzone-bottom, #aura-root-dropzone').removeClass('active-drop drag-over-zone');
        $('#aura-tree-draggable-root').removeClass('drag-active');
        $('#aura-drop-hint').removeClass('show');

        $pointerDraggedItem = null;
    }

    function getDropPosition($target, clientY) {
        const isParent = $target.hasClass('aura-tree-parent-item');
        const $header = isParent ? $target.find('.aura-tree-parent-header').first() : $target;
        const rect = ($header[0] || $target[0]).getBoundingClientRect();
        const relY = clientY - rect.top;
        const h = rect.height;

        const draggedCat = rawCategories.find(c => c.id == pointerDraggedId);
        // Si el elemento arrastrado ya tiene subcategorías, no se puede anidar dentro de otra (mantener 2 niveles)
        const draggedHasChildren = draggedCat ? rawCategories.some(c => c.parent_id == draggedCat.id) : false;
        // Si el destino es una subcategoría (tampoco puede recibir subcategorías anidadas)
        const targetIsChild = !$target.data('is-parent');

        if (draggedHasChildren || targetIsChild) {
            // Solo reordenar arriba (antes) o abajo (después) con umbral 50/50
            return (relY < h * 0.5) ? 'before' : 'after';
        }

        // Para una categoría sin hijos arrastrada sobre una categoría padre:
        if (relY < h * 0.32) return 'before';
        if (relY > h * 0.68) return 'after';
        return 'inside';
    }

    function onPointerDragMove(e) {
        if (!pointerDraggedId || !$pointerDraggedItem || !$pointerDraggedItem.length) return;

        // Si aún no ha iniciado arrastre activo, verificar umbral de movimiento de 4px
        if (!pointerDragActive) {
            const dist = Math.hypot(e.clientX - pointerStartX, e.clientY - pointerStartY);
            if (dist < 5) return;

            pointerDragActive = true;
            $('body').addClass('aura-is-dragging');
            $pointerDraggedItem.addClass('is-dragging');
            $('#aura-tree-draggable-root').addClass('drag-active');
            $('#aura-root-dropzone-top, #aura-root-dropzone-bottom, #aura-root-dropzone').addClass('active-drop');

            const draggedCat = rawCategories.find(c => c.id == pointerDraggedId);
            const catName = draggedCat ? draggedCat.name : 'Categoría';
            const catIcon = draggedCat ? (draggedCat.icon || '📁') : '📁';

            // Crear el ghost flotante que acompaña al cursor con estilo moderno
            $pointerGhost = $(`
                <div class="aura-pointer-drag-ghost" style="position:fixed; z-index:99999999; pointer-events:none; left:${e.clientX + 16}px; top:${e.clientY + 14}px;">
                    <span style="font-size:18px;">${catIcon}</span>
                    <span style="font-weight:700;">${escapeHtml(catName)}</span>
                </div>
            `).appendTo('body');

            startPointerAutoScroll();
        }

        // Actualizar posición del ghost flotante
        if ($pointerGhost && $pointerGhost.length) {
            $pointerGhost.css({
                left: (e.clientX + 16) + 'px',
                top: (e.clientY + 14) + 'px'
            });
        }

        // Auto-Scroll continuo dinámico
        const winH = window.innerHeight;
        const topThreshold = 130;
        const bottomThreshold = winH - 90;

        if (e.clientY < topThreshold) {
            const ratio = Math.min(1, Math.max(0, (topThreshold - e.clientY) / topThreshold));
            pointerScrollSpeed = -Math.round(8 + ratio * 24);
        } else if (e.clientY > bottomThreshold) {
            const dist = e.clientY - bottomThreshold;
            const ratio = Math.min(1, Math.max(0, dist / 90));
            pointerScrollSpeed = Math.round(8 + ratio * 24);
        } else {
            pointerScrollSpeed = 0;
        }

        // Detección de elemento objetivo bajo el cursor
        const elemBelow = document.elementFromPoint(e.clientX, e.clientY);
        if (!elemBelow) return;

        $('.aura-tree-item').removeClass('drop-before drop-after drop-inside');
        $('#aura-root-dropzone-top, #aura-root-dropzone-bottom, #aura-root-dropzone').removeClass('drag-over-zone');

        const $rootTop = $(elemBelow).closest('#aura-root-dropzone-top');
        const $rootBottom = $(elemBelow).closest('#aura-root-dropzone-bottom, #aura-root-dropzone');
        const $targetItem = $(elemBelow).closest('.aura-tree-item');
        const $hint = $('#aura-drop-hint');

        if ($rootTop.length) {
            $rootTop.addClass('drag-over-zone');
            lastDropTarget = 'root_start';
            lastDropPos = 'root_start';
            $hint.text('⭐ Convertir en Categoría Principal (al inicio)').addClass('show').css({ top: e.clientY - 38, left: e.clientX + 20 });
            return;
        }

        if ($rootBottom.length) {
            $rootBottom.addClass('drag-over-zone');
            lastDropTarget = 'root_end';
            lastDropPos = 'root_end';
            $hint.text('⭐ Convertir en Categoría Principal (al final)').addClass('show').css({ top: e.clientY - 38, left: e.clientX + 20 });
            return;
        }

        if ($targetItem.length) {
            const targetId = parseInt($targetItem.data('id'), 10);
            if (targetId && targetId !== pointerDraggedId) {
                const pos = getDropPosition($targetItem, e.clientY);
                lastDropTarget = targetId;
                lastDropPos = pos;

                if (pos === 'before') $targetItem.addClass('drop-before');
                else if (pos === 'after') $targetItem.addClass('drop-after');
                else if (pos === 'inside') $targetItem.addClass('drop-inside');

                const targetCat = rawCategories.find(c => c.id == targetId);
                const targetName = targetCat ? targetCat.name : '';

                let hintLabel = '';
                if (pos === 'inside') hintLabel = `📂 Anidar dentro de "${targetName}"`;
                else if (pos === 'before') hintLabel = `⬆ Insertar antes de "${targetName}"`;
                else hintLabel = `⬇ Insertar después de "${targetName}"`;

                $hint.text(hintLabel).addClass('show').css({ top: e.clientY - 38, left: e.clientX + 20 });
                return;
            }
        }

        // Si el puntero se encuentra en los huecos (gaps) del contenedor del árbol
        const $treeContainer = $(elemBelow).closest('#aura-tree-draggable-root');
        if ($treeContainer.length) {
            const visibleParents = $treeContainer.children('.aura-tree-parent-item:visible');
            let closestItem = null;
            let closestDist = Infinity;
            let insertPos = 'after';

            visibleParents.each(function() {
                const r = this.getBoundingClientRect();
                const midY = r.top + r.height / 2;
                const dist = Math.abs(e.clientY - midY);
                if (dist < closestDist) {
                    closestDist = dist;
                    closestItem = this;
                    insertPos = (e.clientY < midY) ? 'before' : 'after';
                }
            });

            if (closestItem) {
                const $closest = $(closestItem);
                const targetId = parseInt($closest.data('id'), 10);
                if (targetId && targetId !== pointerDraggedId) {
                    lastDropTarget = targetId;
                    lastDropPos = insertPos;
                    if (insertPos === 'before') $closest.addClass('drop-before');
                    else $closest.addClass('drop-after');

                    const targetCat = rawCategories.find(c => c.id == targetId);
                    const label = insertPos === 'before' ? `⬆ Insertar antes de "${targetCat?.name || ''}"` : `⬇ Insertar después de "${targetCat?.name || ''}"`;
                    $hint.text(label).addClass('show').css({ top: e.clientY - 38, left: e.clientX + 20 });
                    return;
                }
            }
        }

        lastDropTarget = null;
        lastDropPos = null;
        $hint.removeClass('show');
    }

    function onPointerDragEnd(e) {
        $(document).off('pointermove.auraDragMove pointerup.auraDragUp pointercancel.auraDragUp');

        const wasDragging = pointerDragActive;
        const draggedId = pointerDraggedId;
        const target = lastDropTarget;
        const pos = lastDropPos;

        cleanupPointerDrag();

        if (wasDragging && draggedId && target) {
            if (target === 'root_start') {
                applyReorderAndHierarchy(draggedId, null, 'root_start', true);
            } else if (target === 'root_end') {
                applyReorderAndHierarchy(draggedId, null, 'root_end', true);
            } else {
                applyReorderAndHierarchy(draggedId, target, pos, true);
            }
        }
    }

    function initDragAndDrop() {
        // Asegurar la presencia de drop hint
        if (!$('#aura-drop-hint').length) {
            $('<div id="aura-drop-hint" class="aura-drop-hint"></div>').appendTo('body');
        }

        // Listener de Pointer Events sobre el handle ⠿
        $(document).off('pointerdown.auraDrag').on('pointerdown.auraDrag', '.aura-drag-handle', function(e) {
            if (e.button !== undefined && e.button !== 0) return; // Solo clic primario

            e.preventDefault();
            const $handle = $(this);
            $pointerDraggedItem = $handle.closest('.aura-tree-item');
            if (!$pointerDraggedItem.length) return;

            pointerDraggedId = parseInt($pointerDraggedItem.data('id'), 10);
            pointerStartX = e.clientX;
            pointerStartY = e.clientY;
            pointerDragActive = false;

            $('#aura-global-tooltip').removeClass('is-visible').hide();

            $(document).off('pointermove.auraDragMove pointerup.auraDragUp pointercancel.auraDragUp');
            $(document).on('pointermove.auraDragMove', onPointerDragMove);
            $(document).on('pointerup.auraDragUp pointercancel.auraDragUp', onPointerDragEnd);
        });

        // Cancelar drag con Escape o pérdida de foco
        $(window).off('blur.auraPointerSafety').on('blur.auraPointerSafety', cleanupPointerDrag);
        $(document).off('keydown.auraPointerSafety').on('keydown.auraPointerSafety', function(e) {
            if (e.key === 'Escape' && pointerDragActive) {
                cleanupPointerDrag();
            }
        });

        // Prevenir drag nativo HTML5 para evitar conflictos
        $(document).off('dragstart.auraPrevent').on('dragstart.auraPrevent', '.aura-drag-handle, .aura-tree-item', function(e) {
            e.preventDefault();
            return false;
        });
    }

    function applyReorderAndHierarchy(draggedId, targetId, position, showAnimation) {
        if (!draggedId) return;
        draggedId = parseInt(draggedId, 10);
        if (targetId) targetId = parseInt(targetId, 10);

        if (targetId && draggedId === targetId) return;

        // Validar que no se anide en sus propios descendientes
        if (targetId && isDescendant(draggedId, targetId)) {
            showToast('⚠️ No puedes anidar una categoría dentro de sus propias subcategorías', true);
            return;
        }

        const draggedItem = rawCategories.find(c => c.id == draggedId);
        if (!draggedItem) return;

        const targetItem = targetId ? rawCategories.find(c => c.id == targetId) : null;

        if (position === 'inside') {
            // Anidar dentro de targetItem como subcategoría
            draggedItem.parent_id = targetItem.id;
            // Asegurar que la categoría padre se expanda para ver su nuevo hijo
            collapsedParents.delete(targetItem.id);

            const siblings = rawCategories
                .filter(c => c.parent_id == targetItem.id && c.id != draggedId)
                .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));

            siblings.push(draggedItem);
            siblings.forEach((sib, idx) => { sib.display_order = idx; });

        } else if (position === 'root_start') {
            // Convertir en Categoría Padre (Raíz) al principio
            draggedItem.parent_id = 0;
            const roots = rawCategories
                .filter(c => !c.parent_id && c.id != draggedId)
                .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));
            roots.unshift(draggedItem);
            roots.forEach((r, idx) => { r.display_order = idx; });

        } else if (position === 'root_end') {
            // Convertir en Categoría Padre (Raíz) al final
            draggedItem.parent_id = 0;
            const roots = rawCategories
                .filter(c => !c.parent_id && c.id != draggedId)
                .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));
            roots.push(draggedItem);
            roots.forEach((r, idx) => { r.display_order = idx; });

        } else if (position === 'before' || position === 'after') {
            // Mismo nivel que targetItem
            const targetParentId = targetItem ? (parseInt(targetItem.parent_id, 10) || 0) : 0;
            draggedItem.parent_id = targetParentId;

            const siblings = rawCategories
                .filter(c => (parseInt(c.parent_id, 10) || 0) === targetParentId && c.id != draggedId)
                .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name));

            let targetIdx = siblings.findIndex(s => s.id == targetId);
            if (targetIdx === -1) targetIdx = siblings.length;

            if (position === 'before') {
                siblings.splice(targetIdx, 0, draggedItem);
            } else {
                siblings.splice(targetIdx + 1, 0, draggedItem);
            }

            siblings.forEach((sib, idx) => { sib.display_order = idx; });
        }

        // Recalcular display_order normalizado en toda la jerarquía
        const allRoots = rawCategories
            .filter(c => !c.parent_id)
            .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0));

        let rootCounter = 0;
        allRoots.forEach(r => {
            r.display_order = rootCounter++;
            const children = rawCategories
                .filter(c => c.parent_id == r.id)
                .sort((a, b) => (a.display_order ?? 0) - (b.display_order ?? 0));
            let childCounter = 0;
            children.forEach(ch => { ch.display_order = childCounter++; });
        });

        // Construir payload completo
        const hierarchyPayload = rawCategories.map(c => ({
            id: c.id,
            parent_id: parseInt(c.parent_id, 10) || null,
            display_order: c.display_order || 0
        }));

        // Renderizado inmediato en la interfaz
        renderTree();
        populateParentSelect();

        // Flash de confirmación en el item soltado
        if (showAnimation) {
            setTimeout(() => {
                const $placed = $(`#tree-item-${draggedId}`);
                if ($placed.length) {
                    $placed.addClass('drop-placed');
                    setTimeout(() => $placed.removeClass('drop-placed'), 600);
                }
            }, 50);
        }

        // Guardar cambios en el backend
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_reorder_categories',
                hierarchy: hierarchyPayload,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('✓ Orden guardado exitosamente');
                } else {
                    showToast(response.data?.message || 'Error al guardar jerarquía', true);
                    loadCategories();
                }
            },
            error: function(xhr, status, error) {
                showToast('Error en el servidor: ' + error, true);
                loadCategories();
            }
        });
    }




    /* ==========================================================================
       EVENTOS DE INTERFAZ
       ========================================================================== */

    $(document).on('click', '.tab-filter', function(e) {
        e.preventDefault();
        $('.tab-filter').removeClass('button-primary active').addClass('button-secondary');
        $(this).removeClass('button-secondary').addClass('button-primary active');
        currentFilter = $(this).data('filter') || 'active';
        renderTree();
    });

    // Convertir subcategoría en categoría principal (desanidar)
    $(document).on('click', '.btn-unparent-cat', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const catId = parseInt($(this).data('id'), 10);
        const catName = $(this).data('name') || '';
        if (!catId) return;

        applyReorderAndHierarchy(catId, null, 'root_end', true);
        showToast(`✓ "${catName || 'Categoría'}" convertida en Categoría Principal`);
    });

    $('#cat-search-input').on('input keyup search', function() {
        renderTree();
    });

    // Toggle Activar / Desactivar Categoría con 1 clic
    $(document).on('click', '.btn-toggle-status', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const id = parseInt($(this).data('id'), 10);
        const $btn = $(this).prop('disabled', true);
        const originalHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; vertical-align: middle;"></span>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_toggle_category_status',
                category_id: id,
                nonce: nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalHtml);
                if (response.success) {
                    const newStatus = response.data.new_status;
                    showToast(response.data?.message || (newStatus ? 'Categoría activada exitosamente' : 'Categoría archivada exitosamente'));
                    const targetCat = rawCategories.find(c => c.id == id);
                    if (targetCat) {
                        targetCat.is_active = newStatus;
                    }
                    updateCounts();
                    renderTree();
                } else {
                    showToast(response.data?.message || 'Error al cambiar estado', true);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html(originalHtml);
                showToast('Error de conexión: ' + error, true);
            }
        });
    });

    $(document).on('click', '.aura-tree-expander', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const parentId = parseInt($(this).data('id'), 10);
        if (!parentId) return;

        const hasChildren = $(this).attr('data-has-children') === 'true';
        if (!hasChildren) return;

        const $container = $(`#children-container-${parentId}`);
        if (!$container.length) return;

        if (collapsedParents.has(parentId)) {
            collapsedParents.delete(parentId);
            $container.removeClass('is-hidden');
            $(this).removeClass('is-collapsed')
                   .attr('data-tooltip', 'Colapsar subcategorías')
                   .html('<span class="dashicons dashicons-arrow-down-alt2"></span>');
        } else {
            collapsedParents.add(parentId);
            $container.addClass('is-hidden');
            $(this).addClass('is-collapsed')
                   .attr('data-tooltip', 'Expandir subcategorías')
                   .html('<span class="dashicons dashicons-arrow-right-alt2"></span>');
        }
    });

    $('#btn-expand-all').on('click', function() {
        collapsedParents.clear();
        $('.aura-tree-children-container').removeClass('is-hidden');
        $('.aura-tree-expander[data-has-children="true"]')
            .removeClass('is-collapsed')
            .attr('data-tooltip', 'Colapsar subcategorías')
            .html('<span class="dashicons dashicons-arrow-down-alt2"></span>');
    });

    $('#btn-collapse-all').on('click', function() {
        rawCategories.filter(c => !c.parent_id).forEach(p => {
            const hasSubs = rawCategories.some(c => c.parent_id == p.id);
            if (hasSubs) {
                collapsedParents.add(p.id);
            }
        });
        $('.aura-tree-children-container').addClass('is-hidden');
        $('.aura-tree-expander[data-has-children="true"]')
            .addClass('is-collapsed')
            .attr('data-tooltip', 'Expandir subcategorías')
            .html('<span class="dashicons dashicons-arrow-right-alt2"></span>');
    });

    // Abrir Modal Crear
    $('#aura-add-category-btn').on('click', function() {
        $('#aura-category-form')[0].reset();
        $('#category-id').val('');
        $('#category-color').val('#5D5FEF');
        $('#category-icon').val('📁');
        $('#category-order').val(rawCategories.length);
        populateParentSelect(0, 0);
        updateModalIconPreview();
        $('#aura-modal-title').text('Nueva Categoría');
        $('#aura-category-modal').addClass('active');
    });

    // Modal Subcategoría rápida
    $(document).on('click', '.btn-add-sub', function() {
        const parentId = parseInt($(this).data('parent'), 10);
        const parentName = $(this).data('name') || '';

        $('#aura-category-form')[0].reset();
        $('#category-id').val('');
        $('#category-color').val('#5D5FEF');
        $('#category-icon').val('📁');
        populateParentSelect(parentId, 0);
        updateModalIconPreview();
        $('#aura-modal-title').text(`Nueva Subcategoría en "${parentName}"`);
        $('#aura-category-modal').addClass('active');
    });

    // Abrir Modal Editar
    $(document).on('click', '.btn-edit-cat', function() {
        const id = parseInt($(this).data('id'), 10);
        const cat = rawCategories.find(c => c.id == id);
        if (!cat) return;

        $('#category-id').val(cat.id);
        $('#category-name').val(cat.name);
        $('#category-slug').val(cat.slug || '');
        $('#category-type').val(cat.type);
        $('#category-icon').val(cat.icon || '📁');
        $('#category-color').val(cat.color || '#5D5FEF');
        $('#category-description').val(cat.description || '');
        $('#category-order').val(cat.display_order || 0);
        $('#category-active').prop('checked', !!cat.is_active);
        $('#category-is-capex').prop('checked', !!cat.is_capex);

        populateParentSelect(cat.parent_id || 0, cat.id);
        updateModalIconPreview();
        $('#aura-modal-title').text(`Editar Categoría "${cat.name}"`);
        $('#aura-category-modal').addClass('active');
    });

    // Eventos de Vista Previa del Ícono en Tiempo Real
    $('#category-icon').on('input change keyup paste', function() {
        updateModalIconPreview();
    });

    $('#category-color').on('input change', function() {
        updateModalIconPreview();
    });

    // Clic en Píldora de Transacciones (evitar propagación al drag & drop o colapsar de tarjeta)
    $(document).on('click', '.aura-tx-link', function(e) {
        e.stopPropagation();
    });

    // Clic en Emojis rápidos (soporta Shift+Clic para concatenar y armar combinaciones)
    $(document).on('click', '.aura-emoji-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const icon = $(this).attr('data-icon') || $(this).text().trim();
        if (icon) {
            const currentVal = $('#category-icon').val().trim();
            if (e.shiftKey && currentVal && !currentVal.startsWith('dashicons-')) {
                $('#category-icon').val(currentVal + icon);
            } else {
                $('#category-icon').val(icon);
            }
            updateModalIconPreview();
            $('#category-icon').trigger('input').trigger('change');
        }
    });

    // Clic en Dashicons rápidos
    $(document).on('click', '.aura-dashicon-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const icon = $(this).attr('data-icon') || $(this).data('icon');
        if (icon) {
            $('#category-icon').val(icon);
            updateModalIconPreview();
            $('#category-icon').trigger('input').trigger('change');
        }
    });

    // Desplegar / Colapsar Sugerencias de Íconos
    $(document).on('click', '#aura-toggle-picker-header', function(e) {
        e.preventDefault();
        const $body = $('#aura-picker-body-content');
        const $arrow = $(this).find('.aura-picker-toggle-arrow');
        $body.slideToggle(180, function() {
            if ($body.is(':visible')) {
                $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            } else {
                $arrow.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });
    });

    // Cerrar Modales
    $('[data-close-modal], .aura-modal-overlay').on('click', function(e) {
        if (e.target === this || $(this).is('[data-close-modal]')) {
            $('.aura-modal-overlay').removeClass('active');
        }
    });

    // Preset de Colores
    $(document).on('click', '.aura-color-preset', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const color = $(this).attr('data-color') || $(this).data('color');
        if (color) {
            $('.aura-color-preset').removeClass('is-selected is-active');
            $(this).addClass('is-selected is-active');
            $('#category-color').val(color);
            updateModalIconPreview();
            $('#category-color').trigger('input').trigger('change');
        }
    });

    // Generar Slug automático
    $('#category-name').on('input', function() {
        const name = $(this).val();
        const $slug = $('#category-slug');
        if (!$slug.data('manual')) {
            const slug = name.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_]+/g, '-')
                .replace(/^-+|-+$/g, '');
            $slug.val(slug);
        }
    });

    $('#category-slug').on('input', function() {
        $(this).data('manual', true);
    });

    // Guardar Categoría
    $('#aura-category-form').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#aura-save-btn').prop('disabled', true);
        const $btnText = $('#aura-save-btn-text').text('Guardando...');
        const categoryId = $('#category-id').val();
        const action = categoryId ? 'aura_update_category' : 'aura_create_category';

        let parent_id = $('#category-parent-id').val() || '';
        if (parent_id === '0') parent_id = '';

        const data = {
            action: action,
            nonce: nonce,
            category_id: categoryId,
            name: $('#category-name').val().trim(),
            slug: $('#category-slug').val().trim(),
            type: $('#category-type').val(),
            parent_id: parent_id,
            color: $('#category-color').val(),
            icon: $('#category-icon').val(),
            description: $('#category-description').val(),
            is_active: $('#category-active').is(':checked') ? 'true' : 'false',
            is_capex: $('#category-is-capex').is(':checked') ? 'true' : 'false',
            display_order: $('#category-order').val()
        };

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                $btn.prop('disabled', false);
                $btnText.text('Guardar Categoría');
                if (response.success) {
                    $('#aura-category-modal').removeClass('active');
                    showToast(response.data?.message || 'Categoría guardada exitosamente');
                    loadCategories();
                } else {
                    showToast(response.data?.message || 'Error al guardar', true);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $btnText.text('Guardar Categoría');
                showToast('Error en el servidor: ' + error, true);
            }
        });
    });

    $('#aura-save-btn').on('click', function(e) {
        e.preventDefault();
        $('#aura-category-form').trigger('submit');
    });

    // Eliminar Categoría
    $(document).on('click', '.btn-delete-cat', function() {
        const id = parseInt($(this).data('id'), 10);
        const cat = rawCategories.find(c => c.id == id);
        const name = $(this).data('name') || (cat ? cat.name : '');
        const directTxs = cat ? parseInt(cat.transaction_count || 0, 10) : 0;
        const children = rawCategories.filter(c => c.parent_id == id);
        const childrenTxs = children.reduce((sum, c) => sum + parseInt(c.transaction_count || 0, 10), 0);

        $('#aura-delete-id').val(id);

        if (directTxs > 0) {
            $('#aura-delete-confirm-msg').html(`
                <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px;border-radius:10px;margin-bottom:12px;text-align:left;">
                    <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:1rem;margin-bottom:6px;">
                        <span>⚠️</span> No se puede eliminar "${escapeHtml(name)}"
                    </div>
                    <p style="margin:0 0 6px 0;font-size:0.9rem;">
                        Esta categoría tiene <strong>${directTxs} transacción(es) registrada(s)</strong> en el sistema contable.
                    </p>
                    <p style="margin:0;font-size:0.85rem;color:#b91c1c;">
                        💡 Para proteger el historial financiero y las estadísticas, las categorías con transacciones no pueden ser destruidas. Si ya no la utilizas, puedes <strong>desactivarla</strong> editándola.
                    </p>
                </div>
            `);
            $('#aura-confirm-delete-btn').hide();
        } else if (children.length > 0) {
            const warningSubTxs = childrenTxs > 0 
                ? `<p style="margin:6px 0 0 0;font-size:0.85rem;color:#b45309;">⚠️ Sus subcategorías contienen un total de <strong>${childrenTxs} transacción(es)</strong>, las cuales quedarán intactas al promoverse a categorías raíz.</p>` 
                : '';
            $('#aura-delete-confirm-msg').html(`
                <p style="font-size:0.95rem;margin-bottom:12px;">¿Estás seguro de eliminar la categoría principal <strong>"${escapeHtml(name)}"</strong>?</p>
                <div style="background:#fffbeb;border:1px solid #fef3c7;color:#92400e;padding:12px;border-radius:10px;font-size:0.9rem;text-align:left;">
                    ℹ️ Esta categoría tiene <strong>${children.length} subcategoría(s)</strong> vinculada(s). Al eliminar la categoría padre, las subcategorías no se perderán, sino que pasarán a ser categorías principales (raíz).
                    ${warningSubTxs}
                </div>
            `);
            $('#aura-confirm-delete-btn').show().text('Sí, eliminar');
        } else {
            $('#aura-delete-confirm-msg').html(`
                <p style="font-size:0.95rem;margin-bottom:12px;">¿Estás seguro de eliminar la categoría <strong>"${escapeHtml(name)}"</strong>?</p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px;border-radius:10px;font-size:0.9rem;text-align:left;">
                    <div style="font-weight:700;display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                        <span>✓</span> 0 Transacciones asociadas (Totalmente Seguro)
                    </div>
                    Esta categoría no tiene ningún registro financiero vinculado. Puedes eliminarla sin afectar balances ni estadísticas.
                </div>
            `);
            $('#aura-confirm-delete-btn').show().text('Sí, eliminar');
        }

        $('#aura-confirm-delete-modal').addClass('active');
    });

    $('#aura-confirm-delete-btn').on('click', function() {
        const id = $('#aura-delete-id').val();
        if (!id) return;

        const $btn = $(this).prop('disabled', true).text('Eliminando...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_delete_category',
                category_id: id,
                nonce: nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Eliminar');
                $('#aura-confirm-delete-modal').removeClass('active');
                if (response.success) {
                    showToast('Categoría eliminada exitosamente');
                    loadCategories();
                } else {
                    showToast(response.data?.message || 'Error al eliminar', true);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Eliminar');
                showToast('Error de conexión', true);
            }
        });
    });

    // Eliminar Todas
    $('#aura-delete-all-btn').on('click', function() {
        $('#aura-delete-all-confirm-check').prop('checked', false);
        $('#aura-delete-all-confirm').prop('disabled', true);
        $('#aura-delete-all-modal').addClass('active');
    });

    $('#aura-delete-all-confirm-check').on('change', function() {
        $('#aura-delete-all-confirm').prop('disabled', !$(this).is(':checked'));
    });

    $('#aura-delete-all-confirm').on('click', function() {
        const $btn = $(this).prop('disabled', true).text('Eliminando...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_delete_all_categories',
                nonce: nonce,
                scope: 'all'
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Sí, eliminar');
                $('#aura-delete-all-modal').removeClass('active');
                if (response.success) {
                    showToast(response.data?.message || 'Categorías eliminadas');
                    loadCategories();
                } else {
                    showToast(response.data?.message || 'Error al eliminar', true);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Sí, eliminar');
                showToast('Error al conectar', true);
            }
        });
    });

    /* ==========================================================================
       LÓGICA DE FUSIÓN / REASIGNACIÓN MASIVA DE CATEGORÍAS
       ========================================================================== */

    function populateMergeSelects(selectedSourceId = 0, selectedTargetId = 0) {
        selectedSourceId = parseInt(selectedSourceId, 10) || 0;
        selectedTargetId = parseInt(selectedTargetId, 10) || 0;

        const $source = $('#aura-merge-source').empty();
        const $target = $('#aura-merge-target').empty();

        $source.append('<option value="">-- Selecciona categoría origen --</option>');
        $target.append('<option value="">-- Selecciona categoría destino --</option>');

        // Ordenar categorías: primero raíces, luego sus hijas
        const roots = rawCategories.filter(c => !c.parent_id);
        roots.sort((a, b) => a.name.localeCompare(b.name));

        roots.forEach(root => {
            const rType = root.type === 'income' ? 'Ingreso' : (root.type === 'expense' ? 'Egreso' : 'Ambos');
            const rTxs = parseInt(root.transaction_count || 0, 10);
            const labelRoot = `📁 ${root.name} (${rType} · ${rTxs} txs)`;

            $source.append($('<option>').val(root.id).text(labelRoot).prop('selected', root.id === selectedSourceId));
            $target.append($('<option>').val(root.id).text(labelRoot).prop('selected', root.id === selectedTargetId));

            const children = rawCategories.filter(c => c.parent_id == root.id);
            children.sort((a, b) => a.name.localeCompare(b.name));

            children.forEach(child => {
                const cType = child.type === 'income' ? 'Ingreso' : (child.type === 'expense' ? 'Egreso' : 'Ambos');
                const cTxs = parseInt(child.transaction_count || 0, 10);
                const labelChild = `　└─ 📄 ${child.name} (${cType} · ${cTxs} txs)`;

                $source.append($('<option>').val(child.id).text(labelChild).prop('selected', child.id === selectedSourceId));
                $target.append($('<option>').val(child.id).text(labelChild).prop('selected', child.id === selectedTargetId));
            });
        });

        // Huérfanas
        const orphans = rawCategories.filter(c => c.parent_id && !roots.some(r => r.id == c.parent_id));
        if (orphans.length > 0) {
            orphans.forEach(orp => {
                const oTxs = parseInt(orp.transaction_count || 0, 10);
                const labelOrp = `📄 ${orp.name} (Sin padre · ${oTxs} txs)`;
                $source.append($('<option>').val(orp.id).text(labelOrp).prop('selected', orp.id === selectedSourceId));
                $target.append($('<option>').val(orp.id).text(labelOrp).prop('selected', orp.id === selectedTargetId));
            });
        }

        updateMergePreview();
    }

    function updateMergePreview() {
        const sourceId = parseInt($('#aura-merge-source').val(), 10) || 0;
        const targetId = parseInt($('#aura-merge-target').val(), 10) || 0;
        const $card = $('#aura-merge-preview-card');
        const $warning = $('#aura-merge-warning').hide().empty();
        const $confirmBtn = $('#aura-confirm-merge-btn').prop('disabled', true);

        if (!sourceId) {
            $card.hide();
            return;
        }

        if (sourceId && targetId && sourceId === targetId) {
            $card.hide();
            $warning.html('⚠️ <strong>Atención:</strong> La categoría de origen y de destino no pueden ser la misma.').show();
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_get_merge_preview',
                nonce: nonce,
                source_id: sourceId,
                target_id: targetId
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    $card.show();

                    // Llenar Origen
                    if (data.source) {
                        $('#merge-prev-source-name').text(data.source.name);
                        $('#merge-prev-source-tx').text(`${data.source.tx_count} txs`);
                        $('#merge-prev-source-sub').text(`${data.source.subcat_count} hijas`);
                    }

                    // Llenar Destino
                    if (data.target) {
                        $('#merge-prev-target-name').text(data.target.name);
                        $('#merge-prev-target-total').text(`${data.target.projected_total} txs totales proyectadas`);

                        if (data.target.is_circular) {
                            $warning.html('⚠️ <strong>Conflicto de jerarquía:</strong> La categoría destino seleccionada es una subcategoría de la de origen. Desmarca la opción de mover subcategorías o selecciona un destino diferente.').show();
                            $confirmBtn.prop('disabled', true);
                        } else {
                            $confirmBtn.prop('disabled', false);
                        }
                    } else {
                        $('#merge-prev-target-name').text('Selecciona destino...');
                        $('#merge-prev-target-total').text('—');
                    }
                } else {
                    $card.hide();
                    $warning.html(response.data?.message || 'Error al obtener vista previa').show();
                }
            },
            error: function() {
                $card.hide();
            }
        });
    }

    // Botón superior: Abrir Modal Fusión
    $('#aura-merge-categories-btn').on('click', function(e) {
        e.preventDefault();
        $('#aura-merge-form')[0].reset();
        populateMergeSelects(0, 0);
        $('#aura-merge-modal').addClass('active');
    });

    // Botón en cada fila/nodo de categoría: Abrir modal con origen preseleccionado
    $(document).on('click', '.btn-merge-cat', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const sourceId = parseInt($(this).data('id'), 10) || 0;
        $('#aura-merge-form')[0].reset();
        populateMergeSelects(sourceId, 0);
        $('#aura-merge-modal').addClass('active');
    });

    // Cambios en selectores de origen y destino
    $('#aura-merge-source, #aura-merge-target').on('change', function() {
        updateMergePreview();
    });

    // Ejecutar Fusión al confirmar
    $('#aura-confirm-merge-btn').on('click', function(e) {
        e.preventDefault();
        const sourceId = parseInt($('#aura-merge-source').val(), 10) || 0;
        const targetId = parseInt($('#aura-merge-target').val(), 10) || 0;

        if (!sourceId || !targetId) {
            alert('Por favor selecciona tanto la categoría de origen como la de destino.');
            return;
        }

        if (sourceId === targetId) {
            alert('La categoría de origen y la de destino no pueden ser iguales.');
            return;
        }

        const confirmMsg = (typeof auraCategories !== 'undefined' && auraCategories.strings && auraCategories.strings.confirmMerge)
            ? auraCategories.strings.confirmMerge
            : '¿Estás seguro de fusionar estas categorías? Las transacciones asociadas se reasignarán de inmediato.';

        if (!confirm(confirmMsg)) {
            return;
        }

        const $btn = $(this).prop('disabled', true);
        const originalBtnHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; vertical-align: middle;"></span> Fusionando...');

        const postAction = $('input[name="post_action"]:checked').val() || 'archive';
        const reassignChildren = $('#aura-merge-reassign-children').is(':checked');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_merge_categories',
                nonce: nonce,
                source_id: sourceId,
                target_id: targetId,
                post_action: postAction,
                reassign_children: reassignChildren ? 'true' : 'false'
            },
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (response.success) {
                    $('#aura-merge-modal').removeClass('active');
                    showToast(response.data?.message || 'Categorías fusionadas exitosamente');
                    loadCategories();
                } else {
                    showToast(response.data?.message || 'Error al fusionar categorías', true);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                showToast('Error de conexión al fusionar: ' + error, true);
            }
        });
    });

    // Barra de filtros Sticky dinámica con reducción de tamaño en Scroll
    function initStickyFilterBar() {
        const $filterBar = $('#aura-sticky-filter-bar');
        if (!$filterBar.length) return;

        let isStickyApplied = false;
        const checkScroll = () => {
            const scrollTop = $(window).scrollTop();
            // Si el scroll supera los 150px (pasando la hero card superior)
            if (scrollTop > 150) {
                if (!isStickyApplied) {
                    $filterBar.addClass('is-scrolled');
                    isStickyApplied = true;
                }
            } else {
                if (isStickyApplied) {
                    $filterBar.removeClass('is-scrolled');
                    isStickyApplied = false;
                }
            }
        };

        $(window).on('scroll.auraFilterBar resize.auraFilterBar', checkScroll);
        checkScroll();
    }

    // Inicializaciones de UX
    initStickyFilterBar();

    // Carga inicial
    loadCategories();
});
