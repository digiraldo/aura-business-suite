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
<div class="aura-app-wrapper aura-inventory-dashboard">

    <?php
    $actions_html = '<a href="' . esc_url( admin_url( 'admin.php?page=aura-inventory-new-equipment' ) ) . '" class="aura-btn aura-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__( 'Nuevo Equipo', 'aura-business-suite' ) . '</a>';
    if ( $can_maint ) {
        $actions_html .= ' <a href="' . esc_url( admin_url( 'admin.php?page=aura-inventory-new-maintenance' ) ) . '" class="aura-btn aura-btn-secondary"><span class="dashicons dashicons-admin-tools"></span> ' . esc_html__( 'Registrar Mantenimiento', 'aura-business-suite' ) . '</a>';
    }

    Aura_UI::render_page_header( [
        'variant'     => 'wow-vip',
        'title'       => __( 'Dashboard de Inventario', 'aura-business-suite' ),
        'subtitle'    => __( 'Monitoreo de equipos, mantenimientos programados, costos y estado operativo', 'aura-business-suite' ),
        'icon'        => 'dashicons-clipboard',
        'breadcrumbs' => [
            [ 'label' => __( 'Inventario', 'aura-business-suite' ), 'url' => admin_url( 'admin.php?page=aura-inventory' ) ],
            [ 'label' => __( 'Dashboard', 'aura-business-suite' ) ],
        ],
        'actions_html'=> $actions_html,
    ] );

    if ( $kpis['overdue'] > 0 ) : ?>
    <div class="notice notice-error is-dismissible" style="margin-bottom: var(--aura-space-4, 16px);">
        <p>
            <strong><?php esc_html_e( '⚠️ Mantenimientos vencidos:', 'aura-business-suite' ); ?></strong>
            <?php printf(
                _n( 'Hay %d equipo con mantenimiento vencido.', 'Hay %d equipos con mantenimiento vencido.', $kpis['overdue'], 'aura-business-suite' ),
                $kpis['overdue']
            ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-equipment&maintenance_status=overdue' ) ); ?>">
                <?php esc_html_e( 'Ver equipos →', 'aura-business-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif;

    if ( $kpis['overdue_loans'] > 0 ) : ?>
    <div class="notice notice-warning is-dismissible" style="margin-bottom: var(--aura-space-4, 16px);">
        <p>
            <strong><?php esc_html_e( '📦 Préstamos vencidos:', 'aura-business-suite' ); ?></strong>
            <?php printf(
                _n( 'Hay %d equipo prestado sin devolver (vencido).', 'Hay %d equipos prestados sin devolver (vencidos).', $kpis['overdue_loans'], 'aura-business-suite' ),
                $kpis['overdue_loans']
            ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-loans' ) ); ?>">
                <?php esc_html_e( 'Ver préstamos →', 'aura-business-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif;

    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-total-equipment-val',
            'icon'     => 'dashicons-archive',
            'value'    => number_format_i18n( (int) $kpis['total_equipment'] ),
            'label'    => __( 'Equipos Registrados', 'aura-business-suite' ),
            'variant'  => 'primary',
            'tooltip'  => __( 'Total de activos físicos en el catálogo', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-with-maint-val',
            'icon'     => 'dashicons-calendar-alt',
            'value'    => number_format_i18n( (int) $kpis['with_maintenance'] ),
            'label'    => __( 'Con Mantenimiento Periódico', 'aura-business-suite' ),
            'variant'  => 'count',
            'tooltip'  => __( 'Equipos con rutinas de servicio activas', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-overdue-val',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( (int) $kpis['overdue'] ),
            'label'    => __( 'Mantenimientos Vencidos', 'aura-business-suite' ),
            'variant'  => $kpis['overdue'] > 0 ? 'danger' : 'success',
            'tooltip'  => __( 'Equipos que debieron recibir mantenimiento', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-upcoming7-val',
            'icon'     => 'dashicons-clock',
            'value'    => number_format_i18n( (int) $kpis['upcoming7'] ),
            'label'    => __( 'Próximos 7 Días', 'aura-business-suite' ),
            'variant'  => $kpis['upcoming7'] > 0 ? 'warning' : 'success',
            'tooltip'  => __( 'Mantenimientos agendados para esta semana', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-upcoming15-val',
            'icon'     => 'dashicons-calendar',
            'value'    => number_format_i18n( (int) $kpis['upcoming15'] ),
            'label'    => __( 'Próximos 15 Días', 'aura-business-suite' ),
            'variant'  => $kpis['upcoming15'] > 0 ? 'warning' : 'success',
            'tooltip'  => __( 'Mantenimientos agendados para la quincena', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-cost-month-val',
            'icon'     => 'dashicons-money-alt',
            'value'    => $currency . number_format( (float) $kpis['cost_month'], 2 ),
            'label'    => sprintf( __( 'Costo Mant. %s', 'aura-business-suite' ), date( 'M Y' ) ),
            'variant'  => 'success',
            'tooltip'  => __( 'Gasto total de mantenimiento en el mes en curso', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-cost-year-val',
            'icon'     => 'dashicons-chart-area',
            'value'    => $currency . number_format( (float) $kpis['cost_year'], 2 ),
            'label'    => sprintf( __( 'Costo Mant. %s', 'aura-business-suite' ), date( 'Y' ) ),
            'variant'  => 'expense',
            'tooltip'  => __( 'Gasto acumulado de mantenimiento en el año fiscal', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-active-loans-val',
            'icon'     => 'dashicons-share',
            'value'    => number_format_i18n( (int) $kpis['active_loans'] ),
            'label'    => __( 'Préstamos Activos', 'aura-business-suite' ),
            'variant'  => 'info',
            'tooltip'  => __( 'Equipos actualmente en poder de terceros o personal', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-overdue-loans-val',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( (int) $kpis['overdue_loans'] ),
            'label'    => __( 'Préstamos Vencidos', 'aura-business-suite' ),
            'variant'  => $kpis['overdue_loans'] > 0 ? 'danger' : 'success',
            'tooltip'  => __( 'Equipos prestados fuera de la fecha límite acordada', 'aura-business-suite' ),
        ],
    ] );
    ?>

    <!-- ═══ FILA 2: Gráficas ══════════════════════════════════════════════ -->
    <div class="aura-dash-charts-row" style="margin-top: var(--aura-space-6, 24px);">

        <!-- Widget 1: Estado del inventario (dona) -->
        <div class="aura-glass-card aura-dash-chart-card">
            <div class="aura-dash-chart-header" style="margin-bottom:16px;">
                <h3 style="margin:0; font-size:15px; font-weight:600;"><?php esc_html_e( 'Estado del inventario', 'aura-business-suite' ); ?></h3>
                <div class="aura-dash-chart-spinner spinner"></div>
            </div>
            <div id="aura-dash-status-chart" class="aura-dash-apexchart"></div>
        </div>

        <!-- Widget 2: Costos por tipo de mantenimiento (barras) -->
        <div class="aura-glass-card aura-dash-chart-card aura-dash-chart-wide">
            <div class="aura-dash-chart-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px;">
                <h3 style="margin:0; font-size:15px; font-weight:600;"><?php esc_html_e( 'Costos por tipo de mantenimiento', 'aura-business-suite' ); ?></h3>
                <div class="aura-dash-period-tabs" style="display:flex; gap:4px;">
                    <button class="aura-btn aura-btn-secondary aura-btn-sm aura-dash-period active" data-period="month"><?php esc_html_e( 'Este mes', 'aura-business-suite' ); ?></button>
                    <button class="aura-btn aura-btn-ghost aura-btn-sm aura-dash-period" data-period="quarter"><?php esc_html_e( 'Trimestre', 'aura-business-suite' ); ?></button>
                    <button class="aura-btn aura-btn-ghost aura-btn-sm aura-dash-period" data-period="year"><?php esc_html_e( 'Este año', 'aura-business-suite' ); ?></button>
                </div>
                <div class="aura-dash-chart-spinner spinner"></div>
            </div>
            <div id="aura-dash-cost-chart" class="aura-dash-apexchart"></div>
        </div>

    </div><!-- .aura-dash-charts-row -->

    <!-- ═══ FILA 3: Calendario próximos 30 días ═══════════════════════════ -->
    <div class="aura-glass-card aura-dash-section-card" style="margin-top: var(--aura-space-6, 24px);">
        <div class="aura-dash-section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px;">
            <h3 style="margin:0; font-size:15px; font-weight:600;"><?php esc_html_e( 'Calendario de mantenimientos — próximos 30 días', 'aura-business-suite' ); ?></h3>
            <div class="aura-dash-legend" style="display:flex; gap:12px; font-size:12px; align-items:center;">
                <span class="aura-dash-legend-dot overdue"></span><?php esc_html_e( 'Vencido', 'aura-business-suite' ); ?>
                <span class="aura-dash-legend-dot urgent"></span><?php esc_html_e( 'Urgente (≤3d)', 'aura-business-suite' ); ?>
                <span class="aura-dash-legend-dot warning"></span><?php esc_html_e( 'Próximo (≤7d)', 'aura-business-suite' ); ?>
                <span class="aura-dash-legend-dot ok"></span><?php esc_html_e( 'En los próximos 30d', 'aura-business-suite' ); ?>
            </div>
            <div class="aura-dash-chart-spinner spinner" id="aura-dash-cal-spinner"></div>
        </div>
        <div id="aura-dash-calendar-list" class="aura-dash-calendar-list">
            <span class="spinner is-active" style="float:none;display:block;margin:20px auto;"></span>
        </div>
    </div>

    <!-- ═══ FILA 4: Equipos críticos ═════════════════════════════════════ -->
    <div class="aura-glass-card aura-dash-section-card" style="margin-top: var(--aura-space-6, 24px);">
        <div class="aura-dash-section-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:15px; font-weight:600; color:var(--aura-danger-600);"><span class="dashicons dashicons-warning" style="color:var(--aura-danger-500);"></span> <?php esc_html_e( 'Equipos que requieren atención', 'aura-business-suite' ); ?></h3>
            <div class="aura-dash-chart-spinner spinner" id="aura-dash-crit-spinner"></div>
        </div>
        <div id="aura-dash-critical" class="aura-dash-critical-wrap">
            <span class="spinner is-active" style="float:none;display:block;margin:20px auto;"></span>
        </div>
    </div>

    <!-- ═══ Accesos rápidos ═══════════════════════════════════════════════ -->
    <div class="aura-glass-card aura-inv-quick-links" style="margin-top: var(--aura-space-6, 24px);">
        <div class="aura-inv-quick-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-equipment' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content:flex-start; padding:12px 16px;">
                <span class="dashicons dashicons-archive"></span>
                <span><?php esc_html_e( 'Ver Equipos', 'aura-business-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-new-equipment' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content:flex-start; padding:12px 16px;">
                <span class="dashicons dashicons-plus-alt"></span>
                <span><?php esc_html_e( 'Registrar Equipo', 'aura-business-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-maintenance' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content:flex-start; padding:12px 16px;">
                <span class="dashicons dashicons-admin-tools"></span>
                <span><?php esc_html_e( 'Mantenimientos', 'aura-business-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-loans' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content:flex-start; padding:12px 16px;">
                <span class="dashicons dashicons-share"></span>
                <span><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-reports' ) ); ?>" class="aura-btn aura-btn-secondary" style="justify-content:flex-start; padding:12px 16px;">
                <span class="dashicons dashicons-chart-bar"></span>
                <span><?php esc_html_e( 'Reportes', 'aura-business-suite' ); ?></span>
            </a>
        </div>
    </div>

</div><!-- .aura-app-wrapper -->

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
