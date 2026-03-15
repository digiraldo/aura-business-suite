<?php
/**
 * Reportes Financieros Predefinidos — Fase 3, Item 3.2
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_Reports {

    // ─── Bootstrap ───────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_generate_report',      [ __CLASS__, 'ajax_generate_report' ] );
        add_action( 'wp_ajax_aura_export_report_csv',    [ __CLASS__, 'ajax_export_csv' ] );
        add_action( 'wp_ajax_aura_export_report_excel',  [ __CLASS__, 'ajax_export_excel' ] );
        add_action( 'wp_ajax_aura_save_report_config',   [ __CLASS__, 'ajax_save_config' ] );
        add_action( 'wp_ajax_aura_load_report_configs',  [ __CLASS__, 'ajax_load_configs' ] );
        add_action( 'wp_ajax_aura_delete_report_config', [ __CLASS__, 'ajax_delete_config' ] );

        // Cron para reportes programados
        add_action( 'aura_finance_scheduled_reports', [ __CLASS__, 'run_scheduled_reports' ] );
        if ( ! wp_next_scheduled( 'aura_finance_scheduled_reports' ) ) {
            wp_schedule_event( time(), 'daily', 'aura_finance_scheduled_reports' );
        }
    }

    public static function render(): void {
        if ( ! self::can_access() ) {
            wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/financial/reports-page.php';
    }

    // ─── Permisos ─────────────────────────────────────────────────────────────

    private static function can_access(): bool {
        return current_user_can( 'aura_finance_view_all' )
            || current_user_can( 'aura_finance_view_own' )
            || current_user_can( 'manage_options' );
    }

    private static function can_view_all(): bool {
        return current_user_can( 'aura_finance_view_all' )
            || current_user_can( 'manage_options' );
    }

    /**
     * Traducir método de pago de inglés a español
     * 
     * @param string $payment_method Método de pago en inglés o español
     * @return string Método de pago en español
     */
    private static function translate_payment_method( $payment_method ) {
        if ( empty( $payment_method ) ) {
            return '-';
        }
        
        $translations = array(
            'cash'     => 'Efectivo',
            'transfer' => 'Transferencia',
            'check'    => 'Cheque',
            'card'     => 'Tarjeta',
            'other'    => 'Otro'
        );
        
        return $translations[ $payment_method ] ?? $payment_method;
    }

    // ─── Parámetros comunes ───────────────────────────────────────────────────

    private static function get_base_params( string $source = 'POST' ): array {
        $data  = $source === 'GET' ? $_GET : $_POST;
        $start = sanitize_text_field( $data['start'] ?? date( 'Y-m-01' ) );
        $end   = sanitize_text_field( $data['end']   ?? date( 'Y-m-t' ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) $start = date( 'Y-m-01' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) )   $end   = date( 'Y-m-t' );
        return [
            'start'      => $start,
            'end'        => $end,
            'status'     => sanitize_text_field( $data['status'] ?? 'all' ),
            'categories' => array_map( 'absint', (array) ( $data['categories'] ?? [] ) ),
            'created_by' => absint( $data['created_by'] ?? 0 ),
            'area_id'    => absint( $data['area_id']    ?? 0 ),
        ];
    }

    // ─── AJAX: Generar reporte (vista en pantalla) ────────────────────────────

    public static function ajax_generate_report(): void {
        check_ajax_referer( 'aura_reports_nonce', 'nonce' );
        if ( ! self::can_access() ) {
            wp_send_json_error( 'Forbidden' );
        }

        $type   = sanitize_text_field( $_POST['report_type'] ?? '' );
        $params = self::get_base_params( 'POST' );

        $data = match ( $type ) {
            'pl'                 => self::get_pl_data( $params ),
            'cashflow'           => self::get_cashflow_data( $params ),
            'categories'         => self::get_category_data( $params ),
            'pending'            => self::get_pending_data(),
            'budget'             => self::get_budget_data( $params ),
            'budget_area_detail' => self::get_budget_area_detail( $params ),
            'audit'              => self::get_audit_data( $params ),
            'user_payments'      => self::get_user_payments_data( $params ),
            default              => null,
        };

        if ( $data === null ) {
            wp_send_json_error( 'Tipo de reporte no válido' );
        }

        wp_send_json_success( [ 'type' => $type, 'params' => $params, 'data' => $data ] );
    }

    // ─── AJAX: Exportar CSV ───────────────────────────────────────────────────

    public static function ajax_export_csv(): void {
        check_ajax_referer( 'aura_reports_export', 'nonce' );
        if ( ! self::can_access() ) {
            wp_die( 'Forbidden' );
        }

        $type   = sanitize_text_field( $_GET['report_type'] ?? '' );
        $params = self::get_base_params( 'GET' );

        [ $headers, $rows, $filename ] = self::get_flat_data( $type, $params );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, $headers, ';' );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row, ';' );
        }
        fclose( $out );
        exit;
    }

    // ─── AJAX: Exportar Excel ─────────────────────────────────────────────────

    public static function ajax_export_excel(): void {
        check_ajax_referer( 'aura_reports_export', 'nonce' );
        if ( ! self::can_access() ) {
            wp_die( 'Forbidden' );
        }

        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            wp_die( 'PhpSpreadsheet no disponible. Ejecuta: composer install en el plugin.' );
        }
        require_once $autoload;

        $type   = sanitize_text_field( $_GET['report_type'] ?? '' );
        $params = self::get_base_params( 'GET' );
        [ $headers, $rows, $filename, $title ] = self::get_flat_data( $type, $params );

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle( mb_substr( $title, 0, 31 ) );

        // Cabecera → negrita + fondo azul + texto blanco
        $col = 1;
        foreach ( $headers as $h ) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col ) . '1';
            $sheet->getCell( $coord )->setValue( $h );
            $style = $sheet->getStyle( $coord );
            $style->getFont()->setBold( true );
            $style->getFill()
                ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                ->getStartColor()->setRGB( '2563EB' );
            $style->getFont()->getColor()->setRGB( 'FFFFFF' );
            $style->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
            $col++;
        }

        // Filas de datos
        $rowNum = 2;
        foreach ( $rows as $row ) {
            $col = 1;
            foreach ( $row as $val ) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col ) . $rowNum;
                $sheet->getCell( $coord )->setValue( $val );
                $col++;
            }
            // Fila alternante para legibilidad
            if ( $rowNum % 2 === 0 ) {
                $range = 'A' . $rowNum . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( count( $headers ) ) . $rowNum;
                $sheet->getStyle( $range )->getFill()
                    ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                    ->getStartColor()->setRGB( 'EFF6FF' );
            }
            $rowNum++;
        }

        // Fila de total al final si aplica
        $totals_row = $rowNum;
        $sheet->getCell( 'A' . $totals_row )->setValue( 'Total de registros: ' . count( $rows ) );
        $sheet->getStyle( 'A' . $totals_row )->getFont()->setBold( true );

        // Auto-tamaño de columnas
        foreach ( range( 1, count( $headers ) ) as $c ) {
            $sheet->getColumnDimensionByColumn( $c )->setAutoSize( true );
        }

        // Freeze primera fila
        $sheet->freezePane( 'A2' );

        // Pie de página
        $sheet->getHeaderFooter()
            ->setOddFooter( '&L&"Calibri"Aura Business Suite&R&"Calibri"Generado: ' . date( 'd/m/Y H:i' ) );

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
        header( 'Cache-Control: max-age=0' );

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( 'php://output' );
        exit;
    }

    // ─── AJAX: Guardar/Cargar/Eliminar configuraciones ───────────────────────

    public static function ajax_save_config(): void {
        check_ajax_referer( 'aura_reports_nonce', 'nonce' );
        if ( ! self::can_access() ) {
            wp_send_json_error( 'Forbidden' );
        }

        $name = sanitize_text_field( $_POST['config_name'] ?? '' );
        if ( ! $name ) {
            wp_send_json_error( 'Nombre requerido' );
        }

        $config = [
            'report_type' => sanitize_text_field( $_POST['report_type'] ?? '' ),
            'start'       => sanitize_text_field( $_POST['start'] ?? '' ),
            'end'         => sanitize_text_field( $_POST['end'] ?? '' ),
            'status'      => sanitize_text_field( $_POST['status'] ?? 'all' ),
        ];

        $uid     = get_current_user_id();
        $configs = get_user_meta( $uid, 'aura_report_configs', true ) ?: [];
        $key     = sanitize_key( $name );
        $configs[ $key ] = [
            'key'      => $key,
            'name'     => $name,
            'config'   => $config,
            'saved_at' => current_time( 'mysql' ),
        ];
        update_user_meta( $uid, 'aura_report_configs', $configs );

        wp_send_json_success( [ 'configs' => array_values( $configs ) ] );
    }

    public static function ajax_load_configs(): void {
        check_ajax_referer( 'aura_reports_nonce', 'nonce' );
        if ( ! self::can_access() ) {
            wp_send_json_error( 'Forbidden' );
        }
        $configs = get_user_meta( get_current_user_id(), 'aura_report_configs', true ) ?: [];
        wp_send_json_success( [ 'configs' => array_values( $configs ) ] );
    }

    public static function ajax_delete_config(): void {
        check_ajax_referer( 'aura_reports_nonce', 'nonce' );
        if ( ! self::can_access() ) {
            wp_send_json_error( 'Forbidden' );
        }
        $key     = sanitize_key( $_POST['config_key'] ?? '' );
        $uid     = get_current_user_id();
        $configs = get_user_meta( $uid, 'aura_report_configs', true ) ?: [];
        unset( $configs[ $key ] );
        update_user_meta( $uid, 'aura_report_configs', $configs );
        wp_send_json_success();
    }

    // ─── Datos planos para exportación ───────────────────────────────────────

    /**
     * @return array{0:string[], 1:array[], 2:string, 3:string}
     */
    private static function get_flat_data( string $type, array $params ): array {
        return match ( $type ) {
            'pl'                 => self::pl_flat( $params ),
            'cashflow'           => self::cashflow_flat( $params ),
            'categories'         => self::categories_flat( $params ),
            'pending'            => self::pending_flat(),
            'budget'             => self::budget_flat( $params ),
            'budget_area_detail' => self::budget_area_detail_flat( $params ),
            'audit'              => self::audit_flat( $params ),
            'user_payments'      => self::user_payments_flat( $params ),
            default              => [ [ 'Error' ], [ [ 'Tipo no válido' ] ], 'reporte', 'Error' ],
        };
    }

    // ─── A. Estado de Resultados (P&L) ────────────────────────────────────────

    private static function get_pl_data( array $p ): array {
        global $wpdb;
        $t  = $wpdb->prefix . 'aura_finance_transactions';
        $ct = $wpdb->prefix . 'aura_finance_categories';

        $status_sql = $p['status'] !== 'all'
            ? $wpdb->prepare( 'AND t.status = %s', $p['status'] )
            : "AND t.status = 'approved'";

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.transaction_type,
                    COALESCE(c.name, 'Sin categoría') AS category,
                    COALESCE(c.color, '#6B7280') AS color,
                    SUM(t.amount) AS total
             FROM {$t} t
             LEFT JOIN {$ct} c ON t.category_id = c.id
             WHERE t.transaction_date BETWEEN %s AND %s
               AND t.deleted_at IS NULL {$status_sql}
             GROUP BY t.transaction_type, c.id, c.name, c.color
             ORDER BY t.transaction_type DESC, total DESC",
            $p['start'], $p['end']
        ) );

        $income   = array_values( array_filter( $rows, fn( $r ) => $r->transaction_type === 'income' ) );
        $expenses = array_values( array_filter( $rows, fn( $r ) => $r->transaction_type === 'expense' ) );
        $total_in = array_sum( array_column( $income,   'total' ) );
        $total_ex = array_sum( array_column( $expenses, 'total' ) );

        // Año anterior (mismo período)
        $prev_start = date( 'Y-m-d', strtotime( '-1 year', strtotime( $p['start'] ) ) );
        $prev_end   = date( 'Y-m-d', strtotime( '-1 year', strtotime( $p['end'] ) ) );
        $prev = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(CASE WHEN transaction_type = 'income'  THEN amount ELSE 0 END) AS total_in,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_ex
             FROM {$t}
             WHERE transaction_date BETWEEN %s AND %s AND deleted_at IS NULL {$status_sql}",
            $prev_start, $prev_end
        ) );

        foreach ( $income   as &$r ) { $r->total = (float) $r->total; }
        foreach ( $expenses as &$r ) { $r->total = (float) $r->total; }
        unset( $r );

        return [
            'income'        => $income,
            'expenses'      => $expenses,
            'total_income'  => (float) $total_in,
            'total_expense' => (float) $total_ex,
            'net_balance'   => (float) ( $total_in - $total_ex ),
            'prev_year'     => $prev
                ? [ 'income' => (float) $prev->total_in, 'expense' => (float) $prev->total_ex,
                    'income_pct_change'  => self::pct( (float) $total_in, (float) $prev->total_in ),
                    'expense_pct_change' => self::pct( (float) $total_ex, (float) $prev->total_ex ) ]
                : null,
        ];
    }

    private static function pl_flat( array $p ): array {
        $d       = self::get_pl_data( $p );
        $headers = [ 'Tipo', 'Categoría', 'Monto (USD)' ];
        $rows    = [];
        foreach ( $d['income']   as $r ) $rows[] = [ 'Ingreso', $r->category, number_format( $r->total, 2, '.', ',' ) ];
        foreach ( $d['expenses'] as $r ) $rows[] = [ 'Egreso',  $r->category, number_format( $r->total, 2, '.', ',' ) ];
        $rows[] = [ 'TOTAL INGRESOS', '', number_format( $d['total_income'],  2, '.', ',' ) ];
        $rows[] = [ 'TOTAL EGRESOS',  '', number_format( $d['total_expense'], 2, '.', ',' ) ];
        $rows[] = [ 'BALANCE NETO',   '', number_format( $d['net_balance'],   2, '.', ',' ) ];
        return [ $headers, $rows, 'estado-resultados-' . $p['start'] . '-' . $p['end'], 'Estado de Resultados' ];
    }

    // ─── B. Flujo de Efectivo ─────────────────────────────────────────────────

    private static function get_cashflow_data( array $p ): array {
        global $wpdb;
        $t          = $wpdb->prefix . 'aura_finance_transactions';
        $status_sql = $p['status'] !== 'all'
            ? $wpdb->prepare( 'AND status = %s', $p['status'] )
            : "AND status = 'approved'";

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(payment_method, 'Sin especificar') AS payment_method,
                    transaction_type,
                    SUM(amount) AS total,
                    COUNT(*) AS count
             FROM {$t}
             WHERE transaction_date BETWEEN %s AND %s AND deleted_at IS NULL {$status_sql}
             GROUP BY payment_method, transaction_type
             ORDER BY transaction_type DESC, total DESC",
            $p['start'], $p['end']
        ) );

        $by_method = [];
        foreach ( $rows as $r ) {
            $by_method[ $r->payment_method ][ $r->transaction_type ] = [
                'total' => (float) $r->total,
                'count' => (int) $r->count,
            ];
        }

        $totals = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(CASE WHEN transaction_type = 'income'  THEN amount ELSE 0 END) AS total_in,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) AS total_ex
             FROM {$t}
             WHERE transaction_date BETWEEN %s AND %s AND deleted_at IS NULL {$status_sql}",
            $p['start'], $p['end']
        ) );

        return [
            'by_method' => $by_method,
            'total_in'  => (float) ( $totals->total_in ?? 0 ),
            'total_out' => (float) ( $totals->total_ex ?? 0 ),
            'net'       => (float) ( ( $totals->total_in ?? 0 ) - ( $totals->total_ex ?? 0 ) ),
        ];
    }

    private static function cashflow_flat( array $p ): array {
        $d       = self::get_cashflow_data( $p );
        $headers = [ 'Método de Pago', 'Transacciones Entrada', 'Entradas (USD)', 'Transacciones Salida', 'Salidas (USD)', 'Neto (USD)' ];
        $rows    = [];
        foreach ( $d['by_method'] as $method => $types ) {
            $in_count  = $types['income']['count']   ?? 0;
            $out_count = $types['expense']['count']  ?? 0;
            $in        = $types['income']['total']   ?? 0;
            $out       = $types['expense']['total']  ?? 0;
            $rows[] = [ $method, $in_count, number_format( $in, 2, '.', ',' ), $out_count, number_format( $out, 2, '.', ',' ), number_format( $in - $out, 2, '.', ',' ) ];
        }
        $rows[] = [ 'TOTALES', '', number_format( $d['total_in'], 2, '.', ',' ), '', number_format( $d['total_out'], 2, '.', ',' ), number_format( $d['net'], 2, '.', ',' ) ];
        return [ $headers, $rows, 'flujo-efectivo-' . $p['start'] . '-' . $p['end'], 'Flujo de Efectivo' ];
    }

    // ─── C. Análisis por Categoría ────────────────────────────────────────────

    private static function get_category_data( array $p ): array {
        global $wpdb;
        $t          = $wpdb->prefix . 'aura_finance_transactions';
        $ct         = $wpdb->prefix . 'aura_finance_categories';
        $status_sql = $p['status'] !== 'all'
            ? $wpdb->prepare( 'AND t.status = %s', $p['status'] )
            : '';

        $top10 = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.id, COALESCE(c.name,'Sin categoría') AS name,
                    COALESCE(c.color,'#6B7280') AS color,
                    t.transaction_type,
                    SUM(t.amount) AS total, COUNT(*) AS count
             FROM {$t} t
             LEFT JOIN {$ct} c ON t.category_id = c.id
             WHERE t.transaction_date BETWEEN %s AND %s AND t.deleted_at IS NULL {$status_sql}
             GROUP BY c.id, c.name, c.color, t.transaction_type
             ORDER BY total DESC
             LIMIT 10",
            $p['start'], $p['end']
        ) );

        // Tendencia mensual para categorías del top10
        $cat_ids = array_unique( array_filter( array_column( $top10, 'id' ) ) );
        $trend   = [];
        if ( $cat_ids ) {
            $id_list   = implode( ',', array_map( 'intval', $cat_ids ) );
            $trend_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT category_id, DATE_FORMAT(transaction_date,'%%Y-%%m') AS month, SUM(amount) AS total
                 FROM {$t}
                 WHERE category_id IN ({$id_list})
                   AND transaction_date BETWEEN %s AND %s
                   AND deleted_at IS NULL {$status_sql}
                 GROUP BY category_id, month
                 ORDER BY month",
                $p['start'], $p['end']
            ) );
            foreach ( $trend_rows as $r ) {
                $trend[ $r->category_id ][ $r->month ] = (float) $r->total;
            }
        }

        $grand_total = array_sum( array_column( $top10, 'total' ) );
        foreach ( $top10 as &$row ) {
            $row->total      = (float) $row->total;
            $row->count      = (int) $row->count;
            $row->percentage = $grand_total > 0 ? round( $row->total / $grand_total * 100, 1 ) : 0;
            $row->trend      = $trend[ $row->id ] ?? [];
        }
        unset( $row );

        return [ 'top10' => $top10, 'grand_total' => (float) $grand_total ];
    }

    private static function categories_flat( array $p ): array {
        $d       = self::get_category_data( $p );
        $headers = [ 'Categoría', 'Tipo', 'Total (USD)', 'Transacciones', '% del Total' ];
        $rows    = [];
        foreach ( $d['top10'] as $r ) {
            $rows[] = [
                $r->name,
                $r->transaction_type === 'income' ? 'Ingreso' : 'Egreso',
                number_format( $r->total, 2, '.', ',' ),
                $r->count,
                $r->percentage . '%',
            ];
        }
        return [ $headers, $rows, 'analisis-categorias-' . $p['start'] . '-' . $p['end'], 'Análisis por Categoría' ];
    }

    // ─── D. Transacciones Pendientes ──────────────────────────────────────────

    private static function get_pending_data(): array {
        global $wpdb;
        $t        = $wpdb->prefix . 'aura_finance_transactions';
        $ct       = $wpdb->prefix . 'aura_finance_categories';
        $user_sql = self::can_view_all() ? '' : $wpdb->prepare( 'AND t.created_by = %d', get_current_user_id() );

        $rows = $wpdb->get_results(
            "SELECT t.id, t.transaction_type, t.amount, t.description, t.transaction_date,
                    t.payment_method, t.reference_number, t.created_at,
                    COALESCE(c.name,'Sin categoría') AS category_name,
                    u.display_name AS creator_name,
                    DATEDIFF(NOW(), t.created_at) AS age_days
             FROM {$t} t
             LEFT JOIN {$ct} c ON t.category_id = c.id
             LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
             WHERE t.status = 'pending' AND t.deleted_at IS NULL {$user_sql}
             ORDER BY t.created_by, t.created_at ASC"
        );

        $by_user = [];
        $total   = 0.0;
        foreach ( $rows as $r ) {
            $r->amount   = (float) $r->amount;
            $r->age_days = (int) $r->age_days;
            $total      += $r->amount;
            $by_user[ $r->creator_name ?? 'Desconocido' ][] = $r;
        }

        return [ 'by_user' => $by_user, 'total_pending' => $total, 'count' => count( $rows ) ];
    }

    private static function pending_flat(): array {
        $d       = self::get_pending_data();
        $headers = [ 'Usuario', 'Tipo', 'Categoría', 'Monto (USD)', 'Descripción', 'Fecha', 'Días pendiente' ];
        $rows    = [];
        foreach ( $d['by_user'] as $user => $txs ) {
            foreach ( $txs as $r ) {
                $rows[] = [
                    $user,
                    $r->transaction_type === 'income' ? 'Ingreso' : 'Egreso',
                    $r->category_name,
                    number_format( $r->amount, 2, '.', ',' ),
                    $r->description,
                    $r->transaction_date,
                    $r->age_days . ' días',
                ];
            }
        }
        return [ $headers, $rows, 'pendientes-' . date( 'Y-m-d' ), 'Transacciones Pendientes' ];
    }

    // ─── E. Presupuesto vs Ejecutado ──────────────────────────────────────────

    private static function get_budget_data( array $p ): array {
        global $wpdb;
        $t  = $wpdb->prefix . 'aura_finance_transactions';
        $b  = $wpdb->prefix . 'aura_finance_budgets';
        $ct = $wpdb->prefix . 'aura_finance_categories';
        $a  = $wpdb->prefix . 'aura_areas';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$b}'" ) !== $b ) {
            return [ 'budgets' => [], 'total_budget' => 0.0, 'total_executed' => 0.0 ];
        }

        $area_sql = '';
        $area_arg = [];
        if ( ! empty( $p['area_id'] ) ) {
            $area_sql = $wpdb->prepare( 'AND bud.area_id = %d', $p['area_id'] );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT bud.id, bud.budget_amount, bud.alert_threshold, bud.start_date, bud.end_date,
                    COALESCE(a.name,'Sin área') AS area_name,
                    COALESCE(a.color,'#6B7280') AS area_color,
                    COALESCE(a.icon,'dashicons-building') AS area_icon,
                    COALESCE(c.name,'Sin categoría') AS category_name,
                    COALESCE(c.color,'#6B7280') AS color,
                    COALESCE(SUM(tx.amount), 0) AS executed
             FROM {$b} bud
             LEFT JOIN {$a} a ON bud.area_id = a.id
             LEFT JOIN {$ct} c ON bud.category_id = c.id
             LEFT JOIN {$t} tx
                ON tx.area_id        = bud.area_id
               AND tx.transaction_type = 'expense'
               AND tx.status          = 'approved'
               AND tx.transaction_date BETWEEN bud.start_date AND bud.end_date
               AND tx.deleted_at IS NULL
             WHERE bud.is_active = 1 {$area_sql}
             GROUP BY bud.id, bud.budget_amount, bud.alert_threshold, bud.start_date, bud.end_date, a.name, a.color, a.icon, c.name, c.color
             ORDER BY a.name ASC, executed DESC",
            $p['start'], $p['end']
        ) );

        foreach ( $rows as &$r ) {
            $r->budget_amount = (float) $r->budget_amount;
            $r->executed      = (float) $r->executed;
            $r->pct           = $r->budget_amount > 0 ? round( $r->executed / $r->budget_amount * 100, 1 ) : 0.0;
            $r->remaining     = max( 0.0, $r->budget_amount - $r->executed );
            $r->overrun       = $r->executed > $r->budget_amount;

            $days_total   = max( 1, ( strtotime( $r->end_date )   - strtotime( $r->start_date ) ) / DAY_IN_SECONDS );
            $days_elapsed = max( 1, min( $days_total, ( time() - strtotime( $r->start_date ) ) / DAY_IN_SECONDS ) );
            $r->projected = round( $r->executed / $days_elapsed * $days_total, 2 );
        }
        unset( $r );

        return [
            'budgets'        => $rows,
            'total_budget'   => (float) array_sum( array_column( $rows, 'budget_amount' ) ),
            'total_executed' => (float) array_sum( array_column( $rows, 'executed' ) ),
        ];
    }

    private static function budget_flat( array $p ): array {
        $d       = self::get_budget_data( $p );
        $headers = [ 'Área/Programa', 'Categoría', 'Presupuesto (USD)', 'Ejecutado (USD)', '% Ejecución', 'Restante (USD)', 'Proyectado (USD)', '¿Sobregiro?' ];
        $rows    = [];
        foreach ( $d['budgets'] as $r ) {
            $rows[] = [
                $r->area_name,
                $r->category_name,
                number_format( $r->budget_amount, 2, '.', ',' ),
                number_format( $r->executed,      2, '.', ',' ),
                $r->pct . '%',
                number_format( $r->remaining,     2, '.', ',' ),
                number_format( $r->projected,     2, '.', ',' ),
                $r->overrun ? 'SÍ' : 'No',
            ];
        }
        return [ $headers, $rows, 'presupuesto-' . date( 'Y-m-d' ), 'Presupuesto vs Ejecutado' ];
    }

    // ─── E2. Detalle de transacciones por Área ────────────────────────────────

    private static function get_budget_area_detail( array $p ): array {
        global $wpdb;
        $t  = $wpdb->prefix . 'aura_finance_transactions';
        $b  = $wpdb->prefix . 'aura_finance_budgets';
        $ct = $wpdb->prefix . 'aura_finance_categories';
        $a  = $wpdb->prefix . 'aura_areas';

        $area_id = absint( $p['area_id'] ?? 0 );
        if ( ! $area_id ) {
            return [ 'error' => 'Selecciona un área para generar este reporte.' ];
        }

        // Datos del área
        $area = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, color, icon FROM {$a} WHERE id = %d", $area_id
        ) );
        if ( ! $area ) {
            return [ 'error' => 'Área no encontrada.' ];
        }

        // Presupuestos activos del área
        $budgets_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.budget_amount, b.start_date, b.end_date,
                    COALESCE(c.name,'Sin categoría') AS category_name,
                    COALESCE(c.color,'#6B7280') AS category_color
             FROM {$b} b
             LEFT JOIN {$ct} c ON b.category_id = c.id
             WHERE b.area_id = %d AND b.is_active = 1",
            $area_id
        ) );
        $budget_by_cat = [];
        $total_budget   = 0.0;
        foreach ( $budgets_raw as $brow ) {
            $budget_by_cat[ $brow->category_name ] = (float) $brow->budget_amount;
            $total_budget += (float) $brow->budget_amount;
        }

        // Transacciones del área en el período
        $status_sql = "AND t.status = 'approved'";
        if ( ! empty( $p['status'] ) && $p['status'] !== 'all' ) {
            $status_sql = $wpdb->prepare( 'AND t.status = %s', $p['status'] );
        }

        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.transaction_type, t.amount, t.transaction_date,
                    t.description, t.status, t.payment_method, t.reference_number,
                    COALESCE(c.name,'Sin categoría') AS category_name,
                    COALESCE(c.color,'#6B7280') AS category_color,
                    u.display_name AS created_by_name
             FROM {$t} t
             LEFT JOIN {$ct} c ON t.category_id = c.id
             LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
             WHERE t.area_id = %d
               AND t.transaction_date BETWEEN %s AND %s
               {$status_sql}
               AND t.deleted_at IS NULL
             ORDER BY c.name ASC, t.transaction_date DESC",
            $area_id, $p['start'], $p['end']
        ) ) ?: [];

        // Agrupar por categoría
        $by_category = [];
        foreach ( $transactions as $tx ) {
            $cat = $tx->category_name;
            if ( ! isset( $by_category[ $cat ] ) ) {
                $by_category[ $cat ] = [
                    'name'          => $cat,
                    'color'         => $tx->category_color,
                    'budget'        => $budget_by_cat[ $cat ] ?? 0.0,
                    'total_income'  => 0.0,
                    'total_expense' => 0.0,
                    'transactions'  => [],
                ];
            }
            if ( $tx->transaction_type === 'income' ) {
                $by_category[ $cat ]['total_income'] += (float) $tx->amount;
            } else {
                $by_category[ $cat ]['total_expense'] += (float) $tx->amount;
            }
            $by_category[ $cat ]['transactions'][] = $tx;
        }

        // Calcular % ejecución por categoría
        foreach ( $by_category as &$cat_data ) {
            $bud = $cat_data['budget'];
            $exe = $cat_data['total_expense'];
            $cat_data['pct']       = $bud > 0 ? round( $exe / $bud * 100, 1 ) : 0.0;
            $cat_data['available'] = max( 0.0, $bud - $exe );
            $cat_data['overrun']   = $exe > $bud && $bud > 0;
        }
        unset( $cat_data );

        $total_income  = array_sum( array_column( $by_category, 'total_income' ) );
        $total_expense = array_sum( array_column( $by_category, 'total_expense' ) );

        return [
            'area'          => $area,
            'by_category'   => array_values( $by_category ),
            'total_income'  => $total_income,
            'total_expense' => $total_expense,
            'total_budget'  => $total_budget,
            'available'     => max( 0.0, $total_budget - $total_expense ),
            'pct'           => $total_budget > 0 ? round( $total_expense / $total_budget * 100, 1 ) : 0.0,
            'tx_count'      => count( $transactions ),
        ];
    }

    private static function budget_area_detail_flat( array $p ): array {
        $d = self::get_budget_area_detail( $p );
        if ( isset( $d['error'] ) ) {
            return [ [ 'Error' ], [ [ $d['error'] ] ], 'error', 'Error' ];
        }
        $area_name = $d['area']->name ?? 'área';
        $headers   = [ 'Categoría', 'Tipo', 'ID', 'Fecha', 'Monto (USD)', 'Descripción', 'Método de Pago', 'Referencia', 'Estado', 'Registrado por' ];
        $rows      = [];
        foreach ( $d['by_category'] as $cat ) {
            foreach ( $cat['transactions'] as $tx ) {
                $rows[] = [
                    $cat['name'],
                    $tx->transaction_type === 'income' ? 'Ingreso' : 'Egreso',
                    $tx->id,
                    $tx->transaction_date,
                    number_format( (float) $tx->amount, 2, '.', ',' ),
                    $tx->description ?? '',
                    self::translate_payment_method( $tx->payment_method ?? '' ),
                    $tx->reference_number ?? '',
                    $tx->status,
                    $tx->created_by_name ?? '',
                ];
            }
            $rows[] = [
                '— SUBTOTAL: ' . $cat['name'], '', '', '',
                number_format( $cat['total_expense'] + $cat['total_income'], 2, '.', ',' ),
                '', '', '', '', '',
            ];
        }
        $filename = 'detalle-area-' . sanitize_title( $area_name ) . '-' . date( 'Y-m-d' );
        return [ $headers, $rows, $filename, 'Detalle por Área: ' . $area_name ];
    }

    // ─── F. Auditoría ─────────────────────────────────────────────────────────

    private static function get_audit_data( array $p ): array {
        global $wpdb;
        $t  = $wpdb->prefix . 'aura_finance_transactions';
        $ct = $wpdb->prefix . 'aura_finance_categories';

        $status_sql  = $p['status'] !== 'all'
            ? $wpdb->prepare( 'AND t.status = %s', $p['status'] )
            : '';
        $creator_sql = $p['created_by'] > 0
            ? $wpdb->prepare( 'AND t.created_by = %d', $p['created_by'] )
            : '';

        // Sin permiso global → solo propias
        if ( ! self::can_view_all() ) {
            $creator_sql = $wpdb->prepare( 'AND t.created_by = %d', get_current_user_id() );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.transaction_type, t.amount, t.description, t.transaction_date,
                    t.status, t.payment_method, t.reference_number, t.receipt_file,
                    t.created_at, t.deleted_at,
                    COALESCE(c.name,'Sin categoría') AS category_name,
                    uc.display_name AS creator_name,
                    ua.display_name AS approver_name
             FROM {$t} t
             LEFT JOIN {$ct} c ON t.category_id = c.id
             LEFT JOIN {$wpdb->users} uc ON t.created_by   = uc.ID
             LEFT JOIN {$wpdb->users} ua ON t.approved_by  = ua.ID
             WHERE t.transaction_date BETWEEN %s AND %s
               {$status_sql} {$creator_sql}
             ORDER BY t.created_at DESC
             LIMIT 500",
            $p['start'], $p['end']
        ) );

        foreach ( $rows as &$r ) {
            $r->amount = (float) $r->amount;
        }
        unset( $r );

        return [ 'rows' => $rows, 'count' => count( $rows ) ];
    }

    private static function audit_flat( array $p ): array {
        $d       = self::get_audit_data( $p );
        $headers = [ 'ID', 'Tipo', 'Categoría', 'Monto (USD)', 'Estado', 'Método Pago',
                     'Referencia', 'Descripción', 'Fecha Tx', 'Creado por', 'Aprobado por',
                     'Creado el', 'Tiene comprobante', 'Eliminado' ];
        $rows = [];
        foreach ( $d['rows'] as $r ) {
            $rows[] = [
                $r->id,
                $r->transaction_type === 'income' ? 'Ingreso' : 'Egreso',
                $r->category_name,
                number_format( $r->amount, 2, '.', ',' ),
                ucfirst( $r->status ),
                self::translate_payment_method( $r->payment_method ),
                $r->reference_number ?? '-',
                $r->description,
                $r->transaction_date,
                $r->creator_name   ?? '-',
                $r->approver_name  ?? '-',
                $r->created_at,
                $r->receipt_file   ? 'Sí' : 'No',
                $r->deleted_at     ? 'Sí' : 'No',
            ];
        }
        return [ $headers, $rows, 'auditoria-' . $p['start'] . '-' . $p['end'], 'Reporte de Auditoría' ];
    }

    // ─── G. Sueldos / Pagos a Usuarios ──────────────────────────────────────────

    private static function get_user_payments_data( array $p ): array {
        global $wpdb;
        $t  = $wpdb->prefix . 'aura_finance_transactions';
        $ct = $wpdb->prefix . 'aura_finance_categories';

        $status_sql  = $p['status'] !== 'all'
            ? $wpdb->prepare( 'AND t.status = %s', $p['status'] )
            : "AND t.status = 'approved'";
        $user_filter = $p['created_by'] > 0
            ? $wpdb->prepare( 'AND t.related_user_id = %d', $p['created_by'] )
            : '';

        if ( ! self::can_view_all() ) {
            $user_filter = $wpdb->prepare( 'AND t.related_user_id = %d', get_current_user_id() );
        }

        $concept_labels = [
            'salary'   => 'Sueldo',
            'advance'  => 'Adelanto de sueldo',
            'expense'  => 'Reembolso de gastos',
            'loan'     => 'Préstamo',
            'payment'  => 'Pago',
        ];

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.transaction_type, t.amount, t.description, t.transaction_date,
                    t.status, t.payment_method, t.reference_number,
                    t.related_user_concept,
                    COALESCE(c.name,'Sin categoría') AS category_name,
                    ru.display_name AS related_user_name,
                    ru.user_email   AS related_user_email,
                    cu.display_name AS creator_name
             FROM {$t} t
             LEFT JOIN {$ct}           c  ON c.id  = t.category_id
             LEFT JOIN {$wpdb->users}  ru ON ru.ID = t.related_user_id
             LEFT JOIN {$wpdb->users}  cu ON cu.ID = t.created_by
             WHERE t.related_user_id IS NOT NULL
               AND t.transaction_date BETWEEN %s AND %s
               AND t.deleted_at IS NULL
               {$status_sql} {$user_filter}
             ORDER BY ru.display_name ASC, t.transaction_date DESC
             LIMIT 1000",
            $p['start'], $p['end']
        ) );

        foreach ( $rows as &$r ) {
            $r->amount        = (float) $r->amount;
            $r->concept_label = $concept_labels[ $r->related_user_concept ?? '' ] ?? ucfirst( $r->related_user_concept ?? 'Pago' );
        }
        unset( $r );

        $summary_map = [];
        foreach ( $rows as $r ) {
            $uid = $r->related_user_email;
            if ( ! isset( $summary_map[ $uid ] ) ) {
                $summary_map[ $uid ] = [
                    'user_name'      => $r->related_user_name ?? 'Sin nombre',
                    'user_email'     => $r->related_user_email ?? '-',
                    'total_paid'     => 0.0,
                    'total_received' => 0.0,
                    'count'          => 0,
                ];
            }
            if ( $r->transaction_type === 'expense' ) {
                $summary_map[ $uid ]['total_paid'] += $r->amount;
            } else {
                $summary_map[ $uid ]['total_received'] += $r->amount;
            }
            $summary_map[ $uid ]['count']++;
        }
        $summary = array_values( $summary_map );

        return [
            'rows'    => $rows,
            'summary' => $summary,
            'totals'  => [
                'paid'     => array_sum( array_column( $summary, 'total_paid' ) ),
                'received' => array_sum( array_column( $summary, 'total_received' ) ),
                'count'    => count( $rows ),
            ],
        ];
    }

    private static function user_payments_flat( array $p ): array {
        $d       = self::get_user_payments_data( $p );
        $headers = [
            'ID', 'Fecha', 'Usuario Relacionado', 'Email', 'Concepto',
            'Tipo (Org)', 'Categoría', 'Monto (USD)', 'Estado',
            'Método Pago', 'Referencia', 'Descripción', 'Registrado por',
        ];
        $rows = [];
        foreach ( $d['rows'] as $r ) {
            $type_label = $r->transaction_type === 'expense' ? 'Pago a usuario' : 'Cobro a usuario';
            $rows[] = [
                $r->id,
                $r->transaction_date,
                $r->related_user_name  ?? '-',
                $r->related_user_email ?? '-',
                $r->concept_label,
                $type_label,
                $r->category_name,
                number_format( $r->amount, 2, '.', ',' ),
                ucfirst( $r->status ),
                self::translate_payment_method( $r->payment_method ),
                $r->reference_number  ?? '-',
                $r->description,
                $r->creator_name      ?? '-',
            ];
        }
        return [ $headers, $rows, 'sueldos-pagos-' . $p['start'] . '-' . $p['end'], 'Sueldos / Pagos a Usuarios' ];
    }

    // ─── Cron: Reportes Programados ───────────────────────────────────────────

    public static function run_scheduled_reports(): void {
        $schedules = get_option( 'aura_scheduled_reports', [] );
        if ( empty( $schedules ) ) {
            return;
        }

        $today        = (int) date( 'N' ); // 1=Lun … 7=Dom
        $day_of_month = (int) date( 'j' );

        foreach ( $schedules as $schedule ) {
            $send = false;
            if ( $schedule['frequency'] === 'weekly'   && $today === 1 ) $send = true;
            if ( $schedule['frequency'] === 'biweekly' && in_array( $day_of_month, [ 1, 15 ], true ) ) $send = true;
            if ( $schedule['frequency'] === 'monthly'  && $day_of_month === 1 ) $send = true;
            if ( ! $send ) continue;

            $params = [
                'start'      => date( 'Y-m-01', strtotime( '-1 month' ) ),
                'end'        => date( 'Y-m-t',  strtotime( '-1 month' ) ),
                'status'     => 'approved',
                'categories' => [],
                'created_by' => 0,
            ];

            [ $headers, $rows, $filename ] = self::get_flat_data( $schedule['report_type'], $params );

            // Generar CSV en memoria
            ob_start();
            echo "\xEF\xBB\xBF";
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, $headers, ';' );
            foreach ( $rows as $row ) {
                fputcsv( $out, $row, ';' );
            }
            fclose( $out );
            $csv = ob_get_clean();

            $to = is_array( $schedule['emails'] ) ? $schedule['emails'] : [ $schedule['emails'] ];
            wp_mail(
                $to,
                '[Aura Suite] Reporte Automático: ' . ( $schedule['name'] ?? $schedule['report_type'] ),
                sprintf(
                    '<p>Estimado equipo,</p><p>Se adjunta el reporte <strong>%s</strong> generado automáticamente por <em>Aura Business Suite</em> para el período %s – %s.</p>',
                    esc_html( $schedule['name'] ?? $schedule['report_type'] ),
                    esc_html( $params['start'] ),
                    esc_html( $params['end'] )
                ),
                [ 'Content-Type: text/html; charset=UTF-8' ],
                [ [ 'name' => $filename . '.csv', 'content' => $csv, 'encoding' => 'base64', 'type' => 'text/csv' ] ]
            );
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private static function pct( float $current, float $previous ): ?float {
        if ( $previous == 0 ) return $current > 0 ? 100.0 : null;
        return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
    }
}
