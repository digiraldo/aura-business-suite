<?php
/**
 * Dashboard y Reportes del Módulo de Biblioteca — Fase 6
 *
 * KPIs en tiempo real, datos para gráficos Chart.js y 4 tipos de reporte
 * exportables a CSV y PDF (mPDF).
 *
 * @package AuraBusinessSuite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Reports {

    const NONCE = 'aura_library_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'aura_library_dashboard_kpis'         => 'ajax_kpis',
            'aura_library_dashboard_loans_chart'  => 'ajax_loans_chart',
            'aura_library_dashboard_status_chart' => 'ajax_status_chart',
            'aura_library_dashboard_dewey_chart'  => 'ajax_dewey_chart',
            'aura_library_dashboard_overdue_list' => 'ajax_overdue_list',
            'aura_library_dashboard_top_books'    => 'ajax_top_books',
            'aura_library_dashboard_recent_res'   => 'ajax_recent_reservations',
            'aura_library_report_activity'        => 'ajax_report_activity',
            'aura_library_report_dewey'           => 'ajax_report_dewey',
            'aura_library_report_overdue'         => 'ajax_report_overdue',
            'aura_library_report_inventory'       => 'ajax_report_inventory',
            'aura_library_export_csv'             => 'ajax_export_csv',
            'aura_library_export_pdf'             => 'ajax_export_pdf',
        ];
        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — KPIs (6 widgets)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_kpis(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }
        wp_send_json_success( self::get_kpis() );
    }

    /**
     * Calcula los 6 KPIs del dashboard.
     *
     * @return array
     */
    public static function get_kpis(): array {
        global $wpdb;
        $t_books  = $wpdb->prefix . 'aura_library_books';
        $t_loans  = $wpdb->prefix . 'aura_library_loans';
        $t_res    = $wpdb->prefix . 'aura_library_reservations';
        $today    = gmdate( 'Y-m-d' );

        $total_books = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_books} WHERE deleted_at IS NULL"
        );

        $available_copies = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(available_copies),0) FROM {$t_books} WHERE deleted_at IS NULL"
        );

        $active_loans = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_loans} WHERE status IN ('active','extended')"
        );

        $overdue_loans = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_loans} WHERE status IN ('active','extended','overdue') AND due_date < '{$today}'"
        );

        $pending_reservations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_res} WHERE status IN ('pending','notified')"
        );

        $pending_fines = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(fine_amount),0) FROM {$t_loans} WHERE fine_amount > 0 AND fine_paid = 0"
        );

        return compact(
            'total_books',
            'available_copies',
            'active_loans',
            'overdue_loans',
            'pending_reservations',
            'pending_fines'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — Gráfico de préstamos por mes (últimos 6 meses)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_loans_chart(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        $rows = $wpdb->get_results(
            "SELECT DATE_FORMAT(loan_date, '%Y-%m') AS month,
                    COUNT(*) AS total
             FROM {$t_loans}
             WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY month ORDER BY month ASC"
        );

        $labels = [];
        $data   = [];
        foreach ( $rows as $r ) {
            $ts       = strtotime( $r->month . '-01' );
            $labels[] = gmdate( 'M Y', $ts );
            $data[]   = (int) $r->total;
        }

        wp_send_json_success( compact( 'labels', 'data' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — Gráfico de dona: libros por estado
    // ─────────────────────────────────────────────────────────────

    public static function ajax_status_chart(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_books = $wpdb->prefix . 'aura_library_books';

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt FROM {$t_books} WHERE deleted_at IS NULL GROUP BY status"
        );

        $label_map = [
            'available'    => __( 'Disponible',  'aura-business-suite' ),
            'loaned'       => __( 'Prestado',    'aura-business-suite' ),
            'reserved'     => __( 'Reservado',   'aura-business-suite' ),
            'maintenance'  => __( 'Mantenimiento','aura-business-suite' ),
            'reference_only' => __( 'Solo Consulta','aura-business-suite' ),
            'inactive'     => __( 'Inactivo',    'aura-business-suite' ),
        ];
        $color_map = [
            'available'      => '#22c55e',
            'loaned'         => '#3b82f6',
            'reserved'       => '#f59e0b',
            'maintenance'    => '#ef4444',
            'reference_only' => '#8b5cf6',
            'inactive'       => '#94a3b8',
        ];

        $labels = $data = $colors = [];
        foreach ( $rows as $r ) {
            $labels[] = $label_map[ $r->status ] ?? $r->status;
            $data[]   = (int) $r->cnt;
            $colors[] = $color_map[ $r->status ] ?? '#94a3b8';
        }

        wp_send_json_success( compact( 'labels', 'data', 'colors' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — Gráfico de barras: distribución Dewey
    // ─────────────────────────────────────────────────────────────

    public static function ajax_dewey_chart(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        // Agrupar por la primera cifra del número Dewey (clase principal)
        $rows = $wpdb->get_results(
            "SELECT LEFT(b.dewey_number, 1) AS dewey_class,
                    COUNT(l.id) AS total_loans
             FROM {$t_loans} l
             INNER JOIN {$t_books} b ON b.id = l.book_id
             WHERE b.dewey_number IS NOT NULL AND b.dewey_number != ''
             GROUP BY dewey_class
             ORDER BY total_loans DESC
             LIMIT 10"
        );

        $dewey_names = [
            '0' => __( '000 Informática', 'aura-business-suite' ),
            '1' => __( '100 Filosofía',   'aura-business-suite' ),
            '2' => __( '200 Religión',    'aura-business-suite' ),
            '3' => __( '300 Sociales',    'aura-business-suite' ),
            '4' => __( '400 Lengua',      'aura-business-suite' ),
            '5' => __( '500 Ciencias',    'aura-business-suite' ),
            '6' => __( '600 Tecnología',  'aura-business-suite' ),
            '7' => __( '700 Artes',       'aura-business-suite' ),
            '8' => __( '800 Literatura',  'aura-business-suite' ),
            '9' => __( '900 Historia',    'aura-business-suite' ),
        ];

        $labels = $data = [];
        foreach ( $rows as $r ) {
            $labels[] = $dewey_names[ $r->dewey_class ] ?? ( $r->dewey_class . 'xx' );
            $data[]   = (int) $r->total_loans;
        }

        wp_send_json_success( compact( 'labels', 'data' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — Lista: Préstamos vencidos recientes
    // ─────────────────────────────────────────────────────────────

    public static function ajax_overdue_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';
        $today   = gmdate( 'Y-m-d' );

        $rows = $wpdb->get_results(
            "SELECT l.id, b.title AS book_title, u.display_name AS borrower,
                    l.due_date, DATEDIFF(NOW(), l.due_date) AS days_overdue, l.fine_amount
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             LEFT JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
             WHERE l.status IN ('active','extended','overdue') AND l.due_date < '{$today}'
             ORDER BY l.due_date ASC
             LIMIT 10"
        );

        $items = [];
        foreach ( $rows as $r ) {
            $items[] = [
                'id'           => (int) $r->id,
                'book_title'   => $r->book_title,
                'borrower'     => $r->borrower,
                'due_date'     => $r->due_date,
                'days_overdue' => (int) $r->days_overdue,
                'fine_amount'  => number_format( (float) $r->fine_amount, 2 ),
            ];
        }

        wp_send_json_success( $items );
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — Lista: Libros más prestados (top 5)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_top_books(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $rows = $wpdb->get_results(
            "SELECT b.id, b.title, b.author, b.cover_image_url,
                    COUNT(l.id) AS loan_count
             FROM {$t_loans} l
             INNER JOIN {$t_books} b ON b.id = l.book_id
             WHERE b.deleted_at IS NULL
             GROUP BY b.id ORDER BY loan_count DESC LIMIT 5"
        );

        $items = [];
        foreach ( $rows as $r ) {
            $items[] = [
                'id'         => (int) $r->id,
                'title'      => $r->title,
                'author'     => $r->author,
                'cover'      => $r->cover_image_url ? esc_url( $r->cover_image_url ) : '',
                'loan_count' => (int) $r->loan_count,
            ];
        }

        wp_send_json_success( $items );
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD — Lista: Reservas recientes
    // ─────────────────────────────────────────────────────────────

    public static function ajax_recent_reservations(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_res   = $wpdb->prefix . 'aura_library_reservations';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $rows = $wpdb->get_results(
            "SELECT r.id, b.title AS book_title, u.display_name AS user_name,
                    r.status, r.reserved_at, r.expires_at
             FROM {$t_res} r
             LEFT JOIN {$t_books} b ON b.id = r.book_id
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.status IN ('pending','notified')
             ORDER BY r.reserved_at DESC LIMIT 8"
        );

        $items = [];
        foreach ( $rows as $r ) {
            $items[] = [
                'id'         => (int) $r->id,
                'book_title' => $r->book_title,
                'user_name'  => $r->user_name,
                'status'     => $r->status,
                'reserved_at'=> substr( $r->reserved_at, 0, 10 ),
                'expires_at' => $r->expires_at ? substr( $r->expires_at, 0, 10 ) : '',
            ];
        }

        wp_send_json_success( $items );
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 1 — Actividad general
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_activity(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $period = sanitize_text_field( $_POST['period'] ?? 'month' );
        $data   = self::get_activity_report( $period );
        wp_send_json_success( $data );
    }

    private static function get_activity_report( string $period ): array {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $interval_map = [
            'week'  => '7 DAY',
            'month' => '1 MONTH',
            'year'  => '1 YEAR',
        ];
        $interval = $interval_map[ $period ] ?? '1 MONTH';

        // Préstamos en el período
        $loans_in_period = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_loans}
             WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL {$interval})"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Devoluciones a tiempo vs tardías
        $on_time = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_loans}
             WHERE status = 'returned' AND return_date <= due_date
               AND loan_date >= DATE_SUB(CURDATE(), INTERVAL {$interval})"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $late = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_loans}
             WHERE status = 'returned' AND return_date > due_date
               AND loan_date >= DATE_SUB(CURDATE(), INTERVAL {$interval})"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Top 10 libros más prestados
        $top_books = $wpdb->get_results(
            "SELECT b.id, b.title, b.author, b.dewey_number, b.cover_image_url,
                    COUNT(l.id) AS loan_count
             FROM {$t_loans} l
             INNER JOIN {$t_books} b ON b.id = l.book_id
             WHERE b.deleted_at IS NULL
               AND l.loan_date >= DATE_SUB(CURDATE(), INTERVAL {$interval})
             GROUP BY b.id ORDER BY loan_count DESC LIMIT 10"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Top 10 lectores más activos
        $top_readers = $wpdb->get_results(
            "SELECT u.ID AS user_id, u.display_name, COUNT(l.id) AS loan_count
             FROM {$t_loans} l
             INNER JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
             WHERE l.loan_date >= DATE_SUB(CURDATE(), INTERVAL {$interval})
             GROUP BY u.ID ORDER BY loan_count DESC LIMIT 10"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return compact( 'loans_in_period', 'on_time', 'late', 'top_books', 'top_readers' );
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 2 — Clasificación Dewey
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_dewey(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        // Distribución de préstamos por clase Dewey
        $distribution = $wpdb->get_results(
            "SELECT LEFT(b.dewey_number, 3) AS dewey_class,
                    b.title, b.author, COUNT(l.id) AS loan_count
             FROM {$t_loans} l
             INNER JOIN {$t_books} b ON b.id = l.book_id
             WHERE b.dewey_number IS NOT NULL AND b.dewey_number != ''
             GROUP BY b.id
             ORDER BY dewey_class ASC, loan_count DESC"
        );

        wp_send_json_success( [ 'rows' => $distribution ] );
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 3 — Morosidad
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_overdue(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';
        $today   = gmdate( 'Y-m-d' );

        // Préstamos vencidos actuales
        $current_overdue = $wpdb->get_results(
            "SELECT l.id, b.title AS book_title, b.dewey_number,
                    u.display_name AS borrower, l.due_date,
                    DATEDIFF(NOW(), l.due_date) AS days_overdue, l.fine_amount
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             LEFT JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
             WHERE l.status IN ('active','extended','overdue') AND l.due_date < '{$today}'
             ORDER BY l.due_date ASC"
        );

        // Multas cobradas (historial)
        $paid_fines = $wpdb->get_results(
            "SELECT l.id, b.title AS book_title, u.display_name AS borrower,
                    l.fine_amount, l.return_date
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             LEFT JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
             WHERE l.fine_paid = 1 AND l.fine_amount > 0
             ORDER BY l.return_date DESC LIMIT 50"
        );

        $total_collected = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(fine_amount),0) FROM {$t_loans} WHERE fine_paid = 1"
        );

        wp_send_json_success( compact( 'current_overdue', 'paid_fines', 'total_collected' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 4 — Inventario
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_inventory(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $inactive_months = (int) ( $_POST['inactive_months'] ?? 6 );
        $data            = self::get_inventory_report( $inactive_months );
        wp_send_json_success( $data );
    }

    private static function get_inventory_report( int $inactive_months ): array {
        global $wpdb;
        $t_books = $wpdb->prefix . 'aura_library_books';
        $t_loans = $wpdb->prefix . 'aura_library_loans';

        // Por estado
        $by_status = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_copies),0) AS total_copies
             FROM {$t_books} WHERE deleted_at IS NULL GROUP BY status"
        );

        // Stock inactivo (sin préstamos en N meses)
        $inactive = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.id, b.title, b.author, b.dewey_number, b.total_copies, b.available_copies
                 FROM {$t_books} b
                 WHERE b.deleted_at IS NULL
                   AND b.id NOT IN (
                       SELECT DISTINCT book_id FROM {$t_loans}
                       WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
                   )
                 ORDER BY b.title ASC",
                $inactive_months
            )
        );

        // Mayor rotación (top 10)
        $top_rotation = $wpdb->get_results(
            "SELECT b.id, b.title, b.author, b.dewey_number,
                    COUNT(l.id) AS total_loans
             FROM {$t_books} b
             INNER JOIN {$t_loans} l ON l.book_id = b.id
             WHERE b.deleted_at IS NULL
             GROUP BY b.id ORDER BY total_loans DESC LIMIT 10"
        );

        return compact( 'by_status', 'inactive', 'top_rotation' );
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORTACIÓN — CSV
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_csv(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos.', 'aura-business-suite' ) );
        }

        $report_type = sanitize_key( $_POST['report_type'] ?? 'activity' );
        $period      = sanitize_text_field( $_POST['period'] ?? 'month' );

        $data     = self::get_report_data_for_export( $report_type, $period );
        $filename = 'biblioteca-reporte-' . $report_type . '-' . gmdate( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM UTF-8

        if ( ! empty( $data['headers'] ) ) {
            fputcsv( $output, $data['headers'] );
        }
        if ( ! empty( $data['rows'] ) ) {
            foreach ( $data['rows'] as $row ) {
                fputcsv( $output, (array) $row );
            }
        }
        fclose( $output );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORTACIÓN — PDF (mPDF)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_pdf(): void {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'aura_library_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos.', 'aura-business-suite' ) );
        }

        $report_type = sanitize_key( $_POST['report_type'] ?? 'activity' );
        $period      = sanitize_text_field( $_POST['period'] ?? 'month' );

        $data     = self::get_report_data_for_export( $report_type, $period );
        $filename = 'biblioteca-reporte-' . $report_type . '-' . gmdate( 'Y-m-d' ) . '.pdf';
        $org      = aura_get_org_name();
        $title    = sprintf( __( 'Reporte de Biblioteca — %s', 'aura-business-suite' ), $org );

        // Verificar mPDF disponible
        $mpdf_autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( ! file_exists( $mpdf_autoload ) || ! class_exists( '\Mpdf\Mpdf' ) ) {
            // Fallback: enviar error JSON
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [ 'error' => __( 'mPDF no está disponible. Usa la exportación CSV.', 'aura-business-suite' ) ] );
            exit;
        }

        $html  = '<h1>' . esc_html( $title ) . '</h1>';
        $html .= '<p>' . esc_html( gmdate( 'd/m/Y' ) ) . '</p>';

        if ( ! empty( $data['headers'] ) ) {
            $html .= '<table border="1" cellpadding="4" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:11px;">';
            $html .= '<tr style="background:#2271b1;color:#fff;">';
            foreach ( $data['headers'] as $h ) {
                $html .= '<th>' . esc_html( $h ) . '</th>';
            }
            $html .= '</tr>';
            foreach ( $data['rows'] as $i => $row ) {
                $bg    = ( $i % 2 === 0 ) ? '#fff' : '#f9f9f9';
                $html .= '<tr style="background:' . $bg . ';">';
                foreach ( (array) $row as $cell ) {
                    $html .= '<td>' . esc_html( (string) $cell ) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        try {
            $mpdf = new \Mpdf\Mpdf( [ 'mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 10, 'margin_right' => 10 ] );
            $mpdf->SetTitle( $title );
            $mpdf->WriteHTML( $html );
            $mpdf->Output( $filename, 'D' );
        } catch ( \Throwable $e ) {
            header( 'Content-Type: application/json' );
            echo wp_json_encode( [ 'error' => $e->getMessage() ] );
        }
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER — Datos para exportación unificada
    // ─────────────────────────────────────────────────────────────

    private static function get_report_data_for_export( string $report_type, string $period ): array {
        switch ( $report_type ) {
            case 'activity':
                $d = self::get_activity_report( $period );
                $headers = [
                    __( 'Título', 'aura-business-suite' ),
                    __( 'Autor', 'aura-business-suite' ),
                    __( 'Dewey', 'aura-business-suite' ),
                    __( 'Nº Préstamos', 'aura-business-suite' ),
                ];
                $rows = array_map( fn( $b ) => [ $b->title, $b->author, $b->dewey_number ?? '', $b->loan_count ], $d['top_books'] );
                return compact( 'headers', 'rows' );

            case 'dewey':
                global $wpdb;
                $t_loans = $wpdb->prefix . 'aura_library_loans';
                $t_books = $wpdb->prefix . 'aura_library_books';
                $rows_raw = $wpdb->get_results(
                    "SELECT LEFT(b.dewey_number, 3) AS dewey, b.title, b.author, COUNT(l.id) AS loans
                     FROM {$t_loans} l
                     INNER JOIN {$t_books} b ON b.id = l.book_id
                     WHERE b.dewey_number IS NOT NULL
                     GROUP BY b.id ORDER BY dewey ASC, loans DESC"
                );
                $headers = [ __( 'Dewey', 'aura-business-suite' ), __( 'Título', 'aura-business-suite' ), __( 'Autor', 'aura-business-suite' ), __( 'Préstamos', 'aura-business-suite' ) ];
                $rows    = array_map( fn( $r ) => [ $r->dewey, $r->title, $r->author, $r->loans ], $rows_raw );
                return compact( 'headers', 'rows' );

            case 'overdue':
                $d = [];
                global $wpdb;
                $t_loans = $wpdb->prefix . 'aura_library_loans';
                $t_books = $wpdb->prefix . 'aura_library_books';
                $today   = gmdate( 'Y-m-d' );
                $rows_raw = $wpdb->get_results(
                    "SELECT b.title, u.display_name AS borrower, l.due_date,
                            DATEDIFF(NOW(), l.due_date) AS days_overdue, l.fine_amount
                     FROM {$t_loans} l
                     LEFT JOIN {$t_books} b ON b.id = l.book_id
                     LEFT JOIN {$wpdb->users} u ON u.ID = l.borrower_user_id
                     WHERE l.status IN ('active','extended','overdue') AND l.due_date < '{$today}'
                     ORDER BY l.due_date ASC"
                );
                $headers = [ __( 'Libro', 'aura-business-suite' ), __( 'Lector', 'aura-business-suite' ), __( 'Fec. Venc.', 'aura-business-suite' ), __( 'Días Vencido', 'aura-business-suite' ), __( 'Multa', 'aura-business-suite' ) ];
                $rows    = array_map( fn( $r ) => [ $r->title, $r->borrower, $r->due_date, $r->days_overdue, number_format( (float) $r->fine_amount, 2 ) ], $rows_raw );
                return compact( 'headers', 'rows' );

            case 'inventory':
            default:
                $d = self::get_inventory_report( 6 );
                $headers = [ __( 'Título', 'aura-business-suite' ), __( 'Autor', 'aura-business-suite' ), __( 'Dewey', 'aura-business-suite' ), __( 'Ejs. Total', 'aura-business-suite' ), __( 'Ejs. Disponibles', 'aura-business-suite' ) ];
                $rows    = array_map( fn( $b ) => [ $b->title, $b->author, $b->dewey_number ?? '', $b->total_copies, $b->available_copies ], $d['inactive'] );
                return compact( 'headers', 'rows' );
        }
    }
}
