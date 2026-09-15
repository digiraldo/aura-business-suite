<?php
/**
 * Financial Transactions Update Handler
 * 
 * Maneja la edición de transacciones existentes con:
 * - Control de permisos granular
 * - Registro completo de cambios
 * - Validaciones específicas
 * - Notificaciones automáticas
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Transactions_Update {
    
    /**
     * Días máximos para editar transacciones (configurable)
     * @var int
     */
    private static $max_edit_days = 30;
    
    /**
     * Porcentaje de cambio en monto que requiere motivo
     * @var int
     */
    private static $significant_amount_change = 20;
    
    /**
     * Inicializa los hooks
     */
    public static function init() {
        // AJAX para actualizar transacción
        add_action('wp_ajax_aura_update_transaction', array(__CLASS__, 'ajax_update_transaction'));
        
        // AJAX para obtener datos de transacción para editar
        add_action('wp_ajax_aura_get_transaction_for_edit', array(__CLASS__, 'ajax_get_transaction_for_edit'));
        
        // Hook para cargar configuraciones
        add_action('init', array(__CLASS__, 'load_config'));
    }
    
    /**
     * Cargar configuraciones desde opciones
     */
    public static function load_config() {
        self::$max_edit_days = get_option('aura_finance_max_edit_days', 30);
        self::$significant_amount_change = get_option('aura_finance_significant_amount_change', 20);
    }
    
    /**
     * Obtiene datos de transacción para editar
     * 
     * @since 1.0.0
     */
    public static function ajax_get_transaction_for_edit() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_edit_nonce', 'nonce');
        
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
        
        // Verificar permisos de edición
        $can_edit = self::can_edit_transaction($transaction);
        
        if (!$can_edit['allowed']) {
            wp_send_json_error(array(
                'message' => $can_edit['reason']
            ));
        }
        
        // Procesar tags
        $transaction['tags'] = !empty($transaction['tags']) ? explode(',', $transaction['tags']) : array();
        
        // Obtener información de categoría
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        $category = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$categories_table} WHERE id = %d",
                $transaction['category_id']
            ),
            ARRAY_A
        );
        
        $transaction['category_info'] = $category;
        
        wp_send_json_success(array(
            'transaction' => $transaction,
            'can_edit' => $can_edit
        ));
    }
    
    /**
     * Actualiza una transacción existente
     * 
     * @since 1.0.0
     */
    public static function ajax_update_transaction() {
        // Verificar nonce
        check_ajax_referer('aura_transaction_edit_nonce', 'nonce');
        
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        
        if (!$transaction_id) {
            wp_send_json_error(array(
                'message' => __('ID de transacción inválido.', 'aura-suite')
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        
        // Obtener transacción actual
        $old_transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $transaction_id
            ),
            ARRAY_A
        );
        
        if (!$old_transaction) {
            wp_send_json_error(array(
                'message' => __('Transacción no encontrada.', 'aura-suite')
            ));
        }
        
        // Verificar permisos de edición
        $can_edit = self::can_edit_transaction($old_transaction);
        
        if (!$can_edit['allowed']) {
            wp_send_json_error(array(
                'message' => $can_edit['reason']
            ));
        }
        
        // Recopilar datos nuevos
        // Convertir fecha dd/mm/yyyy → yyyy-mm-dd (formato MySQL)
        $raw_date = sanitize_text_field($_POST['transaction_date'] ?? '');
        $parsed_date = DateTime::createFromFormat('d/m/Y', $raw_date);
        $transaction_date_db = $parsed_date ? $parsed_date->format('Y-m-d') : $raw_date;

        // Fase 8.2: respetar restricción de área para usuarios view_own (Scope Multi-Área)
        $area_id = null;
        if (
            current_user_can('aura_areas_view_own')
            && !current_user_can('aura_areas_view_all')
            && !current_user_can('manage_options')
        ) {
            $allowed_areas = class_exists('Aura_Areas_Setup') ? Aura_Areas_Setup::get_user_area_ids( get_current_user_id() ) : array();
            if ( empty( $allowed_areas ) ) {
                $legacy_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}aura_areas WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
                    get_current_user_id()
                ));
                if ( $legacy_id > 0 ) {
                    $allowed_areas = array( $legacy_id );
                }
            }

            $posted_area = ! empty( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
            if ( $posted_area > 0 && in_array( $posted_area, $allowed_areas, true ) ) {
                $area_id = $posted_area;
            } elseif ( ! empty( $allowed_areas ) ) {
                $area_id = $allowed_areas[0];
            } else {
                $area_id = null;
            }
        } elseif (!empty($_POST['area_id'])) {
            $area_id = absint($_POST['area_id']) ?: null;
        }

        $tx_type = $old_transaction['transaction_type'] ?? '';
        $posted_category_id = (!empty($_POST['category_id']) && $area_id > 0) ? intval($_POST['category_id']) : 0;
        $expense_category_id = isset($_POST['expense_category_id']) ? intval($_POST['expense_category_id']) : 0;
        if ($expense_category_id <= 0 && !empty($old_transaction['expense_category_id'])) {
            $expense_category_id = intval($old_transaction['expense_category_id']);
        } elseif ($expense_category_id <= 0 && !empty($old_transaction['category_id'])) {
            $expense_category_id = intval($old_transaction['category_id']);
        }

        $source_account_id = isset($_POST['source_account_id']) ? intval($_POST['source_account_id']) : 0;
        $destination_account_id = isset($_POST['destination_account_id']) ? intval($_POST['destination_account_id']) : 0;

        // Fallback robusto al editar: si no se modificaron cuentas o el campo no llegó en el POST, conservar la cuenta original
        if ($tx_type === 'income' && $destination_account_id <= 0 && !empty($old_transaction['destination_account_id'])) {
            $destination_account_id = intval($old_transaction['destination_account_id']);
        }
        if (($tx_type === 'expense' || $tx_type === 'capital') && $source_account_id <= 0 && !empty($old_transaction['source_account_id']) && empty($_POST['related_user_id'])) {
            $source_account_id = intval($old_transaction['source_account_id']);
        }

        $amount = (isset($_POST['amount']) && floatval($_POST['amount']) > 0) ? floatval($_POST['amount']) : floatval($old_transaction['amount']);
        $transaction_date_final = !empty($transaction_date_db) ? $transaction_date_db : $old_transaction['transaction_date'];
        $payment_method = !empty($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : ($old_transaction['payment_method'] ?? '');

        // Si no se selecciona categoría presupuestaria explícita vinculada a un área, la categoría principal es la del gasto
        $effective_category_id = $posted_category_id > 0 ? $posted_category_id : $expense_category_id;

        $new_data = array(
            'category_id' => $effective_category_id,
            'expense_category_id' => $expense_category_id > 0 ? $expense_category_id : $effective_category_id,
            'amount' => $amount,
            'transaction_date' => $transaction_date_final,
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : ($old_transaction['description'] ?? ''),
            'payment_method' => $payment_method,
            'source_account_id' => $source_account_id > 0 ? $source_account_id : null,
            'destination_account_id' => $destination_account_id > 0 ? $destination_account_id : null,
            'reference_number' => sanitize_text_field($_POST['reference_number'] ?? ''),
            'recipient_payer' => sanitize_text_field($_POST['recipient_payer'] ?? ''),
            'third_party_id'  => !empty($_POST['third_party_id']) ? intval($_POST['third_party_id']) : null,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'tags' => sanitize_text_field($_POST['tags'] ?? ''),
            'receipt_file' => (function() {
                $raw = isset($_POST['receipt_file']) ? wp_unslash($_POST['receipt_file']) : '';
                if (empty($raw)) return '';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $sanitized = array();
                    foreach ($decoded as $item) {
                        if (is_array($item)) {
                            $sanitized[] = array(
                                'url'      => esc_url_raw($item['url'] ?? ($item['file_url'] ?? '')),
                                'name'     => sanitize_text_field($item['name'] ?? ($item['filename'] ?? '')),
                                'is_drive' => !empty($item['is_drive']),
                            );
                        } elseif (is_string($item) && !empty($item)) {
                            $sanitized[] = (strpos($item, 'http') === 0) ? esc_url_raw($item) : sanitize_text_field($item);
                        }
                    }
                    return !empty($sanitized) ? json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
                }
                return (strpos($raw, 'http') === 0) ? esc_url_raw($raw) : sanitize_text_field($raw);
            })(),
            // Fase 6, Item 6.1: usuario vinculado y concepto por defecto
            'related_user_id'      => intval( $_POST['related_user_id'] ?? 0 ) ?: null,
            'related_user_concept' => sanitize_key( $_POST['related_user_concept'] ?? '' ) ?: ( intval( $_POST['related_user_id'] ?? 0 ) > 0 ? ( sanitize_text_field( $_POST['transaction_type'] ?? '' ) === 'income' ? 'charge_to_user' : 'payment_to_user' ) : 'unlinked' ),
            // Fase 8.2: área / programa
            'area_id'              => $area_id,
        );
        
        // Motivo del cambio (opcional pero recomendado)
        $change_reason = sanitize_textarea_field($_POST['change_reason'] ?? '');
        
        // Validaciones
        $validation = self::validate_update($old_transaction, $new_data, $change_reason);
        
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => $validation['message'],
                'errors' => $validation['errors']
            ));
        }
        
        // Verificar categoría del gasto (obligatoria)
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        $expense_category = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$categories_table} WHERE id = %d AND is_active = 1",
                intval($new_data['expense_category_id'])
            ),
            ARRAY_A
        );

        if (!$expense_category) {
            wp_send_json_error(array(
                'message' => __('La categoría del gasto seleccionada no es válida o está inactiva.', 'aura-suite')
            ));
        }

        $tx_type = $old_transaction['transaction_type'];
        $is_expense_like = ($tx_type === 'expense' || $tx_type === 'capital');

        if ($expense_category['type'] !== 'both' && $expense_category['type'] !== $tx_type && !($tx_type === 'capital' && $expense_category['type'] === 'expense')) {
            wp_send_json_error(array(
                'message' => __('La categoría del gasto no corresponde al tipo de transacción.', 'aura-suite')
            ));
        }

        // Verificar categoría de presupuesto (opcional): si se seleccionó manualmente, validar que exista
        if ($posted_category_id > 0) {
            $budget_category = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$categories_table} WHERE id = %d AND is_active = 1",
                    $posted_category_id
                ),
                ARRAY_A
            );

            if (!$budget_category) {
                wp_send_json_error(array(
                    'message' => __('La categoría del presupuesto no es válida o está inactiva.', 'aura-suite')
                ));
            }

            if ($budget_category['type'] !== 'both' && $budget_category['type'] !== $tx_type && !($tx_type === 'capital' && $budget_category['type'] === 'expense')) {
                wp_send_json_error(array(
                    'message' => __('La categoría del presupuesto no es válida para este tipo de transacción.', 'aura-suite')
                ));
            }
        }

        if ($tx_type === 'income' && intval($new_data['destination_account_id']) <= 0) {
            wp_send_json_error(array(
                'message' => __('En un ingreso debes seleccionar la cuenta destino.', 'aura-suite')
            ));
        }

        if ($is_expense_like) {
            $has_source = intval($new_data['source_account_id']) > 0;
            $has_related_user = intval($new_data['related_user_id']) > 0;
            if (!$has_source && !$has_related_user) {
                wp_send_json_error(array(
                    'message' => __('En un egreso debes seleccionar la cuenta origen o vincular un usuario para registrar reembolso por dinero personal.', 'aura-suite')
                ));
            }
        }

        if ($tx_type === 'income' && !empty($new_data['source_account_id'])) {
            $new_data['source_account_id'] = null;
        }

        if ($is_expense_like && !empty($new_data['destination_account_id'])) {
            $new_data['destination_account_id'] = null;
        }

        if (intval($new_data['category_id']) <= 0) {
            wp_send_json_error(array(
                'message' => __('No se pudo determinar una categoría válida para la transacción.', 'aura-suite')
            ));
        }
        
        // Detectar cambios en los campos editables
        $changes = self::detect_changes($old_transaction, $new_data);
        
        // Si la transacción estaba rechazada, registrar el re-envío ANTES de verificar
        // si hay cambios, para que la re-submisión sea válida aunque no se modifiquen campos.
        if ($old_transaction['status'] === 'rejected') {
            $changes[] = array(
                'field' => 'status',
                'old_value' => 'rejected',
                'new_value' => 'pending (re-enviada para aprobación)',
                'change_reason' => sprintf(
                    __('Transacción corregida y re-enviada después de ser rechazada. Motivo original del rechazo: %s', 'aura-suite'),
                    $old_transaction['rejection_reason']
                )
            );
        }
        
        if (empty($changes)) {
            wp_send_json_error(array(
                'message' => __('No se detectaron cambios en la transacción.', 'aura-suite')
            ));
        }
        
        // Actualizar transacción
        $update_data = array_merge($new_data, array(
            'updated_at' => current_time('mysql')
        ));
        
        // Si la transacción estaba aprobada y se edita, volver a pendiente
        if ($old_transaction['status'] === 'approved' && !current_user_can('aura_finance_edit_all')) {
            $update_data['status'] = 'pending';
            $update_data['approved_by'] = null;
            $update_data['approved_at'] = null;
        }
        
        // Si la transacción estaba rechazada, actualizar campos de estado
        // (el registro en historial ya se añadió antes del empty check)
        if ($old_transaction['status'] === 'rejected') {
            $update_data['status'] = 'pending';
            $update_data['rejection_reason'] = null;
            // Mantener approved_by para tracking (quien rechazó originalmente)
            $update_data['approved_at'] = null;
        }
        
        // Construir array de formatos dinámicamente según los campos en $update_data
        $format_map = array(
            'category_id'      => '%d',
            'expense_category_id' => '%d',
            'amount'           => '%f',
            'transaction_date'  => '%s',
            'description'      => '%s',
            'payment_method'   => '%s',
            'source_account_id' => '%d',
            'destination_account_id' => '%d',
            'reference_number' => '%s',
            'recipient_payer'  => '%s',
            'third_party_id'   => '%d',
            'notes'            => '%s',
            'tags'             => '%s',
            'receipt_file'     => '%s',
            'updated_at'       => '%s',
            'status'           => '%s',
            'rejection_reason' => '%s',
            'approved_by'      => '%d',
            'approved_at'      => '%s',
            // Fase 6
            'related_user_id'      => '%d',
            'related_user_concept' => '%s',
            // Fase 8.2
            'area_id'              => '%d',
        );
        $update_formats = array_map(function($key) use ($format_map) {
            return isset($format_map[$key]) ? $format_map[$key] : '%s';
        }, array_keys($update_data));

        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $transaction_id),
            $update_formats,
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Error al actualizar la transacción en la base de datos.', 'aura-suite'),
                'debug' => $wpdb->last_error
            ));
        }
        
        // Registrar cambios en historial
        self::log_changes($transaction_id, $changes, $change_reason);
        
        // Enviar notificaciones
        self::send_notifications($transaction_id, $old_transaction, $update_data, $changes);
        
        // Disparar hook para extensibilidad
        do_action('aura_finance_transaction_updated', $transaction_id, $old_transaction, $update_data, get_current_user_id());
        
        wp_send_json_success(array(
            'message' => __('Transacción actualizada exitosamente.', 'aura-suite'),
            'transaction_id' => $transaction_id,
            'changes_count' => count($changes),
            'status_changed' => isset($update_data['status']) && $update_data['status'] !== $old_transaction['status'],
            'redirect_url' => admin_url('admin.php?page=aura-financial-transactions')
        ));
    }
    
    /**
     * Verifica si un usuario puede editar una transacción
     * 
     * @param array $transaction Datos de la transacción
     * @return array ['allowed' => bool, 'reason' => string]
     */
    private static function can_edit_transaction($transaction) {
        $current_user_id = get_current_user_id();
        $can_edit_all = current_user_can('aura_finance_edit_all');
        $can_edit_own = current_user_can('aura_finance_edit_own');
        $is_creator = ($transaction['created_by'] == $current_user_id);
        
        // Admin puede editar todo
        if ($can_edit_all) {
            return array('allowed' => true, 'reason' => '');
        }
        
        // Usuario normal solo puede editar sus propias transacciones pendientes o rechazadas
        if ($can_edit_own && $is_creator) {
            // Verificar estado: permitir editar pending y rejected (para corregir y re-enviar)
            if ($transaction['status'] === 'approved') {
                return array(
                    'allowed' => false,
                    'reason' => __('No puedes editar transacciones aprobadas. Solo un administrador puede modificarlas.', 'aura-suite')
                );
            }
            
            // Verificar antigüedad
            $created_date = new DateTime($transaction['created_at']);
            $now = new DateTime();
            $diff_days = $now->diff($created_date)->days;
            
            if ($diff_days > self::$max_edit_days) {
                return array(
                    'allowed' => false,
                    'reason' => sprintf(
                        __('No puedes editar transacciones con más de %d días de antigüedad.', 'aura-suite'),
                        self::$max_edit_days
                    )
                );
            }
            
            return array('allowed' => true, 'reason' => '');
        }
        
        return array(
            'allowed' => false,
            'reason' => __('No tienes permisos para editar esta transacción.', 'aura-suite')
        );
    }
    
    /**
     * Valida los datos de actualización
     * 
     * @param array $old_data Datos antiguos
     * @param array $new_data Datos nuevos
     * @param string $change_reason Motivo del cambio
     * @return array ['valid' => bool, 'message' => string, 'errors' => array]
     */
    private static function validate_update($old_data, $new_data, $change_reason) {
        $errors = array();
        
        // Validar campos obligatorios
        if (empty($new_data['expense_category_id']) || intval($new_data['expense_category_id']) <= 0) {
            $errors[] = __('Debe seleccionar la categoría del gasto.', 'aura-suite');
        }
        
        if (empty($new_data['amount']) || $new_data['amount'] <= 0) {
            $errors[] = __('El monto debe ser mayor a 0.', 'aura-suite');
        }
        
        if (empty($new_data['transaction_date'])) {
            $errors[] = __('La fecha es requerida.', 'aura-suite');
        }
        
        if (empty($new_data['description']) || strlen($new_data['description']) < 10) {
            $errors[] = __('La descripción debe tener al menos 10 caracteres.', 'aura-suite');
        }

        if ($old_data['transaction_type'] === 'income' && intval($new_data['destination_account_id']) <= 0) {
            $errors[] = __('En un ingreso debes seleccionar la cuenta destino.', 'aura-suite');
        }

        if ($old_data['transaction_type'] === 'expense') {
            $has_source = intval($new_data['source_account_id']) > 0;
            $has_related_user = intval($new_data['related_user_id']) > 0;
            if (!$has_source && !$has_related_user) {
                $errors[] = __('En un egreso debes seleccionar la cuenta origen o vincular un usuario para registrar reembolso por dinero personal.', 'aura-suite');
            }
        }
        
        // Validar cambio significativo de monto
        if ($new_data['amount'] != $old_data['amount']) {
            $old_amount = floatval($old_data['amount']);
            $new_amount = floatval($new_data['amount']);
            $percent_change = abs(($new_amount - $old_amount) / $old_amount * 100);
            
            if ($percent_change > self::$significant_amount_change && empty($change_reason)) {
                $errors[] = sprintf(
                    __('El cambio en el monto es significativo (%.1f%%). Debes proporcionar un motivo del cambio.', 'aura-suite'),
                    $percent_change
                );
            }
        }
        
        if (!empty($errors)) {
            return array(
                'valid' => false,
                'message' => __('Errores de validación encontrados.', 'aura-suite'),
                'errors' => $errors
            );
        }
        
        return array('valid' => true, 'message' => '', 'errors' => array());
    }
    
    /**
     * Detecta cambios entre datos antiguos y nuevos
     * 
     * @param array $old_data Datos antiguos
     * @param array $new_data Datos nuevos
     * @return array Array de cambios detectados
     */
    private static function detect_changes($old_data, $new_data) {
        $changes = array();
        
        $fields_to_check = array(
            'category_id' => 'Categoría',
            'expense_category_id' => 'Categoría del gasto',
            'amount' => 'Monto',
            'transaction_date' => 'Fecha',
            'description' => 'Descripción',
            'payment_method' => 'Método de Pago',
            'source_account_id' => 'Cuenta Origen',
            'destination_account_id' => 'Cuenta Destino',
            'reference_number' => 'Número de Referencia',
            'recipient_payer' => 'Destinatario/Pagador',
            'third_party_id'  => 'Beneficiario / Proveedor / Tercero',
            // Fase 6, Item 6.1
            'related_user_id'      => 'Usuario Vinculado',
            'related_user_concept' => 'Concepto de Vinculación',
            // Fase 8.2
            'area_id' => 'Área / Programa',
            'notes' => 'Notas',
            'tags' => 'Etiquetas',
            'receipt_file' => 'Comprobante'
        );
        
        foreach ($fields_to_check as $field => $label) {
            $old_value = (string) ($old_data[$field] ?? '');
            $new_value = (string) ($new_data[$field] ?? '');
            
            // Normalizar valores para comparación
            $old_value = trim($old_value);
            $new_value = trim($new_value);
            
            // Normalizar comparación de montos (evitar falso positivo por precisión de float)
            if ($field === 'amount') {
                $old_value = number_format(floatval($old_value), 2, '.', '');
                $new_value = number_format(floatval($new_value), 2, '.', '');
            }
            
            if ($old_value != $new_value) {
                $changes[] = array(
                    'field' => $field,
                    'label' => $label,
                    'old_value' => $old_value,
                    'new_value' => $new_value
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Registra cambios en la tabla de historial
     * 
     * @param int $transaction_id ID de la transacción
     * @param array $changes Array de cambios
     * @param string $change_reason Motivo del cambio
     */
    private static function log_changes($transaction_id, $changes, $change_reason) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        
        foreach ($changes as $change) {
            // Ignorar entradas sin campo definido
            if (empty($change['field'])) {
                continue;
            }
            $wpdb->insert(
                $history_table,
                array(
                    'transaction_id' => $transaction_id,
                    'field_changed' => $change['field'],
                    'old_value' => $change['old_value'],
                    'new_value' => $change['new_value'],
                    'changed_by' => get_current_user_id(),
                    'changed_at' => current_time('mysql'),
                    'change_reason' => $change_reason
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
    }
    
    /**
     * Envía notificaciones sobre la actualización
     * 
     * @param int $transaction_id ID de la transacción
     * @param array $old_data Datos antiguos
     * @param array $new_data Datos nuevos
     * @param array $changes Array de cambios
     */
    private static function send_notifications($transaction_id, $old_data, $new_data, $changes) {
        $current_user_id = get_current_user_id();
        $creator_id = $old_data['created_by'];
        
        // Si alguien más edita la transacción, notificar al creador
        if ($current_user_id != $creator_id) {
            $editor = get_userdata($current_user_id);
            if ($editor) {
                $message = sprintf(
                    __('Tu transacción #%d ha sido modificada por %s. Se realizaron %d cambios.', 'aura-suite'),
                    $transaction_id,
                    $editor->display_name,
                    count($changes)
                );
                
                if (class_exists('Aura_Notifications')) {
                    Aura_Notifications::create_notification(
                        $creator_id,
                        $message,
                        'info',
                        'financial'
                    );
                }
            }
        }
        
        // Si la transacción estaba aprobada y volvió a pendiente, notificar al aprobador
        if ($old_data['status'] === 'approved' && isset($new_data['status']) && $new_data['status'] === 'pending') {
            if ($old_data['approved_by']) {
                $message = sprintf(
                    __('La transacción #%d que aprobaste ha sido modificada y requiere revisión nuevamente.', 'aura-suite'),
                    $transaction_id
                );
                
                if (class_exists('Aura_Notifications')) {
                    Aura_Notifications::create_notification(
                        $old_data['approved_by'],
                        $message,
                        'warning',
                        'financial'
                    );
                }
            }
        }
        
        // Si la transacción estaba rechazada y fue corregida (vuelve a pendiente), notificar al aprobador
        if ($old_data['status'] === 'rejected' && isset($new_data['status']) && $new_data['status'] === 'pending') {
            if ($old_data['approved_by']) {
                $creator  = get_userdata($creator_id);
                $approver = get_userdata($old_data['approved_by']);

                if ($creator && $approver) {
                    $message = sprintf(
                        __('La transacción #%d que rechazaste ha sido corregida por %s y está lista para revisión nuevamente.', 'aura-suite'),
                        $transaction_id,
                        $creator->display_name
                    );
                    
                    if (class_exists('Aura_Notifications')) {
                        Aura_Notifications::create_notification(
                            $old_data['approved_by'],
                            $message,
                            'info',
                            'financial'
                        );
                    }
                    
                    // También enviar email si está habilitado
                    if (get_option('aura_finance_email_notifications', false)) {
                        $subject = sprintf(__('[Aura] Transacción #%d Re-enviada para Aprobación', 'aura-suite'), $transaction_id);
                        $email_message = sprintf(
                            __("Hola %s,\n\nLa transacción #%d que rechazaste anteriormente ha sido corregida y re-enviada para tu aprobación.\n\nMotivo original del rechazo:\n%s\n\nPor favor, revisa los cambios realizados y aprueba o rechaza nuevamente según corresponda.\n\nVer transacción: %s\n\nSaludos,\nAura Business Suite", 'aura-suite'),
                            $approver->display_name,
                            $transaction_id,
                            $old_data['rejection_reason'],
                            admin_url('admin.php?page=aura-financial-pending')
                        );
                        
                        wp_mail($approver->user_email, $subject, $email_message);
                    }
                }
            }
        }
    }
    
    /**
     * Obtiene el historial de cambios de una transacción
     * 
     * @param int $transaction_id ID de la transacción
     * @return array Historial de cambios
     */
    public static function get_transaction_history($transaction_id) {
        global $wpdb;
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        
        $history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$history_table} WHERE transaction_id = %d ORDER BY changed_at DESC",
                $transaction_id
            ),
            ARRAY_A
        );
        
        // Enriquecer con información de usuario
        foreach ($history as &$entry) {
            $user = get_userdata($entry['changed_by']);
            $entry['changed_by_name'] = $user ? $user->display_name : __('Usuario desconocido', 'aura-suite');
        }
        
        return $history;
    }
}

// Inicializar
Aura_Financial_Transactions_Update::init();
