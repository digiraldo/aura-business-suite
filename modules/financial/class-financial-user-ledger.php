<?php
/**
 * Libro Mayor por Usuario (Fase 6, Item 6.3)
 *
 * Proporciona una vista completa de todas las transacciones vinculadas a un
 * usuario específico, con balance acumulativo corriente, filtros por fecha y
 * concepto, estadísticas avanzadas, gráficos y exportaciones profesionales
 * en Excel (.xlsx) y CSV 100% en español sin IDs técnicos.
 *
 * Capability requerida: aura_finance_user_ledger
 * Slug de página: aura-user-ledger
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_User_Ledger {

    // -------------------------------------------------------------------------
    // Constantes
    // -------------------------------------------------------------------------

    const PER_PAGE = 50;

    // -------------------------------------------------------------------------
    // Inicialización
    // -------------------------------------------------------------------------

    public static function init(): void {
        // AJAX: exportaciones del Libro Mayor
        add_action( 'wp_ajax_aura_export_ledger_csv',   [ __CLASS__, 'ajax_export_ledger_csv' ] );
        add_action( 'wp_ajax_aura_export_ledger_excel', [ __CLASS__, 'ajax_export_ledger_excel' ] );

        // AJAX: exportaciones de Estadísticas
        add_action( 'wp_ajax_aura_export_ledger_stats_csv',   [ __CLASS__, 'ajax_export_ledger_stats_csv' ] );
        add_action( 'wp_ajax_aura_export_ledger_stats_excel', [ __CLASS__, 'ajax_export_ledger_stats_excel' ] );
    }

    // -------------------------------------------------------------------------
    // Renderizado de la página
    // -------------------------------------------------------------------------

    /**
     * Punto de entrada para la página admin — verifica capacidad e incluye template.
     */
    public static function render(): void {
        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para acceder al Libro Mayor.', 'aura-suite' ) );
        }

        include AURA_PLUGIN_DIR . 'templates/financial/user-ledger.php';
    }

    // -------------------------------------------------------------------------
    // Lógica de datos
    // -------------------------------------------------------------------------

    /**
     * Obtener filas del libro mayor para un usuario, ordenadas cronológicamente
     * (ASC) para poder calcular el balance acumulativo en PHP.
     *
     * @param int   $user_id   ID del usuario relacionado.
     * @param array $filters   Claves: date_from, date_to, concept, show_all (bool), paged.
     * @return array           Objetos de la BD. Balance acumulativo añadido como ->running_balance.
     */
    public static function get_ledger_rows( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Cláusulas WHERE
        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        // Por defecto solo aprobadas; toggle "show_all" muestra todas
        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // Paginación
        $paged  = max( 1, (int) ( $filters['paged'] ?? 1 ) );
        $offset = ( $paged - 1 ) * self::PER_PAGE;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT id, transaction_type, amount, description, transaction_date,
                    status, related_user_concept, recipient_payer, reference_number,
                    created_by
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC, id ASC
             LIMIT " . self::PER_PAGE . " OFFSET {$offset}"
        );

        if ( empty( $rows ) ) {
            return [];
        }

        // -----------------------------------------------------------------------
        // Calcular balance acumulativo corriente.
        // -----------------------------------------------------------------------
        $prior_balance = 0.0;
        if ( $paged > 1 ) {
            $prior_balance = self::get_balance_before_page( $user_id, $filters, $paged );
        }

        $running = $prior_balance;
        foreach ( $rows as $row ) {
            $amount = (float) $row->amount;
            // Perspectiva usuario: egreso org → usuario = ingreso usuario
            if ( $row->transaction_type === 'expense' ) {
                $running += $amount;
            } else {
                $running -= $amount;
            }
            $row->running_balance = $running;
        }

        return $rows;
    }

    /**
     * Obtener el balance acumulado de todas las filas que preceden a la página actual.
     */
    private static function get_balance_before_page( int $user_id, array $filters, int $paged ): float {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }
        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        $prior_limit = ( $paged - 1 ) * self::PER_PAGE;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $prior_rows = $wpdb->get_results(
            "SELECT transaction_type, amount
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC, id ASC
             LIMIT {$prior_limit} OFFSET 0"
        );

        $balance = 0.0;
        foreach ( $prior_rows as $r ) {
            if ( $r->transaction_type === 'expense' ) {
                $balance += (float) $r->amount;
            } else {
                $balance -= (float) $r->amount;
            }
        }

        return $balance;
    }

    /**
     * Contar el total de filas del libro mayor para paginación.
     */
    public static function count_ledger_rows( int $user_id, array $filters = [] ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}"
        );
    }

    /**
     * Obtener totales del período: total ingresos, total egresos, balance neto.
     */
    public static function get_ledger_totals( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT transaction_type, SUM(amount) AS total
             FROM {$table}
             WHERE {$where}
             GROUP BY transaction_type"
        );

        $income  = 0.0;
        $expense = 0.0;

        foreach ( $rows as $row ) {
            if ( $row->transaction_type === 'expense' ) {
                $income = (float) $row->total;
            } else {
                $expense = (float) $row->total;
            }
        }

        return [
            'income'  => $income,
            'expense' => $expense,
            'net'     => $income - $expense,
        ];
    }

    /**
     * Generar estadísticas contables detalladas para el usuario.
     */
    public static function get_ledger_statistics( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        $concepts_map = self::get_concepts_labels();

        $rows = $wpdb->get_results(
            "SELECT id, transaction_type, amount, description, transaction_date,
                    status, related_user_concept
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC"
        );

        $total_income    = 0.0;
        $total_expense   = 0.0;
        $max_income      = 0.0;
        $max_expense     = 0.0;
        $approved_count  = 0;
        $pending_count   = 0;
        $rejected_count  = 0;
        $concepts_data   = [];
        $monthly_data    = [];

        $meses_es = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
        ];

        foreach ( $rows as $r ) {
            $amt       = (float) $r->amount;
            $is_income = $r->transaction_type === 'expense'; // Perspectiva usuario

            if ( $is_income ) {
                $total_income += $amt;
                if ( $amt > $max_income ) $max_income = $amt;
            } else {
                $total_expense += $amt;
                if ( $amt > $max_expense ) $max_expense = $amt;
            }

            if ( $r->status === 'approved' ) $approved_count++;
            elseif ( $r->status === 'pending' ) $pending_count++;
            elseif ( $r->status === 'rejected' ) $rejected_count++;

            $c_key = ! empty( $r->related_user_concept ) ? $r->related_user_concept : 'general';
            if ( ! isset( $concepts_data[ $c_key ] ) ) {
                $concepts_data[ $c_key ] = [
                    'concept_key'  => $c_key,
                    'concept_name' => $concepts_map[ $c_key ] ?? ucfirst( $c_key ),
                    'type'         => $is_income ? 'Ingreso' : 'Egreso',
                    'total'        => 0.0,
                    'count'        => 0,
                ];
            }
            $concepts_data[ $c_key ]['total'] += $amt;
            $concepts_data[ $c_key ]['count']++;

            $ym = substr( $r->transaction_date, 0, 7 );
            if ( ! isset( $monthly_data[ $ym ] ) ) {
                $parts = explode( '-', $ym );
                $y = $parts[0] ?? '';
                $m = $parts[1] ?? '01';
                $monthly_data[ $ym ] = [
                    'year_month' => $ym,
                    'label'      => ( $meses_es[ $m ] ?? $m ) . ' ' . $y,
                    'income'     => 0.0,
                    'expense'    => 0.0,
                    'net'        => 0.0,
                    'count'      => 0,
                ];
            }
            if ( $is_income ) {
                $monthly_data[ $ym ]['income'] += $amt;
            } else {
                $monthly_data[ $ym ]['expense'] += $amt;
            }
            $monthly_data[ $ym ]['net'] = $monthly_data[ $ym ]['income'] - $monthly_data[ $ym ]['expense'];
            $monthly_data[ $ym ]['count']++;
        }

        $total_count   = count( $rows );
        $grand_total   = $total_income + $total_expense;
        $avg_tx        = $total_count > 0 ? ( $grand_total / $total_count ) : 0.0;
        $approval_rate = $total_count > 0 ? round( ( $approved_count / $total_count ) * 100, 1 ) : 100.0;

        foreach ( $concepts_data as &$cd ) {
            $cd['pct'] = $grand_total > 0 ? round( ( $cd['total'] / $grand_total ) * 100, 1 ) : 0.0;
        }
        unset( $cd );

        usort( $concepts_data, function( $a, $b ) {
            return $b['total'] <=> $a['total'];
        } );

        ksort( $monthly_data );

        return [
            'metrics' => [
                'total_income'    => $total_income,
                'total_expense'   => $total_expense,
                'net_balance'     => $total_income - $total_expense,
                'total_count'     => $total_count,
                'avg_transaction' => $avg_tx,
                'max_income'      => $max_income,
                'max_expense'     => $max_expense,
                'approved_count'  => $approved_count,
                'pending_count'   => $pending_count,
                'rejected_count'  => $rejected_count,
                'approval_rate'   => $approval_rate,
            ],
            'concepts' => array_values( $concepts_data ),
            'monthly'  => array_values( $monthly_data ),
        ];
    }

    // -------------------------------------------------------------------------
    // AJAX: Exportar CSV del Libro Mayor (100% Español)
    // -------------------------------------------------------------------------

    public static function ajax_export_ledger_csv(): void {
        check_ajax_referer( 'aura_transaction_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'Sin permisos para exportar.', 'aura-suite' ) );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_die( __( 'Usuario requerido.', 'aura-suite' ) );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'show_all'  => ! empty( $_POST['show_all'] ),
        ];

        $all_rows = self::get_all_ledger_rows_for_csv( $user_id, $filters );
        $user_obj = get_userdata( $user_id );
        $currency = get_option( 'aura_currency_symbol', '$' );
        $concepts = self::get_concepts_labels();

        $status_map = [
            'approved' => 'Aprobado',
            'pending'  => 'Pendiente',
            'rejected' => 'Rechazado',
        ];

        $user_name_slug = sanitize_file_name( $user_obj ? ( $user_obj->user_login ?: $user_obj->display_name ) : 'usuario' );
        $filename = 'libro-mayor-' . $user_name_slug . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $out = fopen( 'php://output', 'w' );

        fputcsv( $out, [
            'Fecha',
            'Descripción del Movimiento',
            'Número de Referencia',
            'Concepto Contable',
            'Tipo de Movimiento',
            'Ingreso Percibido (' . $currency . ')',
            'Egreso o Aporte (' . $currency . ')',
            'Balance Acumulado (' . $currency . ')',
            'Estado de Aprobación',
        ], ';' );

        foreach ( $all_rows as $row ) {
            $is_income        = $row->transaction_type === 'expense';
            $tipo_movimiento  = $is_income ? 'Ingreso percibido' : 'Egreso / Aporte a la organización';
            $income_col       = $is_income  ? number_format( (float) $row->amount, 2, '.', '' ) : '';
            $expense_col      = ! $is_income ? number_format( (float) $row->amount, 2, '.', '' ) : '';
            $balance_col      = number_format( (float) $row->running_balance, 2, '.', '' );
            $estado_es        = $status_map[ $row->status ] ?? ucfirst( $row->status );
            $referencia       = ! empty( $row->reference_number ) ? $row->reference_number : '—';

            fputcsv( $out, [
                $row->transaction_date,
                $row->description,
                $referencia,
                $concepts[ $row->related_user_concept ] ?? ( ! empty( $row->related_user_concept ) ? $row->related_user_concept : 'General' ),
                $tipo_movimiento,
                $income_col,
                $expense_col,
                $balance_col,
                $estado_es,
            ], ';' );
        }

        fclose( $out );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: Exportar Excel (.xlsx) del Libro Mayor (100% Español)
    // -------------------------------------------------------------------------

    public static function ajax_export_ledger_excel(): void {
        check_ajax_referer( 'aura_transaction_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'Sin permisos para exportar.', 'aura-suite' ) );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_die( __( 'Usuario requerido.', 'aura-suite' ) );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'show_all'  => ! empty( $_POST['show_all'] ),
        ];

        $all_rows = self::get_all_ledger_rows_for_csv( $user_id, $filters );
        $user_obj = get_userdata( $user_id );
        $currency = get_option( 'aura_currency_symbol', '$' );
        $concepts = self::get_concepts_labels();

        $status_map = [
            'approved' => 'Aprobado',
            'pending'  => 'Pendiente',
            'rejected' => 'Rechazado',
        ];

        $user_name_slug = sanitize_file_name( $user_obj ? ( $user_obj->user_login ?: $user_obj->display_name ) : 'usuario' );
        $filename = 'libro-mayor-' . $user_name_slug . '-' . date( 'Y-m-d' ) . '.xlsx';

        // Cargar PhpSpreadsheet
        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
            if ( file_exists( $autoload ) ) {
                require_once $autoload;
            }
        }

        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle( 'Libro Mayor' );

            // Título Superior
            $sheet->setCellValue( 'A1', 'LIBRO MAYOR PERSONAL - AURA BUSINESS SUITE' );
            $sheet->mergeCells( 'A1:I1' );
            $sheet->getStyle( 'A1' )->getFont()->setBold( true )->setSize( 14 )->getColor()->setRGB( 'FFFFFF' );
            $sheet->getStyle( 'A1' )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( '0F172A' );
            $sheet->getStyle( 'A1' )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );

            // Meta Info
            $sheet->setCellValue( 'A2', 'Colaborador: ' . ( $user_obj ? $user_obj->display_name . ' (@' . $user_obj->user_login . ')' : 'N/A' ) . ' | Período: ' . ( $filters['date_from'] ?: 'Inicio' ) . ' al ' . ( $filters['date_to'] ?: 'Hoy' ) );
            $sheet->mergeCells( 'A2:I2' );
            $sheet->getStyle( 'A2' )->getFont()->setItalic( true )->setSize( 10 )->getColor()->setRGB( '64748B' );

            // Encabezados
            $headers = [
                'Fecha', 'Descripción', 'Referencia', 'Concepto Contable', 'Tipo de Movimiento',
                'Ingreso (' . $currency . ')', 'Egreso (' . $currency . ')', 'Balance (' . $currency . ')', 'Estado'
            ];
            $col_idx = 1;
            foreach ( $headers as $h ) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx ) . '4';
                $sheet->setCellValue( $cell, $h );
                $sheet->getStyle( $cell )->getFont()->setBold( true )->getColor()->setRGB( 'FFFFFF' );
                $sheet->getStyle( $cell )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( '2563EB' );
                $sheet->getStyle( $cell )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );
                $col_idx++;
            }

            // Filas
            $row_idx = 5;
            $tot_inc = 0.0;
            $tot_exp = 0.0;

            foreach ( $all_rows as $r ) {
                $is_income  = ( $r->transaction_type === 'expense' );
                $amt        = (float) $r->amount;
                $inc_val    = $is_income ? $amt : 0;
                $exp_val    = ! $is_income ? $amt : 0;
                $tot_inc   += $inc_val;
                $tot_exp   += $exp_val;

                $sheet->setCellValue( 'A' . $row_idx, $r->transaction_date );
                $sheet->setCellValue( 'B' . $row_idx, $r->description );
                $sheet->setCellValue( 'C' . $row_idx, ! empty( $r->reference_number ) ? '#' . $r->reference_number : '—' );
                $sheet->setCellValue( 'D' . $row_idx, $concepts[ $r->related_user_concept ] ?? ( $r->related_user_concept ?: 'General' ) );
                $sheet->setCellValue( 'E' . $row_idx, $is_income ? 'Ingreso percibido' : 'Egreso / Aporte' );
                
                $sheet->setCellValue( 'F' . $row_idx, $inc_val ?: '' );
                $sheet->setCellValue( 'G' . $row_idx, $exp_val ?: '' );
                $sheet->setCellValue( 'H' . $row_idx, (float) $r->running_balance );
                $sheet->setCellValue( 'I' . $row_idx, $status_map[ $r->status ] ?? ucfirst( $r->status ) );

                $sheet->getStyle( 'F' . $row_idx . ':H' . $row_idx )->getNumberFormat()->setFormatCode( '#,##0.00' );
                $sheet->getStyle( 'A' . $row_idx )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );
                $sheet->getStyle( 'C' . $row_idx )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );
                $sheet->getStyle( 'I' . $row_idx )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );

                if ( $row_idx % 2 === 0 ) {
                    $sheet->getStyle( 'A' . $row_idx . ':I' . $row_idx )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( 'F8FAFC' );
                }

                $row_idx++;
            }

            // Fila de Totales
            $sheet->setCellValue( 'A' . $row_idx, 'TOTALES DEL PERÍODO' );
            $sheet->mergeCells( 'A' . $row_idx . ':E' . $row_idx );
            $sheet->setCellValue( 'F' . $row_idx, $tot_inc );
            $sheet->setCellValue( 'G' . $row_idx, $tot_exp );
            $sheet->setCellValue( 'H' . $row_idx, $tot_inc - $tot_exp );
            $sheet->getStyle( 'A' . $row_idx . ':I' . $row_idx )->getFont()->setBold( true );
            $sheet->getStyle( 'A' . $row_idx . ':I' . $row_idx )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( 'E2E8F0' );
            $sheet->getStyle( 'F' . $row_idx . ':H' . $row_idx )->getNumberFormat()->setFormatCode( '#,##0.00' );

            foreach ( range( 1, 9 ) as $col ) {
                $sheet->getColumnDimension( \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col ) )->setAutoSize( true );
            }

            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Cache-Control: max-age=0' );

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
            $writer->save( 'php://output' );
            exit;
        }

        // Fallback a CSV si PhpSpreadsheet no está instalado
        self::ajax_export_ledger_csv();
    }

    // -------------------------------------------------------------------------
    // AJAX: Exportar Estadísticas CSV (100% Español)
    // -------------------------------------------------------------------------

    public static function ajax_export_ledger_stats_csv(): void {
        check_ajax_referer( 'aura_transaction_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'Sin permisos para exportar estadísticas.', 'aura-suite' ) );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_die( __( 'Usuario requerido.', 'aura-suite' ) );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'show_all'  => ! empty( $_POST['show_all'] ),
        ];

        $stats    = self::get_ledger_statistics( $user_id, $filters );
        $user_obj = get_userdata( $user_id );
        $currency = get_option( 'aura_currency_symbol', '$' );

        $user_name_slug = sanitize_file_name( $user_obj ? ( $user_obj->user_login ?: $user_obj->display_name ) : 'usuario' );
        $filename = 'estadisticas-libro-mayor-' . $user_name_slug . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF"; // BOM UTF-8

        $out = fopen( 'php://output', 'w' );
        $m = $stats['metrics'];

        fputcsv( $out, [ 'REPORTE ESTADÍSTICO DE LIBRO MAYOR PERSONAL - AURA BUSINESS SUITE' ], ';' );
        fputcsv( $out, [ 'Colaborador / Usuario:', ( $user_obj ? $user_obj->display_name . ' (@' . $user_obj->user_login . ')' : 'N/A' ) ], ';' );
        fputcsv( $out, [ 'Correo Electrónico:', ( $user_obj ? $user_obj->user_email : 'N/A' ) ], ';' );
        fputcsv( $out, [ 'Fecha de Generación:', date_i18n( 'd/m/Y H:i:s' ) ], ';' );
        fputcsv( $out, [ 'Rango de Consulta:', ( ! empty( $filters['date_from'] ) ? $filters['date_from'] : 'Inicio' ) . ' hasta ' . ( ! empty( $filters['date_to'] ) ? $filters['date_to'] : 'Hoy' ) ], ';' );
        fputcsv( $out, [], ';' );

        fputcsv( $out, [ 'INDICADOR FINANCIERO', 'VALOR' ], ';' );
        fputcsv( $out, [ 'Total Ingresos Percibidos (' . $currency . ')', number_format( $m['total_income'], 2, '.', '' ) ], ';' );
        fputcsv( $out, [ 'Total Egresos o Aportes (' . $currency . ')', number_format( $m['total_expense'], 2, '.', '' ) ], ';' );
        fputcsv( $out, [ 'Balance Neto Corriente (' . $currency . ')', number_format( $m['net_balance'], 2, '.', '' ) ], ';' );
        fputcsv( $out, [ 'Total de Operaciones Registradas', $m['total_count'] ], ';' );
        fputcsv( $out, [ 'Monto Promedio por Operación (' . $currency . ')', number_format( $m['avg_transaction'], 2, '.', '' ) ], ';' );
        fputcsv( $out, [ 'Mayor Ingreso Registrado (' . $currency . ')', number_format( $m['max_income'], 2, '.', '' ) ], ';' );
        fputcsv( $out, [ 'Mayor Egreso Registrado (' . $currency . ')', number_format( $m['max_expense'], 2, '.', '' ) ], ';' );
        fputcsv( $out, [ 'Tasa de Aprobación Contable (%)', $m['approval_rate'] . '%' ], ';' );
        fputcsv( $out, [ 'Operaciones Aprobadas', $m['approved_count'] ], ';' );
        fputcsv( $out, [ 'Operaciones Pendientes', $m['pending_count'] ], ';' );
        fputcsv( $out, [ 'Operaciones Rechazadas', $m['rejected_count'] ], ';' );
        fputcsv( $out, [], ';' );

        fputcsv( $out, [ 'DESGLOSE POR CONCEPTO CONTABLE' ], ';' );
        fputcsv( $out, [ 'Concepto Contable', 'Tipo de Operación', 'Monto Total (' . $currency . ')', 'Cantidad de Movimientos', 'Porcentaje del Total (%)' ], ';' );
        foreach ( $stats['concepts'] as $c ) {
            fputcsv( $out, [ $c['concept_name'], $c['type'], number_format( $c['total'], 2, '.', '' ), $c['count'], $c['pct'] . '%' ], ';' );
        }
        fputcsv( $out, [], ';' );

        fputcsv( $out, [ 'EVOLUCIÓN CRONOLÓGICA MENSUAL' ], ';' );
        fputcsv( $out, [ 'Mes y Año', 'Ingresos Percibidos (' . $currency . ')', 'Egresos / Aportes (' . $currency . ')', 'Balance Neto Mensual (' . $currency . ')', 'Cantidad de Movimientos' ], ';' );
        foreach ( $stats['monthly'] as $mo ) {
            fputcsv( $out, [ $mo['label'], number_format( $mo['income'], 2, '.', '' ), number_format( $mo['expense'], 2, '.', '' ), number_format( $mo['net'], 2, '.', '' ), $mo['count'] ], ';' );
        }

        fclose( $out );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: Exportar Estadísticas Excel (.xlsx) (100% Español)
    // -------------------------------------------------------------------------

    public static function ajax_export_ledger_stats_excel(): void {
        check_ajax_referer( 'aura_transaction_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_user_ledger' ) &&
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'Sin permisos para exportar estadísticas.', 'aura-suite' ) );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_die( __( 'Usuario requerido.', 'aura-suite' ) );
        }

        $filters = [
            'date_from' => sanitize_text_field( $_POST['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_POST['date_to']   ?? '' ),
            'concept'   => sanitize_key( $_POST['concept']           ?? '' ),
            'show_all'  => ! empty( $_POST['show_all'] ),
        ];

        $stats    = self::get_ledger_statistics( $user_id, $filters );
        $user_obj = get_userdata( $user_id );
        $currency = get_option( 'aura_currency_symbol', '$' );

        $user_name_slug = sanitize_file_name( $user_obj ? ( $user_obj->user_login ?: $user_obj->display_name ) : 'usuario' );
        $filename = 'estadisticas-libro-mayor-' . $user_name_slug . '-' . date( 'Y-m-d' ) . '.xlsx';

        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
            if ( file_exists( $autoload ) ) {
                require_once $autoload;
            }
        }

        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle( 'Estadísticas Contables' );

            $sheet->setCellValue( 'A1', 'ESTADÍSTICAS CONTABLES DE LIBRO MAYOR - AURA BUSINESS SUITE' );
            $sheet->mergeCells( 'A1:E1' );
            $sheet->getStyle( 'A1' )->getFont()->setBold( true )->setSize( 14 )->getColor()->setRGB( 'FFFFFF' );
            $sheet->getStyle( 'A1' )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( '0F172A' );
            $sheet->getStyle( 'A1' )->getAlignment()->setHorizontal( \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );

            $sheet->setCellValue( 'A2', 'Colaborador: ' . ( $user_obj ? $user_obj->display_name . ' (@' . $user_obj->user_login . ')' : 'N/A' ) . ' | Período: ' . ( $filters['date_from'] ?: 'Inicio' ) . ' al ' . ( $filters['date_to'] ?: 'Hoy' ) );
            $sheet->mergeCells( 'A2:E2' );
            $sheet->getStyle( 'A2' )->getFont()->setItalic( true )->setSize( 10 )->getColor()->setRGB( '64748B' );

            $m = $stats['metrics'];
            $sheet->setCellValue( 'A4', 'INDICADOR CLAVE' );
            $sheet->setCellValue( 'B4', 'VALOR' );
            $sheet->getStyle( 'A4:B4' )->getFont()->setBold( true )->getColor()->setRGB( 'FFFFFF' );
            $sheet->getStyle( 'A4:B4' )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( '6366F1' );

            $sheet->setCellValue( 'A5', 'Total Ingresos Percibidos' );
            $sheet->setCellValue( 'B5', $m['total_income'] );
            $sheet->setCellValue( 'A6', 'Total Egresos o Aportes' );
            $sheet->setCellValue( 'B6', $m['total_expense'] );
            $sheet->setCellValue( 'A7', 'Balance Neto Corriente' );
            $sheet->setCellValue( 'B7', $m['net_balance'] );
            $sheet->setCellValue( 'A8', 'Promedio por Operación' );
            $sheet->setCellValue( 'B8', $m['avg_transaction'] );
            $sheet->setCellValue( 'A9', 'Tasa de Aprobación' );
            $sheet->setCellValue( 'B9', $m['approval_rate'] . '%' );
            $sheet->getStyle( 'B5:B8' )->getNumberFormat()->setFormatCode( '#,##0.00' );

            // Conceptos
            $sheet->setCellValue( 'A11', 'DESGLOSE POR CONCEPTO CONTABLE' );
            $sheet->mergeCells( 'A11:E11' );
            $sheet->getStyle( 'A11' )->getFont()->setBold( true )->getColor()->setRGB( 'FFFFFF' );
            $sheet->getStyle( 'A11' )->getFill()->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )->getStartColor()->setRGB( '2563EB' );

            $sheet->setCellValue( 'A12', 'Concepto' );
            $sheet->setCellValue( 'B12', 'Tipo' );
            $sheet->setCellValue( 'C12', 'Monto Total (' . $currency . ')' );
            $sheet->setCellValue( 'D12', 'Operaciones' );
            $sheet->setCellValue( 'E12', '% del Total' );
            $sheet->getStyle( 'A12:E12' )->getFont()->setBold( true );

            $c_idx = 13;
            foreach ( $stats['concepts'] as $c ) {
                $sheet->setCellValue( 'A' . $c_idx, $c['concept_name'] );
                $sheet->setCellValue( 'B' . $c_idx, $c['type'] );
                $sheet->setCellValue( 'C' . $c_idx, (float) $c['total'] );
                $sheet->setCellValue( 'D' . $c_idx, (int) $c['count'] );
                $sheet->setCellValue( 'E' . $c_idx, $c['pct'] . '%' );
                $sheet->getStyle( 'C' . $c_idx )->getNumberFormat()->setFormatCode( '#,##0.00' );
                $c_idx++;
            }

            foreach ( range( 1, 5 ) as $col ) {
                $sheet->getColumnDimension( \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col ) )->setAutoSize( true );
            }

            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Cache-Control: max-age=0' );

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
            $writer->save( 'php://output' );
            exit;
        }

        self::ajax_export_ledger_stats_csv();
    }

    /**
     * Obtener todas las filas para CSV (sin paginación, con balance corriente).
     */
    private static function get_all_ledger_rows_for_csv( int $user_id, array $filters = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $where = $wpdb->prepare(
            'related_user_id = %d AND deleted_at IS NULL',
            $user_id
        );

        if ( ! empty( $filters['date_from'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date >= %s', $filters['date_from'] );
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where .= $wpdb->prepare( ' AND transaction_date <= %s', $filters['date_to'] );
        }
        if ( ! empty( $filters['concept'] ) ) {
            $where .= $wpdb->prepare( ' AND related_user_concept = %s', $filters['concept'] );
        }

        $show_all = ! empty( $filters['show_all'] );
        if ( ! $show_all ) {
            $where .= " AND status = 'approved'";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT id, transaction_type, amount, description, transaction_date,
                    status, related_user_concept, reference_number
             FROM {$table}
             WHERE {$where}
             ORDER BY transaction_date ASC, id ASC"
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $running = 0.0;
        foreach ( $rows as $row ) {
            $amount = (float) $row->amount;
            if ( $row->transaction_type === 'expense' ) {
                $running += $amount;
            } else {
                $running -= $amount;
            }
            $row->running_balance = $running;
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Mapa de conceptos de vinculación de usuario → etiqueta legible en español.
     */
    public static function get_concepts_labels(): array {
        return [
            'payment_to_user'       => __( 'Pago realizado al usuario', 'aura-suite' ),
            'charge_to_user'        => __( 'Cobro realizado al usuario', 'aura-suite' ),
            'salary'                => __( 'Pago de salario / nómina', 'aura-suite' ),
            'scholarship'           => __( 'Beca asignada', 'aura-suite' ),
            'loan_payment'          => __( 'Pago de préstamo', 'aura-suite' ),
            'refund'                => __( 'Reembolso directo', 'aura-suite' ),
            'expense_reimbursement' => __( 'Reembolso de gastos', 'aura-suite' ),
        ];
    }
}
