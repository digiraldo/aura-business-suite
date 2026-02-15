<?php
/**
 * Template: Página de Gestión de Categorías Financieras
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap aura-categories-page">
    <h1 class="wp-heading-inline">
        <?php _e('Gestión de Categorías Financieras', 'aura-suite'); ?>
    </h1>
    <button type="button" class="page-title-action" id="aura-add-category-btn">
        <span class="dashicons dashicons-plus-alt"></span>
        <?php _e('Agregar Nueva', 'aura-suite'); ?>
    </button>
    <hr class="wp-header-end">
    
    <!-- Filtros -->
    <div class="aura-filters-container">
        <div class="aura-filters-row">
            <div class="aura-filter-group">
                <label for="aura-filter-search">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Buscar:', 'aura-suite'); ?>
                </label>
                <input type="text" id="aura-filter-search" placeholder="<?php esc_attr_e('Buscar categorías...', 'aura-suite'); ?>">
            </div>
            
            <div class="aura-filter-group">
                <label for="aura-filter-type">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Tipo:', 'aura-suite'); ?>
                </label>
                <select id="aura-filter-type">
                    <option value=""><?php _e('Todos', 'aura-suite'); ?></option>
                    <option value="income"><?php _e('Ingresos', 'aura-suite'); ?></option>
                    <option value="expense"><?php _e('Egresos', 'aura-suite'); ?></option>
                    <option value="both"><?php _e('Ambos', 'aura-suite'); ?></option>
                </select>
            </div>
            
            <div class="aura-filter-group">
                <label for="aura-filter-status">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Estado:', 'aura-suite'); ?>
                </label>
                <select id="aura-filter-status">
                    <option value=""><?php _e('Todos', 'aura-suite'); ?></option>
                    <option value="active"><?php _e('Activas', 'aura-suite'); ?></option>
                    <option value="inactive"><?php _e('Inactivas', 'aura-suite'); ?></option>
                </select>
            </div>
            
            <div class="aura-filter-group">
                <label for="aura-filter-orderby">
                    <span class="dashicons dashicons-sort"></span>
                    <?php _e('Ordenar por:', 'aura-suite'); ?>
                </label>
                <select id="aura-filter-orderby">
                    <option value="menu_order"><?php _e('Orden personalizado', 'aura-suite'); ?></option>
                    <option value="title"><?php _e('Nombre', 'aura-suite'); ?></option>
                    <option value="date"><?php _e('Fecha de creación', 'aura-suite'); ?></option>
                </select>
            </div>
            
            <button type="button" id="aura-clear-filters-btn" class="button">
                <?php _e('Limpiar filtros', 'aura-suite'); ?>
            </button>
        </div>
    </div>
    
    <!-- Tabla de categorías -->
    <div class="aura-table-container">
        <table class="wp-list-table widefat fixed striped aura-categories-table">
            <thead>
                <tr>
                    <th class="column-name"><?php _e('Nombre', 'aura-suite'); ?></th>
                    <th class="column-type"><?php _e('Tipo', 'aura-suite'); ?></th>
                    <th class="column-parent"><?php _e('Categoría Padre', 'aura-suite'); ?></th>
                    <th class="column-color"><?php _e('Color', 'aura-suite'); ?></th>
                    <th class="column-icon"><?php _e('Icono', 'aura-suite'); ?></th>
                    <th class="column-status"><?php _e('Estado', 'aura-suite'); ?></th>
                    <th class="column-order"><?php _e('Orden', 'aura-suite'); ?></th>
                    <th class="column-transactions"><?php _e('Transacciones', 'aura-suite'); ?></th>
                    <th class="column-actions"><?php _e('Acciones', 'aura-suite'); ?></th>
                </tr>
            </thead>
            <tbody id="aura-categories-tbody">
                <tr class="aura-loading-row">
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
                        <p><?php _e('Cargando categorías...', 'aura-suite'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div id="aura-no-categories" style="display: none; text-align: center; padding: 40px;">
            <span class="dashicons dashicons-info" style="font-size: 48px; color: #ccc;"></span>
            <p><?php _e('No se encontraron categorías con los filtros actuales.', 'aura-suite'); ?></p>
        </div>
    </div>
</div>

<!-- Modal para crear/editar categoría -->
<div id="aura-category-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2 id="aura-modal-title"><?php _e('Nueva Categoría', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close" id="aura-modal-close-btn">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <form id="aura-category-form">
                <input type="hidden" id="category-id" name="category_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category-name">
                            <?php _e('Nombre de la categoría', 'aura-suite'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" id="category-name" name="name" class="widefat" required>
                        <span class="description"><?php _e('Nombre único para identificar la categoría.', 'aura-suite'); ?></span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><?php _e('Tipo de categoría', 'aura-suite'); ?> <span class="required">*</span></label>
                        <fieldset class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="type" value="income" checked>
                                <span class="radio-icon income">
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                </span>
                                <span class="radio-text"><?php _e('Ingresos', 'aura-suite'); ?></span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="type" value="expense">
                                <span class="radio-icon expense">
                                    <span class="dashicons dashicons-arrow-down-alt"></span>
                                </span>
                                <span class="radio-text"><?php _e('Egresos', 'aura-suite'); ?></span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="type" value="both">
                                <span class="radio-icon both">
                                    <span class="dashicons dashicons-leftright"></span>
                                </span>
                                <span class="radio-text"><?php _e('Ambos', 'aura-suite'); ?></span>
                            </label>
                        </fieldset>
                    </div>
                </div>
                
                <div class="form-row form-row-2-cols">
                    <div class="form-group">
                        <label for="category-parent">
                            <?php _e('Categoría padre', 'aura-suite'); ?>
                        </label>
                        <select id="category-parent" name="parent_id" class="widefat">
                            <option value="0"><?php _e('Ninguna (Categoría principal)', 'aura-suite'); ?></option>
                        </select>
                        <span class="description"><?php _e('Selecciona una categoría padre para crear subcategorías.', 'aura-suite'); ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="category-order">
                            <?php _e('Orden de visualización', 'aura-suite'); ?>
                        </label>
                        <input type="number" id="category-order" name="display_order" class="widefat" min="0" value="0">
                        <span class="description"><?php _e('Menor número aparece primero.', 'aura-suite'); ?></span>
                    </div>
                </div>
                
                <div class="form-row form-row-2-cols">
                    <div class="form-group">
                        <label for="category-color">
                            <?php _e('Color', 'aura-suite'); ?>
                        </label>
                        <input type="text" id="category-color" name="color" class="aura-color-picker" value="#3498db">
                        <span class="description"><?php _e('Color para identificar visualmente la categoría.', 'aura-suite'); ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="category-icon">
                            <?php _e('Icono (Dashicon)', 'aura-suite'); ?>
                        </label>
                        <input type="text" id="category-icon" name="icon" class="widefat" placeholder="dashicons-category" value="dashicons-category">
                        <div class="icon-preview">
                            <span class="dashicons dashicons-category" id="icon-preview" style="font-size: 32px; color: #3498db;"></span>
                        </div>
                        <span class="description">
                            <?php _e('Ej: dashicons-money-alt, dashicons-cart, dashicons-heart', 'aura-suite'); ?>
                            <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">
                                <?php _e('Ver todos', 'aura-suite'); ?>
                            </a>
                        </span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category-description">
                            <?php _e('Descripción', 'aura-suite'); ?>
                        </label>
                        <textarea id="category-description" name="description" rows="3" class="widefat"></textarea>
                        <span class="description"><?php _e('Descripción opcional de esta categoría.', 'aura-suite'); ?></span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="category-active" name="is_active" value="1" checked>
                            <span><?php _e('Categoría activa', 'aura-suite'); ?></span>
                        </label>
                        <span class="description"><?php _e('Las categorías inactivas no aparecerán en los formularios de transacciones.', 'aura-suite'); ?></span>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary" id="aura-modal-cancel-btn">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary" id="aura-save-category-btn">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Guardar Categoría', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div id="aura-confirm-delete-modal" class="aura-modal aura-modal-small" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Confirmar Eliminación', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close aura-confirm-delete-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="aura-alert aura-alert-warning">
                <span class="dashicons dashicons-warning"></span>
                <p id="aura-delete-message"><?php _e('¿Estás seguro de eliminar esta categoría? Esta acción no se puede deshacer.', 'aura-suite'); ?></p>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-confirm-delete-close">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary aura-confirm-delete-btn" id="aura-confirm-delete-yes">
                <span class="dashicons dashicons-trash"></span>
                <span class="delete-text"><?php _e('Sí, Eliminar', 'aura-suite'); ?></span>
                <span class="deactivate-text" style="display: none;"><?php _e('Desactivar en su lugar', 'aura-suite'); ?></span>
            </button>
        </div>
    </div>
</div>
