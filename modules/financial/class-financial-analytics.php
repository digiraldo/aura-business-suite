<?php
/**
 * Análisis Visual Financiero - Fase 3, Item 3.3
 *
 * Provee todos los endpoints AJAX para los 5 tabs de análisis visual:
 * 1. Tendencias Temporales
 * 2. Distribución por Categorías
 * 3. Comparaciones de Períodos
 * 4. Análisis de Patrones
 * 5. Presupuesto vs Realidad
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aura_Financial_Analytics {

    /** @var string Tabla de transacciones */
    private static $table;

    /** @var string Tabla de categorías */
    private static $cat_table;

    /** @var string Tabla de anotaciones */
    private static $ann_table;

    /**
     * Inicializar hooks AJAX
     */
    public static function init() {
        global $wpdb;
        self::$table     = $wpdb->prefix . 'aura_finance_transactions';
        self::$cat_table = $wpdb->prefix . 'aura_finance_categories';
        self::$ann_table = $wpdb->prefix . 'aura_finance_chart_annotations';

        // Asegurar que la tabla de anotaciones exista
        add_action('admin_init', array(__CLASS__, 'maybe_create_annotations_table'));

        // AJAX endpoints
        $actions = array(
            'aura_analytics_trends'           => 'ajax_trends',
            'aura_analytics_categories'       => 'ajax_categories',
            'aura_analytics_comparison'       => 'ajax_comparison',
            'aura_analytics_patterns'         => 'ajax_patterns',
            'aura_analytics_budget'           => 'ajax_budget',
            'aura_analytics_budget_save'      => 'ajax_save_budgets',
            'aura_analytics_annotation_save'   => 'ajax_save_annotation',
            'aura_analytics_annotation_list'   => 'ajax_list_annotations',
            'aura_analytics_annotation_delete' => 'ajax_delete_annotation',
        );

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, array(__CLASS__, $method));
        }
    }

    /* ------------------------------------------------------------------ */
    /* TABLA DE ANOTACIONES                                                 */
    /* ------------------------------------------------------------------ */

    public static function maybe_create_annotations_table() {
        global $wpdb;
        $option_key = 'aura_analytics_annotations_table_v1';
        if (get_option($option_key)) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $table = self::$ann_table;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chart_tab VARCHAR(50) NOT NULL,
            annotation_date DATE NOT NULL,
            note TEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tab (chart_tab),
            INDEX idx_date (annotation_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option($option_key, true);
    }

    /* ------------------------------------------------------------------ */
    /* HELPERS                                                              */
    /* ------------------------------------------------------------------ */

    private static function check_nonce() {
        check_ajax_referer('aura_analytics_nonce', 'nonce');
    }

    private static function can_view(): bool {
        return current_user_can('aura_finance_view_own')
            || current_user_can('aura_finance_view_all')
            || current_user_can('manage_options');
    }

    private static function visibility_where(): string {
        global $wpdb;
        if (current_user_can('aura_finance_view_all') || current_user_can('manage_options')) {
            return '';
        }
        // Solo propias
        return $wpdb->prepare(' AND t.created_by = %d', get_current_user_id());
    }

    /**
     * Parsea period string 'YYYY-MM' o 'YYYY-QN' o 'YYYY' a rango de fechas
     */
    private static function period_to_range(string $period): array {
        if (preg_match('/^(\d{4})-Q(\d)$/', $period, $m)) {
            $q     = (int) $m[2];
            $month = ($q - 1) * 3 + 1;
            $start = sprintf('%s-%02d-01', $m[1], $month);
            $end   = date('Y-m-t', strtotime(sprintf('%s-%02d-01', $m[1], $month + 2)));
            return [$start, $end];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            $start = $period . '-01';
            $end   = date('Y-m-t', strtotime($start));
            return [$start, $end];
        }
        if (preg_match('/^(\d{4})$/', $period, $m)) {
            return [$m[1] . '-01-01', $m[1] . '-12-31'];
        }
        return [date('Y-m-01'), date('Y-m-t')];
    }

    /**
     * Formatos de agrupación por granularidad
     */
    private static function granularity_format(string $gran): string {
        $map = [
            'day'     => '%Y-%m-%d',
            'week'    => '%x-W%v',
            'month'   => '%Y-%m',
            'quarter' => '',          // se maneja aparte
            'year'    => '%Y',
        ];
        return $map[$gran] ?? '%Y-%m';
    }

    /* ------------------------------------------------------------------ */
    /* TAB 1 — TENDENCIAS TEMPORALES                                        */
    /* ------------------------------------------------------------------ */

    public static function ajax_trends() {
        self::check_nonce();
        if (!self::can_view()) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $start       = sanitize_text_field($_POST['start_date'] ?? date('Y-01-01'));
        $end         = sanitize_text_field($_POST['end_date']   ?? date('Y-m-t'));
        $granularity = sanitize_text_field($_POST['granularity'] ?? 'month');
        $vis         = self::visibility_where();

        if ($granularity === 'quarter') {
            // Agrupar por trimestre manualmente
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT transaction_type,
                        YEAR(transaction_date) AS yr,
                        QUARTER(transaction_date) AS qt,
                        SUM(amount) AS total,
                        COUNT(*) AS count
                 FROM " . self::$table . " t
                 WHERE t.status = 'approved'
                   AND t.deleted_at IS NULL
                   AND t.transaction_date BETWEEN %s AND %s
                   {$vis}
                 GROUP BY yr, qt, transaction_type
                 ORDER BY yr, qt",
                $start, $end
            ), ARRAY_A);

            $buckets = [];
            foreach ($rows as $row) {
                $key = $row['yr'] . '-Q' . $row['qt'];
                if (!isset($buckets[$key])) {
                    $buckets[$key] = ['label' => $key, 'income' => 0, 'expense' => 0, 'count' => 0];
                }
                $buckets[$key][$row['transaction_type']] += (float) $row['total'];
                $buckets[$key]['count'] += (int) $row['count'];
            }
        } else {
            $fmt  = self::granularity_format($granularity);
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT transaction_type,
                        DATE_FORMAT(transaction_date, %s) AS period_label,
                        SUM(amount) AS total,
                        COUNT(*) AS count
                 FROM " . self::$table . " t
                 WHERE t.status = 'approved'
                   AND t.deleted_at IS NULL
                   AND t.transaction_date BETWEEN %s AND %s
                   {$vis}
                 GROUP BY period_label, transaction_type
                 ORDER BY period_label",
                $fmt, $start, $end
            ), ARRAY_A);

            $buckets = [];
            foreach ($rows as $row) {
                $key = $row['period_label'];
                if (!isset($buckets[$key])) {
                    $buckets[$key] = ['label' => $key, 'income' => 0, 'expense' => 0, 'count' => 0];
                }
                $buckets[$key][$row['transaction_type']] += (float) $row['total'];
                $buckets[$key]['count'] += (int) $row['count'];
            }
        }

        $labels   = [];
        $incomes  = [];
        $expenses = [];
        $balances = [];
        $counts   = [];

        foreach ($buckets as $b) {
            $labels[]   = $b['label'];
            $incomes[]  = round($b['income'], 2);
            $expenses[] = round($b['expense'], 2);
            $balances[] = round($b['income'] - $b['expense'], 2);
            $counts[]   = $b['count'];
        }

        // Anotaciones para este tab
        $annotations = self::get_annotations_for_tab('trends', $start, $end);

        // Proyección: regresión lineal simple sobre el balance
        $projection = self::linear_projection($balances, 3);

        wp_send_json_success([
            'labels'      => $labels,
            'income'      => $incomes,
            'expense'     => $expenses,
            'balance'     => $balances,
            'counts'      => $counts,
            'annotations' => $annotations,
            'projection'  => $projection,
        ]);
    }

    /**
     * Regresión lineal: devuelve N periodos adicionales proyectados
     */
    private static function linear_projection(array $values, int $n): array {
        $count = count($values);
        if ($count < 2) {
            return [];
        }
        $x_mean = ($count - 1) / 2;
        $y_mean = array_sum($values) / $count;
        $num = 0;
        $den = 0;
        foreach ($values as $i => $v) {
            $num += ($i - $x_mean) * ($v - $y_mean);
            $den += ($i - $x_mean) ** 2;
        }
        $slope     = $den != 0 ? $num / $den : 0;
        $intercept = $y_mean - $slope * $x_mean;

        $projection = [];
        for ($i = 1; $i <= $n; $i++) {
            $projection[] = round($intercept + $slope * ($count - 1 + $i), 2);
        }
        return $projection;
    }

    /* ------------------------------------------------------------------ */
    /* TAB 2 — DISTRIBUCIÓN POR CATEGORÍAS                                  */
    /* ------------------------------------------------------------------ */

    public static function ajax_categories() {
        self::check_nonce();
        if (!self::can_view()) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $start = sanitize_text_field($_POST['start_date']   ?? date('Y-01-01'));
        $end   = sanitize_text_field($_POST['end_date']     ?? date('Y-m-t'));
        $type  = sanitize_text_field($_POST['type']         ?? 'both');   // income|expense|both
        $sort  = sanitize_text_field($_POST['sort']         ?? 'amount'); // amount|frequency|alpha
        $limit = max(1, min(20, intval($_POST['limit'] ?? 10)));
        $vis   = self::visibility_where();

        $type_where = '';
        if ($type === 'income' || $type === 'expense') {
            $type_where = $wpdb->prepare(' AND t.transaction_type = %s', $type);
        }

        $order_sql = match($sort) {
            'frequency' => 'tx_count DESC',
            'alpha'     => 'c.name ASC',
            default     => 'total DESC',
        };

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.name, c.color, c.icon, c.parent_id,
                    t.transaction_type,
                    SUM(t.amount)  AS total,
                    COUNT(t.id)    AS tx_count
             FROM " . self::$table . " t
             JOIN " . self::$cat_table . " c ON t.category_id = c.id
             WHERE t.status = 'approved'
               AND t.deleted_at IS NULL
               AND t.transaction_date BETWEEN %s AND %s
               {$type_where}
               {$vis}
             GROUP BY c.id, t.transaction_type
             ORDER BY {$order_sql}
             LIMIT %d",
            $start, $end, $limit
        ), ARRAY_A);

        // Agrupar income + expense por categoría si type=both
        $cats = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            if (!isset($cats[$id])) {
                $cats[$id] = [
                    'id'       => $id,
                    'name'     => $row['name'],
                    'color'    => $row['color'] ?: '#3498db',
                    'icon'     => $row['icon'] ?: 'dashicons-category',
                    'parent'   => $row['parent_id'],
                    'income'   => 0,
                    'expense'  => 0,
                    'count'    => 0,
                ];
            }
            $cats[$id][$row['transaction_type']] += (float) $row['total'];
            $cats[$id]['count'] += (int) $row['tx_count'];
        }

        // Ordenar final
        $list = array_values($cats);
        usort($list, function($a, $b) use ($sort, $type) {
            if ($sort === 'frequency') return $b['count'] - $a['count'];
            if ($sort === 'alpha') return strcmp($a['name'], $b['name']);
            $ta = $type === 'expense' ? $a['expense'] : $a['income'] + $a['expense'];
            $tb = $type === 'expense' ? $b['expense'] : $b['income'] + $b['expense'];
            return $tb <=> $ta;
        });

        wp_send_json_success(['categories' => $list]);
    }

    /* ------------------------------------------------------------------ */
    /* TAB 3 — COMPARACIONES                                                */
    /* ------------------------------------------------------------------ */

    public static function ajax_comparison() {
        self::check_nonce();
        if (!self::can_view()) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $a_start = sanitize_text_field($_POST['a_start'] ?? date('Y-01-01'));
        $a_end   = sanitize_text_field($_POST['a_end']   ?? date('Y-06-30'));
        $b_start = sanitize_text_field($_POST['b_start'] ?? date('Y-07-01'));
        $b_end   = sanitize_text_field($_POST['b_end']   ?? date('Y-m-t'));
        $vis     = self::visibility_where();

        $fetch = function(string $s, string $e) use ($wpdb, $vis): array {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT c.name AS cat, t.transaction_type,
                        SUM(t.amount) AS total, COUNT(t.id) AS cnt
                 FROM " . self::$table . " t
                 JOIN " . self::$cat_table . " c ON t.category_id = c.id
                 WHERE t.status = 'approved'
                   AND t.deleted_at IS NULL
                   AND t.transaction_date BETWEEN %s AND %s
                   {$vis}
                 GROUP BY c.id, t.transaction_type
                 ORDER BY total DESC",
                $s, $e
            ), ARRAY_A);

            $result = [];
            foreach ($rows as $row) {
                $cat = $row['cat'];
                if (!isset($result[$cat])) {
                    $result[$cat] = ['income' => 0, 'expense' => 0, 'count' => 0];
                }
                $result[$cat][$row['transaction_type']] += (float) $row['total'];
                $result[$cat]['count'] += (int) $row['cnt'];
            }
            return $result;
        };

        $period_a = $fetch($a_start, $a_end);
        $period_b = $fetch($b_start, $b_end);

        // Unir categorías de ambos períodos
        $all_cats = array_unique(array_merge(array_keys($period_a), array_keys($period_b)));
        sort($all_cats);

        $diff_rows = [];
        foreach ($all_cats as $cat) {
            $a_total = ($period_a[$cat]['income'] ?? 0) - ($period_a[$cat]['expense'] ?? 0);
            $b_total = ($period_b[$cat]['income'] ?? 0) - ($period_b[$cat]['expense'] ?? 0);
            $abs_diff = $b_total - $a_total;
            $pct_diff = $a_total != 0 ? round($abs_diff / abs($a_total) * 100, 1) : null;

            $diff_rows[] = [
                'category'  => $cat,
                'a_income'  => round($period_a[$cat]['income']  ?? 0, 2),
                'a_expense' => round($period_a[$cat]['expense'] ?? 0, 2),
                'b_income'  => round($period_b[$cat]['income']  ?? 0, 2),
                'b_expense' => round($period_b[$cat]['expense'] ?? 0, 2),
                'abs_diff'  => round($abs_diff, 2),
                'pct_diff'  => $pct_diff,
            ];
        }

        // Ordenar por mayor variación absoluta
        usort($diff_rows, fn($x, $y) => abs($y['abs_diff']) <=> abs($x['abs_diff']));

        // Totales de período
        $sum_a_income  = array_sum(array_column(array_values($period_a), 'income'));
        $sum_a_expense = array_sum(array_column(array_values($period_a), 'expense'));
        $sum_b_income  = array_sum(array_column(array_values($period_b), 'income'));
        $sum_b_expense = array_sum(array_column(array_values($period_b), 'expense'));

        wp_send_json_success([
            'categories' => array_slice($diff_rows, 0, 20),
            'totals' => [
                'a' => ['income' => round($sum_a_income, 2), 'expense' => round($sum_a_expense, 2)],
                'b' => ['income' => round($sum_b_income, 2), 'expense' => round($sum_b_expense, 2)],
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* TAB 4 — ANÁLISIS DE PATRONES                                         */
    /* ------------------------------------------------------------------ */

    public static function ajax_patterns() {
        self::check_nonce();
        if (!self::can_view()) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $start = sanitize_text_field($_POST['start_date'] ?? date('Y-01-01'));
        $end   = sanitize_text_field($_POST['end_date']   ?? date('Y-m-t'));
        $vis   = self::visibility_where();

        // Heatmap: día de la semana (1=Lun..7=Dom) × semana del mes (1-5)
        $heatmap_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DAYOFWEEK(transaction_date) AS dow,
                    WEEK(transaction_date, 3) AS wk,
                    COUNT(*) AS cnt,
                    SUM(amount) AS total
             FROM " . self::$table . " t
             WHERE t.status = 'approved'
               AND t.deleted_at IS NULL
               AND t.transaction_date BETWEEN %s AND %s
               {$vis}
             GROUP BY dow, wk",
            $start, $end
        ), ARRAY_A);

        // Convertir a matriz [dow][count] / [dow][total]
        // dow: 1=Dom,2=Lun...7=Sab en MySQL → normalizar a Lun=0..Dom=6
        $heatmap = array_fill(0, 7, 0);
        $heatmap_amount = array_fill(0, 7, 0);
        foreach ($heatmap_rows as $row) {
            $dow = ((int)$row['dow'] + 5) % 7; // MySQL 1=Dom → 0=Lun
            $heatmap[$dow] += (int) $row['cnt'];
            $heatmap_amount[$dow] += (float) $row['total'];
        }

        // Scatter: categoría → transacciones (frecuencia vs monto promedio)
        $scatter = $wpdb->get_results($wpdb->prepare(
            "SELECT c.name AS cat, c.color,
                    COUNT(t.id) AS freq,
                    AVG(t.amount) AS avg_amount,
                    SUM(t.amount) AS total
             FROM " . self::$table . " t
             JOIN " . self::$cat_table . " c ON t.category_id = c.id
             WHERE t.status = 'approved'
               AND t.deleted_at IS NULL
               AND t.transaction_date BETWEEN %s AND %s
               {$vis}
             GROUP BY c.id",
            $start, $end
        ), ARRAY_A);

        // Outliers: transacciones cuyo monto es > media + 2*desvstd
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(amount) AS mean_val, STD(amount) AS std_val
             FROM " . self::$table . " t
             WHERE t.status = 'approved'
               AND t.deleted_at IS NULL
               AND t.transaction_date BETWEEN %s AND %s
               {$vis}",
            $start, $end
        ), ARRAY_A);

        $outliers = [];
        if ($stats && $stats['mean_val']) {
            $threshold = (float)$stats['mean_val'] + 2 * (float)$stats['std_val'];
            $outliers = $wpdb->get_results($wpdb->prepare(
                "SELECT t.id, t.transaction_date, t.description,
                        t.amount, t.transaction_type, c.name AS cat
                 FROM " . self::$table . " t
                 LEFT JOIN " . self::$cat_table . " c ON t.category_id = c.id
                 WHERE t.status = 'approved'
                   AND t.deleted_at IS NULL
                   AND t.amount > %f
                   AND t.transaction_date BETWEEN %s AND %s
                   {$vis}
                 ORDER BY t.amount DESC
                 LIMIT 10",
                $threshold, $start, $end
            ), ARRAY_A);
        }

        wp_send_json_success([
            'heatmap'       => $heatmap,
            'heatmap_amount'=> array_map(fn($v) => round($v, 2), $heatmap_amount),
            'scatter'       => $scatter,
            'outliers'      => $outliers,
            'dow_labels'    => ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* TAB 5 — PRESUPUESTO VS REALIDAD                                      */
    /* ------------------------------------------------------------------ */

    public static function ajax_budget() {
        self::check_nonce();
        if (!self::can_view()) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $year  = intval($_POST['year']  ?? date('Y'));
        $month = intval($_POST['month'] ?? date('n'));
        $vis   = self::visibility_where();

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        $today = date('Y-m-d');
        $days_in_month   = (int) date('t', strtotime($start));
        $days_elapsed    = min($days_in_month, max(1, (int) ((strtotime(min($today, $end)) - strtotime($start)) / 86400) + 1));

        // Obtener presupuestos guardados en opciones de WP
        $budgets = get_option('aura_finance_budgets_' . $year . '_' . $month, []);

        // Gasto real por categoría
        $real_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.name, c.color, t.transaction_type,
                    SUM(t.amount) AS total
             FROM " . self::$table . " t
             JOIN " . self::$cat_table . " c ON t.category_id = c.id
             WHERE t.status = 'approved'
               AND t.deleted_at IS NULL
               AND t.transaction_date BETWEEN %s AND %s
               {$vis}
             GROUP BY c.id, t.transaction_type",
            $start, $end
        ), ARRAY_A);

        $real = [];
        foreach ($real_rows as $row) {
            $id = $row['id'];
            if (!isset($real[$id])) {
                $real[$id] = ['id' => $id, 'name' => $row['name'], 'color' => $row['color'], 'income' => 0, 'expense' => 0];
            }
            $real[$id][$row['transaction_type']] += (float) $row['total'];
        }

        // Combinar con presupuestos
        $result = [];
        $all_ids = array_unique(array_merge(array_keys($real), array_keys($budgets)));

        foreach ($all_ids as $id) {
            if (!isset($real[$id]) && !isset($budgets[$id])) {
                continue;
            }
            $name   = $real[$id]['name'] ?? ($budgets[$id]['name'] ?? 'Categoría ' . $id);
            $color  = $real[$id]['color'] ?? '#3498db';
            $budget = (float) ($budgets[$id]['amount'] ?? 0);
            $actual = (float) (($real[$id]['expense'] ?? 0) + ($real[$id]['income'] ?? 0));

            $pct = $budget > 0 ? round($actual / $budget * 100, 1) : null;

            // Proyección al fin de mes
            $projection = $days_elapsed > 0
                ? round($actual / $days_elapsed * $days_in_month, 2)
                : $actual;

            $result[] = [
                'id'         => $id,
                'name'       => $name,
                'color'      => $color,
                'budget'     => $budget,
                'actual'     => round($actual, 2),
                'pct'        => $pct,
                'projection' => $projection,
                'over'       => $budget > 0 && $actual > $budget,
            ];
        }

        // Ordenar por % de ejecución desc
        usort($result, fn($a, $b) => ($b['pct'] ?? 0) <=> ($a['pct'] ?? 0));

        wp_send_json_success([
            'items'          => $result,
            'year'           => $year,
            'month'          => $month,
            'days_in_month'  => $days_in_month,
            'days_elapsed'   => $days_elapsed,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* GUARDAR PRESUPUESTOS                                                 */
    /* ------------------------------------------------------------------ */

    public static function ajax_save_budgets() {
        self::check_nonce();
        if (!current_user_can('manage_options') && !current_user_can('aura_finance_view_all')) {
            wp_send_json_error(['message' => __('Sin permisos para guardar presupuestos.', 'aura-suite')]);
        }

        $year    = intval($_POST['year']    ?? date('Y'));
        $month   = intval($_POST['month']  ?? date('n'));
        $raw     = stripslashes($_POST['budgets'] ?? '{}');
        $budgets = json_decode($raw, true);

        if (!is_array($budgets)) {
            wp_send_json_error(['message' => __('Datos inválidos.', 'aura-suite')]);
        }

        // Sanitizar: sólo números positivos
        $clean = [];
        foreach ($budgets as $id => $data) {
            $id = intval($id);
            if ($id < 1) continue;
            $clean[$id] = [
                'amount' => max(0, (float) ($data['amount'] ?? 0)),
                'name'   => sanitize_text_field($data['name'] ?? ''),
            ];
        }

        update_option('aura_finance_budgets_' . $year . '_' . $month, $clean);
        wp_send_json_success(['message' => __('Presupuestos guardados.', 'aura-suite')]);
    }

    /* ------------------------------------------------------------------ */
    /* ANOTACIONES                                                           */
    /* ------------------------------------------------------------------ */

    private static function get_annotations_for_tab(string $tab, string $start, string $end): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, annotation_date, note, created_by FROM " . self::$ann_table . "
             WHERE chart_tab = %s AND annotation_date BETWEEN %s AND %s
             ORDER BY annotation_date",
            $tab, $start, $end
        ), ARRAY_A) ?: [];
    }

    public static function ajax_save_annotation() {
        self::check_nonce();
        if (!current_user_can('aura_finance_view_own') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $tab  = sanitize_text_field($_POST['tab']  ?? '');
        $date = sanitize_text_field($_POST['date'] ?? '');
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$tab || !$date || strlen($note) < 3) {
            wp_send_json_error(['message' => __('Datos incompletos', 'aura-suite')]);
        }

        $res = $wpdb->insert(
            self::$ann_table,
            ['chart_tab' => $tab, 'annotation_date' => $date, 'note' => $note, 'created_by' => get_current_user_id()],
            ['%s','%s','%s','%d']
        );

        if ($res === false) {
            wp_send_json_error(['message' => __('Error al guardar', 'aura-suite')]);
        }

        wp_send_json_success(['id' => $wpdb->insert_id, 'message' => __('Anotación guardada', 'aura-suite')]);
    }

    public static function ajax_list_annotations() {
        self::check_nonce();
        if (!self::can_view()) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        global $wpdb;
        $tab   = sanitize_text_field($_POST['tab']   ?? '');
        $start = sanitize_text_field($_POST['start'] ?? date('Y-01-01'));
        $end   = sanitize_text_field($_POST['end']   ?? date('Y-m-t'));

        $rows = self::get_annotations_for_tab($tab, $start, $end);
        wp_send_json_success(['annotations' => $rows]);
    }

    public static function ajax_delete_annotation() {
        self::check_nonce();
        global $wpdb;
        $id  = intval($_POST['annotation_id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::$ann_table . " WHERE id = %d", $id), ARRAY_A);

        if (!$row) {
            wp_send_json_error(['message' => __('Anotación no encontrada', 'aura-suite')]);
        }

        // Solo admins o el autor pueden borrar
        if ($row['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Sin permisos', 'aura-suite')]);
        }

        $wpdb->delete(self::$ann_table, ['id' => $id], ['%d']);
        wp_send_json_success(['message' => __('Anotación eliminada', 'aura-suite')]);
    }

    /* ------------------------------------------------------------------ */
    /* RENDER — Página "Análisis Visual"                                    */
    /* ------------------------------------------------------------------ */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/visual-analytics.php';
    }
}
