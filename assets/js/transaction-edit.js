/**
 * Transaction Edit JS
 * 
 * Maneja la edición de transacciones con:
 * - Vista previa en tiempo real en vivo (paridad UX/UI con Nueva Transacción)
 * - Detección de cambios precisa y chips visuales
 * - Resumen de cambios en sidebar
 * - Validación de cambios significativos
 * - Restauración de valores originales
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Objeto para almacenar datos originales
    const originalData = JSON.parse($('#original_data').val() || '{}');
    
    // Objeto para rastrear cambios
    let detectedChanges = {};
    
    // Caché de nombres de categorías (ID => Nombre)
    let categoriesCache = {};

    // Estado de categorías/presupuesto por área
    let areaBudgetCategoriesMap = {};
    let activeAreaHasBudgetCategories = false;
    let currentAreaBudget = null;

    // Valores iniciales para conservar prellenado al recargar selects dinámicos
    const initialState = {
        budgetCategoryId: String($('#category_id').data('original') || $('#category_id').val() || ''),
        expenseCategoryId: String($('#expense_category_id').data('original') || $('#expense_category_id').val() || '')
    };
    
    // Configuración
    const significantAmountChange = 20; // Porcentaje
    
    /**
     * Inicializar formulario de edición
     */
    function init() {
        initCategoriesCache();
        const type = getTransactionType();

        $('.aura-transaction-form-wrap').addClass('type-' + type);

        // Mantener lógica de cuentas coherente con Nueva Transacción
        toggleAccountFields(type);
        updateAccountGuidance(type);
        updateAccountSelectionState();
        updateAccountImpactSummaries();

        // Cargar categorías del gasto según tipo y conservar selección actual
        loadExpenseCategoriesForType(type, initialState.expenseCategoryId);

        // Estado inicial del campo de presupuesto
        toggleBudgetCategoryField(false);

        const areaId = getSelectedAreaId();
        if (areaId) {
            loadCategoriesForArea(areaId, type, initialState.budgetCategoryId);
        } else {
            loadCategories(type, initialState.budgetCategoryId);
        }

        initDatepicker();
        initFieldChangeDetection();
        initResetButtons();
        initFormSubmit();
        initFileUpload();
        initCollapsible();
        updateCharCounters();
        initSmartInteractions();
        initUserAutocomplete();
        initTagsSelector();
        updateExpenseCategoryDescHint();
        updateContextNote();
        updateFormCompletion();
        updateLivePreview();
    }
    
    /**
     * Inicializar secciones colapsables
     */
    function initCollapsible() {
        $(document).on('click', '.aura-collapsible-header', function(e) {
            e.preventDefault();
            const $header   = $(this);
            const $section  = $header.closest('.aura-collapsible');
            const $content  = $section.find('.aura-collapsible-content');
            const $icon     = $header.find('.toggle-icon');
            const isOpening = !$section.hasClass('aura-collapsible-open');
            
            if (isOpening) {
                $section.addClass('aura-collapsible-open');
                $content.stop(true, true).slideDown(250);
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            } else {
                $section.removeClass('aura-collapsible-open');
                $content.stop(true, true).slideUp(250);
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });
    }

    /**
     * Inicializar caché de categorías con opciones existentes
     */
    function initCategoriesCache() {
        $('#category_id option, #expense_category_id option').each(function() {
            const categoryId = String($(this).val());
            const categoryName = $(this).text().replace(/^[\s\u00a0—]+/, '').trim();
            
            if (categoryId && categoryId !== '') {
                categoriesCache[categoryId] = categoryName;
            }
        });
    }
    
    /**
     * Cargar categorías según tipo de transacción
     */
    function loadCategories(type, selectedCategoryId) {
        const transactionType = type || getTransactionType();
        const currentCategoryId = parseInt(
            selectedCategoryId || $('#category_id').val() || $('#category_id').data('original') || 0,
            10
        );
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_get_categories_by_type',
                transaction_type: transactionType,
                nonce: auraTransactionEdit.transactionNonce
            },
            success: function(response) {
                if (response.success) {
                    const $select = $('#category_id');
                    $select.find('option:not(:first)').remove();
                    
                    const typeLabel = transactionType === 'income' ? 'Ingreso' : 'Egreso';
                    
                    function appendCategories(list, prefix) {
                        list.forEach(function(category) {
                            const displayName = (prefix || '') + category.name + ' (' + typeLabel + ')';
                            categoriesCache[String(category.id)] = category.name;
                            
                            const $option = $('<option>')
                                .val(category.id)
                                .text(displayName)
                                .css('color', category.color || '');
                            
                            if (parseInt(category.id) === currentCategoryId) {
                                $option.prop('selected', true);
                            }
                            
                            $select.append($option);
                            
                            if (category.children && category.children.length > 0) {
                                appendCategories(category.children, '\u00a0\u00a0\u00a0— ');
                            }
                        });
                    }
                    
                    appendCategories(response.data.categories || [], '');
                    initCategoriesCache();
                    updateLivePreview();
                }
            }
        });
    }

    function loadExpenseCategoriesForType(type, selectedId) {
        const $select = $('#expense_category_id');
        if (!$select.length) return;

        const selectedValue = String(selectedId || $select.val() || $select.data('original') || '');
        $select.html('<option value="">Cargando...</option>').prop('disabled', true);

        $.ajax({
            url: auraTransactionEdit.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_get_categories_by_type',
                nonce: auraTransactionEdit.transactionNonce,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    renderExpenseCategoriesSelect(response.data.categories || [], selectedValue);
                } else {
                    $select.html('<option value="">Error al cargar</option>');
                }
            },
            error: function() {
                $select.html('<option value="">Error de conexión</option>');
            },
            complete: function() {
                $select.prop('disabled', false);
                updateLivePreview();
            }
        });
    }

    function renderExpenseCategoriesSelect(categories, selectedId, level) {
        const $select = $('#expense_category_id');
        level = level || 0;

        if (level === 0) {
            $select.html('<option value="">Seleccionar categoría del gasto...</option>');
        }

        (categories || []).forEach(function(category) {
            const indent = '\u00a0\u00a0'.repeat(level * 2);
            const optionId = String(category.id);
            categoriesCache[optionId] = category.name;

            const $option = $('<option></option>')
                .val(optionId)
                .html(indent + category.name)
                .attr('data-type', category.type || '')
                .attr('data-description', category.description || '')
                .data('category', category)
                .prop('selected', optionId === String(selectedId || ''));

            $select.append($option);

            if (category.children && category.children.length > 0) {
                renderExpenseCategoriesSelect(category.children, selectedId, level + 1);
            }
        });

        if (level === 0) {
            initCategoriesCache();
            updateExpenseCategoryDescHint();
        }
    }

    /**
     * Actualizar descripción inferior de la categoría de gasto seleccionada
     */
    function updateExpenseCategoryDescHint() {
        const $selected = $('#expense_category_id option:selected');
        const catData = $selected.data('category');
        const desc = (catData && catData.description) || $selected.attr('data-description') || '';
        const $hint = $('#expense-category-desc-hint');

        if ($hint.length) {
            if (desc && desc.trim() !== '') {
                $hint.find('.desc-text').text(desc.trim());
                $hint.slideDown(150);
            } else {
                $hint.slideUp(150);
            }
        }
    }

    function getSelectedAreaId() {
        const $area = $('#transaction_area_id');
        if (!$area.length) return 0;
        return parseInt($area.val(), 10) || 0;
    }

    function getSelectedAreaName() {
        const $area = $('#transaction_area_id');
        if (!$area.length) return '';

        if ($area.is('select')) {
            return $area.find('option:selected').text().trim();
        }

        return String($area.data('area-name') || '');
    }

    function renderCategoriesForArea(categories, selectedCategoryId) {
        const $select = $('#category_id');
        const selectedId = String(selectedCategoryId || '');
        $select.html('<option value="">Seleccionar categoría...</option>');

        if (!categories || !categories.length) {
            $select.append('<option value="" disabled>Sin categorías disponibles</option>');
            return;
        }

        categories.forEach(function(cat) {
            const hasBudget = !!areaBudgetCategoriesMap[cat.id];
            const label = hasBudget ? '💰 ' + cat.name : cat.name;
            const optionId = String(cat.id);
            categoriesCache[optionId] = cat.name;

            const $option = $('<option></option>')
                .val(optionId)
                .text(label)
                .data('category', cat)
                .data('has-budget', hasBudget)
                .prop('selected', optionId === selectedId);

            $select.append($option);
        });
    }

    function loadCategoriesForArea(areaId, type, selectedCategoryId) {
        const $select = $('#category_id');
        $select.html('<option value="">' + (auraTransactionEdit.messages.loadingCats || 'Cargando...') + '</option>').prop('disabled', true);
        hideBudgetBanner();

        return $.post(auraTransactionEdit.ajaxUrl, {
            action: 'aura_get_area_budget_categories',
            nonce: auraTransactionEdit.budgetsNonce,
            area_id: areaId,
            type: type
        }).done(function(res) {
            if (!res.success) {
                activeAreaHasBudgetCategories = false;
                currentAreaBudget = null;
                toggleBudgetCategoryField(false);
                loadCategories(type, selectedCategoryId);
                updateContextNote();
                return;
            }

            const data = res.data || {};
            areaBudgetCategoriesMap = {};
            currentAreaBudget = data.area_budget || null;
            activeAreaHasBudgetCategories = !!(data.has_budgets && data.categories && data.categories.length);

            if (data.categories && data.categories.length) {
                data.categories.forEach(function(c) {
                    areaBudgetCategoriesMap[c.id] = c;
                });
            }

            const cats = (data.categories || []).filter(function(c) {
                return !type || c.type === type || !c.type;
            });

            renderCategoriesForArea(cats, selectedCategoryId);
            toggleBudgetCategoryField(activeAreaHasBudgetCategories);

            const currentCat = parseInt($('#category_id').val(), 10) || 0;
            if (currentCat) {
                renderBudgetBanner(areaId, currentCat);
            } else if (currentAreaBudget) {
                renderAreaGlobalBudgetBanner(currentAreaBudget);
            } else if (!activeAreaHasBudgetCategories) {
                showBudgetBannerWarning(
                    auraTransactionEdit.messages.noBudgetsForArea || 'Esta área no tiene presupuestos asignados para la fecha actual.'
                );
            }

            updateContextNote();
            updateFormCompletion();
            initCategoriesCache();
            updateLivePreview();
        }).fail(function() {
            activeAreaHasBudgetCategories = false;
            currentAreaBudget = null;
            toggleBudgetCategoryField(false);
            loadCategories(type, selectedCategoryId);
            updateContextNote();
            updateFormCompletion();
        }).always(function() {
            $select.prop('disabled', false);
        });
    }

    function renderAreaGlobalBudgetBanner(areaBudget) {
        if (!areaBudget) return;
        const pct      = parseFloat(areaBudget.percentage || 0);
        const executed = parseFloat(areaBudget.executed   || 0);
        const budget   = parseFloat(areaBudget.budget_amount || 0);
        const avail    = parseFloat(areaBudget.available  || 0);
        const overrun  = parseFloat(areaBudget.overrun    || 0);

        const barColor = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
        const statusIcon = pct > 100 ? '🔴' : (pct >= 90 ? '🟠' : (pct >= 70 ? '🟡' : '💰'));
        const areaName = getSelectedAreaName() || 'Área';

        const html = '<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:6px;padding:10px 14px;font-size:13px;line-height:1.5;">'
            + statusIcon + ' <strong>Presupuesto activo de Área: ' + escHtml(areaName) + '</strong><br>'
            + '<span style="color:#50575e;">'
            + '&nbsp;Asignado: <strong>$' + fmtNum(budget) + '</strong>'
            + ' &nbsp;|&nbsp; Ejecutado: <strong style="color:' + barColor + '">$' + fmtNum(executed) + ' (' + pct.toFixed(1) + '%)</strong>'
            + ' &nbsp;|&nbsp; ' + (overrun > 0
                ? 'Exceso: <strong style="color:#d63638">$' + fmtNum(overrun) + '</strong>'
                : 'Disponible: <strong style="color:#00a32a">$' + fmtNum(avail) + '</strong>')
            + '</span>'
            + '<div id="aura-overspend-warning" style="display:none;color:#d63638;margin-top:4px;font-weight:600;"></div>'
            + '</div>';

        $('#aura-budget-status-banner').html(html).show();
        checkOverspend();
    }

    function renderBudgetBanner(areaId, catId) {
        const catData = areaBudgetCategoriesMap[catId];

        if (!catData || !catData.budget_id) {
            if (currentAreaBudget) {
                renderAreaGlobalBudgetBanner(currentAreaBudget);
                return;
            }
            const catName = $('#category_id option:selected').text().replace('💰 ', '').trim();
            const areaName = getSelectedAreaName();
            showBudgetBannerWarning(
                (auraTransactionEdit.messages.noBudgetForCat || 'No hay presupuesto activo para esta categoría en el área seleccionada.')
                + (catName && areaName ? ' (' + catName + ' en ' + areaName + ')' : '')
            );
            return;
        }

        const pct = parseFloat(catData.percentage || 0);
        const executed = parseFloat(catData.executed || 0);
        const budget = parseFloat(catData.budget_amount || 0);
        const avail = parseFloat(catData.available || 0);
        const overrun = parseFloat(catData.overrun || 0);

        const barColor = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
        const statusIcon = pct > 100 ? '🔴' : (pct >= 90 ? '🟠' : (pct >= 70 ? '🟡' : '💰'));
        const catName = $('#category_id option:selected').text().replace('💰 ', '').trim();
        const areaName = getSelectedAreaName();

        const html = '<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:6px;padding:10px 14px;font-size:13px;line-height:1.5;">'
            + statusIcon + ' <strong>Presupuesto activo: ' + escHtml(areaName) + ' → ' + escHtml(catName) + '</strong><br>'
            + '<span style="color:#50575e;">'
            + '&nbsp;Asignado: <strong>$' + fmtNum(budget) + '</strong>'
            + ' &nbsp;|&nbsp; Ejecutado: <strong style="color:' + barColor + '">$' + fmtNum(executed) + ' (' + pct.toFixed(1) + '%)</strong>'
            + ' &nbsp;|&nbsp; ' + (overrun > 0
                ? 'Exceso: <strong style="color:#d63638">$' + fmtNum(overrun) + '</strong>'
                : 'Disponible: <strong style="color:#00a32a">$' + fmtNum(avail) + '</strong>')
            + '</span>'
            + '<div id="aura-overspend-warning" style="display:none;color:#d63638;margin-top:4px;font-weight:600;"></div>'
            + '</div>';

        $('#aura-budget-status-banner').html(html).show();
        checkOverspend();
    }

    function showBudgetBannerWarning(msg) {
        const html = '<div style="background:#fff8e7;border:1px solid #dba617;border-radius:6px;padding:10px 14px;font-size:13px;color:#614200;">'
            + '⚠️ ' + escHtml(msg)
            + '</div>';
        $('#aura-budget-status-banner').html(html).show();
    }

    function hideBudgetBanner() {
        $('#aura-budget-status-banner').hide().html('');
    }

    function toggleBudgetCategoryField(show) {
        const $field = $('#aura-budget-category-field');
        if (!$field.length) return;

        const $select = $field.find('select');
        if (show) {
            $select.prop('disabled', false);
            $field.show();
            return;
        }

        $select.prop('disabled', true).val('');
        $field.hide();
        hideBudgetBanner();
    }

    function checkOverspend() {
        const $warning = $('#aura-overspend-warning');
        if (!$warning.length) return;

        const catId = parseInt($('#category_id').val(), 10) || 0;
        const catData = areaBudgetCategoriesMap[catId];
        let avail = 0;
        let hasBudget = false;

        if (catData && catData.budget_id) {
            avail = parseFloat(catData.available || 0);
            hasBudget = true;
        } else if (currentAreaBudget) {
            avail = parseFloat(currentAreaBudget.available || 0);
            hasBudget = true;
        }

        if (!hasBudget) {
            $warning.hide();
            return;
        }

        const amount = parseFloat($('#amount').val()) || 0;

        if (amount > 0 && amount > avail) {
            $warning.text(
                (auraTransactionEdit.messages.overspend || '⚠️ Este monto supera el disponible del presupuesto')
                + ' ($' + fmtNum(avail) + ')'
            ).show();
        } else {
            $warning.hide();
        }
    }

    function updateAccountSelectionState() {
        const sourceSelected = !!$('#source_account_id').val();
        const destinationSelected = !!$('#destination_account_id').val();

        $('#source_account_id').closest('.aura-account-field').toggleClass('is-account-selected', sourceSelected);
        $('#destination_account_id').closest('.aura-account-field').toggleClass('is-account-selected', destinationSelected);
    }

    function getSelectedAccountData(selectId) {
        const $select = $(selectId);
        const $selected = $select.find('option:selected');
        const id = ($selected.val() || '').toString().trim();
        const balanceRaw = parseFloat($selected.data('balance'));
        const currency = ($selected.data('currency') || 'COP').toString();

        return {
            selected: !!id,
            id: id,
            balance: isNaN(balanceRaw) ? 0 : balanceRaw,
            currency: currency,
            name: ($selected.text() || '').trim()
        };
    }

    function formatAccountMoney(amount, currency) {
        const value = parseFloat(amount || 0);
        return (currency || 'COP') + ' ' + value.toLocaleString('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateAccountImpactSummaries() {
        const type = getTransactionType();
        const amount = parseFloat($('#amount').val()) || 0;
        const source = getSelectedAccountData('#source_account_id');
        const destination = getSelectedAccountData('#destination_account_id');

        const $sourceImpact = $('#source-account-impact');
        const $destinationImpact = $('#destination-account-impact');

        const buildImpactHtml = function(label, current, estimated, currency, mode) {
            const trendClass = mode === 'down' ? 'is-down' : 'is-up';
            const trendSymbol = mode === 'down' ? '↓' : '↑';
            return ''
                + '<strong>' + label + '</strong>'
                + '<span>Saldo actual: <b>' + formatAccountMoney(current, currency) + '</b></span>'
                + '<span class="aura-account-impact__estimate ' + trendClass + '">Estimado: <b>' + formatAccountMoney(estimated, currency) + '</b> ' + trendSymbol + '</span>';
        };

        $sourceImpact.hide().removeClass('has-impact').empty();
        $destinationImpact.hide().removeClass('has-impact').empty();

        if (type === 'expense' && source.selected) {
            const estimated = source.balance - amount;
            const label = amount > 0 ? 'Impacto estimado al guardar (egreso)' : 'Selecciona un monto para estimar impacto';
            $sourceImpact.html(buildImpactHtml(label, source.balance, estimated, source.currency, 'down')).addClass('has-impact').show();
        }

        if (type === 'income' && destination.selected) {
            const estimated = destination.balance + amount;
            const label = amount > 0 ? 'Impacto estimado al guardar (ingreso)' : 'Selecciona un monto para estimar impacto';
            $destinationImpact.html(buildImpactHtml(label, destination.balance, estimated, destination.currency, 'up')).addClass('has-impact').show();
        }
    }

    function updateContextNote() {
        const type = getTransactionType();
        const areaId = getSelectedAreaId();
        const areaName = getSelectedAreaName();
        const hasBudgetField = $('#aura-budget-category-field').is(':visible');

        const title = type === 'income' ? 'Ingreso' : (type === 'capital' ? 'Gasto de Capital' : 'Egreso');
        let message = type === 'income'
            ? 'Selecciona la cuenta destino para registrar el dinero que entra. Si el área tiene presupuesto activo, podrás asignar una categoría del presupuesto.'
            : 'Selecciona la cuenta origen para registrar el dinero que sale. Si la transacción corresponde a reembolso personal, el sistema permite usar un usuario vinculado.';

        if (areaId && areaName) {
            message += ' Área seleccionada: ' + areaName + '.';
        }

        if (type === 'expense' && !hasBudgetField) {
            message += ' La categoría del presupuesto solo aparecerá cuando el área tenga presupuestos activos.';
        }

        const sourceName = $('#source_account_id option:selected').text().trim();
        const destinationName = $('#destination_account_id option:selected').text().trim();

        if (type === 'expense' && $('#source_account_id').val() && sourceName) {
            message += ' Cuenta origen seleccionada: ' + sourceName + '.';
        }

        if (type === 'income' && $('#destination_account_id').val() && destinationName) {
            message += ' Cuenta destino seleccionada: ' + destinationName + '.';
        }

        $('#aura-transaction-context-note').html('<strong>' + title + '</strong>' + message);
    }

    function updateAccountGuidance(type) {
        const isIncome = type === 'income';
        const isExpense = type === 'expense' || type === 'capital';

        $('#source-account-help').text(
            isExpense
                ? 'Obligatoria para egresos. Desde aquí saldrá el dinero.'
                : 'No aplica para ingresos; el sistema usará la cuenta destino.'
        );

        $('#destination-account-help').text(
            isIncome
                ? 'Obligatoria para ingresos. Aquí entrará el dinero.'
                : 'No aplica para egresos; el sistema usará la cuenta origen.'
        );

        $('#source_account_id').closest('.aura-account-field').toggleClass('is-hidden-by-type', isIncome);
        $('#destination_account_id').closest('.aura-account-field').toggleClass('is-hidden-by-type', !isIncome);
    }

    function updateFormCompletion() {
        const budgetFieldVisible = $('#aura-budget-category-field').is(':visible');
        const sourceFieldVisible = $('.aura-account-source').is(':visible');
        const destinationFieldVisible = $('.aura-account-destination').is(':visible');

        const checks = [
            !!getTransactionType(),
            !!$('#transaction_date').val(),
            !!($('#expense_category_id').val() && parseInt($('#expense_category_id').val(), 10) > 0),
            !!($('#amount').val() && parseFloat($('#amount').val()) > 0),
            !!($('#description').val() && $('#description').val().trim().length >= 10),
            !!$('#payment_method').val(),
            (!budgetFieldVisible || !!$('#category_id').val()),
            (!sourceFieldVisible || !!$('#source_account_id').val()),
            (!destinationFieldVisible || !!$('#destination_account_id').val())
        ];

        const done = checks.filter(Boolean).length;
        const percent = Math.round((done / checks.length) * 100);

        $('#aura-tx-progress-text').text(percent + '%');
        $('#aura-tx-progress-bar').css('width', percent + '%');
        $('.aura-progress-track').attr('aria-valuenow', String(percent));
    }

    /**
     * Actualizar la tarjeta de vista previa en tiempo real
     */
    function updateLivePreview() {
        const $preview = $('.aura-transaction-preview');
        if (!$preview.length) return;

        const rawAmount = parseFloat($('#amount').val()) || 0;
        $preview.find('.amount-value').text(rawAmount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        const dateVal = $('#transaction_date').val() || '';
        $preview.find('.date-text').text(dateVal || '—');

        const areaName = getSelectedAreaName();
        const $areaEl = $preview.find('.preview-area');
        if (areaName && areaName !== 'General (sin área)') {
            $areaEl.find('.area-name').text(areaName);
            $areaEl.show();
        } else {
            $areaEl.hide();
        }

        const expCatText = $('#expense_category_id option:selected').text().replace(/^[\s\u00a0—]+/, '').trim();
        const $expCatEl = $preview.find('.preview-expense-category');
        if ($('#expense_category_id').val() && expCatText) {
            $expCatEl.find('.expense-category-name').text(expCatText);
            $expCatEl.show();
        } else {
            $expCatEl.find('.expense-category-name').text('Sin categoría');
        }

        const presCatText = $('#category_id option:selected').text().replace('💰 ', '').trim();
        const $presCatEl = $preview.find('.preview-category');
        if ($('#aura-budget-category-field').is(':visible') && $('#category_id').val() && presCatText) {
            $presCatEl.find('.category-name').text(presCatText);
            $presCatEl.show();
        } else {
            $presCatEl.hide();
        }

        const paymentText = $('#payment_method option:selected').text().trim();
        const $paymentEl = $preview.find('.preview-payment');
        if ($('#payment_method').val() && paymentText && paymentText !== 'Seleccionar...') {
            $paymentEl.find('.payment-name').text(paymentText);
            $paymentEl.show();
        } else {
            $paymentEl.hide();
        }

        const recipient = $('#recipient_payer').val().trim();
        const $recipEl = $preview.find('.preview-recipient');
        if (recipient) {
            $recipEl.find('.recipient-name').text(recipient);
            $recipEl.show();
        } else {
            $recipEl.hide();
        }

        const ref = $('#reference_number').val().trim();
        const $refEl = $preview.find('.preview-reference');
        if (ref) {
            $refEl.find('.reference-text').text(ref);
            $refEl.show();
        } else {
            $refEl.hide();
        }

        const desc = $('#description').val().trim();
        $preview.find('.description-text').text(desc || 'Sin descripción');

        const tags = $('#tags').val().trim();
        const $tagsEl = $preview.find('.preview-tags');
        if (tags) {
            $tagsEl.find('.tags-list').text(tags);
            $tagsEl.show();
        } else {
            $tagsEl.hide();
        }

        const receiptVal = $('#receipt_file_url').val() || '';
        const $receiptEl = $preview.find('.preview-receipt');
        if ($receiptEl.length) {
            if (typeof attachedReceipts !== 'undefined' && attachedReceipts && attachedReceipts.length > 0) {
                const count = attachedReceipts.length;
                const anyDrive = attachedReceipts.some(function(r) { return r.is_drive; });
                $receiptEl.find('.dashicons')
                    .removeClass('dashicons-paperclip dashicons-cloud')
                    .addClass(anyDrive ? 'dashicons-cloud' : 'dashicons-paperclip');
                
                if (count === 1) {
                    const first = attachedReceipts[0];
                    $receiptEl.find('.receipt-text').text(
                        first.is_drive ? ('Comprobante en Drive ☁️: ' + (first.name || 'Archivo')) : ('Comprobante: ' + (first.name || 'Archivo'))
                    );
                } else {
                    $receiptEl.find('.receipt-text').text(count + ' comprobantes adjuntos ' + (anyDrive ? '☁️' : '📎'));
                }
                $receiptEl.css('display', 'flex').show();
            } else if (receiptVal) {
                const isDrive = String(receiptVal).indexOf('drive.google.com') !== -1;
                $receiptEl.find('.dashicons')
                    .removeClass('dashicons-paperclip dashicons-cloud')
                    .addClass(isDrive ? 'dashicons-cloud' : 'dashicons-paperclip');
                $receiptEl.find('.receipt-text').text(
                    isDrive ? 'Comprobante en Google Drive ☁️' : ('Comprobante: ' + receiptVal.split('/').pop())
                );
                $receiptEl.css('display', 'flex').show();
            } else {
                $receiptEl.hide();
            }
        }
    }

    function initSmartInteractions() {
        const type = getTransactionType();

        $(document).on('change', '#transaction_area_id', function() {
            const areaId = getSelectedAreaId();
            const selectedBudgetCategory = String($('#category_id').val() || $('#category_id').data('original') || '');

            areaBudgetCategoriesMap = {};
            activeAreaHasBudgetCategories = false;
            hideBudgetBanner();

            if (!areaId) {
                toggleBudgetCategoryField(false);
                loadCategories(type, selectedBudgetCategory);
                updateContextNote();
                updateFormCompletion();
                updateLivePreview();
                return;
            }

            loadCategoriesForArea(areaId, type, selectedBudgetCategory);
        });

        $(document).on('change', '#category_id', function() {
            const areaId = getSelectedAreaId();
            const catId = parseInt($(this).val(), 10) || 0;

            if (areaId && catId) {
                renderBudgetBanner(areaId, catId);
            } else {
                hideBudgetBanner();
            }

            updateFormCompletion();
            checkOverspend();
            updateContextNote();
            updateLivePreview();
        });

        $(document).on('change', '#expense_category_id', function() {
            updateExpenseCategoryDescHint();
            updateLivePreview();
            updateFormCompletion();
        });

        $(document).on('change', '#source_account_id, #destination_account_id, #payment_method', function() {
            updateAccountSelectionState();
            updateAccountImpactSummaries();
            updateContextNote();
            updateFormCompletion();
            updateLivePreview();
        });

        $(document).on('input change', '#amount', function() {
            updateAccountImpactSummaries();
            checkOverspend();
            updateFormCompletion();
            updateLivePreview();
        });

        $('#aura-transaction-edit-form').on('input change', 'input, select, textarea', function() {
            updateFormCompletion();
            updateContextNote();
            updateLivePreview();
        });
    }

    function fmtNum(n) {
        return parseFloat(n || 0).toLocaleString('es', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }
    
    /**
     * Inicializar datepicker
     */
    function initDatepicker() {
        $('.aura-datepicker').datepicker({
            dateFormat: 'dd/mm/yy',
            changeMonth: true,
            changeYear: true,
            yearRange: '-10:+0',
            maxDate: 0,
            onSelect: function() {
                $(this).trigger('change');
                updateLivePreview();
            }
        });
    }
    
    /**
     * Inicializar detección de cambios en campos
     */
    function initFieldChangeDetection() {
        $('input[data-original], textarea[data-original], select[data-original]').on('input change', function() {
            const $field = $(this);
            const fieldName = $field.attr('name');
            if (!fieldName) return;

            let currentValue = String($field.val() || '').trim();
            let originalValue = String($field.data('original') !== undefined ? $field.data('original') : '').trim();
            
            // Si es monto, comparar numéricamente
            if (fieldName === 'amount') {
                const curNum = parseFloat(currentValue) || 0;
                const origNum = parseFloat(originalValue) || 0;
                if (Math.abs(curNum - origNum) > 0.001) {
                    markFieldAsChanged($field, fieldName, originalValue, currentValue);
                } else {
                    unmarkFieldAsChanged($field, fieldName);
                }
            } else {
                if (currentValue !== originalValue) {
                    markFieldAsChanged($field, fieldName, originalValue, currentValue);
                } else {
                    unmarkFieldAsChanged($field, fieldName);
                }
            }
            
            updateChangesSummary();
            checkSignificantChanges();
            updateLivePreview();
        });
        
        $('#description, #change_reason').on('input', updateCharCounters);
    }
    
    /**
     * Marcar campo como modificado
     */
    function markFieldAsChanged($field, fieldName, oldValue, newValue) {
        const $formField = $field.closest('.aura-form-field');
        $formField.addClass('field-changed');
        $formField.find('.change-indicator').show();
        
        detectedChanges[fieldName] = {
            label: getFieldLabel(fieldName),
            oldValue: oldValue,
            newValue: newValue
        };
    }
    
    /**
     * Desmarcar campo como modificado
     */
    function unmarkFieldAsChanged($field, fieldName) {
        const $formField = $field.closest('.aura-form-field');
        $formField.removeClass('field-changed');
        $formField.find('.change-indicator').hide();
        
        delete detectedChanges[fieldName];
    }
    
    /**
     * Obtener etiqueta legible del campo en español
     */
    function getFieldLabel(fieldName) {
        const labels = {
            'transaction_date':       'Fecha de Transacción',
            'category_id':            'Categoría del Presupuesto',
            'expense_category_id':    'Categoría del Gasto',
            'amount':                 'Monto',
            'payment_method':         'Método de Pago',
            'source_account_id':      'Cuenta Origen',
            'destination_account_id': 'Cuenta Destino',
            'area_id':                'Área / Programa',
            'description':            'Descripción',
            'reference_number':       'Número de Referencia',
            'recipient_payer':        'Beneficiario / Proveedor / Tercero',
            'third_party_id':         'Beneficiario / Proveedor / Tercero',
            'related_user_id':        'Usuario Vinculado',
            'related_user_concept':   'Concepto de Vinculación',
            'notes':                  'Notas Internas',
            'tags':                   'Etiquetas',
            'receipt_file':           'Comprobante',
            'status':                 'Estado',
            'type':                   'Tipo de Transacción',
            'change_reason':          'Motivo de la Modificación'
        };
        
        return labels[fieldName] || (auraTransactionEdit.labels && auraTransactionEdit.labels[fieldName]) || fieldName;
    }
    
    /**
     * Actualizar resumen de cambios en sidebar
     */
    function updateChangesSummary() {
        const changesCount = Object.keys(detectedChanges).length;
        
        if (changesCount === 0) {
            $('#changes-summary').hide();
            return;
        }
        
        $('#changes-summary').show();
        $('#changes-count').text(changesCount);
        
        const $changesList = $('#changes-list');
        $changesList.empty();
        
        $.each(detectedChanges, function(fieldName, change) {
            const emptyLabel = (auraTransactionEdit.labels && auraTransactionEdit.labels.empty) || '(vacío)';
            let displayOldValue = change.oldValue || emptyLabel;
            let displayNewValue = change.newValue || emptyLabel;
            
            if (fieldName === 'amount') {
                displayOldValue = '$ ' + (parseFloat(change.oldValue) || 0).toLocaleString('es-CO', {minimumFractionDigits: 2});
                displayNewValue = '$ ' + (parseFloat(change.newValue) || 0).toLocaleString('es-CO', {minimumFractionDigits: 2});
            } else if (fieldName === 'category_id' || fieldName === 'expense_category_id') {
                const oldCatId = String(change.oldValue);
                const newCatId = String(change.newValue);
                displayOldValue = categoriesCache[oldCatId] || $('#' + fieldName + ' option[value="' + oldCatId + '"]').text().trim() || (change.oldValue ? 'Categoría seleccionada' : emptyLabel);
                displayNewValue = categoriesCache[newCatId] || $('#' + fieldName + ' option[value="' + newCatId + '"]').text().trim() || (change.newValue ? 'Categoría seleccionada' : emptyLabel);
            } else if (fieldName === 'source_account_id' || fieldName === 'destination_account_id') {
                const selector = '[name="' + fieldName + '"]';
                const oldText = $(selector + ' option[value="' + String(change.oldValue) + '"]').text().trim();
                const newText = $(selector + ' option[value="' + String(change.newValue) + '"]').text().trim();
                
                displayOldValue = (!change.oldValue || oldText.toLowerCase().indexOf('seleccionar') !== -1) ? emptyLabel : oldText;
                displayNewValue = (!change.newValue || newText.toLowerCase().indexOf('seleccionar') !== -1) ? emptyLabel : newText;
            } else if (fieldName === 'area_id') {
                const selector = '[name="' + fieldName + '"]';
                const oldText = $(selector + ' option[value="' + String(change.oldValue) + '"]').text().trim();
                const newText = $(selector + ' option[value="' + String(change.newValue) + '"]').text().trim();
                
                displayOldValue = (!change.oldValue || String(change.oldValue) === '0') ? 'General (sin área)' : (oldText || 'Área asignada');
                displayNewValue = (!change.newValue || String(change.newValue) === '0') ? 'General (sin área)' : (newText || 'Área asignada');
            } else if (fieldName === 'third_party_id') {
                const origTpName = $('#third_party_id').data('original-name') || $('#recipient_payer').data('original') || '';
                const curTpName = $('#third_party_id').data('tp-name') || $('#aura-tp-preview-text').text().trim() || $('#recipient_payer').val().trim();
                
                displayOldValue = (change.oldValue && origTpName) ? origTpName : (change.oldValue ? 'Tercero asignado' : emptyLabel);
                displayNewValue = (change.newValue && curTpName) ? curTpName : (change.newValue ? curTpName : emptyLabel);
            } else if (fieldName === 'related_user_id') {
                const origUserName = $('#related_user_id').data('original-name') || $('#related_user_search').data('original') || '';
                let curUserName = $('#related_user_id').data('user-name') || $('#related_user_search').val().trim() || $('#aura-user-name strong').text().trim() || $('#aura-user-name').text().trim();
                
                curUserName = curUserName.replace(/\s+/g, ' ').trim();
                
                displayOldValue = (change.oldValue && origUserName) ? origUserName : (change.oldValue ? 'Usuario asignado' : emptyLabel);
                displayNewValue = (change.newValue && curUserName) ? curUserName : (change.newValue ? 'Usuario seleccionado' : emptyLabel);
            } else if (fieldName === 'related_user_concept') {
                const conceptMap = {
                    'salary':                'Pago de Salario / Nómina',
                    'payment_to_user':       'Pago a Usuario',
                    'charge_to_user':        'Cobro a Usuario',
                    'scholarship':           'Beca Asignada',
                    'loan_payment':          'Pago de Préstamo',
                    'refund':                'Reembolso',
                    'expense_reimbursement': 'Reembolso de Gastos',
                    'unlinked':              'Desvinculado',
                    'none':                  'Ninguno'
                };
                const oldOpt = $('#related_user_concept option[value="' + String(change.oldValue) + '"]').text().trim();
                const newOpt = $('#related_user_concept option[value="' + String(change.newValue) + '"]').text().trim();
                
                displayOldValue = change.oldValue ? (conceptMap[change.oldValue] || (oldOpt && !oldOpt.startsWith('—') ? oldOpt : change.oldValue)) : emptyLabel;
                displayNewValue = change.newValue ? (conceptMap[change.newValue] || (newOpt && !newOpt.startsWith('—') ? newOpt : change.newValue)) : emptyLabel;
            } else if (fieldName === 'payment_method') {
                const methodMap = {
                    'cash':           'Efectivo',
                    'transfer':       'Transferencia',
                    'bank_transfer':  'Transferencia Bancaria',
                    'card':           'Tarjeta',
                    'credit_card':    'Tarjeta de Crédito',
                    'debit_card':     'Tarjeta de Débito',
                    'check':          'Cheque',
                    'digital_wallet': 'Billetera Digital',
                    'other':          'Otro'
                };
                const oldOpt = $('#payment_method option[value="' + String(change.oldValue) + '"]').text().trim();
                const newOpt = $('#payment_method option[value="' + String(change.newValue) + '"]').text().trim();
                
                displayOldValue = change.oldValue ? (methodMap[change.oldValue] || (oldOpt && !oldOpt.startsWith('—') ? oldOpt : change.oldValue)) : emptyLabel;
                displayNewValue = change.newValue ? (methodMap[change.newValue] || (newOpt && !newOpt.startsWith('—') ? newOpt : change.newValue)) : emptyLabel;
            } else if (fieldName === 'status') {
                const statusMap = {
                    'pending':  'Pendiente',
                    'approved': 'Aprobada',
                    'rejected': 'Rechazada'
                };
                displayOldValue = statusMap[change.oldValue] || change.oldValue;
                displayNewValue = statusMap[change.newValue] || change.newValue;
            } else if (fieldName === 'type') {
                const typeMap = {
                    'income':  'Ingreso',
                    'expense': 'Egreso',
                    'capital': 'Gasto de Capital'
                };
                displayOldValue = typeMap[change.oldValue] || change.oldValue;
                displayNewValue = typeMap[change.newValue] || change.newValue;
            } else if (fieldName === 'receipt_file') {
                const formatReceiptVal = function(val) {
                    if (!val) return emptyLabel;
                    try {
                        const parsed = JSON.parse(val);
                        if (Array.isArray(parsed)) {
                            if (parsed.length === 0) return emptyLabel;
                            if (parsed.length === 1) {
                                return (parsed[0].is_drive ? '☁️ ' : '📎 ') + (parsed[0].name || 'Comprobante');
                            }
                            return parsed.length + ' comprobantes adjuntos';
                        }
                    } catch(e) {}
                    if (String(val).indexOf('drive.google.com') !== -1) return '☁️ Google Drive';
                    const parts = String(val).split('/');
                    return '📎 ' + parts[parts.length - 1];
                };
                displayOldValue = formatReceiptVal(change.oldValue);
                displayNewValue = formatReceiptVal(change.newValue);
            }
            
            displayOldValue = String(displayOldValue).trim();
            displayNewValue = String(displayNewValue).trim();
            
            if (displayOldValue.length > 35) displayOldValue = displayOldValue.substring(0, 35) + '...';
            if (displayNewValue.length > 35) displayNewValue = displayNewValue.substring(0, 35) + '...';
            
            const fieldTitle = getFieldLabel(fieldName);
            
            const $item = $('<li>').html(
                '<strong>' + escHtml(fieldTitle) + '</strong>' +
                '<span class="old-value">' + escHtml(displayOldValue) + '</span> ' +
                '<span class="dashicons dashicons-arrow-right-alt"></span> ' +
                '<span class="new-value">' + escHtml(displayNewValue) + '</span>'
            );
            
            $changesList.append($item);
        });
    }
    
    /**
     * Verificar cambios significativos que requieren motivo
     */
    function checkSignificantChanges() {
        let requiresReason = false;
        let reasonMessage = '';
        
        if (detectedChanges.amount) {
            const oldAmount = parseFloat(detectedChanges.amount.oldValue) || 0;
            const newAmount = parseFloat(detectedChanges.amount.newValue) || 0;
            if (oldAmount > 0) {
                const percentChange = Math.abs((newAmount - oldAmount) / oldAmount * 100);
                if (percentChange > significantAmountChange) {
                    requiresReason = true;
                    reasonMessage = (auraTransactionEdit.messages && auraTransactionEdit.messages.significantAmountChange
                        ? auraTransactionEdit.messages.significantAmountChange.replace('%s', percentChange.toFixed(1))
                        : 'El cambio en el monto es significativo (' + percentChange.toFixed(1) + '%). Debes proporcionar un motivo.');
                }
            }
        }
        
        if (requiresReason) {
            $('#change-reason-section').slideDown(200);
            $('#change-reason-message').text(reasonMessage);
            $('#change_reason').prop('required', true);
        } else {
            $('#change-reason-section').slideUp(200);
            $('#change_reason').prop('required', false);
        }
    }
    
    /**
     * Actualizar contadores de caracteres
     */
    function updateCharCounters() {
        $('textarea[minlength]').each(function() {
            const $textarea = $(this);
            const currentLength = $textarea.val().length;
            const minLength = parseInt($textarea.attr('minlength'), 10) || 0;
            const $counter = $textarea.siblings('.char-counter');
            
            if ($counter.length) {
                $counter.text(currentLength + ' / ' + minLength + ' ' + ((auraTransactionEdit.labels && auraTransactionEdit.labels.minChars) || 'caracteres mínimos'));
                $counter.toggleClass('valid', currentLength >= minLength);
            }
        });
    }
    
    /**
     * Inicializar botones de restauración
     */
    function initResetButtons() {
        $('#reset-all-fields').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm((auraTransactionEdit.messages && auraTransactionEdit.messages.confirmResetAll) || '¿Restaurar todos los campos a sus valores originales?')) {
                return;
            }
            
            $('input[data-original], textarea[data-original], select[data-original]').each(function() {
                const $field = $(this);
                const originalValue = $field.data('original');
                $field.val(originalValue).trigger('change');
            });
            
            $('#change_reason').val('');

            const type = getTransactionType();
            toggleAccountFields(type);
            updateAccountGuidance(type);
            updateContextNote();
            updateFormCompletion();
            checkOverspend();
            
            detectedChanges = {};
            updateChangesSummary();
            checkSignificantChanges();
            updateLivePreview();
            
            showMessage((auraTransactionEdit.messages && auraTransactionEdit.messages.allFieldsReset) || 'Todos los campos restaurados.', 'info');
        });
    }
    
    /**
     * Inicializar envío del formulario
     */
    function initFormSubmit() {
        $('#aura-transaction-edit-form').on('submit', function(e) {
            e.preventDefault();
            
            if (Object.keys(detectedChanges).length === 0) {
                showMessage((auraTransactionEdit.messages && auraTransactionEdit.messages.noChanges) || 'No se detectaron cambios.', 'warning');
                return;
            }
            
            if (!validateForm()) {
                return;
            }
            
            if (!confirm((auraTransactionEdit.messages && auraTransactionEdit.messages.confirmSave) || '¿Guardar los cambios realizados?')) {
                return;
            }
            
            const $btn = $('#save-transaction-btn');
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update spin"></span> ' + ((auraTransactionEdit.messages && auraTransactionEdit.messages.saving) || 'Guardando...'));
            
            const txType = getTransactionType();
            // Asegurar que el select de cuenta relevante esté habilitado para que serialize() lo capture siempre
            if (txType === 'income') {
                const $dest = $('#destination_account_id');
                $dest.prop('disabled', false);
                if (!$dest.val() && originalData.destination_account_id) {
                    $dest.val(originalData.destination_account_id);
                }
            } else if (txType === 'expense' || txType === 'capital') {
                const $src = $('#source_account_id');
                $src.prop('disabled', false);
                if (!$src.val() && originalData.source_account_id && !$('#related_user_id').val()) {
                    $src.val(originalData.source_account_id);
                }
            }

            const formData = $(this).serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&nonce=' + auraTransactionEdit.nonce,
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    } else {
                        const mainMsg = response.data.message || 'Error al guardar';
                        const detailedErrors = response.data.errors && response.data.errors.length > 0 ? response.data.errors : [];
                        
                        if (detailedErrors.length > 0) {
                            // Si el mensaje principal ya es idéntico al único error detallado, solo mostrarlo una vez
                            if (detailedErrors.length === 1 && detailedErrors[0] === mainMsg) {
                                showMessage(escHtml(detailedErrors[0]), 'error');
                            } else {
                                let errorsList = '<strong>' + escHtml(mainMsg) + '</strong><ul style="margin:5px 0 0 16px;padding:0;">';
                                detailedErrors.forEach(function(error) {
                                    errorsList += '<li>' + escHtml(error) + '</li>';
                                });
                                errorsList += '</ul>';
                                showMessage(errorsList, 'error');
                            }
                        } else {
                            showMessage(mainMsg, 'error');
                        }
                        
                        $btn.prop('disabled', false);
                        $btn.html('<span class="dashicons dashicons-saved"></span> ' + ((auraTransactionEdit.labels && auraTransactionEdit.labels.saveChanges) || 'Guardar Cambios'));
                    }
                },
                error: function(xhr, status, error) {
                    showMessage(((auraTransactionEdit.messages && auraTransactionEdit.messages.error) || 'Error') + ': ' + error, 'error');
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-saved"></span> ' + ((auraTransactionEdit.labels && auraTransactionEdit.labels.saveChanges) || 'Guardar Cambios'));
                }
            });
        });
    }
    
    /**
     * Validar formulario antes de enviar
     */
    function validateForm() {
        let isValid = true;
        let errors = [];

        const transactionType = getTransactionType();
        const v = auraTransactionEdit.validation || {};
        
        if (!$('#expense_category_id').val()) {
            errors.push(v.expenseCategoryRequired || 'Debes seleccionar la categoría del gasto.');
            isValid = false;
        }
        
        const amount = parseFloat($('#amount').val());
        if (!amount || amount <= 0) {
            errors.push(v.amountRequired || 'El monto debe ser mayor a 0.');
            isValid = false;
        }
        
        if (!$('#transaction_date').val()) {
            errors.push(v.dateRequired || 'La fecha es requerida.');
            isValid = false;
        }
        
        const description = $('#description').val().trim();
        if (description.length < 10) {
            errors.push(v.descriptionMinLength || 'La descripción debe tener al menos 10 caracteres.');
            isValid = false;
        }

        if (transactionType === 'income') {
            const destVal = $('#destination_account_id').val() || originalData.destination_account_id;
            if (!destVal) {
                errors.push(v.destinationAccountRequired || 'En un ingreso debes seleccionar la cuenta destino.');
                isValid = false;
            }
        }

        if (transactionType === 'expense' || transactionType === 'capital') {
            const srcVal = $('#source_account_id').val() || originalData.source_account_id;
            const hasRelatedUser = !!$('#related_user_id').val();
            if (!srcVal && !hasRelatedUser) {
                errors.push(v.sourceAccountRequired || 'En un egreso debes seleccionar la cuenta origen o vincular un usuario.');
                isValid = false;
            }
        }
        
        if ($('#change_reason').prop('required')) {
            const changeReason = $('#change_reason').val().trim();
            if (changeReason.length < 20) {
                errors.push(v.changeReasonRequired || 'Debes proporcionar un motivo del cambio (mínimo 20 caracteres).');
                isValid = false;
            }
        }
        
        if (!isValid) {
            let errorsList = '<ul>';
            errors.forEach(function(error) {
                errorsList += '<li>' + escHtml(error) + '</li>';
            });
            errorsList += '</ul>';
            showMessage(errorsList, 'error');
        }
        
        return isValid;
    }

    function getTransactionType() {
        return $('input[name="transaction_type"]:checked').val() || originalData.transaction_type || 'expense';
    }

    function toggleAccountFields(type) {
        const isIncome = type === 'income';
        const isExpense = type === 'expense' || type === 'capital';

        const $sourceWrap = $('.aura-account-source');
        const $destinationWrap = $('.aura-account-destination');
        const $sourceSelect = $('#source_account_id');
        const $destinationSelect = $('#destination_account_id');

        $sourceWrap.toggle(isExpense).toggleClass('is-hidden-by-type', !isExpense);
        $destinationWrap.toggle(isIncome).toggleClass('is-hidden-by-type', !isIncome);

        $sourceSelect.prop('disabled', !isExpense);
        $destinationSelect.prop('disabled', !isIncome);

        $sourceSelect.prop('required', isExpense);
        $destinationSelect.prop('required', isIncome);

        $sourceWrap.toggleClass('is-active-field', isExpense);
        $destinationWrap.toggleClass('is-active-field', isIncome);

        updateAccountSelectionState();
        updateAccountImpactSummaries();
    }
    
    /**
     * Gestión Interactiva Multi-archivo de Comprobantes (Edición)
     */
    let attachedReceipts = [];

    function initFileUpload() {
        const $manager = $('#aura-receipts-manager');
        if (!$manager.length) return;

        // Cargar comprobantes iniciales
        let initialData = $manager.data('initial');
        if (typeof initialData === 'string') {
            try {
                initialData = JSON.parse(initialData);
            } catch(e) {
                initialData = [];
            }
        }
        attachedReceipts = Array.isArray(initialData) ? initialData : [];

        // Si no vino en data-initial pero hay un valor en #receipt_file_url
        if (attachedReceipts.length === 0) {
            const rawVal = $('#receipt_file_url').val();
            if (rawVal) {
                try {
                    const parsed = JSON.parse(rawVal);
                    if (Array.isArray(parsed)) {
                        attachedReceipts = parsed;
                    }
                } catch(e) {
                    // Si es una sola URL o filename
                    const isDrive = String(rawVal).indexOf('drive.google.com') !== -1;
                    attachedReceipts.push({
                        url: rawVal,
                        name: isDrive ? 'Documento en Google Drive' : rawVal.split('/').pop(),
                        is_drive: isDrive,
                        preview_url: rawVal,
                        download_url: rawVal,
                        thumbnail_url: rawVal,
                        is_pdf: /\.pdf($|\?)/i.test(rawVal),
                        is_img: /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(rawVal)
                    });
                }
            }
        }

        renderAttachedReceiptsList();

        // Eliminar un archivo específico de la lista
        $('#aura-receipts-manager').on('click', '.aura-btn-remove-receipt', function(e) {
            e.preventDefault();
            const idx = parseInt($(this).data('index'), 10);
            if (isNaN(idx) || idx < 0 || idx >= attachedReceipts.length) return;

            const fileToRemove = attachedReceipts[idx];
            const fileName = fileToRemove ? (fileToRemove.name || 'este archivo') : 'este archivo';

            if (!confirm('¿Estás seguro de quitar ' + fileName + ' de esta transacción?')) {
                return;
            }

            attachedReceipts.splice(idx, 1);
            renderAttachedReceiptsList();
            syncAttachedReceiptsValue();
            showMessage('Comprobante quitado de la transacción.', 'info');
        });

        // Subida de uno o varios archivos
        $('#receipt_file_upload_input').on('change', function(e) {
            const files = e.target.files;
            if (!files || files.length === 0) return;

            const filesArray = Array.from(files);
            uploadReceiptFilesSequential(filesArray, 0);
            $(this).val('');
        });
    }

    function renderAttachedReceiptsList() {
        const $list = $('#aura-attached-receipts-list');
        if (!$list.length) return;

        $list.empty();

        if (attachedReceipts.length === 0) {
            $list.hide();
            return;
        }

        $list.css('display', 'flex').show();

        attachedReceipts.forEach(function(rec, idx) {
            let thumbHtml = '';
            if (rec.is_img && rec.thumbnail_url) {
                thumbHtml = '<img src="' + escHtml(rec.thumbnail_url) + '" alt="" style="width:100%;height:100%;object-fit:cover;">';
            } else if (rec.is_pdf) {
                thumbHtml = '<span class="dashicons dashicons-pdf" style="color:#ef4444;font-size:24px;width:24px;height:24px;"></span>';
            } else if (rec.is_drive) {
                thumbHtml = '<span class="dashicons dashicons-cloud" style="color:#2563eb;font-size:24px;width:24px;height:24px;"></span>';
            } else {
                thumbHtml = '<span class="dashicons dashicons-media-document" style="color:#64748b;font-size:24px;width:24px;height:24px;"></span>';
            }

            const badgeHtml = rec.is_drive 
                ? '<span class="aura-badge aura-badge-blue" style="font-size:10.5px;padding:2px 7px;border-radius:10px;display:inline-flex;align-items:center;gap:3px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;"><span class="dashicons dashicons-cloud" style="font-size:12px;width:12px;height:12px;"></span> Google Drive</span>'
                : '<span class="aura-badge" style="font-size:10.5px;padding:2px 7px;border-radius:10px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;">📎 Local</span>';

            const typeLabel = rec.is_pdf ? 'PDF' : (rec.is_img ? 'Imagen' : 'Documento');
            const previewUrl = rec.preview_url || rec.url;

            const $card = $(
                '<div class="aura-receipt-item-card" data-index="' + idx + '" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;transition:all 0.2s ease;">' +
                    '<div style="display:flex;align-items:center;gap:10px;min-width:0;">' +
                        '<div class="receipt-card-thumb-wrap" style="width:40px;height:40px;border-radius:6px;overflow:hidden;background:#e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                            thumbHtml +
                        '</div>' +
                        '<div style="min-width:0;line-height:1.35;">' +
                            '<div class="receipt-card-name" style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:320px;" title="' + escHtml(rec.name || '') + '">' +
                                escHtml(rec.name || 'Comprobante') +
                            '</div>' +
                            '<div style="display:flex;align-items:center;gap:6px;margin-top:2px;">' +
                                badgeHtml +
                                '<span style="font-size:11px;color:#94a3b8;">' + typeLabel + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="receipt-card-actions" style="display:flex;align-items:center;gap:6px;flex-shrink:0;">' +
                        '<a href="' + escHtml(previewUrl) + '" target="_blank" class="button button-small" style="display:inline-flex;align-items:center;gap:4px;" title="Ver comprobante">' +
                            '<span class="dashicons dashicons-visibility"></span> Ver' +
                        '</a>' +
                        '<button type="button" class="button button-small aura-btn-remove-receipt" data-index="' + idx + '" style="color:#ef4444;border-color:#fca5a5;" title="Quitar este comprobante">' +
                            '<span class="dashicons dashicons-trash" style="color:#ef4444;"></span>' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );

            $list.append($card);
        });
    }

    function syncAttachedReceiptsValue() {
        const valToStore = attachedReceipts.length > 0 ? JSON.stringify(attachedReceipts) : '';
        $('#receipt_file_url').val(valToStore).trigger('change');
        updateLivePreview();
    }

    function uploadReceiptFilesSequential(filesArray, currentIndex) {
        if (currentIndex >= filesArray.length) {
            $('.aura-upload-status').hide();
            return;
        }

        const file = filesArray[currentIndex];
        const $status = $('.aura-upload-status');
        const $statusText = $status.find('.upload-status-text');

        if (file.size > 5 * 1024 * 1024) {
            alert('El archivo "' + file.name + '" supera el límite de 5MB.');
            uploadReceiptFilesSequential(filesArray, currentIndex + 1);
            return;
        }

        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
        const isPdfByName = /\.pdf$/i.test(file.name);
        const isImgByName = /\.(jpg|jpeg|png|webp|gif)$/i.test(file.name);

        if (!validTypes.includes(file.type) && !isPdfByName && !isImgByName) {
            alert('Formato no válido para "' + file.name + '". Solo se admiten PDF, JPG y PNG.');
            uploadReceiptFilesSequential(filesArray, currentIndex + 1);
            return;
        }

        $status.show();
        $statusText.text('Subiendo ' + (currentIndex + 1) + ' de ' + filesArray.length + ': ' + file.name + '...');

        const formData = new FormData();
        formData.append('action', 'aura_upload_receipt');
        formData.append('nonce', auraTransactionEdit.transactionNonce);
        formData.append('receipt_file', file);
        formData.append('transaction_date', $('#transaction_date').val() || '');
        const txId = $('#transaction_id').val() || $('input[name="transaction_id"]').val() || (auraTransactionEdit.transaction ? auraTransactionEdit.transaction.id : '') || (new URLSearchParams(window.location.search).get('id')) || '';
        formData.append('transaction_id', txId);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success && response.data) {
                    const isDrive = !!response.data.is_drive || (response.data.file_url && response.data.file_url.indexOf('drive.google.com') !== -1);
                    const fileName = response.data.filename || file.name;
                    const isPdf = !!(response.data.mime_type === 'application/pdf' || /\.pdf($|\?)/i.test(fileName));
                    const isImg = !isPdf && !!(response.data.mime_type && response.data.mime_type.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(fileName));

                    attachedReceipts.push({
                        url: response.data.file_url || response.data.file_path,
                        name: fileName,
                        is_drive: isDrive,
                        file_id: response.data.file_id || '',
                        preview_url: response.data.preview_url || response.data.file_url,
                        download_url: response.data.download_url || response.data.file_url,
                        thumbnail_url: response.data.thumbnail_url || response.data.file_url,
                        is_pdf: isPdf,
                        is_img: isImg
                    });

                    renderAttachedReceiptsList();
                    syncAttachedReceiptsValue();
                    showMessage('Comprobante "' + fileName + '" adjuntado con éxito.', 'success');
                } else {
                    showMessage(response.data && response.data.message ? response.data.message : 'Error al subir comprobante', 'error');
                }

                uploadReceiptFilesSequential(filesArray, currentIndex + 1);
            },
            error: function(xhr, status, error) {
                showMessage('Error de conexión al subir "' + file.name + '": ' + error, 'error');
                uploadReceiptFilesSequential(filesArray, currentIndex + 1);
            }
        });
    }
    
    function showMessage(message, type = 'info') {
        const typeClasses = {
            'success': 'notice-success',
            'error': 'notice-error',
            'warning': 'notice-warning',
            'info': 'notice-info'
        };
        
        const $notice = $('<div>')
            .addClass('notice ' + (typeClasses[type] || 'notice-info') + ' is-dismissible')
            .html('<p>' + message + '</p>');
        
        const $messages = $('#aura-transaction-messages');
        $messages.empty().append($notice);
        
        $('html, body').animate({
            scrollTop: $messages.offset().top - 32
        }, 300);
        
        if (type !== 'error') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    /**
     * Autocomplete usuario vinculado y banner explicativo de concepto en Edición
     */
    function initUserAutocomplete() {
        const $searchInput   = $('#related_user_search');
        const $hiddenId      = $('#related_user_id');
        const $preview       = $('#aura-user-preview');
        const $previewAvatar = $('#aura-user-avatar');
        const $previewName   = $('#aura-user-name');
        const $clearBtn      = $('#aura-user-clear');
        const $conceptSelect = $('#related_user_concept');
        const $conceptBanner = $('#aura-concept-info-banner');
        const $bannerTitle   = $('#aura-concept-banner-title');
        const $bannerDesc    = $('#aura-concept-banner-desc');
        const $bannerIcon    = $conceptBanner.find('.aura-concept-banner-icon');

        const conceptDescriptions = {
            'salary': {
                icon: 'dashicons-id-alt',
                title: '💼 Pago de Salario / Nómina',
                desc: 'Registra el pago de sueldo al colaborador. Este egreso se reflejará en su Libro Mayor Personal (aura-user-ledger), computará en su widget de escritorio de WordPress y en el reporte contable de nómina.'
            },
            'payment_to_user': {
                icon: 'dashicons-admin-users',
                title: '👤 Pago Realizado a un Usuario',
                desc: 'Egreso destinado a un colaborador, proveedor persona natural o tercero registrado en el sistema por servicios, compras directas u honorarios.'
            },
            'charge_to_user': {
                icon: 'dashicons-money-alt',
                title: '💳 Cobro Realizado a un Usuario',
                desc: 'Ingreso recibido de un usuario por concepto de cuotas, mensualidades, matrículas o cobros por servicios internos.'
            },
            'scholarship': {
                icon: 'dashicons-welcome-learn-more',
                title: '🎓 Beca o Subsidio Asignado',
                desc: 'Egreso destinado al financiamiento educativo, auxilio o apoyo económico directo para un estudiante o beneficiario.'
            },
            'loan_payment': {
                icon: 'dashicons-exchange',
                title: '🤝 Pago / Abono de Préstamo',
                desc: 'Movimiento contable asociado al otorgamiento, abono o liquidación total de un préstamo financiero con el usuario.'
            },
            'refund': {
                icon: 'dashicons-undo',
                title: '🔄 Reembolso / Devolución',
                desc: 'Devolución de dinero por cancelaciones, anulaciones o saldos a favor del usuario en el sistema.'
            },
            'expense_reimbursement': {
                icon: 'dashicons-media-document',
                title: '🧾 Reembolso de Gastos Operativos',
                desc: 'Restitución de dinero a un colaborador por gastos institucionales que pagó previamente de su propio bolsillo.'
            }
        };

        function updateConceptBanner(conceptVal) {
            if (conceptVal && conceptDescriptions[conceptVal]) {
                const info = conceptDescriptions[conceptVal];
                $bannerTitle.text(info.title);
                $bannerDesc.text(info.desc);
                if ($bannerIcon.length) {
                    $bannerIcon.attr('class', 'dashicons ' + info.icon + ' aura-concept-banner-icon');
                }
                $conceptBanner.stop(true, true).slideDown(220);
            } else {
                $conceptBanner.stop(true, true).slideUp(180);
            }
        }

        $conceptSelect.on('change', function() {
            updateConceptBanner($(this).val());
        });

        if ($conceptSelect.val()) {
            updateConceptBanner($conceptSelect.val());
        }

        if (!$searchInput.length) return;

        $searchInput.autocomplete({
            minLength: 2,
            delay: 250,
            source: function(request, response) {
                $.ajax({
                    url: auraTransactionEdit.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aura_search_users',
                        nonce: auraTransactionEdit.nonce,
                        term: request.term
                    },
                    success: function(res) {
                        if (res.success && Array.isArray(res.data)) {
                            response(res.data);
                        } else {
                            response([]);
                        }
                    },
                    error: function() { response([]); }
                });
            },
            select: function(event, ui) {
                const loginText = ui.item.login ? ' (@' + ui.item.login + ')' : '';
                const fullUserName = ui.item.name + loginText;
                $searchInput.val(fullUserName).trigger('input');
                $hiddenId.data('user-name', fullUserName).val(ui.item.id).trigger('change');
                $previewAvatar.attr('src', ui.item.avatar_url);
                $previewName.html('<strong>' + ui.item.name + '</strong> <span style="color:#64748b;font-size:12px;">(@' + (ui.item.login || '') + ' · ' + (ui.item.email || '') + ')</span>');
                $preview.show();
                return false;
            }
        }).autocomplete('instance')._renderItem = function(ul, item) {
            const loginBadge = item.login ? ' <span style="color:#2563eb;font-weight:600;font-size:11.5px;">@' + $('<span>').text(item.login).html() + '</span>' : '';
            return $('<li>')
                .append(
                    '<div style="display:flex;align-items:center;gap:10px;padding:6px 8px;">' +
                    '<img src="' + item.avatar_url + '" width="30" height="30" style="border-radius:50%;flex-shrink:0;">' +
                    '<div style="line-height:1.35;">' +
                    '<div><strong>' + $('<span>').text(item.name).html() + '</strong>' + loginBadge + '</div>' +
                    '<div style="color:#64748b;font-size:11.5px;">' + $('<span>').text(item.email).html() + '</div>' +
                    '</div>' +
                    '</div>'
                )
                .appendTo(ul);
        };

        $clearBtn.on('click', function(e) {
            e.preventDefault();
            $searchInput.val('').trigger('input');
            $hiddenId.data('user-name', '').val('').trigger('change');
            $preview.hide();
            $previewAvatar.attr('src', '');
            $previewName.text('');
        });
    }

    /**
     * Selector y Gestor Interactivo de Etiquetas (Edición)
     */
    function initTagsSelector() {
        const $container = $('#aura-tags-manager-box');
        if (!$container.length) return;

        const $hiddenInput = $('#tags');
        const $chipsBox    = $('#aura-selected-tags-chips');
        const $newInput    = $('#aura-tag-new-input');
        const $addBtn      = $('#btn-add-tag-chip');
        const $pills       = $('#aura-available-tags-pills .aura-tag-pill-btn');

        function getSelectedTags() {
            const val = $hiddenInput.val() || '';
            return val.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        }

        function setSelectedTags(tagsArr) {
            const seen = {};
            const unique = [];
            tagsArr.forEach(function(t) {
                const clean = t.replace(/^#+/, '').trim();
                const lower = clean.toLowerCase();
                if (clean && !seen[lower]) {
                    seen[lower] = true;
                    unique.push(clean);
                }
            });

            $hiddenInput.val(unique.join(', ')).trigger('change');
            renderChips(unique);
            syncPills(unique);
            updateLivePreview();
        }

        function renderChips(tagsArr) {
            $chipsBox.empty();
            tagsArr.forEach(function(tag) {
                const safeTag = $('<div>').text(tag).html();
                const $chip = $(
                    '<span class="aura-selected-tag-chip" data-tag="' + safeTag + '">' +
                    '<span class="chip-hash">#</span><span class="chip-text">' + safeTag + '</span>' +
                    '<button type="button" class="btn-remove-tag" title="Eliminar etiqueta">×</button>' +
                    '</span>'
                );
                $chipsBox.append($chip);
            });

            if (tagsArr.length > 0) {
                $chipsBox.css('display', 'flex');
            } else {
                $chipsBox.hide();
            }
        }

        function syncPills(tagsArr) {
            const lowerTags = tagsArr.map(function(t) { return t.toLowerCase(); });
            $pills.each(function() {
                const pillTag = String($(this).data('tag') || '').toLowerCase();
                if (lowerTags.indexOf(pillTag) !== -1) {
                    $(this).addClass('is-selected');
                } else {
                    $(this).removeClass('is-selected');
                }
            });
        }

        function addTagFromInput() {
            const raw = $newInput.val();
            if (!raw || !raw.trim()) return;
            const pieces = raw.split(/[,\n]+/);
            const current = getSelectedTags();
            pieces.forEach(function(p) {
                const clean = p.replace(/^#+/, '').trim();
                if (clean) current.push(clean);
            });
            setSelectedTags(current);
            $newInput.val('');
        }

        // Añadir por botón
        $addBtn.on('click', function(e) {
            e.preventDefault();
            addTagFromInput();
            $newInput.focus();
        });

        // Añadir por teclado (Enter, coma)
        $newInput.on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addTagFromInput();
            } else if (e.key === 'Backspace' && !$newInput.val()) {
                const current = getSelectedTags();
                if (current.length > 0) {
                    current.pop();
                    setSelectedTags(current);
                }
            }
        });

        // Eliminar tag chip al pulsar '×'
        $chipsBox.on('click', '.btn-remove-tag', function(e) {
            e.preventDefault();
            const tagToRemove = $(this).closest('.aura-selected-tag-chip').data('tag');
            const current = getSelectedTags().filter(function(t) {
                return t.toLowerCase() !== String(tagToRemove).toLowerCase();
            });
            setSelectedTags(current);
        });

        // Clic en píldoras existentes
        $container.on('click', '.aura-tag-pill-btn', function(e) {
            e.preventDefault();
            const tag = $(this).data('tag');
            const current = getSelectedTags();
            const lowerTag = String(tag).toLowerCase();
            const existsIndex = current.findIndex(function(t) { return t.toLowerCase() === lowerTag; });

            if (existsIndex !== -1) {
                current.splice(existsIndex, 1);
            } else {
                current.push(tag);
            }
            setSelectedTags(current);
        });

        // Sincronización inicial
        const initial = getSelectedTags();
        renderChips(initial);
        syncPills(initial);

        // Exponer función de sincronización global
        window.auraSyncTagsEditUI = function() {
            const tags = getSelectedTags();
            renderChips(tags);
            syncPills(tags);
        };
    }
    
    $(window).on('beforeunload', function(e) {
        if (Object.keys(detectedChanges).length > 0) {
            const message = (auraTransactionEdit.messages && auraTransactionEdit.messages.unsavedChanges) || 'Tienes cambios sin guardar.';
            e.returnValue = message;
            return message;
        }
    });
    
    $('#aura-transaction-edit-form').on('submit', function() {
        $(window).off('beforeunload');
    });
    
    init();
});
