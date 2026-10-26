<?php
/**
 * Template: Presupuestos por Área
 * Fase 5, Item 5.1 — Refactorizado Fase 8.3+ (presupuesto por área, categoría como referencia opcional)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// Usuarios con aura_areas_view_own pueden ver el presupuesto de su propia área
if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_view_own' ) ) {
    wp_die( esc_html__( 'Sin permisos para ver presupuestos', 'aura-suite' ) );
}
$can_manage = current_user_can( 'aura_finance_create' ) || current_user_can( 'manage_options' );
$can_delete = current_user_can( 'aura_finance_delete_all' ) || current_user_can( 'manage_options' );

// Áreas activas para filtro y formulario (Soporte Multi-Área y CBAC)
global $wpdb;
$_aura_user_area_only = (
    ! current_user_can( 'manage_options' ) &&
    ! current_user_can( 'aura_areas_view_all' ) &&
    current_user_can( 'aura_areas_view_own' )
);

if ( $_aura_user_area_only ) {
    $_aura_areas = Aura_Areas_Setup::get_user_areas( get_current_user_id() );
    $_aura_single_area = ( count( $_aura_areas ) === 1 ) ? $_aura_areas[0] : null;
    $_aura_user_area_id = $_aura_single_area ? (int) $_aura_single_area->id : 0;
} else {
    $_aura_areas = $wpdb->get_results(
        "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
    );
    $_aura_single_area = null;
    $_aura_user_area_id = 0;
}

$aura_active_main_tab = ( isset( $_GET['tab'] ) && $_GET['tab'] === 'areas' ) ? 'areas' : 'categories';
?>
<div class="wrap aura-budgets-wrap aura-module-wrap aura-app-wrapper">
    <h1 class="screen-reader-text"><?php esc_html_e( 'Presupuestos por Área', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">
    <div id="aura-wp-notices-container"></div>

    <!-- ── Hero Banner Superior Unificado ─────────────────────────────── -->
    <div class="aura-hero-header">
        <div class="aura-hero-content">
            <div class="aura-hero-icon-box">
                <span class="dashicons dashicons-chart-pie"></span>
            </div>
            <div class="aura-hero-titles">
                <div class="aura-hero-subtitle-row">
                    <span class="aura-tag-badge">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( 'Aura Finanzas', 'aura-suite' ); ?>
                    </span>
                    <span class="aura-tag-badge aura-tag-secondary">
                        <span class="dashicons dashicons-portfolio"></span>
                        <?php esc_html_e( 'Control Presupuestario & Inteligencia de Gasto', 'aura-suite' ); ?>
                    </span>
                </div>
                <h1 class="aura-hero-title"><?php esc_html_e( 'Presupuestos & Estadísticas Financieras', 'aura-suite' ); ?></h1>
                <p class="aura-hero-desc">
                    <?php esc_html_e( 'Control, monitoreo y comparación en tiempo real de techos presupuestarios autorizados por categoría de gasto (con desglose de subcategorías) y por área/departamento.', 'aura-suite' ); ?>
                </p>
            </div>
        </div>

        <div class="aura-hero-actions">
            <?php if ( $can_manage ) : ?>
            <button type="button" class="aura-btn aura-btn-primary aura-btn-shimmer" id="aura-new-budget-btn" style="<?php echo ( $aura_active_main_tab === 'areas' ) ? '' : 'display:none;'; ?>" data-tooltip="<?php esc_attr_e( 'Crear un nuevo presupuesto por área o programa', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-plus-alt2"></span>
                <span><?php esc_html_e( 'Nuevo Presupuesto por Área', 'aura-suite' ); ?></span>
            </button>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-transactions' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Ir al listado general de transacciones de egresos', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-list-view"></span>
                <span><?php esc_html_e( 'Transacciones', 'aura-suite' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-categories' ) ); ?>" class="aura-btn aura-btn-secondary" data-tooltip="<?php esc_attr_e( 'Ir a la administración y árbol de categorías financieras', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-category"></span>
                <span><?php esc_html_e( 'Catálogo de Categorías', 'aura-suite' ); ?></span>
            </a>
        </div>
    </div>

    <!-- ── PESTAÑAS DE PRIMER NIVEL (TABS MODERNOS) ─────────────────── -->
    <div class="aura-budget-main-nav">
        <button type="button" class="aura-main-tab-btn <?php echo ( $aura_active_main_tab === 'categories' ) ? 'active' : ''; ?>" data-tab="tab-category-budgets" id="aura-tab-btn-categories">
            <span class="dashicons dashicons-category"></span>
            <span><?php esc_html_e( '📊 Presupuesto por Categorías & Subcategorías', 'aura-suite' ); ?></span>
        </button>
        <button type="button" class="aura-main-tab-btn <?php echo ( $aura_active_main_tab === 'areas' ) ? 'active' : ''; ?>" data-tab="tab-area-budgets" id="aura-tab-btn-areas">
            <span class="dashicons dashicons-portfolio"></span>
            <span><?php esc_html_e( '🏢 Presupuestos por Áreas / Departamentos', 'aura-suite' ); ?></span>
        </button>
    </div>

    <!-- ================================================================
         PESTAÑA 1: PRESUPUESTOS Y ESTADÍSTICAS POR CATEGORÍA
         ============================================================== -->
    <div id="tab-category-budgets" class="aura-main-tab-panel <?php echo ( $aura_active_main_tab === 'categories' ) ? 'active' : ''; ?>" style="<?php echo ( $aura_active_main_tab === 'categories' ) ? '' : 'display:none;'; ?>">
        
        <!-- Barra de Control y Filtros de Categoría -->
        <div class="aura-cat-budget-control-bar aura-glass-card">
            <div class="aura-cat-control-left">
                <!-- Selector de Año Fiscal -->
                <div class="aura-control-field">
                    <label for="aura-cat-budget-year">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e( 'Ejercicio Fiscal:', 'aura-suite' ); ?>
                    </label>
                    <select id="aura-cat-budget-year" class="aura-select-styled">
                        <?php
                        $cur_y = (int) current_time( 'Y' );
                        for ( $y = $cur_y + 1; $y >= 2022; $y-- ) :
                        ?>
                            <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $y, $cur_y ); ?>>
                                <?php echo esc_html( $y ); ?> <?php echo ( $y === $cur_y ) ? esc_html__( '(Año en Curso)', 'aura-suite' ) : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Selector de Categoría Principal -->
                <div class="aura-control-field aura-control-field--grow">
                    <label for="aura-cat-budget-category-select">
                        <span class="dashicons dashicons-tag"></span>
                        <?php esc_html_e( 'Categoría Principal:', 'aura-suite' ); ?>
                    </label>
                    <select id="aura-cat-budget-category-select" class="aura-select-styled">
                        <option value=""><?php esc_html_e( 'Cargando catálogo de categorías…', 'aura-suite' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="aura-cat-control-right">
                <?php if ( $can_manage ) : ?>
                <button type="button" class="aura-btn aura-btn-primary aura-btn-shimmer" id="aura-cat-budget-open-modal-btn">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <span><?php esc_html_e( 'Asignar / Editar Presupuesto', 'aura-suite' ); ?></span>
                </button>
                <?php endif; ?>
                <button type="button" class="aura-btn aura-btn-secondary" id="aura-cat-budget-export-btn" data-tooltip="<?php esc_attr_e( 'Exportar resumen detallado de esta categoría y subcategorías a CSV/Excel', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <span><?php esc_html_e( 'Exportar CSV', 'aura-suite' ); ?></span>
                </button>
                <button type="button" class="aura-btn aura-btn-secondary" id="aura-cat-budget-print-btn" data-tooltip="<?php esc_attr_e( 'Imprimir reporte oficial de ejecución presupuestaria', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-printer"></span>
                    <span><?php esc_html_e( 'Imprimir / PDF', 'aura-suite' ); ?></span>
                </button>
            </div>
        </div>

        <!-- Indicador de carga -->
        <div id="aura-cat-budget-loading" class="aura-budgets-loading" style="display:none;">
            <span class="spinner is-active"></span>
            <span><?php esc_html_e( 'Calculando estadísticas presupuestarias y transacciones…', 'aura-suite' ); ?></span>
        </div>

        <!-- Contenedor dinámico de estadísticas de categoría -->
        <div id="aura-cat-budget-content">

            <!-- Encabezado Imprimible Formal (Visible solo en @media print) -->
            <div class="aura-cat-print-header" id="aura-cat-print-header" style="display:none;">
                <div class="aura-print-header-top">
                    <div class="aura-print-brand">
                        <h2 class="aura-print-title"><?php esc_html_e( 'REPORTE EJECUTIVO DE EJECUCIÓN PRESUPUESTARIA', 'aura-suite' ); ?></h2>
                        <p class="aura-print-subtitle"><?php esc_html_e( 'Aura Business Suite — Módulo de Inteligencia Financiera', 'aura-suite' ); ?></p>
                    </div>
                    <div class="aura-print-meta">
                        <div><strong><?php esc_html_e( 'Fecha de Emisión:', 'aura-suite' ); ?></strong> <span id="aura-print-date"><?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?></span></div>
                        <div><strong><?php esc_html_e( 'Ejercicio Fiscal:', 'aura-suite' ); ?></strong> <span id="aura-print-fiscal-year">2026</span></div>
                    </div>
                </div>
                <div class="aura-print-category-info">
                    <div><strong><?php esc_html_e( 'Rubro / Categoría Principal:', 'aura-suite' ); ?></strong> <span id="aura-print-category-name">—</span></div>
                    <div><strong><?php esc_html_e( 'Dictamen de Consumo:', 'aura-suite' ); ?></strong> <span id="aura-print-status-label">—</span></div>
                </div>
            </div>

            <!-- Banner Alerta de Estado del Año -->
            <div id="aura-cat-budget-status-banner" class="aura-cat-status-banner" style="display:none;"></div>

            <!-- ── Grid de Tarjetas KPI Gerenciales ───────────────────── -->
            <div class="aura-cat-kpi-grid" id="aura-cat-budget-kpis">
                <!-- KPI 1: Presupuesto Anual -->
                <div class="aura-kpi-card card-lift kpi-blue">
                    <div class="aura-kpi-head">
                        <span class="aura-kpi-label"><?php esc_html_e( 'Presupuesto Autorizado', 'aura-suite' ); ?></span>
                        <span class="aura-kpi-icon-box"><span class="dashicons dashicons-calculator"></span></span>
                    </div>
                    <div class="aura-kpi-value" id="kpi-cat-budget-amount">$0.00</div>
                    <div class="aura-kpi-foot">
                        <span class="aura-kpi-sub" id="kpi-cat-budget-monthly"><?php esc_html_e( '$0.00 / mes promedio', 'aura-suite' ); ?></span>
                        <span class="aura-badge-mini" id="kpi-cat-budget-mode-badge"><?php esc_html_e( 'Monto Fijo', 'aura-suite' ); ?></span>
                    </div>
                </div>

                <!-- KPI 2: Gasto Acumulado YTD -->
                <div class="aura-kpi-card card-lift kpi-emerald">
                    <div class="aura-kpi-head">
                        <span class="aura-kpi-label"><?php esc_html_e( 'Gasto Real Ejecutado', 'aura-suite' ); ?></span>
                        <span class="aura-kpi-icon-box"><span class="dashicons dashicons-money-alt"></span></span>
                    </div>
                    <div class="aura-kpi-value" id="kpi-cat-spent-amount">$0.00</div>
                    <div class="aura-kpi-foot">
                        <span class="aura-kpi-sub" id="kpi-cat-spent-tx-count"><?php esc_html_e( '0 transacciones aprobadas', 'aura-suite' ); ?></span>
                        <span class="pulse-dot"></span>
                    </div>
                </div>

                <!-- KPI 3: Saldo Disponible Remanente -->
                <div class="aura-kpi-card card-lift kpi-indigo" id="kpi-cat-remaining-card">
                    <div class="aura-kpi-head">
                        <span class="aura-kpi-label"><?php esc_html_e( 'Saldo Disponible Remanente', 'aura-suite' ); ?></span>
                        <span class="aura-kpi-icon-box"><span class="dashicons dashicons-vault"></span></span>
                    </div>
                    <div class="aura-kpi-value" id="kpi-cat-remaining-amount">$0.00</div>
                    <div class="aura-kpi-foot">
                        <span class="aura-kpi-sub" id="kpi-cat-remaining-label"><?php esc_html_e( 'Margen libre por gastar', 'aura-suite' ); ?></span>
                    </div>
                </div>

                <!-- KPI 4: Porcentaje de Ejecución y Semáforo -->
                <div class="aura-kpi-card card-lift kpi-amber" id="kpi-cat-pct-card">
                    <div class="aura-kpi-head">
                        <span class="aura-kpi-label"><?php esc_html_e( '% Ejecución Presupuestaria', 'aura-suite' ); ?></span>
                        <span class="aura-kpi-icon-box"><span class="dashicons dashicons-performance"></span></span>
                    </div>
                    <div class="aura-kpi-value" id="kpi-cat-pct-value">0.0%</div>
                    <div class="aura-kpi-progress-bar-wrap">
                        <div class="aura-kpi-progress-fill" id="kpi-cat-progress-fill" style="width:0%;"></div>
                    </div>
                    <div class="aura-kpi-foot">
                        <span class="aura-kpi-status-badge" id="kpi-cat-status-badge"><?php esc_html_e( 'Sin Presupuesto', 'aura-suite' ); ?></span>
                    </div>
                </div>

                <!-- KPI 5: Proyección de Cierre o Cierre Fiscal -->
                <div class="aura-kpi-card card-lift kpi-purple">
                    <div class="aura-kpi-head">
                        <span class="aura-kpi-label" id="kpi-cat-projection-title"><?php esc_html_e( 'Proyección al Cierre de Año', 'aura-suite' ); ?></span>
                        <span class="aura-kpi-icon-box"><span class="dashicons dashicons-chart-line"></span></span>
                    </div>
                    <div class="aura-kpi-value" id="kpi-cat-projection-amount">$0.00</div>
                    <div class="aura-kpi-foot">
                        <span class="aura-kpi-sub" id="kpi-cat-projection-desc"><?php esc_html_e( 'Ritmo estimado al 31 de Dic', 'aura-suite' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- ── Grid de Gráficos Interactivos (ApexCharts) ─────────── -->
            <div class="aura-cat-charts-grid">
                <!-- Gráfico 1: Curva de Ejecución Mensual -->
                <div class="aura-glass-card aura-chart-card">
                    <div class="aura-card-head">
                        <div class="aura-card-title-group">
                            <span class="aura-card-badge-icon"><span class="dashicons dashicons-chart-area"></span></span>
                            <div>
                                <h3 class="aura-card-title"><?php esc_html_e( 'Evolución Mensual: Presupuesto vs Gasto Real', 'aura-suite' ); ?></h3>
                                <p class="aura-card-subtitle"><?php esc_html_e( 'Monitoreo acumulativo de los 12 meses del ejercicio fiscal seleccionado.', 'aura-suite' ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="aura-chart-body" id="aura-cat-trend-chart" style="min-height:330px;">
                        <div class="aura-chart-placeholder"><?php esc_html_e( 'Cargando curva mensual…', 'aura-suite' ); ?></div>
                    </div>
                </div>

                <!-- Gráfico 2: Desglose y Concentración por Subcategorías -->
                <div class="aura-glass-card aura-chart-card">
                    <div class="aura-card-head">
                        <div class="aura-card-title-group">
                            <span class="aura-card-badge-icon"><span class="dashicons dashicons-chart-pie"></span></span>
                            <div>
                                <h3 class="aura-card-title"><?php esc_html_e( 'Distribución del Gasto por Subcategorías', 'aura-suite' ); ?></h3>
                                <p class="aura-card-subtitle"><?php esc_html_e( 'Participación porcentual de cada subcategoría en el gasto total del rubro.', 'aura-suite' ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="aura-chart-body" id="aura-cat-donut-chart" style="min-height:330px;">
                        <div class="aura-chart-placeholder"><?php esc_html_e( 'Cargando distribución de subcategorías…', 'aura-suite' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- ── Tabla Desplegable Tipo Árbol: Categoría y Subcategorías ── -->
            <div class="aura-glass-card aura-cat-table-card">
                <div class="aura-card-head">
                    <div class="aura-card-title-group">
                        <span class="aura-card-badge-icon"><span class="dashicons dashicons-list-view"></span></span>
                        <div>
                            <h3 class="aura-card-title">
                                <span id="aura-cat-table-family-name"><?php esc_html_e( 'Categoría Seleccionada', 'aura-suite' ); ?></span>
                                <span class="aura-badge-count" id="aura-cat-table-subs-count">0 <?php esc_html_e( 'subcategorías', 'aura-suite' ); ?></span>
                            </h3>
                            <p class="aura-card-subtitle"><?php esc_html_e( 'Haz clic en la fila de la categoría para desplegar o colapsar todas sus subcategorías vinculadas.', 'aura-suite' ); ?></p>
                        </div>
                    </div>
                    <div class="aura-card-actions">
                        <button type="button" class="aura-btn aura-btn-secondary aura-btn-sm" id="aura-cat-toggle-all-tree">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <span><?php esc_html_e( 'Desplegar / Colapsar Subcategorías', 'aura-suite' ); ?></span>
                        </button>
                    </div>
                </div>

                <div class="aura-table-responsive-wrap">
                    <table class="aura-cat-budget-table wp-list-table widefat fixed striped" id="aura-cat-budget-table">
                        <thead>
                            <tr>
                                <th style="width:36%;"><?php esc_html_e( 'Rubro / Categoría / Subcategoría', 'aura-suite' ); ?></th>
                                <th style="width:12%;text-align:center;"><?php esc_html_e( 'Transacciones', 'aura-suite' ); ?></th>
                                <th style="width:16%;text-align:right;"><?php esc_html_e( 'Gasto Real Acumulado', 'aura-suite' ); ?></th>
                                <th style="width:14%;text-align:center;"><?php esc_html_e( '% de la Categoría', 'aura-suite' ); ?></th>
                                <th style="width:14%;text-align:center;"><?php esc_html_e( '% del Presupuesto', 'aura-suite' ); ?></th>
                                <th style="width:8%;text-align:center;"><?php esc_html_e( 'Acción', 'aura-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="aura-cat-budget-table-body">
                            <tr>
                                <td colspan="6" class="aura-loading"><?php esc_html_e( 'Cargando desglose de la categoría…', 'aura-suite' ); ?></td>
                            </tr>
                        </tbody>
                        <tfoot id="aura-cat-budget-table-foot"></tfoot>
                    </table>
                </div><!-- .aura-table-responsive-wrap -->
            </div><!-- .aura-cat-table-card -->

            <!-- Pie de Reporte para Firmas y Validez Oficial (Visible solo en @media print) -->
            <div class="aura-cat-print-footer" id="aura-cat-print-footer" style="display:none;">
                <div class="aura-print-signatures">
                    <div class="aura-print-sig-box">
                        <div class="aura-sig-line"></div>
                        <span><?php esc_html_e( 'Responsable de Área / Solicitante', 'aura-suite' ); ?></span>
                    </div>
                    <div class="aura-print-sig-box">
                        <div class="aura-sig-line"></div>
                        <span><?php esc_html_e( 'Dirección Financiera / Aprobación', 'aura-suite' ); ?></span>
                    </div>
                </div>
                <div class="aura-print-watermark-note">
                    <?php esc_html_e( 'Documento generado automáticamente por Aura Business Suite. Todos los importes expresados en moneda de curso legal.', 'aura-suite' ); ?>
                </div>
            </div>

        </div><!-- #aura-cat-budget-content -->

    </div><!-- #tab-category-budgets -->


    <!-- ================================================================
         PESTAÑA 2: PRESUPUESTOS POR ÁREAS / DEPARTAMENTOS (Legacy/Existente)
         ============================================================== -->
    <div id="tab-area-budgets" class="aura-main-tab-panel <?php echo ( $aura_active_main_tab === 'areas' ) ? 'active' : ''; ?>" style="<?php echo ( $aura_active_main_tab === 'areas' ) ? '' : 'display:none;'; ?>">

        <!-- ── Resumen rápido / KPIs por Área ───────────────────────────── -->
        <div class="aura-budget-summary-bar" id="aura-budget-summary-bar">
            <div class="aura-summary-skeleton"></div>
        </div>

        <!-- ── Barra de Filtros y Búsqueda en Tiempo Real ────────────────── -->
        <div class="aura-budgets-filters">
            <div class="aura-filter-item aura-filter-search-item">
                <label for="aura-filter-search">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Búsqueda rápida', 'aura-suite' ); ?>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtra en tiempo real por nombre de área, departamento o categoría de referencia.', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </label>
                <div class="aura-search-input-wrap">
                    <input type="text" id="aura-filter-search" placeholder="<?php esc_attr_e( 'Buscar por área o categoría…', 'aura-suite' ); ?>" autocomplete="off">
                    <span class="dashicons dashicons-search aura-search-icon"></span>
                </div>
            </div>

            <div class="aura-filter-item">
                <label for="aura-filter-period">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Período', 'aura-suite' ); ?>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtra según el ciclo temporal de renovación (mensual, trimestral, semestral o anual).', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </label>
                <select id="aura-filter-period">
                    <option value=""><?php esc_html_e( 'Todos los períodos', 'aura-suite' ); ?></option>
                    <option value="monthly"><?php esc_html_e( 'Mensual', 'aura-suite' ); ?></option>
                    <option value="quarterly"><?php esc_html_e( 'Trimestral', 'aura-suite' ); ?></option>
                    <option value="semestral"><?php esc_html_e( 'Semestral', 'aura-suite' ); ?></option>
                    <option value="yearly"><?php esc_html_e( 'Anual', 'aura-suite' ); ?></option>
                </select>
            </div>

            <div class="aura-filter-item">
                <label for="aura-filter-status">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e( 'Estado', 'aura-suite' ); ?>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtra por nivel de consumo: En buen estado (<70%), Advertencia (70-89%), Crítico (90-99%) o Sobrepasado (>100%).', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </label>
                <select id="aura-filter-status">
                    <option value=""><?php esc_html_e( 'Todos los estados', 'aura-suite' ); ?></option>
                    <option value="ok"><?php esc_html_e( 'En buen estado (< 70%)', 'aura-suite' ); ?></option>
                    <option value="warning"><?php esc_html_e( 'Advertencia (70-89%)', 'aura-suite' ); ?></option>
                    <option value="critical"><?php esc_html_e( 'Crítico (90-99%)', 'aura-suite' ); ?></option>
                    <option value="overrun"><?php esc_html_e( 'Sobrepasado (> 100%)', 'aura-suite' ); ?></option>
                </select>
            </div>

            <?php if ( ! empty( $_aura_areas ) ) : ?>
            <div class="aura-filter-item">
                <label for="aura-filter-area">
                    <span class="dashicons dashicons-portfolio"></span>
                    <?php esc_html_e( 'Área', 'aura-suite' ); ?>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Filtra los presupuestos según el departamento o programa asignado.', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </label>
                <select id="aura-filter-area" <?php echo $_aura_user_area_only ? 'disabled' : ''; ?>>
                    <option value=""><?php esc_html_e( 'Todas las áreas', 'aura-suite' ); ?></option>
                    <?php foreach ( $_aura_areas as $_a ) : ?>
                    <option value="<?php echo esc_attr( $_a->id ); ?>"
                            <?php if ( $_aura_user_area_only ) selected( $_aura_user_area_id, $_a->id ); ?>>
                        <?php echo esc_html( $_a->name ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( $_aura_user_area_only && $_aura_user_area_id ) : ?>
                <input type="hidden" id="aura-filter-area-forced" value="<?php echo esc_attr( $_aura_user_area_id ); ?>">
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="aura-filter-actions">
                <?php if ( $can_manage ) : ?>
                <button type="button" class="aura-btn aura-btn-primary aura-btn-sm aura-btn-shimmer aura-trigger-new-budget" data-tooltip="<?php esc_attr_e( 'Crear un nuevo presupuesto por área o programa', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <span><?php esc_html_e( 'Nuevo Presupuesto', 'aura-suite' ); ?></span>
                </button>
                <?php endif; ?>
                <button type="button" class="button" id="aura-filters-clear" data-tooltip="<?php esc_attr_e( 'Restablecer todos los filtros y búsqueda', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <?php esc_html_e( 'Limpiar', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <!-- ── Tabla de presupuestos por área ───────────────────────────── -->
        <div id="aura-budgets-loading" class="aura-budgets-loading">
            <span class="spinner is-active"></span>
            <span><?php esc_html_e( 'Cargando presupuestos por área…', 'aura-suite' ); ?></span>
        </div>

        <div id="aura-budgets-list" style="display:none"></div>

        <div id="aura-budgets-empty" class="aura-empty-state" style="display:none; text-align: center; padding: 48px 24px; background: #fff; border-radius: 12px; border: 1px dashed #cbd5e1; margin-top: 16px;">
            <div style="font-size: 42px; line-height: 1; margin-bottom: 12px;">📊</div>
            <h3 style="margin: 0 0 8px 0; font-size: 17px; font-weight: 600; color: #1e293b;">
                <?php esc_html_e( 'No hay presupuestos registrados para los filtros seleccionados', 'aura-suite' ); ?>
            </h3>
            <p style="color: #64748b; font-size: 13px; max-width: 480px; margin: 0 auto 20px auto; line-height: 1.5;">
                <?php esc_html_e( 'Establece un techo presupuestario para un Área o Programa con desglose automático de categorías y subcategorías para monitorear el gasto en tiempo real.', 'aura-suite' ); ?>
            </p>
            <?php if ( $can_manage ) : ?>
            <button type="button" class="aura-btn aura-btn-primary aura-trigger-new-budget" style="display: inline-flex;">
                <span class="dashicons dashicons-plus-alt2"></span>
                <span><?php esc_html_e( 'Crear Nuevo Presupuesto por Área', 'aura-suite' ); ?></span>
            </button>
            <?php endif; ?>
        </div>

    </div><!-- #tab-area-budgets -->

</div><!-- .aura-budgets-wrap -->


<!-- ================================================================
     MODAL: Crear / Editar Presupuesto
     ============================================================== -->
<div id="aura-budget-modal" class="aura-modal-overlay" style="display:none" role="dialog" aria-modal="true">
    <div class="aura-modal-container aura-modal-content aura-budget-form-modal">
        <div class="aura-modal-header">
            <h2 id="aura-budget-modal-title"><?php esc_html_e( 'Nuevo Presupuesto', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" id="aura-budget-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <form id="aura-budget-form" autocomplete="off">
            <input type="hidden" id="budget_id" name="id" value="0">

            <div class="aura-form-grid">

                <!-- Área / Programa (REQUERIDO — Fase 8.3+) -->
                <div class="aura-form-group full-width">
                    <label for="budget_area_id">
                        <?php esc_html_e( 'Área / Programa *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Selecciona el área responsable a la que se le asignará el límite presupuestario.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <?php if ( ! empty( $_aura_areas ) ) : ?>
                    <select id="budget_area_id" name="area_id" required
                            <?php if ( $_aura_user_area_only ) echo 'disabled'; ?>>
                        <option value=""><?php esc_html_e( '— Seleccione un área —', 'aura-suite' ); ?></option>
                        <?php foreach ( $_aura_areas as $_a ) : ?>
                        <option value="<?php echo esc_attr( $_a->id ); ?>"
                                <?php if ( $_aura_user_area_only ) selected( $_aura_user_area_id, $_a->id ); ?>>
                            <?php echo esc_html( $_a->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( $_aura_user_area_only && $_aura_user_area_id ) : ?>
                    <input type="hidden" name="area_id" value="<?php echo esc_attr( $_aura_user_area_id ); ?>">
                    <?php endif; ?>
                    <?php else : ?>
                    <p class="description" style="color:#d63638;">
                        <?php esc_html_e( 'No hay áreas activas. Crea al menos una área antes de crear presupuestos.', 'aura-suite' ); ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Categoría (OPCIONAL — solo como referencia del tipo de gasto) -->
                <div class="aura-form-group full-width">
                    <label for="budget_category_id">
                        <?php esc_html_e( 'Categoría de referencia', 'aura-suite' ); ?>
                        <span style="font-weight:400;color:#8c8f94;font-size:12px;margin-left:4px;">(<?php esc_html_e( 'opcional', 'aura-suite' ); ?>)</span>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Opcional. Permite asociar el presupuesto a un rubro contable específico de egreso.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <select id="budget_category_id" name="category_id">
                        <option value=""><?php esc_html_e( '— Sin categoría de referencia (Techo Global del Área) —', 'aura-suite' ); ?></option>
                        <?php
                        global $wpdb;
                        $raw_cats = $wpdb->get_results( "SELECT id, name, type, color, icon, parent_id FROM {$wpdb->prefix}aura_finance_categories WHERE is_active = 1 AND type = 'expense' ORDER BY name ASC" );
                        
                        $p_cats   = [];
                        $ch_cats  = [];
                        foreach ( $raw_cats as $rc ) {
                            if ( empty( $rc->parent_id ) ) {
                                $p_cats[ (int) $rc->id ] = $rc;
                            } else {
                                $ch_cats[ (int) $rc->parent_id ][] = $rc;
                            }
                        }

                        // Helper inline para asignación semántica de emoji en PHP
                        $sem_emoji_resolver = function( $name, $raw_icon ) {
                            $icon = trim( (string) $raw_icon );
                            if ( ! empty( $icon ) && strpos( $icon, 'dashicons' ) === false ) {
                                return $icon;
                            }
                            $n = strtolower( iconv( 'UTF-8', 'ASCII//TRANSLIT', (string) $name ) );
                            if ( preg_match( '/piscina|alberca/', $n ) ) return '🏊';
                            if ( preg_match( '/taller|reparac|manten|mecanic|herramient/', $n ) ) return '🛠️';
                            if ( preg_match( '/limpiez|aseo|lavad|higien/', $n ) ) return '🧼';
                            if ( preg_match( '/quimic|cloro/', $n ) ) return '🧪';
                            if ( preg_match( '/agua|hidric/', $n ) ) return '💧';
                            if ( preg_match( '/jardin|planta|paisaj|cesped/', $n ) ) return '🍃';
                            if ( preg_match( '/nomin|sueld|salar|honorari|personal|rrhh/', $n ) ) return '👥';
                            if ( preg_match( '/combust|gasolin|diesel/', $n ) ) return '⛽';
                            if ( preg_match( '/vehicul|auto|camionet|flot/', $n ) ) return '🚗';
                            if ( preg_match( '/camion|flete|transp|carga|mudanz/', $n ) ) return '🚚';
                            if ( preg_match( '/viaj|viatic|vuelo|hotel|hospedaj/', $n ) ) return '✈️';
                            if ( preg_match( '/segur|poliz|siniestr/', $n ) ) return '🛡️';
                            if ( preg_match( '/aliment|comid|restaur|almuerz/', $n ) ) return '🍔';
                            if ( preg_match( '/cafe|bebid|cafeter/', $n ) ) return '☕';
                            if ( preg_match( '/impuest|fiscal|sat|haciend/', $n ) ) return '🏛️';
                            if ( preg_match( '/oficin|papel|utiles|toner/', $n ) ) return '📎';
                            if ( preg_match( '/tecno|softw|comput|sistem|hosting|nube|licenc/', $n ) ) return '💻';
                            if ( preg_match( '/telef|celular|internet/', $n ) ) return '📱';
                            if ( preg_match( '/market|publi|pauta|redes/', $n ) ) return '📢';
                            if ( preg_match( '/ventas|comision/', $n ) ) return '🏷️';
                            if ( preg_match( '/compras|proveed/', $n ) ) return '🛒';
                            if ( preg_match( '/legal|abogad|juridic/', $n ) ) return '⚖️';
                            if ( preg_match( '/banco|financ|prestam/', $n ) ) return '🏦';
                            if ( preg_match( '/alquiler|renta|inmuebl/', $n ) ) return '🏢';
                            if ( preg_match( '/luz|electr|energia/', $n ) ) return '⚡';
                            if ( preg_match( '/capacit|curso|educac/', $n ) ) return '🎓';
                            if ( preg_match( '/salud|medic|farmac|botiquin/', $n ) ) return '🩺';
                            return '📁';
                        };

                        foreach ( $p_cats as $pid => $pcat ) :
                            $pe = $sem_emoji_resolver( $pcat->name, $pcat->icon );
                            $subs = $ch_cats[ $pid ] ?? [];
                            $sub_cnt = count( $subs );
                            $optgroup_lbl = $pe . ' ' . $pcat->name . ( $sub_cnt > 0 ? " ({$sub_cnt} subcategorías)" : '' );
                        ?>
                            <optgroup label="<?php echo esc_attr( $optgroup_lbl ); ?>">
                                <option value="<?php echo esc_attr( $pcat->id ); ?>">
                                    ⭐ <?php echo esc_html( "{$pe} {$pcat->name} (Todo el rubro consolidado)" ); ?>
                                </option>
                                <?php foreach ( $subs as $scat ) :
                                    $se = $sem_emoji_resolver( $scat->name, $scat->icon );
                                ?>
                                    <option value="<?php echo esc_attr( $scat->id ); ?>">
                                        &nbsp;&nbsp;&nbsp;&nbsp;↳ <?php echo esc_html( "{$se} {$scat->name}" ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Si seleccionas un rubro principal, el presupuesto sumará y consolidará automáticamente todos los gastos de sus subcategorías.', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Monto -->
                <div class="aura-form-group">
                    <label for="budget_amount">
                        <?php esc_html_e( 'Monto del presupuesto *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Límite monetario total autorizado para el período configurado.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <div class="aura-input-prefix">
                        <span>$</span>
                        <input type="number" id="budget_amount" name="budget_amount" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                </div>

                <!-- Período -->
                <div class="aura-form-group">
                    <label>
                        <?php esc_html_e( 'Tipo de período *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Frecuencia con la que se renueva y controla este techo presupuestario.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <div class="aura-period-radios">
                        <label class="aura-radio-pill selected">
                            <input type="radio" name="period_type" value="monthly" checked>
                            <?php esc_html_e( 'Mensual', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-pill">
                            <input type="radio" name="period_type" value="quarterly">
                            <?php esc_html_e( 'Trimestral', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-pill">
                            <input type="radio" name="period_type" value="semestral">
                            <?php esc_html_e( 'Semestral', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-radio-pill">
                            <input type="radio" name="period_type" value="yearly">
                            <?php esc_html_e( 'Anual', 'aura-suite' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Desde -->
                <div class="aura-form-group">
                    <label for="budget_start_date">
                        <?php esc_html_e( 'Fecha inicio *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha inicial a partir de la cual se computan los egresos del presupuesto.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <input type="date" id="budget_start_date" name="start_date" required data-allow-future="true">
                </div>

                <!-- Hasta -->
                <div class="aura-form-group">
                    <label for="budget_end_date">
                        <?php esc_html_e( 'Fecha fin *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Fecha límite del ciclo presupuestario.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <input type="date" id="budget_end_date" name="end_date" required data-allow-future="true">
                </div>

                <!-- Alertas -->
                <div class="aura-form-group full-width">
                    <label>
                        <?php esc_html_e( 'Alertas automáticas', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Configura disparadores para enviar notificaciones automáticas por correo.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <div class="aura-alert-options">
                        <label class="aura-check-row">
                            <input type="checkbox" name="alert_on_threshold" id="alert_on_threshold" checked>
                            <?php esc_html_e( 'Enviar alerta al llegar a', 'aura-suite' ); ?>
                            <input type="number" id="alert_threshold" name="alert_threshold" value="80" min="1" max="99" style="width:60px;margin:0 4px">
                            %
                        </label>
                        <label class="aura-check-row">
                            <input type="checkbox" name="alert_on_exceed" id="alert_on_exceed" checked>
                            <?php esc_html_e( 'Enviar alerta al sobrepasar el 100%', 'aura-suite' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Notificar a -->
                <div class="aura-form-group full-width">
                    <label>
                        <?php esc_html_e( 'Destinatarios de alertas', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Usuarios y correos que recibirán los avisos de consumo presupuestario.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <div class="aura-notify-options">
                        <label class="aura-check-row">
                            <input type="checkbox" name="notify_creator" id="notify_creator" checked>
                            <?php esc_html_e( 'Creador del presupuesto', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-check-row">
                            <input type="checkbox" name="notify_admins" id="notify_admins" checked>
                            <?php esc_html_e( 'Administradores del sistema', 'aura-suite' ); ?>
                        </label>
                        <label class="aura-check-row" style="display:block">
                            <input type="checkbox" id="notify_extra_toggle">
                            <?php esc_html_e( 'Emails adicionales (separados por coma)', 'aura-suite' ); ?>
                            <input type="email" id="notify_emails" name="notify_emails" placeholder="email1@x.com, email2@x.com"
                                   style="display:none;width:100%;margin-top:6px" multiple>
                        </label>
                    </div>
                </div>

            </div><!-- .aura-form-grid -->

            <div class="aura-modal-footer">
                <button type="button" class="button" id="aura-budget-cancel"><?php esc_html_e( 'Cancelar', 'aura-suite' ); ?></button>
                <button type="submit" class="button button-primary" id="aura-budget-save">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Guardar Presupuesto', 'aura-suite' ); ?>
                </button>
            </div>

            <div class="aura-form-error" id="aura-budget-form-error" style="display:none"></div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL: Detalle de Presupuesto
     ============================================================== -->
<div id="aura-budget-detail-modal" class="aura-modal-overlay" style="display:none" role="dialog" aria-modal="true">
    <div class="aura-modal-container aura-modal-content aura-budget-detail-modal">
        <div class="aura-modal-header">
            <h2 id="aura-detail-title"><?php esc_html_e( 'Detalle del Presupuesto', 'aura-suite' ); ?></h2>
            <button type="button" class="aura-modal-close" id="aura-detail-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">✕</button>
        </div>

        <div class="aura-detail-grid">

            <!-- Gráfico y stats -->
            <div class="aura-detail-left">
                <div class="aura-detail-chart-wrap">
                    <div id="aura-budget-donut-chart"></div>
                </div>
                <div class="aura-detail-stats" id="aura-detail-stats"></div>

                <!-- Ajuste rápido -->
                <div class="aura-adjust-section" id="aura-adjust-section">
                    <h4>
                        <?php esc_html_e( 'Ajustar presupuesto', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Incrementa o disminuye el monto del presupuesto de forma inmediata.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </h4>
                    <div class="aura-adjust-form">
                        <select id="adj_type">
                            <option value="percent"><?php esc_html_e( '%', 'aura-suite' ); ?></option>
                            <option value="amount"><?php esc_html_e( 'Monto fijo', 'aura-suite' ); ?></option>
                        </select>
                        <input type="number" id="adj_value" step="0.01" placeholder="10" style="width:80px">
                        <button type="button" class="button button-small" id="aura-apply-adjust">
                            <?php esc_html_e( 'Aplicar', 'aura-suite' ); ?>
                        </button>
                    </div>
                    <p class="description"><?php esc_html_e( 'Ej: +10 para aumentar 10%; -500 para reducir $500', 'aura-suite' ); ?></p>
                </div>
            </div>

            <!-- Historial y transacciones (panel derecho con tabs) -->
            <div class="aura-detail-right">

                <!-- Gráfico histórico -->
                <h4>
                    <?php esc_html_e( 'Presupuesto vs. Ejecutado (últimos 6 períodos)', 'aura-suite' ); ?>
                    <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Comparativa de evolución entre el límite asignado y el gasto real en períodos previos.', 'aura-suite' ); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </span>
                </h4>
                <div id="aura-budget-history-chart"></div>

                <!-- Tabs: Transacciones / Análisis por Categoría -->
                <div class="aura-detail-tabs" style="margin-top:18px;">
                    <div class="aura-tab-nav" role="tablist">
                        <button type="button" class="aura-tab-btn aura-tab-active"
                                role="tab" aria-selected="true"
                                data-tab="aura-tab-transactions">
                            <span class="dashicons dashicons-list-view"></span>
                            <?php esc_html_e( 'Transacciones del período', 'aura-suite' ); ?>
                            <span class="aura-tab-counter-badge" id="aura-tab-tx-badge" style="display:none;">0</span>
                        </button>
                        <button type="button" class="aura-tab-btn"
                                role="tab" aria-selected="false"
                                data-tab="aura-tab-analysis">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php esc_html_e( 'Análisis por Categoría', 'aura-suite' ); ?>
                            <span class="aura-tab-counter-badge" id="aura-tab-cat-badge" style="display:none;">0</span>
                        </button>
                    </div>

                    <!-- Panel: Transacciones -->
                    <div id="aura-tab-transactions" class="aura-tab-panel" role="tabpanel">
                        <div class="aura-detail-tx-wrap">
                            <table class="widefat striped" id="aura-detail-transactions">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Monto', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aura-detail-tx-body">
                                    <tr><td colspan="5" class="aura-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Panel: Análisis por Categoría -->
                    <div id="aura-tab-analysis" class="aura-tab-panel" role="tabpanel" style="display:none;">
                        <div id="aura-cat-breakdown-loading" style="text-align:center;padding:20px;">
                            <span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span>
                            <?php esc_html_e( 'Cargando análisis…', 'aura-suite' ); ?>
                        </div>
                        <div id="aura-cat-breakdown-content" style="display:none;">
                            <!-- Gráfico de barras CSS -->
                            <div id="aura-cat-bar-chart" style="margin-bottom:16px;"></div>
                            <!-- Tabla de desglose -->
                            <table class="widefat striped" id="aura-cat-breakdown-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'N° Trans.', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Ejecutado', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( '% del Total', 'aura-suite' ); ?></th>
                                        <th><?php esc_html_e( 'Barra', 'aura-suite' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="aura-cat-breakdown-body"></tbody>
                                <tfoot id="aura-cat-breakdown-foot"></tfoot>
                            </table>
                            <!-- Alerta contextual -->
                            <div id="aura-cat-breakdown-alert" style="display:none;margin-top:12px;"></div>
                        </div>
                        <div id="aura-cat-breakdown-empty" style="display:none;padding:12px;color:#8c8f94;font-style:italic;">
                            <?php esc_html_e( 'Sin transacciones aprobadas en este período.', 'aura-suite' ); ?>
                        </div>
                    </div>

                </div><!-- .aura-detail-tabs -->
            </div>

        </div><!-- .aura-detail-grid -->

        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary" id="aura-detail-modal-close-btn"><?php esc_html_e( 'Cerrar Detalle', 'aura-suite' ); ?></button>
        </div>
    </div>
</div>

<!-- ================================================================
     MODAL: Configurar Presupuesto de Categoría & Subcategorías (Nuevo)
     ============================================================== -->
<div id="aura-category-budget-modal" class="aura-modal-overlay" style="display:none" role="dialog" aria-modal="true">
    <div class="aura-modal-container aura-modal-content aura-budget-form-modal" style="max-width:680px;">
        <div class="aura-modal-header" style="background:linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);">
            <div>
                <h2 id="aura-cat-modal-title" style="margin:0;color:#fff;display:flex;align-items:center;gap:8px;">
                    <span class="dashicons dashicons-money-alt"></span>
                    <span><?php esc_html_e( 'Asignar Presupuesto a la Categoría', 'aura-suite' ); ?></span>
                </h2>
                <p id="aura-cat-modal-subtitle" style="margin:4px 0 0;color:#94a3b8;font-size:12.5px;">
                    <?php esc_html_e( 'Define el techo financiero anual o porcentaje para esta categoría y sus subcategorías.', 'aura-suite' ); ?>
                </p>
            </div>
            <button type="button" class="aura-modal-close" id="aura-cat-modal-close-btn" aria-label="<?php esc_attr_e( 'Cerrar', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <form id="aura-cat-budget-form">
            <input type="hidden" id="aura-modal-cat-id" name="category_id" value="">
            <input type="hidden" id="aura-modal-cat-year" name="fiscal_year" value="">
            <input type="hidden" id="aura-modal-budget-id" name="budget_id" value="0">

            <div class="aura-modal-body" style="padding:22px 26px;">

                <!-- Ficha informativa de la categoría seleccionada -->
                <div class="aura-cat-modal-info-box" id="aura-cat-modal-info-box" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:18px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div id="aura-cat-modal-icon-badge" style="width:38px;height:38px;border-radius:8px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;">
                            📁
                        </div>
                        <div>
                            <strong id="aura-cat-modal-cat-name" style="font-size:15px;color:#0f172a;display:block;">—</strong>
                            <span id="aura-cat-modal-subs-info" style="font-size:12px;color:#64748b;">0 subcategorías vinculadas</span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;display:block;"><?php esc_html_e( 'Ejercicio Fiscal', 'aura-suite' ); ?></span>
                        <strong id="aura-cat-modal-year-display" style="font-size:14px;color:#1e293b;">2026</strong>
                    </div>
                </div>

                <!-- Selector de Modo: Monto vs Porcentaje -->
                <div class="aura-form-group full-width" style="margin-bottom:16px;">
                    <label style="display:block;font-weight:700;margin-bottom:6px;font-size:13px;color:#334155;">
                        <?php esc_html_e( '¿Cómo deseas definir el presupuesto de esta categoría?', 'aura-suite' ); ?>
                    </label>
                    <div class="aura-period-radios" style="display:flex;gap:10px;">
                        <label class="aura-radio-pill selected" id="aura-mode-pill-amount" style="flex:1;text-align:center;padding:10px;cursor:pointer;">
                            <input type="radio" name="calculation_mode" value="amount" checked style="margin-right:6px;">
                            <strong><?php esc_html_e( '💵 Monto Fijo ($)', 'aura-suite' ); ?></strong>
                        </label>
                        <label class="aura-radio-pill" id="aura-mode-pill-percentage" style="flex:1;text-align:center;padding:10px;cursor:pointer;">
                            <input type="radio" name="calculation_mode" value="percentage" style="margin-right:6px;">
                            <strong><?php esc_html_e( '📊 Porcentaje (%) del Gasto Anual', 'aura-suite' ); ?></strong>
                        </label>
                    </div>
                </div>

                <!-- Campo Monto -->
                <div class="aura-form-group full-width" id="aura-group-budget-amount" style="margin-bottom:16px;">
                    <label for="aura-modal-budget-amount" style="display:block;font-weight:700;margin-bottom:6px;font-size:13px;color:#334155;">
                        <?php esc_html_e( 'Monto Presupuestado Anual ($) *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Límite de gasto anual total para esta categoría y todas sus subcategorías.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <div class="aura-input-prefix">
                        <span>$</span>
                        <input type="number" id="aura-modal-budget-amount" name="budget_amount" step="0.01" min="0" placeholder="0.00" style="font-size:16px;font-weight:700;">
                    </div>
                    <p class="description" id="aura-modal-monthly-calc" style="margin-top:4px;color:#64748b;font-size:12px;">
                        <?php esc_html_e( 'Equivale a aprox. $0.00 al mes (distribuido parejo en 12 meses).', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Campo Porcentaje (oculto por defecto o visible al elegir porcentaje) -->
                <div class="aura-form-group full-width" id="aura-group-budget-percentage" style="display:none;margin-bottom:16px;">
                    <label for="aura-modal-budget-pct" style="display:block;font-weight:700;margin-bottom:6px;font-size:13px;color:#334155;">
                        <?php esc_html_e( 'Porcentaje asignado (%) *', 'aura-suite' ); ?>
                        <span class="aura-help-icon" data-tooltip="<?php esc_attr_e( 'Porcentaje representativo de la bolsa general anual que le corresponde a este rubro.', 'aura-suite' ); ?>">
                            <span class="dashicons dashicons-editor-help"></span>
                        </span>
                    </label>
                    <div class="aura-input-prefix">
                        <span>%</span>
                        <input type="number" id="aura-modal-budget-pct" name="budget_percentage" step="0.1" min="0.1" max="100" placeholder="Ej: 15.0" style="font-size:16px;font-weight:700;">
                    </div>
                    <p class="description" id="aura-modal-pct-calc" style="margin-top:4px;color:#0284c7;font-size:12px;">
                        <?php esc_html_e( 'Se calculará el monto equivalente en base a los gastos consolidados del ejercicio.', 'aura-suite' ); ?>
                    </p>
                </div>

                <!-- Desglose Mensual Opcional -->
                <div class="aura-form-group full-width" style="margin-bottom:18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <label style="font-weight:700;font-size:13px;color:#334155;">
                            <?php esc_html_e( 'Distribución Mensual (12 Meses):', 'aura-suite' ); ?>
                        </label>
                        <button type="button" class="button button-small" id="aura-toggle-monthly-dist-btn">
                            <span class="dashicons dashicons-calendar"></span>
                            <span><?php esc_html_e( 'Personalizar por Mes', 'aura-suite' ); ?></span>
                        </button>
                    </div>

                    <div id="aura-monthly-dist-container" style="display:none;background:#f8fafc;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
                        <p class="description" style="margin-bottom:10px;font-size:11.5px;color:#64748b;">
                            <?php esc_html_e( 'Puedes ingresar un tope diferente para cada mes o usar el botón "Distribuir Parejo".', 'aura-suite' ); ?>
                            <button type="button" class="button button-small" id="aura-distribute-even-btn" style="margin-left:6px;">
                                <?php esc_html_e( 'Distribuir Parejo (÷12)', 'aura-suite' ); ?>
                            </button>
                        </p>
                        <div class="aura-monthly-grid-12" style="display:grid;grid-template-columns:repeat(4, 1fr);gap:8px;">
                            <?php
                            $m_abbr = [ 1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dic' ];
                            foreach ( $m_abbr as $mnum => $mname ) :
                            ?>
                            <div style="background:#fff;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                                <label style="font-size:11px;font-weight:700;color:#64748b;display:block;"><?php echo esc_html( $mname ); ?></label>
                                <input type="number" class="aura-modal-month-input" data-month="<?php echo esc_attr( $mnum ); ?>" step="0.01" min="0" placeholder="0.00" style="width:100%;height:28px;font-size:12px;padding:2px 4px;margin-top:2px;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Umbral de Alertas y Notificaciones -->
                <div class="aura-form-group full-width" style="background:#f1f5f9;padding:14px;border-radius:8px;">
                    <label style="font-weight:700;font-size:13px;color:#1e293b;display:block;margin-bottom:8px;">
                        <span class="dashicons dashicons-bell"></span>
                        <?php esc_html_e( 'Alertas y Notificaciones de Consumo', 'aura-suite' ); ?>
                    </label>

                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <label style="font-size:12.5px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" id="aura-modal-alert-threshold-chk" checked>
                            <span><?php esc_html_e( 'Enviar alerta preventiva al alcanzar el', 'aura-suite' ); ?></span>
                            <input type="number" id="aura-modal-alert-threshold" value="80" min="1" max="99" style="width:55px;height:28px;text-align:center;">
                            <span>% <?php esc_html_e( 'del presupuesto', 'aura-suite' ); ?></span>
                        </label>
                        <label style="font-size:12.5px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" id="aura-modal-alert-exceed-chk" checked>
                            <span><?php esc_html_e( 'Enviar alerta crítica inmediata si se sobrepasa el 100% (Sobregiro)', 'aura-suite' ); ?></span>
                        </label>
                    </div>
                </div>

            </div>

            <div class="aura-modal-footer" style="display:flex;justify-content:space-between;align-items:center;padding:16px 26px;background:#f8fafc;border-top:1px solid #e2e8f0;">
                <div>
                    <button type="button" class="aura-btn aura-btn-danger aura-btn-sm" id="aura-cat-modal-delete-btn" style="display:none;">
                        <span class="dashicons dashicons-trash"></span>
                        <span><?php esc_html_e( 'Eliminar Presupuesto', 'aura-suite' ); ?></span>
                    </button>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" class="aura-btn aura-btn-secondary" id="aura-cat-modal-cancel-btn">
                        <?php esc_html_e( 'Cancelar', 'aura-suite' ); ?>
                    </button>
                    <button type="submit" class="aura-btn aura-btn-primary aura-btn-shimmer" id="aura-cat-modal-save-btn">
                        <span class="dashicons dashicons-saved"></span>
                        <span><?php esc_html_e( 'Guardar Presupuesto', 'aura-suite' ); ?></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
