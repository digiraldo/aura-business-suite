<?php
/**
 * Template: Dashboard de Área — Fase 8, Ítem 8.3
 *
 * Acceso:  admin.php?page=aura-areas&view=dashboard[&area_id={id}]
 * Permisos: aura_areas_view_own | aura_areas_view_all | aura_areas_manage | manage_options
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Permisos ──────────────────────────────────────────────────────────────
$_adb_can_manage   = current_user_can( 'manage_options' ) || current_user_can( 'aura_areas_manage' );
$_adb_can_all      = $_adb_can_manage || current_user_can( 'aura_areas_view_all' );
$_adb_can_own      = current_user_can( 'aura_areas_view_own' );
$_adb_can_budget   = $_adb_can_all || current_user_can( 'aura_areas_budget_view' );

global $wpdb;
$_areas_table = $wpdb->prefix . 'aura_areas';

// ── Selector de área ──────────────────────────────────────────────────────
// Si view_all: listado de todas las áreas activas para el selector.
// Si view_own: forzar el área responsable.
$_adb_areas    = [];
$_adb_area_id  = isset( $_GET['area_id'] ) ? absint( $_GET['area_id'] ) : 0;
$_adb_area     = null;

if ( $_adb_can_all ) {
    $_adb_areas = $wpdb->get_results(
        "SELECT id, name, color, icon FROM `{$_areas_table}` WHERE status = 'active' ORDER BY sort_order, name"
    );
    // Si no se pasó area_id, usar la primera
    if ( ! $_adb_area_id && ! empty( $_adb_areas ) ) {
        $_adb_area_id = (int) $_adb_areas[0]->id;
    }
} elseif ( $_adb_can_own ) {
    $_adb_area_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM `{$_areas_table}` WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
        get_current_user_id()
    ) );
    if ( ! $_adb_area_id ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>'
            . esc_html__( 'No estás asignado como responsable de ningún área activa.', 'aura-suite' )
            . '</p></div></div>';
        return;
    }
}

if ( $_adb_area_id ) {
    $_adb_area = $wpdb->get_row( $wpdb->prepare(
        "SELECT a.*, u.display_name AS responsible_name
         FROM `{$_areas_table}` a
         LEFT JOIN `{$wpdb->users}` u ON u.ID = a.responsible_user_id
         WHERE a.id = %d",
        $_adb_area_id
    ) );
}

// Nonce para AJAX
$_adb_nonce = wp_create_nonce( Aura_Areas_Admin::NONCE );
?>

<div class="wrap aura-area-dashboard-wrap">

    <!-- ── Encabezado ─────────────────────────────────────────────── -->
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
        <?php if ( $_adb_can_manage ) : ?>
        <a href="<?php echo admin_url( 'admin.php?page=aura-areas' ); ?>"
           class="button" style="display:flex;align-items:center;gap:5px;">
            <span class="dashicons dashicons-arrow-left-alt" style="margin-top:3px;font-size:16px;"></span>
            <?php esc_html_e( 'Gestión de Áreas', 'aura-suite' ); ?>
        </a>
        <?php endif; ?>

        <h1 class="wp-heading-inline" style="margin:0;flex:1;">
            <?php if ( $_adb_area ) : ?>
            <span class="dashicons <?php echo esc_attr( $_adb_area->icon ?? 'dashicons-building' ); ?>"
                  style="color:<?php echo esc_attr( $_adb_area->color ?? '#2271b1' ); ?>;margin-right:6px;font-size:1.4em;vertical-align:middle;"></span>
            <?php echo esc_html( $_adb_area->name ); ?>
            <?php else : ?>
            <?php esc_html_e( 'Dashboard de Área', 'aura-suite' ); ?>
            <?php endif; ?>
        </h1>

        <!-- Selector de área (solo view_all) -->
        <?php if ( $_adb_can_all && count( $_adb_areas ) > 1 ) : ?>
        <div style="display:flex;align-items:center;gap:6px;">
            <label for="adb-area-select" style="font-weight:600;">
                <?php esc_html_e( 'Área:', 'aura-suite' ); ?>
            </label>
            <select id="adb-area-select" style="min-width:200px;">
                <?php foreach ( $_adb_areas as $_aa ) : ?>
                <option value="<?php echo (int) $_aa->id; ?>"
                        <?php selected( (int) $_aa->id, $_adb_area_id ); ?>>
                    <?php echo esc_html( $_aa->name ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <button type="button" id="adb-refresh-btn" class="button"
                style="display:flex;align-items:center;gap:5px;">
            <span class="dashicons dashicons-update" style="margin-top:3px;font-size:16px;"></span>
            <?php esc_html_e( 'Actualizar', 'aura-suite' ); ?>
        </button>
    </div>

    <?php if ( $_adb_area ) : ?>
    <p style="color:#646970;margin-top:0;margin-bottom:16px;">
        <?php if ( $_adb_area->description ) : ?>
        <?php echo esc_html( $_adb_area->description ); ?> &mdash;
        <?php endif; ?>
        <?php if ( $_adb_area->responsible_name ) :
            printf(
                /* translators: 1 = display name */
                esc_html__( 'Responsable: %s', 'aura-suite' ),
                '<strong>' . esc_html( $_adb_area->responsible_name ) . '</strong>'
            );
        endif; ?>
    </p>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ── Spinner / error ────────────────────────────────────────── -->
    <div id="adb-loading" style="padding:30px;text-align:center;color:#646970;display:none;">
        <span class="spinner" style="float:none;visibility:visible;"></span>
        <?php esc_html_e( 'Cargando datos…', 'aura-suite' ); ?>
    </div>
    <div id="adb-error" class="notice notice-error" style="display:none;">
        <p id="adb-error-msg"></p>
    </div>

    <!-- ── Contenido del dashboard ───────────────────────────────── -->
    <div id="adb-content" style="display:none;">

        <!-- KPIs ─────────────────────────────────────────────────── -->
        <div id="adb-kpis" style="
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(190px,1fr));
            gap:14px;
            margin-bottom:24px;">
        </div>

        <!-- Fila: gráfico + alertas ──────────────────────────────── -->
        <div style="display:grid;grid-template-columns:1fr 340px;gap:18px;margin-bottom:24px;align-items:start;"
             class="adb-chart-row">

            <!-- Gráfico de barras ────────────────────────── -->
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:18px;">
                <h3 style="margin:0 0 14px;font-size:14px;color:#1d2327;">
                    <span class="dashicons dashicons-chart-bar" style="color:#2271b1;margin-right:5px;font-size:16px;vertical-align:middle;"></span>
                    <?php esc_html_e( 'Gasto ejecutado por categoría', 'aura-suite' ); ?>
                </h3>
                <div id="adb-chart">
                    <p style="color:#aaa;font-style:italic;"><?php esc_html_e( 'Sin datos de egresos.', 'aura-suite' ); ?></p>
                </div>
            </div>

            <!-- Alertas de presupuesto ───────────────────── -->
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:18px;">
                <h3 style="margin:0 0 14px;font-size:14px;color:#1d2327;">
                    <span class="dashicons dashicons-warning" style="color:#d63638;margin-right:5px;font-size:16px;vertical-align:middle;"></span>
                    <?php esc_html_e( 'Alertas de presupuesto', 'aura-suite' ); ?>
                </h3>
                <div id="adb-alerts">
                    <p style="color:#aaa;font-style:italic;"><?php esc_html_e( 'Sin alertas activas.', 'aura-suite' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Últimas transacciones ────────────────────────────────── -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <h3 style="margin:0;font-size:14px;color:#1d2327;">
                    <span class="dashicons dashicons-list-view" style="color:#2271b1;margin-right:5px;font-size:16px;vertical-align:middle;"></span>
                    <?php esc_html_e( 'Últimas transacciones', 'aura-suite' ); ?>
                    <span id="adb-tx-count-badge" style="
                        display:inline-block;background:#f0f6fc;border:1px solid #c3d4e9;
                        color:#2271b1;font-size:11px;font-weight:600;padding:1px 7px;
                        border-radius:10px;margin-left:6px;vertical-align:middle;"></span>
                </h3>
                <a id="adb-all-tx-link" href="#" style="font-size:13px;">
                    <?php esc_html_e( 'Ver todas →', 'aura-suite' ); ?>
                </a>
            </div>
            <div id="adb-transactions">
                <p style="color:#aaa;font-style:italic;"><?php esc_html_e( 'Sin transacciones registradas para esta área.', 'aura-suite' ); ?></p>
            </div>
        </div>

    </div><!-- #adb-content -->

</div><!-- .wrap -->

<style>
.aura-area-dashboard-wrap .adb-kpi-card {
    background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px 18px;
    display:flex;align-items:center;gap:14px;
}
.aura-area-dashboard-wrap .adb-kpi-icon {
    width:42px;height:42px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.aura-area-dashboard-wrap .adb-kpi-icon .dashicons { font-size:22px;width:22px;height:22px;color:#fff; }
.aura-area-dashboard-wrap .adb-kpi-label { font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.4px; }
.aura-area-dashboard-wrap .adb-kpi-value { font-size:22px;font-weight:700;color:#1d2327;line-height:1.1; }
.aura-area-dashboard-wrap .adb-kpi-sub   { font-size:11px;color:#8c8f94;margin-top:2px; }

.adb-bar-row { display:flex;align-items:center;gap:10px;margin-bottom:8px; }
.adb-bar-label { width:130px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#1d2327;text-align:right; }
.adb-bar-track { flex:1;background:#f0f0f1;border-radius:3px;height:16px;overflow:hidden; }
.adb-bar-fill  { height:16px;border-radius:3px;transition:width .4s ease; }
.adb-bar-amt   { width:80px;flex-shrink:0;text-align:right;font-size:12px;color:#1d2327;font-weight:600; }

.adb-alert-row { padding:8px 10px;border-radius:4px;margin-bottom:6px;border-left:3px solid; }
.adb-alert-row.exceeded  { background:#fff0f0;border-color:#d63638; }
.adb-alert-row.threshold { background:#fff8e5;border-color:#dba617; }
.adb-alert-row .adb-alert-cat { font-weight:600;font-size:12px; }
.adb-alert-row .adb-alert-pct { font-size:13px;font-weight:700; }

.adb-tx-table { width:100%;border-collapse:collapse; }
.adb-tx-table th { font-size:11px;color:#646970;text-transform:uppercase;letter-spacing:.4px;padding:4px 8px;border-bottom:2px solid #f0f0f1;text-align:left; }
.adb-tx-table td { padding:7px 8px;border-bottom:1px solid #f6f7f7;font-size:13px;vertical-align:middle; }
.adb-tx-table tr:last-child td { border-bottom:none; }
.adb-tx-badge { display:inline-block;padding:2px 7px;border-radius:3px;font-size:11px;color:#fff;white-space:nowrap; }

@media (max-width: 900px) {
    .adb-chart-row { grid-template-columns:1fr !important; }
    .adb-bar-label { width:80px; }
}
</style>

<script type="text/javascript">
(function($) {
    'use strict';

    var ajaxUrl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
    var nonce   = '<?php echo esc_js( $_adb_nonce ); ?>';
    var txListUrl = '<?php echo esc_js( admin_url( "admin.php?page=aura-financial-transactions" ) ); ?>';

    /* ── Formateador de moneda ───────────────────────────────── */
    function fmt(n) {
        n = parseFloat(n) || 0;
        return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function escHtml(s) {
        return $('<div>').text(s || '').html();
    }

    /* ── Cargar datos del dashboard ─────────────────────────── */
    function loadDashboard(areaId) {
        $('#adb-loading').show();
        $('#adb-content, #adb-error').hide();

        $.post(ajaxUrl, {
            action:  'aura_area_dashboard_data',
            nonce:   nonce,
            area_id: areaId
        }, function(resp) {
            $('#adb-loading').hide();
            if (!resp.success) {
                $('#adb-error-msg').text(resp.data ? resp.data.message : 'Error');
                $('#adb-error').show();
                return;
            }
            var d = resp.data;
            renderKPIs(d.kpis, d.area);
            renderChart(d.chart_data);
            renderAlerts(d.kpis);
            renderTransactions(d.recent_tx, d.tx_count, areaId);
            $('#adb-content').show();
        }).fail(function() {
            $('#adb-loading').hide();
            $('#adb-error-msg').text('<?php echo esc_js( __( "Error de comunicación con el servidor.", "aura-suite" ) ); ?>');
            $('#adb-error').show();
        });
    }

    /* ── KPIs ────────────────────────────────────────────────── */
    function renderKPIs(kpis, area) {
        var $c = $('#adb-kpis').empty();
        if (!kpis) {
            $c.append('<p style="color:#aaa;font-style:italic;grid-column:1/-1;"><?php echo esc_js( __( "No tienes permisos para ver los KPIs de presupuesto.", "aura-suite" ) ); ?></p>');
            return;
        }
        var pct = parseFloat(kpis.pct) || 0;
        var pctColor = pct > 100 ? '#d63638' : (pct >= 90 ? '#f97316' : (pct >= 70 ? '#dba617' : '#00a32a'));
        var items = [
            { icon: 'dashicons-money-alt', bg: '#2271b1', label: '<?php echo esc_js( __( "Presupuesto Asignado", "aura-suite" ) ); ?>', value: fmt(kpis.total_budget), sub: '' },
            { icon: 'dashicons-arrow-down-alt', bg: '#d63638', label: '<?php echo esc_js( __( "Egresos Ejecutados", "aura-suite" ) ); ?>', value: fmt(kpis.total_executed), sub: '' },
            { icon: 'dashicons-arrow-up-alt', bg: '#00a32a', label: '<?php echo esc_js( __( "Ingresos del Área", "aura-suite" ) ); ?>', value: fmt(kpis.total_income), sub: '' },
            { icon: pct > 100 ? 'dashicons-warning' : 'dashicons-yes-alt', bg: pct > 100 ? '#d63638' : '#00a32a', label: pct > 100 ? '<?php echo esc_js( __( "Sobreejecutado", "aura-suite" ) ); ?>' : '<?php echo esc_js( __( "Disponible", "aura-suite" ) ); ?>', value: pct > 100 ? fmt(kpis.overrun) : fmt(kpis.available), sub: '' },
            { icon: 'dashicons-chart-pie', bg: pctColor, label: '<?php echo esc_js( __( "% Ejecución", "aura-suite" ) ); ?>', value: pct + '%', sub: '' },
        ];
        items.forEach(function(it) {
            $c.append(
                '<div class="adb-kpi-card">'
                + '<div class="adb-kpi-icon" style="background:' + it.bg + '"><span class="dashicons ' + it.icon + '"></span></div>'
                + '<div>'
                + '<div class="adb-kpi-label">' + escHtml(it.label) + '</div>'
                + '<div class="adb-kpi-value">' + escHtml(it.value) + '</div>'
                + (it.sub ? '<div class="adb-kpi-sub">' + escHtml(it.sub) + '</div>' : '')
                + '</div>'
                + '</div>'
            );
        });
    }

    /* ── Gráfico de barras CSS ───────────────────────────────── */
    function renderChart(chartData) {
        var $c = $('#adb-chart').empty();
        if (!chartData || !chartData.length) {
            $c.html('<p style="color:#aaa;font-style:italic;"><?php echo esc_js( __( "Sin egresos registrados.", "aura-suite" ) ); ?></p>');
            return;
        }
        chartData.forEach(function(row) {
            $c.append(
                '<div class="adb-bar-row">'
                + '<div class="adb-bar-label" title="' + escHtml(row.name) + '">' + escHtml(row.name) + '</div>'
                + '<div class="adb-bar-track">'
                + '<div class="adb-bar-fill" style="width:' + row.pct + '%;background:' + row.color + ';"></div>'
                + '</div>'
                + '<div class="adb-bar-amt">' + fmt(row.total) + '</div>'
                + '</div>'
            );
        });
    }

    /* ── Alertas ─────────────────────────────────────────────── */
    function renderAlerts(kpis) {
        var $c = $('#adb-alerts').empty();
        if (!kpis || !kpis.alerts || !kpis.alerts.length) {
            $c.html('<p style="color:#00a32a;font-size:13px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:4px;"></span><?php echo esc_js( __( "Sin alertas activas.", "aura-suite" ) ); ?></p>');
            return;
        }
        kpis.alerts.forEach(function(a) {
            var cls   = a.is_exceeded ? 'exceeded' : 'threshold';
            var icon  = a.is_exceeded ? 'dashicons-warning' : 'dashicons-bell';
            var color = a.is_exceeded ? '#d63638' : '#dba617';
            $c.append(
                '<div class="adb-alert-row ' + cls + '">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;">'
                + '<span class="adb-alert-cat"><span class="dashicons ' + icon + '" style="color:' + color + ';vertical-align:middle;font-size:14px;margin-right:3px;"></span>' + escHtml(a.category_name) + '</span>'
                + '<span class="adb-alert-pct" style="color:' + color + ';">' + parseFloat(a.pct).toFixed(1) + '%</span>'
                + '</div>'
                + '<div style="font-size:11px;color:#646970;margin-top:2px;">'
                + escHtml('<?php echo esc_js( __( "Presupuesto:", "aura-suite" ) ); ?>') + ' ' + fmt(a.budget_amount) + ' &nbsp;·&nbsp; '
                + escHtml('<?php echo esc_js( __( "Ejecutado:", "aura-suite" ) ); ?>') + ' ' + fmt(a.executed)
                + '</div>'
                + '</div>'
            );
        });
    }

    /* ── Tabla de transacciones ──────────────────────────────── */
    function renderTransactions(txList, txCount, areaId) {
        // Badge de conteo
        var badge = $('#adb-tx-count-badge');
        if (txCount > 0) {
            badge.text(txCount + ' <?php echo esc_js( __( "total", "aura-suite" ) ); ?>').show();
        } else {
            badge.hide();
        }

        // Link "Ver todas"
        $('#adb-all-tx-link').attr('href', txListUrl + '&filter_area=' + areaId);

        var $c = $('#adb-transactions').empty();
        if (!txList || !txList.length) {
            $c.html('<p style="color:#aaa;font-style:italic;"><?php echo esc_js( __( "Sin transacciones registradas para esta área.", "aura-suite" ) ); ?></p>');
            return;
        }

        var html = '<table class="adb-tx-table">'
            + '<thead><tr>'
            + '<th><?php echo esc_js( __( "Fecha",      "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Categoría",  "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Descripción","aura-suite" ) ); ?></th>'
            + '<th style="text-align:right"><?php echo esc_js( __( "Monto",     "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Estado",     "aura-suite" ) ); ?></th>'
            + '<th><?php echo esc_js( __( "Creado por", "aura-suite" ) ); ?></th>'
            + '</tr></thead><tbody>';

        txList.forEach(function(t) {
            var isInc  = t.transaction_type === 'income';
            var amtClr = isInc ? '#00a32a' : '#d63638';
            var amtSgn = isInc ? '+' : '-';
            var catBg  = t.category_color || '#8c8f94';
            var stateMap = {
                pending:  { label: '<?php echo esc_js( __( "Pendiente", "aura-suite" ) ); ?>', bg: '#f39c12' },
                approved: { label: '<?php echo esc_js( __( "Aprobado",  "aura-suite" ) ); ?>', bg: '#00a32a' },
                rejected: { label: '<?php echo esc_js( __( "Rechazado", "aura-suite" ) ); ?>', bg: '#d63638' },
            };
            var st = stateMap[t.status] || stateMap.pending;
            var desc = t.description || '';
            if (desc.length > 50) desc = desc.substring(0, 50) + '…';

            html += '<tr>'
                + '<td style="white-space:nowrap;color:#646970;">' + escHtml(t.transaction_date || '') + '</td>'
                + '<td>'
                + (t.category_name
                    ? '<span class="adb-tx-badge" style="background:' + catBg + ';">' + escHtml(t.category_name) + '</span>'
                    : '<em style="color:#aaa">—</em>')
                + '</td>'
                + '<td title="' + escHtml(t.description || '') + '">' + escHtml(desc) + '</td>'
                + '<td style="text-align:right;white-space:nowrap;font-weight:600;color:' + amtClr + ';">'
                + amtSgn + fmt(t.amount)
                + '</td>'
                + '<td><span class="adb-tx-badge" style="background:' + st.bg + ';">' + st.label + '</span></td>'
                + '<td style="color:#646970;">' + escHtml(t.created_by_name || '') + '</td>'
                + '</tr>';
        });

        html += '</tbody></table>';
        $c.html(html);
    }

    /* ── Inicializar ─────────────────────────────────────────── */
    $(document).ready(function() {
        var currentAreaId = <?php echo (int) $_adb_area_id; ?>;

        if (currentAreaId) {
            loadDashboard(currentAreaId);
        }

        $('#adb-area-select').on('change', function() {
            currentAreaId = parseInt($(this).val(), 10);
            if (currentAreaId) loadDashboard(currentAreaId);
        });

        $('#adb-refresh-btn').on('click', function() {
            if (currentAreaId) loadDashboard(currentAreaId);
        });
    });

})(jQuery);
</script>
