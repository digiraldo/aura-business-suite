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
            $('#aura-filter-search').on('input', _.debounce(() => this.loadCategories(), 300));
            $('#aura-filter-type, #aura-filter-status, #aura-filter-orderby').on('change', () => this.loadCategories());
            
            // Limpiar filtros
            $('#aura-clear-filters-btn').on('click', () => this.clearFilters());
            
            // Preview de icono en tiempo real
            $('#category-icon').on('input', () => this.updateIconPreview());
            
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
            const typeIcons = {
                income: '<span class="dashicons dashicons-arrow-up-alt aura-icon-income" title="Ingresos"></span>',
                expense: '<span class="dashicons dashicons-arrow-down-alt aura-icon-expense" title="Egresos"></span>',
                both: '<span class="dashicons dashicons-leftright aura-icon-both" title="Ambos"></span>'
            };
            
            const statusIcon = category.is_active
                ? '<span class="dashicons dashicons-yes-alt aura-icon-active" title="Activa"></span>'
                : '<span class="dashicons dashicons-dismiss aura-icon-inactive" title="Inactiva"></span>';
            
            const colorBadge = `<span class="aura-color-badge" style="background-color: ${category.color}; border: 1px solid #ccc;"></span>`;
            
            const iconPreview = `<span class="dashicons ${category.icon}" style="font-size: 20px; color: ${category.color};"></span>`;
            
            const parentName = category.parent_name 
                ? `<span class="aura-parent-name">${category.parent_name}</span>` 
                : '—';
            
            const transactionCount = category.transaction_count > 0
                ? `<span class="dashicons dashicons-money-alt"></span> ${category.transaction_count}`
                : '—';
            
            const toggleStatusText = category.is_active ? 'Desactivar' : 'Activar';
            const toggleStatusClass = category.is_active ? 'aura-deactivate' : 'aura-activate';
            
            const row = $(`
                <tr data-category-id="${category.id}">
                    <td class="column-name">
                        <strong>${this.escapeHtml(category.name)}</strong>
                    </td>
                    <td class="column-type">${typeIcons[category.type] || ''}</td>
                    <td class="column-parent">${parentName}</td>
                    <td class="column-color">${colorBadge}</td>
                    <td class="column-icon">${iconPreview}</td>
                    <td class="column-status">${statusIcon}</td>
                    <td class="column-order">${category.display_order}</td>
                    <td class="column-transactions">${transactionCount}</td>
                    <td class="column-actions">
                        <div class="row-actions">
                            <span class="edit">
                                <a href="#" class="aura-edit-category">Editar</a> |
                            </span>
                            <span class="toggle-status">
                                <a href="#" class="aura-toggle-status ${toggleStatusClass}">${toggleStatusText}</a> |
                            </span>
                            <span class="delete">
                                <a href="#" class="aura-delete-category" style="color: #b32d2e;">Eliminar</a>
                            </span>
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
            
            if (mode === 'create') {
                $('#aura-modal-title').text('Nueva Categoría');
                $('#category-active').prop('checked', true);
                this.loadParentCategories();
            } else {
                $('#aura-modal-title').text('Editar Categoría');
                this.populateForm(categoryData);
            }
            
            $('#aura-category-modal').fadeIn(200);
            $('body').addClass('aura-modal-open');
            
            // Re-inicializar color picker si es necesario
            setTimeout(() => {
                if ($('#category-color').hasClass('wp-color-picker')) {
                    $('#category-color').wpColorPicker('color', categoryData ? categoryData.color : '#3498db');
                } else {
                    this.initColorPicker();
                }
                this.updateIconPreview();
            }, 100);
        },
        
        /**
         * Cerrar modal
         */
        closeModal: function() {
            $('#aura-category-modal').fadeOut(200);
            $('body').removeClass('aura-modal-open');
        },
        
        /**
         * Cargar categorías para el dropdown de padres
         */
        loadParentCategories: function(excludeId = null) {
            const data = {
                action: 'aura_get_categories',
                nonce: auraCategories.nonce,
                status: 'active'
            };
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        const select = $('#category-parent');
                        select.empty();
                        select.append('<option value="0">Ninguna (Categoría principal)</option>');
                        
                        response.data.categories.forEach(cat => {
                            if (cat.id != excludeId) {
                                select.append(`<option value="${cat.id}">${this.escapeHtml(cat.name)}</option>`);
                            }
                        });
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
            
            $.ajax({
                url: auraCategories.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.loadParentCategories(categoryId);
                        setTimeout(() => {
                            this.openModal('edit', response.data.category);
                        }, 200);
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
         * Poblar formulario con datos
         */
        populateForm: function(category) {
            $('#category-id').val(category.id);
            $('#category-name').val(category.name);
            $(`input[name="type"][value="${category.type}"]`).prop('checked', true);
            $('#category-parent').val(category.parent_id);
            $('#category-color').val(category.color);
            $('#category-icon').val(category.icon);
            $('#category-description').val(category.description);
            $('#category-active').prop('checked', category.is_active);
            $('#category-order').val(category.display_order);
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
            
            const data = {
                action: action,
                nonce: auraCategories.nonce,
                category_id: categoryId,
                name: name,
                type: $('input[name="type"]:checked').val(),
                parent_id: $('#category-parent').val(),
                color: color,
                icon: $('#category-icon').val(),
                description: $('#category-description').val(),
                is_active: $('#category-active').is(':checked') ? 'true' : 'false',
                display_order: $('#category-order').val()
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
            const hasTransactions = row.find('.column-transactions .dashicons-money-alt').length > 0;
            
            if (hasTransactions) {
                const count = row.find('.column-transactions').text().trim().split(' ')[1];
                $('#aura-delete-message').html(
                    auraCategories.strings.confirmDeleteWithTransactions.replace('%d', count)
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
            $('#aura-filter-orderby').val('menu_order');
            this.loadCategories();
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
                    <td colspan="9" style="text-align: center; padding: 40px;">
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
    
    // Debounce function (si no está disponible lodash)
    if (typeof _ === 'undefined' || !_.debounce) {
        window._ = {
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        };
    }
    
})(jQuery);
