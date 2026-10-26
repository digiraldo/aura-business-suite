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

        // AJAX Presupuestos Generales y por Área
        add_action( 'wp_ajax_aura_get_budgets',         [ __CLASS__, 'ajax_get_budgets' ] );
        add_action( 'wp_ajax_aura_save_budget',         [ __CLASS__, 'ajax_save_budget' ] );
        add_action( 'wp_ajax_aura_delete_budget',       [ __CLASS__, 'ajax_delete_budget' ] );
        add_action( 'wp_ajax_aura_get_budget_detail',   [ __CLASS__, 'ajax_get_budget_detail' ] );
        add_action( 'wp_ajax_aura_get_budget_progress',           [ __CLASS__, 'ajax_get_budget_progress' ] );
        add_action( 'wp_ajax_aura_adjust_budget',                 [ __CLASS__, 'ajax_adjust_budget' ] );
        add_action( 'wp_ajax_aura_budget_widget_data',            [ __CLASS__, 'ajax_widget_data' ] );
        add_action( 'wp_ajax_aura_get_area_budget_categories',    [ __CLASS__, 'ajax_get_area_budget_categories' ] );
        add_action( 'wp_ajax_aura_budget_category_breakdown',     [ __CLASS__, 'ajax_budget_category_breakdown' ] );

        // AJAX Presupuestos y Estadísticas por Categoría & Subcategorías (Nuevo)
        add_action( 'wp_ajax_aura_get_category_budget_stats',    [ __CLASS__, 'ajax_get_category_budget_stats' ] );
        add_action( 'wp_ajax_aura_save_category_budget',         [ __CLASS__, 'ajax_save_category_budget' ] );
        add_action( 'wp_ajax_aura_delete_category_budget',       [ __CLASS__, 'ajax_delete_category_budget' ] );

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

        // Soporte Presupuestos y Estadísticas por Categoría (Año Fiscal, Modo Cálculo, Porcentaje y Distribución Mensual)
        if ( ! in_array( 'fiscal_year', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `fiscal_year` INT(4) NULL DEFAULT NULL AFTER `category_id`" );
            $wpdb->query( "UPDATE `{$table}` SET `fiscal_year` = YEAR(start_date) WHERE (`fiscal_year` IS NULL OR `fiscal_year` = 0) AND start_date IS NOT NULL" );
        }
        if ( ! in_array( 'calculation_mode', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `calculation_mode` ENUM('amount','percentage') DEFAULT 'amount' AFTER `budget_amount`" );
        }
        if ( ! in_array( 'budget_percentage', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `budget_percentage` DECIMAL(5,2) NULL DEFAULT NULL AFTER `calculation_mode`" );
        }
        if ( ! in_array( 'monthly_distribution', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `monthly_distribution` LONGTEXT NULL DEFAULT NULL AFTER `budget_percentage`" );
        }

        // Índices de optimización para presupuestos de categorías
        $indices = $wpdb->get_results( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_cat_fiscal_year'" );
        if ( empty( $indices ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `idx_cat_fiscal_year` (`category_id`, `fiscal_year`, `is_active`)" );
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
    /**
     * Obtener el ID de la categoría y todos los IDs de sus subcategorías activas.
     */
    public static function get_category_and_subcategories_ids( int $category_id ): array {
        global $wpdb;
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $sub_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$c_table} WHERE parent_id = %d AND is_active = 1",
            $category_id
        ) );
        $ids = [ $category_id ];
        if ( ! empty( $sub_ids ) ) {
            $ids = array_merge( $ids, array_map( 'intval', $sub_ids ) );
        }
        return array_unique( $ids );
    }

    /**
     * Calcular monto ejecutado (egresos) para un Área en un rango de fechas.
     * Incluye automáticamente transacciones de subcategorías si la categoría padre fue seleccionada.
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
            $cat_ids = self::get_category_and_subcategories_ids( (int) $category_id );
            $placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );
            $sql  .= " AND (category_id IN ({$placeholders}) OR expense_category_id IN ({$placeholders}))";
            $args = array_merge( $args, $cat_ids, $cat_ids );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (float) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
    }

    /**
     * Contar número de transacciones ejecutadas (egresos aprobados) para un Área y opcionalmente Categoría en un rango de fechas.
     * Incluye automáticamente transacciones de subcategorías.
     *
     * @param int         $area_id     ID del área.
     * @param string      $start_date  Fecha inicio (Y-m-d).
     * @param string      $end_date    Fecha fin (Y-m-d).
     * @param int|null    $category_id Opcional: filtro por categoría específica.
     */
    public static function get_tx_count( $area_id, $start_date, $end_date, ?int $category_id = null ): int {
        global $wpdb;
        $tx_table = $wpdb->prefix . 'aura_finance_transactions';

        $sql  = "SELECT COUNT(id)
                 FROM {$tx_table}
                 WHERE area_id = %d
                   AND transaction_date BETWEEN %s AND %s
                   AND transaction_type = 'expense'
                   AND status = 'approved'
                   AND deleted_at IS NULL";
        $args = [ (int) $area_id, $start_date, $end_date ];

        if ( $category_id !== null ) {
            $cat_ids = self::get_category_and_subcategories_ids( (int) $category_id );
            $placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );
            $sql  .= " AND (category_id IN ({$placeholders}) OR expense_category_id IN ({$placeholders}))";
            $args = array_merge( $args, $cat_ids, $cat_ids );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
    }

    /* ----------------------------------------------------------------
     * Enriquecer presupuesto con datos calculados
     * -------------------------------------------------------------- */

    private static function enrich( $budget ) {
        // Fase 8.3+: Si el presupuesto tiene categoría asignada, computar contra esa categoría y sus subcategorías; si no, contra todo el área
        $cat_id   = ! empty( $budget->category_id ) ? (int) $budget->category_id : null;
        $area_id  = (int) ( $budget->area_id ?? 0 );
        $executed = self::get_executed( $area_id, $budget->start_date, $budget->end_date, $cat_id );
        $tx_count = self::get_tx_count( $area_id, $budget->start_date, $budget->end_date, $cat_id );
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
        $budget->tx_count    = $tx_count;
        $budget->percentage  = $pct;
        $budget->available   = max( 0, $budget->budget_amount - $executed );
        $budget->overrun     = max( 0, $executed - $budget->budget_amount );
        $budget->projection  = $projection;
        $budget->status      = $pct > 100 ? 'overrun' : ( $pct >= 90 ? 'critical' : ( $pct >= 70 ? 'warning' : 'ok' ) );

        // Desglose de subcategorías vinculadas al presupuesto para visualización árbol en Área
        global $wpdb;
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $subcategories = [];

        if ( ! empty( $budget->category_id ) ) {
            $raw_subs = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name, color, icon FROM {$c_table} WHERE parent_id = %d AND is_active = 1 ORDER BY name ASC",
                $budget->category_id
            ) );
            if ( ! empty( $raw_subs ) ) {
                foreach ( $raw_subs as $rs ) {
                    $s_exec = self::get_executed( $area_id, $budget->start_date, $budget->end_date, (int) $rs->id );
                    $s_tx   = self::get_tx_count( $area_id, $budget->start_date, $budget->end_date, (int) $rs->id );
                    $subcategories[] = [
                        'id'         => (int) $rs->id,
                        'name'       => $rs->name,
                        'color'      => ! empty( $rs->color ) ? $rs->color : ( ! empty( $budget->category_color ) ? $budget->category_color : '#6366f1' ),
                        'icon'       => ! empty( $rs->icon ) ? $rs->icon : 'dashicons-tag',
                        'executed'   => $s_exec,
                        'tx_count'   => $s_tx,
                        'percentage' => $budget->budget_amount > 0 ? round( ( $s_exec / $budget->budget_amount ) * 100, 1 ) : 0.0,
                    ];
                }
            }
        }
        $budget->subcategories = $subcategories;

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

        // ── Filtro de área y scoping multi-área ────────────────────────
        $filter_area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $is_restricted  = (
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_areas_view_all' ) &&
            current_user_can( 'aura_areas_view_own' )
        );

        $allowed_area_ids = [];
        if ( $is_restricted ) {
            $allowed_area_ids = Aura_Areas_Setup::get_user_area_ids( get_current_user_id() );
            if ( empty( $allowed_area_ids ) ) {
                wp_send_json_success( [ 'budgets' => [] ] );
                return;
            }
            if ( $filter_area_id && ! in_array( $filter_area_id, $allowed_area_ids, true ) ) {
                $filter_area_id = 0; // Prevenir acceso a área no asignada
            }
        }

        // ── Query ────────────────────────────────────────────────────────
        $where = 'b.is_active = 1';
        $args  = [];

        if ( $filter_area_id ) {
            $where .= ' AND b.area_id = %d';
            $args[] = $filter_area_id;
        } elseif ( $is_restricted && ! empty( $allowed_area_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $allowed_area_ids ), '%d' ) );
            $where .= " AND b.area_id IN ({$placeholders})";
            $args = array_merge( $args, $allowed_area_ids );
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

        // Transacciones del período filtradas por área y categoría (incluyendo subcategorías) si está definida
        $where_tx = "t.area_id = %d AND t.transaction_date BETWEEN %s AND %s AND t.transaction_type = 'expense' AND t.status != 'rejected' AND t.deleted_at IS NULL";
        $tx_args  = [ (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date ];

        if ( ! empty( $budget->category_id ) ) {
            $cat_ids = self::get_category_and_subcategories_ids( (int) $budget->category_id );
            $placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );
            $where_tx .= " AND (t.category_id IN ({$placeholders}) OR t.expense_category_id IN ({$placeholders}))";
            $tx_args = array_merge( $tx_args, $cat_ids, $cat_ids );
        }

        $transactions = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.transaction_date, t.amount, t.description, t.status, t.payment_method,
                    c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
                    u.display_name AS created_by_name
             FROM {$tx_table} t
             LEFT JOIN {$c_table} c ON c.id = t.category_id
             LEFT JOIN {$wpdb->users} u ON u.ID = t.created_by
             WHERE {$where_tx}
             ORDER BY t.transaction_date DESC
             LIMIT 150",
            ...$tx_args
        ) );

        // Desglose por categoría dentro del área con conteo de transacciones
        $by_cat_where = "t.area_id = %d AND t.transaction_date BETWEEN %s AND %s AND t.transaction_type = 'expense' AND t.status != 'rejected' AND t.deleted_at IS NULL";
        $by_cat_args  = [ (int) ( $budget->area_id ?? 0 ), $budget->start_date, $budget->end_date ];

        if ( ! empty( $budget->category_id ) ) {
            $family_ids = self::get_category_and_subcategories_ids( (int) $budget->category_id );
            $placeholders = implode( ',', array_fill( 0, count( $family_ids ), '%d' ) );
            $by_cat_where .= " AND (t.category_id IN ({$placeholders}) OR t.expense_category_id IN ({$placeholders}))";
            $by_cat_args = array_merge( $by_cat_args, $family_ids, $family_ids );
        }

        $by_category = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(c.id, 0) AS id,
                    COALESCE(c.name, '— Sin categoría —') AS name,
                    COALESCE(c.color, '#8c8f94') AS color,
                    COALESCE(c.icon, 'dashicons-category') AS icon,
                    COALESCE(c.parent_id, 0) AS parent_id,
                    COUNT(t.id) AS tx_count,
                    COALESCE(SUM(t.amount), 0) AS total
             FROM {$tx_table} t
             LEFT JOIN {$c_table} c ON c.id = t.category_id
             WHERE {$by_cat_where}
             GROUP BY t.category_id
             ORDER BY total DESC",
            ...$by_cat_args
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
        $cat_id    = ! empty( $budget->category_id ) ? (int) $budget->category_id : null;

        for ( $i = 1; $i <= $periods; $i++ ) {
            $p_end   = clone $start;
            $p_end->modify( '-1 day' );
            $p_start = clone $p_end;
            $p_start->modify( '-' . $diff_days . ' days' );

            $executed = self::get_executed(
                (int) ( $budget->area_id ?? 0 ),
                $p_start->format( 'Y-m-d' ),
                $p_end->format( 'Y-m-d' ),
                $cat_id
            );

            $tx_count = self::get_tx_count(
                (int) ( $budget->area_id ?? 0 ),
                $p_start->format( 'Y-m-d' ),
                $p_end->format( 'Y-m-d' ),
                $cat_id
            );

            array_unshift( $history, [
                'period'     => $p_start->format( 'm/Y' ),
                'budget'     => (float) $budget->budget_amount,
                'executed'   => $executed,
                'tx_count'   => $tx_count,
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

        // Presupuesto general activo del área (sin categoría o nivel global)
        $global_budget    = self::get_active_budget_for_area( $area_id );
        $area_budget_data = null;
        if ( $global_budget ) {
            $area_executed = self::get_executed( $area_id, $global_budget->start_date, $global_budget->end_date );
            $area_budget_data = [
                'id'            => (int) $global_budget->id,
                'budget_amount' => (float) $global_budget->budget_amount,
                'start_date'    => $global_budget->start_date,
                'end_date'      => $global_budget->end_date,
                'executed'      => $area_executed,
                'available'     => max( 0, (float) $global_budget->budget_amount - $area_executed ),
                'overrun'       => max( 0, $area_executed - (float) $global_budget->budget_amount ),
                'percentage'    => $global_budget->budget_amount > 0
                    ? round( ( $area_executed / (float) $global_budget->budget_amount ) * 100, 1 )
                    : 0,
                'tx_count'      => self::get_tx_count( $area_id, $global_budget->start_date, $global_budget->end_date ),
            ];
        }

        $has_budgets = ! empty( $cats ) || ! empty( $area_budget_data );

        // Si no hay presupuestos con category_id específico, devolver todas las categorías activas
        if ( empty( $cats ) ) {
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
                $cat->tx_count    = self::get_tx_count( $area_id, $cat->start_date, $cat->end_date, (int) $cat->id );
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
            'area_budget' => $area_budget_data,
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

        $where_breakdown = "t.area_id = %d AND t.transaction_date BETWEEN %s AND %s AND t.transaction_type = 'expense' AND t.status = 'approved' AND t.deleted_at IS NULL";
        $breakdown_args  = [ $area_id, $start_date, $end_date ];

        if ( ! empty( $budget->category_id ) ) {
            $family_ids = self::get_category_and_subcategories_ids( (int) $budget->category_id );
            $placeholders = implode( ',', array_fill( 0, count( $family_ids ), '%d' ) );
            $where_breakdown .= " AND (t.category_id IN ({$placeholders}) OR t.expense_category_id IN ({$placeholders}))";
            $breakdown_args = array_merge( $breakdown_args, $family_ids, $family_ids );
        }

        // Desglose de transacciones aprobadas agrupadas por categoría
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(c.id, 0) AS id,
                    COALESCE(c.name, '— Sin categoría —') AS name,
                    COALESCE(c.color, '#8c8f94') AS color,
                    COALESCE(c.icon, 'dashicons-category') AS icon,
                    COALESCE(c.parent_id, 0) AS parent_id,
                    COUNT(t.id) AS tx_count,
                    COALESCE(SUM(t.amount), 0) AS total_amount
             FROM {$tx_table} t
             LEFT JOIN {$c_table} c ON c.id = t.category_id
             WHERE {$where_breakdown}
             GROUP BY t.category_id
             ORDER BY total_amount DESC",
            ...$breakdown_args
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
     * Presupuestos y Estadísticas por Categoría & Subcategorías (Nuevo)
     * -------------------------------------------------------------- */

    /**
     * Obtener lista de categorías principales de egreso con estado de presupuesto y gasto en el año fiscal.
     *
     * @param int $fiscal_year Año fiscal (ej. 2026).
     * @return array
     */
    public static function get_budgetable_categories( int $fiscal_year ): array {
        global $wpdb;
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $b_table = $wpdb->prefix . 'aura_finance_budgets';
        $t_table = $wpdb->prefix . 'aura_finance_transactions';

        // 1. Obtener todas las categorías padre activas de egresos, ambos o capex
        $parents = $wpdb->get_results(
            "SELECT c.id, c.name, c.color, c.icon, c.slug, c.type,
                    (SELECT COUNT(*) FROM {$c_table} sc WHERE sc.parent_id = c.id AND sc.is_active = 1) AS subcategories_count
             FROM {$c_table} c
             WHERE c.is_active = 1
               AND c.parent_id IS NULL
               AND c.type IN ('expense', 'both', 'capital')
             ORDER BY c.name ASC"
        );

        if ( empty( $parents ) ) {
            return [];
        }

        $results    = [];
        $start_date = sprintf( '%04d-01-01', $fiscal_year );
        $end_date   = sprintf( '%04d-12-31', $fiscal_year );

        foreach ( $parents as $cat ) {
            $cat_id = (int) $cat->id;

            // Presupuesto activo para el año fiscal
            $budget = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, budget_amount, calculation_mode, budget_percentage, monthly_distribution, alert_threshold, alert_on_exceed
                 FROM {$b_table}
                 WHERE category_id = %d
                   AND is_active = 1
                   AND (fiscal_year = %d OR (start_date <= %s AND end_date >= %s))
                 ORDER BY id DESC LIMIT 1",
                $cat_id, $fiscal_year, $end_date, $start_date
            ) );

            // Subcategorías IDs
            $sub_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$c_table} WHERE parent_id = %d AND is_active = 1",
                $cat_id
            ) );
            $all_ids = array_merge( [ $cat_id ], array_map( 'intval', (array) $sub_ids ) );
            $placeholders = implode( ',', array_fill( 0, count( $all_ids ), '%d' ) );

            // Total gastado en el año fiscal
            $spent_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(id) AS tx_count, COALESCE(SUM(amount), 0) AS total_spent
                 FROM {$t_table}
                 WHERE (category_id IN ({$placeholders}) OR expense_category_id IN ({$placeholders}))
                   AND transaction_type = 'expense'
                   AND status = 'approved'
                   AND deleted_at IS NULL
                   AND transaction_date BETWEEN %s AND %s",
                ...array_merge( $all_ids, $all_ids, [ $start_date, $end_date ] )
            ) );

            $budget_amount = $budget ? (float) $budget->budget_amount : 0.00;
            $total_spent   = $spent_row ? (float) $spent_row->total_spent : 0.00;
            $tx_count      = $spent_row ? (int) $spent_row->tx_count : 0;
            $pct           = $budget_amount > 0 ? round( ( $total_spent / $budget_amount ) * 100, 1 ) : 0.0;

            $status = 'no_budget';
            if ( $budget_amount > 0 ) {
                if ( $pct >= 100 ) {
                    $status = 'overrun';
                } elseif ( $pct >= 90 ) {
                    $status = 'critical';
                } elseif ( $pct >= 70 ) {
                    $status = 'warning';
                } else {
                    $status = 'ok';
                }
            }

            $results[] = [
                'id'                  => $cat_id,
                'name'                => $cat->name,
                'color'               => ! empty( $cat->color ) ? $cat->color : '#475569',
                'icon'                => ! empty( $cat->icon ) ? $cat->icon : 'dashicons-category',
                'slug'                => $cat->slug,
                'type'                => $cat->type,
                'subcategories_count' => (int) $cat->subcategories_count,
                'has_budget'          => (bool) $budget,
                'budget_id'           => $budget ? (int) $budget->id : 0,
                'budget_amount'       => $budget_amount,
                'budget_percentage'   => $budget ? (float) $budget->budget_percentage : null,
                'calculation_mode'    => $budget ? ( $budget->calculation_mode ?? 'amount' ) : 'amount',
                'total_spent'         => $total_spent,
                'tx_count'            => $tx_count,
                'execution_pct'       => $pct,
                'status'              => $status,
                'remaining'           => max( 0, $budget_amount - $total_spent ),
                'overrun'             => max( 0, $total_spent - $budget_amount ),
            ];
        }

        return $results;
    }

    /**
     * Calcular estadísticas y desglose completo para una Categoría y sus Subcategorías en un año fiscal.
     *
     * @param int $category_id ID de la categoría padre seleccionada.
     * @param int $fiscal_year Año fiscal a consultar.
     * @return array|null
     */
    public static function get_category_budget_stats( int $category_id, int $fiscal_year ): ?array {
        global $wpdb;
        $c_table = $wpdb->prefix . 'aura_finance_categories';
        $b_table = $wpdb->prefix . 'aura_finance_budgets';
        $t_table = $wpdb->prefix . 'aura_finance_transactions';

        // 1. Obtener datos de la categoría principal
        $parent = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, color, icon, slug, type, description, parent_id
             FROM {$c_table}
             WHERE id = %d AND is_active = 1",
            $category_id
        ) );

        if ( ! $parent ) {
            return null;
        }

        $start_date = sprintf( '%04d-01-01', $fiscal_year );
        $end_date   = sprintf( '%04d-12-31', $fiscal_year );

        // 2. Presupuesto asignado para la categoría
        $budget = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, budget_amount, calculation_mode, budget_percentage, monthly_distribution,
                    alert_threshold, alert_on_exceed, notify_creator, notify_admins, notify_emails,
                    created_at, updated_at
             FROM {$b_table}
             WHERE category_id = %d
               AND is_active = 1
               AND (fiscal_year = %d OR (start_date <= %s AND end_date >= %s))
             ORDER BY id DESC LIMIT 1",
            $category_id, $fiscal_year, $end_date, $start_date
        ) );

        $budget_amount     = $budget ? (float) $budget->budget_amount : 0.00;
        $budget_percentage = $budget && $budget->budget_percentage !== null ? (float) $budget->budget_percentage : null;
        $calculation_mode  = $budget ? ( $budget->calculation_mode ?? 'amount' ) : 'amount';

        // Presupuesto global total de egresos de la organización para el año (para contexto de porcentaje)
        $global_expense_total = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM {$t_table}
             WHERE transaction_type = 'expense'
               AND status = 'approved'
               AND deleted_at IS NULL
               AND transaction_date BETWEEN %s AND %s",
            $start_date, $end_date
        ) );

        // Si se configuró porcentaje pero no amount, o viceversa, sincronizar porcentaje representativo
        $pct_of_global_expenses = ( $global_expense_total > 0 && $budget_amount > 0 )
            ? round( ( $budget_amount / $global_expense_total ) * 100, 2 )
            : ( $budget_percentage ?? 0.00 );

        // 3. Subcategorías de esta categoría padre
        $subcategories_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, color, icon, slug, description
             FROM {$c_table}
             WHERE parent_id = %d AND is_active = 1
             ORDER BY name ASC",
            $category_id
        ) );

        $sub_ids = array_map( function( $sub ) { return (int) $sub->id; }, $subcategories_raw );
        $all_family_ids = array_merge( [ $category_id ], $sub_ids );

        // 4. Gasto directo en la categoría padre
        $direct_spent_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(id) AS tx_count, COALESCE(SUM(amount), 0) AS total_spent
             FROM {$t_table}
             WHERE (category_id = %d OR expense_category_id = %d)
               AND transaction_type = 'expense'
               AND status = 'approved'
               AND deleted_at IS NULL
               AND transaction_date BETWEEN %s AND %s",
            $category_id, $category_id, $start_date, $end_date
        ) );
        $direct_spent = $direct_spent_row ? (float) $direct_spent_row->total_spent : 0.00;
        $direct_tx    = $direct_spent_row ? (int) $direct_spent_row->tx_count : 0;

        // 5. Gasto detallado de cada subcategoría
        $subcategories_stats = [];
        $subs_total_spent    = 0.00;
        $subs_total_tx       = 0;

        foreach ( $subcategories_raw as $sub ) {
            $sid = (int) $sub->id;
            $sub_spent_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(id) AS tx_count, COALESCE(SUM(amount), 0) AS total_spent
                 FROM {$t_table}
                 WHERE (category_id = %d OR expense_category_id = %d)
                   AND transaction_type = 'expense'
                   AND status = 'approved'
                   AND deleted_at IS NULL
                   AND transaction_date BETWEEN %s AND %s",
                $sid, $sid, $start_date, $end_date
            ) );

            $sub_spent = $sub_spent_row ? (float) $sub_spent_row->total_spent : 0.00;
            $sub_tx    = $sub_spent_row ? (int) $sub_spent_row->tx_count : 0;

            $subs_total_spent += $sub_spent;
            $subs_total_tx    += $sub_tx;

            $subcategories_stats[] = [
                'id'          => $sid,
                'name'        => $sub->name,
                'color'       => ! empty( $sub->color ) ? $sub->color : ( $parent->color ?: '#3b82f6' ),
                'icon'        => ! empty( $sub->icon ) ? $sub->icon : 'dashicons-tag',
                'slug'        => $sub->slug,
                'tx_count'    => $sub_tx,
                'total_spent' => $sub_spent,
            ];
        }

        // Total consolidado de la familia (Padre + Todas sus Subcategorías)
        $consolidated_spent = $direct_spent + $subs_total_spent;
        $consolidated_tx    = $direct_tx + $subs_total_tx;

        // Calcular porcentaje que representa cada subcategoría sobre el gasto total
        foreach ( $subcategories_stats as &$s_stat ) {
            $s_stat['pct_of_category'] = $consolidated_spent > 0
                ? round( ( $s_stat['total_spent'] / $consolidated_spent ) * 100, 1 )
                : 0.0;
            $s_stat['pct_of_budget']   = $budget_amount > 0
                ? round( ( $s_stat['total_spent'] / $budget_amount ) * 100, 1 )
                : 0.0;
        }
        unset( $s_stat );

        // Ordenar subcategorías por gasto descendente (las que más gastaron primero)
        usort( $subcategories_stats, function( $a, $b ) {
            return $b['total_spent'] <=> $a['total_spent'];
        } );

        // 6. Evolución mensualizada en 12 meses (gasto mes a mes)
        $placeholders_family = implode( ',', array_fill( 0, count( $all_family_ids ), '%d' ) );
        $monthly_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT MONTH(transaction_date) AS tx_month,
                    COUNT(id) AS tx_count,
                    COALESCE(SUM(amount), 0) AS month_spent
             FROM {$t_table}
             WHERE (category_id IN ({$placeholders_family}) OR expense_category_id IN ({$placeholders_family}))
               AND transaction_type = 'expense'
               AND status = 'approved'
               AND deleted_at IS NULL
               AND transaction_date BETWEEN %s AND %s
             GROUP BY MONTH(transaction_date)
             ORDER BY tx_month ASC",
            ...array_merge( $all_family_ids, $all_family_ids, [ $start_date, $end_date ] )
        ) );

        $monthly_spent_map = [];
        foreach ( $monthly_rows as $mr ) {
            $monthly_spent_map[ (int) $mr->tx_month ] = (float) $mr->month_spent;
        }

        // Distribución mensual del presupuesto (si se guardó manual en JSON o prorrateo / 12)
        $custom_monthly = [];
        if ( ! empty( $budget->monthly_distribution ) ) {
            $decoded = json_decode( $budget->monthly_distribution, true );
            if ( is_array( $decoded ) ) {
                $custom_monthly = $decoded;
            }
        }

        $default_month_budget = $budget_amount > 0 ? round( $budget_amount / 12, 2 ) : 0.00;
        $monthly_trend        = [];
        $accum_budget         = 0.00;
        $accum_spent          = 0.00;
        $month_names          = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        for ( $m = 1; $m <= 12; $m++ ) {
            $m_budget = isset( $custom_monthly[ $m ] ) ? (float) $custom_monthly[ $m ] : $default_month_budget;
            $m_spent  = isset( $monthly_spent_map[ $m ] ) ? (float) $monthly_spent_map[ $m ] : 0.00;

            $accum_budget += $m_budget;
            $accum_spent  += $m_spent;

            $monthly_trend[] = [
                'month'               => $m,
                'month_name'          => $month_names[ $m ],
                'budget'              => $m_budget,
                'spent'               => $m_spent,
                'cumulative_budget'   => round( $accum_budget, 2 ),
                'cumulative_spent'    => round( $accum_spent, 2 ),
                'diff'                => round( $m_budget - $m_spent, 2 ),
            ];
        }

        // 7. Métricas de Cierre y Proyección
        $current_year   = (int) current_time( 'Y' );
        $is_year_closed = $fiscal_year < $current_year;

        // Días transcurridos
        $year_start_ts = strtotime( $start_date );
        $year_end_ts   = strtotime( $end_date );
        $today_ts      = strtotime( current_time( 'Y-m-d' ) );

        $total_days_in_year = (int) round( ( $year_end_ts - $year_start_ts ) / 86400 ) + 1;

        if ( $is_year_closed ) {
            $days_elapsed = $total_days_in_year;
            $projection   = $consolidated_spent;
        } else {
            $days_elapsed = max( 1, min( $total_days_in_year, (int) round( ( $today_ts - $year_start_ts ) / 86400 ) + 1 ) );
            $projection   = $days_elapsed > 0
                ? round( ( $consolidated_spent / $days_elapsed ) * $total_days_in_year, 2 )
                : $consolidated_spent;
        }

        $execution_pct = $budget_amount > 0 ? round( ( $consolidated_spent / $budget_amount ) * 100, 1 ) : 0.0;
        $remaining     = max( 0, $budget_amount - $consolidated_spent );
        $overrun       = max( 0, $consolidated_spent - $budget_amount );

        $status = 'no_budget';
        if ( $budget_amount > 0 ) {
            if ( $execution_pct >= 100 ) {
                $status = 'overrun';
            } elseif ( $execution_pct >= 90 ) {
                $status = 'critical';
            } elseif ( $execution_pct >= 70 ) {
                $status = 'warning';
            } else {
                $status = 'ok';
            }
        }

        $category_data = [
            'id'                  => (int) $parent->id,
            'name'                => $parent->name,
            'color'               => ! empty( $parent->color ) ? $parent->color : '#3b82f6',
            'icon'                => ! empty( $parent->icon ) ? $parent->icon : 'dashicons-category',
            'slug'                => $parent->slug,
            'type'                => $parent->type,
            'description'         => $parent->description,
            'subcategories_count' => count( $subcategories_raw ),
        ];

        return [
            'category' => $category_data,
            'family'   => $category_data, // Alias retrocompatible
            'budget' => [
                'has_budget'             => (bool) $budget,
                'budget_id'              => $budget ? (int) $budget->id : 0,
                'fiscal_year'            => $fiscal_year,
                'budget_amount'          => $budget_amount,
                'budget_annual'          => $budget_amount, // Alias retrocompatible
                'budget_monthly'         => $default_month_budget,
                'budget_percentage'      => $budget_percentage,
                'calculation_mode'       => $calculation_mode,
                'pct_of_global_expenses' => $pct_of_global_expenses,
                'alert_threshold'        => $budget ? (int) $budget->alert_threshold : 80,
                'alert_on_exceed'        => $budget ? (bool) $budget->alert_on_exceed : true,
                'custom_monthly'         => $custom_monthly,
            ],
            'execution' => [
                'total_spent'            => $consolidated_spent,
                'tx_count'               => $consolidated_tx,
                'direct_spent'           => $direct_spent,
                'direct_tx'              => $direct_tx,
                'subs_spent'             => $subs_total_spent,
                'subs_tx'                => $subs_total_tx,
                'remaining'              => $remaining,
                'overrun'                => $overrun,
                'execution_pct'          => $execution_pct,
                'status'                 => $status,
                'is_year_closed'         => $is_year_closed,
                'days_elapsed'           => $days_elapsed,
                'days_in_year'           => $total_days_in_year,
                'projection_year_end'    => $projection,
                'diff_vs_projection'     => $budget_amount > 0 ? round( $budget_amount - $projection, 2 ) : 0.00,
            ],
            'subcategories' => $subcategories_stats,
            'monthly_trend' => $monthly_trend,
        ];
    }

    /* ----------------------------------------------------------------
     * AJAX Handlers para Presupuestos de Categorías
     * -------------------------------------------------------------- */

    /**
     * AJAX: Obtener categorías y estadísticas completas de la categoría seleccionada
     */
    public static function ajax_get_category_budget_stats() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if (
            ! current_user_can( 'aura_finance_view_all' ) &&
            ! current_user_can( 'manage_options' ) &&
            ! current_user_can( 'aura_finance_budgets' ) &&
            ! current_user_can( 'aura_areas_view_own' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para consultar presupuestos.', 'aura-suite' ) ], 403 );
        }

        $fiscal_year = isset( $_POST['fiscal_year'] ) ? absint( $_POST['fiscal_year'] ) : (int) current_time( 'Y' );
        if ( $fiscal_year < 2000 || $fiscal_year > 2100 ) {
            $fiscal_year = (int) current_time( 'Y' );
        }

        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        // 1. Obtener todas las categorías presupuestables
        $categories = self::get_budgetable_categories( $fiscal_year );

        if ( empty( $categories ) ) {
            wp_send_json_success( [
                'fiscal_year'          => $fiscal_year,
                'categories'           => [],
                'selected_category_id' => 0,
                'stats'                => null,
            ] );
            return;
        }

        // Si no se pasó categoría, tomar la que corresponda (primera con presupuesto o primera en lista)
        if ( ! $category_id ) {
            foreach ( $categories as $c ) {
                if ( $c['has_budget'] ) {
                    $category_id = $c['id'];
                    break;
                }
            }
            if ( ! $category_id ) {
                $category_id = $categories[0]['id'];
            }
        }

        // 2. Obtener estadísticas de la categoría seleccionada
        $stats = self::get_category_budget_stats( $category_id, $fiscal_year );

        wp_send_json_success( [
            'fiscal_year'          => $fiscal_year,
            'categories'           => $categories,
            'selected_category_id' => $category_id,
            'stats'                => $stats,
        ] );
    }

    /**
     * AJAX: Guardar o actualizar presupuesto para una categoría
     */
    public static function ajax_save_category_budget() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para guardar presupuestos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_budgets';

        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
        $fiscal_year = isset( $_POST['fiscal_year'] ) ? absint( $_POST['fiscal_year'] ) : (int) current_time( 'Y' );

        if ( ! $category_id ) {
            wp_send_json_error( [ 'message' => __( 'Debes seleccionar una categoría válida.', 'aura-suite' ) ] );
        }

        $calculation_mode  = isset( $_POST['calculation_mode'] ) && in_array( $_POST['calculation_mode'], [ 'amount', 'percentage' ], true )
            ? sanitize_text_field( $_POST['calculation_mode'] )
            : 'amount';

        $budget_amount     = isset( $_POST['budget_amount'] ) ? max( 0.00, (float) $_POST['budget_amount'] ) : 0.00;
        $budget_percentage = isset( $_POST['budget_percentage'] ) && $_POST['budget_percentage'] !== ''
            ? max( 0.00, min( 100.00, (float) $_POST['budget_percentage'] ) )
            : null;

        $alert_threshold   = isset( $_POST['alert_threshold'] ) ? max( 1, min( 99, absint( $_POST['alert_threshold'] ) ) ) : 80;
        $alert_on_exceed   = ! empty( $_POST['alert_on_exceed'] ) ? 1 : 0;
        $notify_creator    = ! empty( $_POST['notify_creator'] ) ? 1 : 0;
        $notify_admins     = ! empty( $_POST['notify_admins'] ) ? 1 : 0;
        $notify_emails     = isset( $_POST['notify_emails'] ) ? sanitize_textarea_field( $_POST['notify_emails'] ) : '';

        // Distribución mensual si viene especificada
        $monthly_dist_json = null;
        if ( ! empty( $_POST['monthly_distribution'] ) ) {
            $raw_dist = is_array( $_POST['monthly_distribution'] )
                ? $_POST['monthly_distribution']
                : json_decode( stripslashes( $_POST['monthly_distribution'] ), true );

            if ( is_array( $raw_dist ) ) {
                $clean_dist = [];
                for ( $m = 1; $m <= 12; $m++ ) {
                    $clean_dist[ $m ] = isset( $raw_dist[ $m ] ) ? max( 0.00, (float) $raw_dist[ $m ] ) : 0.00;
                }
                $monthly_dist_json = wp_json_encode( $clean_dist );
            }
        }

        $start_date = sprintf( '%04d-01-01', $fiscal_year );
        $end_date   = sprintf( '%04d-12-31', $fiscal_year );
        $now        = current_time( 'mysql' );

        // Verificar si ya existe presupuesto para esta categoría y año fiscal
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE category_id = %d
               AND (fiscal_year = %d OR (start_date <= %s AND end_date >= %s))
               AND is_active = 1
             LIMIT 1",
            $category_id, $fiscal_year, $end_date, $start_date
        ) );

        $data = [
            'category_id'          => $category_id,
            'fiscal_year'          => $fiscal_year,
            'budget_amount'        => $budget_amount,
            'calculation_mode'     => $calculation_mode,
            'budget_percentage'    => $budget_percentage,
            'monthly_distribution' => $monthly_dist_json,
            'period_type'          => 'yearly',
            'start_date'           => $start_date,
            'end_date'             => $end_date,
            'alert_threshold'      => $alert_threshold,
            'alert_on_exceed'      => $alert_on_exceed,
            'notify_creator'       => $notify_creator,
            'notify_admins'        => $notify_admins,
            'notify_emails'        => $notify_emails,
            'alert_sent_threshold' => 0,
            'alert_sent_exceed'    => 0,
            'is_active'            => 1,
            'updated_at'           => $now,
        ];

        $formats = [
            '%d', '%d', '%f', '%s', '%f', '%s', '%s', '%s', '%s',
            '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s'
        ];

        if ( $existing ) {
            $wpdb->update(
                $table,
                $data,
                [ 'id' => (int) $existing->id ],
                $formats,
                [ '%d' ]
            );
            $budget_id = (int) $existing->id;
            $msg = __( 'Presupuesto de la categoría actualizado exitosamente.', 'aura-suite' );
        } else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = $now;
            $formats[] = '%d';
            $formats[] = '%s';

            $wpdb->insert( $table, $data, $formats );
            $budget_id = (int) $wpdb->insert_id;
            $msg = __( 'Presupuesto para la categoría asignado exitosamente.', 'aura-suite' );
        }

        // Retornar estadísticas actualizadas
        $stats = self::get_category_budget_stats( $category_id, $fiscal_year );

        wp_send_json_success( [
            'message'              => $msg,
            'budget_id'            => $budget_id,
            'fiscal_year'          => $fiscal_year,
            'selected_category_id' => $category_id,
            'stats'                => $stats,
        ] );
    }

    /**
     * AJAX: Eliminar presupuesto de una categoría
     */
    public static function ajax_delete_category_budget() {
        check_ajax_referer( 'aura_budgets_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_delete_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para eliminar presupuestos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'aura_finance_budgets';
        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
        $fiscal_year = isset( $_POST['fiscal_year'] ) ? absint( $_POST['fiscal_year'] ) : (int) current_time( 'Y' );

        if ( ! $category_id ) {
            wp_send_json_error( [ 'message' => __( 'Categoría inválida.', 'aura-suite' ) ] );
        }

        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table}
             SET is_active = 0, updated_at = %s
             WHERE category_id = %d
               AND (fiscal_year = %d OR (start_date <= %s AND end_date >= %s))",
            current_time( 'mysql' ),
            $category_id,
            $fiscal_year,
            sprintf( '%04d-12-31', $fiscal_year ),
            sprintf( '%04d-01-01', $fiscal_year )
        ) );

        $stats = self::get_category_budget_stats( $category_id, $fiscal_year );

        wp_send_json_success( [
            'message'              => __( 'Presupuesto de la categoría eliminado.', 'aura-suite' ),
            'fiscal_year'          => $fiscal_year,
            'selected_category_id' => $category_id,
            'stats'                => $stats,
        ] );
    }

    /* ----------------------------------------------------------------
     * Renderizar página admin
     * -------------------------------------------------------------- */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/budgets-page.php';
    }
}
