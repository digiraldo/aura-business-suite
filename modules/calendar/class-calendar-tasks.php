<?php
/**
 * Gestión de Tareas y Entregas — Módulo de Calendario
 *
 * Publicación de tareas por materia y evento, recepción de entregas estudiantiles
 * con archivos adjuntos o texto, y retroalimentación docente con asignación de nota.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Tasks {

    /**
     * Inicializar hooks y AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_get_tasks',         [ __CLASS__, 'ajax_get_tasks' ] );
        add_action( 'wp_ajax_aura_cal_get_task',          [ __CLASS__, 'ajax_get_task' ] );
        add_action( 'wp_ajax_aura_cal_save_task',         [ __CLASS__, 'ajax_save_task' ] );
        add_action( 'wp_ajax_aura_cal_delete_task',       [ __CLASS__, 'ajax_delete_task' ] );
        add_action( 'wp_ajax_aura_cal_get_submissions',   [ __CLASS__, 'ajax_get_submissions' ] );
        add_action( 'wp_ajax_aura_cal_grade_submission',  [ __CLASS__, 'ajax_grade_submission' ] );
        add_action( 'wp_ajax_aura_cal_submit_task',       [ __CLASS__, 'ajax_submit_task' ] );
    }

    /**
     * Obtener listado de tareas con filtros
     *
     * @param array $args
     * @return array
     */
    public static function get_tasks( array $args = [] ): array {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_subj  = $wpdb->prefix . 'aura_cal_subjects';
        $table_subs  = $wpdb->prefix . 'aura_cal_task_submissions';

        $defaults = [
            'program_id' => 0,
            'subject_id' => 0,
            'status'     => '',
            'search'     => '',
            'limit'      => 100,
            'offset'     => 0,
        ];
        $r = wp_parse_args( $args, $defaults );

        $where  = [ 't.deleted_at IS NULL' ];
        $params = [];

        if ( ! empty( $r['program_id'] ) ) {
            $where[]  = 't.program_id = %d';
            $params[] = intval( $r['program_id'] );
        }

        if ( ! empty( $r['subject_id'] ) ) {
            $where[]  = 't.subject_id = %d';
            $params[] = intval( $r['subject_id'] );
        }

        if ( ! empty( $r['status'] ) ) {
            $where[]  = 't.status = %s';
            $params[] = sanitize_text_field( $r['status'] );
        }

        if ( ! empty( $r['search'] ) ) {
            $where[]  = 't.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $r['search'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT t.*, p.name AS program_name, p.code AS program_code,
                       s.name AS subject_name, s.code AS subject_code,
                       u.display_name AS author_name,
                       (SELECT COUNT(*) FROM {$table_subs} sub WHERE sub.task_id = t.id) AS submissions_count,
                       (SELECT COUNT(*) FROM {$table_subs} sub WHERE sub.task_id = t.id AND sub.status IN ('submitted','late')) AS pending_grade_count
                FROM {$table_tasks} t
                LEFT JOIN {$table_prog} p ON p.id = t.program_id
                LEFT JOIN {$table_subj} s ON s.id = t.subject_id
                LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
                WHERE {$where_sql}
                ORDER BY t.due_datetime DESC, t.id DESC
                LIMIT %d OFFSET %d";

        $params[] = intval( $r['limit'] );
        $params[] = intval( $r['offset'] );

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        return is_array( $results ) ? $results : [];
    }

    /**
     * Obtener detalle de una tarea
     *
     * @param int $id
     * @return object|null
     */
    public static function get_task( int $id ): ?object {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $table_prog  = $wpdb->prefix . 'aura_cal_programs';
        $table_subj  = $wpdb->prefix . 'aura_cal_subjects';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, p.name AS program_name, p.code AS program_code,
                    s.name AS subject_name, s.code AS subject_code,
                    u.display_name AS author_name
             FROM {$table_tasks} t
             LEFT JOIN {$table_prog} p ON p.id = t.program_id
             LEFT JOIN {$table_subj} s ON s.id = t.subject_id
             LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
             WHERE t.id = %d AND t.deleted_at IS NULL",
            $id
        ) );

        if ( $row && ! empty( $row->attachment_urls ) ) {
            $row->attachments = json_decode( $row->attachment_urls, true ) ?: [];
        } elseif ( $row ) {
            $row->attachments = [];
        }

        return $row;
    }

    /**
     * Guardar o actualizar una tarea
     *
     * @param array $data
     * @return int|WP_Error
     */
    public static function save_task( array $data ) {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';

        $id         = ! empty( $data['id'] ) ? intval( $data['id'] ) : 0;
        $program_id = ! empty( $data['program_id'] ) ? intval( $data['program_id'] ) : 0;
        $subject_id = ! empty( $data['subject_id'] ) ? intval( $data['subject_id'] ) : null;
        $event_id   = ! empty( $data['event_id'] ) ? intval( $data['event_id'] ) : null;
        $title      = sanitize_text_field( $data['title'] ?? '' );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'El título de la tarea es obligatorio.', 'aura' ) );
        }

        if ( ! $program_id ) {
            return new WP_Error( 'missing_program', __( 'Debe asociar un programa.', 'aura' ) );
        }

        $due_dt = sanitize_text_field( $data['due_datetime'] ?? '' );
        if ( empty( $due_dt ) ) {
            $due_dt = date( 'Y-m-d 23:59:59', strtotime( '+7 days' ) );
        }

        $attachments_json = null;
        if ( ! empty( $data['attachment_urls'] ) ) {
            if ( is_array( $data['attachment_urls'] ) ) {
                $clean_urls = array_map( 'esc_url_raw', $data['attachment_urls'] );
                $attachments_json = wp_json_encode( $clean_urls );
            } else {
                $attachments_json = sanitize_textarea_field( $data['attachment_urls'] );
            }
        }

        $fields = [
            'program_id'      => $program_id,
            'subject_id'      => $subject_id,
            'event_id'        => $event_id,
            'title'           => $title,
            'description'     => wp_kses_post( $data['description'] ?? '' ),
            'due_datetime'    => $due_dt,
            'max_score'       => floatval( $data['max_score'] ?? 100 ),
            'weight'          => floatval( $data['weight'] ?? 1.0 ),
            'attachment_urls' => $attachments_json,
            'status'          => in_array( $data['status'] ?? '', [ 'published', 'draft', 'closed' ], true ) ? $data['status'] : 'published',
            'updated_at'      => current_time( 'mysql' ),
        ];

        $formats = [ '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s' ];

        if ( $id > 0 ) {
            $wpdb->update( $table_tasks, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
            return $id;
        } else {
            $fields['created_by'] = get_current_user_id();
            $formats[]            = '%d';
            $fields['created_at'] = current_time( 'mysql' );
            $formats[]            = '%s';

            $wpdb->insert( $table_tasks, $fields, $formats );
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Eliminar tarea (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public static function delete_task( int $id ): bool {
        global $wpdb;
        $table_tasks = $wpdb->prefix . 'aura_cal_tasks';
        $res = $wpdb->update(
            $table_tasks,
            [ 'deleted_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );
        return $res !== false;
    }

    /**
     * Obtener entregas de una tarea
     *
     * @param int $task_id
     * @return array
     */
    public static function get_submissions( int $task_id ): array {
        global $wpdb;
        $table_subs = $wpdb->prefix . 'aura_cal_task_submissions';
        $table_stud = $wpdb->prefix . 'aura_students';

        $has_students = $wpdb->get_var( "SHOW TABLES LIKE '{$table_stud}'" ) === $table_stud;
        if ( ! $has_students ) {
            return [];
        }

        $sql = "SELECT sub.*, st.first_name, st.last_name, COALESCE(st.id_number, CONCAT('EST-', st.id)) AS student_code, st.email,
                       u.display_name AS graded_by_name
                FROM {$table_subs} sub
                JOIN {$table_stud} st ON st.id = sub.student_id
                LEFT JOIN {$wpdb->users} u ON u.ID = sub.graded_by
                WHERE sub.task_id = %d
                ORDER BY sub.submitted_at DESC";

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $task_id ) );
        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Calificar entrega de tarea
     *
     * @param int $submission_id
     * @param float $score
     * @param string $feedback
     * @return bool
     */
    public static function grade_submission( int $submission_id, float $score, string $feedback = '' ): bool {
        global $wpdb;
        $table_subs = $wpdb->prefix . 'aura_cal_task_submissions';

        $updated = $wpdb->update(
            $table_subs,
            [
                'score'     => $score,
                'feedback'  => sanitize_textarea_field( $feedback ),
                'status'    => 'graded',
                'graded_by' => get_current_user_id(),
                'graded_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $submission_id ],
            [ '%f', '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        return $updated !== false;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_tasks(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $tasks = self::get_tasks( [
            'program_id' => intval( $_POST['program_id'] ?? 0 ),
            'subject_id' => intval( $_POST['subject_id'] ?? 0 ),
            'status'     => sanitize_text_field( $_POST['status'] ?? '' ),
            'search'     => sanitize_text_field( $_POST['search'] ?? '' ),
        ] );

        wp_send_json_success( [ 'tasks' => $tasks ] );
    }

    public static function ajax_get_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $task = self::get_task( $id );
        if ( ! $task ) {
            wp_send_json_error( [ 'message' => __( 'Tarea no encontrada.', 'aura' ) ] );
        }

        wp_send_json_success( [ 'task' => $task ] );
    }

    public static function ajax_save_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'aura_teach_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $res = self::save_task( $_POST );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( [
            'message' => __( 'Tarea guardada correctamente.', 'aura' ),
            'task_id' => $res,
        ] );
    }

    public static function ajax_delete_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura' ) ] );
        }

        $ok = self::delete_task( $id );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Tarea eliminada.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar la tarea.', 'aura' ) ] );
        }
    }

    public static function ajax_get_submissions(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'aura_teach_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $task_id = intval( $_POST['task_id'] ?? 0 );
        if ( ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de tarea inválido.', 'aura' ) ] );
        }

        $submissions = self::get_submissions( $task_id );
        wp_send_json_success( [ 'submissions' => $submissions ] );
    }

    public static function ajax_grade_submission(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_record_grades' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para calificar.', 'aura' ) ] );
        }

        $submission_id = intval( $_POST['submission_id'] ?? 0 );
        $score         = floatval( $_POST['score'] ?? 0 );
        $feedback      = sanitize_textarea_field( $_POST['feedback'] ?? '' );

        if ( ! $submission_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de entrega inválido.', 'aura' ) ] );
        }

        $ok = self::grade_submission( $submission_id, $score, $feedback );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => __( 'Entrega calificada exitosamente.', 'aura' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al calificar la entrega.', 'aura' ) ] );
        }
    }

    public static function ajax_submit_task(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        // Identificar al estudiante por su user_id actual
        global $wpdb;
        $table_stud = $wpdb->prefix . 'aura_students';
        $table_subs = $wpdb->prefix . 'aura_cal_task_submissions';
        $table_tasks= $wpdb->prefix . 'aura_cal_tasks';

        $user_id    = get_current_user_id();
        $student_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_stud} WHERE wp_user_id = %d", $user_id ) );

        if ( ! $student_id ) {
            wp_send_json_error( [ 'message' => __( 'Perfil de estudiante no encontrado para este usuario.', 'aura' ) ] );
        }

        $task_id = intval( $_POST['task_id'] ?? 0 );
        if ( ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de tarea inválido.', 'aura' ) ] );
        }

        $task = self::get_task( $task_id );
        if ( ! $task || $task->status === 'closed' ) {
            wp_send_json_error( [ 'message' => __( 'La tarea no existe o ya está cerrada.', 'aura' ) ] );
        }

        $now_str = current_time( 'mysql' );
        $is_late = strtotime( $now_str ) > strtotime( $task->due_datetime );
        $sub_status = $is_late ? 'late' : 'submitted';

        $submission_text = sanitize_textarea_field( $_POST['submission_text'] ?? '' );
        $attachments     = sanitize_text_field( $_POST['attachment_urls'] ?? '' );

        $existing_sub_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table_subs} WHERE task_id = %d AND student_id = %d",
            $task_id,
            $student_id
        ) );

        if ( $existing_sub_id ) {
            $wpdb->update(
                $table_subs,
                [
                    'submission_text' => $submission_text,
                    'attachment_urls' => $attachments,
                    'submitted_at'    => $now_str,
                    'status'          => $sub_status,
                ],
                [ 'id' => $existing_sub_id ],
                [ '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $table_subs,
                [
                    'task_id'         => $task_id,
                    'student_id'      => $student_id,
                    'submission_text' => $submission_text,
                    'attachment_urls' => $attachments,
                    'submitted_at'    => $now_str,
                    'status'          => $sub_status,
                ],
                [ '%d', '%d', '%s', '%s', '%s', '%s' ]
            );
        }

        wp_send_json_success( [ 'message' => __( '¡Tarea entregada correctamente!', 'aura' ) ] );
    }
}
