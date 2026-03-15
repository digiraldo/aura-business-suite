<?php
/**
 * Sistema de Exportación Multi-formato – Fase 4, Item 4.1
 *
 * Soporta: CSV, Excel (.xlsx via PhpSpreadsheet), PDF (HTML profesional con
 * auto-print, logo corporativo y CSS @page), JSON y XML.
 * Registra un log de exportaciones y limpia archivos temporales.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Export {

    /** @var string Tabla de log de exportaciones */
    private static $log_table;

    /** @var string Directorio temporal para archivos generados */
    private static $export_dir;

    /** @var string URL pública del directorio de exportaciones */
    private static $export_url;

    /** @var string Tabla de transacciones */
    private static $tx_table;

    /** @var string Tabla de categorías */
    private static $cat_table;

    /* ------------------------------------------------------------------ */
    /* INIT                                                                 */
    /* ------------------------------------------------------------------ */

    public static function init() {
        global $wpdb;
        self::$log_table = $wpdb->prefix . 'aura_finance_export_log';
        self::$tx_table  = $wpdb->prefix . 'aura_finance_transactions';
        self::$cat_table = $wpdb->prefix . 'aura_finance_categories';

        $upload           = wp_upload_dir();
        self::$export_dir = trailingslashit($upload['basedir']) . 'aura-exports/';
        self::$export_url = trailingslashit($upload['baseurl']) . 'aura-exports/';

        add_action('admin_init',              [__CLASS__, 'maybe_create_log_table']);
        add_action('wp_ajax_aura_export_transactions', [__CLASS__, 'ajax_export']);
        add_action('wp_ajax_aura_export_log_list',    [__CLASS__, 'ajax_log_list']);
        add_action('aura_cleanup_exports_cron',        [__CLASS__, 'cleanup_old_exports']);

        if (!wp_next_scheduled('aura_cleanup_exports_cron')) {
            wp_schedule_event(time(), 'daily', 'aura_cleanup_exports_cron');
        }
    }

    /* ------------------------------------------------------------------ */
    /* TABLA DE LOG                                                          */
    /* ------------------------------------------------------------------ */

    public static function maybe_create_log_table() {
        global $wpdb;
        if (get_option('aura_export_log_table_v1')) {
            return;
        }
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$log_table . " (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            format     VARCHAR(10) NOT NULL,
            row_count  INT UNSIGNED NOT NULL DEFAULT 0,
            scope      VARCHAR(20) NOT NULL DEFAULT 'filtered',
            filters    TEXT,
            filename   VARCHAR(255),
            exported_by BIGINT UNSIGNED NOT NULL,
            exported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (exported_by),
            INDEX idx_date (exported_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('aura_export_log_table_v1', true);
    }

    /* ------------------------------------------------------------------ */
    /* AJAX – EXPORTAR                                                       */
    /* ------------------------------------------------------------------ */

    public static function ajax_export() {
        check_ajax_referer('aura_export_nonce', 'nonce');

        if (!current_user_can('aura_finance_view_own')
            && !current_user_can('aura_finance_view_all')
            && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos de exportación.', 'aura-suite')]);
        }

        $format  = sanitize_key($_POST['format']  ?? 'csv');
        $scope   = sanitize_text_field($_POST['scope']  ?? 'filtered');
        $columns = array_map('sanitize_key', (array)($_POST['columns'] ?? self::default_columns()));
        $ids     = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));

        // Opciones adicionales por formato
        $opts = [
            'include_totals' => !empty($_POST['include_totals']),
            'delimiter'      => in_array($_POST['delimiter'] ?? ',', [',', ';', "\t"]) ? $_POST['delimiter'] : ',',
            'company_name'   => get_option('aura_company_name', get_bloginfo('name')),
            'currency'       => get_option('aura_currency_symbol', '$'),
        ];

        $filters = self::parse_filters();
        $rows    = self::get_transactions($filters, $scope, $ids, $columns);

        if (empty($rows)) {
            wp_send_json_error(['message' => __('No hay transacciones para exportar con los filtros aplicados.', 'aura-suite')]);
        }

        $result = match($format) {
            'excel' => self::generate_excel($rows, $columns, $opts),
            'pdf'   => self::generate_pdf($rows, $columns, $opts),
            'json'  => self::generate_json($rows, $columns),
            'xml'   => self::generate_xml($rows, $columns),
            default => self::generate_csv($rows, $columns, $opts),
        };

        self::save_export_log($format, count($rows), $scope, $filters, $result['filename'] ?? '');
        do_action( 'aura_finance_export_executed', $format, count( $rows ), $filters );

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /* PARSE FILTERS                                                         */
    /* ------------------------------------------------------------------ */

    private static function parse_filters(): array {
        return [
            'date_from'      => sanitize_text_field($_POST['filter_date_from']      ?? ''),
            'date_to'        => sanitize_text_field($_POST['filter_date_to']        ?? ''),
            'type'           => sanitize_text_field($_POST['filter_type']           ?? ''),
            'status'         => array_map('sanitize_text_field', (array)($_POST['filter_status'] ?? [])),
            'category'       => intval($_POST['filter_category']                    ?? 0),
            'area'           => intval($_POST['filter_area']                        ?? 0),
            'amount_min'     => (float)($_POST['filter_amount_min']               ?? 0),
            'amount_max'     => (float)($_POST['filter_amount_max']               ?? 0),
            'payment_method' => sanitize_text_field($_POST['filter_payment_method'] ?? ''),
            'user_id'        => intval($_POST['filter_user']                        ?? 0),
            'search'         => sanitize_text_field($_POST['filter_search']         ?? ''),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* OBTENER TRANSACCIONES                                                 */
    /* ------------------------------------------------------------------ */

    private static function get_transactions(array $filters, string $scope, array $ids, array $columns): array {
        global $wpdb;

        $limit = intval(get_option('aura_export_max_rows', 10000));

        // Columnas a seleccionar
        $col_map = [
            'id'             => 't.id',
            'transaction_date'=> 't.transaction_date',
            'transaction_type'=> 't.transaction_type',
            'category'       => 'c.name AS category_name',
            'area'           => 'a.name AS area_name',
            'amount'         => 't.amount',
            'description'    => 't.description',
            'status'         => 't.status',
            'notes'          => 't.notes',
            'payment_method' => 't.payment_method',
            'reference'      => 't.reference_number',
            'created_by'     => 'u.display_name AS created_by_name',
            'approved_by'    => 'ap.display_name AS approved_by_name',
        ];

        $selected = [];
        foreach ($columns as $col) {
            if (isset($col_map[$col])) {
                $selected[] = $col_map[$col];
            }
        }
        if (empty($selected)) {
            $selected = ['t.id', 't.transaction_date', 't.transaction_type',
                         'c.name AS category_name', 't.amount', 't.description', 't.status'];
        }

        // Siempre incluir campos internos necesarios
        if (!in_array('t.id', $selected)) {
            $selected[] = 't.id';
        }

        $select_sql = implode(', ', array_unique($selected));

        $joins = "LEFT JOIN " . self::$cat_table . " c ON t.category_id = c.id"
               . " LEFT JOIN {$wpdb->prefix}aura_areas a ON t.area_id = a.id"
               . " LEFT JOIN {$wpdb->users} u  ON t.created_by = u.ID"
               . " LEFT JOIN {$wpdb->users} ap ON t.approved_by = ap.ID";

        // Visibilidad
        $vis_where = '';
        if (!current_user_can('aura_finance_view_all') && !current_user_can('manage_options')) {
            $vis_where = $wpdb->prepare(' AND t.created_by = %d', get_current_user_id());
        }

        // Scope
        $scope_where = '';
        if ($scope === 'selected' && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $scope_where  = $wpdb->prepare(" AND t.id IN ($placeholders)", ...$ids);
        }

        // Filtros
        $filter_where = ' AND t.deleted_at IS NULL';

        if (!empty($filters['date_from'])) {
            $filter_where .= $wpdb->prepare(' AND t.transaction_date >= %s', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $filter_where .= $wpdb->prepare(' AND t.transaction_date <= %s', $filters['date_to']);
        }
        if (!empty($filters['type'])) {
            $filter_where .= $wpdb->prepare(' AND t.transaction_type = %s', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $st_placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
            $filter_where   .= $wpdb->prepare(" AND t.status IN ($st_placeholders)", ...$filters['status']);
        }
        if (!empty($filters['category'])) {
            $filter_where .= $wpdb->prepare(' AND t.category_id = %d', $filters['category']);
        }
        // Fase 8.2: filtro por área
        if (!empty($filters['area'])) {
            $filter_where .= $wpdb->prepare(' AND t.area_id = %d', $filters['area']);
        }
        if (!empty($filters['amount_min'])) {
            $filter_where .= $wpdb->prepare(' AND t.amount >= %f', $filters['amount_min']);
        }
        if (!empty($filters['amount_max'])) {
            $filter_where .= $wpdb->prepare(' AND t.amount <= %f', $filters['amount_max']);
        }
        if (!empty($filters['payment_method'])) {
            $filter_where .= $wpdb->prepare(' AND t.payment_method = %s', $filters['payment_method']);
        }
        if (!empty($filters['user_id'])) {
            $filter_where .= $wpdb->prepare(' AND t.created_by = %d', $filters['user_id']);
        }
        if (!empty($filters['search'])) {
            $like         = '%' . $wpdb->esc_like($filters['search']) . '%';
            $filter_where .= $wpdb->prepare(' AND (t.description LIKE %s OR t.reference_number LIKE %s)', $like, $like);
        }

        $sql = "SELECT {$select_sql}
                FROM " . self::$tx_table . " t
                {$joins}
                WHERE 1=1
                {$vis_where}
                {$scope_where}
                {$filter_where}
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A) ?: [];
    }

    /* ------------------------------------------------------------------ */
    /* COLUMNAS POR DEFECTO                                                  */
    /* ------------------------------------------------------------------ */

    private static function default_columns(): array {
        return ['id', 'transaction_date', 'transaction_type', 'category', 'amount', 'description', 'status'];
    }

    private static function all_column_labels(): array {
        return [
            'id'               => 'ID',
            'transaction_date' => __('Fecha',           'aura-suite'),
            'transaction_type' => __('Tipo',            'aura-suite'),
            'category'         => __('Categoría',       'aura-suite'),
            'area'             => __('Área/Programa',    'aura-suite'),
            'amount'           => __('Monto',           'aura-suite'),
            'description'      => __('Descripción',     'aura-suite'),
            'status'           => __('Estado',          'aura-suite'),
            'notes'            => __('Notas',           'aura-suite'),
            'payment_method'   => __('Método de pago',  'aura-suite'),
            'reference'        => __('Referencia',      'aura-suite'),
            'created_by'       => __('Creado por',      'aura-suite'),
            'approved_by'      => __('Aprobado por',    'aura-suite'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* HELPER – Mapear fila DB → columnas seleccionadas                     */
    /* ------------------------------------------------------------------ */

    private static function map_row(array $row, array $columns): array {
        $db_key_map = [
            'id'               => 'id',
            'transaction_date' => 'transaction_date',
            'transaction_type' => 'transaction_type',
            'category'         => 'category_name',
            'area'             => 'area_name',
            'amount'           => 'amount',
            'description'      => 'description',
            'status'           => 'status',
            'notes'            => 'notes',
            'payment_method'   => 'payment_method',
            'reference'        => 'reference_number',
            'created_by'       => 'created_by_name',
            'approved_by'      => 'approved_by_name',
        ];
        $mapped = [];
        foreach ($columns as $col) {
            $db_key        = $db_key_map[$col] ?? $col;
            $mapped[$col]  = $row[$db_key] ?? '';
        }
        return $mapped;
    }

    /* ------------------------------------------------------------------ */
    /* CSV                                                                   */
    /* ------------------------------------------------------------------ */

    private static function generate_csv(array $rows, array $columns, array $opts): array {
        $delimiter = $opts['delimiter'];
        $labels    = self::all_column_labels();

        ob_start();
        // BOM para compatibilidad con Excel
        echo "\xEF\xBB\xBF";

        // Encabezados
        $headers = array_map(fn($c) => $labels[$c] ?? $c, $columns);
        echo implode($delimiter, array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\r\n";

        // Datos
        foreach ($rows as $row) {
            $mapped = self::map_row($row, $columns);
            $cells  = array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $mapped);
            echo implode($delimiter, $cells) . "\r\n";
        }

        // Totales (si se solicita y la columna amount está)
        if (!empty($opts['include_totals']) && in_array('amount', $columns)) {
            $total = array_sum(array_column($rows, 'amount'));
            $idx   = array_search('amount', $columns);
            $total_row = array_fill(0, count($columns), '""');
            $total_row[0] = '"TOTAL"';
            $total_row[$idx] = '"' . number_format($total, 2) . '"';
            echo implode($delimiter, $total_row) . "\r\n";
        }

        $content = ob_get_clean();
        $filename = 'transacciones-' . date('Y-m-d-His') . '.csv';

        return [
            'type'     => 'base64',
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'text/csv;charset=utf-8',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* EXCEL                                                                 */
    /* ------------------------------------------------------------------ */

    private static function generate_excel(array $rows, array $columns, array $opts): array {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            } else {
                return self::generate_csv($rows, $columns, $opts);
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $labels      = self::all_column_labels();

        // ---- Hoja 1: Transacciones ----
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transacciones');

        // Estilo encabezado
        $header_style = [
            'font'  => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'  => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2271B1']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        // Encabezados fila 1
        $col_idx = 1;
        foreach ($columns as $col) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx) . '1';
            $sheet->setCellValue($cell, $labels[$col] ?? $col);
            $sheet->getStyle($cell)->applyFromArray($header_style);
            $col_idx++;
        }

        // Filtros automáticos
        $last_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->setAutoFilter("A1:{$last_col}1");

        // Datos
        $row_num  = 2;
        $total_amount = 0;
        $amount_col_idx = array_search('amount', $columns);

        foreach ($rows as $row) {
            $mapped  = self::map_row($row, $columns);
            $col_idx = 1;
            foreach ($columns as $col) {
                $cellRef = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx) . $row_num;
                $value   = $mapped[$col] ?? '';

                if ($col === 'amount') {
                    $value = (float) $value;
                    $total_amount += $value;
                    $sheet->setCellValue($cellRef, $value);
                    $sheet->getStyle($cellRef)
                          ->getNumberFormat()
                          ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
                } elseif ($col === 'transaction_date') {
                    $sheet->setCellValue($cellRef, $value);
                } else {
                    $sheet->setCellValue($cellRef, $value);
                }

                // Filas alternadas
                if ($row_num % 2 === 0) {
                    $sheet->getStyle($cellRef)->getFill()
                          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                          ->getStartColor()->setRGB('F0F4F8');
                }
                $col_idx++;
            }
            $row_num++;
        }

        // Fila de totales
        if (!empty($opts['include_totals']) && $amount_col_idx !== false) {
            $total_row   = $row_num;
            $amount_cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($amount_col_idx + 1) . $total_row;
            $a1_cell     = 'A' . $total_row;

            $sheet->setCellValue($a1_cell, 'TOTAL');
            $sheet->getStyle($a1_cell)->getFont()->setBold(true);
            $sheet->setCellValue($amount_cell, $total_amount);
            $sheet->getStyle($amount_cell)->getFont()->setBold(true);
            $sheet->getStyle($amount_cell)
                  ->getNumberFormat()
                  ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        }

        // Ajustar ancho de columnas
        foreach (range(1, count($columns)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        // ---- Hoja 2: Resumen ----
        $summary = $spreadsheet->createSheet();
        $summary->setTitle('Resumen');

        $income  = array_sum(array_filter(
            array_column($rows, 'amount'),
            fn($k) => ($rows[$k]['transaction_type'] ?? '') === 'income',
            ARRAY_FILTER_USE_KEY
        ));
        $expense = array_sum(array_filter(
            array_column($rows, 'amount'),
            fn($k) => ($rows[$k]['transaction_type'] ?? '') === 'expense',
            ARRAY_FILTER_USE_KEY
        ));

        // Calcular totales reales
        $inc_total = 0;
        $exp_total = 0;
        foreach ($rows as $r) {
            if (($r['transaction_type'] ?? '') === 'income') {
                $inc_total += (float)($r['amount'] ?? 0);
            } else {
                $exp_total += (float)($r['amount'] ?? 0);
            }
        }

        $summary_data = [
            ['Concepto', 'Valor'],
            ['Empresa',   $opts['company_name']],
            ['Generado',  date('d/m/Y H:i')],
            ['Registros', count($rows)],
            ['Total Ingresos',  $inc_total],
            ['Total Egresos',   $exp_total],
            ['Balance Net',     $inc_total - $exp_total],
        ];

        foreach ($summary_data as $i => $s_row) {
            $summary->setCellValue('A' . ($i + 1), $s_row[0]);
            $summary->setCellValue('B' . ($i + 1), $s_row[1]);
            if ($i === 0) {
                $summary->getStyle('A1:B1')->applyFromArray($header_style);
            }
        }

        $summary->getColumnDimension('A')->setWidth(20);
        $summary->getColumnDimension('B')->setWidth(25);

        // Guardar en directorio temporal
        self::ensure_export_dir();
        $filename = 'transacciones-' . date('Y-m-d-His') . '-' . wp_generate_password(6, false) . '.xlsx';
        $filepath = self::$export_dir . $filename;

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filepath);

        return [
            'type'     => 'url',
            'url'      => self::$export_url . $filename,
            'filename' => $filename,
            'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* PDF — HTML profesional con auto-print, logo corporativo y CSS @page */
    /* ------------------------------------------------------------------ */

    private static function generate_pdf(array $rows, array $columns, array $opts): array {
        self::ensure_export_dir();

        $all_labels = self::all_column_labels();
        $company    = esc_html($opts['company_name']);
        $date_label = date_i18n(get_option('date_format') . ' H:i');
        $cur        = esc_html($opts['currency']);
        $count      = count($rows);
        $user_name  = esc_html(wp_get_current_user()->display_name);

        // Logo: primero el logo del plugin, luego el icono del sitio como fallback.
        // Se incrusta como data URI para que funcione dentro del blob HTML sin
        // depender de acceso al servidor.
        $plugin_logo_path = AURA_PLUGIN_DIR . 'assets/images/logo-aura.png';
        if ( file_exists( $plugin_logo_path ) ) {
            $logo_data = base64_encode( file_get_contents( $plugin_logo_path ) );
            $logo_src  = 'data:image/png;base64,' . $logo_data;
        } elseif ( ( $site_icon = get_site_icon_url(80) ) ) {
            $logo_src  = esc_url( $site_icon );
        } else {
            $logo_src  = '';
        }
        $logo_html = $logo_src
            ? '<img src="' . $logo_src . '" alt="' . esc_attr( $company ) . '" style="height:48px;width:auto;vertical-align:middle;margin-right:12px;object-fit:contain">'
            : '';

        // Encabezados de columna
        $th_cells = '';
        foreach ($columns as $col) {
            $th_cells .= '<th>' . esc_html($all_labels[$col] ?? $col) . '</th>';
        }

        // Filas de datos
        $tbody        = '';
        $total_amount = 0.0;
        $amount_idx   = array_search('amount', $columns);

        foreach ($rows as $row) {
            $mapped = self::map_row($row, $columns);
            $tr     = '';
            foreach ($columns as $col) {
                $val = $mapped[$col] ?? '';
                if ($col === 'amount') {
                    $val           = (float) $val;
                    $total_amount += $val;
                    $tr .= '<td class="num">' . $cur . '&nbsp;' . number_format($val, 2) . '</td>';
                } elseif ($col === 'transaction_type') {
                    $lbl   = $val === 'income' ? 'Ingreso' : 'Egreso';
                    $cls   = $val === 'income' ? 'type-income' : 'type-expense';
                    $tr   .= '<td><span class="badge ' . $cls . '">' . $lbl . '</span></td>';
                } elseif ($col === 'status') {
                    $lbl_map = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];
                    $tr .= '<td>' . esc_html($lbl_map[$val] ?? ucfirst($val)) . '</td>';
                } else {
                    $tr .= '<td>' . esc_html($val) . '</td>';
                }
            }
            $tbody .= "<tr>{$tr}</tr>\n";
        }

        // Fila de totales
        $total_row = '';
        if (!empty($opts['include_totals']) && $amount_idx !== false) {
            $cells        = array_fill(0, count($columns), '<td></td>');
            $cells[0]     = '<td class="total-label">TOTAL</td>';
            $cells[$amount_idx] = '<td class="num total-amount">'
                . $cur . '&nbsp;' . number_format($total_amount, 2) . '</td>';
            $total_row = '<tr class="total-row">' . implode('', $cells) . '</tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{$company} – Transacciones</title>
<style>
  /* ── Variables ─────────────────────────────────────────────── */
  :root {
    --brand:   #2271b1;
    --brand-dk:#1a5489;
    --income:  #059669;
    --expense: #dc2626;
    --bg-alt:  #f8fafc;
    --border:  #e2e8f0;
    --text:    #1e293b;
    --muted:   #64748b;
  }

  /* ── Layout base ───────────────────────────────────────────── */
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #fff; }
  body {
    font-family: 'Arial', Helvetica, sans-serif;
    font-size: 10pt;
    color: var(--text);
    line-height: 1.4;
  }

  /* ── Cabecera del documento ────────────────────────────────── */
  .doc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0 10px;
    border-bottom: 3px solid var(--brand);
    margin-bottom: 14px;
  }
  .doc-header__left  { display: flex; align-items: center; }
  .doc-header__title { font-size: 16pt; font-weight: 700; color: var(--brand); margin: 0; }
  .doc-header__sub   { font-size: 9pt; color: var(--muted); margin-top: 2px; }
  .doc-header__right { text-align: right; font-size: 8pt; color: var(--muted); }

  /* ── Resumen rápido ────────────────────────────────────────── */
  .summary-bar {
    display: flex;
    gap: 12px;
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 14px;
    margin-bottom: 14px;
    font-size: 9pt;
  }
  .summary-bar span { color: var(--muted); }
  .summary-bar strong { color: var(--text); }

  /* ── Tabla ─────────────────────────────────────────────────── */
  table {
    border-collapse: collapse;
    width: 100%;
    font-size: 9pt;
  }
  thead th {
    background: var(--brand);
    color: #fff;
    padding: 5px 8px;
    text-align: left;
    font-weight: 600;
    border-right: 1px solid var(--brand-dk);
  }
  thead th:last-child { border-right: none; }
  tbody tr:nth-child(even) td { background: var(--bg-alt); }
  tbody tr:hover td { background: #e8f1fb; }
  td {
    padding: 4px 8px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
  }
  td.num    { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

  /* ── Badges de tipo ────────────────────────────────────────── */
  .badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 9999px;
    font-size: 8pt;
    font-weight: 600;
  }
  .badge.type-income  { background: #d1fae5; color: var(--income); }
  .badge.type-expense { background: #fee2e2; color: var(--expense); }

  /* ── Fila de totales ───────────────────────────────────────── */
  .total-row td          { background: #dbeafe !important; border-top: 2px solid var(--brand); }
  .total-label           { font-weight: 700; }
  .total-amount          { font-weight: 700; color: var(--brand-dk); }

  /* ── Pie de página ─────────────────────────────────────────── */
  .doc-footer {
    margin-top: 16px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
    font-size: 7.5pt;
    color: var(--muted);
    display: flex;
    justify-content: space-between;
  }

  /* ── Barra de acción (solo pantalla) ───────────────────────── */
  .action-bar {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 10px 16px;
    margin-bottom: 16px;
    font-size: 10pt;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .action-bar .btn-print {
    background: var(--brand);
    color: #fff;
    border: none;
    padding: 7px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 10pt;
    font-weight: 600;
  }
  .action-bar .btn-print:hover { background: var(--brand-dk); }

  /* ── CSS Paged Media (impresión) ───────────────────────────── */
  @page {
    size: A4 landscape;
    margin: 14mm 10mm 18mm;

    @bottom-center {
      content: "Página " counter(page) " de " counter(pages);
      font-size: 8pt;
      color: #64748b;
    }
    @bottom-left {
      content: "{$company}";
      font-size: 8pt;
      color: #64748b;
    }
    @bottom-right {
      content: "Aura Business Suite";
      font-size: 8pt;
      color: #64748b;
    }
  }

  @media print {
    .action-bar { display: none !important; }
    body { font-size: 9pt; }
    thead { display: table-header-group; }
    tbody tr { page-break-inside: avoid; }
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
</style>
</head>
<body>

<!-- Barra de acción (solo visible en pantalla) -->
<div class="action-bar">
  <div>
    <strong>Vista previa PDF.</strong>
    Para guardar como PDF: haz clic en el botón o usa <kbd>Ctrl+P</kbd> → <em>Guardar como PDF</em>.
  </div>
  <button class="btn-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
</div>

<!-- Cabecera del documento -->
<div class="doc-header">
  <div class="doc-header__left">
    {$logo_html}
    <div>
      <div class="doc-header__title">{$company}</div>
      <div class="doc-header__sub">Exportación de Transacciones</div>
    </div>
  </div>
  <div class="doc-header__right">
    Generado por <strong>{$user_name}</strong><br>
    {$date_label}
  </div>
</div>

<!-- Barra de resumen -->
<div class="summary-bar">
  <div><span>Registros:</span> <strong>{$count}</strong></div>
  <div><span>Moneda:</span> <strong>{$cur}</strong></div>
</div>

<!-- Tabla de datos -->
<table>
  <thead><tr>{$th_cells}</tr></thead>
  <tbody>{$tbody}{$total_row}</tbody>
</table>

<!-- Pie -->
<div class="doc-footer">
  <span>Generado por Aura Business Suite · {$date_label}</span>
  <span>{$company}</span>
</div>

<script>
  // Auto-abrir el diálogo de impresión al cargar (funciona cuando se abre en pestaña nueva)
  window.addEventListener('load', function() {
    // Pequeño delay para que el navegador renderice los estilos
    setTimeout(function() { window.print(); }, 400);
  });
</script>
</body>
</html>
HTML;

        // Devolver el HTML codificado en base64 para que el JS lo abra
        // como Blob URL (evita problemas de permisos en el servidor de archivos)
        $filename = 'transacciones-' . date('Y-m-d-His') . '.pdf.html';

        return [
            'type'         => 'base64',
            'content'      => base64_encode( $html ),
            'filename'     => $filename,
            'mime'         => 'text/html;charset=utf-8',
            'open_in_tab'  => true,
            'record_count' => $count,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* JSON                                                                  */
    /* ------------------------------------------------------------------ */

    private static function generate_json(array $rows, array $columns): array {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[] = self::map_row($row, $columns);
        }

        $payload = [
            'meta' => [
                'exported_at'   => date('Y-m-d\TH:i:sP'),
                'total_records' => count($rows),
                'columns'       => $columns,
                'plugin'        => 'Aura Business Suite',
            ],
            'transactions' => $mapped,
        ];

        $content  = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'transacciones-' . date('Y-m-d-His') . '.json';

        return [
            'type'     => 'base64',
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'application/json',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* XML                                                                   */
    /* ------------------------------------------------------------------ */

    private static function generate_xml(array $rows, array $columns): array {
        $dom  = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('transactions');
        $root->setAttribute('exported_at', date('Y-m-d\TH:i:sP'));
        $root->setAttribute('total_records', (string)count($rows));
        $dom->appendChild($root);

        foreach ($rows as $row) {
            $mapped = self::map_row($row, $columns);
            $tx_el  = $dom->createElement('transaction');
            foreach ($mapped as $key => $val) {
                $el = $dom->createElement($key);
                $el->appendChild($dom->createTextNode((string)$val));
                $tx_el->appendChild($el);
            }
            $root->appendChild($tx_el);
        }

        $content  = $dom->saveXML();
        $filename = 'transacciones-' . date('Y-m-d-His') . '.xml';

        return [
            'type'     => 'base64',
            'content'  => base64_encode($content),
            'filename' => $filename,
            'mime'     => 'application/xml',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* LOG DE EXPORTACIONES                                                   */
    /* ------------------------------------------------------------------ */

    private static function save_export_log(string $format, int $count, string $scope, array $filters, string $filename): void {
        global $wpdb;
        $wpdb->insert(
            self::$log_table,
            [
                'format'      => $format,
                'row_count'   => $count,
                'scope'       => $scope,
                'filters'     => wp_json_encode($filters),
                'filename'    => basename($filename),
                'exported_by' => get_current_user_id(),
                'exported_at' => current_time('mysql'),
            ],
            ['%s','%d','%s','%s','%s','%d','%s']
        );
    }

    public static function ajax_log_list() {
        check_ajax_referer('aura_export_nonce', 'nonce');
        if (!current_user_can('manage_options') && !current_user_can('aura_finance_view_all')) {
            wp_send_json_error(['message' => __('Sin permisos.', 'aura-suite')]);
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT l.*, u.display_name
             FROM " . self::$log_table . " l
             LEFT JOIN {$wpdb->users} u ON l.exported_by = u.ID
             ORDER BY l.exported_at DESC
             LIMIT 100",
            ARRAY_A
        ) ?: [];

        wp_send_json_success(['logs' => $rows]);
    }

    /* ------------------------------------------------------------------ */
    /* LIMPIEZA AUTOMÁTICA                                                   */
    /* ------------------------------------------------------------------ */

    public static function cleanup_old_exports(): void {
        if (!is_dir(self::$export_dir)) {
            return;
        }
        $files   = glob(self::$export_dir . '*') ?: [];
        $cutoff  = time() - DAY_IN_SECONDS;
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* HELPER – Directorio de exportaciones                                  */
    /* ------------------------------------------------------------------ */

    private static function ensure_export_dir(): void {
        if (!is_dir(self::$export_dir)) {
            wp_mkdir_p(self::$export_dir);
            // Proteger el directorio con .htaccess
            $htaccess = self::$export_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\nOrder deny,allow\nDeny from all\n");
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* RENDER PÁGINA DE LOG                                                   */
    /* ------------------------------------------------------------------ */

    public static function render() {
        global $wpdb;
        $logs = $wpdb->get_results(
            "SELECT l.*, u.display_name
             FROM " . self::$log_table . " l
             LEFT JOIN {$wpdb->users} u ON l.exported_by = u.ID
             ORDER BY l.exported_at DESC
             LIMIT 100",
            ARRAY_A
        ) ?: [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Log de Exportaciones', 'aura-suite'); ?></h1>
            <p class="description">
                <?php esc_html_e('Registro de las últimas exportaciones realizadas.', 'aura-suite'); ?>
            </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Fecha', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Formato', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Registros', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Alcance', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Usuario', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Archivo', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6"><?php esc_html_e('Sin exportaciones registradas.', 'aura-suite'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['exported_at']); ?></td>
                        <td><strong><?php echo strtoupper(esc_html($log['format'])); ?></strong></td>
                        <td><?php echo intval($log['row_count']); ?></td>
                        <td><?php echo esc_html($log['scope']); ?></td>
                        <td><?php echo esc_html($log['display_name'] ?? '—'); ?></td>
                        <td><?php echo esc_html($log['filename'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
