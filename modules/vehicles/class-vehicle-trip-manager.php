<?php
/**
 * Aura Vehicle Trip Manager — Fase 3
 * Capa de negocio: CRUD completo del ciclo de vida de salidas (trips).
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Trip_Manager {

    const TABLE         = 'aura_vehicle_trips';
    const TABLE_VEHICLE = 'aura_vehicles';

    // Estados válidos del viaje
    const STATUS_ACTIVE    = 'active';
    const STATUS_RETURNED  = 'returned';
    const STATUS_CANCELLED = 'cancelled';

    // Tipos de viaje
    const TYPE_RENTAL      = 'rental';
    const TYPE_ERRAND      = 'errand';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_OTHER       = 'other';

    // ─────────────────────────────────────────────────────────────
    // CREAR SALIDA
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar una nueva salida vehicular.
     *
     * @param  array $data Datos de la salida sin sanitizar.
     * @return int|WP_Error ID del nuevo trip o WP_Error.
     */
    public static function create( array $data ) {
        global $wpdb;

        // Validaciones obligatorias
        $vehicle_id = absint( $data['vehicle_id'] ?? 0 );
        if ( ! $vehicle_id ) {
            return new WP_Error( 'missing_vehicle', __( 'El vehículo es obligatorio.', 'aura-suite' ) );
        }

        $trip_type = sanitize_text_field( $data['trip_type'] ?? '' );
        if ( ! in_array( $trip_type, array( self::TYPE_RENTAL, self::TYPE_ERRAND, self::TYPE_MAINTENANCE, self::TYPE_OTHER ), true ) ) {
            return new WP_Error( 'invalid_type', __( 'Tipo de salida inválido.', 'aura-suite' ) );
        }

        $departure_datetime = self::normalize_datetime_input( $data['departure_datetime'] ?? '' );
        if ( ! $departure_datetime ) {
            return new WP_Error( 'missing_departure', __( 'La fecha de salida es obligatoria.', 'aura-suite' ) );
        }
        $data['departure_datetime'] = $departure_datetime;

        // Verificar vehículo existe y está disponible
        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM {$wpdb->prefix}" . self::TABLE_VEHICLE . " WHERE id = %d AND active = 1",
            $vehicle_id
        ) );

        if ( ! $vehicle ) {
            return new WP_Error( 'vehicle_not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        // Reglas de disponibilidad por tipo:
        // - rental/errand/other: solo si status = available
        // - maintenance: si status = available O maintenance (traslado a otro taller)
        $allowed_statuses = array( 'available' );
        if ( self::TYPE_MAINTENANCE === $trip_type ) {
            $allowed_statuses[] = 'maintenance';
        }
        if ( ! in_array( $vehicle->status, $allowed_statuses, true ) ) {
            return new WP_Error(
                'vehicle_not_available',
                sprintf(
                    __( 'El vehículo no está disponible para salida (estado actual: %s).', 'aura-suite' ),
                    $vehicle->status
                )
            );
        }

        // Fase 8: bloquear salidas tipo rental si el vehículo tiene mantenimiento vencido
        if ( self::TYPE_RENTAL === $trip_type && Aura_Vehicle_Alerts::is_blocked_for_rental( $vehicle_id ) ) {
            return new WP_Error(
                'maintenance_overdue',
                __( 'El vehículo tiene mantenimiento vencido y no puede salir como rental. Registra primero el mantenimiento.', 'aura-suite' )
            );
        }

        // Validaciones específicas por tipo
        $type_validation = self::validate_by_type( $trip_type, $data );
        if ( is_wp_error( $type_validation ) ) {
            return $type_validation;
        }

        // Construir fila
        $row = self::sanitize_fields( $data, $trip_type );
        $row['vehicle_id']         = $vehicle_id;
        $row['trip_type']          = $trip_type;
        $row['status']             = self::STATUS_ACTIVE;
        $row['created_by']         = get_current_user_id();
        $row['created_at']         = current_time( 'mysql' );
        $row['deleted']            = 0;

        // Actualizar estado del vehículo ANTES del insert para coherencia
        $new_vehicle_status = ( self::TYPE_MAINTENANCE === $trip_type ) ? 'maintenance' : 'rented';

        $wpdb->update(
            $wpdb->prefix . self::TABLE_VEHICLE,
            array( 'status' => $new_vehicle_status, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $vehicle_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        $result = $wpdb->insert( $wpdb->prefix . self::TABLE, $row );
        if ( false === $result ) {
            // Revertir status del vehículo
            $wpdb->update(
                $wpdb->prefix . self::TABLE_VEHICLE,
                array( 'status' => $vehicle->status, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $vehicle_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
            return new WP_Error( 'db_error', __( 'Error al registrar la salida.', 'aura-suite' ) );
        }

        $id = (int) $wpdb->insert_id;

        Aura_Vehicle_Audit_Manager::log( 'trip_create', 'trip', $id, array(
            'vehicle_id' => $vehicle_id,
            'trip_type'  => $trip_type,
            'status'     => self::STATUS_ACTIVE,
        ) );

        return $id;
    }

    // ─────────────────────────────────────────────────────────────
    // CHECK-IN (RETORNO)
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar el retorno de una salida activa.
     *
     * @param  int   $id          ID del trip.
     * @param  array $return_data Datos de retorno.
     * @return bool|WP_Error
     */
    public static function check_in( int $id, array $return_data ) {
        global $wpdb;

        $trip = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE id = %d AND deleted = 0",
            $id
        ) );

        if ( ! $trip ) {
            return new WP_Error( 'not_found', __( 'Salida no encontrada.', 'aura-suite' ) );
        }

        if ( self::STATUS_ACTIVE !== $trip->status ) {
            return new WP_Error( 'not_active', __( 'Solo se puede registrar retorno en salidas activas.', 'aura-suite' ) );
        }

        $return_datetime = self::normalize_datetime_input( $return_data['return_datetime'] ?? '' );
        if ( ! $return_datetime ) {
            return new WP_Error( 'missing_return_datetime', __( 'La fecha de retorno es obligatoria.', 'aura-suite' ) );
        }
        $return_data['return_datetime'] = $return_datetime;

        $return_odometer = absint( $return_data['return_odometer'] ?? 0 );
        $departure_odometer = (int) $trip->departure_odometer;
        $km_traveled = max( 0, $return_odometer - $departure_odometer );

        // Calcular total_amount (solo para rental)
        $total_amount = 0.00;
        if ( self::TYPE_RENTAL === $trip->trip_type ) {
            $rate         = (float) ( $return_data['rate_per_km'] ?? $trip->rate_per_km );
            $total_amount = $km_traveled * $rate;
        }

        $additional_charges = (float) ( $return_data['additional_charges'] ?? 0 );
        $discounts          = (float) ( $return_data['discounts'] ?? 0 );
        $total_expenses     = (float) ( $return_data['total_expenses'] ?? 0 );
        $final_total        = $total_amount + $additional_charges - $discounts;

        // Gastos detallados (JSON)
        $expenses_detail = null;
        if ( ! empty( $return_data['expenses_detail'] ) && is_array( $return_data['expenses_detail'] ) ) {
            $expenses_detail = wp_json_encode( $return_data['expenses_detail'] );
        }

        $update = array(
            'status'                 => self::STATUS_RETURNED,
            'return_datetime'        => $return_datetime,
            'return_odometer'        => $return_odometer,
            'return_fuel'            => isset( $return_data['return_fuel'] ) ? absint( $return_data['return_fuel'] ) : null,
            'km_traveled'            => $km_traveled,
            'total_amount'           => $final_total,
            'additional_charges'     => $additional_charges,
            'discounts'              => $discounts,
            'total_expenses'         => $total_expenses,
            'updated_at'             => current_time( 'mysql' ),
        );

        if ( ! empty( $return_data['maint_actual_cost'] ) && self::TYPE_MAINTENANCE === $trip->trip_type ) {
            $update['maint_actual_cost']          = (float) $return_data['maint_actual_cost'];
            $update['maint_completion_notes']     = sanitize_textarea_field( $return_data['maint_completion_notes'] ?? '' );
        }

        if ( $expenses_detail ) {
            $update['expenses_detail'] = $expenses_detail;
        }

        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            $update,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );

        // Actualizar kilometraje del vehículo y próximo mantenimiento si corresponde
        if ( $return_odometer > 0 ) {
            $vehicle_update = array(
                'mileage'     => $return_odometer,
                'updated_at'  => current_time( 'mysql' ),
            );
            $vehicle_format = array( '%d', '%s' );

            if ( self::TYPE_MAINTENANCE === $trip->trip_type ) {
                $next_service_interval_km = isset( $return_data['next_service_interval_km'] )
                    ? absint( $return_data['next_service_interval_km'] )
                    : 0;

                if ( $next_service_interval_km > 0 ) {
                    $vehicle_update['next_maintenance_km']   = $return_odometer + $next_service_interval_km;
                    $vehicle_update['maintenance_alert_sent'] = 0;
                    $vehicle_format[] = '%d';
                    $vehicle_format[] = '%d';
                }
            }

            $wpdb->update(
                $wpdb->prefix . self::TABLE_VEHICLE,
                $vehicle_update,
                array( 'id' => $trip->vehicle_id ),
                $vehicle_format,
                array( '%d' )
            );
        }

        // Restaurar status del vehículo
        $wpdb->update(
            $wpdb->prefix . self::TABLE_VEHICLE,
            array( 'status' => 'available', 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $trip->vehicle_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        // Fase 8: verificar si el vehículo necesita mantenimiento tras actualizar km
        if ( $return_odometer > 0 ) {
            Aura_Vehicle_Alerts::check_vehicle_after_checkin( (int) $trip->vehicle_id );
        }

        Aura_Vehicle_Audit_Manager::log( 'trip_checkin', 'trip', $id, array(
            'vehicle_id'    => $trip->vehicle_id,
            'km_traveled'   => $km_traveled,
            'total_amount'  => $final_total,
        ) );

        // Fase 10: notificar cierre de salida para integraciones (ej. Financial)
        do_action( 'aura_vehicles_trip_closed', $trip, $update );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // CANCELAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Cancelar una salida activa.
     *
     * @param  int    $id     ID del trip.
     * @param  string $reason Motivo de cancelación.
     * @return bool|WP_Error
     */
    public static function cancel( int $id, string $reason ) {
        global $wpdb;

        $trip = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status, vehicle_id, created_by FROM {$wpdb->prefix}" . self::TABLE . " WHERE id = %d AND deleted = 0",
            $id
        ) );

        if ( ! $trip ) {
            return new WP_Error( 'not_found', __( 'Salida no encontrada.', 'aura-suite' ) );
        }

        if ( self::STATUS_ACTIVE !== $trip->status ) {
            return new WP_Error( 'not_active', __( 'Solo se pueden cancelar salidas activas.', 'aura-suite' ) );
        }

        if ( empty( $reason ) ) {
            return new WP_Error( 'missing_reason', __( 'El motivo de cancelación es obligatorio.', 'aura-suite' ) );
        }

        // Brecha #5 — Verificar propiedad cuando el usuario no tiene exits_edit_all.
        $current_user = get_current_user_id();
        $can_edit_all = current_user_can( 'aura_vehicles_exits_edit_all' ) || current_user_can( 'manage_options' );
        $can_edit_own = current_user_can( 'aura_vehicles_exits_edit_own' );

        if ( ! $can_edit_all ) {
            if ( ! $can_edit_own || (int) $trip->created_by !== $current_user ) {
                return new WP_Error(
                    'permission_denied',
                    __( 'No tienes permiso para cancelar esta salida.', 'aura-suite' ),
                    array( 'status' => 403 )
                );
            }
        }

        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            array(
                'status'              => self::STATUS_CANCELLED,
                'cancellation_reason' => sanitize_textarea_field( $reason ),
                'updated_at'          => current_time( 'mysql' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Restaurar status del vehículo
        $wpdb->update(
            $wpdb->prefix . self::TABLE_VEHICLE,
            array( 'status' => 'available', 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $trip->vehicle_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        Aura_Vehicle_Audit_Manager::log( 'trip_cancel', 'trip', $id, array(
            'vehicle_id' => $trip->vehicle_id,
            'reason'     => $reason,
        ) );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Editar una salida activa.
     * Aplica permisos: exits_edit_own solo puede editar la suya; exits_edit_all cualquiera.
     *
     * @param  int   $id   ID del trip.
     * @param  array $data Datos a actualizar.
     * @return bool|WP_Error
     */
    public static function update( int $id, array $data ) {
        global $wpdb;

        $trip = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE id = %d AND deleted = 0",
            $id
        ) );

        if ( ! $trip ) {
            return new WP_Error( 'not_found', __( 'Salida no encontrada.', 'aura-suite' ) );
        }

        if ( self::STATUS_ACTIVE !== $trip->status ) {
            return new WP_Error( 'not_active', __( 'Solo se pueden editar salidas activas.', 'aura-suite' ) );
        }

        // Verificar permisos de edición
        $current_user = get_current_user_id();
        $can_edit_all = current_user_can( 'aura_vehicles_exits_edit_all' ) || current_user_can( 'manage_options' );
        $can_edit_own = current_user_can( 'aura_vehicles_exits_edit_own' );

        if ( ! $can_edit_all ) {
            if ( ! $can_edit_own || (int) $trip->created_by !== $current_user ) {
                return new WP_Error( 'permission_denied', __( 'No tienes permiso para editar esta salida.', 'aura-suite' ), array( 'status' => 403 ) );
            }
        }

        $row = self::sanitize_fields( $data, $trip->trip_type );
        $row['updated_at'] = current_time( 'mysql' );

        // No permitir cambiar vehicle_id o trip_type en edición
        unset( $row['vehicle_id'], $row['trip_type'], $row['status'], $row['created_by'], $row['created_at'], $row['deleted'] );

        if ( empty( $row ) ) {
            return true;
        }

        $result = $wpdb->update(
            $wpdb->prefix . self::TABLE,
            $row,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );

        Aura_Vehicle_Audit_Manager::log( 'trip_update', 'trip', $id, array(
            'changes' => array_keys( $row ),
        ) );

        return false !== $result;
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR (SOFT DELETE)
    // ─────────────────────────────────────────────────────────────

    /**
     * Eliminar suavemente una salida (solo no-activas).
     *
     * @param  int $id ID del trip.
     * @return bool|WP_Error
     */
    public static function delete( int $id ) {
        global $wpdb;

        $trip = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status, created_by FROM {$wpdb->prefix}" . self::TABLE . " WHERE id = %d AND deleted = 0",
            $id
        ) );

        if ( ! $trip ) {
            return new WP_Error( 'not_found', __( 'Salida no encontrada.', 'aura-suite' ) );
        }

        if ( self::STATUS_ACTIVE === $trip->status ) {
            return new WP_Error( 'still_active', __( 'No se puede eliminar una salida activa. Cancélela primero.', 'aura-suite' ) );
        }

        $current_user = get_current_user_id();
        $can_delete_all = current_user_can( 'aura_vehicles_exits_delete_all' )
            || current_user_can( 'aura_vehicles_delete' )
            || current_user_can( 'manage_options' );
        $can_delete_own = current_user_can( 'aura_vehicles_exits_delete_own' );

        if ( ! $can_delete_all ) {
            if ( ! $can_delete_own || (int) $trip->created_by !== (int) $current_user ) {
                return new WP_Error(
                    'permission_denied',
                    __( 'No tienes permiso para eliminar esta salida.', 'aura-suite' ),
                    array( 'status' => 403 )
                );
            }
        }

        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            array( 'deleted' => 1, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        Aura_Vehicle_Audit_Manager::log( 'trip_delete', 'trip', $id, array() );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // OBTENER UNO
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener una salida por ID.
     *
     * @param  int $id
     * @return array|null
     */
    public static function get( int $id ): ?array {
        global $wpdb;

        $trip = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, v.plate, v.brand, v.model,
                    u.display_name AS created_by_name,
                    au.display_name AS assigned_to_name
             FROM {$wpdb->prefix}" . self::TABLE . " t
             LEFT JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
             LEFT JOIN {$wpdb->users} u  ON u.ID  = t.created_by
             LEFT JOIN {$wpdb->users} au ON au.ID = t.assigned_to
             WHERE t.id = %d AND t.deleted = 0",
            $id
        ), ARRAY_A );

        if ( ! $trip ) {
            return null;
        }

        return self::decode_json_fields( $trip );
    }

    // ─────────────────────────────────────────────────────────────
    // LISTAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener listado de salidas con filtros y paginación.
     *
     * @param  array $filters Opciones: type, status, vehicle_id, area_id, date_from, date_to, page, per_page.
     * @return array { items: [], total: int, pages: int }
     */
    public static function get_list( array $filters = array() ): array {
        global $wpdb;

        $table    = $wpdb->prefix . self::TABLE;
        $page     = max( 1, absint( $filters['page']     ?? 1 ) );
        $per_page = min( 200, max( 1, absint( $filters['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where   = array( 't.deleted = 0' );
        $prepare = array();

        if ( ! empty( $filters['type'] ) ) {
            $where[]   = 't.trip_type = %s';
            $prepare[] = sanitize_text_field( $filters['type'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[]   = 't.status = %s';
            $prepare[] = sanitize_text_field( $filters['status'] );
        }

        if ( ! empty( $filters['vehicle_id'] ) ) {
            $where[]   = 't.vehicle_id = %d';
            $prepare[] = absint( $filters['vehicle_id'] );
        }

        if ( ! empty( $filters['area_id'] ) ) {
            $where[]   = 't.area_id = %d';
            $prepare[] = absint( $filters['area_id'] );
        }

        if ( ! empty( $filters['date_from'] ) ) {
            $where[]   = 'DATE(t.departure_datetime) >= %s';
            $prepare[] = sanitize_text_field( $filters['date_from'] );
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where[]   = 'DATE(t.departure_datetime) <= %s';
            $prepare[] = sanitize_text_field( $filters['date_to'] );
        }

        // CBAC: si no tiene view_all, filtrar por áreas del usuario
        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            $user_id   = get_current_user_id();
            $where[]   = '(t.area_id IN (SELECT area_id FROM ' . $wpdb->prefix . 'aura_area_users WHERE user_id = ' . (int) $user_id . ') OR t.created_by = ' . (int) $user_id . ')';
        }

        $where_sql = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$table} t WHERE {$where_sql}";
        $total     = (int) ( empty( $prepare )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, ...$prepare ) )
        );

        $sort_by  = in_array( $filters['sort_by'] ?? '', array( 'departure_datetime', 'status', 'trip_type', 'km_traveled' ), true )
            ? $filters['sort_by']
            : 'departure_datetime';
        $sort_dir = 'asc' === strtolower( $filters['sort_dir'] ?? '' ) ? 'ASC' : 'DESC';

        $list_sql = "SELECT t.*, v.plate, v.brand, v.model,
                            u.display_name AS created_by_name,
                            au.display_name AS assigned_to_name
                     FROM {$table} t
                     LEFT JOIN {$wpdb->prefix}aura_vehicles v ON v.id = t.vehicle_id
                     LEFT JOIN {$wpdb->users} u  ON u.ID = t.created_by
                     LEFT JOIN {$wpdb->users} au ON au.ID = t.assigned_to
                     WHERE {$where_sql}
                     ORDER BY t.{$sort_by} {$sort_dir}
                     LIMIT %d OFFSET %d";

        $all_prepare   = array_merge( $prepare, array( $per_page, $offset ) );
        $rows          = $wpdb->get_results(
            $wpdb->prepare( $list_sql, ...$all_prepare ),
            ARRAY_A
        ) ?: array();

        $items = array_map( array( __CLASS__, 'decode_json_fields' ), $rows );

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
            'page'  => $page,
        );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Validaciones específicas por tipo de salida.
     */
    private static function validate_by_type( string $type, array $data ): bool|WP_Error {
        switch ( $type ) {
            case self::TYPE_RENTAL:
                if ( empty( $data['client_name'] ) ) {
                    return new WP_Error( 'missing_client', __( 'El nombre del cliente es obligatorio para rentas.', 'aura-suite' ) );
                }
                break;
            case self::TYPE_ERRAND:
                if ( empty( $data['responsible_name'] ) ) {
                    return new WP_Error( 'missing_responsible', __( 'El nombre del responsable es obligatorio.', 'aura-suite' ) );
                }
                break;
            case self::TYPE_MAINTENANCE:
                if ( empty( $data['maint_description'] ) ) {
                    return new WP_Error( 'missing_maint_desc', __( 'La descripción del mantenimiento es obligatoria.', 'aura-suite' ) );
                }
                break;
        }
        return true;
    }

    /**
     * Sanitizar campos del trip según tipo.
     * Solo incluye campos presentes en $data para permitir actualizaciones parciales.
     */
    private static function sanitize_fields( array $data, string $trip_type = '' ): array {
        $row = array();

        // Campos comunes
        $text_fields = array(
            'cancellation_reason',
            'destination', 'purpose', 'trip_description', 'responsible_name',
            'client_name', 'client_phone', 'client_email', 'client_document',
            'maint_provider', 'maint_contact', 'maint_description',
            'maint_completion_notes',
        );

        foreach ( $text_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $row[ $field ] = sanitize_text_field( $data[ $field ] ?? '' );
            }
        }

        if ( array_key_exists( 'trip_description', $data ) ) {
            $row['trip_description'] = sanitize_textarea_field( $data['trip_description'] ?? '' );
        }
        if ( array_key_exists( 'maint_description', $data ) ) {
            $row['maint_description'] = sanitize_textarea_field( $data['maint_description'] ?? '' );
        }
        if ( array_key_exists( 'maint_completion_notes', $data ) ) {
            $row['maint_completion_notes'] = sanitize_textarea_field( $data['maint_completion_notes'] ?? '' );
        }

        // Fechas datetime-local (HTML) -> DATETIME MySQL
        if ( array_key_exists( 'departure_datetime', $data ) ) {
            $normalized = self::normalize_datetime_input( $data['departure_datetime'] ?? '' );
            if ( $normalized ) {
                $row['departure_datetime'] = $normalized;
            }
        }
        if ( array_key_exists( 'return_datetime', $data ) ) {
            $normalized = self::normalize_datetime_input( $data['return_datetime'] ?? '' );
            if ( $normalized ) {
                $row['return_datetime'] = $normalized;
            }
        }

        // Campos numéricos
        $int_fields = array( 'departure_odometer', 'return_odometer', 'area_id', 'assigned_to',
                             'departure_fuel', 'return_fuel', 'km_traveled' );
        foreach ( $int_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $row[ $field ] = absint( $data[ $field ] );
            }
        }

        $decimal_fields = array( 'rate_per_km', 'total_amount', 'additional_charges', 'discounts',
                                 'total_expenses', 'maint_estimated_cost', 'maint_actual_cost' );
        foreach ( $decimal_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $row[ $field ] = (float) $data[ $field ];
            }
        }

        // ENUMs
        $maint_subtypes   = array( 'preventive', 'corrective', 'inspection', '' );
        $maint_priorities = array( 'low', 'medium', 'high', 'critical', '' );

        if ( array_key_exists( 'maint_subtype', $data ) ) {
            $val = sanitize_text_field( $data['maint_subtype'] ?? '' );
            $row['maint_subtype'] = in_array( $val, $maint_subtypes, true ) ? $val : 'preventive';
        }

        if ( array_key_exists( 'maint_priority', $data ) ) {
            $val = sanitize_text_field( $data['maint_priority'] ?? '' );
            $row['maint_priority'] = in_array( $val, $maint_priorities, true ) ? $val : 'medium';
        }

        // JSON — expenses_detail
        if ( array_key_exists( 'expenses_detail', $data ) && is_array( $data['expenses_detail'] ) ) {
            $row['expenses_detail'] = wp_json_encode( $data['expenses_detail'] );
        }

        // email con sanitize_email
        if ( array_key_exists( 'client_email', $data ) ) {
            $row['client_email'] = sanitize_email( $data['client_email'] ?? '' );
        }

        return $row;
    }

    /**
     * Decodificar campos JSON de una fila de BD.
     */
    private static function decode_json_fields( array $row ): array {
        foreach ( array( 'expenses_detail' ) as $field ) {
            if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ) {
                $decoded = json_decode( $row[ $field ], true );
                $row[ $field ] = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : array();
            }
        }
        return $row;
    }

    /**
     * Normaliza entradas de fecha/hora a DATETIME MySQL (Y-m-d H:i:s).
     * Acepta formatos de input datetime-local (Y-m-dTH:i[:s]) y variantes comunes.
     */
    private static function normalize_datetime_input( $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $value = trim( $value );
        if ( '' === $value ) {
            return null;
        }

        if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
            return sprintf( '%s %s:%s:%s', $m[1], $m[2], $m[3], isset( $m[4] ) && '' !== $m[4] ? $m[4] : '00' );
        }

        $ts = strtotime( $value );
        if ( false === $ts ) {
            return null;
        }

        return wp_date( 'Y-m-d H:i:s', $ts, wp_timezone() );
    }
}
