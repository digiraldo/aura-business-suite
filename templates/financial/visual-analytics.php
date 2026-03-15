<?php
/**
 * Template: Análisis Visual – Fase 3, Item 3.3
 *
 * @package AuraBusinessSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
$current_year  = date('Y');
$current_month = date('n');
?>
<div class="wrap aura-analytics-wrap">

    <h1 class="wp-heading-inline">
        <?php esc_html_e('Análisis Visual', 'aura-suite'); ?>
    </h1>

    <!-- ================================================================ -->
    <!-- BARRA DE FILTROS GLOBALES                                         -->
    <!-- ================================================================ -->
    <div class="aura-analytics-global-filters" id="aura-global-filters">
        <div class="aura-filters-row">
            <label for="aura-filter-start"><?php esc_html_e('Desde', 'aura-suite'); ?></label>
            <input type="date" id="aura-filter-start" value="<?php echo esc_attr($current_year . '-01-01'); ?>">

            <label for="aura-filter-end"><?php esc_html_e('Hasta', 'aura-suite'); ?></label>
            <input type="date" id="aura-filter-end" value="<?php echo esc_attr(date('Y-m-d')); ?>">

            <button type="button" id="aura-apply-filters" class="button button-primary">
                <?php esc_html_e('Aplicar', 'aura-suite'); ?>
            </button>
            <button type="button" id="aura-reset-filters" class="button">
                <?php esc_html_e('Resetear vista', 'aura-suite'); ?>
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- NAVEGACIÓN DE TABS                                                 -->
    <!-- ================================================================ -->
    <nav class="aura-analytics-tabs nav-tab-wrapper" id="aura-analytics-tabs">
        <a href="#tab-trends"      class="nav-tab nav-tab-active" data-tab="trends">
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e('Tendencias', 'aura-suite'); ?>
        </a>
        <a href="#tab-categories" class="nav-tab" data-tab="categories">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e('Distribución', 'aura-suite'); ?>
        </a>
        <a href="#tab-comparison" class="nav-tab" data-tab="comparison">
            <span class="dashicons dashicons-controls-repeat"></span>
            <?php esc_html_e('Comparaciones', 'aura-suite'); ?>
        </a>
        <a href="#tab-patterns"   class="nav-tab" data-tab="patterns">
            <span class="dashicons dashicons-format-gallery"></span>
            <?php esc_html_e('Patrones', 'aura-suite'); ?>
        </a>
        <a href="#tab-budget"     class="nav-tab" data-tab="budget">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e('Presupuesto', 'aura-suite'); ?>
        </a>
    </nav>

    <!-- ================================================================ -->
    <!-- TAB 1: TENDENCIAS TEMPORALES                                       -->
    <!-- ================================================================ -->
    <div id="tab-trends" class="aura-tab-content active">
        <div class="aura-chart-toolbar">
            <div class="aura-toolbar-left">
                <label><?php esc_html_e('Granularidad', 'aura-suite'); ?></label>
                <div class="aura-btn-group" id="trends-granularity">
                    <button class="aura-btn-toggle" data-gran="day"><?php esc_html_e('Día', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-gran="week"><?php esc_html_e('Semana', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle active" data-gran="month"><?php esc_html_e('Mes', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-gran="quarter"><?php esc_html_e('Trimestre', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-gran="year"><?php esc_html_e('Año', 'aura-suite'); ?></button>
                </div>
            </div>
            <div class="aura-toolbar-right">
                <button type="button" class="aura-add-annotation" data-tab="trends">
                    <span class="dashicons dashicons-edit-large"></span>
                    <?php esc_html_e('Agregar nota', 'aura-suite'); ?>
                </button>
                <button type="button" class="aura-fullscreen-btn" data-chart="chart-trends">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            </div>
        </div>

        <div class="aura-chart-container" id="chart-trends"></div>

        <div class="aura-chart-legend" id="trends-legend">
            <span class="legend-item income"><span class="legend-dot"></span><?php esc_html_e('Ingresos', 'aura-suite'); ?></span>
            <span class="legend-item expense"><span class="legend-dot"></span><?php esc_html_e('Egresos', 'aura-suite'); ?></span>
            <span class="legend-item balance"><span class="legend-dot"></span><?php esc_html_e('Balance', 'aura-suite'); ?></span>
            <span class="legend-item projection"><span class="legend-dot dashed"></span><?php esc_html_e('Proyección', 'aura-suite'); ?></span>
        </div>

        <div class="aura-annotations-list" id="trends-annotations" data-tab="trends"></div>
    </div><!-- #tab-trends -->

    <!-- ================================================================ -->
    <!-- TAB 2: DISTRIBUCIÓN POR CATEGORÍAS                                 -->
    <!-- ================================================================ -->
    <div id="tab-categories" class="aura-tab-content">
        <div class="aura-chart-toolbar">
            <div class="aura-toolbar-left">
                <label><?php esc_html_e('Ver', 'aura-suite'); ?></label>
                <div class="aura-btn-group" id="cat-type">
                    <button class="aura-btn-toggle active" data-val="both"><?php esc_html_e('Ambos', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-val="income"><?php esc_html_e('Ingresos', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-val="expense"><?php esc_html_e('Egresos', 'aura-suite'); ?></button>
                </div>

                <label><?php esc_html_e('Ordenar', 'aura-suite'); ?></label>
                <div class="aura-btn-group" id="cat-sort">
                    <button class="aura-btn-toggle active" data-val="amount"><?php esc_html_e('Monto', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-val="frequency"><?php esc_html_e('Frecuencia', 'aura-suite'); ?></button>
                    <button class="aura-btn-toggle" data-val="alpha"><?php esc_html_e('A-Z', 'aura-suite'); ?></button>
                </div>

                <label><?php esc_html_e('Límite', 'aura-suite'); ?></label>
                <select id="cat-limit">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                </select>
            </div>
            <div class="aura-toolbar-right">
                <button type="button" class="aura-fullscreen-btn" data-chart="chart-categories">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            </div>
        </div>

        <div class="aura-chart-container" id="chart-categories"></div>
    </div><!-- #tab-categories -->

    <!-- ================================================================ -->
    <!-- TAB 3: COMPARACIONES                                               -->
    <!-- ================================================================ -->
    <div id="tab-comparison" class="aura-tab-content">
        <div class="aura-comparison-periods">
            <div class="period-picker">
                <h3><?php esc_html_e('Período A', 'aura-suite'); ?></h3>
                <label><?php esc_html_e('Desde', 'aura-suite'); ?></label>
                <input type="date" id="cmp-a-start" value="<?php echo esc_attr($current_year . '-01-01'); ?>">
                <label><?php esc_html_e('Hasta', 'aura-suite'); ?></label>
                <input type="date" id="cmp-a-end" value="<?php echo esc_attr($current_year . '-06-30'); ?>">
            </div>
            <div class="period-vs"><?php esc_html_e('vs', 'aura-suite'); ?></div>
            <div class="period-picker">
                <h3><?php esc_html_e('Período B', 'aura-suite'); ?></h3>
                <label><?php esc_html_e('Desde', 'aura-suite'); ?></label>
                <input type="date" id="cmp-b-start" value="<?php echo esc_attr($current_year . '-07-01'); ?>">
                <label><?php esc_html_e('Hasta', 'aura-suite'); ?></label>
                <input type="date" id="cmp-b-end" value="<?php echo esc_attr(date('Y-m-d')); ?>">
            </div>
            <button type="button" id="cmp-apply" class="button button-primary">
                <?php esc_html_e('Comparar', 'aura-suite'); ?>
            </button>
        </div>

        <div class="aura-chart-container" id="chart-comparison"></div>

        <div class="aura-comparison-table-wrap">
            <h3><?php esc_html_e('Diferencias por categoría', 'aura-suite'); ?></h3>
            <table class="aura-comparison-table wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Período A', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Período B', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Dif. Absoluta', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Dif. %', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody id="cmp-table-body">
                    <tr><td colspan="5" class="aura-empty-row"><?php esc_html_e('Seleccione los períodos y haga clic en Comparar.', 'aura-suite'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div><!-- #tab-comparison -->

    <!-- ================================================================ -->
    <!-- TAB 4: ANÁLISIS DE PATRONES                                        -->
    <!-- ================================================================ -->
    <div id="tab-patterns" class="aura-tab-content">
        <div class="aura-patterns-grid">
            <div class="aura-pattern-card">
                <h3><?php esc_html_e('Heatmap: Actividad por día de la semana', 'aura-suite'); ?></h3>
                <div class="aura-chart-container short" id="chart-heatmap"></div>
            </div>
            <div class="aura-pattern-card">
                <h3><?php esc_html_e('Frecuencia vs Monto por Categoría', 'aura-suite'); ?></h3>
                <div class="aura-fullscreen-btn-wrap">
                    <button type="button" class="aura-fullscreen-btn" data-chart="chart-scatter">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                    </button>
                </div>
                <div class="aura-chart-container short" id="chart-scatter"></div>
            </div>
        </div>

        <div class="aura-outliers-wrap">
            <h3><?php esc_html_e('Transacciones atípicas (outliers)', 'aura-suite'); ?></h3>
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
                    <tr><td colspan="5" class="aura-empty-row"><?php esc_html_e('Cargando…', 'aura-suite'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div><!-- #tab-patterns -->

    <!-- ================================================================ -->
    <!-- TAB 5: PRESUPUESTO VS REALIDAD                                     -->
    <!-- ================================================================ -->
    <div id="tab-budget" class="aura-tab-content">
        <div class="aura-budget-controls">
            <label><?php esc_html_e('Año', 'aura-suite'); ?></label>
            <select id="budget-year">
                <?php for ($y = $current_year - 2; $y <= $current_year + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php selected($y, $current_year); ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>

            <label><?php esc_html_e('Mes', 'aura-suite'); ?></label>
            <select id="budget-month">
                <?php
                $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                foreach ($months as $i => $m):
                    $n = $i + 1;
                ?>
                    <option value="<?php echo $n; ?>" <?php selected($n, $current_month); ?>><?php echo esc_html($m); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="button" id="budget-load" class="button button-primary">
                <?php esc_html_e('Cargar', 'aura-suite'); ?>
            </button>
            <button type="button" id="budget-edit" class="button">
                <?php esc_html_e('Editar presupuestos', 'aura-suite'); ?>
            </button>
        </div>

        <div class="aura-chart-container" id="chart-budget"></div>

        <div class="aura-budget-table-wrap">
            <table class="aura-budget-table wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Presupuesto', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Ejecutado', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('% Ejecución', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Proyección mes', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Estado', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody id="budget-table-body">
                    <tr><td colspan="6" class="aura-empty-row"><?php esc_html_e('Seleccione año y mes.', 'aura-suite'); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div><!-- #tab-budget -->

</div><!-- .aura-analytics-wrap -->

<!-- ================================================================ -->
<!-- MODALES                                                            -->
<!-- ================================================================ -->

<!-- Modal: Anotación -->
<div id="aura-annotation-modal" class="aura-modal" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-box">
        <h2><?php esc_html_e('Agregar anotación', 'aura-suite'); ?></h2>
        <form id="aura-annotation-form">
            <input type="hidden" id="ann-tab" name="tab">
            <div class="form-group">
                <label for="ann-date"><?php esc_html_e('Fecha', 'aura-suite'); ?></label>
                <input type="date" id="ann-date" name="date" required>
            </div>
            <div class="form-group">
                <label for="ann-note"><?php esc_html_e('Nota', 'aura-suite'); ?></label>
                <textarea id="ann-note" name="note" rows="3" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Guardar', 'aura-suite'); ?></button>
                <button type="button" class="button aura-modal-close"><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar presupuesto -->
<div id="aura-budget-modal" class="aura-modal" style="display:none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-box aura-budget-modal-box">
        <h2><?php esc_html_e('Editar presupuestos', 'aura-suite'); ?></h2>
        <p class="description"><?php esc_html_e('Define el monto mensual por categoría.', 'aura-suite'); ?></p>
        <div id="budget-form-items">
            <div class="aura-spinner"><?php esc_html_e('Cargando categorías…', 'aura-suite'); ?></div>
        </div>
        <div class="form-actions">
            <button type="button" id="budget-save" class="button button-primary"><?php esc_html_e('Guardar presupuestos', 'aura-suite'); ?></button>
            <button type="button" class="button aura-modal-close"><?php esc_html_e('Cancelar', 'aura-suite'); ?></button>
        </div>
    </div>
</div>

<!-- Fullscreen overlay -->
<div id="aura-fullscreen-overlay" class="aura-fullscreen-overlay" style="display:none;">
    <button type="button" id="aura-exit-fullscreen" class="aura-exit-fullscreen">
        <span class="dashicons dashicons-fullscreen-exit-alt"></span>
        <?php esc_html_e('Salir', 'aura-suite'); ?>
    </button>
    <div id="aura-fullscreen-chart"></div>
</div>
