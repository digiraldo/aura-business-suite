<?php
/**
 * Sistema de Préstamos de Equipos — FASE 5
 *
 * Checkout / Checkin de equipos con validación de disponibilidad,
 * actualización automática de estado del equipo, historial de préstamos
 * y detección de préstamos vencidos.
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Loans {

    const NONCE = 'aura_inventory_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'get_loans_list'             => 'ajax_get_loans_list',
            'checkout_equipment'         => 'ajax_checkout_equipment',
            'checkin_equipment'          => 'ajax_checkin_equipment',
            'get_active_loans'           => 'ajax_get_active_loans',
            'get_overdue_loans'          => 'ajax_get_overdue_loans',
            'get_equipment_loan_history' => 'ajax_get_equipment_loan_history',
            'get_available_equipment'    => 'ajax_get_available_equipment',
            'get_loan_detail'            => 'ajax_get_loan_detail',
            'update_loan'                => 'ajax_update_loan',
            'delete_loan'                => 'ajax_delete_loan',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_inventory_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_list(): void {
        $can = current_user_can( 'aura_inventory_checkout' ) ||
               current_user_can( 'aura_inventory_checkin'  ) ||
               current_user_can( 'manage_options' );
        if ( ! $can ) {
            wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/loans-list.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado con filtros
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_loans_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $can = current_user_can( 'aura_inventory_checkout' ) ||
               current_user_can( 'aura_inventory_checkin'  ) ||
               current_user_can( 'manage_options' );
        if ( ! $can ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $page        = max( 1, intval( $_POST['page']     ?? 1  ) );
        $per_page    = min( 100, max( 10, intval( $_POST['per_page'] ?? 20 ) ) );
        $offset      = ( $page - 1 ) * $per_page;
        $search      = sanitize_text_field( $_POST['search']      ?? '' );
        $status_filter = sanitize_key( $_POST['loan_status'] ?? '' ); // active|overdue|returned
        $equipment_id  = intval( $_POST['equipment_id'] ?? 0 );
        $date_from   = self::sanitize_date( $_POST['date_from'] ?? '' );
        $date_to     = self::sanitize_date( $_POST['date_to']   ?? '' );
        $sort_by     = in_array( $_POST['sort_by'] ?? '', [ 'loan_date', 'expected_return_date', 'actual_return_date', 'equipment_name' ] )
                           ? sanitize_key( $_POST['sort_by'] ) : 'loan_date';
        $sort_dir    = strtoupper( sanitize_key( $_POST['sort_dir'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

        $where  = [ '1=1' ];
        $params = [];
        $today  = current_time( 'Y-m-d' );

        if ( $equipment_id > 0 ) {
            $where[] = 'l.equipment_id = %d';
            $params[] = $equipment_id;
        }
        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(e.name LIKE %s OR l.borrowed_to_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        if ( $status_filter === 'active' ) {
            $where[] = 'l.actual_return_date IS NULL AND l.expected_return_date >= %s';
            $params[] = $today;
        } elseif ( $status_filter === 'overdue' ) {
            $where[] = 'l.actual_return_date IS NULL AND l.expected_return_date < %s';
            $params[] = $today;
        } elseif ( $status_filter === 'returned' ) {
            $where[] = 'l.actual_return_date IS NOT NULL';
        }
        if ( $date_from ) {
            $where[] = 'l.loan_date >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[] = 'l.loan_date <= %s';
            $params[] = $date_to;
        }

        $where_sql = implode( ' AND ', $where );
        $sort_col  = $sort_by === 'equipment_name' ? 'e.name' : "l.{$sort_by}";

        $base_sql = "FROM {$t_loans} l
                     LEFT JOIN {$t_equip} e ON e.id = l.equipment_id
                     WHERE {$where_sql}";

        $count_sql = "SELECT COUNT(*) {$base_sql}";
        $data_sql  = "SELECT
                        l.*,
                        e.name       AS equipment_name,
                        e.brand      AS equipment_brand,
                        e.category   AS equipment_category,
                        e.status     AS equipment_status,
                        e.photo      AS equipment_photo_id,
                        DATEDIFF( %s, l.expected_return_date ) AS days_overdue
                      {$base_sql}
                      ORDER BY {$sort_col} {$sort_dir}
                      LIMIT %d OFFSET %d";

        if ( ! empty( $params ) ) {
            $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
            $rows      = $wpdb->get_results( $wpdb->prepare( $data_sql, $today, $per_page, $offset, ...$params ) );
        } else {
            $total     = (int) $wpdb->get_var( $count_sql );
            $rows      = $wpdb->get_results( $wpdb->prepare( $data_sql, $today, $per_page, $offset ) );
        }

        // Enriquecer con datos de usuario
        $can_edit   = current_user_can( 'aura_inventory_loan_edit'   ) || current_user_can( 'manage_options' );
        $can_delete = current_user_can( 'aura_inventory_loan_delete' ) || current_user_can( 'manage_options' );

        foreach ( $rows as &$row ) {
            $user = get_userdata( $row->borrowed_by_user_id );

            // Prioridad: nombre libre → si no, nombre del usuario WP
            $free_name = trim( $row->borrowed_to_name ?? '' );
            if ( $free_name !== '' ) {
                $row->borrower_display = $free_name;
                $row->borrower_avatar  = null;   // sin avatar para externos
                $row->is_external      = true;
            } else {
                $row->borrower_display = $user ? $user->display_name : __( 'Usuario desconocido', 'aura-suite' );
                $row->borrower_avatar  = $user
                    ? get_avatar_url( $user->ID, [ 'size' => 40, 'default' => 'mystery' ] )
                    : null;
                $row->is_external      = false;
            }

            $row->loan_status = self::get_loan_status( $row );
            // URL miniatura de la foto del equipo
            $photo_urls             = aura_get_equipment_photo_urls( $row->equipment_photo_id ?? '' );
            $row->equipment_photo_thumb = $photo_urls['thumb'];
            $row->can_edit   = $can_edit;
            $row->can_delete = $can_delete;
        }
        unset( $row );

        wp_send_json_success( [
            'items'      => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages'=> (int) ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — CHECKOUT (salida del equipo)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_checkout_equipment(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_checkout' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para registrar préstamos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';

        $equipment_id = intval( $_POST['equipment_id'] ?? 0 );
        if ( $equipment_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no válido.', 'aura-suite' ) ] );
        }

        // ── Validar que el equipo exista y esté disponible ─────
        $equip = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, status FROM {$t_equip} WHERE id = %d AND deleted_at IS NULL",
            $equipment_id
        ) );
        if ( ! $equip ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no encontrado.', 'aura-suite' ) ] );
        }
        if ( $equip->status !== 'available' ) {
            wp_send_json_error( [ 'message' => sprintf(
                __( 'El equipo "%s" no está disponible (estado actual: %s).', 'aura-suite' ),
                esc_html( $equip->name ),
                esc_html( self::get_status_label( $equip->status ) )
            ) ] );
        }

        // ── Validar que no haya préstamo activo para ese equipo ─
        $active_loan = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$t_loans} WHERE equipment_id = %d AND actual_return_date IS NULL",
            $equipment_id
        ) );
        if ( $active_loan ) {
            wp_send_json_error( [ 'message' => __( 'Este equipo ya tiene un préstamo activo registrado.', 'aura-suite' ) ] );
        }

        // ── Validar fechas ──────────────────────────────────────
        $loan_date           = self::sanitize_date( $_POST['loan_date']           ?? '' );
        $expected_return     = self::sanitize_date( $_POST['expected_return_date'] ?? '' );
        if ( ! $loan_date || ! $expected_return ) {
            wp_send_json_error( [ 'message' => __( 'Las fechas de préstamo y devolución esperada son obligatorias.', 'aura-suite' ) ] );
        }
        if ( $expected_return < $loan_date ) {
            wp_send_json_error( [ 'message' => __( 'La fecha de devolución esperada no puede ser anterior a la fecha de préstamo.', 'aura-suite' ) ] );
        }

        $borrowed_by   = intval( $_POST['borrowed_by_user_id'] ?? get_current_user_id() );
        $borrowed_name = sanitize_text_field( $_POST['borrowed_to_name'] ?? '' );
        $borrowed_phone = sanitize_text_field( $_POST['borrowed_to_phone'] ?? '' );
        // Validar formato básico de teléfono: solo +, dígitos, espacios, guiones
        if ( $borrowed_phone !== '' && ! preg_match( '/^[\+0-9\s\-]{6,30}$/', $borrowed_phone ) ) {
            $borrowed_phone = '';
        }
        $project       = sanitize_text_field( $_POST['project']           ?? '' );
        $state_out     = in_array( $_POST['equipment_state_out'] ?? '', [ 'good', 'fair', 'poor' ] )
                             ? sanitize_key( $_POST['equipment_state_out'] ) : 'good';
        $photo_out     = sanitize_text_field( $_POST['photo_out'] ?? '' );

        // ── Insertar préstamo ───────────────────────────────────
        $inserted = $wpdb->insert(
            $t_loans,
            [
                'equipment_id'        => $equipment_id,
                'borrowed_by_user_id' => $borrowed_by,
                'borrowed_to_name'    => $borrowed_name ?: null,
                'borrowed_to_phone'   => $borrowed_phone ?: null,
                'loan_date'           => $loan_date,
                'expected_return_date'=> $expected_return,
                'project'             => $project ?: null,
                'equipment_state_out' => $state_out,
                'photo_out'           => $photo_out ?: null,
                'registered_by'       => get_current_user_id(),
                'created_at'          => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( ! $inserted || ! $wpdb->insert_id ) {
            wp_send_json_error( [ 'message' => __( 'Error al registrar el préstamo en la base de datos.', 'aura-suite' ) ] );
        }

        // ── Actualizar status del equipo → in_use ───────────────
        $wpdb->update(
            $t_equip,
            [ 'status' => 'in_use', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id'     => $equipment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        $new_loan_id = $wpdb->insert_id;

        // ── Notificación WhatsApp de checkout (si hay teléfono) ─
        if ( $borrowed_phone ) {
            Aura_Inventory_Notifications::notify_borrower( $new_loan_id, 'checkout' );
        }

        wp_send_json_success( [
            'message'  => sprintf( __( 'Préstamo registrado correctamente para "%s".', 'aura-suite' ), esc_html( $equip->name ) ),
            'loan_id'  => $new_loan_id,
            'equipment'=> esc_html( $equip->name ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — CHECKIN (devolución del equipo)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_checkin_equipment(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_checkin' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para registrar devoluciones.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( $loan_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no válido.', 'aura-suite' ) ] );
        }

        // ── Obtener el préstamo ─────────────────────────────────
        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT l.*, e.name AS equipment_name
             FROM {$t_loans} l
             LEFT JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.id = %d",
            $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-suite' ) ] );
        }
        if ( ! is_null( $loan->actual_return_date ) ) {
            wp_send_json_error( [ 'message' => __( 'Este préstamo ya fue devuelto.', 'aura-suite' ) ] );
        }

        // ── Solo el registrador o admin puede hacer checkin ─────
        $current_uid = get_current_user_id();
        if ( (int) $loan->registered_by !== $current_uid && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo el registrador del préstamo o un administrador puede registrar la devolución.', 'aura-suite' ) ] );
        }

        $actual_return = self::sanitize_date( $_POST['actual_return_date'] ?? '' );
        if ( ! $actual_return ) {
            $actual_return = current_time( 'Y-m-d' );
        }

        $return_state  = in_array( $_POST['return_state'] ?? '', [ 'good', 'fair', 'damaged' ] )
                             ? sanitize_key( $_POST['return_state'] ) : 'good';
        $hours_used    = ( $_POST['hours_used'] ?? '' !== '' ) ? abs( floatval( $_POST['hours_used'] ) ) : null;
        $return_photo  = sanitize_text_field( $_POST['return_photo']  ?? '' );
        $req_maint     = ! empty( $_POST['requires_maintenance_after'] ) ? 1 : 0;
        $observations  = sanitize_textarea_field( $_POST['return_observations'] ?? '' );

        // ── Actualizar préstamo ─────────────────────────────────
        $wpdb->update(
            $t_loans,
            [
                'actual_return_date'         => $actual_return,
                'return_state'               => $return_state,
                'hours_used'                 => $hours_used,
                'return_photo'               => $return_photo ?: null,
                'requires_maintenance_after' => $req_maint,
                'return_observations'        => $observations ?: null,
                'returned_at'                => current_time( 'mysql' ),
            ],
            [ 'id' => $loan_id ],
            [ '%s', '%s', $hours_used !== null ? '%f' : '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        // ── Determinar nuevo status del equipo ──────────────────
        if ( $return_state === 'damaged' || $req_maint ) {
            $new_status = 'maintenance';
        } else {
            $new_status = 'available';
        }

        $wpdb->update(
            $t_equip,
            [ 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id'     => $loan->equipment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // ── Alerta si el equipo llega dañado ───────────────────
        if ( $return_state === 'damaged' ) {
            self::notify_damaged_equipment( $loan );
        }

        // ── Notificación WhatsApp de checkin ────────────────────
        if ( ! empty( $loan->borrowed_to_phone ) ) {
            Aura_Inventory_Notifications::notify_borrower( $loan_id, 'checkin' );
        }

        wp_send_json_success( [
            'message'     => sprintf( __( 'Devolución registrada correctamente para "%s".', 'aura-suite' ), esc_html( $loan->equipment_name ) ),
            'new_status'  => $new_status,
            'status_label'=> self::get_status_label( $new_status ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Préstamos activos (para Dashboard)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_active_loans(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $today   = current_time( 'Y-m-d' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.equipment_id, l.borrowed_to_name, l.loan_date, l.expected_return_date,
                    e.name AS equipment_name,
                    DATEDIFF( %s, l.expected_return_date ) AS days_overdue
             FROM {$t_loans} l
             LEFT JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.actual_return_date IS NULL
             ORDER BY l.expected_return_date ASC
             LIMIT 20",
            $today
        ) );

        wp_send_json_success( $rows );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Préstamos vencidos (para Dashboard)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_overdue_loans(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $today   = current_time( 'Y-m-d' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.equipment_id, l.borrowed_to_name, l.loan_date, l.expected_return_date,
                    e.name AS equipment_name,
                    DATEDIFF( %s, l.expected_return_date ) AS days_overdue
             FROM {$t_loans} l
             LEFT JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.actual_return_date IS NULL
               AND l.expected_return_date < %s
             ORDER BY l.expected_return_date ASC
             LIMIT 10",
            $today, $today
        ) );

        wp_send_json_success( $rows );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Historial de préstamos de un equipo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_equipment_loan_history(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $equipment_id = intval( $_POST['equipment_id'] ?? 0 );
        if ( $equipment_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Equipo no válido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $today   = current_time( 'Y-m-d' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*,
                    DATEDIFF( %s, l.expected_return_date ) AS days_overdue
             FROM {$t_loans} l
             WHERE l.equipment_id = %d
             ORDER BY l.loan_date DESC
             LIMIT 30",
            $today, $equipment_id
        ) );

        foreach ( $rows as &$row ) {
            $user      = get_userdata( $row->borrowed_by_user_id );
            $free_name = trim( $row->borrowed_to_name ?? '' );

            // Prioridad: nombre libre del prestatario → si no, usuario WP
            if ( $free_name !== '' ) {
                $row->borrower_display = $free_name;
                $row->borrower_avatar  = null;
                $row->is_external      = true;
            } else {
                $row->borrower_display = $user ? $user->display_name : __( 'Desconocido', 'aura-suite' );
                $row->borrower_avatar  = $user
                    ? get_avatar_url( $user->ID, [ 'size' => 40, 'default' => 'mystery' ] )
                    : null;
                $row->is_external      = false;
            }

            $row->loan_status = self::get_loan_status( $row );
        }
        unset( $row );

        wp_send_json_success( $rows );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Equipos disponibles para checkout
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_available_equipment(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        global $wpdb;
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $search  = sanitize_text_field( $_POST['search'] ?? '' );

        $where  = "deleted_at IS NULL AND status = 'available'";
        $params = [];

        if ( $search !== '' ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= ' AND (name LIKE %s OR brand LIKE %s OR internal_code LIKE %s)';
            $params  = [ $like, $like, $like ];
        }

        $sql = "SELECT id, name, brand, model, internal_code, category
                FROM {$t_equip}
                WHERE {$where}
                ORDER BY name ASC
                LIMIT 50";

        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) )
            : $wpdb->get_results( $sql );

        wp_send_json_success( $rows );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Detalle de un préstamo (para modal checkin)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_loan_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( $loan_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no válido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT l.*, e.name AS equipment_name, e.brand AS equipment_brand
             FROM {$t_loans} l
             LEFT JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.id = %d",
            $loan_id
        ) );

        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-suite' ) ] );
        }

        $user      = get_userdata( $loan->borrowed_by_user_id );
        $free_name = trim( $loan->borrowed_to_name ?? '' );

        // Prioridad: nombre libre → usuario WP (igual que en get_loans_list e historial)
        if ( $free_name !== '' ) {
            $loan->borrower_display = $free_name;
            $loan->borrower_avatar  = null;
            $loan->is_external      = true;
        } else {
            $loan->borrower_display = $user ? $user->display_name : __( 'Desconocido', 'aura-suite' );
            $loan->borrower_avatar  = $user
                ? get_avatar_url( $user->ID, [ 'size' => 40, 'default' => 'mystery' ] )
                : null;
            $loan->is_external      = false;
        }

        $loan->loan_status = self::get_loan_status( $loan );

        wp_send_json_success( $loan );
    }

    // ─────────────────────────────────────────────────────────────
    // KPIs estáticos (para render síncrono en template)
    // ─────────────────────────────────────────────────────────────

    public static function get_kpis(): array {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $today   = current_time( 'Y-m-d' );
        $first_of_month = current_time( 'Y-m-01' );

        return [
            'active'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_loans} WHERE actual_return_date IS NULL" ),
            'overdue'  => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_loans} WHERE actual_return_date IS NULL AND expected_return_date < %s",
                $today
            ) ),
            'returned_month' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t_loans} WHERE actual_return_date IS NOT NULL AND actual_return_date >= %s",
                $first_of_month
            ) ),
            'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_loans}" ),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve el estado lógico de un préstamo
     */
    public static function get_loan_status( object $loan ): string {
        $today = current_time( 'Y-m-d' );
        if ( ! is_null( $loan->actual_return_date ) ) {
            return 'returned';
        }
        if ( $loan->expected_return_date < $today ) {
            return 'overdue';
        }
        return 'active';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Editar préstamo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_update_loan(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_loan_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para editar préstamos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( $loan_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no válido.', 'aura-suite' ) ] );
        }

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_loans} WHERE id = %d",
            $loan_id
        ) );
        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-suite' ) ] );
        }

        $loan_date       = self::sanitize_date( $_POST['loan_date']            ?? '' );
        $expected_return = self::sanitize_date( $_POST['expected_return_date'] ?? '' );
        if ( ! $loan_date || ! $expected_return ) {
            wp_send_json_error( [ 'message' => __( 'Las fechas de préstamo y devolución esperada son obligatorias.', 'aura-suite' ) ] );
        }
        if ( $expected_return < $loan_date ) {
            wp_send_json_error( [ 'message' => __( 'La fecha de devolución no puede ser anterior a la fecha de préstamo.', 'aura-suite' ) ] );
        }

        $borrowed_name  = sanitize_text_field( $_POST['borrowed_to_name']  ?? '' );
        $borrowed_phone = sanitize_text_field( $_POST['borrowed_to_phone'] ?? '' );
        if ( $borrowed_phone !== '' && ! preg_match( '/^[\+0-9\s\-]{6,30}$/', $borrowed_phone ) ) {
            $borrowed_phone = '';
        }
        $project = sanitize_text_field( $_POST['project'] ?? '' );

        $wpdb->update(
            $t_loans,
            [
                'loan_date'            => $loan_date,
                'expected_return_date' => $expected_return,
                'borrowed_to_name'     => $borrowed_name  ?: null,
                'borrowed_to_phone'    => $borrowed_phone ?: null,
                'project'              => $project        ?: null,
                'updated_at'           => current_time( 'mysql' ),
            ],
            [ 'id' => $loan_id ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => __( 'Préstamo actualizado correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Eliminar préstamo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_loan(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_inventory_loan_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para eliminar préstamos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $loan_id = intval( $_POST['loan_id'] ?? 0 );
        if ( $loan_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no válido.', 'aura-suite' ) ] );
        }

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_loans} WHERE id = %d",
            $loan_id
        ) );
        if ( ! $loan ) {
            wp_send_json_error( [ 'message' => __( 'Préstamo no encontrado.', 'aura-suite' ) ] );
        }

        // Si el préstamo estaba activo, liberar el equipo
        if ( is_null( $loan->actual_return_date ) ) {
            $wpdb->update(
                $t_equip,
                [ 'status' => 'available', 'updated_at' => current_time( 'mysql' ) ],
                [ 'id'     => $loan->equipment_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        $wpdb->delete( $t_loans, [ 'id' => $loan_id ], [ '%d' ] );

        wp_send_json_success( [ 'message' => __( 'Préstamo eliminado correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Etiqueta legible del estado del equipo
     */
    public static function get_status_label( string $status ): string {
        $labels = [
            'available'   => __( 'Disponible',    'aura-suite' ),
            'in_use'      => __( 'En uso',         'aura-suite' ),
            'maintenance' => __( 'Mantenimiento',  'aura-suite' ),
            'in_repair'   => __( 'En reparación',  'aura-suite' ),
            'retired'     => __( 'Retirado',        'aura-suite' ),
        ];
        return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
    }

    /**
     * Sanitizar y validar fecha (YYYY-MM-DD)
     */
    private static function sanitize_date( string $raw ): string {
        $d = sanitize_text_field( trim( $raw ) );
        return ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) ? $d : '';
    }

    /**
     * Notificación cuando el equipo regresa dañado
     */
    private static function notify_damaged_equipment( object $loan ): void {
        $admin_email = get_option( 'admin_email' );
        $subject     = sprintf(
            __( '[AURA Inventario] Equipo devuelto con daño: %s', 'aura-suite' ),
            $loan->equipment_name
        );
        $user = get_userdata( $loan->borrowed_by_user_id );
        $body = sprintf(
            __( "El equipo \"%s\" fue devuelto en estado DAÑADO.\n\nPrestado a: %s\nFecha préstamo: %s\nFecha devolución: %s\n\nRevisa el panel de Préstamos para más detalles.", 'aura-suite' ),
            $loan->equipment_name,
            $loan->borrowed_to_name ?: ( $user ? $user->display_name : 'N/D' ),
            $loan->loan_date,
            current_time( 'Y-m-d' )
        );
        wp_mail( $admin_email, $subject, $body );
    }
}
