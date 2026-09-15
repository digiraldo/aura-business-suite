/**
 * JavaScript para el Formulario de Transacciones
 * 
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

(function($) {
    'use strict';
    
    // Variables globales
    let formChanged = false;
    let autoSaveInterval = null;
    let currentCategories = [];
    // Fase 8: mapa de categorías con presupuesto activo para el área actual { catId: catData }
    let areaBudgetCategoriesMap = {};
    let activeAreaHasBudgetCategories = false;
    let currentAreaBudget = null;
    
    /**
     * Inicializar
     */
    $(document).ready(function() {
        initDatepicker();
        initToggleSwitch();
        initFormValidation();
        initFieldMicroInteractions();
        initFileUpload();
        initCollapsible();
        initPreview();
        initAutoSave();
        initUserAutocomplete();
        initThirdPartyAutocomplete();
        initTagsSelector();
        loadCategories('income');
        loadExpenseCategoriesForType('income');
        restoreDraft();
        updateFormCompletion();
        initSectionEntrance();
        $('.aura-transaction-form-wrap').addClass('type-income');

        // Fase 8: estado inicial del campo Categoría del presupuesto
        // Solo se mostrará cuando el área seleccionada tenga presupuesto activo.
        toggleBudgetCategoryField(false);

        // Fase 8: área → recarga categorías y banner de presupuesto
        $(document).on('change', '#transaction_area_id', function () {
            const areaId = parseInt($(this).val()) || 0;
            const type   = $('input[name="transaction_type"]:checked').val() || 'income';
            areaBudgetCategoriesMap = {};
            activeAreaHasBudgetCategories = false;
            hideBudgetBanner();

            // Si no hay área, ocultar el campo de presupuesto y cargar categorías genéricas.
            if (!areaId) {
                toggleBudgetCategoryField(false);
                $('#category_id').val('').html('<option value="">Seleccionar categoría...</option>');
                loadCategories(type);
                updateContextNote();
                return;
            }

            if (areaId) {
                loadCategoriesForArea(areaId, type);
            }

            updateContextNote();
        });

        // Cambios en selección de cuentas para guías visuales en tiempo real
        $(document).on('change', '#source_account_id, #destination_account_id', function () {
            updateAccountSelectionState();
            updateAccountImpactSummaries();
            updateContextNote();
        });

        $(document).on('input change', '#amount', function () {
            updateAccountImpactSummaries();
            checkOverspend();
        });

        // Fase 8: categoría → muestra banner de presupuesto + auto-filtrado inverso de áreas
        $(document).on('change', '#category_id', function () {
            const areaId = getSelectedAreaId();
            const catId  = parseInt($(this).val()) || 0;
            if (areaId && catId) {
                renderBudgetBanner(areaId, catId);
            } else if (areaId && currentAreaBudget) {
                renderAreaGlobalBudgetBanner(currentAreaBudget);
            } else {
                hideBudgetBanner();
            }

            // Auto-filtrado inverso: si no hay área seleccionada, filtrar el dropdown de áreas
            // para mostrar solo las que tienen un presupuesto activo para esta categoría.
            if (!areaId && catId) {
                const budgetedAreas = (auraTransactionData.budgetedAreasByCategory || {})[catId] || null;
                const $areaSelect   = $('#transaction_area_id');
                if ($areaSelect.length) {
                    if (budgetedAreas && budgetedAreas.length) {
                        $areaSelect.find('option').each(function () {
                            const val = parseInt($(this).val()) || 0;
                            if (val === 0) return; // Mantener la opción "Sin área"
                            $(this).toggle(budgetedAreas.indexOf(val) !== -1);
                        });
                        showBudgetBannerWarning(
                            auraTransactionData.messages.areaFilteredByCategory ||
                            'Mostrando áreas con presupuesto para esta categoría.'
                        );
                    } else {
                        // No hay restricción para esta categoría — mostrar todas las áreas
                        $areaSelect.find('option').show();
                    }
                }
            } else if (!catId) {
                // Categoría borrada: restaurar todas las opciones de área
                const $areaSelect = $('#transaction_area_id');
                if ($areaSelect.length) {
                    $areaSelect.find('option').show();
                }
            }
        });

        // Fase 8: monto → advertencia de sobregiro
        $(document).on('input change', '#amount', function () {
            checkOverspend();
        });
        
        // Detectar cambios en el formulario
        $('#aura-transaction-form').on('change input', function() {
            formChanged = true;
            updateFormCompletion();
            updateContextNote();
        });
        
        // Advertir al salir con cambios sin guardar
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return auraTransactionData.messages.confirmLeave;
            }
        });
    });
    
    /**
     * Inicializar Datepicker
     */
    function initDatepicker() {
        $('.aura-datepicker').datepicker({
            dateFormat: 'dd/mm/yy',
            maxDate: 0, // No permitir fechas futuras por defecto
            changeMonth: true,
            changeYear: true,
            yearRange: '-10:+0',
            beforeShow: function(input, inst) {
                setTimeout(function() {
                    inst.dpDiv.css({
                        marginTop: -input.offsetHeight + 'px',
                        marginLeft: input.offsetWidth + 'px'
                    });
                }, 0);
            }
        });
    }
    
    /**
     * Inicializar Toggle Switch
     */
    function initToggleSwitch() {
        $('input[name="transaction_type"]').on('change', function() {
            const type = $(this).val();
            
            // Cambiar clases del contenedor
            const $wrap = $('.aura-transaction-form-wrap');
            $wrap.removeClass('type-income type-expense type-capital').addClass('type-' + type);

            if (type === 'capital') {
                $('#aura-capital-notice').slideDown(250);
            } else {
                $('#aura-capital-notice').slideUp(250);
            }
            
            // Actualizar previsualización
            updatePreview();
            updateContextNote();
            
            // Cargar categorías: primero área si está seleccionada
            const areaId = getSelectedAreaId();
            if (areaId) {
                loadCategoriesForArea(areaId, type);
            } else {
                loadCategories(type);
            }
            // Fase 8.4: recargar categoría del gasto por tipo
            loadExpenseCategoriesForType(type);
            toggleAccountFields(type);
            updateAccountGuidance(type);
        });

        const initialType = $('input[name="transaction_type"]:checked').val() || 'income';
        toggleAccountFields(initialType);
        updateContextNote();
        updateAccountGuidance(initialType);
        updateAccountSelectionState();
        updateAccountImpactSummaries();
    }

    /**
     * Alternar campos de cuenta según el tipo de transacción.
     */
    function toggleAccountFields(type) {
        const isIncome = type === 'income';
        const isExpense = type === 'expense' || type === 'capital'; // Capital requiere cuenta de origen igual que un egreso

        const $sourceWrap = $('.aura-account-source');
        const $destinationWrap = $('.aura-account-destination');
        const $sourceSelect = $('#source_account_id');
        const $destinationSelect = $('#destination_account_id');

        $sourceWrap.toggle(!isIncome).toggleClass('is-hidden-by-type', isIncome);
        $destinationWrap.toggle(!isExpense).toggleClass('is-hidden-by-type', isExpense);

        $sourceSelect.prop('disabled', isIncome);
        $destinationSelect.prop('disabled', isExpense);

        if (isIncome) {
            $sourceSelect.val('');
        }
        if (isExpense) {
            $destinationSelect.val('');
        }

        $sourceSelect.prop('required', isExpense);
        $destinationSelect.prop('required', isIncome);

        $sourceWrap.toggleClass('is-active-field', isExpense);
        $destinationWrap.toggleClass('is-active-field', isIncome);

        updateAccountImpactSummaries();
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
        const type = $('input[name="transaction_type"]:checked').val() || 'income';
        const amount = parseFloat($('#amount').val()) || 0;
        const source = getSelectedAccountData('#source_account_id');
        const destination = getSelectedAccountData('#destination_account_id');

        const $sourceImpact = $('#source-account-impact');
        const $destinationImpact = $('#destination-account-impact');

        const buildImpactHtml = function (label, current, estimated, currency, mode) {
            const trendClass = mode === 'down' ? 'is-down' : 'is-up';
            const trendSymbol = mode === 'down' ? '↓' : '↑';
            return ''
                + '<strong>' + label + '</strong>'
                + '<span>Saldo actual: <b>' + formatAccountMoney(current, currency) + '</b></span>'
                + '<span class="aura-account-impact__estimate ' + trendClass + '">Estimado: <b>' + formatAccountMoney(estimated, currency) + '</b> ' + trendSymbol + '</span>';
        };

        $sourceImpact.hide().removeClass('has-impact').empty();
        $destinationImpact.hide().removeClass('has-impact').empty();

        if ((type === 'expense' || type === 'capital') && source.selected) {
            const estimated = source.balance - amount;
            const label = amount > 0 ? 'Impacto estimado al guardar (' + (type === 'capital' ? 'capital' : 'egreso') + ')' : 'Selecciona un monto para estimar impacto';
            $sourceImpact
                .html(buildImpactHtml(label, source.balance, estimated, source.currency, 'down'))
                .addClass('has-impact')
                .show();
        }

        if (type === 'income' && destination.selected) {
            const estimated = destination.balance + amount;
            const label = amount > 0 ? 'Impacto estimado al guardar (ingreso)' : 'Selecciona un monto para estimar impacto';
            $destinationImpact
                .html(buildImpactHtml(label, destination.balance, estimated, destination.currency, 'up'))
                .addClass('has-impact')
                .show();
        }
    }

    function updateContextNote() {
        const type = $('input[name="transaction_type"]:checked').val() || 'income';
        const areaId = getSelectedAreaId();
        const areaName = getSelectedAreaName();
        const hasBudgetField = $('#aura-budget-category-field').is(':visible');

        let title = type === 'income' ? 'Ingreso' : (type === 'capital' ? 'Gasto de Capital' : 'Egreso');
        let message = type === 'income'
            ? 'Selecciona la cuenta destino para registrar el dinero que entra. Si el área tiene presupuesto activo, podrás asignar una categoría del presupuesto.'
            : (type === 'capital' 
                ? 'Selecciona la cuenta origen de donde saldrá el dinero. Recuerda que este tipo de transacción no afectará los porcentajes del presupuesto operacional.' 
                : 'Selecciona la cuenta origen para registrar el dinero que sale. Si la transacción corresponde a reembolso personal, el sistema permite usar un usuario vinculado.');

        if (areaId && areaName) {
            message += ' Área seleccionada: ' + areaName + '.';
        }

        if (type === 'expense' && !hasBudgetField) {
            message += ' La categoría del presupuesto solo aparecerá cuando el área tenga presupuestos activos.';
        }

        const sourceName = $('#source_account_id option:selected').text().trim();
        const destinationName = $('#destination_account_id option:selected').text().trim();

        if ((type === 'expense' || type === 'capital') && $('#source_account_id').val() && sourceName) {
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
        $('#destination_account_id').closest('.aura-account-field').toggleClass('is-hidden-by-type', isExpense);
    }
    
    /**
     * Cargar categorías por tipo
     */
    function loadCategories(type) {
        const $select = $('#category_id');
        $select.html('<option value="">Cargando...</option>').prop('disabled', true);
        
        $.ajax({
            url: auraTransactionData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_get_categories_by_type',
                nonce: auraTransactionData.nonce,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    currentCategories = response.data.categories;
                    renderCategoriesSelect(response.data.categories);
                } else {
                    showMessage(response.data.message || 'Error al cargar categorías', 'error');
                }
            },
            error: function() {
                showMessage('Error de conexión al cargar categorías', 'error');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    }
    
    /**
     * Fase 8.4: Cargar TODAS las categorías para el selector "Categoría del gasto".
     * Filtra por tipo (income/expense) igual que el selector de presupuesto.
     */
    function loadExpenseCategoriesForType(type) {
        const $select = $('#expense_category_id');
        if (!$select.length) return;

        $select.html('<option value="">Cargando...</option>').prop('disabled', true);

        $.ajax({
            url: auraTransactionData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_get_categories_by_type',
                nonce: auraTransactionData.nonce,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    renderExpenseCategoriesSelect(response.data.categories);
                } else {
                    $select.html('<option value="">Error al cargar</option>');
                }
            },
            error: function() {
                $select.html('<option value="">Error de conexión</option>');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    }

    /**
     * Renderizar el select de "Categoría del gasto" con todas las categorías.
     */
    function renderExpenseCategoriesSelect(categories, level) {
        level = level || 0;
        const $select = $('#expense_category_id');

        if (level === 0) {
            $select.html('<option value="">Seleccionar categoría del gasto...</option>');
        }

        (categories || []).forEach(function(category) {
            const indent = '\u00a0\u00a0'.repeat(level * 2);
            const $opt = $('<option></option>')
                .val(category.id)
                .html(indent + category.name)
                .attr('data-description', category.description || '')
                .data('category', category);
            $select.append($opt);

            if (category.children && category.children.length > 0) {
                renderExpenseCategoriesSelect(category.children, level + 1);
            }
        });

        if (level === 0) {
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

    $(document).on('change', '#expense_category_id', function() {
        updateExpenseCategoryDescHint();
    });


    /**
     * Renderizar select de categorías
     */
    function renderCategoriesSelect(categories, level = 0) {
        const $select = $('#category_id');
        
        if (level === 0) {
            $select.html('<option value="">Seleccionar categoría...</option>');
        }
        
        categories.forEach(function(category) {
            const indent = '&nbsp;&nbsp;'.repeat(level * 2);
            const option = $('<option></option>')
                .val(category.id)
                .html(indent + category.name)
                .data('category', category);
            
            $select.append(option);
            
            // Renderizar subcategorías recursivamente
            if (category.children && category.children.length > 0) {
                renderCategoriesSelect(category.children, level + 1);
            }
        });
    }

    /* ----------------------------------------------------------------
     * FASE 8 — Carga dinámica de categorías según área seleccionada
     * -------------------------------------------------------------- */

    /**
     * Obtener el ID del área actualmente seleccionada (select o hidden input)
     */
    function getSelectedAreaId() {
        const $sel = $('#transaction_area_id');
        if ($sel.length) return parseInt($sel.val()) || 0;
        // Área fija (view_own): hidden input con name="area_id"
        const $hidden = $('input[type="hidden"][name="area_id"]');
        return $hidden.length ? (parseInt($hidden.val()) || 0) : 0;
    }

    /**
     * Obtener el nombre del área para la Vista Previa
     */
    function getSelectedAreaName() {
        const $sel = $('#transaction_area_id');
        if ($sel.length) return $sel.find('option:selected').text().trim();
        // Área fija: data-area-name en el hidden input
        const $hidden = $('input[type="hidden"][name="area_id"]');
        return $hidden.length ? ($hidden.data('area-name') || '') : '';
    }

    /**
     * Cargar categorías para un área específica vía aura_get_area_budget_categories.
     */
    function loadCategoriesForArea(areaId, type) {
        const $select = $('#category_id');
        $select.html('<option value="">' + (auraTransactionData.messages.loadingCats || 'Cargando...') + '</option>').prop('disabled', true);
        hideBudgetBanner();

        return $.post(auraTransactionData.ajaxUrl, {
            action  : 'aura_get_area_budget_categories',
            nonce   : auraTransactionData.budgetsNonce,
            area_id : areaId,
            type    : type,
        })
        .done(function (res) {
            if (!res.success) {
                // Fallback a carga genérica
                activeAreaHasBudgetCategories = false;
                currentAreaBudget = null;
                toggleBudgetCategoryField(false);
                loadCategories(type);
                return;
            }

            const data = res.data;
            areaBudgetCategoriesMap = {};
            currentAreaBudget = data.area_budget || null;
            activeAreaHasBudgetCategories = !!(data.has_budgets && data.categories && data.categories.length);

            // Construir mapa catId → datos enriquecidos
            if (data.categories && data.categories.length) {
                data.categories.forEach(function (c) {
                    areaBudgetCategoriesMap[c.id] = c;
                });
            }

            // Filtrar por tipo de transacción activo
            let cats = (data.categories || []).filter(function (c) {
                return !type || c.type === type || !c.type;
            });

            renderCategoriesForArea(cats);

            // Mostrar el campo según exista presupuesto (por categoría o general de área)
            toggleBudgetCategoryField(activeAreaHasBudgetCategories);
            updateContextNote();

            // Si hay presupuesto general de área y aún no se elige categoría, mostrar banner de área de inmediato
            if (currentAreaBudget) {
                renderAreaGlobalBudgetBanner(currentAreaBudget);
            } else if (!activeAreaHasBudgetCategories) {
                // Mostrar advertencia si el área no tiene presupuestos
                showBudgetBannerWarning(auraTransactionData.messages.noBudgetsForArea || 'Esta área no tiene presupuestos asignados para la fecha actual.');
                $('#category_id').val('');
            }
        })
        .fail(function () {
            activeAreaHasBudgetCategories = false;
            currentAreaBudget = null;
            toggleBudgetCategoryField(false);
            loadCategories(type);
            updateContextNote();
        })
        .always(function () {
            $select.prop('disabled', false);
        });
    }

    /**
     * Renderizar el select de categorías con datos del área.
     */
    function renderCategoriesForArea(categories) {
        const $select = $('#category_id');
        $select.html('<option value="">Seleccionar categoría...</option>');

        if (!categories || !categories.length) {
            $select.append('<option value="" disabled>Sin categorías disponibles</option>');
            return;
        }

        categories.forEach(function (cat) {
            const hasBudget = !!(areaBudgetCategoriesMap[cat.id] && areaBudgetCategoriesMap[cat.id].budget_id);
            const label     = hasBudget ? '💰 ' + cat.name : cat.name;
            const $opt      = $('<option></option>')
                .val(cat.id)
                .text(label)
                .data('category', cat)
                .data('has-budget', hasBudget);
            $select.append($opt);
        });
    }

    /**
     * Renderizar el banner de estado del presupuesto general del Área
     */
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

        let html = '<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:6px;padding:10px 14px;font-size:13px;line-height:1.5;">'
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

    /**
     * Renderizar el banner de estado del presupuesto para área + categoría.
     */
    function renderBudgetBanner(areaId, catId) {
        const catData = areaBudgetCategoriesMap[catId];

        if (!catData || !catData.budget_id) {
            // Si la categoría no tiene presupuesto individual, pero el área tiene presupuesto general
            if (currentAreaBudget) {
                renderAreaGlobalBudgetBanner(currentAreaBudget);
                return;
            }
            // Sin presupuesto activo para esta combinación
            const catName = $('#category_id option:selected').text().replace('💰 ', '').trim();
            const areaName = getSelectedAreaName() || $('#transaction_area_id option:selected').text().trim();
            showBudgetBannerWarning(
                (auraTransactionData.messages.noBudgetForCat || 'No hay presupuesto activo para esta categoría en el área seleccionada.')
                + (catName && areaName ? ' (' + catName + ' en ' + areaName + ')' : '')
            );
            return;
        }

        const pct      = parseFloat(catData.percentage || 0);
        const executed = parseFloat(catData.executed   || 0);
        const budget   = parseFloat(catData.budget_amount || 0);
        const avail    = parseFloat(catData.available  || 0);
        const overrun  = parseFloat(catData.overrun    || 0);

        const barColor = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
        const statusIcon = pct > 100 ? '🔴' : (pct >= 90 ? '🟠' : (pct >= 70 ? '🟡' : '💰'));

        const catName  = $('#category_id option:selected').text().replace('💰 ', '').trim();
        const areaName = getSelectedAreaName() || $('#transaction_area_id option:selected').text().trim();

        let html = '<div style="background:#f0f6fc;border:1px solid #72aee6;border-radius:6px;padding:10px 14px;font-size:13px;line-height:1.5;">'
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

        // Verificar sobregiro inmediatamente si ya hay monto
        checkOverspend();
    }

    /**
     * Mostrar banner de advertencia (sin presupuesto)
     */
    function showBudgetBannerWarning(msg) {
        const html = '<div style="background:#fff8e7;border:1px solid #dba617;border-radius:6px;padding:10px 14px;font-size:13px;color:#614200;">'
            + '⚠️ ' + escHtml(msg)
            + '</div>';
        $('#aura-budget-status-banner').html(html).show();
    }

    function hideBudgetBanner() {
        $('#aura-budget-status-banner').hide().html('');
    }

    /**
     * Mostrar u ocultar el campo "Categoría del presupuesto".
     * Solo tiene sentido mostrarlo cuando hay un Área seleccionada.
     */
    function toggleBudgetCategoryField(show) {
        const $field = $('#aura-budget-category-field');
        if (show) {
            $field.show();
        } else {
            $field.hide();
            // Limpiar selección y banner al ocultar
            $('#category_id').val('').html('<option value="">Seleccionar categoría...</option>');
            hideBudgetBanner();
        }
    }

    /**
     * Verificar si el monto ingresado supera el disponible del presupuesto.
     */
    function checkOverspend() {
        const $banner = $('#aura-overspend-warning');
        if (!$banner.length) return;

        const catId   = parseInt($('#category_id').val()) || 0;
        const catData = areaBudgetCategoriesMap[catId];
        let avail     = 0;
        let hasBudget = false;

        if (catData && catData.budget_id) {
            avail     = parseFloat(catData.available || 0);
            hasBudget = true;
        } else if (currentAreaBudget) {
            avail     = parseFloat(currentAreaBudget.available || 0);
            hasBudget = true;
        }

        if (!hasBudget) {
            $banner.hide();
            return;
        }

        const amount = parseFloat($('#amount').val()) || 0;

        if (amount > 0 && amount > avail) {
            $banner.text(
                (auraTransactionData.messages.overspend || '⚠️ Este monto supera el disponible del presupuesto')
                + ' ($' + fmtNum(avail) + ')'
            ).show();
        } else {
            $banner.hide();
        }
    }

    /** Formatear número con separadores de miles */
    function fmtNum(n) {
        return parseFloat(n || 0).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /** Escapar HTML */
    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    /**
     * Inicializar validación del formulario
     */
    function initFormValidation() {
        $('#aura-transaction-form').on('submit', function(e) {
            e.preventDefault();
            
            // Validar campos
            if (!validateForm()) {
                return false;
            }
            
            // Guardar transacción
            saveTransaction();
        });
        
        // Contador de caracteres para descripción
        $('#description').on('input', function() {
            const length = $(this).val().length;
            const $counter = $('.char-counter');
            $counter.text(length + ' / 10 caracteres mínimos');
            
            if (length >= 10) {
                $counter.addClass('valid');
            } else {
                $counter.removeClass('valid');
            }
        });
        
        // Validación en tiempo real del monto
        $('#amount').on('input', function() {
            const value = parseFloat($(this).val());
            if (value <= 0) {
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
    }
    
    /**
     * Validar formulario
     */
    function validateForm() {
        let isValid = true;
        const errors = [];
        const invalidEntries = [];
        let firstInvalidField = null;

        clearValidationSummary();

        function setFieldError($field, message) {
            if (!$field || !$field.length) return;
            $field.addClass('error aura-field-invalid').removeClass('aura-field-success');
            $field.closest('.aura-form-field').removeClass('has-success').addClass('has-error aura-shake');
            setTimeout(function () {
                $field.closest('.aura-form-field').removeClass('aura-shake');
            }, 360);
            invalidEntries.push({
                id: $field.attr('id') || '',
                message: message
            });
            firstInvalidField = firstInvalidField || $field;
        }

        function setFieldSuccess($field) {
            if (!$field || !$field.length) return;
            $field.removeClass('error aura-field-invalid').addClass('aura-field-success');
            $field.closest('.aura-form-field').removeClass('has-error').addClass('has-success');
        }

        // Validar tipo
        const type = $('input[name="transaction_type"]:checked').val();
        if (!type) {
            errors.push('Debe seleccionar un tipo de transacción');
            isValid = false;
        }

        // Validar categoría del gasto (obligatoria)
        const expenseCategoryId = parseInt($('#expense_category_id').val(), 10);
        if (!expenseCategoryId || expenseCategoryId <= 0) {
            const msg = 'Debe seleccionar la categoría del gasto';
            errors.push(msg);
            setFieldError($('#expense_category_id'), msg);
            isValid = false;
        } else {
            setFieldSuccess($('#expense_category_id'));
        }

        // Categoría del presupuesto: opcional (se sugiere pero no bloquea si el área no tiene presupuesto)
        const categoryId = parseInt($('#category_id').val(), 10);
        if (categoryId > 0) {
            setFieldSuccess($('#category_id'));
        } else {
            $('#category_id').removeClass('aura-field-success aura-field-invalid error');
            $('#category_id').closest('.aura-form-field').removeClass('has-success has-error');
        }

        // Validar monto
        const amount = parseFloat($('#amount').val());
        if (!amount || amount <= 0) {
            const msg = 'El monto debe ser mayor a 0';
            errors.push(msg);
            setFieldError($('#amount'), msg);
            isValid = false;
        } else {
            setFieldSuccess($('#amount'));
        }

        // Validar fecha
        const date = $('#transaction_date').val();
        if (!date) {
            const msg = 'La fecha es requerida';
            errors.push(msg);
            setFieldError($('#transaction_date'), msg);
            isValid = false;
        } else {
            setFieldSuccess($('#transaction_date'));
        }

        // Validar descripción
        const description = $('#description').val() || '';
        if (description.length < 10) {
            const msg = 'La descripción debe tener al menos 10 caracteres';
            errors.push(msg);
            setFieldError($('#description'), msg);
            isValid = false;
        } else {
            setFieldSuccess($('#description'));
        }

        // Mostrar errores
        if (!isValid) {
            showValidationSummary(invalidEntries, errors);
            showMessage(errors.join('<br>'), 'error');
            if (firstInvalidField && firstInvalidField.length) {
                firstInvalidField.trigger('focus');
                $('html, body').animate({
                    scrollTop: Math.max(0, firstInvalidField.offset().top - 180)
                }, 250);
            }
        }

        return isValid;
    }
    
    /**
     * Guardar transacción
     */
    function saveTransaction() {
        const $form = $('#aura-transaction-form');
        const $button = $('#btn-save-transaction');
        const originalText = $button.html();
        
        // Deshabilitar botón
        $button.prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> ' + 
            auraTransactionData.messages.saving
        );
        
        // Convertir fecha al formato correcto
        const dateValue = $('#transaction_date').val();
        const dateParts = dateValue.split('/');
        const formattedDate = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0]; // YYYY-MM-DD
        
        // Preparar datos — incluyendo area_id desde el select o el hidden input
        const areaVal = $('#transaction_area_id').val() || $('input[type="hidden"][name="area_id"]').val() || '';

        const formData = {
            action: 'aura_save_transaction',
            nonce: auraTransactionData.nonce,
            transaction_type: $('input[name="transaction_type"]:checked').val(),
            category_id: $('#category_id').val(),
            amount: $('#amount').val(),
            transaction_date: formattedDate,
            description: $('#description').val(),
            payment_method: $('#payment_method').val(),
            source_account_id: $('#source_account_id').val(),
            destination_account_id: $('#destination_account_id').val(),
            reference_number: $('#reference_number').val(),
            recipient_payer: $('#recipient_payer').val(),
            third_party_id: $('#third_party_id').val() || 0,
            related_user_id: $('#related_user_id').val(),
            related_user_concept: $('#related_user_concept').val(),
            notes: $('#notes').val(),
            tags: $('#tags').val(),
            receipt_file: $('#receipt_file_url').val(),
            // Área / Programa (Fase 8.2)
            area_id: areaVal,
            // Categoría detallada del gasto (Fase 8.4)
            expense_category_id: $('#expense_category_id').val()
        };

        $.ajax({
            url: auraTransactionData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    if (!response || typeof response !== 'object') {
                        throw new Error('Respuesta inesperada del servidor.');
                    }
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        localStorage.removeItem('aura_transaction_draft');
                        formChanged = false;
                        showSuccessActions(response.data);
                    } else {
                        const msg = (response.data && response.data.message) ? response.data.message : 'Error al guardar la transacción.';
                        showMessage(msg, 'error');
                        $button.prop('disabled', false).html(originalText);
                    }
                } catch (e) {
                    showMessage('Error inesperado al procesar la respuesta del servidor. Verifica la consola del navegador.', 'error');
                    $button.prop('disabled', false).html(originalText);
                    if (window.console) console.error('AURA saveTransaction:', e, response);
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error de conexión: ' + error, 'error');
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    /**
     * Mostrar acciones después del éxito
     */
    function showSuccessActions(data) {
        const $messages = $('#aura-transaction-messages');
        
        const html = `
            <div class="notice notice-success is-dismissible">
                <p><strong>${data.message}</strong></p>
                <p>
                    <a href="${data.redirect_url}" class="button button-primary">
                        <span class="dashicons dashicons-visibility"></span>
                        Ver Transacciones
                    </a>
                    <button type="button" class="button" id="btn-create-another">
                        <span class="dashicons dashicons-plus"></span>
                        Crear Otra
                    </button>
                </p>
            </div>
        `;
        
        $messages.html(html);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $messages.offset().top - 100
        }, 500);
        
        // Botón crear otra
        $('#btn-create-another').on('click', function() {
            location.reload();
        });
    }
    
    /**
     * Inicializar upload de archivos
     */
    function initFileUpload() {
        $('#receipt_file').on('change', function(e) {
            const file = e.target.files[0];
            
            if (!file) return;
            
            // Validar tipo de archivo
            const allowedTypes = auraTransactionData.allowedFileTypes;
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(fileExtension)) {
                showMessage('Tipo de archivo no permitido. Solo: ' + allowedTypes.join(', '), 'error');
                $(this).val('');
                return;
            }
            
            // Validar tamaño
            if (file.size > auraTransactionData.maxFileSize) {
                showMessage('El archivo excede el tamaño máximo de 5MB', 'error');
                $(this).val('');
                return;
            }
            
            // Comprimir y subir archivo
            compressImage(file, function(processedFile) {
                uploadFile(processedFile);
            });
        });
        
        // Remover archivo
        $('.remove-file').on('click', function() {
            $('#receipt_file').val('');
            $('#receipt_file_url').val('');
            $('.file-preview').hide();
            $('.aura-receipt-filename').text('').attr('title', '');
            $('.preview-image').attr('src', '').hide();
            $('.preview-icon-wrap').hide();
            $('.aura-receipt-view-btn').attr('href', '#');
            $('.file-upload-label').show();
        });
    }
    
    /**
     * Subir archivo
     */
    function uploadFile(file) {
        const formData = new FormData();
        formData.append('action', 'aura_upload_receipt');
        formData.append('nonce', auraTransactionData.nonce);
        formData.append('receipt_file', file);
        formData.append('transaction_date', $('#transaction_date').val() || '');
        
        // Mostrar loading
        $('.file-upload-label .file-label-text').text('Subiendo archivo...');
        
        $.ajax({
            url: auraTransactionData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Guardar URL del archivo (o ruta relativa si es local)
                    const fileVal = response.data.file_path || response.data.file_url;
                    $('#receipt_file_url').val(fileVal);
                    
                    const fileName = response.data.filename || file.name || 'Comprobante';
                    const viewUrl = response.data.view_url || response.data.file_url || response.data.preview_url || '#';
                    const thumbUrl = response.data.thumbnail_url || response.data.file_url || viewUrl;
                    const mimeType = response.data.mime_type || file.type || '';
                    const isPdf = mimeType === 'application/pdf' || /\.pdf$/i.test(fileName);
                    const isImg = (!isPdf && (mimeType.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif)$/i.test(fileName)));
                    const isDrive = !!(response.data.is_drive);

                    // Formatear tamaño de archivo
                    let sizeText = '';
                    if (file.size && file.size > 0) {
                        const bytes = file.size;
                        if (bytes < 1024) sizeText = bytes + ' B';
                        else if (bytes < 1048576) sizeText = (bytes / 1024).toFixed(1) + ' KB';
                        else sizeText = (bytes / 1048576).toFixed(1) + ' MB';
                    }

                    // Actualizar nombre
                    $('.aura-receipt-filename').text(fileName).attr('title', fileName);

                    // Configurar medios (imagen vs icono PDF/Drive/Doc)
                    if (isImg) {
                        $('.preview-image').attr('src', thumbUrl).show();
                        $('.preview-icon-wrap').hide();
                    } else {
                        $('.preview-image').hide();
                        const $icon = $('.preview-icon-wrap').find('.dashicons');
                        $icon.removeClass('dashicons-pdf dashicons-cloud dashicons-media-document');
                        if (isPdf) {
                            $icon.addClass('dashicons-pdf').css('color', '#ef4444');
                        } else if (isDrive) {
                            $icon.addClass('dashicons-cloud').css('color', '#2563eb');
                        } else {
                            $icon.addClass('dashicons-media-document').css('color', '#64748b');
                        }
                        $('.preview-icon-wrap').show();
                    }

                    // Configurar badge de almacenamiento y tipo
                    const $sourceBadge = $('.aura-receipt-source');
                    if (isDrive) {
                        $sourceBadge.html('<span class="dashicons dashicons-cloud" style="font-size:12px;width:12px;height:12px;line-height:12px;vertical-align:middle;"></span> Google Drive')
                                    .removeClass('badge-local')
                                    .addClass('badge-drive');
                    } else {
                        $sourceBadge.html('📎 Local')
                                    .removeClass('badge-drive')
                                    .addClass('badge-local');
                    }

                    $('.aura-receipt-type').text(isPdf ? 'PDF' : (isImg ? 'Imagen' : 'Documento'));
                    $('.aura-receipt-size').text(sizeText ? '· ' + sizeText : '');

                    // Botón ver
                    $('.aura-receipt-view-btn').attr('href', viewUrl);

                    $('.file-preview').show();
                    $('.file-upload-label').hide();
                    
                    showMessage('Archivo subido exitosamente', 'success');
                } else {
                    showMessage(response.data.message || auraTransactionData.messages.uploadError, 'error');
                    $('#receipt_file').val('');
                }
            },
            error: function() {
                showMessage(auraTransactionData.messages.uploadError, 'error');
                $('#receipt_file').val('');
            },
            complete: function() {
                $('.file-upload-label .file-label-text').text('Subir archivo (JPG, PNG, PDF - Max 5MB)');
            }
        });
    }

    /**
     * Comprimir imagen antes de subirla
     */
    function compressImage(file, callback) {
        if (!file.type.startsWith('image/')) {
            callback(file);
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
                    callback(compressedFile);
                }, 'image/jpeg', 0.7); // 70% calidad
            };
        };
    }
    
    /**
     * Inicializar sección colapsable
     */
    function initCollapsible() {
        $('.aura-collapsible-header').on('click', function() {
            const $section  = $(this).closest('.aura-collapsible');
            const $content  = $(this).next('.aura-collapsible-content');
            const $icon     = $(this).find('.dashicons');
            const isOpening = !$section.hasClass('aura-collapsible-open');

            if (isOpening) {
                $section.addClass('aura-collapsible-open');
                // Asignar delays de fila antes de slideDown para que CSS los lea
                $content.find('.aura-form-row').each(function(i) {
                    $(this).css('--aura-row-delay', (i * 60) + 'ms');
                });
                $content.slideDown(300);
            } else {
                $section.removeClass('aura-collapsible-open');
                $content.slideUp(260, function() {
                    // Resetear animación de filas para la próxima apertura
                    $content.find('.aura-form-row').css('--aura-row-delay', '');
                });
            }

            $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });
    }
    
    /**
     * Animaciones de entrada por sección (IntersectionObserver + stagger de filas).
     * No bloquea ninguna funcionalidad en browsers que no soporten IntersectionObserver.
     */
    function initSectionEntrance() {
        $('.aura-transaction-form-wrap').addClass('aura-entrance-ready');

        // --- Secciones del formulario ---
        var $sections = $('.aura-form-section');

        $sections.each(function(sectionIdx) {
            var $section = $(this);
            // Stagger base: cada sección espera un poco más que la anterior,
            // pero solo si está en viewport desde el inicio (las de arriba se ven de inmediato).
            var baseDelay = sectionIdx * 60;
            $section.css('--aura-section-delay', baseDelay + 'ms');

            // Escalonar filas internas
            $section.find('.aura-form-row').each(function(rowIdx) {
                $(this).css('--aura-row-delay', (rowIdx * 55) + 'ms');
            });
        });

        if (typeof IntersectionObserver !== 'undefined') {
            var sectionObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('aura-section-visible');
                        sectionObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.06, rootMargin: '0px 0px -30px 0px' });

            $sections.each(function() {
                sectionObserver.observe(this);
            });
        } else {
            // Fallback: mostrar todas de inmediato
            $sections.addClass('aura-section-visible');
        }

        // --- Panel de vista previa y tips ---
        var $previewCards = $('.aura-transaction-preview .preview-card, .aura-transaction-preview .preview-tips');

        $previewCards.each(function(i) {
            $(this).css('--aura-preview-delay', (120 + i * 80) + 'ms');
        });

        if (typeof IntersectionObserver !== 'undefined') {
            var previewObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('aura-preview-visible');
                        previewObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.04 });

            $previewCards.each(function() {
                previewObserver.observe(this);
            });
        } else {
            $previewCards.addClass('aura-preview-visible');
        }
    }

    /**
     * Inicializar previsualización en tiempo real
     */
    function initPreview() {
        // Actualizar previsualización en tiempo real
        $('#aura-transaction-form').on('input change', function() {
            updatePreview();
        });
    }
    
    /**
     * Actualizar vista previa
     */
    /**
     * Mapa de métodos de pago legibles
     */
    const paymentMethodLabels = {
        cash: 'Efectivo', transfer: 'Transferencia',
        check: 'Cheque', card: 'Tarjeta', other: 'Otro'
    };

    function updatePreview() {
        const type        = $('input[name="transaction_type"]:checked').val();
        const amount      = $('#amount').val() || '0.00';
        const categoryId  = $('#category_id').val();
        const expCatId    = $('#expense_category_id').val();
        const description = $('#description').val() || 'Sin descripción';
        const date        = $('#transaction_date').val() || new Date().toLocaleDateString('es-ES');
        const tags        = $('#tags').val();
        const payment     = $('#payment_method').val();
        const recipient   = $('#recipient_payer').val();
        const reference   = $('#reference_number').val();

        // Tipo
        const $badge = $('.preview-badge .badge-type');
        $badge.removeClass('income expense capital').addClass(type);
        if (type === 'income') {
            $badge.html('<span class="dashicons dashicons-arrow-up-alt"></span> Ingreso');
        } else if (type === 'capital') {
            $badge.html('<span class="dashicons dashicons-building"></span> Gasto de Capital');
        } else {
            $badge.html('<span class="dashicons dashicons-arrow-down-alt"></span> Egreso');
        }

        // Monto
        $('.preview-amount .amount-value').text(parseFloat(amount).toFixed(2));

        // Fecha
        $('.preview-date .date-text').text(date);

        // Área / Programa
        const areaName = getSelectedAreaName();
        if (areaName) {
            $('.preview-area .area-name').text(areaName);
            $('.preview-area').show();
        } else {
            $('.preview-area').hide();
        }

        // Categoría del gasto
        if (expCatId) {
            const expName = $('#expense_category_id option:selected').text().trim();
            if (expName) {
                $('.preview-expense-category .expense-category-name').text(expName);
                $('.preview-expense-category').show();
            } else {
                $('.preview-expense-category').hide();
            }
        } else {
            $('.preview-expense-category').hide();
        }

        // Categoría del presupuesto
        if (categoryId) {
            const categoryName = $('#category_id option:selected').text().replace('💰 ', '').trim();
            $('.preview-category .category-name').text(categoryName);
            $('.preview-category').show();
        } else {
            $('.preview-category').hide();
        }

        // Método de pago
        if (payment) {
            $('.preview-payment .payment-name').text(paymentMethodLabels[payment] || payment);
            $('.preview-payment').show();
        } else {
            $('.preview-payment').hide();
        }

        // Pagador / Beneficiario
        if (recipient) {
            $('.preview-recipient .recipient-name').text(recipient);
            $('.preview-recipient').show();
        } else {
            $('.preview-recipient').hide();
        }

        // Número de referencia
        if (reference) {
            $('.preview-reference .reference-text').text(reference);
            $('.preview-reference').show();
        } else {
            $('.preview-reference').hide();
        }

        // Descripción
        $('.preview-description .description-text').text(description);

        // Etiquetas
        if (tags) {
            $('.preview-tags .tags-list').text(tags);
            $('.preview-tags').show();
        } else {
            $('.preview-tags').hide();
        }
    }

    /**
     * Calcula y muestra el progreso de diligenciamiento del formulario.
     */
    function updateFormCompletion() {
        const budgetFieldVisible = $('#aura-budget-category-field').is(':visible');
        const sourceFieldVisible = $('.aura-account-source').is(':visible');
        const destinationFieldVisible = $('.aura-account-destination').is(':visible');

        const checks = [
            !!$('input[name="transaction_type"]:checked').val(),
            !!$('#transaction_date').val(),
            !!($('#expense_category_id').val() && parseInt($('#expense_category_id').val(), 10) > 0),
            !!($('#amount').val() && parseFloat($('#amount').val()) > 0),
            !!($('#description').val() && $('#description').val().trim().length >= 10),
            !!$('#payment_method').val(),
            !!$('#recipient_payer').val(),
            (!budgetFieldVisible || !!$('#category_id').val()),
            (!sourceFieldVisible || !!$('#source_account_id').val()),
            (!destinationFieldVisible || !!$('#destination_account_id').val())
        ];

        const done = checks.filter(Boolean).length;
        const percent = Math.round((done / checks.length) * 100);
        const $text = $('#aura-tx-progress-text');
        const $bar = $('#aura-tx-progress-bar');
        const $track = $('.aura-progress-track');

        if ($text.length) {
            $text.text(percent + '%');
        }
        if ($bar.length) {
            $bar.css('width', percent + '%');
        }
        if ($track.length) {
            $track.attr('aria-valuenow', String(percent));
        }
    }

    /**
     * Microinteracciones de campos: éxito/limpieza de error en tiempo real.
     */
    function initFieldMicroInteractions() {
        $('#aura-transaction-form').on('input change blur', 'input, select, textarea', function () {
            const $field = $(this);
            const id = $field.attr('id') || '';
            const rawVal = ($field.val() || '').toString().trim();
            let isValid = rawVal.length > 0;

            if (id === 'amount') {
                isValid = !!rawVal && parseFloat(rawVal) > 0;
            }

            if (id === 'description') {
                isValid = rawVal.length >= 10;
            }

            if (id === 'expense_category_id') {
                isValid = !!rawVal && parseInt(rawVal, 10) > 0;
            }

            if (id === 'category_id' && !$('#aura-budget-category-field').is(':visible')) {
                isValid = true;
            }

            if (isValid) {
                $field.removeClass('error aura-field-invalid').addClass('aura-field-success');
                $field.closest('.aura-form-field').removeClass('has-error').addClass('has-success');
            } else if (rawVal.length === 0) {
                $field.removeClass('aura-field-success aura-field-invalid error');
                $field.closest('.aura-form-field').removeClass('has-success has-error');
            }
        });
    }

    /**
     * Crea (si no existe) y devuelve el panel flotante de errores.
     */
    function ensureValidationSummary() {
        let $summary = $('#aura-validation-summary');
        if ($summary.length) return $summary;

        $summary = $('<div id="aura-validation-summary" class="aura-validation-summary is-hidden" role="alert" aria-live="polite"></div>');
        $('#aura-transaction-messages').after($summary);
        return $summary;
    }

    function clearValidationSummary() {
        const $summary = ensureValidationSummary();
        $summary.addClass('is-hidden').removeClass('is-visible').empty();
    }

    function showValidationSummary(invalidEntries, fallbackErrors) {
        const $summary = ensureValidationSummary();
        const items = (invalidEntries || []).filter(function (entry) {
            return entry && entry.message;
        });

        const seen = {};
        const listItems = [];

        items.forEach(function (entry) {
            if (seen[entry.message]) return;
            seen[entry.message] = true;
            const fieldId = entry.id ? (' data-field-id="' + escHtml(entry.id) + '"') : '';
            listItems.push('<li><button type="button" class="aura-validation-link"' + fieldId + '>' + escHtml(entry.message) + '</button></li>');
        });

        if (!listItems.length && Array.isArray(fallbackErrors)) {
            fallbackErrors.forEach(function (message) {
                if (seen[message]) return;
                seen[message] = true;
                listItems.push('<li>' + escHtml(message) + '</li>');
            });
        }

        if (!listItems.length) {
            clearValidationSummary();
            return;
        }

        const html = ''
            + '<div class="aura-validation-summary__title">Revisa estos campos antes de guardar:</div>'
            + '<ul>' + listItems.join('') + '</ul>';

        $summary.html(html).removeClass('is-hidden').addClass('is-visible');

        $summary.off('click', '.aura-validation-link').on('click', '.aura-validation-link', function () {
            const fieldId = $(this).data('fieldId');
            if (!fieldId) return;
            const $field = $('#' + fieldId);
            if (!$field.length) return;
            $field.trigger('focus');
            $('html, body').animate({
                scrollTop: Math.max(0, $field.offset().top - 180)
            }, 220);
        });
    }
    
    /**
     * Inicializar autoguardado
     */
    function initAutoSave() {
        // Guardar borrador cada 30 segundos
        autoSaveInterval = setInterval(function() {
            if (formChanged) {
                saveDraft();
            }
        }, 30000);
        
        // Botón guardar borrador manual
        $('#btn-save-draft').on('click', function() {
            saveDraft();
            showMessage('Borrador guardado', 'info');
        });
        
        // Botón limpiar borrador
        $('#btn-clear-draft').on('click', function() {
            if (confirm('¿Limpiar el formulario? Los cambios no guardados se perderán.')) {
                clearDraft();
                location.reload();
            }
        });
    }
    
    /**
     * Autocomplete usuario vinculado y banner explicativo de concepto (Fase 6, Item 6.1)
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

        // Función para actualizar el banner explicativo
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

        // Listener en cambio de concepto
        $conceptSelect.on('change', function() {
            updateConceptBanner($(this).val());
        });

        // Comprobar estado inicial
        if ($conceptSelect.val()) {
            updateConceptBanner($conceptSelect.val());
        }

        if (!$searchInput.length) return;

        $searchInput.autocomplete({
            minLength: 2,
            delay: 250,
            source: function(request, response) {
                $.ajax({
                    url: auraTransactionData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aura_search_users',
                        nonce: auraTransactionData.nonce,
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
                $searchInput.val(ui.item.name + loginText);
                $hiddenId.val(ui.item.id).trigger('change');
                $previewAvatar.attr('src', ui.item.avatar_url);
                $previewName.html('<strong>' + ui.item.name + '</strong> <span style="color:#64748b;font-size:12px;">(@' + (ui.item.login || '') + ' · ' + (ui.item.email || '') + ')</span>');
                $preview.show();
                formChanged = true;
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

        // Botón limpiar usuario
        $clearBtn.on('click', function(e) {
            e.preventDefault();
            $searchInput.val('');
            $hiddenId.val('').trigger('change');
            $preview.hide();
            $previewAvatar.attr('src', '');
            $previewName.text('');
            formChanged = true;
        });
    }

    /**
     * Autocomplete de Contraparte / Tercero / Empresa / Tienda / Usuario con Agrupación Contable
     */
    function initThirdPartyAutocomplete() {
        const $recipientInput = $('#recipient_payer');
        const $hiddenTpId     = $('#third_party_id');
        const $preview        = $('#aura-tp-preview');
        const $previewAvatar  = $('#aura-tp-preview-avatar');
        const $previewText    = $('#aura-tp-preview-text');
        const $previewBadge   = $('#aura-tp-preview-badge');
        const $clearBtn       = $('#aura-tp-clear');

        if (!$recipientInput.length) return;

        $recipientInput.autocomplete({
            minLength: 1,
            delay: 200,
            source: function(request, response) {
                $.ajax({
                    url: auraTransactionData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aura_search_counterparties',
                        nonce: auraTransactionData.nonce,
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
                const item = ui.item;
                $recipientInput.val(item.commercial_name || item.name || item.value);
                
                if (item.type === 'third_party') {
                    $hiddenTpId.val(item.third_party_id || item.id).trigger('change');
                    
                    if (item.avatar_url) {
                        $previewAvatar.html('<img src="' + item.avatar_url + '" width="24" height="24" style="object-fit:cover;width:100%;height:100%;border-radius:4px;">');
                    } else {
                        $previewAvatar.html('<span class="dashicons ' + (item.icon || 'dashicons-building') + '" style="font-size:16px;width:16px;height:16px;line-height:16px;color:#64748b;"></span>');
                    }
                    
                    const docText = item.document_id ? ' (' + (item.tax_id_type || 'NIT') + ': ' + item.document_id + ')' : '';
                    $previewText.text((item.commercial_name || item.name) + docText);
                    const badgeClass = item.category_badge || 'aura-pill--muted';
                    $previewBadge.attr('class', 'aura-pill ' + badgeClass).text(item.accounting_role_label || item.party_type_label || 'Tercero');
                    $preview.css('display', 'inline-flex');
                } else if (item.type === 'wp_user') {
                    $hiddenTpId.val('');
                    const $relUserId = $('#related_user_id');
                    const $relUserSearch = $('#related_user_search');
                    if ($relUserId.length && !$relUserId.val()) {
                        $relUserId.val(item.user_id).trigger('change');
                        $relUserSearch.val(item.name + (item.login ? ' (@' + item.login + ')' : ''));
                        $('#aura-user-avatar').attr('src', item.avatar_url);
                        $('#aura-user-name').html('<strong>' + item.name + '</strong> <span style="color:#64748b;font-size:12px;">(@' + (item.login || '') + ' · ' + (item.email || '') + ')</span>');
                        $('#aura-user-preview').show();
                    }
                    $preview.hide();
                }
                
                formChanged = true;
                return false;
            }
        });

        const autocomp = $recipientInput.autocomplete('instance');
        if (autocomp) {
            autocomp._renderMenu = function(ul, items) {
                const that = this;
                let currentCategory = "";
                ul.addClass('aura-ac-dropdown-menu');

                $.each(items, function(index, item) {
                    const category = item.category || item.accounting_role_label || 'Terceros';
                    if (category !== currentCategory) {
                        const iconClass = item.icon || 'dashicons-category';
                        ul.append(
                            '<li class="ui-autocomplete-category aura-ac-category-header">' +
                            '<span class="dashicons ' + iconClass + '" style="font-size:14px;width:14px;height:14px;line-height:1;margin-right:6px;vertical-align:middle;"></span>' +
                            $('<span>').text(category).html() +
                            '</li>'
                        );
                        currentCategory = category;
                    }
                    that._renderItemData(ul, item);
                });
            };

            autocomp._renderItem = function(ul, item) {
                let avatarContent = '';
                if (item.avatar_url) {
                    avatarContent = '<img src="' + item.avatar_url + '" width="28" height="28" style="border-radius:6px;object-fit:cover;flex-shrink:0;">';
                } else {
                    avatarContent = '<div style="width:28px;height:28px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="dashicons ' + (item.icon || 'dashicons-building') + '" style="font-size:16px;width:16px;height:16px;color:#64748b;"></span></div>';
                }
                
                const badgeClass = item.category_badge || 'aura-pill--muted';
                const badgeLabel = item.accounting_role_label || item.party_type_label || item.type;
                const badge = '<span class="aura-pill ' + badgeClass + '" style="font-size:10px;padding:2px 7px;font-weight:700;">' + $('<span>').text(badgeLabel).html() + '</span>';
                const sub = item.document_id ? ((item.tax_id_type || 'NIT') + ': ' + item.document_id) : (item.email || '');

                return $('<li class="aura-ac-item">')
                    .append(
                        '<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;">' +
                        avatarContent +
                        '<div style="line-height:1.35;flex-grow:1;min-width:0;">' +
                        '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">' +
                        '<strong style="font-size:13px;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + $('<span>').text(item.commercial_name || item.name).html() + '</strong>' +
                        badge +
                        '</div>' +
                        (sub ? '<div style="color:#64748b;font-size:11.5px;margin-top:2px;">' + $('<span>').text(sub).html() + '</div>' : '') +
                        '</div>' +
                        '</div>'
                    )
                    .appendTo(ul);
            };
        }

        $recipientInput.on('input', function() {
            if ($hiddenTpId.val() && $(this).val().trim() === '') {
                $hiddenTpId.val('');
                $preview.hide();
            }
        });

        $clearBtn.on('click', function(e) {
            e.preventDefault();
            $hiddenTpId.val('');
            $preview.hide();
            formChanged = true;
        });
    }

    /**
     * Guardar borrador en localStorage
     */
    function saveDraft() {
        const draft = {
            transaction_type: $('input[name="transaction_type"]:checked').val(),
            area_id: $('#transaction_area_id').val() || $('input[type="hidden"][name="area_id"]').val() || '',
            category_id: $('#category_id').val(),
            expense_category_id: $('#expense_category_id').val(),
            amount: $('#amount').val(),
            transaction_date: $('#transaction_date').val(),
            description: $('#description').val(),
            payment_method: $('#payment_method').val(),
            source_account_id: $('#source_account_id').val(),
            destination_account_id: $('#destination_account_id').val(),
            reference_number: $('#reference_number').val(),
            recipient_payer: $('#recipient_payer').val(),
            third_party_id: $('#third_party_id').val() || '',
            related_user_id: $('#related_user_id').val(),
            related_user_concept: $('#related_user_concept').val(),
            notes: $('#notes').val(),
            tags: $('#tags').val(),
            timestamp: new Date().getTime()
        };
        
        localStorage.setItem('aura_transaction_draft', JSON.stringify(draft));
    }
    
    /**
     * Restaurar borrador desde localStorage
     */
    function restoreDraft() {
        const draftJson = localStorage.getItem('aura_transaction_draft');
        
        if (!draftJson) return;
        
        try {
            const draft = JSON.parse(draftJson);
            const age = new Date().getTime() - draft.timestamp;
            
            // Solo restaurar si el borrador tiene menos de 24 horas
            if (age > 86400000) {
                localStorage.removeItem('aura_transaction_draft');
                return;
            }
            
            // Preguntar si quiere restaurar
            if (confirm('Se encontró un borrador guardado. ¿Deseas restaurarlo?')) {
                // Restaurar valores
                $('input[name="transaction_type"][value="' + draft.transaction_type + '"]').prop('checked', true).trigger('change');
                $('#amount').val(draft.amount);
                $('#transaction_date').val(draft.transaction_date);
                $('#description').val(draft.description).trigger('input');
                $('#payment_method').val(draft.payment_method);
                $('#reference_number').val(draft.reference_number);
                $('#recipient_payer').val(draft.recipient_payer);
                if (draft.third_party_id) {
                    $('#third_party_id').val(draft.third_party_id);
                }
                if (draft.related_user_id) {
                    $('#related_user_id').val(draft.related_user_id);
                }
                if (typeof draft.source_account_id !== 'undefined') {
                    $('#source_account_id').val(draft.source_account_id);
                }
                if (typeof draft.destination_account_id !== 'undefined') {
                    $('#destination_account_id').val(draft.destination_account_id);
                }
                if (draft.related_user_concept) {
                    $('#related_user_concept').val(draft.related_user_concept);
                }
                $('#notes').val(draft.notes);
                $('#tags').val(draft.tags);
                if (typeof window.auraSyncTagsUI === 'function') {
                    window.auraSyncTagsUI();
                }

                const applySavedCategories = function () {
                    if (typeof draft.expense_category_id !== 'undefined') {
                        $('#expense_category_id').val(draft.expense_category_id).trigger('change');
                    }

                    if (typeof draft.category_id !== 'undefined' && $('#aura-budget-category-field').is(':visible')) {
                        $('#category_id').val(draft.category_id).trigger('change');
                    }

                    updatePreview();
                    updateFormCompletion();
                };

                if (typeof draft.area_id !== 'undefined' && draft.area_id !== '') {
                    const $areaSelect = $('#transaction_area_id');
                    if ($areaSelect.length) {
                        $areaSelect.val(draft.area_id);
                    } else {
                        $('input[type="hidden"][name="area_id"]').val(draft.area_id);
                    }

                    const areaPromise = loadCategoriesForArea(draft.area_id, draft.transaction_type);
                    if (areaPromise && typeof areaPromise.always === 'function') {
                        areaPromise.always(applySavedCategories);
                    } else {
                        applySavedCategories();
                    }
                } else {
                    applySavedCategories();
                }
                
                showMessage('Borrador restaurado', 'info');
            } else {
                localStorage.removeItem('aura_transaction_draft');
            }
        } catch (e) {
            console.error('Error al restaurar borrador:', e);
            localStorage.removeItem('aura_transaction_draft');
        }
    }
    
    /**
     * Limpiar borrador
     */
    function clearDraft() {
        localStorage.removeItem('aura_transaction_draft');
        $('#tags').val('');
        if (typeof window.auraSyncTagsUI === 'function') {
            window.auraSyncTagsUI();
        }
    }
    
    /**
     * Selector y Gestor Interactivo de Etiquetas
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
            formChanged = true;
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
        window.auraSyncTagsUI = function() {
            const tags = getSelectedTags();
            renderChips(tags);
            syncPills(tags);
        };
    }

    /**
     * Mostrar mensaje
     */
    function showMessage(message, type = 'info') {
        const $messages = $('#aura-transaction-messages');
        
        const classList = {
            'success': 'notice-success',
            'error': 'notice-error',
            'warning': 'notice-warning',
            'info': 'notice-info'
        };
        
        const html = `
            <div class="notice ${classList[type]} is-dismissible">
                <p>${message}</p>
            </div>
        `;
        
        $messages.html(html);
        
        // Auto-dismiss después de 5 segundos
        setTimeout(function() {
            $messages.find('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $messages.offset().top - 100
        }, 300);
    }
    
})(jQuery);
