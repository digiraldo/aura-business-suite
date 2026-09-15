<?php
/**
 * Template: Editar Transacción Financiera
 * 
 * Reutiliza formulario de creación con:
 * - Pre-llenado de datos existentes
 * - Indicadores de cambios en tiempo real
 * - Vista previa dinámica en vivo (paridad UX/UI con Nueva Transacción)
 * - Historial de modificaciones y metadatos
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
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0);

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

// Determinar el tipo de transacción
$tx_type = !empty($transaction['transaction_type']) ? $transaction['transaction_type'] : 'expense';

// Obtener categorías filtradas por el tipo de la transacción
$categories_table = $wpdb->prefix . 'aura_finance_categories';
$categories = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $categories_table WHERE is_active = 1 AND (type = %s OR type = 'both') ORDER BY display_order ASC, name ASC",
    $tx_type
));

// Obtener cuentas financieras activas
$accounts_table = $wpdb->prefix . 'aura_finance_accounts';
$finance_accounts = $wpdb->get_results(
    "SELECT id, name, account_type, currency, current_balance
     FROM {$accounts_table}
     WHERE deleted_at IS NULL AND is_active = 1
     ORDER BY name ASC"
);

require_once AURA_PLUGIN_DIR . 'modules/financial/class-financial-tags.php';
$available_tags = Aura_Financial_Tags::get_all_tags();
$existing_tags_val = !empty($transaction['tags']) ? trim($transaction['tags']) : '';

// Soporte Multi-Área y CBAC para edición de transacciones
$_edit_view_own = (
    current_user_can('aura_areas_view_own')
    && !current_user_can('aura_areas_view_all')
    && !current_user_can('manage_options')
);

if ($_edit_view_own) {
    $_edit_areas = Aura_Areas_Setup::get_user_areas(get_current_user_id());
    $_edit_single_area = (count($_edit_areas) === 1) ? $_edit_areas[0] : null;
    $_edit_user_area_id = $_edit_single_area ? (int) $_edit_single_area->id : 0;
} else {
    $_edit_areas = $wpdb->get_results(
        "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order, name"
    );
    $_edit_single_area = null;
    $_edit_user_area_id = 0;
}

$expense_category_current = !empty($transaction['expense_category_id'])
    ? (int) $transaction['expense_category_id']
    : (int) $transaction['category_id'];

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

// Pre-cargar datos del tercero vinculado
$third_party_data = null;
if (!empty($transaction['third_party_id'])) {
    $tp_table = $wpdb->prefix . 'aura_finance_third_parties';
    $third_party_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tp_table} WHERE id = %d", intval($transaction['third_party_id'])), ARRAY_A);
}

$history_categories = $wpdb->get_results(
    "SELECT id, name FROM {$categories_table} WHERE is_active = 1",
    ARRAY_A
);
$history_category_map = array();
foreach ($history_categories as $row) {
    $history_category_map[(string) $row['id']] = $row['name'];
}

$history_account_map = array();
foreach ($finance_accounts as $account) {
    $history_account_map[(string) $account->id] = $account->name . ' · ' . $account->currency;
}

$history_area_map = array(
    '' => __('General (sin área)', 'aura-suite'),
    '0' => __('General (sin área)', 'aura-suite'),
);
foreach ($_edit_areas as $area) {
    $history_area_map[(string) $area->id] = $area->name;
}

$history_concept_labels = array(
    'payment_to_user'       => __('Pago realizado a un usuario', 'aura-suite'),
    'charge_to_user'        => __('Cobro realizado a un usuario', 'aura-suite'),
    'salary'                => __('Pago de salario/nómina', 'aura-suite'),
    'scholarship'           => __('Beca asignada', 'aura-suite'),
    'loan_payment'          => __('Pago de préstamo', 'aura-suite'),
    'refund'                => __('Reembolso', 'aura-suite'),
    'expense_reimbursement' => __('Reembolso de gastos', 'aura-suite'),
    'unlinked'              => __('Desvinculado', 'aura-suite'),
    'none'                  => __('Ninguno', 'aura-suite'),
    ''                      => __('—', 'aura-suite'),
);

$history_payment_methods = array(
    'cash'            => __('Efectivo', 'aura-suite'),
    'bank_transfer'   => __('Transferencia bancaria', 'aura-suite'),
    'credit_card'     => __('Tarjeta de Crédito', 'aura-suite'),
    'debit_card'      => __('Tarjeta de Débito', 'aura-suite'),
    'check'           => __('Cheque', 'aura-suite'),
    'digital_wallet'  => __('Billetera digital', 'aura-suite'),
    'other'           => __('Otro', 'aura-suite'),
);

$history_status_labels = array(
    'pending'            => __('Pendiente', 'aura-suite'),
    'approved'           => __('Aprobada', 'aura-suite'),
    'rejected'           => __('Rechazada', 'aura-suite'),
    'active'             => __('Activa', 'aura-suite'),
    'soft_delete'        => __('En papelera', 'aura-suite'),
    'status_resubmitted' => __('Re-enviada para aprobación', 'aura-suite'),
    'status_deletion'    => __('Envío a papelera / Eliminación', 'aura-suite'),
);

$format_history_value = static function ($field, $value) use (
    $history_category_map,
    $history_account_map,
    $history_area_map,
    $history_concept_labels,
    $history_payment_methods,
    $history_status_labels
) {
    $raw = is_scalar($value) ? trim((string) $value) : '';

    if ($raw === '' || ($raw === '0' && in_array($field, array('area_id', 'related_user_id', 'third_party_id'), true))) {
        if ($field === 'area_id') {
            return $history_area_map[''];
        }
        return '—';
    }

    switch ($field) {
        case 'amount':
            return '$' . number_format((float) $raw, 2, '.', ',');

        case 'transaction_date':
            $ts = strtotime($raw);
            return $ts ? date('d/m/Y', $ts) : $raw;

        case 'category_id':
        case 'expense_category_id':
            return $history_category_map[$raw] ?? $raw;

        case 'source_account_id':
        case 'destination_account_id':
            return $history_account_map[$raw] ?? $raw;

        case 'area_id':
            return $history_area_map[$raw] ?? $raw;

        case 'payment_method':
            return $history_payment_methods[$raw] ?? ucfirst($raw);

        case 'related_user_id':
            $user = get_userdata((int) $raw);
            return $user ? $user->display_name : ($raw ? sprintf(__('Usuario #%d', 'aura-suite'), (int)$raw) : '—');

        case 'third_party_id':
            global $wpdb;
            $tp_name = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(commercial_name, full_name) FROM {$wpdb->prefix}aura_finance_third_parties WHERE id = %d", (int) $raw));
            return $tp_name ?: ($raw ? sprintf(__('Tercero #%d', 'aura-suite'), (int)$raw) : '—');

        case 'related_user_concept':
            return $history_concept_labels[$raw] ?? ($raw === 'unlinked' ? __('Desvinculado', 'aura-suite') : $raw);

        case 'receipt_file':
            if (empty($raw)) {
                return __('— (Sin comprobante)', 'aura-suite');
            }
            if (strpos($raw, 'drive.google.com') !== false) {
                return __('☁️ Archivo en Google Drive', 'aura-suite');
            }
            return '📎 ' . basename($raw);

        case 'status':
        case 'status_resubmitted':
        case 'status_deletion':
            if (strpos($raw, 'approved') !== false) {
                if (strpos($raw, 'Auto-aprobada') !== false || strpos($raw, 'auto') !== false) {
                    return __('Aprobada (Auto-aprobada)', 'aura-suite');
                }
                return __('Aprobada', 'aura-suite');
            }
            if (strpos($raw, 'pending') !== false) {
                return __('Pendiente', 'aura-suite');
            }
            if (strpos($raw, 'rejected') !== false) {
                return __('Rechazada', 'aura-suite');
            }
            if (strpos($raw, 'soft_delete') !== false) {
                return __('En papelera', 'aura-suite');
            }
            if (strpos($raw, 'deleted') !== false) {
                return __('Eliminada', 'aura-suite');
            }
            return $history_status_labels[$raw] ?? $raw;

        default:
            return $raw;
    }
};

$status_classes = array(
    'approved' => 'status-approved',
    'pending'  => 'status-pending',
    'rejected' => 'status-rejected',
);
$status_class = $status_classes[$transaction['status']] ?? 'status-default';
$status_label = $history_status_labels[$transaction['status']] ?? ucfirst($transaction['status']);

// Formatear monto inicial
$amount_raw = (float) $transaction['amount'];
$amount_formatted = number_format($amount_raw, 2, '.', '');
?>

<div class="wrap aura-transaction-form-wrap aura-edit-mode type-<?php echo esc_attr($tx_type); ?>">
    <div class="aura-page-header-container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <h1 class="wp-heading-inline" style="margin:0;display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-edit"></span>
                <?php _e('Editar Transacción', 'aura-suite'); ?>
                <span class="transaction-id" style="font-size:16px;color:#64748b;font-weight:600;">#<?php echo $transaction_id; ?></span>
            </h1>
            <span class="aura-status-badge <?php echo esc_attr($status_class); ?>" style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                <?php echo esc_html($status_label); ?>
            </span>
        </div>
        
        <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-secondary" style="display:inline-flex;align-items:center;gap:4px;">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php _e('Volver al Listado', 'aura-suite'); ?>
        </a>
    </div>
    
    <hr class="wp-header-end">
    
    <!-- Notificaciones -->
    <div id="aura-transaction-messages" class="aura-messages"></div>

    <section class="aura-transaction-ux-head" aria-label="Estado del formulario">
        <div class="aura-transaction-ux-head__content">
            <h2><?php _e('Editar Transacción', 'aura-suite'); ?></h2>
            <p><?php _e('Ajusta los datos clave y revisa el impacto estimado y la vista previa en vivo antes de guardar los cambios.', 'aura-suite'); ?></p>
        </div>
        <div class="aura-transaction-ux-head__progress">
            <div class="aura-progress-meta">
                <strong><?php _e('Progreso del formulario', 'aura-suite'); ?></strong>
                <span id="aura-tx-progress-text">100%</span>
            </div>
            <div class="aura-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" aria-label="Progreso de diligenciamiento">
                <span id="aura-tx-progress-bar" class="aura-progress-fill" style="width:100%;"></span>
            </div>
        </div>
    </section>

    <div id="aura-transaction-context-note" class="aura-transaction-context-note" role="status" aria-live="polite"></div>
    
    <!-- Info Box: Transacción Rechazada -->
    <?php if ($transaction['status'] === 'rejected'): ?>
    <div class="notice notice-error" style="margin: 20px 0; padding: 15px; border-left: 4px solid #dc3232; border-radius: 8px;">
        <h3 style="margin-top: 0; display:flex; align-items:center; gap:6px;">
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
    <div class="aura-info-box aura-info-warning" style="margin: 16px 0; border-radius: 8px;">
        <span class="dashicons dashicons-info"></span>
        <div class="info-content">
            <strong><?php _e('Modo de Edición:', 'aura-suite'); ?></strong>
            <?php if ($transaction['status'] === 'approved' && $can_edit_all): ?>
                <p><?php _e('Esta transacción fue aprobada. Al editarla, volverá a estado "Pendiente" y requerirá nueva aprobación contable.', 'aura-suite'); ?></p>
            <?php elseif ($transaction['status'] === 'rejected'): ?>
                <p><?php _e('Al guardar los cambios, esta transacción volverá a estado "Pendiente" y será enviada nuevamente para su revisión y aprobación.', 'aura-suite'); ?></p>
            <?php else: ?>
                <p><?php _e('Los campos modificados se resaltarán automáticamente. Todos los cambios quedarán auditados y registrados en el historial.', 'aura-suite'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="aura-transaction-container aura-edit-layout">
        <!-- Formulario Principal -->
        <div class="aura-transaction-form-main">
            <form id="aura-transaction-edit-form" method="post">
                <input type="hidden" id="transaction_id" name="transaction_id" value="<?php echo $transaction_id; ?>">
                <input type="hidden" name="action" value="aura_update_transaction">
                <input type="hidden" id="original_data" value='<?php echo htmlspecialchars(json_encode($transaction), ENT_QUOTES, 'UTF-8'); ?>'>
                
                <!-- Selector de Tipo de Transacción (BLOQUEADO) -->
                <div class="aura-form-section aura-transaction-type-selector disabled">
                    <div class="aura-toggle-switch aura-toggle-three">
                        <input type="radio" id="type-income" name="transaction_type" value="income" 
                               <?php checked($tx_type, 'income'); ?> disabled>
                        <label for="type-income" class="income-label">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <?php _e('Ingreso', 'aura-suite'); ?>
                        </label>
                        
                        <input type="radio" id="type-expense" name="transaction_type" value="expense" 
                               <?php checked($tx_type, 'expense'); ?> disabled>
                        <label for="type-expense" class="expense-label">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                            <?php _e('Egreso', 'aura-suite'); ?>
                        </label>

                        <input type="radio" id="type-capital" name="transaction_type" value="capital" 
                               <?php checked($tx_type, 'capital'); ?> disabled>
                        <label for="type-capital" class="capital-label">
                            <span class="dashicons dashicons-building"></span>
                            <?php _e('Gasto de Capital', 'aura-suite'); ?>
                        </label>
                        
                        <span class="toggle-slider"></span>
                    </div>
                    <p class="description" style="margin-top:8px;display:flex;align-items:center;justify-content:center;gap:6px;color:#64748b;">
                        <span class="dashicons dashicons-lock" style="font-size:16px;width:16px;height:16px;"></span>
                        <?php _e('El tipo de transacción es fijo para preservar la integridad contable.', 'aura-suite'); ?>
                    </p>
                </div>
                
                <!-- Campos Principales -->
                <div class="aura-form-section">
                    <h2><?php _e('Información General', 'aura-suite'); ?></h2>
                    <p class="aura-section-help"><?php _e('Estos campos definen el impacto contable y ayudan a prevenir errores al editar.', 'aura-suite'); ?></p>
                    
                    <!-- Fila 1: Fecha | Categoría del gasto -->
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
                            <label for="expense_category_id" class="required">
                                <?php _e('Categoría del gasto', 'aura-suite'); ?>
                            </label>
                            <select id="expense_category_id" name="expense_category_id" data-original="<?php echo esc_attr($expense_category_current); ?>" required>
                                <option value=""><?php _e('Seleccionar...', 'aura-suite'); ?></option>
                                <?php 
                                $current_expense_cat_desc = '';
                                foreach ($categories as $category):
                                    if ((int)$category->id === (int)$expense_category_current) {
                                        $current_expense_cat_desc = $category->description ?? '';
                                    }
                                    $type_label = $category->type === 'income' ? __('Ingreso', 'aura-suite')
                                                : ($category->type === 'expense' ? __('Egreso', 'aura-suite')
                                                : __('General', 'aura-suite'));
                                ?>
                                    <option 
                                        value="<?php echo esc_attr($category->id); ?>" 
                                        data-type="<?php echo esc_attr($category->type); ?>"
                                        data-description="<?php echo esc_attr($category->description ?? ''); ?>"
                                        <?php selected($expense_category_current, $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                        (<?php echo $type_label; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-tag"></span>
                            <p class="description" style="margin-top:4px;font-size:11px;color:#8c8f94;">
                                <?php _e('¿En qué se usó el dinero? (detalle del gasto)', 'aura-suite'); ?>
                            </p>
                            <div id="expense-category-desc-hint" class="aura-category-desc-box" style="<?php echo empty(trim($current_expense_cat_desc)) ? 'display:none;' : ''; ?>margin-top:6px;padding:7px 12px;background:#f0fdf4;border-left:3px solid #10b981;border-radius:6px;font-size:12px;color:#166534;line-height:1.45;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                                <span style="font-weight:700;">ℹ️ <?php _e('Descripción:', 'aura-suite'); ?></span> <span class="desc-text" style="color:#14532d;"><?php echo esc_html($current_expense_cat_desc); ?></span>
                            </div>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Fila 2: Área / Programa | Categoría del presupuesto -->
                    <div class="aura-form-row">
                        <div class="aura-form-field aura-field-50">
                            <label for="transaction_area_id">
                                <?php _e('Área / Programa', 'aura-suite'); ?>
                                <?php if ($_edit_view_own) : ?>
                                    <small style="color:#8c8f94;font-weight:normal;"><?php _e('(asignada a tu área)', 'aura-suite'); ?></small>
                                <?php endif; ?>
                            </label>
                            <?php if ($_edit_view_own && $_edit_user_area_id) : ?>
                                <?php
                                $ua = array_filter($_edit_areas, function($a) use ($_edit_user_area_id) {
                                    return (int) $a->id === $_edit_user_area_id;
                                });
                                $ua = reset($ua);
                                ?>
                                <input
                                    type="hidden"
                                    id="transaction_area_id"
                                    name="area_id"
                                    value="<?php echo esc_attr($_edit_user_area_id); ?>"
                                    data-original="<?php echo esc_attr($_edit_user_area_id); ?>"
                                    data-area-name="<?php echo $ua ? esc_attr($ua->name) : ''; ?>">
                                <div style="display:flex;align-items:center;gap:8px;padding:6px 8px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;">
                                    <?php if ($ua) : ?>
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:<?php echo esc_attr($ua->color); ?>;flex-shrink:0;"></span>
                                    <strong><?php echo esc_html($ua->name); ?></strong>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <select id="transaction_area_id" name="area_id" data-original="<?php echo esc_attr($transaction['area_id']); ?>">
                                    <option value=""><?php _e('General (sin área)', 'aura-suite'); ?></option>
                                    <?php foreach ($_edit_areas as $_ea) : ?>
                                    <option value="<?php echo (int) $_ea->id; ?>" <?php selected($transaction['area_id'], $_ea->id); ?>>
                                        <?php echo esc_html($_ea->name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="aura-field-icon dashicons dashicons-building"></span>
                            <?php endif; ?>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>

                        <div id="aura-budget-category-field" class="aura-form-field aura-field-50" style="display:none;">
                            <label for="category_id">
                                <?php _e('Categoría del presupuesto', 'aura-suite'); ?>
                                <small style="color:#8c8f94;font-weight:normal;"><?php _e('(clasificación del gasto)', 'aura-suite'); ?></small>
                            </label>
                            <select id="category_id" name="category_id" data-original="<?php echo esc_attr($transaction['category_id']); ?>" disabled>
                                <option value=""><?php _e('Seleccionar categoría...', 'aura-suite'); ?></option>
                                <?php foreach ($categories as $category) :
                                    $type_label = $category->type === 'income' ? __('Ingreso', 'aura-suite')
                                                : ($category->type === 'expense' ? __('Egreso', 'aura-suite')
                                                : __('General', 'aura-suite'));
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
                            <p class="description" style="margin-top:4px;font-size:11px;color:#8c8f94;">
                                <?php _e('¿En qué rubro se gasta? Permite auditar en qué se consumen los fondos del área.', 'aura-suite'); ?>
                            </p>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>

                    <div id="aura-budget-status-banner" style="display:none;margin-bottom:12px;" role="status"></div>
                    
                    <!-- Fila 3: Monto | Método de Pago -->
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
                                    value="<?php echo esc_attr($amount_formatted); ?>"
                                    data-original="<?php echo esc_attr($amount_formatted); ?>"
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

                    <!-- Fila 4: Cuentas de Origen y Destino -->
                    <div class="aura-form-row aura-account-links">
                        <div class="aura-form-field aura-field-50 aura-account-field aura-account-source">
                            <label for="source_account_id" class="required">
                                <?php _e('Cuenta Origen', 'aura-suite'); ?>
                            </label>
                            <select id="source_account_id" name="source_account_id" data-original="<?php echo esc_attr($transaction['source_account_id']); ?>">
                                <option value=""><?php _e('Seleccionar cuenta origen...', 'aura-suite'); ?></option>
                                <?php foreach ($finance_accounts as $account) : ?>
                                <option
                                    value="<?php echo (int) $account->id; ?>"
                                    data-balance="<?php echo esc_attr((string) (float) $account->current_balance); ?>"
                                    data-currency="<?php echo esc_attr($account->currency); ?>"
                                    <?php selected($transaction['source_account_id'], $account->id); ?>>
                                    <?php echo esc_html($account->name . ' · ' . $account->currency . ' · ' . number_format((float) $account->current_balance, 2)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-money-alt"></span>
                            <p class="description" id="source-account-help"><?php _e('Obligatoria para egresos. Usa la Caja Chica del área si tienes el dinero en mano, o la cuenta de Banco si el pago es por transferencia institucional.', 'aura-suite'); ?></p>
                            <div class="aura-account-impact" id="source-account-impact" style="display:none;"></div>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>

                        <div class="aura-form-field aura-field-50 aura-account-field aura-account-destination">
                            <label for="destination_account_id" class="required">
                                <?php _e('Cuenta Destino', 'aura-suite'); ?>
                            </label>
                            <select id="destination_account_id" name="destination_account_id" data-original="<?php echo esc_attr($transaction['destination_account_id']); ?>">
                                <option value=""><?php _e('Seleccionar cuenta destino...', 'aura-suite'); ?></option>
                                <?php foreach ($finance_accounts as $account) : ?>
                                <option
                                    value="<?php echo (int) $account->id; ?>"
                                    data-balance="<?php echo esc_attr((string) (float) $account->current_balance); ?>"
                                    data-currency="<?php echo esc_attr($account->currency); ?>"
                                    <?php selected($transaction['destination_account_id'], $account->id); ?>>
                                    <?php echo esc_html($account->name . ' · ' . $account->currency . ' · ' . number_format((float) $account->current_balance, 2)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="aura-field-icon dashicons dashicons-store"></span>
                            <p class="description" id="destination-account-help"><?php _e('Obligatoria para ingresos. Aquí entrará el dinero.', 'aura-suite'); ?></p>
                            <div class="aura-account-impact" id="destination-account-impact" style="display:none;"></div>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Fila 5: Descripción -->
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
                    
                    <!-- Fila 6: Referencia | Pagador / Beneficiario -->
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
                            <span class="aura-field-icon dashicons dashicons-tag"></span>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                        
                        <div class="aura-form-field aura-field-50">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                                <label for="recipient_payer" style="margin-bottom:0;font-weight:600;">
                                    <span class="label-income"><?php _e('Pagador / Empresa / Tercero', 'aura-suite'); ?></span>
                                    <span class="label-expense"><?php _e('Beneficiario / Proveedor / Tercero', 'aura-suite'); ?></span>
                                    <span class="label-capital"><?php _e('Proveedor / Tercero', 'aura-suite'); ?></span>
                                </label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <button type="button" class="button button-secondary aura-btn-open-tp-explorer" data-target-input="#recipient_payer" data-target-hidden="#third_party_id" style="font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:4px;height:26px;line-height:24px;padding:0 8px;" title="<?php esc_attr_e('Abrir catálogo y explorador clasificado por rol contable', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-groups" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Seleccionar Tercero', 'aura-suite'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=aura-third-parties')); ?>" target="_blank" style="font-size:11.5px;color:#64748b;text-decoration:none;font-weight:500;" title="<?php esc_attr_e('Abrir directorio de terceros y empresas', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-admin-generic" style="font-size:12px;width:12px;height:12px;line-height:12px;vertical-align:middle;"></span> <?php _e('Gestionar', 'aura-suite'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="aura-counterparty-autocomplete-wrap" style="position:relative;">
                                <input 
                                    type="text" 
                                    id="recipient_payer" 
                                    name="recipient_payer"
                                    value="<?php echo esc_attr($transaction['recipient_payer']); ?>"
                                    data-original="<?php echo esc_attr($transaction['recipient_payer']); ?>"
                                    autocomplete="off"
                                    placeholder="<?php _e('Escribe o busca empresa, tienda o persona...', 'aura-suite'); ?>">
                                <input type="hidden" id="third_party_id" name="third_party_id" 
                                       value="<?php echo esc_attr($transaction['third_party_id'] ?? ''); ?>" 
                                       data-original="<?php echo esc_attr($transaction['third_party_id'] ?? ''); ?>"
                                       data-original-name="<?php echo esc_attr($third_party_data ? ($third_party_data['commercial_name'] ?: $third_party_data['full_name']) : ''); ?>">
                                <span class="aura-field-icon dashicons dashicons-businessman"></span>
                                
                                <div id="aura-tp-preview" class="aura-entity-preview-card" style="<?php echo $third_party_data ? '' : 'display:none;'; ?>">
                                    <div id="aura-tp-preview-avatar" class="avatar-box">
                                        <?php if ($third_party_data && !empty($third_party_data['logo_id'])) : 
                                            $tp_logo_url = wp_get_attachment_image_url($third_party_data['logo_id'], 'thumbnail');
                                        ?>
                                            <img src="<?php echo esc_url($tp_logo_url); ?>" width="28" height="28">
                                        <?php else : ?>
                                            <span class="dashicons dashicons-businessman" style="font-size:16px;width:16px;height:16px;color:#64748b;"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="entity-info">
                                        <span id="aura-tp-preview-text" class="entity-name"><?php echo esc_html($third_party_data ? ($third_party_data['commercial_name'] ?: $third_party_data['full_name']) : ''); ?></span>
                                        <span id="aura-tp-preview-badge" class="aura-pill aura-pill--muted entity-badge"><?php echo esc_html($third_party_data ? ($third_party_data['accounting_role'] ?: 'Tercero') : ''); ?></span>
                                    </div>
                                    <a href="#" id="aura-tp-clear" class="entity-clear-btn" title="<?php _e('Desvincular tercero', 'aura-suite'); ?>">✕</a>
                                </div>
                            </div>
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
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                                <label for="related_user_search" style="margin-bottom:0;font-weight:600;">
                                    <?php _e('Usuario Vinculado (Sistema)', 'aura-suite'); ?>
                                </label>
                                <button type="button" class="button button-secondary aura-btn-open-tp-explorer" data-default-tab="employee" data-target-input="#related_user_search" data-target-hidden="#related_user_id" style="font-size:11.5px;font-weight:600;display:inline-flex;align-items:center;gap:4px;height:26px;line-height:24px;padding:0 8px;" title="<?php esc_attr_e('Seleccionar usuario o colaborador del sistema', 'aura-suite'); ?>">
                                    <span class="dashicons dashicons-admin-users" style="font-size:13px;width:13px;height:13px;line-height:13px;"></span> <?php _e('Seleccionar Usuario', 'aura-suite'); ?>
                                </button>
                            </div>
                            <div class="aura-user-autocomplete-wrap" style="position:relative;">
                                <input type="text"
                                       id="related_user_search"
                                       placeholder="<?php _e('Buscar usuario por nombre o email...', 'aura-suite'); ?>"
                                       autocomplete="off"
                                       class="regular-text"
                                       value="<?php echo esc_attr($related_user_display_name); ?>"
                                       data-original="<?php echo esc_attr($related_user_display_name); ?>">
                                <input type="hidden" id="related_user_id" name="related_user_id"
                                       value="<?php echo esc_attr($transaction['related_user_id'] ?? ''); ?>"
                                       data-original="<?php echo esc_attr($transaction['related_user_id'] ?? ''); ?>"
                                       data-original-name="<?php echo esc_attr($related_user_display_name); ?>">
                                <span class="aura-field-icon dashicons dashicons-admin-users"></span>
                                
                                <div id="aura-user-preview" class="aura-entity-preview-card" style="<?php echo $related_user_data ? '' : 'display:none;'; ?>">
                                    <div class="avatar-box">
                                        <img id="aura-user-avatar"
                                             src="<?php echo $related_user_data ? esc_url(get_avatar_url($related_user_data->ID, array('size' => 28))) : ''; ?>"
                                             width="28" height="28" style="border-radius:4px;">
                                    </div>
                                    <div class="entity-info">
                                        <span id="aura-user-name" class="entity-name"><?php echo esc_html($related_user_display_name); ?></span>
                                        <span class="aura-pill aura-pill--info entity-badge"><?php _e('Usuario Sistema', 'aura-suite'); ?></span>
                                    </div>
                                    <a href="#" id="aura-user-clear" class="entity-clear-btn" title="<?php _e('Quitar usuario', 'aura-suite'); ?>">✕</a>
                                </div>
                            </div>
                            <span class="change-indicator" style="display: none;">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Modificado', 'aura-suite'); ?>
                            </span>
                        </div>
                        <div class="aura-form-field aura-field-40">
                            <label for="related_user_concept" class="aura-label-with-help" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;font-weight:600;">
                                <span><?php _e('Concepto de Vinculación', 'aura-suite'); ?></span>
                                <span class="aura-tooltip-wrap">
                                    <span class="aura-help-icon" tabindex="0" role="button" aria-label="<?php esc_attr_e('Guía de Conceptos', 'aura-suite'); ?>" style="color:#2563eb;cursor:help;display:inline-flex;align-items:center;">
                                        <span class="dashicons dashicons-editor-help" style="font-size:16px;width:16px;height:16px;"></span>
                                    </span>
                                    <span class="aura-tooltip-box" style="width:280px;white-space:normal;text-align:left;line-height:1.45;font-weight:normal;">
                                        <strong style="display:block;margin-bottom:4px;color:#93c5fd;"><?php _e('Guía de Conceptos:', 'aura-suite'); ?></strong>
                                        • <strong><?php _e('Salario/Nómina:', 'aura-suite'); ?></strong> <?php _e('Pago de sueldo. Se refleja en el Libro Mayor del empleado.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Pago a Usuario:', 'aura-suite'); ?></strong> <?php _e('Salida a terceros o proveedores.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Cobro a Usuario:', 'aura-suite'); ?></strong> <?php _e('Ingreso por cuotas o servicios.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Beca Asignada:', 'aura-suite'); ?></strong> <?php _e('Apoyo económico o subsidio.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Pago Préstamo:', 'aura-suite'); ?></strong> <?php _e('Abono o liquidación de crédito.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Reembolso:', 'aura-suite'); ?></strong> <?php _e('Devolución de dinero.', 'aura-suite'); ?><br>
                                        • <strong><?php _e('Reembolso Gastos:', 'aura-suite'); ?></strong> <?php _e('Gastos pagados por el usuario.', 'aura-suite'); ?>
                                    </span>
                                </span>
                            </label>
                            <select id="related_user_concept" name="related_user_concept"
                                    data-original="<?php echo esc_attr($transaction['related_user_concept'] ?? ''); ?>">
                                <option value=""><?php _e('— Seleccionar —', 'aura-suite'); ?></option>
                                <?php
                                $concepts_edit = array(
                                    'payment_to_user'       => __('Pago realizado a un usuario', 'aura-suite'),
                                    'charge_to_user'        => __('Cobro realizado a un usuario', 'aura-suite'),
                                    'salary'                => __('Pago de salario/nómina', 'aura-suite'),
                                    'scholarship'           => __('Beca asignada', 'aura-suite'),
                                    'loan_payment'          => __('Pago de préstamo', 'aura-suite'),
                                    'refund'                => __('Reembolso', 'aura-suite'),
                                    'expense_reimbursement' => __('Reembolso de gastos', 'aura-suite'),
                                );
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
                    
                    <!-- Banner Explicativo Dinámico del Concepto Seleccionado -->
                    <div id="aura-concept-info-banner" class="aura-concept-info-banner" style="display: none;">
                        <span class="dashicons dashicons-info aura-concept-banner-icon"></span>
                        <div class="aura-concept-banner-body">
                            <strong id="aura-concept-banner-title"></strong>
                            <p id="aura-concept-banner-desc"></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Información Adicional (Colapsable) -->
                <?php $has_additional_data = !empty($transaction['notes']) || !empty($tags_string) || !empty($transaction['receipt_file']); ?>
                <div class="aura-form-section aura-collapsible <?php echo $has_additional_data ? 'aura-collapsible-open' : ''; ?>">
                    <h2 class="aura-collapsible-header" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;user-select:none;">
                        <span style="display:flex;align-items:center;gap:8px;">
                            <span class="dashicons <?php echo $has_additional_data ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'; ?> toggle-icon"></span>
                            <?php _e('Información Adicional (Opcional)', 'aura-suite'); ?>
                        </span>
                        <span style="font-size:12px;font-weight:normal;color:#64748b;">
                            <?php echo $has_additional_data ? __('Contiene datos adjuntos', 'aura-suite') : __('Notas, etiquetas y comprobante', 'aura-suite'); ?>
                        </span>
                    </h2>
                    
                    <div class="aura-collapsible-content" style="<?php echo $has_additional_data ? 'display:block;' : 'display:none;'; ?>">
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
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;gap:8px;flex-wrap:wrap;">
                                    <label for="aura-tag-new-input" style="margin-bottom:0;font-weight:600;">
                                        <span class="dashicons dashicons-tag" style="font-size:16px;width:16px;height:16px;vertical-align:middle;color:#2563eb;margin-right:2px;"></span>
                                        <?php _e('Etiquetas (#tags)', 'aura-suite'); ?>
                                        <small style="color:#8c8f94;font-weight:normal;"><?php _e('(proyectos, sedes, campañas o taxonomía contable)', 'aura-suite'); ?></small>
                                    </label>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=aura-financial-tags')); ?>" target="_blank" style="font-size:11.5px;color:#64748b;text-decoration:none;font-weight:500;" title="<?php esc_attr_e('Abrir módulo de administración de etiquetas', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-admin-generic" style="font-size:12px;width:12px;height:12px;vertical-align:middle;"></span> <?php _e('Catálogo de Etiquetas', 'aura-suite'); ?>
                                    </a>
                                </div>

                                <!-- Componente Interactivo de Etiquetas -->
                                <div class="aura-tags-manager-box" id="aura-tags-manager-box">
                                    <!-- Contenedor de Chips Seleccionados + Input en Línea -->
                                    <div class="aura-tags-input-container" id="aura-tags-input-container">
                                        <div id="aura-selected-tags-chips" class="aura-selected-tags-chips"></div>
                                        <div class="aura-tag-input-inline-wrap">
                                            <input 
                                                type="text" 
                                                id="aura-tag-new-input" 
                                                class="aura-tag-inline-field"
                                                placeholder="<?php esc_attr_e('Escribe una etiqueta y presiona Enter o coma...', 'aura-suite'); ?>"
                                                autocomplete="off">
                                            <button type="button" id="btn-add-tag-chip" class="aura-btn-add-tag" title="<?php esc_attr_e('Añadir etiqueta', 'aura-suite'); ?>">
                                                <span class="dashicons dashicons-plus"></span>
                                                <span><?php _e('Añadir', 'aura-suite'); ?></span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Input oculto sincronizado con la BD -->
                                    <input 
                                        type="hidden" 
                                        id="tags" 
                                        name="tags" 
                                        value="<?php echo esc_attr($existing_tags_val); ?>"
                                        data-original="<?php echo esc_attr($existing_tags_val); ?>">

                                    <span class="change-indicator" style="display: none;">
                                        <span class="dashicons dashicons-marker"></span>
                                        <?php _e('Modificado', 'aura-suite'); ?>
                                    </span>

                                    <!-- Catálogo / Nube de Etiquetas Existentes para Inserción Rápida -->
                                    <?php if (!empty($available_tags)) : ?>
                                    <div class="aura-tags-suggestions-panel">
                                        <div class="aura-tags-suggestions-header">
                                            <span class="dashicons dashicons-randomize" style="font-size:13px;width:13px;height:13px;"></span>
                                            <span><?php _e('Etiquetas existentes (haz clic para seleccionar/deseleccionar):', 'aura-suite'); ?></span>
                                        </div>
                                        <div class="aura-tags-cloud-pills" id="aura-available-tags-pills">
                                            <?php foreach ($available_tags as $tag_name => $tag_count) : ?>
                                            <button type="button" class="aura-tag-pill-btn" data-tag="<?php echo esc_attr($tag_name); ?>">
                                                <span class="tag-hash">#</span><span class="tag-txt"><?php echo esc_html($tag_name); ?></span>
                                                <span class="tag-cnt"><?php echo esc_html($tag_count); ?></span>
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php else : ?>
                                    <p class="description" style="font-size:11.5px;color:#94a3b8;margin-top:6px;">
                                        <span class="dashicons dashicons-info" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span>
                                        <?php _e('No hay etiquetas creadas todavía. Escribe cualquier nombre arriba para crear la primera.', 'aura-suite'); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="aura-form-row">
                            <div class="aura-form-field aura-field-100">
                                <label for="receipt_file_upload_input" class="aura-label-with-help">
                                    <span><?php _e('Comprobantes y Archivos Adjuntos', 'aura-suite'); ?></span>
                                    <span class="aura-help-icon aura-has-tooltip" data-tooltip="<?php esc_attr_e('Puedes adjuntar uno o varios comprobantes (Facturas, recibos o vouchers en JPG, PNG o PDF hasta 5MB). Se sincronizan automáticamente con Google Drive.', 'aura-suite'); ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e('Ayuda: Comprobantes', 'aura-suite'); ?>">
                                        <span class="dashicons dashicons-editor-help"></span>
                                    </span>
                                </label>

                                <?php
                                $existing_receipts = array();
                                if (!empty($transaction['receipt_file'])) {
                                    $decoded_receipts = json_decode($transaction['receipt_file'], true);
                                    $raw_receipt_items = is_array($decoded_receipts) ? $decoded_receipts : array_map('trim', explode(',', $transaction['receipt_file']));
                                    
                                    foreach ($raw_receipt_items as $r_item) {
                                        if (empty($r_item)) continue;
                                        if (is_array($r_item)) {
                                            $r_url = $r_item['url'] ?? ($r_item['file_url'] ?? '');
                                            $r_name = $r_item['name'] ?? ($r_item['filename'] ?? '');
                                            $r_is_drive = !empty($r_item['is_drive']) || (class_exists('Aura_Drive_Manager') && Aura_Drive_Manager::is_drive_url($r_url));
                                        } else {
                                            $r_url = $r_item;
                                            $r_is_drive = class_exists('Aura_Drive_Manager') && Aura_Drive_Manager::is_drive_url($r_url);
                                            $r_name = basename($r_url);
                                        }
                                        if (empty($r_url)) continue;
                                        
                                        if ($r_is_drive && (empty($r_name) || strpos($r_name, 'view') !== false || strpos($r_name, '?') !== false)) {
                                            $r_name = __('Documento en Google Drive', 'aura-suite');
                                        }
                                        
                                        $r_file_id = $r_is_drive ? Aura_Drive_Manager::extract_file_id($r_url) : '';
                                        $r_preview_url = $r_is_drive ? Aura_Drive_Manager::get_preview_url($r_url) : (strpos($r_url, 'http') === 0 ? $r_url : content_url('uploads/aura-finance/receipts/' . $r_url));
                                        $r_download_url = $r_is_drive ? Aura_Drive_Manager::get_download_url($r_url) : $r_preview_url;
                                        $r_thumb_url = $r_is_drive ? Aura_Drive_Manager::get_thumbnail_url($r_url) : $r_preview_url;
                                        $r_is_pdf = (bool) preg_match('/\.pdf($|\?)/i', $r_name) || (bool) preg_match('/\.pdf($|\?)/i', $r_url);
                                        $r_is_img = (bool) preg_match('/\.(jpg|jpeg|png|webp|gif)($|\?)/i', $r_name) || (bool) preg_match('/\.(jpg|jpeg|png|webp|gif)($|\?)/i', $r_url);

                                        $existing_receipts[] = array(
                                            'url'           => $r_url,
                                            'name'          => $r_name,
                                            'is_drive'      => $r_is_drive,
                                            'file_id'       => $r_file_id,
                                            'preview_url'   => $r_preview_url,
                                            'download_url'  => $r_download_url,
                                            'thumbnail_url' => $r_thumb_url,
                                            'is_pdf'        => $r_is_pdf,
                                            'is_img'        => $r_is_img,
                                        );
                                    }
                                }
                                ?>

                                <!-- Contenedor de Gestión de Comprobantes Múltiples -->
                                <div class="aura-receipts-manager" id="aura-receipts-manager" data-initial='<?php echo esc_attr(json_encode($existing_receipts)); ?>'>
                                    <!-- Lista de Archivos Adjuntos Existentes/Nuevos -->
                                    <div id="aura-attached-receipts-list" class="aura-attached-receipts-list" style="display:<?php echo empty($existing_receipts) ? 'none' : 'flex'; ?>;flex-direction:column;gap:10px;margin-bottom:12px;">
                                        <?php foreach ($existing_receipts as $idx => $rec): ?>
                                        <div class="aura-receipt-item-card" data-index="<?php echo $idx; ?>" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;transition:all 0.2s ease;">
                                            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                                                <div class="receipt-card-thumb-wrap" style="width:40px;height:40px;border-radius:6px;overflow:hidden;background:#e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                    <?php if ($rec['is_img']): ?>
                                                        <img src="<?php echo esc_url($rec['thumbnail_url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                                    <?php elseif ($rec['is_pdf']): ?>
                                                        <span class="dashicons dashicons-pdf" style="color:#ef4444;font-size:24px;width:24px;height:24px;"></span>
                                                    <?php elseif ($rec['is_drive']): ?>
                                                        <span class="dashicons dashicons-cloud" style="color:#2563eb;font-size:24px;width:24px;height:24px;"></span>
                                                    <?php else: ?>
                                                        <span class="dashicons dashicons-media-document" style="color:#64748b;font-size:24px;width:24px;height:24px;"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="min-width:0;line-height:1.35;">
                                                    <div class="receipt-card-name" style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:320px;" title="<?php echo esc_attr($rec['name']); ?>">
                                                        <?php echo esc_html($rec['name']); ?>
                                                    </div>
                                                    <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                                                        <?php if ($rec['is_drive']): ?>
                                                            <span class="aura-badge aura-badge-blue" style="font-size:10.5px;padding:2px 7px;border-radius:10px;display:inline-flex;align-items:center;gap:3px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">
                                                                <span class="dashicons dashicons-cloud" style="font-size:12px;width:12px;height:12px;"></span> Google Drive
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="aura-badge" style="font-size:10.5px;padding:2px 7px;border-radius:10px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;">
                                                                📎 Local
                                                            </span>
                                                        <?php endif; ?>
                                                        <span style="font-size:11px;color:#94a3b8;"><?php echo $rec['is_pdf'] ? 'PDF' : ($rec['is_img'] ? 'Imagen' : 'Documento'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="receipt-card-actions" style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                                                <a href="<?php echo esc_url($rec['preview_url']); ?>" target="_blank" class="button button-small" style="display:inline-flex;align-items:center;gap:4px;" title="<?php esc_attr_e('Ver comprobante', 'aura-suite'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                    <span><?php _e('Ver', 'aura-suite'); ?></span>
                                                </a>
                                                <button type="button" class="button button-small aura-btn-remove-receipt" data-index="<?php echo $idx; ?>" style="color:#ef4444;border-color:#fca5a5;" title="<?php esc_attr_e('Quitar este comprobante', 'aura-suite'); ?>">
                                                    <span class="dashicons dashicons-trash" style="color:#ef4444;"></span>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Uploader Drag & Drop y Selección de Archivos -->
                                    <div class="aura-file-upload aura-multi-file-upload">
                                        <input 
                                            type="file" 
                                            id="receipt_file_upload_input" 
                                            name="receipt_file_upload"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            multiple>
                                        <label for="receipt_file_upload_input" class="file-upload-label" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:18px 16px;border:2px dashed #cbd5e1;border-radius:8px;background:#f8fafc;cursor:pointer;transition:border-color 0.2s ease;">
                                            <span class="dashicons dashicons-upload" style="font-size:32px;width:32px;height:32px;color:#3b82f6;margin-bottom:6px;"></span>
                                            <span class="file-label-text" style="font-weight:600;font-size:13px;color:#1e293b;">
                                                <?php _e('+ Adjuntar comprobante (puedes subir varios)', 'aura-suite'); ?>
                                            </span>
                                            <span class="file-label-sub" style="font-size:11.5px;color:#64748b;margin-top:2px;">
                                                <?php _e('JPG, PNG o PDF (hasta 5MB). Se suben directamente a Google Drive.', 'aura-suite'); ?>
                                            </span>
                                        </label>
                                        <div class="aura-upload-status" style="display:none;margin-top:8px;font-size:12px;color:#2563eb;text-align:center;">
                                            <span class="dashicons dashicons-update spin"></span>
                                            <span class="upload-status-text"><?php _e('Subiendo archivo...', 'aura-suite'); ?></span>
                                        </div>
                                    </div>

                                    <!-- Input oculto serializado que se envía en el POST -->
                                    <input type="hidden" id="receipt_file_url" name="receipt_file" value="<?php echo esc_attr($transaction['receipt_file']); ?>" data-original="<?php echo esc_attr($transaction['receipt_file']); ?>">
                                    <span class="change-indicator" style="display: none;">
                                        <span class="dashicons dashicons-marker"></span>
                                        <?php _e('Modificado', 'aura-suite'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Motivo del Cambio (aparece si hay cambios significativos) -->
                <div class="aura-form-section" id="change-reason-section" style="display: none;">
                    <h2>
                        <span class="dashicons dashicons-warning" style="color: #f39c12;"></span>
                        <?php _e('Motivo del Cambio Requerido', 'aura-suite'); ?>
                    </h2>
                    
                    <div class="aura-info-box aura-info-warning">
                        <span class="dashicons dashicons-info"></span>
                        <div class="info-content">
                            <p id="change-reason-message"><?php _e('Se detectaron modificaciones clave. Por favor describe el motivo del cambio.', 'aura-suite'); ?></p>
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
                    <button type="button" class="button button-link" id="reset-all-fields">
                        <span class="dashicons dashicons-undo"></span>
                        <?php _e('Restaurar Originales', 'aura-suite'); ?>
                    </button>
                    
                    <div class="primary-actions" style="display:flex;gap:10px;align-items:center;">
                        <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-secondary button-large">
                            <?php _e('Cancelar', 'aura-suite'); ?>
                        </a>
                        
                        <button type="submit" class="button button-primary button-large" id="save-transaction-btn">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Guardar Cambios', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sidebar: Vista Previa en Vivo + Resumen de Cambios + Historial + Metadatos -->
        <aside class="aura-transaction-sidebar">
            
            <!-- Panel de Vista Previa en Tiempo Real -->
            <div class="aura-transaction-preview">
                <div class="preview-card">
                    <div class="preview-header-bar" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <h3 style="margin:0;font-size:15px;font-weight:700;color:#1e293b;"><?php _e('Vista Previa en Vivo', 'aura-suite'); ?></h3>
                        <span class="aura-preview-status-tag" style="background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;">
                            <?php _e('Modo Edición', 'aura-suite'); ?>
                        </span>
                    </div>
                    
                    <div class="preview-content">
                        <div class="preview-badge">
                            <span class="badge-type <?php echo esc_attr($tx_type); ?>">
                                <?php if ($tx_type === 'income'): ?>
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                    <?php _e('Ingreso', 'aura-suite'); ?>
                                <?php elseif ($tx_type === 'capital'): ?>
                                    <span class="dashicons dashicons-building"></span>
                                    <?php _e('Gasto de Capital', 'aura-suite'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-arrow-down-alt"></span>
                                    <?php _e('Egreso', 'aura-suite'); ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="preview-amount">
                            <span class="amount-symbol">$</span>
                            <span class="amount-value"><?php echo number_format($amount_raw, 2); ?></span>
                        </div>

                        <div class="preview-date">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <span class="date-text"><?php echo esc_html($transaction_date_formatted); ?></span>
                        </div>

                        <div class="preview-area" style="<?php echo !empty($transaction['area_id']) ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-building"></span>
                            <span class="area-name"><?php echo esc_html($history_area_map[(string)$transaction['area_id']] ?? ''); ?></span>
                        </div>

                        <div class="preview-expense-category">
                            <span class="dashicons dashicons-tag"></span>
                            <span class="exp-cat-label" style="font-size:10px;opacity:.7;display:block;"><?php _e('Categoría del gasto', 'aura-suite'); ?></span>
                            <span class="expense-category-name"><?php echo esc_html($history_category_map[(string)$expense_category_current] ?? __('Sin categoría', 'aura-suite')); ?></span>
                        </div>

                        <div class="preview-category" style="<?php echo !empty($transaction['category_id']) && $transaction['category_id'] != $expense_category_current ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-category"></span>
                            <span class="pres-cat-label" style="font-size:10px;opacity:.7;display:block;"><?php _e('Presupuesto', 'aura-suite'); ?></span>
                            <span class="category-name"><?php echo esc_html($history_category_map[(string)$transaction['category_id']] ?? ''); ?></span>
                        </div>

                        <div class="preview-payment" style="<?php echo !empty($transaction['payment_method']) ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-money"></span>
                            <span class="payment-name"><?php echo esc_html(ucfirst($transaction['payment_method'])); ?></span>
                        </div>

                        <div class="preview-recipient" style="<?php echo !empty($transaction['recipient_payer']) ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-businessman"></span>
                            <span class="recipient-name"><?php echo esc_html($transaction['recipient_payer']); ?></span>
                        </div>

                        <div class="preview-reference" style="<?php echo !empty($transaction['reference_number']) ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-id"></span>
                            <span class="reference-text"><?php echo esc_html($transaction['reference_number']); ?></span>
                        </div>

                        <div class="preview-description">
                            <span class="description-text"><?php echo esc_html($transaction['description'] ?: __('Sin descripción', 'aura-suite')); ?></span>
                        </div>

                        <div class="preview-tags" style="<?php echo !empty($tags_string) ? '' : 'display: none;'; ?>">
                            <span class="dashicons dashicons-tag"></span>
                            <span class="tags-list"><?php echo esc_html($tags_string); ?></span>
                        </div>

                        <div class="preview-receipt" style="<?php echo !empty($transaction['receipt_file']) ? 'display:flex;align-items:center;gap:6px;margin-top:8px;padding-top:8px;border-top:1px dashed #cbd5e1;' : 'display: none;'; ?>">
                            <span class="dashicons <?php echo (class_exists('Aura_Drive_Manager') && Aura_Drive_Manager::is_drive_url($transaction['receipt_file'] ?? '')) ? 'dashicons-cloud' : 'dashicons-paperclip'; ?>" style="color:#2563eb;font-size:16px;width:16px;height:16px;"></span>
                            <span class="receipt-text" style="font-size:12px;font-weight:600;color:#2563eb;">
                                <?php 
                                if (!empty($transaction['receipt_file'])) {
                                    echo (class_exists('Aura_Drive_Manager') && Aura_Drive_Manager::is_drive_url($transaction['receipt_file'])) ? __('Comprobante en Google Drive ☁️', 'aura-suite') : __('Comprobante: ', 'aura-suite') . esc_html(basename($transaction['receipt_file']));
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="preview-footer">
                        <small><?php _e('Los cambios se reflejan en tiempo real al editar', 'aura-suite'); ?></small>
                    </div>
                </div>
            </div>

            <!-- Resumen de Cambios -->
            <div class="aura-sidebar-widget" id="changes-summary" style="display: none;">
                <h3 style="display:flex;align-items:center;gap:6px;">
                    <span class="dashicons dashicons-marker" style="color:#f59e0b;"></span>
                    <?php _e('Resumen de Cambios', 'aura-suite'); ?>
                </h3>
                <div class="widget-content">
                    <p class="changes-count">
                        <strong id="changes-count" style="color:#d97706;font-size:16px;">0</strong> <?php _e('campos modificados', 'aura-suite'); ?>
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
                                'category_id'            => __('Categoría del presupuesto', 'aura-suite'),
                                'expense_category_id'    => __('Categoría del gasto', 'aura-suite'),
                                'amount'                 => __('Monto', 'aura-suite'),
                                'transaction_date'       => __('Fecha', 'aura-suite'),
                                'description'            => __('Descripción', 'aura-suite'),
                                'source_account_id'      => __('Cuenta origen', 'aura-suite'),
                                'destination_account_id' => __('Cuenta destino', 'aura-suite'),
                                'area_id'                => __('Área / Programa', 'aura-suite'),
                                'payment_method'         => __('Método de Pago', 'aura-suite'),
                                'reference_number'       => __('Referencia', 'aura-suite'),
                                'recipient_payer'        => __('Destinatario / Pagador', 'aura-suite'),
                                'third_party_id'         => __('Beneficiario / Proveedor / Tercero', 'aura-suite'),
                                'related_user_id'        => __('Usuario Vinculado', 'aura-suite'),
                                'related_user_concept'   => __('Concepto de Vinculación', 'aura-suite'),
                                'notes'                  => __('Notas', 'aura-suite'),
                                'tags'                   => __('Etiquetas', 'aura-suite'),
                                'receipt_file'           => __('Comprobante', 'aura-suite'),
                                'status'                 => __('Estado', 'aura-suite'),
                                'status_deletion'        => __('Envío a papelera / Eliminación', 'aura-suite'),
                                'status_resubmitted'     => __('Re-enviada para aprobación', 'aura-suite'),
                                'rejection_reason'       => __('Motivo de rechazo', 'aura-suite'),
                                'category_merged'        => __('Fusión de categoría', 'aura-suite')
                            );
                            $field_label = $field_labels[$entry['field_changed']] ?? $entry['field_changed'];
                            $old_label = $format_history_value($entry['field_changed'], $entry['old_value']);
                            $new_label = $format_history_value($entry['field_changed'], $entry['new_value']);
                        ?>
                        <div class="history-entry">
                            <div class="history-icon">
                                <span class="dashicons dashicons-edit"></span>
                            </div>
                            <div class="history-content">
                                <strong><?php echo $field_label; ?></strong>
                                <div class="history-change">
                                    <span class="old-value"><?php echo esc_html($old_label); ?></span>
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                    <span class="new-value"><?php echo esc_html($new_label); ?></span>
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
                <h3>
                    <span class="dashicons dashicons-backup"></span>
                    <?php _e('Historial de Modificaciones', 'aura-suite'); ?>
                </h3>
                <div class="widget-content">
                    <p class="no-history" style="color:#64748b;font-size:13px;margin:0;">
                        <span class="dashicons dashicons-info" style="color:#94a3b8;vertical-align:middle;margin-right:4px;"></span>
                        <?php _e('Esta transacción aún no tiene modificaciones previas registradas.', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Información Original -->
            <div class="aura-sidebar-widget">
                <h3>
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Información del Registro', 'aura-suite'); ?>
                </h3>
                <div class="widget-content" style="font-size:13px;line-height:1.6;color:#475569;">
                    <p style="margin:0 0 8px;">
                        <strong><?php _e('Creado por:', 'aura-suite'); ?></strong><br>
                        <?php 
                        $creator = get_userdata($transaction['created_by']);
                        echo $creator ? esc_html($creator->display_name) : __('Usuario desconocido', 'aura-suite');
                        ?>
                    </p>
                    <p style="margin:0 0 8px;">
                        <strong><?php _e('Fecha de creación:', 'aura-suite'); ?></strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                    </p>
                    <?php if ($transaction['updated_at'] && $transaction['updated_at'] != $transaction['created_at']): ?>
                    <p style="margin:0;">
                        <strong><?php _e('Última modificación:', 'aura-suite'); ?></strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($transaction['updated_at'])); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>
