<?php
/**
 * Reportes del Módulo de Estudiantes
 *
 * 8 tipos de reporte exportables a Excel (.xlsx) y PDF:
 *  1. students_list       — Lista completa de estudiantes
 *  2. payments_by_course  — Estado de pagos por curso
 *  3. enrolled_by_area    — Inscritos por área/programa
 *  4. income_by_area      — Ingresos por área/programa (Excel)
 *  5. overdue             — Morosos (cuotas vencidas)
 *  6. income_projection   — Proyección de ingresos futuros por mes (Excel)
 *  7. scholarships        — Becas otorgadas
 *  8. graduates           — Graduados por período
 *
 * Formato Excel  → PhpSpreadsheet (vendor/)
 * Formato PDF    → mPDF            (vendor/)
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Reports {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_students_generate_report', [ __CLASS__, 'ajax_generate_report' ] );
        add_action( 'wp_ajax_aura_students_export_excel',    [ __CLASS__, 'ajax_export_excel' ] );
        add_action( 'wp_ajax_aura_students_export_pdf',      [ __CLASS__, 'ajax_export_pdf' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER — página admin
    // ─────────────────────────────────────────────────────────────

    public static function render(): void {
        if ( ! self::can_access() ) {
            wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/students/reports.php';
    }

    // ─────────────────────────────────────────────────────────────
    // PERMISOS
    // ─────────────────────────────────────────────────────────────

    private static function can_access(): bool {
        return current_user_can( 'aura_students_reports' ) || current_user_can( 'manage_options' );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Vista previa en pantalla (JSON)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_generate_report(): void {
        check_ajax_referer( 'aura_students_reports_nonce', 'nonce' );

        if ( ! self::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'Acceso denegado.', 'aura-suite' ) ] );
        }

        $type   = sanitize_key( $_POST['report_type'] ?? '' );
        $params = self::parse_params( 'POST' );

        $data = match ( $type ) {
            'students_list'      => self::get_students_list( $params ),
            'payments_by_course' => self::get_payments_by_course( $params ),
            'enrolled_by_area'   => self::get_enrolled_by_area( $params ),
            'income_by_area'     => self::get_income_by_area( $params ),
            'overdue'            => self::get_overdue( $params ),
            'income_projection'  => self::get_income_projection( $params ),
            'scholarships'       => self::get_scholarships( $params ),
            'graduates'          => self::get_graduates( $params ),
            default              => null,
        };

        if ( $data === null ) {
            wp_send_json_error( [ 'message' => __( 'Tipo de reporte no válido.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'type' => $type, 'params' => $params, 'data' => $data ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Exportar Excel
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_excel(): void {
        check_ajax_referer( 'aura_students_reports_export', 'nonce' );

        if ( ! self::can_access() ) {
            wp_die( __( 'Acceso denegado.', 'aura-suite' ) );
        }

        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            wp_die( 'PhpSpreadsheet no disponible. Ejecuta: composer install en el plugin.' );
        }
        require_once $autoload;

        $type   = sanitize_key( $_GET['report_type'] ?? '' );
        $params = self::parse_params( 'GET' );

        [ $headers, $rows, $filename, $title ] = self::get_flat_data( $type, $params );

        if ( empty( $headers ) ) {
            wp_die( __( 'Tipo de reporte no válido.', 'aura-suite' ) );
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle( mb_substr( $title, 0, 31 ) );

        // Fila 1: título del reporte
        $sheet->mergeCells( 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( count( $headers ) ) . '1' );
        $sheet->getCell( 'A1' )->setValue( $title . ' — ' . date_i18n( get_option( 'date_format' ) ) );
        $sheet->getStyle( 'A1' )->getFont()->setBold( true )->setSize( 13 );
        $sheet->getStyle( 'A1' )->getFill()
            ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
            ->getStartColor()->setRGB( '8B5CF6' );
        $sheet->getStyle( 'A1' )->getFont()->getColor()->setRGB( 'FFFFFF' );
        $sheet->getRowDimension( 1 )->setRowHeight( 26 );

        // Fila 2: cabeceras
        $col = 1;
        foreach ( $headers as $h ) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col ) . '2';
            $sheet->getCell( $coord )->setValue( $h );
            $style = $sheet->getStyle( $coord );
            $style->getFont()->setBold( true );
            $style->getFill()
                ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                ->getStartColor()->setRGB( '5B21B6' );
            $style->getFont()->getColor()->setRGB( 'FFFFFF' );
            $style->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
            $col++;
        }

        // Filas de datos (a partir de fila 3)
        $rowNum = 3;
        foreach ( $rows as $row ) {
            $col = 1;
            foreach ( $row as $val ) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col ) . $rowNum;
                $sheet->getCell( $coord )->setValue( $val );
                $col++;
            }
            if ( $rowNum % 2 === 0 ) {
                $range = 'A' . $rowNum . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( count( $headers ) ) . $rowNum;
                $sheet->getStyle( $range )->getFill()
                    ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                    ->getStartColor()->setRGB( 'F5F3FF' );
            }
            $rowNum++;
        }

        // Fila de total
        $sheet->getCell( 'A' . $rowNum )->setValue( sprintf( __( 'Total registros: %d', 'aura-suite' ), count( $rows ) ) );
        $sheet->getStyle( 'A' . $rowNum )->getFont()->setBold( true )->setItalic( true );

        // Auto-tamaño + freeze
        foreach ( range( 1, count( $headers ) ) as $c ) {
            $sheet->getColumnDimensionByColumn( $c )->setAutoSize( true );
        }
        $sheet->freezePane( 'A3' );

        $org = aura_get_org_name();
        $sheet->getHeaderFooter()->setOddFooter(
            '&L&"Calibri"' . $org . ' · Aura Business Suite&R&"Calibri"Generado: ' . date( 'd/m/Y H:i' )
        );

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.xlsx"' );
        header( 'Cache-Control: max-age=0' );

        ( new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet ) )->save( 'php://output' );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Exportar PDF
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_pdf(): void {
        check_ajax_referer( 'aura_students_reports_export', 'nonce' );

        if ( ! self::can_access() ) {
            wp_die( __( 'Acceso denegado.', 'aura-suite' ) );
        }

        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            wp_die( 'mPDF no disponible. Ejecuta: composer install en el plugin.' );
        }
        require_once $autoload;

        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            wp_die( __( 'mPDF no está instalado. Agrega mpdf/mpdf al composer.json.', 'aura-suite' ) );
        }

        $type   = sanitize_key( $_GET['report_type'] ?? '' );
        $params = self::parse_params( 'GET' );

        // Tipos que admiten PDF
        $pdf_types = [ 'students_list', 'payments_by_course', 'enrolled_by_area', 'overdue', 'graduates' ];
        if ( ! in_array( $type, $pdf_types, true ) ) {
            wp_die( __( 'Este reporte no está disponible en formato PDF.', 'aura-suite' ) );
        }

        [ $headers, $rows, $filename, $title ] = self::get_flat_data( $type, $params );
        $org = aura_get_org_name();

        $html = self::build_pdf_html( $title, $headers, $rows, $org );

        $mpdf = new \Mpdf\Mpdf( [ 'format' => 'A4-L', 'margin_top' => 20, 'margin_bottom' => 15 ] );
        $mpdf->SetTitle( $title );
        $mpdf->SetAuthor( $org );
        $mpdf->WriteHTML( $html );
        $mpdf->Output( sanitize_file_name( $filename ) . '.pdf', 'D' );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS DE EXPORTACIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Despacha a los métodos get_flat_*() según el tipo de reporte.
     * Devuelve [ $headers[], $rows[][], $filename, $title ].
     */
    private static function get_flat_data( string $type, array $params ): array {
        return match ( $type ) {
            'students_list'      => self::flat_students_list( $params ),
            'payments_by_course' => self::flat_payments_by_course( $params ),
            'enrolled_by_area'   => self::flat_enrolled_by_area( $params ),
            'income_by_area'     => self::flat_income_by_area( $params ),
            'overdue'            => self::flat_overdue( $params ),
            'income_projection'  => self::flat_income_projection( $params ),
            'scholarships'       => self::flat_scholarships( $params ),
            'graduates'          => self::flat_graduates( $params ),
            default              => [ [], [], 'reporte', 'Reporte' ],
        };
    }

    /**
     * Construye el HTML de la tabla para mPDF.
     */
    private static function build_pdf_html( string $title, array $headers, array $rows, string $org ): string {
        $th_html = '';
        foreach ( $headers as $h ) {
            $th_html .= '<th>' . esc_html( $h ) . '</th>';
        }

        $tr_html = '';
        foreach ( $rows as $i => $row ) {
            $bg      = $i % 2 === 0 ? '#f5f3ff' : '#ffffff';
            $tr_html .= '<tr style="background:' . $bg . ';">';
            foreach ( $row as $cell ) {
                $tr_html .= '<td>' . esc_html( (string) $cell ) . '</td>';
            }
            $tr_html .= '</tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:#1f2937;}
  .rep-title{font-size:14pt;font-weight:700;color:#5b21b6;margin-bottom:4px;}
  .rep-meta{font-size:8pt;color:#6b7280;margin-bottom:12px;}
  table{width:100%;border-collapse:collapse;}
  thead th{background:#8b5cf6;color:#fff;padding:5px 7px;font-size:8pt;text-align:left;}
  tbody td{padding:5px 7px;font-size:8pt;border-bottom:1px solid #e5e7eb;}
  .footer{font-size:7pt;color:#9ca3af;text-align:right;margin-top:8px;}
  .total-row{font-weight:700;color:#374151;}
</style></head><body>
<div class="rep-title">' . esc_html( $title ) . '</div>
<div class="rep-meta">' . esc_html( $org ) . ' · Generado el ' . date_i18n( get_option( 'date_format' ) . ' H:i' ) . '</div>
<table>
<thead><tr>' . $th_html . '</tr></thead>
<tbody>' . $tr_html . '</tbody>
</table>
<div class="footer">Total: ' . count( $rows ) . ' registros · Aura Business Suite</div>
</body></html>';
    }

    // ─────────────────────────────────────────────────────────────
    // PARÁMETROS COMUNES
    // ─────────────────────────────────────────────────────────────

    private static function parse_params( string $source = 'POST' ): array {
        $d          = $source === 'GET' ? $_GET : $_POST;
        $start      = sanitize_text_field( $d['start']   ?? date( 'Y-01-01' ) );
        $end        = sanitize_text_field( $d['end']     ?? date( 'Y-12-31' ) );
        $course_id  = absint( $d['course_id']  ?? 0 );
        $area_id    = absint( $d['area_id']    ?? 0 );
        $status     = sanitize_text_field( $d['status']  ?? '' );
        $type_filter= sanitize_text_field( $d['profile_type'] ?? '' );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) $start = date( 'Y-01-01' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) )   $end   = date( 'Y-12-31' );

        return compact( 'start', 'end', 'course_id', 'area_id', 'status', 'type_filter' );
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 1: Lista completa de estudiantes ─────────────────
    // ─────────────────────────────────────────────────────────────

    private static function get_students_list( array $p ): array {
        global $wpdb;
        $where = [ 's.deleted_at IS NULL' ];
        $args  = [];

        if ( $p['status'] ) {
            $where[] = 's.status = %s';
            $args[]  = $p['status'];
        }
        if ( $p['type_filter'] ) {
            $where[] = 's.profile_type = %s';
            $args[]  = $p['type_filter'];
        }
        if ( $p['area_id'] ) {
            $where[] = 'EXISTS (
                SELECT 1 FROM ' . $wpdb->prefix . 'aura_student_enrollments e2
                JOIN ' . $wpdb->prefix . 'aura_student_courses c2 ON c2.id = e2.course_id
                WHERE e2.student_id = s.id AND c2.area_id = ' . (int) $p['area_id'] . '
            )';
        }
        if ( $p['course_id'] ) {
            $where[] = 'EXISTS (
                SELECT 1 FROM ' . $wpdb->prefix . 'aura_student_enrollments e3
                WHERE e3.student_id = s.id AND e3.course_id = ' . (int) $p['course_id'] . '
            )';
        }

        $sql = 'SELECT s.id, s.first_name, s.last_name, s.email, s.phone,
                       s.id_number, s.profile_type, s.status, s.city, s.country,
                       s.created_at, s.approved_at, s.graduated_at,
                       COUNT(DISTINCT e.id) AS total_enrollments,
                       SUM(e.total_paid) AS total_paid,
                       SUM(e.balance_due) AS balance_due
                FROM ' . $wpdb->prefix . 'aura_students s
                LEFT JOIN ' . $wpdb->prefix . 'aura_student_enrollments e ON e.student_id = s.id
                WHERE ' . implode( ' AND ', $where ) . '
                GROUP BY s.id
                ORDER BY s.last_name, s.first_name';

        $results = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );

        return [
            'headers' => [
                '#', __( 'Nombre', 'aura-suite' ), __( 'Email', 'aura-suite' ),
                __( 'Tipo', 'aura-suite' ), __( 'Estado', 'aura-suite' ),
                __( 'Ciudad', 'aura-suite' ), __( 'Inscripciones', 'aura-suite' ),
                __( 'Pagado', 'aura-suite' ), __( 'Saldo', 'aura-suite' ),
                __( 'Registrado', 'aura-suite' ),
            ],
            'rows'    => array_map( fn( $r ) => [
                $r->id,
                $r->first_name . ' ' . $r->last_name,
                $r->email,
                self::profile_label( $r->profile_type ),
                self::status_label( $r->status ),
                $r->city,
                $r->total_enrollments,
                number_format( (float) $r->total_paid, 2 ),
                number_format( (float) $r->balance_due, 2 ),
                date_i18n( get_option( 'date_format' ), strtotime( $r->created_at ) ),
            ], $results ),
        ];
    }

    private static function flat_students_list( array $p ): array {
        $d = self::get_students_list( $p );
        return [ $d['headers'], $d['rows'], 'estudiantes-lista', __( 'Lista de Estudiantes', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 2: Estado de pagos por curso ─────────────────────
    // ─────────────────────────────────────────────────────────────

    private static function get_payments_by_course( array $p ): array {
        global $wpdb;
        $where = [ 'e.status != \'withdrawn\'' ];
        $args  = [];

        if ( $p['course_id'] ) {
            $where[] = 'e.course_id = %d';
            $args[]  = $p['course_id'];
        }
        if ( $p['area_id'] ) {
            $where[] = 'c.area_id = %d';
            $args[]  = $p['area_id'];
        }

        $sql = 'SELECT c.id AS course_id, c.name AS course_name,
                       a.name AS area_name,
                       COUNT(DISTINCT e.student_id) AS total_students,
                       SUM(e.net_cost) AS total_expected,
                       SUM(e.total_paid) AS total_paid,
                       SUM(e.balance_due) AS balance_due,
                       SUM(CASE WHEN e.payment_status = \'paid\'    THEN 1 ELSE 0 END) AS cnt_paid,
                       SUM(CASE WHEN e.payment_status = \'partial\'  THEN 1 ELSE 0 END) AS cnt_partial,
                       SUM(CASE WHEN e.payment_status = \'overdue\'  THEN 1 ELSE 0 END) AS cnt_overdue,
                       SUM(CASE WHEN e.payment_status = \'unpaid\'   THEN 1 ELSE 0 END) AS cnt_unpaid
                FROM ' . $wpdb->prefix . 'aura_student_enrollments e
                JOIN ' . $wpdb->prefix . 'aura_student_courses c  ON c.id = e.course_id
                LEFT JOIN ' . $wpdb->prefix . 'aura_areas a       ON a.id = c.area_id
                WHERE ' . implode( ' AND ', $where ) . '
                GROUP BY c.id
                ORDER BY a.name, c.name';

        $results = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );

        return [
            'headers' => [
                __( 'Curso', 'aura-suite' ), __( 'Área', 'aura-suite' ),
                __( 'Estudiantes', 'aura-suite' ), __( 'Esperado', 'aura-suite' ),
                __( 'Cobrado', 'aura-suite' ), __( 'Pendiente', 'aura-suite' ),
                __( 'Al día', 'aura-suite' ), __( 'Parcial', 'aura-suite' ),
                __( 'Vencidos', 'aura-suite' ), __( 'Sin pago', 'aura-suite' ),
            ],
            'rows' => array_map( fn( $r ) => [
                $r->course_name, $r->area_name ?? '—',
                $r->total_students,
                number_format( (float) $r->total_expected, 2 ),
                number_format( (float) $r->total_paid, 2 ),
                number_format( (float) $r->balance_due, 2 ),
                $r->cnt_paid, $r->cnt_partial, $r->cnt_overdue, $r->cnt_unpaid,
            ], $results ),
        ];
    }

    private static function flat_payments_by_course( array $p ): array {
        $d = self::get_payments_by_course( $p );
        return [ $d['headers'], $d['rows'], 'pagos-por-curso', __( 'Estado de Pagos por Curso', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 3: Inscritos por área/programa ───────────────────
    // ─────────────────────────────────────────────────────────────

    private static function get_enrolled_by_area( array $p ): array {
        global $wpdb;
        $where = [ "a.type = 'program'", 'a.status = \'active\'', 's.deleted_at IS NULL' ];
        $args  = [];

        if ( $p['area_id'] ) {
            $where[] = 'a.id = %d';
            $args[]  = $p['area_id'];
        }

        $sql = 'SELECT a.id AS area_id, a.name AS area_name,
                       COUNT(DISTINCT e.student_id) AS total_students,
                       SUM(CASE WHEN s.status = \'active\'    THEN 1 ELSE 0 END) AS cnt_active,
                       SUM(CASE WHEN s.status = \'graduated\' THEN 1 ELSE 0 END) AS cnt_graduated,
                       COUNT(DISTINCT c.id) AS total_courses
                FROM ' . $wpdb->prefix . 'aura_areas a
                JOIN ' . $wpdb->prefix . 'aura_student_courses c     ON c.area_id = a.id
                JOIN ' . $wpdb->prefix . 'aura_student_enrollments e ON e.course_id = c.id
                JOIN ' . $wpdb->prefix . 'aura_students s             ON s.id = e.student_id
                WHERE ' . implode( ' AND ', $where ) . '
                GROUP BY a.id
                ORDER BY a.name';

        $results = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );

        return [
            'headers' => [
                __( 'Área / Programa', 'aura-suite' ),
                __( 'Cursos', 'aura-suite' ),
                __( 'Total inscritos', 'aura-suite' ),
                __( 'Activos', 'aura-suite' ),
                __( 'Graduados', 'aura-suite' ),
            ],
            'rows' => array_map( fn( $r ) => [
                $r->area_name, $r->total_courses,
                $r->total_students, $r->cnt_active, $r->cnt_graduated,
            ], $results ),
        ];
    }

    private static function flat_enrolled_by_area( array $p ): array {
        $d = self::get_enrolled_by_area( $p );
        return [ $d['headers'], $d['rows'], 'inscritos-por-area', __( 'Inscritos por Área/Programa', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 4: Ingresos por área/programa (Excel only) ───────
    // ─────────────────────────────────────────────────────────────

    private static function get_income_by_area( array $p ): array {
        global $wpdb;

        $sql = "SELECT a.name AS area_name,
                       COUNT(DISTINCT e.student_id) AS total_students,
                       SUM(e.net_cost)    AS total_expected,
                       SUM(e.total_paid)  AS total_paid,
                       SUM(e.balance_due) AS total_pending
                FROM {$wpdb->prefix}aura_areas a
                JOIN {$wpdb->prefix}aura_student_courses c      ON c.area_id = a.id
                JOIN {$wpdb->prefix}aura_student_enrollments e  ON e.course_id = c.id
                WHERE a.type = 'program' AND a.status = 'active'
                GROUP BY a.id
                ORDER BY total_expected DESC";

        $results = $wpdb->get_results( $sql );
        $currency = get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD';

        return [
            'headers' => [
                __( 'Área / Programa', 'aura-suite' ),
                __( 'Estudiantes', 'aura-suite' ),
                __( "Esperado ({$currency})", 'aura-suite' ),
                __( "Cobrado ({$currency})", 'aura-suite' ),
                __( "Pendiente ({$currency})", 'aura-suite' ),
                __( '% Recaudado', 'aura-suite' ),
            ],
            'rows' => array_map( function( $r ) {
                $pct = $r->total_expected > 0
                    ? round( ( $r->total_paid / $r->total_expected ) * 100, 1 )
                    : 0;
                return [
                    $r->area_name,
                    $r->total_students,
                    number_format( (float) $r->total_expected, 2 ),
                    number_format( (float) $r->total_paid,     2 ),
                    number_format( (float) $r->total_pending,  2 ),
                    $pct . '%',
                ];
            }, $results ),
        ];
    }

    private static function flat_income_by_area( array $p ): array {
        $d = self::get_income_by_area( $p );
        return [ $d['headers'], $d['rows'], 'ingresos-por-area', __( 'Ingresos por Área/Programa', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 5: Morosos ───────────────────────────────────────
    // ─────────────────────────────────────────────────────────────

    private static function get_overdue( array $p ): array {
        global $wpdb;
        $today = gmdate( 'Y-m-d' );
        $where = [ 'sch.status = \'overdue\'', 's.deleted_at IS NULL' ];
        $args  = [];

        if ( $p['course_id'] ) {
            $where[] = 'e.course_id = %d';
            $args[]  = $p['course_id'];
        }
        if ( $p['area_id'] ) {
            $where[] = 'c.area_id = %d';
            $args[]  = $p['area_id'];
        }

        $sql = 'SELECT s.first_name, s.last_name, s.email, s.phone,
                       c.name AS course_name, a.name AS area_name,
                       sch.installment_num, sch.due_date, sch.expected_amount,
                       DATEDIFF(%s, sch.due_date) AS days_overdue,
                       e.balance_due AS total_balance
                FROM ' . $wpdb->prefix . 'aura_student_installment_schedule sch
                JOIN ' . $wpdb->prefix . 'aura_student_enrollments e ON e.id = sch.enrollment_id
                JOIN ' . $wpdb->prefix . 'aura_student_courses c     ON c.id = e.course_id
                LEFT JOIN ' . $wpdb->prefix . 'aura_areas a          ON a.id = c.area_id
                JOIN ' . $wpdb->prefix . 'aura_students s             ON s.id = e.student_id
                WHERE ' . implode( ' AND ', $where ) . '
                ORDER BY days_overdue DESC, s.last_name';

        array_unshift( $args, $today );
        $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

        return [
            'headers' => [
                __( 'Estudiante', 'aura-suite' ), __( 'Email', 'aura-suite' ),
                __( 'Teléfono', 'aura-suite' ), __( 'Curso', 'aura-suite' ),
                __( 'Área', 'aura-suite' ), __( 'Cuota #', 'aura-suite' ),
                __( 'Vencimiento', 'aura-suite' ), __( 'Días vencida', 'aura-suite' ),
                __( 'Monto cuota', 'aura-suite' ), __( 'Saldo total', 'aura-suite' ),
            ],
            'rows' => array_map( fn( $r ) => [
                $r->first_name . ' ' . $r->last_name,
                $r->email, $r->phone ?? '—',
                $r->course_name, $r->area_name ?? '—',
                $r->installment_num,
                date_i18n( get_option( 'date_format' ), strtotime( $r->due_date ) ),
                $r->days_overdue,
                number_format( (float) $r->expected_amount, 2 ),
                number_format( (float) $r->total_balance,   2 ),
            ], $results ),
        ];
    }

    private static function flat_overdue( array $p ): array {
        $d = self::get_overdue( $p );
        return [ $d['headers'], $d['rows'], 'morosos', __( 'Morosos — Cuotas Vencidas', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 6: Proyección de ingresos por mes (Excel only) ──
    // ─────────────────────────────────────────────────────────────

    private static function get_income_projection( array $p ): array {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE_FORMAT(sch.due_date, '%%Y-%%m') AS month_key,
                    MIN(DATE_FORMAT(sch.due_date, '%%M %%Y')) AS month_label,
                    SUM(sch.expected_amount) AS total_expected,
                    SUM(CASE WHEN sch.status = 'paid' THEN sch.expected_amount ELSE 0 END) AS total_paid,
                    SUM(CASE WHEN sch.status IN ('pending','overdue') THEN sch.expected_amount ELSE 0 END) AS total_pending,
                    COUNT(*) AS installment_count
             FROM   {$wpdb->prefix}aura_student_installment_schedule sch
             JOIN   {$wpdb->prefix}aura_student_enrollments e ON e.id = sch.enrollment_id
             WHERE  sch.due_date BETWEEN %s AND %s
               AND  e.status NOT IN ('withdrawn')
             GROUP BY month_key
             ORDER BY month_key",
            $p['start'],
            $p['end']
        ) );

        $currency = get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD';

        return [
            'headers' => [
                __( 'Mes', 'aura-suite' ),
                __( 'N° cuotas', 'aura-suite' ),
                __( "Proyectado ({$currency})", 'aura-suite' ),
                __( "Cobrado ({$currency})", 'aura-suite' ),
                __( "Pendiente ({$currency})", 'aura-suite' ),
            ],
            'rows' => array_map( fn( $r ) => [
                $r->month_label,
                $r->installment_count,
                number_format( (float) $r->total_expected, 2 ),
                number_format( (float) $r->total_paid,     2 ),
                number_format( (float) $r->total_pending,  2 ),
            ], $results ),
        ];
    }

    private static function flat_income_projection( array $p ): array {
        $d = self::get_income_projection( $p );
        return [ $d['headers'], $d['rows'], 'proyeccion-ingresos', __( 'Proyección de Ingresos por Mes', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 7: Becas otorgadas ───────────────────────────────
    // ─────────────────────────────────────────────────────────────

    private static function get_scholarships( array $p ): array {
        global $wpdb;
        $where = [ "e.scholarship_type != 'none'" ];
        $args  = [];

        if ( $p['course_id'] ) {
            $where[] = 'e.course_id = %d';
            $args[]  = $p['course_id'];
        }
        if ( $p['area_id'] ) {
            $where[] = 'c.area_id = %d';
            $args[]  = $p['area_id'];
        }

        $sql = 'SELECT s.first_name, s.last_name, s.email,
                       c.name AS course_name, a.name AS area_name,
                       e.scholarship_type, e.scholarship_pct, e.scholarship_sponsor,
                       e.base_cost, e.net_cost,
                       (e.base_cost - e.net_cost) AS discount_amount,
                       e.created_at
                FROM ' . $wpdb->prefix . 'aura_student_enrollments e
                JOIN ' . $wpdb->prefix . 'aura_students s             ON s.id = e.student_id
                JOIN ' . $wpdb->prefix . 'aura_student_courses c      ON c.id = e.course_id
                LEFT JOIN ' . $wpdb->prefix . 'aura_areas a           ON a.id = c.area_id
                WHERE s.deleted_at IS NULL AND ' . implode( ' AND ', $where ) . '
                ORDER BY e.scholarship_type, s.last_name';

        $results = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );

        $type_labels = [
            'internal' => __( 'Interna', 'aura-suite' ),
            'external' => __( 'Externa', 'aura-suite' ),
        ];

        return [
            'headers' => [
                __( 'Estudiante', 'aura-suite' ), __( 'Email', 'aura-suite' ),
                __( 'Curso', 'aura-suite' ), __( 'Área', 'aura-suite' ),
                __( 'Tipo beca', 'aura-suite' ), __( '% beca', 'aura-suite' ),
                __( 'Patrocinador', 'aura-suite' ),
                __( 'Costo base', 'aura-suite' ), __( 'Descuento', 'aura-suite' ),
                __( 'Costo neto', 'aura-suite' ),
                __( 'Fecha inscripción', 'aura-suite' ),
            ],
            'rows' => array_map( fn( $r ) => [
                $r->first_name . ' ' . $r->last_name,
                $r->email,
                $r->course_name,
                $r->area_name ?? '—',
                $type_labels[ $r->scholarship_type ] ?? $r->scholarship_type,
                $r->scholarship_pct . '%',
                $r->scholarship_sponsor ?? '—',
                number_format( (float) $r->base_cost,       2 ),
                number_format( (float) $r->discount_amount, 2 ),
                number_format( (float) $r->net_cost,        2 ),
                date_i18n( get_option( 'date_format' ), strtotime( $r->created_at ) ),
            ], $results ),
        ];
    }

    private static function flat_scholarships( array $p ): array {
        $d = self::get_scholarships( $p );
        return [ $d['headers'], $d['rows'], 'becas-otorgadas', __( 'Becas Otorgadas', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // ── REPORTE 8: Graduados por período ─────────────────────────
    // ─────────────────────────────────────────────────────────────

    private static function get_graduates( array $p ): array {
        global $wpdb;
        $where = [ "s.status = 'graduated'", 's.deleted_at IS NULL' ];
        $args  = [];

        if ( $p['start'] ) {
            $where[] = 's.graduated_at >= %s';
            $args[]  = $p['start'];
        }
        if ( $p['end'] ) {
            $where[] = 's.graduated_at <= %s';
            $args[]  = $p['end'];
        }
        if ( $p['area_id'] ) {
            $where[] = 'c.area_id = %d';
            $args[]  = $p['area_id'];
        }
        if ( $p['course_id'] ) {
            $where[] = 'e.course_id = %d';
            $args[]  = $p['course_id'];
        }

        $sql = 'SELECT s.first_name, s.last_name, s.email, s.profile_type,
                       s.graduated_at,
                       c.name AS course_name, a.name AS area_name,
                       e.net_cost, e.total_paid
                FROM ' . $wpdb->prefix . 'aura_students s
                JOIN ' . $wpdb->prefix . 'aura_student_enrollments e ON e.student_id = s.id
                JOIN ' . $wpdb->prefix . 'aura_student_courses c     ON c.id = e.course_id
                LEFT JOIN ' . $wpdb->prefix . 'aura_areas a          ON a.id = c.area_id
                WHERE ' . implode( ' AND ', $where ) . '
                ORDER BY s.graduated_at DESC, s.last_name';

        $results = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );

        return [
            'headers' => [
                __( 'Nombre', 'aura-suite' ), __( 'Email', 'aura-suite' ),
                __( 'Tipo', 'aura-suite' ), __( 'Graduado el', 'aura-suite' ),
                __( 'Curso', 'aura-suite' ), __( 'Área', 'aura-suite' ),
                __( 'Costo neto', 'aura-suite' ), __( 'Total pagado', 'aura-suite' ),
            ],
            'rows' => array_map( fn( $r ) => [
                $r->first_name . ' ' . $r->last_name,
                $r->email,
                self::profile_label( $r->profile_type ),
                $r->graduated_at ? date_i18n( get_option( 'date_format' ), strtotime( $r->graduated_at ) ) : '—',
                $r->course_name,
                $r->area_name ?? '—',
                number_format( (float) $r->net_cost,   2 ),
                number_format( (float) $r->total_paid, 2 ),
            ], $results ),
        ];
    }

    private static function flat_graduates( array $p ): array {
        $d = self::get_graduates( $p );
        return [ $d['headers'], $d['rows'], 'graduados', __( 'Graduados por Período', 'aura-suite' ) ];
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS DE ETIQUETAS
    // ─────────────────────────────────────────────────────────────

    private static function profile_label( string $type ): string {
        return [
            'student'     => __( 'Estudiante', 'aura-suite' ),
            'volunteer'   => __( 'Voluntario', 'aura-suite' ),
            'teacher'     => __( 'Instructor', 'aura-suite' ),
            'participant' => __( 'Participante', 'aura-suite' ),
            'intern'      => __( 'Practicante', 'aura-suite' ),
        ][ $type ] ?? $type;
    }

    private static function status_label( string $status ): string {
        return [
            'applicant'  => __( 'Postulante', 'aura-suite' ),
            'approved'   => __( 'Aprobado', 'aura-suite' ),
            'active'     => __( 'Activo', 'aura-suite' ),
            'graduated'  => __( 'Graduado', 'aura-suite' ),
            'withdrawn'  => __( 'Retirado', 'aura-suite' ),
            'rejected'   => __( 'Rechazado', 'aura-suite' ),
        ][ $status ] ?? $status;
    }
}
