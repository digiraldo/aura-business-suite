/**
 * Transaction Edit JS
 * 
 * Maneja la edición de transacciones con:
 * - Detección de cambios en tiempo real
 * - Indicadores visuales de modificaciones
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
    
    // Configuración
    const significantAmountChange = 20; // Porcentaje
    
    /**
     * Inicializar formulario de edición
     */
    function init() {
        initCategoriesCache();
        // No llamar loadCategories() en init: el tipo está bloqueado (disabled)
        // y PHP ya renderiza las opciones filtradas por tipo correctamente.
        initDatepicker();
        initFieldChangeDetection();
        initResetButtons();
        initFormSubmit();
        initFileUpload();
        updateCharCounters();
    }
    
    /**
     * Inicializar caché de categorías con opciones existentes
     */
    function initCategoriesCache() {
        // Leer todas las opciones del select y almacenarlas en el caché
        $('#category_id option').each(function() {
            const categoryId = String($(this).val()); // Convertir a string
            const categoryName = $(this).text().trim(); // Limpiar espacios
            
            if (categoryId && categoryId !== '') { // Ignorar la opción vacía
                categoriesCache[categoryId] = categoryName;
            }
        });
    }
    
    /**
     * Cargar categorías según tipo de transacción
     */
    function loadCategories() {
        const transactionType = $('input[name="transaction_type"]:checked').val();
        const currentCategoryId = parseInt($('#category_id').data('original'));
        
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
                    
                    // La respuesta viene en response.data.categories (estructura jerárquica)
                    const typeLabel = transactionType === 'income' ? 'Ingreso' : 'Egreso';
                    
                    function appendCategories(list, prefix) {
                        list.forEach(function(category) {
                            const displayName = (prefix || '') + category.name + ' (' + typeLabel + ')';
                            
                            // Almacenar en caché con ID como string
                            categoriesCache[String(category.id)] = displayName;
                            
                            const $option = $('<option>')
                                .val(category.id)
                                .text(displayName)
                                .css('color', category.color || '');
                            
                            if (parseInt(category.id) === currentCategoryId) {
                                $option.prop('selected', true);
                            }
                            
                            $select.append($option);
                            
                            // Procesar hijos (categorías jerárquicas)
                            if (category.children && category.children.length > 0) {
                                appendCategories(category.children, '\u00a0\u00a0\u00a0— ');
                            }
                        });
                    }
                    
                    appendCategories(response.data.categories || [], '');
                    
                    // Reinicializar caché después de cargar opciones
                    initCategoriesCache();
                }
            }
        });
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
            }
        });
    }
    
    /**
     * Inicializar detección de cambios en campos
     */
    function initFieldChangeDetection() {
        // Detectar cambios en inputs y textareas
        $('input[data-original], textarea[data-original], select[data-original]').on('input change', function() {
            const $field = $(this);
            const fieldName = $field.attr('name');
            const currentValue = $field.val().trim();
            const originalValue = $field.data('original').toString().trim();
            
            if (currentValue !== originalValue) {
                markFieldAsChanged($field, fieldName, originalValue, currentValue);
            } else {
                unmarkFieldAsChanged($field, fieldName);
            }
            
            updateChangesSummary();
            checkSignificantChanges();
        });
        
        // Contador de caracteres
        $('#description, #change_reason').on('input', updateCharCounters);
    }
    
    /**
     * Marcar campo como modificado
     */
    function markFieldAsChanged($field, fieldName, oldValue, newValue) {
        const $formField = $field.closest('.aura-form-field');
        $formField.addClass('field-changed');
        $formField.find('.change-indicator').show();
        
        // Registrar cambio
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
        
        // Eliminar del registro de cambios
        delete detectedChanges[fieldName];
    }
    
    /**
     * Obtener etiqueta legible del campo
     */
    function getFieldLabel(fieldName) {
        const labels = {
            'transaction_date': auraTransactionEdit.labels.date,
            'category_id': auraTransactionEdit.labels.category,
            'amount': auraTransactionEdit.labels.amount,
            'payment_method': auraTransactionEdit.labels.paymentMethod,
            'description': auraTransactionEdit.labels.description,
            'reference_number': auraTransactionEdit.labels.reference,
            'recipient_payer': auraTransactionEdit.labels.recipient,
            'notes': auraTransactionEdit.labels.notes,
            'tags': auraTransactionEdit.labels.tags
        };
        
        return labels[fieldName] || fieldName;
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
            let displayOldValue = change.oldValue || auraTransactionEdit.labels.empty;
            let displayNewValue = change.newValue || auraTransactionEdit.labels.empty;
            
            // Formatear valores especiales
            if (fieldName === 'amount') {
                displayOldValue = '$' + parseFloat(change.oldValue).toLocaleString('es-MX', {minimumFractionDigits: 2});
                displayNewValue = '$' + parseFloat(change.newValue).toLocaleString('es-MX', {minimumFractionDigits: 2});
            } else if (fieldName === 'category_id') {
                // Usar caché de categorías (convertir a string para asegurar coincidencia)
                const oldCatId = String(change.oldValue);
                const newCatId = String(change.newValue);
                
                displayOldValue = categoriesCache[oldCatId] || 
                                  $('#category_id option[value="' + oldCatId + '"]').text().trim() || 
                                  'Categoría ID: ' + oldCatId;
                displayNewValue = categoriesCache[newCatId] || 
                                  $('#category_id option[value="' + newCatId + '"]').text().trim() || 
                                  'Categoría ID: ' + newCatId;
            }
            
            // Limpiar espacios en blanco antes de truncar
            displayOldValue = String(displayOldValue).trim();
            displayNewValue = String(displayNewValue).trim();
            
            // Truncar textos largos
            if (displayOldValue.length > 30) displayOldValue = displayOldValue.substring(0, 30) + '...';
            if (displayNewValue.length > 30) displayNewValue = displayNewValue.substring(0, 30) + '...';
            
            const $item = $('<li>').html(
                '<strong>' + change.label + '</strong><br>' +
                '<span class="old-value">' + displayOldValue + '</span> ' +
                '<span class="dashicons dashicons-arrow-right-alt"></span> ' +
                '<span class="new-value">' + displayNewValue + '</span>'
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
        
        // Verificar cambio significativo en monto
        if (detectedChanges.amount) {
            const oldAmount = parseFloat(detectedChanges.amount.oldValue);
            const newAmount = parseFloat(detectedChanges.amount.newValue);
            const percentChange = Math.abs((newAmount - oldAmount) / oldAmount * 100);
            
            if (percentChange > significantAmountChange) {
                requiresReason = true;
                reasonMessage = auraTransactionEdit.messages.significantAmountChange
                    .replace('%s', percentChange.toFixed(1));
            }
        }
        
        // Mostrar/ocultar sección de motivo
        if (requiresReason) {
            $('#change-reason-section').show();
            $('#change-reason-message').text(reasonMessage);
            $('#change_reason').prop('required', true);
        } else {
            $('#change-reason-section').hide();
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
            const minLength = parseInt($textarea.attr('minlength')) || 0;
            const $counter = $textarea.siblings('.char-counter');
            
            if ($counter.length) {
                $counter.text(currentLength + ' / ' + minLength + ' ' + auraTransactionEdit.labels.minChars);
                
                if (currentLength >= minLength) {
                    $counter.addClass('valid');
                } else {
                    $counter.removeClass('valid');
                }
            }
        });
    }
    
    /**
     * Inicializar botones de restauración
     */
    function initResetButtons() {
        // Restaurar todos los valores
        $('#reset-all-fields').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(auraTransactionEdit.messages.confirmResetAll)) {
                return;
            }
            
            // Restaurar cada campo a su valor original
            $('input[data-original], textarea[data-original], select[data-original]').each(function() {
                const $field = $(this);
                const originalValue = $field.data('original');
                $field.val(originalValue).trigger('change');
            });
            
            // Limpiar motivo del cambio
            $('#change_reason').val('');
            
            // Actualizar resumen
            detectedChanges = {};
            updateChangesSummary();
            checkSignificantChanges();
            
            showMessage(auraTransactionEdit.messages.allFieldsReset, 'info');
        });
    }
    
    /**
     * Inicializar envío del formulario
     */
    function initFormSubmit() {
        $('#aura-transaction-edit-form').on('submit', function(e) {
            e.preventDefault();
            
            // Verificar que haya cambios
            if (Object.keys(detectedChanges).length === 0) {
                showMessage(auraTransactionEdit.messages.noChanges, 'warning');
                return;
            }
            
            // Validar formulario
            if (!validateForm()) {
                return;
            }
            
            // Confirmar guardado
            if (!confirm(auraTransactionEdit.messages.confirmSave)) {
                return;
            }
            
            // Deshabilitar botón
            const $btn = $('#save-transaction-btn');
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update spin"></span> ' + auraTransactionEdit.messages.saving);
            
            // Preparar datos
            const formData = $(this).serialize();
            
            // Enviar via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&nonce=' + auraTransactionEdit.nonce,
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        
                        // Redirigir después de 1 segundo
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    } else {
                        showMessage(response.data.message, 'error');
                        
                        // Mostrar errores específicos
                        if (response.data.errors && response.data.errors.length > 0) {
                            let errorsList = '<ul>';
                            response.data.errors.forEach(function(error) {
                                errorsList += '<li>' + error + '</li>';
                            });
                            errorsList += '</ul>';
                            showMessage(errorsList, 'error');
                        }
                        
                        // Re-habilitar botón
                        $btn.prop('disabled', false);
                        $btn.html('<span class="dashicons dashicons-saved"></span> ' + auraTransactionEdit.labels.saveChanges);
                    }
                },
                error: function(xhr, status, error) {
                    showMessage(auraTransactionEdit.messages.error + ': ' + error, 'error');
                    
                    // Re-habilitar botón
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-saved"></span> ' + auraTransactionEdit.labels.saveChanges);
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
        
        // Validar categoría
        if (!$('#category_id').val()) {
            errors.push(auraTransactionEdit.validation.categoryRequired);
            isValid = false;
        }
        
        // Validar monto
        const amount = parseFloat($('#amount').val());
        if (!amount || amount <= 0) {
            errors.push(auraTransactionEdit.validation.amountRequired);
            isValid = false;
        }
        
        // Validar fecha
        if (!$('#transaction_date').val()) {
            errors.push(auraTransactionEdit.validation.dateRequired);
            isValid = false;
        }
        
        // Validar descripción
        const description = $('#description').val().trim();
        if (description.length < 10) {
            errors.push(auraTransactionEdit.validation.descriptionMinLength);
            isValid = false;
        }
        
        // Validar motivo del cambio si es requerido
        if ($('#change_reason').prop('required')) {
            const changeReason = $('#change_reason').val().trim();
            if (changeReason.length < 20) {
                errors.push(auraTransactionEdit.validation.changeReasonRequired);
                isValid = false;
            }
        }
        
        if (!isValid) {
            let errorsList = '<ul>';
            errors.forEach(function(error) {
                errorsList += '<li>' + error + '</li>';
            });
            errorsList += '</ul>';
            showMessage(errorsList, 'error');
        }
        
        return isValid;
    }
    
    /**
     * Inicializar manejo de archivos
     */
    function initFileUpload() {
        // Cambiar archivo existente
        $('.change-receipt').on('click', function(e) {
            e.preventDefault();
            $('.current-receipt-file').hide();
            $('.aura-file-upload').show();
        });
        
        // Eliminar archivo existente
        $('.remove-current-receipt').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('¿Estás seguro de eliminar este comprobante?')) {
                return;
            }
            
            $('.current-receipt-file').remove();
            $('.aura-file-upload').show();
            $('#receipt_file_url').val('').trigger('change');
        });
        
        // Upload de nuevo archivo
        $('#receipt_file').on('change', function(e) {
            const file = e.target.files[0];
            
            if (!file) return;
            
            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('El archivo es muy grande. Tamaño máximo: 5MB');
                $(this).val('');
                return;
            }
            
            // Validar tipo
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                alert('Tipo de archivo no válido. Solo JPG, PNG o PDF.');
                $(this).val('');
                return;
            }
            
            // Previsualizar imagen
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('.file-preview img').attr('src', e.target.result);
                    $('.file-upload-label').hide();
                    $('.file-preview').show();
                };
                reader.readAsDataURL(file);
            }
            
            // Subir archivo
            uploadReceiptFile(file);
        });
        
        // Remover archivo de preview
        $('.remove-file').on('click', function(e) {
            e.preventDefault();
            $('#receipt_file').val('');
            $('#receipt_file_url').val('').trigger('change');
            $('.file-preview').hide();
            $('.file-upload-label').show();
        });
    }
    
    /**
     * Subir archivo de comprobante
     */
    function uploadReceiptFile(file) {
        const formData = new FormData();
        formData.append('action', 'aura_upload_receipt');
        formData.append('nonce', auraTransactionEdit.transactionNonce);
        formData.append('receipt_file', file);
        
        $('.file-upload-label .file-label-text').text('Subiendo archivo...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Guardar URL del archivo
                    $('#receipt_file_url').val(response.data.file_name).trigger('change');
                    
                    showMessage('Archivo subido exitosamente', 'success');
                    
                    // Si es imagen, mostrar preview
                    if (response.data.file_type === 'image') {
                        $('.file-preview img').attr('src', response.data.file_url);
                        $('.file-upload-label').hide();
                        $('.file-preview').show();
                    }
                } else {
                    showMessage(response.data.message || 'Error al subir archivo', 'error');
                    $('#receipt_file').val('');
                }
                
                $('.file-upload-label .file-label-text').text('Subir archivo (JPG, PNG, PDF - Max 5MB)');
            },
            error: function(xhr, status, error) {
                showMessage('Error de conexión al subir archivo: ' + error, 'error');
                $('#receipt_file').val('');
                $('.file-upload-label .file-label-text').text('Subir archivo (JPG, PNG, PDF - Max 5MB)');
            }
        });
    }
    
    /**
     * Mostrar mensaje al usuario
     */
    function showMessage(message, type = 'info') {
        const typeClasses = {
            'success': 'notice-success',
            'error': 'notice-error',
            'warning': 'notice-warning',
            'info': 'notice-info'
        };
        
        const $notice = $('<div>')
            .addClass('notice ' + typeClasses[type] + ' is-dismissible')
            .html('<p>' + message + '</p>');
        
        const $messages = $('#aura-transaction-messages');
        $messages.empty().append($notice);
        
        // Scroll al mensaje
        $('html, body').animate({
            scrollTop: $messages.offset().top - 32
        }, 300);
        
        // Auto-ocultar después de 5 segundos (excepto errores)
        if (type !== 'error') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    /**
     * Advertir al usuario si intenta salir con cambios sin guardar
     */
    $(window).on('beforeunload', function(e) {
        if (Object.keys(detectedChanges).length > 0) {
            const message = auraTransactionEdit.messages.unsavedChanges;
            e.returnValue = message;
            return message;
        }
    });
    
    // Desactivar advertencia cuando se envía el formulario
    $('#aura-transaction-edit-form').on('submit', function() {
        $(window).off('beforeunload');
    });
    
    // Inicializar todo
    init();
});
