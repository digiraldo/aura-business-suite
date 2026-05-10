<?php
/**
 * Reportes del Módulo de Certificados
 *
 * Genera reportes estadísticos de certificados emitidos, revocados y pendientes.
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Reports {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_cert_generate_report', [ __CLASS__, 'ajax_generate_report' ] );
        add_action( 'wp_ajax_aura_cert_export_excel',    [ __CLASS__, 'ajax_export_excel' ] );
        add_action( 'wp_ajax_aura_cert_export_pdf',      [ __CLASS__, 'ajax_export_pdf' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER
    // ─────────────────────────────────────────────────────────────

    public static function render(): void {
        if ( ! current_user_can( 'aura_cert_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos para ver reportes.', 'aura-suite' ) );
        }

        $template = AURA_PLUGIN_DIR . 'templates/certificates/reports.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Generar reporte
    // ─────────────────────────────────────────────────────────────

    public static function ajax_generate_report(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        $type       = sanitize_key( $_POST['report_type'] ?? 'issued_by_period' );
        $date_from  = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
        $date_to    = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );

        // Validar fechas
        if ( $date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
            $date_from = '';
        }
        if ( $date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            $date_to = '';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificates';

        switch ( $type ) {
            case 'issued_by_period':
                $data = self::report_issued_by_period( $table, $date_from, $date_to );
                break;
            case 'issued_by_course':
                $data = self::report_issued_by_course( $table, $date_from, $date_to );
                break;
            case 'revoked':
                $data = self::report_revoked( $table, $date_from, $date_to );
                break;
            case 'pending_emit':
                $data = self::report_pending_emit();
                break;
            default:
                wp_send_json_error( [ 'message' => __( 'Tipo de reporte inválido.', 'aura-suite' ) ], 400 );
                return;
        }

        wp_send_json_success( [
            'report_type' => $type,
            'rows'        => $data['rows'],
            'totals'      => $data['totals'],
            'generated_at'=> current_time( 'mysql' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Exportar a Excel
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_excel(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos.', 'aura-suite' ), 403 );
        }

        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            wp_die( esc_html__( 'La librería PhpSpreadsheet no está disponible.', 'aura-suite' ), 500 );
        }

        $type      = sanitize_key( $_POST['report_type'] ?? 'issued_by_period' );
        $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
        $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificates';
        $data  = self::get_report_data( $type, $table, $date_from, $date_to );

        if ( empty( $data['headers'] ) ) {
            wp_die( esc_html__( 'No hay datos para exportar.', 'aura-suite' ), 404 );
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle( __( 'Certificados', 'aura-suite' ) );

        // Encabezados
        $col = 1;
        foreach ( $data['headers'] as $header ) {
            $sheet->setCellValueByColumnAndRow( $col, 1, $header );
            $sheet->getColumnDimensionByColumn( $col )->setAutoSize( true );
            $col++;
        }

        // Filas
        $row = 2;
        foreach ( $data['rows'] as $r ) {
            $col = 1;
            foreach ( array_values( (array) $r ) as $val ) {
                $sheet->setCellValueByColumnAndRow( $col, $row, $val );
                $col++;
            }
            $row++;
        }

        $writer   = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter( $spreadsheet, 'Xlsx' );
        $filename = 'reporte-certificados-' . date( 'Y-m-d' ) . '.xlsx';

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment;filename="' . $filename . '"' );
        header( 'Cache-Control: max-age=0' );

        $writer->save( 'php://output' );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Exportar a PDF
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_pdf(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_reports' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos.', 'aura-suite' ), 403 );
        }

        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            wp_die( esc_html__( 'La librería mPDF no está disponible.', 'aura-suite' ), 500 );
        }

        $type      = sanitize_key( $_POST['report_type'] ?? 'issued_by_period' );
        $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
        $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificates';
        $data  = self::get_report_data( $type, $table, $date_from, $date_to );

        $org_name = Aura_Certificates_Settings::get( 'org_name', get_option( 'blogname', '' ) );

        ob_start();
        ?>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; }
                h1 { font-size: 14px; color: #8b5cf6; margin-bottom: 4px; }
                .meta { color: #666; font-size: 10px; margin-bottom: 12px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #8b5cf6; color: #fff; padding: 6px 8px; text-align: left; }
                td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
                tr:nth-child(even) td { background: #f9f7ff; }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html( $org_name ); ?> — <?php esc_html_e( 'Reporte de Certificados', 'aura-suite' ); ?></h1>
            <div class="meta"><?php echo esc_html( __( 'Generado:', 'aura-suite' ) . ' ' . current_time( 'mysql' ) ); ?></div>
            <table>
                <thead>
                    <tr>
                        <?php foreach ( $data['headers'] as $h ) : ?>
                            <th><?php echo esc_html( $h ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $data['rows'] as $r ) : ?>
                    <tr>
                        <?php foreach ( array_values( (array) $r ) as $v ) : ?>
                            <td><?php echo esc_html( (string) $v ); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        try {
            $mpdf = new \Mpdf\Mpdf( [
                'format'      => 'A4',
                'orientation' => 'L',
                'margin_top'  => 10,
                'margin_left' => 10,
                'margin_right'=> 10,
            ] );
            $mpdf->SetTitle( __( 'Reporte de Certificados', 'aura-suite' ) );
            $mpdf->WriteHTML( $html );

            $filename = 'reporte-certificados-' . date( 'Y-m-d' ) . '.pdf';
            $mpdf->Output( $filename, 'D' );
        } catch ( \Exception $e ) {
            wp_die( esc_html( $e->getMessage() ), 500 );
        }

        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTES INTERNOS
    // ─────────────────────────────────────────────────────────────

    private static function report_issued_by_period( string $table, string $from, string $to ): array {
        global $wpdb;

        $where  = "WHERE status = 'active'";
        $params = [];

        if ( $from ) {
            $where   .= ' AND issued_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ( $to ) {
            $where   .= ' AND issued_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        $sql = "SELECT DATE(issued_at) as fecha, COUNT(*) as total FROM {$table} {$where} GROUP BY DATE(issued_at) ORDER BY fecha DESC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );

        $total = array_sum( array_column( $rows ?: [], 'total' ) );

        return [
            'headers' => [ __( 'Fecha', 'aura-suite' ), __( 'Certificados Emitidos', 'aura-suite' ) ],
            'rows'    => $rows ?: [],
            'totals'  => [ 'total' => $total ],
        ];
    }

    private static function report_issued_by_course( string $table, string $from, string $to ): array {
        global $wpdb;

        $where  = "WHERE status = 'active'";
        $params = [];

        if ( $from ) {
            $where   .= ' AND issued_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ( $to ) {
            $where   .= ' AND issued_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        $sql = "SELECT course_name, program_name, COUNT(*) as total FROM {$table} {$where} GROUP BY course_name, program_name ORDER BY total DESC";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );

        $total = array_sum( array_column( $rows ?: [], 'total' ) );

        return [
            'headers' => [
                __( 'Curso', 'aura-suite' ),
                __( 'Programa', 'aura-suite' ),
                __( 'Certificados', 'aura-suite' ),
            ],
            'rows'    => $rows ?: [],
            'totals'  => [ 'total' => $total ],
        ];
    }

    private static function report_revoked( string $table, string $from, string $to ): array {
        global $wpdb;
        $students_table = $wpdb->prefix . 'aura_students';

        $where  = "WHERE c.status = 'revoked'";
        $params = [];

        if ( $from ) {
            $where   .= ' AND c.revoked_at >= %s';
            $params[] = $from . ' 00:00:00';
        }
        if ( $to ) {
            $where   .= ' AND c.revoked_at <= %s';
            $params[] = $to . ' 23:59:59';
        }

        $sql = "SELECT c.folio, CONCAT(s.first_name,' ',s.last_name) as estudiante, c.course_name, c.revoked_at, c.revoke_reason
                FROM {$table} c
                LEFT JOIN {$students_table} s ON c.student_id = s.id
                {$where}
                ORDER BY c.revoked_at DESC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );

        return [
            'headers' => [
                __( 'Folio', 'aura-suite' ),
                __( 'Estudiante', 'aura-suite' ),
                __( 'Curso', 'aura-suite' ),
                __( 'Fecha Revocación', 'aura-suite' ),
                __( 'Motivo', 'aura-suite' ),
            ],
            'rows'    => $rows ?: [],
            'totals'  => [ 'total' => count( $rows ?: [] ) ],
        ];
    }

    private static function report_pending_emit(): array {
        global $wpdb;

        $certs_table    = $wpdb->prefix . 'aura_certificates';
        $students_table = $wpdb->prefix . 'aura_students';
        $enroll_table   = $wpdb->prefix . 'aura_enrollments';

        // Inscripciones graduadas sin certificado activo
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT e.id as enrollment_id, CONCAT(s.first_name,' ',s.last_name) as estudiante,
                    s.email, e.course_name, e.graduation_date
             FROM {$enroll_table} e
             INNER JOIN {$students_table} s ON e.student_id = s.id
             WHERE e.status = 'graduated'
             AND NOT EXISTS (
                 SELECT 1 FROM {$certs_table} c
                 WHERE c.enrollment_id = e.id AND c.status = 'active'
             )
             ORDER BY e.graduation_date ASC
             LIMIT 500"
        );

        return [
            'headers' => [
                __( 'ID Inscripción', 'aura-suite' ),
                __( 'Estudiante', 'aura-suite' ),
                __( 'Email', 'aura-suite' ),
                __( 'Curso', 'aura-suite' ),
                __( 'Fecha Graduación', 'aura-suite' ),
            ],
            'rows'    => $rows ?: [],
            'totals'  => [ 'total' => count( $rows ?: [] ) ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDAD
    // ─────────────────────────────────────────────────────────────

    private static function get_report_data( string $type, string $table, string $from, string $to ): array {
        switch ( $type ) {
            case 'issued_by_period': return self::report_issued_by_period( $table, $from, $to );
            case 'issued_by_course': return self::report_issued_by_course( $table, $from, $to );
            case 'revoked':          return self::report_revoked( $table, $from, $to );
            case 'pending_emit':     return self::report_pending_emit();
            default:                 return [ 'headers' => [], 'rows' => [], 'totals' => [] ];
        }
    }
}
