/* global auraFinancialAccounts */
jQuery(function ($) {
    'use strict';

    if (window.AuraUI && typeof window.AuraUI.initTabs === 'function') {
        window.AuraUI.initTabs();
    }

    const $tableBody = $('#aura-accounts-table tbody');
    const $table = $('#aura-accounts-table');
    const $form = $('#aura-account-form');
    const $feedback = $('#aura-accounts-feedback');
    const $budgetForm = $('#aura-budget-form');
    const $search = $('#aura-accounts-search');
    const $pettyTableBody = $('#aura-petty-cash-table tbody');
    const $pettyForm = $('#aura-petty-cash-form');
    const $reimbursementsForm = $('#aura-reimbursements-form');
    const $reimbursementsPayForm = $('#aura-reimbursements-pay-form');
    const $reimbursementsTableBody = $('#aura-reimbursements-table tbody');
    const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    let pettyCashCanApprove = false;
    let budgetImportToken = '';
    let accountsTable = null;

    function setWizardStep(wizardId, stepNum) {
        const $wizard = $('#' + wizardId);
        if (!$wizard.length) {
            return;
        }

        const targetStep = parseInt(stepNum, 10) || 1;
        $wizard.find('.aura-modal-step').removeClass('is-active');
        $wizard.find('.aura-modal-step[data-step="' + targetStep + '"]').addClass('is-active');
        $wizard.find('.aura-modal-wizard__panel').removeClass('is-active');
        $wizard.find('.aura-modal-wizard__panel[data-step="' + targetStep + '"]').addClass('is-active');
    }

    function validateBudgetStepOne() {
        const year = parseInt($('#aura-budget-year').val(), 10);
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());

        if (!year || year < 2000 || year > 2100) {
            showFeedback('Define un año fiscal válido antes de continuar.', false);
            return false;
        }
        if (annualLimit <= 0) {
            showFeedback('El tope anual debe ser mayor a 0 para continuar.', false);
            return false;
        }
        return true;
    }

    function validatePettyStepOne() {
        const accountId = parseInt($('#aura-petty-account').val(), 10);
        const responsibleId = $('#aura-petty-responsible').val();
        const delivered = parseMoney($('#aura-petty-delivered').val());

        if (!accountId) {
            showFeedback('Selecciona una cuenta de caja chica antes de continuar.', false);
            return false;
        }
        if (!responsibleId) {
            showFeedback('Selecciona un responsable antes de continuar.', false);
            return false;
        }
        if (delivered <= 0) {
            showFeedback('El monto entregado debe ser mayor a 0.', false);
            return false;
        }
        return true;
    }

    function updatePettyStepThreeHint() {
        const delivered = parseMoney($('#aura-petty-delivered').val());
        const spent = parseMoney($('#aura-petty-spent').val());
        const returned = parseMoney($('#aura-petty-returned').val());
        const settlementId = parseInt($('#aura-petty-id').val(), 10);

        let hint = 'Regla: Entregado = Gastado + Devuelto antes de enviar a aprobación.';

        if (!settlementId) {
            hint += ' Primero registra la entrega para generar el ID de rendición.';
        } else if (Math.abs((spent + returned) - delivered) > 0.009) {
            hint += ' Ajusta los valores para que coincidan antes de enviar.';
        } else {
            hint += ' Los montos están consistentes para enviar.';
        }

        $('#aura-petty-step3-hint').text(hint);
    }

    function escapeHtml(text) {
        return $('<div/>').text(text || '').html();
    }

    function typeLabel(type) {
        const map = {
            bank_account: 'Cuenta Bancaria',
            petty_cash: 'Caja Chica',
            contributions_fund: 'Aportes',
            usd_cash: 'Caja USD',
            eur_cash: 'Caja EUR',
            cad_cash: 'Caja CAD',
            foreign_cash: 'Caja Extranjera',
            custom: 'Otro'
        };
        return map[type] || type;
    }

    function statusLabel(active) {
        return parseInt(active, 10) === 1 ? 'Activa' : 'Inactiva';
    }

    function formatNumber(value) {
        const amount = parseFloat(value || 0);
        return amount.toLocaleString('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function typeBadge(type) {
        let color = 'indigo';
        let icon = 'dashicons-bank';
        switch (String(type).toLowerCase()) {
            case 'bank':
                color = 'indigo';
                icon = 'dashicons-bank';
                break;
            case 'cash':
            case 'petty_cash':
                color = 'emerald';
                icon = 'dashicons-money-alt';
                break;
            case 'card':
            case 'credit_card':
                color = 'amber';
                icon = 'dashicons-cart';
                break;
            case 'savings':
                color = 'cyan';
                icon = 'dashicons-vault';
                break;
            case 'investment':
                color = 'violet';
                icon = 'dashicons-chart-area';
                break;
            default:
                color = 'slate';
                icon = 'dashicons-category';
        }
        return '<span class="badge badge-' + color + ' aura-tooltip-trigger" data-tooltip="Clasificación: ' + escapeHtml(typeLabel(type)) + '" style="display:inline-flex;align-items:center;gap:4px;cursor:help;">' +
            '<span class="dashicons ' + icon + '" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span>' +
            '<span>' + escapeHtml(typeLabel(type)) + '</span>' +
        '</span>';
    }

    function statusBadge(active) {
        const isActive = parseInt(active, 10) === 1;
        if (isActive) {
            return '<span class="status-traffic-light aura-tooltip-trigger" data-tooltip="Cuenta activa y disponible para operaciones" style="display:inline-flex;align-items:center;gap:6px;cursor:help;">' +
                '<span class="traffic-dot traffic-dot-success"></span>' +
                '<span class="badge badge-emerald">Activa</span>' +
            '</span>';
        }
        return '<span class="status-traffic-light aura-tooltip-trigger" data-tooltip="Cuenta deshabilitada para operaciones" style="display:inline-flex;align-items:center;gap:6px;cursor:help;">' +
            '<span class="traffic-dot traffic-dot-danger"></span>' +
            '<span class="badge badge-rose">Inactiva</span>' +
        '</span>';
    }

    function renderActionButtons(id) {
        return '<div class="aura-account-actions" style="display:inline-flex;gap:6px;align-items:center;">' +
            '<button type="button" class="btn btn-sm btn-secondary btn-lift aura-account-action is-edit aura-account-edit" data-id="' + id + '" data-tooltip="Editar detalles de la cuenta" aria-label="Editar">' +
                '<span class="dashicons dashicons-edit"></span>' +
            '</button>' +
            '<button type="button" class="btn btn-sm btn-danger btn-lift aura-account-action is-delete aura-account-delete" data-id="' + id + '" data-tooltip="Eliminar cuenta bancaria" aria-label="Eliminar">' +
                '<span class="dashicons dashicons-trash"></span>' +
            '</button>' +
        '</div>';
    }

    function showFeedback(message, ok) {
        $feedback
            .removeClass('notice-success notice-error')
            .addClass(ok ? 'notice-success' : 'notice-error')
            .html('<p>' + escapeHtml(message) + '</p>')
            .show();
    }

    function resetForm() {
        if ($form && $form.length && $form[0]) {
            $form[0].reset();
        }
        $('#aura-account-id').val('0');
        $('#aura-account-active').prop('checked', true);
        $('#aura-account-form-title').text('Nueva Cuenta');
    }

    function parseMoney(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : 0;
    }

    let currentAnnualSpent = 0; // Guardamos el valor actual de gasto anual
    const MONTH_NAMES_ES = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    let currentBudgetData = null;

    function updateBudgetMonthlyTotal() {
        let total = 0;
        $('.aura-budget-month-input').each(function () {
            total += parseMoney($(this).val());
        });
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
        const diff = annualLimit - total;

        $('#aura-budget-monthly-total').text('$' + formatNumber(total));
        $('#aura-budget-modal-annual-ref').text('$' + formatNumber(annualLimit));
        
        const $diff = $('#aura-budget-modal-diff');
        $diff.text('$' + formatNumber(diff));
        if (diff < 0) {
            $diff.css('color', '#ef4444').text('-$' + formatNumber(Math.abs(diff)) + ' (Excedido)');
        } else if (diff === 0) {
            $diff.css('color', '#10b981').text('$0.00 (Cuadrado exacto)');
        } else {
            $diff.css('color', '#3b82f6').text('$' + formatNumber(diff) + ' (Por asignar)');
        }

        return total;
    }

    function updateBudgetOverview() {
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
        const monthlyTotal = updateBudgetMonthlyTotal();
        const remaining = annualLimit - currentAnnualSpent;
        const usedPercent = annualLimit > 0 ? Math.min((currentAnnualSpent / annualLimit) * 100, 100) : 0;
        const policy = $('#aura-budget-policy').val() === 'block' ? 'Bloquear' : 'Advertir';

        $('#aura-budget-kpi-annual').text('$' + formatNumber(annualLimit));
        $('#aura-budget-kpi-monthly').text('$' + formatNumber(monthlyTotal));
        $('#aura-budget-kpi-remaining').text('$' + formatNumber(remaining));
        $('#aura-budget-kpi-policy').text(policy);
        $('#aura-budget-kpi-spent').text('$' + formatNumber(currentAnnualSpent));

        $('#aura-budget-progress-text').text(usedPercent.toFixed(1) + '%');
        $('#aura-budget-progress-bar').css('width', usedPercent + '%');
        
        if (usedPercent >= 100) {
            $('#aura-budget-progress-bar').css('background', 'var(--aura-danger)');
        } else if (usedPercent >= 80) {
            $('#aura-budget-progress-bar').css('background', 'var(--aura-warning)');
        } else {
            $('#aura-budget-progress-bar').css('background', '');
        }

        $('.aura-budget-progress-track').attr('aria-valuenow', usedPercent.toFixed(0));
    }

    function renderMonthlyBudgetTable(data) {
        const $tbody = $('#aura-budget-monthly-breakdown-table tbody');
        if (!$tbody.length) return;

        const months = (data && data.months) || [];
        const monthlySpent = (data && data.monthly_spent) || [];

        let rowsHtml = '';
        for (let i = 0; i < 12; i++) {
            const monthName = MONTH_NAMES_ES[i];
            const limit = typeof months[i] !== 'undefined' ? parseFloat(months[i]) : 0;
            const spent = typeof monthlySpent[i] !== 'undefined' ? parseFloat(monthlySpent[i]) : 0;
            const remaining = limit - spent;
            const pct = limit > 0 ? (spent / limit) * 100 : (spent > 0 ? 100 : 0);

            let statusBadge = '<span class="badge badge-slate">Sin asignar</span>';
            let fillModifier = 'micro-progress-fill--emerald';

            if (limit > 0) {
                if (spent > limit) {
                    statusBadge = '<span class="status-traffic-light"><span class="traffic-dot traffic-dot-danger"></span><span class="badge badge-rose">Excedido</span></span>';
                    fillModifier = 'micro-progress-fill--rose';
                } else if (pct >= 85) {
                    statusBadge = '<span class="status-traffic-light"><span class="traffic-dot traffic-dot-warning"></span><span class="badge badge-amber">Cerca del límite</span></span>';
                    fillModifier = 'micro-progress-fill--amber';
                } else {
                    statusBadge = '<span class="status-traffic-light"><span class="traffic-dot traffic-dot-success"></span><span class="badge badge-emerald">Normal</span></span>';
                    fillModifier = 'micro-progress-fill--emerald';
                }
            } else if (spent > 0) {
                statusBadge = '<span class="status-traffic-light"><span class="traffic-dot traffic-dot-danger"></span><span class="badge badge-rose">Sin límite fijado</span></span>';
                fillModifier = 'micro-progress-fill--rose';
            }

            const clampedPct = Math.min(Math.round(pct), 100);

            rowsHtml += `
                <tr class="table-row-hover-lift">
                    <td><strong>${monthName}</strong></td>
                    <td style="font-weight:600; color:var(--aura-text-heading,#0f172a);">$${formatNumber(limit)}</td>
                    <td style="color:${spent > 0 ? 'var(--aura-text-heading,#0f172a)' : '#94a3b8'};">$${formatNumber(spent)}</td>
                    <td style="font-weight:600; color:${remaining < 0 ? '#ef4444' : '#10b981'};">$${formatNumber(remaining)}</td>
                    <td style="min-width:140px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div class="micro-progress-bar" style="flex:1;">
                                <div class="micro-progress-fill ${fillModifier}" style="width:${clampedPct}%;"></div>
                            </div>
                            <span style="font-size:12px; font-weight:700; min-width:38px; text-align:right; color:var(--aura-text-muted,#475569);">${pct.toFixed(0)}%</span>
                        </div>
                    </td>
                    <td>${statusBadge}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-secondary btn-lift aura-budget-quick-edit-month" data-month="${i + 1}" data-tooltip="Modificar monto de ${monthName}" aria-label="Editar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </td>
                </tr>
            `;
        }

        $tbody.html(rowsHtml);
    }

    function renderConfiguredYearsTable(years) {
        const $tbody = $('#aura-budget-configured-years-table tbody');
        if (!$tbody.length) return;

        const currentActiveYear = parseInt($('#aura-global-budget-year').val(), 10) || new Date().getFullYear();

        if (!Array.isArray(years) || years.length === 0) {
            $tbody.html('<tr><td colspan="7" style="text-align:center; padding:20px; color:#64748b;">No hay presupuestos anuales configurados todavía. Haz clic en "Nuevo Año" para crear uno.</td></tr>');
            return;
        }

        let rowsHtml = '';
        years.forEach(function (row) {
            const yr = parseInt(row.fiscal_year, 10);
            const annualLimit = parseFloat(row.annual_limit || 0);
            const monthlyTotal = parseFloat(row.monthly_total || 0);
            const annualSpent = parseFloat(row.annual_spent || 0);
            const isSelected = yr === currentActiveYear;
            const policyText = row.exceed_policy === 'block' ? 'Bloquear gastos' : 'Solo advertir';

            rowsHtml += `
                <tr class="table-row-hover-lift" style="${isSelected ? 'background-color:rgba(59, 130, 246, 0.08);' : ''}">
                    <td>
                        <strong style="font-size:14px; color:var(--aura-text-heading,#0f172a);">${yr}</strong>
                        ${isSelected ? ' <span class="badge badge-primary" style="font-size:10px; padding:2px 6px; margin-left:4px;">Activo</span>' : ''}
                    </td>
                    <td style="font-weight:600; color:var(--aura-text-heading,#0f172a);">$${formatNumber(annualLimit)}</td>
                    <td style="color:var(--aura-text-muted,#475569);">$${formatNumber(monthlyTotal)}</td>
                    <td style="font-weight:600; color:var(--aura-text-heading,#0f172a);">$${formatNumber(annualSpent)}</td>
                    <td><span class="badge badge-slate">${policyText}</span></td>
                    <td>
                        ${row.is_active == 1 ? '<span class="status-traffic-light"><span class="traffic-dot traffic-dot-success"></span><span class="badge badge-emerald">Habilitado</span></span>' : '<span class="status-traffic-light"><span class="traffic-dot traffic-dot-danger"></span><span class="badge badge-slate">Inactivo</span></span>'}
                    </td>
                    <td>
                        <div style="display:inline-flex; gap:6px; align-items:center;">
                            <button type="button" class="btn btn-sm btn-primary btn-lift aura-budget-year-select-btn" data-year="${yr}" data-tooltip="Cargar y ver datos de ${yr}">
                                <span class="dashicons dashicons-visibility"></span> <span>Cargar</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary btn-lift aura-budget-year-clone-btn" data-year="${yr}" data-tooltip="Clonar presupuesto de ${yr} a otro año" aria-label="Clonar">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger btn-lift aura-budget-year-delete-btn" data-year="${yr}" data-tooltip="Eliminar presupuesto del año ${yr}" aria-label="Eliminar">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $tbody.html(rowsHtml);
    }

    function distributeEvenly(totalAmount) {
        const total = Math.max(0, parseFloat(totalAmount) || 0);
        const base = Math.floor((total / 12) * 100) / 100;
        const remainder = Math.round((total - (base * 12)) * 100) / 100;

        $('.aura-budget-month-input').each(function (idx) {
            if (idx === 11) {
                $(this).val((base + remainder).toFixed(2));
            } else {
                $(this).val(base.toFixed(2));
            }
        });

        updateBudgetMonthlyTotal();
    }

    function fillBudgetForm(data) {
        currentBudgetData = data;
        const env = (data && data.envelope) || null;
        const months = (data && data.months) || [];
        const year = data && data.year ? data.year : parseInt($('#aura-budget-year').val(), 10);

        $('#aura-budget-year').val(year);
        $('#aura-global-budget-year').val(year);
        $('#aura-budget-active-year-label').text(year);
        $('#aura-budget-progress-year-tag').text(year);

        $('#aura-budget-annual-limit').val(env ? env.annual_limit : 0);
        $('#aura-budget-policy').val(env ? env.exceed_policy : 'warn');
        currentAnnualSpent = env && env.annual_spent ? parseFloat(env.annual_spent) : 0;

        $('.aura-budget-month-input').each(function (idx) {
            const val = typeof months[idx] !== 'undefined' ? months[idx] : 0;
            $(this).val(val);
        });

        updateBudgetOverview();
        renderMonthlyBudgetTable(data);
        if (data && data.configured_years) {
            renderConfiguredYearsTable(data.configured_years);
        }
    }

    function loadBudgetByYear(customYear) {
        const year = customYear || parseInt($('#aura-global-budget-year').val(), 10) || parseInt($('#aura-budget-year').val(), 10) || new Date().getFullYear();

        $('#aura-global-budget-year').val(year);
        $('#aura-budget-year').val(year);
        $('#aura-budget-active-year-label').text(year);
        $('#aura-budget-progress-year-tag').text(year);

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_get',
            nonce: auraFinancialAccounts.nonce,
            year: year
        }).done(function (res) {
            if (res && res.success) {
                fillBudgetForm(res.data || {});
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function saveBudget() {
        const year = parseInt($('#aura-budget-year').val(), 10);
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
        const monthlyLimits = [];

        $('.aura-budget-month-input').each(function () {
            monthlyLimits.push(parseMoney($(this).val()));
        });

        const monthlySum = monthlyLimits.reduce(function (acc, val) { return acc + val; }, 0);
        if (monthlySum > annualLimit) {
            showFeedback('La suma mensual ($' + formatNumber(monthlySum) + ') supera el tope anual ($' + formatNumber(annualLimit) + ').', false);
            return;
        }

        $('#aura-budget-save-btn').prop('disabled', true).text(auraFinancialAccounts.i18n.saving);

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_save',
            nonce: auraFinancialAccounts.nonce,
            year: year,
            annual_limit: annualLimit,
            exceed_policy: $('#aura-budget-policy').val(),
            monthly_limits: monthlyLimits
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Presupuesto guardado correctamente.', true);
                window.AuraUI.closeModal('aura-finance-budget-modal');
                loadBudgetByYear(year);
                loadReports(); // Actualiza pestaña de Reportes y Cierres en sincronía
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-budget-save-btn').prop('disabled', false).text('Guardar presupuesto');
        });
    }

    function deleteBudgetYear(year) {
        if (!confirm('¿Estás seguro de que deseas eliminar el presupuesto configurado para el año ' + year + '?')) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_delete',
            nonce: auraFinancialAccounts.nonce,
            year: year
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Presupuesto eliminado.', true);
                loadBudgetByYear(year);
                loadReports();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function copyBudgetYear(fromYear, toYear) {
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_copy',
            nonce: auraFinancialAccounts.nonce,
            from_year: fromYear,
            to_year: toYear
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Presupuesto clonado con éxito.', true);
                loadBudgetByYear(toYear);
                loadReports();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function renderBudgetPreview(headers, rows) {
        const $thead = $('#aura-budget-preview-table thead');
        const $tbody = $('#aura-budget-preview-table tbody');

        const headHtml = '<tr>' + (headers || []).map(function (h) {
            return '<th>' + escapeHtml(h) + '</th>';
        }).join('') + '</tr>';
        $thead.html(headHtml);

        const bodyHtml = (rows || []).map(function (row) {
            return '<tr>' + row.map(function (cell) {
                return '<td>' + escapeHtml(cell) + '</td>';
            }).join('') + '</tr>';
        }).join('');
        $tbody.html(bodyHtml || '<tr><td colspan="99">Sin datos para mostrar.</td></tr>');
    }

    function renderBudgetMapping(headers, autoMapping) {
        const fields = [
            { key: 'year', label: 'Año fiscal *' },
            { key: 'annual_limit', label: 'Tope anual *' },
            { key: 'exceed_policy', label: 'Política exceso' },
            { key: 'jan', label: 'Ene' },
            { key: 'feb', label: 'Feb' },
            { key: 'mar', label: 'Mar' },
            { key: 'apr', label: 'Abr' },
            { key: 'may', label: 'May' },
            { key: 'jun', label: 'Jun' },
            { key: 'jul', label: 'Jul' },
            { key: 'aug', label: 'Ago' },
            { key: 'sep', label: 'Sep' },
            { key: 'oct', label: 'Oct' },
            { key: 'nov', label: 'Nov' },
            { key: 'dec', label: 'Dic' }
        ];

        const opts = ['<option value="">— Ignorar —</option>'];
        (headers || []).forEach(function (h, i) {
            opts.push('<option value="' + i + '">' + escapeHtml(h) + '</option>');
        });

        const html = fields.map(function (f) {
            return '<div class="aura-budget-mapping-row">' +
                '<label for="map-' + f.key + '"><strong>' + f.label + '</strong></label>' +
                '<select id="map-' + f.key + '" class="aura-budget-map" data-field="' + f.key + '">' + opts.join('') + '</select>' +
                '</div>';
        }).join('');

        $('#aura-budget-mapping-grid').html(html);

        const detected = autoMapping || {};
        Object.keys(detected).forEach(function (key) {
            if (detected[key] !== '' && typeof detected[key] !== 'undefined') {
                $('#map-' + key).val(String(detected[key]));
            }
        });
    }

    function collectBudgetMapping() {
        const mapping = {};
        $('.aura-budget-map').each(function () {
            const field = $(this).data('field');
            mapping[field] = $(this).val();
        });
        return mapping;
    }

    function importBudgetFile() {
        const input = document.getElementById('aura-budget-import-file');
        const file = input && input.files ? input.files[0] : null;

        if (!file) {
            showFeedback('Selecciona un archivo .csv o .xlsx para importar.', false);
            return;
        }

        const formData = new window.FormData();
        formData.append('action', 'aura_finance_budget_import');
        formData.append('nonce', auraFinancialAccounts.nonce);
        formData.append('budget_file', file);

        $('#aura-budget-import-btn').prop('disabled', true).text(auraFinancialAccounts.i18n.importing || 'Importando...');

        $.ajax({
            url: auraFinancialAccounts.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function (res) {
            if (res && res.success) {
                const msg = (res.data && res.data.message) || '¡Presupuestos importados exitosamente!';
                showFeedback(msg, true);
                const currentYr = parseInt($('#aura-global-budget-year').val(), 10) || new Date().getFullYear();
                loadBudgetByYear(currentYr);
                loadReports();
                $('#aura-budget-import-file').val('');
                $('#aura-budget-import-wizard').hide();
                $('#aura-budget-import-validation').hide().empty();
                budgetImportToken = '';
                return;
            }

            uploadForPreview(file);
        }).fail(function () {
            uploadForPreview(file);
        }).always(function () {
            $('#aura-budget-import-btn').prop('disabled', false).text('Analizar e Importar');
        });
    }

    function uploadForPreview(file) {
        const formData = new window.FormData();
        formData.append('action', 'aura_finance_budget_upload_preview');
        formData.append('nonce', auraFinancialAccounts.nonce);
        formData.append('budget_file', file);

        $.ajax({
            url: auraFinancialAccounts.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function (res) {
            if (res && res.success) {
                budgetImportToken = (res.data && res.data.token) || '';
                $('#aura-budget-import-wizard').show();
                $('#aura-budget-confirm-btn').prop('disabled', false);
                $('#aura-budget-import-validation').hide().empty();

                renderBudgetPreview((res.data && res.data.headers) || [], (res.data && res.data.preview) || []);
                renderBudgetMapping((res.data && res.data.headers) || [], (res.data && res.data.auto_mapping) || {});

                const summary = 'Archivo: ' + escapeHtml((res.data && res.data.filename) || '') +
                    ' | Filas detectadas: ' + ((res.data && res.data.total_rows) || 0);
                $('#aura-budget-import-summary').html(summary);
                showFeedback('Revisa las columnas detectadas y haz clic en Confirmar importación.', true);
                return;
            }

            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function validateBudgetImport() {
        if (!budgetImportToken) {
            showFeedback('Primero analiza un archivo para continuar.', false);
            return;
        }

        $('#aura-budget-validate-btn').prop('disabled', true).text('Validando...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_validate_preview',
            nonce: auraFinancialAccounts.nonce,
            token: budgetImportToken,
            mapping: collectBudgetMapping()
        }).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                const errors = data.errors || [];
                const html = '<p><strong>Total:</strong> ' + (data.total || 0) +
                    ' | <strong>Válidas:</strong> ' + (data.valid || 0) +
                    ' | <strong>Con error:</strong> ' + (data.invalid || 0) + '</p>' +
                    (errors.length ? '<ul><li>' + errors.map(escapeHtml).join('</li><li>') + '</li></ul>' : '<p>Sin errores de validación.</p>');

                $('#aura-budget-import-validation').html(html).show();
                $('#aura-budget-confirm-btn').prop('disabled', (data.valid || 0) === 0);
                showFeedback('Validación completada.', true);
                return;
            }

            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-budget-validate-btn').prop('disabled', false).text('2) Validar datos');
        });
    }

    function executeBudgetImport() {
        if (!budgetImportToken) {
            showFeedback('Token de importación inválido. Analiza el archivo nuevamente.', false);
            return;
        }

        $('#aura-budget-confirm-btn').prop('disabled', true).text(auraFinancialAccounts.i18n.importing || 'Importando...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_execute_import',
            nonce: auraFinancialAccounts.nonce,
            token: budgetImportToken,
            mapping: collectBudgetMapping()
        }).done(function (res) {
            if (res && res.success) {
                const errors = (res.data && res.data.errors) || [];
                const detail = errors.length ? (' Avisos: ' + errors.slice(0, 3).join(' | ')) : '';
                showFeedback(((res.data && res.data.message) || auraFinancialAccounts.i18n.importDone || 'Importación completada.') + detail, true);
                const currentYr = parseInt($('#aura-global-budget-year').val(), 10) || new Date().getFullYear();
                loadBudgetByYear(currentYr);
                loadReports();
                $('#aura-budget-import-file').val('');
                $('#aura-budget-import-validation').hide().empty();
                $('#aura-budget-import-wizard').hide();
                budgetImportToken = '';
                return;
            }

            const serverErrors = (res && res.data && res.data.errors) ? ' ' + res.data.errors.slice(0, 3).join(' | ') : '';
            showFeedback(((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error) + serverErrors, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-budget-confirm-btn').prop('disabled', false).text('Confirmar importación');
        });
    }

    function fillForm(account) {
        $('#aura-account-id').val(account.id || 0);
        $('#aura-account-name').val(account.name || '');
        $('#aura-account-type').val(account.account_type || 'bank_account');
        $('#aura-account-currency').val(account.currency || 'COP');
        $('#aura-account-institution').val(account.institution || '');
        $('#aura-account-number').val(account.account_number_masked || '');
        $('#aura-account-initial-balance').val(account.initial_balance || 0);
        $('#aura-account-current-balance').val(account.current_balance || 0);
        $('#aura-account-active').prop('checked', parseInt(account.is_active, 10) === 1);
        $('#aura-account-form-title').text('Editar Cuenta');
    }

    function updateAccountsKpis(accounts) {
        accounts = accounts || [];
        let total = accounts.length;
        let active = 0;
        let balLocal = 0;
        let balUsd = 0;

        accounts.forEach(function (a) {
            if (parseInt(a.is_active, 10) === 1) {
                active++;
            }
            const bal = parseFloat(a.current_balance || 0);
            const curr = String(a.currency || 'COP').toUpperCase();
            if (curr === 'USD') {
                balUsd += bal;
            } else {
                balLocal += bal;
            }
        });

        $('#aura-kpi-total-accounts').text(total);
        $('#aura-kpi-active-accounts').text(active);
        $('#aura-kpi-balance-cop').text(formatNumber(balLocal));
        $('#aura-kpi-balance-usd').text(formatNumber(balUsd));
    }

    function initAccountsTable() {
        if (!$.fn.DataTable) {
            return;
        }
        if ($.fn.DataTable.isDataTable('#aura-accounts-table')) {
            accountsTable = $('#aura-accounts-table').DataTable();
            return;
        }
        accountsTable = $('#aura-accounts-table').DataTable({
            responsive: true,
            pageLength: 25,
            language: {
                emptyTable: 'No hay cuentas registradas',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ cuentas',
                infoEmpty: 'Mostrando 0 a 0 de 0 cuentas',
                lengthMenu: 'Mostrar _MENU_ cuentas',
                paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
            },
            order: [[4, 'desc'], [0, 'asc']],
            columnDefs: [{ targets: [5], orderable: false }]
        });
    }

    function renderAccountTip(a) {
        const typeLabels = {
            'bank_account': 'Cuenta Bancaria',
            'petty_cash': 'Caja Chica / Menor',
            'contributions_fund': 'Fondo de Aportes',
            'usd_cash': 'Efectivo USD',
            'eur_cash': 'Efectivo EUR',
            'cad_cash': 'Efectivo CAD',
            'foreign_cash': 'Efectivo Divisas',
            'custom': 'Personalizada'
        };
        const typeIcons = {
            'bank_account': 'dashicons-building',
            'petty_cash': 'dashicons-portfolio',
            'contributions_fund': 'dashicons-groups',
            'usd_cash': 'dashicons-money-alt',
            'eur_cash': 'dashicons-money-alt',
            'cad_cash': 'dashicons-money-alt',
            'foreign_cash': 'dashicons-translation',
            'custom': 'dashicons-admin-generic'
        };
        const tLabel = typeLabels[a.account_type] || a.account_type || 'Cuenta';
        const tIcon = typeIcons[a.account_type] || 'dashicons-money-alt';
        const curr = String(a.currency || 'COP').toUpperCase();
        const initBal = parseFloat(a.initial_balance || 0);
        const curBal = parseFloat(a.current_balance || 0);
        const diffBal = curBal - initBal;
        const isActive = parseInt(a.is_active, 10) === 1;

        return '<div class="aura-tip-card" style="min-width:260px;max-width:320px;">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:linear-gradient(135deg,#0284c7 0%,#2563eb 100%);">' +
                    '<span class="dashicons ' + tIcon + '" style="font-size:20px;width:20px;height:20px;"></span>' +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name">' + escapeHtml(a.name) + '</div>' +
                    '<div class="aura-tip-card-badges">' +
                        '<span class="badge ' + (isActive ? 'badge-emerald' : 'badge-rose') + '" style="font-size:10px;padding:1px 6px;">' + (isActive ? '● Activa' : '○ Inactiva') + '</span>' +
                        '<span class="badge badge-indigo" style="font-size:10px;padding:1px 6px;">' + escapeHtml(curr) + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div style="font-size:11.5px;color:#94a3b8;margin-bottom:2px;">' +
                    '<span class="dashicons dashicons-location-alt" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>' +
                    escapeHtml(a.institution || 'Entidad financiera') + (a.account_number_masked ? ' • <code>' + escapeHtml(a.account_number_masked) + '</code>' : '') +
                '</div>' +
                '<div class="aura-tip-stat-grid">' +
                    '<div class="aura-tip-stat-item">' +
                        '<span class="aura-tip-stat-label">Tipo</span>' +
                        '<span class="aura-tip-stat-value" style="font-size:12px;color:#e2e8f0;">' + escapeHtml(tLabel) + '</span>' +
                    '</div>' +
                    '<div class="aura-tip-stat-item">' +
                        '<span class="aura-tip-stat-label">Saldo Inicial</span>' +
                        '<span class="aura-tip-stat-value" style="font-size:12px;color:#94a3b8;">$' + escapeHtml(formatNumber(initBal)) + '</span>' +
                    '</div>' +
                    '<div class="aura-tip-stat-item span-2">' +
                        '<span class="aura-tip-stat-label">Saldo Actual</span>' +
                        '<span class="aura-tip-stat-value is-delivered" style="font-size:15px;color:#38bdf8;">$' + escapeHtml(formatNumber(curBal)) + ' ' + escapeHtml(curr) + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span style="color:' + (diffBal >= 0 ? '#34d399' : '#f87171') + ';font-weight:700;">' +
                    (diffBal >= 0 ? '▲ +$' : '▼ -$') + escapeHtml(formatNumber(Math.abs(diffBal))) + ' variación' +
                '</span>' +
                '<span style="color:#94a3b8;font-size:10.5px;">Bancos &amp; Cuentas</span>' +
            '</div>' +
        '</div>';
    }

    function renderTable(accounts) {
        updateAccountsKpis(accounts);
        if (!$tableBody.length) {
            return;
        }

        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#aura-accounts-table')) {
            $('#aura-accounts-table').DataTable().destroy();
            accountsTable = null;
        }

        if (!accounts || !accounts.length) {
            $tableBody.html('<tr><td colspan="6" style="text-align:center;padding:20px;">No hay cuentas registradas.</td></tr>');
            return;
        }

        const rows = accounts.map(function (a) {
            const balance = formatNumber(a.current_balance || 0);
            const tipCard = renderAccountTip(a);

            // Tooltip enriquecido para Tipo de Cuenta
            const typeTip = '<div class="aura-tip-card" style="min-width:210px;padding:10px 12px;">' +
                '<div style="font-weight:700;font-size:12px;color:#ffffff;margin-bottom:4px;display:flex;align-items:center;gap:6px;"><span class="dashicons dashicons-tag" style="color:#38bdf8;"></span>Clasificación Contable</div>' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' + escapeHtml(typeLabel(a.account_type)) + '</div>' +
                '<div style="font-size:10.5px;color:#94a3b8;margin-top:4px;border-top:1px solid #334155;padding-top:4px;">Moneda: ' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + '</div>' +
            '</div>';

            // Tooltip enriquecido para Moneda
            const currTip = '<div class="aura-tip-card" style="min-width:200px;padding:10px 12px;">' +
                '<div style="font-weight:700;font-size:12px;color:#ffffff;margin-bottom:4px;display:flex;align-items:center;gap:6px;"><span class="dashicons dashicons-money-alt" style="color:#34d399;"></span>Divisa de Registro</div>' +
                '<div style="font-size:11.5px;color:#cbd5e1;">Código ISO: <strong style="color:#38bdf8;">' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + '</strong></div>' +
                '<div style="font-size:10.5px;color:#94a3b8;margin-top:4px;border-top:1px solid #334155;padding-top:4px;">Operativa en transferencias y conversiones</div>' +
            '</div>';

            return '<tr class="table-row-hover-lift">' +
                '<td><div class="aura-account-name aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(tipCard) + '" style="cursor:pointer;">' +
                    '<strong>' + escapeHtml(a.name) + '</strong>' +
                    '<small style="color:var(--aura-text-muted,#64748b);display:block;">' + escapeHtml(a.institution || 'Sin institución') + '</small>' +
                '</div></td>' +
                '<td><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(typeTip) + '" style="cursor:help;">' + typeBadge(a.account_type) + '</span></td>' +
                '<td><span class="badge badge-slate aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(currTip) + '" style="font-weight:700;cursor:help;">' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + '</span></td>' +
                '<td><div class="aura-account-balance aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(tipCard) + '" style="cursor:pointer;"><strong style="font-size:13px;color:var(--aura-text-heading,#0f172a);">' + escapeHtml(balance) + '</strong><small style="color:var(--aura-text-muted,#64748b);display:block;">' + escapeHtml(a.account_number_masked || 'Sin número visible') + '</small></div></td>' +
                '<td>' + statusBadge(a.is_active) + '</td>' +
                '<td>' + renderActionButtons(a.id) + '</td>' +
                '</tr>';
        });

        $tableBody.html(rows.join(''));
        initAccountsTable();
    }

    function populateCurrencyFilter(accounts) {
        const $sel = $('#aura-filter-currency');
        const current = $sel.val();
        const currencies = [];
        (accounts || []).forEach(function (a) {
            const c = String(a.currency || 'COP').toUpperCase();
            if (currencies.indexOf(c) === -1) { currencies.push(c); }
        });
        currencies.sort();
        const opts = ['<option value="">' + (auraFinancialAccounts.i18n.allCurrencies || 'Todas las monedas') + '</option>'];
        currencies.forEach(function (c) {
            opts.push('<option value="' + c + '">' + c + '</option>');
        });
        $sel.html(opts.join(''));
        if (current) { $sel.val(current); }
    }

    function getFilters() {
        return {
            type: $('#aura-filter-type').val(),
            currency: $('#aura-filter-currency').val(),
            status: $('#aura-filter-status').val(),
            search: ($('#aura-accounts-search').val() || '').toLowerCase().trim()
        };
    }

    function updateFilterUI(filters) {
        const active = [filters.type, filters.currency, filters.status, filters.search]
            .filter(function (v) { return v !== '' && v !== null && typeof v !== 'undefined'; }).length;
        const $count = $('#aura-filter-count');
        const $reset = $('#aura-filter-reset');
        if (active > 0) {
            $count.text(active + ' activo' + (active > 1 ? 's' : '')).show();
            $reset.show();
        } else {
            $count.hide();
            $reset.hide();
        }
    }

    function applyFilters() {
        const filters = getFilters();
        const all = window.auraAccountsCache || [];

        const filtered = all.filter(function (a) {
            if (filters.type && a.account_type !== filters.type) { return false; }
            if (filters.currency && String(a.currency || 'COP').toUpperCase() !== filters.currency) { return false; }
            if (filters.status !== '' && String(parseInt(a.is_active, 10)) !== filters.status) { return false; }
            if (filters.search) {
                const haystack = [
                    a.name || '',
                    a.institution || '',
                    a.currency || '',
                    a.account_number_masked || ''
                ].join(' ').toLowerCase();
                if (haystack.indexOf(filters.search) === -1) { return false; }
            }
            return true;
        });

        updateFilterUI(filters);
        renderTable(filtered);
    }

    function applyInitialFilters() {
        const initial = auraFinancialAccounts.initialFilters || {};
        if (initial.type) {
            $('#aura-filter-type').val(initial.type);
        }
        if (initial.currency) {
            $('#aura-filter-currency').val(initial.currency);
        }
    }

    function loadAccounts() {
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_accounts_list',
            nonce: auraFinancialAccounts.nonce
        }).done(function (res) {
            if (res && res.success) {
                window.auraAccountsCache = res.data.accounts || [];
                populateCurrencyFilter(window.auraAccountsCache);
                applyInitialFilters();
                applyFilters();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function saveAccount(data) {
        $('#aura-account-save-btn').prop('disabled', true).text(auraFinancialAccounts.i18n.saving);

        $.post(auraFinancialAccounts.ajaxUrl, data)
            .done(function (res) {
                if (res && res.success) {
                    showFeedback(auraFinancialAccounts.i18n.saved, true);
                    resetForm();
                    window.AuraUI.closeModal('aura-finance-account-modal');
                    loadAccounts();
                    return;
                }
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            })
            .fail(function () {
                showFeedback(auraFinancialAccounts.i18n.error, false);
            })
            .always(function () {
                $('#aura-account-save-btn').prop('disabled', false).text('Guardar cuenta');
            });
    }

    function deleteAccount(id) {
        if (!window.confirm(auraFinancialAccounts.i18n.deleteConfirm)) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_accounts_delete',
            nonce: auraFinancialAccounts.nonce,
            id: id
        }).done(function (res) {
            if (res && res.success) {
                showFeedback(auraFinancialAccounts.i18n.deleted, true);
                loadAccounts();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function pettyStatusLabel(status) {
        const map = {
            open: 'Abierta',
            submitted: 'En revisión',
            approved: 'Aprobada',
            closed: 'Cerrada',
            rejected: 'Rechazada'
        };
        return map[status] || status;
    }

    function pettyStatusBadge(status) {
        return '<span class="aura-petty-status is-' + escapeHtml(status) + '">' + escapeHtml(pettyStatusLabel(status)) + '</span>';
    }

    function toLocalDateInput(daysFromNow) {
        const d = new Date();
        d.setDate(d.getDate() + (daysFromNow || 0));
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function getEvidenceCount(evidenceJson) {
        if (!evidenceJson) {
            return 0;
        }
        try {
            const parsed = typeof evidenceJson === 'string' ? JSON.parse(evidenceJson) : evidenceJson;
            const attachments = parsed && Array.isArray(parsed.attachments) ? parsed.attachments : [];
            return attachments.length;
        } catch (e) {
            return 0;
        }
    }

    function parseEvidence(evidenceJson) {
        const result = { links: '', attachments: [] };
        if (!evidenceJson) {
            return result;
        }
        try {
            const parsed = typeof evidenceJson === 'string' ? JSON.parse(evidenceJson) : evidenceJson;
            result.links = parsed && parsed.links ? String(parsed.links) : '';
            result.attachments = parsed && Array.isArray(parsed.attachments) ? parsed.attachments : [];
        } catch (e) {
            result.links = String(evidenceJson);
        }
        return result;
    }

    let pettySelectedEvidenceFiles = [];

    function renderPettyFilesPreview() {
        const $container = $('#aura-petty-files-preview-list');
        if (!$container.length) return;
        if (!pettySelectedEvidenceFiles.length) {
            $container.empty().hide();
            return;
        }
        let html = '';
        pettySelectedEvidenceFiles.forEach(function (f, idx) {
            const isPdf = (f.name && f.name.toLowerCase().endsWith('.pdf')) || f.type === 'application/pdf';
            const icon = isPdf ? 'dashicons-media-document' : 'dashicons-format-image';
            const sizeKb = (f.size / 1024).toFixed(1);
            const sizeStr = sizeKb > 1024 ? (sizeKb / 1024).toFixed(2) + ' MB' : sizeKb + ' KB';
            html += '<div class="aura-petty-file-chip ' + (isPdf ? 'is-pdf' : 'is-img') + '">' +
                '<span class="dashicons ' + icon + ' aura-petty-file-icon"></span>' +
                '<span class="aura-petty-file-info" title="' + escapeHtml(f.name || '') + '">' +
                    '<strong class="aura-petty-file-name">' + escapeHtml(f.name || 'archivo') + '</strong>' +
                    '<span class="aura-petty-file-size">' + sizeStr + '</span>' +
                '</span>' +
                '<button type="button" class="aura-petty-file-remove" data-index="' + idx + '" title="Quitar archivo">✕</button>' +
            '</div>';
        });
        $container.html(html).show();
    }

    function renderEvidenceContent(row) {
        const evidence = parseEvidence(row && row.evidence_json ? row.evidence_json : null);
        
        let html = '<div class="aura-petty-evidence-view-wrap">';
        
        // Header de contexto
        html += '<div class="aura-petty-evidence-meta-bar">' +
            '<div><span>Responsable:</span> <strong>' + escapeHtml(row.responsible_name || '—') + '</strong></div>' +
            '<div><span>Cuenta:</span> <strong>' + escapeHtml(row.account_name || '—') + '</strong></div>' +
            '<div><span>Entregado:</span> <strong>' + escapeHtml(formatNumber(row.delivered_amount || 0)) + '</strong></div>' +
            '<div><span>Total Gastado:</span> <strong style="color:var(--aura-primary,#0284c7);">' + escapeHtml(formatNumber(row.spent_amount || 0)) + '</strong></div>' +
            '<div><span>Devuelto:</span> <strong style="color:#059669;">' + escapeHtml(formatNumber(row.returned_amount || 0)) + '</strong></div>' +
        '</div>';

        // Detalle de Gastos registrados
        if (row.expenses && row.expenses.length > 0) {
            html += '<h4 class="aura-petty-evidence-subtitle"><span class="dashicons dashicons-list-view"></span> Detalle de Gastos Registrados</h4>';
            html += '<div class="aura-dt-wrapper"><table class="aura-petty-evidence-table">' +
                '<thead><tr><th>Monto</th><th>Categoría</th><th>Concepto / Justificación</th></tr></thead><tbody>';
            row.expenses.forEach(function (exp) {
                const catName = exp.category_name || 'Sin categoría';
                // Filtrar URLs de conceptos de gastos
                const rawConcept = exp.notes || exp.concept || exp.receipt_url || '—';
                const concept = rawConcept.replace(/https?:\/\/[^\s,;]+/g, '').trim() || '—';
                // Si el campo contiene una URL de Drive, mostrar icono de enlace
                const hasUrl = rawConcept.match(/https?:\/\/[^\s,;]+/);
                const driveUrl = hasUrl ? hasUrl[0] : null;
                const conceptCell = driveUrl
                    ? escapeHtml(concept) + ' <a href="' + escapeHtml(driveUrl) + '" target="_blank" rel="noopener noreferrer" class="aura-drive-link-icon" title="Ver comprobante adjunto" style="margin-left:4px;color:#0284c7;text-decoration:none;font-size:14px;" onclick="event.stopPropagation()">📎</a>'
                    : escapeHtml(concept);
                html += '<tr>' +
                    '<td><strong>' + escapeHtml(formatNumber(exp.amount || 0)) + '</strong></td>' +
                    '<td><span class="aura-pill aura-pill--muted">' + escapeHtml(catName) + '</span></td>' +
                    '<td>' + conceptCell + '</td>' +
                '</tr>';
            });
            html += '</tbody></table></div>';
        }

        // Extraer comprobantes que hayan sido ingresados en las notas (como Google Drive u otros enlaces)
        const rawNotes = row && row.notes ? String(row.notes) : '';
        const notesUrls = rawNotes.match(/https?:\/\/[^\s,;]+/gi) || [];
        let cleanNotesText = rawNotes.replace(/https?:\/\/[^\s,;]+/gi, '').replace(/Comprobante\s*(Entrega|Egreso|Transferencia)?\s*:?/gi, '').trim();

        // Si hay una URL en las notas pero no estaba en los attachments de evidence, agregarla como comprobante inicial
        notesUrls.forEach(function (nUrl) {
            const alreadyIn = (evidence.attachments || []).some(function (att) { return att.url === nUrl; });
            if (!alreadyIn) {
                if (!evidence.attachments) evidence.attachments = [];
                evidence.attachments.unshift({
                    id: 0,
                    url: nUrl,
                    name: 'Comprobante de Entrega / Anticipo Inicial'
                });
            }
        });

        // Comprobantes y Adjuntos
        html += '<h4 class="aura-petty-evidence-subtitle" style="margin-top:16px;"><span class="dashicons dashicons-paperclip"></span> Comprobantes Digitales y Soportes</h4>';
        if (evidence.attachments && evidence.attachments.length > 0) {
            html += '<div class="aura-petty-evidence-cards-grid">';
            evidence.attachments.forEach(function (f) {
                const url = f && f.url ? String(f.url) : '#';
                const name = f && f.name ? String(f.name) : 'Comprobante';
                const isDrive = (url.indexOf('drive.google.com') !== -1) || (f && f.is_drive);
                const isLocalImg = !isDrive && (name.match(/\.(jpg|jpeg|png|webp|gif)$/i));
                const isPdf = name.toLowerCase().endsWith('.pdf') || (f && f.type === 'application/pdf');
                
                // Preview inline para imágenes locales
                const previewHtml = isLocalImg
                    ? '<div class="aura-petty-ev-thumb" style="margin:6px 0;text-align:center;">' +
                        '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' +
                            '<img src="' + escapeHtml(url) + '" alt="' + escapeHtml(name) + '" style="max-width:100%;max-height:120px;border-radius:6px;border:1px solid #e2e8f0;object-fit:cover;cursor:zoom-in;" loading="lazy" onerror="this.style.display=\'none\'">' +
                        '</a>' +
                      '</div>'
                    : '';

                html += '<div class="aura-petty-evidence-card">' +
                    previewHtml +
                    '<div class="aura-petty-evidence-card-icon">' +
                        '<span class="dashicons ' + (isPdf ? 'dashicons-media-document is-pdf-icon' : (isDrive ? 'dashicons-cloud-saved is-drive-icon' : 'dashicons-format-image is-img-icon')) + '"></span>' +
                    '</div>' +
                    '<div class="aura-petty-evidence-card-body">' +
                        '<div class="aura-petty-evidence-card-name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</div>' +
                        '<div class="aura-petty-evidence-card-source">' +
                            (isDrive
                                ? '<span class="aura-pill aura-pill--blue"><span class="dashicons dashicons-cloud-saved" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span> Google Drive</span>'
                                : '<span class="aura-pill aura-pill--muted"><span class="dashicons dashicons-portfolio" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span> Local / Adjunto</span>') +
                        '</div>' +
                    '</div>' +
                    '<div class="aura-petty-evidence-card-actions">' +
                        '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="button button-small button-primary" title="Ver comprobante" style="display:inline-flex;align-items:center;gap:4px;">' +
                            '<span class="dashicons dashicons-visibility" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> Ver' +
                        '</a>' +
                    '</div>' +
                '</div>';
            });
            html += '</div>';
        } else {
            html += '<p class="description" style="padding:8px 0;">No se adjuntaron archivos comprobantes en esta rendición.</p>';
        }

        // Observaciones adicionales limpias
        if (cleanNotesText && cleanNotesText !== '') {
            html += '<h4 class="aura-petty-evidence-subtitle" style="margin-top:14px;"><span class="dashicons dashicons-edit"></span> Observaciones y Propósito del Fondo</h4>';
            html += '<div class="aura-petty-evidence-notes-box" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;color:#334155;line-height:1.5;">' + escapeHtml(cleanNotesText) + '</div>';
        }

        html += '</div>';
        return html;
    }

    function openEvidenceModal(row) {
        const $content = $('#aura-petty-evidence-content');
        if (!$content.length) return;
        $content.html(renderEvidenceContent(row));
        if (window.AuraUI && typeof window.AuraUI.openModal === 'function') {
            window.AuraUI.openModal('aura-petty-evidence-modal');
        } else {
            $('#aura-petty-evidence-modal').show().attr('aria-hidden', 'false');
            syncBodyModalState();
        }
    }

    function closeEvidenceModal() {
        if (window.AuraUI && typeof window.AuraUI.closeModal === 'function') {
            window.AuraUI.closeModal('aura-petty-evidence-modal');
        } else {
            $('#aura-petty-evidence-modal').hide().attr('aria-hidden', 'true');
            syncBodyModalState();
        }
    }

    let isEvidenceBackdropMouseDown = false;
    $(document).on('mousedown', '#aura-petty-evidence-modal-backdrop', function (e) {
        isEvidenceBackdropMouseDown = (e.target === this);
    });

    $(document).on('click', '#aura-petty-evidence-modal-close, #aura-petty-evidence-modal-backdrop', function (e) {
        e.preventDefault();
        if (this.id === 'aura-petty-evidence-modal-backdrop') {
            if (!isEvidenceBackdropMouseDown || e.target !== this) {
                return;
            }
        }
        closeEvidenceModal();
        isEvidenceBackdropMouseDown = false;
    });

    function switchPettyTab(tabName) {
        $('.aura-petty-tab').removeClass('is-active');
        $('.aura-petty-panel').removeClass('is-active');
        $('.aura-petty-tab[data-tab="' + tabName + '"]').addClass('is-active');
        $('#aura-petty-panel-' + tabName).addClass('is-active');
    }

    function loadSettlementData(row) {
        $('#aura-petty-id').val(row.id || 0);
        $('#aura-petty-spent').val(row.spent_amount || 0);
        $('#aura-petty-returned').val(row.returned_amount || 0);
        $('#aura-petty-settle-notes').val(row.notes || '');
        $('#aura-petty-evidence').val('');
        $('#aura-petty-evidence-files').val('');
        pettySelectedEvidenceFiles = [];
        renderPettyFilesPreview();
        
        // Cargar filas de gastos
        const $tbody = $('#aura-petty-expenses-table tbody');
        $tbody.empty();
        
        if (row.expenses && row.expenses.length > 0) {
            row.expenses.forEach(function (exp) {
                addPettyExpenseRow(exp.amount, exp.category_id, exp.notes || exp.concept || exp.receipt_url || '');
            });
        } else {
            if (parseFloat(row.spent_amount) > 0) {
                addPettyExpenseRow(row.spent_amount, row.category_id, '');
            } else {
                addPettyExpenseRow(); // Fila inicial para empezar
            }
        }
        
        // Contexto resumen
        $('#aura-petty-sum-responsible').text(row.responsible_name || '—');
        $('#aura-petty-sum-account').text(row.account_name || '—');
        $('#aura-petty-sum-delivered').text(formatNumber(row.delivered_amount || 0));
        $('#aura-petty-sum-due').text((row.due_date || '').slice(0, 10) || '—');
        $('#aura-petty-loaded-summary').show();
        $('#aura-petty-settle-info').hide();
        $('#aura-petty-submit-btn').prop('disabled', false);

        // Badge de ID
        $('#aura-petty-settlement-id-badge').text('#' + row.id).show();
        $('#aura-petty-tab-settlement').prop('disabled', false);

        calculatePettyTotalSpent();
        switchPettyTab('settlement');
    }

    function addPettyExpenseRow(amount, categoryId, concept) {
        const template = document.getElementById('aura-petty-expense-row-template');
        if (!template) return;
        const clone = template.content.cloneNode(true);
        const $row = $(clone).find('tr');
        
        if (amount !== undefined && amount !== null && amount !== '') $row.find('.aura-petty-expense-amount').val(amount);
        if (categoryId !== undefined && categoryId !== null && categoryId !== '') $row.find('.aura-petty-expense-category').val(categoryId);
        if (concept !== undefined && concept !== null && concept !== '') $row.find('.aura-petty-expense-concept').val(concept);
        
        $('#aura-petty-expenses-table tbody').append($row);
    }

    function calculatePettyTotalSpent() {
        let total = 0;
        let count = 0;
        $('#aura-petty-expenses-table .aura-petty-expense-amount').each(function () {
            const val = parseFloat($(this).val()) || 0;
            if (val > 0) {
                total += val;
                count++;
            }
        });

        const id = parseInt($('#aura-petty-id').val(), 10);
        const list = window.auraPettyCashCache || [];
        const row = list.find(function (item) { return parseInt(item.id, 10) === id; });
        const delivered = row ? parseFloat(row.delivered_amount || 0) : parseFloat($('#aura-petty-delivered').val() || 0);

        // Actualizar inputs técnicos
        $('#aura-petty-spent').val(total.toFixed(2));

        // Card 1: Entregado
        $('#aura-petty-kpi-delivered').text(formatNumber(delivered));

        // Card 2: Total Gastado
        $('#aura-petty-kpi-spent').text(formatNumber(total));
        $('#aura-petty-kpi-receipt-count').text(count + (count === 1 ? ' comprobante' : ' comprobantes'));

        const pct = delivered > 0 ? Math.round((total / delivered) * 100) : 0;
        const clampedPct = Math.min(100, pct);
        $('#aura-petty-kpi-percent').text(pct + '%');
        const $progBar = $('#aura-petty-progress-bar');
        $progBar.css('width', clampedPct + '%');
        if (pct > 100) {
            $progBar.addClass('is-over').removeClass('is-warn');
        } else if (pct >= 85) {
            $progBar.addClass('is-warn').removeClass('is-over');
        } else {
            $progBar.removeClass('is-warn is-over');
        }

        // Card 3: Arqueo / Saldo de Rendición (Devuelto vs Excedente)
        const balance = delivered - total;
        const $cardBalance = $('#aura-petty-kpi-card-balance');
        const $badge = $('#aura-petty-kpi-badge');
        const $title = $('#aura-petty-kpi-balance-title');
        const $val = $('#aura-petty-kpi-balance-val');
        const $desc = $('#aura-petty-kpi-balance-desc');
        const $icon = $('#aura-petty-kpi-balance-icon');

        if (balance < -0.009) {
            // Excedente a favor del responsable
            const excedent = Math.abs(balance);
            $('#aura-petty-returned').val(0);
            $cardBalance.removeClass('is-return is-exact').addClass('is-excedent');
            $icon.attr('class', 'aura-petty-kpi-icon dashicons dashicons-warning');
            $badge.text('Reembolso');
            $title.text('Excedente a Reembolsar');
            $val.text(formatNumber(excedent));
            $desc.text('El gasto superó el fondo entregado. Se generará un saldo a favor del responsable.');
        } else if (Math.abs(balance) <= 0.009) {
            // Cuadrado exacto
            $('#aura-petty-returned').val('0.00');
            $cardBalance.removeClass('is-return is-excedent').addClass('is-exact');
            $icon.attr('class', 'aura-petty-kpi-icon dashicons dashicons-yes-alt');
            $badge.text('Cuadrado 100%');
            $title.text('Fondo Cuadrado Exacto');
            $val.text(formatNumber(0));
            $desc.text('El gasto coincide exactamente con lo entregado. Rendición balanceada.');
        } else {
            // Reintegro / Sobrante a devolver a caja
            $('#aura-petty-returned').val(balance.toFixed(2));
            $cardBalance.removeClass('is-exact is-excedent').addClass('is-return');
            $icon.attr('class', 'aura-petty-kpi-icon dashicons dashicons-money-alt');
            $badge.text('Reintegro');
            $title.text('Efectivo por Devolver a Caja');
            $val.text(formatNumber(balance));
            $desc.text('Monto en efectivo que el responsable debe reintegrar al fondo de caja.');
        }

        // Habilitar o deshabilitar botón de envío si hay al menos 1 gasto con monto > 0
        $('#aura-petty-submit-btn').prop('disabled', count === 0);
    }

    $(document).on('click', '#aura-petty-add-expense-btn', function () {
        addPettyExpenseRow();
        calculatePettyTotalSpent();
    });

    $(document).on('click', '.aura-petty-expense-remove', function () {
        $(this).closest('tr').remove();
        calculatePettyTotalSpent();
    });

    $(document).on('input change', '.aura-petty-expense-amount', function () {
        calculatePettyTotalSpent();
    });

    // Dropzone interactivo de comprobantes
    $(document).on('click', '#aura-petty-dropzone', function (e) {
        if ($(e.target).closest('input[type="file"]').length) return;
        $('#aura-petty-evidence-files').trigger('click');
    });

    $(document).on('dragover dragenter', '#aura-petty-dropzone', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('is-dragover');
    });

    $(document).on('dragleave drop', '#aura-petty-dropzone', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('is-dragover');
    });

    $(document).on('drop', '#aura-petty-dropzone', function (e) {
        const dt = e.originalEvent.dataTransfer;
        if (dt && dt.files && dt.files.length) {
            Array.prototype.forEach.call(dt.files, function (file) {
                pettySelectedEvidenceFiles.push(file);
            });
            renderPettyFilesPreview();
        }
    });

    $(document).on('change', '#aura-petty-evidence-files', function () {
        if (this.files && this.files.length) {
            Array.prototype.forEach.call(this.files, function (file) {
                pettySelectedEvidenceFiles.push(file);
            });
            renderPettyFilesPreview();
        }
        $(this).val('');
    });

    $(document).on('click', '.aura-petty-file-remove', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const idx = parseInt($(this).data('index'), 10);
        if (!isNaN(idx) && idx >= 0 && idx < pettySelectedEvidenceFiles.length) {
            pettySelectedEvidenceFiles.splice(idx, 1);
            renderPettyFilesPreview();
        }
    });

    // Atajos de días para fecha límite (+3d, +5d, +7d, +15d o +1m, +3m, +6m, +1a)
    $(document).on('click', '.aura-petty-shortcut-btn', function (e) {
        e.preventDefault();
        const days = parseInt($(this).data('days'), 10) || 5;
        $('#aura-petty-due-date').val(toLocalDateInput(days));
    });

    function updatePettyTypeUI(type) {
        const isProgram = (type === 'program_budget');
        const $cards = $('.aura-petty-type-card');
        $cards.removeClass('is-selected').each(function () {
            const cardType = $(this).data('type');
            if (cardType === type) {
                $(this).addClass('is-selected');
                $(this).css({
                    'border-color': '#2563eb',
                    'background': '#eff6ff'
                });
            } else {
                $(this).css({
                    'border-color': '#cbd5e1',
                    'background': '#ffffff'
                });
            }
        });

        const $label = $('#aura-petty-due-date-label');
        const $shortcuts = $('#aura-petty-shortcuts-container');
        const $help = $('#aura-petty-due-date-help');

        if (isProgram) {
            if ($label.length) {
                $label.html('<strong>Fecha estimada fin de programa</strong>');
            }
            if ($shortcuts.length) {
                $shortcuts.html(
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="30">+1m</button>' +
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="90">+3m</button>' +
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="180">+6m</button>' +
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="365">+1a</button>'
                );
            }
            if ($help.length) {
                $help.text('Presupuesto de mediano/largo plazo (ej. 6 meses). Se rinde de forma continua sin alarmas de vencimiento de corto plazo.');
            }
            const curVal = $('#aura-petty-due-date').val();
            const fiveDaysVal = toLocalDateInput(parseInt(auraFinancialAccounts.defaultDueDays, 10) || 5);
            if (!curVal || curVal === fiveDaysVal) {
                $('#aura-petty-due-date').val(toLocalDateInput(180));
            }
        } else {
            if ($label.length) {
                $label.html('<strong>Fecha límite rendición</strong>');
            }
            if ($shortcuts.length) {
                $shortcuts.html(
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="3">+3d</button>' +
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="5">+5d</button>' +
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="7">+7d</button>' +
                    '<button type="button" class="aura-petty-shortcut-btn" data-days="15">+15d</button>'
                );
            }
            if ($help.length) {
                $help.text('Plazo límite para compra y devolución (SLA normal: 3 a 5 días).');
            }
            const curVal = $('#aura-petty-due-date').val();
            const sixMonthsVal = toLocalDateInput(180);
            if (!curVal || curVal === sixMonthsVal) {
                $('#aura-petty-due-date').val(toLocalDateInput(parseInt(auraFinancialAccounts.defaultDueDays, 10) || 5));
            }
        }
    }

    $(document).on('change', 'input[name="aura_petty_type"]', function () {
        updatePettyTypeUI($(this).val());
    });

    $(document).on('click', '.aura-petty-type-card', function (e) {
        if (e.target && e.target.tagName && e.target.tagName.toLowerCase() === 'input') {
            return;
        }
        const $radio = $(this).find('input[name="aura_petty_type"]');
        if ($radio.length && !$radio.prop('checked')) {
            $radio.prop('checked', true).trigger('change');
        }
    });

    function resetPettyForm() {
        var $deliveryForm = $('#aura-petty-delivery-form');
        if ($deliveryForm.length && $deliveryForm[0]) {
            $deliveryForm[0].reset();
        }
        if ($pettyForm.length && $pettyForm[0]) {
            $pettyForm[0].reset();
        }
        $('#aura-petty-id').val('0');
        $('#aura-petty-responsible').val('');
        $('#aura-petty-responsible-name').val('');
        $('#aura-petty-responsible-preview').hide();
        $('#aura-petty-expenses-table tbody').empty();
        $('#aura-petty-spent').val('0');
        $('#aura-petty-returned').val('0');
        $('#aura-petty-excedent-msg').text('');
        $('input[name="aura_petty_type"][value="purchase_errand"]').prop('checked', true);
        updatePettyTypeUI('purchase_errand');
        $('#aura-petty-due-date').val(toLocalDateInput(parseInt(auraFinancialAccounts.defaultDueDays, 10) || 5));
        $('#aura-petty-evidence-files').val('');
        $('#aura-petty-settle-notes').val('');
        $('#aura-petty-delivery-receipt-url').val('');
        $('.aura-petty-receipt-mode-btn[data-mode="file"]').addClass('is-active').css({background: '#ffffff', color: '#0f172a', 'box-shadow': '0 1px 2px rgba(0,0,0,0.06)'});
        $('.aura-petty-receipt-mode-btn[data-mode="url"]').removeClass('is-active').css({background: 'transparent', color: '#64748b', 'box-shadow': 'none'});
        $('#aura-petty-delivery-receipt-file-wrap').show();
        $('#aura-petty-delivery-receipt-url-wrap').hide();
        pettySelectedEvidenceFiles = [];
        renderPettyFilesPreview();

        // Reset KPIs
        $('#aura-petty-kpi-delivered').text('$ 0.00');
        $('#aura-petty-kpi-spent').text('$ 0.00');
        $('#aura-petty-kpi-receipt-count').text('0 comprobantes');
        $('#aura-petty-kpi-percent').text('0%');
        $('#aura-petty-progress-bar').css('width', '0%');
        $('#aura-petty-kpi-balance-val').text('$ 0.00');
        $('#aura-petty-kpi-card-balance').removeClass('is-exact is-excedent').addClass('is-return');
        $('#aura-petty-kpi-badge').text('Reintegro');
        $('#aura-petty-kpi-balance-title').text('Efectivo por Devolver');
        $('#aura-petty-kpi-balance-desc').text('Efectivo que el responsable debe reintegrar al fondo.');

        // Reset settlement tab
        $('#aura-petty-loaded-summary').hide();
        $('#aura-petty-settle-info').show();
        $('#aura-petty-submit-btn').prop('disabled', true);
        $('#aura-petty-tab-settlement').prop('disabled', true);
        $('#aura-petty-settlement-id-badge').hide().text('');
        switchPettyTab('delivery');
    }

    function fillPettyAccounts(accounts, allAccountsList) {
        const $sel = $('#aura-petty-account');
        if ($sel.length) {
            const opts = ['<option value="">Selecciona una cuenta...</option>'];
            (accounts || []).forEach(function (a) {
                opts.push('<option value="' + parseInt(a.id, 10) + '">' +
                    escapeHtml(a.name) + ' (' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + ')</option>');
            });
            $sel.html(opts.join(''));
        }

        const $selOrigin = $('#aura-petty-origin-account');
        if ($selOrigin.length) {
            const originOpts = ['<option value="">Sin desembolso bancario directo (Efectivo manual)</option>'];
            const allAccs = (allAccountsList && allAccountsList.length) ? allAccountsList : (window.auraAccountsCache || accounts || []);
            allAccs.forEach(function (a) {
                if (a.account_type !== 'petty_cash') {
                    originOpts.push('<option value="' + parseInt(a.id, 10) + '">' +
                        escapeHtml(a.name) + ' (' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + ')</option>');
                }
            });
            $selOrigin.html(originOpts.join(''));
        }
    }

    function updatePettyCashKPIs(rows) {
        let totalDelivered = 0;
        let totalSpent = 0;
        let totalReturned = 0;
        let countActive = 0;
        let countOverdue = 0;

        (rows || []).forEach(function (r) {
            totalDelivered += parseFloat(r.delivered_amount || 0);
            totalSpent += parseFloat(r.spent_amount || 0);
            totalReturned += parseFloat(r.returned_amount || 0);
            if (r.status === 'open' || r.status === 'submitted') {
                countActive++;
            }
            if (parseInt(r.is_overdue, 10) === 1 && r.status !== 'approved' && r.status !== 'closed') {
                countOverdue++;
            }
        });

        $('#aura-kpi-petty-delivered').text('$' + formatNumber(totalDelivered));
        $('#aura-kpi-petty-spent').text('$' + formatNumber(totalSpent));
        $('#aura-kpi-petty-returned').text('$' + formatNumber(totalReturned));
        $('#aura-kpi-petty-active').text(countActive);
        $('#aura-kpi-petty-overdue').text(countOverdue);

        if (countOverdue > 0) {
            $('#aura-kpi-petty-overdue-box').addClass('is-alert');
        } else {
            $('#aura-kpi-petty-overdue-box').removeClass('is-alert');
        }
    }

    function filterPettyCash() {
        const search = ($('#aura-petty-search').val() || '').toLowerCase().trim();
        const custodian = $('#aura-petty-filter-custodian').val() || '';
        const status = $('#aura-petty-filter-status').val() || '';
        const overdue = $('#aura-petty-filter-overdue').val() || '';
        const deliveryType = $('#aura-petty-filter-delivery-type').val() || '';

        const allRows = window.auraPettyCashCache || [];
        let activeFiltersCount = 0;
        if (search) activeFiltersCount++;
        if (custodian) activeFiltersCount++;
        if (status) activeFiltersCount++;
        if (overdue) activeFiltersCount++;
        if (deliveryType) activeFiltersCount++;

        const filtered = allRows.filter(function (r) {
            if (search) {
                const hay = [
                    r.responsible_name || '',
                    r.account_name || '',
                    r.notes || '',
                    r.created_at || '',
                    r.due_date || '',
                    r.delivered_amount || '',
                    pettyStatusLabel(r.status) || '',
                    (r.delivery_type === 'program_budget' ? 'programa presupuesto' : 'compra diligencia')
                ].join(' ').toLowerCase();
                if (!hay.includes(search)) {
                    return false;
                }
            }

            const isThirdParty = (r.counterparty_id && parseInt(r.counterparty_id, 10) > 0);
            if (custodian === 'third_party' && !isThirdParty) {
                return false;
            }
            if (custodian === 'internal' && isThirdParty) {
                return false;
            }

            if (status && r.status !== status) {
                return false;
            }

            if (deliveryType && (r.delivery_type || 'purchase_errand') !== deliveryType) {
                return false;
            }

            const isOverdue = parseInt(r.is_overdue, 10) === 1 && r.status !== 'approved' && r.status !== 'closed';
            if (overdue === 'overdue' && !isOverdue) {
                return false;
            }
            if (overdue === 'ontime' && isOverdue) {
                return false;
            }

            return true;
        });

        if (activeFiltersCount > 0) {
            $('#aura-petty-filter-count').text(filtered.length + ' de ' + allRows.length).removeClass('aura-hidden');
            $('#aura-petty-filter-reset').removeClass('aura-hidden');
        } else {
            $('#aura-petty-filter-count').addClass('aura-hidden');
            $('#aura-petty-filter-reset').addClass('aura-hidden');
        }

        renderPettyTable(filtered);
    }

    function renderPettyResponsibleTip(r) {
        const isThirdParty = (r.counterparty_id && parseInt(r.counterparty_id, 10) > 0);
        const roleBadge = isThirdParty 
            ? '<span class="aura-tip-badge aura-tip-badge--company" style="background:rgba(2,132,199,0.25);color:#38bdf8;border:1px solid rgba(56,189,248,0.4);font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:999px;">👤 Tercero (Diligencia Externa)</span>' 
            : '<span class="aura-tip-badge aura-tip-badge--role" style="background:rgba(148,163,184,0.25);color:#cbd5e1;border:1px solid rgba(148,163,184,0.4);font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:999px;">🏢 Personal Interno (Área)</span>';
        const balance = parseFloat(r.delivered_amount || 0) - parseFloat(r.spent_amount || 0) - parseFloat(r.returned_amount || 0);

        const avatarContent = (r.responsible_avatar_url && String(r.responsible_avatar_url).trim() !== '')
            ? '<img src="' + escapeHtml(r.responsible_avatar_url) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:12px;display:block;">'
            : (isThirdParty ? '👤' : '🏢');

        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:' + (isThirdParty ? 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)' : 'linear-gradient(135deg, #475569 0%, #334155 100%)') + ';">' +
                    avatarContent +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name">' + escapeHtml(r.responsible_name || 'Sin responsable') + '</div>' +
                    '<div class="aura-tip-card-badges">' + roleBadge + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div class="aura-tip-stat-grid">' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Entregado</span><span class="aura-tip-stat-value is-delivered">$' + escapeHtml(formatNumber(r.delivered_amount || 0)) + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Comprobado</span><span class="aura-tip-stat-value is-spent">$' + escapeHtml(formatNumber(r.spent_amount || 0)) + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Devuelto</span><span class="aura-tip-stat-value is-returned">$' + escapeHtml(formatNumber(r.returned_amount || 0)) + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Saldo Pendiente</span><span class="aura-tip-stat-value is-balance">$' + escapeHtml(formatNumber(balance)) + '</span></div>' +
                '</div>' +
                (r.notes ? '<div style="margin-top:6px;font-size:11.5px;line-height:1.4;color:#cbd5e1;"><strong style="color:#94a3b8;display:block;margin-bottom:2px;">📝 Propósito / Diligencia:</strong>' + escapeHtml(r.notes) + '</div>' : '') +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span>Caja: ' + escapeHtml(r.account_name || 'Caja Chica') + '</span>' +
                '<span>ID #' + r.id + '</span>' +
            '</div>' +
        '</div>';
    }

    function renderPettyDueTip(r) {
        const isProgram = (r.delivery_type === 'program_budget');
        const isOverdue = parseInt(r.is_overdue, 10) === 1 && r.status !== 'approved' && r.status !== 'closed';
        const dueDate = (r.due_date || '').slice(0, 10) || 'Sin fecha fija';
        const createdDate = (r.created_at || '').slice(0, 10) || '—';

        if (isProgram) {
            return '<div class="aura-tip-card">' +
                '<div class="aura-tip-card-header">' +
                    '<div class="aura-tip-avatar-box" style="background:' + (isOverdue ? 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)' : 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)') + ';">' +
                        (isOverdue ? '⚠️' : '📦') +
                    '</div>' +
                    '<div class="aura-tip-card-title-box">' +
                        '<div class="aura-tip-card-name">' + (isOverdue ? 'Vigencia de Programa Expirada' : 'Presupuesto de Programa Activo') + '</div>' +
                        '<div class="aura-tip-card-badges">' +
                            '<span class="aura-tip-badge" style="background:' + (isOverdue ? 'rgba(239,68,68,0.2)' : 'rgba(37,99,235,0.2)') + ';color:' + (isOverdue ? '#fca5a5' : '#93c5fd') + ';font-size:10.5px;padding:2px 8px;border-radius:999px;">' +
                                (isOverdue ? 'Período Finalizado' : 'En Ejecución Operativa') +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="aura-tip-card-body">' +
                    '<div class="aura-tip-stat-grid">' +
                        '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Fecha Apertura</span><span class="aura-tip-stat-value">' + createdDate + '</span></div>' +
                        '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Cierre Programa</span><span class="aura-tip-stat-value" style="color:' + (isOverdue ? '#fca5a5' : '#93c5fd') + ';">' + dueDate + '</span></div>' +
                    '</div>' +
                    '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' +
                        (isOverdue 
                            ? 'El período previsto para este programa ya finalizó. Se recomienda proceder con el cierre y conciliación de la caja.' 
                            : 'Fondo de programa a mediano/largo plazo (ej. 6 meses). Se rinde de forma continua contra el presupuesto asignado sin alarmas de vencimiento de corto plazo.') +
                    '</div>' +
                '</div>' +
                '<div class="aura-tip-card-footer">' +
                    '<span>Responsable: ' + escapeHtml(r.responsible_name || '—') + '</span>' +
                '</div>' +
            '</div>';
        }

        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:' + (isOverdue ? 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)' : 'linear-gradient(135deg, #059669 0%, #047857 100%)') + ';">' +
                    (isOverdue ? '⚠️' : '⏱️') +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name">' + (isOverdue ? 'Plazo Vencido de Rendición' : 'Plazo SLA en Vigencia') + '</div>' +
                    '<div class="aura-tip-card-badges">' +
                        '<span class="aura-tip-badge" style="background:' + (isOverdue ? 'rgba(239,68,68,0.2)' : 'rgba(16,185,129,0.2)') + ';color:' + (isOverdue ? '#fca5a5' : '#6ee7b7') + ';font-size:10.5px;padding:2px 8px;border-radius:999px;">' +
                            (isOverdue ? 'En Mora' : 'Al Día') +
                        '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div class="aura-tip-stat-grid">' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Fecha Entrega</span><span class="aura-tip-stat-value">' + createdDate + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Fecha Límite</span><span class="aura-tip-stat-value" style="color:' + (isOverdue ? '#fca5a5' : '#6ee7b7') + ';">' + dueDate + '</span></div>' +
                '</div>' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' +
                    (isOverdue 
                        ? 'Este fondo de compra superó su fecha límite sin haber justificado los comprobantes o devuelto el cambio. Requiere contacto prioritario con el responsable.' 
                        : 'El custodio se encuentra dentro de los plazos establecidos para presentar facturas de compra y reintegrar el sobrante.') +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span>Responsable: ' + escapeHtml(r.responsible_name || '—') + '</span>' +
            '</div>' +
        '</div>';
    }

    function renderPettyAccountTip(r) {
        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">' +
                    '🏦' +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name">' + escapeHtml(r.account_name || 'Caja Chica') + '</div>' +
                    '<div class="aura-tip-card-badges">' +
                        '<span class="aura-tip-badge" style="background:rgba(2,132,199,0.25);color:#38bdf8;font-size:10.5px;padding:2px 8px;border-radius:999px;">Cuenta de Custodia</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' +
                    'Caja operativa o cuenta puente donde radican los fondos físicos para compras menores y diligencias de esta entrega.' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span>Asignado a: ' + escapeHtml(r.responsible_name || '—') + '</span>' +
            '</div>' +
        '</div>';
    }

    function renderPettyMoneyTip(r, metric) {
        const balance = parseFloat(r.delivered_amount || 0) - parseFloat(r.spent_amount || 0) - parseFloat(r.returned_amount || 0);
        let title = 'Arqueo Contable de la Entrega';
        if (metric === 'delivered') title = 'Fondo Inicial Otorgado';
        if (metric === 'spent') title = 'Compras Justificadas con Facturas';
        if (metric === 'returned') title = 'Efectivo Sobrante Reintegrado';

        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">' +
                    '💵' +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name">' + title + '</div>' +
                    '<div class="aura-tip-card-badges">' +
                        '<span class="aura-tip-badge" style="background:rgba(59,130,246,0.25);color:#93c5fd;font-size:10.5px;padding:2px 8px;border-radius:999px;">Liquidación Financiera</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div class="aura-tip-stat-grid">' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">1. Entregado Base</span><span class="aura-tip-stat-value is-delivered">$' + escapeHtml(formatNumber(r.delivered_amount || 0)) + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">2. Gastos Facturados</span><span class="aura-tip-stat-value is-spent">$' + escapeHtml(formatNumber(r.spent_amount || 0)) + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">3. Reintegro Efectivo</span><span class="aura-tip-stat-value is-returned">$' + escapeHtml(formatNumber(r.returned_amount || 0)) + '</span></div>' +
                    '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">4. Saldo por Liquidar</span><span class="aura-tip-stat-value is-balance">$' + escapeHtml(formatNumber(balance)) + '</span></div>' +
                '</div>' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' +
                    (balance <= 0.01 && balance >= -0.01 
                        ? '✅ Cuadre perfecto: La suma de gastos comprobados y efectivo devuelto coincide con el fondo entregado.' 
                        : (balance > 0.01 
                            ? '⚠️ Pendiente de rendir: El responsable aún debe presentar facturas o devolver $' + escapeHtml(formatNumber(balance)) + '.' 
                            : 'ℹ️ Excedente: Los gastos superaron el monto entregado en $' + escapeHtml(formatNumber(Math.abs(balance))) + '.')) +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span>Responsable: ' + escapeHtml(r.responsible_name || '—') + '</span>' +
                '<span>Entrega #' + r.id + '</span>' +
            '</div>' +
        '</div>';
    }

    function renderPettyStatusTip(r) {
        const s = r.status;
        let title = 'Fase Contable';
        let desc = '';
        let icon = '🏷️';
        let color = '#38bdf8';

        if (s === 'open') {
            title = 'Entrega Abierta (Fondo en Poder)';
            desc = 'El dinero se encuentra en custodia del responsable para efectuar las compras asignadas. Aún no se ha enviado la liquidación formal.';
            icon = '⏳';
            color = '#38bdf8';
        } else if (s === 'submitted') {
            title = 'Rendida (Pendiente de Aprobación)';
            desc = 'El custodio cargó las facturas y declaró el arqueo final. Finanzas o el supervisor deben revisar y validar los comprobantes.';
            icon = '📋';
            color = '#f59e0b';
        } else if (s === 'approved') {
            title = 'Aprobada (Paz y Salvo Contable)';
            desc = 'Los gastos han sido verificados y aprobados por Finanzas. El custodio queda en paz y salvo por este fondo entregado.';
            icon = '✅';
            color = '#10b981';
        } else if (s === 'closed') {
            title = 'Cerrada Definitivamente';
            desc = 'Operación concluida, conciliada y archivada en el libro mayor contable.';
            icon = '🔒';
            color = '#94a3b8';
        } else if (s === 'rejected') {
            title = 'Rendición Rechazada';
            desc = 'Se encontraron discrepancias en los comprobantes o faltan facturas tributarias válidas. El custodio debe corregir los gastos.';
            icon = '❌';
            color = '#ef4444';
        }

        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:rgba(255,255,255,0.1);color:' + color + ';">' +
                    icon +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name" style="color:' + color + ';">' + title + '</div>' +
                    '<div class="aura-tip-card-badges">' +
                        '<span class="aura-tip-badge" style="background:rgba(255,255,255,0.15);color:#ffffff;font-size:10.5px;padding:2px 8px;border-radius:999px;">Estado: ' + pettyStatusLabel(s) + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' + desc + '</div>' +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span>Entrega #' + r.id + '</span>' +
            '</div>' +
        '</div>';
    }

    function renderPettyChildCard(r, canSubmit, canApprove, canClose, evidenceCount, isThirdParty, respBadge, isOverdue, dueDateDisplay, isProgram) {
        const balance = parseFloat(r.delivered_amount || 0) - parseFloat(r.spent_amount || 0) - parseFloat(r.returned_amount || 0);
        const statusBadge = pettyStatusBadge(r.status);
        const overdueBadge = isOverdue ? '<span class="aura-badge aura-badge-red" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;">⚠️ Vencida</span>' : '';
        const evidenceBadge = evidenceCount > 0 ? '<span class="aura-badge" style="background:#e0f2fe;color:#0284c7;border:1px solid #bae6fd;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;">' + evidenceCount + ' comprobante(s)</span>' : '';

        const typeHeroBadge = isProgram
            ? '<span class="aura-pill aura-pill--program-lg" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;"><span class="dashicons dashicons-portfolio"></span> Presupuesto de Programa</span>'
            : '<span class="aura-pill aura-pill--errand-lg" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;padding:3px 10px;border-radius:8px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><span class="dashicons dashicons-cart"></span> Compra puntual / Diligencia</span>';

        const hasDriveOrReceipt = /https?:\/\/[^\s,;]+/i.test(r.notes || '');

        // ── BOTONES DEL PANEL LATERAL IZQUIERDO ──
        const sidebarButtons = [];
        sidebarButtons.push(
            '<button type="button" class="btn btn-sm btn-primary btn-shimmer btn-lift aura-child-side-btn aura-child-side-btn--primary aura-petty-pick" data-id="' + r.id + '" data-tooltip="Cargar facturas y rendir gastos">' +
                '<span class="dashicons dashicons-edit"></span>' +
                '<span>Rendir Gastos</span>' +
            '</button>'
        );

        if (evidenceCount > 0 || (r.evidence_json && String(r.evidence_json).trim() !== '') || hasDriveOrReceipt) {
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-secondary btn-lift aura-child-side-btn aura-child-side-btn--evidence aura-petty-view-evidence" data-id="' + r.id + '" data-tooltip="Ver comprobantes y respaldos adjuntos">' +
                    '<span class="dashicons dashicons-visibility"></span>' +
                    '<span>Comprobantes (' + Math.max(1, evidenceCount) + ')</span>' +
                '</button>'
            );
        }

        if (canSubmit) {
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-emerald btn-lift aura-child-side-btn aura-child-side-btn--submit aura-petty-action" data-id="' + r.id + '" data-status="submitted" data-tooltip="Enviar rendición para revisión">' +
                    '<span class="dashicons dashicons-yes-alt"></span>' +
                    '<span>Enviar a Revisión</span>' +
                '</button>'
            );
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-danger btn-lift aura-child-side-btn aura-child-side-btn--delete aura-petty-delete" data-id="' + r.id + '" data-tooltip="Eliminar este registro">' +
                    '<span class="dashicons dashicons-trash"></span>' +
                    '<span>Eliminar Entrega</span>' +
                '</button>'
            );
        }

        if (canApprove) {
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-emerald btn-shimmer btn-lift aura-child-side-btn aura-child-side-btn--approve aura-petty-action" data-id="' + r.id + '" data-status="approved" data-tooltip="Aprobar rendición (paz y salvo)">' +
                    '<span class="dashicons dashicons-yes"></span>' +
                    '<span>Aprobar Rendición</span>' +
                '</button>'
            );
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-rose btn-lift aura-child-side-btn aura-child-side-btn--reject aura-petty-action" data-id="' + r.id + '" data-status="rejected" data-tooltip="Rechazar rendición">' +
                    '<span class="dashicons dashicons-no"></span>' +
                    '<span>Rechazar</span>' +
                '</button>'
            );
        }

        if (canClose) {
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-secondary btn-lift aura-child-side-btn aura-child-side-btn--close aura-petty-action" data-id="' + r.id + '" data-status="closed" data-tooltip="Cerrar definitivamente la caja">' +
                    '<span class="dashicons dashicons-lock"></span>' +
                    '<span>Cerrar Definitivamente</span>' +
                '</button>'
            );
        }

        const spentAmtChild = parseFloat(r.spent_amount || 0);
        const delivAmtChild = parseFloat(r.delivered_amount || 0);
        if (spentAmtChild > delivAmtChild) {
            const excedentVal = spentAmtChild - delivAmtChild;
            sidebarButtons.push(
                '<button type="button" class="btn btn-sm btn-indigo btn-shimmer btn-lift aura-child-side-btn aura-petty-pay-excedent" data-id="' + r.id + '" data-tooltip="Pagar o reembolsar excedente comprobado al custodio">' +
                    '<span class="dashicons dashicons-money-alt"></span>' +
                    '<span>Liquidar Excedente ($' + formatNumber(excedentVal) + ')</span>' +
                '</button>'
            );
        }

        return '<div class="aura-child-card aura-child-card--sidebar-layout">' +
            '<!-- Panel Lateral Izquierdo: Acciones Rápidas -->' +
            '<div class="aura-child-actions-sidebar">' +
                '<div class="aura-child-sidebar-header">' +
                    '<span class="dashicons dashicons-admin-generic"></span>' +
                    '<span>Acciones Rápidas</span>' +
                '</div>' +
                '<div class="aura-child-sidebar-buttons">' +
                    sidebarButtons.join('') +
                '</div>' +
            '</div>' +

            '<!-- Contenido Principal -->' +
            '<div class="aura-child-main-panel">' +
                '<div class="aura-child-hero-bar">' +
                    '<div class="aura-child-hero-left">' +
                        '<div class="aura-child-icon-box" style="background:' + (isProgram ? 'rgba(79,70,229,0.12);color:#4f46e5;' : 'rgba(2,132,199,0.12);color:#0284c7;') + '">' +
                            (isProgram ? '📦' : '🛒') +
                        '</div>' +
                        '<div class="aura-child-hero-titles">' +
                            '<div class="aura-child-hero-title" style="font-weight:700;font-size:15px;color:#0f172a;">' +
                                'Entrega #' + r.id + ': ' + escapeHtml(r.responsible_name || 'Responsable') +
                            '</div>' +
                            '<div class="aura-child-hero-subtitle" style="font-size:12.5px;color:#64748b;">' +
                                'Caja Asignada: <strong>' + escapeHtml(r.account_name || 'Caja Chica') + '</strong>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="aura-child-hero-badges" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">' +
                        typeHeroBadge +
                        respBadge +
                        statusBadge +
                        overdueBadge +
                        evidenceBadge +
                        '<div class="aura-child-amount-card" style="margin-left:auto;padding:6px 14px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;text-align:right;">' +
                            '<span style="display:block;font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;">Entregado</span>' +
                            '<strong style="font-size:16px;color:#0f172a;">$' + escapeHtml(formatNumber(r.delivered_amount || 0)) + '</strong>' +
                        '</div>' +
                    '</div>' +
                '</div>' +

                '<div class="aura-child-sections-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:14px;">' +
                    '<div class="aura-child-subcard" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;">' +
                        '<div class="aura-child-subcard-header" style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:flex;align-items:center;gap:6px;">' +
                            '<span class="dashicons dashicons-calendar-alt" style="font-size:15px;color:#0284c7;"></span> Cronograma y Plazos' +
                        '</div>' +
                        '<div class="aura-child-subcard-body" style="font-size:12.5px;line-height:1.6;">' +
                            '<div><span style="color:#64748b;">Modalidad:</span> <strong>' + (isProgram ? 'Presupuesto Programa (Largo Plazo)' : 'Compra puntual / Diligencia') + '</strong></div>' +
                            '<div><span style="color:#64748b;">Fecha entrega:</span> <strong>' + escapeHtml(r.created_at || '—') + '</strong></div>' +
                            '<div><span style="color:#64748b;">Fecha límite:</span> ' + dueDateDisplay + '</div>' +
                        '</div>' +
                    '</div>' +

                    '<div class="aura-child-subcard" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;">' +
                        '<div class="aura-child-subcard-header" style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:flex;align-items:center;gap:6px;">' +
                            '<span class="dashicons dashicons-money-alt" style="font-size:15px;color:#10b981;"></span> Liquidación Contable' +
                        '</div>' +
                        '<div class="aura-child-subcard-body" style="font-size:12.5px;line-height:1.6;">' +
                            '<div><span style="color:#64748b;">Gastado comprobado:</span> <strong style="color:#4f46e5;">$' + escapeHtml(formatNumber(r.spent_amount || 0)) + '</strong></div>' +
                            '<div><span style="color:#64748b;">Efectivo reintegrado:</span> <strong style="color:#059669;">$' + escapeHtml(formatNumber(r.returned_amount || 0)) + '</strong></div>' +
                            '<div><span style="color:#64748b;">Saldo pendiente:</span> <strong style="color:' + (balance > 0.01 ? '#dc2626' : '#059669') + '">$' + escapeHtml(formatNumber(balance)) + '</strong></div>' +
                        '</div>' +
                    '</div>' +

                    '<div class="aura-child-subcard aura-child-subcard-full" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;grid-column:1 / -1;">' +
                        '<div class="aura-child-subcard-header" style="font-size:12px;font-weight:700;color:#475569;margin-bottom:8px;display:flex;align-items:center;gap:6px;">' +
                            '<span class="dashicons dashicons-clipboard" style="font-size:15px;color:#f59e0b;"></span> Propósito y Observaciones' +
                        '</div>' +
                        '<div class="aura-child-subcard-body" style="font-size:13px;color:#334155;line-height:1.5;">' +
                            (r.notes ? escapeHtml(r.notes).replace(/\n/g, '<br>') : '<em style="color:#94a3b8;">Sin notas u observaciones registradas.</em>') +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function renderPettyPurposeTip(r) {
        const fullNotes = r && r.notes ? String(r.notes) : '';
        const urlMatches = fullNotes.match(/https?:\/\/[^\s,;]+/gi) || [];
        let cleanText = fullNotes.replace(/https?:\/\/[^\s,;]+/gi, '').replace(/Comprobante\s*(Entrega|Egreso|Transferencia)?\s*:?/gi, '').trim();
        if (!cleanText) {
            cleanText = fullNotes.trim() || 'Sin notas u observaciones registradas.';
        }

        let linksHtml = '';
        if (urlMatches.length > 0) {
            linksHtml = '<div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.1);">' +
                '<strong style="font-size:11px;color:#38bdf8;display:block;margin-bottom:4px;">📎 Enlaces / Soportes adjuntos:</strong>' +
                urlMatches.map(function(url, idx) {
                    return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" style="color:#93c5fd;text-decoration:underline;display:block;font-size:11.5px;word-break:break-all;margin-bottom:2px;">🔗 Soporte #' + (idx + 1) + '</a>';
                }).join('') +
            '</div>';
        }

        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header">' +
                '<div class="aura-tip-avatar-box" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">' +
                    '📋' +
                '</div>' +
                '<div class="aura-tip-card-title-box">' +
                    '<div class="aura-tip-card-name">Propósito y Diligencia</div>' +
                    '<div class="aura-tip-card-badges">' +
                        '<span class="aura-tip-badge" style="background:rgba(245,158,11,0.25);color:#fde68a;font-size:10.5px;padding:2px 8px;border-radius:999px;">Justificación de Gasto</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="aura-tip-card-body">' +
                '<div style="font-size:12px;color:#f1f5f9;line-height:1.5;white-space:pre-wrap;">' + escapeHtml(cleanText) + '</div>' +
                linksHtml +
            '</div>' +
            '<div class="aura-tip-card-footer">' +
                '<span>Responsable: ' + escapeHtml(r.responsible_name || '—') + '</span>' +
            '</div>' +
        '</div>';
    }

    function renderPettyTable(rows) {
        if (!$pettyTableBody.length) {
            return;
        }

        if (!rows || !rows.length) {
            $pettyTableBody.html('<tr><td colspan="11" style="text-align:center;padding:34px 16px;color:#64748b;"><div style="font-size:28px;margin-bottom:8px;">📦</div><strong style="font-size:14px;display:block;color:#334155;">Sin registros de caja chica</strong><p style="margin:4px 0 0;font-size:12px;">No se encontraron entregas o rendiciones con los criterios actuales.</p></td></tr>');
            return;
        }

        const html = [];
        rows.forEach(function (r) {
            const isProgram = (r.delivery_type === 'program_budget');
            const canSubmit = r.status === 'open' || r.status === 'rejected';
            const canApprove = pettyCashCanApprove && r.status === 'submitted';
            const canClose = pettyCashCanApprove && r.status === 'approved';
            const evidenceCount = getEvidenceCount(r.evidence_json);
            const hasDriveOrReceipt = /https?:\/\/[^\s,;]+/i.test(r.notes || '');

            // Botones de acción compactos para la fila principal
            const buttons = [
                '<button type="button" class="btn btn-sm btn-primary btn-lift aura-btn-action-icon aura-petty-pick" data-id="' + r.id + '" data-tooltip="Cargar y rendir entrega #' + r.id + '" aria-label="Rendir"><span class="dashicons dashicons-edit"></span></button>'
            ];
            if (evidenceCount > 0 || (r.evidence_json && String(r.evidence_json).trim() !== '') || hasDriveOrReceipt) {
                buttons.push('<button type="button" class="btn btn-sm btn-secondary btn-lift aura-btn-action-icon aura-btn-action-evidence aura-petty-view-evidence" data-id="' + r.id + '" data-tooltip="Ver comprobante(s) (' + Math.max(1, evidenceCount) + ')" aria-label="Comprobantes"><span class="dashicons dashicons-visibility"></span>' + (evidenceCount > 0 ? '<span class="aura-btn-badge-count">' + evidenceCount + '</span>' : '') + '</button>');
            }
            if (canSubmit) {
                buttons.push('<button type="button" class="btn btn-sm btn-emerald btn-lift aura-btn-action-icon aura-btn-action-submit aura-petty-action" data-id="' + r.id + '" data-status="submitted" data-tooltip="Enviar para aprobación" aria-label="Enviar"><span class="dashicons dashicons-yes-alt"></span></button>');
                buttons.push('<button type="button" class="btn btn-sm btn-danger btn-lift aura-btn-action-icon aura-petty-delete" data-id="' + r.id + '" data-tooltip="Eliminar entrega #' + r.id + '" aria-label="Eliminar"><span class="dashicons dashicons-trash"></span></button>');
            }
            if (canApprove) {
                buttons.push('<button type="button" class="btn btn-sm btn-emerald btn-shimmer btn-lift aura-btn-action-icon aura-btn-action-approve aura-petty-action" data-id="' + r.id + '" data-status="approved" data-tooltip="Aprobar rendición" aria-label="Aprobar"><span class="dashicons dashicons-yes"></span></button>');
                buttons.push('<button type="button" class="btn btn-sm btn-rose btn-lift aura-btn-action-icon aura-btn-action-reject aura-petty-action" data-id="' + r.id + '" data-status="rejected" data-tooltip="Rechazar rendición" aria-label="Rechazar"><span class="dashicons dashicons-no"></span></button>');
            }
            if (canClose) {
                buttons.push('<button type="button" class="btn btn-sm btn-secondary btn-lift aura-btn-action-icon aura-btn-action-close aura-petty-action" data-id="' + r.id + '" data-status="closed" data-tooltip="Cerrar definitivamente" aria-label="Cerrar"><span class="dashicons dashicons-lock"></span></button>');
            }

            const spentAmtMain = parseFloat(r.spent_amount || 0);
            const delivAmtMain = parseFloat(r.delivered_amount || 0);
            if (spentAmtMain > delivAmtMain) {
                const excedentRow = spentAmtMain - delivAmtMain;
                buttons.push('<button type="button" class="btn btn-sm btn-indigo btn-lift aura-btn-action-icon aura-petty-pay-excedent" data-id="' + r.id + '" data-tooltip="Liquidar excedente de $' + formatNumber(excedentRow) + ' a favor del custodio" aria-label="Liquidar Excedente"><span class="dashicons dashicons-money-alt"></span></button>');
            }

            const isThirdParty = (r.counterparty_id && parseInt(r.counterparty_id, 10) > 0);
            const respBadge = isThirdParty 
                ? '<span class="badge badge-indigo" style="font-size:10px;padding:1px 6px;border-radius:5px;font-weight:600;">Tercero</span>' 
                : '<span class="badge badge-slate" style="font-size:10px;padding:1px 6px;border-radius:5px;font-weight:600;">Interno</span>';

            const typeBadge = isProgram
                ? '<span class="badge badge-success" style="font-size:10px;padding:1px 6px;border-radius:5px;font-weight:700;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;" title="Presupuesto de Programa (Largo plazo)"><span class="dashicons dashicons-portfolio" style="font-size:10px;width:10px;height:10px;line-height:10px;vertical-align:middle;margin-right:2px;"></span>Programa</span>'
                : '<span class="badge badge-danger" style="font-size:10px;padding:1px 6px;border-radius:5px;font-weight:700;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;" title="Compra puntual / Diligencia (Corto plazo)"><span class="dashicons dashicons-cart" style="font-size:10px;width:10px;height:10px;line-height:10px;vertical-align:middle;margin-right:2px;"></span>Compra</span>';

            const isOverdue = parseInt(r.is_overdue, 10) === 1 && r.status !== 'approved' && r.status !== 'closed';
            const dueDateStr = (r.due_date || '').slice(0, 10) || '—';
            let dueDateDisplay = '';
            if (isOverdue) {
                dueDateDisplay = '<span style="color:#ef4444;font-weight:700;"><span class="dashicons dashicons-warning" style="font-size:13px;vertical-align:middle;margin-right:2px;"></span>' + escapeHtml(dueDateStr) + '</span>';
            } else if (isProgram) {
                dueDateDisplay = '<span style="color:#0284c7;font-weight:600;"><span class="dashicons dashicons-calendar-alt" style="font-size:13px;vertical-align:middle;margin-right:2px;color:#0284c7;"></span>' + escapeHtml(dueDateStr) + '</span>';
            } else {
                dueDateDisplay = '<span style="color:#64748b;">' + escapeHtml(dueDateStr) + '</span>';
            }

            const tipCardHtml = renderPettyResponsibleTip(r);
            const childCardHtml = renderPettyChildCard(r, canSubmit, canApprove, canClose, evidenceCount, isThirdParty, respBadge, isOverdue, dueDateDisplay, isProgram);

            // ── Columna Fecha: icono + formato compacto
            const dateVal = r.created_at ? r.created_at.slice(0, 10) : '';
            const dateDisplay = dateVal
                ? '<span style="display:inline-flex;align-items:center;gap:3px;color:#475569;font-size:12px;">' +
                    '<span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;color:#94a3b8;flex-shrink:0;"></span>' +
                    escapeHtml(dateVal) +
                  '</span>'
                : '<span style="color:#94a3b8;">—</span>';

            // ── Avatar / Imagen de perfil con Aro Rojo (Compras) o Verde (Programas)
            const ringClass = isProgram ? 'aura-avatar-ring--program' : 'aura-avatar-ring--purchase';
            const ringTitle = isProgram ? 'Presupuesto de Programa' : 'Compras / Diligencias';
            const respName = r.responsible_name || '?';
            let avatarHtml = '';
            if (r.responsible_avatar_url && String(r.responsible_avatar_url).trim() !== '') {
                avatarHtml = '<span class="aura-responsible-avatar-wrap ' + ringClass + '" title="' + escapeHtml(respName) + ' (' + ringTitle + ')">' +
                    '<img src="' + escapeHtml(r.responsible_avatar_url) + '" alt="' + escapeHtml(respName) + '" class="aura-responsible-avatar-img" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'inline-flex\';">' +
                    '<span class="aura-responsible-avatar-icon" style="display:none;background:' + (isThirdParty ? '#e0e7ff' : '#e0f2fe') + ';color:' + (isThirdParty ? '#4f46e5' : '#0284c7') + ';"><span class="dashicons ' + (isThirdParty ? 'dashicons-businessman' : 'dashicons-admin-users') + '" style="font-size:14px;width:14px;height:14px;line-height:14px;"></span></span>' +
                '</span>';
            } else {
                avatarHtml = '<span class="aura-responsible-avatar-wrap ' + ringClass + '" title="' + escapeHtml(respName) + ' (' + ringTitle + ')" style="background:' + (isThirdParty ? '#e0e7ff' : '#e0f2fe') + ';color:' + (isThirdParty ? '#4f46e5' : '#0284c7') + ';"><span class="aura-responsible-avatar-icon"><span class="dashicons ' + (isThirdParty ? 'dashicons-businessman' : 'dashicons-admin-users') + '" style="font-size:14px;width:14px;height:14px;line-height:14px;"></span></span></span>';
            }

            // ── Barra micro de progreso entregado vs gastado
            const delivAmt = parseFloat(r.delivered_amount || 0);
            const spentAmt = parseFloat(r.spent_amount || 0);
            const pctSpent = delivAmt > 0 ? Math.min(100, Math.round((spentAmt / delivAmt) * 100)) : 0;
            const barColor = pctSpent > 100 ? '#ef4444' : pctSpent >= 85 ? '#f59e0b' : '#10b981';
            const microBar = '<div style="height:3px;border-radius:2px;background:#e2e8f0;margin-top:2px;overflow:hidden;">' +
                '<div style="height:100%;width:' + pctSpent + '%;background:' + barColor + ';border-radius:2px;transition:width 0.4s;"></div>' +
                '</div>';

            // ── Badge comprobantes enriquecido
            const evBadge = (evidenceCount > 0 || hasDriveOrReceipt)
                ? '<span class="aura-petty-overdue-mark is-evidence" title="' + Math.max(1, evidenceCount) + ' comprobante(s)" style="margin-left:3px;background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:999px;padding:1px 5px;font-size:9.5px;font-weight:700;">' +
                    '<span class="dashicons dashicons-paperclip" style="font-size:9px;width:9px;height:9px;line-height:9px;vertical-align:middle;margin-right:1px;"></span>' + Math.max(1, evidenceCount) +
                  '</span>'
                : '';

            // ── Columna Propósito / Diligencia: resumen limpio + tooltip enriquecido
            const rawNotesAll = (r.notes || '').trim();
            const cleanSummaryText = rawNotesAll
                .replace(/https?:\/\/[^\s,;]+/gi, '')
                .replace(/Comprobante\s*(Entrega|Egreso|Transferencia)?\s*:?/gi, '')
                .trim();
            const shortSummary = cleanSummaryText
                ? (cleanSummaryText.length > 28 ? escapeHtml(cleanSummaryText.substring(0, 26)) + '...' : escapeHtml(cleanSummaryText))
                : (hasDriveOrReceipt ? 'Comprobante adjunto' : '<span style="color:#94a3b8;font-style:italic;">Sin propósito</span>');

            const purposeDisplay = '<span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyPurposeTip(r)) + '" style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;">' +
                '<span class="dashicons dashicons-editor-quote" style="font-size:12px;width:12px;height:12px;color:#94a3b8;flex-shrink:0;"></span>' +
                '<span style="font-size:12px;color:#334155;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + shortSummary + '</span>' +
                (hasDriveOrReceipt ? '<span class="dashicons dashicons-paperclip" style="font-size:11px;width:11px;height:11px;color:#0284c7;flex-shrink:0;" title="Tiene comprobante adjunto"></span>' : '') +
            '</span>';

            // Fila principal con clase .aura-parent-row y elevación .table-row-hover-lift
            html.push('<tr class="aura-parent-row table-row-hover-lift" data-id="' + r.id + '">' +
                '<td class="column-toggle" style="text-align:center;white-space:nowrap;">' +
                    '<button type="button" class="aura-row-toggle" data-id="' + r.id + '" aria-expanded="false" title="Expandir detalles de entrega #' + r.id + '">' +
                        '<span class="dashicons dashicons-arrow-right-alt2"></span>' +
                    '</button>' +
                    '<span class="aura-txn-id-pill" style="font-weight:700;font-size:11px;color:#475569;margin-left:2px;">#' + r.id + '</span>' +
                '</td>' +
                '<td class="column-date" style="white-space:nowrap;">' + dateDisplay + '</td>' +
                '<td class="column-due aura-col-desktop" style="white-space:nowrap;"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyDueTip(r)) + '" style="cursor:help;">' + dueDateDisplay + '</span></td>' +
                '<td class="column-account aura-col-desktop"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyAccountTip(r)) + '" style="cursor:help;">' +
                    '<span style="display:inline-flex;align-items:center;gap:4px;">' +
                        '<span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:5px;background:#e0f2fe;flex-shrink:0;">' +
                            '<span class="dashicons dashicons-money-alt" style="font-size:12px;width:12px;height:12px;color:#0284c7;"></span>' +
                        '</span>' +
                        '<span style="font-size:12px;font-weight:600;color:#0f172a;">' + escapeHtml(r.account_name || '') + '</span>' +
                    '</span>' +
                '</span></td>' +
                '<td class="column-responsible"><span class="aura-responsible-cell aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(tipCardHtml) + '" style="cursor:pointer;">' +
                    avatarHtml +
                    '<div style="display:flex;flex-direction:column;min-width:0;line-height:1.25;">' +
                        '<strong style="font-size:12px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;" title="' + escapeHtml(r.responsible_name || '') + '">' + escapeHtml(r.responsible_name || '') + '</strong>' +
                        '<div style="display:flex;align-items:center;gap:4px;margin-top:2px;">' +
                            respBadge +
                            typeBadge +
                        '</div>' +
                    '</div>' +
                '</span></td>' +
                '<td class="column-purpose aura-col-desktop">' + purposeDisplay + '</td>' +
                '<td class="column-delivered" style="text-align:right;"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyMoneyTip(r, 'delivered')) + '" style="cursor:help;">' +
                    '<strong style="font-size:12.5px;color:#0f172a;display:block;">$' + escapeHtml(formatNumber(r.delivered_amount || 0)) + '</strong>' +
                    microBar +
                '</span></td>' +
                '<td class="column-spent aura-col-desktop" style="text-align:right;"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyMoneyTip(r, 'spent')) + '" style="cursor:help;">' +
                    '<strong style="font-size:12.5px;color:' + (spentAmt > delivAmt ? '#ef4444' : '#4f46e5') + ';">$' + escapeHtml(formatNumber(r.spent_amount || 0)) + '</strong>' +
                    '<span style="display:block;font-size:9.5px;color:' + barColor + ';font-weight:700;">' + pctSpent + '% usado</span>' +
                '</span></td>' +
                '<td class="column-returned aura-col-desktop" style="text-align:right;"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyMoneyTip(r, 'returned')) + '" style="cursor:help;font-weight:600;color:#059669;font-size:12px;">$' + escapeHtml(formatNumber(r.returned_amount || 0)) + '</span></td>' +
                '<td class="column-status" style="text-align:center;"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(renderPettyStatusTip(r)) + '" style="cursor:pointer;display:inline-block;">' +
                    pettyStatusBadge(r.status) +
                    (isOverdue ? ' <span class="aura-petty-overdue-mark" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;border-radius:999px;padding:1px 5px;font-size:9.5px;font-weight:700;margin-left:2px;">⚠ Vencida</span>' : '') +
                    evBadge +
                '</span></td>' +
                '<td class="column-actions aura-col-desktop" style="white-space:nowrap;text-align:center;"><div class="aura-petty-actions">' + buttons.join('') + '</div></td>' +
            '</tr>');

            // Fila Hija (Child Row) colapsada por defecto
            html.push('<tr id="aura-child-row-' + r.id + '" class="aura-child-row" style="display:none;">' +
                '<td colspan="11" class="aura-child-row-cell" style="padding:0;border:none;">' +
                    '<div class="aura-child-card-wrapper">' +
                        childCardHtml +
                    '</div>' +
                '</td>' +
            '</tr>');
        });

        $pettyTableBody.html(html.join(''));
    }

    function loadPettyCash() {
        if (!$pettyTableBody.length) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_petty_cash_list',
            nonce: auraFinancialAccounts.nonce
        }).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                pettyCashCanApprove = !!data.can_approve;
                window.auraPettyCashCache = data.settlements || [];
                updatePettyCashKPIs(window.auraPettyCashCache);
                filterPettyCash();
                fillPettyAccounts(data.petty_cash_accounts || [], data.all_accounts || []);
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    async function createPettyCash() {
        const $btn = $('#aura-petty-create-btn');
        const fd = new window.FormData();
        fd.append('action', 'aura_finance_petty_cash_create');
        fd.append('nonce', auraFinancialAccounts.nonce);
        fd.append('petty_cash_account_id', $('#aura-petty-account').val());
        fd.append('origin_account_id', $('#aura-petty-origin-account').val() || '');
        fd.append('responsible_user_id', $('#aura-petty-responsible').val());
        fd.append('delivered_amount', $('#aura-petty-delivered').val());
        fd.append('delivery_type', $('input[name="aura_petty_type"]:checked').val() || 'purchase_errand');
        fd.append('due_date', $('#aura-petty-due-date').val());
        fd.append('notes', $('#aura-petty-notes').val());
        fd.append('delivery_receipt_url', $('#aura-petty-delivery-receipt-url').val() || '');

        const fileInput = document.getElementById('aura-petty-delivery-receipt');
        if (fileInput && fileInput.files && fileInput.files.length) {
            $btn.prop('disabled', true).text('Procesando comprobante...');
            try {
                const processed = await compressImage(fileInput.files[0]);
                fd.append('delivery_receipt_file', processed);
            } catch(e) {
                fd.append('delivery_receipt_file', fileInput.files[0]);
            }
        }

        $btn.prop('disabled', true).text('Registrando...');

        $.ajax({
            url: auraFinancialAccounts.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:2px;"></span> Registrar Entrega de Fondo');
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Entrega registrada.', true);
                resetPettyForm();
                window.AuraUI.closeModal('aura-finance-petty-modal');
                loadPettyCash();
                loadAccounts();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:2px;"></span> Registrar Entrega de Fondo');
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function compressImage(file) {
        return new Promise(function(resolve) {
            if (!file.type.startsWith('image/')) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(event) {
                const img = new Image();
                img.src = event.target.result;
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const MAX_WIDTH = 1200;
                    const MAX_HEIGHT = 1200;
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > MAX_WIDTH) {
                            height = Math.round(height * MAX_WIDTH / width);
                            width = MAX_WIDTH;
                        }
                    } else {
                        if (height > MAX_HEIGHT) {
                            width = Math.round(width * MAX_HEIGHT / height);
                            height = MAX_HEIGHT;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function(blob) {
                        const compressedFile = new File([blob], file.name, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    }, 'image/jpeg', 0.7);
                };
            };
        });
    }

    async function submitPettyCash(id) {
        const fd = new window.FormData();
        fd.append('action', 'aura_finance_petty_cash_submit');
        fd.append('nonce', auraFinancialAccounts.nonce);
        fd.append('id', id);
        
        const expenses = [];
        $('#aura-petty-expenses-table tbody tr').each(function() {
            const amt = parseFloat($(this).find('.aura-petty-expense-amount').val()) || 0;
            const cat = $(this).find('.aura-petty-expense-category').val();
            const concept = $(this).find('.aura-petty-expense-concept').val();
            if (amt > 0) {
                expenses.push({
                    amount: amt,
                    category_id: cat,
                    notes: concept,
                    concept: concept
                });
            }
        });
        
        if (!expenses.length) {
            showFeedback('Debes registrar al menos un gasto con monto válido.', false);
            return;
        }

        fd.append('expenses_json', JSON.stringify(expenses));
        fd.append('returned_amount', $('#aura-petty-returned').val() || 0);
        fd.append('evidence_json', $('#aura-petty-evidence').val() || '');
        fd.append('notes', $('#aura-petty-settle-notes').val() || $('#aura-petty-notes').val() || '');

        if (pettySelectedEvidenceFiles && pettySelectedEvidenceFiles.length) {
            $('#aura-petty-submit-btn').prop('disabled', true).text('Procesando comprobantes...');
            
            const filePromises = pettySelectedEvidenceFiles.map(function(file) {
                return compressImage(file);
            });
            
            try {
                const processedFiles = await Promise.all(filePromises);
                processedFiles.forEach(function(file) {
                    fd.append('evidence_files[]', file);
                });
            } catch (e) {
                showFeedback('Error procesando imágenes de comprobantes', false);
                $('#aura-petty-submit-btn').prop('disabled', false).text('Enviar Rendición para Aprobación');
                return;
            }
        }
        
        $('#aura-petty-submit-btn').prop('disabled', true).text('Enviando...');

        $.ajax({
            url: auraFinancialAccounts.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Rendición enviada.', true);
                resetPettyForm();
                window.AuraUI.closeModal('aura-finance-petty-modal');
                loadPettyCash();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            $('#aura-petty-submit-btn').prop('disabled', false).text('Enviar Rendición');
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
            $('#aura-petty-submit-btn').prop('disabled', false).text('Enviar Rendición');
        });
    }

    function deletePettyCash(id) {
        if (!confirm('¿Estás seguro de que deseas eliminar este registro de caja chica?')) {
            return;
        }
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_petty_cash_delete',
            nonce: auraFinancialAccounts.nonce,
            id: id
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Registro eliminado.', true);
                loadPettyCash();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function updatePettyStatus(id, status) {
        let note = '';
        if (status === 'rejected') {
            const reason = window.prompt('Indica el motivo del rechazo de esta rendición (obligatorio):');
            if (reason === null) {
                return; // Cancelado por el usuario
            }
            if (!reason.trim()) {
                showFeedback('Debes ingresar un motivo para rechazar la rendición.', false);
                return;
            }
            note = reason.trim();
        } else if (status === 'approved') {
            const confirmMsg = '¿Deseas aprobar esta rendición y publicar automáticamente sus transacciones de egreso en el Libro Mayor?';
            if (!window.confirm(confirmMsg)) {
                return;
            }
            const optNote = window.prompt('Observación o nota de aprobación para auditoría (opcional):', '');
            if (optNote !== null && optNote.trim() !== '') {
                note = optNote.trim();
            }
        } else if (status === 'closed') {
            if (!window.confirm('¿Deseas cerrar definitivamente esta entrega de caja chica?')) {
                return;
            }
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_petty_cash_status',
            nonce: auraFinancialAccounts.nonce,
            id: id,
            status: status,
            note: note,
            reason: note
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Estado actualizado.', true);
                loadPettyCash();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function reimbursementStatusLabel(status) {
        const map = {
            pending: 'Pendiente',
            partial: 'Parcial',
            paid: 'Pagado',
            cancelled: 'Cancelado'
        };
        return map[status] || status;
    }

    function reimbursementStatusBadge(status) {
        switch (String(status).toLowerCase()) {
            case 'paid':
                return '<span class="status-traffic-light" style="display:inline-flex;align-items:center;gap:6px;">' +
                    '<span class="traffic-dot traffic-dot-success"></span>' +
                    '<span class="badge badge-emerald">Pagado</span>' +
                '</span>';
            case 'partial':
                return '<span class="status-traffic-light" style="display:inline-flex;align-items:center;gap:6px;">' +
                    '<span class="traffic-dot traffic-dot-warning"></span>' +
                    '<span class="badge badge-amber">Parcial</span>' +
                '</span>';
            case 'pending':
                return '<span class="status-traffic-light" style="display:inline-flex;align-items:center;gap:6px;">' +
                    '<span class="traffic-dot traffic-dot-danger"></span>' +
                    '<span class="badge badge-rose">Pendiente</span>' +
                '</span>';
            case 'cancelled':
                return '<span class="status-traffic-light" style="display:inline-flex;align-items:center;gap:6px;">' +
                    '<span class="traffic-dot traffic-dot-danger"></span>' +
                    '<span class="badge badge-slate">Cancelado</span>' +
                '</span>';
            default:
                return '<span class="badge badge-slate">' + escapeHtml(reimbursementStatusLabel(status)) + '</span>';
        }
    }

    function fillReimburseAccountOptions(accounts) {
        const $sel = $('#aura-reimburse-pay-account');
        if (!$sel.length) {
            return;
        }

        const opts = ['<option value="">Selecciona cuenta...</option>'];
        (accounts || []).forEach(function (a) {
            opts.push('<option value="' + parseInt(a.id, 10) + '">' +
                escapeHtml(a.name || '') + ' (' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + ')</option>');
        });
        $sel.html(opts.join(''));
    }

    function fillThirdPartyOptions(thirdParties, selectedId, users) {
        const $sel = $('#aura-reimburse-person');
        if (!$sel.length) {
            return;
        }

        const opts = ['<option value="">Selecciona responsable o tercero...</option>'];

        const appUsers = users || window.auraAppUsersCache || (typeof auraFinancialAccounts !== 'undefined' ? auraFinancialAccounts.appUsers : []) || [];
        if (appUsers && appUsers.length) {
            opts.push('<optgroup label="Usuarios de WordPress (Aura)">');
            appUsers.forEach(function (u) {
                const uid = parseInt(u.id, 10);
                if (!uid) return;
                const label = u.display_name + (u.user_email ? ' (' + u.user_email + ')' : '');
                opts.push('<option value="wp:' + uid + '">' + escapeHtml(label) + '</option>');
            });
            opts.push('</optgroup>');
        }

        const tps = thirdParties || window.auraThirdPartiesCache || [];
        if (tps && tps.length) {
            opts.push('<optgroup label="Terceros">');
            tps.forEach(function (p) {
                const id = parseInt(p.id, 10);
                if (!id) return;
                const label = p.document_id
                    ? (p.full_name + ' (' + p.document_id + ')')
                    : (p.full_name || 'Tercero #' + id);
                opts.push('<option value="tp:' + id + '">' + escapeHtml(label) + '</option>');
            });
            opts.push('</optgroup>');
        }

        $sel.html(opts.join(''));
        if (selectedId) {
            $sel.val(String(selectedId));
        }
    }

    function showThirdPartyManageFeedback(message, isOk) {
        const $box = $('#aura-third-party-manage-feedback');
        if (!$box.length) {
            return;
        }

        if (!message) {
            $box.hide().removeClass('is-error is-success').text('');
            return;
        }

        $box
            .show()
            .removeClass('is-error is-success')
            .addClass(isOk ? 'is-success' : 'is-error')
            .text(message);
    }

    function renderThirdPartyManageTable(rows) {
        const $tbody = $('#aura-third-party-manage-table tbody, #aura-third-party-modal-table tbody');
        if (!$tbody.length) {
            return;
        }

        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="8">Sin terceros ni empresas registradas.</td></tr>');
            return;
        }

        const typeLabels = {
            company: 'Empresa / Negocio',
            store: 'Tienda / Comercio',
            organization_foundation: 'Fundación / ONG',
            person: 'Persona Natural',
            religious: 'Entidad Religiosa',
            other: 'Otra Entidad'
        };
        const typeIcons = {
            company: 'dashicons-building',
            store: 'dashicons-cart',
            organization_foundation: 'dashicons-heart',
            person: 'dashicons-businessman',
            religious: 'dashicons-location-alt',
            other: 'dashicons-category'
        };

        const html = rows.map(function (p) {
            const id = parseInt(p.id, 10) || 0;
            const active = parseInt(p.is_active, 10) === 1;
            const hasWpUser = parseInt(p.wp_user_id, 10) > 0;
            const wpLabel = hasWpUser
                ? (p.wp_user_display || p.wp_user_login || ('Usuario #' + p.wp_user_id))
                : 'No vinculado';

            const ptype = p.party_type || 'company';
            const typeLabel = typeLabels[ptype] || typeLabels.company;
            const typeIcon = typeIcons[ptype] || 'dashicons-building';
            const logoUrl = p.logo_url || '';

            const logoHtml = logoUrl
                ? '<div class="aura-user-avatar-wrap is-third-party is-' + escapeHtml(ptype) + '" style="margin:0 auto;"><img src="' + escapeHtml(logoUrl) + '" class="aura-avatar-img" alt="' + escapeHtml(p.full_name || '') + '" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'inline-flex\'; }"><span class="dashicons ' + escapeHtml(typeIcon) + '" style="display:none;"></span></div>'
                : '<div class="aura-user-avatar-wrap is-third-party is-' + escapeHtml(ptype) + '" style="margin:0 auto;"><span class="dashicons ' + escapeHtml(typeIcon) + '"></span></div>';

            const nameHtml = '<strong>' + escapeHtml(p.commercial_name || p.full_name || '') + '</strong>' +
                (p.commercial_name && p.commercial_name !== p.full_name ? '<br><small style="color:#64748b;">' + escapeHtml(p.full_name || '') + '</small>' : '');

            const typeBadge = '<span class="aura-pill aura-pill--muted is-party-' + escapeHtml(ptype) + '"><span class="dashicons ' + escapeHtml(typeIcon) + '" style="font-size:12px;width:12px;height:12px;line-height:12px;margin-right:2px;"></span>' + escapeHtml(typeLabel) + '</span>';

            const docHtml = p.document_id
                ? '<span style="font-weight:500;">' + escapeHtml(p.tax_id_type || 'NIT') + ':</span> ' + escapeHtml(p.document_id)
                : '-';

            const contactParts = [];
            if (p.phone) {
                contactParts.push('<div><span class="dashicons dashicons-phone" style="font-size:13px;width:13px;height:13px;color:#64748b;"></span> ' + escapeHtml(p.phone) + '</div>');
            }
            if (p.email) {
                contactParts.push('<div><span class="dashicons dashicons-email" style="font-size:13px;width:13px;height:13px;color:#64748b;"></span> ' + escapeHtml(p.email) + '</div>');
            }
            const contactHtml = contactParts.length ? contactParts.join('') : '-';

            const actions = [
                '<button type="button" class="button button-small aura-third-party-edit" data-id="' + id + '">Editar</button>',
                '<button type="button" class="button button-small aura-third-party-toggle" data-id="' + id + '" data-active="' + (active ? '1' : '0') + '">' + (active ? 'Desactivar' : 'Reactivar') + '</button>'
            ];

            if (!hasWpUser) {
                actions.push('<button type="button" class="button button-small button-primary aura-third-party-convert" data-id="' + id + '">Convertir a usuario WP</button>');
            }

            return '<tr>' +
                '<td style="text-align:center;">' + logoHtml + '</td>' +
                '<td>' + nameHtml + '</td>' +
                '<td>' + typeBadge + '</td>' +
                '<td>' + docHtml + '</td>' +
                '<td>' + contactHtml + '</td>' +
                '<td>' + (active ? '<span class="aura-pill aura-pill--ok">Activo</span>' : '<span class="aura-pill aura-pill--muted">Inactivo</span>') + '</td>' +
                '<td>' + escapeHtml(wpLabel) + '</td>' +
                '<td><div class="aura-third-party-actions">' + actions.join('') + '</div></td>' +
                '</tr>';
        });

        $tbody.html(html.join(''));
    }

    function loadThirdPartiesManagement() {
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_third_parties_list',
            nonce: auraFinancialAccounts.nonce,
            include_inactive: 1
        }).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                window.auraThirdPartiesManagementCache = data.third_parties || [];

                const activeOnly = (window.auraThirdPartiesManagementCache || []).filter(function (p) {
                    return parseInt(p.is_active, 10) === 1;
                });
                window.auraThirdPartiesCache = activeOnly;

                renderThirdPartyManageTable(window.auraThirdPartiesManagementCache);
                fillThirdPartyOptions(window.auraThirdPartiesCache);
                return;
            }

            showThirdPartyManageFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showThirdPartyManageFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function renderReimbursementsTable(rows) {
        if (!$reimbursementsTableBody.length) {
            return;
        }

        if (!rows || !rows.length) {
            $reimbursementsTableBody.html('<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--aura-text-muted,#64748b);">Sin reembolsos registrados.</td></tr>');
            return;
        }

        const html = rows.map(function (r) {
            const owed = parseFloat(r.owed_amount || 0);
            const paid = parseFloat(r.paid_amount || 0);
            const remaining = Math.max(0, owed - paid);
            const percentPaid = owed > 0 ? Math.min(100, Math.round((paid / owed) * 100)) : 100;
            const canPay = r.status === 'pending' || r.status === 'partial';
            const originText = r.origin_transaction_id ? ('#' + r.origin_transaction_id) : 'Manual';
            const actions = [];

            if (canPay && remaining > 0) {
                actions.push('<button type="button" class="btn btn-sm btn-primary btn-shimmer btn-lift aura-reimburse-pay-pick" data-id="' + r.id + '" data-remaining="' + remaining + '" data-tooltip="Registrar pago ($' + escapeHtml(formatNumber(remaining)) + ')" aria-label="Pagar">' +
                    '<span class="dashicons dashicons-money-alt"></span> <span>Pagar</span>' +
                '</button>');
            }

            if (paid === 0) {
                actions.push('<button type="button" class="btn btn-sm btn-danger btn-lift aura-reimburse-delete" data-id="' + r.id + '" data-tooltip="Eliminar este reembolso" aria-label="Eliminar">' +
                    '<span class="dashicons dashicons-trash"></span>' +
                '</button>');
            }

            // Avatar o Icono Predeterminado
            const personName = r.person_name || ('Tercero #' + (r.counterparty_id || r.person_user_id || 'N/A'));
            let avatarMarkup = '';
            if (r.person_avatar && r.has_custom_avatar) {
                avatarMarkup = '<div class="aura-reimburse-avatar-box avatar-hover-zoom aura-avatar-zoom" data-img-url="' + escapeHtml(r.person_avatar) + '" data-preview-title="' + escapeHtml(personName) + '">' +
                    '<img src="' + escapeHtml(r.person_avatar) + '" alt="' + escapeHtml(personName) + '" class="aura-reimburse-avatar-img">' +
                '</div>';
            } else {
                const iconClass = r.default_icon || 'dashicons-businessman';
                avatarMarkup = '<div class="aura-reimburse-avatar-box" title="' + escapeHtml(personName) + '">' +
                    '<span class="dashicons ' + iconClass + ' aura-reimburse-avatar-icon"></span>' +
                '</div>';
            }

            // Tarjeta Enriquecida del Beneficiario (.aura-tip-card)
            const personTip = '<div class="aura-tip-card" style="min-width:270px;max-width:320px;">' +
                '<div class="aura-tip-card-header">' +
                    '<div class="aura-tip-avatar-box" style="background:linear-gradient(135deg,#6366f1 0%,#4f46e5 100%);">' +
                        (r.person_avatar && r.has_custom_avatar
                            ? '<img src="' + escapeHtml(r.person_avatar) + '" alt="' + escapeHtml(personName) + '" style="width:100%;height:100%;object-fit:cover;">'
                            : '<span class="dashicons ' + (r.default_icon || 'dashicons-businessman') + '" style="font-size:22px;width:22px;height:22px;"></span>'
                        ) +
                    '</div>' +
                    '<div class="aura-tip-card-title-box">' +
                        '<div class="aura-tip-card-name">' + escapeHtml(personName) + '</div>' +
                        '<div class="aura-tip-card-badges">' +
                            '<span class="badge badge-indigo" style="font-size:10px;padding:1px 6px;">' + escapeHtml(r.person_type_label || (r.counterparty_id ? 'Tercero' : 'Usuario')) + '</span>' +
                            (r.person_document ? '<span class="badge badge-slate" style="font-size:10px;padding:1px 6px;">Doc: ' + escapeHtml(r.person_document) + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="aura-tip-card-body">' +
                    (r.person_email ? '<div style="font-size:11.5px;color:#94a3b8;margin-bottom:4px;"><span class="dashicons dashicons-email" style="font-size:12px;width:12px;height:12px;vertical-align:middle;margin-right:3px;"></span>' + escapeHtml(r.person_email) + '</div>' : '') +
                    '<div class="aura-tip-stat-grid">' +
                        '<div class="aura-tip-stat-item">' +
                            '<span class="aura-tip-stat-label">Adeudado</span>' +
                            '<span class="aura-tip-stat-value" style="font-size:12.5px;color:#f8fafc;">$' + escapeHtml(formatNumber(owed)) + '</span>' +
                        '</div>' +
                        '<div class="aura-tip-stat-item">' +
                            '<span class="aura-tip-stat-label">Pagado</span>' +
                            '<span class="aura-tip-stat-value is-returned" style="font-size:12.5px;color:#34d399;">$' + escapeHtml(formatNumber(paid)) + '</span>' +
                        '</div>' +
                        '<div class="aura-tip-stat-item span-2">' +
                            '<span class="aura-tip-stat-label">Saldo Pendiente</span>' +
                            '<span class="aura-tip-stat-value" style="font-size:14px;color:' + (remaining > 0 ? '#f87171' : '#34d399') + ';">$' + escapeHtml(formatNumber(remaining)) + '</span>' +
                        '</div>' +
                    '</div>' +
                    (r.notes ? '<div style="font-size:11px;color:#cbd5e1;background:rgba(30,41,59,0.5);border:1px solid #334155;border-radius:6px;padding:6px 8px;margin-top:6px;line-height:1.4;"><strong>Notas:</strong> ' + escapeHtml(r.notes) + '</div>' : '') +
                '</div>' +
                '<div class="aura-tip-card-footer">' +
                    '<span style="color:#94a3b8;">Reembolso #' + r.id + '</span>' +
                    '<span style="color:#38bdf8;font-weight:700;">' + percentPaid + '% amortizado</span>' +
                '</div>' +
            '</div>';

            // Tarjeta Enriquecida del Balance / Deuda
            const debtTip = '<div class="aura-tip-card" style="min-width:250px;max-width:300px;">' +
                '<div class="aura-tip-card-header">' +
                    '<div class="aura-tip-avatar-box" style="background:linear-gradient(135deg,#059669 0%,#10b981 100%);">' +
                        '<span class="dashicons dashicons-money-alt" style="font-size:20px;width:20px;height:20px;"></span>' +
                    '</div>' +
                    '<div class="aura-tip-card-title-box">' +
                        '<div class="aura-tip-card-name">Balance Reembolso #' + r.id + '</div>' +
                        '<div class="aura-tip-card-badges">' +
                            '<span class="badge ' + (remaining <= 0 ? 'badge-emerald' : (paid > 0 ? 'badge-amber' : 'badge-rose')) + '" style="font-size:10px;padding:1px 6px;">' + (remaining <= 0 ? '● Liquidado' : (paid > 0 ? '● Parcial' : '○ Pendiente')) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="aura-tip-card-body">' +
                    '<div class="aura-tip-progress-wrap">' +
                        '<div class="aura-tip-progress-bar" style="width:' + percentPaid + '%;"></div>' +
                    '</div>' +
                    '<div style="display:flex;justify-content:space-between;font-size:11px;color:#94a3b8;margin-bottom:6px;">' +
                        '<span>Amortizado</span><strong>' + percentPaid + '%</strong>' +
                    '</div>' +
                    '<div class="aura-tip-stat-grid">' +
                        '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Adeudado</span><span class="aura-tip-stat-value" style="font-size:12px;color:#f8fafc;">$' + escapeHtml(formatNumber(owed)) + '</span></div>' +
                        '<div class="aura-tip-stat-item"><span class="aura-tip-stat-label">Pagado</span><span class="aura-tip-stat-value" style="font-size:12px;color:#34d399;">$' + escapeHtml(formatNumber(paid)) + '</span></div>' +
                        '<div class="aura-tip-stat-item span-2"><span class="aura-tip-stat-label">Saldo Pendiente</span><span class="aura-tip-stat-value" style="font-size:14px;color:' + (remaining > 0 ? '#f87171' : '#34d399') + ';">$' + escapeHtml(formatNumber(remaining)) + '</span></div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            // Tarjeta Enriquecida de Origen
            const originTip = '<div class="aura-tip-card" style="min-width:240px;padding:10px 12px;">' +
                '<div style="font-weight:700;font-size:12px;color:#ffffff;margin-bottom:4px;display:flex;align-items:center;gap:6px;"><span class="dashicons dashicons-tag" style="color:#f59e0b;"></span>Transacción de Origen</div>' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">' + (r.origin_description ? escapeHtml(r.origin_description) : 'Registro manual de adeudo') + '</div>' +
                (r.origin_date ? '<div style="font-size:10.5px;color:#94a3b8;margin-top:4px;border-top:1px solid #334155;padding-top:4px;">Fecha: ' + escapeHtml(r.origin_date) + '</div>' : '') +
            '</div>';

            return '<tr class="table-row-hover-lift">' +
                '<td style="white-space:nowrap;font-size:12px;color:var(--aura-text-muted,#64748b);"><span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;color:#94a3b8;vertical-align:middle;margin-right:2px;"></span>' + escapeHtml(String(r.created_at || '').slice(0, 10)) + '</td>' +
                '<td>' +
                    '<div class="aura-reimburse-person-cell">' +
                        avatarMarkup +
                        '<div class="aura-reimburse-person-info">' +
                            '<span class="aura-tooltip-trigger aura-reimburse-person-name" data-aura-tooltip="' + encodeURIComponent(personTip) + '" style="cursor:pointer;">' + escapeHtml(personName) + '</span>' +
                            '<span class="aura-reimburse-person-sub">' + escapeHtml(r.person_type_label || (r.counterparty_id ? 'Tercero' : 'Usuario')) + (r.person_document ? ' • ' + escapeHtml(r.person_document) : '') + '</span>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '<td><span class="badge badge-slate aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(originTip) + '" style="font-weight:700;cursor:help;">' + escapeHtml(originText) + '</span><br><small style="color:var(--aura-text-muted,#64748b);display:block;margin-top:2px;">' + escapeHtml(r.origin_description || '') + '</small></td>' +
                '<td style="font-weight:600;color:var(--aura-text-heading,#0f172a);"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(debtTip) + '" style="cursor:pointer;">$ ' + escapeHtml(formatNumber(owed)) + '</span></td>' +
                '<td style="color:#10b981;font-weight:600;"><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(debtTip) + '" style="cursor:pointer;">$ ' + escapeHtml(formatNumber(paid)) + '</span></td>' +
                '<td><span class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(debtTip) + '" style="cursor:pointer;"><strong style="color:' + (remaining > 0 ? '#ef4444' : '#10b981') + ';font-size:13px;">$ ' + escapeHtml(formatNumber(remaining)) + '</strong></span></td>' +
                '<td>' + reimbursementStatusBadge(r.status) + '</td>' +
                '<td><div class="aura-reimburse-actions" style="display:inline-flex;gap:6px;align-items:center;">' + actions.join('') + '</div></td>' +
                '</tr>';
        });

        $reimbursementsTableBody.html(html.join(''));
    }

    function resetReimburseForms() {
        if ($reimbursementsForm.length && $reimbursementsForm[0]) {
            $reimbursementsForm[0].reset();
        }
        if ($reimbursementsPayForm.length && $reimbursementsPayForm[0]) {
            $reimbursementsPayForm[0].reset();
        }
        $('#aura-reimburse-pay-id').val('0');
    }

    function loadReimbursements() {
        if (!$reimbursementsTableBody.length) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_reimbursements_list',
            nonce: auraFinancialAccounts.nonce
        }).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                window.auraReimbursementsCache = data.reimbursements || [];
                renderReimbursementsTable(window.auraReimbursementsCache);
                fillReimburseAccountOptions(data.paying_accounts || []);
                window.auraThirdPartiesCache = data.third_parties || [];
                window.auraAppUsersCache = data.users || (typeof auraFinancialAccounts !== 'undefined' ? auraFinancialAccounts.appUsers : []) || [];
                fillThirdPartyOptions(window.auraThirdPartiesCache, null, window.auraAppUsersCache);
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function resetReimburseForms() {
        const $reimburseForm = $('#aura-reimbursements-form');
        if ($reimburseForm.length && $reimburseForm[0]) {
            $reimburseForm[0].reset();
        }
        $('#aura-reimburse-id').val('0');
        $('#aura-reimburse-person').val('');
        $('#aura-reimburse-person-name').val('');
        $('#aura-reimburse-preview').hide();

        if ($reimbursementsPayForm.length && $reimbursementsPayForm[0]) {
            $reimbursementsPayForm[0].reset();
        }
        $('#aura-reimburse-pay-id').val('0');
        $('#aura-reimburse-pay-person').val('');
        $('#aura-reimburse-pay-person-name').val('');
        $('#aura-reimburse-pay-preview').hide();
        $('#aura-reimburse-pay-receipt').val('');
        $('#aura-reimburse-pay-receipt-preview').hide();
        $('#aura-reimburse-pay-create-tx').prop('checked', true);
        $('#aura-reimburse-pay-tx-fields').show();
    }

    function createReimbursement() {
        const rawPersonVal = String($('#aura-reimburse-person').val() || '').trim();
        if (!rawPersonVal) {
            showFeedback('Selecciona un usuario o tercero para registrar la deuda.', false);
            return;
        }

        let counterpartyId = 0;
        let personUserId = 0;

        if (rawPersonVal.indexOf('wp:') === 0) {
            personUserId = parseInt(rawPersonVal.substring(3), 10);
        } else if (rawPersonVal.indexOf('tp:') === 0) {
            counterpartyId = parseInt(rawPersonVal.substring(3), 10);
        } else {
            counterpartyId = parseInt(rawPersonVal, 10);
        }

        if (!counterpartyId && !personUserId) {
            showFeedback('Selecciona un usuario o tercero válido para registrar la deuda.', false);
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_reimbursements_create',
            nonce: auraFinancialAccounts.nonce,
            person_id: rawPersonVal,
            counterparty_id: counterpartyId,
            person_user_id: personUserId,
            owed_amount: $('#aura-reimburse-owed').val(),
            origin_transaction_id: $('#aura-reimburse-origin').val(),
            notes: $('#aura-reimburse-notes').val()
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Reembolso creado.', true);
                resetReimburseForms();
                window.AuraUI.closeModal('aura-finance-reimburse-modal');
                loadReimbursements();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
    }

    function showThirdPartyFeedback(message, isOk) {
        const $box = $('#aura-third-party-feedback');
        if (!$box.length) {
            return;
        }
        if (!message) {
            $box.hide().removeClass('is-error is-success').text('');
            return;
        }

        $box
            .show()
            .removeClass('is-error is-success')
            .addClass(isOk ? 'is-success' : 'is-error')
            .text(message);
    }

    function setThirdPartyFieldError(fieldId, message) {
        const $field = $('#' + fieldId);
        const $error = $('#' + fieldId + '-error');

        if (!$field.length || !$error.length) {
            return;
        }

        if (!message) {
            $field.removeClass('aura-input-invalid');
            $error.removeClass('is-visible').text('');
            return;
        }

        $field.addClass('aura-input-invalid');
        $error.addClass('is-visible').text(message);
    }

    function validateThirdPartyField(fieldId) {
        const value = String($('#' + fieldId).val() || '').trim();

        if (fieldId === 'aura-third-party-name') {
            if (value.length < 3) {
                setThirdPartyFieldError(fieldId, 'El nombre debe tener al menos 3 caracteres.');
                return false;
            }
            setThirdPartyFieldError(fieldId, '');
            return true;
        }

        if (fieldId === 'aura-third-party-email') {
            if (value !== '' && !isValidEmail(value)) {
                setThirdPartyFieldError(fieldId, 'Ingresa un correo válido.');
                return false;
            }
            setThirdPartyFieldError(fieldId, '');
            return true;
        }

        if (fieldId === 'aura-third-party-phone') {
            const digits = value.replace(/\D/g, '');
            if (value !== '' && digits.length < 7) {
                setThirdPartyFieldError(fieldId, 'El teléfono debe tener al menos 7 dígitos.');
                return false;
            }
            setThirdPartyFieldError(fieldId, '');
            return true;
        }

        if (fieldId === 'aura-third-party-document') {
            setThirdPartyFieldError(fieldId, '');
            return true;
        }

        return true;
    }

    function validateThirdPartyForm() {
        const fields = [
            'aura-third-party-name',
            'aura-third-party-document',
            'aura-third-party-phone',
            'aura-third-party-email'
        ];

        let valid = true;
        fields.forEach(function (fieldId) {
            if (!validateThirdPartyField(fieldId)) {
                valid = false;
            }
        });

        return valid;
    }

    function resetThirdPartyForm() {
        const $formLocal = $('#aura-third-party-form');
        if ($formLocal.length && $formLocal[0]) {
            $formLocal[0].reset();
        }

        $('#aura-third-party-id').val('0');
        $('#aura-third-party-mode').val('create');
        $('#aura-third-party-logo-id').val('0');
        $('#aura-third-party-type').val('company');
        $('#aura-third-party-commercial-name').val('');
        $('#aura-third-party-tax-type').val('NIT');
        $('#aura-third-party-website').val('');
        $('#aura-third-party-address').val('');
        $('#aura-third-party-notes').val('');

        $('#aura-third-party-logo-img').hide().attr('src', '');
        $('#aura-third-party-logo-icon-placeholder').show().attr('class', 'dashicons dashicons-building');
        $('#aura-third-party-logo-remove-btn').hide();

        $('#aura-third-party-modal-title').text('Nuevo Tercero / Empresa');
        $('#aura-third-party-save-btn').text('Guardar');

        ['aura-third-party-name', 'aura-third-party-document', 'aura-third-party-phone', 'aura-third-party-email'].forEach(function (fieldId) {
            setThirdPartyFieldError(fieldId, '');
        });

        showThirdPartyFeedback('', false);
    }

    function openThirdPartyEditForm(id) {
        const list = window.auraThirdPartiesManagementCache || [];
        const row = list.find(function (p) { return parseInt(p.id, 10) === parseInt(id, 10); });
        if (!row) {
            return;
        }

        resetThirdPartyForm();

        $('#aura-third-party-id').val(parseInt(row.id, 10) || 0);
        $('#aura-third-party-mode').val('edit');
        $('#aura-third-party-modal-title').text('Editar Tercero / Empresa');
        $('#aura-third-party-save-btn').text('Guardar cambios');

        $('#aura-third-party-type').val(row.party_type || 'company');
        $('#aura-third-party-commercial-name').val(row.commercial_name || '');
        $('#aura-third-party-name').val(row.full_name || '');
        $('#aura-third-party-tax-type').val(row.tax_id_type || 'NIT');
        $('#aura-third-party-document').val(row.document_id || '');
        $('#aura-third-party-phone').val(row.phone || '');
        $('#aura-third-party-email').val(row.email || '');
        $('#aura-third-party-website').val(row.website || '');
        $('#aura-third-party-address').val(row.address || '');
        $('#aura-third-party-notes').val(row.notes || '');

        const logoId = parseInt(row.logo_id, 10) || 0;
        $('#aura-third-party-logo-id').val(logoId);
        if (row.logo_url) {
            $('#aura-third-party-logo-img').attr('src', row.logo_url).show();
            $('#aura-third-party-logo-icon-placeholder').hide();
            $('#aura-third-party-logo-remove-btn').show();
        } else {
            const _iconMap = { company: 'dashicons-building', store: 'dashicons-cart', organization_foundation: 'dashicons-heart', person: 'dashicons-businessman', religious: 'dashicons-location-alt', other: 'dashicons-category' };
            const iconClass = _iconMap[row.party_type] || 'dashicons-building';
            $('#aura-third-party-logo-icon-placeholder').attr('class', 'dashicons ' + iconClass).show();
            $('#aura-third-party-logo-img').hide();
            $('#aura-third-party-logo-remove-btn').hide();
        }

        window.AuraUI.openModal('aura-finance-third-party-modal');
    }

    function createThirdPartyFromModal() {
        if (!validateThirdPartyForm()) {
            showThirdPartyFeedback('Corrige los campos marcados para continuar.', false);
            return;
        }

        const mode = String($('#aura-third-party-mode').val() || 'create');
        const thirdPartyId = parseInt($('#aura-third-party-id').val(), 10) || 0;
        const isEdit = mode === 'edit' && thirdPartyId > 0;

        const payload = {
            action: isEdit ? 'aura_finance_third_parties_update' : 'aura_finance_third_parties_create',
            nonce: auraFinancialAccounts.nonce,
            full_name: String($('#aura-third-party-name').val() || '').trim(),
            commercial_name: String($('#aura-third-party-commercial-name').val() || '').trim(),
            party_type: String($('#aura-third-party-type').val() || 'company'),
            tax_id_type: String($('#aura-third-party-tax-type').val() || 'NIT'),
            document_id: String($('#aura-third-party-document').val() || '').trim(),
            phone: String($('#aura-third-party-phone').val() || '').trim(),
            email: String($('#aura-third-party-email').val() || '').trim(),
            website: String($('#aura-third-party-website').val() || '').trim(),
            address: String($('#aura-third-party-address').val() || '').trim(),
            notes: String($('#aura-third-party-notes').val() || '').trim(),
            logo_id: parseInt($('#aura-third-party-logo-id').val(), 10) || 0
        };

        if (isEdit) {
            payload.id = thirdPartyId;
        }

        $('#aura-third-party-save-btn').prop('disabled', true).text('Guardando...');

        $.post(auraFinancialAccounts.ajaxUrl, payload).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                const list = data.third_parties || [];
                window.auraThirdPartiesManagementCache = list;

                const activeOnly = list.filter(function (p) {
                    return parseInt(p.is_active, 10) === 1;
                });
                window.auraThirdPartiesCache = activeOnly;
                fillThirdPartyOptions(window.auraThirdPartiesCache, data.id || (isEdit ? thirdPartyId : null));
                renderThirdPartyManageTable(window.auraThirdPartiesManagementCache);

                showThirdPartyFeedback((data.message) || (isEdit ? 'Tercero actualizado.' : 'Tercero registrado.'), true);
                showThirdPartyManageFeedback((data.message) || (isEdit ? 'Tercero actualizado.' : 'Tercero registrado.'), true);

                window.setTimeout(function () {
                    window.AuraUI.closeModal('aura-finance-third-party-modal');
                    showFeedback((data.message) || (isEdit ? 'Tercero actualizado.' : 'Tercero registrado.'), true);
                }, 350);
                return;
            }

            showThirdPartyFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showThirdPartyFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            const modeNow = String($('#aura-third-party-mode').val() || 'create');
            $('#aura-third-party-save-btn').prop('disabled', false).text(modeNow === 'edit' ? 'Guardar cambios' : 'Guardar');
        });
    }

    function toggleThirdParty(id, currentlyActive) {
        const activate = !currentlyActive;
        const question = activate
            ? '¿Reactivar este tercero?' : '¿Desactivar este tercero?';

        if (!window.confirm(question)) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_third_parties_toggle',
            nonce: auraFinancialAccounts.nonce,
            id: id,
            is_active: activate ? 1 : 0
        }).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                const list = data.third_parties || [];
                window.auraThirdPartiesManagementCache = list;
                const activeOnly = list.filter(function (p) {
                    return parseInt(p.is_active, 10) === 1;
                });
                window.auraThirdPartiesCache = activeOnly;
                renderThirdPartyManageTable(window.auraThirdPartiesManagementCache);
                fillThirdPartyOptions(window.auraThirdPartiesCache);
                showThirdPartyManageFeedback((data.message) || 'Estado actualizado.', true);
                return;
            }
            showThirdPartyManageFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showThirdPartyManageFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function convertThirdPartyToWpUser(id) {
        if (!window.confirm('¿Convertir este tercero en usuario WordPress con rol Suscriptor?')) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_third_parties_convert_user',
            nonce: auraFinancialAccounts.nonce,
            id: id,
            role: 'subscriber',
            send_invite: 1
        }).done(function (res) {
            if (res && res.success) {
                const data = res.data || {};
                const list = data.third_parties || [];
                window.auraThirdPartiesManagementCache = list;
                const activeOnly = list.filter(function (p) {
                    return parseInt(p.is_active, 10) === 1;
                });
                window.auraThirdPartiesCache = activeOnly;
                renderThirdPartyManageTable(window.auraThirdPartiesManagementCache);
                fillThirdPartyOptions(window.auraThirdPartiesCache);
                showThirdPartyManageFeedback((data.message) || 'Tercero convertido a usuario WP.', true);
                return;
            }
            showThirdPartyManageFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showThirdPartyManageFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function populateReimbursePayDebtSelect(selectedId) {
        const $select = $('#aura-reimburse-pay-debt-select');
        if (!$select.length) return;

        $select.empty();
        $select.append('<option value="direct">➕ Registrar Pago Directo (Sin registrar deuda previa)</option>');

        const cache = window.auraReimbursementsCache || [];
        const pendingDebts = cache.filter(function (r) {
            const owed = parseFloat(r.owed_amount || 0);
            const paid = parseFloat(r.paid_amount || 0);
            return (owed - paid) > 0.009 && r.status !== 'cancelled';
        });

        pendingDebts.forEach(function (r) {
            const owed = parseFloat(r.owed_amount || 0);
            const paid = parseFloat(r.paid_amount || 0);
            const remaining = Math.max(0, owed - paid);
            const name = r.person_name || ('Responsable #' + (r.person_user_id || r.counterparty_id));
            const optText = 'Deuda #' + r.id + ' - ' + name + ' (Pendiente: ' + formatNumber(remaining) + ')';
            $select.append('<option value="' + r.id + '">' + optText + '</option>');
        });

        if (selectedId && selectedId !== 'direct' && parseInt(selectedId, 10) > 0) {
            $select.val(String(selectedId));
            setReimbursePayMode(parseInt(selectedId, 10));
        } else {
            $select.val('direct');
            setReimbursePayMode('direct');
        }
    }

    function setReimbursePayMode(mode) {
        if (mode === 'direct' || !mode || mode === 0) {
            $('#aura-reimburse-pay-id').val('0');
            $('#aura-reimburse-pay-summary').hide().addClass('is-hidden');
            $('#aura-reimburse-pay-direct-person-wrap').show().removeClass('is-hidden');
            if (!$('#aura-reimburse-pay-concept').val()) {
                $('#aura-reimburse-pay-concept').val('Pago de reembolso directo');
            }
        } else {
            const id = parseInt(mode, 10);
            $('#aura-reimburse-pay-id').val(id);
            $('#aura-reimburse-pay-direct-person-wrap').hide().addClass('is-hidden');
            $('#aura-reimburse-pay-summary').show().removeClass('is-hidden');

            const cache = window.auraReimbursementsCache || [];
            const r = cache.find(function (item) { return parseInt(item.id, 10) === id; });
            if (r) {
                const remaining = Math.max(0, parseFloat(r.owed_amount || 0) - parseFloat(r.paid_amount || 0));
                $('#aura-reimburse-pay-sum-beneficiary').text(r.person_name || ('Responsable #' + (r.person_user_id || r.counterparty_id)));
                $('#aura-reimburse-pay-sum-owed').text(formatNumber(r.owed_amount || 0));
                $('#aura-reimburse-pay-sum-remaining').text(formatNumber(remaining));
                $('#aura-reimburse-pay-counterparty-id').val(r.counterparty_id || 0);
                $('#aura-reimburse-pay-user-id').val(r.person_user_id || 0);
                $('#aura-reimburse-pay-amount').val(remaining.toFixed(2));
                $('#aura-reimburse-pay-concept').val('Pago Reembolso #' + r.id + ' - ' + (r.person_name || 'Gastos'));
            }
        }
    }

    async function payReimbursement() {
        const id = parseInt($('#aura-reimburse-pay-id').val(), 10) || 0;
        const rawPersonVal = String($('#aura-reimburse-pay-person').val() || $('#aura-reimburse-pay-person-name').val() || '').trim();

        if (id <= 0 && !rawPersonVal) {
            showFeedback('Por favor selecciona o escribe el Beneficiario (Tercero o Usuario WP) del pago.', false);
            $('#aura-reimburse-pay-person-name').trigger('focus');
            return;
        }

        const paymentAmount = parseFloat($('#aura-reimburse-pay-amount').val()) || 0;
        if (paymentAmount <= 0) {
            showFeedback('El monto a pagar debe ser mayor a cero.', false);
            $('#aura-reimburse-pay-amount').trigger('focus');
            return;
        }

        const payingAccount = $('#aura-reimburse-pay-account').val();
        if (!payingAccount) {
            showFeedback('Por favor selecciona una cuenta bancaria de pago.', false);
            $('#aura-reimburse-pay-account').trigger('focus');
            return;
        }

        const createTx = $('#aura-reimburse-pay-create-tx').is(':checked') ? 1 : 0;
        const catId = parseInt($('#aura-reimburse-pay-category').val(), 10) || 0;

        if (createTx && catId <= 0) {
            showFeedback('Por favor selecciona una Categoría Contable para registrar el egreso en el Libro Mayor.', false);
            $('#aura-reimburse-pay-category').trigger('focus');
            return;
        }

        if (auraFinancialAccounts.i18n.reimbursementPayConfirm && !window.confirm(auraFinancialAccounts.i18n.reimbursementPayConfirm)) {
            return;
        }

        const $btn = $('#aura-reimburse-pay-save-btn');
        const fd = new window.FormData();
        fd.append('action', 'aura_finance_reimbursements_pay');
        fd.append('nonce', auraFinancialAccounts.nonce);
        fd.append('id', id);
        fd.append('person_id', rawPersonVal);
        fd.append('paying_account_id', payingAccount);
        fd.append('payment_amount', paymentAmount);
        fd.append('payment_date', $('#aura-reimburse-pay-date').val());
        fd.append('payment_method', $('#aura-reimburse-pay-method').val());
        fd.append('notes', $('#aura-reimburse-pay-notes').val());
        fd.append('create_transaction', createTx);
        fd.append('category_id', catId);
        fd.append('area_id', $('#aura-reimburse-pay-area').val() || '');
        fd.append('concept', $('#aura-reimburse-pay-concept').val() || '');

        const receiptInput = document.getElementById('aura-reimburse-pay-receipt');
        if (receiptInput && receiptInput.files && receiptInput.files.length) {
            $btn.prop('disabled', true).text('Procesando comprobante...');
            try {
                const processed = await compressImage(receiptInput.files[0]);
                fd.append('receipt_file', processed);
            } catch (e) {
                fd.append('receipt_file', receiptInput.files[0]);
            }
        }

        $btn.prop('disabled', true).text('Aplicando pago...');

        $.ajax({
            url: auraFinancialAccounts.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-right:4px;vertical-align:text-bottom;"></span> Aplicar Pago y Contabilizar');
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Pago aplicado correctamente.', true);
                resetReimburseForms();
                window.AuraUI.closeModal('aura-finance-reimburse-pay-modal');
                loadReimbursements();
                loadAccounts();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-right:4px;vertical-align:text-bottom;"></span> Aplicar Pago y Contabilizar');
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function renderSimpleRows($tbody, rows, emptyCols, builder) {
        if (!$tbody.length) {
            return;
        }
        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="' + emptyCols + '">Sin datos disponibles.</td></tr>');
            return;
        }
        $tbody.html(rows.map(builder).join(''));
    }

    function renderReports(data) {
        const accounts = data.accounts || [];
        const currencies = data.currency_summary || [];
        const types = data.type_summary || [];
        const budget = data.budget || {};
        const months = budget.months || [];
        const audit = data.audit || {};
        const cashFlow = data.cash_flow_totals || {};
        const auditFindings = [
            { label: 'Transacciones aprobadas con cuenta', value: audit.approved_with_account || 0, tone: 'neutral' },
            { label: 'Aprobadas sin cuenta obligatoria', value: audit.missing_account_count || 0, tone: (audit.missing_account_count || 0) > 0 ? 'danger' : 'ok' },
            { label: 'Aprobadas sin movimiento contable', value: audit.approved_without_movement || 0, tone: (audit.approved_without_movement || 0) > 0 ? 'danger' : 'ok' },
            { label: 'Movimientos huérfanos', value: audit.orphan_movements || 0, tone: (audit.orphan_movements || 0) > 0 ? 'danger' : 'ok' },
            { label: 'Cuentas en negativo', value: audit.negative_accounts || 0, tone: (audit.negative_accounts || 0) > 0 ? 'warn' : 'ok' }
        ];

        $('#aura-report-kpi-inflows').text(formatNumber(cashFlow.inflows || 0));
        $('#aura-report-kpi-outflows').text(formatNumber(cashFlow.outflows || 0));
        $('#aura-report-kpi-budget').text(formatNumber(budget.annual_spent || 0));
        $('#aura-report-kpi-audit').text(
            (audit.missing_account_count || 0) +
            (audit.approved_without_movement || 0) +
            (audit.orphan_movements || 0) +
            (audit.negative_accounts || 0)
        );

        renderSimpleRows($('#aura-report-accounts-table tbody'), accounts, 5, function (row) {
            return '<tr class="table-row-hover-lift">' +
                '<td><strong>' + escapeHtml(row.name || '') + '</strong><br><small style="color:var(--aura-text-muted,#64748b);">' + escapeHtml(typeLabel(row.account_type || 'custom')) + '</small></td>' +
                '<td><span class="badge badge-slate" style="font-weight:700;">' + escapeHtml(String(row.currency || '').toUpperCase()) + '</span></td>' +
                '<td>' + escapeHtml(formatNumber(row.inflows || 0)) + '</td>' +
                '<td>' + escapeHtml(formatNumber(row.outflows || 0)) + '</td>' +
                '<td><strong style="color:var(--aura-text-heading,#0f172a);">' + escapeHtml(formatNumber(row.current_balance || 0)) + '</strong></td>' +
                '</tr>';
        });

        renderSimpleRows($('#aura-report-currency-table tbody'), currencies, 3, function (row) {
            return '<tr class="table-row-hover-lift"><td><span class="badge badge-indigo" style="font-weight:700;">' + escapeHtml(String(row.currency || '').toUpperCase()) + '</span></td><td>' +
                escapeHtml(row.account_count) + '</td><td><strong style="color:var(--aura-text-heading,#0f172a);">' + escapeHtml(formatNumber(row.total_balance || 0)) + '</strong></td></tr>';
        });

        renderSimpleRows($('#aura-report-type-table tbody'), types, 3, function (row) {
            return '<tr class="table-row-hover-lift"><td>' + typeBadge(row.account_type || 'custom') + '</td><td>' +
                escapeHtml(row.account_count) + '</td><td><strong style="color:var(--aura-text-heading,#0f172a);">' + escapeHtml(formatNumber(row.total_balance || 0)) + '</strong></td></tr>';
        });

        window.auraBudgetReportCache = budget; // Store for inline editing
        const isEditMode = $('#aura-inline-budget-save-btn').is(':visible');

        let topeHtml = '<span><strong>Tope:</strong> ' + escapeHtml(formatNumber(budget.annual_limit || 0)) + '</span>';
        if (isEditMode) {
            topeHtml = '<span><strong>Tope:</strong> <input type="number" id="aura-inline-budget-annual" value="' + (budget.annual_limit || 0) + '" style="width:100px;"></span>';
        }

        $('#aura-report-budget-summary').html(
            topeHtml +
            '<span><strong>Ejecutado:</strong> ' + escapeHtml(formatNumber(budget.annual_spent || 0)) + '</span>' +
            '<span><strong>Disponible:</strong> ' + escapeHtml(formatNumber(budget.annual_remaining || 0)) + '</span>' +
            '<span><strong>Política:</strong> ' + escapeHtml((budget.policy || 'warn') === 'block' ? 'Bloquear' : 'Advertir') + '</span>'
        );

        renderSimpleRows($('#aura-report-budget-table tbody'), months, 5, function (row) {
            const isNegative = parseFloat(row.remaining || 0) < 0;
            const limit = parseFloat(row.limit || 0);
            const spent = parseFloat(row.spent || 0);
            const ratio = limit > 0 ? (spent / limit) * 100 : (spent > 0 ? 100 : 0);
            const ratioSafe = Math.max(0, ratio);
            const fill = Math.min(100, ratioSafe);

            const fillModifier = ratioSafe >= 90 ? 'micro-progress-fill--rose' : (ratioSafe >= 70 ? 'micro-progress-fill--amber' : 'micro-progress-fill--emerald');

            const percentText = (Math.round(ratioSafe * 10) / 10).toFixed(1).replace('.0', '') + '%';
            const progressHint = limit > 0
                ? (formatNumber(spent) + ' / ' + formatNumber(limit))
                : 'Sin límite definido';

            let limitHtml = escapeHtml(formatNumber(row.limit || 0));
            if (isEditMode) {
                limitHtml = '<input type="number" class="aura-inline-budget-month" data-month="' + row.month_num + '" value="' + (row.limit || 0) + '" style="width:100px;">';
            }

            return '<tr class="table-row-hover-lift">' +
                '<td><strong>' + escapeHtml(monthNames[(parseInt(row.month_num, 10) || 1) - 1]) + '</strong></td>' +
                '<td>' + limitHtml + '</td>' +
                '<td>' + escapeHtml(formatNumber(row.spent || 0)) + '</td>' +
                '<td><span class="' + (isNegative ? 'badge badge-rose' : 'badge badge-emerald') + '">' + escapeHtml(formatNumber(row.remaining || 0)) + '</span></td>' +
                '<td><div style="display:flex;align-items:center;gap:8px;" data-tooltip="' + escapeHtml(progressHint) + '">' +
                '<div class="micro-progress-bar" style="flex:1;"><div class="micro-progress-fill ' + fillModifier + '" style="width:' + fill + '%;"></div></div>' +
                '<span style="font-size:12px;font-weight:700;color:var(--aura-text-muted,#475569);min-width:38px;text-align:right;">' + escapeHtml(percentText) + '</span>' +
                '</div></td>' +
                '</tr>';
        });

        $('#aura-report-audit-list').html(auditFindings.map(function (item) {
            return '<div class="aura-report-audit-item is-' + escapeHtml(item.tone) + '">' +
                '<span>' + escapeHtml(item.label) + '</span>' +
                '<strong>' + escapeHtml(item.value) + '</strong>' +
                '</div>';
        }).join(''));
    }

    function loadReports() {
        if (!$('#aura-report-refresh-btn').length) {
            return;
        }

        const year = parseInt($('#aura-report-year').val(), 10) || new Date().getFullYear();
        $('#aura-report-refresh-btn').prop('disabled', true).text('Actualizando...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_accounts_report',
            nonce: auraFinancialAccounts.nonce,
            year: year
        }).done(function (res) {
            if (res && res.success) {
                window.auraLastReportData = res.data || {};
                renderReports(window.auraLastReportData);
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-report-refresh-btn').prop('disabled', false).text('Actualizar reportes');
        });
    }

    $('#aura-account-new-btn').on('click', function (e) {
        e.preventDefault();
        resetForm();
        window.AuraUI.openModal('aura-finance-account-modal');
    });

    $(document).on('change', '#aura-account-type', function () {
        const type = $(this).val();
        const $curr = $('#aura-account-currency');
        if (type === 'usd_cash') {
            $curr.val('USD');
        } else if (type === 'eur_cash') {
            $curr.val('EUR');
        } else if (type === 'cad_cash') {
            $curr.val('CAD');
        }
    });

    $('#aura-budget-open-btn, #aura-budget-open-inline-btn').on('click', function () {
        setWizardStep('aura-budget-modal-wizard', 1);
        window.AuraUI.openModal('aura-finance-budget-modal');
    });

    $('#aura-petty-open-btn, #aura-petty-open-inline-btn').on('click', function () {
        resetPettyForm();
        window.AuraUI.openModal('aura-finance-petty-modal');
    });

    $('#aura-petty-tab-delivery, #aura-petty-tab-settlement').on('click', function () {
        if ($(this).prop('disabled')) return;
        switchPettyTab($(this).data('tab'));
    });

    $(document).on('click', '.aura-petty-receipt-mode-btn', function (e) {
        e.preventDefault();
        var mode = $(this).data('mode');
        $('.aura-petty-receipt-mode-btn').removeClass('is-active').css({background: 'transparent', color: '#64748b', 'box-shadow': 'none'});
        $(this).addClass('is-active').css({background: '#ffffff', color: '#0f172a', 'box-shadow': '0 1px 2px rgba(0,0,0,0.06)'});
        if (mode === 'url') {
            $('#aura-petty-delivery-receipt-file-wrap').hide();
            $('#aura-petty-delivery-receipt-url-wrap').show();
            $('#aura-petty-delivery-receipt-url').focus();
        } else {
            $('#aura-petty-delivery-receipt-file-wrap').show();
            $('#aura-petty-delivery-receipt-url-wrap').hide();
        }
    });

    $(document).on('click', '#aura-reimburse-open-btn, #aura-reimburse-open-inline-btn', function () {
        if ($reimbursementsForm.length && $reimbursementsForm[0]) {
            $reimbursementsForm[0].reset();
        }
        fillThirdPartyOptions(window.auraThirdPartiesCache || [], null, window.auraAppUsersCache || (typeof auraFinancialAccounts !== 'undefined' ? auraFinancialAccounts.appUsers : []));
        window.AuraUI.openModal('aura-finance-reimburse-modal');
    });

    $(document).on('click', '#aura-reimburse-pay-open-btn, #aura-reimburse-pay-open-inline-btn', function () {
        if ($reimbursementsPayForm.length && $reimbursementsPayForm[0]) {
            $reimbursementsPayForm[0].reset();
        }
        $('#aura-reimburse-pay-id').val('0');
        $('#aura-reimburse-pay-person').val('');
        $('#aura-reimburse-pay-person-name').val('');
        $('#aura-reimburse-pay-preview').hide();
        $('#aura-reimburse-pay-receipt').val('');
        $('#aura-reimburse-pay-receipt-preview').hide();
        $('#aura-reimburse-pay-create-tx').prop('checked', true);
        $('#aura-reimburse-pay-tx-fields').show();

        populateReimbursePayDebtSelect('direct');
        window.AuraUI.openModal('aura-finance-reimburse-pay-modal');
    });

    $('#aura-account-reset-btn').on('click', function () {
        resetForm();
    });

    $('#aura-budget-step-next').on('click', function () {
        if (!validateBudgetStepOne()) {
            return;
        }
        setWizardStep('aura-budget-modal-wizard', 2);
    });

    $('#aura-budget-step-back').on('click', function () {
        setWizardStep('aura-budget-modal-wizard', 1);
    });

    $('#aura-petty-step-next-1').on('click', function () {
        if (!validatePettyStepOne()) {
            return;
        }
        setWizardStep('aura-petty-modal-wizard', 2);
    });

    $('#aura-petty-step-back-2').on('click', function () {
        setWizardStep('aura-petty-modal-wizard', 1);
    });

    $('#aura-petty-step-next-2').on('click', function () {
        updatePettyStepThreeHint();
        setWizardStep('aura-petty-modal-wizard', 3);
    });

    $('#aura-petty-step-back-3').on('click', function () {
        setWizardStep('aura-petty-modal-wizard', 2);
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        const data = {
            action: 'aura_finance_accounts_save',
            nonce: auraFinancialAccounts.nonce,
            id: $('#aura-account-id').val(),
            name: $('#aura-account-name').val(),
            account_type: $('#aura-account-type').val(),
            currency: $('#aura-account-currency').val(),
            institution: $('#aura-account-institution').val(),
            account_number_masked: $('#aura-account-number').val(),
            initial_balance: $('#aura-account-initial-balance').val(),
            current_balance: $('#aura-account-current-balance').val(),
            is_active: $('#aura-account-active').is(':checked') ? 1 : 0
        };

        saveAccount(data);
    });

    $budgetForm.on('submit', function (e) {
        e.preventDefault();
        saveBudget();
    });

    $('#aura-budget-load-btn').on('click', function () {
        loadBudgetByYear();
    });

    $('#aura-budget-policy, #aura-budget-annual-limit').on('change input', function () {
        updateBudgetOverview();
    });

    $('#aura-budget-import-btn').on('click', function () {
        importBudgetFile();
    });

    $('#aura-budget-validate-btn').on('click', function () {
        validateBudgetImport();
    });

    $('#aura-budget-confirm-btn').on('click', function () {
        executeBudgetImport();
    });

    $('#aura-report-refresh-btn').on('click', function () {
        loadReports();
    });

    $(document).on('input change', '.aura-budget-month-input', function () {
        updateBudgetOverview();
    });

    $search.on('input', function () {
        applyFilters();
    });

    $('#aura-filter-type, #aura-filter-currency, #aura-filter-status').on('change', function () {
        applyFilters();
    });

    $('#aura-filter-reset').on('click', function () {
        $('#aura-filter-type').val('');
        $('#aura-filter-currency').val('');
        $('#aura-filter-status').val('');
        $search.val('');
        applyFilters();
    });

    $(document).on('click', '.aura-account-edit', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraAccountsCache || [];
        const account = list.find(function (item) { return parseInt(item.id, 10) === id; });
        if (account) {
            fillForm(account);
            window.AuraUI.openModal('aura-finance-account-modal');
        }
    });

    $(document).on('click', '.aura-account-delete', function () {
        deleteAccount(parseInt($(this).data('id'), 10));
    });

    // Delivery form submit
    $('#aura-petty-delivery-form').on('submit', function (e) {
        e.preventDefault();
        createPettyCash();
    });

    // Settlement form submit
    $pettyForm.on('submit', function (e) {
        e.preventDefault();
        const id = parseInt($('#aura-petty-id').val(), 10);
        if (!id) {
            showFeedback('No hay una entrega cargada. Usa el ícono ✏️ de la tabla para cargar una.', false);
            return;
        }
        submitPettyCash(id);
    });

    $reimbursementsForm.on('submit', function (e) {
        e.preventDefault();
        createReimbursement();
    });

    $('#aura-third-party-new-btn').on('click', function (e) {
        e.preventDefault();
        resetThirdPartyForm();
        window.AuraUI.openModal('aura-finance-third-party-modal');
        window.setTimeout(function () {
            $('#aura-third-party-name').trigger('focus');
        }, 80);
    });

    $('#aura-third-party-manage-btn').on('click', function (e) {
        e.preventDefault();
        showThirdPartyManageFeedback('', false);
        window.AuraUI.openModal('aura-finance-third-party-manage-modal');
        loadThirdPartiesManagement();
    });

    $('#aura-third-party-manage-new-inline').on('click', function (e) {
        e.preventDefault();
        resetThirdPartyForm();
        window.AuraUI.openModal('aura-finance-third-party-modal');
    });

    $('#aura-third-party-form').on('submit', function (e) {
        e.preventDefault();
        createThirdPartyFromModal();
    });

    let thirdPartyMediaFrame = null;
    $('#aura-third-party-logo-upload-btn').on('click', function (e) {
        e.preventDefault();
        if (thirdPartyMediaFrame) {
            thirdPartyMediaFrame.open();
            return;
        }
        if (typeof wp !== 'undefined' && wp.media) {
            thirdPartyMediaFrame = wp.media({
                title: 'Seleccionar Logo o Imagen',
                button: { text: 'Usar como Logo' },
                multiple: false,
                library: { type: 'image' }
            });
            thirdPartyMediaFrame.on('select', function () {
                const attachment = thirdPartyMediaFrame.state().get('selection').first().toJSON();
                if (attachment && attachment.id) {
                    $('#aura-third-party-logo-id').val(attachment.id);
                    const url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
                    $('#aura-third-party-logo-img').attr('src', url).show();
                    $('#aura-third-party-logo-icon-placeholder').hide();
                    $('#aura-third-party-logo-remove-btn').show();
                }
            });
            thirdPartyMediaFrame.open();
        }
    });

    $('#aura-third-party-logo-remove-btn').on('click', function (e) {
        e.preventDefault();
        $('#aura-third-party-logo-id').val('0');
        $('#aura-third-party-logo-img').hide().attr('src', '');
        $('#aura-third-party-logo-icon-placeholder').show();
        $(this).hide();
    });

    $('#aura-third-party-type').on('change', function () {
        const ptype = $(this).val();
        const _iconMap = { company: 'dashicons-building', store: 'dashicons-cart', organization_foundation: 'dashicons-heart', person: 'dashicons-businessman', religious: 'dashicons-location-alt', other: 'dashicons-category' };
        const iconClass = _iconMap[ptype] || 'dashicons-building';
        $('#aura-third-party-logo-icon-placeholder').attr('class', 'dashicons ' + iconClass);
        if (ptype === 'person') {
            $('#aura-third-party-name-label').html('Nombre Completo *');
            $('#aura-third-party-tax-type').val('CC');
        } else {
            $('#aura-third-party-name-label').html('Razón Social o Nombre Oficial *');
            $('#aura-third-party-tax-type').val('NIT');
        }
    });

    $('#aura-third-party-name, #aura-third-party-commercial-name, #aura-third-party-document, #aura-third-party-phone, #aura-third-party-email').on('input blur', function () {
        validateThirdPartyField($(this).attr('id'));
    });

    $(document).on('click', '.aura-third-party-edit', function () {
        const id = parseInt($(this).data('id'), 10);
        if (!id) {
            return;
        }
        openThirdPartyEditForm(id);
    });

    $(document).on('click', '.aura-third-party-toggle', function () {
        const id = parseInt($(this).data('id'), 10);
        const active = parseInt($(this).data('active'), 10) === 1;
        if (!id) {
            return;
        }
        toggleThirdParty(id, active);
    });

    $(document).on('click', '.aura-third-party-convert', function () {
        const id = parseInt($(this).data('id'), 10);
        if (!id) {
            return;
        }
        convertThirdPartyToWpUser(id);
    });

    $reimbursementsPayForm.on('submit', function (e) {
        e.preventDefault();
        payReimbursement();
    });

    $('#aura-petty-reset-btn').on('click', function () {
        resetPettyForm();
    });

    $('#aura-petty-clear-settle-btn').on('click', function () {
        resetPettyForm();
        window.AuraUI.closeModal('aura-finance-petty-modal');
    });

    $(document).on('click', '.aura-petty-pick', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraPettyCashCache || [];
        const row = list.find(function (item) { return parseInt(item.id, 10) === id; });
        if (!row) {
            return;
        }
        resetPettyForm();
        loadSettlementData(row);
        window.AuraUI.openModal('aura-finance-petty-modal');
    });

    $(document).on('input', '#aura-petty-spent', function () {
        calculatePettyTotalSpent();
    });

    $(document).on('click', '.aura-petty-action', function () {
        const id = parseInt($(this).data('id'), 10);
        const status = $(this).data('status');
        if (!id || !status) {
            return;
        }
        updatePettyStatus(id, status);
    });

    $(document).on('click', '.aura-petty-delete', function () {
        const id = parseInt($(this).data('id'), 10);
        if (!id) return;
        deletePettyCash(id);
    });

    $(document).on('click', '.aura-petty-pay-excedent', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraPettyCashCache || [];
        const row = list.find(function (item) { return parseInt(item.id, 10) === id; });
        if (!row) return;

        const spentAmt = parseFloat(row.spent_amount || 0);
        const delivAmt = parseFloat(row.delivered_amount || 0);
        const excedent = Math.max(0, spentAmt - delivAmt);

        if (excedent <= 0) {
            showFeedback('Esta entrega no presenta excedente a favor del custodio.', false);
            return;
        }

        if ($reimbursementsPayForm.length && $reimbursementsPayForm[0]) {
            $reimbursementsPayForm[0].reset();
        }

        const personIdPrefixed = (row.counterparty_id && parseInt(row.counterparty_id, 10) > 0)
            ? ('tp:' + row.counterparty_id)
            : ((row.responsible_user_id && parseInt(row.responsible_user_id, 10) > 0) ? ('wp:' + row.responsible_user_id) : '');

        $('#aura-reimburse-pay-id').val('0');
        $('#aura-reimburse-pay-person').val(personIdPrefixed);
        $('#aura-reimburse-pay-person-name').val(row.responsible_name || '');
        $('#aura-reimburse-pay-amount').val(excedent.toFixed(2));
        $('#aura-reimburse-pay-concept').val('Reembolso de excedente de Caja Chica #' + row.id + ' - ' + (row.responsible_name || ''));
        $('#aura-reimburse-pay-notes').val('Liquidación de excedente en rendición de Caja Chica #' + row.id);
        $('#aura-reimburse-pay-create-tx').prop('checked', true);
        $('#aura-reimburse-pay-tx-fields').show();

        if (typeof setReimbursePayMode === 'function') {
            setReimbursePayMode('direct');
        }
        $('#aura-reimburse-pay-debt-select').val('direct');

        window.AuraUI.openModal('aura-finance-reimburse-pay-modal');
        showFeedback('Preparando liquidación del excedente ($' + formatNumber(excedent) + ') para ' + (row.responsible_name || 'el custodio') + '.', true);
    });

    $(document).on('click', '.aura-petty-view-evidence', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraPettyCashCache || [];
        const row = list.find(function (item) { return parseInt(item.id, 10) === id; });
        if (!row) {
            return;
        }
        openEvidenceModal(row);
    });

    $(document).on('change', '#aura-reimburse-pay-debt-select', function () {
        const val = $(this).val();
        setReimbursePayMode(val === 'direct' ? 'direct' : parseInt(val, 10));
    });

    $(document).on('click', '.aura-reimburse-pay-pick', function () {
        const id = parseInt($(this).data('id'), 10);
        if ($reimbursementsPayForm.length && $reimbursementsPayForm[0]) {
            $reimbursementsPayForm[0].reset();
        }
        $('#aura-reimburse-pay-person').val('');
        $('#aura-reimburse-pay-person-name').val('');
        $('#aura-reimburse-pay-preview').hide();
        $('#aura-reimburse-pay-receipt').val('');
        $('#aura-reimburse-pay-receipt-preview').hide();
        $('#aura-reimburse-pay-create-tx').prop('checked', true);
        $('#aura-reimburse-pay-tx-fields').show();

        populateReimbursePayDebtSelect(id);
        window.AuraUI.openModal('aura-finance-reimburse-pay-modal');
    });

    $(document).on('change', '#aura-reimburse-pay-create-tx', function () {
        if ($(this).is(':checked')) {
            $('#aura-reimburse-pay-tx-fields').slideDown(200);
        } else {
            $('#aura-reimburse-pay-tx-fields').slideUp(200);
        }
    });

    $(document).on('change', '#aura-reimburse-pay-receipt', function () {
        const file = this.files && this.files[0];
        if (file) {
            $('#aura-reimburse-pay-receipt-name').text(file.name);
            $('#aura-reimburse-pay-receipt-preview').show();
        } else {
            $('#aura-reimburse-pay-receipt-preview').hide();
        }
    });

    $(document).on('click', '#aura-reimburse-pay-receipt-clear', function (e) {
        e.preventDefault();
        $('#aura-reimburse-pay-receipt').val('');
        $('#aura-reimburse-pay-receipt-preview').hide();
    });

    $(document).on('click', '.aura-reimburse-delete', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        if (!id) {
            return;
        }

        if (!window.confirm('¿Estás seguro de que deseas eliminar este reembolso? Esta acción no se puede deshacer.')) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_reimbursements_delete',
            id: id,
            nonce: auraFinancialAccounts.nonce
        }).done(function (res) {
            if (res && res.success) {
                showFeedback(res.data && res.data.message ? res.data.message : 'Reembolso eliminado correctamente.', true);
                loadReimbursements();
            } else {
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            }
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    });

    let isBackdropMouseDown = false;
    $(document).on('mousedown', '.aura-modal-overlay, .aura-finance-modal__backdrop', function (e) {
        isBackdropMouseDown = (e.target === this);
    });

    $(document).on('click', '[data-modal-close]', function (e) {
        if ($(this).hasClass('aura-modal-overlay') || $(this).hasClass('aura-finance-modal__backdrop')) {
            if (!isBackdropMouseDown || e.target !== this) {
                return;
            }
        }
        window.AuraUI.closeModal($(this).data('modal-close'));
        isBackdropMouseDown = false;
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            const $visibleModal = $('.aura-modal-overlay.active').last();
            if ($visibleModal.length) {
                window.AuraUI.closeModal($visibleModal.attr('id'));
            }
        }
    });

    $('#aura-petty-evidence-close').on('click', function () {
        closeEvidenceModal();
    });

    $(document).on('click', '#aura-petty-evidence-modal [data-close="1"]', function () {
        closeEvidenceModal();
    });

    // --- Currency Exchange Multi-Currency Helpers ---
    const AURA_FOREIGN_CURRENCIES = {
        USD: { name: 'Dólares', symbol: '$', code: 'USD', boxName: 'Caja USD' },
        EUR: { name: 'Euros', symbol: '€', code: 'EUR', boxName: 'Caja EUR' },
        CAD: { name: 'Dólares Canadienses', symbol: 'C$', code: 'CAD', boxName: 'Caja CAD' }
    };

    function getForeignCurrencyMeta(curr) {
        const c = String(curr || 'USD').toUpperCase();
        return AURA_FOREIGN_CURRENCIES[c] || { name: c, symbol: '$', code: c, boxName: 'Caja ' + c };
    }

    window.auraLastExchangeEdited = 'usd';
    window.auraExchangeHistoryCache = [];

    function populateExchangeAccounts(direction, foreignCurrency) {
        const list = window.auraAccountsCache || [];
        const $source = $('#aura-exchange-source');
        const $target = $('#aura-exchange-target');
        const currentSource = $source.val();
        const currentTarget = $target.val();

        // Determinar divisa extranjera seleccionada
        const targetForeign = String(foreignCurrency || $('#aura-exchange-currency-select').val() || 'USD').toUpperCase();
        const meta = getForeignCurrencyMeta(targetForeign);

        // Sincronizar selector si difiere
        if ($('#aura-exchange-currency-select').val() !== targetForeign) {
            $('#aura-exchange-currency-select').val(targetForeign);
        }

        // Actualizar textos dinámicos según divisa extranjera
        $('#aura-exchange-dir-buy-title').text('Comprar ' + meta.name);
        $('#aura-exchange-dir-buy-sub').text('Moneda Local ➔ ' + meta.boxName);
        $('#aura-exchange-dir-sell-title').text('Vender ' + meta.name);
        $('#aura-exchange-dir-sell-sub').text(meta.boxName + ' ➔ Moneda Local');
        $('#aura-exchange-rate-label').text('Tasa de Cambio (1 ' + meta.code + ' = X Local)');
        $('#aura-exchange-amount-usd-label').text('Monto en ' + meta.name + ' (' + meta.code + ')');
        $('#aura-exchange-foreign-symbol').text(meta.symbol);
        $('#aura-exchange-foreign-code').text(meta.code);

        $source.empty();
        $target.empty();

        if (direction === 'local_to_usd') {
            $('#aura-exchange-source-label').text('Cuenta Origen (Moneda Local / Banco / Caja)');
            $('#aura-exchange-target-label').text('Cuenta Destino (' + meta.boxName + ' / Banco ' + meta.code + ')');
            $('#aura-exchange-summary-badge').text('Compra de ' + meta.name + ' (Local ➔ ' + meta.code + ')');
            $source.append('<option value="">Selecciona cuenta local de origen...</option>');
            $target.append('<option value="">Selecciona cuenta/caja ' + meta.code + ' de destino...</option>');
        } else {
            $('#aura-exchange-source-label').text('Cuenta Origen (' + meta.boxName + ' / Banco ' + meta.code + ')');
            $('#aura-exchange-target-label').text('Cuenta Destino (Moneda Local / Banco / Caja)');
            $('#aura-exchange-summary-badge').text('Venta de ' + meta.name + ' (' + meta.code + ' ➔ Local)');
            $source.append('<option value="">Selecciona cuenta ' + meta.code + ' de origen...</option>');
            $target.append('<option value="">Selecciona cuenta local de destino...</option>');
        }

        list.forEach(function (a) {
            if (parseInt(a.is_active, 10) !== 1) return;
            const balance = formatNumber(a.current_balance || 0);
            const label = escapeHtml(a.name) + ' (' + balance + ' ' + escapeHtml(a.currency) + ')';
            const opt = '<option value="' + a.id + '" data-currency="' + escapeHtml(a.currency) + '" data-balance="' + parseFloat(a.current_balance || 0) + '">' + label + '</option>';
            const isSelectedForeign = String(a.currency).toUpperCase() === targetForeign;

            if (direction === 'local_to_usd') {
                if (!isSelectedForeign) {
                    $source.append(opt);
                } else {
                    $target.append(opt);
                }
            } else {
                if (isSelectedForeign) {
                    $source.append(opt);
                } else {
                    $target.append(opt);
                }
            }
        });

        // Intentar preservar selección si aún es válida
        if (currentSource && $source.find('option[value="' + currentSource + '"]').length) {
            $source.val(currentSource);
        }
        if (currentTarget && $target.find('option[value="' + currentTarget + '"]').length) {
            $target.val(currentTarget);
        }

        updateExchangeImpactCard();
    }

    function openExchangeModal() {
        const $exForm = $('#aura-exchange-form');
        if ($exForm.length && $exForm[0]) {
            $exForm[0].reset();
        }
        $('input[name="exchange_direction"][value="local_to_usd"]').prop('checked', true);
        $('.aura-exchange-dir-card').removeClass('is-active');
        $('input[name="exchange_direction"][value="local_to_usd"]').closest('.aura-exchange-dir-card').addClass('is-active');
        window.auraLastExchangeEdited = 'usd';

        // Auto-detectar divisa extranjera si hay cuentas creadas
        const accounts = window.auraAccountsCache || [];
        let defaultForeign = 'USD';
        const found = accounts.find(a => ['USD', 'EUR', 'CAD'].indexOf(String(a.currency).toUpperCase()) !== -1);
        if (found) {
            defaultForeign = String(found.currency).toUpperCase();
        }
        $('#aura-exchange-currency-select').val(defaultForeign);

        populateExchangeAccounts('local_to_usd', defaultForeign);
        $('#aura-exchange-amount-local-input').val('');
        $('#aura-exchange-amount-usd').val('');
        updateExchangeImpactCard();
        window.AuraUI.openModal('aura-finance-exchange-modal');
    }

    function updateExchangeCalculation(trigger) {
        const rate = parseMoney($('#aura-exchange-rate').val());
        let usd = parseMoney($('#aura-exchange-amount-usd').val());
        let local = parseMoney($('#aura-exchange-amount-local-input').val());

        if (trigger === 'usd') {
            window.auraLastExchangeEdited = 'usd';
            if (usd > 0 && rate > 0) {
                local = Number((usd * rate).toFixed(2));
                $('#aura-exchange-amount-local-input').val(local.toFixed(2));
            } else if ($('#aura-exchange-amount-usd').val() === '') {
                $('#aura-exchange-amount-local-input').val('');
            }
        } else if (trigger === 'local') {
            window.auraLastExchangeEdited = 'local';
            if (local > 0 && rate > 0) {
                usd = Number((local / rate).toFixed(2));
                $('#aura-exchange-amount-usd').val(usd.toFixed(2));
            } else if ($('#aura-exchange-amount-local-input').val() === '') {
                $('#aura-exchange-amount-usd').val('');
            }
        } else if (trigger === 'rate') {
            if (rate > 0) {
                if (window.auraLastExchangeEdited === 'local' && local > 0) {
                    usd = Number((local / rate).toFixed(2));
                    $('#aura-exchange-amount-usd').val(usd.toFixed(2));
                } else if (usd > 0) {
                    local = Number((usd * rate).toFixed(2));
                    $('#aura-exchange-amount-local-input').val(local.toFixed(2));
                }
            }
        }

        updateExchangeImpactCard();
    }

    function updateExchangeImpactCard() {
        const direction = $('input[name="exchange_direction"]:checked').val() || 'local_to_usd';
        const targetForeign = String($('#aura-exchange-currency-select').val() || 'USD').toUpperCase();
        const meta = getForeignCurrencyMeta(targetForeign);
        const sourceId = parseInt($('#aura-exchange-source').val(), 10);
        const targetId = parseInt($('#aura-exchange-target').val(), 10);
        const accounts = window.auraAccountsCache || [];

        const srcAcc = accounts.find(a => parseInt(a.id, 10) === sourceId);
        const tgtAcc = accounts.find(a => parseInt(a.id, 10) === targetId);

        const foreignAmt = parseMoney($('#aura-exchange-amount-usd').val());
        const rate = parseMoney($('#aura-exchange-rate').val());
        let local = parseMoney($('#aura-exchange-amount-local-input').val());

        if (local <= 0 && foreignAmt > 0 && rate > 0) {
            local = Number((foreignAmt * rate).toFixed(2));
        }

        // Moneda local según la cuenta local involucrada
        const localCurr = (direction === 'local_to_usd') 
            ? (srcAcc ? srcAcc.currency : 'Local') 
            : (tgtAcc ? tgtAcc.currency : 'Local');

        $('#aura-exchange-local-addon-curr').text(localCurr);

        // Hints bajo los selects
        if (srcAcc) {
            $('#aura-exchange-source-balance-hint').html('Disponible: <strong>' + formatNumber(srcAcc.current_balance || 0) + ' ' + escapeHtml(srcAcc.currency) + '</strong>');
        } else {
            $('#aura-exchange-source-balance-hint').text('');
        }

        if (tgtAcc) {
            $('#aura-exchange-target-balance-hint').html('Saldo actual: <strong>' + formatNumber(tgtAcc.current_balance || 0) + ' ' + escapeHtml(tgtAcc.currency) + '</strong>');
        } else {
            $('#aura-exchange-target-balance-hint').text('');
        }

        let hasInsufficientBalance = false;
        let warningMsg = '';

        if (direction === 'local_to_usd') {
            // Se debita moneda local en origen, se acredita divisa extranjera en destino
            const debitAmt = local;
            const creditAmt = foreignAmt;

            if (srcAcc) {
                const oldBal = parseFloat(srcAcc.current_balance || 0);
                const newBal = oldBal - debitAmt;
                $('#aura-exchange-impact-source-text').html('<span style="color:#ef4444;">- ' + formatNumber(debitAmt) + ' ' + escapeHtml(srcAcc.currency) + '</span>');
                $('#aura-exchange-impact-source-sub').html(escapeHtml(srcAcc.name) + '<br>Saldo: ' + formatNumber(oldBal) + ' ➔ <strong>' + formatNumber(newBal) + ' ' + escapeHtml(srcAcc.currency) + '</strong>');

                if (debitAmt > 0 && oldBal < debitAmt) {
                    hasInsufficientBalance = true;
                    warningMsg = 'Saldo insuficiente en ' + escapeHtml(srcAcc.name) + '. Saldo disponible: ' + formatNumber(oldBal) + ' ' + escapeHtml(srcAcc.currency) + ', requerido: ' + formatNumber(debitAmt) + ' ' + escapeHtml(srcAcc.currency);
                }
            } else {
                $('#aura-exchange-impact-source-text').text('—');
                $('#aura-exchange-impact-source-sub').text('Selecciona cuenta local de origen');
            }

            if (tgtAcc) {
                const oldBal = parseFloat(tgtAcc.current_balance || 0);
                const newBal = oldBal + creditAmt;
                $('#aura-exchange-impact-target-text').html('<span style="color:#10b981;">+ ' + meta.symbol + ' ' + formatNumber(creditAmt) + ' ' + meta.code + '</span>');
                $('#aura-exchange-impact-target-sub').html(escapeHtml(tgtAcc.name) + '<br>Saldo: ' + formatNumber(oldBal) + ' ➔ <strong>' + formatNumber(newBal) + ' ' + meta.code + '</strong>');
            } else {
                $('#aura-exchange-impact-target-text').text('—');
                $('#aura-exchange-impact-target-sub').text('Selecciona cuenta ' + meta.code + ' de destino');
            }
        } else {
            // Se debita divisa extranjera en origen, se acredita moneda local en destino
            const debitAmt = foreignAmt;
            const creditAmt = local;

            if (srcAcc) {
                const oldBal = parseFloat(srcAcc.current_balance || 0);
                const newBal = oldBal - debitAmt;
                $('#aura-exchange-impact-source-text').html('<span style="color:#ef4444;">- ' + meta.symbol + ' ' + formatNumber(debitAmt) + ' ' + meta.code + '</span>');
                $('#aura-exchange-impact-source-sub').html(escapeHtml(srcAcc.name) + '<br>Saldo: ' + formatNumber(oldBal) + ' ➔ <strong>' + formatNumber(newBal) + ' ' + meta.code + '</strong>');

                if (debitAmt > 0 && oldBal < debitAmt) {
                    hasInsufficientBalance = true;
                    warningMsg = 'Saldo insuficiente en ' + escapeHtml(srcAcc.name) + '. Saldo disponible: ' + meta.symbol + ' ' + formatNumber(oldBal) + ' ' + meta.code + ', requerido: ' + meta.symbol + ' ' + formatNumber(debitAmt) + ' ' + meta.code;
                }
            } else {
                $('#aura-exchange-impact-source-text').text('—');
                $('#aura-exchange-impact-source-sub').text('Selecciona cuenta ' + meta.code + ' de origen');
            }

            if (tgtAcc) {
                const oldBal = parseFloat(tgtAcc.current_balance || 0);
                const newBal = oldBal + creditAmt;
                $('#aura-exchange-impact-target-text').html('<span style="color:#10b981;">+ ' + formatNumber(creditAmt) + ' ' + escapeHtml(tgtAcc.currency) + '</span>');
                $('#aura-exchange-impact-target-sub').html(escapeHtml(tgtAcc.name) + '<br>Saldo: ' + formatNumber(oldBal) + ' ➔ <strong>' + formatNumber(newBal) + ' ' + escapeHtml(tgtAcc.currency) + '</strong>');
            } else {
                $('#aura-exchange-impact-target-text').text('—');
                $('#aura-exchange-impact-target-sub').text('Selecciona cuenta local de destino');
            }
        }

        if (hasInsufficientBalance) {
            $('#aura-exchange-balance-warning').text('⚠️ ' + warningMsg).show();
            $('#aura-exchange-save-btn').prop('disabled', true);
        } else {
            $('#aura-exchange-balance-warning').hide();
            $('#aura-exchange-save-btn').prop('disabled', false);
        }
    }

    $(document).on('change', 'input[name="exchange_direction"]', function () {
        const dir = $(this).val();
        $('.aura-exchange-dir-card').removeClass('is-active');
        $(this).closest('.aura-exchange-dir-card').addClass('is-active');
        const foreignCurr = $('#aura-exchange-currency-select').val() || 'USD';
        populateExchangeAccounts(dir, foreignCurr);
        updateExchangeCalculation(window.auraLastExchangeEdited || 'usd');
    });

    $(document).on('change', '#aura-exchange-currency-select', function () {
        const dir = $('input[name="exchange_direction"]:checked').val() || 'local_to_usd';
        populateExchangeAccounts(dir, $(this).val());
        updateExchangeCalculation(window.auraLastExchangeEdited || 'usd');
    });

    $('#aura-exchange-source, #aura-exchange-target').on('change', function () {
        updateExchangeImpactCard();
    });

    $('#aura-exchange-open-btn, #aura-tab-exchange-new-btn').on('click', function () {
        openExchangeModal();
    });

    $('#aura-exchange-amount-usd').on('input change', function () {
        updateExchangeCalculation('usd');
    });

    $('#aura-exchange-amount-local-input').on('input change', function () {
        updateExchangeCalculation('local');
    });

    $('#aura-exchange-rate').on('input change', function () {
        updateExchangeCalculation('rate');
    });

    $('#aura-exchange-form').on('submit', function (e) {
        e.preventDefault();
        const usd = parseMoney($('#aura-exchange-amount-usd').val());
        const rate = parseMoney($('#aura-exchange-rate').val());
        const sourceId = parseInt($('#aura-exchange-source').val(), 10);
        const targetId = parseInt($('#aura-exchange-target').val(), 10);
        const direction = $('input[name="exchange_direction"]:checked').val() || 'local_to_usd';
        
        if (!sourceId || !targetId || usd <= 0 || rate <= 0) {
            showFeedback('Revisa los campos del cambio de divisa (cuentas de origen y destino, monto y tasa).', false);
            return;
        }

        $('#aura-exchange-save-btn').prop('disabled', true).text('Procesando operación...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_exchange_currency',
            nonce: auraFinancialAccounts.nonce,
            source_account_id: sourceId,
            target_account_id: targetId,
            amount_usd: usd,
            exchange_rate: rate,
            direction: direction,
            notes: $('#aura-exchange-notes').val()
        }).done(function (res) {
            if (res && res.success) {
                showFeedback(res.data.message || 'Cambio de divisa completado exitosamente.', true);
                window.AuraUI.closeModal('aura-finance-exchange-modal');
                loadAccounts();
                loadReports();
                loadExchangeHistory();
            } else {
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            }
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-exchange-save-btn').prop('disabled', false).text('Confirmar Cambio');
        });
    });

    // --- Exchange History & Audit Logic ---
    function loadExchangeHistory() {
        const $tbody = $('#aura-exchanges-tbody');
        $tbody.html('<tr><td colspan="9" style="text-align:center;padding:24px;color:var(--aura-text-muted,#888);"><span class="dashicons dashicons-update aura-spin" style="vertical-align:middle;margin-right:6px;"></span>Cargando historial de cambios de divisa...</td></tr>');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_exchange_history',
            nonce: auraFinancialAccounts.nonce
        }).done(function (res) {
            if (res && res.success && res.data) {
                const kpis = res.data.kpis || {};
                $('#aura-kpi-exchange-total').text(kpis.total_operations || 0);
                $('#aura-kpi-exchange-bought').text(kpis.bought_summary || ('$ ' + formatNumber(kpis.total_usd_bought || 0)));
                $('#aura-kpi-exchange-sold').text(kpis.sold_summary || ('$ ' + formatNumber(kpis.total_usd_sold || 0)));
                $('#aura-kpi-exchange-avg-rate').text(formatNumber(kpis.avg_exchange_rate || 0));

                window.auraExchangeHistoryCache = res.data.records || [];
                applyExchangeFilters();
            } else {
                $tbody.html('<tr><td colspan="9" style="text-align:center;padding:20px;color:#dc2626;">Error al cargar el historial de divisas.</td></tr>');
            }
        }).fail(function () {
            $tbody.html('<tr><td colspan="9" style="text-align:center;padding:20px;color:#dc2626;">Error de comunicación con el servidor.</td></tr>');
        });
    }

    function renderExchangeTable(records) {
        const $tbody = $('#aura-exchanges-tbody');
        $tbody.empty();

        if (!records || !records.length) {
            $tbody.html('<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--aura-text-muted,#888);">' +
                '<span class="dashicons dashicons-money-alt" style="font-size:32px;width:32px;height:32px;color:#94a3b8;display:block;margin:0 auto 8px;"></span>' +
                'No hay operaciones de cambio de divisa que coincidan con la búsqueda.</td></tr>');
            return;
        }

        const canEdit = !!(auraFinancialAccounts.canExchangeEdit);
        const canDelete = !!(auraFinancialAccounts.canExchangeDelete);

        records.forEach(function (rec) {
            const foreignCurr = String(rec.foreign_currency || 'USD').toUpperCase();
            const foreignMeta = getForeignCurrencyMeta(foreignCurr);
            const isBuy = rec.direction === 'local_to_usd' || rec.direction.indexOf('to_' + foreignCurr.toLowerCase()) !== -1;
            const badgeClass = isBuy ? 'badge-emerald' : 'badge-indigo';
            const badgeIcon = isBuy ? 'dashicons-cart' : 'dashicons-money-alt';
            const badgeLabel = isBuy ? ('Compra (Local ➔ ' + foreignCurr + ')') : ('Venta (' + foreignCurr + ' ➔ Local)');
            const isReverted = rec.status === 'reverted';
            
            const foreignAmt = parseFloat(rec.amount_foreign || rec.amount_usd || 0);
            const sourceAmountHtml = isBuy
                ? '<span style="color:#ef4444;font-weight:700;">- ' + formatNumber(rec.amount_local) + ' ' + escapeHtml(rec.source_currency) + '</span>'
                : '<span style="color:#ef4444;font-weight:700;">- ' + foreignMeta.symbol + ' ' + formatNumber(foreignAmt) + ' ' + foreignCurr + '</span>';

            const targetAmountHtml = isBuy
                ? '<span style="color:#10b981;font-weight:700;">+ ' + foreignMeta.symbol + ' ' + formatNumber(foreignAmt) + ' ' + foreignCurr + '</span>'
                : '<span style="color:#10b981;font-weight:700;">+ ' + formatNumber(rec.amount_local) + ' ' + escapeHtml(rec.target_currency) + '</span>';

            const rateHtml = '1 ' + foreignCurr + ' = ' + formatNumber(rec.exchange_rate) + ' ' + escapeHtml(isBuy ? rec.source_currency : rec.target_currency);

            // Estado Badge con Semáforo Canónico
            let statusBadgeHtml = '<span class="status-traffic-light" data-tooltip="Operación contable confirmada y conciliada">' +
                '<span class="traffic-dot traffic-dot-success"></span>' +
                '<span class="badge badge-emerald"><span class="dashicons dashicons-yes-alt" style="font-size:12px;width:12px;height:12px;margin-right:3px;vertical-align:middle;"></span>Completada</span>' +
            '</span>';
            if (isReverted) {
                const revertTooltip = 'Revertida por ' + (rec.reverted_by_name || 'Auditor') + (rec.reverted_at ? ' el ' + rec.reverted_at : '') + (rec.revert_reason ? ': ' + rec.revert_reason : '');
                statusBadgeHtml = '<span class="status-traffic-light" data-tooltip="' + escapeHtml(revertTooltip) + '">' +
                    '<span class="traffic-dot traffic-dot-danger"></span>' +
                    '<span class="badge badge-rose"><span class="dashicons dashicons-undo" style="font-size:12px;width:12px;height:12px;margin-right:3px;vertical-align:middle;"></span>Revertida</span>' +
                '</span>';
            }

            // Acciones con botones WOW del Design System
            let actionsHtml = '<div style="display:inline-flex;gap:6px;justify-content:flex-end;align-items:center;">';
            // 1. Ver Detalle (siempre disponible)
            actionsHtml += '<button type="button" class="btn btn-sm btn-secondary btn-lift aura-exchange-action-view" data-id="' + rec.id + '" data-tooltip="Ver detalle y auditoría contable" aria-label="Ver detalle">' +
                '<span class="dashicons dashicons-visibility" style="vertical-align:middle;font-size:14px;width:14px;height:14px;"></span>' +
            '</button>';

            // 2. Editar Justificación o Datos
            if (canEdit && !isReverted) {
                actionsHtml += '<button type="button" class="btn btn-sm btn-secondary btn-lift aura-exchange-action-edit" data-id="' + rec.id + '" data-tooltip="Editar justificación o notas" aria-label="Editar">' +
                    '<span class="dashicons dashicons-edit" style="vertical-align:middle;font-size:14px;width:14px;height:14px;color:#0284c7;"></span>' +
                '</button>';
            }

            // 3. Revertir Operación
            if (canDelete && !isReverted) {
                actionsHtml += '<button type="button" class="btn btn-sm btn-secondary btn-lift aura-exchange-action-revert" data-id="' + rec.id + '" data-tooltip="Anular y revertir saldos contables" aria-label="Revertir" style="color:#ef4444;border-color:rgba(239,68,68,0.3);">' +
                    '<span class="dashicons dashicons-undo" style="vertical-align:middle;font-size:14px;width:14px;height:14px;"></span>' +
                '</button>';
            }

            // 4. Eliminar Registro definitivo si ya está revertido
            if (canDelete && isReverted) {
                actionsHtml += '<button type="button" class="btn btn-sm btn-danger btn-lift aura-exchange-action-delete" data-id="' + rec.id + '" data-tooltip="Eliminar definitivamente este registro" aria-label="Eliminar">' +
                    '<span class="dashicons dashicons-trash" style="vertical-align:middle;font-size:14px;width:14px;height:14px;"></span>' +
                '</button>';
            }

            actionsHtml += '</div>';

            const opTip = '<div class="aura-tip-card" style="min-width:240px;padding:10px 12px;">' +
                '<div style="font-weight:700;font-size:12px;color:#ffffff;margin-bottom:4px;display:flex;align-items:center;gap:6px;"><span class="dashicons ' + badgeIcon + '" style="color:#38bdf8;"></span>' + escapeHtml(badgeLabel) + '</div>' +
                '<div style="font-size:11.5px;color:#cbd5e1;line-height:1.4;">Conversión ejecutada entre <strong>' + escapeHtml(rec.source_name || 'Origen') + '</strong> y <strong>' + escapeHtml(rec.target_name || 'Destino') + '</strong></div>' +
                '<div style="font-size:10.5px;color:#94a3b8;margin-top:4px;border-top:1px solid #334155;padding-top:4px;">ID Operación: #' + rec.id + ' • ' + escapeHtml(rec.created_at || '') + '</div>' +
            '</div>';

            const rateTip = '<div class="aura-tip-card" style="min-width:220px;padding:10px 12px;">' +
                '<div style="font-weight:700;font-size:12px;color:#ffffff;margin-bottom:4px;display:flex;align-items:center;gap:6px;"><span class="dashicons dashicons-chart-line" style="color:#34d399;"></span>Tasa de Conversión</div>' +
                '<div style="font-size:13px;font-weight:800;color:#38bdf8;margin:2px 0;">' + rateHtml + '</div>' +
                '<div style="font-size:10.5px;color:#94a3b8;margin-top:4px;border-top:1px solid #334155;padding-top:4px;">Tasa pactada e inmutable de la operación</div>' +
            '</div>';

            const userTip = '<div class="aura-tip-card" style="min-width:220px;padding:10px 12px;">' +
                '<div style="font-weight:700;font-size:12px;color:#ffffff;margin-bottom:4px;display:flex;align-items:center;gap:6px;"><span class="dashicons dashicons-admin-users" style="color:#a855f7;"></span>Auditor Responsable</div>' +
                '<div style="font-size:12px;font-weight:700;color:#e2e8f0;">' + escapeHtml(rec.user_name || 'Sistema') + '</div>' +
                '<div style="font-size:10.5px;color:#94a3b8;margin-top:4px;border-top:1px solid #334155;padding-top:4px;">Registrado el: ' + escapeHtml(rec.created_at || '—') + '</div>' +
            '</div>';

            const rowClass = 'table-row-hover-lift' + (isReverted ? ' aura-row-reverted' : '');
            const rowStyle = isReverted ? ' style="opacity:0.75;"' : '';

            const rowHtml = '<tr class="' + rowClass + '"' + rowStyle + '>' +
                '<td style="white-space:nowrap;font-size:12px;color:var(--aura-text-muted,#64748b);font-variant-numeric:tabular-nums;">' +
                    '<span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;margin-right:4px;vertical-align:middle;color:#94a3b8;"></span>' +
                    escapeHtml(rec.created_at || '—') +
                '</td>' +
                '<td>' +
                    '<span class="badge ' + badgeClass + ' aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(opTip) + '" style="font-size:11.5px;padding:3px 8px;border-radius:6px;font-weight:600;cursor:pointer;">' +
                        '<span class="dashicons ' + badgeIcon + '" style="font-size:13px;width:13px;height:13px;margin-right:4px;vertical-align:middle;"></span>' +
                        escapeHtml(badgeLabel) +
                    '</span>' +
                '</td>' +
                '<td>' +
                    '<strong>' + escapeHtml(rec.source_name || '—') + '</strong>' +
                    '<div style="font-size:12px;margin-top:2px;">' + sourceAmountHtml + '</div>' +
                '</td>' +
                '<td style="white-space:nowrap;font-weight:600;font-size:12.5px;color:var(--aura-text-heading,#0f172a);">' +
                    '<span class="badge badge-slate aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(rateTip) + '" style="font-size:11.5px;font-weight:600;cursor:pointer;"><span class="dashicons dashicons-randomize" style="font-size:12px;width:12px;height:12px;margin-right:3px;vertical-align:middle;"></span>' + rateHtml + '</span>' +
                '</td>' +
                '<td>' +
                    '<strong>' + escapeHtml(rec.target_name || '—') + '</strong>' +
                    '<div style="font-size:12px;margin-top:2px;">' + targetAmountHtml + '</div>' +
                '</td>' +
                '<td style="font-size:12px;">' +
                    '<div class="aura-tooltip-trigger" data-aura-tooltip="' + encodeURIComponent(userTip) + '" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;"><span class="dashicons dashicons-admin-users" style="vertical-align:middle;font-size:15px;width:15px;height:15px;color:var(--aura-text-muted,#64748b);"></span> <span style="font-weight:500;">' +
                    escapeHtml(rec.user_name || 'Sistema') + '</span></div>' +
                '</td>' +
                '<td style="font-size:12px;color:var(--aura-text-muted,#64748b);max-width:180px;word-break:break-word;">' +
                    (rec.notes ? '<span data-tooltip="' + escapeHtml(rec.notes) + '" style="cursor:help;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + escapeHtml(rec.notes) + '</span>' : '<span style="color:var(--aura-text-muted,#94a3b8);">—</span>') +
                '</td>' +
                '<td style="white-space:nowrap;">' +
                    statusBadgeHtml +
                '</td>' +
                '<td style="text-align:right;white-space:nowrap;">' +
                    actionsHtml +
                '</td>' +
            '</tr>';

            $tbody.append(rowHtml);
        });
    }

    function applyExchangeFilters() {
        const query = ($('#aura-exchanges-search').val() || '').toLowerCase().trim();
        const dirFilter = $('#aura-filter-exchange-dir').val() || '';
        const currFilter = $('#aura-filter-exchange-currency').val() || '';
        const statusFilter = $('#aura-filter-exchange-status').val() || '';
        const list = window.auraExchangeHistoryCache || [];

        const filtered = list.filter(function (rec) {
            if (dirFilter && rec.direction !== dirFilter) {
                return false;
            }
            if (currFilter && String(rec.foreign_currency || 'USD').toUpperCase() !== currFilter) {
                return false;
            }
            if (statusFilter && rec.status !== statusFilter) {
                return false;
            }
            if (query) {
                const str = (
                    (rec.source_name || '') + ' ' +
                    (rec.target_name || '') + ' ' +
                    (rec.user_name || '') + ' ' +
                    (rec.notes || '') + ' ' +
                    (rec.foreign_currency || '') + ' ' +
                    (rec.revert_reason || '') + ' ' +
                    (rec.created_at || '')
                ).toLowerCase();
                return str.indexOf(query) !== -1;
            }
            return true;
        });

        renderExchangeTable(filtered);
    }

    $('#aura-exchanges-search').on('input keyup', function () {
        applyExchangeFilters();
    });

    $('#aura-filter-exchange-dir, #aura-filter-exchange-status, #aura-filter-exchange-currency').on('change', function () {
        applyExchangeFilters();
    });

    $('#aura-exchanges-refresh-btn').on('click', function () {
        loadExchangeHistory();
    });

    // --- CRUD Event Handlers: Ver Detalle ---
    $(document).on('click', '.aura-exchange-action-view', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraExchangeHistoryCache || [];
        const cached = list.find(r => parseInt(r.id, 10) === id);

        const $content = $('#aura-exchange-detail-content');
        $content.html('<div style="text-align:center;padding:24px;color:var(--aura-text-muted,#888);"><span class="dashicons dashicons-update aura-spin" style="vertical-align:middle;margin-right:6px;"></span>Cargando detalle...</div>');
        window.AuraUI.openModal('aura-finance-exchange-detail-modal');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_exchange_get',
            nonce: auraFinancialAccounts.nonce,
            id: id
        }).done(function (res) {
            const data = (res && res.success && res.data && res.data.exchange) ? res.data.exchange : cached;
            if (!data) {
                $content.html('<div style="text-align:center;padding:20px;color:#dc2626;">No se encontró la información de esta operación.</div>');
                return;
            }

            const foreignCurr = String(data.foreign_currency || 'USD').toUpperCase();
            const foreignMeta = getForeignCurrencyMeta(foreignCurr);
            const isBuy = data.direction === 'local_to_usd' || data.direction.indexOf('to_' + foreignCurr.toLowerCase()) !== -1;
            const opTitle = isBuy ? ('Compra de ' + foreignMeta.name + ' (Moneda Local ➔ ' + foreignCurr + ')') : ('Venta de ' + foreignMeta.name + ' (' + foreignCurr + ' ➔ Moneda Local)');
            const isReverted = data.status === 'reverted';
            const foreignAmt = parseFloat(data.amount_foreign || data.amount_usd || 0);

            let html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--aura-border-color,#e2e8f0);">' +
                '<div>' +
                    '<span class="aura-badge ' + (isBuy ? 'aura-badge-green' : 'aura-badge-indigo') + '" style="font-size:12px;padding:3px 10px;border-radius:6px;font-weight:700;">' +
                        escapeHtml(opTitle) +
                    '</span>' +
                '</div>' +
                '<div>' +
                    (isReverted 
                        ? '<span class="aura-badge aura-badge-red" style="font-size:12px;padding:3px 10px;border-radius:6px;font-weight:700;">Revertida</span>'
                        : '<span class="aura-badge aura-badge-green" style="font-size:12px;padding:3px 10px;border-radius:6px;font-weight:700;">Completada</span>') +
                '</div>' +
            '</div>';

            if (isReverted) {
                html += '<div style="margin-bottom:16px;padding:12px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-radius:8px;">' +
                    '<strong style="color:#dc2626;display:block;margin-bottom:4px;">⚠️ Operación Anulada / Revertida</strong>' +
                    '<div style="font-size:12.5px;color:var(--aura-text-main,#334155);line-height:1.5;">' +
                        '<strong>Fecha de reversión:</strong> ' + escapeHtml(data.reverted_at || '—') + '<br>' +
                        '<strong>Revertida por:</strong> ' + escapeHtml(data.reverted_by_name || 'Auditor') + '<br>' +
                        '<strong>Motivo de anulación:</strong> ' + escapeHtml(data.revert_reason || 'Sin justificación') +
                    '</div>' +
                '</div>';
            }

            const sourceDebitHtml = isBuy
                ? ('- ' + formatNumber(data.amount_local) + ' ' + escapeHtml(data.source_currency))
                : ('- ' + foreignMeta.symbol + ' ' + formatNumber(foreignAmt) + ' ' + foreignCurr);

            const targetCreditHtml = isBuy
                ? ('+ ' + foreignMeta.symbol + ' ' + formatNumber(foreignAmt) + ' ' + foreignCurr)
                : ('+ ' + formatNumber(data.amount_local) + ' ' + escapeHtml(data.target_currency));

            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">' +
                '<div class="aura-exchange-detail-card" style="border-radius:8px;padding:12px;">' +
                    '<small style="text-transform:uppercase;letter-spacing:0.5px;font-size:10.5px;color:var(--aura-text-muted,#64748b);font-weight:700;display:block;margin-bottom:4px;">Cuenta Origen (Salida de fondos)</small>' +
                    '<strong style="font-size:14px;color:var(--aura-text-heading,#0f172a);display:block;">' + escapeHtml(data.source_name || 'Cuenta Origen') + '</strong>' +
                    '<div style="margin-top:6px;font-size:12.5px;color:#dc2626;font-weight:700;">' + sourceDebitHtml + '</div>' +
                    '<div style="margin-top:4px;font-size:11.5px;color:var(--aura-text-muted,#64748b);">' +
                        'Saldo previo: ' + formatNumber(data.source_old_balance) + '<br>' +
                        'Saldo tras cambio: <strong>' + formatNumber(data.source_new_balance) + ' ' + escapeHtml(data.source_currency) + '</strong>' +
                    '</div>' +
                '</div>' +
                '<div class="aura-exchange-detail-card" style="border-radius:8px;padding:12px;">' +
                    '<small style="text-transform:uppercase;letter-spacing:0.5px;font-size:10.5px;color:var(--aura-text-muted,#64748b);font-weight:700;display:block;margin-bottom:4px;">Cuenta Destino (Entrada de fondos)</small>' +
                    '<strong style="font-size:14px;color:var(--aura-text-heading,#0f172a);display:block;">' + escapeHtml(data.target_name || 'Cuenta Destino') + '</strong>' +
                    '<div style="margin-top:6px;font-size:12.5px;color:#16a34a;font-weight:700;">' + targetCreditHtml + '</div>' +
                    '<div style="margin-top:4px;font-size:11.5px;color:var(--aura-text-muted,#64748b);">' +
                        'Saldo previo: ' + formatNumber(data.target_old_balance) + '<br>' +
                        'Saldo tras cambio: <strong>' + formatNumber(data.target_new_balance) + ' ' + escapeHtml(data.target_currency) + '</strong>' +
                    '</div>' +
                '</div>' +
            '</div>';

            html += '<div class="aura-exchange-detail-box" style="border-radius:8px;padding:12px;margin-bottom:16px;font-size:12.5px;line-height:1.6;">' +
                '<div><strong>Tasa de Cambio Aplicada:</strong> 1 ' + foreignCurr + ' = ' + formatNumber(data.exchange_rate) + ' ' + escapeHtml(isBuy ? data.source_currency : data.target_currency) + '</div>' +
                '<div><strong>Fecha y Hora de Registro:</strong> ' + escapeHtml(data.created_at || '—') + '</div>' +
                '<div><strong>Auditor / Registrado por:</strong> ' + escapeHtml(data.user_name || 'Sistema') + '</div>' +
                '<div><strong>ID de Operación:</strong> #' + data.id + '</div>' +
            '</div>';

            html += '<div>' +
                '<strong style="display:block;font-size:12.5px;margin-bottom:4px;color:var(--aura-text-heading,#0f172a);">Notas, Justificación o Referencia:</strong>' +
                '<div class="aura-exchange-detail-notes-box" style="border-radius:6px;padding:10px 12px;font-size:12.5px;line-height:1.5;">' +
                    escapeHtml(data.notes || 'Ninguna observación registrada.') +
                '</div>' +
            '</div>';

            $content.html(html);
        }).fail(function () {
            if (cached) {
                $content.html('<div style="padding:10px;font-size:13px;">Mostrando datos locales de caché: ID #' + cached.id + '</div>');
            } else {
                $content.html('<div style="text-align:center;padding:20px;color:#dc2626;">Error al cargar los datos desde el servidor.</div>');
            }
        });
    });

    // --- CRUD Event Handlers: Edición Completa de Operación ---
    function populateExchangeEditAccounts(direction, currentSourceId, currentTargetId, foreignCurrency) {
        const $source = $('#aura-exchange-edit-source');
        const $target = $('#aura-exchange-edit-target');
        const accounts = window.auraAccountsCache || [];
        const targetForeign = String(foreignCurrency || 'USD').toUpperCase();

        $source.empty();
        $target.empty();

        const isBuy = direction === 'local_to_usd' || direction.indexOf('to_' + targetForeign.toLowerCase()) !== -1;

        accounts.forEach(function (a) {
            const balance = formatNumber(a.current_balance || 0);
            const label = escapeHtml(a.name) + ' (' + balance + ' ' + escapeHtml(a.currency) + ')';
            const opt = '<option value="' + a.id + '" data-currency="' + escapeHtml(a.currency) + '">' + label + '</option>';
            const isForeign = String(a.currency).toUpperCase() === targetForeign;

            if (isBuy) {
                if (!isForeign) {
                    $source.append(opt);
                } else {
                    $target.append(opt);
                }
            } else {
                if (isForeign) {
                    $source.append(opt);
                } else {
                    $target.append(opt);
                }
            }
        });

        if (currentSourceId) {
            $source.val(currentSourceId);
        }
        if (currentTargetId) {
            $target.val(currentTargetId);
        }
    }

    function recalcExchangeEditLocal() {
        const rate = parseFloat($('#aura-exchange-edit-rate').val()) || 0;
        const foreignAmt = parseFloat($('#aura-exchange-edit-amount-usd').val()) || 0;
        if (rate > 0 && foreignAmt > 0) {
            $('#aura-exchange-edit-amount-local').val((foreignAmt * rate).toFixed(2));
        } else {
            $('#aura-exchange-edit-amount-local').val('');
        }
    }

    $('#aura-exchange-edit-rate, #aura-exchange-edit-amount-usd').on('input change', function () {
        recalcExchangeEditLocal();
    });

    $(document).on('click', '.aura-exchange-action-edit', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraExchangeHistoryCache || [];
        const rec = list.find(r => parseInt(r.id, 10) === id);

        if (!rec) {
            showFeedback('Operación no encontrada.', false);
            return;
        }

        const foreignCurr = String(rec.foreign_currency || 'USD').toUpperCase();
        const foreignMeta = getForeignCurrencyMeta(foreignCurr);
        const isReverted = rec.status === 'reverted';
        const isBuy = rec.direction === 'local_to_usd' || rec.direction.indexOf('to_' + foreignCurr.toLowerCase()) !== -1;

        $('#aura-exchange-edit-id').val(id);
        $('#aura-exchange-edit-direction').val(rec.direction);
        $('#aura-exchange-edit-direction-badge').text(isBuy ? ('Compra de ' + foreignMeta.name + ' (Local ➔ ' + foreignCurr + ')') : ('Venta de ' + foreignMeta.name + ' (' + foreignCurr + ' ➔ Local)'));
        $('#aura-exchange-edit-amount-foreign-label').text('Monto ' + foreignMeta.name + ' (' + foreignMeta.symbol + ')');

        populateExchangeEditAccounts(rec.direction, rec.source_id, rec.target_id, foreignCurr);

        $('#aura-exchange-edit-rate').val(parseFloat(rec.exchange_rate) || 1);
        $('#aura-exchange-edit-amount-usd').val(parseFloat(rec.amount_foreign || rec.amount_usd) || '');
        $('#aura-exchange-edit-amount-local').val(parseFloat(rec.amount_local) || '');
        $('#aura-exchange-edit-notes').val(rec.notes || '');

        if (isReverted) {
            $('#aura-exchange-edit-source, #aura-exchange-edit-target, #aura-exchange-edit-rate, #aura-exchange-edit-amount-usd').prop('disabled', true);
            $('#aura-exchange-edit-direction-badge').html('<span style="color:#dc2626;">Operación Anulada / Revertida</span> (Solo puedes actualizar la justificación)');
        } else {
            $('#aura-exchange-edit-source, #aura-exchange-edit-target, #aura-exchange-edit-rate, #aura-exchange-edit-amount-usd').prop('disabled', false);
        }

        window.AuraUI.openModal('aura-finance-exchange-edit-modal');
    });

    $('#aura-exchange-edit-form').on('submit', function (e) {
        e.preventDefault();
        const id = parseInt($('#aura-exchange-edit-id').val(), 10);
        const sourceId = parseInt($('#aura-exchange-edit-source').val(), 10);
        const targetId = parseInt($('#aura-exchange-edit-target').val(), 10);
        const rate = parseFloat($('#aura-exchange-edit-rate').val()) || 0;
        const foreignAmt = parseFloat($('#aura-exchange-edit-amount-usd').val()) || 0;
        const local = parseFloat($('#aura-exchange-edit-amount-local').val()) || 0;
        const notes = $('#aura-exchange-edit-notes').val();

        $('#aura-exchange-edit-save-btn').prop('disabled', true).text('Guardando...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_exchange_update',
            nonce: auraFinancialAccounts.nonce,
            id: id,
            source_account_id: sourceId,
            target_account_id: targetId,
            exchange_rate: rate,
            amount_usd: foreignAmt,
            amount_local: local,
            notes: notes
        }).done(function (res) {
            if (res && res.success) {
                showFeedback(res.data.message || 'Operación actualizada exitosamente.', true);
                window.AuraUI.closeModal('aura-finance-exchange-edit-modal');
                loadAccounts();
                loadReports();
                loadExchangeHistory();
            } else {
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            }
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-exchange-edit-save-btn').prop('disabled', false).text('Guardar y Rebalancear');
        });
    });

    // --- CRUD Event Handlers: Revertir Operación ---
    $(document).on('click', '.aura-exchange-action-revert', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraExchangeHistoryCache || [];
        const rec = list.find(r => parseInt(r.id, 10) === id);

        if (!rec) {
            showFeedback('Operación no encontrada.', false);
            return;
        }

        $('#aura-exchange-revert-id').val(id);
        $('#aura-exchange-revert-reason').val('');

        const foreignCurr = String(rec.foreign_currency || 'USD').toUpperCase();
        const foreignMeta = getForeignCurrencyMeta(foreignCurr);
        const isBuy = rec.direction === 'local_to_usd' || rec.direction.indexOf('to_' + foreignCurr.toLowerCase()) !== -1;
        const foreignAmt = parseFloat(rec.amount_foreign || rec.amount_usd || 0);

        const debitAmt = isBuy ? (formatNumber(rec.amount_local) + ' ' + escapeHtml(rec.source_currency)) : (foreignMeta.symbol + ' ' + formatNumber(foreignAmt) + ' ' + foreignCurr);
        const creditAmt = isBuy ? (foreignMeta.symbol + ' ' + formatNumber(foreignAmt) + ' ' + foreignCurr) : (formatNumber(rec.amount_local) + ' ' + escapeHtml(rec.target_currency));

        let summaryHtml = '<strong>Operación a anular:</strong> Cambio #' + rec.id + '<br>' +
            '• Se devolverán <strong>+' + debitAmt + '</strong> a <em>' + escapeHtml(rec.source_name) + '</em><br>' +
            '• Se retirarán <strong>-' + creditAmt + '</strong> de <em>' + escapeHtml(rec.target_name) + '</em>';

        $('#aura-exchange-revert-summary').html(summaryHtml);
        window.AuraUI.openModal('aura-finance-exchange-revert-modal');
    });

    $('#aura-exchange-revert-form').on('submit', function (e) {
        e.preventDefault();
        const id = parseInt($('#aura-exchange-revert-id').val(), 10);
        const reason = $('#aura-exchange-revert-reason').val();

        if (!reason || !reason.trim()) {
            showFeedback('Debes ingresar un motivo de reversión.', false);
            return;
        }

        $('#aura-exchange-revert-save-btn').prop('disabled', true).text('Revirtiendo...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_exchange_revert',
            nonce: auraFinancialAccounts.nonce,
            id: id,
            revert_reason: reason
        }).done(function (res) {
            if (res && res.success) {
                showFeedback(res.data.message || 'Operación revertida exitosamente.', true);
                window.AuraUI.closeModal('aura-finance-exchange-revert-modal');
                loadAccounts();
                loadReports();
                loadExchangeHistory();
            } else {
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            }
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-exchange-revert-save-btn').prop('disabled', false).text('Confirmar Reversión');
        });
    });

    // --- CRUD Event Handlers: Eliminar Registro ---
    $(document).on('click', '.aura-exchange-action-delete', function () {
        const id = parseInt($(this).data('id'), 10);
        const msg = (auraFinancialAccounts.i18n && auraFinancialAccounts.i18n.exchangeDeleteConfirm) || '¿Eliminar permanentemente este registro del historial?';

        if (!window.confirm(msg)) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_exchange_delete',
            nonce: auraFinancialAccounts.nonce,
            id: id
        }).done(function (res) {
            if (res && res.success) {
                showFeedback(res.data.message || 'Registro eliminado.', true);
                loadExchangeHistory();
            } else {
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
            }
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    });

    // Exportar Historial de Divisas a CSV
    $('#aura-exchanges-export-btn').on('click', function () {
        const list = window.auraExchangeHistoryCache || [];
        if (!list.length) {
            showFeedback('No hay datos para exportar en este momento.', false);
            return;
        }

        const headers = ['Fecha / Hora', 'Operación', 'Cuenta Origen', 'Monto Salida', 'Moneda Origen', 'Tasa Cambio', 'Cuenta Destino', 'Monto Entrada', 'Moneda Destino', 'Estado', 'Auditor / Usuario', 'Notas / Justificación'];
        const csvRows = [headers.join(',')];

        list.forEach(function (rec) {
            const isToUsd = rec.direction === 'local_to_usd';
            const opLabel = isToUsd ? 'Compra USD (Local a USD)' : 'Venta USD (USD a Local)';
            const amtOut = isToUsd ? rec.amount_local : rec.amount_usd;
            const currOut = isToUsd ? rec.source_currency : 'USD';
            const amtIn = isToUsd ? rec.amount_usd : rec.amount_local;
            const currIn = isToUsd ? 'USD' : rec.target_currency;

            const row = [
                '"' + (rec.created_at || '').replace(/"/g, '""') + '"',
                '"' + opLabel.replace(/"/g, '""') + '"',
                '"' + (rec.source_name || '').replace(/"/g, '""') + '"',
                amtOut,
                '"' + currOut + '"',
                rec.exchange_rate,
                '"' + (rec.target_name || '').replace(/"/g, '""') + '"',
                amtIn,
                '"' + currIn + '"',
                '"' + (rec.status === 'reverted' ? 'Revertida' : 'Completada') + '"',
                '"' + (rec.user_name || '').replace(/"/g, '""') + '"',
                '"' + (rec.notes || '').replace(/"/g, '""') + '"'
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = '\uFEFF' + csvRows.join('\r\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const now = new Date().toISOString().slice(0, 10);
        link.href = URL.createObjectURL(blob);
        link.download = 'auditoria-cambio-divisas-' + now + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });


    // --- Inline Budget Editing ---
    $(document).on('click', '#aura-inline-budget-edit-btn', function(e) {
        e.preventDefault();
        $(this).hide();
        $('#aura-inline-budget-save-btn').show();
        if (window.auraLastReportData) {
            renderReports(window.auraLastReportData);
        }
    });

    $(document).on('click', '#aura-inline-budget-save-btn', function(e) {
        e.preventDefault();
        const year = parseInt($('#aura-report-year').val(), 10) || new Date().getFullYear();
        const annualLimit = parseFloat($('#aura-inline-budget-annual').val() || 0);
        const policy = window.auraBudgetReportCache ? window.auraBudgetReportCache.policy : 'warn';
        
        const months = {};
        $('.aura-inline-budget-month').each(function() {
            const m = $(this).data('month');
            months[m] = parseFloat($(this).val() || 0);
        });

        const $btn = $(this);
        $btn.prop('disabled', true).text('Guardando...');

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_budget_save',
            nonce: auraFinancialAccounts.nonce,
            year: year,
            annual_limit: annualLimit,
            exceed_policy: policy,
            months: months
        }).done(function(res) {
            if (res && res.success) {
                showFeedback('Presupuesto actualizado.', true);
                $('#aura-inline-budget-save-btn').hide();
                $('#aura-inline-budget-edit-btn').show();
                loadReports(); // reload to get new calculations
            } else {
                showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
                $btn.prop('disabled', false).text('Guardar Cambios');
            }
        }).fail(function() {
            showFeedback(auraFinancialAccounts.i18n.error, false);
            $btn.prop('disabled', false).text('Guardar Cambios');
        });
    });

    // ── GESTIÓN Y MODOS DE CONFIGURACIÓN DE PRESUPUESTOS ──
    function setBudgetConfigMode(mode) {
        $('input[name="budget_config_mode"][value="' + mode + '"]').prop('checked', true);

        if (mode === 'even') {
            $('#aura-budget-mode-card-even').css({
                'border-color': '#2563eb',
                'background': '#eff6ff'
            }).find('strong').css('color', '#1e3a8a');

            $('#aura-budget-mode-card-manual').css({
                'border-color': '#e2e8f0',
                'background': '#f8fafc'
            }).find('strong').css('color', '#334155');

            $('#aura-budget-even-tools').removeClass('aura-hidden').show();
            $('#aura-budget-manual-tools').addClass('aura-hidden').hide();
            $('#aura-budget-monthly-hint').text('El sistema divide automáticamente el tope anual entre los 12 meses.');

            const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
            if (annualLimit > 0) {
                distributeEvenly(annualLimit);
            }
        } else {
            $('#aura-budget-mode-card-manual').css({
                'border-color': '#2563eb',
                'background': '#eff6ff'
            }).find('strong').css('color', '#1e3a8a');

            $('#aura-budget-mode-card-even').css({
                'border-color': '#e2e8f0',
                'background': '#f8fafc'
            }).find('strong').css('color', '#334155');

            $('#aura-budget-manual-tools').removeClass('aura-hidden').show();
            $('#aura-budget-even-tools').addClass('aura-hidden').hide();
            $('#aura-budget-monthly-hint').text('Modifica cada mes a tu gusto; la suma se sincroniza automáticamente con el total anual.');
        }
    }

    $('input[name="budget_config_mode"]').on('change', function() {
        setBudgetConfigMode($(this).val());
    });

    $('#aura-budget-annual-limit').on('input change', function() {
        const mode = $('input[name="budget_config_mode"]:checked').val() || 'even';
        if (mode === 'even') {
            distributeEvenly($(this).val());
        } else {
            updateBudgetOverview();
        }
    });

    $('.aura-budget-month-input').on('input change', function() {
        const mode = $('input[name="budget_config_mode"]:checked').val() || 'even';
        if (mode === 'manual') {
            let sum = 0;
            $('.aura-budget-month-input').each(function() {
                sum += parseMoney($(this).val());
            });
            $('#aura-budget-annual-limit').val(sum.toFixed(2));
        }
        updateBudgetOverview();
    });

    $('#aura-budget-policy').on('change', function() {
        updateBudgetOverview();
    });

    $('#aura-budget-apply-even-btn').on('click', function(e) {
        e.preventDefault();
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
        if (annualLimit <= 0) {
            showFeedback('Ingresa un presupuesto anual total mayor a $0.', false);
            return;
        }
        distributeEvenly(annualLimit);
        showFeedback('Tope anual dividido en los 12 meses.', true);
    });

    $('#aura-budget-sum-to-annual-btn').on('click', function(e) {
        e.preventDefault();
        let sum = 0;
        $('.aura-budget-month-input').each(function() {
            sum += parseMoney($(this).val());
        });
        $('#aura-budget-annual-limit').val(sum.toFixed(2));
        updateBudgetOverview();
        showFeedback('Tope anual actualizado con la suma de los 12 meses ($' + formatNumber(sum) + ').', true);
    });

    $('#aura-budget-clear-all-btn').on('click', function(e) {
        e.preventDefault();
        $('.aura-budget-month-input').val(0);
        $('#aura-budget-annual-limit').val(0);
        updateBudgetMonthlyTotal();
        updateBudgetOverview();
        showFeedback('Presupuestos en cero.', true);
    });

    $('#aura-global-budget-year').on('change', function() {
        const yr = $(this).val();
        $('#aura-budget-year').val(yr);
        loadBudgetByYear(yr);
    });

    $('#aura-budget-year').on('change', function() {
        const yr = $(this).val();
        $('#aura-global-budget-year').val(yr);
        loadBudgetByYear(yr);
    });

    // Botón Configurar / Editar Año
    $('#aura-budget-open-inline-btn').on('click', function(e) {
        e.preventDefault();
        const yr = parseInt($('#aura-global-budget-year').val(), 10) || new Date().getFullYear();
        $('#aura-budget-year').val(yr);
        setBudgetConfigMode('even');
        window.AuraUI.openModal('aura-finance-budget-modal');
        loadBudgetByYear(yr);
    });

    // Botón Nuevo Presupuesto Anual
    $('#aura-budget-new-year-btn').on('click', function(e) {
        e.preventDefault();
        const currentYear = (new Date()).getFullYear();
        const nextYear = currentYear + 1;
        $('#aura-budget-year').val(nextYear);
        $('#aura-budget-annual-limit').val(0);
        $('#aura-budget-policy').val('warn');
        $('.aura-budget-month-input').val(0);
        setBudgetConfigMode('even');
        updateBudgetOverview();
        window.AuraUI.openModal('aura-finance-budget-modal');
    });

    // Botón Importar Plantilla desde la barra de herramientas
    $('#aura-budget-open-import-btn').on('click', function(e) {
        e.preventDefault();
        window.AuraUI.openModal('aura-finance-budget-modal');
        $('#aura-budget-import-details-accordion').prop('open', true);
        setTimeout(function() {
            $('#aura-budget-import-file').trigger('click');
        }, 150);
    });

    // Botón Eliminar Presupuesto del Año
    $('#aura-budget-delete-year-btn').on('click', function(e) {
        e.preventDefault();
        const yr = parseInt($('#aura-global-budget-year').val(), 10);
        if (yr) {
            deleteBudgetYear(yr);
        }
    });

    // Botón Distribuir Tope Parejo (÷12) desde la vista principal
    $('#aura-budget-quick-distribute-btn').on('click', function(e) {
        e.preventDefault();
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
        if (annualLimit <= 0) {
            showFeedback('Primero debes ingresar un tope anual mayor a $0.', false);
            $('#aura-budget-open-inline-btn').trigger('click');
            return;
        }

        setBudgetConfigMode('even');
        distributeEvenly(annualLimit);
        window.AuraUI.openModal('aura-finance-budget-modal');
        showFeedback('Tope anual dividido en 12 meses. Puedes guardar cuando desees.', true);
    });

    $('#aura-budget-load-btn').on('click', function(e) {
        e.preventDefault();
        loadBudgetByYear($('#aura-budget-year').val());
    });

    $('#aura-budget-form').on('submit', function(e) {
        e.preventDefault();
        saveBudget();
    });

    // Acciones en la tabla de 12 meses
    $(document).on('click', '.aura-budget-quick-edit-month', function(e) {
        e.preventDefault();
        const monthNum = $(this).data('month');
        setBudgetConfigMode('manual');
        window.AuraUI.openModal('aura-finance-budget-modal');
        setTimeout(function() {
            const $input = $('#aura-budget-month-' + monthNum);
            if ($input.length) {
                $input.focus().select();
            }
        }, 150);
    });

    // Acciones en la tabla de Historial de Años
    $(document).on('click', '.aura-budget-year-select-btn', function(e) {
        e.preventDefault();
        const yr = $(this).data('year');
        loadBudgetByYear(yr);
        showFeedback('Presupuesto de ' + yr + ' cargado en pantalla.', true);
    });

    $(document).on('click', '.aura-budget-year-clone-btn', function(e) {
        e.preventDefault();
        const fromYear = $(this).data('year');
        const defaultToYear = fromYear + 1;
        const toYearStr = prompt('Ingresa el año destino para clonar el presupuesto de ' + fromYear + ':', String(defaultToYear));
        if (toYearStr) {
            const toYear = parseInt(toYearStr, 10);
            if (toYear >= 2000 && toYear <= 2100 && toYear !== fromYear) {
                copyBudgetYear(fromYear, toYear);
            } else {
                showFeedback('Año destino no válido.', false);
            }
        }
    });

    $(document).on('click', '.aura-budget-year-delete-btn', function(e) {
        e.preventDefault();
        const yr = $(this).data('year');
        if (yr) {
            deleteBudgetYear(yr);
        }
    });

    // Exportar cuentas bancarias a CSV
    $(document).on('click', '#aura-accounts-export-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite;vertical-align:middle;"></span> ' + (auraFinancialAccounts.i18n.saving || 'Exportando...'));

        $.ajax({
            url: auraFinancialAccounts.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aura_export_accounts',
                nonce: auraFinancialAccounts.nonce
            }
        }).done(function(res) {
            $btn.prop('disabled', false).html(originalHtml);
            if (res.success && res.data && res.data.content) {
                const byteCharacters = atob(res.data.content);
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                const blob = new Blob([byteArray], { type: res.data.mime || 'text/csv;charset=utf-8' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = res.data.filename || 'cuentas-bancarias.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showFeedback('Cuentas bancarias exportadas correctamente.', true);
            } else {
                showFeedback((res.data && res.data.message) ? res.data.message : 'Error al exportar cuentas.', false);
            }
        }).fail(function() {
            $btn.prop('disabled', false).html(originalHtml);
            showFeedback('Error de conexión al exportar.', false);
        });
    });

    if (window.AuraUI && typeof window.AuraUI.initTabs === 'function') {
        window.AuraUI.initTabs();
    }

    // Inicializar selector y autocompletado de Terceros para Caja Chica y Reembolsos
    if (window.AuraThirdPartySelector) {
        window.AuraThirdPartySelector.attach({
            input: '#aura-petty-responsible-name',
            hiddenId: '#aura-petty-responsible',
            formatId: 'prefixed',
            previewContainer: '#aura-petty-responsible-preview',
            previewAvatar: '#aura-petty-responsible-preview-avatar',
            previewText: '#aura-petty-responsible-preview-text',
            previewBadge: '#aura-petty-responsible-preview-badge'
        });

        window.AuraThirdPartySelector.attach({
            input: '#aura-reimburse-person-name',
            hiddenId: '#aura-reimburse-person',
            formatId: 'prefixed',
            previewContainer: '#aura-reimburse-preview',
            previewAvatar: '#aura-reimburse-preview-avatar',
            previewText: '#aura-reimburse-preview-text',
            previewBadge: '#aura-reimburse-preview-badge'
        });

        window.AuraThirdPartySelector.attach({
            input: '#aura-reimburse-pay-person-name',
            hiddenId: '#aura-reimburse-pay-person',
            formatId: 'prefixed',
            previewContainer: '#aura-reimburse-pay-preview',
            previewAvatar: '#aura-reimburse-pay-preview-avatar',
            previewText: '#aura-reimburse-pay-preview-text',
            previewBadge: '#aura-reimburse-pay-preview-badge'
        });
    }

    $(document).on('click', '#aura-petty-responsible-preview-clear', function (e) {
        e.preventDefault();
        $('#aura-petty-responsible-name').val('');
        $('#aura-petty-responsible').val('');
        $('#aura-petty-responsible-preview').hide();
    });

    $(document).on('click', '#aura-reimburse-preview-clear', function (e) {
        e.preventDefault();
        $('#aura-reimburse-person-name').val('');
        $('#aura-reimburse-person').val('');
        $('#aura-reimburse-preview').hide();
    });

    $(document).on('click', '#aura-reimburse-pay-preview-clear', function (e) {
        e.preventDefault();
        $('#aura-reimburse-pay-person-name').val('');
        $('#aura-reimburse-pay-person').val('');
        $('#aura-reimburse-pay-preview').hide();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-cuentas"]', function () {
        loadAccounts();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-presupuestos"]', function () {
        loadBudgetByYear();
        updateBudgetOverview();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-cajachica"]', function () {
        loadPettyCash();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-reembolsos"]', function () {
        loadReimbursements();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-reportes"]', function () {
        loadReports();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-terceros"]', function () {
        loadThirdPartiesManagement();
    });

    $(document).on('click', '.aura-tab-btn[data-tab="tab-divisas"]', function () {
        loadExchangeHistory();
    });

    // Listeners interactivos de Caja Chica
    $(document).on('input', '#aura-petty-search', filterPettyCash);
    $(document).on('change', '#aura-petty-filter-custodian, #aura-petty-filter-status, #aura-petty-filter-overdue, #aura-petty-filter-delivery-type', filterPettyCash);
    $(document).on('click', '#aura-petty-filter-reset', function () {
        $('#aura-petty-search').val('');
        $('#aura-petty-filter-custodian').val('');
        $('#aura-petty-filter-status').val('');
        $('#aura-petty-filter-overdue').val('');
        $('#aura-petty-filter-delivery-type').val('');
        filterPettyCash();
    });
    $(document).on('click', '#aura-petty-refresh-btn', function () {
        const $btn = $(this);
        $btn.prop('disabled', true);
        loadPettyCash();
        setTimeout(function () {
            $btn.prop('disabled', false);
        }, 500);
    });
    $(document).on('click', '#aura-petty-guide-close', function () {
        $('#aura-petty-guide-banner').slideUp(180);
    });

    resetForm();
    updateBudgetOverview();
    resetPettyForm();
    resetReimburseForms();
    loadAccounts();
    loadPettyCash();
    loadReimbursements();
    loadThirdPartiesManagement();
    loadReports();
    loadBudgetByYear();
    loadExchangeHistory();
});

