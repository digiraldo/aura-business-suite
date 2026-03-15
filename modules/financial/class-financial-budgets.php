<?php
/**
 * Clase: Sistema de Presupuestos por Área
 * Fase 5, Item 5.1 — Refactorizado Fase 8.3+ (presupuesto por área, categoría como referencia opcional)
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Budgets {

    /* ----------------------------------------------------------------
     * Bootstrap
     * -------------------------------------------------------------- */

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_upgrade_table' ] );

        // AJAX
        add_action( 'wp_ajax_aura_get_budgets',         [ __CLASS__, 'ajax_get_budgets' ] );
        add_action( 'wp_ajax_aura_save_budget',         [ __CLASS__, 'ajax_save_budget' ] );
        add_action( 'wp_ajax_aura_delete_budget',       [ __CLASS__, 'ajax_delete_budget' ] );
        add_action( 'wp_ajax_aura_get_budget_detail',   [ __CLASS__, 'ajax_get_budget_detail' ] );
        add_action( 'wp_ajax_aura_get_budget_progress',           [ __CLASS__, 'ajax_get_budget_progress' ] );
        add_action( 'wp_ajax_aura_adjust_budget',                 [ __CLASS__, 'ajax_adjust_budget' ] );
        add_action( 'wp_ajax_aura_budget_widget_data',            [ __CLASS__, 'ajax_widget_data' ] );
        add_action( 'wp_ajax_aura_get_area_budget_categories',    [ __CLASS__, 'ajax_get_area_budget_categories' ] );
        add_action( 'wp_ajax_aura_budget_category_breakdown',     [ __CLASS__, 'ajax_budget_category_breakdown' ] );

        // Cron diario de alertas
        add_action( 'aura_finance_check_budgets_daily', [ __CLASS__, 'check_budgets_and_alert' ] );
        if ( ! wp_next_scheduled( 'aura_finance_check_budgets_daily' ) ) {
            wp_schedule_event( time(), 'daily', 'aura_finance_check_budgets_daily' );
        }
    }

    /* ----------------------------------------------------------------
     * Actualizar tabla (agregar columnas de alerta si no existen)
     * -------------------------------------------------------------- */

    public static function maybe_upgrade_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'aura_finance_budgets';
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );

        if ( ! in_array( 'alert_on_exceed', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `alert_on_exceed` TINYINT(1) DEFAULT 1 AFTER `alert_threshold`" );
        }
        if ( ! in_array( 'notify_creator', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `notify_creator` TINYINT(1) DEFAULT 1 AFTER `alert_on_exceed`" );
        }
        if ( ! in_array( 'notify_admins', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `notify_admins` TINYINT(1) DEFAULT 1 AFTER `notify_creator`" );
        }
        if ( ! in_array( 'notify_emails', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `notify_emails` TEXT NULL AFTER `notify_admins`" );
        }
        if ( ! in_array( 'alert_sent_threshold', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `alert_sent_threshold` TINYINT(1) DEFAULT 0 AFTER `notify_emails`" );
        }
        if ( ! in_array( 'alert_sent_exceed', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `alert_sent_exceed` TINYINT(1) DEFAULT 0 AFTER `alert_sent_threshold`" );
        }

        // Ampliar ENUM period_type para incluir 'semestral' si no lo tiene aún
        $col = $wpdb->get_row( "SHOW COLUMNS FROM `{$table}` LIKE 'period_type'" );
        if ( $col && strpos( $col->Type, 'semestral' ) === false ) {
            $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN `period_type` ENUM('monthly','quarterly','semestral','yearly') DEFAULT 'monthly'" );
        }

        // Fase 8.3+: Presupuesto por Área — category_id pasa a ser referencia opcional
        $col_cat = $wpdb->get_row( "SHOW COLUMNS FROM `{$table}` LIKE 'category_id'" );
        if ( $col_cat && strpos( $col_cat->Null, 'YES' ) === false ) {
            $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN `category_id` INT(11) NULL DEFAULT NULL" );
        }
    }

    /* ----------------------------------------------------------------
     * Obtener presupuesto activo para una categoría en la fecha actual
     * Usado por Aura_Financial_Settings::is_budget_exceeded()
     * -------------------------------------------------------------- */

    /**
     * Obtener presupuesto activo vigente para un Área.
     * Método principal desde Fase 8.3+ (presupuesto por área).
     *
     * @param int $area_id ID del área.
     */
    public static function get_active_budget_for_area( int $area_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_budgets';
        $today = current_time( 'Y-m-d' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE area_id   = %d
               AND start_date <= %s
               AND end_date   >= %s
               AND is_active  = 1
             ORDER BY start_date DESC LIMIT 1",
            $area_id, $today, $today
        ) );
    }

    /**
     * @deprecated Usar get_active_budget_for_area() cuando el presupuesto es por área.
     * Se mantiene por compatibilidad con class-financial-settings.php.
     *
     * @param int      $category_id  ID de categoría (ignorado si area_id se provee).
     * @param int|null $area_id      Área para buscar el presupuesto vigente.
     */
    public static function get_active_budget_for_category( int $category_id, ?int $area_id = null ): ?object {
        // Si se pasa area_id, delegar al método principal
        if ( $area_id !== null ) {
            return self::get_active_budget_for_area( $area_id );
        }

        // Fallback legacy: buscar por category_id (para presupuestos migrados sin área)
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_budgets';
        $today = current_time( 'Y-m-d' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE category_id = %d
               AND start_date  <= %s
               AND end_date    >= %s
               AND is_active   = 1
             ORDER BY start_date DESC LIMIT 1",
            $category_id, $today, $today
        ) );
    }

    /* ----------------------------------------------------------------
     * Calcular monto ejecutado de un presupuesto
     * -------------------------------------------------------------- */

    /**
     * Calcular monto ejecutado (egresos) para un Área en un rango de fechas.
     * Desde Fase 8.3+ el eje principal es area_id; category_id es filtro opcional.
     *
     * @param int         $area_id     ID del área.
     * @param string      $start_date  Fecha inicio (Y-m-d).
     * @param string      $end_date    Fecha fin (Y-m-d).
     * @param int|null    $category_id Opcional: desglose por categoría.
     */
    public static function get_executed( $area_id, $start_date, $end_date, ?int $category_id = null ) {
        global $wpdb;
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';

        $sql  = "SELECT COALESCE(SUM(amount), 0)
                 FROM {$tx_table}
                 WHERE area_id = %d
                   AND transaction_date BETWEEN %s AND %s
                   AND transaction_type = 'expense'
                   AND status = 'approved'
                   AND deleted_at IS NULL";
        $args = [ (int) $area_id, $start_date, $end_date ];

        if ( $category_id !== null ) {
            $sql  .= ' AND category_id = %d';
            $args[] = $category_id;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (float) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
    }

    /* ----------------------------------------------------------------
     * Enriquecer presupuesto con datos calculados
     * -------------------------------------------------------------- */

    private static function enrich( $budget ) {
        // Fase 8.3+: ejecución a nivel de área (area_id es el eje principal)
        $executed = self::get_executed( (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date );
        $pct      = $budget->budget_amount > 0 ? round( ( $executed / $budget->budget_amount ) * 100, 1 ) : 0;

        // Proyección lineal al fin del período
        $start_ts     = strtotime( $budget->start_date );
        $end_ts       = strtotime( $budget->end_date );
        $now_ts       = time();
        $total_days   = max( 1, ( $end_ts - $start_ts ) / 86400 );
        $elapsed_days = max( 1, min( ( $now_ts - $start_ts ) / 86400, $total_days ) );
        $projection   = $elapsed_days < $total_days
                        ? round( $executed / $elapsed_days * $total_days, 2 )
                        : $executed;

        $budget->executed    = $executed;
        $budget->percentage  = $pct;
        $budget->available   = max( 0, $budget->budget_amount - $executed );
        $budget->overrun     = max( 0, $executed - $budget->budget_amount );
        $budget->projection  = $projection;
        $budget->status      = $pct > 100 ? 'overrun' : ( $pct >= 90 ? 'critical' : ( $pct >= 70 ? 'warning' : 'ok' ) );

        return $budget;
    }

    /* ----------------------------------------------------------------
     * AJAX: Obtener lista de presupuestos
     * -------------------------------------------------------------- */

    public static function ajax_get_budgets() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        // Usuarios con aura_areas_view_own pueden ver el presupuesto de su propia área
        if (
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_areas_view_own' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $b_table = $wpdb->prefix . 'aura_finance_budgets';
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $a_table = $wpdb->prefix . 'aura_areas';

        // ── Filtro de área ───────────────────────────────────────────────
        $filter_area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

        // Si el usuario solo puede ver su propia área, se fuerza el filtro
        if (
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_areas_view_all' ) &&
            current_user_can( 'aura_areas_view_own' )
        ) {
            $user_area = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$a_table} WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
                get_current_user_id()
            ) );
            if ( $user_area ) {
                $filter_area_id = (int) $user_area;
            }
        }

        // ── Query ────────────────────────────────────────────────────────
        $where = 'b.is_active = 1';
        $args  = [];

        if ( $filter_area_id ) {
            $where .= ' AND b.area_id = %d';
            $args[] = $filter_area_id;
        }

        $sql = "SELECT b.*,
                       c.name  AS category_name,
                       c.color AS category_color,
                       c.icon  AS category_icon,
                       a.name  AS area_name,
                       a.color AS area_color
                FROM   {$b_table} b
                LEFT   JOIN {$c_table} c ON c.id = b.category_id
                LEFT   JOIN {$a_table} a ON a.id = b.area_id
                WHERE  {$where}
                ORDER  BY a.name ASC, b.start_date DESC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $budgets = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        $budgets = array_map( [ __CLASS__, 'enrich' ], $budgets );

        wp_send_json_success( [ 'budgets' => $budgets ] );
    }

    /* ----------------------------------------------------------------
     * AJAX: Guardar presupuesto (crear o editar)
     * -------------------------------------------------------------- */

    public static function ajax_save_budget() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'aura_finance_budgets';
        $id      = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

        // Fase 8.3+: Presupuesto por Área — area_id es requerido, category_id es referencia opcional
        $data = [
            'area_id'        => ! empty( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : null,
            'category_id'    => ! empty( $_POST['category_id'] ) ? (int) $_POST['category_id'] : null,
            'budget_amount'  => (float) ( $_POST['budget_amount'] ?? 0 ),
            'period_type'    => in_array( $_POST['period_type'] ?? '', [ 'monthly', 'quarterly', 'semestral', 'yearly' ], true ) ? $_POST['period_type'] : 'monthly',
            'start_date'     => sanitize_text_field( $_POST['start_date'] ?? '' ),
            'end_date'       => sanitize_text_field( $_POST['end_date'] ?? '' ),
            'alert_threshold'=> (int) ( $_POST['alert_threshold'] ?? 80 ),
            'alert_on_exceed'=> ! empty( $_POST['alert_on_exceed'] ) ? 1 : 0,
            'notify_creator' => ! empty( $_POST['notify_creator'] ) ? 1 : 0,
            'notify_admins'  => ! empty( $_POST['notify_admins'] ) ? 1 : 0,
            'notify_emails'  => sanitize_textarea_field( $_POST['notify_emails'] ?? '' ),
            'is_active'      => 1,
        ];

        if ( ! $data['area_id'] || $data['budget_amount'] <= 0 || ! $data['start_date'] || ! $data['end_date'] ) {
            wp_send_json_error( [ 'message' => __( 'Área, monto y fechas son obligatorios', 'aura-suite' ) ] );
        }

        // ── Verificar unicidad: no puede haber otro presupuesto activo con el mismo
        //    (area_id, category_id, start_date, end_date), excluyendo el actual en edición.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.id,
                    c.name  AS cat_name,
                    a.name  AS area_name
             FROM {$table} b
             LEFT JOIN {$wpdb->prefix}aura_finance_categories c ON c.id = b.category_id
             LEFT JOIN {$wpdb->prefix}aura_areas              a ON a.id = b.area_id
             WHERE b.area_id  = %d
               AND b.category_id <=> %s
               AND b.start_date = %s
               AND b.end_date   = %s
               AND b.is_active  = 1
               AND b.id        != %d",
            $data['area_id'],
            $data['category_id'],
            $data['start_date'],
            $data['end_date'],
            $id
        ) );

        if ( $existing ) {
            $cat_label  = $existing->cat_name  ?: __( 'sin categoría', 'aura-suite' );
            $area_label = $existing->area_name ?: __( 'general', 'aura-suite' );
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: 1: category name, 2: area name */
                    __( 'Ya existe un presupuesto de "%1$s" para el área "%2$s" en este período.', 'aura-suite' ),
                    $cat_label,
                    $area_label
                ),
            ] );
        }

        if ( $id ) {
            // Resetear flags de alerta enviada al editar
            $data['alert_sent_threshold'] = 0;
            $data['alert_sent_exceed']    = 0;
            $wpdb->update( $table, $data, [ 'id' => $id ], array_fill( 0, count( $data ), '%s' ), [ '%d' ] );
            do_action( 'aura_finance_budget_saved', $id, 'updated', $data );
            wp_send_json_success( [ 'id' => $id, 'action' => 'updated' ] );
        } else {
            $data['created_by'] = get_current_user_id();
            $wpdb->insert( $table, $data );
            $new_id = $wpdb->insert_id;
            do_action( 'aura_finance_budget_saved', $new_id, 'created', $data );
            wp_send_json_success( [ 'id' => $new_id, 'action' => 'created' ] );
        }
    }

    /* ----------------------------------------------------------------
     * AJAX: Eliminar presupuesto (soft-delete via is_active=0)
     * -------------------------------------------------------------- */

    public static function ajax_delete_budget() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_delete_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( [ 'message' => __( 'ID inválido', 'aura-suite' ) ] );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aura_finance_budgets',
            [ 'is_active' => 0 ],
            [ 'id' => $id ],
            [ '%d' ], [ '%d' ]
        );

        do_action( 'aura_finance_budget_deleted', $id );
        wp_send_json_success( [ 'deleted' => $id ] );
    }

    /* ----------------------------------------------------------------
     * AJAX: Detalle completo de presupuesto
     * -------------------------------------------------------------- */

    public static function ajax_get_budget_detail() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( [ 'message' => __( 'ID inválido', 'aura-suite' ) ] );

        global $wpdb;
        $b_table  = $wpdb->prefix . 'aura_finance_budgets';
        $c_table  = $wpdb->prefix . 'aura_finance_categories';
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';

        $a_table  = $wpdb->prefix . 'aura_areas';

        $budget = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*,
                    c.name  AS category_name,  c.color AS category_color,  c.icon AS category_icon,
                    a.name  AS area_name,       a.color AS area_color,       a.icon AS area_icon
             FROM {$b_table} b
             LEFT JOIN {$c_table} c ON c.id = b.category_id
             LEFT JOIN {$a_table} a ON a.id = b.area_id
             WHERE b.id = %d",
            $id
        ) );

        if ( ! $budget ) {
            wp_send_json_error( [ 'message' => __( 'Presupuesto no encontrado', 'aura-suite' ) ] );
        }

        $budget = self::enrich( $budget );

        // Transacciones del período filtradas por área (Fase 8.3+)
        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.transaction_date, t.amount, t.description, t.status, t.payment_method,
                    c.name AS category_name, c.color AS category_color,
                    u.display_name AS created_by_name
             FROM {$tx_table} t
             LEFT JOIN {$c_table} c ON c.id = t.category_id
             LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
             WHERE t.area_id = %d
               AND t.transaction_date BETWEEN %s AND %s
               AND t.transaction_type = 'expense'
               AND t.status != 'rejected'
               AND t.deleted_at IS NULL
             ORDER BY t.transaction_date DESC
             LIMIT 50",
            (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date
        ) );

        // Desglose por categoría dentro del área
        $by_category = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(c.name, '— Sin categoría —') AS name,
                    COALESCE(c.color, '#8c8f94') AS color,
                    SUM(t.amount) AS total
             FROM {$tx_table} t
             LEFT JOIN {$c_table} c ON c.id = t.category_id
             WHERE t.area_id = %d
               AND t.transaction_date BETWEEN %s AND %s
               AND t.transaction_type = 'expense'
               AND t.status != 'rejected'
               AND t.deleted_at IS NULL
             GROUP BY t.category_id
             ORDER BY total DESC",
            (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date
        ) );

        // Historial últimos 6 períodos equivalentes
        $history = self::get_period_history( $budget, 6 );

        wp_send_json_success( [
            'budget'       => $budget,
            'transactions' => $transactions,
            'by_category'  => $by_category,
            'history'      => $history,
        ] );
    }

    /* ----------------------------------------------------------------
     * AJAX: Progreso rápido (para widget dashboard)
     * -------------------------------------------------------------- */

    public static function ajax_get_budget_progress() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        global $wpdb;
        $b_table = $wpdb->prefix . 'aura_finance_budgets';
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $today   = current_time( 'Y-m-d' );

        $budgets = $wpdb->get_results(
            "SELECT b.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
             FROM {$b_table} b
             LEFT JOIN {$c_table} c ON c.id = b.category_id
             WHERE b.is_active = 1
               AND '{$today}' BETWEEN b.start_date AND b.end_date
             ORDER BY b.start_date DESC"
        );

        $budgets = array_map( [ __CLASS__, 'enrich' ], $budgets );

        wp_send_json_success( [ 'budgets' => $budgets ] );
    }

    /* ----------------------------------------------------------------
     * AJAX: Widget data (top 5 más críticos)
     * -------------------------------------------------------------- */

    public static function ajax_widget_data() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        global $wpdb;
        $b_table = $wpdb->prefix . 'aura_finance_budgets';
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $today   = current_time( 'Y-m-d' );

        $a_table = $wpdb->prefix . 'aura_areas';

        $budgets = $wpdb->get_results(
            "SELECT b.*,
                    c.name  AS category_name,
                    c.color AS category_color,
                    c.icon  AS category_icon,
                    a.name  AS area_name,
                    a.color AS area_color,
                    a.icon  AS area_icon
             FROM {$b_table} b
             LEFT JOIN {$c_table} c ON c.id = b.category_id
             LEFT JOIN {$a_table} a ON a.id = b.area_id
             WHERE b.is_active = 1
               AND '{$today}' BETWEEN b.start_date AND b.end_date"
        );

        $budgets = array_map( [ __CLASS__, 'enrich' ], $budgets );

        // Ordenar por área ASC, luego por % ejecutado DESC
        usort( $budgets, function ( $a, $b ) {
            $area_cmp = strcmp( $a->area_name ?? '', $b->area_name ?? '' );
            if ( $area_cmp !== 0 ) return $area_cmp;
            return $b->percentage <=> $a->percentage;
        } );
        $top5 = array_slice( $budgets, 0, 5 );

        wp_send_json_success( [ 'budgets' => $top5 ] );
    }

    /* ----------------------------------------------------------------
     * AJAX: Ajustar presupuesto (% o monto fijo)
     * -------------------------------------------------------------- */

    public static function ajax_adjust_budget() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_edit_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $id         = (int) ( $_POST['id'] ?? 0 );
        $adj_type   = sanitize_text_field( $_POST['adj_type'] ?? 'percent' ); // percent | amount
        $adj_value  = (float) ( $_POST['adj_value'] ?? 0 );

        if ( ! $id ) wp_send_json_error( [ 'message' => __( 'ID inválido', 'aura-suite' ) ] );

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_finance_budgets';
        $budget = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( ! $budget ) wp_send_json_error( [ 'message' => __( 'Presupuesto no encontrado', 'aura-suite' ) ] );

        $new_amount = $adj_type === 'percent'
            ? $budget->budget_amount * ( 1 + $adj_value / 100 )
            : $budget->budget_amount + $adj_value;

        $new_amount = max( 0.01, round( $new_amount, 2 ) );

        $wpdb->update( $table, [ 'budget_amount' => $new_amount, 'alert_sent_threshold' => 0, 'alert_sent_exceed' => 0 ], [ 'id' => $id ], [ '%f' ], [ '%d' ] );

        wp_send_json_success( [ 'id' => $id, 'new_amount' => $new_amount ] );
    }

    /* ----------------------------------------------------------------
     * Historial de períodos anteriores
     * -------------------------------------------------------------- */

    private static function get_period_history( $budget, $periods = 6 ) {
        $history = [];
        $start   = new DateTime( $budget->start_date );
        $end     = new DateTime( $budget->end_date );

        // Calcular duración del período en meses aprox
        $diff_days = (int) $start->diff( $end )->days;

        for ( $i = 1; $i <= $periods; $i++ ) {
            $p_end   = clone $start;
            $p_end->modify( '-1 day' );
            $p_start = clone $p_end;
            $p_start->modify( '-' . $diff_days . ' days' );

            $executed = self::get_executed(
                (int) ( $budget->area_id ?? 0 ),
                $p_start->format( 'Y-m-d' ),
                $p_end->format( 'Y-m-d' )
            );

            array_unshift( $history, [
                'period'     => $p_start->format( 'm/Y' ),
                'budget'     => (float) $budget->budget_amount,
                'executed'   => $executed,
            ] );

            $start = clone $p_start;
        }

        return $history;
    }

    /* ----------------------------------------------------------------
     * AJAX: Categorías con presupuesto activo para un área
     * Usado por el formulario de transacciones (carga dinámica)
     * -------------------------------------------------------------- */

    public static function ajax_get_area_budget_categories() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_create' )
            && ! current_user_can( 'aura_finance_edit_all' )
            && ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $area_id = absint( $_POST['area_id'] ?? 0 );
        if ( ! $area_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de área inválido', 'aura-suite' ) ] );
        }

        global $wpdb;
        $today   = current_time( 'Y-m-d' );
        $b_table = $wpdb->prefix . 'aura_finance_budgets';
        $c_table = $wpdb->prefix . 'aura_finance_categories';

        // Categorías con presupuesto activo vigente para el área
        $cats = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT c.id, c.name, c.color, c.icon, c.type,
                    b.id AS budget_id, b.budget_amount, b.start_date, b.end_date
             FROM {$b_table} b
             JOIN {$c_table} c ON c.id = b.category_id
             WHERE b.area_id   = %d
               AND b.is_active = 1
               AND b.start_date <= %s
               AND b.end_date   >= %s
             ORDER BY c.name ASC",
            $area_id, $today, $today
        ) );

        $has_budgets = ! empty( $cats );

        // Si no hay presupuestos con category_id, devolver todas las categorías activas
        if ( ! $has_budgets ) {
            $type_filter = sanitize_key( $_POST['type'] ?? '' );
            $where_type  = in_array( $type_filter, [ 'income', 'expense' ], true )
                ? $wpdb->prepare( ' AND type = %s', $type_filter )
                : '';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cats = $wpdb->get_results(
                "SELECT id, name, color, icon, type FROM {$c_table} WHERE is_active = 1{$where_type} ORDER BY display_order ASC, name ASC"
            );
        } else {
            // Enriquecer cada fila con datos ejecutados para el banner de estatus
            foreach ( $cats as $cat ) {
                $executed         = self::get_executed( $area_id, $cat->start_date, $cat->end_date, (int) $cat->id );
                $cat->executed    = $executed;
                $cat->available   = max( 0, (float) $cat->budget_amount - $executed );
                $cat->overrun     = max( 0, $executed - (float) $cat->budget_amount );
                $cat->percentage  = $cat->budget_amount > 0
                    ? round( ( $executed / (float) $cat->budget_amount ) * 100, 1 )
                    : 0;
            }
        }

        wp_send_json_success( [
            'categories'  => $cats,
            'has_budgets' => $has_budgets,
        ] );
    }

    /* ----------------------------------------------------------------
     * AJAX: Desglose por categoría para un presupuesto (Análisis)
     * -------------------------------------------------------------- */

    public static function ajax_budget_category_breakdown() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        $budget_id = absint( $_POST['budget_id'] ?? 0 );
        if ( ! $budget_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de presupuesto inválido', 'aura-suite' ) ] );
        }

        global $wpdb;
        $b_table  = $wpdb->prefix . 'aura_finance_budgets';
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';
        $c_table  = $wpdb->prefix . 'aura_finance_categories';

        $budget = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$b_table} WHERE id = %d AND is_active = 1",
            $budget_id
        ) );

        if ( ! $budget ) {
            wp_send_json_error( [ 'message' => __( 'Presupuesto no encontrado', 'aura-suite' ) ] );
        }

        $area_id    = (int) ( $budget->area_id ?? 0 );
        $start_date = $budget->start_date;
        $end_date   = $budget->end_date;

        // Desglose de transacciones aprobadas agrupadas por categoría
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(c.id, 0) AS id,
                    COALESCE(c.name, '— Sin categoría —') AS name,
                    COALESCE(c.color, '#8c8f94') AS color,
                    COUNT(t.id) AS tx_count,
                    COALESCE(SUM(t.amount), 0) AS total_amount
             FROM {$tx_table} t
             LEFT JOIN {$c_table} c ON c.id = t.category_id
             WHERE t.area_id = %d
               AND t.transaction_date BETWEEN %s AND %s
               AND t.transaction_type = 'expense'
               AND t.status = 'approved'
               AND t.deleted_at IS NULL
             GROUP BY t.category_id
             ORDER BY total_amount DESC",
            $area_id, $start_date, $end_date
        ) );

        $grand_total = array_sum( array_column( (array) $rows, 'total_amount' ) );

        foreach ( $rows as $row ) {
            $row->total_amount = (float) $row->total_amount;
            $row->tx_count     = (int)   $row->tx_count;
            $row->pct          = $grand_total > 0
                ? round( ( $row->total_amount / $grand_total ) * 100, 1 )
                : 0;
        }

        $enriched = self::enrich( $budget );

        wp_send_json_success( [
            'categories'    => $rows,
            'total_amount'  => (float) $grand_total,
            'budget_amount' => (float) $budget->budget_amount,
            'pct_used'      => $enriched->percentage,
        ] );
    }

    /* ----------------------------------------------------------------
     * Widget HTML para incluir en financial dashboard
     * -------------------------------------------------------------- */

    public static function render_dashboard_widget() {
        ?>
        <div class="aura-budget-widget" id="aura-budget-widget">
            <div class="aura-budget-widget-header">
                <h3>
                    <span class="dashicons dashicons-chart-pie"></span>
                    <?php esc_html_e( 'Estado de Presupuestos', 'aura-suite' ); ?>
                </h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-budgets' ) ); ?>" class="aura-widget-link">
                    <?php esc_html_e( 'Ver todos', 'aura-suite' ); ?> →
                </a>
            </div>
            <div class="aura-budget-widget-body" id="aura-budget-widget-body">
                <p class="aura-loading"><?php esc_html_e( 'Cargando presupuestos…', 'aura-suite' ); ?></p>
            </div>
        </div>
        <?php
    }

    /* ----------------------------------------------------------------
     * Cron: Verificar presupuestos y enviar alertas
     * -------------------------------------------------------------- */

    public static function check_budgets_and_alert() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_budgets';
        $today = current_time( 'Y-m-d' );

        $budgets = $wpdb->get_results(
            "SELECT b.*, a.name AS area_name, c.name AS category_name
             FROM {$table} b
             LEFT JOIN {$wpdb->prefix}aura_areas a ON a.id = b.area_id
             LEFT JOIN {$wpdb->prefix}aura_finance_categories c ON c.id = b.category_id
             WHERE b.is_active = 1
               AND '{$today}' BETWEEN b.start_date AND b.end_date"
        );

        foreach ( $budgets as $budget ) {
            $executed = self::get_executed( (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date );
            $pct      = $budget->budget_amount > 0 ? ( $executed / $budget->budget_amount ) * 100 : 0;

            // Alerta al umbral (ej. 80%)
            if ( $pct >= $budget->alert_threshold && ! $budget->alert_sent_threshold ) {
                self::send_alert( $budget, 'threshold', $pct );
                $wpdb->update( $table, [ 'alert_sent_threshold' => 1 ], [ 'id' => $budget->id ], [ '%d' ], [ '%d' ] );
            }

            // Alerta al 100% / sobregiro
            if ( $pct >= 100 && ! $budget->alert_sent_exceed && $budget->alert_on_exceed ) {
                self::send_alert( $budget, 'exceed', $pct );
                $wpdb->update( $table, [ 'alert_sent_exceed' => 1 ], [ 'id' => $budget->id ], [ '%d' ], [ '%d' ] );
            }
        }
    }

    /* ----------------------------------------------------------------
     * Enviar alerta por email
     * -------------------------------------------------------------- */

    private static function send_alert( $budget, $type, $pct ) {
        $pct_fmt  = round( $pct, 1 );
        // Fase 8.3+: mostrar área como identificador principal
        $area_name = $budget->area_name ?? __( 'Sin área', 'aura-suite' );
        $cat_name  = $budget->category_name ?? '';
        $subject_id = $cat_name ? "{$area_name} / {$cat_name}" : $area_name;
        $url      = admin_url( 'admin.php?page=aura-financial-budgets' );

        if ( $type === 'threshold' ) {
            $subject = sprintf( __( '⚠️ Presupuesto de %s al %s%%', 'aura-suite' ), $subject_id, $pct_fmt );
            $emoji   = '⚠️';
            $color   = '#f59e0b';
        } else {
            $subject = sprintf( __( '🚨 Presupuesto de %s sobrepasado (%s%%)', 'aura-suite' ), $subject_id, $pct_fmt );
            $emoji   = '🚨';
            $color   = '#d63638';
        }

        $amount_fmt   = number_format( $budget->budget_amount, 2 );
        $executed_fmt = number_format( self::get_executed( (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date ), 2 );

        $message = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f6f7f7;padding:20px">';
        $message .= '<div style="background:#fff;max-width:600px;margin:0 auto;border-radius:8px;padding:30px;border:1px solid #dce0e4">';
        $message .= '<h2 style="color:' . $color . '">' . $emoji . ' ' . esc_html( $subject ) . '</h2>';
        $message .= '<table style="width:100%;border-collapse:collapse">';
        $message .= '<tr><td style="padding:8px;border-bottom:1px solid #eee"><strong>' . __( 'Área', 'aura-suite' ) . '</strong></td><td style="padding:8px;border-bottom:1px solid #eee">' . esc_html( $area_name ) . '</td></tr>';
        if ( $cat_name ) : $message .= '<tr><td style="padding:8px;border-bottom:1px solid #eee"><strong>' . __( 'Categoría ref.', 'aura-suite' ) . '</strong></td><td style="padding:8px;border-bottom:1px solid #eee">' . esc_html( $cat_name ) . '</td></tr>'; endif;
        $message .= '<tr><td style="padding:8px;border-bottom:1px solid #eee"><strong>' . __( 'Presupuesto', 'aura-suite' ) . '</strong></td><td style="padding:8px;border-bottom:1px solid #eee">$' . $amount_fmt . '</td></tr>';
        $message .= '<tr><td style="padding:8px;border-bottom:1px solid #eee"><strong>' . __( 'Ejecutado', 'aura-suite' ) . '</strong></td><td style="padding:8px;border-bottom:1px solid #eee">$' . $executed_fmt . ' (' . $pct_fmt . '%)</td></tr>';
        $message .= '<tr><td style="padding:8px"><strong>' . __( 'Período', 'aura-suite' ) . '</strong></td><td style="padding:8px">' . esc_html( $budget->start_date ) . ' — ' . esc_html( $budget->end_date ) . '</td></tr>';
        $message .= '</table>';
        $message .= '<p style="margin-top:20px"><a href="' . esc_url( $url ) . '" style="background:' . $color . ';color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none">' . __( 'Ver Presupuestos', 'aura-suite' ) . '</a></p>';
        $message .= '</div></body></html>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $recipients = [];

        // Notificar al creador
        if ( $budget->notify_creator ) {
            $creator = get_userdata( $budget->created_by );
            if ( $creator ) $recipients[] = $creator->user_email;
        }

        // Notificar a admins
        if ( $budget->notify_admins || $type === 'exceed' ) {
            $admins = get_users( [ 'role' => 'administrator', 'fields' => [ 'user_email' ] ] );
            foreach ( $admins as $a ) {
                $recipients[] = $a->user_email;
            }
        }

        // Emails adicionales
        if ( ! empty( $budget->notify_emails ) ) {
            $extras = array_filter( array_map( 'trim', explode( ',', $budget->notify_emails ) ) );
            $recipients = array_merge( $recipients, $extras );
        }

        $recipients = array_unique( array_filter( $recipients ) );

        foreach ( $recipients as $email ) {
            if ( is_email( $email ) ) {
                wp_mail( $email, $subject, $message, $headers );
            }
        }
    }

    /* ----------------------------------------------------------------
     * Renderizar página admin
     * -------------------------------------------------------------- */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/budgets-page.php';
    }
}
