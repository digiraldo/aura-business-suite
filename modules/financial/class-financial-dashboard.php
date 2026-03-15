<?php
/**
 * Dashboard Financiero — Fase 3, Item 3.1
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_Dashboard {

    public static function init() {
        add_action( 'wp_ajax_aura_get_dashboard_data', array( __CLASS__, 'ajax_get_dashboard_data' ) );

        // FASE E: Invalidar caché cuando se modifiquen transacciones
        $bust_events = [
            'aura_finance_transaction_created',
            'aura_finance_transaction_updated',
            'aura_finance_transaction_trashed',
            'aura_finance_transaction_approved',
            'aura_finance_transaction_rejected',
            'aura_finance_transaction_restored',
            'aura_finance_budget_saved',
            'aura_finance_budget_deleted',
        ];
        foreach ( $bust_events as $event ) {
            add_action( $event, [ __CLASS__, 'bust_cache' ] );
        }
    }

    public static function render() {
        if ( ! current_user_can( 'aura_finance_charts' ) &&
             ! current_user_can( 'aura_finance_view_own' ) &&
             ! current_user_can( 'aura_finance_view_all' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
        }

        $can_view_all = current_user_can( 'aura_finance_view_all' );
        $can_approve  = current_user_can( 'aura_finance_approve' );
        $tx_list_url      = admin_url( 'admin.php?page=aura-financial-transactions' );
        $pending_list_url = admin_url( 'admin.php?page=aura-financial-pending' );
        ?>
        <div class="wrap">
            <div class="aura-dash-header">
                <h1>
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php esc_html_e( 'Dashboard Financiero', 'aura-suite' ); ?>
                </h1>
                <button id="dash-refresh" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Refrescar', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-period-bar">
                <div class="aura-period-bar__presets">
                    <button class="period-btn" data-period="today"><?php esc_html_e( 'Hoy', 'aura-suite' ); ?></button>
                    <button class="period-btn" data-period="week"><?php esc_html_e( 'Semana', 'aura-suite' ); ?></button>
                    <button class="period-btn active" data-period="month"><?php esc_html_e( 'Mes', 'aura-suite' ); ?></button>
                    <button class="period-btn" data-period="quarter"><?php esc_html_e( 'Trimestre', 'aura-suite' ); ?></button>
                    <button class="period-btn" data-period="year"><?php esc_html_e( 'Año', 'aura-suite' ); ?></button>
                    <button class="period-btn" data-period="custom"><?php esc_html_e( 'Personalizado', 'aura-suite' ); ?></button>
                </div>
                <span class="aura-period-bar__sep"></span>
                <div class="aura-period-bar__custom">
                    <input type="date" id="dash-start" value="<?php echo esc_attr( date( 'Y-m-01' ) ); ?>">
                    <span>—</span>
                    <input type="date" id="dash-end" value="<?php echo esc_attr( date( 'Y-m-t' ) ); ?>">
                    <button id="dash-apply-custom" class="button button-small apply-custom"><?php esc_html_e( 'Aplicar', 'aura-suite' ); ?></button>
                </div>
                <span class="aura-period-bar__sep"></span>
                <label class="aura-period-bar__compare">
                    <input type="checkbox" id="dash-compare">
                    <?php esc_html_e( 'Comparar con período anterior', 'aura-suite' ); ?>
                </label>
            </div>

            <div class="aura-kpis-row">
                <?php if ( $can_view_all ) : ?>
                <div class="aura-kpi aura-kpi--income is-loading">
                    <div class="aura-kpi__label"><span class="dashicons dashicons-arrow-up-alt"></span><?php esc_html_e( 'Total Ingresos', 'aura-suite' ); ?></div>
                    <div class="aura-kpi__value">—</div>
                    <div class="aura-kpi__meta"><span class="aura-kpi__trend" style="display:none"></span></div>
                </div>
                <div class="aura-kpi aura-kpi--expense is-loading">
                    <div class="aura-kpi__label"><span class="dashicons dashicons-arrow-down-alt"></span><?php esc_html_e( 'Total Egresos', 'aura-suite' ); ?></div>
                    <div class="aura-kpi__value">—</div>
                    <div class="aura-kpi__meta"><span class="aura-kpi__trend" style="display:none"></span></div>
                </div>
                <div class="aura-kpi aura-kpi--balance is-loading">
                    <div class="aura-kpi__label"><span class="dashicons dashicons-chart-line"></span><?php esc_html_e( 'Balance Neto', 'aura-suite' ); ?></div>
                    <div class="aura-kpi__value">—</div>
                    <div class="aura-kpi__meta"><span class="aura-kpi__trend" style="display:none"></span></div>
                </div>
                <?php endif; ?>
                <?php if ( $can_approve ) : ?>
                <div class="aura-kpi aura-kpi--pending is-loading">
                    <div class="aura-kpi__label"><span class="dashicons dashicons-clock"></span><?php esc_html_e( 'Pendientes de Aprobación', 'aura-suite' ); ?></div>
                    <div class="aura-kpi__value">—</div>
                    <div class="aura-kpi__meta">
                        <a class="aura-kpi__link" href="<?php echo esc_url( $pending_list_url ); ?>"><?php esc_html_e( 'Ver pendientes →', 'aura-suite' ); ?></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( $can_view_all ) : ?>
            <div class="aura-charts-row">
                <div class="aura-chart-card">
                    <div class="aura-chart-card__header">
                        <h2 class="aura-chart-card__title"><?php esc_html_e( 'Ingresos vs Egresos', 'aura-suite' ); ?></h2>
                        <div class="aura-chart-card__actions">
                            <button id="export-line-png" class="button button-small chart-export-btn"><span class="dashicons dashicons-download"></span> PNG</button>
                        </div>
                    </div>
                    <canvas id="aura-line-chart"></canvas>
                </div>
                <div class="aura-chart-card">
                    <div class="aura-chart-card__header">
                        <h2 class="aura-chart-card__title"><?php esc_html_e( 'Gastos por Categoría', 'aura-suite' ); ?></h2>
                        <div class="aura-chart-card__actions">
                            <button id="export-donut-png" class="button button-small chart-export-btn"><span class="dashicons dashicons-download"></span> PNG</button>
                        </div>
                    </div>
                    <canvas id="aura-donut-chart"></canvas>
                    <div id="aura-donut-legend" class="aura-donut-legend"></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="aura-widgets-row">
                <div class="aura-widget-card">
                    <div class="aura-widget-card__header">
                        <h2 class="aura-widget-card__title"><?php esc_html_e( 'Últimas Transacciones', 'aura-suite' ); ?></h2>
                        <a class="aura-widget-card__link" href="<?php echo esc_url( $tx_list_url ); ?>"><?php esc_html_e( 'Ver todas →', 'aura-suite' ); ?></a>
                    </div>
                    <div id="aura-recent-empty" class="aura-empty-state" style="display:none">
                        <span class="dashicons dashicons-list-view"></span>
                        <p><?php esc_html_e( 'No hay transacciones en este período.', 'aura-suite' ); ?></p>
                    </div>
                    <table class="aura-recent-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                                <th></th>
                                <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Monto', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aura-recent-tbody"></tbody>
                    </table>
                </div>
                <div class="aura-widget-card">
                    <div class="aura-widget-card__header">
                        <h2 class="aura-widget-card__title">
                            <span class="dashicons dashicons-warning" style="color:#f59e0b;font-size:15px;width:15px;height:15px;"></span>
                            <?php esc_html_e( 'Alertas', 'aura-suite' ); ?>
                        </h2>
                    </div>
                    <div id="aura-alerts-empty" class="aura-alerts-empty">
                        <span class="dashicons dashicons-yes-alt" style="color:#10b981;font-size:24px;width:24px;height:24px;"></span>
                        <p><?php esc_html_e( 'Sin alertas activas. ¡Todo en orden!', 'aura-suite' ); ?></p>
                    </div>
                    <div id="aura-alerts-list" class="aura-alerts-list"></div>
                </div>
            </div>

            <?php if ( class_exists( 'Aura_Financial_Budgets' ) ) : ?>
            <!-- Widget Presupuestos (Fase 5, Item 5.1) -->
            <?php Aura_Financial_Budgets::render_dashboard_widget(); ?>
            <?php endif; ?>

            <?php if ( class_exists( 'Aura_Financial_Audit' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'aura_auditor' ) ) ) : ?>
            <!-- Widget Actividad Reciente (Fase 5, Item 5.3) -->
            <?php Aura_Financial_Audit::render_dashboard_widget(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function ajax_get_dashboard_data() {
        check_ajax_referer( 'aura_dashboard_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_charts' ) &&
             ! current_user_can( 'aura_finance_view_own' ) &&
             ! current_user_can( 'aura_finance_view_all' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }

        $start   = sanitize_text_field( $_POST['start'] ?? date( 'Y-m-01' ) );
        $end     = sanitize_text_field( $_POST['end']   ?? date( 'Y-m-t' ) );
        $compare = ! empty( $_POST['compare'] );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) $start = date( 'Y-m-01' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) )   $end   = date( 'Y-m-t' );

        $prev_start = $prev_end = null;
        if ( $compare ) {
            $days       = max( 1, ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS );
            $prev_end   = date( 'Y-m-d', strtotime( $start ) - DAY_IN_SECONDS );
            $prev_start = date( 'Y-m-d', strtotime( $prev_end ) - $days * DAY_IN_SECONDS );
        }

        // ── FASE E: Caché con Transients (5 minutos) ──────────────────
        $cache_key = 'aura_dash_' . self::get_cache_version() . '_'
                   . get_current_user_id() . '_'
                   . md5( $start . '|' . $end . '|' . ( $compare ? '1' : '0' )
                        . '|' . ( $prev_start ?? '' ) . '|' . ( $prev_end ?? '' ) );

        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            wp_send_json_success( $cached );
            return;
        }
        // ──────────────────────────────────────────────────────────────

        $data = array(
            'kpis'        => self::get_kpis( $start, $end, $prev_start, $prev_end ),
            'chart_line'  => self::get_line_data( $start, $end, $prev_start, $prev_end ),
            'chart_donut' => self::get_donut_data( $start, $end ),
            'recent'      => self::get_recent_transactions(),
            'alerts'      => self::get_alerts(),
        );

        set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

        wp_send_json_success( $data );
    }

    private static function get_kpis( $start, $end, $prev_start, $prev_end ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $kpis  = array();

        if ( current_user_can( 'aura_finance_view_all' ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT transaction_type, SUM(amount) AS total
                   FROM {$table}
                  WHERE transaction_date BETWEEN %s AND %s
                    AND status = 'approved'
                    AND deleted_at IS NULL
                  GROUP BY transaction_type",
                $start, $end
            ), ARRAY_A );

            $income = $expense = 0.0;
            foreach ( $rows as $r ) {
                if ( $r['transaction_type'] === 'income' )  $income  = (float) $r['total'];
                if ( $r['transaction_type'] === 'expense' ) $expense = (float) $r['total'];
            }

            $prev_income = $prev_expense = null;
            if ( $prev_start && $prev_end ) {
                $prev = $wpdb->get_results( $wpdb->prepare(
                    "SELECT transaction_type, SUM(amount) AS total
                       FROM {$table}
                      WHERE transaction_date BETWEEN %s AND %s
                        AND status = 'approved'
                        AND deleted_at IS NULL
                      GROUP BY transaction_type",
                    $prev_start, $prev_end
                ), ARRAY_A );
                foreach ( $prev as $r ) {
                    if ( $r['transaction_type'] === 'income' )  $prev_income  = (float) $r['total'];
                    if ( $r['transaction_type'] === 'expense' ) $prev_expense = (float) $r['total'];
                }
            }

            $kpis['income']  = array( 'raw' => $income,  'formatted' => self::fmt_money( $income ),  'pct_change' => self::pct_change( $income,  $prev_income ) );
            $kpis['expense'] = array( 'raw' => $expense, 'formatted' => self::fmt_money( $expense ), 'pct_change' => self::pct_change( $expense, $prev_expense ) );
            $kpis['balance'] = array( 'raw' => $income - $expense, 'formatted' => self::fmt_money( $income - $expense ), 'pct_change' => null );
        }

        if ( current_user_can( 'aura_finance_approve' ) ) {
            $pending_count  = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='pending' AND deleted_at IS NULL" );
            $pending_amount = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status='pending' AND deleted_at IS NULL" );
            $kpis['pending'] = array(
                'raw'        => $pending_count,
                'formatted'  => $pending_count . ' transacciones — ' . self::fmt_money( $pending_amount ),
                'pct_change' => null,
            );
        }

        return $kpis;
    }

    private static function get_line_data( $start, $end, $prev_start, $prev_end ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $days  = max( 1, ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS );

        if ( $days <= 31 )      { $date_fmt = '%Y-%m-%d'; }
        elseif ( $days <= 92 )  { $date_fmt = '%Y-%u'; }
        else                    { $date_fmt = '%Y-%m'; }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE_FORMAT(transaction_date, %s) AS period, transaction_type, SUM(amount) AS total
               FROM {$table}
              WHERE transaction_date BETWEEN %s AND %s
                AND status = 'approved' AND deleted_at IS NULL
              GROUP BY period, transaction_type
              ORDER BY period",
            $date_fmt, $start, $end
        ), ARRAY_A );

        $grouped = array();
        foreach ( $rows as $r ) {
            $p = $r['period'];
            if ( ! isset( $grouped[ $p ] ) ) $grouped[ $p ] = array( 'income' => 0, 'expense' => 0 );
            $grouped[ $p ][ $r['transaction_type'] ] = (float) $r['total'];
        }

        if ( $date_fmt === '%Y-%m' ) {
            $cur = new DateTime( $start );
            $end_dt = new DateTime( $end );
            while ( $cur <= $end_dt ) {
                $k = $cur->format( 'Y-m' );
                if ( ! isset( $grouped[ $k ] ) ) $grouped[ $k ] = array( 'income' => 0, 'expense' => 0 );
                $cur->modify( '+1 month' );
            }
        } elseif ( $date_fmt === '%Y-%m-%d' ) {
            $cur = new DateTime( $start );
            $end_dt = new DateTime( $end );
            while ( $cur <= $end_dt ) {
                $k = $cur->format( 'Y-m-d' );
                if ( ! isset( $grouped[ $k ] ) ) $grouped[ $k ] = array( 'income' => 0, 'expense' => 0 );
                $cur->modify( '+1 day' );
            }
        }
        ksort( $grouped );

        $labels = $income = $expense = array();
        foreach ( $grouped as $p => $v ) {
            if ( $date_fmt === '%Y-%m' ) {
                $dt = DateTime::createFromFormat( 'Y-m', $p );
                $labels[] = $dt ? $dt->format( 'M Y' ) : $p;
            } elseif ( $date_fmt === '%Y-%m-%d' ) {
                $dt = DateTime::createFromFormat( 'Y-m-d', $p );
                $labels[] = $dt ? $dt->format( 'd M' ) : $p;
            } else {
                $labels[] = $p;
            }
            $income[]  = $v['income'];
            $expense[] = $v['expense'];
        }

        $result = compact( 'labels', 'income', 'expense' );

        if ( $prev_start && $prev_end ) {
            $prev_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE_FORMAT(transaction_date, %s) AS period, transaction_type, SUM(amount) AS total
                   FROM {$table}
                  WHERE transaction_date BETWEEN %s AND %s
                    AND status = 'approved' AND deleted_at IS NULL
                  GROUP BY period, transaction_type ORDER BY period",
                $date_fmt, $prev_start, $prev_end
            ), ARRAY_A );
            $pg = array();
            foreach ( $prev_rows as $r ) {
                $p = $r['period'];
                if ( ! isset( $pg[ $p ] ) ) $pg[ $p ] = array( 'income' => 0, 'expense' => 0 );
                $pg[ $p ][ $r['transaction_type'] ] = (float) $r['total'];
            }
            $result['prev_income']  = array_column( array_values( $pg ), 'income' );
            $result['prev_expense'] = array_column( array_values( $pg ), 'expense' );
        }

        return $result;
    }

    private static function get_donut_data( $start, $end ) {
        global $wpdb;
        $table     = $wpdb->prefix . 'aura_finance_transactions';
        $cat_table = $wpdb->prefix . 'aura_finance_categories';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.category_id, c.name AS cat_name, c.color AS cat_color, SUM(t.amount) AS total
               FROM {$table} t LEFT JOIN {$cat_table} c ON c.id = t.category_id
              WHERE t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN %s AND %s
                AND t.status = 'approved' AND t.deleted_at IS NULL
              GROUP BY t.category_id ORDER BY total DESC LIMIT 8",
            $start, $end
        ), ARRAY_A );

        if ( ! $rows ) return array( 'labels' => array(), 'amounts' => array(), 'colors' => array(), 'cat_ids' => array() );

        return array(
            'labels'  => array_map( function ( $r ) { return $r['cat_name'] ?: __( 'Sin categoría', 'aura-suite' ); }, $rows ),
            'amounts' => array_map( function ( $r ) { return (float) $r['total']; }, $rows ),
            'colors'  => array_map( function ( $r ) { return $r['cat_color'] ?: '#6366f1'; }, $rows ),
            'cat_ids' => array_map( function ( $r ) { return (int) $r['category_id']; }, $rows ),
        );
    }

    private static function get_recent_transactions() {
        global $wpdb;
        $table     = $wpdb->prefix . 'aura_finance_transactions';
        $cat_table = $wpdb->prefix . 'aura_finance_categories';
        $user_id   = get_current_user_id();

        $where = current_user_can( 'aura_finance_view_all' )
            ? "WHERE t.deleted_at IS NULL"
            : $wpdb->prepare( "WHERE t.deleted_at IS NULL AND t.created_by = %d", $user_id );

        $rows = $wpdb->get_results(
            "SELECT t.id, t.transaction_type, t.amount, t.transaction_date, t.description, t.status, c.name AS cat_name
               FROM {$table} t LEFT JOIN {$cat_table} c ON c.id = t.category_id
               {$where} ORDER BY t.created_at DESC LIMIT 10",
            ARRAY_A
        );
        if ( ! $rows ) return array();

        $edit_base  = admin_url( 'admin.php?page=aura-financial-edit-transaction&id=' );
        $status_map = array(
            'pending'  => __( 'Pendiente', 'aura-suite' ),
            'approved' => __( 'Aprobada', 'aura-suite' ),
            'rejected' => __( 'Rechazada', 'aura-suite' ),
        );

        return array_map( function ( $r ) use ( $edit_base, $status_map ) {
            return array(
                'id'           => (int) $r['id'],
                'type'         => $r['transaction_type'],
                'amount'       => (float) $r['amount'],
                'formatted'    => number_format( (float) $r['amount'], 2 ),
                'date'         => date_i18n( get_option( 'date_format' ), strtotime( $r['transaction_date'] ) ),
                'description'  => $r['description'],
                'cat_name'     => $r['cat_name'] ?: __( 'Sin cat.', 'aura-suite' ),
                'status'       => $r['status'],
                'status_label' => $status_map[ $r['status'] ] ?? $r['status'],
                'edit_url'     => $edit_base . (int) $r['id'],
            );
        }, $rows );
    }

    private static function get_alerts() {
        global $wpdb;
        $table   = $wpdb->prefix . 'aura_finance_transactions';
        $budgets = $wpdb->prefix . 'aura_finance_budgets';
        $cat_t   = $wpdb->prefix . 'aura_finance_categories';
        $alerts  = array();

        $pending_url = admin_url( 'admin.php?page=aura-financial-pending' );
        $tx_url      = admin_url( 'admin.php?page=aura-financial-transactions' );

        if ( current_user_can( 'aura_finance_approve' ) ) {
            $old_pending = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE status='pending' AND deleted_at IS NULL AND created_at < DATE_SUB(NOW(),INTERVAL 7 DAY)"
            );
            if ( $old_pending > 0 ) {
                $alerts[] = array( 'type' => 'danger', 'message' => sprintf(
                    __( '<a href="%s">%d transacción(es)</a> llevan más de 7 días pendientes de aprobación.', 'aura-suite' ),
                    esc_url( $pending_url ), $old_pending
                ) );
            }

            $total_pending = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE status='pending' AND deleted_at IS NULL"
            );
            if ( $total_pending > 0 ) {
                $alerts[] = array( 'type' => 'warning', 'message' => sprintf(
                    __( '<a href="%s">%d transacción(es)</a> esperan aprobación.', 'aura-suite' ),
                    esc_url( $pending_url ), $total_pending
                ) );
            }
        }

        if ( current_user_can( 'aura_finance_view_all' ) ) {
            $threshold = (float) get_option( 'aura_finance_receipt_required_above', 0 );
            if ( $threshold > 0 ) {
                $no_receipt = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE status='approved' AND deleted_at IS NULL AND (receipt_file IS NULL OR receipt_file='') AND amount >= %f",
                    $threshold
                ) );
                if ( $no_receipt > 0 ) {
                    $alerts[] = array( 'type' => 'warning', 'message' => sprintf(
                        __( '<a href="%s">%d transacción(es)</a> aprobadas sin comprobante (monto ≥ %s).', 'aura-suite' ),
                        esc_url( $tx_url ), $no_receipt, self::fmt_money( $threshold )
                    ) );
                }
            }

            // Presupuestos excedidos
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$budgets}'" ) ) {
                $areas_t = $wpdb->prefix . 'aura_areas';
                $budget_alerts = $wpdb->get_results(
                    "SELECT b.id, 
                            COALESCE(a.name, 'Sin área') AS area_name,
                            c.name AS cat_name, 
                            b.budget_amount, 
                            b.alert_threshold,
                            COALESCE(SUM(t.amount),0) AS spent
                       FROM {$budgets} b
                       LEFT JOIN {$areas_t} a ON a.id = b.area_id
                       LEFT JOIN {$cat_t} c ON c.id = b.category_id
                       LEFT JOIN {$table} t ON t.area_id = b.area_id
                            AND t.status = 'approved' AND t.deleted_at IS NULL
                            AND t.transaction_type = 'expense'
                            AND t.transaction_date BETWEEN b.start_date AND b.end_date
                      WHERE b.is_active = 1
                      GROUP BY b.id
                     HAVING spent >= ( b.budget_amount * b.alert_threshold / 100 )",
                    ARRAY_A
                );
                foreach ( $budget_alerts as $ba ) {
                    $pct = $ba['budget_amount'] > 0 ? round( $ba['spent'] / $ba['budget_amount'] * 100 ) : 100;
                    $budget_label = $ba['area_name'];
                    if ( ! empty( $ba['cat_name'] ) ) {
                        $budget_label .= ' / ' . $ba['cat_name'];
                    }
                    $alerts[] = array( 'type' => $pct >= 100 ? 'danger' : 'warning', 'message' => sprintf(
                        __( 'Presupuesto <strong>%s</strong>: %d%% ejecutado (%s de %s).', 'aura-suite' ),
                        esc_html( $budget_label ), $pct,
                        self::fmt_money( $ba['spent'] ), self::fmt_money( $ba['budget_amount'] )
                    ) );
                }
            }
        }

        if ( empty( $alerts ) ) {
            $alerts[] = array( 'type' => 'success', 'message' => __( '¡Todo en orden! Sin alertas activas.', 'aura-suite' ) );
        }

        return $alerts;
    }

    private static function fmt_money( $amount ) {
        return '$' . number_format( (float) $amount, 2, '.', ',' );
    }

    private static function pct_change( $current, $previous ) {
        if ( $previous === null ) return null;
        if ( $previous == 0 )    return $current > 0 ? 100.0 : 0.0;
        return round( ( $current - $previous ) / $previous * 100, 1 );
    }

    /* ============================================================
     * FASE E — Caché de dashboard con Transients
     * ============================================================ */

    /**
     * Devuelve la versión actual de la caché del dashboard.
     * Incrementar la versión invalida todos los Transients existentes
     * sin necesidad de borrarlos con wildcard.
     */
    private static function get_cache_version(): int {
        return (int) get_option( 'aura_finance_cache_version', 1 );
    }

    /**
     * Incrementa la versión de caché.
     * Llamado cuando se guardan/borran transacciones o presupuestos,
     * de modo que la siguiente petición AJAX forzará de nuevo las queries.
     */
    public static function bust_cache(): void {
        $current = self::get_cache_version();
        update_option( 'aura_finance_cache_version', $current + 1, false );
    }

}
