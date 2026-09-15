<?php
/**
 * Gestión de Becas — Fase 7
 *
 * Asignación, modificación y consulta de becas internas y externas
 * sobre inscripciones activas de estudiantes.
 *
 * Cuando se asigna/cambia una beca:
 *  1. Se recalcula net_cost = base_cost * (1 - pct/100)
 *  2. Se recalcula balance_due = max(0, net_cost - total_paid)
 *  3. Si el esquema es 'installments', se eliminan las cuotas
 *     pendientes y se regeneran con el nuevo monto.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Scholarships {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_assign_scholarship',  [ __CLASS__, 'ajax_assign_scholarship' ] );
        add_action( 'wp_ajax_aura_students_remove_scholarship',  [ __CLASS__, 'ajax_remove_scholarship' ] );
        add_action( 'wp_ajax_aura_students_list_scholarships',   [ __CLASS__, 'ajax_list_scholarships' ] );
        add_action( 'wp_ajax_aura_students_scholarship_stats',   [ __CLASS__, 'ajax_scholarship_stats' ] );
        add_action( 'wp_ajax_aura_students_get_enrollment_for_scholarship', [ __CLASS__, 'ajax_get_enrollment' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render_scholarships(): void {
        if (
            ! current_user_can( 'aura_students_scholarships_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/students/scholarships.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ASIGNAR / EDITAR BECA
    // ─────────────────────────────────────────────────────────────

    public static function ajax_assign_scholarship(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_scholarships_assign' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para asignar becas.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $enrollment_id      = (int) ( $_POST['enrollment_id'] ?? 0 );
        $scholarship_type   = sanitize_key( $_POST['scholarship_type'] ?? 'none' );
        $scholarship_pct    = max( 0, min( 100, (int) ( $_POST['scholarship_pct'] ?? 0 ) ) );
        $scholarship_sponsor= sanitize_text_field( $_POST['scholarship_sponsor'] ?? '' );
        $scholarship_notes  = sanitize_textarea_field( $_POST['scholarship_notes'] ?? '' );

        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de inscripción inválido.', 'aura-suite' ) ] );
        }

        $allowed_types = [ 'none', 'internal', 'external' ];
        if ( ! in_array( $scholarship_type, $allowed_types, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Tipo de beca inválido.', 'aura-suite' ) ] );
        }

        if ( $scholarship_type === 'external' && empty( $scholarship_sponsor ) ) {
            wp_send_json_error( [ 'message' => __( 'El campo "Patrocinador" es obligatorio para becas externas.', 'aura-suite' ) ] );
        }

        $te = $wpdb->prefix . 'aura_student_enrollments';

        // Obtener inscripción actual
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$te} WHERE id = %d AND status IN ('active','pending')",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada o inactiva.', 'aura-suite' ) ] );
        }

        // ── Calcular nuevo net_cost ──
        $base_cost    = (float) $enrollment->base_cost;
        $total_paid   = (float) $enrollment->total_paid;
        $new_net_cost = round( $base_cost * ( 1 - $scholarship_pct / 100 ), 2 );

        // Ajuste si el total ya pagado supera el nuevo costo neto
        $new_balance  = max( 0.0, $new_net_cost - $total_paid );
        $new_pmt_status = self::calculate_payment_status( $total_paid, $new_net_cost );

        // ── Actualizar inscripción ──
        $update_data = [
            'scholarship_type'    => $scholarship_type,
            'scholarship_pct'     => $scholarship_pct,
            'scholarship_sponsor' => $scholarship_sponsor ?: null,
            'scholarship_notes'   => $scholarship_notes ?: null,
            'net_cost'            => $new_net_cost,
            'balance_due'         => $new_balance,
            'payment_status'      => $new_pmt_status,
        ];

        // Recalcular monto por cuota si el esquema es 'installments'
        $new_installment_amount = (float) $enrollment->installment_amount;
        if ( $enrollment->payment_scheme === 'installments' && $enrollment->installment_count > 0 ) {
            $unpaid_count = self::get_pending_installment_count( $enrollment_id );
            if ( $unpaid_count > 0 ) {
                $new_installment_amount = round( $new_balance / $unpaid_count, 2 );
            } elseif ( $new_balance <= 0 ) {
                $new_installment_amount = 0.00;
            }
            $update_data['installment_amount'] = $new_installment_amount;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $updated = $wpdb->update( $te, $update_data, [ 'id' => $enrollment_id ], null, [ '%d' ] );

        if ( $updated === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al actualizar la inscripción.', 'aura-suite' ) ] );
        }

        // ── Regenerar schedule de cuotas pendientes ──
        if ( $enrollment->payment_scheme === 'installments' ) {
            self::regenerate_pending_installments( $enrollment_id, $new_balance, $new_net_cost );
        }

        wp_send_json_success( [
            'message'       => sprintf(
                __( 'Beca del %d%% asignada correctamente. Nuevo costo neto: $%s.', 'aura-suite' ),
                $scholarship_pct,
                number_format( $new_net_cost, 2 )
            ),
            'new_net_cost'  => $new_net_cost,
            'new_balance'   => $new_balance,
            'payment_status'=> $new_pmt_status,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: QUITAR BECA
    // ─────────────────────────────────────────────────────────────

    public static function ajax_remove_scholarship(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_scholarships_assign' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $enrollment_id = (int) ( $_POST['enrollment_id'] ?? 0 );
        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de inscripción inválido.', 'aura-suite' ) ] );
        }

        $te = $wpdb->prefix . 'aura_student_enrollments';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $enrollment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$te} WHERE id = %d",
            $enrollment_id
        ) );

        if ( ! $enrollment ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        // Restaurar base_cost como net_cost
        $base_cost   = (float) $enrollment->base_cost;
        $total_paid  = (float) $enrollment->total_paid;
        $new_balance = max( 0.0, $base_cost - $total_paid );
        $new_status  = self::calculate_payment_status( $total_paid, $base_cost );

        $new_installment_amount = (float) $enrollment->installment_amount;
        $update_data = [
            'scholarship_type'    => 'none',
            'scholarship_pct'     => 0,
            'scholarship_sponsor' => null,
            'scholarship_notes'   => null,
            'net_cost'            => $base_cost,
            'balance_due'         => $new_balance,
            'payment_status'      => $new_status,
        ];

        if ( $enrollment->payment_scheme === 'installments' && $enrollment->installment_count > 0 ) {
            $unpaid_count = self::get_pending_installment_count( $enrollment_id );
            if ( $unpaid_count > 0 ) {
                $new_installment_amount = round( $new_balance / $unpaid_count, 2 );
            }
            $update_data['installment_amount'] = $new_installment_amount;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update( $te, $update_data, [ 'id' => $enrollment_id ], null, [ '%d' ] );

        if ( $enrollment->payment_scheme === 'installments' ) {
            self::regenerate_pending_installments( $enrollment_id, $new_balance, $base_cost );
        }

        wp_send_json_success( [
            'message'     => __( 'Beca eliminada. Se restauró el costo base.', 'aura-suite' ),
            'new_net_cost'=> $base_cost,
            'new_balance' => $new_balance,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: LISTAR BECAS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_scholarships(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_scholarships_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $ts  = $wpdb->prefix . 'aura_students';
        $te  = $wpdb->prefix . 'aura_student_enrollments';
        $tc  = $wpdb->prefix . 'aura_student_courses';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ts}'" ) !== $ts ) {
            wp_send_json_success( [ 'rows' => [], 'page' => 1, 'total_pages' => 1, 'total' => 0 ] );
        }

        $show_all      = (bool) ( $_POST['show_all'] ?? false );
        $sch_type      = sanitize_key( $_POST['sch_type'] ?? '' );      // internal|external|''
        $min_pct       = max( 0, (int) ( $_POST['min_pct'] ?? 0 ) );
        $course_id     = (int) ( $_POST['course_id'] ?? 0 );
        $search        = sanitize_text_field( $_POST['search'] ?? '' );
        $per_page      = 20;
        $page          = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $offset        = ( $page - 1 ) * $per_page;

        // Guard: áreas
        $areas_table  = $wpdb->prefix . 'aura_areas';
        $areas_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $areas_table ) );

        $area_join  = '';
        $area_col   = "'' AS area_name";
        if ( $areas_exists ) {
            $area_join = "LEFT JOIN `{$areas_table}` ar ON ar.id = c.area_id";
            $area_col  = 'ar.name AS area_name';
        }

        // Construir WHERE
        $where = "WHERE s.deleted_at IS NULL AND e.status IN ('active','pending')";

        if ( ! $show_all ) {
            $where .= " AND e.scholarship_type != 'none'";
        }
        if ( $sch_type ) {
            $where .= $wpdb->prepare( ' AND e.scholarship_type = %s', $sch_type );
        }
        if ( $min_pct > 0 ) {
            $where .= $wpdb->prepare( ' AND e.scholarship_pct >= %d', $min_pct );
        }
        if ( $course_id ) {
            $where .= $wpdb->prepare( ' AND e.course_id = %d', $course_id );
        }
        if ( $search ) {
            $like  = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare(
                ' AND (s.first_name LIKE %s OR s.last_name LIKE %s OR s.email LIKE %s)',
                $like, $like, $like
            );
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                e.id AS enrollment_id,
                e.scholarship_type, e.scholarship_pct, e.scholarship_sponsor, e.scholarship_notes,
                e.base_cost, e.net_cost, e.total_paid, e.balance_due, e.payment_status,
                e.payment_scheme, e.installment_count,
                s.id AS student_id,
                s.first_name, s.last_name, s.email,
                c.id AS course_id, c.name AS course_name,
                {$area_col},
                (e.base_cost - e.net_cost) AS discount_amount
             FROM {$te} e
             JOIN {$ts} s ON s.id = e.student_id
             JOIN {$tc} c ON c.id = e.course_id
             {$area_join}
             {$where}
             ORDER BY e.scholarship_pct DESC, e.updated_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$te} e
             JOIN {$ts} s ON s.id = e.student_id
             JOIN {$tc} c ON c.id = e.course_id
             {$area_join}
             {$where}"
        );
        // phpcs:enable

        wp_send_json_success( [
            'rows'        => $rows,
            'page'        => $page,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
            'total'       => $total,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ESTADÍSTICAS DE BECAS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_scholarship_stats(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_scholarships_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $te = $wpdb->prefix . 'aura_student_enrollments';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$te}'" ) !== $te ) {
            wp_send_json_success( self::empty_stats() );
        }

        $year  = (int) current_time( 'Y' );
        $month = current_time( 'Y-m' );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$te}
             WHERE scholarship_type != 'none' AND status IN ('active','pending')"
        );
        $total_discount_year = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(base_cost - net_cost), 0) FROM {$te}
             WHERE scholarship_type != 'none' AND status IN ('active','pending') AND YEAR(created_at) = %d",
            $year
        ) );
        $total_discount_all = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(base_cost - net_cost), 0) FROM {$te}
             WHERE scholarship_type != 'none' AND status IN ('active','pending')"
        );
        $internal_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$te}
             WHERE scholarship_type = 'internal' AND status IN ('active','pending')"
        );
        $internal_discount = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(base_cost - net_cost), 0) FROM {$te}
             WHERE scholarship_type = 'internal' AND status IN ('active','pending')"
        );
        $external_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$te}
             WHERE scholarship_type = 'external' AND status IN ('active','pending')"
        );
        $external_discount = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(base_cost - net_cost), 0) FROM {$te}
             WHERE scholarship_type = 'external' AND status IN ('active','pending')"
        );

        // Distribución por % (agrupada en brackets)
        $pct_distribution = $wpdb->get_results(
            "SELECT scholarship_pct AS pct, COUNT(*) AS total,
                    COALESCE(SUM(base_cost - net_cost), 0) AS discount_total
             FROM {$te}
             WHERE scholarship_type != 'none' AND status IN ('active','pending')
             GROUP BY scholarship_pct
             ORDER BY scholarship_pct DESC"
        );
        // phpcs:enable

        wp_send_json_success( [
            'total_active'        => $total_active,
            'total_discount_year' => $total_discount_year,
            'total_discount_all'  => $total_discount_all,
            'internal_count'      => $internal_count,
            'internal_discount'   => $internal_discount,
            'external_count'      => $external_count,
            'external_discount'   => $external_discount,
            'pct_distribution'    => $pct_distribution,
            'year'                => $year,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: OBTENER DATOS DE INSCRIPCIÓN (para pre-llenar modal)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_enrollment(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_scholarships_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;

        $enrollment_id = (int) ( $_POST['enrollment_id'] ?? 0 );
        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        $te = $wpdb->prefix . 'aura_student_enrollments';
        $ts = $wpdb->prefix . 'aura_students';
        $tc = $wpdb->prefix . 'aura_student_courses';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.id, e.scholarship_type, e.scholarship_pct, e.scholarship_sponsor,
                    e.scholarship_notes, e.base_cost, e.net_cost, e.total_paid, e.payment_scheme,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    c.name AS course_name
             FROM {$te} e
             JOIN {$ts} s ON s.id = e.student_id
             JOIN {$tc} c ON c.id = e.course_id
             WHERE e.id = %d",
            $enrollment_id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        wp_send_json_success( $row );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Cuenta las cuotas pendientes (no pagadas) de una inscripción.
     */
    private static function get_pending_installment_count( int $enrollment_id ): int {
        global $wpdb;
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tis ) ) !== $tis ) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tis}
             WHERE enrollment_id = %d AND status NOT IN ('paid')",
            $enrollment_id
        ) );
    }

    /**
     * Elimina cuotas pendientes y regenera con nuevo monto distribuido.
     *
     * Cuotas ya pagadas se conservan intactas.
     * El monto restante (new_balance) se distribuye entre las cuotas pendientes.
     */
    private static function regenerate_pending_installments( int $enrollment_id, float $new_balance, float $new_net_cost ): void {
        global $wpdb;
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tis ) ) !== $tis ) {
            return;
        }

        // Obtener cuotas pendientes con sus fechas de vencimiento (para reutilizar las fechas)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, installment_num, due_date FROM {$tis}
             WHERE enrollment_id = %d AND status NOT IN ('paid')
             ORDER BY installment_num ASC",
            $enrollment_id
        ) );

        if ( empty( $pending ) ) {
            return;
        }

        $pending_count = count( $pending );

        // Si el balance es 0 o beca 100%, marcar todas como pagadas con expected=0
        if ( $new_balance <= 0 || $new_net_cost <= 0 ) {
            foreach ( $pending as $inst ) {
                $wpdb->update(
                    $tis,
                    [ 'expected_amount' => 0.00, 'status' => 'paid' ],
                    [ 'id' => $inst->id ],
                    [ '%f', '%s' ],
                    [ '%d' ]
                );
            }
            return;
        }

        // Distribuir new_balance entre cuotas pendientes
        $per_installment = round( $new_balance / $pending_count, 2 );
        $remainder       = round( $new_balance - ( $per_installment * $pending_count ), 2 );

        foreach ( $pending as $idx => $inst ) {
            // El último recibe el ajuste por redondeo
            $amount = $per_installment;
            if ( $idx === $pending_count - 1 && $remainder != 0 ) {
                $amount = round( $amount + $remainder, 2 );
            }
            $wpdb->update(
                $tis,
                [
                    'expected_amount' => $amount,
                    'status'          => ( $inst->due_date < current_time( 'Y-m-d' ) ) ? 'overdue' : 'pending',
                ],
                [ 'id' => $inst->id ],
                [ '%f', '%s' ],
                [ '%d' ]
            );
        }
    }

    /**
     * Calcula el estado de pago.
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

    /**
     * Retorna estadísticas vacías (tabla no existe aún).
     */
    private static function empty_stats(): array {
        return [
            'total_active'        => 0,
            'total_discount_year' => 0.0,
            'total_discount_all'  => 0.0,
            'internal_count'      => 0,
            'internal_discount'   => 0.0,
            'external_count'      => 0,
            'external_discount'   => 0.0,
            'pct_distribution'    => [],
            'year'                => (int) current_time( 'Y' ),
        ];
    }
}
