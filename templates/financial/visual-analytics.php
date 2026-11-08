<?php
/**
 * Template: Análisis Visual Financiero
 * Muestra 5 dimensiones interactivas de análisis avanzado:
 * 1. Tendencias Temporales y Proyección
 * 2. Distribución y Concentración por Categorías
 * 3. Comparación entre Períodos
 * 4. Análisis de Patrones y Outliers
 * 5. Presupuesto vs Realidad
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_year  = date('Y');
$current_month = date('n');
?>

<div class="aura-app-wrapper">
<div class="wrap aura-app-context aura-analytics-page aura-analytics-wrap">

    <!-- ====================================================
         Cabecera de Impresión Oficial (@media print)
    ===================================================== -->
    <div class="aura-print-header">
        <div class="aura-print-header__brand-box">
            <?php
            $logo_url = get_site_icon_url(80);
            if ($logo_url) {
                echo '<img src="' . esc_url($logo_url) . '" alt="" class="aura-print-logo" loading="eager">';
            }
            ?>
            <div class="aura-print-header__brand-text">
                <h2><?php echo esc_html(get_bloginfo('name')); ?></h2>
                <span class="aura-print-header__tagline"><?php esc_html_e('MÓDULO FINANCIERO — ANÁLISIS VISUAL Y PREDICTIVO', 'aura-suite'); ?></span>
            </div>
        </div>
        <div class="aura-print-header__report-card">
            <h1><?php esc_html_e('Informe Ejecutivo de Inteligencia Financiera', 'aura-suite'); ?></h1>
            <div class="aura-print-period-pill">
                <span class="dashicons dashicons-chart-pie"></span>
                <span><?php printf(esc_html__('Ejercicio Fiscal %s', 'aura-suite'), $current_year); ?></span>
            </div>
        </div>
        <div class="aura-print-header__meta-box">
            <div class="aura-print-meta-item">
                <span><?php esc_html_e('Generado por', 'aura-suite'); ?>:</span>
                <strong class="aura-print-meta-value"><?php echo esc_html(wp_get_current_user()->display_name); ?></strong>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e('Fecha de Emisión', 'aura-suite'); ?>:</span>
                <span class="aura-print-meta-value"><?php echo esc_html(date_i18n('d/m/Y H:i')); ?></span>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e('Carácter', 'aura-suite'); ?>:</span>
                <span class="aura-print-badge-official"><?php esc_html_e('Estratégico / Gerencial', 'aura-suite'); ?></span>
            </div>
        </div>
    </div>

    <!-- ====================================================
         Cabecera de Pantalla
    ===================================================== -->
    <div class="aura-layout">
    <main class="aura-content">
        <header class="aura-analytics-hero aura-glass-card">
            <div class="aura-analytics-hero__main">
                <div class="aura-analytics-hero__icon">
                    <span class="dashicons dashicons-chart-pie"></span>
                </div>
                <div class="aura-analytics-hero__copy">
                    <p class="aura-analytics-eyebrow"><?php esc_html_e('MÓDULO FINANCIERO', 'aura-suite'); ?></p>
                    <h1 class="wp-heading-inline">
                        <?php esc_html_e('Análisis Visual Financiero', 'aura-suite'); ?>
                    </h1>
                    <p class="aura-analytics-intro">
                        <?php esc_html_e('Visualiza patrones, evalúa la evolución temporal, compara períodos y proyecta la ejecución presupuestaria con modelos estadísticos avanzados.', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            <div class="aura-analytics-hero__actions">
                <button type="button" id="aura-print-analytics-btn" class="btn btn-amber btn-shimmer btn-lift">
                    <span class="dashicons dashicons-printer"></span>
                    <span><?php esc_html_e('Imprimir / PDF', 'aura-suite'); ?></span>
                </button>
            </div>
        </header>

        <hr class="wp-header-end">

        <!-- ================================================================ -->
        <!-- BARRA DE FILTROS GLOBALES CON CHIPS                              -->
        <!-- ================================================================ -->
        <div class="aura-analytics-global-filters aura-glass-card" id="aura-global-filters">
            <div class="aura-filters-row">
                <div class="aura-filter-field">
                    <label for="aura-filter-start">
                        <?php esc_html_e('Desde', 'aura-suite'); ?>
                    </label>
                    <input type="date" id="aura-filter-start" class="input-fancy" value="<?php echo esc_attr($current_year . '-01-01'); ?>">
                </div>

                <div class="aura-filter-field">
                    <label for="aura-filter-end">
                        <?php esc_html_e('Hasta', 'aura-suite'); ?>
                    </label>
                    <input type="date" id="aura-filter-end" class="input-fancy" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                </div>

                <!-- Chips de Períodos Rápidos -->
                <div class="aura-quick-date-chips" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="aura-chip-btn" data-range="this_month"><?php esc_html_e('Este Mes', 'aura-suite'); ?></button>
                    <button type="button" class="aura-chip-btn" data-range="this_quarter"><?php esc_html_e('Este Trimestre', 'aura-suite'); ?></button>
                    <button type="button" class="aura-chip-btn active" data-range="this_year"><?php esc_html_e('Año Actual', 'aura-suite'); ?></button>
                    <button type="button" class="aura-chip-btn" data-range="last_year"><?php esc_html_e('Año Anterior', 'aura-suite'); ?></button>
                </div>

                <div class="aura-filter-actions">
                    <button type="button" id="aura-apply-filters" class="btn btn-primary btn-shimmer btn-lift">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Actualizar Gráficos', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" id="aura-reset-filters" class="btn btn-secondary btn-lift">
                        <span class="dashicons dashicons-undo"></span>
                        <span><?php esc_html_e('Resetear', 'aura-suite'); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- NAVEGACIÓN DE TABS                                               -->
        <!-- ================================================================ -->
        <nav class="aura-analytics-tabs nav-tab-wrapper aura-glass-card" id="aura-analytics-tabs" style="padding:4px 10px 0 10px;margin-bottom:0;">
            <a href="#tab-trends" class="nav-tab nav-tab-active" data-tab="trends">
                <span class="dashicons dashicons-chart-line"></span>
                <span><?php esc_html_e('Tendencias Temporales', 'aura-suite'); ?></span>
                <span class="aura-tooltip-wrap">
                    <span class="dashicons dashicons-info aura-help-icon" style="font-size:14px;width:14px;height:14px;color:#64748b;margin-left:4px;"></span>
                    <span class="aura-tooltip-box"><?php esc_html_e('Evolución histórica de ingresos, egresos, balance y proyección lineal futura.', 'aura-suite'); ?></span>
                </span>
            </a>
            <a href="#tab-categories" class="nav-tab" data-tab="categories">
                <span class="dashicons dashicons-chart-bar"></span>
                <span><?php esc_html_e('Distribución', 'aura-suite'); ?></span>
                <span class="aura-tooltip-wrap">
                    <span class="dashicons dashicons-info aura-help-icon" style="font-size:14px;width:14px;height:14px;color:#64748b;margin-left:4px;"></span>
                    <span class="aura-tooltip-box"><?php esc_html_e('Concentración de volumen por categoría y Pareto de impacto.', 'aura-suite'); ?></span>
                </span>
            </a>
            <a href="#tab-comparison" class="nav-tab" data-tab="comparison">
                <span class="dashicons dashicons-controls-repeat"></span>
                <span><?php esc_html_e('Comparaciones', 'aura-suite'); ?></span>
                <span class="aura-tooltip-wrap">
                    <span class="dashicons dashicons-info aura-help-icon" style="font-size:14px;width:14px;height:14px;color:#64748b;margin-left:4px;"></span>
                    <span class="aura-tooltip-box"><?php esc_html_e('Contraste directo entre dos períodos temporales (A vs B) y variación porcentual.', 'aura-suite'); ?></span>
                </span>
            </a>
            <a href="#tab-patterns" class="nav-tab" data-tab="patterns">
                <span class="dashicons dashicons-format-gallery"></span>
                <span><?php esc_html_e('Patrones y Outliers', 'aura-suite'); ?></span>
                <span class="aura-tooltip-wrap">
                    <span class="dashicons dashicons-info aura-help-icon" style="font-size:14px;width:14px;height:14px;color:#64748b;margin-left:4px;"></span>
                    <span class="aura-tooltip-box"><?php esc_html_e('Heatmap de actividad por día de la semana y detección de transacciones atípicas.', 'aura-suite'); ?></span>
                </span>
            </a>
            <a href="#tab-budget" class="nav-tab" data-tab="budget">
                <span class="dashicons dashicons-money-alt"></span>
                <span><?php esc_html_e('Presupuesto vs Real', 'aura-suite'); ?></span>
                <span class="aura-tooltip-wrap">
                    <span class="dashicons dashicons-info aura-help-icon" style="font-size:14px;width:14px;height:14px;color:#64748b;margin-left:4px;"></span>
                    <span class="aura-tooltip-box"><?php esc_html_e('Monitoreo del porcentaje de ejecución presupuestaria mensual y alertas de sobregasto.', 'aura-suite'); ?></span>
                </span>
            </a>
        </nav>

        <!-- ================================================================ -->
        <!-- TAB 1: TENDENCIAS TEMPORALES                                     -->
        <!-- ================================================================ -->
        <div id="tab-trends" class="aura-tab-content active aura-glass-card">
            <div class="aura-chart-toolbar">
                <div class="aura-toolbar-left">
                    <label><?php esc_html_e('Agrupación Temporal:', 'aura-suite'); ?></label>
                    <div class="aura-btn-group" id="trends-granularity">
                        <button class="aura-btn-toggle" data-gran="day"><?php esc_html_e('Día', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-gran="week"><?php esc_html_e('Semana', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle active" data-gran="month"><?php esc_html_e('Mes', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-gran="quarter"><?php esc_html_e('Trimestre', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-gran="year"><?php esc_html_e('Año', 'aura-suite'); ?></button>
                    </div>
                </div>
                <div class="aura-toolbar-right">
                    <button type="button" class="aura-add-annotation btn btn-secondary btn-sm btn-lift" data-tab="trends">
                        <span class="dashicons dashicons-edit-large"></span>
                        <span><?php esc_html_e('Agregar Anotación', 'aura-suite'); ?></span>
                    </button>
                    <button type="button" class="aura-fullscreen-btn btn btn-glass btn-sm btn-lift" data-chart="chart-trends" title="<?php esc_attr_e('Pantalla Completa', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                    </button>
                </div>
            </div>

            <div class="aura-chart-container" id="chart-trends"></div>

            <div class="aura-chart-legend" id="trends-legend">
                <span class="legend-item income"><span class="legend-dot"></span><?php esc_html_e('Ingresos', 'aura-suite'); ?></span>
                <span class="legend-item expense"><span class="legend-dot"></span><?php esc_html_e('Egresos', 'aura-suite'); ?></span>
                <span class="legend-item balance"><span class="legend-dot"></span><?php esc_html_e('Balance Neto', 'aura-suite'); ?></span>
                <span class="legend-item projection"><span class="legend-dot dashed"></span><?php esc_html_e('Proyección Lineal', 'aura-suite'); ?></span>
            </div>

            <div class="aura-annotations-list" id="trends-annotations" data-tab="trends"></div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 2: DISTRIBUCIÓN POR CATEGORÍAS                               -->
        <!-- ================================================================ -->
        <div id="tab-categories" class="aura-tab-content aura-glass-card" style="display:none;">
            <div class="aura-chart-toolbar">
                <div class="aura-toolbar-left">
                    <label><?php esc_html_e('Tipo de Flujo:', 'aura-suite'); ?></label>
                    <div class="aura-btn-group" id="cat-type">
                        <button class="aura-btn-toggle active" data-val="both"><?php esc_html_e('Ambos', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-val="income"><?php esc_html_e('Ingresos', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-val="expense"><?php esc_html_e('Egresos', 'aura-suite'); ?></button>
                    </div>

                    <label><?php esc_html_e('Ordenar Por:', 'aura-suite'); ?></label>
                    <div class="aura-btn-group" id="cat-sort">
                        <button class="aura-btn-toggle active" data-val="amount"><?php esc_html_e('Monto', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-val="frequency"><?php esc_html_e('Frecuencia', 'aura-suite'); ?></button>
                        <button class="aura-btn-toggle" data-val="alpha"><?php esc_html_e('A-Z', 'aura-suite'); ?></button>
                    </div>

                    <label for="cat-limit"><?php esc_html_e('Límite Top:', 'aura-suite'); ?></label>
                    <select id="cat-limit" class="select-fancy" style="height:34px;padding:4px 10px;border-radius:8px;">
                        <option value="5">Top 5</option>
                        <option value="10" selected>Top 10</option>
                        <option value="20">Top 20</option>
                    </select>
                </div>
                <div class="aura-toolbar-right">
                    <button type="button" class="aura-fullscreen-btn btn btn-glass btn-sm btn-lift" data-chart="chart-categories" title="<?php esc_attr_e('Pantalla Completa', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                    </button>
                </div>
            </div>

            <div class="aura-chart-container" id="chart-categories"></div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 3: COMPARACIONES DE PERÍODOS                                 -->
        <!-- ================================================================ -->
        <div id="tab-comparison" class="aura-tab-content aura-glass-card" style="display:none;">
            <div class="aura-comparison-periods">
                <div class="period-picker">
                    <h3><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Período Base (A)', 'aura-suite'); ?></h3>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <div>
                            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--aura-text-muted,#64748b);"><?php esc_html_e('Desde', 'aura-suite'); ?></label>
                            <input type="date" id="cmp-a-start" class="input-fancy" value="<?php echo esc_attr($current_year . '-01-01'); ?>">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--aura-text-muted,#64748b);"><?php esc_html_e('Hasta', 'aura-suite'); ?></label>
                            <input type="date" id="cmp-a-end" class="input-fancy" value="<?php echo esc_attr($current_year . '-06-30'); ?>">
                        </div>
                    </div>
                </div>
                <div class="period-vs"><?php esc_html_e('VS', 'aura-suite'); ?></div>
                <div class="period-picker">
                    <h3><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Período Comparativo (B)', 'aura-suite'); ?></h3>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <div>
                            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--aura-text-muted,#64748b);"><?php esc_html_e('Desde', 'aura-suite'); ?></label>
                            <input type="date" id="cmp-b-start" class="input-fancy" value="<?php echo esc_attr($current_year . '-07-01'); ?>">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--aura-text-muted,#64748b);"><?php esc_html_e('Hasta', 'aura-suite'); ?></label>
                            <input type="date" id="cmp-b-end" class="input-fancy" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                        </div>
                    </div>
                </div>
                <button type="button" id="cmp-apply" class="btn btn-primary btn-shimmer btn-lift" style="height:38px;align-self:flex-end;">
                    <span class="dashicons dashicons-controls-repeat"></span>
                    <span><?php esc_html_e('Comparar Períodos', 'aura-suite'); ?></span>
                </button>
            </div>

            <div class="aura-chart-container" id="chart-comparison"></div>

            <div class="aura-comparison-table-wrap">
                <h3 style="display:flex;align-items:center;gap:6px;">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('Desglose de Variaciones por Categoría', 'aura-suite'); ?>
                </h3>
                <div class="aura-table-responsive-wrap">
                    <table class="aura-comparison-table wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Período A', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Período B', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Dif. Absoluta', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Variación %', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="cmp-table-body">
                            <tr><td colspan="5" class="aura-empty-row"><?php esc_html_e('Selecciona los períodos y pulsa en Comparar.', 'aura-suite'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 4: ANÁLISIS DE PATRONES Y OUTLIERS                            -->
        <!-- ================================================================ -->
        <div id="tab-patterns" class="aura-tab-content aura-glass-card" style="display:none;">
            <div class="aura-patterns-grid">
                <div class="aura-pattern-card aura-glass-card">
                    <h3 style="display:flex;align-items:center;gap:6px;">
                        <span class="dashicons dashicons-grid-view"></span>
                        <?php esc_html_e('Heatmap: Actividad por Día de la Semana y Hora', 'aura-suite'); ?>
                    </h3>
                    <div class="aura-chart-container short" id="chart-heatmap"></div>
                </div>
                <div class="aura-pattern-card aura-glass-card">
                    <h3 style="display:flex;align-items:center;gap:6px;">
                        <span class="dashicons dashicons-chart-scatter"></span>
                        <?php esc_html_e('Dispersión: Frecuencia vs Monto por Categoría', 'aura-suite'); ?>
                    </h3>
                    <div class="aura-fullscreen-btn-wrap">
                        <button type="button" class="aura-fullscreen-btn btn btn-glass btn-sm btn-lift" data-chart="chart-scatter" title="<?php esc_attr_e('Pantalla Completa', 'aura-suite'); ?>">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                        </button>
                    </div>
                    <div class="aura-chart-container short" id="chart-scatter"></div>
                </div>
            </div>

            <div class="aura-outliers-wrap aura-glass-card" style="padding:16px;margin-top:20px;">
                <h3 style="display:flex;align-items:center;gap:6px;margin:0 0 12px 0;">
                    <span class="dashicons dashicons-warning" style="color:#d97706;"></span>
                    <?php esc_html_e('Transacciones Atípicas Detectadas (Outliers Estadísticos)', 'aura-suite'); ?>
                </h3>
                <div class="aura-table-responsive-wrap">
                    <table class="aura-outliers-table wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Fecha', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Descripción', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Tipo', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Monto', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="outliers-body">
                            <tr><td colspan="5" class="aura-empty-row"><?php esc_html_e('Cargando registros…', 'aura-suite'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 5: PRESUPUESTO VS REALIDAD                                   -->
        <!-- ================================================================ -->
        <div id="tab-budget" class="aura-tab-content aura-glass-card" style="display:none;">
            <div class="aura-budget-controls">
                <label for="budget-year"><?php esc_html_e('Año Fiscal:', 'aura-suite'); ?></label>
                <select id="budget-year" class="select-fancy" style="height:36px;padding:4px 8px;border-radius:6px;">
                    <?php for ($y = $current_year - 2; $y <= $current_year + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php selected($y, $current_year); ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>

                <label for="budget-month"><?php esc_html_e('Mes:', 'aura-suite'); ?></label>
                <select id="budget-month" class="select-fancy" style="height:36px;padding:4px 8px;border-radius:6px;">
                    <?php
                    $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                    foreach ($months as $i => $m):
                        $n = $i + 1;
                    ?>
                        <option value="<?php echo $n; ?>" <?php selected($n, $current_month); ?>><?php echo esc_html($m); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="button" id="budget-load" class="btn btn-primary btn-shimmer btn-lift">
                    <span class="dashicons dashicons-search"></span>
                    <span><?php esc_html_e('Cargar Presupuesto', 'aura-suite'); ?></span>
                </button>
                <button type="button" id="budget-edit" class="btn btn-secondary btn-lift">
                    <span class="dashicons dashicons-edit"></span>
                    <span><?php esc_html_e('Configurar Metas Presupuestarias', 'aura-suite'); ?></span>
                </button>
            </div>

            <div class="aura-chart-container" id="chart-budget"></div>

            <div class="aura-budget-table-wrap">
                <h3 style="display:flex;align-items:center;gap:6px;">
                    <span class="dashicons dashicons-portfolio"></span>
                    <?php esc_html_e('Tabla de Cumplimiento Presupuestario', 'aura-suite'); ?>
                </h3>
                <div class="aura-table-responsive-wrap">
                    <table class="aura-budget-table wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Presupuesto Asignado', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Monto Ejecutado', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('% Ejecución', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Proyección Cierre de Mes', 'aura-suite'); ?></th>
                                <th><?php esc_html_e('Estado', 'aura-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="budget-table-body">
                            <tr><td colspan="6" class="aura-empty-row"><?php esc_html_e('Selecciona año y mes para consultar.', 'aura-suite'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ====================================================
             Footer Canónico Aura Design System
        ===================================================== -->
        <div class="adp-footer">
            <p>
                Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.015 2.896-.015 3.286 0 .315.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>DiGiraldo</a></strong> &nbsp;|&nbsp; © 2026 AURA Business Suite
            </p>
        </div>

        <!-- Pie de Impresión Oficial (@media print) -->
        <div class="aura-print-footer">
            <div class="aura-print-footer__left">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php esc_html_e('Documento oficial de control interno emitido por Aura Business Suite.', 'aura-suite'); ?>
            </div>
            <div class="aura-print-footer__right">
                <?php echo esc_html(get_bloginfo('name')); ?> &bull; <?php esc_html_e('Página 1', 'aura-suite'); ?>
            </div>
        </div>

    </main>
    </div>
</div>
</div>

<!-- ================================================================ -->
<!-- MODALES (OCULTOS POR DEFECTO CON DISPLAY:NONE)                   -->
<!-- ================================================================ -->

<!-- Modal: Anotación -->
<div id="aura-annotation-modal" class="aura-modal aura-modal-hidden" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-annotation-modal-title">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2 id="aura-annotation-modal-title">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e('Agregar Anotación al Gráfico', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="aura-modal-body">
            <form id="aura-annotation-form">
                <input type="hidden" id="ann-tab" name="tab">
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="ann-date" style="display:block;font-weight:700;font-size:12px;margin-bottom:6px;color:var(--aura-text-secondary, #475569);text-transform:uppercase;letter-spacing:0.5px;"><?php esc_html_e('Fecha de la Anotación', 'aura-suite'); ?></label>
                    <input type="date" id="ann-date" name="date" required class="input-fancy" style="width:100%;height:38px;border-radius:8px;padding:6px 12px;">
                </div>
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="ann-note" style="display:block;font-weight:700;font-size:12px;margin-bottom:6px;color:var(--aura-text-secondary, #475569);text-transform:uppercase;letter-spacing:0.5px;"><?php esc_html_e('Nota Explicativa (Hito, Evento, Ajuste)', 'aura-suite'); ?></label>
                    <textarea id="ann-note" name="note" rows="3" required class="input-fancy form-control" style="width:100%;border-radius:8px;padding:10px;" placeholder="<?php esc_attr_e('Ej: Inicio de campaña publicitaria, Pago extraordinario...', 'aura-suite'); ?>"></textarea>
                </div>
                <div class="form-actions" style="display:flex;justify-content:flex-end;gap:10px;padding-top:12px;border-top:1px solid var(--aura-border-color, #e2e8f0);">
                    <button type="button" class="btn btn-secondary btn-lift aura-modal-close"><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
                    <button type="submit" class="btn btn-primary btn-shimmer btn-lift"><?php esc_html_e('Guardar Anotación', 'aura-suite'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar presupuesto -->
<div id="aura-budget-modal" class="aura-modal aura-modal-hidden" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aura-budget-modal-title">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content aura-budget-modal-box">
        <div class="aura-modal-header">
            <h2 id="aura-budget-modal-title">
                <span class="dashicons dashicons-money-alt"></span>
                <?php esc_html_e('Definir Metas Presupuestarias', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="aura-modal-body">
            <p class="description" style="color:var(--aura-text-muted, #64748b);margin:0 0 16px 0;font-size:13px;line-height:1.5;">
                <?php esc_html_e('Asigna el límite presupuestario mensual para cada categoría de egreso.', 'aura-suite'); ?>
            </p>
            <div id="budget-form-items" style="max-height:360px;overflow-y:auto;padding-right:6px;">
                <div class="skeleton-modal-wrap" style="display:flex;flex-direction:column;gap:10px;padding:12px 0;">
                    <div class="skeleton skel-rect" style="height:44px;border-radius:8px;"></div>
                    <div class="skeleton skel-rect" style="height:44px;border-radius:8px;"></div>
                    <div class="skeleton skel-rect" style="height:44px;border-radius:8px;"></div>
                    <div class="skeleton skel-rect" style="height:44px;border-radius:8px;"></div>
                </div>
            </div>
            <div class="form-actions" style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--aura-border-color, #e2e8f0);">
                <button type="button" class="btn btn-secondary btn-lift aura-modal-close"><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
                <button type="button" id="budget-save" class="btn btn-emerald btn-shimmer btn-lift"><?php esc_html_e('Guardar Presupuestos', 'aura-suite'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen overlay -->
<div id="aura-fullscreen-overlay" class="aura-fullscreen-overlay" style="display:none;">
    <button type="button" id="aura-exit-fullscreen" class="aura-exit-fullscreen btn btn-danger btn-lift">
        <span class="dashicons dashicons-fullscreen-exit-alt"></span>
        <?php esc_html_e('Salir de Pantalla Completa', 'aura-suite'); ?>
    </button>
    <div id="aura-fullscreen-chart"></div>
</div>
