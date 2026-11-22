<?php
/**
 * Template: Dashboard de Inventario — Migrado al Design System (WOW VIP Gold + Dual Light/Dark)
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$kpis      = Aura_Inventory_Dashboard::get_initial_kpis();
$currency  = $kpis['currency'];
$can_maint = current_user_can( 'aura_inventory_maintenance_view' ) || current_user_can( 'manage_options' );
?>
<div class="aura-app-wrapper aura-inventory-dashboard">
    <div class="wrap aura-app-context">

    <?php
    $header_actions = [
        [
            'label' => __( 'Nuevo Equipo', 'aura-suite' ),
            'url'   => admin_url( 'admin.php?page=aura-inventory-new-equipment' ),
            'class' => 'btn btn-shimmer btn-indigo btn-lift',
            'icon'  => 'dashicons-plus-alt2',
            'attr'  => 'data-tooltip="' . esc_attr__( 'Registrar un nuevo activo o equipo en el inventario', 'aura-suite' ) . '"',
        ],
    ];
    if ( $can_maint ) {
        $header_actions[] = [
            'label' => __( 'Registrar Mantenimiento', 'aura-suite' ),
            'url'   => admin_url( 'admin.php?page=aura-inventory-new-maintenance' ),
            'class' => 'btn btn-glass btn-lift',
            'icon'  => 'dashicons-admin-tools',
            'attr'  => 'data-tooltip="' . esc_attr__( 'Registrar mantenimiento preventivo o correctivo', 'aura-suite' ) . '"',
        ];
    }

    Aura_UI::render_page_header( [
        'variant'     => 'wow-vip',
        'title'       => __( 'Dashboard de Inventario', 'aura-suite' ),
        'subtitle'    => __( 'Monitoreo de equipos, mantenimientos programados, costos y estado operativo', 'aura-suite' ),
        'icon'        => 'dashicons-clipboard',
        'breadcrumbs' => [
            [ 'label' => __( 'Inventario', 'aura-suite' ), 'url' => admin_url( 'admin.php?page=aura-inventory' ) ],
            [ 'label' => __( 'Dashboard', 'aura-suite' ) ],
        ],
        'badges'      => [
            [
                'label'   => sprintf( __( '%d equipos', 'aura-suite' ), (int) $kpis['total_equipment'] ),
                'variant' => 'indigo',
                'tooltip' => __( 'Activos físicos activos registrados', 'aura-suite' ),
            ],
        ],
        'actions'     => $header_actions,
    ] );
    ?>

    <div class="aura-inv-live-bar">
        <span class="live-chip" data-tooltip="<?php esc_attr_e( 'Métricas, alertas de mantenimiento y préstamos sincronizados en tiempo real', 'aura-suite' ); ?>">
            <span class="pulse-dot"></span>
            <?php esc_html_e( 'Monitoreo Operativo Activo', 'aura-suite' ); ?>
        </span>
    </div>

    <?php if ( $kpis['overdue'] > 0 ) : ?>
    <div class="alert-card alert-danger aura-inv-dash-alert" role="alert">
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <div class="aura-inv-dash-alert-body">
            <strong><?php esc_html_e( 'Mantenimientos vencidos:', 'aura-suite' ); ?></strong>
            <?php printf(
                _n( 'Hay %d equipo con mantenimiento vencido.', 'Hay %d equipos con mantenimiento vencido.', $kpis['overdue'], 'aura-suite' ),
                (int) $kpis['overdue']
            ); ?>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-equipment&maintenance_status=overdue' ) ); ?>"
           class="btn btn-rose btn-sm btn-lift"
           data-tooltip="<?php esc_attr_e( 'Abrir catálogo filtrado por mantenimientos vencidos', 'aura-suite' ); ?>">
            <?php esc_html_e( 'Ver equipos →', 'aura-suite' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <?php if ( $kpis['overdue_loans'] > 0 ) : ?>
    <div class="alert-card alert-warning aura-inv-dash-alert" role="alert">
        <span class="dashicons dashicons-share" aria-hidden="true"></span>
        <div class="aura-inv-dash-alert-body">
            <strong><?php esc_html_e( 'Préstamos vencidos:', 'aura-suite' ); ?></strong>
            <?php printf(
                _n( 'Hay %d equipo prestado sin devolver (vencido).', 'Hay %d equipos prestados sin devolver (vencidos).', $kpis['overdue_loans'], 'aura-suite' ),
                (int) $kpis['overdue_loans']
            ); ?>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-loans' ) ); ?>"
           class="btn btn-amber btn-sm btn-lift"
           data-tooltip="<?php esc_attr_e( 'Ir al listado de préstamos vencidos', 'aura-suite' ); ?>">
            <?php esc_html_e( 'Ver préstamos →', 'aura-suite' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <?php
    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-total-equipment-val',
            'icon'     => 'dashicons-archive',
            'value'    => number_format_i18n( (int) $kpis['total_equipment'] ),
            'label'    => __( 'Equipos Registrados', 'aura-suite' ),
            'variant'  => 'capital',
            'tooltip'  => __( 'Total de activos físicos en el catálogo', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-with-maint-val',
            'icon'     => 'dashicons-calendar-alt',
            'value'    => number_format_i18n( (int) $kpis['with_maintenance'] ),
            'label'    => __( 'Con Mantenimiento Periódico', 'aura-suite' ),
            'variant'  => 'count',
            'tooltip'  => __( 'Equipos con rutinas de servicio activas', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-overdue-val',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( (int) $kpis['overdue'] ),
            'label'    => __( 'Mantenimientos Vencidos', 'aura-suite' ),
            'variant'  => $kpis['overdue'] > 0 ? 'expense' : 'income',
            'tooltip'  => __( 'Equipos que debieron recibir mantenimiento', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-upcoming7-val',
            'icon'     => 'dashicons-clock',
            'value'    => number_format_i18n( (int) $kpis['upcoming7'] ),
            'label'    => __( 'Próximos 7 Días', 'aura-suite' ),
            'variant'  => $kpis['upcoming7'] > 0 ? 'pending' : 'income',
            'tooltip'  => __( 'Mantenimientos agendados para esta semana', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-upcoming15-val',
            'icon'     => 'dashicons-calendar',
            'value'    => number_format_i18n( (int) $kpis['upcoming15'] ),
            'label'    => __( 'Próximos 15 Días', 'aura-suite' ),
            'variant'  => $kpis['upcoming15'] > 0 ? 'pending' : 'income',
            'tooltip'  => __( 'Mantenimientos agendados para la quincena', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-cost-month-val',
            'icon'     => 'dashicons-money-alt',
            'value'    => $currency . number_format( (float) $kpis['cost_month'], 2 ),
            'label'    => sprintf( __( 'Costo Mant. %s', 'aura-suite' ), date( 'M Y' ) ),
            'variant'  => 'income',
            'tooltip'  => __( 'Gasto total de mantenimiento en el mes en curso', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-cost-year-val',
            'icon'     => 'dashicons-chart-area',
            'value'    => $currency . number_format( (float) $kpis['cost_year'], 2 ),
            'label'    => sprintf( __( 'Costo Mant. %s', 'aura-suite' ), date( 'Y' ) ),
            'variant'  => 'expense',
            'tooltip'  => __( 'Gasto acumulado de mantenimiento en el año fiscal', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-active-loans-val',
            'icon'     => 'dashicons-share',
            'value'    => number_format_i18n( (int) $kpis['active_loans'] ),
            'label'    => __( 'Préstamos Activos', 'aura-suite' ),
            'variant'  => 'balance',
            'tooltip'  => __( 'Equipos actualmente en poder de terceros o personal', 'aura-suite' ),
        ],
        [
            'id'       => 'kpi-overdue-loans-val',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( (int) $kpis['overdue_loans'] ),
            'label'    => __( 'Préstamos Vencidos', 'aura-suite' ),
            'variant'  => $kpis['overdue_loans'] > 0 ? 'expense' : 'income',
            'tooltip'  => __( 'Equipos prestados fuera de la fecha límite acordada', 'aura-suite' ),
        ],
    ] );
    ?>

    <!-- ═══ FILA 2: Gráficas ══════════════════════════════════════════════ -->
    <div class="aura-dash-charts-row">

        <!-- Widget 1: Estado del inventario (dona) -->
        <div class="aura-glass-card aura-dash-chart-card card-lift">
            <div class="aura-dash-chart-header">
                <h3>
                    <?php esc_html_e( 'Estado del inventario', 'aura-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Distribución porcentual por condición operativa actual de cada equipo', 'aura-suite' ); ?>">?</span>
                </h3>
                <div class="aura-dash-chart-spinner spinner"></div>
            </div>
            <div id="aura-dash-status-chart" class="aura-dash-apexchart"></div>
        </div>

        <!-- Widget 2: Costos por tipo de mantenimiento (barras) -->
        <div class="aura-glass-card aura-dash-chart-card aura-dash-chart-wide card-lift">
            <div class="aura-dash-chart-header">
                <h3>
                    <?php esc_html_e( 'Costos por tipo de mantenimiento', 'aura-suite' ); ?>
                    <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Inversión agrupada por tipo de intervención según el período elegido', 'aura-suite' ); ?>">?</span>
                </h3>
                <div class="aura-dash-period-tabs" role="tablist">
                    <button type="button" class="btn btn-sm btn-indigo active aura-dash-period" data-period="month" data-tooltip="<?php esc_attr_e( 'Mostrar costos del mes actual', 'aura-suite' ); ?>"><?php esc_html_e( 'Este mes', 'aura-suite' ); ?></button>
                    <button type="button" class="btn btn-sm btn-glass aura-dash-period" data-period="quarter" data-tooltip="<?php esc_attr_e( 'Mostrar costos del trimestre', 'aura-suite' ); ?>"><?php esc_html_e( 'Trimestre', 'aura-suite' ); ?></button>
                    <button type="button" class="btn btn-sm btn-glass aura-dash-period" data-period="year" data-tooltip="<?php esc_attr_e( 'Mostrar costos del año actual', 'aura-suite' ); ?>"><?php esc_html_e( 'Este año', 'aura-suite' ); ?></button>
                </div>
                <div class="aura-dash-chart-spinner spinner"></div>
            </div>
            <div id="aura-dash-cost-chart" class="aura-dash-apexchart"></div>
        </div>

    </div><!-- .aura-dash-charts-row -->

    <!-- ═══ FILA 3: Calendario próximos 30 días ═══════════════════════════ -->
    <div class="aura-glass-card aura-dash-section-card card-lift">
        <div class="aura-dash-section-header">
            <h3>
                <?php esc_html_e( 'Calendario de mantenimientos — próximos 30 días', 'aura-suite' ); ?>
                <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Equipos con fecha programada próxima o vencida ordenados por urgencia', 'aura-suite' ); ?>">?</span>
            </h3>
            <div class="aura-dash-legend">
                <span class="aura-dash-legend-item"><span class="aura-dash-legend-dot overdue"></span><?php esc_html_e( 'Vencido', 'aura-suite' ); ?></span>
                <span class="aura-dash-legend-item"><span class="aura-dash-legend-dot urgent"></span><?php esc_html_e( 'Urgente (≤3d)', 'aura-suite' ); ?></span>
                <span class="aura-dash-legend-item"><span class="aura-dash-legend-dot warning"></span><?php esc_html_e( 'Próximo (≤7d)', 'aura-suite' ); ?></span>
                <span class="aura-dash-legend-item"><span class="aura-dash-legend-dot ok"></span><?php esc_html_e( 'En los próximos 30d', 'aura-suite' ); ?></span>
            </div>
            <div class="aura-dash-chart-spinner spinner" id="aura-dash-cal-spinner"></div>
        </div>
        <div id="aura-dash-calendar-list" class="aura-dash-calendar-list">
            <div class="aura-dash-skel-stack">
                <div class="skeleton skel-line w-80"></div>
                <div class="skeleton skel-line w-60"></div>
                <div class="skeleton skel-line w-70"></div>
            </div>
        </div>
    </div>

    <!-- ═══ FILA 4: Equipos críticos ═════════════════════════════════════ -->
    <div class="aura-glass-card aura-dash-section-card card-lift">
        <div class="aura-dash-section-header">
            <h3 class="aura-dash-critical-heading">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e( 'Equipos que requieren atención', 'aura-suite' ); ?>
                <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Resumen de equipos con mantenimiento vencido, en taller o préstamo vencido', 'aura-suite' ); ?>">?</span>
            </h3>
            <div class="aura-dash-chart-spinner spinner" id="aura-dash-crit-spinner"></div>
        </div>
        <div id="aura-dash-critical" class="aura-dash-critical-wrap">
            <div class="aura-dash-skel-stack">
                <div class="skeleton skel-card"></div>
                <div class="skeleton skel-card"></div>
            </div>
        </div>
    </div>

    <!-- ═══ Accesos rápidos ═══════════════════════════════════════════════ -->
    <div class="aura-glass-card aura-dash-section-card aura-inv-quick-links">
        <div class="aura-dash-section-header">
            <h3><?php esc_html_e( 'Accesos Rápidos de Inventario', 'aura-suite' ); ?></h3>
        </div>
        <div class="aura-inv-quick-grid">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-equipment' ) ); ?>" class="btn btn-glass btn-lift aura-inv-quick-btn" data-tooltip="<?php esc_attr_e( 'Explorar catálogo completo de activos y herramientas', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-archive"></span>
                <span><?php esc_html_e( 'Ver Equipos', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-new-equipment' ) ); ?>" class="btn btn-glass btn-lift aura-inv-quick-btn" data-tooltip="<?php esc_attr_e( 'Dar de alta un equipo nuevo', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-plus-alt"></span>
                <span><?php esc_html_e( 'Registrar Equipo', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-maintenance' ) ); ?>" class="btn btn-glass btn-lift aura-inv-quick-btn" data-tooltip="<?php esc_attr_e( 'Consultar bitácora y programación de mantenimientos', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-admin-tools"></span>
                <span><?php esc_html_e( 'Mantenimientos', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-loans' ) ); ?>" class="btn btn-glass btn-lift aura-inv-quick-btn" data-tooltip="<?php esc_attr_e( 'Administrar préstamos y devoluciones de equipos', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-share"></span>
                <span><?php esc_html_e( 'Préstamos', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-inventory-reports' ) ); ?>" class="btn btn-glass btn-lift aura-inv-quick-btn" data-tooltip="<?php esc_attr_e( 'Generar reportes de costos, eficiencia y vida útil', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-chart-bar"></span>
                <span><?php esc_html_e( 'Reportes', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

    <div class="adp-footer">
        <div>
            <?php esc_html_e( 'Desarrollado con ❤️ por', 'aura-suite' ); ?> <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; <?php esc_html_e( 'Versión', 'aura-suite' ); ?> <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    </div><!-- .wrap.aura-app-context -->
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
