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
    
    /**
     * Inicializar
     */
    $(document).ready(function() {
        initDatepicker();
        initToggleSwitch();
        initFormValidation();
        initFileUpload();
        initCollapsible();
        initPreview();
        initAutoSave();
        initUserAutocomplete();
        loadCategories('income');
        loadExpenseCategoriesForType('income');
        restoreDraft();

        // Fase 8: estado inicial del campo Categoría del presupuesto
        // Si hay un área fija (view_own) el campo siempre es visible;
        // si el select de área existe pero no tiene valor, se oculta.
        toggleBudgetCategoryField(getSelectedAreaId() > 0);

        // Fase 8: área → recarga categorías y banner de presupuesto
        $(document).on('change', '#transaction_area_id', function () {
            const areaId = parseInt($(this).val()) || 0;
            const type   = $('input[name="transaction_type"]:checked').val() || 'income';
            areaBudgetCategoriesMap = {};
            hideBudgetBanner();
            // Mostrar u ocultar el campo Categoría del presupuesto
            toggleBudgetCategoryField(areaId > 0);
            if (areaId) {
                loadCategoriesForArea(areaId, type);
            } else {
                // Sin área: resetear el select de presupuesto y cargar categorías genéricas
                $('#category_id').val('').html('<option value="">Seleccionar categoría...</option>');
                loadCategories(type);
            }
        });

        // Fase 8: categoría → muestra banner de presupuesto + auto-filtrado inverso de áreas
        $(document).on('change', '#category_id', function () {
            const areaId = getSelectedAreaId();
            const catId  = parseInt($(this).val()) || 0;
            if (areaId && catId) {
                renderBudgetBanner(areaId, catId);
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
            $wrap.removeClass('type-income type-expense').addClass('type-' + type);
            
            // Actualizar previsualización
            updatePreview();
            
            // Cargar categorías: primero área si está seleccionada
            const areaId = getSelectedAreaId();
            if (areaId) {
                loadCategoriesForArea(areaId, type);
            } else {
                loadCategories(type);
            }
            // Fase 8.4: recargar categoría del gasto por tipo
            loadExpenseCategoriesForType(type);
        });
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
                .data('category', category);
            $select.append($opt);

            if (category.children && category.children.length > 0) {
                renderExpenseCategoriesSelect(category.children, level + 1);
            }
        });
    }

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

        $.post(auraTransactionData.ajaxUrl, {
            action  : 'aura_get_area_budget_categories',
            nonce   : auraTransactionData.budgetsNonce,
            area_id : areaId,
            type    : type,
        })
        .done(function (res) {
            if (!res.success) {
                // Fallback a carga genérica
                loadCategories(type);
                return;
            }

            const data = res.data;
            areaBudgetCategoriesMap = {};

            // Construir mapa catId → datos enriquecidos
            if (data.has_budgets && data.categories) {
                data.categories.forEach(function (c) {
                    areaBudgetCategoriesMap[c.id] = c;
                });
            }

            // Filtrar por tipo de transacción activo
            let cats = (data.categories || []).filter(function (c) {
                return !type || c.type === type || !c.type;
            });

            renderCategoriesForArea(cats);

            // Mostrar advertencia si el área no tiene presupuestos
            if (!data.has_budgets) {
                showBudgetBannerWarning(auraTransactionData.messages.noBudgetsForArea || 'Esta área no tiene presupuestos asignados para la fecha actual.');
            }
        })
        .fail(function () {
            loadCategories(type);
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
            const hasBudget = !!areaBudgetCategoriesMap[cat.id];
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
     * Renderizar el banner de estado del presupuesto para área + categoría.
     */
    function renderBudgetBanner(areaId, catId) {
        const catData = areaBudgetCategoriesMap[catId];

        if (!catData || !catData.budget_id) {
            // Sin presupuesto activo para esta combinación
            const catName = $('#category_id option:selected').text().replace('💰 ', '').trim();
            const areaName = $('#transaction_area_id option:selected').text().trim();
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
        const areaName = $('#transaction_area_id option:selected').text().trim();

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

        const catId  = parseInt($('#category_id').val()) || 0;
        const catData = areaBudgetCategoriesMap[catId];
        if (!catData) return;

        const amount = parseFloat($('#amount').val()) || 0;
        const avail  = parseFloat(catData.available || 0);

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
        
        // Validar tipo
        const type = $('input[name="transaction_type"]:checked').val();
        if (!type) {
            errors.push('Debe seleccionar un tipo de transacción');
            isValid = false;
        }
        
        // Validar categoría del gasto (obligatoria)
        const expenseCategoryId = parseInt($('#expense_category_id').val());
        if (!expenseCategoryId || expenseCategoryId <= 0) {
            errors.push('Debe seleccionar la categoría del gasto');
            $('#expense_category_id').addClass('error');
            isValid = false;
        } else {
            $('#expense_category_id').removeClass('error');
        }

        // Categoría del presupuesto: opcional (se sugiere pero no bloquea si el área no tiene presupuesto)
        const categoryId = parseInt($('#category_id').val());
        // No se valida como requerida.
        
        // Validar monto
        const amount = parseFloat($('#amount').val());
        if (!amount || amount <= 0) {
            errors.push('El monto debe ser mayor a 0');
            $('#amount').addClass('error');
            isValid = false;
        } else {
            $('#amount').removeClass('error');
        }
        
        // Validar fecha
        const date = $('#transaction_date').val();
        if (!date) {
            errors.push('La fecha es requerida');
            $('#transaction_date').addClass('error');
            isValid = false;
        } else {
            $('#transaction_date').removeClass('error');
        }
        
        // Validar descripción
        const description = $('#description').val();
        if (description.length < 10) {
            errors.push('La descripción debe tener al menos 10 caracteres');
            $('#description').addClass('error');
            isValid = false;
        } else {
            $('#description').removeClass('error');
        }
        
        // Mostrar errores
        if (!isValid) {
            showMessage(errors.join('<br>'), 'error');
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
            reference_number: $('#reference_number').val(),
            recipient_payer: $('#recipient_payer').val(),
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
            
            // Subir archivo
            uploadFile(file);
        });
        
        // Remover archivo
        $('.remove-file').on('click', function() {
            $('#receipt_file').val('');
            $('#receipt_file_url').val('');
            $('.file-preview').hide();
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
                    // Guardar URL del archivo
                    $('#receipt_file_url').val(response.data.file_url);
                    
                    // Mostrar preview
                    if (file.type.startsWith('image/')) {
                        $('.preview-image').attr('src', response.data.file_url).show();
                    } else {
                        $('.preview-image').hide();
                    }
                    
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
     * Inicializar sección colapsable
     */
    function initCollapsible() {
        $('.aura-collapsible-header').on('click', function() {
            const $content = $(this).next('.aura-collapsible-content');
            const $icon = $(this).find('.dashicons');
            
            $content.slideToggle(300);
            $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });
    }
    
    /**
     * Inicializar vista previa
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
        $badge.removeClass('income expense').addClass(type);
        if (type === 'income') {
            $badge.html('<span class="dashicons dashicons-arrow-up-alt"></span> Ingreso');
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
     * Autocomplete usuario vinculado (Fase 6, Item 6.1)
     */
    function initUserAutocomplete() {
        const $searchInput  = $('#related_user_search');
        const $hiddenId     = $('#related_user_id');
        const $preview      = $('#aura-user-preview');
        const $previewAvatar= $('#aura-user-avatar');
        const $previewName  = $('#aura-user-name');
        const $clearBtn     = $('#aura-user-clear');

        if (!$searchInput.length) return;

        $searchInput.autocomplete({
            minLength: 2,
            delay: 300,
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
                $searchInput.val(ui.item.name);
                $hiddenId.val(ui.item.id).trigger('change');
                $previewAvatar.attr('src', ui.item.avatar_url);
                $previewName.text(ui.item.name);
                $preview.show();
                formChanged = true;
                return false;
            }
        }).autocomplete('instance')._renderItem = function(ul, item) {
            return $('<li>')
                .append(
                    '<div style="display:flex;align-items:center;gap:8px;">' +
                    '<img src="' + item.avatar_url + '" width="28" height="28" style="border-radius:50%;">' +
                    '<div><strong>' + $('<span>').text(item.name).html() + '</strong>' +
                    '<br><small style="color:#8c8f94">' + $('<span>').text(item.email).html() + '</small></div>' +
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
     * Guardar borrador en localStorage
     */
    function saveDraft() {
        const draft = {
            transaction_type: $('input[name="transaction_type"]:checked').val(),
            category_id: $('#category_id').val(),
            amount: $('#amount').val(),
            transaction_date: $('#transaction_date').val(),
            description: $('#description').val(),
            payment_method: $('#payment_method').val(),
            reference_number: $('#reference_number').val(),
            recipient_payer: $('#recipient_payer').val(),
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
                $('#category_id').val(draft.category_id);
                $('#amount').val(draft.amount);
                $('#transaction_date').val(draft.transaction_date);
                $('#description').val(draft.description).trigger('input');
                $('#payment_method').val(draft.payment_method);
                $('#reference_number').val(draft.reference_number);
                $('#recipient_payer').val(draft.recipient_payer);
                if (draft.related_user_id) {
                    $('#related_user_id').val(draft.related_user_id);
                }
                if (draft.related_user_concept) {
                    $('#related_user_concept').val(draft.related_user_concept);
                }
                $('#notes').val(draft.notes);
                $('#tags').val(draft.tags);
                
                updatePreview();
                
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
