<?php
/**
 * Template — Reportes del Módulo de Inventario (FASE 7 + Design System)
 *
 * 4 pestañas de reporte + exportación CSV/Excel/PDF + tablas responsivas con Child Rows (+).
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce      = wp_create_nonce( 'aura_inventory_nonce' );
$categories = Aura_Inventory_Reports::get_categories();
?>
<div class="aura-app-wrapper aura-inv-reports-page">
    <div class="wrap aura-app-context">

    <?php
    Aura_UI::render_page_header( [
        'title'       => __( 'Reportes de Inventario', 'aura-suite' ),
        'subtitle'    => __( 'Análisis de costos por equipo, historial técnico, eficiencia de mantenimientos y vida útil', 'aura-suite' ),
        'icon'        => 'dashicons-chart-bar',
        'breadcrumbs' => [
            [ 'label' => __( 'Inventario', 'aura-suite' ), 'url' => admin_url( 'admin.php?page=aura-inventory' ) ],
            [ 'label' => __( 'Reportes', 'aura-suite' ) ],
        ],
        'badges'      => [
            [ 'label' => sprintf( __( '%d categorías', 'aura-suite' ), count( $categories ) ), 'variant' => 'indigo' ],
        ],
    ] );
    ?>

    <div id="js-report-notice" class="notice" style="display:none;"></div>

    <!-- ══════════════════════════════════════════════════════
         BARRA DE NAVEGACIÓN AURA DESIGN SYSTEM (4 REPORTES)
    ══════════════════════════════════════════════════════════ -->
    <nav class="aura-navbar aura-nav aura-glass-card tabs-bar aura-inv-reports-nav fade-up" id="aura-inv-reports-nav" role="tablist">
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab active" data-tab="costs" role="tab"
                data-tooltip="<?php esc_attr_e( 'Comparativa de costo de adquisición vs inversión en mantenimientos', 'aura-suite' ); ?>">
            <span class="dashicons dashicons-money-alt"></span>
            <span><?php esc_html_e( 'Costos por Equipo', 'aura-suite' ); ?></span>
            <span class="aura-inv-nav-pill aura-inv-nav-pill-indigo"><?php esc_html_e( 'ROI', 'aura-suite' ); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab" data-tab="maintenance" role="tab"
                data-tooltip="<?php esc_attr_e( 'Detalle histórico de intervenciones preventivas y correctivas', 'aura-suite' ); ?>">
            <span class="dashicons dashicons-admin-tools"></span>
            <span><?php esc_html_e( 'Historial Mantenimientos', 'aura-suite' ); ?></span>
            <span class="aura-inv-nav-pill"><?php esc_html_e( 'Bitácora', 'aura-suite' ); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab" data-tab="efficiency" role="tab"
                data-tooltip="<?php esc_attr_e( 'Cumplimiento de meta preventiva (≥70%) y equipos críticos', 'aura-suite' ); ?>">
            <span class="dashicons dashicons-performance"></span>
            <span><?php esc_html_e( 'Eficiencia', 'aura-suite' ); ?></span>
            <span class="aura-inv-nav-pill aura-inv-nav-pill-ok"><?php esc_html_e( 'Meta ≥70%', 'aura-suite' ); ?></span>
        </button>
        <button type="button" class="aura-navbar-item aura-tab-btn tab-btn aura-inv-tab" data-tab="lifecycle" role="tab"
                data-tooltip="<?php esc_attr_e( 'Antigüedad, depreciación estimada e indicador de reemplazo', 'aura-suite' ); ?>">
            <span class="dashicons dashicons-update"></span>
            <span><?php esc_html_e( 'Depreciación y Vida Útil', 'aura-suite' ); ?></span>
            <span class="aura-inv-nav-pill"><?php esc_html_e( 'Ciclo', 'aura-suite' ); ?></span>
        </button>
    </nav>

    <!-- ══════════════════════════════════════════════════════
         TAB 1: COSTOS POR EQUIPO
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-glass-card aura-inv-tab-panel active is-active" data-panel="costs">
        <div class="aura-inv-report-toolbar">
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Filtrar equipos por categoría específica', 'aura-suite' ); ?>">
                    <span class="input-group-text" aria-hidden="true">🏷️</span>
                    <select id="rpt-costs-category" class="form-control">
                        <option value=""><?php esc_html_e( 'Todas las categorías', 'aura-suite' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-shimmer btn-indigo btn-lift js-run-report" data-report="costs"
                    data-tooltip="<?php esc_attr_e( 'Calcular análisis de costos por equipo', 'aura-suite' ); ?>">
                <span class="dashicons dashicons-search"></span>
                <span><?php esc_html_e( 'Generar reporte', 'aura-suite' ); ?></span>
            </button>
            <div class="aura-inv-report-actions">
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-report" data-report="costs" style="display:none;"
                        data-tooltip="<?php esc_attr_e( 'Descargar reporte en CSV', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-xlsx" data-report="costs" style="display:none;"
                        data-tooltip="<?php esc_attr_e( 'Descargar libro Excel (.xlsx)', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-media-spreadsheet"></span> Excel
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-print-report" data-report="costs" style="display:none;"
                        data-tooltip="<?php esc_attr_e( 'Imprimir o guardar como PDF', 'aura-suite' ); ?>">
                    <span class="dashicons dashicons-printer"></span> PDF
                </button>
            </div>
        </div>

        <div id="rpt-costs-summary" class="aura-inv-report-kpis" style="display:none;"></div>

        <div id="rpt-costs-container" class="aura-dt-wrapper aura-table-responsive-wrap" style="margin-top:16px;">
            <table class="aura-table display aura-inv-responsive-table" id="rpt-costs-table" style="display:none;width:100%;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Equipo', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Nombre del activo, código interno y botón + para detalle completo', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Categoría de inventario del activo', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Costo adq.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Valor de compra original registrado', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Total mant.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Suma histórica invertida en mantenimientos', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Total invertido', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Suma de costo de adquisición + mantenimientos', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( '% mant.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Porcentaje que representa el mantenimiento frente al costo inicial', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Prom./mant.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Costo promedio por cada intervención registrada', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Último mant.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha del servicio técnico más reciente', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Próximo mant.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha programada para el siguiente mantenimiento', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Indicador', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Semáforo de rentabilidad operativa (Ok, Revisar o Reemplazo)', 'aura-suite' ); ?>">?</span></th>
                    </tr>
                </thead>
                <tbody id="rpt-costs-tbody"></tbody>
            </table>
            <div id="rpt-costs-empty" class="empty-card" style="display:none;">
                <span class="empty-icon">📊</span>
                <p><?php esc_html_e( 'No se encontraron equipos con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB 2: HISTORIAL DE MANTENIMIENTOS
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-glass-card aura-inv-tab-panel" data-panel="maintenance" style="display:none;">
        <div class="aura-inv-report-toolbar">
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Fecha de inicio del reporte', 'aura-suite' ); ?>">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="rpt-maint-from" class="form-control" value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
                </div>
            </div>
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group" data-tooltip="<?php esc_attr_e( 'Fecha final del reporte', 'aura-suite' ); ?>">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="rpt-maint-to" class="form-control" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                </div>
            </div>
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">🔧</span>
                    <select id="rpt-maint-type" class="form-control">
                        <option value=""><?php esc_html_e( 'Todos los tipos', 'aura-suite' ); ?></option>
                        <option value="preventive"><?php esc_html_e( 'Preventivo', 'aura-suite' ); ?></option>
                        <option value="corrective"><?php esc_html_e( 'Correctivo', 'aura-suite' ); ?></option>
                        <option value="oil_change"><?php esc_html_e( 'Cambio de aceite', 'aura-suite' ); ?></option>
                        <option value="cleaning"><?php esc_html_e( 'Limpieza', 'aura-suite' ); ?></option>
                        <option value="inspection"><?php esc_html_e( 'Inspección', 'aura-suite' ); ?></option>
                        <option value="major_repair"><?php esc_html_e( 'Reparación mayor', 'aura-suite' ); ?></option>
                    </select>
                </div>
            </div>
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">👷</span>
                    <select id="rpt-maint-performed" class="form-control">
                        <option value=""><?php esc_html_e( 'Todos', 'aura-suite' ); ?></option>
                        <option value="internal"><?php esc_html_e( 'Interno', 'aura-suite' ); ?></option>
                        <option value="external"><?php esc_html_e( 'Externo', 'aura-suite' ); ?></option>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-shimmer btn-indigo btn-lift js-run-report" data-report="maintenance">
                <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Generar', 'aura-suite' ); ?>
            </button>
            <div class="aura-inv-report-actions">
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-report" data-report="maintenance" style="display:none;">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-xlsx" data-report="maintenance" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> Excel
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-print-report" data-report="maintenance" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Imprimir', 'aura-suite' ); ?>
                </button>
            </div>
        </div>

        <div id="rpt-maint-summary" class="aura-inv-report-kpis" style="display:none;"></div>

        <div class="aura-dt-wrapper aura-table-responsive-wrap" style="margin-top:16px;">
            <table class="aura-table display aura-inv-responsive-table" id="rpt-maint-table" style="display:none;width:100%;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Fecha', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha en que se ejecutó el mantenimiento', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Equipo', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Equipo intervenido', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Tipo', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Tipo de mantenimiento realizado', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Descripción', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Detalle técnico de trabajos ejecutados', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Repuestos', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Costo de materiales y refacciones', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Mano obra', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Costo de servicio técnico', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Total', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Importe total del mantenimiento', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Ejecutor', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Personal interno o taller proveedor externo', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Factura', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Número de factura o comprobante', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Estado operativo posterior al mantenimiento', 'aura-suite' ); ?>">?</span></th>
                    </tr>
                </thead>
                <tbody id="rpt-maint-tbody"></tbody>
            </table>
            <div id="rpt-maint-empty" class="empty-card" style="display:none;">
                <span class="empty-icon">🔧</span>
                <p><?php esc_html_e( 'No se encontraron mantenimientos con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB 3: EFICIENCIA
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-glass-card aura-inv-tab-panel" data-panel="efficiency" style="display:none;">
        <div class="aura-inv-report-toolbar">
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="rpt-eff-from" class="form-control" value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
                </div>
            </div>
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">📅</span>
                    <input type="date" id="rpt-eff-to" class="form-control" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                </div>
            </div>
            <button type="button" class="btn btn-shimmer btn-indigo btn-lift js-run-report" data-report="efficiency">
                <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Generar', 'aura-suite' ); ?>
            </button>
            <div class="aura-inv-report-actions">
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-xlsx" data-report="efficiency" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> Excel
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-print-report" data-report="efficiency" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Imprimir', 'aura-suite' ); ?>
                </button>
            </div>
        </div>
        <div id="rpt-eff-content" style="margin-top:16px;display:none;"></div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB 4: DEPRECIACIÓN Y VIDA ÚTIL
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-glass-card aura-inv-tab-panel" data-panel="lifecycle" style="display:none;">
        <div class="aura-inv-report-toolbar">
            <div class="aura-inv-report-toolbar-field">
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true">🏷️</span>
                    <select id="rpt-lc-category" class="form-control">
                        <option value=""><?php esc_html_e( 'Todas las categorías', 'aura-suite' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-shimmer btn-indigo btn-lift js-run-report" data-report="lifecycle">
                <span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Generar reporte', 'aura-suite' ); ?>
            </button>
            <div class="aura-inv-report-actions">
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-report" data-report="lifecycle" style="display:none;">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-export-xlsx" data-report="lifecycle" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> Excel
                </button>
                <button type="button" class="btn btn-glass btn-sm btn-lift js-print-report" data-report="lifecycle" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> PDF
                </button>
            </div>
        </div>

        <div id="rpt-lc-summary" class="aura-inv-report-kpis" style="display:none;"></div>

        <div class="aura-dt-wrapper aura-table-responsive-wrap" style="margin-top:16px;">
            <table class="aura-table display aura-inv-responsive-table" id="rpt-lc-table" style="display:none;width:100%;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Equipo', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Nombre y código interno del activo', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Categoría', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Categoría del activo', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Adquisición', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Fecha de compra o ingreso al inventario', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Antigüedad', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Tiempo transcurrido desde la adquisición', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Costo original', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Valor de adquisición inicial', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Total mant.', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Gasto acumulado en mantenimientos', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Total invertido', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Inversión total acumulada en el equipo', 'aura-suite' ); ?>">?</span></th>
                        <th class="num"><?php esc_html_e( 'Valor estimado', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Valor residual estimado tras depreciación', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Estado actual en el catálogo', 'aura-suite' ); ?>">?</span></th>
                        <th><?php esc_html_e( 'Indicador', 'aura-suite' ); ?> <span class="aura-help-icon" tabindex="0" data-tooltip="<?php esc_attr_e( 'Recomendación de ciclo de vida', 'aura-suite' ); ?>">?</span></th>
                    </tr>
                </thead>
                <tbody id="rpt-lc-tbody"></tbody>
            </table>
            <div id="rpt-lc-empty" class="empty-card" style="display:none;">
                <span class="empty-icon">📈</span>
                <p><?php esc_html_e( 'No se encontraron equipos con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>
        </div>
    </div>

    <div class="adp-footer">
        <div>
            <?php esc_html_e( 'Desarrollado con ❤️ por', 'aura-suite' ); ?> <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; <?php esc_html_e( 'Versión', 'aura-suite' ); ?> <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->

<script>
/* global jQuery */
(function ($) {
    'use strict';

    var nonce  = <?php echo json_encode( $nonce ); ?>;
    var ajaxurl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var cur_symbol = '<?php echo esc_js( function_exists( 'aura_get_currency_symbol' ) ? aura_get_currency_symbol() : get_option( 'aura_currency_symbol', '$' ) ); ?>';
    var rptI18n = <?php echo wp_json_encode( [
        'loading'           => __( 'Cargando...', 'aura-suite' ),
        'generate'          => __( 'Generar', 'aura-suite' ),
        'generate_report'   => __( 'Generar reporte', 'aura-suite' ),
        'err_report'        => __( 'Error al generar el reporte.', 'aura-suite' ),
        'err_network'       => __( 'Error de red.', 'aura-suite' ),
        'err_export'        => __( 'Error al exportar.', 'aura-suite' ),
        'err_export_xlsx'   => __( 'Error al exportar Excel.', 'aura-suite' ),
        'generating'        => __( 'Generando...', 'aura-suite' ),
        'excel'             => __( 'Excel', 'aura-suite' ),
        'expand_tip'        => __( 'Desplegar ficha completa del registro', 'aura-suite' ),
        'ind_replace_tip'   => __( 'Costo acumulado elevado o fin de vida útil: evaluar reemplazo', 'aura-suite' ),
        'ind_replace'       => __( '🔴 Reemplazo', 'aura-suite' ),
        'ind_review_tip'    => __( 'Revisar frecuencia e inversión de mantenimiento', 'aura-suite' ),
        'ind_review'        => __( '⚠️ Revisar', 'aura-suite' ),
        'ind_ok_tip'        => __( 'Relación costo-beneficio óptima', 'aura-suite' ),
        'ind_ok'            => __( '✅ Ok', 'aura-suite' ),
        'cat'               => __( 'Categoría', 'aura-suite' ),
        'acq'               => __( 'Adquisición', 'aura-suite' ),
        'tot_maint_short'   => __( 'Total Mant.', 'aura-suite' ),
        'tot_inv'           => __( 'Total Invertido', 'aura-suite' ),
        'inv_short'         => __( 'Inv:', 'aura-suite' ),
        'equip'             => __( 'Equipo', 'aura-suite' ),
        'acq_cost'          => __( 'Costo Adquisición', 'aura-suite' ),
        'tot_maint'         => __( 'Total Mantenimiento', 'aura-suite' ),
        'avg_maint'         => __( 'Prom. / Mant.', 'aura-suite' ),
        'last_next'         => __( 'Último / Próximo', 'aura-suite' ),
        'equips'            => __( 'Equipos', 'aura-suite' ),
        'tot_maints_kpi'    => __( 'Total mantenimientos', 'aura-suite' ),
        'tot_inv_kpi'       => __( 'Total invertido', 'aura-suite' ),
        'replace_cand'      => __( 'Candidatos reemplazo', 'aura-suite' ),
        'preventive'        => __( 'Preventivo', 'aura-suite' ),
        'corrective'        => __( 'Correctivo', 'aura-suite' ),
        'oil_change'        => __( 'Aceite', 'aura-suite' ),
        'cleaning'          => __( 'Limpieza', 'aura-suite' ),
        'inspection'        => __( 'Inspección', 'aura-suite' ),
        'major_repair'      => __( 'Rep. Mayor', 'aura-suite' ),
        'date'              => __( 'Fecha', 'aura-suite' ),
        'type'              => __( 'Tipo', 'aura-suite' ),
        'total'             => __( 'Total', 'aura-suite' ),
        'executor'          => __( 'Ejecutor', 'aura-suite' ),
        'external'          => __( 'Externo', 'aura-suite' ),
        'internal'          => __( 'Interno', 'aura-suite' ),
        'parts_labor'       => __( 'Repuestos / Mano de Obra', 'aura-suite' ),
        'exec_inv'          => __( 'Ejecutor / Factura', 'aura-suite' ),
        'desc'              => __( 'Descripción', 'aura-suite' ),
        'records'           => __( 'Registros', 'aura-suite' ),
        'total_cost'        => __( 'Costo total', 'aura-suite' ),
        'preventives'       => __( 'Preventivos', 'aura-suite' ),
        'correctives'       => __( 'Correctivos', 'aura-suite' ),
        'overdue_unreg'     => __( 'Vencidos sin registrar', 'aura-suite' ),
        'goal_lbl'          => __( 'Meta preventivos ≥70%:', 'aura-suite' ),
        'goal_met'          => __( 'Cumplida', 'aura-suite' ),
        'goal_not_met'      => __( 'No cumplida', 'aura-suite' ),
        'goal_target'       => __( 'meta: 70%', 'aura-suite' ),
        'top5_title'        => __( '🔴 Top 5 equipos con más mantenimientos correctivos', 'aura-suite' ),
        'qty_short'         => __( 'Cant.', 'aura-suite' ),
        'cost'              => __( 'Costo', 'aura-suite' ),
        'overdue_title'     => __( '⏰ Equipos con mantenimiento vencido sin registrar', 'aura-suite' ),
        'sched_date'        => __( 'Fecha programada', 'aura-suite' ),
        'sched_date_cap'    => __( 'Fecha Programada', 'aura-suite' ),
        'age'               => __( 'Antigüedad', 'aura-suite' ),
        'est_val_short'     => __( 'Valor Est.', 'aura-suite' ),
        'est_short'         => __( 'Est:', 'aura-suite' ),
        'acq_age'           => __( 'Adquisición / Antigüedad', 'aura-suite' ),
        'orig_cost'         => __( 'Costo Original', 'aura-suite' ),
        'accum_maint'       => __( 'Mantenimiento Acumulado', 'aura-suite' ),
        'res_val_est'       => __( 'Valor Residual Est.', 'aura-suite' ),
        'status'            => __( 'Estado', 'aura-suite' ),
        'eq_analyzed'       => __( 'Equipos analizados', 'aura-suite' ),
        'replace_cands'     => __( 'Candidatos a reemplazo', 'aura-suite' ),
        'need_review'       => __( 'Requieren revisión', 'aura-suite' ),
        'good_cond'         => __( 'En buen estado', 'aura-suite' ),
    ] ); ?>;
    function t(k) { return rptI18n[k] || k; }

    // ── Pestañas ─────────────────────────────────────────────
    $(document).on('click', '.aura-inv-tab', function () {
        $('.aura-inv-tab').removeClass('active');
        $(this).addClass('active');
        $('.aura-inv-tab-panel').hide().removeClass('is-active');
        $('[data-panel="' + $(this).data('tab') + '"]').show().addClass('is-active');
    });

    // ── Toggle Child Rows (+ / -) ────────────────────────────
    $(document).on('click', '.aura-inv-report-row-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var cid  = $btn.data('target');
        var $tr  = $btn.closest('tr.aura-parent-row');
        var $ch  = $('#' + cid);
        if ($ch.hasClass('is-open')) {
            $ch.removeClass('is-open');
            $tr.removeClass('is-expanded');
            $btn.removeClass('is-active').attr('aria-expanded', 'false')
                .html('<span class="dashicons dashicons-plus-alt2"></span>');
        } else {
            $ch.addClass('is-open');
            $tr.addClass('is-expanded');
            $btn.addClass('is-active').attr('aria-expanded', 'true')
                .html('<span class="dashicons dashicons-minus"></span>');
        }
    });

    // ── Generar reporte ──────────────────────────────────────
    $(document).on('click', '.js-run-report', function () {
        var report = $(this).data('report');
        var $btn   = $(this).prop('disabled', true).text(t('loading'));
        hideNotice();

        var data = { action: 'aura_inventory_report_' + (report === 'maintenance' ? 'maintenance_log' : report), nonce: nonce, _t: Date.now() };

        if (report === 'costs')     { data.category  = $('#rpt-costs-category').val(); }
        if (report === 'lifecycle') { data.category  = $('#rpt-lc-category').val(); }
        if (report === 'maintenance') {
            data.date_from   = $('#rpt-maint-from').val();
            data.date_to     = $('#rpt-maint-to').val();
            data.maint_type  = $('#rpt-maint-type').val();
            data.performed_by= $('#rpt-maint-performed').val();
        }
        if (report === 'efficiency') {
            data.date_from = $('#rpt-eff-from').val();
            data.date_to   = $('#rpt-eff-to').val();
        }

        $.post(ajaxurl, data)
            .done(function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + escHtml((report === 'costs' || report === 'lifecycle') ? t('generate_report') : t('generate')));
                if (resp.success) {
                    renderReport(report, resp.data);
                } else {
                    showNotice('error', (resp.data && resp.data.message) || t('err_report'));
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + escHtml((report === 'costs' || report === 'lifecycle') ? t('generate_report') : t('generate')));
                showNotice('error', t('err_network'));
            });
    });

    // ── Exportar CSV ─────────────────────────────────────────
    $(document).on('click', '.js-export-report', function () {
        var report = $(this).data('report');
        var data   = { action: 'aura_inventory_export_report', nonce: nonce, report: report, format: 'csv' };
        $.extend( data, getReportFilters( report ) );
        $.post(ajaxurl, data).done(function (resp) {
            if (resp.success) {
                downloadBase64( resp.data.data, resp.data.filename, resp.data.mime );
            } else {
                showNotice('error', (resp.data && resp.data.message) || t('err_export'));
            }
        });
    });

    // ── Exportar Excel (XLSX) ────────────────────────────────
    $(document).on('click', '.js-export-xlsx', function () {
        var report = $(this).data('report');
        var $btn   = $(this).prop('disabled', true).text(t('generating'));
        var data   = { action: 'aura_inventory_export_report', nonce: nonce, report: report, format: 'xlsx' };
        $.extend( data, getReportFilters( report ) );
        $.post(ajaxurl, data)
            .done(function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet"></span> ' + escHtml(t('excel')));
                if (resp.success) {
                    downloadBase64( resp.data.data, resp.data.filename, resp.data.mime );
                } else {
                    showNotice('error', (resp.data && resp.data.message) || t('err_export_xlsx'));
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet"></span> ' + escHtml(t('excel')));
                showNotice('error', t('err_network'));
            });
    });

    // ── Imprimir / PDF ───────────────────────────────────────
    $(document).on('click', '.js-print-report', function () {
        window.print();
    });

    // ── Helpers ──────────────────────────────────────────────
    function downloadBase64(b64, filename, mime) {
        var bytes = atob(b64);
        var arr   = new Uint8Array(bytes.length);
        for (var i = 0; i < bytes.length; i++) { arr[i] = bytes.charCodeAt(i); }
        var blob = new Blob([arr], { type: mime || 'application/octet-stream' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href = url; a.download = filename; a.click();
        URL.revokeObjectURL(url);
    }

    function getReportFilters(report) {
        var f = {};
        if (report === 'costs')       { f.category     = $('#rpt-costs-category').val(); }
        if (report === 'lifecycle')   { f.category     = $('#rpt-lc-category').val(); }
        if (report === 'maintenance') {
            f.date_from    = $('#rpt-maint-from').val();
            f.date_to      = $('#rpt-maint-to').val();
            f.maint_type   = $('#rpt-maint-type').val();
            f.performed_by = $('#rpt-maint-performed').val();
        }
        if (report === 'efficiency')  {
            f.date_from = $('#rpt-eff-from').val();
            f.date_to   = $('#rpt-eff-to').val();
        }
        return f;
    }

    function fmt(n) { return parseFloat(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fmtDate(d) { if (!d) return '—'; var p = d.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
    function toggleBtn(cid) {
        return '<button type="button" class="aura-inv-row-toggle aura-inv-report-row-toggle" data-target="' + escHtml(cid) + '" aria-expanded="false" data-tooltip="' + escHtml(t('expand_tip')) + '"><span class="dashicons dashicons-plus-alt2"></span></button>';
    }
    function tipCard(title, pairs) {
        var rowsHtml = '';
        $.each(pairs, function (_, p) {
            rowsHtml += '<div class="aura-tip-row"><span>' + escHtml(p[0]) + '</span><strong>' + escHtml(p[1]) + '</strong></div>';
        });
        return '<div class="aura-tip-card">' +
            '<div class="aura-tip-card-header"><div class="aura-tip-info"><div class="aura-tip-title">' + escHtml(title) + '</div></div></div>' +
            '<div class="aura-tip-card-body">' + rowsHtml + '</div></div>';
    }
    function indicator(ind) {
        if (ind === 'replace') return '<span class="badge badge-red" data-tooltip="' + escHtml(t('ind_replace_tip')) + '">' + escHtml(t('ind_replace')) + '</span>';
        if (ind === 'review')  return '<span class="badge badge-amber" data-tooltip="' + escHtml(t('ind_review_tip')) + '">' + escHtml(t('ind_review')) + '</span>';
        return '<span class="badge badge-emerald" data-tooltip="' + escHtml(t('ind_ok_tip')) + '">' + escHtml(t('ind_ok')) + '</span>';
    }

    function renderReport(report, data) {
        if (report === 'costs')       renderCosts(data.rows);
        if (report === 'maintenance') renderMaintenance(data.rows);
        if (report === 'efficiency')  renderEfficiency(data);
        if (report === 'lifecycle')   renderLifecycle(data.rows);
    }

    function renderCosts(rows) {
        var tbody = $('#rpt-costs-tbody').empty();
        if (!rows || !rows.length) {
            $('#rpt-costs-table').hide(); $('#rpt-costs-empty').show();
            $('#rpt-costs-summary').hide();
            $('.js-export-report[data-report="costs"], .js-export-xlsx[data-report="costs"], .js-print-report[data-report="costs"]').hide();
            return;
        }
        var totalInv = 0, totalMaint = 0;
        $.each(rows, function (i, r) {
            totalInv   += r.total_invested;
            totalMaint += r.total_maintenance;
            var cid = 'rpt-cost-ch-' + i;
            var tip = tipCard(r.name, [
                [t('cat'), r.category || '—'],
                [t('acq'), cur_symbol + ' ' + fmt(r.acquisition_cost)],
                [t('tot_maint_short'), cur_symbol + ' ' + fmt(r.total_maintenance)],
                [t('tot_inv'), cur_symbol + ' ' + fmt(r.total_invested)]
            ]);
            tbody.append('<tr class="aura-parent-row">' +
                '<td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<div data-aura-tooltip="' + escHtml(tip) + '"><strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? '<br><code>' + escHtml(r.internal_code) + '</code>' : '') + '</div></div><div class="aura-inv-mobile-sub">' + escHtml(r.category || '—') + ' · ' + escHtml(t('inv_short')) + ' ' + cur_symbol + ' ' + fmt(r.total_invested) + '</div></td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.category || '—') + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.acquisition_cost) + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.total_maintenance) + '</td>' +
                '<td class="num aura-inv-col-secondary"><strong>' + cur_symbol + ' ' + fmt(r.total_invested) + '</strong></td>' +
                '<td class="num aura-inv-col-secondary">' + r.maintenance_pct + '%</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.average_maintenance) + '</td>' +
                '<td class="aura-inv-col-secondary">' + fmtDate(r.last_maintenance) + '</td>' +
                '<td class="aura-inv-col-secondary">' + fmtDate(r.next_maintenance) + '</td>' +
                '<td class="aura-inv-col-stat">' + indicator(r.indicator) + '</td></tr>' +
                '<tr class="aura-child-row" id="' + cid + '"><td colspan="10"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('equip')) + '</span><strong>' + escHtml(r.name) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('cat')) + '</span><strong>' + escHtml(r.category || '—') + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('acq_cost')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.acquisition_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('tot_maint')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.total_maintenance) + ' (' + r.maintenance_pct + '%)</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('tot_inv')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.total_invested) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('avg_maint')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.average_maintenance) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('last_next')) + '</span><strong>' + fmtDate(r.last_maintenance) + ' / ' + fmtDate(r.next_maintenance) + '</strong></div>' +
                '</div></div></td></tr>');
        });
        $('#rpt-costs-summary').html(
            kpi(rows.length, escHtml(t('equips')), 'kpi-indigo') +
            kpi(cur_symbol + ' ' + fmt(totalMaint), escHtml(t('tot_maints_kpi')), 'kpi-amber') +
            kpi(cur_symbol + ' ' + fmt(totalInv), escHtml(t('tot_inv_kpi')), 'kpi-emerald') +
            kpi(rows.filter(function(r){return r.indicator==='replace';}).length, escHtml(t('replace_cand')), 'kpi-red')
        ).show();
        $('#rpt-costs-table').show(); $('#rpt-costs-empty').hide();
        $('.js-export-report[data-report="costs"], .js-export-xlsx[data-report="costs"], .js-print-report[data-report="costs"]').show();
    }

    function renderMaintenance(rows) {
        var tbody = $('#rpt-maint-tbody').empty();
        if (!rows || !rows.length) {
            $('#rpt-maint-table').hide(); $('#rpt-maint-empty').show();
            $('#rpt-maint-summary').hide();
            $('.js-export-report[data-report="maintenance"], .js-export-xlsx[data-report="maintenance"], .js-print-report[data-report="maintenance"]').hide();
            return;
        }
        var typeLabels = { preventive: t('preventive'), corrective: t('corrective'), oil_change: t('oil_change'), cleaning: t('cleaning'), inspection: t('inspection'), major_repair: t('major_repair') };
        var totalCost = 0;
        $.each(rows, function (i, r) {
            totalCost += parseFloat(r.total_cost || 0);
            var cid = 'rpt-m-ch-' + i;
            var tip = tipCard(r.equipment_name, [
                [t('date'), fmtDate(r.maintenance_date)],
                [t('type'), typeLabels[r.type] || r.type],
                [t('total'), cur_symbol + ' ' + fmt(r.total_cost)],
                [t('executor'), r.performed_by === 'external' ? (r.workshop_name || t('external')) : t('internal')]
            ]);
            tbody.append('<tr class="aura-parent-row">' +
                '<td class="aura-inv-col-secondary">' + fmtDate(r.maintenance_date) + '</td>' +
                '<td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<div data-aura-tooltip="' + escHtml(tip) + '"><strong>' + escHtml(r.equipment_name) + '</strong>' + (r.internal_code ? '<br><code>'+escHtml(r.internal_code)+'</code>' : '') + '</div></div><div class="aura-inv-mobile-sub">📅 ' + fmtDate(r.maintenance_date) + ' · ' + escHtml(typeLabels[r.type] || r.type) + '</div></td>' +
                '<td class="aura-inv-col-secondary"><span class="badge badge-indigo">' + escHtml(typeLabels[r.type] || r.type) + '</span></td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.description || '—') + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.parts_cost) + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.labor_cost) + '</td>' +
                '<td class="num aura-inv-col-stat"><strong>' + cur_symbol + ' ' + fmt(r.total_cost) + '</strong></td>' +
                '<td class="aura-inv-col-secondary">' + (r.performed_by === 'external' ? ('🔧 ' + escHtml(r.workshop_name || t('external'))) : ('👷 ' + escHtml(t('internal')))) + '</td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.invoice_number || '—') + '</td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.post_status || '—') + '</td></tr>' +
                '<tr class="aura-child-row" id="' + cid + '"><td colspan="10"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('date')) + '</span><strong>' + fmtDate(r.maintenance_date) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('type')) + '</span><strong>' + escHtml(typeLabels[r.type] || r.type) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('parts_labor')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.parts_cost) + ' + ' + cur_symbol + ' ' + fmt(r.labor_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('total')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.total_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('exec_inv')) + '</span><strong>' + (r.performed_by === 'external' ? escHtml(r.workshop_name || t('external')) : escHtml(t('internal'))) + (r.invoice_number ? ' · ' + escHtml(r.invoice_number) : '') + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('desc')) + '</span><strong>' + escHtml(r.description || '—') + '</strong></div>' +
                '</div></div></td></tr>');
        });
        $('#rpt-maint-summary').html(
            kpi(rows.length, escHtml(t('records')), 'kpi-indigo') +
            kpi(cur_symbol + ' ' + fmt(totalCost), escHtml(t('total_cost')), 'kpi-emerald')
        ).show();
        $('#rpt-maint-table').show(); $('#rpt-maint-empty').hide();
        $('.js-export-report[data-report="maintenance"], .js-export-xlsx[data-report="maintenance"], .js-print-report[data-report="maintenance"]').show();
    }

    function renderEfficiency(data) {
        var t    = data.totals;
        var goal = data.goal_met;
        var html = '<div class="aura-inv-report-kpis">' +
            kpi(t.count, escHtml(rptI18n.tot_maints_kpi), 'kpi-indigo') +
            kpi(t.prev_pct + '%', escHtml(rptI18n.preventives), goal ? 'kpi-emerald' : 'kpi-red') +
            kpi(t.corr_pct + '%', escHtml(rptI18n.correctives), 'kpi-amber') +
            kpi(cur_symbol + ' ' + fmt(t.total_cost), escHtml(rptI18n.total_cost), 'kpi-blue') +
            kpi(data.overdue_count, escHtml(rptI18n.overdue_unreg), data.overdue_count > 0 ? 'kpi-red' : 'kpi-emerald') +
        '</div>';

        html += '<div class="alert-card ' + (goal ? 'alert-success' : 'alert-warning') + ' aura-inv-goal-banner">' +
            '<strong>' + (goal ? '✅' : '❌') + ' ' + escHtml(rptI18n.goal_lbl) + '</strong> ' +
            (goal ? escHtml(rptI18n.goal_met) + ' (' + t.prev_pct + '%)' : escHtml(rptI18n.goal_not_met) + ' (' + t.prev_pct + '% — ' + escHtml(rptI18n.goal_target) + ')') + '</div>';

        if (data.top_corrective && data.top_corrective.length) {
            html += '<div class="aura-rpt-eff-section"><h3>' + escHtml(rptI18n.top5_title) + '</h3>';
            html += '<div class="aura-dt-wrapper aura-table-responsive-wrap"><table class="aura-table display aura-inv-responsive-table" style="width:100%;"><thead><tr><th>' + escHtml(rptI18n.equip) + '</th><th class="num">' + escHtml(rptI18n.qty_short) + '</th><th class="num">' + escHtml(rptI18n.cost) + '</th></tr></thead><tbody>';
            $.each(data.top_corrective, function (i, r) {
                var cid = 'rpt-eff-top-' + i;
                html += '<tr class="aura-parent-row"><td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? ' <code>'+escHtml(r.internal_code)+'</code>' : '') + '</div></td><td class="num aura-inv-col-stat">' + r.qty + '</td><td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.cost) + '</td></tr>' +
                    '<tr class="aura-child-row" id="' + cid + '"><td colspan="3"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                    '<div class="aura-child-meta-item"><span>' + escHtml(rptI18n.equip) + '</span><strong>' + escHtml(r.name) + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>' + escHtml(rptI18n.correctives) + '</span><strong>' + r.qty + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>' + escHtml(rptI18n.cost) + '</span><strong>' + cur_symbol + ' ' + fmt(r.cost) + '</strong></div>' +
                    '</div></div></td></tr>';
            });
            html += '</tbody></table></div></div>';
        }

        if (data.pending_list && data.pending_list.length) {
            html += '<div class="aura-rpt-eff-section"><h3>' + escHtml(rptI18n.overdue_title) + '</h3>';
            html += '<div class="aura-dt-wrapper aura-table-responsive-wrap"><table class="aura-table display aura-inv-responsive-table" style="width:100%;"><thead><tr><th>' + escHtml(rptI18n.equip) + '</th><th>' + escHtml(rptI18n.cat) + '</th><th>' + escHtml(rptI18n.sched_date) + '</th></tr></thead><tbody>';
            $.each(data.pending_list, function (i, r) {
                var cid = 'rpt-eff-pen-' + i;
                html += '<tr class="aura-parent-row"><td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<strong>' + escHtml(r.name) + '</strong></div></td><td class="aura-inv-col-secondary">' + escHtml(r.category || '—') + '</td><td class="aura-inv-col-stat"><span class="badge badge-red">' + fmtDate(r.next_maintenance_date) + '</span></td></tr>' +
                    '<tr class="aura-child-row" id="' + cid + '"><td colspan="3"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                    '<div class="aura-child-meta-item"><span>' + escHtml(rptI18n.equip) + '</span><strong>' + escHtml(r.name) + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>' + escHtml(rptI18n.cat) + '</span><strong>' + escHtml(r.category || '—') + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>' + escHtml(rptI18n.sched_date_cap) + '</span><strong>' + fmtDate(r.next_maintenance_date) + '</strong></div>' +
                    '</div></div></td></tr>';
            });
            html += '</tbody></table></div></div>';
        }

        $('#rpt-eff-content').html(html).show();
        $('.js-export-xlsx[data-report="efficiency"], .js-print-report[data-report="efficiency"]').show();
    }

    function renderLifecycle(rows) {
        var tbody = $('#rpt-lc-tbody').empty();
        if (!rows || !rows.length) {
            $('#rpt-lc-table').hide(); $('#rpt-lc-empty').show();
            $('#rpt-lc-summary').hide();
            $('.js-export-report[data-report="lifecycle"], .js-export-xlsx[data-report="lifecycle"], .js-print-report[data-report="lifecycle"]').hide();
            return;
        }
        var replacements = 0, reviews = 0;
        $.each(rows, function (i, r) {
            if (r.indicator === 'replace') replacements++;
            if (r.indicator === 'review')  reviews++;
            var cid = 'rpt-lc-ch-' + i;
            var tip = tipCard(r.name, [
                [t('cat'), r.category || '—'],
                [t('acq'), fmtDate(r.acquisition_date)],
                [t('age'), r.age || '—'],
                [t('est_val_short'), cur_symbol + ' ' + fmt(r.estimated_value)]
            ]);
            tbody.append('<tr class="aura-parent-row">' +
                '<td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<div data-aura-tooltip="' + escHtml(tip) + '"><strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? '<br><code>'+escHtml(r.internal_code)+'</code>' : '') + '</div></div><div class="aura-inv-mobile-sub">' + escHtml(r.age || '') + ' · ' + escHtml(t('est_short')) + ' ' + cur_symbol + ' ' + fmt(r.estimated_value) + '</div></td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.category || '—') + '</td>' +
                '<td class="aura-inv-col-secondary">' + fmtDate(r.acquisition_date) + '</td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.age) + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.original_cost) + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.total_maintenance) + '</td>' +
                '<td class="num aura-inv-col-secondary"><strong>' + cur_symbol + ' ' + fmt(r.total_invested) + '</strong></td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.estimated_value) + '</td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.status) + '</td>' +
                '<td class="aura-inv-col-stat">' + indicator(r.indicator) + '</td></tr>' +
                '<tr class="aura-child-row" id="' + cid + '"><td colspan="10"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('cat')) + '</span><strong>' + escHtml(r.category || '—') + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('acq_age')) + '</span><strong>' + fmtDate(r.acquisition_date) + ' (' + escHtml(r.age) + ')</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('orig_cost')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.original_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('accum_maint')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.total_maintenance) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('res_val_est')) + '</span><strong>' + cur_symbol + ' ' + fmt(r.estimated_value) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>' + escHtml(t('status')) + '</span><strong>' + escHtml(r.status) + '</strong></div>' +
                '</div></div></td></tr>');
        });
        $('#rpt-lc-summary').html(
            kpi(rows.length, escHtml(t('eq_analyzed')), 'kpi-indigo') +
            kpi(replacements, escHtml(t('replace_cands')), 'kpi-red') +
            kpi(reviews, escHtml(t('need_review')), 'kpi-amber') +
            kpi(rows.length - replacements - reviews, escHtml(t('good_cond')), 'kpi-emerald')
        ).show();
        $('#rpt-lc-table').show(); $('#rpt-lc-empty').hide();
        $('.js-export-report[data-report="lifecycle"], .js-export-xlsx[data-report="lifecycle"], .js-print-report[data-report="lifecycle"]').show();
    }

    function kpi(val, lbl, cls) {
        return '<div class="kpi-card ' + (cls || 'kpi-indigo') + ' card-lift aura-inv-report-kpi-card"><div class="kpi-value">' + val + '</div><div class="kpi-label">' + lbl + '</div></div>';
    }

    function escHtml(str) { return $('<div>').text(String(str || '')).html(); }
    function showNotice(type, msg) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
            AuraUI.showToast(msg, type === 'error' ? 'error' : 'success');
        }
        $('#js-report-notice').removeClass('notice-success notice-error').addClass(type === 'error' ? 'notice-error' : 'notice-success').text(msg).show();
    }
    function hideNotice() { $('#js-report-notice').hide(); }

}(jQuery));
</script>
