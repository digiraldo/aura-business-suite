/**
 * Gestión de Categorías Financieras - JavaScript
 * 
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

(function($) {
    'use strict';
    
    const AuraCategories = {
        
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.initColorPicker();
            this.loadCategories();
        },
        
        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Botón agregar nueva categoría
            $('#aura-add-category-btn').on('click', () => this.openModal());
            
            // Cerrar modal
            $('#aura-modal-close-btn, #aura-modal-cancel-btn, .aura-modal-overlay').on('click', () => this.closeModal());
            
            // Guardar categoría
            $('#aura-save-category-btn').on('click', () => this.saveCategory());
            
            // Filtros
            $('#aura-filter-search').on('input', this.debounce(() => this.loadCategories(), 300));
            $('#aura-filter-type, #aura-filter-status, #aura-filter-orderby').on('change', () => this.loadCategories());
            
            // Limpiar filtros
            $('#aura-clear-filters-btn').on('click', () => this.clearFilters());
            
            // Preview de icono en tiempo real
            $('#category-icon').on('input', () => this.updateIconPreview());

            // Slug sugerido desde nombre (solo si no fue editado manualmente)
            $('#category-name').on('input', () => this.maybeSyncSlugFromName());
            $('#category-slug').on('input', () => {
                const hasCustomSlug = $('#category-slug').val().trim() !== '';
                $('#category-slug').attr('data-manual', hasCustomSlug ? '1' : '0');
            });

            // Recargar categorías padre cuando cambia el tipo.
            $('input[name="type"]').on('change', () => {
                const currentId = $('#category-id').val() || null;
                const currentParent = $('#category-parent').val() || '0';
                this.loadParentCategories(currentId, currentParent);
                this.updateParentPathPreview();
            });

            $('#category-parent').on('change', () => this.updateParentPathPreview());
            $('#category-parent-new').on('input', () => {
                const hasNewParentName = $('#category-parent-new').val().trim() !== '';
                if (hasNewParentName) {
                    $('#category-parent').val('0');
                }
                this.updateParentCreateBadge();
                this.updateParentPathPreview();
            });
            
            // Cerrar modal confirmación
            $('.aura-confirm-delete-close').on('click', () => this.closeConfirmDeleteModal());
            
            // Confirmar eliminación
            $('#aura-confirm-delete-yes').on('click', () => this.confirmDelete());
            
            // Prevenir submit del form
            $('#aura-category-form').on('submit', (e) => {
                e.preventDefault();
                this.saveCategory();
            });
        },
        
        /**
         * Inicializar color picker
         */
        initColorPicker: function() {
            $('#category-color').wpColorPicker({
                change: function(event, ui) {
                    const color = ui.color.toString();
                    $('#icon-preview').css('color', color);
                }
            });
        },
        
        /**
         * Cargar categorías
         */
        loadCategories: function() {
            const data = {
                action: 'aura_get_categories',
                nonce: auraCategories.nonce,
                type: $('#aura-filter-type').val(),
                status: $('#aura-filter-status').val(),
                search: $('#aura-filter-search').val(),
                orderby: $('#aura-filter-orderby').val(),
                order: 'ASC'
            };
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: () => {
                    this.showLoading();
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCategories(response.data.categories);
                    } else {
                        this.showError(response.data.message || auraCategories.strings.error);
                    }
                },
                error: () => {
                    this.showError(auraCategories.strings.error);
                }
            });
        },
        
        /**
         * Renderizar categorías en la tabla
         */
        renderCategories: function(categories) {
            const tbody = $('#aura-categories-tbody');
            tbody.empty();
            
            if (!categories || categories.length === 0) {
                $('#aura-no-categories').show();
                return;
            }
            
            $('#aura-no-categories').hide();
            
            categories.forEach(category => {
                const row = this.createCategoryRow(category);
                tbody.append(row);
            });
            
            // Vincular eventos de las filas
            this.bindRowEvents();
        },
        
        /**
         * Crear fila de categoría
         */
        createCategoryRow: function(category) {
            const typeLabels = {
                income: 'Ingresos',
                expense: 'Egresos',
                both: 'Ambos'
            };

            const typeClasses = {
                income: 'is-income',
                expense: 'is-expense',
                both: 'is-both'
            };

            const parentName = category.parent_name 
                ? `<span class="aura-parent-chip">${this.escapeHtml(category.parent_name)}</span>` 
                : '<span class="aura-parent-empty">Principal</span>';

            const statusBadge = category.is_active
                ? '<span class="aura-status-badge is-active">Activa</span>'
                : '<span class="aura-status-badge is-inactive">Inactiva</span>';

            const categoryDescription = category.description
                ? `<span class="aura-category-description">${this.escapeHtml(category.description)}</span>`
                : '<span class="aura-category-description is-empty">Sin descripción</span>';
            
            const toggleStatusText = category.is_active ? 'Desactivar' : 'Activar';
            const toggleStatusClass = category.is_active ? 'aura-deactivate' : 'aura-activate';
            
            const row = $(`
                <tr data-category-id="${category.id}" data-transaction-count="${category.transaction_count}">
                    <td class="column-name">
                        <div class="aura-category-main">
                            <span class="aura-color-dot" style="background-color: ${category.color};"></span>
                            <span class="aura-category-name-wrap">
                                <strong class="aura-category-name">${this.escapeHtml(category.name)}</strong>
                                ${categoryDescription}
                            </span>
                        </div>
                    </td>
                    <td class="column-parent">${parentName}</td>
                    <td class="column-type"><span class="aura-type-badge ${typeClasses[category.type] || ''}">${typeLabels[category.type] || 'N/A'}</span></td>
                    <td class="column-slug"><code>${this.escapeHtml(category.slug || 'sin-slug')}</code></td>
                    <td class="column-order"><span class="aura-order-badge">${category.display_order}</span></td>
                    <td class="column-status">${statusBadge}</td>
                    <td class="column-actions">
                        <div class="aura-action-buttons" role="group" aria-label="Acciones de categoría">
                            <button type="button" class="aura-action-btn aura-edit-category" title="Editar categoría">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="aura-action-btn aura-toggle-status ${toggleStatusClass}" title="${toggleStatusText} categoría">
                                <span class="dashicons ${category.is_active ? 'dashicons-hidden' : 'dashicons-visibility'}"></span>
                            </button>
                            <button type="button" class="aura-action-btn aura-delete-category is-danger" title="Eliminar categoría">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
            
            return row;
        },
        
        /**
         * Vincular eventos de las filas
         */
        bindRowEvents: function() {
            // Editar
            $('.aura-edit-category').off('click').on('click', (e) => {
                e.preventDefault();
                const categoryId = $(e.target).closest('tr').data('category-id');
                this.editCategory(categoryId);
            });
            
            // Toggle status
            $('.aura-toggle-status').off('click').on('click', (e) => {
                e.preventDefault();
                const categoryId = $(e.target).closest('tr').data('category-id');
                this.toggleStatus(categoryId);
            });
            
            // Eliminar
            $('.aura-delete-category').off('click').on('click', (e) => {
                e.preventDefault();
                const categoryId = $(e.target).closest('tr').data('category-id');
                this.deleteCategory(categoryId);
            });
        },
        
        /**
         * Abrir modal
         */
        openModal: function(mode = 'create', categoryData = null) {
            // Resetear form
            $('#aura-category-form')[0].reset();
            $('#category-id').val('');
            $('#category-slug').attr('data-manual', '0');
            $('#category-parent-new').val('');
            this.updateParentCreateBadge();
            this.setModalLoading(false);
            
            if (mode === 'create') {
                $('#aura-modal-title').text('Nueva Categoría');
                $('#category-active').prop('checked', true);
                this.loadParentCategories();
            } else {
                $('#aura-modal-title').text('Editar Categoría');
                if (categoryData) {
                    this.populateForm(categoryData);
                }
            }
            
            // Mostrar modal con display: flex (evitar conflictos con .hide())
            $('#aura-category-modal').stop(true, true).css('display', 'flex').fadeIn(200);
            $('body').addClass('aura-modal-open');
            
            // Re-inicializar color picker si es necesario
            setTimeout(() => {
                if ($('#category-color').hasClass('wp-color-picker')) {
                    $('#category-color').wpColorPicker('color', categoryData ? categoryData.color : '#3498db');
                } else {
                    this.initColorPicker();
                }
                this.updateIconPreview();
                this.updateParentPathPreview();
            }, 100);
        },
        
        /**
         * Cerrar modal
         */
        closeModal: function() {
            this.setModalLoading(false);
            $('#aura-category-modal').stop(true, true).fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('aura-modal-open');
        },

        /**
         * Mostrar/ocultar estado de carga del modal
         */
        setModalLoading: function(isLoading, text = 'Cargando categoría...') {
            const loading = $('#aura-modal-loading');
            const form = $('#aura-category-form');
            const footerButtons = $('#aura-modal-footer button, .aura-modal-footer .button');

            if (loading.length) {
                loading.find('p').text(text);
            }

            if (isLoading) {
                loading.show();
                form.addClass('is-loading').css({ opacity: 0.55, pointerEvents: 'none' });
                $('#aura-save-category-btn').prop('disabled', true);
                footerButtons.not('#aura-modal-close-btn').prop('disabled', true);
            } else {
                loading.hide();
                form.removeClass('is-loading').css({ opacity: '', pointerEvents: '' });
                $('#aura-save-category-btn').prop('disabled', false);
                footerButtons.prop('disabled', false);
            }
        },
        
        /**
         * Cargar categorías para el dropdown de padres
         */
        loadParentCategories: function(excludeId = null, selectedParentId = '0') {
            const data = {
                action: 'aura_get_categories',
                nonce: auraCategories.nonce
            };
            
            return $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        const select = $('#category-parent');
                        const selectedType = $('input[name="type"]:checked').val() || 'both';
                        select.empty();
                        select.append('<option value="0">Ninguna (Categoría principal)</option>');
                        
                        response.data.categories.forEach(cat => {
                            const isSameCategory = parseInt(cat.id, 10) === parseInt(excludeId || 0, 10);
                            const isTypeCompatible = (
                                selectedType === 'both' ||
                                cat.type === 'both' ||
                                cat.type === selectedType
                            );

                            if (!isSameCategory && isTypeCompatible) {
                                const inactiveHint = cat.is_active ? '' : ' (Inactiva)';
                                select.append(`<option value="${cat.id}">${this.escapeHtml(cat.name + inactiveHint)}</option>`);
                            }
                        });

                        select.val(String(selectedParentId || '0'));

                        if (select.val() !== String(selectedParentId || '0')) {
                            select.val('0');
                        }

                        this.updateParentPathPreview();
                    }
                }
            });
        },
        
        /**
         * Editar categoría
         */
        editCategory: function(categoryId) {
            const data = {
                action: 'aura_get_category_by_id',
                nonce: auraCategories.nonce,
                category_id: categoryId
            };

            this.openModal('create');
            $('#aura-modal-title').text('Editar Categoría');
            this.setModalLoading(true, 'Cargando categoría...');
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.loadParentCategories(categoryId, response.data.category.parent_id)
                            .always(() => {
                                this.populateForm(response.data.category);
                                this.updateIconPreview();
                                this.setModalLoading(false);
                            });
                    } else {
                        this.setModalLoading(false);
                        this.showError(response.data.message || auraCategories.strings.error);
                    }
                },
                error: () => {
                    this.setModalLoading(false);
                    this.showError(auraCategories.strings.error);
                }
            });
        },
        
        /**
         * Poblar formulario con datos
         */
        populateForm: function(category) {
            $('#category-id').val(category.id);
            $('#category-name').val(category.name);
            $('#category-slug').val(category.slug || '').attr('data-manual', category.slug ? '1' : '0');
            $(`input[name="type"][value="${category.type}"]`).prop('checked', true);
            $('#category-parent').val(category.parent_id);
            $('#category-parent-new').val('');
            this.updateParentCreateBadge();
            $('#category-color').val(category.color);
            $('#category-icon').val(category.icon);
            $('#category-description').val(category.description);
            $('#category-active').prop('checked', category.is_active);
            $('#category-order').val(category.display_order);
            
            // Cargar integraciones
            $('input[name="integration_modules[]"]').prop('checked', false);
            if (category.integration_modules) {
                const modules = Array.isArray(category.integration_modules) ? category.integration_modules : JSON.parse(category.integration_modules || '[]');
                modules.forEach(module => {
                    $(`input[name="integration_modules[]"][value="${module}"]`).prop('checked', true);
                });
            }

            this.updateParentPathPreview();
        },
        
        /**
         * Guardar categoría
         */
        saveCategory: function() {
            // Validaciones frontend
            const name = $('#category-name').val().trim();
            const color = $('#category-color').val();
            
            if (!name) {
                this.showError(auraCategories.strings.nameRequired);
                $('#category-name').focus();
                return;
            }
            
            if (!this.isValidHexColor(color)) {
                this.showError(auraCategories.strings.invalidColor);
                return;
            }
            
            const categoryId = $('#category-id').val();
            const action = categoryId ? 'aura_update_category' : 'aura_create_category';
            
            // Recopilar integraciones seleccionadas
            const integration_modules = [];
            $('input[name="integration_modules[]"]:checked').each(function() {
                integration_modules.push($(this).val());
            });
            
            const data = {
                action: action,
                nonce: auraCategories.nonce,
                category_id: categoryId,
                name: name,
                slug: $('#category-slug').val().trim(),
                type: $('input[name="type"]:checked').val(),
                parent_id: $('#category-parent').val(),
                parent_new_name: $('#category-parent-new').val().trim(),
                color: color,
                icon: $('#category-icon').val(),
                description: $('#category-description').val(),
                is_active: $('#category-active').is(':checked') ? 'true' : 'false',
                display_order: $('#category-order').val(),
                integration_modules: JSON.stringify(integration_modules)
            };
            
            const btn = $('#aura-save-category-btn');
            const btnText = btn.html();
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: () => {
                    btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Guardando...');
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModal();
                        this.loadCategories();
                    } else {
                        this.showError(response.data.message || auraCategories.strings.error);
                    }
                },
                error: () => {
                    this.showError(auraCategories.strings.error);
                },
                complete: () => {
                    btn.prop('disabled', false).html(btnText);
                }
            });
        },

        /**
         * Sincronizar slug desde nombre mientras no se haya personalizado.
         */
        maybeSyncSlugFromName: function() {
            const slugInput = $('#category-slug');
            const isManual = slugInput.attr('data-manual') === '1';
            if (isManual) {
                return;
            }

            const source = $('#category-name').val() || '';
            const generated = this.slugify(source);
            slugInput.val(generated);
        },

        /**
         * Convertir texto a slug URL-friendly.
         */
        slugify: function(value) {
            return String(value || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
        },

        /**
         * Mostrar ruta jerárquica estimada según tipo + padre seleccionado/creado.
         */
        updateParentPathPreview: function() {
            const preview = $('#category-parent-path-preview');
            if (!preview.length) {
                return;
            }

            const type = $('input[name="type"]:checked').val() || 'both';
            const typeLabelMap = {
                income: 'Ingresos',
                expense: 'Egresos',
                both: 'Ambos'
            };
            const typeLabel = typeLabelMap[type] || 'Ambos';

            const selectedParentText = $('#category-parent option:selected').text() || '';
            const selectedParentId = $('#category-parent').val() || '0';
            const newParentName = ($('#category-parent-new').val() || '').trim();

            let parentLabel = '(Categoría principal)';
            if (newParentName) {
                parentLabel = newParentName;
            } else if (selectedParentId !== '0' && selectedParentText) {
                parentLabel = selectedParentText.replace(' (Inactiva)', '');
            }

            preview.text('Ruta jerárquica: ' + typeLabel + ' > ' + parentLabel);
        },

        /**
         * Mostrar badge "Se creará" al escribir una categoría padre nueva.
         */
        updateParentCreateBadge: function() {
            const badge = $('#category-parent-create-badge');
            if (!badge.length) {
                return;
            }

            const newParentName = ($('#category-parent-new').val() || '').trim();
            if (!newParentName) {
                badge.hide().text('');
                return;
            }

            badge.text('Se creará: ' + newParentName).show();
        },
        
        /**
         * Toggle status de categoría
         */
        toggleStatus: function(categoryId) {
            const data = {
                action: 'aura_toggle_category_status',
                nonce: auraCategories.nonce,
                category_id: categoryId
            };
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.loadCategories();
                    } else {
                        this.showError(response.data.message || auraCategories.strings.error);
                    }
                },
                error: () => {
                    this.showError(auraCategories.strings.error);
                }
            });
        },
        
        /**
         * Eliminar categoría
         */
        deleteCategory: function(categoryId) {
            this.currentDeleteId = categoryId;
            
            // Verificar si tiene transacciones
            const row = $(`tr[data-category-id="${categoryId}"]`);
            const transactionCount = parseInt(row.attr('data-transaction-count') || '0', 10);
            const hasTransactions = transactionCount > 0;
            
            if (hasTransactions) {
                $('#aura-delete-message').html(
                    auraCategories.strings.confirmDeleteWithTransactions.replace('%d', transactionCount)
                );
                $('.delete-text').hide();
                $('.deactivate-text').show();
                this.deleteAction = 'deactivate';
            } else {
                $('#aura-delete-message').text(auraCategories.strings.confirmDelete);
                $('.delete-text').show();
                $('.deactivate-text').hide();
                this.deleteAction = 'delete';
            }
            
            $('#aura-confirm-delete-modal').fadeIn(200);
        },
        
        /**
         * Confirmar eliminación
         */
        confirmDelete: function() {
            if (this.deleteAction === 'deactivate') {
                // Desactivar en lugar de eliminar
                this.toggleStatus(this.currentDeleteId);
                this.closeConfirmDeleteModal();
                return;
            }
            
            const data = {
                action: 'aura_delete_category',
                nonce: auraCategories.nonce,
                category_id: this.currentDeleteId
            };
            
            const btn = $('#aura-confirm-delete-yes');
            const btnText = btn.html();
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: () => {
                    btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Eliminando...');
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeConfirmDeleteModal();
                        this.loadCategories();
                    } else {
                        // Si tiene transacciones, ofrecer desactivar
                        if (response.data.transaction_count) {
                            $('#aura-delete-message').html(
                                auraCategories.strings.confirmDeleteWithTransactions.replace('%d', response.data.transaction_count)
                            );
                            $('.delete-text').hide();
                            $('.deactivate-text').show();
                            this.deleteAction = 'deactivate';
                        } else {
                            this.showError(response.data.message || auraCategories.strings.error);
                            this.closeConfirmDeleteModal();
                        }
                    }
                },
                error: () => {
                    this.showError(auraCategories.strings.error);
                    this.closeConfirmDeleteModal();
                },
                complete: () => {
                    btn.prop('disabled', false).html(btnText);
                }
            });
        },
        
        /**
         * Cerrar modal de confirmación
         */
        closeConfirmDeleteModal: function() {
            $('#aura-confirm-delete-modal').fadeOut(200);
        },
        
        /**
         * Limpiar filtros
         */
        clearFilters: function() {
            $('#aura-filter-search').val('');
            $('#aura-filter-type').val('');
            $('#aura-filter-status').val('');
            $('#aura-filter-orderby').val('display_order');
            this.loadCategories();
        },

        /**
         * Debounce local para evitar dependencia dura de lodash
         */
        debounce: function(func, wait) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => func(...args), wait);
            };
        },
        
        /**
         * Actualizar preview de icono
         */
        updateIconPreview: function() {
            const iconClass = $('#category-icon').val();
            const color = $('#category-color').val();
            $('#icon-preview')
                .attr('class', 'dashicons ' + iconClass)
                .css('color', color);
        },
        
        /**
         * Validar color hex
         */
        isValidHexColor: function(color) {
            return /^#([0-9A-F]{3}){1,2}$/i.test(color);
        },
        
        /**
         * Mostrar loading
         */
        showLoading: function() {
            $('#aura-categories-tbody').html(`
                <tr class="aura-loading-row">
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
                        <p>${auraCategories.strings.loading}</p>
                    </td>
                </tr>
            `);
        },
        
        /**
         * Mostrar mensaje de éxito
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        /**
         * Mostrar mensaje de error
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        /**
         * Mostrar notificación
         */
        showNotice: function(message, type) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible aura-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Descartar este aviso.</span>
                    </button>
                </div>
            `);
            
            $('.wrap.aura-categories-page').prepend(notice);
            
            // Auto-dismiss después de 5 segundos
            setTimeout(() => {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Click en botón dismiss
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Scroll al top
            $('html, body').animate({ scrollTop: 0 }, 300);
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };
    
    // Inicializar cuando el documento esté listo
    $(document).ready(() => {
        AuraCategories.init();
    });
    
})(jQuery);
