<?php
/**
 * Template: Reportes de Biblioteca — Fase 6
 * 4 pestañas con tablas y botones de exportación CSV/PDF.
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce = wp_create_nonce( 'aura_library_nonce' );
?>
<div class="wrap aura-library-reports" id="aura-lib-reports" data-nonce="<?php echo esc_attr( $nonce ); ?>">

    <h1 class="aura-lib-dash-title">
        <span class="dashicons dashicons-chart-bar"></span>
        <?php esc_html_e( 'Reportes de Biblioteca', 'aura-business-suite' ); ?>
    </h1>

    <!-- ── Pestañas ──────────────────────────────────────────── -->
    <nav class="nav-tab-wrapper aura-lib-tabs" id="aura-lib-report-tabs">
        <a href="#tab-activity"  class="nav-tab nav-tab-active" data-tab="activity">
            <?php esc_html_e( 'Actividad General', 'aura-business-suite' ); ?>
        </a>
        <a href="#tab-dewey" class="nav-tab" data-tab="dewey">
            <?php esc_html_e( 'Clasificación Dewey', 'aura-business-suite' ); ?>
        </a>
        <a href="#tab-overdue" class="nav-tab" data-tab="overdue">
            <?php esc_html_e( 'Morosidad', 'aura-business-suite' ); ?>
        </a>
        <a href="#tab-inventory" class="nav-tab" data-tab="inventory">
            <?php esc_html_e( 'Inventario', 'aura-business-suite' ); ?>
        </a>
    </nav>

    <!-- ── Tab 1: Actividad General ──────────────────────────── -->
    <div id="tab-activity" class="aura-lib-tab-panel">

        <div class="aura-lib-report-toolbar">
            <label><?php esc_html_e( 'Período:', 'aura-business-suite' ); ?>
                <select id="activity-period">
                    <option value="week"><?php esc_html_e( 'Última semana', 'aura-business-suite' ); ?></option>
                    <option value="month" selected><?php esc_html_e( 'Último mes', 'aura-business-suite' ); ?></option>
                    <option value="year"><?php esc_html_e( 'Último año', 'aura-business-suite' ); ?></option>
                </select>
            </label>
            <button class="button" id="activity-load"><?php esc_html_e( 'Cargar', 'aura-business-suite' ); ?></button>
            <span class="spinner" id="activity-spinner"></span>
            <div class="aura-lib-export-btns">
                <button class="button button-secondary aura-lib-export" data-type="activity" data-format="csv">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button class="button button-secondary aura-lib-export" data-type="activity" data-format="pdf">
                    <span class="dashicons dashicons-pdf"></span> PDF
                </button>
            </div>
        </div>

        <div id="activity-summary" class="aura-lib-summary-cards" style="display:none;">
            <div class="aura-lib-summary-card">
                <span id="activity-total" class="aura-lib-summary-val">—</span>
                <span class="aura-lib-summary-lbl"><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></span>
            </div>
            <div class="aura-lib-summary-card">
                <span id="activity-on-time" class="aura-lib-summary-val" style="color:#00a32a;">—</span>
                <span class="aura-lib-summary-lbl"><?php esc_html_e( 'A tiempo', 'aura-business-suite' ); ?></span>
            </div>
            <div class="aura-lib-summary-card">
                <span id="activity-late" class="aura-lib-summary-val" style="color:#d63638;">—</span>
                <span class="aura-lib-summary-lbl"><?php esc_html_e( 'Con retraso', 'aura-business-suite' ); ?></span>
            </div>
        </div>

        <div class="aura-lib-two-cols" style="display:none;" id="activity-tables">
            <div>
                <h3><?php esc_html_e( 'Top 10 libros más prestados', 'aura-business-suite' ); ?></h3>
                <table class="wp-list-table widefat fixed striped aura-lib-report-table" id="activity-top-books">
                    <thead><tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Autor', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="activity-top-books-body"></tbody>
                </table>
            </div>
            <div>
                <h3><?php esc_html_e( 'Top 10 lectores más activos', 'aura-business-suite' ); ?></h3>
                <table class="wp-list-table widefat fixed striped aura-lib-report-table" id="activity-top-readers">
                    <thead><tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="activity-top-readers-body"></tbody>
                </table>
            </div>
        </div>

    </div><!-- #tab-activity -->

    <!-- ── Tab 2: Clasificación Dewey ────────────────────────── -->
    <div id="tab-dewey" class="aura-lib-tab-panel" style="display:none;">

        <div class="aura-lib-report-toolbar">
            <button class="button" id="dewey-load"><?php esc_html_e( 'Cargar reporte', 'aura-business-suite' ); ?></button>
            <span class="spinner" id="dewey-spinner"></span>
            <div class="aura-lib-export-btns">
                <button class="button button-secondary aura-lib-export" data-type="dewey" data-format="csv">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button class="button button-secondary aura-lib-export" data-type="dewey" data-format="pdf">
                    <span class="dashicons dashicons-pdf"></span> PDF
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped aura-lib-report-table" id="dewey-table" style="display:none;">
            <thead><tr>
                <th><?php esc_html_e( 'Dewey', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Autor', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
            </tr></thead>
            <tbody id="dewey-body"></tbody>
        </table>

    </div><!-- #tab-dewey -->

    <!-- ── Tab 3: Morosidad ───────────────────────────────────── -->
    <div id="tab-overdue" class="aura-lib-tab-panel" style="display:none;">

        <div class="aura-lib-report-toolbar">
            <button class="button" id="overdue-load"><?php esc_html_e( 'Cargar reporte', 'aura-business-suite' ); ?></button>
            <span class="spinner" id="overdue-spinner"></span>
            <div class="aura-lib-export-btns">
                <button class="button button-secondary aura-lib-export" data-type="overdue" data-format="csv">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button class="button button-secondary aura-lib-export" data-type="overdue" data-format="pdf">
                    <span class="dashicons dashicons-pdf"></span> PDF
                </button>
            </div>
        </div>

        <div id="overdue-summary" class="aura-lib-summary-cards" style="display:none;">
            <div class="aura-lib-summary-card">
                <span id="overdue-count" class="aura-lib-summary-val" style="color:#d63638;">—</span>
                <span class="aura-lib-summary-lbl"><?php esc_html_e( 'Vencidos activos', 'aura-business-suite' ); ?></span>
            </div>
            <div class="aura-lib-summary-card">
                <span id="overdue-collected" class="aura-lib-summary-val" style="color:#00a32a;">—</span>
                <span class="aura-lib-summary-lbl"><?php esc_html_e( 'Total multas cobradas', 'aura-business-suite' ); ?></span>
            </div>
        </div>

        <h3><?php esc_html_e( 'Préstamos vencidos actuales', 'aura-business-suite' ); ?></h3>
        <table class="wp-list-table widefat fixed striped aura-lib-report-table" id="overdue-current-table" style="display:none;">
            <thead><tr>
                <th>#</th>
                <th><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Fec. Venc.', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Días', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Multa', 'aura-business-suite' ); ?></th>
            </tr></thead>
            <tbody id="overdue-current-body"></tbody>
        </table>

        <h3 style="margin-top:24px;"><?php esc_html_e( 'Historial de multas cobradas (últimas 50)', 'aura-business-suite' ); ?></h3>
        <table class="wp-list-table widefat fixed striped aura-lib-report-table" id="overdue-paid-table" style="display:none;">
            <thead><tr>
                <th><?php esc_html_e( 'Libro', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Lector', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Monto', 'aura-business-suite' ); ?></th>
                <th><?php esc_html_e( 'Fecha Devolución', 'aura-business-suite' ); ?></th>
            </tr></thead>
            <tbody id="overdue-paid-body"></tbody>
        </table>

    </div><!-- #tab-overdue -->

    <!-- ── Tab 4: Inventario ──────────────────────────────────── -->
    <div id="tab-inventory" class="aura-lib-tab-panel" style="display:none;">

        <div class="aura-lib-report-toolbar">
            <label><?php esc_html_e( 'Stock inactivo (sin préstamos en últimos N meses):', 'aura-business-suite' ); ?>
                <select id="inventory-months">
                    <option value="3">3</option>
                    <option value="6" selected>6</option>
                    <option value="12">12</option>
                </select>
            </label>
            <button class="button" id="inventory-load"><?php esc_html_e( 'Cargar', 'aura-business-suite' ); ?></button>
            <span class="spinner" id="inventory-spinner"></span>
            <div class="aura-lib-export-btns">
                <button class="button button-secondary aura-lib-export" data-type="inventory" data-format="csv">
                    <span class="dashicons dashicons-download"></span> CSV
                </button>
                <button class="button button-secondary aura-lib-export" data-type="inventory" data-format="pdf">
                    <span class="dashicons dashicons-pdf"></span> PDF
                </button>
            </div>
        </div>

        <div class="aura-lib-two-cols" id="inventory-tables" style="display:none;">
            <div>
                <h3><?php esc_html_e( 'Libros por estado', 'aura-business-suite' ); ?></h3>
                <table class="wp-list-table widefat fixed striped aura-lib-report-table">
                    <thead><tr>
                        <th><?php esc_html_e( 'Estado', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Títulos', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Ejemplares', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="inventory-by-status-body"></tbody>
                </table>

                <h3 style="margin-top:20px;"><?php esc_html_e( 'Mayor rotación (top 10)', 'aura-business-suite' ); ?></h3>
                <table class="wp-list-table widefat fixed striped aura-lib-report-table">
                    <thead><tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Dewey', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Préstamos', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="inventory-top-rotation-body"></tbody>
                </table>
            </div>
            <div>
                <h3><?php esc_html_e( 'Stock inactivo', 'aura-business-suite' ); ?></h3>
                <table class="wp-list-table widefat fixed striped aura-lib-report-table">
                    <thead><tr>
                        <th><?php esc_html_e( 'Título', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Autor', 'aura-business-suite' ); ?></th>
                        <th><?php esc_html_e( 'Ejs.', 'aura-business-suite' ); ?></th>
                    </tr></thead>
                    <tbody id="inventory-inactive-body"></tbody>
                </table>
            </div>
        </div>

    </div><!-- #tab-inventory -->

</div><!-- .aura-library-reports -->
