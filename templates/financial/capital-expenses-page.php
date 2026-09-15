<?php
/**
 * Template: Gastos de Capital (CapEx)
 *
 * Página administrativa para visualizar, auditar y analizar los
 * Gastos de Capital que NO forman parte del presupuesto operacional mensual.
 *
 * @since 1.1.0
 * @updated 2.1.0 Design System Centralizado
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$current_year = (int) date('Y');
?>
<div class="aura-app-wrapper aura-capital-expenses-page">
    <div class="wrap aura-app-context">

        <?php
        Aura_UI::render_page_header([
            'title'    => __('Gastos de Capital (CapEx)', 'aura-suite'),
            'subtitle' => __('Adquisiciones y proyectos especiales financiados por donaciones externas o fondos de capital.', 'aura-suite'),
            'icon'     => 'dashicons-building',
            'badges'   => [
                ['label' => sprintf(__('Año %d', 'aura-suite'), $current_year), 'variant' => 'blue', 'tooltip' => __('Período analizado por defecto', 'aura-suite')],
            ],
            'actions'  => [
                [
                    'label' => __('Transacciones Operativas', 'aura-suite'),
                    'url'   => admin_url('admin.php?page=aura-financial-transactions'),
                    'class' => 'aura-btn aura-btn-secondary',
                    'icon'  => 'dashicons-arrow-left-alt',
                ]
            ]
        ]);
        ?>

        <!-- Info Banner (Glass Card) -->
        <div class="aura-glass-card" style="padding: 18px 22px; margin-bottom: 24px; border-left: 4px solid #f59e0b; display: flex; gap: 14px; align-items: flex-start;">
            <span class="dashicons dashicons-info" style="color: #f59e0b; font-size: 24px; width: 24px; height: 24px; flex-shrink: 0; margin-top: 2px;"></span>
            <div>
                <strong style="color: #0f172a; display: block; margin-bottom: 4px; font-size: 14px;"><?php esc_html_e('¿Qué son los Gastos de Capital?', 'aura-suite'); ?></strong>
                <p style="margin: 0; color: #475569; font-size: 13px; line-height: 1.6;">
                    <?php esc_html_e('Son adquisiciones específicas financiadas por donaciones externas o agencias patrocinadoras (p.ej. sistema solar, vehículo, obras mayores). No afectan el presupuesto operacional mensual ni los porcentajes de gasto y se auditan de forma independiente.', 'aura-suite'); ?>
                </p>
            </div>
        </div>

        <!-- Cards de estadísticas (KPI Grid Canónico) -->
        <div id="aura-capital-stats-row" class="aura-stats-grid">
            <div class="aura-stat-card capital">
                <div class="aura-stat-icon-wrap">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="aura-stat-body">
                    <div class="aura-stat-label-wrap">
                        <span class="aura-stat-label"><?php esc_html_e('Total CapEx del Año', 'aura-suite'); ?></span>
                    </div>
                    <span id="stat-total-year" class="aura-stat-value">—</span>
                    <span class="aura-stat-sub"><?php esc_html_e('Ejecución total anual', 'aura-suite'); ?></span>
                </div>
            </div>

            <div class="aura-stat-card pending">
                <div class="aura-stat-icon-wrap">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="aura-stat-body">
                    <div class="aura-stat-label-wrap">
                        <span class="aura-stat-label"><?php esc_html_e('Transacciones Pendientes', 'aura-suite'); ?></span>
                    </div>
                    <span id="stat-pending" class="aura-stat-value">—</span>
                    <span class="aura-stat-sub"><?php esc_html_e('Por auditar / aprobar', 'aura-suite'); ?></span>
                </div>
            </div>

            <div class="aura-stat-card income">
                <div class="aura-stat-icon-wrap">
                    <span class="dashicons dashicons-tag"></span>
                </div>
                <div class="aura-stat-body">
                    <div class="aura-stat-label-wrap">
                        <span class="aura-stat-label"><?php esc_html_e('Mayor Categoría', 'aura-suite'); ?></span>
                    </div>
                    <span id="stat-top-cat" class="aura-stat-value" style="font-size: 1.15rem; word-break: break-word;">—</span>
                    <span class="aura-stat-sub"><?php esc_html_e('Mayor volumen ejecutado', 'aura-suite'); ?></span>
                </div>
            </div>

            <div class="aura-stat-card balance">
                <div class="aura-stat-icon-wrap">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="aura-stat-body">
                    <div class="aura-stat-label-wrap">
                        <span class="aura-stat-label"><?php esc_html_e('Año Analizado', 'aura-suite'); ?></span>
                    </div>
                    <span id="stat-year-label" class="aura-stat-value"><?php echo esc_html( $current_year ); ?></span>
                    <span class="aura-stat-sub"><?php esc_html_e('Ejercicio fiscal', 'aura-suite'); ?></span>
                </div>
            </div>
        </div>

        <!-- Gráfico mensual -->
        <div class="aura-glass-card" style="padding: 20px 24px; margin-bottom: 24px;">
            <h3 style="margin: 0 0 16px; font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-chart-bar" style="color: #7c3aed;"></span>
                <?php esc_html_e('CapEx por Mes', 'aura-suite'); ?>
            </h3>
            <div id="aura-capital-chart" style="display: flex; align-items: flex-end; gap: 8px; height: 120px; overflow: hidden;">
                <span style="color: #94a3b8; align-self: center;"><?php esc_html_e('Cargando...', 'aura-suite'); ?></span>
            </div>
            <div id="aura-capital-chart-labels" style="display: flex; gap: 8px; margin-top: 6px; font-size: 11px; color: #64748b;"></div>
        </div>

        <!-- Filtros -->
        <div class="aura-glass-card" style="padding: 18px 20px; margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
                <div class="aura-filter-group" style="display: flex; flex-direction: column; gap: 4px;">
                    <label for="cap-filter-year" style="font-size: 12px; font-weight: 600; color: #475569;">
                        <span class="dashicons dashicons-calendar-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e('Año:', 'aura-suite'); ?>
                    </label>
                    <select id="cap-filter-year" style="min-width: 100px;">
                        <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php selected($y, $current_year); ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="aura-filter-group" style="display: flex; flex-direction: column; gap: 4px;">
                    <label for="cap-filter-month" style="font-size: 12px; font-weight: 600; color: #475569;">
                        <span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e('Mes:', 'aura-suite'); ?>
                    </label>
                    <select id="cap-filter-month" style="min-width: 130px;">
                        <option value="0"><?php esc_html_e('Todos', 'aura-suite'); ?></option>
                        <?php
                        $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                                   7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                        foreach ($months as $num => $name):
                        ?>
                            <option value="<?php echo $num; ?>"><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aura-filter-group" style="display: flex; flex-direction: column; gap: 4px;">
                    <label for="cap-filter-category" style="font-size: 12px; font-weight: 600; color: #475569;">
                        <span class="dashicons dashicons-tag" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e('Categoría:', 'aura-suite'); ?>
                    </label>
                    <select id="cap-filter-category" style="min-width: 160px;">
                        <option value="0"><?php esc_html_e('Todas', 'aura-suite'); ?></option>
                    </select>
                </div>

                <div class="aura-filter-group" style="display: flex; flex-direction: column; gap: 4px;">
                    <label for="cap-filter-status" style="font-size: 12px; font-weight: 600; color: #475569;">
                        <span class="dashicons dashicons-admin-settings" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e('Estado:', 'aura-suite'); ?>
                    </label>
                    <select id="cap-filter-status" style="min-width: 130px;">
                        <option value=""><?php esc_html_e('Todos', 'aura-suite'); ?></option>
                        <option value="approved"><?php esc_html_e('Aprobados', 'aura-suite'); ?></option>
                        <option value="pending"><?php esc_html_e('Pendientes', 'aura-suite'); ?></option>
                        <option value="rejected"><?php esc_html_e('Rechazados', 'aura-suite'); ?></option>
                    </select>
                </div>

                <div class="aura-filter-group" style="display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 180px;">
                    <label for="cap-filter-search" style="font-size: 12px; font-weight: 600; color: #475569;">
                        <span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e('Buscar:', 'aura-suite'); ?>
                    </label>
                    <input type="text" id="cap-filter-search" placeholder="<?php esc_attr_e('Descripción, notas...', 'aura-suite'); ?>" style="height: 38px; border-radius: 6px; border: 1px solid #cbd5e1;">
                </div>

                <button type="button" class="button aura-btn-secondary" id="cap-clear-filters" style="height: 38px;">
                    <?php esc_html_e('Limpiar filtros', 'aura-suite'); ?>
                </button>
            </div>
        </div>

        <!-- Tabla Fluida Canónica -->
        <div class="aura-dt-wrapper">
            <table class="dataTable aura-table display responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Fecha', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Descripción', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Categoría', 'aura-suite'); ?></th>
                        <th><?php esc_html_e('Fuente / Notas', 'aura-suite'); ?></th>
                        <th style="text-align: right;"><?php esc_html_e('Monto', 'aura-suite'); ?></th>
                        <th style="text-align: center;"><?php esc_html_e('Estado', 'aura-suite'); ?></th>
                        <th style="text-align: center;"><?php esc_html_e('Ref.', 'aura-suite'); ?></th>
                    </tr>
                </thead>
                <tbody id="aura-capital-tbody">
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 40px; color: #888;">
                            <span class="spinner is-active" style="float:none; margin: 0 auto 8px; display: block;"></span>
                            <?php esc_html_e('Cargando gastos de capital...', 'aura-suite'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div id="aura-capital-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; font-size: 13px; color: #64748b;">
            <span id="cap-page-info"></span>
            <div style="display: flex; gap: 8px;">
                <button class="button aura-btn-secondary" id="cap-prev-page" disabled><?php esc_html_e('← Anterior', 'aura-suite'); ?></button>
                <button class="button aura-btn-secondary" id="cap-next-page" disabled><?php esc_html_e('Siguiente →', 'aura-suite'); ?></button>
            </div>
        </div>

        <!-- Desglose por Categoría -->
        <div class="aura-glass-card" style="padding: 20px 24px; margin-top: 24px;">
            <h3 style="margin: 0 0 16px; font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-tag" style="color: #7c3aed;"></span>
                <?php esc_html_e('Desglose por Categoría', 'aura-suite'); ?>
            </h3>
            <div id="aura-capital-by-cat">
                <span style="color: #94a3b8;"><?php esc_html_e('Cargando...', 'aura-suite'); ?></span>
            </div>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

