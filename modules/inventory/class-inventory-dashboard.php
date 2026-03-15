<?php
/**
 * Dashboard del Módulo de Inventario — FASE 4 completo
 *
 * KPIs, gráficas (ApexCharts), calendario de mantenimientos, equipos
 * críticos y widget del panel de WordPress.
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Dashboard {

    // ──────────────────────────────────────────────────────────────
    // INIT
    // ──────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'aura_inventory_dashboard_kpis'          => 'ajax_kpis',
            'aura_inventory_dashboard_status_chart'   => 'ajax_status_chart',
            'aura_inventory_dashboard_cost_chart'     => 'ajax_cost_chart',
            'aura_inventory_dashboard_calendar'       => 'ajax_calendar',
            'aura_inventory_dashboard_critical_list'  => 'ajax_critical_list',
        ];
        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $handler ] );
        }
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'register_wp_dashboard_widget' ] );
    }

    // ──────────────────────────────────────────────────────────────
    // RENDER PRINCIPAL
    // ──────────────────────────────────────────────────────────────

    public static function render(): void {
        if (
            ! current_user_can( 'aura_inventory_view_all' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( __( 'No tienes permisos para ver esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/dashboard.php';
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX: LOS 9 KPIs DEL PRD
    // ──────────────────────────────────────────────────────────────

    public static function ajax_kpis(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $te    = $wpdb->prefix . 'aura_inventory_equipment';
        $tm    = $wpdb->prefix . 'aura_inventory_maintenance';
        $tl    = $wpdb->prefix . 'aura_inventory_loans';
        $today = date( 'Y-m-d' );

        wp_send_json_success( self::get_initial_kpis() );
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX: GRÁFICO DE DONA — estado del inventario
    // ──────────────────────────────────────────────────────────────

    public static function ajax_status_chart(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $te   = $wpdb->prefix . 'aura_inventory_equipment';
        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt FROM {$te}
             WHERE deleted_at IS NULL GROUP BY status ORDER BY cnt DESC"
        );

        $label_map = [
            'available'   => __( 'Disponible',       'aura-suite' ),
            'in_use'      => __( 'En uso',            'aura-suite' ),
            'maintenance' => __( 'Mantenimiento',     'aura-suite' ),
            'repair'      => __( 'Reparación',        'aura-suite' ),
            'retired'     => __( 'Retirado',          'aura-suite' ),
        ];
        $color_map = [
            'available'   => '#22c55e',
            'in_use'      => '#3b82f6',
            'maintenance' => '#f59e0b',
            'repair'      => '#ef4444',
            'retired'     => '#8b5cf6',
        ];

        $series = $labels = $colors = [];
        foreach ( $rows as $r ) {
            $series[] = (int)   $r->cnt;
            $labels[] = $label_map[ $r->status ] ?? $r->status;
            $colors[] = $color_map[ $r->status ] ?? '#94a3b8';
        }

        wp_send_json_success( compact( 'series', 'labels', 'colors' ) );
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX: GRÁFICO DE BARRAS — costos por tipo (con filtro período)
    // ──────────────────────────────────────────────────────────────

    public static function ajax_cost_chart(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $period  = sanitize_text_field( $_POST['period'] ?? 'month' );
        $months  = [ 'month' => 1, 'quarter' => 3, 'year' => 12 ][ $period ] ?? 1;
        $from    = date( 'Y-m-d', strtotime( "-{$months} months" ) );

        global $wpdb;
        $tm   = $wpdb->prefix . 'aura_inventory_maintenance';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT type, COALESCE(SUM(total_cost),0) AS total, COUNT(*) AS cnt
             FROM {$tm} WHERE maintenance_date >= %s
             GROUP BY type ORDER BY total DESC",
            $from
        ) );

        $lmap = [
            'preventive'   => __( 'Preventivo',       'aura-suite' ),
            'corrective'   => __( 'Correctivo',       'aura-suite' ),
            'oil_change'   => __( 'Cambio de aceite', 'aura-suite' ),
            'cleaning'     => __( 'Limpieza',         'aura-suite' ),
            'inspection'   => __( 'Inspección',       'aura-suite' ),
            'major_repair' => __( 'Rep. mayor',       'aura-suite' ),
        ];
        $cmap = [
            'preventive'   => '#3b82f6',
            'corrective'   => '#ef4444',
            'oil_change'   => '#f59e0b',
            'cleaning'     => '#10b981',
            'inspection'   => '#8b5cf6',
            'major_repair' => '#dc2626',
        ];

        $categories = $series = $colors = $counts = [];
        foreach ( $rows as $r ) {
            $categories[] = $lmap[ $r->type ] ?? $r->type;
            $series[]     = round( (float) $r->total, 2 );
            $colors[]     = $cmap[ $r->type ] ?? '#94a3b8';
            $counts[]     = (int)  $r->cnt;
        }

        wp_send_json_success( [
            'categories' => $categories,
            'series'     => $series,
            'colors'     => $colors,
            'counts'     => $counts,
            'currency'   => get_option( 'aura_currency_symbol', '$' ),
        ] );
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX: CALENDARIO — vencidos + próximos 30 días
    // ──────────────────────────────────────────────────────────────

    public static function ajax_calendar(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $te    = $wpdb->prefix . 'aura_inventory_equipment';
        $today = date( 'Y-m-d' );
        $in30  = date( 'Y-m-d', strtotime( '+30 days' ) );

        // Incluye vencidos (< today) + próximos 30 días
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, brand, category, status, next_maintenance_date
             FROM {$te}
             WHERE deleted_at IS NULL AND requires_maintenance=1
               AND next_maintenance_date IS NOT NULL
               AND next_maintenance_date <= %s
             ORDER BY next_maintenance_date ASC LIMIT 60",
            $in30
        ) );

        $items = [];
        foreach ( $rows as $eq ) {
            $diff = (int) round( ( strtotime( $eq->next_maintenance_date ) - strtotime( $today ) ) / DAY_IN_SECONDS );
            $level = $diff < 0 ? 'overdue' : ( $diff <= 3 ? 'urgent' : ( $diff <= 7 ? 'warning' : 'ok' ) );
            $items[] = [
                'id'        => (int) $eq->id,
                'name'      => $eq->name . ( $eq->brand ? ' · ' . $eq->brand : '' ),
                'category'  => $eq->category ?: '—',
                'date'      => $eq->next_maintenance_date,
                'days'      => $diff,
                'level'     => $level,
                'maint_url' => admin_url( 'admin.php?page=aura-inventory-new-maintenance&equipment_id=' . $eq->id ),
            ];
        }

        wp_send_json_success( [ 'items' => $items, 'today' => $today ] );
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX: LISTA CRÍTICA
    // ──────────────────────────────────────────────────────────────

    public static function ajax_critical_list(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $te    = $wpdb->prefix . 'aura_inventory_equipment';
        $tl    = $wpdb->prefix . 'aura_inventory_loans';
        $today = date( 'Y-m-d' );

        $overdue_maint = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, brand, status, next_maintenance_date, 'overdue_maint' AS alert_type
             FROM {$te} WHERE deleted_at IS NULL AND requires_maintenance=1
               AND next_maintenance_date < %s AND next_maintenance_date IS NOT NULL
             ORDER BY next_maintenance_date ASC LIMIT 10",
            $today
        ) );

        $in_repair = $wpdb->get_results(
            "SELECT id, name, brand, status, NULL AS next_maintenance_date, 'repair' AS alert_type
             FROM {$te} WHERE deleted_at IS NULL AND status='repair'
             ORDER BY name ASC LIMIT 5"
        );

        $overdue_loans = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.name, e.brand, e.status,
                    l.expected_return_date AS next_maintenance_date,
                    'overdue_loan' AS alert_type, l.id AS loan_id
             FROM {$tl} l
             INNER JOIN {$te} e ON l.equipment_id = e.id
             WHERE l.actual_return_date IS NULL AND l.expected_return_date < %s
             ORDER BY l.expected_return_date ASC LIMIT 5",
            $today
        ) );

        wp_send_json_success( compact( 'overdue_maint', 'in_repair', 'overdue_loans' ) );
    }

    // ──────────────────────────────────────────────────────────────
    // WIDGET DEL PANEL DE WORDPRESS
    // ──────────────────────────────────────────────────────────────

    public static function register_wp_dashboard_widget(): void {
        if ( ! current_user_can( 'aura_inventory_view_all' ) && ! current_user_can( 'manage_options' ) ) return;
        wp_add_dashboard_widget(
            'aura_inventory_widget',
            __( '⚠️ Inventario — Mantenimientos', 'aura-suite' ),
            [ __CLASS__, 'render_wp_widget' ]
        );
    }

    public static function render_wp_widget(): void {
        global $wpdb;
        $te    = $wpdb->prefix . 'aura_inventory_equipment';
        $tl    = $wpdb->prefix . 'aura_inventory_loans';
        $today = date( 'Y-m-d' );
        $in7   = date( 'Y-m-d', strtotime( '+7 days' ) );

        $overdue = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, next_maintenance_date FROM {$te}
             WHERE deleted_at IS NULL AND requires_maintenance=1
               AND next_maintenance_date < %s AND next_maintenance_date IS NOT NULL
             ORDER BY next_maintenance_date ASC LIMIT 5",
            $today
        ) );

        $upcoming = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, next_maintenance_date FROM {$te}
             WHERE deleted_at IS NULL AND requires_maintenance=1
               AND next_maintenance_date BETWEEN %s AND %s
             ORDER BY next_maintenance_date ASC LIMIT 5",
            $today, $in7
        ) );

        $overdue_loans = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tl} WHERE actual_return_date IS NULL AND expected_return_date < %s",
            $today
        ) );
        ?>
        <div class="aura-inv-wp-widget">
            <?php if ( ! empty( $overdue ) ) : ?>
            <p style="color:#d63638;font-weight:600;margin-bottom:5px;">
                <?php printf(
                    _n( '%d equipo con mantenimiento vencido:', '%d equipos con mantenimiento vencido:', count( $overdue ), 'aura-suite' ),
                    count( $overdue )
                ); ?>
            </p>
            <ul style="margin:0 0 10px 18px;">
                <?php foreach ( $overdue as $eq ) :
                    $d = (int) abs( round( ( strtotime( $eq->next_maintenance_date ) - strtotime( $today ) ) / DAY_IN_SECONDS ) ); ?>
                <li>
                    <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-new-maintenance&equipment_id=' . $eq->id ); ?>"><?php echo esc_html( $eq->name ); ?></a>
                    — <strong style="color:#d63638;"><?php printf( _n( 'Vencido hace %d día', 'Vencido hace %d días', $d, 'aura-suite' ), $d ); ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ( ! empty( $upcoming ) ) : ?>
            <p style="color:#996800;font-weight:600;margin-bottom:5px;">
                <?php printf(
                    _n( '%d próximo en 7 días:', '%d próximos en 7 días:', count( $upcoming ), 'aura-suite' ),
                    count( $upcoming )
                ); ?>
            </p>
            <ul style="margin:0 0 10px 18px;">
                <?php foreach ( $upcoming as $eq ) :
                    $d = (int) round( ( strtotime( $eq->next_maintenance_date ) - strtotime( $today ) ) / DAY_IN_SECONDS ); ?>
                <li>
                    <?php echo esc_html( $eq->name ); ?>
                    — <span style="color:#996800;"><?php printf( _n( 'En %d día', 'En %d días', $d, 'aura-suite' ), $d ); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ( empty( $overdue ) && empty( $upcoming ) ) : ?>
            <p style="color:#00a32a;">✅ <?php _e( 'Sin mantenimientos urgentes.', 'aura-suite' ); ?></p>
            <?php endif; ?>

            <?php if ( $overdue_loans > 0 ) : ?>
            <p style="color:#d63638;margin-top:6px;">
                <?php printf(
                    _n( '📦 %d préstamo vencido sin devolver.', '📦 %d préstamos vencidos sin devolver.', $overdue_loans, 'aura-suite' ),
                    $overdue_loans
                ); ?>
                <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-loans' ); ?>"><?php _e( 'Ver →', 'aura-suite' ); ?></a>
            </p>
            <?php endif; ?>

            <p style="margin-top:10px;">
                <a href="<?php echo admin_url( 'admin.php?page=aura-inventory' ); ?>" class="button button-small">
                    <?php _e( 'Dashboard Inventario →', 'aura-suite' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // HELPER — KPIs sincrónicos para carga inicial del template
    // ──────────────────────────────────────────────────────────────

    public static function get_initial_kpis(): array {
        global $wpdb;
        $te    = $wpdb->prefix . 'aura_inventory_equipment';
        $tm    = $wpdb->prefix . 'aura_inventory_maintenance';
        $tl    = $wpdb->prefix . 'aura_inventory_loans';
        $today = date( 'Y-m-d' );

        return [
            'total_equipment'  => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$te} WHERE deleted_at IS NULL" ),
            'with_maintenance' => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$te} WHERE deleted_at IS NULL AND requires_maintenance=1" ),
            'overdue'          => (int)   $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$te} WHERE deleted_at IS NULL AND requires_maintenance=1 AND next_maintenance_date < %s AND next_maintenance_date IS NOT NULL", $today ) ),
            'upcoming7'        => (int)   $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$te} WHERE deleted_at IS NULL AND requires_maintenance=1 AND next_maintenance_date BETWEEN %s AND %s", $today, date( 'Y-m-d', strtotime( '+7 days' ) ) ) ),
            'upcoming15'       => (int)   $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$te} WHERE deleted_at IS NULL AND requires_maintenance=1 AND next_maintenance_date BETWEEN %s AND %s", $today, date( 'Y-m-d', strtotime( '+15 days' ) ) ) ),
            'cost_month'       => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_cost),0) FROM {$tm} WHERE maintenance_date >= %s", date( 'Y-m-01' ) ) ),
            'cost_year'        => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_cost),0) FROM {$tm} WHERE maintenance_date >= %s", date( 'Y-01-01' ) ) ),
            'active_loans'     => (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$tl} WHERE actual_return_date IS NULL" ),
            'overdue_loans'    => (int)   $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tl} WHERE actual_return_date IS NULL AND expected_return_date < %s", $today ) ),
            'currency'         => get_option( 'aura_currency_symbol', '$' ),
        ];
    }
}
