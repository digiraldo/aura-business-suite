<?php
/**
 * Template: Reportes de Biblioteca — Fase 6
 * 4 pestañas con tablas responsivas y botones de exportación CSV/PDF.
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce = wp_create_nonce( 'aura_library_nonce' );
?>
<div class="aura-app-wrapper aura-library-reports" id="aura-lib-reports" data-nonce="<?php echo esc_attr( $nonce ); ?>">
    <div class="wrap aura-app-context">

        <?php
        Aura_UI::render_page_header( [
            'title'    => __( 'Reportes de Biblioteca', 'aura-suite' ),
            'subtitle' => __( 'Análisis de circulación, clasificación Dewey, morosidad e inventario inactivo con exportación CSV y PDF.', 'aura-suite' ),
            'icon'     => 'dashicons-chart-bar',
            'badges'   => [
                [ 'label' => __( '4 Informes Analíticos', 'aura-suite' ), 'variant' => 'indigo' ],
            ],
            'actions'  => [
                [
                    'label' => __( 'Volver al Dashboard', 'aura-suite' ),
                    'url'   => admin_url( 'admin.php?page=aura-library' ),
                    'class' => 'btn btn-glass btn-sm btn-lift',
                    'icon'  => 'dashicons-dashboard',
                ],
            ],
        ] );
        ?>

        <!-- ── Pestañas ──────────────────────────────────────────── -->
        <nav class="aura-glass-card aura-lib-tabs" id="aura-lib-report-tabs" aria-label="<?php esc_attr_e( 'Secciones de reportes', 'aura-business-suite' ); ?>">
            <a href="#tab-activity" class="nav-tab nav-tab-active" data-tab="activity">
                <span class="dashicons dashicons-chart-line"></span>
                <span><?php esc_html_e( 'Actividad General', 'aura-business-suite' ); ?></span>
            </a>
            <a href="#tab-dewey" class="nav-tab" data-tab="dewey">
                <span class="dashicons dashicons-category"></span>
                <span><?php esc_html_e( 'Clasificación Dewey', 'aura-business-suite' ); ?></span>
            </a>
            <a href="#tab-overdue" class="nav-tab" data-tab="overdue">
                <span class="dashicons dashicons-warning"></span>
                <span><?php esc_html_e( 'Morosidad', 'aura-business-suite' ); ?></span>
            </a>
            <a href="#tab-inventory" class="nav-tab" data-tab="inventory">
                <span class="dashicons dashicons-archive"></span>
                <span><?php esc_html_e( 'Inventario', 'aura-business-suite' ); ?></span>
            </a>
        </nav>

        <!-- ── Tab 1: Actividad General ──────────────────────────── -->
        <div id="tab-activity" class="aura-glass-card aura-lib-tab-panel">

            <div class="aura-lib-report-toolbar">
                <div class="aura-lib-toolbar-field">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">📅</span>
                        <select id="activity-period" class="form-control" aria-label="<?php esc_attr_e( 'Período', 'aura-business-suite' ); ?>">
                            <option value="week"><?php esc_html_e( 'Última semana', 'aura-business-suite' ); ?></option>
                            <option value="month" selected><?php esc_html_e( 'Último mes', 'aura-business-suite' ); ?></option>
                            <option value="year"><?php esc_html_e( 'Último año', 'aura-business-suite' ); ?></option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn btn-shimmer btn-indigo btn-lift" id="activity-load">
                    <span class="dashicons dashicons-update"></span>
                    <span><?php esc_html_e( 'Cargar', 'aura-business-suite' ); ?></span>
                </button>
                <span class="spinner" id="activity-spinner"></span>
                <div class="aura-lib-export-btns">
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="activity" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <span>CSV</span>
                    </button>
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="activity" data-format="pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <span>PDF</span>
                    </button>
                </div>
            </div>

            <div id="activity-summary" class="aura-lib-summary-cards" style="display:none;">
                <div class="kpi-card kpi-indigo card-lift aura-lib-summary-card">
                    <span id="activity-total" class="kpi-value aura-lib-summary-val">—</span>
                    <span class="kpi-label aura-lib-summary-lbl"><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></span>
                </div>
                <div class="kpi-card kpi-emerald card-lift aura-lib-summary-card">
                    <span id="activity-on-time" class="kpi-value aura-lib-summary-val aura-lib-val-emerald">—</span>
                    <span class="kpi-label aura-lib-summary-lbl"><?php esc_html_e( 'A tiempo', 'aura-business-suite' ); ?></span>
                </div>
                <div class="kpi-card kpi-red card-lift aura-lib-summary-card">
                    <span id="activity-late" class="kpi-value aura-lib-summary-val aura-lib-val-red">—</span>
                    <span class="kpi-label aura-lib-summary-lbl"><?php esc_html_e( 'Con retraso', 'aura-business-suite' ); ?></span>
                </div>
            </div>

            <div class="aura-lib-two-cols" style="display:none;" id="activity-tables">
                <div>
                    <h3 class="aura-lib-section-subtitle"><?php esc_html_e( 'Top 10 libros más prestados', 'aura-business-suite' ); ?></h3>
                    <div class="aura-dt-wrapper aura-table-responsive-wrap">
                        <table class="aura-table wp-list-table widefat fixed aura-lib-report-table" id="activity-top-books">
                            <thead><tr>
                                <th style="width:48px;">#</th>
                                <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                                <th><?php esc_html_e( 'Autor', 'aura-business-suite' ); ?></th>
                                <th style="width:100px;"><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                            </tr></thead>
                            <tbody id="activity-top-books-body"></tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 class="aura-lib-section-subtitle"><?php esc_html_e( 'Top 10 lectores más activos', 'aura-business-suite' ); ?></h3>
                    <div class="aura-dt-wrapper aura-table-responsive-wrap">
                        <table class="aura-table wp-list-table widefat fixed aura-lib-report-table" id="activity-top-readers">
                            <thead><tr>
                                <th style="width:48px;">#</th>
                                <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></th>
                                <th style="width:100px;"><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                            </tr></thead>
                            <tbody id="activity-top-readers-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- #tab-activity -->

        <!-- ── Tab 2: Clasificación Dewey ────────────────────────── -->
        <div id="tab-dewey" class="aura-glass-card aura-lib-tab-panel" style="display:none;">

            <div class="aura-lib-report-toolbar">
                <button type="button" class="btn btn-shimmer btn-indigo btn-lift" id="dewey-load">
                    <span class="dashicons dashicons-update"></span>
                    <span><?php esc_html_e( 'Cargar reporte', 'aura-business-suite' ); ?></span>
                </button>
                <span class="spinner" id="dewey-spinner"></span>
                <div class="aura-lib-export-btns">
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="dewey" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <span>CSV</span>
                    </button>
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="dewey" data-format="pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <span>PDF</span>
                    </button>
                </div>
            </div>

            <div class="aura-dt-wrapper aura-table-responsive-wrap">
                <table class="aura-table wp-list-table widefat fixed aura-lib-report-table" id="dewey-table" style="display:none;">
                    <thead><tr>
                        <th><?php esc_html_e( 'Dewey', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Autor', 'aura-business-suite' ); ?></th>
                        <th style="width:110px;"><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="dewey-body"></tbody>
                </table>
            </div>

        </div><!-- #tab-dewey -->

        <!-- ── Tab 3: Morosidad ───────────────────────────────────── -->
        <div id="tab-overdue" class="aura-glass-card aura-lib-tab-panel" style="display:none;">

            <div class="aura-lib-report-toolbar">
                <button type="button" class="btn btn-shimmer btn-indigo btn-lift" id="overdue-load">
                    <span class="dashicons dashicons-update"></span>
                    <span><?php esc_html_e( 'Cargar reporte', 'aura-business-suite' ); ?></span>
                </button>
                <span class="spinner" id="overdue-spinner"></span>
                <div class="aura-lib-export-btns">
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="overdue" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <span>CSV</span>
                    </button>
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="overdue" data-format="pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <span>PDF</span>
                    </button>
                </div>
            </div>

            <div id="overdue-summary" class="aura-lib-summary-cards" style="display:none;">
                <div class="kpi-card kpi-red card-lift aura-lib-summary-card">
                    <span id="overdue-count" class="kpi-value aura-lib-summary-val aura-lib-val-red">—</span>
                    <span class="kpi-label aura-lib-summary-lbl"><?php esc_html_e( 'Vencidos activos', 'aura-business-suite' ); ?></span>
                </div>
                <div class="kpi-card kpi-emerald card-lift aura-lib-summary-card">
                    <span id="overdue-collected" class="kpi-value aura-lib-summary-val aura-lib-val-emerald">—</span>
                    <span class="kpi-label aura-lib-summary-lbl"><?php esc_html_e( 'Total multas cobradas', 'aura-business-suite' ); ?></span>
                </div>
            </div>

            <h3 class="aura-lib-section-subtitle"><?php esc_html_e( 'Préstamos vencidos actuales', 'aura-business-suite' ); ?></h3>
            <div class="aura-dt-wrapper aura-table-responsive-wrap">
                <table class="aura-table wp-list-table widefat fixed aura-lib-report-table" id="overdue-current-table" style="display:none;">
                    <thead><tr>
                        <th style="width:48px;">#</th>
                        <th><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Fec. Venc.', 'aura-business-suite' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Días', 'aura-business-suite' ); ?></th>
                        <th style="width:110px;"><?php esc_html_e( 'Multa', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="overdue-current-body"></tbody>
                </table>
            </div>

            <h3 class="aura-lib-section-subtitle" style="margin-top:24px;"><?php esc_html_e( 'Historial de multas cobradas (últimas 50)', 'aura-business-suite' ); ?></h3>
            <div class="aura-dt-wrapper aura-table-responsive-wrap">
                <table class="aura-table wp-list-table widefat fixed aura-lib-report-table" id="overdue-paid-table" style="display:none;">
                    <thead><tr>
                        <th><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></th>
                        <th style="width:110px;"><?php esc_html_e( 'Monto', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Fecha Devolución', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="overdue-paid-body"></tbody>
                </table>
            </div>

        </div><!-- #tab-overdue -->

        <!-- ── Tab 4: Inventario ──────────────────────────────────── -->
        <div id="tab-inventory" class="aura-glass-card aura-lib-tab-panel" style="display:none;">

            <div class="aura-lib-report-toolbar">
                <div class="aura-lib-toolbar-field aura-lib-toolbar-field--wide">
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">⏱️</span>
                        <select id="inventory-months" class="form-control" aria-label="<?php esc_attr_e( 'Meses sin préstamos', 'aura-business-suite' ); ?>">
                            <option value="3"><?php esc_html_e( 'Inactivo 3 meses', 'aura-business-suite' ); ?></option>
                            <option value="6" selected><?php esc_html_e( 'Inactivo 6 meses', 'aura-business-suite' ); ?></option>
                            <option value="12"><?php esc_html_e( 'Inactivo 12 meses', 'aura-business-suite' ); ?></option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn btn-shimmer btn-indigo btn-lift" id="inventory-load">
                    <span class="dashicons dashicons-update"></span>
                    <span><?php esc_html_e( 'Cargar', 'aura-business-suite' ); ?></span>
                </button>
                <span class="spinner" id="inventory-spinner"></span>
                <div class="aura-lib-export-btns">
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="inventory" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <span>CSV</span>
                    </button>
                    <button type="button" class="btn btn-glass btn-sm btn-lift aura-lib-export" data-type="inventory" data-format="pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <span>PDF</span>
                    </button>
                </div>
            </div>

            <div class="aura-lib-two-cols" id="inventory-tables" style="display:none;">
                <div>
                    <h3 class="aura-lib-section-subtitle"><?php esc_html_e( 'Libros por estado', 'aura-business-suite' ); ?></h3>
                    <div class="aura-dt-wrapper aura-table-responsive-wrap">
                        <table class="aura-table wp-list-table widefat fixed aura-lib-report-table">
                            <thead><tr>
                                <th><?php esc_html_e( 'Estado', 'aura-business-suite' ); ?></th>
                                <th style="width:90px;"><?php esc_html_e( 'Títulos', 'aura-business-suite' ); ?></th>
                                <th style="width:100px;"><?php esc_html_e( 'Ejemplares', 'aura-business-suite' ); ?></th>
                            </tr></thead>
                            <tbody id="inventory-by-status-body"></tbody>
                        </table>
                    </div>

                    <h3 class="aura-lib-section-subtitle" style="margin-top:20px;"><?php esc_html_e( 'Mayor rotación (top 10)', 'aura-business-suite' ); ?></h3>
                    <div class="aura-dt-wrapper aura-table-responsive-wrap">
                        <table class="aura-table wp-list-table widefat fixed aura-lib-report-table">
                            <thead><tr>
                                <th style="width:48px;">#</th>
                                <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                                <th><?php esc_html_e( 'Dewey', 'aura-business-suite' ); ?></th>
                                <th style="width:100px;"><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                            </tr></thead>
                            <tbody id="inventory-top-rotation-body"></tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h3 class="aura-lib-section-subtitle"><?php esc_html_e( 'Stock inactivo', 'aura-business-suite' ); ?></h3>
                    <div class="aura-dt-wrapper aura-table-responsive-wrap">
                        <table class="aura-table wp-list-table widefat fixed aura-lib-report-table">
                            <thead><tr>
                                <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                                <th><?php esc_html_e( 'Autor', 'aura-business-suite' ); ?></th>
                                <th style="width:70px;"><?php esc_html_e( 'Ejs.', 'aura-business-suite' ); ?></th>
                            </tr></thead>
                            <tbody id="inventory-inactive-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- #tab-inventory -->

        <div class="adp-footer">
            <div>
                Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
            </div>
        </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->
