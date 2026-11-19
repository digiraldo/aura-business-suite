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
                    <span class="dashicons dashicons-printer"></span> Imprimir
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
                    <span class="dashicons dashicons-printer"></span> Imprimir
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
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
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
        var $btn   = $(this).prop('disabled', true).text('Cargando...');
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
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Generar' + (report === 'costs' || report === 'lifecycle' ? ' reporte' : ''));
                if (resp.success) {
                    renderReport(report, resp.data);
                } else {
                    showNotice('error', (resp.data && resp.data.message) || 'Error al generar el reporte.');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Generar' + (report === 'costs' || report === 'lifecycle' ? ' reporte' : ''));
                showNotice('error', 'Error de red.');
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
                showNotice('error', (resp.data && resp.data.message) || 'Error al exportar.');
            }
        });
    });

    // ── Exportar Excel (XLSX) ────────────────────────────────
    $(document).on('click', '.js-export-xlsx', function () {
        var report = $(this).data('report');
        var $btn   = $(this).prop('disabled', true).text('Generando...');
        var data   = { action: 'aura_inventory_export_report', nonce: nonce, report: report, format: 'xlsx' };
        $.extend( data, getReportFilters( report ) );
        $.post(ajaxurl, data)
            .done(function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet"></span> Excel');
                if (resp.success) {
                    downloadBase64( resp.data.data, resp.data.filename, resp.data.mime );
                } else {
                    showNotice('error', (resp.data && resp.data.message) || 'Error al exportar Excel.');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet"></span> Excel');
                showNotice('error', 'Error de red.');
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
        return '<button type="button" class="aura-inv-row-toggle aura-inv-report-row-toggle" data-target="' + escHtml(cid) + '" aria-expanded="false" data-tooltip="Desplegar ficha completa del registro"><span class="dashicons dashicons-plus-alt2"></span></button>';
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
        if (ind === 'replace') return '<span class="badge badge-red" data-tooltip="Costo acumulado elevado o fin de vida útil: evaluar reemplazo">🔴 Reemplazo</span>';
        if (ind === 'review')  return '<span class="badge badge-amber" data-tooltip="Revisar frecuencia e inversión de mantenimiento">⚠️ Revisar</span>';
        return '<span class="badge badge-emerald" data-tooltip="Relación costo-beneficio óptima">✅ Ok</span>';
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
                ['Categoría', r.category || '—'],
                ['Adquisición', cur_symbol + ' ' + fmt(r.acquisition_cost)],
                ['Total Mant.', cur_symbol + ' ' + fmt(r.total_maintenance)],
                ['Total Invertido', cur_symbol + ' ' + fmt(r.total_invested)]
            ]);
            tbody.append('<tr class="aura-parent-row">' +
                '<td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<div data-aura-tooltip="' + escHtml(tip) + '"><strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? '<br><code>' + escHtml(r.internal_code) + '</code>' : '') + '</div></div><div class="aura-inv-mobile-sub">' + escHtml(r.category || '—') + ' · Inv: ' + cur_symbol + ' ' + fmt(r.total_invested) + '</div></td>' +
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
                '<div class="aura-child-meta-item"><span>Equipo</span><strong>' + escHtml(r.name) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Categoría</span><strong>' + escHtml(r.category || '—') + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Costo Adquisición</span><strong>' + cur_symbol + ' ' + fmt(r.acquisition_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Total Mantenimiento</span><strong>' + cur_symbol + ' ' + fmt(r.total_maintenance) + ' (' + r.maintenance_pct + '%)</strong></div>' +
                '<div class="aura-child-meta-item"><span>Total Invertido</span><strong>' + cur_symbol + ' ' + fmt(r.total_invested) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Prom. / Mant.</span><strong>' + cur_symbol + ' ' + fmt(r.average_maintenance) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Último / Próximo</span><strong>' + fmtDate(r.last_maintenance) + ' / ' + fmtDate(r.next_maintenance) + '</strong></div>' +
                '</div></div></td></tr>');
        });
        $('#rpt-costs-summary').html(
            kpi(rows.length, 'Equipos', 'kpi-indigo') +
            kpi(cur_symbol + ' ' + fmt(totalMaint), 'Total mantenimientos', 'kpi-amber') +
            kpi(cur_symbol + ' ' + fmt(totalInv), 'Total invertido', 'kpi-emerald') +
            kpi(rows.filter(function(r){return r.indicator==='replace';}).length, 'Candidatos reemplazo', 'kpi-red')
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
        var typeLabels = { preventive:'Preventivo', corrective:'Correctivo', oil_change:'Aceite', cleaning:'Limpieza', inspection:'Inspección', major_repair:'Rep. Mayor' };
        var totalCost = 0;
        $.each(rows, function (i, r) {
            totalCost += parseFloat(r.total_cost || 0);
            var cid = 'rpt-m-ch-' + i;
            var tip = tipCard(r.equipment_name, [
                ['Fecha', fmtDate(r.maintenance_date)],
                ['Tipo', typeLabels[r.type] || r.type],
                ['Total', cur_symbol + ' ' + fmt(r.total_cost)],
                ['Ejecutor', r.performed_by === 'external' ? (r.workshop_name || 'Externo') : 'Interno']
            ]);
            tbody.append('<tr class="aura-parent-row">' +
                '<td class="aura-inv-col-secondary">' + fmtDate(r.maintenance_date) + '</td>' +
                '<td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<div data-aura-tooltip="' + escHtml(tip) + '"><strong>' + escHtml(r.equipment_name) + '</strong>' + (r.internal_code ? '<br><code>'+escHtml(r.internal_code)+'</code>' : '') + '</div></div><div class="aura-inv-mobile-sub">📅 ' + fmtDate(r.maintenance_date) + ' · ' + escHtml(typeLabels[r.type] || r.type) + '</div></td>' +
                '<td class="aura-inv-col-secondary"><span class="badge badge-indigo">' + escHtml(typeLabels[r.type] || r.type) + '</span></td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.description || '—') + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.parts_cost) + '</td>' +
                '<td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.labor_cost) + '</td>' +
                '<td class="num aura-inv-col-stat"><strong>' + cur_symbol + ' ' + fmt(r.total_cost) + '</strong></td>' +
                '<td class="aura-inv-col-secondary">' + (r.performed_by === 'external' ? ('🔧 ' + escHtml(r.workshop_name || 'Externo')) : '👷 Interno') + '</td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.invoice_number || '—') + '</td>' +
                '<td class="aura-inv-col-secondary">' + escHtml(r.post_status || '—') + '</td></tr>' +
                '<tr class="aura-child-row" id="' + cid + '"><td colspan="10"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                '<div class="aura-child-meta-item"><span>Fecha</span><strong>' + fmtDate(r.maintenance_date) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Tipo</span><strong>' + escHtml(typeLabels[r.type] || r.type) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Repuestos / Mano de Obra</span><strong>' + cur_symbol + ' ' + fmt(r.parts_cost) + ' + ' + cur_symbol + ' ' + fmt(r.labor_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Total</span><strong>' + cur_symbol + ' ' + fmt(r.total_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Ejecutor / Factura</span><strong>' + (r.performed_by === 'external' ? escHtml(r.workshop_name || 'Externo') : 'Interno') + (r.invoice_number ? ' · ' + escHtml(r.invoice_number) : '') + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Descripción</span><strong>' + escHtml(r.description || '—') + '</strong></div>' +
                '</div></div></td></tr>');
        });
        $('#rpt-maint-summary').html(
            kpi(rows.length, 'Registros', 'kpi-indigo') +
            kpi(cur_symbol + ' ' + fmt(totalCost), 'Costo total', 'kpi-emerald')
        ).show();
        $('#rpt-maint-table').show(); $('#rpt-maint-empty').hide();
        $('.js-export-report[data-report="maintenance"], .js-export-xlsx[data-report="maintenance"], .js-print-report[data-report="maintenance"]').show();
    }

    function renderEfficiency(data) {
        var t    = data.totals;
        var goal = data.goal_met;
        var html = '<div class="aura-inv-report-kpis">' +
            kpi(t.count, 'Total mantenimientos', 'kpi-indigo') +
            kpi(t.prev_pct + '%', 'Preventivos', goal ? 'kpi-emerald' : 'kpi-red') +
            kpi(t.corr_pct + '%', 'Correctivos', 'kpi-amber') +
            kpi(cur_symbol + ' ' + fmt(t.total_cost), 'Costo total', 'kpi-blue') +
            kpi(data.overdue_count, 'Vencidos sin registrar', data.overdue_count > 0 ? 'kpi-red' : 'kpi-emerald') +
        '</div>';

        html += '<div class="alert-card ' + (goal ? 'alert-success' : 'alert-warning') + ' aura-inv-goal-banner">' +
            '<strong>' + (goal ? '✅' : '❌') + ' Meta preventivos ≥70%:</strong> ' +
            (goal ? 'Cumplida (' + t.prev_pct + '%)' : 'No cumplida (' + t.prev_pct + '% — meta: 70%)') + '</div>';

        if (data.top_corrective && data.top_corrective.length) {
            html += '<div class="aura-rpt-eff-section"><h3>🔴 Top 5 equipos con más mantenimientos correctivos</h3>';
            html += '<div class="aura-dt-wrapper aura-table-responsive-wrap"><table class="aura-table display aura-inv-responsive-table" style="width:100%;"><thead><tr><th>Equipo</th><th class="num">Cant.</th><th class="num">Costo</th></tr></thead><tbody>';
            $.each(data.top_corrective, function (i, r) {
                var cid = 'rpt-eff-top-' + i;
                html += '<tr class="aura-parent-row"><td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? ' <code>'+escHtml(r.internal_code)+'</code>' : '') + '</div></td><td class="num aura-inv-col-stat">' + r.qty + '</td><td class="num aura-inv-col-secondary">' + cur_symbol + ' ' + fmt(r.cost) + '</td></tr>' +
                    '<tr class="aura-child-row" id="' + cid + '"><td colspan="3"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                    '<div class="aura-child-meta-item"><span>Equipo</span><strong>' + escHtml(r.name) + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>Correctivos</span><strong>' + r.qty + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>Costo</span><strong>' + cur_symbol + ' ' + fmt(r.cost) + '</strong></div>' +
                    '</div></div></td></tr>';
            });
            html += '</tbody></table></div></div>';
        }

        if (data.pending_list && data.pending_list.length) {
            html += '<div class="aura-rpt-eff-section"><h3>⏰ Equipos con mantenimiento vencido sin registrar</h3>';
            html += '<div class="aura-dt-wrapper aura-table-responsive-wrap"><table class="aura-table display aura-inv-responsive-table" style="width:100%;"><thead><tr><th>Equipo</th><th>Categoría</th><th>Fecha programada</th></tr></thead><tbody>';
            $.each(data.pending_list, function (i, r) {
                var cid = 'rpt-eff-pen-' + i;
                html += '<tr class="aura-parent-row"><td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<strong>' + escHtml(r.name) + '</strong></div></td><td class="aura-inv-col-secondary">' + escHtml(r.category || '—') + '</td><td class="aura-inv-col-stat"><span class="badge badge-red">' + fmtDate(r.next_maintenance_date) + '</span></td></tr>' +
                    '<tr class="aura-child-row" id="' + cid + '"><td colspan="3"><div class="aura-inv-child-card"><div class="aura-child-meta-grid">' +
                    '<div class="aura-child-meta-item"><span>Equipo</span><strong>' + escHtml(r.name) + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>Categoría</span><strong>' + escHtml(r.category || '—') + '</strong></div>' +
                    '<div class="aura-child-meta-item"><span>Fecha Programada</span><strong>' + fmtDate(r.next_maintenance_date) + '</strong></div>' +
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
                ['Categoría', r.category || '—'],
                ['Adquisición', fmtDate(r.acquisition_date)],
                ['Antigüedad', r.age || '—'],
                ['Valor Est.', cur_symbol + ' ' + fmt(r.estimated_value)]
            ]);
            tbody.append('<tr class="aura-parent-row">' +
                '<td class="aura-inv-col-main"><div class="aura-id-toggle-wrap">' + toggleBtn(cid) + '<div data-aura-tooltip="' + escHtml(tip) + '"><strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? '<br><code>'+escHtml(r.internal_code)+'</code>' : '') + '</div></div><div class="aura-inv-mobile-sub">' + escHtml(r.age || '') + ' · Est: ' + cur_symbol + ' ' + fmt(r.estimated_value) + '</div></td>' +
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
                '<div class="aura-child-meta-item"><span>Categoría</span><strong>' + escHtml(r.category || '—') + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Adquisición / Antigüedad</span><strong>' + fmtDate(r.acquisition_date) + ' (' + escHtml(r.age) + ')</strong></div>' +
                '<div class="aura-child-meta-item"><span>Costo Original</span><strong>' + cur_symbol + ' ' + fmt(r.original_cost) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Mantenimiento Acumulado</span><strong>' + cur_symbol + ' ' + fmt(r.total_maintenance) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Valor Residual Est.</span><strong>' + cur_symbol + ' ' + fmt(r.estimated_value) + '</strong></div>' +
                '<div class="aura-child-meta-item"><span>Estado</span><strong>' + escHtml(r.status) + '</strong></div>' +
                '</div></div></td></tr>');
        });
        $('#rpt-lc-summary').html(
            kpi(rows.length, 'Equipos analizados', 'kpi-indigo') +
            kpi(replacements, 'Candidatos a reemplazo', 'kpi-red') +
            kpi(reviews, 'Requieren revisión', 'kpi-amber') +
            kpi(rows.length - replacements - reviews, 'En buen estado', 'kpi-emerald')
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
