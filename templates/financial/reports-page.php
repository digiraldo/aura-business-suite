<?php
/**
 * Template: Reportes Financieros Predefinidos
 * Fase 3, Item 3.2
 *
 * @package AuraBusinessSuite
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Lista de usuarios para el filtro de auditoría (solo admins/view_all)
$can_view_all = current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' );
$users_list   = $can_view_all ? get_users( [ 'fields' => [ 'ID', 'display_name' ], 'orderby' => 'display_name' ] ) : [];

// Lista de áreas para el filtro de presupuesto
global $wpdb;
$_areas_for_report = $wpdb->get_results(
    "SELECT id, name, color FROM {$wpdb->prefix}aura_areas WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
);
?>
<div class="wrap aura-reports-wrap" id="aura-reports-app">

    <div class="aura-reports-header">
        <h1><?php esc_html_e( 'Reportes Financieros', 'aura-suite' ); ?></h1>
        <p class="aura-reports-subtitle"><?php esc_html_e( 'Genera, visualiza y exporta reportes predefinidos de tu módulo financiero.', 'aura-suite' ); ?></p>
    </div>

    <div class="aura-reports-layout">

        <!-- ══════════════════ PANEL IZQUIERDO: Configuración ══════════════════ -->
        <aside class="aura-reports-sidebar">

            <div class="aura-reports-card">
                <h2 class="aura-reports-card__title">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e( 'Configurar Reporte', 'aura-suite' ); ?>
                </h2>

                <form id="aura-report-form" autocomplete="off">
                    <?php wp_nonce_field( 'aura_reports_nonce', '_reports_nonce_field', false ); ?>

                    <!-- Tipo de reporte -->
                    <div class="aura-form-group">
                        <label for="report_type"><?php esc_html_e( 'Tipo de reporte', 'aura-suite' ); ?></label>
                        <select name="report_type" id="report_type" class="aura-select" required>
                            <option value=""><?php esc_html_e( '— Seleccionar —', 'aura-suite' ); ?></option>
                            <option value="pl">📊 <?php esc_html_e( 'Estado de Resultados (P&L)', 'aura-suite' ); ?></option>
                            <option value="cashflow">💵 <?php esc_html_e( 'Flujo de Efectivo', 'aura-suite' ); ?></option>
                            <option value="categories">🏷️ <?php esc_html_e( 'Análisis por Categoría', 'aura-suite' ); ?></option>
                            <option value="pending">⏳ <?php esc_html_e( 'Transacciones Pendientes', 'aura-suite' ); ?></option>
                            <option value="budget">📋 <?php esc_html_e( 'Presupuesto vs Ejecutado', 'aura-suite' ); ?></option>
                            <option value="budget_area_detail">🏢 <?php esc_html_e( 'Detalle por Área (transacciones por categoría)', 'aura-suite' ); ?></option>
                            <option value="user_payments">👥 <?php esc_html_e( 'Sueldos / Pagos a Usuarios', 'aura-suite' ); ?></option>
                            <?php if ( $can_view_all ) : ?>
                            <option value="audit">🔍 <?php esc_html_e( 'Auditoría Completa', 'aura-suite' ); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Período -->
                    <div class="aura-form-group" id="group-dates">
                        <label><?php esc_html_e( 'Período', 'aura-suite' ); ?></label>
                        <div class="aura-date-presets">
                            <button type="button" class="aura-preset-btn" data-preset="month"><?php esc_html_e( 'Este mes', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-preset-btn" data-preset="quarter"><?php esc_html_e( 'Trimestre', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-preset-btn" data-preset="year"><?php esc_html_e( 'Este año', 'aura-suite' ); ?></button>
                            <button type="button" class="aura-preset-btn" data-preset="prevmonth"><?php esc_html_e( 'Mes anterior', 'aura-suite' ); ?></button>
                        </div>
                        <div class="aura-date-range">
                            <input type="date" name="start" id="report_start" class="aura-input"
                                   value="<?php echo esc_attr( date( 'Y-m-01' ) ); ?>">
                            <span>—</span>
                            <input type="date" name="end" id="report_end" class="aura-input"
                                   value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                        </div>
                    </div>

                    <!-- Estado (oculto para pending y budget) -->
                    <div class="aura-form-group" id="group-status">
                        <label for="report_status"><?php esc_html_e( 'Estado de transacciones', 'aura-suite' ); ?></label>
                        <select name="status" id="report_status" class="aura-select">
                            <option value="approved"><?php esc_html_e( 'Aprobadas', 'aura-suite' ); ?></option>
                            <option value="all"><?php esc_html_e( 'Todas', 'aura-suite' ); ?></option>
                            <option value="pending"><?php esc_html_e( 'Pendientes', 'aura-suite' ); ?></option>
                            <option value="rejected"><?php esc_html_e( 'Rechazadas', 'aura-suite' ); ?></option>
                        </select>
                    </div>

                    <!-- Área (visible para budget y budget_area_detail) -->
                    <?php if ( ! empty( $_areas_for_report ) ) : ?>
                    <div class="aura-form-group" id="group-area" style="display:none;">
                        <label for="report_area"><?php esc_html_e( 'Área / Programa', 'aura-suite' ); ?></label>
                        <select name="area_id" id="report_area" class="aura-select">
                            <option value="0"><?php esc_html_e( '— Todas las áreas —', 'aura-suite' ); ?></option>
                            <?php foreach ( $_areas_for_report as $_ra ) : ?>
                                <option value="<?php echo esc_attr( $_ra->id ); ?>"><?php echo esc_html( $_ra->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#6b7280;margin-top:4px;display:block;">
                            <?php esc_html_e( '"Detalle por Área" requiere seleccionar un área específica.', 'aura-suite' ); ?>
                        </small>
                    </div>
                    <?php endif; ?>

                    <!-- Creado por (solo auditoría + admins) -->
                    <?php if ( $can_view_all ) : ?>
                    <div class="aura-form-group" id="group-creator" style="display:none;">
                        <label for="report_creator"><?php esc_html_e( 'Creado por (usuario)', 'aura-suite' ); ?></label>
                        <select name="created_by" id="report_creator" class="aura-select">
                            <option value="0"><?php esc_html_e( 'Todos los usuarios', 'aura-suite' ); ?></option>
                            <?php foreach ( $users_list as $u ) : ?>
                                <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Botón generar -->
                    <div class="aura-form-group">
                        <button type="submit" id="btn-generate" class="button button-primary aura-btn-generate" disabled>
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Generar Reporte', 'aura-suite' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Exportación -->
            <div class="aura-reports-card" id="aura-export-card" style="display:none;">
                <h2 class="aura-reports-card__title">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Exportar', 'aura-suite' ); ?>
                </h2>
                <div class="aura-export-buttons">
                    <button type="button" id="btn-export-csv" class="aura-export-btn aura-export-btn--csv">
                        <span class="dashicons dashicons-media-spreadsheet"></span> CSV
                    </button>
                    <button type="button" id="btn-export-excel" class="aura-export-btn aura-export-btn--excel">
                        <span class="dashicons dashicons-media-spreadsheet"></span> Excel (.xlsx)
                    </button>
                    <button type="button" id="btn-print" class="aura-export-btn aura-export-btn--print">
                        <span class="dashicons dashicons-printer"></span>
                        <?php esc_html_e( 'Imprimir / PDF', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

            <!-- Configuraciones guardadas -->
            <div class="aura-reports-card">
                <h2 class="aura-reports-card__title">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Mis configuraciones', 'aura-suite' ); ?>
                </h2>
                <div id="aura-saved-configs">
                    <p class="aura-empty-msg"><?php esc_html_e( 'No hay configuraciones guardadas.', 'aura-suite' ); ?></p>
                </div>
                <hr>
                <div class="aura-save-config-form">
                    <input type="text" id="config-name-input" class="aura-input"
                           placeholder="<?php esc_attr_e( 'Nombre de la configuración…', 'aura-suite' ); ?>" maxlength="60">
                    <button type="button" id="btn-save-config" class="button aura-btn-save-config">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Guardar configuración actual', 'aura-suite' ); ?>
                    </button>
                </div>
            </div>

        </aside>

        <!-- ══════════════════ PANEL DERECHO: Vista del Reporte ══════════════════ -->
        <main class="aura-reports-main" id="aura-report-output">

            <!-- Estado inicial -->
            <div class="aura-report-empty" id="aura-report-empty">
                <span class="dashicons dashicons-chart-bar aura-report-empty__icon"></span>
                <p><?php esc_html_e( 'Selecciona un tipo de reporte y haz clic en "Generar Reporte" para visualizar los resultados.', 'aura-suite' ); ?></p>
            </div>

            <!-- Loader -->
            <div class="aura-report-loader" id="aura-report-loader" style="display:none;">
                <div class="aura-spinner"></div>
                <p><?php esc_html_e( 'Generando reporte…', 'aura-suite' ); ?></p>
            </div>

            <!-- Contenido del reporte (renderizado por JS) -->
            <div id="aura-report-content" style="display:none;" class="aura-report-printable">

                <!-- Cabecera de impresión -->
                <div class="aura-print-header">
                    <div class="aura-print-header__logo">
                        <?php
                        $logo_url = get_site_icon_url( 60 );
                        if ( $logo_url ) {
                            echo '<img src="' . esc_url( $logo_url ) . '" alt="" class="aura-print-logo" loading="eager">';
                        }
                        ?>
                        <div class="aura-print-header__brand">
                            <strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
                            <span class="aura-print-header__tagline"><?php esc_html_e( 'Reporte Financiero', 'aura-suite' ); ?></span>
                        </div>
                    </div>
                    <div class="aura-print-header__info">
                        <span id="print-report-title"></span><br>
                        <small id="print-report-period"></small>
                    </div>
                    <div class="aura-print-header__meta">
                        <?php esc_html_e( 'Generado por', 'aura-suite' ); ?>: <strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong><br>
                        <span id="print-report-date"></span>
                    </div>
                </div>

                <!-- KPIs resumen del reporte -->
                <div id="report-kpis" class="aura-report-kpis" style="display:none;"></div>

                <!-- Gráfico del reporte -->
                <div id="report-chart-wrap" class="aura-report-chart-wrap" style="display:none;">
                    <canvas id="report-chart" height="300"></canvas>
                </div>

                <!-- Tabla principal del reporte -->
                <div id="report-table-wrap" class="aura-report-table-wrap"></div>

                <!-- Pie del reporte -->
                <div class="aura-report-footer">
                    <small><?php esc_html_e( 'Reporte generado automáticamente por Aura Business Suite', 'aura-suite' ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></small>
                </div>

            </div><!-- /#aura-report-content -->

        </main><!-- /.aura-reports-main -->

    </div><!-- /.aura-reports-layout -->

</div><!-- /.aura-reports-wrap -->
