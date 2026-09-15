<?php
/**
 * Reportes del Módulo de Inventario — FASE 7
 *
 * 4 tipos de reportes + exportación CSV:
 *   1. Costos por Equipo
 *   2. Historial de Mantenimientos
 *   3. Eficiencia de Mantenimientos
 *   4. Depreciación y Vida Útil
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Reports {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_inventory_report_costs',            [ __CLASS__, 'ajax_report_costs'       ] );
        add_action( 'wp_ajax_aura_inventory_report_maintenance_log',  [ __CLASS__, 'ajax_report_maintenance' ] );
        add_action( 'wp_ajax_aura_inventory_report_efficiency',       [ __CLASS__, 'ajax_report_efficiency'  ] );
        add_action( 'wp_ajax_aura_inventory_report_lifecycle',        [ __CLASS__, 'ajax_report_lifecycle'   ] );
        add_action( 'wp_ajax_aura_inventory_export_report',           [ __CLASS__, 'ajax_export_report'      ] );
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER — Página principal
    // ─────────────────────────────────────────────────────────────

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_reports' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/reports-page.php';
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 1 — Costos por Equipo
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_costs(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_reports' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }
        wp_send_json_success( [ 'rows' => self::fetch_costs_data() ] );
    }

    private static function fetch_costs_data(): array {
        global $wpdb;
        $t_eq = $wpdb->prefix . 'aura_inventory_equipment';
        $t_m  = $wpdb->prefix . 'aura_inventory_maintenance';

        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

        $where = 'WHERE e.deleted_at IS NULL';
        $args  = [];
        if ( $category ) {
            $where .= ' AND e.category = %s';
            $args[] = $category;
        }

        $sql = "
            SELECT
                e.id,
                e.name,
                e.internal_code,
                e.category,
                e.cost AS acquisition_cost,
                e.estimated_value,
                e.status,
                e.last_maintenance_date,
                e.next_maintenance_date,
                COUNT(m.id)                    AS maintenance_count,
                COALESCE(SUM(m.total_cost), 0) AS total_maintenance_cost
            FROM {$t_eq} e
            LEFT JOIN {$t_m} m ON m.equipment_id = e.id
            {$where}
            GROUP BY e.id
            ORDER BY total_maintenance_cost DESC
        ";

        $rows = ! empty( $args )
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        $data = [];
        foreach ( (array) $rows as $r ) {
            $total_invested = (float) $r->acquisition_cost + (float) $r->total_maintenance_cost;
            $pct = $r->acquisition_cost > 0
                ? round( ( $r->total_maintenance_cost / $r->acquisition_cost ) * 100, 1 )
                : 0;
            $avg = $r->maintenance_count > 0
                ? round( $r->total_maintenance_cost / $r->maintenance_count, 2 )
                : 0;

            // Indicador de reemplazo: mantenimientos > 60% del costo original
            $indicator = 'ok';
            if ( $r->acquisition_cost > 0 && $r->total_maintenance_cost > $r->acquisition_cost * 0.6 ) {
                $indicator = 'replace';
            } elseif ( $r->acquisition_cost > 0 && $r->total_maintenance_cost > $r->acquisition_cost * 0.3 ) {
                $indicator = 'review';
            }

            $data[] = [
                'id'                  => (int) $r->id,
                'name'                => $r->name,
                'internal_code'       => $r->internal_code,
                'category'            => $r->category,
                'acquisition_cost'    => (float) $r->acquisition_cost,
                'estimated_value'     => (float) $r->estimated_value,
                'status'              => $r->status,
                'maintenance_count'   => (int) $r->maintenance_count,
                'total_maintenance'   => (float) $r->total_maintenance_cost,
                'total_invested'      => round( $total_invested, 2 ),
                'maintenance_pct'     => $pct,
                'average_maintenance' => $avg,
                'last_maintenance'    => $r->last_maintenance_date,
                'next_maintenance'    => $r->next_maintenance_date,
                'indicator'           => $indicator,
            ];
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 2 — Historial de Mantenimientos
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_maintenance(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_reports' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }
        wp_send_json_success( [ 'rows' => self::fetch_maintenance_data() ] );
    }

    private static function fetch_maintenance_data(): array {
        global $wpdb;
        $t_eq = $wpdb->prefix . 'aura_inventory_equipment';
        $t_m  = $wpdb->prefix . 'aura_inventory_maintenance';

        $date_from   = isset( $_POST['date_from'] )    ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) )    : '';
        $date_to     = isset( $_POST['date_to'] )      ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) )      : '';
        $type_filter = isset( $_POST['maint_type'] )   ? sanitize_text_field( wp_unslash( $_POST['maint_type'] ) )   : '';
        $performed   = isset( $_POST['performed_by'] ) ? sanitize_text_field( wp_unslash( $_POST['performed_by'] ) ) : '';

        $where = 'WHERE e.deleted_at IS NULL';
        $args  = [];

        if ( $date_from )   { $where .= ' AND m.maintenance_date >= %s'; $args[] = $date_from; }
        if ( $date_to )     { $where .= ' AND m.maintenance_date <= %s'; $args[] = $date_to; }
        if ( $type_filter ) { $where .= ' AND m.type = %s';              $args[] = $type_filter; }
        if ( $performed )   { $where .= ' AND m.performed_by = %s';      $args[] = $performed; }

        $sql = "
            SELECT
                m.maintenance_date,
                e.name          AS equipment_name,
                e.internal_code,
                m.type,
                m.description,
                m.parts_replaced,
                m.parts_cost,
                m.labor_cost,
                m.total_cost,
                m.performed_by,
                m.workshop_name,
                m.invoice_number,
                m.post_status,
                m.observations
            FROM {$t_m} m
            INNER JOIN {$t_eq} e ON e.id = m.equipment_id
            {$where}
            ORDER BY m.maintenance_date DESC
            LIMIT 500
        ";

        $rows = ! empty( $args )
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A )
            : $wpdb->get_results( $sql, ARRAY_A );

        return (array) $rows;
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 3 — Eficiencia de Mantenimientos
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_efficiency(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_reports' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }
        wp_send_json_success( self::fetch_efficiency_data() );
    }

    private static function fetch_efficiency_data(): array {
        global $wpdb;
        $t_eq = $wpdb->prefix . 'aura_inventory_equipment';
        $t_m  = $wpdb->prefix . 'aura_inventory_maintenance';

        $date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : date( 'Y-01-01' );
        $date_to   = isset( $_POST['date_to'] )   ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) )   : date( 'Y-m-d' );

        $totals = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*)                                          AS total,
                SUM(type='preventive')                           AS preventive,
                SUM(type='corrective' OR type='major_repair')    AS corrective,
                SUM(total_cost)                                  AS total_cost,
                SUM(parts_cost)                                  AS parts_cost,
                SUM(labor_cost)                                  AS labor_cost
             FROM {$t_m}
             WHERE maintenance_date BETWEEN %s AND %s",
            $date_from, $date_to
        ) );

        $overdue = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_eq}
             WHERE deleted_at IS NULL
               AND requires_maintenance = 1
               AND next_maintenance_date < CURDATE()"
        );

        $top_corrective = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.name, e.internal_code, COUNT(m.id) AS qty, SUM(m.total_cost) AS cost
             FROM {$t_m} m
             INNER JOIN {$t_eq} e ON e.id = m.equipment_id
             WHERE m.maintenance_date BETWEEN %s AND %s
               AND (m.type = 'corrective' OR m.type = 'major_repair')
               AND e.deleted_at IS NULL
             GROUP BY m.equipment_id
             ORDER BY qty DESC
             LIMIT 5",
            $date_from, $date_to
        ) );

        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.name, e.internal_code, e.next_maintenance_date, e.category
             FROM {$t_eq} e
             WHERE e.deleted_at IS NULL
               AND e.requires_maintenance = 1
               AND e.next_maintenance_date < %s
               AND e.id NOT IN (
                   SELECT DISTINCT equipment_id FROM {$t_m}
                   WHERE maintenance_date BETWEEN %s AND %s
               )
             ORDER BY e.next_maintenance_date ASC
             LIMIT 50",
            $date_to, $date_from, $date_to
        ) );

        $total    = (int) ( $totals->total    ?? 0 );
        $prev_pct = $total > 0 ? round( ( $totals->preventive / $total ) * 100, 1 ) : 0;
        $corr_pct = $total > 0 ? round( ( $totals->corrective  / $total ) * 100, 1 ) : 0;

        return [
            'period'         => [ 'from' => $date_from, 'to' => $date_to ],
            'totals'         => [
                'count'      => $total,
                'preventive' => (int) ( $totals->preventive ?? 0 ),
                'corrective' => (int) ( $totals->corrective ?? 0 ),
                'prev_pct'   => $prev_pct,
                'corr_pct'   => $corr_pct,
                'total_cost' => (float) ( $totals->total_cost ?? 0 ),
                'parts_cost' => (float) ( $totals->parts_cost ?? 0 ),
                'labor_cost' => (float) ( $totals->labor_cost ?? 0 ),
            ],
            'overdue_count'  => $overdue,
            'top_corrective' => array_map( static function ( $r ) {
                return [
                    'name'          => $r->name,
                    'internal_code' => $r->internal_code,
                    'qty'           => (int) $r->qty,
                    'cost'          => (float) $r->cost,
                ];
            }, (array) $top_corrective ),
            'pending_list'   => array_map( static function ( $r ) {
                return [
                    'name'                  => $r->name,
                    'category'              => $r->category,
                    'next_maintenance_date' => $r->next_maintenance_date,
                ];
            }, (array) $pending ),
            'goal_met'       => $prev_pct >= 70,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // REPORTE 4 — Depreciación y Vida Útil
    // ─────────────────────────────────────────────────────────────

    public static function ajax_report_lifecycle(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_reports' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }
        wp_send_json_success( [ 'rows' => self::fetch_lifecycle_data() ] );
    }

    private static function fetch_lifecycle_data(): array {
        global $wpdb;
        $t_eq = $wpdb->prefix . 'aura_inventory_equipment';
        $t_m  = $wpdb->prefix . 'aura_inventory_maintenance';

        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

        $where = 'WHERE e.deleted_at IS NULL';
        $args  = [];
        if ( $category ) {
            $where .= ' AND e.category = %s';
            $args[] = $category;
        }

        $sql = "
            SELECT
                e.id,
                e.name,
                e.internal_code,
                e.category,
                e.acquisition_date,
                e.cost,
                e.estimated_value,
                e.status,
                COALESCE(SUM(m.total_cost), 0) AS total_maintenance_cost
            FROM {$t_eq} e
            LEFT JOIN {$t_m} m ON m.equipment_id = e.id
            {$where}
            GROUP BY e.id
            ORDER BY e.acquisition_date ASC
        ";

        $rows = ! empty( $args )
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        $today = new DateTime();
        $data  = [];

        foreach ( (array) $rows as $r ) {
            $age_str = '—';
            if ( $r->acquisition_date ) {
                $acq     = new DateTime( $r->acquisition_date );
                $diff    = $today->diff( $acq );
                $age_str = $diff->y > 0
                    ? sprintf( '%d año%s %d mes%s', $diff->y, $diff->y > 1 ? 's' : '', $diff->m, $diff->m > 1 ? 'es' : '' )
                    : sprintf( '%d mes%s', $diff->m, $diff->m > 1 ? 'es' : '' );
            }

            $total_invested = (float) $r->cost + (float) $r->total_maintenance_cost;

            $indicator = 'ok';
            if ( $r->cost > 0 && $r->total_maintenance_cost > $r->cost * 0.6 ) {
                $indicator = 'replace';
            } elseif ( $r->cost > 0 && $r->total_maintenance_cost > $r->cost * 0.3 ) {
                $indicator = 'review';
            }

            $data[] = [
                'id'                  => (int) $r->id,
                'name'                => $r->name,
                'internal_code'       => $r->internal_code,
                'category'            => $r->category,
                'acquisition_date'    => $r->acquisition_date,
                'age'                 => $age_str,
                'original_cost'       => (float) $r->cost,
                'total_maintenance'   => (float) $r->total_maintenance_cost,
                'total_invested'      => round( $total_invested, 2 ),
                'estimated_value'     => (float) $r->estimated_value,
                'status'              => $r->status,
                'indicator'           => $indicator,
            ];
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORTAR — CSV / XLSX
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_report(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_reports' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $report = isset( $_POST['report'] ) ? sanitize_text_field( wp_unslash( $_POST['report'] ) ) : '';
        $format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';

        // Obtener datos directamente (sin ob_start que es incompatible con wp_die)
        switch ( $report ) {
            case 'costs':
                $rows = self::fetch_costs_data();
                break;
            case 'maintenance':
                $rows = self::fetch_maintenance_data();
                break;
            case 'efficiency':
                $rows = self::build_efficiency_flat_rows( self::fetch_efficiency_data() );
                break;
            case 'lifecycle':
                $rows = self::fetch_lifecycle_data();
                break;
            default:
                wp_send_json_error( [ 'message' => 'Reporte no válido.' ] );
                return;
        }

        if ( empty( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin datos para exportar.', 'aura-suite' ) ] );
            return;
        }

        $filename_base = 'reporte-inventario-' . $report . '-' . date( 'Y-m-d' );

        if ( $format === 'csv' ) {
            $fp = fopen( 'php://temp', 'rw' );
            fputcsv( $fp, array_keys( (array) $rows[0] ) );
            foreach ( $rows as $row ) {
                fputcsv( $fp, array_values( (array) $row ) );
            }
            rewind( $fp );
            $file_data = stream_get_contents( $fp );
            fclose( $fp );

            wp_send_json_success( [
                'filename' => $filename_base . '.csv',
                'data'     => base64_encode( $file_data ),
                'mime'     => 'text/csv',
            ] );

        } elseif ( $format === 'xlsx' ) {
            require_once AURA_PLUGIN_DIR . 'vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle( 'Reporte' );

            // Fila de cabeceras con estilo
            $headers = array_keys( (array) $rows[0] );
            foreach ( $headers as $c => $h ) {
                $cell = $sheet->getCellByColumnAndRow( $c + 1, 1 );
                $cell->setValue( $h );
                $cell->getStyle()->getFont()->setBold( true );
                $cell->getStyle()->getFill()
                    ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                    ->getStartColor()->setARGB( 'FF2271B1' );
                $cell->getStyle()->getFont()->getColor()->setARGB( 'FFFFFFFF' );
            }

            // Filas de datos
            foreach ( $rows as $r_idx => $row ) {
                foreach ( array_values( (array) $row ) as $c_idx => $val ) {
                    $sheet->getCellByColumnAndRow( $c_idx + 1, $r_idx + 2 )->setValue( $val );
                }
            }

            // Auto-ancho y fila fija
            foreach ( range( 1, count( $headers ) ) as $c ) {
                $sheet->getColumnDimensionByColumn( $c )->setAutoSize( true );
            }
            $sheet->freezePane( 'A2' );

            ob_start();
            ( new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet ) )->save( 'php://output' );
            $file_data = ob_get_clean();

            wp_send_json_success( [
                'filename' => $filename_base . '.xlsx',
                'data'     => base64_encode( $file_data ),
                'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ] );

        } else {
            wp_send_json_error( [ 'message' => 'Formato no soportado.' ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER — Eficiencia → filas planas para CSV/XLSX
    // ─────────────────────────────────────────────────────────────

    private static function build_efficiency_flat_rows( array $data ): array {
        $rows = [];
        $t    = $data['totals'] ?? [];

        $rows[] = [ 'Sección' => 'RESUMEN',              'Descripción' => 'Total mantenimientos',   'Valor' => $t['count']     ?? 0 ];
        $rows[] = [ 'Sección' => '',                     'Descripción' => '% Preventivos',          'Valor' => ( $t['prev_pct'] ?? 0 ) . '%' ];
        $rows[] = [ 'Sección' => '',                     'Descripción' => '% Correctivos',          'Valor' => ( $t['corr_pct'] ?? 0 ) . '%' ];
        $rows[] = [ 'Sección' => '',                     'Descripción' => 'Costo total',            'Valor' => number_format( (float) ( $t['total_cost'] ?? 0 ), 2 ) ];
        $rows[] = [ 'Sección' => '',                     'Descripción' => 'Vencidos sin registrar', 'Valor' => $data['overdue_count'] ?? 0 ];
        $rows[] = [ 'Sección' => '',                     'Descripción' => 'Meta ≥70% preventivos',  'Valor' => ! empty( $data['goal_met'] ) ? 'Cumplida' : 'No cumplida' ];
        $rows[] = [ 'Sección' => '',                     'Descripción' => '',                       'Valor' => '' ];

        $rows[] = [ 'Sección' => 'TOP 5 CORRECTIVOS',   'Descripción' => 'Equipo',                 'Valor' => 'Cant. | Costo' ];
        foreach ( $data['top_corrective'] ?? [] as $r ) {
            $r      = (array) $r;
            $name   = $r['name']          ?? '';
            $code   = $r['internal_code'] ?? '';
            $rows[] = [
                'Sección'     => '',
                'Descripción' => $name . ( $code ? ' (' . $code . ')' : '' ),
                'Valor'       => ( $r['qty'] ?? 0 ) . ' | ' . number_format( (float) ( $r['cost'] ?? 0 ), 2 ),
            ];
        }

        $rows[] = [ 'Sección' => '',                     'Descripción' => '',       'Valor' => '' ];
        $rows[] = [ 'Sección' => 'MANTENIMIENTO VENCIDO','Descripción' => 'Equipo', 'Valor' => 'Fecha programada' ];
        foreach ( $data['pending_list'] ?? [] as $r ) {
            $r      = (array) $r;
            $rows[] = [
                'Sección'     => '',
                'Descripción' => $r['name'] ?? '',
                'Valor'       => $r['next_maintenance_date'] ?? '',
            ];
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER — Categorías disponibles
    // ─────────────────────────────────────────────────────────────

    public static function get_categories(): array {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT DISTINCT category FROM {$wpdb->prefix}aura_inventory_equipment
             WHERE deleted_at IS NULL AND category IS NOT NULL AND category != ''
             ORDER BY category ASC"
        );
        return (array) $rows;
    }
}
