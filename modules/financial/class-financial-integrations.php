<?php
/**
 * Aura Business Suite — Integraciones con Software Contable (Fase 5, Item 5.5)
 *
 * Genera exportaciones en formatos:
 *   - QuickBooks IIF
 *   - Contabilidad Electrónica MX / SAP (XML balanza)
 *   - Excel personalizado (.xlsx via PhpSpreadsheet, CSV fallback)
 *
 * @package Aura_Business_Suite
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_Integrations {

    /* ------------------------------------------------------------------
     * Constantes
     * ------------------------------------------------------------------ */
    const NONCE          = 'aura_integrations_nonce';
    const MAPPING_OPTION = 'aura_accounting_mapping';

    /* ------------------------------------------------------------------
     * INIT — registrar AJAX
     * ------------------------------------------------------------------ */
    public static function init(): void {
        add_action( 'wp_ajax_aura_integrations_get_categories', [ __CLASS__, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_aura_integrations_get_mapping',    [ __CLASS__, 'ajax_get_mapping' ] );
        add_action( 'wp_ajax_aura_integrations_save_mapping',   [ __CLASS__, 'ajax_save_mapping' ] );
        add_action( 'wp_ajax_aura_integrations_preview',        [ __CLASS__, 'ajax_preview' ] );
        add_action( 'wp_ajax_aura_export_accounting_format',    [ __CLASS__, 'ajax_export_accounting' ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: obtener categorías activas para el mapeo
     * ------------------------------------------------------------------ */
    public static function ajax_get_categories(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $cats = self::get_all_categories();
        wp_send_json_success( $cats );
    }

    /* ------------------------------------------------------------------
     * AJAX: obtener mapeo guardado
     * ------------------------------------------------------------------ */
    public static function ajax_get_mapping(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $mapping = get_option( self::MAPPING_OPTION, [] );
        wp_send_json_success( $mapping );
    }

    /* ------------------------------------------------------------------
     * AJAX: guardar mapeo categoría → cuenta contable
     * ------------------------------------------------------------------ */
    public static function ajax_save_mapping(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $raw_mapping = (array) ( $_POST['mapping'] ?? [] );
        $clean       = [];
        foreach ( $raw_mapping as $cat_id => $code ) {
            $clean[ absint( $cat_id ) ] = sanitize_text_field( $code );
        }

        update_option( self::MAPPING_OPTION, $clean );
        wp_send_json_success( [ 'message' => __( 'Mapeo guardado correctamente.', 'aura-suite' ) ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: vista previa (primeras 10 filas según software)
     * ------------------------------------------------------------------ */
    public static function ajax_preview(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $software      = sanitize_key( $_POST['software'] ?? 'quickbooks' );
        $date_from     = sanitize_text_field( $_POST['date_from']  ?? '' );
        $date_to       = sanitize_text_field( $_POST['date_to']    ?? '' );
        $only_approved = ! empty( $_POST['only_approved'] );
        $excluded_cats = array_map( 'absint', (array) ( $_POST['excluded_cats'] ?? [] ) );

        $rows = self::get_transactions( [
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'only_approved' => $only_approved,
            'excluded_cats' => $excluded_cats,
            'limit'         => 10,
        ] );

        if ( empty( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay transacciones con los filtros aplicados.', 'aura-suite' ) ] );
        }

        $mapping      = (array) get_option( self::MAPPING_OPTION, [] );
        $preview_html = self::build_preview_html( $rows, $software, $mapping );

        wp_send_json_success( [
            'html'  => $preview_html,
            'total' => count( $rows ),
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: exportar formato contable completo
     * ------------------------------------------------------------------ */
    public static function ajax_export_accounting(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $software      = sanitize_key( $_POST['software']  ?? 'quickbooks' );
        $date_from     = sanitize_text_field( $_POST['date_from']  ?? '' );
        $date_to       = sanitize_text_field( $_POST['date_to']    ?? '' );
        $only_approved = ! empty( $_POST['only_approved'] );
        $excluded_cats = array_map( 'absint', (array) ( $_POST['excluded_cats'] ?? [] ) );
        $custom_cols   = array_map( 'sanitize_key', (array) ( $_POST['custom_cols'] ?? [] ) );

        $rows = self::get_transactions( [
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'only_approved' => $only_approved,
            'excluded_cats' => $excluded_cats,
        ] );

        if ( empty( $rows ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay transacciones para exportar con los filtros aplicados.', 'aura-suite' ) ] );
        }

        $mapping    = (array) get_option( self::MAPPING_OPTION, [] );
        $categories = self::get_all_categories();

        $result = match ( $software ) {
            'sap', 'contabilidad_mx' => self::generate_accounting_xml( $rows, $categories, $mapping ),
            'excel'                  => self::generate_accounting_excel( $rows, $categories, $mapping, $custom_cols ),
            default                  => self::generate_iif( $rows, $categories, $mapping ),
        };

        do_action( 'aura_finance_export_executed', 'accounting_' . $software, count( $rows ), [] );

        wp_send_json_success( $result );
    }

    /* ==================================================================
     * GENERADORES
     * ================================================================== */

    /* ------------------------------------------------------------------
     * QUICKBOOKS IIF
     * ------------------------------------------------------------------ */
    private static function generate_iif( array $rows, array $categories, array $mapping ): array {
        $lines = [];

        // ---- Cuentas ----
        $lines[] = "!ACCNT\tNAME\tACCNTTYPE";
        $seen_cats = [];
        foreach ( $rows as $row ) {
            if ( in_array( $row->category_id, $seen_cats, true ) ) {
                continue;
            }
            $seen_cats[] = $row->category_id;
            $cat_name    = $row->category_name ?? 'Sin Categoría';
            $account     = ( ! empty( $mapping[ $row->category_id ] ) )
                ? $mapping[ $row->category_id ]
                : $cat_name;
            $acc_type = ( $row->transaction_type === 'income' ) ? 'INC' : 'EXP';
            $lines[]  = "ACCNT\t" . $account . "\t" . $acc_type;
        }

        // ---- Transacciones ----
        $lines[] = "!TRNS\tDATE\tACCNT\tNAME\tAMOUNT\tMEMO\tTOPRINT";
        $lines[] = "!SPL\tDATE\tACCNT\tAMOUNT\tMEMO";
        $lines[] = "!ENDTRNS";

        foreach ( $rows as $row ) {
            $date    = date( 'm/d/Y', strtotime( $row->transaction_date ) );
            $account = ( ! empty( $mapping[ $row->category_id ] ) )
                ? $mapping[ $row->category_id ]
                : ( $row->category_name ?? 'Sin Categoría' );
            $amount = ( $row->transaction_type === 'income' )
                ? abs( (float) $row->amount )
                : -abs( (float) $row->amount );
            $memo   = str_replace( [ "\t", "\r", "\n" ], ' ', $row->description ?? '' );
            $name   = str_replace( [ "\t", "\r", "\n" ], ' ', $row->description ?? 'Transacción' );

            $lines[] = "TRNS\t{$date}\t{$account}\t{$name}\t{$amount}\t{$memo}\tN";
            $lines[] = "SPL\t{$date}\tAccounts Payable\t" . ( -$amount ) . "\t{$memo}";
            $lines[] = "ENDTRNS";
        }

        $content  = implode( "\r\n", $lines );
        $filename = 'quickbooks-' . date( 'Y-m-d' ) . '-' . wp_generate_password( 4, false ) . '.iif';

        return [
            'content'   => base64_encode( $content ),
            'filename'  => $filename,
            'mime_type' => 'text/plain',
            'count'     => count( $rows ),
            'software'  => 'QuickBooks',
        ];
    }

    /* ------------------------------------------------------------------
     * CONTABILIDAD ELECTRÓNICA MX / SAP (XML balanza comprobación)
     * ------------------------------------------------------------------ */
    private static function generate_accounting_xml( array $rows, array $categories, array $mapping ): array {
        $rfc     = sanitize_text_field( get_option( 'aura_company_rfc',  'XAXX010101000' ) );
        $company = htmlspecialchars( get_option( 'aura_company_name', get_bloginfo( 'name' ) ), ENT_XML1, 'UTF-8' );
        $month   = date( 'm' );
        $year    = date( 'Y' );

        // Agrupa debe/haber por cuenta contable
        $grouped = [];
        foreach ( $rows as $row ) {
            $num_cta = ( ! empty( $mapping[ $row->category_id ] ) )
                ? sanitize_text_field( $mapping[ $row->category_id ] )
                : ( (string) ( (int) $row->category_id + 100 ) );

            if ( ! isset( $grouped[ $num_cta ] ) ) {
                $grouped[ $num_cta ] = [ 'debe' => 0.0, 'haber' => 0.0 ];
            }

            if ( $row->transaction_type === 'expense' ) {
                $grouped[ $num_cta ]['debe'] += abs( (float) $row->amount );
            } else {
                $grouped[ $num_cta ]['haber'] += abs( (float) $row->amount );
            }
        }

        // Calcula saldos iniciales y finales (simplificado: saldo ini = 0)
        $balance_lines = [];
        foreach ( $grouped as $num_cta => $totals ) {
            $debe    = number_format( $totals['debe'],  2, '.', '' );
            $haber   = number_format( $totals['haber'], 2, '.', '' );
            $saldo_f = number_format( $totals['haber'] - $totals['debe'], 2, '.', '' );
            $num_esc = htmlspecialchars( $num_cta, ENT_XML1, 'UTF-8' );
            $balance_lines[] = "  <BCE:Ctas NumCta=\"{$num_esc}\" SaldoIni=\"0.00\" Debe=\"{$debe}\" Haber=\"{$haber}\" SaldoFin=\"{$saldo_f}\"/>";
        }

        $rfc_esc = htmlspecialchars( $rfc, ENT_XML1, 'UTF-8' );
        $xml     = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml    .= '<BCE:Balanza' . "\n";
        $xml    .= '  xmlns:BCE="http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion"' . "\n";
        $xml    .= '  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        $xml    .= '  xsi:schemaLocation="http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion http://www.sat.gob.mx/esquemas/ContabilidadE/1_3/BalanzaComprobacion/BalanzaComprobacion_1_3.xsd"' . "\n";
        $xml    .= '  Version="1.3"' . "\n";
        $xml    .= '  RFC="' . $rfc_esc . '"' . "\n";
        $xml    .= '  Mes="' . $month . '"' . "\n";
        $xml    .= '  Anio="' . $year . '"' . "\n";
        $xml    .= '  TipoEnvio="N">' . "\n";
        $xml    .= implode( "\n", $balance_lines ) . "\n";
        $xml    .= '</BCE:Balanza>';

        $filename = 'balanza-contable-' . $year . $month . '-' . wp_generate_password( 4, false ) . '.xml';

        return [
            'content'   => base64_encode( $xml ),
            'filename'  => $filename,
            'mime_type' => 'application/xml',
            'count'     => count( $rows ),
            'software'  => 'SAP / Contabilidad MX',
        ];
    }

    /* ------------------------------------------------------------------
     * EXCEL PERSONALIZADO
     * ------------------------------------------------------------------ */
    private static function generate_accounting_excel( array $rows, array $categories, array $mapping, array $custom_cols ): array {
        $valid_cols = [ 'id', 'fecha', 'tipo', 'categoria', 'cuenta_contable', 'monto', 'descripcion', 'estado', 'metodo_pago' ];
        $cols       = ! empty( $custom_cols )
            ? array_values( array_intersect( $valid_cols, $custom_cols ) )
            : $valid_cols;

        if ( class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            return self::generate_xlsx_phpspreadsheet( $rows, $mapping, $cols );
        }

        return self::generate_xlsx_fallback_csv( $rows, $mapping, $cols );
    }

    private static function generate_xlsx_phpspreadsheet( array $rows, array $mapping, array $cols ): array {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()
                    ->setTitle( 'Exportación Contable' )
                    ->setSubject( 'Aura Business Suite' )
                    ->setCreator( 'Aura Business Suite' );

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle( 'Transacciones' );

        $col_labels = [
            'id'             => 'ID',
            'fecha'          => 'Fecha',
            'tipo'           => 'Tipo',
            'categoria'      => 'Categoría',
            'cuenta_contable'=> 'Cuenta Contable',
            'monto'          => 'Monto',
            'descripcion'    => 'Descripción',
            'estado'         => 'Estado',
            'metodo_pago'    => 'Método de Pago',
        ];

        // Encabezados con estilo
        $col_idx = 1;
        foreach ( $cols as $col ) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx ) . '1';
            $sheet->setCellValue( $cell, $col_labels[ $col ] ?? $col );
            $col_idx++;
        }

        $last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( count( $cols ) );
        $sheet->getStyle( 'A1:' . $last_col_letter . '1' )->applyFromArray( [
            'font' => [
                'bold'  => true,
                'color' => [ 'rgb' => 'FFFFFF' ],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [ 'rgb' => '2C3E50' ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ] );

        // Filas de datos
        $row_num = 2;
        foreach ( $rows as $row ) {
            $col_idx = 1;
            foreach ( $cols as $col ) {
                $cell  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( $col_idx ) . $row_num;
                $value = match ( $col ) {
                    'id'              => (int) $row->id,
                    'fecha'           => $row->transaction_date,
                    'tipo'            => ( $row->transaction_type === 'income' ) ? 'Ingreso' : 'Egreso',
                    'categoria'       => $row->category_name ?? '',
                    'cuenta_contable' => $mapping[ $row->category_id ] ?? '',
                    'monto'           => (float) $row->amount,
                    'descripcion'     => $row->description ?? '',
                    'estado'          => $row->status ?? '',
                    'metodo_pago'     => $row->payment_method ?? '',
                    default           => '',
                };

                $sheet->setCellValue( $cell, $value );

                if ( $col === 'monto' ) {
                    $sheet->getStyle( $cell )->getNumberFormat()
                          ->setFormatCode( \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2 );
                }

                // Alternar color de fila
                if ( $row_num % 2 === 0 ) {
                    $sheet->getStyle( $cell )->getFill()
                          ->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
                          ->getStartColor()->setRGB( 'F8F9FA' );
                }

                $col_idx++;
            }
            $row_num++;
        }

        // Ancho automático
        foreach ( range( 1, count( $cols ) ) as $ci ) {
            $sheet->getColumnDimensionByColumn( $ci )->setAutoSize( true );
        }

        // Guardar a fichero temporal
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $tmp_file = wp_tempnam( 'aura-accounting' ) . '.xlsx';
        $writer->save( $tmp_file );
        $content = file_get_contents( $tmp_file );
        @unlink( $tmp_file );

        $filename = 'contabilidad-' . date( 'Y-m-d' ) . '-' . wp_generate_password( 4, false ) . '.xlsx';

        return [
            'content'   => base64_encode( $content ),
            'filename'  => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'count'     => count( $rows ),
            'software'  => 'Excel',
        ];
    }

    private static function generate_xlsx_fallback_csv( array $rows, array $mapping, array $cols ): array {
        $col_labels = [
            'id'             => 'ID',
            'fecha'          => 'Fecha',
            'tipo'           => 'Tipo',
            'categoria'      => 'Categoría',
            'cuenta_contable'=> 'Cuenta Contable',
            'monto'          => 'Monto',
            'descripcion'    => 'Descripción',
            'estado'         => 'Estado',
            'metodo_pago'    => 'Método de Pago',
        ];

        $out = fopen( 'php://temp', 'r+' );
        // BOM UTF-8 para compatibilidad con Excel
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array_map( fn( $c ) => $col_labels[ $c ] ?? $c, $cols ) );

        foreach ( $rows as $row ) {
            $line = [];
            foreach ( $cols as $col ) {
                $line[] = match ( $col ) {
                    'id'              => $row->id,
                    'fecha'           => $row->transaction_date,
                    'tipo'            => ( $row->transaction_type === 'income' ) ? 'Ingreso' : 'Egreso',
                    'categoria'       => $row->category_name ?? '',
                    'cuenta_contable' => $mapping[ $row->category_id ] ?? '',
                    'monto'           => $row->amount,
                    'descripcion'     => $row->description ?? '',
                    'estado'          => $row->status ?? '',
                    'metodo_pago'     => $row->payment_method ?? '',
                    default           => '',
                };
            }
            fputcsv( $out, $line );
        }

        rewind( $out );
        $content = stream_get_contents( $out );
        fclose( $out );

        $filename = 'contabilidad-' . date( 'Y-m-d' ) . '-' . wp_generate_password( 4, false ) . '.csv';

        return [
            'content'   => base64_encode( $content ),
            'filename'  => $filename,
            'mime_type' => 'text/csv',
            'count'     => count( $rows ),
            'software'  => 'Excel (CSV)',
        ];
    }

    /* ==================================================================
     * QUERIES
     * ================================================================== */

    private static function get_transactions( array $filters ): array {
        global $wpdb;

        $tt = $wpdb->prefix . 'aura_finance_transactions';
        $ct = $wpdb->prefix . 'aura_finance_categories';

        $where  = [ '1=1' ];
        $params = [];

        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 't.transaction_date >= %s';
            $params[] = $filters['date_from'];
        }

        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 't.transaction_date <= %s';
            $params[] = $filters['date_to'];
        }

        if ( ! empty( $filters['only_approved'] ) ) {
            $where[] = "t.status = 'approved'";
        }

        if ( ! empty( $filters['excluded_cats'] ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $filters['excluded_cats'] ), '%d' ) );
            $where[]      = "t.category_id NOT IN ($placeholders)";
            $params       = array_merge( $params, $filters['excluded_cats'] );
        }

        $limit_sql = '';
        if ( ! empty( $filters['limit'] ) ) {
            $limit_sql = ' LIMIT ' . absint( $filters['limit'] );
        }

        $sql = "SELECT t.id, t.transaction_date, t.transaction_type, t.amount,
                       t.description, t.status, t.payment_method, t.category_id,
                       c.name AS category_name, c.type AS category_type
                FROM {$tt} t
                LEFT JOIN {$ct} c ON t.category_id = c.id
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY t.transaction_date DESC{$limit_sql}";

        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare( $sql, $params );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $sql ) ?: [];
    }

    private static function get_all_categories(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( "SELECT id, name, type, slug FROM {$table} WHERE is_active=1 ORDER BY type, name" ) ?: [];
    }

    /* ==================================================================
     * VISTA PREVIA HTML
     * ================================================================== */

    private static function build_preview_html( array $rows, string $software, array $mapping ): string {
        $html = '<table class="widefat striped aura-preview-table">';

        if ( $software === 'quickbooks' ) {
            $html .= '<thead><tr>
                        <th>Fecha</th>
                        <th>Cuenta (IIF)</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                        <th>Tipo</th>
                      </tr></thead><tbody>';

            foreach ( $rows as $row ) {
                $account = ( ! empty( $mapping[ $row->category_id ] ) )
                    ? $mapping[ $row->category_id ]
                    : ( $row->category_name ?? '—' );
                $amount  = number_format( (float) $row->amount, 2 );
                $badge   = ( $row->transaction_type === 'income' )
                    ? '<span class="aura-badge aura-badge-green">Ingreso</span>'
                    : '<span class="aura-badge aura-badge-red">Egreso</span>';
                $html .= '<tr>
                    <td>' . esc_html( $row->transaction_date ) . '</td>
                    <td><code>' . esc_html( $account ) . '</code></td>
                    <td>' . esc_html( $row->description ?? '—' ) . '</td>
                    <td>$' . esc_html( $amount ) . '</td>
                    <td>' . $badge . '</td>
                  </tr>';
            }
        } elseif ( in_array( $software, [ 'sap', 'contabilidad_mx' ], true ) ) {
            $html .= '<thead><tr>
                        <th>Fecha</th>
                        <th>Núm. Cuenta</th>
                        <th>Descripción</th>
                        <th>Debe</th>
                        <th>Haber</th>
                      </tr></thead><tbody>';

            foreach ( $rows as $row ) {
                $num_cta = ( ! empty( $mapping[ $row->category_id ] ) )
                    ? $mapping[ $row->category_id ]
                    : ( (string) ( (int) $row->category_id + 100 ) );
                $debe  = ( $row->transaction_type === 'expense' ) ? number_format( (float) $row->amount, 2 ) : '0.00';
                $haber = ( $row->transaction_type === 'income' )  ? number_format( (float) $row->amount, 2 ) : '0.00';
                $html .= '<tr>
                    <td>' . esc_html( $row->transaction_date ) . '</td>
                    <td><code>' . esc_html( $num_cta ) . '</code></td>
                    <td>' . esc_html( $row->description ?? '—' ) . '</td>
                    <td class="aura-debe">$' . esc_html( $debe ) . '</td>
                    <td class="aura-haber">$' . esc_html( $haber ) . '</td>
                  </tr>';
            }
        } else {
            // Excel
            $html .= '<thead><tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Categoría</th>
                        <th>Cuenta Contable</th>
                        <th>Monto</th>
                        <th>Estado</th>
                      </tr></thead><tbody>';

            foreach ( $rows as $row ) {
                $account = ( ! empty( $mapping[ $row->category_id ] ) ) ? $mapping[ $row->category_id ] : '—';
                $tipo    = ( $row->transaction_type === 'income' ) ? 'Ingreso' : 'Egreso';
                $html   .= '<tr>
                    <td>' . esc_html( $row->transaction_date ) . '</td>
                    <td>' . esc_html( $tipo ) . '</td>
                    <td>' . esc_html( $row->category_name ?? '—' ) . '</td>
                    <td><code>' . esc_html( $account ) . '</code></td>
                    <td>$' . esc_html( number_format( (float) $row->amount, 2 ) ) . '</td>
                    <td>' . esc_html( $row->status ?? '—' ) . '</td>
                  </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /* ------------------------------------------------------------------
     * RENDER
     * ------------------------------------------------------------------ */
    public static function render(): void {
        include AURA_PLUGIN_DIR . 'templates/financial/integrations-page.php';
    }
}
