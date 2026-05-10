<?php
/**
 * Gestión de Pagos — Fase 5
 *
 * Responsabilidades:
 *  - Registrar pagos de cuotas de estudiantes
 *  - Actualizar totales en inscripción y schedule de cuotas
 *  - Crear transacción en el módulo financiero (integración)
 *  - Servir datos de pagos/cuotas al template payments.php
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Payments {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_register_payment',   [ __CLASS__, 'ajax_register_payment' ] );
        add_action( 'wp_ajax_aura_students_payment_summary',    [ __CLASS__, 'ajax_get_payment_summary' ] );
        add_action( 'wp_ajax_aura_students_list_payments',      [ __CLASS__, 'ajax_list_payments' ] );
        add_action( 'wp_ajax_aura_students_get_installments',   [ __CLASS__, 'ajax_get_enrollment_installments' ] );
        add_action( 'wp_ajax_aura_students_delete_payment',     [ __CLASS__, 'ajax_delete_payment' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_payments(): void {
        if (
            ! current_user_can( 'aura_students_payments_view_all' ) &&
            ! current_user_can( 'aura_students_payments_register' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        require_once AURA_PLUGIN_DIR . 'templates/students/payments.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: REGISTRAR PAGO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_register_payment(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_payments_register' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $payments_table  = $wpdb->prefix . 'aura_student_payments';
        $enroll_table    = $wpdb->prefix . 'aura_student_enrollments';
        $schedule_table  = $wpdb->prefix . 'aura_student_installment_schedule';
        $students_table  = $wpdb->prefix . 'aura_students';
        $courses_table   = $wpdb->prefix . 'aura_student_courses';

        $enrollment_id   = isset( $_POST['enrollment_id'] )   ? absint( $_POST['enrollment_id'] )                   : 0;
        $amount          = isset( $_POST['amount'] )          ? round( (float) $_POST['amount'], 2 )                 : 0.00;
        $payment_date    = isset( $_POST['payment_date'] )    ? sanitize_text_field( $_POST['payment_date'] )        : current_time( 'Y-m-d' );
        $payment_method  = isset( $_POST['payment_method'] )  ? sanitize_text_field( $_POST['payment_method'] )      : 'cash';
        $reference_number = isset( $_POST['reference_number'] ) ? sanitize_text_field( $_POST['reference_number'] ) : '';
        $installment_num = isset( $_POST['installment_num'] ) ? absint( $_POST['installment_num'] )                  : 0;
        $notes           = isset( $_POST['notes'] )           ? sanitize_textarea_field( $_POST['notes'] )           : '';

        if ( ! $enrollment_id || $amount <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción y monto son obligatorios.', 'aura-suite' ) ] );
        }

        // Validar fecha
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $payment_date ) ) {
            $payment_date = current_time( 'Y-m-d' );
        }

        // Validar método de pago
        $valid_methods = [ 'cash', 'transfer', 'card', 'check', 'other' ];
        if ( ! in_array( $payment_method, $valid_methods, true ) ) {
            $payment_method = 'cash';
        }

        // Obtener inscripción
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$enroll_table}` WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        if ( $enrollment->payment_status === 'paid' ) {
            wp_send_json_error( [ 'message' => __( 'Esta inscripción ya está completamente pagada.', 'aura-suite' ) ] );
        }

        // Obtener datos del estudiante y curso para descripción
        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name, email FROM `{$students_table}` WHERE id = %d",
            $enrollment->student_id
        ) );

        $course = $wpdb->get_row( $wpdb->prepare(
            "SELECT name, finance_cat_id FROM `{$courses_table}` WHERE id = %d",
            $enrollment->course_id
        ) );

        // 1. Insertar pago
        $wpdb->insert(
            $payments_table,
            [
                'enrollment_id'    => $enrollment_id,
                'student_id'       => absint( $enrollment->student_id ),
                'course_id'        => absint( $enrollment->course_id ),
                'payment_date'     => $payment_date,
                'amount'           => $amount,
                'payment_method'   => $payment_method,
                'reference_number' => $reference_number,
                'installment_num'  => $installment_num ?: null,
                'notes'            => $notes,
                'registered_by'    => get_current_user_id(),
                'created_at'       => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', $installment_num ? '%d' : null, '%s', '%d', '%s' ]
        );

        if ( ! $wpdb->insert_id ) {
            wp_send_json_error( [ 'message' => __( 'Error al registrar el pago.', 'aura-suite' ) ] );
        }

        $payment_id = $wpdb->insert_id;

        // 2. Actualizar totales de la inscripción
        $new_total   = round( (float) $enrollment->total_paid + $amount, 2 );
        $new_balance = max( 0.00, round( (float) $enrollment->net_cost - $new_total, 2 ) );

        if ( $new_balance <= 0 ) {
            $new_payment_status = 'paid';
        } elseif ( $new_total > 0 ) {
            $new_payment_status = 'partial';
        } else {
            $new_payment_status = 'unpaid';
        }

        $wpdb->update(
            $enroll_table,
            [
                'total_paid'     => $new_total,
                'balance_due'    => $new_balance,
                'payment_status' => $new_payment_status,
                'updated_at'     => current_time( 'mysql' ),
            ],
            [ 'id' => $enrollment_id ],
            [ '%f', '%f', '%s', '%s' ],
            [ '%d' ]
        );

        // 3. Actualizar cuota en el schedule
        if ( $installment_num ) {
            $sched_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM `{$schedule_table}` WHERE enrollment_id = %d AND installment_num = %d",
                $enrollment_id,
                $installment_num
            ) );

            if ( $sched_row ) {
                $sched_paid   = round( (float) $sched_row->paid_amount + $amount, 2 );
                $sched_status = ( $sched_paid >= (float) $sched_row->expected_amount ) ? 'paid' : 'partial';

                $wpdb->update(
                    $schedule_table,
                    [
                        'paid_amount' => $sched_paid,
                        'status'      => $sched_status,
                        'payment_id'  => $payment_id,
                    ],
                    [ 'id' => absint( $sched_row->id ) ],
                    [ '%f', '%s', '%d' ],
                    [ '%d' ]
                );
            }
        }

        // 4. Integración con módulo financiero
        $finance_tx_id = self::create_finance_transaction(
            $amount,
            $payment_date,
            $payment_method,
            $reference_number,
            $installment_num,
            $student,
            $course,
            $payment_id
        );

        // Guardar finance_tx_id si se creó
        if ( $finance_tx_id ) {
            $wpdb->update(
                $payments_table,
                [ 'finance_tx_id' => $finance_tx_id ],
                [ 'id' => $payment_id ],
                [ '%d' ],
                [ '%d' ]
            );
        }

        // 5. Notificación opcional
        if ( class_exists( 'Aura_Students_Notifications' ) ) {
            Aura_Students_Notifications::send_payment_receipt( (int) $enrollment->student_id, $payment_id );
        }

        wp_send_json_success( [
            'message'        => __( 'Pago registrado correctamente.', 'aura-suite' ),
            'payment_id'     => $payment_id,
            'new_total'      => $new_total,
            'new_balance'    => $new_balance,
            'payment_status' => $new_payment_status,
            'finance_tx_id'  => $finance_tx_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: RESUMEN DE PAGOS DE UN ESTUDIANTE
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_payment_summary(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_payments_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $student_id     = isset( $_POST['student_id'] ) ? absint( $_POST['student_id'] ) : 0;
        $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
        $schedule_table = $wpdb->prefix . 'aura_student_installment_schedule';

        if ( ! $student_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de estudiante inválido.', 'aura-suite' ) ] );
        }

        $summary = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(net_cost)   AS total_net,
                SUM(total_paid) AS total_paid,
                SUM(balance_due) AS total_balance,
                COUNT(*)         AS enrollment_count,
                SUM(CASE WHEN payment_status = 'paid'    THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
                SUM(CASE WHEN payment_status = 'unpaid'  THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN payment_status = 'overdue' THEN 1 ELSE 0 END) AS overdue_count
             FROM `{$enroll_table}`
             WHERE student_id = %d AND status != 'withdrawn'",
            $student_id
        ) );

        // Cuotas vencidas (due_date < hoy y status != paid)
        $overdue_installments = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$schedule_table}` sch
             INNER JOIN `{$enroll_table}` e ON e.id = sch.enrollment_id
             WHERE e.student_id = %d AND sch.due_date < CURDATE() AND sch.status NOT IN ('paid')",
            $student_id
        ) );

        wp_send_json_success( [
            'summary'             => $summary,
            'overdue_installments' => (int) $overdue_installments,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTAR INSCRIPCIONES CON ESTADO DE PAGOS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_payments(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_payments_view_all' ) &&
            ! current_user_can( 'aura_students_payments_register' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $enroll_table   = $wpdb->prefix . 'aura_student_enrollments';
        $students_table = $wpdb->prefix . 'aura_students';
        $courses_table  = $wpdb->prefix . 'aura_student_courses';
        $areas_table    = $wpdb->prefix . 'aura_areas';
        $schedule_table = $wpdb->prefix . 'aura_student_installment_schedule';

        $per_page      = 20;
        $page          = isset( $_POST['page'] )          ? max( 1, absint( $_POST['page'] ) )           : 1;
        $offset        = ( $page - 1 ) * $per_page;
        $search        = isset( $_POST['search'] )        ? sanitize_text_field( $_POST['search'] )       : '';
        $course_filter = isset( $_POST['course_id'] )     ? absint( $_POST['course_id'] )                 : 0;
        $area_filter   = isset( $_POST['area_id'] )       ? absint( $_POST['area_id'] )                   : 0;
        $pay_status    = isset( $_POST['pay_status'] )    ? sanitize_text_field( $_POST['pay_status'] )   : '';
        $month         = isset( $_POST['month'] )         ? absint( $_POST['month'] )                     : 0;
        $year          = isset( $_POST['year'] )          ? absint( $_POST['year'] )                      : 0;

        $valid_pay_statuses = [ 'unpaid', 'partial', 'paid', 'overdue' ];
        if ( $pay_status && ! in_array( $pay_status, $valid_pay_statuses, true ) ) {
            $pay_status = '';
        }

        $where  = "WHERE e.status NOT IN ('withdrawn')";
        $params = [];

        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where   .= ' AND (s.first_name LIKE %s OR s.last_name LIKE %s OR s.email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $course_filter ) {
            $where   .= ' AND e.course_id = %d';
            $params[] = $course_filter;
        }

        if ( $area_filter ) {
            $where   .= ' AND c.area_id = %d';
            $params[] = $area_filter;
        }

        if ( $pay_status ) {
            $where   .= ' AND e.payment_status = %s';
            $params[] = $pay_status;
        }

        if ( $month ) {
            $where   .= ' AND MONTH(e.enrollment_date) = %d';
            $params[] = $month;
        }

        if ( $year ) {
            $where   .= ' AND YEAR(e.enrollment_date) = %d';
            $params[] = $year;
        }

        // Verificar si existe tabla de áreas
        $areas_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $areas_table ) );
        $area_join    = $areas_exists ? "LEFT JOIN `{$areas_table}` a ON a.id = c.area_id" : '';
        $area_sel     = $areas_exists ? 'a.name AS area_name,' : "'' AS area_name,";

        // Subconsulta para cuotas vencidas por inscripción
        $overdue_subq = "( SELECT COUNT(*) FROM `{$schedule_table}` sch
                           WHERE sch.enrollment_id = e.id
                             AND sch.due_date < CURDATE()
                             AND sch.status NOT IN ('paid') ) AS overdue_count";

        // Próxima cuota pendiente
        $next_due_subq = "( SELECT MIN(sch.due_date) FROM `{$schedule_table}` sch
                            WHERE sch.enrollment_id = e.id
                              AND sch.status NOT IN ('paid') ) AS next_due_date";

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
                    e.scholarship_pct, e.scholarship_type, e.net_cost,
                    e.payment_scheme, e.installment_count,
                    e.total_paid, e.balance_due, e.payment_status,
                    e.first_payment_date,
                    s.first_name, s.last_name, s.email, s.photo_url,
                    c.name AS course_name, c.area_id, c.finance_cat_id,
                    {$area_sel}
                    {$overdue_subq},
                    {$next_due_subq}
             FROM `{$enroll_table}` e
             JOIN `{$students_table}` s ON s.id = e.student_id
             JOIN `{$courses_table}` c  ON c.id = e.course_id
             {$area_join}
             {$where}
             ORDER BY e.payment_status DESC, e.updated_at DESC
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
    // AJAX: OBTENER SCHEDULE DE CUOTAS DE UNA INSCRIPCIÓN
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_enrollment_installments(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_payments_view_all' ) &&
            ! current_user_can( 'aura_students_payments_register' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $schedule_table  = $wpdb->prefix . 'aura_student_installment_schedule';
        $payments_table  = $wpdb->prefix . 'aura_student_payments';
        $enroll_table    = $wpdb->prefix . 'aura_student_enrollments';

        $enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( $_POST['enrollment_id'] ) : 0;

        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        // Obtener enrollment para datos base
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT payment_scheme, net_cost, installment_count, first_payment_date, balance_due
             FROM `{$enroll_table}` WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        $installments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$schedule_table}` WHERE enrollment_id = %d ORDER BY installment_num ASC",
            $enrollment_id
        ) );

        // Si no hay schedule (pago único full), devolver cuota virtual
        if ( ! $installments ) {
            $installments = [ (object) [
                'id'              => 0,
                'enrollment_id'   => $enrollment_id,
                'installment_num' => 1,
                'due_date'        => $enrollment->first_payment_date ?: current_time( 'Y-m-d' ),
                'expected_amount' => $enrollment->net_cost,
                'paid_amount'     => 0,
                'status'          => 'pending',
                'payment_id'      => null,
            ] ];
        }

        // Historial de pagos recientes de esta inscripción
        $payment_history = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, payment_date, amount, payment_method, reference_number, installment_num, notes
             FROM `{$payments_table}` WHERE enrollment_id = %d ORDER BY payment_date DESC LIMIT 20",
            $enrollment_id
        ) );

        $today = current_time( 'Y-m-d' );
        foreach ( $installments as &$inst ) {
            // Indicador de urgencia
            if ( $inst->status === 'paid' ) {
                $inst->urgency = 'paid';
            } elseif ( $inst->due_date < $today ) {
                $inst->urgency = 'overdue';
            } elseif ( strtotime( $inst->due_date ) - strtotime( $today ) <= 7 * DAY_IN_SECONDS ) {
                $inst->urgency = 'soon';
            } else {
                $inst->urgency = 'ok';
            }
        }
        unset( $inst );

        wp_send_json_success( [
            'installments'    => $installments,
            'payment_history' => $payment_history,
            'enrollment'      => $enrollment,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ELIMINAR/REVERTIR PAGO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_payment(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo administradores pueden eliminar pagos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $payments_table = $wpdb->prefix . 'aura_student_payments';
        $enroll_table   = $wpdb->prefix . 'aura_student_installment_schedule';
        $enroll_t       = $wpdb->prefix . 'aura_student_enrollments';

        $payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;

        if ( ! $payment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        $payment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$payments_table}` WHERE id = %d",
            $payment_id
        ) );

        if ( ! $payment ) {
            wp_send_json_error( [ 'message' => __( 'Pago no encontrado.', 'aura-suite' ) ] );
        }

        // Revertir totales en enrollment
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$enroll_t}` WHERE id = %d",
            $payment->enrollment_id
        ) );

        if ( $enrollment ) {
            $rev_total   = max( 0, round( (float) $enrollment->total_paid - (float) $payment->amount, 2 ) );
            $rev_balance = round( (float) $enrollment->net_cost - $rev_total, 2 );

            if ( $rev_balance <= 0 ) {
                $rev_status = 'paid';
            } elseif ( $rev_total > 0 ) {
                $rev_status = 'partial';
            } else {
                $rev_status = 'unpaid';
            }

            $wpdb->update(
                $enroll_t,
                [
                    'total_paid'     => $rev_total,
                    'balance_due'    => $rev_balance,
                    'payment_status' => $rev_status,
                    'updated_at'     => current_time( 'mysql' ),
                ],
                [ 'id' => absint( $payment->enrollment_id ) ],
                [ '%f', '%f', '%s', '%s' ],
                [ '%d' ]
            );
        }

        // Revertir cuota en schedule
        if ( $payment->installment_num ) {
            $sched = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM `{$enroll_table}` WHERE enrollment_id = %d AND installment_num = %d AND payment_id = %d",
                $payment->enrollment_id,
                $payment->installment_num,
                $payment_id
            ) );

            if ( $sched ) {
                $rev_paid = max( 0, round( (float) $sched->paid_amount - (float) $payment->amount, 2 ) );
                $wpdb->update(
                    $enroll_table,
                    [
                        'paid_amount' => $rev_paid,
                        'status'      => $rev_paid > 0 ? 'partial' : 'pending',
                        'payment_id'  => null,
                    ],
                    [ 'id' => absint( $sched->id ) ],
                    [ '%f', '%s', null ],
                    [ '%d' ]
                );
            }
        }

        // Eliminar el pago
        $wpdb->delete( $payments_table, [ 'id' => $payment_id ], [ '%d' ] );

        wp_send_json_success( [ 'message' => __( 'Pago eliminado y totales revertidos.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER: CREAR TRANSACCIÓN FINANCIERA
    // ─────────────────────────────────────────────────────────────

    private static function create_finance_transaction(
        float  $amount,
        string $payment_date,
        string $payment_method,
        string $reference_number,
        int    $installment_num,
        ?object $student,
        ?object $course,
        int    $payment_id
    ): int {
        global $wpdb;

        // Solo integrar si el curso tiene categoría financiera
        if ( ! $course || ! $course->finance_cat_id ) {
            return 0;
        }

        $fin_table = $wpdb->prefix . 'aura_finance_transactions';

        // Guard: verificar que la tabla existe
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $fin_table ) );
        if ( ! $exists ) {
            return 0;
        }

        $student_name = $student
            ? trim( ( $student->first_name ?? '' ) . ' ' . ( $student->last_name ?? '' ) )
            : '';

        $description = $installment_num
            ? sprintf(
                /* translators: 1: installment number, 2: student name, 3: course name */
                __( 'Pago cuota #%1$d — %2$s / %3$s', 'aura-suite' ),
                $installment_num,
                $student_name,
                $course->name ?? ''
              )
            : sprintf(
                /* translators: 1: student name, 2: course name */
                __( 'Pago — %1$s / %2$s', 'aura-suite' ),
                $student_name,
                $course->name ?? ''
              );

        $wpdb->insert(
            $fin_table,
            [
                'transaction_type' => 'income',
                'category_id'      => absint( $course->finance_cat_id ),
                'amount'           => $amount,
                'transaction_date' => $payment_date,
                'description'      => $description,
                'status'           => 'approved',
                'payment_method'   => $payment_method,
                'reference_number' => $reference_number,
                'related_module'   => 'students',
                'related_item_id'  => $payment_id,
                'related_action'   => 'payment',
                'created_by'       => get_current_user_id(),
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ],
            [
                '%s', '%d', '%f', '%s', '%s',
                '%s', '%s', '%s',
                '%s', '%d', '%s', '%d',
                '%s', '%s',
            ]
        );

        return (int) $wpdb->insert_id;
    }
}
