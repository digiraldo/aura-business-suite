<?php
/**
 * Template: Dashboard de Inventario — FASE 4 completo
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// KPIs cargados sincrónicamente para primer render sin AJAX
$kpis     = Aura_Inventory_Dashboard::get_initial_kpis();
$currency = $kpis['currency'];
$can_maint = current_user_can('aura_inventory_maintenance_view') || current_user_can('manage_options');
?>

<div class="wrap aura-inventory-dashboard">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-clipboard" style="font-size:28px;height:28px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
        <?php _e( 'Dashboard de Inventario', 'aura-suite' ); ?>
    </h1>
    <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-new-equipment' ); ?>" class="page-title-action">
        + <?php _e( 'Nuevo Equipo', 'aura-suite' ); ?>
    </a>
    <?php if ( $can_maint ) : ?>
    <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-new-maintenance' ); ?>" class="page-title-action">
        + <?php _e( 'Registrar Mantenimiento', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if ( $kpis['overdue'] > 0 ) : ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <strong><?php _e( '⚠️ Mantenimientos vencidos:', 'aura-suite' ); ?></strong>
            <?php printf(
                _n( 'Hay %d equipo con mantenimiento vencido.', 'Hay %d equipos con mantenimiento vencido.', $kpis['overdue'], 'aura-suite' ),
                $kpis['overdue']
            ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-equipment&maintenance_status=overdue' ); ?>">
                <?php _e( 'Ver equipos →', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    <?php if ( $kpis['overdue_loans'] > 0 ) : ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong><?php _e( '📦 Préstamos vencidos:', 'aura-suite' ); ?></strong>
            <?php printf(
                _n( 'Hay %d equipo prestado sin devolver (vencido).', 'Hay %d equipos prestados sin devolver (vencidos).', $kpis['overdue_loans'], 'aura-suite' ),
                $kpis['overdue_loans']
            ); ?>
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-loans' ); ?>">
                <?php _e( 'Ver préstamos →', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- ═══ FILA 1: 9 KPIs ═══════════════════════════════════════════════ -->
    <div class="aura-inv-kpi-grid aura-dash-kpis">

        <!-- 1 Total equipos -->
        <div class="aura-inv-kpi-card aura-inv-kpi-blue" id="kpi-total-equipment">
            <div class="aura-inv-kpi-icon dashicons dashicons-archive"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['total_equipment'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Equipos registrados', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 2 Con mantenimiento periódico -->
        <div class="aura-inv-kpi-card aura-inv-kpi-gray" id="kpi-with-maint">
            <div class="aura-inv-kpi-icon dashicons dashicons-calendar-alt"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['with_maintenance'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Con mantenimiento periódico', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 3 Mantenimientos vencidos -->
        <div class="aura-inv-kpi-card <?php echo $kpis['overdue'] > 0 ? 'aura-inv-kpi-red' : 'aura-inv-kpi-green'; ?>" id="kpi-overdue">
            <div class="aura-inv-kpi-icon dashicons dashicons-warning"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['overdue'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Mantenimientos vencidos', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 4 Próximos 7 días -->
        <div class="aura-inv-kpi-card <?php echo $kpis['upcoming7'] > 0 ? 'aura-inv-kpi-orange' : 'aura-inv-kpi-green'; ?>" id="kpi-upcoming7">
            <div class="aura-inv-kpi-icon dashicons dashicons-clock"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['upcoming7'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Próximos 7 días', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 5 Próximos 15 días -->
        <div class="aura-inv-kpi-card <?php echo $kpis['upcoming15'] > 0 ? 'aura-inv-kpi-orange' : 'aura-inv-kpi-green'; ?>" id="kpi-upcoming15">
            <div class="aura-inv-kpi-icon dashicons dashicons-calendar"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['upcoming15'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Próximos 15 días', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 6 Costo del mes -->
        <div class="aura-inv-kpi-card aura-inv-kpi-green" id="kpi-cost-month">
            <div class="aura-inv-kpi-icon dashicons dashicons-money-alt"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $currency . number_format( $kpis['cost_month'], 2 ); ?></span>
                <span class="aura-inv-kpi-label"><?php printf( __( 'Costo mantenimientos %s', 'aura-suite' ), date('M Y') ); ?></span>
            </div>
        </div>

        <!-- 7 Costo del año -->
        <div class="aura-inv-kpi-card aura-inv-kpi-purple" id="kpi-cost-year">
            <div class="aura-inv-kpi-icon dashicons dashicons-chart-area"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo $currency . number_format( $kpis['cost_year'], 2 ); ?></span>
                <span class="aura-inv-kpi-label"><?php printf( __( 'Costo mantenimientos %d', 'aura-suite' ), date('Y') ); ?></span>
            </div>
        </div>

        <!-- 8 Préstamos activos -->
        <div class="aura-inv-kpi-card aura-inv-kpi-blue" id="kpi-active-loans">
            <div class="aura-inv-kpi-icon dashicons dashicons-share"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['active_loans'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Préstamos activos', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 9 Préstamos vencidos -->
        <div class="aura-inv-kpi-card <?php echo $kpis['overdue_loans'] > 0 ? 'aura-inv-kpi-red' : 'aura-inv-kpi-green'; ?>" id="kpi-overdue-loans">
            <div class="aura-inv-kpi-icon dashicons dashicons-warning"></div>
            <div class="aura-inv-kpi-body">
                <span class="aura-inv-kpi-value"><?php echo number_format( $kpis['overdue_loans'] ); ?></span>
                <span class="aura-inv-kpi-label"><?php _e( 'Préstamos vencidos', 'aura-suite' ); ?></span>
            </div>
        </div>

    </div><!-- .aura-dash-kpis -->

    <!-- ═══ FILA 2: Gráficas ══════════════════════════════════════════════ -->
    <div class="aura-dash-charts-row">

        <!-- Widget 1: Estado del inventario (dona) -->
        <div class="aura-dash-chart-card">
            <div class="aura-dash-chart-header">
                <h3><?php _e( 'Estado del inventario', 'aura-suite' ); ?></h3>
                <div class="aura-dash-chart-spinner spinner"></div>
            </div>
            <div id="aura-dash-status-chart" class="aura-dash-apexchart"></div>
        </div>

        <!-- Widget 2: Costos por tipo de mantenimiento (barras) -->
        <div class="aura-dash-chart-card aura-dash-chart-wide">
            <div class="aura-dash-chart-header">
                <h3><?php _e( 'Costos por tipo de mantenimiento', 'aura-suite' ); ?></h3>
                <div class="aura-dash-period-tabs">
                    <button class="aura-dash-period active" data-period="month"><?php _e( 'Este mes', 'aura-suite' ); ?></button>
                    <button class="aura-dash-period" data-period="quarter"><?php _e( 'Trimestre', 'aura-suite' ); ?></button>
                    <button class="aura-dash-period" data-period="year"><?php _e( 'Este año', 'aura-suite' ); ?></button>
                </div>
                <div class="aura-dash-chart-spinner spinner"></div>
            </div>
            <div id="aura-dash-cost-chart" class="aura-dash-apexchart"></div>
        </div>

    </div><!-- .aura-dash-charts-row -->

    <!-- ═══ FILA 3: Calendario próximos 30 días ═══════════════════════════ -->
    <div class="aura-dash-section-card">
        <div class="aura-dash-section-header">
            <h3><?php _e( 'Calendario de mantenimientos — próximos 30 días', 'aura-suite' ); ?></h3>
            <div class="aura-dash-legend">
                <span class="aura-dash-legend-dot overdue"></span><?php _e( 'Vencido', 'aura-suite' ); ?>
                <span class="aura-dash-legend-dot urgent"></span><?php _e( 'Urgente (≤3d)', 'aura-suite' ); ?>
                <span class="aura-dash-legend-dot warning"></span><?php _e( 'Próximo (≤7d)', 'aura-suite' ); ?>
                <span class="aura-dash-legend-dot ok"></span><?php _e( 'En los próximos 30d', 'aura-suite' ); ?>
            </div>
            <div class="aura-dash-chart-spinner spinner" id="aura-dash-cal-spinner"></div>
        </div>
        <div id="aura-dash-calendar-list" class="aura-dash-calendar-list">
            <span class="spinner is-active" style="float:none;display:block;margin:20px auto;"></span>
        </div>
    </div>

    <!-- ═══ FILA 4: Equipos críticos ═════════════════════════════════════ -->
    <div class="aura-dash-section-card">
        <div class="aura-dash-section-header">
            <h3><?php _e( 'Equipos que requieren atención', 'aura-suite' ); ?></h3>
            <div class="aura-dash-chart-spinner spinner" id="aura-dash-crit-spinner"></div>
        </div>
        <div id="aura-dash-critical" class="aura-dash-critical-wrap">
            <span class="spinner is-active" style="float:none;display:block;margin:20px auto;"></span>
        </div>
    </div>

    <!-- ═══ Accesos rápidos ═══════════════════════════════════════════════ -->
    <div class="aura-inv-quick-links" style="margin-top:20px;">
        <div class="aura-inv-quick-grid">
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-equipment' ); ?>" class="aura-inv-quick-card">
                <span class="dashicons dashicons-archive"></span>
                <span><?php _e( 'Ver Equipos', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-new-equipment' ); ?>" class="aura-inv-quick-card">
                <span class="dashicons dashicons-plus-alt"></span>
                <span><?php _e( 'Registrar Equipo', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-maintenance' ); ?>" class="aura-inv-quick-card">
                <span class="dashicons dashicons-admin-tools"></span>
                <span><?php _e( 'Mantenimientos', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-loans' ); ?>" class="aura-inv-quick-card">
                <span class="dashicons dashicons-share"></span>
                <span><?php _e( 'Préstamos', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=aura-inventory-reports' ); ?>" class="aura-inv-quick-card">
                <span class="dashicons dashicons-chart-bar"></span>
                <span><?php _e( 'Reportes', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

</div><!-- .aura-inventory-dashboard -->

<?php
$_dash_js = wp_json_encode( [
    'ajaxurl'  => admin_url( 'admin-ajax.php' ),
    'nonce'    => wp_create_nonce( 'aura_inventory_nonce' ),
    'currency' => $currency,
    'txt'      => [
        'no_data'       => __( 'Sin datos para el período seleccionado.', 'aura-suite' ),
        'cost_title'    => __( 'Costo total',   'aura-suite' ),
        'events'        => __( 'registros',     'aura-suite' ),
        'total'         => __( 'Total',         'aura-suite' ),
        'units'         => __( 'equipos',       'aura-suite' ),
        'overdue'       => __( 'Vencido',       'aura-suite' ),
        'urgent'        => __( 'Urgente',       'aura-suite' ),
        'warning'       => __( 'Próximo',       'aura-suite' ),
        'ok'            => __( 'Planificado',   'aura-suite' ),
        'days_ago'      => __( 'Vencido hace %d días', 'aura-suite' ),
        'today'         => __( '¡Hoy!',         'aura-suite' ),
        'in_days'       => __( 'En %d días',    'aura-suite' ),
        'register_maint'=> __( 'Registrar mantenimiento', 'aura-suite' ),
        'cal_empty'     => __( '✅ Ningún equipo requiere atención en los próximos 30 días.', 'aura-suite' ),
        'crit_overdue'  => __( 'Mantenimiento vencido',   'aura-suite' ),
        'crit_repair'   => __( 'En reparación',           'aura-suite' ),
        'crit_loan'     => __( 'Préstamo vencido',        'aura-suite' ),
        'no_critical'   => __( '✅ Sin equipos críticos actualmente.', 'aura-suite' ),
    ],
    'edit_url' => admin_url( 'admin.php?page=aura-inventory-equipment&action=edit&id={id}' ),
] );
?>
<script>var auraInventoryDashboard = <?php echo $_dash_js; ?>;</script>
