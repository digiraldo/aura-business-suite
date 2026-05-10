<?php
/**
 * Flujo de Aprobación e Inscripción — Fase 4
 *
 * Responsabilidades:
 *  - Aprobar o rechazar postulantes (cambia estado + crea usuario WP)
 *  - Inscribir estudiante aprobado en un curso (genera registro + schedule de cuotas)
 *  - Actualizar inscripción (beca, esquema de pago, estado)
 *  - Graduar estudiante (dispara hook para módulo de certificados)
 *  - Servir listados AJAX para la plantilla enrollments.php
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Enrollments {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_approve',              [ __CLASS__, 'ajax_approve_student' ] );
        add_action( 'wp_ajax_aura_students_reject',               [ __CLASS__, 'ajax_reject_student' ] );
        add_action( 'wp_ajax_aura_students_enroll',               [ __CLASS__, 'ajax_enroll_student' ] );
        add_action( 'wp_ajax_aura_students_update_enrollment',    [ __CLASS__, 'ajax_update_enrollment' ] );
        add_action( 'wp_ajax_aura_students_graduate',             [ __CLASS__, 'ajax_graduate_student' ] );
        add_action( 'wp_ajax_aura_students_list_applicants',      [ __CLASS__, 'ajax_list_applicants' ] );
        add_action( 'wp_ajax_aura_students_list_enrollments',     [ __CLASS__, 'ajax_list_enrollments' ] );
        add_action( 'wp_ajax_aura_students_get_courses_by_area',  [ __CLASS__, 'ajax_get_courses_by_area' ] );
        add_action( 'wp_ajax_aura_students_get_enrollment',       [ __CLASS__, 'ajax_get_enrollment' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_enrollments(): void {
        if (
            ! current_user_can( 'aura_students_enrollments_manage' ) &&
            ! current_user_can( 'aura_students_approve' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/enrollments.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: APROBAR POSTULANTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_approve_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_approve' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'aura_students';
        $id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de estudiante inválido.', 'aura-suite' ) ] );
        }

        // Obtener datos del estudiante
        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d",
            $id
        ) );

        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
        }

        if ( $student->status !== 'applicant' ) {
            wp_send_json_error( [ 'message' => __( 'Solo se pueden aprobar postulantes en estado "applicant".', 'aura-suite' ) ] );
        }

        // Verificar si ya tiene un usuario WP
        $wp_user_id = 0;
        if ( $student->wp_user_id ) {
            $wp_user_id = absint( $student->wp_user_id );
        } else {
            // Crear usuario WP
            $email      = $student->email;
            $first_name = $student->first_name;
            $last_name  = $student->last_name;
            $password   = wp_generate_password( 12, true, false );

            // Verificar si el email ya existe
            if ( email_exists( $email ) ) {
                $existing_user = get_user_by( 'email', $email );
                $wp_user_id    = $existing_user ? $existing_user->ID : 0;
            } else {
                $wp_user_id = wp_insert_user( [
                    'user_login' => $email,
                    'user_email' => $email,
                    'user_pass'  => $password,
                    'role'       => 'subscriber',
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ] );
            }

            if ( is_wp_error( $wp_user_id ) ) {
                wp_send_json_error( [ 'message' => $wp_user_id->get_error_message() ] );
            }

            // Asignar capabilities del portal de estudiante
            $user = new WP_User( $wp_user_id );
            $user->add_cap( 'aura_students_view_own' );
            $user->add_cap( 'aura_students_payments_view_own' );
        }

        // Actualizar estado del estudiante
        $updated = $wpdb->update(
            $table,
            [
                'status'      => 'approved',
                'wp_user_id'  => $wp_user_id,
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%d', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            wp_send_json_error( [ 'message' => __( 'Error al actualizar el estado del estudiante.', 'aura-suite' ) ] );
        }

        // Enviar email de bienvenida con credenciales (si se creó un usuario nuevo)
        if ( isset( $password ) && class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::send_approval_email( $id, $password );
        }

        wp_send_json_success( [
            'message'    => __( 'Estudiante aprobado correctamente. Se han creado sus credenciales de acceso.', 'aura-suite' ),
            'wp_user_id' => $wp_user_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: RECHAZAR POSTULANTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_reject_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_approve' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table            = $wpdb->prefix . 'aura_students';
        $id               = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $rejection_reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( $_POST['rejection_reason'] ) : '';

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de estudiante inválido.', 'aura-suite' ) ] );
        }

        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d",
            $id
        ) );

        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
        }

        if ( ! in_array( $student->status, [ 'applicant', 'approved' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo se pueden rechazar postulantes activos.', 'aura-suite' ) ] );
        }

        $updated = $wpdb->update(
            $table,
            [
                'status'           => 'rejected',
                'rejection_reason' => $rejection_reason,
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            wp_send_json_error( [ 'message' => __( 'Error al rechazar el estudiante.', 'aura-suite' ) ] );
        }

        // Notificar al postulante
        if ( class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::send_rejection_email( (int) $id, $rejection_reason );
        }

        wp_send_json_success( [ 'message' => __( 'Solicitud rechazada. Se notificó al postulante.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: INSCRIBIR ESTUDIANTE EN UN CURSO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_enroll_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_enrollments_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $students_table  = $wpdb->prefix . 'aura_students';
        $enroll_table    = $wpdb->prefix . 'aura_student_enrollments';
        $schedule_table  = $wpdb->prefix . 'aura_student_installment_schedule';

        $student_id       = isset( $_POST['student_id'] )       ? absint( $_POST['student_id'] )                         : 0;
        $course_id        = isset( $_POST['course_id'] )        ? absint( $_POST['course_id'] )                          : 0;
        $base_cost        = isset( $_POST['base_cost'] )        ? round( (float) $_POST['base_cost'], 2 )                 : 0.00;
        $scholarship_type = isset( $_POST['scholarship_type'] ) ? sanitize_text_field( $_POST['scholarship_type'] )       : 'none';
        $scholarship_pct  = isset( $_POST['scholarship_pct'] )  ? min( 100, max( 0, (float) $_POST['scholarship_pct'] ) ) : 0.00;
        $scholarship_sponsor = isset( $_POST['scholarship_sponsor'] ) ? sanitize_text_field( $_POST['scholarship_sponsor'] ) : '';
        $scholarship_notes   = isset( $_POST['scholarship_notes'] )   ? sanitize_textarea_field( $_POST['scholarship_notes'] ) : '';
        $payment_scheme   = isset( $_POST['payment_scheme'] )   ? sanitize_text_field( $_POST['payment_scheme'] )        : 'full';
        $installment_count = isset( $_POST['installment_count'] ) ? max( 1, absint( $_POST['installment_count'] ) )       : 1;
        $first_payment_date = isset( $_POST['first_payment_date'] ) ? sanitize_text_field( $_POST['first_payment_date'] ) : current_time( 'Y-m-d' );
        $notes            = isset( $_POST['notes'] )            ? sanitize_textarea_field( $_POST['notes'] )              : '';

        if ( ! $student_id || ! $course_id ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante y curso son obligatorios.', 'aura-suite' ) ] );
        }

        // Validar esquema de pago
        $valid_schemes = [ 'full', 'installments', 'scholarship_full' ];
        if ( ! in_array( $payment_scheme, $valid_schemes, true ) ) {
            $payment_scheme = 'full';
        }

        $valid_scholarship_types = [ 'none', 'internal', 'external' ];
        if ( ! in_array( $scholarship_type, $valid_scholarship_types, true ) ) {
            $scholarship_type = 'none';
        }

        // Validar fecha
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $first_payment_date ) ) {
            $first_payment_date = current_time( 'Y-m-d' );
        }

        // Obtener estudiante
        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$students_table}` WHERE id = %d",
            $student_id
        ) );

        if ( ! $student ) {
            wp_send_json_error( [ 'message' => __( 'Estudiante no encontrado.', 'aura-suite' ) ] );
        }

        if ( ! in_array( $student->status, [ 'approved', 'active' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo se pueden inscribir estudiantes aprobados.', 'aura-suite' ) ] );
        }

        // Verificar inscripción duplicada
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$enroll_table}` WHERE student_id = %d AND course_id = %d AND status NOT IN ('withdrawn', 'suspended')",
            $student_id,
            $course_id
        ) );

        if ( $existing ) {
            wp_send_json_error( [ 'message' => __( 'El estudiante ya está inscrito en este curso.', 'aura-suite' ) ] );
        }

        // Calcular costo neto
        $net_cost = round( $base_cost * ( 1 - $scholarship_pct / 100 ), 2 );

        // Calcular cuota
        $installment_amount = ( $payment_scheme === 'installments' && $installment_count > 0 )
            ? round( $net_cost / $installment_count, 2 )
            : $net_cost;

        $payment_status = ( $net_cost <= 0 ) ? 'paid' : 'unpaid';

        // Insertar inscripción
        $inserted = $wpdb->insert(
            $enroll_table,
            [
                'student_id'          => $student_id,
                'course_id'           => $course_id,
                'enrollment_date'     => current_time( 'Y-m-d' ),
                'status'              => 'active',
                'base_cost'           => $base_cost,
                'scholarship_type'    => $scholarship_type,
                'scholarship_pct'     => $scholarship_pct,
                'scholarship_sponsor' => $scholarship_sponsor,
                'scholarship_notes'   => $scholarship_notes,
                'net_cost'            => $net_cost,
                'payment_scheme'      => $payment_scheme,
                'installment_count'   => $installment_count,
                'installment_amount'  => $installment_amount,
                'first_payment_date'  => $first_payment_date,
                'total_paid'          => 0.00,
                'balance_due'         => $net_cost,
                'payment_status'      => $payment_status,
                'enrolled_by'         => get_current_user_id(),
                'notes'               => $notes,
                'created_at'          => current_time( 'mysql' ),
                'updated_at'          => current_time( 'mysql' ),
            ],
            [
                '%d', '%d', '%s', '%s',
                '%f', '%s', '%f', '%s', '%s',
                '%f', '%s', '%d', '%f', '%s',
                '%f', '%f', '%s', '%d', '%s',
                '%s', '%s',
            ]
        );

        if ( ! $inserted ) {
            wp_send_json_error( [ 'message' => __( 'Error al crear la inscripción.', 'aura-suite' ) ] );
        }

        $enrollment_id = $wpdb->insert_id;

        // Generar schedule de cuotas
        if ( $payment_scheme === 'installments' && $installment_count > 0 && $net_cost > 0 ) {
            self::generate_installment_schedule( $enrollment_id, $installment_count, $installment_amount, $first_payment_date );
        } elseif ( $net_cost > 0 ) {
            // Esquema full: una sola cuota
            $wpdb->insert(
                $schedule_table,
                [
                    'enrollment_id'   => $enrollment_id,
                    'installment_num' => 1,
                    'due_date'        => $first_payment_date,
                    'expected_amount' => $net_cost,
                    'paid_amount'     => 0.00,
                    'status'          => 'pending',
                ],
                [ '%d', '%d', '%s', '%f', '%f', '%s' ]
            );
        }

        // Activar el estudiante
        $wpdb->update(
            $students_table,
            [
                'status'     => 'active',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $student_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // Notificación
        if ( class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::send_enrollment_email( $student_id, $enrollment_id );
        }

        wp_send_json_success( [
            'message'       => __( 'Estudiante inscrito correctamente.', 'aura-suite' ),
            'enrollment_id' => $enrollment_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ACTUALIZAR INSCRIPCIÓN
    // ─────────────────────────────────────────────────────────────

    public static function ajax_update_enrollment(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_enrollments_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
        $schedule_table = $wpdb->prefix . 'aura_student_installment_schedule';

        $enrollment_id       = isset( $_POST['enrollment_id'] )       ? absint( $_POST['enrollment_id'] )                               : 0;
        $scholarship_type    = isset( $_POST['scholarship_type'] )    ? sanitize_text_field( $_POST['scholarship_type'] )               : 'none';
        $scholarship_pct     = isset( $_POST['scholarship_pct'] )     ? min( 100, max( 0, (float) $_POST['scholarship_pct'] ) )         : 0.00;
        $scholarship_sponsor = isset( $_POST['scholarship_sponsor'] ) ? sanitize_text_field( $_POST['scholarship_sponsor'] )            : '';
        $scholarship_notes   = isset( $_POST['scholarship_notes'] )   ? sanitize_textarea_field( $_POST['scholarship_notes'] )          : '';
        $payment_scheme      = isset( $_POST['payment_scheme'] )      ? sanitize_text_field( $_POST['payment_scheme'] )                 : 'full';
        $installment_count   = isset( $_POST['installment_count'] )   ? max( 1, absint( $_POST['installment_count'] ) )                 : 1;
        $first_payment_date  = isset( $_POST['first_payment_date'] )  ? sanitize_text_field( $_POST['first_payment_date'] )             : '';
        $status              = isset( $_POST['status'] )              ? sanitize_text_field( $_POST['status'] )                         : '';
        $notes               = isset( $_POST['notes'] )               ? sanitize_textarea_field( $_POST['notes'] )                      : '';

        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de inscripción inválido.', 'aura-suite' ) ] );
        }

        // Obtener inscripción actual
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$enroll_table}` WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        // Validar valores
        $valid_schemes = [ 'full', 'installments', 'scholarship_full' ];
        if ( ! in_array( $payment_scheme, $valid_schemes, true ) ) {
            $payment_scheme = $enrollment->payment_scheme;
        }

        $valid_scholarship_types = [ 'none', 'internal', 'external' ];
        if ( ! in_array( $scholarship_type, $valid_scholarship_types, true ) ) {
            $scholarship_type = $enrollment->scholarship_type;
        }

        $valid_statuses = [ 'active', 'pending', 'completed', 'withdrawn', 'suspended' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            $status = $enrollment->status;
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $first_payment_date ) ) {
            $first_payment_date = $enrollment->first_payment_date;
        }

        // Recalcular costos
        $net_cost           = round( $enrollment->base_cost * ( 1 - $scholarship_pct / 100 ), 2 );
        $installment_amount = ( $payment_scheme === 'installments' && $installment_count > 0 )
            ? round( $net_cost / $installment_count, 2 )
            : $net_cost;
        $balance_due        = max( 0, $net_cost - (float) $enrollment->total_paid );
        $payment_status     = self::calculate_payment_status( (float) $enrollment->total_paid, $net_cost );

        $wpdb->update(
            $enroll_table,
            [
                'scholarship_type'    => $scholarship_type,
                'scholarship_pct'     => $scholarship_pct,
                'scholarship_sponsor' => $scholarship_sponsor,
                'scholarship_notes'   => $scholarship_notes,
                'net_cost'            => $net_cost,
                'payment_scheme'      => $payment_scheme,
                'installment_count'   => $installment_count,
                'installment_amount'  => $installment_amount,
                'first_payment_date'  => $first_payment_date,
                'balance_due'         => $balance_due,
                'payment_status'      => $payment_status,
                'status'              => $status,
                'notes'               => $notes,
                'updated_at'          => current_time( 'mysql' ),
            ],
            [ 'id' => $enrollment_id ],
            [ '%s', '%f', '%s', '%s', '%f', '%s', '%d', '%f', '%s', '%f', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        // Regenerar schedule si cambió el esquema de pago o los montos
        $scheme_changed = ( $payment_scheme !== $enrollment->payment_scheme );
        $cost_changed   = ( abs( $net_cost - (float) $enrollment->net_cost ) > 0.01 );

        if ( $scheme_changed || $cost_changed ) {
            // Eliminar cuotas aún no pagadas
            $wpdb->delete(
                $schedule_table,
                [
                    'enrollment_id' => $enrollment_id,
                    'status'        => 'pending',
                ],
                [ '%d', '%s' ]
            );

            if ( $payment_scheme === 'installments' && $installment_count > 0 && $net_cost > 0 ) {
                self::generate_installment_schedule( $enrollment_id, $installment_count, $installment_amount, $first_payment_date );
            }
        }

        wp_send_json_success( [ 'message' => __( 'Inscripción actualizada correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: GRADUAR ESTUDIANTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_graduate_student(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_enrollments_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $students_table = $wpdb->prefix . 'aura_students';
        $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';

        $enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;

        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de inscripción inválido.', 'aura-suite' ) ] );
        }

        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$enroll_table}` WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        if ( ! in_array( $enrollment->status, [ 'active', 'pending' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo inscripciones activas pueden ser graduadas.', 'aura-suite' ) ] );
        }

        $today      = current_time( 'Y-m-d' );
        $student_id = absint( $enrollment->student_id );

        // Marcar inscripción como completada
        $wpdb->update(
            $enroll_table,
            [
                'status'     => 'completed',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $enrollment_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // Marcar estudiante como graduado
        $wpdb->update(
            $students_table,
            [
                'status'       => 'graduated',
                'graduated_at' => $today,
                'updated_at'   => current_time( 'mysql' ),
            ],
            [ 'id' => $student_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        // Disparar hook para módulo de certificados
        do_action( 'aura_student_graduated', $student_id, $enrollment_id );

        wp_send_json_success( [
            'message' => __( 'Estudiante graduado correctamente.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTAR POSTULANTES
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_applicants(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_approve' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'aura_students';
        $per_page = 20;
        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $offset   = ( $page - 1 ) * $per_page;
        $search   = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $profile  = isset( $_POST['profile_type'] ) ? sanitize_text_field( $_POST['profile_type'] ) : '';

        $where   = "WHERE status = 'applicant' AND deleted_at IS NULL";
        $params  = [];

        if ( $search ) {
            $where   .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $profile ) {
            $where   .= ' AND profile_type = %s';
            $params[] = $profile;
        }

        // Usar prepare() solo cuando hay parámetros dinámicos (%s/%d en $where).
        // Para LIMIT/OFFSET siempre se necesita prepare.
        $count_sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
        $total     = empty( $params )
            ? (int) $wpdb->get_var( $count_sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

        $data_sql = "SELECT id, first_name, last_name, email, phone, profile_type, photo_url,
                    preferred_areas, motivation, created_at
             FROM `{$table}` {$where}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d";

        $rows = empty( $params )
            ? $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $params, [ $per_page, $offset ] ) ) );

        wp_send_json_success( [
            'rows'       => $rows,
            'total'      => (int) $total,
            'per_page'   => $per_page,
            'page'       => $page,
            'total_pages' => ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTAR INSCRIPCIONES ACTIVAS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_enrollments(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_enrollments_manage' ) &&
            ! current_user_can( 'aura_students_approve' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $enroll_table  = $wpdb->prefix . 'aura_student_enrollments';
        $students_table = $wpdb->prefix . 'aura_students';
        $courses_table  = $wpdb->prefix . 'aura_student_courses';
        $areas_table    = $wpdb->prefix . 'aura_areas';

        $per_page      = 20;
        $page          = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $offset        = ( $page - 1 ) * $per_page;
        $search        = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $area_filter   = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $status_filter = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'active';

        $valid_statuses = [ 'active', 'pending', 'completed', 'withdrawn', 'suspended' ];
        if ( $status_filter && ! in_array( $status_filter, $valid_statuses, true ) ) {
            $status_filter = 'active';
        }

        $where  = 'WHERE 1=1';
        $params = [];

        if ( $status_filter ) {
            $where   .= ' AND e.status = %s';
            $params[] = $status_filter;
        }

        if ( $area_filter ) {
            $where   .= ' AND c.area_id = %d';
            $params[] = $area_filter;
        }

        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where   .= ' AND (s.first_name LIKE %s OR s.last_name LIKE %s OR s.email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Check if areas table exists
        $areas_exists = $wpdb->get_var( $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $areas_table
        ) );

        $area_join = $areas_exists
            ? "LEFT JOIN `{$areas_table}` a ON a.id = c.area_id"
            : '';

        $area_select = $areas_exists
            ? 'a.name AS area_name,'
            : "'' AS area_name,";

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$enroll_table}` e
             JOIN `{$students_table}` s ON s.id = e.student_id
             JOIN `{$courses_table}` c  ON c.id = e.course_id
             {$area_join}
             {$where}",
            ...$params
        ) );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.student_id, e.course_id, e.enrollment_date, e.status,
                    e.base_cost, e.scholarship_type, e.scholarship_pct, e.net_cost,
                    e.payment_scheme, e.installment_count, e.total_paid, e.balance_due,
                    e.payment_status, e.first_payment_date,
                    s.first_name, s.last_name, s.email, s.photo_url,
                    c.name AS course_name, c.area_id,
                    {$area_select}
                    e.notes
             FROM `{$enroll_table}` e
             JOIN `{$students_table}` s ON s.id = e.student_id
             JOIN `{$courses_table}` c  ON c.id = e.course_id
             {$area_join}
             {$where}
             ORDER BY e.created_at DESC
             LIMIT %d OFFSET %d",
            ...array_merge( $params, [ $per_page, $offset ] )
        ) );

        wp_send_json_success( [
            'rows'        => $rows,
            'total'       => (int) $total,
            'per_page'    => $per_page,
            'page'        => $page,
            'total_pages' => ceil( $total / $per_page ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER CURSOS POR ÁREA (para el modal de inscripción)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_courses_by_area(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_enrollments_manage' ) &&
            ! current_user_can( 'aura_students_approve' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

        $table = $wpdb->prefix . 'aura_student_courses';
        $query = "SELECT id, name, base_cost, currency FROM `{$table}` WHERE status = 'active'";
        $params = [];

        if ( $area_id ) {
            $query   .= ' AND area_id = %d';
            $params[] = $area_id;
        }

        $query .= ' ORDER BY name ASC';

        $courses = $area_id
            ? $wpdb->get_results( $wpdb->prepare( $query, ...$params ) )
            : $wpdb->get_results( $query );

        wp_send_json_success( [ 'courses' => $courses ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER DATOS DE UNA INSCRIPCIÓN (para editar)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_enrollment(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_enrollments_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
        $students_table = $wpdb->prefix . 'aura_students';
        $courses_table  = $wpdb->prefix . 'aura_student_courses';

        $enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;

        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, s.first_name, s.last_name, s.email,
                    c.name AS course_name, c.area_id
             FROM `{$enroll_table}` e
             JOIN `{$students_table}` s ON s.id = e.student_id
             JOIN `{$courses_table}` c  ON c.id = e.course_id
             WHERE e.id = %d",
            $enrollment_id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'enrollment' => $row ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera el schedule de cuotas para una inscripción.
     */
    private static function generate_installment_schedule(
        int    $enrollment_id,
        int    $count,
        float  $amount,
        string $first_payment_date
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_student_installment_schedule';

        for ( $i = 1; $i <= $count; $i++ ) {
            $months   = $i - 1;
            $due_date = date( 'Y-m-d', strtotime( "+{$months} months", strtotime( $first_payment_date ) ) );

            $wpdb->insert(
                $table,
                [
                    'enrollment_id'   => $enrollment_id,
                    'installment_num' => $i,
                    'due_date'        => $due_date,
                    'expected_amount' => $amount,
                    'paid_amount'     => 0.00,
                    'status'          => 'pending',
                ],
                [ '%d', '%d', '%s', '%f', '%f', '%s' ]
            );
        }
    }

    /**
     * Calcula el estado de pago basado en total pagado vs costo neto.
     */
    private static function calculate_payment_status( float $total_paid, float $net_cost ): string {
        if ( $net_cost <= 0 ) {
            return 'paid';
        }
        if ( $total_paid <= 0 ) {
            return 'unpaid';
        }
        if ( $total_paid >= $net_cost ) {
            return 'paid';
        }
        return 'partial';
    }
}
