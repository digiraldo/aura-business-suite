<?php
/**
 * Gestión de Equipos e Inventario — FASE 2
 *
 * CRUD completo de equipos: listado paginado con filtros, formulario de
 * alta/edición, soft-delete, recálculo automático de próximo mantenimiento
 * y endpoints AJAX para el frontend.
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Equipment {

    const NONCE = 'aura_inventory_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'get_list'     => 'ajax_get_list',
            'save'         => 'ajax_save',
            'delete'       => 'ajax_delete',
            'get_detail'   => 'ajax_get_detail',
            'update_status'=> 'ajax_update_status',
            'search'       => 'ajax_search',
            'get_form_data'=> 'ajax_get_form_data',
            'crop_photo'   => 'ajax_crop_equipment_photo',
            'get_photo'    => 'ajax_get_equipment_photo',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_inventory_equipment_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER — páginas admin
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/equipment-list.php';
    }

    public static function render_form(): void {
        if ( ! current_user_can( 'aura_inventory_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/equipment-form.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado con filtros
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_inventory_equipment';

        // Parámetros
        $page     = max( 1, intval( $_POST['page']     ?? 1 ) );
        $per_page = min( 100, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $_POST['search']   ?? '' );
        $category = sanitize_text_field( $_POST['category'] ?? '' );
        $status   = sanitize_text_field( $_POST['status']   ?? '' );
        $area_id  = intval( $_POST['area_id'] ?? 0 );
        $requires_maint = isset( $_POST['requires_maintenance'] ) ? intval( $_POST['requires_maintenance'] ) : -1;
        $sort_by  = in_array( $_POST['sort_by'] ?? '', [ 'name', 'status', 'next_maintenance_date', 'created_at', 'cost' ] )
                    ? $_POST['sort_by'] : 'name';
        $sort_dir = ( strtoupper( $_POST['sort_dir'] ?? 'ASC' ) === 'DESC' ) ? 'DESC' : 'ASC';

        // Condiciones
        $where   = [ 'deleted_at IS NULL' ];
        $params  = [];

        if ( $search ) {
            $where[] = '(name LIKE %s OR brand LIKE %s OR serial_number LIKE %s OR internal_code LIKE %s)';
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            array_push( $params, $like, $like, $like, $like );
        }
        if ( $category ) {
            $where[] = 'category = %s';
            $params[] = $category;
        }
        $status_allowed = [ 'available', 'in_use', 'maintenance', 'repair', 'retired' ];
        if ( $status && in_array( $status, $status_allowed ) ) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ( $area_id > 0 ) {
            $where[] = 'area_id = %d';
            $params[] = $area_id;
        }
        if ( $requires_maint >= 0 ) {
            $where[] = 'requires_maintenance = %d';
            $params[] = $requires_maint;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        // Total
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $total     = $params
            ? intval( $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) )
            : intval( $wpdb->get_var( $count_sql ) );

        // Datos
        $data_sql  = "SELECT * FROM {$table} {$where_sql} ORDER BY {$sort_by} {$sort_dir} LIMIT %d OFFSET %d";
        $data_params = array_merge( $params, [ $per_page, $offset ] );
        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ) );

        // Enriquecer con datos de usuario responsable
        foreach ( $rows as &$row ) {
            $row->status_label            = self::get_status_label( $row->status );
            $row->maintenance_status      = self::get_maintenance_status( $row );
            $row->responsible_name        = $row->responsible_user_id
                ? ( get_user_by( 'id', $row->responsible_user_id )->display_name ?? '—' )
                : '—';
            $row->can_edit   = current_user_can( 'aura_inventory_edit' ) || current_user_can( 'manage_options' );
            $row->can_delete = current_user_can( 'aura_inventory_delete' ) || current_user_can( 'manage_options' );
            // URLs de imagen en ambos tamaños
            $photo_urls           = aura_get_equipment_photo_urls( $row->photo ?? '' );
            $row->photo_full_url  = $photo_urls['full'];
            $row->photo_thumb_url = $photo_urls['thumb'];
        }
        unset( $row );

        wp_send_json_success( [
            'items'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar equipo (crear o actualizar)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $id = intval( $_POST['id'] ?? 0 );
        $is_new = $id === 0;

        // Permiso
        if ( $is_new && ! current_user_can( 'aura_inventory_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para crear equipos.', 'aura-suite' ) ] );
        }
        if ( ! $is_new && ! current_user_can( 'aura_inventory_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para editar equipos.', 'aura-suite' ) ] );
        }

        // Validar campo obligatorio
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre del equipo es obligatorio.', 'aura-suite' ) ] );
        }

        // Sanitizar todos los campos
        $status_allowed = [ 'available', 'in_use', 'maintenance', 'repair', 'retired' ];
        $interval_allowed = [ 'time', 'hours', 'both', 'none' ];

        $data = [
            'name'                 => $name,
            'brand'                => sanitize_text_field( $_POST['brand']         ?? '' ) ?: null,
            'model'                => sanitize_text_field( $_POST['model']         ?? '' ) ?: null,
            'serial_number'        => sanitize_text_field( $_POST['serial_number'] ?? '' ) ?: null,
            'internal_code'        => sanitize_text_field( $_POST['internal_code'] ?? '' ) ?: null,
            'category'             => sanitize_text_field( $_POST['category']      ?? '' ) ?: null,
            'description'          => sanitize_textarea_field( $_POST['description'] ?? '' ) ?: null,
            'status'               => in_array( $_POST['status'] ?? '', $status_allowed ) ? $_POST['status'] : 'available',
            'location'             => sanitize_text_field( $_POST['location']      ?? '' ) ?: null,
            'acquisition_date'     => self::sanitize_date( $_POST['acquisition_date'] ?? '' ),
            'cost'                 => floatval( $_POST['cost']             ?? 0 ),
            'estimated_value'      => floatval( $_POST['estimated_value']  ?? 0 ),
            'supplier'             => sanitize_text_field( $_POST['supplier']      ?? '' ) ?: null,
            'warranty_date'        => self::sanitize_date( $_POST['warranty_date'] ?? '' ),
            'requires_maintenance' => isset( $_POST['requires_maintenance'] ) ? 1 : 0,
            'interval_type'        => in_array( $_POST['interval_type'] ?? '', $interval_allowed ) ? $_POST['interval_type'] : 'time',
            'interval_months'      => intval( $_POST['interval_months'] ?? 0 ) ?: null,
            'interval_hours'       => intval( $_POST['interval_hours']  ?? 0 ) ?: null,
            'alert_days_before'    => max( 1, intval( $_POST['alert_days_before'] ?? 7 ) ),
            'oil_type'             => sanitize_text_field( $_POST['oil_type']           ?? '' ) ?: null,
            'oil_capacity'         => floatval( $_POST['oil_capacity'] ?? 0 ) ?: null,
            'fuel_type'            => sanitize_text_field( $_POST['fuel_type']           ?? '' ) ?: null,
            'voltage'              => intval( $_POST['voltage']          ?? 0 ) ?: null,
            'hydraulic_pressure'   => sanitize_text_field( $_POST['hydraulic_pressure'] ?? '' ) ?: null,
            'responsible_user_id'  => intval( $_POST['responsible_user_id'] ?? 0 ) ?: null,
            'area_id'              => intval( $_POST['area_id']              ?? 0 ) ?: null,
            'photo'                => sanitize_text_field( $_POST['photo']              ?? '' ) ?: null,
            'accessories'          => sanitize_textarea_field( $_POST['accessories'] ?? '' ) ?: null,
            'parent_equipment_id'  => intval( $_POST['parent_equipment_id'] ?? 0 ) ?: null,
        ];

        global $wpdb;
        $table = $wpdb->prefix . 'aura_inventory_equipment';

        if ( $is_new ) {
            $data['created_by'] = get_current_user_id();
            $result = $wpdb->insert( $table, $data );

            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al guardar el equipo.', 'aura-suite' ) ] );
            }

            $id = $wpdb->insert_id;

            // Si tiene mantenimiento configurado, calcular próxima fecha
            // (recalculate usa acquisition_date o hoy como fallback, no requiere que esté llena)
            if ( $data['requires_maintenance'] ) {
                self::recalculate_next_maintenance( $id );
            }

            wp_send_json_success( [
                'id'      => $id,
                'message' => __( 'Equipo registrado correctamente.', 'aura-suite' ),
            ] );

        } else {
            // Verificar que el equipo existe y no está eliminado
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
            ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Equipo no encontrado.', 'aura-suite' ) ] );
            }

            $result = $wpdb->update( $table, $data, [ 'id' => $id ] );

            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el equipo.', 'aura-suite' ) ] );
            }

            // Recalcular si cambió la configuración de mantenimiento
            self::recalculate_next_maintenance( $id );

            wp_send_json_success( [
                'id'      => $id,
                'message' => __( 'Equipo actualizado correctamente.', 'aura-suite' ),
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Soft delete
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_inventory_equipment';

        // Verificar que no tiene préstamo activo
        $loans_table = $wpdb->prefix . 'aura_inventory_loans';
        $active_loan = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$loans_table} WHERE equipment_id = %d AND actual_return_date IS NULL",
            $id
        ) );

        if ( $active_loan > 0 ) {
            wp_send_json_error( [ 'message' => __( 'No se puede eliminar un equipo con préstamo activo.', 'aura-suite' ) ] );
        }

        $result = $wpdb->update( $table, [ 'deleted_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar el equipo.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Equipo eliminado correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Detalle completo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';

        $equipment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_equip} WHERE id = %d AND deleted_at IS NULL", $id
        ) );

        if ( ! $equipment ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no encontrado.', 'aura-suite' ) ] );
        }

        // Historial de mantenimientos (últimos 20)
        $maintenance_history = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t_maint} WHERE equipment_id = %d ORDER BY maintenance_date DESC LIMIT 20",
            $id
        ) );

        foreach ( $maintenance_history as &$m ) {
            $m->type_label       = self::get_maintenance_type_label( $m->type );
            $m->technician_name  = $m->internal_technician
                ? ( get_user_by( 'id', $m->internal_technician )->display_name ?? '—' )
                : '—';
        }
        unset( $m );

        // Historial de préstamos (últimos 10)
        $loan_history = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t_loans} WHERE equipment_id = %d ORDER BY loan_date DESC LIMIT 10",
            $id
        ) );

        foreach ( $loan_history as &$l ) {
            $user      = get_user_by( 'id', $l->borrowed_by_user_id );
            $free_name = trim( $l->borrowed_to_name ?? '' );

            // Prioridad: nombre libre → usuario WP (consistente con módulo de préstamos)
            if ( $free_name !== '' ) {
                $l->borrower_display = $free_name;
                $l->borrower_avatar  = null;
                $l->is_external      = true;
            } else {
                $l->borrower_display = $user ? $user->display_name : '—';
                $l->borrower_avatar  = $user
                    ? get_avatar_url( $user->ID, [ 'size' => 40, 'default' => 'mystery' ] )
                    : null;
                $l->is_external      = false;
            }

            $l->borrower_name = $l->borrower_display; // alias compatibilidad
        }
        unset( $l );

        // Enriquecer equipo
        $equipment->status_label       = self::get_status_label( $equipment->status );
        $equipment->maintenance_status = self::get_maintenance_status( $equipment );
        $equipment->responsible_name   = $equipment->responsible_user_id
            ? ( get_user_by( 'id', $equipment->responsible_user_id )->display_name ?? '—' )
            : '—';
        // URLs de imagen en ambos tamaños
        $photo_urls                  = aura_get_equipment_photo_urls( $equipment->photo ?? '' );
        $equipment->photo_full_url   = $photo_urls['full'];
        $equipment->photo_thumb_url  = $photo_urls['thumb'];

        // Resolver nombre del equipo padre (si aplica)
        $equipment->parent_equipment_name = null;
        if ( ! empty( $equipment->parent_equipment_id ) ) {
            $equipment->parent_equipment_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM {$t_equip} WHERE id = %d AND deleted_at IS NULL",
                $equipment->parent_equipment_id
            ) );
        }

        // Componentes / accesorios vinculados (equipos hijos)
        $components = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, brand, internal_code, status, category
             FROM {$t_equip}
             WHERE parent_equipment_id = %d AND deleted_at IS NULL
             ORDER BY name ASC",
            $id
        ) );
        foreach ( $components as &$comp ) {
            $comp->status_label = self::get_status_label( $comp->status );
        }
        unset( $comp );

        wp_send_json_success( [
            'equipment'           => $equipment,
            'maintenance_history' => $maintenance_history,
            'loan_history'        => $loan_history,
            'components'          => $components,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Cambio rápido de estado
    // ─────────────────────────────────────────────────────────────

    public static function ajax_update_status(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $id     = intval( $_POST['id']     ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $allowed = [ 'available', 'in_use', 'maintenance', 'repair', 'retired' ];

        if ( ! $id || ! in_array( $status, $allowed ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos inválidos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_inventory_equipment';
        $result = $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al actualizar el estado.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [
            'status'       => $status,
            'status_label' => self::get_status_label( $status ),
            'message'      => __( 'Estado actualizado.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Autocomplete/search (para módulo de préstamos y mantenimientos)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_search(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_inventory_equipment';
        $term   = sanitize_text_field( $_POST['term']   ?? '' );
        $status = sanitize_text_field( $_POST['status'] ?? '' ); // opcional: filtrar por status

        $like   = '%' . $wpdb->esc_like( $term ) . '%';
        $params = [ $like, $like, $like ];
        $extra  = '';

        if ( $status ) {
            $extra    = 'AND status = %s';
            $params[] = $status;
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, brand, serial_number, status, category, photo
             FROM {$table}
             WHERE deleted_at IS NULL
               AND (name LIKE %s OR brand LIKE %s OR serial_number LIKE %s)
               {$extra}
             ORDER BY name ASC
             LIMIT 20",
            ...$params
        ) );

        $output = [];
        foreach ( $results as $r ) {
            $photo_urls = aura_get_equipment_photo_urls( $r->photo ?? '' );
            $output[] = [
                'id'    => $r->id,
                'label' => $r->name . ( $r->brand ? " ({$r->brand})" : '' ),
                'value' => $r->name,
                'extra' => [
                    'serial'          => $r->serial_number,
                    'status'          => $r->status,
                    'category'        => $r->category,
                    'photo'           => $r->photo,
                    'photo_thumb_url' => $photo_urls['thumb'],
                    'photo_full_url'  => $photo_urls['full'],
                ],
            ];
        }

        wp_send_json_success( $output );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Datos para poblar formulario de edición
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_form_data(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table     = $wpdb->prefix . 'aura_inventory_equipment';
        $equipment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id
        ) );

        if ( ! $equipment ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no encontrado.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'equipment' => $equipment ] );
    }

    // ─────────────────────────────────────────────────────────────
    // LÓGICA DE NEGOCIO — helpers públicos
    // ─────────────────────────────────────────────────────────────

    /**
     * Recalcular y persistir la fecha/horas del próximo mantenimiento.
     * Llamado después de guardar un equipo o al registrar un mantenimiento.
     *
     * @param int $equipment_id
     */
    public static function recalculate_next_maintenance( int $equipment_id ): void {
        global $wpdb;
        $table     = $wpdb->prefix . 'aura_inventory_equipment';
        $equipment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", $equipment_id
        ) );

        if ( ! $equipment || ! $equipment->requires_maintenance ) {
            return;
        }

        $next_date  = null;
        $next_hours = null;

        // Por tiempo
        if ( in_array( $equipment->interval_type, [ 'time', 'both' ] ) && $equipment->interval_months ) {
            $base = $equipment->last_maintenance_date ?: $equipment->acquisition_date ?: date( 'Y-m-d' );
            $next_date = date( 'Y-m-d', strtotime( "+{$equipment->interval_months} months", strtotime( $base ) ) );
        }

        // Por horas
        if ( in_array( $equipment->interval_type, [ 'hours', 'both' ] ) && $equipment->interval_hours ) {
            $base_hours = floatval( $equipment->last_maintenance_hours ?? 0 );
            $next_hours = $base_hours + floatval( $equipment->interval_hours );
        }

        $wpdb->update( $table, [
            'next_maintenance_date'  => $next_date,
            'next_maintenance_hours' => $next_hours,
        ], [ 'id' => $equipment_id ] );

        // Sincronizar con Google Calendar (solo si la integración está activa)
        if ( class_exists( 'Aura_Inventory_Google_Calendar' ) ) {
            Aura_Inventory_Google_Calendar::sync_maintenance_event( $equipment_id );
        }
    }

    /**
     * Determinar estado del mantenimiento de un equipo
     *
     * @param object $equipment  Fila de BD del equipo
     * @return string  'overdue' | 'urgent' | 'warning' | 'ok' | 'none'
     */
    public static function get_maintenance_status( object $equipment ): string {
        if ( ! $equipment->requires_maintenance || ! $equipment->next_maintenance_date ) {
            return 'none';
        }

        $today      = strtotime( date( 'Y-m-d' ) );
        $next       = strtotime( $equipment->next_maintenance_date );
        $days_left  = (int) round( ( $next - $today ) / DAY_IN_SECONDS );
        $alert_days = intval( $equipment->alert_days_before ?: 7 );

        if ( $days_left < 0 )           return 'overdue';
        if ( $days_left <= 3 )          return 'urgent';
        if ( $days_left <= $alert_days ) return 'warning';
        return 'ok';
    }

    /**
     * Etiqueta legible del estado del equipo
     *
     * @param string $status
     * @return string
     */
    public static function get_status_label( string $status ): string {
        $labels = [
            'available'   => __( 'Disponible',   'aura-suite' ),
            'in_use'      => __( 'En uso',        'aura-suite' ),
            'maintenance' => __( 'Mantenimiento', 'aura-suite' ),
            'repair'      => __( 'Reparación',    'aura-suite' ),
            'retired'     => __( 'Retirado',      'aura-suite' ),
        ];
        return $labels[ $status ] ?? $status;
    }

    /**
     * Etiqueta legible del tipo de mantenimiento
     *
     * @param string $type
     * @return string
     */
    public static function get_maintenance_type_label( string $type ): string {
        $labels = [
            'preventive'   => __( 'Preventivo',       'aura-suite' ),
            'corrective'   => __( 'Correctivo',        'aura-suite' ),
            'oil_change'   => __( 'Cambio de aceite',  'aura-suite' ),
            'cleaning'     => __( 'Limpieza',          'aura-suite' ),
            'inspection'   => __( 'Inspección',        'aura-suite' ),
            'major_repair' => __( 'Reparación mayor',  'aura-suite' ),
        ];
        return $labels[ $type ] ?? $type;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Obtener URLs de foto de un equipo (uso en otros módulos)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_equipment_photo(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        // Permiso mínimo: crear o editar mantenimientos (o gestor)
        if ( ! current_user_can( 'aura_inventory_maintenance_create' )
             && ! current_user_can( 'aura_inventory_view_all' )
             && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $photo = $wpdb->get_var( $wpdb->prepare(
            "SELECT photo FROM {$wpdb->prefix}aura_inventory_equipment WHERE id = %d AND deleted_at IS NULL",
            $id
        ) );

        if ( $photo === null ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no encontrado.', 'aura-suite' ) ] );
        }

        $urls = aura_get_equipment_photo_urls( (string) $photo );
        wp_send_json_success( [
            'photo_full_url'  => $urls['full'],
            'photo_thumb_url' => $urls['thumb'],
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Recortar y comprimir foto de equipo
    // ─────────────────────────────────────────────────────────────

    /**
     * Recibe un attachment_id de la media library, las coordenadas de recorte
     * (de Cropper.js getData(true)) en píxeles reales, y genera:
     *   • Una imagen principal 800×600 px, JPEG q80 → nuevo WP attachment.
     *   • El tamaño aura-equipment-thumb (220×165) se genera automáticamente.
     * Devuelve el ID del nuevo adjunto y sus dos URLs.
     */
    public static function ajax_crop_equipment_photo(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        $crop_x        = (int) round( (float) ( $_POST['x']      ?? 0 ) );
        $crop_y        = (int) round( (float) ( $_POST['y']      ?? 0 ) );
        $crop_w        = (int) round( (float) ( $_POST['width']  ?? 0 ) );
        $crop_h        = (int) round( (float) ( $_POST['height'] ?? 0 ) );

        if ( ! $attachment_id || $crop_w < 10 || $crop_h < 10 ) {
            wp_send_json_error( [ 'message' => __( 'Datos de recorte inválidos.', 'aura-suite' ) ] );
        }
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'El archivo seleccionado no es una imagen.', 'aura-suite' ) ] );
        }

        $original_path = get_attached_file( $attachment_id );
        if ( ! $original_path || ! file_exists( $original_path ) ) {
            wp_send_json_error( [ 'message' => __( 'Archivo original no encontrado.', 'aura-suite' ) ] );
        }

        // Abrir editor de imagen
        $editor = wp_get_image_editor( $original_path );
        if ( is_wp_error( $editor ) ) {
            wp_send_json_error( [ 'message' => $editor->get_error_message() ] );
        }

        // Recortar al área seleccionada por el usuario
        $crop_result = $editor->crop( $crop_x, $crop_y, $crop_w, $crop_h );
        if ( is_wp_error( $crop_result ) ) {
            wp_send_json_error( [ 'message' => $crop_result->get_error_message() ] );
        }

        // Redimensionar a exactamente 800×600
        $resize_result = $editor->resize( 800, 600, true );
        if ( is_wp_error( $resize_result ) ) {
            wp_send_json_error( [ 'message' => __( 'Error al redimensionar la imagen.', 'aura-suite' ) ] );
        }

        // Calidad JPEG 80%
        $editor->set_quality( 80 );

        // Guardar en el directorio de uploads del mes actual
        $upload_dir = wp_upload_dir();
        $filename   = 'equipo-' . time() . '-' . wp_rand( 1000, 9999 ) . '.jpg';
        $save_path  = trailingslashit( $upload_dir['path'] ) . $filename;
        $saved      = $editor->save( $save_path, 'image/jpeg' );

        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( [ 'message' => $saved->get_error_message() ] );
        }

        // Registrar como adjunto en la Media Library
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $new_id = wp_insert_attachment( $attachment, $saved['path'] );
        if ( is_wp_error( $new_id ) ) {
            wp_send_json_error( [ 'message' => $new_id->get_error_message() ] );
        }

        // Generar metadata → WP crea automáticamente aura-equipment-thumb (220×165)
        $metadata = wp_generate_attachment_metadata( $new_id, $saved['path'] );
        wp_update_attachment_metadata( $new_id, $metadata );

        $urls = aura_get_equipment_photo_urls( (string) $new_id );
        wp_send_json_success( [
            'attachment_id' => $new_id,
            'full_url'      => $urls['full'],
            'thumb_url'     => $urls['thumb'],
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS INTERNOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Sanitizar y validar una fecha en formato Y-m-d
     *
     * @param string $raw
     * @return string|null
     */
    private static function sanitize_date( string $raw ): ?string {
        $clean = sanitize_text_field( $raw );
        if ( $clean && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $clean ) ) {
            return $clean;
        }
        return null;
    }
}
