<?php
/**
 * Template — Reportes del Módulo de Inventario (FASE 7)
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce      = wp_create_nonce( 'aura_inventory_nonce' );
$categories = Aura_Inventory_Reports::get_categories();
?>
<div class="wrap aura-inv-reports-wrap">

    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-bar" style="color:#2271b1;margin-right:6px;"></span>
        <?php _e( 'Reportes de Inventario', 'aura-suite' ); ?>
    </h1>
    <hr class="wp-header-end">

    <div id="js-report-notice" class="aura-inv-notice" style="display:none;"></div>

    <!-- ══════════════════════════════════════════════════════
         PESTAÑAS
    ══════════════════════════════════════════════════════════ -->
    <nav class="aura-inv-tabs" role="tablist" style="margin-top:16px;">
        <button type="button" class="aura-inv-tab active" data-tab="costs">
            <span class="dashicons dashicons-money-alt"></span>
            <?php _e( 'Costos por Equipo', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="maintenance">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e( 'Historial Mantenimientos', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="efficiency">
            <span class="dashicons dashicons-performance"></span>
            <?php _e( 'Eficiencia', 'aura-suite' ); ?>
        </button>
        <button type="button" class="aura-inv-tab" data-tab="lifecycle">
            <span class="dashicons dashicons-update"></span>
            <?php _e( 'Depreciación y Vida Útil', 'aura-suite' ); ?>
        </button>
    </nav>

    <!-- ══════════════════════════════════════════════════════
         TAB 1: COSTOS POR EQUIPO
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel active is-active" data-panel="costs">
        <div class="aura-inv-card">
            <div class="aura-inv-report-filters">
                <select id="rpt-costs-category" class="aura-inv-select-sm">
                    <option value=""><?php _e( 'Todas las categorías', 'aura-suite' ); ?></option>
                    <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-primary js-run-report" data-report="costs">
                    <span class="dashicons dashicons-search"></span> <?php _e( 'Generar reporte', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-report" data-report="costs" style="display:none;">
                    <span class="dashicons dashicons-download"></span> <?php _e( 'Exportar CSV', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-xlsx" data-report="costs" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e( 'Exportar Excel', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button js-print-report" data-report="costs" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> <?php _e( 'Imprimir / PDF', 'aura-suite' ); ?>
                </button>
            </div>

            <div id="rpt-costs-summary" class="aura-inv-kpi-row" style="display:none;"></div>

            <div id="rpt-costs-container" style="overflow-x:auto;margin-top:16px;">
                <table class="wp-list-table widefat fixed striped" id="rpt-costs-table" style="display:none;">
                    <thead>
                        <tr>
                            <th><?php _e( 'Equipo', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Categoría', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Costo adq.', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Total mantenimientos', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Total invertido', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( '% mant.', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Prom./mant.', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Último mant.', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Próximo mant.', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Indicador', 'aura-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rpt-costs-tbody"></tbody>
                </table>
                <p id="rpt-costs-empty" class="aura-inv-empty-msg" style="display:none;"><?php _e( 'No se encontraron equipos con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB 2: HISTORIAL DE MANTENIMIENTOS
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="maintenance" style="display:none;">
        <div class="aura-inv-card">
            <div class="aura-inv-report-filters" style="flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <label style="font-size:13px;"><?php _e( 'Desde', 'aura-suite' ); ?></label>
                    <input type="date" id="rpt-maint-from" class="aura-inv-input-sm" value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <label style="font-size:13px;"><?php _e( 'Hasta', 'aura-suite' ); ?></label>
                    <input type="date" id="rpt-maint-to" class="aura-inv-input-sm" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                </div>
                <select id="rpt-maint-type" class="aura-inv-select-sm">
                    <option value=""><?php _e( 'Todos los tipos', 'aura-suite' ); ?></option>
                    <option value="preventive"><?php _e( 'Preventivo', 'aura-suite' ); ?></option>
                    <option value="corrective"><?php _e( 'Correctivo', 'aura-suite' ); ?></option>
                    <option value="oil_change"><?php _e( 'Cambio de aceite', 'aura-suite' ); ?></option>
                    <option value="cleaning"><?php _e( 'Limpieza', 'aura-suite' ); ?></option>
                    <option value="inspection"><?php _e( 'Inspección', 'aura-suite' ); ?></option>
                    <option value="major_repair"><?php _e( 'Reparación mayor', 'aura-suite' ); ?></option>
                </select>
                <select id="rpt-maint-performed" class="aura-inv-select-sm">
                    <option value=""><?php _e( 'Todos', 'aura-suite' ); ?></option>
                    <option value="internal"><?php _e( 'Interno', 'aura-suite' ); ?></option>
                    <option value="external"><?php _e( 'Externo', 'aura-suite' ); ?></option>
                </select>
                <button type="button" class="button button-primary js-run-report" data-report="maintenance">
                    <span class="dashicons dashicons-search"></span> <?php _e( 'Generar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-report" data-report="maintenance" style="display:none;">
                    <span class="dashicons dashicons-download"></span> <?php _e( 'CSV', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-xlsx" data-report="maintenance" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e( 'Excel', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button js-print-report" data-report="maintenance" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> <?php _e( 'Imprimir', 'aura-suite' ); ?>
                </button>
            </div>

            <div id="rpt-maint-summary" class="aura-inv-kpi-row" style="display:none;margin-top:12px;"></div>

            <div style="overflow-x:auto;margin-top:16px;">
                <table class="wp-list-table widefat fixed striped" id="rpt-maint-table" style="display:none;">
                    <thead>
                        <tr>
                            <th><?php _e( 'Fecha', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Equipo', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Tipo', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Descripción', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Repuestos', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Mano obra', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Total', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Ejecutor', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Factura', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Estado', 'aura-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rpt-maint-tbody"></tbody>
                </table>
                <p id="rpt-maint-empty" class="aura-inv-empty-msg" style="display:none;"><?php _e( 'No se encontraron mantenimientos con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB 3: EFICIENCIA
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="efficiency" style="display:none;">
        <div class="aura-inv-card">
            <div class="aura-inv-report-filters" style="flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <label style="font-size:13px;"><?php _e( 'Desde', 'aura-suite' ); ?></label>
                    <input type="date" id="rpt-eff-from" class="aura-inv-input-sm" value="<?php echo esc_attr( date( 'Y-01-01' ) ); ?>">
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <label style="font-size:13px;"><?php _e( 'Hasta', 'aura-suite' ); ?></label>
                    <input type="date" id="rpt-eff-to" class="aura-inv-input-sm" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                </div>
                <button type="button" class="button button-primary js-run-report" data-report="efficiency">
                    <span class="dashicons dashicons-search"></span> <?php _e( 'Generar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-xlsx" data-report="efficiency" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e( 'Exportar Excel', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button js-print-report" data-report="efficiency" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> <?php _e( 'Imprimir', 'aura-suite' ); ?>
                </button>
            </div>
            <div id="rpt-eff-content" style="margin-top:16px;display:none;"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         TAB 4: DEPRECIACIÓN Y VIDA ÚTIL
    ══════════════════════════════════════════════════════════ -->
    <div class="aura-inv-tab-panel" data-panel="lifecycle" style="display:none;">
        <div class="aura-inv-card">
            <div class="aura-inv-report-filters">
                <select id="rpt-lc-category" class="aura-inv-select-sm">
                    <option value=""><?php _e( 'Todas las categorías', 'aura-suite' ); ?></option>
                    <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-primary js-run-report" data-report="lifecycle">
                    <span class="dashicons dashicons-search"></span> <?php _e( 'Generar reporte', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-report" data-report="lifecycle" style="display:none;">
                    <span class="dashicons dashicons-download"></span> <?php _e( 'Exportar CSV', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary js-export-xlsx" data-report="lifecycle" style="display:none;">
                    <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e( 'Exportar Excel', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button js-print-report" data-report="lifecycle" style="display:none;">
                    <span class="dashicons dashicons-printer"></span> <?php _e( 'Imprimir / PDF', 'aura-suite' ); ?>
                </button>
            </div>

            <div id="rpt-lc-summary" class="aura-inv-kpi-row" style="display:none;"></div>

            <div style="overflow-x:auto;margin-top:16px;">
                <table class="wp-list-table widefat fixed striped" id="rpt-lc-table" style="display:none;">
                    <thead>
                        <tr>
                            <th><?php _e( 'Equipo', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Categoría', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Adquisición', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Antigüedad', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Costo original', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Total mantenimientos', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Total invertido', 'aura-suite' ); ?></th>
                            <th class="num"><?php _e( 'Valor estimado', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Estado', 'aura-suite' ); ?></th>
                            <th><?php _e( 'Indicador', 'aura-suite' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rpt-lc-tbody"></tbody>
                </table>
                <p id="rpt-lc-empty" class="aura-inv-empty-msg" style="display:none;"><?php _e( 'No se encontraron equipos con los filtros aplicados.', 'aura-suite' ); ?></p>
            </div>
        </div>
    </div>

</div><!-- .wrap -->

<style>
.aura-inv-report-filters {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    background:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px; padding:12px 16px;
}
.aura-inv-select-sm { height:32px; font-size:13px; }
.aura-inv-input-sm  { height:32px; font-size:13px; padding:0 8px; border:1px solid #8c8f94; border-radius:3px; }
.aura-inv-kpi-row   {
    display:flex; flex-wrap:wrap; gap:12px; margin-top:12px;
}
.aura-inv-kpi-card {
    background:#fff; border:1px solid #ddd; border-radius:6px; padding:12px 18px;
    min-width:140px; text-align:center;
}
.aura-inv-kpi-card .kpi-val { font-size:22px; font-weight:700; color:#2271b1; }
.aura-inv-kpi-card .kpi-lbl { font-size:12px; color:#666; }
.aura-inv-reports-wrap .num { text-align:right; }
table.wp-list-table th.num { text-align:right; }
.badge-ok      { background:#eafaf1; color:#1a7a3e; border:1px solid #27ae60; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; white-space:nowrap; }
.badge-review  { background:#fff7e6; color:#a05800; border:1px solid #f0a500; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; white-space:nowrap; }
.badge-replace { background:#fdf0ee; color:#9c2719; border:1px solid #e74c3c; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; white-space:nowrap; }
.aura-rpt-eff-section { margin-bottom:24px; }
.aura-rpt-eff-section h3 { margin:0 0 10px; font-size:14px; }
.aura-inv-reports-wrap .aura-inv-empty-msg { text-align:center; padding:32px; color:#888; font-style:italic; }

/* ── Impresión / PDF ────────────────────────────────────── */
@media print {
    /* Ocultar chrome de WordPress y controles de UI */
    #adminmenuwrap, #adminmenuback, #wpadminbar, #screen-meta,
    #screen-meta-links, .notice, .update-nag,
    .aura-inv-tabs, .aura-inv-report-filters,
    .button, #js-report-notice { display: none !important; }

    /* Mostrar sólo el panel activo */
    .aura-inv-tab-panel              { display: none !important; }
    .aura-inv-tab-panel.is-active    { display: block !important; }

    /* Ajustes de página */
    #wpcontent, #wpbody, #wpbody-content { margin: 0 !important; padding: 0 !important; float: none !important; }
    .wrap.aura-inv-reports-wrap { padding: 0; }

    /* Tablas más compactas */
    table.wp-list-table { font-size: 10px; border-collapse: collapse; }
    table.wp-list-table th, table.wp-list-table td { padding: 4px 6px; border: 1px solid #ccc; }

    /* KPIs en una fila */
    .aura-inv-kpi-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .aura-inv-kpi-card { border: 1px solid #999; min-width: 100px; padding: 8px 12px; break-inside: avoid; }
}
</style>

<script>
/* global jQuery */
(function ($) {
    'use strict';

    var nonce  = <?php echo json_encode( $nonce ); ?>;
    var ajaxurl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var cur_symbol = '<?php echo esc_js( get_option( 'aura_currency_symbol', '$' ) ); ?>';

    // ── Pestañas ─────────────────────────────────────────────
    $(document).on('click', '.aura-inv-tab', function () {
        $('.aura-inv-tab').removeClass('active');
        $(this).addClass('active');
        $('.aura-inv-tab-panel').hide().removeClass('is-active');
        $('[data-panel="' + $(this).data('tab') + '"]').show().addClass('is-active');
    });

    // ── Generar reporte ──────────────────────────────────────
    $(document).on('click', '.js-run-report', function () {
        var report = $(this).data('report');
        var $btn   = $(this).prop('disabled', true).text('Cargando...');
        hideNotice();

        var data = { action: 'aura_inventory_report_' + (report === 'maintenance' ? 'maintenance_log' : report), nonce: nonce };

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
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet"></span> Exportar Excel');
                if (resp.success) {
                    downloadBase64( resp.data.data, resp.data.filename, resp.data.mime );
                } else {
                    showNotice('error', (resp.data && resp.data.message) || 'Error al exportar Excel.');
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-media-spreadsheet"></span> Exportar Excel');
                showNotice('error', 'Error de red.');
            });
    });

    // ── Imprimir / PDF ───────────────────────────────────────
    $(document).on('click', '.js-print-report', function () {
        window.print();
    });

    // ── Helpers de descarga y filtros ────────────────────────
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

    // ── Renderizadores ───────────────────────────────────────
    function fmt(n) { return parseFloat(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fmtDate(d) { if (!d) return '—'; var p = d.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
    function indicator(ind) {
        if (ind === 'replace') return '<span class="badge-replace">🔴 Recomplazo</span>';
        if (ind === 'review')  return '<span class="badge-review">⚠️ Revisar</span>';
        return '<span class="badge-ok">✅ Ok</span>';
    }

    function renderReport(report, data) {
        if (report === 'costs')      renderCosts(data.rows);
        if (report === 'maintenance') renderMaintenance(data.rows);
        if (report === 'efficiency') renderEfficiency(data);
        if (report === 'lifecycle')  renderLifecycle(data.rows);
    }

    function renderCosts(rows) {
        var tbody = $('#rpt-costs-tbody').empty();
        if (!rows || !rows.length) {
            $('#rpt-costs-table').hide(); $('#rpt-costs-empty').show();
            $('#rpt-costs-summary').hide();
            $('.js-export-report[data-report="costs"]').hide();
            $('.js-export-xlsx[data-report="costs"]').hide();
            $('.js-print-report[data-report="costs"]').hide();
            return;
        }
        var totalInv = 0, totalMaint = 0;
        $.each(rows, function (i, r) {
            totalInv   += r.total_invested;
            totalMaint += r.total_maintenance;
            tbody.append('<tr>' +
                '<td><strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? '<br><small>' + escHtml(r.internal_code) + '</small>' : '') + '</td>' +
                '<td>' + escHtml(r.category || '—') + '</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.acquisition_cost) + '</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.total_maintenance) + '</td>' +
                '<td class="num"><strong>' + cur_symbol + ' ' + fmt(r.total_invested) + '</strong></td>' +
                '<td class="num">' + r.maintenance_pct + '%</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.average_maintenance) + '</td>' +
                '<td>' + fmtDate(r.last_maintenance) + '</td>' +
                '<td>' + fmtDate(r.next_maintenance) + '</td>' +
                '<td>' + indicator(r.indicator) + '</td></tr>');
        });
        $('#rpt-costs-summary').html(
            kpi(rows.length, 'Equipos') +
            kpi(cur_symbol + ' ' + fmt(totalMaint), 'Total mantenimientos') +
            kpi(cur_symbol + ' ' + fmt(totalInv), 'Total invertido') +
            kpi(rows.filter(function(r){return r.indicator==='replace';}).length, '🔴 Candidatos remplazo')
        ).show();
        $('#rpt-costs-table').show(); $('#rpt-costs-empty').hide();
        $('.js-export-report[data-report="costs"]').show();
        $('.js-export-xlsx[data-report="costs"]').show();
        $('.js-print-report[data-report="costs"]').show();
    }

    function renderMaintenance(rows) {
        var tbody = $('#rpt-maint-tbody').empty();
        if (!rows || !rows.length) {
            $('#rpt-maint-table').hide(); $('#rpt-maint-empty').show();
            $('#rpt-maint-summary').hide();
            $('.js-export-report[data-report="maintenance"]').hide();
            $('.js-export-xlsx[data-report="maintenance"]').hide();
            $('.js-print-report[data-report="maintenance"]').hide();
            return;
        }
        var typeLabels = { preventive:'Preventivo', corrective:'Correctivo', oil_change:'Aceite', cleaning:'Limpieza', inspection:'Inspección', major_repair:'Rep. Mayor' };
        var totalCost = 0;
        $.each(rows, function (i, r) {
            totalCost += parseFloat(r.total_cost || 0);
            tbody.append('<tr>' +
                '<td>' + fmtDate(r.maintenance_date) + '</td>' +
                '<td><strong>' + escHtml(r.equipment_name) + '</strong>' + (r.internal_code ? '<br><small>'+escHtml(r.internal_code)+'</small>' : '') + '</td>' +
                '<td>' + (typeLabels[r.type] || r.type) + '</td>' +
                '<td>' + escHtml(r.description || '—') + '</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.parts_cost) + '</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.labor_cost) + '</td>' +
                '<td class="num"><strong>' + cur_symbol + ' ' + fmt(r.total_cost) + '</strong></td>' +
                '<td>' + (r.performed_by === 'external' ? ('🔧 ' + escHtml(r.workshop_name || 'Externo')) : '👷 Interno') + '</td>' +
                '<td>' + escHtml(r.invoice_number || '—') + '</td>' +
                '<td>' + escHtml(r.post_status || '—') + '</td></tr>');
        });
        $('#rpt-maint-summary').html(
            kpi(rows.length, 'Registros') +
            kpi(cur_symbol + ' ' + fmt(totalCost), 'Costo total')
        ).show();
        $('#rpt-maint-table').show(); $('#rpt-maint-empty').hide();
        $('.js-export-report[data-report="maintenance"]').show();
        $('.js-export-xlsx[data-report="maintenance"]').show();
        $('.js-print-report[data-report="maintenance"]').show();
    }

    function renderEfficiency(data) {
        var t    = data.totals;
        var goal = data.goal_met;
        var html = '<div class="aura-inv-kpi-row">' +
            kpi(t.count, 'Total mantenimientos') +
            kpi(t.prev_pct + '%', 'Preventivos', goal ? '#27ae60' : '#e74c3c') +
            kpi(t.corr_pct + '%', 'Correctivos') +
            kpi(cur_symbol + ' ' + fmt(t.total_cost), 'Costo total') +
            kpi(data.overdue_count, '⚠️ Vencidos sin registrar', data.overdue_count > 0 ? '#e74c3c' : '#27ae60') +
        '</div>';

        // Meta
        html += '<p style="margin:12px 0 4px;"><strong>' + (goal ? '✅' : '❌') + ' Meta preventivos ≥70%:</strong> ' +
            (goal ? 'Cumplida (' + t.prev_pct + '%)' : 'No cumplida (' + t.prev_pct + '% — meta: 70%)') + '</p>';

        // Top correctivos
        if (data.top_corrective && data.top_corrective.length) {
            html += '<div class="aura-rpt-eff-section" style="margin-top:16px;"><h3>🔴 Top 5 equipos con más mantenimientos correctivos</h3>';
            html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Equipo</th><th class="num">Cant.</th><th class="num">Costo</th></tr></thead><tbody>';
            $.each(data.top_corrective, function (i, r) {
                html += '<tr><td>' + escHtml(r.name) + (r.internal_code ? ' <small>('+escHtml(r.internal_code)+')</small>' : '') + '</td><td class="num">' + r.qty + '</td><td class="num">' + cur_symbol + ' ' + fmt(r.cost) + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        // Pendientes
        if (data.pending_list && data.pending_list.length) {
            html += '<div class="aura-rpt-eff-section" style="margin-top:16px;"><h3>⏰ Equipos con mantenimiento vencido sin registrar</h3>';
            html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Equipo</th><th>Categoría</th><th>Fecha programada</th></tr></thead><tbody>';
            $.each(data.pending_list, function (i, r) {
                html += '<tr><td>' + escHtml(r.name) + '</td><td>' + escHtml(r.category || '—') + '</td><td style="color:#e74c3c;">' + fmtDate(r.next_maintenance_date) + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        $('#rpt-eff-content').html(html).show();
        $('.js-export-xlsx[data-report="efficiency"]').show();
        $('.js-print-report[data-report="efficiency"]').show();
    }

    function renderLifecycle(rows) {
        var tbody = $('#rpt-lc-tbody').empty();
        if (!rows || !rows.length) {
            $('#rpt-lc-table').hide(); $('#rpt-lc-empty').show();
            $('#rpt-lc-summary').hide();
            $('.js-export-report[data-report="lifecycle"]').hide();
            $('.js-export-xlsx[data-report="lifecycle"]').hide();
            $('.js-print-report[data-report="lifecycle"]').hide();
            return;
        }
        var replacements = 0, reviews = 0;
        $.each(rows, function (i, r) {
            if (r.indicator === 'replace') replacements++;
            if (r.indicator === 'review')  reviews++;
            tbody.append('<tr>' +
                '<td><strong>' + escHtml(r.name) + '</strong>' + (r.internal_code ? '<br><small>'+escHtml(r.internal_code)+'</small>' : '') + '</td>' +
                '<td>' + escHtml(r.category || '—') + '</td>' +
                '<td>' + fmtDate(r.acquisition_date) + '</td>' +
                '<td>' + escHtml(r.age) + '</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.original_cost) + '</td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.total_maintenance) + '</td>' +
                '<td class="num"><strong>' + cur_symbol + ' ' + fmt(r.total_invested) + '</strong></td>' +
                '<td class="num">' + cur_symbol + ' ' + fmt(r.estimated_value) + '</td>' +
                '<td>' + escHtml(r.status) + '</td>' +
                '<td>' + indicator(r.indicator) + '</td></tr>');
        });
        $('#rpt-lc-summary').html(
            kpi(rows.length, 'Equipos analizados') +
            kpi(replacements, '🔴 Candidatos a reemplazo') +
            kpi(reviews, '⚠️ Requieren revisión') +
            kpi(rows.length - replacements - reviews, '✅ En buen estado')
        ).show();
        $('#rpt-lc-table').show(); $('#rpt-lc-empty').hide();
        $('.js-export-report[data-report="lifecycle"]').show();
        $('.js-export-xlsx[data-report="lifecycle"]').show();
        $('.js-print-report[data-report="lifecycle"]').show();
    }

    function kpi(val, lbl, color) {
        return '<div class="aura-inv-kpi-card"><div class="kpi-val" style="' + (color ? 'color:'+color : '') + '">' + val + '</div><div class="kpi-lbl">' + lbl + '</div></div>';
    }

    function escHtml(str) { return $('<div>').text(String(str || '')).html(); }
    function showNotice(type, msg) { $('#js-report-notice').removeClass('success error').addClass(type).text(msg).show(); }
    function hideNotice() { $('#js-report-notice').hide(); }

}(jQuery));
</script>
