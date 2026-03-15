<?php
/**
 * Gestión de Configuraciones del Módulo Financiero
 * 
 * Maneja la configuración de aprobación automática basada en umbrales,
 * excepciones por categoría y módulo, y otras configuraciones globales.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Settings {
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // AJAX handlers para guardar configuraciones
        add_action('wp_ajax_aura_save_financial_settings', array(__CLASS__, 'ajax_save_settings'));
        add_action('wp_ajax_aura_get_financial_settings', array(__CLASS__, 'ajax_get_settings'));
        
        // Dashboard widget de estadísticas de aprobación
        add_action('wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widget'));
    }
    
    /**
     * Determinar el estado inicial de una transacción basado en configuraciones
     * 
     * Esta función implementa la lógica de aprobación automática basada en umbral.
     * Ver prdFinanzas.md - Item 2.6 para especificaciones completas.
     * 
     * @param array $transaction_data Datos de la transacción
     * @return string 'approved' o 'pending'
     * @since 1.0.0
     */
    public static function determine_initial_status($transaction_data) {
        // 1. Obtener configuraciones del sistema
        $auto_approval_enabled = get_option('aura_finance_auto_approval_enabled', false);
        $threshold = (float) get_option('aura_finance_auto_approval_threshold', 0);
        $apply_only_expenses = get_option('aura_finance_auto_approval_apply_to_expenses_only', true);
        
        // Si no está habilitado, todo es pendiente
        if (!$auto_approval_enabled) {
            return 'pending';
        }
        
        // Si el umbral es 0, todo requiere aprobación manual
        if ($threshold <= 0) {
            return 'pending';
        }
        
        // 2. Verificar si aplica según el tipo de transacción
        if ($apply_only_expenses && $transaction_data['transaction_type'] === 'income') {
            // Si solo aplica a egresos y esta es un ingreso, requiere aprobación
            return 'pending';
        }
        
        // 3. Verificar excepciones que fuerzan aprobación manual
        if (self::requires_manual_approval($transaction_data)) {
            return 'pending';
        }
        
        // 4. Comparar monto con umbral
        $amount = (float) $transaction_data['amount'];
        
        if ($amount < $threshold) {
            // Auto-aprobar: monto menor al umbral
            return 'approved';
        }
        
        // Monto igual o mayor al umbral: requiere aprobación manual
        return 'pending';
    }
    
    /**
     * Verificar si una transacción requiere aprobación manual por excepciones
     * 
     * Excepciones incluyen:
     * - Categorías marcadas como "siempre requiere aprobación"
     * - Transacciones de módulos con restricciones
     * - Presupuestos sobrepasados
     * 
     * @param array $transaction_data Datos de la transacción
     * @return bool true si requiere aprobación manual, false si puede auto-aprobar
     * @since 1.0.0
     */
    public static function requires_manual_approval($transaction_data) {
        global $wpdb;
        
        // 1. Verificar excepciones por categoría
        if (!empty($transaction_data['category_id'])) {
            $category_table = $wpdb->prefix . 'aura_finance_categories';
            $category = $wpdb->get_row($wpdb->prepare(
                "SELECT always_require_approval FROM $category_table WHERE id = %d",
                $transaction_data['category_id']
            ));
            
            // Si la categoría tiene el flag activado, requiere aprobación manual
            if ($category && !empty($category->always_require_approval)) {
                return true;
            }
        }
        
        // 2. Verificar excepciones por módulo de origen
        $exceptions = get_option('aura_finance_auto_approval_exceptions', array());
        
        if (!empty($transaction_data['related_module'])) {
            // Generar clave única: módulo_acción (ej: "vehicles_fuel_expense")
            $exception_key = $transaction_data['related_module'];
            
            if (!empty($transaction_data['related_action'])) {
                $exception_key .= '_' . $transaction_data['related_action'];
            }
            
            // Si existe excepción configurada para este módulo/acción
            if (isset($exceptions[$exception_key]) && $exceptions[$exception_key]) {
                return true;
            }
        }
        
        // 3. Verificar presupuesto sobrepasado: fuerza aprobación manual si se excede
        // Fase 8.3+: el presupuesto es por área; se necesita area_id de la transacción
        if (!empty($transaction_data['area_id']) && !empty($transaction_data['amount'])) {
            if (self::is_budget_exceeded((int) $transaction_data['area_id'], (float) $transaction_data['amount'])) {
                return true;
            }
        }

        // No hay excepciones, puede proceder con auto-aprobación si cumple umbral
        return false;
    }
    
    /**
     * Verificar si agregar $amount al área excedería el presupuesto activo.
     *
     * Fase 8.3+: el presupuesto es por Área. Consulta
     * Aura_Financial_Budgets::get_active_budget_for_area() para obtener
     * el presupuesto vigente y compara el ejecutado acumulado + el nuevo monto
     * contra el límite definido. Retorna false si no hay presupuesto configurado.
     *
     * @param int   $area_id ID del área
     * @param float $amount  Monto de la transacción a evaluar
     * @return bool true si se excedería el presupuesto, false en caso contrario
     * @since 1.0.0
     */
    private static function is_budget_exceeded(int $area_id, float $amount): bool {
        if (!class_exists('Aura_Financial_Budgets')) {
            return false;
        }

        $budget = Aura_Financial_Budgets::get_active_budget_for_area($area_id);
        if (!$budget) {
            return false;
        }

        $executed = Aura_Financial_Budgets::get_executed(
            $area_id,
            $budget->start_date,
            $budget->end_date
        );

        return ($executed + $amount) > (float) $budget->budget_amount;
    }
    
    /**
     * Obtener configuraciones del módulo financiero
     * 
     * @return array Configuraciones actuales
     * @since 1.0.0
     */
    public static function get_settings() {
        return array(
            'auto_approval_enabled' => get_option('aura_finance_auto_approval_enabled', false),
            'auto_approval_threshold' => (float) get_option('aura_finance_auto_approval_threshold', 0),
            'apply_to_expenses_only' => get_option('aura_finance_auto_approval_apply_to_expenses_only', true),
            'apply_to_income_only' => get_option('aura_finance_auto_approval_apply_to_income_only', false),
            'exceptions' => get_option('aura_finance_auto_approval_exceptions', array()),
            'edit_time_limit_days' => (int) get_option('aura_finance_edit_time_limit_days', 30),
            'trash_auto_delete_days' => (int) get_option('aura_finance_trash_auto_delete_days', 30),
            'require_receipts' => get_option('aura_finance_require_receipts', false),
            'receipt_min_amount' => (float) get_option('aura_finance_receipt_min_amount', 0),
            'notification_email_enabled' => get_option('aura_finance_notification_email_enabled', true),
            'notification_email_on_pending' => get_option('aura_finance_notification_email_on_pending', true),
            'notification_email_on_approved' => get_option('aura_finance_notification_email_on_approved', false),
            'notification_email_on_rejected' => get_option('aura_finance_notification_email_on_rejected', true),
        );
    }
    
    /**
     * Guardar configuraciones del módulo (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_save_settings() {
        check_ajax_referer('aura_settings_nonce', 'nonce');
        
        // Verificar permisos (solo administradores)
        if (!current_user_can('aura_finance_settings_manage')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para modificar configuraciones', 'aura-suite')
            ));
        }
        
        // Obtener y sanitizar datos
        $settings = array(
            'auto_approval_enabled' => isset($_POST['auto_approval_enabled']) && $_POST['auto_approval_enabled'] === 'true',
            'auto_approval_threshold' => floatval($_POST['auto_approval_threshold'] ?? 0),
            'apply_to_expenses_only' => isset($_POST['apply_to_expenses_only']) && $_POST['apply_to_expenses_only'] === 'true',
            'apply_to_income_only' => isset($_POST['apply_to_income_only']) && $_POST['apply_to_income_only'] === 'true',
            'edit_time_limit_days' => absint($_POST['edit_time_limit_days'] ?? 30),
            'trash_auto_delete_days' => absint($_POST['trash_auto_delete_days'] ?? 30),
            'require_receipts' => isset($_POST['require_receipts']) && $_POST['require_receipts'] === 'true',
            'receipt_min_amount' => floatval($_POST['receipt_min_amount'] ?? 0),
            'notification_email_enabled' => isset($_POST['notification_email_enabled']) && $_POST['notification_email_enabled'] === 'true',
            'notification_email_on_pending' => isset($_POST['notification_email_on_pending']) && $_POST['notification_email_on_pending'] === 'true',
            'notification_email_on_approved' => isset($_POST['notification_email_on_approved']) && $_POST['notification_email_on_approved'] === 'true',
            'notification_email_on_rejected' => isset($_POST['notification_email_on_rejected']) && $_POST['notification_email_on_rejected'] === 'true',
        );
        
        // Validaciones
        if ($settings['auto_approval_threshold'] < 0) {
            wp_send_json_error(array(
                'message' => __('El umbral de aprobación automática no puede ser negativo', 'aura-suite')
            ));
        }
        
        if ($settings['apply_to_expenses_only'] && $settings['apply_to_income_only']) {
            wp_send_json_error(array(
                'message' => __('No puedes aplicar auto-aprobación solo a egresos Y solo a ingresos simultáneamente', 'aura-suite')
            ));
        }
        
        // Guardar cada configuración
        foreach ($settings as $key => $value) {
            update_option('aura_finance_' . $key, $value);
        }
        
        // Guardar excepciones si vienen en el POST
        if (isset($_POST['exceptions']) && is_array($_POST['exceptions'])) {
            $exceptions = array_map('sanitize_text_field', $_POST['exceptions']);
            update_option('aura_finance_auto_approval_exceptions', $exceptions);
        }
        
        // Log de auditoría
        self::log_settings_change(get_current_user_id(), $settings);
        
        // Hook para extensibilidad
        do_action('aura_finance_settings_updated', $settings, get_current_user_id());
        
        wp_send_json_success(array(
            'message' => __('Configuraciones guardadas exitosamente', 'aura-suite'),
            'settings' => self::get_settings()
        ));
    }
    
    /**
     * Obtener configuraciones (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_get_settings() {
        check_ajax_referer('aura_settings_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('aura_finance_settings_manage')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para ver configuraciones', 'aura-suite')
            ));
        }
        
        wp_send_json_success(array(
            'settings' => self::get_settings()
        ));
    }
    
    /**
     * Registrar cambio de configuración en log de auditoría
     * 
     * @param int $user_id ID del usuario que hizo el cambio
     * @param array $new_settings Nuevas configuraciones
     * @since 1.0.0
     */
    private static function log_settings_change($user_id, $new_settings) {
        global $wpdb;
        
        $old_settings = self::get_settings();
        $changes = array();
        
        // Detectar qué cambió
        foreach ($new_settings as $key => $new_value) {
            $old_value = $old_settings[$key] ?? null;
            
            if ($old_value !== $new_value) {
                $changes[] = sprintf(
                    '%s: %s → %s',
                    $key,
                    is_bool($old_value) ? ($old_value ? 'true' : 'false') : $old_value,
                    is_bool($new_value) ? ($new_value ? 'true' : 'false') : $new_value
                );
            }
        }
        
        if (empty($changes)) {
            return; // No hubo cambios
        }
        
        // Registrar en tabla de auditoría (si existe)
        // Por ahora solo lo guardamos en un log simple
        $log_entry = sprintf(
            '[%s] Usuario %d modificó configuraciones del módulo financiero: %s',
            current_time('mysql'),
            $user_id,
            implode(', ', $changes)
        );
        
        // Guardar en option para historial simple
        $audit_log = get_option('aura_finance_settings_audit_log', array());
        $audit_log[] = $log_entry;
        
        // Mantener solo los últimos 100 registros
        if (count($audit_log) > 100) {
            $audit_log = array_slice($audit_log, -100);
        }
        
        update_option('aura_finance_settings_audit_log', $audit_log);
    }
    
    /**
     * Obtener estadísticas de aprobación automática
     * 
     * Útil para el dashboard widget de estadísticas
     * 
     * @param string $period Período: 'today', 'week', 'month', 'year'
     * @return array Estadísticas
     * @since 1.0.0
     */
    public static function get_auto_approval_stats($period = 'month') {
        global $wpdb;
        
        // Determinar rango de fechas
        switch ($period) {
            case 'today':
                $start_date = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'year':
                $start_date = date('Y-m-d 00:00:00', strtotime('-1 year'));
                break;
            case 'month':
            default:
                $start_date = date('Y-m-01 00:00:00');
                break;
        }
        
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Total de transacciones en el período
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE created_at >= %s AND deleted_at IS NULL",
            $start_date
        ));
        
        // Transacciones auto-aprobadas (approved_by = created_by)
        $auto_approved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE created_at >= %s 
             AND deleted_at IS NULL
             AND status = 'approved' 
             AND approved_by = created_by",
            $start_date
        ));
        
        // Transacciones aprobadas manualmente (approved_by != created_by)
        $manual_approved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE created_at >= %s 
             AND deleted_at IS NULL
             AND status = 'approved' 
             AND approved_by != created_by",
            $start_date
        ));
        
        // Transacciones rechazadas
        $rejected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE created_at >= %s 
             AND deleted_at IS NULL
             AND status = 'rejected'",
            $start_date
        ));
        
        // Transacciones pendientes
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE created_at >= %s 
             AND deleted_at IS NULL
             AND status = 'pending'",
            $start_date
        ));
        
        // Calcular porcentajes
        $total = (int) $total;
        
        return array(
            'total' => $total,
            'auto_approved' => (int) $auto_approved,
            'manual_approved' => (int) $manual_approved,
            'rejected' => (int) $rejected,
            'pending' => (int) $pending,
            'auto_approved_percent' => $total > 0 ? round(($auto_approved / $total) * 100, 1) : 0,
            'manual_approved_percent' => $total > 0 ? round(($manual_approved / $total) * 100, 1) : 0,
            'rejected_percent' => $total > 0 ? round(($rejected / $total) * 100, 1) : 0,
            'pending_percent' => $total > 0 ? round(($pending / $total) * 100, 1) : 0,
            // Tiempo estimado ahorrado (asumiendo 5 minutos por aprobación manual)
            'time_saved_minutes' => $auto_approved * 5,
            'time_saved_hours' => round(($auto_approved * 5) / 60, 1),
        );
    }
    
    /**
     * Agregar dashboard widget de estadísticas
     * 
     * @since 1.0.0
     */
    public static function add_dashboard_widget() {
        // Solo para usuarios con permiso de ver transacciones
        if (!current_user_can('aura_finance_view_all') && !current_user_can('aura_finance_approve')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'aura_finance_approval_stats_widget',
            __('📊 Estadísticas de Aprobación - Finanzas', 'aura-suite'),
            array(__CLASS__, 'render_approval_stats_widget')
        );
    }
    
    /**
     * Renderizar widget de estadísticas de aprobación
     * 
     * @since 1.0.0
     */
    public static function render_approval_stats_widget() {
        $stats = self::get_auto_approval_stats('month');
        $settings = self::get_settings();
        
        // Verificar si la auto-aprobación está habilitada
        $auto_approval_enabled = $settings['auto_approval_enabled'];
        $threshold = $settings['auto_approval_threshold'];
        
        ?>
        <div class="aura-approval-stats-widget">
            <style>
                .aura-approval-stats-widget {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .aura-stats-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px;
                    margin: -12px -12px 15px -12px;
                    border-radius: 4px 4px 0 0;
                }
                .aura-stats-header h4 {
                    margin: 0 0 5px 0;
                    font-size: 16px;
                    color: white;
                }
                .aura-stats-header p {
                    margin: 0;
                    font-size: 12px;
                    opacity: 0.9;
                }
                .aura-stat-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                .aura-stat-row:last-child {
                    border-bottom: none;
                }
                .aura-stat-label {
                    font-weight: 500;
                    color: #555;
                }
                .aura-stat-value {
                    font-weight: 600;
                    color: #333;
                }
                .aura-stat-value.auto {
                    color: #10b981;
                }
                .aura-stat-value.manual {
                    color: #3b82f6;
                }
                .aura-stat-value.rejected {
                    color: #ef4444;
                }
                .aura-stat-value.pending {
                    color: #f59e0b;
                }
                .aura-progress-bar {
                    height: 8px;
                    background: #f0f0f0;
                    border-radius: 4px;
                    overflow: hidden;
                    margin: 10px 0;
                }
                .aura-progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                    transition: width 0.3s ease;
                }
                .aura-time-saved {
                    background: #f0fdf4;
                    border: 1px solid #86efac;
                    border-radius: 6px;
                    padding: 12px;
                    margin-top: 15px;
                    text-align: center;
                }
                .aura-time-saved strong {
                    display: block;
                    font-size: 24px;
                    color: #10b981;
                    margin-bottom: 5px;
                }
                .aura-time-saved small {
                    color: #059669;
                    font-size: 12px;
                }
                .aura-widget-footer {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #e5e7eb;
                    text-align: center;
                }
                .aura-config-notice {
                    background: #fef3c7;
                    border: 1px solid #fbbf24;
                    border-radius: 6px;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 13px;
                    color: #92400e;
                }
            </style>
            
            <div class="aura-stats-header">
                <h4><?php _e('Este Mes', 'aura-suite'); ?></h4>
                <?php if ($auto_approval_enabled): ?>
                    <p><?php printf(__('Umbral de auto-aprobación: $%s', 'aura-suite'), number_format($threshold, 2)); ?></p>
                <?php else: ?>
                    <p><?php _e('Auto-aprobación deshabilitada', 'aura-suite'); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!$auto_approval_enabled): ?>
                <div class="aura-config-notice">
                    ⚠️ <?php _e('La aprobación automática está deshabilitada. Todas las transacciones requieren aprobación manual.', 'aura-suite'); ?>
                </div>
            <?php endif; ?>
            
            <div class="aura-stat-row">
                <span class="aura-stat-label"><?php _e('Total transacciones:', 'aura-suite'); ?></span>
                <span class="aura-stat-value"><?php echo $stats['total']; ?></span>
            </div>
            
            <?php if ($auto_approval_enabled && $stats['auto_approved'] > 0): ?>
                <div class="aura-stat-row">
                    <span class="aura-stat-label">• <?php _e('Auto-aprobadas:', 'aura-suite'); ?></span>
                    <span class="aura-stat-value auto">
                        <?php echo $stats['auto_approved']; ?> (<?php echo $stats['auto_approved_percent']; ?>%)
                    </span>
                </div>
                
                <div class="aura-progress-bar">
                    <div class="aura-progress-fill" style="width: <?php echo $stats['auto_approved_percent']; ?>%"></div>
                </div>
            <?php endif; ?>
            
            <div class="aura-stat-row">
                <span class="aura-stat-label">• <?php _e('Aprobación manual:', 'aura-suite'); ?></span>
                <span class="aura-stat-value manual">
                    <?php echo $stats['manual_approved']; ?> (<?php echo $stats['manual_approved_percent']; ?>%)
                </span>
            </div>
            
            <div class="aura-stat-row">
                <span class="aura-stat-label">• <?php _e('Rechazadas:', 'aura-suite'); ?></span>
                <span class="aura-stat-value rejected">
                    <?php echo $stats['rejected']; ?> (<?php echo $stats['rejected_percent']; ?>%)
                </span>
            </div>
            
            <?php if ($stats['pending'] > 0): ?>
                <div class="aura-stat-row">
                    <span class="aura-stat-label">• <?php _e('Pendientes:', 'aura-suite'); ?></span>
                    <span class="aura-stat-value pending">
                        <?php echo $stats['pending']; ?> (<?php echo $stats['pending_percent']; ?>%)
                    </span>
                </div>
            <?php endif; ?>
            
            <?php if ($auto_approval_enabled && $stats['time_saved_hours'] > 0): ?>
                <div class="aura-time-saved">
                    <strong><?php echo $stats['time_saved_hours']; ?> hrs</strong>
                    <small><?php _e('Tiempo estimado ahorrado este mes', 'aura-suite'); ?></small>
                    <br>
                    <small style="color: #6b7280; margin-top: 5px; display: inline-block;">
                        <?php printf(__('(%d min por aprobación × %d auto-aprobadas)', 'aura-suite'), 5, $stats['auto_approved']); ?>
                    </small>
                </div>
            <?php endif; ?>
            
            <div class="aura-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-primary">
                    <?php _e('Ver Todas las Transacciones', 'aura-suite'); ?>
                </a>
                <?php if (current_user_can('aura_finance_settings_manage')): ?>
                    <a href="<?php echo admin_url('admin.php?page=aura-financial-settings'); ?>" class="button" style="margin-left: 5px;">
                        <?php _e('⚙️ Configurar', 'aura-suite'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
