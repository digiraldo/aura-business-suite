<?php
/**
 * Template: Editar Transacción Financiera
 * 
 * Reutiliza formulario de creación con:
 * - Pre-llenado de datos existentes
 * - Indicadores de cambios
 * - Historial de modificaciones
 * - Validaciones específicas
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener ID de transacción
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$transaction_id) {
    wp_die(__('ID de transacción inválido', 'aura-suite'));
}

// Obtener datos de la transacción
global $wpdb;
$table = $wpdb->prefix . 'aura_finance_transactions';
$transaction = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL",
        $transaction_id
    ),
    ARRAY_A
);

if (!$transaction) {
    wp_die(__('Transacción no encontrada', 'aura-suite'));
}

// Verificar permisos
$current_user_id = get_current_user_id();
$can_edit_all = current_user_can('aura_finance_edit_all');
$can_edit_own = current_user_can('aura_finance_edit_own');
$is_creator = ($transaction['created_by'] == $current_user_id);

if (!$can_edit_all && (!$can_edit_own || !$is_creator)) {
    wp_die(__('No tienes permisos para editar esta transacción', 'aura-suite'));
}

// Verificar restricciones adicionales para usuarios normales
if (!$can_edit_all) {
    // Permitir editar transacciones pendientes o rechazadas (para corregir y re-enviar)
    // Solo admin puede editar transacciones aprobadas
    if ($transaction['status'] === 'approved') {
        wp_die(__('No puedes editar transacciones aprobadas. Solo un administrador puede modificarlas.', 'aura-suite'));
    }
    
    // Verificar antigüedad (30 días por defecto)
    $max_edit_days = get_option('aura_finance_max_edit_days', 30);
    $created_date = new DateTime($transaction['created_at']);
    $now = new DateTime();
    $diff_days = $now->diff($created_date)->days;
    
    if ($diff_days > $max_edit_days) {
        wp_die(sprintf(
            __('No puedes editar transacciones con más de %d días de antigüedad', 'aura-suite'),
            $max_edit_days
        ));
    }
}

// Obtener categorías filtradas por el tipo de la transacción
$categories_table = $wpdb->prefix . 'aura_finance_categories';
$categories = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $categories_table WHERE is_active = 1 AND (type = %s OR type = 'both') ORDER BY display_order ASC, name ASC",
    $transaction['transaction_type']
));

// Obtener historial de cambios
$history_table = $wpdb->prefix . 'aura_finance_transaction_history';
$history = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$history_table} WHERE transaction_id = %d ORDER BY changed_at DESC",
        $transaction_id
    ),
    ARRAY_A
);

// Procesar tags
$tags_array = !empty($transaction['tags']) ? explode(',', $transaction['tags']) : array();
$tags_string = implode(', ', array_map('trim', $tags_array));

// Formatear fecha para el datepicker
$transaction_date_formatted = date('d/m/Y', strtotime($transaction['transaction_date']));

// Pre-cargar datos del usuario vinculado (Fase 6, Item 6.1)
$related_user_data = null;
$related_user_display_name = '';
if (!empty($transaction['related_user_id'])) {
    $related_user_data = get_userdata(intval($transaction['related_user_id']));
    if ($related_user_data) {
        $related_user_display_name = $related_user_data->display_name;
    }
}

?>

<div class="wrap aura-transaction-form-wrap aura-edit-mode">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-edit"></span>
        <?php _e('Editar Transacción', 'aura-suite'); ?>
        <span class="transaction-id">#<?php echo $transaction_id; ?></span>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="page-title-action">
        <?php _e('← Volver al Listado', 'aura-suite'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Notificaciones -->
    <div id="aura-transaction-messages" class="aura-messages"></div>
    
    <!-- Info Box: Transacción Rechazada -->
    <?php if ($transaction['status'] === 'rejected'): ?>
    <div class="notice notice-error" style="margin: 20px 0; padding: 15px; border-left: 4px solid #dc3232;">
        <h3 style="margin-top: 0;">
            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
            <?php _e('Transacción Rechazada', 'aura-suite'); ?>
        </h3>
        <p><strong><?php _e('Motivo del rechazo:', 'aura-suite'); ?></strong></p>
        <p style="background: #fff; padding: 10px; border-radius: 4px; border-left: 3px solid #dc3232;">
            <?php echo nl2br(esc_html($transaction['rejection_reason'])); ?>
        </p>
        <p style="margin-bottom: 0;">
            <span class="dashicons dashicons-info-outline" style="color: #2271b1;"></span>
            <strong><?php _e('Puedes corregir esta transacción y re-enviarla:', 'aura-suite'); ?></strong>
            <?php _e('Realiza las correcciones necesarias y guarda los cambios. La transacción volverá a estado "Pendiente" para nueva aprobación.', 'aura-suite'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Info Box de Edición -->
    <div class="aura-info-box aura-info-warning">
        <span class="dashicons dashicons-info"></span>
        <div class="info-content">
            <strong><?php _e('Modo de Edición:', 'aura-suite'); ?></strong>
            <?php if ($transaction['status'] === 'approved' && $can_edit_all): ?>
                <p><?php _e('Esta transacción fue aprobada. Al editarla, volverá a estado "Pendiente" y requerirá nueva aprobación.', 'aura-suite'); ?></p>
            <?php elseif ($transaction['status'] === 'rejected'): ?>
                <p><?php _e('Al guardar los cambios, esta transacción volverá a estado "Pendiente" y será enviada nuevamente para aprobación.', 'aura-suite'); ?></p>
            <?php else: ?>
                <p><?php _e('Los campos modificados se resaltarán automáticamente. Todos los cambios quedarán registrados en el historial.', 'aura-suite'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="aura-transaction-container aura-edit-layout">
        <!-- Formulario Principal -->
        <div class="aura-transaction-form-main">
            <form id="aura-transaction-edit-form" method="post">
                <input type="hidden" name="transaction_id" value="<?php echo $transaction_id; ?>">
                <input type="hidden" name="action" value="aura_update_transaction">
                <input type="hidden" id="original_data" value='<?php echo htmlspecialchars(json_encode($transaction), ENT_QUOTES, 'UTF-8'); ?>'>
                
                <!-- Selector de Tipo de Transacción (BLOQUEADO) -->
                <div class="aura-form-section aura-transaction-type-selector disabled">
                    <div class="aura-toggle-switch">
                        <input type="radio" id="type-income" name="transaction_type" value="income" 
                               <?php checked($transaction['transaction_type'], 'income'); ?> disabled>
                        <label for="type-income" class="income-label">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                            <?php _e('Ingreso', 'aura-suite'); ?>
                        </label>
                        
                        <input type="radio" id="type-expense" name="transaction_type" value="expense" 
                               <?php checked($transaction['transaction_type'], 'expense'); ?> disabled>
                        <label for="type-expense" class="expense-label">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <?php _e('Egreso', 'aura-suite'); ?>
                        </label>
                        
                        <span class="toggle-slider"></span>
                    </div>
                    <p class="description">
                        <span class="dashicons dashicons-lock"></span>
                        <?php _e('No se puede cambiar el tipo de transacción una vez creada', 'aura-suite'); ?>
                    </p>
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
                                value="<?php echo esc_attr($transaction_date_formatted); ?>"
                                data-original="<?php echo esc_attr($transaction_date_formatted); ?>"
                                required>
                            <span class="aura-field-icon dashicons dashicons-calendar-alt"></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="category_id" class="required">
                                <?php _e('Categoría', 'aura-suite'); ?>
                            </label>
                            <select id="category_id" name="category_id" data-original="<?php echo esc_attr($transaction['category_id']); ?>" required>
                                <option value=""><?php _e('Seleccionar categoría...', 'aura-suite'); ?></option>
                                <?php 
                                foreach ($categories as $category):
                                    // El tipo se filtró en la consulta SQL; no se filtra aquí
                                    $type_label = $category->type === 'income' ? __('Ingreso', 'aura-suite')
                                                : ($category->type === 'expense' ? __('Egreso', 'aura-suite')
                                                : __('General', 'aura-suite')); // 'both'
                                ?>
                                    <option 
                                        value="<?php echo esc_attr($category->id); ?>" 
                                        data-type="<?php echo esc_attr($category->type); ?>"
                                        <?php selected($transaction['category_id'], $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                        (<?php echo $type_label; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-category"></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
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
                                    value="<?php echo esc_attr($transaction['amount']); ?>"
                                    data-original="<?php echo esc_attr($transaction['amount']); ?>"
                                    required>
                            </div>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="payment_method">
                                <?php _e('Método de Pago', 'aura-suite'); ?>
                            </label>
                            <select id="payment_method" name="payment_method" data-original="<?php echo esc_attr($transaction['payment_method']); ?>">
                                <option value=""><?php _e('Seleccionar...', 'aura-suite'); ?></option>
                                <option value="cash" <?php selected($transaction['payment_method'], 'cash'); ?>><?php _e('Efectivo', 'aura-suite'); ?></option>
                                <option value="transfer" <?php selected($transaction['payment_method'], 'transfer'); ?>><?php _e('Transferencia', 'aura-suite'); ?></option>
                                <option value="check" <?php selected($transaction['payment_method'], 'check'); ?>><?php _e('Cheque', 'aura-suite'); ?></option>
                                <option value="card" <?php selected($transaction['payment_method'], 'card'); ?>><?php _e('Tarjeta', 'aura-suite'); ?></option>
                                <option value="other" <?php selected($transaction['payment_method'], 'other'); ?>><?php _e('Otro', 'aura-suite'); ?></option>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-money"></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
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
                                data-original="<?php echo esc_attr($transaction['description']); ?>"
                                required><?php echo esc_textarea($transaction['description']); ?></textarea>
                            <span class="char-counter"><?php echo strlen($transaction['description']); ?> / 10 <?php _e('caracteres mínimos', 'aura-suite'); ?></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
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
                                value="<?php echo esc_attr($transaction['reference_number']); ?>"
                                data-original="<?php echo esc_attr($transaction['reference_number']); ?>"
                                placeholder="<?php _e('N° Factura, Cheque, etc.', 'aura-suite'); ?>">
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <label for="recipient_payer">
                                <?php echo $transaction['transaction_type'] === 'income' ? __('Pagador', 'aura-suite') : __('Destinatario', 'aura-suite'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="recipient_payer" 
                                name="recipient_payer"
                                value="<?php echo esc_attr($transaction['recipient_payer']); ?>"
                                data-original="<?php echo esc_attr($transaction['recipient_payer']); ?>"
                                placeholder="<?php _e('Nombre o empresa', 'aura-suite'); ?>">
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Usuario Vinculado al Sistema (Fase 6, Item 6.1) -->
                    <?php if (current_user_can('aura_finance_link_user') || current_user_can('manage_options')) : ?>
                    <div class="aura-form-row" id="aura-related-user-row">
                        <div class="aura-form-field aura-field-60">
                            <label for="related_user_search">
                                <?php _e('Usuario Vinculado (Sistema)', 'aura-suite'); ?>
                            </label>
                            <div class="aura-user-autocomplete-wrap">
                                <input type="text"
                                       id="related_user_search"
                                       placeholder="<?php _e('Buscar usuario por nombre o email...', 'aura-suite'); ?>"
                                       autocomplete="off"
                                       class="regular-text"
                                       value="<?php echo esc_attr($related_user_display_name); ?>">
                                <input type="hidden" id="related_user_id" name="related_user_id"
                                       value="<?php echo esc_attr($transaction['related_user_id'] ?? ''); ?>"
                                       data-original="<?php echo esc_attr($transaction['related_user_id'] ?? ''); ?>">
                                <div id="aura-user-preview" class="aura-user-preview" style="<?php echo $related_user_data ? '' : 'display:none;'; ?>">
                                    <img id="aura-user-avatar"
                                         src="<?php echo $related_user_data ? esc_url(get_avatar_url($related_user_data->ID, ['size' => 24])) : ''; ?>"
                                         width="24" height="24" style="border-radius:50%;vertical-align:middle;margin-right:6px;">
                                    <span id="aura-user-name"><?php echo esc_html($related_user_display_name); ?></span>
                                    <a href="#" id="aura-user-clear" style="margin-left:8px;color:#e74c3c;" title="<?php _e('Quitar usuario', 'aura-suite'); ?>">✕</a>
                                </div>
                            </div>
                            <span class="aura-field-icon dashicons dashicons-admin-users"></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                        <div class="aura-form-field aura-field-40">
                            <label for="related_user_concept"><?php _e('Concepto de Vinculación', 'aura-suite'); ?></label>
                            <select id="related_user_concept" name="related_user_concept"
                                    data-original="<?php echo esc_attr($transaction['related_user_concept'] ?? ''); ?>">
                                <option value=""><?php _e('— Seleccionar —', 'aura-suite'); ?></option>
                                <?php
                                $concepts_edit = [
                                    'payment_to_user'       => __('Pago realizado a un usuario', 'aura-suite'),
                                    'charge_to_user'        => __('Cobro realizado a un usuario', 'aura-suite'),
                                    'salary'                => __('Pago de salario/nómina', 'aura-suite'),
                                    'scholarship'           => __('Beca asignada', 'aura-suite'),
                                    'loan_payment'          => __('Pago de préstamo', 'aura-suite'),
                                    'refund'                => __('Reembolso', 'aura-suite'),
                                    'expense_reimbursement' => __('Reembolso de gastos', 'aura-suite'),
                                ];
                                foreach ($concepts_edit as $val => $label) :
                                    $sel_concept = selected($transaction['related_user_concept'] ?? '', $val, false);
                                ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php echo $sel_concept; ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Detalles Adicionales -->
                <div class="aura-form-section">
                    <h2><?php _e('Detalles Adicionales', 'aura-suite'); ?></h2>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-100">
                            <label for="notes">
                                <?php _e('Notas Internas', 'aura-suite'); ?>
                            </label>
                            <textarea 
                                id="notes" 
                                name="notes" 
                                rows="3"
                                data-original="<?php echo esc_attr($transaction['notes']); ?>"
                                placeholder="<?php _e('Notas o comentarios adicionales (solo visible internamente)', 'aura-suite'); ?>"><?php echo esc_textarea($transaction['notes']); ?></textarea>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
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
                                data-autocomplete="aura-tags"
                                value="<?php echo esc_attr($tags_string); ?>"
                                data-original="<?php echo esc_attr($tags_string); ?>"
                                placeholder="<?php _e('Ej: urgente, recurrente, fiscal (separadas por comas)', 'aura-suite'); ?>">
                            <span class="aura-field-icon dashicons dashicons-tag"></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-100">
                            <label for="receipt_file">
                                <?php _e('Comprobante', 'aura-suite'); ?>
                            </label>
                            
                            <?php if (!empty($transaction['receipt_file'])): ?>
                            <!-- Archivo existente -->
                            <div class="current-receipt-file">
                                <div class="receipt-preview">
                                    <?php 
                                    $file_ext = strtolower(pathinfo($transaction['receipt_file'], PATHINFO_EXTENSION));
                                    $file_url = content_url('uploads/aura-receipts/' . $transaction['receipt_file']);
                                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                    ?>
                                        <img src="<?php echo esc_url($file_url); ?>" alt="Comprobante" class="receipt-image">
                                    <?php else: ?>
                                        <span class="dashicons dashicons-media-document receipt-icon"></span>
                                        <span class="file-name"><?php echo esc_html(basename($transaction['receipt_file'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="receipt-actions">
                                    <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="button button-small">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <?php _e('Ver', 'aura-suite'); ?>
                                    </a>
                                    <button type="button" class="button button-small remove-current-receipt" data-file="<?php echo esc_attr($transaction['receipt_file']); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e('Eliminar', 'aura-suite'); ?>
                                    </button>
                                    <button type="button" class="button button-small change-receipt">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php _e('Cambiar', 'aura-suite'); ?>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php _e('Puedes mantener el archivo actual o cambiarlo por uno nuevo.', 'aura-suite'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Uploader de nuevo archivo -->
                            <div class="aura-file-upload" style="<?php echo !empty($transaction['receipt_file']) ? 'display: none;' : ''; ?>">
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
                            <input type="hidden" id="receipt_file_url" name="receipt_file" value="<?php echo esc_attr($transaction['receipt_file']); ?>" data-original="<?php echo esc_attr($transaction['receipt_file']); ?>">
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Motivo del Cambio (aparece si hay cambios significativos) -->
                <div class="aura-form-section" id="change-reason-section" style="display: none;">
                    <h2>
                        <span class="dashicons dashicons-warning" style="color: #f39c12;"></span>
                        <?php _e('Motivo del Cambio', 'aura-suite'); ?>
                    </h2>
                    
                    <div class="aura-info-box aura-info-warning">
                        <span class="dashicons dashicons-info"></span>
                        <div class="info-content">
                            <p id="change-reason-message"><?php _e('Se requiere explicar el motivo de este cambio.', 'aura-suite'); ?></p>
                        </div>
                    </div>
                    
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-100">
                            <label for="change_reason" class="required">
                                <?php _e('Explica por qué estás modificando esta transacción', 'aura-suite'); ?>
                            </label>
                            <textarea 
                                id="change_reason" 
                                name="change_reason" 
                                rows="3"
                                minlength="20"
                                placeholder="<?php _e('Describe el motivo del cambio (mínimo 20 caracteres)...', 'aura-suite'); ?>"></textarea>
                            <span class="char-counter">0 / 20 <?php _e('caracteres mínimos', 'aura-suite'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de Acción -->
                <div class="aura-form-actions">
                    <button type="submit" class="button button-primary button-large" id="save-transaction-btn">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Guardar Cambios', 'aura-suite'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-secondary button-large">
                        <?php _e('Cancelar', 'aura-suite'); ?>
                    </a>
                    
                    <button type="button" class="button button-link" id="reset-all-fields">
                        <span class="dashicons dashicons-undo"></span>
                        <?php _e('Restaurar Todos los Valores Originales', 'aura-suite'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Sidebar con Historial -->
        <aside class="aura-transaction-sidebar">
            <!-- Resumen de Cambios -->
            <div class="aura-sidebar-widget" id="changes-summary" style="display: none;">
                <h3><?php _e('Resumen de Cambios', 'aura-suite'); ?></h3>
                <div class="widget-content">
                    <p class="changes-count">
                        <strong id="changes-count">0</strong> <?php _e('cambios detectados', 'aura-suite'); ?>
                    </p>
                    <ul id="changes-list"></ul>
                </div>
            </div>
            
            <!-- Historial de Modificaciones -->
            <?php if (!empty($history)): ?>
            <div class="aura-sidebar-widget">
                <h3>
                    <span class="dashicons dashicons-backup"></span>
                    <?php _e('Historial de Modificaciones', 'aura-suite'); ?>
                </h3>
                <div class="widget-content">
                    <div class="history-timeline">
                        <?php foreach ($history as $entry): 
                            $changed_by = get_userdata($entry['changed_by']);
                            $field_labels = array(
                                'category_id' => __('Categoría', 'aura-suite'),
                                'amount' => __('Monto', 'aura-suite'),
                                'transaction_date' => __('Fecha', 'aura-suite'),
                                'description' => __('Descripción', 'aura-suite'),
                                'payment_method' => __('Método de Pago', 'aura-suite'),
                                'reference_number' => __('Referencia', 'aura-suite'),
                                'recipient_payer' => __('Destinatario/Pagador', 'aura-suite'),
                                'notes' => __('Notas', 'aura-suite'),
                                'tags' => __('Etiquetas', 'aura-suite'),
                                'status' => __('Estado', 'aura-suite')
                            );
                            $field_label = $field_labels[$entry['field_changed']] ?? $entry['field_changed'];
                        ?>
                        <div class="history-entry">
                            <div class="history-icon">
                                <span class="dashicons dashicons-edit"></span>
                            </div>
                            <div class="history-content">
                                <strong><?php echo $field_label; ?></strong>
                                <div class="history-change">
                                    <span class="old-value"><?php echo esc_html($entry['old_value']); ?></span>
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                    <span class="new-value"><?php echo esc_html($entry['new_value']); ?></span>
                                </div>
                                <div class="history-meta">
                                    <small>
                                        <?php echo $changed_by ? $changed_by->display_name : __('Usuario desconocido', 'aura-suite'); ?>
                                        · <?php echo date('d/m/Y H:i', strtotime($entry['changed_at'])); ?>
                                    </small>
                                    <?php if (!empty($entry['change_reason'])): ?>
                                    <p class="change-reason"><?php echo esc_html($entry['change_reason']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="aura-sidebar-widget">
                <h3><?php _e('Historial de Modificaciones', 'aura-suite'); ?></h3>
                <div class="widget-content">
                    <p class="no-history">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Esta transacción no tiene modificaciones previas.', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Información Original -->
            <div class="aura-sidebar-widget">
                <h3><?php _e('Información Original', 'aura-suite'); ?></h3>
                <div class="widget-content">
                    <p>
                        <strong><?php _e('Creado por:', 'aura-suite'); ?></strong><br>
                        <?php 
                        $creator = get_userdata($transaction['created_by']);
                        echo $creator ? $creator->display_name : __('Usuario desconocido', 'aura-suite');
                        ?>
                    </p>
                    <p>
                        <strong><?php _e('Fecha de creación:', 'aura-suite'); ?></strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                    </p>
                    <?php if ($transaction['updated_at'] && $transaction['updated_at'] != $transaction['created_at']): ?>
                    <p>
                        <strong><?php _e('Última modificación:', 'aura-suite'); ?></strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($transaction['updated_at'])); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>
