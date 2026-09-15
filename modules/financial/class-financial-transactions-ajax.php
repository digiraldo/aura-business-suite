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
        // Verificar nonce tolerante
        $nonce = $_POST['nonce'] ?? ($_POST['_wpnonce'] ?? '');
        $nonce_valid = wp_verify_nonce($nonce, 'aura_transaction_modal_nonce')
                    || wp_verify_nonce($nonce, 'aura_transactions_list_nonce')
                    || wp_verify_nonce($nonce, 'aura_transaction_nonce');

        if (!$nonce_valid) {
            wp_send_json_error(array(
                'message' => __('Sesión o nonce inválido. Por favor recarga la página.', 'aura-suite')
            ));
        }
        
        // Verificar permisos: puede ver si tiene cualquier permiso financiero o de aprobación o es admin
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
        $accounts_table = $wpdb->prefix . 'aura_finance_accounts';
        $areas_table = $wpdb->prefix . 'aura_areas';

        $has_accounts_table = ($wpdb->get_var("SHOW TABLES LIKE '{$accounts_table}'") === $accounts_table);
        $has_areas_table = ($wpdb->get_var("SHOW TABLES LIKE '{$areas_table}'") === $areas_table);
        $has_categories_table = ($wpdb->get_var("SHOW TABLES LIKE '{$categories_table}'") === $categories_table);

        $accounts_join = $has_accounts_table 
            ? "LEFT JOIN {$accounts_table} sa ON t.source_account_id = sa.id
               LEFT JOIN {$accounts_table} da ON t.destination_account_id = da.id"
            : "";
        $accounts_select = $has_accounts_table
            ? "sa.name as source_account_name, sa.currency as source_account_currency, sa.account_type as source_account_type, sa.institution as source_account_institution, da.name as destination_account_name, da.currency as destination_account_currency, da.account_type as destination_account_type, da.institution as destination_account_institution,"
            : "'' as source_account_name, '' as source_account_currency, '' as source_account_type, '' as source_account_institution, '' as destination_account_name, '' as destination_account_currency, '' as destination_account_type, '' as destination_account_institution,";

        $areas_join = $has_areas_table ? "LEFT JOIN {$areas_table} ar ON t.area_id = ar.id" : "";
        $areas_select = $has_areas_table ? "ar.name as area_name, ar.slug as area_slug, ar.type as area_type, ar.description as area_description, ar.color as area_color, ar.icon as area_icon, ar.logo_id as area_logo_id," : "'' as area_name, '' as area_slug, '' as area_type, '' as area_description, '' as area_color, '' as area_icon, 0 as area_logo_id,";

        $categories_join = $has_categories_table 
            ? "LEFT JOIN {$categories_table} c ON t.category_id = c.id
               LEFT JOIN {$categories_table} ec ON t.expense_category_id = ec.id
               LEFT JOIN {$categories_table} pc ON pc.id = COALESCE(ec.parent_id, c.parent_id)"
            : "";
        $categories_select = $has_categories_table
            ? "COALESCE(ec.name, c.name) as category_name, COALESCE(ec.color, c.color) as category_color, COALESCE(ec.icon, c.icon) as category_icon, COALESCE(ec.slug, c.slug) as category_slug, COALESCE(ec.type, c.type) as category_type, COALESCE(ec.is_capex, c.is_capex) as category_is_capex, COALESCE(ec.description, c.description) as category_description, COALESCE(ec.parent_id, c.parent_id) as category_parent_id, pc.name as parent_category_name, pc.slug as parent_category_slug, pc.icon as parent_category_icon, pc.color as parent_category_color, ec.name as expense_category_name,"
            : "'' as category_name, '' as category_color, '' as category_icon, '' as category_slug, '' as category_type, 0 as category_is_capex, '' as category_description, 0 as category_parent_id, '' as parent_category_name, '' as parent_category_slug, '' as parent_category_icon, '' as parent_category_color, '' as expense_category_name,";

        $tp_table = $wpdb->prefix . 'aura_finance_third_parties';
        $has_tp_table = ($wpdb->get_var("SHOW TABLES LIKE '{$tp_table}'") === $tp_table);

        $tp_join = $has_tp_table ? "LEFT JOIN {$tp_table} tp ON t.third_party_id = tp.id" : "";
        $tp_select = $has_tp_table
            ? "tp.full_name as tp_full_name, tp.commercial_name as tp_commercial_name, tp.party_type as tp_party_type, tp.accounting_role as tp_accounting_role, tp.tax_id_type as tp_tax_id_type, tp.document_id as tp_document_id, tp.phone as tp_phone, tp.email as tp_email, tp.website as tp_website, tp.address as tp_address, tp.notes as tp_notes, tp.logo_id as tp_logo_id"
            : "'' as tp_full_name, '' as tp_commercial_name, '' as tp_party_type, 'supplier' as tp_accounting_role, '' as tp_tax_id_type, '' as tp_document_id, '' as tp_phone, '' as tp_email, '' as tp_website, '' as tp_address, '' as tp_notes, 0 as tp_logo_id";

        // Obtener transacción con datos de categoría, cuentas y área
        $query = $wpdb->prepare(
            "SELECT
                t.*,
                {$categories_select}
                {$accounts_select}
                {$areas_select}
                {$tp_select}
            FROM {$table} t
            {$categories_join}
            {$accounts_join}
            {$areas_join}
            {$tp_join}
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
        $can_view_all = current_user_can('aura_finance_view_all') || current_user_can('aura_finance_approve') || current_user_can('manage_options');
        $can_view_own = current_user_can('aura_finance_view_own');
        $is_creator = ($transaction['created_by'] == $current_user_id);
        
        if (!$can_view_all && (!$can_view_own || !$is_creator)) {
            wp_send_json_error(array(
                'message' => __('No tienes permisos para ver esta transacción.', 'aura-suite')
            ));
        }
        
        // Obtener información del creador
        $creator = !empty($transaction['created_by']) ? get_userdata($transaction['created_by']) : null;
        $creator_data = array(
            'id' => (int) ($transaction['created_by'] ?? 0),
            'name' => $creator ? $creator->display_name : ($transaction['created_by'] ? sprintf(__('Usuario #%d', 'aura-suite'), $transaction['created_by']) : __('Sistema', 'aura-suite')),
            'avatar' => $creator ? get_avatar_url($transaction['created_by'], array('size' => 48)) : ''
        );
        
        // Obtener información del aprobador si existe
        $approver_data = null;
        if (!empty($transaction['approved_by'])) {
            $approver = get_userdata($transaction['approved_by']);
            $approver_data = array(
                'id' => (int) $transaction['approved_by'],
                'name' => $approver ? $approver->display_name : sprintf(__('Usuario #%d', 'aura-suite'), $transaction['approved_by']),
                'avatar' => $approver ? get_avatar_url($transaction['approved_by'], array('size' => 32)) : ''
            );
        }
        
        // Procesar tags
        $tags = !empty($transaction['tags']) ? explode(',', $transaction['tags']) : array();
        $tags = array_map('trim', $tags);
        
        // Obtener historial de cambios
        $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
        $history = array();
        if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") === $history_table) {
            $history_query = $wpdb->prepare(
                "SELECT * FROM {$history_table} WHERE transaction_id = %d ORDER BY changed_at DESC",
                $transaction_id
            );
            $history = $wpdb->get_results($history_query, ARRAY_A) ?: array();
        }
        
        $categories_map = array();
        if ($has_categories_table) {
            $category_rows = $wpdb->get_results("SELECT id, name FROM {$categories_table}", ARRAY_A) ?: array();
            foreach ($category_rows as $row) {
                $categories_map[(string) $row['id']] = $row['name'];
            }
        }

        $accounts_map = array();
        if ($has_accounts_table) {
            $account_rows = $wpdb->get_results("SELECT id, name, currency FROM {$accounts_table}", ARRAY_A) ?: array();
            foreach ($account_rows as $row) {
                $accounts_map[(string) $row['id']] = $row['name'] . ' · ' . $row['currency'];
            }
        }

        $areas_map = array(
            '' => __('General (sin área)', 'aura-suite'),
            '0' => __('General (sin área)', 'aura-suite'),
        );
        if ($has_areas_table) {
            $area_rows = $wpdb->get_results("SELECT id, name FROM {$areas_table}", ARRAY_A) ?: array();
            foreach ($area_rows as $row) {
                $areas_map[(string) $row['id']] = $row['name'];
            }
        }

        $status_map = array(
            'pending'            => __('Pendiente', 'aura-suite'),
            'approved'           => __('Aprobada', 'aura-suite'),
            'rejected'           => __('Rechazada', 'aura-suite'),
            'active'             => __('Activa', 'aura-suite'),
            'soft_delete'        => __('En papelera', 'aura-suite'),
            'status_resubmitted' => __('Re-enviada para aprobación', 'aura-suite'),
        );

        $payment_method_map = array(
            'cash'            => __('Efectivo', 'aura-suite'),
            'efectivo'        => __('Efectivo', 'aura-suite'),
            'transfer'        => __('Transferencia', 'aura-suite'),
            'transferencia'   => __('Transferencia', 'aura-suite'),
            'bank_transfer'   => __('Transferencia Bancaria', 'aura-suite'),
            'card'            => __('Tarjeta', 'aura-suite'),
            'tarjeta'         => __('Tarjeta', 'aura-suite'),
            'credit_card'     => __('Tarjeta de Crédito', 'aura-suite'),
            'debit_card'      => __('Tarjeta de Débito', 'aura-suite'),
            'check'           => __('Cheque', 'aura-suite'),
            'cheque'          => __('Cheque', 'aura-suite'),
            'digital_wallet'  => __('Billetera Digital', 'aura-suite'),
            'nequi'           => __('Nequi', 'aura-suite'),
            'daviplata'       => __('DaviPlata', 'aura-suite'),
            'other'           => __('Otro', 'aura-suite'),
            'otro'            => __('Otro', 'aura-suite'),
        );

        $concept_map = array(
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

        $field_labels_map = array(
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
            'category_merged'        => __('Fusión de categoría', 'aura-suite'),
        );

        $action_labels_map = array(
            'category_id'            => __('Cambio de categoría presupuesto', 'aura-suite'),
            'expense_category_id'    => __('Cambio de categoría del gasto', 'aura-suite'),
            'amount'                 => __('Cambio de monto', 'aura-suite'),
            'transaction_date'       => __('Cambio de fecha', 'aura-suite'),
            'description'            => __('Cambio de descripción', 'aura-suite'),
            'source_account_id'      => __('Cambio de cuenta origen', 'aura-suite'),
            'destination_account_id' => __('Cambio de cuenta destino', 'aura-suite'),
            'area_id'                => __('Cambio de área / programa', 'aura-suite'),
            'payment_method'         => __('Cambio de método de pago', 'aura-suite'),
            'reference_number'       => __('Cambio de referencia', 'aura-suite'),
            'recipient_payer'        => __('Cambio de destinatario / pagador', 'aura-suite'),
            'third_party_id'         => __('Cambio de tercero / beneficiario', 'aura-suite'),
            'related_user_id'        => __('Cambio de usuario vinculado', 'aura-suite'),
            'related_user_concept'   => __('Cambio de concepto de vinculación', 'aura-suite'),
            'notes'                  => __('Cambio de notas', 'aura-suite'),
            'tags'                   => __('Cambio de etiquetas', 'aura-suite'),
            'receipt_file'           => __('Cambio de comprobante', 'aura-suite'),
            'status'                 => __('Cambio de estado', 'aura-suite'),
            'status_deletion'        => __('Envío a papelera / Eliminación', 'aura-suite'),
            'status_resubmitted'     => __('Re-enviada para aprobación', 'aura-suite'),
            'rejection_reason'       => __('Motivo de rechazo', 'aura-suite'),
            'category_merged'        => __('Fusión de categoría', 'aura-suite'),
        );

        $tp_map = array();
        if ($has_tp_table) {
            $tp_rows = $wpdb->get_results("SELECT id, full_name, commercial_name FROM {$tp_table}", ARRAY_A) ?: array();
            foreach ($tp_rows as $row) {
                $tp_map[(string) $row['id']] = !empty($row['commercial_name']) ? $row['commercial_name'] : $row['full_name'];
            }
        }

        $format_history_value = static function ($field, $value) use ($categories_map, $accounts_map, $areas_map, $status_map, $payment_method_map, $concept_map, $tp_map, $wpdb) {
            $raw = is_scalar($value) ? trim((string) $value) : '';

            if ($raw === '' || $raw === '0' && in_array($field, array('area_id', 'related_user_id', 'third_party_id'), true)) {
                return $field === 'area_id' ? ($areas_map[''] ?? 'General (sin área)') : '—';
            }

            switch ($field) {
                case 'amount':
                    return '$' . number_format((float) $raw, 2, '.', ',');

                case 'transaction_date':
                    $ts = strtotime($raw);
                    return $ts ? date('d/m/Y', $ts) : $raw;

                case 'category_id':
                case 'expense_category_id':
                    return $categories_map[$raw] ?? $raw;

                case 'source_account_id':
                case 'destination_account_id':
                    return $accounts_map[$raw] ?? $raw;

                case 'area_id':
                    return $areas_map[$raw] ?? $raw;

                case 'payment_method':
                    return $payment_method_map[$raw] ?? ucfirst($raw);

                case 'related_user_id':
                    $user = get_userdata((int) $raw);
                    return $user ? $user->display_name : ($raw ? sprintf(__('Usuario #%d', 'aura-suite'), (int)$raw) : '—');

                case 'related_user_concept':
                    return $concept_map[$raw] ?? ($raw === 'unlinked' ? __('Desvinculado', 'aura-suite') : $raw);

                case 'receipt_file':
                    if (empty($raw)) {
                        return __('— (Sin comprobante)', 'aura-suite');
                    }
                    if (strpos($raw, 'drive.google.com') !== false) {
                        return __('☁️ Archivo en Google Drive', 'aura-suite');
                    }
                    return '📎 ' . basename($raw);

                case 'third_party_id':
                    if (isset($tp_map[$raw])) {
                        return $tp_map[$raw];
                    }
                    if ((int) $raw > 0) {
                        $found_name = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(commercial_name, full_name) FROM {$wpdb->prefix}aura_finance_third_parties WHERE id = %d", (int) $raw));
                        if ($found_name) {
                            return $found_name;
                        }
                    }
                    return $raw ? sprintf(__('Tercero #%d', 'aura-suite'), (int)$raw) : '—';

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
                    return $status_map[$raw] ?? $raw;

                default:
                    return $raw;
            }
        };

        // Formatear historial con avatares de los usuarios y etiquetas en español
        $formatted_history = array();
        foreach ($history as $entry) {
            $changed_by_user = get_userdata($entry['changed_by']);
            $user_avatar = $changed_by_user ? get_avatar_url($entry['changed_by'], array('size' => 48)) : '';
            $field_key = $entry['field_changed'];
            $formatted_history[] = array(
                'id'                  => $entry['id'],
                'field_changed'       => $field_key,
                'field_changed_label' => $field_labels_map[$field_key] ?? $field_key,
                'action_label'        => $action_labels_map[$field_key] ?? $field_key,
                'old_value'           => $entry['old_value'],
                'new_value'           => $entry['new_value'],
                'old_value_label'     => $format_history_value($field_key, $entry['old_value']),
                'new_value_label'     => $format_history_value($field_key, $entry['new_value']),
                'changed_at'          => $entry['changed_at'],
                'changed_by'          => $changed_by_user ? $changed_by_user->display_name : __('Usuario desconocido', 'aura-suite'),
                'changed_by_avatar'   => $user_avatar,
                'changed_by_id'       => (int) $entry['changed_by']
            );
        }
        
        // Información de Tercero / Empresa vinculada
        $third_party_data = null;
        if ( ! empty( $transaction['third_party_id'] ) ) {
            $tp_name = ! empty( $transaction['tp_commercial_name'] ) ? $transaction['tp_commercial_name'] : ( ! empty( $transaction['tp_full_name'] ) ? $transaction['tp_full_name'] : $transaction['recipient_payer'] );
            $tp_logo_id = (int) ( $transaction['tp_logo_id'] ?? 0 );
            $tp_logo_url = $tp_logo_id ? ( wp_get_attachment_image_url( $tp_logo_id, 'thumbnail' ) ?: wp_get_attachment_url( $tp_logo_id ) ?: '' ) : '';
            
            $ptype = $transaction['tp_party_type'] ?: 'company';
            $type_labels = array(
                'company'                 => __('Empresa / Negocio', 'aura-suite'),
                'store'                   => __('Tienda / Comercio', 'aura-suite'),
                'organization_foundation' => __('Fundación / ONG', 'aura-suite'),
                'person'                  => __('Persona Natural (Tercero)', 'aura-suite'),
                'religious'               => __('Entidad Religiosa', 'aura-suite'),
                'other'                   => __('Otra Entidad', 'aura-suite'),
            );
            $type_icons = array(
                'company'                 => 'dashicons-building',
                'store'                   => 'dashicons-cart',
                'organization_foundation' => 'dashicons-heart',
                'person'                  => 'dashicons-businessman',
                'religious'               => 'dashicons-location-alt',
                'other'                   => 'dashicons-category',
            );

            $accounting_roles = class_exists('Aura_Third_Parties') ? Aura_Third_Parties::get_accounting_roles() : array();
            $role_key = !empty($transaction['tp_accounting_role']) ? $transaction['tp_accounting_role'] : 'supplier';
            $role_label = $accounting_roles[$role_key]['label'] ?? ($type_labels[$ptype] ?? __('Proveedores / Comercios', 'aura-suite'));

            $third_party_data = array(
                'id'                    => (int) $transaction['third_party_id'],
                'name'                  => $tp_name,
                'full_name'             => $transaction['tp_full_name'] ?? '',
                'commercial_name'       => $transaction['tp_commercial_name'] ?? '',
                'party_type'            => $ptype,
                'type_label'            => $type_labels[$ptype] ?? $type_labels['company'],
                'accounting_role'       => $role_key,
                'accounting_role_label' => $role_label,
                'role_label'            => $role_label,
                'icon'                  => $accounting_roles[$role_key]['icon'] ?? ($type_icons[$ptype] ?? 'dashicons-building'),
                'tax_id_type'           => !empty($transaction['tp_tax_id_type']) ? $transaction['tp_tax_id_type'] : 'NIT',
                'document_id'           => $transaction['tp_document_id'] ?? '',
                'phone'                 => $transaction['tp_phone'] ?? '',
                'email'                 => $transaction['tp_email'] ?? '',
                'website'               => $transaction['tp_website'] ?? '',
                'address'               => $transaction['tp_address'] ?? '',
                'notes'                 => $transaction['tp_notes'] ?? '',
                'logo_id'               => $tp_logo_id,
                'logo_url'              => $tp_logo_url,
            );
        }
        
        // Fase 6, Item 6.1: obtener datos del usuario vinculado
        $related_user_data = null;
        $related_user_concept_label = '';
        $concept_map = array(
            'payment_to_user'       => __('Pago a usuario', 'aura-suite'),
            'charge_to_user'        => __('Cobro a usuario', 'aura-suite'),
            'salary'                => __('Pago de salario / nómina', 'aura-suite'),
            'scholarship'           => __('Beca asignada', 'aura-suite'),
            'loan_payment'          => __('Pago de préstamo', 'aura-suite'),
            'refund'                => __('Reembolso', 'aura-suite'),
            'expense_reimbursement' => __('Reembolso de gastos', 'aura-suite'),
            'unlinked'              => __('Desvinculado', 'aura-suite'),
            'none'                  => __('Ninguno', 'aura-suite'),
        );
        $raw_concept = ! empty( $transaction['related_user_concept'] ) ? $transaction['related_user_concept'] : '';
        if ( $raw_concept ) {
            $related_user_concept_label = $concept_map[$raw_concept] ?? ucfirst(str_replace('_', ' ', $raw_concept));
        }

        if ( ! empty( $transaction['related_user_id'] ) ) {
            $rel_user = get_userdata( $transaction['related_user_id'] );
            if ( $rel_user ) {
                $related_user_data = [
                    'id'            => (int) $transaction['related_user_id'],
                    'name'          => $rel_user->display_name,
                    'email'         => $rel_user->user_email,
                    'avatar_url'    => get_avatar_url( $transaction['related_user_id'], [ 'size' => 48 ] ),
                    'concept'       => $raw_concept,
                    'concept_label' => $related_user_concept_label,
                ];
            }
        }

        // Preparar URLs y metadatos de comprobantes (soporta 1 o múltiples, Google Drive y Local)
        $receipt_url = '';
        $receipt_exists = false;
        $receipt_preview_url = '';
        $receipt_thumbnail_url = '';
        $receipt_download_url = '';
        $receipt_is_drive = false;
        $receipt_is_img = false;
        $receipt_is_pdf = false;
        $receipts_list = array();

        if (!empty($transaction['receipt_file'])) {
            $raw_receipts = array();
            $decoded = json_decode($transaction['receipt_file'], true);
            if (is_array($decoded)) {
                $raw_receipts = $decoded;
            } else {
                $raw_receipts = array_map('trim', explode(',', $transaction['receipt_file']));
            }

            $upload_dir = wp_upload_dir();

            foreach ($raw_receipts as $item) {
                if (empty($item)) continue;
                $url = '';
                $name = '';
                if (is_array($item)) {
                    $url = $item['url'] ?? ($item['file_url'] ?? '');
                    $name = $item['name'] ?? ($item['filename'] ?? '');
                } else {
                    $url = $item;
                    $name = basename($item);
                }

                if (empty($url)) continue;

                $item_is_drive = (strpos($url, 'drive.google.com') !== false || strpos($url, 'googleusercontent.com') !== false);
                $file_id = $item_is_drive ? Aura_Drive_Manager::extract_file_id($url) : '';
                
                if ($item_is_drive && (empty($name) || strpos($name, 'view') !== false || strpos($name, '?') !== false)) {
                    $name = __('Documento en Google Drive', 'aura-suite');
                }

                $prev_url = $url;
                $thumb_url = $url;
                $down_url = $url;
                $is_img = false;
                $is_pdf = false;

                if ($item_is_drive) {
                    $prev_url = 'https://drive.google.com/file/d/' . $file_id . '/preview';
                    $thumb_url = 'https://drive.google.com/thumbnail?id=' . $file_id . '&sz=w1600';
                    $down_url = 'https://drive.google.com/uc?export=download&id=' . $file_id;
                    $is_pdf = (bool)preg_match('/\.pdf($|\?)/i', $name) || (bool)preg_match('/\.pdf($|\?)/i', $url);
                    $is_img = (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)($|\?)/i', $name) || (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)($|\?)/i', $url);
                } else {
                    if (strpos($url, 'http') === 0) {
                        $is_img = (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $url);
                        $is_pdf = (bool)preg_match('/\.pdf$/i', $url);
                    } else {
                        $receipt_path = $upload_dir['basedir'] . '/aura-finance/receipts/' . basename($url);
                        if (file_exists($receipt_path)) {
                            $url = $upload_dir['baseurl'] . '/aura-finance/receipts/' . basename($url);
                            $prev_url = $url;
                            $thumb_url = $url;
                            $down_url = $url;
                            $is_img = (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $receipt_path);
                            $is_pdf = (bool)preg_match('/\.pdf$/i', $receipt_path);
                        }
                    }
                }

                $receipts_list[] = array(
                    'url'           => $url,
                    'preview_url'   => $prev_url,
                    'thumbnail_url' => $thumb_url,
                    'download_url'  => $down_url,
                    'is_drive'      => $item_is_drive,
                    'file_id'       => $file_id,
                    'is_img'        => $is_img,
                    'is_pdf'        => $is_pdf,
                    'filename'      => $name
                );
            }

            if (!empty($receipts_list)) {
                $first = $receipts_list[0];
                $receipt_url = $first['url'];
                $receipt_preview_url = $first['preview_url'];
                $receipt_thumbnail_url = $first['thumbnail_url'];
                $receipt_download_url = $first['download_url'];
                $receipt_is_drive = $first['is_drive'];
                $receipt_is_img = $first['is_img'];
                $receipt_is_pdf = $first['is_pdf'];
                $receipt_exists = true;
            }
        }
        
        $updated_by_name = '—';
        if (!empty($formatted_history)) {
            $updated_by_name = $formatted_history[0]['changed_by'];
        }

        $area_logo_id = (int) ($transaction['area_logo_id'] ?? 0);
        $area_logo_url = $area_logo_id ? (wp_get_attachment_image_url($area_logo_id, 'thumbnail') ?: wp_get_attachment_url($area_logo_id) ?: '') : '';

        // Preparar datos de respuesta
        $response_data = array(
            'id' => $transaction['id'],
            'transaction_type' => $transaction['transaction_type'],
            'category' => array(
                'id'          => $transaction['category_id'],
                'name'        => $transaction['category_name'],
                'color'       => $transaction['category_color'],
                'icon'        => $transaction['category_icon'],
                'slug'        => $transaction['category_slug'] ?? '',
                'type'        => $transaction['category_type'] ?? '',
                'is_capex'    => (int) ($transaction['category_is_capex'] ?? 0),
                'description' => $transaction['category_description'] ?? '',
                'parent_id'   => (int) ($transaction['category_parent_id'] ?? 0),
                'parent_name' => $transaction['parent_category_name'] ?? '',
                'parent_slug' => $transaction['parent_category_slug'] ?? '',
                'parent_icon' => $transaction['parent_category_icon'] ?? '',
                'parent_color'=> $transaction['parent_category_color'] ?? ''
            ),
            'expense_category' => array(
                'id'   => $transaction['expense_category_id'],
                'name' => $transaction['expense_category_name']
            ),
            'area' => array(
                'id'          => $transaction['area_id'],
                'name'        => !empty($transaction['area_name']) ? $transaction['area_name'] : __('General (sin área)', 'aura-suite'),
                'slug'        => $transaction['area_slug'] ?? '',
                'type'        => $transaction['area_type'] ?? '',
                'description' => $transaction['area_description'] ?? '',
                'color'       => $transaction['area_color'] ?? '',
                'icon'        => $transaction['area_icon'] ?? '',
                'logo_id'     => $area_logo_id,
                'logo_url'    => $area_logo_url
            ),
            'accounts' => array(
                'source' => array(
                    'id'          => $transaction['source_account_id'],
                    'name'        => $transaction['source_account_name'],
                    'currency'    => $transaction['source_account_currency'],
                    'type'        => $transaction['source_account_type'] ?? '',
                    'institution' => $transaction['source_account_institution'] ?? ''
                ),
                'destination' => array(
                    'id'          => $transaction['destination_account_id'],
                    'name'        => $transaction['destination_account_name'],
                    'currency'    => $transaction['destination_account_currency'],
                    'type'        => $transaction['destination_account_type'] ?? '',
                    'institution' => $transaction['destination_account_institution'] ?? ''
                )
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
            'receipt_preview_url' => $receipt_preview_url,
            'receipt_thumbnail_url' => $receipt_thumbnail_url,
            'receipt_download_url' => $receipt_download_url,
            'receipt_is_drive' => $receipt_is_drive,
            'receipt_is_img' => $receipt_is_img,
            'receipt_is_pdf' => $receipt_is_pdf,
            'receipts_list' => $receipts_list,
            'receipt_exists' => $receipt_exists,
            'rejection_reason' => $transaction['rejection_reason'],
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at'],
            'approved_at' => $transaction['approved_at'],
            'creator' => $creator_data,
            'approver' => $approver_data,
            'updated_by' => $updated_by_name,
            'created_by' => $transaction['created_by'],
            'approved_by' => $transaction['approved_by'],
            'history' => $formatted_history,
            'is_creator' => $is_creator,
            // Integración de módulos
            'related_module'  => $transaction['related_module'] ?? '',
            'related_item_id' => (int) ($transaction['related_item_id'] ?? 0),
            'related_action'  => $transaction['related_action'] ?? '',
            'import_batch_id' => $transaction['import_batch_id'] ?? '',
            // Fase 6, Item 6.1: usuario vinculado
            'related_user_id'            => ! empty( $transaction['related_user_id'] ) ? (int) $transaction['related_user_id'] : null,
            'related_user_concept'       => $related_user_concept_label ?: ($transaction['related_user_concept'] ?? null),
            'related_user_concept_raw'   => $raw_concept,
            'related_user_concept_label' => $related_user_concept_label,
            'related_user'               => $related_user_data,
            // Tercero / Empresa vinculada
            'third_party_id'       => ! empty( $transaction['third_party_id'] ) ? (int) $transaction['third_party_id'] : null,
            'third_party'          => $third_party_data,
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
