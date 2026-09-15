<?php
/**
 * Assignments del Módulo de Formularios — Asignación de encuestas
 *
 * Gestiona la asignación manual y automática de formularios tipo survey/feedback
 * a estudiantes, y expone la lista de pendientes al portal frontend del estudiante.
 *
 * AJAX actions registradas:
 *  - aura_forms_assign             — Crear N asignaciones
 *  - aura_forms_revoke             — Marcar asignación como expirada
 *  - aura_forms_list_assignments   — Listar con filtros (panel admin)
 *  - aura_forms_student_pending    — Pendientes/completados del estudiante autenticado
 *  - aura_forms_get_students_list  — Lista de estudiantes para el selector de asignación
 *  - aura_forms_complete_assignment — Registra el submission en el assignment
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Assignments {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_forms_assign',              [ __CLASS__, 'ajax_assign' ] );
        add_action( 'wp_ajax_aura_forms_revoke',              [ __CLASS__, 'ajax_revoke' ] );
        add_action( 'wp_ajax_aura_forms_list_assignments',    [ __CLASS__, 'ajax_list_assignments' ] );
        add_action( 'wp_ajax_aura_forms_student_pending',     [ __CLASS__, 'ajax_list_student_forms' ] );
        add_action( 'wp_ajax_aura_forms_get_students_list',   [ __CLASS__, 'ajax_get_students_list' ] );
        add_action( 'wp_ajax_aura_forms_complete_assignment', [ __CLASS__, 'ajax_complete_assignment' ] );

        // Hook: al guardar un submission, marcar assignment como completado si aplica
        add_action( 'aura_form_submission_saved', [ __CLASS__, 'maybe_complete_assignment' ], 20, 2 );
    }

    // ─────────────────────────────────────────────────────────────
    // CREATE ASSIGNMENT (método estático reutilizable)
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear una asignación de formulario a estudiante.
     * Usada por Aura_Forms_Setup (auto-asignación) y por ajax_assign().
     *
     * @param int      $form_id
     * @param int      $student_id
     * @param int|null $enrollment_id
     * @param int|null $expires_timestamp  Timestamp Unix o null para sin expiración
     * @param string   $trigger            'manual' | 'on_enrollment_approved' | 'on_course_complete' | 'scheduled'
     * @return int|false  ID del assignment creado, o false si ya existe
     */
    public static function create_assignment(
        int $form_id,
        int $student_id,
        ?int $enrollment_id,
        ?int $expires_timestamp,
        string $trigger = 'manual'
    ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_form_assignments';

        // Comprobar si ya existe un assignment activo (pendiente o completado)
        if ( $enrollment_id ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}`
                  WHERE form_id = %d AND student_id = %d AND enrollment_id = %d
                  AND status IN ('pending','completed')
                  LIMIT 1",
                $form_id, $student_id, $enrollment_id
            ) );
        } else {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$table}`
                  WHERE form_id = %d AND student_id = %d AND enrollment_id IS NULL
                  AND status IN ('pending','completed')
                  LIMIT 1",
                $form_id, $student_id
            ) );
        }

        if ( $exists ) {
            return false;
        }

        $expires_at = $expires_timestamp ? gmdate( 'Y-m-d H:i:s', $expires_timestamp ) : null;

        $result = $wpdb->insert(
            $table,
            [
                'form_id'            => $form_id,
                'student_id'         => $student_id,
                'enrollment_id'      => $enrollment_id,
                'status'             => 'pending',
                'assigned_at'        => current_time( 'mysql' ),
                'expires_at'         => $expires_at,
                'assigned_by'        => get_current_user_id() ?: null,
                'assignment_trigger' => $trigger,
            ],
            [ '%d', '%d', $enrollment_id ? '%d' : null, '%s', '%s', $expires_at ? '%s' : null, $trigger === 'manual' ? '%d' : null, '%s' ]
        );

        if ( ! $result ) {
            return false;
        }

        $assignment_id = (int) $wpdb->insert_id;

        // Notificar al estudiante si la asignación es manual
        if ( $trigger === 'manual' && class_exists( 'Aura_Forms_Notifications' ) ) {
            Aura_Forms_Notifications::notify_student_assignment( $assignment_id );
        }

        return $assignment_id;
    }

    // ─────────────────────────────────────────────────────────────
    // AUTO-COMPLETE: vincular submission con assignment
    // ─────────────────────────────────────────────────────────────

    /**
     * Escucha 'aura_form_submission_saved': si el formulario enviado tiene
     * un assignment pendiente para este usuario, lo marca como completado.
     */
    public static function maybe_complete_assignment( int $submission_id, int $form_id ): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        global $wpdb;

        // Buscar el estudiante vinculado al usuario WP actual
        $student_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_students
              WHERE wp_user_id = %d AND deleted_at IS NULL LIMIT 1",
            get_current_user_id()
        ) );

        if ( ! $student_id ) {
            return;
        }

        // Buscar assignment pendiente
        $assignment_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_form_assignments
              WHERE form_id = %d AND student_id = %d AND status = 'pending'
              ORDER BY assigned_at DESC LIMIT 1",
            $form_id, $student_id
        ) );

        if ( ! $assignment_id ) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'aura_form_assignments',
            [
                'status'        => 'completed',
                'submission_id' => $submission_id,
                'completed_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $assignment_id ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );

        do_action( 'aura_form_assignment_completed', $assignment_id, $submission_id, $student_id );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ASIGNAR FORMULARIO A ESTUDIANTES
    // ─────────────────────────────────────────────────────────────

    public static function ajax_assign(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_assign' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $form_id         = isset( $_POST['form_id'] )         ? absint( $_POST['form_id'] ) : 0;
        $student_ids_raw = isset( $_POST['student_ids'] )     ? wp_unslash( $_POST['student_ids'] ) : '';
        $expires_raw     = isset( $_POST['expires_at'] )      ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
        $notes           = isset( $_POST['notes'] )           ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'Selecciona un formulario.', 'aura-suite' ) ] );
        }

        // Parsear array de student_ids
        if ( is_array( $student_ids_raw ) ) {
            $student_ids = array_map( 'absint', $student_ids_raw );
        } else {
            $student_ids = array_filter( array_map( 'absint', explode( ',', $student_ids_raw ) ) );
        }

        $student_ids = array_unique( array_filter( $student_ids ) );

        if ( empty( $student_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'Selecciona al menos un estudiante.', 'aura-suite' ) ] );
        }

        // Verificar que el formulario es de tipo survey o feedback
        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, type, title FROM {$wpdb->prefix}aura_forms
              WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );

        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Formulario no encontrado.', 'aura-suite' ) ] );
        }

        if ( ! in_array( $form->type, [ 'survey', 'feedback' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo se pueden asignar formularios de tipo encuesta o feedback.', 'aura-suite' ) ] );
        }

        // Fecha de expiración
        $expires_timestamp = null;
        if ( $expires_raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires_raw ) ) {
            $expires_timestamp = strtotime( $expires_raw . ' 23:59:59' );
        }

        $created  = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ( $student_ids as $student_id ) {
            $result = self::create_assignment( $form_id, $student_id, null, $expires_timestamp, 'manual' );
            if ( false === $result ) {
                ++$skipped;
            } elseif ( $result ) {
                // Guardar notes si las hay
                if ( $notes ) {
                    $wpdb->update(
                        $wpdb->prefix . 'aura_form_assignments',
                        [ 'notes' => $notes ],
                        [ 'id'   => $result ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
                ++$created;
            } else {
                ++$errors;
            }
        }

        if ( $created === 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %d = skipped */
                    __( 'No se crearon asignaciones nuevas. %d ya estaban asignados.', 'aura-suite' ),
                    $skipped
                ),
            ] );
        }

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %1$d = created, %2$d = skipped */
                __( '%1$d asignación(es) creada(s). %2$d ya existían.', 'aura-suite' ),
                $created,
                $skipped
            ),
            'created' => $created,
            'skipped' => $skipped,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: REVOCAR ASIGNACIÓN
    // ─────────────────────────────────────────────────────────────

    public static function ajax_revoke(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_assign' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;

        if ( ! $assignment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de asignación inválido.', 'aura-suite' ) ] );
        }

        $assignment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM {$wpdb->prefix}aura_form_assignments WHERE id = %d",
            $assignment_id
        ) );

        if ( ! $assignment ) {
            wp_send_json_error( [ 'message' => __( 'Asignación no encontrada.', 'aura-suite' ) ] );
        }

        if ( $assignment->status === 'completed' ) {
            wp_send_json_error( [ 'message' => __( 'No se puede revocar una asignación ya completada.', 'aura-suite' ) ] );
        }

        $wpdb->update(
            $wpdb->prefix . 'aura_form_assignments',
            [
                'status'     => 'expired',
                'expires_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $assignment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'message' => __( 'Asignación revocada correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTAR ASIGNACIONES (admin)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_assignments(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_assign' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $filter_form    = isset( $_POST['filter_form'] )   ? absint( $_POST['filter_form'] )                          : 0;
        $filter_status  = isset( $_POST['filter_status'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_status'] ) ) : 'all';
        $filter_student = isset( $_POST['filter_student'] )? absint( $_POST['filter_student'] )                       : 0;
        $paged          = isset( $_POST['paged'] )         ? max( 1, absint( $_POST['paged'] ) )                      : 1;
        $per_page       = 25;
        $offset         = ( $paged - 1 ) * $per_page;

        $valid_statuses = [ 'all', 'pending', 'completed', 'expired' ];
        if ( ! in_array( $filter_status, $valid_statuses, true ) ) {
            $filter_status = 'all';
        }

        $where_parts = [ '1=1' ];
        $where_args  = [];

        if ( $filter_form ) {
            $where_parts[] = 'a.form_id = %d';
            $where_args[]  = $filter_form;
        }

        if ( $filter_status !== 'all' ) {
            $where_parts[] = 'a.status = %s';
            $where_args[]  = $filter_status;
        }

        if ( $filter_student ) {
            $where_parts[] = 'a.student_id = %d';
            $where_args[]  = $filter_student;
        }

        $where_sql = implode( ' AND ', $where_parts );

        $count_sql = "SELECT COUNT(*)
          FROM {$wpdb->prefix}aura_form_assignments a
          WHERE {$where_sql}";

        $data_sql = "SELECT
            a.id, a.form_id, a.student_id, a.status,
            a.assigned_at, a.expires_at, a.completed_at, a.assignment_trigger,
            f.title AS form_title,
            CONCAT(st.first_name, ' ', st.last_name) AS student_name,
            st.email AS student_email
          FROM {$wpdb->prefix}aura_form_assignments a
     LEFT JOIN {$wpdb->prefix}aura_forms f   ON f.id = a.form_id
     LEFT JOIN {$wpdb->prefix}aura_students st ON st.id = a.student_id
         WHERE {$where_sql}
      ORDER BY a.assigned_at DESC
         LIMIT %d OFFSET %d";

        if ( $where_args ) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_args ) );
            $rows  = $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $where_args, [ $per_page, $offset ] ) ) );
            // phpcs:enable
        } else {
            $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore
            $rows  = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) ); // phpcs:ignore
        }

        wp_send_json_success( [
            'rows'        => $rows,
            'total'       => $total,
            'total_pages' => (int) ceil( $total / $per_page ),
            'paged'       => $paged,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: PENDIENTES DEL ESTUDIANTE AUTENTICADO (portal frontend)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_student_forms(): void {
        // Este endpoint es solo para usuarios autenticados
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Debes iniciar sesión.', 'aura-suite' ) ] );
        }

        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );

        global $wpdb;

        // Obtener el estudiante vinculado al usuario WP actual
        $student_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_students
              WHERE wp_user_id = %d AND deleted_at IS NULL LIMIT 1",
            get_current_user_id()
        ) );

        if ( ! $student_id ) {
            wp_send_json_error( [ 'message' => __( 'Perfil de estudiante no encontrado.', 'aura-suite' ) ] );
        }

        // Asignaciones pendientes
        $current_time = current_time( 'mysql' );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                a.id AS assignment_id,
                a.form_id, a.expires_at, a.assigned_at,
                f.title, f.description,
                c.name AS course_name
             FROM {$wpdb->prefix}aura_form_assignments a
        LEFT JOIN {$wpdb->prefix}aura_forms f         ON f.id = a.form_id
        LEFT JOIN {$wpdb->prefix}aura_student_courses c ON c.id = f.course_id
            WHERE a.student_id = %d
              AND a.status = 'pending'
              AND ( a.expires_at IS NULL OR a.expires_at > %s )
              AND f.is_active = 1
              AND f.deleted_at IS NULL
         ORDER BY a.assigned_at DESC",
            $student_id, $current_time
        ) );

        // Asignaciones completadas
        $completed = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                a.id AS assignment_id,
                a.form_id, a.completed_at, a.submission_id,
                f.title, f.description,
                c.name AS course_name
             FROM {$wpdb->prefix}aura_form_assignments a
        LEFT JOIN {$wpdb->prefix}aura_forms f         ON f.id = a.form_id
        LEFT JOIN {$wpdb->prefix}aura_student_courses c ON c.id = f.course_id
            WHERE a.student_id = %d
              AND a.status = 'completed'
              AND f.deleted_at IS NULL
         ORDER BY a.completed_at DESC
            LIMIT 20",
            $student_id
        ) );

        wp_send_json_success( [
            'pending'   => $pending,
            'completed' => $completed,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTA DE ESTUDIANTES PARA EL SELECTOR DE ASIGNACIÓN
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_students_list(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_assign' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $filter_course = isset( $_POST['filter_course'] ) ? absint( $_POST['filter_course'] )                             : 0;
        $filter_status = isset( $_POST['filter_status'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_status'] ) ) : 'active';
        $search        = isset( $_POST['search'] )        ? sanitize_text_field( wp_unslash( $_POST['search'] ) )        : '';

        $where_parts = [ 'st.deleted_at IS NULL' ];
        $where_args  = [];

        $valid_statuses = [ 'applicant', 'approved', 'active', 'graduated', 'withdrawn', 'rejected', 'all' ];
        if ( $filter_status !== 'all' && in_array( $filter_status, $valid_statuses, true ) ) {
            $where_parts[] = 'st.status = %s';
            $where_args[]  = $filter_status;
        }

        if ( $filter_course ) {
            $where_parts[] = 'EXISTS (
                SELECT 1 FROM {$wpdb->prefix}aura_student_enrollments se
                 WHERE se.student_id = st.id AND se.course_id = %d
                   AND se.status NOT IN (\'withdrawn\',\'suspended\')
            )';
            $where_args[] = $filter_course;
        }

        if ( $search ) {
            $like          = '%' . $wpdb->esc_like( $search ) . '%';
            $where_parts[] = '( st.first_name LIKE %s OR st.last_name LIKE %s OR st.email LIKE %s )';
            $where_args[]  = $like;
            $where_args[]  = $like;
            $where_args[]  = $like;
        }

        $where_sql = implode( ' AND ', $where_parts );

        $sql = "SELECT st.id, st.first_name, st.last_name, st.email, st.status
                  FROM {$wpdb->prefix}aura_students st
                 WHERE {$where_sql}
              ORDER BY st.first_name ASC, st.last_name ASC
                 LIMIT 200";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $students = $where_args
            ? $wpdb->get_results( $wpdb->prepare( $sql, $where_args ) )
            : $wpdb->get_results( $sql );

        wp_send_json_success( [ 'students' => $students ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: MARCAR ASSIGNMENT COMO COMPLETADO (manual desde portal)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_complete_assignment(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Debes iniciar sesión.', 'aura-suite' ) ] );
        }

        check_ajax_referer( 'aura_students_frontend_nonce', 'nonce' );

        global $wpdb;

        $assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
        $submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;

        if ( ! $assignment_id || ! $submission_id ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'aura-suite' ) ] );
        }

        // Verificar que la asignación pertenece al estudiante actual
        $student_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_students
              WHERE wp_user_id = %d AND deleted_at IS NULL LIMIT 1",
            get_current_user_id()
        ) );

        if ( ! $student_id ) {
            wp_send_json_error( [ 'message' => __( 'Perfil de estudiante no encontrado.', 'aura-suite' ) ] );
        }

        $assignment = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, student_id, status FROM {$wpdb->prefix}aura_form_assignments
              WHERE id = %d",
            $assignment_id
        ) );

        if ( ! $assignment || (int) $assignment->student_id !== $student_id ) {
            wp_send_json_error( [ 'message' => __( 'Asignación no válida.', 'aura-suite' ) ] );
        }

        if ( $assignment->status === 'completed' ) {
            wp_send_json_success( [ 'message' => __( 'Ya estaba completado.', 'aura-suite' ) ] );
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'aura_form_assignments',
            [
                'status'        => 'completed',
                'submission_id' => $submission_id,
                'completed_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $assignment_id ],
            [ '%s', '%d', '%s' ],
            [ '%d' ]
        );

        do_action( 'aura_form_assignment_completed', $assignment_id, $submission_id, $student_id );

        wp_send_json_success( [ 'message' => __( 'Formulario marcado como completado.', 'aura-suite' ) ] );
    }
}
