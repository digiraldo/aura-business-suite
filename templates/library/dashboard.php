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
    <div class="wrap aura-app-context">

    <?php
    Aura_UI::render_page_header( [
        'variant'  => 'wow-vip',
        'title'    => __( 'Biblioteca — Dashboard', 'aura-business-suite' ),
        'subtitle' => __( 'Visión general de acervo, préstamos en curso, moras y reservas', 'aura-business-suite' ),
        'icon'     => 'dashicons-book',
        'actions'  => [
            [
                'label' => __( 'Catálogo', 'aura-business-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-library-books' ),
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-book-alt',
            ],
            [
                'label' => __( 'Préstamos', 'aura-business-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-library-loans' ),
                'class' => 'btn btn-glass btn-sm btn-lift',
                'icon'  => 'dashicons-id',
            ],
            [
                'label' => __( 'Ver Reportes', 'aura-business-suite' ),
                'url'   => admin_url( 'admin.php?page=aura-library-reports' ),
                'class' => 'btn btn-shimmer btn-indigo btn-sm btn-lift',
                'icon'  => 'dashicons-chart-bar',
            ],
        ],
    ] );

    Aura_UI::render_stats_grid( [
        [
            'id'       => 'kpi-total-books',
            'icon'     => 'dashicons-book-alt',
            'value'    => number_format_i18n( (int) $kpis['total_books'] ),
            'label'    => __( 'Libros en Catálogo', 'aura-business-suite' ),
            'variant'  => 'capital',
            'tooltip'  => __( 'Total de títulos registrados en biblioteca', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-available',
            'icon'     => 'dashicons-yes-alt',
            'value'    => number_format_i18n( (int) $kpis['available_copies'] ),
            'label'    => __( 'Ejemplares Disponibles', 'aura-business-suite' ),
            'variant'  => 'income',
            'tooltip'  => __( 'Copias físicas disponibles para préstamo inmediato', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-active-loans',
            'icon'     => 'dashicons-id',
            'value'    => number_format_i18n( (int) $kpis['active_loans'] ),
            'label'    => __( 'Préstamos Activos', 'aura-business-suite' ),
            'variant'  => 'balance',
            'tooltip'  => __( 'Libros actualmente prestados a lectores', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-overdue',
            'icon'     => 'dashicons-warning',
            'value'    => number_format_i18n( (int) $kpis['overdue_loans'] ),
            'label'    => __( 'Préstamos Vencidos', 'aura-business-suite' ),
            'variant'  => 'expense',
            'tooltip'  => __( 'Préstamos con fecha límite superada', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-reservations',
            'icon'     => 'dashicons-calendar',
            'value'    => number_format_i18n( (int) $kpis['pending_reservations'] ),
            'label'    => __( 'Reservas Pendientes', 'aura-business-suite' ),
            'variant'  => 'pending',
            'tooltip'  => __( 'Lectores esperando disponibilidad de ejemplares', 'aura-business-suite' ),
        ],
        [
            'id'       => 'kpi-fines',
            'icon'     => 'dashicons-money-alt',
            'value'    => '$' . number_format( (float) $kpis['pending_fines'], 2 ),
            'label'    => __( 'Multas Pendientes', 'aura-business-suite' ),
            'variant'  => 'expense',
            'tooltip'  => __( 'Monto acumulado por penalizaciones de mora', 'aura-business-suite' ),
        ],
    ], [ 'columns' => 6 ] );
    ?>

    <!-- ── 3 GRÁFICOS Chart.js ───────────────────────────────── -->
    <div class="aura-lib-charts-row fade-up">

        <div class="aura-glass-card card-lift aura-lib-chart-card aura-lib-chart-wide">
            <div class="aura-lib-chart-header">
                <h3><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Préstamos por mes (últimos 6 meses)', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-loans-chart-spinner"></div>
            </div>
            <div class="aura-lib-chart-canvas-wrap">
                <canvas id="aura-lib-loans-chart" height="120"></canvas>
            </div>
        </div>

        <div class="aura-glass-card card-lift aura-lib-chart-card">
            <div class="aura-lib-chart-header">
                <h3><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'Estado del catálogo', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-status-chart-spinner"></div>
            </div>
            <div class="aura-lib-chart-canvas-wrap">
                <canvas id="aura-lib-status-chart" height="160"></canvas>
            </div>
        </div>

        <div class="aura-glass-card card-lift aura-lib-chart-card">
            <div class="aura-lib-chart-header">
                <h3><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Préstamos por clasificación Dewey', 'aura-business-suite' ); ?></h3>
                <div class="aura-lib-chart-spinner spinner" id="aura-lib-dewey-chart-spinner"></div>
            </div>
            <div class="aura-lib-chart-canvas-wrap">
                <canvas id="aura-lib-dewey-chart" height="160"></canvas>
            </div>
        </div>

    </div><!-- .aura-lib-charts-row -->

    <!-- ── 3 LISTAS RÁPIDAS ──────────────────────────────────── -->
    <div class="aura-lib-lists-row fade-up">

        <!-- Lista 1: Préstamos vencidos -->
        <div class="aura-glass-card card-lift aura-lib-list-card">
            <div class="aura-lib-list-header">
                <h3 class="aura-lib-heading-danger">
                    <span class="pulse-dot aura-lib-dot-danger"></span>
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e( 'Préstamos vencidos', 'aura-business-suite' ); ?>
                </h3>
                <div class="spinner is-active" id="aura-lib-overdue-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-overdue-list" class="aura-lib-list-body">
                <div class="aura-lib-list-skeleton">
                    <div class="skeleton skel-line w-85"></div>
                    <div class="skeleton skel-line w-65"></div>
                    <div class="skeleton skel-line w-75"></div>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-loans' ) ); ?>" class="aura-lib-list-footer-link">
                <?php esc_html_e( 'Ver todos los préstamos →', 'aura-business-suite' ); ?>
            </a>
        </div>

        <!-- Lista 2: Libros más prestados -->
        <div class="aura-glass-card card-lift aura-lib-list-card">
            <div class="aura-lib-list-header">
                <h3 class="aura-lib-heading-amber">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e( 'Libros más prestados', 'aura-business-suite' ); ?>
                </h3>
                <div class="spinner is-active" id="aura-lib-top-books-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-top-books-list" class="aura-lib-list-body">
                <div class="aura-lib-list-skeleton">
                    <div class="skeleton skel-line w-85"></div>
                    <div class="skeleton skel-line w-65"></div>
                    <div class="skeleton skel-line w-75"></div>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-books' ) ); ?>" class="aura-lib-list-footer-link">
                <?php esc_html_e( 'Ver catálogo →', 'aura-business-suite' ); ?>
            </a>
        </div>

        <!-- Lista 3: Reservas recientes -->
        <div class="aura-glass-card card-lift aura-lib-list-card">
            <div class="aura-lib-list-header">
                <h3 class="aura-lib-heading-indigo">
                    <span class="dashicons dashicons-calendar"></span>
                    <?php esc_html_e( 'Reservas pendientes', 'aura-business-suite' ); ?>
                </h3>
                <div class="spinner is-active" id="aura-lib-reservations-spinner" style="float:none;margin:0;"></div>
            </div>
            <div id="aura-lib-reservations-list" class="aura-lib-list-body">
                <div class="aura-lib-list-skeleton">
                    <div class="skeleton skel-line w-85"></div>
                    <div class="skeleton skel-line w-65"></div>
                    <div class="skeleton skel-line w-75"></div>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-library-reservations' ) ); ?>" class="aura-lib-list-footer-link">
                <?php esc_html_e( 'Ver reservas →', 'aura-business-suite' ); ?>
            </a>
        </div>

    </div><!-- .aura-lib-lists-row -->

    <div class="adp-footer">
        <div>
            Desarrollado con ❤️ por <strong><a href="https://github.com/digiraldo" target="_blank" rel="noopener noreferrer" class="adp-footer-link"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="adp-footer-link__icon"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.757-1.333-1.757-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg> Diego Giraldo</a></strong> &bull; Versión <?php echo esc_html( defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0' ); ?> &bull; Aura Business Suite &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    </div><!-- .wrap.aura-app-context -->
</div><!-- .aura-app-wrapper -->
