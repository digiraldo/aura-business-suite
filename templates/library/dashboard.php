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
<div class="aura-app-wrapper aura-library-dashboard" id="aura-lib-dashboard" data-nonce="<?php echo esc_attr( $nonce ); ?>">

    <?php
    $actions_html = '<a href="' . esc_url( admin_url( 'admin.php?page=aura-library-reports' ) ) . '" class="aura-btn aura-btn-secondary"><span class="dashicons dashicons-chart-bar"></span> ' . esc_html__( 'Ver Reportes', 'aura-business-suite' ) . '</a>';

    Aura_UI::render_page_header( [
        'variant'     => 'wow-vip',
        'title'       => __( 'Biblioteca — Dashboard', 'aura-business-suite' ),
        'subtitle'    => __( 'Visión general de acervo, préstamos en curso, moras y reservas', 'aura-business-suite' ),
        'icon'        => 'dashicons-book',
        'breadcrumbs' => [
            [ 'label' => __( 'Biblioteca', 'aura-business-suite' ), 'url' => admin_url( 'admin.php?page=aura-library' ) ],
            [ 'label' => __( 'Dashboard', 'aura-business-suite' ) ],
        ],
        'actions_html'=> $actions_html,
    ] );

    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-total-books',
            'icon'     => 'dashicons-book-alt',
            'value'    => number_format_i18n( (int) $kpis['total_books'] ),
            'label'    => __( 'Libros en Catálogo', 'aura-business-suite' ),
            'variant'  => 'primary',
            'tooltip'  => __( 'Total de títulos registrados en biblioteca', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-available',
            'icon'     => 'dashicons-yes-alt',
            'value'    => number_format_i18n( (int) $kpis['available_copies'] ),
            'label'    => __( 'Ejemplares Disponibles', 'aura-business-suite' ),
            'variant'  => 'success',
            'tooltip'  => __( 'Copias físicas disponibles para préstamo inmediato', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-active-loans',
            'icon'     => 'dashicons-id',
            'value'    => number_format_i18n( (int) $kpis['active_loans'] ),
            'label'    => __( 'Préstamos Activos', 'aura-business-suite' ),
            'variant'  => 'info',
            'tooltip'  => __( 'Libros actualmente prestados a lectores', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-overdue',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( (int) $kpis['overdue_loans'] ),
            'label'    => __( 'Préstamos Vencidos', 'aura-business-suite' ),
            'variant'  => 'danger',
            'tooltip'  => __( 'Préstamos con fecha límite superada', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-reservations',
            'icon'     => 'dashicons-calendar',
            'value'    => number_format_i18n( (int) $kpis['pending_reservations'] ),
            'label'    => __( 'Reservas Pendientes', 'aura-business-suite' ),
            'variant'  => 'warning',
            'tooltip'  => __( 'Lectores esperando disponibilidad de ejemplares', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-fines',
            'icon'     => 'dashicons-money-alt',
            'value'    => '$' . number_format( (float) $kpis['pending_fines'], 2 ),
            'label'    => __( 'Multas Pendientes', 'aura-business-suite' ),
            'variant'  => 'danger',
            'tooltip'  => __( 'Monto acumulado por penalizaciones de mora', 'aura-business-suite' ),
        ],
    ], [ 'columns' => 6 ] );
    ?>

    <!-- ── 3 GRÁFICOS Chart.js ───────────────────────────────── -->
    <div class="aura-lib-charts-row" style="margin-top: var(--aura-space-6, 24px);">

        <div class="aura-glass-card aura-lib-chart-card aura-lib-chart-wide">
            <div class="aura-lib-chart-header" style="margin-bottom:16px;">
                <h3 style="margin:0; font-size:15px; font-weight:600;"><?php esc_html_e( 'Préstamos por mes (últimos 6 meses)', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-loans-chart-spinner"></div>
            </div>
            <canvas id="aura-lib-loans-chart" height="120"></canvas>
        </div>

        <div class="aura-glass-card aura-lib-chart-card">
            <div class="aura-lib-chart-header" style="margin-bottom:16px;">
                <h3 style="margin:0; font-size:15px; font-weight:600;"><?php esc_html_e( 'Estado del catálogo', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-status-chart-spinner"></div>
            </div>
            <canvas id="aura-lib-status-chart" height="160"></canvas>
        </div>

        <div class="aura-glass-card aura-lib-chart-card">
            <div class="aura-lib-chart-header" style="margin-bottom:16px;">
                <h3 style="margin:0; font-size:15px; font-weight:600;"><?php esc_html_e( 'Préstamos por clasificación Dewey', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-dewey-chart-spinner"></div>
            </div>
            <canvas id="aura-lib-dewey-chart" height="160"></canvas>
        </div>

    </div><!-- .aura-lib-charts-row -->

    <!-- ── 3 LISTAS RÁPIDAS ──────────────────────────────────── -->
    <div class="aura-lib-lists-row" style="margin-top: var(--aura-space-6, 24px); display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">

        <!-- Lista 1: Préstamos vencidos -->
        <div class="aura-glass-card aura-lib-list-card">
            <div class="aura-lib-list-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; font-size:14px; font-weight:600; color:var(--aura-danger-600);"><span class="dashicons dashicons-warning" style="color:var(--aura-danger-500);"></span> <?php esc_html_e( 'Préstamos vencidos', 'aura-business-suite' ); ?></h3>
                <div class="spinner is-active" id="aura-lib-overdue-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-overdue-list">
                <p class="aura-lib-loading"><?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?></p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-loans' ) ); ?>" class="aura-lib-list-footer-link" style="display:inline-block; margin-top:12px; font-size:13px; font-weight:500; color:var(--aura-primary-600);">
                <?php esc_html_e( 'Ver todos los préstamos →', 'aura-business-suite' ); ?>
            </a>
        </div>

        <!-- Lista 2: Libros más prestados -->
        <div class="aura-glass-card aura-lib-list-card">
            <div class="aura-lib-list-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; font-size:14px; font-weight:600; color:var(--aura-warning-700);"><span class="dashicons dashicons-star-filled" style="color:var(--aura-warning-500);"></span> <?php esc_html_e( 'Libros más prestados', 'aura-business-suite' ); ?></h3>
                <div class="spinner is-active" id="aura-lib-top-books-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-top-books-list">
                <p class="aura-lib-loading"><?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?></p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-books' ) ); ?>" class="aura-lib-list-footer-link" style="display:inline-block; margin-top:12px; font-size:13px; font-weight:500; color:var(--aura-primary-600);">
                <?php esc_html_e( 'Ver catálogo →', 'aura-business-suite' ); ?>
            </a>
        </div>

        <!-- Lista 3: Reservas recientes -->
        <div class="aura-glass-card aura-lib-list-card">
            <div class="aura-lib-list-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h3 style="margin:0; font-size:14px; font-weight:600; color:var(--aura-primary-700);"><span class="dashicons dashicons-calendar" style="color:var(--aura-primary-500);"></span> <?php esc_html_e( 'Reservas pendientes', 'aura-business-suite' ); ?></h3>
                <div class="spinner is-active" id="aura-lib-reservations-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-reservations-list">
                <p class="aura-lib-loading"><?php esc_html_e( 'Cargando…', 'aura-business-suite' ); ?></p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-reservations' ) ); ?>" class="aura-lib-list-footer-link" style="display:inline-block; margin-top:12px; font-size:13px; font-weight:500; color:var(--aura-primary-600);">
                <?php esc_html_e( 'Ver reservas →', 'aura-business-suite' ); ?>
            </a>
        </div>

    </div><!-- .aura-lib-lists-row -->

</div><!-- .aura-app-wrapper -->
