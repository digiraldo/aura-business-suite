/* global auraFinancialAccounts */
jQuery(function ($) {
    'use strict';

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

    function syncBodyModalState() {
        const hasOpenModal = $('.aura-finance-modal:visible').length > 0 || $('#aura-petty-evidence-modal:visible').length > 0;
        $('body').toggleClass('aura-modal-open', hasOpenModal);
    }

    function openFinanceModal(modalId) {
        const $modal = $('#' + modalId);
        if (!$modal.length) {
            return;
        }
        $modal.show().attr('aria-hidden', 'false');
        syncBodyModalState();
    }

    function closeFinanceModal(modalId) {
        const $modal = $('#' + modalId);
        if (!$modal.length) {
            return;
        }
        $modal.hide().attr('aria-hidden', 'true');
        syncBodyModalState();
    }

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
        const responsibleId = parseInt($('#aura-petty-responsible').val(), 10);
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

    function positionTooltip(element) {
        const $tip = $(element);
        if (!$tip.hasClass('aura-help-tip')) {
            return;
        }

        // Remove all position classes
        $tip.removeClass('tooltip-top tooltip-bottom tooltip-left tooltip-right');

        // Get button rect
        const rect = element.getBoundingClientRect();
        const tipWidth = 320;
        const tipHeight = 80;
        const arrowSize = 6;
        const gap = 10;

        // Calculate available space
        const spaceTop = rect.top;
        const spaceBottom = window.innerHeight - rect.bottom;
        const spaceLeft = rect.left;
        const spaceRight = window.innerWidth - rect.right;

        let position = 'top';

        // Prefer top/bottom over left/right
        if (spaceTop > tipHeight + gap) {
            position = 'top';
        } else if (spaceBottom > tipHeight + gap) {
            position = 'bottom';
        } else if (spaceLeft > tipWidth + gap) {
            position = 'left';
        } else if (spaceRight > tipWidth + gap) {
            position = 'right';
        } else {
            // Fallback to best available
            const spaces = { top: spaceTop, bottom: spaceBottom, left: spaceLeft, right: spaceRight };
            position = Object.keys(spaces).reduce((a, b) => spaces[a] > spaces[b] ? a : b);
        }

        $tip.addClass('tooltip-' + position);

        // Set CSS variables for positioning
        const midX = rect.left + rect.width / 2;
        const midY = rect.top + rect.height / 2;

        if (position === 'top') {
            const tooltipLeft = Math.max(10, Math.min(midX - tipWidth / 2, window.innerWidth - tipWidth - 10));
            $tip.css({
                '--tooltip-top': (rect.top - tipHeight - gap) + 'px',
                '--tooltip-left': midX + 'px',
                '--tooltip-arrow-top': (rect.top - arrowSize - gap) + 'px',
                '--tooltip-arrow-left': midX + 'px'
            });
        } else if (position === 'bottom') {
            $tip.css({
                '--tooltip-top': (rect.bottom + gap) + 'px',
                '--tooltip-left': midX + 'px',
                '--tooltip-arrow-top': (rect.bottom + gap - arrowSize) + 'px',
                '--tooltip-arrow-left': midX + 'px'
            });
        } else if (position === 'left') {
            $tip.css({
                '--tooltip-top': midY + 'px',
                '--tooltip-right': (window.innerWidth - rect.left + gap) + 'px',
                '--tooltip-arrow-top': midY + 'px',
                '--tooltip-arrow-right': (window.innerWidth - rect.left + gap - arrowSize) + 'px'
            });
        } else if (position === 'right') {
            $tip.css({
                '--tooltip-top': midY + 'px',
                '--tooltip-left': (rect.right + gap) + 'px',
                '--tooltip-arrow-top': midY + 'px',
                '--tooltip-arrow-left': (rect.right + gap - arrowSize) + 'px'
            });
        }
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
        return '<span class="aura-account-badge is-' + escapeHtml(type) + '">' + escapeHtml(typeLabel(type)) + '</span>';
    }

    function statusBadge(active) {
        const isActive = parseInt(active, 10) === 1;
        return '<span class="aura-account-status ' + (isActive ? 'is-active' : 'is-inactive') + '">' + escapeHtml(statusLabel(active)) + '</span>';
    }

    function renderActionButtons(id) {
        return '<div class="aura-account-actions">' +
            '<button type="button" class="button button-small aura-account-action is-edit aura-account-edit" data-id="' + id + '" title="Editar">' +
                '<span class="dashicons dashicons-edit"></span>' +
            '</button>' +
            '<button type="button" class="button button-small aura-account-action is-delete aura-account-delete" data-id="' + id + '" title="Eliminar">' +
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
        $form[0].reset();
        $('#aura-account-id').val('0');
        $('#aura-account-active').prop('checked', true);
        $('#aura-account-form-title').text('Nueva Cuenta');
    }

    function parseMoney(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : 0;
    }

    function updateBudgetMonthlyTotal() {
        let total = 0;
        $('.aura-budget-month-input').each(function () {
            total += parseMoney($(this).val());
        });
        $('#aura-budget-monthly-total').text(formatNumber(total));
        return total;
    }

    function updateBudgetOverview() {
        const annualLimit = parseMoney($('#aura-budget-annual-limit').val());
        const monthlyTotal = updateBudgetMonthlyTotal();
        const remaining = annualLimit - monthlyTotal;
        const usedPercent = annualLimit > 0 ? Math.min((monthlyTotal / annualLimit) * 100, 100) : 0;
        const policy = $('#aura-budget-policy').val() === 'block' ? 'Bloquear' : 'Advertir';

        $('#aura-budget-kpi-annual').text(formatNumber(annualLimit));
        $('#aura-budget-kpi-monthly').text(formatNumber(monthlyTotal));
        $('#aura-budget-kpi-remaining').text(formatNumber(remaining));
        $('#aura-budget-kpi-policy').text(policy);
        $('#aura-budget-progress-text').text(usedPercent.toFixed(0) + '%');
        $('#aura-budget-progress-bar').css('width', usedPercent + '%');
        $('.aura-budget-progress-track').attr('aria-valuenow', usedPercent.toFixed(0));
    }

    function updateAccountsKpis(accounts) {
        const rows = Array.isArray(accounts) ? accounts : [];
        let active = 0;
        let cop = 0;
        let usd = 0;

        rows.forEach(function (account) {
            const balance = parseFloat(account.current_balance || 0);
            const currency = String(account.currency || 'COP').toUpperCase();
            if (parseInt(account.is_active, 10) === 1) {
                active += 1;
            }
            if (currency === 'USD') {
                usd += balance;
            } else if (currency === 'COP') {
                cop += balance;
            }
        });

        $('#aura-kpi-total-accounts').text(rows.length);
        $('#aura-kpi-active-accounts').text(active);
        $('#aura-kpi-balance-cop').text(formatNumber(cop));
        $('#aura-kpi-balance-usd').text(formatNumber(usd));
    }

    function initAccountsTable() {
        if (!$.fn.DataTable || !$table.length) {
            return;
        }

        if (accountsTable) {
            accountsTable.destroy();
            accountsTable = null;
        }

        accountsTable = $table.DataTable({
            responsive: true,
            searching: false,
            dom: '<"aura-dt-top"li>rt<"aura-dt-bottom"p>',
            pageLength: 20,
            lengthMenu: [10, 20, 50, 100],
            autoWidth: false,
            order: [[0, 'asc']],
            language: {
                info: '_TOTAL_ cuentas',
                infoEmpty: '0 cuentas',
                infoFiltered: '(filtrado de _MAX_ total)',
                lengthMenu: 'Mostrar _MENU_ por página',
                zeroRecords: 'No se encontraron cuentas.',
                paginate: { first: '«', last: '»', previous: '‹', next: '›' }
            },
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: 3 },
                { responsivePriority: 1, targets: 5 },
                { responsivePriority: 10000, targets: 1 },
                { responsivePriority: 2, targets: 4 }
            ]
        });
    }

    function fillBudgetForm(data) {
        const env = (data && data.envelope) || null;
        const months = (data && data.months) || [];

        $('#aura-budget-annual-limit').val(env ? env.annual_limit : 0);
        $('#aura-budget-policy').val(env ? env.exceed_policy : 'warn');

        $('.aura-budget-month-input').each(function (idx) {
            const val = typeof months[idx] !== 'undefined' ? months[idx] : 0;
            $(this).val(val);
        });

        updateBudgetOverview();
    }

    function loadBudgetByYear() {
        const year = parseInt($('#aura-budget-year').val(), 10) || new Date().getFullYear();

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
            showFeedback('La suma mensual supera el tope anual.', false);
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
                closeFinanceModal('aura-finance-budget-modal');
                loadBudgetByYear();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-budget-save-btn').prop('disabled', false).text('Guardar presupuesto');
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
        formData.append('action', 'aura_finance_budget_upload_preview');
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
                budgetImportToken = (res.data && res.data.token) || '';
                $('#aura-budget-import-wizard').show();
                $('#aura-budget-confirm-btn').prop('disabled', true);
                $('#aura-budget-import-validation').hide().empty();

                renderBudgetPreview((res.data && res.data.headers) || [], (res.data && res.data.preview) || []);
                renderBudgetMapping((res.data && res.data.headers) || [], (res.data && res.data.auto_mapping) || {});

                const summary = 'Archivo: ' + escapeHtml((res.data && res.data.filename) || '') +
                    ' | Filas detectadas: ' + ((res.data && res.data.total_rows) || 0);
                $('#aura-budget-import-summary').html(summary);
                showFeedback('Archivo analizado. Revisa la vista previa y valida antes de importar.', true);
                return;
            }

            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        }).always(function () {
            $('#aura-budget-import-btn').prop('disabled', false).text('1) Analizar archivo');
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
                loadBudgetByYear();
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
            $('#aura-budget-confirm-btn').prop('disabled', false).text('3) Confirmar importación');
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

    function renderTable(accounts) {
        updateAccountsKpis(accounts);

        if (!accounts || !accounts.length) {
            if (accountsTable) {
                accountsTable.clear().destroy();
                accountsTable = null;
            }
            $tableBody.html('<tr><td colspan="6">No hay cuentas registradas.</td></tr>');
            return;
        }

        const rows = accounts.map(function (a) {
            const balance = formatNumber(a.current_balance || 0);

            return '<tr>' +
                '<td><div class="aura-account-name"><strong>' + escapeHtml(a.name) + '</strong><small>' + escapeHtml(a.institution || 'Sin institución') + '</small></div></td>' +
                '<td>' + typeBadge(a.account_type) + '</td>' +
                '<td><strong>' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + '</strong></td>' +
                '<td><div class="aura-account-balance"><strong>' + escapeHtml(balance) + '</strong><small>' + escapeHtml(a.account_number_masked || 'Sin número visible') + '</small></div></td>' +
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
                    closeFinanceModal('aura-finance-account-modal');
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

    function renderEvidenceContent(row) {
        const evidence = parseEvidence(row && row.evidence_json ? row.evidence_json : null);
        const linksText = evidence.links ? '<pre class="aura-petty-evidence-links">' + escapeHtml(evidence.links) + '</pre>' : '<p class="description">Sin enlaces manuales.</p>';

        const attachmentsHtml = evidence.attachments.length
            ? '<ul class="aura-petty-evidence-files">' + evidence.attachments.map(function (f) {
                const url = f && f.url ? String(f.url) : '#';
                const name = f && f.name ? String(f.name) : 'archivo';
                return '<li><a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(name) + '</a></li>';
            }).join('') + '</ul>'
            : '<p class="description">Sin archivos adjuntos.</p>';

        return '<p><strong>ID:</strong> #' + escapeHtml(row.id) + ' &nbsp; <strong>Responsable:</strong> ' + escapeHtml(row.responsible_name || '') + '</p>' +
            '<h4>Archivos recibo</h4>' + attachmentsHtml +
            '<h4>Enlaces / referencias</h4>' + linksText;
    }

    function openEvidenceModal(row) {
        const $modal = $('#aura-petty-evidence-modal');
        const $content = $('#aura-petty-evidence-content');
        if (!$modal.length || !$content.length) {
            return;
        }
        $content.html(renderEvidenceContent(row));
        $modal.show().attr('aria-hidden', 'false');
        syncBodyModalState();
    }

    function closeEvidenceModal() {
        const $modal = $('#aura-petty-evidence-modal');
        if (!$modal.length) {
            return;
        }
        $modal.hide().attr('aria-hidden', 'true');
        syncBodyModalState();
    }

    function resetPettyForm() {
        if ($pettyForm.length && $pettyForm[0]) {
            $pettyForm[0].reset();
        }
        $('#aura-petty-id').val('0');
        $('#aura-petty-spent').val('0');
        $('#aura-petty-returned').val('0');
        $('#aura-petty-due-date').val(toLocalDateInput(parseInt(auraFinancialAccounts.defaultDueDays, 10) || 5));
        $('#aura-petty-evidence-files').val('');
        setWizardStep('aura-petty-modal-wizard', 1);
        updatePettyStepThreeHint();
    }

    function fillPettyAccounts(accounts) {
        const $sel = $('#aura-petty-account');
        if (!$sel.length) {
            return;
        }
        const opts = ['<option value="">Selecciona una cuenta...</option>'];
        (accounts || []).forEach(function (a) {
            opts.push('<option value="' + parseInt(a.id, 10) + '">' +
                escapeHtml(a.name) + ' (' + escapeHtml(String(a.currency || 'COP').toUpperCase()) + ')</option>');
        });
        $sel.html(opts.join(''));
    }

    function renderPettyTable(rows) {
        if (!$pettyTableBody.length) {
            return;
        }

        if (!rows || !rows.length) {
            $pettyTableBody.html('<tr><td colspan="9">Sin rendiciones registradas.</td></tr>');
            return;
        }

        const html = rows.map(function (r) {
            const canSubmit = r.status === 'open' || r.status === 'rejected';
            const canApprove = pettyCashCanApprove && r.status === 'submitted';
            const canClose = pettyCashCanApprove && r.status === 'approved';
            const evidenceCount = getEvidenceCount(r.evidence_json);

            const buttons = [
                '<button type="button" class="button button-small aura-petty-pick" data-id="' + r.id + '" title="Cargar en formulario"><span class="dashicons dashicons-edit"></span></button>'
            ];
            if (evidenceCount > 0 || (r.evidence_json && String(r.evidence_json).trim() !== '')) {
                buttons.push('<button type="button" class="button button-small aura-petty-view-evidence" data-id="' + r.id + '" title="Ver evidencias"><span class="dashicons dashicons-visibility"></span></button>');
            }
            if (canSubmit) {
                buttons.push('<button type="button" class="button button-small aura-petty-action" data-id="' + r.id + '" data-status="submitted">Enviar</button>');
            }
            if (canApprove) {
                buttons.push('<button type="button" class="button button-small aura-petty-action" data-id="' + r.id + '" data-status="approved">Aprobar</button>');
                buttons.push('<button type="button" class="button button-small aura-petty-action" data-id="' + r.id + '" data-status="rejected">Rechazar</button>');
            }
            if (canClose) {
                buttons.push('<button type="button" class="button button-small aura-petty-action" data-id="' + r.id + '" data-status="closed">Cerrar</button>');
            }

            return '<tr>' +
                '<td>' + escapeHtml(r.created_at || '') + '</td>' +
                '<td>' + escapeHtml((r.due_date || '').slice(0, 10)) + '</td>' +
                '<td>' + escapeHtml(r.account_name || '') + '</td>' +
                '<td>' + escapeHtml(r.responsible_name || '') + '</td>' +
                '<td><strong>' + escapeHtml(formatNumber(r.delivered_amount || 0)) + '</strong></td>' +
                '<td>' + escapeHtml(formatNumber(r.spent_amount || 0)) + '</td>' +
                '<td>' + escapeHtml(formatNumber(r.returned_amount || 0)) + '</td>' +
                '<td>' + pettyStatusBadge(r.status) + (parseInt(r.is_overdue, 10) === 1 ? ' <span class="aura-petty-overdue-mark">Vencida</span>' : '') + (evidenceCount > 0 ? ' <span class="aura-petty-overdue-mark is-evidence">' + evidenceCount + ' recibo(s)</span>' : '') + '</td>' +
                '<td><div class="aura-petty-actions">' + buttons.join('') + '</div></td>' +
                '</tr>';
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
                renderPettyTable(window.auraPettyCashCache);
                fillPettyAccounts(data.petty_cash_accounts || []);
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function createPettyCash() {
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_petty_cash_create',
            nonce: auraFinancialAccounts.nonce,
            petty_cash_account_id: $('#aura-petty-account').val(),
            responsible_user_id: $('#aura-petty-responsible').val(),
            delivered_amount: $('#aura-petty-delivered').val(),
            due_date: $('#aura-petty-due-date').val(),
            notes: $('#aura-petty-notes').val()
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Entrega registrada.', true);
                resetPettyForm();
                closeFinanceModal('aura-finance-petty-modal');
                loadPettyCash();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function submitPettyCash(id) {
        const fd = new window.FormData();
        fd.append('action', 'aura_finance_petty_cash_submit');
        fd.append('nonce', auraFinancialAccounts.nonce);
        fd.append('id', id);
        fd.append('spent_amount', $('#aura-petty-spent').val());
        fd.append('returned_amount', $('#aura-petty-returned').val());
        fd.append('evidence_json', $('#aura-petty-evidence').val());
        fd.append('notes', $('#aura-petty-notes').val());

        const files = document.getElementById('aura-petty-evidence-files');
        if (files && files.files && files.files.length) {
            Array.prototype.forEach.call(files.files, function (file) {
                fd.append('evidence_files[]', file);
            });
        }

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
                closeFinanceModal('aura-finance-petty-modal');
                loadPettyCash();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function updatePettyStatus(id, status) {
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_petty_cash_status',
            nonce: auraFinancialAccounts.nonce,
            id: id,
            status: status
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
        return '<span class="aura-reimburse-status is-' + escapeHtml(status) + '">' + escapeHtml(reimbursementStatusLabel(status)) + '</span>';
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

    function renderReimbursementsTable(rows) {
        if (!$reimbursementsTableBody.length) {
            return;
        }

        if (!rows || !rows.length) {
            $reimbursementsTableBody.html('<tr><td colspan="8">Sin reembolsos registrados.</td></tr>');
            return;
        }

        const html = rows.map(function (r) {
            const owed = parseFloat(r.owed_amount || 0);
            const paid = parseFloat(r.paid_amount || 0);
            const remaining = Math.max(0, owed - paid);
            const canPay = r.status === 'pending' || r.status === 'partial';
            const originText = r.origin_transaction_id ? ('#' + r.origin_transaction_id) : 'Manual';
            const actions = [];

            if (canPay && remaining > 0) {
                actions.push('<button type="button" class="button button-small aura-reimburse-pay-pick" data-id="' + r.id + '" data-remaining="' + remaining + '" title="Registrar pago"><span class="dashicons dashicons-money-alt"></span></button>');
            }

            return '<tr>' +
                '<td>' + escapeHtml(String(r.created_at || '').slice(0, 10)) + '</td>' +
                '<td>' + escapeHtml(r.person_name || ('Usuario #' + r.person_user_id)) + '</td>' +
                '<td><strong>' + escapeHtml(originText) + '</strong><br><small>' + escapeHtml(r.origin_description || '') + '</small></td>' +
                '<td>' + escapeHtml(formatNumber(owed)) + '</td>' +
                '<td>' + escapeHtml(formatNumber(paid)) + '</td>' +
                '<td><strong>' + escapeHtml(formatNumber(remaining)) + '</strong></td>' +
                '<td>' + reimbursementStatusBadge(r.status) + '</td>' +
                '<td><div class="aura-reimburse-actions">' + actions.join('') + '</div></td>' +
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
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function createReimbursement() {
        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_reimbursements_create',
            nonce: auraFinancialAccounts.nonce,
            person_user_id: $('#aura-reimburse-person').val(),
            owed_amount: $('#aura-reimburse-owed').val(),
            origin_transaction_id: $('#aura-reimburse-origin').val(),
            notes: $('#aura-reimburse-notes').val()
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Reembolso creado.', true);
                resetReimburseForms();
                closeFinanceModal('aura-finance-reimburse-modal');
                loadReimbursements();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
            showFeedback(auraFinancialAccounts.i18n.error, false);
        });
    }

    function payReimbursement() {
        if (auraFinancialAccounts.i18n.reimbursementPayConfirm && !window.confirm(auraFinancialAccounts.i18n.reimbursementPayConfirm)) {
            return;
        }

        $.post(auraFinancialAccounts.ajaxUrl, {
            action: 'aura_finance_reimbursements_pay',
            nonce: auraFinancialAccounts.nonce,
            id: $('#aura-reimburse-pay-id').val(),
            paying_account_id: $('#aura-reimburse-pay-account').val(),
            payment_amount: $('#aura-reimburse-pay-amount').val(),
            notes: $('#aura-reimburse-pay-notes').val()
        }).done(function (res) {
            if (res && res.success) {
                showFeedback((res.data && res.data.message) || 'Pago aplicado.', true);
                resetReimburseForms();
                closeFinanceModal('aura-finance-reimburse-pay-modal');
                loadReimbursements();
                loadAccounts();
                return;
            }
            showFeedback((res && res.data && res.data.message) || auraFinancialAccounts.i18n.error, false);
        }).fail(function () {
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
        const blocks = data.excel_blocks || [];
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
            return '<tr>' +
                '<td><strong>' + escapeHtml(row.name || '') + '</strong><br><small>' + escapeHtml(typeLabel(row.account_type || 'custom')) + '</small></td>' +
                '<td>' + escapeHtml(String(row.currency || '').toUpperCase()) + '</td>' +
                '<td>' + escapeHtml(formatNumber(row.inflows || 0)) + '</td>' +
                '<td>' + escapeHtml(formatNumber(row.outflows || 0)) + '</td>' +
                '<td><strong>' + escapeHtml(formatNumber(row.current_balance || 0)) + '</strong></td>' +
                '</tr>';
        });

        renderSimpleRows($('#aura-report-currency-table tbody'), currencies, 3, function (row) {
            return '<tr><td><strong>' + escapeHtml(String(row.currency || '').toUpperCase()) + '</strong></td><td>' +
                escapeHtml(row.account_count) + '</td><td>' + escapeHtml(formatNumber(row.total_balance || 0)) + '</td></tr>';
        });

        renderSimpleRows($('#aura-report-type-table tbody'), types, 3, function (row) {
            return '<tr><td>' + typeBadge(row.account_type || 'custom') + '</td><td>' +
                escapeHtml(row.account_count) + '</td><td>' + escapeHtml(formatNumber(row.total_balance || 0)) + '</td></tr>';
        });

        renderSimpleRows($('#aura-report-blocks-table tbody'), blocks, 4, function (row) {
            return '<tr><td><strong>' + escapeHtml(row.excel_block || 'sin_bloque') + '</strong></td><td>' +
                escapeHtml(row.total_transactions) + '</td><td>' + escapeHtml(formatNumber(row.total_income || 0)) + '</td><td>' + escapeHtml(formatNumber(row.total_expense || 0)) + '</td></tr>';
        });

        $('#aura-report-budget-summary').html(
            '<span><strong>Tope:</strong> ' + escapeHtml(formatNumber(budget.annual_limit || 0)) + '</span>' +
            '<span><strong>Ejecutado:</strong> ' + escapeHtml(formatNumber(budget.annual_spent || 0)) + '</span>' +
            '<span><strong>Disponible:</strong> ' + escapeHtml(formatNumber(budget.annual_remaining || 0)) + '</span>' +
            '<span><strong>Política:</strong> ' + escapeHtml((budget.policy || 'warn') === 'block' ? 'Bloquear' : 'Advertir') + '</span>'
        );

        renderSimpleRows($('#aura-report-budget-table tbody'), months, 4, function (row) {
            const isNegative = parseFloat(row.remaining || 0) < 0;
            return '<tr>' +
                '<td><strong>' + escapeHtml(monthNames[(parseInt(row.month_num, 10) || 1) - 1]) + '</strong></td>' +
                '<td>' + escapeHtml(formatNumber(row.limit || 0)) + '</td>' +
                '<td>' + escapeHtml(formatNumber(row.spent || 0)) + '</td>' +
                '<td><span class="' + (isNegative ? 'aura-report-negative' : 'aura-report-positive') + '">' + escapeHtml(formatNumber(row.remaining || 0)) + '</span></td>' +
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
                renderReports(res.data || {});
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
        openFinanceModal('aura-finance-account-modal');
    });

    $('#aura-budget-open-btn, #aura-budget-open-inline-btn').on('click', function () {
        setWizardStep('aura-budget-modal-wizard', 1);
        openFinanceModal('aura-finance-budget-modal');
    });

    $('#aura-petty-open-btn, #aura-petty-open-inline-btn').on('click', function () {
        resetPettyForm();
        openFinanceModal('aura-finance-petty-modal');
    });

    $('#aura-reimburse-open-btn, #aura-reimburse-open-inline-btn').on('click', function () {
        if ($reimbursementsForm.length && $reimbursementsForm[0]) {
            $reimbursementsForm[0].reset();
        }
        openFinanceModal('aura-finance-reimburse-modal');
    });

    $('#aura-reimburse-pay-open-btn, #aura-reimburse-pay-open-inline-btn').on('click', function () {
        if ($reimbursementsPayForm.length && $reimbursementsPayForm[0]) {
            $reimbursementsPayForm[0].reset();
        }
        $('#aura-reimburse-pay-id').val('0');
        openFinanceModal('aura-finance-reimburse-pay-modal');
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
            openFinanceModal('aura-finance-account-modal');
        }
    });

    $(document).on('click', '.aura-account-delete', function () {
        deleteAccount(parseInt($(this).data('id'), 10));
    });

    $pettyForm.on('submit', function (e) {
        e.preventDefault();
        createPettyCash();
    });

    $reimbursementsForm.on('submit', function (e) {
        e.preventDefault();
        createReimbursement();
    });

    $reimbursementsPayForm.on('submit', function (e) {
        e.preventDefault();
        const id = parseInt($('#aura-reimburse-pay-id').val(), 10);
        if (!id) {
            showFeedback('Selecciona una deuda de la tabla para aplicar el pago.', false);
            return;
        }
        payReimbursement();
    });

    $('#aura-petty-submit-btn').on('click', function () {
        const id = parseInt($('#aura-petty-id').val(), 10);
        if (!id) {
            showFeedback('Primero selecciona una rendición de la tabla para enviarla.', false);
            return;
        }
        submitPettyCash(id);
    });

    $('#aura-petty-reset-btn').on('click', function () {
        resetPettyForm();
    });

    $(document).on('click', '.aura-petty-pick', function () {
        const id = parseInt($(this).data('id'), 10);
        const list = window.auraPettyCashCache || [];
        const row = list.find(function (item) { return parseInt(item.id, 10) === id; });
        if (!row) {
            return;
        }
        $('#aura-petty-id').val(row.id || 0);
        $('#aura-petty-account').val(row.petty_cash_account_id || '');
        $('#aura-petty-responsible').val(row.responsible_user_id || '');
        $('#aura-petty-delivered').val(row.delivered_amount || 0);
        if (row.due_date) {
            $('#aura-petty-due-date').val(String(row.due_date).slice(0, 10));
        }
        $('#aura-petty-spent').val(row.spent_amount || 0);
        $('#aura-petty-returned').val(row.returned_amount || 0);
        $('#aura-petty-notes').val(row.notes || '');
        setWizardStep('aura-petty-modal-wizard', 2);
        openFinanceModal('aura-finance-petty-modal');
    });

    $(document).on('click', '.aura-petty-action', function () {
        const id = parseInt($(this).data('id'), 10);
        const status = $(this).data('status');
        if (!id || !status) {
            return;
        }
        updatePettyStatus(id, status);
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

    $(document).on('click', '.aura-reimburse-pay-pick', function () {
        const id = parseInt($(this).data('id'), 10);
        const remaining = parseFloat($(this).data('remaining') || 0);
        $('#aura-reimburse-pay-id').val(id || 0);
        $('#aura-reimburse-pay-amount').val(remaining > 0 ? remaining.toFixed(2) : '0.00');
        openFinanceModal('aura-finance-reimburse-pay-modal');
    });

    $(document).on('click', '[data-modal-close]', function () {
        closeFinanceModal($(this).data('modal-close'));
    });

    $(document).on('click', '.aura-help-tip', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const $tip = $(this);
        $('.aura-help-tip').not($tip).removeClass('is-open');
        $tip.toggleClass('is-open');
        positionTooltip($tip[0]);
    });

    $(document).on('click', function () {
        $('.aura-help-tip').removeClass('is-open');
    });

    $(document).on('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        $('.aura-help-tip').removeClass('is-open');

        if ($('#aura-petty-evidence-modal:visible').length) {
            closeEvidenceModal();
            return;
        }

        const $visibleModal = $('.aura-finance-modal:visible').last();
        if ($visibleModal.length) {
            closeFinanceModal($visibleModal.attr('id'));
        }
    });

    // Auto-position tooltips on hover/focus
    $(document).on('mouseenter focus', '.aura-help-tip', function () {
        positionTooltip(this);
    });

    // Reposition on window resize
    $(window).on('resize', function () {
        if ($('.aura-help-tip.is-open').length) {
            $('.aura-help-tip.is-open').each(function () {
                positionTooltip(this);
            });
        }
    });

    $('#aura-petty-evidence-close').on('click', function () {
        closeEvidenceModal();
    });

    $(document).on('click', '#aura-petty-evidence-modal [data-close="1"]', function () {
        closeEvidenceModal();
    });

    resetForm();
    setWizardStep('aura-budget-modal-wizard', 1);
    setWizardStep('aura-petty-modal-wizard', 1);
    updatePettyStepThreeHint();
    updateBudgetOverview();
    loadAccounts();
    loadPettyCash();
    loadReimbursements();
    loadReports();
    if ($budgetForm.length) {
        loadBudgetByYear();
    }
});
