<?php
/**
 * Dashboard del Módulo de Estudiantes e Inscripciones
 *
 * KPIs en tiempo real, gráficos ApexCharts y páginas "próximamente"
 * para fases aún no implementadas.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Dashboard {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_dashboard_kpis',       [ __CLASS__, 'ajax_kpis' ] );
        add_action( 'wp_ajax_aura_students_dashboard_charts',     [ __CLASS__, 'ajax_charts' ] );
        add_action( 'wp_ajax_aura_students_recent_activity',       [ __CLASS__, 'ajax_recent_activity' ] );
        add_action( 'wp_ajax_aura_students_paz_salvo_list',        [ __CLASS__, 'ajax_paz_salvo_list' ] );
        add_action( 'wp_ajax_aura_students_send_reminder',         [ __CLASS__, 'ajax_send_reminder' ] );
        add_action( 'wp_ajax_aura_students_export_debtors',        [ __CLASS__, 'ajax_export_debtors' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER PRINCIPAL
    // ─────────────────────────────────────────────────────────────

    public static function render(): void {
        if (
            ! current_user_can( 'aura_students_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/students/dashboard.php';
    }

    /**
     * Página provisional para secciones aún no implementadas.
     */
    public static function render_coming_soon(): void {
        $page      = sanitize_key( $_GET['page'] ?? '' );
        $labels    = [
            'aura-students-list'        => __( 'Listado de Estudiantes', 'aura-suite' ),
            'aura-students-new'         => __( 'Nuevo Estudiante', 'aura-suite' ),
            'aura-students-courses'     => __( 'Cursos y Programas', 'aura-suite' ),
            'aura-students-enrollments' => __( 'Inscripciones', 'aura-suite' ),
            'aura-students-payments'    => __( 'Pagos y Cuotas', 'aura-suite' ),
            'aura-students-scholarships'=> __( 'Becas', 'aura-suite' ),
            'aura-students-paz-salvo'   => __( 'Paz y Salvo', 'aura-suite' ),
            'aura-students-reports'     => __( 'Reportes', 'aura-suite' ),
            'aura-students-settings'    => __( 'Configuración', 'aura-suite' ),
        ];
        $title = $labels[ $page ] ?? __( 'Sección', 'aura-suite' );
        ?>
        <div class="wrap aura-students-coming-soon">
            <h1>🎓 <?php echo esc_html( $title ); ?></h1>
            <div class="aura-stu-notice-box">
                <span class="dashicons dashicons-hammer" style="font-size:48px;color:#8b5cf6;display:block;margin-bottom:12px;"></span>
                <h2><?php _e( 'En desarrollo — Próximamente', 'aura-suite' ); ?></h2>
                <p><?php _e( 'Esta sección se implementará en la próxima fase del módulo de Estudiantes.', 'aura-suite' ); ?></p>
                <a href="<?php echo admin_url( 'admin.php?page=aura-students' ); ?>" class="button button-primary">
                    ← <?php _e( 'Volver al Dashboard', 'aura-suite' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER PAZ Y SALVO
    // ─────────────────────────────────────────────────────────────

    public static function render_paz_salvo(): void {
        if (
            ! current_user_can( 'aura_students_status_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/students/paz-salvo.php';
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: GRÁFICOS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_charts(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $tp  = $wpdb->prefix . 'aura_student_payments';
        $te  = $wpdb->prefix . 'aura_student_enrollments';
        $ts  = $wpdb->prefix . 'aura_students';
        $tc  = $wpdb->prefix . 'aura_student_courses';

        // Verificar tabla existe
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ts}'" ) !== $ts ) {
            wp_send_json_success( [ 'payments_vs_projected' => [], 'profile_dist' => [], 'enrollments_by_month' => [] ] );
        }

        // ── 1. Barras: pagos recibidos vs saldo proyectado (últimos 6 meses) ──
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $payments_monthly = $wpdb->get_results(
            "SELECT DATE_FORMAT(payment_date,'%Y-%m') AS ym, SUM(amount) AS total_paid
             FROM {$tp}
             WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym
             ORDER BY ym ASC"
        );
        $projected_monthly = $wpdb->get_results(
            "SELECT DATE_FORMAT(e.created_at,'%Y-%m') AS ym, SUM(e.net_cost) AS total_projected
             FROM {$te} e
             WHERE e.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
               AND e.status = 'active'
             GROUP BY ym
             ORDER BY ym ASC"
        );

        // ── 2. Dona: distribución por tipo de perfil ──
        $profile_dist = $wpdb->get_results(
            "SELECT profile_type, COUNT(*) AS total
             FROM {$ts}
             WHERE status NOT IN ('rejected') AND deleted_at IS NULL
             GROUP BY profile_type
             ORDER BY total DESC"
        );

        // ── 3. Línea: nuevas inscripciones por mes (últimos 6 meses) ──
        $enrollments_by_month = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS total
             FROM {$te}
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym
             ORDER BY ym ASC"
        );
        // phpcs:enable

        // Normalizar a los últimos 6 meses (incluir meses vacíos)
        $months = [];
        for ( $i = 5; $i >= 0; $i-- ) {
            $months[] = date( 'Y-m', strtotime( "-{$i} months" ) );
        }

        $paid_map      = wp_list_pluck( $payments_monthly, 'total_paid', 'ym' );
        $projected_map = wp_list_pluck( $projected_monthly, 'total_projected', 'ym' );
        $enroll_map    = wp_list_pluck( $enrollments_by_month, 'total', 'ym' );

        $bars = [];
        foreach ( $months as $ym ) {
            $bars[] = [
                'month'          => $ym,
                'total_paid'     => (float) ( $paid_map[ $ym ] ?? 0 ),
                'total_projected'=> (float) ( $projected_map[ $ym ] ?? 0 ),
                'new_enrollments'=> (int) ( $enroll_map[ $ym ] ?? 0 ),
            ];
        }

        wp_send_json_success( [
            'bars'         => $bars,
            'profile_dist' => array_map( static function( $r ) {
                return [ 'type' => $r->profile_type, 'total' => (int) $r->total ];
            }, $profile_dist ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ÚLTIMAS ACTIVIDADES
    // ─────────────────────────────────────────────────────────────

    public static function ajax_recent_activity(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $ts  = $wpdb->prefix . 'aura_students';
        $te  = $wpdb->prefix . 'aura_student_enrollments';
        $tp  = $wpdb->prefix . 'aura_student_payments';
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';
        $tc  = $wpdb->prefix . 'aura_student_courses';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ts}'" ) !== $ts ) {
            wp_send_json_success( [ 'activities' => [] ] );
        }

        $activities = [];

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Nuevos postulantes (últimos 10)
        $applicants = $wpdb->get_results(
            "SELECT 'applicant' AS event_type, CONCAT(first_name,' ',last_name) AS name, email, created_at
             FROM {$ts}
             WHERE status = 'applicant' AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 5"
        );
        foreach ( $applicants as $r ) {
            $activities[] = [
                'type'    => 'applicant',
                'icon'    => '⏳',
                'message' => sprintf( __( 'Nueva solicitud de <strong>%s</strong>', 'aura-suite' ), esc_html( $r->name ) ),
                'time'    => $r->created_at,
            ];
        }

        // Pagos recientes (últimos 5)
        $payments = $wpdb->get_results(
            "SELECT p.id, p.amount, p.payment_date, p.created_at,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    c.name AS course_name
             FROM {$tp} p
             JOIN {$ts} s ON s.id = p.student_id
             JOIN {$tc} c ON c.id = p.course_id
             ORDER BY p.created_at DESC LIMIT 5"
        );
        foreach ( $payments as $r ) {
            $activities[] = [
                'type'    => 'payment',
                'icon'    => '💰',
                'message' => sprintf(
                    __( 'Pago de <strong>%s</strong> por $%s en %s', 'aura-suite' ),
                    esc_html( $r->student_name ),
                    number_format( (float) $r->amount, 2 ),
                    esc_html( $r->course_name )
                ),
                'time'    => $r->created_at,
            ];
        }

        // Inscripciones aprobadas/activas recientes (últimas 5)
        $enrollments = $wpdb->get_results(
            "SELECT e.created_at,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    c.name AS course_name
             FROM {$te} e
             JOIN {$ts} s ON s.id = e.student_id
             JOIN {$tc} c ON c.id = e.course_id
             WHERE e.status IN ('active','completed')
             ORDER BY e.created_at DESC LIMIT 5"
        );
        foreach ( $enrollments as $r ) {
            $activities[] = [
                'type'    => 'enrollment',
                'icon'    => '✅',
                'message' => sprintf(
                    __( '<strong>%s</strong> inscrito en %s', 'aura-suite' ),
                    esc_html( $r->student_name ),
                    esc_html( $r->course_name )
                ),
                'time'    => $r->created_at,
            ];
        }

        // Cuotas vencidas recientes (últimas 5)
        $overdue = $wpdb->get_results(
            "SELECT i.due_date, i.expected_amount, i.created_at,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    c.name AS course_name
             FROM {$tis} i
             JOIN {$te} e ON e.id = i.enrollment_id
             JOIN {$ts} s ON s.id = e.student_id
             JOIN {$tc} c ON c.id = e.course_id
             WHERE i.status = 'overdue'
             ORDER BY i.due_date DESC LIMIT 5"
        );
        foreach ( $overdue as $r ) {
            $activities[] = [
                'type'    => 'overdue',
                'icon'    => '🔴',
                'message' => sprintf(
                    __( 'Cuota vencida de <strong>%s</strong> en %s (vencía %s)', 'aura-suite' ),
                    esc_html( $r->student_name ),
                    esc_html( $r->course_name ),
                    esc_html( $r->due_date )
                ),
                'time'    => $r->due_date . ' 00:00:00',
            ];
        }
        // phpcs:enable

        // Ordenar por tiempo desc y tomar los 10 más recientes
        usort( $activities, static function( $a, $b ) {
            return strcmp( $b['time'], $a['time'] );
        } );

        wp_send_json_success( [ 'activities' => array_slice( $activities, 0, 10 ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: PAZ Y SALVO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_paz_salvo_list(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_status_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $ts  = $wpdb->prefix . 'aura_students';
        $te  = $wpdb->prefix . 'aura_student_enrollments';
        $tc  = $wpdb->prefix . 'aura_student_courses';
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ts}'" ) !== $ts ) {
            wp_send_json_success( [ 'rows' => [] ] );
        }

        $filter      = sanitize_key( $_POST['filter'] ?? '' );          // '' | 'debtors' | 'current'
        $search      = sanitize_text_field( $_POST['search'] ?? '' );
        $course_id   = (int) ( $_POST['course_id'] ?? 0 );
        $per_page    = 20;
        $page        = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $offset      = ( $page - 1 ) * $per_page;

        $where = "WHERE s.status = 'active' AND s.deleted_at IS NULL AND e.status = 'active'";

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (s.first_name LIKE %s OR s.last_name LIKE %s OR s.email LIKE %s)", $like, $like, $like );
        }
        if ( $course_id ) {
            $where .= $wpdb->prepare( ' AND e.course_id = %d', $course_id );
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                s.id AS student_id,
                s.wp_user_id,
                s.first_name, s.last_name, s.email, s.phone, s.phone_country,
                e.id AS enrollment_id,
                e.balance_due, e.total_paid, e.net_cost, e.payment_status,
                c.name AS course_name,
                (
                    SELECT COUNT(*)
                    FROM {$tis} i
                    WHERE i.enrollment_id = e.id AND i.status = 'overdue'
                ) AS overdue_count,
                (
                    SELECT MIN(i2.due_date)
                    FROM {$tis} i2
                    WHERE i2.enrollment_id = e.id AND i2.status NOT IN ('paid') AND i2.due_date < CURDATE()
                ) AS oldest_overdue_date
             FROM {$ts} s
             JOIN {$te} e ON e.student_id = s.id
             JOIN {$tc} c ON c.id = e.course_id
             {$where}
             ORDER BY overdue_count DESC, e.balance_due DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$ts} s
             JOIN {$te} e ON e.student_id = s.id
             JOIN {$tc} c ON c.id = e.course_id
             {$where}"
        );
        // phpcs:enable

        // Filtro post-query (debtors / current)
        if ( $filter === 'debtors' ) {
            $rows = array_filter( $rows, static fn( $r ) => (float) $r->balance_due > 0 || (int) $r->overdue_count > 0 );
        } elseif ( $filter === 'current' ) {
            $rows = array_filter( $rows, static fn( $r ) => (float) $r->balance_due <= 0 && (int) $r->overdue_count === 0 );
        }

        // F7.5 — Integración con Biblioteca: verificar préstamos vencidos y multas pendientes
        $library_enabled = class_exists( 'Aura_Library_Loans' )
            && get_option( 'aura_library_paz_y_salvo', false );

        foreach ( $rows as $row ) {
            $row->lib_overdue = false;
            $row->lib_fines   = false;
            if ( $library_enabled && ! empty( $row->wp_user_id ) ) {
                $uid              = (int) $row->wp_user_id;
                $row->lib_overdue = Aura_Library_Loans::has_overdue_loans( $uid );
                $row->lib_fines   = Aura_Library_Loans::has_unpaid_fines( $uid );
            }
        }

        wp_send_json_success( [
            'rows'            => array_values( $rows ),
            'page'            => $page,
            'total_pages'     => max( 1, (int) ceil( $total / $per_page ) ),
            'total'           => $total,
            'library_enabled' => $library_enabled,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: ENVIAR RECORDATORIO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_send_reminder(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_status_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $enrollment_id = (int) ( $_POST['enrollment_id'] ?? 0 );
        $channel       = sanitize_key( $_POST['channel'] ?? 'email' );

        if ( ! $enrollment_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de inscripción inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $te = $wpdb->prefix . 'aura_student_enrollments';
        $ts = $wpdb->prefix . 'aura_students';
        $tc = $wpdb->prefix . 'aura_student_courses';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $data = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.first_name, s.last_name, s.email, s.phone, s.phone_country,
                    e.balance_due, e.overdue_count,
                    c.name AS course_name
             FROM {$te} e
             JOIN {$ts} s ON s.id = e.student_id
             JOIN {$tc} c ON c.id = e.course_id
             WHERE e.id = %d",
            $enrollment_id
        ) );
        // phpcs:enable

        if ( ! $data ) {
            wp_send_json_error( [ 'message' => __( 'Inscripción no encontrada.', 'aura-suite' ) ] );
        }

        $sent = false;

        if ( $channel === 'email' && $data->email ) {
            $subject = sprintf( __( '[CEM] Recordatorio de pago - %s', 'aura-suite' ), $data->course_name );
            $message = sprintf(
                __( "Hola %s,\n\nTe recordamos que tienes un saldo pendiente de \$%s en el curso \"%s\".\n\nPor favor contáctanos para regularizar tu situación.\n\nCentro de Estudio Musical (CEM)", 'aura-suite' ),
                esc_html( $data->first_name ),
                number_format( (float) $data->balance_due, 2 ),
                esc_html( $data->course_name )
            );
            $sent = wp_mail( $data->email, $subject, $message );

        } elseif ( $channel === 'whatsapp' && class_exists( 'Aura_Notifications' ) && $data->phone ) {
            $wa_msg = sprintf(
                __( "Hola %s, recuerda que tienes un saldo pendiente de \$%s en el curso \"%s\". Contáctanos para ponerte al día. — CEM", 'aura-suite' ),
                $data->first_name,
                number_format( (float) $data->balance_due, 2 ),
                $data->course_name
            );
            $phone = ( $data->phone_country ?? '+1' ) . preg_replace( '/\D/', '', $data->phone );
            Aura_Notifications::send_whatsapp( $phone, $wa_msg );
            $sent = true;
        }

        if ( $sent ) {
            wp_send_json_success( [ 'message' => __( 'Recordatorio enviado correctamente.', 'aura-suite' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'No se pudo enviar el recordatorio. Verifica que el estudiante tenga canal de contacto configurado.', 'aura-suite' ) ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: EXPORTAR MOROSOS (CSV simple)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_debtors(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_students_status_view' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $ts  = $wpdb->prefix . 'aura_students';
        $te  = $wpdb->prefix . 'aura_student_enrollments';
        $tc  = $wpdb->prefix . 'aura_student_courses';
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ts}'" ) !== $ts ) {
            wp_send_json_error( [ 'message' => __( 'Tabla no encontrada.', 'aura-suite' ) ] );
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT
                s.first_name, s.last_name, s.email, s.phone,
                c.name AS course_name,
                e.net_cost, e.total_paid, e.balance_due, e.payment_status,
                (SELECT COUNT(*) FROM {$tis} i WHERE i.enrollment_id = e.id AND i.status = 'overdue') AS overdue_count,
                (SELECT MIN(i2.due_date) FROM {$tis} i2 WHERE i2.enrollment_id = e.id AND i2.status NOT IN ('paid') AND i2.due_date < CURDATE()) AS oldest_overdue_date
             FROM {$ts} s
             JOIN {$te} e ON e.student_id = s.id
             JOIN {$tc} c ON c.id = e.course_id
             WHERE s.status = 'active' AND s.deleted_at IS NULL
               AND e.status = 'active'
               AND e.balance_due > 0
             ORDER BY e.balance_due DESC"
        );
        // phpcs:enable

        if ( empty( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay morosos para exportar.', 'aura-suite' ) ] );
        }

        // Generar CSV en memoria
        $csv_rows = [];
        $csv_rows[] = [ 'Nombre', 'Apellido', 'Email', 'Teléfono', 'Curso', 'Costo Neto', 'Pagado', 'Saldo', 'Estado Pago', 'Cuotas Vencidas', 'Fecha Venc. más Antigua' ];
        foreach ( $rows as $r ) {
            $csv_rows[] = [
                $r->first_name, $r->last_name, $r->email, $r->phone ?? '',
                $r->course_name,
                number_format( (float) $r->net_cost, 2 ),
                number_format( (float) $r->total_paid, 2 ),
                number_format( (float) $r->balance_due, 2 ),
                $r->payment_status,
                (int) $r->overdue_count,
                $r->oldest_overdue_date ?? '',
            ];
        }

        $csv = '';
        foreach ( $csv_rows as $row ) {
            $cols = array_map( static function( $col ) {
                $col = str_replace( '"', '""', $col );
                return '"' . $col . '"';
            }, $row );
            $csv .= implode( ',', $cols ) . "\r\n";
        }

        wp_send_json_success( [
            'csv'      => $csv,
            'filename' => 'morosos-' . date( 'Y-m-d' ) . '.csv',
            'count'    => count( $rows ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // KPIs
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener todos los KPIs del dashboard.
     * @return array
     */
    public static function get_kpis(): array {
        global $wpdb;

        $ts  = $wpdb->prefix . 'aura_students';
        $te  = $wpdb->prefix . 'aura_student_enrollments';
        $tp  = $wpdb->prefix . 'aura_student_payments';
        $tis = $wpdb->prefix . 'aura_student_installment_schedule';
        $tc  = $wpdb->prefix . 'aura_student_courses';

        // Verificar que la tabla principal existe
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ts}'" ) !== $ts ) {
            return self::get_empty_kpis();
        }

        $year  = (int) date( 'Y' );
        $month = date( 'Y-m' );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active_students    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ts} WHERE status = 'active' AND deleted_at IS NULL" );
        $applicants_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ts} WHERE status = 'applicant' AND deleted_at IS NULL" );
        $graduated_year     = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$ts} WHERE status = 'graduated' AND YEAR(graduated_at) = %d",
            $year
        ) );
        $overdue_installments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tis} WHERE status = 'overdue'" );
        $income_month       = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$tp} WHERE DATE_FORMAT(payment_date, '%%Y-%%m') = %s",
            $month
        ) );
        $projected_income   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(balance_due), 0) FROM {$te} WHERE status = 'active'" );
        $total_students     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ts} WHERE deleted_at IS NULL" );
        $active_courses     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tc} WHERE status = 'active'" );
        // phpcs:enable

        return compact(
            'active_students',
            'applicants_pending',
            'graduated_year',
            'overdue_installments',
            'income_month',
            'projected_income',
            'total_students',
            'active_courses'
        );
    }

    private static function get_empty_kpis(): array {
        return [
            'active_students'      => 0,
            'applicants_pending'   => 0,
            'graduated_year'       => 0,
            'overdue_installments' => 0,
            'income_month'         => 0.0,
            'projected_income'     => 0.0,
            'total_students'       => 0,
            'active_courses'       => 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX
    // ─────────────────────────────────────────────────────────────

    public static function ajax_kpis(): void {
        check_ajax_referer( 'aura_students_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_students_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        wp_send_json_success( self::get_kpis() );
    }
}
