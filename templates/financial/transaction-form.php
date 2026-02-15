<?php
/**
 * Template: Formulario de Nueva Transacción
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_finance_create')) {
    wp_die(__('No tienes permisos para acceder a esta página', 'aura-suite'));
}

// Obtener categorías para el formulario
global $wpdb;
$categories_table = $wpdb->prefix . 'aura_finance_categories';
$categories = $wpdb->get_results("SELECT * FROM $categories_table WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
?>

<div class="wrap aura-transaction-form-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-money-alt"></span>
        <?php _e('Nueva Transacción', 'aura-suite'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Notificaciones -->
    <div id="aura-transaction-messages" class="aura-messages"></div>
    
    <div class="aura-transaction-container">
        <!-- Formulario Principal -->
        <div class="aura-transaction-form-main">
            <form id="aura-transaction-form" method="post" enctype="multipart/form-data">
                
                <!-- Selector de Tipo de Transacción -->
                <div class="aura-form-section aura-transaction-type-selector">
                    <div class="aura-toggle-switch">
                        <input type="radio" id="type-income" name="transaction_type" value="income" checked>
                        <label for="type-income" class="income-label">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                            <?php _e('Ingreso', 'aura-suite'); ?>
                        </label>
                        
                        <input type="radio" id="type-expense" name="transaction_type" value="expense">
                        <label for="type-expense" class="expense-label">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <?php _e('Egreso', 'aura-suite'); ?>
                        </label>
                        
                        <span class="toggle-slider"></span>
                    </div>
                </div>
                
                <!-- Campos Principales -->
                <div class="aura-form-section">
                    <h2><?php _e('Información General', 'aura-suite'); ?></h2>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="transaction_date" class="required">
                                <?php _e('Fecha de Transacción', 'aura-suite'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="transaction_date" 
                                name="transaction_date" 
                                class="aura-datepicker" 
                                placeholder="dd/mm/yyyy"
                                value="<?php echo date('d/m/Y'); ?>"
                                required>
                            <span class="aura-field-icon dashicons dashicons-calendar-alt"></span>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="category_id" class="required">
                                <?php _e('Categoría', 'aura-suite'); ?>
                            </label>
                            <select id="category_id" name="category_id" required>
                                <option value=""><?php _e('Seleccionar categoría...', 'aura-suite'); ?></option>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-category"></span>
                        </div>
                    </div>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="amount" class="required">
                                <?php _e('Monto', 'aura-suite'); ?>
                            </label>
                            <div class="aura-amount-field">
                                <span class="currency-symbol">$</span>
                                <input 
                                    type="number" 
                                    id="amount" 
                                    name="amount" 
                                    step="0.01" 
                                    min="0.01"
                                    placeholder="0.00"
                                    required>
                            </div>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="payment_method">
                                <?php _e('Método de Pago', 'aura-suite'); ?>
                            </label>
                            <select id="payment_method" name="payment_method">
                                <option value=""><?php _e('Seleccionar...', 'aura-suite'); ?></option>
                                <option value="cash"><?php _e('Efectivo', 'aura-suite'); ?></option>
                                <option value="transfer"><?php _e('Transferencia', 'aura-suite'); ?></option>
                                <option value="check"><?php _e('Cheque', 'aura-suite'); ?></option>
                                <option value="card"><?php _e('Tarjeta', 'aura-suite'); ?></option>
                                <option value="other"><?php _e('Otro', 'aura-suite'); ?></option>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-money"></span>
                        </div>
                    </div>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-100">
                            <label for="description" class="required">
                                <?php _e('Descripción', 'aura-suite'); ?>
                            </label>
                            <textarea 
                                id="description" 
                                name="description" 
                                rows="3"
                                minlength="10"
                                placeholder="<?php _e('Describe la transacción (mínimo 10 caracteres)...', 'aura-suite'); ?>"
                                required></textarea>
                            <span class="char-counter">0 / 10 <?php _e('caracteres mínimos', 'aura-suite'); ?></span>
                        </div>
                    </div>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="reference_number">
                                <?php _e('Número de Referencia', 'aura-suite'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="reference_number" 
                                name="reference_number"
                                placeholder="<?php _e('N° Factura, Cheque, etc.', 'aura-suite'); ?>">
                            <span class="aura-field-icon dashicons dashicons-tag"></span>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="recipient_payer">
                                <span class="label-income"><?php _e('Pagador', 'aura-suite'); ?></span>
                                <span class="label-expense"><?php _e('Beneficiario', 'aura-suite'); ?></span>
                            </label>
                            <input 
                                type="text" 
                                id="recipient_payer" 
                                name="recipient_payer"
                                placeholder="<?php _e('Nombre de la persona u organización', 'aura-suite'); ?>">
                            <span class="aura-field-icon dashicons dashicons-businessman"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Campos Opcionales (colapsables) -->
                <div class="aura-form-section aura-collapsible">
                    <h2 class="aura-collapsible-header">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Información Adicional (Opcional)', 'aura-suite'); ?>
                    </h2>
                    
                    <div class="aura-collapsible-content" style="display: none;">
                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <label for="notes">
                                    <?php _e('Notas Adicionales', 'aura-suite'); ?>
                                </label>
                                <textarea 
                                    id="notes" 
                                    name="notes" 
                                    rows="3"
                                    placeholder="<?php _e('Cualquier información adicional relevante...', 'aura-suite'); ?>"></textarea>
                            </div>
                        </div>
                        
                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <label for="tags">
                                    <?php _e('Etiquetas', 'aura-suite'); ?>
                                </label>
                                <input 
                                    type="text" 
                                    id="tags" 
                                    name="tags"
                                    placeholder="<?php _e('Separar por comas: proyecto1, urgente, etc.', 'aura-suite'); ?>">
                                <span class="aura-field-icon dashicons dashicons-tag"></span>
                            </div>
                        </div>
                        
                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <label for="receipt_file">
                                    <?php _e('Comprobante', 'aura-suite'); ?>
                                </label>
                                <div class="aura-file-upload">
                                    <input 
                                        type="file" 
                                        id="receipt_file" 
                                        name="receipt_file"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                    <label for="receipt_file" class="file-upload-label">
                                        <span class="dashicons dashicons-upload"></span>
                                        <span class="file-label-text"><?php _e('Subir archivo (JPG, PNG, PDF - Max 5MB)', 'aura-suite'); ?></span>
                                    </label>
                                    <div class="file-preview" style="display: none;">
                                        <img src="" alt="Preview" class="preview-image">
                                        <button type="button" class="remove-file">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" id="receipt_file_url" name="receipt_file_url">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones del Formulario -->
                <div class="aura-form-actions">
                    <button type="button" class="button" id="btn-clear-draft">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Limpiar Formulario', 'aura-suite'); ?>
                    </button>
                    
                    <div class="primary-actions">
                        <button type="button" class="button" id="btn-save-draft">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Guardar Borrador', 'aura-suite'); ?>
                        </button>
                        
                        <button type="submit" class="button button-primary" id="btn-save-transaction">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Guardar Transacción', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Nonce -->
                <?php wp_nonce_field('aura_transaction_nonce', 'nonce'); ?>
            </form>
        </div>
        
        <!-- Panel de Vista Previa -->
        <div class="aura-transaction-preview">
            <div class="preview-card">
                <h3><?php _e('Vista Previa', 'aura-suite'); ?></h3>
                
                <div class="preview-content">
                    <div class="preview-badge">
                        <span class="badge-type income">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                            <?php _e('Ingreso', 'aura-suite'); ?>
                        </span>
                    </div>
                    
                    <div class="preview-amount">
                        <span class="amount-symbol">$</span>
                        <span class="amount-value">0.00</span>
                    </div>
                    
                    <div class="preview-category">
                        <span class="dashicons dashicons-category"></span>
                        <span class="category-name"><?php _e('Sin categoría', 'aura-suite'); ?></span>
                    </div>
                    
                    <div class="preview-description">
                        <span class="description-text"><?php _e('Sin descripción', 'aura-suite'); ?></span>
                    </div>
                    
                    <div class="preview-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span class="date-text"><?php echo date('d/m/Y'); ?></span>
                    </div>
                    
                    <div class="preview-tags" style="display: none;">
                        <span class="dashicons dashicons-tag"></span>
                        <span class="tags-list"></span>
                    </div>
                </div>
                
                <div class="preview-footer">
                    <small><?php _e('Los cambios se reflejan en tiempo real', 'aura-suite'); ?></small>
                </div>
            </div>
            
            <!-- Tips y Ayuda -->
            <div class="preview-tips">
                <h4>
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('Consejos', 'aura-suite'); ?>
                </h4>
                <ul>
                    <li><?php _e('Asegúrate de seleccionar la categoría correcta para mejor organización', 'aura-suite'); ?></li>
                    <li><?php _e('Adjunta el comprobante para facilitar auditorías futuras', 'aura-suite'); ?></li>
                    <li><?php _e('Usa etiquetas para agrupar transacciones relacionadas', 'aura-suite'); ?></li>
                    <li><?php _e('El formulario se guarda automáticamente cada 30 segundos', 'aura-suite'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
