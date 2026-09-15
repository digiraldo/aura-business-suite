<?php
/**
 * Control de Asistencia — Módulo de Calendario
 *
 * Permite a los profesores y directivos registrar y consultar la asistencia
 * de los estudiantes inscritos en el programa a cada clase o evento académico.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Attendance {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_attendance_roster', [ __CLASS__, 'ajax_get_attendance_roster' ] );
        add_action( 'wp_ajax_aura_cal_save_attendance',        [ __CLASS__, 'ajax_save_attendance' ] );
    }

    /**
     * Obtener la lista de estudiantes inscritos para un evento y su asistencia actual
     *
     * @param int $event_id ID del evento
     * @return array
     */
    public static function get_event_roster( int $event_id ): array {
        global $wpdb;
        $table_evts   = $wpdb->prefix . 'aura_cal_events';
        $table_prog   = $wpdb->prefix . 'aura_cal_programs';
        $table_att    = $wpdb->prefix . 'aura_cal_attendance';
        $table_stud   = $wpdb->prefix . 'aura_students';
        $table_enr    = $wpdb->prefix . 'aura_students_enrollments';

        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.id, e.program_id, e.subject_id, e.title, e.start_datetime, p.name AS program_name
             FROM {$table_evts} e
             JOIN {$table_prog} p ON p.id = e.program_id
             WHERE e.id = %d AND e.deleted_at IS NULL",
            $event_id
        ) );

        if ( ! $event ) {
            return [];
        }

        // Verificar si existe la tabla de estudiantes
        $has_students = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;
        if ( ! $has_students ) {
            return [];
        }

        // Obtener estudiantes inscritos activos en el programa
        $sql = "SELECT st.id AS student_id, st.first_name, st.last_name, COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, st.email,
                       att.id AS attendance_id, att.status AS attendance_status, att.notes AS attendance_notes,
                       att.recorded_at
                FROM {$table_enr} enr
                JOIN {$table_stud} st ON st.id = enr.student_id
                LEFT JOIN {$table_att} att ON att.event_id = %d AND att.student_id = st.id
                WHERE enr.course_id = %d AND enr.status = 'active'
                ORDER BY st.last_name ASC, st.first_name ASC";

        $roster = $wpdb->get_results( $wpdb->prepare( $sql, $event_id, $event->program_id ) );

        // Si no hay inscripciones por course_id directo, buscar estudiantes activos/aprobados o que ya tengan asistencia registrada
        if ( empty( $roster ) ) {
            $fallback_sql = "SELECT st.id AS student_id, st.first_name, st.last_name, COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, st.email,
                                    att.id AS attendance_id, att.status AS attendance_status, att.notes AS attendance_notes,
                                    att.recorded_at
                             FROM {$table_stud} st
                             LEFT JOIN {$table_att} att ON att.event_id = %d AND att.student_id = st.id
                             WHERE st.status IN ('active', 'approved', 'applicant') OR att.id IS NOT NULL
                             ORDER BY st.last_name ASC, st.first_name ASC";
            $roster = $wpdb->get_results( $wpdb->prepare( $fallback_sql, $event_id ) );
        }

        return [
            'event'  => $event,
            'roster' => is_array( $roster ) ? $roster : [],
        ];
    }

    /**
     * Guardar asistencia en lote para un evento
     *
     * @param int $event_id
     * @param array $records Array de [ 'student_id' => int, 'status' => string, 'notes' => string ]
     * @return bool
     */
    public static function save_attendance( int $event_id, array $records ): bool {
        global $wpdb;
        $table_att = $wpdb->prefix . 'aura_cal_attendance';

        $valid_statuses = [ 'present', 'late', 'absent_unexcused', 'absent_excused' ];
        $user_id        = get_current_user_id();
        $now            = current_time( 'mysql' );

        foreach ( $records as $rec ) {
            $student_id = intval( $rec['student_id'] ?? 0 );
            $status     = in_array( $rec['status'] ?? '', $valid_statuses, true ) ? $rec['status'] : 'present';
            $notes      = sanitize_text_field( $rec['notes'] ?? '' );

            if ( ! $student_id ) {
                continue;
            }

            // Usar ON DUPLICATE KEY UPDATE mediante REPLACE o INSERT con clave única (event_id, student_id)
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_att} WHERE event_id = %d AND student_id = %d",
                $event_id,
                $student_id
            ) );

            if ( $existing_id ) {
                $wpdb->update(
                    $table_att,
                    [
                        'status'      => $status,
                        'notes'       => $notes,
                        'recorded_by' => $user_id,
                        'updated_at'  => $now,
                    ],
                    [ 'id' => $existing_id ],
                    [ '%s', '%s', '%d', '%s' ],
                    [ '%d' ]
                );
            } else {
                $wpdb->insert(
                    $table_att,
                    [
                        'event_id'    => $event_id,
                        'student_id'  => $student_id,
                        'status'      => $status,
                        'notes'       => $notes,
                        'recorded_by' => $user_id,
                        'recorded_at' => $now,
                        'updated_at'  => $now,
                    ],
                    [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ]
                );
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_attendance_roster(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_take_attendance' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para tomar asistencia.', 'aura' ) ] );
        }

        $event_id = intval( $_POST['event_id'] ?? 0 );
        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de evento inválido.', 'aura' ) ] );
        }

        $data = self::get_event_roster( $event_id );
        wp_send_json_success( $data );
    }

    public static function ajax_save_attendance(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_take_attendance' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $event_id = intval( $_POST['event_id'] ?? 0 );
        $records  = $_POST['records'] ?? [];

        if ( ! $event_id || ! is_array( $records ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos de asistencia inválidos.', 'aura' ) ] );
        }

        $ok = self::save_attendance( $event_id, $records );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Asistencia guardada correctamente.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al guardar la asistencia.', 'aura' ) ] );
        }
    }
}
