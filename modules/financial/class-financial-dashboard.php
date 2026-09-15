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
        add_action( 'wp_ajax_aura_get_category_drilldown', array( __CLASS__, 'ajax_get_category_drilldown' ) );

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

        $can_view_all = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
        $can_view_own = current_user_can( 'aura_finance_view_own' );
        $can_approve  = current_user_can( 'aura_finance_approve' );
        $tx_list_url      = admin_url( 'admin.php?page=aura-financial-transactions' );
        $pending_list_url = admin_url( 'admin.php?page=aura-financial-pending' );
        ?>
        <div class="aura-app-wrapper">
        <div class="wrap aura-app-context">
            <h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e( 'Dashboard Financiero', 'aura-suite' ); ?></h1>
            <hr class="wp-header-end">
            <div id="aura-wp-notices-container" class="aura-wp-notices-container"></div>
            <div class="aura-layout">

                <!-- =========================================================
                     NAVBAR HORIZONTAL GLASSMORPHISM
                ========================================================= -->
                <nav class="aura-navbar aura-nav aura-glass-card" id="aura-top-navbar">
                    <div class="aura-navbar-brand">
                        <span class="dashicons dashicons-chart-area" style="font-size:22px;color:var(--aura-primary,#6366f1);"></span>
                        <span class="aura-navbar-title"><?php esc_html_e( 'Dashboard Financiero', 'aura-suite' ); ?></span>
                    </div>
                    <div class="aura-navbar-menu">
                        <button class="aura-navbar-item aura-tab-btn active" data-tab="tab-overview">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e( 'Resumen', 'aura-suite' ); ?>
                        </button>
                        <?php if ( $can_view_all || $can_view_own ) : ?>
                        <button class="aura-navbar-item aura-tab-btn" data-tab="tab-graficos">
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php esc_html_e( 'Gráficos', 'aura-suite' ); ?>
                        </button>
                        <?php endif; ?>
                        <button class="aura-navbar-item aura-tab-btn" data-tab="tab-alertas">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e( 'Alertas', 'aura-suite' ); ?>
                        </button>
                        <?php if ( $can_view_all && class_exists( 'Aura_Financial_Accounts' ) ) : ?>
                        <button class="aura-navbar-item aura-tab-btn" data-tab="tab-bancos">
                            <span class="dashicons dashicons-bank"></span>
                            <?php esc_html_e( 'Presupuesto Bancos', 'aura-suite' ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="aura-navbar-actions">
                        <button id="dash-refresh" class="aura-btn aura-btn-sm aura-btn-ghost" title="<?php esc_attr_e( 'Refrescar datos', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <span class="aura-navbar-btn-label"><?php esc_html_e( 'Refrescar', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </nav>

                <!-- =========================================================
                     CONTENIDO PRINCIPAL (Tabs)
                ========================================================= -->
                <main class="aura-content">

                    <!-- =========================================================
                         BARRA DE PERÍODO (siempre visible, fuera de tabs)
                    ========================================================= -->
                    <div class="aura-period-bar" style="margin-bottom: 20px;">
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
                            <?php esc_html_e( 'Comparar período anterior', 'aura-suite' ); ?>
                        </label>
                    </div><!-- .aura-period-bar -->

                    <!-- ── TAB: RESUMEN ── -->
                    <div id="tab-overview" class="aura-tab-panel active">

                        <!-- KPIs Expandidos -->
                        <div class="aura-kpis-row aura-kpis-row--extended">
                            <?php if ( $can_view_all || $can_view_own ) : ?>
                            <div class="aura-kpi aura-kpi--income is-loading">
                                <div class="aura-kpi__label">
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                    <?php echo $can_view_all ? esc_html__( 'Total Ingresos', 'aura-suite' ) : esc_html__( 'Mis Ingresos', 'aura-suite' ); ?>
                                </div>
                                <div class="aura-kpi__value" id="kpi-val-income">—</div>
                                <div class="aura-kpi__meta"><span class="aura-kpi__trend" style="display:none"></span></div>
                            </div>
                            <div class="aura-kpi aura-kpi--expense is-loading">
                                <div class="aura-kpi__label">
                                    <span class="dashicons dashicons-arrow-down-alt"></span>
                                    <?php echo $can_view_all ? esc_html__( 'Total Egresos', 'aura-suite' ) : esc_html__( 'Mis Egresos', 'aura-suite' ); ?>
                                </div>
                                <div class="aura-kpi__value" id="kpi-val-expense">—</div>
                                <div class="aura-kpi__meta"><span class="aura-kpi__trend" style="display:none"></span></div>
                            </div>
                            <div class="aura-kpi aura-kpi--balance is-loading">
                                <div class="aura-kpi__label">
                                    <span class="dashicons dashicons-chart-line"></span>
                                    <?php echo $can_view_all ? esc_html__( 'Balance Neto', 'aura-suite' ) : esc_html__( 'Mi Balance', 'aura-suite' ); ?>
                                </div>
                                <div class="aura-kpi__value" id="kpi-val-balance">—</div>
                                <div class="aura-kpi__meta"><span class="aura-kpi__trend" style="display:none"></span></div>
                            </div>
                            <div class="aura-kpi aura-kpi--savings_rate is-loading">
                                <div class="aura-kpi__label">
                                    <span class="dashicons dashicons-vault"></span>
                                    <?php esc_html_e( 'Tasa de Ahorro', 'aura-suite' ); ?>
                                </div>
                                <div class="aura-kpi__value" id="kpi-val-savings">—</div>
                                <div class="aura-kpi__meta"><span class="aura-kpi__subtext" id="kpi-sub-savings"><?php esc_html_e( 'Margen neto vs ingresos', 'aura-suite' ); ?></span></div>
                            </div>
                            <div class="aura-kpi aura-kpi--daily_avg is-loading">
                                <div class="aura-kpi__label">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php esc_html_e( 'Gasto Promedio Diario', 'aura-suite' ); ?>
                                </div>
                                <div class="aura-kpi__value" id="kpi-val-daily">—</div>
                                <div class="aura-kpi__meta"><span class="aura-kpi__subtext" id="kpi-sub-daily"><?php esc_html_e( 'Por día en el período', 'aura-suite' ); ?></span></div>
                            </div>
                            <?php endif; ?>
                            <?php if ( $can_approve ) : ?>
                            <div class="aura-kpi aura-kpi--pending is-loading">
                                <div class="aura-kpi__label">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php esc_html_e( 'Por Aprobar', 'aura-suite' ); ?>
                                </div>
                                <div class="aura-kpi__value" id="kpi-val-pending">—</div>
                                <div class="aura-kpi__meta">
                                    <a class="aura-kpi__link" href="<?php echo esc_url( $pending_list_url ); ?>"><?php esc_html_e( 'Ver pendientes →', 'aura-suite' ); ?></a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div><!-- .aura-kpis-row -->

                        <!-- Widget: Cuentas y Saldos Disponibles -->
                        <?php if ( $can_view_all ) : ?>
                        <div class="aura-widget-card aura-accounts-summary-card" style="margin-bottom: 24px;">
                            <div class="aura-widget-card__header">
                                <h2 class="aura-widget-card__title">
                                    <span class="dashicons dashicons-money-alt" style="color:var(--aura-primary,#6366f1)"></span>
                                    <?php esc_html_e( 'Disponibilidad y Cuentas', 'aura-suite' ); ?>
                                </h2>
                                <a class="aura-widget-card__link" href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-accounts' ) ); ?>">
                                    <?php esc_html_e( 'Gestionar Cuentas →', 'aura-suite' ); ?>
                                </a>
                            </div>
                            <div id="aura-accounts-grid" class="aura-accounts-grid">
                                <div class="aura-empty-state"><p><?php esc_html_e( 'Cargando cuentas…', 'aura-suite' ); ?></p></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Últimas transacciones + Alertas rápidas -->
                        <div class="aura-widgets-row">
                            <div class="aura-widget-card">
                                <div class="aura-widget-card__header">
                                    <h2 class="aura-widget-card__title">
                                        <span class="dashicons dashicons-list-view"></span>
                                        <?php esc_html_e( 'Últimas Transacciones', 'aura-suite' ); ?>
                                    </h2>
                                    <a class="aura-widget-card__link" href="<?php echo esc_url( $tx_list_url ); ?>">
                                        <?php esc_html_e( 'Ver todas →', 'aura-suite' ); ?>
                                    </a>
                                </div>
                                <div id="aura-recent-empty" class="aura-empty-state" style="display:none">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <p><?php esc_html_e( 'No hay transacciones en este período.', 'aura-suite' ); ?></p>
                                </div>
                                <div class="aura-dt-wrapper">
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
                            </div>

                            <div class="aura-widget-card">
                                <div class="aura-widget-card__header">
                                    <h2 class="aura-widget-card__title">
                                        <span class="dashicons dashicons-warning" style="color:#f59e0b;font-size:15px;width:15px;height:15px;"></span>
                                        <?php esc_html_e( 'Alertas Financieras', 'aura-suite' ); ?>
                                    </h2>
                                </div>
                                <div id="aura-alerts-empty" class="aura-alerts-empty">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981;font-size:24px;width:24px;height:24px;"></span>
                                    <p><?php esc_html_e( 'Sin alertas activas. ¡Todo en orden!', 'aura-suite' ); ?></p>
                                </div>
                                <div id="aura-alerts-list" class="aura-alerts-list"></div>
                            </div>
                        </div><!-- .aura-widgets-row -->

                    </div><!-- #tab-overview -->

                    <!-- ── TAB: GRÁFICOS ── -->
                    <?php if ( $can_view_all || $can_view_own ) : ?>
                    <div id="tab-graficos" class="aura-tab-panel">
                        <div class="aura-charts-row">
                            <!-- Gráfico de Ingresos vs Egresos -->
                            <div class="aura-chart-card">
                                <div class="aura-chart-card__header">
                                    <h2 class="aura-chart-card__title">
                                        <span class="dashicons dashicons-chart-line"></span>
                                        <?php esc_html_e( 'Ingresos vs Egresos', 'aura-suite' ); ?>
                                    </h2>
                                    <div class="aura-chart-card__actions">
                                        <div class="aura-btn-group aura-btn-group-sm">
                                            <button type="button" class="aura-btn aura-btn-xs active" id="btn-chart-type-line"><?php esc_html_e( 'Línea', 'aura-suite' ); ?></button>
                                            <button type="button" class="aura-btn aura-btn-xs" id="btn-chart-type-bar"><?php esc_html_e( 'Barras', 'aura-suite' ); ?></button>
                                        </div>
                                        <button id="export-line-png" class="button button-small chart-export-btn" title="<?php esc_attr_e( 'Descargar imagen PNG', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-download"></span> PNG
                                        </button>
                                    </div>
                                </div>
                                <div class="aura-chart-canvas-wrap">
                                    <canvas id="aura-line-chart"></canvas>
                                </div>
                            </div>

                            <!-- Gráfico de Gastos por Categoría con DRILLDOWN INTERACTIVO -->
                            <div class="aura-chart-card aura-chart-card--drilldown">
                                <div class="aura-chart-card__header">
                                    <div class="aura-drilldown-header-wrap">
                                        <h2 class="aura-chart-card__title" id="aura-donut-main-title">
                                            <span class="dashicons dashicons-chart-pie"></span>
                                            <?php esc_html_e( 'Gastos por Categoría', 'aura-suite' ); ?>
                                        </h2>
                                        <div id="aura-drilldown-nav" class="aura-drilldown-nav" style="display:none;">
                                            <button type="button" id="aura-drilldown-back" class="aura-drilldown-back-btn">
                                                <span class="dashicons dashicons-arrow-left-alt"></span>
                                                <?php esc_html_e( 'Volver a Categorías', 'aura-suite' ); ?>
                                            </button>
                                            <span class="aura-drilldown-current" id="aura-drilldown-current-name"></span>
                                        </div>
                                    </div>
                                    <div class="aura-chart-card__actions">
                                        <div class="aura-help-tooltip-wrap" id="aura-drilldown-help-wrap">
                                            <span class="dashicons dashicons-editor-help aura-help-icon" title=""></span>
                                            <div class="aura-help-tooltip-box">
                                                <strong><?php esc_html_e( 'Explorador Interactivo', 'aura-suite' ); ?></strong>
                                                <p><?php esc_html_e( 'Haz clic en cualquier categoría de la lista o del gráfico para explorar el desglose detallado de sus subcategorías.', 'aura-suite' ); ?></p>
                                            </div>
                                        </div>
                                        <button id="export-donut-png" class="button button-small chart-export-btn" title="<?php esc_attr_e( 'Descargar imagen PNG', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-download"></span> PNG
                                        </button>
                                    </div>
                                </div>
                                <div class="aura-chart-canvas-wrap">
                                    <canvas id="aura-donut-chart"></canvas>
                                </div>
                                <div id="aura-donut-legend" class="aura-donut-legend"></div>
                            </div>
                        </div>

                        <!-- Fila Secundaria: Distribución por Área / Programa -->
                        <div class="aura-charts-row" style="margin-top:24px;">
                            <div class="aura-chart-card aura-chart-card--full">
                                <div class="aura-chart-card__header">
                                    <h2 class="aura-chart-card__title">
                                        <span class="dashicons dashicons-groups"></span>
                                        <?php esc_html_e( 'Distribución de Egresos por Área / Programa', 'aura-suite' ); ?>
                                    </h2>
                                    <div class="aura-chart-card__actions">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-areas' ) ); ?>" class="aura-widget-card__link">
                                            <?php esc_html_e( 'Ver Áreas y Programas →', 'aura-suite' ); ?>
                                        </a>
                                    </div>
                                </div>
                                <div id="aura-area-distribution-wrap" class="aura-area-distribution-wrap">
                                    <div class="aura-empty-state"><p><?php esc_html_e( 'Cargando distribución por área…', 'aura-suite' ); ?></p></div>
                                </div>
                            </div>
                        </div>
                    </div><!-- #tab-graficos -->
                    <?php endif; ?>

                    <!-- ── TAB: ALERTAS & REPORTES ── -->
                    <div id="tab-alertas" class="aura-tab-panel">

                        <?php if ( class_exists( 'Aura_Financial_Budgets' ) ) : ?>
                        <!-- Widget Presupuestos (Fase 5, Item 5.1) -->
                        <?php Aura_Financial_Budgets::render_dashboard_widget(); ?>
                        <?php endif; ?>

                        <?php if ( class_exists( 'Aura_Financial_Audit' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'aura_auditor' ) ) ) : ?>
                        <!-- Widget Actividad Reciente (Fase 5, Item 5.3) -->
                        <?php Aura_Financial_Audit::render_dashboard_widget(); ?>
                        <?php endif; ?>

                    </div><!-- #tab-alertas -->

                    <!-- ── TAB: PRESUPUESTO BANCOS ── -->
                    <?php if ( $can_view_all && class_exists( 'Aura_Financial_Accounts' ) ) : ?>
                    <div id="tab-bancos" class="aura-tab-panel">
                        <div class="aura-bank-budget-row">
                            <div class="aura-widget-card aura-widget-card--bank-budget">
                                <div class="aura-widget-card__header">
                                    <h2 class="aura-widget-card__title">
                                        <span class="dashicons dashicons-bank"></span>
                                        <?php esc_html_e( 'Presupuesto: Anual vs Mensual (Bancos)', 'aura-suite' ); ?>
                                    </h2>
                                    <div class="aura-bank-budget-year-tools">
                                        <span class="aura-widget-card__meta" id="aura-bank-budget-year"><?php echo esc_html( date_i18n( 'Y' ) ); ?></span>
                                        <label for="aura-bank-budget-year-select" class="aura-bank-budget-year-label">
                                            <?php esc_html_e( 'Año:', 'aura-suite' ); ?>
                                        </label>
                                        <select id="aura-bank-budget-year-select" class="aura-bank-budget-year-select">
                                            <?php
                                            $current_year = (int) current_time( 'Y' );
                                            for ( $y = $current_year + 1; $y >= $current_year - 4; $y-- ) :
                                            ?>
                                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $y, $current_year ); ?>><?php echo esc_html( $y ); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Tarjetas de Métricas Presupuestarias -->
                                <div id="aura-bank-budget-kpis" class="aura-bank-budget-kpis">
                                    <div class="aura-bank-kpi">
                                        <span class="aura-bank-kpi__title"><?php esc_html_e( 'Tope Anual', 'aura-suite' ); ?></span>
                                        <span class="aura-bank-kpi__val" id="bb-kpi-limit">—</span>
                                    </div>
                                    <div class="aura-bank-kpi">
                                        <span class="aura-bank-kpi__title"><?php esc_html_e( 'Ejecutado Anual', 'aura-suite' ); ?></span>
                                        <span class="aura-bank-kpi__val" id="bb-kpi-spent">—</span>
                                    </div>
                                    <div class="aura-bank-kpi">
                                        <span class="aura-bank-kpi__title"><?php esc_html_e( 'Disponible Anual', 'aura-suite' ); ?></span>
                                        <span class="aura-bank-kpi__val" id="bb-kpi-remaining">—</span>
                                    </div>
                                    <div class="aura-bank-kpi">
                                        <span class="aura-bank-kpi__title"><?php esc_html_e( 'Política de Control', 'aura-suite' ); ?></span>
                                        <span class="aura-bank-kpi__val" id="bb-kpi-policy">—</span>
                                    </div>
                                </div>

                                <div class="aura-bank-budget-table-wrap">
                                    <table id="aura-bank-budget-table" class="aura-bank-budget-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Mes', 'aura-suite' ); ?></th>
                                                <th><?php esc_html_e( 'Presupuestado', 'aura-suite' ); ?></th>
                                                <th><?php esc_html_e( 'Ejecutado', 'aura-suite' ); ?></th>
                                                <th><?php esc_html_e( 'Diferencia', 'aura-suite' ); ?></th>
                                                <th><?php esc_html_e( 'Cumplimiento', 'aura-suite' ); ?></th>
                                                <th><?php esc_html_e( 'Acción', 'aura-suite' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="aura-bank-budget-tbody">
                                            <tr>
                                                <td colspan="6" class="aura-table-loading"><?php esc_html_e( 'Cargando datos presupuestarios…', 'aura-suite' ); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div><!-- #tab-bancos -->
                    <?php endif; ?>

                </main><!-- .aura-content -->

            </div><!-- .aura-layout -->
        </div><!-- .wrap.aura-app-context -->
        </div><!-- .aura-app-wrapper -->
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
        $bank_year = isset( $_POST['bank_year'] ) ? absint( $_POST['bank_year'] ) : 0;

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
                . '|' . ( $prev_start ?? '' ) . '|' . ( $prev_end ?? '' )
                . '|bankbudget-v3|' . $bank_year );

        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            wp_send_json_success( $cached );
            return;
        }
        // ──────────────────────────────────────────────────────────────

        $data = array(
            'kpis'              => self::get_kpis( $start, $end, $prev_start, $prev_end ),
            'chart_line'        => self::get_line_data( $start, $end, $prev_start, $prev_end ),
            'chart_donut'       => self::get_donut_data( $start, $end ),
            'recent'            => self::get_recent_transactions(),
            'alerts'            => self::get_alerts(),
            'bank_budget'       => self::get_bank_budget_summary( $start, $end, $bank_year ),
            'area_distribution' => self::get_area_distribution( $start, $end ),
            'accounts_summary'  => self::get_accounts_summary(),
        );

        set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

        wp_send_json_success( $data );
    }

    /**
     * Endpoint AJAX para Drilldown de Categorías -> Subcategorías
     */
    public static function ajax_get_category_drilldown() {
        check_ajax_referer( 'aura_dashboard_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_charts' ) &&
             ! current_user_can( 'aura_finance_view_own' ) &&
             ! current_user_can( 'aura_finance_view_all' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }

        global $wpdb;
        $table     = $wpdb->prefix . 'aura_finance_transactions';
        $cat_table = $wpdb->prefix . 'aura_finance_categories';

        $cat_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
        $start  = sanitize_text_field( $_POST['start'] ?? date( 'Y-m-01' ) );
        $end    = sanitize_text_field( $_POST['end']   ?? date( 'Y-m-t' ) );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) $start = date( 'Y-m-01' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) )   $end   = date( 'Y-m-t' );

        $parent_cat = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, color FROM {$cat_table} WHERE id = %d", $cat_id ), ARRAY_A );
        if ( ! $parent_cat ) {
            wp_send_json_error( __( 'Categoría no encontrada', 'aura-suite' ) );
        }

        $can_view_all = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
        $user_filter  = $can_view_all ? '' : $wpdb->prepare( ' AND t.created_by = %d', get_current_user_id() );

        // 1. Buscar transacciones de subcategorías con parent_id = $cat_id
        $subcat_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.id AS sub_id, c.name AS sub_name, c.color AS sub_color, SUM(t.amount) AS total, COUNT(t.id) AS tx_count
               FROM {$table} t
               JOIN {$cat_table} c ON t.category_id = c.id
              WHERE c.parent_id = %d
                AND t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN %s AND %s
                AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}
              GROUP BY c.id ORDER BY total DESC",
            $cat_id, $start, $end
        ), ARRAY_A );

        // 2. Buscar transacciones asignadas directamente a la categoría padre
        $direct_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(t.amount) AS total, COUNT(t.id) AS tx_count
               FROM {$table} t
              WHERE t.category_id = %d
                AND t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN %s AND %s
                AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}",
            $cat_id, $start, $end
        ), ARRAY_A );

        $direct_total = (float) ( $direct_row['total'] ?? 0 );
        $direct_count = (int) ( $direct_row['tx_count'] ?? 0 );

        $palette = [
            '#6366f1', '#ec4899', '#10b981', '#f59e0b', '#3b82f6',
            '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4', '#e11d48',
            '#84cc16', '#a855f7', '#0ea5e9', '#d97706', '#64748b',
        ];

        $items = [];
        if ( ! empty( $subcat_rows ) ) {
            foreach ( $subcat_rows as $idx => $sr ) {
                $custom_color = trim( (string) $sr['sub_color'] );
                if ( ! empty( $custom_color ) && strtolower( $custom_color ) !== '#607d8b' ) {
                    $assigned_color = $custom_color;
                } else {
                    $assigned_color = $palette[ $idx % count( $palette ) ];
                }

                $items[] = [
                    'id'       => (int) $sr['sub_id'],
                    'name'     => $sr['sub_name'],
                    'color'    => $assigned_color,
                    'amount'   => (float) $sr['total'],
                    'tx_count' => (int) $sr['tx_count'],
                    'is_sub'   => true,
                ];
            }
            if ( $direct_total > 0 ) {
                $items[] = [
                    'id'       => $cat_id,
                    'name'     => sprintf( __( 'General (%s)', 'aura-suite' ), $parent_cat['name'] ),
                    'color'    => $palette[ count( $subcat_rows ) % count( $palette ) ],
                    'amount'   => $direct_total,
                    'tx_count' => $direct_count,
                    'is_sub'   => false,
                ];
            }
        } else {
            // Si no tiene subcategorías registradas, desglosar por los conceptos/descripciones principales
            $desc_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.description, SUM(t.amount) AS total, COUNT(t.id) AS tx_count
                   FROM {$table} t
                  WHERE t.category_id = %d
                    AND t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN %s AND %s
                    AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}
                  GROUP BY t.description ORDER BY total DESC LIMIT 8",
                $cat_id, $start, $end
            ), ARRAY_A );

            foreach ( $desc_rows as $idx => $dr ) {
                $items[] = [
                    'id'       => $cat_id,
                    'name'     => $dr['description'] ?: __( 'Sin detalle', 'aura-suite' ),
                    'color'    => $palette[ $idx % count( $palette ) ],
                    'amount'   => (float) $dr['total'],
                    'tx_count' => (int) $dr['tx_count'],
                    'is_sub'   => false,
                ];
            }
        }

        $total_sum = array_sum( array_column( $items, 'amount' ) );

        wp_send_json_success( [
            'parent' => [
                'id'    => $cat_id,
                'name'  => $parent_cat['name'],
                'color' => $parent_cat['color'] ?: '#6366f1',
            ],
            'labels'  => array_column( $items, 'name' ),
            'amounts' => array_column( $items, 'amount' ),
            'colors'  => array_column( $items, 'color' ),
            'items'   => $items,
            'total'   => $total_sum,
            'total_formatted' => self::fmt_money( $total_sum ),
        ] );
    }

    private static function get_kpis( $start, $end, $prev_start, $prev_end ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $cat_table = $wpdb->prefix . 'aura_finance_categories';
        $kpis  = array();

        $can_view_all = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
        $can_view_own = current_user_can( 'aura_finance_view_own' );
        $user_filter  = $can_view_all ? '' : $wpdb->prepare( ' AND created_by = %d', get_current_user_id() );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT transaction_type, SUM(amount) AS total
               FROM {$table}
              WHERE transaction_date BETWEEN %s AND %s
                AND status = 'approved'
                AND deleted_at IS NULL{$user_filter}
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
                    AND deleted_at IS NULL{$user_filter}
                  GROUP BY transaction_type",
                $prev_start, $prev_end
            ), ARRAY_A );
            foreach ( $prev as $r ) {
                if ( $r['transaction_type'] === 'income' )  $prev_income  = (float) $r['total'];
                if ( $r['transaction_type'] === 'expense' ) $prev_expense = (float) $r['total'];
            }
        }

        $balance = $income - $expense;
        $days = max( 1, ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS + 1 );
        $daily_avg = $expense > 0 ? ( $expense / $days ) : 0.0;
        $savings_rate = $income > 0 ? round( ( $balance / $income ) * 100, 1 ) : 0.0;

        // Categoría Top de Egresos
        $top_cat = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.name, SUM(t.amount) AS total
               FROM {$table} t
               LEFT JOIN {$cat_table} c ON c.id = t.category_id
              WHERE t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN %s AND %s
                AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}
              GROUP BY t.category_id ORDER BY total DESC LIMIT 1",
            $start, $end
        ), ARRAY_A );

        $kpis['income']       = array( 'raw' => $income,    'formatted' => self::fmt_money( $income ),    'pct_change' => self::pct_change( $income,  $prev_income ) );
        $kpis['expense']      = array( 'raw' => $expense,   'formatted' => self::fmt_money( $expense ),   'pct_change' => self::pct_change( $expense, $prev_expense ) );
        $kpis['balance']      = array( 'raw' => $balance,   'formatted' => self::fmt_money( $balance ),   'pct_change' => null );
        $kpis['savings_rate'] = array( 'raw' => $savings_rate, 'formatted' => ( $savings_rate >= 0 ? '+' : '' ) . $savings_rate . '%', 'pct_change' => null );
        $kpis['daily_avg']    = array( 'raw' => $daily_avg, 'formatted' => self::fmt_money( $daily_avg ), 'pct_change' => null );
        $kpis['top_category'] = array(
            'name'      => $top_cat ? ( $top_cat['name'] ?: __( 'Sin categoría', 'aura-suite' ) ) : '—',
            'amount'    => $top_cat ? (float) $top_cat['total'] : 0.0,
            'formatted' => $top_cat ? self::fmt_money( $top_cat['total'] ) : '—',
        );

        if ( current_user_can( 'aura_finance_approve' ) ) {
            $pending_count  = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='pending' AND deleted_at IS NULL" );
            $pending_amount = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE status='pending' AND deleted_at IS NULL" );
            $kpis['pending'] = array(
                'raw'        => $pending_count,
                'formatted'  => $pending_count . ' trans. (' . self::fmt_money( $pending_amount ) . ')',
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

                $can_view_all = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
                $user_filter  = $can_view_all ? '' : $wpdb->prepare( ' AND created_by = %d', get_current_user_id() );

                $rows = $wpdb->get_results( $wpdb->prepare(
                        "SELECT DATE_FORMAT(transaction_date, %s) AS period, transaction_type, SUM(amount) AS total
                             FROM {$table}
                            WHERE transaction_date BETWEEN %s AND %s
                                AND status = 'approved' AND deleted_at IS NULL{$user_filter}
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
                                        AND status = 'approved' AND deleted_at IS NULL{$user_filter}
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

        $can_view_all      = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
        $donut_user_filter = $can_view_all ? '' : $wpdb->prepare( ' AND t.created_by = %d', get_current_user_id() );

        // Paleta vibrante y moderna para colores distintivos
        $palette = array(
            '#6366f1', '#ec4899', '#10b981', '#f59e0b', '#3b82f6',
            '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4', '#e11d48',
            '#84cc16', '#a855f7', '#0ea5e9', '#d97706', '#475569'
        );

        // Agrupación de gastos por categoría principal (padre o individual)
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT COALESCE(p.id, c.id) AS main_cat_id,
                    COALESCE(p.name, c.name, 'Sin categoría') AS main_cat_name,
                    COALESCE(p.color, c.color) AS main_cat_color,
                    SUM(t.amount) AS total,
                    COUNT(t.id) AS tx_count
               FROM {$table} t
               LEFT JOIN {$cat_table} c ON c.id = t.category_id
               LEFT JOIN {$cat_table} p ON c.parent_id = p.id
              WHERE t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN %s AND %s
                AND t.status = 'approved' AND t.deleted_at IS NULL{$donut_user_filter}
              GROUP BY main_cat_id
              ORDER BY total DESC",
            $start, $end
        ), ARRAY_A );

        if ( ! $rows ) {
            return array( 'labels' => array(), 'amounts' => array(), 'colors' => array(), 'cat_ids' => array(), 'subcat_counts' => array(), 'total' => 0 );
        }

        $total_amount = array_sum( array_column( $rows, 'total' ) );

        $labels = array();
        $amounts = array();
        $colors = array();
        $cat_ids = array();
        $subcat_counts = array();

        foreach ( $rows as $idx => $r ) {
            $cat_id = (int) $r['main_cat_id'];
            $cat_name = $r['main_cat_name'] ?: __( 'Sin categoría', 'aura-suite' );
            $amount = (float) $r['total'];
            
            // Color personalizado o de la paleta
            $custom_color = trim( (string) $r['main_cat_color'] );
            if ( ! empty( $custom_color ) && strtolower( $custom_color ) !== '#607d8b' ) {
                $assigned_color = $custom_color;
            } else {
                $assigned_color = $palette[ $idx % count( $palette ) ];
            }

            // Contar subcategorías que tiene este padre
            $subs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$cat_table} WHERE parent_id = %d AND is_active = 1", $cat_id ) );

            $labels[] = $cat_name;
            $amounts[] = $amount;
            $colors[] = $assigned_color;
            $cat_ids[] = $cat_id;
            $subcat_counts[] = $subs;
        }

        return array(
            'labels'        => $labels,
            'amounts'       => $amounts,
            'colors'        => $colors,
            'cat_ids'       => $cat_ids,
            'subcat_counts' => $subcat_counts,
            'total'         => $total_amount,
        );
    }

    private static function get_area_distribution( $start, $end ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'aura_finance_transactions';
        $areas_t = $wpdb->prefix . 'aura_areas';

        $can_view_all = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
        $user_filter  = $can_view_all ? '' : $wpdb->prepare( ' AND t.created_by = %d', get_current_user_id() );

        $has_areas_table = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$areas_t}'" );

        $total_expense = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$table} t
              WHERE t.transaction_type = 'expense'
                AND t.transaction_date BETWEEN %s AND %s
                AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}",
            $start, $end
        ) );

        $rows = array();

        if ( $has_areas_table ) {
            // Egresos asignados a áreas específicas
            $area_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.id, a.name AS area_name, a.color AS area_color, a.icon AS area_icon, SUM(t.amount) AS total, COUNT(t.id) AS tx_count
                   FROM {$table} t
                   JOIN {$areas_t} a ON a.id = t.area_id
                  WHERE t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN %s AND %s
                    AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}
                  GROUP BY a.id
                  ORDER BY total DESC",
                $start, $end
            ), ARRAY_A );

            // Egresos sin área asignada (Generales)
            $general_total = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(t.amount), 0)
                   FROM {$table} t
                  WHERE (t.area_id IS NULL OR t.area_id = 0)
                    AND t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN %s AND %s
                    AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}",
                $start, $end
            ) );

            $general_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(t.id)
                   FROM {$table} t
                  WHERE (t.area_id IS NULL OR t.area_id = 0)
                    AND t.transaction_type = 'expense'
                    AND t.transaction_date BETWEEN %s AND %s
                    AND t.status = 'approved' AND t.deleted_at IS NULL{$user_filter}",
                $start, $end
            ) );

            if ( ! empty( $area_rows ) ) {
                foreach ( $area_rows as $ar ) {
                    $amt = (float) $ar['total'];
                    $pct = $total_expense > 0 ? round( ( $amt / $total_expense ) * 100, 1 ) : 0.0;
                    $rows[] = array(
                        'area_name' => $ar['area_name'],
                        'color'     => $ar['area_color'] ?: '#6366f1',
                        'icon'      => $ar['area_icon'] ?: 'dashicons-groups',
                        'amount'    => $amt,
                        'formatted' => self::fmt_money( $amt ),
                        'percent'   => $pct,
                        'tx_count'  => (int) $ar['tx_count'],
                    );
                }
            }

            if ( $general_total > 0 ) {
                $pct = $total_expense > 0 ? round( ( $general_total / $total_expense ) * 100, 1 ) : 0.0;
                $rows[] = array(
                    'area_name' => __( 'General / Administración Central', 'aura-suite' ),
                    'color'     => '#64748b',
                    'icon'      => 'dashicons-admin-generic',
                    'amount'    => $general_total,
                    'formatted' => self::fmt_money( $general_total ),
                    'percent'   => $pct,
                    'tx_count'  => $general_count,
                );
            }
        }

        if ( empty( $rows ) && $total_expense > 0 ) {
            $rows[] = array(
                'area_name' => __( 'Operación General (Sin asignación de Área)', 'aura-suite' ),
                'color'     => '#6366f1',
                'icon'      => 'dashicons-building',
                'amount'    => $total_expense,
                'formatted' => self::fmt_money( $total_expense ),
                'percent'   => 100.0,
                'tx_count'  => 1,
            );
        }

        return $rows;
    }

    private static function get_accounts_summary() {
        global $wpdb;
        $acc_table = $wpdb->prefix . 'aura_finance_accounts';
        $tx_table  = $wpdb->prefix . 'aura_finance_transactions';

        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$acc_table}'" ) ) {
            return array();
        }

        $rows = $wpdb->get_results(
            "SELECT a.id, a.name, a.account_type, a.currency, a.institution, a.initial_balance, a.current_balance, a.is_active,
                    (SELECT COALESCE(SUM(t.amount), 0) FROM {$tx_table} t WHERE t.destination_account_id = a.id AND t.transaction_type = 'income' AND t.status = 'approved' AND t.deleted_at IS NULL) AS total_incomes,
                    (SELECT COALESCE(SUM(t.amount), 0) FROM {$tx_table} t WHERE t.source_account_id = a.id AND t.transaction_type = 'expense' AND t.status = 'approved' AND t.deleted_at IS NULL) AS total_expenses,
                    (SELECT COUNT(t.id) FROM {$tx_table} t WHERE (t.source_account_id = a.id OR t.destination_account_id = a.id) AND t.status = 'approved' AND t.deleted_at IS NULL) AS tx_count
               FROM {$acc_table} a
              WHERE a.is_active = 1 AND a.deleted_at IS NULL
              ORDER BY a.id ASC",
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        return array_map( function ( $r ) {
            $initial = (float) $r['initial_balance'];
            $stored  = (float) $r['current_balance'];
            $calc    = $initial + (float) $r['total_incomes'] - (float) $r['total_expenses'];
            $bal     = $stored != 0 ? $stored : $calc;

            return array(
                'id'           => (int) $r['id'],
                'name'         => $r['name'],
                'account_type' => $r['account_type'],
                'currency'     => strtoupper( $r['currency'] ?: 'COP' ),
                'institution'  => $r['institution'] ?: '',
                'balance'      => $bal,
                'formatted'    => self::fmt_money( $bal ),
                'tx_count'     => (int) $r['tx_count'],
            );
        }, $rows );
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
            // Alertas de sobregiro mensual en Presupuesto de Bancos
            if ( class_exists( 'Aura_Financial_Accounts' ) && method_exists( 'Aura_Financial_Accounts', 'build_accounts_report_data' ) ) {
                $current_year = (int) current_time( 'Y' );
                $rep = Aura_Financial_Accounts::build_accounts_report_data( $current_year );
                if ( ! empty( $rep['budget']['months'] ) ) {
                    $month_names = array(
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                    );
                    foreach ( $rep['budget']['months'] as $m ) {
                        if ( isset( $m['remaining'] ) && (float) $m['remaining'] < 0 && (float) $m['spent'] > 0 ) {
                            $m_num = (int) $m['month_num'];
                            $m_name = $month_names[ $m_num ] ?? "Mes {$m_num}";
                            $alerts[] = array(
                                'type'    => 'danger',
                                'message' => sprintf(
                                    __( 'Presupuesto Bancos excedido en <strong>%s %d</strong>: Se gastaron %s frente a un límite de %s (Déficit: %s).', 'aura-suite' ),
                                    esc_html( $m_name ), $current_year,
                                    self::fmt_money( $m['spent'] ),
                                    self::fmt_money( $m['limit'] ),
                                    self::fmt_money( abs( (float) $m['remaining'] ) )
                                ),
                            );
                        }
                    }
                }
            }

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

            // Presupuestos por Área excedidos
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
                        __( 'Presupuesto de Área <strong>%s</strong>: %d%% ejecutado (%s de %s).', 'aura-suite' ),
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

    private static function get_bank_budget_summary( $start, $end, $manual_year = 0 ) {
        if ( ! ( current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' ) ) ) {
            return null;
        }

        if ( ! class_exists( 'Aura_Financial_Accounts' ) || ! method_exists( 'Aura_Financial_Accounts', 'build_accounts_report_data' ) ) {
            return null;
        }

        $detected_year = (int) date( 'Y', strtotime( $end ?: $start ) );
        if ( $detected_year < 2000 || $detected_year > 2100 ) {
            $detected_year = (int) current_time( 'Y' );
        }

        $year = ( $manual_year >= 2000 && $manual_year <= 2100 ) ? $manual_year : $detected_year;

        $report = Aura_Financial_Accounts::build_accounts_report_data( $year );
        if ( ! is_array( $report ) ) {
            return null;
        }

        return array(
            'year'          => $year,
            'detected_year' => $detected_year,
            'budget' => isset( $report['budget'] ) && is_array( $report['budget'] ) ? $report['budget'] : array(),
        );
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
