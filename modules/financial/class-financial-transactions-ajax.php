<?php
/**
 * Financial Transactions AJAX Handler
 * 
 * Maneja todas las peticiones AJAX para el modal de detalle
 * de transacciones: obtener datos, aprobar, rechazar, eliminar
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Transactions_Ajax {
    
    /**
     * Inicializa los hooks de AJAX
     */
    public static function init() {
        // AJAX para obtener detalles de transacción
        add_action('wp_ajax_aura_get_transaction_details', array(__CLASS__, 'get_transaction_details'));
        
        // Nota: approve_transaction y reject_transaction son manejados por
        // Aura_Financial_Approval::init() para evitar conflicto de nonces.
        
        // AJAX para eliminar transacción
        add_action('wp_ajax_aura_delete_transaction', array(__CLASS__, 'delete_transaction'));
    }
    
    /**
     * Obtiene los detalles completos de una transacción
     * 
     * @since 1.0.0
     */
    public static function get_transaction_details() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_modal_nonce', 'nonce');
        
        // Verificar permisos: puede ver si tiene cualquier permiso financiero o de aprobación
        $can_view = current_user_can('aura_finance_view_own')
                 || current_user_can('aura_finance_view_all')
                 || current_user_can('aura_finance_approve')
                 || current_user_can('manage_options');
        if (!$can_view) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para ver transacciones.', 'aura-suite')
            ));
        }
        
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción inválido.', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        
        // Obtener transacción con datos de categoría
        $query = $wpdb->prepare(
            "SELECT 
                t.*,
                c.name as category_name,
                c.color as category_color,
                c.icon as category_icon
            FROM {$table} t
            LEFT JOIN {$categories_table} c ON t.category_id = c.id
            WHERE t.id = %d AND t.deleted_at IS NULL",
            $transaction_id
        );
        
        $transaction = $wpdb->get_row($query, ARRAY_A);
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada.', 'aura-suite')
            ));
        }
        
        // Verificar permisos de visualización
        $current_user_id = get_current_user_id();
        $can_view_all = current_user_can('aura_finance_view_all');
        $can_view_own = current_user_can('aura_finance_view_own');
        $is_creator = ($transaction['created_by'] == $current_user_id);
        
        if (!$can_view_all && (!$can_view_own || !$is_creator)) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para ver esta transacción.', 'aura-suite')
            ));
        }
        
        // Obtener información del creador
        $creator = get_userdata($transaction['created_by']);
        $creator_data = array(
            'id' => $transaction['created_by'],
            'name' => $creator ? $creator->display_name : __('Usuario desconocido', 'aura-suite'),
            'avatar' => $creator ? get_avatar_url($transaction['created_by'], array('size' => 48)) : ''
        );
        
        // Obtener información del aprobador si existe
        $approver_data = null;
        if ($transaction['approved_by']) {
            $approver = get_userdata($transaction['approved_by']);
            $approver_data = array(
                'id' => $transaction['approved_by'],
                'name' => $approver ? $approver->display_name : __('Usuario desconocido', 'aura-suite'),
                'avatar' => $approver ? get_avatar_url($transaction['approved_by'], array('size' => 32)) : ''
            );
        }
        
        // Procesar tags
        $tags = !empty($transaction['tags']) ? explode(',', $transaction['tags']) : array();
        $tags = array_map('trim', $tags);
        
        // Obtener historial de cambios
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        $history_query = $wpdb->prepare(
            "SELECT * FROM {$history_table} WHERE transaction_id = %d ORDER BY changed_at DESC",
            $transaction_id
        );
        $history = $wpdb->get_results($history_query, ARRAY_A);
        
        // Formatear historial
        $formatted_history = array();
        foreach ($history as $entry) {
            $changed_by_user = get_userdata($entry['changed_by']);
            $formatted_history[] = array(
                'id' => $entry['id'],
                'field_changed' => $entry['field_changed'],
                'old_value' => $entry['old_value'],
                'new_value' => $entry['new_value'],
                'changed_at' => $entry['changed_at'],
                'changed_by' => $changed_by_user ? $changed_by_user->display_name : __('Usuario desconocido', 'aura-suite')
            );
        }
        
        // Fase 6, Item 6.1: obtener datos del usuario vinculado
        $related_user_data = null;
        if ( ! empty( $transaction['related_user_id'] ) ) {
            $rel_user = get_userdata( $transaction['related_user_id'] );
            if ( $rel_user ) {
                $related_user_data = [
                    'id'         => (int) $transaction['related_user_id'],
                    'name'       => $rel_user->display_name,
                    'email'      => $rel_user->user_email,
                    'avatar_url' => get_avatar_url( $transaction['related_user_id'], [ 'size' => 48 ] ),
                ];
            }
        }

        // Preparar URL del comprobante
        $receipt_url = '';
        $receipt_exists = false;
        
        if (!empty($transaction['receipt_file'])) {
            $upload_dir = wp_upload_dir();
            
            // Construir ruta completa del archivo
            $receipt_path = $upload_dir['basedir'] . '/aura-finance/receipts/' . basename($transaction['receipt_file']);
            
            // Verificar que el archivo existe
            if (file_exists($receipt_path)) {
                $receipt_url = $upload_dir['baseurl'] . '/aura-finance/receipts/' . basename($transaction['receipt_file']);
                $receipt_exists = true;
            }
        }
        
        // Preparar datos de respuesta
        $response_data = array(
            'id' => $transaction['id'],
            'transaction_type' => $transaction['transaction_type'],
            'category' => array(
                'id' => $transaction['category_id'],
                'name' => $transaction['category_name'],
                'color' => $transaction['category_color'],
                'icon' => $transaction['category_icon']
            ),
            'amount' => floatval($transaction['amount']),
            'transaction_date' => $transaction['transaction_date'],
            'description' => $transaction['description'],
            'notes' => $transaction['notes'],
            'status' => $transaction['status'],
            'payment_method' => $transaction['payment_method'],
            'reference_number' => $transaction['reference_number'],
            'recipient_payer' => $transaction['recipient_payer'],
            'tags' => $tags,
            'receipt_file' => $transaction['receipt_file'],
            'receipt_url' => $receipt_url,
            'receipt_exists' => $receipt_exists,
            'rejection_reason' => $transaction['rejection_reason'],
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at'],
            'approved_at' => $transaction['approved_at'],
            'creator' => $creator_data,
            'approver' => $approver_data,
            'created_by' => $transaction['created_by'],
            'approved_by' => $transaction['approved_by'],
            'history' => $formatted_history,
            'is_creator' => $is_creator,
            // Fase 6, Item 6.1: usuario vinculado
            'related_user_id'      => ! empty( $transaction['related_user_id'] ) ? (int) $transaction['related_user_id'] : null,
            'related_user_concept' => $transaction['related_user_concept'] ?? null,
            'related_user'         => $related_user_data,
            // Información de auditoría
            'audit_log' => $formatted_history
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Aprueba una transacción
     * 
     * @since 1.0.0
     */
    public static function approve_transaction() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_modal_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para aprobar transacciones.', 'aura-suite')
            ));
        }
        
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción inválido.', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Verificar que la transacción existe y está pendiente
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $transaction_id
            ),
            ARRAY_A
        );
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada.', 'aura-suite')
            ));
        }
        
        if ($transaction['status'] !== 'pending') {
            wp_send_json_error(array(
                'message' => __('Solo se pueden aprobar transacciones pendientes.', 'aura-suite')
            ));
        }
        
        // Registrar en historial
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        $wpdb->insert(
            $history_table,
            array(
                'transaction_id' => $transaction_id,
                'field_changed' => 'status',
                'old_value' => 'pending',
                'new_value' => 'approved',
                'changed_by' => get_current_user_id(),
                'changed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
        
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
                'message' => __('Error al aprobar la transacción.', 'aura-suite')
            ));
        }
        
        // Enviar notificación al creador
        self::send_notification(
            $transaction['created_by'],
            sprintf(
                __('Tu transacción #%d ha sido aprobada.', 'aura-suite'),
                $transaction_id
            ),
            'success'
        );
        
        wp_send_json_success(array(
            'message' => __('Transacción aprobada correctamente.', 'aura-suite')
        ));
    }
    
    /**
     * Rechaza una transacción
     * 
     * @since 1.0.0
     */
    public static function reject_transaction() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_modal_nonce', 'nonce');
        
        // Verificar permisos
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para rechazar transacciones.', 'aura-suite')
            ));
        }
        
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_textarea_field($_POST['rejection_reason']) : '';
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción inválido.', 'aura-suite')
            ));
        }
        
        if (empty($rejection_reason)) {
            wp_send_json_error(array(
                'message' => __('Debes proporcionar una razón de rechazo.', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Verificar que la transacción existe y está pendiente
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $transaction_id
            ),
            ARRAY_A
        );
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada.', 'aura-suite')
            ));
        }
        
        if ($transaction['status'] !== 'pending') {
            wp_send_json_error(array(
                'message' => __('Solo se pueden rechazar transacciones pendientes.', 'aura-suite')
            ));
        }
        
        // Registrar en historial
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        $wpdb->insert(
            $history_table,
            array(
                'transaction_id' => $transaction_id,
                'field_changed' => 'status',
                'old_value' => 'pending',
                'new_value' => 'rejected',
                'changed_by' => get_current_user_id(),
                'changed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
        
        // Actualizar transacción
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'rejected',
                'rejection_reason' => $rejection_reason,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al rechazar la transacción.', 'aura-suite')
            ));
        }
        
        // Enviar notificación al creador
        self::send_notification(
            $transaction['created_by'],
            sprintf(
                __('Tu transacción #%d ha sido rechazada. Razón: %s', 'aura-suite'),
                $transaction_id,
                $rejection_reason
            ),
            'error'
        );
        
        wp_send_json_success(array(
            'message' => __('Transacción rechazada correctamente.', 'aura-suite')
        ));
    }
    
    /**
     * Elimina una transacción (soft delete)
     * 
     * @since 1.0.0
     */
    public static function delete_transaction() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_modal_nonce', 'nonce');
        
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción inválido.', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener transacción
        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $transaction_id
            ),
            ARRAY_A
        );
        
        if (!$transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada.', 'aura-suite')
            ));
        }
        
        // Verificar permisos
        $current_user_id = get_current_user_id();
        $can_delete_all = current_user_can('aura_finance_delete_all');
        $can_delete_own = current_user_can('aura_finance_delete_own');
        $is_creator = ($transaction['created_by'] == $current_user_id);
        
        if (!$can_delete_all && (!$can_delete_own || !$is_creator)) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para eliminar esta transacción.', 'aura-suite')
            ));
        }
        
        // Transacciones aprobadas solo pueden ser eliminadas por admin
        if ($transaction['status'] === 'approved' && !$can_delete_all) {
            wp_send_json_error(array(
                'message' => __('Las transacciones aprobadas solo pueden ser eliminadas por un administrador.', 'aura-suite')
            ));
        }
        
        // Registrar en historial
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        $wpdb->insert(
            $history_table,
            array(
                'transaction_id' => $transaction_id,
                'field_changed' => 'status_deletion',
                'old_value' => 'active',
                'new_value' => 'soft_delete',
                'changed_by' => $current_user_id,
                'changed_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
        
        // Realizar soft delete
        $result = $wpdb->update(
            $table,
            array(
                'deleted_at' => current_time('mysql'),
                'deleted_by' => $current_user_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al eliminar la transacción.', 'aura-suite')
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Transacción eliminada correctamente.', 'aura-suite')
        ));
    }
    
    /**
     * Envía una notificación a un usuario
     * 
     * @param int $user_id ID del usuario
     * @param string $message Mensaje de la notificación
     * @param string $type Tipo de notificación (success, error, info, warning)
     * @return bool
     */
    private static function send_notification($user_id, $message, $type = 'info') {
        // Verificar si existe la clase de notificaciones
        if (!class_exists('Aura_Notifications')) {
            return false;
        }
        
        return Aura_Notifications::create_notification(
            $user_id,
            $message,
            $type,
            'financial'
        );
    }
}

// Inicializar
Aura_Financial_Transactions_Ajax::init();
