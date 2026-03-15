<?php
/**
 * Gestión de Mantenimientos de Equipos — FASE 3
 *
 * CRUD completo: registro de mantenimientos preventivos/correctivos,
 * integración automática con Finanzas, actualización del próximo
 * mantenimiento en el equipo, y endpoints AJAX para el frontend.
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Maintenance {

    const NONCE = 'aura_inventory_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'get_list'      => 'ajax_get_list',
            'save'          => 'ajax_save',
            'delete'        => 'ajax_delete',
            'get_detail'    => 'ajax_get_detail',
            'get_form_data' => 'ajax_get_form_data',
            'get_stats'     => 'ajax_get_stats',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_inventory_maintenance_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER — páginas admin
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        if ( ! current_user_can( 'aura_inventory_maintenance_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/maintenance-list.php';
    }

    public static function render_form(): void {
        $can = current_user_can( 'aura_inventory_maintenance_create' ) ||
               current_user_can( 'aura_inventory_maintenance_edit'   ) ||
               current_user_can( 'manage_options' );
        if ( ! $can ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/maintenance-form.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado con filtros
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_maintenance_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        // ── Parámetros ─────────────────────────────────────────
        $page         = max( 1, intval( $_POST['page']      ?? 1  ) );
        $per_page     = min( 100, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $offset       = ( $page - 1 ) * $per_page;
        $search       = sanitize_text_field( $_POST['search']       ?? '' );
        $equipment_id = intval( $_POST['equipment_id'] ?? 0 );
        $type         = sanitize_text_field( $_POST['type']         ?? '' );
        $performed_by = sanitize_text_field( $_POST['performed_by'] ?? '' );
        $post_status  = sanitize_text_field( $_POST['post_status']  ?? '' );
        $date_from    = self::sanitize_date( $_POST['date_from'] ?? '' );
        $date_to      = self::sanitize_date( $_POST['date_to']   ?? '' );
        $sort_by      = in_array( $_POST['sort_by'] ?? '', [ 'maintenance_date', 'total_cost', 'type', 'equipment_name' ] )
                            ? sanitize_key( $_POST['sort_by'] )
                            : 'maintenance_date';
        $sort_dir     = strtoupper( sanitize_key( $_POST['sort_dir'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

        // ── Construcción dinámica de WHERE ─────────────────────
        $where   = [ "1=1" ];
        $params  = [];

        if ( $equipment_id > 0 ) {
            $where[] = "m.equipment_id = %d";
            $params[] = $equipment_id;
        }
        if ( $search !== '' ) {
            $where[] = "(e.name LIKE %s OR e.brand LIKE %s OR m.description LIKE %s OR m.workshop_name LIKE %s)";
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $params = array_merge( $params, [ $like, $like, $like, $like ] );
        }
        if ( $type !== '' ) {
            $where[] = "m.type = %s";
            $params[] = $type;
        }
        if ( $performed_by !== '' ) {
            $where[] = "m.performed_by = %s";
            $params[] = $performed_by;
        }
        if ( $post_status !== '' ) {
            $where[] = "m.post_status = %s";
            $params[] = $post_status;
        }
        if ( $date_from ) {
            $where[] = "m.maintenance_date >= %s";
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[] = "m.maintenance_date <= %s";
            $params[] = $date_to;
        }

        $where_sql = implode( ' AND ', $where );

        // Sort alias map
        $sort_col = $sort_by === 'equipment_name' ? 'e.name' : "m.{$sort_by}";

        // ── Consultas ─────────────────────────────────────────
        $base_sql = "FROM {$t_maint} m
                     LEFT JOIN {$t_equip} e ON e.id = m.equipment_id
                     WHERE {$where_sql}";

        if ( ! empty( $params ) ) {
            $total = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) {$base_sql}", ...$params )
            );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT m.*,
                            e.name          AS equipment_name,
                            e.brand         AS equipment_brand,
                            e.internal_code AS equipment_code,
                            e.status        AS equipment_status,
                            e.photo         AS equipment_photo_id
                     {$base_sql}
                     ORDER BY {$sort_col} {$sort_dir}
                     LIMIT %d OFFSET %d",
                    ...array_merge( $params, [ $per_page, $offset ] )
                )
            );
        } else {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) {$base_sql}" );
            $rows  = $wpdb->get_results(
                "SELECT m.*,
                        e.name          AS equipment_name,
                        e.brand         AS equipment_brand,
                        e.internal_code AS equipment_code,
                        e.status        AS equipment_status,
                        e.photo         AS equipment_photo_id
                 {$base_sql}
                 ORDER BY {$sort_col} {$sort_dir}
                 LIMIT {$per_page} OFFSET {$offset}"
            );
        }

        // ── Enriquecer filas ────────────────────────────────────
        $can_edit   = current_user_can( 'aura_inventory_maintenance_edit'   ) || current_user_can( 'manage_options' );
        $can_delete = current_user_can( 'aura_inventory_maintenance_delete' ) || current_user_can( 'manage_options' );

        $items = [];
        foreach ( $rows as $row ) {
            // Nombre del técnico interno
            $tech_name = '';
            if ( $row->internal_technician ) {
                $user = get_userdata( (int) $row->internal_technician );
                $tech_name = $user ? $user->display_name : '';
            }

            $photo_urls = aura_get_equipment_photo_urls( $row->equipment_photo_id ?? '' );
            $items[] = [
                'id'               => (int)    $row->id,
                'equipment_id'     => (int)    $row->equipment_id,
                'equipment_name'   => $row->equipment_name ?? '',
                'equipment_brand'  => $row->equipment_brand ?? '',
                'equipment_code'   => $row->equipment_code ?? '',
                'photo_thumb_url'  => $photo_urls['thumb'],
                'type'             => $row->type,
                'type_label'       => self::get_type_label( $row->type ),
                'maintenance_date' => $row->maintenance_date,
                'equipment_hours'  => $row->equipment_hours !== null ? (float) $row->equipment_hours : null,
                'performed_by'     => $row->performed_by,
                'workshop_name'    => $row->workshop_name ?? '',
                'tech_name'        => $tech_name,
                'parts_cost'       => (float) $row->parts_cost,
                'labor_cost'       => (float) $row->labor_cost,
                'total_cost'       => (float) $row->total_cost,
                'post_status'      => $row->post_status,
                'post_status_label'=> self::get_post_status_label( $row->post_status ),
                'next_action_date' => $row->next_action_date,
                'has_finance'      => (bool) $row->finance_transaction_id,
                'can_edit'         => $can_edit,
                'can_delete'       => $can_delete,
            ];
        }

        wp_send_json_success( [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar (crear o editar)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $can = current_user_can( 'aura_inventory_maintenance_create' ) ||
               current_user_can( 'aura_inventory_maintenance_edit'   ) ||
               current_user_can( 'manage_options' );
        if ( ! $can ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $maint_id    = intval( $_POST['id']           ?? 0 );
        $equipment_id = intval( $_POST['equipment_id'] ?? 0 );

        // ── Validaciones ────────────────────────────────────────
        if ( $equipment_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Debes seleccionar un equipo.', 'aura-suite' ) ] );
        }

        $equipment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_equip} WHERE id = %d AND deleted_at IS NULL",
            $equipment_id
        ) );
        if ( ! $equipment ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no encontrado.', 'aura-suite' ) ] );
        }

        $maint_date = self::sanitize_date( $_POST['maintenance_date'] ?? '' );
        if ( ! $maint_date ) {
            wp_send_json_error( [ 'message' => __( 'La fecha de mantenimiento es obligatoria.', 'aura-suite' ) ] );
        }

        $valid_types = [ 'preventive', 'corrective', 'oil_change', 'cleaning', 'inspection', 'major_repair' ];
        $type = sanitize_key( $_POST['type'] ?? 'preventive' );
        if ( ! in_array( $type, $valid_types, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Tipo de mantenimiento inválido.', 'aura-suite' ) ] );
        }

        $performed_by = sanitize_key( $_POST['performed_by'] ?? 'internal' );
        if ( ! in_array( $performed_by, [ 'internal', 'external' ], true ) ) {
            $performed_by = 'internal';
        }

        $valid_post_status = [ 'operational', 'needs_followup', 'out_of_service' ];
        $post_status = sanitize_key( $_POST['post_status'] ?? 'operational' );
        if ( ! in_array( $post_status, $valid_post_status, true ) ) {
            $post_status = 'operational';
        }

        $parts_cost  = max( 0, floatval( str_replace( ',', '.', $_POST['parts_cost'] ?? '0' ) ) );
        $labor_cost  = max( 0, floatval( str_replace( ',', '.', $_POST['labor_cost'] ?? '0' ) ) );
        $total_cost  = round( $parts_cost + $labor_cost, 2 );

        // ── Datos a persistir ────────────────────────────────────
        $data = [
            'equipment_id'        => $equipment_id,
            'type'                => $type,
            'maintenance_date'    => $maint_date,
            'equipment_hours'     => is_numeric( $_POST['equipment_hours'] ?? '' ) ? floatval( $_POST['equipment_hours'] ) : null,
            'description'         => sanitize_textarea_field( $_POST['description']          ?? '' ),
            'parts_replaced'      => sanitize_textarea_field( $_POST['parts_replaced']       ?? '' ),
            'parts_cost'          => $parts_cost,
            'labor_cost'          => $labor_cost,
            'total_cost'          => $total_cost,
            'performed_by'        => $performed_by,
            'workshop_name'       => sanitize_text_field( $_POST['workshop_name']      ?? '' ),
            'internal_technician' => intval( $_POST['internal_technician'] ?? 0 ) ?: null,
            'workshop_invoice'    => esc_url_raw(  $_POST['workshop_invoice']   ?? '' ),
            'invoice_number'      => sanitize_text_field( $_POST['invoice_number']     ?? '' ),
            'post_status'         => $post_status,
            'next_action_date'    => self::sanitize_date( $_POST['next_action_date'] ?? '' ) ?: null,
            'observations'        => sanitize_textarea_field( $_POST['observations']   ?? '' ),
            'registered_by'       => get_current_user_id(),
        ];

        // ── Insertar o actualizar ─────────────────────────────
        $is_new = $maint_id <= 0;

        if ( $is_new ) {
            $ok = $wpdb->insert( $t_maint, $data );
            if ( $ok === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al guardar el mantenimiento.', 'aura-suite' ) ] );
            }
            $maint_id = $wpdb->insert_id;
        } else {
            // Verificar que existe y pertenece a este equipo
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, finance_transaction_id FROM {$t_maint} WHERE id = %d",
                $maint_id
            ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Registro de mantenimiento no encontrado.', 'aura-suite' ) ] );
            }
            $wpdb->update( $t_maint, $data, [ 'id' => $maint_id ] );
        }

        // ── Integración Finanzas ─────────────────────────────
        $finance_id = null;
        $create_finance = ( $total_cost > 0 ) && isset( $_POST['create_finance_transaction'] )
            && intval( $_POST['create_finance_transaction'] ) === 1;

        if ( $create_finance && $is_new ) {
            $finance_category_id = intval( $_POST['finance_category_id'] ?? 0 );
            // Si no se envió categoría, usar la configurada por defecto en ajustes
            if ( $finance_category_id <= 0 ) {
                $inv_s = Aura_Inventory_Categories::get_settings();
                $finance_category_id = intval( $inv_s['finance_category_id'] ?? 0 );
            }
            $finance_payment     = sanitize_text_field( $_POST['finance_payment_method'] ?? 'Efectivo' );
            $finance_invoice     = sanitize_text_field( $_POST['invoice_number'] ?? '' );

            $finance_desc = sprintf(
                __( '[Mantenimiento] %s — %s (%s)', 'aura-suite' ),
                $equipment->name,
                self::get_type_label( $type ),
                date_i18n( get_option( 'date_format' ), strtotime( $maint_date ) )
            );
            if ( $performed_by === 'external' && ! empty( $data['workshop_name'] ) ) {
                $finance_desc .= ' — ' . $data['workshop_name'];
            }

            // Estado: usar el configurado en ajustes (approved o pending)
            $inv_settings_for_status = Aura_Inventory_Categories::get_settings();
            $finance_status = in_array( $inv_settings_for_status['auto_approve_transactions'] ?? '', [ 'approved', 'pending' ] )
                ? $inv_settings_for_status['auto_approve_transactions']
                : 'approved';

            if ( $finance_category_id > 0 && class_exists( 'Aura_Financial_Transactions' ) ) {
                $finance_id = Aura_Financial_Transactions::create_related_transaction( [
                    'transaction_type' => 'expense',
                    'category_id'      => $finance_category_id,
                    'amount'           => $total_cost,
                    'description'      => $finance_desc,
                    'transaction_date' => $maint_date,
                    'related_module'   => 'inventory',
                    'related_item_id'  => $equipment_id,
                    'related_action'   => 'maintenance',
                    'payment_method'   => $finance_payment,
                    'reference_number' => $finance_invoice,
                    'recipient_payer'  => $performed_by === 'external' ? ( $data['workshop_name'] ?: '' ) : '',
                    'status'           => $finance_status,
                ] );

                if ( $finance_id ) {
                    $wpdb->update( $t_maint,
                        [ 'create_finance_transaction' => 1, 'finance_transaction_id' => $finance_id ],
                        [ 'id' => $maint_id ]
                    );
                }
            }
        }

        // ── Actualizar equipo: horas + estado + fecha base de próximo mantenimiento ─
        $update_eq = [];

        // Registrar horas de uso acumuladas si se proporcionaron
        if ( isset( $data['equipment_hours'] ) && $data['equipment_hours'] > 0 ) {
            $update_eq['current_hours'] = $data['equipment_hours'];
        }

        // Si el estado post-mantenimiento indica que hay que sacar de servicio
        if ( $post_status === 'out_of_service' ) {
            $update_eq['status'] = 'repair';
        } elseif ( $post_status === 'operational' && $equipment->status === 'maintenance' ) {
            $update_eq['status'] = 'available';
        }

        // ── CRÍTICO: actualizar last_maintenance_date y last_maintenance_hours ──
        // Sin esto, recalculate_next_maintenance() siempre usaría la acquisition_date
        // como base y el próximo mantenimiento nunca avanzaría en el calendario.
        if ( $is_new ) {
            $update_eq['last_maintenance_date'] = $maint_date;
            if ( ! empty( $data['equipment_hours'] ) && $data['equipment_hours'] > 0 ) {
                $update_eq['last_maintenance_hours'] = $data['equipment_hours'];
            }
        }

        if ( ! empty( $update_eq ) ) {
            $wpdb->update( $t_equip, $update_eq, [ 'id' => $equipment_id ] );
        }

        // Recalcular próximo mantenimiento (usa last_maintenance_date justo actualizado)
        if ( $is_new && $equipment->requires_maintenance ) {
            Aura_Inventory_Equipment::recalculate_next_maintenance( $equipment_id );
        }

        wp_send_json_success( [
            'id'         => $maint_id,
            'finance_id' => $finance_id,
            'total_cost' => $total_cost,
            'message'    => $is_new
                ? __( 'Mantenimiento registrado correctamente.', 'aura-suite' )
                : __( 'Mantenimiento actualizado correctamente.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Eliminar
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_maintenance_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        $maint = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, equipment_id, finance_transaction_id FROM {$t_maint} WHERE id = %d",
            $id
        ) );
        if ( ! $maint ) {
            wp_send_json_error( [ 'message' => __( 'Registro no encontrado.', 'aura-suite' ) ] );
        }

        // Advertencia si tiene transacción financiera vinculada
        if ( $maint->finance_transaction_id ) {
            // Permitir borrar pero informar
            $wpdb->delete( $t_maint, [ 'id' => $id ] );
            wp_send_json_success( [
                'message' => __( 'Mantenimiento eliminado. Nota: tiene una transacción financiera vinculada que debe revisarse manualmente.', 'aura-suite' ),
                'had_finance' => true,
            ] );
            return;
        }

        $wpdb->delete( $t_maint, [ 'id' => $id ] );

        // Recalcular próximo mantenimiento del equipo
        Aura_Inventory_Equipment::recalculate_next_maintenance( (int) $maint->equipment_id );

        wp_send_json_success( [ 'message' => __( 'Mantenimiento eliminado.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Detalle completo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_maintenance_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*,
                    e.name                AS equipment_name,
                    e.brand               AS equipment_brand,
                    e.model               AS equipment_model,
                    e.internal_code       AS equipment_code,
                    e.status              AS equipment_current_status,
                    e.next_maintenance_date,
                    e.photo               AS equipment_photo_id
             FROM {$t_maint} m
             LEFT JOIN {$t_equip} e ON e.id = m.equipment_id
             WHERE m.id = %d",
            $id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Registro no encontrado.', 'aura-suite' ) ] );
        }

        // Nombres de usuarios
        $tech_name = '';
        if ( $row->internal_technician ) {
            $u = get_userdata( (int) $row->internal_technician );
            $tech_name = $u ? $u->display_name : '';
        }
        $reg_name = '';
        if ( $row->registered_by ) {
            $u = get_userdata( (int) $row->registered_by );
            $reg_name = $u ? $u->display_name : '';
        }

        wp_send_json_success( [
            'maintenance' => array_merge( (array) $row, [
                'type_label'          => self::get_type_label( $row->type ),
                'post_status_label'   => self::get_post_status_label( $row->post_status ),
                'tech_name'           => $tech_name,
                'registered_by_name'  => $reg_name,
                'total_cost'          => (float) $row->total_cost,
                'parts_cost'          => (float) $row->parts_cost,
                'labor_cost'          => (float) $row->labor_cost,
                // Foto del equipo en ambos tamaños
                'equipment_photo'     => aura_get_equipment_photo_urls( $row->equipment_photo_id ?? '' )['full'],
                'equipment_photo_thumb' => aura_get_equipment_photo_urls( $row->equipment_photo_id ?? '' )['thumb'],
            ] ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Datos para formulario de edición
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_form_data(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_maintenance_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aura_inventory_maintenance WHERE id = %d",
            $id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Registro no encontrado.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'maintenance' => $row ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Estadísticas para el dashboard
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_stats(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_maintenance_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';

        $year   = intval( $_POST['year']  ?? date( 'Y' ) );
        $month  = intval( $_POST['month'] ?? 0 );             // 0 = todos los meses del año

        $date_where  = $month > 0
            ? $wpdb->prepare( "AND maintenance_date BETWEEN %s AND %s",
                  sprintf( '%04d-%02d-01', $year, $month ),
                  sprintf( '%04d-%02d-%02d', $year, $month, cal_days_in_month( CAL_GREGORIAN, $month, $year ) )
              )
            : $wpdb->prepare( "AND YEAR(maintenance_date) = %d", $year );

        $total_count    = (int)   $wpdb->get_var( "SELECT COUNT(*)         FROM {$t_maint} WHERE 1=1 {$date_where}" );
        $total_cost     = (float) $wpdb->get_var( "SELECT SUM(total_cost)  FROM {$t_maint} WHERE 1=1 {$date_where}" );
        $by_type        = $wpdb->get_results( "SELECT type, COUNT(*) AS qty, SUM(total_cost) AS cost FROM {$t_maint} WHERE 1=1 {$date_where} GROUP BY type ORDER BY qty DESC" );
        $by_performed   = $wpdb->get_results( "SELECT performed_by, COUNT(*) AS qty, SUM(total_cost) AS cost FROM {$t_maint} WHERE 1=1 {$date_where} GROUP BY performed_by" );
        $top_equipment  = $wpdb->get_results(
            "SELECT m.equipment_id, e.name, COUNT(m.id) AS qty, SUM(m.total_cost) AS cost
             FROM {$t_maint} m
             LEFT JOIN {$wpdb->prefix}aura_inventory_equipment e ON e.id = m.equipment_id
             WHERE 1=1 {$date_where}
             GROUP BY m.equipment_id ORDER BY cost DESC LIMIT 5"
        );

        $by_type_out = [];
        foreach ( $by_type as $r ) {
            $by_type_out[] = [
                'type'  => $r->type,
                'label' => self::get_type_label( $r->type ),
                'qty'   => (int)   $r->qty,
                'cost'  => (float) $r->cost,
            ];
        }

        wp_send_json_success( [
            'total_count'   => $total_count,
            'total_cost'    => $total_cost,
            'by_type'       => $by_type_out,
            'by_performed'  => $by_performed,
            'top_equipment' => $top_equipment,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ─────────────────────────────────────────────────────────────

    public static function get_type_label( string $type ): string {
        $labels = [
            'preventive'   => __( 'Preventivo',         'aura-suite' ),
            'corrective'   => __( 'Correctivo',         'aura-suite' ),
            'oil_change'   => __( 'Cambio de aceite',   'aura-suite' ),
            'cleaning'     => __( 'Limpieza',           'aura-suite' ),
            'inspection'   => __( 'Inspección',         'aura-suite' ),
            'major_repair' => __( 'Reparación mayor',  'aura-suite' ),
        ];
        return $labels[ $type ] ?? ucfirst( $type );
    }

    public static function get_post_status_label( string $status ): string {
        $labels = [
            'operational'    => __( 'Operacional',         'aura-suite' ),
            'needs_followup' => __( 'Requiere seguimiento','aura-suite' ),
            'out_of_service' => __( 'Fuera de servicio',   'aura-suite' ),
        ];
        return $labels[ $status ] ?? ucfirst( $status );
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────────

    private static function sanitize_date( string $raw ): string {
        $raw = sanitize_text_field( $raw );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            return $raw;
        }
        return '';
    }
}
