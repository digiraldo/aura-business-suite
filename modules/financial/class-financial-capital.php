<?php
/**
 * Módulo: Gastos de Capital (CapEx)
 *
 * Gestiona los gastos de capital que son financiados por donaciones externas
 * o agencias patrocinadores. Estos gastos se registran con transaction_type='capital'
 * y NO deben afectar los porcentajes de gasto operacional mensual.
 *
 * Estándar contable aplicado: Separación CapEx vs OpEx
 *
 * @package    Aura_Business_Suite
 * @subpackage Financial
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Capital {

    private static $table_tx;
    private static $table_cat;

    public static function init(): void {
        global $wpdb;
        self::$table_tx  = $wpdb->prefix . 'aura_finance_transactions';
        self::$table_cat = $wpdb->prefix . 'aura_finance_categories';

        // AJAX endpoints
        add_action( 'wp_ajax_aura_get_capital_expenses',   [ __CLASS__, 'ajax_get_capital_expenses' ] );
        add_action( 'wp_ajax_aura_get_capital_stats',      [ __CLASS__, 'ajax_get_capital_stats' ] );
        add_action( 'wp_ajax_aura_get_capital_categories', [ __CLASS__, 'ajax_get_capital_categories' ] );

        // Enqueue
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

        // Asegurar categoría predeterminada
        add_action( 'admin_init', [ __CLASS__, 'ensure_default_capital_category' ] );
    }

    /* ─────────────────────────────────────────
     * Scripts
     * ───────────────────────────────────────── */
    public static function enqueue_scripts( string $hook ): void {
        if ( strpos( $hook, 'aura-capital-expenses' ) === false ) return;

        wp_enqueue_style(  'aura-financial-categories' );
        wp_enqueue_script( 'aura-capital-expenses',
            AURA_PLUGIN_URL . 'assets/js/capital-expenses.js',
            [ 'jquery' ], AURA_VERSION, true
        );
        wp_localize_script( 'aura-capital-expenses', 'auraCapital', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aura_capital_nonce' ),
            'strings' => [
                'loading'     => __( 'Cargando...', 'aura-suite' ),
                'noResults'   => __( 'No hay gastos de capital registrados.', 'aura-suite' ),
                'error'       => __( 'Error al cargar los datos.', 'aura-suite' ),
            ],
        ] );
    }

    /* ─────────────────────────────────────────
     * Categoría predeterminada CapEx
     * ───────────────────────────────────────── */
    public static function ensure_default_capital_category(): void {
        if ( get_option( 'aura_finance_capital_category_created_v1' ) ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        $exists = $wpdb->get_var( "SELECT id FROM {$table} WHERE slug = 'gastos-de-capital' LIMIT 1" );
        if ( ! $exists ) {
            $wpdb->insert( $table, [
                'name'          => 'Gastos de Capital',
                'slug'          => 'gastos-de-capital',
                'type'          => 'capital',
                'color'         => '#e67e22',
                'icon'          => 'dashicons-building',
                'description'   => 'Adquisiciones financiadas por donaciones externas o agencias. No afectan el presupuesto operacional mensual.',
                'is_active'     => 1,
                'display_order' => 999,
                'created_by'    => 1,
            ], [ '%s','%s','%s','%s','%s','%s','%d','%d','%d' ] );
        }

        update_option( 'aura_finance_capital_category_created_v1', '1.1' );
    }

    /* ─────────────────────────────────────────
     * AJAX: Listar gastos de capital
     * ───────────────────────────────────────── */
    public static function ajax_get_capital_expenses(): void {
        check_ajax_referer( 'aura_capital_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_view_own' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;

        $year     = intval( $_POST['year']        ?? date( 'Y' ) );
        $month    = intval( $_POST['month']       ?? 0 );
        $cat_id   = intval( $_POST['category_id'] ?? 0 );
        $status   = sanitize_key( $_POST['status'] ?? '' );
        $search   = sanitize_text_field( $_POST['search'] ?? '' );
        $page     = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        $where = [
            "t.deleted_at IS NULL",
            "t.transaction_type = 'capital'",
        ];

        if ( $year > 0 ) {
            $where[] = $wpdb->prepare( 'YEAR(t.transaction_date) = %d', $year );
        }
        if ( $month > 0 ) {
            $where[] = $wpdb->prepare( 'MONTH(t.transaction_date) = %d', $month );
        }
        if ( $cat_id > 0 ) {
            $where[] = $wpdb->prepare( 't.category_id = %d', $cat_id );
        }
        if ( $status ) {
            $where[] = $wpdb->prepare( 't.status = %s', $status );
        }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = $wpdb->prepare( '(t.description LIKE %s OR t.notes LIKE %s)', $like, $like );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::$table_tx . " t {$where_sql}"
        );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                t.id,
                t.transaction_date,
                t.amount,
                t.description,
                t.notes,
                t.status,
                t.payment_method,
                t.reference_number,
                t.receipt_file,
                c.name  AS category_name,
                c.color AS category_color,
                c.icon  AS category_icon
             FROM " . self::$table_tx . " t
             LEFT JOIN " . self::$table_cat . " c ON c.id = t.category_id
             {$where_sql}
             ORDER BY t.transaction_date DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        wp_send_json_success( [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'pages'    => ceil( $total / $per_page ),
            'per_page' => $per_page,
        ] );
    }

    /* ─────────────────────────────────────────
     * AJAX: Estadísticas CapEx
     * ───────────────────────────────────────── */
    public static function ajax_get_capital_stats(): void {
        check_ajax_referer( 'aura_capital_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_view_own' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $year = intval( $_POST['year'] ?? date( 'Y' ) );

        // Total del año
        $total_year = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM " . self::$table_tx .
            " WHERE transaction_type='capital' AND deleted_at IS NULL AND YEAR(transaction_date)=%d",
            $year
        ) );

        // Total por mes
        $by_month = $wpdb->get_results( $wpdb->prepare(
            "SELECT MONTH(transaction_date) AS month, COALESCE(SUM(amount),0) AS total
             FROM " . self::$table_tx .
            " WHERE transaction_type='capital' AND deleted_at IS NULL AND YEAR(transaction_date)=%d
             GROUP BY MONTH(transaction_date) ORDER BY month",
            $year
        ), ARRAY_A );

        // Total por categoría
        $by_category = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.name, c.color, c.icon, COALESCE(SUM(t.amount),0) AS total, COUNT(*) AS count
             FROM " . self::$table_tx . " t
             LEFT JOIN " . self::$table_cat . " c ON c.id = t.category_id
             WHERE t.transaction_type='capital' AND t.deleted_at IS NULL AND YEAR(t.transaction_date)=%d
             GROUP BY c.id ORDER BY total DESC",
            $year
        ), ARRAY_A );

        // Total transacciones pendientes
        $pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_tx .
            " WHERE transaction_type='capital' AND deleted_at IS NULL AND status='pending' AND YEAR(transaction_date)=%d",
            $year
        ) );

        wp_send_json_success( [
            'total_year'  => $total_year,
            'by_month'    => $by_month,
            'by_category' => $by_category,
            'pending'     => $pending,
            'year'        => $year,
        ] );
    }

    /* ─────────────────────────────────────────
     * AJAX: Categorías disponibles para CapEx
     * ───────────────────────────────────────── */
    public static function ajax_get_capital_categories(): void {
        check_ajax_referer( 'aura_capital_nonce', 'nonce' );

        global $wpdb;
        $cats = $wpdb->get_results(
            "SELECT id, name, color, icon FROM " . self::$table_cat .
            " WHERE (is_capex = 1 OR type = 'capital' OR type = 'both') AND is_active = 1 ORDER BY display_order, name"
        );
        wp_send_json_success( [ 'categories' => $cats ] );
    }

    /* ─────────────────────────────────────────
     * Helper: Total CapEx de un período (para dashboard)
     * ───────────────────────────────────────── */
    public static function get_period_total( string $start, string $end ): float {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM " . $wpdb->prefix . "aura_finance_transactions
             WHERE transaction_type='capital' AND deleted_at IS NULL
             AND transaction_date BETWEEN %s AND %s",
            $start, $end
        ) );
    }
}

Aura_Financial_Capital::init();
