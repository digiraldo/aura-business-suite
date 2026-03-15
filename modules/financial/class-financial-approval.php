<?php
/**
 * Gestión de Aprobación y Rechazo de Transacciones Financieras
 * 
 * Maneja el flujo completo de aprobación, rechazo y notificaciones
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Approval {
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_aura_approve_transaction', array(__CLASS__, 'ajax_approve_transaction'));
        add_action('wp_ajax_aura_reject_transaction', array(__CLASS__, 'ajax_reject_transaction'));
        add_action('wp_ajax_aura_get_pending_count', array(__CLASS__, 'ajax_get_pending_count'));
        add_action('wp_ajax_aura_bulk_approve', array(__CLASS__, 'ajax_bulk_approve'));
        add_action('wp_ajax_aura_bulk_reject', array(__CLASS__, 'ajax_bulk_reject'));
        
        // Dashboard widget
        add_action('wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widget'));
    }
    
    /**
     * Aprobar una transacción (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_approve_transaction() {
        check_ajax_referer('aura_approval_nonce', 'nonce');
        
        $transaction_id = absint($_POST['transaction_id'] ?? 0);
        $approval_note = sanitize_textarea_field($_POST['approval_note'] ?? '');
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción no válido', 'aura-suite')
            ));
        }
        
        // Verificar permisos
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para aprobar transacciones', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener transacción
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $transaction_id
        ), ARRAY_A);
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada', 'aura-suite')
            ));
        }
        
        // Validaciones
        $validation = self::validate_approval($transaction);
        if (!$validation['can_approve']) {
            wp_send_json_error(array(
                'message' => $validation['message']
            ));
        }
        
        // Actualizar transacción
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'approved',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al aprobar la transacción', 'aura-suite')
            ));
        }
        
        // Log en historial
        self::log_approval($transaction_id, 'approved', $approval_note);
        
        // Enviar notificación al creador
        self::notify_creator($transaction_id, 'approved', $approval_note);
        
        // Hook para extensiones
        do_action('aura_finance_transaction_approved', $transaction_id, get_current_user_id(), $approval_note);
        
        wp_send_json_success(array(
            'message' => __('Transacción aprobada exitosamente', 'aura-suite'),
            'transaction_id' => $transaction_id
        ));
    }
    
    /**
     * Rechazar una transacción (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_reject_transaction() {
        check_ajax_referer('aura_approval_nonce', 'nonce');
        
        $transaction_id = absint($_POST['transaction_id'] ?? 0);
        $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción no válido', 'aura-suite')
            ));
        }
        
        // Validar motivo de rechazo (mínimo 20 caracteres)
        if (strlen($rejection_reason) < 20) {
            wp_send_json_error(array(
                'message' => __('El motivo de rechazo debe tener al menos 20 caracteres', 'aura-suite')
            ));
        }
        
        // Verificar permisos
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para rechazar transacciones', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener transacción
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $transaction_id
        ), ARRAY_A);
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada', 'aura-suite')
            ));
        }
        
        // Validaciones
        $validation = self::validate_approval($transaction);
        if (!$validation['can_approve']) {
            wp_send_json_error(array(
                'message' => $validation['message']
            ));
        }
        
        // Actualizar transacción
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'rejected',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql'),
                'rejection_reason' => $rejection_reason,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al rechazar la transacción', 'aura-suite')
            ));
        }
        
        // Log en historial
        self::log_approval($transaction_id, 'rejected', $rejection_reason);
        
        // Enviar notificación al creador
        self::notify_creator($transaction_id, 'rejected', $rejection_reason);
        
        // Hook para extensiones
        do_action('aura_finance_transaction_rejected', $transaction_id, get_current_user_id(), $rejection_reason);
        
        wp_send_json_success(array(
            'message' => __('Transacción rechazada', 'aura-suite'),
            'transaction_id' => $transaction_id
        ));
    }
    
    /**
     * Obtener conteo de transacciones pendientes (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_get_pending_count() {
        check_ajax_referer('aura_approval_nonce', 'nonce');
        
        $count = self::get_pending_count();
        
        wp_send_json_success(array(
            'count' => $count
        ));
    }
    
    /**
     * Aprobar múltiples transacciones (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_bulk_approve() {
        check_ajax_referer('aura_approval_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para aprobar transacciones', 'aura-suite')
            ));
        }
        
        $transaction_ids = isset($_POST['transaction_ids']) ? array_map('absint', $_POST['transaction_ids']) : array();
        $approval_note = sanitize_textarea_field($_POST['approval_note'] ?? '');
        
        if (empty($transaction_ids)) {
            wp_send_json_error(array(
                'message' => __('No se seleccionaron transacciones', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        $approved_count = 0;
        $errors = array();
        
        foreach ($transaction_ids as $transaction_id) {
            $transaction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $transaction_id
            ), ARRAY_A);
            
            if (!$transaction) {
                $errors[] = sprintf(__('Transacción #%d no encontrada', 'aura-suite'), $transaction_id);
                continue;
            }
            
            $validation = self::validate_approval($transaction);
            if (!$validation['can_approve']) {
                $errors[] = sprintf(__('Transacción #%d: %s', 'aura-suite'), $transaction_id, $validation['message']);
                continue;
            }
            
            $result = $wpdb->update(
                $table,
                array(
                    'status' => 'approved',
                    'approved_by' => get_current_user_id(),
                    'approved_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $transaction_id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $approved_count++;
                self::log_approval($transaction_id, 'approved', $approval_note);
                self::notify_creator($transaction_id, 'approved', $approval_note);
                do_action('aura_finance_transaction_approved', $transaction_id, get_current_user_id(), $approval_note);
            }
        }
        
        $message = sprintf(
            _n(
                '%d transacción aprobada exitosamente',
                '%d transacciones aprobadas exitosamente',
                $approved_count,
                'aura-suite'
            ),
            $approved_count
        );
        
        if (!empty($errors)) {
            $message .= '. ' . implode('; ', $errors);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'approved_count' => $approved_count,
            'errors' => $errors
        ));
    }
    
    /**
     * Rechazar múltiples transacciones (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_bulk_reject() {
        check_ajax_referer('aura_approval_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para rechazar transacciones', 'aura-suite')
            ));
        }
        
        $transaction_ids = isset($_POST['transaction_ids']) ? array_map('absint', $_POST['transaction_ids']) : array();
        $rejection_reason = sanitize_textarea_field($_POST['rejection_reason'] ?? '');
        
        if (empty($transaction_ids)) {
            wp_send_json_error(array(
                'message' => __('No se seleccionaron transacciones', 'aura-suite')
            ));
        }
        
        if (strlen($rejection_reason) < 20) {
            wp_send_json_error(array(
                'message' => __('El motivo de rechazo debe tener al menos 20 caracteres', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        $rejected_count = 0;
        $errors = array();
        
        foreach ($transaction_ids as $transaction_id) {
            $transaction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $transaction_id
            ), ARRAY_A);
            
            if (!$transaction) {
                $errors[] = sprintf(__('Transacción #%d no encontrada', 'aura-suite'), $transaction_id);
                continue;
            }
            
            $validation = self::validate_approval($transaction);
            if (!$validation['can_approve']) {
                $errors[] = sprintf(__('Transacción #%d: %s', 'aura-suite'), $transaction_id, $validation['message']);
                continue;
            }
            
            $result = $wpdb->update(
                $table,
                array(
                    'status' => 'rejected',
                    'approved_by' => get_current_user_id(),
                    'approved_at' => current_time('mysql'),
                    'rejection_reason' => $rejection_reason,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $transaction_id),
                array('%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $rejected_count++;
                self::log_approval($transaction_id, 'rejected', $rejection_reason);
                self::notify_creator($transaction_id, 'rejected', $rejection_reason);
                do_action('aura_finance_transaction_rejected', $transaction_id, get_current_user_id(), $rejection_reason);
            }
        }
        
        $message = sprintf(
            _n(
                '%d transacción rechazada',
                '%d transacciones rechazadas',
                $rejected_count,
                'aura-suite'
            ),
            $rejected_count
        );
        
        if (!empty($errors)) {
            $message .= '. ' . implode('; ', $errors);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'rejected_count' => $rejected_count,
            'errors' => $errors
        ));
    }
    
    /**
     * Validar que una transacción puede ser aprobada/rechazada
     * 
     * @since 1.0.0
     * @param array $transaction Datos de la transacción
     * @return array ['can_approve' => bool, 'message' => string]
     */
    public static function validate_approval($transaction) {
        $current_user_id = get_current_user_id();
        
        // No puede estar eliminada
        if (!empty($transaction['deleted_at'])) {
            return array(
                'can_approve' => false,
                'message' => __('Esta transacción está eliminada', 'aura-suite')
            );
        }
        
        // Debe estar pendiente
        if ($transaction['status'] !== 'pending') {
            $status_labels = array(
                'approved' => __('aprobada', 'aura-suite'),
                'rejected' => __('rechazada', 'aura-suite')
            );
            
            $status_text = isset($status_labels[$transaction['status']]) 
                ? $status_labels[$transaction['status']] 
                : $transaction['status'];
            
            return array(
                'can_approve' => false,
                'message' => sprintf(__('Esta transacción ya está %s', 'aura-suite'), $status_text)
            );
        }
        
        // No puede aprobar sus propias transacciones
        if ($transaction['created_by'] == $current_user_id) {
            return array(
                'can_approve' => false,
                'message' => __('No puedes aprobar tus propias transacciones', 'aura-suite')
            );
        }
        
        // Validar límite de aprobación (opcional, configurable)
        $approval_limit = get_user_meta($current_user_id, 'aura_finance_approval_limit', true);
        
        if ($approval_limit && floatval($transaction['amount']) > floatval($approval_limit)) {
            return array(
                'can_approve' => false,
                'message' => sprintf(
                    __('El monto de esta transacción ($%s) excede tu límite de aprobación ($%s)', 'aura-suite'),
                    number_format($transaction['amount'], 2),
                    number_format($approval_limit, 2)
                )
            );
        }
        
        return array(
            'can_approve' => true,
            'message' => ''
        );
    }
    
    /**
     * Registrar aprobación/rechazo en historial
     * 
     * @since 1.0.0
     * @param int $transaction_id ID de la transacción
     * @param string $action 'approved' o 'rejected'
     * @param string $note Nota o motivo
     */
    public static function log_approval($transaction_id, $action, $note = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transaction_history';
        
        $action_labels = array(
            'approved' => __('Transacción aprobada', 'aura-suite'),
            'rejected' => __('Transacción rechazada', 'aura-suite')
        );
        
        $change_reason = isset($action_labels[$action]) ? $action_labels[$action] : $action;
        
        if (!empty($note)) {
            $change_reason .= ': ' . $note;
        }
        
        $wpdb->insert(
            $table,
            array(
                'transaction_id' => $transaction_id,
                'field_changed' => 'status',
                'old_value' => 'pending',
                'new_value' => $action,
                'change_reason' => $change_reason,
                'changed_by' => get_current_user_id(),
                'changed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Enviar notificación al creador de la transacción
     * 
     * @since 1.0.0
     * @param int $transaction_id ID de la transacción
     * @param string $action 'approved' o 'rejected'
     * @param string $note Nota o motivo
     */
    public static function notify_creator($transaction_id, $action, $note = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $transaction_id
        ), ARRAY_A);
        
        if (!$transaction) {
            return;
        }
        
        $creator = get_userdata($transaction['created_by']);
        if (!$creator) {
            return;
        }
        
        $approver = wp_get_current_user();
        
        // Email notification (si está habilitado en configuración)
        if (get_option('aura_finance_email_notifications', false)) {
            $subject = '';
            $message = '';
            
            if ($action === 'approved') {
                $subject = sprintf(
                    __('[%s] Tu transacción fue aprobada', 'aura-suite'),
                    aura_get_org_name()
                );
                
                $message = sprintf(
                    __('Hola %s,

Tu transacción ha sido aprobada por %s.

Detalles de la transacción:
- Monto: $%s
- Descripción: %s
- Fecha: %s

%s

Puedes ver los detalles completos en: %s

Saludos,
%s', 'aura-suite'),
                    $creator->display_name,
                    $approver->display_name,
                    number_format($transaction['amount'], 2),
                    $transaction['description'],
                    $transaction['transaction_date'],
                    !empty($note) ? 'Nota del aprobador: ' . $note : '',
                    admin_url('admin.php?page=aura-financial-transactions'),
                    aura_get_org_name()
                );
            } elseif ($action === 'rejected') {
                $subject = sprintf(
                    __('[%s] Tu transacción fue rechazada', 'aura-suite'),
                    aura_get_org_name()
                );
                
                $message = sprintf(
                    __('Hola %s,

Tu transacción ha sido rechazada por %s.

Detalles de la transacción:
- Monto: $%s
- Descripción: %s
- Fecha: %s

Motivo del rechazo: %s

Puedes editar y reenviar la transacción desde: %s

Saludos,
%s', 'aura-suite'),
                    $creator->display_name,
                    $approver->display_name,
                    number_format($transaction['amount'], 2),
                    $transaction['description'],
                    $transaction['transaction_date'],
                    $note,
                    admin_url('admin.php?page=aura-financial-transactions'),
                    aura_get_org_name()
                );
            }
            
            wp_mail($creator->user_email, $subject, $message);
        }
        
        // In-app notification (opcional, para futuras implementaciones)
        do_action('aura_finance_send_notification', $transaction['created_by'], $action, $transaction_id, $note);
    }
    
    /**
     * Obtener conteo de transacciones pendientes
     * 
     * @since 1.0.0
     * @return int
     */
    public static function get_pending_count() {
        if (!current_user_can('aura_finance_approve')) {
            return 0;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // No contar las propias transacciones
        $current_user_id = get_current_user_id();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE status = 'pending' 
             AND deleted_at IS NULL
             AND created_by != %d",
            $current_user_id
        ));
        
        return intval($count);
    }
    
    /**
     * Agregar widget al dashboard de WordPress
     * 
     * @since 1.0.0
     */
    public static function add_dashboard_widget() {
        if (!current_user_can('aura_finance_approve')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'aura_finance_pending_widget',
            __('Transacciones Pendientes de Aprobación', 'aura-suite'),
            array(__CLASS__, 'render_dashboard_widget')
        );
    }
    
    /**
     * Renderizar widget del dashboard
     * 
     * @since 1.0.0
     */
    public static function render_dashboard_widget() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        $current_user_id = get_current_user_id();
        
        // Obtener últimas 5 transacciones pendientes
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, c.name as category_name, c.color as category_color
             FROM $table t
             LEFT JOIN $categories_table c ON t.category_id = c.id
             WHERE t.status = 'pending' 
             AND t.deleted_at IS NULL
             AND t.created_by != %d
             ORDER BY t.transaction_date DESC
             LIMIT 5",
            $current_user_id
        ), ARRAY_A);
        
        $total_pending = self::get_pending_count();
        
        if (empty($pending)) {
            echo '<p>' . __('No hay transacciones pendientes de aprobación', 'aura-suite') . '</p>';
            return;
        }
        
        echo '<div class="aura-dashboard-widget">';
        echo '<table class="widefat">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Fecha', 'aura-suite') . '</th>';
        echo '<th>' . __('Descripción', 'aura-suite') . '</th>';
        echo '<th>' . __('Monto', 'aura-suite') . '</th>';
        echo '<th>' . __('Acciones', 'aura-suite') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($pending as $transaction) {
            $date = new DateTime($transaction['transaction_date']);
            $type_class = $transaction['transaction_type'] === 'income' ? 'income' : 'expense';
            
            echo '<tr>';
            echo '<td>' . $date->format('d/m/Y') . '</td>';
            echo '<td>' . esc_html($transaction['description']) . '</td>';
            echo '<td class="' . $type_class . '">$' . number_format($transaction['amount'], 2) . '</td>';
            echo '<td>';
            echo '<a href="#" class="button button-small quick-approve" data-id="' . $transaction['id'] . '">' . __('Aprobar', 'aura-suite') . '</a> ';
            echo '<a href="#" class="button button-small quick-reject" data-id="' . $transaction['id'] . '">' . __('Rechazar', 'aura-suite') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        if ($total_pending > 5) {
            echo '<p class="textright">';
            echo '<a href="' . admin_url('admin.php?page=aura-financial-pending') . '" class="button">';
            echo sprintf(__('Ver todas (%d)', 'aura-suite'), $total_pending);
            echo '</a>';
            echo '</p>';
        }
        
        echo '</div>';
        
        // CSS inline para el widget
        echo '<style>
            .aura-dashboard-widget .income { color: #10b981; font-weight: 600; }
            .aura-dashboard-widget .expense { color: #e74c3c; font-weight: 600; }
            .aura-dashboard-widget .textright { text-align: right; margin-top: 10px; }
        </style>';
    }
}

// Inicializar la clase
Aura_Financial_Approval::init();
