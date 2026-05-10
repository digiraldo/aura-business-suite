<?php
/**
 * Template: Library Dashboard — Fase 6
 * 6 KPIs, 3 gráficos Chart.js, 3 listas rápidas.
 *
 * @package Aura_Business_Suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$kpis = class_exists( 'Aura_Library_Reports' ) ? Aura_Library_Reports::get_kpis() : [];
$kpis = array_merge( [
    'total_books'          => 0,
    'available_copies'     => 0,
    'active_loans'         => 0,
    'overdue_loans'        => 0,
    'pending_reservations' => 0,
    'pending_fines'        => 0.0,
], $kpis );

$nonce = wp_create_nonce( 'aura_library_nonce' );
?>
<div class="wrap aura-library-dashboard" id="aura-lib-dashboard" data-nonce="<?php echo esc_attr( $nonce ); ?>">

    <h1 class="aura-lib-dash-title">
        <span class="dashicons dashicons-book"></span>
        <?php esc_html_e( 'Biblioteca — Dashboard', 'aura-business-suite' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-reports' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Ver Reportes', 'aura-business-suite' ); ?>
        </a>
    </h1>

    <!-- ── 6 KPI CARDS ──────────────────────────────────────── -->
    <div class="aura-lib-kpi-grid">

        <div class="aura-lib-kpi-card">
            <div class="aura-lib-kpi-icon" style="background:#e8f4fd;color:#2271b1;">
                <span class="dashicons dashicons-book-alt"></span>
            </div>
            <div class="aura-lib-kpi-body">
                <div class="aura-lib-kpi-value" id="kpi-total-books"><?php echo esc_html( number_format( $kpis['total_books'] ) ); ?></div>
                <div class="aura-lib-kpi-label"><?php esc_html_e( 'Libros en catálogo', 'aura-business-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-lib-kpi-card">
            <div class="aura-lib-kpi-icon" style="background:#edfaed;color:#00a32a;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="aura-lib-kpi-body">
                <div class="aura-lib-kpi-value" id="kpi-available"><?php echo esc_html( number_format( $kpis['available_copies'] ) ); ?></div>
                <div class="aura-lib-kpi-label"><?php esc_html_e( 'Ejemplares disponibles', 'aura-business-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-lib-kpi-card">
            <div class="aura-lib-kpi-icon" style="background:#e8f4fd;color:#2271b1;">
                <span class="dashicons dashicons-id"></span>
            </div>
            <div class="aura-lib-kpi-body">
                <div class="aura-lib-kpi-value" id="kpi-active-loans"><?php echo esc_html( number_format( $kpis['active_loans'] ) ); ?></div>
                <div class="aura-lib-kpi-label"><?php esc_html_e( 'Préstamos activos', 'aura-business-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-lib-kpi-card aura-lib-kpi-card--alert">
            <div class="aura-lib-kpi-icon" style="background:#fce8e8;color:#d63638;">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="aura-lib-kpi-body">
                <div class="aura-lib-kpi-value" id="kpi-overdue"><?php echo esc_html( number_format( $kpis['overdue_loans'] ) ); ?></div>
                <div class="aura-lib-kpi-label"><?php esc_html_e( 'Préstamos vencidos', 'aura-business-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-lib-kpi-card">
            <div class="aura-lib-kpi-icon" style="background:#fef8e7;color:#996800;">
                <span class="dashicons dashicons-calendar"></span>
            </div>
            <div class="aura-lib-kpi-body">
                <div class="aura-lib-kpi-value" id="kpi-reservations"><?php echo esc_html( number_format( $kpis['pending_reservations'] ) ); ?></div>
                <div class="aura-lib-kpi-label"><?php esc_html_e( 'Reservas pendientes', 'aura-business-suite' ); ?></div>
            </div>
        </div>

        <div class="aura-lib-kpi-card">
            <div class="aura-lib-kpi-icon" style="background:#fce8e8;color:#b45309;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="aura-lib-kpi-body">
                <div class="aura-lib-kpi-value" id="kpi-fines"><?php echo esc_html( number_format( (float) $kpis['pending_fines'], 2 ) ); ?></div>
                <div class="aura-lib-kpi-label"><?php esc_html_e( 'Multas pendientes', 'aura-business-suite' ); ?></div>
            </div>
        </div>

    </div><!-- .aura-lib-kpi-grid -->

    <!-- ── 3 GRÁFICOS Chart.js ───────────────────────────────── -->
    <div class="aura-lib-charts-row">

        <div class="aura-lib-chart-card aura-lib-chart-wide">
            <div class="aura-lib-chart-header">
                <h3><?php esc_html_e( 'Préstamos por mes (últimos 6 meses)', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-loans-chart-spinner"></div>
            </div>
            <canvas id="aura-lib-loans-chart" height="120"></canvas>
        </div>

        <div class="aura-lib-chart-card">
            <div class="aura-lib-chart-header">
                <h3><?php esc_html_e( 'Estado del catálogo', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-status-chart-spinner"></div>
            </div>
            <canvas id="aura-lib-status-chart" height="160"></canvas>
        </div>

        <div class="aura-lib-chart-card">
            <div class="aura-lib-chart-header">
                <h3><?php esc_html_e( 'Préstamos por clasificación Dewey', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-dewey-chart-spinner"></div>
            </div>
            <canvas id="aura-lib-dewey-chart" height="160"></canvas>
        </div>

    </div><!-- .aura-lib-charts-row -->

    <!-- ── 3 LISTAS RÁPIDAS ──────────────────────────────────── -->
    <div class="aura-lib-lists-row">

        <!-- Lista 1: Préstamos vencidos -->
        <div class="aura-lib-list-card">
            <div class="aura-lib-list-header">
                <h3><span class="dashicons dashicons-warning" style="color:#d63638;"></span> <?php esc_html_e( 'Préstamos vencidos', 'aura-business-suite' ); ?></h3>
                <div class="spinner is-active" id="aura-lib-overdue-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-overdue-list">
                <p class="aura-lib-loading"><?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?></p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-loans' ) ); ?>" class="aura-lib-list-footer-link">
                <?php esc_html_e( 'Ver todos los préstamos →', 'aura-business-suite' ); ?>
            </a>
        </div>

        <!-- Lista 2: Libros más prestados -->
        <div class="aura-lib-list-card">
            <div class="aura-lib-list-header">
                <h3><span class="dashicons dashicons-star-filled" style="color:#996800;"></span> <?php esc_html_e( 'Libros más prestados', 'aura-business-suite' ); ?></h3>
                <div class="spinner is-active" id="aura-lib-top-books-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-top-books-list">
                <p class="aura-lib-loading"><?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?></p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-books' ) ); ?>" class="aura-lib-list-footer-link">
                <?php esc_html_e( 'Ver catálogo →', 'aura-business-suite' ); ?>
            </a>
        </div>

        <!-- Lista 3: Reservas recientes -->
        <div class="aura-lib-list-card">
            <div class="aura-lib-list-header">
                <h3><span class="dashicons dashicons-calendar" style="color:#2271b1;"></span> <?php esc_html_e( 'Reservas pendientes', 'aura-business-suite' ); ?></h3>
                <div class="spinner is-active" id="aura-lib-reservations-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-reservations-list">
                <p class="aura-lib-loading"><?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?></p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-reservations' ) ); ?>" class="aura-lib-list-footer-link">
                <?php esc_html_e( 'Ver reservas →', 'aura-business-suite' ); ?>
            </a>
        </div>

    </div><!-- .aura-lib-lists-row -->

</div><!-- .aura-library-dashboard -->
