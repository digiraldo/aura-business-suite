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
        add_action('wp_ajax_aura_export_accounts',     [__CLASS__, 'ajax_export_accounts']);
        add_action('wp_ajax_aura_export_categories',   [__CLASS__, 'ajax_export_categories']);
        add_action('wp_ajax_aura_export_areas',         [__CLASS__, 'ajax_export_areas']);
        add_action('wp_ajax_aura_export_third_parties', [__CLASS__, 'ajax_export_third_parties']);
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

        // Si se solicitó incluir comprobantes y el formato no es PDF (o según elección), empaquetar en ZIP
        if (!empty($_POST['include_attachments']) && class_exists('ZipArchive')) {
            $zip_res = self::generate_zip_export($result, $rows, $format);
            if (!is_wp_error($zip_res)) {
                $result = $zip_res;
            }
        }

        self::save_export_log($format, count($rows), $scope, $filters, $result['filename'] ?? '');
        do_action( 'aura_finance_export_executed', $format, count( $rows ), $filters );

        wp_send_json_success($result);
    }

    /* ------------------------------------------------------------------ */
    /* EXPORTAR CUENTAS (RAW)                                             */
    /* ------------------------------------------------------------------ */

    public static function ajax_export_accounts() {
        if (!current_user_can('aura_finance_view_all') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos.', 'aura-suite')]);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_accounts';
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A);
        if (empty($rows)) {
            wp_send_json_error(['message' => __('No hay cuentas para exportar.', 'aura-suite')]);
        }
        $columns = array_keys($rows[0]);
        ob_start();
        echo "\xEF\xBB\xBF";
        echo implode(',', $columns) . "\r\n";
        foreach ($rows as $row) {
            $cells = array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row);
            echo implode(',', $cells) . "\r\n";
        }
        $content = ob_get_clean();
        wp_send_json_success([
            'type' => 'base64',
            'content' => base64_encode($content),
            'filename' => 'cuentas-' . date('Y-m-d-His') . '.csv',
            'mime' => 'text/csv;charset=utf-8'
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* EXPORTAR CATEGORÍAS (RAW)                                          */
    /* ------------------------------------------------------------------ */

    public static function ajax_export_categories() {
        if (!current_user_can('aura_finance_category_manage') && !current_user_can('aura_finance_view_all') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos para exportar categorías.', 'aura-suite')]);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        
        $sql = "SELECT 
                    c.name,
                    c.slug,
                    c.type,
                    p.slug AS parent_category_slug,
                    c.color,
                    c.icon,
                    c.description,
                    c.is_active,
                    c.display_order,
                    c.is_capex
                FROM {$table} c
                LEFT JOIN {$table} p ON c.parent_id = p.id
                ORDER BY (c.parent_id IS NOT NULL) ASC, c.display_order ASC, c.name ASC";

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)) {
            wp_send_json_error(['message' => __('No hay categorías para exportar.', 'aura-suite')]);
        }

        $columns = ['name', 'slug', 'type', 'parent_category_slug', 'color', 'icon', 'description', 'is_active', 'display_order', 'is_capex'];

        ob_start();
        echo "\xEF\xBB\xBF"; // UTF-8 BOM para soporte de Excel y caracteres especiales/emojis
        echo implode(',', $columns) . "\r\n";
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $val = $row[$col] ?? '';
                if ($col === 'parent_category_slug' && empty($val)) {
                    $val = '';
                }
                $line[] = '"' . str_replace('"', '""', (string)$val) . '"';
            }
            echo implode(',', $line) . "\r\n";
        }
        $content = ob_get_clean();

        wp_send_json_success([
            'type'     => 'base64',
            'content'  => base64_encode($content),
            'filename' => 'categorias-financieras-' . date('Y-m-d-His') . '.csv',
            'mime'     => 'text/csv;charset=utf-8'
        ]);
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
        // Columnas a seleccionar (100% paridad con BD)
        $col_map = [
            'id'                       => 't.id',
            'transaction_date'         => 't.transaction_date',
            'transaction_type'         => 't.transaction_type',
            'category_id'              => 't.category_id',
            'category_name'            => 'c.name AS category_name',
            'area_id'                  => 't.area_id',
            'area_name'                => 'a.name AS area_name',
            'amount'                   => 't.amount',
            'description'              => 't.description',
            'notes'                    => 't.notes',
            'status'                   => 't.status',
            'payment_method'           => 't.payment_method',
            'reference_number'         => 't.reference_number',
            'source_account_id'        => 't.source_account_id',
            'source_account_name'      => 'sa.name AS source_account_name',
            'destination_account_id'   => 't.destination_account_id',
            'destination_account_name' => 'da.name AS destination_account_name',
            'recipient_payer'          => 't.recipient_payer',
            'receipt_file'             => 't.receipt_file',
            'tags'                     => 't.tags',
            'related_module'           => 't.related_module',
            'related_item_id'          => 't.related_item_id',
            'related_action'           => 't.related_action',
            'related_user_id'          => 't.related_user_id',
            'related_user_name'        => 'ru.display_name AS related_user_name',
            'related_user_concept'     => 't.related_user_concept',
            'expense_category_id'      => 't.expense_category_id',
            'created_by'               => 't.created_by',
            'created_by_name'          => 'u.display_name AS created_by_name',
            'approved_by'              => 't.approved_by',
            'approved_by_name'         => 'ap.display_name AS approved_by_name',
            'approved_at'              => 't.approved_at',
            'rejection_reason'         => 't.rejection_reason',
            'created_at'               => 't.created_at',
            'updated_at'               => 't.updated_at',
            'deleted_at'               => 't.deleted_at',
            'deleted_by'               => 't.deleted_by',
            'deleted_by_name'          => 'du.display_name AS deleted_by_name'
        ];

        // Mapa explícito de columnas ID → su columna de nombre legible
        $companion_cols = [
            'category_id'            => 'category_name',
            'area_id'                => 'area_name',
            'created_by'             => 'created_by_name',
            'approved_by'            => 'approved_by_name',
            'source_account_id'      => 'source_account_name',
            'destination_account_id' => 'destination_account_name',
            'related_user_id'        => 'related_user_name',
            'deleted_by'             => 'deleted_by_name',
        ];

        $selected = [];
        foreach ($columns as $col) {
            if (isset($col_map[$col])) {
                $selected[] = $col_map[$col];
                // Incluir la columna de nombre legible si existe un compañero definido
                $companion = $companion_cols[$col] ?? ($col . '_name');
                if (isset($col_map[$companion])) {
                    $selected[] = $col_map[$companion];
                }
            }
        }
        if (empty($selected)) {
            $selected = [
                't.id', 't.transaction_date', 't.transaction_type', 
                't.category_id', 'c.name AS category_name', 
                't.amount', 't.description', 't.status'
            ];
        }

        // Siempre incluir campos internos necesarios
        if (!in_array('t.id', $selected)) {
            $selected[] = 't.id';
        }

        $select_sql = implode(', ', array_unique($selected));

        $acc_table = $wpdb->prefix . 'aura_finance_accounts';
        $joins = "LEFT JOIN " . self::$cat_table . " c ON t.category_id = c.id"
               . " LEFT JOIN {$wpdb->prefix}aura_areas a ON t.area_id = a.id"
               . " LEFT JOIN {$wpdb->users} u  ON t.created_by = u.ID"
               . " LEFT JOIN {$wpdb->users} ap ON t.approved_by = ap.ID"
               . " LEFT JOIN {$acc_table} sa ON t.source_account_id = sa.id"
               . " LEFT JOIN {$acc_table} da ON t.destination_account_id = da.id"
               . " LEFT JOIN {$wpdb->users} ru ON t.related_user_id = ru.ID"
               . " LEFT JOIN {$wpdb->users} du ON t.deleted_by = du.ID";

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
        return [
            'id', 'transaction_date', 'transaction_type', 'category_id', 'area_id', 
            'amount', 'description', 'status', 'notes', 'payment_method', 
            'reference_number', 'source_account_id', 'destination_account_id', 
            'recipient_payer', 'receipt_file', 'tags', 'related_module', 
            'related_item_id', 'related_action', 'related_user_id', 
            'related_user_concept', 'expense_category_id', 'created_by', 
            'approved_by', 'approved_at', 'rejection_reason', 'created_at', 
            'updated_at', 'deleted_at', 'deleted_by'
        ];
    }

    private static function all_column_labels(string $format = 'csv'): array {
        $labels = [
            'id'                     => 'ID',
            'transaction_date'       => __('Fecha', 'aura-suite'),
            'transaction_type'       => __('Tipo', 'aura-suite'),
            'category_id'            => __('Categoría', 'aura-suite'),
            'area_id'                => __('Área/Programa', 'aura-suite'),
            'amount'                 => __('Monto', 'aura-suite'),
            'description'            => __('Descripción', 'aura-suite'),
            'status'                 => __('Estado', 'aura-suite'),
            'notes'                  => __('Notas', 'aura-suite'),
            'payment_method'         => __('Método de pago', 'aura-suite'),
            'reference_number'       => __('N° Referencia', 'aura-suite'),
            'source_account_id'      => __('Cuenta Origen', 'aura-suite'),
            'destination_account_id' => __('Cuenta Destino', 'aura-suite'),
            'recipient_payer'        => __('Beneficiario/Pagador', 'aura-suite'),
            'receipt_file'           => __('Recibo', 'aura-suite'),
            'tags'                   => __('Etiquetas', 'aura-suite'),
            'related_module'         => __('Módulo', 'aura-suite'),
            'related_item_id'        => __('ID Item', 'aura-suite'),
            'related_action'         => __('Acción', 'aura-suite'),
            'related_user_id'        => __('Usuario Vinculado', 'aura-suite'),
            'related_user_concept'   => __('Concepto Usuario Vinculado', 'aura-suite'),
            'expense_category_id'    => __('Cat. Gasto', 'aura-suite'),
            'created_by'             => __('Creado por', 'aura-suite'),
            'approved_by'            => __('Aprobado por', 'aura-suite'),
            'approved_at'            => __('Fecha Aprobación', 'aura-suite'),
            'rejection_reason'       => __('Motivo Rechazo', 'aura-suite'),
            'created_at'             => __('Fecha Creación', 'aura-suite'),
            'updated_at'             => __('Última Mod.', 'aura-suite'),
            'deleted_at'             => __('Eliminado el', 'aura-suite'),
            'deleted_by'             => __('Eliminado por', 'aura-suite'),
        ];

        return $labels;
    }

    /* ------------------------------------------------------------------ */
    /* HELPER – Mapear fila DB → columnas seleccionadas                     */
    /* ------------------------------------------------------------------ */

    private static function map_row(array $row, array $columns, string $format = 'csv'): array {
        $mapped = [];
        foreach ($columns as $col) {
            // Usar nombres legibles en lugar de IDs numéricos para todos los formatos
            if ($col === 'category_id' && !empty($row['category_name'])) {
                $mapped[$col] = $row['category_name'];
                continue;
            }
            if ($col === 'area_id' && !empty($row['area_name'])) {
                $mapped[$col] = $row['area_name'];
                continue;
            }
            if ($col === 'created_by' && !empty($row['created_by_name'])) {
                $mapped[$col] = $row['created_by_name'];
                continue;
            }
            if ($col === 'approved_by' && !empty($row['approved_by_name'])) {
                $mapped[$col] = $row['approved_by_name'];
                continue;
            }
            if ($col === 'source_account_id') {
                $mapped[$col] = !empty($row['source_account_name']) ? $row['source_account_name'] : '';
                continue;
            }
            if ($col === 'destination_account_id') {
                $mapped[$col] = !empty($row['destination_account_name']) ? $row['destination_account_name'] : '';
                continue;
            }
            if ($col === 'related_user_id') {
                if (!empty($row['related_user_name'])) {
                    $mapped[$col] = $row['related_user_name'];
                } elseif (!empty($row['related_user_id'])) {
                    $user_obj = get_userdata((int)$row['related_user_id']);
                    $mapped[$col] = $user_obj ? ($user_obj->display_name ?: $user_obj->user_login) : (string)$row['related_user_id'];
                } else {
                    $mapped[$col] = '';
                }
                continue;
            }
            if ($col === 'transaction_type') {
                $type_map = [
                    'income'   => __('Ingreso', 'aura-suite'),
                    'expense'  => __('Gasto', 'aura-suite'),
                    'capital'  => __('Gasto de Capital', 'aura-suite'),
                    'transfer' => __('Transferencia', 'aura-suite'),
                ];
                $val = $row['transaction_type'] ?? '';
                $mapped[$col] = $type_map[$val] ?? ucfirst($val);
                continue;
            }
            if ($col === 'status') {
                $status_map = [
                    'pending'  => __('Pendiente', 'aura-suite'),
                    'approved' => __('Aprobado', 'aura-suite'),
                    'rejected' => __('Rechazado', 'aura-suite'),
                    'draft'    => __('Borrador', 'aura-suite'),
                    'void'     => __('Anulado', 'aura-suite'),
                ];
                $val = $row['status'] ?? '';
                $mapped[$col] = $status_map[$val] ?? ucfirst($val);
                continue;
            }
            if ($col === 'payment_method') {
                $method_map = [
                    'cash'        => __('Efectivo', 'aura-suite'),
                    'transfer'    => __('Transferencia', 'aura-suite'),
                    'check'       => __('Cheque', 'aura-suite'),
                    'card'        => __('Tarjeta', 'aura-suite'),
                    'credit_card' => __('Tarjeta de Crédito', 'aura-suite'),
                    'debit_card'  => __('Tarjeta de Débito', 'aura-suite'),
                    'other'       => __('Otro', 'aura-suite'),
                ];
                $val = $row['payment_method'] ?? '';
                $mapped[$col] = !empty($val) ? ($method_map[$val] ?? ucfirst(str_replace('_', ' ', $val))) : '';
                continue;
            }
            if ($col === 'related_user_concept') {
                $concepts_map = [
                    'salary'                => __('Pago de salario/nómina', 'aura-suite'),
                    'payment_to_user'       => __('Pago realizado a un usuario', 'aura-suite'),
                    'charge_to_user'        => __('Cobro realizado a un usuario', 'aura-suite'),
                    'scholarship'           => __('Beca asignada', 'aura-suite'),
                    'loan_payment'          => __('Pago de préstamo', 'aura-suite'),
                    'refund'                => __('Reembolso', 'aura-suite'),
                    'expense_reimbursement' => __('Reembolso de gastos', 'aura-suite'),
                    'unlinked'              => __('Sin vinculación (General)', 'aura-suite'),
                ];
                $val = $row['related_user_concept'] ?? '';
                $mapped[$col] = !empty($val) ? ($concepts_map[$val] ?? ucfirst(str_replace('_', ' ', $val))) : __('Sin vinculación (General)', 'aura-suite');
                continue;
            }
            if ($col === 'deleted_by' && !empty($row['deleted_by_name'])) {
                $mapped[$col] = $row['deleted_by_name'];
                continue;
            }
            if ($col === 'receipt_file') {
                $val = trim((string)($row['receipt_file'] ?? ''));
                if (!empty($val)) {
                    if (str_starts_with($val, '[') || str_starts_with($val, '{')) {
                        $decoded = json_decode($val, true);
                        if (is_array($decoded)) {
                            $urls = [];
                            $items = isset($decoded['url']) ? [$decoded] : $decoded;
                            foreach ($items as $it) {
                                $u = is_array($it) ? ($it['url'] ?? '') : (string)$it;
                                if (!empty($u)) {
                                    if (!preg_match('#^https?://#i', $u)) {
                                        $upload = wp_upload_dir();
                                        $urls[] = trailingslashit($upload['baseurl']) . 'aura-finance/receipts/' . ltrim(basename($u), '/');
                                    } else {
                                        $urls[] = $u;
                                    }
                                }
                            }
                            $mapped[$col] = implode(', ', $urls);
                            continue;
                        }
                    }
                    if (!preg_match('#^https?://#i', $val)) {
                        $upload = wp_upload_dir();
                        $mapped[$col] = trailingslashit($upload['baseurl']) . 'aura-finance/receipts/' . ltrim(basename($val), '/');
                    } else {
                        $mapped[$col] = $val;
                    }
                } else {
                    $mapped[$col] = '';
                }
                continue;
            }
            $mapped[$col]  = $row[$col] ?? '';
        }
        return $mapped;
    }

    /* ------------------------------------------------------------------ */
    /* CSV                                                                   */
    /* ------------------------------------------------------------------ */

    private static function generate_csv(array $rows, array $columns, array $opts): array {
        $delimiter = $opts['delimiter'];
        $labels    = self::all_column_labels('csv');

        ob_start();
        // BOM para compatibilidad con Excel
        echo "\xEF\xBB\xBF";

        // Encabezados
        $headers = array_map(fn($c) => $labels[$c] ?? $c, $columns);
        echo implode($delimiter, array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\r\n";

        // Datos
        foreach ($rows as $row) {
            $mapped = self::map_row($row, $columns, 'csv');
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
        $labels      = self::all_column_labels('excel');

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
            $mapped  = self::map_row($row, $columns, 'excel');
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

        $all_labels = self::all_column_labels('pdf');
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
            $mapped = self::map_row($row, $columns, 'pdf');
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

    /* ------------------------------------------------------------------ */
    /* EMPAQUETADO ZIP CON COMPROBANTES FÍSICOS                           */
    /* ------------------------------------------------------------------ */

    private static function generate_zip_export(array $base_result, array $rows, string $format) {
        self::ensure_export_dir();

        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip_ext', __('La extensión ZipArchive no está habilitada en el servidor.', 'aura-suite'));
        }

        $zip = new ZipArchive();
        $zip_filename = 'transacciones-con-comprobantes-' . date('Y-m-d-His') . '-' . wp_generate_password(6, false) . '.zip';
        $zip_path = self::$export_dir . $zip_filename;

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('zip_open_err', __('No se pudo crear el archivo ZIP temporal.', 'aura-suite'));
        }

        // Agregar el archivo de datos principal
        if (isset($base_result['type']) && $base_result['type'] === 'base64' && !empty($base_result['content'])) {
            $binary = base64_decode($base_result['content']);
            $zip->addFromString($base_result['filename'] ?? ('transacciones.' . $format), $binary);
        } elseif (isset($base_result['type']) && $base_result['type'] === 'url' && !empty($base_result['filename'])) {
            $local_file = self::$export_dir . $base_result['filename'];
            if (file_exists($local_file)) {
                $zip->addFile($local_file, $base_result['filename']);
            }
        }

        $zip->addEmptyDir('comprobantes');

        $upload = wp_upload_dir();
        $base_upload_dir = trailingslashit($upload['basedir']);
        $added_files = [];

        foreach ($rows as $r) {
            $raw_receipt = trim((string)($r['receipt_file'] ?? ''));
            if (empty($raw_receipt)) {
                continue;
            }

            $extracted_names = [];
            if (str_starts_with($raw_receipt, '[') || str_starts_with($raw_receipt, '{')) {
                $dec = json_decode($raw_receipt, true);
                if (is_array($dec)) {
                    $items = isset($dec['url']) ? [$dec] : $dec;
                    foreach ($items as $it) {
                        $u = is_array($it) ? ($it['url'] ?? '') : (string)$it;
                        if (!empty($u)) {
                            $extracted_names[] = basename($u);
                        }
                    }
                }
            } else {
                $parts = array_map('trim', explode(',', $raw_receipt));
                foreach ($parts as $p) {
                    if (!empty($p)) {
                        $extracted_names[] = basename($p);
                    }
                }
            }

            foreach ($extracted_names as $fname) {
                if (empty($fname) || isset($added_files[$fname])) {
                    continue;
                }

                $candidate_paths = [
                    $base_upload_dir . 'aura-finance/receipts/' . $fname,
                    $base_upload_dir . 'aura-receipts/' . $fname,
                    WP_CONTENT_DIR . '/uploads/aura-finance/receipts/' . $fname,
                    WP_CONTENT_DIR . '/uploads/aura-receipts/' . $fname,
                ];

                foreach ($candidate_paths as $cp) {
                    if (file_exists($cp) && is_readable($cp)) {
                        $zip->addFile($cp, 'comprobantes/' . $fname);
                        $added_files[$fname] = true;
                        break;
                    }
                }
            }
        }

        $zip->close();

        return [
            'type'     => 'url',
            'url'      => self::$export_url . $zip_filename,
            'filename' => $zip_filename,
            'mime'     => 'application/zip',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* EXPORTAR ÁREAS Y PROGRAMAS (CSV)                                   */
    /* ------------------------------------------------------------------ */

    public static function ajax_export_areas() {
        if (!current_user_can('aura_areas_manage') && !current_user_can('manage_options') && !current_user_can('aura_finance_view_all')) {
            wp_send_json_error(['message' => __('Sin permisos para exportar áreas.', 'aura-suite')]);
        }

        global $wpdb;
        $table_areas = $wpdb->prefix . 'aura_areas';

        $sql = "SELECT 
                    a.name,
                    a.slug,
                    a.type,
                    p.slug AS parent_area_slug,
                    a.color,
                    a.icon,
                    a.description,
                    a.status,
                    a.sort_order,
                    u.user_login AS responsible_user
                FROM {$table_areas} a
                LEFT JOIN {$table_areas} p ON a.parent_area_id = p.id
                LEFT JOIN {$wpdb->users} u ON a.responsible_user_id = u.ID
                ORDER BY a.sort_order ASC, a.name ASC";

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)) {
            wp_send_json_error(['message' => __('No hay áreas registradas para exportar.', 'aura-suite')]);
        }

        $columns = ['name', 'slug', 'type', 'parent_area_slug', 'color', 'icon', 'description', 'status', 'sort_order', 'responsible_user'];

        ob_start();
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo implode(',', $columns) . "\r\n";
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $val = $row[$col] ?? '';
                $line[] = '"' . str_replace('"', '""', (string)$val) . '"';
            }
            echo implode(',', $line) . "\r\n";
        }
        $content = ob_get_clean();

        wp_send_json_success([
            'type'     => 'base64',
            'content'  => base64_encode($content),
            'filename' => 'areas-y-programas-' . date('Y-m-d-His') . '.csv',
            'mime'     => 'text/csv;charset=utf-8',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* EXPORTAR DIRECTORIO DE TERCEROS (CSV)                              */
    /* ------------------------------------------------------------------ */

    public static function ajax_export_third_parties() {
        if (!current_user_can('aura_third_parties_view') && !current_user_can('aura_finance_view_all') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos para exportar terceros.', 'aura-suite')]);
        }

        global $wpdb;
        $table_tp = $wpdb->prefix . 'aura_finance_third_parties';

        $sql = "SELECT 
                    full_name,
                    party_type,
                    accounting_role,
                    commercial_name,
                    document_id,
                    tax_id_type,
                    phone,
                    email,
                    website,
                    address,
                    notes,
                    is_active
                FROM {$table_tp}
                ORDER BY full_name ASC";

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)) {
            wp_send_json_error(['message' => __('No hay terceros registrados para exportar.', 'aura-suite')]);
        }

        $columns = ['full_name', 'party_type', 'accounting_role', 'commercial_name', 'document_id', 'tax_id_type', 'phone', 'email', 'website', 'address', 'notes', 'is_active'];

        ob_start();
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo implode(',', $columns) . "\r\n";
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $val = $row[$col] ?? '';
                $line[] = '"' . str_replace('"', '""', (string)$val) . '"';
            }
            echo implode(',', $line) . "\r\n";
        }
        $content = ob_get_clean();

        wp_send_json_success([
            'type'     => 'base64',
            'content'  => base64_encode($content),
            'filename' => 'directorio-terceros-' . date('Y-m-d-His') . '.csv',
            'mime'     => 'text/csv;charset=utf-8',
        ]);
    }
}
