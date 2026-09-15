<?php
/**
 * Gestión de Eventos y Horarios Académicos — Módulo de Calendario
 *
 * CRUD de clases, talleres, evaluaciones y actividades con soporte para
 * recurrencia simplificada por días de la semana, sincronización con Google Calendar,
 * asignación de múltiples instructores y filtrado según permisos de rol (CBAC).
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Events {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_events',          [ __CLASS__, 'ajax_get_events' ] );
        add_action( 'wp_ajax_aura_cal_get_event',           [ __CLASS__, 'ajax_get_event' ] );
        add_action( 'wp_ajax_aura_cal_save_event',          [ __CLASS__, 'ajax_save_event' ] );
        add_action( 'wp_ajax_aura_cal_delete_event',        [ __CLASS__, 'ajax_delete_event' ] );
        add_action( 'wp_ajax_aura_cal_update_event_dates',  [ __CLASS__, 'ajax_update_event_dates' ] );
    }

    /**
     * Obtener eventos formateados para FullCalendar v6
     *
     * @param array $filters (start, end, program_id, subject_id, teacher_id, event_type, status)
     * @param int|null $user_id Usuario actual para filtrado CBAC (default: current_user)
     * @return array
     */
    public static function get_events( array $filters = [], ?int $user_id = null ): array {
        global $wpdb;
        $table_evts        = $wpdb->prefix . 'aura_cal_events';
        $table_prog        = $wpdb->prefix . 'aura_cal_programs';
        $table_subj        = $wpdb->prefix . 'aura_cal_subjects';
        $table_inst        = $wpdb->prefix . 'aura_cal_event_instructors';
        $table_att         = $wpdb->prefix . 'aura_cal_attendance';

        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $where  = [ 'e.deleted_at IS NULL' ];
        $params = [];

        // Rango de fechas (FullCalendar envía start y end en ISO)
        if ( ! empty( $filters['start'] ) ) {
            $start_dt = gmdate( 'Y-m-d H:i:s', strtotime( $filters['start'] ) );
            $where[]  = 'e.end_datetime >= %s';
            $params[] = $start_dt;
        }

        if ( ! empty( $filters['end'] ) ) {
            $end_dt   = gmdate( 'Y-m-d H:i:s', strtotime( $filters['end'] ) );
            $where[]  = 'e.start_datetime <= %s';
            $params[] = $end_dt;
        }

        if ( ! empty( $filters['program_id'] ) ) {
            $where[]  = 'e.program_id = %d';
            $params[] = intval( $filters['program_id'] );
        }

        if ( ! empty( $filters['subject_id'] ) ) {
            $where[]  = 'e.subject_id = %d';
            $params[] = intval( $filters['subject_id'] );
        }

        if ( ! empty( $filters['event_type'] ) ) {
            $where[]  = 'e.event_type = %s';
            $params[] = sanitize_text_field( $filters['event_type'] );
        }

        if ( ! empty( $filters['status'] ) ) {
            $where[]  = 'e.status = %s';
            $params[] = sanitize_text_field( $filters['status'] );
        }

        // Filtro específico por profesor
        if ( ! empty( $filters['teacher_id'] ) ) {
            $where[]  = "EXISTS (SELECT 1 FROM {$table_inst} ei_filter WHERE ei_filter.event_id = e.id AND ei_filter.teacher_id = %d)";
            $params[] = intval( $filters['teacher_id'] );
        }

        // ── FILTRADO CBAC SEGÚN ROL DEL USUARIO ──
        $is_admin = current_user_can( 'manage_options' ) || current_user_can( 'aura_manage_calendar' );
        if ( ! $is_admin && $user_id > 0 ) {
            $is_teacher = current_user_can( 'aura_teach_calendar' );
            $is_student = current_user_can( 'aura_student_portal_access' );

            if ( $is_teacher && ! $is_student ) {
                // El profesor ve sólo los eventos donde está asignado como instructor o coordina el programa
                $where[]  = "(EXISTS (SELECT 1 FROM {$table_inst} ei_cbac WHERE ei_cbac.event_id = e.id AND ei_cbac.teacher_id = %d) OR p.coordinator_id = %d)";
                $params[] = $user_id;
                $params[] = $user_id;
            } elseif ( $is_student ) {
                // El estudiante ve los eventos de los programas donde está inscrito activamente
                $table_enroll = $wpdb->prefix . 'aura_students_enrollments';
                $table_stud   = $wpdb->prefix . 'aura_students';
                // Comprobamos si las tablas de estudiantes existen
                $has_students = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;

                if ( $has_students ) {
                    $where[] = "e.program_id IN (
                        SELECT enr.course_id FROM {$table_enroll} enr
                        JOIN {$table_stud} st ON st.id = enr.student_id
                        WHERE st.wp_user_id = %d AND enr.status = 'active'
                    )";
                    $params[] = $user_id;
                }
            }
        }

        $where_sql = implode( ' AND ', $where );

        $sql = "SELECT e.*,
                       p.name AS program_name, p.code AS program_code, p.color AS program_color,
                       s.name AS subject_name, s.code AS subject_code, s.color AS subject_color,
                       (SELECT COUNT(*) FROM {$table_att} a WHERE a.event_id = e.id) AS attendance_count
                FROM {$table_evts} e
                LEFT JOIN {$table_prog} p ON p.id = e.program_id
                LEFT JOIN {$table_subj} s ON s.id = e.subject_id
                WHERE {$where_sql}
                ORDER BY e.start_datetime ASC";

        $rows = ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            return [];
        }

        // Obtener instructores de todos los eventos encontrados en una sola consulta
        $event_ids = wp_list_pluck( $rows, 'id' );
        $instructors_by_event = [];
        if ( ! empty( $event_ids ) ) {
            $ids_placeholder = implode( ',', array_map( 'intval', $event_ids ) );
            $inst_rows = $wpdb->get_results(
                "SELECT ei.*, u.display_name, u.user_email
                 FROM {$table_inst} ei
                 JOIN {$wpdb->users} u ON u.ID = ei.teacher_id
                 WHERE ei.event_id IN ({$ids_placeholder})"
            );
            foreach ( $inst_rows as $ir ) {
                $instructors_by_event[ $ir->event_id ][] = [
                    'id'    => (int) $ir->teacher_id,
                    'name'  => $ir->display_name,
                    'email' => $ir->user_email,
                    'role'  => $ir->role,
                ];
            }
        }

        // Mapear al formato esperado por FullCalendar
        $fc_events = [];
        foreach ( $rows as $row ) {
            // Determinar color de fondo
            $bg_color = ! empty( $row->color ) ? $row->color : ( ! empty( $row->subject_color ) ? $row->subject_color : ( ! empty( $row->program_color ) ? $row->program_color : '#6366f1' ) );

            // Si está cancelado, atenuar visualmente
            if ( $row->status === 'cancelled' ) {
                $bg_color = '#94a3b8';
            }

            // Título para FullCalendar
            $title = $row->title;
            if ( ! empty( $row->subject_name ) ) {
                $title = $row->subject_name . ' - ' . $title;
            }

            $inst_list = $instructors_by_event[ $row->id ] ?? [];

            $fc_events[] = [
                'id'              => (string) $row->id,
                'title'           => $title,
                'start'           => $row->start_datetime,
                'end'             => $row->end_datetime,
                'backgroundColor' => $bg_color,
                'borderColor'     => $bg_color,
                'textColor'       => '#ffffff',
                'extendedProps'   => [
                    'raw_title'           => $row->title,
                    'program_id'          => (int) $row->program_id,
                    'program_name'        => $row->program_name,
                    'program_code'        => $row->program_code,
                    'subject_id'          => (int) $row->subject_id,
                    'subject_name'        => $row->subject_name,
                    'subject_code'        => $row->subject_code,
                    'event_type'          => $row->event_type,
                    'location'            => $row->location,
                    'online_url'          => $row->online_url,
                    'status'              => $row->status,
                    'description'         => $row->description,
                    'recurrence_group_id' => $row->recurrence_group_id,
                    'gcal_event_id'       => $row->gcal_event_id,
                    'gcal_sync_status'    => $row->gcal_sync_status,
                    'attendance_taken'    => intval( $row->attendance_count ) > 0,
                    'instructors'         => $inst_list,
                ],
            ];
        }

        return $fc_events;
    }

    /**
     * Obtener el detalle de un evento por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $table_prog = $wpdb->prefix . 'aura_cal_programs';
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';
        $table_inst = $wpdb->prefix . 'aura_cal_event_instructors';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*,
                    p.name AS program_name, p.code AS program_code,
                    s.name AS subject_name, s.code AS subject_code
             FROM {$table_evts} e
             LEFT JOIN {$table_prog} p ON p.id = e.program_id
             LEFT JOIN {$table_subj} s ON s.id = e.subject_id
             WHERE e.id = %d AND e.deleted_at IS NULL",
            $id
        ) );

        if ( ! $row ) {
            return null;
        }

        // Obtener instructores asociados
        $instructors = $wpdb->get_results( $wpdb->prepare(
            "SELECT ei.*, u.display_name, u.user_email
             FROM {$table_inst} ei
             JOIN {$wpdb->users} u ON u.ID = ei.teacher_id
             WHERE ei.event_id = %d",
            $id
        ) );

        $row->instructors = is_array( $instructors ) ? $instructors : [];

        return $row;
    }

    /**
     * Crear o actualizar un evento (soporta recurrencia simplificada)
     *
     * @param array $data Datos del formulario
     * @return array|WP_Error ['ids' => array, 'message' => string]
     */
    public static function save( array $data ) {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';
        $table_inst = $wpdb->prefix . 'aura_cal_event_instructors';

        $id           = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;
        $program_id   = ! empty( $data['program_id'] ) ? intval( $data['program_id'] ) : 0;
        $subject_id   = ! empty( $data['subject_id'] ) ? intval( $data['subject_id'] ) : null;
        $title        = sanitize_text_field( $data['title'] ?? '' );
        $event_type   = in_array( $data['event_type'] ?? '', [ 'class', 'exam', 'workshop', 'activity', 'break', 'other' ], true ) ? $data['event_type'] : 'class';
        $description  = sanitize_textarea_field( $data['description'] ?? '' );
        $location     = sanitize_text_field( $data['location'] ?? '' );
        $online_url   = esc_url_raw( $data['online_url'] ?? '' );
        $color        = sanitize_hex_color( $data['color'] ?? '' );
        $status       = in_array( $data['status'] ?? '', [ 'scheduled', 'completed', 'cancelled', 'postponed' ], true ) ? $data['status'] : 'scheduled';
        $is_recurring = ! empty( $data['is_recurring'] ) && $data['is_recurring'] === '1';

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'El título del evento es obligatorio.', 'aura' ) );
        }

        if ( ! $program_id ) {
            return new WP_Error( 'missing_program', __( 'Debe seleccionar un programa.', 'aura' ) );
        }

        // Obtener instructores seleccionados (array de IDs)
        $teacher_ids = [];
        if ( ! empty( $data['teacher_ids'] ) ) {
            if ( is_array( $data['teacher_ids'] ) ) {
                $teacher_ids = array_unique( array_filter( array_map( 'intval', $data['teacher_ids'] ) ) );
            } else {
                $teacher_ids = array_unique( array_filter( array_map( 'intval', explode( ',', $data['teacher_ids'] ) ) ) );
            }
        }

        $created_event_ids = [];

        // ── CASO A: EVENTO RECURRENTE (NUEVA SERIE) ──
        if ( $id === 0 && $is_recurring ) {
            $rec_days = isset( $data['recurring_days'] ) && is_array( $data['recurring_days'] ) ? array_map( 'intval', $data['recurring_days'] ) : [];
            if ( empty( $rec_days ) ) {
                return new WP_Error( 'missing_recurrence_days', __( 'Debe seleccionar al menos un día de la semana para la recurrencia.', 'aura' ) );
            }

            $date_start = sanitize_text_field( $data['rec_date_start'] ?? '' );
            $date_end   = sanitize_text_field( $data['rec_date_end'] ?? '' );
            $time_start = sanitize_text_field( $data['rec_time_start'] ?? '' );
            $time_end   = sanitize_text_field( $data['rec_time_end'] ?? '' );

            if ( empty( $date_start ) || empty( $date_end ) || empty( $time_start ) || empty( $time_end ) ) {
                return new WP_Error( 'missing_recurrence_dates', __( 'Debe completar el rango de fechas y horas para la serie recurrente.', 'aura' ) );
            }

            $ts_current = strtotime( $date_start );
            $ts_end     = strtotime( $date_end );

            if ( $ts_end < $ts_current ) {
                return new WP_Error( 'invalid_dates', __( 'La fecha fin no puede ser anterior a la fecha de inicio.', 'aura' ) );
            }

            // Generar identificador de grupo recurrente
            $recurrence_group_id = wp_generate_uuid4();
            $rule_summary = sprintf( 'Dias: %s | Horas: %s - %s', implode( ',', $rec_days ), $time_start, $time_end );

            // Límite de seguridad para evitar bucles infinitos (máx 365 días)
            $max_days = 365;
            $days_count = 0;

            while ( $ts_current <= $ts_end && $days_count < $max_days ) {
                $day_of_week = (int) date( 'N', $ts_current ); // 1 (Lunes) a 7 (Domingo)

                if ( in_array( $day_of_week, $rec_days, true ) ) {
                    $curr_date_str = date( 'Y-m-d', $ts_current );
                    $evt_start     = $curr_date_str . ' ' . $time_start . ':00';
                    $evt_end       = $curr_date_str . ' ' . $time_end . ':00';

                    $wpdb->insert(
                        $table_evts,
                        [
                            'program_id'          => $program_id,
                            'subject_id'          => $subject_id,
                            'title'               => $title,
                            'description'         => $description,
                            'event_type'          => $event_type,
                            'start_datetime'      => $evt_start,
                            'end_datetime'        => $evt_end,
                            'location'            => $location,
                            'online_url'          => $online_url,
                            'color'               => $color,
                            'status'              => $status,
                            'recurrence_group_id' => $recurrence_group_id,
                            'recurrence_rule'     => $rule_summary,
                            'gcal_sync_status'    => 'pending',
                            'created_by'          => get_current_user_id(),
                            'created_at'          => current_time( 'mysql' ),
                            'updated_at'          => current_time( 'mysql' ),
                        ],
                        [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
                    );

                    $new_evt_id = (int) $wpdb->insert_id;
                    if ( $new_evt_id > 0 ) {
                        $created_event_ids[] = $new_evt_id;

                        // Insertar instructores
                        foreach ( $teacher_ids as $tid ) {
                            $wpdb->insert(
                                $table_inst,
                                [
                                    'event_id'   => $new_evt_id,
                                    'teacher_id' => $tid,
                                    'role'       => 'lead',
                                    'created_at' => current_time( 'mysql' ),
                                ],
                                [ '%d', '%d', '%s', '%s' ]
                            );
                        }
                    }
                }

                $ts_current = strtotime( '+1 day', $ts_current );
                $days_count++;
            }

            // Sincronizar con Google Calendar en lote (primeros 20 inmediatos, resto batch)
            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                $sync_batch = array_slice( $created_event_ids, 0, 20 );
                foreach ( $sync_batch as $sid ) {
                    Aura_Calendar_Google_Sync::sync_event( $sid );
                }
            }

            return [
                'ids'     => $created_event_ids,
                'count'   => count( $created_event_ids ),
                'message' => sprintf( __( 'Se crearon con éxito %d clases en la serie recurrente.', 'aura' ), count( $created_event_ids ) ),
            ];
        }

        // ── CASO B: EVENTO ÚNICO (CREACIÓN O ACTUALIZACIÓN) ──
        $start_dt = sanitize_text_field( $data['start_datetime'] ?? '' );
        $end_dt   = sanitize_text_field( $data['end_datetime'] ?? '' );

        if ( empty( $start_dt ) || empty( $end_dt ) ) {
            return new WP_Error( 'missing_dates', __( 'Debe indicar fecha/hora de inicio y fin.', 'aura' ) );
        }

        $fields = [
            'program_id'     => $program_id,
            'subject_id'     => $subject_id,
            'title'          => $title,
            'description'    => $description,
            'event_type'     => $event_type,
            'start_datetime' => $start_dt,
            'end_datetime'   => $end_dt,
            'location'       => $location,
            'online_url'     => $online_url,
            'color'          => $color,
            'status'         => $status,
            'updated_at'     => current_time( 'mysql' ),
        ];

        $formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

        if ( $id > 0 ) {
            // Actualizar evento existente
            $wpdb->update( $table_evts, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
            $event_id = $id;

            // Re-asignar instructores
            $wpdb->delete( $table_inst, [ 'event_id' => $event_id ], [ '%d' ] );
            foreach ( $teacher_ids as $tid ) {
                $wpdb->insert(
                    $table_inst,
                    [
                        'event_id'   => $event_id,
                        'teacher_id' => $tid,
                        'role'       => 'lead',
                        'created_at' => current_time( 'mysql' ),
                    ],
                    [ '%d', '%d', '%s', '%s' ]
                );
            }

            // Sincronizar actualización con Google Calendar
            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                Aura_Calendar_Google_Sync::sync_event( $event_id );
            }

            return [
                'ids'     => [ $event_id ],
                'count'   => 1,
                'message' => __( 'Clase actualizada con éxito.', 'aura' ),
            ];
        } else {
            // Crear evento individual nuevo
            $fields['gcal_sync_status'] = 'pending';
            $formats[]                  = '%s';
            $fields['created_by']       = get_current_user_id();
            $formats[]                  = '%d';
            $fields['created_at']       = current_time( 'mysql' );
            $formats[]                  = '%s';

            $wpdb->insert( $table_evts, $fields, $formats );
            $event_id = (int) $wpdb->insert_id;

            foreach ( $teacher_ids as $tid ) {
                $wpdb->insert(
                    $table_inst,
                    [
                        'event_id'   => $event_id,
                        'teacher_id' => $tid,
                        'role'       => 'lead',
                        'created_at' => current_time( 'mysql' ),
                    ],
                    [ '%d', '%d', '%s', '%s' ]
                );
            }

            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                Aura_Calendar_Google_Sync::sync_event( $event_id );
            }

            return [
                'ids'     => [ $event_id ],
                'count'   => 1,
                'message' => __( 'Clase agendada con éxito.', 'aura' ),
            ];
        }
    }

    /**
     * Eliminar evento (y opcionalmente toda la serie recurrente)
     *
     * @param int $id ID del evento
     * @param bool $delete_series Si true y pertenece a una serie, elimina todos los eventos de la serie
     * @return bool
     */
    public static function delete( int $id, bool $delete_series = false ): bool {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';

        $event = $wpdb->get_row( $wpdb->prepare( "SELECT id, recurrence_group_id FROM {$table_evts} WHERE id = %d", $id ) );
        if ( ! $event ) {
            return false;
        }

        $ids_to_delete = [ $id ];

        if ( $delete_series && ! empty( $event->recurrence_group_id ) ) {
            $series_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$table_evts} WHERE recurrence_group_id = %s AND deleted_at IS NULL",
                $event->recurrence_group_id
            ) );
            if ( ! empty( $series_ids ) ) {
                $ids_to_delete = array_map( 'intval', $series_ids );
            }
        }

        foreach ( $ids_to_delete as $del_id ) {
            // Eliminar de Google Calendar
            if ( Aura_Calendar_Google_Sync::is_enabled() ) {
                Aura_Calendar_Google_Sync::delete_event( $del_id );
            }

            // Soft delete en BD
            $wpdb->update(
                $table_evts,
                [
                    'deleted_at' => current_time( 'mysql' ),
                    'status'     => 'cancelled',
                ],
                [ 'id' => $del_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        return true;
    }

    /**
     * Actualizar fechas por arrastre (drag & drop) o redimensionamiento
     *
     * @param int $id
     * @param string $start_dt
     * @param string $end_dt
     * @return bool
     */
    public static function update_dates( int $id, string $start_dt, string $end_dt ): bool {
        global $wpdb;
        $table_evts = $wpdb->prefix . 'aura_cal_events';

        $updated = $wpdb->update(
            $table_evts,
            [
                'start_datetime' => $start_dt,
                'end_datetime'   => $end_dt,
                'updated_at'     => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $updated !== false ) {
            if ( Aura_Calendar_Google_Sync::is_auto_sync() ) {
                Aura_Calendar_Google_Sync::sync_event( $id );
            }
            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_events(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $filters = [
            'start'      => sanitize_text_field( $_POST['start'] ?? '' ),
            'end'        => sanitize_text_field( $_POST['end'] ?? '' ),
            'program_id' => intval( $_POST['program_id'] ?? 0 ),
            'subject_id' => intval( $_POST['subject_id'] ?? 0 ),
            'teacher_id' => intval( $_POST['teacher_id'] ?? 0 ),
            'event_type' => sanitize_text_field( $_POST['event_type'] ?? '' ),
            'status'     => sanitize_text_field( $_POST['status'] ?? '' ),
        ];

        $events = self::get_events( $filters );
        wp_send_json_success( [ 'events' => $events ] );
    }

    public static function ajax_get_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $event = self::get( $id );
        if ( ! $event ) {
            wp_send_json_error( [ 'message' => __( 'Evento no encontrado.', 'aura' ) ] );
        }

        wp_send_json_success( [ 'event' => $event ] );
    }

    public static function ajax_save_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_create_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para crear o editar eventos.', 'aura' ) ] );
        }

        $res = self::save( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( $res );
    }

    public static function ajax_delete_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_delete_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para eliminar eventos.', 'aura' ) ] );
        }

        $id            = intval( $_POST['id'] ?? 0 );
        $delete_series = ! empty( $_POST['delete_series'] ) && $_POST['delete_series'] === '1';

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::delete( $id, $delete_series );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Evento retirado con éxito.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo retirar el evento.', 'aura' ) ] );
        }
    }

    public static function ajax_update_event_dates(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_edit_calendar_events' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id       = intval( $_POST['id'] ?? 0 );
        $start_dt = sanitize_text_field( $_POST['start'] ?? '' );
        $end_dt   = sanitize_text_field( $_POST['end'] ?? '' );

        if ( ! $id || empty( $start_dt ) || empty( $end_dt ) ) {
            wp_send_json_error( [ 'message' => __( 'Parámetros inválidos.', 'aura' ) ] );
        }

        $ok = self::update_dates( $id, $start_dt, $end_dt );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Horario actualizado.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo actualizar el horario.', 'aura' ) ] );
        }
    }
}
