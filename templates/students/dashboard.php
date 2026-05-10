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

<div class="wrap aura-students-dashboard">

    <!-- ─── CABECERA ─────────────────────────────────────────── -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups" style="font-size:28px;height:28px;vertical-align:middle;margin-right:6px;color:#8b5cf6;"></span>
        <?php _e( 'Dashboard de Estudiantes', 'aura-suite' ); ?>
    </h1>

    <?php if ( $can_create ) : ?>
    <a href="<?php echo admin_url( 'admin.php?page=aura-students-new' ); ?>" class="page-title-action">
        + <?php _e( 'Nuevo Estudiante', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>

    <?php if ( current_user_can( 'aura_students_courses_manage' ) || current_user_can( 'manage_options' ) ) : ?>
    <a href="<?php echo admin_url( 'admin.php?page=aura-students-courses' ); ?>" class="page-title-action">
        + <?php _e( 'Nuevo Curso', 'aura-suite' ); ?>
    </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ─── ALERTAS ──────────────────────────────────────────── -->

    <?php if ( $kpis['applicants_pending'] > 0 && $can_approve ) : ?>
    <div class="notice notice-warning is-dismissible">
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
            <a href="<?php echo admin_url( 'admin.php?page=aura-students-enrollments' ); ?>">
                <?php _e( 'Revisar solicitudes →', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <?php if ( $kpis['overdue_installments'] > 0 ) : ?>
    <div class="notice notice-error is-dismissible">
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
            <a href="<?php echo admin_url( 'admin.php?page=aura-students-paz-salvo' ); ?>">
                <?php _e( 'Ver paz y salvo →', 'aura-suite' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <!-- ─── FILA DE KPIs ─────────────────────────────────────── -->
    <div class="aura-stu-kpi-grid" id="aura-stu-kpis">

        <!-- 1 Estudiantes activos -->
        <div class="aura-stu-kpi-card aura-stu-kpi-violet">
            <div class="aura-stu-kpi-icon">🎓</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-active-students">
                    <?php echo number_format( $kpis['active_students'] ); ?>
                </span>
                <span class="aura-stu-kpi-label"><?php _e( 'Estudiantes activos', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 2 Postulantes pendientes -->
        <div class="aura-stu-kpi-card <?php echo $kpis['applicants_pending'] > 0 ? 'aura-stu-kpi-orange' : 'aura-stu-kpi-gray'; ?>">
            <div class="aura-stu-kpi-icon">⏳</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-applicants">
                    <?php echo number_format( $kpis['applicants_pending'] ); ?>
                </span>
                <span class="aura-stu-kpi-label"><?php _e( 'Solicitudes pendientes', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 3 Graduados este año -->
        <div class="aura-stu-kpi-card aura-stu-kpi-gold">
            <div class="aura-stu-kpi-icon">🏅</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-graduated">
                    <?php echo number_format( $kpis['graduated_year'] ); ?>
                </span>
                <span class="aura-stu-kpi-label">
                    <?php printf( __( 'Graduados %d', 'aura-suite' ), date( 'Y' ) ); ?>
                </span>
            </div>
        </div>

        <!-- 4 Cuotas vencidas -->
        <div class="aura-stu-kpi-card <?php echo $kpis['overdue_installments'] > 0 ? 'aura-stu-kpi-red' : 'aura-stu-kpi-green'; ?>">
            <div class="aura-stu-kpi-icon"><?php echo $kpis['overdue_installments'] > 0 ? '🔴' : '✅'; ?></div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-overdue">
                    <?php echo number_format( $kpis['overdue_installments'] ); ?>
                </span>
                <span class="aura-stu-kpi-label"><?php _e( 'Cuotas vencidas', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 5 Ingresos del mes -->
        <div class="aura-stu-kpi-card aura-stu-kpi-green">
            <div class="aura-stu-kpi-icon">💰</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-income-month">
                    <?php echo esc_html( $currency ); ?> <?php echo number_format( $kpis['income_month'], 2 ); ?>
                </span>
                <span class="aura-stu-kpi-label">
                    <?php printf( __( 'Ingresos en %s', 'aura-suite' ), date_i18n( 'F' ) ); ?>
                </span>
            </div>
        </div>

        <!-- 6 Saldo pendiente por cobrar -->
        <div class="aura-stu-kpi-card aura-stu-kpi-blue">
            <div class="aura-stu-kpi-icon">📈</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-projected">
                    <?php echo esc_html( $currency ); ?> <?php echo number_format( $kpis['projected_income'], 2 ); ?>
                </span>
                <span class="aura-stu-kpi-label"><?php _e( 'Saldo por cobrar', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 7 Total de perfiles registrados -->
        <div class="aura-stu-kpi-card aura-stu-kpi-indigo">
            <div class="aura-stu-kpi-icon">👥</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-total-students">
                    <?php echo number_format( $kpis['total_students'] ); ?>
                </span>
                <span class="aura-stu-kpi-label"><?php _e( 'Total de perfiles', 'aura-suite' ); ?></span>
            </div>
        </div>

        <!-- 8 Cursos activos -->
        <div class="aura-stu-kpi-card aura-stu-kpi-teal">
            <div class="aura-stu-kpi-icon">📚</div>
            <div class="aura-stu-kpi-body">
                <span class="aura-stu-kpi-value" id="kpi-active-courses">
                    <?php echo number_format( $kpis['active_courses'] ); ?>
                </span>
                <span class="aura-stu-kpi-label"><?php _e( 'Cursos activos', 'aura-suite' ); ?></span>
            </div>
        </div>

    </div><!-- /.aura-stu-kpi-grid -->

    <!-- ─── ACCESOS RÁPIDOS ───────────────────────────────────── -->
    <div class="aura-stu-quick-access">

        <div class="aura-stu-quick-card">
            <h3>📋 <?php _e( 'Gestión de Estudiantes', 'aura-suite' ); ?></h3>
            <ul>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-list' ); ?>">
                    <?php _e( 'Ver todos los estudiantes', 'aura-suite' ); ?>
                </a></li>
                <?php if ( $can_create ) : ?>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-new' ); ?>">
                    <?php _e( '+ Registrar estudiante manualmente', 'aura-suite' ); ?>
                </a></li>
                <?php endif; ?>
                <?php if ( $can_approve ) : ?>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-enrollments' ); ?>">
                    <?php _e( 'Revisar solicitudes de inscripción', 'aura-suite' ); ?>
                    <?php if ( $kpis['applicants_pending'] > 0 ) : ?>
                    <span class="awaiting-mod count-<?php echo $kpis['applicants_pending']; ?>"><?php echo $kpis['applicants_pending']; ?></span>
                    <?php endif; ?>
                </a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="aura-stu-quick-card">
            <h3>📚 <?php _e( 'Cursos y Programas', 'aura-suite' ); ?></h3>
            <ul>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-courses' ); ?>">
                    <?php _e( 'Ver todos los cursos', 'aura-suite' ); ?>
                </a></li>
                <?php if ( current_user_can( 'aura_students_courses_manage' ) || current_user_can( 'manage_options' ) ) : ?>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-courses' ); ?>">
                    <?php _e( '+ Crear nuevo curso', 'aura-suite' ); ?>
                </a></li>
                <?php endif; ?>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-enrollments' ); ?>">
                    <?php _e( 'Gestionar inscripciones', 'aura-suite' ); ?>
                </a></li>
            </ul>
        </div>

        <div class="aura-stu-quick-card">
            <h3>💰 <?php _e( 'Pagos y Finanzas', 'aura-suite' ); ?></h3>
            <ul>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-payments' ); ?>">
                    <?php _e( 'Estado de pagos', 'aura-suite' ); ?>
                </a></li>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-paz-salvo' ); ?>">
                    <?php _e( 'Paz y Salvo', 'aura-suite' ); ?>
                    <?php if ( $kpis['overdue_installments'] > 0 ) : ?>
                    <span class="awaiting-mod" style="background:#ef4444;"><?php echo $kpis['overdue_installments']; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-scholarships' ); ?>">
                    <?php _e( 'Gestionar becas', 'aura-suite' ); ?>
                </a></li>
            </ul>
        </div>

        <div class="aura-stu-quick-card">
            <h3>📊 <?php _e( 'Reportes', 'aura-suite' ); ?></h3>
            <ul>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-reports' ); ?>">
                    <?php _e( 'Reporte de inscripciones', 'aura-suite' ); ?>
                </a></li>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-reports' ); ?>">
                    <?php _e( 'Reporte de morosos', 'aura-suite' ); ?>
                </a></li>
                <li><a href="<?php echo admin_url( 'admin.php?page=aura-students-reports' ); ?>">
                    <?php _e( 'Proyección de ingresos', 'aura-suite' ); ?>
                </a></li>
            </ul>
        </div>

    </div><!-- /.aura-stu-quick-access -->

    <!-- ─── GRÁFICOS ─────────────────────────────────────────── -->
    <div class="aura-stu-charts-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin:24px 0;">

        <!-- Barras: Pagos recibidos vs Saldo pendiente -->
        <div class="aura-stu-chart-card" style="background:#fff;border:1px solid #e9d5ff;border-radius:8px;padding:20px;">
            <h3 style="margin:0 0 14px;color:#8b5cf6;font-size:15px;">
                📊 <?php _e( 'Pagos Recibidos vs. Nuevas Inscripciones (últimos 6 meses)', 'aura-suite' ); ?>
            </h3>
            <div id="chart-bars" style="min-height:220px;">
                <div class="aura-chart-loader" style="text-align:center;padding:50px 0;color:#aaa;">
                    <span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>
                    <?php _e( 'Cargando gráfico…', 'aura-suite' ); ?>
                </div>
            </div>
        </div>

        <!-- Dona: distribución por tipo de perfil -->
        <div class="aura-stu-chart-card" style="background:#fff;border:1px solid #e9d5ff;border-radius:8px;padding:20px;">
            <h3 style="margin:0 0 14px;color:#8b5cf6;font-size:15px;">
                🍩 <?php _e( 'Distribución por Tipo de Perfil', 'aura-suite' ); ?>
            </h3>
            <div id="chart-donut" style="min-height:220px;">
                <div class="aura-chart-loader" style="text-align:center;padding:50px 0;color:#aaa;">
                    <span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>
                    <?php _e( 'Cargando gráfico…', 'aura-suite' ); ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ─── ÚLTIMAS ACTIVIDADES ───────────────────────────────── -->
    <div class="aura-stu-activity-section" style="background:#fff;border:1px solid #e9d5ff;border-radius:8px;padding:20px;margin-bottom:24px;">
        <h2 style="margin:0 0 14px;font-size:15px;color:#8b5cf6;">
            🕐 <?php _e( 'Últimas Actividades', 'aura-suite' ); ?>
        </h2>
        <table class="wp-list-table widefat fixed" id="recent-activity-table">
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