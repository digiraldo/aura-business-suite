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
        loadCategories('income');
        restoreDraft();
        
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
            
            // Cargar categorías del tipo seleccionado
            loadCategories(type);
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
        
        // Validar categoría
        const categoryId = parseInt($('#category_id').val());
        if (!categoryId || categoryId <= 0) {
            errors.push('Debe seleccionar una categoría');
            $('#category_id').addClass('error');
            isValid = false;
        } else {
            $('#category_id').removeClass('error');
        }
        
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
        
        // Preparar datos
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
            notes: $('#notes').val(),
            tags: $('#tags').val(),
            receipt_file: $('#receipt_file_url').val()
        };
        
        $.ajax({
            url: auraTransactionData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    
                    // Limpiar draft del localStorage
                    localStorage.removeItem('aura_transaction_draft');
                    formChanged = false;
                    
                    // Mostrar opciones de siguiente acción
                    showSuccessActions(response.data);
                } else {
                    showMessage(response.data.message || 'Error al guardar', 'error');
                    $button.prop('disabled', false).html(originalText);
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
    function updatePreview() {
        const type = $('input[name="transaction_type"]:checked').val();
        const amount = $('#amount').val() || '0.00';
        const categoryId = $('#category_id').val();
        const description = $('#description').val() || 'Sin descripción';
        const date = $('#transaction_date').val() || new Date().toLocaleDateString('es-ES');
        const tags = $('#tags').val();
        
        // Actualizar tipo
        const $badge = $('.preview-badge .badge-type');
        $badge.removeClass('income expense').addClass(type);
        
        if (type === 'income') {
            $badge.html('<span class="dashicons dashicons-arrow-down-alt"></span> Ingreso');
        } else {
            $badge.html('<span class="dashicons dashicons-arrow-up-alt"></span> Egreso');
        }
        
        // Actualizar monto
        $('.preview-amount .amount-value').text(parseFloat(amount).toFixed(2));
        
        // Actualizar categoría
        if (categoryId) {
            const categoryName = $('#category_id option:selected').text().trim();
            $('.preview-category .category-name').text(categoryName);
        } else {
            $('.preview-category .category-name').text('Sin categoría');
        }
        
        // Actualizar descripción
        $('.preview-description .description-text').text(description);
        
        // Actualizar fecha
        $('.preview-date .date-text').text(date);
        
        // Actualizar etiquetas
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
