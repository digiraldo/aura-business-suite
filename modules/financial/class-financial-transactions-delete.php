<?php
/**
 * Gestión de Eliminación de Transacciones Financieras
 * 
 * Maneja soft delete, restauración, hard delete y auto-limpieza de transacciones
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Transactions_Delete {
    
    /**
     * Días antes de eliminar permanentemente transacciones en papelera
     * @var int
     */
    const TRASH_RETENTION_DAYS = 30;
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_aura_delete_transaction', array(__CLASS__, 'ajax_delete_transaction'));
        add_action('wp_ajax_aura_restore_transaction', array(__CLASS__, 'ajax_restore_transaction'));
        add_action('wp_ajax_aura_permanent_delete_transaction', array(__CLASS__, 'ajax_permanent_delete_transaction'));
        add_action('wp_ajax_aura_bulk_restore', array(__CLASS__, 'ajax_bulk_restore'));
        add_action('wp_ajax_aura_empty_trash', array(__CLASS__, 'ajax_empty_trash'));
        
        // Cron job para auto-limpieza
        add_action('aura_finance_empty_trash_cron', array(__CLASS__, 'empty_trash_scheduled'));
        
        // Activar cron job si no está programado
        if (!wp_next_scheduled('aura_finance_empty_trash_cron')) {
            wp_schedule_event(time(), 'daily', 'aura_finance_empty_trash_cron');
        }
    }
    
    /**
     * Soft delete de una transacción (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_delete_transaction() {
        check_ajax_referer('aura_transaction_delete_nonce', 'nonce');
        
        $transaction_id = absint($_POST['transaction_id'] ?? 0);
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción no válido', 'aura-suite')
            ));
        }
        
        // Verificar permisos
        if (!self::can_delete_transaction($transaction_id)) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para eliminar esta transacción', 'aura-suite')
            ));
        }
        
        // Validar que no esté ya eliminada
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $transaction_id
        ), ARRAY_A);
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada', 'aura-suite')
            ));
        }
        
        if ($transaction['deleted_at']) {
            wp_send_json_error(array(
                'message' => __('Esta transacción ya está en la papelera', 'aura-suite')
            ));
        }
        
        // Validaciones adicionales
        $validation = self::validate_deletion($transaction);
        if (!$validation['can_delete']) {
            wp_send_json_error(array(
                'message' => $validation['message']
            ));
        }
        
        // Realizar soft delete
        $result = $wpdb->update(
            $table,
            array(
                'deleted_at' => current_time('mysql'),
                'deleted_by' => get_current_user_id()
            ),
            array('id' => $transaction_id),
            array('%s', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al mover la transacción a la papelera', 'aura-suite')
            ));
        }
        
        // Log de auditoría
        self::log_deletion($transaction_id, 'soft_delete', get_current_user_id());
        
        // Enviar notificación
        do_action('aura_finance_transaction_trashed', $transaction_id, $transaction);
        
        wp_send_json_success(array(
            'message' => __('Transacción enviada a la papelera', 'aura-suite'),
            'transaction_id' => $transaction_id,
            'can_undo' => true
        ));
    }
    
    /**
     * Restaurar transacción desde papelera (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_restore_transaction() {
        check_ajax_referer('aura_transaction_delete_nonce', 'nonce');
        
        $transaction_id = absint($_POST['transaction_id'] ?? 0);
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción no válido', 'aura-suite')
            ));
        }
        
        // Verificar permisos
        if (!self::can_restore_transaction($transaction_id)) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para restaurar esta transacción', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Verificar que esté en papelera
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND deleted_at IS NOT NULL",
            $transaction_id
        ), ARRAY_A);
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada en papelera', 'aura-suite')
            ));
        }
        
        // Restaurar (limpiar deleted_at)
        $result = $wpdb->update(
            $table,
            array(
                'deleted_at' => null,
                'deleted_by' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al restaurar la transacción', 'aura-suite')
            ));
        }
        
        // Log de auditoría
        self::log_deletion($transaction_id, 'restored', get_current_user_id());
        
        // Enviar notificación
        do_action('aura_finance_transaction_restored', $transaction_id, $transaction);
        
        wp_send_json_success(array(
            'message' => __('Transacción restaurada exitosamente', 'aura-suite'),
            'transaction_id' => $transaction_id
        ));
    }
    
    /**
     * Eliminación permanente de una transacción (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_permanent_delete_transaction() {
        check_ajax_referer('aura_transaction_delete_nonce', 'nonce');
        
        $transaction_id = absint($_POST['transaction_id'] ?? 0);
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción no válido', 'aura-suite')
            ));
        }
        
        // Solo administradores pueden hacer hard delete
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Solo los administradores pueden eliminar transacciones permanentemente', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener datos antes de eliminar (para log)
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $transaction_id
        ), ARRAY_A);
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada', 'aura-suite')
            ));
        }
        
        // Eliminar archivo adjunto si existe
        if (!empty($transaction['receipt_file'])) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/aura-finance/receipts/' . basename($transaction['receipt_file']);
            
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        // Log de auditoría ANTES de eliminar
        self::log_deletion($transaction_id, 'permanent_delete', get_current_user_id(), $transaction);
        
        // Eliminar de la base de datos
        $result = $wpdb->delete(
            $table,
            array('id' => $transaction_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al eliminar la transacción permanentemente', 'aura-suite')
            ));
        }
        
        // Enviar notificación
        do_action('aura_finance_transaction_permanently_deleted', $transaction_id, $transaction);
        
        wp_send_json_success(array(
            'message' => __('Transacción eliminada permanentemente', 'aura-suite'),
            'transaction_id' => $transaction_id
        ));
    }
    
    /**
     * Restaurar múltiples transacciones (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_bulk_restore() {
        check_ajax_referer('aura_transaction_delete_nonce', 'nonce');
        
        $transaction_ids = isset($_POST['transaction_ids']) ? array_map('absint', $_POST['transaction_ids']) : array();
        
        if (empty($transaction_ids)) {
            wp_send_json_error(array(
                'message' => __('No se seleccionaron transacciones', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        $restored_count = 0;
        $errors = array();
        
        foreach ($transaction_ids as $transaction_id) {
            if (!self::can_restore_transaction($transaction_id)) {
                $errors[] = sprintf(__('No tienes permisos para restaurar la transacción #%d', 'aura-suite'), $transaction_id);
                continue;
            }
            
            $result = $wpdb->update(
                $table,
                array(
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $transaction_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $restored_count++;
                self::log_deletion($transaction_id, 'restored', get_current_user_id());
            }
        }
        
        $message = sprintf(
            _n(
                '%d transacción restaurada exitosamente',
                '%d transacciones restauradas exitosamente',
                $restored_count,
                'aura-suite'
            ),
            $restored_count
        );
        
        if (!empty($errors)) {
            $message .= '. ' . implode('; ', $errors);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'restored_count' => $restored_count,
            'errors' => $errors
        ));
    }
    
    /**
     * Vaciar papelera completa (AJAX)
     * 
     * @since 1.0.0
     */
    public static function ajax_empty_trash() {
        check_ajax_referer('aura_transaction_delete_nonce', 'nonce');
        
        // Solo administradores
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Solo los administradores pueden vaciar la papelera', 'aura-suite')
            ));
        }
        
        $deleted_count = self::empty_trash_permanently();
        
        wp_send_json_success(array(
            'message' => sprintf(
                _n(
                    '%d transacción eliminada permanentemente',
                    '%d transacciones eliminadas permanentemente',
                    $deleted_count,
                    'aura-suite'
                ),
                $deleted_count
            ),
            'deleted_count' => $deleted_count
        ));
    }
    
    /**
     * Cron job: Eliminar permanentemente transacciones en papelera > 30 días
     * 
     * @since 1.0.0
     */
    public static function empty_trash_scheduled() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener transacciones en papelera > 30 días
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::TRASH_RETENTION_DAYS . ' days'));
        
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, receipt_file FROM $table 
             WHERE deleted_at IS NOT NULL 
             AND deleted_at < %s",
            $cutoff_date
        ), ARRAY_A);
        
        if (empty($transactions)) {
            return 0;
        }
        
        $deleted_count = 0;
        $upload_dir = wp_upload_dir();
        
        foreach ($transactions as $transaction) {
            // Eliminar archivo adjunto si existe
            if (!empty($transaction['receipt_file'])) {
                $file_path = $upload_dir['basedir'] . '/aura-finance/receipts/' . basename($transaction['receipt_file']);
                
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            // Log de auditoría
            self::log_deletion($transaction['id'], 'auto_deleted', 0);
            
            // Eliminar
            $result = $wpdb->delete(
                $table,
                array('id' => $transaction['id']),
                array('%d')
            );
            
            if ($result !== false) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Vaciar papelera permanentemente (todas las transacciones eliminadas)
     * 
     * @since 1.0.0
     * @return int Número de transacciones eliminadas
     */
    public static function empty_trash_permanently() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener TODAS las transacciones en papelera
        $transactions = $wpdb->get_results(
            "SELECT id, receipt_file FROM $table WHERE deleted_at IS NOT NULL",
            ARRAY_A
        );
        
        if (empty($transactions)) {
            return 0;
        }
        
        $deleted_count = 0;
        $upload_dir = wp_upload_dir();
        
        foreach ($transactions as $transaction) {
            // Eliminar archivo adjunto si existe
            if (!empty($transaction['receipt_file'])) {
                $file_path = $upload_dir['basedir'] . '/aura-finance/receipts/' . basename($transaction['receipt_file']);
                
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            // Log de auditoría
            self::log_deletion($transaction['id'], 'permanent_delete', get_current_user_id());
            
            // Eliminar
            $result = $wpdb->delete(
                $table,
                array('id' => $transaction['id']),
                array('%d')
            );
            
            if ($result !== false) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Verificar si el usuario puede eliminar una transacción
     * 
     * @since 1.0.0
     * @param int $transaction_id ID de la transacción
     * @return bool
     */
    public static function can_delete_transaction($transaction_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Administradores y usuarios con delete_all siempre pueden
        if (current_user_can('manage_options') || current_user_can('aura_finance_delete_all')) {
            return true;
        }
        
        // Usuarios con delete_own solo sus propias transacciones
        if (current_user_can('aura_finance_delete_own')) {
            $transaction = $wpdb->get_row($wpdb->prepare(
                "SELECT created_by FROM $table WHERE id = %d",
                $transaction_id
            ), ARRAY_A);
            
            return $transaction && $transaction['created_by'] == get_current_user_id();
        }
        
        return false;
    }
    
    /**
     * Verificar si el usuario puede restaurar una transacción
     * 
     * @since 1.0.0
     * @param int $transaction_id ID de la transacción
     * @return bool
     */
    public static function can_restore_transaction($transaction_id) {
        // Mismos permisos que para eliminar
        return self::can_delete_transaction($transaction_id);
    }
    
    /**
     * Validar que una transacción puede ser eliminada
     * 
     * @since 1.0.0
     * @param array $transaction Datos de la transacción
     * @return array ['can_delete' => bool, 'message' => string]
     */
    public static function validate_deletion($transaction) {
        $warnings = array();
        
        // Advertir si es monto alto (configurable)
        $high_amount_threshold = get_option('aura_finance_high_amount_threshold', 10000);
        
        if ($transaction['amount'] >= $high_amount_threshold) {
            $warnings[] = sprintf(
                __('Esta transacción tiene un monto alto (%s). ¿Estás seguro?', 'aura-suite'),
                number_format($transaction['amount'], 2)
            );
        }
        
        // Verificar si está aprobada
        if ($transaction['status'] === 'approved') {
            $warnings[] = __('Esta transacción ya está aprobada. Considéralo cuidadosamente.', 'aura-suite');
        }
        
        // Por ahora permitir siempre (las advertencias son para el usuario)
        return array(
            'can_delete' => true,
            'message' => empty($warnings) ? '' : implode(' ', $warnings),
            'warnings' => $warnings
        );
    }
    
    /**
     * Registrar eliminación en log de auditoría
     * 
     * @since 1.0.0
     * @param int $transaction_id ID de la transacción
     * @param string $action Tipo de acción (soft_delete, restored, permanent_delete, auto_deleted)
     * @param int $user_id ID del usuario que realiza la acción
     * @param array $transaction_data Datos completos de la transacción (opcional)
     */
    public static function log_deletion($transaction_id, $action, $user_id, $transaction_data = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transaction_history';
        
        $action_labels = array(
            'soft_delete' => __('Enviado a papelera', 'aura-suite'),
            'restored' => __('Restaurado desde papelera', 'aura-suite'),
            'permanent_delete' => __('Eliminado permanentemente', 'aura-suite'),
            'auto_deleted' => __('Auto-eliminado (>30 días en papelera)', 'aura-suite')
        );
        
        $wpdb->insert(
            $table,
            array(
                'transaction_id' => $transaction_id,
                'field_changed' => 'status_deletion',
                'old_value' => $action === 'restored' ? 'deleted' : 'active',
                'new_value' => $action,
                'change_reason' => isset($action_labels[$action]) ? $action_labels[$action] : $action,
                'changed_by' => $user_id,
                'changed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        // Log adicional si es eliminación permanente (solo en modo debug, el audit log ya registra esto)
        if ( $action === 'permanent_delete' && $transaction_data && defined('WP_DEBUG') && WP_DEBUG ) {
            error_log(sprintf(
                '[AURA Finance] Transacción #%d eliminada permanentemente por usuario #%d. Monto: %s, Descripción: %s',
                $transaction_id,
                $user_id,
                $transaction_data['amount'],
                $transaction_data['description']
            ));
        }
    }
    
    /**
     * Obtener conteo de transacciones en papelera
     * 
     * @since 1.0.0
     * @return int
     */
    public static function get_trash_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE deleted_at IS NOT NULL"
        );
        
        return intval($count);
    }
}

// Inicializar la clase
Aura_Financial_Transactions_Delete::init();
