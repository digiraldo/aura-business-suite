<?php
/**
 * Reportes — Módulo de Formularios (Fase 8)
 *
 * Tres pestañas JS (sin recarga de página):
 *  1. Actividad   — Formularios activos, envíos por mes
 *  2. Inscripciones — Postulaciones por curso
 *  3. Encuestas   — Asignaciones vs completadas
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! current_user_can( 'aura_forms_reports' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes permiso para acceder a esta página.', 'aura-suite' ) );
}

$nonce = wp_create_nonce( 'aura_forms_nonce' );
?>
<div class="wrap aura-forms-wrap aura-reports-wrap">

    <h1 class="wp-heading-inline"><?php esc_html_e( 'Reportes de Formularios', 'aura-suite' ); ?></h1>
    <hr class="wp-header-end">

    <!-- ── Pestañas (navegación JS, sin recarga) ── -->
    <nav class="nav-tab-wrapper" style="margin-bottom:0;">
        <a href="#" class="nav-tab nav-tab-active aura-report-tab-btn" data-tab="activity">
            <?php esc_html_e( 'Actividad', 'aura-suite' ); ?>
        </a>
        <a href="#" class="nav-tab aura-report-tab-btn" data-tab="enrollments">
            <?php esc_html_e( 'Inscripciones', 'aura-suite' ); ?>
        </a>
        <a href="#" class="nav-tab aura-report-tab-btn" data-tab="surveys">
            <?php esc_html_e( 'Encuestas', 'aura-suite' ); ?>
        </a>
    </nav>

    <!-- ── Contenido de las pestañas ── -->
    <div style="padding-top:20px;">

        <!-- Error global -->
        <div id="report-error" class="notice notice-error" style="display:none;"></div>

        <!-- ═══════ PESTAÑA 1 — ACTIVIDAD ═══════ -->
        <div id="tab-activity" class="aura-report-tab" style="display:block;">
            <div id="activity-loading" class="aura-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></div>
            <div id="activity-content" style="display:none;">
                <div class="aura-kpis-row">
                    <div class="aura-kpi-card"><span class="aura-kpi-value" id="kpi-active-forms">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Formularios activos', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card"><span class="aura-kpi-value" id="kpi-total-sub">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Total de respuestas', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card"><span class="aura-kpi-value" id="kpi-this-month">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Respuestas este mes', 'aura-suite' ); ?></span></div>
                </div>
                <div class="card aura-report-card" style="margin-bottom:20px;">
                    <h2><?php esc_html_e( 'Formularios por tipo', 'aura-suite' ); ?></h2>
                    <div id="by-type-table"></div>
                </div>
                <div class="card aura-report-card">
                    <h2><?php esc_html_e( 'Respuestas por mes (últimos 12 meses)', 'aura-suite' ); ?></h2>
                    <div class="aura-chart-wrap"><canvas id="chart-activity" height="100"></canvas></div>
                </div>
            </div>
        </div>

        <!-- ═══════ PESTAÑA 2 — INSCRIPCIONES ═══════ -->
        <div id="tab-enrollments" class="aura-report-tab" style="display:none;">
            <div id="enrollments-loading" class="aura-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></div>
            <div id="enrollments-content" style="display:none;">
                <div class="aura-kpis-row">
                    <div class="aura-kpi-card"><span class="aura-kpi-value" id="kpi-enr-total">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Total postulaciones', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--warning"><span class="aura-kpi-value" id="kpi-enr-pending">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--success"><span class="aura-kpi-value" id="kpi-enr-approved">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Aprobadas', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--danger"><span class="aura-kpi-value" id="kpi-enr-withdrawn">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Retiradas / Rechazadas', 'aura-suite' ); ?></span></div>
                </div>
                <div class="card aura-report-card" style="margin-bottom:20px;">
                    <h2><?php esc_html_e( 'Postulaciones por curso', 'aura-suite' ); ?></h2>
                    <div class="aura-chart-wrap"><canvas id="chart-enrollments" height="120"></canvas></div>
                </div>
                <div class="card aura-report-card">
                    <h2><?php esc_html_e( 'Detalle por curso', 'aura-suite' ); ?></h2>
                    <div id="enrollments-table-wrap" class="aura-table-wrap"></div>
                </div>
            </div>
        </div>

        <!-- ═══════ PESTAÑA 3 — ENCUESTAS ═══════ -->
        <div id="tab-surveys" class="aura-report-tab" style="display:none;">
            <div id="surveys-loading" class="aura-loading"><?php esc_html_e( 'Cargando…', 'aura-suite' ); ?></div>
            <div id="surveys-content" style="display:none;">
                <div class="aura-kpis-row">
                    <div class="aura-kpi-card"><span class="aura-kpi-value" id="kpi-srv-assigned">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Encuestas asignadas', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--success"><span class="aura-kpi-value" id="kpi-srv-completed">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Completadas', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--warning"><span class="aura-kpi-value" id="kpi-srv-pending">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--danger"><span class="aura-kpi-value" id="kpi-srv-expired">–</span><span class="aura-kpi-label"><?php esc_html_e( 'Expiradas', 'aura-suite' ); ?></span></div>
                    <div class="aura-kpi-card aura-kpi-card--info"><span class="aura-kpi-value" id="kpi-srv-rate">–%</span><span class="aura-kpi-label"><?php esc_html_e( 'Tasa de completación', 'aura-suite' ); ?></span></div>
                </div>
                <div class="card aura-report-card" style="margin-bottom:20px;">
                    <h2><?php esc_html_e( 'Encuestas: asignadas vs completadas', 'aura-suite' ); ?></h2>
                    <div class="aura-chart-wrap"><canvas id="chart-surveys" height="120"></canvas></div>
                </div>
                <div class="card aura-report-card">
                    <h2><?php esc_html_e( 'Detalle por encuesta', 'aura-suite' ); ?></h2>
                    <div id="surveys-table-wrap" class="aura-table-wrap"></div>
                </div>
            </div>
        </div>

    </div><!-- content wrapper -->

</div><!-- .wrap -->

<style>
.aura-reports-wrap .aura-kpis-row { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
.aura-reports-wrap .aura-kpi-card { flex:1; min-width:160px; background:#fff; border:1px solid #ddd; border-radius:6px; padding:16px 20px; text-align:center; }
.aura-reports-wrap .aura-kpi-card--success { border-top:3px solid #16a34a; }
.aura-reports-wrap .aura-kpi-card--warning { border-top:3px solid #d97706; }
.aura-reports-wrap .aura-kpi-card--danger  { border-top:3px solid #dc2626; }
.aura-reports-wrap .aura-kpi-card--info    { border-top:3px solid #2563eb; }
.aura-reports-wrap .aura-kpi-value { display:block; font-size:2rem; font-weight:700; color:#111827; line-height:1.2; }
.aura-reports-wrap .aura-kpi-label { display:block; font-size:.78rem; color:#6b7280; margin-top:4px; }
.aura-reports-wrap .aura-report-card { padding:20px; margin-bottom:20px; }
.aura-reports-wrap .aura-report-card h2 { font-size:1rem; margin:0 0 14px; padding-bottom:6px; border-bottom:1px solid #e5e7eb; }
.aura-reports-wrap .aura-chart-wrap { position:relative; }
.aura-reports-wrap .aura-table-wrap { overflow-x:auto; }
.aura-reports-wrap .aura-table-wrap table { width:100%; border-collapse:collapse; font-size:.875rem; }
.aura-reports-wrap .aura-table-wrap th { background:#f9fafb; text-align:left; padding:8px 10px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
.aura-reports-wrap .aura-table-wrap td { padding:8px 10px; border-bottom:1px solid #f3f4f6; }
.aura-reports-wrap .aura-table-wrap tr:hover td { background:#f9fafb; }
.aura-reports-wrap .aura-badge-pct { display:inline-block; padding:2px 8px; border-radius:12px; font-size:.75rem; font-weight:600; background:#d1fae5; color:#065f46; }
.aura-reports-wrap .aura-loading { color:#6b7280; font-style:italic; padding:20px 0; }
.aura-reports-wrap .by-type-list { display:flex; gap:10px; flex-wrap:wrap; }
.aura-reports-wrap .by-type-item { background:#f3f4f6; border-radius:6px; padding:6px 14px; font-size:.85rem; }
</style>

<script>
(function ($) {
    var nonce   = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

    var actionMap = {
        activity:    'aura_forms_report_activity',
        enrollments: 'aura_forms_report_enrollments',
        surveys:     'aura_forms_report_surveys'
    };

    // Rastrea qué pestañas ya fueron cargadas
    var loaded = {};

    // Instancias de Chart (para destruir al recargar si fuera necesario)
    var charts = {};

    var typeLabels = {
        generic:    '<?php echo esc_js( __( 'Genérico',    'aura-suite' ) ); ?>',
        enrollment: '<?php echo esc_js( __( 'Inscripción', 'aura-suite' ) ); ?>',
        survey:     '<?php echo esc_js( __( 'Encuesta',    'aura-suite' ) ); ?>',
        feedback:   '<?php echo esc_js( __( 'Feedback',    'aura-suite' ) ); ?>'
    };

    var cc = {
        green:  'rgba(22,163,74,0.8)',
        yellow: 'rgba(217,119,6,0.8)',
        red:    'rgba(220,38,38,0.8)',
        blue:   'rgba(37,99,235,0.8)',
        blueLine: 'rgb(37,99,235)'
    };

    function numFmt(n) {
        return Number(n).toLocaleString('es');
    }

    function makeChart(id, config) {
        var el = document.getElementById(id);
        if (!el) return;
        if (charts[id]) { charts[id].destroy(); }
        if (typeof Chart === 'undefined') return;
        charts[id] = new Chart(el, config);
    }

    // ── Cargar pestaña ────────────────────────────────────────
    function loadTab(tab) {
        if (loaded[tab]) return;

        var action = actionMap[tab];
        if (!action) return;

        $('#' + tab + '-loading').show();
        $('#' + tab + '-content').hide();
        $('#report-error').hide();

        $.post(ajaxUrl, { action: action, nonce: nonce })
            .done(function (res) {
                $('#' + tab + '-loading').hide();
                if (!res || !res.success) {
                    var msg = (res && res.data && res.data.message)
                        ? res.data.message
                        : '<?php echo esc_js( __( 'Error al cargar el reporte.', 'aura-suite' ) ); ?>';
                    $('#report-error').show().html('<p>' + msg + '</p>');
                    return;
                }
                renderTab(tab, res.data);
                $('#' + tab + '-content').show();
                loaded[tab] = true;
            })
            .fail(function () {
                $('#' + tab + '-loading').hide();
                $('#report-error').show().html('<p><?php echo esc_js( __( 'Error de conexión. Recarga la página e intenta de nuevo.', 'aura-suite' ) ); ?></p>');
            });
    }

    // ── Renderizar datos en la pestaña ────────────────────────
    function renderTab(tab, data) {
        if (tab === 'activity')    renderActivity(data);
        if (tab === 'enrollments') renderEnrollments(data);
        if (tab === 'surveys')     renderSurveys(data);
    }

    function renderActivity(data) {
        var k = data.kpis;
        $('#kpi-active-forms').text(numFmt(k.active_forms));
        $('#kpi-total-sub').text(numFmt(k.total_sub));
        $('#kpi-this-month').text(numFmt(k.this_month));

        var typeHtml = '<div class="by-type-list">';
        $.each(data.by_type, function (type, count) {
            typeHtml += '<div class="by-type-item"><strong>' + (typeLabels[type] || type) + '</strong>: ' + numFmt(count) + '</div>';
        });
        typeHtml += '</div>';
        $('#by-type-table').html(typeHtml);

        makeChart('chart-activity', {
            type: 'line',
            data: {
                labels: data.chart.labels,
                datasets: [{
                    label: '<?php echo esc_js( __( 'Respuestas', 'aura-suite' ) ); ?>',
                    data: data.chart.data,
                    borderColor: cc.blueLine,
                    backgroundColor: 'rgba(37,99,235,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } }
            }
        });
    }

    function renderEnrollments(data) {
        var k = data.kpis;
        $('#kpi-enr-total').text(numFmt(k.total));
        $('#kpi-enr-pending').text(numFmt(k.pending));
        $('#kpi-enr-approved').text(numFmt(k.approved));
        $('#kpi-enr-withdrawn').text(numFmt(k.withdrawn));

        if (data.chart.labels.length) {
            makeChart('chart-enrollments', {
                type: 'bar',
                data: {
                    labels: data.chart.labels,
                    datasets: [
                        { label: '<?php echo esc_js( __( 'Pendientes', 'aura-suite' ) ); ?>', data: data.chart.pending,   backgroundColor: cc.yellow },
                        { label: '<?php echo esc_js( __( 'Aprobadas',  'aura-suite' ) ); ?>', data: data.chart.approved,  backgroundColor: cc.green  },
                        { label: '<?php echo esc_js( __( 'Retiradas',  'aura-suite' ) ); ?>', data: data.chart.withdrawn, backgroundColor: cc.red    }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
            });
        }

        if (!data.table.length) {
            $('#enrollments-table-wrap').html('<em><?php echo esc_js( __( 'Sin datos de inscripciones todavía.', 'aura-suite' ) ); ?></em>');
            return;
        }
        var html = '<table><thead><tr>'
            + '<th><?php echo esc_js( __( 'Curso',       'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Pendientes',  'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Aprobadas',   'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Retiradas',   'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Total',       'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( '% Aprobadas', 'aura-suite' ) ); ?></th>'
            + '</tr></thead><tbody>';
        $.each(data.table, function (i, r) {
            html += '<tr>'
                + '<td>' + $('<span>').text(r.course).html() + '</td>'
                + '<td>' + numFmt(r.pending)   + '</td>'
                + '<td>' + numFmt(r.approved)  + '</td>'
                + '<td>' + numFmt(r.withdrawn) + '</td>'
                + '<td><strong>' + numFmt(r.total) + '</strong></td>'
                + '<td><span class="aura-badge-pct">' + r.pct + '%</span></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        $('#enrollments-table-wrap').html(html);
    }

    function renderSurveys(data) {
        var k = data.kpis;
        $('#kpi-srv-assigned').text(numFmt(k.total_assigned));
        $('#kpi-srv-completed').text(numFmt(k.total_completed));
        $('#kpi-srv-pending').text(numFmt(k.total_pending));
        $('#kpi-srv-expired').text(numFmt(k.total_expired));
        $('#kpi-srv-rate').text(k.completion_rate + '%');

        if (data.chart.labels.length) {
            makeChart('chart-surveys', {
                type: 'bar',
                data: {
                    labels: data.chart.labels,
                    datasets: [
                        { label: '<?php echo esc_js( __( 'Completadas', 'aura-suite' ) ); ?>', data: data.chart.completed, backgroundColor: cc.green  },
                        { label: '<?php echo esc_js( __( 'Pendientes',  'aura-suite' ) ); ?>', data: data.chart.pending,   backgroundColor: cc.yellow },
                        { label: '<?php echo esc_js( __( 'Expiradas',   'aura-suite' ) ); ?>', data: data.chart.expired,   backgroundColor: cc.red    }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
            });
        }

        if (!data.table.length) {
            $('#surveys-table-wrap').html('<em><?php echo esc_js( __( 'Sin encuestas asignadas todavía.', 'aura-suite' ) ); ?></em>');
            return;
        }
        var html = '<table><thead><tr>'
            + '<th><?php echo esc_js( __( 'Encuesta',    'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Asignadas',   'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Completadas', 'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Pendientes',  'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Expiradas',   'aura-suite' ) ); ?></th>'
            + '<th><?php echo esc_js( __( 'Tasa',        'aura-suite' ) ); ?></th>'
            + '</tr></thead><tbody>';
        $.each(data.table, function (i, r) {
            html += '<tr>'
                + '<td>' + $('<span>').text(r.title).html() + '</td>'
                + '<td>' + numFmt(r.total)     + '</td>'
                + '<td>' + numFmt(r.completed) + '</td>'
                + '<td>' + numFmt(r.pending)   + '</td>'
                + '<td>' + numFmt(r.expired)   + '</td>'
                + '<td><span class="aura-badge-pct">' + r.rate + '%</span></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        $('#surveys-table-wrap').html(html);
    }

    // ── Inicialización ────────────────────────────────────────
    $(function () {
        // Clic en pestañas
        $('.aura-report-tab-btn').on('click', function (e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            // Cambiar clase activa
            $('.aura-report-tab-btn').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Mostrar/ocultar secciones
            $('.aura-report-tab').hide();
            $('#tab-' + tab).show();

            // Cargar datos si aún no se cargaron
            loadTab(tab);
        });

        // Cargar la primera pestaña al entrar
        loadTab('activity');
    });

})(jQuery);
</script>