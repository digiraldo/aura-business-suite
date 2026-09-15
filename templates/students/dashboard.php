<?php
/**
 * Template: Dashboard del Módulo de Estudiantes — Fase 1
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$kpis        = Aura_Students_Dashboard::get_kpis();
$can_create  = current_user_can( 'aura_students_create' ) || current_user_can( 'manage_options' );
$can_approve = current_user_can( 'aura_students_approve' ) || current_user_can( 'manage_options' );
$currency    = get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD';
?>

<div class="wrap aura-app-container aura-students-dashboard">

    <?php
    $header_actions = [];
    if ( $can_create ) {
        $header_actions[] = [
            'label' => __( 'Nuevo Estudiante', 'aura-suite' ),
            'url'   => admin_url( 'admin.php?page=aura-students-new' ),
            'icon'  => 'dashicons-plus-alt2',
            'class' => 'aura-btn-primary',
        ];
    }
    if ( current_user_can( 'aura_students_courses_manage' ) || current_user_can( 'manage_options' ) ) {
        $header_actions[] = [
            'label' => __( 'Nuevo Curso', 'aura-suite' ),
            'url'   => admin_url( 'admin.php?page=aura-students-courses' ),
            'icon'  => 'dashicons-welcome-learn-more',
            'class' => 'aura-btn-secondary',
        ];
    }

    Aura_UI::render_page_header( [
        'variant'     => 'wow-vip',
        'title'       => __( 'Dashboard de Estudiantes', 'aura-suite' ),
        'description' => __( 'Métricas generales de estudiantes, cursos, finanzas y actividades del instituto.', 'aura-suite' ),
        'icon'        => 'dashicons-groups',
        'badge'       => __( 'Académico', 'aura-suite' ),
        'actions'     => $header_actions,
    ] );
    ?>

    <!-- ─── ALERTAS ──────────────────────────────────────────── -->
    <?php if ( $kpis['applicants_pending'] > 0 && $can_approve ) : ?>
    <div class="notice notice-warning is-dismissible" style="border-radius:var(--aura-radius-md);margin-bottom:16px;">
        <p>
            <strong><?php _e( '⏳ Solicitudes pendientes:', 'aura-suite' ); ?></strong>
            <?php printf(
                _n(
                    'Hay %d solicitud de inscripción pendiente de revisión.',
                    'Hay %d solicitudes de inscripción pendientes de revisión.',
                    $kpis['applicants_pending'],
                    'aura-suite'
                ),
                $kpis['applicants_pending']
            ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-enrollments' ) ); ?>">
                <?php _e( 'Revisar solicitudes →', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <?php if ( $kpis['overdue_installments'] > 0 ) : ?>
    <div class="notice notice-error is-dismissible" style="border-radius:var(--aura-radius-md);margin-bottom:16px;">
        <p>
            <strong><?php _e( '🔴 Cuotas vencidas:', 'aura-suite' ); ?></strong>
            <?php printf(
                _n(
                    'Hay %d cuota vencida sin pago.',
                    'Hay %d cuotas vencidas sin pago.',
                    $kpis['overdue_installments'],
                    'aura-suite'
                ),
                $kpis['overdue_installments']
            ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-paz-salvo' ) ); ?>">
                <?php _e( 'Ver paz y salvo →', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- ─── FILA DE KPIs ─────────────────────────────────────── -->
    <?php
    $stats = [
        [
            'id'    => 'kpi-active-students',
            'label' => __( 'Estudiantes activos', 'aura-suite' ),
            'value' => number_format_i18n( $kpis['active_students'] ),
            'icon'  => 'dashicons-welcome-learn-more',
            'color' => 'primary',
        ],
        [
            'id'    => 'kpi-applicants',
            'label' => __( 'Solicitudes pendientes', 'aura-suite' ),
            'value' => number_format_i18n( $kpis['applicants_pending'] ),
            'icon'  => 'dashicons-clock',
            'color' => $kpis['applicants_pending'] > 0 ? 'warning' : 'neutral',
        ],
        [
            'id'    => 'kpi-graduated',
            'label' => sprintf( __( 'Graduados %d', 'aura-suite' ), date( 'Y' ) ),
            'value' => number_format_i18n( $kpis['graduated_year'] ),
            'icon'  => 'dashicons-awards',
            'color' => 'success',
        ],
        [
            'id'    => 'kpi-overdue',
            'label' => __( 'Cuotas vencidas', 'aura-suite' ),
            'value' => number_format_i18n( $kpis['overdue_installments'] ),
            'icon'  => 'dashicons-warning',
            'color' => $kpis['overdue_installments'] > 0 ? 'danger' : 'success',
        ],
        [
            'id'    => 'kpi-income-month',
            'label' => sprintf( __( 'Ingresos en %s', 'aura-suite' ), date_i18n( 'F' ) ),
            'value' => esc_html( $currency ) . ' ' . number_format_i18n( $kpis['income_month'], 2 ),
            'icon'  => 'dashicons-money-alt',
            'color' => 'success',
        ],
        [
            'id'    => 'kpi-projected',
            'label' => __( 'Saldo por cobrar', 'aura-suite' ),
            'value' => esc_html( $currency ) . ' ' . number_format_i18n( $kpis['projected_income'], 2 ),
            'icon'  => 'dashicons-chart-line',
            'color' => 'info',
        ],
        [
            'id'    => 'kpi-total-students',
            'label' => __( 'Total de perfiles', 'aura-suite' ),
            'value' => number_format_i18n( $kpis['total_students'] ),
            'icon'  => 'dashicons-admin-users',
            'color' => 'neutral',
        ],
        [
            'id'    => 'kpi-active-courses',
            'label' => __( 'Cursos activos', 'aura-suite' ),
            'value' => number_format_i18n( $kpis['active_courses'] ),
            'icon'  => 'dashicons-book',
            'color' => 'primary',
        ],
    ];

    Aura_UI::render_stats_grid( $stats, [ 'columns' => 4 ] );
    ?>

    <!-- ─── ACCESOS RÁPIDOS ───────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:16px;margin:24px 0;">

        <div class="aura-card" style="padding:16px;">
            <h3 style="margin:0 0 12px;font-size:15px;color:var(--aura-text-primary,#1e293b);display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-admin-users" style="color:var(--aura-primary,#6366f1);"></span>
                <?php _e( 'Gestión de Estudiantes', 'aura-suite' ); ?>
            </h3>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-list' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Ver todos los estudiantes', 'aura-suite' ); ?>
                </a></li>
                <?php if ( $can_create ) : ?>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-new' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    + <?php _e( 'Registrar estudiante manualmente', 'aura-suite' ); ?>
                </a></li>
                <?php endif; ?>
                <?php if ( $can_approve ) : ?>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-enrollments' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;display:flex;justify-content:space-between;align-items:center;">
                    <span>→ <?php _e( 'Revisar solicitudes', 'aura-suite' ); ?></span>
                    <?php if ( $kpis['applicants_pending'] > 0 ) : ?>
                    <span class="aura-badge aura-badge-warning"><?php echo $kpis['applicants_pending']; ?></span>
                    <?php endif; ?>
                </a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="aura-card" style="padding:16px;">
            <h3 style="margin:0 0 12px;font-size:15px;color:var(--aura-text-primary,#1e293b);display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-book" style="color:var(--aura-primary,#6366f1);"></span>
                <?php _e( 'Cursos y Programas', 'aura-suite' ); ?>
            </h3>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-courses' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Ver todos los cursos', 'aura-suite' ); ?>
                </a></li>
                <?php if ( current_user_can( 'aura_students_courses_manage' ) || current_user_can( 'manage_options' ) ) : ?>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-courses' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    + <?php _e( 'Crear nuevo curso', 'aura-suite' ); ?>
                </a></li>
                <?php endif; ?>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-enrollments' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Gestionar inscripciones', 'aura-suite' ); ?>
                </a></li>
            </ul>
        </div>

        <div class="aura-card" style="padding:16px;">
            <h3 style="margin:0 0 12px;font-size:15px;color:var(--aura-text-primary,#1e293b);display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-money-alt" style="color:var(--aura-primary,#6366f1);"></span>
                <?php _e( 'Pagos y Finanzas', 'aura-suite' ); ?>
            </h3>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-payments' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Estado de pagos', 'aura-suite' ); ?>
                </a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-paz-salvo' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;display:flex;justify-content:space-between;align-items:center;">
                    <span>→ <?php _e( 'Paz y Salvo', 'aura-suite' ); ?></span>
                    <?php if ( $kpis['overdue_installments'] > 0 ) : ?>
                    <span class="aura-badge aura-badge-danger"><?php echo $kpis['overdue_installments']; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-scholarships' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Gestionar becas', 'aura-suite' ); ?>
                </a></li>
            </ul>
        </div>

        <div class="aura-card" style="padding:16px;">
            <h3 style="margin:0 0 12px;font-size:15px;color:var(--aura-text-primary,#1e293b);display:flex;align-items:center;gap:8px;">
                <span class="dashicons dashicons-chart-bar" style="color:var(--aura-primary,#6366f1);"></span>
                <?php _e( 'Reportes', 'aura-suite' ); ?>
            </h3>
            <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-reports' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Reporte de inscripciones', 'aura-suite' ); ?>
                </a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-reports' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Reporte de morosos', 'aura-suite' ); ?>
                </a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-students-reports' ) ); ?>" style="text-decoration:none;color:var(--aura-primary,#6366f1);font-weight:500;">
                    → <?php _e( 'Proyección de ingresos', 'aura-suite' ); ?>
                </a></li>
            </ul>
        </div>

    </div>

    <!-- ─── GRÁFICOS ─────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:20px;margin:24px 0;">

        <!-- Barras: Pagos recibidos vs Saldo pendiente -->
        <div class="aura-card" style="padding:20px;">
            <div class="aura-card-header" style="margin-bottom:16px;">
                <h3 class="aura-card-title">
                    <span class="dashicons dashicons-chart-bar" style="color:var(--aura-primary,#6366f1);margin-right:6px;"></span>
                    <?php _e( 'Pagos Recibidos vs. Nuevas Inscripciones (últimos 6 meses)', 'aura-suite' ); ?>
                </h3>
            </div>
            <div id="chart-bars" style="min-height:220px;">
                <div class="aura-chart-loader" style="text-align:center;padding:50px 0;color:var(--aura-text-muted,#9ca3af);">
                    <span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>
                    <?php _e( 'Cargando gráfico…', 'aura-suite' ); ?>
                </div>
            </div>
        </div>

        <!-- Dona: distribución por tipo de perfil -->
        <div class="aura-card" style="padding:20px;">
            <div class="aura-card-header" style="margin-bottom:16px;">
                <h3 class="aura-card-title">
                    <span class="dashicons dashicons-chart-pie" style="color:var(--aura-primary,#6366f1);margin-right:6px;"></span>
                    <?php _e( 'Distribución por Tipo de Perfil', 'aura-suite' ); ?>
                </h3>
            </div>
            <div id="chart-donut" style="min-height:220px;">
                <div class="aura-chart-loader" style="text-align:center;padding:50px 0;color:var(--aura-text-muted,#9ca3af);">
                    <span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>
                    <?php _e( 'Cargando gráfico…', 'aura-suite' ); ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ─── ÚLTIMAS ACTIVIDADES ───────────────────────────────── -->
    <div class="aura-card" style="padding:20px;margin-bottom:24px;">
        <div class="aura-card-header" style="margin-bottom:16px;">
            <h3 class="aura-card-title">
                <span class="dashicons dashicons-backup" style="color:var(--aura-primary,#6366f1);margin-right:6px;"></span>
                <?php _e( 'Últimas Actividades', 'aura-suite' ); ?>
            </h3>
        </div>
        <div class="aura-table-responsive">
            <table class="aura-table" id="recent-activity-table">
                <thead>
                    <tr>
                        <th width="36"></th>
                        <th><?php _e( 'Actividad', 'aura-suite' ); ?></th>
                        <th width="160"><?php _e( 'Fecha', 'aura-suite' ); ?></th>
                    </tr>
                </thead>
                <tbody id="recent-activity-tbody">
                    <tr><td colspan="3" style="text-align:center;padding:16px;">
                        <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                        <?php _e( 'Cargando…', 'aura-suite' ); ?>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.aura-students-dashboard -->

<script>
jQuery(function($){
    'use strict';

    var nonce   = '<?php echo esc_js( wp_create_nonce( 'aura_students_nonce' ) ); ?>';
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    // ── Cargar datos para gráficos ────────────────────────────
    $.post(ajaxUrl, { action: 'aura_students_dashboard_charts', nonce: nonce }, function(res){
        if (!res.success) return;
        var bars = res.data.bars || [];
        var dist = res.data.profile_dist || [];

        // ── Gráfico de barras + línea ──
        var months     = bars.map(function(b){ return b.month; });
        var seriesPaid = bars.map(function(b){ return parseFloat(b.total_paid); });
        var seriesEnrl = bars.map(function(b){ return parseInt(b.new_enrollments); });

        if (typeof ApexCharts !== 'undefined') {
            // Barras: pagos recibidos
            var chartBars = new ApexCharts(document.querySelector('#chart-bars'), {
                series: [
                    { name: '<?php echo esc_js( __( 'Pagos recibidos ($)', 'aura-suite' ) ); ?>', type: 'bar', data: seriesPaid },
                    { name: '<?php echo esc_js( __( 'Nuevas inscripciones', 'aura-suite' ) ); ?>', type: 'line', data: seriesEnrl }
                ],
                chart: { height: 220, toolbar: { show: false }, background: 'transparent' },
                colors: ['#8b5cf6', '#06b6d4'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
                xaxis: { categories: months, labels: { style: { colors: '#888', fontSize: '11px' } } },
                yaxis: [
                    { labels: { formatter: function(v){ return '$' + v.toFixed(0); }, style: { colors: '#8b5cf6' } } },
                    { opposite: true, labels: { style: { colors: '#06b6d4' } } }
                ],
                tooltip: { shared: true },
                legend: { position: 'top', fontSize: '12px' }
            });
            chartBars.render();

            // Dona: distribución por tipo de perfil
            var profileLabels = { student:'Estudiante', volunteer:'Voluntario', teacher:'Instructor', participant:'Participante', intern:'Practicante' };
            var donutLabels = dist.map(function(d){ return profileLabels[d.type] || d.type; });
            var donutSeries = dist.map(function(d){ return d.total; });

            var chartDonut = new ApexCharts(document.querySelector('#chart-donut'), {
                series: donutSeries,
                labels: donutLabels,
                chart: { type: 'donut', height: 220, toolbar: { show: false } },
                colors: ['#8b5cf6','#06b6d4','#f59e0b','#10b981','#ef4444'],
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: { pie: { donut: { size: '55%' } } },
                dataLabels: { enabled: true, formatter: function(val){ return val.toFixed(1) + '%'; } }
            });
            chartDonut.render();
        } else {
            // Fallback si ApexCharts no está disponible
            $('#chart-bars').html('<p style="text-align:center;color:#888;padding:40px 0;"><?php echo esc_js( __( 'Gráficos no disponibles (ApexCharts no cargado).', 'aura-suite' ) ); ?></p>');
            $('#chart-donut').html('');
        }
    });


});
</script>