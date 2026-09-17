<?php
/**
 * Gestión de Calificaciones Académicas — Módulo de Calendario
 *
 * Registro y ponderación de notas (parciales, finales, tareas, proyectos, participación)
 * por materia y estudiante, con cálculo de promedios ponderados y retroalimentación docente.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Grades {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_grades',        [ __CLASS__, 'ajax_get_grades' ] );
        add_action( 'wp_ajax_aura_cal_save_grade',         [ __CLASS__, 'ajax_save_grade' ] );
        add_action( 'wp_ajax_aura_cal_delete_grade',       [ __CLASS__, 'ajax_delete_grade' ] );
        add_action( 'wp_ajax_aura_cal_get_student_report', [ __CLASS__, 'ajax_get_student_report' ] );
    }

    /**
     * Obtener calificaciones de una materia en un programa
     *
     * @param int $program_id
     * @param int $subject_id
     * @return array
     */
    public static function get_grades( int $program_id, int $subject_id ): array {
        global $wpdb;
        $table_grd  = $wpdb->prefix . 'aura_cal_grades';
        $table_stud = $wpdb->prefix . 'aura_students';
        $table_enr  = $wpdb->prefix . 'aura_students_enrollments';

        $has_students = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;
        if ( ! $has_students ) {
            return [];
        }

        // Obtener estudiantes inscritos
        $students_sql = "SELECT st.id AS student_id, st.first_name, st.last_name, COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, st.email
                         FROM {$table_enr} enr
                         JOIN {$table_stud} st ON st.id = enr.student_id
                         WHERE enr.course_id = %d AND enr.status = 'active'
                         ORDER BY st.last_name ASC, st.first_name ASC";
        $students = $wpdb->get_results( $wpdb->prepare( $students_sql, $program_id ) );

        if ( empty( $students ) ) {
            $fallback_sql = "SELECT st.id AS student_id, st.first_name, st.last_name, COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, st.email
                             FROM {$table_stud} st
                             WHERE st.status IN ('active', 'approved', 'applicant')
                             ORDER BY st.last_name ASC, st.first_name ASC";
            $students = $wpdb->get_results( $fallback_sql );
        }

        // Obtener todas las calificaciones registradas para esta materia
        $grades_sql = "SELECT g.*, u.display_name AS graded_by_name
                       FROM {$table_grd} g
                       LEFT JOIN {$wpdb->users} u ON u.ID = g.graded_by
                       WHERE g.program_id = %d AND g.subject_id = %d
                       ORDER BY g.graded_at ASC, g.id ASC";
        $all_grades = $wpdb->get_results( $wpdb->prepare( $grades_sql, $program_id, $subject_id ) );

        $grades_by_student = [];
        foreach ( $all_grades as $g ) {
            $grades_by_student[ $g->student_id ][] = $g;
        }

        // Estructurar reporte con promedios ponderados
        $roster_with_grades = [];
        foreach ( $students as $st ) {
            $st_grades   = $grades_by_student[ $st->student_id ] ?? [];
            $total_weight = 0.0;
            $weighted_sum = 0.0;

            foreach ( $st_grades as $sg ) {
                $w = floatval( $sg->weight );
                $s = floatval( $sg->score );
                $m = floatval( $sg->max_score ) ?: 100.0;
                $normalized = ( $s / $m ) * 100.0;

                $weighted_sum += ( $normalized * $w );
                $total_weight += $w;
            }

            $final_average = $total_weight > 0 ? round( $weighted_sum / $total_weight, 2 ) : null;

            $roster_with_grades[] = [
                'student'       => $st,
                'grades'        => $st_grades,
                'final_average' => $final_average,
            ];
        }

        return $roster_with_grades;
    }

    /**
     * Obtener el boletín de un estudiante
     *
     * @param int $student_id
     * @param int|null $program_id
     * @return array
     */
    public static function get_student_report( int $student_id, ?int $program_id = null ): array {
        global $wpdb;
        $table_grd  = $wpdb->prefix . 'aura_cal_grades';
        $table_prog = $wpdb->prefix . 'aura_cal_programs';
        $table_subj = $wpdb->prefix . 'aura_cal_subjects';

        $where  = [ 'g.student_id = %d' ];
        $params = [ $student_id ];

        if ( ! empty( $program_id ) ) {
            $where[]  = 'g.program_id = %d';
            $params[] = $program_id;
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT g.*, p.name AS program_name, p.code AS program_code,
                       s.name AS subject_name, s.code AS subject_code,
                       u.display_name AS graded_by_name
                FROM {$table_grd} g
                JOIN {$table_prog} p ON p.id = g.program_id
                JOIN {$table_subj} s ON s.id = g.subject_id
                LEFT JOIN {$wpdb->users} u ON u.ID = g.graded_by
                WHERE {$where_sql}
                ORDER BY p.name ASC, s.name ASC, g.id ASC";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        return is_array( $results ) ? $results : [];
    }

    /**
     * Guardar o actualizar una calificación
     *
     * @param array $data
     * @return int|WP_Error
     */
    public static function save( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_grades';

        $id         = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;
        $program_id = ! empty( $data['program_id'] ) ? intval( $data['program_id'] ) : 0;
        $subject_id = ! empty( $data['subject_id'] ) ? intval( $data['subject_id'] ) : 0;
        $student_id = ! empty( $data['student_id'] ) ? intval( $data['student_id'] ) : 0;

        if ( ! $program_id || ! $subject_id || ! $student_id ) {
            return new WP_Error( 'missing_keys', __( 'Programa, materia y estudiante son requeridos.', 'aura' ) );
        }

        $eval_title = sanitize_text_field( $data['eval_title'] ?? '' );
        if ( empty( $eval_title ) ) {
            return new WP_Error( 'missing_title', __( 'El título de la evaluación es requerido.', 'aura' ) );
        }

        $eval_type = in_array( $data['eval_type'] ?? '', [ 'partial', 'final', 'task', 'project', 'participation', 'other' ], true ) ? $data['eval_type'] : 'partial';
        $score     = floatval( $data['score'] ?? 0 );
        $max_score = floatval( $data['max_score'] ?? 100 );
        $weight    = floatval( $data['weight'] ?? 1.0 );
        $feedback  = sanitize_textarea_field( $data['feedback'] ?? '' );
        $user_id   = get_current_user_id();
        $now       = current_time( 'mysql' );

        $fields = [
            'program_id' => $program_id,
            'subject_id' => $subject_id,
            'student_id' => $student_id,
            'eval_title' => $eval_title,
            'eval_type'  => $eval_type,
            'weight'     => $weight,
            'score'      => $score,
            'max_score'  => $max_score,
            'feedback'   => $feedback,
            'graded_by'  => $user_id,
            'graded_at'  => $now,
            'updated_at' => $now,
        ];

        $formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%s', '%s' ];

        if ( $id > 0 ) {
            $wpdb->update( $table, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
            return $id;
        } else {
            $wpdb->insert( $table, $fields, $formats );
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Eliminar una nota
     *
     * @param int $id
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_cal_grades';
        $res   = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        return $res !== false;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_grades(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_view_grades' ) && ! current_user_can( 'aura_cal_manage_grades' ) && ! current_user_can( 'aura_record_grades' ) && ! current_user_can( 'aura_view_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $program_id = intval( $_POST['program_id'] ?? 0 );
        $subject_id = intval( $_POST['subject_id'] ?? 0 );

        if ( ! $program_id || ! $subject_id ) {
            wp_send_json_error( [ 'message' => __( 'Parámetros inválidos.', 'aura' ) ] );
        }

        $matrix = self::get_grades( $program_id, $subject_id );
        wp_send_json_success( [ 'matrix' => $matrix ] );
    }

    public static function ajax_save_grade(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_grades' ) && ! current_user_can( 'aura_record_grades' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para registrar notas.', 'aura' ) ] );
        }

        $res = self::save( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'  => __( 'Calificación guardada con éxito.', 'aura' ),
            'grade_id' => $res,
        ] );
    }

    public static function ajax_delete_grade(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_grades' ) && ! current_user_can( 'aura_record_grades' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::delete( $id );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Calificación eliminada.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar la calificación.', 'aura' ) ] );
        }
    }

    public static function ajax_get_student_report(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $student_id = intval( $_POST['student_id'] ?? 0 );
        $program_id = ! empty( $_POST['program_id'] ) ? intval( $_POST['program_id'] ) : null;

        // Si es el propio estudiante consultando su boletín o un rol con gestión de calificaciones
        if ( ! current_user_can( 'aura_cal_manage_grades' ) && ! current_user_can( 'aura_record_grades' ) && ! current_user_can( 'manage_options' ) ) {
            // Validar que el estudiante corresponda al usuario actual
            global $wpdb;
            $table_stud = $wpdb->prefix . 'aura_students';
            $curr_st_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_stud} WHERE wp_user_id = %d",
                get_current_user_id()
            ) );
            if ( (int) $curr_st_id !== $student_id ) {
                wp_send_json_error( [ 'message' => __( 'No autorizado.', 'aura' ) ] );
            }
        }

        $report = self::get_student_report( $student_id, $program_id );
        wp_send_json_success( [ 'report' => $report ] );
    }
}
